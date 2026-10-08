<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Velox page cache — a standalone, disk-based full-page cache.
 *
 * Generation happens in WordPress (output buffer on template_redirect).
 * Serving happens as early as possible through an advanced-cache.php drop-in,
 * which reads a tiny JSON config and serves a static file before plugins load.
 *
 * Built to be safe on the Oxygen + Cloudflare stack: anything dynamic, logged-in,
 * builder-related, WooCommerce cart/checkout, or query-string driven is skipped.
 */
class Velox_Cache {

	const IGNORED_QUERY = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'mc_cid', 'mc_eid', '_ga', 'ref' );

	/* --------------------------------------------------------------- paths */

	public static function dir() {
		$base = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
		return $base . '/cache/velox';
	}

	public static function enabled() {
		return (bool) Velox_Settings::get( 'cache_enable', false );
	}

	/** Build the on-disk path for a host + URI (+ mobile variant). Traversal-safe. */
	public static function path_for( $host, $uri, $mobile = false ) {
		$host = strtolower( preg_replace( '/[^a-z0-9.\-]/i', '', (string) $host ) );
		$path = parse_url( $uri, PHP_URL_PATH );
		$path = $path ? $path : '/';
		$path = str_replace( '..', '', $path );
		$path = preg_replace( '#[^a-zA-Z0-9/_\-]#', '', $path );
		$path = trim( $path, '/' );
		$rel  = $host . ( '' === $path ? '' : '/' . $path );
		$file = ( $mobile ? 'index-mobile' : 'index' ) . '.html';
		return self::dir() . '/' . $rel . '/' . $file;
	}

	/* ----------------------------------------------------------- lifecycle */

	public static function init() {
		if ( self::enabled() ) {
			// Serving: the advanced-cache.php drop-in serves before WordPress when it's
			// installed. When it can't be (e.g. wp-config.php isn't writable on locked-down
			// Plesk hosts), this fallback serves the cached page as early as a plugin can,
			// so the cache still works everywhere — just a touch later than the drop-in.
			if ( ! self::dropin_active() ) {
				add_action( 'plugins_loaded', array( __CLASS__, 'maybe_serve' ), -PHP_INT_MAX );
			}
			add_action( 'template_redirect', array( __CLASS__, 'maybe_buffer' ), 0 );
		}
		// Auto-purge on content changes (registered regardless, cheap no-ops when empty).
		add_action( 'save_post', array( __CLASS__, 'purge_post' ), 10, 1 );
		add_action( 'deleted_post', array( __CLASS__, 'purge_post' ), 10, 1 );
		add_action( 'wp_trash_post', array( __CLASS__, 'purge_post' ), 10, 1 );
		add_action( 'comment_post', array( __CLASS__, 'purge_by_comment' ), 10, 1 );
		add_action( 'edit_comment', array( __CLASS__, 'purge_by_comment' ), 10, 1 );
		add_action( 'switch_theme', array( __CLASS__, 'purge_all' ) );
		add_action( 'customize_save_after', array( __CLASS__, 'purge_all' ) );
		add_action( 'wp_update_nav_menu', array( __CLASS__, 'purge_all' ) );
		// Auto-warm: after a full purge we schedule a background rebuild so visitors
		// hit a warm cache instead of paying for the first regeneration themselves.
		add_action( 'velox_cache_preload', array( __CLASS__, 'preload' ) );

		// Oxygen stores page designs in post meta and its global styles/classes in
		// options — neither goes through save_post in a way that reaches every page
		// a template or class is used on, so watch those directly.
		add_action( 'added_post_meta', array( __CLASS__, 'purge_by_meta' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'purge_by_meta' ), 10, 3 );
		add_action( 'updated_option', array( __CLASS__, 'purge_by_option' ), 10, 1 );

		// A Velox update changes what pages are built with — start from a clean cache.
		add_action( 'init', array( __CLASS__, 'purge_on_version_change' ), 20 );
	}

	/** Post types whose content appears on many pages (templates, reusable parts). */
	private static $shared_types = array( 'ct_template', 'oxy_user_library', 'wp_template', 'wp_template_part', 'wp_block', 'wp_navigation' );

	/** Options holding site-wide design (Oxygen global styles, classes, colors…). */
	private static $design_options = array(
		'ct_global_settings', 'ct_components_classes', 'ct_custom_selectors', 'ct_style_sheets',
		'ct_style_sets', 'ct_style_folders', 'oxygen_vsb_global_colors', 'oxygen_vsb_element_presets',
		'oxygen_vsb_universal_css_cache', 'oxy_global_settings',
	);

	/** Oxygen page-design meta keys. */
	private static $design_meta = array( 'ct_builder_shortcodes', '_ct_builder_shortcodes', 'ct_builder_json', '_ct_builder_json', 'ct_other_template', 'ct_template_inner_content' );

	/** Purge once per request at most, however many triggers fire (Oxygen saves several at once). */
	private static $purged_all = false;

	public static function purge_by_meta( $meta_id, $post_id, $meta_key ) {
		if ( self::$purged_all || ! in_array( $meta_key, self::$design_meta, true ) ) {
			return;
		}
		self::purge_post( (int) $post_id );
	}

	public static function purge_by_option( $option ) {
		if ( ! self::$purged_all && in_array( $option, self::$design_options, true ) ) {
			self::purge_all();
		}
	}

	public static function purge_on_version_change() {
		if ( ! defined( 'VELOX_VERSION' ) || get_option( 'velox_cache_built_with' ) === VELOX_VERSION ) {
			return;
		}
		update_option( 'velox_cache_built_with', VELOX_VERSION, true );
		self::purge_all();
	}

	/**
	 * Did a settings save change anything that shapes front-end output? Admin-only
	 * keys (PageSpeed widget, language, mail, …) don't need a purge.
	 */
	public static function settings_affect_pages( array $before, array $after ) {
		$prefixes = array( 'perf_', 'webp_', 'image_', 'cookie_', 'seo_', 'cache_', 'module_', 'util_', 'cf_', 'avif_' );
		foreach ( $after as $key => $val ) {
			if ( array_key_exists( $key, $before ) && $before[ $key ] === $val ) {
				continue;
			}
			foreach ( $prefixes as $p ) {
				if ( 0 === strpos( (string) $key, $p ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/** Is the advanced-cache.php drop-in installed and wired (WP_CACHE on)? */
	public static function dropin_active() {
		$dropin = ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content' ) . '/advanced-cache.php';
		return is_file( $dropin )
			&& false !== strpos( (string) @file_get_contents( $dropin, false, null, 0, 200 ), 'Velox' )
			&& defined( 'WP_CACHE' ) && WP_CACHE;
	}

	/** Fallback serve (no drop-in). Cookie-based checks only — WP isn't loaded yet. */
	public static function maybe_serve() {
		if ( ! self::enabled() ) {
			return;
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( $_SERVER['REQUEST_METHOD'] ) : 'GET';
		if ( 'GET' !== $method ) {
			return;
		}
		if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_admin() ) {
			return;
		}
		if ( isset( $_GET['ct_builder'] ) || isset( $_GET['oxygen_iframe'] ) || isset( $_GET['ct_inner'] ) ) {
			return;
		}
		if ( ! self::query_is_ignorable() ) {
			return;
		}
		// Logged-in cookie check (can't use is_user_logged_in() this early).
		if ( ! Velox_Settings::get( 'cache_logged_in', false ) ) {
			foreach ( array_keys( $_COOKIE ) as $cn ) {
				if ( false !== strpos( $cn, 'wordpress_logged_in_' ) ) {
					return;
				}
			}
		}
		if ( self::has_excluded_cookie() ) {
			return;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
		if ( self::uri_excluded( $uri ) ) {
			return;
		}
		$file = self::path_for( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost', $uri, self::is_mobile() );
		if ( ! is_readable( $file ) ) {
			return; // miss — let WordPress render + generate
		}
		$ttl = (int) Velox_Settings::get( 'cache_ttl', 36000 );
		if ( $ttl > 0 && ( time() - filemtime( $file ) ) > $ttl ) {
			return; // stale
		}
		// Content negotiation.
		$accept = isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : '';
		$send   = $file;
		$enc    = '';
		if ( Velox_Settings::get( 'cache_gzip', true ) ) {
			if ( false !== strpos( $accept, 'br' ) && is_readable( $file . '.br' ) ) {
				$send = $file . '.br'; $enc = 'br';
			} elseif ( false !== strpos( $accept, 'gzip' ) && is_readable( $file . '.gz' ) ) {
				$send = $file . '.gz'; $enc = 'gzip';
			}
		}
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'X-Velox-Cache: HIT (fallback)' );
		if ( '' !== $enc ) {
			header( 'Content-Encoding: ' . $enc );
			header( 'Vary: Accept-Encoding' );
		}
		header( 'Content-Length: ' . filesize( $send ) );
		readfile( $send );
		exit;
	}

	/* -------------------------------------------------------- cacheability */

	/**
	 * Should the current request be cached/served? Defensive: every WP
	 * conditional is guarded so this can also be reasoned about in isolation.
	 */
	public static function is_cacheable() {
		if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
			return false;
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( $_SERVER['REQUEST_METHOD'] ) : 'GET';
		if ( 'GET' !== $method ) {
			return false;
		}
		if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'DOING_CRON' ) && DOING_CRON )
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI )
			|| ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
			return false;
		}
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return false;
		}
		// Oxygen builder / iframe — never cache the editor.
		if ( isset( $_GET['ct_builder'] ) || isset( $_GET['oxygen_iframe'] ) || isset( $_GET['ct_inner'] ) || defined( 'SHOW_CT_BUILDER' ) ) {
			return false;
		}
		// Query string: allow only known tracking params, otherwise bypass.
		if ( ! self::query_is_ignorable() ) {
			return false;
		}
		// Logged-in users (unless explicitly enabled).
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() && ! Velox_Settings::get( 'cache_logged_in', false ) ) {
			return false;
		}
		foreach ( array( 'is_feed', 'is_search', 'is_404', 'is_preview', 'is_trackback', 'is_robots', 'is_customize_preview' ) as $cond ) {
			if ( function_exists( $cond ) && call_user_func( $cond ) ) {
				return false;
			}
		}
		if ( function_exists( 'post_password_required' ) && is_singular() && post_password_required() ) {
			return false;
		}
		// WooCommerce dynamic pages.
		foreach ( array( 'is_cart', 'is_checkout', 'is_account_page' ) as $cond ) {
			if ( function_exists( $cond ) && call_user_func( $cond ) ) {
				return false;
			}
		}
		// Excluded cookies (carts, sessions, etc.).
		if ( self::has_excluded_cookie() ) {
			return false;
		}
		// User URL exclusions.
		if ( self::uri_excluded( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/' ) ) {
			return false;
		}
		/**
		 * Let a module veto caching for a request it knows is personal — a cart,
		 * a checkout, an account view. Returning true from this filter means
		 * "do not cache".
		 *
		 * @param bool $skip Whether to skip caching this request.
		 */
		if ( apply_filters( 'velox_cache_skip', false ) ) {
			return false;
		}
		return true;
	}

	public static function query_is_ignorable() {
		if ( empty( $_GET ) ) {
			return true;
		}
		foreach ( array_keys( $_GET ) as $k ) {
			if ( ! in_array( $k, self::IGNORED_QUERY, true ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Cookies that always mean "this page is personal". Shared by the PHP check
	 * and the drop-in config so the two can never disagree.
	 */
	public static function builtin_cookie_patterns() {
		return array(
			'comment_author_', 'wp-postpass_',
			'woocommerce_items_in_cart', 'woocommerce_cart_hash', 'wp_woocommerce_session_',
			'edd_items_in_cart', 'velox_cart',
		);
	}

	public static function has_excluded_cookie() {
		if ( empty( $_COOKIE ) ) {
			return false;
		}
		// A visitor holding a cart must never be served a cached page: the cart,
		// checkout and account views are personal. Velox Shop's own cookie sits
		// alongside the WooCommerce and EDD ones for exactly that reason.
		$patterns = self::builtin_cookie_patterns();
		$extra = (string) Velox_Settings::get( 'cache_exclude_cookies', '' );
		foreach ( array_filter( array_map( 'trim', explode( "\n", $extra ) ) ) as $p ) {
			$patterns[] = $p;
		}
		foreach ( array_keys( $_COOKIE ) as $name ) {
			foreach ( $patterns as $p ) {
				if ( '' !== $p && false !== strpos( $name, $p ) ) {
					return true;
				}
			}
		}
		return false;
	}

	public static function uri_excluded( $uri ) {
		$path = parse_url( $uri, PHP_URL_PATH );
		$path = $path ? $path : '/';
		$list = (string) Velox_Settings::get( 'cache_exclude_urls', '' );
		foreach ( array_filter( array_map( 'trim', explode( "\n", $list ) ) ) as $rule ) {
			$rule = trim( $rule );
			if ( '' === $rule ) {
				continue;
			}
			// Trailing * = prefix match; (.*) or regex-ish handled as wildcard.
			$regex = '#^' . str_replace( '\*', '.*', preg_quote( $rule, '#' ) ) . '$#i';
			if ( preg_match( $regex, $path ) ) {
				return true;
			}
		}
		return false;
	}

	public static function is_mobile() {
		if ( ! Velox_Settings::get( 'cache_mobile_separate', false ) ) {
			return false;
		}
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
		return (bool) preg_match( '/Mobile|Android|iPhone|iPod|BlackBerry|IEMobile|Opera Mini/i', $ua );
	}

	/* --------------------------------------------------------- generation */

	public static function maybe_buffer() {
		if ( ! self::is_cacheable() ) {
			return;
		}
		ob_start( array( __CLASS__, 'finalize' ) );
	}

	public static function finalize( $html ) {
		if ( strlen( $html ) < 255 ) {
			return $html; // too small / likely a redirect or empty
		}
		if ( function_exists( 'http_response_code' ) && 200 !== http_response_code() ) {
			return $html;
		}
		if ( ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) || ! self::is_cacheable() ) {
			return $html;
		}
		// Don't cache obvious error/login output.
		if ( false !== stripos( $html, '<body class="error404' ) ) {
			return $html;
		}
		if ( Velox_Settings::get( 'perf_minify_html', false ) ) {
			$html = self::minify_html( $html );
		}
		$host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost';
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
		$stamp = '<!-- Velox cache · ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC -->';
		self::write( self::path_for( $host, $uri, self::is_mobile() ), $html . "\n" . $stamp );
		return $html;
	}

	/**
	 * Conservative, fail-open HTML minifier. Protects script/style/pre/textarea/code
	 * and IE conditional comments, drops normal comments, and collapses inter-tag
	 * whitespace to a single space (so inline spacing is preserved). On any regex
	 * failure it returns the original HTML untouched — it can never blank a page.
	 */
	public static function minify_html( $html ) {
		if ( ! is_string( $html ) || '' === $html || false === stripos( $html, '<html' ) ) {
			return $html;
		}
		$orig      = $html;
		$protected = array();
		$html = preg_replace_callback(
			'#<(pre|textarea|script|style|code)\b[^>]*>.*?</\1>|<!--\[if[\s\S]*?<!\[endif\]-->#is',
			function ( $m ) use ( &$protected ) {
				$key = '<!--VLXP' . count( $protected ) . '-->';
				$protected[ $key ] = $m[0];
				return $key;
			},
			$html
		);
		if ( null === $html ) {
			return $orig;
		}
		$remove_comments = Velox_Settings::get( 'perf_html_remove_comments', true );
		$collapse_ws     = Velox_Settings::get( 'perf_html_collapse_whitespace', true );
		// Drop normal HTML comments (the protected placeholders survive).
		if ( $remove_comments ) {
			$html = preg_replace( '/<!--(?!VLXP)[\s\S]*?-->/', '', $html );
			if ( null === $html ) {
				return $orig;
			}
		}
		// Collapse whitespace that sits purely between tags, keeping one space so
		// inline word spacing is never lost.
		if ( $collapse_ws ) {
			$html = preg_replace( '/>\s+</', '> <', $html );
			if ( null === $html ) {
				return $orig;
			}
		}
		$html = trim( $html );
		// Restore protected blocks.
		if ( $protected ) {
			$html = strtr( $html, $protected );
		}
		return ( '' === $html ) ? $orig : $html;
	}

	public static function write( $file, $html ) {
		$dir = dirname( $file );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$ok = (bool) @file_put_contents( $file, $html, LOCK_EX );
		if ( $ok && Velox_Settings::get( 'cache_gzip', true ) ) {
			if ( function_exists( 'gzencode' ) ) {
				@file_put_contents( $file . '.gz', gzencode( $html, 6 ), LOCK_EX );
			}
			if ( function_exists( 'brotli_compress' ) ) {
				@file_put_contents( $file . '.br', brotli_compress( $html, 5 ), LOCK_EX );
			}
		}
		return $ok;
	}

	/* -------------------------------------------------------------- purge */

	public static function purge_all() {
		self::$purged_all = true;
		self::rrmdir( self::dir(), false );
		self::write_config(); // keep the drop-in's config in place after a full purge
		// Velox never stacks a second cache on top of yours — so also nudge the common
		// third-party page caches, otherwise a change won't show until THEY expire.
		if ( function_exists( 'wpfc_clear_all_cache' ) ) { wpfc_clear_all_cache( true ); } // WP Fastest Cache
		if ( function_exists( 'rocket_clean_domain' ) ) { rocket_clean_domain(); }          // WP Rocket
		if ( function_exists( 'w3tc_flush_all' ) ) { w3tc_flush_all(); }                     // W3 Total Cache
		if ( function_exists( 'wp_cache_clear_cache' ) ) { wp_cache_clear_cache(); }         // WP Super Cache
		if ( has_action( 'litespeed_purge_all' ) ) { do_action( 'litespeed_purge_all' ); }   // LiteSpeed Cache
		do_action( 'velox_purge_all' );
		// Warm the cache back up in the background (debounced — rapid purges collapse
		// into one), so the next visitor doesn't pay for the rebuild. Opt-out via the
		// "Auto-warm cache after purge" setting.
		if ( self::enabled()
			&& Velox_Settings::get( 'cache_auto_preload', true )
			&& function_exists( 'wp_schedule_single_event' )
			&& ! wp_next_scheduled( 'velox_cache_preload' ) ) {
			wp_schedule_single_event( time() + 12, 'velox_cache_preload' );
		}
		return true;
	}

	public static function purge_url( $url ) {
		$host = parse_url( $url, PHP_URL_HOST );
		$host = $host ? $host : ( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost' );
		foreach ( array( false, true ) as $mobile ) {
			$f = self::path_for( $host, $url, $mobile );
			@unlink( $f );
			@unlink( $f . '.gz' );
			@unlink( $f . '.br' );
		}
	}

	public static function purge_post( $post_id ) {
		// A template / reusable part is shown on many pages — drop them all.
		if ( function_exists( 'get_post_type' ) && in_array( get_post_type( $post_id ), self::$shared_types, true ) ) {
			if ( ! self::$purged_all ) {
				self::purge_all();
			}
			return;
		}
		if ( function_exists( 'get_permalink' ) ) {
			$link = get_permalink( $post_id );
			if ( $link ) {
				self::purge_url( $link );
			}
		}
		if ( function_exists( 'home_url' ) ) {
			self::purge_url( home_url( '/' ) );
		}
	}

	public static function purge_by_comment( $comment_id ) {
		if ( function_exists( 'get_comment' ) ) {
			$c = get_comment( $comment_id );
			if ( $c && ! empty( $c->comment_post_ID ) ) {
				self::purge_post( (int) $c->comment_post_ID );
			}
		}
	}

	/* --------------------------------------------------------- drop-in/io */

	/** Write the JSON config the advanced-cache.php drop-in reads. */
	public static function write_config() {
		$cfg = array(
			'enabled'         => self::enabled(),
			'ttl'             => (int) Velox_Settings::get( 'cache_ttl', 36000 ),
			'logged_in'       => (bool) Velox_Settings::get( 'cache_logged_in', false ),
			'mobile_separate' => (bool) Velox_Settings::get( 'cache_mobile_separate', false ),
			'gzip'            => (bool) Velox_Settings::get( 'cache_gzip', true ),
			'exclude_urls'    => array_filter( array_map( 'trim', explode( "\n", (string) Velox_Settings::get( 'cache_exclude_urls', '' ) ) ) ),
			// The drop-in serves before WordPress loads, so it cannot call
			// has_excluded_cookie(). The built-in patterns therefore have to be
			// written into its config alongside the user's own — without this a
			// visitor holding a cart is served a cached page by the drop-in no
			// matter what the PHP-side check says.
			'exclude_cookies' => array_values( array_unique( array_merge(
				self::builtin_cookie_patterns(),
				array_filter( array_map( 'trim', explode( "\n", (string) Velox_Settings::get( 'cache_exclude_cookies', '' ) ) ) )
			) ) ),
		);
		$dir = self::dir();
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return (bool) @file_put_contents( $dir . '/_config.json', wp_json_encode( $cfg ), LOCK_EX );
	}

	/** Install advanced-cache.php drop-in + try to set WP_CACHE. Returns status array. */
	public static function install_dropin() {
		self::write_config();
		$src  = VELOX_PATH . 'includes/advanced-cache.php';
		$dest = ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content' ) . '/advanced-cache.php';
		$dropin_ok = is_readable( $src ) ? (bool) @copy( $src, $dest ) : false;
		$wpcache_ok = self::set_wp_cache( true );
		return array(
			'dropin'   => $dropin_ok,
			'wp_cache' => $wpcache_ok,
			'manual'   => ( ! $wpcache_ok ) ? "define( 'WP_CACHE', true );" : '',
		);
	}

	public static function remove_dropin() {
		$dest = ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content' ) . '/advanced-cache.php';
		if ( is_file( $dest ) ) {
			$head = (string) @file_get_contents( $dest, false, null, 0, 200 );
			if ( false !== strpos( $head, 'Velox' ) ) {
				@unlink( $dest );
			}
		}
		self::set_wp_cache( false );
		self::purge_all();
	}

	/** Best-effort edit of wp-config.php to define WP_CACHE. */
	public static function set_wp_cache( $on ) {
		$file = ABSPATH . 'wp-config.php';
		if ( ! is_writable( $file ) ) {
			return false;
		}
		$src = file_get_contents( $file );
		if ( false === $src ) {
			return false;
		}
		$line = "define( 'WP_CACHE', " . ( $on ? 'true' : 'false' ) . " ); // Velox";
		if ( preg_match( "/define\(\s*'WP_CACHE'.*?;/", $src ) ) {
			$src = preg_replace( "/define\(\s*'WP_CACHE'.*?;/", $line, $src, 1 );
		} else {
			$src = preg_replace( '/^(<\?php\s*)/', "$1\n" . $line . "\n", $src, 1 );
		}
		return (bool) @file_put_contents( $file, $src, LOCK_EX );
	}

	/* -------------------------------------------------------------- stats */

	public static function stats() {
		$dir = self::dir();
		$pages = 0; $bytes = 0;
		if ( is_dir( $dir ) ) {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $f ) {
				if ( $f->isFile() ) {
					$name = $f->getFilename();
					if ( 'index.html' === $name || 'index-mobile.html' === $name ) {
						$pages++;
					}
					$bytes += $f->getSize();
				}
			}
		}
		return array( 'pages' => $pages, 'bytes' => $bytes, 'dropin_active' => self::dropin_active(), 'serving' => self::enabled() );
	}

	/* ------------------------------------------------------------ preload */

	/** Collect URLs to warm: home + published pages/posts (capped). */
	public static function preload_urls( $limit = 50 ) {
		$urls = array();
		if ( function_exists( 'home_url' ) ) {
			$urls[] = home_url( '/' );
		}
		if ( function_exists( 'get_posts' ) ) {
			$ids = get_posts( array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'orderby'        => 'modified',
				'order'          => 'DESC',
			) );
			foreach ( $ids as $id ) {
				$u = get_permalink( $id );
				if ( $u ) {
					$urls[] = $u;
				}
			}
		}
		return array_values( array_unique( $urls ) );
	}

	/** Warm a batch of URLs with non-blocking-ish loopback GETs. */
	public static function preload( $limit = 30 ) {
		if ( ! function_exists( 'wp_remote_get' ) ) {
			return array( 'warmed' => 0 );
		}
		$warmed = 0;
		foreach ( self::preload_urls( $limit ) as $url ) {
			$r = wp_remote_get( $url, array( 'timeout' => 8, 'blocking' => true, 'headers' => array( 'X-Velox-Preload' => '1' ) ) );
			if ( ! is_wp_error( $r ) ) {
				$warmed++;
			}
		}
		return array( 'warmed' => $warmed );
	}

	/* ------------------------------------------------------------ helpers */

	private static function rrmdir( $dir, $remove_self = true ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = @scandir( $dir );
		if ( false === $items ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				self::rrmdir( $path, true );
			} else {
				@unlink( $path );
			}
		}
		if ( $remove_self ) {
			@rmdir( $dir );
		}
	}
}
