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
	 * The full default configuration. Anything aggressive is OFF by default so
	 * a fresh install never changes how a site renders until the user opts in.
	 */
	public static function defaults() {
		return array(
			// Master switches for each module.
			'module_images'      => true,
			'module_media'       => true,
			'module_performance' => true,
			'module_database'    => true,

			// Image optimizer.
			'webp_quality'        => 80,
			'webp_keep_original'  => true,   // never delete source files
			'webp_auto_convert'   => false,  // convert new uploads automatically
			'webp_convert_sizes'  => true,   // also convert generated thumbnail sizes
			'webp_serve_rewrite'  => false,  // swap <img> to webp on the front end (opt-in)

			// Performance (all complement WP Fastest Cache; none duplicate page cache).
			'perf_disable_emojis'      => true,
			'perf_disable_embeds'      => false,
			'perf_remove_query_strings'=> false,
			'perf_disable_xmlrpc'      => true,
			'perf_clean_head'          => true,   // RSD, wlwmanifest, shortlink, generator
			'perf_disable_dashicons'   => false,  // only for logged-out visitors
			'perf_disable_jquery_migrate' => false, // Oxygen Bloat Eliminator may already do this
			'perf_limit_revisions'     => false,
			'perf_revisions_keep'      => 5,
			'perf_heartbeat'           => 'default', // default | slow | off
			'perf_defer_js'            => false,  // risky with Oxygen; has an exclusion list
			'perf_defer_exclude'       => "jquery\noxygen\nfluentform",
			'perf_dns_prefetch'        => "https://cdnjs.cloudflare.com\nhttps://fonts.gstatic.com",
			'perf_lazy_native'         => false,  // WPFC usually handles this already

			// Database cleanup (manual button per item; nothing runs on a schedule unless enabled).
			'db_schedule_cleanup'      => false,  // weekly auto-clean

			// Updater.
			'gh_token'                 => '',     // optional, only needed for a PRIVATE repo
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

	/**
	 * True only when both the parent module and the feature itself are on.
	 * Example: Velox_Settings::enabled( 'perf_disable_emojis', 'module_performance' )
	 */
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
}
