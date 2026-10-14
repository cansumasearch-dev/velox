<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s       = Velox_Settings::all();
$last    = Velox_Site_Scan::last();
$running = Velox_Site_Scan::running();
$levels  = isset( $last['levels'] ) ? $last['levels'] : array( 'danger' => 0, 'warn' => 0, 'info' => 0 );
$q       = Velox_Site_Scan_Fix::quarantined();
$scanned = ! empty( $last['ended'] );
$danger  = (int) ( $levels['danger'] ?? 0 );
$warn    = (int) ( $levels['warn'] ?? 0 );

/* What each kind of finding is called, and — the part people actually need —
   how that kind of hack works and what the fix does. */
$vx_kinds = array(
	'code_suspicious'   => array( __( 'Malicious code', 'velox' ), '' ),
	'upload_php'        => array( __( 'Code in uploads', 'velox' ), __( 'Attackers upload a PHP file disguised among your images, then open it in a browser to control the site. Quarantine moves it where it can\'t run.', 'velox' ) ),
	'upload_double_ext' => array( __( 'Disguised file', 'velox' ), __( 'A name like photo.php.jpg looks like an image but some servers run it as code.', 'velox' ) ),
	'upload_htaccess'   => array( __( 'Server rules in uploads', 'velox' ), __( 'Tells the server to run files in the media folder as code, so an uploaded "image" becomes a back door.', 'velox' ) ),
	'plugin_unexpected' => array( __( 'Added to a plugin', 'velox' ), __( 'Hackers drop extra PHP files into plugin folders where nobody looks. The plugin never shipped this file.', 'velox' ) ),
	'core_unexpected'   => array( __( 'Added to WordPress', 'velox' ), __( 'wp-admin and wp-includes belong to WordPress alone. A file WordPress didn\'t ship was put there by someone.', 'velox' ) ),
	'core_modified'     => array( __( 'WordPress file changed', 'velox' ), __( 'Malware often injects itself into a core file so it loads on every page. Restore puts back the original from WordPress.org.', 'velox' ) ),
	'core_missing'      => array( __( 'WordPress file missing', 'velox' ), __( 'Usually harmless, but sometimes a sign files were tampered with. Restore puts it back from WordPress.org.', 'velox' ) ),
	'plugin_modified'   => array( __( 'Plugin file changed', 'velox' ), __( 'Malware hides by adding a few lines to a file that otherwise looks normal. Restore puts back the original from WordPress.org.', 'velox' ) ),
	'db_script'         => array( __( 'Injected into a page', 'velox' ), __( 'Code planted in a page\'s content runs in every visitor\'s browser — typically redirecting them to spam or scam sites.', 'velox' ) ),
	'db_siteurl'        => array( __( 'Site address changed', 'velox' ), __( 'Fix it under Settings → General, then change all passwords.', 'velox' ) ),
	'db_option'         => array( __( 'Code hidden in settings', 'velox' ), __( 'Needs removing directly in the database — ask your host or a developer, and give them the setting name shown.', 'velox' ) ),
	'admin_new'         => array( __( 'New administrator', 'velox' ), __( 'If you don\'t recognise it, delete the account (attribute its content to yourself), then sign everyone out and change passwords.', 'velox' ) ),
	'htaccess_redirect' => array( __( 'Hidden redirect', 'velox' ), __( 'Remove that line in Performance → .htaccess (switch on Risky mode to see it).', 'velox' ) ),
	'htaccess_prepend'  => array( __( 'Hidden loader', 'velox' ), __( 'Remove that line in Performance → .htaccess (Risky mode), then quarantine the file it loads.', 'velox' ) ),
	'htaccess_handler'  => array( __( 'Images run as code', 'velox' ), __( 'Remove that line in Performance → .htaccess (Risky mode).', 'velox' ) ),
	'outdated'          => array( __( 'Updates waiting', 'velox' ), '' ),
	'file_edit'         => array( __( 'File editor on', 'velox' ), '' ),
	'debug_display'     => array( __( 'Errors shown to visitors', 'velox' ), '' ),
	'admin_user'        => array( __( '"admin" account', 'velox' ), '' ),
	'unreadable'        => array( __( 'Unreadable', 'velox' ), '' ),
);
$vx_groups = array(
	'code'   => array( __( 'Malicious or suspicious code', 'velox' ), array( 'code_suspicious', 'upload_php', 'upload_double_ext', 'upload_htaccess', 'plugin_unexpected', 'core_unexpected' ) ),
	'files'  => array( __( 'Changed WordPress and plugin files', 'velox' ), array( 'core_modified', 'core_missing', 'plugin_modified', 'unreadable' ) ),
	'db'     => array( __( 'Pages, settings and accounts', 'velox' ), array( 'db_script', 'db_siteurl', 'db_option', 'admin_new' ) ),
	'server' => array( __( 'Server rules (.htaccess)', 'velox' ), array( 'htaccess_redirect', 'htaccess_prepend', 'htaccess_handler' ) ),
	'tips'   => array( __( 'Hardening tips — not a hack, but how hacks get in', 'velox' ), array( 'outdated', 'file_edit', 'debug_display', 'admin_user' ) ),
);
$vx_pill  = array( 'danger' => 'velox-pill--bad', 'warn' => 'velox-pill--warn', 'info' => 'velox-pill--muted' );
$vx_level = array( 'danger' => __( 'Serious', 'velox' ), 'warn' => __( 'Check', 'velox' ), 'info' => __( 'Tip', 'velox' ) );
$vx_done  = array( 'quarantine' => __( 'Quarantined', 'velox' ), 'restore_core' => __( 'Restored', 'velox' ), 'restore_plugin' => __( 'Restored', 'velox' ), 'clean_post' => __( 'Cleaned', 'velox' ) );

