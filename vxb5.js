( function () {
	var P = new URLSearchParams( location.search );
	if ( 'features2' !== P.get( 'vxtest' ) ) { return; }
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
	function ptr( target, type, x, y ) { target.dispatchEvent( new PointerEvent( type, { bubbles: true, cancelable: true, clientX: x, clientY: y, button: 0, buttons: 'pointerup' === type ? 0 : 1, pointerId: 1, isPrimary: true } ) ); }
	function sel() { var s = cd().querySelector( '.vb-sel' ); return s && s.id; }
	function order( pid ) { return [].slice.call( cd().getElementById( pid ).children ).filter( function ( c ) { return c.hasAttribute( 'data-node' ); } ).map( function ( c ) { return c.className.split( ' ' )[ 0 ]; } ); }
	function report( r ) { var b = new FormData(); b.append( 'action', 'vxt_report' ); b.append( 'r', JSON.stringify( r ) ); return fetch( window.VELOX_BUILDER.ajaxurl + '?k=features2', { method: 'POST', body: b, credentials: 'same-origin' } ); }
	async function openAdd() { if ( ! q( '#vb-addmenu.open' ) ) { q( '[data-add]' ).click(); await sleep( 150 ); } }
	// editor-page coordinates of a canvas point
	function ed( x, y ) { var r = document.getElementById( 'vb-canvas' ).getBoundingClientRect(); var sc = r.width / document.getElementById( 'vb-canvas' ).offsetWidth; return { x: r.left + x * sc, y: r.top + y * sc }; }
	async function dragTile( key, cx, cy ) {
		await openAdd();
		var tile = q( '#vb-addmenu [data-insert="' + key + '"]' ), tr = tile.getBoundingClientRect();
		ptr( tile, 'pointerdown', tr.left + 10, tr.top + 10 );
		ptr( document, 'pointermove', tr.left + 30, tr.top + 30 );
		var p = ed( cx, cy );
		ptr( document, 'pointermove', p.x, p.y ); await sleep( 50 );
		var mark = cd().getElementById( 'vb-dropmark' ), m = mark ? mark.className + '/' + mark.style.display : 'none';
		ptr( tile, 'pointerup', p.x, p.y );
		tile.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) ); // the click that follows a drag
		await sleep( 300 );
		return m;
	}
	setTimeout( async function () {
		var r = {};
		try {
			var t0 = Date.now();
			while ( ! ( cd() && cd().body && q( '.vb-inspector' ) ) && Date.now() - t0 < 30000 ) { await sleep( 200 ); }
			await sleep( 1500 );
			var g = 0; while ( cd().querySelector( '[data-node]' ) && g++ < 60 ) { cd().querySelector( '[data-node]' ).click(); await sleep( 80 ); var d = q( '.vb-inspector [data-del]' ); if ( d ) { d.click(); await sleep( 100 ); } }
			await openAdd(); q( '#vb-addmenu [data-insert="section"]' ).click(); await sleep( 250 );
			var secId = sel();
			await openAdd(); q( '#vb-addmenu [data-insert="heading"]' ).click(); await sleep( 250 );
			var hId = sel();
			cd().getElementById( secId ).dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) ); await sleep( 150 );
			await openAdd(); q( '#vb-addmenu [data-insert="div"]' ).click(); await sleep( 300 );
			var dId = sel();
			r.start = order( secId );
			// 1. drag a Button tile onto the top half of the heading -> before heading
			var hr = cd().getElementById( hId ).getBoundingClientRect();
			r.markBefore = await dragTile( 'button', hr.left + 20, hr.top + 3 );
			r.afterTileDrop = order( secId );
			r.nodeCount1 = cd().querySelectorAll( '[data-node]' ).length;
			// 2. drag a Text tile into the middle of the empty div -> inside
			var dr = cd().getElementById( dId ).getBoundingClientRect();
			r.markInside = await dragTile( 'text', dr.left + dr.width / 2, dr.top + dr.height / 2 );
			r.divKids = cd().getElementById( dId ).querySelectorAll( '[data-node]' ).length;
			// 3. move the (now filled) div before the button by dragging it on the canvas
			cd().getElementById( dId ).dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) ); await sleep( 200 );
			var dEl = cd().getElementById( dId ), dr2 = dEl.getBoundingClientRect();
			var first = cd().getElementById( secId ).querySelector( '[data-node]' ), fr = first.getBoundingClientRect();
			ptr( dEl, 'pointerdown', dr2.left + 4, dr2.bottom - 4 );
			ptr( cd(), 'pointermove', dr2.left + 4, dr2.bottom - 20 );
			ptr( cd(), 'pointermove', fr.left + 10, fr.top + 2 ); await sleep( 50 );
			r.ghost = ( q( '#vb-dndghost' ) || {} ).textContent;
			ptr( dEl, 'pointerup', fr.left + 10, fr.top + 2 );
			dEl.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );
			await sleep( 300 );
			r.afterMove = order( secId );
			r.selAfterMove = sel() === dId;
			// 4. a drop onto itself is refused
			dEl = cd().getElementById( dId ); var dr3 = dEl.getBoundingClientRect();
			ptr( dEl, 'pointerdown', dr3.left + 4, dr3.top + 4 );
			ptr( cd(), 'pointermove', dr3.left + 30, dr3.top + 30 );
			ptr( cd(), 'pointermove', dr3.left + dr3.width / 2, dr3.top + dr3.height / 2 ); await sleep( 50 );
			r.selfNo = cd().documentElement.classList.contains( 'vb-dnd-no' );
			ptr( dEl, 'pointerup', dr3.left + dr3.width / 2, dr3.top + dr3.height / 2 ); await sleep( 200 );
			r.afterSelf = order( secId );
			// 5. Escape cancels
			dEl = cd().getElementById( dId ); dr3 = dEl.getBoundingClientRect();
			ptr( dEl, 'pointerdown', dr3.left + 4, dr3.top + 4 );
			ptr( cd(), 'pointermove', dr3.left + 4, dr3.top + 40 );
			key( 'Escape', {}, cd().body ); await sleep( 100 );
			r.escCancel = ! cd().documentElement.classList.contains( 'vb-dnd' ) && ! ( q( '#vb-dndghost' ) && q( '#vb-dndghost' ).style.display === 'block' );
			ptr( dEl, 'pointerup', 0, 0 ); await sleep( 200 );
			r.afterEsc = order( secId );
			// 6. copy / paste style
			cd().getElementById( hId ).dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) ); await sleep( 200 );
			key( 'C', { ctrlKey: true, shiftKey: true } ); await sleep( 200 );
			r.clip = !! localStorage.getItem( 'velox_builder_style_clip' );
			cd().getElementById( dId ).dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) ); await sleep( 200 );
			key( 'V', { ctrlKey: true, shiftKey: true } ); await sleep( 300 );
			r.pastedClasses = cd().getElementById( dId ).className;
			// 7. zoom
			var z = q( '#vb-zoom' ); r.zoomOpts = z ? z.options.length : 0;
			z.value = '1'; fire( z, 'change' ); await sleep( 300 );
			r.zoom100 = Math.round( document.getElementById( 'vb-canvas-wrap' ).getBoundingClientRect().width );
			key( '!', { shiftKey: true, code: 'Digit1' } ); await sleep( 300 );
			r.zoomFit = Math.round( document.getElementById( 'vb-canvas-wrap' ).getBoundingClientRect().width );
			// 8. resize the style panel
			var h = q( '#vb-iresize' ), hr2 = h.getBoundingClientRect();
			ptr( h, 'pointerdown', hr2.left + 3, 300 ); ptr( h, 'pointermove', 420, 300 ); ptr( h, 'pointerup', 420, 300 ); await sleep( 300 );
			r.inspW = Math.round( q( '.vb-inspector' ).getBoundingClientRect().width );
			r.savedW = localStorage.getItem( 'velox_builder_iw' );
			h.dispatchEvent( new MouseEvent( 'dblclick', { bubbles: true } ) ); await sleep( 300 );
			r.inspWReset = Math.round( q( '.vb-inspector' ).getBoundingClientRect().width );
			// 9. command palette
			var before = cd().querySelectorAll( '[data-node]' ).length;
			key( 'k', { ctrlKey: true } ); await sleep( 200 );
			r.cmdkOpen = !! q( '.vb-cmdk.open' );
			var ci = q( '#vb-cmdk-in' ); ci.value = 'hdg'; fire( ci, 'input' ); await sleep( 100 );
			r.cmdkFirst = ( q( '.vb-cmdk-i.on .vb-cmdk-l' ) || {} ).textContent;
			r.cmdkCount = qa( '.vb-cmdk-i' ).length;
			ci.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Enter', bubbles: true, cancelable: true } ) ); await sleep( 300 );
			r.cmdkClosed = ! q( '.vb-cmdk.open' );
			r.cmdkInserted = cd().querySelectorAll( '[data-node]' ).length - before;
			key( 'k', { ctrlKey: true } ); await sleep( 150 );
			ci = q( '#vb-cmdk-in' ); ci.value = 'publ'; fire( ci, 'input' ); await sleep( 80 );
			r.cmdkPub = ( q( '.vb-cmdk-i.on .vb-cmdk-l' ) || {} ).textContent;
			ci.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true, cancelable: true } ) ); await sleep( 150 );
			r.cmdkEsc = ! q( '.vb-cmdk.open' );
			// 10. inherited value shows as a placeholder
			cd().getElementById( hId ).dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) ); await sleep( 200 );
			await pickCls( '#' );
			var fs = q( '[data-setnum="fontSize"]' );
			r.inherit = fs ? { value: fs.value, ph: fs.placeholder } : null;
			var dots = qa( '.vb-f-num .vb-src.orange' ).length; r.orangeDots = dots;
		} catch ( e ) { r.exc = e.message + ' ' + ( e.stack || '' ).slice( 0, 300 ); }
		r.errors = errors;
		await report( r );
		document.title = 'VXDONE';
	}, 500 );
}() );
