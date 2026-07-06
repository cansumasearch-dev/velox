<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s = Velox_Settings::all();
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Settings</h1>
	<p class="velox-sub">Set your defaults, point the auto-updater at your repo, and check your environment.</p>
</div>

<div class="velox-panel">
	<h3 class="velox-panel-title">Page builder</h3>
	<?php $vx_b = Velox_Builders::current(); ?>
	<p class="velox-hint">Velox tunes its performance settings to your builder. Currently configured for:
		<strong><?php echo esc_html( $vx_b ? Velox_Builders::label( $vx_b ) : 'not set up yet' ); ?></strong>.</p>
	<div class="velox-fonts-btns">
		<button class="velox-btn velox-btn--primary" id="velox-open-wizard">Run setup wizard</button>
	</div>
	<p class="velox-hint">Switching builders? Run this again — Velox will wipe the old performance settings and reconfigure for the new one. Your image, font and database settings are kept.</p>
</div>

<div class="velox-panel">
	<h3 class="velox-panel-title">Quick setup</h3>
	<p class="velox-hint">Not sure what to toggle? Pick a starting point — you can fine-tune everything afterwards.</p>
	<div class="velox-fonts-btns">
		<button class="velox-btn velox-btn--primary" id="velox-preset-safe">Apply safe defaults</button>
		<button class="velox-btn velox-btn--ghost" id="velox-preset-aggressive">Apply aggressive preset</button>
	</div>
	<p class="velox-hint"><strong>Safe</strong> turns on only the optimizations that can't break a site. <strong>Aggressive</strong> adds async CSS, unused-CSS removal (auto-learn), JS delay and bloat removal — then test and exclude anything that misbehaves. Neither touches jQuery Migrate or content-visibility (those need per-site testing).</p>
</div>

<div class="velox-panel">
	<h3 class="velox-panel-title">System status</h3>
	<p class="velox-hint">A quick read on the environment Velox is running in — handy when debugging or filing a support note.</p>
	<?php
	$vx_cache_dir  = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/cache' : '';
	$vx_cache_ok   = $vx_cache_dir && ( is_writable( $vx_cache_dir ) || ( ! file_exists( $vx_cache_dir ) && is_writable( dirname( $vx_cache_dir ) ) ) );
	$vx_htaccess   = get_home_path() . '.htaccess';
	$vx_status = array(
		array( 'Velox version', VELOX_VERSION, null ),
		array( 'WordPress', get_bloginfo( 'version' ), null ),
		array( 'PHP', PHP_VERSION, version_compare( PHP_VERSION, '7.4', '>=' ) ),
		array( 'Memory limit', ini_get( 'memory_limit' ), null ),
		array( 'Max upload size', size_format( wp_max_upload_size() ), null ),
		array( 'Cache directory writable', $vx_cache_ok ? 'Yes' : 'No', (bool) $vx_cache_ok ),
		array( '.htaccess writable', ( file_exists( $vx_htaccess ) && is_writable( $vx_htaccess ) ) ? 'Yes' : 'No', file_exists( $vx_htaccess ) && is_writable( $vx_htaccess ) ),
	);
	foreach ( $vx_status as $row ) :
		$vx_dot = '';
		if ( true === $row[2] )  { $vx_dot = ' velox-status-v--ok'; }
		if ( false === $row[2] ) { $vx_dot = ' velox-status-v--warn'; }
		?>
		<div class="velox-status-row">
			<span class="velox-status-k"><?php echo esc_html( $row[0] ); ?></span>
			<span class="velox-status-v<?php echo esc_attr( $vx_dot ); ?>"><?php echo esc_html( $row[1] ); ?></span>
		</div>
	<?php endforeach; ?>
</div>

<div class="velox-panel">
	<h3 class="velox-panel-title">Dashboard traffic</h3>
	<p class="velox-hint">Velox can count page views with a tiny first-party script &mdash; no cookies, no raw IP stored (visitors are de-duped with a salted daily hash), bots and logged-in admins excluded. Powers the Visitors widget on the dashboard. Turn it off and nothing is collected.</p>
	<?php $vx_track_on = ( ! isset( $s['traffic_tracking'] ) ) ? true : ! empty( $s['traffic_tracking'] ); ?>
	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label">Count visitors</span>
			<span class="velox-toggle-desc">First-party, aggregate only. Mention it in your privacy policy.</span>
		</div>
		<label class="velox-switch">
			<input type="checkbox" data-setting="traffic_tracking" <?php checked( $vx_track_on ); ?>>
			<span class="velox-switch-track"></span>
		</label>
	</div>
</div>

