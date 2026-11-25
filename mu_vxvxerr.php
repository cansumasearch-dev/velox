<?php
add_action( 'plugins_loaded', function () {
	if ( ! is_admin() || empty( $_GET['vxerr'] ) ) { return; }
	ob_start( function ( $html ) {
		if ( false === strpos( $html, 'velox-builder-root' ) ) { return $html; }
		$js = "<script>window.__vxe=[];window.addEventListener('error',function(e){window.__vxe.push(e.message+' @'+e.lineno+':'+e.colno)});window.addEventListener('unhandledrejection',function(e){window.__vxe.push('REJ '+(e.reason&&e.reason.message||e.reason))});</script>";
		$html = preg_replace( '/<head>/', '<head>' . $js, $html, 1 );
		$rep = "<script>setTimeout(function(){var b=new FormData();b.append('action','vxt_report');b.append('r',JSON.stringify({errs:window.__vxe}));fetch(window.VELOX_BUILDER.ajaxurl+'?k=err',{method:'POST',body:b,credentials:'same-origin'})},9000);</script>";
		return str_replace( '</body>', $rep . '</body>', $html );
	} );
} );
