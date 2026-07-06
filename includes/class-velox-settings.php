<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central settings store. Everything lives in one option array (velox_settings)
 * so reads are a single DB hit. Feature checks go through Velox_Settings::enabled().
 */
class Velox_Settings {

	const OPTION = 'velox_settings';

	private static $cache = null;

	/**
	 * The full default configuration. Anything that changes how a site renders is
	 * OFF by default so a fresh install is invisible until the user opts in. A few
	 * zero-risk cleanups (emoji script, head junk) default ON.
	 */
	public static function defaults() {
		return array(
			// ---- Master module switches (all on by default) ----
			'module_images'      => true,
			'module_media'       => true,
			'module_performance' => true,
			'module_database'    => true,
			'module_seo'         => true,

			// ---- SEO ----
			'seo_robots_enable'  => true,
			'seo_og_enable'      => true,
			'seo_robots_content' => '', // empty = use the recommended default template
			'seo_sitemap_enable' => true,

			// ---- Builder-aware setup ----
			'builder'            => '',     // chosen page builder id ('' = wizard not run)
			'wizard_done'        => false,  // has the setup wizard been completed/dismissed

			// ---- Utilities (each tool off by default) ----
			'util_svg_upload'    => false,
			'util_duplicate'     => false,
			'util_maintenance'         => false,
			'util_maintenance_title'   => 'We\'ll be right back',
			'util_maintenance_message' => 'The site is undergoing a little maintenance. Please check back shortly.',
			'util_maintenance_logo'    => '',          // empty → bundled Velox mark
			'util_maintenance_bg'      => '#0c0e17',
			'util_maintenance_text'    => '#e9edf5',
			'util_maintenance_accent'  => '#2ab7f1',
			'util_maintenance_bgimage' => '',
			'util_maintenance_btn_text'=> '',
			'util_maintenance_btn_url' => '',
			'util_maintenance_brand'   => '',     // footer text; empty = no footer line
			'util_maintenance_anim'    => 'bar',  // bar | pulse | dots | spinner | lottie | none
			'util_maintenance_lottie'  => '',          // Lottie .json/.lottie URL (when anim = lottie)
			'util_login_slug'          => '',     // empty = default wp-login; set a slug to move it
			'util_redirects_log_404'   => true,
			'util_activity'            => false,
			'util_scripts'             => false,
			'util_mail'                => false,
			'util_installer'           => false,
			'util_redirects'           => false,
			'util_unusedmedia'         => false,
			'util_loginurl'            => false,
			'util_snippets'            => false,
			'util_cookies'             => false,
			'util_october'             => false,
			'util_backup'              => false,
			'backup_schedule'          => 'off',    // off | daily | weekly | monthly
			'backup_schedule_what'     => 'both',   // db | files | both
			'backup_keep'              => 5,        // retention: keep newest N

			// ---- Cookie banner ----
			'cookie_layout'            => 'bar-bottom',   // bar-bottom | box-bl | box-br | modal-center
			'cookie_cat_analytics'     => true,
			'cookie_cat_marketing'     => true,
			'cookie_consent_mode'      => true,           // Google Consent Mode v2
			'cookie_ga_id'             => '',             // G-XXXX (GA4) or GTM-XXXX
			'cookie_reconsent_days'    => 180,
			'cookie_heading'           => 'We value your privacy',
			'cookie_body'              => 'We use cookies to improve your experience, analyse traffic and for marketing. You can accept all, reject non-essential, or choose what to allow.',
			'cookie_btn_accept'        => 'Accept all',
			'cookie_btn_reject'        => 'Reject non-essential',
			'cookie_btn_settings'      => 'Preferences',
			'cookie_small_text'        => '',
			'cookie_link1_label'       => 'Privacy Policy',
			'cookie_link1_url'         => '/datenschutz/',
			'cookie_link2_label'       => 'Imprint',
			'cookie_link2_url'         => '/impressum/',
			'cookie_logo'              => '',
			'cookie_bg'                => '#ffffff',
			'cookie_text'              => '#1d1d1f',
			'cookie_accent'            => '#2ab7f1',
			'cookie_accent_text'       => '#ffffff',
			'cookie_btn2_bg'           => '#f1f2f5',
			'cookie_btn2_text'         => '#1d1d1f',
			'cookie_border_color'      => '#e6e7eb',
			'cookie_border_width'      => 1,
			'cookie_radius'            => 16,
			'cookie_shadow'            => true,
			'cookie_overlay'           => false,
			'cookie_offset'            => 24,
			'cookie_layout_mobile'     => 'inherit',  // inherit | bar-bottom | bar-top | box-bl | box-br | modal-center
			'cookie_width'             => 460,        // px, floating box / modal width
			'cookie_font_size'         => 14,         // px base
			'cookie_btn_full_mobile'   => true,       // stack buttons full-width on mobile
			// --- Oxygen-style structural layout controls (override preset defaults) ---
			'cookie_layout_mode'       => 'preset',   // preset | custom — custom unlocks the controls below
			'cookie_display'           => 'flex',     // flex | grid | block
			'cookie_direction'         => 'row',      // row | column  (flex only)
			'cookie_align'             => 'center',   // align-items: flex-start | center | flex-end | stretch
			'cookie_justify'           => 'space-between', // justify-content
			'cookie_gap'               => 24,         // px gap between content + actions
			'cookie_grid_cols'         => 2,          // grid template columns (grid only)
			'cookie_pad_y'             => 22,         // px vertical padding
			'cookie_pad_x'             => 24,         // px horizontal padding
			'cookie_margin'            => 0,          // px outer margin around the box

			// ---- Cookie banner: dynamic buttons + advanced styling ----
			'cookie_buttons'           => '[{"id":"b1","label":"Accept all","action":"accept","element":"button","url":"","variant":"primary"},{"id":"b2","label":"Reject non-essential","action":"reject","element":"button","url":"","variant":"secondary"},{"id":"b3","label":"Preferences","action":"preferences","element":"button","url":"","variant":"secondary"}]',
			'cookie_custom_css'        => '',
			'cookie_heading_size'      => 0,          // 0 = inherit
			'cookie_heading_weight'    => 0,
			'cookie_heading_color'     => '',
			'cookie_body_size'         => 0,
			'cookie_body_color'        => '',
			'cookie_link_color'        => '',
			'cookie_link_underline'    => true,
			'cookie_btn_gap'           => 10,
			'cookie_btn_font_size'     => 14,
			'cookie_btn_font_weight'   => 600,
			'cookie_backdrop_blur'     => 0,
			'cookie_overlay_color'     => 'rgba(10,12,20,.45)',
			'cookie_max_height'        => 0,
			'cookie_z_index'           => 0,

			// ---- Page cache ----
			'cache_enable'          => false,
			'cache_ttl'             => 36000,   // seconds (10h)
			'cache_logged_in'       => false,
			'cache_mobile_separate' => false,
			'cache_gzip'            => true,
			'cache_auto_preload'    => true,   // warm the cache in the background after a full purge
			'cache_exclude_urls'    => '',
			'cache_exclude_cookies' => '',

			// ---- Mail & forms ----
			'mail_smtp_enabled'   => false,
			'mail_smtp_host'      => '',
			'mail_smtp_port'      => 587,
			'mail_smtp_secure'    => 'tls',  // tls | ssl | none
			'mail_smtp_user'      => '',
			'mail_smtp_pass'      => '',
			'mail_smtp_from'      => '',
			'mail_smtp_from_name' => '',
			'mail_connections'    => '',     // JSON: [ {id,label,host,port,secure,user,pass,from,from_name} ]
			'mail_routes'         => '',     // JSON: [ {match,value,conn} ]
			'mail_primary'        => '',     // connection id
			'mail_fallback'       => '',     // connection id ('' = none)
			'mail_migrated_v2'    => false,  // legacy single-connection → connections[] migration flag
			'mail_captcha_enabled'  => false, // global gate: when off, forms cannot use CAPTCHA
			'mail_captcha_provider' => 'turnstile', // turnstile | recaptcha
			'mail_captcha_site'   => '',
			'mail_captcha_secret' => '',
			'mail_log'            => true,

			// ---- Image optimizer / WebP ----
			'webp_quality'       => 80,
			'webp_keep_original' => true,
			'webp_auto_convert'  => false,
			'webp_convert_sizes' => true,
			'webp_serve_rewrite' => true,
			'image_engine'       => 'auto', // auto | imagick | gd
			'image_webp'         => true,   // generate WebP output
			'image_avif'         => false, // also generate AVIF twins + serve them to capable browsers
			'image_lossless'     => false, // lossless WebP/AVIF (Imagick) — bigger files, perfect quality
			'image_keep_exif'    => false, // strip camera/GPS metadata by default for smaller files
			'image_max_width'    => 2560,  // downscale oversized uploads/conversions; 0 = off
			'image_replace'      => true,  // replace originals with WebP in the media library (in-place)
			'image_comparison'   => true,  // show the old/new comparator in the Images tab

			// ---- Performance · General ----
			'perf_disable_emojis'        => true,
			'perf_minify_html'           => false,
			'perf_html_remove_comments'    => true,
			'perf_html_collapse_whitespace'=> true,
			'perf_disable_embeds'        => false,
			'perf_remove_query_strings'  => false,
			'perf_disable_xmlrpc'        => true,
			'perf_disable_self_pingbacks'=> true,
			'perf_clean_head'            => true,
			'perf_disable_dashicons'     => true,
			'perf_remove_jquery_migrate' => false,
			'perf_disable_comments'      => false,
			'perf_disable_rss'           => false,
			'perf_disable_app_passwords' => false,
			'perf_heartbeat'             => 'default', // default | slow | off
			'perf_revisions_keep'        => 5,         // 0 = unlimited
			'perf_autosave_interval'     => 60,        // seconds; 0 = WP default (60)

			// ---- Performance · master ----
			'perf_risky_mode'            => false, // reveals the "might break" settings in the UI

			// ---- Performance · CSS ----
			'perf_disable_block_css'     => false, // wp-block-library (safe on Oxygen, no Gutenberg front end)
			'perf_disable_global_styles' => false, // global-styles + classic-theme-styles
			'perf_disable_woo_css'       => false, // WooCommerce CSS off non-shop pages
			'perf_optimize_css_delivery' => false, // load CSS non-render-blocking (async)
			'perf_critical_css'          => '',    // above-the-fold CSS to inline in <head>
			'perf_css_async_exclude'     => "oxygen\nadmin-bar", // stylesheets that stay render-blocking
			'perf_remove_unused_css'     => false, // local used-CSS trimming (best-effort, no cloud)
			'perf_rucss_engine'          => 'auto',  // auto | local | cloudflare
			'cf_account_id'              => '',    // Cloudflare account ID (for Browser Run)
			'cf_api_token'               => '',    // Cloudflare API token with Browser Rendering permission
			'perf_rucss_urls'            => "/",   // page paths to scan, one per line
			'perf_rucss_safelist'        => ".ct-\n.oxy-\n.wp-\n.menu\n.active\n.open\n.show\n.is-\n.has-", // never strip these
			'perf_rucss_exclude'         => '',    // stylesheet URL fragments to leave untouched

			// ---- Performance · JavaScript ----
			'perf_defer_scripts'         => false,
			'perf_defer_exclude'         => "jquery\noxygen\nfluentform",
			'perf_delay_js'              => false, // delay until user interaction (big win, opt-in)
			'perf_delay_js_exclude'      => "oxygen\nfluentform",
			'perf_delay_js_timeout'      => 8,     // fallback: run delayed JS after N seconds even with no interaction
			'perf_disable_woo_fragments' => false, // cart-fragments off non-woo pages

			// ---- Performance · Images (front-end) ----
			'perf_add_image_dimensions'  => true,  // width/height to cut CLS
			'perf_lazyload_iframes'      => true,
			'perf_lazy_skip_count'       => 2,    // keep first N images eager (above-the-fold)
			'perf_fetchpriority_lcp'     => true,  // fetchpriority=high on the hero/featured image
			'perf_youtube_facade'        => true,  // replace YouTube iframes with a click-to-load thumbnail
			'perf_preload_lcp'           => '',    // URL of the hero/LCP image
			'perf_content_visibility'    => false, // content-visibility:auto lazy-render (risky)
			'perf_content_visibility_selector' => '', // CSS selector(s) to lazy-render, one per line

			// ---- Performance · Fonts ----
			'perf_fonts_preconnect'      => true,
			'perf_fonts_display_swap'    => true,
			'perf_local_fonts'           => false, // host Google Fonts locally (OMGF-style)
			'perf_preload_fonts'         => '',    // one font URL per line
			'perf_font_block'            => '',    // block fonts by family name or URL, one per line
			'perf_system_fonts'          => false, // skip web fonts, use the system stack

			// ---- Performance · Preload / Network ----
			'perf_dns_prefetch'          => "https://cdnjs.cloudflare.com\nhttps://fonts.gstatic.com",
			'perf_preconnect'            => '',    // one origin per line
			'perf_speculative_loading'   => 'off', // off | conservative | moderate
			'perf_preload_assets'        => '',    // one URL per line (css/js/img)

			// ---- Performance · CDN ----
			'perf_cdn_enable'            => false, // rewrite static asset URLs to a CDN host
			'perf_cdn_url'               => '',    // e.g. https://cdn.example.com
			'perf_cdn_exclude'           => '',    // URL fragments to keep on the origin, one per line

			// ---- Database ----
			'db_schedule_cleanup'        => false,

			// ---- PageSpeed (dashboard live status) ----
			'ps_enable'                  => false,       // run PageSpeed checks + show the widget
			'ps_api_key'                 => '',          // Google PageSpeed Insights API key
			'ps_url'                     => '',          // URL to test (blank = site home)
			'ps_strategy'                => 'mobile',    // mobile | desktop
			'ps_interval'                => 'daily',     // hourly | twicedaily | daily
			'ps_show_metrics'            => true,        // show the Core Web Vitals chips
			'ps_show_issues'             => true,        // show the top opportunities to fix

			// ---- Updater ----
			'gh_token'                   => '',

			// ---- Housekeeping ----
			'keep_data_on_uninstall'     => false, // don't wipe settings/tables when the plugin is deleted
		);
	}

