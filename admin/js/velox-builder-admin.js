/**
 * Velox Builder — admin section script (foundation).
 *
 * The admin section pages (Overview, Templates, …) are mostly server-rendered.
 * This bundle wires the small bits of interactivity they need. It self-boots
 * and bails safely if its config global isn't present.
 */

/* ------------------------------------------------------------------
   window.vbaDialog — in-app confirm / prompt / alert.

   Replaces the browser's native dialogs on the builder screens with one
   shared modal built from the same .vba-modal markup as the template and
   class editors. Callback based:

     vbaDialog.confirm( { title, text, ok, cancel, danger }, onYes, onNo )
     vbaDialog.prompt( { title, text, value, placeholder, ok, cancel, danger }, onValue, onNo )
     vbaDialog.alert( { title, text, ok }, onClose )

   onYes / onValue run only when confirmed (onValue gets the trimmed value and
   only when it is not empty); onNo runs on Cancel, Escape, × or a backdrop
   click. Every string is set with textContent — titles come from user data.

   Defined before, and independent of, the VELOX_BUILDER check below: the view
   scripts need it even where that config is missing.
   ------------------------------------------------------------------ */
( function () {
	'use strict';

	if ( window.vbaDialog ) {
		return;
	}

	var root = null;
	var el = {};
	var state = null;

	function make( tag, cls, attrs ) {
		var n = document.createElement( tag );
		if ( cls ) { n.className = cls; }
		if ( attrs ) {
			Object.keys( attrs ).forEach( function ( k ) { n.setAttribute( k, attrs[ k ] ); } );
		}
		return n;
	}

	// Built lazily, once, the first time a dialog opens.
	function build() {
		if ( root ) {
			return;
		}
		root = make( 'div', 'vba-modal vba-dialog' );
		root.hidden = true;

		var back = make( 'div', 'vba-modal-back', { 'data-dlg-cancel': '' } );
		var box = make( 'div', 'vba-modal-box', { role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'vba-dlg-title', 'aria-describedby': 'vba-dlg-text' } );

		var head = make( 'div', 'vba-modal-h' );
		el.title = make( 'b', '', { id: 'vba-dlg-title' } );
		el.x = make( 'button', 'vba-modal-x', { type: 'button', 'data-dlg-cancel': '' } );
		el.x.textContent = '×';
		head.appendChild( el.title );
		head.appendChild( el.x );

		var body = make( 'div', 'vba-modal-b' );
		el.text = make( 'p', 'vba-dialog-text', { id: 'vba-dlg-text' } );
		el.input = make( 'input', 'vba-input', { type: 'text', spellcheck: 'false', autocomplete: 'off', 'aria-labelledby': 'vba-dlg-title vba-dlg-text' } );
		body.appendChild( el.text );
		body.appendChild( el.input );

		var foot = make( 'div', 'vba-modal-f' );
		el.cancel = make( 'button', 'vba-btn vba-btn-ghost', { type: 'button', 'data-dlg-cancel': '' } );
		el.ok = make( 'button', 'vba-btn vba-btn-primary', { type: 'button', 'data-dlg-ok': '' } );
		foot.appendChild( el.cancel );
		foot.appendChild( el.ok );

		box.appendChild( head );
		box.appendChild( body );
		box.appendChild( foot );
		root.appendChild( back );
		root.appendChild( box );
		document.body.appendChild( root );

		root.addEventListener( 'click', function ( e ) {
			if ( ! state ) { return; }
			if ( e.target.closest( '[data-dlg-ok]' ) ) { finish( true ); return; }
			if ( e.target.closest( '[data-dlg-cancel]' ) ) { finish( false ); }
		} );
		root.addEventListener( 'keydown', function ( e ) {
			if ( ! state ) { return; }
			// Keep the key inside the dialog so a screen's own Escape handler
			// does not also act on it.
			e.stopPropagation();
			if ( 'Escape' === e.key || 'Esc' === e.key ) {
				e.preventDefault();
				finish( false );
				return;
			}
			if ( 'Enter' === e.key && e.target === el.input ) {
				e.preventDefault();
				finish( true );
				return;
			}
			if ( 'Tab' === e.key ) {
				trapTab( e );
			}
		} );
	}

	// Tab and Shift+Tab cycle through the dialog's own controls only.
	function trapTab( e ) {
		var list = [ el.x, el.input, el.cancel, el.ok ].filter( function ( n ) { return ! n.hidden; } );
		var i = list.indexOf( document.activeElement );
		var next = e.shiftKey ? ( i <= 0 ? list.length - 1 : i - 1 ) : ( i < 0 || i >= list.length - 1 ? 0 : i + 1 );
		e.preventDefault();
		list[ next ].focus();
	}

	function open( kind, o, onYes, onNo ) {
		o = o || {};
		build();
		if ( state ) {
			// A dialog that is already open counts as cancelled.
			finish( false );
		}
		var cancelLabel = o.cancel || 'Cancel';
		el.title.textContent = o.title || '';
		el.text.textContent = o.text || '';
		el.text.hidden = ! o.text;
		el.input.hidden = 'prompt' !== kind;
		el.input.value = 'prompt' === kind && null != o.value ? String( o.value ) : '';
		el.input.placeholder = 'prompt' === kind && o.placeholder ? String( o.placeholder ) : '';
		el.cancel.hidden = 'alert' === kind;
		el.cancel.textContent = cancelLabel;
		el.x.setAttribute( 'aria-label', 'alert' === kind ? ( o.ok || 'OK' ) : cancelLabel );
		el.ok.textContent = o.ok || 'OK';
		el.ok.className = 'vba-btn ' + ( o.danger ? 'vba-btn-danger' : 'vba-btn-primary' );

		state = { kind: kind, onYes: onYes, onNo: onNo, back: document.activeElement };
		root.hidden = false;
		if ( 'prompt' === kind ) {
			el.input.focus();
			el.input.select();
		} else {
			el.ok.focus();
		}
	}

	function finish( yes ) {
		var s = state;
		if ( ! s ) { return; }
		var value = el.input.value.trim();
		state = null;
		root.hidden = true;
		// Focus goes back where it was before the callback runs, so a dialog
		// opened from the callback remembers the right element in turn.
		if ( s.back && s.back.focus && document.body.contains( s.back ) ) {
			try { s.back.focus(); } catch ( err ) { /* element no longer focusable */ }
		}
		if ( ! yes ) {
			if ( 'function' === typeof s.onNo ) { s.onNo(); }
			return;
		}
		if ( 'function' !== typeof s.onYes ) { return; }
		if ( 'prompt' === s.kind ) {
			if ( value ) { s.onYes( value ); }
			return;
		}
		s.onYes();
	}

	window.vbaDialog = {
		confirm: function ( o, onYes, onNo ) { open( 'confirm', o, onYes, onNo ); },
		prompt: function ( o, onValue, onNo ) { open( 'prompt', o, onValue, onNo ); },
		alert: function ( o, onClose ) { open( 'alert', o, onClose, onClose ); }
	};
}() );

