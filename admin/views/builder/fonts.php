<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox Builder — Fonts & icons: font families output on built pages. */
$fonts    = Velox_Builder::fonts();
$weights  = array( '100', '200', '300', '400', '500', '600', '700', '800', '900' );
$displays = array(
	'swap'     => __( 'swap — show fallback text immediately (recommended)', 'velox' ),
	'optional' => __( 'optional — skip the font on slow connections', 'velox' ),
	'fallback' => __( 'fallback — short block, then fallback', 'velox' ),
	'block'    => __( 'block — hide text until the font loads', 'velox' ),
	'auto'     => __( 'auto — leave it to the browser', 'velox' ),
);

/** One editable font row. */
function velox_font_row( $f, $weights, $displays ) {
	$f = wp_parse_args( (array) $f, array( 'name' => '', 'type' => 'google', 'url' => '', 'weights' => array( '400', '700' ), 'italic' => 0, 'display' => 'swap', 'preload' => 0 ) );
	?>
	<div class="vba-font">
		<div class="vba-font-top">
			<input type="text" class="vba-font-name" value="<?php echo esc_attr( $f['name'] ); ?>" placeholder="Inter">
			<select class="vba-font-type">
				<option value="google" <?php selected( $f['type'], 'google' ); ?>><?php esc_html_e( 'Google Fonts', 'velox' ); ?></option>
				<option value="url" <?php selected( $f['type'], 'url' ); ?>><?php esc_html_e( 'CSS URL', 'velox' ); ?></option>
			</select>
			<input type="text" class="vba-font-url" value="<?php echo esc_attr( $f['url'] ); ?>" placeholder="https://">
			<button class="vba-mini vba-mini-del vba-font-del"><?php esc_html_e( 'Remove', 'velox' ); ?></button>
		</div>
		<div class="vba-font-opts">
			<div class="vba-font-field">
				<span class="vba-fl"><?php esc_html_e( 'Weights to load', 'velox' ); ?></span>
				<div class="vba-weights">
					<?php foreach ( $weights as $w ) : ?>
						<label class="vba-w<?php echo in_array( $w, (array) $f['weights'], true ) ? ' on' : ''; ?>">
							<input type="checkbox" value="<?php echo esc_attr( $w ); ?>" <?php checked( in_array( $w, (array) $f['weights'], true ) ); ?>><?php echo esc_html( $w ); ?>
						</label>
					<?php endforeach; ?>
					<label class="vba-w vba-w-ital<?php echo $f['italic'] ? ' on' : ''; ?>">
						<input type="checkbox" class="vba-font-italic" <?php checked( (bool) $f['italic'] ); ?>><?php esc_html_e( 'italic', 'velox' ); ?>
					</label>
				</div>
			</div>
			<div class="vba-font-field vba-font-field-sm">
				<span class="vba-fl"><?php esc_html_e( 'While loading', 'velox' ); ?></span>
				<select class="vba-font-display">
					<?php foreach ( $displays as $k => $label ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f['display'], $k ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<label class="vba-font-pre">
				<input type="checkbox" class="vba-font-preload" <?php checked( (bool) $f['preload'] ); ?>>
				<span><?php esc_html_e( 'Preload', 'velox' ); ?></span>
			</label>
		</div>
	</div>
	<?php
}
?>
<div class="vba-shell">
	<header class="vba-head">
		<div><h1><?php esc_html_e( 'Fonts &amp; icons', 'velox' ); ?></h1>
		<p><?php esc_html_e( 'Register font families to use in the editor, and control exactly which weights load.', 'velox' ); ?></p></div>
		<div class="vba-head-actions">
			<button class="vba-btn vba-btn-primary" id="vba-fonts-save"><?php esc_html_e( 'Save changes', 'velox' ); ?></button>
		</div>
	</header>
	<div class="vba-card">
		<div class="vba-card-h">
			<div><h2><?php esc_html_e( 'Font families', 'velox' ); ?></h2><p><?php esc_html_e( 'These load on every built page. Every extra weight is another file the visitor downloads, so tick only what you use.', 'velox' ); ?></p></div>
			<button class="vba-btn vba-btn-ghost vba-btn-sm" id="vba-add-font"><?php esc_html_e( 'Add font', 'velox' ); ?></button>
		</div>
		<div class="vba-fontlist" id="vba-fonts">
			<?php
			if ( empty( $fonts ) ) {
				velox_font_row( array(), $weights, $displays );
			} else {
				foreach ( $fonts as $f ) {
					velox_font_row( $f, $weights, $displays );
				}
			}
			?>
		</div>
	</div>
</div>
<script>
( function () {
	var CFG = window.VELOX_BUILDER || {};
	var wrap = document.getElementById( 'vba-fonts' );
	var TPL = wrap.firstElementChild ? wrap.firstElementChild.outerHTML : '';

	document.getElementById( 'vba-add-font' ).addEventListener( 'click', function () {
		wrap.insertAdjacentHTML( 'beforeend', TPL );
		var row = wrap.lastElementChild;
		row.querySelector( '.vba-font-name' ).value = '';
		row.querySelector( '.vba-font-url' ).value = '';
	} );

	wrap.addEventListener( 'click', function ( e ) {
		var d = e.target.closest( '.vba-font-del' );
		if ( d ) {
			if ( wrap.children.length > 1 ) { d.closest( '.vba-font' ).remove(); }
			else { d.closest( '.vba-font' ).querySelector( '.vba-font-name' ).value = ''; }
		}
	} );
	// Keep the pill's look in step with its hidden checkbox.
	wrap.addEventListener( 'change', function ( e ) {
		var lab = e.target.closest( '.vba-w' );
		if ( lab ) { lab.classList.toggle( 'on', e.target.checked ); }
	} );

	document.getElementById( 'vba-fonts-save' ).addEventListener( 'click', function () {
		var btn = this; btn.textContent = 'Saving…';
		var fonts = [].map.call( wrap.querySelectorAll( '.vba-font' ), function ( r ) {
			var ws = [].filter.call( r.querySelectorAll( '.vba-weights input[type=checkbox]' ), function ( c ) {
				return c.checked && c.value;
			} ).map( function ( c ) { return c.value; } );
			return {
				name: r.querySelector( '.vba-font-name' ).value.trim(),
				type: r.querySelector( '.vba-font-type' ).value,
				url: r.querySelector( '.vba-font-url' ).value.trim(),
				weights: ws,
				italic: r.querySelector( '.vba-font-italic' ).checked ? 1 : 0,
				display: r.querySelector( '.vba-font-display' ).value,
				preload: r.querySelector( '.vba-font-preload' ).checked ? 1 : 0
			};
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
