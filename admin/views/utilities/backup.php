<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$on = Velox_Settings::get( 'util_backup', false );
$s  = Velox_Settings::all();

/** Human file size. */
function velox_backup_size( $bytes ) {
	$bytes = (int) $bytes;
	if ( $bytes <= 0 ) {
		return '—';
	}
	$units = array( 'B', 'KB', 'MB', 'GB' );
	$i = 0;
	while ( $bytes >= 1024 && $i < count( $units ) - 1 ) {
		$bytes /= 1024;
		$i++;
	}
	return round( $bytes, $bytes < 10 && $i > 0 ? 1 : 0 ) . ' ' . $units[ $i ];
}
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Backup &amp; restore</h1>
	<p class="velox-sub">Back up your database and files, download them for safe-keeping, restore in a click, and schedule automatic backups with a keep-newest-N retention. Backups are stored in a protected folder under <code>wp-content</code>.</p>
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
	$labels  = array( 'off' => 'Off', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly' );
	?>

	<?php if ( ! $stats['zip_ready'] ) : ?>
		<div class="velox-alert velox-alert--warn">PHP-zip isn't available on this server, so <strong>file</strong> backups can't be created or restored — database backups still work. Ask your host to enable the <code>php-zip</code> extension to back up files too.</div>
	<?php endif; ?>

	<div class="vbk-stats">
		<div class="vbk-stat"><span class="vbk-stat-n"><?php echo (int) $stats['count']; ?></span><span class="vbk-stat-l">Backups</span></div>
		<div class="vbk-stat"><span class="vbk-stat-n"><?php echo esc_html( velox_backup_size( $stats['total_size'] ) ); ?></span><span class="vbk-stat-l">Total size</span></div>
		<div class="vbk-stat"><span class="vbk-stat-n" style="font-size:15px;"><?php echo $stats['newest'] ? esc_html( $stats['newest'] ) : '—'; ?></span><span class="vbk-stat-l">Newest</span></div>
		<div class="vbk-stat"><span class="vbk-stat-n" style="font-size:15px;"><?php echo $stats['next_run'] ? esc_html( date_i18n( 'M j, H:i', $stats['next_run'] ) ) : 'Not scheduled'; ?></span><span class="vbk-stat-l">Next auto-backup</span></div>
	</div>

	<div class="velox-panel velox-tool-form">
		<h3 class="velox-panel-title">Create a backup now</h3>
		<p class="velox-hint">Pick what to include, then run it. Large sites can take a minute — keep the tab open until it finishes.</p>
		<div class="vbk-create">
			<select class="velox-select" id="vbk-what" <?php echo $stats['zip_ready'] ? '' : 'data-nozip="1"'; ?>>
				<option value="both">Database &amp; files</option>
				<option value="db">Database only</option>
				<option value="files">Files only</option>
			</select>
			<button class="velox-btn velox-btn--primary" id="vbk-create">Create backup</button>
			<span class="vbk-progress" id="vbk-progress" hidden></span>
		</div>
	</div>

	<div class="velox-panel velox-panel--flush">
		<div class="vbk-list-head">
			<h3 class="velox-panel-title" style="margin:0;">Your backups</h3>
		</div>
		<div id="vbk-list">
			<?php if ( empty( $backups ) ) : ?>
				<p class="velox-hint" style="padding:22px;">No backups yet. Create one above, or set up a schedule below.</p>
			<?php else : ?>
				<table class="vbk-table">
					<thead><tr><th>Created</th><th>Contents</th><th>Size</th><th>Note</th><th class="vbk-th-act"></th></tr></thead>
					<tbody>
						<?php foreach ( $backups as $b ) :
							$has_db  = ! empty( $b['db_file'] );
							$has_zip = ! empty( $b['zip_file'] );
							$size    = (int) ( $b['db_size'] ?? 0 ) + (int) ( $b['zip_size'] ?? 0 );
							$contents = array();
							if ( $has_db )  { $contents[] = 'DB (' . (int) ( $b['tables'] ?? 0 ) . ' tables)'; }
							if ( $has_zip ) { $contents[] = 'Files (' . (int) ( $b['files'] ?? 0 ) . ')'; }
							?>
							<tr class="vbk-row" data-id="<?php echo esc_attr( $b['id'] ); ?>" data-hasdb="<?php echo $has_db ? '1' : '0'; ?>" data-haszip="<?php echo $has_zip ? '1' : '0'; ?>">
								<td><?php echo esc_html( date_i18n( 'M j, Y · H:i', strtotime( $b['created'] ) ) ); ?></td>
								<td><?php echo esc_html( implode( ' + ', $contents ) ); ?></td>
								<td><?php echo esc_html( velox_backup_size( $size ) ); ?></td>
								<td class="vbk-note"><?php echo esc_html( $b['note'] ?? '' ); ?></td>
								<td class="vbk-act">
									<?php if ( $has_db ) : ?>
										<a class="velox-btn velox-btn--ghost velox-btn--sm" href="<?php echo esc_url( Velox_Backup::download_url( $b['id'], 'db' ) ); ?>">SQL</a>
									<?php endif; ?>
									<?php if ( $has_zip ) : ?>
										<a class="velox-btn velox-btn--ghost velox-btn--sm" href="<?php echo esc_url( Velox_Backup::download_url( $b['id'], 'zip' ) ); ?>">ZIP</a>
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

	<div class="velox-panel velox-tool-form">
		<h3 class="velox-panel-title">Schedule</h3>
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

<?php endif; ?>
