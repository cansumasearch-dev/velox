/**
 * Velox — guided tours and the built-in help.
 *
 * window.vxTour.start( 'shop' | 'builder' ) runs a tour: a dimmed page with a
 * spotlight on one part and a card that explains it. Back / Next / Skip,
 * Escape skips, arrow keys move. Skipping goes to the place where the tour can
 * be started again. ?vx_tour=shop|builder starts it on load (the first visit
 * after switching the shop or the builder on).
 *
 * window.vxHelp( el, 'shop' | 'builder' ) draws the searchable help into el.
 */
( function () {
	'use strict';
	var V = window.VXTOUR || {}, T = V.t || {}, d = document, st = null;
	var REDUCED = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function esc( s ) { return String( null == s ? '' : s ).replace( /[&<>"']/g, function ( c ) { return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[ c ]; } ); }
	function post( fields ) {
		var b = new URLSearchParams(); b.set( 'action', 'velox_tour' ); b.set( 'nonce', V.nonce || '' );
		Object.keys( fields ).forEach( function ( k ) { b.set( k, fields[ k ] ); } );
		return fetch( V.ajax, { method:'POST', credentials:'same-origin', body:b } ).catch( function () {} );
	}
	function visible( el ) { if ( ! el ) { return false; } var r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0; }
	/* Wait until a part is on screen (the shop draws its screens after a hash change). */
	function waitFor( sel, ms ) {
		return new Promise( function ( res ) {
			if ( ! sel ) { res( null ); return; }
			var t0 = Date.now();
			( function look() {
				var el = null;
				try { el = d.querySelector( sel ); } catch ( e ) {}
				if ( visible( el ) ) { res( el ); return; }
				if ( Date.now() - t0 > ms ) { res( null ); return; }
				setTimeout( look, 120 );
			}() );
		} );
	}

	function build() {
		var ov = d.createElement( 'div' ); ov.className = 'vxt-ov'; ov.innerHTML = '<div class="vxt-spot"></div>';
		var card = d.createElement( 'div' ); card.className = 'vxt-card'; card.setAttribute( 'role', 'dialog' ); card.setAttribute( 'aria-modal', 'true' ); card.setAttribute( 'aria-labelledby', 'vxt-t' );
		d.body.appendChild( ov ); d.body.appendChild( card );
		card.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '[data-vxt]' ); if ( ! b ) { return; }
			var a = b.getAttribute( 'data-vxt' );
			if ( 'next' === a ) { go( st.i + 1 ); } else if ( 'back' === a ) { go( st.i - 1 ); } else if ( 'skip' === a ) { end( 'skipped' ); }
		} );
		d.addEventListener( 'keydown', onKey, true );
		window.addEventListener( 'resize', reposition );
		window.addEventListener( 'scroll', reposition, true );
		st.ov = ov; st.card = card; st.spot = ov.firstChild;
	}
	function onKey( e ) {
		if ( ! st ) { return; }
		if ( 'Escape' === e.key ) { e.preventDefault(); end( 'skipped' ); }
		else if ( 'ArrowRight' === e.key ) { e.preventDefault(); go( st.i + 1 ); }
		else if ( 'ArrowLeft' === e.key ) { e.preventDefault(); go( st.i - 1 ); }
	}
	function go( i ) {
		if ( ! st ) { return; }
		if ( i >= st.steps.length ) { end( 'done' ); return; }
		if ( i < 0 ) { return; }
		st.i = i;
		var s = st.steps[ i ];
		if ( s.hash && location.hash !== s.hash ) { location.hash = s.hash; }
		st.card.classList.add( 'is-moving' );
		waitFor( s.sel, s.hash ? 3500 : 1500 ).then( function ( el ) {
			if ( ! st || st.i !== i ) { return; }
			st.el = el;
			if ( el ) { el.scrollIntoView( { block:'center', behavior: REDUCED ? 'auto' : 'smooth' } ); }
			draw( s, i );
			setTimeout( reposition, REDUCED ? 0 : 320 );
		} );
	}
	function draw( s, i ) {
		var n = st.steps.length, last = i === n - 1;
		st.card.innerHTML = '<div class="vxt-n">' + esc( ( T.of || '%1$d of %2$d' ).replace( '%1$d', i + 1 ).replace( '%2$d', n ) ) + '</div>' +
			'<h2 id="vxt-t">' + esc( s.title ) + '</h2><p>' + esc( s.text ) + '</p>' +
			'<div class="vxt-acts"><button type="button" class="vxt-skip" data-vxt="skip">' + esc( T.skip || 'Skip' ) + '</button><span></span>' +
			( i ? '<button type="button" class="vxt-btn" data-vxt="back">' + esc( T.back || 'Back' ) + '</button>' : '' ) +
			'<button type="button" class="vxt-btn vxt-pri" data-vxt="next">' + esc( last ? ( T.done || 'Done' ) : ( T.next || 'Next' ) ) + '</button></div>' +
			'<div class="vxt-dots" aria-hidden="true">' + st.steps.map( function ( x, j ) { return '<i' + ( j === i ? ' class="on"' : '' ) + '></i>'; } ).join( '' ) + '</div>';
		reposition();
		var nb = st.card.querySelector( '[data-vxt="next"]' ); if ( nb ) { nb.focus( { preventScroll:true } ); }
	}
	function reposition() {
		if ( ! st || ! st.card ) { return; }
		var card = st.card, spot = st.spot, el = st.el, pad = 8, vw = window.innerWidth, vh = window.innerHeight;
		card.classList.remove( 'is-moving' );
		if ( ! visible( el ) ) {
			spot.style.cssText = 'left:50%;top:50%;width:0;height:0';
			card.classList.add( 'is-center' ); card.style.left = ''; card.style.top = '';
			return;
		}
		card.classList.remove( 'is-center' );
		var r = el.getBoundingClientRect();
		spot.style.cssText = 'left:' + ( r.left - pad ) + 'px;top:' + ( r.top - pad ) + 'px;width:' + ( r.width + pad * 2 ) + 'px;height:' + ( r.height + pad * 2 ) + 'px';
		var cw = card.offsetWidth, ch = card.offsetHeight, gap = 14, x, y;
		if ( r.bottom + gap + ch < vh ) { y = r.bottom + gap + pad; x = r.left; }
		else if ( r.top - gap - ch > 0 ) { y = r.top - gap - ch - pad; x = r.left; }
		else if ( r.right + gap + cw < vw ) { x = r.right + gap + pad; y = r.top; }
		else { x = r.left - gap - cw - pad; y = r.top; }
		x = Math.max( 12, Math.min( x, vw - cw - 12 ) ); y = Math.max( 12, Math.min( y, vh - ch - 12 ) );
		card.style.left = x + 'px'; card.style.top = y + 'px';
	}
	function end( state ) {
		if ( ! st ) { return; }
		var name = st.name;
		d.removeEventListener( 'keydown', onKey, true );
		window.removeEventListener( 'resize', reposition );
		window.removeEventListener( 'scroll', reposition, true );
		st.ov.remove(); st.card.remove(); st = null;
		post( { tour:name, state:state } );
		// Skipped: show where it can be started again.
		if ( 'skipped' === state && V.skip && V.skip[ name ] ) { location.href = V.skip[ name ]; }
	}
	function start( name ) {
		var steps = ( V.tours || {} )[ name ];
		if ( ! steps || ! steps.length || st ) { return; }
		st = { name:name, steps:steps, i:0 };
		build();
		go( 0 );
	}

	/* The searchable help. */
	function help( el, name ) {
		var docs = ( V.docs || {} )[ name ] || [];
		if ( ! el || ! docs.length ) { return; }
		el.innerHTML = '<input type="search" class="vxh-find" placeholder="' + esc( T.find || 'Search' ) + '" aria-label="' + esc( T.find || 'Search' ) + '">' +
			'<div class="vxh-list">' + docs.map( function ( g ) {
				return '<section class="vxh-g"><h3>' + esc( g[ 0 ] ) + '</h3>' + g[ 1 ].map( function ( qa ) {
					return '<details class="vxh-q"><summary>' + esc( qa[ 0 ] ) + '</summary><p>' + esc( qa[ 1 ] ) + '</p></details>';
				} ).join( '' ) + '</section>';
			} ).join( '' ) + '</div><p class="vxh-none" hidden>' + esc( T.none || '' ) + '</p>';
		var f = el.querySelector( '.vxh-find' );
		f.addEventListener( 'input', function () {
			var q = f.value.trim().toLowerCase(), any = false;
			[].forEach.call( el.querySelectorAll( '.vxh-g' ), function ( g ) {
				var hit = false;
				[].forEach.call( g.querySelectorAll( '.vxh-q' ), function ( item ) {
					var on = ! q || item.textContent.toLowerCase().indexOf( q ) > -1;
					item.hidden = ! on; if ( q ) { item.open = on; } if ( on ) { hit = true; }
				} );
				g.hidden = ! hit; if ( hit ) { any = true; }
			} );
			el.querySelector( '.vxh-none' ).hidden = any;
		} );
	}

	/* A "start the tour" button anywhere: data-vx-tour="shop". */
	d.addEventListener( 'click', function ( e ) {
		var b = e.target.closest && e.target.closest( '[data-vx-tour]' );
		if ( b ) { e.preventDefault(); start( b.getAttribute( 'data-vx-tour' ) ); }
	} );
	/* After Skip: point at the button that starts it again. */
	function hint() {
		var b = d.querySelector( '[data-vx-tour]' );
		if ( ! b ) { return; }
		b.scrollIntoView( { block:'center' } );
		b.classList.add( 'vxt-pulse' );
		var tip = d.createElement( 'div' ); tip.className = 'vxt-tip'; tip.textContent = T.hint || '';
		d.body.appendChild( tip );
		var r = b.getBoundingClientRect();
		tip.style.left = Math.max( 12, Math.min( r.left, window.innerWidth - 300 ) ) + 'px'; tip.style.top = ( r.bottom + 10 ) + 'px';
		setTimeout( function () { tip.remove(); b.classList.remove( 'vxt-pulse' ); }, 6000 );
	}

	window.vxTour = { start:start, hint:hint };
	window.vxHelp = help;

	var q = new URLSearchParams( location.search ), auto = q.get( 'vx_tour' );
	var wantHint = 'tour' === q.get( 'vx_hint' ) || /[?&]hint=tour/.test( location.hash );
	function boot() {
		if ( auto && ( V.tours || {} )[ auto ] ) {
			q.delete( 'vx_tour' );
			history.replaceState( null, '', location.pathname + ( q.toString() ? '?' + q.toString() : '' ) + location.hash );
			setTimeout( function () { start( auto ); }, 700 );
		}
		if ( wantHint ) { setTimeout( function () { waitFor( '[data-vx-tour]', 4000 ).then( function ( b ) { if ( b ) { hint(); } } ); }, 400 ); }
	}
	if ( 'loading' === d.readyState ) { d.addEventListener( 'DOMContentLoaded', boot ); } else { boot(); }
}() );
