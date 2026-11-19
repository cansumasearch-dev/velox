( function () {
	// Parity suite: insert every element from + Add, publish, then compare each
	// one in the editor canvas against the published page.
	var P = new URLSearchParams( location.search );
	if ( 'parity' !== P.get( 'vxtest' ) ) { return; }
	var errors = [];
	window.addEventListener( 'error', function ( e ) { errors.push( 'ERR ' + e.message + ' @' + ( e.lineno || '' ) ); } );
	var sleep = function ( t ) { return new Promise( function ( r ) { setTimeout( r, t ); } ); };
	var q = function ( s, r ) { return ( r || document ).querySelector( s ); };
	var qa = function ( s, r ) { return [].slice.call( ( r || document ).querySelectorAll( s ) ); };
	function cd() { var f = document.getElementById( 'vb-canvas' ); return f && f.contentDocument; }
	function report( r ) { var b = new FormData(); b.append( 'action', 'vxt_report' ); b.append( 'r', JSON.stringify( r ) ); return fetch( window.VELOX_BUILDER.ajaxurl + '?k=parity', { method: 'POST', body: b, credentials: 'same-origin' } ); }
	var BAD = /undefined|NaN|\[object Object\]/;
	function metrics( el, win ) {
		if ( ! el ) { return null; }
		var r = el.getBoundingClientRect(), cs = win.getComputedStyle( el );
		var txt = ( el.innerText || '' ).replace( /\s+/g, ' ' ).trim();
		return { w: Math.round( r.width ), h: Math.round( r.height ), disp: cs.display, pos: cs.position, vis: cs.visibility, op: cs.opacity, txt: txt.slice( 0, 60 ), tl: txt.length, kids: el.querySelectorAll( '*' ).length, imgs: el.querySelectorAll( 'img,svg,video,iframe' ).length, bad: BAD.test( el.outerHTML ) ? el.outerHTML.match( BAD )[ 0 ] : '' };
	}
	async function run() {
		var t0 = Date.now();
		while ( ! ( cd() && cd().body && q( '.vb-inspector' ) ) && Date.now() - t0 < 30000 ) { await sleep( 200 ); }
		await sleep( 1500 );
		var st = document.createElement( 'style' ); st.textContent = '*{transition:none!important;animation-duration:0s!important}'; document.head.appendChild( st );
		if ( ! q( '#vb-addmenu.open' ) ) { q( '[data-add]' ).click(); await sleep( 200 ); }
		var keys = qa( '#vb-addmenu [data-insert]' ).map( function ( b ) { return b.getAttribute( 'data-insert' ); } ).filter( function ( k ) { return 'innercontent' !== k; } );
		var only = P.get( 'only' ); if ( only ) { keys = keys.filter( function ( k ) { return only.split( ',' ).indexOf( k ) > -1; } ); }
		var ids = {}, insErr = {};
		for ( var i = 0; i < keys.length; i++ ) {
			var k = keys[ i ];
			errors.length = 0;
			var cl = q( '[data-insp-close]' ); if ( cl ) { cl.click(); await sleep( 60 ); }
			if ( ! q( '#vb-addmenu.open' ) ) { q( '[data-add]' ).click(); await sleep( 120 ); }
			var tile = q( '#vb-addmenu [data-insert="' + k + '"]' ); if ( ! tile ) { insErr[ k ] = 'no tile'; continue; }
			tile.click(); await sleep( 220 );
			var sel = cd().querySelector( '.vb-sel' );
			ids[ k ] = sel ? sel.id : null;
			if ( errors.length ) { insErr[ k ] = errors.slice( 0, 3 ); }
		}
		await sleep( 800 );
		var canvasW = document.getElementById( 'vb-canvas' ).offsetWidth;
		var cm = {};
		keys.forEach( function ( k ) { cm[ k ] = metrics( ids[ k ] && cd().getElementById( ids[ k ] ), cd().defaultView ); } );
		// publish
		var pub = q( '#vb-publish' ); if ( pub ) { pub.click(); await sleep( 2500 ); var yes = q( '#vb-ask [data-ask="yes"]' ); if ( yes ) { yes.click(); await sleep( 2500 ); } }
		var pubLabel = pub ? pub.textContent.trim() : '';
		// the published page, same width as the canvas
		var url = ( window.VELOX_BUILDER.frontUrl || '' ); url += ( url.indexOf( '?' ) > -1 ? '&' : '?' ) + 'velox_live=1&nocache=' + Date.now();
		var fr = document.createElement( 'iframe' ); fr.style.cssText = 'position:fixed;left:0;top:0;width:' + canvasW + 'px;height:900px;opacity:0;pointer-events:none;z-index:-1';
		document.body.appendChild( fr );
		var ferr = [];
		fr.src = url;
		await new Promise( function ( res ) { fr.onload = res; setTimeout( res, 15000 ); } );
		try { fr.contentWindow.addEventListener( 'error', function ( e ) { ferr.push( e.message ); } ); } catch ( e ) {}
		await sleep( 3000 );
		var fd = fr.contentDocument, fw = fr.contentWindow, fm = {};
		keys.forEach( function ( k ) { fm[ k ] = metrics( ids[ k ] && fd.getElementById( ids[ k ] ), fw ); } );
		var out = { n: keys.length, canvasW: canvasW, url: url, pubLabel: pubLabel, frontTitle: fd.title, insErr: insErr, ferr: ferr, rows: [] };
		keys.forEach( function ( k ) {
			var c = cm[ k ], f = fm[ k ], iss = [];
			if ( ! ids[ k ] ) { iss.push( 'not inserted' ); }
			if ( ! c ) { iss.push( 'no canvas el' ); }
			if ( ! f ) { iss.push( 'MISSING on front' ); }
			if ( c && c.bad ) { iss.push( 'canvas has ' + c.bad ); }
			if ( f && f.bad ) { iss.push( 'front has ' + f.bad ); }
			if ( c && f ) {
				if ( Math.abs( c.h - f.h ) > 40 && Math.abs( c.h - f.h ) > 0.3 * Math.max( c.h, f.h ) ) { iss.push( 'height ' + c.h + ' vs ' + f.h ); }
				if ( Math.abs( c.w - f.w ) > 40 && Math.abs( c.w - f.w ) > 0.3 * Math.max( c.w, f.w ) ) { iss.push( 'width ' + c.w + ' vs ' + f.w ); }
				if ( c.tl && ! f.tl ) { iss.push( 'text lost on front' ); }
				if ( ! c.tl && f.tl > 3 ) { iss.push( 'text only on front: ' + f.txt ); }
				if ( c.imgs !== f.imgs ) { iss.push( 'media ' + c.imgs + ' vs ' + f.imgs ); }
				if ( f.h < 2 && 'fixed' !== f.pos ) { iss.push( 'front invisible ' + f.w + 'x' + f.h ); }
			}
			if ( c && c.h < 4 ) { iss.push( 'canvas tiny ' + c.w + 'x' + c.h ); }
			out.rows.push( { k: k, c: c && ( c.w + 'x' + c.h + ' ' + c.disp + ' t' + c.tl ), f: f && ( f.w + 'x' + f.h + ' ' + f.disp + '/' + f.pos + ' t' + f.tl ), iss: iss } );
		} );
		out.bad = out.rows.filter( function ( r ) { return r.iss.length; } );
		out.okKeys = out.rows.filter( function ( r ) { return ! r.iss.length; } ).map( function ( r ) { return r.k; } );
		out.errors = errors.slice( 0, 10 );
		delete out.rows;
		await report( out );
	}
	setTimeout( run, 500 );
}() );
