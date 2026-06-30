<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var Velox_Admin $admin */
$allowed = array( 7, 14, 30, 90 );
$range   = isset( $_GET['range'] ) ? (int) $_GET['range'] : 7; // phpcs:ignore WordPress.Security.NonceVerification
if ( ! in_array( $range, $allowed, true ) ) {
	$range = 7;
}
$tr       = class_exists( 'Velox_Stats' ) ? Velox_Stats::traffic_summary( $range ) : array( 'visitors' => 0, 'views' => 0, 'series' => array() );
$series   = isset( $tr['series'] ) ? array_values( $tr['series'] ) : array();
$visitors = (int) $tr['visitors'];
$views    = (int) $tr['views'];

$peak_u = 0;
$peak_d = '';
foreach ( $series as $pt ) {
	if ( (int) $pt['u'] >= $peak_u ) {
		$peak_u = (int) $pt['u'];
		$peak_d = $pt['d'];
	}
}
$max_u = max( 1, $peak_u );
$n     = count( $series );
$avg   = $range > 0 ? round( $visitors / $range, $visitors >= 10 * $range ? 0 : 1 ) : 0;
$base  = $admin->tab_url( 'dashboard' );

$fmt = function ( $d, $f = 'M j' ) {
	$ts = strtotime( (string) $d );
	return $ts ? gmdate( $f, $ts ) : (string) $d;
};
// Show ~8 x-axis labels max so long ranges don't crowd.
$label_every = max( 1, (int) ceil( $n / 8 ) );
?>
<div class="velox-page-head velox-page-head--row">
	<div>
		<a class="vmail-back-link" href="<?php echo esc_url( $base ); ?>">&larr; Dashboard</a>
		<h1 class="velox-h2" style="margin-top:8px;">Traffic</h1>
		<p class="velox-sub">First-party visitor counts, measured by Velox's own lightweight beacon — no third-party analytics.</p>
	</div>
	<div class="velox-tr-range">
		<?php foreach ( $allowed as $r ) : ?>
			<a class="velox-tr-range-btn<?php echo $r === $range ? ' is-on' : ''; ?>" href="<?php echo esc_url( $base . '&traffic=1&range=' . $r ); ?>"><?php echo (int) $r; ?>d</a>
		<?php endforeach; ?>
	</div>
</div>

<div class="velox-tr-cards">
	<div class="velox-tr-card"><div class="k">Visitors</div><div class="v"><?php echo number_format_i18n( $visitors ); ?></div><div class="s">last <?php echo (int) $range; ?> days</div></div>
	<div class="velox-tr-card"><div class="k">Page views</div><div class="v"><?php echo number_format_i18n( $views ); ?></div><div class="s"><?php echo $visitors > 0 ? esc_html( round( $views / max( 1, $visitors ), 1 ) ) : '0'; ?> per visitor</div></div>
	<div class="velox-tr-card"><div class="k">Peak day</div><div class="v"><?php echo number_format_i18n( $peak_u ); ?></div><div class="s"><?php echo $peak_d ? esc_html( $fmt( $peak_d ) ) : '—'; ?></div></div>
	<div class="velox-tr-card"><div class="k">Daily average</div><div class="v"><?php echo esc_html( $avg ); ?></div><div class="s">visitors / day</div></div>
</div>

<div class="velox-panel">
	<h3 class="velox-panel-title">Visitors per day</h3>
	<?php if ( 0 === $visitors ) : ?>
		<div class="velox-tr-empty">
			<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6"/><rect x="12" y="8" width="3" height="10"/><rect x="17" y="4" width="3" height="14"/></svg>
			<p>No visits recorded in this range yet.</p>
			<span class="velox-hint">Velox counts a visit the first time a real browser loads a page. Numbers will fill in as people visit your site.</span>
		</div>
	<?php else : ?>
		<div class="velox-tr-chartwrap">
			<div class="velox-tr-yaxis">
				<span><?php echo number_format_i18n( $max_u ); ?></span>
				<span><?php echo number_format_i18n( (int) round( $max_u / 2 ) ); ?></span>
				<span>0</span>
			</div>
			<div class="velox-tr-chart">
				<span class="velox-tr-grid" style="top:0"></span>
				<span class="velox-tr-grid" style="top:50%"></span>
				<span class="velox-tr-grid" style="bottom:0"></span>
				<?php foreach ( $series as $i => $pt ) :
					$u   = (int) $pt['u'];
					$v   = (int) $pt['v'];
					$pct = $max_u > 0 ? round( $u / $max_u * 100, 2 ) : 0;
					$lbl = ( 0 === $i % $label_every || $i === $n - 1 ) ? $fmt( $pt['d'] ) : '';
					?>
					<div class="velox-tr-col" title="<?php echo esc_attr( $fmt( $pt['d'], 'D, M j' ) . ' — ' . number_format_i18n( $u ) . ' visitors · ' . number_format_i18n( $v ) . ' views' ); ?>">
						<div class="velox-tr-bar<?php echo $pt['d'] === $peak_d && $peak_u > 0 ? ' is-peak' : ''; ?>" style="height:<?php echo esc_attr( max( $u > 0 ? 2 : 0, $pct ) ); ?>%"></div>
						<span class="velox-tr-xlabel"><?php echo esc_html( $lbl ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
</div>
