<?php
/**
 * Plugin Name:       Velox
 * Plugin URI:        https://github.com/cansumasearch-dev/velox
 * Description:       The speed toolkit that works *with* your stack, not against it. WebP images, smart CSS &amp; JS optimization, local fonts, media cleanup and database tools — built to sit on top of Oxygen, WP Fastest Cache and Cloudflare without stepping on them.
 * Version:           3.09.75
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
define( 'VELOX_VERSION', '3.09.75' );
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
require_once VELOX_PATH . 'includes/class-velox-settings.php';
require_once VELOX_PATH . 'includes/class-velox-image-optimizer.php';
require_once VELOX_PATH . 'includes/class-velox-media-manager.php';
require_once VELOX_PATH . 'includes/class-velox-performance.php';
require_once VELOX_PATH . 'includes/class-velox-cache.php';
require_once VELOX_PATH . 'includes/class-velox-seo.php';
require_once VELOX_PATH . 'includes/class-velox-fonts.php';
require_once VELOX_PATH . 'includes/class-velox-css.php';
require_once VELOX_PATH . 'includes/class-velox-database.php';
require_once VELOX_PATH . 'includes/class-velox-filemanager.php';
require_once VELOX_PATH . 'includes/class-velox-ajax.php';
require_once VELOX_PATH . 'includes/class-velox-admin.php';
require_once VELOX_PATH . 'includes/class-velox-builders.php';
require_once VELOX_PATH . 'includes/class-velox-redirects.php';
require_once VELOX_PATH . 'includes/class-velox-activity.php';
require_once VELOX_PATH . 'includes/class-velox-scripts.php';
require_once VELOX_PATH . 'includes/class-velox-mail.php';
require_once VELOX_PATH . 'includes/class-velox-forms.php';
require_once VELOX_PATH . 'includes/class-velox-stats.php';
require_once VELOX_PATH . 'includes/class-velox-pagespeed.php';
require_once VELOX_PATH . 'includes/class-velox-fields.php';
require_once VELOX_PATH . 'includes/class-velox-post-types.php';
require_once VELOX_PATH . 'includes/class-velox-utilities.php';
require_once VELOX_PATH . 'includes/class-velox-pagemeta.php';
require_once VELOX_PATH . 'includes/class-velox-conflicts.php';
require_once VELOX_PATH . 'includes/class-velox-snippets.php';
require_once VELOX_PATH . 'includes/class-velox-cookies.php';
require_once VELOX_PATH . 'includes/class-velox-import.php';
require_once VELOX_PATH . 'includes/class-velox-backup.php';
require_once VELOX_PATH . 'includes/class-velox-october.php';
require_once VELOX_PATH . 'includes/class-velox-updater.php';
require_once VELOX_PATH . 'includes/class-velox.php';

/* -------------------------------------------------------------------------
 * Boot
 * ---------------------------------------------------------------------- */
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
	flush_rewrite_rules();
} );
