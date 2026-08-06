<?php
/**
 * Velox — Frontend admin bar (quick panel).
 *
 * A small arrow pinned to the bottom-left of the FRONT END, visible to admins
 * only (manage_options). Clicking it expands a panel of quick actions:
 *   - toggle the WP admin bar on/off (persisted per admin)
 *   - purge the Velox cache
 *   - toggle maintenance mode
 *   - edit this page
 *   - Oxygen editor / Oxygen settings (shown only when Oxygen is active)
 *   - WordPress settings
 *   - view this page as a logged-out visitor (opens a guest render in a new tab)
 *
 * It is its own utility (util_frontendbar) so it can be switched on/off per site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Frontend_Bar {

	public static function init() {
		// Guest-view render can run for anyone (it's how a visitor render is faked),
		// so wire it before the admin gate.
		add_action( 'template_redirect', array( __CLASS__, 'maybe_guest_view' ), 1 );

		if ( ! Velox_Settings::get( 'util_frontendbar', false ) ) {
			return;
		}

		// Per-admin admin-bar preference: honour it everywhere.
		add_action( 'after_setup_theme', array( __CLASS__, 'apply_admin_bar_pref' ) );

		// Only render the panel on the front end, for admins, and never inside the
		// guest-view render.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render' ), 100 );
	}

	/** True when we should show anything to this user on this request. */
	protected static function eligible() {
		return ! is_admin()
			&& is_user_logged_in()
			&& current_user_can( 'manage_options' )
			&& ! self::is_guest_view()
			&& ! self::is_builder_editing();
	}

	/**
	 * True when the current front-end request is actually a page builder's editor
	 * or its live preview/iframe — in which case the Velox frontend bar must not
	 * show. Covers Oxygen, Bricks, Elementor, Divi, Beaver Builder, Brizy, WPBakery,
	 * Cornerstone/Pro, Fusion (Avada), Thrive, Zion/Gant, Breakdance, and the generic
	 * customize preview. Detection is by the query params / constants each builder
	 * sets when it loads a page inside its editor, so new builders that follow the
	 * same "?builder=1 / preview" convention are largely covered too.
	 */
	protected static function is_builder_editing() {
		// Customizer preview.
		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return true;
		}
		// Constants some builders define while rendering their editor.
		$const_flags = array( 'ELEMENTOR_EDIT_MODE', 'ET_BUILDER_PLUGIN_ACTIVE_EDIT' );
		foreach ( $const_flags as $c ) {
			if ( defined( $c ) && constant( $c ) ) {
				return true;
			}
		}
		// Query-param signatures each builder sets on its editor / preview request.
		// Keyed by param name => value to match ('*' = any value present).
		$params = array(
			'ct_builder'        => '*',   // Oxygen
			'oxygen_iframe'     => '*',   // Oxygen iframe
			'bricks'            => 'run', // Bricks (?bricks=run)
			'brizy-edit'        => '*',   // Brizy
			'brizy-edit-iframe' => '*',
			'elementor-preview' => '*',   // Elementor
			'et_fb'             => '*',   // Divi front-end builder
			'et_pb_preview'     => '*',
			'fl_builder'        => '*',   // Beaver Builder
			'vcv-action'        => '*',   // Visual Composer (new)
			'vc_editable'       => '*',   // WPBakery
			'vc_action'         => '*',
			'cs_preview_state'  => '*',   // Cornerstone / X / Pro
			'fb-edit'           => '*',   // Fusion Builder (Avada)
			'builder'           => 'true',// Fusion / generic
			'tve'               => '*',   // Thrive
			'zionbuilder-preview' => '*', // Zion
			'breakdance'        => '*',   // Breakdance
			'breakdance_iframe' => '*',
		);
		foreach ( $params as $key => $want ) {
			if ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( '*' === $want || (string) $want === (string) $_GET[ $key ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					return true;
				}
			}
		}
		// Oxygen also sets this global while its builder renders.
		if ( defined( 'SHOW_CT_BUILDER' ) && SHOW_CT_BUILDER ) {
			return true;
		}
		return false;
	}

	/* ------------------------------------------------------- admin-bar pref */

	/**
	 * Apply the admin's saved "admin bar on/off" choice on the front end. Stored in
	 * user meta so it persists per admin across page loads.
	 */
	public static function apply_admin_bar_pref() {
		if ( is_admin() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$pref = get_user_meta( get_current_user_id(), 'velox_fb_adminbar', true );
		if ( '0' === (string) $pref ) {
			add_filter( 'show_admin_bar', '__return_false' );
		}
	}

	/* --------------------------------------------------------- guest view */

	/** Is the current request a Velox guest-view render? */
	protected static function is_guest_view() {
		return isset( $_GET['velox_guest'] ) && '' !== $_GET['velox_guest']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * When an admin opens "view as visitor", we reload the URL with a signed
	 * velox_guest token. On that request we log the user out FOR THAT RENDER ONLY
	 * (by clearing the current user) and suppress the admin bar, so the page looks
	 * exactly as a logged-out visitor sees it — without touching real cookies.
	 */
	public static function maybe_guest_view() {
		if ( ! self::is_guest_view() ) {
			return;
		}
		$token = sanitize_text_field( wp_unslash( $_GET['velox_guest'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		// The token is a nonce that only a logged-in admin could have generated.
		if ( ! wp_verify_nonce( $token, 'velox_guest_view' ) ) {
			return; // invalid/expired — just render normally (still logged in)
		}
		// Render this request as nobody: drop the current user and hide the bar.
		wp_set_current_user( 0 );
		add_filter( 'show_admin_bar', '__return_false' );
		add_filter( 'velox_is_guest_view', '__return_true' );
	}

	/* ------------------------------------------------------------ assets */

	public static function enqueue() {
		if ( ! self::eligible() ) {
			return;
		}
		$ver = defined( 'VELOX_VERSION' ) ? VELOX_VERSION : '1';
		wp_enqueue_style( 'velox-frontendbar', VELOX_URL . 'assets/frontendbar.css', array(), $ver );
		wp_enqueue_script( 'velox-frontendbar', VELOX_URL . 'assets/frontendbar.js', array(), $ver, true );
		wp_localize_script( 'velox-frontendbar', 'VELOX_FB', self::js_data() );
	}

	protected static function js_data() {
		$post_id   = self::current_post_id();
		$edit_link = $post_id ? get_edit_post_link( $post_id, 'raw' ) : '';

		return array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'velox_nonce' ),
			'adminBar'   => self::admin_bar_on() ? 1 : 0,
			'maint'      => Velox_Settings::get( 'util_maintenance', false ) ? 1 : 0,
			'maintOn'    => (bool) Velox_Settings::get( 'util_maintenance', false ),
			'editUrl'    => $edit_link ? $edit_link : '',
			'wpSettings' => admin_url( 'options-general.php' ),
			'oxygen'     => self::oxygen_links( $post_id ),
			'guestUrl'   => self::guest_url(),
			'i18n'       => self::i18n(),
		);
	}

	/* ------------------------------------------------------------- render */

	public static function render() {
		if ( ! self::eligible() ) {
			return;
		}
		// The panel body is built in JS from VELOX_FB; we only output the mount +
		// the arrow so there's no flash of unstyled controls.
		echo '<div id="velox-fb" class="velox-fb" aria-live="polite"></div>';
	}

	/* -------------------------------------------------------------- helpers */

	protected static function admin_bar_on() {
		$pref = get_user_meta( get_current_user_id(), 'velox_fb_adminbar', true );
		// Default: on (empty meta = never toggled = WP default of showing the bar).
		return '0' !== (string) $pref;
	}

	protected static function current_post_id() {
		$id = get_queried_object_id();
		return $id ? (int) $id : 0;
	}

	/** Oxygen editor + settings links, only when Oxygen is active. */
	protected static function oxygen_links( $post_id ) {
		$active = defined( 'CT_VERSION' ) || defined( 'OXYGEN_VERSION' ) || function_exists( 'ct_template_output' );
		if ( ! $active ) {
			return array( 'active' => false );
		}
		$editor = '';
		if ( $post_id ) {
			// Oxygen's edit URL for a specific post.
			$editor = add_query_arg(
				array( 'ct_builder' => 'true', 'ct_inner' => 'true', 'action' => 'ct_edit_in_builder' ),
				get_permalink( $post_id )
			);
		}
		return array(
			'active'   => true,
			'editor'   => $editor,
			'settings' => admin_url( 'admin.php?page=oxygen_vsb_settings' ),
		);
	}

	/** A signed URL that re-renders the current page as a logged-out visitor. */
	protected static function guest_url() {
		$base = home_url( add_query_arg( array() ) );
		return add_query_arg(
			array(
				'velox_guest' => wp_create_nonce( 'velox_guest_view' ),
				'nocache'     => time(),
			),
			$base
		);
	}

	protected static function i18n() {
		return array(
			'panel'       => __( 'Admin tools', 'velox' ),
			'open'        => __( 'Open admin tools', 'velox' ),
			'close'       => __( 'Close', 'velox' ),
			'adminBar'    => __( 'Admin bar', 'velox' ),
			'purge'       => __( 'Purge cache', 'velox' ),
			'purging'     => __( 'Purging…', 'velox' ),
			'purged'      => __( 'Cache purged', 'velox' ),
			'maint'       => __( 'Maintenance mode', 'velox' ),
			'edit'        => __( 'Edit this page', 'velox' ),
			'oxyEdit'     => __( 'Oxygen editor', 'velox' ),
			'oxySettings' => __( 'Oxygen settings', 'velox' ),
			'wpSettings'  => __( 'WordPress settings', 'velox' ),
			'guest'       => __( 'View as visitor', 'velox' ),
			'on'          => __( 'On', 'velox' ),
			'off'         => __( 'Off', 'velox' ),
			'failed'      => __( 'Failed', 'velox' ),
		);
	}

	/* ---------------------------------------------------------------- ajax */

	/** wp_ajax_velox 'do' handlers routed here from the dispatcher. */
	public static function ajax_toggle_admin_bar() {
		$on = ! empty( $_POST['on'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		update_user_meta( get_current_user_id(), 'velox_fb_adminbar', $on ? '1' : '0' );
		wp_send_json_success( array( 'on' => $on ) );
	}

	public static function ajax_toggle_maintenance() {
		$on = ! empty( $_POST['on'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		Velox_Settings::set( 'util_maintenance', $on );
		// When turning maintenance OFF from here, immediately release any pages the
		// maintenance SEO-hide had noindexed, so content isn't left stranded out of
		// the sitemap / stuck noindexed. (The full deliberate flow on the Maintenance
		// page still offers the keep/release choice; this quick toggle just never
		// leaves things hidden behind your back.)
		if ( ! $on && class_exists( 'Velox_Utilities' ) && method_exists( 'Velox_Utilities', 'maintenance_seo_release_all' ) ) {
			$released = Velox_Utilities::maintenance_seo_release_all();
			wp_send_json_success( array( 'on' => $on, 'released' => $released ) );
		}
		wp_send_json_success( array( 'on' => $on ) );
	}

	public static function ajax_purge() {
		if ( class_exists( 'Velox_Cache' ) ) {
			Velox_Cache::purge_all();
		}
		wp_send_json_success( array( 'ok' => true ) );
	}
}
