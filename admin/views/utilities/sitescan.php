<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$last   = class_exists( 'Velox_Site_Scan' ) ? Velox_Site_Scan::last() : array();
$levels = isset( $last['levels'] ) ? $last['levels'] : array();

$vx_kind_labels = array(
	'core_modified'     => __( 'Changed', 'velox' ),
	'core_missing'      => __( 'Missing', 'velox' ),
	'core_unexpected'   => __( 'Does not belong', 'velox' ),
	'upload_php'        => __( 'Code in uploads', 'velox' ),
	'upload_double_ext' => __( 'Double extension', 'velox' ),
	'upload_htaccess'   => __( 'Server rules', 'velox' ),
	'unreadable'        => __( 'Unreadable', 'velox' ),
);
$vx_pill = array( 'danger' => 'velox-pill--bad', 'warn' => 'velox-pill--warn', 'info' => 'velox-pill--muted' );
?>
<div class="velox-page-head">
	<h1 class="velox-h2"><?php esc_html_e( 'Site scan', 'velox' ); ?></h1>
	<p class="velox-sub"><?php esc_html_e( 'Compares your WordPress core files against the ones WordPress.org published for your exact version, and checks the uploads folder for runnable code. Read what it does not check before you rely on it.', 'velox' ); ?></p>
</div>

<div class="velox-panel">
	<div class="velox-tool-actions" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0;">
		<button class="velox-btn velox-btn--primary" id="velox-scan-run"><?php esc_html_e( 'Run scan', 'velox' ); ?></button>
		<span class="velox-hint" id="velox-scan-summary">
			<?php
			if ( ! empty( $last['ended'] ) ) {
				printf(
					/* translators: %s: how long ago, e.g. "2 hours" */
					esc_html__( 'Last scanned %s ago.', 'velox' ),
					esc_html( human_time_diff( (int) $last['ended'] ) )
				);
			} else {
				esc_html_e( 'Not scanned yet.', 'velox' );
			}
			?>
		</span>
	</div>

	<div id="velox-scan-progress" hidden>
		<div class="velox-progress"><div class="velox-progress-bar" id="velox-scan-bar"></div></div>
		<p class="velox-progress-text" id="velox-scan-label"></p>
	</div>
</div>

<div class="velox-note velox-note--info">
	<strong><?php esc_html_e( 'What this does not check', 'velox' ); ?></strong>
	<p><?php esc_html_e( 'It does not read your files looking for malicious code. Searching for "known bad" patterns fails in both directions: ordinary minified or packed code sets it off constantly, so the report fills with alarms nobody can act on, and any infection not already on the list walks straight past — which is worse than no scan at all, because it feels like an all-clear.', 'velox' ); ?></p>
	<p><?php esc_html_e( 'It also does not check your plugins, your theme or wp-content, because there is no published list of what those files are supposed to contain. A clean result here means your core files match and uploads holds no code. It is not proof that the site is uninfected.', 'velox' ); ?></p>
</div>

<div class="velox-panel" id="velox-scan-report"<?php echo empty( $last['ended'] ) ? ' hidden' : ''; ?>>
	<div class="velox-panel-head">
		<h3 class="velox-panel-title"><?php esc_html_e( 'Result', 'velox' ); ?></h3>
		<div style="display:flex;gap:6px;align-items:center;">
			<?php if ( ! empty( $levels['danger'] ) ) : ?>
				<span class="velox-pill velox-pill--bad"><?php echo (int) $levels['danger']; ?> <?php esc_html_e( 'serious', 'velox' ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $levels['warn'] ) ) : ?>
				<span class="velox-pill velox-pill--warn"><?php echo (int) $levels['warn']; ?> <?php esc_html_e( 'to look at', 'velox' ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $last['ended'] ) && empty( $levels['danger'] ) && empty( $levels['warn'] ) ) : ?>
				<span class="velox-pill velox-pill--ok"><?php esc_html_e( 'Nothing found', 'velox' ); ?></span>
			<?php endif; ?>
		</div>
	</div>

	<div id="velox-scan-results">
		<?php if ( ! empty( $last['ended'] ) ) : ?>
			<?php if ( ! empty( $last['notes'] ) ) : ?>
				<?php foreach ( $last['notes'] as $vx_note ) : ?>
					<p class="velox-hint"><?php echo esc_html( $vx_note ); ?></p>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php if ( empty( $last['findings'] ) ) : ?>
				<p class="velox-hint"><?php
					printf(
						/* translators: 1: number of core files 2: number of upload files */
						esc_html__( '%1$s core files matched, and %2$s files in uploads carried no runnable code.', 'velox' ),
						esc_html( number_format_i18n( (int) ( $last['counts']['core'] ?? 0 ) ) ),
						esc_html( number_format_i18n( (int) ( $last['counts']['uploads'] ?? 0 ) ) )
					);
				?></p>
			<?php else : ?>
				<table class="velox-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'What', 'velox' ); ?></th>
							<th><?php esc_html_e( 'File', 'velox' ); ?></th>
							<th><?php esc_html_e( 'Why it is listed', 'velox' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $last['findings'] as $vx_f ) : ?>
						<tr>
							<td><span class="velox-pill <?php echo esc_attr( $vx_pill[ $vx_f['level'] ] ?? 'velox-pill--muted' ); ?>"><?php echo esc_html( $vx_kind_labels[ $vx_f['kind'] ] ?? $vx_f['kind'] ); ?></span></td>
							<td><code><?php echo esc_html( $vx_f['path'] ); ?></code></td>
							<td><?php echo esc_html( $vx_f['note'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( ! empty( $last['extra'] ) ) : ?>
					<p class="velox-hint"><?php
						printf(
							/* translators: %s: number of further findings */
							esc_html__( 'A further %s were found and are not listed here. Deal with the ones above first, then scan again.', 'velox' ),
							esc_html( number_format_i18n( (int) $last['extra'] ) )
						);
					?></p>
				<?php endif; ?>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
