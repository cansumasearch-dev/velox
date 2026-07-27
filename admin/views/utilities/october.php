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
	<h1 class="velox-h2"><?php esc_html_e('OctoberCMS theme', 'velox'); ?></h1>
	<p class="velox-sub"><?php printf( esc_html__( 'Scan the whole site and export it as an importable OctoberCMS theme: every published page becomes a %s page, the shared header/footer are lifted into partials, the CSS is converted into the theme\'s SCSS structure, and the media you actually use (and have in the library) is bundled in. Builds are versioned — re-scan to pick up new pages, and keep older versions to revert to.', 'velox' ), '<code>' . esc_html__( '.htm', 'velox' ) . '</code>' ); ?></p>
</div>

<?php if ( ! $on ) : ?>
	<div class="velox-panel">
		<label class="velox-inline-toggle">
			<span><strong><?php esc_html_e('Enable OctoberCMS theme builder', 'velox'); ?></strong></span>
			<span class="velox-switch"><input type="checkbox" data-setting="util_october" id="velox-october-toggle"><span class="velox-switch-track"></span></span>
		</label>
		<p class="velox-hint" style="margin-top:14px;"><?php esc_html_e('Turn this on to scan your site and generate themes. Nothing is changed on your live site — it only reads the rendered pages.', 'velox'); ?></p>
	</div>
