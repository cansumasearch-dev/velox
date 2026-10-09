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
		<a class="velox-back" href="<?php echo esc_url( $back_url ); ?>"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg> <?php esc_html_e('Images', 'velox'); ?></a>
		<h1 class="velox-h2"><?php esc_html_e('Converted images', 'velox'); ?></h1>
		<p class="velox-sub"><?php echo count( $converted ); ?> image<?php echo 1 === count( $converted ) ? '' : 's'; ?> converted to WebP<?php echo $total_saved > 0 ? ' &middot; ' . esc_html( size_format( $total_saved, 1 ) ) . ' saved' : ''; ?>.</p>
	</div>

	<?php if ( empty( $converted ) ) : ?>
		<div class="velox-panel">
			<p class="velox-hint" style="margin:0 0 12px;"><?php esc_html_e('Nothing converted yet. Run a bulk optimization to fill this up.', 'velox'); ?></p>
			<a class="velox-btn velox-btn--primary" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e('Go to the optimizer', 'velox'); ?></a>
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
<?php
/* -------------------------------------------------------------------------
 * Large-images screen (?view=large) — every image above a size limit, with a
 * one-off re-convert (its own width/quality/format; saved settings untouched).
 * The list is loaded and processed by initLargeImages() in velox-admin.js.
 * ---------------------------------------------------------------------- */
