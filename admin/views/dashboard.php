<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var Velox_Admin $admin */
$engine = Velox_Image_Optimizer::engine();
?>
<section class="velox-hero">
	<div>
		<h1 class="velox-h1">Everything in one place.<br>Built to make your site <span class="velox-accent">faster</span>.</h1>
		<p class="velox-sub">Image optimization, media management and page-speed tuning — designed to sit on top of Oxygen, WP Fastest Cache and Cloudflare without breaking a thing.</p>
	</div>
	<div class="velox-hero-badge">
		<?php if ( $engine ) : ?>
			<span class="velox-pill velox-pill--ok"><?php echo Velox_Admin::icon( 'check', 16 ); ?> WebP engine: <?php echo esc_html( strtoupper( $engine ) ); ?></span>
		<?php else : ?>
			<span class="velox-pill velox-pill--warn">No WebP engine detected</span>
		<?php endif; ?>
	</div>
</section>

<div class="velox-stats" id="velox-dash-stats">
	<div class="velox-stat"><span class="velox-stat-num" data-stat="total">—</span><span class="velox-stat-label">Images</span></div>
	<div class="velox-stat"><span class="velox-stat-num" data-stat="done">—</span><span class="velox-stat-label">Optimized</span></div>
	<div class="velox-stat"><span class="velox-stat-num" data-stat="pending">—</span><span class="velox-stat-label">Pending</span></div>
	<div class="velox-stat"><span class="velox-stat-num" data-stat="saved">—</span><span class="velox-stat-label">Space saved</span></div>
</div>

<h2 class="velox-section-title">Tools</h2>
<div class="velox-cards">

	<a class="velox-card" href="<?php echo esc_url( $admin->tab_url( 'images' ) ); ?>">
		<span class="velox-card-ic"><?php echo Velox_Admin::icon( 'image', 24 ); ?></span>
		<h3>Image Optimization</h3>
		<p>Bulk-convert JPG &amp; PNG to WebP at a quality you choose, with a live before/after comparator.</p>
		<span class="velox-card-go">Open →</span>
	</a>

	<a class="velox-card" href="<?php echo esc_url( $admin->tab_url( 'media' ) ); ?>">
		<span class="velox-card-ic"><?php echo Velox_Admin::icon( 'tag', 24 ); ?></span>
		<h3>Media Editor</h3>
		<p>Rename files safely, set alt text &amp; titles in a grid, or bulk-apply with the pipe format.</p>
		<span class="velox-card-go">Open →</span>
	</a>

	<a class="velox-card" href="<?php echo esc_url( $admin->tab_url( 'performance' ) ); ?>">
		<span class="velox-card-ic"><?php echo Velox_Admin::icon( 'bolt', 24 ); ?></span>
		<h3>Performance</h3>
		<p>Safe, Oxygen-aware head cleanup, defer, DNS-prefetch and more — no conflict with your cache plugin.</p>
		<span class="velox-card-go">Open →</span>
	</a>

	<a class="velox-card" href="<?php echo esc_url( $admin->tab_url( 'database' ) ); ?>">
		<span class="velox-card-ic"><?php echo Velox_Admin::icon( 'db', 24 ); ?></span>
		<h3>Database</h3>
		<p>Clear revisions, transients and junk, then optimize tables to keep queries quick.</p>
		<span class="velox-card-go">Open →</span>
	</a>

</div>

<div class="velox-note">
	<strong>Stack-friendly by design.</strong> Velox never runs its own page cache or CSS/JS combine — those stay with WP Fastest Cache and Cloudflare. It only adds the pieces they don't cover.
</div>
