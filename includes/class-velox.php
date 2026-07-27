<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core loader. Instantiates each module (respecting its master toggle) and the
 * always-on pieces (admin UI, AJAX, updater).
 */
final class Velox {

	private static $instance = null;

	public $image_optimizer;
	public $media_manager;
	public $performance;
	public $database;
	public $admin;
	public $ajax;
	public $updater;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// The locale filters must be registered BEFORE the text domain loads.
		// plugin_locale runs when load_plugin_textdomain() resolves the .mo file;
		// determine_locale runs earlier in the admin and is what WordPress 6.x
		// actually consults first. We hook both so the chosen language wins.
		$pick_locale = function ( $locale, $domain = 'velox' ) {
			if ( 'velox' !== $domain ) {
				return $locale;
			}
			// Only override inside wp-admin — the front end stays on the site locale.
			if ( ! is_admin() ) {
				return $locale;
			}
			$choice = Velox_Settings::get( 'admin_language', '' );
			return ( is_string( $choice ) && '' !== $choice ) ? $choice : $locale;
		};
		add_filter( 'plugin_locale', $pick_locale, 10, 2 );
		add_filter(
			'determine_locale',
			function ( $locale ) use ( $pick_locale ) {
				return $pick_locale( $locale, 'velox' );
			},
			10,
			1
		);

		add_action( 'plugins_loaded', array( $this, 'init' ) );
		// Load translations on init (WordPress 6.7+ warns if this happens earlier).
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );
	}

	/**
	 * Load the Velox text domain. Hooked to init so it runs after the
	 * determine_locale/plugin_locale filters above are in place.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'velox', false, dirname( VELOX_BASENAME ) . '/languages' );
	}

	public function init() {

		// Heal any settings corrupted by the pre-1.1.1 save bug (runs once).
		Velox_Settings::migrate();

		// Modules.
		if ( Velox_Settings::get( 'module_images' ) ) {
			$this->image_optimizer = new Velox_Image_Optimizer();
		}
		if ( Velox_Settings::get( 'module_media' ) ) {
			$this->media_manager = new Velox_Media_Manager();
		}
		if ( Velox_Settings::get( 'module_performance' ) ) {
			$this->performance = new Velox_Performance();
			new Velox_Fonts();
			new Velox_CSS();
		}
		if ( Velox_Settings::get( 'module_database' ) ) {
			$this->database = new Velox_Database();
		}

		// Always-on.
		$this->ajax    = new Velox_Ajax();
		$this->updater = new Velox_Updater();
		Velox_Utilities::init();
		Velox_Snippets::init();
		Velox_Stats::init();
		Velox_Pagespeed::init();
		Velox_Cookies::init();
		Velox_Backup::init();
		if ( Velox_Settings::get( 'util_october', false ) ) {
			Velox_October::maybe_install();
			Velox_October::init();
		}
		if ( is_admin() ) {
			Velox_Conflicts::init();
		}
		Velox_Redirects::maybe_install();
		Velox_Redirects::init();
		Velox_Scripts::init();
		Velox_Cache::init();
		if ( Velox_Settings::get( 'module_seo', false ) ) {
			Velox_Seo::init();
		}
		if ( Velox_Settings::get( 'util_mail' ) ) {
			Velox_Mail::maybe_install();
			Velox_Mail::init();
			Velox_Forms::maybe_install();
			Velox_Forms::init();
		}

		if ( Velox_Settings::get( 'util_fields', false ) ) {
			Velox_Fields::init();
			Velox_Post_Types::init();
		}

		// Velox_Admin always loads so its admin-bar nodes show on the front end too;
		// its heavy admin-only hooks are gated inside the class.
		$this->admin = new Velox_Admin();
		if ( is_admin() ) {
			new Velox_PageMeta();
		}
	}
}
