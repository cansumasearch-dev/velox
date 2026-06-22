<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$engine  = Velox_Image_Optimizer::engine();
$quality = (int) Velox_Settings::get( 'webp_quality', 80 );
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Image Optimization</h1>
	<p class="velox-sub">Convert your library to WebP. Originals are always kept as fallbacks.</p>
</div>

<?php if ( ! $engine ) : ?>
	<div class="velox-alert velox-alert--warn">
		No WebP-capable image library was found on this server. Ask your host (Plesk → PHP settings) to enable the <strong>Imagick</strong> or <strong>GD</strong> extension with WebP support.
	</div>
<?php endif; ?>

<div class="velox-grid-2">
	<div class="velox-panel">
		<h3 class="velox-panel-title">Bulk convert</h3>

		<label class="velox-field">
			<span class="velox-field-label">Quality <em id="velox-q-val"><?php echo esc_html( $quality ); ?>%</em></span>
			<input type="range" id="velox-quality" min="40" max="100" step="1" value="<?php echo esc_attr( $quality ); ?>" class="velox-range">
			<span class="velox-hint">Lower = smaller files. 80% is a good balance; photos tolerate 70–75%.</span>
		</label>

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
			<div><span data-stat="total">—</span><small>total</small></div>
			<div><span data-stat="done">—</span><small>done</small></div>
			<div><span data-stat="pending">—</span><small>pending</small></div>
			<div><span data-stat="saved">—</span><small>saved</small></div>
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

<div class="velox-panel">
	<h3 class="velox-panel-title">Before / after comparator</h3>
	<p class="velox-hint">Pick a converted image to inspect quality and savings. Drag the handle to compare.</p>

	<div class="velox-compare-toolbar">
		<select id="velox-compare-select" class="velox-select"><option value="">Loading converted images…</option></select>
	</div>

	<div class="velox-compare" id="velox-compare" hidden>
		<div class="velox-compare-stage">
			<img id="velox-compare-webp" alt="WebP version" class="velox-compare-img">
			<div class="velox-compare-top" id="velox-compare-top">
				<img id="velox-compare-orig" alt="Original version" class="velox-compare-img">
			</div>
			<div class="velox-compare-handle" id="velox-compare-handle"><span></span></div>
			<span class="velox-compare-tag velox-compare-tag--l">Original</span>
			<span class="velox-compare-tag velox-compare-tag--r">WebP</span>
		</div>
		<div class="velox-compare-stats" id="velox-compare-stats"></div>
	</div>
</div>
