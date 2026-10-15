<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox Builder — Global styles: the defaults every built page inherits. */
$g      = Velox_Builder::global_styles();
$tokens = Velox_Builder::tokens();
$fonts  = Velox_Builder::fonts();
$wopts  = array( '', '100', '200', '300', '400', '500', '600', '700', '800', '900' );
$sides  = array(
	'top'    => __( 'Top', 'velox' ),
	'right'  => __( 'Right', 'velox' ),
	'bottom' => __( 'Bottom', 'velox' ),
	'left'   => __( 'Left', 'velox' ),
);

// The shipped defaults, read by asking for the styles with nothing saved. The
// breakpoint fields show them as placeholders, and an empty one saves as that
// default — so "leave it empty" really means "use the default".
add_filter( 'pre_option_' . Velox_Builder::OPT_GLOBAL_STYLES, '__return_empty_array', 999 );
$gd = Velox_Builder::global_styles();
remove_filter( 'pre_option_' . Velox_Builder::OPT_GLOBAL_STYLES, '__return_empty_array', 999 );

// Colour tokens, so a var(--name) value can still show its real colour.
$tok_colors = array();
foreach ( (array) ( $tokens['colors'] ?? array() ) as $c ) {
	$tn = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) ( $c['name'] ?? '' ) ) );
	if ( $tn && ! empty( $c['value'] ) ) {
		$tok_colors[ $tn ] = (string) $c['value'];
	}
}

/**
 * One numeric field. $placeholder shows what an empty field means; $fallback,
 * when set, is what an empty field is saved as.
 */
