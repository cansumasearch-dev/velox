/* global wp */
( function () {
	'use strict';

	var V = window.VELOX || {};

	function $( sel, root ) { return ( root || document ).querySelector( sel ); }

	function api( doAction, data ) {
		var fd = new FormData();
		fd.append( 'action', 'velox' );
		fd.append( 'do', doAction );
		fd.append( 'nonce', V.nonce );
		Object.keys( data || {} ).forEach( function ( k ) { fd.append( k, data[ k ] ); } );
		return fetch( V.ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( j ) {
				if ( ! j || ! j.success ) {
					throw new Error( ( j && j.data && j.data.message ) || 'Request failed.' );
				}
				return j.data;
			} );
	}

	var toastTimer;
	function toast( msg, type ) {
		var el = $( '#velox-toast' );
		if ( ! el ) { return; }
		var t = type || 'success';
		var icons = { success: '\u2713', error: '\u2715', warn: '!', info: '\u2139' };
		el.innerHTML = '<span class="velox-toast-ic"></span><span class="velox-toast-msg"></span>';
		el.querySelector( '.velox-toast-ic' ).textContent = icons[ t ] || icons.success;
		el.querySelector( '.velox-toast-msg' ).textContent = msg;
		el.className = 'velox-toast is-visible velox-toast--' + t;
		clearTimeout( toastTimer );
		toastTimer = setTimeout( function () { el.className = 'velox-toast'; }, 3200 );
	}

	function modeFor( type ) {
		switch ( type ) {
			case 'css':  return 'text/css';
			case 'js':   return 'text/javascript';
			case 'html': return 'htmlmixed';
			default:     return 'application/x-httpd-php';
		}
	}
	function hintFor( type ) {
		switch ( type ) {
			case 'css':  return 'Injected into the page <head> for the chosen location.';
			case 'js':   return 'Printed before </body> for the chosen location.';
			case 'html': return 'Printed in the footer, and available as [velox_snippet id="…"].';
			default:     return 'Runs early (you can add_action / add_filter). No opening <?php tag needed. If it errors, it auto-disables.';
		}
	}

	/* ---------------- Editor ---------------- */

	function initEditor( editor ) {
		var typeEl  = $( '#velox-snip-type' );
		var scopeEl = $( '#velox-snip-scope' );
		var prioEl  = $( '#velox-snip-prio' );
		var nameEl  = $( '#velox-snip-name' );
		var descEl  = $( '#velox-snip-desc' );
		var codeEl  = $( '#velox-snip-code' );
		var hintEl  = $( '#velox-snip-codehint' );
		var saveAct = $( '#velox-snip-save-activate' );
		var saveOnly = $( '#velox-snip-save-only' );

		var cm = null;
		if ( window.wp && wp.codeEditor && wp.codeEditor.initialize ) {
			var inited = wp.codeEditor.initialize( codeEl, {
				codemirror: { mode: modeFor( typeEl.value ), lineNumbers: true, indentUnit: 4, tabSize: 4, indentWithTabs: true, lineWrapping: false }
			} );
			cm = inited && inited.codemirror ? inited.codemirror : null;
		}
		function getCode() { return cm ? cm.getValue() : codeEl.value; }

		if ( hintEl ) { hintEl.textContent = hintFor( typeEl.value ); }

		typeEl.addEventListener( 'change', function () {
			if ( cm ) { cm.setOption( 'mode', modeFor( typeEl.value ) ); }
			if ( hintEl ) { hintEl.textContent = hintFor( typeEl.value ); }
		} );

		function relabel() {
			saveAct.textContent = editor.getAttribute( 'data-active' ) === '1' ? 'Save and Deactivate' : 'Save and Activate';
		}

		function gather( activeVal ) {
			return {
				id:          editor.getAttribute( 'data-id' ) || 0,
				name:        nameEl.value,
				description: descEl.value,
				type:        typeEl.value,
				code:        getCode(),
				scope:       scopeEl.value,
				priority:    prioEl.value || 10,
				active:      activeVal
			};
		}

		function doSave( activeVal, btn ) {
			var wasNew = ! ( editor.getAttribute( 'data-id' ) > 0 );
			btn.disabled = true;
			api( 'snippet_save', gather( activeVal ) )
				.then( function ( r ) {
					if ( ! r.ok ) { toast( r.message || 'Could not save.', 'error' ); return; }
					editor.setAttribute( 'data-id', r.id );
					editor.setAttribute( 'data-active', activeVal ? '1' : '0' );
					relabel();
					if ( wasNew && window.history && history.replaceState ) {
						history.replaceState( {}, '', 'admin.php?page=velox-snippets&action=edit&id=' + r.id );
					}
					toast( activeVal ? 'Saved & active.' : 'Saved.' );
				} )
				.catch( function ( e ) { toast( e.message, 'error' ); } )
				.then( function () { btn.disabled = false; } );
		}

		saveOnly.addEventListener( 'click', function () {
			// Save without changing whether it's on or off.
			doSave( editor.getAttribute( 'data-active' ) === '1' ? 1 : 0, saveOnly );
		} );
		saveAct.addEventListener( 'click', function () {
			// Flip the active state.
			doSave( editor.getAttribute( 'data-active' ) === '1' ? 0 : 1, saveAct );
		} );
	}

	/* ---------------- List ---------------- */

	function initList( list ) {
		var addBtn = $( '#velox-snip-add-btn' );
		var addBtn2 = $( '#velox-snip-add-btn-2' );
		var modal  = $( '#velox-snip-modal' );
		if ( modal ) {
			function openModal() { modal.hidden = false; }
			function closeModal() { modal.hidden = true; }
			if ( addBtn )  { addBtn.addEventListener( 'click', openModal ); }
			if ( addBtn2 ) { addBtn2.addEventListener( 'click', openModal ); }
			modal.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( '[data-close]' ) ) { closeModal(); }
			} );
			document.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' && ! modal.hidden ) { closeModal(); }
			} );
		}

		// Row actions menu (⋯) — open one popover at a time, close on outside click.
		list.addEventListener( 'click', function ( e ) {
			var trigger = e.target.closest( '.velox-snip-menu-btn' );
			// Close any open menus first.
			var open = list.querySelector( '.velox-snip-menu-pop:not([hidden])' );
			if ( open && ( ! trigger || open.previousElementSibling !== trigger ) ) {
				open.hidden = true;
			}
			if ( trigger ) {
				e.stopPropagation();
				var pop = trigger.nextElementSibling;
				if ( pop ) { pop.hidden = ! pop.hidden; }
			}
		} );
		document.addEventListener( 'click', function ( e ) {
			if ( ! e.target.closest( '.velox-snip-menu' ) ) {
				var open = list.querySelector( '.velox-snip-menu-pop:not([hidden])' );
				if ( open ) { open.hidden = true; }
			}
		} );

		// Safe Mode rescue buttons (only present when Safe Mode is showing).
		var clearPanic = $( '#velox-snip-clear-panic' );
		if ( clearPanic ) {
			clearPanic.addEventListener( 'click', function () {
				clearPanic.disabled = true;
				api( 'snippet_clear_panic', {} )
					.then( function () { toast( 'Safe Mode cleared.' ); setTimeout( function () { location.reload(); }, 500 ); } )
					.catch( function ( e ) { toast( e.message, 'error' ); clearPanic.disabled = false; } );
			} );
		}
		var disableAll = $( '#velox-snip-disable-all' );
		if ( disableAll ) {
			disableAll.addEventListener( 'click', function () {
				if ( ! window.confirm( 'Switch off every PHP snippet? Your CSS/JS/HTML snippets stay as they are.' ) ) { return; }
				disableAll.disabled = true;
				api( 'snippet_disable_all', {} )
					.then( function () { toast( 'All PHP snippets switched off.' ); setTimeout( function () { location.reload(); }, 500 ); } )
					.catch( function ( e ) { toast( e.message, 'error' ); disableAll.disabled = false; } );
			} );
		}

		function reload() { setTimeout( function () { location.reload(); }, 400 ); }

		list.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( 'button' );
			if ( ! btn ) { return; }
			var row = e.target.closest( '.velox-snip-row' );
			if ( ! row ) { return; }
			var id = row.getAttribute( 'data-id' );

			if ( btn.classList.contains( 'velox-snip-toggle' ) ) {
				var turnOn = row.getAttribute( 'data-active' ) !== '1';
				btn.disabled = true;
				api( turnOn ? 'snippet_activate' : 'snippet_deactivate', { id: id } )
					.then( function ( r ) {
						if ( ! r.ok ) { toast( r.message || 'Could not change.', 'error' ); btn.disabled = false; return; }
						toast( turnOn ? 'Activated.' : 'Deactivated.' );
						reload();
					} )
					.catch( function ( er ) { toast( er.message, 'error' ); btn.disabled = false; } );
			} else if ( btn.classList.contains( 'velox-snip-clone' ) ) {
				api( 'snippet_duplicate', { id: id } )
					.then( function ( r ) { if ( ! r.ok ) { toast( r.message || 'Could not clone.', 'error' ); return; } toast( 'Cloned.' ); reload(); } )
					.catch( function ( er ) { toast( er.message, 'error' ); } );
			} else if ( btn.classList.contains( 'velox-snip-trash' ) ) {
				api( 'snippet_trash', { id: id } )
					.then( function () { toast( 'Moved to trash.' ); reload(); } )
					.catch( function ( er ) { toast( er.message, 'error' ); } );
			} else if ( btn.classList.contains( 'velox-snip-restore' ) ) {
				api( 'snippet_restore', { id: id } )
					.then( function () { toast( 'Restored.' ); reload(); } )
					.catch( function ( er ) { toast( er.message, 'error' ); } );
			} else if ( btn.classList.contains( 'velox-snip-delete' ) ) {
				if ( ! window.confirm( 'Delete this snippet forever? This cannot be undone.' ) ) { return; }
				api( 'snippet_delete', { id: id } )
					.then( function () { row.remove(); toast( 'Removed.' ); } )
					.catch( function ( er ) { toast( er.message, 'error' ); } );
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var editor = $( '#velox-snip-editor' );
		if ( editor ) { initEditor( editor ); }
		var list = $( '#velox-snip-list' );
		if ( list ) { initList( list ); }
	} );
} )();