if ( $running ) {
	$state = 'running';
} elseif ( ! $scanned ) {
	$state = 'never';
} elseif ( $danger ) {
	$state = 'danger';
} elseif ( $warn ) {
	$state = 'warn';
} else {
	$state = 'clean';
}
$c = isset( $last['counts'] ) ? $last['counts'] : array();
$checked = sprintf(
	/* translators: 1: core files 2: plugin files 3: other code files 4: uploads 5: pages */
	__( '%1$s WordPress files, %2$s plugin files, %3$s other code files, %4$s uploads and %5$s pages checked', 'velox' ),
	number_format_i18n( (int) ( $c['core'] ?? 0 ) ),
	number_format_i18n( (int) ( $c['plugin_files'] ?? 0 ) ),
	number_format_i18n( (int) ( $c['code'] ?? 0 ) ),
	number_format_i18n( (int) ( $c['uploads'] ?? 0 ) ),
	number_format_i18n( (int) ( $c['posts'] ?? 0 ) )
);
$next = wp_next_scheduled( Velox_Site_Scan::CRON );
?>
<div class="velox-page-head">
	<h1 class="velox-h2"><?php esc_html_e( 'Security scan', 'velox' ); ?></h1>
	<p class="velox-sub"><?php esc_html_e( 'Finds out whether the site has been hacked: WordPress and plugin files are checked against the originals on WordPress.org, everything else is read for backdoors, and pages, settings, accounts and server rules are checked for the common hacks. Every finding says what it is, how it works and how to fix it.', 'velox' ); ?></p>
</div>

<div class="velox-panel vxsec-status vxsec-status--<?php echo esc_attr( $state ); ?>" id="vxsec-status">
	<div class="vxsec-status-main">
		<span class="vxsec-status-ic" aria-hidden="true">
			<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><?php if ( 'clean' === $state ) : ?><path d="m8.5 12 2.5 2.5 4.5-5"/><?php elseif ( 'danger' === $state || 'warn' === $state ) : ?><path d="M12 8v5M12 16.5v.01"/><?php endif; ?></svg>
		</span>
		<div class="vxsec-status-text">
			<strong class="vxsec-headline">
				<?php
				if ( 'running' === $state ) {
					esc_html_e( 'A scan is in progress', 'velox' );
				} elseif ( 'never' === $state ) {
					esc_html_e( 'Not scanned yet', 'velox' );
				} elseif ( 'danger' === $state ) {
					/* translators: %d: number of serious problems */
					echo esc_html( sprintf( 1 === $danger ? __( '%d serious problem found', 'velox' ) : __( '%d serious problems found', 'velox' ), $danger ) );
				} elseif ( 'warn' === $state ) {
					/* translators: %d: number of things */
					echo esc_html( sprintf( 1 === $warn ? __( '%d thing to check', 'velox' ) : __( '%d things to check', 'velox' ), $warn ) );
				} else {
					esc_html_e( 'No signs of a hack', 'velox' );
				}
				?>
			</strong>
			<span class="vxsec-detail">
				<?php
				if ( 'never' === $state ) {
					esc_html_e( 'The first scan takes a minute or two. You can keep working while it runs.', 'velox' );
				} elseif ( 'running' === $state ) {
					esc_html_e( 'Click Scan now to watch it finish.', 'velox' );
				} else {
					if ( 'danger' === $state ) {
						echo esc_html__( 'Your site shows signs of a hack. Fix the findings below, then follow the steps.', 'velox' ) . ' ';
					}
					/* translators: %s: how long ago */
					echo esc_html( sprintf( __( 'Last scan %s ago', 'velox' ), human_time_diff( (int) $last['ended'] ) ) . ' · ' . $checked . '.' );
				}
				?>
			</span>
		</div>
		<button type="button" class="velox-btn velox-btn--primary" id="velox-scan-run"><?php echo $scanned ? esc_html__( 'Scan again', 'velox' ) : esc_html__( 'Scan now', 'velox' ); ?></button>
	</div>
	<div id="velox-scan-progress" class="vxsec-progress" hidden>
		<div class="velox-progress"><div class="velox-progress-bar" id="velox-scan-bar"></div></div>
		<p class="velox-progress-text" id="velox-scan-label"></p>
	</div>
