<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$on   = Velox_Settings::get( 'util_mail', false );
$s    = Velox_Settings::all();
$edit = isset( $_GET['form'] ) ? sanitize_text_field( wp_unslash( $_GET['form'] ) ) : '';
$base = admin_url( 'admin.php?page=velox-utilities&tool=mail' );
?>

<?php if ( ! $on ) : ?>
	<div class="velox-page-head">
		<h1 class="velox-h2">Mail &amp; forms</h1>
		<p class="velox-sub">Build forms, route submissions to styled notification emails (to you and to the customer), and send everything reliably over SMTP.</p>
	</div>

	<div class="velox-panel">
		<label class="velox-inline-toggle">
			<span><strong>Enable Mail &amp; forms</strong></span>
			<span class="velox-switch"><input type="checkbox" data-setting="util_mail" id="velox-mail-toggle"><span class="velox-switch-track"></span></span>
		</label>
		<p class="velox-hint" style="margin-top:14px;">Turn this on to create forms and configure SMTP. Embed any form with <code>[velox_form id="1"]</code> — including inside an Oxygen Shortcode element.</p>
	</div>

<?php elseif ( '' !== $edit ) :
	$form = ( 'new' === $edit ) ? Velox_Forms::blank_form() : Velox_Forms::get_form( (int) $edit );
	if ( ! $form ) { $form = Velox_Forms::blank_form(); }
	$captcha_ready = Velox_Forms::captcha_ready();
	$fid_int       = ( 'new' === $edit ) ? 0 : (int) $edit;
	$stat_subs     = $fid_int ? (int) Velox_Forms::submission_count( $fid_int ) : 0;
	$stat_recent   = $fid_int ? (int) Velox_Forms::submission_count_recent( 7 ) : 0;
	$stat_fields   = is_array( $form['fields'] ?? null ) ? count( $form['fields'] ) : 0;
	$stat_emails   = 0;
	if ( ! empty( $form['emails'] ) && is_array( $form['emails'] ) ) {
		foreach ( $form['emails'] as $em ) { if ( ! empty( $em['enabled'] ) ) { $stat_emails++; } }
	}
	?>
	<div class="vmail-builder vmail-sb" id="vmail-builder">
		<div class="vmail-nav">
			<div class="vmail-nav-left">
				<a class="vmail-nav-back" href="<?php echo esc_url( $base ); ?>" title="All forms"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></a>
				<div class="vmail-nav-crumb">Utilities <span>/</span> <b>Mail &amp; forms</b></div>
				<div class="vmail-nav-vsep"></div>
				<input type="text" class="velox-input vmail-title-input" id="vmail-title" value="<?php echo esc_attr( $form['title'] ); ?>" placeholder="Form name">
				<label class="vmail-nav-switch" title="Turn this form on or off">
					<input type="checkbox" id="vmail-enabled" <?php checked( ! isset( $form['enabled'] ) || ! empty( $form['enabled'] ) ); ?>>
					<span class="vmail-switch-track"></span>
				</label>
				<span class="vmail-nav-onoff" id="vmail-onoff-label"><?php echo ( ! isset( $form['enabled'] ) || ! empty( $form['enabled'] ) ) ? 'On' : 'Off'; ?></span>
				<button type="button" class="vmail-nav-sc" data-code='[velox_form id="<?php echo (int) $form['id']; ?>"]' title="Form shortcode — click to copy">
					<span class="vmail-nav-sc-tag">Shortcode</span>
					<code>[velox_form id="<?php echo (int) $form['id']; ?>"]</code>
				</button>
			</div>
			<div class="vmail-nav-mode">
				<button type="button" class="vmail-tab vmail-modebtn is-active" data-tab="build"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 5h18M3 12h18M3 19h12"/></svg> Build</button>
				<button type="button" class="vmail-modebtn" id="vmail-style-btn"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg> Style</button>
				<button type="button" class="vmail-modebtn" id="vmail-preview-btn"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg> Preview</button>
			</div>
			<div class="vmail-nav-right">
				<button type="button" class="vmail-tab vmail-nav-ghost" data-tab="notify"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg> Notifications</button>
				<button type="button" class="vmail-tab vmail-nav-ghost" data-tab="settings"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> Settings</button>
				<button class="velox-btn velox-btn--primary" id="vmail-save">Save form</button>
			</div>
		</div>

		<div class="vmail-panel" data-panel="build">
			<div class="vmail-sb-body">
				<!-- zone 1: palette -->
				<div class="vmail-sb-zone vmail-sb-zone--pal">
					<div class="vmail-sb-zhead"><span class="t">Add field</span></div>
					<div class="vmail-sb-search">
						<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
						<input type="text" class="vmail-palette-search-input" id="vmail-palette-search" placeholder="Search fields…">
					</div>
					<div id="vmail-palette" class="vmail-palette-list"></div>
				</div>
				<!-- zone 2: canvas -->
				<div class="vmail-sb-zone vmail-sb-zone--canvas">
					<div class="vmail-sb-zhead"><span class="t">Form canvas</span><span class="t vmail-sb-zhint">drag to reorder</span></div>
					<div class="vmail-canvas-wrap">
						<div class="vmail-canvas" id="vmail-canvas"></div>
					</div>
				</div>
				<!-- zone 3: inspector -->
				<div class="vmail-sb-zone vmail-sb-zone--insp">
					<aside class="vmail-inspector" id="vmail-inspector"></aside>
				</div>
			</div>
		</div>

		<div class="vmail-panel" data-panel="notify" hidden>
			<p class="velox-hint" style="margin-bottom:14px;">Notifications are sent when the form is submitted. Use the <strong>Insert field</strong> menu to drop in merge tags like <code>{inputs.email}</code>, or <code>{all_fields}</code> for the whole submission.</p>
			<div id="vmail-emails"></div>
		</div>

		<div class="vmail-panel" data-panel="settings" hidden>
			<div class="velox-panel">
				<div class="velox-field">
					<span class="velox-field-label">Success message</span>
					<input type="text" class="velox-input" id="vmail-success" value="<?php echo esc_attr( $form['success'] ); ?>">
					<span class="velox-hint">Shown after the form is submitted successfully.</span>
				</div>
				<p class="velox-hint" style="margin:2px 0 14px;">The submit button text, colours and full styling now live on the <strong>form canvas</strong> — click the button there, or open the <strong>Style editor</strong>.</p>
				<?php
				$captcha_gate = Velox_Forms::captcha_enabled();
				$captcha_desc = ! $captcha_gate
					? 'Locked — switch CAPTCHA on under Mail settings first.'
					: ( $captcha_ready ? 'Keys are set — the widget will appear on the form.' : 'Enabled, but add your keys under Mail settings to make it work.' );
				?>
				<label class="velox-toggle-row<?php echo $captcha_gate ? '' : ' is-locked'; ?>" style="cursor:<?php echo $captcha_gate ? 'pointer' : 'not-allowed'; ?>;" title="<?php echo $captcha_gate ? '' : esc_attr( 'Enable CAPTCHA globally under Mail settings to use this.' ); ?>">
					<div class="velox-toggle-meta">
						<span class="velox-toggle-label">Require CAPTCHA <?php if ( ! $captcha_gate ) : ?><span class="vmail-lock-ic" aria-hidden="true">🔒</span><?php endif; ?></span>
						<span class="velox-toggle-desc"><?php echo esc_html( $captcha_desc ); ?></span>
					</div>
					<span class="velox-switch"><input type="checkbox" id="vmail-captcha" <?php checked( ! empty( $form['captcha'] ) && $captcha_gate ); ?> <?php disabled( ! $captcha_gate ); ?>><span class="velox-switch-track"></span></span>
				</label>
				<?php if ( 'new' !== $edit ) : ?>
					<div class="velox-field" style="margin-top:8px;">
						<span class="velox-field-label">Shortcode</span>
						<code class="velox-mail-shortcode">[velox_form id="<?php echo (int) $form['id']; ?>"]</code>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Full-screen style editor -->
	<div class="vse" id="vmail-style-editor" hidden>
		<div class="vmail-nav vmail-nav--vse">
			<div class="vmail-nav-left">
				<a class="vmail-nav-back" id="vse-back" title="Back to Build" style="cursor:pointer"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></a>
				<div class="vmail-nav-crumb">Utilities <span>/</span> <b>Mail &amp; forms</b></div>
				<div class="vmail-nav-vsep"></div>
				<span class="vmail-nav-title vmail-nav-title--static"><?php echo esc_html( $form['title'] ); ?></span>
				<label class="vmail-nav-switch" title="Turn this form on or off">
					<input type="checkbox" id="vse-enabled" <?php checked( ! isset( $form['enabled'] ) || ! empty( $form['enabled'] ) ); ?>>
					<span class="vmail-switch-track"></span>
				</label>
				<span class="vmail-nav-onoff" id="vse-onoff-label"><?php echo ( ! isset( $form['enabled'] ) || ! empty( $form['enabled'] ) ) ? 'On' : 'Off'; ?></span>
				<button type="button" class="vmail-nav-sc" data-code='[velox_form id="<?php echo (int) $form['id']; ?>"]' title="Form shortcode — click to copy">
					<span class="vmail-nav-sc-tag">Shortcode</span>
					<code>[velox_form id="<?php echo (int) $form['id']; ?>"]</code>
				</button>
			</div>
			<div class="vmail-nav-mode">
				<button type="button" class="vmail-modebtn" id="vse-to-build"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 5h18M3 12h18M3 19h12"/></svg> Build</button>
				<button type="button" class="vmail-modebtn is-active"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg> Style</button>
				<button type="button" class="vmail-modebtn" id="vse-to-preview"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg> Preview</button>
			</div>
			<div class="vmail-nav-right">
				<div class="vmail-nav-devs" id="vse-device">
					<button class="is-on" data-dev="desktop" title="Desktop"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8"/></svg></button>
					<button data-dev="mobile" title="Mobile"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="7" y="2" width="10" height="20" rx="2.5"/></svg></button>
				</div>
				<button class="vmail-nav-ghost" id="vse-reset" type="button"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8m0-5v5h5"/></svg> Reset</button>
				<button class="velox-btn velox-btn--primary" id="vse-save" type="button">Save styles</button>
			</div>
		</div>
		<div class="vse-body">
			<div class="vse-left">
				<div class="vse-picker">
					<div class="vse-picker-head">
						<div class="tt">What do you want to style?</div>
					</div>
					<div class="vse-tree" id="vse-tree"></div>
				</div>
				<div class="vse-controls" id="vse-controls"></div>
			</div>
			<div class="vse-stage">
				<div class="vse-live"><span class="vse-pulse"></span> Live preview</div>
				<div class="vse-canvas" id="vse-canvas"><div class="vse-form" id="vse-form"></div></div>
			</div>
		</div>
		<style id="vse-live-css"></style>
	</div>
	<script type="application/json" id="vmail-data"><?php echo wp_json_encode( $form ); ?></script>
	<script type="application/json" id="vmail-meta"><?php echo wp_json_encode( array( 'captcha_ready' => $captcha_ready, 'captcha_enabled' => Velox_Forms::captcha_enabled(), 'admin_email' => get_option( 'admin_email' ), 'site_name' => get_bloginfo( 'name' ), 'base' => $base ) ); ?></script>

