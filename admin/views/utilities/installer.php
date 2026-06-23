<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$blueprints = Velox_Utilities::blueprints();
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Bulk installer</h1>
	<p class="velox-sub">Paste WordPress.org plugin slugs (one per line) and install the whole stack in one click. Save the list as a blueprint to re-apply it on the next site.</p>
</div>

<div class="velox-panel velox-tool-form">
	<div class="velox-field">
		<span class="velox-field-label">Plugin slugs</span>
		<textarea class="velox-textarea" id="velox-installer-slugs" rows="6" placeholder="wordfence&#10;wp-fastest-cache&#10;code-snippets"></textarea>
		<span class="velox-hint">The slug is the last part of a plugin's wordpress.org URL — e.g. <code>wordpress.org/plugins/<strong>wp-fastest-cache</strong></code>.</span>
	</div>

	<label class="velox-toggle-row" style="cursor:pointer;">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label">Activate after install</span>
			<span class="velox-toggle-desc">Turn each plugin on as soon as it's installed.</span>
		</div>
		<span class="velox-switch"><input type="checkbox" id="velox-installer-activate" checked><span class="velox-switch-track"></span></span>
	</label>

	<div class="velox-tool-actions" style="display:flex;gap:10px;flex-wrap:wrap;">
		<button class="velox-btn velox-btn--primary" id="velox-installer-run">Install all</button>
		<input type="text" class="velox-input" id="velox-blueprint-name" placeholder="Blueprint name (e.g. Agency base)" style="max-width:260px;">
		<button class="velox-btn velox-btn--ghost" id="velox-blueprint-save">Save as blueprint</button>
	</div>

	<div id="velox-installer-log" class="velox-install-log" hidden></div>
</div>

<div class="velox-panel">
	<h3 class="velox-panel-title">Saved blueprints</h3>
	<div id="velox-blueprint-list" class="velox-bp-list">
		<?php if ( empty( $blueprints ) ) : ?>
			<p class="velox-hint" id="velox-bp-empty">No blueprints yet. Save a slug list above to create one.</p>
		<?php else : ?>
			<?php foreach ( $blueprints as $name => $slugs ) : ?>
				<div class="velox-bp-item" data-name="<?php echo esc_attr( $name ); ?>" data-slugs="<?php echo esc_attr( implode( "\n", (array) $slugs ) ); ?>">
					<div class="velox-bp-meta">
						<span class="velox-bp-name"><?php echo esc_html( $name ); ?></span>
						<span class="velox-bp-count"><?php echo count( (array) $slugs ); ?> plugins</span>
					</div>
					<div class="velox-bp-actions">
						<button class="velox-btn velox-btn--ghost velox-bp-load">Load</button>
						<button class="velox-btn velox-btn--ghost velox-bp-del">Delete</button>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>
