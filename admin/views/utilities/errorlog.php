<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$on      = Velox_Settings::get( 'util_errorlog', false );
$groups  = $on ? Velox_Error_Logger::grouped() : array( 'fatal' => array(), 'php' => array(), 'http' => array() );
$total   = 0;
foreach ( $groups as $g ) {
	$total += count( $g );
}

// Display meta per group: label, icon, tone.
$group_meta = array(
	'fatal' => array( 'label' => __( 'Fatal errors', 'velox' ),        'icon' => 'warning', 'tone' => 'bad',     'blurb' => __( 'Errors that stop a request completely — the usual cause of a white screen or broken page.', 'velox' ) ),
	'php'   => array( 'label' => __( 'PHP warnings & notices', 'velox' ), 'icon' => 'code',   'tone' => 'warn',    'blurb' => __( 'Non-fatal PHP messages. The page still loads, but these can point at bugs in a plugin or theme.', 'velox' ) ),
	'http'  => array( 'label' => __( 'API & HTTP errors', 'velox' ),   'icon' => 'redirect', 'tone' => 'neutral', 'blurb' => __( 'Outgoing requests to other servers that failed or came back with an error status.', 'velox' ) ),
);

/** Small helper: relative "x ago". */
if ( ! function_exists( 'velox_errlog_ago' ) ) {
	function velox_errlog_ago( $ts ) {
		$d = time() - (int) $ts;
		if ( $d < 60 ) {
			return __( 'just now', 'velox' );
		}
		/* translators: %s is a human-readable time difference, e.g. "5 mins". */
		return sprintf( __( '%s ago', 'velox' ), human_time_diff( (int) $ts, time() ) );
	}
}
?>
<div class="velox-page-head">
	<h1 class="velox-h2"><?php esc_html_e( 'Error Logger', 'velox' ); ?></h1>
	<p class="velox-sub"><?php esc_html_e( 'Every PHP error, fatal and failed API request as it happens — grouped by type, each with what it means and how to fix it. Turn it on and errors show up here from that point forward.', 'velox' ); ?></p>
</div>

