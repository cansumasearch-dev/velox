<?php
/**
 * Backup & restore — redesigned (stage 7).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$on = Velox_Settings::get( 'util_backup', false );
$s  = Velox_Settings::all();

if ( ! function_exists( 'velox_backup_size' ) ) {
	function velox_backup_size( $bytes ) {
		$bytes = (int) $bytes;
		if ( $bytes <= 0 ) { return '—'; }
		$units = array( 'B', 'KB', 'MB', 'GB' );
		$i = 0;
		while ( $bytes >= 1024 && $i < count( $units ) - 1 ) { $bytes /= 1024; $i++; }
		return round( $bytes, $bytes < 10 && $i > 0 ? 1 : 0 ) . ' ' . $units[ $i ];
	}
}
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Backup &amp; restore</h1>
	<p class="velox-sub">Back up your database and files, download them, restore in a click, or import a backup from another site. Backups live in a protected folder under <code>wp-content</code>.</p>
</div>

<?php if ( ! $on ) : ?>
	<div class="velox-panel">
		<label class="velox-inline-toggle">
			<span><strong>Enable Backup &amp; restore</strong></span>
			<span class="velox-switch"><input type="checkbox" data-setting="util_backup" id="velox-backup-toggle"><span class="velox-switch-track"></span></span>
		</label>
		<p class="velox-hint" style="margin-top:14px;">Turn this on to create and manage backups. Nothing runs until you ask for it.</p>
	</div>
<?php else :
	$stats   = Velox_Backup::stats();
	$backups = Velox_Backup::all();
	$history = Velox_Backup::history();
	$labels  = array( 'off' => 'Off', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly' );
	?>

	<?php if ( ! $stats['zip_ready'] ) : ?>
		<div class="velox-alert velox-alert--warn">PHP-zip isn't available on this server, so <strong>file</strong> backups can't be created or restored — database backups still work. Ask your host to enable the <code>php-zip</code> extension.</div>
	<?php endif; ?>

	<div class="vbk-stats">
		<div class="vbk-stat"><span class="vbk-stat-n"><?php echo (int) $stats['count']; ?></span><span class="vbk-stat-l">Backups</span></div>
		<div class="vbk-stat"><span class="vbk-stat-n"><?php echo esc_html( velox_backup_size( $stats['total_size'] ) ); ?></span><span class="vbk-stat-l">Total size</span></div>
		<div class="vbk-stat"><span class="vbk-stat-n" style="font-size:15px;"><?php echo $stats['newest'] ? esc_html( date_i18n( 'M j, H:i', strtotime( $stats['newest'] ) ) ) : '—'; ?></span><span class="vbk-stat-l">Newest</span></div>
		<div class="vbk-stat"><span class="vbk-stat-n"><?php echo (int) $stats['restores']; ?></span><span class="vbk-stat-l">Restores run</span></div>
	</div>

	<!-- Create + import row -->
	<div class="vbk-action-grid">
		<div class="velox-panel velox-tool-form">
			<h3 class="velox-panel-title">Create a backup</h3>
			<p class="velox-hint">Choose what to include. Large sites can take a minute — a progress box will keep you posted.</p>
			<div class="vbk-create-row">
				<div class="vbk-seg" id="vbk-what-seg">
					<button type="button" class="vbk-seg-btn is-active" data-what="both"<?php echo $stats['zip_ready'] ? '' : ' disabled'; ?>>Export all</button>
					<button type="button" class="vbk-seg-btn" data-what="db">Export DB</button>
					<button type="button" class="vbk-seg-btn" data-what="files"<?php echo $stats['zip_ready'] ? '' : ' disabled'; ?>>Export files</button>
				</div>
				<button class="velox-btn velox-btn--primary" id="vbk-create">Create backup</button>
			</div>
		</div>

		<div class="velox-panel velox-tool-form">
			<h3 class="velox-panel-title">Import &amp; restore a backup</h3>
			<p class="velox-hint">Upload a <code>.sql</code> dump or a <code>.zip</code> archive made on another site and it's <strong>restored straight away</strong>. A safety backup of the current site is taken first, so you can roll the restore back from the list below.</p>
			<div class="vbk-import-row">
				<input type="file" id="vbk-import-file" accept=".sql,.zip" class="vbk-file">
				<button class="velox-btn velox-btn--primary" id="vbk-import-btn">Import &amp; restore</button>
			</div>
		</div>
	</div>

	<!-- Backups list -->
	<div class="velox-section-title">Your backups</div>
	<div class="velox-panel velox-panel--flush">
		<div id="vbk-list">
			<?php if ( empty( $backups ) ) : ?>
				<p class="velox-hint" style="padding:22px;">No backups yet. Create one above, import one, or set up a schedule below.</p>
			<?php else : ?>
				<table class="vbk-table">
					<thead><tr><th>Backup</th><th>Contents</th><th>Size</th><th>When</th><th class="vbk-th-act"></th></tr></thead>
					<tbody>
						<?php foreach ( $backups as $b ) :
							$has_db  = ! empty( $b['db_file'] );
							$has_zip = ! empty( $b['zip_file'] );
							$size    = (int) ( $b['db_size'] ?? 0 ) + (int) ( $b['zip_size'] ?? 0 );
							$contents = array();
							if ( $has_db )  { $contents[] = 'DB · ' . (int) ( $b['tables'] ?? 0 ) . ' tables'; }
							if ( $has_zip ) { $contents[] = 'Files · ' . (int) ( $b['files'] ?? 0 ); }
							$is_safety = ( false !== stripos( $b['note'] ?? '', 'safety snapshot' ) );
							$is_import = ! empty( $b['imported'] );
							?>
							<tr class="vbk-row" data-id="<?php echo esc_attr( $b['id'] ); ?>" data-hasdb="<?php echo $has_db ? '1' : '0'; ?>" data-haszip="<?php echo $has_zip ? '1' : '0'; ?>">
								<td>
									<span class="vbk-name"><?php echo esc_html( $b['name'] ?? $b['id'] ); ?></span>
									<?php if ( $is_safety ) : ?><span class="vbk-tag vbk-tag--safety">safety</span><?php endif; ?>
									<?php if ( $is_import ) : ?><span class="vbk-tag vbk-tag--import">imported</span><?php endif; ?>
									<?php if ( ! empty( $b['note'] ) && ! $is_safety ) : ?><span class="vbk-rownote"><?php echo esc_html( $b['note'] ); ?></span><?php endif; ?>
								</td>
								<td><?php echo esc_html( implode( '  +  ', $contents ) ); ?></td>
								<td><?php echo esc_html( velox_backup_size( $size ) ); ?></td>
								<td><?php echo esc_html( date_i18n( 'M j, Y · H:i', strtotime( $b['created'] ) ) ); ?></td>
								<td class="vbk-act">
									<?php
									// One download button, label matches what the backup holds.
									if ( $has_db && $has_zip ) {
										$dl_kind = 'all'; $dl_label = 'Download';
									} elseif ( $has_db ) {
										$dl_kind = 'db'; $dl_label = 'DB download';
									} else {
										$dl_kind = 'zip'; $dl_label = 'Files download';
									}
									if ( $has_db || $has_zip ) :
										?>
										<a class="velox-btn velox-btn--ghost velox-btn--sm" href="<?php echo esc_url( Velox_Backup::download_url( $b['id'], $dl_kind ) ); ?>"><?php echo esc_html( $dl_label ); ?></a>
									<?php endif; ?>
									<button class="velox-btn velox-btn--ghost velox-btn--sm vbk-restore">Restore</button>
									<button class="velox-btn velox-btn--ghost velox-btn--sm vbk-delete">Delete</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>

	<!-- Restore history -->
	<?php if ( ! empty( $history ) ) : ?>
		<div class="velox-section-title vbk-hist-head">
			<span>Restore history</span>
			<button type="button" class="velox-btn velox-btn--ghost velox-btn--sm" id="vbk-hist-clear">Clear history</button>
		</div>
		<div class="velox-panel velox-panel--flush">
			<table class="vbk-table vbk-hist-table">
				<thead><tr><th>When</th><th>Backup</th><th>What</th><th>Took</th><th>Result</th><th></th></tr></thead>
				<tbody>
					<?php foreach ( $history as $h ) : ?>
						<tr data-when="<?php echo esc_attr( $h['when'] ?? '' ); ?>">
							<td><?php echo esc_html( date_i18n( 'M j, Y · H:i', strtotime( $h['when'] ) ) ); ?></td>
							<td><span class="vbk-name"><?php echo esc_html( $h['backup_name'] ?? $h['backup_id'] ?? '—' ); ?></span></td>
							<td><?php echo esc_html( 'both' === ( $h['what'] ?? '' ) ? 'DB + files' : strtoupper( $h['what'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( isset( $h['duration'] ) ? $h['duration'] . 's' : '—' ); ?></td>
							<td>
								<?php if ( ! empty( $h['ok'] ) ) : ?>
									<span class="vbk-result vbk-result--ok">Success</span>
								<?php else : ?>
									<span class="vbk-result vbk-result--fail">Failed</span>
								<?php endif; ?>
								<span class="vbk-hist-detail"><?php echo esc_html( $h['detail'] ?? '' ); ?></span>
							</td>
							<td class="vbk-hist-x">
								<button type="button" class="vbk-hist-del" title="Remove this entry" aria-label="Remove this entry"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<!-- Schedule -->
	<div class="velox-section-title">Schedule</div>
	<div class="velox-panel velox-tool-form">
		<p class="velox-hint">Run backups automatically via WP-Cron. Older backups beyond your keep-count are pruned after each scheduled run.</p>
		<div class="velox-grid-2">
			<div class="velox-field">
				<span class="velox-field-label">Frequency</span>
				<select class="velox-select" id="vbk-sched-freq" data-setting="backup_schedule">
					<?php foreach ( $labels as $v => $l ) : ?>
						<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $s['backup_schedule'], $v ); ?>><?php echo esc_html( $l ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="velox-field">
				<span class="velox-field-label">What to back up</span>
				<select class="velox-select" id="vbk-sched-what" data-setting="backup_schedule_what">
					<option value="both" <?php selected( $s['backup_schedule_what'], 'both' ); ?>>Database &amp; files</option>
					<option value="db" <?php selected( $s['backup_schedule_what'], 'db' ); ?>>Database only</option>
					<option value="files" <?php selected( $s['backup_schedule_what'], 'files' ); ?>>Files only</option>
				</select>
			</div>
			<div class="velox-field velox-field--narrow">
				<span class="velox-field-label">Keep newest</span>
				<input type="number" class="velox-input velox-input--sm" id="vbk-keep" data-setting="backup_keep" value="<?php echo esc_attr( (int) $s['backup_keep'] ); ?>" min="1" max="50">
			</div>
		</div>
		<div class="velox-tool-actions">
			<button class="velox-btn velox-btn--primary" id="vbk-sched-save">Save schedule</button>
			<span class="velox-hint" style="margin-left:8px;">WP-Cron runs on traffic — quiet sites may back up a little late.</span>
		</div>
	</div>

	<div class="velox-panel velox-mail-disable">
		<label class="velox-inline-toggle">
			<span><strong>Backup &amp; restore is on</strong> <span class="velox-hint" style="display:inline;">— switch off to hide this tool (your stored backups are kept).</span></span>
			<span class="velox-switch"><input type="checkbox" data-setting="util_backup" id="velox-backup-toggle" checked><span class="velox-switch-track"></span></span>
		</label>
	</div>

	<!-- Progress modal -->
	<div class="vbk-modal" id="vbk-modal" hidden>
		<div class="vbk-modal-backdrop"></div>
		<div class="vbk-modal-card" role="dialog" aria-modal="true" aria-labelledby="vbk-modal-title">
			<div class="vbk-modal-spinner" aria-hidden="true"></div>
			<h3 class="vbk-modal-title" id="vbk-modal-title">Working…</h3>
			<p class="vbk-modal-msg" id="vbk-modal-msg">Please keep this tab open.</p>
			<div class="vbk-modal-bar"><span class="vbk-modal-bar-fill" id="vbk-modal-fill"></span></div>
			<p class="vbk-modal-eta" id="vbk-modal-eta"></p>
		</div>
	</div>

	<!-- Restore confirm modal -->
	<div class="vbk-modal" id="vbk-restore-modal" hidden>
		<div class="vbk-modal-backdrop" data-close></div>
		<div class="vbk-modal-card" role="dialog" aria-modal="true" aria-labelledby="vbk-rm-title">
			<h3 class="vbk-modal-title" id="vbk-rm-title">Restore this backup?</h3>
			<p class="vbk-modal-msg" id="vbk-rm-msg">This replaces your current site with the contents of this backup.</p>
			<label class="velox-toggle-row" style="margin:14px 0;cursor:pointer;">
				<div class="velox-toggle-meta">
					<span class="velox-toggle-label">Take a safety snapshot first</span>
					<span class="velox-toggle-desc">Saves your <em>current</em> database (a small DB-only backup) so you can undo this restore. It's the small one labelled “safety”.</span>
				</div>
				<span class="velox-switch"><input type="checkbox" id="vbk-rm-safety" checked><span class="velox-switch-track"></span></span>
			</label>
			<div class="vbk-modal-actions">
				<button class="velox-btn velox-btn--ghost" data-close>Cancel</button>
				<button class="velox-btn velox-btn--primary" id="vbk-rm-go">Restore now</button>
			</div>
		</div>
	</div>

<?php endif; ?>
