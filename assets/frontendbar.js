/* Velox frontend admin quick-panel. Admins only; mounted in wp_footer. */
( function () {
	'use strict';

	var D = window.VELOX_FB;
	var mount = document.getElementById( 'velox-fb' );
	if ( ! D || ! mount ) {
		return;
	}
	var t = D.i18n || {};

	function svg( paths ) {
		return '<svg class="velox-fb-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + paths + '</svg>';
	}
	var ICON = {
		chevron: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>',
		bar:     svg( '<rect x="3" y="4" width="18" height="5" rx="1"></rect><line x1="7" y1="12" x2="7" y2="20"></line><line x1="3" y1="20" x2="21" y2="20"></line>' ),
		purge:   svg( '<polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>' ),
		cone:    svg( '<path d="M10 3 4 21h16L14 3z"></path><line x1="7" y1="14" x2="17" y2="14"></line>' ),
		edit:    svg( '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>' ),
		oxygen:  svg( '<circle cx="12" cy="12" r="9"></circle><circle cx="12" cy="12" r="3"></circle>' ),
		gear:    svg( '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>' ),
		wp:      svg( '<circle cx="12" cy="12" r="9"></circle><path d="M5 8l3.5 9 2-6 2 6L18 8"></path>' ),
		eye:     svg( '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path><circle cx="12" cy="12" r="3"></circle>' )
	};

	// ---- build DOM ----
	var wrap = document.createElement( 'div' );
	wrap.className = 'velox-fb-panel';

	function rowBtn( icon, label, opts ) {
		opts = opts || {};
		var b = document.createElement( opts.href ? 'a' : 'button' );
		b.className = 'velox-fb-row' + ( opts.danger ? ' is-danger' : '' );
		if ( opts.href ) {
			b.href = opts.href;
			if ( opts.blank ) { b.target = '_blank'; b.rel = 'noopener'; }
		} else {
			b.type = 'button';
		}
		b.innerHTML = icon + '<span class="velox-fb-label">' + label + '</span>' + ( opts.pill ? '<span class="velox-fb-pill"></span>' : '' );
		return b;
	}
	function setPill( row, on ) {
		row.classList.toggle( 'is-on', !! on );
		var p = row.querySelector( '.velox-fb-pill' );
		if ( p ) { p.textContent = on ? ( t.on || 'On' ) : ( t.off || 'Off' ); }
	}

	// header
	var head = document.createElement( 'div' );
	head.className = 'velox-fb-head';
	head.innerHTML = '<span class="velox-fb-title">' + ( t.panel || 'Admin tools' ) + '</span>';
	wrap.appendChild( head );

	// admin bar toggle
	var barRow = rowBtn( ICON.bar, t.adminBar || 'Admin bar', { pill: true } );
	setPill( barRow, D.adminBar );
	barRow.addEventListener( 'click', function () {
		var next = ! barRow.classList.contains( 'is-on' );
		post( 'fb_admin_bar', { on: next ? 1 : 0 }, barRow, function () {
			setPill( barRow, next );
			toast( ( t.adminBar || 'Admin bar' ) + ': ' + ( next ? ( t.on || 'On' ) : ( t.off || 'Off' ) ) );
			// admin bar visibility only changes on next load; reflect immediately if possible
			var ab = document.getElementById( 'wpadminbar' );
			if ( ab && ! next ) { ab.style.display = 'none'; document.documentElement.style.marginTop = '0'; }
			else if ( ! ab && next ) { location.reload(); }
		} );
	} );
	wrap.appendChild( barRow );

	// purge cache
	var purgeRow = rowBtn( ICON.purge, t.purge || 'Purge cache', {} );
	purgeRow.addEventListener( 'click', function () {
		post( 'fb_purge', {}, purgeRow, function () {
			toast( t.purged || 'Cache purged' );
		} );
	} );
	wrap.appendChild( purgeRow );

	// maintenance mode toggle
	var maintRow = rowBtn( ICON.cone, t.maint || 'Maintenance mode', { pill: true, danger: true } );
	setPill( maintRow, D.maint );
	maintRow.addEventListener( 'click', function () {
		var next = ! maintRow.classList.contains( 'is-on' );
		post( 'fb_maint', { on: next ? 1 : 0 }, maintRow, function () {
			setPill( maintRow, next );
			toast( ( t.maint || 'Maintenance mode' ) + ': ' + ( next ? ( t.on || 'On' ) : ( t.off || 'Off' ) ) );
		} );
	} );
	wrap.appendChild( maintRow );

	wrap.appendChild( sep() );

	// edit this page
	if ( D.editUrl ) {
		wrap.appendChild( rowBtn( ICON.edit, t.edit || 'Edit this page', { href: D.editUrl } ) );
	}
	// oxygen
	if ( D.oxygen && D.oxygen.active ) {
		if ( D.oxygen.editor ) {
			wrap.appendChild( rowBtn( ICON.oxygen, t.oxyEdit || 'Oxygen editor', { href: D.oxygen.editor } ) );
		}
		wrap.appendChild( rowBtn( ICON.gear, t.oxySettings || 'Oxygen settings', { href: D.oxygen.settings } ) );
	}
	// wp settings
	wrap.appendChild( rowBtn( ICON.wp, t.wpSettings || 'WordPress settings', { href: D.wpSettings } ) );

	wrap.appendChild( sep() );

	// view as visitor (guest render in a new tab)
	if ( D.guestUrl ) {
		wrap.appendChild( rowBtn( ICON.eye, t.guest || 'View as visitor', { href: D.guestUrl, blank: true } ) );
	}

	// ---- launcher ----
	var toggle = document.createElement( 'button' );
	toggle.className = 'velox-fb-toggle';
	toggle.type = 'button';
	toggle.setAttribute( 'aria-label', t.open || 'Open admin tools' );
	toggle.innerHTML = ICON.chevron;

	mount.appendChild( wrap );
	mount.appendChild( toggle );

	toggle.addEventListener( 'click', function () {
		mount.classList.toggle( 'is-open' );
	} );
	// click-away closes
	document.addEventListener( 'click', function ( e ) {
		if ( mount.classList.contains( 'is-open' ) && ! mount.contains( e.target ) ) {
			mount.classList.remove( 'is-open' );
		}
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) { mount.classList.remove( 'is-open' ); }
	} );

	// ---- helpers ----
	function sep() { var s = document.createElement( 'div' ); s.className = 'velox-fb-sep'; return s; }

	var toastEl;
	var toastTimer;
	function toast( msg ) {
		if ( ! toastEl ) {
			toastEl = document.createElement( 'div' );
			toastEl.className = 'velox-fb-toast';
			mount.appendChild( toastEl );
		}
		toastEl.textContent = msg;
		toastEl.classList.add( 'is-show' );
		clearTimeout( toastTimer );
		toastTimer = setTimeout( function () { toastEl.classList.remove( 'is-show' ); }, 2200 );
	}

	function post( doAction, data, row, ok ) {
		if ( row ) { row.classList.add( 'is-busy' ); }
		var body = new URLSearchParams();
		body.set( 'action', 'velox' );
		body.set( 'do', doAction );
		body.set( 'nonce', D.nonce );
		Object.keys( data || {} ).forEach( function ( k ) { body.set( k, data[ k ] ); } );
		fetch( D.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( j ) {
				if ( j && j.success ) { ok && ok( j.data ); }
				else { toast( t.failed || 'Failed' ); }
			} )
			.catch( function () { toast( t.failed || 'Failed' ); } )
			.then( function () { if ( row ) { row.classList.remove( 'is-busy' ); } } );
	}
} )();
