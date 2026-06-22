<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s = Velox_Settings::all();

/** Helper to render a toggle row. */
function velox_toggle( $key, $label, $desc, $note = '' ) {
	$on = ! empty( Velox_Settings::get( $key ) );
	?>
	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label"><?php echo esc_html( $label ); ?></span>
			<span class="velox-toggle-desc"><?php echo wp_kses_post( $desc ); ?></span>
			<?php if ( $note ) : ?><span class="velox-toggle-note"><?php echo wp_kses_post( $note ); ?></span><?php endif; ?>
		</div>
		<label class="velox-switch">
			<input type="checkbox" data-setting="<?php echo esc_attr( $key ); ?>" <?php checked( $on ); ?>>
			<span class="velox-switch-track"></span>
		</label>
	</div>
	<?php
}
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Performance</h1>
	<p class="velox-sub">Additive tweaks only. Page caching, minify and combine stay with WP Fastest Cache — Velox never touches them.</p>
</div>

<div class="velox-alert velox-alert--info">
	Flip one toggle at a time and re-test on PageSpeed Insights (mobile + desktop), then clear WP Fastest Cache. Items marked <span class="velox-tag-overlap">overlap</span> may already be handled elsewhere in your stack.
</div>

<div class="velox-panel">
	<h3 class="velox-panel-title">Head &amp; request cleanup</h3>
	<?php
	velox_toggle( 'perf_disable_emojis', 'Disable WP emojis', 'Removes the emoji detection script and styles from every page.' );
	velox_toggle( 'perf_clean_head', 'Clean <head>', 'Removes RSD, WLW manifest, generator tag, shortlink and extra feed links.' );
	velox_toggle( 'perf_disable_embeds', 'Disable oEmbed', 'Stops WordPress auto-embedding and loading the wp-embed script.' );
	velox_toggle( 'perf_remove_query_strings', 'Strip ?ver query strings', 'Removes version query args from CSS/JS URLs.', 'Minor; some CDNs cache better without them.' );
	velox_toggle( 'perf_disable_xmlrpc', 'Disable XML-RPC', 'Blocks xmlrpc.php and removes the pingback header. Leave on unless you use the WP mobile app or Jetpack.' );
	velox_toggle( 'perf_disable_dashicons', 'Disable Dashicons (front-end)', 'Drops the Dashicons stylesheet for logged-out visitors only.' );
	velox_toggle( 'perf_disable_jquery_migrate', 'Disable jQuery Migrate', 'Removes the legacy jQuery Migrate shim on the front end.', '<span class="velox-tag-overlap">overlap</span> Oxygen Bloat Eliminator may already do this.' );
	?>
</div>

<div class="velox-panel">
	<h3 class="velox-panel-title">Scripts &amp; loading</h3>
	<?php
	velox_toggle( 'perf_defer_js', 'Defer JavaScript', 'Adds <code>defer</code> to enqueued scripts. Test carefully with Oxygen.', 'Use the exclusion list below for jQuery, Oxygen and Fluent Form.' );
	?>
	<label class="velox-field">
		<span class="velox-field-label">Defer exclusions <small>(one per line — handle or filename fragment)</small></span>
		<textarea class="velox-textarea" data-setting="perf_defer_exclude" rows="3"><?php echo esc_textarea( $s['perf_defer_exclude'] ); ?></textarea>
	</label>

	<?php velox_toggle( 'perf_lazy_native', 'Native lazy-load images', 'Forces loading="lazy" on images.', '<span class="velox-tag-overlap">overlap</span> WP Fastest Cache usually handles lazy-load.' ); ?>

	<label class="velox-field">
		<span class="velox-field-label">DNS-prefetch / preconnect <small>(one URL per line)</small></span>
		<textarea class="velox-textarea" data-setting="perf_dns_prefetch" rows="3"><?php echo esc_textarea( $s['perf_dns_prefetch'] ); ?></textarea>
		<span class="velox-hint">Good for Cloudflare, font hosts, or any third-party origin used above the fold.</span>
	</label>
</div>

<div class="velox-panel">
	<h3 class="velox-panel-title">Background &amp; revisions</h3>
	<label class="velox-field velox-field--inline">
		<span class="velox-field-label">Heartbeat API</span>
		<select class="velox-select" data-setting="perf_heartbeat">
			<option value="default" <?php selected( $s['perf_heartbeat'], 'default' ); ?>>Default (15s)</option>
			<option value="slow" <?php selected( $s['perf_heartbeat'], 'slow' ); ?>>Slow (60s)</option>
			<option value="off" <?php selected( $s['perf_heartbeat'], 'off' ); ?>>Off (except post editor)</option>
		</select>
	</label>
	<?php velox_toggle( 'perf_limit_revisions', 'Limit post revisions', 'Caps how many revisions WordPress stores per post.' ); ?>
	<label class="velox-field velox-field--inline">
		<span class="velox-field-label">Revisions to keep</span>
		<input type="number" min="0" max="50" class="velox-input velox-input--sm" data-setting="perf_revisions_keep" value="<?php echo esc_attr( $s['perf_revisions_keep'] ); ?>">
	</label>
</div>

<div class="velox-actions velox-actions--sticky">
	<button class="velox-btn velox-btn--primary" id="velox-perf-save">Save performance settings</button>
</div>
