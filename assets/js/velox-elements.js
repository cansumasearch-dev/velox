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
		// mouseenter/mouseleave do NOT bubble, so they cannot be delegated
		// directly — a listener on document never fires. Translate them into
		// mouseover/mouseout and check we actually crossed the element's
		// boundary rather than moving between its own children.
		if ( 'mouseenter' === type || 'mouseleave' === type ) {
			var real = ( 'mouseenter' === type ) ? 'mouseover' : 'mouseout';
			d.addEventListener( real, function ( e ) {
				var t = e.target.closest ? e.target.closest( sel ) : null;
				if ( ! t ) { return; }
				var other = e.relatedTarget;
				if ( other && ( t === other || t.contains( other ) ) ) { return; }
				fn( e, t );
			}, false );
			return;
		}
		d.addEventListener( type, function ( e ) {
			var t = e.target.closest ? e.target.closest( sel ) : null;
			if ( t ) { fn( e, t ); }
		}, false );
	}
	function num( el, attr, fallback ) {
		var v = parseInt( el.getAttribute( attr ), 10 );
		return isNaN( v ) ? fallback : v;
	}
	// Tabs, sliders and accordions can be nested inside one another, so every
	// lookup is scoped to the element's own parts — a plain querySelectorAll
	// also matched the inner copy and threw the outer one's indexes off.
	function own( root, sel ) {
		try { return Array.prototype.slice.call( root.querySelectorAll( sel.replace( /(^|,)\s*/g, '$1:scope > ' ) ) ); }
		catch ( e ) { return Array.prototype.slice.call( root.querySelectorAll( sel ) ); }
	}
	function ownOne( root, sel ) { return own( root, sel )[ 0 ] || null; }
	function safeQuery( sel ) {
		if ( ! sel ) { return null; }
		try { return d.querySelector( sel ); } catch ( e ) { return null; }
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
		var btns = own( root, '.vx-acc-item > .vx-acc-h > .vx-acc-btn' );
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
	function tabsOf( root ) { return own( root, '.vx-tablist > .vx-tab' ); }
	function panelsOf( root ) { return own( root, '.vx-tabpanels > .vx-tabp' ); }
	function selectTab( root, index ) {
		var tabs = tabsOf( root );
		var panels = panelsOf( root );
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
		return tabsOf( root ).indexOf( tab );
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
		var tabs = tabsOf( root );
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
		selectTab( root, panelsOf( root ).indexOf( panel ) );
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
	function syncToggles( el, open ) {
		if ( ! el.id ) { return; }
		var ts = d.querySelectorAll( '[data-vx-toggle]' );
		for ( var i = 0; i < ts.length; i++ ) {
			if ( ts[ i ].getAttribute( 'data-vx-toggle' ).replace( /^#/, '' ) === el.id ) {
				ts[ i ].setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			}
		}
	}
	function openOverlay( el, opener, quiet ) {
		if ( ! el || el.classList.contains( 'is-open' ) ) { return; }
		el.__vxOpener = opener || d.activeElement;
		el.hidden = false;
		// Reflow before adding the class so the CSS transition has a start state.
		el.offsetHeight;
		el.classList.add( 'is-open' );
		el.setAttribute( 'aria-hidden', 'false' );
		syncToggles( el, true );
		if ( el.hasAttribute( 'data-vx-modal' ) ) {
			openStack.push( el );
			lockScroll( true );
		}
		// APG: do not focus the container itself — a large focusable box makes the
		// focus position impossible to perceive. Prefer the close button, then the
		// first control, then the heading.
		var first = quiet ? null : el.querySelector( '[data-vx-autofocus]' ) ||
			el.querySelector( '.vx-ov-close' ) || focusables( el )[ 0 ] ||
			el.querySelector( 'h1,h2,h3' );
		if ( first ) {
			if ( ! first.hasAttribute( 'tabindex' ) && ! first.matches( FOCUSABLE ) ) { first.setAttribute( 'tabindex', '-1' ); }
			first.focus();
		}
		el.dispatchEvent( new CustomEvent( 'vx:open', { bubbles: true } ) );
	}
	function closeOverlay( el, quiet ) {
		if ( ! el || ! el.classList.contains( 'is-open' ) ) { return; }
		el.classList.remove( 'is-open' );
		el.setAttribute( 'aria-hidden', 'true' );
		// The toggle button kept saying "expanded" after Escape.
		syncToggles( el, false );
		var i = openStack.indexOf( el );
		if ( i > -1 ) { openStack.splice( i, 1 ); }
		if ( ! openStack.length ) { lockScroll( false ); }
		var back = el.__vxOpener;
		var ms = num( el, 'data-vx-ms', 250 );
		window.setTimeout( function () {
			if ( ! el.classList.contains( 'is-open' ) ) { el.hidden = true; }
		}, REDUCED ? 0 : ms );
		if ( back && back.focus && ! quiet ) { back.focus(); }
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
		if ( open ) { closeOverlay( el ); } else { openOverlay( el, t ); }
	} );
	// "Opened by (CSS selector)": any matching element on the page opens it.
	d.addEventListener( 'click', function ( e ) {
		if ( ! e.target.closest ) { return; }
		var ovs = d.querySelectorAll( '.vx-ov[data-vx-openwith]' );
		for ( var i = 0; i < ovs.length; i++ ) {
			var t = null;
			try { t = e.target.closest( ovs[ i ].getAttribute( 'data-vx-openwith' ) ); } catch ( err ) { continue; }
			if ( t && ! ovs[ i ].contains( t ) ) {
				e.preventDefault();
				openOverlay( ovs[ i ], t );
				return;
			}
		}
	} );
	// A dropdown closes when the visitor clicks anywhere else.
	d.addEventListener( 'click', function ( e ) {
		var open = d.querySelectorAll( '.vx-dropdown.is-open' );
		for ( var i = 0; i < open.length; i++ ) {
			var wrap = open[ i ].closest( '.vx-dd-wrap' ) || open[ i ];
			if ( ! wrap.contains( e.target ) ) { closeOverlay( open[ i ], true ); }
		}
	} );
	// "Opens on: Hover" for dropdowns (pointer devices; click still works).
	on( 'mouseenter', '.vx-dd-wrap[data-vx-hover]', function ( e, w ) {
		if ( window.matchMedia && window.matchMedia( '(hover: none)' ).matches ) { return; }
		var el = ownOne( w, '.vx-dropdown' );
		if ( el ) { window.clearTimeout( w.__vxT ); openOverlay( el, null, true ); }
	} );
	on( 'mouseleave', '.vx-dd-wrap[data-vx-hover]', function ( e, w ) {
		var el = ownOne( w, '.vx-dropdown' );
		if ( el ) { w.__vxT = window.setTimeout( function () { closeOverlay( el, true ); }, 150 ); }
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
		return own( root, '.vx-track > .vx-slide' );
	}
	function slideIndex( root ) {
		var track = ownOne( root, '.vx-track' );
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
		var track = ownOne( root, '.vx-track' );
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
		var dots = own( root, '.vx-controls > .vx-dots > .vx-dot' );
		for ( var k = 0; k < dots.length; k++ ) {
			var on = ( k === i );
			dots[ k ].setAttribute( 'aria-selected', on ? 'true' : 'false' );
			dots[ k ].setAttribute( 'tabindex', on ? '0' : '-1' );
			dots[ k ].classList.toggle( 'is-active', on );
		}
		if ( ! root.hasAttribute( 'data-vx-loop' ) ) {
			var prev = ownOne( root, '.vx-controls > .vx-arrows > .vx-prev' ), next = ownOne( root, '.vx-controls > .vx-arrows > .vx-next' );
			if ( prev ) { prev.disabled = ( 0 === i ); }
			if ( next ) { next.disabled = ( i >= list.length - 1 ); }
		}
		// Tell assistive tech which slide is showing without stealing focus.
		var live = ownOne( root, '.vx-live' );
		if ( live ) { live.textContent = ( i + 1 ) + ' / ' + list.length; }
		// The visible "2 of 5" counter never moved off "1 / 5".
		var count = ownOne( root, '.vx-count' ) || ownOne( root, '.vx-controls > .vx-count' );
		if ( count ) { count.textContent = ( i + 1 ) + ' / ' + list.length; }
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
			var active = ownOne( root, '.vx-controls > .vx-dots > .vx-dot.is-active' );
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
		var pp = ownOne( root, '.vx-controls > .vx-play' );
		if ( pp ) { pp.setAttribute( 'aria-pressed', 'false' ); pp.setAttribute( 'aria-label', pp.getAttribute( 'data-vx-pause-label' ) || 'Pause' ); }
	}
	function stopAuto( root ) {
		if ( root.__vxTimer ) { window.clearInterval( root.__vxTimer ); root.__vxTimer = null; }
		root.classList.remove( 'is-playing' );
		var pp = ownOne( root, '.vx-controls > .vx-play' );
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
				var track = ownOne( root, '.vx-track' );
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
				// With reduced motion nothing plays, so the button must offer
				// "Play" rather than claim it can pause.
				if ( REDUCED && root.hasAttribute( 'data-vx-auto' ) ) { stopAuto( root ); }
				startAuto( root );
			}( roots[ i ] ) );
		}
	}
	d.addEventListener( 'visibilitychange', function () {
		var roots = d.querySelectorAll( '.vx-slider.is-playing' );
		for ( var i = 0; i < roots.length; i++ ) { if ( d.hidden ) { stopAuto( roots[ i ] ); } else { startAuto( roots[ i ] ); } }
	} );
	if ( 'loading' === d.readyState ) { d.addEventListener( 'DOMContentLoaded', initSliders ); } else { initSliders(); }

	/* ------------------------------------------------------------------ *
	 * Triggers — shared by popups, floating buttons and announcement bars.
	 * One implementation so "show after 20% scroll, once per visit" behaves
	 * identically wherever it is configured.
	 * ------------------------------------------------------------------ */
	function seen( key ) {
		try { return !! window.localStorage.getItem( 'vx_seen_' + key ); } catch ( e ) { return false; }
	}
	function markSeen( key, scope ) {
		try {
			if ( 'session' === scope ) { window.sessionStorage.setItem( 'vx_seen_' + key, '1' ); }
			else if ( 'day' === scope ) { window.localStorage.setItem( 'vx_seen_' + key, String( Date.now() ) ); }
			else if ( 'ever' === scope ) { window.localStorage.setItem( 'vx_seen_' + key, '1' ); }
		} catch ( e ) {}
	}
	function alreadyShown( key, scope ) {
		try {
			if ( 'always' === scope || ! scope ) { return false; }
			if ( 'session' === scope ) { return !! window.sessionStorage.getItem( 'vx_seen_' + key ); }
			var v = window.localStorage.getItem( 'vx_seen_' + key );
			if ( ! v ) { return false; }
			if ( 'ever' === scope ) { return true; }
			return ( Date.now() - parseInt( v, 10 ) ) < 86400000;   // within the day
		} catch ( e ) { return false; }
	}
	/** Run `fire` when the configured trigger happens. Returns a teardown. */
	function armTrigger( el, fire ) {
		var type  = el.getAttribute( 'data-vx-trig' ) || 'click';
		var scope = el.getAttribute( 'data-vx-once' ) || 'always';
		var key   = el.id || el.getAttribute( 'data-vx-key' ) || 'vx';
		if ( alreadyShown( key, scope ) ) { return; }
		// A dismissal is remembered for the session regardless of frequency.
		if ( alreadyShown( key, 'session' ) ) { return; }

		function go() {
			markSeen( key, scope );
			fire();
		}
		if ( 'load' === type ) { go(); return; }
		if ( 'delay' === type ) {
			window.setTimeout( go, ( parseFloat( el.getAttribute( 'data-vx-delay' ) ) || 3 ) * 1000 );
			return;
		}
		if ( 'scroll' === type ) {
			var pct = parseFloat( el.getAttribute( 'data-vx-scroll' ) ) || 50;
			var onScroll = function () {
				var h = d.documentElement.scrollHeight - window.innerHeight;
				if ( h > 0 && ( window.scrollY / h ) * 100 >= pct ) {
					window.removeEventListener( 'scroll', onScroll );
					go();
				}
			};
			window.addEventListener( 'scroll', onScroll, { passive: true } );
			onScroll();
			return;
		}
		if ( 'element' === type ) {
			var sel = el.getAttribute( 'data-vx-target' );
			// An invalid selector threw here and stopped every trigger after it.
			var watch = safeQuery( sel );
			if ( ! watch || ! ( 'IntersectionObserver' in window ) ) { return; }
			var io = new IntersectionObserver( function ( ents ) {
				if ( ents[ 0 ].isIntersecting ) { io.disconnect(); go(); }
			} );
			io.observe( watch );
			return;
		}
		if ( 'idle' === type ) {
			var secs = ( parseFloat( el.getAttribute( 'data-vx-idle' ) ) || 20 ) * 1000;
			var t = null;
			var reset = function () {
				if ( t ) { window.clearTimeout( t ); }
				t = window.setTimeout( go, secs );
			};
			[ 'mousemove', 'keydown', 'scroll', 'touchstart' ].forEach( function ( ev ) {
				d.addEventListener( ev, reset, { passive: true } );
			} );
			reset();
			return;
		}
		if ( 'exit' === type ) {
			// Exit intent is a desktop idea; on touch there is no cursor to leave,
			// so fall back to a scroll trigger rather than never firing.
			var touch = window.matchMedia && window.matchMedia( '(hover: none)' ).matches;
			if ( touch ) {
				var fired = false;
				var onS = function () {
					var h2 = d.documentElement.scrollHeight - window.innerHeight;
					if ( ! fired && h2 > 0 && ( window.scrollY / h2 ) > 0.6 ) { fired = true; window.removeEventListener( 'scroll', onS ); go(); }
				};
				window.addEventListener( 'scroll', onS, { passive: true } );
				return;
			}
			var leave = function ( e ) {
				if ( e.clientY <= 0 ) { d.removeEventListener( 'mouseout', leave ); go(); }
			};
			d.addEventListener( 'mouseout', leave );
			return;
		}
		// 'click' needs no arming — a [data-vx-open] button handles it.
	}

	function initTriggered() {
		var els = d.querySelectorAll( '[data-vx-trig]' );
		for ( var i = 0; i < els.length; i++ ) {
			( function ( el ) {
				if ( el.__vxArmed ) { return; }
				el.__vxArmed = true;
				armTrigger( el, function () {
					if ( el.classList.contains( 'vx-ov' ) ) { openOverlay( el, null ); return; }
					// Drop the attribute as well as adding the class: leaving `hidden`
					// on a visible element tells assistive tech the opposite of what
					// the page shows.
					el.hidden = false;
					el.removeAttribute( 'hidden' );
					el.classList.add( 'is-shown' );
				} );
			}( els[ i ] ) );
		}
	}
	if ( 'loading' === d.readyState ) { d.addEventListener( 'DOMContentLoaded', initTriggered ); } else { initTriggered(); }

	/* Dismissible bars remember the dismissal for the configured scope. */
	on( 'click', '[data-vx-dismiss]', function ( e, t ) {
		var bar = t.closest( '.vx-bar' ) || t.closest( '[data-vx-dismiss-target]' );
		if ( ! bar ) { return; }
		e.preventDefault();
		bar.classList.remove( 'is-shown' );
		bar.hidden = true;
		bar.setAttribute( 'hidden', '' );
		// Dismissal is a deliberate action, so it sticks for at least the session
		// even when the trigger is set to show every time. Otherwise closing a bar
		// only closes it until the next page load, which reads as broken.
		var scope = bar.getAttribute( 'data-vx-once' ) || 'session';
		markSeen( bar.id || 'bar', ( 'always' === scope ) ? 'session' : scope );
	} );

	/* Back-to-top and any button that scrolls somewhere. */
	on( 'click', '[data-vx-scrollto]', function ( e, t ) {
		e.preventDefault();
		var sel = t.getAttribute( 'data-vx-scrollto' );
		var target = ( '#top' === sel || ! sel ) ? null : safeQuery( sel );
		if ( target ) { target.scrollIntoView( { behavior: REDUCED ? 'auto' : 'smooth', block: 'start' } ); }
		else { window.scrollTo( { top: 0, behavior: REDUCED ? 'auto' : 'smooth' } ); }
	} );

	/* Reading progress bar. */
	function initProgress() {
		var bars = d.querySelectorAll( '.vx-progress' );
		if ( ! bars.length ) { return; }
		var upd = function () {
			var h = d.documentElement.scrollHeight - window.innerHeight;
			var pct = h > 0 ? Math.min( 100, ( window.scrollY / h ) * 100 ) : 0;
			for ( var i = 0; i < bars.length; i++ ) {
				var fill = bars[ i ].querySelector( '.vx-progress-fill' );
				if ( fill ) { fill.style.width = pct.toFixed( 1 ) + '%'; }
				bars[ i ].setAttribute( 'aria-valuenow', Math.round( pct ) );
			}
		};
		window.addEventListener( 'scroll', upd, { passive: true } );
		window.addEventListener( 'resize', upd );
		upd();
	}
	if ( 'loading' === d.readyState ) { d.addEventListener( 'DOMContentLoaded', initProgress ); } else { initProgress(); }

	/* ------------------------------------------------------------------ *
	 * Navigation.
	 *
	 * Deliberately NOT the ARIA menubar/menu/menuitem pattern. Those roles put a
	 * screen reader into application mode and promise keyboard behaviour that
	 * site navigation does not have. This is the APG Disclosure Navigation
	 * pattern: a <nav> landmark, a plain list of links, and a real <button
	 * aria-expanded> for each submenu. Tab reaches everything; no roving
	 * tabindex, no aria-haspopup.
	 * ------------------------------------------------------------------ */
	function closeSubmenus( root, except ) {
		var btns = root.querySelectorAll( '.vx-sub-btn[aria-expanded="true"]' );
		for ( var i = 0; i < btns.length; i++ ) {
			if ( btns[ i ] === except ) { continue; }
			btns[ i ].setAttribute( 'aria-expanded', 'false' );
			var p2 = d.getElementById( btns[ i ].getAttribute( 'aria-controls' ) );
			if ( p2 ) { p2.hidden = true; }
		}
	}
	function toggleSubmenu( btn, force ) {
		var panel = d.getElementById( btn.getAttribute( 'aria-controls' ) );
		if ( ! panel ) { return; }
		var open = ( force === undefined ) ? 'false' === btn.getAttribute( 'aria-expanded' ) : force;
		var root = btn.closest( '.vx-nav' );
		if ( open && root ) { closeSubmenus( root, btn ); }
		btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		panel.hidden = ! open;
	}
	on( 'click', '.vx-sub-btn', function ( e, btn ) {
		e.preventDefault();
		var root = btn.closest( '.vx-nav' );
		// In hover mode on a pointer device, hover already opened this — letting
		// the click toggle would close it the instant you tried to click through.
		if ( root && 'hover' === root.getAttribute( 'data-vx-subtrigger' ) &&
			! root.classList.contains( 'is-mobile' ) &&
			! ( window.matchMedia && window.matchMedia( '(hover: none)' ).matches ) ) {
			toggleSubmenu( btn, true );
			return;
		}
		toggleSubmenu( btn );
	} );
	// Hover opening is a convenience on pointer devices only; the button still
	// works by click and keyboard, so nothing depends on hover.
	on( 'mouseenter', '.vx-has-sub', function ( e, li ) {
		var root = li.closest( '.vx-nav' );
		if ( ! root || 'hover' !== root.getAttribute( 'data-vx-subtrigger' ) ) { return; }
		if ( window.matchMedia && window.matchMedia( '(hover: none)' ).matches ) { return; }
		if ( root.classList.contains( 'is-mobile' ) ) { return; }
		var btn = li.querySelector( '.vx-sub-btn' );
		if ( btn ) { toggleSubmenu( btn, true ); }
	} );
	on( 'mouseleave', '.vx-has-sub', function ( e, li ) {
		var root = li.closest( '.vx-nav' );
		if ( ! root || 'hover' !== root.getAttribute( 'data-vx-subtrigger' ) ) { return; }
		if ( root.classList.contains( 'is-mobile' ) ) { return; }
		var btn = li.querySelector( '.vx-sub-btn' );
		if ( btn ) { toggleSubmenu( btn, false ); }
	} );
	// WCAG 1.4.13: moving focus out of the nav must dismiss what hover opened.
	d.addEventListener( 'focusin', function ( e ) {
		var navs = d.querySelectorAll( '.vx-nav' );
		for ( var i = 0; i < navs.length; i++ ) {
			if ( ! navs[ i ].contains( e.target ) ) { closeSubmenus( navs[ i ] ); }
		}
	} );
	function closeBurger( root, focus ) {
		root.classList.remove( 'is-open' );
		var b = root.querySelector( '.vx-burger' );
		var list = root.querySelector( '.vx-nav-list' );
		if ( b ) { b.setAttribute( 'aria-expanded', 'false' ); if ( focus ) { b.focus(); } }
		if ( list && root.classList.contains( 'is-mobile' ) ) { list.hidden = true; }
	}
	d.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' !== e.key ) { return; }
		var open = d.querySelector( '.vx-sub-btn[aria-expanded="true"]' );
		if ( open ) {
			toggleSubmenu( open, false );
			open.focus();
			return;
		}
		var nav = d.querySelector( '.vx-nav.is-mobile.is-open' );
		if ( nav ) { closeBurger( nav, true ); }
	} );
	d.addEventListener( 'click', function ( e ) {
		var navs = d.querySelectorAll( '.vx-nav' );
		for ( var i = 0; i < navs.length; i++ ) {
			if ( ! navs[ i ].contains( e.target ) ) {
				closeSubmenus( navs[ i ] );
				if ( navs[ i ].classList.contains( 'is-open' ) ) { closeBurger( navs[ i ], false ); }
			}
		}
	} );

	/* Burger: below the configured width the links collapse behind a button.
	 * The same list serves both layouts, so there is one set of links in the
	 * document rather than a duplicate mobile copy. */
	function syncNav() {
		var navs = d.querySelectorAll( '.vx-nav[data-vx-bp]' );
		for ( var i = 0; i < navs.length; i++ ) {
			var bp = num( navs[ i ], 'data-vx-bp', 991 );
			var mobile = window.innerWidth <= bp;
			navs[ i ].classList.toggle( 'is-mobile', mobile );
			var burger = navs[ i ].querySelector( '.vx-burger' );
			var list = navs[ i ].querySelector( '.vx-nav-list' );
			if ( burger ) { burger.hidden = ! mobile; }
			if ( list && ! mobile ) { list.hidden = false; }
			if ( list && mobile && ! navs[ i ].classList.contains( 'is-open' ) ) { list.hidden = true; }
		}
	}
	on( 'click', '.vx-burger', function ( e, btn ) {
		var root = btn.closest( '.vx-nav' );
		if ( ! root ) { return; }
		var open = ! root.classList.contains( 'is-open' );
		root.classList.toggle( 'is-open', open );
		btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		var list = root.querySelector( '.vx-nav-list' );
		if ( list ) { list.hidden = ! open; }
	} );
	window.addEventListener( 'resize', syncNav );
	if ( 'loading' === d.readyState ) { d.addEventListener( 'DOMContentLoaded', syncNav ); } else { syncNav(); }

	/* Sticky header: shrink and/or hide as the page scrolls. */
	function initSticky() {
		var els = d.querySelectorAll( '[data-vx-sticky]' );
		if ( ! els.length ) { return; }
		var last = window.scrollY;
		var upd = function () {
			var y = window.scrollY;
			for ( var i = 0; i < els.length; i++ ) {
				var el = els[ i ];
				var after = num( el, 'data-vx-shrink-at', 60 );
				el.classList.toggle( 'is-stuck', y > after );
				if ( el.hasAttribute( 'data-vx-hide-down' ) ) {
					// Never hide while focus is inside — that would trap a keyboard
					// user in an element they cannot see.
					var safe = ! el.contains( d.activeElement );
					el.classList.toggle( 'is-hidden', safe && y > last && y > after * 2 );
				}
			}
			last = y;
		};
		window.addEventListener( 'scroll', upd, { passive: true } );
		upd();
	}
	if ( 'loading' === d.readyState ) { d.addEventListener( 'DOMContentLoaded', initSticky ); } else { initSticky(); }

	/* Scrollspy: mark the nav link whose section is on screen. */
	function initSpy() {
		var spies = d.querySelectorAll( '[data-vx-spy]' );
		if ( ! spies.length || ! ( 'IntersectionObserver' in window ) ) { return; }
		for ( var i = 0; i < spies.length; i++ ) {
			( function ( nav ) {
				var links = Array.prototype.slice.call( nav.querySelectorAll( 'a[href^="#"]' ) );
				var map = {};
				var targets = [];
				links.forEach( function ( a ) {
					var t = null;
					try { t = d.querySelector( a.getAttribute( 'href' ) ); } catch ( err ) {}
					if ( t ) { map[ t.id ] = a; targets.push( t ); }
				} );
				if ( ! targets.length ) { return; }
				var io = new IntersectionObserver( function ( ents ) {
					ents.forEach( function ( en ) {
						if ( ! en.isIntersecting ) { return; }
						links.forEach( function ( a ) { a.classList.remove( 'is-current' ); a.removeAttribute( 'aria-current' ); } );
						var a2 = map[ en.target.id ];
						if ( a2 ) { a2.classList.add( 'is-current' ); a2.setAttribute( 'aria-current', 'true' ); }
					} );
				}, { rootMargin: '-40% 0px -55% 0px' } );
				targets.forEach( function ( t ) { io.observe( t ); } );
			}( spies[ i ] ) );
		}
	}
	if ( 'loading' === d.readyState ) { d.addEventListener( 'DOMContentLoaded', initSpy ); } else { initSpy(); }

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
