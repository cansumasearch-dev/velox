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
		// Scope Velox's admin UI language to Velox ONLY. plugin_locale receives
		// the text domain, so this filter can key off 'velox' and never touches
		// WordPress core, the site locale, or any other plugin. (Do NOT use
		// determine_locale here — it has no domain and would switch all of wp-admin.)
		add_filter(
			'plugin_locale',
			function ( $locale, $domain ) {
				if ( 'velox' !== $domain ) {
					return $locale;
				}
				$choice = Velox_Settings::get( 'admin_language', '' );
				return ( is_string( $choice ) && '' !== $choice ) ? $choice : $locale;
			},
			10,
			2
		);

		add_action( 'plugins_loaded', array( $this, 'init' ) );
		// Load translations on init (WordPress 6.7+ warns if this happens earlier).
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );
	}

	/**
	 * Load the Velox text domain.
	 *
	 * The plugin_locale filter above handles the normal path, but WordPress 6.5+
	 * loads translations "just in time" and resolves the locale via
	 * determine_locale() — which returns the SITE locale, so the chosen language
	 * can be ignored and the UI stays in English. To make the choice actually
	 * stick, when a language is picked (and we're in wp-admin) we explicitly load
	 * that specific .mo up front. That populates the translation cache before any
	 * just-in-time resolution happens, so our language wins. When no language is
	 * chosen we just do the standard load and follow WordPress.
	 */
	public function load_textdomain() {
		$dir    = dirname( VELOX_BASENAME ) . '/languages';
		$choice = Velox_Settings::get( 'admin_language', '' );

		if ( is_admin() && is_string( $choice ) && '' !== $choice ) {
			// Drop anything already loaded/registered for this domain so the
			// just-in-time loader can't win with the site locale first.
			unload_textdomain( 'velox' );
			$mofile = WP_PLUGIN_DIR . '/' . $dir . '/velox-' . $choice . '.mo';
			if ( file_exists( $mofile ) ) {
				load_textdomain( 'velox', $mofile, $choice );
				return;
			}
		}

		load_plugin_textdomain( 'velox', false, $dir );
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