if ( 'large' === $velox_view ) :
	$back_url = admin_url( 'admin.php?page=velox-images' );
	?>
	<div class="velox-page-head velox-page-head--back">
		<a class="velox-back" href="<?php echo esc_url( $back_url ); ?>"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg> <?php esc_html_e('Images', 'velox'); ?></a>
		<h1 class="velox-h2"><?php esc_html_e('Large images', 'velox'); ?></h1>
		<p class="velox-sub"><?php esc_html_e('Every image above the limit you set — 1001 counts when the limit is 1000. Re-convert them smaller with the settings below; your saved Images settings stay as they are.', 'velox'); ?></p>
	</div>

	<?php if ( ! $engine ) : ?>
		<div class="velox-alert velox-alert--warn"><?php esc_html_e('No image engine (Imagick or GD with WebP) was found on this server, so images can be listed but not re-converted.', 'velox'); ?></div>
	<?php endif; ?>

	<div class="velox-panel vxlg-setup" id="velox-large" data-engine="<?php echo $engine ? '1' : '0'; ?>">
		<div class="vxlg-find">
			<span class="velox-field-label"><?php esc_html_e('Show images', 'velox'); ?></span>
			<div class="vxlg-find-row">
				<select class="velox-select" id="vxlg-by">
					<option value="width"><?php esc_html_e('wider than', 'velox'); ?></option>
					<option value="height"><?php esc_html_e('taller than', 'velox'); ?></option>
					<option value="kb"><?php esc_html_e('bigger than', 'velox'); ?></option>
				</select>
				<input type="number" class="velox-input velox-input--sm" id="vxlg-min" value="1000" min="0" step="1" aria-label="<?php esc_attr_e( 'Limit', 'velox' ); ?>">
				<span class="vxlg-unit" id="vxlg-unit">px</span>
				<button type="button" class="velox-btn velox-btn--ghost" id="vxlg-find"><?php esc_html_e('Find', 'velox'); ?></button>
			</div>
		</div>

		<div class="vxlg-opts">
			<span class="velox-field-label"><?php esc_html_e('Re-convert with', 'velox'); ?></span>
			<div class="vxlg-opts-grid">
				<label class="vxlg-opt">
					<span class="vxlg-opt-k"><?php esc_html_e('Max width', 'velox'); ?></span>
					<span class="vxlg-opt-v"><input type="number" class="velox-input velox-input--sm" id="vxlg-maxw" value="1000" min="0" step="10"> <span class="vxlg-unit">px</span></span>
					<span class="vxlg-opt-h"><?php esc_html_e('Height follows. 0 keeps the size.', 'velox'); ?></span>
				</label>
				<label class="vxlg-opt">
					<span class="vxlg-opt-k"><?php esc_html_e('Quality', 'velox'); ?></span>
					<span class="vxlg-opt-v"><input type="number" class="velox-input velox-input--sm" id="vxlg-quality" value="<?php echo esc_attr( $quality ); ?>" min="1" max="100" step="1"> <span class="vxlg-unit">%</span></span>
					<span class="vxlg-opt-h"><?php esc_html_e('75–85 looks identical for photos.', 'velox'); ?></span>
				</label>
				<div class="vxlg-opt">
					<span class="vxlg-opt-k"><?php esc_html_e('Format', 'velox'); ?></span>
					<span class="vxck-seg" role="group" aria-label="<?php esc_attr_e( 'Format', 'velox' ); ?>">
						<button type="button" class="vxck-seg-btn<?php echo empty( $s['image_avif'] ) ? ' is-active' : ''; ?>" data-vxlg-format="webp"><?php esc_html_e('WebP', 'velox'); ?></button>
						<button type="button" class="vxck-seg-btn<?php echo ! empty( $s['image_avif'] ) ? ' is-active' : ''; ?>" data-vxlg-format="avif" <?php disabled( ! $avif_engine ); ?> title="<?php echo $avif_engine ? '' : esc_attr__( 'This server cannot create AVIF files.', 'velox' ); ?>"><?php esc_html_e('WebP + AVIF', 'velox'); ?></button>
					</span>
				</div>
			</div>
			<div class="vxlg-toggles">
				<label class="vxlg-toggle"><span class="velox-switch"><input type="checkbox" id="vxlg-replace" <?php checked( ! empty( $s['image_replace'] ) ); ?>><span class="velox-switch-track"></span></span><?php esc_html_e('Replace in media library', 'velox'); ?></label>
				<label class="vxlg-toggle"><span class="velox-switch"><input type="checkbox" id="vxlg-lossless" <?php checked( ! empty( $s['image_lossless'] ) ); ?>><span class="velox-switch-track"></span></span><?php esc_html_e('Lossless', 'velox'); ?></label>
				<label class="vxlg-toggle"><span class="velox-switch"><input type="checkbox" id="vxlg-exif" <?php checked( ! empty( $s['image_keep_exif'] ) ); ?>><span class="velox-switch-track"></span></span><?php esc_html_e('Keep EXIF', 'velox'); ?></label>
			</div>
			<p class="velox-hint vxlg-orig-hint"><?php esc_html_e('Originals: a JPG or PNG is always kept, and every image wider than 1000 px always has a full-size original saved — re-convert builds from it. Smaller WebPs are not backed up.', 'velox'); ?></p>
		</div>
	</div>

	<div class="velox-panel vxlg-results">
		<div class="vxlg-bar">
			<label class="vxlg-all"><input type="checkbox" id="vxlg-all" aria-label="<?php esc_attr_e( 'Select all', 'velox' ); ?>"> <span id="vxlg-count" class="vxlg-count">—</span></label>
			<div class="vxlg-bar-acts">
				<button type="button" class="velox-btn velox-btn--ghost" id="vxlg-stop" hidden><?php esc_html_e('Stop', 'velox'); ?></button>
				<button type="button" class="velox-btn velox-btn--primary" id="vxlg-run" disabled><?php esc_html_e('Re-convert selected', 'velox'); ?></button>
			</div>
		</div>
		<div class="velox-progress-wrap" id="vxlg-progress" hidden>
			<div class="velox-progress"><div class="velox-progress-bar" id="vxlg-progress-bar"></div></div>
			<span class="velox-progress-text" id="vxlg-progress-text">0 / 0</span>
		</div>
		<div class="vxlg-list" id="vxlg-list" aria-live="polite">
			<div class="velox-loading"><?php esc_html_e('Looking through your library…', 'velox'); ?></div>
		</div>
	</div>
	<?php
	return;
endif;
?>
<div class="velox-page-head">
	<h1 class="velox-h2"><?php esc_html_e('Images', 'velox'); ?></h1>
	<p class="velox-sub"><?php esc_html_e('Your image optimization center — pick formats and quality, then convert your whole library. With replace mode on, images become WebP right in your media library; the resize width sets a max (height follows automatically, smaller images are left untouched).', 'velox'); ?></p>
</div>

<?php if ( ! $engine ) : ?>
	<div class="velox-alert velox-alert--warn"><?php esc_html_e('No image engine (Imagick or GD with WebP) was found on this server. Conversion is disabled until one is enabled in your PHP settings — see the compatibility list below.', 'velox'); ?></div>
