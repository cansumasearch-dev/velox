<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s = Velox_Settings::all();
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Settings</h1>
	<p class="velox-sub">Turn whole modules on or off, set your defaults, and point the auto-updater at your repo.</p>
</div>

<div class="velox-panel">
	<h3 class="velox-panel-title">Modules</h3>
	<p class="velox-hint">Disabling a module hides its tab and stops all of its hooks from loading.</p>
	<?php
	$modules = array(
		'module_images'      => array( 'Image Optimization', 'WebP conversion, comparator, library stats.' ),
		'module_media'       => array( 'Media Editor', 'Rename, alt/title editing, pipe import/export.' ),
		'module_performance' => array( 'Performance', 'Head cleanup, defer, heartbeat, DNS-prefetch.' ),
		'module_database'    => array( 'Database', 'Cleanup and table optimization.' ),
	);
	foreach ( $modules as $key => $m ) :
		$on = ! empty( $s[ $key ] );
		?>
		<div class="velox-toggle-row">
			<div class="velox-toggle-meta">
				<span class="velox-toggle-label"><?php echo esc_html( $m[0] ); ?></span>
				<span class="velox-toggle-desc"><?php echo esc_html( $m[1] ); ?></span>
			</div>
			<label class="velox-switch">
				<input type="checkbox" data-setting="<?php echo esc_attr( $key ); ?>" <?php checked( $on ); ?>>
				<span class="velox-switch-track"></span>
			</label>
		</div>
	<?php endforeach; ?>
</div>

<div class="velox-panel">
	<h3 class="velox-panel-title">Image defaults</h3>
	<label class="velox-field velox-field--inline">
		<span class="velox-field-label">Default WebP quality</span>
		<input type="number" min="1" max="100" class="velox-input velox-input--sm" data-setting="webp_quality" value="<?php echo esc_attr( $s['webp_quality'] ); ?>">
	</label>
	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label">Auto-convert new uploads</span>
			<span class="velox-toggle-desc">Every new JPG/PNG upload is converted to WebP automatically.</span>
		</div>
		<label class="velox-switch"><input type="checkbox" data-setting="webp_auto_convert" <?php checked( ! empty( $s['webp_auto_convert'] ) ); ?>><span class="velox-switch-track"></span></label>
	</div>
	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label">Convert thumbnail sizes too</span>
			<span class="velox-toggle-desc">Also generates WebP for each registered image size (recommended for srcset).</span>
		</div>
		<label class="velox-switch"><input type="checkbox" data-setting="webp_convert_sizes" <?php checked( ! empty( $s['webp_convert_sizes'] ) ); ?>><span class="velox-switch-track"></span></label>
	</div>
	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label">Serve WebP on the front end</span>
			<span class="velox-toggle-desc">Swaps WordPress-rendered &lt;img&gt; to WebP when the browser supports it (originals stay as fallback).</span>
			<span class="velox-toggle-note">Opt-in. Targets WP/Oxygen Image elements; CSS background-images aren't auto-swapped. With Cloudflare Polish enabled you can leave this off.</span>
		</div>
		<label class="velox-switch"><input type="checkbox" data-setting="webp_serve_rewrite" <?php checked( ! empty( $s['webp_serve_rewrite'] ) ); ?>><span class="velox-switch-track"></span></label>
	</div>
	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label">Before/after comparator</span>
			<span class="velox-toggle-desc">Shows the original-vs-WebP drag comparison panel on the Images tab.</span>
		</div>
		<label class="velox-switch"><input type="checkbox" data-setting="image_comparison" <?php checked( ! empty( $s['image_comparison'] ) ); ?>><span class="velox-switch-track"></span></label>
	</div>
</div>

<div class="velox-panel">
	<h3 class="velox-panel-title">Updates</h3>
	<p class="velox-hint">Velox updates straight from GitHub releases, so it never appears in the public plugin directory. When a new version is released it shows up like any other plugin update.</p>
	<div class="velox-actions">
		<a class="velox-btn velox-btn--ghost" href="<?php echo esc_url( admin_url( 'update-core.php?force-check=1' ) ); ?>">Check for updates</a>
		<a class="velox-btn velox-btn--ghost" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">Open plugins page</a>
	</div>
</div>

<div class="velox-panel">
	<h3 class="velox-panel-title">Import / Export</h3>
	<p class="velox-hint">Copy your whole Velox config to another site. Export gives you a JSON blob; paste one in and hit Import to apply it.</p>
	<div class="velox-actions">
		<button class="velox-btn velox-btn--ghost" id="velox-export">Export settings</button>
		<button class="velox-btn velox-btn--ghost" id="velox-import-open">Import settings</button>
	</div>
	<textarea id="velox-import-box" class="velox-textarea" rows="4" placeholder="Exported JSON appears here — or paste a config to import." hidden></textarea>
	<div class="velox-actions" id="velox-import-actions" hidden>
		<button class="velox-btn velox-btn--primary" id="velox-import-apply">Apply imported settings</button>
		<button class="velox-btn velox-btn--ghost" id="velox-import-cancel">Cancel</button>
	</div>
</div>

<div class="velox-actions velox-actions--sticky">
	<button class="velox-btn velox-btn--primary" id="velox-settings-save">Save settings</button>
</div>