	/** Performance keys grouped by sub-section, used by the Performance view + hide logic. */
	public static function perf_sections() {
		return array(
			'cache' => array(
				'label' => 'Cache',
				'keys'  => array( 'cache_enable', 'cache_ttl', 'cache_logged_in', 'cache_mobile_separate', 'cache_gzip', 'cache_auto_preload', 'cache_exclude_urls', 'cache_exclude_cookies' ),
			),
			'general' => array(
				'label' => 'General',
				'keys'  => array(
					'perf_disable_emojis', 'perf_disable_embeds', 'perf_remove_query_strings',
					'perf_disable_xmlrpc', 'perf_disable_self_pingbacks', 'perf_clean_head',
					'perf_disable_dashicons', 'perf_remove_jquery_migrate', 'perf_disable_comments',
					'perf_disable_rss', 'perf_disable_app_passwords',
				),
			),
			'html' => array(
				'label' => 'HTML',
				'keys'  => array( 'perf_minify_html', 'perf_html_remove_comments', 'perf_html_collapse_whitespace' ),
			),
			'css' => array(
				'label' => 'CSS',
				'keys'  => array( 'perf_disable_block_css', 'perf_disable_global_styles', 'perf_disable_woo_css', 'perf_optimize_css_delivery', 'perf_critical_css', 'perf_css_async_exclude', 'perf_remove_unused_css', 'perf_rucss_engine', 'cf_account_id', 'cf_api_token', 'perf_rucss_urls', 'perf_rucss_safelist', 'perf_rucss_exclude' ),
			),
			'js' => array(
				'label' => 'JavaScript',
				'keys'  => array( 'perf_defer_scripts', 'perf_defer_exclude', 'perf_delay_js', 'perf_delay_js_exclude', 'perf_delay_js_timeout', 'perf_disable_woo_fragments' ),
			),
			'images' => array(
				'label' => 'Images',
				'keys'  => array( 'perf_add_image_dimensions', 'perf_fetchpriority_lcp', 'perf_lazyload_iframes', 'perf_lazy_skip_count', 'perf_youtube_facade', 'perf_preload_lcp', 'perf_content_visibility', 'perf_content_visibility_selector' ),
			),
			'fonts' => array(
				'label' => 'Fonts',
				'keys'  => array( 'perf_fonts_preconnect', 'perf_fonts_display_swap', 'perf_local_fonts', 'perf_preload_fonts', 'perf_system_fonts' ),
			),
			'preload' => array(
				'label' => 'Preload & Network',
				'keys'  => array( 'perf_dns_prefetch', 'perf_preconnect', 'perf_speculative_loading', 'perf_preload_assets' ),
			),
			'cdn' => array(
				'label' => 'CDN',
				'keys'  => array( 'perf_cdn_enable', 'perf_cdn_url', 'perf_cdn_exclude' ),
			),
			'background' => array(
				'label' => 'Background',
				'keys'  => array( 'perf_heartbeat', 'perf_revisions_keep', 'perf_autosave_interval' ),
			),
		);
	}

