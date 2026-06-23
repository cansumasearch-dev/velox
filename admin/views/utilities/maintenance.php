<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s = Velox_Settings::all();
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Maintenance mode</h1>
	<p class="velox-sub">Shows visitors a branded holding page while you work. You and any other admins keep seeing the live site, and wp-admin stays reachable.</p>
</div>

<div class="velox-panel velox-tool-form">
	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label">Enable maintenance mode</span>
			<span class="velox-toggle-desc">Front end shows the holding page to everyone except logged-in admins. Sends a 503 so search engines know it's temporary.</span>
		</div>
		<label class="velox-switch"><input type="checkbox" data-setting="util_maintenance" <?php checked( ! empty( $s['util_maintenance'] ) ); ?>><span class="velox-switch-track"></span></label>
	</div>

	<div class="velox-field">
		<span class="velox-field-label">Heading</span>
		<input type="text" class="velox-input" data-setting="util_maintenance_title" value="<?php echo esc_attr( $s['util_maintenance_title'] ); ?>">
		<span class="velox-hint">Big line shown on the page.</span>
	</div>

	<div class="velox-field">
		<span class="velox-field-label">Message</span>
		<textarea class="velox-textarea" data-setting="util_maintenance_message" rows="3"><?php echo esc_textarea( $s['util_maintenance_message'] ); ?></textarea>
		<span class="velox-hint">Shown under the heading. Line breaks are kept.</span>
	</div>

	<div class="velox-tool-actions">
		<button class="velox-btn velox-btn--primary velox-util-save">Save</button>
	</div>
</div>
