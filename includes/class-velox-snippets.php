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

	const DB_VERSION = '1';
	const DB_OPTION  = 'velox_snippets_db';
	const MENU_SLUG  = 'velox-snippets';

	const TYPES  = array( 'php', 'css', 'js', 'html' );
	const SCOPES = array( 'everywhere', 'admin', 'front', 'once' );

	/** Id of the PHP snippet currently being eval'd — read by the shutdown guard. */
	private static $executing = 0;

	/* ------------------------------------------------------------------ */
	/* Bootstrap                                                          */
	/* ------------------------------------------------------------------ */

	public static function init() {
		self::maybe_install();

		if ( ! Velox_Settings::get( 'util_snippets' ) ) {
			return;
		}

		// Catch fatals from a snippet and disable it on the next load.
		register_shutdown_function( array( __CLASS__, 'shutdown_guard' ) );

		// PHP snippets run now, early enough to hook into WordPress.
		self::run_php();

		// Output snippets (css/js/html) hook into head/footer.
		add_action( 'wp_head',      array( __CLASS__, 'output_css' ), 20 );
		add_action( 'admin_head',   array( __CLASS__, 'output_css' ), 20 );
		add_action( 'wp_footer',    array( __CLASS__, 'output_js_html' ), 20 );
		add_action( 'admin_footer', array( __CLASS__, 'output_js_html' ), 20 );
		add_shortcode( 'velox_snippet', array( __CLASS__, 'shortcode' ) );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
			add_action( 'admin_head', array( __CLASS__, 'menu_icon_css' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		}
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
			'priority'    => $priority,
			'active'      => $active,
			'modified'    => current_time( 'mysql' ),
		);
		$fmt = array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' );

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
		foreach ( self::all( 'active' ) as $snippet ) {
			if ( 'php' !== $snippet['type'] || ! self::scope_matches( $snippet['scope'] ) ) {
				continue;
			}
			self::execute_php( $snippet );
		}
	}

	private static function execute_php( $snippet ) {
		$code = self::strip_php_open( (string) $snippet['code'] );
		if ( '' === trim( $code ) ) {
			return;
		}
		self::$executing = (int) $snippet['id'];
		try {
			eval( $code ); // phpcs:ignore Squiz.PHP.Eval
		} catch ( \Throwable $e ) {
			self::set_active( $snippet['id'], false, 'Disabled after an error: ' . $e->getMessage() );
		}
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
		}
	}

	/* ------------------------------------------------------------------ */
	/* CSS / JS / HTML output                                             */
	/* ------------------------------------------------------------------ */

	public static function output_css() {
		foreach ( self::all( 'active' ) as $s ) {
			if ( 'css' !== $s['type'] || ! self::scope_matches( $s['scope'] ) || '' === trim( (string) $s['code'] ) ) {
				continue;
			}
			echo "\n<style id=\"velox-snippet-" . (int) $s['id'] . "\">\n" . $s['code'] . "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
			self::maybe_run_once( $s );
		}
	}

	public static function output_js_html() {
		foreach ( self::all( 'active' ) as $s ) {
			if ( ! self::scope_matches( $s['scope'] ) || '' === trim( (string) $s['code'] ) ) {
				continue;
			}
			if ( 'js' === $s['type'] ) {
				echo "\n<script id=\"velox-snippet-" . (int) $s['id'] . "\">\n" . $s['code'] . "\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
				self::maybe_run_once( $s );
			} elseif ( 'html' === $s['type'] ) {
				echo "\n<!-- velox-snippet " . (int) $s['id'] . " -->\n" . $s['code'] . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
				self::maybe_run_once( $s );
			}
		}
	}

	private static function maybe_run_once( $s ) {
		if ( 'once' === $s['scope'] ) {
			self::set_active( $s['id'], false, 'Ran once.' );
		}
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'velox_snippet' );
		$s    = self::get( (int) $atts['id'] );
		if ( ! $s || 'html' !== $s['type'] || ! $s['active'] ) {
			return '';
		}
		return $s['code'];
	}

	/* ------------------------------------------------------------------ */
	/* Admin menu + page                                                  */
	/* ------------------------------------------------------------------ */

	public static function admin_menu() {
		add_menu_page(
			'Snippets',
			'Snippets',
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render' ),
			VELOX_URL . 'assets/menu-icon.png',
			100.71 // directly below Velox (100.7)
		);
	}

	/** Match Velox's centred icon styling for the Snippets menu item. */
	public static function menu_icon_css() {
		echo '<style>#adminmenu #toplevel_page_' . esc_attr( self::MENU_SLUG ) . ' .wp-menu-image img{width:25px;height:25px;padding:5px 0 0;margin:0 auto;display:block;}</style>';
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
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

		echo '<div class="wrap velox-wrap velox-snippets-page">';
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
		echo '</div>';
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
}
