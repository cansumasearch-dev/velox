<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mail backbone: routes wp_mail through SMTP (when configured) and keeps a small
 * send log. Velox_Forms uses send() so every form email is logged.
 */
class Velox_Mail {

	const DB_VERSION = '1';
	const VER_OPTION = 'velox_mail_db';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'velox_mail_log';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$t = self::table();
		dbDelta( "CREATE TABLE {$t} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created DATETIME NULL,
			recipient VARCHAR(190) NULL,
			subject VARCHAR(190) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'sent',
			error VARCHAR(190) NULL,
			PRIMARY KEY  (id),
			KEY created (created)
		) {$charset};" );
		update_option( self::VER_OPTION, self::DB_VERSION );
	}

	public static function maybe_install() {
		if ( get_option( self::VER_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function init() {
		if ( Velox_Settings::get( 'mail_smtp_enabled', false ) ) {
			add_action( 'phpmailer_init', array( __CLASS__, 'configure_smtp' ) );
		}
	}

	public static function configure_smtp( $phpmailer ) {
		$host = Velox_Settings::get( 'mail_smtp_host', '' );
		if ( '' === $host ) {
			return;
		}
		$phpmailer->isSMTP();
		$phpmailer->Host       = $host;
		$phpmailer->Port       = (int) Velox_Settings::get( 'mail_smtp_port', 587 );
		$secure                = Velox_Settings::get( 'mail_smtp_secure', 'tls' );
		$phpmailer->SMTPSecure = in_array( $secure, array( 'ssl', 'tls' ), true ) ? $secure : '';
		$user = Velox_Settings::get( 'mail_smtp_user', '' );
		$pass = Velox_Settings::get( 'mail_smtp_pass', '' );
		if ( '' !== $user ) {
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = $user;
			$phpmailer->Password = $pass;
		}
		$from      = Velox_Settings::get( 'mail_smtp_from', '' );
		$from_name = Velox_Settings::get( 'mail_smtp_from_name', '' );
		if ( $from && is_email( $from ) ) {
			$phpmailer->setFrom( $from, $from_name ? $from_name : get_bloginfo( 'name' ), false );
		}
	}

	/**
	 * Send + log. $to may be a string or array. Returns bool.
	 */
	public static function send( $to, $subject, $body, $headers = array() ) {
		if ( empty( $headers ) ) {
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		}
		$ok    = wp_mail( $to, $subject, $body, $headers );
		$recip = is_array( $to ) ? implode( ', ', $to ) : (string) $to;

		if ( Velox_Settings::get( 'mail_log', true ) ) {
			global $wpdb;
			$wpdb->insert(
				self::table(),
				array(
					'created'   => current_time( 'mysql' ),
					'recipient' => substr( $recip, 0, 190 ),
					'subject'   => substr( (string) $subject, 0, 190 ),
					'status'    => $ok ? 'sent' : 'failed',
					'error'     => $ok ? '' : 'wp_mail returned false',
				),
				array( '%s', '%s', '%s', '%s', '%s' )
			);
			if ( 1 === wp_rand( 1, 30 ) ) {
				self::prune();
			}
		}
		return $ok;
	}

	public static function prune() {
		global $wpdb;
		$t      = self::table();
		$cutoff = (int) $wpdb->get_var( "SELECT id FROM {$t} ORDER BY id DESC LIMIT 1 OFFSET 500" );
		if ( $cutoff > 0 ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE id < %d", $cutoff ) );
		}
	}

	public static function log( $limit = 100 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d',
			(int) $limit
		), ARRAY_A ) ?: array();
	}

	public static function clear_log() {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . self::table() );
		return array( 'ok' => true );
	}

	/** Fire a quick test email to confirm the SMTP setup works. */
	public static function send_test( $to ) {
		if ( ! is_email( $to ) ) {
			return array( 'ok' => false, 'message' => 'Enter a valid email address.' );
		}
		$ok = self::send(
			$to,
			'Velox SMTP test',
			'<p>This is a test email from Velox. If you can read this, your SMTP settings work.</p>'
		);
		return array( 'ok' => (bool) $ok, 'message' => $ok ? 'Test email sent.' : 'Send failed — check your SMTP settings.' );
	}
}
