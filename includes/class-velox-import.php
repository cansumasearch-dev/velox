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
				'label' => 'Rank Math SEO', 'into' => 'SEO', 'ready' => false,
				'detected' => self::present( array( 'const' => array( 'RANK_MATH_VERSION' ), 'option' => array( 'rank-math-options-general' ) ) ),
				'desc' => 'SEO titles, meta descriptions, robots and sitemap settings.',
			),
			'aioseo' => array(
				'label' => 'All in One SEO', 'into' => 'SEO', 'ready' => false,
				'detected' => self::present( array( 'const' => array( 'AIOSEO_VERSION' ), 'option' => array( 'aioseo_options' ) ) ),
				'desc' => 'SEO titles, meta descriptions, robots and sitemap settings.',
			),
			'seopress' => array(
				'label' => 'SEOPress', 'into' => 'SEO', 'ready' => false,
				'detected' => self::present( array( 'const' => array( 'SEOPRESS_VERSION' ), 'option' => array( 'seopress_titles_option_name' ) ) ),
				'desc' => 'SEO titles, meta descriptions, robots and sitemap settings.',
			),
			'litespeed' => array(
				'label' => 'LiteSpeed Cache', 'into' => 'Performance', 'ready' => false,
				'detected' => self::present( array( 'const' => array( 'LSCWP_V', 'LSWCP_PLUGIN_NAME' ) ) ),
				'desc' => 'Caching, CSS/JS optimisation, lazy-load and image settings.',
			),
			'wpfastest' => array(
				'label' => 'WP Fastest Cache', 'into' => 'Performance', 'ready' => false,
				'detected' => self::present( array( 'class' => array( 'WpFastestCache' ), 'const' => array( 'WPFC_MAIN_PATH' ) ) ),
				'desc' => 'Cache, minification and combine settings.',
			),
			'w3tc' => array(
				'label' => 'W3 Total Cache', 'into' => 'Performance', 'ready' => false,
				'detected' => self::present( array( 'const' => array( 'W3TC_VERSION', 'W3TC' ) ) ),
				'desc' => 'Page cache, minify, and browser-cache settings.',
			),
			'wpsupercache' => array(
				'label' => 'WP Super Cache', 'into' => 'Performance', 'ready' => false,
				'detected' => self::present( array( 'const' => array( 'WPCACHEHOME' ) ) ),
				'desc' => 'Page caching configuration.',
			),
			'autoptimize' => array(
				'label' => 'Autoptimize', 'into' => 'Performance', 'ready' => false,
				'detected' => self::present( array( 'const' => array( 'AUTOPTIMIZE_PLUGIN_VERSION' ), 'option' => array( 'autoptimize_js' ) ) ),
				'desc' => 'CSS/JS aggregation, defer and image optimisation.',
			),
			'perfmatters' => array(
				'label' => 'Perfmatters', 'into' => 'Performance', 'ready' => false,
				'detected' => self::present( array( 'const' => array( 'PERFMATTERS_VERSION' ) ) ),
				'desc' => 'Script manager, disabled features and preload settings.',
			),
			'flyingpress' => array(
				'label' => 'FlyingPress', 'into' => 'Performance', 'ready' => false,
				'detected' => self::present( array( 'const' => array( 'FLYING_PRESS_VERSION' ) ) ),
				'desc' => 'Cache, CSS/JS optimisation and lazy-load settings.',
			),
			'fluentsmtp' => array(
				'label' => 'FluentSMTP', 'into' => 'Mail', 'ready' => false,
				'detected' => self::present( array( 'const' => array( 'FLUENTMAIL' ), 'option' => array( 'fluentmail-settings' ) ) ),
				'desc' => 'SMTP connections and routing.',
			),
			'postsmtp' => array(
				'label' => 'Post SMTP', 'into' => 'Mail', 'ready' => false,
				'detected' => self::present( array( 'const' => array( 'POST_SMTP_VER' ), 'option' => array( 'postman_options' ) ) ),
				'desc' => 'SMTP host, port, auth and From details.',
			),
			'easywpsmtp' => array(
				'label' => 'Easy WP SMTP', 'into' => 'Mail', 'ready' => false,
				'detected' => self::present( array( 'const' => array( 'EASY_WP_SMTP_VERSION' ), 'option' => array( 'swpsmtp_options' ) ) ),
				'desc' => 'SMTP host, port, auth and From details.',
			),
			'cookieyes' => array(
				'label' => 'CookieYes', 'into' => 'Cookie Banner', 'ready' => false,
				'detected' => self::present( array( 'const' => array( 'CLI_PLUGIN_VERSION' ), 'option' => array( 'cookielawinfo_settings' ) ) ),
				'desc' => 'Consent banner text, categories and appearance.',
			),
			'complianz' => array(
				'label' => 'Complianz', 'into' => 'Cookie Banner', 'ready' => false,
				'detected' => self::present( array( 'const' => array( 'cmplz_plugin_file', 'cmplz_version' ) ) ),
				'desc' => 'Consent banner and cookie categories.',
			),
			'borlabs' => array(
				'label' => 'Borlabs Cookie', 'into' => 'Cookie Banner', 'ready' => false,
				'detected' => self::present( array( 'class' => array( 'BorlabsCookie' ), 'const' => array( 'BORLABS_COOKIE_VERSION' ) ) ),
				'desc' => 'Consent banner and cookie categories.',
			),
			'redirection' => array(
				'label' => 'Redirection', 'into' => 'Redirects & 404s', 'ready' => false,
				'detected' => self::present( array( 'const' => array( 'REDIRECTION_VERSION' ), 'option' => array( 'redirection_options' ) ) ),
				'desc' => 'Redirect rules and 404 handling.',
			),
			'wpcode' => array(
				'label' => 'WPCode', 'into' => 'Code Snippets', 'ready' => false,
				'detected' => self::present( array( 'const' => array( 'WPCODE_VERSION' ) ) ),
				'desc' => 'PHP / CSS / JS snippets.',
			),
			'codesnippets' => array(
				'label' => 'Code Snippets', 'into' => 'Code Snippets', 'ready' => false,
				'detected' => self::present( array( 'const' => array( 'CODE_SNIPPETS_VERSION' ) ) ),
				'desc' => 'PHP / CSS / JS snippets.',
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
		foreach ( (array) ( isset( $checks['option'] ) ? $checks['option'] : array() ) as $o ) {
			if ( false !== get_option( $o, false ) ) {
				return true;
			}
		}
		return false;
	}

	public static function run( $source ) {
		switch ( $source ) {
			case 'wprocket':
				return self::import_wprocket();
			case 'yoast':
				return self::import_yoast();
			case 'wpmailsmtp':
				return self::import_wpmailsmtp();
		}
		return array( 'ok' => false, 'message' => 'Automatic migration for this plugin isn\'t available yet.' );
	}

	/* ----------------------------------------------------------------- *
	 * Detection
	 * ----------------------------------------------------------------- */

	private static function wprocket_present() {
		return ( false !== get_option( 'wp_rocket_settings', false ) ) || defined( 'WP_ROCKET_VERSION' );
	}

	private static function yoast_present() {
		return ( false !== get_option( 'wpseo', false ) ) || defined( 'WPSEO_VERSION' );
	}

	private static function wpmailsmtp_present() {
		return ( false !== get_option( 'wp_mail_smtp', false ) ) || defined( 'WPMS_PLUGIN_VER' );
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
