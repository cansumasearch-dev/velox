/* Velox Shop — storefront behaviour: add to cart without a page load, the
 * mini cart drawer, choosing options, quantity steppers, the product
 * gallery and live totals at checkout. Every form also works without it. */
( function () {
	'use strict';
	var C = window.VELOX_SHOP || {}, L = C.i18n || {};
	var state = null;

	function $( s, r ) { return ( r || document ).querySelector( s ); }
	function $$( s, r ) { return [].slice.call( ( r || document ).querySelectorAll( s ) ); }
	function esc( s ) { return String( null == s ? '' : s ).replace( /[&<>"']/g, function ( c ) { return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[ c ]; } ); }
	function hasCart() { return /(?:^|;\s*)velox_cart=/.test( document.cookie ); }

	function rest( path, data ) {
		var o = { method:data ? 'POST' : 'GET', credentials:'same-origin', headers:{ 'Accept':'application/json' } };
		if ( data ) { o.headers[ 'Content-Type' ] = 'application/json'; o.body = JSON.stringify( data ); }
		return fetch( C.rest + path, o ).then( function ( r ) { if ( ! r.ok ) { throw new Error( r.status ); } return r.json(); } );
	}

	var toastT;
	function toast( msg ) {
		var t = $( '[data-vxs-toast]' ); if ( ! t || ! msg ) { return; }
		t.textContent = msg; t.hidden = false;
		requestAnimationFrame( function () { t.classList.add( 'is-on' ); } );
		clearTimeout( toastT ); toastT = setTimeout( function () { t.classList.remove( 'is-on' ); }, 2800 );
	}

	function setCount( n, bump, total ) {
		$$( '[data-vxs-count]' ).forEach( function ( b ) {
			// The "dot" style marks a filled cart without a number.
			b.textContent = b.closest( '.vxs-cc-dot' ) ? '' : n; b.hidden = ! n;
			if ( bump ) { b.classList.remove( 'is-bump' ); void b.offsetWidth; b.classList.add( 'is-bump' ); }
		} );
		if ( undefined !== total ) {
			$$( '[data-vxs-cart-total]' ).forEach( function ( t ) { t.textContent = n ? total : ''; } );
		}
	}

	/* ------------------------------------------------------------ drawer */
	var lastFocus = null;
	function drawerHTML( s ) {
		var h = '<header class="vxs-dr-h"><h2 id="vxs-drawer-t">' + esc( L.cart ) + ( s.count ? ' <span>(' + s.count + ')</span>' : '' ) + '</h2><button type="button" class="vxs-dr-x" data-vxs-close aria-label="' + esc( L.close ) + '">×</button></header>';
		if ( ! s.lines.length ) {
			return h + '<div class="vxs-dr-empty"><p>' + esc( L.empty ) + '</p><button type="button" class="vxs-btn vxs-btn-ghost" data-vxs-close>' + esc( L.close ) + '</button></div>';
		}
		if ( s.freeLeft ) {
			h += '<div class="vxs-dr-free">' + ( '0' === s.freeLeft ? esc( L.freeYes ) : esc( L.freeLeft ).replace( '%s', '<b>' + esc( s.freeLeft ) + '</b>' ) ) + '<i><b style="width:' + ( '0' === s.freeLeft ? 100 : s.freePct ) + '%"></b></i></div>';
		}
		h += '<ul class="vxs-dr-l">' + s.lines.map( function ( l ) {
			return '<li data-key="' + esc( l.key ) + '"><a class="vxs-dr-im" href="' + esc( l.url ) + '" tabindex="-1">' + ( l.thumb ? '<img src="' + esc( l.thumb ) + '" alt="">' : '' ) + '</a>' +
				'<div class="vxs-dr-b"><a href="' + esc( l.url ) + '">' + esc( l.name ) + '</a>' + ( l.variant ? '<small>' + esc( l.variant ) + '</small>' : '' ) + '<small>' + esc( l.unit ) + '</small>' +
				( l.unique ? '' : '<div class="vxs-qty"><button type="button" data-dr-q="-1" aria-label="' + esc( L.fewer ) + '">−</button><input type="number" value="' + l.qty + '" min="0" max="' + l.max + '" data-dr-in aria-label="Qty"><button type="button" data-dr-q="1" aria-label="' + esc( L.more ) + '">+</button></div>' ) + '</div>' +
				'<div class="vxs-dr-r"><b>' + esc( l.total ) + '</b><button type="button" class="vxs-dr-rm" data-dr-rm>' + esc( L.remove ) + '</button></div></li>';
		} ).join( '' ) + '</ul>';
		h += '<footer class="vxs-dr-f">' + ( s.discount ? '<div class="vxs-dr-sum" style="font-size:14px;font-weight:500"><span>' + esc( L.discount ) + ' ' + esc( s.coupon ) + '</span><span>− ' + esc( s.discount ) + '</span></div>' : '' ) +
			'<div class="vxs-dr-sum"><span>' + esc( L.subtotal ) + '</span><span>' + esc( s.subtotal ) + '</span></div><small>' + esc( L.taxNote ) + '</small>' +
			'<a class="vxs-btn vxs-btn-lg vxs-btn-block" href="' + esc( s.checkout ) + '">' + esc( L.checkout ) + '</a><a class="vxs-link" href="' + esc( s.cart ) + '">' + esc( L.viewCart ) + '</a></footer>';
		return h;
	}
	function render( s ) {
		state = s; setCount( s.count, false, s.subtotal );
		var p = $( '.vxs-drawer-p' ); if ( p ) { p.innerHTML = drawerHTML( s ); }
	}
	function openDrawer() {
		var d = $( '[data-vxs-drawer]' ); if ( ! d ) { return false; }
		lastFocus = document.activeElement;
		d.hidden = false;
		requestAnimationFrame( function () { d.classList.add( 'is-open' ); var p = $( '.vxs-drawer-p', d ); p.focus(); } );
		document.documentElement.style.overflow = 'hidden';
		return true;
	}
	function closeDrawer() {
		var d = $( '[data-vxs-drawer]' ); if ( ! d || d.hidden ) { return; }
		d.classList.remove( 'is-open' ); document.documentElement.style.overflow = '';
		setTimeout( function () { d.hidden = true; }, 320 );
		if ( lastFocus && lastFocus.focus ) { lastFocus.focus(); }
	}
	function setLine( key, qty ) {
		var parts = key.split( ':' );
		return rest( 'cart', { op:'set', product:+parts[ 0 ], variant:parts[ 1 ] || '', qty:qty } ).then( function ( s ) { render( s ); if ( s.msg ) { toast( s.msg ); } } );
	}

	/* ------------------------------------------------------------- variants */
	function buyBoxes() {
		$$( '[data-vxs-variants]' ).forEach( function ( w ) {
			var vars = [];
			try { vars = JSON.parse( w.getAttribute( 'data-vxs-variants' ) ) || []; } catch ( e ) {}
			var box = w.closest( '[data-vxs-buy]' ), form = $( 'form', w );
			var radios = $$( '[data-vxs-opt]', w ), nOpts = 0;
			radios.forEach( function ( r ) { nOpts = Math.max( nOpts, +r.getAttribute( 'data-vxs-opt' ) + 1 ); } );
			function chosen() { var c = []; for ( var i = 0; i < nOpts; i++ ) { var r = $( '[data-vxs-opt="' + i + '"]:checked', w ); c.push( r ? r.value : null ); } return c; }
			function update() {
				var c = chosen(), v = null, full = c.every( function ( x ) { return null !== x; } );
				if ( full ) { v = vars.filter( function ( x ) { return x.opts.every( function ( o, i ) { return o === c[ i ]; } ); } )[ 0 ] || null; }
				// grey out values that cannot be combined with what is chosen
				radios.forEach( function ( r ) {
					var i = +r.getAttribute( 'data-vxs-opt' ), test = c.slice(); test[ i ] = r.value;
					var ok = vars.some( function ( x ) { return x.can && x.opts.every( function ( o, j ) { return null === test[ j ] || o === test[ j ]; } ); } );
					r.closest( '.vxs-chip' ).classList.toggle( 'is-na', ! ok );
				} );
				$$( '.vxs-opt', w ).forEach( function ( o, i ) { var l = $( '.vxs-opt-l', o ); if ( ! l._t ) { l._t = l.textContent; } l.innerHTML = esc( l._t ) + ( c[ i ] ? ': <b>' + esc( c[ i ] ) + '</b>' : '' ); } );
				var btn = $( '.vxs-buy-btn', w ), av = $( '[data-vxs-avail]', box ), price = $( '[data-vxs-price]', box ), unit = $( '[data-vxs-unit]', box );
				form.querySelector( '[name="variant"]' ).value = v ? v.id : '';
				if ( v ) {
					if ( price && v.price ) { price.innerHTML = v.price; }
					if ( unit ) { unit.textContent = v.unit || ''; }
					av.textContent = v.av; av.className = 'vxs-avail is-' + v.avk;
					btn.disabled = ! v.can; btn.textContent = v.can ? btn.getAttribute( 'data-vxs-label' ) : v.av;
					var q = $( 'input[name="qty"]', w ); if ( q && 'number' === q.type ) { q.max = v.max; if ( +q.value > v.max ) { q.value = Math.max( 1, v.max ); } }
					if ( v.img ) { var main = document.querySelector( '[data-vxs-main]' ); if ( main ) { main.src = v.img; main.removeAttribute( 'srcset' ); } }
				} else {
					av.textContent = full ? L.unavail : L.choose; av.className = 'vxs-avail ' + ( full ? 'is-na' : 'is-choose' );
					btn.disabled = full; btn.textContent = btn.getAttribute( 'data-vxs-label' );
				}
				w._ready = !! v;
			}
			w.addEventListener( 'change', function ( e ) { if ( e.target.hasAttribute( 'data-vxs-opt' ) ) { update(); } } );
			form.addEventListener( 'submit', function ( e ) {
				if ( ! w._ready ) { e.preventDefault(); e.stopImmediatePropagation(); toast( L.choose ); var first = $$( '.vxs-opt', w ).filter( function ( o ) { return ! $( 'input:checked', o ); } )[ 0 ]; if ( first ) { $( 'input', first ).focus(); } }
			}, true );
			// one value per option: pick it right away
			for ( var i = 0; i < nOpts; i++ ) { var all = $$( '[data-vxs-opt="' + i + '"]', w ); if ( 1 === all.length ) { all[ 0 ].checked = true; } }
			update();
		} );
	}

	/* -------------------------------------------------------------- checkout */
	function checkout() {
		var f = $( '[data-vxs-checkout]' ); if ( ! f ) { return; }
		var src = $( '[data-vxs-src]', f ); if ( src ) { try { src.value = localStorage.getItem( 'vxs_src' ) || ''; } catch ( e ) {} }
		var t = null, seq = 0;
		function country() { var o = $( '[data-vxs-other]', f ); return ( o && o.checked ? $( '[name="vx_s_country"]', f ) : $( '[name="vx_country"]', f ) ).value; }
		function val( n ) { var e = $( '[name="' + n + '"]:checked', f ) || $( '[name="' + n + '"]', f ); return e ? e.value : ''; }
		function quote() {
			var my = ++seq; f.classList.add( 'is-busy' );
			rest( 'quote', { country:country(), ship:val( 'vx_ship' ), coupon:$( '[data-vxs-coupon]', f ) ? $( '[data-vxs-coupon]', f ).value : '', pay:val( 'vx_pay' ) } ).then( function ( r ) {
				if ( my !== seq ) { return; }
				f.classList.remove( 'is-busy' );
				var tot = $( '[data-vxs-totals]', f ); if ( tot ) { tot.innerHTML = r.html; }
				var sh = $( '[data-vxs-ship]', f ); if ( sh ) { var cur = val( 'vx_ship' ); sh.innerHTML = r.ship; var keep = $( '[name="vx_ship"][value="' + cur + '"]', sh ); if ( keep ) { keep.checked = true; } }
				var tl = $( '[data-vxs-total]', f ); if ( tl ) { tl.textContent = r.total; }
				$$( '[data-vxs-pay]', f ).forEach( function ( p ) { var on = r.pay.indexOf( p.getAttribute( 'data-vxs-pay' ) ) > -1; p.hidden = ! on; if ( ! on ) { $( 'input', p ).checked = false; } } );
				if ( ! $( '[name="vx_pay"]:checked', f ) ) { var first = $$( '[data-vxs-pay]:not([hidden]) input', f )[ 0 ]; if ( first ) { first.checked = true; } }
				var cm = $( '[data-vxs-couponmsg]', f ); if ( cm ) { cm.textContent = r.couponMsg || ''; }
			} ).catch( function () { f.classList.remove( 'is-busy' ); } );
		}
		f.addEventListener( 'change', function ( e ) {
			if ( e.target.hasAttribute( 'data-vxs-quote' ) || 'vx_s_country' === e.target.name ) { clearTimeout( t ); t = setTimeout( quote, 120 ); }
			if ( e.target.hasAttribute( 'data-vxs-other' ) ) { var b = $( '[data-vxs-otherbox]', f ); b.hidden = ! e.target.checked; $$( 'input', b ).forEach( function ( i ) { if ( /vx_s_(first|last|line1|zip|city)/.test( i.name ) ) { i.required = e.target.checked; } } ); quote(); }
		} );
		var cb = $( '[data-vxs-couponbtn]', f ); if ( cb ) { cb.addEventListener( 'click', quote ); }
		var ci = $( '[data-vxs-coupon]', f ); if ( ci ) { ci.addEventListener( 'keydown', function ( e ) { if ( 'Enter' === e.key ) { e.preventDefault(); quote(); } } ); }
		f.addEventListener( 'submit', function ( e ) {
			var bad = $$( 'input[required],select[required]', f ).filter( function ( i ) { return ! i.closest( '[hidden]' ) && ( 'checkbox' === i.type ? ! i.checked : ! i.value.trim() || ( 'email' === i.type && ! /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test( i.value ) ) ); } );
			$$( '.vxs-f.is-err,.vxs-check.is-err', f ).forEach( function ( x ) { x.classList.remove( 'is-err' ); } );
			if ( bad.length ) {
				e.preventDefault();
				bad.forEach( function ( i ) { var w = i.closest( '.vxs-f,.vxs-check' ); if ( w ) { w.classList.add( 'is-err' ); } i.setAttribute( 'aria-invalid', 'true' ); } );
				bad[ 0 ].focus(); bad[ 0 ].scrollIntoView( { block:'center', behavior:'smooth' } );
				return;
			}
			var b = $( '[data-vxs-submit]', f ); if ( b ) { b.classList.add( 'is-busy' ); setTimeout( function () { b.disabled = true; }, 0 ); }
		} );
	}

	/* where the visitor came from (shown on the order; first-party only) */
	function source() {
		try {
			var q = new URLSearchParams( location.search ), parts = [];
			[ 'utm_source', 'utm_medium', 'utm_campaign' ].forEach( function ( k ) { if ( q.get( k ) ) { parts.push( q.get( k ) ); } } );
			if ( ! parts.length && document.referrer && document.referrer.indexOf( location.host ) < 0 ) { parts.push( new URL( document.referrer ).host ); }
			if ( parts.length ) { localStorage.setItem( 'vxs_src', parts.join( ' / ' ).slice( 0, 120 ) ); }
		} catch ( e ) {}
	}

	/* --------------------------------------------------------------- events */
	document.addEventListener( 'submit', function ( e ) {
		var f = e.target.closest( '[data-vxs-add]' ); if ( ! f || ! window.fetch ) { return; }
		e.preventDefault();
		var btn = $( '[type="submit"]', f ); if ( btn ) { btn.classList.add( 'is-busy' ); }
		rest( 'cart', { op:'add', product:+f.product.value, variant:f.variant ? f.variant.value : '', qty:+( f.qty ? f.qty.value : 1 ) || 1 } ).then( function ( s ) {
			if ( btn ) { btn.classList.remove( 'is-busy' ); }
			render( s ); setCount( s.count, true, s.subtotal );
			if ( s.msg ) { toast( s.msg ); }
			if ( ! openDrawer() ) { toast( L.added ); }
		} ).catch( function () { f.submit(); } );
	} );
	document.addEventListener( 'click', function ( e ) {
		var t = e.target;
		var cb = t.closest( '[data-vxs-cartbtn]' );
		if ( cb && $( '[data-vxs-drawer]' ) ) {
			e.preventDefault();
			if ( state ) { render( state ); openDrawer(); } else { rest( 'cart' ).then( function ( s ) { render( s ); openDrawer(); } ).catch( function () { location.href = cb.href; } ); }
			return;
		}
		if ( t.closest( '[data-vxs-close]' ) ) { closeDrawer(); return; }
		var st = t.closest( '[data-vxs-step]' );
		if ( st ) {
			var inp = $( 'input', st.parentNode ), n = ( +inp.value || 0 ) + +st.getAttribute( 'data-vxs-step' );
			n = Math.max( +inp.min || 0, Math.min( +inp.max || 999, n ) ); inp.value = n;
			inp.dispatchEvent( new Event( 'change', { bubbles:true } ) );
			return;
		}
		var dq = t.closest( '[data-dr-q]' );
		if ( dq ) { var li = dq.closest( 'li' ), di = $( '[data-dr-in]', li ), q = Math.max( 0, Math.min( +di.max || 999, ( +di.value || 0 ) + +dq.getAttribute( 'data-dr-q' ) ) ); di.value = q; setLine( li.getAttribute( 'data-key' ), q ); return; }
		var rm = t.closest( '[data-dr-rm]' ); if ( rm ) { setLine( rm.closest( 'li' ).getAttribute( 'data-key' ), 0 ); return; }
		var th = t.closest( '[data-vxs-img]' );
		if ( th ) {
			var main = $( '[data-vxs-main]' ); if ( main ) { main.src = th.getAttribute( 'data-vxs-img' ); main.removeAttribute( 'srcset' ); }
			$$( '.vxs-gal-th' ).forEach( function ( x ) { x.classList.toggle( 'is-on', x === th ); } );
		}
	} );
	document.addEventListener( 'change', function ( e ) {
		var t = e.target;
		if ( t.hasAttribute( 'data-dr-in' ) ) { setLine( t.closest( 'li' ).getAttribute( 'data-key' ), Math.max( 0, +t.value || 0 ) ); return; }
		if ( t.hasAttribute( 'data-vxs-autosubmit' ) ) {
			var f = t.form; clearTimeout( t._t );
			t._t = setTimeout( function () { rest( 'cart', { op:'set', product:+f.product.value, variant:f.variant.value, qty:+t.value || 0 } ).then( function () { location.reload(); } ).catch( function () { f.submit(); } ); }, 450 );
		}
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) { closeDrawer(); }
		if ( 'Tab' === e.key ) {
			var d = $( '[data-vxs-drawer]' ); if ( ! d || d.hidden ) { return; }
			var f = $$( 'a[href],button:not([disabled]),input', d ); if ( ! f.length ) { return; }
			var first = f[ 0 ], last = f[ f.length - 1 ];
			if ( e.shiftKey && document.activeElement === first ) { e.preventDefault(); last.focus(); }
			else if ( ! e.shiftKey && document.activeElement === last ) { e.preventDefault(); first.focus(); }
		}
	} );

	source();
	buyBoxes();
	checkout();
	// Cached pages carry an old count: ask once when there is a cart.
	if ( hasCart() ) { rest( 'cart' ).then( render ).catch( function () {} ); } else { setCount( 0 ); }
}() );
