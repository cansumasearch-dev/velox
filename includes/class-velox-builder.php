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

		// On our own admin section: strip every notice (WordPress core nags, other
		// plugins, and Velox's own) and mark the body so the panel goes full-bleed.
		add_action( 'in_admin_header', array( $this, 'silence_notices' ), 1000 );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );

		// "Edit with Velox" entry points on ordinary pages/posts.
		add_filter( 'page_row_actions', array( $this, 'row_action' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_action' ), 10, 2 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_link' ), 90 );
	}

	/** Are we on a Velox Builder admin screen? */
	private function is_builder_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$s = get_current_screen();
		return $s && false !== strpos( (string) $s->id, self::SLUG );
	}

	/** Remove all admin notices on our screens — nothing but Velox shows here. */
	public function silence_notices() {
		if ( ! $this->is_builder_screen() ) {
			return;
		}
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
	}

	/** Full-bleed body flag for our screens. */
	public function body_class( $classes ) {
		if ( $this->is_builder_screen() ) {
			$classes .= ' velox-builder-fullbleed';
		}
		return $classes;
	}

	/**
	 * Open (or create) the builder document for a given WordPress post, then
	 * return the editor URL. If the post already has a bound doc we reuse it;
	 * otherwise a fresh doc is created and linked so edits round-trip.
	 */
	public static function edit_url_for_post( $post_id ) {
		global $wpdb;
		$post_id = (int) $post_id;
		$t       = self::table();
		$doc_id  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE post_id = %d LIMIT 1", $post_id ) );
		if ( ! $doc_id ) {
			$doc_id = (int) get_post_meta( $post_id, '_velox_builder_doc', true );
		}
		return self::edit_url( $doc_id ) . ( $doc_id ? '' : '&post=' . $post_id );
	}

	/** Add an "Edit with Velox" row action in the Pages/Posts list tables. */
	public function row_action( $actions, $post ) {
		if ( current_user_can( 'edit_post', $post->ID ) && in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
			$url                    = self::edit_url_for_post( $post->ID );
			$actions['velox_edit']  = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Edit with Velox', 'velox' ) . '</a>';
		}
		return $actions;
	}

	/** Add "Edit with Velox" to the admin bar when viewing/editing a singular page. */
	public function admin_bar_link( $bar ) {
		$post_id = 0;
		if ( is_admin() ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && 'post' === $screen->base && isset( $_GET['post'] ) ) {
				$post_id = absint( $_GET['post'] );
			}
		} elseif ( is_singular() ) {
			$post_id = get_queried_object_id();
		}
		if ( $post_id && current_user_can( 'edit_post', $post_id ) ) {
			$bar->add_node(
				array(
					'id'    => 'velox-builder-edit',
					'title' => '⚡ ' . __( 'Edit with Velox', 'velox' ),
					'href'  => self::edit_url_for_post( $post_id ),
				)
			);
		}
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

		// Top-level "Velox Builder" section.
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
			'postId'  => isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0,
			'kind'    => isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : 'page',
			'reusables' => self::reusables_payload(),
			'backUrl' => admin_url( 'admin.php?page=' . self::SLUG ),
			'settingsUrl' => admin_url( 'admin.php?page=' . self::SLUG . '-settings' ),
			'reusablesUrl' => admin_url( 'admin.php?page=' . self::SLUG . '-reusables' ),
			'reviewConnections' => self::review_connections(),
			'reviewPresets' => self::review_presets(),
			'i18n'    => class_exists( 'Velox' ) ? Velox::js_dictionary() : array(),
		);

		// The editor lives in its own document, so the WordPress media library
		// (wp.media) has to be enqueued and printed by hand here.
		wp_enqueue_media();
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $title ); ?> — Velox Builder</title>
	<link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>">
	<script>window.VELOX_BUILDER = <?php echo wp_json_encode( $boot ); ?>;</script>
	<?php
	// Print the media library styles + core scripts (jQuery, wp.media) into the head.
	wp_print_styles( array( 'media-views', 'imgareaselect' ) );
	wp_print_scripts( array( 'jquery', 'media-editor', 'media-views' ) );
	?>
