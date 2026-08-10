<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Velox Builder — the visual page builder module.
 *
 * This is the foundation layer: it registers the module (toggleable like the
 * other utilities), installs the documents table, adds the "Velox Builder"
 * admin section, and serves the standalone full-screen editor shell on its own
 * route (escaping the WordPress admin chrome the way Oxygen/Bricks do).
 *
 * The editor *engine* (store, live CSS injection, cascade resolver) and the
 * front-end render layer are layered on top of this in later passes; this class
 * is what makes the module exist inside WordPress and open.
 */
class Velox_Builder {

	const DB_VERSION  = '1';
	const VER_OPTION  = 'velox_builder_db';
	const ENABLE_KEY  = 'module_builder';           // stored in Velox_Settings
	const SLUG        = 'velox-builder';            // admin section slug
	const EDIT_SLUG   = 'velox-builder-edit';       // standalone editor route

	/** @var Velox_Builder */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		// The standalone editor takes over the whole screen before WP admin renders.
		add_action( 'current_screen', array( $this, 'maybe_launch_editor' ) );
	}

	/* --------------------------------------------------------------- enabled */

	/** The module is opt-in, mirroring how utilities are toggled. */
	public static function is_enabled() {
		if ( ! class_exists( 'Velox_Settings' ) ) {
			return false;
		}
		return (bool) Velox_Settings::get( self::ENABLE_KEY, false );
	}

	/* ----------------------------------------------------------------- table */

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'velox_builder_docs';
	}

	/**
	 * One row per built document (a page, template, or reusable). The tree +
	 * class rules live in `data` as JSON — the same shape the engine spike
	 * proved out. Relational columns are only what we query/list by.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$t       = self::table();

		dbDelta(
			"CREATE TABLE {$t} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				kind VARCHAR(20) NOT NULL DEFAULT 'page',
				title VARCHAR(190) NOT NULL DEFAULT '',
				post_id BIGINT UNSIGNED NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'draft',
				data LONGTEXT NULL,
				css_size INT UNSIGNED NOT NULL DEFAULT 0,
				priority INT NOT NULL DEFAULT 0,
				updated DATETIME NULL,
				created DATETIME NULL,
				PRIMARY KEY  (id),
				KEY kind (kind),
				KEY post_id (post_id)
			) {$charset};"
		);

		update_option( self::VER_OPTION, self::DB_VERSION );
	}

	/** Run install if the schema version changed (called on admin_init elsewhere). */
	public static function maybe_upgrade() {
		if ( get_option( self::VER_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/* ------------------------------------------------------------------ menu */

	public function menu() {
		if ( ! self::is_enabled() ) {
			return;
		}

		// Top-level "Velox Builder" section, sitting just under the Velox menu.
		add_menu_page(
			__( 'Velox Builder', 'velox' ),
			__( 'Velox Builder', 'velox' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_admin' ),
			$this->menu_icon(),
			100.8
		);

		$subs = array(
			'overview'  => __( 'Overview', 'velox' ),
			'templates' => __( 'Templates', 'velox' ),
			'reusables' => __( 'Reusables', 'velox' ),
			'classes'   => __( 'Classes', 'velox' ),
			'styles'    => __( 'Global styles', 'velox' ),
			'fonts'     => __( 'Fonts & icons', 'velox' ),
			'settings'  => __( 'Settings', 'velox' ),
		);
		foreach ( $subs as $key => $label ) {
			add_submenu_page(
				self::SLUG,
				'Velox Builder — ' . $label,
				$label,
				'manage_options',
				'overview' === $key ? self::SLUG : self::SLUG . '-' . $key,
				array( $this, 'render_admin' )
			);
		}

		// The editor route is registered but hidden from the menu (null parent).
		add_submenu_page(
			null,
			__( 'Velox Builder — Editor', 'velox' ),
			__( 'Editor', 'velox' ),
			'manage_options',
			self::EDIT_SLUG,
			array( $this, 'render_editor_fallback' )
		);
	}

	/** Inline SVG for the sidebar menu icon (base64 so WP colours it like a dashicon). */
	private function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#a7aaad" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/* --------------------------------------------------- standalone editor */

	/**
	 * When the editor route is hit, take over the whole screen: no WP admin bar,
	 * no menu, no footer — just our dark full-screen shell. We do this by
	 * rendering our own document and exiting before WordPress paints admin.php.
	 */
	public function maybe_launch_editor() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::EDIT_SLUG !== $page ) {
			return;
		}
		if ( ! self::is_enabled() ) {
			return;
		}

		$doc_id = isset( $_GET['doc'] ) ? absint( $_GET['doc'] ) : 0;
		$title  = __( 'Untitled', 'velox' );

		// Render the standalone shell and stop — WordPress never draws its chrome.
		$this->render_editor_shell( $doc_id, $title );
		exit;
	}

	/** The full-screen editor document. Assets are the builder's own bundle. */
	private function render_editor_shell( $doc_id, $title ) {
		$css_url = VELOX_ASSETS . 'css/velox-builder.css?v=' . VELOX_VERSION;
		$js_url  = VELOX_ASSETS . 'js/velox-builder.js?v=' . VELOX_VERSION;
		$boot    = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'velox_nonce' ),
			'docId'   => $doc_id,
			'backUrl' => admin_url( 'admin.php?page=' . self::SLUG ),
			'i18n'    => class_exists( 'Velox' ) ? Velox::js_dictionary() : array(),
		);
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $title ); ?> — Velox Builder</title>
	<link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>">
	<script>window.VELOX_BUILDER = <?php echo wp_json_encode( $boot ); ?>;</script>
