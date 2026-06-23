<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Forms engine.
 *
 * Forms are stored as structured config in an option (a handful per site).
 * Submissions go to their own table. Each form carries its own notification
 * emails (admin = to you with every field; customer = auto-reply to the sender),
 * an optional consent checkbox, an optional CAPTCHA and a honeypot for spam.
 *
 * Embed anywhere with [velox_form id="1"] — including Oxygen's shortcode element.
 */
class Velox_Forms {

	const DB_VERSION  = '1';
	const VER_OPTION  = 'velox_forms_db';
	const FORMS_OPTION = 'velox_forms';

	private static $rendered = false;

	/* ----------------------------------------------------------------- setup */

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'velox_submissions';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$t = self::table();
		dbDelta( "CREATE TABLE {$t} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL,
			created DATETIME NULL,
			data LONGTEXT NULL,
			ip VARCHAR(45) NULL,
			PRIMARY KEY  (id),
			KEY form_id (form_id)
		) {$charset};" );
		update_option( self::VER_OPTION, self::DB_VERSION );
	}

	public static function maybe_install() {
		if ( get_option( self::VER_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function init() {
		add_shortcode( 'velox_form', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_ajax_velox_form', array( __CLASS__, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_velox_form', array( __CLASS__, 'handle_submit' ) );
		add_action( 'wp_footer', array( __CLASS__, 'print_assets' ), 5 );
	}

	/* ------------------------------------------------------------------- CRUD */

	public static function forms() {
		$f = get_option( self::FORMS_OPTION, array() );
		return is_array( $f ) ? $f : array();
	}

	public static function get_form( $id ) {
		$forms = self::forms();
		return isset( $forms[ $id ] ) ? $forms[ $id ] : null;
	}

	public static function blank_form() {
		return array(
			'id'           => 0,
			'title'        => 'New form',
			'fields'       => array(
				array( 'key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true, 'placeholder' => '', 'options' => '' ),
				array( 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'placeholder' => '', 'options' => '' ),
				array( 'key' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => true, 'placeholder' => '', 'options' => '' ),
				array( 'key' => 'consent', 'type' => 'consent', 'label' => 'I accept the privacy policy.', 'required' => true, 'placeholder' => '', 'options' => '' ),
			),
			'submit_label' => 'Send',
			'success'      => 'Thanks — we\'ll be in touch soon.',
			'captcha'      => false,
			'accent'       => '#2ab7f1',
			'emails'       => array(
				array( 'type' => 'admin', 'enabled' => true, 'to' => get_option( 'admin_email' ), 'cc' => '', 'subject' => 'New form submission', 'body' => "You received a new submission:\n\n{all_fields}" ),
				array( 'type' => 'customer', 'enabled' => false, 'to_field' => 'email', 'cc' => '', 'subject' => 'We received your message', 'body' => "Hi {name},\n\nThanks for getting in touch — we'll reply shortly.\n\n{site_name}" ),
			),
		);
	}

	public static function save_form( $form ) {
		$forms = self::forms();
		$id    = (int) ( $form['id'] ?? 0 );
		if ( ! $id ) {
			$id = 1;
			foreach ( array_keys( $forms ) as $k ) {
				$id = max( $id, (int) $k + 1 );
			}
			$form['id'] = $id;
		}
		$form          = self::sanitize_form( $form );
		$forms[ $id ]  = $form;
		update_option( self::FORMS_OPTION, $forms, false );
		return array( 'ok' => true, 'id' => $id );
	}

	public static function delete_form( $id ) {
		$forms = self::forms();
		unset( $forms[ (int) $id ] );
		update_option( self::FORMS_OPTION, $forms, false );
		return array( 'ok' => true );
	}

	private static function sanitize_form( $form ) {
		$out = array(
			'id'           => (int) $form['id'],
			'title'        => sanitize_text_field( $form['title'] ?? 'Form' ),
			'submit_label' => sanitize_text_field( $form['submit_label'] ?? 'Send' ),
			'success'      => sanitize_text_field( $form['success'] ?? 'Thanks.' ),
			'captcha'      => ! empty( $form['captcha'] ),
			'accent'       => sanitize_hex_color( $form['accent'] ?? '#2ab7f1' ) ? sanitize_hex_color( $form['accent'] ) : '#2ab7f1',
			'fields'       => array(),
			'emails'       => array(),
		);
		$types = array( 'text', 'email', 'tel', 'number', 'textarea', 'select', 'radio', 'checkbox', 'consent' );
		foreach ( (array) ( $form['fields'] ?? array() ) as $f ) {
			$label = sanitize_text_field( $f['label'] ?? '' );
			$key   = sanitize_key( $f['key'] ?? '' );
			if ( '' === $key ) {
				$slug = sanitize_key( str_replace( array( ' ', '-' ), '_', $label ) );
				$key  = $slug ? $slug : 'field_' . wp_rand( 100, 999 );
			}
			$w     = $f['width'] ?? 'full';
			$width = in_array( $w, array( 'full', 'half' ), true ) ? $w : 'full';
			$out['fields'][] = array(
				'key'         => $key,
				'type'        => in_array( $f['type'] ?? 'text', $types, true ) ? $f['type'] : 'text',
				'label'       => $label,
				'required'    => ! empty( $f['required'] ),
				'placeholder' => sanitize_text_field( $f['placeholder'] ?? '' ),
				'options'     => sanitize_textarea_field( $f['options'] ?? '' ),
				'default'     => sanitize_text_field( $f['default'] ?? '' ),
				'help'        => sanitize_text_field( $f['help'] ?? '' ),
				'width'       => $width,
				'css'         => sanitize_html_class( $f['css'] ?? '' ),
			);
		}
		foreach ( (array) ( $form['emails'] ?? array() ) as $e ) {
			$out['emails'][] = array(
				'type'       => ( 'customer' === ( $e['type'] ?? 'admin' ) ) ? 'customer' : 'admin',
				'enabled'    => ! empty( $e['enabled'] ),
				'to'         => sanitize_text_field( $e['to'] ?? '' ),
				'to_field'   => sanitize_key( $e['to_field'] ?? 'email' ),
				'cc'         => sanitize_text_field( $e['cc'] ?? '' ),
				'bcc'        => sanitize_text_field( $e['bcc'] ?? '' ),
				'from_name'  => sanitize_text_field( $e['from_name'] ?? '' ),
				'from_email' => sanitize_text_field( $e['from_email'] ?? '' ),
				'reply_to'   => sanitize_text_field( $e['reply_to'] ?? '' ),
				'subject'    => sanitize_text_field( $e['subject'] ?? '' ),
				'body'       => sanitize_textarea_field( $e['body'] ?? '' ),
			);
		}
		return $out;
	}

	/* --------------------------------------------------------------- rendering */

	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'velox_form' );
		return self::render( (int) $atts['id'] );
	}

	public static function render( $id ) {
		$form = self::get_form( $id );
		if ( ! $form ) {
			return '<!-- velox: form ' . (int) $id . ' not found -->';
		}
		self::$rendered = true;
		$accent = esc_attr( $form['accent'] );
		$nonce  = wp_create_nonce( 'velox_form_' . $id );

		ob_start();
		?>
		<form class="velox-form" data-form="<?php echo (int) $id; ?>" style="--vf-accent:<?php echo $accent; ?>">
			<div class="velox-form-msg" hidden></div>
			<div class="velox-form-grid">
				<?php foreach ( $form['fields'] as $f ) : ?>
					<?php echo self::field_html( $f ); // phpcs:ignore ?>
				<?php endforeach; ?>
			</div>
			<?php if ( ! empty( $form['captcha'] ) ) : echo self::captcha_widget(); endif; // phpcs:ignore ?>
			<input type="text" name="velox_hp" class="velox-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
			<input type="hidden" name="velox_nonce" value="<?php echo esc_attr( $nonce ); ?>">
			<button type="submit" class="velox-form-submit"><?php echo esc_html( $form['submit_label'] ); ?></button>
		</form>
		<?php
		return ob_get_clean();
	}

	private static function field_html( $f ) {
		$key   = esc_attr( $f['key'] );
		$label = esc_html( $f['label'] );
		$req   = ! empty( $f['required'] );
		$star  = $req ? ' <span class="velox-req">*</span>' : '';
		$ph    = esc_attr( $f['placeholder'] );
		$def   = isset( $f['default'] ) ? $f['default'] : '';
		$help  = ! empty( $f['help'] ) ? '<span class="velox-form-help">' . esc_html( $f['help'] ) . '</span>' : '';
		$rq    = $req ? ' required' : '';
		$name  = 'vf[' . $key . ']';
		$width = ( isset( $f['width'] ) && 'half' === $f['width'] ) ? ' velox-form-field--half' : '';
		$css   = ! empty( $f['css'] ) ? ' ' . esc_attr( $f['css'] ) : '';
		$opts  = array_filter( array_map( 'trim', explode( "\n", (string) ( $f['options'] ?? '' ) ) ) );

		ob_start();
		if ( 'consent' === $f['type'] || 'checkbox' === $f['type'] ) {
			?>
			<div class="velox-form-row velox-form-row--check<?php echo esc_attr( $width . $css ); ?>">
				<label class="velox-form-consent">
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1"<?php echo $rq; ?>>
					<span><?php echo $label . $star; ?></span>
				</label>
				<?php echo $help; // phpcs:ignore ?>
			</div>
			<?php
		} elseif ( 'radio' === $f['type'] ) {
			?>
			<div class="velox-form-row<?php echo esc_attr( $width . $css ); ?>">
				<span class="velox-form-label"><?php echo $label . $star; ?></span>
				<div class="velox-form-radios">
					<?php foreach ( $opts as $opt ) : $checked = ( $def === $opt ) ? ' checked' : ''; ?>
						<label class="velox-form-radio"><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $opt ); ?>"<?php echo $checked . $rq; ?>> <span><?php echo esc_html( $opt ); ?></span></label>
					<?php endforeach; ?>
				</div>
				<?php echo $help; // phpcs:ignore ?>
			</div>
			<?php
		} else {
			?>
			<label class="velox-form-field<?php echo esc_attr( $width . $css ); ?>">
				<?php if ( '' !== $label ) : ?><span class="velox-form-label"><?php echo $label . $star; ?></span><?php endif; ?>
				<?php if ( 'textarea' === $f['type'] ) : ?>
					<textarea name="<?php echo esc_attr( $name ); ?>" rows="5" placeholder="<?php echo $ph; ?>"<?php echo $rq; ?>><?php echo esc_textarea( $def ); ?></textarea>
				<?php elseif ( 'select' === $f['type'] ) : ?>
					<select name="<?php echo esc_attr( $name ); ?>"<?php echo $rq; ?>>
						<option value="">—</option>
						<?php foreach ( $opts as $opt ) : ?>
							<option value="<?php echo esc_attr( $opt ); ?>"<?php selected( $def, $opt ); ?>><?php echo esc_html( $opt ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input type="<?php echo esc_attr( $f['type'] ); ?>" name="<?php echo esc_attr( $name ); ?>" placeholder="<?php echo $ph; ?>" value="<?php echo esc_attr( $def ); ?>"<?php echo $rq; ?>>
				<?php endif; ?>
				<?php echo $help; // phpcs:ignore ?>
			</label>
			<?php
		}
		return ob_get_clean();
	}

	/* ----------------------------------------------------------------- CAPTCHA */

	public static function captcha_provider() {
		$p = Velox_Settings::get( 'mail_captcha_provider', 'turnstile' );
		return in_array( $p, array( 'recaptcha', 'turnstile' ), true ) ? $p : 'turnstile';
	}

	public static function captcha_ready() {
		return Velox_Settings::get( 'mail_captcha_site', '' ) && Velox_Settings::get( 'mail_captcha_secret', '' );
	}

	public static function captcha_widget() {
		if ( ! self::captcha_ready() ) {
			return '<p class="velox-form-note">CAPTCHA is enabled but keys are missing — add them in Mail settings.</p>';
		}
		$site = esc_attr( Velox_Settings::get( 'mail_captcha_site', '' ) );
		if ( 'recaptcha' === self::captcha_provider() ) {
			return '<div class="g-recaptcha" data-sitekey="' . $site . '"></div>';
		}
		return '<div class="cf-turnstile" data-sitekey="' . $site . '"></div>';
	}

	private static function verify_captcha() {
		if ( ! self::captcha_ready() ) {
			return true; // nothing to verify against
		}
		$provider = self::captcha_provider();
		$field    = 'recaptcha' === $provider ? 'g-recaptcha-response' : 'cf-turnstile-response';
		$token    = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		if ( '' === $token ) {
			return false;
		}
		$url = 'recaptcha' === $provider
			? 'https://www.google.com/recaptcha/api/siteverify'
			: 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
		$res = wp_remote_post( $url, array(
			'timeout' => 8,
			'body'    => array(
				'secret'   => Velox_Settings::get( 'mail_captcha_secret', '' ),
				'response' => $token,
			),
		) );
		if ( is_wp_error( $res ) ) {
			return false;
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		return ! empty( $data['success'] );
	}

	/* -------------------------------------------------------------- submission */

	public static function handle_submit() {
		$id   = isset( $_POST['form_id'] ) ? (int) $_POST['form_id'] : 0;
		$form = self::get_form( $id );
		if ( ! $form ) {
			wp_send_json_error( array( 'message' => 'Form not found.' ) );
		}
		// Honeypot — real users never fill this.
		if ( ! empty( $_POST['velox_hp'] ) ) {
			wp_send_json_success( array( 'message' => $form['success'] ) ); // pretend success, drop silently
		}
		$nonce = isset( $_POST['velox_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['velox_nonce'] ) ) : '';
		// Nonce is best-effort (public, cacheable form); the honeypot + CAPTCHA carry spam defence.
		$raw   = isset( $_POST['vf'] ) ? (array) wp_unslash( $_POST['vf'] ) : array();

		$data   = array();
		$errors = array();
		foreach ( $form['fields'] as $f ) {
			$key = $f['key'];
			$val = isset( $raw[ $key ] ) ? $raw[ $key ] : '';
			if ( in_array( $f['type'], array( 'consent', 'checkbox' ), true ) ) {
				$checked = ! empty( $val );
				if ( $f['required'] && ! $checked ) {
					$errors[ $key ] = 'required';
				}
				$data[ $key ] = $checked ? 'yes' : 'no';
				continue;
			}
			$val = is_array( $val ) ? '' : trim( wp_strip_all_tags( (string) $val ) );
			if ( $f['required'] && '' === $val ) {
				$errors[ $key ] = 'required';
			}
			if ( 'email' === $f['type'] && '' !== $val && ! is_email( $val ) ) {
				$errors[ $key ] = 'invalid';
			}
			$data[ $key ] = $val;
		}

		if ( $errors ) {
			wp_send_json_error( array( 'message' => 'Please check the highlighted fields.', 'fields' => array_keys( $errors ) ) );
		}
		if ( ! empty( $form['captcha'] ) && ! self::verify_captcha() ) {
			wp_send_json_error( array( 'message' => 'CAPTCHA check failed — please try again.' ) );
		}

		// Store the submission.
		global $wpdb;
		$wpdb->insert( self::table(), array(
			'form_id' => $id,
			'created' => current_time( 'mysql' ),
			'data'    => wp_json_encode( $data ),
			'ip'      => isset( $_SERVER['REMOTE_ADDR'] ) ? substr( preg_replace( '/[^0-9a-f:.]/i', '', $_SERVER['REMOTE_ADDR'] ), 0, 45 ) : '',
		), array( '%d', '%s', '%s', '%s' ) );

		self::send_emails( $form, $data );

		wp_send_json_success( array( 'message' => $form['success'] ) );
	}

	private static function send_emails( $form, $data ) {
		foreach ( $form['emails'] as $e ) {
			if ( empty( $e['enabled'] ) ) {
				continue;
			}
			if ( 'customer' === $e['type'] ) {
				$field = $e['to_field'] ? $e['to_field'] : 'email';
				$to    = isset( $data[ $field ] ) ? $data[ $field ] : '';
				if ( ! $to || ! is_email( $to ) ) {
					continue;
				}
			} else {
				$to = $e['to'] ? $e['to'] : get_option( 'admin_email' );
			}
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			if ( ! empty( $e['from_email'] ) && is_email( $e['from_email'] ) ) {
				$headers[] = ! empty( $e['from_name'] )
					? 'From: ' . $e['from_name'] . ' <' . $e['from_email'] . '>'
					: 'From: ' . $e['from_email'];
			}
			if ( ! empty( $e['reply_to'] ) ) {
				$reply = self::replace( $e['reply_to'], $data, $form );
				if ( is_email( $reply ) ) {
					$headers[] = 'Reply-To: ' . $reply;
				}
			}
			if ( ! empty( $e['cc'] ) ) {
				foreach ( array_filter( array_map( 'trim', explode( ',', $e['cc'] ) ) ) as $cc ) {
					$headers[] = 'Cc: ' . $cc;
				}
			}
			if ( ! empty( $e['bcc'] ) ) {
				foreach ( array_filter( array_map( 'trim', explode( ',', $e['bcc'] ) ) ) as $bcc ) {
					$headers[] = 'Bcc: ' . $bcc;
				}
			}
			$subject = self::replace( $e['subject'], $data, $form );
			$body    = wpautop( self::replace( $e['body'], $data, $form ) );
			Velox_Mail::send( $to, $subject, $body, $headers );
		}
	}

	private static function replace( $text, $data, $form ) {
		$text = (string) $text;
		// {all_fields} → a labelled list of everything submitted.
		if ( false !== strpos( $text, '{all_fields}' ) ) {
			$lines = array();
			foreach ( $form['fields'] as $f ) {
				if ( 'consent' === $f['type'] ) {
					continue;
				}
				$val     = isset( $data[ $f['key'] ] ) ? $data[ $f['key'] ] : '';
				$lines[] = $f['label'] . ': ' . $val;
			}
			$text = str_replace( '{all_fields}', implode( "\n", $lines ), $text );
		}
		$text = str_replace( array( '{site_name}', '{date}' ), array( get_bloginfo( 'name' ), date_i18n( get_option( 'date_format' ) ) ), $text );
		foreach ( $data as $k => $v ) {
			// Support both the short {key} and Fluent-style {inputs.key} tags.
			$text = str_replace( array( '{' . $k . '}', '{inputs.' . $k . '}' ), $v, $text );
		}
		return $text;
	}

	/* ------------------------------------------------------------ submissions */

	public static function submissions( $form_id = 0, $limit = 100 ) {
		global $wpdb;
		$t = self::table();
		if ( $form_id ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE form_id = %d ORDER BY id DESC LIMIT %d", $form_id, $limit ), ARRAY_A ) ?: array();
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ) ?: array();
	}

	public static function delete_submission( $sid ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $sid ), array( '%d' ) );
		return array( 'ok' => true );
	}

	/* --------------------------------------------------------------- front assets */

	public static function print_assets() {
		if ( ! self::$rendered ) {
			return;
		}
		// CAPTCHA provider script, only if a rendered form uses it.
		if ( self::captcha_ready() ) {
			if ( 'recaptcha' === self::captcha_provider() ) {
				echo '<script src="https://www.google.com/recaptcha/api.js" async defer></script>' . "\n";
			} else {
				echo '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>' . "\n";
			}
		}
		?>
<style>
.velox-form{max-width:560px;display:flex;flex-direction:column;gap:16px;font-family:inherit}
.velox-form-grid{display:flex;flex-wrap:wrap;gap:16px}
.velox-form-field,.velox-form-row{display:flex;flex-direction:column;gap:6px;width:100%}
.velox-form-field--half{width:calc(50% - 8px)}
.velox-form-radios{display:flex;flex-wrap:wrap;gap:14px}
.velox-form-radio{display:inline-flex;align-items:center;gap:7px;font-size:14px;cursor:pointer}
.velox-form-radio input{width:auto;margin:0}
.velox-form-help{font-size:13px;color:#6b7280}
.velox-form-label{font-size:14px;font-weight:600}
.velox-form .velox-req{color:#e11d48}
.velox-form input,.velox-form textarea,.velox-form select{width:100%;padding:11px 13px;border:1px solid #d4d8e0;border-radius:10px;font-size:15px;font-family:inherit;background:#fff;color:#1d1d1f}
.velox-form input:focus,.velox-form textarea:focus,.velox-form select:focus{outline:none;border-color:var(--vf-accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--vf-accent) 22%,transparent)}
.velox-form-consent{display:flex;gap:9px;align-items:flex-start;font-size:14px;line-height:1.45;cursor:pointer}
.velox-form-consent input{width:auto;margin-top:3px}
.velox-form-submit{align-self:flex-start;background:var(--vf-accent);color:#fff;border:0;border-radius:10px;padding:12px 26px;font-size:15px;font-weight:600;cursor:pointer;transition:filter .15s}
.velox-form-submit:hover{filter:brightness(.93)}
.velox-form-submit:disabled{opacity:.6;cursor:default}
.velox-hp{position:absolute!important;left:-9999px!important;width:1px!important;height:1px!important;overflow:hidden}
.velox-form-msg{padding:13px 15px;border-radius:10px;font-size:14px}
.velox-form-msg.is-ok{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.velox-form-msg.is-err{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
.velox-form .has-error input,.velox-form .has-error textarea,.velox-form .has-error select{border-color:#e11d48}
.velox-form-note{font-size:13px;color:#b45309}
</style>
<script>
(function(){
  document.querySelectorAll('.velox-form').forEach(function(form){
    form.addEventListener('submit',function(e){
      e.preventDefault();
      var msg=form.querySelector('.velox-form-msg');
      var btn=form.querySelector('.velox-form-submit');
      form.querySelectorAll('.has-error').forEach(function(el){el.classList.remove('has-error');});
      var fd=new FormData(form);
      fd.append('action','velox_form');
      fd.append('form_id',form.getAttribute('data-form'));
      btn.disabled=true;
      fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',{method:'POST',credentials:'same-origin',body:fd})
        .then(function(r){return r.json();})
        .then(function(j){
          if(j&&j.success){
            form.reset();
            msg.className='velox-form-msg is-ok';msg.textContent=j.data.message;msg.hidden=false;
          }else{
            var d=(j&&j.data)||{};
            msg.className='velox-form-msg is-err';msg.textContent=d.message||'Something went wrong.';msg.hidden=false;
            (d.fields||[]).forEach(function(k){
              var inp=form.querySelector('[name="vf['+k+']"]');
              if(inp){var wrap=inp.closest('.velox-form-field')||inp.closest('.velox-form-row')||inp.closest('.velox-form-consent');if(wrap)wrap.classList.add('has-error');}
            });
          }
        })
        .catch(function(){msg.className='velox-form-msg is-err';msg.textContent='Network error — please try again.';msg.hidden=false;})
        .then(function(){btn.disabled=false;});
    });
  });
})();
</script>
		<?php
	}
}
