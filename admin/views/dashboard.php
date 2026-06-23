<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var Velox_Admin $admin */
$engine  = Velox_Image_Optimizer::engine();
$avif    = Velox_Image_Optimizer::avif_engine();

$v_css   = class_exists( 'Velox_CSS' ) ? Velox_CSS::learn_stats() : array( 'pages' => 0, 'built' => 0 );
$v_fonts = class_exists( 'Velox_Fonts' ) ? Velox_Fonts::status() : array( 'active' => false, 'files' => 0 );
$v_db    = class_exists( 'Velox_Database' ) ? ( new Velox_Database() )->counts() : array();
$v_dbsum = 0;
foreach ( array( 'revisions', 'auto_drafts', 'trashed_posts', 'spam_comments', 'trashed_comments', 'expired_transients', 'orphan_postmeta', 'orphan_commentmeta' ) as $k ) {
	$v_dbsum += isset( $v_db[ $k ] ) ? (int) $v_db[ $k ] : 0;
}

// ---- Optimization score: weighted checklist of high-impact tweaks ----
$g = function ( $k, $d = false ) { return Velox_Settings::get( $k, $d ); };
$checks = array(
	array( 'on' => (bool) $g( 'webp_auto_convert' ),                'w' => 3, 'area' => 'images',      'todo' => 'Convert &amp; serve images as WebP' ),
	array( 'on' => (bool) $g( 'perf_defer_scripts' ),              'w' => 2, 'area' => 'performance', 'todo' => 'Defer JavaScript so it stops blocking render' ),
	array( 'on' => (bool) $g( 'perf_delay_js' ),                   'w' => 2, 'area' => 'performance', 'todo' => 'Delay JavaScript until the visitor interacts' ),
	array( 'on' => (bool) $g( 'perf_remove_unused_css' ),          'w' => 2, 'area' => 'performance', 'todo' => 'Strip unused CSS' ),
	array( 'on' => (bool) $g( 'perf_optimize_css_delivery' ),      'w' => 2, 'area' => 'performance', 'todo' => 'Load CSS without blocking render' ),
	array( 'on' => (bool) $g( 'perf_local_fonts' ),               'w' => 1, 'area' => 'performance', 'todo' => 'Host Google Fonts locally' ),
	array( 'on' => (bool) $g( 'perf_add_image_dimensions', true ), 'w' => 1, 'area' => 'performance', 'todo' => 'Add image dimensions to cut layout shift' ),
	array( 'on' => (bool) $g( 'perf_fetchpriority_lcp', true ),    'w' => 1, 'area' => 'performance', 'todo' => 'Prioritise the largest image (LCP)' ),
	array( 'on' => (bool) $g( 'perf_lazyload_iframes', true ),     'w' => 1, 'area' => 'performance', 'todo' => 'Lazy-load iframes' ),
	array( 'on' => (bool) $g( 'perf_clean_head', true ),          'w' => 1, 'area' => 'performance', 'todo' => 'Clean the WordPress &lt;head&gt;' ),
	array( 'on' => 'off' !== $g( 'perf_speculative_loading', 'off' ), 'w' => 1, 'area' => 'performance', 'todo' => 'Preload links on hover' ),
	array( 'on' => (bool) $g( 'db_schedule_cleanup' ),            'w' => 1, 'area' => 'database',    'todo' => 'Schedule automatic database cleanups' ),
);
$max = 0; $got = 0; $on_count = 0;
foreach ( $checks as $c ) { $max += $c['w']; if ( $c['on'] ) { $got += $c['w']; $on_count++; } }
$score = $max ? (int) round( 100 * $got / $max ) : 0;
$total = count( $checks );

if ( $score >= 85 )      { $grade = 'Excellent'; $gcls = 'ok'; }
elseif ( $score >= 65 )  { $grade = 'Good';      $gcls = 'primary'; }
elseif ( $score >= 40 )  { $grade = 'Fair';      $gcls = 'warn'; }
else                     { $grade = 'Needs work'; $gcls = 'bad'; }

// Top recommendations = highest-weight items still off.
$todo = array_filter( $checks, function ( $c ) { return ! $c['on']; } );
usort( $todo, function ( $a, $b ) { return $b['w'] - $a['w']; } );
$todo = array_slice( $todo, 0, 4 );

$purge_url = wp_nonce_url( admin_url( 'admin-post.php?action=velox_cache&which=all' ), 'velox_cache_all' );
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Dashboard</h1>
	<p class="velox-sub">A quick read on your site's speed setup — and one-click actions to keep it fast.</p>
