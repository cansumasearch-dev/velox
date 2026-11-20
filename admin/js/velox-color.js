/* Velox — one colour picker for every Velox screen.
 *
 * Every <input type="color"> on a Velox screen gets a clean chip in its place;
 * clicking it opens a picker: saturation/brightness field, hue slider,
 * eyedropper, hex field, the colours already used on this screen and your
 * recent ones. The native input stays in the page (hidden), keeps its value and
 * receives the usual "input" and "change" events — so every screen keeps
 * working without changes. Fully usable with the keyboard. */
( function () {
	'use strict';
	if ( window.VeloxColor ) { return; }
	var L = ( window.VELOX_COLOR_I18N || {} );
	function t( k, d ) { return L[ k ] || d; }
	var DESC = Object.getOwnPropertyDescriptor( HTMLInputElement.prototype, 'value' );
	var RECENT = 'velox_recent_colours';

	/* ------------------------------------------------------------ colour math */
	function clamp( v, a, b ) { return Math.max( a, Math.min( b, v ) ); }
	function hexToRgb( h ) {
		h = String( h || '' ).trim().replace( /^#/, '' );
		if ( /^[0-9a-f]{3}$/i.test( h ) ) { h = h.replace( /(.)/g, '$1$1' ); }
		if ( ! /^[0-9a-f]{6}$/i.test( h ) ) { return null; }
		return [ parseInt( h.slice( 0, 2 ), 16 ), parseInt( h.slice( 2, 4 ), 16 ), parseInt( h.slice( 4, 6 ), 16 ) ];
	}
	function rgbToHex( r, g, b ) { return '#' + [ r, g, b ].map( function ( x ) { x = Math.round( clamp( x, 0, 255 ) ).toString( 16 ); return x.length < 2 ? '0' + x : x; } ).join( '' ); }
	function rgbToHsv( r, g, b ) {
		r /= 255; g /= 255; b /= 255;
		var max = Math.max( r, g, b ), min = Math.min( r, g, b ), d = max - min, h = 0;
		if ( d ) {
			h = max === r ? ( ( g - b ) / d ) % 6 : max === g ? ( b - r ) / d + 2 : ( r - g ) / d + 4;
			h *= 60; if ( h < 0 ) { h += 360; }
		}
		return [ h, max ? d / max : 0, max ];
	}
	function hsvToRgb( h, s, v ) {
		var f = function ( n ) { var k = ( n + h / 60 ) % 6; return v - v * s * Math.max( 0, Math.min( k, 4 - k, 1 ) ); };
		return [ f( 5 ) * 255, f( 3 ) * 255, f( 1 ) * 255 ];
	}
	function normalise( v ) {
		v = String( v || '' ).trim();
		if ( /^[0-9a-f]{3}$|^[0-9a-f]{6}$/i.test( v ) ) { v = '#' + v; }
		var rgb = hexToRgb( v );
		return rgb ? rgbToHex( rgb[ 0 ], rgb[ 1 ], rgb[ 2 ] ) : null;
	}
	function light( hex ) { var c = hexToRgb( hex ) || [ 0, 0, 0 ]; return ( 0.299 * c[ 0 ] + 0.587 * c[ 1 ] + 0.114 * c[ 2 ] ) > 160; }

	/* ------------------------------------------------------------ the chips */
	function enhance( input ) {
		if ( input.__vxcp || input.closest( '.vxcp' ) ) { return; }
		input.__vxcp = true;
		var w = input.offsetWidth, h = input.offsetHeight;
		var chip = document.createElement( 'button' );
		chip.type = 'button';
		chip.className = 'vxcp-chip';
		var size = clamp( Math.round( Math.min( w || 28, h || 28 ) ), 20, 36 );
		chip.style.setProperty( '--vxcp-size', size + 'px' );
		chip.setAttribute( 'aria-haspopup', 'dialog' );
		input.insertAdjacentElement( 'afterend', chip );
		input.classList.add( 'vxcp-native' );
		input.tabIndex = -1;
		input.setAttribute( 'aria-hidden', 'true' );
		chip.__input = input;
		input.__chip = chip;
		// Code that sets input.value directly still updates the chip.
		try {
			Object.defineProperty( input, 'value', {
				configurable: true,
				get: function () { return DESC.get.call( this ); },
				set: function ( v ) { DESC.set.call( this, v ); paint( chip ); }
			} );
		} catch ( e ) {}
		input.addEventListener( 'input', function () { paint( chip ); } );
		input.addEventListener( 'change', function () { paint( chip ); } );
		paint( chip );
	}
	function paint( chip ) {
		var inp = chip.__input, v = normalise( DESC.get.call( inp ) ) || '#000000';
		chip.style.setProperty( '--c', v );
		chip.classList.toggle( 'is-light', light( v ) );
		chip.disabled = !! inp.disabled;
		var name = inp.getAttribute( 'aria-label' ) || ( inp.closest( 'label' ) && inp.closest( 'label' ).textContent.trim().split( '\n' )[ 0 ] ) || t( 'colour', 'Colour' );
		chip.setAttribute( 'aria-label', name.slice( 0, 60 ) + ': ' + v );
		chip.title = v;
	}
	function scan( root ) {
		[].forEach.call( ( root || document ).querySelectorAll( 'input[type="color"]' ), enhance );
	}

	/* ------------------------------------------------------------ the popover */
	var pop = null, cur = null, hsv = [ 0, 0, 0 ], start = '', changed = false;
	function build() {
		pop = document.createElement( 'div' );
		pop.className = 'vxcp';
		pop.setAttribute( 'role', 'dialog' );
		pop.setAttribute( 'aria-label', t( 'colour', 'Colour' ) );
		pop.hidden = true;
		pop.innerHTML =
			'<div class="vxcp-sv" tabindex="0" role="slider" aria-label="' + t( 'sv', 'Saturation and brightness' ) + '"><span class="vxcp-th"></span></div>' +
			'<div class="vxcp-line">' +
				( window.EyeDropper ? '<button type="button" class="vxcp-ico vxcp-drop" title="' + t( 'drop', 'Pick a colour from the screen' ) + '" aria-label="' + t( 'drop', 'Pick a colour from the screen' ) + '"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m14.5 6.5 3 3M4 20l1-4 9.5-9.5a2.1 2.1 0 0 1 3 3L8 19z"/><path d="M15 4l5 5"/></svg></button>' : '' ) +
				'<div class="vxcp-hue" tabindex="0" role="slider" aria-label="' + t( 'hue', 'Hue' ) + '" aria-valuemin="0" aria-valuemax="360"><span class="vxcp-th"></span></div>' +
			'</div>' +
			'<div class="vxcp-in"><span class="vxcp-cmp"><i class="vxcp-old" title="' + t( 'before', 'Before' ) + '"></i><i class="vxcp-new"></i></span><label><span>HEX</span><input type="text" class="vxcp-hex" spellcheck="false" autocomplete="off" maxlength="7" aria-label="' + t( 'hex', 'Hex value' ) + '"></label></div>' +
			'<div class="vxcp-sw-wrap"></div>';
		document.body.appendChild( pop );
		drag( pop.querySelector( '.vxcp-sv' ), function ( x, y ) { hsv[ 1 ] = x; hsv[ 2 ] = 1 - y; apply(); } );
		drag( pop.querySelector( '.vxcp-hue' ), function ( x ) { hsv[ 0 ] = x * 360; apply(); } );
		var hex = pop.querySelector( '.vxcp-hex' );
		hex.addEventListener( 'input', function () {
			var n = normalise( hex.value );
			if ( n && hex.value.replace( '#', '' ).length >= 6 ) { setHex( n, true ); }
		} );
		hex.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' === e.key ) { e.preventDefault(); var n = normalise( hex.value ); if ( n ) { setHex( n ); close( true ); } }
		} );
		hex.addEventListener( 'blur', function () { var n = normalise( hex.value ); hex.value = n ? n : rgbToHex.apply( null, hsvToRgb( hsv[ 0 ], hsv[ 1 ], hsv[ 2 ] ) ); } );
		pop.querySelector( '.vxcp-sv' ).addEventListener( 'keydown', function ( e ) { keys( e, 'sv' ); } );
		pop.querySelector( '.vxcp-hue' ).addEventListener( 'keydown', function ( e ) { keys( e, 'hue' ); } );
		var dr = pop.querySelector( '.vxcp-drop' );
		if ( dr ) {
			dr.addEventListener( 'click', function () {
				try { new window.EyeDropper().open().then( function ( r ) { var n = normalise( r.sRGBHex ); if ( n ) { setHex( n ); } } ).catch( function () {} ); } catch ( e ) {}
			} );
		}
		pop.querySelector( '.vxcp-old' ).addEventListener( 'click', function () { setHex( start ); } );
		pop.addEventListener( 'click', function ( e ) {
			var s = e.target.closest( '[data-vxcp]' );
			if ( s ) { setHex( s.getAttribute( 'data-vxcp' ) ); }
		} );
		pop.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) { e.preventDefault(); e.stopPropagation(); setHex( start ); changed = false; close( true ); }
		} );
	}
	function drag( el, fn ) {
		function at( e ) {
			var r = el.getBoundingClientRect();
			fn( clamp( ( e.clientX - r.left ) / r.width, 0, 1 ), clamp( ( e.clientY - r.top ) / r.height, 0, 1 ) );
		}
		el.addEventListener( 'pointerdown', function ( e ) {
			if ( 0 !== e.button ) { return; }
			e.preventDefault(); el.focus(); el.setPointerCapture( e.pointerId ); at( e );
			var move = function ( ev ) { at( ev ); };
			var up = function () { el.removeEventListener( 'pointermove', move ); el.removeEventListener( 'pointerup', up ); el.removeEventListener( 'pointercancel', up ); };
			el.addEventListener( 'pointermove', move ); el.addEventListener( 'pointerup', up ); el.addEventListener( 'pointercancel', up );
		} );
	}
	function keys( e, which ) {
		var big = e.shiftKey ? 10 : 1, k = e.key, used = true;
		if ( 'hue' === which ) {
			if ( 'ArrowLeft' === k || 'ArrowDown' === k ) { hsv[ 0 ] = ( hsv[ 0 ] - big + 360 ) % 360; }
			else if ( 'ArrowRight' === k || 'ArrowUp' === k ) { hsv[ 0 ] = ( hsv[ 0 ] + big ) % 360; }
			else if ( 'Home' === k ) { hsv[ 0 ] = 0; } else if ( 'End' === k ) { hsv[ 0 ] = 359; } else { used = false; }
		} else {
			if ( 'ArrowLeft' === k ) { hsv[ 1 ] = clamp( hsv[ 1 ] - big / 100, 0, 1 ); }
			else if ( 'ArrowRight' === k ) { hsv[ 1 ] = clamp( hsv[ 1 ] + big / 100, 0, 1 ); }
			else if ( 'ArrowUp' === k ) { hsv[ 2 ] = clamp( hsv[ 2 ] + big / 100, 0, 1 ); }
			else if ( 'ArrowDown' === k ) { hsv[ 2 ] = clamp( hsv[ 2 ] - big / 100, 0, 1 ); } else { used = false; }
		}
		if ( 'Enter' === k ) { close( true ); used = true; }
		if ( used ) { e.preventDefault(); apply(); }
	}
	function setHex( hex, keepField ) {
		var c = hexToRgb( hex ); if ( ! c ) { return; }
		var n = rgbToHsv( c[ 0 ], c[ 1 ], c[ 2 ] );
		// Keep the hue when the colour has none (grey), so the slider does not jump.
		if ( ! n[ 1 ] || ! n[ 2 ] ) { n[ 0 ] = hsv[ 0 ]; }
		hsv = n;
		apply( keepField );
	}
	function apply( keepField ) {
		var rgb = hsvToRgb( hsv[ 0 ], hsv[ 1 ], hsv[ 2 ] ), hex = rgbToHex( rgb[ 0 ], rgb[ 1 ], rgb[ 2 ] );
		draw( hex, keepField );
		if ( cur && hex !== normalise( DESC.get.call( cur ) ) ) {
			DESC.set.call( cur, hex );
			changed = true;
			cur.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		}
	}
	function draw( hex, keepField ) {
		var sv = pop.querySelector( '.vxcp-sv' ), hue = pop.querySelector( '.vxcp-hue' );
		sv.style.setProperty( '--h', 'hsl(' + Math.round( hsv[ 0 ] ) + ' 100% 50%)' );
		var th = sv.querySelector( '.vxcp-th' );
		th.style.left = ( hsv[ 1 ] * 100 ) + '%';
		th.style.top = ( ( 1 - hsv[ 2 ] ) * 100 ) + '%';
		th.style.background = hex;
		sv.setAttribute( 'aria-valuetext', t( 'sat', 'Saturation' ) + ' ' + Math.round( hsv[ 1 ] * 100 ) + '%, ' + t( 'bright', 'brightness' ) + ' ' + Math.round( hsv[ 2 ] * 100 ) + '%' );
		var hth = hue.querySelector( '.vxcp-th' );
		hth.style.left = ( hsv[ 0 ] / 360 * 100 ) + '%';
		hth.style.background = 'hsl(' + Math.round( hsv[ 0 ] ) + ' 100% 50%)';
		hue.setAttribute( 'aria-valuenow', Math.round( hsv[ 0 ] ) );
		pop.querySelector( '.vxcp-new' ).style.background = hex;
		if ( ! keepField ) { pop.querySelector( '.vxcp-hex' ).value = hex; }
	}
	function swatches() {
		var seen = {}, page = [], rec = [];
		[].forEach.call( document.querySelectorAll( 'input.vxcp-native' ), function ( i ) {
			var v = normalise( DESC.get.call( i ) );
			if ( v && ! seen[ v ] && i !== cur ) { seen[ v ] = 1; page.push( v ); }
		} );
		( window.VELOX_SWATCHES || [] ).forEach( function ( v ) { v = normalise( v ); if ( v && ! seen[ v ] ) { seen[ v ] = 1; page.unshift( v ); } } );
		try { rec = JSON.parse( localStorage.getItem( RECENT ) || '[]' ).filter( function ( v ) { return normalise( v ); } ); } catch ( e ) {}
		var row = function ( title, list ) {
			return list.length ? '<div class="vxcp-sw-h">' + title + '</div><div class="vxcp-sws">' + list.slice( 0, 14 ).map( function ( v ) { return '<button type="button" class="vxcp-s' + ( light( v ) ? ' is-light' : '' ) + '" data-vxcp="' + v + '" style="--c:' + v + '" title="' + v + '" aria-label="' + v + '"></button>'; } ).join( '' ) + '</div>' : '';
		};
		pop.querySelector( '.vxcp-sw-wrap' ).innerHTML = row( t( 'page', 'On this screen' ), page ) + row( t( 'recent', 'Recent' ), rec );
	}
	function place( chip ) {
		var r = chip.getBoundingClientRect(), pw = pop.offsetWidth, ph = pop.offsetHeight, vw = window.innerWidth, vh = window.innerHeight;
		var top = r.bottom + 8;
		if ( top + ph > vh - 8 && r.top - ph - 8 > 8 ) { top = r.top - ph - 8; }
		top = clamp( top, 8, Math.max( 8, vh - ph - 8 ) );
		var left = clamp( r.left, 8, Math.max( 8, vw - pw - 8 ) );
		pop.style.top = top + 'px';
		pop.style.left = left + 'px';
	}
	function open( chip ) {
		if ( ! pop ) { build(); }
		if ( cur === chip.__input && ! pop.hidden ) { close( true ); return; }
		if ( cur ) { close( false ); }
		cur = chip.__input;
		start = normalise( DESC.get.call( cur ) ) || '#000000';
		changed = false;
		var c = hexToRgb( start );
		hsv = rgbToHsv( c[ 0 ], c[ 1 ], c[ 2 ] );
		pop.querySelector( '.vxcp-old' ).style.background = start;
		swatches();
		pop.hidden = false;
		draw( start );
		place( chip );
		chip.setAttribute( 'aria-expanded', 'true' );
		pop.classList.remove( 'is-in' );
		void pop.offsetWidth;
		pop.classList.add( 'is-in' );
		pop.querySelector( '.vxcp-sv' ).focus( { preventScroll: true } );
	}
	function close( focusChip ) {
		if ( ! pop || pop.hidden ) { return; }
		var inp = cur;
		pop.hidden = true;
		cur = null;
		if ( inp ) {
			inp.__chip.setAttribute( 'aria-expanded', 'false' );
			if ( changed ) {
				var v = normalise( DESC.get.call( inp ) );
				try {
					var rec = JSON.parse( localStorage.getItem( RECENT ) || '[]' ).filter( function ( x ) { return x !== v; } );
					rec.unshift( v ); localStorage.setItem( RECENT, JSON.stringify( rec.slice( 0, 12 ) ) );
				} catch ( e ) {}
				inp.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
			if ( focusChip ) { inp.__chip.focus( { preventScroll: true } ); }
		}
		changed = false;
	}

	document.addEventListener( 'click', function ( e ) {
		var chip = e.target.closest && e.target.closest( '.vxcp-chip' );
		if ( chip ) { e.preventDefault(); e.stopPropagation(); open( chip ); return; }
		// A click on a label's text must not open the browser's own picker of the hidden input.
		var lab = e.target.closest && e.target.closest( 'label' );
		if ( lab && lab.control && lab.control.classList.contains( 'vxcp-native' ) && ! e.target.closest( 'input,button,select,textarea,a' ) ) {
			e.preventDefault();
			var txt = lab.querySelector( 'input:not([type="color"]):not([type="hidden"])' );
			if ( txt ) { txt.focus(); }
		}
		if ( pop && ! pop.hidden && ! pop.contains( e.target ) ) { close( false ); }
	}, true );
	document.addEventListener( 'focusin', function ( e ) {
		if ( pop && ! pop.hidden && ! pop.contains( e.target ) && ! ( e.target.classList && e.target.classList.contains( 'vxcp-chip' ) ) ) { close( false ); }
	} );
	window.addEventListener( 'resize', function () { if ( cur ) { place( cur.__chip ); } } );
	document.addEventListener( 'scroll', function () { if ( cur ) { place( cur.__chip ); } }, true );

	function boot() {
		scan( document );
		new MutationObserver( function ( list ) {
			list.forEach( function ( m ) {
				[].forEach.call( m.addedNodes, function ( n ) {
					if ( 1 !== n.nodeType ) { return; }
					if ( n.matches && n.matches( 'input[type="color"]' ) ) { enhance( n ); } else if ( n.querySelectorAll ) { scan( n ); }
				} );
			} );
			if ( cur && ! document.contains( cur ) ) { close( false ); }
		} ).observe( document.body, { childList: true, subtree: true } );
	}
	if ( document.body ) { boot(); } else { document.addEventListener( 'DOMContentLoaded', boot ); }
	window.VeloxColor = { scan: scan, normalise: normalise };
}() );
