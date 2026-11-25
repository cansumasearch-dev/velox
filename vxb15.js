( function () {
	// Deep element suite (4.77): one element at a time on an empty page. Every
	// setting through every option; after each change the editor (element HTML,
	// its CSS, computed styles) is compared with the live-page render of the
	// same model (server, via vxt_render). Repeater items: add, edit, remove.
	// Then the element is deleted and the next one comes.
	var P = new URLSearchParams( location.search );
	if ( 'deep' !== P.get( 'vxtest' ) ) { return; }
	window.confirm = function () { return true; };
	var errors = [];
	window.addEventListener( 'error', function ( e ) { errors.push( 'ERR ' + e.message + ' @' + ( e.lineno || '' ) ); } );
	window.addEventListener( 'unhandledrejection', function ( e ) { errors.push( 'REJ ' + ( e.reason && e.reason.message || e.reason ) ); } );
	var sleep = function ( t ) { return new Promise( function ( r ) { var end = Date.now() + t, ch = new MessageChannel(); ch.port1.onmessage = function () { if ( Date.now() >= end ) { ch.port1.close(); r(); } else { ch.port2.postMessage( 0 ); } }; ch.port2.postMessage( 0 ); } ); }; // not throttled in a hidden pane
	var q = function ( s, r ) { return ( r || document ).querySelector( s ); }, qa = function ( s, r ) { return [].slice.call( ( r || document ).querySelectorAll( s ) ); };
	function cd() { var f = document.getElementById( 'vb-canvas' ); return f && f.contentDocument; }
	function insp() { return q( '.vb-inspector' ); }
	function fire( el, t ) { el.dispatchEvent( new Event( t, { bubbles: true } ) ); }
	var TAG = P.get( 'tag' ) || 'deep';
	function report( r ) { var b = new FormData(); b.append( 'action', 'vxt_report' ); b.append( 'r', JSON.stringify( r ) ); return fetch( window.VELOX_BUILDER.ajaxurl + '?k=' + TAG, { method: 'POST', body: b, credentials: 'same-origin' } ); }
	function hash( s ) { var h = 0; s = String( s ); for ( var i = 0; i < s.length; i++ ) { h = ( h * 31 + s.charCodeAt( i ) ) | 0; } return h; }
	var BAD = /\bundefined\b|\bNaN\b|\[object Object\]/;
	var PROPS = [ 'display', 'flexDirection', 'gridTemplateColumns', 'gap', 'width', 'height', 'color', 'backgroundColor', 'backgroundImage', 'fontSize', 'fontWeight', 'textAlign', 'borderTopWidth', 'borderTopColor', 'borderTopLeftRadius', 'paddingTop', 'opacity', 'transform', 'boxShadow', 'justifyContent', 'alignItems', 'aspectRatio', 'objectFit', 'position', 'overflow', 'textTransform', 'letterSpacing', 'lineHeight' ];
	function model() {
		var best = null;
		for ( var i = 0; i < localStorage.length; i++ ) { var k = localStorage.key( i ); if ( 0 === k.indexOf( 'velox_builder_backup_' ) && ( ! P.get( 'doc' ) || k === 'velox_builder_backup_' + P.get( 'doc' ) ) ) { try { var b = JSON.parse( localStorage.getItem( k ) ); if ( b && ( ! best || b.at > best.at ) ) { best = b; } } catch ( e ) {} } }
		return best && best.model;
	}
	function canvasSig( id ) {
		var el = cd().getElementById( id ); if ( ! el ) { return { h: 'GONE', s: 'GONE' }; }
		var cl = el.cloneNode( true );
		[ cl ].concat( [].slice.call( cl.querySelectorAll( '*' ) ) ).forEach( function ( n ) {
			if ( ! n.removeAttribute ) { return; }
			[ 'data-node', 'contenteditable', 'spellcheck', 'data-vb-item', 'data-vb-f', 'draggable', 'style' ].forEach( function ( a ) { if ( 'style' !== a || n !== cl ) { n.removeAttribute( a ); } } );
			if ( n.classList ) { [].slice.call( n.classList ).forEach( function ( c ) { if ( /^vb-/.test( c ) ) { n.classList.remove( c ); } } ); }
		} );
		var cs = cd().defaultView.getComputedStyle( el ), st = PROPS.map( function ( p ) { return cs[ p ]; } ).join( '|' );
		var inner = el.firstElementChild ? cd().defaultView.getComputedStyle( el.firstElementChild ) : null;
		var css = ( ( cd().getElementById( 'vb-preview-css' ) || {} ).textContent || '' ).split( '}' ).filter( function ( r ) { return r.indexOf( '#' + id ) > -1; } ).join( '}' );
		return { h: hash( cl.outerHTML ) + ':' + hash( css ), s: hash( st + ( inner ? PROPS.map( function ( p ) { return inner[ p ]; } ).join( '|' ) : '' ) ), raw: cl.outerHTML.length };
	}
	async function pageSig() {
		var m = model(); if ( ! m ) { return { h: 'NOMODEL', errs: [] }; }
		var b = new FormData(); b.append( 'action', 'vxt_render' ); b.append( 'model', JSON.stringify( { tree: m.tree, content: m.content, classes: m.classes } ) );
		for ( var tries = 0; tries < 2; tries++ ) { // one retry: the sandbox's PHP sometimes drops a request
			try {
				var j = await ( await fetch( window.VELOX_BUILDER.ajaxurl, { method: 'POST', body: b, credentials: 'same-origin' } ) ).json();
				if ( ! j.success ) { if ( tries ) { return { h: 'FAIL', errs: [ 'render failed' ] }; } await sleep( 600 ); continue; }
				// The page's own CSS for the element counts too (float = per-element CSS, doc = classes/overrides).
				return { h: hash( j.data.html ) + ':' + hash( ( j.data.float || '' ) + ( j.data.doc_css || '' ) ), errs: j.data.errs || [], bad: BAD.test( j.data.html ) ? j.data.html.match( BAD )[ 0 ] : '', len: j.data.html.length };
			} catch ( e ) { if ( tries ) { return { h: 'EXC', errs: [ e.message ] }; } await sleep( 600 ); }
		}
	}
	async function settle() { await sleep( 1400 ); }
	async function snap( id ) { var c = canvasSig( id ); var p = await pageSig(); return { c: c, p: p }; }
	function cmp( prev, cur ) {
		var cc = prev.c.h !== cur.c.h || prev.c.s !== cur.c.s, pc = prev.p.h !== cur.p.h;
		return cc && pc ? 'both' : ( cc ? 'editor-only' : ( pc ? 'page-only' : 'none' ) );
	}
	async function apply( k, kind, v ) {
		var c;
		if ( 'seg' === kind ) { c = q( '.vb-inspector button[data-setel="' + k + '"][data-segval="' + CSS.escape( v ) + '"]' ); if ( c ) { c.click(); } }
		else if ( 'sel' === kind ) { c = q( '.vb-inspector select[data-setel="' + k + '"]' ); if ( c ) { c.value = v; fire( c, 'change' ); } }
		else if ( 'chk' === kind ) { c = q( '.vb-inspector input[type=checkbox][data-setel="' + k + '"]' ); if ( c && c.checked !== v ) { c.click(); } }
		else { c = q( '.vb-inspector input[data-setel="' + k + '"], .vb-inspector textarea[data-setel="' + k + '"]' ); if ( c ) { c.focus(); c.value = v; fire( c, 'input' ); fire( c, 'change' ); document.body.dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true } ) ); } }
		return !! c;
	}
	function controls() {
		var out = {};
		qa( '[data-setel]', insp() ).forEach( function ( c ) {
			var k = c.getAttribute( 'data-setel' ); if ( out[ k ] ) { return; }
			if ( c.hasAttribute( 'data-segval' ) ) { var bs = qa( 'button[data-setel="' + k + '"][data-segval]', insp() ), on = bs.filter( function ( b ) { return b.classList.contains( 'on' ); } )[ 0 ]; var sv = bs.map( function ( b ) { return b.getAttribute( 'data-segval' ); } ); if ( on ) { sv = [ on.getAttribute( 'data-segval' ) ].concat( sv.filter( function ( x ) { return x !== on.getAttribute( 'data-segval' ); } ) ); } out[ k ] = { kind: 'seg', vals: sv }; }
			else if ( 'SELECT' === c.tagName ) { var ov = [].map.call( c.options, function ( o ) { return o.value; } ); out[ k ] = { kind: 'sel', vals: [ c.value ].concat( ov.filter( function ( x ) { return x !== c.value; } ) ) }; }
			else if ( 'checkbox' === c.type ) { out[ k ] = { kind: 'chk', vals: [ ! c.checked, c.checked ] }; }
			else if ( c.readOnly ) { out[ k ] = { kind: 'skip', why: 'read-only' }; }
			else if ( c.closest( '.vb-media-row' ) ) { out[ k ] = { kind: 'txt', vals: [ ( window.VELOX_BUILDER.imgBase || '' ) + 'vx-ph-avatar.svg', '' ] }; }
			else if ( c.parentNode && c.parentNode.querySelector( '[data-setelswatch]' ) ) { out[ k ] = { kind: 'txt', vals: [ '#2ab7f1', '#e11d48' ] }; }
			else if ( c.classList.contains( 'num' ) ) { out[ k ] = { kind: 'txt', vals: [ '3', '13' ] }; }
			else if ( 'TEXTAREA' === c.tagName && c.closest( '.vb-code-wrap' ) ) { out[ k ] = { kind: 'txt', vals: [ '<p class="vxt-a">Alpha</p>', '<p class="vxt-b">Beta</p>' ] }; }
			else { out[ k ] = { kind: 'txt', vals: [ /url|link|href/i.test( k ) ? 'https://example.com/a' : 'Alpha ' + k, /url|link|href/i.test( k ) ? 'https://example.com/b' : 'Beta ' + k ] }; }
		} );
		return out;
	}
	async function deep( k ) {
		var r = { k: k, iss: [], n: 0, none: [], eonly: [], ponly: [], errs: [] };
		errors.length = 0;
		var cl = q( '[data-insp-close]' ); if ( cl ) { cl.click(); await sleep( 80 ); }
		if ( ! q( '#vb-addmenu.open' ) ) { q( '[data-add]' ).click(); await sleep( 150 ); }
		var tile = q( '#vb-addmenu [data-insert="' + k + '"]' ); if ( ! tile ) { r.iss.push( 'no tile' ); return r; }
		tile.click(); await settle();
		var selEl = cd().querySelector( '.vb-sel' ), id = selEl && selEl.id;
		if ( ! id ) { r.iss.push( 'not selected after insert' ); return r; }
		var t = q( '.vb-tab[data-tab="ess"]' ); if ( t ) { t.click(); await sleep( 150 ); }
		var prev = await snap( id ), seen = {};
		if ( prev.p.bad ) { r.iss.push( 'page shows ' + prev.p.bad ); }
		if ( BAD.test( ( insp() || {} ).innerText || '' ) ) { r.iss.push( 'inspector shows ' + insp().innerText.match( BAD )[ 0 ] ); }
		// Each setting through all its values. Settings that appear only for one
		// value (when:) are tested right then, nested, before going on; the
		// setting then goes back to its first value.
		async function testKey( key, c, depth ) {
			seen[ key ] = 1;
			if ( 'skip' === c.kind ) { r.iss.push( key + ' skipped: ' + c.why ); return; }
			var anyChange = false;
			for ( var j = 0; j < c.vals.length; j++ ) {
				var v = c.vals[ j ];
				if ( ! ( await apply( key, c.kind, v ) ) ) { r.iss.push( key + '=' + String( v ).slice( 0, 20 ) + ' control vanished' ); break; }
				await settle(); r.n++;
				var cur = await snap( id ), d = cmp( prev, cur );
				if ( 'GONE' === cur.c.h ) { r.iss.push( 'element gone after ' + key + '=' + v ); throw new Error( 'gone' ); }
				if ( cur.p.errs && cur.p.errs.length ) { r.errs = r.errs.concat( cur.p.errs.map( function ( e ) { return key + '=' + v + ': ' + e; } ) ).slice( 0, 8 ); }
				if ( cur.p.bad ) { r.iss.push( 'page shows ' + cur.p.bad + ' after ' + key + '=' + v ); }
				if ( 'both' === d ) { anyChange = true; }
				else if ( 'editor-only' === d ) { r.eonly.push( key + '=' + String( v ).slice( 0, 24 ) ); anyChange = true; }
				else if ( 'page-only' === d ) { r.ponly.push( key + '=' + String( v ).slice( 0, 24 ) ); anyChange = true; }
				prev = cur;
				if ( depth < 2 ) {
					var now = controls(), fresh = Object.keys( now ).filter( function ( x ) { return ! seen[ x ]; } );
					for ( var f = 0; f < fresh.length; f++ ) { await testKey( fresh[ f ], now[ fresh[ f ] ], depth + 1 ); }
				}
			}
			if ( ! anyChange && c.vals.length > 1 ) { r.none.push( key ); }
			// Back to the first value, so settings that depend on it stay reachable.
			if ( c.vals.length > 1 && 'chk' !== c.kind ) { if ( await apply( key, c.kind, c.vals[ 0 ] ) ) { await settle(); prev = await snap( id ); } }
		}
		try {
			for ( var pass = 0; pass < 3; pass++ ) {
				var cs = controls(), keys = Object.keys( cs ).filter( function ( x ) { return ! seen[ x ]; } );
				if ( ! keys.length ) { break; }
				for ( var i = 0; i < keys.length; i++ ) { if ( ! seen[ keys[ i ] ] ) { var c2 = controls()[ keys[ i ] ]; if ( c2 ) { await testKey( keys[ i ], c2, 0 ); } } }
			}
		} catch ( ex ) { if ( 'gone' === ex.message ) { return r; } throw ex; }
		// Items (slides, tabs, rows …): add one, edit its first field, remove it.
		var add = q( '.vb-inspector [data-repadd]' );
		if ( add ) {
			var before = await snap( id ); add.click(); await settle(); var afterAdd = await snap( id );
			if ( 'none' === cmp( before, afterAdd ) ) { r.iss.push( 'adding an item changed nothing' ); }
			var rows = qa( '.vb-inspector [data-repi]' ), last = rows[ rows.length - 1 ];
			var f = last && last.querySelector( 'input[data-repset], textarea[data-repset]' );
			if ( f ) { f.focus(); f.value = 'Gamma item'; fire( f, 'input' ); fire( f, 'change' ); await settle(); var afterEdit = await snap( id ); if ( 'none' === cmp( afterAdd, afterEdit ) ) { r.iss.push( 'editing an item changed nothing' ); } else if ( 'both' !== cmp( afterAdd, afterEdit ) ) { r.iss.push( 'item edit ' + cmp( afterAdd, afterEdit ) ); } afterAdd = afterEdit; }
			var del = last && last.querySelector( '[data-repdel]' );
			if ( del ) { del.click(); await settle(); var afterDel = await snap( id ); if ( 'none' === cmp( afterAdd, afterDel ) ) { r.iss.push( 'removing an item changed nothing' ); } }
		}
		if ( errors.length ) { r.errs = r.errs.concat( errors.slice( 0, 4 ) ); }
		// Remove it: the next element starts on an empty page.
		var e2 = cd().getElementById( id ); if ( e2 ) { e2.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) ); await sleep( 200 ); }
		document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Delete', code: 'Delete', bubbles: true, cancelable: true } ) ); await sleep( 500 );
		if ( cd().getElementById( id ) ) { r.iss.push( 'Delete key did not remove it' ); }
		return r;
	}
	async function run() {
		var t0 = Date.now();
		while ( ! ( cd() && cd().body && insp() ) && Date.now() - t0 < 30000 ) { await sleep( 200 ); }
		await sleep( 1500 );
		var st = document.createElement( 'style' ); st.textContent = '*{transition:none!important;animation-duration:0s!important}'; document.head.appendChild( st );
		if ( ! q( '#vb-addmenu.open' ) ) { q( '[data-add]' ).click(); await sleep( 200 ); }
		var keys = qa( '#vb-addmenu [data-insert]' ).map( function ( b ) { return b.getAttribute( 'data-insert' ); } ).filter( function ( k ) { return 'innercontent' !== k; } );
		var only = P.get( 'only' ); if ( only ) { keys = keys.filter( function ( k ) { return only.split( ',' ).indexOf( k ) > -1; } ); }
		var from = +( P.get( 'from' ) || 0 ), to = +( P.get( 'to' ) || keys.length ); keys = keys.slice( from, to );
		var rows = [];
		for ( var i = 0; i < keys.length; i++ ) {
			var r; try { r = await deep( keys[ i ] ); } catch ( ex ) { r = { k: keys[ i ], iss: [ 'EXC ' + ex.message ] }; }
			rows.push( r );
			await report( { partial: true, done: i + 1, of: keys.length, rows: rows } );
		}
		await report( { done: keys.length, of: keys.length, ms: Date.now() - t0, rows: rows } );
		document.title = 'VXDONE';
	}
	setTimeout( run, 500 );
}() );
