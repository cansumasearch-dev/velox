<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activity log — a lightweight audit trail.
 *
 * Captures the events an agency actually cares about (logins, content changes,
 * plugin/theme changes, user changes, updates) into its own table, aggregated
 * nowhere — each event is a row, but the table self-prunes to a sane ceiling.
 */
class Velox_Activity {

	const DB_VERSION = '1';
	const VER_OPTION = 'velox_activity_db';
	const MAX_ROWS   = 2000;

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'velox_activity';
	}

	/* ----------------------------------------------------------------- setup */

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$t = self::table();
		dbDelta( "CREATE TABLE {$t} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created DATETIME NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			user_name VARCHAR(190) NULL,
			action VARCHAR(50) NOT NULL,
			object VARCHAR(190) NULL,
			detail VARCHAR(190) NULL,
			ip VARCHAR(45) NULL,
			PRIMARY KEY  (id),
			KEY created (created),
			KEY action (action)
		) {$charset};" );
		update_option( self::VER_OPTION, self::DB_VERSION );
	}

	public static function maybe_install() {
		if ( get_option( self::VER_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/* ----------------------------------------------------------------- hooks */

	public static function init() {
		if ( ! Velox_Settings::get( 'util_activity', false ) ) {
			return;
		}
		add_action( 'wp_login', array( __CLASS__, 'on_login' ), 10, 2 );
		add_action( 'wp_login_failed', array( __CLASS__, 'on_login_failed' ) );
		add_action( 'wp_logout', array( __CLASS__, 'on_logout' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition' ), 10, 3 );
		add_action( 'activated_plugin', array( __CLASS__, 'on_plugin_activate' ) );
		add_action( 'deactivated_plugin', array( __CLASS__, 'on_plugin_deactivate' ) );
		add_action( 'switch_theme', array( __CLASS__, 'on_theme_switch' ) );
		add_action( 'user_register', array( __CLASS__, 'on_user_register' ) );
		add_action( 'delete_user', array( __CLASS__, 'on_user_delete' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'on_upgrade' ), 10, 2 );
	}

	/* -------------------------------------------------------------- handlers */

	public static function on_login( $login, $user = null ) {
		$id   = ( $user && isset( $user->ID ) ) ? (int) $user->ID : 0;
		$name = ( $user && isset( $user->display_name ) ) ? $user->display_name : $login;
		self::log( 'login', $login, '', array( 'id' => $id, 'name' => $name ) );
	}

	public static function on_login_failed( $username ) {
		self::log( 'login_failed', $username, '', array( 'id' => 0, 'name' => $username ) );
	}

	public static function on_logout( $user_id = 0 ) {
		$u = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
		$name = ( $u && $u->ID ) ? $u->display_name : 'unknown';
		self::log( 'logout', $name, '', array( 'id' => $u ? (int) $u->ID : 0, 'name' => $name ) );
	}

	public static function on_transition( $new, $old, $post ) {
		if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
			return;
		}
		$skip = array( 'nav_menu_item', 'revision', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_global_styles', 'custom_css', 'scheduled-action' );
		if ( in_array( $post->post_type, $skip, true ) ) {
			return;
		}
		if ( 'auto-draft' === $new || 'auto-draft' === $old ) {
			return;
		}
		if ( 'trash' === $new ) {
			$action = 'post_trash';
		} elseif ( 'publish' === $new && 'publish' !== $old ) {
			$action = 'post_publish';
		} elseif ( 'publish' === $new && 'publish' === $old ) {
			$action = 'post_update';
		} else {
			return; // draft/pending saves — too noisy to log
		}
		self::log( $action, get_the_title( $post ) ? get_the_title( $post ) : ( '#' . $post->ID ), $post->post_type );
	}

	public static function on_plugin_activate( $plugin ) {
		self::log( 'plugin_activate', self::plugin_name( $plugin ) );
	}
	public static function on_plugin_deactivate( $plugin ) {
		self::log( 'plugin_deactivate', self::plugin_name( $plugin ) );
	}
	public static function on_theme_switch( $name ) {
		self::log( 'theme_switch', $name );
	}
	public static function on_user_register( $user_id ) {
		$u = get_userdata( $user_id );
		self::log( 'user_register', $u ? $u->user_login : ( '#' . $user_id ) );
	}
	public static function on_user_delete( $user_id ) {
		$u = get_userdata( $user_id );
		self::log( 'user_delete', $u ? $u->user_login : ( '#' . $user_id ) );
	}
	public static function on_upgrade( $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['type'] ) || empty( $hook_extra['action'] ) || 'update' !== $hook_extra['action'] ) {
			return;
		}
		$what = $hook_extra['type']; // plugin | theme | core
		$items = '';
		if ( ! empty( $hook_extra['plugins'] ) ) {
			$items = implode( ', ', array_map( array( __CLASS__, 'plugin_name' ), (array) $hook_extra['plugins'] ) );
		} elseif ( ! empty( $hook_extra['themes'] ) ) {
			$items = implode( ', ', (array) $hook_extra['themes'] );
		}
		self::log( 'update', ucfirst( $what ) . ' updated', $items );
	}

	private static function plugin_name( $file ) {
		$dir = dirname( $file );
		return '.' === $dir ? $file : $dir;
	}

	/* -------------------------------------------------------------- writing */

	public static function log( $action, $object = '', $detail = '', $user = null ) {
		global $wpdb;
		if ( null === $user ) {
			$u    = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
			$uid  = ( $u && $u->ID ) ? (int) $u->ID : 0;
			$name = ( $u && $u->ID ) ? $u->display_name : 'system';
		} else {
			$uid  = (int) $user['id'];
			$name = $user['name'];
		}
		$wpdb->insert(
			self::table(),
			array(
				'created'   => current_time( 'mysql' ),
				'user_id'   => $uid,
				'user_name' => substr( (string) $name, 0, 190 ),
				'action'    => substr( (string) $action, 0, 50 ),
				'object'    => substr( (string) $object, 0, 190 ),
				'detail'    => substr( (string) $detail, 0, 190 ),
				'ip'        => self::ip(),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( 1 === wp_rand( 1, 25 ) ) {
			self::prune();
		}
	}

	private static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		return substr( preg_replace( '/[^0-9a-f:.]/i', '', $ip ), 0, 45 );
	}

	public static function prune() {
		global $wpdb;
		$t       = self::table();
		$cutoff  = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$t} ORDER BY id DESC LIMIT 1 OFFSET %d",
			self::MAX_ROWS
		) );
		if ( $cutoff > 0 ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE id < %d", $cutoff ) );
		}
	}

	/* -------------------------------------------------------------- reading */

	public static function list_events( $action = '', $limit = 200 ) {
		global $wpdb;
		$t = self::table();
		if ( $action ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$t} WHERE action = %s ORDER BY id DESC LIMIT %d",
				$action,
				(int) $limit
			), ARRAY_A ) ?: array();
		}
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$t} ORDER BY id DESC LIMIT %d",
			(int) $limit
		), ARRAY_A ) ?: array();
	}

	public static function actions_present() {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_col( "SELECT DISTINCT action FROM {$t} ORDER BY action ASC" ) ?: array();
	}

	public static function clear() {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . self::table() );
		return array( 'ok' => true );
	}

	/** Human label + colour group for an action code. */
	public static function label( $action ) {
		$map = array(
			'login'             => 'Logged in',
			'login_failed'      => 'Failed login',
			'logout'            => 'Logged out',
			'post_publish'      => 'Published',
			'post_update'       => 'Updated',
			'post_trash'        => 'Trashed',
			'plugin_activate'   => 'Plugin activated',
			'plugin_deactivate' => 'Plugin deactivated',
			'theme_switch'      => 'Theme switched',
			'user_register'     => 'User created',
			'user_delete'       => 'User deleted',
			'update'            => 'Update run',
		);
		return isset( $map[ $action ] ) ? $map[ $action ] : ucfirst( str_replace( '_', ' ', $action ) );
	}
}