</div>

<?php if ( $danger ) : ?>
<div class="velox-panel vxsec-todo">
	<h3 class="velox-panel-title"><?php esc_html_e( 'What to do now', 'velox' ); ?></h3>
	<ol class="vxsec-steps">
		<li><strong><?php esc_html_e( 'Fix every serious finding below.', 'velox' ); ?></strong> <?php esc_html_e( 'Restore changed files from WordPress.org and quarantine the ones that don\'t belong. Nothing is deleted — you can put any file back.', 'velox' ); ?></li>
		<li><strong><?php esc_html_e( 'Sign everyone out.', 'velox' ); ?></strong> <?php esc_html_e( 'Makes every stolen login cookie useless. You stay signed in.', 'velox' ); ?> <button type="button" class="velox-btn velox-btn--ghost velox-btn--sm" id="vxsec-logout"><?php esc_html_e( 'Sign everyone out', 'velox' ); ?></button></li>
		<li><strong><?php esc_html_e( 'Change every administrator password', 'velox' ); ?></strong> <?php esc_html_e( '— and your hosting and FTP passwords too.', 'velox' ); ?> <a class="velox-btn velox-btn--ghost velox-btn--sm" href="<?php echo esc_url( admin_url( 'users.php?role=administrator' ) ); ?>"><?php esc_html_e( 'Administrators', 'velox' ); ?></a></li>
		<li><strong><?php esc_html_e( 'Update WordPress, plugins and themes', 'velox' ); ?></strong> <?php esc_html_e( '— the hole they came through is usually an old plugin.', 'velox' ); ?> <a class="velox-btn velox-btn--ghost velox-btn--sm" href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>"><?php esc_html_e( 'Updates', 'velox' ); ?></a></li>
		<li><strong><?php esc_html_e( 'Scan again', 'velox' ); ?></strong> <?php esc_html_e( 'until nothing serious is left.', 'velox' ); ?></li>
	</ol>
	<p class="velox-hint vxsec-todo-foot"><?php esc_html_e( 'Still finding problems after that? Restore a backup from before the hack (Utilities → Backup & restore) or ask your host — some infections live outside WordPress where no plugin can reach.', 'velox' ); ?></p>
</div>
<?php endif; ?>

