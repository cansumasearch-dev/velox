/**
 * Resize controls inside WordPress's own Attachment details panel.
 *
 * The panel is re-rendered by wp.media whenever you switch image, so everything
 * here is delegated from the document rather than bound to the markup.
 */
( function () {
	'use strict';

	function post( action, data ) {
		var body = new FormData();
		body.append( 'action', 'velox' );
		body.append( 'nonce', VELOX_RZ.nonce );
		body.append( 'do', action );
		Object.keys( data ).forEach( function ( k ) { body.append( k, data[ k ] ); } );
		return fetch( VELOX_RZ.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					throw new Error( ( json && json.data && json.data.message ) || 'Something went wrong.' );
				}
				return json.data;
			} );
	}

	function box( el ) { return el.closest( '.velox-rz' ); }
	function ratioOf( wrap ) {
		var w = parseInt( wrap.getAttribute( 'data-w' ), 10 );
		var h = parseInt( wrap.getAttribute( 'data-h' ), 10 );
		return ( w > 0 && h > 0 ) ? ( h / w ) : 1;
	}
	function say( wrap, text, kind ) {
		var m = wrap.querySelector( '.velox-rz-msg' );
		if ( ! m ) { return; }
		m.textContent = text || '';
		m.className = 'velox-rz-msg' + ( kind ? ' ' + kind : '' );
	}

	document.addEventListener( 'input', function ( e ) {
		var isW = e.target.classList && e.target.classList.contains( 'velox-rz-w' );
		var isH = e.target.classList && e.target.classList.contains( 'velox-rz-h' );
		if ( ! isW && ! isH ) { return; }
		var wrap = box( e.target );
		if ( ! wrap ) { return; }
		var lock = wrap.querySelector( '.velox-rz-lock' );
		if ( ! lock || ! lock.classList.contains( 'is-on' ) ) { return; }
		var ratio = ratioOf( wrap );
		var v = parseInt( e.target.value, 10 );
		if ( ! ( v > 0 ) ) { return; }
		if ( isW ) {
			wrap.querySelector( '.velox-rz-h' ).value = Math.max( 1, Math.round( v * ratio ) );
		} else {
			wrap.querySelector( '.velox-rz-w' ).value = Math.max( 1, Math.round( v / ratio ) );
		}
	} );

	document.addEventListener( 'click', function ( e ) {
		var t = e.target;

		var lock = t.closest ? t.closest( '.velox-rz-lock' ) : null;
		if ( lock ) {
			e.preventDefault();
			var on = ! lock.classList.contains( 'is-on' );
			lock.classList.toggle( 'is-on', on );
			lock.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			return;
		}

		var preset = t.closest ? t.closest( '.velox-rz-presets button' ) : null;
		if ( preset ) {
			e.preventDefault();
			var pw = box( preset );
			var sc = parseFloat( preset.getAttribute( 'data-scale' ) ) || 1;
			var ow = parseInt( pw.getAttribute( 'data-w' ), 10 );
			var oh = parseInt( pw.getAttribute( 'data-h' ), 10 );
			pw.querySelector( '.velox-rz-w' ).value = Math.max( 1, Math.round( ow * sc ) );
			pw.querySelector( '.velox-rz-h' ).value = Math.max( 1, Math.round( oh * sc ) );
			return;
		}

		var go = t.closest ? t.closest( '.velox-rz-go' ) : null;
		if ( ! go ) { return; }
		e.preventDefault();
		var wrap = box( go );
		var w = parseInt( wrap.querySelector( '.velox-rz-w' ).value, 10 );
		var h = parseInt( wrap.querySelector( '.velox-rz-h' ).value, 10 );
		if ( ! ( w > 0 && h > 0 ) ) { say( wrap, 'Enter a width and height.', 'err' ); return; }
		go.disabled = true;
		say( wrap, 'Resizing…' );
		post( 'media_resize', { id: wrap.getAttribute( 'data-id' ), w: w, h: h } )
			.then( function ( r ) {
				wrap.setAttribute( 'data-w', r.width );
				wrap.setAttribute( 'data-h', r.height );
				say( wrap, r.unchanged ? 'Already that size.' : 'Done — ' + r.width + ' × ' + r.height + '.', 'ok' );
				// Refresh the thumbnail wp.media is showing, cache-busted.
				var img = document.querySelector( '.attachment-details .thumbnail img, .wp_attachment_image img' );
				if ( img && r.thumb ) { img.src = r.thumb + ( r.thumb.indexOf( '?' ) === -1 ? '?' : '&' ) + 'v=' + Date.now(); }
			} )
			.catch( function ( err ) { say( wrap, err.message, 'err' ); } )
			.then( function () { go.disabled = false; } );
	} );
}() );
