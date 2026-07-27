<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$blueprints = Velox_Utilities::blueprints();
?>
<div class="velox-page-head">
	<h1 class="velox-h2"><?php esc_html_e('Bulk installer', 'velox'); ?></h1>
	<p class="velox-sub"><?php esc_html_e('Install a whole stack at once — paste wordpress.org slugs or links, or upload plugin ZIPs straight from your computer. Save a list as a blueprint to re-apply on the next site.', 'velox'); ?></p>
</div>

<div class="velox-panel velox-tool-form">
	<div class="velox-field">
		<span class="velox-field-label"><?php esc_html_e('Plugins — slugs or links', 'velox'); ?></span>
		<textarea class="velox-textarea" id="velox-installer-slugs" rows="6" placeholder="wp-fastest-cache&#10;https://wordpress.org/plugins/wordfence/&#10;https://example.com/my-plugin.zip"></textarea>
		<span class="velox-hint"><?php printf( esc_html__( 'One per line. Accepts a plain slug (%1$s), a wordpress.org link, or a direct %2$s download URL.', 'velox' ), '<code>' . esc_html__( 'wp-fastest-cache', 'velox' ) . '</code>', '<code>' . esc_html__( '.zip', 'velox' ) . '</code>' ); ?></span>
	</div>

	<div class="velox-field">
		<span class="velox-field-label"><?php esc_html_e('Or upload plugin ZIPs', 'velox'); ?></span>
		<input type="file" class="velox-file" id="velox-installer-zip" accept=".zip,application/zip" multiple>
		<span class="velox-hint"><?php printf( esc_html__( 'Pick one or more %s plugin files from your computer and install them directly.', 'velox' ), '<code>' . esc_html__( '.zip', 'velox' ) . '</code>' ); ?></span>
	</div>

	<label class="velox-toggle-row" style="cursor:pointer;">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label"><?php esc_html_e('Activate after install', 'velox'); ?></span>
			<span class="velox-toggle-desc"><?php esc_html_e('Turn each plugin on as soon as it\'s installed.', 'velox'); ?></span>
		</div>
		<span class="velox-switch"><input type="checkbox" id="velox-installer-activate" checked><span class="velox-switch-track"></span></span>
	</label>

	<div class="velox-tool-actions" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
		<button class="velox-btn velox-btn--primary" id="velox-installer-run"><?php esc_html_e('Install from list', 'velox'); ?></button>
		<button class="velox-btn velox-btn--ghost" id="velox-installer-upload">Upload &amp; install ZIPs</button>
		<span style="flex:1;"></span>
		<input type="text" class="velox-input" id="velox-blueprint-name" placeholder="Blueprint name (e.g. Agency base)" style="max-width:240px;">
		<button class="velox-btn velox-btn--ghost" id="velox-blueprint-save"><?php esc_html_e('Save as blueprint', 'velox'); ?></button>
	</div>

	<div id="velox-installer-log" class="velox-install-log" hidden></div>
</div>

<div class="velox-panel">
	<h3 class="velox-panel-title"><?php esc_html_e('Saved blueprints', 'velox'); ?></h3>
	<div id="velox-blueprint-list" class="velox-bp-list">
		<?php if ( empty( $blueprints ) ) : ?>
			<p class="velox-hint" id="velox-bp-empty"><?php esc_html_e('No blueprints yet. Save a slug list above to create one.', 'velox'); ?></p>
		<?php else : ?>
			<?php foreach ( $blueprints as $name => $slugs ) : ?>
				<div class="velox-bp-item" data-name="<?php echo esc_attr( $name ); ?>" data-slugs="<?php echo esc_attr( implode( "\n", (array) $slugs ) ); ?>">
					<div class="velox-bp-meta">
						<span class="velox-bp-name"><?php echo esc_html( $name ); ?></span>
						<span class="velox-bp-count"><?php echo count( (array) $slugs ); ?> plugins</span>
					</div>
					<div class="velox-bp-actions">
						<button class="velox-btn velox-btn--ghost velox-bp-load"><?php esc_html_e('Load', 'velox'); ?></button>
						<button class="velox-btn velox-btn--ghost velox-bp-del"><?php esc_html_e('Delete', 'velox'); ?></button>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>