<?php if ( $scanned ) : ?>
	<?php foreach ( $vx_groups as $gid => $g ) :
		$items = array();
		foreach ( $last['findings'] as $i => $f ) {
			if ( in_array( $f['kind'], $g[1], true ) ) {
				$items[ $i ] = $f;
			}
		}
		if ( ! $items ) {
			continue;
		}
		?>
		<div class="velox-panel vxsec-group vxsec-group--<?php echo esc_attr( $gid ); ?>">
			<h3 class="velox-panel-title"><?php echo esc_html( $g[0] ); ?> <span class="vxsec-count"><?php echo (int) count( $items ); ?></span></h3>
			<?php foreach ( $items as $i => $f ) :
				$meta  = isset( $f['meta'] ) ? $f['meta'] : array();
				$fix   = isset( $meta['fix'] ) ? $meta['fix'] : '';
				$label = isset( $vx_kinds[ $f['kind'] ] ) ? $vx_kinds[ $f['kind'] ] : array( $f['kind'], '' );
				$how   = ! empty( $meta['how'] ) ? sprintf( /* translators: %s: what the code does */ __( 'Inside it: %s', 'velox' ), $meta['how'] ) : $label[1];
				$done  = ! empty( $f['resolved'] );
				?>
				<div class="vxsec-item<?php echo $done ? ' is-done' : ''; ?>" data-index="<?php echo (int) $i; ?>">
					<div class="vxsec-item-body">
						<div class="vxsec-item-head">
							<span class="velox-pill <?php echo esc_attr( $done ? 'velox-pill--ok' : ( $vx_pill[ $f['level'] ] ?? 'velox-pill--muted' ) ); ?>"><?php echo esc_html( $done ? ( $vx_done[ $f['resolved'] ] ?? __( 'Fixed', 'velox' ) ) : ( $vx_level[ $f['level'] ] ?? '' ) ); ?></span>
							<strong class="vxsec-item-title"><?php echo esc_html( $label[0] ); ?></strong>
							<code class="vxsec-path"><?php echo esc_html( $f['path'] ); ?></code>
						</div>
						<p class="vxsec-what"><?php echo esc_html( $f['note'] ); ?></p>
						<?php if ( $how ) : ?><p class="vxsec-how"><?php echo esc_html( $how ); ?></p><?php endif; ?>
						<?php if ( ! empty( $meta['snippet'] ) ) : ?>
							<div class="vxsec-code"><?php if ( ! empty( $meta['line'] ) ) : ?><span class="vxsec-line"><?php /* translators: %d: line number */ echo esc_html( sprintf( __( 'Line %d', 'velox' ), (int) $meta['line'] ) ); ?></span><?php endif; ?><code><?php echo esc_html( $meta['snippet'] ); ?></code></div>
						<?php endif; ?>
					</div>
					<div class="vxsec-item-acts">
						<?php if ( ! $done ) : ?>
							<?php if ( 'restore_core' === $fix || 'restore_plugin' === $fix ) : ?>
								<button type="button" class="velox-btn velox-btn--primary velox-btn--sm" data-scan-fix="restore"><?php esc_html_e( 'Restore original', 'velox' ); ?></button>
							<?php elseif ( 'quarantine' === $fix ) : ?>
								<button type="button" class="velox-btn velox-btn--primary velox-btn--sm" data-scan-fix="quarantine" data-qrisk="<?php echo 0 === strpos( $f['path'], 'uploads/' ) ? '0' : '1'; ?>"><?php esc_html_e( 'Quarantine', 'velox' ); ?></button>
							<?php elseif ( 'clean_post' === $fix ) : ?>
								<button type="button" class="velox-btn velox-btn--primary velox-btn--sm" data-scan-fix="clean"><?php esc_html_e( 'Remove injected code', 'velox' ); ?></button>
							<?php endif; ?>
							<?php if ( ! empty( $meta['post_id'] ) ) : ?>
								<a class="velox-btn velox-btn--ghost velox-btn--sm" href="<?php echo esc_url( (string) get_edit_post_link( (int) $meta['post_id'] ) ); ?>"><?php esc_html_e( 'Open', 'velox' ); ?></a>
							<?php elseif ( 'link' === $fix && ! empty( $meta['url'] ) ) : ?>
								<a class="velox-btn velox-btn--ghost velox-btn--sm" href="<?php echo esc_url( $meta['url'] ); ?>"><?php esc_html_e( 'Open', 'velox' ); ?></a>
							<?php elseif ( 'htaccess' === $fix ) : ?>
								<a class="velox-btn velox-btn--ghost velox-btn--sm" href="<?php echo esc_url( admin_url( 'admin.php?page=velox-performance' ) ); ?>"><?php esc_html_e( 'Open .htaccess', 'velox' ); ?></a>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>

	<?php if ( ! empty( $last['extra'] ) ) : ?>
		<p class="velox-hint"><?php
			/* translators: %s: number of further findings */
			echo esc_html( sprintf( __( 'A further %s were found and are not listed here. Deal with the ones above first, then scan again.', 'velox' ), number_format_i18n( (int) $last['extra'] ) ) );
		?></p>
	<?php endif; ?>
	<?php foreach ( (array) ( $last['notes'] ?? array() ) as $vx_note ) : ?>
		<p class="velox-hint vxsec-note"><?php echo esc_html( $vx_note ); ?></p>
	<?php endforeach; ?>
<?php endif; ?>

