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
<div class="velox-page-head velox-snip-pagehead">
	<div>
		<a class="velox-snip-back" href="<?php echo esc_url( Velox_Snippets::list_url() ); ?>">
			<svg class="velox-ic" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
			All snippets
		</a>
		<h1 class="velox-h2"><?php echo $snippet ? 'Edit snippet' : 'New snippet'; ?></h1>
	</div>
</div>

<div class="velox-snip-editor" id="velox-snip-editor" data-id="<?php echo (int) ( $snippet ? $snippet['id'] : 0 ); ?>" data-active="<?php echo $active ? '1' : '0'; ?>">

	<div class="velox-panel">
		<div class="velox-field">
			<label class="velox-label" for="velox-snip-name">Name</label>
			<input type="text" class="velox-input" id="velox-snip-name" value="<?php echo esc_attr( $name ); ?>" placeholder="What does this snippet do?">
		</div>

		<div class="velox-field">
			<label class="velox-label" for="velox-snip-desc">Description <span class="velox-opt">optional</span></label>
			<input type="text" class="velox-input" id="velox-snip-desc" value="<?php echo esc_attr( $desc ); ?>" placeholder="A short note for future you">
		</div>

		<div class="velox-snip-grid">
			<div class="velox-field">
				<label class="velox-label" for="velox-snip-type">Type</label>
				<select class="velox-select" id="velox-snip-type">
					<?php foreach ( $type_opts as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $type, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="velox-field">
				<label class="velox-label" for="velox-snip-scope">Location</label>
				<select class="velox-select" id="velox-snip-scope">
					<?php foreach ( $scope_opts as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $scope, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="velox-field velox-field--narrow">
				<label class="velox-label" for="velox-snip-prio">Priority</label>
				<input type="number" class="velox-input" id="velox-snip-prio" value="<?php echo esc_attr( $priority ); ?>" min="1" max="999">
			</div>
		</div>
	</div>

	<div class="velox-panel velox-snip-code-panel">
		<div class="velox-field" style="margin:0;">
			<label class="velox-label" for="velox-snip-code">Code</label>
			<textarea id="velox-snip-code" class="velox-snip-code" spellcheck="false"><?php echo esc_textarea( $code ); ?></textarea>
			<span class="velox-hint" id="velox-snip-codehint"></span>
		</div>
	</div>

	<div class="velox-snip-save velox-actions--sticky">
		<button class="velox-btn velox-btn--primary" id="velox-snip-save-activate" type="button">
			<?php echo $active ? 'Save & deactivate' : 'Save & activate'; ?>
		</button>
		<button class="velox-btn velox-btn--ghost" id="velox-snip-save-only" type="button">Save only</button>
	</div>
</div>
