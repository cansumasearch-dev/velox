/**
 * Velox element runtime.
 *
 * One delegated script for every interactive element. Loaded only when a page
 * actually contains one (see Velox_Builder_Render::print_element_runtime).
 *
 * Rules this file follows:
 *  - Progressive enhancement: markup works without JS, the script upgrades it.
 *  - Delegation over per-element listeners, so repeaters and AJAX content work.
 *  - ARIA state is owned here, so behaviour and semantics can never disagree.
 *  - Respects prefers-reduced-motion for anything that animates.
 */
( function () {
	'use strict';

	var REDUCED = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var d = document;

	function on( type, sel, fn ) {
		d.addEventListener( type, function ( e ) {
			var t = e.target.closest ? e.target.closest( sel ) : null;
			if ( t ) { fn( e, t ); }
		}, false );
	}
	function num( el, attr, fallback ) {
		var v = parseInt( el.getAttribute( attr ), 10 );
		return isNaN( v ) ? fallback : v;
	}

	/* ------------------------------------------------------------------ *
	 * Disclosure / accordion / FAQ
	 * W3C APG Accordion pattern: heading > button[aria-expanded][aria-controls]
	 * and a panel region. Height is animated from a measured value because
	 * `auto` cannot be transitioned.
	 * ------------------------------------------------------------------ */
	function setPanel( panel, open, ms ) {
		if ( REDUCED || ! ms ) {
			panel.hidden = ! open;
			panel.style.height = '';
			return;
		}
		if ( open ) {
			panel.hidden = false;
			var h = panel.scrollHeight;
			panel.style.height = '0px';
			panel.offsetHeight; // force a reflow so the transition has a start value
			panel.style.transition = 'height ' + ms + 'ms ease';
			panel.style.height = h + 'px';
			window.setTimeout( function () {
				panel.style.transition = '';
				panel.style.height = '';
			}, ms );
		} else {
			panel.style.height = panel.scrollHeight + 'px';
			panel.offsetHeight;
			panel.style.transition = 'height ' + ms + 'ms ease';
			panel.style.height = '0px';
			window.setTimeout( function () {
				panel.hidden = true;
				panel.style.transition = '';
				panel.style.height = '';
			}, ms );
		}
	}

	function toggleAccordionItem( root, item, force ) {
		var btn = item.querySelector( '.vx-acc-btn' );
		var panel = item.querySelector( '.vx-acc-p' );
		if ( ! btn || ! panel ) { return; }
		var isOpen = 'true' === btn.getAttribute( 'aria-expanded' );
		var next = ( force === undefined ) ? ! isOpen : force;
		var ms = num( root, 'data-vx-speed', 220 );

		// "One at a time" closes the others first.
		if ( next && 'multi' !== root.getAttribute( 'data-vx-mode' ) ) {
			var siblings = root.querySelectorAll( ':scope > .vx-acc-item' );
			for ( var i = 0; i < siblings.length; i++ ) {
				if ( siblings[ i ] !== item ) { toggleAccordionItem( root, siblings[ i ], false ); }
			}
		}
		btn.setAttribute( 'aria-expanded', next ? 'true' : 'false' );
		item.classList.toggle( 'is-open', next );
		setPanel( panel, next, ms );
	}

	on( 'click', '.vx-acc-btn', function ( e, btn ) {
		var item = btn.closest( '.vx-acc-item' );
		var root = btn.closest( '.vx-acc' );
		if ( ! item || ! root ) { return; }
		e.preventDefault();
		toggleAccordionItem( root, item );
	} );

	// Optional arrow-key movement between headers (APG lists this as optional;
	// Tab already works because every header is a real button).
	on( 'keydown', '.vx-acc-btn', function ( e, btn ) {
		var keys = { ArrowDown:1, ArrowUp:1, Home:1, End:1 };
		if ( ! keys[ e.key ] ) { return; }
		var root = btn.closest( '.vx-acc' );
		if ( ! root ) { return; }
		var btns = Array.prototype.slice.call( root.querySelectorAll( '.vx-acc-btn' ) );
		var i = btns.indexOf( btn );
		var next = i;
		if ( 'ArrowDown' === e.key ) { next = ( i + 1 ) % btns.length; }
		if ( 'ArrowUp' === e.key ) { next = ( i - 1 + btns.length ) % btns.length; }
		if ( 'Home' === e.key ) { next = 0; }
		if ( 'End' === e.key ) { next = btns.length - 1; }
		e.preventDefault();
		btns[ next ].focus();
	} );

	/* Deep link: /page/#section-id opens and scrolls to that item. */
	function openFromHash() {
		if ( ! window.location.hash ) { return; }
		var target = null;
		try { target = d.querySelector( window.location.hash ); } catch ( err ) { return; }
		if ( ! target ) { return; }
		var item = target.closest ? target.closest( '.vx-acc-item' ) : null;
		if ( ! item ) { return; }
		var root = item.closest( '.vx-acc' );
		if ( ! root || ! root.hasAttribute( 'data-vx-deeplink' ) ) { return; }
		toggleAccordionItem( root, item, true );
		window.setTimeout( function () { item.scrollIntoView( { behavior: REDUCED ? 'auto' : 'smooth', block: 'start' } ); }, 60 );
	}
	window.addEventListener( 'hashchange', openFromHash );
	if ( 'loading' === d.readyState ) { d.addEventListener( 'DOMContentLoaded', openFromHash ); } else { openFromHash(); }

	/* ------------------------------------------------------------------ *
	 * Tabs — W3C APG Tabs pattern.
	 * Roving tabindex: exactly one tab is in the tab order, arrows move between
	 * them. Tab from the tablist lands in the panel, not on the next tab.
	 * ------------------------------------------------------------------ */
	function selectTab( root, index ) {
		var tabs = root.querySelectorAll( '.vx-tab' );
		var panels = root.querySelectorAll( '.vx-tabp' );
		if ( ! tabs.length ) { return; }
		index = Math.max( 0, Math.min( index, tabs.length - 1 ) );
		for ( var i = 0; i < tabs.length; i++ ) {
			var on = ( i === index );
			tabs[ i ].setAttribute( 'aria-selected', on ? 'true' : 'false' );
			tabs[ i ].setAttribute( 'tabindex', on ? '0' : '-1' );
			tabs[ i ].classList.toggle( 'is-active', on );
			if ( panels[ i ] ) { panels[ i ].hidden = ! on; }
		}
	}
	function tabIndex( root, tab ) {
		return Array.prototype.indexOf.call( root.querySelectorAll( '.vx-tab' ), tab );
	}
	on( 'click', '.vx-tab', function ( e, tab ) {
		var root = tab.closest( '.vx-tabs' );
		if ( ! root ) { return; }
		e.preventDefault();
		selectTab( root, tabIndex( root, tab ) );
	} );
	on( 'mouseenter', '.vx-tab', function ( e, tab ) {
		var root = tab.closest( '.vx-tabs' );
		if ( ! root || 'hover' !== root.getAttribute( 'data-vx-activate' ) ) { return; }
		selectTab( root, tabIndex( root, tab ) );
	} );
	on( 'keydown', '.vx-tab', function ( e, tab ) {
		var root = tab.closest( '.vx-tabs' );
		if ( ! root ) { return; }
		var vertical = root.classList.contains( 'vx-tabs-left' );
		var next = { ArrowRight: ! vertical, ArrowLeft: ! vertical, ArrowDown: vertical, ArrowUp: vertical };
		if ( ! next[ e.key ] && 'Home' !== e.key && 'End' !== e.key ) { return; }
		var tabs = root.querySelectorAll( '.vx-tab' );
		var i = tabIndex( root, tab ), to = i;
		if ( 'ArrowRight' === e.key || 'ArrowDown' === e.key ) { to = ( i + 1 ) % tabs.length; }
		if ( 'ArrowLeft' === e.key || 'ArrowUp' === e.key ) { to = ( i - 1 + tabs.length ) % tabs.length; }
		if ( 'Home' === e.key ) { to = 0; }
		if ( 'End' === e.key ) { to = tabs.length - 1; }
		e.preventDefault();
		selectTab( root, to );
		tabs[ to ].focus();
	} );

	/* Tabs collapse to an accordion below the mobile breakpoint. The markup is
	 * identical either way — only the class changes — so no content is
	 * duplicated and screen readers see one set of controls. */
	function syncTabMode() {
		var roots = d.querySelectorAll( '.vx-tabs[data-vx-toacc]' );
		for ( var i = 0; i < roots.length; i++ ) {
			var bp = num( roots[ i ], 'data-vx-accbp', 767 );
			roots[ i ].classList.toggle( 'vx-tabs-acc', window.innerWidth <= bp );
		}
	}
	window.addEventListener( 'resize', syncTabMode );
	if ( 'loading' === d.readyState ) { d.addEventListener( 'DOMContentLoaded', syncTabMode ); } else { syncTabMode(); }

	function openTabFromHash() {
		if ( ! window.location.hash ) { return; }
		var el = null;
		try { el = d.querySelector( window.location.hash ); } catch ( err ) { return; }
		if ( ! el ) { return; }
		var panel = el.closest ? el.closest( '.vx-tabp' ) : null;
		if ( ! panel ) { return; }
		var root = panel.closest( '.vx-tabs' );
		if ( ! root || ! root.hasAttribute( 'data-vx-deeplink' ) ) { return; }
		var panels = Array.prototype.slice.call( root.querySelectorAll( '.vx-tabp' ) );
		selectTab( root, panels.indexOf( panel ) );
	}
	window.addEventListener( 'hashchange', openTabFromHash );
	if ( 'loading' === d.readyState ) { d.addEventListener( 'DOMContentLoaded', openTabFromHash ); } else { openTabFromHash(); }

	/* ------------------------------------------------------------------ *
	 * Overlay primitive — shared by offcanvas, modal and dropdown.
	 * W3C APG Dialog (Modal) pattern: focus moves in, is trapped, Escape closes,
	 * focus returns to whatever opened it, and the rest of the page is inert.
	 * Written once here so three elements cannot drift apart.
	 * ------------------------------------------------------------------ */
	var FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),' +
		'textarea:not([disabled]),[tabindex]:not([tabindex="-1"]),summary,area[href],iframe';
	var openStack = [];

	function focusables( root ) {
		return Array.prototype.filter.call( root.querySelectorAll( FOCUSABLE ), function ( el ) {
			return el.offsetWidth || el.offsetHeight || el.getClientRects().length;
		} );
	}
	function lockScroll( on ) {
		if ( on ) {
			// Compensate for the scrollbar so the page doesn't jump sideways.
			var gap = window.innerWidth - d.documentElement.clientWidth;
			d.documentElement.style.overflow = 'hidden';
			if ( gap > 0 ) { d.body.style.paddingRight = gap + 'px'; }
		} else {
			d.documentElement.style.overflow = '';
			d.body.style.paddingRight = '';
		}
	}
	function openOverlay( el, opener ) {
		if ( ! el || el.classList.contains( 'is-open' ) ) { return; }
		el.__vxOpener = opener || d.activeElement;
		el.hidden = false;
		// Reflow before adding the class so the CSS transition has a start state.
		el.offsetHeight;
		el.classList.add( 'is-open' );
		el.setAttribute( 'aria-hidden', 'false' );
		if ( el.hasAttribute( 'data-vx-modal' ) ) {
			openStack.push( el );
			lockScroll( true );
		}
		// APG: do not focus the container itself — a large focusable box makes the
		// focus position impossible to perceive. Prefer the close button, then the
		// first control, then the heading.
		var first = el.querySelector( '[data-vx-autofocus]' ) ||
			el.querySelector( '.vx-ov-close' ) || focusables( el )[ 0 ] ||
			el.querySelector( 'h1,h2,h3' );
		if ( first ) {
			if ( ! first.hasAttribute( 'tabindex' ) && ! first.matches( FOCUSABLE ) ) { first.setAttribute( 'tabindex', '-1' ); }
			first.focus();
		}
		el.dispatchEvent( new CustomEvent( 'vx:open', { bubbles: true } ) );
	}
	function closeOverlay( el ) {
		if ( ! el || ! el.classList.contains( 'is-open' ) ) { return; }
		el.classList.remove( 'is-open' );
		el.setAttribute( 'aria-hidden', 'true' );
		var i = openStack.indexOf( el );
		if ( i > -1 ) { openStack.splice( i, 1 ); }
		if ( ! openStack.length ) { lockScroll( false ); }
		var back = el.__vxOpener;
		var ms = num( el, 'data-vx-ms', 250 );
		window.setTimeout( function () {
			if ( ! el.classList.contains( 'is-open' ) ) { el.hidden = true; }
		}, REDUCED ? 0 : ms );
		if ( back && back.focus ) { back.focus(); }
		el.dispatchEvent( new CustomEvent( 'vx:close', { bubbles: true } ) );
	}
	function overlayById( id ) { return id ? d.getElementById( id.replace( /^#/, '' ) ) : null; }

	// Any element can open/close/toggle any overlay — this is the whole
	// "interaction" system in three attributes.
	on( 'click', '[data-vx-open]', function ( e, t ) {
		var el = overlayById( t.getAttribute( 'data-vx-open' ) );
		if ( ! el ) { return; }
		e.preventDefault();
		openOverlay( el, t );
	} );
	on( 'click', '[data-vx-toggle]', function ( e, t ) {
		var el = overlayById( t.getAttribute( 'data-vx-toggle' ) );
		if ( ! el ) { return; }
		e.preventDefault();
		var open = el.classList.contains( 'is-open' );
		t.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
		if ( open ) { closeOverlay( el ); } else { openOverlay( el, t ); }
	} );
	on( 'click', '[data-vx-close]', function ( e, t ) {
		var el = t.closest( '.vx-ov' ) || overlayById( t.getAttribute( 'data-vx-close' ) );
		if ( ! el ) { return; }
		e.preventDefault();
		closeOverlay( el );
	} );
	// Clicking the backdrop closes, but only when the click started there —
	// otherwise dragging a text selection out of the panel closes it.
	var backdropDown = false;
	on( 'mousedown', '.vx-ov-back', function () { backdropDown = true; } );
	d.addEventListener( 'mouseup', function ( e ) {
		var back = e.target.closest ? e.target.closest( '.vx-ov-back' ) : null;
		if ( back && backdropDown ) {
			var el = back.closest( '.vx-ov' );
			if ( el && ! el.hasAttribute( 'data-vx-nobackclose' ) ) { closeOverlay( el ); }
		}
		backdropDown = false;
	} );
	d.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) {
			var top = openStack[ openStack.length - 1 ] || d.querySelector( '.vx-ov.is-open' );
			if ( top && ! top.hasAttribute( 'data-vx-noesc' ) ) { e.preventDefault(); closeOverlay( top ); }
			return;
		}
		if ( 'Tab' !== e.key ) { return; }
		var modal = openStack[ openStack.length - 1 ];
		if ( ! modal ) { return; }
		var f = focusables( modal );
		if ( ! f.length ) { e.preventDefault(); return; }
		var first = f[ 0 ], last = f[ f.length - 1 ];
		if ( e.shiftKey && ( d.activeElement === first || ! modal.contains( d.activeElement ) ) ) {
			e.preventDefault(); last.focus();
		} else if ( ! e.shiftKey && d.activeElement === last ) {
			e.preventDefault(); first.focus();
		}
	}, true );

	/* ------------------------------------------------------------------ *
	 * Slider — CSS scroll-snap does the sliding; JS only adds controls.
	 *
	 * Chosen over Swiper/Splide deliberately: the slides are real HTML in a
	 * scroll container, so they are present for search engines and screen
	 * readers with no JS, there is no layout shift while a library boots, and
	 * native scrolling handles touch, trackpad and keyboard for free.
	 * W3C APG Carousel pattern for the roles and the play/pause requirement.
	 * ------------------------------------------------------------------ */
	function slides( root ) {
		return Array.prototype.slice.call( root.querySelectorAll( '.vx-slide' ) );
	}
	function slideIndex( root ) {
		var track = root.querySelector( '.vx-track' );
		if ( ! track ) { return 0; }
		var list = slides( root );
		var best = 0, dist = Infinity;
		for ( var i = 0; i < list.length; i++ ) {
			var d2 = Math.abs( list[ i ].offsetLeft - track.scrollLeft );
			if ( d2 < dist ) { dist = d2; best = i; }
		}
		return best;
	}
	function goToSlide( root, i, smooth ) {
		var track = root.querySelector( '.vx-track' );
		var list = slides( root );
		if ( ! track || ! list.length ) { return; }
		var loop = root.hasAttribute( 'data-vx-loop' );
		if ( loop ) { i = ( i + list.length ) % list.length; }
		i = Math.max( 0, Math.min( i, list.length - 1 ) );
		track.scrollTo( { left: list[ i ].offsetLeft, behavior: ( smooth && ! REDUCED ) ? 'smooth' : 'auto' } );
	}
	function syncSlider( root ) {
		var i = slideIndex( root );
		var list = slides( root );
		var dots = root.querySelectorAll( '.vx-dot' );
		for ( var k = 0; k < dots.length; k++ ) {
			var on = ( k === i );
			dots[ k ].setAttribute( 'aria-selected', on ? 'true' : 'false' );
			dots[ k ].setAttribute( 'tabindex', on ? '0' : '-1' );
			dots[ k ].classList.toggle( 'is-active', on );
		}
		if ( ! root.hasAttribute( 'data-vx-loop' ) ) {
			var prev = root.querySelector( '.vx-prev' ), next = root.querySelector( '.vx-next' );
			if ( prev ) { prev.disabled = ( 0 === i ); }
			if ( next ) { next.disabled = ( i >= list.length - 1 ); }
		}
		// Tell assistive tech which slide is showing without stealing focus.
		var live = root.querySelector( '.vx-live' );
		if ( live ) { live.textContent = ( i + 1 ) + ' / ' + list.length; }
	}
	on( 'click', '.vx-prev', function ( e, t ) {
		var root = t.closest( '.vx-slider' ); if ( ! root ) { return; }
		goToSlide( root, slideIndex( root ) - 1, true );
	} );
	on( 'click', '.vx-next', function ( e, t ) {
		var root = t.closest( '.vx-slider' ); if ( ! root ) { return; }
		goToSlide( root, slideIndex( root ) + 1, true );
	} );
	on( 'click', '.vx-dot', function ( e, t ) {
		var root = t.closest( '.vx-slider' ); if ( ! root ) { return; }
		goToSlide( root, parseInt( t.getAttribute( 'data-vx-i' ), 10 ) || 0, true );
	} );
	on( 'keydown', '.vx-dots', function ( e, t ) {
		if ( 'ArrowLeft' !== e.key && 'ArrowRight' !== e.key ) { return; }
		var root = t.closest( '.vx-slider' ); if ( ! root ) { return; }
		e.preventDefault();
		var i = slideIndex( root ) + ( 'ArrowRight' === e.key ? 1 : -1 );
		goToSlide( root, i, true );
		window.setTimeout( function () {
			var active = root.querySelector( '.vx-dot.is-active' );
			if ( active ) { active.focus(); }
		}, 180 );
	} );

	/* Autoplay. Pauses on hover, on focus, when the tab is hidden and when the
	 * visitor prefers reduced motion — and always ships a play/pause control,
	 * which the APG requires for anything that moves on its own. */
	function startAuto( root ) {
		if ( REDUCED || root.__vxTimer ) { return; }
		var ms = num( root, 'data-vx-auto', 0 );
		if ( ! ms ) { return; }
		root.__vxTimer = window.setInterval( function () {
			if ( d.hidden ) { return; }
			goToSlide( root, slideIndex( root ) + 1, true );
		}, ms );
		root.classList.add( 'is-playing' );
		var pp = root.querySelector( '.vx-play' );
		if ( pp ) { pp.setAttribute( 'aria-pressed', 'false' ); pp.setAttribute( 'aria-label', pp.getAttribute( 'data-vx-pause-label' ) || 'Pause' ); }
	}
	function stopAuto( root ) {
		if ( root.__vxTimer ) { window.clearInterval( root.__vxTimer ); root.__vxTimer = null; }
		root.classList.remove( 'is-playing' );
		var pp = root.querySelector( '.vx-play' );
		if ( pp ) { pp.setAttribute( 'aria-pressed', 'true' ); pp.setAttribute( 'aria-label', pp.getAttribute( 'data-vx-play-label' ) || 'Play' ); }
	}
	on( 'click', '.vx-play', function ( e, t ) {
		var root = t.closest( '.vx-slider' ); if ( ! root ) { return; }
		if ( root.__vxTimer ) { stopAuto( root ); root.__vxPaused = true; }
		else { root.__vxPaused = false; startAuto( root ); }
	} );

	function initSliders() {
		var roots = d.querySelectorAll( '.vx-slider' );
		for ( var i = 0; i < roots.length; i++ ) {
			( function ( root ) {
				if ( root.__vxInit ) { return; }
				root.__vxInit = true;
				var track = root.querySelector( '.vx-track' );
				if ( track ) {
					var raf = null;
					track.addEventListener( 'scroll', function () {
						if ( raf ) { return; }
						raf = window.requestAnimationFrame( function () { raf = null; syncSlider( root ); } );
					}, { passive: true } );
				}
				root.addEventListener( 'mouseenter', function () { if ( root.__vxTimer ) { stopAuto( root ); root.__vxHover = true; } } );
				root.addEventListener( 'mouseleave', function () { if ( root.__vxHover && ! root.__vxPaused ) { root.__vxHover = false; startAuto( root ); } } );
				root.addEventListener( 'focusin', function () { if ( root.__vxTimer ) { stopAuto( root ); root.__vxFocus = true; } } );
				root.addEventListener( 'focusout', function () {
					if ( root.__vxFocus && ! root.contains( d.activeElement ) && ! root.__vxPaused ) { root.__vxFocus = false; startAuto( root ); }
				} );
				syncSlider( root );
				startAuto( root );
			}( roots[ i ] ) );
		}
	}
	d.addEventListener( 'visibilitychange', function () {
		var roots = d.querySelectorAll( '.vx-slider.is-playing' );
		for ( var i = 0; i < roots.length; i++ ) { if ( d.hidden ) { stopAuto( roots[ i ] ); } else { startAuto( roots[ i ] ); } }
	} );
	if ( 'loading' === d.readyState ) { d.addEventListener( 'DOMContentLoaded', initSliders ); } else { initSliders(); }

	// Expose a small API so later elements (tabs, sliders, offcanvas) can reuse
	// the same primitives instead of shipping their own copies.
	window.VeloxElements = {
		reduced: REDUCED,
		on: on,
		setPanel: setPanel,
		selectTab: selectTab,
		open: openOverlay,
		close: closeOverlay,
		goToSlide: goToSlide
	};
}() );