	/** Keys hidden behind the "Risky" toggle — these MIGHT break a site. */
	public static function perf_risky_keys() {
		return array(
			'perf_remove_jquery_migrate', 'perf_disable_comments', 'perf_disable_rss',
			'perf_disable_woo_css', 'perf_disable_woo_fragments',
			'perf_optimize_css_delivery', 'perf_critical_css', 'perf_css_async_exclude',
			'perf_remove_unused_css', 'perf_rucss_safelist', 'perf_rucss_exclude',
			'perf_rucss_engine', 'cf_account_id', 'cf_api_token', 'perf_rucss_urls',
			'perf_delay_js', 'perf_delay_js_exclude', 'perf_delay_js_timeout',
			'perf_content_visibility', 'perf_content_visibility_selector',
			'perf_system_fonts',
		);
	}

	public static function all() {
		if ( null === self::$cache ) {
			$saved = get_option( self::OPTION, array() );
			if ( ! is_array( $saved ) ) {
				$saved = array();
			}
			self::$cache = array_merge( self::defaults(), $saved );
		}
		return self::$cache;
	}

	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	public static function set( $key, $value ) {
		$all = self::all();
		$all[ $key ] = $value;
		self::save( $all );
	}

	public static function save( array $values ) {
		$merged = array_merge( self::defaults(), $values );
		update_option( self::OPTION, $merged );
		self::$cache = $merged;
	}

