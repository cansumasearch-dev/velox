<?php
// Sandbox-only test harness for the Velox Builder.
add_action( 'wp_ajax_vxt_report', function () {
	update_option( 'vxt_report_' . sanitize_key( $_GET['k'] ?? 'a' ), wp_unslash( $_POST['r'] ) );
	wp_send_json_success();
} );
add_action( 'plugins_loaded', function () {
	if ( ! is_admin() || ( empty( $_GET['vxtest'] ) && empty( $_GET['vxnc'] ) ) ) { return; }
	ob_start( function ( $html ) {
		if ( false === strpos( $html, 'velox-builder-root' ) ) { return $html; }
		$html = preg_replace( '/(velox-builder\.(js|css))\?v=[^"\']+/', '$1?v=' . time(), $html );
		if ( empty( $_GET['vxtest'] ) ) { return $html; }
		$js = ''; foreach ( glob( WP_CONTENT_DIR . '/vxh/*.js' ) as $f ) { $js .= file_get_contents( $f ) . "\n"; }
		if ( 'eval' === $_GET['vxtest'] ) { $js .= "\n(function(){var sleep=function(t){return new Promise(function(r){setTimeout(r,t)})};function cd(){var f=document.getElementById('vb-canvas');return f&&f.contentDocument}function report(r){var b=new FormData();b.append('action','vxt_report');b.append('r',JSON.stringify(r));return fetch(window.VELOX_BUILDER.ajaxurl+'?k=eval',{method:'POST',body:b,credentials:'same-origin'})}setTimeout(async function(){var t0=Date.now();while(!(cd()&&cd().body&&document.querySelector('.vb-inspector'))&&Date.now()-t0<30000){await sleep(200)}await sleep(1500);var r;try{r=await (async function(){" . get_option( 'vxt_eval', 'return 1;' ) . "})()}catch(e){r={exc:e.message}}await report(r)},300)})();"; }
		return str_replace( '</body>', '<script>' . $js . '</script></body>', $html );
	} );
} );
