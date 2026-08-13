<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox Builder — Global styles: the defaults every built page inherits. */
$g      = Velox_Builder::global_styles();
$tokens = Velox_Builder::tokens();
$fonts  = Velox_Builder::fonts();
$wopts  = array( '', '100', '200', '300', '400', '500', '600', '700', '800', '900' );

function velox_gs_num( $group, $key, $label, $val, $unit = 'px' ) {
	?>
	<label class="vba-gs-f">
		<span class="vba-fl"><?php echo esc_html( $label ); ?></span>
		<span class="vba-gs-in">
			<input type="text" data-gs="<?php echo esc_attr( $group . '.' . $key ); ?>" value="<?php echo esc_attr( $val ); ?>">
			<?php if ( $unit ) : ?><i><?php echo esc_html( $unit ); ?></i><?php endif; ?>
		</span>
	</label>
	<?php
}
function velox_gs_color( $group, $key, $label, $val ) {
	?>
	<label class="vba-gs-f">
		<span class="vba-fl"><?php echo esc_html( $label ); ?></span>
		<span class="vba-gs-color">
			<input type="color" class="vba-gs-swatch" value="<?php echo esc_attr( $val ? $val : '#000000' ); ?>">
			<input type="text" data-gs="<?php echo esc_attr( $group . '.' . $key ); ?>" value="<?php echo esc_attr( $val ); ?>" placeholder="<?php esc_attr_e( 'inherit', 'velox' ); ?>">
		</span>
	</label>
	<?php
}
function velox_gs_weight( $group, $key, $val, $wopts ) {
	?>
	<label class="vba-gs-f">
		<span class="vba-fl"><?php esc_html_e( 'Weight', 'velox' ); ?></span>
		<select data-gs="<?php echo esc_attr( $group . '.' . $key ); ?>">
			<?php foreach ( $wopts as $w ) : ?>
				<option value="<?php echo esc_attr( $w ); ?>" <?php selected( (string) $val, (string) $w ); ?>><?php echo esc_html( $w ? $w : __( 'inherit', 'velox' ) ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<?php
}
function velox_gs_font( $group, $key, $label, $val, $fonts ) {
	?>
	<label class="vba-gs-f">
		<span class="vba-fl"><?php echo esc_html( $label ); ?></span>
		<select data-gs="<?php echo esc_attr( $group . '.' . $key ); ?>">
			<option value=""><?php esc_html_e( 'Theme default', 'velox' ); ?></option>
			<?php foreach ( $fonts as $f ) : ?>
				<option value="<?php echo esc_attr( $f['name'] ); ?>" <?php selected( $val, $f['name'] ); ?>><?php echo esc_html( $f['name'] ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<?php
}
?>
<div class="vba-shell">
	<header class="vba-head">
		<div><h1><?php esc_html_e( 'Global styles', 'velox' ); ?></h1>
		<p><?php esc_html_e( 'The defaults every built page inherits. Leave a field empty to inherit instead of forcing a value.', 'velox' ); ?></p></div>
		<div class="vba-head-actions">
			<button class="vba-btn vba-btn-primary" id="vba-gs-save"><?php esc_html_e( 'Save changes', 'velox' ); ?></button>
		</div>
	</header>

	<div class="vba-card">
		<div class="vba-card-h"><div><h2><?php esc_html_e( 'Body text', 'velox' ); ?></h2>
		<p><?php esc_html_e( 'The base for all ordinary text on the page.', 'velox' ); ?></p></div></div>
		<div class="vba-gs-grid">
			<?php
			velox_gs_font( 'body', 'font', __( 'Font family', 'velox' ), $g['body']['font'], $fonts );
			velox_gs_num( 'body', 'size', __( 'Font size', 'velox' ), $g['body']['size'] );
			velox_gs_weight( 'body', 'weight', $g['body']['weight'], $wopts );
			velox_gs_num( 'body', 'lineHeight', __( 'Line height', 'velox' ), $g['body']['lineHeight'], '' );
			velox_gs_color( 'body', 'color', __( 'Colour', 'velox' ), $g['body']['color'] );
			?>
		</div>
	</div>

	<div class="vba-card">
		<div class="vba-card-h"><div><h2><?php esc_html_e( 'Headings', 'velox' ); ?></h2>
		<p><?php esc_html_e( 'H1 through H6. An empty size or colour leaves that heading alone.', 'velox' ); ?></p></div></div>
		<div class="vba-gs-grid"><?php velox_gs_font( 'headings', 'font', __( 'Heading font', 'velox' ), $g['headings']['font'], $fonts ); ?></div>
		<?php foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $tag ) : $h = $g['headings'][ $tag ]; ?>
			<div class="vba-gs-row">
				<span class="vba-gs-tag"><?php echo esc_html( strtoupper( $tag ) ); ?></span>
				<div class="vba-gs-grid">
					<?php
					velox_gs_num( 'headings.' . $tag, 'size', __( 'Size', 'velox' ), $h['size'] );
					velox_gs_weight( 'headings.' . $tag, 'weight', $h['weight'], $wopts );
					velox_gs_num( 'headings.' . $tag, 'lineHeight', __( 'Line height', 'velox' ), $h['lineHeight'], '' );
					velox_gs_color( 'headings.' . $tag, 'color', __( 'Colour', 'velox' ), $h['color'] );
					?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="vba-card">
		<div class="vba-card-h"><div><h2><?php esc_html_e( 'Links', 'velox' ); ?></h2></div></div>
		<div class="vba-gs-grid">
			<?php
			velox_gs_color( 'links', 'color', __( 'Colour', 'velox' ), $g['links']['color'] );
			velox_gs_color( 'links', 'hover', __( 'Hover colour', 'velox' ), $g['links']['hover'] );
			?>
			<label class="vba-gs-f">
				<span class="vba-fl"><?php esc_html_e( 'Underline', 'velox' ); ?></span>
				<select data-gs="links.decoration">
					<option value="none" <?php selected( $g['links']['decoration'], 'none' ); ?>><?php esc_html_e( 'No underline', 'velox' ); ?></option>
					<option value="underline" <?php selected( $g['links']['decoration'], 'underline' ); ?>><?php esc_html_e( 'Underlined', 'velox' ); ?></option>
				</select>
			</label>
			<?php velox_gs_weight( 'links', 'weight', $g['links']['weight'], $wopts ); ?>
		</div>
	</div>

	<div class="vba-card">
		<div class="vba-card-h"><div><h2><?php esc_html_e( 'Width & breakpoints', 'velox' ); ?></h2>
		<p><?php esc_html_e( 'Breakpoints apply to the editor preview and the front end together.', 'velox' ); ?></p></div></div>
		<div class="vba-gs-grid">
			<?php
			velox_gs_num( 'width', 'page', __( 'Page width', 'velox' ), $g['width']['page'] );
			velox_gs_num( 'width', 'tablet', __( 'Tablet at', 'velox' ), $g['width']['tablet'] );
			velox_gs_num( 'width', 'landscape', __( 'Landscape at', 'velox' ), $g['width']['landscape'] );
			velox_gs_num( 'width', 'portrait', __( 'Portrait at', 'velox' ), $g['width']['portrait'] );
			?>
		</div>
		<p class="vba-hint" style="padding:0 18px 16px"><?php esc_html_e( 'Changing a breakpoint rewrites the media queries in every page stylesheet. Republish a page if it looks stale.', 'velox' ); ?></p>
	</div>

	<div class="vba-card">
		<div class="vba-card-h"><div><h2><?php esc_html_e( 'Animate on scroll', 'velox' ); ?></h2>
		<p><?php esc_html_e( 'The default for every element. Any element can override or opt out in the builder.', 'velox' ); ?></p></div></div>
		<div class="vba-gs-grid">
			<label class="vba-gs-f">
				<span class="vba-fl"><?php esc_html_e( 'Animation', 'velox' ); ?></span>
				<select data-gs="aos.type">
					<option value=""><?php esc_html_e( 'None', 'velox' ); ?></option>
					<?php foreach ( Velox_Builder::aos_types() as $k => $label ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $g['aos']['type'], $k ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<?php velox_gs_num( 'aos', 'duration', __( 'Duration', 'velox' ), $g['aos']['duration'], 'ms' ); ?>
			<label class="vba-gs-f">
				<span class="vba-fl"><?php esc_html_e( 'Easing', 'velox' ); ?></span>
				<select data-gs="aos.easing">
					<?php foreach ( array( 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'linear' ) as $e ) : ?>
						<option value="<?php echo esc_attr( $e ); ?>" <?php selected( $g['aos']['easing'], $e ); ?>><?php echo esc_html( $e ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<?php
			velox_gs_num( 'aos', 'offset', __( 'Trigger offset', 'velox' ), $g['aos']['offset'] );
			velox_gs_num( 'aos', 'delay', __( 'Delay', 'velox' ), $g['aos']['delay'], 'ms' );
			?>
			<label class="vba-gs-f">
				<span class="vba-fl"><?php esc_html_e( 'Play', 'velox' ); ?></span>
				<select data-gs="aos.once">
					<option value="1" <?php selected( $g['aos']['once'], '1' ); ?>><?php esc_html_e( 'Once', 'velox' ); ?></option>
					<option value="" <?php selected( $g['aos']['once'], '' ); ?>><?php esc_html_e( 'Every time it scrolls in', 'velox' ); ?></option>
				</select>
			</label>
			<label class="vba-gs-f">
				<span class="vba-fl"><?php esc_html_e( 'Turn off on', 'velox' ); ?></span>
				<select data-gs="aos.disable">
					<option value="" <?php selected( $g['aos']['disable'], '' ); ?>><?php esc_html_e( 'Nothing — always animate', 'velox' ); ?></option>
					<option value="mobile" <?php selected( $g['aos']['disable'], 'mobile' ); ?>><?php esc_html_e( 'Mobile', 'velox' ); ?></option>
					<option value="tablet" <?php selected( $g['aos']['disable'], 'tablet' ); ?>><?php esc_html_e( 'Tablet and below', 'velox' ); ?></option>
				</select>
			</label>
		</div>
		<p class="vba-hint" style="padding:0 18px 16px"><?php esc_html_e( 'Visitors who ask their system for reduced motion never see animations, whatever is set here.', 'velox' ); ?></p>
	</div>

	<div class="vba-card">
		<div class="vba-card-h"><div><h2><?php esc_html_e( 'Sections & columns', 'velox' ); ?></h2>
		<p><?php esc_html_e( 'Default padding for new sections and column blocks.', 'velox' ); ?></p></div></div>
		<div class="vba-gs-row">
			<span class="vba-gs-tag"><?php esc_html_e( 'Section', 'velox' ); ?></span>
			<div class="vba-gs-grid">
				<?php foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
					velox_gs_num( 'sections', $side, ucfirst( $side ), $g['sections'][ $side ] );
				} ?>
			</div>
		</div>
		<div class="vba-gs-row">
			<span class="vba-gs-tag"><?php esc_html_e( 'Columns', 'velox' ); ?></span>
			<div class="vba-gs-grid">
				<?php foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
					velox_gs_num( 'columns', $side, ucfirst( $side ), $g['columns'][ $side ] );
				} ?>
			</div>
		</div>
	</div>
</div>
<script>
( function () {
	var CFG = window.VELOX_BUILDER || {};
	// Colour swatch and hex field stay in step with each other.
	document.addEventListener( 'input', function ( e ) {
		if ( e.target.classList.contains( 'vba-gs-swatch' ) ) {
			e.target.parentElement.querySelector( '[data-gs]' ).value = e.target.value;
		} else if ( e.target.hasAttribute( 'data-gs' ) && e.target.parentElement.classList.contains( 'vba-gs-color' ) ) {
			var sw = e.target.parentElement.querySelector( '.vba-gs-swatch' );
			if ( /^#[0-9a-f]{6}$/i.test( e.target.value ) ) { sw.value = e.target.value; }
		}
	} );
	document.getElementById( 'vba-gs-save' ).addEventListener( 'click', function () {
		var btn = this; btn.textContent = 'Saving…';
		var out = {};
		[].forEach.call( document.querySelectorAll( '[data-gs]' ), function ( el ) {
			var path = el.getAttribute( 'data-gs' ).split( '.' ), ref = out;
			for ( var i = 0; i < path.length - 1; i++ ) { ref[ path[ i ] ] = ref[ path[ i ] ] || {}; ref = ref[ path[ i ] ]; }
			ref[ path[ path.length - 1 ] ] = el.value.trim();
		} );
		var body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', 'builder_global_styles_save' ); body.set( 'nonce', CFG.nonce || '' );
		body.set( 'styles', JSON.stringify( out ) );
		fetch( CFG.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) { btn.textContent = res && res.success ? 'Saved' : 'Save failed'; setTimeout( function () { btn.textContent = 'Save changes'; }, 1500 ); } )
			.catch( function () { btn.textContent = 'Save failed'; } );
	} );
}() );
</script>
