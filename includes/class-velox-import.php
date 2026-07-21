<?php
/**
 * Velox — migration importers.
 *
 * Reads configuration from other popular plugins (WP Rocket, Yoast SEO,
 * WP Mail SMTP) straight out of their stored options and maps it onto Velox's
 * own settings, so switching to Velox doesn't mean reconfiguring from scratch.
 *
 * Nothing is changed in the source plugin — we only read. Each importer reports
 * exactly what it brought over so the user can verify before relying on it.
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Import {

	/** Which sources we can import, and whether each looks present on this site. */
	public static function sources() {
		return array(
			// --- fully supported (real importers) ---
			'wprocket' => array(
				'label'     => 'WP Rocket',
				'detected'  => self::wprocket_present(),
				'into'      => 'Performance',
				'ready'     => true,
				'desc'      => 'Cache lifespan, exclusions, defer/delay JS, lazy-load, font and preload settings.',
			),
			'yoast' => array(
				'label'     => 'Yoast SEO',
				'detected'  => self::yoast_present(),
				'into'      => 'SEO',
				'ready'     => true,
				'desc'      => 'Robots.txt rules, sitemap on/off, and per-post SEO titles, descriptions and noindex flags.',
			),
			'wpmailsmtp' => array(
				'label'     => 'WP Mail SMTP',
				'detected'  => self::wpmailsmtp_present(),
				'into'      => 'Mail',
				'ready'     => true,
				'desc'      => 'SMTP host, port, encryption, auth and From details — imported as a Velox mail connection.',
			),

			// --- recognised, one-click migration on the way ---
			'rankmath' => array(
				'label' => 'Rank Math SEO', 'into' => 'SEO', 'ready' => true,
				'detected' => self::present( array( 'const' => array( 'RANK_MATH_VERSION' ), 'class' => array( 'RankMath' ), 'slug' => array( 'seo-by-rank-math', 'seo-by-rank-math-pro' ) ) ),
				'desc' => 'Per-page SEO titles, meta descriptions and noindex flags, plus sitemap on/off.',
			),
			'aioseo' => array(
				'label' => 'All in One SEO', 'into' => 'SEO', 'ready' => true,
				'detected' => self::present( array( 'const' => array( 'AIOSEO_VERSION' ), 'slug' => array( 'all-in-one-seo-pack', 'all-in-one-seo-pack-pro' ) ) ),
				'desc' => 'Per-page SEO titles, meta descriptions and noindex flags, plus sitemap on/off.',
			),
			'seopress' => array(
				'label' => 'SEOPress', 'into' => 'SEO', 'ready' => true,
				'detected' => self::present( array( 'const' => array( 'SEOPRESS_VERSION' ), 'slug' => array( 'wp-seopress', 'wp-seopress-pro' ) ) ),
				'desc' => 'Per-page SEO titles, meta descriptions and noindex flags, plus sitemap on/off.',
			),
			'litespeed' => array(
				'label' => 'LiteSpeed Cache', 'into' => 'Performance', 'ready' => true,
				'detected' => self::present( array( 'const' => array( 'LSCWP_V', 'LSWCP_PLUGIN_NAME' ), 'slug' => array( 'litespeed-cache' ) ) ),
				'desc' => 'Public cache lifespan, separate mobile cache, and defer/delay JS. Server-level cache rules stay with LiteSpeed.',
			),
			'wpfastest' => array(
				'label' => 'WP Fastest Cache', 'into' => 'Performance', 'ready' => true,
				'detected' => self::present( array( 'class' => array( 'WpFastestCache' ), 'const' => array( 'WPFC_MAIN_PATH' ), 'slug' => array( 'wp-fastest-cache' ) ) ),
				'desc' => 'Mobile/logged-in caching, render-blocking (defer), lazy-load and cache URL exclusions.',
			),
			'w3tc' => array(
				'label' => 'W3 Total Cache', 'into' => 'Performance', 'ready' => true,
				'detected' => self::present( array( 'const' => array( 'W3TC_VERSION', 'W3TC' ), 'slug' => array( 'w3-total-cache' ) ) ),
				'desc' => 'Cache lifespan only — page cache, minify and browser-cache rules are server-level and stay with W3TC.',
			),
			'wpsupercache' => array(
				'label' => 'WP Super Cache', 'into' => 'Performance', 'ready' => true,
				'detected' => self::present( array( 'const' => array( 'WPCACHEHOME' ), 'slug' => array( 'wp-super-cache' ) ) ),
				'desc' => 'Cache expiry only — the rest is page-cache/mod_rewrite config that stays with WP Super Cache.',
			),
			'autoptimize' => array(
				'label' => 'Autoptimize', 'into' => 'Performance', 'ready' => true,
				'detected' => self::present( array( 'const' => array( 'AUTOPTIMIZE_PLUGIN_VERSION' ), 'slug' => array( 'autoptimize' ) ) ),
				'desc' => 'Defer JS and image lazy-load. CSS/JS aggregation & minification have no Velox equivalent and are not carried over.',
			),
			'perfmatters' => array(
				'label' => 'Perfmatters', 'into' => 'Performance', 'ready' => true,
				'detected' => self::present( array( 'const' => array( 'PERFMATTERS_VERSION' ), 'slug' => array( 'perfmatters' ) ) ),
				'desc' => 'Defer/delay JS, lazy-load, JS exclusions, font preloads and DNS-prefetch. The script manager (per-page disabling) stays with Perfmatters.',
			),
			'flyingpress' => array(
				'label' => 'FlyingPress', 'into' => 'Performance', 'ready' => true,
				'detected' => self::present( array( 'const' => array( 'FLYING_PRESS_VERSION' ), 'slug' => array( 'flying-press' ) ) ),
				'desc' => 'Defer/delay JS, lazy-load, JS exclusions and font preloads.',
			),
			'fluentsmtp' => array(
				'label' => 'FluentSMTP', 'into' => 'Mail', 'ready' => true,
				'detected' => self::present( array( 'const' => array( 'FLUENTMAIL' ), 'slug' => array( 'fluent-smtp' ) ) ),
				'desc' => 'Imports your first SMTP connection (host, port, encryption, auth and From) as a Velox mail connection.',
			),
			'postsmtp' => array(
				'label' => 'Post SMTP', 'into' => 'Mail', 'ready' => true,
				'detected' => self::present( array( 'const' => array( 'POST_SMTP_VER' ), 'slug' => array( 'post-smtp' ) ) ),
				'desc' => 'SMTP host, port, auth and From details as a Velox mail connection.',
			),
			'easywpsmtp' => array(
				'label' => 'Easy WP SMTP', 'into' => 'Mail', 'ready' => true,
				'detected' => self::present( array( 'const' => array( 'EASY_WP_SMTP_VERSION' ), 'slug' => array( 'easy-wp-smtp' ) ) ),
				'desc' => 'SMTP host, port, auth and From details.',
			),
			'cookieyes' => array(
				'label' => 'CookieYes', 'into' => 'Cookie Banner', 'ready' => true,
				'detected' => self::present( array( 'const' => array( 'CLI_PLUGIN_VERSION' ), 'slug' => array( 'cookie-law-info' ) ) ),
				'desc' => 'Banner text and button labels only — consent categories and script-blocking do not transfer.',
			),
			'complianz' => array(
				'label' => 'Complianz', 'into' => 'Cookie Banner', 'ready' => true,
				'detected' => self::present( array( 'const' => array( 'cmplz_plugin_file', 'cmplz_version' ), 'slug' => array( 'complianz-gdpr', 'complianz-gdpr-premium' ) ) ),
				'desc' => 'Banner text and button labels only — consent categories and script-blocking do not transfer.',
			),
			'borlabs' => array(
				'label' => 'Borlabs Cookie', 'into' => 'Cookie Banner', 'ready' => true,
				'detected' => self::present( array( 'class' => array( 'BorlabsCookie' ), 'const' => array( 'BORLABS_COOKIE_VERSION' ), 'slug' => array( 'borlabs-cookie' ) ) ),
				'desc' => 'Banner text and button labels only — consent categories and script-blocking do not transfer.',
			),
			'redirection' => array(
				'label' => 'Redirection', 'into' => 'Redirects & 404s', 'ready' => true,
				'detected' => self::present( array( 'const' => array( 'REDIRECTION_VERSION' ), 'slug' => array( 'redirection' ) ) ),
				'desc' => 'Your URL redirects (source, target, 301/302, regex) into Velox Redirects & 404s.',
			),
			'wpcode' => array(
				'label' => 'WPCode', 'into' => 'Code Snippets', 'ready' => true,
				'detected' => self::present( array( 'const' => array( 'WPCODE_VERSION' ), 'slug' => array( 'insert-headers-and-footers', 'wpcode-premium' ) ) ),
				'desc' => 'PHP / CSS / JS / HTML snippets — imported inactive so you can review before running them.',
			),
			'codesnippets' => array(
				'label' => 'Code Snippets', 'into' => 'Code Snippets', 'ready' => true,
				'detected' => self::present( array( 'const' => array( 'CODE_SNIPPETS_VERSION' ), 'slug' => array( 'code-snippets', 'code-snippets-pro' ) ) ),
				'desc' => 'PHP / CSS / JS snippets — imported inactive so you can review before running them.',
			),
		);
	}

	/** Presence check across constants, classes, options and active plugin files. */
	private static function present( $checks ) {
		foreach ( (array) ( isset( $checks['const'] ) ? $checks['const'] : array() ) as $c ) {
			if ( defined( $c ) ) {
				return true;
			}
		}
		foreach ( (array) ( isset( $checks['class'] ) ? $checks['class'] : array() ) as $cl ) {
			if ( class_exists( $cl ) ) {
				return true;
			}
		}
		if ( isset( $checks['slug'] ) && self::on_disk( $checks['slug'] ) ) {
			return true;
		}
		// NOTE: leftover DB options are deliberately NOT treated as "present" —
		// plugins like Yoast leave their options behind after uninstall, which made
		// migration falsely detect plugins that are no longer installed.
		return false;
	}

	/** True only if the plugin's folder is actually installed on disk (active or not). */
	private static function on_disk( $slugs ) {
		static $all = null;
		if ( null === $all ) {
			if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
			$all = array_keys( get_plugins() );
		}
		foreach ( (array) $slugs as $s ) {
			foreach ( $all as $file ) {
				if ( 0 === strpos( $file, $s . '/' ) ) { return true; }
			}
		}
		return false;
	}

	public static function run( $source ) {
		switch ( $source ) {
			case 'wprocket':
				return self::import_wprocket();
			case 'wpfastest':
				return self::import_wpfastest();
			case 'litespeed':
				return self::import_litespeed();
			case 'autoptimize':
				return self::import_autoptimize();
			case 'perfmatters':
				return self::import_perfmatters();
			case 'flyingpress':
				return self::import_flyingpress();
			case 'w3tc':
				return self::import_w3tc();
			case 'wpsupercache':
				return self::import_wpsupercache();
			case 'redirection':
				return self::import_redirection();
			case 'codesnippets':
				return self::import_codesnippets();
			case 'wpcode':
				return self::import_wpcode();
			case 'cookieyes':
				return self::import_cookieyes();
			case 'complianz':
				return self::import_complianz();
			case 'borlabs':
				return self::import_borlabs();
			case 'yoast':
				return self::import_yoast();
			case 'rankmath':
				return self::import_rankmath();
			case 'aioseo':
				return self::import_aioseo();
			case 'seopress':
				return self::import_seopress();
			case 'wpmailsmtp':
				return self::import_wpmailsmtp();
			case 'fluentsmtp':
				return self::import_fluentsmtp();
			case 'postsmtp':
				return self::import_postsmtp();
			case 'easywpsmtp':
				return self::import_easywpsmtp();
		}
		return array( 'ok' => false, 'message' => 'Automatic migration for this plugin isn\'t available yet.' );
	}

	/* ----------------------------------------------------------------- *
	 * Detection
	 * ----------------------------------------------------------------- */

	private static function wprocket_present() {
		return defined( 'WP_ROCKET_VERSION' ) || self::on_disk( 'wp-rocket' );
	}

	private static function yoast_present() {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) || self::on_disk( array( 'wordpress-seo', 'wordpress-seo-premium' ) );
	}

	private static function wpmailsmtp_present() {
		return defined( 'WPMS_PLUGIN_VER' ) || self::on_disk( 'wp-mail-smtp' );
	}

	/* ----------------------------------------------------------------- *
	 * WP Rocket → Velox Performance
	 * ----------------------------------------------------------------- */

	public static function import_wprocket() {
		$r = get_option( 'wp_rocket_settings', array() );
		if ( ! is_array( $r ) || empty( $r ) ) {
			return array( 'ok' => false, 'message' => 'No WP Rocket settings found on this site.' );
		}
		$set     = array();
		$imported = array();

		// Cache lifespan → seconds.
		if ( isset( $r['purge_cron_interval'], $r['purge_cron_unit'] ) ) {
			$unit = (string) $r['purge_cron_unit'];
			$mult = ( 'DAY_IN_SECONDS' === $unit ) ? 86400 : ( ( 'HOUR_IN_SECONDS' === $unit ) ? 3600 : 60 );
			$ttl  = (int) $r['purge_cron_interval'] * $mult;
			if ( $ttl > 0 ) {
				$set['cache_ttl'] = $ttl;
				$imported[]       = 'Cache lifespan (' . $ttl . 's)';
			}
		}

		// Booleans that map 1:1-ish.
		$map_bool = array(
			'cache_mobile'              => 'cache_mobile_separate',
			'cache_logged_user'         => 'cache_logged_in',
			'defer_all_js'              => 'perf_defer_scripts',
			'delay_js'                  => 'perf_delay_js',
			'lazyload_iframes'          => 'perf_lazyload_iframes',
			'minify_concatenate_css'    => null, // not a 1:1 concept in Velox; skip
		);
		foreach ( $map_bool as $from => $to ) {
			if ( null === $to || ! isset( $r[ $from ] ) ) {
				continue;
			}
			$set[ $to ] = ! empty( $r[ $from ] );
			if ( $set[ $to ] ) {
				$imported[] = self::pretty( $to );
			}
		}

		// Exclusions / lists (arrays → newline text).
		if ( ! empty( $r['cache_reject_uri'] ) && is_array( $r['cache_reject_uri'] ) ) {
			$set['cache_exclude_urls'] = implode( "\n", array_map( 'strval', $r['cache_reject_uri'] ) );
			$imported[]                = count( $r['cache_reject_uri'] ) . ' cache URL exclusions';
		}
		if ( ! empty( $r['cache_reject_cookies'] ) && is_array( $r['cache_reject_cookies'] ) ) {
			$set['cache_exclude_cookies'] = implode( "\n", array_map( 'strval', $r['cache_reject_cookies'] ) );
			$imported[]                   = 'cookie cache exclusions';
		}
		if ( ! empty( $r['exclude_defer_js'] ) && is_array( $r['exclude_defer_js'] ) ) {
			$set['perf_defer_exclude'] = implode( "\n", array_map( 'strval', $r['exclude_defer_js'] ) );
			$imported[]                = 'defer-JS exclusions';
		}
		if ( ! empty( $r['delay_js_exclusions'] ) && is_array( $r['delay_js_exclusions'] ) ) {
			$set['perf_delay_js_exclude'] = implode( "\n", array_map( 'strval', $r['delay_js_exclusions'] ) );
			$imported[]                   = 'delay-JS exclusions';
		}
		if ( ! empty( $r['preload_fonts'] ) && is_array( $r['preload_fonts'] ) ) {
			$set['perf_preload_fonts'] = implode( "\n", array_map( 'strval', $r['preload_fonts'] ) );
			$imported[]                = 'font preloads';
		}
		if ( ! empty( $r['dns_prefetch'] ) && is_array( $r['dns_prefetch'] ) ) {
			$set['perf_dns_prefetch'] = implode( "\n", array_map( 'strval', $r['dns_prefetch'] ) );
			$imported[]               = 'DNS-prefetch hosts';
		}

		if ( empty( $imported ) ) {
			return array( 'ok' => false, 'message' => 'WP Rocket was found, but nothing mappable was set.' );
		}
		self::apply( $set );
		return array(
			'ok'       => true,
			'message'  => 'Imported from WP Rocket. Review the Performance settings, then enable what you want.',
			'imported' => $imported,
			'note'     => 'Velox did not switch caching on automatically — turn it on once you have reviewed the exclusions.',
		);
	}

	/* ---------------- WP Fastest Cache ---------------- */

	public static function import_wpfastest() {
		$raw = get_option( 'WpFastestCache', '' );
		$o   = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : null );
		if ( ! is_array( $o ) || empty( $o ) ) {
			return array( 'ok' => false, 'message' => 'No WP Fastest Cache settings found on this site.' );
		}
		$set      = array();
		$imported = array();
		$on = function ( $k ) use ( $o ) {
			return isset( $o[ $k ] ) && ( 'on' === $o[ $k ] || true === $o[ $k ] || 1 === (int) $o[ $k ] );
		};

		$map = array(
			'wpFastestCacheMobile'         => 'cache_mobile_separate',
			'wpFastestCacheLoggedInUser'   => 'cache_logged_in',
			'wpFastestCacheRenderBlocking' => 'perf_defer_scripts',
			'wpFastestCacheLazyLoad'       => 'perf_lazyload_iframes',
		);
		foreach ( $map as $from => $to ) {
			if ( isset( $o[ $from ] ) ) {
				$set[ $to ] = $on( $from );
				if ( $set[ $to ] ) {
					$imported[] = self::pretty( $to );
				}
			}
		}

		// Page URL exclusions live in a separate option (JSON list of rules).
		$exraw = get_option( 'WpFastestCacheExclude', '' );
		$ex    = is_string( $exraw ) ? json_decode( $exraw, true ) : ( is_array( $exraw ) ? $exraw : null );
		if ( is_array( $ex ) ) {
			$urls = array();
			foreach ( $ex as $rule ) {
				if ( is_array( $rule ) && ! empty( $rule['content'] ) && ( ! isset( $rule['type'] ) || 'page' === $rule['type'] ) ) {
					$urls[] = (string) $rule['content'];
				}
			}
			$urls = array_values( array_filter( array_unique( $urls ) ) );
			if ( $urls ) {
				$set['cache_exclude_urls'] = implode( "\n", $urls );
				$imported[]                = count( $urls ) . ' cache URL exclusions';
			}
		}

		if ( empty( $imported ) ) {
			return array( 'ok' => false, 'message' => 'WP Fastest Cache was found, but nothing mappable was set.' );
		}
		self::apply( $set );
		return array(
			'ok'       => true,
			'message'  => 'Imported from WP Fastest Cache. Review the Performance settings, then enable what you want.',
			'imported' => $imported,
			'note'     => 'Velox did not switch caching on automatically — turn it on once you have reviewed the exclusions.',
		);
	}

	/* ---------------- LiteSpeed Cache ---------------- */

	public static function import_litespeed() {
		// LiteSpeed v4+ stores each setting as its own option (litespeed.conf.*);
		// v3 kept them in a serialized litespeed-cache-conf array. Support both.
		$legacy = get_option( 'litespeed-cache-conf', array() );
		$legacy = is_array( $legacy ) ? $legacy : array();
		$get = function ( $modern, $legacy_key ) use ( $legacy ) {
			$v = get_option( 'litespeed.conf.' . $modern, null );
			if ( null !== $v ) {
				return $v;
			}
			return isset( $legacy[ $legacy_key ] ) ? $legacy[ $legacy_key ] : null;
		};

		$set      = array();
		$imported = array();

		$ttl = $get( 'cache-ttl_pub', 'public_ttl' );
		if ( null !== $ttl && (int) $ttl > 0 ) {
			$set['cache_ttl'] = (int) $ttl;
			$imported[]       = 'Cache lifespan (' . (int) $ttl . 's)';
		}
		$mobile = $get( 'cache-mobile', 'mobileview_enabled' );
		if ( null !== $mobile ) {
			$set['cache_mobile_separate'] = ! empty( $mobile );
			if ( $set['cache_mobile_separate'] ) {
				$imported[] = self::pretty( 'cache_mobile_separate' );
			}
		}
		$defer = $get( 'optm-js_defer', 'js_defer' );
		if ( null !== $defer ) {
			$set['perf_defer_scripts'] = ( (int) $defer >= 1 );
			if ( $set['perf_defer_scripts'] ) {
				$imported[] = self::pretty( 'perf_defer_scripts' );
			}
			if ( 2 === (int) $defer ) { // LiteSpeed uses 2 = "delayed"
				$set['perf_delay_js'] = true;
				$imported[]           = self::pretty( 'perf_delay_js' );
			}
		}

		if ( empty( $imported ) ) {
			return array( 'ok' => false, 'message' => 'LiteSpeed Cache was found, but nothing that maps to Velox was set.' );
		}
		self::apply( $set );
		return array(
			'ok'       => true,
			'message'  => 'Imported from LiteSpeed Cache. Review the Performance settings, then enable what you want.',
			'imported' => $imported,
			'note'     => 'LiteSpeed’s server-level cache rules are not part of Velox and stay where they are — Velox did not switch caching on automatically.',
		);
	}

	/* ---------------- Autoptimize ---------------- */

	public static function import_autoptimize() {
		$set      = array();
		$imported = array();

		// Autoptimize defers the JS it aggregates; treat "JS optimisation on, not forced in <head>" as defer.
		if ( 'on' === get_option( 'autoptimize_js', '' ) && 'on' !== get_option( 'autoptimize_js_forcehead', '' ) ) {
			$set['perf_defer_scripts'] = true;
			$imported[]                = self::pretty( 'perf_defer_scripts' );
		}

		// Image lazy-load lives in the image-optimisation settings blob.
		$img = get_option( 'autoptimize_imgopt_settings', array() );
		if ( is_array( $img ) && ! empty( $img['autoptimize_imgopt_checkbox_field_3'] ) ) {
			$set['perf_lazyload_iframes'] = true;
			$imported[]                   = self::pretty( 'perf_lazyload_iframes' );
		}

		if ( empty( $imported ) ) {
			return array(
				'ok'      => false,
				'message' => 'Autoptimize was found, but its core features (CSS/JS aggregation & minification) have no Velox equivalent, so there was nothing to import.',
			);
		}
		self::apply( $set );
		return array(
			'ok'       => true,
			'message'  => 'Imported from Autoptimize. Review the Performance settings, then enable what you want.',
			'imported' => $imported,
			'note'     => 'Autoptimize’s CSS/JS aggregation and minification are not part of Velox’s model and were not carried over.',
		);
	}

	/* ---------------- Perfmatters ---------------- */

	public static function import_perfmatters() {
		$o = get_option( 'perfmatters_options', array() );
		if ( ! is_array( $o ) || empty( $o ) ) {
			return array( 'ok' => false, 'message' => 'No Perfmatters settings found on this site.' );
		}
		$assets  = isset( $o['assets'] ) && is_array( $o['assets'] ) ? $o['assets'] : array();
		$lazy    = isset( $o['lazyload'] ) && is_array( $o['lazyload'] ) ? $o['lazyload'] : array();
		$preload = isset( $o['preload'] ) && is_array( $o['preload'] ) ? $o['preload'] : array();
		$set      = array();
		$imported = array();
		$text = function ( $v ) { return is_array( $v ) ? implode( "\n", array_map( 'strval', $v ) ) : (string) $v; };

		$bools = array(
			array( $assets, 'defer_js', 'perf_defer_scripts' ),
			array( $assets, 'delay_js', 'perf_delay_js' ),
			array( $lazy, 'lazy_loading_iframes', 'perf_lazyload_iframes' ),
		);
		foreach ( $bools as $b ) {
			if ( ! empty( $b[0][ $b[1] ] ) ) {
				$set[ $b[2] ] = true;
				$imported[]   = self::pretty( $b[2] );
			}
		}
		if ( ! empty( $assets['js_exclusions'] ) ) {
			$set['perf_defer_exclude'] = $text( $assets['js_exclusions'] );
			$imported[]                = 'defer-JS exclusions';
		}
		if ( ! empty( $assets['delay_js_exclusions'] ) ) {
			$set['perf_delay_js_exclude'] = $text( $assets['delay_js_exclusions'] );
			$imported[]                   = 'delay-JS exclusions';
		}
		if ( ! empty( $preload['preload_fonts'] ) ) {
			$set['perf_preload_fonts'] = $text( $preload['preload_fonts'] );
			$imported[]                = 'font preloads';
		}
		if ( ! empty( $preload['dns_prefetch'] ) ) {
			$set['perf_dns_prefetch'] = $text( $preload['dns_prefetch'] );
			$imported[]               = 'DNS-prefetch hosts';
		}

		if ( empty( $imported ) ) {
			return array( 'ok' => false, 'message' => 'Perfmatters was found, but nothing that maps to Velox was set.' );
		}
		self::apply( $set );
		return array(
			'ok'       => true,
			'message'  => 'Imported from Perfmatters. Review the Performance settings, then enable what you want.',
			'imported' => $imported,
		);
	}

	/* ---------------- FlyingPress ---------------- */

	public static function import_flyingpress() {
		$o = get_option( 'flying_press_settings', array() );
		if ( ! is_array( $o ) || empty( $o ) ) {
			return array( 'ok' => false, 'message' => 'No FlyingPress settings found on this site.' );
		}
		$set      = array();
		$imported = array();
		$text = function ( $v ) { return is_array( $v ) ? implode( "\n", array_map( 'strval', $v ) ) : (string) $v; };

		$bools = array(
			'js_defer'  => 'perf_defer_scripts',
			'js_delay'  => 'perf_delay_js',
			'lazy_load' => 'perf_lazyload_iframes',
		);
		foreach ( $bools as $from => $to ) {
			if ( isset( $o[ $from ] ) ) {
				$set[ $to ] = ! empty( $o[ $from ] );
				if ( $set[ $to ] ) {
					$imported[] = self::pretty( $to );
				}
			}
		}
		if ( ! empty( $o['js_defer_exclusions'] ) ) {
			$set['perf_defer_exclude'] = $text( $o['js_defer_exclusions'] );
			$imported[]                = 'defer-JS exclusions';
		}
		if ( ! empty( $o['js_delay_exclusions'] ) ) {
			$set['perf_delay_js_exclude'] = $text( $o['js_delay_exclusions'] );
			$imported[]                   = 'delay-JS exclusions';
		}
		if ( ! empty( $o['preload_fonts_urls'] ) ) {
			$set['perf_preload_fonts'] = $text( $o['preload_fonts_urls'] );
			$imported[]                = 'font preloads';
		}

		if ( empty( $imported ) ) {
			return array( 'ok' => false, 'message' => 'FlyingPress was found, but nothing that maps to Velox was set.' );
		}
		self::apply( $set );
		return array(
			'ok'       => true,
			'message'  => 'Imported from FlyingPress. Review the Performance settings, then enable what you want.',
			'imported' => $imported,
		);
	}

	/* ---------------- W3 Total Cache ---------------- */

	public static function import_w3tc() {
		$set      = array();
		$imported = array();
		$cfg      = get_option( 'w3tc_config', array() );
		if ( is_array( $cfg ) ) {
			$ttl = isset( $cfg['pgcache.lifetime'] ) ? (int) $cfg['pgcache.lifetime'] : ( isset( $cfg['browsercache.cssjs.lifetime'] ) ? (int) $cfg['browsercache.cssjs.lifetime'] : 0 );
			if ( $ttl > 0 ) {
				$set['cache_ttl'] = $ttl;
				$imported[]       = 'Cache lifespan (' . $ttl . 's)';
			}
		}
		if ( empty( $imported ) ) {
			return array(
				'ok'      => false,
				'message' => 'W3 Total Cache is mostly page-cache and server/.htaccess configuration with no Velox equivalent — nothing was imported. Set defer/delay JS, lazy-load and exclusions directly in Velox Performance.',
			);
		}
		self::apply( $set );
		return array(
			'ok'       => true,
			'message'  => 'Imported the cache lifespan from W3 Total Cache. Page cache, minify and browser-cache rules are server-level and stay with W3TC.',
			'imported' => $imported,
		);
	}

	/* ---------------- WP Super Cache ---------------- */

	public static function import_wpsupercache() {
		$set      = array();
		$imported = array();
		$dir      = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
		$file     = $dir . '/wp-cache-config.php';
		if ( is_readable( $file ) ) {
			$c = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( preg_match( '/\$cache_max_time\s*=\s*(\d+)/', $c, $m ) ) {
				$ttl = (int) $m[1];
				if ( $ttl > 0 ) {
					$set['cache_ttl'] = $ttl;
					$imported[]       = 'Cache lifespan (' . $ttl . 's)';
				}
			}
		}
		if ( empty( $imported ) ) {
			return array(
				'ok'      => false,
				'message' => 'WP Super Cache is page-cache and mod_rewrite configuration with no Velox equivalent — nothing was imported. Set your Velox Performance options directly.',
			);
		}
		self::apply( $set );
		return array(
			'ok'       => true,
			'message'  => 'Imported the cache expiry from WP Super Cache. Its page-cache/mod_rewrite rules are server-level and stay with WP Super Cache.',
			'imported' => $imported,
		);
	}

	/* ---------------- Redirection ---------------- */

	public static function import_redirection() {
		global $wpdb;
		if ( ! class_exists( 'Velox_Redirects' ) ) {
			return array( 'ok' => false, 'message' => 'Velox Redirects module is unavailable.' );
		}
		$table = $wpdb->prefix . 'redirection_items';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
			return array( 'ok' => false, 'message' => 'No Redirection data found on this site.' );
		}
		$rows = $wpdb->get_results( "SELECT url, action_data, action_code, action_type, regex, status FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( empty( $rows ) ) {
			return array( 'ok' => false, 'message' => 'Redirection has no redirects to import.' );
		}
		$added   = 0;
		$skipped = 0;
		foreach ( $rows as $r ) {
			// Only plain URL redirects — skip pass-through / 404 / random actions.
			if ( isset( $r['action_type'] ) && 'url' !== $r['action_type'] ) {
				$skipped++;
				continue;
			}
			$source = (string) $r['url'];
			$target = maybe_unserialize( (string) $r['action_data'] );
			if ( is_array( $target ) ) {
				$target = isset( $target['url'] ) ? $target['url'] : '';
			}
			$target = (string) $target;
			if ( '' === trim( $source ) || '' === trim( $target ) ) {
				$skipped++;
				continue;
			}
			$type = (int) $r['action_code'];
			if ( ! in_array( $type, array( 301, 302, 307, 410 ), true ) ) {
				$type = 301;
			}
			$res = Velox_Redirects::add(
				$source,
				$target,
				$type,
				array(
					'match_type'  => ! empty( $r['regex'] ) ? 'regex' : 'exact',
					'active'      => ( 'enabled' === ( isset( $r['status'] ) ? $r['status'] : 'enabled' ) ) ? 1 : 0,
					'description' => 'Imported from Redirection',
				)
			);
			if ( ! empty( $res['ok'] ) ) {
				$added++;
			} else {
				$skipped++;
			}
		}
		if ( 0 === $added ) {
			return array( 'ok' => false, 'message' => 'Redirection was found, but no URL redirects could be imported.' );
		}
		return array(
			'ok'       => true,
			'message'  => 'Imported ' . $added . ' redirect(s) from Redirection into Redirects & 404s.',
			'imported' => array( $added . ' redirects' . ( $skipped ? ' (' . $skipped . ' skipped — non-URL or invalid)' : '' ) ),
		);
	}

	/* ---------------- Code Snippets ---------------- */

	public static function import_codesnippets() {
		global $wpdb;
		if ( ! class_exists( 'Velox_Snippets' ) ) {
			return array( 'ok' => false, 'message' => 'Velox Snippets module is unavailable.' );
		}
		$table = $wpdb->prefix . 'snippets';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) {
			return array( 'ok' => false, 'message' => 'No Code Snippets data found on this site.' );
		}
		$rows = $wpdb->get_results( "SELECT name, description, code, scope FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( empty( $rows ) ) {
			return array( 'ok' => false, 'message' => 'Code Snippets has nothing to import.' );
		}
		$added = 0;
		foreach ( $rows as $r ) {
			$scope = (string) $r['scope'];
			if ( in_array( $scope, array( 'admin-css', 'site-css' ), true ) ) {
				$type = 'css';
			} elseif ( in_array( $scope, array( 'site-head-js', 'site-footer-js' ), true ) ) {
				$type = 'js';
			} else {
				$type = 'php';
			}
			$res = Velox_Snippets::save(
				array(
					'name'        => (string) $r['name'],
					'description' => (string) $r['description'],
					'type'        => $type,
					'code'        => (string) $r['code'],
					'scope'       => 'everywhere',
					'active'      => 0, // Always import inactive — never auto-run someone else's code.
				)
			);
			if ( ! empty( $res['ok'] ) ) {
				$added++;
			}
		}
		if ( 0 === $added ) {
			return array( 'ok' => false, 'message' => 'Code Snippets was found, but nothing could be imported.' );
		}
		return array(
			'ok'       => true,
			'message'  => 'Imported ' . $added . ' snippet(s) from Code Snippets — all imported INACTIVE. Review each, then activate.',
			'imported' => array( $added . ' snippets (inactive)' ),
		);
	}

	/* ---------------- WPCode ---------------- */

	public static function import_wpcode() {
		if ( ! class_exists( 'Velox_Snippets' ) ) {
			return array( 'ok' => false, 'message' => 'Velox Snippets module is unavailable.' );
		}
		$posts = get_posts(
			array(
				'post_type'   => 'wpcode',
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts' => 500,
			)
		);
		if ( empty( $posts ) ) {
			return array( 'ok' => false, 'message' => 'No WPCode snippets found on this site.' );
		}
		$added = 0;
		foreach ( $posts as $p ) {
			$ctype = (string) get_post_meta( $p->ID, '_wpcode_code_type', true );
			$type  = in_array( $ctype, array( 'php', 'css', 'js', 'html' ), true ) ? $ctype : ( 'text' === $ctype ? 'html' : 'php' );
			$res   = Velox_Snippets::save(
				array(
					'name'        => $p->post_title,
					'description' => '',
					'type'        => $type,
					'code'        => $p->post_content,
					'scope'       => 'everywhere',
					'active'      => 0,
				)
			);
			if ( ! empty( $res['ok'] ) ) {
				$added++;
			}
		}
		if ( 0 === $added ) {
			return array( 'ok' => false, 'message' => 'WPCode was found, but nothing could be imported.' );
		}
		return array(
			'ok'       => true,
			'message'  => 'Imported ' . $added . ' snippet(s) from WPCode — all imported INACTIVE. Review each, then activate.',
			'imported' => array( $added . ' snippets (inactive)' ),
		);
	}

	/* ---------------- Cookie-consent plugins (text/labels only) ---------------- */

	/** Shared: map whatever banner text/labels were found into the Velox cookie banner. */
	private static function apply_cookie_text( $heading, $body, $accept, $reject, $settings, $label ) {
		$set      = array();
		$imported = array();
		$put = function ( $val, $key, $desc ) use ( &$set, &$imported ) {
			$val = trim( wp_strip_all_tags( (string) $val ) );
			if ( '' !== $val ) {
				$set[ $key ]  = $val;
				$imported[]   = $desc;
			}
		};
		$put( $heading, 'cookie_heading', 'banner heading' );
		$put( $body, 'cookie_body', 'banner text' );
		$put( $accept, 'cookie_btn_accept', 'accept label' );
		$put( $reject, 'cookie_btn_reject', 'reject label' );
		$put( $settings, 'cookie_btn_settings', 'preferences label' );

		if ( empty( $imported ) ) {
			return array(
				'ok'      => false,
				'message' => $label . ' uses its own consent model (cookie categories and script-blocking) with no Velox equivalent, and no plainly-stored banner text was found — set your Velox banner text directly.',
			);
		}
		self::apply( $set );
		return array(
			'ok'       => true,
			'message'  => 'Imported the banner text/labels from ' . $label . '. Review the Velox cookie banner.',
			'imported' => $imported,
			'note'     => 'Only text and button labels were imported — ' . $label . '\'s cookie categories, script-blocking and appearance are a different model and were not carried over.',
		);
	}

	public static function import_cookieyes() {
		$s = get_option( 'cookielawinfo_settings', array() );
		if ( ! is_array( $s ) ) {
			$s = array();
		}
		$body = isset( $s['notify_message'] ) ? $s['notify_message'] : ( isset( $s['notify_message_eu'] ) ? $s['notify_message_eu'] : '' );
		return self::apply_cookie_text(
			'',
			$body,
			isset( $s['button_1_button_text'] ) ? $s['button_1_button_text'] : '',
			isset( $s['button_2_button_text'] ) ? $s['button_2_button_text'] : '',
			isset( $s['button_3_button_text'] ) ? $s['button_3_button_text'] : '',
			'CookieYes'
		);
	}

	public static function import_complianz() {
		$o = get_option( 'complianz_options_settings', array() );
		if ( ! is_array( $o ) ) {
			$o = array();
		}
		$body = isset( $o['banner_message_optin'] ) ? $o['banner_message_optin'] : ( isset( $o['message_optin'] ) ? $o['message_optin'] : '' );
		return self::apply_cookie_text(
			isset( $o['banner_title'] ) ? $o['banner_title'] : '',
			$body,
			isset( $o['accept'] ) ? $o['accept'] : '',
			isset( $o['deny'] ) ? $o['deny'] : '',
			isset( $o['view_preferences'] ) ? $o['view_preferences'] : '',
			'Complianz'
		);
	}

	public static function import_borlabs() {
		$o = get_option( 'BorlabsCookieDialogSettings', array() );
		if ( ! is_array( $o ) || empty( $o ) ) {
			$o = get_option( 'borlabs-cookie', array() );
			if ( ! is_array( $o ) ) {
				$o = array();
			}
		}
		return self::apply_cookie_text(
			isset( $o['dialogTitle'] ) ? $o['dialogTitle'] : '',
			isset( $o['dialogText'] ) ? $o['dialogText'] : '',
			isset( $o['buttonAcceptAllText'] ) ? $o['buttonAcceptAllText'] : '',
			isset( $o['buttonRejectText'] ) ? $o['buttonRejectText'] : '',
			isset( $o['buttonManageText'] ) ? $o['buttonManageText'] : '',
			'Borlabs Cookie'
		);
	}

	/* ----------------------------------------------------------------- *
	 * Yoast SEO → Velox SEO
	 * ----------------------------------------------------------------- */

	public static function import_yoast() {
		$titles = get_option( 'wpseo_titles', array() );
		$wpseo  = get_option( 'wpseo', array() );
		if ( ( ! is_array( $titles ) || empty( $titles ) ) && ( ! is_array( $wpseo ) || empty( $wpseo ) ) ) {
			return array( 'ok' => false, 'message' => 'No Yoast SEO settings found on this site.' );
		}
		$set      = array();
		$imported = array();

		// Sitemap on/off.
		if ( isset( $wpseo['enable_xml_sitemap'] ) ) {
			$set['seo_sitemap_enable'] = ! empty( $wpseo['enable_xml_sitemap'] );
			$imported[]                = 'Sitemap ' . ( $set['seo_sitemap_enable'] ? 'on' : 'off' );
		}

		self::apply( $set );

		// Per-post meta: map Yoast post-meta keys → Velox post-meta keys.
		$posts = self::map_yoast_postmeta();
		if ( $posts > 0 ) {
			$imported[] = $posts . ' per-page SEO title/description/noindex set(s)';
		}

		if ( empty( $imported ) ) {
			return array( 'ok' => false, 'message' => 'Yoast was found, but nothing mappable was set.' );
		}
		return array(
			'ok'       => true,
			'message'  => 'Imported from Yoast SEO. Existing Velox per-page values were not overwritten.',
			'imported' => $imported,
		);
	}

	/**
	 * Copy Yoast per-post SEO meta into Velox's own meta keys, without clobbering
	 * anything the user already set in Velox.
	 *
	 * Yoast keys: _yoast_wpseo_title, _yoast_wpseo_metadesc, _yoast_wpseo_meta-robots-noindex (1 = noindex)
	 * Velox keys: _velox_seo_title, _velox_seo_desc, _velox_seo_noindex
	 *
	 * @return int number of posts touched
	 */
	private static function map_yoast_postmeta() {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
			 WHERE meta_key IN ('_yoast_wpseo_title','_yoast_wpseo_metadesc','_yoast_wpseo_meta-robots-noindex')",
			ARRAY_A
		);
		if ( empty( $rows ) ) {
			return 0;
		}
		$touched = array();
		foreach ( $rows as $row ) {
			$pid = (int) $row['post_id'];
			$val = (string) $row['meta_value'];
			if ( '' === trim( $val ) ) {
				continue;
			}
			switch ( $row['meta_key'] ) {
				case '_yoast_wpseo_title':
					if ( '' === (string) get_post_meta( $pid, '_velox_seo_title', true ) ) {
						// Yoast uses %%title%% style vars — strip the obvious ones to plain text.
						update_post_meta( $pid, '_velox_seo_title', self::strip_yoast_vars( $val ) );
						$touched[ $pid ] = true;
					}
					break;
				case '_yoast_wpseo_metadesc':
					if ( '' === (string) get_post_meta( $pid, '_velox_seo_desc', true ) ) {
						update_post_meta( $pid, '_velox_seo_desc', self::strip_yoast_vars( $val ) );
						$touched[ $pid ] = true;
					}
					break;
				case '_yoast_wpseo_meta-robots-noindex':
					if ( '1' === $val && '' === (string) get_post_meta( $pid, '_velox_seo_noindex', true ) ) {
						update_post_meta( $pid, '_velox_seo_noindex', '1' );
						$touched[ $pid ] = true;
					}
					break;
			}
		}
		return count( $touched );
	}

	private static function strip_yoast_vars( $s ) {
		// Replace the most common Yoast template variables with sensible plain text.
		$s = str_replace(
			array( '%%title%%', '%%sitename%%', '%%page%%', '%%sep%%', '%%primary_category%%', '%%excerpt%%' ),
			array( get_the_title(), get_bloginfo( 'name' ), '', '|', '', '' ),
			(string) $s
		);
		// Drop any remaining %%var%% tokens.
		$s = preg_replace( '/%%[^%]+%%/', '', $s );
		return trim( preg_replace( '/\s{2,}/', ' ', $s ) );
	}

	/* ---------------- Rank Math SEO ---------------- */

	public static function import_rankmath() {
		$imported = array();
		$set      = array();

		// Sitemap on/off — Rank Math runs it as a module.
		$modules = get_option( 'rank_math_modules', null );
		if ( is_array( $modules ) ) {
			$set['seo_sitemap_enable'] = in_array( 'sitemap', $modules, true );
			$imported[]                = 'Sitemap ' . ( $set['seo_sitemap_enable'] ? 'on' : 'off' );
			self::apply( $set );
		}

		$posts = self::map_rankmath_postmeta();
		if ( $posts > 0 ) {
			$imported[] = $posts . ' per-page SEO title/description/noindex set(s)';
		}

		if ( empty( $imported ) ) {
			return array( 'ok' => false, 'message' => 'Rank Math was found, but nothing mappable was set.' );
		}
		return array(
			'ok'       => true,
			'message'  => 'Imported from Rank Math SEO. Existing Velox per-page values were not overwritten.',
			'imported' => $imported,
		);
	}

	private static function map_rankmath_postmeta() {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
			 WHERE meta_key IN ('rank_math_title','rank_math_description','rank_math_robots')",
			ARRAY_A
		);
		if ( empty( $rows ) ) {
			return 0;
		}
		$touched = array();
		foreach ( $rows as $row ) {
			$pid = (int) $row['post_id'];
			$val = (string) $row['meta_value'];
			switch ( $row['meta_key'] ) {
				case 'rank_math_title':
					if ( '' !== trim( $val ) && '' === (string) get_post_meta( $pid, '_velox_seo_title', true ) ) {
						update_post_meta( $pid, '_velox_seo_title', self::strip_rankmath_vars( $val ) );
						$touched[ $pid ] = true;
					}
					break;
				case 'rank_math_description':
					if ( '' !== trim( $val ) && '' === (string) get_post_meta( $pid, '_velox_seo_desc', true ) ) {
						update_post_meta( $pid, '_velox_seo_desc', self::strip_rankmath_vars( $val ) );
						$touched[ $pid ] = true;
					}
					break;
				case 'rank_math_robots':
					$robots = maybe_unserialize( $val );
					if ( is_array( $robots ) && in_array( 'noindex', $robots, true ) && '' === (string) get_post_meta( $pid, '_velox_seo_noindex', true ) ) {
						update_post_meta( $pid, '_velox_seo_noindex', '1' );
						$touched[ $pid ] = true;
					}
					break;
			}
		}
		return count( $touched );
	}

	private static function strip_rankmath_vars( $s ) {
		// Rank Math uses single-% variables, e.g. %title%, %sitename%, %sep%, %page%.
		$s = str_replace(
			array( '%title%', '%sitename%', '%page%', '%sep%', '%primary_category%', '%excerpt%', '%pt_single%' ),
			array( get_the_title(), get_bloginfo( 'name' ), '', '|', '', '', '' ),
			(string) $s
		);
		$s = preg_replace( '/%[a-z0-9_()]+%/i', '', $s );
		return trim( preg_replace( '/\s{2,}/', ' ', $s ) );
	}

	/* ---------------- All in One SEO ---------------- */

	public static function import_aioseo() {
		$imported = array();
		$set      = array();

		// Sitemap on/off from the aioseo_options JSON blob.
		$opts = get_option( 'aioseo_options', '' );
		$o    = is_string( $opts ) ? json_decode( $opts, true ) : ( is_array( $opts ) ? $opts : null );
		if ( is_array( $o ) && isset( $o['sitemap']['general']['enable'] ) ) {
			$set['seo_sitemap_enable'] = ! empty( $o['sitemap']['general']['enable'] );
			$imported[]                = 'Sitemap ' . ( $set['seo_sitemap_enable'] ? 'on' : 'off' );
			self::apply( $set );
		}

		$posts = self::map_aioseo_posts();
		if ( $posts > 0 ) {
			$imported[] = $posts . ' per-page SEO title/description/noindex set(s)';
		}

		if ( empty( $imported ) ) {
			return array( 'ok' => false, 'message' => 'All in One SEO was found, but nothing mappable was set.' );
		}
		return array(
			'ok'       => true,
			'message'  => 'Imported from All in One SEO. Existing Velox per-page values were not overwritten.',
			'imported' => $imported,
		);
	}

	private static function map_aioseo_posts() {
		global $wpdb;
		$touched = array();

		// AIOSEO v4 keeps per-post SEO in its own table.
		$table   = $wpdb->prefix . 'aioseo_posts';
		$has_tbl = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
		if ( $has_tbl ) {
			$rows = $wpdb->get_results( "SELECT post_id, title, description, robots_default, robots_noindex FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB
			foreach ( (array) $rows as $row ) {
				$pid = (int) $row['post_id'];
				if ( ! $pid ) {
					continue;
				}
				$title = self::strip_aioseo_vars( (string) $row['title'] );
				$desc  = self::strip_aioseo_vars( (string) $row['description'] );
				if ( '' !== $title && '' === (string) get_post_meta( $pid, '_velox_seo_title', true ) ) {
					update_post_meta( $pid, '_velox_seo_title', $title );
					$touched[ $pid ] = true;
				}
				if ( '' !== $desc && '' === (string) get_post_meta( $pid, '_velox_seo_desc', true ) ) {
					update_post_meta( $pid, '_velox_seo_desc', $desc );
					$touched[ $pid ] = true;
				}
				if ( empty( $row['robots_default'] ) && ! empty( $row['robots_noindex'] ) && '' === (string) get_post_meta( $pid, '_velox_seo_noindex', true ) ) {
					update_post_meta( $pid, '_velox_seo_noindex', '1' );
					$touched[ $pid ] = true;
				}
			}
		}

		// AIOSEO v3 (and earlier) used postmeta — pick these up too if present.
		$rows = $wpdb->get_results(
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
			 WHERE meta_key IN ('_aioseop_title','_aioseop_description','_aioseop_noindex')",
			ARRAY_A
		);
		foreach ( (array) $rows as $row ) {
			$pid = (int) $row['post_id'];
			$val = (string) $row['meta_value'];
			if ( '' === trim( $val ) ) {
				continue;
			}
			switch ( $row['meta_key'] ) {
				case '_aioseop_title':
					if ( '' === (string) get_post_meta( $pid, '_velox_seo_title', true ) ) {
						update_post_meta( $pid, '_velox_seo_title', self::strip_aioseo_vars( $val ) );
						$touched[ $pid ] = true;
					}
					break;
				case '_aioseop_description':
					if ( '' === (string) get_post_meta( $pid, '_velox_seo_desc', true ) ) {
						update_post_meta( $pid, '_velox_seo_desc', self::strip_aioseo_vars( $val ) );
						$touched[ $pid ] = true;
					}
					break;
				case '_aioseop_noindex':
					if ( ( 'on' === $val || '1' === $val ) && '' === (string) get_post_meta( $pid, '_velox_seo_noindex', true ) ) {
						update_post_meta( $pid, '_velox_seo_noindex', '1' );
						$touched[ $pid ] = true;
					}
					break;
			}
		}

		return count( $touched );
	}

	private static function strip_aioseo_vars( $s ) {
		// AIOSEO smart tags, e.g. #post_title #separator_sa #site_title #tagline.
		$s = str_replace(
			array( '#post_title', '#site_title', '#separator_sa', '#tagline', '#current_date', '#page_number' ),
			array( get_the_title(), get_bloginfo( 'name' ), '|', get_bloginfo( 'description' ), '', '' ),
			(string) $s
		);
		$s = preg_replace( '/#[a-z0-9_]+/i', '', $s );
		return trim( preg_replace( '/\s{2,}/', ' ', $s ) );
	}

	/* ---------------- SEOPress ---------------- */

	public static function import_seopress() {
		$imported = array();
		$set      = array();

		// Sitemap on/off.
		$sm = get_option( 'seopress_xml_sitemap_option_name', array() );
		if ( is_array( $sm ) && isset( $sm['seopress_xml_sitemap_general_enable'] ) ) {
			$set['seo_sitemap_enable'] = ! empty( $sm['seopress_xml_sitemap_general_enable'] );
			$imported[]                = 'Sitemap ' . ( $set['seo_sitemap_enable'] ? 'on' : 'off' );
			self::apply( $set );
		}

		$posts = self::map_seopress_postmeta();
		if ( $posts > 0 ) {
			$imported[] = $posts . ' per-page SEO title/description/noindex set(s)';
		}

		if ( empty( $imported ) ) {
			return array( 'ok' => false, 'message' => 'SEOPress was found, but nothing mappable was set.' );
		}
		return array(
			'ok'       => true,
			'message'  => 'Imported from SEOPress. Existing Velox per-page values were not overwritten.',
			'imported' => $imported,
		);
	}

	private static function map_seopress_postmeta() {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
			 WHERE meta_key IN ('_seopress_titles_title','_seopress_titles_desc','_seopress_robots_index')",
			ARRAY_A
		);
		if ( empty( $rows ) ) {
			return 0;
		}
		$touched = array();
		foreach ( $rows as $row ) {
			$pid = (int) $row['post_id'];
			$val = (string) $row['meta_value'];
			switch ( $row['meta_key'] ) {
				case '_seopress_titles_title':
					// SEOPress uses %%var%% tokens, same shape as Yoast.
					if ( '' !== trim( $val ) && '' === (string) get_post_meta( $pid, '_velox_seo_title', true ) ) {
						update_post_meta( $pid, '_velox_seo_title', self::strip_yoast_vars( $val ) );
						$touched[ $pid ] = true;
					}
					break;
				case '_seopress_titles_desc':
					if ( '' !== trim( $val ) && '' === (string) get_post_meta( $pid, '_velox_seo_desc', true ) ) {
						update_post_meta( $pid, '_velox_seo_desc', self::strip_yoast_vars( $val ) );
						$touched[ $pid ] = true;
					}
					break;
				case '_seopress_robots_index':
					// SEOPress stores 'yes' on this key when the page should be noindex.
					if ( 'yes' === $val && '' === (string) get_post_meta( $pid, '_velox_seo_noindex', true ) ) {
						update_post_meta( $pid, '_velox_seo_noindex', '1' );
						$touched[ $pid ] = true;
					}
					break;
			}
		}
		return count( $touched );
	}

	/* ----------------------------------------------------------------- *
	 * WP Mail SMTP → Velox Mail connection
	 * ----------------------------------------------------------------- */

	public static function import_wpmailsmtp() {
		$conf = get_option( 'wp_mail_smtp', array() );
		if ( ! is_array( $conf ) || empty( $conf['smtp'] ) || empty( $conf['smtp']['host'] ) ) {
			return array( 'ok' => false, 'message' => 'No WP Mail SMTP host found on this site.' );
		}
		if ( ! class_exists( 'Velox_Mail' ) ) {
			return array( 'ok' => false, 'message' => 'Velox Mail module is unavailable.' );
		}
		$smtp = $conf['smtp'];
		$mail = isset( $conf['mail'] ) ? $conf['mail'] : array();

		$secure = 'none';
		if ( ! empty( $smtp['encryption'] ) && in_array( $smtp['encryption'], array( 'ssl', 'tls' ), true ) ) {
			$secure = $smtp['encryption'];
		}

		$conn = array(
			'id'        => 'wpms_' . substr( md5( (string) $smtp['host'] . wp_rand() ), 0, 8 ),
			'label'     => 'WP Mail SMTP (' . $smtp['host'] . ')',
			'host'      => (string) $smtp['host'],
			'port'      => isset( $smtp['port'] ) ? (int) $smtp['port'] : 587,
			'secure'    => $secure,
			'user'      => isset( $smtp['user'] ) ? (string) $smtp['user'] : '',
			'pass'      => isset( $smtp['pass'] ) ? (string) $smtp['pass'] : '',
			'from'      => isset( $mail['from_email'] ) ? (string) $mail['from_email'] : '',
			'from_name' => isset( $mail['from_name'] ) ? (string) $mail['from_name'] : '',
		);

		// Append to any existing connections rather than replacing them.
		$conns   = Velox_Mail::connections();
		$conns[] = $conn;
		$primary = Velox_Mail::primary_id();
		if ( '' === $primary ) {
			$primary = $conn['id'];
		}
		$res = Velox_Mail::save_routing( $conns, Velox_Mail::routes(), $primary, Velox_Mail::fallback_id() );

		if ( empty( $res['ok'] ) ) {
			return array( 'ok' => false, 'message' => 'Could not save the imported connection.' );
		}
		$imported = array( 'SMTP connection: ' . $conn['host'] . ':' . $conn['port'] . ' (' . strtoupper( $secure ) . ')' );
		if ( '' !== $conn['from'] ) {
			$imported[] = 'From: ' . $conn['from'];
		}
		return array(
			'ok'       => true,
			'message'  => 'Imported the WP Mail SMTP connection. Send a test from Mail & forms, then switch SMTP on.',
			'imported' => $imported,
			'note'     => ! empty( $smtp['pass'] ) ? '' : 'No password was stored in plain text (it may be in a constant) — re-enter it if sends fail.',
		);
	}

	/* ---------------- Shared SMTP import ---------------- */

	/** Build a Velox mail connection from generic SMTP fields and append it (never replaces existing). */
	private static function save_imported_smtp( $label, $host, $port, $secure, $user, $pass, $from, $from_name ) {
		if ( ! class_exists( 'Velox_Mail' ) ) {
			return array( 'ok' => false, 'message' => 'Velox Mail module is unavailable.' );
		}
		$host = trim( (string) $host );
		if ( '' === $host ) {
			return array( 'ok' => false, 'message' => 'No SMTP host was configured on this site.' );
		}
		$secure = in_array( $secure, array( 'ssl', 'tls' ), true ) ? $secure : 'none';
		$conn   = array(
			'id'        => 'imp_' . substr( md5( $host . wp_rand() ), 0, 8 ),
			'label'     => $label . ' (' . $host . ')',
			'host'      => $host,
			'port'      => $port ? (int) $port : 587,
			'secure'    => $secure,
			'user'      => (string) $user,
			'pass'      => (string) $pass,
			'from'      => (string) $from,
			'from_name' => (string) $from_name,
		);
		$conns   = Velox_Mail::connections();
		$conns[] = $conn;
		$primary = Velox_Mail::primary_id();
		if ( '' === $primary ) {
			$primary = $conn['id'];
		}
		$res = Velox_Mail::save_routing( $conns, Velox_Mail::routes(), $primary, Velox_Mail::fallback_id() );
		if ( empty( $res['ok'] ) ) {
			return array( 'ok' => false, 'message' => 'Could not save the imported connection.' );
		}
		$imported = array( 'SMTP connection: ' . $conn['host'] . ':' . $conn['port'] . ' (' . strtoupper( $secure ) . ')' );
		if ( '' !== $conn['from'] ) {
			$imported[] = 'From: ' . $conn['from'];
		}
		return array(
			'ok'       => true,
			'message'  => 'Imported the ' . $label . ' connection. Send a test from Mail & forms, then switch SMTP on.',
			'imported' => $imported,
			'note'     => '' !== $conn['pass'] ? '' : 'No usable password was stored (often encrypted or in a constant) — re-enter it if sends fail.',
		);
	}

	public static function import_fluentsmtp() {
		$s = get_option( 'fluentmail-settings', array() );
		if ( ! is_array( $s ) || empty( $s['connections'] ) || ! is_array( $s['connections'] ) ) {
			return array( 'ok' => false, 'message' => 'No FluentSMTP connections found on this site.' );
		}
		foreach ( $s['connections'] as $c ) {
			$ps = isset( $c['provider_settings'] ) && is_array( $c['provider_settings'] ) ? $c['provider_settings'] : array();
			if ( empty( $ps['host'] ) ) {
				continue;
			}
			return self::save_imported_smtp(
				'FluentSMTP',
				$ps['host'],
				isset( $ps['port'] ) ? $ps['port'] : 587,
				isset( $ps['encryption'] ) ? $ps['encryption'] : 'none',
				isset( $ps['username'] ) ? $ps['username'] : '',
				isset( $ps['password'] ) ? $ps['password'] : '',
				isset( $ps['sender_email'] ) ? $ps['sender_email'] : '',
				isset( $ps['sender_name'] ) ? $ps['sender_name'] : ''
			);
		}
		return array( 'ok' => false, 'message' => 'FluentSMTP was found, but no SMTP host is configured.' );
	}

	public static function import_postsmtp() {
		$o = get_option( 'postman_options', array() );
		if ( ! is_array( $o ) || empty( $o['hostname'] ) ) {
			return array( 'ok' => false, 'message' => 'No Post SMTP host found on this site.' );
		}
		$sec = isset( $o['security'] ) ? $o['security'] : ( isset( $o['enc_type'] ) ? $o['enc_type'] : 'none' );
		return self::save_imported_smtp(
			'Post SMTP',
			$o['hostname'],
			isset( $o['port'] ) ? $o['port'] : 587,
			$sec,
			isset( $o['basic_auth_username'] ) ? $o['basic_auth_username'] : '',
			isset( $o['basic_auth_password'] ) ? $o['basic_auth_password'] : '',
			isset( $o['sender_email'] ) ? $o['sender_email'] : '',
			isset( $o['sender_name'] ) ? $o['sender_name'] : ''
		);
	}

	public static function import_easywpsmtp() {
		$o = get_option( 'swpsmtp_options', array() );
		if ( ! is_array( $o ) || empty( $o['smtp_settings']['host'] ) ) {
			return array( 'ok' => false, 'message' => 'No Easy WP SMTP host found on this site.' );
		}
		$sm  = $o['smtp_settings'];
		$enc = isset( $sm['type_encryption'] ) ? $sm['type_encryption'] : ( isset( $sm['type_encr'] ) ? $sm['type_encr'] : 'none' );
		// Easy WP SMTP base64-encodes the stored password on older versions.
		$pass = isset( $sm['password'] ) ? (string) $sm['password'] : '';
		if ( '' !== $pass ) {
			$decoded = base64_decode( $pass, true );
			if ( false !== $decoded && base64_encode( $decoded ) === $pass ) {
				$pass = $decoded;
			}
		}
		return self::save_imported_smtp(
			'Easy WP SMTP',
			$sm['host'],
			isset( $sm['port'] ) ? $sm['port'] : 587,
			$enc,
			isset( $sm['username'] ) ? $sm['username'] : '',
			$pass,
			isset( $o['from_email'] ) ? $o['from_email'] : '',
			isset( $o['from_name'] ) ? $o['from_name'] : ''
		);
	}

	/* ----------------------------------------------------------------- *
	 * Helpers
	 * ----------------------------------------------------------------- */

	private static function apply( $set ) {
		if ( empty( $set ) ) {
			return;
		}
		$all = Velox_Settings::all();
		foreach ( $set as $k => $v ) {
			$all[ $k ] = $v;
		}
		Velox_Settings::save( $all );
	}

	private static function pretty( $key ) {
		$key = preg_replace( '/^(perf_|cache_)/', '', $key );
		return ucfirst( str_replace( '_', ' ', $key ) );
	}
}
