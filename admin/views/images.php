<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s        = Velox_Settings::all();
$engine   = Velox_Image_Optimizer::engine();
$quality  = (int) $s['webp_quality'];
$show_cmp = ! empty( $s['image_comparison'] );
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Images</h1>
	<p class="velox-sub">Convert your library to WebP, browse and filter every image, and rename files safely — references are fixed automatically so nothing breaks.</p>
</div>

<?php if ( ! $engine ) : ?>
	<div class="velox-alert velox-alert--warn">No WebP engine (Imagick or GD with WebP) was found on this server. Conversion is disabled until one is enabled in your PHP settings.</div>
<?php endif; ?>

<!-- ============ WebP conversion ============ -->
<div class="velox-grid-2">
	<div class="velox-panel">
		<h3 class="velox-panel-title">Bulk convert to WebP</h3>
		<label class="velox-field">
			<span class="velox-field-label">Quality <em id="velox-q-val"><?php echo esc_html( $quality ); ?>%</em></span>
			<input type="range" id="velox-quality" min="40" max="100" step="1" value="<?php echo esc_attr( $quality ); ?>" class="velox-range">
			<span class="velox-hint">80% is a good balance. Originals are always kept as fallbacks.</span>
		</label>
		<div class="velox-field">
			<span class="velox-field-label">Max width (px)</span>
			<input type="number" class="velox-input velox-input--sm" id="velox-max-width" data-setting="image_max_width" value="<?php echo esc_attr( (int) $s['image_max_width'] ); ?>" min="0" step="10">
			<span class="velox-hint">Images wider than this are scaled down on upload and conversion. 2560 suits most sites; 0 = never resize.</span>
		</div>
		<div class="velox-toggle-row">
			<div class="velox-toggle-meta">
				<span class="velox-toggle-label">Preserve EXIF metadata</span>
				<span class="velox-toggle-desc">Off (default) strips camera, date and GPS data for smaller, more private files. On keeps it.</span>
			</div>
			<label class="velox-switch"><input type="checkbox" id="velox-keep-exif" data-setting="image_keep_exif" <?php checked( ! empty( $s['image_keep_exif'] ) ); ?>><span class="velox-switch-track"></span></label>
		</div>
		<div class="velox-progress-wrap" id="velox-bulk-progress" hidden>
			<div class="velox-progress"><div class="velox-progress-bar" id="velox-bulk-bar"></div></div>
			<span class="velox-progress-text" id="velox-bulk-text">0 / 0</span>
		</div>
		<div class="velox-actions">
			<button class="velox-btn velox-btn--primary" id="velox-bulk-start" <?php disabled( ! $engine ); ?>>Convert pending images</button>
			<button class="velox-btn velox-btn--ghost" id="velox-bulk-stop" hidden>Stop</button>
		</div>
		<p class="velox-hint" id="velox-bulk-summary"></p>
	</div>

	<div class="velox-panel">
		<h3 class="velox-panel-title">Library</h3>
		<div class="velox-mini-stats" id="velox-img-stats">
			<div><span data-mini="done">—</span><small>Optimized</small></div>
			<div><span data-mini="pending">—</span><small>Pending</small></div>
			<div><span data-mini="saved">—</span><small>Saved</small></div>
		</div>
		<div class="velox-ring-wrap">
			<svg class="velox-ring" viewBox="0 0 120 120">
				<circle cx="60" cy="60" r="52" class="velox-ring-bg"/>
				<circle cx="60" cy="60" r="52" class="velox-ring-fg" id="velox-ring-fg"/>
			</svg>
			<span class="velox-ring-label" id="velox-ring-label">0%</span>
		</div>
	</div>
</div>

<!-- ============ Library browser ============ -->
<div class="velox-panel">
	<div class="velox-lib-toolbar">
		<div class="velox-chips" id="velox-lib-filters">
			<button type="button" class="velox-chip is-active" data-filter="all">All</button>
			<button type="button" class="velox-chip" data-filter="jpg">JPG</button>
			<button type="button" class="velox-chip" data-filter="png">PNG</button>
			<button type="button" class="velox-chip" data-filter="webp">WebP</button>
			<button type="button" class="velox-chip" data-filter="gif">GIF</button>
			<button type="button" class="velox-chip" data-filter="svg">SVG</button>
		</div>
		<input type="search" id="velox-lib-search" class="velox-input" placeholder="Search filename or title…">
		<div class="velox-lib-toolbar-right">
			<button class="velox-btn velox-btn--ghost" id="velox-lib-bulk">Bulk rename</button>
			<button class="velox-btn velox-btn--primary" id="velox-lib-apply-all" hidden>Apply all names</button>
		</div>
	</div>

	<div class="velox-lib-grid" id="velox-lib-grid"><div class="velox-loading">Loading images…</div></div>

	<div class="velox-pager">
		<button class="velox-btn velox-btn--ghost" id="velox-lib-prev" disabled>← Prev</button>
		<span id="velox-lib-pageinfo" class="velox-hint">—</span>
		<button class="velox-btn velox-btn--ghost" id="velox-lib-next" disabled>Next →</button>
	</div>
	<p class="velox-hint">Typed names are saved in your browser — reload safely, they'll still be here until you apply them. Renaming updates every reference in posts and Oxygen automatically.</p>
</div>

<?php if ( $show_cmp ) : ?>
<!-- ============ Comparator ============ -->
<div class="velox-panel">
	<h3 class="velox-panel-title">Before / after</h3>
	<div class="velox-compare-toolbar">
		<select id="velox-compare-select" class="velox-select"><option value="">Loading optimized images…</option></select>
	</div>
	<div class="velox-compare" id="velox-compare" hidden>
		<div class="velox-compare-stage" id="velox-compare-stage">
			<img id="velox-compare-webp" alt="WebP version" class="velox-compare-img">
			<div class="velox-compare-top" id="velox-compare-top">
				<img id="velox-compare-orig" alt="Original version" class="velox-compare-img">
			</div>
			<span class="velox-compare-tag velox-compare-tag--l">Original</span>
			<span class="velox-compare-tag velox-compare-tag--r">WebP</span>
			<div class="velox-compare-handle" id="velox-compare-handle"><span></span></div>
		</div>
		<div class="velox-compare-stats" id="velox-compare-stats"></div>
	</div>
</div>
<?php endif; ?>

<!-- ============ Preview lightbox ============ -->
<div class="velox-lightbox" id="velox-lightbox" hidden>
	<div class="velox-lightbox-inner">
		<img id="velox-lightbox-img" src="" alt="">
		<div class="velox-lightbox-meta" id="velox-lightbox-meta"></div>
		<button class="velox-lightbox-close" id="velox-lightbox-close" aria-label="Close">&times;</button>
	</div>
</div>
