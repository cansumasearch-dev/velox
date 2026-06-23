<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$blueprints = Velox_Utilities::blueprints();
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Bulk installer</h1>
	<p class="velox-sub">Install a whole stack at once — paste wordpress.org slugs or links, or upload plugin ZIPs straight from your computer. Save a list as a blueprint to re-apply on the next site.</p>
</div>

<div class="velox-panel velox-tool-form">
	<div class="velox-field">
		<span class="velox-field-label">Plugins — slugs or links</span>
		<textarea class="velox-textarea" id="velox-installer-slugs" rows="6" placeholder="wp-fastest-cache&#10;https://wordpress.org/plugins/wordfence/&#10;https://example.com/my-plugin.zip"></textarea>
		<span class="velox-hint">One per line. Accepts a plain slug (<code>wp-fastest-cache</code>), a wordpress.org link, or a direct <code>.zip</code> download URL.</span>
	</div>

	<div class="velox-field">
		<span class="velox-field-label">Or upload plugin ZIPs</span>
		<input type="file" class="velox-file" id="velox-installer-zip" accept=".zip,application/zip" multiple>
		<span class="velox-hint">Pick one or more <code>.zip</code> plugin files from your computer and install them directly.</span>
	</div>

	<label class="velox-toggle-row" style="cursor:pointer;">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label">Activate after install</span>
			<span class="velox-toggle-desc">Turn each plugin on as soon as it's installed.</span>
		</div>
		<span class="velox-switch"><input type="checkbox" id="velox-installer-activate" checked><span class="velox-switch-track"></span></span>
	</label>

	<div class="velox-tool-actions" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
		<button class="velox-btn velox-btn--primary" id="velox-installer-run">Install from list</button>
		<button class="velox-btn velox-btn--ghost" id="velox-installer-upload">Upload &amp; install ZIPs</button>
		<span style="flex:1;"></span>
		<input type="text" class="velox-input" id="velox-blueprint-name" placeholder="Blueprint name (e.g. Agency base)" style="max-width:240px;">
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
