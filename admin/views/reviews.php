<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Google Reviews — top level.
 *
 * The tool lived only under Utilities, which meant it was three clicks deep and
 * absent from the dashboard. The full tool still renders here; this file just
 * gives it its own home in the navigation.
 */
$vx_reviews_tool = VELOX_PATH . 'admin/views/utilities/reviews.php';
if ( is_readable( $vx_reviews_tool ) ) {
	include $vx_reviews_tool;
} else {
	echo '<div class="velox-alert velox-alert--warn" style="margin:24px">'
		. esc_html__( 'The Google Reviews tool could not be loaded.', 'velox' ) . '</div>';
}
