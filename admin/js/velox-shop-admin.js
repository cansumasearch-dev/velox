/* Velox Shop — the shop editor (wp-admin → Velox Shop).
 *
 * One screen app: overview, orders, products, coupons, customers and the
 * settings, drawn from the data that Velox_Shop_Admin sends over one AJAX
 * action. No framework: each screen is a function that returns HTML, and
 * one delegated click / input / change handler drives everything.
 */
( function () {
	'use strict';

	var C = window.VSA || {};
	var D = C.i18n || {};
	function vxT( s ) { return Object.prototype.hasOwnProperty.call( D, s ) ? D[ s ] : s; }
	var S = C.settings || {};
	var root = document.getElementById( 'vsa-app' );
	if ( ! root ) { return; }

	/* ------------------------------------------------------------ helpers */

	function esc( s ) { return String( null == s ? '' : s ).replace( /[&<>"']/g, function ( c ) { return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[ c ]; } ); }
	var NF = null;
	function money( n ) {
		NF = NF || new Intl.NumberFormat( C.locale || 'de-DE', { minimumFractionDigits:2, maximumFractionDigits:2 } );
		var v = NF.format( +n || 0 );
		return 'left' === C.symbolPos ? C.currency + ' ' + v : v + ' ' + C.currency;
	}
	function num( v ) { v = String( v == null ? '' : v ).trim(); if ( '' === v ) { return null; } if ( v.indexOf( ',' ) > -1 && v.indexOf( '.' ) > -1 ) { v = v.replace( /\./g, '' ); } v = v.replace( ',', '.' ); return isNaN( +v ) ? null : +v; }
	function dec( v ) { return null == v || '' === v ? '' : String( v ).replace( '.', /^de/.test( C.locale || '' ) ? ',' : '.' ); }
	function date( ts, time ) {
		if ( ! ts ) { return ''; }
		var d = new Date( ts * 1000 );
		return d.toLocaleDateString( C.locale, { day:'2-digit', month:'short', year:'numeric' } ) + ( time ? ', ' + d.toLocaleTimeString( C.locale, { hour:'2-digit', minute:'2-digit' } ) : '' );
	}
	function ago( ts ) {
		var s = Math.round( Date.now() / 1000 - ts );
		if ( s < 3600 ) { return Math.max( 1, Math.round( s / 60 ) ) + ' ' + vxT( 'min ago' ); }
		if ( s < 86400 ) { return Math.round( s / 3600 ) + ' ' + vxT( 'h ago' ); }
		return date( ts );
	}
	function api( action, data ) {
		var b = new URLSearchParams();
		b.set( 'action', 'velox_shop_admin' ); b.set( 'nonce', C.nonce ); b.set( 'do', action ); b.set( 'data', JSON.stringify( data || {} ) );
		return fetch( C.ajax, { method:'POST', body:b, credentials:'same-origin' } )
			.then( function ( r ) { return r.json().catch( function () { throw new Error( vxT( 'The server sent an unexpected answer.' ) ); } ); } )
			.then( function ( j ) { if ( ! j || ! j.success ) { throw new Error( ( j && j.data && j.data.message ) || vxT( 'Something went wrong.' ) ); } return j.data; } );
	}
	var toastT = null;
	function toast( msg, bad, act ) {
		var t = document.getElementById( 'vsa-toast' );
		if ( ! t ) { t = document.createElement( 'div' ); t.id = 'vsa-toast'; t.setAttribute( 'role', 'status' ); document.body.appendChild( t ); }
		t.textContent = msg; t.className = 'vsa-toast is-on' + ( bad ? ' is-bad' : '' ) + ( act ? ' has-act' : '' );
		if ( act ) {
			var b = document.createElement( 'button' ); b.type = 'button'; b.className = 'vsa-toast-a'; b.textContent = act.label;
			b.addEventListener( 'click', function () { clearTimeout( toastT ); t.className = 'vsa-toast'; act.fn(); } );
			t.appendChild( b );
		}
		clearTimeout( toastT ); toastT = setTimeout( function () { t.className = 'vsa-toast'; }, bad ? 6000 : ( act ? 9000 : 3200 ) );
	}
	function fail( e ) { toast( e && e.message ? e.message : String( e ), true ); }

	var IC = {
		brush: '<path d="M14.5 3.5l6 6-8 8-6-6z"/><path d="M6.5 11.5 3 15c-1 1 0 3.5 2 5.5s4.5 3 5.5 2l3.5-3.5"/>',
		home:'<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v10h14V10"/>',
		bag:'<path d="M6 7h12l-1 12.2a1 1 0 0 1-1 .8H8a1 1 0 0 1-1-.8z"/><path d="M9 7V6a3 3 0 0 1 6 0v1"/>',
		box:'<path d="m3 7 9-4 9 4-9 4z"/><path d="M3 7v10l9 4 9-4V7M12 11v10"/>',
		tag:'<path d="M3 12V4h8l9 9-8 8z"/><circle cx="7.5" cy="8.5" r="1.3"/>',
		users:'<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M21.5 20a6.5 6.5 0 0 0-4-6"/>',
		gear:'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
		search:'<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
		plus:'<path d="M12 5v14M5 12h14"/>',
		truck:'<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
		check:'<path d="m5 12.5 4.5 4.5L19 7.5"/>',
		x:'<path d="M6 6l12 12M18 6 6 18"/>',
		card:'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/>',
		mail:'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		file:'<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5"/>',
		back:'<path d="M15 18 9 12l6-6"/>',
		ext:'<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
		more:'<circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/>',
		trash:'<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
		copy:'<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
		image:'<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>',
		alert:'<path d="M12 4 2.5 20h19z"/><path d="M12 10v4M12 17v.5"/>',
		clock:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		download:'<path d="M12 4v11M7 10l5 5 5-5M4 20h16"/>',
		undo:'<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
		chev:'<path d="m9 6 6 6-6 6"/>',
		up:'<path d="m6 15 6-6 6 6"/>',
		down:'<path d="m6 9 6 6 6-6"/>',
		globe:'<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3.5 3 14.5 0 18M12 3c-3 3.5-3 14.5 0 18"/>',
		receipt:'<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
		percent:'<path d="M19 5 5 19"/><circle cx="7" cy="7" r="2.2"/><circle cx="17" cy="17" r="2.2"/>',
		bolt:'<path d="M13 3 5 13h6l-1 8 8-10h-6z"/>',
		eye:'<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
		store:'<path d="M4 9.5 5.5 4h13L20 9.5M4 9.5V20h16V9.5M4 9.5a2.7 2.7 0 0 0 5.3 0 2.7 2.7 0 0 0 5.4 0 2.7 2.7 0 0 0 5.3 0"/><path d="M10 20v-5h4v5"/>',
		scale:'<path d="M12 4v16M5 20h14M5 8h14M7 8l-3 6a3 3 0 0 0 6 0zM17 8l-3 6a3 3 0 0 0 6 0z"/>',
		shield:'<path d="M12 3 4 6v6c0 4.5 3.3 8 8 9 4.7-1 8-4.5 8-9V6z"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
		layers:'<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
		chart:'<path d="M4 4v16h16"/><path d="m7 15 4-5 3 3 5-6"/>'
	};
	function ic( n, s ) { return '<svg class="vsa-ic" viewBox="0 0 24 24" width="' + ( s || 16 ) + '" height="' + ( s || 16 ) + '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + ( IC[ n ] || '' ) + '</svg>'; }

	var PAY = { pending:[ vxT( 'Awaiting payment' ), 'wait' ], paid:[ vxT( 'Paid' ), 'ok' ], partial:[ vxT( 'Partly refunded' ), 'warn' ], refunded:[ vxT( 'Refunded' ), 'off' ], cancelled:[ vxT( 'Cancelled' ), 'off' ], failed:[ vxT( 'Payment failed' ), 'bad' ] };
	var FUL = { unfulfilled:[ vxT( 'To ship' ), 'wait' ], shipped:[ vxT( 'Shipped' ), 'ok' ], ready:[ vxT( 'Ready for pickup' ), 'ok' ], delivered:[ vxT( 'Delivered' ), 'ok' ], cancelled:[ vxT( 'Cancelled' ), 'off' ], done:[ vxT( 'Complete' ), 'ok' ], none:[ vxT( 'Nothing to ship' ), 'off' ] };
	function chip( map, k ) { var m = map[ k ] || [ k, 'off' ]; return '<span class="vsa-chip is-' + m[ 1 ] + '">' + esc( m[ 0 ] ) + '</span>'; }
	function methodLabel( k ) { var m = ( C.methods || [] ).filter( function ( x ) { return x.key === k; } )[ 0 ]; return m ? m.title : k; }

	/* --------------------------------------------------------------- shell */

	var NAV = [
		[ '', 'home', vxT( 'Overview' ) ], [ 'revenue', 'chart', vxT( 'Revenue' ) ], [ 'orders', 'receipt', vxT( 'Orders' ) ], [ 'products', 'box', vxT( 'Products' ) ],
		[ 'coupons', 'percent', vxT( 'Coupons' ) ], [ 'customers', 'users', vxT( 'Customers' ) ], [ 'settings', 'gear', vxT( 'Settings' ) ]
	];
	var dirty = false, badge = { orders:0 };
	function shell() {
		root.innerHTML = '<div class="vsa-shell"><header class="vsa-bar"><a class="vsa-brand" href="#/">' + ic( 'store', 18 ) + '<b>' + esc( S.store_name || vxT( 'Shop' ) ) + '</b></a>' +
			'<nav class="vsa-nav" aria-label="' + esc( vxT( 'Shop' ) ) + '">' + NAV.map( function ( n ) { return '<a href="#/' + n[ 0 ] + '" data-nav="' + n[ 0 ] + '">' + ic( n[ 1 ], 15 ) + '<span>' + esc( n[ 2 ] ) + '</span>' + ( 'orders' === n[ 0 ] ? '<i class="vsa-navbadge" id="vsa-ordbadge" hidden></i>' : '' ) + '</a>'; } ).join( '' ) + '</nav>' +
			'<div class="vsa-bar-r"><a class="vsa-btn vsa-btn-ghost vsa-btn-sm" href="' + esc( pageUrl( 'shop' ) || C.home ) + '" target="_blank" rel="noopener">' + ic( 'ext', 14 ) + vxT( 'View shop' ) + '</a></div></header>' +
			'<main class="vsa-main" id="vsa-main" tabindex="-1"></main></div>';
	}
	function pageUrl( k ) { var id = +S[ 'page_' + k ]; var p = ( C.pages || [] ).filter( function ( x ) { return x.id === id; } )[ 0 ]; return p ? p.url : ''; }
	function main() { return document.getElementById( 'vsa-main' ); }
	function setNav( k ) { [].forEach.call( root.querySelectorAll( '[data-nav]' ), function ( a ) { a.classList.toggle( 'is-on', a.getAttribute( 'data-nav' ) === k ); a.setAttribute( 'aria-current', a.getAttribute( 'data-nav' ) === k ? 'page' : 'false' ); } ); }
	function setBadge( n ) { var b = document.getElementById( 'vsa-ordbadge' ); if ( b ) { b.hidden = ! n; b.textContent = n > 99 ? '99+' : n; } }
	function loading() { main().innerHTML = '<div class="vsa-loading"><span></span><span></span><span></span></div>'; }
	function head( title, sub, actions, back ) {
		return '<div class="vsa-head">' + ( back ? '<a class="vsa-back" href="' + back + '" aria-label="' + esc( vxT( 'Back' ) ) + '">' + ic( 'back', 18 ) + '</a>' : '' ) + '<div class="vsa-head-t"><h1>' + title + '</h1>' + ( sub ? '<p>' + sub + '</p>' : '' ) + '</div>' + ( actions ? '<div class="vsa-head-a">' + actions + '</div>' : '' ) + '</div>';
	}
	function card( title, body, extra ) { return '<section class="vsa-card' + ( extra && extra.cls ? ' ' + extra.cls : '' ) + '"' + ( extra && extra.id ? ' id="' + extra.id + '"' : '' ) + '>' + ( title ? '<header class="vsa-card-h"><h2>' + title + '</h2>' + ( extra && extra.right ? '<div class="vsa-card-r">' + extra.right + '</div>' : '' ) + '</header>' : '' ) + '<div class="vsa-card-b">' + body + '</div></section>'; }
	function empty( icon, title, text, action ) { return '<div class="vsa-empty">' + ic( icon, 28 ) + '<b>' + title + '</b>' + ( text ? '<p>' + text + '</p>' : '' ) + ( action || '' ) + '</div>'; }

	/* small form builders — every input carries data-k (its key) */
	// A <label> only around a real text field or select: clicking empty space
	// next to a button, a picker or a group of choices must not "press" it.
	function field( label, input, hint, cls ) {
		var tag = /<(select|textarea)|<input(?![^>]*type="(hidden|color|checkbox|radio)")/.test( input ) && ! /<button/.test( input ) && 1 === ( input.match( /<(input|select|textarea)(?![^>]*type="(hidden|color)")/g ) || [] ).length ? 'label' : 'div';
		return '<' + tag + ' class="vsa-f' + ( cls ? ' ' + cls : '' ) + '"><span class="vsa-f-l">' + label + '</span>' + input + ( hint ? '<span class="vsa-f-h">' + hint + '</span>' : '' ) + '</' + tag + '>';
	}
	/* One colour field: the swatch inside, the hex next to it, × back to the template. */
	function colorF( k, v, ph, resettable ) {
		v = v || '';
		var shown = /^#[0-9a-f]{6}$/i.test( v || ph || '' ) ? ( v || ph ) : '#ffffff';
		return '<div class="vsa-colorf' + ( v ? '' : ' is-tpl' ) + '"><input type="color" data-k="' + k + '" value="' + esc( shown ) + '">' +
			'<input type="text" class="vsa-colorf-in" data-k="' + k + '" value="' + esc( v ) + '" placeholder="' + esc( ph || '' ) + '" spellcheck="false" autocomplete="off" aria-label="' + esc( vxT( 'Hex value' ) ) + '">' +
			( resettable && v ? '<button type="button" class="vsa-colorf-x" data-act="d-reset" data-v="' + k + '" title="' + esc( vxT( 'Back to the template' ) ) + '" aria-label="' + esc( vxT( 'Back to the template' ) ) + '">' + ic( 'x', 13 ) + '</button>' : '' ) + '</div>';
	}
	function inp( k, v, o ) { o = o || {}; return '<input class="vsa-in' + ( o.cls ? ' ' + o.cls : '' ) + '" data-k="' + k + '" value="' + esc( v ) + '"' + ( o.ph ? ' placeholder="' + esc( o.ph ) + '"' : '' ) + ( o.type ? ' type="' + o.type + '"' : ' type="text"' ) + ( o.mode ? ' inputmode="' + o.mode + '"' : '' ) + ( o.attr || '' ) + '>' + ( o.suffix ? '<i class="vsa-sfx">' + esc( o.suffix ) + '</i>' : '' ); }
	function area( k, v, rows, ph ) { return '<textarea class="vsa-in" data-k="' + k + '" rows="' + ( rows || 3 ) + '"' + ( ph ? ' placeholder="' + esc( ph ) + '"' : '' ) + '>' + esc( v ) + '</textarea>'; }
	function sel( k, v, opts ) { return '<select class="vsa-in" data-k="' + k + '">' + opts.map( function ( o ) { return '<option value="' + esc( o[ 0 ] ) + '"' + ( String( v ) === String( o[ 0 ] ) ? ' selected' : '' ) + '>' + esc( o[ 1 ] ) + '</option>'; } ).join( '' ) + '</select>'; }
	function tog( k, on, label, hint ) { return '<label class="vsa-tog"><input type="checkbox" data-k="' + k + '"' + ( on ? ' checked' : '' ) + '><span class="vsa-tog-ui"></span><span class="vsa-tog-t"><b>' + label + '</b>' + ( hint ? '<small>' + hint + '</small>' : '' ) + '</span></label>'; }
	function seg( k, v, opts ) { return '<div class="vsa-seg" role="radiogroup">' + opts.map( function ( o ) { return '<button type="button" data-seg="' + k + '" data-v="' + esc( o[ 0 ] ) + '" class="' + ( String( v ) === String( o[ 0 ] ) ? 'is-on' : '' ) + '" role="radio" aria-checked="' + ( String( v ) === String( o[ 0 ] ) ) + '">' + ( o[ 2 ] ? ic( o[ 2 ], 15 ) : '' ) + esc( o[ 1 ] ) + '</button>'; } ).join( '' ) + '</div>'; }

	/* modal */
	function modal( title, body, buttons, onBtn, wide ) {
		closeModal();
		var m = document.createElement( 'div' ); m.className = 'vsa-modal'; m.id = 'vsa-modal';
		m.innerHTML = '<div class="vsa-modal-bg" data-mclose></div><div class="vsa-modal-p' + ( wide ? ' is-wide' : '' ) + '" role="dialog" aria-modal="true" aria-labelledby="vsa-mt"><header><h2 id="vsa-mt">' + title + '</h2><button type="button" class="vsa-iconbtn" data-mclose aria-label="' + esc( vxT( 'Close' ) ) + '">' + ic( 'x' ) + '</button></header><div class="vsa-modal-b">' + body + '</div>' +
			( buttons && buttons.length ? '<footer>' + buttons.map( function ( b, i ) { return '<button type="button" class="vsa-btn ' + ( b.cls || ( 0 === i ? 'vsa-btn-ghost' : 'vsa-btn-pri' ) ) + '" data-mbtn="' + esc( b.k ) + '">' + b.label + '</button>'; } ).join( '' ) + '</footer>' : '' ) + '</div>';
		document.body.appendChild( m );
		var prev = document.activeElement;
		m.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-mclose]' ) ) { closeModal(); return; }
			var b = e.target.closest( '[data-mbtn]' ); if ( b && onBtn ) { onBtn( b.getAttribute( 'data-mbtn' ), m, b ); }
		} );
		m.addEventListener( 'keydown', function ( e ) { if ( 'Escape' === e.key ) { closeModal(); } } );
		m._prev = prev;
		setTimeout( function () { var f = m.querySelector( 'input,select,textarea,button:not([data-mclose])' ); if ( f ) { f.focus(); } }, 30 );
		return m;
	}
	function closeModal() { var m = document.getElementById( 'vsa-modal' ); if ( m ) { var p = m._prev; m.remove(); if ( p && p.focus ) { p.focus(); } } }
	function confirmBox( title, text, okLabel, danger ) {
		return new Promise( function ( res ) {
			modal( title, '<p class="vsa-p">' + text + '</p>', [ { k:'no', label:vxT( 'Cancel' ) }, { k:'yes', label:okLabel, cls:danger ? 'vsa-btn-danger' : 'vsa-btn-pri' } ], function ( k ) { closeModal(); res( 'yes' === k ); } );
		} );
	}

	/* media library */
	function pickMedia( opts, done ) {
		if ( ! window.wp || ! wp.media ) { toast( vxT( 'The media library is unavailable.' ), true ); return; }
		var f = wp.media( { title:opts.title || vxT( 'Choose an image' ), multiple:!! opts.multiple, library:opts.any ? {} : { type:'image' }, button:{ text:opts.button || vxT( 'Use' ) } } );
		f.on( 'select', function () { done( f.state().get( 'selection' ).toJSON() ); } );
		f.open();
	}

	/* ------------------------------------------------------------- router */

	var route = { name:'', id:'', sub:'' };
	function parse() {
		var h = ( location.hash || '' ).replace( /^#\/?/, '' ).split( '?' )[ 0 ];
		var p = h.split( '/' );
		return { name:p[ 0 ] || '', id:p[ 1 ] || '', sub:p[ 2 ] || '', query:new URLSearchParams( ( location.hash.split( '?' )[ 1 ] ) || '' ) };
	}
	var lastHash = location.hash;
	function go() {
		if ( dirty && location.hash !== lastHash ) {
			if ( ! window.confirm( vxT( 'You have unsaved changes. Leave without saving?' ) ) ) { history.replaceState( null, '', lastHash || '#/' ); return; }
			dirty = false;
		}
		lastHash = location.hash;
		route = parse();
		setNav( route.name );
		closeModal();
		var fn = { '':overview, orders:route.id ? orderDetail : orders, products:route.id ? productEdit : products, coupons:coupons, customers:customers, revenue:revenue, settings:settings, setup:setup }[ route.name ] || overview;
		fn();
		var m = main(); if ( m ) { m.scrollTop = 0; window.scrollTo( 0, 0 ); }
	}
	window.addEventListener( 'hashchange', go );
	window.addEventListener( 'beforeunload', function ( e ) { if ( dirty ) { e.preventDefault(); e.returnValue = ''; } } );

	/* ============================================================ OVERVIEW */

	function overview() {
		loading();
		api( 'stats' ).then( function ( d ) {
			setBadge( d.toShip + d.withdrawals );
			var fresh = ! S.setup_done && ! d.products;
			var h = head( vxT( 'Overview' ), esc( vxT( 'Last 30 days' ) ), '<a class="vsa-btn vsa-btn-pri" href="#/products/new">' + ic( 'plus', 15 ) + vxT( 'New product' ) + '</a>' );
			if ( fresh ) { h += setupCard(); }
			if ( d.checks.length && ! fresh ) {
				h += '<div class="vsa-checks">' + d.checks.map( function ( c ) {
					var link = 'mail' === c.go ? '<a href="' + esc( C.mailUrl ) + '">' + vxT( 'Set up' ) + '</a>' : '<a href="' + esc( c.go ) + '">' + vxT( 'Fix' ) + '</a>';
					return '<div class="vsa-check is-' + c.sev + '">' + ic( 'err' === c.sev ? 'alert' : 'shield', 15 ) + '<span>' + esc( c.text ) + '</span>' + link + '</div>';
				} ).join( '' ) + '</div>';
			}
			h += '<div class="vsa-kpis">' +
				kpi( vxT( 'Revenue' ), money( d.revenue ), vxT( 'paid, minus refunds' ) ) +
				kpi( vxT( 'Orders' ), d.orders, vxT( 'paid' ) ) +
				kpi( vxT( 'Average order' ), money( d.avg ), '' ) +
				kpi( vxT( 'Today' ), money( d.today ), '' ) + '</div>';
			h += '<div class="vsa-grid-2">';
			h += d.line ? card( vxT( 'Revenue' ) + ' <small class="vsa-card-sub">' + esc( vxT( 'Last 30 days' ) ) + '</small>', lineChart( { labels:d.line.labels, short:d.line.short, cur:d.line.cur, cmp:d.line.cmp, cmpLabels:d.line.cmpLabels, curName:d.line.span, cmpName:d.line.cmpSpan, money:true } ), { cls:'vsa-span-2', right:'<a class="vsa-link" href="#/revenue">' + vxT( 'Open the report' ) + ' ' + ic( 'chev', 13 ) + '</a>' } ) : card( vxT( 'Revenue per day' ), chart( d.series ), { cls:'vsa-span-2' } );
			var todo = '';
			todo += todoRow( '#/orders?view=ship', 'truck', d.toShip, vxT( 'orders to ship' ), vxT( 'Paid, waiting for you' ) );
			todo += todoRow( '#/orders?view=unpaid', 'clock', d.unpaid, vxT( 'awaiting payment' ), vxT( 'Bank transfer, invoice or not yet paid' ) );
			todo += todoRow( '#/orders?view=withdrawn', 'undo', d.withdrawals, vxT( 'withdrawals to handle' ), vxT( 'Customers who withdrew from a purchase' ) );
			h += card( vxT( 'Needs you' ), todo );
			h += card( vxT( 'Low stock' ), d.low.length ? '<ul class="vsa-list">' + d.low.map( function ( p ) { return '<li><a href="#/products/' + p.id + '">' + thumb( p.thumb ) + '<span class="vsa-grow">' + esc( p.name ) + '</span><b class="' + ( p.stock ? 'vsa-warn' : 'vsa-bad' ) + '">' + p.stock + ' ' + vxT( 'left' ) + '</b></a></li>'; } ).join( '' ) + '</ul>' : '<p class="vsa-muted">' + vxT( 'Everything is well stocked.' ) + '</p>' );
			h += card( vxT( 'Best sellers' ), d.top.length ? '<ul class="vsa-list">' + d.top.map( function ( p, i ) { return '<li><a href="#/products/' + p.id + '"><i class="vsa-rank">' + ( i + 1 ) + '</i><span class="vsa-grow">' + esc( p.name ) + '</span><span class="vsa-muted">' + p.qty + '×</span><b>' + money( p.total ) + '</b></a></li>'; } ).join( '' ) + '</ul>' : '<p class="vsa-muted">' + vxT( 'No sales in the last 30 days yet.' ) + '</p>' );
			h += card( vxT( 'Latest orders' ), d.recent.length ? ordersTable( d.recent, true ) : '<p class="vsa-muted">' + vxT( 'No orders yet. They appear here the moment someone buys.' ) + '</p>', { cls:'vsa-span-2', right:'<a class="vsa-link" href="#/orders">' + vxT( 'All orders' ) + ' ' + ic( 'chev', 13 ) + '</a>' } );
			h += '</div>';
			main().innerHTML = h;
			drawCharts();
		} ).catch( fail );
	}
	function kpi( l, v, s ) { return '<div class="vsa-kpi"><span>' + esc( l ) + '</span><b>' + esc( v ) + '</b>' + ( s ? '<small>' + esc( s ) + '</small>' : '' ) + '</div>'; }
	function todoRow( href, icon, n, label, sub ) { return '<a class="vsa-todo' + ( n ? '' : ' is-zero' ) + '" href="' + href + '">' + ic( icon, 18 ) + '<span><b>' + n + ' ' + esc( label ) + '</b><small>' + esc( sub ) + '</small></span>' + ic( 'chev', 14 ) + '</a>'; }
	function thumb( u ) { return '<span class="vsa-th">' + ( u ? '<img src="' + esc( u ) + '" alt="" loading="lazy">' : ic( 'image', 16 ) ) + '</span>'; }
	function chart( series ) {
		var keys = Object.keys( series ), vals = keys.map( function ( k ) { return series[ k ]; } ), max = Math.max.apply( null, vals.concat( [ 1 ] ) );
		var w = 100 / keys.length;
		var bars = keys.map( function ( k, i ) {
			var h = Math.max( vals[ i ] > 0 ? 3 : 1, vals[ i ] / max * 100 );
			var d = new Date( k + 'T12:00:00' );
			return '<div class="vsa-bar-c" style="width:' + w + '%" title="' + esc( d.toLocaleDateString( C.locale, { day:'numeric', month:'short' } ) + ': ' + money( vals[ i ] ) ) + '"><i style="height:' + h + '%"' + ( vals[ i ] > 0 ? '' : ' class="is-zero"' ) + '></i></div>';
		} ).join( '' );
		var total = vals.reduce( function ( a, b ) { return a + b; }, 0 );
		return '<div class="vsa-chart" role="img" aria-label="' + esc( vxT( 'Revenue per day' ) + ': ' + money( total ) ) + '">' + bars + '</div><div class="vsa-chart-x"><span>' + esc( new Date( keys[ 0 ] + 'T12:00:00' ).toLocaleDateString( C.locale, { day:'numeric', month:'short' } ) ) + '</span><span>' + vxT( 'Today' ) + '</span></div>';
	}


	/* ======================================================= LINE CHART
	 * One period as a solid line, the period to compare with dashed. Smooth
	 * (monotone, so it never dips below zero), hover shows both values. */
	var CHARTS = [], chartSeq = 0;
	function moneyShort( v ) {
		var f = new Intl.NumberFormat( C.locale || 'de-DE', { maximumFractionDigits:0, notation:v >= 100000 ? 'compact' : 'standard' } ).format( v );
		return 'left' === C.symbolPos ? C.currency + f : f + ' ' + C.currency;
	}
	function lineChart( o ) {
		var id = 'vsa-lc' + ( ++chartSeq );
		CHARTS.push( { id:id, o:o } );
		var leg = '<div class="vsa-lc-leg"><span><i class="is-cur"></i>' + esc( o.curName || '' ) + '</span>' + ( o.cmp && o.cmp.length && o.cmpName ? '<span><i class="is-cmp"></i>' + esc( o.cmpName ) + '</span>' : '' ) + '</div>';
		return '<div class="vsa-lc" id="' + id + '"><div class="vsa-lc-plot"></div><div class="vsa-lc-tip" hidden></div></div>' + leg;
	}
	function drawCharts() {
		CHARTS = CHARTS.filter( function ( c ) { var el = document.getElementById( c.id ); if ( ! el ) { return false; } drawLine( el, c.o ); return true; } );
	}
	var chartT = null;
	window.addEventListener( 'resize', function () { clearTimeout( chartT ); chartT = setTimeout( drawCharts, 120 ); } );
	function niceStep( max, n ) {
		var raw = max / n, p = Math.pow( 10, Math.floor( Math.log10( raw || 1 ) ) ), f = raw / p;
		return ( f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10 ) * p;
	}
	function monoPath( pts ) {
		var n = pts.length, i;
		if ( ! n ) { return ''; }
		if ( 1 === n ) { return 'M' + pts[ 0 ][ 0 ] + ',' + pts[ 0 ][ 1 ]; }
		var m = [], t = [];
		for ( i = 0; i < n - 1; i++ ) { m.push( ( pts[ i + 1 ][ 1 ] - pts[ i ][ 1 ] ) / ( pts[ i + 1 ][ 0 ] - pts[ i ][ 0 ] ) ); }
		t[ 0 ] = m[ 0 ]; t[ n - 1 ] = m[ n - 2 ];
		for ( i = 1; i < n - 1; i++ ) { t[ i ] = m[ i - 1 ] * m[ i ] <= 0 ? 0 : ( m[ i - 1 ] + m[ i ] ) / 2; }
		for ( i = 0; i < n - 1; i++ ) {
			if ( 0 === m[ i ] ) { t[ i ] = 0; t[ i + 1 ] = 0; continue; }
			var a = t[ i ] / m[ i ], b = t[ i + 1 ] / m[ i ], s = a * a + b * b;
			if ( s > 9 ) { var tau = 3 / Math.sqrt( s ); t[ i ] = tau * a * m[ i ]; t[ i + 1 ] = tau * b * m[ i ]; }
		}
		var d = 'M' + pts[ 0 ][ 0 ].toFixed( 1 ) + ',' + pts[ 0 ][ 1 ].toFixed( 1 );
		for ( i = 0; i < n - 1; i++ ) {
			var dx = ( pts[ i + 1 ][ 0 ] - pts[ i ][ 0 ] ) / 3;
			d += 'C' + ( pts[ i ][ 0 ] + dx ).toFixed( 1 ) + ',' + ( pts[ i ][ 1 ] + t[ i ] * dx ).toFixed( 1 ) + ' ' + ( pts[ i + 1 ][ 0 ] - dx ).toFixed( 1 ) + ',' + ( pts[ i + 1 ][ 1 ] - t[ i + 1 ] * dx ).toFixed( 1 ) + ' ' + pts[ i + 1 ][ 0 ].toFixed( 1 ) + ',' + pts[ i + 1 ][ 1 ].toFixed( 1 );
		}
		return d;
	}
	function drawLine( el, o ) {
		var plot = el.querySelector( '.vsa-lc-plot' ), tip = el.querySelector( '.vsa-lc-tip' );
		var W = Math.max( 260, el.clientWidth ), H = W < 560 ? 220 : 290;
		var cur = o.cur || [], cmp = o.cmp || [], n = Math.max( cur.length, cmp.length, 1 );
		var max = Math.max.apply( null, cur.concat( cmp, [ 0 ] ) );
		var fmt = o.money ? money : function ( v ) { return o.dec ? dec( Math.round( v * 100 ) / 100 ) : String( Math.round( v ) ); };
		var ax = o.money ? moneyShort : function ( v ) { return String( Math.round( v * 100 ) / 100 ).replace( '.', /^de/.test( C.locale || '' ) ? ',' : '.' ); };
		var step = niceStep( max || ( o.money ? 100 : 4 ), 4 ), top = Math.max( step, Math.ceil( ( max || 1 ) / step ) * step );
		var ticks = []; for ( var v = 0; v <= top + 1e-9; v += step ) { ticks.push( v ); }
		var padL = Math.max( 34, Math.max.apply( null, ticks.map( function ( x ) { return ax( x ).length; } ) ) * 7 + 14 ), padR = 14, padT = 12, padB = 30;
		var iw = W - padL - padR, ih = H - padT - padB;
		var X = function ( i ) { return padL + ( n <= 1 ? iw / 2 : i * iw / ( n - 1 ) ); };
		var Y = function ( val ) { return padT + ih * ( 1 - val / top ); };
		var svg = '<svg width="' + W + '" height="' + H + '" viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="' + esc( ( o.title || '' ) + ' ' + ( o.curName || '' ) ) + '"><defs><linearGradient id="' + el.id + '-g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" class="vsa-lc-g0"/><stop offset="1" class="vsa-lc-g1"/></linearGradient></defs>';
		ticks.forEach( function ( tv ) { var y = Y( tv ).toFixed( 1 ); svg += '<line class="vsa-lc-grid" x1="' + padL + '" x2="' + ( W - padR ) + '" y1="' + y + '" y2="' + y + '"/><text class="vsa-lc-ax" x="' + ( padL - 10 ) + '" y="' + y + '" text-anchor="end" dominant-baseline="middle">' + esc( ax( tv ) ) + '</text>'; } );
		var every = Math.max( 1, Math.ceil( n / Math.max( 2, Math.floor( iw / 74 ) ) ) );
		( o.short || [] ).forEach( function ( l, i ) {
			if ( i % every ) { return; }
			var x = X( i ), anchor = 'middle';
			if ( x - padL < 24 ) { anchor = 'start'; } else if ( W - padR - x < 24 ) { anchor = 'end'; }
			svg += '<text class="vsa-lc-ax" x="' + x.toFixed( 1 ) + '" y="' + ( H - 8 ) + '" text-anchor="' + anchor + '">' + esc( l ) + '</text>';
		} );
		var pc = cur.map( function ( val, i ) { return [ X( i ), Y( val ) ]; } );
		var pp = cmp.map( function ( val, i ) { return [ X( i ), Y( val ) ]; } );
		if ( pp.length > 1 ) { svg += '<path class="vsa-lc-cmp" d="' + monoPath( pp ) + '"/>'; }
		if ( pc.length > 1 ) {
			var line = monoPath( pc );
			svg += '<path class="vsa-lc-area" fill="url(#' + el.id + '-g)" d="' + line + 'L' + pc[ pc.length - 1 ][ 0 ].toFixed( 1 ) + ',' + Y( 0 ).toFixed( 1 ) + 'L' + pc[ 0 ][ 0 ].toFixed( 1 ) + ',' + Y( 0 ).toFixed( 1 ) + 'Z"/>';
			svg += '<path class="vsa-lc-cur" d="' + line + '"/>';
		} else if ( 1 === pc.length ) {
			svg += '<circle class="vsa-lc-dot" cx="' + pc[ 0 ][ 0 ] + '" cy="' + pc[ 0 ][ 1 ] + '" r="4"/>';
		}
		svg += '<line class="vsa-lc-guide" y1="' + padT + '" y2="' + ( padT + ih ) + '" x1="-10" x2="-10"/><circle class="vsa-lc-hc is-cmp" r="4" cx="-10" cy="-10"/><circle class="vsa-lc-hc" r="4.5" cx="-10" cy="-10"/>';
		svg += '<rect class="vsa-lc-hit" x="' + padL + '" y="0" width="' + iw + '" height="' + H + '"/></svg>';
		plot.innerHTML = svg;
		var s = plot.querySelector( 'svg' ), guide = s.querySelector( '.vsa-lc-guide' ), hc = s.querySelectorAll( '.vsa-lc-hc' ), hit = s.querySelector( '.vsa-lc-hit' );
		function show( i ) {
			var x = X( i ).toFixed( 1 );
			s.classList.add( 'is-hover' );
			guide.setAttribute( 'x1', x ); guide.setAttribute( 'x2', x );
			hc[ 1 ].setAttribute( 'cx', i < cur.length ? x : -10 ); hc[ 1 ].setAttribute( 'cy', i < cur.length ? Y( cur[ i ] ) : -10 );
			hc[ 0 ].setAttribute( 'cx', i < cmp.length ? x : -10 ); hc[ 0 ].setAttribute( 'cy', i < cmp.length ? Y( cmp[ i ] ) : -10 );
			var ch = '';
			if ( i < cur.length && i < cmp.length ) {
				var a = cur[ i ], b = cmp[ i ];
				ch = b > 0 ? ( a >= b ? '+' : '−' ) + Math.abs( Math.round( ( a - b ) / b * 100 ) ) + ' %' : ( a > 0 ? vxT( 'new' ) : '' );
				ch = ch ? '<em class="' + ( a >= b ? 'is-up' : 'is-down' ) + '">' + ch + '</em>' : '';
			}
			tip.innerHTML = ( i < cur.length ? '<div><i class="is-cur"></i><span>' + esc( ( o.labels || [] )[ i ] || '' ) + '</span><b>' + esc( fmt( cur[ i ] ) ) + '</b></div>' : '' ) +
				( i < cmp.length ? '<div><i class="is-cmp"></i><span>' + esc( ( o.cmpLabels || [] )[ i ] || '' ) + '</span><b>' + esc( fmt( cmp[ i ] ) ) + '</b></div>' : '' ) + ch;
			tip.hidden = false;
			var tw = tip.offsetWidth, left = X( i ) + 14;
			if ( left + tw > W - 4 ) { left = X( i ) - tw - 14; }
			tip.style.left = Math.max( 4, left ) + 'px';
			tip.style.top = Math.max( 4, Math.min( Y( Math.max( cur[ i ] || 0, cmp[ i ] || 0 ) ) - 10, H - tip.offsetHeight - 34 ) ) + 'px';
		}
		function at( e ) { var r = s.getBoundingClientRect(); var px = e.clientX - r.left; return Math.max( 0, Math.min( n - 1, Math.round( ( px - padL ) / ( n <= 1 ? 1 : iw / ( n - 1 ) ) ) ) ); }
		hit.addEventListener( 'pointermove', function ( e ) { show( at( e ) ); } );
		hit.addEventListener( 'pointerdown', function ( e ) { show( at( e ) ); } );
		hit.addEventListener( 'pointerleave', function () { tip.hidden = true; s.classList.remove( 'is-hover' ); guide.setAttribute( 'x1', -10 ); guide.setAttribute( 'x2', -10 ); hc[ 0 ].setAttribute( 'cx', -10 ); hc[ 1 ].setAttribute( 'cx', -10 ); } );
	}

	/* ============================================================ REVENUE */

	var RV = { range:'30d', compare:'prev', group:'auto', metric:'revenue', basis:'paid', tax:'gross', method:'', cat:'', from:'', to:'' }, RD = null;
	function rvSel( k, opts ) { return '<label class="vsa-rv-sel"><select class="vsa-in vsa-in-sm" data-rvf="' + k + '">' + opts.map( function ( o ) { return '<option value="' + esc( o[ 0 ] ) + '"' + ( String( RV[ k ] ) === String( o[ 0 ] ) ? ' selected' : '' ) + '>' + esc( o[ 1 ] ) + '</option>'; } ).join( '' ) + '</select></label>'; }
	function revenue() {
		var ranges = [ [ '7d', vxT( '7 days' ) ], [ '30d', vxT( '30 days' ) ], [ '90d', vxT( '90 days' ) ], [ 'month', vxT( 'This month' ) ], [ 'lastmonth', vxT( 'Last month' ) ], [ '12m', vxT( '12 months' ) ], [ 'year', vxT( 'This year' ) ], [ 'lastyear', vxT( 'Last year' ) ], [ 'custom', vxT( 'Custom' ) ] ];
		var today = new Date().toISOString().slice( 0, 10 );
		main().innerHTML = head( vxT( 'Revenue' ), '<span id="vsa-rv-span" class="vsa-muted"></span>', '<button type="button" class="vsa-btn vsa-btn-ghost" data-act="rv-csv">' + ic( 'download', 15 ) + vxT( 'Export CSV' ) + '</button>' ) +
			'<div class="vsa-tools"><div class="vsa-tabs" role="tablist">' + ranges.map( function ( r ) { return '<button type="button" role="tab" data-rvr="' + r[ 0 ] + '" class="' + ( RV.range === r[ 0 ] ? 'is-on' : '' ) + '" aria-selected="' + ( RV.range === r[ 0 ] ) + '">' + esc( r[ 1 ] ) + '</button>'; } ).join( '' ) + '</div></div>' +
			'<div class="vsa-rv-custom"' + ( 'custom' === RV.range ? '' : ' hidden' ) + '><input type="date" class="vsa-in vsa-in-sm" id="vsa-rv-from" value="' + esc( RV.from || today.slice( 0, 8 ) + '01' ) + '" max="' + today + '"><span>–</span><input type="date" class="vsa-in vsa-in-sm" id="vsa-rv-to" value="' + esc( RV.to || today ) + '" max="' + today + '"><button type="button" class="vsa-btn vsa-btn-sm" data-act="rv-apply">' + vxT( 'Show' ) + '</button></div>' +
			'<div class="vsa-rv-filters">' +
				rvSel( 'compare', [ [ 'prev', vxT( 'Compare: previous period' ) ], [ 'year', vxT( 'Compare: same time last year' ) ], [ 'none', vxT( 'No comparison' ) ] ] ) +
				rvSel( 'group', [ [ 'auto', vxT( 'Grouping: automatic' ) ], [ 'day', vxT( 'Per day' ) ], [ 'week', vxT( 'Per week' ) ], [ 'month', vxT( 'Per month' ) ] ] ) +
				rvSel( 'basis', [ [ 'paid', vxT( 'Paid orders' ) ], [ 'placed', vxT( 'All orders, also unpaid' ) ] ] ) +
				rvSel( 'tax', [ [ 'gross', vxT( 'incl. VAT' ) ], [ 'net', vxT( 'excl. VAT' ) ] ] ) +
				rvSel( 'method', [ [ '', vxT( 'All payment methods' ) ] ].concat( ( C.methods || [] ).map( function ( m ) { return [ m.key, m.title ]; } ) ) ) +
				'<span id="vsa-rv-cats">' + rvSel( 'cat', [ [ '', vxT( 'All categories' ) ] ].concat( ( C.cats || [] ).map( function ( c ) { return [ c.id, c.name ]; } ) ) ) + '</span>' +
			'</div><div id="vsa-rv"><div class="vsa-loading"><span></span><span></span><span></span></div></div>';
		loadRevenue();
	}
	function loadRevenue() {
		var box = document.getElementById( 'vsa-rv' ); if ( ! box ) { return; }
		box.classList.add( 'is-busy' );
		api( 'revenue', RV ).then( function ( d ) {
			RD = d;
			box = document.getElementById( 'vsa-rv' ); if ( ! box ) { return; }
			box.classList.remove( 'is-busy' );
			if ( d.cats && d.cats.length && document.querySelector( '[data-rvf="cat"]' ) && document.querySelector( '[data-rvf="cat"]' ).options.length !== d.cats.length + 1 ) {
				document.getElementById( 'vsa-rv-cats' ).innerHTML = rvSel( 'cat', [ [ '', vxT( 'All categories' ) ] ].concat( d.cats.map( function ( c ) { return [ c.id, c.name ]; } ) ) );
			}
			drawRevenue();
		} ).catch( function ( e ) { var b = document.getElementById( 'vsa-rv' ); if ( b ) { b.classList.remove( 'is-busy' ); } fail( e ); } );
	}
	function pct( a, b ) {
		if ( null == b ) { return ''; }
		if ( ! b ) { return a > 0 ? '<em class="vsa-delta is-up">' + vxT( 'new' ) + '</em>' : '<em class="vsa-delta">±0 %</em>'; }
		var p = Math.round( ( a - b ) / b * 1000 ) / 10;
		return '<em class="vsa-delta ' + ( p > 0 ? 'is-up' : p < 0 ? 'is-down' : '' ) + '">' + ( p > 0 ? '▲ ' : p < 0 ? '▼ ' : '' ) + dec( Math.abs( p ) ) + ' %</em>';
	}
	var RVM = { revenue:[ 'Revenue', true ], orders:[ 'Orders', false ], avg:[ 'Average order', true ], items:[ 'Items sold', false ] };
	function drawRevenue() {
		var d = RD, box = document.getElementById( 'vsa-rv' ); if ( ! box || ! d ) { return; }
		var sp = document.getElementById( 'vsa-rv-span' ); if ( sp ) { sp.textContent = d.span + ( d.cmpSpan ? ' · ' + vxT( 'compared with' ) + ' ' + d.cmpSpan : '' ); }
		var T = d.total, P = d.cmpTotal;
		var tile = function ( k ) {
			var isM = RVM[ k ][ 1 ], v = T[ k ];
			return '<button type="button" class="vsa-kpi vsa-kpi-btn' + ( RV.metric === k ? ' is-on' : '' ) + '" data-rvm="' + k + '" aria-pressed="' + ( RV.metric === k ) + '"><span>' + esc( vxT( RVM[ k ][ 0 ] ) ) + '</span><b>' + esc( isM ? money( v ) : v ) + '</b><small>' + ( P ? pct( v, P[ k ] ) + ' ' + esc( vxT( 'vs.' ) ) + ' ' + esc( isM ? money( P[ k ] ) : P[ k ] ) : '&nbsp;' ) + '</small></button>';
		};
		var GL = { day:vxT( 'per day' ), week:vxT( 'per week' ), month:vxT( 'per month' ) };
		var m = RV.metric, isM = RVM[ m ][ 1 ];
		var h = '<div class="vsa-kpis">' + tile( 'revenue' ) + tile( 'orders' ) + tile( 'avg' ) + tile( 'items' ) + '</div>';
		h += card( esc( vxT( RVM[ m ][ 0 ] ) ) + ' <small class="vsa-card-sub">' + esc( GL[ d.group ] ) + '</small>', lineChart( { title:vxT( RVM[ m ][ 0 ] ), labels:d.labels, short:d.short, cur:d.cur[ m ], cmp:d.cmp[ m ], cmpLabels:d.cmpLabels, curName:d.span, cmpName:d.cmpSpan, money:isM } ) );
		// the numbers behind the chart
		var rows = d.labels.map( function ( l, i ) {
			var pv = i < d.cmp.revenue.length ? d.cmp.revenue[ i ] : null;
			return '<tr><td>' + esc( l ) + '</td><td class="vsa-num">' + d.cur.orders[ i ] + '</td><td class="vsa-num">' + d.cur.items[ i ] + '</td><td class="vsa-num">' + money( d.cur.avg[ i ] ) + '</td><td class="vsa-num"><b>' + money( d.cur.revenue[ i ] ) + '</b></td>' + ( P ? '<td class="vsa-num vsa-muted">' + ( null == pv ? '—' : money( pv ) ) + '</td><td class="vsa-num">' + ( null == pv ? '' : pct( d.cur.revenue[ i ], pv ) ) + '</td>' : '' ) + '</tr>';
		} ).reverse().join( '' );
		h += card( { day:vxT( 'Day by day' ), week:vxT( 'Week by week' ), month:vxT( 'Month by month' ) }[ d.group ], '<div class="vsa-rv-tbl"><table class="vsa-table"><thead><tr><th>' + vxT( 'Period' ) + '</th><th class="vsa-num">' + vxT( 'Orders' ) + '</th><th class="vsa-num">' + vxT( 'Items' ) + '</th><th class="vsa-num">' + vxT( 'Average order' ) + '</th><th class="vsa-num">' + vxT( 'Revenue' ) + '</th>' + ( P ? '<th class="vsa-num">' + vxT( 'Compared' ) + '</th><th class="vsa-num">' + vxT( 'Change' ) + '</th>' : '' ) + '</tr></thead><tbody>' + rows + '</tbody><tfoot><tr><td>' + vxT( 'Total' ) + '</td><td class="vsa-num">' + T.orders + '</td><td class="vsa-num">' + T.items + '</td><td class="vsa-num">' + money( T.avg ) + '</td><td class="vsa-num"><b>' + money( T.revenue ) + '</b></td>' + ( P ? '<td class="vsa-num vsa-muted">' + money( P.revenue ) + '</td><td class="vsa-num">' + pct( T.revenue, P.revenue ) + '</td>' : '' ) + '</tr></tfoot></table></div>' +
			( +d.refunds ? '<p class="vsa-hint">' + vxT( 'Refunds in this period' ) + ': ' + money( d.refunds ) + ' — ' + vxT( 'already taken off the revenue.' ) + '</p>' : '' ), { cls:'vsa-card-flushb' } );
		var bar = function ( list, max ) { return '<ul class="vsa-rv-bars">' + list.map( function ( r ) { return '<li><span class="vsa-grow">' + esc( r.name ) + '</span><b>' + money( r.total ) + '</b><i style="width:' + ( max ? Math.max( 2, r.total / max * 100 ) : 0 ) + '%"></i></li>'; } ).join( '' ) + '</ul>'; };
		var mx = function ( list ) { return Math.max.apply( null, list.map( function ( r ) { return +r.total; } ).concat( [ 0 ] ) ); };
		h += '<div class="vsa-grid-2">';
		h += card( vxT( 'Best sellers' ), d.top.length ? '<ul class="vsa-list">' + d.top.map( function ( p, i ) { return '<li><a href="' + ( p.id ? '#/products/' + p.id : '#/revenue' ) + '"><i class="vsa-rank">' + ( i + 1 ) + '</i><span class="vsa-grow">' + esc( p.name ) + '</span><span class="vsa-muted">' + p.qty + '×</span><b>' + money( p.total ) + '</b></a></li>'; } ).join( '' ) + '</ul>' : '<p class="vsa-muted">' + vxT( 'No sales in this period.' ) + '</p>' );
		h += '<div class="vsa-stack">' + card( vxT( 'By payment method' ), d.methods.length ? bar( d.methods, mx( d.methods ) ) : '<p class="vsa-muted">—</p>' ) + card( vxT( 'By country' ), d.countries.length ? bar( d.countries, mx( d.countries ) ) : '<p class="vsa-muted">—</p>' ) + '</div>';
		h += '</div>';
		box.innerHTML = h;
		drawCharts();
	}
	function revenueCsv() {
		var d = RD; if ( ! d ) { return; }
		var q = function ( v ) { v = String( v ); return /[";\n]/.test( v ) ? '"' + v.replace( /"/g, '""' ) + '"' : v; };
		var n = function ( v ) { return dec( v ); };
		var lines = [ [ vxT( 'Period' ), vxT( 'Orders' ), vxT( 'Items' ), vxT( 'Average order' ), vxT( 'Revenue' ) ].concat( d.cmpTotal ? [ vxT( 'Compared' ) ] : [] ).map( q ).join( ';' ) ];
		d.labels.forEach( function ( l, i ) { lines.push( [ q( l ), d.cur.orders[ i ], d.cur.items[ i ], n( d.cur.avg[ i ] ), n( d.cur.revenue[ i ] ) ].concat( d.cmpTotal ? [ i < d.cmp.revenue.length ? n( d.cmp.revenue[ i ] ) : '' ] : [] ).join( ';' ) ); } );
		lines.push( [ q( vxT( 'Total' ) ), d.total.orders, d.total.items, n( d.total.avg ), n( d.total.revenue ) ].concat( d.cmpTotal ? [ n( d.cmpTotal.revenue ) ] : [] ).join( ';' ) );
		var blob = new Blob( [ '\ufeff' + lines.join( '\r\n' ) ], { type:'text/csv;charset=utf-8' } );
		var a = document.createElement( 'a' ); a.href = URL.createObjectURL( blob ); a.download = ( vxT( 'revenue' ) + '-' + d.from + '-' + d.to + '.csv' ).toLowerCase(); document.body.appendChild( a ); a.click(); setTimeout( function () { URL.revokeObjectURL( a.href ); a.remove(); }, 500 );
	}

	/* ------------------------------------------------------- setup wizard */

	var W = null;
	function setupCard() {
		return '<section class="vsa-setup"><div class="vsa-setup-t">' + ic( 'bolt', 22 ) + '<div><h2>' + vxT( 'Set up your shop in a minute' ) + '</h2><p>' + vxT( 'Five questions, and Velox sets up taxes, shipping, payments and the shop pages for you. Everything can be changed later.' ) + '</p></div></div><a class="vsa-btn vsa-btn-pri" href="#/setup">' + vxT( 'Start' ) + ' ' + ic( 'chev', 14 ) + '</a></section>';
	}
	function setup() {
		W = W || { step:0, tax:'standard', sells:'physical', ship:'dach', pay:{ bank:1, stripe:0, paypal:0, invoice:0, cod:0 }, store_name:S.store_name || '', store_email:S.store_email || '', store_address:S.store_address || '', bank_iban:'', bank_owner:'' };
		var steps = [
			[ vxT( 'Do you charge VAT?' ), seg( 'w_tax', W.tax, [ [ 'standard', vxT( 'Yes — prices include VAT' ), 'receipt' ], [ 'small', vxT( 'No — small business (§ 19 UStG)' ), 'shield' ] ] ) +
				'<p class="vsa-hint">' + ( 'small' === W.tax ? vxT( 'Prices are shown without VAT, and invoices carry the § 19 UStG note.' ) : vxT( 'Prices are entered and shown including VAT (19 % or 7 %), as German shops must.' ) ) + '</p>' ],
			[ vxT( 'What do you sell?' ), seg( 'w_sells', W.sells, [ [ 'physical', vxT( 'Things we ship' ), 'box' ], [ 'digital', vxT( 'Downloads' ), 'download' ], [ 'both', vxT( 'Both' ), 'layers' ], [ 'service', vxT( 'Services' ), 'users' ] ] ) ],
			[ vxT( 'Where do you deliver?' ), 'digital' === W.sells || 'service' === W.sells ? '<p class="vsa-hint">' + vxT( 'Nothing to ship — you can skip this.' ) + '</p>' : seg( 'w_ship', W.ship, [ [ 'de', vxT( 'Germany' ), 'home' ], [ 'dach', vxT( 'Germany, Austria, Switzerland' ), 'truck' ], [ 'eu', vxT( 'The EU' ), 'globe' ], [ 'world', vxT( 'Worldwide' ), 'globe' ] ] ) ],
			[ vxT( 'How can customers pay?' ), '<div class="vsa-wpay">' + [ [ 'bank', vxT( 'Bank transfer' ), vxT( 'No contract with anyone needed.' ) ], [ 'stripe', vxT( 'Card, Apple Pay, Google Pay & more' ), vxT( 'Through Stripe — you add the key afterwards.' ) ], [ 'paypal', 'PayPal', vxT( 'You add the API key afterwards.' ) ], [ 'invoice', vxT( 'Invoice' ), vxT( 'For returning customers.' ) ], [ 'cod', vxT( 'Cash on delivery' ), '' ] ].map( function ( p ) {
				return '<label class="vsa-wpay-o"><input type="checkbox" data-wpay="' + p[ 0 ] + '"' + ( W.pay[ p[ 0 ] ] ? ' checked' : '' ) + '><span><b>' + esc( p[ 1 ] ) + '</b><small>' + esc( p[ 2 ] ) + '</small></span></label>';
			} ).join( '' ) + '</div>' + ( W.pay.bank ? '<div class="vsa-fr">' + field( 'IBAN', '<input class="vsa-in" data-w="bank_iban" value="' + esc( W.bank_iban ) + '" placeholder="DE89 3704 0044 0532 0130 00">' ) + field( vxT( 'Account holder' ), '<input class="vsa-in" data-w="bank_owner" value="' + esc( W.bank_owner ) + '">' ) + '</div>' : '' ) ],
			[ vxT( 'Your business details' ), '<div class="vsa-fr">' + field( vxT( 'Shop name' ), '<input class="vsa-in" data-w="store_name" value="' + esc( W.store_name ) + '">' ) + field( vxT( 'Email for customers' ), '<input class="vsa-in" type="email" data-w="store_email" value="' + esc( W.store_email ) + '">' ) + '</div>' + field( vxT( 'Address (shown on invoices)' ), '<textarea class="vsa-in" rows="3" data-w="store_address">' + esc( W.store_address ) + '</textarea>' ) ]
		];
		var s = steps[ W.step ];
		main().innerHTML = head( vxT( 'Set up your shop' ), esc( vxT( 'Step' ) + ' ' + ( W.step + 1 ) + ' / ' + steps.length ), '', '#/' ) +
			'<div class="vsa-wiz"><div class="vsa-wiz-dots">' + steps.map( function ( x, i ) { return '<i class="' + ( i < W.step ? 'is-done' : i === W.step ? 'is-on' : '' ) + '"></i>'; } ).join( '' ) + '</div>' +
			'<h2>' + s[ 0 ] + '</h2>' + s[ 1 ] +
			'<div class="vsa-wiz-f">' + ( W.step ? '<button type="button" class="vsa-btn vsa-btn-ghost" data-act="w-back">' + vxT( 'Back' ) + '</button>' : '<span></span>' ) +
			( W.step < steps.length - 1 ? '<button type="button" class="vsa-btn vsa-btn-pri" data-act="w-next">' + vxT( 'Next' ) + ' ' + ic( 'chev', 14 ) + '</button>' : '<button type="button" class="vsa-btn vsa-btn-pri" data-act="w-done">' + ic( 'check', 15 ) + vxT( 'Set up my shop' ) + '</button>' ) + '</div></div>';
	}
	var EU = [ 'DE', 'AT', 'NL', 'BE', 'LU', 'FR', 'IT', 'ES', 'PT', 'DK', 'SE', 'FI', 'PL', 'CZ', 'SK', 'HU', 'SI', 'HR', 'IE', 'GR', 'RO', 'BG', 'EE', 'LV', 'LT' ];
	function wizardFinish( btn ) {
		var ship = { de:[ 'DE' ], dach:[ 'DE', 'AT', 'CH' ], eu:EU, world:Object.keys( C.countries ) }[ W.ship ];
		var zones = [ { id:'z1', name:vxT( 'Germany' ), countries:[ 'DE' ], rates:[ { id:'r1', name:vxT( 'Standard shipping' ), cost:'4.90', free_above:'50', days:'2–4' } ] } ];
		if ( 'de' !== W.ship ) { zones.push( { id:'z2', name:vxT( 'Everywhere else' ), countries:[], rates:[ { id:'r2', name:vxT( 'International shipping' ), cost:'14.90', free_above:'', days:'4–10' } ] } ); }
		var set = {
			tax_mode:W.tax, ship_countries:'digital' === W.sells || 'service' === W.sells ? Object.keys( C.countries ) : ship, zones:zones,
			pay_bank:W.pay.bank ? '1' : '', pay_stripe:W.pay.stripe ? '1' : '', pay_paypal:W.pay.paypal ? '1' : '', pay_invoice:W.pay.invoice ? '1' : '', pay_cod:W.pay.cod ? '1' : '',
			bank_iban:W.bank_iban, bank_owner:W.bank_owner, store_name:W.store_name, store_email:W.store_email, store_address:W.store_address, mail_from_name:W.store_name
		};
		btn.disabled = true;
		api( 'settings_save', { settings:set } ).then( function ( r ) {
			S = r.settings;
			return api( 'create_pages', { setup:1 } );
		} ).then( function ( r ) {
			S = r.settings; C.pages = r.pages; W = null;
			toast( vxT( 'Your shop is set up. Now add your first product.' ) );
			location.hash = '#/products/new';
		} ).catch( function ( e ) { btn.disabled = false; fail( e ); } );
	}

	/* ============================================================== ORDERS */

	var OV = { view:'all', q:'', page:1 };
	function orders() {
		var qv = route.query && route.query.get( 'view' ); if ( qv ) { OV.view = qv; OV.page = 1; }
		var qs = route.query && route.query.get( 'q' ); if ( null !== qs && undefined !== qs && qs !== null ) { OV.q = qs; }
		var views = [ [ 'all', vxT( 'All' ) ], [ 'ship', vxT( 'To ship' ) ], [ 'unpaid', vxT( 'Unpaid' ) ], [ 'shipped', vxT( 'Shipped' ) ], [ 'cancelled', vxT( 'Cancelled' ) ], [ 'withdrawn', vxT( 'Withdrawals' ) ] ];
		main().innerHTML = head( vxT( 'Orders' ), '', '<a class="vsa-btn vsa-btn-ghost" href="' + esc( C.export ) + '">' + ic( 'download', 15 ) + vxT( 'Export CSV' ) + '</a>' ) +
			'<div class="vsa-tools"><div class="vsa-tabs" role="tablist">' + views.map( function ( v ) { return '<button type="button" role="tab" data-oview="' + v[ 0 ] + '" class="' + ( OV.view === v[ 0 ] ? 'is-on' : '' ) + '" aria-selected="' + ( OV.view === v[ 0 ] ) + '">' + esc( v[ 1 ] ) + '</button>'; } ).join( '' ) + '</div>' +
			'<label class="vsa-search">' + ic( 'search', 15 ) + '<input type="search" data-osearch value="' + esc( OV.q ) + '" placeholder="' + esc( vxT( 'Order number, name or email' ) ) + '"></label></div><div id="vsa-olist"></div>';
		loadOrders();
	}
	function loadOrders() {
		var box = document.getElementById( 'vsa-olist' ); if ( ! box ) { return; }
		box.innerHTML = '<div class="vsa-loading"><span></span><span></span><span></span></div>';
		api( 'orders', OV ).then( function ( d ) {
			if ( ! d.rows.length ) {
				box.innerHTML = empty( 'receipt', OV.q ? vxT( 'No order matches.' ) : vxT( 'No orders here.' ), 'all' === OV.view && ! OV.q ? vxT( 'Orders appear here the moment someone buys. Each one shows whether it is paid and whether it has been sent.' ) : '' );
				return;
			}
			box.innerHTML = '<div class="vsa-card vsa-card-flush">' + ordersTable( d.rows ) + '</div>' + pager( d.pages, OV.page, 'opage' );
		} ).catch( fail );
	}
	function ordersTable( rows, compact ) {
		return '<table class="vsa-table"><thead><tr><th>' + vxT( 'Order' ) + '</th><th>' + vxT( 'Customer' ) + '</th>' + ( compact ? '' : '<th>' + vxT( 'Items' ) + '</th>' ) + '<th>' + vxT( 'Payment' ) + '</th><th>' + vxT( 'Shipping' ) + '</th><th class="n">' + vxT( 'Total' ) + '</th></tr></thead><tbody>' +
			rows.map( function ( r ) {
				return '<tr data-href="#/orders/' + r.id + '" tabindex="0"><td><b class="vsa-mono">' + esc( r.number ) + '</b><small>' + esc( ago( r.created ) ) + '</small></td><td><span class="vsa-cust">' + esc( r.name || r.email ) + '</span><small>' + esc( r.email ) + '</small></td>' +
					( compact ? '' : '<td class="vsa-muted">' + r.items + '</td>' ) + '<td>' + chip( PAY, r.pay ) + '<small>' + esc( r.methodLabel || methodLabel( r.method ) ) + '</small></td><td>' + chip( FUL, r.ful ) + ( r.withdrawn ? ' <span class="vsa-chip is-bad">' + vxT( 'Withdrawn' ) + '</span>' : '' ) + '</td><td class="n"><b>' + money( r.total ) + '</b></td></tr>';
			} ).join( '' ) + '</tbody></table>';
	}
	function pager( pages, cur, key ) {
		if ( pages < 2 ) { return ''; }
		var h = '<div class="vsa-pager">';
		for ( var i = 1; i <= pages; i++ ) { h += '<button type="button" data-' + key + '="' + i + '" class="' + ( i === cur ? 'is-on' : '' ) + '">' + i + '</button>'; }
		return h + '</div>';
	}

	var O = null;
	function orderDetail() {
		loading();
		api( 'order', { id:+route.id } ).then( function ( o ) { O = o; drawOrder(); } ).catch( fail );
	}
	function drawOrder() {
		var o = O, p = o.payment, f = o.fulfil;
		var acts = '';
		if ( 'pending' === p.status ) { acts += '<button type="button" class="vsa-btn vsa-btn-pri" data-act="o-paid">' + ic( 'check', 15 ) + vxT( 'Mark as paid' ) + '</button>'; }
		if ( 'unfulfilled' === f.status && 'cancelled' !== p.status ) { acts += '<button type="button" class="vsa-btn ' + ( 'paid' === p.status || 'cod' === p.method || 'invoice' === p.method ? 'vsa-btn-pri' : 'vsa-btn-ghost' ) + '" data-act="o-ship">' + ic( 'truck', 15 ) + ( o.shipping && 'pickup' === o.shipping.type ? vxT( 'Ready for pickup' ) : vxT( 'Mark as shipped' ) ) + '</button>'; }
		if ( 'shipped' === f.status ) { acts += '<button type="button" class="vsa-btn vsa-btn-ghost" data-act="o-delivered">' + ic( 'check', 15 ) + vxT( 'Delivered' ) + '</button>'; }
		acts += '<div class="vsa-menu"><button type="button" class="vsa-btn vsa-btn-ghost vsa-btn-icon" data-act="menu" aria-label="' + esc( vxT( 'More actions' ) ) + '" aria-haspopup="true">' + ic( 'more', 16 ) + '</button><div class="vsa-menu-p" hidden>' +
			( 'pending' === p.status && /bank|invoice/.test( p.method ) ? '<button type="button" data-act="o-reminder">' + ic( 'mail', 14 ) + vxT( 'Send payment reminder' ) + '</button>' : '' ) +
			'<button type="button" data-act="o-resend">' + ic( 'mail', 14 ) + vxT( 'Send confirmation again' ) + '</button>' +
			( o.invoice && o.invoice.number ? '<a href="' + esc( o.invoiceUrl ) + '" target="_blank" rel="noopener">' + ic( 'file', 14 ) + vxT( 'Open invoice' ) + '</a>' : '<button type="button" data-act="o-invoice">' + ic( 'file', 14 ) + vxT( 'Create invoice' ) + '</button>' ) +
			'<a href="' + esc( o.view ) + '" target="_blank" rel="noopener">' + ic( 'eye', 14 ) + vxT( 'View as customer' ) + '</a>' +
			( /shipped|ready|delivered/.test( f.status ) ? '<button type="button" data-act="o-unship">' + ic( 'undo', 14 ) + vxT( 'Undo shipping' ) + '</button>' : '' ) +
			( /paid|partial/.test( p.status ) ? '<button type="button" data-act="o-refund">' + ic( 'undo', 14 ) + vxT( 'Refund' ) + '</button>' : '' ) +
			( 'cancelled' !== f.status ? '<button type="button" class="is-danger" data-act="o-cancel">' + ic( 'x', 14 ) + vxT( 'Cancel order' ) + '</button>' : '' ) +
			( 'cancelled' === f.status || 'cancelled' === p.status ? '<button type="button" data-act="o-reopen">' + ic( 'undo', 14 ) + vxT( 'Reopen order' ) + '</button>' : '' ) +
			( /pending|cancelled/.test( p.status ) ? '<button type="button" class="is-danger" data-act="o-delete">' + ic( 'trash', 14 ) + vxT( 'Delete' ) + '</button>' : '' ) +
			'</div></div>';
		var h = head( vxT( 'Order' ) + ' <span class="vsa-mono">' + esc( o.number ) + '</span>', esc( date( o.created, true ) ) + ' &nbsp; ' + chip( PAY, p.status ) + ' ' + chip( FUL, f.status ) + ( o.withdrawal ? ' <span class="vsa-chip is-bad">' + vxT( 'Withdrawn' ) + '</span>' : '' ), acts, '#/orders' );
		// next step, said plainly
		var next = '';
		if ( o.withdrawal && 'open' === o.withdrawal.status ) {
			next = '<div class="vsa-next is-bad">' + ic( 'undo', 18 ) + '<div><b>' + vxT( 'The customer withdrew from this purchase' ) + ' — ' + esc( date( o.withdrawal.at, true ) ) + '</b><p>' + ( o.withdrawal.message ? esc( o.withdrawal.message ) : vxT( 'Once the goods are back, refund the money (within 14 days) and mark the withdrawal as handled.' ) ) + '</p></div><button type="button" class="vsa-btn vsa-btn-ghost" data-act="o-wdone">' + vxT( 'Mark as handled' ) + '</button></div>';
		} else if ( 'cancelled' === f.status || 'cancelled' === p.status ) {
			next = '<div class="vsa-next">' + ic( 'x', 18 ) + '<div><b>' + vxT( 'This order is cancelled' ) + '</b><p>' + vxT( 'Its items are back in the shop. Cancelled by mistake? Reopen it and the items are reserved again.' ) + '</p></div><button type="button" class="vsa-btn vsa-btn-ghost" data-act="o-reopen">' + ic( 'undo', 15 ) + vxT( 'Reopen order' ) + '</button></div>';
		} else if ( 'pending' === p.status && /bank|invoice/.test( p.method ) ) {
			next = '<div class="vsa-next">' + ic( 'clock', 18 ) + '<div><b>' + vxT( 'Waiting for the money' ) + '</b><p>' + vxT( 'When the payment arrives in your account, click “Mark as paid”. The customer gets an email and you can ship.' ) + '</p></div></div>';
		} else if ( 'pending' === p.status ) {
			next = '<div class="vsa-next">' + ic( 'clock', 18 ) + '<div><b>' + vxT( 'Payment not completed' ) + '</b><p>' + vxT( 'The customer has not finished paying. If they don’t, the order is cancelled automatically and the stock goes back.' ) + '</p></div></div>';
		} else if ( 'paid' === p.status && 'unfulfilled' === f.status ) {
			next = '<div class="vsa-next is-ok">' + ic( 'truck', 18 ) + '<div><b>' + vxT( 'Paid — ready to ship' ) + '</b><p>' + vxT( 'Pack it, then click “Mark as shipped” and add the tracking number.' ) + '</p></div></div>';
		}
		h += next + '<div class="vsa-cols"><div class="vsa-col-main">';
		h += card( vxT( 'Items' ), '<ul class="vsa-items">' + o.items.map( function ( i ) {
			return '<li>' + thumb( i.thumb ) + '<span class="vsa-grow"><a href="#/products/' + ( i.pid || '' ) + '">' + esc( i.name ) + '</a>' + ( i.variant ? '<small>' + esc( i.variant ) + '</small>' : '' ) + ( i.sku ? '<small class="vsa-mono">' + esc( i.sku ) + '</small>' : '' ) + '</span><span class="vsa-muted">' + money( i.unit ) + ' × ' + i.qty + '</span><b>' + money( i.total ) + '</b></li>';
		} ).join( '' ) + '</ul>' + orderTotals( o ) );
		h += card( vxT( 'Timeline' ), '<div class="vsa-compose"><textarea class="vsa-in" rows="2" id="vsa-note" placeholder="' + esc( vxT( 'Add a note…' ) ) + '"></textarea><div class="vsa-compose-a"><label class="vsa-check"><input type="checkbox" id="vsa-note-c"><span>' + vxT( 'Also email it to the customer' ) + '</span></label><button type="button" class="vsa-btn vsa-btn-sm" data-act="o-note">' + vxT( 'Add' ) + '</button></div></div>' +
			'<ol class="vsa-tl">' + ( o.notes || [] ).slice().reverse().map( function ( n ) { return '<li class="' + ( n.c ? 'is-c' : '' ) + '"><span>' + esc( n.text ) + '</span><small>' + esc( date( n.t, true ) ) + ( n.by ? ' · ' + esc( n.by ) : '' ) + ( n.c ? ' · ' + vxT( 'sent to the customer' ) : '' ) + '</small></li>'; } ).join( '' ) + '</ol>' );
		h += '</div><aside class="vsa-col-side">';
		var c = o.customer;
		h += card( vxT( 'Customer' ), '<p class="vsa-cust-n">' + esc( ( c.first + ' ' + c.last ).trim() ) + ( c.company ? '<small>' + esc( c.company ) + '</small>' : '' ) + '</p>' +
			'<p><a href="mailto:' + esc( c.email ) + '">' + esc( c.email ) + '</a>' + ( c.phone ? '<br><a href="tel:' + esc( c.phone ) + '">' + esc( c.phone ) + '</a>' : '' ) + '</p>' +
			( o.others && o.others.length ? '<p class="vsa-muted">' + o.others.length + ' ' + vxT( 'more orders' ) + ' · <a href="#/orders?q=' + encodeURIComponent( c.email ) + '">' + vxT( 'show' ) + '</a></p>' : '<p class="vsa-muted">' + vxT( 'First order' ) + '</p>' ), { right:'<button type="button" class="vsa-link" data-act="o-addr">' + vxT( 'Edit' ) + '</button>' } );
		h += card( vxT( 'Addresses' ), '<div class="vsa-addr"><h3>' + vxT( 'Billing' ) + '</h3><p>' + o.address_html + '</p></div>' + ( o.shipping && 'pickup' === o.shipping.type ? '<div class="vsa-addr"><h3>' + vxT( 'Delivery' ) + '</h3><p>' + esc( o.shipping.name ) + '</p></div>' : ( o.ship_html ? '<div class="vsa-addr"><h3>' + vxT( 'Delivery' ) + '</h3><p>' + o.ship_html + '</p></div>' : ( o.shipping && 'none' !== o.shipping.id ? '<p class="vsa-muted">' + vxT( 'Delivery to the billing address.' ) + '</p>' : '' ) ) ) +
			( o.note ? '<div class="vsa-addr"><h3>' + vxT( 'Note from the customer' ) + '</h3><p>' + esc( o.note ) + '</p></div>' : '' ) );
		h += card( vxT( 'Payment' ), '<p><b>' + esc( o.methodLabel ) + '</b> ' + chip( PAY, p.status ) + '</p>' + ( p.ref ? '<p class="vsa-muted vsa-mono vsa-small">' + esc( p.ref ) + '</p>' : '' ) + ( p.paid_at ? '<p class="vsa-muted">' + vxT( 'Paid on' ) + ' ' + esc( date( p.paid_at, true ) ) + '</p>' : '' ) +
			( +p.refunded ? '<p class="vsa-muted">' + vxT( 'Refunded' ) + ': ' + money( p.refunded ) + '</p>' : '' ) + ( o.invoice && o.invoice.number ? '<p>' + vxT( 'Invoice' ) + ' <a href="' + esc( o.invoiceUrl ) + '" target="_blank" rel="noopener" class="vsa-mono">' + esc( o.invoice.number ) + '</a></p>' : '' ) );
		if ( /shipped|ready|delivered/.test( f.status ) ) {
			h += card( vxT( 'Shipping' ), '<p>' + chip( FUL, f.status ) + ' ' + esc( date( f.at ) ) + '</p>' + ( f.tracking ? '<p class="vsa-mono">' + esc( f.carrier + ' ' + f.tracking ) + '</p>' : '' ) );
		}
		if ( o.source ) { h += card( vxT( 'Came from' ), '<p class="vsa-muted vsa-small">' + esc( o.source ) + '</p>' ); }
		h += '</aside></div>';
		main().innerHTML = h;
	}
	function orderTotals( o ) {
		var r = function ( l, v, cls ) { return '<div class="vsa-tr' + ( cls ? ' ' + cls : '' ) + '"><span>' + l + '</span><span>' + v + '</span></div>'; };
		var h = '<div class="vsa-totals">' + r( vxT( 'Subtotal' ), money( o.subtotal ) );
		if ( +o.discount ) { h += r( vxT( 'Discount' ) + ( o.coupon ? ' <code>' + esc( o.coupon ) + '</code>' : '' ), '− ' + money( o.discount ) ); }
		if ( o.shipping && 'none' !== o.shipping.id ) { h += r( esc( o.shipping.name ), +o.shipping.cost ? money( o.shipping.cost ) : vxT( 'Free' ) ); }
		if ( +o.fee ) { h += r( vxT( 'Cash on delivery fee' ), money( o.fee ) ); }
		h += r( vxT( 'Total' ), money( o.total ), 'is-total' );
		Object.keys( o.tax_lines || {} ).forEach( function ( k ) { h += r( vxT( 'incl. VAT' ) + ' ' + dec( k ) + ' %', money( o.tax_lines[ k ] ), 'is-sub' ); } );
		if ( o.small ) { h += r( vxT( 'No VAT (§ 19 UStG)' ), '', 'is-sub' ); }
		if ( +o.payment.refunded ) { h += r( vxT( 'Refunded' ), '− ' + money( o.payment.refunded ), 'is-sub' ); }
		return h + '</div>';
	}
	function orderAct( act, extra ) {
		return api( 'order_action', Object.assign( { id:O.id, act:act }, extra || {} ) ).then( function ( r ) {
			if ( r.deleted ) { toast( vxT( 'Deleted.' ) ); location.hash = '#/orders'; return; }
			O = r.order; drawOrder(); if ( r.msg ) { toast( r.msg ); }
		} ).catch( fail );
	}
	function shipDialog() {
		var pickup = O.shipping && 'pickup' === O.shipping.type;
		if ( pickup ) { orderAct( 'ship' ); return; }
		modal( vxT( 'Mark as shipped' ), '<p class="vsa-p">' + vxT( 'The customer gets an email with the tracking link.' ) + '</p><div class="vsa-fr">' +
			field( vxT( 'Carrier' ), '<input class="vsa-in" id="vsa-carrier" list="vsa-carriers" value="DHL"><datalist id="vsa-carriers"><option value="DHL"><option value="DPD"><option value="Hermes"><option value="GLS"><option value="UPS"><option value="Deutsche Post"></datalist>' ) +
			field( vxT( 'Tracking number' ), '<input class="vsa-in vsa-mono" id="vsa-track" placeholder="' + esc( vxT( 'optional' ) ) + '">' ) + '</div>',
			[ { k:'no', label:vxT( 'Cancel' ) }, { k:'yes', label:ic( 'truck', 15 ) + vxT( 'Mark as shipped' ) } ],
			function ( k, m ) { if ( 'yes' !== k ) { closeModal(); return; } var c = m.querySelector( '#vsa-carrier' ).value, t = m.querySelector( '#vsa-track' ).value; closeModal(); orderAct( 'ship', { carrier:c, tracking:t } ); } );
	}
	function refundDialog() {
		var left = +O.total - +O.payment.refunded;
		modal( vxT( 'Refund' ), '<div class="vsa-fr">' + field( vxT( 'Amount' ), '<input class="vsa-in" id="vsa-ramt" inputmode="decimal" value="' + esc( dec( left.toFixed( 2 ) ) ) + '">', vxT( 'At most' ) + ' ' + money( left ) ) + '</div>' +
			( O.canRefundApi ? tog( 'api', true, vxT( 'Send the money back through' ) + ' ' + esc( O.methodLabel ), vxT( 'Otherwise the refund is only noted here and you transfer it yourself.' ) ) : '<p class="vsa-hint">' + vxT( 'This payment is refunded by hand (bank transfer). The refund is noted on the order.' ) + '</p>' ) +
			tog( 'restock', false, vxT( 'Put the items back in stock' ), '' ),
			[ { k:'no', label:vxT( 'Cancel' ) }, { k:'yes', label:vxT( 'Refund' ), cls:'vsa-btn-danger' } ],
			function ( k, m, b ) {
				if ( 'yes' !== k ) { closeModal(); return; }
				var amt = num( m.querySelector( '#vsa-ramt' ).value );
				if ( ! amt || amt <= 0 || amt > left + 0.001 ) { toast( vxT( 'Check the amount.' ), true ); return; }
				var api_ = m.querySelector( '[data-k="api"]' ), rs = m.querySelector( '[data-k="restock"]' );
				b.disabled = true;
				orderAct( 'refund', { amount:amt, api:api_ && api_.checked ? 1 : 0, restock:rs.checked ? 1 : 0 } ).then( closeModal );
			} );
	}
	function addrDialog() {
		var c = O.customer, s = O.ship_to || { first:'', last:'', company:'', address:{ line1:'', line2:'', zip:'', city:'', country:'DE' } };
		var cs = Object.keys( C.countries ).map( function ( k ) { return [ k, C.countries[ k ] ]; } );
		function block( pre, v, withContact ) {
			return ( withContact ? '<div class="vsa-fr">' + field( vxT( 'Email' ), '<input class="vsa-in" data-a="' + pre + '.email" value="' + esc( v.email ) + '">' ) + field( vxT( 'Phone' ), '<input class="vsa-in" data-a="' + pre + '.phone" value="' + esc( v.phone || '' ) + '">' ) + '</div>' : '' ) +
				'<div class="vsa-fr">' + field( vxT( 'First name' ), '<input class="vsa-in" data-a="' + pre + '.first" value="' + esc( v.first ) + '">' ) + field( vxT( 'Last name' ), '<input class="vsa-in" data-a="' + pre + '.last" value="' + esc( v.last ) + '">' ) + '</div>' +
				field( vxT( 'Company' ), '<input class="vsa-in" data-a="' + pre + '.company" value="' + esc( v.company || '' ) + '">' ) +
				field( vxT( 'Street and number' ), '<input class="vsa-in" data-a="' + pre + '.address.line1" value="' + esc( v.address.line1 ) + '">' ) +
				'<div class="vsa-fr">' + field( vxT( 'Postcode' ), '<input class="vsa-in" data-a="' + pre + '.address.zip" value="' + esc( v.address.zip ) + '">' ) + field( vxT( 'Town' ), '<input class="vsa-in" data-a="' + pre + '.address.city" value="' + esc( v.address.city ) + '">' ) + '</div>' +
				field( vxT( 'Country' ), '<select class="vsa-in" data-a="' + pre + '.address.country">' + cs.map( function ( o ) { return '<option value="' + o[ 0 ] + '"' + ( o[ 0 ] === v.address.country ? ' selected' : '' ) + '>' + esc( o[ 1 ] ) + '</option>'; } ).join( '' ) + '</select>' );
		}
		modal( vxT( 'Edit addresses' ), '<h3 class="vsa-h3">' + vxT( 'Billing' ) + '</h3>' + block( 'customer', c, true ) + '<h3 class="vsa-h3">' + vxT( 'Delivery' ) + ' <small class="vsa-muted">' + vxT( 'leave empty to deliver to the billing address' ) + '</small></h3>' + block( 'ship_to', s ),
			[ { k:'no', label:vxT( 'Cancel' ) }, { k:'yes', label:vxT( 'Save' ) } ],
			function ( k, m ) {
				if ( 'yes' !== k ) { closeModal(); return; }
				var out = { customer:JSON.parse( JSON.stringify( c ) ), ship_to:JSON.parse( JSON.stringify( s ) ) };
				[].forEach.call( m.querySelectorAll( '[data-a]' ), function ( el ) { var p = el.getAttribute( 'data-a' ).split( '.' ), o = out; for ( var i = 0; i < p.length - 1; i++ ) { o = o[ p[ i ] ] = o[ p[ i ] ] || {}; } o[ p[ p.length - 1 ] ] = el.value; } );
				if ( ! out.ship_to.address.line1 ) { delete out.ship_to; }
				closeModal(); orderAct( 'address', out );
			}, true );
	}

	/* ============================================================ PRODUCTS */

	var PV = { q:'', cat:0, status:'', page:1, sel:{} }, PROWS = [];
	function products() {
		main().innerHTML = head( vxT( 'Products' ), '', '<button type="button" class="vsa-btn vsa-btn-ghost" data-act="cats">' + ic( 'tag', 15 ) + vxT( 'Categories' ) + '</button><a class="vsa-btn vsa-btn-pri" href="#/products/new">' + ic( 'plus', 15 ) + vxT( 'New product' ) + '</a>' ) +
			'<div class="vsa-tools"><div class="vsa-tabs">' + [ [ '', vxT( 'All' ) ], [ 'publish', vxT( 'Live' ) ], [ 'draft', vxT( 'Drafts' ) ], [ 'low', vxT( 'Low or out of stock' ) ] ].map( function ( v ) { return '<button type="button" data-pview="' + v[ 0 ] + '" class="' + ( PV.status === v[ 0 ] ? 'is-on' : '' ) + '">' + esc( v[ 1 ] ) + '</button>'; } ).join( '' ) + '</div>' +
			'<select class="vsa-in vsa-in-sm" data-pcat><option value="0">' + vxT( 'All categories' ) + '</option>' + ( C.cats || [] ).map( function ( c ) { return '<option value="' + c.id + '"' + ( +PV.cat === c.id ? ' selected' : '' ) + '>' + esc( c.name ) + '</option>'; } ).join( '' ) + '</select>' +
			'<label class="vsa-search">' + ic( 'search', 15 ) + '<input type="search" data-psearch value="' + esc( PV.q ) + '" placeholder="' + esc( vxT( 'Find a product' ) ) + '"></label></div><div id="vsa-bulk"></div><div id="vsa-plist"></div>';
		loadProducts();
	}
	function loadProducts( data ) {
		var box = document.getElementById( 'vsa-plist' ); if ( ! box ) { return; }
		( data ? Promise.resolve( data ) : api( 'products', PV ) ).then( function ( d ) {
			C.cats = d.cats; PROWS = d.rows;
			if ( ! d.rows.length ) {
				box.innerHTML = empty( 'box', PV.q || PV.status || +PV.cat ? vxT( 'No product matches.' ) : vxT( 'No products yet' ), PV.q ? '' : vxT( 'Add your first product: a name, a price and a photo are enough to start selling.' ), PV.q ? '' : '<a class="vsa-btn vsa-btn-pri" href="#/products/new">' + ic( 'plus', 15 ) + vxT( 'New product' ) + '</a>' );
				drawBulk(); return;
			}
			box.innerHTML = '<div class="vsa-card vsa-card-flush"><table class="vsa-table vsa-ptable"><thead><tr><th class="c"><input type="checkbox" data-pall aria-label="' + esc( vxT( 'Select all' ) ) + '"></th><th>' + vxT( 'Product' ) + '</th><th>' + vxT( 'Price' ) + '</th><th>' + vxT( 'Stock' ) + '</th><th>' + vxT( 'Status' ) + '</th></tr></thead><tbody>' +
				d.rows.map( function ( r ) {
					var price = 'inquiry' === r.mode ? '<span class="vsa-muted">' + vxT( 'On request' ) + '</span>' : ( r.variants && r.priceHi !== r.price ? money( r.price ) + ' – ' + money( r.priceHi ) : '<span class="vsa-inl" data-inl="price" data-id="' + r.id + '" tabindex="0" title="' + esc( vxT( 'Click to change' ) ) + '">' + money( r.price ) + '</span>' ) + ( r.compare ? ' <del class="vsa-muted">' + money( r.compare ) + '</del>' : '' );
					var stock = null === r.stock ? '<span class="vsa-muted">' + vxT( 'not counted' ) + '</span>' : ( r.variants || r.unique ? '<b class="' + ( r.stock ? '' : 'vsa-bad' ) + '">' + r.stock + '</b>' : '<span class="vsa-inl" data-inl="stock" data-id="' + r.id + '" tabindex="0" title="' + esc( vxT( 'Click to change' ) ) + '"><b class="' + ( r.stock ? ( 'low' === r.avail ? 'vsa-warn' : '' ) : 'vsa-bad' ) + '">' + r.stock + '</b></span>' );
					return '<tr data-href="#/products/' + r.id + '"><td class="c"><input type="checkbox" data-psel="' + r.id + '"' + ( PV.sel[ r.id ] ? ' checked' : '' ) + '></td><td><span class="vsa-prow">' + thumb( r.thumb ) + '<span><b>' + esc( r.title ) + '</b><small>' + esc( [ r.sku, r.variants ? r.variants + ' ' + vxT( 'variants' ) : '', r.cats.join( ', ' ), { digital:vxT( 'Download' ), service:vxT( 'Service' ) }[ r.type ] || '' ].filter( Boolean ).join( ' · ' ) ) + '</small></span></span></td>' +
						'<td>' + price + '</td><td>' + stock + '</td><td>' + ( 'publish' === r.status ? '<span class="vsa-chip is-ok">' + vxT( 'Live' ) + '</span>' : '<span class="vsa-chip is-off">' + vxT( 'Draft' ) + '</span>' ) + ( /out|sold/.test( r.avail ) ? ' <span class="vsa-chip is-bad">' + esc( r.availText ) + '</span>' : '' ) + '</td></tr>';
				} ).join( '' ) + '</tbody></table></div>' + pager( d.pages, PV.page, 'ppage' );
			drawBulk();
		} ).catch( fail );
	}
	function drawBulk() {
		var n = Object.keys( PV.sel ).filter( function ( k ) { return PV.sel[ k ]; } ).length, b = document.getElementById( 'vsa-bulk' ); if ( ! b ) { return; }
		b.innerHTML = n ? '<div class="vsa-bulk"><b>' + n + ' ' + vxT( 'selected' ) + '</b><button type="button" class="vsa-btn vsa-btn-sm vsa-btn-ghost" data-bulk="publish">' + vxT( 'Publish' ) + '</button><button type="button" class="vsa-btn vsa-btn-sm vsa-btn-ghost" data-bulk="draft">' + vxT( 'To drafts' ) + '</button><button type="button" class="vsa-btn vsa-btn-sm vsa-btn-ghost" data-bulk="duplicate">' + ic( 'copy', 14 ) + vxT( 'Duplicate' ) + '</button><button type="button" class="vsa-btn vsa-btn-sm vsa-btn-danger" data-bulk="trash">' + ic( 'trash', 14 ) + vxT( 'Delete' ) + '</button><button type="button" class="vsa-link" data-bulk="none">' + vxT( 'Clear' ) + '</button></div>' : '';
	}
	function bulk( act, extra ) {
		var ids = Object.keys( PV.sel ).filter( function ( k ) { return PV.sel[ k ]; } ).map( Number );
		if ( extra && extra.ids ) { ids = extra.ids; }
		return api( 'product_bulk', Object.assign( {}, PV, { ids:ids, act:act }, extra || {} ) ).then( function ( d ) { if ( ! extra ) { PV.sel = {}; } loadProducts( d ); } ).catch( fail );
	}
	function catsDialog() {
		function body() {
			return '<ul class="vsa-catlist">' + ( C.cats || [] ).map( function ( c ) { return '<li><input class="vsa-in" data-catname="' + c.id + '" value="' + esc( c.name ) + '"><span class="vsa-muted">' + c.count + '</span><button type="button" class="vsa-iconbtn" data-catdel="' + c.id + '" aria-label="' + esc( vxT( 'Delete' ) ) + '">' + ic( 'trash', 14 ) + '</button></li>'; } ).join( '' ) + '</ul>' +
				'<div class="vsa-row"><input class="vsa-in" id="vsa-newcat" placeholder="' + esc( vxT( 'New category' ) ) + '"><button type="button" class="vsa-btn vsa-btn-sm" data-catadd>' + ic( 'plus', 14 ) + vxT( 'Add' ) + '</button></div>';
		}
		var m = modal( vxT( 'Categories' ), body(), [] );
		function redraw( r ) { C.cats = r.cats; m.querySelector( '.vsa-modal-b' ).innerHTML = body(); }
		m.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-catadd]' ) ) { var n = m.querySelector( '#vsa-newcat' ).value.trim(); if ( n ) { api( 'cat', { act:'add', name:n } ).then( redraw ).catch( fail ); } }
			var d = e.target.closest( '[data-catdel]' ); if ( d ) { api( 'cat', { act:'delete', id:+d.getAttribute( 'data-catdel' ) } ).then( redraw ).catch( fail ); }
		} );
		m.addEventListener( 'change', function ( e ) { var r = e.target.getAttribute( 'data-catname' ); if ( r ) { api( 'cat', { act:'rename', id:+r, name:e.target.value } ).then( function ( x ) { C.cats = x.cats; toast( vxT( 'Saved.' ) ); } ).catch( fail ); } } );
		m.addEventListener( 'keydown', function ( e ) { if ( 'Enter' === e.key && 'vsa-newcat' === e.target.id ) { e.preventDefault(); m.querySelector( '[data-catadd]' ).click(); } } );
	}

	/* --------------------------------------------------- product editor */

	var P = null, Pclean = '';
	function blankProduct() {
		return { id:0, title:'', content:'', excerpt:'', status:'draft', slug:'', menu:0, thumb:null, galleryImgs:[], type:'physical', mode:'buy', price:'', compare:'', sale_until:'', sku:'', gtin:'', track:0, stock:'', backorder:0, unique:0, max_qty:'', tax:'standard', weight:'', delivery:'', unit_qty:'', unit:'', options:[], variants:[], files:[], fileInfo:[], specs:[], gpsr:'', badge:'', button:'', upsell:[], upsellInfo:[], catIds:[], varImgs:{}, url:'', sold:0 };
	}
	function productEdit() {
		if ( 'new' === route.id ) { P = blankProduct(); Pclean = JSON.stringify( P ); drawProduct(); return; }
		loading();
		api( 'product', { id:+route.id } ).then( function ( p ) { P = Object.assign( blankProduct(), p ); Pclean = JSON.stringify( P ); drawProduct(); } ).catch( fail );
	}
	function markDirty() { dirty = JSON.stringify( P ) !== Pclean; var b = document.getElementById( 'vsa-savebar' ); if ( b ) { b.classList.toggle( 'is-dirty', dirty ); } }
	function drawProduct() {
		var p = P, phys = 'physical' === p.type, hasVar = p.options.length > 0;
		var h = head( p.id ? esc( p.title || vxT( 'Product' ) ) : vxT( 'New product' ), p.id ? ( 'publish' === p.status ? '<span class="vsa-chip is-ok">' + vxT( 'Live' ) + '</span>' : '<span class="vsa-chip is-off">' + vxT( 'Draft' ) + '</span>' ) + ( p.sold ? ' &nbsp;' + p.sold + ' ' + vxT( 'orders' ) : '' ) : '', p.id ? '<a class="vsa-btn vsa-btn-ghost" href="' + esc( p.url ) + '" target="_blank" rel="noopener">' + ic( 'eye', 15 ) + vxT( 'View' ) + '</a>' : '', '#/products' );
		h += '<div class="vsa-cols"><div class="vsa-col-main">';
		// basics
		h += card( '', field( vxT( 'Name' ), inp( 'title', p.title, { ph:vxT( 'e.g. Linen shirt' ), cls:'vsa-in-lg' } ) ) +
			field( vxT( 'Short description' ), area( 'excerpt', p.excerpt, 2, vxT( 'One or two sentences next to the price.' ) ) ) +
			field( vxT( 'Description' ), area( 'content', p.content, 8, vxT( 'Everything a buyer wants to know. HTML is allowed.' ) ) ) );
		// media
		var imgs = [];
		if ( p.thumb ) { imgs.push( p.thumb ); }
		imgs = imgs.concat( p.galleryImgs || [] );
		h += card( vxT( 'Photos' ), '<div class="vsa-media">' + imgs.map( function ( im, i ) { return '<figure class="vsa-mimg' + ( 0 === i ? ' is-main' : '' ) + '"><img src="' + esc( im.url ) + '" alt=""><span class="vsa-mimg-a">' + ( i ? '<button type="button" data-img-main="' + i + '" title="' + esc( vxT( 'Make it the main photo' ) ) + '">★</button>' : '<i>' + vxT( 'Main' ) + '</i>' ) + '<button type="button" data-img-del="' + i + '" aria-label="' + esc( vxT( 'Remove' ) ) + '">' + ic( 'x', 13 ) + '</button></span></figure>'; } ).join( '' ) +
			'<button type="button" class="vsa-madd" data-act="p-media">' + ic( 'plus', 20 ) + '<span>' + vxT( 'Add photos' ) + '</span></button></div><p class="vsa-hint">' + vxT( 'The first photo is the main one. Drag order: use ★ to choose the main photo.' ) + '</p>' );
		// pricing
		var pricing = '<div class="vsa-fr">' + field( vxT( 'Price' ), inp( 'price', dec( p.price ), { mode:'decimal', suffix:C.currency, ph:'0,00' } ), 'standard' === S.tax_mode ? vxT( 'Including VAT' ) : vxT( 'No VAT (§ 19 UStG)' ) ) +
			field( vxT( 'Was price' ), inp( 'compare', dec( p.compare ), { mode:'decimal', suffix:C.currency, ph:vxT( 'optional' ) } ), vxT( 'Shown crossed out — a sale.' ) ) + '</div>' +
			( '' !== String( p.compare ) ? '<div class="vsa-fr">' + field( vxT( 'Sale ends after' ), inp( 'sale_until', p.sale_until, { type:'date' } ), vxT( 'Empty = until you remove the was price.' ) ) + '<span></span></div>' : '' ) +
			( 'standard' === S.tax_mode ? field( vxT( 'VAT rate' ), seg( 'tax', p.tax, [ [ 'standard', vxT( 'Standard' ) + ' ' + dec( S.rate_standard ) + ' %' ], [ 'reduced', vxT( 'Reduced' ) + ' ' + dec( S.rate_reduced ) + ' %' ], [ 'zero', '0 %' ] ] ) ) : '' ) +
			tog( 'mode', 'inquiry' === p.mode, vxT( 'Price on request' ), vxT( 'No cart button — visitors ask you instead (for one-off or custom work).' ) ) +
			'<div class="vsa-preview">' + pricePreview() + '</div>';
		h += card( vxT( 'Price' ), pricing );
		// type + inventory
		h += card( vxT( 'Type and stock' ), field( vxT( 'What is it?' ), seg( 'type', p.type, [ [ 'physical', vxT( 'Physical' ), 'box' ], [ 'digital', vxT( 'Download' ), 'download' ], [ 'service', vxT( 'Service' ), 'users' ] ] ) ) +
			tog( 'unique', !! +p.unique, vxT( 'One of a kind' ), vxT( 'Sells exactly once, then shows “Sold” (art, vintage, originals).' ) ) +
			( +p.unique ? '' : tog( 'track', !! +p.track, vxT( 'Count stock' ), vxT( 'Stops selling when it runs out, warns you when it runs low.' ) ) ) +
			( +p.track && ! +p.unique && ! hasVar ? '<div class="vsa-fr">' + field( vxT( 'In stock' ), inp( 'stock', p.stock, { mode:'numeric', ph:'0' } ) ) + field( vxT( 'Max. per order' ), inp( 'max_qty', p.max_qty, { mode:'numeric', ph:vxT( 'no limit' ) } ) ) + '</div>' + tog( 'backorder', !! +p.backorder, vxT( 'Keep selling when sold out' ), vxT( 'Shown as “Available to order”.' ) ) : '' ) +
			( +p.track && ! +p.unique && hasVar ? '<p class="vsa-hint">' + vxT( 'Each variant has its own stock (below).' ) + '</p>' + tog( 'backorder', !! +p.backorder, vxT( 'Keep selling when sold out' ), '' ) : '' ) +
			( +p.unique ? '<div class="vsa-fr">' + field( vxT( 'Status' ), seg( 'stock', String( '' === String( p.stock ) ? 1 : p.stock ), [ [ '1', vxT( 'Available' ) ], [ '0', vxT( 'Sold' ) ] ] ) ) + '<span></span></div>' : '' ) +
			'<div class="vsa-fr">' + field( vxT( 'Item number (SKU)' ), inp( 'sku', p.sku, { ph:vxT( 'optional' ) } ) ) + field( 'EAN / GTIN', inp( 'gtin', p.gtin, { mode:'numeric', ph:vxT( 'optional' ) } ) ) + '</div>' );
		// variants
		h += card( vxT( 'Options' ), variantsHTML(), { id:'vsa-variants', right:p.options.length < 3 && ! +p.unique ? '<button type="button" class="vsa-btn vsa-btn-sm vsa-btn-ghost" data-act="v-addopt">' + ic( 'plus', 14 ) + vxT( 'Add option' ) + '</button>' : '' } );
		// shipping
		if ( phys ) {
			h += card( vxT( 'Shipping' ), '<div class="vsa-fr">' + field( vxT( 'Delivery time' ), inp( 'delivery', p.delivery, { ph:vxT( 'e.g. 2–4 working days' ) } ), vxT( 'Required in Germany before ordering.' ) ) + field( vxT( 'Weight' ), inp( 'weight', p.weight, { mode:'decimal', suffix:'kg', ph:vxT( 'optional' ) } ) ) + '</div>' );
		}
		if ( 'digital' === p.type ) {
			h += card( vxT( 'Files to download' ), '<ul class="vsa-files">' + ( p.fileInfo || [] ).map( function ( f, i ) { return '<li>' + ic( 'file', 15 ) + '<span class="vsa-grow">' + esc( f.name ) + '</span><button type="button" class="vsa-iconbtn" data-file-del="' + i + '" aria-label="' + esc( vxT( 'Remove' ) ) + '">' + ic( 'x', 14 ) + '</button></li>'; } ).join( '' ) + '</ul><button type="button" class="vsa-btn vsa-btn-sm vsa-btn-ghost" data-act="p-files">' + ic( 'plus', 14 ) + vxT( 'Add files' ) + '</button><p class="vsa-hint">' + vxT( 'Buyers get private download links after paying. The files are never linked publicly.' ) + '</p>' );
		}
		// legal + details
		h += card( vxT( 'Legal information' ), '<div class="vsa-fr">' + field( vxT( 'Contents' ), inp( 'unit_qty', p.unit_qty, { mode:'decimal', ph:'250' } ) ) + field( vxT( 'Unit' ), sel( 'unit', p.unit, [ [ '', vxT( 'no unit price' ) ], [ 'g', 'g' ], [ 'kg', 'kg' ], [ 'ml', 'ml' ], [ 'l', 'l' ], [ 'm', 'm' ], [ 'm2', 'm²' ], [ 'm3', 'm³' ] ] ) ) + '</div>' +
			'<p class="vsa-hint">' + vxT( 'Goods sold by weight, volume or length must show the price per kg / l / m next to the price (PAngV). Velox works it out.' ) + '</p>' +
			field( vxT( 'Manufacturer and safety information' ), area( 'gpsr', p.gpsr, 3, vxT( 'Name and address of the manufacturer (or the EU importer), plus any warnings — required by the EU product safety regulation (GPSR).' ) ) ) );
		h += card( vxT( 'Details' ), '<p class="vsa-hint">' + vxT( 'Shown as a table on the product page: material, size, care, year …' ) + '</p><div class="vsa-specs">' + p.specs.map( function ( s, i ) { return '<div class="vsa-spec"><input class="vsa-in" data-spec="' + i + '" data-sk="k" value="' + esc( s.k ) + '" placeholder="' + esc( vxT( 'Material' ) ) + '"><input class="vsa-in" data-spec="' + i + '" data-sk="v" value="' + esc( s.v ) + '" placeholder="' + esc( vxT( 'Linen' ) ) + '"><button type="button" class="vsa-iconbtn" data-spec-del="' + i + '" aria-label="' + esc( vxT( 'Remove' ) ) + '">' + ic( 'x', 14 ) + '</button></div>'; } ).join( '' ) + '</div><button type="button" class="vsa-btn vsa-btn-sm vsa-btn-ghost" data-act="spec-add">' + ic( 'plus', 14 ) + vxT( 'Add a line' ) + '</button>' );
		h += '</div><aside class="vsa-col-side">';
		h += card( vxT( 'Visibility' ), seg( 'status', 'publish' === p.status ? 'publish' : 'draft', [ [ 'publish', vxT( 'Live' ), 'eye' ], [ 'draft', vxT( 'Draft' ), 'file' ] ] ) + '<p class="vsa-hint">' + ( 'publish' === p.status ? vxT( 'Visible in the shop and can be bought.' ) : vxT( 'Only you can see it.' ) ) + '</p>' );
		h += card( vxT( 'Categories' ), ( C.cats || [] ).length ? '<div class="vsa-checks2">' + C.cats.map( function ( c ) { return '<label class="vsa-check"><input type="checkbox" data-pcatid="' + c.id + '"' + ( p.catIds.indexOf( c.id ) > -1 ? ' checked' : '' ) + '><span>' + esc( c.name ) + '</span></label>'; } ).join( '' ) + '</div>' : '<p class="vsa-muted">' + vxT( 'No categories yet.' ) + '</p>', { right:'<button type="button" class="vsa-link" data-act="cats">' + vxT( 'Manage' ) + '</button>' } );
		h += card( vxT( 'Extras' ), field( vxT( 'Badge' ), inp( 'badge', p.badge, { ph:vxT( 'e.g. New, Bestseller' ) } ), vxT( 'A small label on the photo.' ) ) + field( vxT( 'Button text' ), inp( 'button', p.button, { ph:vxT( 'Add to cart' ) } ) ) + field( vxT( 'Web address' ), inp( 'slug', p.slug, { ph:vxT( 'from the name' ) } ) ) +
			field( vxT( 'Recommended with it' ), '<div class="vsa-ups">' + ( p.upsellInfo || [] ).map( function ( u, i ) { return '<span class="vsa-tag">' + esc( u.title ) + '<button type="button" data-ups-del="' + i + '" aria-label="' + esc( vxT( 'Remove' ) ) + '">×</button></span>'; } ).join( '' ) + '</div><input class="vsa-in" data-upsearch placeholder="' + esc( vxT( 'Find a product to add' ) ) + '" list="vsa-upslist"><datalist id="vsa-upslist"></datalist>' ) );
		h += '</aside></div>';
		h += '<div class="vsa-savebar' + ( dirty ? ' is-dirty' : '' ) + '" id="vsa-savebar"><span class="vsa-savebar-t">' + ic( 'clock', 14 ) + vxT( 'Unsaved changes' ) + '</span>' + ( p.id ? '<button type="button" class="vsa-btn vsa-btn-ghost vsa-btn-danger-t" data-act="p-trash">' + ic( 'trash', 15 ) + vxT( 'Delete' ) + '</button>' : '' ) + '<button type="button" class="vsa-btn vsa-btn-pri" data-act="p-save">' + ic( 'check', 15 ) + ( p.id ? vxT( 'Save' ) : vxT( 'Create product' ) ) + '</button></div>';
		main().innerHTML = h;
	}
	function pricePreview() {
		var p = P, pr = num( p.price ), was = num( p.compare );
		if ( 'inquiry' === p.mode ) { return '<span class="vsa-pp"><b>' + vxT( 'Price on request' ) + '</b></span>'; }
		var unit = '';
		var q = num( p.unit_qty );
		if ( q && p.unit && pr ) { var per = { g:[ 1000, 'kg' ], kg:[ 1, 'kg' ], ml:[ 1000, 'l' ], l:[ 1, 'l' ], m:[ 1, 'm' ], m2:[ 1, 'm²' ], m3:[ 1, 'm³' ] }[ p.unit ]; unit = ' · 1 ' + per[ 1 ] + ' = ' + money( pr / q * per[ 0 ] ); }
		return '<span class="vsa-pp-l">' + vxT( 'Customers see' ) + '</span><span class="vsa-pp">' + ( was && pr && was > pr ? '<del>' + money( was ) + '</del> ' : '' ) + '<b>' + ( null === pr ? '—' : money( pr ) ) + '</b><small>' + ( 'small' === S.tax_mode ? vxT( 'No VAT (§ 19 UStG)' ) : vxT( 'incl. VAT' ) ) + ( 'physical' === p.type ? ', ' + vxT( 'plus shipping' ) : '' ) + esc( unit ) + '</small></span>';
	}
	/* variants: options (up to 3, each with values) → one row per combination */
	function combos( opts ) {
		var out = [ [] ];
		opts.forEach( function ( o ) { var n = []; out.forEach( function ( c ) { o.values.forEach( function ( v ) { n.push( c.concat( [ v ] ) ); } ); } ); out = n; } );
		return opts.length ? out : [];
	}
	function syncVariants() {
		var by = {};
		P.variants.forEach( function ( v ) { by[ v.opts.join( '\u0001' ) ] = v; } );
		P.variants = combos( P.options.filter( function ( o ) { return o.values.length; } ) ).map( function ( c ) {
			var k = c.join( '\u0001' );
			return by[ k ] || { id:Math.random().toString( 36 ).slice( 2, 10 ), opts:c, price:'', compare:'', sku:'', stock:'', image:0, off:0 };
		} );
	}
	function variantsHTML() {
		var p = P;
		if ( +p.unique ) { return '<p class="vsa-muted">' + vxT( 'A one-of-a-kind piece has no options.' ) + '</p>'; }
		if ( ! p.options.length ) { return '<p class="vsa-muted">' + vxT( 'Sizes, colours, materials … Add an option and Velox makes a variant for every combination, each with its own price, stock and photo.' ) + '</p>'; }
		var h = '<div class="vsa-opts">' + p.options.map( function ( o, i ) {
			return '<div class="vsa-opt"><input class="vsa-in vsa-opt-n" data-optname="' + i + '" value="' + esc( o.name ) + '" placeholder="' + esc( vxT( 'Size' ) ) + '"><div class="vsa-tags">' + o.values.map( function ( v, j ) { return '<span class="vsa-tag">' + esc( v ) + '<button type="button" data-optval-del="' + i + ':' + j + '" aria-label="' + esc( vxT( 'Remove' ) ) + '">×</button></span>'; } ).join( '' ) +
				'<input class="vsa-tag-in" data-optval="' + i + '" placeholder="' + esc( o.values.length ? vxT( 'Add a value' ) : vxT( 'S, M, L … press Enter' ) ) + '"></div><button type="button" class="vsa-iconbtn" data-optdel="' + i + '" aria-label="' + esc( vxT( 'Remove option' ) ) + '">' + ic( 'trash', 14 ) + '</button></div>';
		} ).join( '' ) + '</div>';
		if ( p.variants.length ) {
			var track = +p.track;
			h += '<div class="vsa-vtable-w"><table class="vsa-vtable"><thead><tr><th>' + vxT( 'Variant' ) + '</th><th>' + vxT( 'Price' ) + '</th><th>' + vxT( 'Was' ) + '</th>' + ( track ? '<th>' + vxT( 'Stock' ) + '</th>' : '' ) + '<th>SKU</th><th>' + vxT( 'Photo' ) + '</th><th>' + vxT( 'On sale' ) + '</th></tr></thead><tbody>' +
				p.variants.map( function ( v, i ) {
					return '<tr class="' + ( +v.off ? 'is-off' : '' ) + '"><td><b>' + esc( v.opts.join( ' / ' ) ) + '</b></td>' +
						'<td><input class="vsa-in vsa-in-sm" data-var="' + i + '" data-vk="price" value="' + esc( dec( v.price ) ) + '" placeholder="' + esc( dec( p.price ) || '0,00' ) + '" inputmode="decimal"></td>' +
						'<td><input class="vsa-in vsa-in-sm" data-var="' + i + '" data-vk="compare" value="' + esc( dec( v.compare ) ) + '" inputmode="decimal"></td>' +
						( track ? '<td><input class="vsa-in vsa-in-sm" data-var="' + i + '" data-vk="stock" value="' + esc( v.stock ) + '" inputmode="numeric" placeholder="0"></td>' : '' ) +
						'<td><input class="vsa-in vsa-in-sm vsa-mono" data-var="' + i + '" data-vk="sku" value="' + esc( v.sku ) + '"></td>' +
						'<td><button type="button" class="vsa-vimg" data-var-img="' + i + '" aria-label="' + esc( vxT( 'Choose a photo' ) ) + '">' + ( +v.image && P.varImgs[ v.image ] ? '<img src="' + esc( P.varImgs[ v.image ] ) + '" alt="">' : ic( 'image', 14 ) ) + '</button></td>' +
						'<td><label class="vsa-tog vsa-tog-sm"><input type="checkbox" data-var="' + i + '" data-vk="on"' + ( +v.off ? '' : ' checked' ) + '><span class="vsa-tog-ui"></span></label></td></tr>';
				} ).join( '' ) + '</tbody></table></div><p class="vsa-hint">' + vxT( 'Empty price = the product price. Switch off combinations you don’t sell.' ) + '</p>';
		}
		return h;
	}
	function redrawVariants() { var c = document.getElementById( 'vsa-variants' ); if ( c ) { c.querySelector( '.vsa-card-b' ).innerHTML = variantsHTML(); } markDirty(); }
	function saveProduct( btn ) {
		if ( ! P.title.trim() ) { toast( vxT( 'Give the product a name.' ), true ); var t = main().querySelector( '[data-k="title"]' ); if ( t ) { t.focus(); } return; }
		if ( 'buy' === P.mode && null === num( P.price ) && ! P.variants.length ) { toast( vxT( 'Enter a price (or switch on “Price on request”).' ), true ); return; }
		var out = Object.assign( {}, P, { thumb:P.thumb ? P.thumb.id : 0, gallery:( P.galleryImgs || [] ).map( function ( g ) { return g.id; } ), upsell:( P.upsellInfo || [] ).map( function ( u ) { return u.id; } ), files:( P.fileInfo || [] ).map( function ( f ) { return f.id; } ) } );
		[ 'price', 'compare' ].forEach( function ( k ) { var n = num( out[ k ] ); out[ k ] = null === n ? '' : n; } );
		out.variants = P.variants.map( function ( v ) { return Object.assign( {}, v, { price:null === num( v.price ) ? '' : num( v.price ), compare:null === num( v.compare ) ? '' : num( v.compare ) } ); } );
		btn.disabled = true;
		api( 'product_save', out ).then( function ( p ) {
			var isNew = ! P.id;
			P = Object.assign( blankProduct(), p ); Pclean = JSON.stringify( P ); dirty = false;
			toast( isNew ? vxT( 'Product created.' ) : vxT( 'Saved.' ) );
			if ( isNew ) { lastHash = '#/products/' + p.id; history.replaceState( null, '', lastHash ); route.id = String( p.id ); }
			drawProduct();
		} ).catch( function ( e ) { btn.disabled = false; fail( e ); } );
	}

	/* ============================================================= COUPONS */

	var CP = null;
	function coupons() {
		loading();
		api( 'coupons' ).then( function ( d ) { CP = d.rows; drawCoupons(); } ).catch( fail );
	}
	function drawCoupons() {
		var h = head( vxT( 'Coupons' ), vxT( 'Codes customers type in the cart or at checkout.' ), '<button type="button" class="vsa-btn vsa-btn-pri" data-act="c-add">' + ic( 'plus', 15 ) + vxT( 'New code' ) + '</button>' );
		if ( ! CP.length ) {
			h += empty( 'percent', vxT( 'No codes yet' ), vxT( 'Percent off, a fixed amount or free shipping — with a minimum order, an end date or a limit if you like.' ), '<button type="button" class="vsa-btn vsa-btn-pri" data-act="c-add">' + ic( 'plus', 15 ) + vxT( 'New code' ) + '</button>' );
		} else {
			h += '<div class="vsa-coupons">' + CP.map( function ( c, i ) {
				return '<div class="vsa-coupon' + ( +c.off ? ' is-off' : '' ) + '"><div class="vsa-fr vsa-fr-6">' +
					field( vxT( 'Code' ), '<input class="vsa-in vsa-mono vsa-up" data-cp="' + i + '" data-ck="code" value="' + esc( c.code ) + '">' ) +
					field( vxT( 'Type' ), '<select class="vsa-in" data-cp="' + i + '" data-ck="type">' + [ [ 'percent', vxT( '% off' ) ], [ 'fixed', vxT( 'Amount off' ) ], [ 'ship', vxT( 'Free shipping' ) ] ].map( function ( o ) { return '<option value="' + o[ 0 ] + '"' + ( o[ 0 ] === c.type ? ' selected' : '' ) + '>' + esc( o[ 1 ] ) + '</option>'; } ).join( '' ) + '</select>' ) +
					( 'ship' === c.type ? '<span></span>' : field( 'percent' === c.type ? vxT( 'Percent' ) : vxT( 'Amount' ), '<input class="vsa-in" data-cp="' + i + '" data-ck="value" value="' + esc( dec( c.value ) ) + '" inputmode="decimal">' ) ) +
					field( vxT( 'Minimum order' ), '<input class="vsa-in" data-cp="' + i + '" data-ck="min" value="' + esc( dec( c.min ) ) + '" inputmode="decimal" placeholder="' + esc( vxT( 'none' ) ) + '">' ) +
					field( vxT( 'Valid until' ), '<input class="vsa-in" type="date" data-cp="' + i + '" data-ck="until" value="' + esc( c.until ) + '">' ) +
					field( vxT( 'Uses' ), '<input class="vsa-in" data-cp="' + i + '" data-ck="limit" value="' + esc( c.limit ) + '" inputmode="numeric" placeholder="∞">', ( +c.used || 0 ) + ' ' + vxT( 'used' ) ) +
					'</div><div class="vsa-coupon-a"><label class="vsa-tog vsa-tog-sm"><input type="checkbox" data-cp="' + i + '" data-ck="on"' + ( +c.off ? '' : ' checked' ) + '><span class="vsa-tog-ui"></span><span class="vsa-tog-t"><b>' + vxT( 'Active' ) + '</b></span></label><button type="button" class="vsa-iconbtn" data-cp-del="' + i + '" aria-label="' + esc( vxT( 'Delete' ) ) + '">' + ic( 'trash', 15 ) + '</button></div></div>';
			} ).join( '' ) + '</div>';
		}
		h += '<div class="vsa-savebar' + ( dirty ? ' is-dirty' : '' ) + '" id="vsa-savebar"><span class="vsa-savebar-t">' + ic( 'clock', 14 ) + vxT( 'Unsaved changes' ) + '</span><button type="button" class="vsa-btn vsa-btn-pri" data-act="c-save">' + ic( 'check', 15 ) + vxT( 'Save' ) + '</button></div>';
		main().innerHTML = h;
	}

	/* =========================================================== CUSTOMERS */

	var CV = { kind:'all', q:'' }, CU = null;
	function customers() {
		if ( route.id ) { customerDetail(); return; }
		var views = [ [ 'all', vxT( 'All' ) ], [ 'account', vxT( 'With account' ) ], [ 'guest', vxT( 'Guests' ) ], [ 'locked', vxT( 'Locked' ) ] ];
		main().innerHTML = head( vxT( 'Customers' ), vxT( 'Customer accounts and everyone who has ordered.' ), '' ) +
			'<div class="vsa-tools"><div class="vsa-tabs" role="tablist">' + views.map( function ( v ) { return '<button type="button" role="tab" data-cview="' + v[ 0 ] + '" class="' + ( CV.kind === v[ 0 ] ? 'is-on' : '' ) + '" aria-selected="' + ( CV.kind === v[ 0 ] ) + '">' + esc( v[ 1 ] ) + '<i class="vsa-tabn" data-cn="' + v[ 0 ] + '"></i></button>'; } ).join( '' ) + '</div>' +
			'<label class="vsa-search">' + ic( 'search', 15 ) + '<input type="search" data-csearch value="' + esc( CV.q ) + '" placeholder="' + esc( vxT( 'Name or email' ) ) + '"></label></div><div id="vsa-clist"></div>';
		loadCustomers();
	}
	function custChips( r ) {
		return ( r.staff ? '<span class="vsa-chip is-off">' + vxT( 'Staff' ) + '</span>' : ( r.user || r.account ? '<span class="vsa-chip is-ok">' + vxT( 'Account' ) + '</span>' : '<span class="vsa-chip is-off">' + vxT( 'Guest' ) + '</span>' ) ) +
			( r.locked ? ' <span class="vsa-chip is-bad">' + vxT( 'Locked' ) + '</span>' : '' );
	}
	function loadCustomers() {
		var b = document.getElementById( 'vsa-clist' ); if ( ! b ) { return; }
		api( 'customers', { q:CV.q, kind:CV.kind } ).then( function ( d ) {
			b = document.getElementById( 'vsa-clist' ); if ( ! b ) { return; }
			Object.keys( d.counts || {} ).forEach( function ( k ) { var n = document.querySelector( '[data-cn="' + k + '"]' ); if ( n ) { n.textContent = d.counts[ k ]; } } );
			b.innerHTML = d.rows.length ? '<div class="vsa-card vsa-card-flush"><table class="vsa-table"><thead><tr><th>' + vxT( 'Customer' ) + '</th><th></th><th>' + vxT( 'Orders' ) + '</th><th>' + vxT( 'Spent' ) + '</th><th>' + vxT( 'Last activity' ) + '</th></tr></thead><tbody>' + d.rows.map( function ( r ) {
				return '<tr data-href="#/customers/' + encodeURIComponent( r.key ) + '"><td><span class="vsa-cust">' + esc( r.name || r.email ) + '</span><small>' + esc( r.email ) + '</small></td><td>' + custChips( r ) + '</td><td>' + r.orders + '</td><td><b>' + money( r.spent ) + '</b></td><td class="vsa-muted">' + esc( date( Math.max( r.last, r.login, r.registered ) ) ) + '</td></tr>';
			} ).join( '' ) + '</tbody></table></div>' : empty( 'users', CV.q || 'all' !== CV.kind ? vxT( 'Nobody here' ) : vxT( 'No customers yet' ), CV.q || 'all' !== CV.kind ? '' : vxT( 'Customers appear here when they create an account or order.' ) );
		} ).catch( fail );
	}
	function customerDetail() {
		loading();
		api( 'customer', { id:decodeURIComponent( route.id ) } ).then( function ( c ) { CU = c; drawCustomer(); } ).catch( function ( e ) { fail( e ); location.hash = '#/customers'; } );
	}
	function drawCustomer() {
		var c = CU, acc = c.account, name = ( c.first + ' ' + c.last ).trim(), A = c.address || {};
		var acts = '';
		if ( acc ) {
			acts += '<button type="button" class="vsa-btn vsa-btn-ghost" data-act="c-reset">' + ic( 'mail', 15 ) + vxT( 'Send password link' ) + '</button>';
		} else {
			acts += '<button type="button" class="vsa-btn vsa-btn-pri" data-act="c-invite">' + ic( 'plus', 15 ) + vxT( 'Create account' ) + '</button>';
		}
		acts += '<button type="button" class="vsa-btn vsa-btn-ghost" data-act="c-msg">' + ic( 'mail', 15 ) + vxT( 'Write an email' ) + '</button>';
		if ( acc ) {
			acts += '<div class="vsa-menu"><button type="button" class="vsa-btn vsa-btn-ghost vsa-btn-icon" data-act="menu" aria-label="' + esc( vxT( 'More actions' ) ) + '" aria-haspopup="true">' + ic( 'more', 16 ) + '</button><div class="vsa-menu-p" hidden>' +
				'<button type="button" data-act="c-signout">' + ic( 'undo', 14 ) + vxT( 'Sign out on all devices' ) + '</button>' +
				'<button type="button" data-act="c-export">' + ic( 'download', 14 ) + vxT( 'Download their data' ) + '</button>' +
				( c.staff ? '' : '<button type="button" data-act="' + ( c.locked ? 'c-unlock' : 'c-lock' ) + '">' + ic( c.locked ? 'check' : 'x', 14 ) + ( c.locked ? vxT( 'Unlock account' ) : vxT( 'Lock account' ) ) + '</button>' +
				'<button type="button" class="is-danger" data-act="c-delete">' + ic( 'trash', 14 ) + vxT( 'Delete account' ) + '</button>' ) +
				'</div></div>';
		}
		var h = head( esc( name || c.email ), custChips( c ) + ' &nbsp; ' + esc( c.email ), acts, '#/customers' );
		if ( c.locked ) {
			h += '<div class="vsa-next is-bad">' + ic( 'x', 18 ) + '<div><b>' + vxT( 'This account is locked' ) + '</b><p>' + vxT( 'The customer cannot sign in. Orders still work as a guest.' ) + '</p></div><button type="button" class="vsa-btn vsa-btn-ghost" data-act="c-unlock">' + vxT( 'Unlock account' ) + '</button></div>';
		} else if ( ! acc ) {
			h += '<div class="vsa-next">' + ic( 'users', 18 ) + '<div><b>' + vxT( 'Ordered as a guest' ) + '</b><p>' + vxT( 'Create an account and the customer gets an email to choose a password. Their orders show up in it right away.' ) + '</p></div></div>';
		}
		h += '<div class="vsa-cols"><div class="vsa-col-main">';
		h += card( vxT( 'Orders' ), c.orders.length ? '<ul class="vsa-items vsa-corders">' + c.orders.map( function ( o ) {
			return '<li><a class="vsa-grow" href="#/orders/' + o.id + '"><b class="vsa-mono">' + esc( o.number ) + '</b><small>' + esc( date( o.created, true ) ) + ' · ' + o.items + ' ' + ( 1 === o.items ? vxT( 'item' ) : vxT( 'items' ) ) + '</small></a>' + chip( PAY, o.pay ) + ' ' + chip( FUL, o.ful ) + '<b>' + money( o.total ) + '</b></li>';
		} ).join( '' ) + '</ul>' : '<p class="vsa-muted">' + vxT( 'No orders yet.' ) + '</p>' );
		var cf = function ( k, label, v, o ) { o = o || {}; return field( label, '<input class="vsa-in" data-cf="' + k + '" value="' + esc( v || '' ) + '"' + ( o.type ? ' type="' + o.type + '"' : '' ) + ( acc && c.canEdit ? '' : ' disabled' ) + '>', o.hint ); };
		var cs = Object.keys( C.countries || {} ).map( function ( k ) { return '<option value="' + k + '"' + ( ( A.vx_country || 'DE' ) === k ? ' selected' : '' ) + '>' + esc( C.countries[ k ] ) + '</option>'; } ).join( '' );
		h += card( vxT( 'Details' ),
			'<div class="vsa-fr">' + cf( 'first', vxT( 'First name' ), c.first ) + cf( 'last', vxT( 'Last name' ), c.last ) + '</div>' +
			cf( 'email', vxT( 'Email' ), c.email, { type:'email', hint:acc ? vxT( 'They sign in with it. The old address is told about the change.' ) : '' } ) +
			'<div class="vsa-fr">' + cf( 'vx_company', vxT( 'Company' ), A.vx_company ) + cf( 'vx_phone', vxT( 'Phone' ), A.vx_phone ) + '</div>' +
			cf( 'vx_line1', vxT( 'Street and number' ), A.vx_line1 ) + cf( 'vx_line2', vxT( 'Address line 2' ), A.vx_line2 ) +
			'<div class="vsa-fr">' + cf( 'vx_zip', vxT( 'Postcode' ), A.vx_zip ) + cf( 'vx_city', vxT( 'Town' ), A.vx_city ) + '</div>' +
			field( vxT( 'Country' ), '<select class="vsa-in" data-cf="vx_country"' + ( acc && c.canEdit ? '' : ' disabled' ) + '>' + cs + '</select>' ) +
			( acc && c.canEdit ? '<div class="vsa-row-end"><button type="button" class="vsa-btn vsa-btn-pri" data-act="c-save">' + ic( 'check', 15 ) + vxT( 'Save' ) + '</button></div>' : '<p class="vsa-hint">' + ( acc ? vxT( 'You are not allowed to change this account.' ) : vxT( 'From the latest order. Guests have no account to change — edit the details in the order, or create an account.' ) ) + '</p>' ) );
		h += '</div><aside class="vsa-col-side">';
		var st = function ( l, v ) { return '<div class="vsa-tr"><span>' + l + '</span><span>' + v + '</span></div>'; };
		h += card( vxT( 'At a glance' ), '<div class="vsa-totals">' + st( vxT( 'Orders' ), c.orders.length ) + st( vxT( 'Spent' ), '<b>' + money( c.spent ) + '</b>' ) +
			( acc ? st( vxT( 'Customer since' ), esc( date( c.registered ) ) ) + st( vxT( 'Last sign-in' ), c.login ? esc( date( c.login, true ) ) : vxT( 'Not yet' ) ) + st( vxT( 'Two-factor sign-in' ), c.twofa ? '<span class="vsa-chip is-ok">' + vxT( 'On' ) + '</span>' : vxT( 'Off' ) ) + ( c.privacy ? st( vxT( 'Privacy consent' ), esc( date( c.privacy ) ) ) : '' ) : '' ) + '</div>' );
		if ( acc ) {
			h += card( vxT( 'Sign-in' ), '<p class="vsa-p">' + ( c.locked ? vxT( 'Locked — the customer cannot sign in.' ) : vxT( 'The customer signs in on the account page with their email.' ) ) + '</p><p><a href="' + esc( c.accountUrl ) + '" target="_blank" rel="noopener">' + ic( 'ext', 14 ) + ' ' + vxT( 'Open the account page' ) + '</a></p>' +
				( c.twofa ? '<p class="vsa-p">' + vxT( 'Two-factor sign-in is on' ) + ' (' + c.twofa.codes + ' ' + vxT( 'recovery codes left' ) + ').</p><p><button type="button" class="vsa-btn vsa-btn-ghost vsa-btn-sm" data-act="c-reset2fa">' + vxT( 'Turn off two-factor sign-in' ) + '</button></p>' : '' ) +
				'<p class="vsa-hint">' + vxT( 'Passwords are never shown or set by the shop: the customer chooses one with the link you send.' ) + '</p>' );
		}
		h += '</aside></div>';
		main().innerHTML = h;
	}
	function custAct( act, extra ) {
		return api( 'customer_action', Object.assign( { id:CU.key, act:act }, extra || {} ) ).then( function ( r ) {
			if ( r.deleted ) { toast( r.msg ); location.hash = '#/customers'; return; }
			if ( r.export ) {
				var blob = new Blob( [ JSON.stringify( r.export, null, 2 ) ], { type:'application/json' } );
				var l = document.createElement( 'a' ); l.href = URL.createObjectURL( blob ); l.download = r.file; document.body.appendChild( l ); l.click(); setTimeout( function () { URL.revokeObjectURL( l.href ); l.remove(); }, 500 );
				return;
			}
			var moved = r.key !== CU.key;
			CU = r;
			if ( moved ) { history.replaceState( null, '', '#/customers/' + encodeURIComponent( r.key ) ); lastHash = location.hash; route = parse(); }
			drawCustomer(); if ( r.msg ) { toast( r.msg ); }
		} ).catch( fail );
	}
	function custClick( act ) {
		var c = CU;
		if ( 'c-save' === act ) {
			var v = function ( k ) { var e = main().querySelector( '[data-cf="' + k + '"]' ); return e ? e.value.trim() : ''; };
			var addr = {}; [ 'vx_company', 'vx_phone', 'vx_line1', 'vx_line2', 'vx_zip', 'vx_city', 'vx_country' ].forEach( function ( k ) { addr[ k ] = v( k ); } );
			custAct( 'save', { first:v( 'first' ), last:v( 'last' ), email:v( 'email' ), address:addr } ); return true;
		}
		if ( 'c-reset' === act ) { confirmBox( vxT( 'Send a password link?' ), vxT( 'The customer gets an email with a link to choose a new password. The link works for 24 hours; the current password keeps working until then.' ) + ' <b>' + esc( c.email ) + '</b>', vxT( 'Send link' ) ).then( function ( y ) { if ( y ) { custAct( 'reset' ); } } ); return true; }
		if ( 'c-invite' === act ) { modal( vxT( 'Create account' ), '<p class="vsa-p">' + vxT( 'A customer account is made for this email, and all their orders show up in it.' ) + ' <b>' + esc( c.email ) + '</b></p><label class="vsa-check"><input type="checkbox" id="vsa-cinv" checked><span>' + vxT( 'Email the customer a link to choose a password' ) + '</span></label>', [ { k:'no', label:vxT( 'Cancel' ) }, { k:'yes', label:ic( 'plus', 15 ) + vxT( 'Create account' ) } ], function ( k, m ) { var t = m.querySelector( '#vsa-cinv' ).checked; closeModal(); if ( 'yes' === k ) { custAct( 'invite', { tell:t ? 1 : 0 } ); } } ); return true; }
		if ( 'c-msg' === act ) { modal( vxT( 'Write an email' ), field( vxT( 'To' ), '<input class="vsa-in" value="' + esc( c.email ) + '" disabled>' ) + field( vxT( 'Subject' ), '<input class="vsa-in" id="vsa-cms">' ) + field( vxT( 'Message' ), '<textarea class="vsa-in" rows="7" id="vsa-cmt"></textarea>', vxT( 'Sent in your shop’s email design. Replies go to your shop email.' ) ), [ { k:'no', label:vxT( 'Cancel' ) }, { k:'yes', label:ic( 'mail', 15 ) + vxT( 'Send' ) } ], function ( k, m ) { var sj = m.querySelector( '#vsa-cms' ).value, tx = m.querySelector( '#vsa-cmt' ).value; if ( 'yes' === k && ( ! sj.trim() || ! tx.trim() ) ) { toast( vxT( 'Write a subject and a message first.' ), true ); return; } closeModal(); if ( 'yes' === k ) { custAct( 'message', { subject:sj, text:tx } ); } } ); return true; }
		if ( 'c-signout' === act ) { custAct( 'signout' ); return true; }
		if ( 'c-reset2fa' === act ) { confirmBox( vxT( 'Turn off two-factor sign-in?' ), vxT( 'For a customer who lost their phone and their recovery codes. Make sure it really is them — for example by replying to an email from their address. They are signed out and can sign in with their password again.' ), vxT( 'Turn off' ), true ).then( function ( y ) { if ( y ) { custAct( 'reset2fa' ); } } ); return true; }
		if ( 'c-export' === act ) { custAct( 'export' ); return true; }
		if ( 'c-lock' === act ) { confirmBox( vxT( 'Lock this account?' ), vxT( 'The customer is signed out and cannot sign in until you unlock the account. Orders and data stay.' ), vxT( 'Lock account' ), true ).then( function ( y ) { if ( y ) { custAct( 'lock' ); } } ); return true; }
		if ( 'c-unlock' === act ) { custAct( 'unlock' ); return true; }
		if ( 'c-delete' === act ) { confirmBox( vxT( 'Delete this account?' ), vxT( 'The account and saved address are deleted for good. Orders are kept (tax law), but no longer linked to an account. This cannot be undone.' ), vxT( 'Delete account' ), true ).then( function ( y ) { if ( y ) { custAct( 'delete' ); } } ); return true; }
		return false;
	}

	/* ============================================================== DESIGN
	 * A template (complete set of choices) plus any choice on top; '' keeps
	 * the template's value. A live preview draws the real grid as you choose. */
	var DS_CLASSES = [
		[ '.vxs-grid', 'product grid' ], [ '.vxs-card', 'one product' ], [ '.vxs-card-media', 'photo' ], [ '.vxs-card-body', 'text under the photo' ],
		[ '.vxs-card-cat', 'category' ], [ '.vxs-card-t', 'product name' ], [ '.vxs-card-text', 'short description' ], [ '.vxs-price', 'price' ],
		[ '.vxs-taxnote', 'incl. VAT note' ], [ '.vxs-btn', 'buttons' ], [ '.vxs-more-link', 'text link' ], [ '.vxs-badge', 'badge (New, Sale)' ],
		[ '.vxs-cats', 'category buttons' ], [ '.vxs-product', 'product page' ], [ '.vxs-gallery', 'product photos' ], [ '.vxs-product-side', 'buy column' ],
		[ '.vxs-buy-price', 'big price' ], [ '.vxs-chip', 'option chips' ], [ '.vxs-acc', 'fold-out details' ], [ '.vxs-cartpage', 'cart page' ],
		[ '.vxs-checkout', 'checkout' ], [ '.vxs-co-card', 'checkout boxes' ], [ '.vxs-cartbtn', 'cart button' ], [ '.vxs-count', 'cart count' ], [ '.vxs-drawer-p', 'mini cart' ]
	];
	var DS_VARS = [ '--vxs-accent', '--vxs-ink', '--vxs-ink-2', '--vxs-ink-3', '--vxs-line', '--vxs-soft', '--vxs-surface', '--vxs-bg', '--vxs-r', '--vxs-r-sm', '--vxs-btn-r', '--vxs-gap', '--vxs-cols' ];
	var DS_OLD = { gallery:'atelier', boutique:'candy', minimal:'maison', editorial:'journal', night:'noir' };
	function dsStack( k ) {
		var f = ( C.fonts || {} )[ k ]; if ( ! f ) { return 'inherit'; }
		return '"' + f[ 0 ] + '",' + ( 'serif' === f[ 1 ] ? 'Georgia,serif' : ( 'mono' === f[ 1 ] ? 'ui-monospace,monospace' : 'system-ui,sans-serif' ) );
	}
	function dsFontName( k ) { var f = ( C.fonts || {} )[ k ]; return f ? f[ 0 ] : vxT( 'Site font' ); }
	/* A small storefront drawn with the template's own colours, shapes and type. */
	function dsMock( v ) {
		var c = function ( x, d ) { return /^#[0-9a-f]{3,8}$/i.test( x || '' ) ? x : d; };
		var bg = c( v.bg, '#ffffff' ), ink = c( v.ink, '#18181b' ), soft = c( v.soft, '#f1f1f3' ), sur = c( v.surface, bg ), line = c( v.line, '#e4e4e7' ), acc = c( v.accent, '#18181b' ), on = c( v.accent_ink, '#ffffff' );
		var r = Math.min( +v.radius || 0, 14 ), br = 'pill' === v.btn_shape ? 99 : ( 'rounded' === v.btn_shape ? Math.max( 3, r / 2 ) : 0 );
		var list = 'list' === v.layout, k = v.card || 'bare', pastel = [ '#fde2e4', '#e2ece9', '#fff1c1' ];
		var tiles = [ 0, 1, 2 ].slice( 0, list ? 2 : 3 ).map( function ( i ) {
			var ph = 'pastel' === v.img_bg ? pastel[ i ] : ( 'none' === v.img_bg ? sur : soft );
			var obj = 'contain' === v.fit ? '<i class="vsa-mk2-o" style="background:' + ink + '"></i>' : '';
			var box = 'boxed' === k || 'framed' === k ? 'background:' + sur + ';' + ( 'framed' === k ? 'box-shadow:0 0 0 1px ' + line + ';' : '' ) + 'border-radius:' + ( 'framed' === k ? 0 : r / 1.6 ) + 'px;padding:5px;' : '';
			var btn = 'button' === v.button ? '<u style="background:' + acc + ';border-radius:' + br + 'px"></u>' : ( 'link' === v.button ? '<u class="is-link" style="border-color:' + ink + '"></u>' : '' );
			return '<span class="vsa-mk2-c' + ( 'center' === v.align ? ' is-c' : '' ) + '" style="' + box + '"><span class="vsa-mk2-ph" style="background:' + ph + ';border-radius:' + ( 'framed' === k ? 0 : r / 2 ) + 'px">' + obj + '</span>' +
				'<em style="background:' + ink + '"></em><em class="is-p" style="background:' + ( 'bold' === v.price ? ink : c( v.muted, ink ) ) + '"></em>' + btn + '</span>';
		} ).join( '' );
		return '<span class="vsa-mk2" style="background:' + bg + ';color:' + ink + '" aria-hidden="true">' +
			'<span class="vsa-mk2-top" style="border-color:' + line + '"><b style="font-family:' + dsStack( v.font_h ) + ';font-weight:' + ( v.h_weight || 600 ) + ';' + ( 'upper' === v.h_case ? 'text-transform:uppercase;letter-spacing:.04em;' : '' ) + '">Aa</b><span class="vsa-mk2-nav"><i style="background:' + ink + '"></i><i style="background:' + ink + '"></i></span><i class="vsa-mk2-dot" style="background:' + acc + '"></i></span>' +
			'<span class="vsa-mk2-g' + ( list ? ' is-list' : '' ) + '">' + tiles + '</span></span>';
	}
	function dsSwatches( v ) {
		return '<span class="vsa-sw" aria-hidden="true">' + [ v.bg, v.surface !== v.bg ? v.surface : v.soft, v.ink, v.accent, v.sale ].filter( function ( x ) { return /^#/.test( x || '' ); } ).map( function ( x ) { return '<i style="background:' + x + '"></i>'; } ).join( '' ) + '</span>';
	}
	function dsCur( s ) { var D = C.designs || {}, k = DS_OLD[ s.design ] || s.design; return D[ k ] ? k : 'velox'; }
	function dsVal( k ) { var v = SD[ 'd_' + k ]; return ( v === undefined || v === null || '' === String( v ) ) ? '' : String( v ); }
	function dsSeg( k, opts, tpl ) {
		var t = tpl[ k ], name = ( opts.filter( function ( o ) { return String( o[ 0 ] ) === String( t ); } )[ 0 ] || [ 0, t ] )[ 1 ];
		return seg( 'd_' + k, dsVal( k ), [ [ '', vxT( 'Template' ) + ( name ? ' · ' + name : '' ) ] ].concat( opts ) );
	}
	function dsSel( k, opts, tpl ) {
		var t = tpl[ k ], name = ( opts.filter( function ( o ) { return String( o[ 0 ] ) === String( t ); } )[ 0 ] || [ 0, t ] )[ 1 ];
		return sel( 'd_' + k, dsVal( k ), [ [ '', vxT( 'Template' ) + ( name ? ' · ' + name : '' ) ] ].concat( opts ) );
	}
	function dsColor( k, label, tpl, hint ) { return field( label, colorF( 'd_' + k, dsVal( k ), tpl[ k ] || '', true ), hint ); }
	function dsEff( k, tpl ) { var v = dsVal( k ); return '' !== v ? v : String( tpl[ k ] || '' ); }
	function dsFonts( k, tpl ) {
		var groups = { sans:vxT( 'Sans serif' ), serif:vxT( 'Serif' ), mono:vxT( 'Monospace' ) }, F = C.fonts || {}, t = tpl[ k ];
		var h = '<select class="vsa-in" data-k="d_' + k + '"><option value=""' + ( dsVal( k ) ? '' : ' selected' ) + '>' + esc( vxT( 'Template' ) + ' · ' + dsFontName( t ) ) + '</option>';
		Object.keys( groups ).forEach( function ( g ) {
			h += '<optgroup label="' + esc( groups[ g ] ) + '">' + Object.keys( F ).filter( function ( x ) { return F[ x ][ 1 ] === g; } ).map( function ( x ) { return '<option value="' + esc( x ) + '"' + ( dsVal( k ) === x ? ' selected' : '' ) + '>' + esc( F[ x ][ 0 ] ) + '</option>'; } ).join( '' ) + '</optgroup>';
		} );
		return h + '</select>';
	}
	/* Picking a template starts clean: choices made on top of the old one are
	 * cleared (otherwise "Noir" stays white), with Undo for a change of mind. */
	function dsPick( k ) {
		var before = {}, had = false;
		Object.keys( SD ).forEach( function ( x ) { if ( /^d_/.test( x ) ) { before[ x ] = SD[ x ]; if ( '' !== String( SD[ x ] == null ? '' : SD[ x ] ) ) { had = true; } } } );
		before.card_ratio = SD.card_ratio; before.design = SD.design; if ( SD.card_ratio ) { had = true; }
		if ( k === dsCur( SD ) && ! had ) { return; }
		SD.design = k; SD.card_ratio = '';
		Object.keys( before ).forEach( function ( x ) { if ( /^d_/.test( x ) ) { SD[ x ] = ''; } } );
		redrawSettings();
		var name = ( ( C.designs || {} )[ k ] || {} ).name || k;
		if ( had ) {
			toast( vxT( '%s applied — your own changes to the old template were reset.' ).replace( '%s', name ), false, { label:vxT( 'Undo' ), fn:function () { Object.keys( before ).forEach( function ( x ) { SD[ x ] = before[ x ]; } ); redrawSettings(); } } );
		} else {
			toast( vxT( '%s applied. Save to put it live.' ).replace( '%s', name ) );
		}
	}
	function dsBody( s ) {
		var D = C.designs || {}, cur = dsCur( s ), tpl = ( D[ cur ] || { v:{} } ).v;
		var own = Object.keys( s ).filter( function ( x ) { return /^d_/.test( x ) && '' !== String( s[ x ] == null ? '' : s[ x ] ); } ).length;
		var tiles = Object.keys( D ).map( function ( k ) {
			var t = D[ k ], on = k === cur;
			return '<button type="button" class="vsa-tpl' + ( on ? ' is-on' : '' ) + '" data-act="d-tpl" data-v="' + esc( k ) + '" aria-pressed="' + on + '">' + dsMock( t.v ) +
				'<span class="vsa-tpl-h"><b>' + esc( t.name ) + '</b>' + ( on ? '<span class="vsa-chip is-ok">' + vxT( 'In use' ) + '</span>' : dsSwatches( t.v ) ) + '</span>' +
				'<small>' + esc( t.desc ) + '</small><span class="vsa-tpl-f">' + ic( 'file', 12 ) + esc( t.fonts ) + '<i>' + esc( t.mood ) + '</i></span></button>';
		} ).join( '' );
		var yn = [ [ '1', vxT( 'Show' ) ], [ '0', vxT( 'Hide' ) ] ];
		var fit = dsEff( 'fit', tpl ), kind = dsEff( 'card', tpl );
		setTimeout( dsPreviewSoon, 0 );
		return card( vxT( 'Template' ), '<div class="vsa-tpls">' + tiles + '</div>' +
				( own ? '<div class="vsa-tpl-own">' + ic( 'brush', 14 ) + '<span>' + vxT( '%d choices changed on top of the template.' ).replace( '%d', own ) + '</span><button type="button" class="vsa-link" data-act="d-clear">' + vxT( 'Back to the pure template' ) + '</button></div>' : '<p class="vsa-hint">' + vxT( 'A template sets every choice below. Change any of them on top — “Template” keeps its value. Picking another template starts fresh.' ) + '</p>' ) ) +
			card( vxT( 'Preview' ), '<div class="vsa-dprev"><iframe id="vsa-dprev" title="' + esc( vxT( 'Preview' ) ) + '"></iframe></div><p class="vsa-hint">' + vxT( 'Your real products, drawn with the choices on this page. Save to put them live.' ) + '</p>', { cls:'vsa-dprev-card', right:'<div class="vsa-seg vsa-dprev-dev">' + [ [ 1280, vxT( 'Desktop' ) ], [ 820, vxT( 'Tablet' ) ], [ 390, vxT( 'Phone' ) ] ].map( function ( o ) { return '<button type="button" data-act="dprev-dev" data-v="' + o[ 0 ] + '" class="' + ( dsDev === o[ 0 ] ? 'is-on' : '' ) + '">' + o[ 1 ] + '</button>'; } ).join( '' ) + '</div>' } ) +
			card( vxT( 'Colours' ),
				'<div class="vsa-fr">' + dsColor( 'bg', vxT( 'Page' ), tpl ) + dsColor( 'surface', vxT( 'Cards' ), tpl ) + dsColor( 'soft', vxT( 'Photo backdrop' ), tpl ) + '</div>' +
				'<div class="vsa-fr">' + dsColor( 'ink', vxT( 'Text' ), tpl ) + dsColor( 'muted', vxT( 'Quiet text' ), tpl ) + dsColor( 'line', vxT( 'Lines' ), tpl ) + '</div>' +
				'<div class="vsa-fr">' + dsColor( 'accent', vxT( 'Buttons' ), tpl ) + dsColor( 'accent_ink', vxT( 'Text on buttons' ), tpl ) + dsColor( 'sale', vxT( 'Sale price' ), tpl ) + '</div>' +
				field( vxT( 'Page background' ), dsSeg( 'page_bg', [ [ '1', vxT( 'Shop pages in the template colour' ) ], [ '0', vxT( 'Keep the site’s background' ) ] ], tpl ), vxT( 'For dark or tinted templates: the whole page behind the shop takes the colour, not just the shop parts.' ) ) ) +
			card( vxT( 'Typography' ),
				'<div class="vsa-fr">' + field( vxT( 'Headings' ), dsFonts( 'font_h', tpl ) ) + field( vxT( 'Text' ), dsFonts( 'font_b', tpl ) ) + '</div>' +
				'<div class="vsa-fr">' + field( vxT( 'Heading weight' ), dsSeg( 'h_weight', [ [ '400', vxT( 'Regular' ) ], [ '500', vxT( 'Medium' ) ], [ '600', vxT( 'Semi-bold' ) ], [ '700', vxT( 'Bold' ) ] ], tpl ) ) +
				field( vxT( 'Headings in' ), dsSeg( 'h_case', [ [ 'none', vxT( 'Normal case' ) ], [ 'upper', vxT( 'Capitals' ) ] ], tpl ) ) + '</div>' +
				'<div class="vsa-fr">' + field( vxT( 'Letter spacing in headings' ), dsSel( 'h_track', [ [ '-0.03', vxT( 'Tight' ) ], [ '-0.015', vxT( 'A little tight' ) ], [ '0', vxT( 'Normal' ) ], [ '0.02', vxT( 'A little wide' ) ], [ '0.06', vxT( 'Wide' ) ] ], tpl ) ) +
				field( vxT( 'Small labels' ), dsSeg( 'label', [ [ 'none', vxT( 'Normal' ) ], [ 'upper', vxT( 'Spaced capitals' ) ] ], tpl ), vxT( 'Category, badges and option names.' ) ) + '</div>' +
				'<p class="vsa-hint">' + vxT( 'Fonts are downloaded once and served from your own site — no visitor ever connects to Google.' ) + '</p>' ) +
			card( vxT( 'Product grid' ),
				field( vxT( 'Layout' ), dsSeg( 'layout', [ [ 'grid', vxT( 'Grid' ) ], [ 'masonry', vxT( 'Masonry' ) ], [ 'list', vxT( 'List' ) ], [ 'row', vxT( 'Scroll row' ) ] ], tpl ) ) +
				'<div class="vsa-fr">' + field( vxT( 'Columns on desktop' ), dsSel( 'cols', [ 1, 2, 3, 4, 5, 6 ].map( function ( n ) { return [ String( n ), String( n ) ]; } ), tpl ) ) +
				field( vxT( 'on tablets' ), dsSel( 'cols_t', [ 1, 2, 3, 4 ].map( function ( n ) { return [ String( n ), String( n ) ]; } ), tpl ) ) +
				field( vxT( 'on phones' ), dsSel( 'cols_m', [ [ '1', '1' ], [ '2', '2' ] ], tpl ) ) + '</div>' +
				'<div class="vsa-fr">' + field( vxT( 'Space between columns' ), inp( 'd_gap', dsVal( 'gap' ), { ph:tpl.gap, suffix:'px', mode:'numeric' } ) ) +
				field( vxT( 'Space between rows' ), inp( 'd_gap_row', dsVal( 'gap_row' ), { ph:tpl.gap_row, suffix:'px', mode:'numeric' } ) ) + '</div>' +
				field( vxT( 'Each product as' ), dsSeg( 'card', [ [ 'bare', vxT( 'Photo and text' ) ], [ 'tile', vxT( 'On a tile' ) ], [ 'boxed', vxT( 'A card' ) ], [ 'framed', vxT( 'Framed' ) ], [ 'overlay', vxT( 'Text on the photo' ) ], [ 'split', vxT( 'Side by side' ) ] ], tpl ) ) +
				'<div class="vsa-fr">' + field( vxT( 'Photo shape' ), dsSel( 'ratio', [ [ '4/5', '4:5 ' + vxT( 'portrait' ) ], [ '3/4', '3:4' ], [ '2/3', '2:3' ], [ '1/1', '1:1 ' + vxT( 'square' ) ], [ '4/3', '4:3 ' + vxT( 'landscape' ) ], [ 'natural', vxT( 'Their own shape' ) ] ], tpl ) ) +
				field( vxT( 'Photo in its frame' ), dsSeg( 'fit', [ [ 'cover', vxT( 'Fill' ) ], [ 'contain', vxT( 'Whole photo' ) ] ], tpl ) ) + '</div>' +
				'<div class="vsa-fr">' + field( vxT( 'Behind the photo' ), dsSeg( 'img_bg', [ [ 'soft', vxT( 'Backdrop colour' ) ], [ 'surface', vxT( 'Card colour' ) ], [ 'pastel', vxT( 'Pastels' ) ], [ 'none', vxT( 'Nothing' ) ] ], tpl ) ) +
				( 'contain' === fit || 'tile' === kind ? field( vxT( 'Space around the photo' ), inp( 'd_img_pad', dsVal( 'img_pad' ), { ph:tpl.img_pad, suffix:'%', mode:'numeric' } ) ) : '' ) + '</div>' +
				field( vxT( 'On hover' ), dsSeg( 'hover', [ [ 'swap', vxT( 'Second photo' ) ], [ 'fade', vxT( 'Fade to second' ) ], [ 'zoom', vxT( 'Zoom' ) ], [ 'lift', vxT( 'Lift' ) ], [ 'shadow', vxT( 'Shadow' ) ], [ 'glow', vxT( 'Glow' ) ], [ 'none', vxT( 'Nothing' ) ] ], tpl ) ) +
				'<div class="vsa-fr">' + field( vxT( 'Text' ), dsSeg( 'align', [ [ 'left', vxT( 'Left' ) ], [ 'center', vxT( 'Centred' ) ] ], tpl ) ) +
				field( vxT( 'Category above the name' ), dsSeg( 'show_cat', yn, tpl ) ) + field( vxT( 'Short description' ), dsSeg( 'show_text', yn, tpl ) ) + '</div>' +
				'<div class="vsa-fr">' + field( vxT( 'Price' ), dsSeg( 'price', [ [ 'regular', vxT( 'Regular' ) ], [ 'bold', vxT( 'Bold' ) ], [ 'mono', vxT( 'Typewriter' ) ] ], tpl ) ) +
				field( vxT( 'Buy button' ), dsSeg( 'button', [ [ 'button', vxT( 'Button' ) ], [ 'icon', vxT( 'Icon' ) ], [ 'link', vxT( 'Text link' ) ], [ 'none', vxT( 'None — the card links' ) ] ], tpl ) ) + '</div>' +
				'<div class="vsa-fr">' + field( vxT( 'Badges (Sale, New, Sold)' ), dsSeg( 'badge', [ [ 'pill', vxT( 'Pill' ) ], [ 'square', vxT( 'Solid block' ) ], [ 'text', vxT( 'Just text' ) ] ], tpl ) ) +
				field( vxT( 'Badge position' ), dsSeg( 'badge_pos', [ [ 'tl', vxT( 'Top left' ) ], [ 'tr', vxT( 'Top right' ) ] ], tpl ) ) + '</div>' ) +
			card( vxT( 'Buttons' ),
				'<div class="vsa-fr">' + field( vxT( 'Button shape' ), dsSeg( 'btn_shape', [ [ 'pill', vxT( 'Pill' ) ], [ 'rounded', vxT( 'Rounded' ) ], [ 'square', vxT( 'Square' ) ] ], tpl ) ) +
				field( vxT( 'Buttons in the grid' ), dsSeg( 'btn_style', [ [ 'solid', vxT( 'Filled' ) ], [ 'outline', vxT( 'Outline' ) ] ], tpl ) ) + '</div>' +
				'<div class="vsa-fr">' + field( vxT( 'Button text' ), dsSeg( 'btn_case', [ [ 'none', vxT( 'Normal' ) ], [ 'upper', vxT( 'Capitals' ) ] ], tpl ) ) +
				field( vxT( 'Corner radius' ), inp( 'd_radius', dsVal( 'radius' ), { ph:tpl.radius, suffix:'px', mode:'numeric' } ), vxT( 'Cards, photos and fields.' ) ) + '</div>' ) +
			card( vxT( 'Product page' ),
				field( vxT( 'Layout' ), dsSeg( 'product', [ [ 'split', vxT( 'Photos left' ) ], [ 'split-reverse', vxT( 'Photos right' ) ], [ 'wide', vxT( 'Big photos' ) ], [ 'stacked', vxT( 'Photos on top' ) ] ], tpl ) ) +
				field( vxT( 'Photos' ), dsSeg( 'gallery', [ [ 'below', vxT( 'Thumbnails below' ) ], [ 'side', vxT( 'Thumbnails at the side' ) ], [ 'stack', vxT( 'All stacked' ) ], [ 'grid', vxT( 'Photo grid' ) ] ], tpl ) ) +
				'<div class="vsa-fr">' + field( vxT( 'While scrolling' ), dsSeg( 'sticky', [ [ '1', vxT( 'Stays in view' ) ], [ '0', vxT( 'Scrolls along' ) ] ], tpl ) ) +
				field( vxT( 'Details' ), dsSeg( 'details', [ [ 'sections', vxT( 'All open' ) ], [ 'accordion', vxT( 'Fold out' ) ] ], tpl ) ) + '</div>' +
				'<p class="vsa-hint">' + vxT( 'Buy bar, full-screen photos, related products and reasons to buy are under Storefront.' ) + ' <a href="#/settings/storefront">' + vxT( 'Open Storefront' ) + '</a></p>' ) +
			card( vxT( 'Cart & checkout' ),
				'<div class="vsa-fr">' + field( vxT( 'Checkout' ), dsSeg( 'checkout', [ [ 'two', vxT( 'Two columns' ) ], [ 'one', vxT( 'One column' ) ] ], tpl ) ) +
				field( vxT( 'Cart icon' ), dsSeg( 'cart_icon', [ [ 'bag', vxT( 'Bag' ) ], [ 'cart', vxT( 'Cart' ) ], [ 'basket', vxT( 'Basket' ) ] ], tpl ) ) + '</div>' +
				'<div class="vsa-fr">' + field( vxT( 'Items in the cart' ), dsSeg( 'cart_count', [ [ 'badge', vxT( 'Number on the icon' ) ], [ 'dot', vxT( 'A dot' ) ], [ 'inline', vxT( 'Number next to it' ) ] ], tpl ) ) +
				field( vxT( 'Cart total next to the icon' ), dsSeg( 'cart_total', yn, tpl ) ) + '</div>' ) +
			card( vxT( 'Your own CSS' ),
				field( '', area( 'custom_css', s.custom_css || '', 10, '.vxs-card-t {\n\tfont-family: Georgia, serif;\n}' ).replace( 'class="vsa-in"', 'class="vsa-in vsa-mono vsa-css"' ), vxT( 'Loaded after the shop’s own styles, on every page with shop parts. The same classes can be styled in Velox Builder too.' ) ) +
				'<details class="vsa-ref"><summary>' + vxT( 'Every part you can style' ) + '</summary><div class="vsa-ref-g">' +
				DS_CLASSES.map( function ( c ) { return '<button type="button" class="vsa-ref-i" data-act="css-ins" data-v="' + esc( c[ 0 ] ) + '"><code>' + esc( c[ 0 ] ) + '</code><span>' + esc( vxT( c[ 1 ] ) ) + '</span></button>'; } ).join( '' ) +
				'</div><p class="vsa-hint">' + vxT( 'Variables for colours and sizes:' ) + ' ' + DS_VARS.map( function ( v ) { return '<code>' + v + '</code>'; } ).join( ' ' ) + '</p></details>' );
	}
	/* Settings → Storefront: how the shop behaves, not how it looks. */
	function sfBody( s ) {
		var on1 = function ( k ) { return '1' === String( s[ k ] ); };
		return card( vxT( 'Adding to the cart' ),
				field( vxT( 'After “Add to cart”' ), seg( 'after_add', s.after_add || 'drawer', [ [ 'drawer', vxT( 'Open the mini cart' ) ], [ 'stay', vxT( 'Stay, show a short note' ) ], [ 'cart', vxT( 'Go to the cart page' ) ] ] ) ) +
				tog( 'buy_bar', on1( 'buy_bar' ), vxT( 'Buy bar on product pages' ), vxT( 'Price and button stay in reach once the visitor scrolls past them — at the bottom on phones, floating on larger screens.' ) ) ) +
			card( vxT( 'Shop page' ),
				'<div class="vsa-fr">' + field( vxT( 'Products before “Show more”' ), sel( 'per_page', String( s.per_page || 24 ), [ 12, 16, 20, 24, 32, 48, 60 ].map( function ( n ) { return [ String( n ), String( n ) ]; } ) ) ) +
				field( vxT( '“New” badge' ), sel( 'new_days', String( s.new_days || 0 ), [ [ '0', vxT( 'Off' ) ], [ '7', vxT( 'First 7 days' ) ], [ '14', vxT( 'First 14 days' ) ], [ '30', vxT( 'First 30 days' ) ], [ '60', vxT( 'First 60 days' ) ] ] ), vxT( 'Sale, sold and few-left badges come first.' ) ) + '</div>' +
				tog( 'grid_sort', on1( 'grid_sort' ), vxT( 'Sort menu' ), vxT( 'Recommended, newest, price up or down, name.' ) ) +
				tog( 'grid_search', on1( 'grid_search' ), vxT( 'Search field' ), vxT( 'Worth it from about 30 products.' ) ) +
				'<p class="vsa-hint">' + vxT( 'On the shop page the product count, categories, sorting and search sit above the grid. Every choice stays in the address, so Back and shared links land in the same place.' ) + '</p>' ) +
			card( vxT( 'Product pages' ),
				tog( 'zoom', on1( 'zoom' ), vxT( 'Photos open full screen' ), vxT( 'Click or tap a photo; arrows, swipe and Esc work.' ) ) +
				tog( 'related', on1( 'related' ), vxT( 'More from the same category' ), vxT( 'Under products without hand-picked suggestions.' ) ) +
				field( vxT( 'Reasons to buy' ), area( 'usp', s.usp || '', 4, [ vxT( 'Free shipping from €50' ), vxT( '30 days to return' ), vxT( 'Secure payment with PayPal and card' ) ].join( '\n' ) ), vxT( 'One per line, up to six, under the buy button. The icon follows the words: shipping, returns, payment, gift, handmade.' ) ) ) +
			card( vxT( 'Notice bar' ),
				tog( 'notice_on', on1( 'notice_on' ), vxT( 'A slim bar across the top of every page' ), vxT( 'Free shipping, a sale, holidays. Visitors can close it.' ) ) +
				( on1( 'notice_on' ) ? field( vxT( 'Message' ), area( 'notice_text', s.notice_text || '', 3, vxT( 'Free shipping in Germany from €50' ) ), vxT( 'Several lines take turns every five seconds.' ) ) + field( vxT( 'Link' ), inp( 'notice_link', s.notice_link || '', { ph:'https://…', type:'url' } ), vxT( 'Optional — the whole message becomes a link.' ) ) : '' ) );
	}
	var dsTimer = 0, dsSeq = 0, dsDev = 1280;
	// The preview renders at a real device width and is scaled down to fit.
	function dsFit() {
		var f = document.getElementById( 'vsa-dprev' ); if ( ! f ) { return; }
		var box = f.parentNode, w = box.clientWidth, h = box.clientHeight, sc = Math.min( 1, w / dsDev );
		f.style.width = dsDev + 'px';
		f.style.height = Math.round( h / sc ) + 'px';
		f.style.transform = 'scale(' + sc + ')';
		f.style.left = Math.max( 0, Math.round( ( w - dsDev * sc ) / 2 ) ) + 'px';
	}
	window.addEventListener( 'resize', dsFit );
	function dsPreviewSoon() {
		clearTimeout( dsTimer );
		dsTimer = setTimeout( function () {
			var f = document.getElementById( 'vsa-dprev' ); if ( ! f || ! SD ) { return; }
			var pick = { design:SD.design, card_ratio:SD.card_ratio, accent:SD.accent, custom_css:SD.custom_css };
			Object.keys( SD ).forEach( function ( k ) { if ( /^d_/.test( k ) ) { pick[ k ] = SD[ k ]; } } );
			var n = ++dsSeq;
			f.parentNode.classList.add( 'is-busy' );
			api( 'design_preview', { settings:pick } ).then( function ( r ) {
				if ( n !== dsSeq ) { return; }
				var fr = document.getElementById( 'vsa-dprev' ); if ( ! fr ) { return; }
				fr.srcdoc = r.html; fr.parentNode.classList.remove( 'is-busy' ); dsFit();
			} ).catch( function () { var fr = document.getElementById( 'vsa-dprev' ); if ( fr ) { fr.parentNode.classList.remove( 'is-busy' ); } } );
		}, 350 );
	}

	/* ============================================================ SETTINGS */

	var SECTIONS = [
		[ 'store', 'store', vxT( 'Store' ) ], [ 'design', 'brush', vxT( 'Design' ) ], [ 'storefront', 'layers', vxT( 'Storefront' ) ], [ 'taxes', 'receipt', vxT( 'Taxes' ) ], [ 'shipping', 'truck', vxT( 'Shipping' ) ], [ 'payments', 'card', vxT( 'Payments' ) ],
		[ 'checkout', 'bag', vxT( 'Checkout' ) ], [ 'emails', 'mail', vxT( 'Emails' ) ], [ 'pages', 'scale', vxT( 'Pages & legal' ) ], [ 'stock', 'box', vxT( 'Stock & products' ) ]
	];
	var SD = null, SDclean = '';
	function settings() {
		SD = JSON.parse( JSON.stringify( S ) );
		if ( DS_OLD[ SD.design ] ) { SD.design = DS_OLD[ SD.design ]; if ( ! SD.d_accent && SD.accent ) { SD.d_accent = SD.accent; } }
		SDclean = JSON.stringify( SD ); dirty = false;
		var sec = route.id || 'store';
		var h = head( vxT( 'Settings' ), '', '' ) + '<div class="vsa-set"><nav class="vsa-setnav">' + SECTIONS.map( function ( s ) { return '<a href="#/settings/' + s[ 0 ] + '" class="' + ( s[ 0 ] === sec ? 'is-on' : '' ) + '">' + ic( s[ 1 ], 15 ) + esc( s[ 2 ] ) + '</a>'; } ).join( '' ) + '</nav><div class="vsa-setb" id="vsa-setb">' + settingsBody( sec ) + '</div></div>' +
			'<div class="vsa-savebar" id="vsa-savebar"><span class="vsa-savebar-t">' + ic( 'clock', 14 ) + vxT( 'Unsaved changes' ) + '</span><button type="button" class="vsa-btn vsa-btn-ghost" data-act="s-reset">' + vxT( 'Discard' ) + '</button><button type="button" class="vsa-btn vsa-btn-pri" data-act="s-save">' + ic( 'check', 15 ) + vxT( 'Save' ) + '</button></div>';
		main().innerHTML = h;
	}
	function sdirty() { dirty = JSON.stringify( SD ) !== SDclean; var b = document.getElementById( 'vsa-savebar' ); if ( b ) { b.classList.toggle( 'is-dirty', dirty ); } if ( 'design' === route.id ) { dsPreviewSoon(); } }
	function redrawSettings() { var b = document.getElementById( 'vsa-setb' ); if ( b ) { b.innerHTML = settingsBody( route.id || 'store' ); } sdirty(); }
	function pageSel( k, label, hint ) {
		var opts = [ [ '0', '— ' + vxT( 'none' ) + ' —' ] ].concat( ( C.pages || [] ).map( function ( p ) { return [ String( p.id ), p.title ]; } ) );
		return field( label, '<div class="vsa-row">' + sel( 'page_' + k, String( SD[ 'page_' + k ] || 0 ), opts ) + ( +SD[ 'page_' + k ] ? '<a class="vsa-iconbtn" href="' + esc( pageUrlSD( k ) ) + '" target="_blank" rel="noopener" aria-label="' + esc( vxT( 'Open' ) ) + '">' + ic( 'ext', 14 ) + '</a>' : '' ) + '</div>', hint );
	}
	function pageUrlSD( k ) { var id = +SD[ 'page_' + k ]; var p = ( C.pages || [] ).filter( function ( x ) { return x.id === id; } )[ 0 ]; return p ? p.url : '#'; }
	function on( k ) { return !! SD[ k ] && '0' !== String( SD[ k ] ); }
	function settingsBody( sec ) {
		var s = SD, cs = Object.keys( C.countries ).map( function ( k ) { return [ k, C.countries[ k ] ]; } );
		switch ( sec ) {
			case 'store':
				return card( vxT( 'Your business' ), '<div class="vsa-fr">' + field( vxT( 'Shop name' ), inp( 'store_name', s.store_name ) ) + field( vxT( 'Email for customers' ), inp( 'store_email', s.store_email, { type:'email' } ), vxT( 'Replies to order emails go here.' ) ) + '</div>' +
					field( vxT( 'Business address' ), area( 'store_address', s.store_address, 3, vxT( 'Company name, street, postcode and town — printed on invoices.' ) ) ) +
					'<div class="vsa-fr">' + field( vxT( 'VAT ID' ), inp( 'store_vat_id', s.store_vat_id, { ph:'DE123456789' } ) ) + field( vxT( 'Tax number' ), inp( 'store_tax_no', s.store_tax_no, { ph:vxT( 'optional' ) } ) ) + '</div>' +
					field( vxT( 'Country of your business' ), sel( 'store_country', s.store_country, cs ) ) ) +
					card( vxT( 'Look' ), '<div class="vsa-fr">' + field( vxT( 'Logo for emails and invoices' ), '<div class="vsa-logo">' + ( s.logo_url ? '<img src="' + esc( s.logo_url ) + '" alt="">' : '' ) + '<button type="button" class="vsa-btn vsa-btn-sm vsa-btn-ghost" data-act="s-logo">' + ( s.logo_url ? vxT( 'Change' ) : vxT( 'Choose' ) ) + '</button>' + ( s.logo_url ? '<button type="button" class="vsa-link" data-act="s-logo-x">' + vxT( 'Remove' ) + '</button>' : '' ) + '</div>' ) +
					field( vxT( 'Accent colour' ), colorF( 'accent', s.accent, '#0a6f9f' ), vxT( 'Buttons and links in emails and on invoices.' ) ) + '</div>' +
					'<p class="vsa-hint">' + vxT( 'The storefront’s colours, fonts and layout are under Design.' ) + ' <a href="#/settings/design">' + vxT( 'Open Design' ) + '</a></p>' ) +
					card( vxT( 'Money' ), '<div class="vsa-fr">' + field( vxT( 'Currency' ), sel( 'currency', s.currency, [ [ 'EUR', 'Euro (EUR)' ], [ 'CHF', 'Franken (CHF)' ], [ 'GBP', 'Pound (GBP)' ], [ 'USD', 'Dollar (USD)' ] ] ) ) + field( vxT( 'Symbol' ), inp( 'symbol', s.symbol, { cls:'vsa-in-sm' } ) ) + field( vxT( 'Position' ), sel( 'symbol_pos', s.symbol_pos, [ [ 'right', '49,90 ' + s.symbol ], [ 'left', s.symbol + ' 49,90' ] ] ) ) + '</div>' );
			case 'design':
				return dsBody( s );
			case 'storefront':
				return sfBody( s );
			case 'taxes':
				return card( vxT( 'VAT' ), seg( 'tax_mode', s.tax_mode, [ [ 'standard', vxT( 'Prices include VAT' ), 'receipt' ], [ 'small', vxT( 'Small business — no VAT' ), 'shield' ] ] ) +
					( 'standard' === s.tax_mode
						? '<div class="vsa-fr">' + field( vxT( 'Standard rate' ), inp( 'rate_standard', dec( s.rate_standard ), { suffix:'%', mode:'decimal' } ) ) + field( vxT( 'Reduced rate' ), inp( 'rate_reduced', dec( s.rate_reduced ), { suffix:'%', mode:'decimal' } ), vxT( 'Books, food, art …' ) ) + '</div><p class="vsa-hint">' + vxT( 'Prices are entered and shown including VAT. Shipping is taxed at the standard rate. Invoices list the VAT per rate.' ) + '</p>'
						: field( vxT( 'Note on invoices and at checkout' ), area( 'small_note', s.small_note, 2 ) ) + '<p class="vsa-hint">' + vxT( 'Kleinunternehmer (§ 19 UStG): no VAT is charged or shown. If you pass €25,000 last year or €100,000 this year, switch back to “Prices include VAT”.' ) + '</p>' ) );
			case 'shipping':
				var zones = ( s.zones || [] ).map( function ( z, zi ) {
					return '<div class="vsa-zone"><div class="vsa-zone-h"><input class="vsa-in vsa-zone-n" data-z="' + zi + '" data-zk="name" value="' + esc( z.name ) + '" placeholder="' + esc( vxT( 'Zone name' ) ) + '"><button type="button" class="vsa-iconbtn" data-zdel="' + zi + '" aria-label="' + esc( vxT( 'Delete zone' ) ) + '">' + ic( 'trash', 14 ) + '</button></div>' +
						field( vxT( 'Countries' ), '<div class="vsa-tags">' + ( z.countries || [] ).map( function ( c, ci ) { return '<span class="vsa-tag">' + esc( C.countries[ c ] || c ) + '<button type="button" data-zc-del="' + zi + ':' + ci + '" aria-label="' + esc( vxT( 'Remove' ) ) + '">×</button></span>'; } ).join( '' ) + '<select class="vsa-tag-sel" data-zc-add="' + zi + '"><option value="">' + ( ( z.countries || [] ).length ? '+ ' + vxT( 'country' ) : vxT( 'All other countries' ) + ' — + ' + vxT( 'country' ) ) + '</option>' + cs.map( function ( o ) { return '<option value="' + o[ 0 ] + '">' + esc( o[ 1 ] ) + '</option>'; } ).join( '' ) + '</select></div>' ) +
						'<table class="vsa-rates"><thead><tr><th>' + vxT( 'Method' ) + '</th><th>' + vxT( 'Cost' ) + '</th><th>' + vxT( 'Free from' ) + '</th><th>' + vxT( 'Working days' ) + '</th><th></th></tr></thead><tbody>' +
						( z.rates || [] ).map( function ( r, ri ) { return '<tr><td><input class="vsa-in vsa-in-sm" data-z="' + zi + '" data-r="' + ri + '" data-rk="name" value="' + esc( r.name ) + '"></td><td><input class="vsa-in vsa-in-sm" data-z="' + zi + '" data-r="' + ri + '" data-rk="cost" value="' + esc( dec( r.cost ) ) + '" inputmode="decimal"></td><td><input class="vsa-in vsa-in-sm" data-z="' + zi + '" data-r="' + ri + '" data-rk="free_above" value="' + esc( dec( r.free_above ) ) + '" placeholder="—" inputmode="decimal"></td><td><input class="vsa-in vsa-in-sm" data-z="' + zi + '" data-r="' + ri + '" data-rk="days" value="' + esc( r.days ) + '" placeholder="2–4"></td><td><button type="button" class="vsa-iconbtn" data-rdel="' + zi + ':' + ri + '" aria-label="' + esc( vxT( 'Remove' ) ) + '">' + ic( 'x', 14 ) + '</button></td></tr>'; } ).join( '' ) +
						'</tbody></table><button type="button" class="vsa-link" data-radd="' + zi + '">+ ' + vxT( 'Add a shipping method' ) + '</button></div>';
				} ).join( '' );
				return card( vxT( 'Where you deliver' ), field( vxT( 'Countries customers can choose' ), '<div class="vsa-tags">' + ( s.ship_countries || [] ).map( function ( c, i ) { return '<span class="vsa-tag">' + esc( C.countries[ c ] || c ) + '<button type="button" data-sc-del="' + i + '" aria-label="' + esc( vxT( 'Remove' ) ) + '">×</button></span>'; } ).join( '' ) + '<select class="vsa-tag-sel" data-sc-add><option value="">+ ' + vxT( 'country' ) + '</option>' + cs.map( function ( o ) { return '<option value="' + o[ 0 ] + '">' + esc( o[ 1 ] ) + '</option>'; } ).join( '' ) + '</select></div>', vxT( 'Empty = every country in the list.' ) ) ) +
					card( vxT( 'Shipping zones' ), '<p class="vsa-hint">' + vxT( 'The delivery country picks the zone (the first zone that lists it; a zone without countries catches the rest). Each zone has one or more methods the customer chooses from.' ) + '</p>' + zones + '<button type="button" class="vsa-btn vsa-btn-sm vsa-btn-ghost" data-act="z-add">' + ic( 'plus', 14 ) + vxT( 'Add a zone' ) + '</button>' ) +
					card( vxT( 'Pickup' ), tog( 'pickup', on( 'pickup' ), vxT( 'Customers can pick up their order' ), vxT( 'Free; you mark it “ready for pickup” and they get an email.' ) ) + ( on( 'pickup' ) ? '<div class="vsa-fr">' + field( vxT( 'Name' ), inp( 'pickup_name', s.pickup_name ) ) + field( vxT( 'Address and opening hours' ), area( 'pickup_address', s.pickup_address, 2 ) ) + '</div>' : '' ) );
			case 'payments':
				var order = ( s.pay_order || [] ).concat( ( C.methods || [] ).map( function ( m ) { return m.key; } ) ).filter( function ( k, i, a ) { return a.indexOf( k ) === i; } );
				return '<p class="vsa-hint vsa-hint-top">' + vxT( 'Customers see the methods in this order. Card and PayPal payments happen on the provider’s page — no card data ever touches your site.' ) + '</p>' + order.map( function ( k, i ) { return payCard( k, i, order.length ); } ).join( '' );
			case 'checkout':
				return card( vxT( 'Customer accounts' ), tog( 'acc_register', on( 'acc_register' ), vxT( 'Customers can create an account' ), vxT( 'On the account page: sign in, create an account, forgotten password — without WordPress’s own login page. Customers never see the dashboard.' ) ) +
					tog( 'acc_checkout', on( 'acc_checkout' ), vxT( 'Offer an account while ordering' ), vxT( 'A box in the checkout: tick it, choose a password, done. Ordering without an account always stays possible.' ) ) ) +
					card( vxT( 'The order form' ), field( vxT( 'Phone number' ), seg( 'f_phone', s.f_phone, [ [ 'hidden', vxT( 'Don’t ask' ) ], [ 'optional', vxT( 'Optional' ) ], [ 'required', vxT( 'Required' ) ] ] ) ) +
					field( vxT( 'Company' ), seg( 'f_company', s.f_company, [ [ 'hidden', vxT( 'Don’t ask' ) ], [ 'optional', vxT( 'Optional' ) ], [ 'required', vxT( 'Required' ) ] ] ) ) +
					tog( 'f_note', on( 'f_note' ), vxT( 'Customers can add a note' ), '' ) + tog( 'terms_check', on( 'terms_check' ), vxT( 'Customers tick “I accept the terms”' ), vxT( 'Common in Germany; the terms are part of the contract either way.' ) ) +
					'<div class="vsa-fr">' + field( vxT( 'Order button text' ), inp( 'button_text', s.button_text, { ph:vxT( 'Buy now' ) } ), vxT( 'German law requires words like “zahlungspflichtig bestellen” or “Jetzt kaufen”. Empty = the safe default.' ) ) + field( vxT( 'Minimum order value' ), inp( 'min_order', dec( s.min_order ), { suffix:C.currency, mode:'decimal', ph:vxT( 'none' ) } ) ) + '</div>' ) +
					card( vxT( 'Reserved stock' ), '<div class="vsa-fr">' + field( vxT( 'Card and PayPal orders' ), inp( 'hold_minutes', s.hold_minutes, { suffix:vxT( 'minutes' ), mode:'numeric' } ), vxT( 'Not paid by then: cancelled, stock goes back.' ) ) + field( vxT( 'Bank transfers' ), inp( 'bank_days', s.bank_days, { suffix:vxT( 'days' ), mode:'numeric' } ), vxT( '0 = never cancel automatically.' ) ) + '</div>' ) +
					card( vxT( 'Order numbers' ), '<div class="vsa-fr">' + field( vxT( 'Order number prefix' ), inp( 'order_prefix', s.order_prefix, { ph:vxT( 'none' ) } ), vxT( 'e.g. “SHOP-” → SHOP-1001' ) ) + field( vxT( 'Invoice number' ), inp( 'invoice_prefix', s.invoice_prefix, { cls:'vsa-mono' } ), vxT( '{Y} = year. Numbers run on without gaps, as the tax office expects.' ) ) + '</div>' );
			case 'emails':
				return card( vxT( 'Sender' ), '<div class="vsa-fr">' + field( vxT( 'Sender name' ), inp( 'mail_from_name', s.mail_from_name ) ) + field( vxT( 'Sender address' ), inp( 'mail_from', s.mail_from, { type:'email', ph:vxT( 'the site default' ) } ) ) + '</div>' + field( vxT( 'New orders go to' ), inp( 'mail_admin', s.mail_admin, { type:'email' } ) ) +
					field( vxT( 'Footer' ), area( 'mail_footer', s.mail_footer, 3, vxT( 'Empty = shop name and address.' ) ) ) + ( C.mailOk ? '' : '<div class="vsa-note">' + ic( 'alert', 15 ) + '<span>' + vxT( 'Emails go out through the server’s mail function, which often ends up in spam. Set up Velox Mail (SMTP) for reliable delivery.' ) + ' <a href="' + esc( C.mailUrl ) + '">' + vxT( 'Set up' ) + '</a></span></div>' ) ) +
					card( vxT( 'Emails customers get' ), '<ul class="vsa-mails">' + ( C.mails || [] ).map( function ( m ) {
						var off = ( s.mail_off || [] ).indexOf( m.key ) > -1, fixed = 'withdrawn' === m.key;
						return '<li><label class="vsa-tog vsa-tog-sm"><input type="checkbox" data-mail="' + m.key + '"' + ( off ? '' : ' checked' ) + ( fixed ? ' disabled' : '' ) + '><span class="vsa-tog-ui"></span></label><span class="vsa-grow"><b>' + esc( m.title ) + '</b><small>' + esc( m.desc ) + '</small></span><button type="button" class="vsa-btn vsa-btn-sm vsa-btn-ghost" data-mprev="' + m.key + '">' + ic( 'eye', 14 ) + vxT( 'Preview' ) + '</button><button type="button" class="vsa-btn vsa-btn-sm vsa-btn-ghost" data-mtest="' + m.key + '">' + ic( 'mail', 14 ) + vxT( 'Test' ) + '</button></li>';
					} ).join( '' ) + '</ul>' );
			case 'pages':
				return card( vxT( 'Shop pages' ), pageSel( 'shop', vxT( 'Shop' ) ) + pageSel( 'cart', vxT( 'Cart' ) ) + pageSel( 'checkout', vxT( 'Checkout' ), vxT( 'Also shows the order after buying.' ) ) + pageSel( 'account', vxT( 'Customer account' ) ) + pageSel( 'withdraw', vxT( 'Withdrawal form' ), vxT( 'The withdrawal button required since June 2026 (§ 356a BGB) — link it in your footer.' ) ) +
					'<button type="button" class="vsa-btn vsa-btn-sm vsa-btn-ghost" data-act="s-pages">' + ic( 'plus', 14 ) + vxT( 'Create the missing pages' ) + '</button>' ) +
					card( vxT( 'Legal pages' ), '<p class="vsa-hint">' + vxT( 'Linked at checkout. Terms and withdrawal policy are also copied into the order confirmation, as the law requires.' ) + '</p>' + pageSel( 'terms', vxT( 'Terms and conditions' ) ) + pageSel( 'withdrawal', vxT( 'Withdrawal policy' ) ) + pageSel( 'privacy', vxT( 'Privacy policy' ) ) + pageSel( 'shipping', vxT( 'Shipping and payment info' ), vxT( 'Linked from “plus shipping” next to every price.' ) ) ) +
					card( vxT( 'Withdrawal' ), tog( 'withdraw_on', on( 'withdraw_on' ), vxT( 'Customers can withdraw online' ), vxT( 'Order pages and emails get a “Withdraw from the contract” link. You get an email and the order is flagged.' ) ) + field( vxT( 'Withdrawal period' ), inp( 'withdraw_days', s.withdraw_days, { suffix:vxT( 'days' ), mode:'numeric', cls:'vsa-in-sm' } ), vxT( 'At least 14 days by law.' ) ) ) +
					card( vxT( 'Product pages' ), tog( 'auto_layout', on( 'auto_layout' ), vxT( 'Build product pages automatically' ), vxT( 'Photos, price, options and the cart button next to each other. Switch off to design them yourself with the shop elements in Velox Builder.' ) ) + field( vxT( 'Product web address' ), '<div class="vsa-row"><span class="vsa-muted vsa-mono">' + esc( C.home ) + '</span>' + inp( 'product_base', s.product_base, { cls:'vsa-in-sm vsa-mono' } ) + '<span class="vsa-muted vsa-mono">/…</span></div>' ) );
			case 'stock':
				return card( vxT( 'Stock' ), '<div class="vsa-fr">' + field( vxT( 'Warn me at' ), inp( 'low_stock', s.low_stock, { suffix:vxT( 'left' ), mode:'numeric' } ), vxT( 'You get an email, and the overview lists it.' ) ) + field( vxT( 'Show stock to customers' ), sel( 'show_stock', s.show_stock, [ [ 'low', vxT( 'Only when it runs low' ) ], [ 'always', vxT( 'Always' ) ], [ 'never', vxT( 'Never' ) ] ] ) ) + '</div>' +
					field( vxT( 'Sold items in the shop' ), sel( 'sold_items', s.sold_items || 'show', [ [ 'show', vxT( 'Show them as sold' ) ], [ 'last', vxT( 'Show them as sold, at the end' ) ], [ 'hide', vxT( 'Hide them' ) ] ] ), vxT( 'A cancelled order puts its items back, and they show up as available again.' ) ) ) +
					card( vxT( 'Everything else' ), '<p class="vsa-hint">' + vxT( 'Bulk edits, imports and exports work in the classic WordPress list.' ) + ' <a href="' + esc( C.classic ) + '&classic=1">' + vxT( 'Open the classic list' ) + '</a></p>' );
		}
		return '';
	}
	function payCard( k, i, n ) {
		var s = SD, m = ( C.methods || [] ).filter( function ( x ) { return x.key === k; } )[ 0 ]; if ( ! m ) { return ''; }
		var en = on( 'pay_' + k ), body = '';
		if ( 'bank' === k ) {
			body = '<div class="vsa-fr">' + field( vxT( 'Account holder' ), inp( 'bank_owner', s.bank_owner, { ph:s.store_name } ) ) + field( 'IBAN', inp( 'bank_iban', s.bank_iban, { cls:'vsa-mono' } ) ) + '</div><div class="vsa-fr">' + field( 'BIC', inp( 'bank_bic', s.bank_bic, { cls:'vsa-mono', ph:vxT( 'optional' ) } ) ) + field( vxT( 'Bank' ), inp( 'bank_name', s.bank_name, { ph:vxT( 'optional' ) } ) ) + '</div>';
		} else if ( 'invoice' === k ) {
			body = tog( 'invoice_known', on( 'invoice_known' ), vxT( 'Only for customers who have paid before' ), vxT( 'Protects you from not getting paid.' ) ) + field( vxT( 'Payment term' ), inp( 'invoice_days', s.invoice_days, { suffix:vxT( 'days' ), mode:'numeric', cls:'vsa-in-sm' } ) );
		} else if ( 'cod' === k ) {
			body = field( vxT( 'Extra fee' ), inp( 'cod_fee', dec( s.cod_fee ), { suffix:C.currency, mode:'decimal', ph:'0,00', cls:'vsa-in-sm' } ), vxT( 'Shown separately at checkout.' ) );
		} else if ( 'stripe' === k ) {
			body = '<ol class="vsa-steps"><li>' + vxT( 'In Stripe: Developers → API keys → copy the Secret key.' ) + '</li><li>' + vxT( 'Developers → Webhooks → add this address and the events checkout.session.completed and checkout.session.async_payment_succeeded, then copy the signing secret.' ) + '<div class="vsa-copy"><code>' + esc( C.webhook ) + '</code><button type="button" class="vsa-iconbtn" data-copy="' + esc( C.webhook ) + '" aria-label="' + esc( vxT( 'Copy' ) ) + '">' + ic( 'copy', 14 ) + '</button></div></li><li>' + vxT( 'Switch on in Stripe whatever you want to offer: cards, Apple Pay, Google Pay, SEPA, Klarna …' ) + '</li></ol>' +
				'<div class="vsa-fr">' + field( vxT( 'Secret key' ), inp( 'stripe_secret', s.stripe_secret, { cls:'vsa-mono', ph:'sk_live_… / sk_test_…', attr:' autocomplete="off" spellcheck="false"' } ) ) + field( vxT( 'Webhook signing secret' ), inp( 'stripe_whsec', s.stripe_whsec, { cls:'vsa-mono', ph:'whsec_…', attr:' autocomplete="off" spellcheck="false"' } ) ) + '</div>' +
				( s.stripe_mode ? '<p><span class="vsa-chip is-' + ( 'test' === s.stripe_mode ? 'warn' : 'ok' ) + '">' + ( 'test' === s.stripe_mode ? vxT( 'Test mode' ) : vxT( 'Live' ) ) + '</span></p>' : '' ) +
				'<button type="button" class="vsa-btn vsa-btn-sm vsa-btn-ghost" data-paytest="stripe">' + ic( 'bolt', 14 ) + vxT( 'Test the connection' ) + '</button>';
		} else if ( 'paypal' === k ) {
			body = '<p class="vsa-hint">' + vxT( 'developer.paypal.com → Apps & Credentials → create an app and copy its Client ID and Secret.' ) + '</p><div class="vsa-fr">' + field( 'Client ID', inp( 'paypal_id', s.paypal_id, { cls:'vsa-mono', attr:' autocomplete="off" spellcheck="false"' } ) ) + field( vxT( 'Secret' ), inp( 'paypal_secret', s.paypal_secret, { cls:'vsa-mono', attr:' autocomplete="off" spellcheck="false"' } ) ) + '</div>' +
				tog( 'paypal_sandbox', on( 'paypal_sandbox' ), vxT( 'Sandbox (test money)' ), vxT( 'Switch off when you go live.' ) ) + '<button type="button" class="vsa-btn vsa-btn-sm vsa-btn-ghost" data-paytest="paypal">' + ic( 'bolt', 14 ) + vxT( 'Test the connection' ) + '</button>';
		}
		return '<section class="vsa-card vsa-pay' + ( en ? ' is-on' : '' ) + '"><header class="vsa-card-h"><div class="vsa-pay-ord"><button type="button" class="vsa-iconbtn" data-pmove="' + i + ':-1"' + ( i ? '' : ' disabled' ) + ' aria-label="' + esc( vxT( 'Move up' ) ) + '">' + ic( 'up', 14 ) + '</button><button type="button" class="vsa-iconbtn" data-pmove="' + i + ':1"' + ( i < n - 1 ? '' : ' disabled' ) + ' aria-label="' + esc( vxT( 'Move down' ) ) + '">' + ic( 'down', 14 ) + '</button></div>' +
			'<label class="vsa-tog"><input type="checkbox" data-k="pay_' + k + '"' + ( en ? ' checked' : '' ) + '><span class="vsa-tog-ui"></span><span class="vsa-tog-t"><b>' + esc( m.title ) + '</b><small>' + esc( m.desc ) + '</small></span></label></header>' + ( en && body ? '<div class="vsa-card-b">' + body + '</div>' : '' ) + '</section>';
	}

	/* ============================================================== EVENTS */

	function setPath( obj, k, v ) { obj[ k ] = v; }
	function onClick( e ) {
		var t = e.target;
		var menu = t.closest( '.vsa-menu' );
		[].forEach.call( document.querySelectorAll( '.vsa-menu-p' ), function ( p ) { if ( ! menu || ! menu.contains( p ) ) { p.hidden = true; } } );
		var row = t.closest( 'tr[data-href]' );
		if ( row && ! t.closest( 'input,button,a,label,.vsa-inl' ) ) { location.hash = row.getAttribute( 'data-href' ); return; }
		var a = t.closest( '[data-act]' );
		var act = a && a.getAttribute( 'data-act' );
		if ( 'menu' === act ) { var mp = a.parentNode.querySelector( '.vsa-menu-p' ); mp.hidden = ! mp.hidden; return; }
		if ( 'dprev-dev' === act ) { dsDev = +a.getAttribute( 'data-v' ); [].forEach.call( main().querySelectorAll( '[data-act="dprev-dev"]' ), function ( b ) { b.classList.toggle( 'is-on', b === a ); } ); dsFit(); return; }
		if ( 'css-ins' === act ) { var ta = main().querySelector( '[data-k="custom_css"]' ); if ( ta ) { var add = ( ta.value && ! /\n$/.test( ta.value ) ? '\n' : '' ) + a.getAttribute( 'data-v' ) + ' {\n\t\n}\n'; ta.value += add; SD.custom_css = ta.value; ta.focus(); ta.setSelectionRange( ta.value.length - 3, ta.value.length - 3 ); sdirty(); } return; }
		/* wizard */
		if ( 'w-next' === act ) { W.step++; setup(); return; }
		if ( 'w-back' === act ) { W.step--; setup(); return; }
		if ( 'w-done' === act ) { wizardFinish( a ); return; }
		var segb = t.closest( '[data-seg]' );
		if ( segb ) {
			var sk = segb.getAttribute( 'data-seg' ), sv = segb.getAttribute( 'data-v' );
			if ( /^w_/.test( sk ) ) { W[ sk.slice( 2 ) ] = sv; setup(); return; }
			if ( 'products' === route.name && P ) { P[ sk ] = sv; if ( 'type' === sk && 'physical' !== sv ) { P.track = P.track; } drawProduct(); markDirty(); return; }
			if ( 'settings' === route.name ) { SD[ sk ] = sv; redrawSettings(); return; }
		}
		/* revenue */
		var rvr = t.closest( '[data-rvr]' ); if ( rvr ) { RV.range = rvr.getAttribute( 'data-rvr' ); [].forEach.call( document.querySelectorAll( '[data-rvr]' ), function ( b ) { b.classList.toggle( 'is-on', b === rvr ); b.setAttribute( 'aria-selected', b === rvr ); } ); var cu = document.querySelector( '.vsa-rv-custom' ); if ( cu ) { cu.hidden = 'custom' !== RV.range; } if ( 'custom' !== RV.range || ( RV.from && RV.to ) ) { loadRevenue(); } return; }
		var rvm = t.closest( '[data-rvm]' ); if ( rvm ) { RV.metric = rvm.getAttribute( 'data-rvm' ); drawRevenue(); return; }
		if ( 'rv-apply' === act ) { RV.from = document.getElementById( 'vsa-rv-from' ).value; RV.to = document.getElementById( 'vsa-rv-to' ).value; loadRevenue(); return; }
		if ( 'rv-csv' === act ) { revenueCsv(); return; }
		/* customers */
		var cvb = t.closest( '[data-cview]' ); if ( cvb ) { CV.kind = cvb.getAttribute( 'data-cview' ); [].forEach.call( document.querySelectorAll( '[data-cview]' ), function ( b ) { b.classList.toggle( 'is-on', b === cvb ); b.setAttribute( 'aria-selected', b === cvb ); } ); loadCustomers(); return; }
		if ( act && 0 === act.indexOf( 'c-' ) && 'customers' === route.name && CU && custClick( act ) ) { return; }
		/* orders */
		var ov = t.closest( '[data-oview]' ); if ( ov ) { OV.view = ov.getAttribute( 'data-oview' ); OV.page = 1; history.replaceState( null, '', '#/orders' ); lastHash = location.hash; [].forEach.call( document.querySelectorAll( '[data-oview]' ), function ( b ) { b.classList.toggle( 'is-on', b === ov ); } ); loadOrders(); return; }
		var op = t.closest( '[data-opage]' ); if ( op ) { OV.page = +op.getAttribute( 'data-opage' ); loadOrders(); return; }
		if ( 'o-paid' === act ) { modal( vxT( 'Mark as paid' ), '<p class="vsa-p">' + vxT( 'Has the money arrived? The customer gets a payment confirmation and an invoice number is assigned.' ) + '</p>' + field( vxT( 'Reference' ), '<input class="vsa-in" id="vsa-pref" placeholder="' + esc( vxT( 'optional, e.g. bank statement date' ) ) + '">' ), [ { k:'no', label:vxT( 'Cancel' ) }, { k:'yes', label:ic( 'check', 15 ) + vxT( 'Mark as paid' ) } ], function ( k, m ) { var r = m.querySelector( '#vsa-pref' ).value; closeModal(); if ( 'yes' === k ) { orderAct( 'paid', { ref:r } ); } } ); return; }
		if ( 'o-ship' === act ) { shipDialog(); return; }
		if ( 'o-delivered' === act ) { orderAct( 'delivered' ); return; }
		if ( 'o-unship' === act ) { orderAct( 'unship' ); return; }
		if ( 'o-reminder' === act ) { orderAct( 'reminder' ); return; }
		if ( 'o-resend' === act ) { orderAct( 'resend' ); return; }
		if ( 'o-invoice' === act ) { orderAct( 'invoice' ); return; }
		if ( 'o-wdone' === act ) { orderAct( 'withdrawal' ); return; }
		if ( 'o-refund' === act ) { refundDialog(); return; }
		if ( 'o-addr' === act ) { addrDialog(); return; }
		if ( 'o-cancel' === act ) { confirmBox( vxT( 'Cancel this order?' ), vxT( 'The stock goes back and the customer gets an email.' ) + ( /paid|partial/.test( O.payment.status ) ? ' <b>' + vxT( 'It is paid: refund the money afterwards.' ) + '</b>' : '' ), vxT( 'Cancel order' ), true ).then( function ( y ) { if ( y ) { orderAct( 'cancel' ); } } ); return; }
		if ( 'o-reopen' === act ) { modal( vxT( 'Reopen this order?' ), '<p class="vsa-p">' + vxT( 'The order is active again and its items are taken out of stock again.' ) + ( 'cancelled' === O.payment.status ? ' ' + vxT( 'It waits for payment like a new order.' ) : '' ) + '</p><label class="vsa-check"><input type="checkbox" id="vsa-reo-m" checked><span>' + vxT( 'Tell the customer by email' ) + '</span></label>', [ { k:'no', label:vxT( 'Cancel' ) }, { k:'yes', label:ic( 'undo', 15 ) + vxT( 'Reopen order' ) } ], function ( k, m ) { var t = m.querySelector( '#vsa-reo-m' ).checked; closeModal(); if ( 'yes' === k ) { orderAct( 'reopen', { tell:t ? 1 : 0 } ); } } ); return; }
		if ( 'o-delete' === act ) { confirmBox( vxT( 'Delete this order?' ), vxT( 'It moves to the trash. Only unpaid or cancelled orders can be deleted.' ), vxT( 'Delete' ), true ).then( function ( y ) { if ( y ) { orderAct( 'delete' ); } } ); return; }
		if ( 'o-note' === act ) { var nt = document.getElementById( 'vsa-note' ), nc = document.getElementById( 'vsa-note-c' ); orderAct( 'note', { text:nt.value, customer:nc.checked ? 1 : 0 } ); return; }
		/* products list */
		var pv = t.closest( '[data-pview]' ); if ( pv ) { PV.status = pv.getAttribute( 'data-pview' ); PV.page = 1; products(); return; }
		var pp = t.closest( '[data-ppage]' ); if ( pp ) { PV.page = +pp.getAttribute( 'data-ppage' ); loadProducts(); return; }
		var bk = t.closest( '[data-bulk]' );
		if ( bk ) {
			var b = bk.getAttribute( 'data-bulk' );
			if ( 'none' === b ) { PV.sel = {}; loadProducts( { rows:PROWS, cats:C.cats, pages:1 } ); return; }
			if ( 'trash' === b ) { confirmBox( vxT( 'Delete the selected products?' ), vxT( 'They move to the trash. Orders that contain them are not affected.' ), vxT( 'Delete' ), true ).then( function ( y ) { if ( y ) { bulk( 'trash' ); } } ); return; }
			bulk( b ); return;
		}
		var inl = t.closest( '[data-inl]' ); if ( inl ) { inlineEdit( inl ); return; }
		if ( 'cats' === act ) { catsDialog(); return; }
		/* product editor */
		if ( 'p-save' === act ) { saveProduct( a ); return; }
		if ( 'p-trash' === act ) { confirmBox( vxT( 'Delete this product?' ), vxT( 'It moves to the trash. Orders that contain it are not affected.' ), vxT( 'Delete' ), true ).then( function ( y ) { if ( y ) { api( 'product_bulk', { ids:[ P.id ], act:'trash' } ).then( function () { dirty = false; toast( vxT( 'Deleted.' ) ); location.hash = '#/products'; } ).catch( fail ); } } ); return; }
		if ( 'p-media' === act ) { pickMedia( { multiple:true, title:vxT( 'Add photos' ) }, function ( sel ) { sel.forEach( function ( im ) { var o = { id:im.id, url:( im.sizes && im.sizes.medium ? im.sizes.medium.url : im.url ), name:im.title }; if ( ! P.thumb ) { P.thumb = o; } else { P.galleryImgs.push( o ); } } ); drawProduct(); markDirty(); } ); return; }
		var imgDel = t.closest( '[data-img-del]' );
		if ( imgDel ) { var di = +imgDel.getAttribute( 'data-img-del' ); var all = ( P.thumb ? [ P.thumb ] : [] ).concat( P.galleryImgs ); all.splice( di, 1 ); P.thumb = all[ 0 ] || null; P.galleryImgs = all.slice( 1 ); drawProduct(); markDirty(); return; }
		var imgMain = t.closest( '[data-img-main]' );
		if ( imgMain ) { var mi = +imgMain.getAttribute( 'data-img-main' ); var all2 = ( P.thumb ? [ P.thumb ] : [] ).concat( P.galleryImgs ); var x = all2.splice( mi, 1 )[ 0 ]; all2.unshift( x ); P.thumb = all2[ 0 ]; P.galleryImgs = all2.slice( 1 ); drawProduct(); markDirty(); return; }
		if ( 'p-files' === act ) { pickMedia( { multiple:true, any:true, title:vxT( 'Files to download' ), button:vxT( 'Add' ) }, function ( sel ) { sel.forEach( function ( f ) { P.fileInfo.push( { id:f.id, name:f.filename || f.title } ); } ); drawProduct(); markDirty(); } ); return; }
		var fd = t.closest( '[data-file-del]' ); if ( fd ) { P.fileInfo.splice( +fd.getAttribute( 'data-file-del' ), 1 ); drawProduct(); markDirty(); return; }
		if ( 'spec-add' === act ) { P.specs.push( { k:'', v:'' } ); drawProduct(); markDirty(); var ins = main().querySelectorAll( '[data-spec]' ); if ( ins.length ) { ins[ ins.length - 2 ].focus(); } return; }
		var sd = t.closest( '[data-spec-del]' ); if ( sd ) { P.specs.splice( +sd.getAttribute( 'data-spec-del' ), 1 ); drawProduct(); markDirty(); return; }
		var ud = t.closest( '[data-ups-del]' ); if ( ud ) { P.upsellInfo.splice( +ud.getAttribute( 'data-ups-del' ), 1 ); drawProduct(); markDirty(); return; }
		if ( 'v-addopt' === act ) { P.options.push( { name:'', values:[] } ); redrawVariants(); var on_ = main().querySelectorAll( '[data-optname]' ); if ( on_.length ) { on_[ on_.length - 1 ].focus(); } var cardV = document.getElementById( 'vsa-variants' ); if ( cardV ) { cardV.querySelector( '.vsa-card-r' ).innerHTML = P.options.length < 3 ? cardV.querySelector( '.vsa-card-r' ).innerHTML : ''; } return; }
		var odel = t.closest( '[data-optdel]' ); if ( odel ) { P.options.splice( +odel.getAttribute( 'data-optdel' ), 1 ); syncVariants(); drawProduct(); markDirty(); return; }
		var ovd = t.closest( '[data-optval-del]' ); if ( ovd ) { var q = ovd.getAttribute( 'data-optval-del' ).split( ':' ); P.options[ +q[ 0 ] ].values.splice( +q[ 1 ], 1 ); syncVariants(); redrawVariants(); return; }
		var vimg = t.closest( '[data-var-img]' ); if ( vimg ) { var vi = +vimg.getAttribute( 'data-var-img' ); pickMedia( { title:vxT( 'Choose a photo' ) }, function ( sel ) { var im = sel[ 0 ]; P.variants[ vi ].image = im.id; P.varImgs[ im.id ] = im.sizes && im.sizes.thumbnail ? im.sizes.thumbnail.url : im.url; redrawVariants(); } ); return; }
		/* coupons */
		if ( 'c-add' === act ) { CP.unshift( { code:'', type:'percent', value:'10', min:'', until:'', limit:'', used:0, off:0 } ); drawCoupons(); dirty = true; document.getElementById( 'vsa-savebar' ).classList.add( 'is-dirty' ); var ci = main().querySelector( '[data-ck="code"]' ); if ( ci ) { ci.focus(); } return; }
		var cpd = t.closest( '[data-cp-del]' ); if ( cpd ) { CP.splice( +cpd.getAttribute( 'data-cp-del' ), 1 ); drawCoupons(); dirty = true; document.getElementById( 'vsa-savebar' ).classList.add( 'is-dirty' ); return; }
		if ( 'c-save' === act ) { a.disabled = true; api( 'coupons_save', { rows:CP } ).then( function ( d ) { CP = d.rows; dirty = false; drawCoupons(); toast( vxT( 'Saved.' ) ); } ).catch( function ( e2 ) { a.disabled = false; fail( e2 ); } ); return; }
		/* settings */
		if ( 's-save' === act ) {
			a.disabled = true;
			api( 'settings_save', { settings:SD } ).then( function ( r ) { S = r.settings; SD = JSON.parse( JSON.stringify( S ) ); SDclean = JSON.stringify( SD ); dirty = false; redrawSettings(); a.disabled = false; toast( vxT( 'Saved.' ) ); var br = root.querySelector( '.vsa-brand b' ); if ( br ) { br.textContent = S.store_name; } } ).catch( function ( e3 ) { a.disabled = false; fail( e3 ); } );
			return;
		}
		if ( 's-reset' === act ) { SD = JSON.parse( SDclean ); redrawSettings(); return; }
		if ( 's-pages' === act ) { a.disabled = true; api( 'create_pages', {} ).then( function ( r ) { S = r.settings; C.pages = r.pages; Object.keys( r.settings ).forEach( function ( k ) { if ( /^page_/.test( k ) ) { SD[ k ] = r.settings[ k ]; } } ); SDclean = JSON.stringify( Object.assign( JSON.parse( SDclean ), Object.keys( r.settings ).filter( function ( k ) { return /^page_/.test( k ); } ).reduce( function ( o, k ) { o[ k ] = r.settings[ k ]; return o; }, {} ) ) ); redrawSettings(); toast( r.made.length ? vxT( 'Created:' ) + ' ' + r.made.join( ', ' ) : vxT( 'All pages exist already.' ) ); } ).catch( fail ); return; }
		if ( 'd-reset' === act ) { SD[ a.getAttribute( 'data-v' ) ] = ''; redrawSettings(); return; }
		if ( 'd-tpl' === act ) { dsPick( a.getAttribute( 'data-v' ) ); return; }
		if ( 'd-clear' === act ) {
			var keep = {}; Object.keys( SD ).forEach( function ( x ) { if ( /^d_/.test( x ) ) { keep[ x ] = SD[ x ]; SD[ x ] = ''; } } ); keep.card_ratio = SD.card_ratio; SD.card_ratio = '';
			redrawSettings(); toast( vxT( 'Back to the pure template.' ), false, { label:vxT( 'Undo' ), fn:function () { Object.keys( keep ).forEach( function ( x ) { SD[ x ] = keep[ x ]; } ); redrawSettings(); } } ); return;
		}
		if ( 's-logo' === act ) { pickMedia( { title:vxT( 'Choose a logo' ) }, function ( sel ) { SD.logo = sel[ 0 ].id; SD.logo_url = sel[ 0 ].url; redrawSettings(); } ); return; }
		if ( 's-logo-x' === act ) { SD.logo = 0; SD.logo_url = ''; redrawSettings(); return; }
		if ( 'z-add' === act ) { SD.zones = ( SD.zones || [] ).concat( [ { id:'z' + Date.now().toString( 36 ), name:'', countries:[], rates:[ { id:'r' + Date.now().toString( 36 ), name:vxT( 'Standard shipping' ), cost:'4.90', free_above:'', days:'' } ] } ] ); redrawSettings(); return; }
		var zd = t.closest( '[data-zdel]' ); if ( zd ) { SD.zones.splice( +zd.getAttribute( 'data-zdel' ), 1 ); redrawSettings(); return; }
		var zcd = t.closest( '[data-zc-del]' ); if ( zcd ) { var zq = zcd.getAttribute( 'data-zc-del' ).split( ':' ); SD.zones[ +zq[ 0 ] ].countries.splice( +zq[ 1 ], 1 ); redrawSettings(); return; }
		var ra = t.closest( '[data-radd]' ); if ( ra ) { SD.zones[ +ra.getAttribute( 'data-radd' ) ].rates.push( { id:'r' + Date.now().toString( 36 ), name:'', cost:'', free_above:'', days:'' } ); redrawSettings(); return; }
		var rd = t.closest( '[data-rdel]' ); if ( rd ) { var rq = rd.getAttribute( 'data-rdel' ).split( ':' ); SD.zones[ +rq[ 0 ] ].rates.splice( +rq[ 1 ], 1 ); redrawSettings(); return; }
		var scd = t.closest( '[data-sc-del]' ); if ( scd ) { SD.ship_countries.splice( +scd.getAttribute( 'data-sc-del' ), 1 ); redrawSettings(); return; }
		var pm = t.closest( '[data-pmove]' );
		if ( pm ) {
			var pq = pm.getAttribute( 'data-pmove' ).split( ':' ), order = ( SD.pay_order || [] ).concat( ( C.methods || [] ).map( function ( m ) { return m.key; } ) ).filter( function ( k, i, arr ) { return arr.indexOf( k ) === i; } );
			var from = +pq[ 0 ], to = from + +pq[ 1 ], it = order.splice( from, 1 )[ 0 ]; order.splice( to, 0, it ); SD.pay_order = order; redrawSettings(); return;
		}
		var pt = t.closest( '[data-paytest]' ); if ( pt ) { if ( dirty ) { toast( vxT( 'Save first, then test.' ), true ); return; } pt.disabled = true; api( 'pay_test', { method:pt.getAttribute( 'data-paytest' ) } ).then( function ( r ) { pt.disabled = false; toast( r.msg ); } ).catch( function ( e4 ) { pt.disabled = false; fail( e4 ); } ); return; }
		var cpy = t.closest( '[data-copy]' ); if ( cpy ) { navigator.clipboard && navigator.clipboard.writeText( cpy.getAttribute( 'data-copy' ) ).then( function () { toast( vxT( 'Copied.' ) ); } ); return; }
		var mp2 = t.closest( '[data-mprev]' ); if ( mp2 ) { api( 'mail_preview', { type:mp2.getAttribute( 'data-mprev' ) } ).then( function ( r ) { var m = modal( esc( r.subject ), '<iframe class="vsa-mailprev" title="' + esc( vxT( 'Preview' ) ) + '"></iframe>', [], null, true ); m.querySelector( 'iframe' ).srcdoc = r.html; } ).catch( fail ); return; }
		var mt = t.closest( '[data-mtest]' ); if ( mt ) { mt.disabled = true; api( 'mail_test', { type:mt.getAttribute( 'data-mtest' ) } ).then( function ( r ) { mt.disabled = false; toast( r.msg ); } ).catch( function ( e5 ) { mt.disabled = false; fail( e5 ); } ); return; }
	}

	function inlineEdit( el ) {
		var id = +el.getAttribute( 'data-id' ), k = el.getAttribute( 'data-inl' ), r = PROWS.filter( function ( x ) { return x.id === id; } )[ 0 ];
		var cur = 'price' === k ? dec( r.price ) : r.stock;
		el.outerHTML = '<input class="vsa-in vsa-in-sm vsa-inl-in" data-inlin="' + k + '" data-id="' + id + '" value="' + esc( cur ) + '" inputmode="decimal">';
		var i = main().querySelector( '[data-inlin="' + k + '"][data-id="' + id + '"]' ); i.focus(); i.select();
		function done( save ) {
			if ( i._done ) { return; } i._done = true;
			if ( ! save || i.value === String( cur ) ) { loadProducts( { rows:PROWS, cats:C.cats, pages:1 } ); return; }
			bulk( k, { ids:[ id ], value:'price' === k ? num( i.value ) : parseInt( i.value, 10 ) || 0 } ).then( function () { toast( vxT( 'Saved.' ) ); } );
		}
		i.addEventListener( 'keydown', function ( e ) { if ( 'Enter' === e.key ) { done( true ); } if ( 'Escape' === e.key ) { done( false ); } } );
		i.addEventListener( 'blur', function () { done( true ); } );
	}

	var searchT = null;
	function onInput( e ) {
		var t = e.target;
		if ( t.hasAttribute( 'data-osearch' ) ) { clearTimeout( searchT ); searchT = setTimeout( function () { OV.q = t.value; OV.page = 1; loadOrders(); }, 300 ); return; }
		if ( t.hasAttribute( 'data-psearch' ) ) { clearTimeout( searchT ); searchT = setTimeout( function () { PV.q = t.value; PV.page = 1; loadProducts(); }, 300 ); return; }
		if ( t.hasAttribute( 'data-csearch' ) ) { clearTimeout( searchT ); searchT = setTimeout( function () { CV.q = t.value; loadCustomers(); }, 300 ); return; }
		var w = t.getAttribute( 'data-w' ); if ( w ) { W[ w ] = t.value; return; }
		if ( t.hasAttribute( 'data-upsearch' ) ) {
			clearTimeout( searchT );
			searchT = setTimeout( function () {
				api( 'products', { q:t.value } ).then( function ( d ) {
					var dl = document.getElementById( 'vsa-upslist' ); if ( ! dl ) { return; }
					dl.innerHTML = d.rows.filter( function ( r ) { return r.id !== P.id; } ).map( function ( r ) { return '<option value="' + esc( r.title ) + '" data-id="' + r.id + '">'; } ).join( '' );
					var hit = d.rows.filter( function ( r ) { return r.title === t.value && r.id !== P.id; } )[ 0 ];
					if ( hit && ! P.upsellInfo.some( function ( u ) { return u.id === hit.id; } ) ) { P.upsellInfo.push( { id:hit.id, title:hit.title } ); drawProduct(); markDirty(); }
				} );
			}, 250 );
			return;
		}
		if ( 'products' === route.name && P ) {
			var k = t.getAttribute( 'data-k' );
			if ( k ) {
				if ( 'checkbox' === t.type ) {
					if ( 'mode' === k ) { P.mode = t.checked ? 'inquiry' : 'buy'; } else { P[ k ] = t.checked ? 1 : 0; }
					if ( 'unique' === k && t.checked ) { P.options = []; P.variants = []; if ( '' === String( P.stock ) ) { P.stock = '1'; } }
					drawProduct();
				} else {
					P[ k ] = t.value;
					if ( /^(price|compare|unit_qty)$/.test( k ) ) { var pv2 = main().querySelector( '.vsa-preview' ); if ( pv2 ) { pv2.innerHTML = pricePreview(); } }
					if ( 'compare' === k && ( '' === t.value ) !== ! main().querySelector( '[data-k="sale_until"]' ) ) { drawProduct(); var c2 = main().querySelector( '[data-k="compare"]' ); if ( c2 ) { c2.focus(); c2.setSelectionRange( c2.value.length, c2.value.length ); } }
				}
				markDirty(); return;
			}
			var on_ = t.getAttribute( 'data-optname' ); if ( null !== on_ ) { P.options[ +on_ ].name = t.value; markDirty(); return; }
			var sp = t.getAttribute( 'data-spec' ); if ( null !== sp ) { P.specs[ +sp ][ t.getAttribute( 'data-sk' ) ] = t.value; markDirty(); return; }
			var vr = t.getAttribute( 'data-var' );
			if ( null !== vr ) { var vk = t.getAttribute( 'data-vk' ); if ( 'on' === vk ) { P.variants[ +vr ].off = t.checked ? 0 : 1; t.closest( 'tr' ).classList.toggle( 'is-off', ! t.checked ); } else { P.variants[ +vr ][ vk ] = t.value; } markDirty(); return; }
			var pc = t.getAttribute( 'data-pcatid' ); if ( null !== pc ) { var cid = +pc, ix = P.catIds.indexOf( cid ); if ( t.checked && ix < 0 ) { P.catIds.push( cid ); } if ( ! t.checked && ix > -1 ) { P.catIds.splice( ix, 1 ); } markDirty(); return; }
		}
		if ( 'coupons' === route.name && CP ) {
			var cp = t.getAttribute( 'data-cp' );
			if ( null !== cp ) {
				var ck = t.getAttribute( 'data-ck' );
				if ( 'on' === ck ) { CP[ +cp ].off = t.checked ? 0 : 1; } else { CP[ +cp ][ ck ] = 'code' === ck ? t.value.toUpperCase().replace( /[^A-Z0-9_-]/g, '' ) : t.value; }
				if ( 'type' === ck ) { drawCoupons(); }
				dirty = true; var sb = document.getElementById( 'vsa-savebar' ); if ( sb ) { sb.classList.add( 'is-dirty' ); } return;
			}
		}
		if ( 'settings' === route.name && SD ) {
			var sk = t.getAttribute( 'data-k' );
			if ( sk ) {
				if ( 'checkbox' === t.type ) { SD[ sk ] = t.checked ? '1' : ''; if ( /^pay_|^pickup$|^auto_layout$|^notice_on$/.test( sk ) ) { redrawSettings(); return; } }
				else { SD[ sk ] = /^page_/.test( sk ) ? +t.value : t.value; if ( 'accent' === sk || /^d_(ink|surface|line|soft|bg|muted|accent|accent_ink|sale)$/.test( sk ) ) { [].forEach.call( main().querySelectorAll( '[data-k="' + sk + '"]' ), function ( x ) { if ( x !== t && ( 'color' !== x.type || /^#[0-9a-f]{6}$/i.test( t.value ) ) ) { x.value = t.value; } } ); } }
				if ( /^page_/.test( sk ) ) { redrawSettings(); return; }
				if ( /^d_font_/.test( sk ) ) { redrawSettings(); return; }
				sdirty(); return;
			}
			var z = t.getAttribute( 'data-z' );
			if ( null !== z ) {
				if ( t.hasAttribute( 'data-zk' ) ) { SD.zones[ +z ][ t.getAttribute( 'data-zk' ) ] = t.value; }
				else { SD.zones[ +z ].rates[ +t.getAttribute( 'data-r' ) ][ t.getAttribute( 'data-rk' ) ] = t.value; }
				sdirty(); return;
			}
			var zca = t.getAttribute( 'data-zc-add' ); if ( null !== zca && t.value ) { var zz = SD.zones[ +zca ]; zz.countries = ( zz.countries || [] ).filter( function ( c ) { return c !== t.value; } ).concat( [ t.value ] ); redrawSettings(); return; }
			if ( t.hasAttribute( 'data-sc-add' ) && t.value ) { SD.ship_countries = ( SD.ship_countries || [] ).filter( function ( c ) { return c !== t.value; } ).concat( [ t.value ] ); redrawSettings(); return; }
			var ml = t.getAttribute( 'data-mail' ); if ( ml ) { var off = ( SD.mail_off || [] ).filter( function ( x ) { return x !== ml; } ); if ( ! t.checked ) { off.push( ml ); } SD.mail_off = off; sdirty(); return; }
		}
		var wp = t.getAttribute( 'data-wpay' ); if ( wp ) { W.pay[ wp ] = t.checked ? 1 : 0; setup(); return; }
		if ( t.hasAttribute( 'data-pcat' ) ) { PV.cat = +t.value; PV.page = 1; loadProducts(); return; }
		if ( t.hasAttribute( 'data-pall' ) ) { PROWS.forEach( function ( r ) { PV.sel[ r.id ] = t.checked; } ); [].forEach.call( main().querySelectorAll( '[data-psel]' ), function ( c ) { c.checked = t.checked; } ); drawBulk(); return; }
		var ps = t.getAttribute( 'data-psel' ); if ( ps ) { PV.sel[ ps ] = t.checked; drawBulk(); return; }
	}

	function onKey( e ) {
		var t = e.target;
		// option values: Enter or comma adds a value chip
		var ov = t.getAttribute && t.getAttribute( 'data-optval' );
		if ( null !== ov && undefined !== ov && ( 'Enter' === e.key || ',' === e.key ) ) {
			e.preventDefault();
			var vals = t.value.split( ',' ).map( function ( x ) { return x.trim(); } ).filter( Boolean );
			var o = P.options[ +ov ];
			vals.forEach( function ( v ) { if ( o.values.indexOf( v ) < 0 ) { o.values.push( v ); } } );
			syncVariants(); redrawVariants();
			var n = main().querySelector( '[data-optval="' + ov + '"]' ); if ( n ) { n.focus(); }
			return;
		}
		if ( null !== ov && undefined !== ov && 'Backspace' === e.key && '' === t.value && P.options[ +ov ].values.length ) { P.options[ +ov ].values.pop(); syncVariants(); redrawVariants(); var n2 = main().querySelector( '[data-optval="' + ov + '"]' ); if ( n2 ) { n2.focus(); } return; }
		if ( 'Enter' === e.key && t.closest && t.closest( 'tr[data-href]' ) && 'TR' === t.tagName ) { location.hash = t.getAttribute( 'data-href' ); return; }
		// Ctrl/⌘+S saves what is open
		if ( ( e.ctrlKey || e.metaKey ) && 's' === e.key.toLowerCase() ) {
			var b = main() && main().querySelector( '[data-act="p-save"],[data-act="s-save"],[data-act="c-save"]' );
			if ( b ) { e.preventDefault(); b.click(); }
		}
	}

	root.addEventListener( 'click', onClick );
	document.addEventListener( 'click', function ( e ) { if ( ! root.contains( e.target ) ) { [].forEach.call( document.querySelectorAll( '.vsa-menu-p' ), function ( p ) { p.hidden = true; } ); } } );
	root.addEventListener( 'input', onInput );
	root.addEventListener( 'change', function ( e ) { var k = e.target.getAttribute && e.target.getAttribute( 'data-rvf' ); if ( k ) { RV[ k ] = e.target.value; loadRevenue(); } } );
	document.addEventListener( 'keydown', onKey );

	shell();
	// The WordPress submenu links ("Velox Shop → Orders") arrive as page=velox-shop#/orders.
	go();
	api( 'stats' ).then( function ( d ) { setBadge( d.toShip + d.withdrawals ); } ).catch( function () {} );
}() );
