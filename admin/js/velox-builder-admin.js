/**
 * Velox Builder — admin section script (foundation).
 *
 * The admin section pages (Overview, Templates, …) are mostly server-rendered.
 * This bundle wires the small bits of interactivity they need. It self-boots
 * and bails safely if its config global isn't present.
 */
( function () {
	'use strict';

	var CFG = window.VELOX_BUILDER || null;
	if ( ! CFG ) {
		return;
	}

	function boot() {
		var root = document.getElementById( 'velox-builder-admin' );
		if ( ! root ) {
			return;
		}
		// Foundation: nothing heavy yet. Hook point for list actions, filters,
		// and the class manager once those screens are built out.
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
