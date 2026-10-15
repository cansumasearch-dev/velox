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

	const DB_VERSION  = '2';
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
		add_action( 'add_meta_boxes', array( $this, 'add_edit_metabox' ) );
		add_action( 'save_post', array( $this, 'save_template_choice' ) );

		// Two-way delete sync: deleting a WP post removes its Velox doc. Trashing
		// does NOT — a page restored from the trash used to come back with its
		// whole layout gone, because the trash hook deleted it permanently.
		add_action( 'before_delete_post', array( __CLASS__, 'on_post_deleted' ) );
	}

	/** When a WP page/post is deleted or trashed, drop its bound Velox document. */
	public static function on_post_deleted( $post_id ) {
		global $wpdb;
		$post_id = (int) $post_id;
		if ( ! $post_id ) {
			return;
		}
		// Only the page layout belongs to the post. A template or reusable that was
		// once a page must not vanish when its old page is emptied from the trash.
		$wpdb->delete( self::table(), array( 'post_id' => $post_id, 'kind' => 'page' ), array( '%d', '%s' ) );
		delete_post_meta( $post_id, '_velox_builder_doc' );
	}

	/** "Edit with Velox" meta box on the page/post editor (like Oxygen's). */
	public function add_edit_metabox() {
		// The builder needs manage_options; offering the button to editors who
		// then hit "not allowed" was a dead end.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		foreach ( array( 'page', 'post' ) as $type ) {
			add_meta_box(
				'velox-builder-edit',
				__( 'Velox Builder', 'velox' ),
				array( $this, 'render_edit_metabox' ),
				$type,
				'normal',
				'high'
			);
		}
	}

	public function render_edit_metabox( $post ) {
		$url   = self::edit_url_for_post( $post->ID );
		$built = (bool) self::doc_id_for_post( $post->ID );
		echo '<div style="text-align:center;padding:18px 0">';
		echo '<a href="' . esc_url( $url ) . '" class="button button-primary button-hero" style="background:linear-gradient(180deg,#2ea9e6,#1f88bd);border:none;text-shadow:none;box-shadow:none">';
		echo '<span style="vertical-align:middle">' . ( $built ? esc_html__( 'Edit with Velox', 'velox' ) : esc_html__( 'Build with Velox', 'velox' ) ) . '</span></a>';
		echo '<p style="color:#787c82;margin:12px 0 0">' . ( $built
			? esc_html__( 'This page has a Velox Builder layout. Opening the builder loads it.', 'velox' )
			: esc_html__( 'Design this page visually with Velox Builder. Your current content stays until you publish a Velox layout.', 'velox' ) ) . '</p>';
		// Say plainly whether visitors are getting the Velox layout or the theme.
		if ( class_exists( 'Velox_Builder_Render' ) ) {
			$st = Velox_Builder_Render::render_status( $post->ID );
			$bg = $st['live'] ? '#edfaf1' : '#fff8e5';
			$bd = $st['live'] ? '#7ad39b' : '#f0c36d';
			echo '<p style="margin:0 0 4px;padding:10px 12px;background:' . esc_attr( $bg ) . ';border-left:3px solid ' . esc_attr( $bd ) . ';border-radius:4px">';
			echo '<strong>' . esc_html( $st['live'] ? __( 'Live with Velox', 'velox' ) : __( 'Not live with Velox', 'velox' ) ) . '</strong><br>';
			echo esc_html( $st['reason'] );
			echo '</p>';
		}
		echo '</div>';

		// ---- Render page using template ----
		$templates = self::template_choices();
		$default   = self::default_template();
		$choice    = get_post_meta( $post->ID, '_velox_template', true );
		$choice    = ( '' === $choice || null === $choice ) ? '' : (int) $choice;
		wp_nonce_field( 'velox_template_' . $post->ID, 'velox_template_nonce' );

		echo '<div style="border-top:1px solid #dcdcde;padding:16px 0 4px">';
		echo '<p style="margin:0 0 8px"><label for="velox-template" style="font-weight:600">' . esc_html__( 'Render page using template', 'velox' ) . '</label></p>';

		if ( ! $templates ) {
			// Two ways to end up with a template, and the old copy mentioned
			// neither by link — nor that an existing page can simply be converted.
			$tpl_new  = self::edit_url( 0, 'template' );
			$tpl_list = admin_url( 'admin.php?page=' . self::SLUG . '-templates' );
			echo '<p style="color:#787c82;margin:0 0 10px">' . esc_html__( 'No templates exist yet. A template holds the navbar, footer and anything else shared between pages, with an Inner Content element marking where each page drops in. The first one you make becomes the default for every page automatically.', 'velox' ) . '</p>';
			echo '<p style="margin:0">';
			echo '<a class="button button-secondary" href="' . esc_url( $tpl_new ) . '">' . esc_html__( 'Create a template', 'velox' ) . '</a> ';
			echo '<a class="button button-secondary" href="' . esc_url( $tpl_list ) . '">' . esc_html__( 'All templates', 'velox' ) . '</a>';
			echo '</p>';
			echo '<p style="color:#787c82;margin:10px 0 0;font-size:12px">' . esc_html__( 'Already built the layout as a normal page? Open it in Velox Builder and switch the type dropdown beside the title from Page to Template — or change its type on the Velox Builder overview.', 'velox' ) . '</p>';
		} else {
			$default_label = '';
			foreach ( $templates as $t ) {
				if ( (int) $t['id'] === $default ) {
					$default_label = $t['title'];
				}
			}
			echo '<select name="velox_template" id="velox-template" style="min-width:280px">';
			echo '<option value=""' . selected( '', $choice, false ) . '>'
				. esc_html( $default_label
					? sprintf( __( 'Site default (%s)', 'velox' ), $default_label )
					: __( 'Site default (none set)', 'velox' ) )
				. '</option>';
			echo '<option value="-1"' . selected( -1, $choice, false ) . '>' . esc_html__( 'No template — this page only', 'velox' ) . '</option>';
			$live = self::live_templates();
			foreach ( $templates as $t ) {
				$draft = ! in_array( (int) $t['id'], $live, true );
				echo '<option value="' . esc_attr( $t['id'] ) . '"' . selected( (int) $t['id'], $choice, false ) . '>' . esc_html( $t['title'] . ( $draft ? ' ' . __( '(draft — not used until published)', 'velox' ) : '' ) ) . '</option>';
			}
			echo '</select>';
			// Say which template this page actually gets right now — "Site default"
			// is not the answer when a Pages or Front page template claims it.
			$in_use = self::template_for_post( $post->ID );
			$in_use_title = '';
			foreach ( $templates as $t ) {
				if ( (int) $t['id'] === $in_use ) {
					$in_use_title = $t['title'];
				}
			}
			echo '<p style="margin:8px 0 0"><strong>' . esc_html__( 'Used right now:', 'velox' ) . '</strong> ' . esc_html( $in_use_title ? $in_use_title : __( 'no template', 'velox' ) ) . '</p>';
			echo '<p style="color:#787c82;margin:8px 0 0">' . esc_html__( 'The template supplies the shared navbar, footer and anything else around the page. This page renders inside the template\'s Inner Content element.', 'velox' ) . '</p>';

			// Saying "this page uses template X" while the page has no Velox layout
			// is a contradiction unless catch-all is on — so say which it is.
			$has_layout = (bool) self::doc_id_for_post( $post->ID );
			if ( ! $has_layout ) {
				$tpl_id = self::template_for_post( $post->ID );
				$slot   = $tpl_id ? self::template_has_inner( $tpl_id ) : false;
				if ( ! self::wrap_legacy() ) {
					echo '<p style="margin:12px 0 0;padding:10px 12px;background:#f0f0f1;border-radius:4px">';
					echo '<strong>' . esc_html__( 'This template is not being applied yet.', 'velox' ) . '</strong><br>';
					echo esc_html__( 'Templates only wrap pages that Velox renders. This page has no Velox layout, so WordPress is still drawing it with your theme.', 'velox' ) . ' ';
					echo '<label style="display:block;margin-top:8px"><input type="checkbox" id="velox-wrap-legacy"> ';
					echo esc_html__( 'Also apply the default template to pages without a Velox layout (their content goes in the Inner Content slot)', 'velox' );
					echo '</label>';
					echo '<span id="velox-wrap-msg" style="color:#787c82"></span>';
					echo '</p>';
				} elseif ( ! $slot ) {
					echo '<p style="margin:12px 0 0;padding:10px 12px;background:#fff8e5;border-left:3px solid #f0c36d;border-radius:4px">';
					echo esc_html__( 'The template has no Inner Content element, so there is nowhere to put this page\'s content. Add one to the template and this page will render inside it.', 'velox' );
					echo '</p>';
				}
				?>
				<script>
				( function () {
					var cb = document.getElementById( 'velox-wrap-legacy' );
					if ( ! cb ) { return; }
					cb.addEventListener( 'change', function () {
						var msg = document.getElementById( 'velox-wrap-msg' );
						msg.textContent = '<?php echo esc_js( __( 'Saving…', 'velox' ) ); ?>';
						var body = new URLSearchParams();
						body.set( 'action', 'velox' ); body.set( 'do', 'builder_wrap_legacy_save' );
						body.set( 'nonce', '<?php echo esc_js( wp_create_nonce( 'velox_nonce' ) ); ?>' );
						body.set( 'on', cb.checked ? '1' : '' );
						fetch( ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
							.then( function ( r ) { return r.json(); } )
							.then( function () { location.reload(); } )
							.catch( function () { msg.textContent = '<?php echo esc_js( __( 'Save failed', 'velox' ) ); ?>'; } );
					} );
				}() );
				</script>
				<?php
			}
		}
		echo '</div>';
	}

	/** Persist the per-page template choice. */
	public function save_template_choice( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['velox_template_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['velox_template_nonce'] ) ), 'velox_template_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$val = isset( $_POST['velox_template'] ) ? sanitize_text_field( wp_unslash( $_POST['velox_template'] ) ) : '';
		// Empty string = "follow the site default", so the meta is removed rather
		// than pinned — that's what lets a later template apply retroactively.
		if ( '' === $val ) {
			delete_post_meta( $post_id, '_velox_template' );
			return;
		}
		update_post_meta( $post_id, '_velox_template', (int) $val );
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
	public static function doc_id_for_post( $post_id ) {
		global $wpdb;
		$post_id = (int) $post_id;
		$doc_id  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE post_id = %d LIMIT 1', $post_id ) );
		if ( ! $doc_id ) {
			$doc_id = (int) get_post_meta( $post_id, '_velox_builder_doc', true );
		}
		return $doc_id;
	}

	public static function edit_url_for_post( $post_id ) {
		$post_id = (int) $post_id;
		$doc_id  = self::doc_id_for_post( $post_id );
		return self::edit_url( $doc_id ) . ( $doc_id ? '' : '&post=' . $post_id );
	}

	/** Add an "Edit with Velox" row action in the Pages/Posts list tables. */
	public function row_action( $actions, $post ) {
		if ( current_user_can( 'manage_options' ) && in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
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
		if ( $post_id && current_user_can( 'manage_options' ) ) {
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
		// Schema 2 (the `draft` column) also changed what "draft" means for
		// templates and reusables. Detect an older table by its columns, not by
		// the version option — activation runs install() first and would set
		// that option before the migration below ever saw the old version.
		$had_table = ( $t === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) );
		$had_draft = $had_table && (bool) $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'draft'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		dbDelta(
			"CREATE TABLE {$t} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				kind VARCHAR(20) NOT NULL DEFAULT 'page',
				title VARCHAR(190) NOT NULL DEFAULT '',
				post_id BIGINT UNSIGNED NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'draft',
				data LONGTEXT NULL,
				draft LONGTEXT NULL,
				css_size INT UNSIGNED NOT NULL DEFAULT 0,
				priority INT NOT NULL DEFAULT 0,
				updated DATETIME NULL,
				created DATETIME NULL,
				PRIMARY KEY  (id),
				KEY kind (kind),
				KEY post_id (post_id)
			) {$charset};"
		);

		if ( $had_table && ! $had_draft ) {
			// Templates and reusables were live whatever their status said; mark
			// them published so nothing that is on the site today disappears.
			$wpdb->query( "UPDATE {$t} SET status = 'published' WHERE kind IN ('template','reusable')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		update_option( self::VER_OPTION, self::DB_VERSION );
	}

	/** Run install if the schema version changed (called on admin_init elsewhere). */
	public static function maybe_upgrade() {
		if ( get_option( self::VER_OPTION ) !== self::DB_VERSION ) {
			self::install(); // includes the schema-2 migration (see install()).
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
			'fonts'     => __( 'Fonts', 'velox' ),
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

		// The editor route is registered but hidden from the menu (empty parent;
		// null triggers deprecation notices in WordPress on PHP 8.1+).
		add_submenu_page(
			'',
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
		$post_id  = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		// Entering by page (…&post=7) when that page already has a Velox document —
		// an old bookmark, the back button, a second tab: open THAT document. Before,
		// the editor started empty and the next save created a second document for
		// the same page and pointed the page at it, orphaning the real design.
		if ( ! $doc_id && $post_id ) {
			$doc_id = self::doc_id_for_post( $post_id );
		}
		// If we entered by doc id, find the WP post this doc is bound to (if any),
		// so Exit destinations (frontend / backend / preview) still work.
		if ( ! $post_id && $doc_id ) {
			global $wpdb;
			$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . self::table() . ' WHERE id = %d', $doc_id ) );
		}
		// When entering from a WP page/post, inherit its title and make Exit return
		// to that post's editor (not the builder home).
		$seed_title = '';
		// Where "Go to backend" should land. Coming in from a WP page editor should
		// return there; a template belongs in the Templates list, a reusable in
		// Reusables, and anything opened from the Velox side goes to the overview.
		$kind_now = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : '';
		if ( ! $kind_now && $doc_id ) {
			global $wpdb;
			$kind_now = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT kind FROM ' . self::table() . ' WHERE id = %d', $doc_id ) );
		}
		$came_from_wp = isset( $_GET['post'] ) && absint( $_GET['post'] );
		if ( 'template' === $kind_now ) {
			$back_url = admin_url( 'admin.php?page=' . self::SLUG . '-templates' );
		} elseif ( 'reusable' === $kind_now ) {
			$back_url = admin_url( 'admin.php?page=' . self::SLUG . '-reusables' );
		} elseif ( $came_from_wp ) {
			$back_url = admin_url( 'edit.php?post_type=page' ); // refined to the post below
		} else {
			$back_url = admin_url( 'admin.php?page=' . self::SLUG );
		}
		$front_url  = home_url( '/' );                        // fallback: site home
		$preview_url = '';
		if ( $post_id ) {
			$p = get_post( $post_id );
			if ( $p ) {
				$seed_title  = $p->post_title;
				if ( $came_from_wp ) {
					$back_url = get_edit_post_link( $post_id, 'raw' );
				}
				$front_url   = get_permalink( $post_id );
				$preview_url = get_preview_post_link( $post_id );
			}
		}
		if ( ! $preview_url ) { $preview_url = $front_url; }
		// The browser tab said "Untitled" for every document.
		if ( '' !== $seed_title ) {
			$title = $seed_title;
		} elseif ( $doc_id ) {
			global $wpdb;
			$doc_title = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT title FROM ' . self::table() . ' WHERE id = %d', $doc_id ) );
			if ( '' !== $doc_title ) {
				$title = $doc_title;
			}
		}
		$boot = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'velox_nonce' ),
			'docId'   => $doc_id,
			'postId'  => $post_id,
			'seedTitle' => $seed_title,
			// The stored type wins over the URL. Opening a template without
			// &kind=template used to boot it as a page, and the next save turned
			// the template into a page for good.
			'kind'    => in_array( $kind_now, array( 'page', 'template', 'reusable' ), true ) ? $kind_now : 'page',
			// Colour tokens + spacing scale. Missing before, so the editor started
			// with none, and touching Global styles saved that empty set over every
			// colour the site had.
			'tokens'  => self::tokens(),
			// The site-wide version of each class, so a class added to this
			// document starts from what the rest of the site uses.
			'classRegistry' => (object) self::class_registry(),
			// Token variables and global styles, so the canvas looks like the page.
			'siteCss' => class_exists( 'Velox_Builder_Render' ) ? Velox_Builder_Render::canvas_css() : '',
			'fontLinks' => class_exists( 'Velox_Builder_Render' ) ? Velox_Builder_Render::canvas_fonts()['links'] : array(),
			// Oxygen import: the page being opened (if it has an Oxygen layout)
			// and whether the site has any Oxygen layouts at all.
			'oxygenPost' => ( $post_id && class_exists( 'Velox_Builder_Oxygen' ) && Velox_Builder_Oxygen::has_layout( $post_id ) ) ? $post_id : 0,
			'oxygenAny'  => class_exists( 'Velox_Builder_Oxygen' ) && Velox_Builder_Oxygen::sources() ? 1 : 0,
			'reusables' => self::reusables_payload(),
			'backUrl' => $back_url,
			'frontUrl' => $front_url,
			// Templates have no URL of their own, so "View page" sends you to the
			// site homepage — the nearest thing to seeing the template in the wild.
			'homeUrl'  => home_url( '/' ),
			'previewUrl' => $preview_url,
			'settingsUrl' => admin_url( 'admin.php?page=' . self::SLUG . '-settings' ),
			'stylesUrl'   => admin_url( 'admin.php?page=' . self::SLUG . '-styles' ),
			'globalStyles' => self::global_styles(),
			'aosTypes'    => self::aos_types(),
			'fontNames'   => wp_list_pluck( self::fonts(), 'name' ),
			'icons'       => class_exists( 'Velox_Icons' ) ? Velox_Icons::all() : array(),
			'reusablesUrl' => admin_url( 'admin.php?page=' . self::SLUG . '-reusables' ),
			'reviewConnections' => self::review_connections(),
			'reviewPresets' => self::review_presets(),
			'globalCss' => self::global_css_files(),
			'globalJs'  => self::global_js_files(),
			'breakpoints' => self::breakpoints(),
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
	// Print the media library styles + scripts into the head. These must be ALL
	// of the scripts wp_enqueue_media() asks for: without media-audiovideo,
	// wp.media.mixin is undefined, the media frame throws as soon as an image is
	// clicked, "Use image" never enables — and no image could ever be changed.
	wp_print_styles( array( 'media-views', 'imgareaselect', 'wp-mediaelement' ) );
	// The shell is dark and wp-admin's own CSS is not loaded here, so the media
	// window inherited white text on its white panels. Velox's resize/convert
	// fields need scripts that only load on wp-admin screens; hide them here.
	echo '<style id="velox-builder-media-fix">.media-modal,.media-modal-content{color:#1d2327;font-size:13px;line-height:1.4}'
		. '.media-modal .screen-reader-text,.media-modal .screen-reader-text span{border:0;clip:rect(1px,1px,1px,1px);clip-path:inset(50%);height:1px;margin:-1px;overflow:hidden;padding:0;position:absolute;width:1px;word-wrap:normal!important}'
		. '.media-modal .compat-field-velox_resize,.media-modal .compat-field-velox_convert{display:none}</style>' . "\n";
	wp_print_scripts( array( 'jquery', 'media-editor', 'media-views', 'media-audiovideo', 'mce-view', 'image-edit' ) );
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
		if ( ! in_array( $kind, array( 'page', 'template', 'reusable' ), true ) ) {
			$kind = 'page';
		}

		// The document model arrives as a JSON string. Keep it as text but verify
		// it decodes so we never store a broken blob. Decoded as OBJECTS: decoding
		// to PHP arrays turned every empty map ({}) into a list ([]) on the way
		// back, and the editor then wrote keys onto a JS array that the next save
		// silently dropped — an image picked, a style set, gone after saving.
		$raw   = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '';
		$model = json_decode( $raw );
		if ( ! is_object( $model ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid document data.', 'velox' ) ), 400 );
		}
		// Page JavaScript is printed as-is. Only someone allowed to post unfiltered
		// HTML may add it (on multisite that is the super admin, not every admin).
		if ( ! current_user_can( 'unfiltered_html' ) && isset( $model->page ) && is_object( $model->page ) ) {
			unset( $model->page->js );
		}
		// Classes edited in this document become the site-wide version when it is
		// published (see ajax_publish). The pending list is kept on the SERVER
		// (in the stored row) — the copy inside the posted model could be stale
		// and re-queue classes that were already published.
		unset( $model->changedClasses );
		$changed = isset( $_POST['changed_classes'] ) ? json_decode( wp_unslash( $_POST['changed_classes'] ), true ) : array();
		$pending = array();
		$pre_id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( $pre_id ) {
			$stored = $wpdb->get_row( $wpdb->prepare( 'SELECT status, data, draft FROM ' . self::table() . ' WHERE id = %d', $pre_id ), ARRAY_A );
			if ( $stored ) {
				$src = ( 'published' === $stored['status'] && null !== $stored['draft'] && '' !== $stored['draft'] ) ? $stored['draft'] : ( 'published' === $stored['status'] ? '' : $stored['data'] );
				$dec = $src ? json_decode( $src, true ) : null;
				if ( is_array( $dec ) && ! empty( $dec['changedClasses'] ) && is_array( $dec['changedClasses'] ) ) {
					$pending = array_values( array_map( 'strval', $dec['changedClasses'] ) );
				}
			}
		}
		foreach ( (array) $changed as $cname ) {
			$cname = (string) $cname;
			if ( '' !== $cname && '.' === $cname[0] && ! in_array( $cname, $pending, true ) ) {
				$pending[] = $cname;
			}
		}
		if ( $pending ) {
			$model->changedClasses = array_values( $pending );
		}
		$data     = wp_json_encode( $model );
		$css_size = isset( $_POST['css_size'] ) ? absint( $_POST['css_size'] ) : 0;
		// Only a page is bound to a WordPress post.
		$post_id  = ( 'page' === $kind && isset( $_POST['post_id'] ) ) ? absint( $_POST['post_id'] ) : 0;
		$now      = current_time( 'mysql' );
		$t        = self::table();

		// A page has ONE document: saving without an id for a page that already
		// has one updates it instead of creating a duplicate.
		if ( ! $id && $post_id ) {
			$id = self::doc_id_for_post( $post_id );
		}
		$row = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT id, status, kind FROM {$t} WHERE id = %d", $id ), ARRAY_A ) : null;
		// Switching the type in the editor: same rules as on the Overview.
		if ( $row && $row['kind'] !== $kind ) {
			self::change_kind( $id, $kind );
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, kind FROM {$t} WHERE id = %d", $id ), ARRAY_A );
		}

		// Save is not Publish. A published document keeps what visitors see in
		// `data` and collects edits in `draft`; Publish moves the draft live. A
		// document that is not live yet simply saves to `data`.
		$live = $row && 'published' === $row['status'];
		if ( $row ) {
			$fields  = array( 'title' => $title, 'kind' => $kind, 'css_size' => $css_size, 'updated' => $now );
			$formats = array( '%s', '%s', '%d', '%s' );
			if ( $live ) {
				$fields['draft'] = $data;
				$formats[]       = '%s';
			} else {
				$fields['data']  = $data;
				$fields['draft'] = null;
				$formats[]       = '%s';
				$formats[]       = '%s';
			}
			if ( $post_id ) {
				$fields['post_id'] = $post_id;
				$formats[]         = '%d';
			}
			$wpdb->update( $t, $fields, array( 'id' => $id ), $formats, array( '%d' ) );
			if ( 'template' === $kind ) {
				self::adopt_template( $id );
			}
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
			if ( 'template' === $kind ) {
				self::adopt_template( $id );
			}
		}

		$bound = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . $t . ' WHERE id = %d', $id ) );
		wp_send_json_success(
			array(
				'id'        => $id,
				'pending_classes' => $pending,
				'title'     => $title,
				'saved'     => $now,
				'kind'      => $kind,
				'post_id'   => $bound,
				'status'    => $live ? 'published' : 'draft',
				'has_draft' => $live ? 1 : 0,
				'url'       => $bound ? get_permalink( $bound ) : '',
				'preview'   => $bound ? get_preview_post_link( $bound ) : '',
			)
		);
	}

	/**
	 * A document that becomes a template: it gets an explicit "catch-all" rule
	 * (the Templates screen showed it as catch-all anyway), and the first
	 * template on the site becomes the default.
	 */
	private static function adopt_template( $id ) {
		$rules = self::template_rules();
		if ( ! isset( $rules[ (int) $id ] ) ) {
			self::set_template_purpose( $id, 'catch_all' );
		}
		if ( ! get_option( self::OPT_DEFAULT_TEMPLATE ) || ! self::default_template() ) {
			update_option( self::OPT_DEFAULT_TEMPLATE, (int) $id, false );
		}
	}

	/**
	 * Turn a document into another type. A page that becomes a template or
	 * reusable lets go of its WordPress page and starts as a draft — it used to
	 * stay bound to the page and go live everywhere at once.
	 */
	public static function change_kind( $id, $kind ) {
		global $wpdb;
		$t   = self::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, kind, post_id, data, draft FROM {$t} WHERE id = %d", $id ), ARRAY_A );
		if ( ! $row || $row['kind'] === $kind ) {
			return;
		}
		$fields = array( 'kind' => $kind, 'updated' => current_time( 'mysql' ) );
		if ( 'page' !== $kind ) {
			if ( (int) $row['post_id'] ) {
				delete_post_meta( (int) $row['post_id'], '_velox_builder_doc' );
				self::purge_cache_for( (int) $row['post_id'] );
			}
			$fields['post_id'] = null;
			$fields['status']  = 'draft';
			// The latest saved version becomes the (unpublished) content.
			if ( null !== $row['draft'] && '' !== $row['draft'] ) {
				$fields['data'] = $row['draft'];
			}
			$fields['draft'] = null;
		}
		$wpdb->update( $t, $fields, array( 'id' => (int) $id ) );
		if ( 'template' === $kind ) {
			self::adopt_template( $id );
		}
	}

	/** Throw away unpublished changes: the editor gets the live version back. */
	public static function ajax_discard_draft() {
		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'No document specified.', 'velox' ) ), 400 );
		}
		$wpdb->update( self::table(), array( 'draft' => null, 'updated' => current_time( 'mysql' ) ), array( 'id' => $id ), array( '%s', '%s' ), array( '%d' ) );
		wp_send_json_success( array( 'id' => $id ) );
	}

	/** Make a template the site default (Templates screen). */
	public static function ajax_template_default() {
		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$ok = $id ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . " WHERE id = %d AND kind = 'template'", $id ) ) : 0;
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Template not found.', 'velox' ) ), 404 );
		}
		self::set_default_template( $id );
		self::purge_cache_for();
		wp_send_json_success( array( 'id' => $id ) );
	}

	/* ------------------------------------------------------- global classes */

	const OPT_CLASSES = 'velox_builder_classes';

	/**
	 * The site-wide copy of every class someone has edited. Each document also
	 * keeps its own copy (so it stays self-contained), but on the page this copy
	 * wins — the editor says "a change rewrites this class everywhere", and
	 * before, the page's copy of a class silently beat the template's.
	 */
	public static function class_registry() {
		$r = get_option( self::OPT_CLASSES, array() );
		return is_array( $r ) ? $r : array();
	}

	/** Write classes into the registry (name => rules; null deletes). */
	public static function registry_put( $classes ) {
		$r = self::class_registry();
		foreach ( (array) $classes as $name => $rules ) {
			$name = (string) $name;
			if ( '' === $name || '.' !== $name[0] ) {
				continue;
			}
			if ( null === $rules ) {
				unset( $r[ $name ] );
				continue;
			}
			$clean = array();
			foreach ( (array) $rules as $key => $props ) {
				if ( ! preg_match( '/^[a-z]+(:[a-z][a-z0-9-]*(\([a-z0-9+\- ]*\))?)?$/i', (string) $key ) ) {
					continue;
				}
				$out = array();
				foreach ( (array) $props as $prop => $val ) {
					if ( is_scalar( $val ) && preg_match( '/^[a-zA-Z]+$/', (string) $prop ) ) {
						$out[ $prop ] = self::sanitize_css_value( (string) $val );
					}
				}
				$clean[ $key ] = $out;
			}
			$r[ $name ] = $clean;
		}
		update_option( self::OPT_CLASSES, $r, false );
	}

	/**
	 * Store a model (array form) as JSON with every map kept as an object, so an
	 * empty one is written {} and not [] (see ajax_save for why that matters).
	 */
	public static function encode_model( $m ) {
		$obj = function ( $v ) {
			return (object) ( is_array( $v ) ? $v : array() );
		};
		$cls = array();
		foreach ( (array) ( $m['classes'] ?? array() ) as $c => $by_key ) {
			$k = array();
			foreach ( (array) $by_key as $key => $props ) {
				$k[ $key ] = $obj( $props );
			}
			$cls[ $c ] = (object) $k;
		}
		$m['classes'] = (object) $cls;
		$m['content'] = $obj( $m['content'] ?? array() );
		if ( isset( $m['page'] ) ) {
			$m['page'] = $obj( $m['page'] );
		}
		$fix = function ( $nodes ) use ( &$fix, $obj ) {
			$out = array();
			foreach ( (array) $nodes as $n ) {
				if ( ! is_array( $n ) ) {
					continue;
				}
				$ov = array();
				foreach ( (array) ( $n['overrides'] ?? array() ) as $key => $props ) {
					$ov[ $key ] = $obj( $props );
				}
				$n['overrides'] = (object) $ov;
				if ( isset( $n['settings'] ) ) {
					$n['settings'] = $obj( $n['settings'] );
				}
				if ( isset( $n['aos'] ) ) {
					$n['aos'] = $obj( $n['aos'] );
				}
				$n['children'] = $fix( $n['children'] ?? array() );
				$out[] = $n;
			}
			return $out;
		};
		$m['tree'] = $fix( $m['tree'] ?? array() );
		unset( $m['__id'], $m['__title'], $m['__kind'], $m['__status'] );
		return wp_json_encode( $m );
	}

	/** Load a document's model for the editor (its draft, when there is one). */
	public static function ajax_load() {
		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'No document specified.', 'velox' ) ), 400 );
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, title, kind, status, post_id, data, draft FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A );
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Document not found.', 'velox' ) ), 404 );
		}
		$has_draft = null !== $row['draft'] && '' !== $row['draft'];
		// Objects, so empty maps stay {} (see ajax_save).
		$model = json_decode( $has_draft ? $row['draft'] : $row['data'] );
		if ( ! is_object( $model ) ) {
			$model = json_decode( (string) $row['data'] );
		}
		// The editor shows the site-wide version of each class, except the ones
		// this document changed and has not published yet.
		if ( is_object( $model ) && isset( $model->classes ) && is_object( $model->classes ) ) {
			$pending = isset( $model->changedClasses ) && is_array( $model->changedClasses ) ? $model->changedClasses : array();
			foreach ( self::class_registry() as $cname => $rules ) {
				if ( property_exists( $model->classes, $cname ) && ! in_array( $cname, $pending, true ) ) {
					$k = array();
					foreach ( (array) $rules as $key => $props ) {
						$k[ $key ] = (object) $props;
					}
					$model->classes->{$cname} = (object) $k;
				}
			}
		}
		wp_send_json_success(
			array(
				'id'        => (int) $row['id'],
				'title'     => $row['title'],
				'kind'      => $row['kind'],
				'status'    => $row['status'],
				'has_draft' => $has_draft ? 1 : 0,
				'pending_classes' => ( is_object( $model ) && isset( $model->changedClasses ) && is_array( $model->changedClasses ) ) ? array_values( $model->changedClasses ) : array(),
				'post_id'   => (int) $row['post_id'],
				'url'       => $row['post_id'] ? get_permalink( (int) $row['post_id'] ) : '',
				'preview'   => $row['post_id'] ? get_preview_post_link( (int) $row['post_id'] ) : '',
				'model'     => $model,
			)
		);
	}

	/** Delete doc rows bound to a WP post that no longer exists (self-heal for #11). */
	public static function purge_orphans() {
		global $wpdb;
		$t    = self::table();
		$rows = $wpdb->get_results( "SELECT id, post_id FROM {$t} WHERE post_id > 0", ARRAY_A );
		foreach ( (array) $rows as $r ) {
			if ( ! get_post( (int) $r['post_id'] ) ) {
				$wpdb->delete( $t, array( 'id' => (int) $r['id'] ), array( '%d' ) );
			}
		}
	}

	/** List documents for the admin section (Overview / lists). */
	public static function list_docs( $kind = null, $limit = 50 ) {
		global $wpdb;
		self::purge_orphans();
		$t = self::table();
		if ( $kind ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT id, title, kind, status, post_id, css_size, updated, ( draft IS NOT NULL AND draft <> '' ) AS has_draft FROM {$t} WHERE kind = %s ORDER BY updated DESC LIMIT %d", $kind, $limit ), ARRAY_A );
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT id, title, kind, status, post_id, css_size, updated, ( draft IS NOT NULL AND draft <> '' ) AS has_draft FROM {$t} ORDER BY updated DESC LIMIT %d", $limit ), ARRAY_A );
	}

	/* =====================================================================
	   GLOBAL STYLES (design tokens) — stored as a single option, output as
	   CSS custom properties on every built page.
	   ===================================================================== */
	const OPT_TOKENS   = 'velox_builder_tokens';
	const OPT_FONTS    = 'velox_builder_fonts';
	const OPT_SETTINGS = 'velox_builder_settings';
	const OPT_CSS      = 'velox_builder_global_css';

	/** Global CSS files: [ ['name'=>..,'css'=>..], ... ] applied on every page. */
	public static function global_css_files() {
		$f = get_option( self::OPT_CSS, null );
		return is_array( $f ) ? $f : array();
	}

	/** Concatenated global CSS for output. */
	public static function global_css() {
		$out = '';
		foreach ( self::global_css_files() as $f ) {
			if ( ! empty( $f['css'] ) ) {
				$out .= "\n/* " . ( isset( $f['name'] ) ? $f['name'] : 'global' ) . " */\n" . $f['css'];
			}
		}
		return $out;
	}

	public static function ajax_css_save() {
		$raw   = isset( $_POST['files'] ) ? wp_unslash( $_POST['files'] ) : '';
		$files = json_decode( $raw, true );
		if ( ! is_array( $files ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'velox' ) ), 400 );
		}
		$clean = array();
		foreach ( $files as $f ) {
			$name = sanitize_text_field( $f['name'] ?? '' );
			$css  = isset( $f['css'] ) ? (string) $f['css'] : '';
			// strip anything that could break out of a <style> block
			$css  = str_ireplace( array( '</style', '<script' ), '', $css );
			if ( '' !== $name || '' !== trim( $css ) ) {
				$clean[] = array( 'name' => $name ? $name : 'global.css', 'css' => $css );
			}
		}
		update_option( self::OPT_CSS, $clean );
		self::purge_cache_for();
		wp_send_json_success( array( 'files' => $clean ) );
	}

	const OPT_JS       = 'velox_builder_global_js';

	/**
	 * Global JS files: [ ['name'=>..,'js'=>..,'where'=>'head|footer','load'=>'normal|defer|async','on'=>1], ... ]
	 * Output on every Velox-rendered page. Kept deliberately separate from CSS
	 * because the loading controls only make sense for scripts.
	 */
	public static function global_js_files() {
		$f = get_option( self::OPT_JS, null );
		return is_array( $f ) ? $f : array();
	}

	/** Print the enabled scripts for one position ('head' or 'footer'). */
	public static function print_global_js( $where ) {
		foreach ( self::global_js_files() as $f ) {
			if ( empty( $f['on'] ) || empty( $f['js'] ) ) {
				continue;
			}
			if ( ( $f['where'] ?? 'footer' ) !== $where ) {
				continue;
			}
			// defer/async are attributes of EXTERNAL scripts; on an inline block the
			// browser ignores them, so an inline "defer" is emulated by deferring
			// execution to DOMContentLoaded instead of silently doing nothing.
			$load = $f['load'] ?? 'normal';
			$code = $f['js'];
			if ( 'defer' === $load ) {
				$code = "document.addEventListener('DOMContentLoaded',function(){\n" . $code . "\n});";
			} elseif ( 'async' === $load ) {
				$code = "setTimeout(function(){\n" . $code . "\n},0);";
			}
			echo "\n<script id=\"velox-global-js-" . esc_attr( sanitize_title( $f['name'] ?? 'js' ) ) . "\">\n" . $code . "\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	public static function ajax_js_save() {
		// Global scripts run on every page. manage_options alone is not enough on
		// multisite, where only super admins may post unfiltered HTML.
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to add scripts.', 'velox' ) ), 403 );
		}
		$raw   = isset( $_POST['files'] ) ? wp_unslash( $_POST['files'] ) : '';
		$files = json_decode( $raw, true );
		if ( ! is_array( $files ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'velox' ) ), 400 );
		}
		$allowed_where = array( 'head', 'footer' );
		$allowed_load  = array( 'normal', 'defer', 'async' );
		$clean = array();
		foreach ( $files as $f ) {
			$name = sanitize_text_field( $f['name'] ?? '' );
			$js   = isset( $f['js'] ) ? (string) $f['js'] : '';
			// Never let the block terminate its own <script> tag.
			$js    = str_ireplace( '</script', '<\/script', $js );
			$where = in_array( $f['where'] ?? '', $allowed_where, true ) ? $f['where'] : 'footer';
			$load  = in_array( $f['load'] ?? '', $allowed_load, true ) ? $f['load'] : 'normal';
			if ( '' !== $name || '' !== trim( $js ) ) {
				$clean[] = array(
					'name'  => $name ? $name : 'global.js',
					'js'    => $js,
					'where' => $where,
					'load'  => $load,
					'on'    => empty( $f['on'] ) ? 0 : 1,
				);
			}
		}
		update_option( self::OPT_JS, $clean );
		self::purge_cache_for();
		wp_send_json_success( array( 'files' => $clean ) );
	}

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
		if ( ! is_array( $f ) ) {
			return array();
		}
		// Fonts saved before weight/display control existed get sensible defaults
		// rather than an empty request that would load nothing.
		foreach ( $f as $i => $one ) {
			$f[ $i ]['weights'] = ( ! empty( $one['weights'] ) && is_array( $one['weights'] ) ) ? $one['weights'] : array( '400', '700' );
			$f[ $i ]['italic']  = empty( $one['italic'] ) ? 0 : 1;
			$f[ $i ]['files']   = ( ! empty( $one['files'] ) && is_array( $one['files'] ) ) ? $one['files'] : array();
			// preload used to be a single yes/no for the family; carry that over as
			// "preload the weights this family loads" rather than dropping it.
			if ( is_array( $one['preload'] ?? null ) ) {
				$f[ $i ]['preload'] = $one['preload'];
			} else {
				$f[ $i ]['preload'] = empty( $one['preload'] ) ? array() : $f[ $i ]['weights'];
			}
		}
		return $f;
	}

	public static function settings() {
		$s = get_option( self::OPT_SETTINGS, null );
		$d = array( 'css_mode' => 'file', 'minify' => 1 );
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
		self::purge_cache_for();
		wp_send_json_success( array( 'tokens' => $clean, 'canvas_css' => class_exists( 'Velox_Builder_Render' ) ? Velox_Builder_Render::canvas_css() : '' ) );
	}

	const OPT_FONT_DISPLAY = 'velox_builder_font_display';

	/** One font-display value for the whole site. */
	public static function font_display() {
		$d = get_option( self::OPT_FONT_DISPLAY, 'swap' );
		return in_array( $d, array( 'swap', 'optional', 'fallback', 'block', 'auto' ), true ) ? $d : 'swap';
	}

	/** The animations Velox can play, keyed by the value stored on an element. */
	public static function aos_types() {
		return array(
			'fade'       => __( 'Fade in', 'velox' ),
			'fade-up'    => __( 'Fade up', 'velox' ),
			'fade-down'  => __( 'Fade down', 'velox' ),
			'fade-left'  => __( 'Fade from left', 'velox' ),
			'fade-right' => __( 'Fade from right', 'velox' ),
			'zoom-in'    => __( 'Zoom in', 'velox' ),
			'zoom-out'   => __( 'Zoom out', 'velox' ),
		);
	}

	const OPT_GLOBAL_STYLES = 'velox_builder_global_styles';

	/** Every global style, with defaults filled in. */
	public static function global_styles() {
		$d = array(
			'body'     => array( 'font' => '', 'size' => '16', 'weight' => '400', 'lineHeight' => '1.6', 'color' => '#1f2329' ),
			'headings' => array( 'font' => '' ),
			'links'    => array( 'color' => '#0e7fb3', 'hover' => '#2ab7f1', 'decoration' => 'none', 'weight' => '' ),
			'width'    => array( 'page' => '1200', 'tablet' => '991', 'landscape' => '767', 'portrait' => '480' ),
			'sections' => array( 'top' => '80', 'right' => '24', 'bottom' => '80', 'left' => '24' ),
			'columns'  => array( 'top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20' ),
			'aos'      => array( 'type' => '', 'duration' => '600', 'easing' => 'ease', 'offset' => '120', 'delay' => '0', 'once' => '1', 'disable' => '' ),
		);
		foreach ( array( 'h1' => '48', 'h2' => '36', 'h3' => '28', 'h4' => '22', 'h5' => '18', 'h6' => '16' ) as $tag => $size ) {
			$d['headings'][ $tag ] = array( 'size' => $size, 'weight' => '700', 'lineHeight' => '1.25', 'color' => '' );
		}
		$saved = get_option( self::OPT_GLOBAL_STYLES, array() );
		if ( ! is_array( $saved ) ) {
			return $d;
		}
		// Merge one level deep so a new key added later still gets its default.
		foreach ( $saved as $group => $vals ) {
			if ( ! isset( $d[ $group ] ) || ! is_array( $vals ) ) {
				continue;
			}
			foreach ( $vals as $k => $v ) {
				if ( is_array( $v ) && isset( $d[ $group ][ $k ] ) && is_array( $d[ $group ][ $k ] ) ) {
					$d[ $group ][ $k ] = array_merge( $d[ $group ][ $k ], $v );
				} else {
					$d[ $group ][ $k ] = $v;
				}
			}
		}
		return $d;
	}

	/** Breakpoints, used by the editor, the renderer and the class CSS parser. */
	public static function breakpoints() {
		$w = self::global_styles()['width'];
		// "Leave empty to inherit" — an empty field collapsed the breakpoints to
		// 320px / 240px, which switched every responsive rule off.
		$w['tablet']    = ( '' === (string) ( $w['tablet'] ?? '' ) || (float) $w['tablet'] < 320 ) ? 991 : $w['tablet'];
		$w['landscape'] = ( '' === (string) ( $w['landscape'] ?? '' ) || (float) $w['landscape'] < 240 ) ? 767 : $w['landscape'];
		// Bootstrap 5 boundaries. lg/md stay tied to the editable global widths
		// (which already sit on Bootstrap's 992/768 lines) so an existing site
		// keeps the boundaries it was designed against; xxl, xl and sm are fixed
		// at Bootstrap's values. The .98 matches Bootstrap's own down-mixins.
		// sm is deliberately NOT derived from the 'portrait' width (480) — that
		// figure predates this and does not correspond to a Bootstrap breakpoint.
		return array(
			'xxl'    => 1399.98,
			'xl'     => 1199.98,
			'lg'     => max( 320, (float) $w['tablet'] ) + 0.98,
			'md'     => max( 240, (float) $w['landscape'] ) + 0.98,
			'sm'     => 575.98,
			// Back-compat for any caller still asking for the old names.
			'tablet' => max( 320, (int) $w['tablet'] ),
			'mobile' => max( 240, (int) $w['landscape'] ),
		);
	}

	public static function ajax_global_styles_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'velox' ) ), 403 );
		}
		$raw = isset( $_POST['styles'] ) ? wp_unslash( $_POST['styles'] ) : '';
		$in  = json_decode( $raw, true );
		if ( ! is_array( $in ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'velox' ) ), 400 );
		}
		$num = function ( $v ) { return preg_replace( '/[^0-9.]/', '', (string) $v ); };
		// hsl(), named colours and transparent were wiped on save.
		$col = function ( $v ) { $v = trim( (string) $v ); return preg_match( '/^(#[0-9a-f]{3,8}|(rgb|hsl)a?\([\d\s,.%\/a-z]+\)|var\(--[a-z0-9\-]+(\s*,\s*[#a-z0-9(),.%\s\/-]+)?\)|[a-z]{3,24}|)$/i', $v ) ? $v : ''; };
		$txt = function ( $v ) { return sanitize_text_field( (string) $v ); };

		$out = array(
			'body'     => array(
				'font' => $txt( $in['body']['font'] ?? '' ), 'size' => $num( $in['body']['size'] ?? '' ),
				'weight' => $num( $in['body']['weight'] ?? '' ), 'lineHeight' => $num( $in['body']['lineHeight'] ?? '' ),
				'color' => $col( $in['body']['color'] ?? '' ),
			),
			'headings' => array( 'font' => $txt( $in['headings']['font'] ?? '' ) ),
			'links'    => array(
				'color' => $col( $in['links']['color'] ?? '' ), 'hover' => $col( $in['links']['hover'] ?? '' ),
				'decoration' => in_array( ( $in['links']['decoration'] ?? 'none' ), array( 'none', 'underline' ), true ) ? $in['links']['decoration'] : 'none',
				'weight' => $num( $in['links']['weight'] ?? '' ),
			),
			'width'    => array(
				'page' => $num( $in['width']['page'] ?? '' ), 'tablet' => $num( $in['width']['tablet'] ?? '' ),
				'landscape' => $num( $in['width']['landscape'] ?? '' ), 'portrait' => $num( $in['width']['portrait'] ?? '' ),
			),
			'sections' => array(), 'columns' => array(),
			'aos'      => array(
				'type'     => in_array( ( $in['aos']['type'] ?? '' ), array_keys( self::aos_types() ), true ) ? $in['aos']['type'] : '',
				'duration' => $num( $in['aos']['duration'] ?? '' ),
				'easing'   => in_array( ( $in['aos']['easing'] ?? 'ease' ), array( 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'linear' ), true ) ? $in['aos']['easing'] : 'ease',
				'offset'   => $num( $in['aos']['offset'] ?? '' ),
				'delay'    => $num( $in['aos']['delay'] ?? '' ),
				'once'     => empty( $in['aos']['once'] ) ? '' : '1',
				'disable'  => in_array( ( $in['aos']['disable'] ?? '' ), array( '', 'mobile', 'tablet' ), true ) ? $in['aos']['disable'] : '',
			),
		);
		foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $tag ) {
			$out['headings'][ $tag ] = array(
				'size' => $num( $in['headings'][ $tag ]['size'] ?? '' ), 'weight' => $num( $in['headings'][ $tag ]['weight'] ?? '' ),
				'lineHeight' => $num( $in['headings'][ $tag ]['lineHeight'] ?? '' ), 'color' => $col( $in['headings'][ $tag ]['color'] ?? '' ),
			);
		}
		foreach ( array( 'sections', 'columns' ) as $g ) {
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				$out[ $g ][ $side ] = $num( $in[ $g ][ $side ] ?? '' );
			}
		}
		update_option( self::OPT_GLOBAL_STYLES, $out );
		self::purge_cache_for();
		wp_send_json_success( array( 'styles' => $out, 'canvas_css' => class_exists( 'Velox_Builder_Render' ) ? Velox_Builder_Render::canvas_css() : '' ) );
	}

	public static function ajax_fonts_save() {
		$raw   = isset( $_POST['fonts'] ) ? wp_unslash( $_POST['fonts'] ) : '';
		$fonts = json_decode( $raw, true );
		if ( ! is_array( $fonts ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'velox' ) ), 400 );
		}
		$ok_display = array( 'swap', 'optional', 'fallback', 'block', 'auto' );
		$ok_weights = array( '100', '200', '300', '400', '500', '600', '700', '800', '900' );
		// font-display is one decision for the whole site, not per family.
		$display = isset( $_POST['display'] ) ? sanitize_key( wp_unslash( $_POST['display'] ) ) : 'swap';
		update_option( self::OPT_FONT_DISPLAY, in_array( $display, $ok_display, true ) ? $display : 'swap' );
		$clean = array();
		foreach ( $fonts as $f ) {
			$name = sanitize_text_field( $f['name'] ?? '' );
			$src  = esc_url_raw( $f['url'] ?? '' );
			$type = in_array( ( $f['type'] ?? 'google' ), array( 'google', 'url', 'local' ), true ) ? $f['type'] : 'google';
			// Only the weights actually ticked get requested. Shipping all nine when
			// a site uses two is the single biggest font cost on most pages.
			$weights = array();
			foreach ( (array) ( $f['weights'] ?? array() ) as $w ) {
				$w = (string) (int) $w;
				if ( in_array( $w, $ok_weights, true ) && ! in_array( $w, $weights, true ) ) {
					$weights[] = $w;
				}
			}
			if ( ! $weights ) {
				$weights = array( '400', '700' );
			}
			sort( $weights, SORT_NUMERIC );
			// Preload is per WEIGHT: preloading a whole family pulls files the page
			// may never use, which is worse than not preloading at all.
			$preload = array();
			foreach ( (array) ( $f['preload'] ?? array() ) as $w ) {
				$w = (string) (int) $w;
				if ( in_array( $w, $weights, true ) && ! in_array( $w, $preload, true ) ) {
					$preload[] = $w;
				}
			}
			// Self-hosted files, one URL per weight.
			$files = array();
			foreach ( (array) ( $f['files'] ?? array() ) as $w => $url ) {
				$w   = (string) (int) $w;
				$url = esc_url_raw( $url );
				if ( in_array( $w, $ok_weights, true ) && $url ) {
					$files[ $w ] = $url;
				}
			}
			if ( $name ) {
				$clean[] = array(
					'name'    => $name,
					'type'    => $type,
					'url'     => $src,
					'weights' => $weights,
					'italic'  => empty( $f['italic'] ) ? 0 : 1,
					'preload' => $preload,
					'files'   => $files,
				);
			}
		}
		update_option( self::OPT_FONTS, $clean );
		// Fonts are in every page's head — cached pages must be rebuilt.
		self::purge_cache_for();
		wp_send_json_success( array( 'fonts' => $clean ) );
	}

	public static function ajax_settings_save() {
		$css_mode  = isset( $_POST['css_mode'] ) && 'inline' === $_POST['css_mode'] ? 'inline' : 'file';
		$minify    = isset( $_POST['minify'] ) && $_POST['minify'] ? 1 : 0;
		// 'container' used to be stored here and read by nothing. Page width now
		// lives in Global styles, so this writes there instead of shadowing it.
		$container = isset( $_POST['container'] ) ? preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['container'] ) ) : '';
		update_option( self::OPT_SETTINGS, array( 'css_mode' => $css_mode, 'minify' => $minify ) );
		self::purge_cache_for();
		if ( '' !== $container ) {
			$gs = self::global_styles();
			$gs['width']['page'] = $container;
			update_option( self::OPT_GLOBAL_STYLES, $gs );
		}
		self::purge_cache_for();
		wp_send_json_success( array( 'saved' => true ) );
	}

	/* =====================================================================
	   CLASSES — aggregate every class used across all documents, with usage
	   counts; rename or delete across the whole site.
	   ===================================================================== */
	public static function all_classes() {
		global $wpdb;
		// Saved-but-unpublished versions count too: a class that only exists in a
		// draft is still a class you can edit here.
		$rows  = $wpdb->get_results( "SELECT id, title, COALESCE( NULLIF( draft, '' ), data ) AS data FROM " . self::table(), ARRAY_A );
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


	/* ------------------------------------------------ overview + classes API */

	/**
	 * Every page/post on the site, Velox-built or not, in one list so the
	 * Overview can show the whole inventory instead of only Velox documents.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function page_inventory() {
		global $wpdb;
		$t    = self::table();
		$docs = $wpdb->get_results( "SELECT id, title, kind, status, post_id, updated, ( draft IS NOT NULL AND draft <> '' ) AS has_draft FROM {$t} ORDER BY updated DESC", ARRAY_A );
		$out  = array();
		$bound = array();

		foreach ( (array) $docs as $d ) {
			$pid = (int) $d['post_id'];
			if ( $pid ) {
				$bound[ $pid ] = true;
			}
			// Whether a visitor actually gets this layout, so the overview can say
			// so per row instead of the answer living only in the page editor.
			$live = array( 'live' => false, 'reason' => '' );
			if ( 'page' === $d['kind'] && class_exists( 'Velox_Builder_Render' ) && $pid ) {
				$live = Velox_Builder_Render::render_status( $pid );
			} elseif ( 'page' === $d['kind'] ) {
				$live = array( 'live' => false, 'reason' => __( 'Never published, so it has no URL yet. Open it and press Publish.', 'velox' ) );
			} elseif ( 'published' === $d['status'] ) {
				$live = array( 'live' => true, 'reason' => __( 'Building block — not served at a URL of its own.', 'velox' ) );
			} else {
				$live = array( 'live' => false, 'reason' => __( 'Not used on the site until it is published.', 'velox' ) );
			}
			$out[] = array(
				'type'    => $d['kind'],                 // page | template | reusable
				'source'  => 'velox',
				'live'    => $live['live'],
				'why'     => $live['reason'],
				'doc_id'  => (int) $d['id'],
				'post_id' => $pid,
				'title'   => $d['title'] ? $d['title'] : __( 'Untitled', 'velox' ),
				'status'  => $d['status'],
				'has_draft' => (int) $d['has_draft'],
				'updated' => $d['updated'],
				'edit'    => self::edit_url( (int) $d['id'], $d['kind'] ),
				'view'    => $pid ? get_permalink( $pid ) : '',
				'wp_edit' => $pid ? get_edit_post_link( $pid, 'raw' ) : '',
			);
		}

		// Everything WordPress knows about that Velox has never touched.
		$posts = get_posts( array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'numberposts'    => 300,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'suppress_filters' => false,
		) );
		foreach ( (array) $posts as $p ) {
			if ( isset( $bound[ $p->ID ] ) ) {
				continue;
			}
			$out[] = array(
				'type'    => 'legacy',
				'source'  => 'wp',
				'live'    => false,
				'why'     => __( 'No Velox layout — WordPress renders this with your theme.', 'velox' ),
				'doc_id'  => 0,
				'post_id' => $p->ID,
				'title'   => $p->post_title ? $p->post_title : __( '(no title)', 'velox' ),
				'status'  => $p->post_status,
				'updated' => $p->post_modified,
				'edit'    => self::edit_url_for_post( $p->ID ),
				'view'    => get_permalink( $p->ID ),
				'wp_edit' => get_edit_post_link( $p->ID, 'raw' ),
			);
		}
		return $out;
	}

	/** Counts for the Overview stat row (these used to be hardcoded zeros). */
	public static function stats() {
		global $wpdb;
		$t = self::table();
		return array(
			'pages'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE kind = 'page'" ),
			'templates' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE kind = 'template'" ),
			'reusables' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE kind = 'reusable'" ),
			'classes'   => count( self::all_classes() ),
		);
	}

	/**
	 * Change a document's type without opening the editor. This is the step that
	 * was previously only reachable from inside the builder, which made "I made a
	 * template but nothing sees it" very easy to hit.
	 */
	public static function ajax_doc_kind() {
		global $wpdb;
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		if ( ! $id || ! in_array( $kind, array( 'page', 'template', 'reusable' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid document type.', 'velox' ) ), 400 );
		}
		self::change_kind( $id, $kind );
		self::purge_cache_for();
		wp_send_json_success( array( 'id' => $id, 'kind' => $kind ) );
	}

	/** Rename a document (title only — never touches the bound WP post slug). */
	public static function ajax_doc_rename() {
		global $wpdb;
		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		if ( ! $id || '' === $title ) {
			wp_send_json_error( array( 'message' => __( 'A name is required.', 'velox' ) ), 400 );
		}
		$wpdb->update( self::table(), array( 'title' => $title, 'updated' => current_time( 'mysql' ) ), array( 'id' => $id ), array( '%s', '%s' ), array( '%d' ) );
		$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . self::table() . ' WHERE id = %d', $id ) );
		if ( $post_id && get_post( $post_id ) ) {
			wp_update_post( array( 'ID' => $post_id, 'post_title' => $title ) );
		}
		wp_send_json_success( array( 'id' => $id, 'title' => $title ) );
	}

	/** Copy a document. The copy is always an unbound draft. */
	public static function ajax_doc_duplicate() {
		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$row = $id ? $wpdb->get_row( $wpdb->prepare( 'SELECT title, kind, COALESCE( NULLIF( draft, \'\' ), data ) AS data, css_size FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A ) : null;
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Document not found.', 'velox' ) ), 404 );
		}
		$now = current_time( 'mysql' );
		$wpdb->insert( self::table(), array(
			'kind'     => $row['kind'],
			'title'    => sprintf( __( '%s (copy)', 'velox' ), $row['title'] ),
			'data'     => $row['data'],
			'css_size' => (int) $row['css_size'],
			'status'   => 'draft',
			'post_id'  => null,
			'updated'  => $now,
			'created'  => $now,
		), array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ) );
		$new = (int) $wpdb->insert_id;
		wp_send_json_success( array( 'id' => $new, 'url' => self::edit_url( $new, $row['kind'] ) ) );
	}

	/**
	 * Class names Velox itself creates when you insert an element, as opposed to
	 * ones you named. Mirrors the editor's CATALOG `cls` values.
	 *
	 * @return array<int,string>
	 */
	public static function starter_class_names() {
		return array(
			'.section', '.div', '.columns', '.grid', '.spacer', '.divider',
			'.heading', '.text', '.list', '.quote', '.button', '.textlink',
			'.image', '.video', '.icon', '.reviews', '.inner-content',
			'.wp-title', '.wp-content', '.wp-featured', '.wp-menu',
		);
	}

	/** The stored rules for one class, as editable CSS text (AJAX). */
	public static function ajax_class_css() {
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Missing class name.', 'velox' ) ), 400 );
		}
		wp_send_json_success( array( 'name' => $name, 'css' => self::class_css( $name ) ) );
	}

	/** The stored rules for one class, as editable CSS text. */
	public static function class_css( $name ) {
		global $wpdb;
		$reg = self::class_registry();
		if ( isset( $reg[ $name ] ) ) {
			return self::rules_to_css( $name, $reg[ $name ] );
		}
		$rows = $wpdb->get_results( 'SELECT data FROM ' . self::table(), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			$model = json_decode( $row['data'], true );
			if ( ! is_array( $model ) || empty( $model['classes'][ $name ] ) ) {
				continue;
			}
			return self::rules_to_css( $name, $model['classes'][ $name ] );
		}
		return '';
	}

	/** Model rules → readable CSS text (one block per breakpoint/state key). */
	private static function rules_to_css( $name, $rules ) {
		$out = '';
		foreach ( (array) $rules as $key => $props ) {
			if ( ! is_array( $props ) || ! $props ) {
				continue;
			}
			$parts = explode( ':', $key );
			$bp    = $parts[0];
			// Old documents keep tablet/mobile keys: show them as the breakpoints
			// they are now (lg/md), so they round-trip into the right place.
			if ( 'tablet' === $bp || 'mobile' === $bp ) {
				$bp = 'tablet' === $bp ? 'lg' : 'md';
			}
			$state = isset( $parts[1] ) ? ':' . $parts[1] : '';
			$sel   = $name . $state;
			$body  = '';
			foreach ( $props as $p => $v ) {
				$css = Velox_Builder_Render::css_prop_name( $p );
				if ( ! $css ) {
					continue;
				}
				$body .= "\t" . $css . ': ' . Velox_Builder_Render::css_value( $p, $v ) . ";\n";
			}
			if ( '' === $body ) {
				continue;
			}
			if ( 'base' !== $bp ) {
				// Every breakpoint gets its REAL media query. All of them used to be
				// written as the mobile one, and saving turned them into mobile rules.
				$mqs  = Velox_Builder_Render::media_queries();
				$mq   = $mqs[ $bp ] ?? '';
				if ( ! $mq ) {
					continue;
				}
				$out .= '@media ' . $mq . " {\n" . $sel . " {\n" . $body . "}\n}\n\n";
			} else {
				$out .= $sel . " {\n" . $body . "}\n\n";
			}
		}
		return rtrim( $out );
	}

	/** Parse edited CSS text back into the model shape, then store it everywhere. */
	public static function ajax_class_css_save() {
		global $wpdb;
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$css  = isset( $_POST['css'] ) ? wp_unslash( $_POST['css'] ) : '';
		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Missing class name.', 'velox' ) ), 400 );
		}
		$rules = self::css_to_rules( $name, (string) $css );
		$hit   = self::each_model(
			function ( &$model ) use ( $name, $rules ) {
				if ( ! isset( $model['classes'][ $name ] ) ) {
					return false;
				}
				$model['classes'][ $name ] = $rules;
				return true;
			}
		);
		self::registry_put( array( $name => $rules ) );
		self::purge_cache_for();
		wp_send_json_success( array( 'name' => $name, 'docs' => $hit ) );
	}

	/**
	 * Turn edited CSS text back into model rules. Deliberately small: it reads
	 * `selector { prop: value; }` blocks and optional @media wrappers, which is
	 * exactly what rules_to_css() emits. Anything it can't map is dropped rather
	 * than stored as junk that the renderer would ignore anyway.
	 */
	public static function css_to_rules( $name, $css ) {
		$rules = array();
		$css   = preg_replace( '#/\*.*?\*/#s', '', (string) $css );

		// Pull @media blocks out first, remembering which breakpoint they map to.
		$scoped = array();
		// One level of nesting: @media { ... { ... } ... }. A lazy `.*?\n\}` would
		// stop at the INNER rule's closing brace and hand back an unbalanced chunk.
		if ( preg_match_all( '/@media([^{]+)\{((?:[^{}]|\{[^{}]*\})*)\}/s', $css, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $mm ) {
				// Match on the site's ACTUAL breakpoints, not the old fixed numbers —
				// otherwise editing a class silently dropped its responsive rules.
				$bp = self::bp_for_media( $mm[1] );
				if ( $bp ) {
					$scoped[] = array( $bp, $mm[2] );
				}
				$css = str_replace( $mm[0], '', $css );
			}
		}
		$scoped[] = array( 'base', $css );

		foreach ( $scoped as $pair ) {
			list( $bp, $chunk ) = $pair;
			if ( ! preg_match_all( '/([^{}]+)\{([^{}]*)\}/s', $chunk, $blocks, PREG_SET_ORDER ) ) {
				continue;
			}
			foreach ( $blocks as $b ) {
				$sel = trim( $b[1] );
				// Only rules for THIS class are kept; the editor owns nothing else.
				if ( 0 !== strpos( $sel, $name ) ) {
					continue;
				}
				$state = trim( substr( $sel, strlen( $name ) ) );
				$key   = ( '' === $state ) ? $bp : $bp . ':' . ltrim( $state, ':' );
				$props = array();
				foreach ( explode( ';', $b[2] ) as $decl ) {
					if ( false === strpos( $decl, ':' ) ) {
						continue;
					}
					list( $p, $v ) = array_map( 'trim', explode( ':', $decl, 2 ) );
					$model_key = Velox_Builder_Render::model_prop_name( $p );
					if ( $model_key && '' !== $v ) {
						$props[ $model_key ] = self::sanitize_css_value( $v );
					}
				}
				if ( $props ) {
					// Two blocks for the same breakpoint/state (an old tablet/mobile
					// block next to its lg/md twin) are merged, not overwritten.
					$rules[ $key ] = array_merge( $rules[ $key ] ?? array(), $props );
				}
			}
		}
		return $rules;
	}

	/** Which breakpoint key a "(max-width: N px)" media query stands for. */
	private static function bp_for_media( $mq ) {
		if ( ! preg_match( '/max-width\s*:\s*([\d.]+)\s*px/i', (string) $mq, $m ) ) {
			return '';
		}
		$n = (float) $m[1];
		foreach ( self::breakpoints() as $key => $px ) {
			if ( in_array( $key, array( 'tablet', 'mobile' ), true ) ) {
				continue;
			}
			if ( abs( (float) $px - $n ) < 1 ) {
				return $key;
			}
		}
		return '';
	}

	/** Strip anything that could break out of a declaration. */
	private static function sanitize_css_value( $v ) {
		$v = str_replace( array( '}', '{', '<', '>' ), '', (string) $v );
		if ( preg_match( '/(expression\s*\(|javascript\s*:|@import|behaviou?r\s*:)/i', $v ) ) {
			return '';
		}
		return trim( $v );
	}

	public static function ajax_class_rename() {
		global $wpdb;
		$from = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
		$to   = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
		$to   = '.' . sanitize_html_class( ltrim( $to, '.' ) );
		if ( ! $from || '.' === $to ) {
			wp_send_json_error( array( 'message' => __( 'Invalid class name.', 'velox' ) ), 400 );
		}
		$from = '.' . ltrim( $from, '.' );
		if ( $from === $to ) {
			// Nothing to do — "renaming" a class to its own name deleted it.
			wp_send_json_success( array( 'from' => $from, 'to' => $to, 'docs' => 0 ) );
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

	/**
	 * Run $fn over every stored model — the live version and the draft — and
	 * write back the ones it changed ($fn returns true). Returns how many
	 * documents changed.
	 */
	private static function each_model( $fn ) {
		global $wpdb;
		$t       = self::table();
		$rows    = $wpdb->get_results( "SELECT id, data, draft FROM {$t}", ARRAY_A );
		$changed = 0;
		foreach ( (array) $rows as $row ) {
			$hit    = false;
			$fields = array();
			foreach ( array( 'data', 'draft' ) as $col ) {
				if ( null === $row[ $col ] || '' === $row[ $col ] ) {
					continue;
				}
				$model = json_decode( $row[ $col ], true );
				if ( ! is_array( $model ) ) {
					continue;
				}
				if ( $fn( $model ) ) {
					$fields[ $col ] = self::encode_model( $model );
					$hit            = true;
				}
			}
			if ( $hit ) {
				$fields['updated'] = current_time( 'mysql' );
				$wpdb->update( $t, $fields, array( 'id' => (int) $row['id'] ) );
				$changed++;
			}
		}
		return $changed;
	}

	/** Rename ($to string) or delete ($to null) a class across every document. */
	private static function rewrite_class( $from, $to ) {
		$changed = self::each_model(
			function ( &$model ) use ( $from, $to ) {
				if ( empty( $model['classes'] ) || ! is_array( $model['classes'] ) || ! isset( $model['classes'][ $from ] ) ) {
					return false;
				}
				// Renaming onto a class that already exists keeps that class's rules
				// rather than overwriting them.
				if ( null !== $to && ! isset( $model['classes'][ $to ] ) ) {
					$model['classes'][ $to ] = $model['classes'][ $from ];
				}
				unset( $model['classes'][ $from ] );
				if ( isset( $model['activeClass'] ) && $model['activeClass'] === $from ) {
					$model['activeClass'] = $to;
				}
				if ( ! empty( $model['tree'] ) && is_array( $model['tree'] ) ) {
					self::walk_ref(
						$model['tree'],
						function ( &$node ) use ( $from, $to ) {
							if ( empty( $node['classes'] ) ) {
								return;
							}
							$out = array();
							foreach ( $node['classes'] as $c ) {
								$c = ( $c === $from ) ? $to : $c;
								if ( null !== $c && ! in_array( $c, $out, true ) ) {
									$out[] = $c;
								}
							}
							$node['classes'] = $out;
						}
					);
				}
				return true;
			}
		);
		$reg = self::class_registry();
		if ( isset( $reg[ $from ] ) ) {
			$put = array( $from => null );
			if ( null !== $to && ! isset( $reg[ $to ] ) ) {
				$put[ $to ] = $reg[ $from ];
			}
			self::registry_put( $put );
		}
		self::purge_cache_for();
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
		// If this doc is bound to a WP page/post, trash that post too (recoverable).
		$post_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT post_id FROM ' . self::table() . " WHERE id = %d AND kind = 'page'", $id ) );
		$wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
		// Leave nothing behind: its template rule, the default pointer, its CSS file.
		$rules = self::template_rules();
		if ( isset( $rules[ $id ] ) ) {
			unset( $rules[ $id ] );
			update_option( self::OPT_TPL_RULES, $rules );
		}
		if ( (int) get_option( self::OPT_DEFAULT_TEMPLATE, 0 ) === $id ) {
			delete_option( self::OPT_DEFAULT_TEMPLATE );
		}
		if ( class_exists( 'Velox_Builder_Render' ) ) {
			Velox_Builder_Render::write_css_for( $id );
		}
		self::purge_cache_for();
		if ( $post_id ) {
			// Detach our hook first so trashing doesn't recurse back here.
			wp_trash_post( $post_id );
			delete_post_meta( $post_id, '_velox_builder_doc' );
		}
		wp_send_json_success( array( 'id' => $id, 'trashed_post' => $post_id ) );
	}

	/* =====================================================================
	   REUSABLES (by reference) + TEMPLATE ROLES (header/footer)
	   ===================================================================== */
	const OPT_ROLES = 'velox_builder_roles';
	const OPT_DEFAULT_TEMPLATE = 'velox_builder_default_template';

	/** The site-wide default template id (0 = none). */
	public static function default_template() {
		$id = (int) get_option( self::OPT_DEFAULT_TEMPLATE, 0 );
		if ( ! $id ) {
			return 0;
		}
		// Don't hand back an id whose template has since been deleted.
		global $wpdb;
		$ok = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . " WHERE id = %d AND kind = 'template'", $id ) );
		if ( ! $ok ) {
			delete_option( self::OPT_DEFAULT_TEMPLATE );
			return 0;
		}
		return $id;
	}

	public static function set_default_template( $id ) {
		$id = (int) $id;
		if ( $id > 0 ) {
			update_option( self::OPT_DEFAULT_TEMPLATE, $id, false );
		} else {
			delete_option( self::OPT_DEFAULT_TEMPLATE );
		}
	}

	/**
	 * Which template renders this post. Per-page choice wins; -1 means the page
	 * explicitly opts out; anything else falls back to the site default. A page
	 * that has never been touched therefore inherits a template created later.
	 *
	 * @return int Template doc id, or 0 for none.
	 */
	public static function template_for_post( $post_id ) {
		$live   = self::live_templates();
		$choice = get_post_meta( (int) $post_id, '_velox_template', true );
		if ( '' !== $choice && null !== $choice ) {
			$choice = (int) $choice;
			if ( -1 === $choice ) {
				return 0; // "No template" chosen deliberately.
			}
			if ( $choice > 0 && in_array( $choice, $live, true ) ) {
				return $choice;
			}
		}
		// A template made for exactly this kind of page (front page, posts, pages)
		// wins. Then the site default — it was losing to any catch-all rule, so
		// the page editor said "Site default (A)" while the page showed B. Then
		// any other catch-all template.
		$by_purpose = self::template_by_purpose( $post_id, false );
		if ( $by_purpose ) {
			return $by_purpose;
		}
		$default = self::default_template();
		if ( $default && in_array( $default, $live, true ) ) {
			return $default;
		}
		return self::template_for_context( array( 'catch_all' ) );
	}

	/** Ids of templates that are published (only those apply on the site). */
	public static function live_templates() {
		static $cache = null;
		if ( null === $cache ) {
			global $wpdb;
			$cache = array_map( 'intval', (array) $wpdb->get_col( 'SELECT id FROM ' . self::table() . " WHERE kind = 'template' AND status = 'published' ORDER BY id ASC" ) );
		}
		return $cache;
	}

	/** First published template whose purpose is in $purposes (in that order). */
	public static function template_for_context( $purposes ) {
		$rules = self::template_rules();
		$live  = self::live_templates();
		foreach ( (array) $purposes as $purpose ) {
			foreach ( $rules as $doc_id => $p ) {
				if ( $p === $purpose && in_array( (int) $doc_id, $live, true ) ) {
					return (int) $doc_id;
				}
			}
			// A template with no rule at all is shown as "Catch-all" on the
			// Templates screen, so it counts as one here too.
			if ( 'catch_all' === $purpose ) {
				foreach ( $live as $tid ) {
					if ( ! isset( $rules[ $tid ] ) ) {
						return $tid;
					}
				}
			}
		}
		return 0;
	}

	/** All templates, for the page-editor picker. */
	public static function template_choices() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT id, title FROM " . self::table() . " WHERE kind = 'template' ORDER BY title ASC", ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/** Full decoded model for one document (tree + classes + content). */
	public static function doc_model( $id, $prefer_draft = false ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, title, kind, status, data, draft FROM ' . self::table() . ' WHERE id = %d', (int) $id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		$use   = ( $prefer_draft && null !== $row['draft'] && '' !== $row['draft'] ) ? $row['draft'] : $row['data'];
		$model = json_decode( (string) $use, true );
		if ( ! is_array( $model ) ) {
			return null;
		}
		$model['__id']    = (int) $row['id'];
		$model['__title'] = $row['title'];
		$model['__kind']   = $row['kind'];
		$model['__status'] = $row['status'];
		return $model;
	}

	/**
	 * Reusables packaged for the editor: id, title and their model, so the
	 * editor can render an inserted reusable inline (by reference) and keep it
	 * in sync everywhere it is used.
	 */
	/**
	 * Where each reusable is used: [ reusable_id => [ ['id'=>doc, 'title'=>…], … ] ].
	 * Deleting a reusable that is live on six pages should not be a blind click.
	 */
	public static function reusable_usage() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT id, title, kind, data FROM ' . self::table(), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			// Reusables inside reusables count too (a reusable referencing itself
			// does not).
			$model = json_decode( $row['data'], true );
			if ( ! is_array( $model ) || empty( $model['tree'] ) ) {
				continue;
			}
			$found = array();
			$walk  = function ( $nodes ) use ( &$walk, &$found ) {
				foreach ( (array) $nodes as $n ) {
					if ( isset( $n['el'] ) && 'Reusable' === $n['el'] && ! empty( $n['ref'] ) ) {
						$found[ (int) $n['ref'] ] = true;
					}
					if ( ! empty( $n['children'] ) ) {
						$walk( $n['children'] );
					}
				}
			};
			$walk( $model['tree'] );
			foreach ( array_keys( $found ) as $ref ) {
				if ( (int) $ref === (int) $row['id'] ) {
					continue;
				}
				$out[ $ref ][] = array(
					'id'    => (int) $row['id'],
					'title' => $row['title'] ? $row['title'] : __( 'Untitled', 'velox' ),
					'kind'  => $row['kind'],
				);
			}
		}
		return $out;
	}

	public static function reusables_payload() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, title, status, data, draft FROM ' . self::table() . ' WHERE kind = %s ORDER BY title ASC', 'reusable' ), ARRAY_A );
		$out  = array();
		$reg  = self::class_registry();
		foreach ( (array) $rows as $row ) {
			// The editor shows the latest version, saved or published.
			$model = json_decode( ( null !== $row['draft'] && '' !== $row['draft'] ) ? $row['draft'] : $row['data'], true );
			if ( ! is_array( $model ) ) {
				continue;
			}
			foreach ( (array) ( $model['classes'] ?? array() ) as $cname => $rules ) {
				if ( isset( $reg[ $cname ] ) ) {
					$model['classes'][ $cname ] = $reg[ $cname ];
				}
			}
			$out[] = array(
				'id'      => (int) $row['id'],
				'title'   => $row['title'] ? $row['title'] : __( 'Untitled', 'velox' ),
				'status'  => $row['status'],
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

	/** Save a node (with its classes) as a reusable document. */
	public static function ajax_make_reusable() {
		global $wpdb;
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : __( 'Reusable', 'velox' );
		$node    = isset( $_POST['node'] ) ? json_decode( wp_unslash( $_POST['node'] ), true ) : null;
		$classes = isset( $_POST['classes'] ) ? json_decode( wp_unslash( $_POST['classes'] ), true ) : array();
		// Text and image content lives outside the tree, keyed by node id. Without
		// it a saved reusable comes back as correctly-styled but empty boxes.
		$content = isset( $_POST['content'] ) ? json_decode( wp_unslash( $_POST['content'] ), true ) : array();
		if ( ! is_array( $node ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid element.', 'velox' ) ), 400 );
		}
		$data = self::encode_model( array(
			'tree'    => array( $node ),
			'classes' => is_array( $classes ) ? $classes : array(),
			'content' => is_array( $content ) ? $content : array(),
		) );
		$wpdb->insert( self::table(), array(
			'kind'    => 'reusable',
			'title'   => $title,
			'status'  => 'published',
			'data'    => $data,
			'updated' => current_time( 'mysql' ),
			'created' => current_time( 'mysql' ),
		) );
		$id = (int) $wpdb->insert_id;
		wp_send_json_success( array( 'id' => $id, 'title' => $title ) );
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
	/**
	 * Everything a template can be previewed against: Velox pages (rendered from
	 * their model) plus plain WordPress pages and posts (rendered from their
	 * content). Kept separate from the switcher list because that one is about
	 * navigation and this one is about preview sources.
	 */
	const OPT_WRAP_LEGACY = 'velox_builder_wrap_legacy';

	/**
	 * Should the default template also wrap pages that have NO Velox layout of
	 * their own (the "catch-all" behaviour)? Off by default on purpose: turning
	 * it on makes Velox take over rendering for every page on the site, which is
	 * not something to do behind someone's back — especially with WooCommerce
	 * checkout/cart pages about.
	 */
	public static function wrap_legacy() {
		return (bool) get_option( self::OPT_WRAP_LEGACY, false );
	}

	/** Does this template actually have a slot to put the content in? */
	public static function template_has_inner( $tpl_id ) {
		$model = self::doc_model( (int) $tpl_id );
		if ( ! $model || empty( $model['tree'] ) ) {
			return false;
		}
		$found = false;
		$walk  = function ( $nodes ) use ( &$walk, &$found ) {
			foreach ( (array) $nodes as $n ) {
				if ( isset( $n['el'] ) && 'InnerContent' === $n['el'] ) {
					$found = true;
					return;
				}
				if ( ! empty( $n['children'] ) ) {
					$walk( $n['children'] );
				}
			}
		};
		$walk( $model['tree'] );
		return $found;
	}

	public static function ajax_wrap_legacy_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'velox' ) ), 403 );
		}
		$on = ! empty( $_POST['on'] ) && 'false' !== $_POST['on'];
		update_option( self::OPT_WRAP_LEGACY, $on ? 1 : 0 );
		self::purge_cache_for();
		wp_send_json_success( array( 'on' => $on ? 1 : 0 ) );
	}

	const OPT_TPL_RULES = 'velox_builder_template_rules';

	/**
	 * What each template is FOR, keyed by doc id: front_page, error404, search,
	 * archive, posts, pages, catch_all. Stored in one option rather than a new
	 * column so no schema migration is needed.
	 */
	public static function template_rules() {
		$r = get_option( self::OPT_TPL_RULES, array() );
		return is_array( $r ) ? $r : array();
	}

	public static function template_purpose( $doc_id ) {
		$r = self::template_rules();
		return isset( $r[ (int) $doc_id ] ) ? $r[ (int) $doc_id ] : 'catch_all';
	}

	public static function set_template_purpose( $doc_id, $purpose ) {
		$doc_id = (int) $doc_id;
		if ( ! $doc_id || ! isset( self::template_purposes()[ $purpose ] ) ) {
			return false;
		}
		$r            = self::template_rules();
		$r[ $doc_id ] = $purpose;
		update_option( self::OPT_TPL_RULES, $r );
		return true;
	}

	/** The purposes a template can serve, most specific first. */
	public static function template_purposes() {
		return array(
			'front_page' => __( 'Front page — the site homepage only', 'velox' ),
			'error404'   => __( '404 — the page-not-found screen', 'velox' ),
			'search'     => __( 'Search results', 'velox' ),
			'archive'    => __( 'Archives — category, tag, date listings', 'velox' ),
			'posts'      => __( 'Posts — every single blog post', 'velox' ),
			'pages'      => __( 'Pages — every WordPress page', 'velox' ),
			'catch_all'  => __( 'Catch-all — anything without a better match', 'velox' ),
		);
	}

	/**
	 * Which template should render this post, by purpose. More specific purposes
	 * win: a Front page template beats a Pages template, which beats catch-all.
	 * Returns 0 when nothing matches.
	 */
	public static function template_by_purpose( $post_id, $with_catch_all = true ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return 0;
		}
		$wanted = array();
		// "Front page" only means something when a static page is the front page.
		if ( 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === (int) $post_id ) {
			$wanted[] = 'front_page';
		}
		$wanted[] = ( 'post' === $post->post_type ) ? 'posts' : 'pages';
		if ( $with_catch_all ) {
			$wanted[] = 'catch_all';
		}
		return self::template_for_context( $wanted );
	}

	public static function ajax_template_purpose() {
		$id      = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$purpose = isset( $_POST['purpose'] ) ? sanitize_key( wp_unslash( $_POST['purpose'] ) ) : '';
		if ( ! self::set_template_purpose( $id, $purpose ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid template purpose.', 'velox' ) ), 400 );
		}
		self::purge_cache_for();
		wp_send_json_success( array( 'id' => $id, 'purpose' => $purpose ) );
	}

	/** Create an empty template with a name and purpose, then open it. */
	public static function ajax_template_create() {
		global $wpdb;
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$purpose = isset( $_POST['purpose'] ) ? sanitize_key( wp_unslash( $_POST['purpose'] ) ) : 'catch_all';
		if ( '' === $title ) {
			wp_send_json_error( array( 'message' => __( 'Give the template a name.', 'velox' ) ), 400 );
		}
		$now  = current_time( 'mysql' );
		// A template starts with the Inner Content slot already in place — without
		// it a template cannot show any page's content, which was the single most
		// confusing thing about building one from scratch.
		$data = self::encode_model( array(
			'tree'    => array( array( 'id' => 'innercontent-' . strtolower( wp_generate_password( 6, false, false ) ), 'el' => 'InnerContent', 'tag' => 'div', 'classes' => array( '.inner-content' ), 'overrides' => array(), 'children' => array() ) ),
			'classes' => array( '.inner-content' => array( 'base' => array( 'minHeight' => '200' ) ) ),
			'content' => array(),
		) );
		$wpdb->insert( self::table(), array(
			'kind' => 'template', 'title' => $title, 'data' => $data, 'css_size' => 0,
			'status' => 'draft', 'post_id' => null, 'updated' => $now, 'created' => $now,
		), array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ) );
		$id = (int) $wpdb->insert_id;
		self::set_template_purpose( $id, $purpose );
		if ( ! self::default_template() ) {
			update_option( self::OPT_DEFAULT_TEMPLATE, $id, false );
		}
		wp_send_json_success( array( 'id' => $id, 'url' => self::edit_url( $id, 'template' ) ) );
	}

	public static function ajax_viewas_list() {
		global $wpdb;
		$out   = array();
		$bound = array();
		$docs  = $wpdb->get_results( "SELECT id, title, post_id FROM " . self::table() . " WHERE kind = 'page' ORDER BY updated DESC", ARRAY_A );
		foreach ( (array) $docs as $d ) {
			if ( (int) $d['post_id'] ) {
				$bound[ (int) $d['post_id'] ] = true;
			}
			$out[] = array(
				'id'    => 'doc:' . (int) $d['id'],
				'title' => $d['title'] ? $d['title'] : __( 'Untitled', 'velox' ),
				'group' => __( 'Velox pages', 'velox' ),
			);
		}
		$posts = get_posts( array(
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		foreach ( (array) $posts as $p ) {
			if ( isset( $bound[ $p->ID ] ) ) {
				continue;
			}
			$out[] = array(
				'id'    => 'post:' . $p->ID,
				'title' => $p->post_title ? $p->post_title : __( '(no title)', 'velox' ),
				'group' => 'page' === $p->post_type ? __( 'WordPress pages', 'velox' ) : __( 'Posts', 'velox' ),
			);
		}
		wp_send_json_success( array( 'items' => $out ) );
	}

	/**
	 * The content to drop into a template's Inner Content for preview. A Velox
	 * page hands back its model so it renders exactly as it would live; a plain
	 * WordPress page has no model, so its post content is returned as HTML.
	 */
	public static function ajax_viewas_content() {
		$ref = isset( $_POST['ref'] ) ? sanitize_text_field( wp_unslash( $_POST['ref'] ) ) : '';
		if ( 0 === strpos( $ref, 'doc:' ) ) {
			$model = self::doc_model( (int) substr( $ref, 4 ) );
			if ( ! $model ) {
				wp_send_json_error( array( 'message' => __( 'That page could not be loaded.', 'velox' ) ), 404 );
			}
			wp_send_json_success( array( 'type' => 'model', 'model' => $model ) );
		}
		if ( 0 === strpos( $ref, 'post:' ) ) {
			$p = get_post( (int) substr( $ref, 5 ) );
			if ( ! $p ) {
				wp_send_json_error( array( 'message' => __( 'That page could not be loaded.', 'velox' ) ), 404 );
			}
			$html = apply_filters( 'the_content', $p->post_content );
			wp_send_json_success( array( 'type' => 'html', 'html' => wp_kses_post( $html ), 'title' => $p->post_title ) );
		}
		wp_send_json_error( array( 'message' => __( 'Nothing selected.', 'velox' ) ), 400 );
	}

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
	/**
	 * Drop cached HTML after a Velox change. Without this, logged-out visitors
	 * keep being served the pre-Velox page from cache — which reads exactly like
	 * "my changes aren't on the front end", because for them they aren't.
	 */
	public static function purge_cache_for( $post_id = 0 ) {
		if ( ! class_exists( 'Velox_Cache' ) ) {
			return;
		}
		// A template or a global change can affect any page, so clear the lot;
		// a single page only needs its own entry dropped.
		if ( $post_id ) {
			Velox_Cache::purge_post( (int) $post_id );
			return;
		}
		Velox_Cache::purge_all();
	}

	public static function ajax_publish() {
		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Save the page before publishing.', 'velox' ) ), 400 );
		}
		$t   = self::table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, title, post_id, kind, data, draft FROM {$t} WHERE id = %d", $id ), ARRAY_A );
		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Document not found.', 'velox' ) ), 404 );
		}

		// The saved draft becomes what visitors see, and the classes this document
		// changed become the site-wide version of those classes.
		$live  = ( null !== $row['draft'] && '' !== $row['draft'] ) ? $row['draft'] : $row['data'];
		$model = json_decode( (string) $live, true );
		$global_change = false;
		if ( is_array( $model ) && ! empty( $model['changedClasses'] ) ) {
			$put = array();
			foreach ( (array) $model['changedClasses'] as $cname ) {
				if ( isset( $model['classes'][ $cname ] ) ) {
					$put[ $cname ] = $model['classes'][ $cname ];
				}
			}
			if ( $put ) {
				self::registry_put( $put );
				$global_change = true;
			}
			unset( $model['changedClasses'] );
			$live = self::encode_model( $model );
		}
		$wpdb->update( $t, array( 'data' => $live, 'draft' => null ), array( 'id' => $id ) );
		if ( $global_change ) {
			self::purge_cache_for();
		}

		// Only pages live at a URL. Templates and reusables are building blocks —
		// publishing one must never create a WP page for it.
		if ( isset( $row['kind'] ) && 'page' !== $row['kind'] ) {
			$wpdb->update( $t, array( 'status' => 'published', 'updated' => current_time( 'mysql' ) ), array( 'id' => $id ), array( '%s', '%s' ), array( '%d' ) );
			if ( 'template' === $row['kind'] && ! self::default_template() ) {
				update_option( self::OPT_DEFAULT_TEMPLATE, $id, false );
			}
			self::purge_cache_for();
			wp_send_json_success( array( 'id' => $id, 'url' => '', 'kind' => $row['kind'] ) );
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
		self::purge_cache_for( $post_id );

		wp_send_json_success(
			array(
				'id'      => $id,
				'post_id' => $post_id,
				'url'     => get_permalink( $post_id ),
				'status'  => 'published',
			)
		);
	}

	/** Revert a document to draft and take its page offline. */
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
		// Without this the page stays in the cache and visitors keep seeing it.
		self::purge_cache_for( $post_id );
		wp_send_json_success( array( 'id' => $id, 'status' => 'draft' ) );
	}
}
