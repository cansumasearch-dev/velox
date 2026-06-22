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

			// ---- Image optimizer / WebP ----
			'webp_quality'       => 80,
			'webp_keep_original' => true,
			'webp_auto_convert'  => false,
			'webp_convert_sizes' => true,
			'webp_serve_rewrite' => false,
			'image_keep_exif'    => false, // strip camera/GPS metadata by default for smaller files
			'image_max_width'    => 2560,  // downscale oversized uploads/conversions; 0 = off
			'image_comparison'   => true,  // show the old/new comparator in the Images tab

			// ---- Performance · General ----
			'perf_disable_emojis'        => true,
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

			// ---- Performance · Preload / Network ----
			'perf_dns_prefetch'          => "https://cdnjs.cloudflare.com\nhttps://fonts.gstatic.com",
			'perf_preconnect'            => '',    // one origin per line
			'perf_speculative_loading'   => 'off', // off | conservative | moderate
			'perf_preload_assets'        => '',    // one URL per line (css/js/img)

			// ---- Database ----
			'db_schedule_cleanup'        => false,

			// ---- Updater ----
			'gh_token'                   => '',
		);
	}

	/** Performance keys grouped by sub-section, used by the Performance view + hide logic. */
	public static function perf_sections() {
		return array(
			'general' => array(
				'label' => 'General',
				'keys'  => array(
					'perf_disable_emojis', 'perf_disable_embeds', 'perf_remove_query_strings',
					'perf_disable_xmlrpc', 'perf_disable_self_pingbacks', 'perf_clean_head',
					'perf_disable_dashicons', 'perf_remove_jquery_migrate', 'perf_disable_comments',
					'perf_disable_rss', 'perf_disable_app_passwords',
				),
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
				'keys'  => array( 'perf_add_image_dimensions', 'perf_fetchpriority_lcp', 'perf_lazyload_iframes', 'perf_youtube_facade', 'perf_preload_lcp', 'perf_content_visibility', 'perf_content_visibility_selector' ),
			),
			'fonts' => array(
				'label' => 'Fonts',
				'keys'  => array( 'perf_fonts_preconnect', 'perf_fonts_display_swap', 'perf_local_fonts', 'perf_preload_fonts' ),
			),
			'preload' => array(
				'label' => 'Preload & Network',
				'keys'  => array( 'perf_dns_prefetch', 'perf_preconnect', 'perf_speculative_loading', 'perf_preload_assets' ),
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
