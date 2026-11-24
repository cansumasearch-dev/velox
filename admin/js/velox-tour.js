/**
 * Velox — guided tours and the built-in help.
 *
 * window.vxTour.start( 'shop' | 'builder' ) runs a tour: a dimmed page with a
 * spotlight on one part and a card that explains it. Back / Next / Skip,
 * Escape skips, arrow keys move. Skipping goes to the place where the tour can
 * be started again. ?vx_tour=shop|builder starts it on load (the first visit
 * after switching the shop or the builder on).
 *
 * window.vxHelp( el, 'shop' | 'builder' ) draws the help into el: the complete
 * guide (chapters, loaded on demand) and the questions, with one search.
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
		if ( 'skipped' === state && V.skip && V.skip[ name ] ) {
			var u = new URL( V.skip[ name ], location.href );
			// Same page (the shop's screens are #/… addresses): no reload, so point at the button here.
			if ( u.pathname + u.search === location.pathname + location.search ) {
				location.hash = u.hash;
				setTimeout( function () { waitFor( '[data-vx-tour]', 4000 ).then( function ( b ) { if ( b ) { hint(); } } ); }, 300 );
			} else {
				location.href = u.href;
			}
		}
	}
	function start( name ) {
		var steps = ( V.tours || {} )[ name ];
		if ( ! steps || ! steps.length || st ) { return; }
		st = { name:name, steps:steps, i:0 };
		build();
		go( 0 );
	}

	/* The help: the complete guide (chapters, loaded when Help opens) and the
	 * questions, under one search. */
	function fmt( s ) { return esc( s ).replace( /\*\*(.+?)\*\*/g, '<b>$1</b>' ); }
	function plain( s ) { return String( s || '' ).replace( /\*\*/g, '' ); }
	function remember( k, v ) { try { if ( undefined === v ) { return localStorage.getItem( k ); } localStorage.setItem( k, v ); } catch ( e ) {} return null; }
	function blockHTML( b ) {
		var t = b[ 0 ], v = b[ 1 ];
		if ( 'path' === t ) { return '<p class="vxg-path"><span>' + esc( T.where || 'Where' ) + '</span>' + fmt( v ) + '</p>'; }
		if ( 'steps' === t ) { return '<ol class="vxg-steps">' + ( v || [] ).map( function ( x ) { return '<li>' + fmt( x ) + '</li>'; } ).join( '' ) + '</ol>'; }
		if ( 'list' === t ) { return '<ul class="vxg-list">' + ( v || [] ).map( function ( x ) { return '<li>' + fmt( x ) + '</li>'; } ).join( '' ) + '</ul>'; }
		if ( 'tip' === t || 'note' === t ) { return '<div class="vxg-call vxg-' + t + '"><b>' + esc( T[ t ] || t ) + '</b><p>' + fmt( v ) + '</p></div>'; }
		return '<p>' + fmt( v ) + '</p>';
	}
	function blockText( b ) { return plain( Array.isArray( b[ 1 ] ) ? b[ 1 ].join( ' ' ) : b[ 1 ] ); }

	function help( el, name ) {
		var docs = ( V.docs || {} )[ name ] || [];
		if ( ! el ) { return; }
		var G = null, view = 'guide', ch = 0, q = '', key = 'vxg-' + name;
		var saved = parseInt( remember( key ), 10 ); if ( saved >= 0 ) { ch = saved; }

		el.classList.add( 'vxg' );
		el.innerHTML = '<div class="vxg-top"><div class="vxg-tabs" role="tablist">' +
			'<button type="button" role="tab" data-vxg-tab="guide">' + esc( T.guide || 'Guide' ) + '</button>' +
			'<button type="button" role="tab" data-vxg-tab="faq">' + esc( T.faq || 'Questions' ) + '</button></div>' +
			'<input type="search" class="vxh-find vxg-find" placeholder="' + esc( T.findg || T.find || 'Search' ) + '" aria-label="' + esc( T.findg || T.find || 'Search' ) + '"></div>' +
			'<div class="vxg-body" aria-live="polite"><p class="vxg-wait">' + esc( T.load || '' ) + '</p></div>';
		var body = el.querySelector( '.vxg-body' ), find = el.querySelector( '.vxg-find' );

		function faqHTML() {
			return '<div class="vxh-list">' + docs.map( function ( g ) {
				return '<section class="vxh-g"><h3>' + esc( g[ 0 ] ) + '</h3>' + g[ 1 ].map( function ( qa ) {
					return '<details class="vxh-q"><summary>' + esc( qa[ 0 ] ) + '</summary><p>' + esc( qa[ 1 ] ) + '</p></details>';
				} ).join( '' ) + '</section>';
			} ).join( '' ) + '</div>';
		}
		function chapterHTML() {
			var c = G[ ch ], n = G.length;
			var toc = '<nav class="vxg-toc" aria-label="' + esc( T.toc || 'Contents' ) + '"><div class="vxg-toc-h">' + esc( T.toc || 'Contents' ) + '</div><ol>' +
				G.map( function ( x, i ) { return '<li><button type="button" data-vxg-ch="' + i + '"' + ( i === ch ? ' class="on" aria-current="true"' : '' ) + '><span>' + ( i + 1 ) + '</span>' + esc( x.title ) + '</button></li>'; } ).join( '' ) + '</ol></nav>' +
				'<select class="vxg-toc-sel" aria-label="' + esc( T.toc || 'Contents' ) + '">' + G.map( function ( x, i ) { return '<option value="' + i + '"' + ( i === ch ? ' selected' : '' ) + '>' + ( i + 1 ) + '. ' + esc( x.title ) + '</option>'; } ).join( '' ) + '</select>';
			var secs = c.sections || [];
			var art = '<article class="vxg-art"><header class="vxg-head"><span class="vxg-kick">' + esc( ( T.of || '%1$d of %2$d' ).replace( '%1$d', ch + 1 ).replace( '%2$d', n ) ) + '</span>' +
				'<h2>' + esc( c.title ) + '</h2>' + ( c.intro ? '<p class="vxg-intro">' + fmt( c.intro ) + '</p>' : '' ) +
				( secs.length > 2 ? '<ul class="vxg-jump">' + secs.map( function ( s, j ) { return '<li><button type="button" data-vxg-go="' + ch + ':' + j + '">' + esc( s.title ) + '</button></li>'; } ).join( '' ) + '</ul>' : '' ) + '</header>' +
				secs.map( function ( s, j ) { return '<section class="vxg-sec" id="vxg-' + ch + '-' + j + '"><h3>' + esc( s.title ) + '</h3>' + ( s.blocks || [] ).map( blockHTML ).join( '' ) + '</section>'; } ).join( '' ) +
				'<footer class="vxg-pager">' +
				( ch > 0 ? '<button type="button" class="vxg-pg vxg-pg-prev" data-vxg-ch="' + ( ch - 1 ) + '"><small>' + esc( T.prev || 'Previous' ) + '</small>' + esc( G[ ch - 1 ].title ) + '</button>' : '<span></span>' ) +
				( ch < n - 1 ? '<button type="button" class="vxg-pg vxg-pg-next" data-vxg-ch="' + ( ch + 1 ) + '"><small>' + esc( T.nextc || 'Next chapter' ) + '</small>' + esc( G[ ch + 1 ].title ) + '</button>' : '' ) +
				'</footer></article>';
			return '<div class="vxg-wrap">' + toc + art + '</div>';
		}
		/* Search: every section and question that holds all the words. */
		function resultsHTML() {
			var words = q.toLowerCase().split( /\s+/ ).filter( Boolean ), out = [];
			function hit( text ) { var t = text.toLowerCase(); return words.every( function ( w ) { return t.indexOf( w ) > -1; } ); }
			function snip( text ) {
				var t = plain( text ), lo = t.toLowerCase(), at = lo.indexOf( words[ 0 ] ), from = Math.max( 0, at - 60 );
				var s = ( from > 0 ? '…' : '' ) + t.slice( from, from + 180 ) + ( from + 180 < t.length ? '…' : '' );
				var h = esc( s );
				words.forEach( function ( w ) { h = h.replace( new RegExp( '(' + w.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) + ')', 'ig' ), '<mark>$1</mark>' ); } );
				return h;
			}
			( G || [] ).forEach( function ( c, i ) {
				( c.sections || [] ).forEach( function ( s, j ) {
					var text = s.title + ' ' + ( s.blocks || [] ).map( blockText ).join( ' ' );
					if ( hit( c.title + ' ' + text ) ) { out.push( '<button type="button" class="vxg-hit" data-vxg-go="' + i + ':' + j + '"><small>' + esc( c.title ) + '</small><b>' + esc( s.title ) + '</b><span>' + snip( ( s.blocks || [] ).map( blockText ).join( ' ' ) ) + '</span></button>' ); }
				} );
			} );
			var faq = [];
			docs.forEach( function ( g ) { g[ 1 ].forEach( function ( qa ) { if ( hit( qa[ 0 ] + ' ' + qa[ 1 ] ) ) { faq.push( '<details class="vxh-q" open><summary>' + esc( qa[ 0 ] ) + '</summary><p>' + esc( qa[ 1 ] ) + '</p></details>' ); } } ); } );
			if ( ! out.length && ! faq.length ) { return '<p class="vxh-none">' + esc( T.none || '' ) + '</p>'; }
			return ( out.length ? '<div class="vxg-hits"><div class="vxg-hits-h">' + esc( T.hits || 'Results' ) + ' · ' + out.length + '</div>' + out.join( '' ) + '</div>' : '' ) +
				( faq.length ? '<div class="vxg-hits"><div class="vxg-hits-h">' + esc( T.inq || 'In the questions' ) + '</div>' + faq.join( '' ) + '</div>' : '' );
		}
		function draw() {
			var hasG = G && G.length;
			el.querySelector( '.vxg-tabs' ).hidden = ! hasG || !! q;
			[].forEach.call( el.querySelectorAll( '[data-vxg-tab]' ), function ( b ) { var on = b.getAttribute( 'data-vxg-tab' ) === view; b.classList.toggle( 'on', on ); b.setAttribute( 'aria-selected', on ? 'true' : 'false' ); } );
			if ( q.length > 1 ) { body.innerHTML = resultsHTML(); return; }
			body.innerHTML = hasG && 'guide' === view ? chapterHTML() : faqHTML();
		}
		function top( node ) {
			var y = node.getBoundingClientRect().top + window.pageYOffset - 90;
			window.scrollTo( { top:Math.max( 0, y ), behavior:REDUCED ? 'auto' : 'smooth' } );
		}
		function openChapter( i, sec ) {
			ch = Math.max( 0, Math.min( G.length - 1, i ) ); remember( key, String( ch ) );
			view = 'guide'; q = ''; find.value = ''; draw();
			var target = null != sec ? el.querySelector( '#vxg-' + ch + '-' + sec ) : el.querySelector( '.vxg-art' );
			if ( target ) {
				top( target );
				if ( null != sec ) { target.classList.add( 'vxg-flash' ); setTimeout( function () { target.classList.remove( 'vxg-flash' ); }, 1600 ); }
			}
		}

		el.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '[data-vxg-tab],[data-vxg-ch],[data-vxg-go]' ); if ( ! b ) { return; }
			if ( b.hasAttribute( 'data-vxg-tab' ) ) { view = b.getAttribute( 'data-vxg-tab' ); draw(); return; }
			if ( b.hasAttribute( 'data-vxg-ch' ) ) { openChapter( +b.getAttribute( 'data-vxg-ch' ) ); return; }
			var at = b.getAttribute( 'data-vxg-go' ).split( ':' );
			if ( +at[ 0 ] === ch && ! q ) {
				var t = el.querySelector( '#vxg-' + at[ 0 ] + '-' + at[ 1 ] ); if ( t ) { top( t ); } return;
			}
			openChapter( +at[ 0 ], +at[ 1 ] );
		} );
		el.addEventListener( 'change', function ( e ) { if ( e.target.classList.contains( 'vxg-toc-sel' ) ) { openChapter( +e.target.value ); } } );
		find.addEventListener( 'input', function () { q = find.value.trim(); draw(); } );

		var b = new URLSearchParams(); b.set( 'action', 'velox_guide' ); b.set( 'nonce', V.nonce || '' ); b.set( 'which', name );
		fetch( V.ajax, { method:'POST', credentials:'same-origin', body:b } ).then( function ( r ) { return r.json(); } ).then( function ( r ) {
			G = r && r.success && Array.isArray( r.data ) ? r.data : [];
			if ( ch >= G.length ) { ch = 0; }
			if ( ! G.length ) { view = 'faq'; }
			draw();
		} ).catch( function () { G = []; view = 'faq'; draw(); } );
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
