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
if ( ! function_exists( 'velox_gs_num' ) ) {
function velox_gs_num( $group, $key, $label, $val, $unit = 'px', $placeholder = '', $fallback = '' ) {
	?>
	<label class="vba-gs-f">
		<span class="vba-fl"><?php echo esc_html( $label ); ?></span>
		<span class="vba-gs-in">
			<input type="text" data-gs="<?php echo esc_attr( $group . '.' . $key ); ?>" value="<?php echo esc_attr( $val ); ?>"<?php
				echo '' !== (string) $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : '';
				echo '' !== (string) $fallback ? ' data-gs-fallback="' . esc_attr( $fallback ) . '"' : '';
			?>>
			<?php if ( $unit ) : ?><i<?php echo preg_match( '/[a-z%(]/i', (string) $val ) ? ' hidden' : ''; ?>><?php echo esc_html( $unit ); ?></i><?php endif; ?>
		</span>
	</label>
	<?php
}
}
if ( ! function_exists( 'velox_gs_color' ) ) {
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
}
if ( ! function_exists( 'velox_gs_weight' ) ) {
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
}
if ( ! function_exists( 'velox_gs_font' ) ) {
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

	<?php
	// Design tokens: colours, fluid type and spacing scales, custom tokens.
	$tk  = Velox_Builder::tokens();
	$tsc = $tk['scales'];
	$ratios = array(
		'1.067' => __( 'Minor second · 1.067', 'velox' ), '1.125' => __( 'Major second · 1.125', 'velox' ), '1.2' => __( 'Minor third · 1.2', 'velox' ),
		'1.25' => __( 'Major third · 1.25', 'velox' ), '1.333' => __( 'Perfect fourth · 1.333', 'velox' ), '1.414' => __( 'Augmented fourth · 1.414', 'velox' ),
		'1.5' => __( 'Perfect fifth · 1.5', 'velox' ), '1.618' => __( 'Golden ratio · 1.618', 'velox' ),
	);
	$num_in = function ( $path, $label, $val, $unit = 'px', $step = '1' ) {
		echo '<label class="vba-gs-f"><span class="vba-fl">' . esc_html( $label ) . '</span><span class="vba-gs-in"><input type="number" step="' . esc_attr( $step ) . '" data-tk="' . esc_attr( $path ) . '" value="' . esc_attr( $val ) . '">' . ( $unit ? '<i>' . esc_html( $unit ) . '</i>' : '' ) . '</span></label>';
	};
	$ratio_in = function ( $path, $label, $val ) use ( $ratios ) {
		$val = rtrim( rtrim( number_format( (float) $val, 3, '.', '' ), '0' ), '.' );
		echo '<label class="vba-gs-f"><span class="vba-fl">' . esc_html( $label ) . '</span><select data-tk="' . esc_attr( $path ) . '">';
		$found = false;
		foreach ( $ratios as $rv => $rl ) {
			$sel = ( (string) $rv === (string) $val ); $found = $found || $sel;
			echo '<option value="' . esc_attr( $rv ) . '"' . selected( $sel, true, false ) . '>' . esc_html( $rl ) . '</option>';
		}
		if ( ! $found ) { echo '<option value="' . esc_attr( $val ) . '" selected>' . esc_html( $val ) . '</option>'; }
		echo '</select></label>';
	};
	?>
	<div class="vba-card vba-tokens" id="vba-tokens">
		<div class="vba-card-h"><div><h2><?php esc_html_e( 'Design tokens', 'velox' ); ?></h2>
		<p><?php esc_html_e( 'Named values every page can use as var(--name). In the editor, each size field lists the tokens that fit it in its unit menu. Change a token here and every place that uses it follows.', 'velox' ); ?></p></div></div>

		<div class="vba-tk-sec">
			<div class="vba-tk-h"><b><?php esc_html_e( 'Colours', 'velox' ); ?></b><button type="button" class="vba-mini" data-tk-add="colors"><?php esc_html_e( 'Add colour', 'velox' ); ?></button></div>
			<div class="vba-tk-list" id="vba-tk-colors"></div>
		</div>

		<div class="vba-tk-sec">
			<div class="vba-tk-h"><b><?php esc_html_e( 'Fluid type scale', 'velox' ); ?></b>
				<label class="vba-switch" title="<?php esc_attr_e( 'Output the --text-* tokens', 'velox' ); ?>"><input type="checkbox" data-tk="type.on" <?php checked( ! empty( $tsc['type']['on'] ) ); ?>><span></span></label></div>
			<p class="vba-hint vba-tk-say"><?php esc_html_e( 'Font sizes that grow smoothly from the small to the large screen: no breakpoints needed. Each step is the step below times the ratio.', 'velox' ); ?></p>
			<div class="vba-gs-grid vba-tk-grid">
				<?php
				$num_in( 'type.minVw', __( 'Small screen', 'velox' ), $tsc['type']['minVw'] );
				$num_in( 'type.maxVw', __( 'Large screen', 'velox' ), $tsc['type']['maxVw'] );
				$num_in( 'type.minBase', __( 'Base size (small)', 'velox' ), $tsc['type']['minBase'], 'px', '0.5' );
				$num_in( 'type.maxBase', __( 'Base size (large)', 'velox' ), $tsc['type']['maxBase'], 'px', '0.5' );
				$ratio_in( 'type.minRatio', __( 'Ratio (small)', 'velox' ), $tsc['type']['minRatio'] );
				$ratio_in( 'type.maxRatio', __( 'Ratio (large)', 'velox' ), $tsc['type']['maxRatio'] );
				$num_in( 'type.down', __( 'Steps smaller', 'velox' ), $tsc['type']['down'], '', '1' );
				$num_in( 'type.up', __( 'Steps larger', 'velox' ), $tsc['type']['up'], '', '1' );
				?>
			</div>
			<div class="vba-tk-preview" id="vba-tk-type"></div>
		</div>

		<div class="vba-tk-sec">
			<div class="vba-tk-h"><b><?php esc_html_e( 'Fluid spacing scale', 'velox' ); ?></b>
				<label class="vba-switch" title="<?php esc_attr_e( 'Output the --space-* tokens', 'velox' ); ?>"><input type="checkbox" data-tk="space.on" <?php checked( ! empty( $tsc['space']['on'] ) ); ?>><span></span></label></div>
			<p class="vba-hint vba-tk-say"><?php esc_html_e( 'Padding, margins and gaps that grow with the screen. From 3xs (a quarter of the base) to 3xl (six times the base).', 'velox' ); ?></p>
			<div class="vba-gs-grid vba-tk-grid">
				<?php
				$num_in( 'space.minVw', __( 'Small screen', 'velox' ), $tsc['space']['minVw'] );
				$num_in( 'space.maxVw', __( 'Large screen', 'velox' ), $tsc['space']['maxVw'] );
				$num_in( 'space.minBase', __( 'Base (small)', 'velox' ), $tsc['space']['minBase'], 'px', '0.5' );
				$num_in( 'space.maxBase', __( 'Base (large)', 'velox' ), $tsc['space']['maxBase'], 'px', '0.5' );
				?>
			</div>
			<div class="vba-tk-preview" id="vba-tk-space"></div>
		</div>

		<div class="vba-tk-sec">
			<div class="vba-tk-h"><b><?php esc_html_e( 'Custom tokens', 'velox' ); ?></b><button type="button" class="vba-mini" data-tk-add="custom"><?php esc_html_e( 'Add token', 'velox' ); ?></button></div>
			<p class="vba-hint vba-tk-say"><?php esc_html_e( 'Corner radii, shadows, widths or anything else. The group decides which fields offer the token: radius for corners, size for widths and heights, space for spacing, type for font sizes.', 'velox' ); ?></p>
			<div class="vba-tk-list" id="vba-tk-custom"></div>
		</div>
	</div>

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

	<div class="vba-card vba-dh" id="vba-dh">
		<div class="vba-card-h"><div><h2><?php esc_html_e( 'Design history', 'velox' ); ?></h2>
		<p><?php esc_html_e( 'Every change to classes, design tokens and global styles is kept, from any screen. Restore an earlier version if something went wrong; what is there now is kept too.', 'velox' ); ?></p></div></div>
		<div class="vba-dh-list" id="vba-dh-list"><p class="vba-dh-empty"><?php esc_html_e( 'Loading…', 'velox' ); ?></p></div>
	</div>
</div>
<script>
( function () {
	var T = <?php echo wp_json_encode( array(
		'none'    => __( 'No changes kept yet. They appear here from the next change on.', 'velox' ),
		'current' => __( 'Current', 'velox' ),
		'restore' => __( 'Restore', 'velox' ),
		'wasRestore' => __( 'Restored an earlier version', 'velox' ),
		'restored'=> __( 'Restored', 'velox' ),
		'ask'     => __( 'Restore this version of classes, design tokens and global styles? What is there now is kept as a version too.', 'velox' ),
		'title'   => __( 'Restore design version', 'velox' ),
		'failed'  => __( 'Something went wrong. Try again.', 'velox' ),
	) ); ?>;
	function cfg() { return window.VELOX_BUILDER || {}; }
	function esc( s ) { var d = document.createElement( 'div' ); d.textContent = String( s == null ? '' : s ); return d.innerHTML; }
	function api( action, fields ) {
		var C = cfg(), body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', action ); body.set( 'nonce', C.nonce || '' );
		Object.keys( fields || {} ).forEach( function ( k ) { body.set( k, fields[ k ] ); } );
		return fetch( C.ajaxurl || window.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) { if ( ! res || ! res.success ) { throw new Error( ( res && res.data && res.data.message ) || T.failed ); } return res.data; } );
	}
	var list = document.getElementById( 'vba-dh-list' );
	function load() {
		api( 'builder_design_versions' ).then( function ( d ) {
			if ( ! d.versions.length ) { list.innerHTML = '<p class="vba-dh-empty">' + esc( T.none ) + '</p>'; return; }
			list.innerHTML = d.versions.map( function ( v ) {
				return '<div class="vba-dh-row' + ( v.current ? ' is-current' : '' ) + '"><div class="vba-dh-tx"><b>' + esc( 'restore' === v.kind ? T.wasRestore : v.what.join( ' · ' ) || '—' ) + '</b><span>' + esc( v.when + ( v.user ? ' · ' + v.user : '' ) ) + '</span></div>' +
					( v.current ? '<span class="vba-dh-cur">' + esc( T.current ) + '</span>' : '<button type="button" class="vba-mini" data-dh-restore="' + v.id + '">' + esc( T.restore ) + '</button>' ) + '</div>';
			} ).join( '' );
		} ).catch( function ( e ) { list.innerHTML = '<p class="vba-dh-empty">' + esc( e.message ) + '</p>'; } );
	}
	list.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '[data-dh-restore]' ); if ( ! b ) { return; }
		var go = function () {
			b.disabled = true;
			api( 'builder_design_restore', { rev:b.getAttribute( 'data-dh-restore' ) } ).then( function () { b.textContent = T.restored; setTimeout( function () { location.reload(); }, 600 ); } )
				.catch( function ( err ) { b.disabled = false; window.vbaDialog ? vbaDialog.alert( { title:T.title, text:err.message } ) : alert( err.message ); } );
		};
		if ( window.vbaDialog ) { vbaDialog.confirm( { title:T.title, text:T.ask, ok:T.restore }, go ); } else if ( confirm( T.ask ) ) { go(); }
	} );
	// The config (nonce) is printed in the footer, after this script.
	if ( 'complete' === document.readyState ) { load(); } else { window.addEventListener( 'load', load ); }
}() );
</script>
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

	/* ---------- design tokens ---------- */
	var TK = <?php echo wp_json_encode( array( 'colors' => array_values( (array) ( $tk['colors'] ?? array() ) ), 'spacing' => array_values( (array) ( $tk['spacing'] ?? array() ) ), 'scales' => $tsc, 'custom' => $tk['custom'] ) ); ?>;
	var TYPE_STEPS = { '-2':'xs', '-1':'sm', '0':'base', '1':'lg', '2':'xl', '3':'2xl', '4':'3xl', '5':'4xl', '6':'5xl', '7':'6xl' };
	var SPACE_STEPS = [ [ '3xs', .25 ], [ '2xs', .5 ], [ 'xs', .75 ], [ 's', 1 ], [ 'm', 1.5 ], [ 'l', 2 ], [ 'xl', 3 ], [ '2xl', 4 ], [ '3xl', 6 ] ];
	var GROUPS = { size:'<?php echo esc_js( __( 'Size', 'velox' ) ); ?>', space:'<?php echo esc_js( __( 'Space', 'velox' ) ); ?>', type:'<?php echo esc_js( __( 'Type', 'velox' ) ); ?>', radius:'<?php echo esc_js( __( 'Radius', 'velox' ) ); ?>', shadow:'<?php echo esc_js( __( 'Shadow', 'velox' ) ); ?>', other:'<?php echo esc_js( __( 'Other', 'velox' ) ); ?>' };
	var TT = { name:'<?php echo esc_js( __( 'name', 'velox' ) ); ?>', value:'<?php echo esc_js( __( 'value', 'velox' ) ); ?>', remove:'<?php echo esc_js( __( 'Remove', 'velox' ) ); ?>', copied:'<?php echo esc_js( __( 'Copied', 'velox' ) ); ?>', copy:'<?php echo esc_js( __( 'Copy var() to the clipboard', 'velox' ) ); ?>', sample:'<?php echo esc_js( __( 'The quick brown fox', 'velox' ) ); ?>', none:'<?php echo esc_js( __( 'None yet.', 'velox' ) ); ?>', off:'<?php echo esc_js( __( 'Off: no --text-* or --space-* tokens are written.', 'velox' ) ); ?>' };
	function r4( v ) { return String( Math.round( v * 10000 ) / 10000 ); }
	/* Same formula as Velox_Builder::fluid() on the server. */
	function fluid( min, max, minVw, maxVw ) {
		if ( Math.abs( max - min ) < 0.01 ) { return r4( min / 16 ) + 'rem'; }
		var slope = ( max - min ) / ( maxVw - minVw ), ic = min - slope * minVw;
		return 'clamp(' + r4( Math.min( min, max ) / 16 ) + 'rem, ' + r4( slope * 100 ) + 'vw ' + ( ic < 0 ? '- ' : '+ ' ) + r4( Math.abs( ic ) / 16 ) + 'rem, ' + r4( Math.max( min, max ) / 16 ) + 'rem)';
	}
	function esc( v ) { return String( v == null ? '' : v ).replace( /[&<>"]/g, function ( c ) { return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;' }[ c ]; } ); }
	function px( v ) { return ( Math.round( v * 10 ) / 10 ) + 'px'; }
	function nameBtn( n ) { return '<button type="button" class="vba-tk-name" data-tk-copy="var(--' + esc( n ) + ')" title="' + esc( TT.copy ) + '">--' + esc( n ) + '</button>'; }
	function renderType() {
		var sc = TK.scales.type, box = document.getElementById( 'vba-tk-type' ); if ( ! box ) { return; }
		if ( ! sc.on ) { box.innerHTML = '<p class="vba-hint">' + esc( TT.off ) + '</p>'; return; }
		var rows = '';
		for ( var st = +sc.up; st >= -sc.down; st-- ) {
			var lab = TYPE_STEPS[ String( st ) ]; if ( ! lab ) { continue; }
			var mn = sc.minBase * Math.pow( sc.minRatio, st ), mx = sc.maxBase * Math.pow( sc.maxRatio, st );
			rows += '<div class="vba-tk-row">' + nameBtn( 'text-' + lab ) + '<span class="vba-tk-num">' + px( mn ) + '</span><span class="vba-tk-num">' + px( mx ) + '</span>' +
				'<span class="vba-tk-sample" style="font-size:' + esc( fluid( mn, mx, sc.minVw, sc.maxVw ) ) + '">' + esc( TT.sample ) + '</span></div>';
		}
		box.innerHTML = '<div class="vba-tk-thead"><span>Token</span><span>' + esc( sc.minVw ) + 'px</span><span>' + esc( sc.maxVw ) + 'px</span><span></span></div>' + rows;
	}
	function renderSpace() {
		var sc = TK.scales.space, box = document.getElementById( 'vba-tk-space' ); if ( ! box ) { return; }
		if ( ! sc.on ) { box.innerHTML = '<p class="vba-hint">' + esc( TT.off ) + '</p>'; return; }
		box.innerHTML = '<div class="vba-tk-thead"><span>Token</span><span>' + esc( sc.minVw ) + 'px</span><span>' + esc( sc.maxVw ) + 'px</span><span></span></div>' + SPACE_STEPS.map( function ( s2 ) {
			var mn = sc.minBase * s2[ 1 ], mx = sc.maxBase * s2[ 1 ];
			return '<div class="vba-tk-row">' + nameBtn( 'space-' + s2[ 0 ] ) + '<span class="vba-tk-num">' + px( mn ) + '</span><span class="vba-tk-num">' + px( mx ) + '</span>' +
				'<span class="vba-tk-bar"><i style="width:' + esc( fluid( mn, mx, sc.minVw, sc.maxVw ) ) + '"></i></span></div>';
		} ).join( '' );
	}
	function renderColors() {
		var box = document.getElementById( 'vba-tk-colors' ); if ( ! box ) { return; }
		box.innerHTML = TK.colors.length ? TK.colors.map( function ( c, i ) {
			var hex = /^#[0-9a-f]{6}$/i.test( c.value ) ? c.value : '#000000';
			return '<div class="vba-tk-item"><input type="color" class="vba-tk-sw" data-tk-color="' + i + '" value="' + esc( hex ) + '" aria-label="' + esc( c.name ) + '">' +
				'<input type="text" class="vba-tk-n" data-tk-cname="' + i + '" value="' + esc( c.name ) + '" placeholder="' + esc( TT.name ) + '" spellcheck="false">' +
				'<input type="text" class="vba-tk-v" data-tk-cval="' + i + '" value="' + esc( c.value ) + '" placeholder="#2ab7f1" spellcheck="false">' +
				'<button type="button" class="vba-mini vba-mini-del" data-tk-del="colors:' + i + '" aria-label="' + esc( TT.remove ) + '">×</button></div>';
		} ).join( '' ) : '<p class="vba-hint">' + esc( TT.none ) + '</p>';
	}
	function renderCustom() {
		var box = document.getElementById( 'vba-tk-custom' ); if ( ! box ) { return; }
		box.innerHTML = TK.custom.length ? TK.custom.map( function ( c, i ) {
			return '<div class="vba-tk-item vba-tk-item-c"><span class="vba-tk-dash">--</span><input type="text" class="vba-tk-n" data-tk-xname="' + i + '" value="' + esc( c.name ) + '" placeholder="radius-m" spellcheck="false">' +
				'<select data-tk-xgroup="' + i + '">' + Object.keys( GROUPS ).map( function ( g ) { return '<option value="' + g + '"' + ( g === c.group ? ' selected' : '' ) + '>' + esc( GROUPS[ g ] ) + '</option>'; } ).join( '' ) + '</select>' +
				'<input type="text" class="vba-tk-v" data-tk-xval="' + i + '" value="' + esc( c.value ) + '" placeholder="12px" spellcheck="false">' +
				'<button type="button" class="vba-mini vba-mini-del" data-tk-del="custom:' + i + '" aria-label="' + esc( TT.remove ) + '">×</button></div>';
		} ).join( '' ) : '<p class="vba-hint">' + esc( TT.none ) + '</p>';
	}
	renderColors(); renderType(); renderSpace(); renderCustom();
	document.getElementById( 'vba-tokens' ).addEventListener( 'input', function ( e ) {
		var t = e.target, k;
		if ( ( k = t.getAttribute( 'data-tk' ) ) ) {
			var parts = k.split( '.' ), v = 'checkbox' === t.type ? ( t.checked ? 1 : 0 ) : parseFloat( t.value );
			if ( 'checkbox' !== t.type && isNaN( v ) ) { return; }
			TK.scales[ parts[ 0 ] ][ parts[ 1 ] ] = v;
			if ( 'type' === parts[ 0 ] ) { renderType(); } else { renderSpace(); }
			return;
		}
		if ( t.hasAttribute( 'data-tk-color' ) ) { var ci = +t.getAttribute( 'data-tk-color' ); TK.colors[ ci ].value = t.value; var tv = document.querySelector( '[data-tk-cval="' + ci + '"]' ); if ( tv ) { tv.value = t.value; } return; }
		if ( t.hasAttribute( 'data-tk-cname' ) ) { TK.colors[ +t.getAttribute( 'data-tk-cname' ) ].name = t.value.toLowerCase().replace( /[^a-z0-9-]/g, '' ); return; }
		if ( t.hasAttribute( 'data-tk-cval' ) ) { var cv = +t.getAttribute( 'data-tk-cval' ); TK.colors[ cv ].value = t.value.trim(); var sw = document.querySelector( '[data-tk-color="' + cv + '"]' ); if ( sw && /^#[0-9a-f]{6}$/i.test( t.value.trim() ) ) { sw.value = t.value.trim(); } return; }
		if ( t.hasAttribute( 'data-tk-xname' ) ) { TK.custom[ +t.getAttribute( 'data-tk-xname' ) ].name = t.value.toLowerCase().replace( /[^a-z0-9-]/g, '' ); return; }
		if ( t.hasAttribute( 'data-tk-xval' ) ) { TK.custom[ +t.getAttribute( 'data-tk-xval' ) ].value = t.value.trim(); return; }
	} );
	document.getElementById( 'vba-tokens' ).addEventListener( 'change', function ( e ) {
		var t = e.target;
		if ( t.hasAttribute( 'data-tk-xgroup' ) ) { TK.custom[ +t.getAttribute( 'data-tk-xgroup' ) ].group = t.value; return; }
		if ( t.getAttribute( 'data-tk' ) && 'SELECT' === t.tagName ) { var p2 = t.getAttribute( 'data-tk' ).split( '.' ); TK.scales[ p2[ 0 ] ][ p2[ 1 ] ] = parseFloat( t.value ); renderType(); }
	} );
	document.getElementById( 'vba-tokens' ).addEventListener( 'click', function ( e ) {
		var add = e.target.closest( '[data-tk-add]' ), del = e.target.closest( '[data-tk-del]' ), cp = e.target.closest( '[data-tk-copy]' );
		if ( add ) {
			if ( 'colors' === add.getAttribute( 'data-tk-add' ) ) { var n = 1; while ( TK.colors.some( function ( c ) { return c.name === 'colour-' + n; } ) ) { n++; } TK.colors.push( { name:'colour-' + n, value:'#2ab7f1' } ); renderColors(); }
			else { var m = 1; while ( TK.custom.some( function ( c ) { return c.name === 'token-' + m; } ) ) { m++; } TK.custom.push( { name:'token-' + m, group:'radius', value:'12px' } ); renderCustom(); }
			return;
		}
		if ( del ) { var d = del.getAttribute( 'data-tk-del' ).split( ':' ); TK[ d[ 0 ] ].splice( +d[ 1 ], 1 ); if ( 'colors' === d[ 0 ] ) { renderColors(); } else { renderCustom(); } return; }
		if ( cp ) {
			var txt = cp.getAttribute( 'data-tk-copy' );
			( navigator.clipboard ? navigator.clipboard.writeText( txt ) : Promise.reject() ).then( function () {
				var was = cp.textContent; cp.textContent = TT.copied; cp.classList.add( 'is-copied' ); setTimeout( function () { cp.textContent = was; cp.classList.remove( 'is-copied' ); }, 900 );
			} ).catch( function () {} );
		}
	} );
	window.vbaSaveTokens = function () {
		var C = cfg(), body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', 'builder_tokens_save' ); body.set( 'nonce', C.nonce || '' );
		body.set( 'tokens', JSON.stringify( TK ) );
		return fetch( C.ajaxurl || window.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } ).then( function ( r ) { return r.json(); } );
	};
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
		Promise.all( [
			fetch( C.ajaxurl || window.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } ).then( function ( r ) { return r.json(); } ),
			window.vbaSaveTokens()
		] )
			.then( function ( all ) { var ok = all[ 0 ] && all[ 0 ].success && all[ 1 ] && all[ 1 ].success; btn.textContent = ok ? T.saved : T.saveFailed; setTimeout( function () { btn.textContent = T.saveChanges; }, 1500 ); } )
			.catch( function () { btn.textContent = T.saveFailed; setTimeout( function () { btn.textContent = T.saveChanges; }, 2500 ); } );
	} );
}() );
</script>
<script>
( function () {
	// Sizes accept units (1.125rem, clamp(...)); the px hint only applies to bare numbers.
	document.querySelectorAll( '.vba-gs-in input[data-gs]' ).forEach( function ( i ) {
		var u = i.parentNode.querySelector( 'i' ); if ( ! u ) { return; }
		i.addEventListener( 'input', function () { u.hidden = /[a-z%(]/i.test( i.value ); } );
	} );
}() );
</script>
