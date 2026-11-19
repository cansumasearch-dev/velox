( function () {
	// Audit suite: every element from + Add, one by one — all three inspector
	// tabs, every Essentials control, the common style properties, a hover
	// state, a second class and Insert Data; then publish and check every
	// styling also arrives on the page.
	var P = new URLSearchParams( location.search );
	if ( 'audit' !== P.get( 'vxtest' ) ) { return; }
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
	function report( r ) { var b = new FormData(); b.append( 'action', 'vxt_report' ); b.append( 'r', JSON.stringify( r ) ); return fetch( window.VELOX_BUILDER.ajaxurl + '?k=audit', { method: 'POST', body: b, credentials: 'same-origin' } ); }
	var BAD = /undefined|NaN|\[object Object\]/;
	async function blur() { document.body.dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true } ) ); document.body.click(); await sleep( 100 ); }
	async function tab( t ) { var b = q( '.vb-tab[data-tab="' + t + '"]' ); if ( ! b ) { return false; } b.click(); await sleep( 150 ); return true; }
	async function pickOpt( a, v ) { var b = q( a ); if ( ! b ) { return false; } b.click(); await sleep( 120 ); var row = q( '.vxs-pop [data-vxs-v="' + v + '"]' ); if ( ! row ) { if ( window.VeloxSelect ) { VeloxSelect.close(); } return false; } row.click(); await sleep( 180 ); if ( window.VeloxSelect ) { VeloxSelect.close(); } return true; }
	async function filter( text ) { var f = q( '#vb-sfilter' ); if ( ! f ) { return; } f.focus(); f.value = text; fire( f, 'input' ); await sleep( 100 ); }
	async function setVia( prop, type, val ) {
		await filter( '' );
		var c;
		if ( 'num' === type ) { c = q( 'input[data-setnum="' + prop + '"]' ); if ( c ) { c.focus(); c.value = val; fire( c, 'input' ); await sleep( 160 ); await blur(); } }
		else { c = q( 'input[data-set="' + prop + '"]' ); if ( c ) { c.focus(); c.value = val; fire( c, 'input' ); await sleep( 160 ); await blur(); } }
		return !! c;
	}
	function ctrlCount() {
		var box = q( '.vb-inspector' ); if ( ! box ) { return 0; }
		return qa( 'input:not([type=hidden]),select,textarea,button[data-set],button[data-setel],[data-setelseg] button', box ).length;
	}
	// Every Essentials control gets a real change, through its own UI.
	async function workEssentials() {
		var done = 0, box = q( '.vb-inspector' ); if ( ! box ) { return 0; }
		var groups = {};
		qa( 'button[data-set][data-val]', box ).forEach( function ( b ) { var g = b.getAttribute( 'data-set' ); ( groups[ g ] = groups[ g ] || [] ).push( b ); } );
		for ( var g in groups ) { var off = groups[ g ].filter( function ( b ) { return ! b.classList.contains( 'on' ); } )[ 0 ]; if ( off && document.contains( off ) ) { off.click(); await sleep( 120 ); done++; } }
		var nums = qa( 'input[data-setnum]', q( '.vb-inspector' ) ).map( function ( i ) { return i.getAttribute( 'data-setnum' ); } );
		for ( var i = 0; i < nums.length; i++ ) { var inp = q( '.vb-inspector input[data-setnum="' + nums[ i ] + '"]' ); if ( ! inp ) { continue; } inp.focus(); inp.value = '7'; fire( inp, 'input' ); await sleep( 120 ); await blur(); done++; }
		var sels = qa( 'select[data-setsel]', q( '.vb-inspector' ) ).map( function ( s ) { return s.getAttribute( 'data-setsel' ); } );
		for ( var j = 0; j < sels.length; j++ ) { var s = q( '.vb-inspector select[data-setsel="' + sels[ j ] + '"]' ); if ( ! s || s.options.length < 2 ) { continue; } s.value = s.options[ s.selectedIndex === 1 ? 2 % s.options.length : 1 ].value; fire( s, 'change' ); await sleep( 120 ); done++; }
		return done;
	}
	var PROPS = [ [ 'paddingTop', 'num', '13', 'paddingTop', '13px' ], [ 'marginBottom', 'num', '11', 'marginBottom', '11px' ], [ 'borderRadius', 'num', '7', 'borderTopLeftRadius', '7px' ],
		[ 'opacity', 'text', '0.95', 'opacity', '0.95' ], [ 'color', 'text', '#123456', 'color', 'rgb(18, 52, 86)' ], [ 'fontSize', 'num', '17', 'fontSize', '17px' ], [ 'background', 'text', '#fdf2f8', 'backgroundColor', 'rgb(253, 242, 248)' ] ];
	function snap( el, win ) { if ( ! el ) { return null; } var cs = win.getComputedStyle( el ), o = {}; PROPS.forEach( function ( p ) { o[ p[ 3 ] ] = cs[ p[ 3 ] ]; } ); return o; }
	async function run() {
		var t0 = Date.now();
		while ( ! ( cd() && cd().body && q( '.vb-inspector' ) ) && Date.now() - t0 < 30000 ) { await sleep( 200 ); }
		await sleep( 1500 );
		var st = document.createElement( 'style' ); st.textContent = '*{transition:none!important;animation-duration:0s!important}'; document.head.appendChild( st );
		if ( ! q( '#vb-addmenu.open' ) ) { q( '[data-add]' ).click(); await sleep( 200 ); }
		var keys = qa( '#vb-addmenu [data-insert]' ).map( function ( b ) { return b.getAttribute( 'data-insert' ); } ).filter( function ( k ) { return 'innercontent' !== k; } );
		var only = P.get( 'only' ); if ( only ) { keys = keys.filter( function ( k ) { return only.split( ',' ).indexOf( k ) > -1; } ); }
		var from = +( P.get( 'from' ) || 0 ), to = +( P.get( 'to' ) || keys.length ); keys = keys.slice( from, to );
		var rows = [], ids = {};
		for ( var i = 0; i < keys.length; i++ ) {
			var k = keys[ i ], r = { k: k, iss: [], tabs: {} };
			errors.length = 0;
			var cl = q( '[data-insp-close]' ); if ( cl ) { cl.click(); await sleep( 60 ); }
			if ( ! q( '#vb-addmenu.open' ) ) { q( '[data-add]' ).click(); await sleep( 120 ); }
			var tile = q( '#vb-addmenu [data-insert="' + k + '"]' ); if ( ! tile ) { r.iss.push( 'no tile' ); rows.push( r ); continue; }
			tile.click(); await sleep( 260 );
			var id = sel(); ids[ k ] = id;
			if ( ! id ) { r.iss.push( 'not selected after insert' ); rows.push( r ); continue; }
			// The three tabs: present, no junk text, how many controls each has.
			for ( var tt of [ 'ess', 'all', 'set' ] ) {
				if ( ! ( await tab( tt ) ) ) { r.tabs[ tt ] = '-'; continue; }
				r.tabs[ tt ] = ctrlCount();
				var txt = ( q( '.vb-inspector' ) || {} ).innerText || '';
				if ( BAD.test( txt ) ) { r.iss.push( tt + ' tab shows ' + txt.match( BAD )[ 0 ] ); }
			}
			await tab( 'ess' ); r.essDone = await workEssentials();
			if ( ! cd().getElementById( id ) ) { r.iss.push( 'element vanished after Essentials' ); rows.push( r ); continue; }
			// Common styles on the element's class, through the All styles tab.
			await tab( 'all' );
			var missing = [];
			for ( var p of PROPS ) { if ( ! ( await setVia( p[ 0 ], p[ 1 ], p[ 2 ] ) ) ) { missing.push( p[ 0 ] ); } }
			if ( missing.length ) { r.iss.push( 'no control for ' + missing.join( ',' ) ); }
			var el = cd().getElementById( id ), cs = snap( el, cd().defaultView );
			PROPS.forEach( function ( p ) { if ( missing.indexOf( p[ 0 ] ) < 0 && cs && cs[ p[ 3 ] ] !== p[ 4 ] ) { r.iss.push( 'canvas ' + p[ 0 ] + '=' + cs[ p[ 3 ] ] ); } } );
			r.canvas = cs;
			// Hover state.
			if ( await pickOpt( '#vb-statepick', 'hover' ) ) {
				await setVia( 'borderRadius', 'num', '3' );
				if ( css().indexOf( ':hover' ) < 0 ) { r.iss.push( 'hover rule missing' ); }
				await pickOpt( '#vb-statepick', 'normal' );
			} else { r.iss.push( 'no state picker' ); }
			// A second class.
			var addc = q( '[data-addclass]' );
			if ( addc ) { addc.click(); await sleep( 150 ); var inp = q( '#vb-ask-in' ); if ( inp ) { inp.value = 'aud-' + i; key( 'Enter', {}, inp ); await sleep( 220 ); } if ( ! cd().getElementById( id ) || ! cd().getElementById( id ).classList.contains( 'aud-' + i ) ) { r.iss.push( 'second class not on element' ); } }
			else { r.iss.push( 'no add-class button' ); }
			// Insert Data, for elements with their own text.
			var e2 = cd().getElementById( id );
			e2.dispatchEvent( new MouseEvent( 'dblclick', { bubbles: true } ) ); await sleep( 250 );
			var ed = cd().querySelector( '[contenteditable="true"]' );
			if ( ed ) {
				r.editable = true;
				var ib = q( '#vb-insertdata' );
				if ( ib ) { ib.click(); await sleep( 200 ); var chip = q( '.vb-dd-chip[data-dd="site.title"]' ); if ( chip ) { chip.click(); await sleep( 200 ); } if ( ! cd().querySelector( '#' + id + ' [data-vx="site.title"], [data-vx="site.title"]' ) ) { r.iss.push( 'insert data: no token' ); } }
				else { r.iss.push( 'editable but no Insert Data button' ); }
				key( 'Escape', {}, ed ); await sleep( 200 ); await blur();
			}
			if ( errors.length ) { r.iss = r.iss.concat( errors.slice( 0, 3 ) ); }
			rows.push( r );
		}
		await sleep( 600 );
		// Publish and compare every styling on the live page.
		var pub = q( '#vb-publish' ); if ( pub ) { pub.click(); await sleep( 2500 ); var yes = q( '#vb-ask [data-ask="yes"]' ); if ( yes ) { yes.click(); await sleep( 2500 ); } }
		var url = ( window.VELOX_BUILDER.frontUrl || '' ); url += ( url.indexOf( '?' ) > -1 ? '&' : '?' ) + 'velox_live=1&nocache=' + Date.now();
		var fr = document.createElement( 'iframe' ); fr.style.cssText = 'position:fixed;left:0;top:0;width:' + document.getElementById( 'vb-canvas' ).offsetWidth + 'px;height:900px;opacity:0;pointer-events:none;z-index:-1';
		document.body.appendChild( fr ); fr.src = url;
		await new Promise( function ( res ) { fr.onload = res; setTimeout( res, 15000 ); } );
		await sleep( 2500 );
		var fd = fr.contentDocument, fw = fr.contentWindow;
		var stf = fd.createElement( 'style' ); stf.textContent = '*{transition:none!important;animation-duration:0s!important}'; fd.head.appendChild( stf ); await sleep( 100 );
		rows.forEach( function ( r ) {
			if ( ! ids[ r.k ] || ! r.canvas ) { return; }
			var fe = fd.getElementById( ids[ r.k ] );
			if ( ! fe ) { r.iss.push( 'not on the page' ); return; }
			var f = snap( fe, fw );
			PROPS.forEach( function ( p ) { if ( r.canvas[ p[ 3 ] ] !== f[ p[ 3 ] ] ) { r.iss.push( 'page ' + p[ 0 ] + ' ' + r.canvas[ p[ 3 ] ] + ' vs ' + f[ p[ 3 ] ] ); } } );
			if ( ! fe.classList.contains( 'aud-' + rows.indexOf( r ) ) && r.iss.indexOf( 'second class not on element' ) < 0 ) { /* class index differs with from/only */ }
			if ( BAD.test( fe.outerHTML ) ) { r.iss.push( 'page html has ' + fe.outerHTML.match( BAD )[ 0 ] ); }
		} );
		var out = { n: rows.length, ms: Date.now() - t0, bad: rows.filter( function ( r ) { return r.iss.length; } ).map( function ( r ) { return { k: r.k, tabs: r.tabs, ess: r.essDone, iss: r.iss }; } ),
			ok: rows.filter( function ( r ) { return ! r.iss.length; } ).map( function ( r ) { return r.k + ':' + r.tabs.ess + '/' + r.tabs.all + '/' + r.tabs.set + ( r.editable ? ':txt' : '' ); } ) };
		await report( out );
		document.title = 'VXDONE';
	}
	setTimeout( run, 500 );
}() );