<?php else :
	Velox_October::maybe_install();
	$edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$hub_url = admin_url( 'admin.php?page=velox-utilities&tool=october' );

	if ( $edit_id ) :
		?>
		<div class="velox-page-head velox-page-head--row">
			<div>
				<a class="vmail-back-link" href="<?php echo esc_url( $hub_url ); ?>">&larr; All builds</a>
				<h1 class="velox-h2" style="margin-top:8px;"><?php esc_html_e('Rename classes &amp; IDs', 'velox'); ?></h1>
				<p class="velox-sub"><?php printf( esc_html__( 'Give the WordPress/Oxygen names something human. Every change is applied to the HTML %s the CSS together, and the preview updates as you type. When you\'re done, download a renamed version.', 'velox' ), '<em>' . esc_html__( 'and', 'velox' ) . '</em>' ); ?></p>
			</div>
		</div>
		<div class="oct-editor" id="oct-editor" data-build="<?php echo (int) $edit_id; ?>" data-dlnonce="<?php echo esc_attr( wp_create_nonce( 'velox_october_dl' ) ); ?>" data-ajaxurl="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<div class="oct-editor-panel">
				<div class="oct-editor-toolbar">
					<input type="text" class="velox-input velox-input--sm" id="oct-tok-filter" placeholder="Filter names…" style="max-width:200px;">
					<label class="oct-tok-tabs">
						<button class="oct-tok-tab is-active" data-tab="classes"><?php esc_html_e('Classes', 'velox'); ?></button>
						<button class="oct-tok-tab" data-tab="ids"><?php esc_html_e('IDs', 'velox'); ?></button>
					</label>
					<span class="velox-hint" id="oct-edit-status" style="margin-left:auto;"></span>
					<button class="velox-btn velox-btn--primary velox-btn--sm" id="oct-apply"><?php esc_html_e('Download renamed', 'velox'); ?></button>
				</div>
				<div class="oct-tok-list" id="oct-tok-list"><p class="velox-hint" style="padding:18px;"><?php esc_html_e('Loading…', 'velox'); ?></p></div>
			</div>
			<div class="oct-editor-preview">
				<span class="vxck-preview-label"><?php esc_html_e('Live preview', 'velox'); ?></span>
				<iframe id="oct-preview" class="oct-preview-frame" title="<?php esc_attr_e('Preview', 'velox'); ?>"></iframe>
			</div>
		</div>
		<?php
		return;
	endif;

	$builds = Velox_October::builds();
	// Group by project, newest version first.
	$projects = array();
	foreach ( $builds as $b ) {
		$projects[ $b['project'] ][] = $b;
	}
	?>

	<div class="velox-panel velox-tool-form">
		<h3 class="velox-panel-title"><?php esc_html_e('Build a theme', 'velox'); ?></h3>
		<div class="velox-field">
			<span class="velox-field-label"><?php esc_html_e('Theme name', 'velox'); ?> <span class="velox-hint" style="display:inline;font-weight:400;"><?php esc_html_e('(optional — defaults to your domain)', 'velox'); ?></span></span>
			<input type="text" class="velox-input" id="oct-name" placeholder="my-project" style="max-width:340px;">
		</div>
		<div class="velox-tool-actions" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
			<button class="velox-btn velox-btn--primary" id="oct-build"><?php esc_html_e('Scan &amp; build theme', 'velox'); ?></button>
			<button class="velox-btn velox-btn--ghost" id="oct-diag"><?php esc_html_e('Test connection', 'velox'); ?></button>
			<span class="velox-hint" id="oct-status" style="display:none;"></span>
		</div>
		<div id="oct-diag-out" class="oct-diag" style="display:none;"></div>
		<p class="velox-hint" style="margin-top:10px;"><?php printf( esc_html__( 'This crawls every published page over HTTP, so a large site can take a minute. If you\'re behind Cloudflare, run %s first — the builder falls back to your origin server automatically, but a strict WAF can still block it.', 'velox' ), '<strong>' . esc_html__( 'Test connection', 'velox' ) . '</strong>' ); ?></p>
	</div>

	<?php if ( empty( $projects ) ) : ?>
		<div class="velox-panel"><p class="velox-hint" style="padding:8px 0;"><?php printf( esc_html__( 'No builds yet. Name your theme above and hit %s.', 'velox' ), '<strong>' . esc_html__( 'Scan &amp; build', 'velox' ) . '</strong>' ); ?></p></div>
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
							<a class="velox-btn velox-btn--ghost velox-btn--sm" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=velox_october_download&project=' . rawurlencode( $proj ) ), 'velox_october_dl' ) ); ?>"><?php esc_html_e('⤓ Download all', 'velox'); ?></a>
						<?php endif; ?>
						<button class="velox-btn velox-btn--ghost velox-btn--sm oct-rescan" data-project="<?php echo esc_attr( $proj ); ?>"><?php esc_html_e('↻ Re-scan website', 'velox'); ?></button>
					</div>
				</div>
				<table class="vmail-table oct-table">
					<thead><tr><th><?php esc_html_e('Version', 'velox'); ?></th><th><?php esc_html_e('Built', 'velox'); ?></th><th><?php esc_html_e('Duration', 'velox'); ?></th><th><?php esc_html_e('Pages', 'velox'); ?></th><th><?php esc_html_e('Media', 'velox'); ?></th><th><?php esc_html_e('Size', 'velox'); ?></th><th class="vmail-th-act"></th></tr></thead>
					<tbody>
						<?php foreach ( $versions as $idx => $b ) :
							$dl    = wp_nonce_url( admin_url( 'admin-ajax.php?action=velox_october_download&id=' . (int) $b['id'] ), 'velox_october_dl' );
							$dlm   = wp_nonce_url( admin_url( 'admin-ajax.php?action=velox_october_download&media=1&id=' . (int) $b['id'] ), 'velox_october_dl' );
							$man   = json_decode( (string) $b['manifest'], true );
							$nimg  = ( is_array( $man ) && isset( $man['images'] ) ) ? (int) $man['images'] : 0;
							?>
							<tr class="oct-row" data-id="<?php echo (int) $b['id']; ?>">
								<td>
									<span class="oct-ver">v<?php echo (int) $b['version']; ?></span>
									<?php if ( 0 === $idx ) : ?><span class="oct-badge oct-badge--latest"><?php esc_html_e('Latest', 'velox'); ?></span><?php else : ?><span class="oct-badge"><?php esc_html_e('Revert point', 'velox'); ?></span><?php endif; ?>
								</td>
								<td>
									<span class="oct-when"><?php echo esc_html( date_i18n( 'M j, Y · H:i', strtotime( $b['finished'] ? $b['finished'] : $b['started'] ) ) ); ?></span>
								</td>
								<td><?php echo esc_html( velox_oct_dur( $b['duration_ms'] ) ); ?></td>
								<td><?php echo (int) $b['pages']; ?></td>
								<td><?php echo (int) $b['media']; ?><?php if ( $nimg ) : ?> <span class="velox-hint" style="display:inline;">(<?php echo (int) $nimg; ?> img)</span><?php endif; ?></td>
								<td><?php echo esc_html( velox_oct_bytes( $b['size'] ) ); ?></td>
								<td class="vmail-th-act oct-actions">
									<a class="velox-btn velox-btn--primary velox-btn--sm" href="<?php echo esc_url( $dl ); ?>"><?php esc_html_e('Download theme', 'velox'); ?></a>
									<?php if ( $nimg ) : ?><a class="velox-btn velox-btn--ghost velox-btn--sm" href="<?php echo esc_url( $dlm ); ?>" title="<?php esc_attr_e('Unzip into storage/app/media/', 'velox'); ?>"><?php printf( esc_html__( 'Download media (%d)', 'velox' ), (int) $nimg ); ?></a><?php endif; ?>
									<a class="velox-btn velox-btn--ghost velox-btn--sm" href="<?php echo esc_url( admin_url( 'admin.php?page=velox-utilities&tool=october&edit=' . (int) $b['id'] ) ); ?>"><?php esc_html_e('Edit names', 'velox'); ?></a>
									<button class="velox-btn velox-btn--ghost velox-btn--sm oct-del"><?php esc_html_e('Delete', 'velox'); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endforeach; ?>

		<div class="velox-panel velox-mail-disable">
			<label class="velox-inline-toggle">
				<span><strong><?php esc_html_e('OctoberCMS builder is on', 'velox'); ?></strong> <span class="velox-hint" style="display:inline;"><?php esc_html_e('— switch off to hide it.', 'velox'); ?></span></span>
				<span class="velox-switch"><input type="checkbox" data-setting="util_october" id="velox-october-toggle" checked><span class="velox-switch-track"></span></span>
			</label>
		</div>
	<?php endif; ?>
<?php endif; ?>
