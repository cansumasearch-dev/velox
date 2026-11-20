<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Light and dark mode for every Velox screen.
 *
 * Each person chooses for themselves (user meta): one mode for all of Velox,
 * and the Builder and the Shop can follow it or have their own. A small
 * switch floats in the corner of every Velox screen, the editor included.
 *
 * The screens were each drawn for one mode (Velox light, Builder and Shop
 * dark), so the other mode is made by converting their colours: a background
 * stays a background, text stays readable text, hairlines stay hairlines.
 * The converted stylesheets are written once to uploads/velox/theme and
 * swapped in before the page is drawn, so nothing flashes. The editor builds
 * its CSS in JavaScript and converts it with the same rules (velox-theme.js).
 *
 * @package Velox
 */
class Velox_Theme {

	const META = 'velox_theme';
	/** Bump when the conversion rules change, so the stored copies are rebuilt. */
	const REV  = '3';

	/** Stylesheets, the mode they were drawn in, and their file. */
	private static function sheets() {
		return array(
			'velox-admin'         => array( 'light', 'admin/css/velox-admin.css' ),
			'velox-builder-admin' => array( 'dark', 'admin/css/velox-builder.css' ),
			'velox-shop-admin'    => array( 'dark', 'admin/css/velox-shop-admin.css' ),
			'velox-fields-edit'   => array( 'light', 'admin/css/velox-fields-edit.css' ),
		);
	}

	public static function init() {
		add_action( 'admin_head', array( __CLASS__, 'head' ), 1 );
		add_filter( 'style_loader_src', array( __CLASS__, 'swap_src' ), 20, 2 );
		add_action( 'admin_footer', array( __CLASS__, 'footer' ) );
		add_action( 'wp_ajax_velox_theme_save', array( __CLASS__, 'ajax_save' ) );
	}

	/* ------------------------------------------------------------ choice */

	public static function prefs( $user = 0 ) {
		$user = $user ? $user : get_current_user_id();
		$p    = get_user_meta( $user, self::META, true );
		$p    = is_array( $p ) ? $p : array();
		// The defaults are how the screens always looked.
		return array_merge( array( 'all' => 'light', 'builder' => 'dark', 'shop' => 'dark' ), array_intersect_key( $p, array_flip( array( 'all', 'builder', 'shop' ) ) ) );
	}