<div class="velox-panel">
	<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
		<label class="velox-inline-toggle">
			<span><strong><?php esc_html_e( 'Log errors', 'velox' ); ?></strong></span>
			<span class="velox-switch"><input type="checkbox" data-setting="util_errorlog" id="velox-errorlog-toggle" <?php checked( $on ); ?>><span class="velox-switch-track"></span></span>
		</label>
		<?php if ( $on && $total > 0 ) : ?>
			<button class="velox-btn velox-btn--ghost" id="velox-errorlog-clear"><?php esc_html_e( 'Clear all', 'velox' ); ?></button>
		<?php endif; ?>
	</div>

	<?php if ( ! $on ) : ?>
		<p class="velox-hint" style="margin-top:14px;"><?php esc_html_e( 'Logging is off. Flip it on to start catching errors from this point forward.', 'velox' ); ?></p>
	<?php elseif ( 0 === $total ) : ?>
		<div class="velox-errlog-empty">
			<?php echo Velox_Admin::icon( 'check', 26 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<p><strong><?php esc_html_e( 'No errors logged.', 'velox' ); ?></strong></p>
			<p class="velox-hint"><?php esc_html_e( 'Nothing has gone wrong since you switched this on. New errors will appear here, newest first.', 'velox' ); ?></p>
		</div>
	<?php else : ?>
		<p class="velox-hint" style="margin-top:12px;"><?php
			/* translators: %d is the number of distinct errors. */
			printf( esc_html( _n( '%d distinct error logged. Click one to see what it means and how to fix it.', '%d distinct errors logged. Click one to see what it means and how to fix it.', $total, 'velox' ) ), (int) $total );
		?></p>
	<?php endif; ?>
</div>

<?php if ( $on && $total > 0 ) : ?>
	<?php foreach ( $group_meta as $gid => $meta ) :
		$items = isset( $groups[ $gid ] ) ? $groups[ $gid ] : array();
		if ( empty( $items ) ) {
			continue;
		}
		?>
		<div class="velox-errlog-group">
			<div class="velox-errlog-grouphead is-<?php echo esc_attr( $meta['tone'] ); ?>">
				<span class="velox-errlog-groupic"><?php echo Velox_Admin::icon( $meta['icon'], 17 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span class="velox-errlog-grouptitle"><?php echo esc_html( $meta['label'] ); ?></span>
				<span class="velox-errlog-groupcount"><?php echo (int) count( $items ); ?></span>
				<span class="velox-errlog-groupblurb"><?php echo esc_html( $meta['blurb'] ); ?></span>
			</div>

			<div class="velox-errlog-list">
				<?php foreach ( $items as $e ) :
					list( $g_title, $g_what, $g_fix ) = Velox_Error_Logger::guidance( $e );
					$fp = isset( $e['fp'] ) ? $e['fp'] : md5( wp_json_encode( $e ) );
					?>
					<details class="velox-errlog-item">
						<summary class="velox-errlog-summary">
							<span class="velox-errlog-dot is-<?php echo esc_attr( $meta['tone'] ); ?>"></span>
							<span class="velox-errlog-level"><?php echo esc_html( isset( $e['level'] ) ? $e['level'] : '' ); ?></span>
							<span class="velox-errlog-msg"><?php echo esc_html( isset( $e['message'] ) ? $e['message'] : '' ); ?></span>
							<?php if ( ! empty( $e['count'] ) && $e['count'] > 1 ) : ?>
								<span class="velox-errlog-times">&times;<?php echo (int) $e['count']; ?></span>
							<?php endif; ?>
							<span class="velox-errlog-when"><?php echo esc_html( velox_errlog_ago( isset( $e['last'] ) ? $e['last'] : time() ) ); ?></span>
							<span class="velox-errlog-chev" aria-hidden="true"><?php echo Velox_Admin::icon( 'redirect', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						</summary>

						<div class="velox-errlog-body">
							<div class="velox-errlog-meta">
								<?php if ( ! empty( $e['file'] ) ) : ?>
									<div class="velox-errlog-metarow">
										<span class="velox-errlog-k"><?php esc_html_e( 'Where', 'velox' ); ?></span>
										<code class="velox-errlog-v"><?php echo esc_html( $e['file'] ); ?><?php echo ! empty( $e['line'] ) ? ':' . (int) $e['line'] : ''; ?></code>
									</div>
								<?php endif; ?>
								<?php if ( ! empty( $e['url'] ) ) : ?>
									<div class="velox-errlog-metarow">
										<span class="velox-errlog-k"><?php esc_html_e( 'URL', 'velox' ); ?></span>
										<code class="velox-errlog-v"><?php echo esc_html( $e['url'] ); ?></code>
									</div>
								<?php endif; ?>
								<div class="velox-errlog-metarow">
									<span class="velox-errlog-k"><?php esc_html_e( 'Seen', 'velox' ); ?></span>
									<span class="velox-errlog-v">
										<?php
										/* translators: 1: first-seen time, 2: last-seen time. */
										printf(
											esc_html__( 'first %1$s, last %2$s', 'velox' ),
											esc_html( velox_errlog_ago( isset( $e['first'] ) ? $e['first'] : time() ) ),
											esc_html( velox_errlog_ago( isset( $e['last'] ) ? $e['last'] : time() ) )
										);
										?>
									</span>
								</div>
							</div>

							<div class="velox-errlog-guide">
								<div class="velox-errlog-guidecard">
									<span class="velox-errlog-guidelabel"><?php esc_html_e( 'What it is', 'velox' ); ?></span>
									<p class="velox-errlog-guidetitle"><?php echo esc_html( $g_title ); ?></p>
									<p class="velox-errlog-guidetext"><?php echo esc_html( $g_what ); ?></p>
								</div>
								<div class="velox-errlog-guidecard velox-errlog-guidecard--fix">
									<span class="velox-errlog-guidelabel"><?php esc_html_e( 'How to fix it', 'velox' ); ?></span>
									<p class="velox-errlog-guidetext"><?php echo esc_html( $g_fix ); ?></p>
								</div>
							</div>

							<div class="velox-errlog-actions">
								<button class="velox-btn velox-btn--ghost velox-btn--sm velox-errlog-del" data-fp="<?php echo esc_attr( $fp ); ?>"><?php esc_html_e( 'Dismiss this error', 'velox' ); ?></button>
							</div>
						</div>
					</details>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endforeach; ?>
<?php endif; ?>
