<?php
/**
 * Velox i18n regression guard.
 *
 * Scans the plugin for user-facing strings that are NOT wrapped for translation,
 * and for wrapped strings that have no German entry yet. Run before shipping:
 *
 *   php bin/check-i18n.php
 *
 * Exit code 0 = clean, 1 = issues found. Intended as a dev aid, not shipped logic.
 */

$root = dirname( __DIR__ );
$dict_file = $root . '/includes/lang/de_DE.php';
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}
$dict = is_readable( $dict_file ) ? include $dict_file : array();

/* ---- 1. PHP: source __() calls with no German entry ---------------------- */
$php_files = array();
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
foreach ( $it as $f ) {
	if ( $f->isFile() && 'php' === $f->getExtension() && strpos( $f->getPathname(), '/bin/' ) === false ) {
		$php_files[] = $f->getPathname();
	}
}
$php_calls = array();
$pat = '/(?:esc_html__|esc_attr__|esc_html_e|esc_attr_e|__|_e|esc_js)\(\s*([\'"])(.*?)(?<!\\\\)\1\s*,\s*[\'"]velox[\'"]/s';
foreach ( $php_files as $f ) {
	if ( preg_match_all( $pat, file_get_contents( $f ), $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $x ) {
			$val = ( "'" === $x[1] ) ? str_replace( array( '\\\\', "\\'" ), array( '\\', "'" ), $x[2] ) : stripcslashes( $x[2] );
			$php_calls[ $val ] = true;
		}
	}
}

/* ---- 2. JS: vxT() calls with no German entry ----------------------------- */
$js_calls = array();
foreach ( glob( $root . '/admin/js/*.js' ) as $jsfile ) {
	$js = file_get_contents( $jsfile );
	if ( preg_match_all( '/vxT\(\s*([\'"])(.*?)(?<!\\\\)\1/s', $js, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $x ) {
			$val = ( "'" === $x[1] ) ? str_replace( array( '\\\\', "\\'" ), array( '\\', "'" ), $x[2] ) : stripcslashes( $x[2] );
			$js_calls[ $val ] = true;
		}
	}
}

/* A string is "code, not prose" if it's punctuation/paths/tokens only. */
function vx_is_code( $t ) {
	$t = trim( $t );
	if ( '' === $t ) {
		return true;
	}
	if ( preg_match( '/^[\/.\d\s%$#:;,()\[\]&|·—…\-]+$/u', $t ) ) {
		return true;
	}
	if ( preg_match( '/^(og:|type:|dashicons|googleapis|billing@|GA4|de-DE|en-GB|wp-|\/wp|\/new$|\[velox|get_field|Velox_Fields|z-index|px$|lang$|functions\.php|robots\.txt|\.htaccess|\.json|\.sql|\.zip|\.htm|\.lottie|\.vxck|dash\.cloudflare)/', $t ) ) {
		return true;
	}
	// Already-German or format-only header labels that intentionally stay literal.
	if ( 'Dateiname | Alt-Text | Titel' === $t ) {
		return true;
	}
	$literal = array( 'Velox', 'WebP', 'AVIF', 'PageSpeed', 'CSS', 'JS', 'HTML', 'PHP', 'SVG', 'GD', 'SEO', 'Cloudflare', 'Imagick', 'CAPTCHA', 'GIF', 'JPG', 'PNG', 'SMTP', 'URL', 'TLS', 'SSL', 'BCC', 'CC', 'WYSIWYG', 'UTF-8', 'GMX', 'IONOS', 'SES', 'Mailgun', 'Postmark', 'SendGrid', 'Zoho Mail', 'GB', 'KB', 'MB' );
	return in_array( $t, $literal, true );
}

$missing_php = array();
foreach ( array_keys( $php_calls ) as $s ) {
	if ( ! vx_is_code( $s ) && ! isset( $dict[ $s ] ) ) {
		$missing_php[] = $s;
	}
}
$missing_js = array();
foreach ( array_keys( $js_calls ) as $s ) {
	if ( ! vx_is_code( $s ) && ! isset( $dict[ $s ] ) ) {
		$missing_js[] = $s;
	}
}

echo "Velox i18n check\n";
echo '  PHP velox-domain strings: ' . count( $php_calls ) . "\n";
echo '  JS  vxT() strings:        ' . count( $js_calls ) . "\n";
echo '  German dictionary:        ' . count( $dict ) . "\n\n";

$fail = 0;
if ( $missing_php ) {
	$fail = 1;
	echo 'MISSING German for ' . count( $missing_php ) . " PHP string(s):\n";
	foreach ( $missing_php as $s ) {
		echo '  - ' . $s . "\n";
	}
}
if ( $missing_js ) {
	$fail = 1;
	echo 'MISSING German for ' . count( $missing_js ) . " JS string(s):\n";
	foreach ( $missing_js as $s ) {
		echo '  - ' . $s . "\n";
	}
}
if ( ! $fail ) {
	echo "OK — every translatable string has a German entry.\n";
}
exit( $fail );
