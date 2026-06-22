<?php
/**
 * Plugin Name:       Velox
 * Plugin URI:        https://github.com/cansumasearch-dev/velox
 * Description:       The speed toolkit that works *with* your stack, not against it. WebP images, smart CSS &amp; JS optimization, local fonts, media cleanup and database tools — built to sit on top of Oxygen, WP Fastest Cache and Cloudflare without stepping on them.
 * Version:           1.8.1
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

/* -------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------- */
define( 'VELOX_VERSION', '1.8.1' );
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
require_once VELOX_PATH . 'includes/class-velox-fonts.php';
require_once VELOX_PATH . 'includes/class-velox-css.php';
require_once VELOX_PATH . 'includes/class-velox-database.php';
require_once VELOX_PATH . 'includes/class-velox-ajax.php';
require_once VELOX_PATH . 'includes/class-velox-admin.php';
require_once VELOX_PATH . 'includes/class-velox-pagemeta.php';
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
	// Folder for any temporary work (e.g. comparator originals if ever needed).
	$dir = wp_upload_dir();
	$velox_dir = trailingslashit( $dir['basedir'] ) . 'velox';
	if ( ! file_exists( $velox_dir ) ) {
		wp_mkdir_p( $velox_dir );
	}
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );
