<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox Builder — Fonts & icons: font families output on built pages. */
$fonts    = Velox_Builder::fonts();
$weights  = array( '100', '200', '300', '400', '500', '600', '700', '800', '900' );
$display  = Velox_Builder::font_display();
$displays = array(
	'swap'     => __( 'swap — show fallback text immediately (recommended)', 'velox' ),
	'optional' => __( 'optional — skip the font on slow connections', 'velox' ),
	'fallback' => __( 'fallback — brief block, then fallback', 'velox' ),
	'block'    => __( 'block — hide text until the font loads', 'velox' ),
	'auto'     => __( 'auto — leave it to the browser', 'velox' ),
);

function velox_font_row( $f, $weights ) {
	$f = wp_parse_args( (array) $f, array( 'name' => '', 'type' => 'google', 'url' => '', 'weights' => array( '400', '700' ), 'italic' => 0, 'preload' => array(), 'files' => array() ) );
	$sel  = (array) $f['weights'];
	$pre  = (array) $f['preload'];
	$name = $f['name'] ? $f['name'] : __( 'New font', 'velox' );
	?>
	<div class="vba-font" data-type="<?php echo esc_attr( $f['type'] ); ?>">
		<div class="vba-font-head">
			<span class="vba-font-eyebrow"><?php esc_html_e( 'Font family', 'velox' ); ?></span>
			<b class="vba-font-title"><?php echo esc_html( $name ); ?></b>
			<button class="vba-mini vba-mini-del vba-font-del"><?php esc_html_e( 'Remove', 'velox' ); ?></button>
		</div>

		<div class="vba-font-top">
			<label class="vba-font-field">
				<span class="vba-fl"><?php esc_html_e( 'Family name', 'velox' ); ?></span>
				<input type="text" class="vba-font-name" value="<?php echo esc_attr( $f['name'] ); ?>" placeholder="Inter">
			</label>
			<label class="vba-font-field">
				<span class="vba-fl"><?php esc_html_e( 'Source', 'velox' ); ?></span>
				<select class="vba-font-type">
					<option value="google" <?php selected( $f['type'], 'google' ); ?>><?php esc_html_e( 'Google Fonts', 'velox' ); ?></option>
					<option value="url" <?php selected( $f['type'], 'url' ); ?>><?php esc_html_e( 'CSS URL', 'velox' ); ?></option>
					<option value="local" <?php selected( $f['type'], 'local' ); ?>><?php esc_html_e( 'Local files (self-hosted)', 'velox' ); ?></option>
				</select>
			</label>
			<label class="vba-font-field vba-only-url">
				<span class="vba-fl"><?php esc_html_e( 'Stylesheet URL', 'velox' ); ?></span>
				<input type="text" class="vba-font-url" value="<?php echo esc_attr( $f['url'] ); ?>" placeholder="https://">
			</label>
		</div>

		<div class="vba-font-block">
			<span class="vba-fl"><?php esc_html_e( 'Weights to load', 'velox' ); ?> — <span class="vba-font-inline"><?php echo esc_html( $name ); ?></span></span>
			<div class="vba-weights">
				<?php foreach ( $weights as $w ) : ?>
					<label class="vba-w<?php echo in_array( $w, $sel, true ) ? ' on' : ''; ?>">
						<input type="checkbox" class="vba-w-load" value="<?php echo esc_attr( $w ); ?>" <?php checked( in_array( $w, $sel, true ) ); ?>><?php echo esc_html( $w ); ?>
					</label>
				<?php endforeach; ?>
				<label class="vba-w vba-w-ital<?php echo $f['italic'] ? ' on' : ''; ?>">
					<input type="checkbox" class="vba-font-italic" <?php checked( (bool) $f['italic'] ); ?>><?php esc_html_e( 'italic', 'velox' ); ?>
				</label>
			</div>
		</div>

		<div class="vba-font-block">
			<span class="vba-fl"><?php esc_html_e( 'Preload — only the weights above the fold', 'velox' ); ?></span>
			<div class="vba-weights vba-preloads">
				<?php foreach ( $weights as $w ) : ?>
					<label class="vba-w vba-w-pre<?php echo in_array( $w, $pre, true ) ? ' on' : ''; ?><?php echo in_array( $w, $sel, true ) ? '' : ' off'; ?>">
						<input type="checkbox" class="vba-w-preload" value="<?php echo esc_attr( $w ); ?>" <?php checked( in_array( $w, $pre, true ) ); ?>><?php echo esc_html( $w ); ?>
					</label>
				<?php endforeach; ?>
			</div>
			<p class="vba-hint"><?php esc_html_e( 'Preloading everything is the same as preloading nothing. Pick the one or two weights your first screen actually uses.', 'velox' ); ?></p>
		</div>

		<div class="vba-font-block vba-only-local">
			<span class="vba-fl"><?php esc_html_e( 'Font files — one per weight', 'velox' ); ?></span>
			<div class="vba-localfiles">
				<?php foreach ( $weights as $w ) : ?>
					<?php $has = $f['files'][ $w ] ?? ''; ?>
					<div class="vba-localrow<?php echo in_array( $w, $sel, true ) ? '' : ' off'; ?>" data-weight="<?php echo esc_attr( $w ); ?>">
						<span class="vba-localw"><?php echo esc_html( $w ); ?></span>
						<input type="text" class="vba-font-file" value="<?php echo esc_attr( $has ); ?>" placeholder=".woff2 URL">
						<button class="vba-mini vba-pickfont"><?php esc_html_e( 'Choose', 'velox' ); ?></button>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="vba-hint"><?php esc_html_e( 'Upload .woff2 files to the media library and pick them here. Velox writes the @font-face rules for you.', 'velox' ); ?></p>
		</div>
	</div>
	<?php
}
?>
<div class="vba-shell">
	<header class="vba-head">
		<div><h1><?php esc_html_e( 'Fonts &amp; icons', 'velox' ); ?></h1>
		<p><?php esc_html_e( 'Register font families, control exactly which weights load, and preload only what your first screen needs.', 'velox' ); ?></p></div>
		<div class="vba-head-actions">
			<button class="vba-btn vba-btn-primary" id="vba-fonts-save"><?php esc_html_e( 'Save changes', 'velox' ); ?></button>
		</div>
	</header>

	<div class="vba-card">
		<div class="vba-card-h">
			<div><h2><?php esc_html_e( 'While fonts load', 'velox' ); ?></h2>
			<p><?php esc_html_e( 'One choice for the whole site — it applies to every family below.', 'velox' ); ?></p></div>
			<select id="vba-font-display" style="max-width:340px">
				<?php foreach ( $displays as $k => $label ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $display, $k ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>

	<div class="vba-card">
		<div class="vba-card-h">
			<div><h2><?php esc_html_e( 'Font families', 'velox' ); ?></h2>
			<p><?php esc_html_e( 'Every extra weight is another file the visitor downloads, so tick only what you use.', 'velox' ); ?></p></div>
			<button class="vba-btn vba-btn-ghost vba-btn-sm" id="vba-add-font"><?php esc_html_e( 'Add font', 'velox' ); ?></button>
		</div>
		<div class="vba-fontlist" id="vba-fonts">
			<?php
			if ( empty( $fonts ) ) {
				velox_font_row( array(), $weights );
			} else {
				foreach ( $fonts as $f ) {
					velox_font_row( $f, $weights );
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

	/* Which blocks are relevant depends on the source, and the family name is
	   echoed into the section labels so it is never ambiguous which font you
	   are editing. */
	function syncRow( row ) {
		var type = row.querySelector( '.vba-font-type' ).value;
		row.setAttribute( 'data-type', type );
		var name = row.querySelector( '.vba-font-name' ).value.trim() || 'New font';
		row.querySelector( '.vba-font-title' ).textContent = name;
		[].forEach.call( row.querySelectorAll( '.vba-font-inline' ), function ( e ) { e.textContent = name; } );
		var loaded = [].filter.call( row.querySelectorAll( '.vba-w-load' ), function ( c ) { return c.checked; } )
			.map( function ( c ) { return c.value; } );
		[].forEach.call( row.querySelectorAll( '.vba-w-pre' ), function ( lab ) {
			var on = loaded.indexOf( lab.querySelector( 'input' ).value ) > -1;
			lab.classList.toggle( 'off', ! on );
			if ( ! on ) { lab.querySelector( 'input' ).checked = false; lab.classList.remove( 'on' ); }
		} );
		[].forEach.call( row.querySelectorAll( '.vba-localrow' ), function ( r ) {
			r.classList.toggle( 'off', loaded.indexOf( r.getAttribute( 'data-weight' ) ) < 0 );
		} );
	}
	function syncAll() { [].forEach.call( wrap.querySelectorAll( '.vba-font' ), syncRow ); }
	syncAll();

	document.getElementById( 'vba-add-font' ).addEventListener( 'click', function () {
		wrap.insertAdjacentHTML( 'beforeend', TPL );
		var row = wrap.lastElementChild;
		row.querySelector( '.vba-font-name' ).value = '';
		row.querySelector( '.vba-font-url' ).value = '';
		syncRow( row );
	} );
	wrap.addEventListener( 'click', function ( e ) {
		var d = e.target.closest( '.vba-font-del' );
		if ( d ) {
			if ( wrap.children.length > 1 ) { d.closest( '.vba-font' ).remove(); }
			else { d.closest( '.vba-font' ).querySelector( '.vba-font-name' ).value = ''; syncAll(); }
			return;
		}
		var pick = e.target.closest( '.vba-pickfont' );
		if ( pick ) {
			e.preventDefault();
			if ( ! window.wp || ! wp.media ) { alert( 'The media library is unavailable here.' ); return; }
			var frame = wp.media( { title:'Choose a font file', multiple:false, button:{ text:'Use this file' } } );
			frame.on( 'select', function () {
				var a = frame.state().get( 'selection' ).first().toJSON();
				pick.closest( '.vba-localrow' ).querySelector( '.vba-font-file' ).value = a.url;
			} );
			frame.open();
		}
	} );
	wrap.addEventListener( 'change', function ( e ) {
		var lab = e.target.closest( '.vba-w' );
		if ( lab ) { lab.classList.toggle( 'on', e.target.checked ); }
		var row = e.target.closest( '.vba-font' );
		if ( row ) { syncRow( row ); }
	} );
	wrap.addEventListener( 'input', function ( e ) {
		var row = e.target.closest( '.vba-font' );
		if ( row && e.target.classList.contains( 'vba-font-name' ) ) { syncRow( row ); }
	} );

	document.getElementById( 'vba-fonts-save' ).addEventListener( 'click', function () {
		var btn = this; btn.textContent = 'Saving…';
		var fonts = [].map.call( wrap.querySelectorAll( '.vba-font' ), function ( r ) {
			var pick = function ( sel ) {
				return [].filter.call( r.querySelectorAll( sel ), function ( c ) { return c.checked; } )
					.map( function ( c ) { return c.value; } );
			};
			var files = {};
			[].forEach.call( r.querySelectorAll( '.vba-localrow' ), function ( lr ) {
				var v = lr.querySelector( '.vba-font-file' ).value.trim();
				if ( v ) { files[ lr.getAttribute( 'data-weight' ) ] = v; }
			} );
			return {
				name: r.querySelector( '.vba-font-name' ).value.trim(),
				type: r.querySelector( '.vba-font-type' ).value,
				url: r.querySelector( '.vba-font-url' ).value.trim(),
				weights: pick( '.vba-w-load' ),
				italic: r.querySelector( '.vba-font-italic' ).checked ? 1 : 0,
				preload: pick( '.vba-w-preload' ),
				files: files
			};
		} ).filter( function ( f ) { return f.name; } );
		var body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', 'builder_fonts_save' ); body.set( 'nonce', CFG.nonce || '' );
		body.set( 'display', document.getElementById( 'vba-font-display' ).value );
		body.set( 'fonts', JSON.stringify( fonts ) );
		fetch( CFG.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) { btn.textContent = res && res.success ? 'Saved' : 'Save failed'; setTimeout( function () { btn.textContent = 'Save changes'; }, 1500 ); } )
			.catch( function () { btn.textContent = 'Save failed'; } );
	} );
}() );
</script>
