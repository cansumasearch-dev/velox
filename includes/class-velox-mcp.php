<?php
/**
 * Velox connector — Claude (and other AI apps) work on this site with the
 * person's own Claude account.
 *
 * Velox is its own MCP server (Streamable HTTP, JSON-RPC over POST) with its
 * own OAuth 2.1 sign-in, so the site can be added in Claude as a custom
 * connector: Customize → Connectors → Add custom connector → paste the URL.
 * The person signs in to WordPress, chooses what Claude may work on and
 * allows it. No API key, no plugin, no Node.js, no config file.
 *
 *  - MCP endpoint:      /wp-json/velox/v1/mcp
 *  - Discovery:         /.well-known/oauth-protected-resource (RFC 9728)
 *                       /.well-known/oauth-authorization-server (RFC 8414)
 *  - Sign-in + consent: wp-admin/admin-post.php?action=velox_connect
 *  - Token, register, revoke: /wp-json/velox/v1/oauth/…
 *
 * Rules (the same as for every Velox agent): off until an administrator
 * switches it on; it acts as the WordPress user who allowed it, with that
 * user's builder rights, limited to the pages they chose; it only writes
 * drafts; every change is a version marked "By AI". Tokens are stored hashed
 * and can be revoked under Velox Builder → AI agents.
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_MCP {

	const GRANTS      = 'velox_mcp_grants';
	const CLIENTS     = 'velox_mcp_clients';
	const SEEN        = 'velox_mcp_seen';
	const VERSIONS    = array( '2025-11-25', '2025-06-18', '2025-03-26' );
	const ACCESS_TTL  = 3600;
	const REFRESH_TTL = 7776000; // 90 days, renewed with every refresh.
	const CODE_TTL    = 600;
	const GRACE       = 120;     // A refresh token just replaced still works this long (two requests at once).
	const MAX_CLIENTS = 200;

	/** The grant of the current MCP request. */
	private static $grant = null;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'well_known' ), 0 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_post_velox_connect', array( __CLASS__, 'authorize' ) );
		add_action( 'admin_post_nopriv_velox_connect', array( __CLASS__, 'authorize_login' ) );
		add_filter( 'determine_current_user', array( __CLASS__, 'bearer_user' ), 30 );
		add_filter( 'application_password_is_api_request', array( __CLASS__, 'not_app_password' ) );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'serve_empty' ), 10, 4 );
		add_filter( 'rest_allowed_cors_headers', array( __CLASS__, 'cors_allowed' ) );
		add_filter( 'rest_exposed_cors_headers', array( __CLASS__, 'cors_exposed' ) );
	}

	public static function enabled() {
		return Velox_Builder_Agents::enabled();
	}

	/* ================================================================ URLs */

	public static function issuer() {
		return untrailingslashit( home_url() );
	}

	public static function mcp_url() {
		return rest_url( 'velox/v1/mcp' );
	}

	/** Where the protected resource metadata is, as the MCP endpoint's 401 says. */
	public static function resource_metadata_url() {
		$p = wp_parse_url( self::mcp_url() );
		if ( '' === (string) wp_parse_url( home_url(), PHP_URL_PATH ) && ! empty( $p['path'] ) && empty( $p['query'] ) ) {
			return self::issuer() . '/.well-known/oauth-protected-resource' . $p['path'];
		}
		return rest_url( 'velox/v1/oauth/resource' );
	}

	public static function authorize_url() {
		return admin_url( 'admin-post.php?action=velox_connect' );
	}

	private static function same_resource( $a, $b ) {
		return untrailingslashit( (string) $a ) === untrailingslashit( (string) $b );
	}

	/* ============================================================ discovery */

	public static function resource_metadata() {
		return array(
			'resource'                 => self::mcp_url(),
			'authorization_servers'    => array( self::issuer() ),
			'bearer_methods_supported' => array( 'header' ),
			'scopes_supported'         => array( 'velox' ),
			'resource_name'            => sprintf( 'Velox — %s', get_bloginfo( 'name' ) ),
		);
	}

	public static function server_metadata() {
		$auth = array( 'none', 'client_secret_post', 'client_secret_basic' );
		return array(
			'issuer'                                  => self::issuer(),
			'authorization_endpoint'                  => self::authorize_url(),
			'token_endpoint'                          => rest_url( 'velox/v1/oauth/token' ),
			'registration_endpoint'                   => rest_url( 'velox/v1/oauth/register' ),
			'revocation_endpoint'                     => rest_url( 'velox/v1/oauth/revoke' ),
			'response_types_supported'                => array( 'code' ),
			'response_modes_supported'                => array( 'query' ),
			'grant_types_supported'                   => array( 'authorization_code', 'refresh_token' ),
			'code_challenge_methods_supported'        => array( 'S256' ),
			'token_endpoint_auth_methods_supported'   => $auth,
			'revocation_endpoint_auth_methods_supported' => $auth,
			'scopes_supported'                        => array( 'velox' ),
			'client_id_metadata_document_supported'   => true,
			'authorization_response_iss_parameter_supported' => true,
		);
	}

	/**
	 * Answer the discovery addresses before WordPress looks for a page. Clients
	 * try several spellings (with the issuer's path appended, or the OpenID
	 * name), so every one of them gets the same answer.
	 */
	public static function well_known() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( false === strpos( $uri, '/.well-known/' ) || ! preg_match( '#/\.well-known/(oauth-protected-resource|oauth-authorization-server|openid-configuration)(/|\?|$)#', $uri, $m ) ) {
			return;
		}
		if ( ! self::enabled() ) {
			return; // WordPress answers 404 as before.
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		self::send_json( 'oauth-protected-resource' === $m[1] ? self::resource_metadata() : self::server_metadata(), 200, array( 'Cache-Control' => 'public, max-age=300' ) );
	}

	private static function send_json( $data, $status = 200, $headers = array() ) {
		status_header( $status );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Authorization, Content-Type, MCP-Protocol-Version' );
		foreach ( $headers as $k => $v ) {
			header( $k . ': ' . $v );
		}
		if ( 'OPTIONS' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		exit;
	}

	/* =============================================================== routes */

	public static function routes() {
		$open = '__return_true'; // Each endpoint checks its own credentials (OAuth), and is off while the switch is off.
		register_rest_route( 'velox/v1', '/mcp', array(
			'methods'             => array( 'GET', 'POST', 'DELETE' ),
			'callback'            => array( __CLASS__, 'rest_mcp' ),
			'permission_callback' => $open,
		) );
		register_rest_route( 'velox/v1', '/oauth/token', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_token' ), 'permission_callback' => $open ) );
		register_rest_route( 'velox/v1', '/oauth/register', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_register' ), 'permission_callback' => $open ) );
		register_rest_route( 'velox/v1', '/oauth/revoke', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'rest_revoke' ), 'permission_callback' => $open ) );
		register_rest_route( 'velox/v1', '/oauth/resource', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'rest_resource' ), 'permission_callback' => $open ) );
		register_rest_route( 'velox/v1', '/oauth/server', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'rest_server' ), 'permission_callback' => $open ) );
	}

	private static function off() {
		return new WP_REST_Response( array( 'error' => 'temporarily_unavailable', 'error_description' => 'AI connections are switched off on this site (Velox Builder → AI agents).' ), 404 );
	}

	public static function rest_resource() {
		return self::enabled() ? new WP_REST_Response( self::resource_metadata(), 200 ) : self::off();
	}

	public static function rest_server() {
		return self::enabled() ? new WP_REST_Response( self::server_metadata(), 200 ) : self::off();
	}

	private static function is_our_route( $parts ) {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$uri = rawurldecode( $uri );
		foreach ( (array) $parts as $p ) {
			if ( false !== strpos( $uri, '/velox/v1/' . $p ) ) {
				return true;
			}
		}
		return false;
	}

	/** Client credentials sent as Basic are OAuth, not a WordPress application password. */
	public static function not_app_password( $is_api ) {
		return self::is_our_route( array( 'oauth/' ) ) ? false : $is_api;
	}

	/** A valid token on the MCP endpoint signs its user in (so plugins that block guests from the REST API let it through). */
	public static function bearer_user( $user ) {
		if ( $user || ! self::is_our_route( array( 'mcp' ) ) || ! self::enabled() ) {
			return $user;
		}
		$g = self::grant_for_access( self::bearer() );
		return $g ? (int) $g['user'] : $user;
	}

	public static function serve_empty( $served, $result, $request, $server ) {
		if ( ! $served && $result instanceof WP_REST_Response && 202 === $result->get_status() && 0 === strpos( (string) $request->get_route(), '/velox/v1/mcp' ) ) {
			return true; // 202 Accepted: no body.
		}
		return $served;
	}

	public static function cors_allowed( $h ) {
		return array_merge( (array) $h, array( 'MCP-Protocol-Version', 'Mcp-Session-Id', 'Last-Event-ID' ) );
	}

	public static function cors_exposed( $h ) {
		return array_merge( (array) $h, array( 'WWW-Authenticate', 'Mcp-Session-Id' ) );
	}

	/** The Authorization header, wherever the server put it. */
	private static function auth_header() {
		foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION' ) as $k ) {
			if ( ! empty( $_SERVER[ $k ] ) ) {
				return trim( (string) wp_unslash( $_SERVER[ $k ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			}
		}
		if ( function_exists( 'getallheaders' ) ) {
			foreach ( (array) getallheaders() as $k => $v ) {
				if ( 'authorization' === strtolower( (string) $k ) ) {
					return trim( (string) $v );
				}
			}
		}
		return '';
	}

	private static function bearer() {
		$h = self::auth_header();
		return preg_match( '/^Bearer\s+([A-Za-z0-9._~+\/-]+=*)$/i', $h, $m ) ? $m[1] : '';
	}

	/* =============================================================== tokens */

	private static function token( $prefix ) {
		return $prefix . rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
	}

	private static function hash( $t ) {
		return hash( 'sha256', (string) $t );
	}

	public static function grants() {
		$g = get_option( self::GRANTS, array() );
		return is_array( $g ) ? $g : array();
	}

	private static function save_grants( $g ) {
		update_option( self::GRANTS, $g, false );
	}

	private static function grant_for_access( $token ) {
		static $cache = array();
		if ( '' === $token || 0 !== strpos( $token, 'vxa_' ) ) {
			return null;
		}
		if ( array_key_exists( $token, $cache ) ) {
			return $cache[ $token ];
		}
		$h = self::hash( $token );
		$found = null;
		foreach ( self::grants() as $id => $g ) {
			if ( isset( $g['at'] ) && hash_equals( (string) $g['at'], $h ) && (int) $g['at_exp'] > time() && user_can( (int) $g['user'], 'velox_builder' ) ) {
				$found = $g + array( 'id' => $id );
				break;
			}
		}
		$cache[ $token ] = $found;
		return $found;
	}

	/** Last use per connection, kept apart from the grants so busy requests never rewrite the tokens. */
	public static function seen() {
		$s = get_option( self::SEEN, array() );
		return is_array( $s ) ? $s : array();
	}

	private static function touch( $gid ) {
		$s = self::seen();
		$t = time();
		if ( empty( $s[ $gid ]['t'] ) || $t - (int) $s[ $gid ]['t'] > 60 ) {
			$s[ $gid ] = array( 't' => $t, 'n' => (int) ( $s[ $gid ]['n'] ?? 0 ) + 1 );
			update_option( self::SEEN, $s, false );
		}
	}

	/** Make a connection (after the person allowed it). Returns the token response. */
	private static function issue( $client, $user, $docs ) {
		$grants = self::grants();
		// One connection per person and app: connecting again replaces it.
		foreach ( $grants as $id => $g ) {
			if ( (int) $g['user'] === (int) $user && (string) $g['client'] === (string) $client['client_id'] ) {
				unset( $grants[ $id ] );
			}
		}
		$at  = self::token( 'vxa_' );
		$rt  = self::token( 'vxr_' );
		$gid = 'g' . strtolower( wp_generate_password( 12, false, false ) );
		$grants[ $gid ] = array(
			'user'    => (int) $user,
			'client'  => (string) $client['client_id'],
			'name'    => (string) $client['name'],
			'kind'    => self::client_kind( $client['redirect_uris'] ),
			'host'    => self::redirect_host( $client['redirect_uris'] ),
			'docs'    => $docs,
			'created' => time(),
			'at'      => self::hash( $at ),
			'at_exp'  => time() + self::ACCESS_TTL,
			'rt'      => self::hash( $rt ),
			'rt_exp'  => time() + self::REFRESH_TTL,
		);
		self::save_grants( $grants );
		return self::token_response( $at, $rt );
	}

	private static function token_response( $at, $rt ) {
		return array( 'access_token' => $at, 'token_type' => 'Bearer', 'expires_in' => self::ACCESS_TTL, 'refresh_token' => $rt, 'scope' => 'velox' );
	}

	public static function revoke_grant( $gid ) {
		$g = self::grants();
		if ( isset( $g[ $gid ] ) ) {
			unset( $g[ $gid ] );
			self::save_grants( $g );
			$s = self::seen();
			unset( $s[ $gid ] );
			update_option( self::SEEN, $s, false );
			return true;
		}
		return false;
	}

	public static function set_scope( $gid, $docs ) {
		$g = self::grants();
		if ( ! isset( $g[ $gid ] ) ) {
			return false;
		}
		$g[ $gid ]['docs'] = $docs;
		self::save_grants( $g );
		return true;
	}

	/* ============================================================== clients */

	private static function clients() {
		$c = get_option( self::CLIENTS, array() );
		return is_array( $c ) ? $c : array();
	}

	/**
	 * A client by its id: registered here (dynamic registration), or an https
	 * address that serves the client's own description (Client ID Metadata Document).
	 */
	private static function client( $client_id ) {
		$client_id = (string) $client_id;
		if ( '' === $client_id || strlen( $client_id ) > 512 ) {
			return null;
		}
		if ( 0 === strpos( $client_id, 'https://' ) ) {
			return self::client_document( $client_id );
		}
		$all = self::clients();
		if ( ! isset( $all[ $client_id ] ) ) {
			return null;
		}
		return $all[ $client_id ] + array( 'client_id' => $client_id );
	}

	private static function client_document( $url ) {
		$key = 'velox_mcp_cimd_' . md5( $url );
		$doc = get_transient( $key );
		if ( ! is_array( $doc ) ) {
			$res = wp_safe_remote_get( $url, array( 'timeout' => 6, 'redirection' => 0, 'limit_response_size' => 20000, 'headers' => array( 'Accept' => 'application/json' ) ) );
			if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				return null;
			}
			$doc = json_decode( (string) wp_remote_retrieve_body( $res ), true );
			if ( ! is_array( $doc ) || ( $doc['client_id'] ?? '' ) !== $url || empty( $doc['redirect_uris'] ) || ! is_array( $doc['redirect_uris'] ) ) {
				return null;
			}
			$method = (string) ( $doc['token_endpoint_auth_method'] ?? 'none' );
			if ( 'none' !== $method ) {
				return null; // Velox has no way to check a document client's secret.
			}
			set_transient( $key, $doc, HOUR_IN_SECONDS );
		}
		$uris = array_values( array_filter( array_map( 'strval', (array) $doc['redirect_uris'] ), array( __CLASS__, 'redirect_ok' ) ) );
		if ( ! $uris ) {
			return null;
		}
		return array(
			'client_id'     => $url,
			'name'          => self::clean_name( $doc['client_name'] ?? wp_parse_url( $url, PHP_URL_HOST ) ),
			'redirect_uris' => $uris,
			'auth'          => 'none',
			'cimd'          => true,
		);
	}

	private static function clean_name( $n ) {
		$n = trim( wp_strip_all_tags( (string) $n ) );
		$n = function_exists( 'mb_substr' ) ? mb_substr( $n, 0, 80 ) : substr( $n, 0, 80 );
		return '' === $n ? __( 'AI app', 'velox' ) : $n;
	}

	/** Allowed redirect addresses: https, http only to this computer, or an app's own scheme. */
	public static function redirect_ok( $uri ) {
		$p = wp_parse_url( (string) $uri );
		if ( ! $p || empty( $p['scheme'] ) || false !== strpos( (string) $uri, '#' ) ) {
			return false;
		}
		$s = strtolower( $p['scheme'] );
		if ( 'https' === $s ) {
			return ! empty( $p['host'] );
		}
		if ( 'http' === $s ) {
			return self::loopback( $p['host'] ?? '' );
		}
		return ! in_array( $s, array( 'javascript', 'data', 'vbscript', 'file', 'about', 'blob', 'ftp', 'ws', 'wss' ), true ) && preg_match( '/^[a-z][a-z0-9+.-]*$/', $s );
	}

	private static function loopback( $host ) {
		return in_array( strtolower( trim( (string) $host, '[]' ) ), array( 'localhost', '127.0.0.1', '::1' ), true );
	}

	/** Exact match; for this computer (loopback) any port, as apps pick a free one (RFC 8252). */
	private static function redirect_match( $given, $registered ) {
		foreach ( (array) $registered as $r ) {
			if ( $given === $r ) {
				return true;
			}
			$a = wp_parse_url( $given );
			$b = wp_parse_url( $r );
			if ( $a && $b && 'http' === ( $a['scheme'] ?? '' ) && 'http' === ( $b['scheme'] ?? '' ) && self::loopback( $a['host'] ?? '' ) && self::loopback( $b['host'] ?? '' )
				&& ( $a['path'] ?? '/' ) === ( $b['path'] ?? '/' ) && ( $a['query'] ?? '' ) === ( $b['query'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/** claude = Claude (claude.ai / claude.com), local = an app on this computer, other. */
	public static function client_kind( $uris ) {
		$kind = 'other';
		foreach ( (array) $uris as $u ) {
			$h = strtolower( (string) wp_parse_url( $u, PHP_URL_HOST ) );
			if ( 'https' === wp_parse_url( $u, PHP_URL_SCHEME ) && preg_match( '/(^|\.)claude\.(ai|com)$/', $h ) ) {
				return 'claude';
			}
			if ( self::loopback( $h ) ) {
				$kind = 'local';
			}
		}
		return $kind;
	}

	private static function redirect_host( $uris ) {
		$u = (string) ( ( (array) $uris )[0] ?? '' );
		$h = (string) wp_parse_url( $u, PHP_URL_HOST );
		return '' !== $h ? $h : (string) wp_parse_url( $u, PHP_URL_SCHEME ) . ':';
	}

	/** Dynamic Client Registration (RFC 7591). */
	public static function rest_register( WP_REST_Request $r ) {
		if ( ! self::enabled() ) {
			return self::off();
		}
		$in = $r->get_json_params();
		if ( ! is_array( $in ) ) {
			$in = $r->get_body_params();
		}
		$uris = array_values( array_unique( array_map( 'strval', (array) ( $in['redirect_uris'] ?? array() ) ) ) );
		if ( ! $uris || count( $uris ) > 10 ) {
			return self::oauth_error( 'invalid_redirect_uri', 'Give one to ten redirect_uris.', 400 );
		}
		foreach ( $uris as $u ) {
			if ( strlen( $u ) > 500 || ! self::redirect_ok( $u ) ) {
				return self::oauth_error( 'invalid_redirect_uri', 'Redirect addresses must use https, http to localhost, or an app\'s own scheme.', 400 );
			}
		}
		// A small brake against floods: registrations are open to anyone, by design.
		$burst = (int) get_transient( 'velox_mcp_reg_burst' );
		if ( $burst > 40 ) {
			return self::oauth_error( 'temporarily_unavailable', 'Too many registrations. Try again in an hour.', 429 );
		}
		set_transient( 'velox_mcp_reg_burst', $burst + 1, HOUR_IN_SECONDS );

		$method = (string) ( $in['token_endpoint_auth_method'] ?? 'none' );
		if ( ! in_array( $method, array( 'none', 'client_secret_post', 'client_secret_basic' ), true ) ) {
			$method = 'none';
		}
		$id     = 'vx_' . strtolower( wp_generate_password( 24, false, false ) );
		$secret = 'none' === $method ? '' : self::token( 'vxs_' );
		$all    = self::clients();
		if ( count( $all ) >= self::MAX_CLIENTS ) {
			// Make room: drop the oldest clients that never connected.
			$in_use = array_column( self::grants(), 'client' );
			uasort( $all, function ( $a, $b ) { return (int) $a['created'] - (int) $b['created']; } );
			foreach ( array_keys( $all ) as $cid ) {
				if ( count( $all ) < self::MAX_CLIENTS ) {
					break;
				}
				if ( ! in_array( $cid, $in_use, true ) ) {
					unset( $all[ $cid ] );
				}
			}
		}
		$all[ $id ] = array(
			'name'          => self::clean_name( $in['client_name'] ?? '' ),
			'redirect_uris' => $uris,
			'auth'          => $method,
			'secret'        => '' === $secret ? '' : self::hash( $secret ),
			'created'       => time(),
		);
		update_option( self::CLIENTS, $all, false );
		$out = array(
			'client_id'                  => $id,
			'client_id_issued_at'        => time(),
			'client_name'                => $all[ $id ]['name'],
			'redirect_uris'              => $uris,
			'grant_types'                => array( 'authorization_code', 'refresh_token' ),
			'response_types'             => array( 'code' ),
			'token_endpoint_auth_method' => $method,
			'scope'                      => 'velox',
		);
		if ( '' !== $secret ) {
			$out['client_secret']            = $secret;
			$out['client_secret_expires_at'] = 0;
		}
		$res = new WP_REST_Response( $out, 201 );
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	private static function oauth_error( $code, $text, $status = 400 ) {
		$res = new WP_REST_Response( array( 'error' => $code, 'error_description' => $text ), $status );
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	/** The client's id and secret from the request (Basic header or body). */
	private static function client_credentials( WP_REST_Request $r ) {
		$id = (string) $r->get_param( 'client_id' );
		$secret = (string) $r->get_param( 'client_secret' );
		$h = self::auth_header();
		if ( preg_match( '/^Basic\s+(.+)$/i', $h, $m ) ) {
			$pair = base64_decode( trim( $m[1] ), true );
			if ( false !== $pair && false !== strpos( $pair, ':' ) ) {
				list( $bid, $bsec ) = explode( ':', $pair, 2 );
				$id     = '' !== $id ? $id : rawurldecode( $bid );
				$secret = rawurldecode( $bsec );
			}
		}
		return array( $id, $secret );
	}

	private static function client_auth_ok( $client, $secret ) {
		if ( 'none' === ( $client['auth'] ?? 'none' ) || empty( $client['secret'] ) ) {
			return true;
		}
		return '' !== $secret && hash_equals( (string) $client['secret'], self::hash( $secret ) );
	}

	/* =============================================================== token */

	public static function rest_token( WP_REST_Request $r ) {
		if ( ! self::enabled() ) {
			return self::off();
		}
		list( $cid, $secret ) = self::client_credentials( $r );
		$client = self::client( $cid );
		if ( ! $client || ! self::client_auth_ok( $client, $secret ) ) {
			return self::oauth_error( 'invalid_client', 'Unknown client or wrong client secret.', 401 );
		}
		$resource = (string) $r->get_param( 'resource' );
		if ( '' !== $resource && ! self::same_resource( $resource, self::mcp_url() ) && ! self::same_resource( $resource, self::issuer() ) ) {
			return self::oauth_error( 'invalid_target', 'This server only issues tokens for ' . self::mcp_url(), 400 );
		}
		$type = (string) $r->get_param( 'grant_type' );

		if ( 'authorization_code' === $type ) {
			$code = (string) $r->get_param( 'code' );
			$key  = 'velox_mcp_code_' . self::hash( $code );
			$c    = '' !== $code ? get_transient( $key ) : false;
			delete_transient( $key ); // One use only, whatever happens next.
			if ( ! is_array( $c ) || (int) $c['exp'] < time() ) {
				return self::oauth_error( 'invalid_grant', 'The code is unknown, used or expired. Connect again.' );
			}
			if ( $c['client'] !== $client['client_id'] || (string) $r->get_param( 'redirect_uri' ) !== $c['redirect'] ) {
				return self::oauth_error( 'invalid_grant', 'The code was issued to another client or redirect address.' );
			}
			$verifier = (string) $r->get_param( 'code_verifier' );
			$expect   = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
			if ( ! preg_match( '/^[A-Za-z0-9._~-]{43,128}$/', $verifier ) || ! hash_equals( (string) $c['challenge'], $expect ) ) {
				return self::oauth_error( 'invalid_grant', 'PKCE check failed (code_verifier).' );
			}
			if ( ! user_can( (int) $c['user'], 'velox_builder' ) ) {
				return self::oauth_error( 'invalid_grant', 'This WordPress user may no longer use Velox Builder.' );
			}
			$res = new WP_REST_Response( self::issue( $client, (int) $c['user'], $c['docs'] ), 200 );
		} elseif ( 'refresh_token' === $type ) {
			$rt     = (string) $r->get_param( 'refresh_token' );
			$h      = self::hash( $rt );
			$grants = self::grants();
			$gid    = '';
			$prev   = false;
			foreach ( $grants as $id => $g ) {
				if ( isset( $g['rt'] ) && hash_equals( (string) $g['rt'], $h ) ) {
					$gid = $id;
					break;
				}
				if ( ! empty( $g['rt_prev'] ) && hash_equals( (string) $g['rt_prev'], $h ) && (int) ( $g['rt_prev_until'] ?? 0 ) > time() ) {
					$gid  = $id;
					$prev = true;
					break;
				}
			}
			if ( '' === $gid || $grants[ $gid ]['client'] !== $client['client_id'] || (int) $grants[ $gid ]['rt_exp'] < time() ) {
				return self::oauth_error( 'invalid_grant', 'The refresh token is unknown or expired. Connect again.' );
			}
			if ( ! user_can( (int) $grants[ $gid ]['user'], 'velox_builder' ) ) {
				self::revoke_grant( $gid );
				return self::oauth_error( 'invalid_grant', 'This WordPress user may no longer use Velox Builder.' );
			}
			$at = self::token( 'vxa_' );
			$nr = self::token( 'vxr_' );
			$g  = $grants[ $gid ];
			if ( ! $prev ) {
				$g['rt_prev']       = $g['rt'];
				$g['rt_prev_until'] = time() + self::GRACE;
			}
			$g['rt']     = self::hash( $nr );
			$g['rt_exp'] = time() + self::REFRESH_TTL;
			$g['at']     = self::hash( $at );
			$g['at_exp'] = time() + self::ACCESS_TTL;
			$grants[ $gid ] = $g;
			self::save_grants( $grants );
			$res = new WP_REST_Response( self::token_response( $at, $nr ), 200 );
		} else {
			return self::oauth_error( 'unsupported_grant_type', 'Use authorization_code or refresh_token.' );
		}
		$res->header( 'Cache-Control', 'no-store' );
		$res->header( 'Pragma', 'no-cache' );
		return $res;
	}

	/** Token revocation (RFC 7009): always 200. */
	public static function rest_revoke( WP_REST_Request $r ) {
		if ( ! self::enabled() ) {
			return self::off();
		}
		list( $cid, $secret ) = self::client_credentials( $r );
		$client = self::client( $cid );
		$h = self::hash( (string) $r->get_param( 'token' ) );
		if ( $client && self::client_auth_ok( $client, $secret ) ) {
			foreach ( self::grants() as $id => $g ) {
				if ( $g['client'] === $client['client_id'] && ( hash_equals( (string) $g['at'], $h ) || hash_equals( (string) $g['rt'], $h ) ) ) {
					self::revoke_grant( $id );
				}
			}
		}
		return new WP_REST_Response( new stdClass(), 200 );
	}

	/* ================================================== sign-in and consent */

	/** Not signed in: to the WordPress login, then back here. */
	public static function authorize_login() {
		$qs = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		nocache_headers();
		wp_safe_redirect( wp_login_url( admin_url( 'admin-post.php?' . $qs ) ) );
		exit;
	}

	private static function param( $k ) {
		// phpcs:ignore WordPress.Security.NonceVerification -- OAuth parameters; the decision itself is checked with a nonce.
		$v = $_POST[ $k ] ?? ( $_GET[ $k ] ?? '' );
		return is_string( $v ) ? trim( (string) wp_unslash( $v ) ) : '';
	}

	/** The authorization endpoint: checks the request, then asks the person. */
	public static function authorize() {
		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		header( 'X-Frame-Options: DENY' );
		header( "Content-Security-Policy: frame-ancestors 'none'" );

		$p = array();
		foreach ( array( 'response_type', 'client_id', 'redirect_uri', 'state', 'code_challenge', 'code_challenge_method', 'scope', 'resource' ) as $k ) {
			$p[ $k ] = self::param( $k );
		}
		if ( ! self::enabled() ) {
			self::page_error( __( 'AI connections are switched off', 'velox' ), __( 'An administrator can switch them on under Velox Builder → AI agents.', 'velox' ) );
		}
		$client = self::client( $p['client_id'] );
		// A wrong client or redirect address is shown here, never sent anywhere.
		if ( ! $client ) {
			self::page_error( __( 'This app is not known to the site', 'velox' ), __( 'Start the connection again from the app (for example remove the connector in Claude and add it again).', 'velox' ) );
		}
		if ( '' === $p['redirect_uri'] && 1 === count( $client['redirect_uris'] ) ) {
			$p['redirect_uri'] = $client['redirect_uris'][0];
		}
		if ( ! self::redirect_match( $p['redirect_uri'], $client['redirect_uris'] ) ) {
			self::page_error( __( 'The app asked to return to an address it did not register', 'velox' ), __( 'For your safety the connection stops here. Start it again from the app.', 'velox' ) );
		}
		$back = function ( $args ) use ( $p ) {
			$args['iss'] = self::issuer();
			if ( '' !== $p['state'] ) {
				$args['state'] = $p['state'];
			}
			$sep = false === strpos( $p['redirect_uri'], '?' ) ? '?' : '&';
			// phpcs:ignore WordPress.Security.SafeRedirect -- the address was registered by the client and checked above.
			wp_redirect( $p['redirect_uri'] . $sep . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 ), 302 );
			exit;
		};
		if ( 'code' !== $p['response_type'] ) {
			$back( array( 'error' => 'unsupported_response_type' ) );
		}
		if ( '' === $p['code_challenge'] || 'S256' !== $p['code_challenge_method'] || ! preg_match( '/^[A-Za-z0-9_-]{43,128}$/', $p['code_challenge'] ) ) {
			$back( array( 'error' => 'invalid_request', 'error_description' => 'PKCE with S256 is required.' ) );
		}
		if ( '' !== $p['resource'] && ! self::same_resource( $p['resource'], self::mcp_url() ) && ! self::same_resource( $p['resource'], self::issuer() ) ) {
			$back( array( 'error' => 'invalid_target' ) );
		}
		if ( ! current_user_can( 'velox_builder' ) ) {
			self::page_error( __( 'Your account may not use Velox Builder', 'velox' ), __( 'Sign in with an account that may edit pages in Velox Builder, or ask an administrator.', 'velox' ), true );
		}

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			check_admin_referer( 'velox_connect_' . md5( $client['client_id'] . '|' . $p['redirect_uri'] ) );
			if ( 'allow' !== self::param( 'decision' ) ) {
				$back( array( 'error' => 'access_denied', 'error_description' => 'The person did not allow the connection.' ) );
			}
			$docs = 'all';
			if ( 'chosen' === self::param( 'reach' ) ) {
				$docs = self::clean_docs( isset( $_POST['docs'] ) ? (array) wp_unslash( $_POST['docs'] ) : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				if ( ! $docs ) {
					self::page_consent( $client, $p, __( 'Choose at least one page, or allow everything.', 'velox' ) );
				}
			}
			$code = self::token( 'vxc_' );
			set_transient( 'velox_mcp_code_' . self::hash( $code ), array(
				'client'    => $client['client_id'],
				'redirect'  => $p['redirect_uri'],
				'challenge' => $p['code_challenge'],
				'user'      => get_current_user_id(),
				'docs'      => $docs,
				'exp'       => time() + self::CODE_TTL,
			), self::CODE_TTL );
			$back( array( 'code' => $code ) );
		}
		self::page_consent( $client, $p, '' );
	}

	/** Only documents the person may edit. */
	public static function clean_docs( $ids ) {
		$out = array();
		foreach ( (array) $ids as $id ) {
			$id = absint( $id );
			if ( $id && ( ! class_exists( 'Velox_Builder_Roles' ) || Velox_Builder_Roles::can_edit_doc( $id ) ) ) {
				$out[] = $id;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** What the person can choose from: their pages, templates and components. */
	public static function choices() {
		$out = array();
		foreach ( Velox_Builder_Agents::docs() as $d ) {
			if ( class_exists( 'Velox_Builder_Roles' ) && ! Velox_Builder_Roles::can_edit_doc( (int) $d['id'] ) ) {
				continue;
			}
			$out[] = array( 'id' => (int) $d['id'], 'kind' => $d['kind'], 'title' => '' !== (string) $d['title'] ? $d['title'] : __( '(no title)', 'velox' ), 'status' => $d['status'] );
		}
		return $out;
	}

	private static function page_error( $title, $text, $signout = false ) {
		self::page_head( $title );
		echo '<main class="vc-card vc-narrow"><div class="vc-ico vc-ico-bad" aria-hidden="true">!</div><h1>' . esc_html( $title ) . '</h1><p class="vc-sub">' . esc_html( $text ) . '</p>';
		if ( $signout ) {
			$qs = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			echo '<p><a class="vc-btn" href="' . esc_url( wp_logout_url( admin_url( 'admin-post.php?' . $qs ) ) ) . '">' . esc_html__( 'Sign in with another account', 'velox' ) . '</a></p>';
		}
		echo '</main>';
		self::page_foot();
	}

	private static function page_consent( $client, $p, $error ) {
		$user  = wp_get_current_user();
		$site  = get_bloginfo( 'name' );
		$kind  = self::client_kind( array( $p['redirect_uri'] ) );
		$host  = self::redirect_host( array( $p['redirect_uri'] ) );
		$docs  = self::choices();
		$roles = wp_roles()->get_names();
		$role  = $user->roles ? translate_user_role( $roles[ $user->roles[0] ] ?? $user->roles[0] ) : '';
		$icon  = get_site_icon_url( 96 );
		$name  = $client['name'];
		$qs    = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$kinds = array( 'page' => __( 'Page', 'velox' ), 'template' => __( 'Template', 'velox' ), 'reusable' => __( 'Component', 'velox' ) );
		/* translators: %s: app name, e.g. Claude */
		self::page_head( sprintf( __( 'Connect %s', 'velox' ), $name ) );
		?>
<main class="vc-card">
	<div class="vc-pair" aria-hidden="true">
		<span class="vc-logo vc-logo-app"><?php echo 'claude' === $kind ? self::claude_mark() : esc_html( strtoupper( substr( $name, 0, 1 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<span class="vc-dots"><i></i><i></i><i></i></span>
		<span class="vc-logo"><?php echo $icon ? '<img src="' . esc_url( $icon ) . '" alt="">' : esc_html( strtoupper( substr( $site, 0, 1 ) ) ); ?></span>
	</div>
	<h1><?php /* translators: 1: app name, 2: site name */ printf( esc_html__( '%1$s wants to work on %2$s', 'velox' ), '<b>' . esc_html( $name ) . '</b>', '<b>' . esc_html( $site ) . '</b>' ); ?></h1>
	<p class="vc-who">
		<?php if ( 'claude' === $kind ) : ?>
			<span class="vc-ok" aria-hidden="true">✓</span> <?php /* translators: %s: address, e.g. claude.ai */ printf( esc_html__( 'Claude by Anthropic · returns to %s', 'velox' ), '<b>' . esc_html( $host ) . '</b>' ); ?>
		<?php elseif ( 'local' === $kind ) : ?>
			<?php esc_html_e( 'An app on this computer (for example Claude Code).', 'velox' ); ?>
		<?php else : ?>
			<span class="vc-warn"><?php /* translators: %s: address the app returns to */ printf( esc_html__( 'Returns to %s. Only continue if you started this connection yourself just now.', 'velox' ), '<b>' . esc_html( $host ) . '</b>' ); ?></span>
		<?php endif; ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vc-form">
		<input type="hidden" name="action" value="velox_connect">
		<?php foreach ( $p as $k => $v ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $v ); ?>">
		<?php endforeach; ?>
		<?php wp_nonce_field( 'velox_connect_' . md5( $client['client_id'] . '|' . $p['redirect_uri'] ) ); ?>

		<?php if ( $error ) : ?><p class="vc-err" role="alert"><?php echo esc_html( $error ); ?></p><?php endif; ?>

		<fieldset class="vc-reach">
			<legend><?php esc_html_e( 'What may it work on?', 'velox' ); ?></legend>
			<label class="vc-opt"><input type="radio" name="reach" value="all" checked>
				<span><b><?php esc_html_e( 'The whole site', 'velox' ); ?></b><small><?php esc_html_e( 'Every page, template and component you may edit in Velox Builder, and new draft pages.', 'velox' ); ?></small></span></label>
			<label class="vc-opt"><input type="radio" name="reach" value="chosen" <?php disabled( ! $docs ); ?>>
				<span><b><?php esc_html_e( 'Only the pages I choose', 'velox' ); ?></b><small><?php esc_html_e( 'It can read the design system, but change only these.', 'velox' ); ?></small></span></label>
			<div class="vc-docs" id="vc-docs" hidden>
				<input type="search" class="vc-find" id="vc-find" placeholder="<?php esc_attr_e( 'Search pages', 'velox' ); ?>" aria-label="<?php esc_attr_e( 'Search pages', 'velox' ); ?>">
				<ul>
					<?php foreach ( $docs as $d ) : ?>
						<li><label><input type="checkbox" name="docs[]" value="<?php echo (int) $d['id']; ?>"><span><?php echo esc_html( $d['title'] ); ?></span><em><?php echo esc_html( $kinds[ $d['kind'] ] ?? $d['kind'] ); ?><?php echo 'published' === $d['status'] ? '' : ' · ' . esc_html__( 'Draft', 'velox' ); ?></em></label></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</fieldset>

		<ul class="vc-rules">
			<li><?php esc_html_e( 'It reads your design system, pages and media library.', 'velox' ); ?></li>
			<li><?php esc_html_e( 'It only changes drafts. Nothing goes live until you publish it.', 'velox' ); ?></li>
			<li><?php esc_html_e( 'Every change is saved as a version marked “By AI”, one click to undo.', 'velox' ); ?></li>
			<li><?php esc_html_e( 'You can disconnect it at any time under Velox Builder → AI agents.', 'velox' ); ?></li>
		</ul>

		<p class="vc-as"><?php /* translators: 1: user name, 2: role */ printf( esc_html__( 'It works as %1$s (%2$s) and can only do what you may do.', 'velox' ), '<b>' . esc_html( $user->display_name ) . '</b>', esc_html( $role ) ); ?>
			<a href="<?php echo esc_url( wp_logout_url( admin_url( 'admin-post.php?' . $qs ) ) ); ?>"><?php esc_html_e( 'Not you?', 'velox' ); ?></a></p>

		<div class="vc-acts">
			<button type="submit" name="decision" value="deny" class="vc-btn"><?php esc_html_e( 'Cancel', 'velox' ); ?></button>
			<button type="submit" name="decision" value="allow" class="vc-btn vc-btn-primary"><?php esc_html_e( 'Allow access', 'velox' ); ?></button>
		</div>
	</form>
</main>
<script>
( function () {
	var docs = document.getElementById( 'vc-docs' ), find = document.getElementById( 'vc-find' );
	document.querySelectorAll( 'input[name=reach]' ).forEach( function ( r ) {
		r.addEventListener( 'change', function () { docs.hidden = 'chosen' !== document.querySelector( 'input[name=reach]:checked' ).value; if ( ! docs.hidden ) { find.focus(); } } );
	} );
	if ( find ) { find.addEventListener( 'input', function () {
		var q = find.value.toLowerCase();
		docs.querySelectorAll( 'li' ).forEach( function ( li ) { li.hidden = q && li.textContent.toLowerCase().indexOf( q ) < 0; } );
	} ); }
}() );
</script>
		<?php
		self::page_foot();
	}

	public static function claude_mark() {
		return '<svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path fill="currentColor" d="M12 2.5c.5 0 .8.3.9.8l.9 5.2 4.2-3.2c.4-.3.9-.3 1.2.1.3.4.3.9-.1 1.2l-3.7 3.6 5.2.6c.5.1.8.5.8 1s-.4.8-.9.9l-5.2.5 3.8 3.6c.4.3.4.9.1 1.2-.3.4-.9.4-1.2.1l-4.3-3.1-.8 5.2c-.1.5-.4.8-.9.8s-.8-.3-.9-.8l-.9-5.2-4.2 3.2c-.4.3-.9.3-1.2-.1-.3-.4-.3-.9.1-1.2l3.7-3.6-5.2-.6c-.5-.1-.8-.5-.8-1s.4-.8.9-.9l5.2-.5-3.8-3.6c-.4-.3-.4-.9-.1-1.2.3-.4.9-.4 1.2-.1l4.3 3.1.8-5.2c.1-.5.4-.8.9-.8z"/></svg>';
	}

	private static function page_head( $title ) {
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $title . ' · ' . get_bloginfo( 'name' ) ); ?></title>
<style>
:root{--bg:#f4f6f8;--card:#fff;--ink:#16202a;--mute:#5b6875;--line:#e3e8ee;--line2:#d3dae2;--acc:#2ab7f1;--acc-ink:#0e7fb3;--soft:#eef8fd;--warn:#9a5b00;--warn-bg:#fff6e5;--bad:#c62f3a}
@media (prefers-color-scheme:dark){:root{--bg:#0e1318;--card:#161d24;--ink:#e8edf2;--mute:#97a3af;--line:#25303b;--line2:#33404c;--acc-ink:#5fcbf5;--soft:#12303d;--warn:#f0b45a;--warn-bg:#2d2312}}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px 16px;background:var(--bg);color:var(--ink);font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Inter,Roboto,sans-serif}
.vc-card{width:100%;max-width:480px;background:var(--card);border:1px solid var(--line);border-radius:16px;padding:32px;box-shadow:0 1px 2px rgba(16,24,40,.04),0 8px 24px rgba(16,24,40,.06)}
.vc-narrow{text-align:center}
h1{margin:0 0 8px;font-size:20px;line-height:1.35;font-weight:600;text-align:center}
.vc-sub{margin:0 0 16px;color:var(--mute)}
.vc-pair{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:20px}
.vc-logo{width:52px;height:52px;border-radius:14px;display:grid;place-items:center;background:var(--soft);color:var(--acc-ink);font-weight:600;font-size:20px;overflow:hidden;border:1px solid var(--line)}
.vc-logo img{width:100%;height:100%;object-fit:cover}
.vc-logo-app{background:#d97757;color:#fff;border-color:transparent}
.vc-dots{display:flex;gap:4px}.vc-dots i{width:4px;height:4px;border-radius:50%;background:var(--line2)}
.vc-who{margin:0 0 24px;text-align:center;color:var(--mute);font-size:13px}
.vc-ok{display:inline-grid;place-items:center;width:16px;height:16px;border-radius:50%;background:#1f9d55;color:#fff;font-size:10px;font-weight:700;vertical-align:-3px}
.vc-warn{display:block;padding:8px 12px;border-radius:8px;background:var(--warn-bg);color:var(--warn)}
.vc-ico{width:44px;height:44px;margin:0 auto 16px;border-radius:50%;display:grid;place-items:center;font-weight:700;font-size:20px}
.vc-ico-bad{background:#fdecee;color:var(--bad)}
fieldset{border:0;margin:0 0 20px;padding:0}
legend{font-weight:600;margin-bottom:8px;padding:0}
.vc-opt{display:flex;gap:12px;align-items:flex-start;padding:12px 14px;border:1px solid var(--line);border-radius:10px;cursor:pointer;margin-bottom:8px;transition:border-color .15s,background-color .15s}
.vc-opt:hover{border-color:var(--line2)}
.vc-opt:has(input:checked){border-color:var(--acc);background:var(--soft)}
.vc-opt input{margin:3px 0 0;accent-color:var(--acc)}
.vc-opt span{display:flex;flex-direction:column;gap:2px}.vc-opt small{color:var(--mute);font-size:13px}
.vc-docs{border:1px solid var(--line);border-radius:10px;overflow:hidden}
.vc-find{width:100%;border:0;border-bottom:1px solid var(--line);padding:10px 14px;font:inherit;background:transparent;color:inherit;outline:none}
.vc-find:focus{box-shadow:inset 0 -2px 0 var(--acc)}
.vc-docs ul{list-style:none;margin:0;padding:4px 0;max-height:220px;overflow:auto}
.vc-docs label{display:flex;align-items:center;gap:10px;padding:7px 14px;cursor:pointer}
.vc-docs label:hover{background:var(--bg)}
.vc-docs input{accent-color:var(--acc);margin:0}
.vc-docs span{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.vc-docs em{font-style:normal;color:var(--mute);font-size:12px;white-space:nowrap}
.vc-rules{margin:0 0 20px;padding:0;list-style:none;display:flex;flex-direction:column;gap:8px;color:var(--mute);font-size:13px}
.vc-rules li{position:relative;padding-left:22px}
.vc-rules li::before{content:"";position:absolute;left:4px;top:4px;width:9px;height:5px;border-left:2px solid var(--acc-ink);border-bottom:2px solid var(--acc-ink);transform:rotate(-45deg)}
.vc-as{margin:0 0 20px;font-size:13px;color:var(--mute)}
.vc-as a,.vc-card a{color:var(--acc-ink)}
.vc-err{margin:0 0 16px;padding:8px 12px;border-radius:8px;background:#fdecee;color:var(--bad)}
.vc-acts{display:flex;gap:8px;justify-content:flex-end}
.vc-btn{display:inline-flex;align-items:center;justify-content:center;height:38px;padding:0 16px;border-radius:8px;border:1px solid var(--line2);background:var(--card);color:var(--ink);font:500 14px/1 inherit;font-family:inherit;cursor:pointer;text-decoration:none;transition:background-color .15s,transform .15s}
.vc-btn:hover{background:var(--bg)}.vc-btn:active{transform:scale(.98)}
.vc-btn:focus-visible,.vc-opt:has(input:focus-visible){outline:2px solid rgba(42,183,241,.6);outline-offset:2px}
.vc-btn-primary{background:var(--acc);border-color:var(--acc);color:#04222f}
.vc-btn-primary:hover{background:#1ea5dd}
@media (max-width:480px){.vc-card{padding:24px 20px}.vc-acts{flex-direction:column-reverse}.vc-btn{width:100%}}
@media (prefers-reduced-motion:reduce){*{transition:none!important}}
</style>
</head>
<body>
		<?php
	}

	private static function page_foot() {
		echo '</body></html>';
		exit;
	}

	/* ============================================================== the MCP */

	private static function unauthorized( $presented ) {
		$h = 'Bearer resource_metadata="' . self::resource_metadata_url() . '", scope="velox"';
		if ( $presented ) {
			$h .= ', error="invalid_token", error_description="The token is unknown or expired"';
		}
		$res = new WP_REST_Response( array( 'error' => $presented ? 'invalid_token' : 'unauthorized', 'error_description' => 'Connect this site first: it signs you in with WordPress.' ), 401 );
		$res->header( 'WWW-Authenticate', $h );
		return $res;
	}

	public static function rest_mcp( WP_REST_Request $r ) {
		if ( ! self::enabled() ) {
			return self::off();
		}
		$token = self::bearer();
		$grant = self::grant_for_access( $token );
		if ( ! $grant ) {
			return self::unauthorized( '' !== $token );
		}
		wp_set_current_user( (int) $grant['user'] );
		self::$grant = $grant;
		self::touch( $grant['id'] );

		if ( 'POST' !== $r->get_method() ) {
			// No server-to-client stream and no sessions: everything is answered in the POST.
			$res = new WP_REST_Response( array( 'error' => 'Use POST.' ), 405 );
			$res->header( 'Allow', 'POST' );
			return $res;
		}
		$msg = json_decode( (string) $r->get_body(), true );
		if ( ! is_array( $msg ) ) {
			return new WP_REST_Response( self::rpc_error( null, -32700, 'Parse error' ), 400 );
		}
		$batch = array_keys( $msg ) === range( 0, count( $msg ) - 1 );
		$out   = array();
		foreach ( $batch ? $msg : array( $msg ) as $m ) {
			if ( ! is_array( $m ) ) {
				$out[] = self::rpc_error( null, -32600, 'Invalid request' );
				continue;
			}
			if ( ! isset( $m['method'] ) ) {
				continue; // A response from the client: nothing was asked, nothing to do.
			}
			$one = self::dispatch( (string) $m['method'], is_array( $m['params'] ?? null ) ? $m['params'] : array() );
			if ( ! array_key_exists( 'id', $m ) ) {
				continue; // A notification.
			}
			$out[] = is_wp_error( $one )
				? self::rpc_error( $m['id'], (int) $one->get_error_code(), $one->get_error_message() )
				: array( 'jsonrpc' => '2.0', 'id' => $m['id'], 'result' => $one );
		}
		if ( ! $out ) {
			return new WP_REST_Response( null, 202 );
		}
		return new WP_REST_Response( $batch ? $out : $out[0], 200 );
	}

	private static function rpc_error( $id, $code, $text ) {
		return array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => array( 'code' => $code, 'message' => $text ) );
	}

	private static function dispatch( $method, $params ) {
		switch ( $method ) {
			case 'initialize':
				$want = (string) ( $params['protocolVersion'] ?? '' );
				return array(
					'protocolVersion' => in_array( $want, self::VERSIONS, true ) ? $want : self::VERSIONS[1],
					'capabilities'    => array( 'tools' => array( 'listChanged' => false ) ),
					'serverInfo'      => array( 'name' => 'velox', 'title' => 'Velox — ' . get_bloginfo( 'name' ), 'version' => defined( 'VELOX_VERSION' ) ? VELOX_VERSION : '' ),
					'instructions'    => self::instructions(),
				);
			case 'ping':
			case 'logging/setLevel':
				return new stdClass();
			case 'tools/list':
				return array( 'tools' => self::tools() );
			case 'tools/call':
				return self::call( (string) ( $params['name'] ?? '' ), is_array( $params['arguments'] ?? null ) ? $params['arguments'] : array() );
			case 'resources/list':
				return array( 'resources' => array() );
			case 'resources/templates/list':
				return array( 'resourceTemplates' => array() );
			case 'prompts/list':
				return array( 'prompts' => array() );
		}
		if ( 0 === strpos( $method, 'notifications/' ) ) {
			return new stdClass();
		}
		return new WP_Error( '-32601', 'Method not found: ' . $method );
	}

	private static function scope_line() {
		$docs = self::$grant['docs'] ?? 'all';
		if ( 'all' === $docs ) {
			return 'You may work on every page, template and component this person may edit, and create new draft pages.';
		}
		$names = array();
		foreach ( self::choices() as $c ) {
			if ( in_array( (int) $c['id'], (array) $docs, true ) ) {
				$names[] = '#' . $c['id'] . ' ' . $c['title'];
			}
		}
		return 'You may change only these documents: ' . implode( ', ', $names ) . '. You can read the design system, but not create new pages.';
	}

	private static function instructions() {
		$u = wp_get_current_user();
		return 'Velox Builder on the WordPress site "' . get_bloginfo( 'name' ) . '" (' . home_url( '/' ) . '). You act as ' . $u->display_name . ' and can only do what they may do. '
			. self::scope_line()
			. ' Before building, call velox_get_design_system and build with the site\'s classes and design tokens. When the person speaks of "this page" or "the page I have open", call velox_get_open_document. '
			. 'Everything you change is saved as a draft and as a version marked "By AI"; nothing is published. If the page is open in the editor, it updates live. '
			. 'When you are done, give the person the editor link so they can check and publish.';
	}

	private static function tools() {
		$out = array();
		foreach ( Velox_Builder_Agents::abilities() as $name => $a ) {
			if ( 'assistant' === ( $a['only'] ?? '' ) ) {
				continue;
			}
			$schema = $a['input'];
			unset( $schema['default'] );
			if ( empty( $schema['properties'] ) ) {
				$schema['properties'] = new stdClass();
			}
			$out[] = array(
				'name'        => Velox_Builder_Agents::tool_name( $name ),
				'title'       => $a['label'],
				'description' => $a['description'],
				'inputSchema' => $schema,
				'annotations' => array(
					'title'           => $a['label'],
					'readOnlyHint'    => ! empty( $a['ann']['readonly'] ),
					'destructiveHint' => ! empty( $a['ann']['destructive'] ),
					'idempotentHint'  => ! empty( $a['ann']['idempotent'] ),
					'openWorldHint'   => false,
				),
			);
		}
		return $out;
	}

	private static function call( $tool, $args ) {
		$ability = Velox_Builder_Agents::ability_for( $tool );
		if ( '' === $ability ) {
			return new WP_Error( '-32602', 'Unknown tool: ' . $tool );
		}
		Velox_Builder_Agents::$scope = self::$grant['docs'] ?? 'all';
		$res = Velox_Builder_Agents::run_tool( $ability, $args, 'connector' );
		Velox_Builder_Agents::$scope = null;
		if ( is_wp_error( $res ) ) {
			return array( 'content' => array( array( 'type' => 'text', 'text' => $res->get_error_message() ) ), 'isError' => true );
		}
		$doc = (int) ( $args['document_id'] ?? ( is_array( $res ) ? ( $res['document_id'] ?? 0 ) : 0 ) );
		$all = Velox_Builder_Agents::abilities();
		if ( $doc && empty( $all[ $ability ]['ann']['readonly'] ) ) {
			self::changed( $doc );
		}
		return array( 'content' => array( array( 'type' => 'text', 'text' => wp_json_encode( $res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) ) );
	}

	/* ===================================================== editor live view */

	/** A connector changed this document: the editor that has it open pulls it in. */
	public static function changed( $doc ) {
		set_transient( 'velox_mcp_doc_' . (int) $doc, time(), DAY_IN_SECONDS );
	}

	public static function last_change( $doc ) {
		return (int) get_transient( 'velox_mcp_doc_' . (int) $doc );
	}

	/** The document the person has open in the editor (for "this page"). */
	public static function note_open( $doc, $force = true ) {
		$uid = get_current_user_id();
		if ( ! $uid ) {
			return;
		}
		$was = get_user_meta( $uid, 'velox_open_doc', true );
		if ( $force || ! is_array( $was ) || (int) $was['id'] !== (int) $doc || time() - (int) $was['t'] > 60 ) {
			update_user_meta( $uid, 'velox_open_doc', array( 'id' => (int) $doc, 't' => time() ) );
		}
	}

	/** This person's connections. */
	public static function mine( $uid = 0 ) {
		$uid = $uid ? $uid : get_current_user_id();
		return array_filter( self::grants(), function ( $g ) use ( $uid ) { return (int) $g['user'] === (int) $uid && (int) $g['rt_exp'] > time(); } );
	}

	/** For the editor's boot config. */
	public static function editor_data() {
		$mine = self::mine();
		$seen = self::seen();
		$last = 0;
		$name = '';
		foreach ( $mine as $id => $g ) {
			$t = (int) ( $seen[ $id ]['t'] ?? $g['created'] );
			if ( $t >= $last ) {
				$last = $t;
				$name = $g['name'];
			}
		}
		return array(
			'on'    => self::enabled(),
			'apps'  => count( $mine ),
			'name'  => $name,
			'last'  => $last,
			'url'   => self::mcp_url(),
			'site'  => get_bloginfo( 'name' ),
		);
	}

	/** Editor poll: did a connector change this page? (do=mcp_watch) */
	public static function ajax_watch() {
		$doc = isset( $_POST['doc'] ) ? absint( $_POST['doc'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- checked by the router.
		if ( ! $doc || ( class_exists( 'Velox_Builder_Roles' ) && ! Velox_Builder_Roles::can_edit_doc( $doc ) ) ) {
			wp_send_json_error( array(), 403 );
		}
		self::note_open( $doc, false );
		$t = self::last_change( $doc );
		wp_send_json_success( array( 't' => $t, 'now' => time() ) );
	}

	/* ===================================================== AI agents screen */

	/** Connected apps for the AI agents screen. */
	public static function list_for_admin() {
		$out  = array();
		$seen = self::seen();
		foreach ( self::grants() as $id => $g ) {
			if ( (int) $g['rt_exp'] < time() ) {
				continue;
			}
			$u = get_userdata( (int) $g['user'] );
			$out[] = array(
				'id'      => $id,
				'name'    => $g['name'],
				'kind'    => $g['kind'],
				'host'    => $g['host'],
				'user'    => $u ? $u->display_name : '#' . (int) $g['user'],
				'docs'    => $g['docs'],
				'created' => (int) $g['created'],
				'used'    => (int) ( $seen[ $id ]['t'] ?? 0 ),
			);
		}
		usort( $out, function ( $a, $b ) { return max( $b['used'], $b['created'] ) - max( $a['used'], $a['created'] ); } );
		return $out;
	}

	public static function ajax_revoke() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'velox' ) ), 403 );
		}
		self::revoke_grant( isset( $_POST['grant'] ) ? sanitize_key( wp_unslash( $_POST['grant'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification -- checked by the router.
		wp_send_json_success( array( 'apps' => self::list_for_admin() ) );
	}

	public static function ajax_scope() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'velox' ) ), 403 );
		}
		// phpcs:disable WordPress.Security.NonceVerification -- checked by the router.
		$gid  = isset( $_POST['grant'] ) ? sanitize_key( wp_unslash( $_POST['grant'] ) ) : '';
		$all  = self::grants();
		if ( ! isset( $all[ $gid ] ) ) {
			wp_send_json_error( array( 'message' => __( 'That connection no longer exists.', 'velox' ) ), 404 );
		}
		$docs = 'all';
		if ( empty( $_POST['all'] ) ) {
			$ids  = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['docs'] ?? '' ) ) ) ) );
			$docs = array_values( array_unique( $ids ) );
			if ( ! $docs ) {
				wp_send_json_error( array( 'message' => __( 'Choose at least one page, or allow everything.', 'velox' ) ), 400 );
			}
		}
		// phpcs:enable
		self::set_scope( $gid, $docs );
		wp_send_json_success( array( 'apps' => self::list_for_admin() ) );
	}

	/**
	 * Check from the server what Claude will see: discovery answers, the
	 * endpoint asks to sign in, and the Authorization header reaches PHP.
	 */
	public static function ajax_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'velox' ) ), 403 );
		}
		$rows = array();
		$add  = function ( $ok, $label, $text ) use ( &$rows ) { $rows[] = array( 'ok' => $ok, 'label' => $label, 'text' => $text ); };
		$add( 0 === strpos( self::mcp_url(), 'https://' ), __( 'Secure address (https)', 'velox' ), 0 === strpos( self::mcp_url(), 'https://' ) ? __( 'Yes.', 'velox' ) : __( 'Claude only connects to https addresses. Switch the site to https first.', 'velox' ) );
		if ( ! self::enabled() ) {
			$add( false, __( 'Connections switched on', 'velox' ), __( 'Switch on “Let Claude work on this site” first.', 'velox' ) );
			wp_send_json_success( array( 'rows' => $rows ) );
		}
		$opts = array( 'timeout' => 10, 'redirection' => 0, 'sslverify' => false );
		$res  = wp_remote_get( self::resource_metadata_url(), $opts );
		$prm  = is_wp_error( $res ) ? null : json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( is_wp_error( $res ) ) {
			$add( null, __( 'The site can reach itself', 'velox' ), __( 'The server could not call its own address, so Velox cannot check the rest from here. That does not mean Claude cannot connect: just try it.', 'velox' ) );
			wp_send_json_success( array( 'rows' => $rows ) );
		}
		$add( is_array( $prm ) && ! empty( $prm['authorization_servers'] ), __( 'Claude finds the sign-in', 'velox' ), is_array( $prm ) && ! empty( $prm['authorization_servers'] ) ? __( 'The discovery addresses answer.', 'velox' ) : sprintf( /* translators: %d: HTTP status */ __( 'The discovery address answered with status %d. A security plugin or the server may block /.well-known/ addresses.', 'velox' ), (int) wp_remote_retrieve_response_code( $res ) ) );
		$res2 = wp_remote_get( self::issuer() . '/.well-known/oauth-authorization-server', $opts );
		$asm  = is_wp_error( $res2 ) ? null : json_decode( (string) wp_remote_retrieve_body( $res2 ), true );
		if ( ! is_array( $asm ) || empty( $asm['token_endpoint'] ) ) {
			$res3 = wp_remote_get( rest_url( 'velox/v1/oauth/server' ), $opts );
			$asm  = is_wp_error( $res3 ) ? null : json_decode( (string) wp_remote_retrieve_body( $res3 ), true );
		}
		$add( is_array( $asm ) && ! empty( $asm['token_endpoint'] ), __( 'Sign-in settings', 'velox' ), is_array( $asm ) && ! empty( $asm['token_endpoint'] ) ? __( 'Published.', 'velox' ) : __( 'Not reachable. A security plugin may block the REST API for visitors.', 'velox' ) );
		$res4 = wp_remote_post( self::mcp_url(), $opts + array( 'headers' => array( 'Authorization' => 'Bearer vxa_selfcheck', 'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream' ), 'body' => '{"jsonrpc":"2.0","id":1,"method":"ping"}' ) );
		$www  = is_wp_error( $res4 ) ? '' : (string) wp_remote_retrieve_header( $res4, 'www-authenticate' );
		$code = is_wp_error( $res4 ) ? 0 : (int) wp_remote_retrieve_response_code( $res4 );
		$add( 401 === $code && false !== strpos( $www, 'resource_metadata' ), __( 'The endpoint asks Claude to sign in', 'velox' ), 401 === $code ? __( 'Yes.', 'velox' ) : sprintf( /* translators: %d: HTTP status */ __( 'It answered with status %d instead of asking to sign in.', 'velox' ), $code ) );
		$add( false !== strpos( $www, 'invalid_token' ), __( 'Sign-in tokens reach WordPress', 'velox' ), false !== strpos( $www, 'invalid_token' ) ? __( 'Yes: the server passes the Authorization header on.', 'velox' ) : __( 'The server drops the Authorization header before WordPress sees it. Your host can fix this (Apache: CGIPassAuth On).', 'velox' ) );
		wp_send_json_success( array( 'rows' => $rows ) );
	}
}

Velox_MCP::init();
