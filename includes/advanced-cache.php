<?php
/**
 * Velox advanced-cache.php drop-in.
 *
 * Loaded by WordPress (when WP_CACHE is true) before plugins. Serves a static
 * cached page when one is fresh, otherwise returns control to WordPress, which
 * regenerates the cache via Velox_Cache. Standalone by necessity — no WP APIs,
 * no plugin classes are available this early.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

$velox_dir = ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content' ) . '/cache/velox';
$velox_cfg_file = $velox_dir . '/_config.json';
if ( ! is_readable( $velox_cfg_file ) ) {
	return;
}
$velox_cfg = json_decode( (string) file_get_contents( $velox_cfg_file ), true );
if ( ! is_array( $velox_cfg ) || empty( $velox_cfg['enabled'] ) ) {
	return;
}

// --- Only plain GET requests are servable ---
$velox_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( $_SERVER['REQUEST_METHOD'] ) : 'GET';
if ( 'GET' !== $velox_method ) {
	return;
}
if ( isset( $_GET['ct_builder'] ) || isset( $_GET['oxygen_iframe'] ) || isset( $_GET['ct_inner'] ) ) {
	return;
}

// --- Query string: only known tracking params allowed ---
if ( ! empty( $_GET ) ) {
	$velox_ignored = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'mc_cid', 'mc_eid', '_ga', 'ref' );
	foreach ( array_keys( $_GET ) as $velox_k ) {
		if ( ! in_array( $velox_k, $velox_ignored, true ) ) {
			return;
		}
	}
}

// --- Cookie exclusions: logged-in, comment authors, carts, sessions ---
if ( ! empty( $_COOKIE ) ) {
	$velox_cookie_block = array( 'comment_author_', 'wp-postpass_', 'woocommerce_items_in_cart', 'woocommerce_cart_hash', 'wp_woocommerce_session_', 'edd_items_in_cart' );
	if ( empty( $velox_cfg['logged_in'] ) ) {
		$velox_cookie_block[] = 'wordpress_logged_in_';
	}
	if ( ! empty( $velox_cfg['exclude_cookies'] ) && is_array( $velox_cfg['exclude_cookies'] ) ) {
		$velox_cookie_block = array_merge( $velox_cookie_block, $velox_cfg['exclude_cookies'] );
	}
	foreach ( array_keys( $_COOKIE ) as $velox_cn ) {
		foreach ( $velox_cookie_block as $velox_bp ) {
			if ( '' !== $velox_bp && false !== strpos( $velox_cn, $velox_bp ) ) {
				return;
			}
		}
	}
}

// --- URL exclusions ---
$velox_uri  = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
$velox_path = parse_url( $velox_uri, PHP_URL_PATH );
$velox_path = $velox_path ? $velox_path : '/';
if ( ! empty( $velox_cfg['exclude_urls'] ) && is_array( $velox_cfg['exclude_urls'] ) ) {
	foreach ( $velox_cfg['exclude_urls'] as $velox_rule ) {
		$velox_rule = trim( $velox_rule );
		if ( '' === $velox_rule ) {
			continue;
		}
		$velox_re = '#^' . str_replace( '\*', '.*', preg_quote( $velox_rule, '#' ) ) . '$#i';
		if ( preg_match( $velox_re, $velox_path ) ) {
			return;
		}
	}
}

// --- Resolve the cache file (must mirror Velox_Cache::path_for) ---
$velox_host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost';
$velox_host = strtolower( preg_replace( '/[^a-z0-9.\-]/i', '', $velox_host ) );
$velox_p    = str_replace( '..', '', $velox_path );
$velox_p    = preg_replace( '#[^a-zA-Z0-9/_\-]#', '', $velox_p );
$velox_p    = trim( $velox_p, '/' );

$velox_mobile = false;
if ( ! empty( $velox_cfg['mobile_separate'] ) ) {
	$velox_ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
	$velox_mobile = (bool) preg_match( '/Mobile|Android|iPhone|iPod|BlackBerry|IEMobile|Opera Mini/i', $velox_ua );
}

$velox_rel  = $velox_host . ( '' === $velox_p ? '' : '/' . $velox_p );
$velox_base = $velox_dir . '/' . $velox_rel . '/' . ( $velox_mobile ? 'index-mobile' : 'index' ) . '.html';

if ( ! is_readable( $velox_base ) ) {
	return; // miss — let WordPress build it
}

// --- Freshness ---
$velox_ttl = isset( $velox_cfg['ttl'] ) ? (int) $velox_cfg['ttl'] : 36000;
if ( $velox_ttl > 0 && ( time() - filemtime( $velox_base ) ) > $velox_ttl ) {
	return; // stale — regenerate
}

// --- Content negotiation: serve precompressed when possible ---
$velox_accept = isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : '';
$velox_send   = $velox_base;
$velox_enc    = '';
if ( ! empty( $velox_cfg['gzip'] ) ) {
	if ( false !== strpos( $velox_accept, 'br' ) && is_readable( $velox_base . '.br' ) ) {
		$velox_send = $velox_base . '.br';
		$velox_enc  = 'br';
	} elseif ( false !== strpos( $velox_accept, 'gzip' ) && is_readable( $velox_base . '.gz' ) ) {
		$velox_send = $velox_base . '.gz';
		$velox_enc  = 'gzip';
	}
}

header( 'Content-Type: text/html; charset=UTF-8' );
header( 'X-Velox-Cache: HIT' );
if ( '' !== $velox_enc ) {
	header( 'Content-Encoding: ' . $velox_enc );
	header( 'Vary: Accept-Encoding' );
}
header( 'Content-Length: ' . filesize( $velox_send ) );
readfile( $velox_send );
exit;
