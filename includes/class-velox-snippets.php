<?php
/**
 * Code Snippets — a small Code-Snippets-style manager.
 *
 * Stores PHP / CSS / JS / HTML snippets in a custom table, runs the active ones
 * by scope + priority, and gives them their own top-level "Snippets" admin menu
 * below Velox. PHP snippets run through a guarded eval that auto-disables a
 * snippet if it errors, so a bad snippet can't permanently white-screen the site.
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Snippets {

	const DB_VERSION = '2';
	const DB_OPTION  = 'velox_snippets_db';
	const MENU_SLUG  = 'velox-snippets';

	const TYPES  = array( 'php', 'css', 'js', 'html' );
	const SCOPES = array( 'everywhere', 'admin', 'front', 'once' );

	/** Id of the PHP snippet currently being eval'd — read by the shutdown guard. */
	private static $executing = 0;

	const SAFE_OPTION  = 'velox_snippets_safe';   // breadcrumb: id we're about to run
	const PANIC_OPTION = 'velox_snippets_panic';   // how many consecutive crashes seen

	/**
	 * Safe Mode is ON (no PHP snippets run) when any of these is true:
	 *  - the URL carries ?velox-safe-mode=1 (admin escape hatch)
	 *  - the constant VELOX_SNIPPETS_SAFE_MODE is defined truthy (wp-config rescue)
	 *  - the panic counter tripped (too many back-to-back crashes)
	 */
	public static function safe_mode() {
		if ( defined( 'VELOX_SNIPPETS_SAFE_MODE' ) && VELOX_SNIPPETS_SAFE_MODE ) {
			return true;
		}
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['velox-safe-mode'] ) && '1' === $_GET['velox-safe-mode'] ) {
			return true;
		}
		if ( (int) get_option( self::PANIC_OPTION, 0 ) >= 2 ) {
			return true;
		}
		return false;
	}

	/* ------------------------------------------------------------------ */
	/* Bootstrap                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Locations available for a snippet type. PHP uses run-contexts (stored in
	 * `scope`); css/js/html use output placements (stored in `location`).
	 */
	public static function locations_for( $type ) {
		if ( 'php' === $type ) {
			return array(
				'everywhere' => 'Run everywhere',
				'front'      => 'Frontend only',
				'admin'      => 'Admin only',
				'once'       => 'Run once, then switch off',
			);
		}
		if ( 'css' === $type ) {
			return array(
				'head'        => 'Site <head>',
				'site_footer' => 'Site footer',
			);
		}
		// js / html
		return array(
			'site_header'      => 'Site header (before </head>)',
			'site_body'        => 'After <body> open',
			'site_footer'      => 'Site footer (before </body>)',
			'before_content'   => 'Before post content',
			'after_content'    => 'After post content',
			'before_paragraph' => 'Before paragraph N',
			'after_paragraph'  => 'After paragraph N',
			'shortcode'        => 'Shortcode only',
		);
	}

	/** Output placements that need a paragraph number. */
	public static function location_needs_num( $location ) {
		return in_array( $location, array( 'before_paragraph', 'after_paragraph' ), true );
	}

	/** Effective output location for a snippet, with legacy fallback by type. */
	private static function loc( $s ) {
		$l = isset( $s['location'] ) ? (string) $s['location'] : '';
		if ( '' !== $l ) { return $l; }
		return ( 'css' === $s['type'] ) ? 'head' : 'site_footer'; // pre-2.x snippets
	}

	public static function init() {
		self::maybe_install();

		if ( ! Velox_Settings::get( 'util_snippets' ) ) {
			return;
		}

		// Catch fatals from a snippet and disable it on the next load.
		register_shutdown_function( array( __CLASS__, 'shutdown_guard' ) );

		// PHP snippets run now, early enough to hook into WordPress.
		self::run_php();

		// Output snippets (css/js/html) route to the chosen placement.
		add_action( 'wp_head',      array( __CLASS__, 'output_head' ), 20 );
		add_action( 'admin_head',   array( __CLASS__, 'output_head' ), 20 );
		add_action( 'wp_body_open', array( __CLASS__, 'output_body' ), 20 );
		add_action( 'wp_footer',    array( __CLASS__, 'output_footer' ), 20 );
		add_action( 'admin_footer', array( __CLASS__, 'output_footer' ), 20 );
		add_filter( 'the_content',  array( __CLASS__, 'filter_content' ), 20 );
		add_shortcode( 'velox_snippet', array( __CLASS__, 'shortcode' ) );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
			add_action( 'admin_head', array( __CLASS__, 'menu_icon_css' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
			add_action( 'admin_post_velox_snippet_export', array( __CLASS__, 'handle_export' ) );
		}
	}

	/** Verify nonce + cap, then stream the exported-plugin zip. */
	public static function handle_export() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'velox_snippet_export_' . $id );
		self::export_plugin_zip( $id );
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'velox_snippets';
	}

	public static function maybe_install() {
		if ( get_option( self::DB_OPTION ) === self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		$table   = self::table();
		$collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE $table (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				name VARCHAR(190) NOT NULL DEFAULT '',
				description TEXT NULL,
				type VARCHAR(20) NOT NULL DEFAULT 'php',
				code LONGTEXT NULL,
				scope VARCHAR(20) NOT NULL DEFAULT 'everywhere',
				location VARCHAR(32) NOT NULL DEFAULT '',
				location_num INT NOT NULL DEFAULT 1,
				priority INT NOT NULL DEFAULT 10,
				active TINYINT(1) NOT NULL DEFAULT 0,
				trashed TINYINT(1) NOT NULL DEFAULT 0,
				created DATETIME NULL,
				modified DATETIME NULL,
				PRIMARY KEY  (id),
				KEY active (active),
				KEY trashed (trashed)
			) $collate;"
		);
		update_option( self::DB_OPTION, self::DB_VERSION );
	}

	/* ------------------------------------------------------------------ */
	/* Data                                                               */
	/* ------------------------------------------------------------------ */

	/** @param string $filter all|active|inactive|trash */
	public static function all( $filter = 'all' ) {
		global $wpdb;
		$table = self::table();
		switch ( $filter ) {
			case 'active':
				$where = 'trashed = 0 AND active = 1';
				break;
			case 'inactive':
				$where = 'trashed = 0 AND active = 0';
				break;
			case 'trash':
				$where = 'trashed = 1';
				break;
			default:
				$where = 'trashed = 0';
		}
		// phpcs:ignore WordPress.DB.PreparedSQL
		return $wpdb->get_results( "SELECT * FROM $table WHERE $where ORDER BY priority ASC, name ASC", ARRAY_A ) ?: array();
	}

	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ), ARRAY_A );
	}

	public static function counts() {
		global $wpdb;
		$table = self::table();
		return array(
			'all'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE trashed = 0" ), // phpcs:ignore WordPress.DB.PreparedSQL
			'active'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE trashed = 0 AND active = 1" ), // phpcs:ignore WordPress.DB.PreparedSQL
			'inactive' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE trashed = 0 AND active = 0" ), // phpcs:ignore WordPress.DB.PreparedSQL
			'trash'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE trashed = 1" ), // phpcs:ignore WordPress.DB.PreparedSQL
		);
	}

	/**
	 * Insert or update a snippet.
	 *
	 * @param array $data id?, name, description, type, code, scope, priority, active?
	 * @return array { ok, id?, message? }
	 */
	public static function save( $data ) {
		global $wpdb;
		$id    = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$type  = in_array( $data['type'] ?? '', self::TYPES, true ) ? $data['type'] : 'php';
		$scope = in_array( $data['scope'] ?? '', self::SCOPES, true ) ? $data['scope'] : 'everywhere';
		$name  = sanitize_text_field( $data['name'] ?? '' );
		if ( '' === trim( $name ) ) {
			$name = 'Untitled snippet';
		}
		$code     = (string) ( $data['code'] ?? '' );
		$priority = isset( $data['priority'] ) ? (int) $data['priority'] : 10;
		$active   = ! empty( $data['active'] ) ? 1 : 0;

		// Output placement (css/js/html). PHP ignores this and uses scope.
		$loc_in   = isset( $data['location'] ) ? sanitize_key( $data['location'] ) : '';
		$valid    = array_keys( self::locations_for( $type ) );
		$location = ( 'php' === $type ) ? '' : ( in_array( $loc_in, $valid, true ) ? $loc_in : '' );
		$loc_num  = isset( $data['location_num'] ) ? max( 1, (int) $data['location_num'] ) : 1;

		// Never let a PHP snippet with a syntax error get saved as active.
		if ( 'php' === $type && $active ) {
			$lint = self::lint_php( $code );
			if ( true !== $lint ) {
				return array( 'ok' => false, 'message' => 'PHP error, not activated: ' . $lint );
			}
		}

		$row = array(
			'name'        => $name,
			'description' => sanitize_textarea_field( $data['description'] ?? '' ),
			'type'        => $type,
			'code'        => $code,
			'scope'       => $scope,
			'location'    => $location,
			'location_num'=> $loc_num,
			'priority'    => $priority,
			'active'      => $active,
			'modified'    => current_time( 'mysql' ),
		);
		$fmt = array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s' );

		if ( $id ) {
			$wpdb->update( self::table(), $row, array( 'id' => $id ), $fmt, array( '%d' ) );
		} else {
			$row['created'] = current_time( 'mysql' );
			$fmt[]          = '%s';
			$wpdb->insert( self::table(), $row, $fmt );
			$id = (int) $wpdb->insert_id;
		}
		return array( 'ok' => true, 'id' => $id );
	}

	/** Toggle active. Lints PHP before turning one on. */
	public static function set_active( $id, $active, $note = '' ) {
		global $wpdb;
		$id      = (int) $id;
		$snippet = self::get( $id );
		if ( ! $snippet ) {
			return array( 'ok' => false, 'message' => 'Snippet not found.' );
		}
		if ( $active && 'php' === $snippet['type'] ) {
			$lint = self::lint_php( $snippet['code'] );
			if ( true !== $lint ) {
				return array( 'ok' => false, 'message' => 'PHP error, not activated: ' . $lint );
			}
		}
		$wpdb->update( self::table(), array( 'active' => $active ? 1 : 0 ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
		if ( $note && class_exists( 'Velox_Activity' ) ) {
			// best-effort breadcrumb; ignored if activity log isn't around
			do_action( 'velox_snippet_note', $id, $note );
		}
		return array( 'ok' => true, 'active' => $active ? 1 : 0 );
	}

	public static function trash( $id ) {
		global $wpdb;
		$wpdb->update( self::table(), array( 'trashed' => 1, 'active' => 0 ), array( 'id' => (int) $id ), array( '%d', '%d' ), array( '%d' ) );
		return array( 'ok' => true );
	}

	public static function restore( $id ) {
		global $wpdb;
		$wpdb->update( self::table(), array( 'trashed' => 0 ), array( 'id' => (int) $id ), array( '%d' ), array( '%d' ) );
		return array( 'ok' => true );
	}

	public static function delete_permanent( $id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
		return array( 'ok' => true );
	}

	public static function duplicate( $id ) {
		$snippet = self::get( $id );
		if ( ! $snippet ) {
			return array( 'ok' => false, 'message' => 'Snippet not found.' );
		}
		return self::save( array(
			'name'        => $snippet['name'] . ' (copy)',
			'description' => $snippet['description'],
			'type'        => $snippet['type'],
			'code'        => $snippet['code'],
			'scope'       => $snippet['scope'],
			'priority'    => $snippet['priority'],
			'active'      => 0, // copies start switched off
		) );
	}

	/* ------------------------------------------------------------------ */
	/* PHP validation + execution                                         */
	/* ------------------------------------------------------------------ */

	private static function strip_php_open( $code ) {
		$code = preg_replace( '/^\s*<\?(php)?/i', '', $code, 1 );
		$code = preg_replace( '/\?>\s*$/', '', $code, 1 );
		return $code;
	}

	/**
	 * Syntax-check PHP WITHOUT executing it. We wrap the code in `if (false) { … }`
	 * and eval that: PHP must parse the whole thing (so a syntax error throws a
	 * ParseError) but the block never runs, so there are no side effects.
	 *
	 * @return true|string  true if valid, else the error message.
	 */
	public static function lint_php( $code ) {
		$code = self::strip_php_open( $code );
		if ( '' === trim( $code ) ) {
			return true;
		}
		try {
			eval( 'if ( false ) { ' . $code . "\n}" ); // phpcs:ignore Squiz.PHP.Eval
			return true;
		} catch ( \ParseError $e ) {
			return $e->getMessage();
		} catch ( \Throwable $e ) {
			return true; // not executed, so anything else isn't a syntax problem
		}
	}

	private static function scope_matches( $scope ) {
		if ( 'everywhere' === $scope || 'once' === $scope ) {
			return true;
		}
		if ( 'admin' === $scope ) {
			return is_admin();
		}
		return ! is_admin(); // front
	}

	public static function run_php() {
		// Safe Mode: skip every PHP snippet so a fatal one can't lock you out.
		if ( self::safe_mode() ) {
			return;
		}

		// Recovery: if a breadcrumb survived from last request, that snippet hard-
		// crashed the whole process (our shutdown guard never got to run). Disable
		// it now and bump the panic counter so repeated crashes trip Safe Mode.
		$crashed = (int) get_option( self::SAFE_OPTION, 0 );
		if ( $crashed ) {
			delete_option( self::SAFE_OPTION );
			self::set_active( $crashed, false, 'Auto-disabled after it crashed the site on the previous load.' );
			update_option( self::PANIC_OPTION, (int) get_option( self::PANIC_OPTION, 0 ) + 1, false );
			return; // don't run anything else this load — let the site recover first
		}

		foreach ( self::all( 'active' ) as $snippet ) {
			if ( 'php' !== $snippet['type'] || ! self::scope_matches( $snippet['scope'] ) ) {
				continue;
			}
			self::execute_php( $snippet );
		}

		// A clean full pass with no crash → reset the panic counter.
		if ( (int) get_option( self::PANIC_OPTION, 0 ) > 0 ) {
			update_option( self::PANIC_OPTION, 0, false );
		}
	}

	private static function execute_php( $snippet ) {
		$code = self::strip_php_open( (string) $snippet['code'] );
		if ( '' === trim( $code ) ) {
			return;
		}
		self::$executing = (int) $snippet['id'];
		// Breadcrumb BEFORE eval: if the process dies entirely (white screen,
		// memory/timeout, the shutdown guard never firing), the next load reads
		// this and disables the culprit. Cleared the moment eval returns OK.
		update_option( self::SAFE_OPTION, (int) $snippet['id'], false );
		try {
			eval( $code ); // phpcs:ignore Squiz.PHP.Eval
		} catch ( \Throwable $e ) {
			self::set_active( $snippet['id'], false, 'Disabled after an error: ' . $e->getMessage() );
		}
		delete_option( self::SAFE_OPTION );
		self::$executing = 0;

		// "Run once" → switch off after a successful run.
		if ( 'once' === $snippet['scope'] ) {
			self::set_active( $snippet['id'], false, 'Ran once.' );
		}
	}

	/** Runs after a fatal: disables whichever snippet was mid-execution. */
	public static function shutdown_guard() {
		if ( ! self::$executing ) {
			return;
		}
		$err = error_get_last();
		$fatal = array( E_ERROR, E_PARSE, E_COMPILE, E_CORE_ERROR, E_USER_ERROR );
		if ( $err && in_array( $err['type'], $fatal, true ) ) {
			self::set_active( self::$executing, false, 'Auto-disabled after a fatal error: ' . $err['message'] );
			delete_option( self::SAFE_OPTION ); // handled here → no need for next-load recovery
			update_option( self::PANIC_OPTION, (int) get_option( self::PANIC_OPTION, 0 ) + 1, false );
		}
	}

	/** Manual reset of Safe Mode's panic counter + breadcrumb (from the admin). */
	public static function clear_panic() {
		delete_option( self::SAFE_OPTION );
		update_option( self::PANIC_OPTION, 0, false );
		return array( 'ok' => true );
	}

	/** Turn every PHP snippet off in one shot (admin "disable all" rescue). */
	public static function disable_all_php() {
		global $wpdb;
		$wpdb->query( "UPDATE " . self::table() . " SET active = 0 WHERE type = 'php' AND active = 1" ); // phpcs:ignore WordPress.DB
		self::clear_panic();
		return array( 'ok' => true );
	}

	/* ------------------------------------------------------------------ */
	/* CSS / JS / HTML output                                             */
	/* ------------------------------------------------------------------ */

	/** Wrap a snippet's code by type. */
	private static function emit( $s ) {
		$code = (string) $s['code'];
		if ( '' === trim( $code ) ) { return ''; }
		$id = (int) $s['id'];
		if ( 'css' === $s['type'] ) { return "\n<style id=\"velox-snippet-{$id}\">\n{$code}\n</style>\n"; }
		if ( 'js' === $s['type'] )  { return "\n<script id=\"velox-snippet-{$id}\">\n{$code}\n</script>\n"; }
		return "\n<!-- velox-snippet {$id} -->\n{$code}\n"; // html
	}

	/** css(head) + js/html(site_header). */
	public static function output_head() {
		foreach ( self::all( 'active' ) as $s ) {
			if ( 'php' === $s['type'] || ! self::scope_matches( $s['scope'] ) ) { continue; }
			$loc = self::loc( $s );
			$ok  = ( 'css' === $s['type'] && 'head' === $loc ) || ( in_array( $s['type'], array( 'js', 'html' ), true ) && 'site_header' === $loc );
			if ( ! $ok ) { continue; }
			echo self::emit( $s ); // phpcs:ignore WordPress.Security.EscapeOutput
			self::maybe_run_once( $s );
		}
	}

	/** js/html(site_body) — front-end only hook. */
	public static function output_body() {
		foreach ( self::all( 'active' ) as $s ) {
			if ( ! in_array( $s['type'], array( 'js', 'html' ), true ) || ! self::scope_matches( $s['scope'] ) ) { continue; }
			if ( 'site_body' !== self::loc( $s ) ) { continue; }
			echo self::emit( $s ); // phpcs:ignore WordPress.Security.EscapeOutput
			self::maybe_run_once( $s );
		}
	}

	/** css(site_footer) + js/html(site_footer, incl. legacy default). */
	public static function output_footer() {
		foreach ( self::all( 'active' ) as $s ) {
			if ( 'php' === $s['type'] || ! self::scope_matches( $s['scope'] ) ) { continue; }
			if ( 'site_footer' !== self::loc( $s ) ) { continue; }
			echo self::emit( $s ); // phpcs:ignore WordPress.Security.EscapeOutput
			self::maybe_run_once( $s );
		}
	}

	/** Insert js/html snippets into post content (before/after content or paragraph N). */
	public static function filter_content( $content ) {
		if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$pre = '';
		$post = '';
		foreach ( self::all( 'active' ) as $s ) {
			if ( ! in_array( $s['type'], array( 'js', 'html' ), true ) || ! self::scope_matches( $s['scope'] ) ) { continue; }
			$loc  = self::loc( $s );
			$html = self::emit( $s );
			if ( '' === $html ) { continue; }
			$num = max( 1, (int) ( $s['location_num'] ?? 1 ) );
			if ( 'before_content' === $loc )      { $pre  .= $html; self::maybe_run_once( $s ); }
			elseif ( 'after_content' === $loc )   { $post .= $html; self::maybe_run_once( $s ); }
			elseif ( 'before_paragraph' === $loc ) { $content = self::insert_at_paragraph( $content, $html, $num, false ); self::maybe_run_once( $s ); }
			elseif ( 'after_paragraph' === $loc )  { $content = self::insert_at_paragraph( $content, $html, $num, true ); self::maybe_run_once( $s ); }
		}
		return $pre . $content . $post;
	}

	/** Insert markup before/after the Nth paragraph (splitting on </p>). */
	private static function insert_at_paragraph( $content, $insert, $n, $after ) {
		$parts = explode( '</p>', $content );
		$count = count( $parts ) - 1; // number of closing </p> tags
		if ( $count < 1 ) { return $after ? $content . $insert : $insert . $content; }
		$n   = max( 1, min( $n, $count ) );
		$out = '';
		$last = count( $parts ) - 1;
		foreach ( $parts as $i => $para ) {
			if ( $i < $last ) { $para .= '</p>'; }
			if ( ! $after && $i === $n - 1 ) { $out .= $insert; }
			$out .= $para;
			if ( $after && $i === $n - 1 ) { $out .= $insert; }
		}
		return $out;
	}

	private static function maybe_run_once( $s ) {
		if ( 'once' === $s['scope'] ) {
			self::set_active( $s['id'], false, 'Ran once.' );
		}
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'velox_snippet' );
		$s    = self::get( (int) $atts['id'] );
		if ( ! $s || ! in_array( $s['type'], array( 'html', 'js' ), true ) || ! $s['active'] ) {
			return '';
		}
		return 'js' === $s['type'] ? self::emit( $s ) : $s['code'];
	}

	/* ------------------------------------------------------------------ */
	/* Export as a standalone plugin                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * Turn a snippet into a self-contained WordPress plugin: a folder with a
	 * single PHP file that does exactly what the snippet did, with no dependency
	 * on Velox. Returns the generated plugin's main-file source + a slug.
	 *
	 * @return array { ok, slug?, file?, php?, message? }
	 */
	public static function export_plugin_source( $id ) {
		$s = self::get( $id );
		if ( ! $s ) {
			return array( 'ok' => false, 'message' => 'Snippet not found.' );
		}
		$name = trim( $s['name'] ) !== '' ? $s['name'] : 'Velox snippet ' . (int) $id;
		$slug = sanitize_title( $name );
		if ( '' === $slug ) {
			$slug = 'velox-snippet-' . (int) $id;
		}
		$slug = 'velox-' . $slug;
		$desc = trim( (string) $s['description'] );
		if ( '' === $desc ) {
			$desc = 'Exported from a Velox code snippet.';
		}
		$type  = $s['type'];
		$scope = $s['scope'];
		$code  = (string) $s['code'];

		$header = "<?php\n"
			. "/**\n"
			. ' * Plugin Name: ' . self::comment_safe( $name ) . "\n"
			. ' * Description: ' . self::comment_safe( $desc ) . "\n"
			. " * Version: 1.0.0\n"
			. " * Requires at least: 5.5\n"
			. " * Requires PHP: 7.4\n"
			. ' * Author: Exported by Velox' . "\n"
			. " */\n\n"
			. "if ( ! defined( 'ABSPATH' ) ) { exit; }\n\n";

		$body = self::plugin_body( $type, $scope, $code, $slug );

		return array(
			'ok'   => true,
			'slug' => $slug,
			'file' => $slug . '.php',
			'php'  => $header . $body,
		);
	}

	/** Build the executable part of the exported plugin for each snippet type. */
	private static function plugin_body( $type, $scope, $code, $slug ) {
		$fn = 'velox_x_' . str_replace( '-', '_', $slug );

		if ( 'php' === $type ) {
			$inner = self::strip_php_open( $code );
			// front/admin scope → guard; everywhere/once → run on init.
			$guard = '';
			if ( 'admin' === $scope ) {
				$guard = "\tif ( ! is_admin() ) { return; }\n";
			} elseif ( 'front' === $scope ) {
				$guard = "\tif ( is_admin() ) { return; }\n";
			}
			return "function {$fn}() {\n" . $guard . $inner . "\n}\nadd_action( 'init', '{$fn}', 5 );\n";
		}

		if ( 'css' === $type ) {
			$hook  = ( 'admin' === $scope ) ? 'admin_head' : 'wp_head';
			$css   = self::heredoc( $code, 'VELOXCSS' );
			return "function {$fn}() {\n\techo \"\\n<style id=\\\"{$slug}\\\">\\n\" . {$css} . \"\\n</style>\\n\";\n}\nadd_action( '{$hook}', '{$fn}', 20 );\n";
		}

		if ( 'js' === $type ) {
			$hook  = ( 'admin' === $scope ) ? 'admin_footer' : 'wp_footer';
			$js    = self::heredoc( $code, 'VELOXJS' );
			return "function {$fn}() {\n\techo \"\\n<script id=\\\"{$slug}\\\">\\n\" . {$js} . \"\\n</script>\\n\";\n}\nadd_action( '{$hook}', '{$fn}', 20 );\n";
		}

		// html
		$hook = ( 'admin' === $scope ) ? 'admin_footer' : 'wp_footer';
		$html = self::heredoc( $code, 'VELOXHTML' );
		return "function {$fn}() {\n\techo \"\\n\" . {$html} . \"\\n\";\n}\nadd_action( '{$hook}', '{$fn}', 20 );\n";
	}

	/** Wrap arbitrary text in a Nowdoc so it's emitted verbatim, never executed. */
	private static function heredoc( $text, $tag ) {
		// Ensure the closing tag never appears inside the body.
		$t = $tag;
		while ( false !== strpos( $text, $t ) ) {
			$t = $tag . wp_rand( 10, 99 );
		}
		return "<<<'{$t}'\n" . $text . "\n{$t}";
	}

	private static function comment_safe( $s ) {
		// Keep plugin-header values on one line and out of the comment terminator.
		return trim( str_replace( array( "\n", "\r", '*/' ), array( ' ', ' ', '* /' ), (string) $s ) );
	}

	/** Stream the exported plugin as a .zip download (nonce-checked upstream). */
	public static function export_plugin_zip( $id ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'velox' ) );
		}
		$gen = self::export_plugin_source( $id );
		if ( empty( $gen['ok'] ) ) {
			wp_die( esc_html( $gen['message'] ) );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'PHP-zip is not available on this server, so the plugin zip cannot be built. Ask your host to enable the php-zip extension.', 'velox' ) );
		}

		$slug = $gen['slug'];
		$tmp  = wp_tempnam( $slug . '.zip' );
		$zip  = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			wp_die( esc_html__( 'Could not create the zip file.', 'velox' ) );
		}
		$zip->addFromString( $slug . '/' . $gen['file'], $gen['php'] );
		$zip->addFromString( $slug . '/readme.txt', self::export_readme( $slug ) );
		$zip->close();

		$data = file_get_contents( $tmp );
		wp_delete_file( $tmp );

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $slug . '.zip"' );
		header( 'Content-Length: ' . strlen( $data ) );
		echo $data; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	private static function export_readme( $slug ) {
		return "=== {$slug} ===\n\n"
			. "This plugin was exported from a Velox code snippet. It is fully standalone —\n"
			. "it does not require Velox to be installed.\n\n"
			. "Install: upload the zip under Plugins → Add New → Upload Plugin, then activate.\n";
	}

	/* ------------------------------------------------------------------ */
	/* Admin menu + page                                                  */
	/* ------------------------------------------------------------------ */

	public static function admin_menu() {
		// Snippets is reached from the Utilities tab, not as its own top-level
		// menu. Registering under a null parent keeps the page routable at
		// admin.php?page=velox-snippets (so all existing links/edit/new URLs keep
		// working) while removing the standalone item below Velox.
		add_submenu_page(
			null,
			'Snippets',
			'Snippets',
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/** Snippets no longer has its own top-level menu item, so no icon CSS needed. */
	public static function menu_icon_css() {}

	public static function assets( $hook ) {
		// As a hidden (null-parent) page the hook is 'admin_page_velox-snippets';
		// accept both that and the older toplevel form so assets always load.
		if ( 'admin_page_' . self::MENU_SLUG !== $hook && 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}
		// velox-admin.css/js + the VELOX nonce object are already enqueued by
		// Velox_Admin::assets() (its hook check matches "velox"). We only add the
		// bundled CodeMirror editor (all modes) + the snippets page script.
		wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php' ) );
		wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
		wp_enqueue_code_editor( array( 'type' => 'text/javascript' ) );
		wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
		wp_enqueue_script( 'velox-snippets', VELOX_ASSETS . 'js/velox-snippets.js', array( 'velox-admin', 'code-editor' ), VELOX_VERSION, true );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$id     = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		echo '<div class="velox-wrap velox-snippets-page"><div class="velox-main">';
		if ( 'edit' === $action || 'new' === $action ) {
			$snippet  = $id ? self::get( $id ) : null;
			$new_type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'php'; // phpcs:ignore WordPress.Security.NonceVerification
			include VELOX_PATH . 'admin/views/snippets-edit.php';
		} else {
			$filter   = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification
			$snippets = self::all( $filter );
			$counts   = self::counts();
			include VELOX_PATH . 'admin/views/snippets-list.php';
		}
		echo '<div class="velox-toast" id="velox-toast"></div>';
		echo '</div></div>';
	}

	public static function list_url( $filter = 'all' ) {
		return admin_url( 'admin.php?page=' . self::MENU_SLUG . ( 'all' === $filter ? '' : '&filter=' . $filter ) );
	}

	public static function edit_url( $id ) {
		return admin_url( 'admin.php?page=' . self::MENU_SLUG . '&action=edit&id=' . (int) $id );
	}

	public static function new_url( $type = 'php' ) {
		return admin_url( 'admin.php?page=' . self::MENU_SLUG . '&action=new&type=' . $type );
	}

	public static function export_url( $id ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=velox_snippet_export&id=' . (int) $id ),
			'velox_snippet_export_' . (int) $id
		);
	}
}
