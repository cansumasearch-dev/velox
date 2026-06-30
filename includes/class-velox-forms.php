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
		add_action( 'admin_post_velox_form_export', array( __CLASS__, 'handle_export' ) );
	}

	/** Verify nonce + capability, then stream the CSV. */
	public static function handle_export() {
		$id = isset( $_GET['form'] ) ? (int) $_GET['form'] : 0;
		check_admin_referer( 'velox_form_export_' . $id );
		self::export_csv( $id );
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
			'fields'       => array(), // start empty — the user builds it from the palette
			'submit_label' => 'Send',
			'success'      => 'Thanks — we\'ll be in touch soon.',
			'captcha'      => false,
			'accent'       => '#2ab7f1',
			'style'        => array(),
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

	private static function sanitize_style( $style ) {
		if ( ! is_array( $style ) ) { return array(); }
		$fixed   = array( 'form', 'header', 'labels', 'inputs', 'submit' );
		$allowed = array( 'bg', 'color', 'hoverBg', 'borderColor', 'fs', 'fw', 'radius', 'border', 'shadow', 'align', 'text',
			'labelColor', 'labelFs', 'labelFw',
			'pt', 'pr', 'pb', 'pl', 'ptb', 'plr', 'mt', 'mr', 'mb', 'ml', 'mtb', 'mlr', '_pfour', '_mfour' );
		$out = array();
		foreach ( $style as $t => $vals ) {
			if ( ! is_array( $vals ) ) { continue; }
			$is_field = ( 0 === strpos( (string) $t, 'field:' ) );
			if ( ! $is_field && ! in_array( $t, $fixed, true ) ) { continue; }
			if ( $is_field && ! preg_match( '/^[a-z0-9_]+$/', substr( (string) $t, 6 ) ) ) { continue; }
			$clean = array();
			foreach ( $vals as $k => $v ) {
				if ( ! in_array( $k, $allowed, true ) ) { continue; }
				if ( is_bool( $v ) ) { $clean[ $k ] = $v; continue; }
				$clean[ $k ] = sanitize_text_field( (string) $v );
			}
			if ( $clean ) { $out[ $t ] = $clean; }
		}
		return $out;
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
			'style'        => self::sanitize_style( $form['style'] ?? array() ),
		);
		$types = array( 'text', 'email', 'tel', 'number', 'url', 'date', 'textarea', 'select', 'radio', 'checkbox', 'multiselect', 'country', 'name', 'consent', 'captcha', 'html', 'step', 'calc' );
		foreach ( (array) ( $form['fields'] ?? array() ) as $f ) {
			$label = sanitize_text_field( $f['label'] ?? '' );
			$key   = sanitize_key( $f['key'] ?? '' );
			if ( '' === $key ) {
				$slug = sanitize_key( str_replace( array( ' ', '-' ), '_', $label ) );
				$key  = $slug ? $slug : 'field_' . wp_rand( 100, 999 );
			}
			$w     = $f['width'] ?? 'full';
			$width = in_array( $w, array( 'full', 'half', 'third' ), true ) ? $w : 'full';

			// Conditional logic: show/hide this field based on another field's value.
			$cond = array();
			if ( ! empty( $f['cond'] ) && is_array( $f['cond'] ) ) {
				$action = ( isset( $f['cond']['action'] ) && 'hide' === $f['cond']['action'] ) ? 'hide' : 'show';
				$logic  = ( isset( $f['cond']['logic'] ) && 'any' === $f['cond']['logic'] ) ? 'any' : 'all';
				$rules  = array();
				foreach ( (array) ( $f['cond']['rules'] ?? array() ) as $r ) {
					$rfield = sanitize_key( $r['field'] ?? '' );
					if ( '' === $rfield ) {
						continue;
					}
					$op = in_array( $r['op'] ?? 'is', array( 'is', 'is_not', 'contains', 'gt', 'lt', 'empty', 'not_empty' ), true ) ? $r['op'] : 'is';
					$rules[] = array(
						'field' => $rfield,
						'op'    => $op,
						'value' => sanitize_text_field( $r['value'] ?? '' ),
					);
				}
				if ( $rules ) {
					$cond = array( 'action' => $action, 'logic' => $logic, 'rules' => $rules );
				}
			}

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
				'content'     => isset( $f['content'] ) ? wp_kses_post( $f['content'] ) : '',
				'min'         => isset( $f['min'] ) && '' !== $f['min'] ? sanitize_text_field( $f['min'] ) : '',
				'max'         => isset( $f['max'] ) && '' !== $f['max'] ? sanitize_text_field( $f['max'] ) : '',
				'pattern'     => isset( $f['pattern'] ) ? sanitize_text_field( $f['pattern'] ) : '',
				'pattern_msg' => isset( $f['pattern_msg'] ) ? sanitize_text_field( $f['pattern_msg'] ) : '',
				'calc'        => isset( $f['calc'] ) ? self::sanitize_formula( $f['calc'] ) : '',
				'calc_prefix' => isset( $f['calc_prefix'] ) ? sanitize_text_field( $f['calc_prefix'] ) : '',
				'calc_suffix' => isset( $f['calc_suffix'] ) ? sanitize_text_field( $f['calc_suffix'] ) : '',
				'cond'        => $cond,
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

	/**
	 * Build scoped CSS from a form's saved style settings (set in the Style editor).
	 * Scope is .velox-form[data-form="ID"] so multiple forms don't collide.
	 */
	public static function style_css( $id, $form ) {
		$s = ( isset( $form['style'] ) && is_array( $form['style'] ) ) ? $form['style'] : array();
		if ( empty( $s ) ) { return ''; }
		$scope = '.velox-form[data-form="' . (int) $id . '"]';
		$px = function ( $v ) {
			if ( $v === '' || $v === null ) { return ''; }
			return preg_match( '/[a-z%]/i', (string) $v ) ? $v : ( (float) $v ) . 'px';
		};
		$shadow = function ( $k ) {
			$map = array( 'none' => 'none', 'soft' => '0 1px 3px rgba(16,24,40,.10)', 'medium' => '0 8px 20px -6px rgba(16,24,40,.22)', 'strong' => '0 16px 40px -8px rgba(16,24,40,.32)' );
			return isset( $map[ $k ] ) ? $map[ $k ] : '';
		};
		$col = function ( $v ) {
			$v = trim( (string) $v );
			return ( $v !== '' && preg_match( '/^(#[0-9a-f]{3,8}|rgba?\([\d.,\s]+\)|[a-z]+)$/i', $v ) ) ? $v : '';
		};
		$rule = function ( $sel, $o ) use ( $px, $shadow, $col ) {
			$d = '';
			if ( ! empty( $o['bg'] ) && $col( $o['bg'] ) ) { $d .= 'background:' . $col( $o['bg'] ) . ';'; }
			if ( ! empty( $o['color'] ) && $col( $o['color'] ) ) { $d .= 'color:' . $col( $o['color'] ) . ';'; }
			if ( ! empty( $o['fs'] ) ) { $d .= 'font-size:' . $px( $o['fs'] ) . ';'; }
			if ( ! empty( $o['fw'] ) ) { $d .= 'font-weight:' . (int) $o['fw'] . ';'; }
			if ( ! empty( $o['radius'] ) ) { $d .= 'border-radius:' . $px( $o['radius'] ) . ';'; }
			if ( isset( $o['border'] ) && $o['border'] !== '' ) { $d .= 'border-width:' . $px( $o['border'] ) . ';border-style:solid;'; }
			if ( ! empty( $o['borderColor'] ) && $col( $o['borderColor'] ) ) { $d .= 'border-color:' . $col( $o['borderColor'] ) . ';'; }
			if ( ! empty( $o['shadow'] ) ) { $d .= 'box-shadow:' . $shadow( $o['shadow'] ) . ';'; }
			$has_p = isset( $o['pt'] ) || isset( $o['pr'] ) || isset( $o['pb'] ) || isset( $o['pl'] );
			if ( $has_p ) { $d .= 'padding:' . $px( $o['pt'] ?? 0 ) . ' ' . $px( $o['pr'] ?? 0 ) . ' ' . $px( $o['pb'] ?? 0 ) . ' ' . $px( $o['pl'] ?? 0 ) . ';'; }
			$has_m = isset( $o['mt'] ) || isset( $o['mr'] ) || isset( $o['mb'] ) || isset( $o['ml'] );
			if ( $has_m ) { $d .= 'margin:' . $px( $o['mt'] ?? 0 ) . ' ' . $px( $o['mr'] ?? 0 ) . ' ' . $px( $o['mb'] ?? 0 ) . ' ' . $px( $o['ml'] ?? 0 ) . ';'; }
			return $d ? ( $sel . '{' . $d . '}' ) : '';
		};
		// normalise 2-side (tb/lr) into 4-side before emitting
		$norm = function ( $o, $p ) {
			if ( isset( $o[ $p . 'tb' ] ) && $o[ $p . 'tb' ] !== '' ) { $o[ $p . 't' ] = $o[ $p . 'tb' ]; $o[ $p . 'b' ] = $o[ $p . 'tb' ]; }
			if ( isset( $o[ $p . 'lr' ] ) && $o[ $p . 'lr' ] !== '' ) { $o[ $p . 'l' ] = $o[ $p . 'lr' ]; $o[ $p . 'r' ] = $o[ $p . 'lr' ]; }
			return $o;
		};
		$css = '';
		$f = $norm( $s['form'] ?? array(), 'p' );
		$css .= $rule( $scope, array( 'bg' => $f['bg'] ?? '', 'radius' => $f['radius'] ?? '', 'shadow' => $f['shadow'] ?? '', 'border' => $f['border'] ?? '', 'borderColor' => $f['borderColor'] ?? '', 'pt' => $f['pt'] ?? null, 'pr' => $f['pr'] ?? null, 'pb' => $f['pb'] ?? null, 'pl' => $f['pl'] ?? null ) );
		$h = $s['header'] ?? array();
		$css .= $rule( $scope . ' .velox-form-title, ' . $scope . ' h3', array( 'color' => $h['color'] ?? '', 'fs' => $h['fs'] ?? '', 'fw' => $h['fw'] ?? '' ) );
		$l = $s['labels'] ?? array();
		$css .= $rule( $scope . ' .velox-form-label', array( 'color' => $l['color'] ?? '', 'fs' => $l['fs'] ?? '', 'fw' => $l['fw'] ?? '' ) );
		$inp = $s['inputs'] ?? array();
		$css .= $rule( $scope . ' .velox-form-field input, ' . $scope . ' .velox-form-field textarea, ' . $scope . ' .velox-form-field select', array( 'bg' => $inp['bg'] ?? '', 'color' => $inp['color'] ?? '', 'fs' => $inp['fs'] ?? '', 'radius' => $inp['radius'] ?? '', 'border' => $inp['border'] ?? '', 'borderColor' => $inp['borderColor'] ?? '' ) );
		$sub = $norm( $norm( $s['submit'] ?? array(), 'p' ), 'm' );
		if ( ! empty( $sub['align'] ) ) {
			$just = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end', 'full' => 'stretch' );
			$j = $just[ $sub['align'] ] ?? 'flex-start';
			$css .= $scope . '{align-items:' . $j . ';}';
			if ( 'full' === $sub['align'] ) { $css .= $scope . ' .velox-form-submit{width:100%;}'; }
		}
		$css .= $rule( $scope . ' .velox-form-submit', array( 'bg' => $sub['bg'] ?? '', 'color' => $sub['color'] ?? '', 'fs' => $sub['fs'] ?? '', 'fw' => $sub['fw'] ?? '', 'radius' => $sub['radius'] ?? '', 'shadow' => $sub['shadow'] ?? '', 'border' => $sub['border'] ?? '', 'borderColor' => $sub['borderColor'] ?? '', 'pt' => $sub['pt'] ?? null, 'pr' => $sub['pr'] ?? null, 'pb' => $sub['pb'] ?? null, 'pl' => $sub['pl'] ?? null, 'mt' => $sub['mt'] ?? null, 'mr' => $sub['mr'] ?? null, 'mb' => $sub['mb'] ?? null, 'ml' => $sub['ml'] ?? null ) );
		if ( ! empty( $sub['hoverBg'] ) && $col( $sub['hoverBg'] ) ) { $css .= $scope . ' .velox-form-submit:hover{background:' . $col( $sub['hoverBg'] ) . ';}'; }
		// Per-field overrides (set in the Style editor → Individual fields).
		foreach ( $s as $t => $o ) {
			if ( 0 !== strpos( (string) $t, 'field:' ) || ! is_array( $o ) ) { continue; }
			$k = substr( (string) $t, 6 );
			if ( ! preg_match( '/^[a-z0-9_]+$/', $k ) ) { continue; }
			$fsel = $scope . ' [data-vf-key="' . $k . '"]';
			$css .= $rule( $fsel . ' .velox-form-label', array( 'color' => $o['labelColor'] ?? '', 'fs' => $o['labelFs'] ?? '', 'fw' => $o['labelFw'] ?? '' ) );
			$css .= $rule( $fsel . ' input, ' . $fsel . ' textarea, ' . $fsel . ' select', array( 'bg' => $o['bg'] ?? '', 'color' => $o['color'] ?? '', 'fs' => $o['fs'] ?? '', 'radius' => $o['radius'] ?? '', 'border' => $o['border'] ?? '', 'borderColor' => $o['borderColor'] ?? '' ) );
		}
		return $css;
	}

	public static function render( $id ) {
		$form = self::get_form( $id );
		if ( ! $form ) {
			return '<!-- velox: form ' . (int) $id . ' not found -->';
		}
		self::$rendered = true;
		$accent = esc_attr( $form['accent'] );
		$nonce  = wp_create_nonce( 'velox_form_' . $id );
		$style_css = self::style_css( $id, $form );
		$has_cap_field = false;
		foreach ( $form['fields'] as $f ) {
			if ( 'captcha' === $f['type'] ) { $has_cap_field = true; break; }
		}

		ob_start();
		if ( $style_css ) { echo '<style>' . $style_css . '</style>'; }

		// Detect multi-step: any 'step' field acts as a page break. The label of a
		// step field becomes the title of the step that FOLLOWS it; the first step's
		// title can be set on a leading step field (or defaults).
		$has_steps = false;
		foreach ( $form['fields'] as $f ) {
			if ( 'step' === $f['type'] ) { $has_steps = true; break; }
		}

		if ( $has_steps ) {
			// Group fields into steps. A leading 'step' field titles step 1; otherwise
			// step 1 is untitled and starts immediately.
			$steps = array();
			$cur   = array( 'title' => '', 'fields' => array() );
			$first = true;
			foreach ( $form['fields'] as $f ) {
				if ( 'step' === $f['type'] ) {
					if ( $first && empty( $cur['fields'] ) ) {
						// leading step → titles the first step
						$cur['title'] = $f['label'];
						$first = false;
						continue;
					}
					$steps[] = $cur;
					$cur   = array( 'title' => $f['label'], 'fields' => array() );
					$first = false;
					continue;
				}
				$cur['fields'][] = $f;
				$first = false;
			}
			$steps[] = $cur;
			$total   = count( $steps );
			?>
			<form class="velox-form velox-form--steps" data-form="<?php echo (int) $id; ?>" data-steps="<?php echo (int) $total; ?>" style="--vf-accent:<?php echo $accent; ?>">
				<div class="velox-form-msg" hidden></div>
				<div class="velox-form-progress" aria-hidden="true">
					<?php for ( $i = 0; $i < $total; $i++ ) : ?>
						<div class="velox-form-pstep<?php echo 0 === $i ? ' is-active' : ''; ?>" data-step="<?php echo (int) $i; ?>">
							<span class="velox-form-pdot"><?php echo (int) ( $i + 1 ); ?></span>
							<?php if ( ! empty( $steps[ $i ]['title'] ) ) : ?><span class="velox-form-plabel"><?php echo esc_html( $steps[ $i ]['title'] ); ?></span><?php endif; ?>
						</div>
					<?php endfor; ?>
				</div>
				<?php foreach ( $steps as $si => $step ) : ?>
					<div class="velox-form-step<?php echo 0 === $si ? ' is-active' : ''; ?>" data-step="<?php echo (int) $si; ?>">
						<div class="velox-form-grid">
							<?php foreach ( $step['fields'] as $f ) : ?>
								<?php echo self::field_html( $f ); // phpcs:ignore ?>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
				<?php if ( ! empty( $form['captcha'] ) && ! $has_cap_field ) : echo self::captcha_widget(); endif; // phpcs:ignore ?>
				<input type="text" name="velox_hp" class="velox-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
				<input type="hidden" name="velox_nonce" value="<?php echo esc_attr( $nonce ); ?>">
				<div class="velox-form-nav">
					<button type="button" class="velox-form-prev" hidden>Back</button>
					<button type="button" class="velox-form-next">Next</button>
					<button type="submit" class="velox-form-submit" hidden><?php echo esc_html( $form['submit_label'] ); ?></button>
				</div>
			</form>
			<?php
		} else {
			?>
			<form class="velox-form" data-form="<?php echo (int) $id; ?>" style="--vf-accent:<?php echo $accent; ?>">
				<div class="velox-form-msg" hidden></div>
				<div class="velox-form-grid">
					<?php foreach ( $form['fields'] as $f ) : ?>
						<?php echo self::field_html( $f ); // phpcs:ignore ?>
					<?php endforeach; ?>
				</div>
				<?php if ( ! empty( $form['captcha'] ) && ! $has_cap_field ) : echo self::captcha_widget(); endif; // phpcs:ignore ?>
				<input type="text" name="velox_hp" class="velox-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
				<input type="hidden" name="velox_nonce" value="<?php echo esc_attr( $nonce ); ?>">
				<button type="submit" class="velox-form-submit"><?php echo esc_html( $form['submit_label'] ); ?></button>
			</form>
			<?php
		}
		return ob_get_clean();
	}

	private static function countries() {
		return array( 'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Argentina', 'Armenia', 'Australia', 'Austria', 'Azerbaijan', 'Bahrain', 'Bangladesh', 'Belarus', 'Belgium', 'Bolivia', 'Bosnia and Herzegovina', 'Brazil', 'Bulgaria', 'Cambodia', 'Cameroon', 'Canada', 'Chile', 'China', 'Colombia', 'Costa Rica', 'Croatia', 'Cuba', 'Cyprus', 'Czechia', 'Denmark', 'Dominican Republic', 'Ecuador', 'Egypt', 'Estonia', 'Ethiopia', 'Finland', 'France', 'Georgia', 'Germany', 'Ghana', 'Greece', 'Guatemala', 'Honduras', 'Hong Kong', 'Hungary', 'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy', 'Japan', 'Jordan', 'Kazakhstan', 'Kenya', 'Kuwait', 'Latvia', 'Lebanon', 'Liechtenstein', 'Lithuania', 'Luxembourg', 'Malaysia', 'Malta', 'Mexico', 'Monaco', 'Morocco', 'Netherlands', 'New Zealand', 'Nigeria', 'North Macedonia', 'Norway', 'Oman', 'Pakistan', 'Panama', 'Paraguay', 'Peru', 'Philippines', 'Poland', 'Portugal', 'Qatar', 'Romania', 'Saudi Arabia', 'Serbia', 'Singapore', 'Slovakia', 'Slovenia', 'South Africa', 'South Korea', 'Spain', 'Sri Lanka', 'Sweden', 'Switzerland', 'Taiwan', 'Thailand', 'Tunisia', 'Turkey', 'Ukraine', 'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay', 'Venezuela', 'Vietnam' );
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
		$wkey  = isset( $f['width'] ) ? $f['width'] : 'full';
		$width = 'half' === $wkey ? ' velox-form-field--half' : ( 'third' === $wkey ? ' velox-form-field--third' : '' );
		$css   = ! empty( $f['css'] ) ? ' ' . esc_attr( $f['css'] ) : '';
		$opts  = array_filter( array_map( 'trim', explode( "\n", (string) ( $f['options'] ?? '' ) ) ) );
		$type  = $f['type'];
		$fkey_attr = ' data-vf-key="' . $key . '"';

		// Conditional logic → a data attribute the front-end evaluator reads.
		$cond_attr = '';
		if ( ! empty( $f['cond'] ) && ! empty( $f['cond']['rules'] ) ) {
			$cond_attr = " data-vf-cond='" . esc_attr( wp_json_encode( $f['cond'] ) ) . "'";
		}
		// Validation attributes (min / max / pattern) for applicable input types.
		$vattr = '';
		if ( in_array( $type, array( 'number', 'date' ), true ) ) {
			if ( isset( $f['min'] ) && '' !== $f['min'] ) { $vattr .= ' min="' . esc_attr( $f['min'] ) . '"'; }
			if ( isset( $f['max'] ) && '' !== $f['max'] ) { $vattr .= ' max="' . esc_attr( $f['max'] ) . '"'; }
		}
		if ( in_array( $type, array( 'text', 'tel', 'url', 'email' ), true ) ) {
			if ( isset( $f['min'] ) && '' !== $f['min'] ) { $vattr .= ' minlength="' . esc_attr( (int) $f['min'] ) . '"'; }
			if ( isset( $f['max'] ) && '' !== $f['max'] ) { $vattr .= ' maxlength="' . esc_attr( (int) $f['max'] ) . '"'; }
			if ( ! empty( $f['pattern'] ) ) {
				$vattr .= ' pattern="' . esc_attr( $f['pattern'] ) . '"';
				if ( ! empty( $f['pattern_msg'] ) ) { $vattr .= ' title="' . esc_attr( $f['pattern_msg'] ) . '"'; }
			}
		}

		ob_start();
		if ( 'step' === $type ) {
			// Structural only — consumed by render(); never output inline.
			return '';
		} elseif ( 'calc' === $type ) {
			// A read-only computed field. The formula lives in data-vf-calc; the
			// front-end fills the value. Submitted as a normal input for storage.
			$formula = isset( $f['calc'] ) ? $f['calc'] : '';
			$prefix  = isset( $f['calc_prefix'] ) ? $f['calc_prefix'] : '';
			$suffix  = isset( $f['calc_suffix'] ) ? $f['calc_suffix'] : '';
			?>
			<label class="velox-form-field velox-form-calc<?php echo esc_attr( $width . $css ); ?>"<?php echo $fkey_attr . $cond_attr; // phpcs:ignore ?>>
				<?php if ( '' !== $label ) : ?><span class="velox-form-label"><?php echo $label . $star; ?></span><?php endif; ?>
				<div class="velox-form-calc-wrap">
					<?php if ( '' !== $prefix ) : ?><span class="velox-form-calc-fix"><?php echo esc_html( $prefix ); ?></span><?php endif; ?>
					<input type="text" name="<?php echo esc_attr( $name ); ?>" class="velox-form-calc-input" readonly
						data-vf-calc="<?php echo esc_attr( $formula ); ?>"
						data-vf-prefix="<?php echo esc_attr( $prefix ); ?>" data-vf-suffix="<?php echo esc_attr( $suffix ); ?>" value="">
					<?php if ( '' !== $suffix ) : ?><span class="velox-form-calc-fix"><?php echo esc_html( $suffix ); ?></span><?php endif; ?>
				</div>
				<?php echo $help; // phpcs:ignore ?>
			</label>
			<?php
		} elseif ( 'html' === $type ) {
			echo '<div class="velox-form-row velox-form-html' . esc_attr( $width . $css ) . '"' . $fkey_attr . $cond_attr . '>' . ( isset( $f['content'] ) ? $f['content'] : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput // phpcs:ignore WordPress.Security.EscapeOutput
		} elseif ( 'captcha' === $type ) {
			echo '<div class="velox-form-row' . esc_attr( $width . $css ) . '"' . $fkey_attr . $cond_attr . '>' . self::captcha_widget() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput // phpcs:ignore WordPress.Security.EscapeOutput
		} elseif ( 'consent' === $type || 'checkbox' === $type ) {
			?>
			<div class="velox-form-row velox-form-row--check<?php echo esc_attr( $width . $css ); ?>"<?php echo $fkey_attr . $cond_attr; // phpcs:ignore ?>>
				<label class="velox-form-consent">
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1"<?php echo $rq; ?>>
					<span><?php echo $label . $star; ?></span>
				</label>
				<?php echo $help; // phpcs:ignore ?>
			</div>
			<?php
		} elseif ( 'radio' === $type || 'multiselect' === $type ) {
			$itype = 'radio' === $type ? 'radio' : 'checkbox';
			$iname = 'radio' === $type ? $name : $name . '[]';
			?>
			<div class="velox-form-row<?php echo esc_attr( $width . $css ); ?>"<?php echo $fkey_attr . $cond_attr; // phpcs:ignore ?>>
				<span class="velox-form-label"><?php echo $label . $star; ?></span>
				<div class="velox-form-radios">
					<?php foreach ( $opts as $opt ) : $checked = ( $def === $opt ) ? ' checked' : ''; ?>
						<label class="velox-form-radio"><input type="<?php echo esc_attr( $itype ); ?>" name="<?php echo esc_attr( $iname ); ?>" value="<?php echo esc_attr( $opt ); ?>"<?php echo $checked . ( 'radio' === $type ? $rq : '' ); ?>> <span><?php echo esc_html( $opt ); ?></span></label>
					<?php endforeach; ?>
				</div>
				<?php echo $help; // phpcs:ignore ?>
			</div>
			<?php
		} elseif ( 'name' === $type ) {
			$f_lbl = $opts[0] ?? 'First name';
			$l_lbl = $opts[1] ?? 'Last name';
			?>
			<div class="velox-form-row velox-form-name<?php echo esc_attr( $width . $css ); ?>"<?php echo $fkey_attr . $cond_attr; // phpcs:ignore ?>>
				<?php if ( '' !== $label ) : ?><span class="velox-form-label"><?php echo $label . $star; ?></span><?php endif; ?>
				<div class="velox-form-name-row">
					<input type="text" name="<?php echo esc_attr( $name ); ?>[first]" placeholder="<?php echo esc_attr( $f_lbl ); ?>"<?php echo $rq; ?>>
					<input type="text" name="<?php echo esc_attr( $name ); ?>[last]" placeholder="<?php echo esc_attr( $l_lbl ); ?>"<?php echo $rq; ?>>
				</div>
				<?php echo $help; // phpcs:ignore ?>
			</div>
			<?php
		} else {
			?>
			<label class="velox-form-field<?php echo esc_attr( $width . $css ); ?>"<?php echo $fkey_attr . $cond_attr; // phpcs:ignore ?>>
				<?php if ( '' !== $label ) : ?><span class="velox-form-label"><?php echo $label . $star; ?></span><?php endif; ?>
				<?php if ( 'textarea' === $type ) : ?>
					<textarea name="<?php echo esc_attr( $name ); ?>" rows="5" placeholder="<?php echo $ph; ?>"<?php echo $rq; ?>><?php echo esc_textarea( $def ); ?></textarea>
				<?php elseif ( 'select' === $type || 'country' === $type ) : ?>
					<?php $list = 'country' === $type ? self::countries() : $opts; ?>
					<select name="<?php echo esc_attr( $name ); ?>"<?php echo $rq; ?>>
						<option value=""><?php echo 'country' === $type ? 'Select a country…' : '—'; ?></option>
						<?php foreach ( $list as $opt ) : ?>
							<option value="<?php echo esc_attr( $opt ); ?>"<?php selected( $def, $opt ); ?>><?php echo esc_html( $opt ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $name ); ?>" placeholder="<?php echo $ph; ?>" value="<?php echo esc_attr( $def ); ?>"<?php echo $rq . $vattr; // phpcs:ignore ?>>
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

	/** Global gate. When off, no form may use CAPTCHA regardless of its own setting. */
	public static function captcha_enabled() {
		return (bool) Velox_Settings::get( 'mail_captcha_enabled', false );
	}

	/** A form can actually show CAPTCHA only when the global gate is on AND keys exist. */
	public static function captcha_ready() {
		return self::captcha_enabled()
			&& Velox_Settings::get( 'mail_captcha_site', '' )
			&& Velox_Settings::get( 'mail_captcha_secret', '' );
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

	/**
	 * Calculation formulas are intentionally restricted to a tiny, safe grammar:
	 * {field_key} references, numbers, + - * / ( ) and spaces. Anything else is
	 * stripped so a formula can never execute arbitrary code.
	 */
	public static function sanitize_formula( $raw ) {
		$raw = (string) $raw;
		// keep {keys}, digits, dot, operators, parens, spaces
		return trim( preg_replace( '/[^0-9a-zA-Z_{}\.\+\-\*\/\(\)\s]/', '', $raw ) );
	}

	/**
	 * Evaluate a sanitized formula against submitted values. Pure arithmetic via
	 * the shunting-yard algorithm — no eval(), no code execution. Returns float.
	 */
	public static function eval_formula( $formula, $values ) {
		// Replace {key} with its numeric value (non-numeric → 0).
		$expr = preg_replace_callback( '/\{([a-z0-9_]+)\}/i', function ( $m ) use ( $values ) {
			$v = isset( $values[ $m[1] ] ) ? $values[ $m[1] ] : 0;
			if ( is_array( $v ) ) { $v = count( $v ); }
			// pull the first number out of the value (e.g. "12 left" → 12)
			return preg_match( '/-?\d+(\.\d+)?/', (string) $v, $mm ) ? $mm[0] : '0';
		}, (string) $formula );

		// Tokenize.
		if ( ! preg_match_all( '/\d+\.?\d*|[\+\-\*\/\(\)]/', $expr, $tok ) ) {
			return 0.0;
		}
		$tokens = $tok[0];
		$prec   = array( '+' => 1, '-' => 1, '*' => 2, '/' => 2 );
		$output = array();
		$ops    = array();
		foreach ( $tokens as $t ) {
			if ( is_numeric( $t ) ) {
				$output[] = (float) $t;
			} elseif ( isset( $prec[ $t ] ) ) {
				while ( $ops && end( $ops ) !== '(' && isset( $prec[ end( $ops ) ] ) && $prec[ end( $ops ) ] >= $prec[ $t ] ) {
					$output[] = array_pop( $ops );
				}
				$ops[] = $t;
			} elseif ( '(' === $t ) {
				$ops[] = $t;
			} elseif ( ')' === $t ) {
				while ( $ops && end( $ops ) !== '(' ) {
					$output[] = array_pop( $ops );
				}
				array_pop( $ops ); // discard '('
			}
		}
		while ( $ops ) {
			$output[] = array_pop( $ops );
		}
		// Evaluate RPN.
		$stack = array();
		foreach ( $output as $t ) {
			if ( is_float( $t ) || is_int( $t ) ) {
				$stack[] = $t;
			} else {
				$b = array_pop( $stack );
				$a = array_pop( $stack );
				if ( null === $a || null === $b ) { return 0.0; }
				switch ( $t ) {
					case '+': $stack[] = $a + $b; break;
					case '-': $stack[] = $a - $b; break;
					case '*': $stack[] = $a * $b; break;
					case '/': $stack[] = 0.0 == $b ? 0.0 : $a / $b; break;
				}
			}
		}
		return $stack ? (float) end( $stack ) : 0.0;
	}

	/* -------------------------------------------------------------- submission */

	/**
	 * Server-side mirror of the front-end conditional-logic evaluator.
	 * Returns true if a field with this $cond should be active (visible) given
	 * the submitted values keyed by field key.
	 */
	public static function cond_active( $cond, $values ) {
		if ( empty( $cond ) || empty( $cond['rules'] ) ) {
			return true;
		}
		$results = array();
		foreach ( $cond['rules'] as $r ) {
			$fv  = isset( $values[ $r['field'] ] ) ? $values[ $r['field'] ] : '';
			$arr = is_array( $fv ) ? array_map( 'strval', $fv ) : array( (string) $fv );
			$cmp = (string) ( $r['value'] ?? '' );
			$met = true;
			switch ( $r['op'] ) {
				case 'is':        $met = in_array( $cmp, $arr, true ); break;
				case 'is_not':    $met = ! in_array( $cmp, $arr, true ); break;
				case 'contains':  $met = (bool) array_filter( $arr, function ( $x ) use ( $cmp ) { return '' !== $cmp && stripos( $x, $cmp ) !== false; } ); break;
				case 'gt':        $met = (float) ( $arr[0] ?? 0 ) > (float) $cmp; break;
				case 'lt':        $met = (float) ( $arr[0] ?? 0 ) < (float) $cmp; break;
				case 'empty':     $met = ( '' === implode( '', $arr ) ); break;
				case 'not_empty': $met = ( '' !== implode( '', $arr ) ); break;
			}
			$results[] = $met;
		}
		$logic = ( isset( $cond['logic'] ) && 'any' === $cond['logic'] ) ? 'any' : 'all';
		$pass  = 'any' === $logic ? in_array( true, $results, true ) : ! in_array( false, $results, true );
		$action = ( isset( $cond['action'] ) && 'hide' === $cond['action'] ) ? 'hide' : 'show';
		return 'hide' === $action ? ! $pass : $pass;
	}

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

		$data    = array();
		$errors  = array();
		$has_cap = ! empty( $form['captcha'] ) && self::captcha_ready();

		// Build a key→submitted-value map first so conditional logic can read siblings.
		$submitted = array();
		foreach ( $form['fields'] as $f ) {
			$k = $f['key'];
			$v = isset( $raw[ $k ] ) ? $raw[ $k ] : '';
			if ( is_array( $v ) ) {
				$submitted[ $k ] = isset( $v['first'] ) || isset( $v['last'] )
					? trim( ( $v['first'] ?? '' ) . ' ' . ( $v['last'] ?? '' ) )
					: array_map( 'strval', $v );
			} else {
				$submitted[ $k ] = (string) $v;
			}
		}

		foreach ( $form['fields'] as $f ) {
			$key  = $f['key'];
			$type = $f['type'];
			$val  = isset( $raw[ $key ] ) ? $raw[ $key ] : '';

			// Conditionally-hidden fields are skipped entirely (no data, no required check).
			if ( ! empty( $f['cond'] ) && ! self::cond_active( $f['cond'], $submitted ) ) {
				continue;
			}

			// Presentational / structural fields carry no user data.
			if ( 'html' === $type || 'step' === $type ) {
				continue;
			}
			if ( 'calc' === $type ) {
				// Recompute server-side from the other submitted values (ignore the
				// posted value entirely, so it can't be tampered with).
				$result = self::eval_formula( isset( $f['calc'] ) ? $f['calc'] : '', $submitted );
				// tidy: drop trailing .0
				$num = ( floor( $result ) == $result ) ? (string) (int) $result : rtrim( rtrim( number_format( $result, 4, '.', '' ), '0' ), '.' );
				$data[ $key ] = ( isset( $f['calc_prefix'] ) ? $f['calc_prefix'] : '' ) . $num . ( isset( $f['calc_suffix'] ) ? $f['calc_suffix'] : '' );
				continue;
			}
			if ( 'captcha' === $type ) {
				$has_cap = true;
				continue;
			}
			if ( in_array( $type, array( 'consent', 'checkbox' ), true ) ) {
				$checked = ! empty( $val );
				if ( $f['required'] && ! $checked ) {
					$errors[ $key ] = 'required';
				}
				$data[ $key ] = $checked ? 'yes' : 'no';
				continue;
			}
			if ( 'name' === $type ) {
				$first = isset( $val['first'] ) ? trim( wp_strip_all_tags( (string) $val['first'] ) ) : '';
				$last  = isset( $val['last'] ) ? trim( wp_strip_all_tags( (string) $val['last'] ) ) : '';
				$full  = trim( $first . ' ' . $last );
				if ( $f['required'] && '' === $full ) {
					$errors[ $key ] = 'required';
				}
				$data[ $key ] = $full;
				continue;
			}
			if ( 'multiselect' === $type ) {
				$picked = is_array( $val ) ? array_map( function ( $v ) { return trim( wp_strip_all_tags( (string) $v ) ); }, $val ) : array();
				$picked = array_filter( $picked );
				if ( $f['required'] && empty( $picked ) ) {
					$errors[ $key ] = 'required';
				}
				$data[ $key ] = implode( ', ', $picked );
				continue;
			}
			$val = is_array( $val ) ? '' : trim( wp_strip_all_tags( (string) $val ) );
			if ( $f['required'] && '' === $val ) {
				$errors[ $key ] = 'required';
			}
			if ( 'email' === $type && '' !== $val && ! is_email( $val ) ) {
				$errors[ $key ] = 'invalid';
			}
			// Length / range / pattern validation (only when a value is present).
			if ( '' !== $val && ! isset( $errors[ $key ] ) ) {
				if ( in_array( $type, array( 'number', 'date' ), true ) ) {
					if ( 'number' === $type ) {
						$num = (float) $val;
						if ( '' !== (string) $f['min'] && $num < (float) $f['min'] ) { $errors[ $key ] = 'min'; }
						if ( '' !== (string) $f['max'] && $num > (float) $f['max'] ) { $errors[ $key ] = 'max'; }
					} else { // date — lexical compare works for YYYY-MM-DD
						if ( '' !== (string) $f['min'] && $val < $f['min'] ) { $errors[ $key ] = 'min'; }
						if ( '' !== (string) $f['max'] && $val > $f['max'] ) { $errors[ $key ] = 'max'; }
					}
				} elseif ( in_array( $type, array( 'text', 'tel', 'url' ), true ) ) {
					$len = function_exists( 'mb_strlen' ) ? mb_strlen( $val ) : strlen( $val );
					if ( '' !== (string) $f['min'] && $len < (int) $f['min'] ) { $errors[ $key ] = 'min'; }
					if ( '' !== (string) $f['max'] && $len > (int) $f['max'] ) { $errors[ $key ] = 'max'; }
					if ( ! empty( $f['pattern'] ) ) {
						$delim = '#';
						$pat   = $delim . str_replace( $delim, '\\' . $delim, $f['pattern'] ) . $delim . 'u';
						// Anchor like the HTML pattern attribute (whole-string match).
						$anchored = $delim . '^(?:' . str_replace( $delim, '\\' . $delim, $f['pattern'] ) . ')$' . $delim . 'u';
						if ( false === @preg_match( $anchored, $val, $m ) || ! $m ) {
							$errors[ $key ] = 'pattern';
						}
					}
				}
			}
			$data[ $key ] = $val;
		}

		if ( $errors ) {
			wp_send_json_error( array( 'message' => 'Please check the highlighted fields.', 'fields' => array_keys( $errors ) ) );
		}
		if ( $has_cap && ! self::verify_captcha() ) {
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
		if ( class_exists( 'Velox_Stats' ) ) { Velox_Stats::bump_form(); }

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
				if ( in_array( $f['type'], array( 'consent', 'html', 'captcha' ), true ) ) {
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

	/**
	 * Inbox feed: every submission across all forms, newest first, each decorated
	 * with its form title and a best-guess "who" (name/email) + short preview, so
	 * the inbox can show who wrote, when, and through which form at a glance.
	 *
	 * @return array list of { id, form_id, form_title, created, ip, who, email, preview, data }
	 */
	public static function inbox( $limit = 200, $offset = 0, $form_id = 0 ) {
		global $wpdb;
		$t = self::table();
		if ( $form_id ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE form_id = %d ORDER BY id DESC LIMIT %d OFFSET %d", $form_id, $limit, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB
		}
		$rows = $rows ?: array();

		// Cache form titles so we don't re-load a form per row.
		$titles = array();
		foreach ( self::forms() as $f ) {
			$titles[ (int) $f['id'] ] = $f['title'];
		}

		$out = array();
		foreach ( $rows as $r ) {
			$d = json_decode( $r['data'], true );
			$d = is_array( $d ) ? $d : array();
			$out[] = array(
				'id'         => (int) $r['id'],
				'form_id'    => (int) $r['form_id'],
				'form_title' => isset( $titles[ (int) $r['form_id'] ] ) ? $titles[ (int) $r['form_id'] ] : 'Form #' . (int) $r['form_id'],
				'created'    => $r['created'],
				'ip'         => $r['ip'],
				'who'        => self::derive_who( $d ),
				'email'      => self::derive_email( $d ),
				'preview'    => self::derive_preview( $d ),
				'data'       => $d,
			);
		}
		return $out;
	}

	/** Best-guess display name from a submission's fields. */
	private static function derive_who( $d ) {
		foreach ( array( 'name', 'your-name', 'full_name', 'fullname', 'first_name', 'vorname', 'firstname' ) as $k ) {
			if ( ! empty( $d[ $k ] ) && is_scalar( $d[ $k ] ) ) {
				return trim( (string) $d[ $k ] );
			}
		}
		// fall back to email, else the first non-empty scalar
		$email = self::derive_email( $d );
		if ( $email ) {
			return $email;
		}
		foreach ( $d as $v ) {
			if ( is_scalar( $v ) && '' !== trim( (string) $v ) ) {
				return trim( (string) $v );
			}
		}
		return 'Anonymous';
	}

	private static function derive_email( $d ) {
		foreach ( $d as $k => $v ) {
			if ( is_scalar( $v ) && is_email( (string) $v ) ) {
				return (string) $v;
			}
			if ( false !== stripos( (string) $k, 'email' ) && is_scalar( $v ) && '' !== trim( (string) $v ) ) {
				return trim( (string) $v );
			}
		}
		return '';
	}

	private static function derive_preview( $d ) {
		$parts = array();
		foreach ( $d as $v ) {
			if ( is_array( $v ) ) { $v = implode( ', ', $v ); }
			if ( is_scalar( $v ) && '' !== trim( (string) $v ) ) {
				$parts[] = trim( (string) $v );
			}
			if ( count( $parts ) >= 3 ) { break; }
		}
		$s = implode( '  ·  ', $parts );
		return mb_strlen( $s ) > 140 ? mb_substr( $s, 0, 140 ) . '…' : $s;
	}

	/** One submission with its form context, for the detail panel. */
	public static function submission( $sid ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE id = %d", (int) $sid ), ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( ! $row ) {
			return null;
		}
		$d = json_decode( $row['data'], true );
		$row['data']   = is_array( $d ) ? $d : array();
		$row['labels'] = self::field_labels( (int) $row['form_id'] );
		$form          = self::get_form( (int) $row['form_id'] );
		$row['form_title'] = $form ? $form['title'] : 'Form #' . (int) $row['form_id'];
		$row['who']    = self::derive_who( $row['data'] );
		$row['email']  = self::derive_email( $row['data'] );
		return $row;
	}

	/** Total entries, optionally for one form. */
	public static function submission_count( $form_id = 0 ) {
		global $wpdb;
		$t = self::table();
		if ( $form_id ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE form_id = %d", $form_id ) ); // phpcs:ignore WordPress.DB
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" ); // phpcs:ignore WordPress.DB
	}

	/** Entries received within the last N days. */
	public static function submission_count_recent( $days = 7 ) {
		global $wpdb;
		$t     = self::table();
		$since = gmdate( 'Y-m-d H:i:s', (int) current_time( 'timestamp' ) - ( (int) $days * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DateTime
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE created >= %s", $since ) ); // phpcs:ignore WordPress.DB
	}

	/** Map a form's field keys → human labels, for rendering entries. */
	public static function field_labels( $form_id ) {
		$form = self::get_form( (int) $form_id );
		$map  = array();
		if ( $form && ! empty( $form['fields'] ) ) {
			foreach ( $form['fields'] as $f ) {
				if ( ! empty( $f['key'] ) ) {
					$map[ $f['key'] ] = ! empty( $f['label'] ) ? $f['label'] : $f['key'];
				}
			}
		}
		return $map;
	}

	/**
	 * Stream every submission for a form as a CSV download.
	 * Columns: a stable union of the form's field keys (in form order) plus any
	 * extra keys found in the data, followed by Submitted-at and IP.
	 */
	public static function export_csv( $form_id ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'velox' ) );
		}
		$form_id = (int) $form_id;
		$form    = self::get_form( $form_id );
		$labels  = self::field_labels( $form_id );
		$subs    = self::submissions( $form_id, 100000 );

		// Column order: form fields first (skip presentational), then any stray keys.
		$cols = array();
		if ( $form && ! empty( $form['fields'] ) ) {
			foreach ( $form['fields'] as $f ) {
				if ( in_array( $f['type'], array( 'html', 'captcha' ), true ) ) {
					continue;
				}
				if ( ! empty( $f['key'] ) && ! in_array( $f['key'], $cols, true ) ) {
					$cols[] = $f['key'];
				}
			}
		}
		foreach ( $subs as $s ) {
			$d = json_decode( $s['data'], true );
			if ( is_array( $d ) ) {
				foreach ( array_keys( $d ) as $k ) {
					if ( ! in_array( $k, $cols, true ) ) {
						$cols[] = $k;
					}
				}
			}
		}

		$slug  = $form ? sanitize_title( $form['title'] ) : 'form-' . $form_id;
		$fname = 'velox-' . ( $slug ? $slug : 'form' ) . '-entries-' . gmdate( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $fname . '"' );

		$out = fopen( 'php://output', 'w' );
		// UTF-8 BOM so Excel reads accents/Umlauts correctly.
		fwrite( $out, "\xEF\xBB\xBF" );

		$header = array();
		foreach ( $cols as $k ) {
			$header[] = isset( $labels[ $k ] ) ? $labels[ $k ] : ucwords( str_replace( array( '_', '-' ), ' ', $k ) );
		}
		$header[] = 'Submitted';
		$header[] = 'IP';
		fputcsv( $out, $header );

		foreach ( $subs as $s ) {
			$d   = json_decode( $s['data'], true );
			$d   = is_array( $d ) ? $d : array();
			$row = array();
			foreach ( $cols as $k ) {
				$v = isset( $d[ $k ] ) ? $d[ $k ] : '';
				$row[] = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
			}
			$row[] = $s['created'];
			$row[] = isset( $s['ip'] ) ? $s['ip'] : '';
			fputcsv( $out, $row );
		}
		fclose( $out );
		exit;
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
.velox-form-field--third{width:calc(33.333% - 11px)}
.velox-form-name-row{display:flex;gap:12px}
.velox-form-name-row input{flex:1;min-width:0}
@media(max-width:520px){.velox-form-field--half,.velox-form-field--third{width:100%}}
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
.velox-form-steps .velox-form-step{display:none}
.velox-form-steps .velox-form-step.is-active{display:block}
.velox-form-progress{display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap}
.velox-form-pstep{display:inline-flex;align-items:center;gap:7px;opacity:.5;transition:opacity .2s}
.velox-form-pstep.is-active{opacity:1}
.velox-form-pdot{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:#e6e8ec;color:#51565d;font-size:13px;font-weight:600;flex:none}
.velox-form-pstep.is-active .velox-form-pdot{background:var(--vf-accent);color:#fff}
.velox-form-plabel{font-size:13px;font-weight:600;color:#1d1d1f}
.velox-form-nav{display:flex;gap:10px;align-items:center}
.velox-form-prev,.velox-form-next{border:1px solid #d4d8e0;background:#fff;color:#1d1d1f;border-radius:10px;padding:11px 22px;font-size:15px;font-weight:600;cursor:pointer;transition:border-color .15s}
.velox-form-next{background:var(--vf-accent);color:#fff;border-color:transparent}
.velox-form-prev:hover{border-color:#9aa0a9}
.velox-form-next:hover{filter:brightness(.93)}
.velox-form-calc-wrap{display:flex;align-items:center;gap:6px}
.velox-form-calc-fix{font-size:15px;color:#6b7280;font-weight:600}
.velox-form-calc-input{background:#f6f7f9!important;font-weight:600}
</style>
<script>
(function(){
  document.querySelectorAll('.velox-form').forEach(function(form){
    /* ---- conditional logic: show/hide fields based on other answers ---- */
    function fieldValue(key){
      var els=form.querySelectorAll('[name="vf['+key+']"],[name="vf['+key+'][]"]');
      if(!els.length){return '';}
      var first=els[0];
      if(first.type==='checkbox'||first.type==='radio'){
        var picked=[];
        els.forEach(function(el){if(el.checked){picked.push(el.value==='1'&&el.type==='checkbox'?'1':el.value);}});
        return picked;
      }
      return first.value;
    }
    function ruleMet(r){
      var v=fieldValue(r.field);
      var arr=Array.isArray(v)?v:[String(v)];
      var val=String(r.value);
      switch(r.op){
        case 'is': return arr.indexOf(val)!==-1;
        case 'is_not': return arr.indexOf(val)===-1;
        case 'contains': return arr.some(function(x){return String(x).toLowerCase().indexOf(val.toLowerCase())!==-1;});
        case 'gt': return parseFloat(arr[0])>parseFloat(val);
        case 'lt': return parseFloat(arr[0])<parseFloat(val);
        case 'empty': return !arr.length||arr.join('')==='';
        case 'not_empty': return arr.join('')!=='';
      }
      return true;
    }
    var condEls=[].slice.call(form.querySelectorAll('[data-vf-cond]'));
    function applyCond(){
      condEls.forEach(function(el){
        var cfg;try{cfg=JSON.parse(el.getAttribute('data-vf-cond'));}catch(e){return;}
        if(!cfg||!cfg.rules||!cfg.rules.length){return;}
        var results=cfg.rules.map(ruleMet);
        var pass=cfg.logic==='any'?results.some(Boolean):results.every(Boolean);
        var visible=cfg.action==='hide'?!pass:pass;
        el.style.display=visible?'':'none';
        /* disable inputs in hidden fields so they don't submit or block on required */
        el.querySelectorAll('input,select,textarea').forEach(function(inp){inp.disabled=!visible;});
      });
    }
    if(condEls.length){
      form.addEventListener('input',applyCond);
      form.addEventListener('change',applyCond);
      applyCond();
    }

    /* ---- live calculations ---- */
    var calcEls=[].slice.call(form.querySelectorAll('[data-vf-calc]'));
    function numFrom(key){
      var els=form.querySelectorAll('[name="vf['+key+']"],[name="vf['+key+'][]"]');
      if(!els.length){return 0;}
      var first=els[0];
      if(first.type==='checkbox'||first.type==='radio'){
        var n=0;els.forEach(function(el){if(el.checked){var m=String(el.value).match(/-?\d+(\.\d+)?/);n+=m?parseFloat(m[0]):0;}});return n;
      }
      var m=String(first.value).match(/-?\d+(\.\d+)?/);return m?parseFloat(m[0]):0;
    }
    function evalFormula(expr){
      expr=expr.replace(/\{([a-z0-9_]+)\}/gi,function(_,k){return '('+numFrom(k)+')';});
      if(!/^[0-9+\-*/(). ]*$/.test(expr)){return 0;}
      try{ /* eslint-disable no-new-func */ var r=Function('"use strict";return ('+(expr||'0')+')')(); return (typeof r==='number'&&isFinite(r))?r:0; }catch(e){return 0;}
    }
    function applyCalc(){
      calcEls.forEach(function(el){
        var v=evalFormula(el.getAttribute('data-vf-calc')||'');
        var out=(Math.round(v*10000)/10000);
        el.value=(el.getAttribute('data-vf-prefix')||'')+out+(el.getAttribute('data-vf-suffix')||'');
      });
    }
    if(calcEls.length){
      form.addEventListener('input',applyCalc);
      form.addEventListener('change',applyCalc);
      applyCalc();
    }

    /* ---- multi-step navigation ---- */
    var steps=[].slice.call(form.querySelectorAll('.velox-form-step'));
    if(steps.length>1){
      var cur=0;
      var prevBtn=form.querySelector('.velox-form-prev');
      var nextBtn=form.querySelector('.velox-form-next');
      var subBtn=form.querySelector('.velox-form-submit');
      var pSteps=[].slice.call(form.querySelectorAll('.velox-form-pstep'));
      function showStep(n){
        cur=Math.max(0,Math.min(steps.length-1,n));
        steps.forEach(function(s,i){s.classList.toggle('is-active',i===cur);});
        pSteps.forEach(function(p,i){p.classList.toggle('is-active',i<=cur);p.classList.toggle('is-current',i===cur);});
        if(prevBtn){prevBtn.hidden=cur===0;}
        var last=cur===steps.length-1;
        if(nextBtn){nextBtn.hidden=last;}
        if(subBtn){subBtn.hidden=!last;}
        var top=form.getBoundingClientRect().top+window.pageYOffset-20;
        if(window.pageYOffset>top){window.scrollTo({top:top,behavior:'smooth'});}
      }
      function stepValid(){
        var ok=true;
        var visible=steps[cur].querySelectorAll('input,select,textarea');
        for(var i=0;i<visible.length;i++){
          var el=visible[i];
          if(el.disabled||el.closest('[style*="display: none"]')){continue;}
          if(typeof el.reportValidity==='function'&&!el.checkValidity()){el.reportValidity();ok=false;break;}
        }
        return ok;
      }
      if(nextBtn){nextBtn.addEventListener('click',function(){if(stepValid()){showStep(cur+1);}});}
      if(prevBtn){prevBtn.addEventListener('click',function(){showStep(cur-1);});}
      showStep(0);
    }

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
            if(condEls.length){applyCond();}
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