	/** Which part of Velox this screen belongs to (null = not a Velox screen). */
	public static function area() {
		if ( defined( 'VELOX_THEME_AREA' ) ) {
			return VELOX_THEME_AREA;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( '' === $page ) {
			$pt = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
			return 'velox_field_group' === $pt || 0 === strpos( $pt, 'velox' ) ? 'velox' : null;
		}
		if ( 0 === strpos( $page, 'velox-shop' ) ) {
			return 'shop';
		}
		if ( 0 === strpos( $page, 'velox-builder' ) ) {
			return 'builder';
		}
		return 0 === strpos( $page, 'velox' ) ? 'velox' : null;
	}

	/** light | dark for an area, "system" resolved from what the browser said last time. */
	public static function mode( $area ) {
		$p = self::prefs();
		$m = ( 'velox' !== $area && isset( $p[ $area ] ) && 'inherit' !== $p[ $area ] ) ? $p[ $area ] : $p['all'];
		if ( 'system' === $m ) {
			$m = ( isset( $_COOKIE['velox_sys_dark'] ) && '1' === $_COOKIE['velox_sys_dark'] ) ? 'dark' : 'light';
		}
		return 'dark' === $m ? 'dark' : 'light';
	}

	/* ------------------------------------------------------- stylesheets */

	/** URL of a stylesheet in a mode (the original when it was drawn in that mode). */
	public static function sheet_url( $handle, $mode ) {
		$s = self::sheets();
		if ( ! isset( $s[ $handle ] ) ) {
			return '';
		}
		list( $native, $rel ) = $s[ $handle ];
		$src = VELOX_PATH . $rel;
		if ( $mode === $native || ! is_readable( $src ) ) {
			return VELOX_URL . $rel . '?ver=' . VELOX_VERSION;
		}
		$up   = wp_upload_dir();
		$dir  = trailingslashit( $up['basedir'] ) . 'velox/theme';
		$name = $handle . '-' . $mode . '-' . substr( md5( VELOX_VERSION . filemtime( $src ) . self::REV ), 0, 10 ) . '.css';
		$file = $dir . '/' . $name;
		if ( ! file_exists( $file ) ) {
			wp_mkdir_p( $dir );
			$css = self::convert( (string) file_get_contents( $src ), $mode ) . self::extra( $handle, $mode );
			// Relative addresses (the fonts) point at the plugin, wherever the copy lives.
			$base = trailingslashit( dirname( VELOX_URL . $rel ) );
			$css  = preg_replace_callback( '/url\(\s*([\'"]?)(?!data:|https?:|\/)([^\'")]+)\1\s*\)/i', function ( $m ) use ( $base ) {
				return 'url(' . $m[1] . self::resolve_url( $base, $m[2] ) . $m[1] . ')';
			}, $css );
			if ( false === @file_put_contents( $file, "/* Velox — " . $handle . ' in ' . $mode . " mode, generated from " . $rel . " */\n" . $css ) ) { // phpcs:ignore
				return VELOX_URL . $rel . '?ver=' . VELOX_VERSION;
			}
			foreach ( (array) glob( $dir . '/' . $handle . '-' . $mode . '-*.css' ) as $old ) {
				if ( $old !== $file ) {
					@unlink( $old ); // phpcs:ignore
				}
			}
		}
		return trailingslashit( $up['baseurl'] ) . 'velox/theme/' . $name;
	}

	/**
	 * Hand-tuned tokens where one colour ramp serves text AND surfaces (the
	 * Velox admin): converted automatically, ink would stay dark on dark.
	 */
	private static function extra( $handle, $mode ) {
		if ( 'velox-admin' === $handle && 'dark' === $mode ) {
			return "
:root,.velox-wrap{--vx-n0:#1a1b1f;--vx-n25:#1f2025;--vx-n50:#121316;--vx-n100:#26272c;--vx-n150:#303137;--vx-n200:#3b3c43;--vx-n300:#55565e;--vx-n400:#8e8e97;--vx-n500:#a3a3ab;--vx-n600:#bcbcc3;--vx-n700:#d2d2d8;--vx-n800:#e2e2e7;--vx-n850:#ebebef;--vx-n900:#f2f2f5;"
				. "--vx-primary-ink:#5fccff;--vx-primary-tint:rgba(42,183,241,.14);--vx-secondary-tint:rgba(42,183,241,.14);--vx-amber-tint:rgba(245,158,11,.14);"
				. "--vx-success:#4ad07f;--vx-success-tint:rgba(74,208,127,.13);--vx-warning:#f0b04a;--vx-warning-tint:rgba(240,176,74,.13);--vx-danger:#ff7b72;--vx-danger-tint:rgba(255,123,114,.13);"
				. "--vx-hover:rgba(42,183,241,.10);--vx-tile-dark:#0d0e10;--vx-shadow-border:0 0 0 1px rgba(255,255,255,.08);--vx-shadow-sm:0 0 0 1px rgba(255,255,255,.06);--vx-shadow:0 0 0 1px rgba(255,255,255,.07);--vx-shadow-md:0 0 0 1px rgba(255,255,255,.08);--vx-shadow-h:0 12px 40px -8px rgba(0,0,0,.6);color-scheme:dark}
"
				. "html[data-vxt=dark] body.wp-admin #wpcontent,html[data-vxt=dark] body.wp-admin #wpbody,html[data-vxt=dark] body.wp-admin{background:#121316}
"
				// Ink-filled controls (black buttons, active tabs) would turn light with
				// grey text: in dark mode they take the accent, info surfaces stay dark.
				. "html[data-vxt=dark] .velox-btn--primary,html[data-vxt=dark] .velox-nav-item.is-active,html[data-vxt=dark] .velox-perf-navitem.is-active,html[data-vxt=dark] .velox-chip.is-active{background:var(--vx-primary);color:#04222f;border-color:var(--vx-primary)}
"
				. "html[data-vxt=dark] .velox-btn--primary:hover{background:var(--vx-primary-h);color:#04222f}html[data-vxt=dark] .velox-nav-item.is-active svg,html[data-vxt=dark] .velox-nav-item.is-active *{color:#04222f}
"
				. "html[data-vxt=dark] .velox-toast,html[data-vxt=dark] .velox-batchbar,html[data-vxt=dark] .velox-info::after,html[data-vxt=dark] .vxsec-code,html[data-vxt=dark] .vmail-fcard-toolbar{background:#2c2d33;color:#f2f2f5}
";
		}
		if ( in_array( $handle, array( 'velox-builder-admin', 'velox-shop-admin' ), true ) && 'light' === $mode ) {
			return "
:root{color-scheme:light}html[data-vxt=light] body.velox-builder-fullbleed #wpcontent,html[data-vxt=light] body.velox-builder-fullbleed #wpbody{background:#f4f5f7}
";
		}
		return '';
	}

	private static function resolve_url( $base, $rel ) {
		$parts = explode( '/', $base . $rel );
		$out   = array();
		foreach ( $parts as $i => $p ) {
			if ( '..' === $p && $i > 3 ) {
				array_pop( $out );
			} elseif ( '.' !== $p ) {
				$out[] = $p;
			}
		}
		return implode( '/', $out );
	}

	public static function swap_src( $src, $handle ) {
		$area = is_admin() ? self::area() : null;
		if ( ! $area || ! isset( self::sheets()[ $handle ] ) ) {
			return $src;
		}
		return self::sheet_url( $handle, self::mode( $area ) );
	}

	/* ----------------------------------------------------- the converter */

	/**
	 * Convert a stylesheet's colours to the other mode. Works block by block,
	 * so it knows what each colour is for: a background, text or a line. A
	 * block whose background is a deliberate colour (a blue button, a dark
	 * tile, a white preview) keeps its text colour too.
	 */
	public static function convert( $css, $to ) {
		$css = preg_replace( '#/\*.*?\*/#s', '', $css ); // a comment before a property hid it from the rules
		return preg_replace_callback( '/\{([^{}]*)\}/', function ( $m ) use ( $to ) {
			return '{' . self::convert_block( $m[1], $to ) . '}';
		}, $css );
	}

	private static function convert_block( $decls, $to ) {
		$keep_text = false;
		// First the backgrounds, to learn whether this block paints its own surface.
		$decls = preg_replace_callback( '/(^|;)(\s*)(background(?:-color|-image)?|--[\w-]+)(\s*:\s*)([^;]+)/i', function ( $m ) use ( $to, &$keep_text ) {
			$prop = strtolower( $m[3] );
			$kind = 0 === strpos( $prop, '--' ) ? self::token_kind( $prop ) : 'bg';
			if ( 'bg' === $kind && 0 !== strpos( $prop, '--' ) && self::is_feature_fill( $m[5], $to ) ) {
				$keep_text = true;
				return $m[0];
			}
			return $m[1] . $m[2] . $m[3] . $m[4] . self::convert_value( $m[5], $kind, $to );
		}, $decls );
		return preg_replace_callback( '/(^|;)(\s*)(color|fill|stroke|caret-color|-webkit-text-fill-color|text-decoration-color|border(?:-[a-z-]+)?|outline(?:-color)?|box-shadow|text-shadow|column-rule(?:-color)?|accent-color)(\s*:\s*)([^;]+)/i', function ( $m ) use ( $to, $keep_text ) {
			$prop = strtolower( $m[3] );
			if ( in_array( $prop, array( 'color', 'fill', 'stroke', '-webkit-text-fill-color' ), true ) ) {
				if ( $keep_text ) {
					return $m[0];
				}
				$kind = 'text';
			} elseif ( false !== strpos( $prop, 'shadow' ) ) {
				$kind = 'shadow';
			} elseif ( 'accent-color' === $prop || 'caret-color' === $prop || 'text-decoration-color' === $prop ) {
				$kind = 'text';
			} else {
				$kind = 'line';
			}
			return $m[1] . $m[2] . $m[3] . $m[4] . self::convert_value( $m[5], $kind, $to );
		}, $decls );
	}

	private static function token_kind( $name ) {
		if ( preg_match( '/(tx|text|ink|fg|muted|label)/', $name ) ) {
			return 'text';
		}
		if ( preg_match( '/(line|border|hair|ring|divider)/', $name ) ) {
			return 'line';
		}
		if ( preg_match( '/shadow/', $name ) ) {
			return 'shadow';
		}
		if ( preg_match( '/(acc|primary|brand|accent|green|red|orange|amber|success|warning|danger|blue|purple)/', $name ) ) {
			return 'accent';
		}
		return 'bg';
	}

	/** A background that is a statement on its own: keep it, and the text on it. */
	private static function is_feature_fill( $value, $to ) {
		if ( false !== stripos( $value, 'gradient' ) || false !== stripos( $value, 'var(' ) ) {
			return false;
		}
		$c = self::first_color( $value );
		if ( ! $c ) {
			return false;
		}
		list( $h, $s, $l, $a ) = self::hsl( $c );
		if ( $a < 0.85 ) {
			return false;
		}
		if ( $s > 0.3 && $l > 0.2 && $l < 0.8 ) {
			return true; // a coloured button or badge
		}
		// A surface of the target mode already (a white preview card in a dark
		// screen, a near-black tile in a light one) was meant to look that way.
		return 'light' === $to ? $l > 0.86 : $l < 0.2;
	}

	private static function first_color( $v ) {
		if ( preg_match( '/#([0-9a-f]{8}|[0-9a-f]{6}|[0-9a-f]{3,4})\b|rgba?\([^)]*\)/i', $v, $m ) ) {
			return self::parse( $m[0] );
		}
		return null;
	}

	public static function convert_value( $v, $kind, $to ) {
		return preg_replace_callback( '/#([0-9a-f]{8}|[0-9a-f]{6}|[0-9a-f]{3,4})\b|rgba?\([^)]*\)|\b(white|black)\b/i', function ( $m ) use ( $kind, $to ) {
			$c = self::parse( $m[0] );
			return $c ? self::fmt( self::map( $c, $kind, $to ) ) : $m[0];
		}, $v );
	}

	private static function parse( $s ) {
		$s = strtolower( trim( $s ) );
		if ( 'white' === $s ) {
			return array( 255, 255, 255, 1 );
		}
		if ( 'black' === $s ) {
			return array( 0, 0, 0, 1 );
		}
		if ( '#' === $s[0] ) {
			$h = substr( $s, 1 );
			if ( 3 === strlen( $h ) || 4 === strlen( $h ) ) {
				$h = preg_replace( '/(.)/', '$1$1', $h );
			}
			$a = 8 === strlen( $h ) ? hexdec( substr( $h, 6, 2 ) ) / 255 : 1;
			return array( hexdec( substr( $h, 0, 2 ) ), hexdec( substr( $h, 2, 2 ) ), hexdec( substr( $h, 4, 2 ) ), $a );
		}
		if ( preg_match( '/rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s\/]+([\d.]+%?))?\s*\)/', $s, $m ) ) {
			$a = isset( $m[4] ) && '' !== $m[4] ? ( '%' === substr( $m[4], -1 ) ? (float) $m[4] / 100 : (float) $m[4] ) : 1;
			return array( (float) $m[1], (float) $m[2], (float) $m[3], $a );
		}
		return null;
	}

	private static function hsl( $c ) {
		$r = $c[0] / 255;
		$g = $c[1] / 255;
		$b = $c[2] / 255;
		$max = max( $r, $g, $b );
		$min = min( $r, $g, $b );
		$l   = ( $max + $min ) / 2;
		$h   = 0;
		$s   = 0;
		if ( $max !== $min ) {
			$d = $max - $min;
			$s = $l > 0.5 ? $d / ( 2 - $max - $min ) : $d / ( $max + $min );
			if ( $max === $r ) {
				$h = ( $g - $b ) / $d + ( $g < $b ? 6 : 0 );
			} elseif ( $max === $g ) {
				$h = ( $b - $r ) / $d + 2;
			} else {
				$h = ( $r - $g ) / $d + 4;
			}
			$h /= 6;
		}
		return array( $h, $s, $l, $c[3] );
	}

	private static function rgb( $h, $s, $l, $a ) {
		if ( 0 == $s ) {
			$v = $l * 255;
			return array( $v, $v, $v, $a );
		}
		$q = $l < 0.5 ? $l * ( 1 + $s ) : $l + $s - $l * $s;
		$p = 2 * $l - $q;
		$f = function ( $t ) use ( $p, $q ) {
			if ( $t < 0 ) {
				$t += 1;
			}
			if ( $t > 1 ) {
				$t -= 1;
			}
			if ( $t < 1 / 6 ) {
				return $p + ( $q - $p ) * 6 * $t;
			}
			if ( $t < 1 / 2 ) {
				return $q;
			}
			if ( $t < 2 / 3 ) {
				return $p + ( $q - $p ) * ( 2 / 3 - $t ) * 6;
			}
			return $p;
		};
		return array( $f( $h + 1 / 3 ) * 255, $f( $h ) * 255, $f( $h - 1 / 3 ) * 255, $a );
	}

	private static function fmt( $c ) {
		$r = (int) round( max( 0, min( 255, $c[0] ) ) );
		$g = (int) round( max( 0, min( 255, $c[1] ) ) );
		$b = (int) round( max( 0, min( 255, $c[2] ) ) );
		$a = round( max( 0, min( 1, $c[3] ) ), 3 );
		return $a >= 1 ? sprintf( '#%02x%02x%02x', $r, $g, $b ) : 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $a . ')';
	}

	private static function clamp( $v, $lo, $hi ) {
		return max( $lo, min( $hi, $v ) );
	}

	/** The rules, in one place (mirrored in velox-theme.js). */
	public static function map( $c, $kind, $to ) {
		list( $h, $s, $l, $a ) = self::hsl( $c );
		$neutral = $s < 0.22 || $l > 0.94 || $l < 0.06;
		$white_ov = $c[0] > 230 && $c[1] > 230 && $c[2] > 230 && $a < 1;
		$black_ov = $c[0] < 40 && $c[1] < 40 && $c[2] < 40 && $a < 1;
		if ( 'accent' === $kind ) {
			return $c;
		}
		if ( 'light' === $to ) { // dark screen → light
			if ( 'shadow' === $kind ) {
				return $black_ov || ( $l < 0.2 ) ? array( 15, 23, 42, self::clamp( $a * 0.35, 0.04, 0.18 ) ) : ( $white_ov ? array( 0, 0, 0, $a * 0.5 ) : $c );
			}
			if ( $white_ov ) {
				return array( 15, 20, 30, self::clamp( $a * ( 'line' === $kind ? 1.25 : 0.9 ), 0, 1 ) );
			}
			if ( $black_ov ) {
				if ( 'bg' === $kind && $a >= 0.8 ) {
					return array( 252, 252, 253, $a ); // a frosted bar, not a backdrop
				}
				return 'bg' === $kind ? array( $c[0], $c[1], $c[2], $a * 0.55 ) : array( 255, 255, 255, $a );
			}
			if ( ! $neutral ) {
				if ( 'text' === $kind && $l > 0.6 ) {
					return self::rgb( $h, min( 1, $s * 1.05 ), self::clamp( 1.02 - $l, 0.3, 0.42 ), $a );
				}
				if ( 'bg' === $kind && $a < 0.5 ) {
					return $c; // a soft tint reads on white too
				}
				if ( 'bg' === $kind && $l < 0.2 ) {
					return self::rgb( $h, $s * 0.6, 0.95, $a ); // tinted dark surface → tinted light surface
				}
				return $c;
			}
			if ( 'text' === $kind ) {
				return self::rgb( $h, $s, self::clamp( ( 1 - $l ) * 0.85 + 0.06, 0.06, 0.46 ), $a );
			}
			if ( 'line' === $kind ) {
				return $l < 0.5 ? self::rgb( $h, $s, self::clamp( 1 - $l + 0.02, 0.72, 0.92 ), $a ) : $c;
			}
			// background
			if ( $l > 0.8 ) {
				return $c; // already a light surface (previews, white cards)
			}
			if ( $l < 0.085 ) {
				return self::rgb( $h, $s, 0.955, $a ); // the canvas
			}
			return self::rgb( $h, $s, self::clamp( 1 - ( $l - 0.09 ) * 1.05, 0.8, 1 ), $a );
		}
		// light screen → dark
		if ( 'shadow' === $kind ) {
			return $black_ov ? array( 0, 0, 0, self::clamp( $a * 1.8, 0.2, 0.6 ) ) : $c;
		}
		if ( $black_ov ) {
			return array( 255, 255, 255, self::clamp( $a * ( 'line' === $kind ? 1.3 : 1 ), 0, 1 ) );
		}
		if ( $white_ov ) {
			return 'bg' === $kind ? array( 20, 22, 27, $a ) : $c;
		}
		if ( ! $neutral ) {
			if ( 'text' === $kind && $l < 0.5 ) {
				return self::rgb( $h, $s, 0.7, $a );
			}
			if ( 'bg' === $kind && $l > 0.85 && $a > 0.5 ) {
				return self::rgb( $h, $s * 0.55, 0.16, $a ); // pale tint → dark tint
			}
			return $c;
		}
		if ( 'text' === $kind ) {
			return self::rgb( $h, $s, self::clamp( 0.98 - $l * 0.8, 0.52, 0.94 ), $a );
		}
		if ( 'line' === $kind ) {
			return $l > 0.5 ? self::rgb( $h, $s, self::clamp( 1.1 - $l, 0.16, 0.34 ), $a ) : $c;
		}
		if ( $l < 0.25 ) {
			return $c; // a deliberate dark tile
		}
		if ( $l >= 0.97 ) {
			return self::rgb( $h, $s, 0.105, $a ); // cards and panels
		}
		if ( $l >= 0.955 ) {
			return self::rgb( $h, $s, 0.075, $a ); // the canvas
		}
		return self::rgb( $h, $s, self::clamp( 0.105 + ( 0.97 - $l ) * 0.9, 0.105, 0.5 ), $a );
	}

	/* ------------------------------------------------- the floating switch */

	/** Before anything is drawn: the mode on <html>, and the browser's own preference remembered. */
	public static function head() {
		$area = self::area();
		if ( ! $area ) {
			return;
		}
		self::print_boot( $area );
	}

	public static function print_boot( $area ) {
		$p = self::prefs();
		echo '<script>(function(){var d=document.documentElement,s=window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches;'
			. 'document.cookie="velox_sys_dark="+(s?1:0)+";path=/;max-age=31536000;SameSite=Lax";'
			. 'var p=' . wp_json_encode( $p ) . ',a=' . wp_json_encode( $area ) . ',m=(a!=="velox"&&p[a]&&p[a]!=="inherit")?p[a]:p.all;if(m==="system"){m=s?"dark":"light";}'
			. 'd.setAttribute("data-vxt",m);d.setAttribute("data-vxarea",a);})();</script>' . "\n";
	}

	public static function footer() {
		$area = self::area();
		if ( ! $area ) {
			return;
		}
		self::print_switch( $area );
	}

	/** Data + script for the switch (also printed by the editor, which has no admin footer). */
	public static function print_switch( $area ) {
		$urls = array();
		foreach ( array_keys( self::sheets() ) as $h ) {
			$urls[ $h ] = array( 'light' => self::sheet_url( $h, 'light' ), 'dark' => self::sheet_url( $h, 'dark' ) );
		}
		$cfg = array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'velox_theme' ),
			'area'  => $area,
			'prefs' => self::prefs(),
			'urls'  => $urls,
			'i18n'  => array(
				'title'   => __( 'Appearance', 'velox' ),
				'all'     => __( 'All of Velox', 'velox' ),
				'builder' => __( 'Velox Builder', 'velox' ),
				'shop'    => __( 'Velox Shop', 'velox' ),
				'light'   => __( 'Light', 'velox' ),
				'dark'    => __( 'Dark', 'velox' ),
				'system'  => __( 'System', 'velox' ),
				'inherit' => __( 'Same as Velox', 'velox' ),
				'here'    => __( 'This screen', 'velox' ),
				'note'    => __( 'Only for you — everyone chooses their own.', 'velox' ),
				'toggle'  => __( 'Switch between light and dark', 'velox' ),
				'more'    => __( 'Appearance settings', 'velox' ),
			),
		);
		echo '<script>window.VELOX_THEME=' . wp_json_encode( $cfg ) . ';</script>';
		echo '<script src="' . esc_url( VELOX_ASSETS . 'js/velox-theme.js?ver=' . VELOX_VERSION ) . '"></script>' . "\n";
		// One colour picker for every colour field on Velox screens.
		echo '<link rel="stylesheet" href="' . esc_url( VELOX_ASSETS . 'css/velox-color.css?ver=' . VELOX_VERSION ) . '">';
		echo '<script>window.VELOX_COLOR_I18N=' . wp_json_encode( array(
			'colour' => __( 'Colour', 'velox' ),
			'sv'     => __( 'Saturation and brightness', 'velox' ),
			'hue'    => __( 'Hue', 'velox' ),
			'drop'   => __( 'Pick a colour from the screen', 'velox' ),
			'before' => __( 'Before — click to go back', 'velox' ),
			'hex'    => __( 'Hex value', 'velox' ),
			'sat'    => __( 'Saturation', 'velox' ),
			'bright' => __( 'brightness', 'velox' ),
			'page'   => __( 'On this screen', 'velox' ),
			'recent' => __( 'Recent', 'velox' ),
		) ) . ';</script>';
		echo '<script src="' . esc_url( VELOX_ASSETS . 'js/velox-color.js?ver=' . VELOX_VERSION ) . '"></script>' . "\n";
	}

	public static function ajax_save() {
		if ( ! is_user_logged_in() || ! check_ajax_referer( 'velox_theme', 'nonce', false ) ) {
			wp_send_json_error( null, 403 );
		}
		$in  = json_decode( wp_unslash( (string) ( $_POST['prefs'] ?? '{}' ) ), true );
		$cur = self::prefs();
		foreach ( array( 'all', 'builder', 'shop' ) as $k ) {
			if ( isset( $in[ $k ] ) && in_array( $in[ $k ], 'all' === $k ? array( 'light', 'dark', 'system' ) : array( 'inherit', 'light', 'dark', 'system' ), true ) ) {
				$cur[ $k ] = $in[ $k ];
			}
		}
		update_user_meta( get_current_user_id(), self::META, $cur );
		wp_send_json_success( array( 'prefs' => $cur ) );
	}
}
