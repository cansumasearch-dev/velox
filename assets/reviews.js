/* Velox Google Reviews — front-end slider. Static grids need no JS. */
( function () {
	'use strict';

	function slidesFor( el ) {
		var w = window.innerWidth;
		if ( w <= 560 ) { return parseInt( el.getAttribute( 'data-m' ), 10 ) || 1; }
		if ( w <= 900 ) { return parseInt( el.getAttribute( 'data-t' ), 10 ) || 2; }
		return parseInt( el.getAttribute( 'data-d' ), 10 ) || 3;
	}

	function initSlider( el ) {
		var track = el.querySelector( '.velox-reviews-track' );
		if ( ! track ) { return; }
		var cards = Array.prototype.slice.call( track.children );
		if ( ! cards.length ) { return; }

		var per = slidesFor( el );
		var index = 0;
		var pages = Math.max( 1, Math.ceil( cards.length / per ) );

		function layout() {
			per = slidesFor( el );
			pages = Math.max( 1, Math.ceil( cards.length / per ) );
			var gap = parseFloat( getComputedStyle( track ).gap ) || 16;
			var cardW = ( el.clientWidth - gap * ( per - 1 ) ) / per;
			cards.forEach( function ( c ) { c.style.width = cardW + 'px'; } );
			if ( index >= pages ) { index = pages - 1; }
			go( index );
			buildDots();
		}

		function go( i ) {
			index = Math.max( 0, Math.min( pages - 1, i ) );
			var gap = parseFloat( getComputedStyle( track ).gap ) || 16;
			var cardW = cards[0] ? cards[0].getBoundingClientRect().width : 0;
			var shift = index * per * ( cardW + gap );
			track.style.transform = 'translateX(-' + shift + 'px)';
			updateDots();
		}

		var dotsWrap = null;
		function buildDots() {
			if ( dotsWrap ) { dotsWrap.remove(); dotsWrap = null; }
			if ( pages < 2 ) { return; }
			dotsWrap = document.createElement( 'div' );
			dotsWrap.className = 'velox-reviews-dots';
			for ( var p = 0; p < pages; p++ ) {
				( function ( p ) {
					var b = document.createElement( 'button' );
					b.className = 'velox-reviews-dot' + ( p === index ? ' is-active' : '' );
					b.type = 'button';
					b.setAttribute( 'aria-label', 'Slide ' + ( p + 1 ) );
					b.addEventListener( 'click', function () { go( p ); restart(); } );
					dotsWrap.appendChild( b );
				}( p ) );
			}
			el.appendChild( dotsWrap );
		}
		function updateDots() {
			if ( ! dotsWrap ) { return; }
			Array.prototype.forEach.call( dotsWrap.children, function ( d, i ) {
				d.classList.toggle( 'is-active', i === index );
			} );
		}

		var timer = null;
		function restart() {
			if ( timer ) { clearInterval( timer ); timer = null; }
			if ( el.getAttribute( 'data-autoplay' ) === '1' && pages > 1 ) {
				var speed = parseInt( el.getAttribute( 'data-speed' ), 10 ) || 4000;
				timer = setInterval( function () { go( ( index + 1 ) % pages ); }, speed );
			}
		}

		el.addEventListener( 'mouseenter', function () { if ( timer ) { clearInterval( timer ); timer = null; } } );
		el.addEventListener( 'mouseleave', restart );
		window.addEventListener( 'resize', debounce( layout, 150 ) );

		layout();
		restart();
	}

	function debounce( fn, ms ) {
		var t;
		return function () { clearTimeout( t ); t = setTimeout( fn, ms ); };
	}

	function boot() {
		document.querySelectorAll( '.velox-reviews.is-slider' ).forEach( initSlider );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
