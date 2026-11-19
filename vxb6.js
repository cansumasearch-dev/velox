( function () {
	var P = new URLSearchParams( location.search );
	if ( 'features3' !== P.get( 'vxtest' ) ) { return; }
	var errors = [];
	window.addEventListener( 'error', function ( e ) { errors.push( 'ERR ' + e.message + ' @' + ( e.lineno || '' ) ); } );
	async function pickOpt( a, v, act ) { var b = document.querySelector( a ); if ( ! b ) { return false; } b.click(); await new Promise( function ( r ) { setTimeout( r, 120 ); } ); var row = document.querySelector( '.vxs-pop [data-vxs-v="' + v + '"]' ); if ( ! row ) { if ( window.VeloxSelect ) { VeloxSelect.close(); } return false; } ( act ? row.querySelector( '[data-vxs-act="' + act + '"]' ) : row ).click(); await new Promise( function ( r ) { setTimeout( r, 200 ); } ); if ( window.VeloxSelect ) { VeloxSelect.close(); } return true; }
	function pickCls( c, act ) { return pickOpt( '#vb-clspick', '#' === c ? '#' : 'cls:' + c, act ); }
	function pickState( s ) { return pickOpt( '#vb-statepick', s ); }
	function activeCls() { var b = document.querySelector( '#vb-clspick' ); if ( ! b ) { return null; } return b.classList.contains( 'is-el' ) ? '#' : b.querySelector( '.vb-sel-v' ).textContent.trim(); }
	function clsLocked() { return !! document.querySelector( '#vb-clspick.is-locked' ); }
	var sleep = function ( t ) { return new Promise( function ( r ) { setTimeout( r, t ); } ); };
	var q = function ( s ) { return document.querySelector( s ); }, qa = function ( s ) { return [].slice.call( document.querySelectorAll( s ) ); };
	function cd() { var f = document.getElementById( 'vb-canvas' ); return f && f.contentDocument; }
	function fire( el, t ) { el.dispatchEvent( new Event( t, { bubbles: true } ) ); }
	function key( k, o, target ) { o = o || {}; ( target || document.body ).dispatchEvent( new KeyboardEvent( 'keydown', Object.assign( { key: k, bubbles: true, cancelable: true }, o ) ) ); }
	function ptr( target, type, x, y, o ) { target.dispatchEvent( new PointerEvent( type, Object.assign( { bubbles: true, cancelable: true, clientX: x, clientY: y, button: 0, pointerId: 1, isPrimary: true }, o || {} ) ) ); }
	function sel() { var s = cd().querySelector( '.vb-sel' ); return s && s.id; }
	function click( id, o ) { cd().getElementById( id ).dispatchEvent( new MouseEvent( 'click', Object.assign( { bubbles: true }, o || {} ) ) ); }
	function css() { return cd().getElementById( 'vb-style' ).textContent.replace( /\s+/g, ' ' ); }
	function rule( c ) { var t = css(), i = t.indexOf( c + ' {' ); return i < 0 ? '' : t.slice( i, t.indexOf( '}', i ) + 1 ); }
	function report( r ) { var b = new FormData(); b.append( 'action', 'vxt_report' ); b.append( 'r', JSON.stringify( r ) ); return fetch( window.VELOX_BUILDER.ajaxurl + '?k=features3', { method: 'POST', body: b, credentials: 'same-origin' } ); }
	async function add( k ) { if ( ! q( '#vb-addmenu.open' ) ) { q( '[data-add]' ).click(); await sleep( 150 ); } q( '#vb-addmenu [data-insert="' + k + '"]' ).click(); await sleep( 250 ); return sel(); }
	async function tab( t ) { var b = q( '.vb-tab[data-tab="' + t + '"]' ); if ( b ) { b.click(); await sleep( 150 ); } }
	setTimeout( async function () {
		var r = {};
		try {
			var t0 = Date.now();
			while ( ! ( cd() && cd().body && q( '.vb-inspector' ) ) && Date.now() - t0 < 30000 ) { await sleep( 200 ); }
			await sleep( 1500 );
			var g = 0; while ( cd().querySelector( '[data-node]' ) && g++ < 60 ) { cd().querySelector( '[data-node]' ).click(); await sleep( 80 ); var d = q( '.vb-inspector [data-del]' ); if ( d ) { d.click(); await sleep( 100 ); } }
			// 1. starting styles
			var secId = await add( 'section' );
			var bId = await add( 'button' ); click( secId ); await sleep( 120 );
			var tId = await add( 'text' ); click( secId ); await sleep( 120 );
			var cId = await add( 'card' ); click( secId ); await sleep( 120 );
			var gId = await add( 'badge' ); click( secId ); await sleep( 120 );
			var dvId = await add( 'divider' );
			r.btn = rule( '.btn' ); r.btnHover = rule( '.btn:hover' ); r.text = rule( '.text' ); r.card = rule( '.card' ); r.badge = rule( '.badge' );
			// 2. value in effect for fields nobody set
			click( tId ); await sleep( 200 ); await tab( 'all' );
			var ff = q( 'select[data-setsel="fontFamily"]' ); r.fontEmpty = ff ? ff.options[ 0 ].textContent : null;
			var col = q( 'input[data-set="color"]' ); r.colorPh = col ? col.placeholder : null;
			var mt = q( '[data-setnum="marginTop"]' ); r.marginTopPh = mt ? mt.placeholder : null;
			r.curSeg = qa( '.vb-seg button.cur' ).map( function ( b ) { return b.getAttribute( 'data-set' ) + '=' + b.getAttribute( 'data-val' ); } ).slice( 0, 6 );
			// 3. property search
			var sf = q( '#vb-sfilter' ); sf.focus(); sf.value = 'radius'; fire( sf, 'input' ); await sleep( 200 );
			r.searchRadius = qa( '.vb-block-h b' ).map( function ( b ) { return b.textContent; } ).join( ',' ) + ' / fields ' + qa( '.vb-controls .vb-f' ).length;
			r.searchFocusKept = document.activeElement && document.activeElement.id === 'vb-sfilter';
			sf = q( '#vb-sfilter' ); sf.value = 'zzzq'; fire( sf, 'input' ); await sleep( 150 );
			r.searchNone = !! q( '.vb-sfilter-none' );
			sf = q( '#vb-sfilter' ); sf.value = 'flex'; fire( sf, 'input' ); await sleep( 150 );
			r.searchFlexOnBlock = qa( '.vb-controls .vb-f' ).length;
			q( '[data-sfilter-x]' ).click(); await sleep( 150 );
			r.searchCleared = ! q( '#vb-sfilter' ).value;
			// 4. recent colours
			col = q( 'input[data-set="color"]' ); col.focus(); col.value = '#ff3366'; fire( col, 'input' ); await sleep( 300 ); document.body.click(); await sleep( 200 );
			r.recent = qa( '.vb-tsw-rec' ).map( function ( b ) { return b.getAttribute( 'data-val' ); } ).slice( 0, 3 );
			// 5. drag a number label
			var lbl = q( '.vb-f-num [data-setnum="fontSize"]' ).closest( '.vb-f' ).querySelector( '.vb-f-lbl' ), lr = lbl.getBoundingClientRect();
			var before = q( '[data-setnum="fontSize"]' ).value || q( '[data-setnum="fontSize"]' ).placeholder;
			ptr( lbl, 'pointerdown', lr.left + 20, lr.top + 5 ); ptr( lbl, 'pointermove', lr.left + 40, lr.top + 5 ); ptr( lbl, 'pointerup', lr.left + 40, lr.top + 5 ); await sleep( 400 );
			r.scrub = before + '->' + q( '[data-setnum="fontSize"]' ).value;
			// 6. sums
			var fs = q( '[data-setnum="fontSize"]' ); fs.focus(); fs.value = '16*2'; fire( fs, 'input' ); fire( fs, 'change' ); await sleep( 400 );
			r.math = q( '[data-setnum="fontSize"]' ).value + ' / ' + ( rule( '.text' ).match( /font-size: [^;]+/ ) || [ '' ] )[ 0 ];
			var w = q( '[data-setnum="width"]' ); w.focus(); w.value = '100%-20px'; fire( w, 'input' ); fire( w, 'change' ); await sleep( 400 );
			r.calc = ( rule( '.text' ).match( /width: [^;]+/ ) || [ '' ] )[ 0 ];
			// 7. lock
			await pickCls( '.text', 'lock' );
			r.lockOn = clsLocked();
			fs = q( '[data-setnum="fontSize"]' ); fs.focus(); fs.value = '50'; fire( fs, 'input' ); await sleep( 400 ); document.body.click(); await sleep( 200 );
			r.lockedKept = ( rule( '.text' ).match( /font-size: [^;]+/ ) || [ '' ] )[ 0 ];
			await pickCls( '.text', 'lock' );
			r.lockOff = ! clsLocked();
			// 8. element style overriding the class
			await pickCls( '#' );
			fs = q( '[data-setnum="fontSize"]' ); fs.focus(); fs.value = '21'; fire( fs, 'input' ); await sleep( 400 ); document.body.click(); await sleep( 150 );
			await pickCls( '.text' );
			r.ovWarn = !! q( '.vb-ovwarn' );
			q( '[data-ovmove]' ).click(); await sleep( 300 );
			r.ovMoved = ( rule( '.text' ).match( /font-size: [^;]+/ ) || [ '' ] )[ 0 ] + ' / warn gone ' + ! q( '.vb-ovwarn' );
			// 9. several elements
			click( bId ); await sleep( 150 );
			click( cId, { ctrlKey: true } ); await sleep( 200 );
			r.multiBar = ( q( '.vb-multibar b' ) || {} ).textContent;
			r.multiOutline = cd().getElementById( bId ).classList.contains( 'vb-msel' );
			key( 'Delete' ); await sleep( 300 );
			r.multiDeleted = ! cd().getElementById( bId ) && ! cd().getElementById( cId );
			// 10. Alt+arrows
			var kidsBefore = [].slice.call( cd().getElementById( secId ).children ).filter( function ( c ) { return c.hasAttribute( 'data-node' ); } ).map( function ( c ) { return c.id; } );
			click( tId ); await sleep( 150 );
			key( 'ArrowDown', { altKey: true } ); await sleep( 250 );
			var kidsAfter = [].slice.call( cd().getElementById( secId ).children ).filter( function ( c ) { return c.hasAttribute( 'data-node' ); } ).map( function ( c ) { return c.id; } );
			r.altMove = kidsBefore.indexOf( tId ) + '->' + kidsAfter.indexOf( tId );
			// 11. Structure keys
			document.getElementById( 'vb-structure' ).click(); await sleep( 300 );
			var row = q( '.vb-st-row.sel' ); row.focus(); await sleep( 50 );
			key( 'ArrowUp', {}, row ); await sleep( 250 );
			r.treeUp = sel() !== tId;
			r.treeFocus = !! ( document.activeElement && document.activeElement.classList.contains( 'vb-st-row' ) && document.activeElement.classList.contains( 'sel' ) );
			r.treeRoles = !! q( '.vb-st-tree[role="tree"] [role="treeitem"][aria-selected="true"]' );
			key( 'ArrowLeft', {}, document.activeElement ); await sleep( 250 );
			r.treeParent = sel();
			// 12. thin divider
			r.thin = cd().getElementById( dvId ).classList.contains( 'vb-thin' );
			r.secId = secId;
		} catch ( e ) { r.exc = e.message + ' ' + ( e.stack || '' ).slice( 0, 300 ); }
		r.errors = errors;
		await report( r );
	}, 500 );
}() );
