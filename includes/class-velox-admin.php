<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin shell: menu, admin-bar entry, asset loading, and the page router.
 * Each tab is registered as its own real submenu page with a clean slug
 * (velox, velox-images, …) so WordPress highlights the correct item and the
 * URL never relies on a fragile "&tab=" query string.
 */
class Velox_Admin {

	const SLUG = 'velox';

	private $tabs = array();

	public function __construct() {
		$this->tabs = array(
			'dashboard'   => array( 'label' => 'Dashboard',    'icon' => 'home' ),
			'images'      => array( 'label' => 'Images',       'icon' => 'image' ),
			'media'       => array( 'label' => 'Media Editor', 'icon' => 'tag' ),
			'performance' => array( 'label' => 'Performance',  'icon' => 'bolt' ),
			'database'    => array( 'label' => 'Database',     'icon' => 'db' ),
			'settings'    => array( 'label' => 'Settings',     'icon' => 'gear' ),
		);

		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 80 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . VELOX_BASENAME, array( $this, 'action_links' ) );
	}

	/** Map a tab key to its admin page slug. */
	public function page_slug( $key ) {
		return 'dashboard' === $key ? self::SLUG : self::SLUG . '-' . $key;
	}

	public function menu() {
		add_menu_page(
			'Velox',
			'Velox',
			'manage_options',
			self::SLUG,
			array( $this, 'render' ),
			$this->menu_icon(),
			80.7 // Sit just below Settings, down in the utility area.
		);

		foreach ( $this->tabs as $key => $tab ) {
			add_submenu_page(
				self::SLUG,
				'Velox — ' . $tab['label'],
				$tab['label'],
				'manage_options',
				$this->page_slug( $key ),
				array( $this, 'render' )
			);
		}
	}

	/** Top admin-bar entry — reachable from the front end and wp-admin alike. */
	public function admin_bar( $bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$icon = '<span style="display:inline-block;width:16px;height:16px;vertical-align:text-bottom;margin-right:6px;">'
			. '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M13 2 4.5 13.5H11l-1 8.5L19.5 10H13l0-8Z" fill="#2ab7f1"/></svg></span>';

		$bar->add_node( array(
			'id'    => 'velox',
			'title' => $icon . 'Velox',
			'href'  => menu_page_url( self::SLUG, false ),
		) );

		foreach ( $this->tabs as $key => $tab ) {
			$bar->add_node( array(
				'id'     => 'velox-' . $key,
				'parent' => 'velox',
				'title'  => $tab['label'],
				'href'   => $this->tab_url( $key ),
			) );
		}
	}

	private function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"><path d="M13 2 4.5 13.5H11l-1 8.5L19.5 10H13l0-8Z" fill="#a7aaad"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'velox-admin', VELOX_ASSETS . 'css/velox-admin.css', array(), VELOX_VERSION );
		wp_enqueue_script( 'velox-admin', VELOX_ASSETS . 'js/velox-admin.js', array(), VELOX_VERSION, true );
		wp_localize_script( 'velox-admin', 'VELOX', array(
			'ajaxurl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'velox_nonce' ),
			'webp_engine' => Velox_Image_Optimizer::engine(),
		) );
	}

	public function action_links( $links ) {
		$url = menu_page_url( self::SLUG, false );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . __( 'Open', 'velox' ) . '</a>' );
		return $links;
	}

	/** Which tab are we on? Derived from the page slug, with a legacy fallback. */
	public function current_tab() {
		// Legacy support first: ?page=velox&tab=settings (old bookmarks).
		if ( isset( $_GET['tab'] ) ) {
			$tab = sanitize_key( wp_unslash( $_GET['tab'] ) );
			if ( isset( $this->tabs[ $tab ] ) ) {
				return $tab;
			}
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : self::SLUG;

		foreach ( $this->tabs as $key => $tab ) {
			if ( $this->page_slug( $key ) === $page ) {
				return $key;
			}
		}

		// Encoded-slug fallback, e.g. "velox-settings" buried in the page var.
		if ( false !== strpos( $page, self::SLUG . '-' ) ) {
			$guess = str_replace( self::SLUG . '-', '', $page );
			if ( isset( $this->tabs[ $guess ] ) ) {
				return $guess;
			}
		}

		return 'dashboard';
	}

	public function tabs() {
		return $this->tabs;
	}

	public function tab_url( $key ) {
		return admin_url( 'admin.php?page=' . $this->page_slug( $key ) );
	}

	public function render() {
		$tab   = $this->current_tab();
		$admin = $this; // available inside views
		include VELOX_PATH . 'admin/views/header.php';

		$view = VELOX_PATH . 'admin/views/' . $tab . '.php';
		if ( file_exists( $view ) ) {
			include $view;
		}
		echo '</main></div>'; // .velox-main + .velox-wrap opened in header
	}

	/** Tiny inline icon set used by the nav + cards. */
	public static function icon( $name, $size = 20 ) {
		$paths = array(
			'home'  => '<path d="M3 11.5 12 4l9 7.5M5 10v9h5v-5h4v5h5v-9"/>',
			'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m4 17 5-5 4 4 3-3 4 4"/>',
			'tag'   => '<path d="M3 7v5l9 9 7-7-9-9H3Z"/><circle cx="7" cy="11" r="1.2"/>',
			'bolt'  => '<path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z"/>',
			'db'    => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
			'gear'  => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3M5 5l2 2m10 10 2 2M5 19l2-2M17 7l2-2"/>',
			'check' => '<path d="m5 12 4 4 10-10"/>',
		);
		$p = isset( $paths[ $name ] ) ? $paths[ $name ] : '';
		return '<svg class="velox-ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
	}
}
