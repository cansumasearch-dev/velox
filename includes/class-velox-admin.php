<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin shell: menu, admin-bar entry, asset loading, cache actions, and the page
 * router. Each tab is a real submenu page with a clean slug. Tabs whose module is
 * switched off disappear from the menu, the in-page nav, and the admin bar.
 */
class Velox_Admin {

	const SLUG = 'velox';

	private $tabs = array();

	public function __construct() {
		$this->tabs = array(
			'dashboard'   => array( 'label' => 'Dashboard',    'icon' => 'home', 'module' => null ),
			'images'      => array( 'label' => 'Images',       'icon' => 'image', 'module' => 'module_images' ),
			'media'       => array( 'label' => 'Media Editor', 'icon' => 'tag', 'module' => 'module_media' ),
			'performance' => array( 'label' => 'Performance',  'icon' => 'bolt', 'module' => 'module_performance' ),
			'database'    => array( 'label' => 'Database',     'icon' => 'db', 'module' => 'module_database' ),
			'settings'    => array( 'label' => 'Settings',     'icon' => 'gear', 'module' => null ),
		);

		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 80 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_velox_cache', array( $this, 'handle_cache_action' ) );
		add_action( 'admin_notices', array( $this, 'cache_notice' ) );
		add_filter( 'plugin_action_links_' . VELOX_BASENAME, array( $this, 'action_links' ) );
	}

	/** Tabs whose module is enabled (dashboard + settings are always on). */
	public function enabled_tabs() {
		$out = array();
		foreach ( $this->tabs as $key => $tab ) {
			if ( null === $tab['module'] || Velox_Settings::get( $tab['module'] ) ) {
				$out[ $key ] = $tab;
			}
		}
		return $out;
	}

	public function page_slug( $key ) {
		return 'dashboard' === $key ? self::SLUG : self::SLUG . '-' . $key;
	}

