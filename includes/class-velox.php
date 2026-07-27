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
		// Apply the chosen admin-UI language via the gettext filter. This is a
		// direct string swap keyed on the English source, so it does NOT depend on
		// WordPress .mo files, the site locale, just-in-time loading, or any cache —
		// which is what made the old locale-based approach unreliable. Registered
		// early (on construct) so it's in place before any __() call runs.
		add_filter( 'gettext', array( $this, 'translate_string' ), 10, 3 );
		add_filter( 'gettext_with_context', array( $this, 'translate_string_ctx' ), 10, 4 );
	}

	/**
	 * The loaded translation dictionary for the current admin language, or null
	 * for English (source). Lazily loaded and cached for the request.
	 *
	 * @var array<string,string>|null|false  false = not yet resolved.
	 */
	private $vx_dict = false;

	/**
	 * Resolve which dictionary to use, once per request.
	 *
	 * @return array<string,string>|null
	 */
	private function get_dict() {
		if ( false !== $this->vx_dict ) {
			return $this->vx_dict;
		}
		$this->vx_dict = null;

		// Only translate inside wp-admin; the front end stays on the site language.
		if ( ! is_admin() ) {
			return null;
		}

		$choice = Velox_Settings::get( 'admin_language', '' );
		// English / Follow WordPress => source strings, no dictionary.
		if ( ! is_string( $choice ) || '' === $choice || 'en_US' === $choice ) {
			return null;
		}

		// Map a locale to a shipped dictionary file. Add new languages here.
		$available = array( 'de_DE' => 'de_DE' );
		if ( ! isset( $available[ $choice ] ) ) {
			return null;
		}

		$file = VELOX_PATH . 'includes/lang/' . $available[ $choice ] . '.php';
		if ( is_readable( $file ) ) {
			$dict = include $file;
			if ( is_array( $dict ) ) {
				$this->vx_dict = $dict;
			}
		}
		return $this->vx_dict;
	}

	/**
	 * gettext filter — swap Velox's English source strings for the chosen language.
	 *
	 * @param string $translation Current (possibly already translated) text.
	 * @param string $text        Original English source string (the msgid).
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public function translate_string( $translation, $text, $domain ) {
		if ( 'velox' !== $domain ) {
			return $translation;
		}
		$dict = $this->get_dict();
		if ( $dict && isset( $dict[ $text ] ) ) {
			return $dict[ $text ];
		}
		return $translation;
	}

	/**
	 * gettext_with_context filter — same swap for _x() calls.
	 *
	 * @param string $translation Current text.
	 * @param string $text        Source string.
	 * @param string $context     Context (unused; keys are unique enough).
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public function translate_string_ctx( $translation, $text, $context, $domain ) {
		return $this->translate_string( $translation, $text, $domain );
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