<?php endif; ?>

<!-- ============ Output & engine ============ -->
<div class="velox-grid-2">
	<div class="velox-panel">
		<h3 class="velox-panel-title"><?php esc_html_e('Output formats', 'velox'); ?></h3>
		<div class="velox-toggle-row">
			<div class="velox-toggle-meta">
				<span class="velox-toggle-label"><?php esc_html_e('WebP', 'velox'); ?></span>
				<span class="velox-toggle-desc"><?php esc_html_e('The modern baseline — typically 25–35% smaller than JPG/PNG with wide browser support.', 'velox'); ?></span>
			</div>
			<label class="velox-switch"><input type="checkbox" id="velox-webp" data-setting="image_webp" <?php checked( ! empty( $s['image_webp'] ) ); ?>><span class="velox-switch-track"></span></label>
		</div>
		<div class="velox-toggle-row"<?php echo $avif_engine ? '' : ' style="opacity:.55;"'; ?>>
			<div class="velox-toggle-meta">
				<span class="velox-toggle-label">AVIF
					<?php if ( $avif_engine ) : ?>
						<span class="velox-tag velox-tag--ok"><?php esc_html_e('Supported', 'velox'); ?></span>
					<?php else : ?>
						<span class="velox-tag velox-tag--muted"><?php esc_html_e('Not on this server', 'velox'); ?></span>
					<?php endif; ?>
				</span>
				<span class="velox-toggle-desc"><?php esc_html_e('An extra AVIF twin, usually 15–30% smaller again. Capable browsers get AVIF, everyone else falls back to WebP, then the original. Slower to encode.', 'velox'); ?></span>
			</div>
			<label class="velox-switch"><input type="checkbox" id="velox-avif" data-setting="image_avif" <?php checked( ! empty( $s['image_avif'] ) ); ?> <?php disabled( ! $avif_engine ); ?>><span class="velox-switch-track"></span></label>
		</div>
		<div class="velox-toggle-row">
			<div class="velox-toggle-meta">
				<span class="velox-toggle-label"><?php esc_html_e('Replace originals with WebP', 'velox'); ?></span>
				<span class="velox-toggle-desc"><?php esc_html_e('On (recommended): the JPG/PNG becomes a WebP right in your media library, at its real smaller size. Off: keeps the original and serves a WebP copy only on the front-end.', 'velox'); ?></span>
			</div>
			<label class="velox-switch"><input type="checkbox" id="velox-replace" data-setting="image_replace" <?php checked( ! empty( $s['image_replace'] ) ); ?>><span class="velox-switch-track"></span></label>
		</div>
		<p class="velox-hint"><?php echo ! empty( $s['image_replace'] ) ? 'Replace mode is on — images become WebP in your media library. The original is kept on disk as a fallback and the front-end is rewritten to serve WebP, so nothing breaks.' : 'Your original JPG/PNG files are kept and served to browsers that don\'t support these formats.'; ?></p>
	</div>

	<div class="velox-panel">
		<h3 class="velox-panel-title"><?php esc_html_e('Conversion engine', 'velox'); ?></h3>
		<div class="velox-field">
			<span class="velox-field-label"><?php esc_html_e('Engine', 'velox'); ?></span>
			<select class="velox-select" id="velox-engine" data-setting="image_engine">
				<option value="auto" <?php selected( $s['image_engine'], 'auto' ); ?>><?php esc_html_e('Auto (recommended)', 'velox'); ?></option>
				<option value="imagick" <?php selected( $s['image_engine'], 'imagick' ); ?>><?php esc_html_e('Imagick', 'velox'); ?></option>
				<option value="gd" <?php selected( $s['image_engine'], 'gd' ); ?>><?php esc_html_e('GD', 'velox'); ?></option>
			</select>
			<span class="velox-hint"><?php esc_html_e('Auto picks the best available engine. Force one only if you have a reason to.', 'velox'); ?></span>
		</div>
		<div class="velox-engine-compat">
			<?php foreach ( $caps as $cap ) : ?>
				<div class="velox-engine-row">
					<span class="velox-engine-name"><?php echo esc_html( $cap['label'] ); ?></span>
					<span class="velox-engine-badges">
						<?php if ( $cap['available'] ) : ?>
							<span class="velox-tag velox-tag--ok"><?php esc_html_e('Available', 'velox'); ?></span>
							<span class="velox-tag <?php echo $cap['webp'] ? 'velox-tag--ok' : 'velox-tag--muted'; ?>"><?php esc_html_e('WebP', 'velox'); ?></span>
							<span class="velox-tag <?php echo $cap['avif'] ? 'velox-tag--ok' : 'velox-tag--muted'; ?>"><?php esc_html_e('AVIF', 'velox'); ?></span>
						<?php else : ?>
							<span class="velox-tag velox-tag--muted"><?php esc_html_e('Not installed', 'velox'); ?></span>
						<?php endif; ?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>

