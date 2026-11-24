/**
 * Velox Builder — motion runtime (published pages only, loaded when a page uses it).
 *
 *  - [data-vx-text]      text reveal: words / letters rise one after another, or the
 *                        whole element slides up out of a mask; on load or in view.
 *  - [data-vx-kids]      children appear one after another as the box scrolls in.
 *  - [data-vx-para]      parallax: moves slower or faster than the page.
 *  - [data-vx-tilt] / [data-vx-glow] / [data-vx-magnet]  pointer effects.
 *  - [data-vx-stack]     cards stack on top of each other while scrolling.
 *  - [data-vx-hscroll]   the box pins and its row moves sideways while scrolling.
 *  - [data-vx-particles] a calm field of drifting particles behind a section.
 *  - [data-vx-smooth]    on <body>: smooth (eased) wheel scrolling.
 *
 * One shared scroll/frame loop: every task measures first, then writes, and
 * sleeps while its element is far off screen. Everything respects
 * prefers-reduced-motion and works without JS (content is always in the HTML).
 */
( function () {
	'use strict';
	var d = document, w = window, html = d.documentElement;
	var reduce = w.matchMedia && w.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var fine = w.matchMedia && w.matchMedia( '(hover: hover) and (pointer: fine)' ).matches;
	var num = function ( el, a, def ) { var v = parseFloat( el.getAttribute( a ) ); return isNaN( v ) ? def : v; };
	var vh = w.innerHeight, vw = w.innerWidth;
	w.addEventListener( 'resize', function () { vh = w.innerHeight; vw = w.innerWidth; refresh(); }, { passive: true } );

	/* ------------------------------------------------------------ frame loop */
	var tasks = [], queued = false;
	function frame() {
		queued = false;
		var i, live = [];
		for ( i = 0; i < tasks.length; i++ ) { if ( tasks[ i ].awake ) { live.push( tasks[ i ] ); } }
		for ( i = 0; i < live.length; i++ ) { if ( live[ i ].read ) { live[ i ].read(); } }
		for ( i = 0; i < live.length; i++ ) { live[ i ].write(); }
	}
	function tick() { if ( ! queued ) { queued = true; w.requestAnimationFrame( frame ); } }
	function refresh() { tasks.forEach( function ( t ) { if ( t.measure ) { t.measure(); } } ); tick(); }
	w.addEventListener( 'scroll', tick, { passive: true } );
	// A task only runs while its element is within one screen of the viewport.
	var sleeper = 'IntersectionObserver' in w ? new IntersectionObserver( function ( list ) {
		list.forEach( function ( en ) {
			var t = en.target.__vxTask;
			if ( t ) { t.awake = en.isIntersecting; if ( t.awake ) { tick(); } }
		} );
	}, { rootMargin: '100% 0px' } ) : null;
	function addTask( el, t ) {
		t.awake = ! sleeper;
		el.__vxTask = t;
		tasks.push( t );
		if ( sleeper ) { sleeper.observe( el ); }
		if ( t.measure ) { t.measure(); }
		return t;
	}

	/* ------------------------------------------------------------ in view */
	var seen = 'IntersectionObserver' in w ? new IntersectionObserver( function ( list ) {
		list.forEach( function ( en ) {
			if ( en.isIntersecting && en.target.__vxIn ) { var fn = en.target.__vxIn; en.target.__vxIn = null; seen.unobserve( en.target ); fn(); }
		} );
	}, { rootMargin: '0px 0px -12% 0px', threshold: 0.01 } ) : null;
	function onView( el, fn ) {
		if ( ! seen ) { fn(); return; }
		el.__vxIn = fn;
		seen.observe( el );
	}

	/* ------------------------------------------------------------ text reveal */
	// Words are wrapped in spans; the text stays in the DOM (screen readers get
	// the element's own text through aria-label on the parent while it plays).
	function splitText( el, chars ) {
		var out = [];
		( function walk( node ) {
			Array.prototype.slice.call( node.childNodes ).forEach( function ( n ) {
				if ( 3 === n.nodeType ) {
					var parts = n.nodeValue.split( /(\s+)/ ), frag = d.createDocumentFragment();
					parts.forEach( function ( p ) {
						if ( ! p ) { return; }
						if ( /^\s+$/.test( p ) ) { frag.appendChild( d.createTextNode( p ) ); return; }
						var word = d.createElement( 'span' );
						word.className = 'vx-w';
						if ( chars ) {
							Array.prototype.forEach.call( p, function ( c ) {
								var ch = d.createElement( 'span' );
								ch.className = 'vx-c';
								ch.textContent = c;
								word.appendChild( ch );
								out.push( ch );
							} );
						} else {
							var inner = d.createElement( 'span' );
							inner.className = 'vx-wi';
							inner.textContent = p;
							word.appendChild( inner );
							out.push( inner );
						}
						frag.appendChild( word );
					} );
					n.parentNode.replaceChild( frag, n );
				} else if ( 1 === n.nodeType && ! /^(BR|SVG|IMG)$/i.test( n.tagName ) ) {
					// Gradient text (background-clip: text) paints on its own box; split
					// into word boxes the gradient no longer reaches the words and they
					// turn invisible. Such an element rises as one piece instead.
					var cs = w.getComputedStyle ? w.getComputedStyle( n ) : null;
					if ( cs && ( 'text' === cs.backgroundClip || 'text' === cs.webkitBackgroundClip ) ) {
						var unit = d.createElement( 'span' ), box = d.createElement( 'span' );
						unit.className = 'vx-w';
						box.className = chars ? 'vx-c' : 'vx-wi';
						n.parentNode.replaceChild( unit, n );
						box.appendChild( n );
						unit.appendChild( box );
						out.push( box );
						return;
					}
					walk( n );
				}
			} );
		}( el ) );
		return out;
	}
	function initText( el ) {
		var mode = el.getAttribute( 'data-vx-text' ) || 'words';
		var step = num( el, 'data-vx-text-step', 'chars' === mode ? 22 : 70 );
		var dur = num( el, 'data-vx-text-dur', 700 );
		el.style.setProperty( '--vx-td', dur + 'ms' );
		if ( 'mask' === mode ) {
			// The element stays in place and clips; its content slides up inside.
			// (Clipping the element itself hides it from IntersectionObserver.)
			var mi = d.createElement( 'span' );
			mi.className = 'vx-mi';
			while ( el.firstChild ) { mi.appendChild( el.firstChild ); }
			el.appendChild( mi );
		} else {
			var label = el.textContent.replace( /\s+/g, ' ' ).trim();
			var parts = splitText( el, 'chars' === mode );
			parts.forEach( function ( p, i ) { p.style.transitionDelay = ( i * step ) + 'ms'; } );
			if ( label && ! el.hasAttribute( 'aria-label' ) && /^H[1-6]$|^P$/.test( el.tagName ) ) {
				// Reading word by word would be noise: the parent carries the sentence.
				el.setAttribute( 'aria-label', label );
				Array.prototype.forEach.call( el.querySelectorAll( '.vx-w' ), function ( s ) { s.setAttribute( 'aria-hidden', 'true' ); } );
			}
		}
		el.classList.add( 'vx-split' );
		var go = function () { w.requestAnimationFrame( function () { el.classList.add( 'vx-on' ); } ); };
		if ( 'load' === el.getAttribute( 'data-vx-text-on' ) ) { go(); } else { onView( el, go ); }
	}

	/* ------------------------------------------------------------ children one after another */
	function initKids( el ) {
		var step = num( el, 'data-vx-kids-step', 90 );
		var kids = Array.prototype.filter.call( el.children, function ( k ) { return ! /^(SCRIPT|STYLE)$/.test( k.tagName ); } );
		kids.forEach( function ( k, i ) { k.classList.add( 'vx-kid' ); k.style.transitionDelay = ( i * step ) + 'ms'; } );
		el.classList.add( 'vx-kids-ready' );
		onView( el, function () { el.classList.add( 'vx-on' ); } );
	}

	/* ------------------------------------------------------------ parallax */
	function initPara( el ) {
		var speed = num( el, 'data-vx-para', 20 ) / 100, top = 0, h = 0, y = 0;
		var desk = el.hasAttribute( 'data-vx-para-desk' );
		addTask( el, {
			measure: function () {
				var t = el.style.transform; el.style.transform = '';
				var r = el.getBoundingClientRect();
				top = r.top + w.scrollY; h = r.height;
				el.style.transform = t;
			},
			read: function () { y = w.scrollY; },
			write: function () {
				if ( desk && vw < 992 ) { el.style.transform = ''; return; }
				// 0 when the element's centre is in the middle of the screen.
				var off = ( top + h / 2 ) - ( y + vh / 2 );
				el.style.transform = 'translate3d(0,' + ( -off * speed ).toFixed( 1 ) + 'px,0)';
			}
		} );
	}

	/* ------------------------------------------------------------ pointer effects */
	function initTilt( el ) {
		var max = num( el, 'data-vx-tilt', 8 ), raf = 0, px = 0, py = 0;
		el.classList.add( 'vx-tilt' );
		el.addEventListener( 'pointermove', function ( e ) {
			var r = el.getBoundingClientRect();
			px = ( e.clientX - r.left ) / r.width - 0.5; py = ( e.clientY - r.top ) / r.height - 0.5;
			if ( ! raf ) { raf = w.requestAnimationFrame( function () { raf = 0; el.style.transform = 'perspective(900px) rotateX(' + ( -py * max ).toFixed( 2 ) + 'deg) rotateY(' + ( px * max ).toFixed( 2 ) + 'deg)'; } ); }
		} );
		el.addEventListener( 'pointerleave', function () { el.style.transform = ''; } );
	}
	function initGlow( el ) {
		el.classList.add( 'vx-glow' );
		var c = el.getAttribute( 'data-vx-glow' );
		if ( c && '1' !== c ) { el.style.setProperty( '--vx-glow', c ); }
		el.addEventListener( 'pointermove', function ( e ) {
			var r = el.getBoundingClientRect();
			el.style.setProperty( '--vx-gx', ( e.clientX - r.left ) + 'px' );
			el.style.setProperty( '--vx-gy', ( e.clientY - r.top ) + 'px' );
		} );
	}
	function initMagnet( el ) {
		var pull = num( el, 'data-vx-magnet', 0.3 );
		el.classList.add( 'vx-magnet' );
		el.addEventListener( 'pointermove', function ( e ) {
			var r = el.getBoundingClientRect();
			el.style.transform = 'translate(' + ( ( e.clientX - r.left - r.width / 2 ) * pull ).toFixed( 1 ) + 'px,' + ( ( e.clientY - r.top - r.height / 2 ) * pull ).toFixed( 1 ) + 'px)';
		} );
		el.addEventListener( 'pointerleave', function () { el.style.transform = ''; } );
	}

	/* ------------------------------------------------------------ stacking cards */
	// Each child sticks a little lower than the one before; cards that are
	// covered shrink and dim a touch, so the stack reads as depth.
	function initStack( el ) {
		var top = num( el, 'data-vx-stack', 100 ), step = num( el, 'data-vx-stack-step', 18 );
		var kids = Array.prototype.slice.call( el.children ), rects = [];
		el.classList.add( 'vx-stack' );
		kids.forEach( function ( k, i ) { k.classList.add( 'vx-stack-card' ); k.style.top = ( top + i * step ) + 'px'; k.style.zIndex = i + 1; } );
		addTask( el, {
			read: function () { rects = kids.map( function ( k ) { return k.getBoundingClientRect(); } ); },
			write: function () {
				kids.forEach( function ( k, i ) {
					var next = rects[ i + 1 ];
					if ( ! next ) { k.style.transform = ''; k.style.filter = ''; return; }
					// How far the next card has slid over this one (0..1).
					var cover = Math.max( 0, Math.min( 1, ( rects[ i ].bottom - next.top ) / rects[ i ].height ) );
					k.style.transform = 'scale(' + ( 1 - cover * 0.06 ).toFixed( 3 ) + ')';
					k.style.filter = cover > 0.02 ? 'brightness(' + ( 1 - cover * 0.18 ).toFixed( 3 ) + ')' : '';
				} );
			}
		} );
	}

	/* ------------------------------------------------------------ pinned sideways row */
	function initHscroll( el ) {
		var pin = el.querySelector( ':scope > .vx-hs-pin' ), track = pin && pin.querySelector( ':scope > .vx-hs-track' );
		if ( ! track ) { return; }
		var minW = num( el, 'data-vx-hscroll', 992 ), dist = 0, top = 0, y = 0, on = false;
		addTask( el, {
			measure: function () {
				on = vw >= minW;
				el.classList.toggle( 'vx-hs-on', on );
				if ( ! on ) { el.style.height = ''; track.style.transform = ''; return; }
				dist = Math.max( 0, track.scrollWidth - pin.clientWidth );
				el.style.height = ( dist + vh ) + 'px';
				top = el.getBoundingClientRect().top + w.scrollY;
			},
			read: function () { y = w.scrollY; },
			write: function () {
				if ( ! on ) { return; }
				var p = Math.max( 0, Math.min( 1, ( y - top ) / ( dist || 1 ) ) );
				track.style.transform = 'translate3d(' + ( -p * dist ).toFixed( 1 ) + 'px,0,0)';
			}
		} );
		w.addEventListener( 'load', refresh );
	}

	/* ------------------------------------------------------------ particles */
	function initParticles( el ) {
		var cv = d.createElement( 'canvas' ), ctx = cv.getContext( '2d' );
		if ( ! ctx ) { return; }
		cv.className = 'vx-particles';
		cv.setAttribute( 'aria-hidden', 'true' );
		el.classList.add( 'vx-has-particles' );
		el.insertBefore( cv, el.firstChild );
		var color = el.getAttribute( 'data-vx-particles-color' ) || '#ffffff';
		var dens = num( el, 'data-vx-particles', 60 ), pts = [], W = 0, H = 0, dpr = Math.min( 2, w.devicePixelRatio || 1 ), mx = -9999, my = -9999, live = false;
		function size() {
			W = el.clientWidth; H = el.clientHeight;
			cv.width = W * dpr; cv.height = H * dpr;
			cv.style.width = W + 'px'; cv.style.height = H + 'px';
			ctx.setTransform( dpr, 0, 0, dpr, 0, 0 );
			var n = Math.round( dens * ( W * H ) / ( 1440 * 800 ) );
			pts = [];
			for ( var i = 0; i < n; i++ ) {
				var z = Math.random();
				pts.push( { x: Math.random() * W, y: Math.random() * H, z: z, r: 0.6 + z * 1.8, vy: 0.08 + z * 0.35, ph: Math.random() * 6.28, sw: 0.3 + Math.random() * 0.8 } );
			}
		}
		function draw( t ) {
			if ( ! live ) { return; }
			ctx.clearRect( 0, 0, W, H );
			ctx.fillStyle = color;
			for ( var i = 0; i < pts.length; i++ ) {
				var p = pts[ i ];
				if ( ! reduce ) {
					p.y -= p.vy;
					p.x += Math.sin( t / 1800 * p.sw + p.ph ) * 0.15;
					var dx = p.x - mx, dy = p.y - my, dd = dx * dx + dy * dy;
					if ( dd < 9000 ) { p.x += dx / 60; p.y += dy / 60; }
					if ( p.y < -4 ) { p.y = H + 4; p.x = Math.random() * W; }
				}
				var a = ( 0.25 + p.z * 0.6 ) * ( 0.65 + 0.35 * Math.sin( t / 900 * p.sw + p.ph ) );
				ctx.globalAlpha = Math.max( 0, Math.min( 1, a * Math.min( 1, p.y / ( H * 0.25 ) ) ) );
				ctx.beginPath(); ctx.arc( p.x, p.y, p.r, 0, 6.283 ); ctx.fill();
			}
			ctx.globalAlpha = 1;
			if ( ! reduce ) { w.requestAnimationFrame( draw ); }
		}
		size();
		w.addEventListener( 'resize', size, { passive: true } );
		el.addEventListener( 'pointermove', function ( e ) { var r = el.getBoundingClientRect(); mx = e.clientX - r.left; my = e.clientY - r.top; } );
		el.addEventListener( 'pointerleave', function () { mx = my = -9999; } );
		if ( 'IntersectionObserver' in w ) {
			new IntersectionObserver( function ( list ) {
				var was = live; live = list[ 0 ].isIntersecting;
				if ( live && ! was ) { w.requestAnimationFrame( draw ); }
			} ).observe( el );
		} else { live = true; w.requestAnimationFrame( draw ); }
	}

	/* ------------------------------------------------------------ smooth scrolling */
	// Eases mouse-wheel scrolling only. Keyboard, scrollbar, touch and anything
	// inside a scrollable box keep the browser's own behaviour.
	function initSmooth( ease ) {
		if ( ! fine || reduce ) { return; }
		var target = w.scrollY, cur = w.scrollY, running = false, max = 0;
		function limit() { max = Math.max( 0, html.scrollHeight - vh ); }
		function scrollable( el ) {
			for ( ; el && el !== d.body && el !== html; el = el.parentElement ) {
				var s = getComputedStyle( el );
				if ( /(auto|scroll)/.test( s.overflowY ) && el.scrollHeight > el.clientHeight ) { return true; }
			}
			return false;
		}
		function run() {
			cur += ( target - cur ) * ease;
			if ( Math.abs( target - cur ) < 0.5 ) { cur = target; running = false; }
			w.scrollTo( 0, cur );
			if ( running ) { w.requestAnimationFrame( run ); }
		}
		w.addEventListener( 'wheel', function ( e ) {
			if ( e.ctrlKey || e.defaultPrevented || scrollable( e.target ) || html.classList.contains( 'vx-lock' ) ) { return; }
			e.preventDefault();
			limit();
			if ( ! running ) { cur = target = w.scrollY; }
			var dy = e.deltaY * ( 1 === e.deltaMode ? 40 : ( 2 === e.deltaMode ? vh : 1 ) );
			target = Math.max( 0, Math.min( max, target + dy ) );
			if ( ! running ) { running = true; w.requestAnimationFrame( run ); }
		}, { passive: false } );
		w.addEventListener( 'scroll', function () { if ( ! running ) { cur = target = w.scrollY; } }, { passive: true } );
	}

	/* ------------------------------------------------------------ start */
	function each( sel, fn ) { Array.prototype.forEach.call( d.querySelectorAll( sel ), fn ); }
	function start() {
		html.classList.add( 'vx-mo' );
		if ( reduce ) {
			// Everything simply shows; nothing moves.
			each( '[data-vx-text],[data-vx-kids]', function ( el ) { el.classList.add( 'vx-split', 'vx-kids-ready', 'vx-on' ); } );
		} else {
			each( '[data-vx-text]', initText );
			each( '[data-vx-kids]', initKids );
			each( '[data-vx-para]', initPara );
			each( '[data-vx-stack]', initStack );
			each( '[data-vx-hscroll]', initHscroll );
			if ( fine ) {
				each( '[data-vx-tilt]', initTilt );
				each( '[data-vx-magnet]', initMagnet );
			}
		}
		if ( fine ) { each( '[data-vx-glow]', initGlow ); }
		each( '[data-vx-particles]', initParticles );
		if ( d.body && d.body.hasAttribute( 'data-vx-smooth' ) ) { initSmooth( Math.max( 0.04, Math.min( 0.4, num( d.body, 'data-vx-smooth', 0.12 ) ) ) ); }
		refresh();
		w.addEventListener( 'load', refresh );
	}
	if ( 'loading' === d.readyState ) { d.addEventListener( 'DOMContentLoaded', start ); } else { start(); }
}() );
