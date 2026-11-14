<?php
/**
 * Velox — local Google Fonts, working the way OMGF (Optimize My Google Fonts)
 * does, setting for setting.
 *
 * How it works:
 *  - Every front-end (and login) page is post-processed. Each <link> pointing at
 *    the Google Fonts API (or Bunny Fonts / fonts-api.wp.com) gets its URL
 *    swapped in place for a local stylesheet — one per stylesheet handle —
 *    keeping the element's id, rel, media and position.
 *  - A stylesheet that hasn't been seen before is fetched, filtered to the
 *    chosen subsets, stripped of the variants marked "Don't load", and its
 *    woff2 files are downloaded into uploads/velox-fonts/{handle}/. The list
 *    of fonts grows on its own as other pages are visited.
 *  - Preconnect / dns-prefetch / preload hints to the Google font hosts are
 *    removed, and "Load early" variants are preloaded from the local files.
 *  - "Save & optimize" loads the front page with a one-time key so everything
 *    is fetched again; "Optimize this page" (admin bar) does the same for any
 *    page.
 *
 * Velox stores the generated files in uploads/velox-fonts/ instead of
 * uploads/omgf/, and its settings live with the other Performance settings.
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Local_Fonts {

	const DIRNAME   = 'velox-fonts';
	const OPT_FONTS = 'velox_fonts_optimized';       // every font seen, per handle (admin list)
	const OPT_FRONT = 'velox_fonts_optimized_front'; // the same without the unloaded variants
	const OPT_PRE   = 'velox_fonts_preload';         // [handle][font_id][variant_id]
	const OPT_UNL   = 'velox_fonts_unload';          // [handle][font_id][variant_id]
	const OPT_META  = 'velox_fonts_meta';            // cache keys, unloaded handles, timestamp, flags
	const OPT_FAIL  = 'velox_fonts_failures';
	const OPT_CHECK = 'velox_fonts_checker';
	const OPT_SEEN  = 'velox_fonts_seen';

	/** Query arg that re-optimizes a page for a logged-in admin. */
	const RUN_ARG = 'vx_fonts_optimize';

	/** Hosts serving Google-Fonts-compatible stylesheets. */
	const API_HOSTS = array( 'fonts.googleapis.com', 'fonts.bunny.net', 'fonts-api.wp.com' );

	/** Hosts whose resource hints are dropped once fonts are local. */
	const HINT_HOSTS = array( 'fonts.googleapis.com', 'fonts.gstatic.com', 'fonts.bunny.net', 'fonts-api.wp.com' );

	const UA        = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:124.0) Gecko/20100101 Firefox/124.0';
	const UA_LEGACY = 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:115.0) Gecko/20100101 Firefox/115.0';

	/** Subsets Google offers, in the order OMGF lists them. */
	const SUBSETS = array(
		'arabic' => 'Arabic', 'bengali' => 'Bengali', 'chinese-hongkong' => 'Chinese (Hong Kong)',
		'chinese-simplified' => 'Chinese (Simplified)', 'chinese-traditional' => 'Chinese (Traditional)',
		'cyrillic' => 'Cyrillic', 'cyrillic-ext' => 'Cyrillic Extended', 'devanagari' => 'Devanagari',
		'greek' => 'Greek', 'greek-ext' => 'Greek Extended', 'gujarati' => 'Gujarati', 'gurmukhi' => 'Gurmukhi',
		'hebrew' => 'Hebrew', 'japanese' => 'Japanese', 'kannada' => 'Kannada', 'khmer' => 'Khmer',
		'korean' => 'Korean', 'latin' => 'Latin', 'latin-ext' => 'Latin Extended', 'malayalam' => 'Malayalam',
		'math' => 'Math', 'myanmar' => 'Myanmar', 'oriya' => 'Oriya', 'sinhala' => 'Sinhala',
		'symbols' => 'Symbols', 'tamil' => 'Tamil', 'telugu' => 'Telugu', 'thai' => 'Thai',
		'tibetan' => 'Tibetan', 'vietnamese' => 'Vietnamese',
	);

	/** Builder previews / editors that must never be rewritten. */
	const SKIP_ARGS = array(
		'bt-beaverbuildertheme', 'ct_builder', 'elementor-preview', 'et_fb', 'fb-edit', 'fl_builder',
		'op3editor', 'siteorigin_panels_live_editor', 'tve', 'vc_action', 'perfmatters', 'tqb_quiz',
		'vx_nofonts', 'breakdance', 'bricks', 'oxygen',
	);

	/** True while this request re-optimizes (fresh fetch, existing files ignored). */
	private static $running = false;

	public static function init() {
		$on = (bool) Velox_Settings::get( 'perf_local_fonts', false );

		// Admin side: AJAX lives in Velox_Ajax; these keep the cache in step.
		add_action( 'update_option_' . Velox_Settings::OPTION, array( __CLASS__, 'settings_changed' ), 10, 2 );
		add_action( 'admin_post_velox_fonts_log', array( __CLASS__, 'download_log' ) );
		if ( $on ) {
			// Same automatic clean-ups OMGF does.
			add_action( 'switch_theme', array( __CLASS__, 'flush_all' ) );
			foreach ( array(
				'upgrader_process_complete', 'permalink_structure_changed', 'oxygen_vsb_post_compiled',
				'elementor/core/files/clear_cache', 'elementor/editor/after_save', 'et_core_static_resources_removed',
				'et_save_post', 'fl_builder_after_save_layout', 'bricks/builder/save_post', 'fusion_cache_reset_after',
			) as $hook ) {
				add_action( $hook, array( __CLASS__, 'flush_orphans' ) );
			}
		}

		if ( ! $on || is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		add_action( 'init', array( __CLASS__, 'boot_frontend' ), 50 );
	}

	public static function boot_frontend() {
		self::$running = self::is_running_optimize();
		if ( self::$running && ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		// Test mode: only admins (or ?vxfonts=1) see the optimized fonts.
		if ( Velox_Settings::get( 'perf_fonts_test_mode', false ) && ! self::$running
			&& ! current_user_can( 'manage_options' ) && ! isset( $_GET['vxfonts'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( isset( $_GET['vxfonts'] ) && ! defined( 'DONOTCACHEPAGE' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			define( 'DONOTCACHEPAGE', true );
		}
		// Priority 3: inside Velox's page-cache buffer, so the cached copy already
		// has the local fonts, and before the other Velox page passes.
		add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ), 3 );
		add_action( 'login_init', array( __CLASS__, 'start_buffer' ), 3 );
		add_action( 'wp_head', array( __CLASS__, 'print_preloads' ), 3 );
		if ( self::$running ) {
			add_action( 'wp_body_open', array( __CLASS__, 'success_banner' ) );
		}
	}

	/* =================================================================
	 * Paths, options, logging
	 * ================================================================= */

	public static function dir() {
		$up = wp_upload_dir( null, false );
		return array(
			'path' => trailingslashit( $up['basedir'] ) . self::DIRNAME,
			// Protocol-relative, like OMGF, so http/https mixups can't break fonts.
			'url'  => preg_replace( '#^https?:#i', '', trailingslashit( $up['baseurl'] ) . self::DIRNAME ),
		);
	}

	public static function meta() {
		$m = get_option( self::OPT_META, array() );
		return wp_parse_args( is_array( $m ) ? $m : array(), array(
			'cache_keys'         => array(), // handle => directory key
			'unload_stylesheets' => array(), // handles whose every variant is unloaded
			'timestamp'          => 0,
			'has_run'            => false,
			'stale'              => false,
			'sources'            => array(), // handle => Google URL, so it can be rebuilt without a page load
		) );
	}

	private static function save_meta( array $m ) {
		update_option( self::OPT_META, $m, false );
	}

	public static function timestamp() {
		$m = self::meta();
		if ( empty( $m['timestamp'] ) ) {
			$m['timestamp'] = time();
			self::save_meta( $m );
		}
		return (int) $m['timestamp'];
	}

	private static function opt( $name ) {
		$v = get_option( $name, array() );
		return is_array( $v ) ? $v : array();
	}

	public static function subsets() {
		$raw  = (string) Velox_Settings::get( 'perf_fonts_subsets', 'latin,latin-ext' );
		$list = array_values( array_intersect( array_map( 'trim', explode( ',', $raw ) ), array_keys( self::SUBSETS ) ) );
		return $list ? $list : array( 'latin', 'latin-ext' );
	}

	public static function display() {
		$d = (string) Velox_Settings::get( 'perf_font_display', 'swap' );
		if ( 'off' === $d ) {
			$d = 'auto'; // the old "leave it" choice is what auto does
		}
		return in_array( $d, array( 'swap', 'auto', 'block', 'fallback', 'optional' ), true ) ? $d : 'swap';
	}

	public static function log_path() {
		return WP_CONTENT_DIR . '/velox-fonts-debug.log';
	}

	public static function log( $msg ) {
		if ( ! Velox_Settings::get( 'perf_fonts_debug', false ) ) {
			return;
		}
		$f = self::log_path();
		if ( is_file( $f ) && filesize( $f ) > MB_IN_BYTES ) {
			return; // capped at 1 MB, like OMGF
		}
		@file_put_contents( $f, '[' . gmdate( 'Y-m-d H:i:s' ) . '] ' . $msg . "\n", FILE_APPEND ); // phpcs:ignore
	}

	/* =================================================================
	 * Front end: buffer, rewrite, hints, preloads
	 * ================================================================= */

	private static function should_process() {
		if ( self::$running ) {
			return true;
		}
		foreach ( self::SKIP_ARGS as $arg ) {
			if ( isset( $_GET[ $arg ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				return false;
			}
		}
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore
		if ( in_array( $action, array( 'edit', 'elementor' ), true ) ) {
			return false;
		}
		if ( isset( $_GET['PageSpeed'] ) && 'off' === $_GET['PageSpeed'] ) { // phpcs:ignore
			return false;
		}
		if ( is_customize_preview() || is_feed() || is_embed() || is_robots() || is_trackback() ) {
			return false;
		}
		if ( class_exists( 'Velox' ) && method_exists( 'Velox', 'is_builder_request' ) && Velox::is_builder_request() ) {
			return false;
		}
		return true;
	}

	public static function start_buffer() {
		if ( self::should_process() ) {
			ob_start( array( __CLASS__, 'buffer' ) );
		}
	}

	public static function buffer( $html ) {
		if ( ! is_string( $html ) || '' === $html || false === stripos( $html, '<html' ) ) {
			return $html;
		}
		// AMP pages are left alone.
		if ( preg_match( '/<html[^>]*\s(amp|⚡)(\s|>|=)/iu', $html ) ) {
			return $html;
		}
		try {
			$out = self::process( $html );
			$out = self::remove_resource_hints( $out );
			if ( self::$running ) {
				$m = self::meta();
				if ( empty( $m['has_run'] ) ) {
					$m['has_run'] = true;
					self::save_meta( $m );
				}
			}
			return ( is_string( $out ) && '' !== $out ) ? $out : $html;
		} catch ( \Throwable $e ) {
			self::log( 'Processing failed: ' . $e->getMessage() );
			return $html;
		}
	}

	/** Every attribute value of an element that is a font API stylesheet URL. */
	private static function element_urls( $element ) {
		$out = array();
		if ( preg_match_all( '/=\s*([\'"])(.*?)\1/s', $element, $m ) ) {
			foreach ( $m[2] as $val ) {
				if ( self::is_api_url( $val ) ) {
					$out[] = $val;
				}
			}
		}
		return array_unique( $out );
	}

	public static function is_api_url( $url ) {
		$url = preg_replace( '/[\s\t\r\n]+/', '', html_entity_decode( (string) $url ) );
		if ( '' === $url ) {
			return false;
		}
		if ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		} elseif ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . $url;
		}
		$p = wp_parse_url( $url );
		if ( empty( $p['host'] ) || ! in_array( strtolower( $p['host'] ), self::API_HOSTS, true ) ) {
			return false;
		}
		return 0 === strpos( isset( $p['path'] ) ? $p['path'] : '', '/css' );
	}

	private static function handle_for( $element, $url ) {
		$handle = '';
		if ( preg_match( '/\bid=([\'"])(.*?)\1/i', $element, $m ) ) {
			$handle = preg_replace( '/-css$/', '', $m[2] );
		}
		if ( '' === $handle ) {
			$handle = 'velox-stylesheet-' . strlen( $url );
		}
		$handle = sanitize_title_with_dashes( strtolower( $handle ) );
		// Divi and Elementor reuse one handle for different font sets per page.
		if ( Velox_Settings::get( 'perf_fonts_compat', false ) ) {
			if ( 0 === strpos( $handle, 'et-builder-googlefonts' ) || 'google-fonts-1' === $handle ) {
				$handle = preg_replace( '/-1$/', '', $handle ) . '-' . strlen( $url );
			}
		}
		return $handle;
	}

	/** Normalize what themes put in the href before reading the query. */
	private static function clean_url( $url ) {
		$url = html_entity_decode( (string) $url );
		$url = str_replace( array( ',&', '&,' ), '&', $url );
		$url = rtrim( $url, ',' );
		if ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		}
		return $url;
	}

	public static function process( $html ) {
		if ( ! preg_match_all( '/<link\b[^>]*?>/is', $html, $links ) ) {
			return $html;
		}
		$meta     = self::meta();
		$dir      = self::dir();
		$ts       = self::timestamp();
		$seen     = array();
		$replace  = array();

		foreach ( array_unique( $links[0] ) as $element ) {
			$urls = self::element_urls( $element );
			if ( ! $urls ) {
				continue;
			}
			$url    = reset( $urls );
			$handle = self::handle_for( $element, $url );
			$seen[] = $handle;

			// Every variant unloaded: the whole stylesheet goes.
			if ( in_array( $handle, (array) $meta['unload_stylesheets'], true ) ) {
				$replace[ $element ] = '';
				continue;
			}
			$key  = ! empty( $meta['cache_keys'][ $handle ] ) ? $meta['cache_keys'][ $handle ] : $handle;
			$file = $dir['path'] . '/' . $key . '/' . $key . '.css';
			$local = $dir['url'] . '/' . $key . '/' . $key . '.css?ver=' . $ts;

			if ( ! self::$running && is_file( $file ) ) {
				$replace[ $element ] = str_replace( $urls, $local, $element );
				continue;
			}
			$clean = self::clean_url( $url );
			$query = array();
			wp_parse_str( (string) wp_parse_url( $clean, PHP_URL_QUERY ), $query );
			if ( empty( $query['family'] ) ) {
				$replace[ $element ] = ''; // nothing to load
				continue;
			}
			$ok = self::optimize_stylesheet( $clean, $handle, $key );
			if ( $ok ) {
				$m = self::meta();
				if ( ! isset( $m['sources'][ $handle ] ) || $m['sources'][ $handle ] !== $clean ) {
					$m['sources'][ $handle ] = $clean;
					self::save_meta( $m );
				}
				$replace[ $element ] = str_replace( $urls, $local, $element );
			}
			// On failure the original link stays, so the page never loses its fonts.
		}

		foreach ( $replace as $from => $to ) {
			$pos = strpos( $html, $from );
			if ( false !== $pos ) {
				$html = substr_replace( $html, $to, $pos, strlen( $from ) );
			}
		}
		if ( $seen ) {
			self::mark_seen( $seen );
		}
		return $html;
	}

	/** Drop preconnect / dns-prefetch / preload hints pointing at the font hosts. */
	public static function remove_resource_hints( $html ) {
		if ( ! preg_match_all( '/<link\b[^>]*?>/is', $html, $links ) ) {
			return $html;
		}
		foreach ( array_unique( $links[0] ) as $el ) {
			if ( ! preg_match( '/\brel=([\'"])\s*([a-z-]+)/i', $el, $rel ) ) {
				continue;
			}
			if ( ! in_array( strtolower( $rel[2] ), array( 'dns-prefetch', 'preconnect', 'preload' ), true ) ) {
				continue;
			}
			if ( ! preg_match( '/\bhref=([\'"])(.*?)\1/i', $el, $href ) ) {
				continue;
			}
			$u    = 0 === strpos( $href[2], '//' ) ? 'https:' . $href[2] : $href[2];
			$host = strtolower( (string) wp_parse_url( $u, PHP_URL_HOST ) );
			if ( in_array( $host, self::HINT_HOSTS, true ) ) {
				$html = str_replace( $el, '', $html );
			}
		}
		return $html;
	}

	public static function print_preloads() {
		$pre   = self::opt( self::OPT_PRE );
		$fonts = self::opt( self::OPT_FRONT );
		if ( ! $pre || ! $fonts ) {
			return;
		}
		$dir  = self::dir();
		$ts   = self::timestamp();
		$done = array();
		$i    = 0;
		foreach ( $pre as $handle => $by_font ) {
			foreach ( (array) $by_font as $font_id => $vids ) {
				$vids = array_filter( (array) $vids, function ( $v ) { return '0' !== (string) $v && '' !== (string) $v; } );
				if ( ! $vids || empty( $fonts[ $handle ][ $font_id ]['variants'] ) ) {
					continue;
				}
				foreach ( $fonts[ $handle ][ $font_id ]['variants'] as $v ) {
					if ( ! in_array( (string) $v['id'], array_map( 'strval', $vids ), true ) || empty( $v['woff2'] ) || isset( $done[ $v['woff2'] ] ) ) {
						continue;
					}
					$path = $dir['path'] . substr( $v['woff2'], strlen( $dir['url'] ) );
					if ( 0 !== strpos( $v['woff2'], $dir['url'] ) || ! is_file( $path ) ) {
						continue; // only preload what is really there
					}
					$done[ $v['woff2'] ] = true;
					$i++;
					echo "<link id='velox-font-preload-" . (int) $i . "' rel='preload' href='" . esc_url( $v['woff2'] . '?ver=' . $ts ) . "' as='font' type='font/woff2' crossorigin />\n";
				}
			}
		}
	}

	public static function success_banner() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div style="position:relative;z-index:99999;background:#16a34a;color:#fff;padding:10px 16px;font:14px/1.4 -apple-system,BlinkMacSystemFont,sans-serif;text-align:center">'
			. esc_html__( 'Google Fonts optimization completed. Return to the settings screen to see the results.', 'velox' )
			. '</div>';
	}

	/* =================================================================
	 * Optimizing one stylesheet
	 * ================================================================= */

	private static function user_agent() {
		return Velox_Settings::get( 'perf_fonts_legacy', false ) ? self::UA_LEGACY : self::UA;
	}

	/** Fetch, filter, download, write. True when the local stylesheet exists afterwards. */
	public static function optimize_stylesheet( $url, $handle, $key ) {
		self::log( "Optimizing {$handle} ({$key}): {$url}" );
		$css = self::fetch_css( $url );
		if ( '' === $css ) {
			return false;
		}
		$fonts = self::parse_css( $css );
		if ( ! $fonts ) {
			self::log( "No usable @font-face rules in {$url}" );
			return false;
		}
		$admin_list = $fonts; // the table shows everything, unloaded variants too
		$front      = self::remove_unloaded( $fonts, $handle );

		$dir   = self::dir();
		$tdir  = $dir['path'] . '/' . $key;
		if ( ! wp_mkdir_p( $tdir ) ) {
			self::log( "Could not create {$tdir}" );
			return false;
		}
		$got = array(); // remote url => local url
		foreach ( $front as $font_id => &$font ) {
			foreach ( $font['variants'] as $vkey => &$v ) {
				$remote = $v['remote'];
				if ( ! isset( $got[ $remote ] ) ) {
					// One file per URL: a variable font serving several weights is
					// downloaded once.
					$shared = 1 < count( array_filter( $font['variants'], function ( $x ) use ( $remote ) { return $x['remote'] === $remote; } ) );
					$name   = sanitize_file_name( strtolower( $font_id . '-' . $v['fontStyle'] . '-' . $v['subset'] . ( $shared ? '' : '-' . str_replace( ' ', '-', $v['fontWeight'] ) ) ) );
					$got[ $remote ] = self::download( $remote, $tdir, $name, $dir['url'] . '/' . $key, $font['family'] );
				}
				$v['woff2'] = $got[ $remote ];
			}
			unset( $v );
		}
		unset( $font );

		// Fill in the local file names for the admin list as well.
		foreach ( $admin_list as $font_id => &$font ) {
			foreach ( $font['variants'] as $vkey => &$v ) {
				$v['woff2'] = isset( $front[ $font_id ]['variants'][ $vkey ]['woff2'] ) ? $front[ $font_id ]['variants'][ $vkey ]['woff2'] : '';
			}
			unset( $v );
		}
		unset( $font );

		$out = self::stylesheet( $front );
		if ( false === @file_put_contents( $tdir . '/' . $key . '.css', $out ) ) { // phpcs:ignore
			self::log( "Could not write {$tdir}/{$key}.css" );
			return false;
		}

		$all = self::opt( self::OPT_FONTS );
		$fr  = self::opt( self::OPT_FRONT );
		$all[ $handle ] = self::strip_remote( $admin_list );
		$fr[ $handle ]  = self::strip_remote( $front );
		update_option( self::OPT_FONTS, $all, false );
		update_option( self::OPT_FRONT, $fr, false );
		self::log( "Wrote {$key}.css with " . count( $front ) . ' famil(ies)' );
		return true;
	}

	private static function strip_remote( array $fonts ) {
		foreach ( $fonts as &$font ) {
			foreach ( $font['variants'] as &$v ) {
				unset( $v['remote'] );
			}
			unset( $v );
		}
		unset( $font );
		return $fonts;
	}

	public static function fetch_css( $url ) {
		// A stylesheet on this site's own host is read from disk.
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( $home && strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) === strtolower( $home ) ) {
			$path = ABSPATH . ltrim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
			return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
		}
		$res = wp_safe_remote_get( $url, array(
			'user-agent'  => self::user_agent(),
			'timeout'     => 30,
			'redirection' => 0,
		) );
		if ( is_wp_error( $res ) ) {
			self::log( 'Fetching ' . $url . ' failed: ' . $res->get_error_message() );
			return '';
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$type = (string) wp_remote_retrieve_header( $res, 'content-type' );
		if ( 200 !== $code || ( '' !== $type && false === stripos( $type, 'css' ) ) ) {
			self::log( "Fetching {$url} returned {$code} {$type}" );
			return '';
		}
		return (string) wp_remote_retrieve_body( $res );
	}

	/**
	 * Google's CSS → [font_id => {id, family, variants => [key => {...}]}],
	 * keeping only the subsets that are switched on.
	 */
	public static function parse_css( $css ) {
		$keep  = self::subsets();
		$fonts = array();
		if ( ! preg_match_all( '#(?:/\*\s*([^*]+?)\s*\*/\s*)?@font-face\s*\{([^}]*)\}#i', $css, $blocks, PREG_SET_ORDER ) ) {
			return $fonts;
		}
		foreach ( $blocks as $b ) {
			$subset = trim( isset( $b[1] ) ? $b[1] : '' );
			$body   = $b[2];
			if ( preg_match( '/^\[(\d+)\]$/', $subset, $n ) ) {
				$subset = 'logogram-' . $n[1]; // CJK slices: always kept
			} elseif ( '' !== $subset && ! in_array( $subset, $keep, true ) ) {
				continue;
			}
			$get = function ( $prop ) use ( $body ) {
				return preg_match( '/' . $prop . '\s*:\s*([^;]+);?/i', $body, $m ) ? trim( $m[1] ) : '';
			};
			$family = trim( $get( 'font-family' ), " '\"" );
			if ( ! preg_match( '/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $body, $u ) || '' === $family || ! preg_match( '/^[\p{L}\p{N} \-_.]+$/u', $family ) ) {
				continue;
			}
			$style  = $get( 'font-style' ) ? $get( 'font-style' ) : 'normal';
			$weight = $get( 'font-weight' ) ? $get( 'font-weight' ) : '400';
			$id     = sanitize_title( $family );
			$vid    = $weight . ( 'italic' === $style ? 'italic' : '' );
			$key    = ( '' !== $subset ? $subset : 'all' ) . '-' . str_replace( ' ', '-', $weight ) . ( 'italic' === $style ? '-italic' : '' );
			if ( ! isset( $fonts[ $id ] ) ) {
				$fonts[ $id ] = array( 'id' => $id, 'family' => $family, 'variants' => array() );
			}
			$fonts[ $id ]['variants'][ $key ] = array(
				'id'          => $vid,
				'fontFamily'  => $family,
				'fontStyle'   => $style,
				'fontWeight'  => $weight,
				'fontStretch' => $get( 'font-stretch' ),
				'subset'      => '' !== $subset ? $subset : 'all',
				'range'       => $get( 'unicode-range' ),
				'remote'      => 0 === strpos( $u[1], '//' ) ? 'https:' . $u[1] : $u[1],
				'woff2'       => '',
			);
		}
		return $fonts;
	}

	private static function remove_unloaded( array $fonts, $handle ) {
		$unl = self::opt( self::OPT_UNL );
		if ( empty( $unl[ $handle ] ) ) {
			return $fonts;
		}
		foreach ( $fonts as $font_id => $font ) {
			$drop = isset( $unl[ $handle ][ $font_id ] ) ? array_map( 'strval', (array) $unl[ $handle ][ $font_id ] ) : array();
			if ( ! $drop ) {
				continue;
			}
			foreach ( $font['variants'] as $k => $v ) {
				if ( in_array( (string) $v['id'], $drop, true ) ) {
					unset( $fonts[ $font_id ]['variants'][ $k ] );
				}
			}
			if ( ! $fonts[ $font_id ]['variants'] ) {
				unset( $fonts[ $font_id ] );
			}
		}
		return $fonts;
	}

	/** Download one font file; returns its local URL or '' (that rule is then left out). */
	public static function download( $remote, $dir, $name, $url_base, $family ) {
		$map = array( 'font/woff2' => 'woff2', 'font/woff' => 'woff', 'font/ttf' => 'ttf', 'font/otf' => 'otf',
			'application/font-woff2' => 'woff2', 'application/font-woff' => 'woff', 'application/x-font-ttf' => 'ttf',
			'font/sfnt' => 'ttf', 'application/vnd.ms-fontobject' => 'eot' );
		foreach ( array( 'woff2', 'woff', 'ttf', 'otf', 'eot' ) as $ext ) {
			if ( is_file( $dir . '/' . $name . '.' . $ext ) && ! self::$running ) {
				return $url_base . '/' . $name . '.' . $ext;
			}
		}
		$tmp = $dir . '/' . $name . '-' . wp_generate_password( 12, false ) . '.tmp';
		$res = null;
		for ( $try = 0; $try < 2; $try++ ) {
			$res = wp_safe_remote_get( $remote, array(
				'timeout'             => 300,
				'stream'              => true,
				'filename'            => $tmp,
				'limit_response_size' => 25 * MB_IN_BYTES,
				'user-agent'          => self::user_agent(),
			) );
			if ( ! is_wp_error( $res ) ) {
				break;
			}
		}
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		$err  = is_wp_error( $res ) ? $res->get_error_message() : ( $code < 200 || $code > 299 ? 'HTTP ' . $code : '' );
		if ( '' === $err && ! self::is_font_file( $tmp ) ) {
			$err = __( 'not a font file', 'velox' );
		}
		if ( '' !== $err ) {
			@unlink( $tmp ); // phpcs:ignore
			self::add_failure( $family, $remote, $err );
			self::log( "Download failed {$remote}: {$err}" );
			return '';
		}
		$type = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $res, 'content-type' ) )[0] ) );
		$ext  = isset( $map[ $type ] ) ? $map[ $type ] : strtolower( pathinfo( (string) wp_parse_url( $remote, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		$ext  = in_array( $ext, array( 'woff2', 'woff', 'ttf', 'otf', 'eot' ), true ) ? $ext : 'woff2';
		@rename( $tmp, $dir . '/' . $name . '.' . $ext ); // phpcs:ignore
		return $url_base . '/' . $name . '.' . $ext;
	}

	private static function is_font_file( $path ) {
		if ( ! is_file( $path ) || filesize( $path ) < 4 ) {
			return false;
		}
		$h = (string) file_get_contents( $path, false, null, 0, 4 );
		if ( in_array( $h, array( 'wOF2', 'wOFF', "\x00\x01\x00\x00", 'true', 'OTTO' ), true ) ) {
			return true;
		}
		// EOT carries its magic number at byte 34.
		return filesize( $path ) > 36 && 'LP' === (string) file_get_contents( $path, false, null, 34, 2 );
	}

	private static function add_failure( $family, $url, $error ) {
		$f = self::opt( self::OPT_FAIL );
		$f[ md5( $url ) ] = array( 'family' => $family, 'url' => $url, 'error' => $error, 'time' => time() );
		update_option( self::OPT_FAIL, array_slice( $f, -20, 20, true ), false );
	}

	public static function stylesheet( array $fonts ) {
		$display = self::display();
		$ts      = self::timestamp();
		$out     = "/**\n * Generated by Velox — Google Fonts hosted on this site.\n */\n\n";
		foreach ( $fonts as $font ) {
			foreach ( $font['variants'] as $v ) {
				if ( empty( $v['woff2'] ) ) {
					continue; // a font that couldn't be downloaded is left out
				}
				$ext  = strtolower( pathinfo( $v['woff2'], PATHINFO_EXTENSION ) );
				$fmt  = array( 'woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype', 'eot' => 'embedded-opentype' );
				$out .= '@font-face{'
					. "font-family: '" . str_replace( "'", '', $v['fontFamily'] ) . "';"
					. 'font-style: ' . $v['fontStyle'] . ';'
					. 'font-weight: ' . $v['fontWeight'] . ';'
					. ( ! empty( $v['fontStretch'] ) ? 'font-stretch: ' . $v['fontStretch'] . ';' : '' )
					. ( ! empty( $v['range'] ) ? 'unicode-range: ' . $v['range'] . ';' : '' )
					. "src: url('" . $v['woff2'] . '?ver=' . $ts . "') format('" . ( isset( $fmt[ $ext ] ) ? $fmt[ $ext ] : 'woff2' ) . "');"
					. 'font-display: ' . $display . ';'
					. "}\n";
			}
		}
		return $out;
	}

	/* =================================================================
	 * Bookkeeping: seen / status
	 * ================================================================= */

	private static function mark_seen( array $handles ) {
		$seen = self::opt( self::OPT_SEEN );
		$day  = (int) floor( time() / DAY_IN_SECONDS );
		$dirty = false;
		foreach ( array_unique( $handles ) as $h ) {
			if ( ! isset( $seen[ $h ] ) || (int) floor( $seen[ $h ] / DAY_IN_SECONDS ) !== $day ) {
				$seen[ $h ] = time();
				$dirty      = true;
			}
		}
		if ( $dirty ) {
			update_option( self::OPT_SEEN, $seen, false );
		}
	}

	/** Per stylesheet: where it's stored and its state (found / unloaded / stale / unused / not-found). */
	public static function status() {
		$meta  = self::meta();
		$fonts = self::opt( self::OPT_FONTS );
		$seen  = self::opt( self::OPT_SEEN );
		$dir   = self::dir();
		$rows  = array();
		$files = 0;
		$fams  = array();
		foreach ( $fonts as $handle => $set ) {
			$key = ! empty( $meta['cache_keys'][ $handle ] ) ? $meta['cache_keys'][ $handle ] : $handle;
			if ( in_array( $handle, (array) $meta['unload_stylesheets'], true ) ) {
				$state = 'unloaded';
			} elseif ( ! empty( $meta['stale'] ) ) {
				$state = 'stale';
			} elseif ( isset( $seen[ $handle ] ) && $seen[ $handle ] < time() - 30 * DAY_IN_SECONDS ) {
				$state = 'unused';
			} elseif ( is_file( $dir['path'] . '/' . $key . '/' . $key . '.css' ) ) {
				$state = 'found';
			} else {
				$state = 'not-found';
			}
			$rows[] = array( 'handle' => $handle, 'path' => 'wp-content/uploads/' . self::DIRNAME . '/' . $key, 'state' => $state );
			foreach ( (array) $set as $font ) {
				$fams[ $font['family'] ] = true;
			}
			$files += count( (array) glob( $dir['path'] . '/' . $key . '/*.woff2' ) );
		}
		return array(
			'active'   => (bool) Velox_Settings::get( 'perf_local_fonts', false ) && $files > 0,
			'files'    => $files,
			'families' => array_keys( $fams ),
			'rows'     => $rows,
			'has_run'  => ! empty( $meta['has_run'] ),
			'stale'    => ! empty( $meta['stale'] ),
		);
	}

	/** Everything the settings screen needs to draw the table and notices. */
	public static function admin_data() {
		return array(
			'fonts'    => self::opt( self::OPT_FONTS ),
			'preload'  => self::opt( self::OPT_PRE ),
			'unload'   => self::opt( self::OPT_UNL ),
			'status'   => self::status(),
			'failures' => array_values( self::opt( self::OPT_FAIL ) ),
			'checker'  => self::opt( self::OPT_CHECK ),
			'test'     => (bool) Velox_Settings::get( 'perf_fonts_test_mode', false ),
			'log'      => is_file( self::log_path() ),
		);
	}

	/* =================================================================
	 * Actions (called from Velox_Ajax)
	 * ================================================================= */

	/**
	 * Save the Load early / Don't load table. Mirrors OMGF: a handle with every
	 * variant unloaded is dropped from the page; one with some unloaded gets a
	 * new cache key so its stylesheet is rebuilt without them.
	 */
	public static function save_manage( $preload, $unload ) {
		$clean = function ( $in ) {
			$out = array();
			foreach ( (array) $in as $h => $by_font ) {
				foreach ( (array) $by_font as $f => $vids ) {
					foreach ( (array) $vids as $vid ) {
						$vid = preg_replace( '/[^0-9a-z ]/i', '', (string) $vid );
						if ( '' !== $vid ) {
							$out[ sanitize_title( $h ) ][ sanitize_title( $f ) ][] = $vid;
						}
					}
				}
			}
			return $out;
		};
		$preload = $clean( $preload );
		$unload  = $clean( $unload );
		$old_unl = self::opt( self::OPT_UNL );
		$fonts   = self::opt( self::OPT_FONTS );
		$meta    = self::meta();
		$dir     = self::dir();

		// A variant can't be both preloaded and unloaded.
		foreach ( $unload as $h => $by_font ) {
			foreach ( $by_font as $f => $vids ) {
				if ( isset( $preload[ $h ][ $f ] ) ) {
					$preload[ $h ][ $f ] = array_values( array_diff( $preload[ $h ][ $f ], $vids ) );
				}
			}
		}

		$unload_sheets = array();
		foreach ( $fonts as $h => $set ) {
			$total = 0;
			$off   = 0;
			foreach ( $set as $f => $font ) {
				$ids    = array_unique( array_map( function ( $v ) { return (string) $v['id']; }, $font['variants'] ) );
				$total += count( $ids );
				$off   += count( array_intersect( $ids, isset( $unload[ $h ][ $f ] ) ? $unload[ $h ][ $f ] : array() ) );
			}
			$was = isset( $old_unl[ $h ] ) ? $old_unl[ $h ] : array();
			$now = isset( $unload[ $h ] ) ? $unload[ $h ] : array();
			if ( $total && $off === $total ) {
				$unload_sheets[] = $h;
			}
			if ( wp_json_encode( $was ) !== wp_json_encode( $now ) ) {
				$old_key = isset( $meta['cache_keys'][ $h ] ) ? $meta['cache_keys'][ $h ] : $h;
				$new_key = ( $off && $off < $total ) ? $h . '-mod-' . strtolower( wp_generate_password( 5, false ) ) : $h;
				$meta['cache_keys'][ $h ] = $new_key;
				// The old folder is out of date either way (OMGF clears it too).
				self::rrmdir( $dir['path'] . '/' . $old_key );
				if ( $new_key === $h ) {
					self::rrmdir( $dir['path'] . '/' . $h ); // rebuilt with everything back in
				}
			}
		}
		$meta['unload_stylesheets'] = $unload_sheets;
		self::save_meta( $meta );
		update_option( self::OPT_PRE, $preload, false );
		update_option( self::OPT_UNL, $unload, false );
		return array( 'unloaded_stylesheets' => count( $unload_sheets ) );
	}

	/**
	 * Load a page with the one-time optimize key: every Google Fonts stylesheet
	 * on it is fetched and downloaded again.
	 */
	public static function run_optimize( $url = '' ) {
		// 1) Rebuild every stylesheet Velox already knows straight from its Google
		//    URL. This needs no page load, so it also works where the server
		//    can't request its own pages (password protection, some firewalls).
		$rebuilt = 0;
		$meta    = self::meta();
		$was     = self::$running;
		self::$running = true;
		foreach ( (array) $meta['sources'] as $handle => $src ) {
			if ( in_array( $handle, (array) $meta['unload_stylesheets'], true ) ) {
				continue;
			}
			$key = ! empty( $meta['cache_keys'][ $handle ] ) ? $meta['cache_keys'][ $handle ] : $handle;
			if ( self::optimize_stylesheet( $src, $handle, $key ) ) {
				$rebuilt++;
			}
		}
		self::$running = $was;

		// 2) Then load the page to pick up any stylesheet that isn't known yet.
		$loop = self::loopback( $url );
		$meta = self::meta();
		$meta['stale']   = false;
		$meta['has_run'] = true;
		self::save_meta( $meta );
		self::purge_page_caches();
		if ( empty( $loop['ok'] ) ) {
			if ( $rebuilt ) {
				return array( 'ok' => true, 'message' => sprintf( __( 'Fonts rebuilt (%1$d stylesheet(s)). %2$s New fonts on other pages are still added when those pages are visited.', 'velox' ), $rebuilt, $loop['message'] ) );
			}
			return $loop;
		}
		$fonts = self::opt( self::OPT_FONTS );
		return array(
			'ok'      => true,
			'message' => $fonts ? __( 'Optimization completed successfully.', 'velox' ) : __( 'Optimization ran, but no Google Fonts were found on your front page.', 'velox' ),
		);
	}

	/** Load a page as the current admin with the one-time optimize key. */
	private static function loopback( $url ) {
		$url  = $url ? $url : home_url( '/' );
		$url  = add_query_arg( array(
			self::RUN_ARG => wp_create_nonce( 'velox_fonts_optimize' ),
			'nocache'     => strtolower( wp_generate_password( 5, false ) ),
		), $url );
		$cookies = array();
		foreach ( $_COOKIE as $name => $value ) { // phpcs:ignore
			if ( 0 === strpos( $name, 'wordpress_' ) ) {
				$cookies[] = new WP_Http_Cookie( array( 'name' => $name, 'value' => wp_unslash( $value ) ) );
			}
		}
		$res = wp_remote_get( $url, array(
			'timeout'   => 60,
			'cookies'   => $cookies,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			'headers'   => array( 'Cache-Control' => 'no-cache' ),
		) );
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'message' => sprintf( __( 'Velox could not load your front page: %s', 'velox' ), $res->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 200 !== $code ) {
			return array( 'ok' => false, 'message' => sprintf( __( 'Velox could not load your front page: %1$d %2$s', 'velox' ), $code, wp_remote_retrieve_response_message( $res ) ) );
		}
		return array( 'ok' => true );
	}

	/** Is this request the loopback (or an admin's "optimize this page")? */
	public static function is_running_optimize() {
		if ( empty( $_GET[ self::RUN_ARG ] ) ) { // phpcs:ignore
			return false;
		}
		return current_user_can( 'manage_options' )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET[ self::RUN_ARG ] ) ), 'velox_fonts_optimize' ); // phpcs:ignore
	}

	/** Admin-bar link that re-optimizes the page being viewed. */
	public static function optimize_page_url( $url ) {
		return add_query_arg( array(
			self::RUN_ARG => wp_create_nonce( 'velox_fonts_optimize' ),
			'nocache'     => strtolower( wp_generate_password( 5, false ) ),
		), $url );
	}

	/** Empty Cache: every file and every font record; the settings stay. */
	public static function empty_cache() {
		self::rrmdir( self::dir()['path'] );
		foreach ( array( self::OPT_FONTS, self::OPT_FRONT, self::OPT_PRE, self::OPT_UNL, self::OPT_META, self::OPT_FAIL, self::OPT_CHECK, self::OPT_SEEN, 'velox_local_fonts' ) as $o ) {
			delete_option( $o );
		}
		self::purge_page_caches();
		return array( 'ok' => true, 'message' => __( 'Cache directory successfully emptied.', 'velox' ) );
	}

	/** Refresh Cache (and maintain settings): new files, same choices. */
	public static function refresh_cache() {
		$meta = self::meta();
		$unl  = self::opt( self::OPT_UNL );
		foreach ( array_keys( self::opt( self::OPT_FONTS ) ) as $h ) {
			$meta['cache_keys'][ $h ] = ! empty( $unl[ $h ] ) ? $h . '-mod-' . strtolower( wp_generate_password( 5, false ) ) : $h;
		}
		$meta['stale']     = false;
		$meta['timestamp'] = time();
		self::save_meta( $meta );
		self::rrmdir( self::dir()['path'] );
		delete_option( self::OPT_FAIL );
		return self::run_optimize();
	}

	/** Remove one stylesheet from every list (its files stay until the next empty). */
	public static function remove_stylesheet( $handle ) {
		$handle = sanitize_title( $handle );
		foreach ( array( self::OPT_FONTS, self::OPT_FRONT, self::OPT_PRE, self::OPT_UNL, self::OPT_SEEN ) as $o ) {
			$v = self::opt( $o );
			unset( $v[ $handle ] );
			update_option( $o, $v, false );
		}
		$meta = self::meta();
		unset( $meta['cache_keys'][ $handle ], $meta['sources'][ $handle ] );
		$meta['unload_stylesheets'] = array_values( array_diff( (array) $meta['unload_stylesheets'], array( $handle ) ) );
		self::save_meta( $meta );
	}

	/** Theme switch: start over completely (OMGF does the same). */
	public static function flush_all() {
		self::empty_cache();
	}

	/** After a builder save / update: drop folders no stylesheet uses any more. */
	public static function flush_orphans() {
		$meta = self::meta();
		$keep = array();
		foreach ( array_keys( self::opt( self::OPT_FONTS ) ) as $h ) {
			$keep[ $h ] = true;
			if ( ! empty( $meta['cache_keys'][ $h ] ) ) {
				$keep[ $meta['cache_keys'][ $h ] ] = true;
			}
		}
		foreach ( (array) glob( self::dir()['path'] . '/*', GLOB_ONLYDIR ) as $d ) {
			if ( ! isset( $keep[ basename( $d ) ] ) ) {
				self::rrmdir( $d );
			}
		}
	}

	/** Changing the subsets or the legacy option makes every stored stylesheet out of date. */
	public static function settings_changed( $old, $new ) {
		$old = is_array( $old ) ? $old : array();
		$new = is_array( $new ) ? $new : array();
		foreach ( array( 'perf_fonts_subsets', 'perf_fonts_legacy', 'perf_font_display', 'perf_fonts_compat' ) as $k ) {
			if ( ( isset( $old[ $k ] ) ? $old[ $k ] : null ) !== ( isset( $new[ $k ] ) ? $new[ $k ] : null ) && get_option( self::OPT_FONTS ) ) {
				$m = self::meta();
				$m['stale'] = true;
				self::save_meta( $m );
				return;
			}
		}
	}

	/** Google Fonts the browser still loaded from Google (reported by the admin's browser). */
	public static function record_checker( $urls, $path ) {
		$res = self::opt( self::OPT_CHECK );
		foreach ( (array) $urls as $u ) {
			$u = esc_url_raw( (string) $u );
			if ( '' === $u || ( false === strpos( $u, 'fonts.googleapis.com' ) && false === strpos( $u, 'fonts.gstatic.com' ) ) ) {
				continue;
			}
			if ( ! isset( $res[ $u ] ) && count( $res ) >= 5 ) {
				continue;
			}
			$paths = isset( $res[ $u ] ) ? $res[ $u ] : array();
			if ( ! in_array( $path, $paths, true ) && count( $paths ) < 5 ) {
				$paths[] = $path;
			}
			$res[ $u ] = $paths;
		}
		update_option( self::OPT_CHECK, $res, false );
	}

	public static function purge_page_caches() {
		if ( class_exists( 'Velox_Cache' ) ) {
			Velox_Cache::purge_all();
		}
		// The same page caches OMGF clears after optimizing.
		if ( function_exists( 'rocket_clean_domain' ) ) { rocket_clean_domain(); }
		if ( function_exists( 'wpfc_clear_all_cache' ) ) { wpfc_clear_all_cache( true ); }
		if ( function_exists( 'w3tc_flush_all' ) ) { w3tc_flush_all(); }
		if ( function_exists( 'wp_cache_clear_cache' ) ) { wp_cache_clear_cache(); }
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) { sg_cachepress_purge_cache(); }
		if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) { autoptimizeCache::clearall(); }
		if ( function_exists( 'wpo_cache_flush' ) ) { wpo_cache_flush(); }
		do_action( 'litespeed_purge_all' );
		do_action( 'flying_press_purge_all' );
		if ( has_action( 'cloudflare_purge_everything' ) ) {
			do_action( 'cloudflare_purge_everything' );
		}
	}

	public static function download_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'velox' ) );
		}
		check_admin_referer( 'velox_fonts_log' );
		$f = self::log_path();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="velox-fonts-debug.log"' );
		echo is_file( $f ) ? (string) file_get_contents( $f ) : ''; // phpcs:ignore
		exit;
	}

	public static function delete_log() {
		@unlink( self::log_path() ); // phpcs:ignore
	}

	public static function rrmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$base = wp_normalize_path( self::dir()['path'] );
		if ( 0 !== strpos( wp_normalize_path( $dir ), $base ) ) {
			return; // never outside uploads/velox-fonts
		}
		foreach ( (array) scandir( $dir ) as $f ) {
			if ( '.' === $f || '..' === $f ) {
				continue;
			}
			$p = $dir . '/' . $f;
			if ( is_dir( $p ) ) {
				self::rrmdir( $p );
			} else {
				@unlink( $p ); // phpcs:ignore
			}
		}
		@rmdir( $dir ); // phpcs:ignore
	}
}
