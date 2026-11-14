<?php
/**
 * Velox local Google Fonts optimizer (OMGF-style).
 *
 * Detects the Google Fonts a page loads, downloads the woff2 files into
 * /uploads/velox-fonts/, builds one local stylesheet, and serves that instead
 * of fetching from fonts.googleapis.com / fonts.gstatic.com on every visit.
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Fonts {

	const OPTION = 'velox_local_fonts';
	const DIRNAME = 'velox-fonts';

	public function __construct() {
		if ( is_admin() ) {
			return;
		}
		// Blocking unwanted fonts works independently of local hosting.
		if ( '' !== trim( (string) Velox_Settings::get( 'perf_font_block', '' ) ) ) {
			add_filter( 'style_loader_tag', array( $this, 'block_fonts' ), 98, 4 );
		}
		// font-display strategy — ask Google to serve its fonts with the chosen mode.
		// This makes the setting actually do something without needing local hosting
		// (which handles it itself). Only relevant when we're NOT localizing.
		if ( '' !== self::display_mode() && ! Velox_Settings::get( 'perf_local_fonts' ) ) {
			add_filter( 'style_loader_tag', array( $this, 'add_display_swap' ), 97, 4 );
			add_action( 'wp_enqueue_scripts', array( $this, 'swap_google_src' ), 98 );
		}
		// Hosting fonts locally is Velox_Local_Fonts' job (it rewrites the whole
		// page, the way OMGF does, so builder-printed links are covered too).
	}

	/* ---------------------------------------------------------------- paths */

	public static function dir() {
		$up = wp_upload_dir();
		return array(
			'path' => trailingslashit( $up['basedir'] ) . self::DIRNAME,
			'url'  => trailingslashit( $up['baseurl'] ) . self::DIRNAME,
		);
	}

	/* ---------------------------------------------------------------- scan + localize */

	/**
	 * Fetch the site's front page, find every Google Fonts stylesheet it loads,
	 * download the fonts locally and build a combined stylesheet.
	 *
	 * @return array|WP_Error  Result summary on success.
	 */
	public function localize() {
		$r = Velox_Local_Fonts::run_optimize();
		return empty( $r['ok'] ) ? new WP_Error( 'velox_fonts', $r['message'] ) : array( 'message' => $r['message'] );
	}

	/** Remove all local fonts (Empty cache). */
	public function clear() {
		$r = Velox_Local_Fonts::empty_cache();
		return array( 'message' => $r['message'] );
	}

	/** Summary for the dashboard. */
	public static function status() {
		return Velox_Local_Fonts::status();
	}

	/** The chosen font-display value, or '' when off/invalid. */
	private static function display_mode() {
		$m = (string) Velox_Settings::get( 'perf_font_display', 'swap' );
		return in_array( $m, array( 'swap', 'auto', 'optional', 'fallback', 'block' ), true ) ? $m : '';
	}

	/** Append display=<mode> to a Google Fonts <link> so it renders with that font-display. */
	public function add_display_swap( $tag, $handle, $href, $media ) {
		$mode = self::display_mode();
		if ( '' === $mode || false === stripos( $href, 'fonts.googleapis.com' ) || false !== stripos( $href, 'display=' ) ) {
			return $tag;
		}
		$new = $href . ( false !== strpos( $href, '?' ) ? '&' : '?' ) . 'display=' . $mode;
		return str_replace( $href, esc_url( $new ), $tag );
	}

	/** Also patch the enqueued src, for setups that read it directly rather than the tag. */
	public function swap_google_src() {
		$mode = self::display_mode();
		if ( '' === $mode ) {
			return;
		}
		$styles = wp_styles();
		if ( empty( $styles->registered ) ) {
			return;
		}
		foreach ( $styles->registered as $obj ) {
			if ( ! empty( $obj->src ) && false !== stripos( $obj->src, 'fonts.googleapis.com' ) && false === stripos( $obj->src, 'display=' ) ) {
				$obj->src .= ( false !== strpos( $obj->src, '?' ) ? '&' : '?' ) . 'display=' . $mode;
			}
		}
	}

	/* ---------------------------------------------------------------- block (5c) */

	/** The user's block list — one font family name or URL fragment per line. */
	public static function block_list() {
		$raw = (string) Velox_Settings::get( 'perf_font_block', '' );
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	/** Family names present in a Google Fonts CSS URL (family=Inter:...|Poppins:...). */
	private static function families_in_google_url( $href ) {
		$fams = array();
		$q    = wp_parse_url( $href, PHP_URL_QUERY );
		if ( ! $q ) {
			return $fams;
		}
		parse_str( $q, $args );
		// css (v1): family=Inter:400|Poppins:600 ; css2 (v2): repeated family=Inter:wght@400
		$raw = array();
		if ( isset( $args['family'] ) ) {
			$raw = is_array( $args['family'] ) ? $args['family'] : explode( '|', $args['family'] );
		}
		foreach ( $raw as $f ) {
			$name = trim( explode( ':', $f )[0] );
			$name = str_replace( '+', ' ', $name );
			if ( '' !== $name ) {
				$fams[] = $name;
			}
		}
		return $fams;
	}

	/** Remove blocked fonts' <link> tags (Google families or URL fragments). */
	public function block_fonts( $tag, $handle, $href, $media ) {
		$blocked = self::block_list();
		if ( empty( $blocked ) ) {
			return $tag;
		}
		// Google Fonts stylesheet: drop it if any of its families is blocked.
		if ( false !== stripos( $href, 'fonts.googleapis.com' ) ) {
			foreach ( self::families_in_google_url( $href ) as $fam ) {
				foreach ( $blocked as $b ) {
					if ( 0 === strcasecmp( $fam, $b ) ) {
						return '';
					}
				}
			}
		}
		// Any stylesheet whose URL contains a blocked URL fragment.
		foreach ( $blocked as $b ) {
			if ( 0 === strpos( $b, 'http' ) && false !== stripos( $href, $b ) ) {
				return '';
			}
		}
		return $tag;
	}

	/* ---------------------------------------------------------------- detect (9b) */

	/** Pull a single declaration value out of an @font-face body. */
	private static function css_decl( $body, $prop ) {
		if ( preg_match( '/(?:^|;)\s*' . preg_quote( $prop, '/' ) . '\s*:\s*([^;]+)/i', $body, $m ) ) {
			return trim( $m[1] );
		}
		return '';
	}

	/**
	 * Read CSS that page builders compile to disk (Oxygen, Bricks, Elementor). This is
	 * how Velox finds fonts on hosts where it can't fetch the rendered front page over
	 * loopback — the builder's @font-face rules live in these cached files.
	 */
	private static function builder_css_blob() {
		$up = wp_upload_dir();
		if ( empty( $up['basedir'] ) ) {
			return '';
		}
		$base  = trailingslashit( $up['basedir'] );
		$roots = array( $base . 'oxygen', $base . 'bricks', $base . 'elementor/css' );
		$blob  = '';
		foreach ( $roots as $root ) {
			if ( ! is_dir( $root ) ) {
				continue;
			}
			try {
				$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
				foreach ( $it as $file ) {
					if ( $file->isFile() && 'css' === strtolower( $file->getExtension() ) && $file->getSize() < 3000000 ) {
						$c = @file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
						if ( $c ) {
							$blob .= "\n" . $c;
						}
					}
				}
			} catch ( Exception $e ) {
				continue;
			}
		}
		return $blob;
	}

	/**
	 * Scan the front page (and its same-origin + Google Fonts stylesheets) for every
	 * @font-face the site actually loads, and return one row per family/weight/style
	 * with the best (woff2-preferred) file URL. Powers the "preload fonts" picker.
	 */
	public function detect() {
		$home     = home_url( '/' );
		$css      = '';
		$homehost = wp_parse_url( $home, PHP_URL_HOST );

		// Best case: read the rendered front page. On hosts that block loopback
		// (common on IONOS/Plesk) this comes back empty — we then fall back to the
		// builder's on-disk CSS cache below so detection still works.
		$res  = wp_remote_get( $home, array( 'timeout' => 15, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Velox font detect)' ) );
		$html = is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_body( $res );

		// Inline <style> blocks.
		if ( '' !== $html && preg_match_all( '#<style[^>]*>(.*?)</style>#is', $html, $m ) ) {
			$css .= "\n" . implode( "\n", $m[1] );
		}
		// Linked stylesheets — same origin or Google Fonts only.
		if ( preg_match_all( '#<link[^>]+rel=["\']stylesheet["\'][^>]*>#i', $html, $links ) ) {
			$seen_css = array();
			foreach ( $links[0] as $link ) {
				if ( ! preg_match( '#href=["\']([^"\']+)["\']#i', $link, $h ) ) {
					continue;
				}
				$url = html_entity_decode( $h[1] );
				if ( 0 === strpos( $url, '//' ) ) {
					$url = 'https:' . $url;
				} elseif ( '' !== $url && '/' === $url[0] ) {
					$url = home_url( $url );
				}
				$host = wp_parse_url( $url, PHP_URL_HOST );
				if ( ! $host || isset( $seen_css[ $url ] ) ) {
					continue;
				}
				$seen_css[ $url ] = true;
				if ( $host !== $homehost && false === stripos( $host, 'fonts.googleapis.com' ) ) {
					continue;
				}
				$cr = wp_remote_get( $url, array( 'timeout' => 12, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Velox font detect)' ) );
				if ( ! is_wp_error( $cr ) ) {
					$css .= "\n" . (string) wp_remote_retrieve_body( $cr );
				}
			}
		}

		$fonts = array();
		$seen  = array();

		// Builder CSS compiled to disk (Oxygen/Bricks/Elementor) — read directly so
		// custom @font-face fonts are found even when the front page can't be fetched.
		$css .= "\n" . self::builder_css_blob();

		// Fetch any Google Fonts CSS referenced anywhere, so their @font-face parse
		// into real families (this hits fonts.googleapis.com, not loopback).
		if ( preg_match_all( '#https://fonts\.googleapis\.com/css2?\?[^"\')\s;>]+#i', $css . ' ' . $html, $gf ) ) {
			$done = array();
			foreach ( $gf[0] as $gurl ) {
				$gurl = html_entity_decode( $gurl );
				if ( isset( $done[ $gurl ] ) ) {
					continue;
				}
				$done[ $gurl ] = true;
				$gr = wp_remote_get( $gurl, array( 'timeout' => 12, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Velox)' ) );
				if ( ! is_wp_error( $gr ) ) {
					$css .= "\n" . (string) wp_remote_retrieve_body( $gr );
				}
			}
		}

		if ( '' === trim( $css ) ) {
			return new WP_Error( 'no_css', __( 'No CSS found to scan. If your host blocks loopback, Velox reads the builder CSS cache instead — open your site once so it gets generated, then try again.', 'velox' ) );
		}

		// Oxygen references Google Fonts by family NAME in its CSS but loads them via a
		// front-end <link> we can't see over loopback. Pull the non-system family names
		// out of the CSS and ask Google for each one — valid families return @font-face
		// (with real URLs), invalid ones 400 and are skipped. This is what lets Google
		// Fonts show up on hosts where the front page can't be fetched.
		if ( preg_match_all( '/font-family\s*:\s*([^;{}]+)/i', $css . ' ' . $html, $ff ) ) {
			$sys = array( 'inherit', 'initial', 'unset', 'revert', 'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', '-apple-system', 'blinkmacsystemfont', 'segoe ui', 'helvetica', 'helvetica neue', 'arial', 'times', 'times new roman', 'georgia', 'courier', 'courier new', 'verdana', 'tahoma', 'trebuchet ms', 'ui-monospace', 'ui-sans-serif', 'ui-serif', 'menlo', 'monaco', 'consolas', 'sans', 'emoji', 'icomoon', 'fontawesome', 'dashicons' );
			$cands = array();
			foreach ( $ff[1] as $decl ) {
				$parts = explode( ',', $decl );
				$first = trim( $parts[0], " '\"\t\r\n" );
				$low   = strtolower( $first );
				if ( '' === $first || strlen( $first ) > 40 || in_array( $low, $sys, true ) || preg_match( '/[{}();:\\\\]|var\(|!important|url\(/i', $first ) ) {
					continue;
				}
				$cands[ $first ] = true;
			}
			$fetched = 0;
			foreach ( array_keys( $cands ) as $fam ) {
				if ( $fetched >= 10 ) {
					break;
				}
				$fetched++;
				$gurl = 'https://fonts.googleapis.com/css2?family=' . rawurlencode( $fam ) . ':wght@400;700&display=swap';
				$gr   = wp_remote_get( $gurl, array( 'timeout' => 8, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Velox)' ) );
				if ( ! is_wp_error( $gr ) && 200 === (int) wp_remote_retrieve_response_code( $gr ) ) {
					$css .= "\n" . (string) wp_remote_retrieve_body( $gr );
				}
			}
		}

		if ( preg_match_all( '#@font-face\s*{([^}]*)}#is', $css, $faces ) ) {
			foreach ( $faces[1] as $body ) {
				$family = trim( self::css_decl( $body, 'font-family' ), " '\"" );
				$weight = self::css_decl( $body, 'font-weight' );
				$weight = '' !== $weight ? $weight : '400';
				$style  = self::css_decl( $body, 'font-style' );
				$style  = '' !== $style ? $style : 'normal';
				// Best source URL — prefer woff2.
				$best = '';
				if ( preg_match_all( '#url\(\s*([^)]+?)\s*\)#i', $body, $us ) ) {
					foreach ( $us[1] as $u ) {
						$u = trim( $u, " '\"" );
						if ( 0 === strpos( $u, '//' ) ) { $u = 'https:' . $u; }
						elseif ( '' !== $u && '/' === $u[0] ) { $u = home_url( $u ); }
						if ( '' === $best ) { $best = $u; }
						if ( false !== stripos( $u, 'woff2' ) ) { $best = $u; break; }
					}
				}
				if ( '' === $family || '' === $best || isset( $seen[ $best ] ) ) {
					continue;
				}
				$seen[ $best ] = true;
				$fonts[] = array(
					'family' => $family,
					'weight' => $weight,
					'style'  => $style,
					'url'    => $best,
					'source' => ( false !== stripos( $best, 'gstatic.com' ) || false !== stripos( $best, 'googleapis.com' ) ) ? 'google' : 'local',
				);
			}
		}

		usort( $fonts, function ( $a, $b ) {
			$c = strcasecmp( $a['family'], $b['family'] );
			return 0 !== $c ? $c : strcmp( (string) $a['weight'], (string) $b['weight'] );
		} );

		return array( 'fonts' => $fonts, 'count' => count( $fonts ) );
	}
}