	/** True only when both the parent module and the feature itself are on. */
	public static function enabled( $feature, $module = null ) {
		if ( $module && ! self::get( $module ) ) {
			return false;
		}
		return (bool) self::get( $feature );
	}

	/**
	 * Apply a one-click preset. "safe" is just our defaults (all-safe-on, risky-off).
	 * "aggressive" layers on the high-value risky features that are reliable on the
	 * Oxygen/WPFC/Cloudflare stack — deliberately NOT jQuery-migrate removal (Oxygen
	 * needs it) or content-visibility (layout shift), which need per-site testing.
	 */
	public static function apply_preset( $name ) {
		$s = self::defaults();
		if ( 'aggressive' === $name ) {
			$s['perf_risky_mode']            = true;
			$s['perf_optimize_css_delivery'] = true;
			$s['perf_remove_unused_css']     = true;   // engine stays 'auto' = safe + zero-setup
			$s['perf_delay_scripts']         = true;
			$s['perf_disable_block_css']     = true;
			$s['perf_disable_global_styles'] = true;
			$s['perf_dequeue_woo_fragments'] = true;
		}
		update_option( self::OPTION, $s );
		self::$cache = $s;
		return array(
			'message' => 'safe' === $name
				? 'Safe defaults applied — every aggressive option is off.'
				: 'Aggressive preset applied — test your site, then exclude anything that breaks.',
		);
	}

	public static function install_defaults() {
		$existing = get_option( self::OPTION, null );
		if ( null === $existing ) {
			update_option( self::OPTION, self::defaults() );
		}
	}

	/**
	 * One-time heal. Versions before 1.1.1 shipped a broken save handler that
	 * silently wrote every boolean setting to false on each save, so any stored
	 * configuration from those versions is unreliable. We reset to clean defaults
	 * exactly once, then never touch the user's settings automatically again.
	 */

	public static function migrate() {
		$schema = get_option( 'velox_settings_schema', '0' );
		if ( version_compare( (string) $schema, '1.1.1', '<' ) ) {
			update_option( self::OPTION, self::defaults() );
			update_option( 'velox_settings_schema', '1.1.1' );
			self::$cache = null;
		}
	}
}
