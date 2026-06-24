<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$on   = Velox_Settings::get( 'util_mail', false );
$s    = Velox_Settings::all();
$edit = isset( $_GET['form'] ) ? sanitize_text_field( wp_unslash( $_GET['form'] ) ) : '';
$base = admin_url( 'admin.php?page=velox-utilities&tool=mail' );
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Mail &amp; forms</h1>
	<p class="velox-sub">Build forms, route submissions to styled notification emails (to you and to the customer), and send everything reliably over SMTP.</p>
</div>

<?php if ( ! $on ) : ?>
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
	?>
	<div class="vmail-builder" id="vmail-builder">
		<div class="vmail-bar">
			<a class="vmail-back" href="<?php echo esc_url( $base ); ?>" title="All forms">&larr;</a>
			<input type="text" class="vmail-title-input" id="vmail-title" value="<?php echo esc_attr( $form['title'] ); ?>" placeholder="Form name">
			<div class="vmail-tabs">
				<button type="button" class="vmail-tab is-active" data-tab="build">Build</button>
				<button type="button" class="vmail-tab" data-tab="notify">Notifications</button>
				<button type="button" class="vmail-tab" data-tab="settings">Settings</button>
			</div>
			<button class="velox-btn velox-btn--primary" id="vmail-save">Save form</button>
		</div>

		<div class="vmail-panel" data-panel="build">
			<div class="vmail-build-grid">
				<aside class="vmail-palette">
					<span class="vmail-palette-title">Fields</span>
					<input type="text" id="vmail-palette-search" class="velox-input vmail-palette-search" placeholder="Search fields…">
					<div id="vmail-palette" class="vmail-palette-list"></div>
					<p class="velox-hint" style="margin-top:14px;">Click to add, or drag a field on the canvas to reorder.</p>
				</aside>

				<div class="vmail-canvas-wrap">
					<div class="vmail-canvas" id="vmail-canvas"></div>
				</div>

				<aside class="vmail-inspector" id="vmail-inspector"></aside>
			</div>
		</div>

		<div class="vmail-panel" data-panel="notify" hidden>
			<p class="velox-hint" style="margin-bottom:14px;">Notifications are sent when the form is submitted. Use the <strong>Insert field</strong> menu to drop in merge tags like <code>{inputs.email}</code>, or <code>{all_fields}</code> for the whole submission.</p>
			<div id="vmail-emails"></div>
		</div>

		<div class="vmail-panel" data-panel="settings" hidden>
			<div class="velox-panel">
				<div class="velox-field">
					<span class="velox-field-label">Submit button label</span>
					<input type="text" class="velox-input" id="vmail-submit" value="<?php echo esc_attr( $form['submit_label'] ); ?>">
				</div>
				<div class="velox-field">
					<span class="velox-field-label">Success message</span>
					<input type="text" class="velox-input" id="vmail-success" value="<?php echo esc_attr( $form['success'] ); ?>">
				</div>
				<div class="velox-field">
					<span class="velox-field-label">Accent colour</span>
					<input type="color" id="vmail-accent" value="<?php echo esc_attr( $form['accent'] ); ?>" style="width:54px;height:36px;padding:2px;">
				</div>
				<label class="velox-toggle-row" style="cursor:pointer;">
					<div class="velox-toggle-meta">
						<span class="velox-toggle-label">Require CAPTCHA</span>
						<span class="velox-toggle-desc"><?php echo $captcha_ready ? 'Keys are set — the widget will appear on the form.' : 'No keys yet — add them under Mail settings first.'; ?></span>
					</div>
					<span class="velox-switch"><input type="checkbox" id="vmail-captcha" <?php checked( ! empty( $form['captcha'] ) ); ?>><span class="velox-switch-track"></span></span>
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
	<script type="application/json" id="vmail-data"><?php echo wp_json_encode( $form ); ?></script>
	<script type="application/json" id="vmail-meta"><?php echo wp_json_encode( array( 'captcha_ready' => $captcha_ready, 'admin_email' => get_option( 'admin_email' ), 'site_name' => get_bloginfo( 'name' ), 'base' => $base ) ); ?></script>

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
			<a class="velox-btn velox-btn--ghost" href="<?php echo esc_url( $base . '&form=' . $entries_for ); ?>">Edit form</a>
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

		<div class="velox-panel velox-tool-form">
			<h3 class="velox-panel-title">SMTP</h3>
			<label class="velox-toggle-row">
				<div class="velox-toggle-meta">
					<span class="velox-toggle-label">Send through SMTP</span>
					<span class="velox-toggle-desc">Far more reliable than PHP mail. Fill in your provider's details below.</span>
				</div>
				<span class="velox-switch"><input type="checkbox" data-setting="mail_smtp_enabled" <?php checked( ! empty( $s['mail_smtp_enabled'] ) ); ?>><span class="velox-switch-track"></span></span>
			</label>
			<div class="velox-grid-2">
				<div class="velox-field"><span class="velox-field-label">Host</span><input type="text" class="velox-input" data-setting="mail_smtp_host" value="<?php echo esc_attr( $s['mail_smtp_host'] ); ?>" placeholder="smtp.example.com"></div>
				<div class="velox-field"><span class="velox-field-label">Port</span><input type="number" class="velox-input velox-input--sm" data-setting="mail_smtp_port" value="<?php echo esc_attr( (int) $s['mail_smtp_port'] ); ?>"></div>
				<div class="velox-field"><span class="velox-field-label">Encryption</span>
					<select class="velox-select" data-setting="mail_smtp_secure">
						<?php foreach ( array( 'tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None' ) as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $s['mail_smtp_secure'], $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="velox-field"><span class="velox-field-label">Username</span><input type="text" class="velox-input" data-setting="mail_smtp_user" value="<?php echo esc_attr( $s['mail_smtp_user'] ); ?>"></div>
				<div class="velox-field"><span class="velox-field-label">Password</span><input type="password" class="velox-input" data-setting="mail_smtp_pass" value="<?php echo esc_attr( $s['mail_smtp_pass'] ); ?>"></div>
				<div class="velox-field"><span class="velox-field-label">From address</span><input type="email" class="velox-input" data-setting="mail_smtp_from" value="<?php echo esc_attr( $s['mail_smtp_from'] ); ?>"></div>
				<div class="velox-field"><span class="velox-field-label">From name</span><input type="text" class="velox-input" data-setting="mail_smtp_from_name" value="<?php echo esc_attr( $s['mail_smtp_from_name'] ); ?>"></div>
			</div>
			<div class="velox-tool-actions" style="display:flex;gap:8px;align-items:center;">
				<button class="velox-btn velox-btn--primary velox-util-save">Save settings</button>
				<input type="email" class="velox-input" id="vmail-test-to" placeholder="you@example.com" style="max-width:220px;">
				<button class="velox-btn velox-btn--ghost" id="vmail-test">Send test</button>
			</div>
		</div>

		<div class="velox-panel velox-tool-form">
			<h3 class="velox-panel-title">CAPTCHA</h3>
			<div class="velox-field">
				<span class="velox-field-label">Provider</span>
				<select class="velox-select" data-setting="mail_captcha_provider">
					<option value="turnstile" <?php selected( $s['mail_captcha_provider'], 'turnstile' ); ?>>Cloudflare Turnstile (free)</option>
					<option value="recaptcha" <?php selected( $s['mail_captcha_provider'], 'recaptcha' ); ?>>Google reCAPTCHA</option>
				</select>
				<span class="velox-hint">Get keys from your provider, paste them below, then switch on "Require CAPTCHA" per form. No keys = the option stays unusable.</span>
			</div>
			<div class="velox-grid-2">
				<div class="velox-field"><span class="velox-field-label">Site key</span><input type="text" class="velox-input" data-setting="mail_captcha_site" value="<?php echo esc_attr( $s['mail_captcha_site'] ); ?>"></div>
				<div class="velox-field"><span class="velox-field-label">Secret key</span><input type="text" class="velox-input" data-setting="mail_captcha_secret" value="<?php echo esc_attr( $s['mail_captcha_secret'] ); ?>"></div>
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
					<?php foreach ( $log as $l ) : ?>
						<div class="velox-mail-logrow">
							<span class="velox-activity-dot is-<?php echo 'sent' === $l['status'] ? 'ok' : 'bad'; ?>"></span>
							<span class="velox-mail-log-to"><?php echo esc_html( $l['recipient'] ); ?></span>
							<span class="velox-mail-log-sub"><?php echo esc_html( $l['subject'] ); ?></span>
							<span class="velox-mail-log-when"><?php echo esc_html( $l['created'] ); ?></span>
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
