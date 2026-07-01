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
		// Front-end local-hosting swap only runs when the feature is on AND we have a local file.
		if ( ! Velox_Settings::get( 'perf_local_fonts' ) ) {
			return;
		}
		$data = get_option( self::OPTION );
		if ( empty( $data['css_url'] ) ) {
			return;
		}
		// Drop the Google Fonts requests and enqueue the local copy instead.
		add_action( 'wp_enqueue_scripts', array( $this, 'swap_enqueued' ), 99 );
		add_filter( 'style_loader_tag', array( $this, 'strip_google_links' ), 99, 4 );
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
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// 1) Grab the rendered front page so we catch fonts added by Oxygen/the theme too.
		$home = wp_remote_get( home_url( '/' ), array(
			'timeout'    => 20,
			'user-agent' => 'Mozilla/5.0 (Velox font scan)',
		) );
		if ( is_wp_error( $home ) ) {
			return $home;
		}
		$html = wp_remote_retrieve_body( $home );
		if ( ! $html ) {
			return new WP_Error( 'no_html', __( 'Could not read the front page to scan for fonts.', 'velox' ) );
		}

		// 2) Pull out every fonts.googleapis.com CSS URL.
		preg_match_all( '#https://fonts\.googleapis\.com/css2?\?[^"\'\s>]+#i', $html, $m );
		$css_urls = array_values( array_unique( array_map( 'html_entity_decode', $m[0] ) ) );
		if ( empty( $css_urls ) ) {
			return new WP_Error( 'none_found', __( 'No Google Fonts were found on the front page. If your fonts load elsewhere, link a page that uses them.', 'velox' ) );
		}

		// 3) Prepare the local directory.
		$dir = self::dir();
		if ( ! wp_mkdir_p( $dir['path'] ) ) {
			return new WP_Error( 'mkdir', __( 'Could not create the local fonts folder. Check uploads permissions.', 'velox' ) );
		}

		$combined  = '';
		$files     = 0;
		$families  = array();
		$swap      = (bool) Velox_Settings::get( 'perf_fonts_display_swap', true );

		foreach ( $css_urls as $css_url ) {
			// Request with a modern browser UA so Google returns woff2 (not ttf).
			$resp = wp_remote_get( $css_url, array(
				'timeout'    => 20,
				'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
			) );
			if ( is_wp_error( $resp ) ) {
				continue;
			}
			$css = wp_remote_retrieve_body( $resp );
			if ( ! $css ) {
				continue;
			}

			// Download each remote font file and rewrite its URL to the local copy.
			$css = preg_replace_callback( '#url\((https://fonts\.gstatic\.com/[^)]+)\)#i', function ( $u ) use ( $dir, &$files ) {
				$remote = $u[1];
				$name   = md5( $remote ) . '.' . ( pathinfo( wp_parse_url( $remote, PHP_URL_PATH ), PATHINFO_EXTENSION ) ?: 'woff2' );
				$local  = trailingslashit( $dir['path'] ) . $name;
				if ( ! file_exists( $local ) ) {
					$bin = wp_remote_get( $remote, array( 'timeout' => 20 ) );
					if ( is_wp_error( $bin ) ) {
						return $u[0]; // leave remote URL on failure
					}
					$body = wp_remote_retrieve_body( $bin );
					if ( ! $body ) {
						return $u[0];
					}
					file_put_contents( $local, $body ); // phpcs:ignore
				}
				$files++;
				return 'url(' . trailingslashit( $dir['url'] ) . $name . ')';
			}, $css );

			if ( $swap && false === stripos( $css, 'font-display' ) ) {
				$css = preg_replace( '/@font-face\s*{/i', "@font-face{font-display:swap;", $css );
			}

			preg_match_all( '/font-family:\s*[\'"]?([^;\'"]+)/i', $css, $fam );
			if ( ! empty( $fam[1] ) ) {
				$families = array_merge( $families, $fam[1] );
			}
			$combined .= $css . "\n";
		}

		if ( ! $files ) {
			return new WP_Error( 'no_files', __( 'Found the Google Fonts CSS but could not download any font files. The server may be blocking outbound requests to fonts.gstatic.com.', 'velox' ) );
		}

		// 4) Write the combined local stylesheet.
		$css_path = trailingslashit( $dir['path'] ) . 'velox-fonts.css';
		file_put_contents( $css_path, $combined ); // phpcs:ignore

		$data = array(
			'css_url'    => trailingslashit( $dir['url'] ) . 'velox-fonts.css?v=' . time(),
			'css_path'   => $css_path,
			'files'      => $files,
			'families'   => array_values( array_unique( array_map( 'trim', $families ) ) ),
			'source_css' => $css_urls,
			'time'       => time(),
		);
		update_option( self::OPTION, $data );

		return array(
			'message'  => sprintf( __( 'Hosted %1$d font file(s) across %2$d family/families locally.', 'velox' ), $files, count( $data['families'] ) ),
			'files'    => $files,
			'families' => $data['families'],
		);
	}

	/** Remove all local fonts + the stored mapping. */
	public function clear() {
		$dir = self::dir();
		if ( is_dir( $dir['path'] ) ) {
			foreach ( glob( trailingslashit( $dir['path'] ) . '*' ) as $f ) {
				@unlink( $f ); // phpcs:ignore
			}
			@rmdir( $dir['path'] ); // phpcs:ignore
		}
		delete_option( self::OPTION );
		return array( 'message' => __( 'Local fonts removed. Google Fonts will load normally again.', 'velox' ) );
	}

	public static function status() {
		$data = get_option( self::OPTION );
		if ( empty( $data['files'] ) ) {
			return array( 'active' => false );
		}
		return array(
			'active'   => true,
			'files'    => (int) $data['files'],
			'families' => isset( $data['families'] ) ? $data['families'] : array(),
			'time'     => isset( $data['time'] ) ? (int) $data['time'] : 0,
		);
	}

	/* ---------------------------------------------------------------- front end */

	public function swap_enqueued() {
		$data = get_option( self::OPTION );
		if ( empty( $data['css_url'] ) ) {
			return;
		}
		// Dequeue any enqueued Google Fonts stylesheet.
		$styles = wp_styles();
		foreach ( $styles->registered as $handle => $obj ) {
			if ( ! empty( $obj->src ) && false !== stripos( $obj->src, 'fonts.googleapis.com' ) ) {
				wp_dequeue_style( $handle );
				wp_deregister_style( $handle );
			}
		}
		wp_enqueue_style( 'velox-local-fonts', $data['css_url'], array(), null );
	}

	/** Catch any Google Fonts <link> that wasn't enqueued through wp_styles. */
	public function strip_google_links( $tag, $handle, $href, $media ) {
		if ( false !== stripos( $href, 'fonts.googleapis.com' ) ) {
			return '';
		}
		return $tag;
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
	 * Scan the front page (and its same-origin + Google Fonts stylesheets) for every
	 * @font-face the site actually loads, and return one row per family/weight/style
	 * with the best (woff2-preferred) file URL. Powers the "preload fonts" picker.
	 */
	public function detect() {
		$home = home_url( '/' );
		$res  = wp_remote_get( $home, array( 'timeout' => 15, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Velox font detect)' ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$html = (string) wp_remote_retrieve_body( $res );
		if ( '' === $html ) {
			return new WP_Error( 'no_html', __( 'Could not read the front page to scan for fonts.', 'velox' ) );
		}

		$css      = '';
		$homehost = wp_parse_url( $home, PHP_URL_HOST );

		// Inline <style> blocks.
		if ( preg_match_all( '#<style[^>]*>(.*?)</style>#is', $html, $m ) ) {
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
