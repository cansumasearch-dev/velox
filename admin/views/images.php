<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s        = Velox_Settings::all();
$engine   = Velox_Image_Optimizer::engine();
$avif_engine = Velox_Image_Optimizer::avif_engine();
$caps     = Velox_Image_Optimizer::capabilities();
$quality  = (int) $s['webp_quality'];
$show_cmp = ! empty( $s['image_comparison'] );

/* -------------------------------------------------------------------------
 * Converted-images screen (?view=converted) — a gallery of everything Velox
 * has turned into WebP, with real before/after sizes.
 * ---------------------------------------------------------------------- */
$velox_view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( 'converted' === $velox_view ) :
	$converted   = Velox_Image_Optimizer::get_converted();
	$back_url     = admin_url( 'admin.php?page=velox-images' );
	$total_saved  = 0;
	foreach ( $converted as $c ) { $total_saved += max( 0, $c['orig'] - $c['webp'] ); }
	?>
	<div class="velox-page-head velox-page-head--back">
		<a class="velox-back" href="<?php echo esc_url( $back_url ); ?>"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg> Images</a>
		<h1 class="velox-h2">Converted images</h1>
		<p class="velox-sub"><?php echo count( $converted ); ?> image<?php echo 1 === count( $converted ) ? '' : 's'; ?> converted to WebP<?php echo $total_saved > 0 ? ' &middot; ' . esc_html( size_format( $total_saved, 1 ) ) . ' saved' : ''; ?>.</p>
	</div>

	<?php if ( empty( $converted ) ) : ?>
		<div class="velox-panel">
			<p class="velox-hint" style="margin:0 0 12px;">Nothing converted yet. Run a bulk optimization to fill this up.</p>
			<a class="velox-btn velox-btn--primary" href="<?php echo esc_url( $back_url ); ?>">Go to the optimizer</a>
		</div>
	<?php else : ?>
		<div class="velox-conv-grid">
			<?php foreach ( $converted as $c ) : $pct = (int) round( $c['saved_pct'] ); ?>
				<div class="velox-conv-card">
					<a class="velox-conv-thumb" href="<?php echo esc_url( $c['url'] ); ?>" target="_blank" rel="noopener"<?php echo $c['thumb'] ? ' style="background-image:url(\'' . esc_url( $c['thumb'] ) . '\')"' : ''; ?>>
						<?php echo $pct > 0 ? '<span class="velox-conv-save">&minus;' . (int) $pct . '%</span>' : ''; ?>
					</a>
					<div class="velox-conv-body">
						<span class="velox-conv-name" title="<?php echo esc_attr( $c['title'] ); ?>"><?php echo esc_html( $c['title'] ); ?></span>
						<span class="velox-conv-sizes">
							<span class="velox-conv-was"><?php echo esc_html( size_format( $c['orig'], 0 ) ); ?></span>
							<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
							<span class="velox-conv-now"><?php echo esc_html( size_format( $c['webp'], 0 ) ); ?></span>
							<?php echo $c['replaced'] ? '<span class="velox-conv-tag">WebP</span>' : '<span class="velox-conv-tag velox-conv-tag--twin">+WebP</span>'; ?>
						</span>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php
	return;
endif;
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Images</h1>
	<p class="velox-sub">Your image optimization center — pick formats and quality, then convert your whole library. With replace mode on, images become WebP right in your media library; the resize width sets a max (height follows automatically, smaller images are left untouched).</p>
</div>

<?php if ( ! $engine ) : ?>
	<div class="velox-alert velox-alert--warn">No image engine (Imagick or GD with WebP) was found on this server. Conversion is disabled until one is enabled in your PHP settings — see the compatibility list below.</div>
<?php endif; ?>

