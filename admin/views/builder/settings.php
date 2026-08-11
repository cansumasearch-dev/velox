<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox Builder — Settings: how the builder outputs CSS and renders pages. */
$s = Velox_Builder::settings();
?>
<div class="vba-shell">
	<header class="vba-head">
		<div><h1><?php esc_html_e( 'Settings', 'velox' ); ?></h1>
		<p><?php esc_html_e( 'How Velox Builder outputs CSS and renders your pages.', 'velox' ); ?></p></div>
		<div class="vba-head-actions">
			<button class="vba-btn vba-btn-primary" id="vba-settings-save"><?php esc_html_e( 'Save changes', 'velox' ); ?></button>
		</div>
	</header>
	<div class="vba-card">
		<div class="vba-setting">
			<div class="vba-setting-tx"><b><?php esc_html_e( 'CSS output', 'velox' ); ?></b><span><?php esc_html_e( 'Write a static .css file (best for caching) or inline it in the page head.', 'velox' ); ?></span></div>
			<select id="vba-css-mode" class="vba-setting-ctrl">
				<option value="file" <?php selected( $s['css_mode'], 'file' ); ?>><?php esc_html_e( 'Static file', 'velox' ); ?></option>
				<option value="inline" <?php selected( $s['css_mode'], 'inline' ); ?>><?php esc_html_e( 'Inline', 'velox' ); ?></option>
			</select>
		</div>
		<div class="vba-setting">
			<div class="vba-setting-tx"><b><?php esc_html_e( 'Minify CSS', 'velox' ); ?></b><span><?php esc_html_e( 'Strip whitespace from generated CSS.', 'velox' ); ?></span></div>
			<label class="vba-switch"><input type="checkbox" id="vba-minify" <?php checked( $s['minify'], 1 ); ?>><span></span></label>
		</div>
		<div class="vba-setting">
			<div class="vba-setting-tx"><b><?php esc_html_e( 'Default container width', 'velox' ); ?></b><span><?php esc_html_e( 'Max width, in pixels, for centered content.', 'velox' ); ?></span></div>
			<input type="number" id="vba-container" class="vba-setting-ctrl" value="<?php echo esc_attr( $s['container'] ); ?>">
		</div>
	</div>
</div>
<script>
( function () {
	var CFG = window.VELOX_BUILDER || {};
	document.getElementById( 'vba-settings-save' ).addEventListener( 'click', function () {
		var btn = this; btn.textContent = 'Saving…';
		var body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', 'builder_settings_save' ); body.set( 'nonce', CFG.nonce || '' );
		body.set( 'css_mode', document.getElementById( 'vba-css-mode' ).value );
		body.set( 'minify', document.getElementById( 'vba-minify' ).checked ? '1' : '' );
		body.set( 'container', document.getElementById( 'vba-container' ).value );
		fetch( CFG.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) { btn.textContent = res && res.success ? 'Saved' : 'Save failed'; setTimeout( function () { btn.textContent = 'Save changes'; }, 1500 ); } )
			.catch( function () { btn.textContent = 'Save failed'; } );
	} );
}() );
</script>
