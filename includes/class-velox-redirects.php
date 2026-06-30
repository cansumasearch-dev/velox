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

	const DB_VERSION = '2';
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
			match_type VARCHAR(20) NOT NULL DEFAULT 'exact',
			priority INT NOT NULL DEFAULT 0,
			category VARCHAR(100) NOT NULL DEFAULT '',
			description VARCHAR(255) NOT NULL DEFAULT '',
			active TINYINT(1) NOT NULL DEFAULT 1,
			ignore_case TINYINT(1) NOT NULL DEFAULT 1,
			ignore_query TINYINT(1) NOT NULL DEFAULT 1,
			ignore_slash TINYINT(1) NOT NULL DEFAULT 1,
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
		$raw = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( '' === $raw ) {
			return;
		}

		foreach ( $map as $rule ) {
			$resolved = self::match_rule( $rule, $raw );
			if ( false === $resolved ) {
				continue;
			}

			// Count the hit (only fires on an actual match).
			global $wpdb;
			$wpdb->query( $wpdb->prepare(
				'UPDATE ' . self::table_redirects() . ' SET hits = hits + 1, last_hit = %s WHERE id = %d',
				current_time( 'mysql' ),
				(int) $rule['id']
			) );

			$type = (int) $rule['type'];
			if ( 410 === $type ) {
				status_header( 410 );
				nocache_headers();
				wp_die( esc_html__( 'This content is no longer available.', 'velox' ), '', array( 'response' => 410 ) );
			}

			$target = $resolved;
			if ( '' !== $target && '/' === $target[0] ) {
				$target = home_url( $target );
			}
			wp_redirect( $target, $type ); // phpcs:ignore WordPress.Security.SafeRedirect
			exit;
		}
	}

	/**
	 * Test a single rule against the raw request URI.
	 * Returns the resolved target string on a match, or false when it doesn't match.
	 */
	protected static function match_rule( $rule, $raw ) {
		$ignore_case  = ! empty( $rule['ignore_case'] );
		$ignore_query = ! isset( $rule['ignore_query'] ) || ! empty( $rule['ignore_query'] );
		$ignore_slash = ! isset( $rule['ignore_slash'] ) || ! empty( $rule['ignore_slash'] );
		$mt           = isset( $rule['match_type'] ) ? $rule['match_type'] : 'exact';

		$subject = self::request_subject( $raw, ! $ignore_query, ! $ignore_slash );
		if ( '' === $subject ) {
			return false;
		}
		$src    = (string) $rule['source'];
		$target = (string) $rule['target'];

		if ( 'regex' === $mt ) {
			$pattern = '#' . str_replace( '#', '\#', $src ) . '#' . ( $ignore_case ? 'i' : '' );
			$ok      = @preg_match( $pattern, $subject ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( 1 !== $ok ) {
				return false;
			}
			if ( '' !== $target ) {
				$rep = @preg_replace( $pattern, $target, $subject ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( is_string( $rep ) ) {
					$target = $rep;
				}
			}
			return $target;
		}

		$s = $ignore_case ? strtolower( $subject ) : $subject;
		$c = $ignore_case ? strtolower( $src ) : $src;

		if ( 'prefix' === $mt ) {
			return ( '' !== $c && 0 === strpos( $s, $c ) ) ? $target : false;
		}
		// Exact.
		return ( $s === $c ) ? $target : false;
	}

	/** Build the request path to test against, honouring the per-rule query/slash flags. */
	protected static function request_subject( $raw, $keep_query, $keep_slash ) {
		$parts = wp_parse_url( $raw );
		$path  = isset( $parts['path'] ) ? $parts['path'] : '';
		if ( ! is_string( $path ) || '' === $path ) {
			return '';
		}
		$path = '/' . ltrim( $path, '/' );
		if ( ! $keep_slash ) {
			$path = rtrim( $path, '/' );
			if ( '' === $path ) {
				$path = '/';
			}
		}
		if ( $keep_query && ! empty( $parts['query'] ) ) {
			$path .= '?' . $parts['query'];
		}
		return $path;
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
		// Only active rules are matched on the front end. Ordered so the matcher can
		// iterate and take the first hit: highest priority first, then exact before
		// prefix before regex (most specific wins on a tie), then oldest id.
		$rows = $wpdb->get_results(
			'SELECT id, source, target, type, match_type, priority, ignore_case, ignore_query, ignore_slash
			 FROM ' . self::table_redirects() . ' WHERE active = 1',
			ARRAY_A
		);
		$map = array();
		if ( $rows ) {
			$rank = array( 'exact' => 0, 'prefix' => 1, 'regex' => 2 );
			usort( $rows, function ( $a, $b ) use ( $rank ) {
				if ( (int) $a['priority'] !== (int) $b['priority'] ) {
					return (int) $b['priority'] - (int) $a['priority'];
				}
				$ra = isset( $rank[ $a['match_type'] ] ) ? $rank[ $a['match_type'] ] : 0;
				$rb = isset( $rank[ $b['match_type'] ] ) ? $rank[ $b['match_type'] ] : 0;
				if ( $ra !== $rb ) {
					return $ra - $rb;
				}
				return (int) $a['id'] - (int) $b['id'];
			} );
			foreach ( $rows as $row ) {
				$map[] = array(
					'id'           => (int) $row['id'],
					'source'       => $row['source'],
					'target'       => $row['target'],
					'type'         => (int) $row['type'],
					'match_type'   => $row['match_type'],
					'ignore_case'  => (int) $row['ignore_case'],
					'ignore_query' => (int) $row['ignore_query'],
					'ignore_slash' => (int) $row['ignore_slash'],
				);
			}
		}
		update_option( self::MAP_OPTION, $map, false );
	}

	/** Normalise the extra rule options coming from the editor. Unset = on (the safe default). */
	protected static function clean_opts( $opts ) {
		$mt = isset( $opts['match_type'] ) ? $opts['match_type'] : 'exact';
		$mt = in_array( $mt, array( 'exact', 'prefix', 'regex' ), true ) ? $mt : 'exact';
		return array(
			'match_type'   => $mt,
			'priority'     => isset( $opts['priority'] ) ? (int) $opts['priority'] : 0,
			'category'     => isset( $opts['category'] ) ? substr( sanitize_text_field( $opts['category'] ), 0, 100 ) : '',
			'description'  => isset( $opts['description'] ) ? substr( sanitize_text_field( $opts['description'] ), 0, 255 ) : '',
			'active'       => ( ! isset( $opts['active'] ) || ! empty( $opts['active'] ) ) ? 1 : 0,
			'ignore_case'  => ( ! isset( $opts['ignore_case'] ) || ! empty( $opts['ignore_case'] ) ) ? 1 : 0,
			'ignore_query' => ( ! isset( $opts['ignore_query'] ) || ! empty( $opts['ignore_query'] ) ) ? 1 : 0,
			'ignore_slash' => ( ! isset( $opts['ignore_slash'] ) || ! empty( $opts['ignore_slash'] ) ) ? 1 : 0,
		);
	}

	/** Validate + normalise source/target for a given match type. Returns ok+source+target or an error. */
	protected static function prep( $source, $target, $type, $mt ) {
		if ( 'exact' === $mt ) {
			$source = self::normalize( $source );
		} elseif ( 'prefix' === $mt ) {
			$source = trim( $source );
			if ( '' !== $source && '/' !== $source[0] && ! preg_match( '#^https?://#i', $source ) ) {
				$source = '/' . ltrim( $source, '/' );
			}
			$source = rtrim( $source, '/' );
			if ( '' === $source ) {
				$source = '/';
			}
		} else {
			$source = trim( $source ); // regex pattern, stored verbatim
		}
		if ( '' === $source ) {
			return array( 'ok' => false, 'message' => 'Enter a source path or pattern.' );
		}
		if ( 410 === (int) $type ) {
			$target = '';
		} else {
			$target = trim( $target );
			if ( '' === $target ) {
				return array( 'ok' => false, 'message' => 'Enter where it should go.' );
			}
			// Exact/prefix targets may be a relative path or a full URL; regex targets are left
			// verbatim so back-references like $1 survive.
			if ( 'regex' !== $mt && '/' !== $target[0] && ! preg_match( '#^https?://#i', $target ) ) {
				$target = '/' . ltrim( $target, '/' );
			}
		}
		if ( 'exact' === $mt && $source === self::normalize( $target ) ) {
			return array( 'ok' => false, 'message' => 'Source and target are the same — that would loop.' );
		}
		return array( 'ok' => true, 'source' => $source, 'target' => $target );
	}

	public static function add( $source, $target, $type = 301, $opts = array() ) {
		$type = in_array( (int) $type, array( 301, 302, 307, 410 ), true ) ? (int) $type : 301;
		$o    = self::clean_opts( $opts );
		$p    = self::prep( $source, $target, $type, $o['match_type'] );
		if ( empty( $p['ok'] ) ) {
			return $p;
		}

		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			'INSERT INTO ' . self::table_redirects() . ' (source, target, type, match_type, priority, category, description, active, ignore_case, ignore_query, ignore_slash, created)
			 VALUES (%s, %s, %d, %s, %d, %s, %s, %d, %d, %d, %d, %s)
			 ON DUPLICATE KEY UPDATE target = VALUES(target), type = VALUES(type), match_type = VALUES(match_type), priority = VALUES(priority), category = VALUES(category), description = VALUES(description), active = VALUES(active), ignore_case = VALUES(ignore_case), ignore_query = VALUES(ignore_query), ignore_slash = VALUES(ignore_slash)',
			$p['source'], $p['target'], $type, $o['match_type'], $o['priority'], $o['category'], $o['description'], $o['active'], $o['ignore_case'], $o['ignore_query'], $o['ignore_slash'], current_time( 'mysql' )
		) );
		self::rebuild_map();
		return array( 'ok' => true );
	}

	public static function update( $id, $source, $target, $type = 301, $opts = array() ) {
		$id = (int) $id;
		if ( ! $id ) {
			return array( 'ok' => false, 'message' => 'Missing redirect id.' );
		}
		$type = in_array( (int) $type, array( 301, 302, 307, 410 ), true ) ? (int) $type : 301;
		$o    = self::clean_opts( $opts );
		$p    = self::prep( $source, $target, $type, $o['match_type'] );
		if ( empty( $p['ok'] ) ) {
			return $p;
		}
		global $wpdb;
		$wpdb->update(
			self::table_redirects(),
			array(
				'source'       => $p['source'],
				'target'       => $p['target'],
				'type'         => $type,
				'match_type'   => $o['match_type'],
				'priority'     => $o['priority'],
				'category'     => $o['category'],
				'description'  => $o['description'],
				'active'       => $o['active'],
				'ignore_case'  => $o['ignore_case'],
				'ignore_query' => $o['ignore_query'],
				'ignore_slash' => $o['ignore_slash'],
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%d' ),
			array( '%d' )
		);
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
