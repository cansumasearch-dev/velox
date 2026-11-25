( function () {
	// Style-property suite (4.77): every style control in Advanced on one Div.
	// Set a value → the canvas element changes, the editor's CSS for the class
	// equals the live page's CSS (server generate_css). Then clear it.
	var P = new URLSearchParams( location.search );
	if ( 'props' !== P.get( 'vxtest' ) ) { return; }
	window.confirm = function () { return true; };
	var errors = [];
	window.addEventListener( 'error', function ( e ) { errors.push( 'ERR ' + e.message + ' @' + ( e.lineno || '' ) ); } );
	var sleep = function ( t ) { return new Promise( function ( r ) { var end = Date.now() + t, ch = new MessageChannel(); ch.port1.onmessage = function () { if ( Date.now() >= end ) { ch.port1.close(); r(); } else { ch.port2.postMessage( 0 ); } }; ch.port2.postMessage( 0 ); } ); }; // not throttled in a hidden pane
	var q = function ( s, r ) { return ( r || document ).querySelector( s ); }, qa = function ( s, r ) { return [].slice.call( ( r || document ).querySelectorAll( s ) ); };
	function cd() { var f = document.getElementById( 'vb-canvas' ); return f && f.contentDocument; }
	function insp() { return q( '.vb-inspector' ); }
	function fire( el, t ) { el.dispatchEvent( new Event( t, { bubbles: true } ) ); }
	var TAG = P.get( 'tag' ) || 'props';
	function report( r ) { var b = new FormData(); b.append( 'action', 'vxt_report' ); b.append( 'r', JSON.stringify( r ) ); return fetch( window.VELOX_BUILDER.ajaxurl + '?k=' + TAG, { method: 'POST', body: b, credentials: 'same-origin' } ); }
	function backup() { var best = null, own = P.get( 'doc' ) ? 'velox_builder_backup_' + P.get( 'doc' ) : ''; for ( var i = 0; i < localStorage.length; i++ ) { var k = localStorage.key( i ); if ( 0 === k.indexOf( 'velox_builder_backup_' ) && ( ! own || k === own ) ) { try { var b = JSON.parse( localStorage.getItem( k ) ); if ( b && ( ! best || b.at > best.at ) ) { best = b; } } catch ( e ) {} } } return best; }
	function model() { var b = backup(); return b && b.model; }
	async function backupAfter( t ) { var t0 = Date.now(); while ( Date.now() - t0 < 5000 ) { var b = backup(); if ( b && b.at >= t ) { return; } await sleep( 200 ); } } // the page check needs this change saved to the backup first
	async function serverCss() { var m = model(); var b = new FormData(); b.append( 'action', 'vxt_render' ); b.append( 'model', JSON.stringify( { tree: m.tree, content: m.content, classes: m.classes } ) ); var j = await ( await fetch( window.VELOX_BUILDER.ajaxurl, { method: 'POST', body: b, credentials: 'same-origin' } ) ).json(); return j.success ? j.data.doc_css : 'FAIL'; }
	function norm( s ) { return String( s || '' ).replace( /\/\*[\s\S]*?\*\//g, '' ).replace( /\s+/g, '' ).replace( /;}/g, '}' ); }
	function rulesFor( css, sel ) { // every rule (incl. inside @media) that mentions the selector, normalised
		var out = [], n = norm( css ), re = /([^{}]+)\{([^{}]*)\}/g, m;
		while ( ( m = re.exec( n ) ) ) { if ( m[ 1 ].indexOf( sel ) > -1 ) { out.push( m[ 1 ].replace( /^.*@media/, '@media' ) + '{' + m[ 2 ].split( ';' ).filter( Boolean ).sort().join( ';' ) + '}' ); } }
		return out.sort().join( '' );
	}
	var VAL = {
		color: '#2ab7f1', background: '#fde68a', backgroundColor: '#fde68a', borderColor: '#e11d48', outlineColor: '#e11d48', textDecorationColor: '#e11d48', caretColor: '#e11d48', accentColor: '#e11d48', fill: '#e11d48', stroke: '#e11d48',
		backgroundImage: 'linear-gradient(90deg,#ffffff,#000000)', boxShadow: '0 4px 12px rgba(0,0,0,.3)', textShadow: '0 1px 2px #000000', transform: 'rotate(3deg)', transition: 'all .3s ease', filter: 'blur(1px)', backdropFilter: 'blur(2px)',
		fontFamily: 'Georgia, serif', gridTemplateColumns: 'repeat(3, minmax(0, 1fr))', gridTemplateRows: 'repeat(2, 100px)', gridColumn: 'span 2', gridRow: 'span 2', gridTemplateAreas: '"a b"', aspectRatio: '16/9',
		objectPosition: 'top left', backgroundPosition: 'center top', backgroundSize: 'cover', transformOrigin: 'top left', clipPath: 'inset(4px)', content: '"x"', cursor: 'pointer', opacity: '0.55', zIndex: '7', order: '2', flexGrow: '2', flexShrink: '0', flexBasis: '33%', lineHeight: '1.7', fontWeight: '700', mixBlendMode: 'multiply'
	};
	async function setCtl( c, v ) {
		if ( 'SELECT' === c.tagName ) { c.value = v; fire( c, 'change' ); }
		else { c.focus(); c.value = v; fire( c, 'input' ); fire( c, 'change' ); document.body.dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true } ) ); }
		await sleep( 350 );
	}
	function find( kind, prop ) {
		if ( 'seg' === kind ) { return qa( '.vb-inspector button[data-set="' + prop + '"][data-val]' ); }
		if ( 'sel' === kind ) { return q( '.vb-inspector select[data-setsel="' + prop + '"]' ); }
		if ( 'num' === kind ) { return q( '.vb-inspector input[data-setnum="' + prop + '"]' ); }
		return q( '.vb-inspector input[data-set="' + prop + '"]' );
	}
	function openAll() { qa( '.vb-inspector .closed > [data-block], .vb-inspector [data-setsec].closed' ).forEach( function ( h ) { h.click(); } ); }
	async function run() {
		var t0 = Date.now();
		while ( ! ( cd() && cd().body && insp() ) && Date.now() - t0 < 30000 ) { await sleep( 200 ); }
		await sleep( 1500 );
		var st = document.createElement( 'style' ); st.textContent = '*{transition:none!important;animation-duration:0s!important}'; document.head.appendChild( st );
		var el0 = P.get( 'el' ) || 'div';
		if ( ! q( '#vb-addmenu.open' ) ) { q( '[data-add]' ).click(); await sleep( 200 ); }
		q( '#vb-addmenu [data-insert="' + el0 + '"]' ).click(); await sleep( 800 );
		var id = cd().querySelector( '.vb-sel' ).id;
		var cls = ( ( q( '#vb-clspick .vb-sel-v' ) || {} ).textContent || '' ).trim();
		var tab = q( '.vb-tab[data-tab="all"]' ); if ( tab ) { tab.click(); await sleep( 300 ); }
		if ( q( '[data-onlyset].on, [data-onlyset][aria-pressed="true"]' ) ) { q( '[data-onlyset]' ).click(); await sleep( 200 ); }
		openAll(); await sleep( 300 ); openAll(); await sleep( 300 );
		var list = [];
		qa( '.vb-inspector input[data-setnum]' ).forEach( function ( c ) { list.push( [ 'num', c.getAttribute( 'data-setnum' ) ] ); } );
		qa( '.vb-inspector input[data-set]' ).forEach( function ( c ) { list.push( [ 'txt', c.getAttribute( 'data-set' ) ] ); } );
		qa( '.vb-inspector select[data-setsel]' ).forEach( function ( c ) { list.push( [ 'sel', c.getAttribute( 'data-setsel' ) ] ); } );
		var segs = {}; qa( '.vb-inspector button[data-set][data-val]' ).forEach( function ( b ) { segs[ b.getAttribute( 'data-set' ) ] = 1; } );
		Object.keys( segs ).forEach( function ( p ) { list.push( [ 'seg', p ] ); } );
		var seenP = {}; list = list.filter( function ( x ) { var k = x[ 0 ] + x[ 1 ]; if ( seenP[ k ] ) { return false; } seenP[ k ] = 1; return true; } );
		var rows = [];
		for ( var i = 0; i < list.length; i++ ) {
			var kind = list[ i ][ 0 ], prop = list[ i ][ 1 ], r = { p: prop, kind: kind, iss: [] };
			errors.length = 0;
			openAll(); await sleep( 120 );
			var el = cd().getElementById( id ), before = el ? cd().defaultView.getComputedStyle( el ).cssText || JSON.stringify( [].slice.call( cd().defaultView.getComputedStyle( el ) ).map( function ( p ) { return cd().defaultView.getComputedStyle( el ).getPropertyValue( p ); } ) ) : '';
			var c = find( kind, prop ), val = '';
			if ( ! c || ( Array.isArray( c ) && ! c.length ) ) { r.iss.push( 'control gone' ); rows.push( r ); continue; }
			if ( 'seg' === kind ) { var off = c.filter( function ( b ) { return ! b.classList.contains( 'on' ); } ).pop(); if ( ! off ) { r.iss.push( 'no option' ); rows.push( r ); continue; } val = off.getAttribute( 'data-val' ); off.click(); await sleep( 350 ); }
			else if ( 'sel' === kind ) { if ( c.options.length < 2 ) { rows.push( r ); continue; } val = c.options[ c.options.length - 1 ].value || c.options[ 1 ].value; await setCtl( c, val ); }
			else if ( 'num' === kind ) { val = VAL[ prop ] || ( /opacity/i.test( prop ) ? '0.55' : '17' ); await setCtl( c, val ); }
			else { val = VAL[ prop ] || ( /color/i.test( prop ) ? '#e11d48' : '17px' ); await setCtl( c, val ); }
			r.v = val;
			var tChange = Date.now() - 400; await sleep( 900 ); await backupAfter( tChange );
			var el2 = cd().getElementById( id );
			var after = el2 ? JSON.stringify( [].slice.call( cd().defaultView.getComputedStyle( el2 ) ).map( function ( p ) { return cd().defaultView.getComputedStyle( el2 ).getPropertyValue( p ); } ) ) : 'GONE';
			if ( 'GONE' === after ) { r.iss.push( 'element gone' ); }
			var befStr = before;
			if ( after === befStr ) { r.iss.push( 'canvas unchanged' ); }
			var edCss = rulesFor( ( cd().getElementById( 'vb-style' ) || {} ).textContent || '', cls ) + rulesFor( ( cd().getElementById( 'vb-style' ) || {} ).textContent || '', '#' + id );
			var svCss = await serverCss(); svCss = rulesFor( svCss, cls ) + rulesFor( svCss, '#' + id );
			if ( edCss !== svCss ) { r.iss.push( 'editor CSS ≠ page CSS' ); r.ed = edCss.slice( 0, 300 ); r.sv = svCss.slice( 0, 300 ); }
			if ( ! svCss ) { r.iss.push( 'no CSS on the page' ); }
			if ( errors.length ) { r.iss = r.iss.concat( errors.slice( 0, 2 ) ); }
			// clear it again
			var c3 = find( kind, prop );
			if ( 'seg' === kind ) { var onb = ( c3 || [] ).filter( function ( b ) { return b.classList.contains( 'on' ); } )[ 0 ]; if ( onb ) { onb.click(); await sleep( 300 ); } }
			else if ( c3 ) { await setCtl( c3, '' ); }
			rows.push( r );
			if ( 0 === i % 10 ) { await report( { partial: true, done: i, of: list.length, rows: rows } ); }
		}
		await report( { done: list.length, of: list.length, cls: cls, ms: Date.now() - t0, bad: rows.filter( function ( r ) { return r.iss.length; } ), ok: rows.filter( function ( r ) { return ! r.iss.length; } ).map( function ( r ) { return r.p + '=' + r.v; } ) } );
		document.title = 'VXDONE';
	}
	setTimeout( run, 500 );
}() );
