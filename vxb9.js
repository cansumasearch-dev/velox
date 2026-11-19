( function () {
	var P = new URLSearchParams( location.search );
	if ( 'panels' !== P.get( 'vxtest' ) ) { return; }
	window.confirm = function () { return true; };
	var errors = [];
	window.addEventListener( 'error', function ( e ) { errors.push( 'ERR ' + e.message + ' @' + ( e.lineno || '' ) ); } );
	var sleep = function ( t ) { return new Promise( function ( r ) { setTimeout( r, t ); } ); };
	var q = function ( s ) { return document.querySelector( s ); }, qa = function ( s ) { return [].slice.call( document.querySelectorAll( s ) ); };
	function cd() { var f = document.getElementById( 'vb-canvas' ); return f && f.contentDocument; }
	function fire( el, t ) { el.dispatchEvent( new Event( t, { bubbles: true } ) ); }
	function key( k, o, target ) { o = o || {}; ( target || document.body ).dispatchEvent( new KeyboardEvent( 'keydown', Object.assign( { key: k, bubbles: true, cancelable: true }, o ) ) ); }
	function sel() { var s = cd().querySelector( '.vb-sel' ); return s && s.id; }
	function report( k, r ) { var b = new FormData(); b.append( 'action', 'vxt_report' ); b.append( 'r', JSON.stringify( r ) ); return fetch( window.VELOX_BUILDER.ajaxurl + '?k=' + k, { method: 'POST', body: b, credentials: 'same-origin' } ); }
	async function add( k ) { if ( ! q( '#vb-addmenu.open' ) ) { q( '[data-add]' ).click(); await sleep( 150 ); } var t = q( '#vb-addmenu [data-insert="' + k + '"]' ); if ( ! t ) { return null; } t.click(); await sleep( 250 ); return sel(); }
	async function view( v ) {
		var b = q( '[data-setview="' + v + '"]' ), up = 0;
		while ( ! b && q( '.vb-set-back' ) && up++ < 4 ) { q( '.vb-set-back' ).click(); await sleep( 200 ); b = q( '[data-setview="' + v + '"]' ); }
		if ( b ) { b.click(); await sleep( 200 ); } return !! b;
	}
	async function type( el, v, ev ) { el.focus(); el.value = v; fire( el, ev || 'input' ); await sleep( 300 ); }
	var ok = function ( r, name, cond, info ) { if ( cond ) { r.pass.push( name ); } else { r.fail.push( name + ( info !== undefined ? ': ' + info : '' ) ); } };
	setTimeout( async function () {
		var r = { pass: [], fail: [] };
		try {
			var t0 = Date.now();
			while ( ! ( cd() && cd().body && q( '.vb-inspector' ) ) && Date.now() - t0 < 30000 ) { await sleep( 200 ); }
			await sleep( 1500 );
			// --- scroll animation on the heading
			var hh = cd().querySelector( 'h3[data-node], h2[data-node]' );
			if ( hh ) { hh.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) ); await sleep( 200 ); var stb = q( '.vb-tab[data-tab="set"]' ); if ( stb ) { stb.click(); await sleep( 200 ); } var ao = q( 'select[data-setaos="type"]' ); if ( ao ) { ao.value = 'fade-up'; fire( ao, 'change' ); await sleep( 200 ); } var ad = q( 'input[data-setaos="duration"]' ); if ( ad ) { await type( ad, '900' ); document.body.click(); await sleep( 300 ); } r.aosHeading = hh.id; ok( r, 'animation set', !! ao ); }
			// --- page settings
			q( '#vb-settings' ).click(); await sleep( 250 );
			ok( r, 'settings panel opens', !! q( '.vb-setpanel' ) && 'none' !== getComputedStyle( q( '.vb-setpanel' ) ).display );
			ok( r, 'page view', await view( 'page' ) );
			var pw = q( 'input[data-setpage="width"]' ); if ( pw ) { await type( pw, '900' ); } ok( r, 'page width field', !! pw );
			var pb = q( 'input[data-setpage="background"]' ); if ( pb ) { await type( pb, '#f4f4f0' ); } ok( r, 'page background field', !! pb );
			await view( 'page-scripts' );
			var pcss = q( 'textarea[data-setpage="css"]' ); if ( pcss ) { await type( pcss, '.vx-page-only{color:#c00}' ); } ok( r, 'page css field', !! pcss );
			await sleep( 400 );
			ok( r, 'page css in canvas', ( cd().getElementById( 'vb-page-css' ) || {} ).textContent === '.vx-page-only{color:#c00}', ( cd().getElementById( 'vb-page-css' ) || {} ).textContent );
			ok( r, 'page background in canvas', /244, 244, 240|f4f4f0/i.test( cd().body.style.background + cd().defaultView.getComputedStyle( cd().body ).backgroundColor ), cd().defaultView.getComputedStyle( cd().body ).backgroundColor );
			// --- global styles: body colour
			await view( 'root' ); ok( r, 'global view', await view( 'global' ) );
			var gsBtns = qa( '[data-setview^="gs-"]' ).map( function ( b ) { return b.getAttribute( 'data-setview' ); } );
			ok( r, 'global style sections', gsBtns.length >= 6, gsBtns.join( ',' ) );
			await view( 'gs-body' );
			var gc = q( 'input[data-gsx="body.color"]' ); if ( gc ) { await type( gc, '#202830' ); await type( gc, '#202830', 'change' ); }
			ok( r, 'body colour field', !! gc );
			await view( 'global' ); await view( 'gs-colors' );
			var tokBefore = qa( '#vb-tokenlist [data-tokenname]' ).length;
			var ta = q( '#vb-token-add' ); if ( ta ) { ta.click(); await sleep( 400 ); }
			ok( r, 'add colour token', qa( '#vb-tokenlist [data-tokenname]' ).length === tokBefore + 1, tokBefore + '->' + qa( '#vb-tokenlist [data-tokenname]' ).length );
			await sleep( 1500 );
			// --- editor settings
			await view( 'root' ); ok( r, 'editor view', await view( 'editor' ) );
			var ip = q( '[data-setpref="indicateParents"]' );
			if ( ip ) { var was = cd().body.classList.contains( 'vb-indicate' ) || cd().documentElement.classList.contains( 'vb-indicate' ) || q( '.vb-app' ).classList.contains( 'vb-indicate' ); ip.click(); await sleep( 200 ); var now = cd().body.classList.contains( 'vb-indicate' ) || cd().documentElement.classList.contains( 'vb-indicate' ) || q( '.vb-app' ).classList.contains( 'vb-indicate' ); ok( r, 'editor pref toggles', was !== now, was + '->' + now ); ip.click(); await sleep( 150 ); }
			else { ok( r, 'editor pref present', false ); }
			q( '#vb-settings' ).click(); await sleep( 200 );
			// --- code panel: global css + js files
			q( '#vb-code' ).click(); await sleep( 300 );
			ok( r, 'code panel opens', !! q( '#vb-css-code' ) );
			var nb = q( '#vb-css-new' ); if ( nb ) { nb.click(); await sleep( 300 ); }
			var cc = q( '#vb-css-code' ); if ( cc ) { await type( cc, '.vx-global-test{outline:1px solid #0a0}' ); }
			await sleep( 1800 );
			var jt = q( '[data-codetab="js"]' ); if ( jt ) { jt.click(); await sleep( 250 ); }
			var jn = q( '#vb-js-new' ); if ( jn ) { jn.click(); await sleep( 300 ); }
			var jc = q( '#vb-js-code' ); if ( jc ) { await type( jc, 'window.__vxGlobalJs = 1;' ); }
			var jon = q( '#vb-js-on' ); if ( jon && ! jon.checked ) { jon.click(); await sleep( 200 ); }
			ok( r, 'js editor', !! jc );
			await sleep( 1800 );
			r.cssStatus = ( q( '#vb-css-status' ) || {} ).textContent; r.jsStatus = ( q( '#vb-js-status' ) || {} ).textContent;
			q( '#vb-code' ).click(); await sleep( 200 );
			// --- page switcher
			q( '#vb-search' ).click(); await sleep( 1500 );
			ok( r, 'page switcher lists pages', qa( '#vb-ps-list .vb-ps-i' ).length > 0, qa( '#vb-ps-list .vb-ps-i' ).length );
			key( 'Escape' ); await sleep( 150 );
			// --- reusables in the add panel
			q( '[data-add]' ).click(); await sleep( 200 );
			var ru = q( '#vb-addmenu [data-insert-reuse]' );
			ok( r, 'reusable listed in add panel', !! ru );
			if ( ru ) { ru.click(); await sleep( 400 ); ok( r, 'reusable inserted', !! cd().querySelector( '[data-reuse]' ) ); }
			// --- structure panel
			for ( var sk of [ 'section', 'div', 'heading', 'text' ] ) { if ( ! q( '#vb-addmenu.open' ) ) { q( '[data-add]' ).click(); await sleep( 200 ); } var stile = q( '#vb-addmenu [data-insert="' + sk + '"]' ); if ( stile ) { stile.click(); await sleep( 250 ); } }
			if ( q( '#vb-addmenu.open' ) ) { q( '[data-add]' ).click(); await sleep( 150 ); }
			q( '#vb-structure' ).click(); await sleep( 300 );
			ok( r, 'structure rows', qa( '.vb-st-row' ).length > 3, qa( '.vb-st-row' ).length );
			var cAll = q( '#vb-st-collapse' ); if ( cAll ) { cAll.click(); await sleep( 200 ); }
			var collapsedRows = qa( '.vb-st-row' ).length;
			var eAll = q( '#vb-st-expand' ); if ( eAll ) { eAll.click(); await sleep( 200 ); }
			ok( r, 'collapse / expand all', qa( '.vb-st-row' ).length > collapsedRows, collapsedRows + '/' + qa( '.vb-st-row' ).length );
			q( '#vb-structure' ).click(); await sleep( 200 );
			// --- save + publish
			q( '#vb-save' ).click(); await sleep( 3500 );
			q( '#vb-publish' ).click(); await sleep( 800 ); if ( q( '.vb-ask.open' ) ) { q( '[data-ask="yes"]' ).click(); } await sleep( 5000 );
			ok( r, 'published', /Published|Veröffentlicht/.test( q( '#vb-publish' ).textContent ), q( '#vb-publish' ).textContent );
			// --- draft vs live: change after publishing, save only
			var h = await add( 'heading' );
			q( '#vb-save' ).click(); await sleep( 3500 );
			r.afterSaveOnly = q( '#vb-publish' ).textContent;
			r.draftHeading = h;
			// --- template document
			var kind = q( '#vb-kind' );
			ok( r, 'kind select', !! kind );
		} catch ( e ) { r.exc = e.message + ' ' + ( e.stack || '' ).slice( 0, 300 ); }
		r.errors = errors;
		await report( 'panels', r );
	}, 500 );
}() );