<!-- ============ Original files ============ -->
<?php
$orig_mode  = Velox_Image_Optimizer::originals_mode();
$orig_stats = Velox_Image_Optimizer::originals_stats();
$orig_zip   = wp_nonce_url( admin_url( 'admin-post.php?action=velox_originals_zip' ), 'velox_originals_zip' );
?>
<div class="velox-panel vxorig" id="velox-originals"
	data-loose="<?php echo (int) $orig_stats['loose']; ?>" data-loose-bytes="<?php echo (int) $orig_stats['loose_bytes']; ?>"
	data-archived="<?php echo (int) $orig_stats['archived']; ?>" data-archived-bytes="<?php echo (int) $orig_stats['archived_bytes']; ?>"
	data-unbacked="<?php echo (int) $orig_stats['unbacked']; ?>">
	<div class="vxorig-head">
		<div>
			<h3 class="velox-panel-title"><?php esc_html_e('Original files', 'velox'); ?></h3>
			<p class="velox-hint"><?php esc_html_e('When an image becomes WebP, Velox keeps its original JPG/PNG so nothing is ever lost — and every image wider than 1000 px always has a full-size original saved, even one that was uploaded as WebP. Choose where the originals live.', 'velox'); ?></p>
		</div>
		<span class="vxck-seg" role="radiogroup" aria-label="<?php esc_attr_e( 'Original files', 'velox' ); ?>">
			<button type="button" class="vxck-seg-btn<?php echo 'archive' !== $orig_mode ? ' is-active' : ''; ?>" data-orig-mode="keep" role="radio" aria-checked="<?php echo 'archive' !== $orig_mode ? 'true' : 'false'; ?>"><?php esc_html_e('Next to the WebP', 'velox'); ?></button>
			<button type="button" class="vxck-seg-btn<?php echo 'archive' === $orig_mode ? ' is-active' : ''; ?>" data-orig-mode="archive" role="radio" aria-checked="<?php echo 'archive' === $orig_mode ? 'true' : 'false'; ?>"><?php esc_html_e('Private backup', 'velox'); ?></button>
		</span>
	</div>
	<p class="vxorig-desc" data-for="keep"<?php echo 'archive' === $orig_mode ? ' hidden' : ''; ?>><?php esc_html_e('The original stays in the same folder as its WebP. Simple — but it still fills up your uploads folder, and anyone with the old link can open it.', 'velox'); ?></p>
	<p class="vxorig-desc" data-for="archive"<?php echo 'archive' !== $orig_mode ? ' hidden' : ''; ?>><?php esc_html_e('Originals move into a hidden backup folder that visitors can\'t open. Your site only uses the converted images, and old .jpg/.png links are sent to the WebP automatically.', 'velox'); ?></p>
	<?php if ( 'delete' === $orig_mode ) : ?>
		<div class="velox-alert velox-alert--warn" id="vxorig-legacy"><?php esc_html_e('Right now originals are deleted after conversion (an older setting). Pick one of the options above to keep them from now on.', 'velox'); ?></div>
	<?php endif; ?>
	<?php if ( empty( $s['image_replace'] ) ) : ?>
		<p class="velox-hint vxorig-note"><?php esc_html_e('This applies when “Replace originals with WebP” is on. With it off, the originals are the files your site shows, so they stay where they are.', 'velox'); ?></p>
	<?php endif; ?>
	<div class="vxorig-foot">
		<span class="vxorig-stats" id="vxorig-stats" aria-live="polite"></span>
		<div class="vxorig-acts">
			<button type="button" class="velox-btn velox-btn--primary" id="vxorig-backup" hidden></button>
			<button type="button" class="velox-btn velox-btn--primary" id="vxorig-move" hidden></button>
			<a class="velox-btn velox-btn--ghost" id="vxorig-zip" href="<?php echo esc_url( $orig_zip ); ?>"><?php esc_html_e('Download all (ZIP)', 'velox'); ?></a>
		</div>
	</div>
