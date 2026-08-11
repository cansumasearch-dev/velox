<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox Builder — Fonts & icons: font families output on built pages. */
$fonts = Velox_Builder::fonts();
?>
<div class="vba-shell">
	<header class="vba-head">
		<div><h1><?php esc_html_e( 'Fonts &amp; icons', 'velox' ); ?></h1>
		<p><?php esc_html_e( 'Register font families to use in the editor. Google Fonts by name, or a self-hosted CSS URL.', 'velox' ); ?></p></div>
		<div class="vba-head-actions">
			<button class="vba-btn vba-btn-primary" id="vba-fonts-save"><?php esc_html_e( 'Save changes', 'velox' ); ?></button>
		</div>
	</header>
	<div class="vba-card">
		<div class="vba-card-h"><div><h2><?php esc_html_e( 'Font families', 'velox' ); ?></h2><p><?php esc_html_e( 'These load on every built page.', 'velox' ); ?></p></div>
			<button class="vba-btn vba-btn-ghost vba-btn-sm" id="vba-add-font"><?php esc_html_e( 'Add font', 'velox' ); ?></button></div>
		<div class="vba-tokens" id="vba-fonts">
			<?php if ( empty( $fonts ) ) : ?>
				<div class="vba-token-row"><input type="text" class="vba-font-name" placeholder="Inter"><select class="vba-font-type"><option value="google">Google Fonts</option><option value="url">CSS URL</option></select><input type="text" class="vba-font-url" placeholder="https:// (only for CSS URL)"><button class="vba-mini vba-mini-del vba-font-del"><?php esc_html_e( 'Remove', 'velox' ); ?></button></div>
			<?php else : foreach ( $fonts as $f ) : ?>
				<div class="vba-token-row">
					<input type="text" class="vba-font-name" value="<?php echo esc_attr( $f['name'] ); ?>" placeholder="Inter">
					<select class="vba-font-type"><option value="google" <?php selected( $f['type'], 'google' ); ?>>Google Fonts</option><option value="url" <?php selected( $f['type'], 'url' ); ?>>CSS URL</option></select>
					<input type="text" class="vba-font-url" value="<?php echo esc_attr( $f['url'] ); ?>" placeholder="https://">
					<button class="vba-mini vba-mini-del vba-font-del"><?php esc_html_e( 'Remove', 'velox' ); ?></button>
				</div>
			<?php endforeach; endif; ?>
		</div>
	</div>
</div>
<script>
( function () {
	var CFG = window.VELOX_BUILDER || {};
	var wrap = document.getElementById( 'vba-fonts' );
	document.getElementById( 'vba-add-font' ).addEventListener( 'click', function () {
		wrap.insertAdjacentHTML( 'beforeend', '<div class="vba-token-row"><input type="text" class="vba-font-name" placeholder="Inter"><select class="vba-font-type"><option value="google">Google Fonts</option><option value="url">CSS URL</option></select><input type="text" class="vba-font-url" placeholder="https://"><button class="vba-mini vba-mini-del vba-font-del">Remove</button></div>' );
	} );
	wrap.addEventListener( 'click', function ( e ) { var d = e.target.closest( '.vba-font-del' ); if ( d ) { d.closest( '.vba-token-row' ).remove(); } } );
	document.getElementById( 'vba-fonts-save' ).addEventListener( 'click', function () {
		var btn = this; btn.textContent = 'Saving…';
		var fonts = [].map.call( wrap.querySelectorAll( '.vba-token-row' ), function ( r ) {
			return { name: r.querySelector( '.vba-font-name' ).value.trim(), type: r.querySelector( '.vba-font-type' ).value, url: r.querySelector( '.vba-font-url' ).value.trim() };
		} ).filter( function ( f ) { return f.name; } );
		var body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', 'builder_fonts_save' ); body.set( 'nonce', CFG.nonce || '' );
		body.set( 'fonts', JSON.stringify( fonts ) );
		fetch( CFG.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) { btn.textContent = res && res.success ? 'Saved' : 'Save failed'; setTimeout( function () { btn.textContent = 'Save changes'; }, 1500 ); } )
			.catch( function () { btn.textContent = 'Save failed'; } );
	} );
}() );
</script>
