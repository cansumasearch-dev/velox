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
	/** @var array<string,string>|null|false  false = dict not yet loaded from disk. */
	private $vx_dict = false;
	/** @var string|null  Resolved locale for this request (null = not yet resolved). */
	private $vx_locale = null;

	/**
	 * Resolve which dictionary to use.
	 *
	 * We deliberately do NOT permanently cache a "no translation" decision: the
	 * gettext filter can fire very early in the request (before settings or the
	 * admin context are meaningful), and caching that first negative result would
	 * lock the whole page into English. Instead we resolve the chosen locale once
	 * it's safely readable, then load the dictionary file a single time.
	 *
	 * @return array<string,string>|null
	 */
	private function get_dict() {
		// Only translate inside wp-admin; the front end keeps the site language.
		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return null;
		}

		// Resolve the chosen locale (cache only a real, non-empty answer).
		if ( null === $this->vx_locale ) {
			if ( ! class_exists( 'Velox_Settings' ) ) {
				return null; // too early — try again on the next call
			}
			$choice = Velox_Settings::get( 'admin_language', '' );
			if ( ! is_string( $choice ) || '' === $choice || 'en_US' === $choice ) {
				return null; // English / Follow WordPress — no dictionary, don't cache
			}
			$available = array( 'de_DE' => 'de_DE' ); // add new languages here
			if ( ! isset( $available[ $choice ] ) ) {
				return null;
			}
			$this->vx_locale = $available[ $choice ];
		}

		// Load the dictionary file once.
		if ( false === $this->vx_dict ) {
			$this->vx_dict = null;
			$file = VELOX_PATH . 'includes/lang/' . $this->vx_locale . '.php';
			if ( is_readable( $file ) ) {
				$dict = include $file;
				if ( is_array( $dict ) ) {
					$this->vx_dict = $dict;
				}
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

	/**
	 * The active translation dictionary for use in JavaScript, or an empty array
	 * for English. Exposed to the admin script as VELOX.i18n so JS-rendered
	 * strings can be translated with the same source-string keys used in PHP.
	 *
	 * @return array<string,string>
	 */
	public static function js_dictionary() {
		$inst = self::instance();
		$dict = $inst->get_dict();
		return is_array( $dict ) ? $dict : array();
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
		Velox_Frontend_Bar::init();
		Velox_Snippets::init();
		Velox_Stats::init();
		Velox_Pagespeed::init();
		Velox_Cookies::init();
		Velox_Backup::init();
		Velox_Error_Logger::init();
		if ( Velox_Settings::get( 'util_october', false ) ) {
			Velox_October::maybe_install();
			Velox_October::init();
		}
		if ( is_admin() ) {
			Velox_Conflicts::init();
		}
		Velox_Redirects::maybe_install();
		Velox_Redirects::init();
		Velox_Reviews::init();
		Velox_Reviews_Oxygen::init();
		Velox_Scripts::init();
		Velox_Cache::init();
		if ( Velox_Settings::get( 'module_seo', false ) ) {
			Velox_Seo::init();
			Velox_Ai::init();
			Velox_Seo_Columns::init();
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

		// Velox Builder — the visual page builder module (opt-in). Boots its own
		// admin section + standalone editor; upgrades its table if the schema moved.
		if ( Velox_Builder::is_enabled() ) {
			Velox_Builder::maybe_upgrade();
			Velox_Builder::instance();
			// Front-end rendering runs on the public side too, so it lives outside
			// the is_admin() block below.
			Velox_Builder_Render::init();
		}
	}
}