<div class="velox-panel" id="pagespeed">
	<h3 class="velox-panel-title">Live PageSpeed</h3>
	<p class="velox-hint">Pull a real Lighthouse score from Google PageSpeed Insights on a schedule and show it on your dashboard. A free <a href="https://developers.google.com/speed/docs/insights/v5/get-started" target="_blank" rel="noopener">PageSpeed Insights API key</a> is recommended (checks run without one, but Google rate-limits keyless requests). Your server needs outbound access to <code>googleapis.com</code> and WordPress cron running.</p>
	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label">Enable PageSpeed status</span>
			<span class="velox-toggle-desc">Runs checks on the schedule below and shows the PageSpeed widget on the dashboard.</span>
		</div>
		<label class="velox-switch"><input type="checkbox" data-setting="ps_enable" <?php checked( ! empty( $s['ps_enable'] ) ); ?>><span class="velox-switch-track"></span></label>
	</div>
	<label class="velox-field velox-field--inline">
		<span class="velox-field-label">API key</span>
		<input type="text" class="velox-input" data-setting="ps_api_key" value="<?php echo esc_attr( $s['ps_api_key'] ); ?>" placeholder="Optional — paste your PSI API key" autocomplete="off" spellcheck="false">
	</label>
	<label class="velox-field velox-field--inline">
		<span class="velox-field-label">URL to test</span>
		<input type="url" class="velox-input" data-setting="ps_url" value="<?php echo esc_attr( $s['ps_url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
	</label>
	<label class="velox-field velox-field--inline">
		<span class="velox-field-label">Default view</span>
		<select class="velox-select" data-setting="ps_strategy">
			<option value="mobile" <?php selected( $s['ps_strategy'], 'mobile' ); ?>>Mobile</option>
			<option value="desktop" <?php selected( $s['ps_strategy'], 'desktop' ); ?>>Desktop</option>
		</select>
		<span class="velox-hint" style="flex-basis:100%;margin:4px 0 0;">Both devices are checked &mdash; this is just the one shown first on the dashboard.</span>
	</label>
	<label class="velox-field velox-field--inline">
		<span class="velox-field-label">Refresh every</span>
		<select class="velox-select" data-setting="ps_interval">
			<option value="hourly" <?php selected( $s['ps_interval'], 'hourly' ); ?>>Hour</option>
			<option value="twicedaily" <?php selected( $s['ps_interval'], 'twicedaily' ); ?>>12 hours</option>
			<option value="daily" <?php selected( $s['ps_interval'], 'daily' ); ?>>Day</option>
		</select>
	</label>
	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label">Show Core Web Vitals</span>
			<span class="velox-toggle-desc">Display the LCP / CLS / TBT metric chips on the widget.</span>
		</div>
		<label class="velox-switch"><input type="checkbox" data-setting="ps_show_metrics" <?php checked( ! empty( $s['ps_show_metrics'] ) ); ?>><span class="velox-switch-track"></span></label>
	</div>
	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label">Show what to fix</span>
			<span class="velox-toggle-desc">List the top opportunities Lighthouse found, biggest time-savings first.</span>
		</div>
		<label class="velox-switch"><input type="checkbox" data-setting="ps_show_issues" <?php checked( ! empty( $s['ps_show_issues'] ) ); ?>><span class="velox-switch-track"></span></label>
	</div>
	<div class="velox-actions">
		<button type="button" class="velox-btn velox-btn--ghost" data-ps-refresh>Run a check now</button>
		<span class="velox-hint" style="margin:0;align-self:center;">Save your settings first. A live check can take ~30&nbsp;seconds.</span>
	</div>
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
			<span class="velox-toggle-desc">Swaps every uploads image to WebP/AVIF when the browser supports it — WordPress images, Oxygen elements, CSS background-images and hard-coded links (originals stay as fallback).</span>
			<span class="velox-toggle-note">On by default. Rewrites the page HTML on the front end. With Cloudflare Polish enabled you can leave this off.</span>
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
	<h3 class="velox-panel-title">Migrate from another plugin</h3>
	<p class="velox-hint">Switching to Velox? Pull your existing configuration straight from these plugins. Velox only reads them — nothing in the other plugin is changed, and your current Velox values aren&rsquo;t overwritten where one already exists. Plugins marked <em>Migration coming soon</em> are recognised, but their one-click import isn&rsquo;t built yet — <a href="https://www.sumasearch.de/" target="_blank" rel="noopener">tell us</a> which you need next.</p>
	<div class="velox-import-sources">
		<?php foreach ( Velox_Import::sources() as $key => $src ) : ?>
			<div class="velox-import-src" data-source="<?php echo esc_attr( $key ); ?>">
				<div class="velox-import-src-main">
					<div class="velox-import-src-head">
						<strong><?php echo esc_html( $src['label'] ); ?></strong>
						<?php if ( $src['detected'] ) : ?>
							<span class="velox-pill velox-pill--ok">Detected</span>
						<?php else : ?>
							<span class="velox-pill">Not found</span>
						<?php endif; ?>
						<span class="velox-import-into">→ <?php echo esc_html( $src['into'] ); ?></span>
					</div>
					<p class="velox-hint" style="margin:4px 0 0;"><?php echo esc_html( $src['desc'] ); ?></p>
					<div class="velox-import-result" hidden></div>
				</div>
				<?php if ( ! empty( $src['ready'] ) ) : ?>
					<button class="velox-btn velox-btn--ghost velox-import-run" type="button" <?php echo $src['detected'] ? '' : 'disabled'; ?>>Import</button>
				<?php else : ?>
					<span class="velox-import-soon" title="Velox recognises this plugin — automatic migration is on the way">Migration coming soon</span>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
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

<div class="velox-panel">
	<h3 class="velox-panel-title">Housekeeping</h3>
	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label">Keep my settings if I delete Velox</span>
			<span class="velox-toggle-desc">By default, deleting the plugin wipes Velox&rsquo;s settings, forms, redirects and logs. Turn this on to leave everything in place so a reinstall picks up where you left off. Your media is never touched either way.</span>
		</div>
		<label class="velox-switch"><input type="checkbox" data-setting="keep_data_on_uninstall" <?php checked( ! empty( $s['keep_data_on_uninstall'] ) ); ?>><span class="velox-switch-track"></span></label>
	</div>
</div>

<div class="velox-actions velox-actions--sticky">
	<button class="velox-btn velox-btn--primary" id="velox-settings-save">Save settings</button>
</div>
