<?php
/**
 * Velox uninstall.
 *
 * Runs only when the plugin is deleted from the WordPress admin (not on
 * deactivate). Removes Velox's own settings and scheduled event. It does
 * NOT touch your media, your WebP files, or anything it converted — those
 * stay exactly where they are.
 *
 * @package Velox
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'velox_settings' );
delete_transient( 'velox_latest_release' );

$timestamp = wp_next_scheduled( 'velox_weekly_cleanup' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'velox_weekly_cleanup' );
}