function velox_gs_num( $group, $key, $label, $val, $unit = 'px', $placeholder = '', $fallback = '' ) {
	?>
	<label class="vba-gs-f">
		<span class="vba-fl"><?php echo esc_html( $label ); ?></span>
		<span class="vba-gs-in">
			<input type="text" data-gs="<?php echo esc_attr( $group . '.' . $key ); ?>" value="<?php echo esc_attr( $val ); ?>"<?php
				echo '' !== (string) $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
				echo '' !== (string) $fallback ? ' data-gs-fallback="' . esc_attr( $fallback ) . '"' : '';
			?>>
			<?php if ( $unit ) : ?><i><?php echo esc_html( $unit ); ?></i><?php endif; ?>
		</span>
	</label>
	<?php
}
function velox_gs_color( $group, $key, $label, $val ) {
	// The picker only understands #rrggbb. Anything else (empty, var(), rgba(),
	// a name) is resolved to its real colour in the browser; until then the
	// swatch shows "not set" rather than a misleading black.
	$hex = preg_match( '/^#[0-9a-f]{6}$/i', (string) $val ) ? strtolower( $val ) : '';
	?>
	<label class="vba-gs-f">
		<span class="vba-fl"><?php echo esc_html( $label ); ?></span>
		<span class="vba-gs-color<?php echo $hex ? '' : ' is-unset'; ?>">
			<input type="color" class="vba-gs-swatch" value="<?php echo esc_attr( $hex ? $hex : '#000000' ); ?>" aria-label="<?php echo esc_attr( $label ); ?>">
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
			<button type="button" class="vba-btn vba-btn-primary" id="vba-gs-save"><?php esc_html_e( 'Save changes', 'velox' ); ?></button>
		</div>
	</header>

	<div class="vba-card">
		<div class="vba-card-h"><div><h2><?php esc_html_e( 'Body text', 'velox' ); ?></h2>
		<p><?php esc_html_e( 'The base for all ordinary text on the page.', 'velox' ); ?></p></div></div>
		<div class="vba-gs-grid">
			<?php
			velox_gs_font( 'body', 'font', __( 'Font family', 'velox' ), $g['body']['font'], $fonts );
			velox_gs_num( 'body', 'size', __( 'Font size', 'velox' ), $g['body']['size'], 'px', __( 'inherit', 'velox' ) );
			velox_gs_weight( 'body', 'weight', $g['body']['weight'], $wopts );
			velox_gs_num( 'body', 'lineHeight', __( 'Line height', 'velox' ), $g['body']['lineHeight'], '', __( 'inherit', 'velox' ) );
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
					velox_gs_num( 'headings.' . $tag, 'size', __( 'Size', 'velox' ), $h['size'], 'px', __( 'inherit', 'velox' ) );
					velox_gs_weight( 'headings.' . $tag, 'weight', $h['weight'], $wopts );
					velox_gs_num( 'headings.' . $tag, 'lineHeight', __( 'Line height', 'velox' ), $h['lineHeight'], '', __( 'inherit', 'velox' ) );
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
			foreach ( array( 'page' => __( 'Page width', 'velox' ), 'tablet' => __( 'Tablet at', 'velox' ), 'landscape' => __( 'Landscape at', 'velox' ) ) as $wk => $wl ) {
				$wdef = (string) ( $gd['width'][ $wk ] ?? '' );
				velox_gs_num( 'width', $wk, $wl, $g['width'][ $wk ] ?? '', 'px', $wdef, $wdef );
			}
			?>
			<?php // Nothing reads the old "Portrait at" width; it is carried along unchanged so saving here never alters it. ?>
			<input type="hidden" data-gs="width.portrait" value="<?php echo esc_attr( $g['width']['portrait'] ?? '' ); ?>">
		</div>
		<p class="vba-hint" style="padding:0 18px 16px"><?php esc_html_e( 'Leave a width empty to use the default shown in the field. Changing a breakpoint rewrites the media queries in every page stylesheet. Republish a page if it looks stale.', 'velox' ); ?></p>
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
				<?php foreach ( $sides as $side => $side_label ) {
					velox_gs_num( 'sections', $side, $side_label, $g['sections'][ $side ] ?? '', 'px', __( 'inherit', 'velox' ) );
				} ?>
			</div>
		</div>
		<div class="vba-gs-row">
			<span class="vba-gs-tag"><?php esc_html_e( 'Columns', 'velox' ); ?></span>
			<div class="vba-gs-grid">
				<?php foreach ( $sides as $side => $side_label ) {
					velox_gs_num( 'columns', $side, $side_label, $g['columns'][ $side ] ?? '', 'px', __( 'inherit', 'velox' ) );
				} ?>
			</div>
		</div>
	</div>
</div>
<script>
( function () {
	var T = {
		saving: '<?php echo esc_js( __( 'Saving…', 'velox' ) ); ?>',
		saved: '<?php echo esc_js( __( 'Saved', 'velox' ) ); ?>',
		saveFailed: '<?php echo esc_js( __( 'Save failed', 'velox' ) ); ?>',
		saveChanges: '<?php echo esc_js( __( 'Save changes', 'velox' ) ); ?>',
		notSet: '<?php echo esc_js( __( 'Not set — inherits', 'velox' ) ); ?>'
	};
	var TOKENS = <?php echo wp_json_encode( (object) $tok_colors ); ?>;
	// The config is printed in the footer, after this script, so read it when used.
	function cfg() { return window.VELOX_BUILDER || {}; }

	/* Resolve any CSS colour the browser understands (hex, rgb[a], hsl[a], a
	   name, or var(--token)) to #rrggbb by letting it compute the colour on a
	   hidden element. The host's sentinel colour tells "unresolvable" apart. */
	var SENTINEL = 'rgb(1, 2, 3)';
	var host = document.createElement( 'div' );
	host.setAttribute( 'aria-hidden', 'true' );
	host.style.cssText = 'position:absolute;left:-9999px;top:0;width:0;height:0;overflow:hidden;color:' + SENTINEL;
	Object.keys( TOKENS ).forEach( function ( k ) { host.style.setProperty( '--' + k, TOKENS[ k ] ); } );
	var probe = document.createElement( 'span' );
	host.appendChild( probe );
	document.body.appendChild( host );
	function hex2( n ) { var h = Math.max( 0, Math.min( 255, Math.round( n ) ) ).toString( 16 ); return h.length < 2 ? '0' + h : h; }
	function resolveColor( v ) {
		v = ( v || '' ).trim();
		if ( ! v ) { return ''; }
		if ( /^#[0-9a-f]{6}$/i.test( v ) ) { return v.toLowerCase(); }
		probe.style.color = '';
		probe.style.color = v;
		if ( ! probe.style.color ) { return ''; } // not a colour at all
		var c = window.getComputedStyle( probe ).color;
		if ( c === SENTINEL ) { return ''; } // unknown variable, inherit, …
		var m = c.match( /rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:\s*[,\/]\s*([\d.]+)(%)?)?/ );
		if ( ! m ) { return ''; }
		if ( undefined !== m[4] && 0 === parseFloat( m[4] ) ) { return ''; } // fully transparent
		return '#' + hex2( +m[1] ) + hex2( +m[2] ) + hex2( +m[3] );
	}
	function syncSwatch( wrap ) {
		var text = wrap.querySelector( '[data-gs]' ), sw = wrap.querySelector( '.vba-gs-swatch' );
		var hex = resolveColor( text.value );
		wrap.classList.toggle( 'is-unset', ! hex );
		if ( hex ) { sw.value = hex; sw.title = text.value.trim(); } else { sw.title = T.notSet; }
	}
	[].forEach.call( document.querySelectorAll( '.vba-gs-color' ), syncSwatch );

	// Picking a colour writes it into the text field; typing in the text field
	// only moves the swatch. The text is never rewritten unless the picker is used.
	document.addEventListener( 'input', function ( e ) {
		var wrap = e.target.closest ? e.target.closest( '.vba-gs-color' ) : null;
		if ( ! wrap ) { return; }
		if ( e.target.classList.contains( 'vba-gs-swatch' ) ) {
			wrap.querySelector( '[data-gs]' ).value = e.target.value;
			wrap.classList.remove( 'is-unset' );
			e.target.title = e.target.value;
		} else if ( e.target.hasAttribute( 'data-gs' ) ) {
			syncSwatch( wrap );
		}
	} );
	document.getElementById( 'vba-gs-save' ).addEventListener( 'click', function () {
		var btn = this; btn.textContent = T.saving;
		var out = {};
		[].forEach.call( document.querySelectorAll( '[data-gs]' ), function ( el ) {
			var path = el.getAttribute( 'data-gs' ).split( '.' ), ref = out;
			for ( var i = 0; i < path.length - 1; i++ ) { ref[ path[ i ] ] = ref[ path[ i ] ] || {}; ref = ref[ path[ i ] ]; }
			var v = el.value.trim();
			if ( '' === v && el.hasAttribute( 'data-gs-fallback' ) ) { v = el.getAttribute( 'data-gs-fallback' ); }
			ref[ path[ path.length - 1 ] ] = v;
		} );
		var C = cfg();
		var body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', 'builder_global_styles_save' ); body.set( 'nonce', C.nonce || '' );
		body.set( 'styles', JSON.stringify( out ) );
		fetch( C.ajaxurl || window.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) { btn.textContent = res && res.success ? T.saved : T.saveFailed; setTimeout( function () { btn.textContent = T.saveChanges; }, 1500 ); } )
			.catch( function () { btn.textContent = T.saveFailed; setTimeout( function () { btn.textContent = T.saveChanges; }, 2500 ); } );
	} );
}() );
</script>
