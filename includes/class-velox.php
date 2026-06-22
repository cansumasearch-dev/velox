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
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function init() {
		load_plugin_textdomain( 'velox', false, dirname( VELOX_BASENAME ) . '/languages' );

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

		if ( is_admin() ) {
			$this->admin = new Velox_Admin();
		}
	}
}
