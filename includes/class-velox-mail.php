<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mail backbone.
 *
 * Routes wp_mail through one of several SMTP "connections", chosen per message by
 * matching the outgoing From address/name against a list of routing rules. If the
 * chosen connection fails, Velox retries once through the designated fallback.
 * Every send is logged (connection used, From, recipient, status, error) and can
 * be resent from the log.
 *
 * Storage (in the Velox settings option, as JSON strings):
 *   mail_connections : [ { id, label, host, port, secure, user, pass, from, from_name } ]
 *   mail_routes      : [ { match, value, conn } ]   match = from_email | from_name | all
 *   mail_primary     : connection id used when no route matches
 *   mail_fallback    : connection id tried when the primary send fails ('' = none)
 *
 * Legacy single-connection keys (mail_smtp_host …) migrate into one connection on
 * first load, so existing setups keep working with zero action.
 */
class Velox_Mail {

	const DB_VERSION = '2';
	const VER_OPTION = 'velox_mail_db';

	/** Per-request: connection configure_smtp() should apply next. */
	protected static $active_conn = null;
	/** Per-request: From of the message currently being sent. */
	protected static $current_from = '';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'velox_mail_log';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$t = self::table();
		dbDelta( "CREATE TABLE {$t} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created DATETIME NULL,
			recipient VARCHAR(190) NULL,
			subject VARCHAR(190) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'sent',
			connection VARCHAR(120) NULL,
			from_email VARCHAR(190) NULL,
			retried TINYINT(1) NOT NULL DEFAULT 0,
			error VARCHAR(190) NULL,
			body LONGTEXT NULL,
			headers TEXT NULL,
			PRIMARY KEY  (id),
			KEY created (created)
		) {$charset};" );
		update_option( self::VER_OPTION, self::DB_VERSION );
	}

	public static function maybe_install() {
		if ( get_option( self::VER_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function init() {
		self::migrate_legacy();
		if ( Velox_Settings::get( 'mail_smtp_enabled', false ) && self::connections() ) {
			add_action( 'phpmailer_init', array( __CLASS__, 'configure_smtp' ) );
		}
		// Replace the default "WordPress <wordpress@domain>" sender site-wide.
		add_filter( 'wp_mail_from', array( __CLASS__, 'filter_from_email' ), 20 );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'filter_from_name' ), 20 );
	}

	/** Sender address override (falls back to WordPress's default when unset/invalid). */
	public static function filter_from_email( $email ) {
		$set = trim( (string) Velox_Settings::get( 'mail_from_email', '' ) );
		return ( '' !== $set && is_email( $set ) ) ? $set : $email;
	}

	/** Sender name override (falls back to WordPress's default when unset). */
	public static function filter_from_name( $name ) {
		$set = trim( (string) Velox_Settings::get( 'mail_from_name', '' ) );
		return '' !== $set ? $set : $name;
	}

	/* ----------------------------------------------------------------- *
	 * Connections + routing
	 * ----------------------------------------------------------------- */

	public static function connections() {
		$raw = Velox_Settings::get( 'mail_connections', '' );
		$arr = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : array() );
		return is_array( $arr ) ? array_values( $arr ) : array();
	}

	public static function routes() {
		$raw = Velox_Settings::get( 'mail_routes', '' );
		$arr = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : array() );
		return is_array( $arr ) ? array_values( $arr ) : array();
	}

	public static function connection( $id ) {
		foreach ( self::connections() as $c ) {
			if ( isset( $c['id'] ) && (string) $c['id'] === (string) $id ) {
				return $c;
			}
		}
		return null;
	}

	protected static function first_conn_id() {
		$c = self::connections();
		return $c && isset( $c[0]['id'] ) ? (string) $c[0]['id'] : '';
	}

	public static function primary_id() {
		$p = (string) Velox_Settings::get( 'mail_primary', '' );
		return ( '' !== $p && self::connection( $p ) ) ? $p : self::first_conn_id();
	}

	public static function fallback_id() {
		$f = (string) Velox_Settings::get( 'mail_fallback', '' );
		return ( '' !== $f && self::connection( $f ) ) ? $f : '';
	}

	/** First matching route wins; otherwise the primary connection id. */
	public static function pick_connection( $from_email, $from_name = '' ) {
		$from_email = strtolower( trim( (string) $from_email ) );
		$from_name  = trim( (string) $from_name );
		foreach ( self::routes() as $r ) {
			$match = isset( $r['match'] ) ? $r['match'] : 'all';
			$value = isset( $r['value'] ) ? trim( (string) $r['value'] ) : '';
			$conn  = isset( $r['conn'] ) ? (string) $r['conn'] : '';
			if ( '' === $conn || ! self::connection( $conn ) ) {
				continue;
			}
			if ( 'all' === $match ) {
				return $conn;
			}
			if ( 'from_email' === $match && '' !== $value && strtolower( $value ) === $from_email ) {
				return $conn;
			}
			if ( 'from_name' === $match && '' !== $value && strcasecmp( $value, $from_name ) === 0 ) {
				return $conn;
			}
		}
		return self::primary_id();
	}

	public static function configure_smtp( $phpmailer ) {
		$conn = self::$active_conn;
		if ( null === $conn ) {
			$conn = self::connection( self::pick_connection( self::$current_from ) );
		}
		if ( ! $conn || empty( $conn['host'] ) ) {
			return;
		}
		$phpmailer->isSMTP();
		$phpmailer->Host       = $conn['host'];
		$phpmailer->Port       = (int) ( isset( $conn['port'] ) ? $conn['port'] : 587 );
		$secure                = isset( $conn['secure'] ) ? $conn['secure'] : 'tls';
		$phpmailer->SMTPSecure = in_array( $secure, array( 'ssl', 'tls' ), true ) ? $secure : '';
		$user = isset( $conn['user'] ) ? $conn['user'] : '';
		$pass = isset( $conn['pass'] ) ? $conn['pass'] : '';
		if ( '' !== $user ) {
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = $user;
			$phpmailer->Password = $pass;
		}
		$from      = isset( $conn['from'] ) ? $conn['from'] : '';
		$from_name = isset( $conn['from_name'] ) ? $conn['from_name'] : '';
		if ( $from && is_email( $from ) ) {
			$phpmailer->setFrom( $from, $from_name ? $from_name : get_bloginfo( 'name' ), false );
		}
		if ( ! empty( $conn['reply_to'] ) && is_email( $conn['reply_to'] ) ) {
			$phpmailer->addReplyTo( $conn['reply_to'], $from_name );
		}
	}

	/* ----------------------------------------------------------------- *
	 * Sending + logging
	 * ----------------------------------------------------------------- */

	public static function send( $to, $subject, $body, $headers = array() ) {
		if ( empty( $headers ) ) {
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		}

		$from_email = self::header_from( $headers );
		self::$current_from = $from_email;

		$smtp_on  = Velox_Settings::get( 'mail_smtp_enabled', false ) && self::connections();
		$primary  = $smtp_on ? self::connection( self::pick_connection( $from_email ) ) : null;
		$fallback = $smtp_on ? self::connection( self::fallback_id() ) : null;

		self::$active_conn = $primary;
		$ok      = wp_mail( $to, $subject, $body, $headers );
		$err     = $ok ? '' : 'Send failed';
		$used    = $primary ? self::conn_label( $primary ) : 'WordPress default';
		$retried = 0;

		if ( ! $ok && $fallback && ( ! $primary || $fallback['id'] !== $primary['id'] ) ) {
			self::$active_conn = $fallback;
			$ok      = wp_mail( $to, $subject, $body, $headers );
			$retried = 1;
			if ( $ok ) {
				$used = self::conn_label( $fallback ) . ' (fallback)';
				$err  = '';
			} else {
				$err  = 'Primary and fallback both failed';
			}
		}

		self::$active_conn  = null;
		self::$current_from = '';

		self::write_log( $to, $subject, $body, $headers, $ok, $used, $from_email, $retried, $err );
		return $ok;
	}

	protected static function write_log( $to, $subject, $body, $headers, $ok, $conn_label, $from_email, $retried, $err ) {
		if ( ! Velox_Settings::get( 'mail_log', true ) ) {
			return;
		}
		global $wpdb;
		$recip = is_array( $to ) ? implode( ', ', $to ) : (string) $to;
		$hdr   = is_array( $headers ) ? implode( "\n", $headers ) : (string) $headers;
		$wpdb->insert(
			self::table(),
			array(
				'created'    => current_time( 'mysql' ),
				'recipient'  => substr( $recip, 0, 190 ),
				'subject'    => substr( (string) $subject, 0, 190 ),
				'status'     => $ok ? 'sent' : 'failed',
				'connection' => substr( (string) $conn_label, 0, 120 ),
				'from_email' => substr( (string) $from_email, 0, 190 ),
				'retried'    => (int) $retried,
				'error'      => substr( (string) $err, 0, 190 ),
				'body'       => (string) $body,
				'headers'    => substr( $hdr, 0, 2000 ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		if ( 1 === wp_rand( 1, 30 ) ) {
			self::prune();
		}
	}

	protected static function header_from( $headers ) {
		$lines = is_array( $headers ) ? $headers : preg_split( '/\r\n|\r|\n/', (string) $headers );
		foreach ( (array) $lines as $line ) {
			if ( stripos( $line, 'from:' ) === 0 && preg_match( '/<([^>]+)>/', $line, $m ) ) {
				return strtolower( trim( $m[1] ) );
			}
			if ( stripos( $line, 'from:' ) === 0 ) {
				$v = trim( substr( $line, 5 ) );
				if ( is_email( $v ) ) {
					return strtolower( $v );
				}
			}
		}
		return '';
	}

	protected static function conn_label( $conn ) {
		if ( ! is_array( $conn ) ) {
			return '';
		}
		if ( ! empty( $conn['label'] ) ) {
			return $conn['label'];
		}
		return ! empty( $conn['host'] ) ? $conn['host'] : 'SMTP';
	}

	public static function prune() {
		global $wpdb;
		$t      = self::table();
		$cutoff = (int) $wpdb->get_var( "SELECT id FROM {$t} ORDER BY id DESC LIMIT 1 OFFSET 500" );
		if ( $cutoff > 0 ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE id < %d", $cutoff ) );
		}
	}

	public static function log( $limit = 100 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d',
			(int) $limit
		), ARRAY_A ) ?: array();
	}

	public static function get_log_row( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE id = %d',
			(int) $id
		), ARRAY_A );
	}

	public static function clear_log() {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . self::table() );
		return array( 'ok' => true );
	}

	public static function resend( $id ) {
		$row = self::get_log_row( $id );
		if ( ! $row ) {
			return array( 'ok' => false, 'message' => 'Log entry not found.' );
		}
		$headers = ! empty( $row['headers'] ) ? preg_split( '/\r\n|\r|\n/', $row['headers'] ) : array();
		$ok = self::send( $row['recipient'], $row['subject'], (string) $row['body'], $headers );
		return array( 'ok' => (bool) $ok, 'message' => $ok ? 'Resent.' : 'Resend failed — check the connection.' );
	}

	public static function send_test( $to, $conn_id = '' ) {
		if ( ! is_email( $to ) ) {
			return array( 'ok' => false, 'message' => 'Enter a valid email address.' );
		}
		$conn = '' !== $conn_id ? self::connection( $conn_id ) : self::connection( self::primary_id() );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( $conn && ! empty( $conn['from'] ) && is_email( $conn['from'] ) ) {
			$headers[] = 'From: ' . ( ! empty( $conn['from_name'] ) ? $conn['from_name'] . ' ' : '' ) . '<' . $conn['from'] . '>';
		}
		$label = $conn ? self::conn_label( $conn ) : 'WordPress default';
		$ok = self::send(
			$to,
			'Velox SMTP test',
			'<p>This is a test email from Velox via <strong>' . esc_html( $label ) . '</strong>. If you can read this, the connection works.</p>',
			$headers
		);
		return array( 'ok' => (bool) $ok, 'message' => $ok ? 'Test email sent via ' . $label . '.' : 'Send failed — check the connection settings.' );
	}

	/**
	 * Open a live SMTP handshake (connect → EHLO → STARTTLS → AUTH) to check
	 * whether a connection is actually reachable and the login works — without
	 * sending an email. Returns a precise ok/message.
	 *
	 * @param array $args host, port, secure (tls|ssl|none), user, pass.
	 */
	public static function test_connection( $args ) {
		$host   = isset( $args['host'] ) ? trim( (string) $args['host'] ) : '';
		$port   = isset( $args['port'] ) ? (int) $args['port'] : 587;
		$secure = isset( $args['secure'] ) ? (string) $args['secure'] : 'tls';
		$user   = isset( $args['user'] ) ? (string) $args['user'] : '';
		$pass   = isset( $args['pass'] ) ? (string) $args['pass'] : '';

		if ( '' === $host ) {
			return array( 'ok' => false, 'message' => 'Enter an SMTP host first.' );
		}
		if ( $port < 1 || $port > 65535 ) {
			return array( 'ok' => false, 'message' => 'Enter a valid port (1–65535).' );
		}

		if ( ! class_exists( '\\PHPMailer\\PHPMailer\\SMTP' ) ) {
			$base = ABSPATH . WPINC . '/PHPMailer/';
			if ( file_exists( $base . 'SMTP.php' ) ) {
				if ( file_exists( $base . 'Exception.php' ) ) {
					require_once $base . 'Exception.php';
				}
				require_once $base . 'SMTP.php';
			}
		}
		if ( ! class_exists( '\\PHPMailer\\PHPMailer\\SMTP' ) ) {
			return array( 'ok' => false, 'message' => 'SMTP library is not available on this server.' );
		}

		$smtp = new \PHPMailer\PHPMailer\SMTP();
		$smtp->Timeout = 10;
		$conn_host = ( 'ssl' === $secure ) ? 'ssl://' . $host : $host;
		$hello     = self::server_domain();

		try {
			if ( ! $smtp->connect( $conn_host, $port, 10 ) ) {
				return array( 'ok' => false, 'message' => 'Could not reach ' . $host . ':' . $port . '. ' . self::smtp_err( $smtp ) );
			}
			if ( ! $smtp->hello( $hello ) ) {
				$smtp->quit();
				return array( 'ok' => false, 'message' => 'Server refused the greeting. ' . self::smtp_err( $smtp ) );
			}
			if ( 'tls' === $secure ) {
				if ( ! $smtp->startTLS() ) {
					$smtp->quit();
					return array( 'ok' => false, 'message' => 'STARTTLS failed — try SSL, or a different port (587 = TLS, 465 = SSL). ' . self::smtp_err( $smtp ) );
				}
				$smtp->hello( $hello );
			}
			if ( '' !== $user ) {
				if ( ! $smtp->authenticate( $user, $pass ) ) {
					$smtp->quit();
					return array( 'ok' => false, 'message' => 'Connected, but the login was rejected — check the username and password. ' . self::smtp_err( $smtp ) );
				}
			}
			$smtp->quit();
			return array(
				'ok'      => true,
				'message' => '' !== $user ? 'Connected and signed in — this connection works.' : 'Connected successfully (no authentication set).',
			);
		} catch ( \Throwable $e ) {
			return array( 'ok' => false, 'message' => 'Connection error: ' . $e->getMessage() );
		}
	}

	private static function smtp_err( $smtp ) {
		$e   = $smtp->getError();
		$msg = '';
		if ( is_array( $e ) ) {
			$msg = ! empty( $e['error'] ) ? $e['error'] : '';
			if ( ! empty( $e['detail'] ) ) {
				$msg .= ' ' . $e['detail'];
			}
		}
		return trim( (string) $msg );
	}

	private static function server_domain() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return $host ? $host : 'localhost';
	}

	/* ----------------------------------------------------------------- *
	 * Legacy migration
	 * ----------------------------------------------------------------- */

	public static function migrate_legacy() {
		if ( Velox_Settings::get( 'mail_migrated_v2', false ) ) {
			return;
		}
		$host  = (string) Velox_Settings::get( 'mail_smtp_host', '' );
		$conns = self::connections();
		if ( '' !== $host && ! $conns ) {
			$id = 'conn_' . substr( md5( $host . microtime() ), 0, 8 );
			$conns[] = array(
				'id'        => $id,
				'label'     => $host,
				'host'      => $host,
				'port'      => (int) Velox_Settings::get( 'mail_smtp_port', 587 ),
				'secure'    => (string) Velox_Settings::get( 'mail_smtp_secure', 'tls' ),
				'user'      => (string) Velox_Settings::get( 'mail_smtp_user', '' ),
				'pass'      => (string) Velox_Settings::get( 'mail_smtp_pass', '' ),
				'from'      => (string) Velox_Settings::get( 'mail_smtp_from', '' ),
				'from_name' => (string) Velox_Settings::get( 'mail_smtp_from_name', '' ),
			);
			Velox_Settings::set( 'mail_connections', wp_json_encode( $conns ) );
			Velox_Settings::set( 'mail_primary', $id );
		}
		Velox_Settings::set( 'mail_migrated_v2', true );
	}

	/* ----------------------------------------------------------------- *
	 * Save handler (from AJAX) — validate + store the JSON blobs
	 * ----------------------------------------------------------------- */

	public static function save_routing( $conns, $routes, $primary, $fallback ) {
		$clean_conns = array();
		$ids = array();
		foreach ( (array) $conns as $c ) {
			$host = isset( $c['host'] ) ? sanitize_text_field( $c['host'] ) : '';
			if ( '' === $host ) {
				continue;
			}
			$id = isset( $c['id'] ) && '' !== $c['id'] ? sanitize_key( $c['id'] ) : 'conn_' . substr( md5( $host . wp_rand() ), 0, 8 );
			$secure = isset( $c['secure'] ) ? $c['secure'] : 'tls';
			$secure = in_array( $secure, array( 'tls', 'ssl', 'none' ), true ) ? $secure : 'tls';
			$clean_conns[] = array(
				'id'        => $id,
				'label'     => isset( $c['label'] ) ? sanitize_text_field( $c['label'] ) : $host,
				'host'      => $host,
				'port'      => isset( $c['port'] ) ? max( 1, min( 65535, (int) $c['port'] ) ) : 587,
				'secure'    => $secure,
				'user'      => isset( $c['user'] ) ? sanitize_text_field( $c['user'] ) : '',
				'pass'      => isset( $c['pass'] ) ? (string) $c['pass'] : '',
				'from'      => isset( $c['from'] ) && is_email( $c['from'] ) ? sanitize_email( $c['from'] ) : '',
				'from_name' => isset( $c['from_name'] ) ? sanitize_text_field( $c['from_name'] ) : '',
				'reply_to'  => isset( $c['reply_to'] ) && is_email( $c['reply_to'] ) ? sanitize_email( $c['reply_to'] ) : '',
			);
			$ids[] = $id;
		}

		$clean_routes = array();
		foreach ( (array) $routes as $r ) {
			$match = isset( $r['match'] ) ? $r['match'] : 'all';
			$match = in_array( $match, array( 'all', 'from_email', 'from_name' ), true ) ? $match : 'all';
			$conn  = isset( $r['conn'] ) ? sanitize_key( $r['conn'] ) : '';
			if ( ! in_array( $conn, $ids, true ) ) {
				continue;
			}
			$clean_routes[] = array(
				'match' => $match,
				'value' => isset( $r['value'] ) ? sanitize_text_field( $r['value'] ) : '',
				'conn'  => $conn,
			);
		}

		$primary  = sanitize_key( $primary );
		$fallback = sanitize_key( $fallback );
		if ( ! in_array( $primary, $ids, true ) ) {
			$primary = $ids ? $ids[0] : '';
		}
		if ( ! in_array( $fallback, $ids, true ) ) {
			$fallback = '';
		}

		Velox_Settings::save( array_merge( Velox_Settings::all(), array(
			'mail_connections' => wp_json_encode( $clean_conns ),
			'mail_routes'      => wp_json_encode( $clean_routes ),
			'mail_primary'     => $primary,
			'mail_fallback'    => $fallback,
		) ) );

		return array(
			'ok'          => true,
			'message'     => 'Mail connections saved.',
			'connections' => $clean_conns,
			'routes'      => $clean_routes,
			'primary'     => $primary,
			'fallback'    => $fallback,
		);
	}
}
