<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$on    = Velox_Settings::get( 'util_scripts', false );
$seen  = Velox_Scripts::seen();
$rules = Velox_Scripts::rules();

/** Render the rule control for one handle. */
function velox_script_row( $type, $handle, $src, $rules ) {
	$key  = $type . ':' . $handle;
	$rule = isset( $rules[ $key ] ) ? $rules[ $key ] : array( 'mode' => 'off', 'ids' => array() );
	$mode = $rule['mode'];
	$ids  = implode( ', ', (array) $rule['ids'] );
	$modes = array(
		'off'        => 'Load normally',
		'everywhere' => 'Disable everywhere',
		'except'     => 'Disable except on…',
		'only'       => 'Disable only on…',
	);
	?>
	<div class="velox-sm-row" data-handle="<?php echo esc_attr( $handle ); ?>" data-type="<?php echo esc_attr( $type ); ?>">
		<div class="velox-sm-meta">
			<span class="velox-sm-handle"><?php echo esc_html( $handle ); ?></span>
			<?php if ( $src ) : ?><span class="velox-sm-src"><?php echo esc_html( $src ); ?></span><?php endif; ?>
		</div>
		<select class="velox-select velox-sm-mode">
			<?php foreach ( $modes as $val => $label ) : ?>
				<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $mode, $val ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="text" class="velox-input velox-sm-ids" value="<?php echo esc_attr( $ids ); ?>" placeholder="e.g. kontakt, front, type:product"<?php echo ( 'except' === $mode || 'only' === $mode ) ? '' : ' hidden'; ?>>
	</div>
	<?php
}
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Script Manager</h1>
	<p class="velox-sub">Stop plugins from loading CSS/JS where it isn't needed — the classic example is keeping Contact Form 7 off every page except your contact page. Pages are matched by ID, slug, <code>front</code> (homepage), <code>blog</code>, <code>archive</code>, <code>shop</code>, or a whole post type with <code>type:product</code>, <code>type:post</code>, etc.</p>
</div>

<div class="velox-panel">
	<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
		<label class="velox-inline-toggle">
			<span><strong>Enable Script Manager</strong></span>
			<span class="velox-switch"><input type="checkbox" data-setting="util_scripts" id="velox-sm-toggle" <?php checked( $on ); ?>><span class="velox-switch-track"></span></span>
		</label>
		<?php if ( $on ) : ?>
			<div style="display:flex;gap:8px;">
				<button class="velox-btn velox-btn--ghost" id="velox-sm-scan">Scan site</button>
				<button class="velox-btn velox-btn--primary" id="velox-sm-save">Save changes</button>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( ! $on ) : ?>
		<p class="velox-hint" style="margin-top:14px;">Turn it on, then hit <strong>Scan site</strong> (or just browse your site) so Velox can learn which handles load. You'll then be able to switch any of them off here.</p>
	<?php else : ?>
		<?php if ( empty( $seen['scripts'] ) && empty( $seen['styles'] ) ) : ?>
			<p class="velox-hint" style="margin-top:14px;">No assets discovered yet. Click <strong>Scan site</strong> above, or visit a few pages of your site, then reload this screen.</p>
		<?php else : ?>
			<p class="velox-hint" style="margin:14px 0 4px;">The list grows as more of your site is visited. Set a rule, then <strong>Save changes</strong>. Disabling a handle also removes anything that depends on it, so re-test the page afterwards.</p>

			<?php if ( ! empty( $seen['styles'] ) ) : ?>
				<h3 class="velox-panel-title" style="margin-top:18px;">Styles (CSS) <span class="velox-count"><?php echo count( $seen['styles'] ); ?></span></h3>
				<div class="velox-sm-list">
					<?php foreach ( $seen['styles'] as $handle => $src ) { velox_script_row( 'style', $handle, $src, $rules ); } ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $seen['scripts'] ) ) : ?>
				<h3 class="velox-panel-title" style="margin-top:18px;">Scripts (JS) <span class="velox-count"><?php echo count( $seen['scripts'] ); ?></span></h3>
				<div class="velox-sm-list">
					<?php foreach ( $seen['scripts'] as $handle => $src ) { velox_script_row( 'script', $handle, $src, $rules ); } ?>
				</div>
			<?php endif; ?>

			<div class="velox-tool-actions" style="margin-top:18px;display:flex;gap:8px;">
				<button class="velox-btn velox-btn--primary" id="velox-sm-save-2">Save changes</button>
				<button class="velox-btn velox-btn--ghost" id="velox-sm-clear">Reset discovered list</button>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