<!-- ============ Output & engine ============ -->
<div class="velox-grid-2">
	<div class="velox-panel">
		<h3 class="velox-panel-title">Output formats</h3>
		<div class="velox-toggle-row">
			<div class="velox-toggle-meta">
				<span class="velox-toggle-label">WebP</span>
				<span class="velox-toggle-desc">The modern baseline — typically 25–35% smaller than JPG/PNG with wide browser support.</span>
			</div>
			<label class="velox-switch"><input type="checkbox" id="velox-webp" data-setting="image_webp" <?php checked( ! empty( $s['image_webp'] ) ); ?>><span class="velox-switch-track"></span></label>
		</div>
		<div class="velox-toggle-row"<?php echo $avif_engine ? '' : ' style="opacity:.55;"'; ?>>
			<div class="velox-toggle-meta">
				<span class="velox-toggle-label">AVIF
					<?php if ( $avif_engine ) : ?>
						<span class="velox-tag velox-tag--ok">Supported</span>
					<?php else : ?>
						<span class="velox-tag velox-tag--muted">Not on this server</span>
					<?php endif; ?>
				</span>
				<span class="velox-toggle-desc">An extra AVIF twin, usually 15–30% smaller again. Capable browsers get AVIF, everyone else falls back to WebP, then the original. Slower to encode.</span>
			</div>
			<label class="velox-switch"><input type="checkbox" id="velox-avif" data-setting="image_avif" <?php checked( ! empty( $s['image_avif'] ) ); ?> <?php disabled( ! $avif_engine ); ?>><span class="velox-switch-track"></span></label>
		</div>
		<div class="velox-toggle-row">
			<div class="velox-toggle-meta">
				<span class="velox-toggle-label">Replace originals with WebP</span>
				<span class="velox-toggle-desc">On (recommended): the JPG/PNG becomes a WebP right in your media library, at its real smaller size. Off: keeps the original and serves a WebP copy only on the front-end.</span>
			</div>
			<label class="velox-switch"><input type="checkbox" id="velox-replace" data-setting="image_replace" <?php checked( ! empty( $s['image_replace'] ) ); ?>><span class="velox-switch-track"></span></label>
		</div>
		<p class="velox-hint"><?php echo ! empty( $s['image_replace'] ) ? 'Replace mode is on — images become WebP in your media library. The original is kept on disk as a fallback and the front-end is rewritten to serve WebP, so nothing breaks.' : 'Your original JPG/PNG files are kept and served to browsers that don\'t support these formats.'; ?></p>
	</div>

	<div class="velox-panel">
		<h3 class="velox-panel-title">Conversion engine</h3>
		<div class="velox-field">
			<span class="velox-field-label">Engine</span>
			<select class="velox-select" id="velox-engine" data-setting="image_engine">
				<option value="auto" <?php selected( $s['image_engine'], 'auto' ); ?>>Auto (recommended)</option>
				<option value="imagick" <?php selected( $s['image_engine'], 'imagick' ); ?>>Imagick</option>
				<option value="gd" <?php selected( $s['image_engine'], 'gd' ); ?>>GD</option>
			</select>
			<span class="velox-hint">Auto picks the best available engine. Force one only if you have a reason to.</span>
		</div>
		<div class="velox-engine-compat">
			<?php foreach ( $caps as $cap ) : ?>
				<div class="velox-engine-row">
					<span class="velox-engine-name"><?php echo esc_html( $cap['label'] ); ?></span>
					<span class="velox-engine-badges">
						<?php if ( $cap['available'] ) : ?>
							<span class="velox-tag velox-tag--ok">Available</span>
							<span class="velox-tag <?php echo $cap['webp'] ? 'velox-tag--ok' : 'velox-tag--muted'; ?>">WebP</span>
							<span class="velox-tag <?php echo $cap['avif'] ? 'velox-tag--ok' : 'velox-tag--muted'; ?>">AVIF</span>
						<?php else : ?>
							<span class="velox-tag velox-tag--muted">Not installed</span>
						<?php endif; ?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>

<!-- ============ Quality & processing ============ -->
<div class="velox-panel">
	<h3 class="velox-panel-title">Quality &amp; processing</h3>
	<div class="velox-field">
		<span class="velox-field-label">Quality <em id="velox-q-val"><?php echo esc_html( $quality ); ?>%</em></span>
		<div class="velox-quality-row">
			<input type="range" id="velox-quality" min="40" max="100" step="1" value="<?php echo esc_attr( $quality ); ?>" class="velox-range">
			<div class="velox-quality-num">
				<input type="number" id="velox-quality-num" class="velox-input velox-input--xs" min="1" max="100" step="1" value="<?php echo esc_attr( $quality ); ?>" aria-label="Quality value">
				<span class="velox-quality-pct">%</span>
			</div>
		</div>
		<span class="velox-hint">Drag the slider or type an exact value. 80% is a good balance; lossless mode below ignores this.</span>
	</div>
	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label">Lossless WebP <span class="velox-tag velox-tag--muted">Imagick</span></span>
			<span class="velox-toggle-desc">Perfect quality with no compression loss — larger files. Great for graphics and screenshots, overkill for photos.</span>
		</div>
		<label class="velox-switch"><input type="checkbox" id="velox-lossless" data-setting="image_lossless" <?php checked( ! empty( $s['image_lossless'] ) ); ?>><span class="velox-switch-track"></span></label>
	</div>
	<div class="velox-field">
		<span class="velox-field-label">Resize width (px)</span>
		<input type="number" class="velox-input velox-input--sm" id="velox-max-width" data-setting="image_max_width" value="<?php echo esc_attr( (int) $s['image_max_width'] ); ?>" min="0" step="10">
		<span class="velox-hint">Images wider than this are scaled down to it; the height follows automatically to keep the aspect ratio. Images already narrower are left at their own size (never upscaled). 0 = never resize.</span>
	</div>
	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label">Preserve EXIF metadata</span>
			<span class="velox-toggle-desc">Off (default) strips camera, date and GPS data for smaller, more private files. On keeps it.</span>
		</div>
		<label class="velox-switch"><input type="checkbox" id="velox-keep-exif" data-setting="image_keep_exif" <?php checked( ! empty( $s['image_keep_exif'] ) ); ?>><span class="velox-switch-track"></span></label>
	</div>
</div>

<!-- ============ Bulk optimization ============ -->
<div class="velox-grid-2">
	<div class="velox-panel">
		<h3 class="velox-panel-title">Bulk optimization</h3>
		<p class="velox-hint">Convert every JPG and PNG in your library to the formats selected above. Safe to stop and resume anytime.</p>
		<div class="velox-progress-wrap" id="velox-bulk-progress" hidden>
			<div class="velox-progress"><div class="velox-progress-bar" id="velox-bulk-bar"></div></div>
			<span class="velox-progress-text" id="velox-bulk-text">0 / 0</span>
		</div>
		<div class="velox-actions">
			<button class="velox-btn velox-btn--primary" id="velox-bulk-start" <?php disabled( ! $engine ); ?>>Convert pending images</button>
			<button class="velox-btn velox-btn--ghost" id="velox-bulk-stop" hidden>Stop</button>
			<a class="velox-btn velox-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=velox-images&view=converted' ) ); ?>">View converted images →</a>
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
			<button class="velox-btn velox-btn--ghost" id="velox-lib-bulk">Find &amp; replace names</button>
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
