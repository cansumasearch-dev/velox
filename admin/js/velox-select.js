/**
 * Velox — one dropdown for the whole plugin.
 *
 * Every native <select> in Velox (the builder editor, the builder screens and
 * the Velox admin) opens this list instead of the browser's own: same look,
 * same keyboard, kept inside the window. The <select> itself stays where it is
 * and keeps working, so forms and existing handlers are untouched: picking an
 * item sets its value and fires "change" like a real choice would.
 *
 * Other menus (the builder's class and state pickers) use the same list:
 *   VeloxSelect.open( anchor, items, onPick, opts )
 *   items: [ { value, label, hint, checked, disabled, header, sep, actions:[ { key, icon, title } ] } ]
 *   onPick( value, actionKey )   opts: { filter, filterPlaceholder, onFilterEnter( text ), width, align }
 *
 * Touch screens keep the native picker, which suits them better.
 * A <select data-native> opts out.
 */
( function () {
	'use strict';
	if ( window.VeloxSelect ) {
		return;
	}

	var CSS = [
		'.vxs-pop{position:fixed;z-index:2147483600;min-width:160px;max-width:min(360px,calc(100vw - 16px));display:flex;flex-direction:column;padding:4px;border-radius:10px;font:500 13px/1.35 "Velox Geist",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;letter-spacing:0;text-transform:none;text-align:left;box-sizing:border-box;opacity:0;transform:translateY(-2px);transition:opacity .12s cubic-bezier(.4,0,.2,1),transform .12s cubic-bezier(.4,0,.2,1)}',
		'.vxs-pop.is-up{transform:translateY(2px)}',
		'.vxs-pop.is-in{opacity:1;transform:none}',
		'.vxs-pop *{box-sizing:border-box}',
		'.vxs-list{overflow:auto;max-height:320px;outline:none;scrollbar-width:thin;margin:0;padding:0;list-style:none}',
		'.vxs-i{display:flex;align-items:center;gap:8px;min-height:32px;padding:6px 8px 6px 10px;border-radius:6px;cursor:pointer;user-select:none}',
		'.vxs-i .vxs-l{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}',
		'.vxs-i .vxs-h{flex:none;font-size:12px;font-weight:400}',
		'.vxs-i .vxs-ck{flex:none;width:14px;height:14px;display:grid;place-items:center}',
		'.vxs-i.is-dis{cursor:default;opacity:.45}',
		'.vxs-act{flex:none;display:grid;place-items:center;width:24px;height:24px;margin:-4px -2px;border:0;border-radius:5px;background:transparent;cursor:pointer;padding:0}',
		'.vxs-grp{padding:10px 10px 4px;font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase}',
		'.vxs-sep{height:1px;margin:4px 6px}',
		'.vxs-f{display:flex;align-items:center;gap:6px;height:32px;margin:0 0 4px;padding:0 10px;border-radius:6px}',
		'.vxs-f input{flex:1;min-width:0;height:30px;border:0!important;background:transparent!important;box-shadow:none!important;outline:none!important;font:inherit;padding:0!important;margin:0}',
		'.vxs-empty{padding:10px;font-size:12px}',
		// dark (the builder)
		'.vxs-pop.is-dark{background:#17191f;color:#e8e8ec;border:1px solid rgba(255,255,255,.09);box-shadow:0 16px 40px rgba(0,0,0,.5),0 2px 6px rgba(0,0,0,.3)}',
		'.vxs-pop.is-dark .vxs-i.is-on{background:rgba(255,255,255,.07)}',
		'.vxs-pop.is-dark .vxs-i.is-sel{color:#fff}',
		'.vxs-pop.is-dark .vxs-i.is-sel .vxs-ck{color:#2ab7f1}',
		'.vxs-pop.is-dark .vxs-h,.vxs-pop.is-dark .vxs-grp,.vxs-pop.is-dark .vxs-empty{color:#8b8d96}',
		'.vxs-pop.is-dark .vxs-sep{background:rgba(255,255,255,.08)}',
		'.vxs-pop.is-dark .vxs-act{color:#8b8d96}.vxs-pop.is-dark .vxs-act:hover{background:rgba(255,255,255,.1);color:#fff}',
		'.vxs-pop.is-dark .vxs-f{background:rgba(255,255,255,.05)}.vxs-pop.is-dark .vxs-f input{color:#fff}',
		'.vxs-pop.is-dark .vxs-i.is-danger{color:#f88b80}',
		// light (the Velox admin)
		'.vxs-pop.is-light{background:#fff;color:#1d1d1f;border:1px solid rgba(0,0,0,.08);box-shadow:0 16px 40px rgba(15,23,42,.14),0 2px 6px rgba(15,23,42,.06)}',
		'.vxs-pop.is-light .vxs-i.is-on{background:#f2f4f7}',
		'.vxs-pop.is-light .vxs-i.is-sel .vxs-ck{color:#0e7fb3}',
		'.vxs-pop.is-light .vxs-h,.vxs-pop.is-light .vxs-grp,.vxs-pop.is-light .vxs-empty{color:#6e6e73}',
		'.vxs-pop.is-light .vxs-sep{background:rgba(0,0,0,.07)}',
		'.vxs-pop.is-light .vxs-act{color:#6e6e73}.vxs-pop.is-light .vxs-act:hover{background:#eceef1;color:#1d1d1f}',
		'.vxs-pop.is-light .vxs-f{background:#f2f4f7}.vxs-pop.is-light .vxs-f input{color:#1d1d1f}',
		'.vxs-pop.is-light .vxs-i.is-danger{color:#c4342b}',
		'@media (prefers-reduced-motion:reduce){.vxs-pop{transition:none}}',
	].join( '\n' );
	var CHECK = '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7"/></svg>';
	var SEARCH = '<svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="7" cy="7" r="4.5"/><path d="M10.5 10.5L14 14"/></svg>';

	var pop = null, state = null;

	function css() {
		if ( document.getElementById( 'vxs-css' ) ) {
			return;
		}
		var st = document.createElement( 'style' );
		st.id = 'vxs-css';
		st.textContent = CSS;
		( document.head || document.documentElement ).appendChild( st );
	}
	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"]/g, function ( c ) { return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;' }[ c ]; } );
	}
	/* Dark or light: the colour behind the anchor decides. */
	function themeOf( el ) {
		var n = el;
		while ( n && 1 === n.nodeType ) {
			var forced = n.getAttribute && n.getAttribute( 'data-vxs-theme' );
			if ( forced ) {
				return forced;
			}
			var bg = getComputedStyle( n ).backgroundColor, m = /rgba?\(([\d.]+),\s*([\d.]+),\s*([\d.]+)(?:,\s*([\d.]+))?/.exec( bg || '' );
			if ( m && ( undefined === m[ 4 ] || parseFloat( m[ 4 ] ) > 0.5 ) ) {
				var l = ( 0.2126 * m[ 1 ] + 0.7152 * m[ 2 ] + 0.0722 * m[ 3 ] ) / 255;
				return l < 0.45 ? 'dark' : 'light';
			}
			n = n.parentElement;
		}
		return 'light';
	}

	function close( refocus ) {
		if ( ! pop ) {
			return;
		}
		var a = state && state.anchor;
		if ( a && a.classList ) { a.classList.remove( 'is-vxs-open' ); a.setAttribute( 'aria-expanded', 'false' ); }
		pop.remove();
		pop = null;
		state = null;
		document.removeEventListener( 'mousedown', outside, true );
		window.removeEventListener( 'resize', onWin, true );
		window.removeEventListener( 'scroll', onScroll, true );
		if ( refocus && a && a.focus ) {
			try { a.focus( { preventScroll:true } ); } catch ( e ) {}
		}
	}
	function outside( e ) {
		if ( pop && ! pop.contains( e.target ) && ! ( state && state.anchor.contains( e.target ) ) ) {
			close( false );
		}
	}
	function onWin() { close( false ); }
	function onScroll( e ) {
		if ( pop && ! pop.contains( e.target ) ) {
			close( false );
		}
	}

	function render() {
		var q = state.query.toLowerCase(), html = '', n = 0;
		state.visible = [];
		state.items.forEach( function ( it, i ) {
			if ( it.sep ) {
				if ( ! q ) { html += '<div class="vxs-sep" role="separator"></div>'; }
				return;
			}
			if ( it.header ) {
				if ( ! q ) { html += '<div class="vxs-grp" role="presentation">' + esc( it.header ) + '</div>'; }
				return;
			}
			if ( q && ( it.label + ' ' + ( it.hint || '' ) ).toLowerCase().indexOf( q ) < 0 && ! it.always ) {
				return;
			}
			var acts = ( it.actions || [] ).map( function ( a ) {
				return '<button type="button" class="vxs-act" data-vxs-act="' + esc( a.key ) + '" title="' + esc( a.title || '' ) + '" aria-label="' + esc( a.title || '' ) + '" tabindex="-1">' + a.icon + '</button>';
			} ).join( '' );
			html += '<div class="vxs-i' + ( it.checked ? ' is-sel' : '' ) + ( it.disabled ? ' is-dis' : '' ) + ( it.danger ? ' is-danger' : '' ) + '" role="option" id="vxs-o' + n + '" data-vxs-i="' + i + '" data-vxs-v="' + esc( undefined === it.value ? '' : it.value ) + '" aria-selected="' + ( it.checked ? 'true' : 'false' ) + '"' + ( it.disabled ? ' aria-disabled="true"' : '' ) + '>' +
				'<span class="vxs-ck">' + ( it.checked ? CHECK : ( it.icon || '' ) ) + '</span><span class="vxs-l">' + esc( it.label ) + '</span>' +
				( it.hint ? '<span class="vxs-h">' + esc( it.hint ) + '</span>' : '' ) + acts + '</div>';
			state.visible.push( i );
			n++;
		} );
		if ( ! state.visible.length ) {
			html += '<div class="vxs-empty">' + esc( state.opts.empty || '—' ) + '</div>';
		}
		state.list.innerHTML = html;
		if ( state.cursor >= state.visible.length ) {
			state.cursor = state.visible.length - 1;
		}
		mark();
	}
	function mark() {
		var rows = state.list.querySelectorAll( '.vxs-i' );
		[].forEach.call( rows, function ( r, k ) { r.classList.toggle( 'is-on', k === state.cursor ); } );
		var on = rows[ state.cursor ];
		if ( on ) {
			state.list.setAttribute( 'aria-activedescendant', on.id );
			var top = on.offsetTop, bot = top + on.offsetHeight;
			if ( top < state.list.scrollTop ) { state.list.scrollTop = top; }
			else if ( bot > state.list.scrollTop + state.list.clientHeight ) { state.list.scrollTop = bot - state.list.clientHeight; }
		}
	}
	function pick( vi, act ) {
		var it = state.items[ state.visible[ vi ] ];
		if ( ! it || it.disabled ) {
			return;
		}
		var cb = state.onPick, keepOpen = act && it.keepOpen;
		if ( ! keepOpen ) {
			close( true );
		}
		cb( it.value, act || '' );
	}
	function place() {
		var r = state.anchor.getBoundingClientRect(), vw = window.innerWidth, vh = window.innerHeight;
		var w = Math.max( state.opts.width || 0, r.width, 160 );
		w = Math.min( w, 360, vw - 16 );
		pop.style.width = w + 'px';
		var h = pop.offsetHeight;
		var below = vh - r.bottom - 8, above = r.top - 8, up = below < Math.min( h, 240 ) && above > below;
		var top = up ? Math.max( 8, r.top - 4 - h ) : Math.min( r.bottom + 4, vh - 8 - h );
		var left = 'right' === state.opts.align ? r.right - w : r.left;
		left = Math.max( 8, Math.min( left, vw - 8 - w ) );
		pop.style.top = Math.max( 8, top ) + 'px';
		pop.style.left = left + 'px';
		pop.classList.toggle( 'is-up', up );
		if ( up && top < 8 ) {
			state.list.style.maxHeight = Math.max( 120, r.top - 24 - ( state.filter ? 40 : 0 ) ) + 'px';
		}
	}

	function open( anchor, items, onPick, opts ) {
		// A second click on the same trigger closes its list (a toggle, like every native dropdown).
		if ( pop && state && state.anchor === anchor ) { close( true ); return; }
		close( false );
		css();
		opts = opts || {};
		pop = document.createElement( 'div' );
		pop.className = 'vxs-pop is-' + ( opts.theme || themeOf( anchor ) );
		pop.setAttribute( 'role', 'dialog' );
		var filter = opts.filter ? '<label class="vxs-f">' + SEARCH + '<input type="text" class="vxs-q" autocomplete="off" spellcheck="false" placeholder="' + esc( opts.filterPlaceholder || '' ) + '"></label>' : '';
		pop.innerHTML = filter + '<div class="vxs-list" role="listbox" tabindex="-1"></div>';
		document.body.appendChild( pop );
		if ( anchor && anchor.classList ) { anchor.classList.add( 'is-vxs-open' ); anchor.setAttribute( 'aria-expanded', 'true' ); }
		state = { anchor:anchor, items:items, onPick:onPick, opts:opts, query:'', cursor:0, visible:[], list:pop.querySelector( '.vxs-list' ), filter:pop.querySelector( '.vxs-q' ) };
		var sel = -1;
		render();
		state.visible.forEach( function ( idx, k ) { if ( items[ idx ].checked && sel < 0 ) { sel = k; } } );
		state.cursor = Math.max( 0, sel );
		mark();
		place();
		var shown = pop, show = function () { if ( shown && shown === pop ) { pop.classList.add( 'is-in' ); } };
		requestAnimationFrame( show );
		setTimeout( show, 30 ); // the fade must never stay half-done (background tabs pause animation frames)
		( state.filter || state.list ).focus( { preventScroll:true } );

		pop.addEventListener( 'mousemove', function ( e ) {
			var row = e.target.closest( '.vxs-i' ); if ( ! row ) { return; }
			var k = [].indexOf.call( state.list.querySelectorAll( '.vxs-i' ), row );
			if ( k > -1 && k !== state.cursor ) { state.cursor = k; mark(); }
		} );
		pop.addEventListener( 'mousedown', function ( e ) { if ( ! e.target.closest( 'input' ) ) { e.preventDefault(); } } );
		pop.addEventListener( 'click', function ( e ) {
			var row = e.target.closest( '.vxs-i' ); if ( ! row ) { return; }
			var act = e.target.closest( '[data-vxs-act]' );
			pick( [].indexOf.call( state.list.querySelectorAll( '.vxs-i' ), row ), act ? act.getAttribute( 'data-vxs-act' ) : '' );
		} );
		if ( state.filter ) {
			state.filter.addEventListener( 'input', function () { state.query = state.filter.value.trim(); state.cursor = 0; render(); } );
		}
		pop.addEventListener( 'keydown', function ( e ) {
			var n = state.visible.length;
			if ( 'ArrowDown' === e.key ) { e.preventDefault(); state.cursor = n ? ( state.cursor + 1 ) % n : 0; mark(); }
			else if ( 'ArrowUp' === e.key ) { e.preventDefault(); state.cursor = n ? ( state.cursor - 1 + n ) % n : 0; mark(); }
			else if ( 'Home' === e.key && ! state.filter ) { e.preventDefault(); state.cursor = 0; mark(); }
			else if ( 'End' === e.key && ! state.filter ) { e.preventDefault(); state.cursor = n - 1; mark(); }
			else if ( 'Enter' === e.key || ( ' ' === e.key && ! state.filter ) ) {
				e.preventDefault();
				if ( n ) { pick( state.cursor, '' ); }
				else if ( state.filter && state.opts.onFilterEnter && state.query ) { var t = state.query, f = state.opts.onFilterEnter; close( true ); f( t ); }
			}
			else if ( 'Escape' === e.key ) { e.preventDefault(); e.stopPropagation(); close( true ); }
			else if ( 'Tab' === e.key ) { close( false ); }
			else if ( ! state.filter && 1 === e.key.length && /\S/.test( e.key ) ) {
				// Type to jump.
				state.typed = ( state.typedAt && Date.now() - state.typedAt < 600 ? state.typed : '' ) + e.key.toLowerCase();
				state.typedAt = Date.now();
				for ( var k = 0; k < n; k++ ) {
					if ( String( state.items[ state.visible[ k ] ].label ).toLowerCase().indexOf( state.typed ) === 0 ) { state.cursor = k; mark(); break; }
				}
			}
		} );
		setTimeout( function () {
			document.addEventListener( 'mousedown', outside, true );
			window.addEventListener( 'resize', onWin, true );
			window.addEventListener( 'scroll', onScroll, true );
		}, 0 );
		return pop;
	}

	/* ---------- every native <select> ---------- */
	function eligible( s ) {
		return s && 'SELECT' === s.tagName && ! s.multiple && ! ( s.size > 1 ) && ! s.disabled && ! s.hasAttribute( 'data-native' ) &&
			! ( window.matchMedia && window.matchMedia( '(pointer: coarse)' ).matches );
	}
	function itemsOf( s ) {
		var out = [];
		[].forEach.call( s.children, function ( c ) {
			if ( 'OPTGROUP' === c.tagName ) {
				out.push( { header:c.label } );
				[].forEach.call( c.children, function ( o ) { out.push( optItem( s, o ) ); } );
			} else if ( 'OPTION' === c.tagName ) {
				out.push( optItem( s, c ) );
			}
		} );
		return out;
	}
	function optItem( s, o ) {
		return { value:o.value, label:o.textContent.trim() || o.value || '—', checked:o.selected && o.value === s.value, disabled:o.disabled, hidden:o.hidden };
	}
	function openSelect( s ) {
		if ( pop && state && state.anchor === s ) {
			close( true );
			return;
		}
		var items = itemsOf( s ).filter( function ( it ) { return ! it.hidden; } );
		open( s, items, function ( v ) {
			if ( s.value === v ) {
				return;
			}
			s.value = v;
			s.dispatchEvent( new Event( 'input', { bubbles:true } ) );
			s.dispatchEvent( new Event( 'change', { bubbles:true } ) );
		}, { filter:items.length > 12, filterPlaceholder:'' } );
	}
	document.addEventListener( 'mousedown', function ( e ) {
		var s = e.target && e.target.closest ? e.target.closest( 'select' ) : null;
		if ( 0 !== e.button || ! eligible( s ) ) {
			return;
		}
		e.preventDefault();
		try { s.focus( { preventScroll:true } ); } catch ( err ) {}
		openSelect( s );
	}, true );
	document.addEventListener( 'keydown', function ( e ) {
		var s = e.target;
		if ( ! eligible( s ) || pop ) {
			return;
		}
		if ( ( e.altKey && ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) ) || 'F4' === e.key || ' ' === e.key || 'Enter' === e.key ) {
			e.preventDefault();
			openSelect( s );
		}
	}, true );

	window.VeloxSelect = { open:open, close:close, theme:themeOf };
}() );
