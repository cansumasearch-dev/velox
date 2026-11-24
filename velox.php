<?php
/**
 * Plugin Name:       Velox
 * Plugin URI:        https://github.com/cansumasearch-dev/velox
 * Description:       The speed toolkit that works *with* your stack, not against it. WebP images, smart CSS &amp; JS optimization, local fonts, media cleanup and database tools — built to sit on top of Oxygen, WP Fastest Cache and Cloudflare without stepping on them.
 * Version:           4.74.0
 * Requires at least: 6.0
 * Tested up to:      7.0
 * Requires PHP:      7.4
 * Author:            Sumasearch
 * Author URI:        https://www.sumasearch.de/
 * Text Domain:       velox
 * License:           GPL-2.0-or-later
 *
 * GitHub Plugin URI: cansumasearch-dev/velox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

// Collision guard: if another copy of Velox (or a plugin using the same slug)
// is already loaded, bail before redefining constants/classes to avoid a fatal.
if ( defined( 'VELOX_VERSION' ) ) {
	return;
}

/* -------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------- */
define( 'VELOX_VERSION', '4.74.0' );
define( 'VELOX_FILE', __FILE__ );
define( 'VELOX_BASENAME', plugin_basename( __FILE__ ) );
define( 'VELOX_PATH', plugin_dir_path( __FILE__ ) );
define( 'VELOX_URL', plugin_dir_url( __FILE__ ) );
define( 'VELOX_ASSETS', VELOX_URL . 'admin/' );

// Change these two lines to point the auto-updater at YOUR GitHub repo.
define( 'VELOX_GH_USER', 'cansumasearch-dev' );
define( 'VELOX_GH_REPO', 'velox' );

/* -------------------------------------------------------------------------
 * Includes
 * ---------------------------------------------------------------------- */
require_once VELOX_PATH . 'includes/class-velox-profile.php';
Velox_Profile::mark( 'Velox: plugin file starts loading' );

/*
 * Classes load when they are first used, so a visitor's page never compiles
 * the code of the admin screens, backups, imports or the site scan. Only files
 * with plain functions, or that start themselves, load right away.
 */