	public function menu() {
		add_menu_page( 'Velox', 'Velox', 'manage_options', self::SLUG, array( $this, 'render' ), $this->menu_icon(), 80.7 );

		foreach ( $this->enabled_tabs() as $key => $tab ) {
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

	/** Top admin-bar entry with a nested Performance + cache submenu. */
	public function admin_bar( $bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$icon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" '
			. 'style="vertical-align:middle;margin:-2px 6px 0 0;">'
			. '<path d="M13 2 4.5 13.5H11l-1 8.5L19.5 10H13l0-8Z" fill="#fff"/></svg>';

		$bar->add_node( array(
			'id'    => 'velox',
			'title' => $icon . 'Velox',
			'href'  => menu_page_url( self::SLUG, false ),
		) );

		foreach ( $this->enabled_tabs() as $key => $tab ) {
			$bar->add_node( array(
				'id'     => 'velox-' . $key,
				'parent' => 'velox',
				'title'  => $tab['label'],
				'href'   => $this->tab_url( $key ),
			) );
		}

		// Nested "Performance & Cache" group — only when the Performance module is on.
		if ( ! Velox_Settings::get( 'module_performance' ) ) {
			return;
		}
		$bar->add_node( array(
			'id'     => 'velox-cache',
			'parent' => 'velox',
			'title'  => 'Performance & Cache',
		) );
		$bar->add_node( array(
			'id'     => 'velox-cache-settings',
			'parent' => 'velox-cache',
			'title'  => 'Performance settings',
			'href'   => $this->tab_url( 'performance' ),
		) );
		$actions = array(
			'all'      => 'Clear all cache',
			'minified' => 'Clear minified CSS / JS',
			'oxygen'   => 'Regenerate Oxygen CSS',
			'cloudflare' => 'Clear Cloudflare cache',
			'velox'    => 'Clear Velox cache',
		);
		foreach ( $actions as $which => $label ) {
			$bar->add_node( array(
				'id'     => 'velox-cache-' . $which,
				'parent' => 'velox-cache',
				'title'  => $label,
				'href'   => $this->cache_url( $which ),
			) );
		}
	}

	private function cache_url( $which ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=velox_cache&which=' . rawurlencode( $which ) ),
			'velox_cache_' . $which
		);
	}

	/** No-JS handler so admin-bar cache buttons work from the front end too. */
	public function handle_cache_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'velox' ) );
		}
		$which = isset( $_GET['which'] ) ? sanitize_key( wp_unslash( $_GET['which'] ) ) : 'all';
		check_admin_referer( 'velox_cache_' . $which );

		$res = self::clear_cache( $which );
		set_transient( 'velox_cache_notice_' . get_current_user_id(), $res, 60 );

		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	/** Show the result of an admin-bar cache purge as a dismissible notice. */
	public function cache_notice() {
		$key    = 'velox_cache_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! $notice ) {
			return;
		}
		delete_transient( $key );
		$class = empty( $notice['ok'] ) ? 'notice-error' : 'notice-success';
		printf(
			'<div class="notice %1$s is-dismissible"><p><strong>Velox:</strong> %2$s</p></div>',
			esc_attr( $class ),
			esc_html( $notice['message'] )
		);
	}

	/**
	 * Clear caches. Integrates with whatever is installed and silently skips the
	 * rest. Shared by the admin bar (no-JS) and the AJAX endpoint.
	 */
	public static function clear_cache( $which ) {
		$done    = array();
		$missing = array();

		$purge_wpfc = function () use ( &$done, &$missing ) {
			if ( function_exists( 'wpfc_clear_all_cache' ) ) {
				wpfc_clear_all_cache( true );
				$done[] = 'WP Fastest Cache';
			} elseif ( isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
				$GLOBALS['wp_fastest_cache']->deleteCache( true );
				$done[] = 'WP Fastest Cache';
			} else {
				$missing[] = 'WP Fastest Cache not active';
			}
		};

		$label = 'Cache';
		switch ( $which ) {
			case 'all':
				$label = 'All caches';
				$purge_wpfc();
				if ( function_exists( 'wp_cache_flush' ) ) {
					wp_cache_flush();
					$done[] = 'object cache';
				}
				if ( class_exists( 'Velox_CSS' ) ) {
					Velox_CSS::clear_cache();
					$done[] = 'Velox used-CSS';
				}
				delete_transient( 'velox_latest_release' );
				break;

			case 'minified':
				$label = 'Minified CSS/JS';
				$purge_wpfc();
				break;

			case 'oxygen':
				$label = 'Oxygen CSS';
				if ( function_exists( 'oxygen_vsb_cache_universal_css_file' ) ) {
					oxygen_vsb_cache_universal_css_file();
					$done[] = 'Oxygen CSS';
				} elseif ( has_action( 'oxygen_vsb_cache_generate_css' ) ) {
					do_action( 'oxygen_vsb_cache_generate_css' );
					$done[] = 'Oxygen CSS';
				} else {
					$missing[] = 'Oxygen not active';
				}
				break;

			case 'cloudflare':
				$label = 'Cloudflare';
				if ( has_action( 'cloudflare_purge_everything' ) ) {
					do_action( 'cloudflare_purge_everything' );
					$done[] = 'Cloudflare';
				} else {
					$missing[] = 'Cloudflare plugin not active';
				}
				break;

			case 'velox':
				$label = 'Velox';
				delete_transient( 'velox_latest_release' );
				if ( class_exists( 'Velox_CSS' ) ) {
					Velox_CSS::clear_cache();
				}
				$done[] = 'Velox';
				break;

			default:
				return array( 'ok' => false, 'cleared' => array(), 'message' => 'Error: unknown cache target "' . $which . '"' );
		}

		if ( ! empty( $done ) ) {
			return array(
				'ok'      => true,
				'cleared' => array_values( array_filter( $done ) ),
				'message' => $label . ' purged' . ( $missing ? ' — skipped: ' . implode( '; ', $missing ) : '' ),
			);
		}
		return array(
			'ok'      => false,
			'cleared' => array(),
			'message' => 'Error: ' . ( $missing ? implode( '; ', $missing ) : 'nothing to purge — no matching cache tool detected' ),
		);
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
		$url = menu_page_url( self::SLUG . '-settings', false );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . __( 'Settings', 'velox' ) . '</a>' );
		return $links;
	}

	public function current_tab() {
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
		$admin = $this; // available inside views

		$slug   = $this->current_tab();
		$view   = VELOX_PATH . 'admin/views/' . $slug . '.php';
		$header = VELOX_PATH . 'admin/views/header.php';

		if ( is_readable( $header ) ) {
			include $header;
		} else {
			echo '<div class="velox-wrap"><main class="velox-main">';
		}

		if ( is_readable( $view ) ) {
			include $view;
		} else {
			printf(
				'<div class="velox-alert velox-alert--warn" style="margin:24px;">'
				. 'Velox couldn\'t load the <strong>%s</strong> screen at <code>%s</code>. Reinstall from a fresh zip and clear OPcache.'
				. '</div>',
				esc_html( $slug ),
				esc_html( $view )
			);
		}
		echo '</main></div>';
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
