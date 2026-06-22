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
</div>

<div class="velox-panel">
	<h3 class="velox-panel-title">Updates</h3>
	<p class="velox-hint">Velox updates from GitHub releases, so it never appears in the public plugin directory. Repo: <code><?php echo esc_html( VELOX_GH_USER . '/' . VELOX_GH_REPO ); ?></code> (edit in <code>velox.php</code>).</p>
	<label class="velox-field">
		<span class="velox-field-label">GitHub access token <small>(only for a private repo)</small></span>
		<input type="password" class="velox-input" data-setting="gh_token" value="<?php echo esc_attr( $s['gh_token'] ); ?>" placeholder="github_pat_…" autocomplete="off">
		<span class="velox-hint">Leave empty for a public repo. For private, use a fine-grained token with read-only Contents access to this repo.</span>
	</label>
	<div class="velox-actions">
		<button class="velox-btn velox-btn--ghost" id="velox-check-updates">Check for updates now</button>
	</div>
</div>

<div class="velox-actions velox-actions--sticky">
	<button class="velox-btn velox-btn--primary" id="velox-settings-save">Save settings</button>
</div>