<?php else :
	$forms       = Velox_Forms::forms();
	$entries_for = isset( $_GET['entries'] ) ? (int) $_GET['entries'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
	?>

	<?php if ( $entries_for ) :
		// ===================== ENTRIES BROWSER =====================
		$eform  = Velox_Forms::get_form( $entries_for );
		$labels = Velox_Forms::field_labels( $entries_for );
		$subs   = Velox_Forms::submissions( $entries_for, 500 );
		$ititle = $eform ? $eform['title'] : 'Form';
		?>
		<div class="velox-page-head velox-page-head--row">
			<div>
				<a class="vmail-back-link" href="<?php echo esc_url( $base ); ?>">&larr; All forms</a>
				<h1 class="velox-h2" style="margin-top:8px;"><?php echo esc_html( $ititle ); ?> <span class="vmail-head-sub">— entries</span></h1>
				<p class="velox-sub"><?php echo count( $subs ); ?> submission<?php echo 1 === count( $subs ) ? '' : 's'; ?> received through this form.</p>
			</div>
			<div class="vmail-entries-actions">
				<?php if ( ! empty( $subs ) ) :
					$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=velox_form_export&form=' . $entries_for ), 'velox_form_export_' . $entries_for );
					?>
					<a class="velox-btn velox-btn--ghost" href="<?php echo esc_url( $export_url ); ?>">Export CSV</a>
				<?php endif; ?>
				<a class="velox-btn velox-btn--ghost" href="<?php echo esc_url( $base . '&form=' . $entries_for ); ?>">Edit form</a>
			</div>
		</div>

		<div class="velox-panel velox-panel--flush">
			<?php if ( empty( $subs ) ) : ?>
				<p class="velox-hint" style="padding:26px;">No entries yet. Once someone submits this form, every message lands here — what they wrote, when, and from where.</p>
			<?php else : ?>
				<div class="vmail-entries">
					<?php foreach ( $subs as $sub ) :
						$d = json_decode( $sub['data'], true );
						$d = is_array( $d ) ? $d : array();
						$preview = array();
						foreach ( $d as $pv ) {
							if ( is_scalar( $pv ) && '' !== trim( (string) $pv ) ) { $preview[] = trim( (string) $pv ); }
							if ( count( $preview ) >= 2 ) { break; }
						}
						?>
						<details class="vmail-entry" data-id="<?php echo (int) $sub['id']; ?>">
							<summary class="vmail-entry-sum">
								<span class="vmail-entry-date"><?php echo esc_html( date_i18n( 'M j, Y · H:i', strtotime( $sub['created'] ) ) ); ?></span>
								<span class="vmail-entry-preview"><?php echo esc_html( implode( '  ·  ', $preview ) ); ?></span>
								<span class="vmail-entry-chev" aria-hidden="true">▾</span>
							</summary>
							<div class="vmail-entry-body">
								<dl class="vmail-entry-dl">
									<?php foreach ( $d as $k => $v ) :
										$lbl = isset( $labels[ $k ] ) ? $labels[ $k ] : ucwords( str_replace( array( '_', '-' ), ' ', $k ) );
										?>
										<dt><?php echo esc_html( $lbl ); ?></dt>
										<dd><?php echo nl2br( esc_html( is_array( $v ) ? implode( ', ', $v ) : (string) $v ) ); ?></dd>
									<?php endforeach; ?>
								</dl>
								<div class="vmail-entry-foot">
									<span class="vmail-entry-meta">#<?php echo (int) $sub['id']; ?><?php echo ! empty( $sub['ip'] ) ? ' · IP ' . esc_html( $sub['ip'] ) : ''; ?></span>
									<button class="velox-btn velox-btn--ghost velox-mail-sub-del" data-id="<?php echo (int) $sub['id']; ?>">Delete entry</button>
								</div>
							</div>
						</details>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

	<?php else :
		// ===================== DASHBOARD =====================
		$total_entries = Velox_Forms::submission_count();
		$recent        = Velox_Forms::submission_count_recent( 7 );
		$log           = Velox_Mail::log( 50 );
		?>
		<div class="velox-page-head velox-page-head--row">
			<div>
				<h1 class="velox-h2">Mail &amp; forms</h1>
				<p class="velox-sub">Build forms, route submissions to styled notification emails, and send reliably over SMTP.</p>
			</div>
			<a class="velox-btn velox-btn--primary" href="<?php echo esc_url( $base . '&form=new' ); ?>">+ New form</a>
		</div>

		<div class="vmail-stats">
			<div class="vmail-stat"><span class="vmail-stat-n"><?php echo count( $forms ); ?></span><span class="vmail-stat-l">Forms</span></div>
			<div class="vmail-stat"><span class="vmail-stat-n"><?php echo (int) $total_entries; ?></span><span class="vmail-stat-l">Total entries</span></div>
			<div class="vmail-stat"><span class="vmail-stat-n"><?php echo (int) $recent; ?></span><span class="vmail-stat-l">Last 7 days</span></div>
		</div>

		<?php
		// ===================== GLOBAL SUBMISSIONS INBOX =====================
		$inbox = Velox_Forms::inbox( 200 );
		$vx_initials = function ( $name ) {
			$name = trim( wp_strip_all_tags( (string) $name ) );
			if ( '' === $name ) { return '?'; }
			$parts = preg_split( '/\s+/', $name );
			$a = mb_substr( $parts[0], 0, 1 );
			$b = count( $parts ) > 1 ? mb_substr( end( $parts ), 0, 1 ) : '';
			return mb_strtoupper( $a . $b );
		};
		$vx_unread = 0;
		foreach ( $inbox as $r ) { if ( empty( $r['read'] ) ) { $vx_unread++; } }
		?>
		<div class="velox-section-title">Inbox</div>
		<div class="velox-panel velox-panel--flush vmail-inbox" id="vmail-inbox">
			<?php if ( empty( $inbox ) ) : ?>
				<p class="velox-hint" style="padding:26px;">No submissions yet. Every message sent through any of your forms lands here — who wrote, when, through which form, and everything they filled out.</p>
			<?php else : ?>
				<div class="vmail-inbox-filters" role="tablist" aria-label="Filter submissions">
					<button type="button" class="vmail-filter is-on" data-filter="all">All</button>
					<button type="button" class="vmail-filter" data-filter="unread">Unread<?php echo $vx_unread ? ' <span class="vmail-filter-count">' . (int) $vx_unread . '</span>' : ''; ?></button>
					<button type="button" class="vmail-filter" data-filter="pinned">Pinned</button>
					<button type="button" class="vmail-filter" data-filter="done">Done</button>
					<span class="vmail-folder-chips" id="vmail-folder-chips"></span>
					<button type="button" class="vmail-folder-manage" id="vmail-folder-manage" title="Manage folders">+ Folders</button>
					<button type="button" class="vmail-filter vmail-filter--deleted" data-filter="deleted">Deleted</button>
				</div>
				<script type="application/json" id="vmail-folders-data"><?php echo wp_json_encode( Velox_Forms::folders() ); ?></script>
				<div class="vmail-bulkbar" id="vmail-bulkbar" hidden>
					<label class="vmail-bulk-all" title="Select all shown"><input type="checkbox" id="vmail-check-all"></label>
					<span class="vmail-bulk-count" id="vmail-bulk-count">0 selected</span>
					<div class="vmail-bulk-acts">
						<button type="button" class="velox-btn velox-btn--ghost velox-btn--sm" data-bulk="read">Mark read</button>
						<button type="button" class="velox-btn velox-btn--ghost velox-btn--sm" data-bulk="done">Mark done</button>
						<button type="button" class="velox-btn velox-btn--ghost velox-btn--sm" data-bulk="delete">Delete</button>
					</div>
					<button type="button" class="vmail-bulk-clear" id="vmail-bulk-clear">Clear</button>
				</div>
				<div class="vmail-inbox-split">
					<div class="vmail-inbox-list" id="vmail-inbox-list" role="listbox" aria-label="Submissions">
						<?php foreach ( $inbox as $i => $row ) : ?>
							<div class="vmail-inbox-item<?php echo 0 === $i ? ' is-active' : ''; ?><?php echo empty( $row['read'] ) ? ' is-unread' : ''; ?><?php echo ! empty( $row['pinned'] ) ? ' is-pinned' : ''; ?>"
								data-id="<?php echo (int) $row['id']; ?>"
								data-read="<?php echo empty( $row['read'] ) ? '0' : '1'; ?>"
								data-pinned="<?php echo ! empty( $row['pinned'] ) ? '1' : '0'; ?>"
								data-status="<?php echo esc_attr( $row['status'] ); ?>"
								data-folder="<?php echo esc_attr( $row['folder'] ); ?>"
								data-email="<?php echo esc_attr( $row['email'] ); ?>"
								role="option" aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>">
								<label class="vmail-inbox-check" title="Select"><input type="checkbox" class="vmail-check" data-id="<?php echo (int) $row['id']; ?>"></label>
								<button type="button" class="vmail-inbox-open" aria-label="Open submission">
									<span class="vmail-avatar" aria-hidden="true"><?php echo esc_html( $vx_initials( $row['who'] ) ); ?></span>
									<span class="vmail-inbox-body">
										<span class="vmail-inbox-line1">
											<span class="vmail-inbox-who"><?php echo esc_html( $row['who'] ); ?></span>
											<span class="vmail-inbox-when"><?php echo esc_html( date_i18n( 'M j', strtotime( $row['created'] ) ) ); ?></span>
										</span>
										<span class="vmail-inbox-form"><?php echo esc_html( $row['form_title'] ); ?></span>
										<span class="vmail-inbox-prev"><?php echo esc_html( $row['preview'] ); ?></span>
									</span>
									<span class="vmail-inbox-marks" aria-hidden="true">
										<svg class="vmail-pin-ic" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4v6l-2 4h10l-2-4V4M12 14v6M8 4h8"/></svg>
										<span class="vmail-unread-dot"></span>
									</span>
								</button>
								<button type="button" class="vmail-inbox-del" data-id="<?php echo (int) $row['id']; ?>" title="Delete this submission" aria-label="Delete this submission">
									<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
								</button>
							</div>
						<?php endforeach; ?>
						<div class="vmail-inbox-nomatch" hidden>Nothing here.</div>
					</div>
					<div class="vmail-inbox-detail" id="vmail-inbox-detail" aria-live="polite">
						<div class="vmail-inbox-empty-detail">Select a submission to read it.</div>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<?php
		$vx_ru = wp_get_current_user();
		$vx_reply_email = ( $vx_ru && $vx_ru->user_email ) ? $vx_ru->user_email : get_option( 'admin_email' );
		$vx_reply_name  = ( $vx_ru && $vx_ru->display_name ) ? $vx_ru->display_name : get_bloginfo( 'name' );
		$vx_reply_tpls  = Velox_Forms::reply_templates();
		$vx_tb = function ( $cmd, $label, $svg, $extra = '' ) {
			return '<button type="button" class="vmail-tb-btn" data-cmd="' . esc_attr( $cmd ) . '" title="' . esc_attr( $label ) . '" aria-label="' . esc_attr( $label ) . '"' . $extra . '><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $svg . '</svg></button>';
		};
		?>
		<div class="vmail-modal-overlay" id="vmail-reply-modal" hidden>
			<div class="vmail-modal" role="dialog" aria-modal="true" aria-label="Reply to submission">
				<div class="vmail-modal-head">
					<div class="vmail-modal-head-l">
						<span class="vmail-avatar" id="vmail-reply-avatar" aria-hidden="true">?</span>
						<div>
							<div class="vmail-modal-title" id="vmail-reply-title">Reply</div>
							<div class="vmail-modal-sub" id="vmail-reply-sub"></div>
						</div>
					</div>
					<button type="button" class="vmail-modal-x" id="vmail-reply-close" aria-label="Close">
						<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
					</button>
				</div>
				<div class="vmail-modal-body">
					<div class="vmail-rrow">
						<label class="vmail-rlabel">From</label>
						<select class="velox-select" id="vmail-reply-from">
							<option value="account" data-email="<?php echo esc_attr( $vx_reply_email ); ?>" data-name="<?php echo esc_attr( $vx_reply_name ); ?>">Your account — <?php echo esc_html( $vx_reply_email ); ?></option>
							<option value="custom">Custom address…</option>
						</select>
					</div>
					<div class="vmail-rrow" id="vmail-reply-customrow" hidden>
						<label class="vmail-rlabel"></label>
						<input type="email" class="velox-input" id="vmail-reply-fromcustom" placeholder="name@yourdomain.com">
						<span class="velox-hint" style="flex:none;margin:0;">shown as the sender</span>
					</div>
					<div class="vmail-rrow">
						<label class="vmail-rlabel">To</label>
						<input type="text" class="velox-input" id="vmail-reply-to" readonly>
					</div>
					<div class="vmail-rrow">
						<label class="vmail-rlabel">Subject</label>
						<input type="text" class="velox-input" id="vmail-reply-subject">
					</div>
					<div class="vmail-rrow vmail-rrow--tpl">
						<label class="vmail-rlabel">Template</label>
						<select class="velox-select" id="vmail-reply-tpl">
							<option value="">Start blank</option>
							<?php foreach ( $vx_reply_tpls as $t ) : ?>
								<option value="<?php echo esc_attr( $t['id'] ); ?>" data-subject="<?php echo esc_attr( $t['subject'] ); ?>" data-body="<?php echo esc_attr( $t['body'] ); ?>"><?php echo esc_html( $t['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="button" class="velox-btn velox-btn--ghost velox-btn--sm" id="vmail-reply-savetpl">Save as template</button>
					</div>
					<div class="vmail-editor">
						<div class="vmail-editor-tb">
							<?php
							echo $vx_tb( 'bold', 'Bold', '<path d="M6 4h8a4 4 0 0 1 0 8H6zM6 12h9a4 4 0 0 1 0 8H6z"/>' ); // phpcs:ignore
							echo $vx_tb( 'italic', 'Italic', '<line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/>' ); // phpcs:ignore
							echo $vx_tb( 'underline', 'Underline', '<path d="M6 3v7a6 6 0 0 0 12 0V3"/><line x1="4" y1="21" x2="20" y2="21"/>' ); // phpcs:ignore
							?>
							<span class="vmail-tb-sep"></span>
							<label class="vmail-tb-btn vmail-tb-color" title="Text colour" aria-label="Text colour">
								<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 20h3l1.6-4h6.8L18 20h3z"/><path d="M9.2 13h5.6"/></svg>
								<input type="color" id="vmail-reply-color" value="#1d1d1f">
							</label>
							<?php
							echo $vx_tb( 'createLink', 'Insert link', '<path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 0 0 7 7l1-1"/>' ); // phpcs:ignore
							echo $vx_tb( 'insertImage', 'Insert image', '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="M21 16l-5-5L5 20"/>' ); // phpcs:ignore
							?>
							<span class="vmail-tb-sep"></span>
							<?php
							echo $vx_tb( 'insertUnorderedList', 'Bulleted list', '<line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>' ); // phpcs:ignore
							echo $vx_tb( 'insertOrderedList', 'Numbered list', '<line x1="10" y1="6" x2="20" y2="6"/><line x1="10" y1="12" x2="20" y2="12"/><line x1="10" y1="18" x2="20" y2="18"/><path d="M4 6h1v4M4 10h2"/><path d="M4 14h2l-2 3h2"/>' ); // phpcs:ignore
							?>
						</div>
						<div class="vmail-editor-body" id="vmail-reply-body" contenteditable="true" role="textbox" aria-multiline="true"></div>
					</div>
				</div>
				<div class="vmail-modal-foot">
					<span class="velox-hint" style="margin:0;">Sends through your SMTP + sender identity.</span>
					<div class="vmail-modal-foot-btns">
						<button type="button" class="velox-btn velox-btn--ghost" id="vmail-reply-cancel">Cancel</button>
						<button type="button" class="velox-btn velox-btn--primary" id="vmail-reply-send">Send reply</button>
					</div>
				</div>
			</div>
		</div>

		<div class="velox-section-title">Forms</div>
		<div class="velox-panel velox-panel--flush">
			<?php if ( empty( $forms ) ) : ?>
				<p class="velox-hint" style="padding:26px;">No forms yet. Hit <strong>New form</strong> to create one — you'll get a <code>[velox_form]</code> shortcode to embed anywhere, including an Oxygen Shortcode element.</p>
			<?php else : ?>
				<table class="vmail-table">
					<thead><tr><th>Form</th><th>Shortcode</th><th class="vmail-th-num">Entries</th><th class="vmail-th-act"></th></tr></thead>
					<tbody>
						<?php foreach ( $forms as $f ) :
							$fc = Velox_Forms::submission_count( (int) $f['id'] );
							?>
							<tr class="vmail-trow" data-id="<?php echo (int) $f['id']; ?>">
								<td><a class="vmail-t-name" href="<?php echo esc_url( $base . '&form=' . (int) $f['id'] ); ?>"><?php echo esc_html( $f['title'] ); ?></a></td>
								<td><code class="velox-mail-shortcode">[velox_form id="<?php echo (int) $f['id']; ?>"]</code></td>
								<td class="vmail-th-num"><a class="vmail-t-count" href="<?php echo esc_url( $base . '&entries=' . (int) $f['id'] ); ?>"><?php echo (int) $fc; ?></a></td>
								<td class="vmail-t-act">
									<a class="velox-btn velox-btn--ghost velox-btn--sm" href="<?php echo esc_url( $base . '&entries=' . (int) $f['id'] ); ?>">Entries</a>
									<a class="velox-btn velox-btn--ghost velox-btn--sm" href="<?php echo esc_url( $base . '&form=' . (int) $f['id'] ); ?>">Edit</a>
									<button class="velox-btn velox-btn--ghost velox-btn--sm velox-mail-formdel">Delete</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<?php
		$vx_conns    = Velox_Mail::connections();
		$vx_routes   = Velox_Mail::routes();
		$vx_primary  = Velox_Mail::primary_id();
		$vx_fallback = Velox_Mail::fallback_id();
		?>
		<div class="velox-panel velox-tool-form">
			<h3 class="velox-panel-title" style="margin:0 0 4px;">Sender identity</h3>
			<p class="velox-hint" style="margin:0 0 16px;">By default WordPress sends mail as <code>WordPress &lt;wordpress@yourdomain&gt;</code>. Set your own name and address here and every mail Velox sends uses it instead.</p>
			<div class="velox-grid-2">
				<div class="velox-field">
					<span class="velox-field-label">From name</span>
					<input type="text" class="velox-input" data-setting="mail_from_name" value="<?php echo esc_attr( $s['mail_from_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
					<span class="velox-hint">Shown as the sender name, e.g. your company.</span>
				</div>
				<div class="velox-field">
					<span class="velox-field-label">From email</span>
					<input type="email" class="velox-input" data-setting="mail_from_email" value="<?php echo esc_attr( $s['mail_from_email'] ); ?>" placeholder="<?php echo esc_attr( 'info@' . wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>">
					<span class="velox-hint">Use an address on your own domain so it isn't marked as spam.</span>
				</div>
			</div>
		</div>
		<div class="velox-panel velox-tool-form">
			<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
				<div>
					<h3 class="velox-panel-title" style="margin:0 0 4px;">Deliverability check</h3>
					<p class="velox-hint" style="margin:0;">Mail not reaching Gmail or Microsoft? Run this to see exactly which authentication record your domain is missing.</p>
				</div>
				<button type="button" class="velox-btn velox-btn--primary" id="vmail-deliv-btn">Check deliverability</button>
			</div>
			<div class="vmail-deliv" id="vmail-deliv" hidden></div>
		</div>
		<div class="velox-panel velox-tool-form" id="vmail-smtp" data-base="<?php echo esc_attr( $base ); ?>">
			<div class="vmail-smtp-head">
				<div>
					<h3 class="velox-panel-title" style="margin:0;">SMTP connections</h3>
					<p class="velox-hint" style="margin:4px 0 0;">Add one or more sending connections, then route mail to them by the From address. If a send fails, Velox retries through your fallback.</p>
				</div>
				<label class="velox-switch velox-switch--inline" title="Send through SMTP">
					<input type="checkbox" data-setting="mail_smtp_enabled" id="vmail-smtp-enabled" <?php checked( ! empty( $s['mail_smtp_enabled'] ) ); ?>>
					<span class="velox-switch-track"></span>
				</label>
			</div>

			<div id="vmail-conn-list" class="vmail-conn-list"></div>
			<div class="vmail-conn-addrow">
				<button type="button" class="velox-btn velox-btn--ghost velox-btn--sm" id="vmail-conn-add">+ Add connection</button>
				<button type="button" class="velox-btn velox-btn--ghost velox-btn--sm" id="vmail-conn-guide">Setup guide</button>
			</div>

			<div class="vmail-routing" id="vmail-routing" hidden>
				<div class="vmail-routing-grid">
					<div class="velox-field">
						<span class="velox-field-label">Primary connection</span>
						<select class="velox-select" id="vmail-primary"></select>
						<span class="velox-hint">Used for any mail that doesn't match a route below.</span>
					</div>
					<div class="velox-field">
						<span class="velox-field-label">Fallback connection</span>
						<select class="velox-select" id="vmail-fallback"></select>
						<span class="velox-hint">Tried automatically if the primary send fails. Optional.</span>
					</div>
				</div>

				<div class="vmail-routes-head">
					<span class="velox-field-label" style="margin:0;">Routing rules</span>
					<button type="button" class="velox-btn velox-btn--ghost velox-btn--sm" id="vmail-route-add">+ Add rule</button>
				</div>
				<p class="velox-hint" style="margin:0 0 8px;">Send mail from a specific address or name through a chosen connection — e.g. route <code>billing@…</code> through a transactional provider and newsletters through another.</p>
				<div id="vmail-route-list" class="vmail-route-list"></div>
			</div>

			<div class="vmail-smtp-foot">
				<button class="velox-btn velox-btn--primary" id="vmail-smtp-save">Save connections</button>
				<span class="velox-hint vmail-smtp-foot-hint">Changes apply to new mail once saved.</span>
			</div>

			<div class="vmail-smtp-testcard">
				<div class="vmail-smtp-testcard-head">
					<span class="vmail-smtp-testcard-title">Test your setup</span>
					<span class="velox-hint" style="margin:0;">Check a connection, or send yourself a real email to confirm delivery.</span>
				</div>
				<div class="vmail-smtp-testrow">
					<select class="velox-select vmail-smtp-testctl" id="vmail-test-conn" title="Which connection to test"></select>
					<button class="velox-btn velox-btn--ghost vmail-smtp-testctl" id="vmail-conn-test" title="Check the connection without sending an email">Test connection</button>
					<input type="email" class="velox-input vmail-smtp-testctl" id="vmail-test-to" placeholder="you@example.com">
					<button class="velox-btn velox-btn--primary vmail-smtp-testctl" id="vmail-test">Send test</button>
				</div>
			</div>
		</div>

		<script type="application/json" id="vmail-smtp-data"><?php echo wp_json_encode( array(
			'connections' => $vx_conns,
			'routes'      => $vx_routes,
			'primary'     => $vx_primary,
			'fallback'    => $vx_fallback,
		) ); ?></script>

		<div class="velox-panel velox-tool-form">
			<div class="vmail-captcha-head">
				<div>
					<h3 class="velox-panel-title" style="margin:0;">CAPTCHA</h3>
					<p class="velox-hint" style="margin:4px 0 0;">When this is off, the “Require CAPTCHA” switch is locked on every form. Turn it on and add your keys to allow forms to use it.</p>
				</div>
				<label class="velox-switch velox-switch--inline" title="Allow forms to use CAPTCHA">
					<input type="checkbox" data-setting="mail_captcha_enabled" id="vmail-captcha-enabled" <?php checked( ! empty( $s['mail_captcha_enabled'] ) ); ?>>
					<span class="velox-switch-track"></span>
				</label>
			</div>
			<div class="vmail-captcha-body<?php echo empty( $s['mail_captcha_enabled'] ) ? ' is-locked' : ''; ?>" id="vmail-captcha-body">
			<div class="velox-field">
				<span class="velox-field-label">Provider</span>
				<select class="velox-select" data-setting="mail_captcha_provider">
					<option value="turnstile" <?php selected( $s['mail_captcha_provider'], 'turnstile' ); ?>>Cloudflare Turnstile (free)</option>
					<option value="recaptcha" <?php selected( $s['mail_captcha_provider'], 'recaptcha' ); ?>>Google reCAPTCHA</option>
				</select>
				<span class="velox-hint">Get keys from your provider, paste them below, then switch on “Require CAPTCHA” per form. No keys = the option stays unusable.</span>
			</div>
			<div class="velox-grid-2">
				<div class="velox-field"><span class="velox-field-label">Site key</span><input type="text" class="velox-input" data-setting="mail_captcha_site" value="<?php echo esc_attr( $s['mail_captcha_site'] ); ?>"></div>
				<div class="velox-field"><span class="velox-field-label">Secret key</span><input type="text" class="velox-input" data-setting="mail_captcha_secret" value="<?php echo esc_attr( $s['mail_captcha_secret'] ); ?>"></div>
			</div>
			</div>
			<div class="velox-tool-actions"><button class="velox-btn velox-btn--primary velox-util-save">Save settings</button></div>
		</div>

		<div class="velox-panel">
			<div style="display:flex;align-items:center;justify-content:space-between;">
				<h3 class="velox-panel-title" style="margin:0;">Send log <span class="velox-count"><?php echo count( $log ); ?></span></h3>
				<?php if ( ! empty( $log ) ) : ?><button class="velox-btn velox-btn--ghost" id="vmail-log-clear">Clear log</button><?php endif; ?>
			</div>
			<div class="velox-mail-log">
				<?php if ( empty( $log ) ) : ?>
					<p class="velox-hint" style="margin-top:10px;">Nothing sent yet.</p>
				<?php else : ?>
					<?php foreach ( $log as $l ) :
						$is_sent = ( 'sent' === $l['status'] );
						$conn    = isset( $l['connection'] ) ? $l['connection'] : '';
						?>
						<div class="velox-mail-logrow" data-id="<?php echo (int) $l['id']; ?>">
							<span class="velox-activity-dot is-<?php echo $is_sent ? 'ok' : 'bad'; ?>" title="<?php echo esc_attr( $is_sent ? 'Sent' : ( ! empty( $l['error'] ) ? $l['error'] : 'Failed' ) ); ?>"></span>
							<span class="velox-mail-log-to"><?php echo esc_html( $l['recipient'] ); ?></span>
							<span class="velox-mail-log-sub"><?php echo esc_html( $l['subject'] ); ?></span>
							<?php if ( '' !== $conn ) : ?><span class="velox-mail-log-conn"><?php echo esc_html( $conn ); ?></span><?php endif; ?>
							<span class="velox-mail-log-when"><?php echo esc_html( $l['created'] ); ?></span>
							<button class="velox-btn velox-btn--ghost velox-btn--sm velox-mail-resend" data-id="<?php echo (int) $l['id']; ?>" title="Send this message again">Resend</button>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>

		<div class="velox-panel velox-mail-disable">
			<label class="velox-inline-toggle">
				<span><strong>Mail &amp; forms is on</strong> <span class="velox-hint" style="display:inline;">— switch off to disable forms and SMTP.</span></span>
				<span class="velox-switch"><input type="checkbox" data-setting="util_mail" id="velox-mail-toggle" checked><span class="velox-switch-track"></span></span>
			</label>
		</div>
	<?php endif; ?>
<?php endif; ?>