</head>
<body class="velox-builder-body">
	<div id="velox-builder-root">
		<div class="vb-boot">
			<div class="vb-boot-mark">V</div>
			<div class="vb-boot-text"><?php esc_html_e( 'Loading builder…', 'velox' ); ?></div>
		</div>
	</div>
	<script src="<?php echo esc_url( $js_url ); ?>"></script>
</body>
</html>
		<?php
	}

	/** If someone lands on the editor route with the module off / no JS. */
	public function render_editor_fallback() {
		echo '<div class="wrap"><h1>' . esc_html__( 'Velox Builder', 'velox' ) . '</h1><p>' .
			esc_html__( 'The editor could not start. Make sure the module is enabled.', 'velox' ) . '</p></div>';
	}

	/* ------------------------------------------------------------ admin views */

	/** Router for the admin section pages (Overview, Templates, …). */
	public function render_admin() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : self::SLUG;
		$key  = self::SLUG === $page ? 'overview' : str_replace( self::SLUG . '-', '', $page );

		$view = VELOX_PATH . 'admin/views/builder/' . $key . '.php';
		if ( ! file_exists( $view ) ) {
			$view = VELOX_PATH . 'admin/views/builder/overview.php';
			$key  = 'overview';
		}

		// Shared full-screen dark shell for the admin section (matches the editor).
		echo '<div id="velox-builder-admin" data-page="' . esc_attr( $key ) . '">';
		include $view;
		echo '</div>';
	}

	/* --------------------------------------------------------------- assets */

	/** Enqueue the admin-section bundle only on our own pages. */
	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'velox-builder-admin', VELOX_ASSETS . 'css/velox-builder.css', array(), VELOX_VERSION );
		wp_enqueue_script( 'velox-builder-admin', VELOX_ASSETS . 'js/velox-builder-admin.js', array(), VELOX_VERSION, true );
		wp_localize_script(
			'velox-builder-admin',
			'VELOX_BUILDER',
			array(
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'velox_nonce' ),
				'editBase' => admin_url( 'admin.php?page=' . self::EDIT_SLUG ),
			)
		);
	}

	/** URL to open the editor for a given document. */
	public static function edit_url( $doc_id = 0 ) {
		$url = admin_url( 'admin.php?page=' . self::EDIT_SLUG );
		if ( $doc_id ) {
			$url = add_query_arg( 'doc', (int) $doc_id, $url );
		}
		return $url;
	}

	/* --------------------------------------------------------- persistence */

	/**
	 * Save a document. The editor posts the full store JSON (tree + classes +
	 * content). We validate it's decodable, compute the CSS size for the list
	 * views, and upsert the row. Creating a new doc returns its fresh id so the
	 * editor can switch from "new" to "editing #id".
	 */
	public static function ajax_save() {
		global $wpdb;
		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : __( 'Untitled', 'velox' );
		$kind  = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : 'page';

		// The document model arrives as a JSON string. Keep it as text but verify
		// it decodes so we never store a broken blob.
		$raw = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '';
		$model = json_decode( $raw, true );
		if ( null === $model || ! is_array( $model ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid document data.', 'velox' ) ), 400 );
		}
		$data     = wp_json_encode( $model );
		$css_size = isset( $_POST['css_size'] ) ? absint( $_POST['css_size'] ) : 0;
		$now      = current_time( 'mysql' );
		$t        = self::table();

		if ( $id ) {
			$wpdb->update(
				$t,
				array( 'title' => $title, 'kind' => $kind, 'data' => $data, 'css_size' => $css_size, 'updated' => $now ),
				array( 'id' => $id ),
				array( '%s', '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$t,
				array( 'title' => $title, 'kind' => $kind, 'data' => $data, 'css_size' => $css_size, 'status' => 'draft', 'updated' => $now, 'created' => $now ),
				array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
			);
			$id = (int) $wpdb->insert_id;
		}

		// Regenerate the page's static CSS file so the front end reflects the save.
		if ( class_exists( 'Velox_Builder_Render' ) ) {
			Velox_Builder_Render::write_css_for( $id );
		}

		wp_send_json_success( array( 'id' => $id, 'title' => $title, 'saved' => $now ) );
	}

	/** Load a document's model for the editor. */
	public static function ajax_load() {
		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'No document specified.', 'velox' ) ), 400 );
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, title, kind, data FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A );
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Document not found.', 'velox' ) ), 404 );
		}
		wp_send_json_success(
			array(
				'id'    => (int) $row['id'],
				'title' => $row['title'],
				'kind'  => $row['kind'],
				'model' => json_decode( $row['data'], true ),
			)
		);
	}

	/** List documents for the admin section (Overview / lists). */
	public static function list_docs( $kind = null, $limit = 50 ) {
		global $wpdb;
		$t = self::table();
		if ( $kind ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT id, title, kind, status, css_size, updated FROM {$t} WHERE kind = %s ORDER BY updated DESC LIMIT %d", $kind, $limit ), ARRAY_A );
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT id, title, kind, status, css_size, updated FROM {$t} ORDER BY updated DESC LIMIT %d", $limit ), ARRAY_A );
	}
}
