( function () {
	// Full audit (4.76): every element from + Add, every element setting through
	// every option, layout tiles, motion switches, common styles, hover, a second
	// class; then publish and check each element and its styles on the page.
	var P = new URLSearchParams( location.search );
	if ( 'audit2' !== P.get( 'vxtest' ) ) { return; }
	window.confirm = function () { return true; };
	var errors = [];
	window.addEventListener( 'error', function ( e ) { errors.push( 'ERR ' + e.message + ' @' + ( e.lineno || '' ) ); } );
	window.addEventListener( 'unhandledrejection', function ( e ) { errors.push( 'REJ ' + ( e.reason && e.reason.message || e.reason ) ); } );
	var sleep = function ( t ) { return new Promise( function ( r ) { setTimeout( r, t ); } ); };
	var q = function ( s, r ) { return ( r || document ).querySelector( s ); }, qa = function ( s, r ) { return [].slice.call( ( r || document ).querySelectorAll( s ) ); };
	function cd() { var f = document.getElementById( 'vb-canvas' ); return f && f.contentDocument; }
	function fire( el, t ) { el.dispatchEvent( new Event( t, { bubbles: true } ) ); }
	function key( k, o, target ) { o = o || {}; ( target || document.body ).dispatchEvent( new KeyboardEvent( 'keydown', Object.assign( { key: k, bubbles: true, cancelable: true }, o ) ) ); }
	function sel() { var s = cd().querySelector( '.vb-sel' ); return s && s.id; }
	function css() { return ( cd().getElementById( 'vb-style' ) || { textContent: '' } ).textContent.replace( /\s+/g, ' ' ); }
	var TAG = P.get( 'tag' ) || 'audit2';
	function report( r ) { var b = new FormData(); b.append( 'action', 'vxt_report' ); b.append( 'r', JSON.stringify( r ) ); return fetch( window.VELOX_BUILDER.ajaxurl + '?k=' + TAG, { method: 'POST', body: b, credentials: 'same-origin' } ); }
	var BAD = /\bundefined\b|\bNaN\b|\[object Object\]/;
	async function blur() { document.body.dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true } ) ); await sleep( 60 ); }
	async function tab( t ) { var b = q( '.vb-tab[data-tab="' + t + '"]' ); if ( ! b ) { return false; } b.click(); await sleep( 120 ); return true; }
	async function pickOpt( a, v ) { var b = q( a ); if ( ! b ) { return false; } b.click(); await sleep( 120 ); var row = q( '.vxs-pop [data-vxs-v="' + v + '"]' ); if ( ! row ) { if ( window.VeloxSelect ) { VeloxSelect.close(); } return false; } row.click(); await sleep( 160 ); if ( window.VeloxSelect ) { VeloxSelect.close(); } return true; }
	async function setVia( prop, type, val ) {
		var f = q( '#vb-sfilter' ); if ( f ) { f.value = ''; fire( f, 'input' ); await sleep( 60 ); }
		var c = 'num' === type ? q( '.vb-inspector input[data-setnum="' + prop + '"]' ) : q( '.vb-inspector input[data-set="' + prop + '"]' );
		if ( c ) { c.focus(); c.value = val; fire( c, 'input' ); await sleep( 140 ); await blur(); }
		return !! c;
	}
	function insp() { return q( '.vb-inspector' ); }
	function alive( id ) { return !! cd().getElementById( id ); }
	// Every element setting, every option. The inspector redraws after each
	// change, so controls are found again by key every time.
	async function workSettings( id, r ) {
		var seen = {}, n = 0, pass;
		for ( pass = 0; pass < 3; pass++ ) {
			var keys = {}; qa( '[data-setel]', insp() ).forEach( function ( c ) { keys[ c.getAttribute( 'data-setel' ) ] = 1; } );
			var fresh = Object.keys( keys ).filter( function ( k ) { return ! seen[ k ]; } );
			if ( ! fresh.length ) { break; }
			for ( var i = 0; i < fresh.length; i++ ) {
				var k = fresh[ i ]; seen[ k ] = 1;
				var segs = qa( 'button[data-setel="' + k + '"][data-segval]', insp() );
				if ( segs.length ) {
					var vals = segs.map( function ( b ) { return b.getAttribute( 'data-segval' ); } );
					for ( var v of vals.concat( [ vals[ 0 ] ] ) ) { var b = q( '.vb-inspector button[data-setel="' + k + '"][data-segval="' + CSS.escape( v ) + '"]' ); if ( b ) { b.click(); await sleep( 110 ); n++; if ( ! alive( id ) ) { r.iss.push( 'gone after ' + k + '=' + v ); return n; } } }
					continue;
				}
				var s = q( '.vb-inspector select[data-setel="' + k + '"]' );
				if ( s ) {
					var opts = [].map.call( s.options, function ( o ) { return o.value; } ), first = s.value;
					for ( var o of opts.concat( [ first ] ) ) { var s2 = q( '.vb-inspector select[data-setel="' + k + '"]' ); if ( ! s2 ) { break; } s2.value = o; fire( s2, 'change' ); await sleep( 110 ); n++; if ( ! alive( id ) ) { r.iss.push( 'gone after ' + k + '=' + o ); return n; } }
					continue;
				}
				var c = q( '.vb-inspector input[type=checkbox][data-setel="' + k + '"]' );
				if ( c ) { c.click(); await sleep( 110 ); n++; var c2 = q( '.vb-inspector input[type=checkbox][data-setel="' + k + '"]' ); if ( c2 ) { c2.click(); await sleep( 110 ); } continue; }
				var t = q( '.vb-inspector input[data-setel="' + k + '"], .vb-inspector textarea[data-setel="' + k + '"]' );
				if ( t && ! t.readOnly && ! t.closest( '.vb-code-wrap' ) ) {
					var isCol = !! ( t.parentNode && t.parentNode.querySelector( '[data-setelswatch]' ) );
					var val = isCol ? '#2ab7f1' : ( t.classList.contains( 'num' ) ? '7' : ( /url|link|href/i.test( k ) ? 'https://example.com/' : 'Test ' + k ) );
					if ( t.closest( '.vb-media-row' ) ) { val = window.VELOX_BUILDER && window.VELOX_BUILDER.pluginUrl ? '' : ''; if ( ! val ) { continue; } }
					t.focus(); t.value = val; fire( t, 'input' ); await sleep( 120 ); fire( t, 'change' ); await blur(); n++;
				}
			}
		}
		return n;
	}
	async function workLayout( id ) {
		var n = 0;
		for ( var a of [ 'data-dz-lay', 'data-dz-h', 'data-dz-v' ] ) {
			var vals = qa( '[' + a + ']', insp() ).map( function ( b ) { return b.getAttribute( a ); } );
			for ( var v of vals ) { var b = q( '.vb-inspector [' + a + '="' + v + '"]' ); if ( b ) { b.click(); await sleep( 100 ); n++; } }
		}
		return n;
	}
	async function workMotion( id ) {
		var n = 0, ks = qa( 'input[type=checkbox][data-setmo]', insp() ).map( function ( c ) { return c.getAttribute( 'data-setmo' ); } );
		for ( var k of ks ) { var c = q( '.vb-inspector input[type=checkbox][data-setmo="' + k + '"]' ); if ( c && ! c.checked ) { c.click(); await sleep( 120 ); n++; } var c2 = q( '.vb-inspector input[type=checkbox][data-setmo="' + k + '"]' ); if ( c2 && c2.checked && ! /^(particles|glow|tilt)$/.test( k ) ) { c2.click(); await sleep( 100 ); } }
		var sels = qa( 'select[data-setmo]', insp() ).map( function ( s ) { return s.getAttribute( 'data-setmo' ); } );
		for ( var sk of sels ) { var s = q( '.vb-inspector select[data-setmo="' + sk + '"]' ); if ( s && s.options.length > 1 ) { s.value = s.options[ 1 ].value; fire( s, 'change' ); await sleep( 110 ); n++; } }
		return n;
	}
	var PROPS = [ [ 'paddingTop', 'num', '13', 'paddingTop', '13px' ], [ 'marginBottom', 'num', '11', 'marginBottom', '11px' ], [ 'borderRadius', 'num', '7', 'borderTopLeftRadius', '7px' ],
		[ 'opacity', 'text', '0.95', 'opacity', '0.95' ], [ 'color', 'text', '#123456', 'color', 'rgb(18, 52, 86)' ], [ 'fontSize', 'num', '17', 'fontSize', '17px' ], [ 'background', 'text', '#fdf2f8', 'backgroundColor', 'rgb(253, 242, 248)' ] ];
	function snap( el, win ) { if ( ! el ) { return null; } var cs = win.getComputedStyle( el ), o = {}; PROPS.forEach( function ( p ) { o[ p[ 3 ] ] = cs[ p[ 3 ] ]; } ); return o; }
	async function run() {
		var t0 = Date.now();
		while ( ! ( cd() && cd().body && insp() ) && Date.now() - t0 < 30000 ) { await sleep( 200 ); }
		await sleep( 1500 );
		var st = document.createElement( 'style' ); st.textContent = '*{transition:none!important;animation-duration:0s!important}'; document.head.appendChild( st );
		if ( ! q( '#vb-addmenu.open' ) ) { q( '[data-add]' ).click(); await sleep( 200 ); }
		var keys = qa( '#vb-addmenu [data-insert]' ).map( function ( b ) { return b.getAttribute( 'data-insert' ); } ).filter( function ( k ) { return 'innercontent' !== k; } );
		if ( P.get( 'count' ) ) { await report( { n: keys.length, keys: keys } ); document.title = 'VXDONE'; return; }
		var only = P.get( 'only' ); if ( only ) { keys = keys.filter( function ( k ) { return only.split( ',' ).indexOf( k ) > -1; } ); }
		var from = +( P.get( 'from' ) || 0 ), to = +( P.get( 'to' ) || keys.length ); keys = keys.slice( from, to );
		var rows = [], ids = {};
		for ( var i = 0; i < keys.length; i++ ) {
			var k = keys[ i ], r = { k: k, iss: [] };
			errors.length = 0;
			try {
				var cl = q( '[data-insp-close]' ); if ( cl ) { cl.click(); await sleep( 60 ); }
				if ( ! q( '#vb-addmenu.open' ) ) { q( '[data-add]' ).click(); await sleep( 120 ); }
				var tile = q( '#vb-addmenu [data-insert="' + k + '"]' ); if ( ! tile ) { r.iss.push( 'no tile' ); rows.push( r ); continue; }
				tile.click(); await sleep( 260 );
				var id = sel(); ids[ k ] = id;
				if ( ! id ) { r.iss.push( 'not selected after insert' ); rows.push( r ); continue; }
				for ( var tt of [ 'ess', 'all' ] ) { if ( await tab( tt ) ) { var txt = ( insp() || {} ).innerText || ''; if ( BAD.test( txt ) ) { r.iss.push( tt + ' tab shows ' + txt.match( BAD )[ 0 ] ); } } }
				await tab( 'ess' );
				r.set = await workSettings( id, r );
				if ( ! alive( id ) ) { rows.push( r ); continue; }
				r.lay = await workLayout( id );
				await tab( 'all' );
				r.mo = await workMotion( id );
				var missing = [];
				for ( var p of PROPS ) { if ( ! ( await setVia( p[ 0 ], p[ 1 ], p[ 2 ] ) ) ) { missing.push( p[ 0 ] ); } }
				if ( missing.length ) { r.iss.push( 'no control for ' + missing.join( ',' ) ); }
				var el = cd().getElementById( id ), cs = snap( el, cd().defaultView );
				PROPS.forEach( function ( p ) { if ( missing.indexOf( p[ 0 ] ) < 0 && cs && cs[ p[ 3 ] ] !== p[ 4 ] ) { r.iss.push( 'canvas ' + p[ 0 ] + '=' + cs[ p[ 3 ] ] ); } } );
				r.canvas = cs;
				if ( await pickOpt( '#vb-statepick', 'hover' ) ) { await setVia( 'borderRadius', 'num', '3' ); if ( css().indexOf( ':hover' ) < 0 ) { r.iss.push( 'hover rule missing' ); } await pickOpt( '#vb-statepick', 'normal' ); }
				else { r.iss.push( 'no state picker' ); }
				var e2 = cd().getElementById( id ); if ( e2 && BAD.test( e2.outerHTML ) ) { r.iss.push( 'canvas html has ' + e2.outerHTML.match( BAD )[ 0 ] ); }
				var it = ( insp() || {} ).innerText || ''; if ( BAD.test( it ) ) { r.iss.push( 'inspector shows ' + it.match( BAD )[ 0 ] ); }
			} catch ( ex ) { r.iss.push( 'EXC ' + ex.message ); }
			if ( errors.length ) { r.iss = r.iss.concat( errors.slice( 0, 4 ) ); }
			rows.push( r );
		}
		await sleep( 600 );
		var pub = q( '#vb-publish' ); if ( pub ) { pub.click(); await sleep( 2500 ); var yes = q( '#vb-ask [data-ask="yes"]' ); if ( yes ) { yes.click(); await sleep( 2500 ); } }
		var url = ( window.VELOX_BUILDER.frontUrl || '' ); url += ( url.indexOf( '?' ) > -1 ? '&' : '?' ) + 'velox_live=1&nocache=' + Date.now();
		var fr = document.createElement( 'iframe' ); fr.style.cssText = 'position:fixed;left:0;top:0;width:' + document.getElementById( 'vb-canvas' ).offsetWidth + 'px;height:900px;opacity:0;pointer-events:none;z-index:-1';
		document.body.appendChild( fr ); fr.src = url;
		await new Promise( function ( res ) { fr.onload = res; setTimeout( res, 20000 ); } );
		await sleep( 2500 );
		var fd = fr.contentDocument, fw = fr.contentWindow, pageErr = [];
		try { fw.addEventListener( 'error', function ( e ) { pageErr.push( e.message ); } ); } catch ( e ) {}
		var stf = fd.createElement( 'style' ); stf.textContent = '*{transition:none!important;animation-duration:0s!important}'; fd.head.appendChild( stf ); await sleep( 100 );
		rows.forEach( function ( r ) {
			if ( ! ids[ r.k ] || ! r.canvas ) { return; }
			var fe = fd.getElementById( ids[ r.k ] );
			if ( ! fe ) { r.iss.push( 'not on the page' ); return; }
			var f = snap( fe, fw );
			PROPS.forEach( function ( p ) { if ( r.canvas[ p[ 3 ] ] !== f[ p[ 3 ] ] ) { r.iss.push( 'page ' + p[ 0 ] + ' ' + r.canvas[ p[ 3 ] ] + ' vs ' + f[ p[ 3 ] ] ); } } );
			if ( BAD.test( fe.outerHTML ) ) { r.iss.push( 'page html has ' + fe.outerHTML.match( BAD )[ 0 ] ); }
		} );
		var out = { n: rows.length, ms: Date.now() - t0, url: url, pageErr: pageErr, pageHtml: ( fd.body && fd.body.innerHTML.length ) || 0,
			bad: rows.filter( function ( r ) { return r.iss.length; } ).map( function ( r ) { return { k: r.k, set: r.set, iss: r.iss }; } ),
			ok: rows.filter( function ( r ) { return ! r.iss.length; } ).map( function ( r ) { return r.k + ':' + ( r.set || 0 ) + '/' + ( r.lay || 0 ) + '/' + ( r.mo || 0 ); } ) };
		await report( out );
		document.title = 'VXDONE';
	}
	setTimeout( run, 500 );
}() );
