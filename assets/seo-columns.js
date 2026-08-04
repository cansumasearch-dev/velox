/* Velox SEO columns — click-the-cell inline editing + Quick Edit populate. */
( function () {
	'use strict';

	var D = window.VELOX_SEOCOL;
	if ( ! D ) { return; }
	var t = D.i18n || {};

	/* ---------------- click-the-cell inline editing ---------------- */

	document.addEventListener( 'click', function ( e ) {
		var cell = e.target.closest( '.velox-seocol' );
		if ( ! cell || cell.dataset.editing === '1' ) { return; }
		openEditor( cell );
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Enter' !== e.key && ' ' !== e.key ) { return; }
		var cell = document.activeElement;
		if ( cell && cell.classList && cell.classList.contains( 'velox-seocol' ) && cell.dataset.editing !== '1' ) {
			e.preventDefault();
			openEditor( cell );
		}
	} );

	function openEditor( cell ) {
		cell.dataset.editing = '1';
		var field = cell.getAttribute( 'data-key' );  // 'title' | 'desc'
		var post  = cell.getAttribute( 'data-post' );
		var cur   = cell.getAttribute( 'data-value' ) || '';

		var input = document.createElement( field === 'desc' ? 'textarea' : 'input' );
		input.className = 'velox-seocol-edit';
		if ( field === 'desc' ) { input.rows = 3; } else { input.type = 'text'; }
		input.value = cur;

		var hint = document.createElement( 'span' );
		hint.className = 'velox-seocol-hint';
		hint.textContent = t.hint || 'Enter to save · Esc to cancel';

		var prevHTML = cell.innerHTML;
		cell.innerHTML = '';
		cell.appendChild( input );
		if ( field !== 'desc' ) { cell.appendChild( hint ); }
		input.focus();
		input.setSelectionRange( input.value.length, input.value.length );

		var done = false;
		function restore( value ) {
			cell.dataset.editing = '';
			cell.setAttribute( 'data-value', value );
			var empty = ! value.trim();
			cell.classList.toggle( 'is-empty', empty );
			cell.textContent = empty ? ( t.add || '— add —' ) : value;
		}
		function cancel() {
			if ( done ) { return; }
			done = true;
			cell.dataset.editing = '';
			cell.innerHTML = prevHTML;
		}
		function save() {
			if ( done ) { return; }
			done = true;
			var value = input.value;
			cell.classList.add( 'is-saving' );
			var body = new URLSearchParams();
			body.set( 'action', 'velox' );
			body.set( 'do', 'seocol_save' );
			body.set( 'nonce', D.nonce );
			body.set( 'post', post );
			body.set( 'field', field );
			body.set( 'value', value );
			fetch( D.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( j ) {
					cell.classList.remove( 'is-saving' );
					if ( j && j.success ) {
						restore( j.data && typeof j.data.value === 'string' ? j.data.value : value );
						flash( cell, t.saved || 'Saved', false );
					} else {
						restore( cur );
						flash( cell, ( j && j.data && j.data.message ) || t.failed || 'Save failed', true );
					}
				} )
				.catch( function () {
					cell.classList.remove( 'is-saving' );
					restore( cur );
					flash( cell, t.failed || 'Save failed', true );
				} );
		}

		input.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) { e.preventDefault(); cancel(); }
			// Enter saves for the title input; in the textarea use Ctrl/Cmd+Enter.
			if ( 'Enter' === e.key && ( field !== 'desc' || e.metaKey || e.ctrlKey ) ) {
				e.preventDefault();
				save();
			}
		} );
		input.addEventListener( 'blur', function () { if ( ! done ) { save(); } } );
	}

	function flash( cell, msg, isError ) {
		var f = document.createElement( 'span' );
		f.className = 'velox-seocol-flash' + ( isError ? ' is-error' : '' );
		f.textContent = msg;
		cell.parentNode.appendChild( f );
		requestAnimationFrame( function () { f.classList.add( 'is-show' ); } );
		setTimeout( function () { f.classList.remove( 'is-show' ); setTimeout( function () { f.remove(); }, 250 ); }, 1400 );
	}

	/* ---------------- populate WordPress Quick Edit ----------------
	   WordPress doesn't fill custom Quick Edit fields, so we copy the row's
	   values into the Quick Edit inputs when it opens. */
	var origInlineEdit = window.inlineEditPost ? window.inlineEditPost.edit : null;
	if ( origInlineEdit ) {
		window.inlineEditPost.edit = function ( id ) {
			origInlineEdit.apply( this, arguments );
			var postId = typeof id === 'object' ? this.getId( id ) : id;
			if ( ! postId ) { return; }
			var row = document.getElementById( 'post-' + postId );
			var editRow = document.getElementById( 'edit-' + postId );
			if ( ! row || ! editRow ) { return; }
			var tCell = row.querySelector( '.velox-seocol[data-key="title"]' );
			var dCell = row.querySelector( '.velox-seocol[data-key="desc"]' );
			var tIn = editRow.querySelector( '.velox-qe-title' );
			var dIn = editRow.querySelector( '.velox-qe-desc' );
			if ( tCell && tIn ) { tIn.value = tCell.getAttribute( 'data-value' ) || ''; }
			if ( dCell && dIn ) { dIn.value = dCell.getAttribute( 'data-value' ) || ''; }
		};
	}
} )();

/* ---------------- Index & Links clickable toggles ---------------- */
( function () {
	var D = window.VELOX_SEOCOL;
	if ( ! D ) { return; }
	var t = D.i18n || {};

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.velox-seo-toggle' );
		if ( ! btn ) { return; }
		var wrap = btn.closest( '.velox-seo-idx' );
		if ( ! wrap ) { return; }
		var post = wrap.getAttribute( 'data-post' );
		var flag = btn.getAttribute( 'data-flag' );        // noindex | nofollow
		var target = btn.getAttribute( 'data-on' ) === '1' ? 1 : 0; // 1 = set the flag ON (noindex/nofollow)

		btn.disabled = true;
		var body = new URLSearchParams();
		body.set( 'action', 'velox' );
		body.set( 'do', 'seocol_flag' );
		body.set( 'nonce', D.nonce );
		body.set( 'post', post );
		body.set( 'flag', flag );
		body.set( 'on', target );
		fetch( D.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( j ) {
				if ( j && j.success ) {
					var nowOn = target === 1; // flag is now active (noindex/nofollow)
					if ( flag === 'noindex' ) {
						btn.textContent = nowOn ? ( t.noindex || 'Noindex' ) : ( t.index || 'Index' );
					} else {
						btn.textContent = nowOn ? ( t.nofollow || 'Nofollow' ) : ( t.follow || 'Follow' );
					}
					btn.classList.toggle( 'is-off', nowOn );
					btn.classList.toggle( 'is-on', ! nowOn );
					// flip the data-on so the next click toggles back
					btn.setAttribute( 'data-on', nowOn ? '0' : '1' );
				}
			} )
			.catch( function () {} )
			.then( function () { btn.disabled = false; } );
	} );
} )();
