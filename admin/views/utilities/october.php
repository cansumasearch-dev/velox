<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$on = Velox_Settings::get( 'util_october', false );

if ( ! function_exists( 'velox_oct_bytes' ) ) {
	function velox_oct_bytes( $b ) {
		$b = (int) $b;
		if ( $b <= 0 ) { return '—'; }
		$u = array( 'B', 'KB', 'MB', 'GB' );
		$i = (int) floor( log( $b, 1024 ) );
		$i = max( 0, min( $i, 3 ) );
		return round( $b / pow( 1024, $i ), $i ? 1 : 0 ) . ' ' . $u[ $i ];
	}
	function velox_oct_dur( $ms ) {
		$ms = (int) $ms;
		if ( $ms < 1000 ) { return $ms . ' ms'; }
		$s = $ms / 1000;
		if ( $s < 60 ) { return round( $s, 1 ) . ' s'; }
		return floor( $s / 60 ) . 'm ' . round( $s - floor( $s / 60 ) * 60 ) . 's';
	}
}
?>
<div class="velox-page-head">
	<h1 class="velox-h2">OctoberCMS theme</h1>
	<p class="velox-sub">Scan the whole site and export it as an importable OctoberCMS theme: every published page becomes a <code>.htm</code> page, the shared header/footer are lifted into partials, the CSS is converted into the theme's SCSS structure, and the media you actually use (and have in the library) is bundled in. Builds are versioned — re-scan to pick up new pages, and keep older versions to revert to.</p>
</div>

<?php if ( ! $on ) : ?>
	<div class="velox-panel">
		<label class="velox-inline-toggle">
			<span><strong>Enable OctoberCMS theme builder</strong></span>
			<span class="velox-switch"><input type="checkbox" data-setting="util_october" id="velox-october-toggle"><span class="velox-switch-track"></span></span>
		</label>
		<p class="velox-hint" style="margin-top:14px;">Turn this on to scan your site and generate themes. Nothing is changed on your live site — it only reads the rendered pages.</p>
	</div>
<?php else :
	Velox_October::maybe_install();
	$builds = Velox_October::builds();
	// Group by project, newest version first.
	$projects = array();
	foreach ( $builds as $b ) {
		$projects[ $b['project'] ][] = $b;
	}
	?>

	<div class="velox-panel velox-tool-form">
		<h3 class="velox-panel-title">Build a theme</h3>
		<div class="velox-field">
			<span class="velox-field-label">Theme name <span class="velox-hint" style="display:inline;font-weight:400;">(optional — defaults to your domain)</span></span>
			<input type="text" class="velox-input" id="oct-name" placeholder="my-project" style="max-width:340px;">
		</div>
		<div class="velox-tool-actions" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
			<button class="velox-btn velox-btn--primary" id="oct-build">Scan &amp; build theme</button>
			<button class="velox-btn velox-btn--ghost" id="oct-diag">Test connection</button>
			<span class="velox-hint" id="oct-status" style="display:none;"></span>
		</div>
		<div id="oct-diag-out" class="oct-diag" style="display:none;"></div>
		<p class="velox-hint" style="margin-top:10px;">This crawls every published page over HTTP, so a large site can take a minute. If you're behind Cloudflare, run <strong>Test connection</strong> first — the builder falls back to your origin server automatically, but a strict WAF can still block it.</p>
	</div>

	<?php if ( empty( $projects ) ) : ?>
		<div class="velox-panel"><p class="velox-hint" style="padding:8px 0;">No builds yet. Name your theme above and hit <strong>Scan &amp; build</strong>.</p></div>
	<?php else : ?>
		<?php foreach ( $projects as $proj => $versions ) :
			$latest = $versions[0];
			?>
			<div class="velox-panel velox-panel--flush oct-project" data-project="<?php echo esc_attr( $proj ); ?>">
				<div class="oct-project-head">
					<div>
						<span class="oct-project-name"><?php echo esc_html( $proj ); ?></span>
						<span class="oct-project-meta"><?php echo count( $versions ); ?> version<?php echo count( $versions ) === 1 ? '' : 's'; ?></span>
					</div>
					<div style="display:flex;gap:8px;">
						<?php if ( count( $versions ) > 1 ) : ?>
							<a class="velox-btn velox-btn--ghost velox-btn--sm" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=velox_october_download&project=' . rawurlencode( $proj ) ), 'velox_october_dl' ) ); ?>">⤓ Download all</a>
						<?php endif; ?>
						<button class="velox-btn velox-btn--ghost velox-btn--sm oct-rescan" data-project="<?php echo esc_attr( $proj ); ?>">↻ Re-scan website</button>
					</div>
				</div>
				<table class="vmail-table oct-table">
					<thead><tr><th>Version</th><th>Built</th><th>Duration</th><th>Pages</th><th>Media</th><th>Size</th><th class="vmail-th-act"></th></tr></thead>
					<tbody>
						<?php foreach ( $versions as $idx => $b ) :
							$dl = wp_nonce_url( admin_url( 'admin-ajax.php?action=velox_october_download&id=' . (int) $b['id'] ), 'velox_october_dl' );
							?>
							<tr class="oct-row" data-id="<?php echo (int) $b['id']; ?>">
								<td>
									<span class="oct-ver">v<?php echo (int) $b['version']; ?></span>
									<?php if ( 0 === $idx ) : ?><span class="oct-badge oct-badge--latest">Latest</span><?php else : ?><span class="oct-badge">Revert point</span><?php endif; ?>
								</td>
								<td>
									<span class="oct-when"><?php echo esc_html( date_i18n( 'M j, Y · H:i', strtotime( $b['finished'] ? $b['finished'] : $b['started'] ) ) ); ?></span>
								</td>
								<td><?php echo esc_html( velox_oct_dur( $b['duration_ms'] ) ); ?></td>
								<td><?php echo (int) $b['pages']; ?></td>
								<td><?php echo (int) $b['media']; ?></td>
								<td><?php echo esc_html( velox_oct_bytes( $b['size'] ) ); ?></td>
								<td class="vmail-th-act oct-actions">
									<a class="velox-btn velox-btn--primary velox-btn--sm" href="<?php echo esc_url( $dl ); ?>">Download</a>
									<button class="velox-btn velox-btn--ghost velox-btn--sm oct-del">Delete</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endforeach; ?>

		<div class="velox-panel velox-mail-disable">
			<label class="velox-inline-toggle">
				<span><strong>OctoberCMS builder is on</strong> <span class="velox-hint" style="display:inline;">— switch off to hide it.</span></span>
				<span class="velox-switch"><input type="checkbox" data-setting="util_october" id="velox-october-toggle" checked><span class="velox-switch-track"></span></span>
			</label>
		</div>
	<?php endif; ?>
<?php endif; ?>