</div>

<!-- ============ Quality & processing ============ -->
<div class="velox-panel">
	<h3 class="velox-panel-title"><?php esc_html_e('Quality &amp; processing', 'velox'); ?></h3>
	<div class="velox-field">
		<span class="velox-field-label"><?php esc_html_e('Quality', 'velox'); ?> <em id="velox-q-val"><?php echo esc_html( $quality ); ?>%</em></span>
		<div class="velox-quality-row">
			<input type="range" id="velox-quality" min="40" max="100" step="1" value="<?php echo esc_attr( $quality ); ?>" class="velox-range">
			<div class="velox-quality-num">
				<input type="number" id="velox-quality-num" class="velox-input velox-input--xs" min="1" max="100" step="1" value="<?php echo esc_attr( $quality ); ?>" aria-label="Quality value">
				<span class="velox-quality-pct">%</span>
			</div>
		</div>
		<span class="velox-hint"><?php esc_html_e('Drag the slider or type an exact value. 80% is a good balance; lossless mode below ignores this.', 'velox'); ?></span>
	</div>
	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label"><?php esc_html_e('Lossless WebP', 'velox'); ?> <span class="velox-tag velox-tag--muted"><?php esc_html_e('Imagick', 'velox'); ?></span></span>
			<span class="velox-toggle-desc"><?php esc_html_e('Perfect quality with no compression loss — larger files. Great for graphics and screenshots, overkill for photos.', 'velox'); ?></span>
		</div>
		<label class="velox-switch"><input type="checkbox" id="velox-lossless" data-setting="image_lossless" <?php checked( ! empty( $s['image_lossless'] ) ); ?>><span class="velox-switch-track"></span></label>
	</div>
	<div class="velox-field">
		<span class="velox-field-label"><?php esc_html_e('Resize width (px)', 'velox'); ?></span>
		<input type="number" class="velox-input velox-input--sm" id="velox-max-width" data-setting="image_max_width" value="<?php echo esc_attr( (int) $s['image_max_width'] ); ?>" min="0" step="10">
		<span class="velox-hint"><?php esc_html_e('Images wider than this are scaled down to it; the height follows automatically to keep the aspect ratio. Images already narrower are left at their own size (never upscaled). 0 = never resize.', 'velox'); ?></span>
	</div>
	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label"><?php esc_html_e('Preserve EXIF metadata', 'velox'); ?></span>
			<span class="velox-toggle-desc"><?php esc_html_e('Off (default) strips camera, date and GPS data for smaller, more private files. On keeps it.', 'velox'); ?></span>
		</div>
		<label class="velox-switch"><input type="checkbox" id="velox-keep-exif" data-setting="image_keep_exif" <?php checked( ! empty( $s['image_keep_exif'] ) ); ?>><span class="velox-switch-track"></span></label>
	</div>
</div>

