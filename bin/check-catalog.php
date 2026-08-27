<?php
/**
 * Velox catalogue regression guard.
 *
 * The utility catalogue is the single source of truth for the sidebar and the
 * dashboard grid. It did not used to be: both were hand-kept lists, and adding
 * a tool meant remembering three places. Four tools — Google Reviews, Login
 * protection, Site scan and Shop — ended up switched on and working but absent
 * from the sidebar, and nothing complained.
 *
 * Nothing complains on its own, so this does:
 *
 *   php bin/check-catalog.php
 *
 * Exit 0 = clean, 1 = a tool would be invisible somewhere. Dev aid, not shipped
 * logic.
 */

$root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}
require $root . '/includes/class-velox-utilities.php';

$cat    = Velox_Utilities::catalog();
$groups = array( 'Content & media', 'Site & visitors', 'Security', 'System' );

/* PageSpeed is spliced into Essentials only when its module is on, so it is
   deliberately group-less. Velox Builder gets its own top-level menu. */
$exempt_group = array( 'pagespeed', 'builder' );
$exempt_tile  = array( 'pagespeed', 'builder' );

$problems = array();

foreach ( $cat as $id => $t ) {
	$label    = isset( $t['label'] ) ? $t['label'] : $id;
	$has_page = ! empty( $t['page'] ) || ! empty( $t['link'] );

	// A tool you can open must be reachable from the sidebar.
	if ( $has_page && ! in_array( $id, $exempt_group, true ) ) {
		if ( empty( $t['group'] ) ) {
			$problems[] = "$id ($label) has a page but no 'group' — it would never appear in the sidebar";
		} elseif ( ! in_array( $t['group'], $groups, true ) ) {
			$problems[] = "$id ($label) is in group '{$t['group']}', which the sidebar does not render";
		}
	}

	// A tool with no page still belongs on the dashboard grid.
	if ( ! in_array( $id, $exempt_tile, true ) ) {
		if ( empty( $t['tile'] ) ) {
			$problems[] = "$id ($label) has no 'tile' — it would be missing from the dashboard grid";
		} elseif ( empty( $t['blurb'] ) ) {
			$problems[] = "$id ($label) has a 'tile' but no 'blurb'";
		}
	}

	// A group that is set but empty is a typo waiting to hide a tool.
	if ( isset( $t['group'] ) && '' === trim( (string) $t['group'] ) ) {
		$problems[] = "$id ($label) has an empty 'group'";
	}
}

echo "Velox catalogue check\n";
echo '  tools: ' . count( $cat ) . "\n";

if ( $problems ) {
	echo "\nPROBLEMS:\n";
	foreach ( $problems as $p ) {
		echo "  - $p\n";
	}
	echo "\nAdd the missing key in Velox_Utilities::catalog().\n";
	exit( 1 );
}

echo "\nOK — every tool is reachable from the sidebar and the dashboard.\n";
exit( 0 );
