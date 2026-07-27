<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var Velox_Admin $admin */

$ps_on        = class_exists( 'Velox_Pagespeed' ) && Velox_Pagespeed::enabled();
$ps_def       = class_exists( 'Velox_Pagespeed' ) ? Velox_Pagespeed::strategy() : 'mobile';
$ps_metrics   = (bool) Velox_Settings::get( 'ps_show_metrics', true );
$ps_has_any   = class_exists( 'Velox_Pagespeed' ) && Velox_Pagespeed::has_any();
$ps_url       = class_exists( 'Velox_Pagespeed' ) ? Velox_Pagespeed::target_url() : home_url( '/' );
$ps_data      = array(
	'mobile'  => class_exists( 'Velox_Pagespeed' ) ? Velox_Pagespeed::result( 'mobile' ) : array( 'ok' => false ),
	'desktop' => class_exists( 'Velox_Pagespeed' ) ? Velox_Pagespeed::result( 'desktop' ) : array( 'ok' => false ),
);
$ps_insights  = 'https://pagespeed.web.dev/analysis?url=' . rawurlencode( $ps_url );

/**
 * One device's report, modelled on Google's PageSpeed Insights page but laid out
 * as separated cards: an overview card of category gauges, a Metrics card, then
 * one card per category with its shape-coded diagnostics.
 */