<?php if ( $q ) : ?>
<div class="velox-panel" id="vxsec-quarantine">
	<h3 class="velox-panel-title"><?php esc_html_e( 'Quarantine', 'velox' ); ?> <span class="vxsec-count"><?php echo (int) count( $q ); ?></span></h3>
	<p class="velox-hint"><?php esc_html_e( 'Files moved out of the site. They can\'t run in here. Put one back if something stopped working; delete them for good once you\'re sure.', 'velox' ); ?></p>
	<?php foreach ( $q as $rec ) : ?>
		<div class="vxsec-item vxsec-qitem" data-qid="<?php echo esc_attr( $rec['id'] ); ?>">
			<div class="vxsec-item-body">
				<div class="vxsec-item-head">
					<code class="vxsec-path"><?php echo esc_html( $rec['path'] ); ?></code>
					<span class="vxsec-meta"><?php echo esc_html( size_format( (int) $rec['size'] ) . ' · ' . sprintf( /* translators: %s: how long ago */ __( '%s ago', 'velox' ), human_time_diff( (int) $rec['time'] ) ) ); ?><?php echo ! empty( $rec['kept'] ) ? ' · ' . esc_html__( 'replaced by the original', 'velox' ) : ''; ?></span>
				</div>
				<p class="vxsec-what"><?php echo esc_html( $rec['why'] ); ?></p>
			</div>
			<div class="vxsec-item-acts">
				<button type="button" class="velox-btn velox-btn--ghost velox-btn--sm" data-q="restore"><?php esc_html_e( 'Put back', 'velox' ); ?></button>
				<button type="button" class="velox-btn velox-btn--ghost velox-btn--sm" data-q="delete"><?php esc_html_e( 'Delete for good', 'velox' ); ?></button>
			</div>
		</div>
	<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="velox-grid-2">
	<div class="velox-panel">
		<h3 class="velox-panel-title"><?php esc_html_e( 'Automatic scans', 'velox' ); ?></h3>
		<label class="velox-field">
			<span class="velox-field-label"><?php esc_html_e( 'Scan by itself', 'velox' ); ?></span>
			<select class="velox-select" data-setting="scan_schedule">
				<option value="weekly" <?php selected( $s['scan_schedule'], 'weekly' ); ?>><?php esc_html_e( 'Every week', 'velox' ); ?></option>
				<option value="daily" <?php selected( $s['scan_schedule'], 'daily' ); ?>><?php esc_html_e( 'Every night', 'velox' ); ?></option>
				<option value="off" <?php selected( $s['scan_schedule'], 'off' ); ?>><?php esc_html_e( 'Only when I click', 'velox' ); ?></option>
			</select>
			<span class="velox-hint"><?php
			echo $next
				/* translators: %s: date/time */
				? esc_html( sprintf( __( 'Next scan: %s (runs at night, site time).', 'velox' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next ) ) )
				: esc_html__( 'Runs at night, site time, when your site has visitors to trigger it.', 'velox' );
			?></span>
		</label>
		<div class="velox-toggle-row">
			<div class="velox-toggle-meta">
				<span class="velox-toggle-label"><?php esc_html_e( 'Email me about new serious problems', 'velox' ); ?></span>
				<span class="velox-toggle-desc"><?php
				/* translators: %s: email address */
				echo esc_html( sprintf( __( 'Sent to %s — only when an automatic scan finds something new.', 'velox' ), get_option( 'admin_email' ) ) );
				?></span>
			</div>
			<label class="velox-switch"><input type="checkbox" data-setting="scan_email" <?php checked( ! empty( $s['scan_email'] ) ); ?>><span class="velox-switch-track"></span></label>
		</div>
		<div class="velox-tool-actions">
			<button class="velox-btn velox-btn--primary velox-util-save"><?php esc_html_e( 'Save', 'velox' ); ?></button>
		</div>
	</div>

	<div class="velox-note velox-note--info vxsec-limits">
		<strong><?php esc_html_e( 'What no scanner can promise', 'velox' ); ?></strong>
		<p><?php esc_html_e( 'WordPress and plugin files from WordPress.org are checked exactly — a changed file is certain. Everything else (your theme, premium plugins like Oxygen) can only be read for the tricks backdoors use, so brand-new malware can slip past, just as it can with Wordfence.', 'velox' ); ?></p>
		<p><?php esc_html_e( 'And a scanner running inside a hacked site can be fooled by malware that hides outside WordPress or tampers with the scanner itself. "No signs of a hack" is strong evidence, not proof. Keep backups and keep everything updated.', 'velox' ); ?></p>
	</div>
</div>
