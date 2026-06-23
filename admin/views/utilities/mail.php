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
	?>
	<a class="velox-back" href="<?php echo esc_url( $base ); ?>">&larr; All forms</a>
	<div class="velox-mail-build">
		<div class="velox-mail-build-main">
			<div class="velox-panel">
				<div class="velox-field">
					<span class="velox-field-label">Form name</span>
					<input type="text" class="velox-input" id="vmail-title" value="<?php echo esc_attr( $form['title'] ); ?>">
				</div>
				<h3 class="velox-panel-title" style="margin-top:14px;">Fields</h3>
				<div id="vmail-fields" class="vmail-fields"></div>
				<div class="vmail-addfield">
					<select class="velox-select" id="vmail-newtype">
						<option value="text">Text</option>
						<option value="email">Email</option>
						<option value="tel">Phone</option>
						<option value="textarea">Text area</option>
						<option value="select">Dropdown</option>
						<option value="checkbox">Checkbox</option>
						<option value="consent">Consent (Datenschutz)</option>
					</select>
					<button class="velox-btn velox-btn--ghost" id="vmail-addfield">+ Add field</button>
				</div>
			</div>

			<div class="velox-panel">
				<h3 class="velox-panel-title">Notification emails</h3>
				<p class="velox-hint">Use <code>{field_key}</code> for one answer, <code>{all_fields}</code> for everything, plus <code>{site_name}</code> and <code>{date}</code>.</p>
				<div id="vmail-emails"></div>
			</div>

			<div class="velox-panel">
				<h3 class="velox-panel-title">Form settings</h3>
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
						<span class="velox-toggle-desc"><?php echo Velox_Forms::captcha_ready() ? 'Keys are set — the widget will appear on the form.' : 'No keys yet — add them under Mail settings first.'; ?></span>
					</div>
					<span class="velox-switch"><input type="checkbox" id="vmail-captcha" <?php checked( ! empty( $form['captcha'] ) ); ?>><span class="velox-switch-track"></span></span>
				</label>
			</div>

			<div class="velox-tool-actions" style="display:flex;gap:10px;">
				<button class="velox-btn velox-btn--primary" id="vmail-save">Save form</button>
				<?php if ( 'new' !== $edit ) : ?>
					<span class="velox-hint" style="align-self:center;">Shortcode: <code>[velox_form id="<?php echo (int) $form['id']; ?>"]</code></span>
				<?php endif; ?>
			</div>
		</div>

		<div class="velox-mail-build-side">
			<div class="velox-panel velox-sticky">
				<h3 class="velox-panel-title">Live preview</h3>
				<div id="vmail-preview" class="vmail-preview"></div>
			</div>
		</div>
	</div>
	<script type="application/json" id="vmail-data"><?php echo wp_json_encode( $form ); ?></script>

<?php else :
	$forms       = Velox_Forms::forms();
	$submissions = Velox_Forms::submissions();
	$log         = Velox_Mail::log( 50 );
	?>
	<div class="velox-panel">
		<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
			<label class="velox-inline-toggle">
				<span><strong>Mail &amp; forms</strong></span>
				<span class="velox-switch"><input type="checkbox" data-setting="util_mail" id="velox-mail-toggle" checked><span class="velox-switch-track"></span></span>
			</label>
			<a class="velox-btn velox-btn--primary" href="<?php echo esc_url( $base . '&form=new' ); ?>">+ New form</a>
		</div>
		<div class="velox-mail-formlist">
			<?php if ( empty( $forms ) ) : ?>
				<p class="velox-hint" style="margin-top:14px;">No forms yet. Create one to get a <code>[velox_form]</code> shortcode.</p>
			<?php else : ?>
				<?php foreach ( $forms as $f ) : ?>
					<div class="velox-mail-formrow" data-id="<?php echo (int) $f['id']; ?>">
						<div class="velox-mail-formmeta">
							<span class="velox-mail-formname"><?php echo esc_html( $f['title'] ); ?></span>
							<code class="velox-mail-shortcode">[velox_form id="<?php echo (int) $f['id']; ?>"]</code>
						</div>
						<div style="display:flex;gap:8px;">
							<a class="velox-btn velox-btn--ghost" href="<?php echo esc_url( $base . '&form=' . (int) $f['id'] ); ?>">Edit</a>
							<button class="velox-btn velox-btn--ghost velox-mail-formdel">Delete</button>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
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
		<h3 class="velox-panel-title">Submissions <span class="velox-count"><?php echo count( $submissions ); ?></span></h3>
		<div class="velox-mail-subs">
			<?php if ( empty( $submissions ) ) : ?>
				<p class="velox-hint" style="margin-top:10px;">No submissions yet.</p>
			<?php else : ?>
				<?php foreach ( $submissions as $sub ) :
					$d = json_decode( $sub['data'], true );
					$d = is_array( $d ) ? $d : array();
					?>
					<div class="velox-mail-sub" data-id="<?php echo (int) $sub['id']; ?>">
						<div class="velox-mail-sub-head">
							<span class="velox-mail-sub-when"><?php echo esc_html( $sub['created'] ); ?></span>
							<button class="velox-btn velox-btn--ghost velox-mail-sub-del">Delete</button>
						</div>
						<div class="velox-mail-sub-body">
							<?php foreach ( $d as $k => $v ) : ?>
								<div><strong><?php echo esc_html( $k ); ?>:</strong> <?php echo esc_html( $v ); ?></div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
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
<?php endif; ?>
