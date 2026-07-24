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
			'pagespeed'   => array( 'label' => 'PageSpeed',    'icon' => 'search', 'module' => 'ps_enable' ),
			'database'    => array( 'label' => 'Database',     'icon' => 'db', 'module' => 'module_database' ),
			'seo'         => array( 'label' => 'SEO',          'icon' => 'search', 'module' => 'module_seo' ),
			'utilities'   => array( 'label' => 'Utilities',    'icon' => 'grid', 'module' => null ),
			'settings'    => array( 'label' => 'Settings',     'icon' => 'gear', 'module' => null ),
		);

		// Admin bar shows on the front end too, plus the no-JS handler its cache
		// links post to — so these register in every context.
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 80 );
		add_action( 'admin_post_velox_cache', array( $this, 'handle_cache_action' ) );

		// Everything else is only needed inside wp-admin.
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_menu', array( $this, 'inject_active_utilities' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
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
		// Nothing to show until at least one utility is switched on.
		if ( empty( $active ) ) {
			return;
		}
		?>
		<div id="velox-utilities-flyout" class="velox-side-subfly velox-util-pop" role="menu" aria-label="Active utilities">
			<div class="velox-side-subfly-head">Active utilities</div>
			<?php foreach ( $active as $id ) :
				if ( ! isset( $cat[ $id ] ) ) { continue; }
				?>
				<a class="velox-side-subfly-item" role="menuitem" href="<?php echo esc_url( Velox_Utilities::tool_url( $id ) ); ?>">
					<span class="velox-util-ficon" aria-hidden="true"><?php echo Velox_Admin::icon( $cat[ $id ]['icon'], 15 ); // phpcs:ignore ?></span>
					<span class="velox-util-flabel"><?php echo esc_html( $cat[ $id ]['label'] ); ?></span>
					<span class="velox-util-fgo" aria-hidden="true">&rsaquo;</span>
				</a>
			<?php endforeach; ?>
		</div>
		<script>
		( function () {
			var pop = document.getElementById( 'velox-utilities-flyout' );
			if ( ! pop ) { return; }
			// Anchor to the real "Utilities" submenu link in the Velox menu.
			var utilSlug = '<?php echo esc_js( $this->page_slug( 'utilities' ) ); ?>';
			var link = document.querySelector( '#toplevel_page_<?php echo esc_js( self::SLUG ); ?> a[href*="page=' + utilSlug + '"]' );
			if ( ! link ) { return; }
			var row = link.parentNode; // the <li>
			row.classList.add( 'velox-util-haspop' );
			var hideTimer;

			function place() {
				var r  = link.getBoundingClientRect();
				var vw = window.innerWidth, vh = window.innerHeight;
				pop.style.maxHeight = ( vh - 16 ) + 'px';
				var ph = pop.offsetHeight, pw = pop.offsetWidth;
				// vertical: align to the item, flip up if it would overflow the bottom
				var top = r.top - 7;
				if ( top + ph > vh - 8 ) { top = Math.max( 8, vh - 8 - ph ); }
				pop.style.top = top + 'px';
				// horizontal: open to the right of the sidebar, flip left if no room
				if ( r.right + 6 + pw > vw - 8 ) { pop.style.left = Math.max( 8, r.left - 6 - pw ) + 'px'; }
				else { pop.style.left = ( r.right + 6 ) + 'px'; }
			}
			function show() { clearTimeout( hideTimer ); place(); pop.classList.add( 'is-open' ); }
			function hide() { hideTimer = setTimeout( function () { pop.classList.remove( 'is-open' ); }, 200 ); }

			link.addEventListener( 'mouseenter', show );
			row.addEventListener( 'mouseleave', hide );
			pop.addEventListener( 'mouseenter', show );
			pop.addEventListener( 'mouseleave', hide );
			window.addEventListener( 'scroll', function () { if ( pop.classList.contains( 'is-open' ) ) { place(); } }, true );
			window.addEventListener( 'resize', function () { if ( pop.classList.contains( 'is-open' ) ) { place(); } } );
		} )();
		</script>
		<?php
	}

	/** Arrow on the Velox row + styling for the custom flyout popover. */
	public function sidebar_flyout_css() {
		?>
		<style id="velox-sidebar-flyout">
			/* Active-utilities popover, anchored to the Utilities submenu item.
			   JS sets top/left; this is a fixed, escape-the-overflow popover. */
			.velox-util-pop {
				position: fixed; z-index: 100000; min-width: 232px;
				max-height: calc(100vh - 16px); overflow-y: auto; overscroll-behavior: contain;
				background: #1d1d1f; color: #fff;
				border-radius: 12px; padding: 8px;
				box-shadow: 0 12px 40px -8px rgba(0,0,0,.45);
				opacity: 0; visibility: hidden; transform: translateX(-6px);
				transition: opacity .14s ease, transform .14s ease, visibility .14s;
				font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
			}
			.velox-util-pop.is-open { opacity: 1; visibility: visible; transform: translateX(0); }
			.velox-side-subfly-head {
				font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
				color: #86868b; padding: 5px 10px 9px; border-bottom: 1px solid rgba(255,255,255,.07); margin-bottom: 5px;
			}
			.velox-side-subfly-item {
				display: flex; align-items: center; gap: 11px;
				padding: 8px 10px; border-radius: 8px;
				color: #f5f5f7 !important; text-decoration: none; font-size: 13px; font-weight: 500;
				transition: background .12s ease;
			}
			.velox-util-ficon {
				flex: none; width: 26px; height: 26px; border-radius: 7px;
				background: rgba(255,255,255,.08); color: #9fdcfb;
				display: flex; align-items: center; justify-content: center;
			}
			.velox-util-ficon svg { width: 15px; height: 15px; }
			.velox-util-flabel { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
			.velox-util-fgo { color: #6e6e73; font-size: 16px; line-height: 1; opacity: 0; transition: opacity .12s ease, transform .12s ease; transform: translateX(-3px); }
			.velox-side-subfly-item:hover { background: rgba(255,255,255,.1); color: #fff !important; }
			.velox-side-subfly-item:hover .velox-util-ficon { background: #2ab7f1; color: #fff; }
			.velox-side-subfly-item:hover .velox-util-fgo { opacity: 1; transform: translateX(0); }
			/* Small arrow hint on the Utilities submenu row when it has a popover. */
			#adminmenu .velox-util-haspop > a::after {
				content: "\203A"; margin-left: auto; padding-left: 8px; opacity: .55; font-size: 14px; line-height: 1;
			}
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
				// Velox clears its OWN minified / used-CSS first — this works with or
				// without WP Fastest Cache, so the button is never a dead end.
				if ( class_exists( 'Velox_CSS' ) ) {
					Velox_CSS::clear_cache();
					$done[] = 'Velox used/minified CSS';
				}
				if ( class_exists( 'Velox_Cache' ) ) {
					Velox_Cache::purge_all();
					$done[] = 'Velox page cache';
				}
				// Then hand WP Fastest Cache a purge only if it happens to be present —
				// no error if it isn't.
				if ( function_exists( 'wpfc_clear_all_cache' ) ) {
					wpfc_clear_all_cache( true );
					$done[] = 'WP Fastest Cache';
				} elseif ( isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
					$GLOBALS['wp_fastest_cache']->deleteCache( true );
					$done[] = 'WP Fastest Cache';
				}
				break;

			case 'oxygen':
				$label = 'Oxygen CSS';
				$oxy_present = defined( 'CT_VERSION' ) || function_exists( 'oxygen_vsb_cache_universal_css_file' ) || class_exists( 'OxygenElement' ) || defined( 'OXYGEN_VSB_PLUGIN_DIR' );
				if ( function_exists( 'oxygen_vsb_cache_universal_css_file' ) ) {
					oxygen_vsb_cache_universal_css_file();
					$done[] = 'Oxygen CSS';
				} elseif ( has_action( 'oxygen_vsb_cache_generate_css' ) ) {
					do_action( 'oxygen_vsb_cache_generate_css' );
					$done[] = 'Oxygen CSS';
				} elseif ( $oxy_present ) {
					// Oxygen is installed but this version doesn't expose its regen helper.
					// Force a rebuild ourselves by clearing the cached universal-CSS
					// signature options so Oxygen regenerates on the next front-end load,
					// and drop Velox's own used-CSS cache alongside it.
					delete_option( 'oxygen_vsb_universal_css_url' );
					delete_option( 'oxygen_vsb_universal_css_cache' );
					delete_option( 'ct_universal_css_status' );
					if ( class_exists( 'Velox_CSS' ) ) { Velox_CSS::clear_cache(); }
					$done[] = 'Oxygen CSS (queued rebuild)';
				} else {
					$missing[] = "Oxygen isn't installed on this site";
				}
				break;

			case 'cloudflare':
				$label = 'Cloudflare';
				if ( has_action( 'cloudflare_purge_everything' ) ) {
					do_action( 'cloudflare_purge_everything' );
					$done[] = 'Cloudflare';
				} else {
					$missing[] = 'Cloudflare plugin not active — install & connect the official Cloudflare plugin (with an API token) to purge the edge cache';
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
		$slug = esc_attr( self::SLUG );
		echo '<style id="velox-adminmenu-fix">';
		// Icon sizing.
		echo '#adminmenu #toplevel_page_' . $slug . ' .wp-menu-image img{width:25px;height:25px;padding:5px 0 0;margin:0 auto;display:block;}';
		// 8b — legible hover for every Velox menu/submenu item. Some admin colour
		// schemes paint the hovered row a dark fill and leave the text dark, so the
		// label + arrow vanish. Keep the background unchanged and colour the text
		// (and the arrow hint) with the Velox accent instead.
		$m = '#adminmenu #toplevel_page_' . $slug;
		echo $m . ' .wp-submenu a:hover,';
		echo $m . ' .wp-submenu a:focus,';
		echo $m . ' .wp-submenu li.current a:hover,';
		echo $m . '.wp-has-current-submenu .wp-submenu a:hover,';
		echo $m . '.opensub .wp-submenu a:hover{background:transparent!important;color:#2ab7f1!important;box-shadow:none!important;}';
		// Top-level Velox row hover.
		echo $m . ':hover>a.menu-top,';
		echo $m . '>a.menu-top:focus{color:#2ab7f1!important;}';
		// Arrow hint stays visible in the accent colour on hover/focus.
		echo $m . ' .velox-util-haspop:hover>a::after,';
		echo $m . ' .velox-util-haspop>a:focus::after{color:#2ab7f1!important;opacity:1!important;}';
		echo '</style>';
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
			'mediaUrl'    => admin_url( 'admin.php?page=' . self::SLUG . '-media' ),
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
		// Code Snippets renders its own page but lives inside the Velox shell, so
		// report it as the active section for the sidebar highlight.
		if ( class_exists( 'Velox_Snippets' ) && Velox_Snippets::MENU_SLUG === $page ) {
			return 'snippets';
		}
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
			'home' => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/> <path d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
			'image' => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/> <circle cx="9" cy="9" r="2"/> <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
			'tag' => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/> <circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
			'bolt' => '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>',
			'db' => '<ellipse cx="12" cy="5" rx="9" ry="3"/> <path d="M3 5V19A9 3 0 0 0 21 19V5"/> <path d="M3 12A9 3 0 0 0 21 12"/>',
			'search' => '<path d="m21 21-4.34-4.34"/> <circle cx="11" cy="11" r="8"/>',
			'gear' => '<path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"/> <circle cx="12" cy="12" r="3"/>',
			'check' => '<path d="M20 6 9 17l-5-5"/>',
			'grid' => '<rect width="7" height="7" x="3" y="3" rx="1"/> <rect width="7" height="7" x="14" y="3" rx="1"/> <rect width="7" height="7" x="14" y="14" rx="1"/> <rect width="7" height="7" x="3" y="14" rx="1"/>',
			'file' => '<path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/> <path d="M14 2v5a1 1 0 0 0 1 1h5"/>',
			'copy' => '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/> <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
			'plug' => '<path d="M12 22v-5"/> <path d="M15 8V2"/> <path d="M17 8a1 1 0 0 1 1 1v4a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4V9a1 1 0 0 1 1-1z"/> <path d="M9 8V2"/>',
			'redirect' => '<circle cx="6" cy="19" r="3"/> <path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/> <circle cx="18" cy="5" r="3"/>',
			'mail' => '<path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"/> <rect x="2" y="4" width="20" height="16" rx="2"/>',
			'broom' => '<path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"/> <path d="M20 2v4"/> <path d="M22 4h-4"/> <circle cx="4" cy="20" r="2"/>',
			'lock' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/> <path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
			'cone' => '<path d="M16.05 10.966a5 2.5 0 0 1-8.1 0"/> <path d="m16.923 14.049 4.48 2.04a1 1 0 0 1 .001 1.831l-8.574 3.9a2 2 0 0 1-1.66 0l-8.574-3.91a1 1 0 0 1 0-1.83l4.484-2.04"/> <path d="M16.949 14.14a5 2.5 0 1 1-9.9 0L10.063 3.5a2 2 0 0 1 3.874 0z"/> <path d="M9.194 6.57a5 2.5 0 0 0 5.61 0"/>',
			'list' => '<path d="M3 5h.01"/> <path d="M3 12h.01"/> <path d="M3 19h.01"/> <path d="M8 5h13"/> <path d="M8 12h13"/> <path d="M8 19h13"/>',
			'code' => '<path d="m16 18 6-6-6-6"/> <path d="m8 6-6 6 6 6"/>',
			'cookie' => '<path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/> <path d="M8.5 8.5v.01"/> <path d="M16 15.5v.01"/> <path d="M12 12v.01"/> <path d="M11 17v.01"/> <path d="M7 14v.01"/>',
			'package' => '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/> <path d="M12 22V12"/> <polyline points="3.29 7 12 12 20.71 7"/> <path d="m7.5 4.27 9 5.15"/>',
			'folder' => '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
			'pin' => '<path d="M12 17v5"/> <path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"/>',
			'trash' => '<path d="M10 11v6"/> <path d="M14 11v6"/> <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/> <path d="M3 6h18"/> <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
		);
		$p = isset( $paths[ $name ] ) ? $paths[ $name ] : '';
		return '<svg class="velox-ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
	}
}
