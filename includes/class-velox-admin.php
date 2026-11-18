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
			'reviews'     => array( 'label' => 'Reviews',      'icon' => 'star', 'module' => 'util_reviews' ),
			'utilities'   => array( 'label' => 'Utilities',    'icon' => 'grid', 'module' => null ),
			'settings'    => array( 'label' => 'Settings',     'icon' => 'gear', 'module' => null ),
		);

		// Admin bar shows on the front end too, plus the no-JS handler its cache
		// links post to — so these register in every context.
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 80 );
		add_action( 'admin_post_velox_cache', array( $this, 'handle_cache_action' ) );
		// Clears the cache in place (loader + confirmation) instead of reloading.
		add_action( 'wp_after_admin_bar_render', array( $this, 'admin_bar_cache_script' ) );

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

	/** Top admin-bar entries: the Velox menu, then Velox Performance (settings +
	 *  every cache action) and Velox Maintenance as siblings next to it. */
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

		// --- Velox Performance: its own top-level entry next to Velox, so clearing
		// the cache is one hover away instead of two levels deep ---
		$perf_url = admin_url( 'admin.php?page=' . $this->page_slug( 'performance' ) );
		$bar->add_node( array(
			'id'    => 'velox-performance',
			'title' => '<span class="ab-icon dashicons dashicons-performance" aria-hidden="true" style="top:2px;"></span>' . esc_html__( 'Velox Performance', 'velox' ),
			'href'  => $perf_url,
		) );
		$bar->add_node( array(
			'id'     => 'velox-pc-settings',
			'parent' => 'velox-performance',
			'title'  => esc_html__( 'Performance settings', 'velox' ),
			'href'   => $perf_url,
		) );
		$cache_items = array(
			'all'        => __( 'Clear all cache', 'velox' ),
			'minified'   => __( 'Clear minified CSS / JS', 'velox' ),
			'oxygen'     => __( 'Regenerate Oxygen CSS', 'velox' ),
			'cloudflare' => __( 'Clear Cloudflare cache', 'velox' ),
			'velox'      => __( 'Clear Velox cache', 'velox' ),
		);
		foreach ( $cache_items as $which => $label ) {
			$bar->add_node( array(
				'id'     => 'velox-pc-' . $which,
				'parent' => 'velox-performance',
				'title'  => esc_html( $label ),
				// The link still works without JavaScript; with it, the click is
				// taken over by admin_bar_cache_script().
				'href'   => $this->cache_url( $which ),
				'meta'   => array( 'class' => 'velox-bar-cache' ),
			) );
		}

		// Fonts: re-optimize the page being viewed (front end only), like OMGF's
		// "Optimize this page".
		if ( ! is_admin() && Velox_Settings::get( 'module_performance' ) && Velox_Settings::get( 'perf_local_fonts', false ) && ! Velox_Settings::get( 'perf_fonts_adminbar_off', false )
			&& class_exists( 'Velox_Local_Fonts' ) ) {
			$here = ( is_ssl() ? 'https://' : 'http://' ) . ( isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '' ) . ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/' );
			$here = remove_query_arg( array( Velox_Local_Fonts::RUN_ARG, 'nocache' ), $here );
			$bar->add_node( array(
				'id'     => 'velox-pc-fonts',
				'parent' => 'velox-performance',
				'title'  => esc_html__( 'Optimize fonts on this page', 'velox' ),
				'href'   => Velox_Local_Fonts::optimize_page_url( $here ),
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

	/**
	 * Admin-bar cache links: clear in place with a loader, then confirm in a
	 * popup — no page reload, works in wp-admin and on the front end alike.
	 */
	public function admin_bar_cache_script() {
		if ( ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) {
			return;
		}
		$cfg = array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'velox_nonce' ),
			// Google Fonts checker: on the front end, report fonts the browser still
			// loaded from Google (what OMGF's checker does).
			'check' => ! is_admin() && Velox_Settings::get( 'perf_local_fonts', false ) && ! Velox_Settings::get( 'perf_fonts_adminbar_off', false ),
			'i18n'  => array(
				'working' => __( 'Clearing cache…', 'velox' ),
				'done'    => __( 'Cache removed', 'velox' ),
				'failed'  => __( 'Could not clear the cache', 'velox' ),
				'ok'      => __( 'OK', 'velox' ),
				'neterr'  => __( 'The server did not answer. Please try again.', 'velox' ),
			),
		);
		?>
<style id="velox-cc-css">
#velox-cc{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;background:rgba(15,18,24,.45);font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
#velox-cc[hidden]{display:none}
#velox-cc .vcc-box{background:#fff;color:#1d2327;border-radius:16px;box-shadow:0 20px 50px rgba(0,0,0,.25);padding:28px 28px 22px;width:min(380px,calc(100vw - 32px));text-align:center}
#velox-cc .vcc-spin{width:38px;height:38px;margin:4px auto 14px;border-radius:50%;border:3px solid #e6eef3;border-top-color:#2ab7f1;animation:vcc-spin .8s linear infinite}
#velox-cc .vcc-ic{width:44px;height:44px;margin:0 auto 12px;border-radius:50%;display:grid;place-items:center;font-size:22px;font-weight:700;color:#fff;background:#22a06b}
#velox-cc.is-err .vcc-ic{background:#d63638}
#velox-cc .vcc-t{margin:0 0 6px;font-size:17px;font-weight:600}
#velox-cc .vcc-m{margin:0 0 18px;color:#50575e;font-size:13px}
#velox-cc .vcc-ok{min-width:96px;padding:9px 18px;border:0;border-radius:8px;background:#1d2327;color:#fff;font:inherit;font-weight:600;cursor:pointer}
#velox-cc .vcc-ok:focus-visible{outline:2px solid #2ab7f1;outline-offset:2px}
@keyframes vcc-spin{to{transform:rotate(360deg)}}
@media (prefers-reduced-motion:reduce){#velox-cc .vcc-spin{animation-duration:2s}}
</style>
<script id="velox-cc-js">
( function () {
	var C = <?php echo wp_json_encode( $cfg ); ?>, box = null, busy = false, back = null;
	function el() {
		if ( box ) { return box; }
		box = document.createElement( 'div' );
		box.id = 'velox-cc';
		box.hidden = true;
		box.setAttribute( 'role', 'dialog' );
		box.setAttribute( 'aria-modal', 'true' );
		box.setAttribute( 'aria-labelledby', 'velox-cc-t' );
		box.innerHTML = '<div class="vcc-box"><div class="vcc-spin" aria-hidden="true"></div><div class="vcc-ic" aria-hidden="true"></div>' +
			'<p class="vcc-t" id="velox-cc-t"></p><p class="vcc-m" aria-live="polite"></p><button type="button" class="vcc-ok"></button></div>';
		document.body.appendChild( box );
		box.querySelector( '.vcc-ok' ).textContent = C.i18n.ok;
		box.querySelector( '.vcc-ok' ).addEventListener( 'click', close );
		box.addEventListener( 'click', function ( e ) { if ( e.target === box && ! busy ) { close(); } } );
		document.addEventListener( 'keydown', function ( e ) { if ( 'Escape' === e.key && ! box.hidden && ! busy ) { close(); } } );
		return box;
	}
	function show( state, title, msg ) {
		var b = el();
		b.hidden = false;
		b.className = 'error' === state ? 'is-err' : '';
		b.querySelector( '.vcc-spin' ).style.display = 'loading' === state ? '' : 'none';
		b.querySelector( '.vcc-ic' ).style.display = 'loading' === state ? 'none' : '';
		b.querySelector( '.vcc-ic' ).textContent = 'error' === state ? '!' : '✓';
		b.querySelector( '.vcc-ok' ).style.display = 'loading' === state ? 'none' : '';
		b.querySelector( '.vcc-t' ).textContent = title;
		b.querySelector( '.vcc-m' ).textContent = msg || '';
		if ( 'loading' !== state ) { b.querySelector( '.vcc-ok' ).focus(); }
	}
	function close() {
		if ( box ) { box.hidden = true; }
		if ( back && back.focus ) { back.focus(); }
	}
	if ( C.check && window.performance && performance.getEntriesByType ) {
		window.addEventListener( 'load', function () {
			setTimeout( function () {
				var left = performance.getEntriesByType( 'resource' ).map( function ( r ) { return r.name; } ).filter( function ( u ) {
					return /\/\/fonts\.googleapis\.com\/(css|icon)|\/\/fonts\.gstatic\.com\//.test( u );
				} );
				if ( ! left.length ) { return; }
				var b = new FormData();
				b.append( 'action', 'velox' ); b.append( 'do', 'fonts_checker' ); b.append( 'nonce', C.nonce );
				b.append( 'urls', JSON.stringify( left.slice( 0, 10 ) ) ); b.append( 'path', location.pathname );
				fetch( C.ajax, { method: 'POST', credentials: 'same-origin', body: b } );
			}, 1500 );
		} );
	}
	document.addEventListener( 'click', function ( e ) {
		var a = e.target.closest ? e.target.closest( '#wpadminbar .velox-bar-cache > a' ) : null;
		if ( ! a || busy || e.ctrlKey || e.metaKey || e.shiftKey ) { return; }
		var which = ( a.href.match( /[?&]which=([a-z0-9_-]+)/ ) || [] )[1];
		if ( ! which ) { return; }
		e.preventDefault();
		busy = true;
		back = document.activeElement;
		show( 'loading', C.i18n.working );
		var body = new FormData();
		body.append( 'action', 'velox' );
		body.append( 'do', 'clear_cache' );
		body.append( 'nonce', C.nonce );
		body.append( 'which', which );
		body.append( 'url', location.href );
		var started = Date.now();
		fetch( C.ajax, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( j ) {
				var d = ( j && j.data ) || {};
				return { ok: !! ( j && j.success ), msg: d.message || '' };
			}, function () { return { ok: false, msg: C.i18n.neterr }; } )
			.then( function ( res ) {
				// Keep the loader up long enough to be seen, so a fast purge
				// doesn't look like nothing happened.
				setTimeout( function () {
					busy = false;
					show( res.ok ? 'done' : 'error', res.ok ? C.i18n.done : C.i18n.failed, res.msg );
				}, Math.max( 0, 500 - ( Date.now() - started ) ) );
			} );
	} );
}() );
</script>
		<?php
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
	/**
	 * @param string $which Cache target.
	 * @param string $url   Page to offer as the PageSpeed target. Defaults to the
	 *                      home page, which is all the admin screens can know; a
	 *                      purge from the front-end bar knows the actual page.
	 */
	public static function clear_cache( $which, $url = '' ) {
		$done    = array();
		$missing = array();

		$purge_wpfc = function () use ( &$done, &$missing ) {
			if ( function_exists( 'wpfc_clear_all_cache' ) ) {
				wpfc_clear_all_cache( true );
				$done[] = 'WP Fastest Cache';
			} elseif ( isset( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
				$GLOBALS['wp_fastest_cache']->deleteCache( true );
				$done[] = 'WP Fastest Cache';
			}
			// Not having WP Fastest Cache installed is normal — Velox has its own
			// page cache. Reporting it as something "skipped" made a successful purge
			// look like a failure.
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
				'measure' => array(
					'text'      => __( 'See the difference', 'velox' ),
					'onpage'    => admin_url( 'admin.php?page=velox-pagespeed' ),
					'onpageTxt' => __( 'Run the built-in check', 'velox' ),
					'external'  => 'https://pagespeed.web.dev/analysis?url=' . rawurlencode(
						( '' !== $url && wp_http_validate_url( $url ) ) ? $url : home_url( '/' )
					),
					'extTxt'    => __( 'Test on PageSpeed Insights', 'velox' ),
				),
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
			// Maintenance can be toggled from the admin bar on any screen, but the
			// prompt that asks what to do about indexing lived in a script that
			// only loaded on Velox pages — so it never appeared. Load a minimal
			// version wherever there is something pending.
			if ( class_exists( 'Velox_Utilities' ) && Velox_Utilities::maintenance_seo_pending() ) {
				wp_enqueue_style( 'velox-admin', VELOX_ASSETS . 'css/velox-admin.css', array(), VELOX_VERSION );
				wp_enqueue_script( 'velox-admin', VELOX_ASSETS . 'js/velox-admin.js', array(), VELOX_VERSION, true );
				wp_localize_script( 'velox-admin', 'VELOX', array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'velox_nonce' ),
					'i18n'    => Velox::js_dictionary(),
				) );
			}
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
			'i18n'        => Velox::js_dictionary(),
		) );
		// Shared translation helper for every Velox admin script (main + the
		// standalone editors). Reads the same dictionary and falls back to the
		// source string, so English is always safe.
		$dict_json = wp_json_encode( Velox::js_dictionary() );
		wp_add_inline_script(
			'velox-admin',
			'window.VELOX_I18N=' . $dict_json . ';window.veloxT=function(s){return (window.VELOX_I18N&&Object.prototype.hasOwnProperty.call(window.VELOX_I18N,s))?window.VELOX_I18N[s]:s;};',
			'before'
		);
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
		// Bootstrap Icons 1.11.3 (MIT), the same set as the Velox Builder editor.
		$paths = array(
			'home' => '<path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L2 8.207V13.5A1.5 1.5 0 0 0 3.5 15h9a1.5 1.5 0 0 0 1.5-1.5V8.207l.646.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293zM13 7.207V13.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V7.207l5-5z"/>',
			'image' => '<path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0"/> <path d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zm12 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1z"/>',
			'tag' => '<path d="M6 4.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0m-1 0a.5.5 0 1 0-1 0 .5.5 0 0 0 1 0"/> <path d="M2 1h4.586a1 1 0 0 1 .707.293l7 7a1 1 0 0 1 0 1.414l-4.586 4.586a1 1 0 0 1-1.414 0l-7-7A1 1 0 0 1 1 6.586V2a1 1 0 0 1 1-1m0 5.586 7 7L13.586 9l-7-7H2z"/>',
			'bolt' => '<path d="M11.251.068a.5.5 0 0 1 .227.58L9.677 6.5H13a.5.5 0 0 1 .364.843l-8 8.5a.5.5 0 0 1-.842-.49L6.323 9.5H3a.5.5 0 0 1-.364-.843l8-8.5a.5.5 0 0 1 .615-.09zM4.157 8.5H7a.5.5 0 0 1 .478.647L6.11 13.59l5.732-6.09H9a.5.5 0 0 1-.478-.647L9.89 2.41z"/>',
			'db' => '<path d="M4.318 2.687C5.234 2.271 6.536 2 8 2s2.766.27 3.682.687C12.644 3.125 13 3.627 13 4c0 .374-.356.875-1.318 1.313C10.766 5.729 9.464 6 8 6s-2.766-.27-3.682-.687C3.356 4.875 3 4.373 3 4c0-.374.356-.875 1.318-1.313M13 5.698V7c0 .374-.356.875-1.318 1.313C10.766 8.729 9.464 9 8 9s-2.766-.27-3.682-.687C3.356 7.875 3 7.373 3 7V5.698c.271.202.58.378.904.525C4.978 6.711 6.427 7 8 7s3.022-.289 4.096-.777A5 5 0 0 0 13 5.698M14 4c0-1.007-.875-1.755-1.904-2.223C11.022 1.289 9.573 1 8 1s-3.022.289-4.096.777C2.875 2.245 2 2.993 2 4v9c0 1.007.875 1.755 1.904 2.223C4.978 15.71 6.427 16 8 16s3.022-.289 4.096-.777C13.125 14.755 14 14.007 14 13zm-1 4.698V10c0 .374-.356.875-1.318 1.313C10.766 11.729 9.464 12 8 12s-2.766-.27-3.682-.687C3.356 10.875 3 10.373 3 10V8.698c.271.202.58.378.904.525C4.978 9.71 6.427 10 8 10s3.022-.289 4.096-.777A5 5 0 0 0 13 8.698m0 3V13c0 .374-.356.875-1.318 1.313C10.766 14.729 9.464 15 8 15s-2.766-.27-3.682-.687C3.356 13.875 3 13.373 3 13v-1.302c.271.202.58.378.904.525C4.978 12.71 6.427 13 8 13s3.022-.289 4.096-.777c.324-.147.633-.323.904-.525"/>',
			'search' => '<path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>',
			'gear' => '<path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492M5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0"/> <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52zm-2.633.283c.246-.835 1.428-.835 1.674 0l.094.319a1.873 1.873 0 0 0 2.693 1.115l.291-.16c.764-.415 1.6.42 1.184 1.185l-.159.292a1.873 1.873 0 0 0 1.116 2.692l.318.094c.835.246.835 1.428 0 1.674l-.319.094a1.873 1.873 0 0 0-1.115 2.693l.16.291c.415.764-.42 1.6-1.185 1.184l-.291-.159a1.873 1.873 0 0 0-2.693 1.116l-.094.318c-.246.835-1.428.835-1.674 0l-.094-.319a1.873 1.873 0 0 0-2.692-1.115l-.292.16c-.764.415-1.6-.42-1.184-1.185l.159-.291A1.873 1.873 0 0 0 1.945 8.93l-.319-.094c-.835-.246-.835-1.428 0-1.674l.319-.094A1.873 1.873 0 0 0 3.06 4.377l-.16-.292c-.415-.764.42-1.6 1.185-1.184l.292.159a1.873 1.873 0 0 0 2.692-1.115z"/>',
			'check' => '<path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425z"/>',
			'grid' => '<path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5zM2.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5zm6.5.5A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5zM1 10.5A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5zm6.5.5A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5z"/>',
			'file' => '<path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5z"/>',
			'copy' => '<path fill-rule="evenodd" d="M4 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1zM2 5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1h1v1a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1v1z"/>',
			'plug' => '<path d="M6 0a.5.5 0 0 1 .5.5V3h3V.5a.5.5 0 0 1 1 0V3h1a.5.5 0 0 1 .5.5v3A3.5 3.5 0 0 1 8.5 10c-.002.434-.01.845-.04 1.22-.041.514-.126 1.003-.317 1.424a2.08 2.08 0 0 1-.97 1.028C6.725 13.9 6.169 14 5.5 14c-.998 0-1.61.33-1.974.718A1.92 1.92 0 0 0 3 16H2c0-.616.232-1.367.797-1.968C3.374 13.42 4.261 13 5.5 13c.581 0 .962-.088 1.218-.219.241-.123.4-.3.514-.55.121-.266.193-.621.23-1.09.027-.34.035-.718.037-1.141A3.5 3.5 0 0 1 4 6.5v-3a.5.5 0 0 1 .5-.5h1V.5A.5.5 0 0 1 6 0M5 4v2.5A2.5 2.5 0 0 0 7.5 9h1A2.5 2.5 0 0 0 11 6.5V4z"/>',
			'redirect' => '<path d="M7 7V1.414a1 1 0 0 1 2 0V2h5a1 1 0 0 1 .8.4l.975 1.3a.5.5 0 0 1 0 .6L14.8 5.6a1 1 0 0 1-.8.4H9v10H7v-5H2a1 1 0 0 1-.8-.4L.225 9.3a.5.5 0 0 1 0-.6L1.2 7.4A1 1 0 0 1 2 7zm1 3V8H2l-.75 1L2 10zm0-5h6l.75-1L14 3H8z"/>',
			'mail' => '<path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1zm13 2.383-4.708 2.825L15 11.105zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741M1 11.105l4.708-2.897L1 5.383z"/>',
			'broom' => '<path d="M7.657 6.247c.11-.33.576-.33.686 0l.645 1.937a2.89 2.89 0 0 0 1.829 1.828l1.936.645c.33.11.33.576 0 .686l-1.937.645a2.89 2.89 0 0 0-1.828 1.829l-.645 1.936a.361.361 0 0 1-.686 0l-.645-1.937a2.89 2.89 0 0 0-1.828-1.828l-1.937-.645a.361.361 0 0 1 0-.686l1.937-.645a2.89 2.89 0 0 0 1.828-1.828zM3.794 1.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387A1.73 1.73 0 0 0 4.593 5.69l-.387 1.162a.217.217 0 0 1-.412 0L3.407 5.69A1.73 1.73 0 0 0 2.31 4.593l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387A1.73 1.73 0 0 0 3.407 2.31zM10.863.099a.145.145 0 0 1 .274 0l.258.774c.115.346.386.617.732.732l.774.258a.145.145 0 0 1 0 .274l-.774.258a1.16 1.16 0 0 0-.732.732l-.258.774a.145.145 0 0 1-.274 0l-.258-.774a1.16 1.16 0 0 0-.732-.732L9.1 2.137a.145.145 0 0 1 0-.274l.774-.258c.346-.115.617-.386.732-.732z"/>',
			'lock' => '<path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2m3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2M5 8h6a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1"/>',
			'globe' => '<path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m7.5-6.923c-.67.204-1.335.82-1.887 1.855q-.215.403-.395.872c.705.157 1.472.257 2.282.287zM4.249 3.539q.214-.577.481-1.078a7 7 0 0 1 .597-.933A7 7 0 0 0 3.051 3.05q.544.277 1.198.49zM3.509 7.5c.036-1.07.188-2.087.436-3.008a9 9 0 0 1-1.565-.667A6.96 6.96 0 0 0 1.018 7.5zm1.4-2.741a12.3 12.3 0 0 0-.4 2.741H7.5V5.091c-.91-.03-1.783-.145-2.591-.332M8.5 5.09V7.5h2.99a12.3 12.3 0 0 0-.399-2.741c-.808.187-1.681.301-2.591.332zM4.51 8.5c.035.987.176 1.914.399 2.741A13.6 13.6 0 0 1 7.5 10.91V8.5zm3.99 0v2.409c.91.03 1.783.145 2.591.332.223-.827.364-1.754.4-2.741zm-3.282 3.696q.18.469.395.872c.552 1.035 1.218 1.65 1.887 1.855V11.91c-.81.03-1.577.13-2.282.287zm.11 2.276a7 7 0 0 1-.598-.933 9 9 0 0 1-.481-1.079 8.4 8.4 0 0 0-1.198.49 7 7 0 0 0 2.276 1.522zm-1.383-2.964A13.4 13.4 0 0 1 3.508 8.5h-2.49a6.96 6.96 0 0 0 1.362 3.675c.47-.258.995-.482 1.565-.667m6.728 2.964a7 7 0 0 0 2.275-1.521 8.4 8.4 0 0 0-1.197-.49 9 9 0 0 1-.481 1.078 7 7 0 0 1-.597.933M8.5 11.909v3.014c.67-.204 1.335-.82 1.887-1.855q.216-.403.395-.872A12.6 12.6 0 0 0 8.5 11.91zm3.555-.401c.57.185 1.095.409 1.565.667A6.96 6.96 0 0 0 14.982 8.5h-2.49a13.4 13.4 0 0 1-.437 3.008M14.982 7.5a6.96 6.96 0 0 0-1.362-3.675c-.47.258-.995.482-1.565.667.248.92.4 1.938.437 3.008zM11.27 2.461q.266.502.482 1.078a8.4 8.4 0 0 0 1.196-.49 7 7 0 0 0-2.275-1.52c.218.283.418.597.597.932m-.488 1.343a8 8 0 0 0-.395-.872C9.835 1.897 9.17 1.282 8.5 1.077V4.09c.81-.03 1.577-.13 2.282-.287z"/>',
			'cone' => '<path d="m9.97 4.88.953 3.811C10.159 8.878 9.14 9 8 9s-2.158-.122-2.923-.309L6.03 4.88C6.635 4.957 7.3 5 8 5s1.365-.043 1.97-.12m-.245-.978L8.97.88C8.718-.13 7.282-.13 7.03.88L6.275 3.9C6.8 3.965 7.382 4 8 4s1.2-.036 1.725-.098m4.396 8.613a.5.5 0 0 1 .037.96l-6 2a.5.5 0 0 1-.316 0l-6-2a.5.5 0 0 1 .037-.96l2.391-.598.565-2.257c.862.212 1.964.339 3.165.339s2.303-.127 3.165-.339l.565 2.257z"/>',
			'list' => '<path fill-rule="evenodd" d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2"/>',
			'code' => '<path d="M10.478 1.647a.5.5 0 1 0-.956-.294l-4 13a.5.5 0 0 0 .956.294zM4.854 4.146a.5.5 0 0 1 0 .708L1.707 8l3.147 3.146a.5.5 0 0 1-.708.708l-3.5-3.5a.5.5 0 0 1 0-.708l3.5-3.5a.5.5 0 0 1 .708 0m6.292 0a.5.5 0 0 0 0 .708L14.293 8l-3.147 3.146a.5.5 0 0 0 .708.708l3.5-3.5a.5.5 0 0 0 0-.708l-3.5-3.5a.5.5 0 0 0-.708 0"/>',
			'cookie' => '<path d="M6 7.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0m4.5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3m-.5 3.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0"/> <path d="M8 0a7.96 7.96 0 0 0-4.075 1.114q-.245.102-.437.28A8 8 0 1 0 8 0m3.25 14.201a1.5 1.5 0 0 0-2.13.71A7 7 0 0 1 8 15a6.97 6.97 0 0 1-3.845-1.15 1.5 1.5 0 1 0-2.005-2.005A6.97 6.97 0 0 1 1 8c0-1.953.8-3.719 2.09-4.989a1.5 1.5 0 1 0 2.469-1.574A7 7 0 0 1 8 1c1.42 0 2.742.423 3.845 1.15a1.5 1.5 0 1 0 2.005 2.005A6.97 6.97 0 0 1 15 8c0 .596-.074 1.174-.214 1.727a1.5 1.5 0 1 0-1.025 2.25 7 7 0 0 1-2.51 2.224Z"/>',
			'package' => '<path d="M8.186 1.113a.5.5 0 0 0-.372 0L1.846 3.5l2.404.961L10.404 2zm3.564 1.426L5.596 5 8 5.961 14.154 3.5zm3.25 1.7-6.5 2.6v7.922l6.5-2.6V4.24zM7.5 14.762V6.838L1 4.239v7.923zM7.443.184a1.5 1.5 0 0 1 1.114 0l7.129 2.852A.5.5 0 0 1 16 3.5v8.662a1 1 0 0 1-.629.928l-7.185 2.874a.5.5 0 0 1-.372 0L.63 13.09a1 1 0 0 1-.63-.928V3.5a.5.5 0 0 1 .314-.464z"/>',
			'folder' => '<path d="M.54 3.87.5 3a2 2 0 0 1 2-2h3.672a2 2 0 0 1 1.414.586l.828.828A2 2 0 0 0 9.828 3h3.982a2 2 0 0 1 1.992 2.181l-.637 7A2 2 0 0 1 13.174 14H2.826a2 2 0 0 1-1.991-1.819l-.637-7a2 2 0 0 1 .342-1.31zM2.19 4a1 1 0 0 0-.996 1.09l.637 7a1 1 0 0 0 .995.91h10.348a1 1 0 0 0 .995-.91l.637-7A1 1 0 0 0 13.81 4zm4.69-1.707A1 1 0 0 0 6.172 2H2.5a1 1 0 0 0-1 .981l.006.139q.323-.119.684-.12h5.396z"/>',
			'pin' => '<path d="M9.828.722a.5.5 0 0 1 .354.146l4.95 4.95a.5.5 0 0 1 0 .707c-.48.48-1.072.588-1.503.588-.177 0-.335-.018-.46-.039l-3.134 3.134a6 6 0 0 1 .16 1.013c.046.702-.032 1.687-.72 2.375a.5.5 0 0 1-.707 0l-2.829-2.828-3.182 3.182c-.195.195-1.219.902-1.414.707s.512-1.22.707-1.414l3.182-3.182-2.828-2.829a.5.5 0 0 1 0-.707c.688-.688 1.673-.767 2.375-.72a6 6 0 0 1 1.013.16l3.134-3.133a3 3 0 0 1-.04-.461c0-.43.108-1.022.589-1.503a.5.5 0 0 1 .353-.146m.122 2.112v-.002zm0-.002v.002a.5.5 0 0 1-.122.51L6.293 6.878a.5.5 0 0 1-.511.12H5.78l-.014-.004a5 5 0 0 0-.288-.076 5 5 0 0 0-.765-.116c-.422-.028-.836.008-1.175.15l5.51 5.509c.141-.34.177-.753.149-1.175a5 5 0 0 0-.192-1.054l-.004-.013v-.001a.5.5 0 0 1 .12-.512l3.536-3.535a.5.5 0 0 1 .532-.115l.096.022c.087.017.208.034.344.034q.172.002.343-.04L9.927 2.028q-.042.172-.04.343a1.8 1.8 0 0 0 .062.46z"/>',
			'trash' => '<path d="M6.5 1h3a.5.5 0 0 1 .5.5v1H6v-1a.5.5 0 0 1 .5-.5M11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3A1.5 1.5 0 0 0 5 1.5v1H1.5a.5.5 0 0 0 0 1h.538l.853 10.66A2 2 0 0 0 4.885 16h6.23a2 2 0 0 0 1.994-1.84l.853-10.66h.538a.5.5 0 0 0 0-1zm1.958 1-.846 10.58a1 1 0 0 1-.997.92h-6.23a1 1 0 0 1-.997-.92L3.042 3.5zm-7.487 1a.5.5 0 0 1 .528.47l.5 8.5a.5.5 0 0 1-.998.06L5 5.03a.5.5 0 0 1 .47-.53Zm5.058 0a.5.5 0 0 1 .47.53l-.5 8.5a.5.5 0 1 1-.998-.06l.5-8.5a.5.5 0 0 1 .528-.47M8 4.5a.5.5 0 0 1 .5.5v8.5a.5.5 0 0 1-1 0V5a.5.5 0 0 1 .5-.5"/>',
			'shield' => '<path d="M5.338 1.59a61 61 0 0 0-2.837.856.48.48 0 0 0-.328.39c-.554 4.157.726 7.19 2.253 9.188a10.7 10.7 0 0 0 2.287 2.233c.346.244.652.42.893.533q.18.085.293.118a1 1 0 0 0 .101.025 1 1 0 0 0 .1-.025q.114-.034.294-.118c.24-.113.547-.29.893-.533a10.7 10.7 0 0 0 2.287-2.233c1.527-1.997 2.807-5.031 2.253-9.188a.48.48 0 0 0-.328-.39c-.651-.213-1.75-.56-2.837-.855C9.552 1.29 8.531 1.067 8 1.067c-.53 0-1.552.223-2.662.524zM5.072.56C6.157.265 7.31 0 8 0s1.843.265 2.928.56c1.11.3 2.229.655 2.887.87a1.54 1.54 0 0 1 1.044 1.262c.596 4.477-.787 7.795-2.465 9.99a11.8 11.8 0 0 1-2.517 2.453 7 7 0 0 1-1.048.625c-.28.132-.581.24-.829.24s-.548-.108-.829-.24a7 7 0 0 1-1.048-.625 11.8 11.8 0 0 1-2.517-2.453C1.928 10.487.545 7.169 1.141 2.692A1.54 1.54 0 0 1 2.185 1.43 63 63 0 0 1 5.072.56"/> <path d="M10.854 5.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 7.793l2.646-2.647a.5.5 0 0 1 .708 0"/>',
			'plus' => '<path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2"/>',
			'warning' => '<path d="M7.938 2.016A.13.13 0 0 1 8.002 2a.13.13 0 0 1 .063.016.15.15 0 0 1 .054.057l6.857 11.667c.036.06.035.124.002.183a.2.2 0 0 1-.054.06.1.1 0 0 1-.066.017H1.146a.1.1 0 0 1-.066-.017.2.2 0 0 1-.054-.06.18.18 0 0 1 .002-.183L7.884 2.073a.15.15 0 0 1 .054-.057m1.044-.45a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767z"/> <path d="M7.002 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0M7.1 5.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0z"/>',
		);
		$p = isset( $paths[ $name ] ) ? $paths[ $name ] : '';
		return '<svg class="velox-ic" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false">' . $p . '</svg>';
	}
}
