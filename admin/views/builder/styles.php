<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox Builder — Global styles: design tokens output as CSS variables. */
$tokens = Velox_Builder::tokens();
?>
<div class="vba-shell">
	<header class="vba-head">
		<div><h1><?php esc_html_e( 'Global styles', 'velox' ); ?></h1>
		<p><?php esc_html_e( 'Colors and spacing that flow into every page as CSS variables.', 'velox' ); ?></p></div>
		<div class="vba-head-actions">
			<button class="vba-btn vba-btn-primary" id="vba-tokens-save"><?php esc_html_e( 'Save changes', 'velox' ); ?></button>
		</div>
	</header>

	<div class="vba-card">
		<div class="vba-card-h"><div><h2><?php esc_html_e( 'Colors', 'velox' ); ?></h2><p><?php esc_html_e( 'Each color is available as var(--name).', 'velox' ); ?></p></div>
			<button class="vba-btn vba-btn-ghost vba-btn-sm" id="vba-add-color"><?php esc_html_e( 'Add color', 'velox' ); ?></button></div>
		<div class="vba-tokens" id="vba-colors">
			<?php foreach ( $tokens['colors'] as $c ) : ?>
				<div class="vba-token-row">
					<input type="color" class="vba-token-swatch" value="<?php echo esc_attr( $c['value'] ); ?>">
					<span class="vba-token-var">--</span>
					<input type="text" class="vba-token-name" value="<?php echo esc_attr( $c['name'] ); ?>" placeholder="name">
					<input type="text" class="vba-token-val" value="<?php echo esc_attr( $c['value'] ); ?>">
					<button class="vba-mini vba-mini-del vba-token-del"><?php esc_html_e( 'Remove', 'velox' ); ?></button>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="vba-card">
		<div class="vba-card-h"><div><h2><?php esc_html_e( 'Spacing scale', 'velox' ); ?></h2><p><?php esc_html_e( 'Comma-separated pixel steps, e.g. 4, 8, 16, 24.', 'velox' ); ?></p></div></div>
		<div style="padding:0 20px 20px">
			<input type="text" class="vba-input-wide" id="vba-spacing" value="<?php echo esc_attr( implode( ', ', $tokens['spacing'] ) ); ?>">
		</div>
	</div>
</div>
<script>
( function () {
	var CFG = window.VELOX_BUILDER || {};
	var colors = document.getElementById( 'vba-colors' );
	function rowHtml( name, val ) {
		return '<div class="vba-token-row"><input type="color" class="vba-token-swatch" value="' + ( val || '#000000' ) + '">' +
			'<span class="vba-token-var">--</span><input type="text" class="vba-token-name" value="' + ( name || '' ) + '" placeholder="name">' +
			'<input type="text" class="vba-token-val" value="' + ( val || '' ) + '">' +
			'<button class="vba-mini vba-mini-del vba-token-del">Remove</button></div>';
	}
	document.getElementById( 'vba-add-color' ).addEventListener( 'click', function () {
		colors.insertAdjacentHTML( 'beforeend', rowHtml( '', '#2ab7f1' ) );
	} );
	colors.addEventListener( 'click', function ( e ) { var d = e.target.closest( '.vba-token-del' ); if ( d ) { d.closest( '.vba-token-row' ).remove(); } } );
	colors.addEventListener( 'input', function ( e ) {
		var row = e.target.closest( '.vba-token-row' ); if ( ! row ) { return; }
		if ( e.target.type === 'color' ) { row.querySelector( '.vba-token-val' ).value = e.target.value; }
		if ( e.target.classList.contains( 'vba-token-val' ) && /^#[0-9a-f]{3,8}$/i.test( e.target.value ) ) { row.querySelector( '.vba-token-swatch' ).value = e.target.value; }
	} );
	document.getElementById( 'vba-tokens-save' ).addEventListener( 'click', function () {
		var btn = this; btn.textContent = 'Saving…';
		var cols = [].map.call( colors.querySelectorAll( '.vba-token-row' ), function ( r ) {
			return { name: r.querySelector( '.vba-token-name' ).value.trim(), value: r.querySelector( '.vba-token-val' ).value.trim() };
		} ).filter( function ( c ) { return c.name && c.value; } );
		var spacing = document.getElementById( 'vba-spacing' ).value.split( ',' ).map( function ( s ) { return s.trim(); } ).filter( Boolean );
		var body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', 'builder_tokens_save' ); body.set( 'nonce', CFG.nonce || '' );
		body.set( 'tokens', JSON.stringify( { colors: cols, spacing: spacing } ) );
		fetch( CFG.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) { btn.textContent = res && res.success ? 'Saved' : 'Save failed'; setTimeout( function () { btn.textContent = 'Save changes'; }, 1500 ); } )
			.catch( function () { btn.textContent = 'Save failed'; } );
	} );
}() );
</script>
