// Shop settings sweep, run in the shop admin tab (sandbox only).
// Phase A: window.__shopA(section) changes every visible control once, saves,
// stores what it expects in localStorage. Phase B (after a reload):
// window.__shopB(section) re-opens the section and compares.
window.__shopErr = window.__shopErr || [];
if ( ! window.__shopErrHook ) {
	window.__shopErrHook = 1;
	window.addEventListener( 'error', function ( e ) { window.__shopErr.push( e.message + ' @' + e.lineno ); } );
	window.addEventListener( 'unhandledrejection', function ( e ) { window.__shopErr.push( 'REJ ' + ( e.reason && e.reason.message || e.reason ) ); } );
}
window.__sleep = function ( t ) { return new Promise( function ( r ) { setTimeout( r, t ); } ); };
window.__BAD = /\bundefined\b|\bNaN\b|\[object Object\]/;
window.__open = async function ( sec ) {
	location.hash = '#/settings/' + sec; await __sleep( 1600 );
	var m = document.querySelector( '#vsa-setb' ) || document.body;
	return m;
};
window.__ctrls = function ( m ) {
	var out = {};
	m.querySelectorAll( '[data-k]' ).forEach( function ( c ) { if ( c.offsetParent === null && 'checkbox' !== c.type ) { return; } out[ c.getAttribute( 'data-k' ) ] = c; } );
	m.querySelectorAll( '[data-seg]' ).forEach( function ( b ) { if ( b.offsetParent === null ) { return; } var k = b.getAttribute( 'data-seg' ); if ( ! out[ k ] ) { out[ k ] = 'seg'; } } );
	return out;
};
window.__shopA = async function ( sec, skip ) {
	skip = skip || /stripe|paypal|secret|_key$|webhook|^page_|^d_|custom_css|^design$/;
	__shopErr.length = 0;
	var m = await __open( sec ), cs = __ctrls( m ), exp = {}, n = 0, bad = [];
	if ( __BAD.test( m.innerText ) ) { bad.push( 'text: ' + m.innerText.match( __BAD )[ 0 ] ); }
	for ( var k of Object.keys( cs ) ) {
		if ( skip.test( k ) ) { continue; }
		var m2 = document.querySelector( '#vsa-setb' ) || document.body, c = cs[ k ];
		if ( 'seg' === c ) {
			var bs = [].slice.call( m2.querySelectorAll( '[data-seg="' + k + '"]' ) ), off = bs.filter( function ( b ) { return ! b.classList.contains( 'is-on' ); } ).pop();
			if ( off ) { off.click(); await __sleep( 120 ); exp[ k ] = off.getAttribute( 'data-v' ); n++; }
			continue;
		}
		c = m2.querySelector( '[data-k="' + k + '"]' ); if ( ! c ) { continue; }
		if ( 'checkbox' === c.type ) { c.click(); await __sleep( 100 ); exp[ k ] = c.checked ? '1' : ''; n++; continue; }
		if ( 'SELECT' === c.tagName ) { if ( c.options.length < 2 ) { continue; } c.value = c.options[ ( c.selectedIndex + 1 ) % c.options.length ].value; c.dispatchEvent( new Event( 'input', { bubbles: true } ) ); c.dispatchEvent( new Event( 'change', { bubbles: true } ) ); await __sleep( 100 ); exp[ k ] = c.value; n++; continue; }
		var v = 'email' === c.type ? 'test@example.com' : ( 'url' === c.type ? 'https://example.com/x' : ( /numeric|decimal/.test( c.getAttribute( 'inputmode' ) || '' ) || 'number' === c.type ? '5' : 'Test ' + k ) );
		if ( /iban/.test( k ) ) { v = 'DE89370400440532013000'; }
		if ( /bic/.test( k ) ) { v = 'COBADEFFXXX'; }
		if ( /vat|ustid/i.test( k ) ) { v = 'DE123456789'; }
		c.focus(); c.value = v; c.dispatchEvent( new Event( 'input', { bubbles: true } ) ); c.dispatchEvent( new Event( 'change', { bubbles: true } ) ); await __sleep( 80 ); exp[ k ] = v; n++;
	}
	var sv = document.querySelector( '[data-act="s-save"]' ); var saved = false;
	if ( sv ) { sv.click(); await __sleep( 2500 ); saved = true; }
	var toastT = ( document.getElementById( 'vsa-toast' ) || {} ).textContent || '';
	try { localStorage.setItem( 'vxshop_exp_' + sec, JSON.stringify( exp ) ); } catch ( e ) {}
	return { sec: sec, changed: n, saved: saved, toast: toastT.slice( 0, 160 ), bad: bad, errs: __shopErr.slice( 0, 5 ) };
};
window.__shopB = async function ( sec ) {
	__shopErr.length = 0;
	var exp = JSON.parse( localStorage.getItem( 'vxshop_exp_' + sec ) || '{}' ), m = await __open( sec ), diff = [], miss = [];
	for ( var k of Object.keys( exp ) ) {
		var seg = m.querySelector( '[data-seg="' + k + '"].is-on' ), c = m.querySelector( '[data-k="' + k + '"]' ), got;
		if ( seg ) { got = seg.getAttribute( 'data-v' ); } else if ( c ) { got = 'checkbox' === c.type ? ( c.checked ? '1' : '' ) : c.value; } else { miss.push( k ); continue; }
		if ( String( got ) !== String( exp[ k ] ) ) { diff.push( k + ': want ' + exp[ k ] + ' got ' + got ); }
	}
	return { sec: sec, checked: Object.keys( exp ).length, diff: diff, notShown: miss, errs: __shopErr.slice( 0, 5 ), bad: __BAD.test( m.innerText ) ? m.innerText.match( __BAD )[ 0 ] : '' };
};
'ok';
