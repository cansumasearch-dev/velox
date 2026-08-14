<?php
/**
 * Snippets — create / edit screen (redesigned, unified Apple system).
 *
 * @var array|null $snippet
 * @var string     $new_type
 * @var int        $id
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$type     = $snippet ? $snippet['type'] : ( in_array( $new_type, Velox_Snippets::TYPES, true ) ? $new_type : 'php' );
$active   = $snippet ? (int) $snippet['active'] === 1 : false;
$scope    = $snippet ? $snippet['scope'] : 'everywhere';
$priority = $snippet ? (int) $snippet['priority'] : 10;
$name     = $snippet ? $snippet['name'] : '';
$desc     = $snippet ? $snippet['description'] : '';
$code     = $snippet ? $snippet['code'] : '';
$location = $snippet && isset( $snippet['location'] ) ? $snippet['location'] : '';
$loc_num  = $snippet && isset( $snippet['location_num'] ) ? (int) $snippet['location_num'] : 1;
// Effective dropdown value: PHP uses scope; output types use location (legacy fallback).
if ( 'php' === $type ) {
	$cur_loc = $scope;
} else {
	$cur_loc = '' !== $location ? $location : ( 'css' === $type ? 'head' : 'site_footer' );
}
$loc_opts = Velox_Snippets::locations_for( $type );
$loc_map  = array(
	'php'  => Velox_Snippets::locations_for( 'php' ),
	'css'  => Velox_Snippets::locations_for( 'css' ),
	'js'   => Velox_Snippets::locations_for( 'js' ),
	'html' => Velox_Snippets::locations_for( 'html' ),
);

$type_opts = array(
	'php'  => 'PHP — functions & hooks',
	'css'  => 'CSS — styles',
	'js'   => 'JS — scripts',
	'html' => 'HTML — markup',
);
$scope_opts = array(
	'everywhere' => 'Run everywhere',
	'admin'      => 'Only in the admin area',
	'front'      => 'Only on the site front-end',
	'once'       => 'Run once, then switch off',
);
?>
<div class="velox-snip-editor" id="velox-snip-editor" data-id="<?php echo (int) ( $snippet ? $snippet['id'] : 0 ); ?>" data-active="<?php echo $active ? '1' : '0'; ?>">

	<div class="velox-snip-edbar">
		<a class="velox-snip-back" href="<?php echo esc_url( Velox_Snippets::list_url() ); ?>">
			<svg class="velox-ic" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
			<?php esc_html_e('All snippets', 'velox'); ?>
		</a>
		<span class="velox-snip-edbar-title">
			<span class="velox-snip-badge is-<?php echo esc_attr( $type ); ?>" id="velox-snip-edbadge"><?php echo esc_html( strtoupper( $type ) ); ?></span>
			<?php echo $snippet ? 'Edit snippet' : 'New snippet'; ?>
		</span>
		<div class="velox-snip-edbar-actions">
			<button class="velox-btn velox-btn--ghost" id="velox-snip-save-only" type="button"><?php esc_html_e('Save only', 'velox'); ?></button>
			<button class="velox-btn velox-btn--primary" id="velox-snip-save-activate" type="button">
				<?php echo $active ? 'Save & deactivate' : 'Save & activate'; ?>
			</button>
		</div>
	</div>

	<div class="velox-snip-edgrid">
		<div class="velox-panel velox-snip-code-panel">
			<div class="velox-field" style="margin:0;">
				<div class="velox-snip-codehead">
					<label class="velox-label" for="velox-snip-code"><?php esc_html_e('Code', 'velox'); ?></label>
					<span class="velox-snip-lang" id="velox-snip-lang" aria-live="polite"></span>
				</div>
				<textarea id="velox-snip-code" class="velox-snip-code" spellcheck="false"><?php echo esc_textarea( $code ); ?></textarea>
				<span class="velox-hint" id="velox-snip-codehint"></span>
			</div>
		</div>

		<aside class="velox-snip-edside">
			<div class="velox-panel">
				<div class="velox-field">
					<label class="velox-label" for="velox-snip-name"><?php esc_html_e('Name', 'velox'); ?></label>
					<input type="text" class="velox-input" id="velox-snip-name" value="<?php echo esc_attr( $name ); ?>" placeholder="What does this snippet do?">
				</div>
				<div class="velox-field">
					<label class="velox-label" for="velox-snip-desc"><?php esc_html_e('Description', 'velox'); ?> <span class="velox-opt"><?php esc_html_e('optional', 'velox'); ?></span></label>
					<input type="text" class="velox-input" id="velox-snip-desc" value="<?php echo esc_attr( $desc ); ?>" placeholder="A short note for future you">
				</div>
				<div class="velox-field">
					<label class="velox-label" for="velox-snip-type"><?php esc_html_e('Type', 'velox'); ?></label>
					<select class="velox-select" id="velox-snip-type">
						<?php foreach ( $type_opts as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $type, $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="velox-field">
					<label class="velox-label" for="velox-snip-scope"><?php esc_html_e('Location', 'velox'); ?></label>
					<select class="velox-select" id="velox-snip-scope">
						<?php foreach ( $loc_opts as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $cur_loc, $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<span class="velox-hint" id="velox-snip-loc-hint" style="margin-top:6px;"></span>
				</div>
				<div class="velox-field velox-field--narrow" id="velox-snip-locnum-wrap"<?php echo Velox_Snippets::location_needs_num( $cur_loc ) ? '' : ' hidden'; ?>>
					<label class="velox-label" for="velox-snip-locnum"><?php esc_html_e('Paragraph number', 'velox'); ?></label>
					<input type="number" class="velox-input" id="velox-snip-locnum" value="<?php echo esc_attr( $loc_num ); ?>" min="1" max="999">
				</div>
				<script type="application/json" id="velox-snip-locmap"><?php echo wp_json_encode( $loc_map ); ?></script>
				<div class="velox-field velox-field--narrow">
					<label class="velox-label" for="velox-snip-prio"><?php esc_html_e('Priority', 'velox'); ?></label>
					<input type="number" class="velox-input" id="velox-snip-prio" value="<?php echo esc_attr( $priority ); ?>" min="1" max="999">
				</div>
			</div>
		</aside>
	</div>
</div>

<script>
( function () {
	/* Say which language the editor is expecting, and what the code is wrapped
	   in — a PHP snippet behaves very differently to a CSS one and nothing on
	   this screen used to say so. */
	var typeSel = document.getElementById( 'velox-snip-type' );
	var lang    = document.getElementById( 'velox-snip-lang' );
	var code    = document.getElementById( 'velox-snip-code' );
	if ( ! typeSel || ! lang ) { return; }
	var MAP = {
		php:  { tag: '<?php … ?>',      note: '<?php echo esc_js( __( 'Runs on the server. Do not add the opening tag — Velox adds it.', 'velox' ) ); ?>' },
		css:  { tag: '<style> … </style>', note: '<?php echo esc_js( __( 'Added to the page styles. No <style> tag needed.', 'velox' ) ); ?>' },
		js:   { tag: '<script> … </script>', note: '<?php echo esc_js( __( 'Runs in the browser. No <script> tag needed.', 'velox' ) ); ?>' },
		html: { tag: '<html>',          note: '<?php echo esc_js( __( 'Inserted as-is.', 'velox' ) ); ?>' }
	};
	function sync() {
		var v = ( typeSel.value || '' ).toLowerCase();
		var m = MAP[ v ] || MAP[ Object.keys( MAP ).filter( function ( k ) { return v.indexOf( k ) > -1; } )[ 0 ] ];
		if ( ! m ) { lang.textContent = ''; lang.removeAttribute( 'data-lang' ); return; }
		lang.setAttribute( 'data-lang', v );
		lang.innerHTML = '<code>' + m.tag.replace( /</g, '&lt;' ) + '</code><span>' + m.note + '</span>';
		if ( code ) { code.setAttribute( 'placeholder', m.tag ); }
	}
	typeSel.addEventListener( 'change', sync );
	sync();
}() );
</script>
