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
			'seo'         => array( 'label' => 'SEO',          'icon' => 'search', 'module' => 'module_seo' ),
			'utilities'   => array( 'label' => 'Utilities',    'icon' => 'grid', 'module' => null ),
			'settings'    => array( 'label' => 'Settings',     'icon' => 'gear', 'module' => null ),
		);

		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_menu', array( $this, 'inject_active_utilities' ), 100 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 80 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_velox_cache', array( $this, 'handle_cache_action' ) );
		add_action( 'admin_notices', array( $this, 'cache_notice' ) );
		add_action( 'admin_notices', array( $this, 'builder_notice' ) );
		add_action( 'admin_head', array( $this, 'menu_icon_css' ) );
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
		add_menu_page( 'Velox', 'Velox', 'manage_options', self::SLUG, array( $this, 'render' ), $this->menu_icon(), 100.7 );

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

	/**
	 * Add the active utilities as a hover "flyout" under the left-sidebar
	 * Utilities item. Utilities is always present and clickable; when one or more
	 * utilities are switched on, each appears as a child row (rendered as a flyout
	 * by velox_sidebar_flyout_css) linking straight into that utility's settings.
	 */
	/**
	 * Custom hover-flyout for the left-sidebar Velox item. Utilities is always a
	 * reachable click; when one or more utilities are active, the Velox row gets an
	 * arrow and a popover (on hover) listing each active utility, linking into its
	 * settings. Rendered as our own markup in the footer so it works whether the
	 * admin menu is expanded or folded, and never fights WordPress's own submenu.
	 */
	public function inject_active_utilities() {
		if ( ! class_exists( 'Velox_Utilities' ) ) {
			return;
		}
		// The flyout mirrors the full Velox menu, so it always renders. The
		// Utilities row inside it gains a sub-flyout only when ≥1 utility is active.
		add_action( 'admin_footer', array( $this, 'render_sidebar_flyout' ) );
		add_action( 'admin_head',   array( $this, 'sidebar_flyout_css' ) );
	}

	/** The flyout markup + the JS that anchors it to the Velox menu row on hover. */
	public function render_sidebar_flyout() {
		$active = Velox_Utilities::enabled_tools();
		$cat    = Velox_Utilities::catalog();

		// The full set of pages, mirroring the top admin-bar Velox dropdown.
		$pages = array(
			'Dashboard'    => 'dashboard',
			'Images'       => 'images',
			'Media Editor' => 'media',
			'Performance'  => 'performance',
			'Database'     => 'database',
			'SEO'          => 'seo',
			'Utilities'    => 'utilities',
			'Settings'     => 'settings',
		);
		?>
		<div id="velox-velox-flyout" class="velox-side-flyout" role="menu" aria-label="Velox menu">
			<?php foreach ( $pages as $label => $key ) :
				$is_utils = ( 'utilities' === $key );
				$has_sub  = ( $is_utils && ! empty( $active ) );
				?>
				<div class="velox-side-fly-row<?php echo $has_sub ? ' has-sub' : ''; ?>">
					<a class="velox-side-fly-item" role="menuitem" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->page_slug( $key ) ) ); ?>">
						<span><?php echo esc_html( $label ); ?></span>
						<?php if ( $has_sub ) : ?><span class="velox-side-fly-arrow" aria-hidden="true">›</span><?php endif; ?>
					</a>
					<?php if ( $has_sub ) : ?>
						<div class="velox-side-subfly" role="menu" aria-label="Active utilities">
							<div class="velox-side-subfly-head">Active utilities</div>
							<?php foreach ( $active as $id ) :
								if ( ! isset( $cat[ $id ] ) ) { continue; }
								?>
								<a class="velox-side-subfly-item" role="menuitem" href="<?php echo esc_url( Velox_Utilities::tool_url( $id ) ); ?>">
									<span class="velox-util-dot" aria-hidden="true"></span>
									<span><?php echo esc_html( $cat[ $id ]['label'] ); ?></span>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<script>
		( function () {
			var menuItem = document.getElementById( 'toplevel_page_<?php echo esc_js( self::SLUG ); ?>' );
			var flyout   = document.getElementById( 'velox-velox-flyout' );
			if ( ! menuItem || ! flyout ) { return; }
			menuItem.classList.add( 'velox-has-flyout' );
			var hideTimer;
			function place() {
				var r = menuItem.getBoundingClientRect();
				flyout.style.top  = Math.max( 8, r.top ) + 'px';
				flyout.style.left = r.right + 'px';
			}
			function show() { clearTimeout( hideTimer ); place(); flyout.classList.add( 'is-open' ); }
			function hide() { hideTimer = setTimeout( function () { flyout.classList.remove( 'is-open' ); }, 220 ); }
			menuItem.addEventListener( 'mouseenter', show );
			menuItem.addEventListener( 'mouseleave', hide );
			flyout.addEventListener( 'mouseenter', show );
			flyout.addEventListener( 'mouseleave', hide );
			window.addEventListener( 'scroll', function () { if ( flyout.classList.contains( 'is-open' ) ) { place(); } }, true );
		} )();
		</script>
		<?php
	}

	/** Arrow on the Velox row + styling for the custom flyout popover. */
	public function sidebar_flyout_css() {
		?>
		<style id="velox-sidebar-flyout">
			/* Arrow affordance on the Velox top-level row. */
			#adminmenu #toplevel_page_<?php echo esc_attr( self::SLUG ); ?>.velox-has-flyout > a.wp-has-submenu::after,
			#adminmenu #toplevel_page_<?php echo esc_attr( self::SLUG ); ?>.velox-has-flyout > a::after {
				content: ""; position: absolute; right: 12px; top: 50%;
				width: 6px; height: 6px; margin-top: -3px;
				border-right: 1.5px solid currentColor; border-bottom: 1.5px solid currentColor;
				transform: rotate(-45deg); opacity: .5;
			}
			/* The main flyout — mirrors the top-bar Velox menu. Fixed so it escapes overflow. */
			.velox-side-flyout {
				position: fixed; z-index: 100000; min-width: 210px;
				background: #1d1d1f; color: #fff;
				border-radius: 11px; padding: 7px;
				box-shadow: 0 12px 40px -8px rgba(0,0,0,.45);
				opacity: 0; visibility: hidden; transform: translateX(-6px);
				transition: opacity .15s ease, transform .15s ease, visibility .15s;
				font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
			}
			.velox-side-flyout.is-open { opacity: 1; visibility: visible; transform: translateX(0); }
			.velox-side-fly-row { position: relative; }
			.velox-side-fly-item {
				display: flex; align-items: center; justify-content: space-between; gap: 9px;
				padding: 8px 11px; border-radius: 7px;
				color: #f5f5f7 !important; text-decoration: none; font-size: 13px; font-weight: 500;
			}
			.velox-side-fly-item:hover { background: rgba(255,255,255,.08); color: #fff !important; }
			.velox-side-fly-arrow { font-size: 15px; line-height: 1; opacity: .6; }
			/* Nested sub-flyout for the Utilities row — opens to the right on hover. */
			.velox-side-subfly {
				position: absolute; left: calc(100% + 6px); top: -7px; min-width: 200px;
				background: #1d1d1f; border-radius: 11px; padding: 7px;
				box-shadow: 0 12px 40px -8px rgba(0,0,0,.45);
				opacity: 0; visibility: hidden; transform: translateX(-6px);
				transition: opacity .14s ease, transform .14s ease, visibility .14s;
			}
			.velox-side-fly-row.has-sub:hover .velox-side-subfly { opacity: 1; visibility: visible; transform: translateX(0); }
			.velox-side-subfly-head {
				font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
				color: #86868b; padding: 4px 10px 8px;
			}
			.velox-side-subfly-item {
				display: flex; align-items: center; gap: 9px;
				padding: 8px 10px; border-radius: 7px;
				color: #f5f5f7 !important; text-decoration: none; font-size: 13px; font-weight: 500;
			}
			.velox-side-subfly-item:hover { background: rgba(255,255,255,.08); color: #fff !important; }
			.velox-util-dot { width: 6px; height: 6px; border-radius: 50%; background: #2ab7f1; flex: none; }
		</style>
		<?php
	}

	/** Top admin-bar entry: full Velox menu + Performance & Cache submenu, with a
	 *  sibling Maintenance node (settings + activate/deactivate). */
	public function admin_bar( $bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$icon = '<img src="' . esc_url( VELOX_URL . 'assets/menu-icon.png' ) . '" alt="" '
			. 'style="width:20px;height:20px;vertical-align:middle;margin:0 7px 0 0;display:inline-block;">';

		// --- Velox parent ---
		$bar->add_node( array(
			'id'    => 'velox',
			'title' => $icon . 'Velox',
			'href'  => menu_page_url( self::SLUG, false ),
		) );

		// --- Velox dropdown: the core pages, in the requested order ---
		// label => page slug key (resolved through page_slug()).
		$pages = array(
			'Dashboard'    => 'dashboard',
			'Images'       => 'images',
			'Media Editor' => 'media',
			'Performance'  => 'performance',
			'Database'     => 'database',
			'SEO'          => 'seo',
			'Utilities'    => 'utilities',
			'Settings'     => 'settings',
		);
		$order = 10;
		foreach ( $pages as $label => $key ) {
			$bar->add_node( array(
				'id'     => 'velox-go-' . $key,
				'parent' => 'velox',
				'title'  => $label,
				'href'   => admin_url( 'admin.php?page=' . $this->page_slug( $key ) ),
				'meta'   => array( 'class' => 'velox-bar-item' ),
			) );
			$order += 10;
		}

		// --- Performance & Cache submenu (nested under Velox) ---
		$bar->add_node( array(
			'id'     => 'velox-perfcache',
			'parent' => 'velox',
			'title'  => 'Performance &amp; Cache',
			'href'   => admin_url( 'admin.php?page=' . $this->page_slug( 'performance' ) ),
		) );
		$cache_items = array(
			'Performance settings' => admin_url( 'admin.php?page=' . $this->page_slug( 'performance' ) ),
			'Clear all cache'        => $this->cache_url( 'all' ),
			'Clear minified CSS / JS'=> $this->cache_url( 'minified' ),
			'Regenerate Oxygen CSS'  => $this->cache_url( 'oxygen' ),
			'Clear Cloudflare cache' => $this->cache_url( 'cloudflare' ),
			'Clear Velox cache'      => $this->cache_url( 'velox' ),
		);
		foreach ( $cache_items as $label => $href ) {
			$bar->add_node( array(
				'id'     => 'velox-pc-' . sanitize_title( $label ),
				'parent' => 'velox-perfcache',
				'title'  => $label,
				'href'   => $href,
			) );
		}

		// --- Maintenance: sibling node with its own Settings + Activate/Deactivate ---
		$maint_on  = (bool) Velox_Settings::get( 'util_maintenance' );
		$maint_settings = admin_url( 'admin.php?page=velox-utilities&tool=maintenance' );
		$maint_toggle   = wp_nonce_url( admin_url( 'admin.php?page=velox-utilities&tool=maintenance&velox_maint_toggle=1' ), 'velox_maint_toggle' );
		$maint_dot = '<span style="display:inline-block;width:9px;height:9px;border-radius:50%;margin:0 7px 0 2px;vertical-align:middle;background:'
			. ( $maint_on ? '#22c55e' : '#9aa0a6' )
			. ( $maint_on ? ';box-shadow:0 0 0 3px rgba(34,197,94,.3)' : '' ) . ';"></span>';

		$bar->add_node( array(
			'id'    => 'velox-maintenance',
			'title' => $maint_dot . 'Velox Maintenance',
			'href'  => $maint_settings,
			'meta'  => array( 'title' => $maint_on ? 'Maintenance is ON' : 'Maintenance is OFF' ),
		) );
		$bar->add_node( array(
			'id'     => 'velox-maint-settings',
			'parent' => 'velox-maintenance',
			'title'  => 'Settings',
			'href'   => $maint_settings,
		) );
		$bar->add_node( array(
			'id'     => 'velox-maint-toggle',
			'parent' => 'velox-maintenance',
			'title'  => $maint_on ? 'Deactivate' : 'Activate',
			'href'   => $maint_toggle,
		) );
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

	/** Warn on every admin page when the active builder no longer matches our setup. */
	public function builder_notice() {
		$saved = Velox_Builders::current();
		if ( '' === $saved ) {
			return; // wizard hasn't run yet — the setup popup handles onboarding
		}
		$detected = Velox_Builders::detect();
		if ( 'wordpress' === $detected || $detected === $saved ) {
			return; // nothing detected, or it still matches
		}
		$url = admin_url( 'admin.php?page=' . self::SLUG . '&velox_wizard=1' );
		printf(
			'<div class="notice notice-warning"><p><strong>Velox:</strong> Builder change detected — you were set up for <strong>%1$s</strong>, but <strong>%2$s</strong> is active now. Re-run the setup wizard so your settings match. <a class="button button-small" href="%3$s">Run setup wizard</a></p></div>',
			esc_html( Velox_Builders::label( $saved ) ),
			esc_html( Velox_Builders::label( $detected ) ),
			esc_url( $url )
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
				if ( class_exists( 'Velox_Cache' ) ) {
					Velox_Cache::purge_all();
					$done[] = 'Velox page cache';
				}
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
				if ( class_exists( 'Velox_Cache' ) ) {
					Velox_Cache::purge_all();
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
		return VELOX_URL . 'assets/menu-icon.png';
	}

	/** Size + centre the Velox icon in the left admin menu (loads on every admin page). */
	public function menu_icon_css() {
		echo '<style>#adminmenu #toplevel_page_' . esc_attr( self::SLUG ) . ' .wp-menu-image img{width:25px;height:25px;padding:5px 0 0;margin:0 auto;display:block;}</style>';
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'velox-admin', VELOX_ASSETS . 'css/velox-admin.css', array(), VELOX_VERSION );
		wp_enqueue_media(); // lets tools (maintenance logo/background) open the media library
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
			echo '<div class="velox-wrap"><div class="velox-app"><div class="velox-content"><main class="velox-main">';
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
		echo '</main></div></div></div>';
	}

	/** Tiny inline icon set used by the nav + cards. */
	public static function icon( $name, $size = 20 ) {
		$paths = array(
			'home'  => '<path d="M3 11.5 12 4l9 7.5M5 10v9h5v-5h4v5h5v-9"/>',
			'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m4 17 5-5 4 4 3-3 4 4"/>',
			'tag'   => '<path d="M3 7v5l9 9 7-7-9-9H3Z"/><circle cx="7" cy="11" r="1.2"/>',
			'bolt'  => '<path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z"/>',
			'db'    => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
			'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
			'gear'  => '<path d="M10.3 2.3a2 2 0 0 1 3.4 0l.4.8a2 2 0 0 0 2.2 1l.9-.2a2 2 0 0 1 2.4 2.4l-.2.9a2 2 0 0 0 1 2.2l.8.4a2 2 0 0 1 0 3.4l-.8.4a2 2 0 0 0-1 2.2l.2.9a2 2 0 0 1-2.4 2.4l-.9-.2a2 2 0 0 0-2.2 1l-.4.8a2 2 0 0 1-3.4 0l-.4-.8a2 2 0 0 0-2.2-1l-.9.2a2 2 0 0 1-2.4-2.4l.2-.9a2 2 0 0 0-1-2.2l-.8-.4a2 2 0 0 1 0-3.4l.8-.4a2 2 0 0 0 1-2.2l-.2-.9a2 2 0 0 1 2.4-2.4l.9.2a2 2 0 0 0 2.2-1z"/><circle cx="12" cy="12" r="3"/>',
			'check' => '<path d="m5 12 4 4 10-10"/>',
			'grid'  => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
			'file'  => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5"/>',
			'copy'  => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/>',
			'plug'  => '<path d="M9 2v6m6-6v6M6 8h12v3a6 6 0 0 1-12 0V8Zm6 9v5"/>',
			'redirect' => '<path d="M4 7h11a4 4 0 0 1 0 8H9m0 0 3-3m-3 3 3 3"/>',
			'mail'  => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
			'broom' => '<path d="m19 4-7 7M6 21l-2-2 5-5 2 2-5 5Zm5-5 4-4 1 1-4 4"/>',
			'lock'  => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
			'cone'  => '<path d="m9 3 6 18M5 21h14M7.5 9h9M6.2 15h11.6"/>',
			'list'  => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
			'code'  => '<path d="m16 18 6-6-6-6M8 6l-6 6 6 6"/>',
			'cookie' => '<path d="M12 3a9 9 0 1 0 9 9 3 3 0 0 1-3-3 3 3 0 0 1-3-3 3 3 0 0 1-3-3Z"/><circle cx="9" cy="11" r="1"/><circle cx="13" cy="15" r="1"/><circle cx="15.5" cy="9.5" r="1"/>',
			'package' => '<path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
		);
		$p = isset( $paths[ $name ] ) ? $paths[ $name ] : '';
		return '<svg class="velox-ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
	}
}
