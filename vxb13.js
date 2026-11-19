( function () {
	// 4.50 structure suite: section nesting, reusables on the page, type-switch
	// confirmation, reusable naming, Ctrl+Shift+S, tag dropdown, scroll to section.
	var P = new URLSearchParams( location.search );
	if ( 'struct' !== P.get( 'vxtest' ) ) { return; }
	var errors = [];
	window.addEventListener( 'error', function ( e ) { errors.push( 'ERR ' + e.message + ' @' + ( e.lineno || '' ) ); } );
	var sleep = function ( t ) { return new Promise( function ( r ) { setTimeout( r, t ); } ); };
	var q = function ( s, r ) { return ( r || document ).querySelector( s ); }, qa = function ( s, r ) { return [].slice.call( ( r || document ).querySelectorAll( s ) ); };
	function cd() { var f = document.getElementById( 'vb-canvas' ); return f && f.contentDocument; }
	function fire( el, t ) { el.dispatchEvent( new Event( t, { bubbles: true } ) ); }
	function sel() { var s = cd().querySelector( '.vb-sel' ); return s && s.id; }
	function report( r ) { var b = new FormData(); b.append( 'action', 'vxt_report' ); b.append( 'r', JSON.stringify( r ) ); return fetch( window.VELOX_BUILDER.ajaxurl + '?k=struct', { method: 'POST', body: b, credentials: 'same-origin' } ); }
	var R = { pass: [], fail: [] };
	function ok( name, cond, info ) { ( cond ? R.pass : R.fail ).push( name + ( cond || undefined === info ? '' : ' :: ' + info ) ); }
	async function add( k ) { var cl = q( '[data-insp-close]' ); if ( ! q( '#vb-addmenu.open' ) ) { q( '[data-add]' ).click(); await sleep( 150 ); } q( '#vb-addmenu [data-insert="' + k + '"]' ).click(); await sleep( 280 ); return sel(); }
	function click( id ) { cd().getElementById( id ).dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) ); }
	async function selectRow( id ) { var row = q( '[data-stnode="' + id + '"]' ); if ( row ) { row.click(); await sleep( 250 ); return true; } click( id ); await sleep( 250 ); return false; }
	function parentId( id ) { var e = cd().getElementById( id ); var p = e && e.parentElement && e.parentElement.closest( '[data-node]' ); return p ? p.id : null; }
	function lastToast() { var t = qa( '.vb-toast, #vb-toast' ).pop(); return t ? t.textContent : ''; }
	async function ctx( id, act ) { var e = cd().getElementById( id ); e.dispatchEvent( new MouseEvent( 'contextmenu', { bubbles: true, clientX: 50, clientY: 50 } ) ); await sleep( 200 ); var b = q( '[data-ctx="' + act + '"]' ); if ( b ) { b.click(); await sleep( 250 ); } return !! b; }
	async function run() {
		var t0 = Date.now();
		while ( ! ( cd() && cd().body && q( '.vb-inspector' ) ) && Date.now() - t0 < 30000 ) { await sleep( 200 ); }
		await sleep( 1500 );
		try {
			// open the structure panel
			if ( ! q( '#vb-struct.open, #vb-struct .vb-st-tree' ) ) { var sb = q( '#vb-structure' ); if ( sb ) { sb.click(); await sleep( 250 ); } }
			// ---- section nesting
			var A = await add( 'section' );
			var D = await add( 'div' );
			ok( 'div goes inside section', parentId( D ) === A, parentId( D ) );
			var S2 = await add( 'section' );
			ok( 'section added in a div lands in the section, not the div', parentId( S2 ) === A, 'parent ' + parentId( S2 ) );
			ok( 'it sits right after the div', cd().getElementById( D ).nextElementSibling && cd().getElementById( D ).nextElementSibling.id === S2 );
			ok( 'a toast explains it', /section/i.test( lastToast() ), lastToast() );
			await selectRow( S2 );
			var S3 = await add( 'section' );
			ok( 'no third level: a section added in a nested section goes next to it', parentId( S3 ) === A, 'parent ' + parentId( S3 ) );
			await selectRow( D );
			var H = await add( 'heading' );
			ok( 'heading goes into the div', parentId( H ) === D, parentId( H ) );
			// wrap a section in a div: refused
			var before = cd().querySelectorAll( '[data-node]' ).length;
			await ctx( S2, 'wrap' );
			ok( 'wrapping a section in a div is refused', cd().querySelectorAll( '[data-node]' ).length === before && parentId( S2 ) === A, lastToast() );
			// paste a section while a heading in a div is selected
			await selectRow( S3 ); document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'c', ctrlKey: true, bubbles: true } ) ); await sleep( 200 );
			await selectRow( H );
			var ids0 = qa( '[data-node]', cd() ).map( function ( e ) { return e.id; } );
			document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'v', ctrlKey: true, bubbles: true } ) ); await sleep( 900 );
			var pasted = qa( '[data-node]', cd() ).filter( function ( e ) { return ids0.indexOf( e.id ) < 0 && e.parentElement.closest( '[data-node]' ) !== null; } )[ 0 ] || qa( 'section[data-node],[data-node].section', cd() ).filter( function ( e ) { return ids0.indexOf( e.id ) < 0; } )[ 0 ];
			ok( 'pasted section is not inside the div', pasted && parentId( pasted.id ) !== D, pasted ? 'parent ' + parentId( pasted.id ) : 'nothing pasted' );
			// ---- tag dropdown + level badge
			await selectRow( H );
			var tp = q( '[data-tagpick]' ); ok( 'tag dropdown on the element name', !! tp );
			if ( tp ) {
				tp.click(); await sleep( 200 );
				var row3 = q( '.vxs-pop [data-vxs-v="h3"]' ); ok( 'dropdown offers h1–h6', !! row3 && !! q( '.vxs-pop [data-vxs-v="h6"]' ) );
				if ( row3 ) { row3.click(); await sleep( 300 ); }
				ok( 'heading became <h3>', cd().getElementById( H ) && 'H3' === cd().getElementById( H ).tagName, cd().getElementById( H ) && cd().getElementById( H ).tagName );
				var badge = q( '[data-stnode="' + H + '"] .vb-hlvl' ); ok( 'structure shows H3', !! badge && 'H3' === badge.textContent, badge && badge.textContent );
				var ib = q( '.vb-insp-head .vb-hlvl' ); ok( 'inspector shows H3', !! ib && 'H3' === ib.textContent );
			}
			// ---- reusable: name dialog, then insert goes on the page
			await selectRow( D );
			var made = await ctx( D, 'reuse' );
			var askIn = q( '#vb-ask.open #vb-ask-in' );
			ok( 'make reusable asks for a name', made && !! askIn, made ? 'no dialog' : 'no menu item' );
			if ( askIn ) {
				askIn.value = 'Struct test block ' + Date.now();
				q( '#vb-ask [data-ask="yes"]' ).click(); await sleep( 2500 );
				ok( 'element replaced by a reusable copy', !! cd().querySelector( '.vb-reuse' ) );
			}
			var reuseIds0 = qa( '.vb-reuse', cd() ).map( function ( e ) { return e.id; } );
			await selectRow( S2 );
			if ( ! q( '#vb-addmenu.open' ) ) { q( '[data-add]' ).click(); await sleep( 200 ); }
			var rt = qa( '#vb-addmenu [data-insert-reuse]' ).pop();
			ok( 'reusables listed in + Add', !! rt );
			if ( rt ) {
				rt.click(); await sleep( 400 );
				var nr = qa( '.vb-reuse', cd() ).filter( function ( e ) { return reuseIds0.indexOf( e.id ) < 0; } )[ 0 ];
				ok( 'inserted reusable sits on the page, not in the section', nr && null === parentId( nr.id ), nr ? 'parent ' + parentId( nr.id ) : 'none' );
				ok( 'right after the section you were in', nr && cd().getElementById( A ).nextElementSibling === nr, nr && nr.previousElementSibling && nr.previousElementSibling.id );
			}
			// ---- document type switch asks first
			var ks = q( '#vb-kind' );
			if ( ks ) {
				ks.value = 'reusable'; fire( ks, 'change' ); await sleep( 250 );
				ok( 'type switch asks first', !! q( '#vb-ask.open' ) );
				ok( 'reusable switch asks for a name', !! q( '#vb-ask.open #vb-ask-in' ) );
				ok( 'select waits for the answer', 'page' === ks.value, ks.value );
				var no = q( '#vb-ask [data-ask="no"]' ); if ( no ) { no.click(); await sleep( 250 ); }
				ok( 'cancel keeps it a page', 'page' === ks.value, ks.value );
				ks.value = 'template'; fire( ks, 'change' ); await sleep( 250 );
				var yes = q( '#vb-ask [data-ask="yes"]' ); if ( yes ) { yes.click(); await sleep( 1500 ); }
				ok( 'confirm switches the type', 'template' === ks.value, ks.value );
				ks.value = 'page'; fire( ks, 'change' ); await sleep( 250 );
				yes = q( '#vb-ask [data-ask="yes"]' ); if ( yes ) { yes.click(); await sleep( 1500 ); }
				ok( 'and back to a page', 'page' === ks.value, ks.value );
			} else { ok( 'type switch present', false ); }
			// ---- scroll to section
			for ( var i = 0; i < 4; i++ ) { var cl = q( '[data-insp-close]' ); if ( cl ) { cl.click(); await sleep( 80 ); } var sx = await add( 'section' ); cd().getElementById( sx ).style.minHeight = '900px'; }
			var last = qa( 'section[data-node]', cd() ).pop();
			cd().defaultView.scrollTo( 0, 0 ); await sleep( 200 );
			await selectRow( last.id ); await sleep( 900 );
			var top = last.getBoundingClientRect().top;
			ok( 'selecting a section scrolls to it', top >= -2 && top < 120, 'top ' + Math.round( top ) );
			// ---- Ctrl+Shift+S publishes
			var pub0 = ( q( '#vb-publish' ) || {} ).textContent;
			document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'S', ctrlKey: true, shiftKey: true, bubbles: true } ) ); await sleep( 2500 );
			var y2 = q( '#vb-ask.open [data-ask="yes"]' ); if ( y2 ) { y2.click(); await sleep( 2500 ); }
			ok( 'Ctrl+Shift+S publishes', /Published|Veröffentlicht/.test( ( q( '#vb-publish' ) || {} ).textContent || '' ), pub0 + ' -> ' + ( q( '#vb-publish' ) || {} ).textContent );
		} catch ( e ) { R.fail.push( 'EXC ' + e.message ); }
		R.errors = errors.slice( 0, 6 );
		await report( R );
		document.title = 'VXDONE';
	}
	setTimeout( run, 500 );
}() );
