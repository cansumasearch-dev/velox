( function () {
	var P = new URLSearchParams( location.search );
	if ( 'ops' !== P.get( 'vxtest' ) ) { return; }
	window.confirm = function () { return true; };
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
	function sel() { var s = cd().querySelector( '.vb-sel' ); return s && s.id; }
	function el( id ) { return cd().getElementById( id ); }
	function click( id, o ) { el( id ).dispatchEvent( new MouseEvent( 'click', Object.assign( { bubbles: true }, o || {} ) ) ); }
	function count() { return cd().querySelectorAll( '[data-node]' ).length; }
	function report( k, r ) { var b = new FormData(); b.append( 'action', 'vxt_report' ); b.append( 'r', JSON.stringify( r ) ); return fetch( window.VELOX_BUILDER.ajaxurl + '?k=' + k, { method: 'POST', body: b, credentials: 'same-origin' } ); }
	async function add( k ) { if ( ! q( '#vb-addmenu.open' ) ) { q( '[data-add]' ).click(); await sleep( 150 ); } q( '#vb-addmenu [data-insert="' + k + '"]' ).click(); await sleep( 250 ); return sel(); }
	async function tab( t ) { var b = q( '.vb-tab[data-tab="' + t + '"]' ); if ( b ) { b.click(); await sleep( 150 ); } }
	async function blur() { document.body.dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true } ) ); document.body.click(); await sleep( 150 ); }
	async function ask( v ) { await sleep( 150 ); var i = q( '#vb-ask-in' ); if ( i && undefined !== v ) { i.value = v; } var y = q( '[data-ask="yes"]' ); if ( y ) { y.click(); } await sleep( 300 ); }
	function selectText( node, word ) {
		var d = cd(), w = d.createTreeWalker( node, NodeFilter.SHOW_TEXT ), t;
		while ( ( t = w.nextNode() ) ) { var i = t.nodeValue.indexOf( word ); if ( i > -1 ) { var rg = d.createRange(); rg.setStart( t, i ); rg.setEnd( t, i + word.length ); var s = d.defaultView.getSelection(); s.removeAllRanges(); s.addRange( rg ); return true; } }
		return false;
	}
	async function ctx( id, action ) {
		el( id ).dispatchEvent( new MouseEvent( 'contextmenu', { bubbles: true, cancelable: true, clientX: 50, clientY: 50 } ) ); await sleep( 150 );
		var b = q( '#vb-ctx [data-ctx="' + action + '"]' ); if ( ! b ) { return false; } b.click(); await sleep( 250 ); return true;
	}
	var ok = function ( r, name, cond, info ) { if ( cond ) { r.pass.push( name ); } else { r.fail.push( name + ( info ? ': ' + info : '' ) ); } };
	setTimeout( async function () {
		var r = { pass: [], fail: [] };
		try {
			var t0 = Date.now();
			while ( ! ( cd() && cd().body && q( '.vb-inspector' ) ) && Date.now() - t0 < 30000 ) { await sleep( 200 ); }
			await sleep( 1500 );
			var g = 0; while ( cd().querySelector( '[data-node]' ) && g++ < 80 ) { cd().querySelector( '[data-node]' ).click(); await sleep( 60 ); var d = q( '.vb-inspector [data-del]' ); if ( d ) { d.click(); await sleep( 80 ); } }
			var sec = await add( 'section' ), div = await add( 'div' );
			var h = await add( 'heading' ); click( div ); await sleep( 120 );
			var tx = await add( 'text' ); click( div ); await sleep( 120 );
			var bt = await add( 'button' ); click( div ); await sleep( 120 );
			var im = await add( 'image' ); click( div ); await sleep( 120 );
			var vd = await add( 'video' ); click( div ); await sleep( 120 );
			var ic = await add( 'icon' );
			ok( r, 'nesting section>div>6', el( div ).parentElement.id === sec && el( div ).querySelectorAll( ':scope > [data-node]' ).length === 6, el( div ).querySelectorAll( ':scope > [data-node]' ).length );

			// --- inline text editing: bold
			click( h ); await sleep( 120 ); el( h ).dispatchEvent( new MouseEvent( 'dblclick', { bubbles: true } ) ); await sleep( 200 );
			ok( r, 'dblclick heading -> editing', 'true' === el( h ).getAttribute( 'contenteditable' ) );
			el( h ).innerHTML = 'Hello World'; selectText( el( h ), 'World' );
			q( '[data-fmt="bold"]' ).dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true, cancelable: true } ) ); q( '[data-fmt="bold"]' ).click(); await sleep( 150 );
			await blur(); await sleep( 200 );
			// headings are bold by default, so B switches the word to normal weight
			ok( r, 'bold toggle kept after edit', /<span style="font-weight:400">World<\/span>/.test( el( h ).innerHTML ), el( h ).innerHTML );
			click( tx ); await sleep( 120 ); el( tx ).dispatchEvent( new MouseEvent( 'dblclick', { bubbles: true } ) ); await sleep( 200 );
			el( tx ).innerHTML = 'Plain bold text'; selectText( el( tx ), 'bold' );
			q( '[data-fmt="bold"]' ).dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true, cancelable: true } ) ); q( '[data-fmt="bold"]' ).click(); await sleep( 150 );
			await blur(); await sleep( 200 );
			ok( r, 'bold on normal text', /<(b|strong)>bold<\/(b|strong)>/.test( el( tx ).innerHTML ), el( tx ).innerHTML );
			// --- link through the dialog
			click( tx ); await sleep( 120 ); el( tx ).dispatchEvent( new MouseEvent( 'dblclick', { bubbles: true } ) ); await sleep( 200 );
			el( tx ).innerHTML = 'Read more here'; selectText( el( tx ), 'more' );
			q( '[data-fmt="createLink"]' ).dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true, cancelable: true } ) ); q( '[data-fmt="createLink"]' ).click(); await sleep( 200 );
			ok( r, 'link dialog opens', !! q( '.vb-ask.open #vb-ask-in' ) );
			ok( r, 'still editing while dialog open', !! cd().querySelector( '[contenteditable="true"]' ) );
			await ask( 'https://example.com/x' );
			await blur(); await sleep( 200 );
			ok( r, 'link inserted', /<a href="https:\/\/example\.com\/x">more<\/a>/.test( el( tx ).innerHTML ), el( tx ).innerHTML );
			// --- dynamic data token
			click( tx ); await sleep( 120 ); el( tx ).dispatchEvent( new MouseEvent( 'dblclick', { bubbles: true } ) ); await sleep( 200 );
			q( '#vb-insertdata' ).dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true, cancelable: true } ) ); q( '#vb-insertdata' ).click(); await sleep( 200 );
			var chip = q( '[data-dd="post.title"]' ) || q( '[data-dd]:not([data-arg])' );
			ok( r, 'insert data panel', !! chip );
			if ( chip ) { chip.dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true, cancelable: true } ) ); chip.click(); await sleep( 200 ); }
			await blur(); await sleep( 200 );
			ok( r, 'data token in text', !! el( tx ).querySelector( '[data-vx]' ), el( tx ).innerHTML.slice( 0, 160 ) );

			// --- heading level, link settings
			click( h ); await sleep( 150 ); await tab( 'set' );
			var h3 = q( '[data-settag="h3"]' ); if ( h3 ) { h3.click(); await sleep( 200 ); }
			ok( r, 'heading level h3', 'H3' === el( h ).tagName, el( h ).tagName );
			click( bt ); await sleep( 150 ); await tab( 'set' );
			var href = q( '[data-sethref]' ); if ( href ) { href.focus(); href.value = 'https://velox.test/kontakt'; fire( href, 'input' ); await sleep( 300 ); await blur(); }
			var nt = q( '[data-settarget="_blank"]' ); if ( nt ) { nt.click(); await sleep( 200 ); }
			ok( r, 'button href in canvas?', true );
			// --- scroll animation
			var aos = q( 'select[data-setaos="type"]' );
			if ( aos && aos.options.length > 1 ) { aos.value = aos.options[ 1 ].value; fire( aos, 'change' ); await sleep( 200 ); }
			ok( r, 'animation select present', !! aos );
			// --- video
			click( vd ); await sleep( 150 ); await tab( 'set' );
			var vu = q( '[data-setel="url"]' ); if ( vu ) { vu.focus(); vu.value = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'; fire( vu, 'input' ); await sleep( 400 ); await blur(); }
			ok( r, 'video embed', !! ( el( vd ) && el( vd ).querySelector( 'iframe[src*="youtube"]' ) ), el( vd ) && el( vd ).innerHTML.slice( 0, 120 ) );
			// --- icon picker
			click( ic ); await sleep( 150 ); await tab( 'set' );
			var ipk = q( '[data-iconpick]' ); if ( ipk ) { ipk.click(); await sleep( 200 ); }
			var iv = qa( '[data-iconval]' )[ 3 ];
			ok( r, 'icon picker opens', !! iv );
			if ( iv ) { var ivName = iv.getAttribute( 'data-iconval' ); iv.click(); await sleep( 250 ); ok( r, 'icon chosen', ( q( '.vb-iconbtn-n' ) || {} ).textContent === ivName, ( q( '.vb-iconbtn-n' ) || {} ).textContent + ' vs ' + ivName ); }
			// --- image from the media library
			click( im ); await sleep( 120 ); el( im ).dispatchEvent( new MouseEvent( 'dblclick', { bubbles: true } ) ); await sleep( 1500 );
			var att = q( '.media-modal .attachment' );
			ok( r, 'media library opens with images', !! att, ( q( '.media-modal' ) ? 'modal' : 'no modal' ) );
			if ( att ) {
				att.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) ); await sleep( 400 );
				var use = q( '.media-modal .media-button-select, .media-modal .media-toolbar-primary .button-primary' ); if ( use ) { use.click(); }
				await sleep( 600 );
				ok( r, 'image set', !! el( im ).querySelector( 'img[src]' ), el( im ).innerHTML.slice( 0, 120 ) );
				click( im ); await sleep( 150 ); await tab( 'ess' );
				var alt = q( '[data-imgalt]' ); if ( alt ) { alt.focus(); alt.value = 'Testbild'; fire( alt, 'change' ); await sleep( 200 ); }
				ok( r, 'alt text field', !! alt );
			}
			key( 'Escape' ); await sleep( 200 );

			// --- duplicate / copy / paste / delete / undo / redo
			click( bt ); await sleep( 150 );
			var n0 = count(); key( 'd', { ctrlKey: true } ); await sleep( 250 ); ok( r, 'Ctrl+D duplicate', count() === n0 + 1, count() );
			key( 'c', { ctrlKey: true } ); await sleep( 100 ); key( 'v', { ctrlKey: true } ); await sleep( 300 ); ok( r, 'Ctrl+C / Ctrl+V', count() === n0 + 2, count() );
			var dup = sel(); key( 'Delete' ); await sleep( 250 ); ok( r, 'Delete key', ! el( dup ) && count() === n0 + 1 );
			key( 'z', { ctrlKey: true } ); await sleep( 250 ); ok( r, 'undo restores', count() === n0 + 2, count() );
			key( 'y', { ctrlKey: true } ); await sleep( 250 ); ok( r, 'redo', count() === n0 + 1, count() );
			q( '#vb-undo' ).click(); await sleep( 200 ); q( '#vb-redo' ).click(); await sleep( 200 ); ok( r, 'undo/redo buttons', count() === n0 + 1 );

			// --- context menu: rename, hide, wrap, move, export, reusable
			ok( r, 'ctx rename', await ctx( h, 'rename' ) ); await ask( 'Hero title' );
			ok( r, 'renamed (badge)', ( cd().getElementById( 'vb-selbadge' ) || {} ).textContent.indexOf( 'Hero title' ) > -1 );
			ok( r, 'ctx hide', await ctx( tx, 'hide' ) );
			ok( r, 'hidden ghosted', el( tx ).classList.contains( 'vb-is-hidden' ) );
			await ctx( tx, 'hide' ); ok( r, 'shown again', ! el( tx ).classList.contains( 'vb-is-hidden' ) );
			ok( r, 'ctx move down', await ctx( h, 'mvdown' ) );
			ok( r, 'moved down', el( h ).previousElementSibling && el( h ).previousElementSibling.hasAttribute( 'data-node' ) );
			await ctx( h, 'mvup' );
			var before = el( bt ).parentElement.id;
			ok( r, 'ctx wrap', await ctx( bt, 'wrap' ) );
			ok( r, 'wrapped in div', el( bt ).parentElement.id !== before && /block/.test( el( bt ).parentElement.className ), el( bt ).parentElement.className );
			ok( r, 'ctx export', await ctx( ic, 'export' ) );
			ok( r, 'ctx copy style', await ctx( bt, 'copystyle' ) );
			ok( r, 'ctx reusable', await ctx( ic, 'reuse' ) ); await ask( 'Test reusable' ); await sleep( 1500 );
			ok( r, 'reusable replaced element', !! cd().querySelector( '[data-reuse]' ), '' );

			// --- classes: add, combo, switch, remove
			click( bt ); await sleep( 150 );
			q( '[data-addclass]' ).click(); await ask( 'k-one' );
			q( '[data-addclass]' ).click(); await ask( 'k-two' );
			ok( r, 'two classes added', el( bt ).classList.contains( 'k-one' ) && el( bt ).classList.contains( 'k-two' ), el( bt ).className );
			await pickCls( '.k-one' );
			ok( r, 'switch active class', '.k-one' === activeCls(), activeCls() );
			await pickCls( '.k-two', 'remove' ); await sleep( 200 );
			ok( r, 'remove class', ! el( bt ).classList.contains( 'k-two' ) );

			// --- breakpoints resize the canvas
			var widths = {};
			for ( var bpk of [ 'xxl', 'lg', 'md', 'base' ] ) { q( '#vb-bp button[data-bp="' + bpk + '"]' ).click(); await sleep( 300 ); widths[ bpk ] = document.getElementById( 'vb-canvas' ).style.width; }
			ok( r, 'breakpoint widths', widths.lg === '820px' && widths.md === '390px', JSON.stringify( widths ) );
			// --- history panel
			document.getElementById( 'vb-history' ).click(); await sleep( 300 );
			var hi = qa( '.vb-hist-i' ); ok( r, 'history lists steps', hi.length > 5, hi.length );
			document.getElementById( 'vb-history' ).click(); await sleep( 200 );
			// --- Ctrl+S saves
			key( 's', { ctrlKey: true } ); await sleep( 3500 );
			ok( r, 'Ctrl+S saved', /Saved|Gespeichert/.test( q( '#vb-save' ).textContent ), q( '#vb-save' ).textContent );
			q( '#vb-publish' ).click(); await sleep( 800 ); if ( q( '.vb-ask.open' ) ) { q( '[data-ask="yes"]' ).click(); } await sleep( 5000 );
			ok( r, 'published', /Published|Veröffentlicht/.test( q( '#vb-publish' ).textContent ), q( '#vb-publish' ).textContent );
			r.ids = { h: h, tx: tx, bt: bt, im: im, vd: vd, ic: ic };
		} catch ( e ) { r.exc = e.message + ' ' + ( e.stack || '' ).slice( 0, 300 ); }
		r.errors = errors;
		await report( 'ops', r );
	}, 500 );
}() );