( function () {
	'use strict';

	var CFG = window.VELOX_BUILDER || null;
	if ( ! CFG ) {
		return;
	}

	function boot() {
		var root = document.getElementById( 'velox-builder-admin' );
		if ( ! root ) {
			return;
		}
		moreMenus( root );
	}

	/* Row actions: Edit / View / Publish stay visible, the rest moves into a "⋯"
	 * menu (Linear / Vercel). Rows had up to seven buttons and ran off the card.
	 * The buttons are moved, not copied, so every existing click handler (they
	 * are delegated on document) keeps working, including hidden ones such as
	 * "Make default" on the default template. */
	var DOTS = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M3 9.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3m5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3m5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3"/></svg>';
	var openMenu = null;
	function keep( el ) {
		if ( el.classList.contains( 'vba-mini-go' ) ) { return true; }            // Publish
		if ( 'A' === el.tagName && '_blank' === el.getAttribute( 'target' ) ) { return true; } // View
		if ( 'A' === el.tagName && el === el.parentNode.querySelector( 'a.vba-mini' ) ) { return true; } // Edit / Build
		return false;
	}
	function moreMenus( root ) {
		var label = ( CFG.i18n && CFG.i18n.more ) || 'More actions';
		[].slice.call( root.querySelectorAll( '.vba-doc-actions' ) ).forEach( function ( box, n ) {
			var rest = [].slice.call( box.children ).filter( function ( el ) { return ! keep( el ); } );
			if ( ! rest.length ) { return; }
			var wrap = document.createElement( 'span' ); wrap.className = 'vba-more';
			var btn = document.createElement( 'button' );
			btn.type = 'button'; btn.className = 'vba-mini vba-more-btn'; btn.innerHTML = DOTS;
			btn.setAttribute( 'aria-haspopup', 'menu' ); btn.setAttribute( 'aria-expanded', 'false' ); btn.setAttribute( 'aria-label', label ); btn.title = label;
			var menu = document.createElement( 'div' ); menu.className = 'vba-more-menu'; menu.setAttribute( 'role', 'menu' ); menu.id = 'vba-more-' + n;
			btn.setAttribute( 'aria-controls', menu.id );
			rest.forEach( function ( el ) { el.classList.add( 'vba-more-i' ); el.setAttribute( 'role', 'menuitem' ); menu.appendChild( el ); } );
			wrap.appendChild( btn ); wrap.appendChild( menu ); box.appendChild( wrap );
		} );
	}
	function closeMenu() {
		if ( ! openMenu ) { return; }
		openMenu.menu.classList.remove( 'open' ); openMenu.btn.setAttribute( 'aria-expanded', 'false' );
		openMenu = null;
	}
	function place( btn, menu ) {
		// Fixed, so a card's overflow never clips it; flips up near the bottom.
		var r = btn.getBoundingClientRect();
		menu.style.right = Math.max( 8, window.innerWidth - r.right ) + 'px';
		menu.style.left = 'auto';
		var h = menu.offsetHeight || 220;
		menu.style.top = ( r.bottom + 6 + h > window.innerHeight ? Math.max( 8, r.top - 6 - h ) : r.bottom + 6 ) + 'px';
	}
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest && e.target.closest( '.vba-more-btn' );
		if ( btn ) {
			var menu = btn.nextElementSibling, was = openMenu && openMenu.menu === menu;
			closeMenu();
			if ( ! was ) {
				menu.classList.add( 'open' ); place( btn, menu ); btn.setAttribute( 'aria-expanded', 'true' );
				openMenu = { btn:btn, menu:menu };
				var first = menu.querySelector( '.vba-more-i:not([hidden])' ); if ( first ) { first.focus(); }
			}
			return;
		}
		// A choice inside the menu runs its own handler; then the menu closes.
		if ( openMenu && ! ( e.target.closest && e.target.closest( '.vba-more-menu' ) ) ) { closeMenu(); }
		else if ( openMenu && e.target.closest( '.vba-more-i' ) ) { setTimeout( closeMenu, 0 ); }
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( ! openMenu ) { return; }
		var items = [].slice.call( openMenu.menu.querySelectorAll( '.vba-more-i:not([hidden])' ) ), i = items.indexOf( document.activeElement );
		if ( 'Escape' === e.key ) { var b = openMenu.btn; closeMenu(); b.focus(); e.preventDefault(); }
		else if ( 'ArrowDown' === e.key ) { e.preventDefault(); ( items[ i + 1 ] || items[ 0 ] ).focus(); }
		else if ( 'ArrowUp' === e.key ) { e.preventDefault(); ( items[ i - 1 ] || items[ items.length - 1 ] ).focus(); }
	} );
	window.addEventListener( 'scroll', closeMenu, true );
	window.addEventListener( 'resize', closeMenu );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