<!-- ============ Bulk optimization ============ -->
<div class="velox-grid-2">
	<div class="velox-panel">
		<h3 class="velox-panel-title"><?php esc_html_e('Bulk optimization', 'velox'); ?></h3>
		<p class="velox-hint"><?php esc_html_e('Convert every JPG and PNG in your library to the formats selected above. Safe to stop and resume anytime.', 'velox'); ?></p>
		<div class="velox-progress-wrap" id="velox-bulk-progress" hidden>
			<div class="velox-progress"><div class="velox-progress-bar" id="velox-bulk-bar"></div></div>
			<span class="velox-progress-text" id="velox-bulk-text">0 / 0</span>
		</div>
		<div class="velox-actions">
			<button class="velox-btn velox-btn--primary" id="velox-bulk-start" <?php disabled( ! $engine ); ?>><?php esc_html_e('Convert pending images', 'velox'); ?></button>
			<button class="velox-btn velox-btn--ghost" id="velox-bulk-stop" hidden><?php esc_html_e('Stop', 'velox'); ?></button>
			<a class="velox-btn velox-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=velox-images&view=converted' ) ); ?>"><?php esc_html_e('View converted images →', 'velox'); ?></a>
			<a class="velox-btn velox-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=velox-images&view=large' ) ); ?>"><?php esc_html_e('Find large images →', 'velox'); ?></a>
		</div>
		<p class="velox-hint" id="velox-bulk-summary"></p>
	</div>

	<div class="velox-panel">
		<h3 class="velox-panel-title"><?php esc_html_e('Library', 'velox'); ?></h3>
		<div class="velox-mini-stats" id="velox-img-stats">
			<div><span data-mini="done">—</span><small><?php esc_html_e('Optimized', 'velox'); ?></small></div>
			<div><span data-mini="pending">—</span><small><?php esc_html_e('Pending', 'velox'); ?></small></div>
			<div><span data-mini="saved">—</span><small><?php esc_html_e('Saved', 'velox'); ?></small></div>
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
			<button type="button" class="velox-chip is-active" data-filter="all"><?php esc_html_e('All', 'velox'); ?></button>
			<button type="button" class="velox-chip" data-filter="jpg"><?php esc_html_e('JPG', 'velox'); ?></button>
			<button type="button" class="velox-chip" data-filter="png"><?php esc_html_e('PNG', 'velox'); ?></button>
			<button type="button" class="velox-chip" data-filter="webp"><?php esc_html_e('WebP', 'velox'); ?></button>
			<button type="button" class="velox-chip" data-filter="gif"><?php esc_html_e('GIF', 'velox'); ?></button>
			<button type="button" class="velox-chip" data-filter="svg"><?php esc_html_e('SVG', 'velox'); ?></button>
		</div>
		<input type="search" id="velox-lib-search" class="velox-input" placeholder="Search filename or title…">
		<div class="velox-lib-toolbar-right">
			<button class="velox-btn velox-btn--ghost" id="velox-lib-bulk"><?php esc_html_e('Find &amp; replace names', 'velox'); ?></button>
			<button class="velox-btn velox-btn--primary" id="velox-lib-apply-all" hidden><?php esc_html_e('Apply all names', 'velox'); ?></button>
		</div>
	</div>

	<div class="velox-lib-grid" id="velox-lib-grid"><div class="velox-loading"><?php esc_html_e('Loading images…', 'velox'); ?></div></div>

	<div class="velox-pager">
		<button class="velox-btn velox-btn--ghost" id="velox-lib-prev" disabled><?php esc_html_e('← Prev', 'velox'); ?></button>
		<span id="velox-lib-pageinfo" class="velox-hint">—</span>
		<button class="velox-btn velox-btn--ghost" id="velox-lib-next" disabled><?php esc_html_e('Next →', 'velox'); ?></button>
	</div>
	<p class="velox-hint"><?php esc_html_e('Typed names are saved in your browser — reload safely, they\'ll still be here until you apply them. Renaming updates every reference in posts and Oxygen automatically.', 'velox'); ?></p>
</div>

<?php if ( $show_cmp ) : ?>
<!-- ============ Comparator ============ -->
<div class="velox-panel">
	<h3 class="velox-panel-title"><?php esc_html_e('Before / after', 'velox'); ?></h3>
	<div class="velox-compare-toolbar">
		<select id="velox-compare-select" class="velox-select"><option value=""><?php esc_html_e('Loading optimized images…', 'velox'); ?></option></select>
	</div>
	<div class="velox-compare" id="velox-compare" hidden>
		<div class="velox-compare-stage" id="velox-compare-stage">
			<img id="velox-compare-webp" alt="WebP version" class="velox-compare-img">
			<div class="velox-compare-top" id="velox-compare-top">
				<img id="velox-compare-orig" alt="Original version" class="velox-compare-img">
			</div>
			<span class="velox-compare-tag velox-compare-tag--l"><?php esc_html_e('Original', 'velox'); ?></span>
			<span class="velox-compare-tag velox-compare-tag--r"><?php esc_html_e('WebP', 'velox'); ?></span>
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