</head>
<body class="velox-builder-body">
	<div id="velox-builder-root">
		<div class="vb-boot">
			<div class="vb-boot-mark">V</div>
			<div class="vb-boot-text"><?php esc_html_e( 'Loading builder…', 'velox' ); ?></div>
		</div>
	</div>
	<?php
	// Media modal Backbone templates live in the admin footer normally; print them here.
	if ( function_exists( 'wp_print_media_templates' ) ) {
		wp_print_media_templates();
	}
	?>
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
	public static function edit_url( $doc_id = 0, $kind = '' ) {
		$url = admin_url( 'admin.php?page=' . self::EDIT_SLUG );
		if ( $doc_id ) {
			$url = add_query_arg( 'doc', (int) $doc_id, $url );
		}
		if ( $kind && 'page' !== $kind ) {
			$url = add_query_arg( 'kind', sanitize_key( $kind ), $url );
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
		$post_id  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$now      = current_time( 'mysql' );
		$t        = self::table();

		if ( $id ) {
			$fields  = array( 'title' => $title, 'kind' => $kind, 'data' => $data, 'css_size' => $css_size, 'updated' => $now );
			$formats = array( '%s', '%s', '%s', '%d', '%s' );
			if ( $post_id ) {
				$fields['post_id'] = $post_id;
				$formats[]         = '%d';
			}
			$wpdb->update( $t, $fields, array( 'id' => $id ), $formats, array( '%d' ) );
		} else {
			$wpdb->insert(
				$t,
				array( 'title' => $title, 'kind' => $kind, 'data' => $data, 'css_size' => $css_size, 'status' => 'draft', 'post_id' => $post_id ? $post_id : null, 'updated' => $now, 'created' => $now ),
				array( '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
			);
			$id = (int) $wpdb->insert_id;
			if ( $post_id ) {
				update_post_meta( $post_id, '_velox_builder_doc', $id );
			}
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
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, title, kind, status, post_id, data FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A );
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Document not found.', 'velox' ) ), 404 );
		}
		wp_send_json_success(
			array(
				'id'     => (int) $row['id'],
				'title'  => $row['title'],
				'kind'   => $row['kind'],
				'status' => $row['status'],
				'url'    => $row['post_id'] ? get_permalink( (int) $row['post_id'] ) : '',
				'model'  => json_decode( $row['data'], true ),
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

	/* =====================================================================
	   GLOBAL STYLES (design tokens) — stored as a single option, output as
	   CSS custom properties on every built page.
	   ===================================================================== */
	const OPT_TOKENS   = 'velox_builder_tokens';
	const OPT_FONTS    = 'velox_builder_fonts';
	const OPT_SETTINGS = 'velox_builder_settings';

	public static function tokens() {
		$t = get_option( self::OPT_TOKENS, null );
		if ( ! is_array( $t ) ) {
			$t = array(
				'colors'  => array(
					array( 'name' => 'primary', 'value' => '#2ab7f1' ),
					array( 'name' => 'ink', 'value' => '#12151a' ),
					array( 'name' => 'muted', 'value' => '#5b6673' ),
				),
				'spacing' => array( '4', '8', '16', '24', '48', '80' ),
			);
		}
		return $t;
	}

	public static function fonts() {
		$f = get_option( self::OPT_FONTS, null );
		return is_array( $f ) ? $f : array();
	}

	public static function settings() {
		$s = get_option( self::OPT_SETTINGS, null );
		$d = array( 'css_mode' => 'file', 'minify' => 1, 'container' => '1140' );
		return is_array( $s ) ? array_merge( $d, $s ) : $d;
	}

	public static function ajax_tokens_save() {
		$raw    = isset( $_POST['tokens'] ) ? wp_unslash( $_POST['tokens'] ) : '';
		$tokens = json_decode( $raw, true );
		if ( ! is_array( $tokens ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'velox' ) ), 400 );
		}
		// sanitise
		$clean = array( 'colors' => array(), 'spacing' => array() );
		foreach ( (array) ( $tokens['colors'] ?? array() ) as $c ) {
			$name = sanitize_key( $c['name'] ?? '' );
			$val  = sanitize_text_field( $c['value'] ?? '' );
			if ( $name && $val ) {
				$clean['colors'][] = array( 'name' => $name, 'value' => $val );
			}
		}
		foreach ( (array) ( $tokens['spacing'] ?? array() ) as $s ) {
			$s = preg_replace( '/[^0-9.]/', '', (string) $s );
			if ( '' !== $s ) {
				$clean['spacing'][] = $s;
			}
		}
		update_option( self::OPT_TOKENS, $clean );
		wp_send_json_success( array( 'tokens' => $clean ) );
	}

	public static function ajax_fonts_save() {
		$raw   = isset( $_POST['fonts'] ) ? wp_unslash( $_POST['fonts'] ) : '';
		$fonts = json_decode( $raw, true );
		if ( ! is_array( $fonts ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'velox' ) ), 400 );
		}
		$clean = array();
		foreach ( $fonts as $f ) {
			$name = sanitize_text_field( $f['name'] ?? '' );
			$src  = esc_url_raw( $f['url'] ?? '' );
			$type = in_array( ( $f['type'] ?? 'google' ), array( 'google', 'url' ), true ) ? $f['type'] : 'google';
			if ( $name ) {
				$clean[] = array( 'name' => $name, 'type' => $type, 'url' => $src );
			}
		}
		update_option( self::OPT_FONTS, $clean );
		wp_send_json_success( array( 'fonts' => $clean ) );
	}

	public static function ajax_settings_save() {
		$css_mode  = isset( $_POST['css_mode'] ) && 'inline' === $_POST['css_mode'] ? 'inline' : 'file';
		$minify    = isset( $_POST['minify'] ) && $_POST['minify'] ? 1 : 0;
		$container = isset( $_POST['container'] ) ? preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['container'] ) ) : '1140';
		update_option( self::OPT_SETTINGS, array( 'css_mode' => $css_mode, 'minify' => $minify, 'container' => $container ) );
		wp_send_json_success( array( 'saved' => true ) );
	}

	/* =====================================================================
	   CLASSES — aggregate every class used across all documents, with usage
	   counts; rename or delete across the whole site.
	   ===================================================================== */
	public static function all_classes() {
		global $wpdb;
		$rows  = $wpdb->get_results( 'SELECT id, title, data FROM ' . self::table(), ARRAY_A );
		$index = array(); // class => array( count, docs[] )
		foreach ( (array) $rows as $row ) {
			$model = json_decode( $row['data'], true );
			if ( ! is_array( $model ) || empty( $model['classes'] ) ) {
				continue;
			}
			foreach ( array_keys( $model['classes'] ) as $cls ) {
				if ( ! isset( $index[ $cls ] ) ) {
					$index[ $cls ] = array( 'count' => 0, 'props' => 0 );
				}
				$index[ $cls ]['count']++;
				// count declared props at base for a quick "size" hint
				$base = $model['classes'][ $cls ]['base'] ?? array();
				$index[ $cls ]['props'] += is_array( $base ) ? count( $base ) : 0;
			}
		}
		ksort( $index );
		return $index;
	}

	public static function ajax_class_rename() {
		global $wpdb;
		$from = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
		$to   = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
		$to   = '.' . sanitize_html_class( ltrim( $to, '.' ) );
		if ( ! $from || '.' === $to ) {
			wp_send_json_error( array( 'message' => __( 'Invalid class name.', 'velox' ) ), 400 );
		}
		$changed = self::rewrite_class( $from, $to );
		wp_send_json_success( array( 'from' => $from, 'to' => $to, 'docs' => $changed ) );
	}

	public static function ajax_class_delete() {
		$cls = isset( $_POST['class'] ) ? sanitize_text_field( wp_unslash( $_POST['class'] ) ) : '';
		if ( ! $cls ) {
			wp_send_json_error( array( 'message' => __( 'No class specified.', 'velox' ) ), 400 );
		}
		$changed = self::rewrite_class( $cls, null );
		wp_send_json_success( array( 'class' => $cls, 'docs' => $changed ) );
	}

	/** Rename ($to string) or delete ($to null) a class across every document. */
	private static function rewrite_class( $from, $to ) {
		global $wpdb;
		$t       = self::table();
		$rows    = $wpdb->get_results( "SELECT id, data FROM {$t}", ARRAY_A );
		$changed = 0;
		foreach ( (array) $rows as $row ) {
			$model = json_decode( $row['data'], true );
			if ( ! is_array( $model ) || empty( $model['classes'] ) || ! isset( $model['classes'][ $from ] ) ) {
				continue;
			}
			// rules map
			if ( null === $to ) {
				unset( $model['classes'][ $from ] );
			} else {
				$model['classes'][ $to ] = $model['classes'][ $from ];
				unset( $model['classes'][ $from ] );
			}
			// node class references
			self::walk_ref(
				$model['tree'],
				function ( &$node ) use ( $from, $to ) {
					if ( empty( $node['classes'] ) ) {
						return;
					}
					$out = array();
					foreach ( $node['classes'] as $c ) {
						if ( $c === $from ) {
							if ( null !== $to ) {
								$out[] = $to;
							}
						} else {
							$out[] = $c;
						}
					}
					$node['classes'] = $out;
				}
			);
			$wpdb->update( $t, array( 'data' => wp_json_encode( $model ), 'updated' => current_time( 'mysql' ) ), array( 'id' => $row['id'] ), array( '%s', '%s' ), array( '%d' ) );
			if ( class_exists( 'Velox_Builder_Render' ) ) {
				Velox_Builder_Render::write_css_for( (int) $row['id'] );
			}
			$changed++;
		}
		return $changed;
	}

	/** walk the tree by reference so mutations stick. */
	private static function walk_ref( &$nodes, $fn ) {
		foreach ( $nodes as &$node ) {
			$fn( $node );
			if ( ! empty( $node['children'] ) ) {
				self::walk_ref( $node['children'], $fn );
			}
		}
		unset( $node );
	}

	public static function ajax_doc_delete() {
		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'No document specified.', 'velox' ) ), 400 );
		}
		$wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
		wp_send_json_success( array( 'id' => $id ) );
	}

	/* =====================================================================
	   REUSABLES (by reference) + TEMPLATE ROLES (header/footer)
	   ===================================================================== */
	const OPT_ROLES = 'velox_builder_roles';

	/** Full decoded model for one document (tree + classes + content). */
	public static function doc_model( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, title, kind, data FROM ' . self::table() . ' WHERE id = %d', (int) $id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		$model = json_decode( $row['data'], true );
		if ( ! is_array( $model ) ) {
			return null;
		}
		$model['__id']    = (int) $row['id'];
		$model['__title'] = $row['title'];
		$model['__kind']  = $row['kind'];
		return $model;
	}

	/**
	 * Reusables packaged for the editor: id, title and their model, so the
	 * editor can render an inserted reusable inline (by reference) and keep it
	 * in sync everywhere it is used.
	 */
	public static function reusables_payload() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, title, data FROM ' . self::table() . ' WHERE kind = %s ORDER BY title ASC', 'reusable' ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$model = json_decode( $row['data'], true );
			if ( ! is_array( $model ) ) {
				continue;
			}
			$out[] = array(
				'id'      => (int) $row['id'],
				'title'   => $row['title'] ? $row['title'] : __( 'Untitled', 'velox' ),
				'tree'    => $model['tree'] ?? array(),
				'classes' => $model['classes'] ?? array(),
				'content' => $model['content'] ?? array(),
			);
		}
		return $out;
	}

	/** Which template is the active header / footer. */
	public static function roles() {
		$r = get_option( self::OPT_ROLES, array() );
		return is_array( $r ) ? array_merge( array( 'header' => 0, 'footer' => 0 ), $r ) : array( 'header' => 0, 'footer' => 0 );
	}

	/** Set (or clear) a template's role as the site header or footer. */
	public static function ajax_template_role() {		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$role = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
		if ( ! in_array( $role, array( 'header', 'footer', 'none' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid role.', 'velox' ) ), 400 );
		}
		$roles = self::roles();
		// A template can hold at most one role; clear it from any other slot first.
		foreach ( array( 'header', 'footer' ) as $slot ) {
			if ( (int) $roles[ $slot ] === $id ) {
				$roles[ $slot ] = 0;
			}
		}
		if ( 'none' !== $role ) {
			$roles[ $role ] = $id;
		}
		update_option( self::OPT_ROLES, $roles );
		wp_send_json_success( array( 'roles' => $roles ) );
	}

	/** Reviews connections + presets for the builder's Reviews element pickers. */
	public static function review_connections() {
		if ( ! class_exists( 'Velox_Reviews' ) ) {
			return array();
		}
		$store = Velox_Reviews::store();
		$out   = array();
		foreach ( (array) ( $store['connections'] ?? array() ) as $c ) {
			$out[] = array( 'id' => $c['id'], 'name' => $c['name'] );
		}
		return $out;
	}
	public static function review_presets() {
		if ( ! class_exists( 'Velox_Reviews' ) ) {
			return array();
		}
		$store = Velox_Reviews::store();
		$out   = array();
		foreach ( (array) ( $store['presets'] ?? array() ) as $p ) {
			$out[] = array( 'id' => $p['id'], 'name' => $p['name'] );
		}
		return $out;
	}

	/**
	 * Page-switcher data: every Velox document (page / template / reusable) plus
	 * WordPress pages and posts that aren't built with Velox yet (so you can jump
	 * in and start building one). Grouped and ready for the editor dropdown.
	 */
	public static function ajax_switcher_list() {
		global $wpdb;
		$out  = array( 'velox' => array(), 'wp' => array() );
		$docs = self::list_docs( null, 200 );
		$bound = array(); // post_ids already built, to exclude from the WP list
		foreach ( (array) $docs as $d ) {
			$out['velox'][] = array(
				'id'     => (int) $d['id'],
				'title'  => $d['title'] ? $d['title'] : __( 'Untitled', 'velox' ),
				'kind'   => $d['kind'],
				'status' => $d['status'],
				'url'    => self::edit_url( (int) $d['id'], $d['kind'] ),
			);
		}
		// map bound post ids
		$rows = $wpdb->get_col( 'SELECT post_id FROM ' . self::table() . ' WHERE post_id > 0' );
		foreach ( (array) $rows as $pid ) {
			$bound[ (int) $pid ] = true;
		}
		$posts = get_posts( array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 40,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		) );
		foreach ( (array) $posts as $p ) {
			if ( isset( $bound[ $p->ID ] ) ) {
				continue;
			}
			$out['wp'][] = array(
				'id'    => $p->ID,
				'title' => $p->post_title ? $p->post_title : __( '(no title)', 'velox' ),
				'type'  => $p->post_type,
				'url'   => add_query_arg( 'post', $p->ID, self::edit_url() ),
			);
		}
		wp_send_json_success( $out );
	}

	/**
	 * Publish a document: ensure it's bound to a WordPress page, flip it to
	 * published, write the CSS file, and return the live URL. Creating the page
	 * on first publish means the visitor-facing URL exists and the front-end
	 * renderer (which keys off post_id + status='published') can serve it.
	 */
	public static function ajax_publish() {
		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Save the page before publishing.', 'velox' ) ), 400 );
		}
		$t   = self::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, title, post_id FROM {$t} WHERE id = %d", $id ), ARRAY_A );
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Document not found.', 'velox' ) ), 404 );
		}

		$title   = $row['title'] ? $row['title'] : __( 'Velox Page', 'velox' );
		$post_id = (int) $row['post_id'];

		// Bind to a WP page — create one on first publish, else keep it in sync.
		if ( $post_id && get_post( $post_id ) ) {
			wp_update_post( array( 'ID' => $post_id, 'post_title' => $title, 'post_status' => 'publish' ) );
		} else {
			$post_id = wp_insert_post(
				array(
					'post_title'   => $title,
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_content' => '',
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				wp_send_json_error( array( 'message' => $post_id->get_error_message() ), 500 );
			}
			// Mark the page as Velox-built so it's identifiable later.
			update_post_meta( $post_id, '_velox_builder_doc', $id );
		}

		$wpdb->update(
			$t,
			array( 'post_id' => $post_id, 'status' => 'published', 'updated' => current_time( 'mysql' ) ),
			array( 'id' => $id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( class_exists( 'Velox_Builder_Render' ) ) {
			Velox_Builder_Render::write_css_for( $id );
		}

		wp_send_json_success(
			array(
				'id'      => $id,
				'post_id' => $post_id,
				'url'     => get_permalink( $post_id ),
				'status'  => 'published',
			)
		);
	}

	/** Revert a document to draft — visitors fall back to the theme. */
	public static function ajax_unpublish() {
		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'No document specified.', 'velox' ) ), 400 );
		}
		$t       = self::table();
		$post_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$t} WHERE id = %d", $id ) );
		if ( $post_id && get_post( $post_id ) ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
		}
		$wpdb->update( $t, array( 'status' => 'draft' ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
		wp_send_json_success( array( 'id' => $id, 'status' => 'draft' ) );
	}
}
