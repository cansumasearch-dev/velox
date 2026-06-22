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

<?php
$v_css   = class_exists( 'Velox_CSS' ) ? Velox_CSS::learn_stats() : array( 'pages' => 0, 'built' => 0 );
$v_fonts = class_exists( 'Velox_Fonts' ) ? Velox_Fonts::status() : array( 'active' => false );
$v_db    = class_exists( 'Velox_Database' ) ? ( new Velox_Database() )->counts() : array();
$v_dbsum = 0;
foreach ( array( 'revisions', 'auto_drafts', 'trashed_posts', 'spam_comments', 'trashed_comments', 'expired_transients', 'orphan_postmeta', 'orphan_commentmeta' ) as $k ) {
	$v_dbsum += isset( $v_db[ $k ] ) ? (int) $v_db[ $k ] : 0;
}
?>
<div class="velox-stats">
	<div class="velox-stat">
		<span class="velox-stat-num"><?php echo (int) $v_css['built']; ?><span style="font-size:.5em;opacity:.5">/<?php echo (int) $v_css['pages']; ?></span></span>
		<span class="velox-stat-label">CSS pages optimized</span>
	</div>
	<div class="velox-stat">
		<span class="velox-stat-num"><?php echo ! empty( $v_fonts['active'] ) ? (int) $v_fonts['files'] : '0'; ?></span>
		<span class="velox-stat-label">Fonts hosted locally</span>
	</div>
	<div class="velox-stat">
		<span class="velox-stat-num"><?php echo (int) $v_dbsum; ?></span>
		<span class="velox-stat-label">DB rows cleanable</span>
	</div>
	<div class="velox-stat">
		<span class="velox-stat-num"><?php echo esc_html( strtoupper( $engine ? $engine : '—' ) ); ?></span>
		<span class="velox-stat-label">WebP engine</span>
	</div>
</div>

<h2 class="velox-section-title">Tools</h2>
<?php
$card_copy = array(
	'images'      => 'Bulk-convert JPG &amp; PNG to WebP, browse your whole library with filters, and rename files safely.',
	'media'       => 'Set alt text &amp; titles in a grid, or bulk-apply everything with the pipe format.',
	'performance' => 'Oxygen-aware head cleanup, defer &amp; delay JS, font and preload tuning — no conflict with your cache plugin.',
	'database'    => 'Clear revisions, transients and junk, then optimize tables to keep queries quick.',
	'settings'    => 'Turn modules on or off, set image defaults, and manage the GitHub auto-updater.',
);
?>
<div class="velox-cards">
	<?php foreach ( $admin->enabled_tabs() as $key => $tab ) : ?>
		<?php if ( 'dashboard' === $key ) { continue; } ?>
		<a class="velox-card" href="<?php echo esc_url( $admin->tab_url( $key ) ); ?>">
			<span class="velox-card-ic"><?php echo Velox_Admin::icon( $tab['icon'], 24 ); ?></span>
			<h3><?php echo esc_html( $tab['label'] ); ?></h3>
			<p><?php echo wp_kses_post( $card_copy[ $key ] ?? '' ); ?></p>
			<span class="velox-card-go">Open →</span>
		</a>
	<?php endforeach; ?>
</div>

<div class="velox-note">
	<strong>Stack-friendly by design.</strong> Velox never runs its own page cache or CSS/JS combine — those stay with WP Fastest Cache and Cloudflare. It only adds the pieces they don't cover.
</div>