spl_autoload_register( function ( $class ) {
	static $map = array(
		'Velox_A11y'               => 'includes/class-velox-a11y.php',
		'Velox_Activity'           => 'includes/class-velox-activity.php',
		'Velox_Admin'              => 'includes/class-velox-admin.php',
		'Velox_Ai'                 => 'includes/class-velox-ai.php',
		'Velox_Ajax'               => 'includes/class-velox-ajax.php',
		'Velox_Backup'             => 'includes/class-velox-backup.php',
		'Velox_Builder'            => 'includes/class-velox-builder.php',
		'Velox_Builder_Conditions' => 'includes/class-velox-builder-conditions.php',
		'Velox_Builder_Loop'       => 'includes/class-velox-builder-loop.php',
		'Velox_Builder_Oxygen'     => 'includes/class-velox-builder-oxygen.php',
		'Velox_Builder_Render'     => 'includes/class-velox-builder-render.php',
		'Velox_Builder_Templates'  => 'includes/class-velox-builder-templates.php',
		'Velox_Builder_Transfer'   => 'includes/class-velox-builder-transfer.php',
		'Velox_Builders'           => 'includes/class-velox-builders.php',
		'Velox_Cache'              => 'includes/class-velox-cache.php',
		'Velox_Conflicts'          => 'includes/class-velox-conflicts.php',
		'Velox_Database'           => 'includes/class-velox-database.php',
		'Velox_Error_Logger'       => 'includes/class-velox-errorlog.php',
		'Velox_Fields'             => 'includes/class-velox-fields.php',
		'Velox_Filemanager'        => 'includes/class-velox-filemanager.php',
		'Velox_Fonts'              => 'includes/class-velox-fonts.php',
		'Velox_Forms'              => 'includes/class-velox-forms.php',
		'Velox_Frontend_Bar'       => 'includes/class-velox-frontendbar.php',
		'Velox_Htaccess'           => 'includes/class-velox-htaccess.php',
		'Velox_Icons'              => 'includes/class-velox-icons.php',
		'Velox_Image_Optimizer'    => 'includes/class-velox-image-optimizer.php',
		'Velox_Import'             => 'includes/class-velox-import.php',
		'Velox_Local_Fonts'        => 'includes/class-velox-local-fonts.php',
		'Velox_Login_Guard'        => 'includes/class-velox-loginguard.php',
		'Velox_Mail'               => 'includes/class-velox-mail.php',
		'Velox_MediaScan'          => 'includes/class-velox-mediascan.php',
		'Velox_Media_Manager'      => 'includes/class-velox-media-manager.php',
		'Velox_October'            => 'includes/class-velox-october.php',
		'Velox_PageMeta'           => 'includes/class-velox-pagemeta.php',
		'Velox_Pagespeed'          => 'includes/class-velox-pagespeed.php',
		'Velox_Qr'                 => 'includes/class-velox-qr.php',
		'Velox_Post_Types'         => 'includes/class-velox-post-types.php',
		'Velox_Redirects'          => 'includes/class-velox-redirects.php',
		'Velox_Reviews'            => 'includes/class-velox-reviews.php',
		'Velox_Reviews_Oxygen'     => 'includes/class-velox-reviews-oxygen.php',
		'Velox_SEO_Health'         => 'includes/class-velox-seo-health.php',
		'Velox_Scripts'            => 'includes/class-velox-scripts.php',
		'Velox_Seo'                => 'includes/class-velox-seo.php',
		'Velox_Seo_Columns'        => 'includes/class-velox-seo-columns.php',
		'Velox_Shop'               => 'includes/class-velox-shop.php',
		'Velox_Shop_Account'       => 'includes/class-velox-shop-account.php',
		'Velox_Shop_Extras'        => 'includes/class-velox-shop-extras.php',
		'Velox_AI_Claude'          => 'includes/class-velox-ai-claude.php',
		'Velox_Builder_Assistant'  => 'includes/class-velox-builder-assistant.php',
		'Velox_Shop_Admin'         => 'includes/class-velox-shop-admin.php',
		'Velox_Shop_Front'         => 'includes/class-velox-shop-front.php',
		'Velox_Shop_Pay'           => 'includes/class-velox-shop-pay.php',
		'Velox_Shop_Texts'         => 'includes/class-velox-shop-texts.php',
		'Velox_Site_Scan'          => 'includes/class-velox-sitescan.php',
		'Velox_Site_Scan_Fix'      => 'includes/class-velox-sitescan-fix.php',
		'Velox_Snippets'           => 'includes/class-velox-snippets.php',
		'Velox_Stats'              => 'includes/class-velox-stats.php',
		'Velox_Theme'              => 'includes/class-velox-theme.php',
		'Velox_Updater'            => 'includes/class-velox-updater.php',
		'Velox_Utilities'          => 'includes/class-velox-utilities.php',
		'Velox_Wpress'             => 'includes/class-velox-wpress.php',
	);
	if ( isset( $map[ $class ] ) ) {
		require VELOX_PATH . $map[ $class ];
	}
} );
require_once VELOX_PATH . 'includes/class-velox-settings.php';
require_once VELOX_PATH . 'includes/class-velox-performance.php';
require_once VELOX_PATH . 'includes/class-velox-css.php';
require_once VELOX_PATH . 'includes/class-velox-builder-revisions.php';
require_once VELOX_PATH . 'includes/class-velox-builder-agents.php';
require_once VELOX_PATH . 'includes/class-velox-mcp.php';
require_once VELOX_PATH . 'includes/class-velox-help.php';
require_once VELOX_PATH . 'includes/class-velox-builder-roles.php';
require_once VELOX_PATH . 'includes/class-velox-commentguard.php';
require_once VELOX_PATH . 'includes/class-velox-cookies.php';
require_once VELOX_PATH . 'includes/class-velox.php';

/* -------------------------------------------------------------------------
 * Boot
 * ---------------------------------------------------------------------- */
Velox_Profile::mark( 'Velox: all files loaded' );
Velox_Profile::init();

function velox() {
	return Velox::instance();
}
velox();

/* -------------------------------------------------------------------------
 * Activation / Deactivation
 * ---------------------------------------------------------------------- */
register_activation_hook( __FILE__, function () {
	Velox_Settings::install_defaults();
	Velox_Redirects::install();
	Velox_Activity::install();
	Velox_Mail::install();
	Velox_Forms::install();
	Velox_Builder::install();
	// Folder for any temporary work (e.g. comparator originals if ever needed).
	$dir = wp_upload_dir();
	$velox_dir = trailingslashit( $dir['basedir'] ) . 'velox';
	if ( ! file_exists( $velox_dir ) ) {
		wp_mkdir_p( $velox_dir );
	}
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	if ( class_exists( 'Velox_Cache' ) ) {
		Velox_Cache::remove_dropin(); // stop early-serving once Velox is off
	}
	wp_clear_scheduled_hook( 'velox_cg_cleanup' );
	wp_clear_scheduled_hook( 'velox_orig_backfill' );
	wp_clear_scheduled_hook( 'velox_scan_cron' );
	wp_clear_scheduled_hook( 'velox_scan_continue' );
	flush_rewrite_rules();
} );
