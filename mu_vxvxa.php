<?php
// Sandbox-only: run stored JS once on an admin or front page with ?vxa=1 and post the result back.
function vxa_js() {
	if ( empty( $_GET['vxa'] ) ) { return; }
	$code = get_option( 'vxt_aeval', '' ); if ( '' === $code ) { return; } delete_option( 'vxt_aeval' );
	echo "<script>(function(){var sleep=function(t){return new Promise(function(r){setTimeout(r,t)})};var q=function(s){return document.querySelector(s)},qa=function(s){return [].slice.call(document.querySelectorAll(s))};var errs=[];window.addEventListener('error',function(e){errs.push(e.message+' @'+e.lineno)});window.confirm=function(){return true};function report(r){var b=new FormData();b.append('action','vxt_report');b.append('r',JSON.stringify(r));return fetch('" . esc_url( admin_url( 'admin-ajax.php' ) ) . "?k=aeval',{method:'POST',body:b,credentials:'same-origin'})}window.addEventListener('load',function(){setTimeout(async function(){var r;try{r=await (async function(){" . $code . "})()}catch(e){r={exc:e.message,stack:(e.stack||'').slice(0,300)}}if(r&&typeof r==='object'){r.__errs=errs}await report(r)},800)})})();</script>";
}
add_action( 'admin_footer', 'vxa_js', 99 );
add_action( 'wp_footer', 'vxa_js', 99 );
