/* Velox — light / dark switch on every Velox screen, and the colour
 * converter the editor uses for its own CSS (same rules as Velox_Theme). */
( function () {
	'use strict';
	var C = window.VELOX_THEME || {};
	var L = C.i18n || {};
	var html = document.documentElement;

	/* ---------------------------------------------------------- converter */
	function parse( s ) {
		s = String( s ).toLowerCase().trim();
		if ( 'white' === s ) { return [ 255, 255, 255, 1 ]; }
		if ( 'black' === s ) { return [ 0, 0, 0, 1 ]; }
		if ( '#' === s[ 0 ] ) {
			var h = s.slice( 1 );
			if ( 3 === h.length || 4 === h.length ) { h = h.replace( /(.)/g, '$1$1' ); }
			return [ parseInt( h.slice( 0, 2 ), 16 ), parseInt( h.slice( 2, 4 ), 16 ), parseInt( h.slice( 4, 6 ), 16 ), 8 === h.length ? parseInt( h.slice( 6, 8 ), 16 ) / 255 : 1 ];
		}
		var m = /rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s/]+([\d.]+%?))?\s*\)/.exec( s );
		if ( ! m ) { return null; }
		var a = m[ 4 ] ? ( /%$/.test( m[ 4 ] ) ? parseFloat( m[ 4 ] ) / 100 : parseFloat( m[ 4 ] ) ) : 1;
		return [ +m[ 1 ], +m[ 2 ], +m[ 3 ], a ];
	}
	function hsl( c ) {
		var r = c[ 0 ] / 255, g = c[ 1 ] / 255, b = c[ 2 ] / 255, max = Math.max( r, g, b ), min = Math.min( r, g, b ), l = ( max + min ) / 2, h = 0, s = 0;
		if ( max !== min ) {
			var d = max - min;
			s = l > 0.5 ? d / ( 2 - max - min ) : d / ( max + min );
			h = max === r ? ( g - b ) / d + ( g < b ? 6 : 0 ) : ( max === g ? ( b - r ) / d + 2 : ( r - g ) / d + 4 );
			h /= 6;
		}
		return [ h, s, l, c[ 3 ] ];
	}
	function rgb( h, s, l, a ) {
		if ( ! s ) { return [ l * 255, l * 255, l * 255, a ]; }
		var q = l < 0.5 ? l * ( 1 + s ) : l + s - l * s, p = 2 * l - q;
		function f( t ) { if ( t < 0 ) { t += 1; } if ( t > 1 ) { t -= 1; } if ( t < 1 / 6 ) { return p + ( q - p ) * 6 * t; } if ( t < 0.5 ) { return q; } if ( t < 2 / 3 ) { return p + ( q - p ) * ( 2 / 3 - t ) * 6; } return p; }
		return [ f( h + 1 / 3 ) * 255, f( h ) * 255, f( h - 1 / 3 ) * 255, a ];
	}
	function fmt( c ) {
		var r = Math.round( Math.max( 0, Math.min( 255, c[ 0 ] ) ) ), g = Math.round( Math.max( 0, Math.min( 255, c[ 1 ] ) ) ), b = Math.round( Math.max( 0, Math.min( 255, c[ 2 ] ) ) ), a = Math.round( Math.max( 0, Math.min( 1, c[ 3 ] ) ) * 1000 ) / 1000;
		if ( a >= 1 ) { return '#' + [ r, g, b ].map( function ( x ) { return ( x < 16 ? '0' : '' ) + x.toString( 16 ); } ).join( '' ); }
		return 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';
	}
	function cl( v, lo, hi ) { return Math.max( lo, Math.min( hi, v ) ); }
	function map( c, kind, to ) {
		var x = hsl( c ), h = x[ 0 ], s = x[ 1 ], l = x[ 2 ], a = x[ 3 ];
		var neutral = s < 0.22 || l > 0.94 || l < 0.06;
		var wo = c[ 0 ] > 230 && c[ 1 ] > 230 && c[ 2 ] > 230 && a < 1, bo = c[ 0 ] < 40 && c[ 1 ] < 40 && c[ 2 ] < 40 && a < 1;
		if ( 'accent' === kind ) { return c; }
		if ( 'light' === to ) {
			if ( 'shadow' === kind ) { return bo || l < 0.2 ? [ 15, 23, 42, cl( a * 0.35, 0.04, 0.18 ) ] : ( wo ? [ 0, 0, 0, a * 0.5 ] : c ); }
			if ( wo ) { return [ 15, 20, 30, cl( a * ( 'line' === kind ? 1.25 : 0.9 ), 0, 1 ) ]; }
			if ( bo ) { if ( 'bg' === kind && a >= 0.8 ) { return [ 252, 252, 253, a ]; } return 'bg' === kind ? [ c[ 0 ], c[ 1 ], c[ 2 ], a * 0.55 ] : [ 255, 255, 255, a ]; }
			if ( ! neutral ) {
				if ( 'text' === kind && l > 0.6 ) { return rgb( h, Math.min( 1, s * 1.05 ), cl( 1.02 - l, 0.3, 0.42 ), a ); }
				if ( 'bg' === kind && a < 0.5 ) { return c; }
				if ( 'bg' === kind && l < 0.2 ) { return rgb( h, s * 0.6, 0.95, a ); }
				return c;
			}
			if ( 'text' === kind ) { return rgb( h, s, cl( ( 1 - l ) * 0.85 + 0.06, 0.06, 0.46 ), a ); }
			if ( 'line' === kind ) { return l < 0.5 ? rgb( h, s, cl( 1 - l + 0.02, 0.72, 0.92 ), a ) : c; }
			if ( l > 0.8 ) { return c; }
			if ( l < 0.085 ) { return rgb( h, s, 0.955, a ); }
			return rgb( h, s, cl( 1 - ( l - 0.09 ) * 1.05, 0.8, 1 ), a );
		}
		if ( 'shadow' === kind ) { return bo ? [ 0, 0, 0, cl( a * 1.8, 0.2, 0.6 ) ] : c; }
		if ( bo ) { return [ 255, 255, 255, cl( a * ( 'line' === kind ? 1.3 : 1 ), 0, 1 ) ]; }
		if ( wo ) { return 'bg' === kind ? [ 20, 22, 27, a ] : c; }
		if ( ! neutral ) {
			if ( 'text' === kind && l < 0.5 ) { return rgb( h, s, 0.7, a ); }
			if ( 'bg' === kind && l > 0.85 && a > 0.5 ) { return rgb( h, s * 0.55, 0.16, a ); }
			return c;
		}
		if ( 'text' === kind ) { return rgb( h, s, cl( 0.98 - l * 0.8, 0.52, 0.94 ), a ); }
		if ( 'line' === kind ) { return l > 0.5 ? rgb( h, s, cl( 1.1 - l, 0.16, 0.34 ), a ) : c; }
		if ( l < 0.25 ) { return c; }
		if ( l >= 0.97 ) { return rgb( h, s, 0.105, a ); }
		if ( l >= 0.955 ) { return rgb( h, s, 0.075, a ); }
		return rgb( h, s, cl( 0.105 + ( 0.97 - l ) * 0.9, 0.105, 0.5 ), a );
	}
	var COLOR = /#([0-9a-f]{8}|[0-9a-f]{6}|[0-9a-f]{3,4})\b|rgba?\([^)]*\)|\b(white|black)\b/gi;
	function convertValue( v, kind, to ) { return v.replace( COLOR, function ( m ) { var c = parse( m ); return c ? fmt( map( c, kind, to ) ) : m; } ); }
	function tokenKind( n ) {
		if ( /(tx|text|ink|fg|muted|label)/.test( n ) ) { return 'text'; }
		if ( /(line|border|hair|ring|divider)/.test( n ) ) { return 'line'; }
		if ( /shadow/.test( n ) ) { return 'shadow'; }
		if ( /(acc|primary|brand|accent|green|red|orange|amber|success|warning|danger|blue|purple)/.test( n ) ) { return 'accent'; }
		return 'bg';
	}
	function featureFill( v, to ) {
		if ( /gradient|var\(/i.test( v ) ) { return false; }
		var m = /#([0-9a-f]{8}|[0-9a-f]{6}|[0-9a-f]{3,4})\b|rgba?\([^)]*\)/i.exec( v ), c = m && parse( m[ 0 ] );
		if ( ! c ) { return false; }
		var x = hsl( c );
		if ( x[ 3 ] < 0.85 ) { return false; }
		if ( x[ 1 ] > 0.3 && x[ 2 ] > 0.2 && x[ 2 ] < 0.8 ) { return true; }
		return 'light' === to ? x[ 2 ] > 0.86 : x[ 2 ] < 0.2;
	}
	function convertBlock( d, to ) {
		var keep = false;
		d = d.replace( /(^|;)(\s*)(background(?:-color|-image)?|--[\w-]+)(\s*:\s*)([^;]+)/gi, function ( m, p1, sp, prop, col, val ) {
			prop = prop.toLowerCase();
			var kind = 0 === prop.indexOf( '--' ) ? tokenKind( prop ) : 'bg';
			if ( 'bg' === kind && 0 !== prop.indexOf( '--' ) && featureFill( val, to ) ) { keep = true; return m; }
			return p1 + sp + prop + col + convertValue( val, kind, to );
		} );
		return d.replace( /(^|;)(\s*)(color|fill|stroke|caret-color|-webkit-text-fill-color|text-decoration-color|border(?:-[a-z-]+)?|outline(?:-color)?|box-shadow|text-shadow|column-rule(?:-color)?|accent-color)(\s*:\s*)([^;]+)/gi, function ( m, p1, sp, prop, col, val ) {
			var p = prop.toLowerCase(), kind;
			if ( /^(color|fill|stroke|-webkit-text-fill-color)$/.test( p ) ) { if ( keep ) { return m; } kind = 'text'; }
			else if ( /shadow/.test( p ) ) { kind = 'shadow'; }
			else if ( /^(accent-color|caret-color|text-decoration-color)$/.test( p ) ) { kind = 'text'; }
			else { kind = 'line'; }
			return p1 + sp + prop + col + convertValue( val, kind, to );
		} );
	}
	function convert( css, to ) { return String( css ).replace( /\/\*[\s\S]*?\*\//g, '' ).replace( /\{([^{}]*)\}/g, function ( m, d ) { return '{' + convertBlock( d, to ) + '}'; } ); }

	/* ------------------------------------------------------------- state */
	var prefs = C.prefs || { all:'light', builder:'dark', shop:'dark' };
	var sys = window.matchMedia ? window.matchMedia( '(prefers-color-scheme: dark)' ) : null;
	function modeFor( area ) {
		var m = ( 'velox' !== area && prefs[ area ] && 'inherit' !== prefs[ area ] ) ? prefs[ area ] : prefs.all;
		if ( 'system' === m ) { m = sys && sys.matches ? 'dark' : 'light'; }
		return 'dark' === m ? 'dark' : 'light';
	}
	var listeners = [];
	function apply() {
		var mode = modeFor( C.area || 'velox' );
		if ( html.getAttribute( 'data-vxt' ) === mode && apply.done ) { return; }
		apply.done = true;
		html.setAttribute( 'data-vxt', mode );
		Object.keys( C.urls || {} ).forEach( function ( h ) {
			var link = document.getElementById( h + '-css' ); if ( ! link ) { return; }
			var url = C.urls[ h ][ mode ];
			if ( url && link.href.split( '?' )[ 0 ] !== url.split( '?' )[ 0 ] ) {
				// Load the new sheet next to the old one, then drop the old: no unstyled flash.
				var n = link.cloneNode(); n.href = url;
				n.onload = function () { link.remove(); n.id = h + '-css'; };
				n.id = h + '-css-next'; link.parentNode.insertBefore( n, link.nextSibling );
			}
		} );
		listeners.forEach( function ( f ) { try { f( mode ); } catch ( e ) {} } );
		paint();
	}
	if ( sys && sys.addEventListener ) { sys.addEventListener( 'change', function () { document.cookie = 'velox_sys_dark=' + ( sys.matches ? 1 : 0 ) + ';path=/;max-age=31536000;SameSite=Lax'; apply(); } ); }

	function save() {
		var b = new URLSearchParams();
		b.set( 'action', 'velox_theme_save' ); b.set( 'nonce', C.nonce ); b.set( 'prefs', JSON.stringify( prefs ) );
		fetch( C.ajax, { method:'POST', body:b, credentials:'same-origin' } ).catch( function () {} );
	}

	/* --------------------------------------------------------------- the switch */
	var SUN = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.2M12 19.3v2.2M4.6 4.6l1.6 1.6M17.8 17.8l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.6 19.4l1.6-1.6M17.8 6.2l1.6-1.6"/></svg>';
	var MOON = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5z"/></svg>';
	var DOTS = '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/></svg>';
	var box = null;
	function rowHTML( key, label, opts ) {
		return '<div class="vxth-row"><span class="vxth-l">' + label + ( key === ( C.area === 'velox' ? 'all' : C.area ) ? ' <i>' + L.here + '</i>' : '' ) + '</span><div class="vxth-seg" role="radiogroup" aria-label="' + label + '">' +
			opts.map( function ( o ) { var on = prefs[ key ] === o; return '<button type="button" role="radio" aria-checked="' + on + '" class="' + ( on ? 'on' : '' ) + '" data-vxth="' + key + ':' + o + '">' + ( L[ o ] || o ) + '</button>'; } ).join( '' ) + '</div></div>';
	}
	function paint() {
		if ( ! box ) { return; }
		var mode = html.getAttribute( 'data-vxt' );
		box.querySelector( '.vxth-main' ).innerHTML = 'dark' === mode ? MOON : SUN;
		box.querySelector( '.vxth-main' ).setAttribute( 'aria-pressed', 'dark' === mode ? 'true' : 'false' );
		var p = box.querySelector( '.vxth-pop' );
		if ( p && ! p.hidden ) { fillPop( p ); }
	}
	function fillPop( p ) {
		p.innerHTML = '<b class="vxth-t">' + L.title + '</b>' +
			rowHTML( 'all', L.all, [ 'light', 'dark', 'system' ] ) +
			rowHTML( 'builder', L.builder, [ 'inherit', 'light', 'dark' ] ) +
			rowHTML( 'shop', L.shop, [ 'inherit', 'light', 'dark' ] ) +
			'<p class="vxth-n">' + L.note + '</p>';
	}
	function build() {
		if ( document.getElementById( 'vxth' ) || ! document.body ) { return; }
		var st = document.createElement( 'style' );
		st.textContent = '#vxth{position:fixed;right:16px;bottom:16px;z-index:100040;display:flex;align-items:center;gap:2px;padding:3px;border-radius:999px;font:500 12.5px/1.3 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;box-shadow:0 8px 28px rgba(0,0,0,.22);transition:opacity .2s}' +
			'html[data-vxt=dark] #vxth{background:#20232a;color:#e9ebf0;border:1px solid rgba(255,255,255,.12)}html:not([data-vxt=dark]) #vxth{background:#fff;color:#1d1d1f;border:1px solid rgba(0,0,0,.1)}' +
			'#vxth>button{display:grid;place-items:center;width:34px;height:34px;padding:0;border:0;border-radius:50%;background:none;color:inherit;cursor:pointer}' +
			'#vxth>button:hover{background:rgba(127,127,127,.16)}#vxth>button:focus-visible{outline:2px solid #2ab7f1;outline-offset:1px}' +
			'#vxth .vxth-more{width:26px;opacity:.7}' +
			'#vxth .vxth-pop{position:absolute;right:0;bottom:calc(100% + 10px);width:330px;padding:14px;border-radius:14px;box-shadow:0 18px 50px rgba(0,0,0,.3)}' +
			'html[data-vxt=dark] #vxth .vxth-pop{background:#1a1d23;border:1px solid rgba(255,255,255,.1)}html:not([data-vxt=dark]) #vxth .vxth-pop{background:#fff;border:1px solid rgba(0,0,0,.1)}' +
			'#vxth .vxth-t{display:block;margin:0 0 10px;font-size:13.5px;font-weight:650}' +
			'#vxth .vxth-row{display:flex;flex-direction:column;gap:6px;margin:0 0 12px}' +
			'#vxth .vxth-l{font-size:11.5px;font-weight:600;opacity:.7}#vxth .vxth-l i{font-style:normal;margin-left:4px;padding:1px 6px;border-radius:999px;background:rgba(42,183,241,.18);color:#2ab7f1;font-size:10px;opacity:1}' +
			'#vxth .vxth-seg{display:flex;gap:2px;padding:2px;border-radius:9px;background:rgba(127,127,127,.12)}' +
			'#vxth .vxth-seg button{flex:1;height:28px;padding:0 6px;border:0;border-radius:7px;background:none;color:inherit;font:inherit;font-size:12px;cursor:pointer;opacity:.8}' +
			'#vxth .vxth-seg button.on{background:#2ab7f1;color:#04222f;opacity:1;font-weight:600}' +
			'#vxth .vxth-n{margin:0;font-size:11.5px;opacity:.6}' +
			'body.velox-builder-body #vxth{bottom:44px}' +
			'@media print{#vxth{display:none}}';
		document.head.appendChild( st );
		box = document.createElement( 'div' ); box.id = 'vxth';
		box.innerHTML = '<button type="button" class="vxth-main" title="' + L.toggle + '" aria-label="' + L.toggle + '"></button><button type="button" class="vxth-more" aria-haspopup="true" aria-expanded="false" title="' + L.more + '" aria-label="' + L.more + '">' + DOTS + '</button><div class="vxth-pop" role="dialog" aria-label="' + L.title + '" hidden></div>';
		document.body.appendChild( box );
		box.addEventListener( 'click', function ( e ) {
			var pop = box.querySelector( '.vxth-pop' );
			if ( e.target.closest( '.vxth-main' ) ) {
				// One click flips this screen's part of Velox.
				var key = 'velox' === C.area ? 'all' : C.area, now = modeFor( C.area );
				prefs[ key ] = 'dark' === now ? 'light' : 'dark';
				apply(); save(); return;
			}
			if ( e.target.closest( '.vxth-more' ) ) {
				pop.hidden = ! pop.hidden; box.querySelector( '.vxth-more' ).setAttribute( 'aria-expanded', String( ! pop.hidden ) );
				if ( ! pop.hidden ) { fillPop( pop ); var on = pop.querySelector( 'button.on' ); if ( on ) { on.focus(); } }
				return;
			}
			var o = e.target.closest( '[data-vxth]' );
			if ( o ) { var kv = o.getAttribute( 'data-vxth' ).split( ':' ); prefs[ kv[ 0 ] ] = kv[ 1 ]; apply(); save(); fillPop( pop ); }
		} );
		document.addEventListener( 'click', function ( e ) { if ( box && ! box.contains( e.target ) ) { var p = box.querySelector( '.vxth-pop' ); if ( ! p.hidden ) { p.hidden = true; box.querySelector( '.vxth-more' ).setAttribute( 'aria-expanded', 'false' ); } } } );
		document.addEventListener( 'keydown', function ( e ) { if ( 'Escape' === e.key && box ) { var p = box.querySelector( '.vxth-pop' ); if ( ! p.hidden ) { p.hidden = true; box.querySelector( '.vxth-more' ).focus(); } } } );
		paint();
	}

	window.VeloxTheme = {
		convert:convert,
		mode:function () { return html.getAttribute( 'data-vxt' ) || modeFor( C.area || 'velox' ); },
		onChange:function ( f ) { listeners.push( f ); }
	};
	if ( ! html.getAttribute( 'data-vxt' ) ) { html.setAttribute( 'data-vxt', modeFor( C.area || 'velox' ) ); }
	apply.done = true;
	if ( document.body ) { build(); } else { document.addEventListener( 'DOMContentLoaded', build ); }
}() );
