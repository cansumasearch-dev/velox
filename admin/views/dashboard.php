<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var Velox_Admin $admin */
if ( isset( $_GET['traffic'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	require __DIR__ . '/dashboard-traffic.php';
	return;
}
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
// "Everything in Velox" — every area + utility, for the catalog grid.
$velox_tiles = array(
	array( 'tab', 'dashboard',   'Dashboard',        'home',     'Overview & score' ),
	array( 'util','fields',      'Custom Fields',    'grid',     'Field groups' ),
	array( 'tab', 'media',       'Media Editor',     'tag',      'Alt text & files' ),
	array( 'util','svg',         'SVG Uploads',      'file',     'Safe SVG' ),
	array( 'util','duplicate',   'Duplicate Post',   'copy',     'One-click clone' ),
	array( 'tab', 'performance', 'Performance',      'bolt',     'Speed tuning' ),
	array( 'tab', 'images',      'Images',           'image',    'WebP / AVIF' ),
	array( 'tab', 'database',    'Database',         'db',       'Cleanup' ),
	array( 'util','scripts',     'Script Manager',   'code',     'Dequeue CSS/JS' ),
	array( 'util','unusedmedia', 'Unused Media',     'broom',    'Find & clean' ),
	array( 'util','redirects',   'Redirects & 404s', 'redirect', 'Manage URLs' ),
	array( 'util','snippets',    'Code Snippets',    'code',     'PHP/CSS/JS' ),
	array( 'util','cookies',     'Cookie Banner',    'cookie',   'Consent Mode' ),
	array( 'util','mail',        'Mail & Forms',     'mail',     'SMTP & forms' ),
	array( 'util','maintenance', 'Maintenance',      'cone',     'Coming-soon' ),
	array( 'util','loginurl',    'Login URL',        'lock',     'Hide wp-login' ),
	array( 'util','installer',   'Bulk Installer',   'plug',     'Plugin stacks' ),
	array( 'util','october',     'OctoberCMS',       'package',  'Theme export' ),
	array( 'util','backup',      'Backup & Restore', 'package',  'DB & files' ),
	array( 'tab', 'seo',         'SEO',              'search',   'Meta & sitemaps' ),
	array( 'tab', 'settings',    'Settings',         'gear',     'Modules & config' ),
);


?>
<div class="velox-page-head velox-dash-head">
	<div>
		<h1 class="velox-h2">Dashboard</h1>
		<p class="velox-sub">A quick read on your site&rsquo;s setup &mdash; with one-click actions to keep it fast.</p>
	</div>
	<div class="velox-dash-actions" id="velox-dash-actions">
		<a class="velox-btn velox-btn--ghost" href="<?php echo esc_url( $admin->tab_url( 'performance' ) ); ?>">Tune performance</a>
		<a class="velox-btn velox-btn--primary" href="<?php echo esc_url( $purge_url ); ?>"><?php echo Velox_Admin::icon( 'broom', 16 ); ?> Purge caches</a>
		<div class="velox-newwidget" id="velox-newwidget" hidden>
			<button type="button" class="velox-btn velox-btn--ghost" id="velox-newwidget-btn"><?php echo Velox_Admin::icon( 'check', 15 ); ?>Add widget</button>
			<div class="velox-newwidget-menu" id="velox-newwidget-menu" hidden></div>
		</div>
		<button type="button" class="velox-btn velox-btn--ghost" id="velox-dash-done" hidden>Done</button>
		<button type="button" class="velox-btn velox-btn--ghost" id="velox-dash-edit"><?php echo Velox_Admin::icon( 'grid', 15 ); ?>Edit</button>
	</div>
</div>

<?php
$velox_clashes = class_exists( 'Velox_Conflicts' ) ? Velox_Conflicts::detect() : array();
if ( ! empty( $velox_clashes ) ) :
	?>
	<div class="velox-panel velox-clash">
		<div class="velox-clash-head">
			<span class="velox-clash-ic">&#9889;</span>
			<div>
				<h3 class="velox-panel-title" style="margin:0;">Turf war detected</h3>
				<p class="velox-hint" style="margin:2px 0 0;">These active plugins overlap features Velox already handles. Two plugins doing the same job tend to fight over the same output &mdash; keep one.</p>
			</div>
		</div>
		<div class="velox-clash-list">
			<?php foreach ( $velox_clashes as $c ) : ?>
				<div class="velox-clash-item">
					<span class="velox-clash-name"><?php echo esc_html( $c['name'] ); ?></span>
					<span class="velox-pill velox-pill--warn">overlaps <?php echo wp_kses_post( $c['label'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
<?php endif; ?>

<?php
// Customizable cockpit: $dash_hidden holds the widget ids the user removed.
$dash_hidden = (array) Velox_Settings::get( 'dash_hidden', array( 'fonts' ) );
$dash_order  = (array) Velox_Settings::get( 'dash_order', array() );
$vx_wcls = function ( $id, $base ) use ( $dash_hidden ) {
	return $base . ( in_array( $id, $dash_hidden, true ) ? ' is-hidden' : '' );
};
// Per-widget edit affordances (checkbox + remove); hidden until edit mode.
$vx_wctl = '<span class="velox-w-chk"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-11"/></svg></span>'
	. '<button type="button" class="velox-w-x" aria-label="Remove this widget"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>';

// Live stats: form submissions + first-party traffic.
$v_forms = class_exists( 'Velox_Stats' ) ? Velox_Stats::form_total( 30 ) : 0;
$v_tr    = class_exists( 'Velox_Stats' ) ? Velox_Stats::traffic_summary( 7 ) : array( 'visitors' => 0, 'views' => 0, 'series' => array() );
$v_tr14  = class_exists( 'Velox_Stats' ) ? Velox_Stats::traffic_summary( 14 ) : array( 'visitors' => 0 );
$v_tr_last  = max( 0, (int) $v_tr14['visitors'] - (int) $v_tr['visitors'] );
$v_tr_trend = $v_tr_last > 0 ? (int) round( 100 * ( (int) $v_tr['visitors'] - $v_tr_last ) / $v_tr_last ) : null;
$v_tr_vals = array();
foreach ( $v_tr['series'] as $vx_pt ) { $v_tr_vals[] = (int) $vx_pt['v']; }
if ( empty( $v_tr_vals ) ) { $v_tr_vals = array( 0 ); }
$vx_max = max( 1, max( $v_tr_vals ) );
$vx_n   = count( $v_tr_vals );
$vx_pts = array();
foreach ( $v_tr_vals as $vx_i => $vx_v ) {
	$vx_x = $vx_n > 1 ? round( $vx_i * 100 / ( $vx_n - 1 ), 2 ) : 0;
	$vx_y = round( 36 - ( $vx_v / $vx_max ) * 32 - 2, 2 );
	$vx_pts[] = $vx_x . ',' . $vx_y;
}
$vx_line    = 'M' . implode( ' L', $vx_pts );
$v_tr_spark = '<svg viewBox="0 0 100 36" preserveAspectRatio="none" width="100%" height="44" style="display:block">'
	. '<defs><linearGradient id="vxspark" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2ab7f1" stop-opacity=".20"/><stop offset="1" stop-color="#2ab7f1" stop-opacity="0"/></linearGradient></defs>'
	. '<path d="' . esc_attr( $vx_line . ' L100,36 L0,36 Z' ) . '" fill="url(#vxspark)"/>'
	. '<path d="' . esc_attr( $vx_line ) . '" fill="none" stroke="#2ab7f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/></svg>';

// Date labels for the sparkline x-axis (first · middle · last).
$vx_fmt_d = function ( $d ) {
	$ts = strtotime( (string) $d );
	return $ts ? gmdate( 'M j', $ts ) : '';
};
$vx_axis = array( '', '', '' );
if ( ! empty( $v_tr['series'] ) ) {
	$vx_s          = array_values( $v_tr['series'] );
	$vx_axis[0]    = $vx_fmt_d( $vx_s[0]['d'] );
	$vx_axis[2]    = $vx_fmt_d( $vx_s[ count( $vx_s ) - 1 ]['d'] );
	$vx_axis[1]    = $vx_fmt_d( $vx_s[ intdiv( count( $vx_s ), 2 ) ]['d'] );
}
?>

<div class="velox-batchbar" id="velox-batchbar" hidden>
	<span><b id="velox-batch-count">0</b> <span id="velox-batch-word">widgets</span> selected</span>
	<span class="velox-batchbar-sp">
		<button type="button" class="velox-btn velox-btn--sm" id="velox-batch-cancel">Clear</button>
		<button type="button" class="velox-btn velox-btn--sm velox-batch-remove" id="velox-batch-remove">Remove selected</button>
	</span>
</div>

<div class="velox-cockpit" id="velox-cockpit" data-order="<?php echo esc_attr( implode( ',', $dash_order ) ); ?>">

	<div class="<?php echo esc_attr( $vx_wcls( 'perf', 'velox-w velox-w--col4' ) ); ?>" data-widget="perf" data-widget-label="Performance">
		<?php echo $vx_wctl; ?>
		<div class="velox-w-h"><?php echo Velox_Admin::icon( 'bolt', 15 ); ?>Performance</div>
		<div class="velox-w-ring">
			<div class="velox-score-ring velox-score-ring--sm" style="--val:<?php echo (int) $score; ?>"><span class="velox-score-num"><?php echo (int) $score; ?></span></div>
			<div>
				<span class="velox-pill velox-pill--<?php echo esc_attr( $gcls ); ?>"><?php echo esc_html( $grade ); ?></span>
				<p class="velox-w-sub" style="margin-top:6px;"><strong><?php echo (int) $on_count; ?></strong> of <?php echo (int) $total; ?> optimizations on</p>
			</div>
		</div>
	</div>

	<div class="<?php echo esc_attr( $vx_wcls( 'cache', 'velox-w velox-w--col4' ) ); ?>" data-widget="cache" data-widget-label="Cache">
		<?php echo $vx_wctl; ?>
		<div class="velox-w-h"><?php echo Velox_Admin::icon( 'broom', 15 ); ?>Cache</div>
		<div class="velox-w-big"><?php echo (int) $v_css['built']; ?><span class="velox-w-of">/ <?php echo (int) $v_css['pages']; ?> pages</span></div>
		<div class="velox-w-sub">Critical CSS built &amp; cached</div>
		<a class="velox-btn velox-btn--ghost velox-btn--sm velox-w-act" href="<?php echo esc_url( $purge_url ); ?>"><?php echo Velox_Admin::icon( 'broom', 15 ); ?>Purge caches</a>
	</div>

	<div class="<?php echo esc_attr( $vx_wcls( 'db', 'velox-w velox-w--col4' ) ); ?>" data-widget="db" data-widget-label="Database">
		<?php echo $vx_wctl; ?>
		<div class="velox-w-h"><?php echo Velox_Admin::icon( 'db', 15 ); ?>Database</div>
		<div class="velox-w-big"><?php echo (int) $v_dbsum; ?></div>
		<div class="velox-w-sub">rows of junk to clean out</div>
		<a class="velox-btn velox-btn--ghost velox-btn--sm velox-w-act" href="<?php echo esc_url( $admin->tab_url( 'database' ) ); ?>">Clean database</a>
	</div>

	<div class="<?php echo esc_attr( $vx_wcls( 'traffic', 'velox-w velox-w--col8' ) ); ?>" data-widget="traffic" data-widget-label="Visitors">
		<?php echo $vx_wctl; ?>
		<div class="velox-w-h"><?php echo Velox_Admin::icon( 'search', 15 ); ?>Visitors &middot; this week</div>
		<div class="velox-w-trtop">
			<span class="velox-w-big"><?php echo (int) $v_tr['visitors']; ?></span>
			<span class="velox-w-sub"><?php echo (int) $v_tr['views']; ?> views<?php if ( null !== $v_tr_trend ) : ?> &middot; <span class="<?php echo $v_tr_trend >= 0 ? 'velox-up' : 'velox-down'; ?>"><?php echo ( $v_tr_trend >= 0 ? '&#9650; ' : '&#9660; ' ) . abs( (int) $v_tr_trend ) . '%'; ?></span> vs last week<?php endif; ?></span>
		</div>
		<div class="velox-spark-wrap">
			<div class="velox-spark-y"><span><?php echo (int) $vx_max; ?></span><span><?php echo (int) round( $vx_max / 2 ); ?></span><span>0</span></div>
			<div class="velox-spark"><?php echo $v_tr_spark; ?></div>
		</div>
		<div class="velox-spark-axis"><span><?php echo esc_html( $vx_axis[0] ); ?></span><span><?php echo esc_html( $vx_axis[1] ); ?></span><span><?php echo esc_html( $vx_axis[2] ); ?></span></div>
		<a class="velox-btn velox-btn--ghost velox-btn--sm velox-w-act" href="<?php echo esc_url( $admin->tab_url( 'dashboard' ) . '&traffic=1' ); ?>">View traffic</a>
	</div>

	<div class="<?php echo esc_attr( $vx_wcls( 'forms', 'velox-w velox-w--col4' ) ); ?>" data-widget="forms" data-widget-label="Form submissions">
		<?php echo $vx_wctl; ?>
		<div class="velox-w-h"><?php echo Velox_Admin::icon( 'mail', 15 ); ?>Form submissions</div>
		<div class="velox-w-big"><?php echo (int) $v_forms; ?></div>
		<div class="velox-w-sub">in the last 30 days</div>
		<a class="velox-btn velox-btn--ghost velox-btn--sm velox-w-act" href="<?php echo esc_url( Velox_Utilities::tool_url( 'mail' ) ); ?>">Open Mail &amp; Forms</a>
	</div>

	<div class="<?php echo esc_attr( $vx_wcls( 'images', 'velox-w velox-w--col4' ) ); ?>" data-widget="images" data-widget-label="Images">
		<?php echo $vx_wctl; ?>
		<div class="velox-w-h"><?php echo Velox_Admin::icon( 'image', 15 ); ?>Images</div>
		<div class="velox-w-big"><span data-dash="done">&mdash;</span><span class="velox-w-of">/ <span data-dash="total">&mdash;</span></span></div>
		<div class="velox-w-sub"><span data-dash="saved">&mdash;</span> saved &middot; engine <?php echo $engine ? esc_html( strtoupper( $engine ) ) : '&mdash;'; ?></div>
		<a class="velox-btn velox-btn--ghost velox-btn--sm velox-w-act" href="<?php echo esc_url( $admin->tab_url( 'images' ) ); ?>">Optimize images</a>
	</div>

	<div class="<?php echo esc_attr( $vx_wcls( 'reco', 'velox-w velox-w--col8' ) ); ?>" data-widget="reco" data-widget-label="Recommendations">
		<?php echo $vx_wctl; ?>
		<div class="velox-w-h"><?php echo Velox_Admin::icon( 'check', 15 ); ?>Recommendations</div>
		<?php if ( empty( $todo ) ) : ?>
			<div class="velox-reco-empty"><?php echo Velox_Admin::icon( 'check', 20 ); ?><span>Everything&rsquo;s tuned. Nice work &mdash; your key optimizations are all on.</span></div>
		<?php else : ?>
			<div class="velox-reco-list">
				<?php foreach ( $todo as $c ) : ?>
					<div class="velox-reco-row">
						<span class="velox-reco-dot"></span>
						<span class="velox-reco-label"><?php echo wp_kses_post( $c['todo'] ); ?></span>
						<a class="velox-reco-go" href="<?php echo esc_url( $admin->tab_url( $c['area'] ) ); ?>">Enable &rarr;</a>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="<?php echo esc_attr( $vx_wcls( 'fonts', 'velox-w velox-w--col4' ) ); ?>" data-widget="fonts" data-widget-label="Local fonts">
		<?php echo $vx_wctl; ?>
		<div class="velox-w-h"><?php echo Velox_Admin::icon( 'image', 15 ); ?>Local fonts</div>
		<div class="velox-w-big"><?php echo (int) ( isset( $v_fonts['files'] ) ? $v_fonts['files'] : 0 ); ?></div>
		<div class="velox-w-sub"><?php echo ! empty( $v_fonts['active'] ) ? 'self-hosted &middot; active' : 'using Google CDN'; ?></div>
		<a class="velox-btn velox-btn--ghost velox-btn--sm velox-w-act" href="<?php echo esc_url( $admin->tab_url( 'performance' ) ); ?>">Manage fonts</a>
	</div>

</div>

<div class="velox-dash-sec"><h2>Everything in Velox</h2><span class="velox-dash-sec-line"></span></div>
<div class="velox-cat">
	<?php
	foreach ( $velox_tiles as $vt ) {
		list( $vk, $vid, $vlabel, $vicon, $vsub ) = $vt;
		$vurl = ( 'tab' === $vk ) ? $admin->tab_url( $vid ) : Velox_Utilities::tool_url( $vid );
		printf(
			'<a class="velox-tile" href="%s"><span class="velox-tile-ic">%s</span><span class="velox-tile-tx"><span class="velox-tile-name">%s</span><span class="velox-tile-sub">%s</span></span></a>',
			esc_url( $vurl ),
			Velox_Admin::icon( $vicon, 18 ),
			esc_html( $vlabel ),
			esc_html( $vsub )
		);
	}
	?>
</div>