$vx_psf_panel = function ( $device, $r, $active ) use ( $admin, $ps_metrics ) {
	$has = ! empty( $r['ok'] ) && isset( $r['score'] );

	$shape = function ( $sev ) {
		if ( 'poor' === $sev ) {
			return '<span class="velox-psi-ind velox-psi-ind--poor" aria-hidden="true"><svg viewBox="0 0 12 12" width="11" height="11"><path d="M6 1 11 10.5H1z"/></svg></span>';
		}
		if ( 'avg' === $sev ) {
			return '<span class="velox-psi-ind velox-psi-ind--avg" aria-hidden="true"><svg viewBox="0 0 12 12" width="10" height="10"><rect x="1" y="1" width="10" height="10" rx="1.5"/></svg></span>';
		}
		if ( 'pass' === $sev ) {
			return '<span class="velox-psi-ind velox-psi-ind--pass" aria-hidden="true"><svg viewBox="0 0 12 12" width="11" height="11"><circle cx="6" cy="6" r="5"/></svg></span>';
		}
		return '<span class="velox-psi-ind velox-psi-ind--na" aria-hidden="true"><svg viewBox="0 0 12 12" width="11" height="11" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="6" cy="6" r="4.6"/></svg></span>';
	};
	$sev_of = function ( $score ) {
		if ( null === $score ) { return 'na'; }
		if ( $score >= 0.9 ) { return 'pass'; }
		if ( $score >= 0.5 ) { return 'avg'; }
		return 'poor';
	};
	$row = function ( $it ) use ( $shape ) {
		$hb  = '' !== $it['desc'];
		$out = '<div class="velox-psi-row">';
		$out .= '<button type="button" class="velox-psi-rowh" data-psf-acc aria-expanded="false"' . ( $hb ? '' : ' disabled' ) . '>';
		$out .= $shape( $it['sev'] );
		$out .= '<span class="velox-psi-rowt">' . esc_html( $it['title'] ) . '</span>';
		if ( ! empty( $it['display'] ) ) {
			$cls = in_array( $it['sev'], array( 'poor', 'avg' ), true ) ? ' velox-psi-rowv--warn' : '';
			$out .= '<span class="velox-psi-rowv' . $cls . '">' . esc_html( $it['display'] ) . '</span>';
		}
		if ( $hb ) {
			$out .= '<svg class="velox-psi-chev" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
		}
		$out .= '</button>';
		if ( $hb ) {
			$out .= '<div class="velox-psi-rowb" data-psf-body hidden><p>' . esc_html( $it['desc'] ) . '</p></div>';
		}
		return $out . '</div>';
	};

	ob_start();
	?>
	<div class="velox-psf-panel<?php echo $active ? ' is-active' : ''; ?>" data-ps-panel="<?php echo esc_attr( $device ); ?>"<?php echo $active ? '' : ' hidden'; ?>>
		<?php if ( ! $has ) : ?>
			<div class="velox-panel velox-psf-empty">
				<p class="velox-hint" style="margin:0 0 12px;"><?php echo ! empty( $r['error'] ) ? esc_html( $r['error'] ) : 'No ' . esc_html( $device ) . ' report yet — run the first check.'; ?></p>
				<button type="button" class="velox-btn velox-btn--primary" data-ps-refresh><?php esc_html_e('Run check now', 'velox'); ?></button>
			</div>
		<?php else : ?>
			<?php
			list( $p_grade, $p_gcls ) = Velox_Pagespeed::grade( (int) $r['score'] );
			$ago       = ! empty( $r['fetched'] ) ? human_time_diff( (int) $r['fetched'], current_time( 'timestamp' ) ) : '';
			$total_fix = 0;
			foreach ( (array) $r['categories'] as $cat ) { $total_fix += (int) $cat['fails']; }
			?>
			<div class="velox-psi-stack">

				<!-- Overview card -->
				<section class="velox-panel velox-psi-card velox-psi-overview">
					<div class="velox-psi-overhead">
						<div>
							<h3 class="velox-psi-cardh"><?php esc_html_e('Overview', 'velox'); ?></h3>
							<p class="velox-psi-overline">
								<?php if ( $total_fix > 0 ) : ?><strong><?php echo (int) $total_fix; ?></strong> <?php echo esc_html( 1 === $total_fix ? __( 'issue', 'velox' ) : __( 'issues', 'velox' ) ); ?> <?php printf( esc_html__( 'to fix on %s', 'velox' ), esc_html( $device ) ); ?><?php else : ?><?php printf( esc_html__( 'No issues found on %s', 'velox' ), esc_html( $device ) ); ?><?php endif; ?>
							</p>
						</div>
						<?php if ( $ago ) : ?><span class="velox-psi-stamp"><?php printf( esc_html__( 'Checked %s ago', 'velox' ), esc_html( $ago ) ); ?></span><?php endif; ?>
					</div>
					<div class="velox-psi-gauges">
						<?php
						foreach ( (array) $r['categories'] as $cat ) :
							$cs = isset( $cat['score'] ) && null !== $cat['score'] ? (int) $cat['score'] : null;
							$cc = null !== $cs ? Velox_Pagespeed::grade( $cs )[1] : 'warn';
							?>
							<a class="velox-psi-gauge" href="#psf-<?php echo esc_attr( $cat['id'] ); ?>">
								<div class="velox-score-ring velox-score-ring--sm velox-score-ring--<?php echo esc_attr( $cc ); ?>" style="--val:<?php echo (int) $cs; ?>"><span class="velox-score-num"><?php echo null !== $cs ? (int) $cs : '&mdash;'; ?></span></div>
								<span class="velox-psi-gauge-label"><?php echo esc_html( $cat['label'] ); ?></span>
								<span class="velox-psi-gauge-sub"><?php echo (int) $cat['fails'] > 0 ? (int) $cat['fails'] . ' to fix' : 'all good'; ?></span>
							</a>
						<?php endforeach; ?>
					</div>
					<div class="velox-psi-legend">
						<span><i class="velox-psi-key velox-psi-key--poor"></i>0&ndash;49</span>
						<span><i class="velox-psi-key velox-psi-key--avg"></i>50&ndash;89</span>
						<span><i class="velox-psi-key velox-psi-key--pass"></i>90&ndash;100</span>
					</div>
				</section>

				<?php if ( $ps_metrics && ! empty( $r['metrics'] ) ) : ?>
					<!-- Metrics card -->
					<section class="velox-panel velox-psi-card">
						<h3 class="velox-psi-cardh"><?php esc_html_e('Metrics', 'velox'); ?></h3>
						<div class="velox-psi-metrics">
							<?php foreach ( $r['metrics'] as $m ) : $sv = $sev_of( isset( $m['score'] ) ? $m['score'] : null ); ?>
								<div class="velox-psi-metric">
									<?php echo $shape( $sv ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<span class="velox-psi-metric-k"><?php echo esc_html( $m['key'] ); ?></span>
									<span class="velox-psi-metric-v"><?php echo esc_html( $m['value'] ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>

				<?php
				foreach ( (array) $r['audits'] as $cat_id => $list ) :
					if ( empty( $list ) ) { continue; }
					$cat_meta = array();
					foreach ( (array) $r['categories'] as $cm ) {
						if ( $cm['id'] === $cat_id ) { $cat_meta = $cm; break; }
					}
					$clabel = ! empty( $cat_meta['label'] ) ? $cat_meta['label'] : ucfirst( $cat_id );
					$cs     = isset( $cat_meta['score'] ) && null !== $cat_meta['score'] ? (int) $cat_meta['score'] : null;
					$ccls   = null !== $cs ? Velox_Pagespeed::grade( $cs )[1] : 'warn';
					$fails  = array();
					$rest   = array();
					foreach ( $list as $it ) {
						if ( 'fail' === $it['state'] ) { $fails[] = $it; } else { $rest[] = $it; }
					}
					?>
					<!-- Category card -->
					<section class="velox-panel velox-psi-card velox-psi-cat" id="psf-<?php echo esc_attr( $cat_id ); ?>">
						<div class="velox-psi-cathead">
							<span class="velox-psi-catbadge velox-psi-catbadge--<?php echo esc_attr( $ccls ); ?>"><?php echo null !== $cs ? (int) $cs : '&mdash;'; ?></span>
							<h3 class="velox-psi-cardh"><?php echo esc_html( $clabel ); ?></h3>
							<span class="velox-psi-catmeta"><?php echo count( $fails ) > 0 ? (int) count( $fails ) . ' to fix' : 'no issues'; ?></span>
						</div>
						<?php if ( empty( $fails ) ) : ?>
							<div class="velox-psi-clear"><?php echo $shape( 'pass' ); // phpcs:ignore ?><span><?php esc_html_e('Everything in this category passed.', 'velox'); ?></span></div>
						<?php else : ?>
							<div class="velox-psi-list">
								<?php foreach ( $fails as $it ) { echo $row( $it ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $rest ) ) : ?>
							<button type="button" class="velox-psi-more" data-psf-passtoggle aria-expanded="false">
								<svg class="velox-psi-chev" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
								<span><?php printf( esc_html__( 'Passed audits (%d)', 'velox' ), (int) count( $rest ) ); ?></span>
							</button>
							<div class="velox-psi-list velox-psi-passlist" data-psf-passbody hidden>
								<?php foreach ( $rest as $it ) { echo $row( $it ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php endif; ?>
					</section>
				<?php endforeach; ?>

			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
};
?>

<div class="velox-page-head velox-page-head--row">
	<div>
		<h1 class="velox-h2"><?php esc_html_e('PageSpeed', 'velox'); ?></h1>
		<p class="velox-sub"><?php printf( esc_html__( 'The full Google PageSpeed Insights report for %s &mdash; every category, every check.', 'velox' ), '<code>' . esc_html( $ps_url ) . '</code>' ); ?></p>
	</div>
	<div class="velox-dash-actions">
		<a class="velox-btn velox-btn--ghost" href="<?php echo esc_url( $ps_insights ); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open on PageSpeed Insights &#8599;', 'velox'); ?></a>
		<?php if ( $ps_on ) : ?><button type="button" class="velox-btn velox-btn--primary" data-ps-refresh><?php esc_html_e('Refresh now', 'velox'); ?></button><?php endif; ?>
	</div>
</div>

<?php if ( ! $ps_on ) : ?>
	<div class="velox-panel velox-psf-empty">
		<p class="velox-hint" style="margin:0 0 12px;"><?php esc_html_e('Live PageSpeed is switched off. Turn it on to pull real Lighthouse reports for this site.', 'velox'); ?></p>
		<a class="velox-btn velox-btn--primary" href="<?php echo esc_url( $admin->tab_url( 'settings' ) . '#pagespeed' ); ?>"><?php esc_html_e('Turn on in Settings', 'velox'); ?></a>
	</div>
<?php elseif ( ! $ps_has_any ) : ?>
	<div class="velox-panel velox-psf-empty">
		<p class="velox-hint" style="margin:0 0 12px;"><?php esc_html_e('No report yet. Running a check tests both Mobile and Desktop across all categories &mdash; it takes about a minute.', 'velox'); ?></p>
		<button type="button" class="velox-btn velox-btn--primary" data-ps-refresh><?php esc_html_e('Run first check', 'velox'); ?></button>
	</div>
<?php else : ?>
	<div class="velox-psf" data-ps-container>
		<div class="velox-ps-seg velox-psf-seg" role="tablist" aria-label="Device">
			<button type="button" class="velox-ps-seg-btn<?php echo 'mobile' === $ps_def ? ' is-active' : ''; ?>" data-ps-view="mobile" role="tab" aria-selected="<?php echo 'mobile' === $ps_def ? 'true' : 'false'; ?>"><?php esc_html_e('Mobile', 'velox'); ?></button>
			<button type="button" class="velox-ps-seg-btn<?php echo 'desktop' === $ps_def ? ' is-active' : ''; ?>" data-ps-view="desktop" role="tab" aria-selected="<?php echo 'desktop' === $ps_def ? 'true' : 'false'; ?>"><?php esc_html_e('Desktop', 'velox'); ?></button>
		</div>
		<?php
		echo $vx_psf_panel( 'mobile', $ps_data['mobile'], 'mobile' === $ps_def );   // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $vx_psf_panel( 'desktop', $ps_data['desktop'], 'desktop' === $ps_def ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	</div>
<?php endif; ?>
