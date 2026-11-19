( function () {
	var P = new URLSearchParams( location.search );
	if ( 'styles' !== P.get( 'vxtest' ) ) { return; }
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
	function click( id, o ) { cd().getElementById( id ).dispatchEvent( new MouseEvent( 'click', Object.assign( { bubbles: true }, o || {} ) ) ); }
	function css() { return cd().getElementById( 'vb-style' ).textContent.replace( /\s+/g, ' ' ); }
	function block( sel, inMedia ) {
		var t = css();
		if ( inMedia ) { var m = t.indexOf( inMedia ); if ( m < 0 ) { return ''; } t = t.slice( m ); }
		var i = t.indexOf( sel + ' {' ); return i < 0 ? '' : t.slice( i, t.indexOf( '}', i ) + 1 );
	}
	function report( k, r ) { var b = new FormData(); b.append( 'action', 'vxt_report' ); b.append( 'r', JSON.stringify( r ) ); return fetch( window.VELOX_BUILDER.ajaxurl + '?k=' + k, { method: 'POST', body: b, credentials: 'same-origin' } ); }
	async function add( k ) { if ( ! q( '#vb-addmenu.open' ) ) { q( '[data-add]' ).click(); await sleep( 150 ); } q( '#vb-addmenu [data-insert="' + k + '"]' ).click(); await sleep( 250 ); return sel(); }
	async function tab( t ) { var b = q( '.vb-tab[data-tab="' + t + '"]' ); if ( b ) { b.click(); await sleep( 150 ); } }
	async function blur() { document.body.dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true } ) ); document.body.click(); await sleep( 120 ); }
	async function addClass( name ) { q( '[data-addclass]' ).click(); await sleep( 150 ); var i = q( '#vb-ask-in' ); i.value = name; key( 'Enter', {}, i ); await sleep( 250 ); }
	async function filter( text ) { var f = q( '#vb-sfilter' ); if ( ! f ) { return; } f.focus(); f.value = text; fire( f, 'input' ); await sleep( 120 ); }
	// Sets one property through its real control. Returns what it did.
	async function setVia( prop, type, val ) {
		await filter( '' );
		var c;
		if ( 'seg' === type ) { c = q( '[data-set="' + prop + '"][data-val="' + val + '"]' ); if ( c && ! c.classList.contains( 'on' ) ) { c.click(); } }
		else if ( 'num' === type ) { c = q( 'input[data-setnum="' + prop + '"]' ); if ( c ) { c.focus(); c.value = val; fire( c, 'input' ); await sleep( 200 ); await blur(); } }
		else if ( 'select' === type || 'font' === type ) { c = q( 'select[data-setsel="' + prop + '"]' ); if ( c ) { c.value = val; fire( c, 'change' ); } }
		else { c = q( 'input[data-set="' + prop + '"]' ); if ( c ) { c.focus(); c.value = val; fire( c, 'input' ); await sleep( 200 ); await blur(); } }
		await sleep( 120 );
		return !! c;
	}
	var KEBAB = function ( p ) { return p.replace( /[A-Z]/g, function ( m ) { return '-' + m.toLowerCase(); } ); };
	var TESTS = [
		// [prop, type, value to set, expected css value]
		[ 'display', 'seg', 'flex', 'flex' ], [ 'flexDirection', 'seg', 'row', 'row' ], [ 'flexWrap', 'seg', 'wrap', 'wrap' ],
		[ 'justifyContent', 'seg', 'space-between', 'space-between' ], [ 'alignItems', 'seg', 'center', 'center' ], [ 'gap', 'num', '13', '13px' ],
		[ 'rowGap', 'num', '5', '5px' ], [ 'columnGap', 'num', '6', '6px' ],
		[ 'alignSelf', 'seg', 'flex-end', 'flex-end' ], [ 'order', 'text', '2', '2' ], [ 'flexGrow', 'text', '1', '1' ], [ 'flexShrink', 'text', '0', '0' ], [ 'flexBasis', 'num', '200', '200px' ],
		[ 'position', 'seg', 'relative', 'relative' ], [ 'top', 'num', '3', '3px' ], [ 'right', 'num', '4', '4px' ], [ 'bottom', 'num', '5', '5px' ], [ 'left', 'num', '6', '6px' ],
		[ 'zIndex', 'text', '7', '7' ], [ 'overflow', 'seg', 'hidden', 'hidden' ],
		[ 'aspectRatio', 'text', '16 / 9', '16 / 9' ],
		[ 'fontFamily', 'font', 'Georgia, serif', 'Georgia, serif' ], [ 'fontSize', 'num', '19', '19px' ], [ 'fontWeight', 'select', '300', '300' ],
		[ 'fontStyle', 'seg', 'italic', 'italic' ], [ 'lineHeight', 'text', '1.4', '1.4' ], [ 'letterSpacing', 'num', '2', '2px' ],
		[ 'textAlign', 'seg', 'center', 'center' ], [ 'textTransform', 'seg', 'uppercase', 'uppercase' ], [ 'textDecoration', 'seg', 'underline', 'underline' ],
		[ 'whiteSpace', 'seg', 'nowrap', 'nowrap' ], [ 'color', 'color', '#123456', '#123456' ], [ 'textShadow', 'text', '0 1px 2px #000', '0 1px 2px #000' ],
		[ 'background', 'color', '#fafafa', '#fafafa' ], [ 'backgroundImage', 'bgimage', 'linear-gradient(90deg, #ff0000, #0000ff)', 'linear-gradient(90deg, #ff0000, #0000ff)' ],
		[ 'backgroundSize', 'seg', 'cover', 'cover' ], [ 'backgroundPosition', 'select', 'center top', 'center top' ], [ 'backgroundRepeat', 'seg', 'no-repeat', 'no-repeat' ],
		[ 'opacity', 'text', '0.9', '0.9' ], [ 'boxShadow', 'text', '0 4px 12px rgba(0,0,0,.1)', '0 4px 12px rgba(0,0,0,.1)' ],
		[ 'borderWidth', 'num', '2', '2px' ], [ 'borderStyle', 'seg', 'dashed', 'dashed' ], [ 'borderColor', 'color', '#ff00ff', '#ff00ff' ], [ 'borderRadius', 'num', '9', '9px' ],
		[ 'borderTopLeftRadius', 'num', '1', '1px' ], [ 'borderTopRightRadius', 'num', '2', '2px' ], [ 'borderBottomRightRadius', 'num', '3', '3px' ], [ 'borderBottomLeftRadius', 'num', '4', '4px' ],
		[ 'transform', 'text', 'rotate(1deg)', 'rotate(1deg)' ], [ 'transition', 'text', 'all .2s ease', 'all .2s ease' ], [ 'filter', 'text', 'grayscale(1)', 'grayscale(1)' ],
		[ 'backdropFilter', 'text', 'blur(4px)', 'blur(4px)' ], [ 'mixBlendMode', 'select', 'multiply', 'multiply' ], [ 'cursor', 'select', 'pointer', 'pointer' ],
		[ 'gridTemplateColumns', 'text', '1fr 2fr', '1fr 2fr', 'grid' ], [ 'gridTemplateRows', 'text', 'auto 1fr', 'auto 1fr', 'grid' ]
	];
	setTimeout( async function () {
		var r = { fail: [], pass: 0, notes: [] };
		try {
			var t0 = Date.now();
			while ( ! ( cd() && cd().body && q( '.vb-inspector' ) ) && Date.now() - t0 < 30000 ) { await sleep( 200 ); }
			await sleep( 1500 );
			var g = 0; while ( cd().querySelector( '[data-node]' ) && g++ < 80 ) { cd().querySelector( '[data-node]' ).click(); await sleep( 60 ); var d = q( '.vb-inspector [data-del]' ); if ( d ) { d.click(); await sleep( 80 ); } }
			var secId = await add( 'section' );
			var pId = await add( 'div' ); await addClass( 'tst-p' );
			await tab( 'all' ); await setVia( 'display', 'seg', 'flex' ); await setVia( 'flexDirection', 'seg', 'row' );
			var aId = await add( 'div' ); await addClass( 'tst-a' );
			r.parentOk = !! cd().querySelector( '#' + pId + ' > #' + aId );
			r.activeClass = activeCls();
			await tab( 'all' );
			for ( var i = 0; i < TESTS.length; i++ ) {
				var t = TESTS[ i ], prop = t[ 0 ];
				if ( 'grid' === t[ 4 ] ) { await setVia( 'display', 'seg', 'grid' ); }
				var found = await setVia( prop, t[ 1 ], t[ 2 ] );
				var b = block( '.tst-a' ), want = KEBAB( prop ).replace( 'background-image', 'background-image' ) + ': ' + t[ 3 ] + ';';
				if ( 'background' === prop ) { want = 'background: ' + t[ 3 ] + ';'; }
				if ( ! found ) { r.fail.push( prop + ': control not found' ); }
				else if ( b.indexOf( want ) < 0 ) { r.fail.push( prop + ': expected "' + want + '" in ' + b.slice( 0, 400 ) ); }
				else { r.pass++; }
				if ( 'grid' === t[ 4 ] ) { await setVia( 'display', 'seg', 'flex' ); }
			}
			// child-of-grid props: parent becomes a grid
			click( pId ); await sleep( 150 ); await tab( 'all' ); await setVia( 'display', 'seg', 'grid' );
			click( aId ); await sleep( 200 ); await tab( 'all' );
			r.activeAfterReselect = activeCls();
			for ( var gp = 0; gp < 2; gp++ ) {
				var gprop = [ 'gridColumn', 'gridRow' ][ gp ], gv = [ 'span 2', '1 / 3' ][ gp ];
				var gf = await setVia( gprop, 'text', gv );
				if ( ! gf ) { r.fail.push( gprop + ': control not found (parent is grid)' ); } else if ( block( '.tst-a' ).indexOf( KEBAB( gprop ) + ': ' + gv ) < 0 ) { r.fail.push( gprop + ' missing' ); } else { r.pass++; }
			}
			// box: padding all sides, margin each side, size fields
			var mode = q( '[data-boxmode="padding"][data-mode="all"]' ); if ( mode ) { mode.click(); await sleep( 150 ); }
			var pa = q( '[data-setbox="padding"]' ) || q( '[data-link="padding"]' ); if ( pa ) { pa.focus(); pa.value = '9'; fire( pa, 'input' ); await sleep( 250 ); await blur(); }
			var bpad = block( '.tst-a' );
			[ 'padding-top: 9px', 'padding-right: 9px', 'padding-bottom: 9px', 'padding-left: 9px' ].forEach( function ( w ) { if ( bpad.indexOf( w ) < 0 ) { r.fail.push( 'padding all: ' + w + ' missing' ); } else { r.pass++; } } );
			var mi = q( '[data-boxmode="padding"][data-mode="individual"]' ); if ( mi ) { mi.click(); await sleep( 150 ); }
			var MS = { marginTop:'1', marginRight:'2', marginBottom:'3', marginLeft:'4', paddingTop:'11', width:'50', minWidth:'10', maxWidth:'600', height:'120', minHeight:'20', maxHeight:'900' };
			for ( var mk in MS ) {
				var mf = q( 'input[data-setnum="' + mk + '"]' );
				if ( ! mf ) { r.fail.push( mk + ': field not found' ); continue; }
				mf.focus(); mf.value = MS[ mk ]; fire( mf, 'input' ); await sleep( 220 ); await blur();
				if ( block( '.tst-a' ).indexOf( KEBAB( mk ) + ': ' + MS[ mk ] + 'px' ) < 0 ) { r.fail.push( mk + ' missing: ' + block( '.tst-a' ).slice( 0, 200 ) ); } else { r.pass++; }
			}
			// unit change: width to %
			var wu = q( 'select[data-setunit="width"]' );
			if ( wu ) { wu.value = '%'; fire( wu, 'change' ); await sleep( 250 ); if ( block( '.tst-a' ).indexOf( 'width: 50%' ) < 0 ) { r.fail.push( 'unit change to %: ' + ( block( '.tst-a' ).match( / width: [^;]+/ ) || [ 'none' ] )[ 0 ] ); } else { r.pass++; } }
			else { r.fail.push( 'width unit select not found' ); }
			// remove a value with its dot
			var fsF = q( 'input[data-setnum="fontSize"]' ), dot = fsF && fsF.closest( '.vb-f' ).querySelector( '.vb-src' );
			if ( dot ) { dot.click(); await sleep( 200 ); if ( block( '.tst-a' ).indexOf( 'font-size' ) > -1 ) { r.fail.push( 'dot click did not remove font-size' ); } else { r.pass++; } }
			await setVia( 'fontSize', 'num', '19' );
			// computed check on canvas for a sample
			var cs = cd().defaultView.getComputedStyle( cd().getElementById( aId ) );
			[ [ 'color', 'rgb(18, 52, 86)' ], [ 'font-weight', '300' ], [ 'text-transform', 'uppercase' ], [ 'border-top-style', 'dashed' ], [ 'opacity', '0.9' ], [ 'z-index', '7' ] ].forEach( function ( c ) {
				if ( cs.getPropertyValue( c[ 0 ] ) !== c[ 1 ] ) { r.fail.push( 'computed ' + c[ 0 ] + '=' + cs.getPropertyValue( c[ 0 ] ) + ' want ' + c[ 1 ] ); } else { r.pass++; }
			} );
			// mobile breakpoint
			q( '#vb-bp button[data-bp="md"]' ).click(); await sleep( 400 );
			r.canvasWidthMd = document.getElementById( 'vb-canvas' ).style.width;
			await tab( 'all' );
			await setVia( 'fontSize', 'num', '11' );
			await setVia( 'flexDirection', 'seg', 'column' );
			var mq = block( '.tst-a', '@media (max-width: 767.98px)' );
			if ( mq.indexOf( 'font-size: 11px' ) < 0 || mq.indexOf( 'flex-direction: column' ) < 0 ) { r.fail.push( 'mobile rules: ' + mq ); } else { r.pass++; }
			r.baseKept = block( '.tst-a' ).indexOf( 'font-size: 19px' ) > -1;
			// the inspector shows the mobile value, desktop inherits as placeholder
			r.mdField = ( q( 'input[data-setnum="fontSize"]' ) || {} ).value;
			q( '#vb-bp button[data-bp="lg"]' ).click(); await sleep( 300 ); await tab( 'all' );
			r.tabletInherit = { value: ( q( 'input[data-setnum="fontSize"]' ) || {} ).value, ph: ( q( 'input[data-setnum="fontSize"]' ) || {} ).placeholder };
			q( '#vb-bp button[data-bp="xxl"]' ).click(); await sleep( 300 ); await tab( 'all' );
			await setVia( 'gap', 'num', '31' );
			if ( css().indexOf( '@media (max-width: 1399.98px)' ) < 0 ) { r.fail.push( 'laptop media query missing' ); } else { r.pass++; }
			q( '#vb-bp button[data-bp="base"]' ).click(); await sleep( 300 );
			// hover state
			await pickState( 'hover' ); await tab( 'all' );
			await setVia( 'color', 'color', '#00aa00' );
			await setVia( 'background', 'color', '#eeeeee' );
			var hv = block( '.tst-a:hover' );
			if ( hv.indexOf( 'color: #00aa00' ) < 0 || hv.indexOf( 'background: #eeeeee' ) < 0 ) { r.fail.push( 'hover: ' + hv ); } else { r.pass++; }
			// custom state via dialog
			await pickOpt( '#vb-statepick', '__add' ); await sleep( 150 );
			var si = q( '#vb-ask-in' ); si.value = 'focus-within'; key( 'Enter', {}, si ); await sleep( 250 );
			await tab( 'all' ); await setVia( 'borderColor', 'color', '#abcdef' );
			if ( block( '.tst-a:focus-within' ).indexOf( 'border-color: #abcdef' ) < 0 ) { r.fail.push( 'custom state: ' + block( '.tst-a:focus-within' ) ); } else { r.pass++; }
			await pickState( 'normal' );
			// element-only styles (This element)
			await pickCls( '#' ); await tab( 'all' );
			await setVia( 'letterSpacing', 'num', '3' );
			if ( css().indexOf( '#' + aId + ' {' ) < 0 || block( '#' + aId ).indexOf( 'letter-spacing: 3px' ) < 0 ) { r.fail.push( 'element style: ' + block( '#' + aId ) ); } else { r.pass++; }
			// save + publish
			q( '#vb-save' ).click(); await sleep( 3500 );
			r.saveLabel = q( '#vb-save' ).textContent;
			q( '#vb-publish' ).click(); await sleep( 800 );
			if ( q( '.vb-ask.open' ) ) { q( '[data-ask="yes"]' ).click(); }
			await sleep( 5000 );
			r.pubLabel = q( '#vb-publish' ).textContent;
			r.aId = aId; r.pId = pId;
			r.canvasCss = css();
		} catch ( e ) { r.exc = e.message + ' ' + ( e.stack || '' ).slice( 0, 300 ); }
		r.errors = errors;
		await report( 'styles', r );
	}, 500 );
}() );