</div>

<div class="velox-dash-top">
	<div class="velox-panel velox-score">
		<div class="velox-score-ring" style="--val:<?php echo (int) $score; ?>">
			<span class="velox-score-num"><?php echo (int) $score; ?></span>
		</div>
		<div class="velox-score-meta">
			<span class="velox-pill velox-pill--<?php echo esc_attr( $gcls ); ?>"><?php echo esc_html( $grade ); ?></span>
			<p class="velox-score-line"><strong><?php echo (int) $on_count; ?></strong> of <?php echo (int) $total; ?> key optimizations active</p>
			<p class="velox-hint">Turn on more in Performance and Images to raise the score.</p>
		</div>
	</div>

	<div class="velox-panel velox-qa">
		<h3 class="velox-panel-title">Quick actions</h3>
		<div class="velox-qa-grid">
			<a class="velox-qa-btn" href="<?php echo esc_url( $purge_url ); ?>">
				<span class="velox-qa-ic"><?php echo Velox_Admin::icon( 'broom', 20 ); ?></span>Purge caches</a>
			<a class="velox-qa-btn" href="<?php echo esc_url( $admin->tab_url( 'images' ) ); ?>">
				<span class="velox-qa-ic"><?php echo Velox_Admin::icon( 'image', 20 ); ?></span>Optimize images</a>
			<a class="velox-qa-btn" href="<?php echo esc_url( $admin->tab_url( 'database' ) ); ?>">
				<span class="velox-qa-ic"><?php echo Velox_Admin::icon( 'db', 20 ); ?></span>Clean database</a>
			<a class="velox-qa-btn" href="<?php echo esc_url( $admin->tab_url( 'performance' ) ); ?>">
				<span class="velox-qa-ic"><?php echo Velox_Admin::icon( 'bolt', 20 ); ?></span>Tune performance</a>
		</div>
	</div>
</div>

<div class="velox-dash-stats">
	<div class="velox-statcard">
		<span class="velox-statcard-label">Images optimized</span>
		<span class="velox-statcard-val"><span data-dash="done">—</span><span class="velox-statcard-of">/ <span data-dash="total">—</span></span></span>
		<span class="velox-statcard-foot"><span data-dash="saved">—</span> saved · <a href="<?php echo esc_url( $admin->tab_url( 'images' ) ); ?>">Open →</a></span>
	</div>
	<div class="velox-statcard">
		<span class="velox-statcard-label">Critical CSS built</span>
		<span class="velox-statcard-val"><?php echo (int) $v_css['built']; ?><span class="velox-statcard-of">/ <?php echo (int) $v_css['pages']; ?></span></span>
		<span class="velox-statcard-foot">pages cached · <a href="<?php echo esc_url( $admin->tab_url( 'performance' ) ); ?>">Tune →</a></span>
	</div>
	<div class="velox-statcard">
		<span class="velox-statcard-label">Database cleanable</span>
		<span class="velox-statcard-val"><?php echo (int) $v_dbsum; ?></span>
		<span class="velox-statcard-foot">rows of junk · <a href="<?php echo esc_url( $admin->tab_url( 'database' ) ); ?>">Clean →</a></span>
	</div>
	<div class="velox-statcard">
		<span class="velox-statcard-label">Image engine</span>
		<span class="velox-statcard-val velox-statcard-val--sm"><?php echo esc_html( $engine ? strtoupper( $engine ) : '—' ); ?></span>
		<span class="velox-statcard-foot">
			<?php if ( $engine ) : ?>
				WebP ready<?php echo $avif ? ' · AVIF ready' : ''; ?>
			<?php else : ?>
				<span class="velox-statcard-warn">No engine detected</span>
			<?php endif; ?>
		</span>
	</div>
</div>

<div class="velox-panel">
	<h3 class="velox-panel-title">Recommendations</h3>
	<?php if ( empty( $todo ) ) : ?>
		<div class="velox-reco-empty">
			<?php echo Velox_Admin::icon( 'check', 20 ); ?>
			<span>Everything's tuned. Nice work — your key optimizations are all on.</span>
		</div>
	<?php else : ?>
		<div class="velox-reco-list">
			<?php foreach ( $todo as $c ) : ?>
				<div class="velox-reco-row">
					<span class="velox-reco-dot"></span>
					<span class="velox-reco-label"><?php echo wp_kses_post( $c['todo'] ); ?></span>
					<a class="velox-reco-go" href="<?php echo esc_url( $admin->tab_url( $c['area'] ) ); ?>">Enable →</a>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
