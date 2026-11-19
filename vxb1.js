( function () {
	var errors = [];
	window.addEventListener( 'error', function ( e ) { errors.push( 'ERR ' + e.message + ' @' + ( e.lineno || '' ) ); } );
	window.addEventListener( 'unhandledrejection', function ( e ) { errors.push( 'REJ ' + ( e.reason && e.reason.message || e.reason ) ); } );
	var oce = console.error; console.error = function () { errors.push( 'CE ' + [].slice.call( arguments ).join( ' ' ).slice( 0, 200 ) ); return oce.apply( console, arguments ); };
	var sleep = function ( t ) { return new Promise( function ( r ) { setTimeout( r, t ); } ); };
	var q = function ( s, r ) { return ( r || document ).querySelector( s ); };
	var qa = function ( s, r ) { return [].slice.call( ( r || document ).querySelectorAll( s ) ); };
	var BAD = /undefined|NaN|\[object Object\]/;
	var mode = new URLSearchParams( location.search ).get( 'vxtest' );
	function report( k, r ) {
		var b = new FormData(); b.append( 'action', 'vxt_report' ); b.append( 'r', JSON.stringify( r ) );
		return fetch( window.VELOX_BUILDER.ajaxurl + '?k=' + k, { method: 'POST', body: b, credentials: 'same-origin' } );
	}
	function canvasDoc() { var f = document.getElementById( 'vb-canvas' ); return f && f.contentDocument; }
	function fire( el, type ) { el.dispatchEvent( new Event( type, { bubbles: true } ) ); }
	function inspText() { var i = q( '.vb-inspector' ); return i ? i.innerText : ''; }
	async function openAdd() {
		if ( ! q( '#vb-addmenu.open' ) ) { q( '[data-add]' ).click(); await sleep( 120 ); }
	}
	async function run() {
		var t0 = Date.now();
		while ( ! ( canvasDoc() && canvasDoc().body && q( '.vb-inspector' ) ) && Date.now() - t0 < 30000 ) { await sleep( 200 ); }
		await sleep( 1500 );
		qa( '[class*=wizard]' ).forEach( function ( e ) { e.remove(); } );
		await openAdd();
		var keys = qa( '#vb-addmenu [data-insert]' ).map( function ( b ) { return b.getAttribute( 'data-insert' ); } );
		var only = new URLSearchParams( location.search ).get( 'only' );
		if ( only ) { keys = keys.filter( function ( k ) { return only.split( ',' ).indexOf( k ) >= 0; } ); }
		var start = +( new URLSearchParams( location.search ).get( 'from' ) || 0 ), cnt = +( new URLSearchParams( location.search ).get( 'n' ) || 999 );
		keys = keys.slice( start, start + cnt );
		var out = { total: keys.length, results: [], globalErrors: [] };
		for ( var ki = 0; ki < keys.length; ki++ ) {
			var key = keys[ ki ], r = { key: key, issues: [] };
			errors.length = 0;
			try {
				await openAdd();
				var tile = q( '#vb-addmenu [data-insert="' + key + '"]' );
				if ( ! tile ) { r.issues.push( 'no tile' ); out.results.push( r ); continue; }
				tile.click();
				await sleep( 250 );
				var cd = canvasDoc();
				var selEl = cd.querySelector( '.vb-sel' );
				if ( ! selEl ) { r.issues.push( 'nothing selected on canvas after insert' ); }
				else {
					var rc = selEl.getBoundingClientRect();
					r.size = Math.round( rc.width ) + 'x' + Math.round( rc.height );
					if ( rc.width < 4 || rc.height < 4 ) { r.issues.push( 'tiny/invisible on canvas ' + r.size ); }
					if ( BAD.test( selEl.outerHTML ) ) { r.issues.push( 'canvas html has ' + selEl.outerHTML.match( BAD )[ 0 ] + ': ' + selEl.outerHTML.slice( Math.max( 0, selEl.outerHTML.search( BAD ) - 80 ), selEl.outerHTML.search( BAD ) + 40 ) ); }
				}
				r.head = ( q( '.vb-insp-tx b' ) || {} ).textContent;
				// tabs
				var tabs = [ 'ess', 'all', 'set' ];
				for ( var ti = 0; ti < tabs.length; ti++ ) {
					var tb = q( '.vb-tab[data-tab="' + tabs[ ti ] + '"]' );
					if ( ! tb ) { r.issues.push( 'no tab ' + tabs[ ti ] ); continue; }
					tb.click(); await sleep( 120 );
					var txt = inspText();
					if ( BAD.test( txt ) ) { r.issues.push( 'inspector ' + tabs[ ti ] + ' shows ' + txt.match( BAD )[ 0 ] + ' near "' + txt.slice( Math.max( 0, txt.search( BAD ) - 50 ), txt.search( BAD ) + 20 ).replace( /\s+/g, ' ' ) + '"' ); }
					qa( '.vb-inspector input, .vb-inspector select' ).forEach( function ( i ) { if ( BAD.test( i.value ) ) { r.issues.push( 'field value ' + i.value + ' (' + ( i.getAttribute( 'data-setel' ) || i.getAttribute( 'data-prop' ) || i.className ) + ')' ); } } );
				}
				// exercise every Settings control
				var nCtl = 0;
				var seen = {};
				for ( var pass = 0; pass < 2; pass++ ) {
					var ctrls = qa( '.vb-inspector [data-setel]' );
					for ( var ci = 0; ci < ctrls.length; ci++ ) {
						var c = ctrls[ ci ]; if ( ! document.contains( c ) ) { continue; }
						var k = c.getAttribute( 'data-setel' ) + ( c.getAttribute( 'data-segval' ) || '' );
						if ( seen[ k ] ) { continue; } seen[ k ] = 1; nCtl++;
						if ( 'BUTTON' === c.tagName ) {
							var sibs = qa( 'button[data-setel="' + c.getAttribute( 'data-setel' ) + '"]' );
							if ( c !== sibs[ sibs.length - 1 ] ) { continue; }
							c.click();
						} else if ( 'SELECT' === c.tagName ) { c.selectedIndex = c.options.length - 1; fire( c, 'change' ); }
						else if ( 'checkbox' === c.type ) { c.click(); }
						else if ( c.classList.contains( 'num' ) ) { c.value = '7'; fire( c, 'input' ); fire( c, 'change' ); }
						else if ( 'TEXTAREA' === c.tagName ) { c.value = 'Test <b>body</b>'; fire( c, 'input' ); fire( c, 'change' ); }
						else { c.value = /colou?r|Bg|Fg|Color/i.test( c.getAttribute( 'data-setel' ) ) ? '#ff3366' : 'Testwert'; fire( c, 'input' ); fire( c, 'change' ); }
						await sleep( 60 );
					}
					await sleep( 450 );
				}
				r.controls = nCtl;
				// repeater: add an item
				var ra = q( '.vb-inspector [data-repadd]' );
				if ( ra ) { var before = qa( '.vb-rep-row' ).length; ra.click(); await sleep( 200 ); if ( qa( '.vb-rep-row' ).length !== before + 1 ) { r.issues.push( 'repeater add did not add a row' ); } }
				await sleep( 500 );
				cd = canvasDoc();
				var s2 = cd.querySelector( '.vb-sel' );
				if ( s2 ) {
					if ( BAD.test( s2.outerHTML ) ) { r.issues.push( 'after settings canvas has ' + s2.outerHTML.match( BAD )[ 0 ] + ': ' + s2.outerHTML.slice( Math.max( 0, s2.outerHTML.search( BAD ) - 80 ), s2.outerHTML.search( BAD ) + 40 ) ); }
				} else { r.issues.push( 'selection lost after settings' ); }
				// undo all, redo all
				var u = q( '#vb-undo' ); for ( var x = 0; x < 3; x++ ) { u.click(); await sleep( 40 ); }
				var rd = q( '#vb-redo' ); for ( var y = 0; y < 3; y++ ) { rd.click(); await sleep( 40 ); }
				// delete: select its root ancestor via structure?
				var del = q( '.vb-inspector [data-del]' );
				if ( del ) { del.click(); await sleep( 200 ); }
				// clean any leftovers at root
				var guard = 0;
				while ( canvasDoc().querySelector( 'body > [data-node], body .vb-root > [data-node]' ) && guard++ < 10 ) {
					var d2 = q( '.vb-inspector [data-del]' ); if ( ! d2 ) { break; } d2.click(); await sleep( 150 );
				}
			} catch ( e ) { r.issues.push( 'EXC ' + e.message ); }
			if ( errors.length ) { r.issues = r.issues.concat( errors.slice( 0, 5 ) ); }
			out.results.push( r );
		}
		out.left = canvasDoc().querySelectorAll( '[data-node]' ).length;
		out.ms = Date.now() - t0;
		out.bad = out.results.filter( function ( r ) { return r.issues.length; } );
		out.ok = out.results.filter( function ( r ) { return ! r.issues.length; } ).map( function ( r ) { return r.key + ':' + r.size + ':' + r.controls; } );
		delete out.results;
		await report( 'b_' + mode, out );
		document.title = 'VXDONE';
	}
	if ( 'elements' === mode ) { setTimeout( run, 500 ); }
}() );
