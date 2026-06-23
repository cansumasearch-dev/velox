<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirect manager + 404 logger.
 *
 * Redirects live in a table for management/stats, and are mirrored to an option
 * so front-end matching is a single in-memory array lookup (no query per request).
 * 404s are aggregated by path so the log stays bounded by unique URLs, not hits.
 */
class Velox_Redirects {

	const DB_VERSION = '1';
	const MAP_OPTION = 'velox_redirects_map';
	const VER_OPTION = 'velox_redirects_db';

	public static function table_redirects() {
		global $wpdb;
		return $wpdb->prefix . 'velox_redirects';
	}
	public static function table_404s() {
		global $wpdb;
		return $wpdb->prefix . 'velox_404s';
	}

	/* ----------------------------------------------------------------- setup */

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$r = self::table_redirects();
		$f = self::table_404s();

		dbDelta( "CREATE TABLE {$r} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source VARCHAR(190) NOT NULL,
			target TEXT NOT NULL,
			type SMALLINT NOT NULL DEFAULT 301,
			hits BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created DATETIME NULL,
			last_hit DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source (source)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$f} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			path VARCHAR(190) NOT NULL,
			hits BIGINT UNSIGNED NOT NULL DEFAULT 1,
			referer VARCHAR(190) NULL,
			last_seen DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY path (path)
		) {$charset};" );

		update_option( self::VER_OPTION, self::DB_VERSION );
		self::rebuild_map();
	}

	/** Create the tables on update too, not just fresh activation. */
	public static function maybe_install() {
		if ( get_option( self::VER_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/* ----------------------------------------------------------------- hooks */

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 1 );
		if ( Velox_Settings::get( 'util_redirects_log_404', true ) ) {
			add_action( 'template_redirect', array( __CLASS__, 'maybe_log_404' ), 99 );
		}
	}

	public static function maybe_redirect() {
		$map = get_option( self::MAP_OPTION, array() );
		if ( empty( $map ) || ! is_array( $map ) ) {
			return;
		}
		$req = self::normalize( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' );
		if ( '' === $req || ! isset( $map[ $req ] ) ) {
			return;
		}
		$rule = $map[ $req ];
		$type = (int) $rule['type'];

		// Count the hit (only fires on an actual match).
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . self::table_redirects() . ' SET hits = hits + 1, last_hit = %s WHERE id = %d',
			current_time( 'mysql' ),
			(int) $rule['id']
		) );

		if ( 410 === $type ) {
			status_header( 410 );
			nocache_headers();
			wp_die( esc_html__( 'This content is no longer available.', 'velox' ), '', array( 'response' => 410 ) );
		}

		$target = $rule['target'];
		if ( '' !== $target && '/' === $target[0] ) {
			$target = home_url( $target );
		}
		wp_redirect( $target, $type ); // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	}

	public static function maybe_log_404() {
		if ( ! is_404() ) {
			return;
		}
		$path = self::normalize( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' );
		if ( '' === $path ) {
			return;
		}
		// Ignore obvious asset probes that just spam the log.
		if ( preg_match( '/\.(php|aspx?|env|git|map|ico|png|jpe?g|gif|svg|css|js|woff2?)$/i', $path ) ) {
			return;
		}
		$ref = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		global $wpdb;
		$table = self::table_404s();
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$table} (path, hits, referer, last_seen) VALUES (%s, 1, %s, %s)
			 ON DUPLICATE KEY UPDATE hits = hits + 1, referer = VALUES(referer), last_seen = VALUES(last_seen)",
			substr( $path, 0, 190 ),
			substr( $ref, 0, 190 ),
			current_time( 'mysql' )
		) );
	}

	/* ------------------------------------------------------------- normalise */

	public static function normalize( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return '';
		}
		$path = '/' . ltrim( $path, '/' );
		$path = rtrim( $path, '/' );
		return '' === $path ? '/' : $path;
	}

	/* ------------------------------------------------------------------- CRUD */

	public static function rebuild_map() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT id, source, target, type FROM ' . self::table_redirects(), ARRAY_A );
		$map  = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$map[ $row['source'] ] = array(
					'id'     => (int) $row['id'],
					'target' => $row['target'],
					'type'   => (int) $row['type'],
				);
			}
		}
		update_option( self::MAP_OPTION, $map, false );
	}

	public static function add( $source, $target, $type = 301 ) {
		$source = self::normalize( $source );
		$type   = in_array( (int) $type, array( 301, 302, 307, 410 ), true ) ? (int) $type : 301;
		if ( '' === $source ) {
			return array( 'ok' => false, 'message' => 'Enter a source path.' );
		}
		if ( 410 !== $type ) {
			$target = trim( $target );
			if ( '' === $target ) {
				return array( 'ok' => false, 'message' => 'Enter where it should go.' );
			}
			// Allow a relative path or a full URL.
			if ( '/' !== $target[0] && ! preg_match( '#^https?://#i', $target ) ) {
				$target = '/' . ltrim( $target, '/' );
			}
		} else {
			$target = '';
		}
		if ( $source === self::normalize( $target ) ) {
			return array( 'ok' => false, 'message' => 'Source and target are the same — that would loop.' );
		}

		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			'INSERT INTO ' . self::table_redirects() . ' (source, target, type, created) VALUES (%s, %s, %d, %s)
			 ON DUPLICATE KEY UPDATE target = VALUES(target), type = VALUES(type)',
			$source,
			$target,
			$type,
			current_time( 'mysql' )
		) );
		self::rebuild_map();
		return array( 'ok' => true );
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::table_redirects(), array( 'id' => (int) $id ), array( '%d' ) );
		self::rebuild_map();
		return array( 'ok' => true );
	}

	public static function list_redirects() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table_redirects() . ' ORDER BY created DESC', ARRAY_A ) ?: array();
	}

	public static function list_404s( $limit = 100 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table_404s() . ' ORDER BY hits DESC, last_seen DESC LIMIT %d',
			(int) $limit
		), ARRAY_A ) ?: array();
	}

	public static function clear_404s() {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . self::table_404s() );
		return array( 'ok' => true );
	}

	public static function forget_404( $id ) {
		global $wpdb;
		$wpdb->delete( self::table_404s(), array( 'id' => (int) $id ), array( '%d' ) );
		return array( 'ok' => true );
	}
}
