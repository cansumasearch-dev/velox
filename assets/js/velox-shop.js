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
		if ( C.nonce ) { o.headers[ 'X-WP-Nonce' ] = C.nonce; }
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
				var al = $( '[data-vxs-alert]', box );
				if ( al ) { al.hidden = ! ( v && ! v.can ); al.variant.value = v ? v.id : ''; }
				var sb = $( '[data-vxs-sbar]' );
				if ( sb && box.closest( '.vxs-product' ) ) {
					var sp = $( '[data-vxs-sbar-price]', sb ), sg = $( '[data-vxs-sbar-go]', sb );
					if ( sp && v && v.price ) { var tmp = document.createElement( 'div' ); tmp.innerHTML = v.price; sp.textContent = tmp.textContent.replace( /\s+/g, ' ' ).trim(); }
					if ( sg && v ) { sg.disabled = ! v.can; sg.textContent = v.can ? btn.getAttribute( 'data-vxs-label' ) : v.av; }
				}
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
			if ( btn && btn.classList.contains( 'vxs-buy-btn' ) && L.addAgain ) { btn.textContent = L.addAgain; }
			if ( 'cart' === C.after && s.cart ) { location.href = s.cart; return; }
			if ( 'stay' === C.after || ! openDrawer() ) { toast( L.added ); }
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

	// Account: copy the recovery codes.
	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest && e.target.closest( '[data-vxa-copy]' );
		if ( ! b || ! navigator.clipboard ) { return; }
		navigator.clipboard.writeText( b.getAttribute( 'data-vxa-copy' ) ).then( function () { b.classList.add( 'is-done' ); b.lastChild.textContent = b.getAttribute( 'data-done' ) || '✓'; } );
	} );

	/* ------------------------------------------------------ grid: sort, search, filters, more */
	function gridTools() {
		$$( '[data-vxs-gridwrap]' ).forEach( function ( wrap ) {
			var form = $( '[data-vxs-tools]', wrap );
			var n = wrap.getAttribute( 'data-vxs-gridwrap' ), busy = 0;
			function load( url, mode ) {
				var my = ++busy; wrap.classList.add( 'is-busy' ); wrap.setAttribute( 'aria-busy', 'true' );
				var before = $$( '.vxs-card', wrap ).length;
				// Remember which filter was open and where the cursor was.
				var open = form && $( '.vxs-fdd[open]', form ), openKey = open ? open.getAttribute( 'data-fk' ) : '';
				var act = document.activeElement, actSel = act && form && form.contains( act ) && act.name ? '[name="' + act.name + '"]' + ( 'checkbox' === act.type ? '[value="' + String( act.value ).replace( /"/g, '\\"' ) + '"]' : '' ) : '';
				return fetch( url, { credentials:'same-origin', headers:{ 'X-Requested-With':'velox-shop' } } ).then( function ( r ) { return r.text(); } ).then( function ( html ) {
					if ( my !== busy ) { return; }
					var doc = new DOMParser().parseFromString( html, 'text/html' );
					var fresh = doc.querySelector( '[data-vxs-gridwrap="' + n + '"]' );
					if ( ! fresh ) { location.href = url; return; }
					// Keep the toolbar (and the cursor in the search field); swap everything else.
					$$( ':scope > :not([data-vxs-tools])', wrap ).forEach( function ( x ) { x.remove(); } );
					$$( ':scope > :not([data-vxs-tools])', fresh ).forEach( function ( x ) { wrap.appendChild( document.importNode( x, true ) ); } );
					var nf = $( '[data-vxs-tools]', fresh );
					if ( form && nf ) {
						[ '.vxs-cats', '[data-vxs-fbar]', '[data-vxs-factive]' ].forEach( function ( sel ) {
							var o = $( sel, form ), nw = $( sel, nf );
							if ( o && nw ) { o.replaceWith( document.importNode( nw, true ) ); }
							else if ( o ) { o.remove(); }
							else if ( nw ) { var anchor = $( 'button[type="submit"].screen-reader-text', form ); form.insertBefore( document.importNode( nw, true ), anchor ); }
						} );
						var ot = $( '.vxs-count-txt', form ), nt = $( '.vxs-count-txt', nf ); if ( ot && nt ) { ot.textContent = nt.textContent; }
						var oh = $( 'input[name="vxcat"]', form ), nh = $( 'input[name="vxcat"]', nf );
						if ( oh ) { oh.remove(); } if ( nh ) { form.insertBefore( document.importNode( nh, true ), form.firstChild ); }
						if ( openKey ) { var again = $( '.vxs-fdd[data-fk="' + openKey + '"]', form ); if ( again ) { again.open = true; } }
						if ( actSel ) { var back = $( actSel, form ); if ( back && back !== document.activeElement ) { back.focus( { preventScroll:true } ); } }
					}
					wrap.classList.remove( 'is-busy' ); wrap.removeAttribute( 'aria-busy' );
					history[ 'more' === mode ? 'replaceState' : 'pushState' ]( { vxs:n }, '', url );
					if ( 'more' === mode ) { var c = $$( '.vxs-card', wrap )[ before ]; var a = c && ( $( '.vxs-card-t a', c ) || $( 'a:not([tabindex="-1"])', c ) ); if ( a ) { a.focus( { preventScroll:true } ); } }
					marks();
				} ).catch( function () { location.href = url; } );
			}
			// Everything the toolbar holds, as the address (other parameters stay).
			function formUrl() {
				var u = new URL( location.href );
				Array.from( u.searchParams.keys() ).filter( function ( k ) { return 'vxcat' === k || 0 === k.indexOf( 'vxs_' ); } ).forEach( function ( k ) { u.searchParams.delete( k ); } );
				new FormData( form ).forEach( function ( v, k ) {
					v = String( v ).trim();
					if ( '' === v || ( 'vxs_sort' === k && 'menu' === v ) ) { return; }
					u.searchParams.append( k, v );
				} );
				return u.toString();
			}
			wrap.addEventListener( 'click', function ( e ) {
				var a = e.target.closest( '[data-vxs-more],[data-vxs-nav]' );
				if ( ! a || e.metaKey || e.ctrlKey || e.shiftKey || 0 !== e.button ) { return; }
				e.preventDefault();
				if ( a.hasAttribute( 'data-vxs-more' ) ) { a.classList.add( 'is-busy' ); a.textContent = L.loading || a.textContent; }
				load( a.href, a.hasAttribute( 'data-vxs-more' ) ? 'more' : 'nav' );
			} );
			if ( ! form ) { return; }
			var qt = null;
			form.addEventListener( 'submit', function ( e ) { e.preventDefault(); load( formUrl(), 'nav' ); } );
			form.addEventListener( 'change', function ( e ) {
				var t = e.target;
				if ( 'vxs_q' === t.name ) { return; }
				if ( 'vxs_min' === t.name || 'vxs_max' === t.name ) { return; } // applied with the button or Enter
				load( formUrl(), 'nav' );
			} );
			form.addEventListener( 'input', function ( e ) {
				if ( 'vxs_q' !== e.target.name ) { return; }
				clearTimeout( qt ); qt = setTimeout( function () { load( formUrl(), 'nav' ); }, 380 );
			} );
			// One filter open at a time; Esc or a click elsewhere closes it.
			form.addEventListener( 'toggle', function ( e ) {
				if ( e.target.classList && e.target.classList.contains( 'vxs-fdd' ) && e.target.open ) { $$( '.vxs-fdd[open]', form ).forEach( function ( d ) { if ( d !== e.target ) { d.open = false; } } ); }
			}, true );
			document.addEventListener( 'click', function ( e ) { if ( ! e.target.closest( '.vxs-fdd' ) ) { $$( '.vxs-fdd[open]', form ).forEach( function ( d ) { d.open = false; } ); } } );
			form.addEventListener( 'keydown', function ( e ) {
				if ( 'Escape' === e.key ) { var d = e.target.closest( '.vxs-fdd[open]' ); if ( d ) { d.open = false; $( 'summary', d ).focus(); } }
			} );
		} );
		window.addEventListener( 'popstate', function ( e ) { if ( e.state && e.state.vxs ) { location.reload(); } } );
	}

	/* ------------------------------------------------------------- wishlist */
	var WISH = null;
	function wishLocal() { try { return JSON.parse( localStorage.getItem( 'vxs_wish' ) || '[]' ).map( Number ).filter( Boolean ); } catch ( e ) { return []; } }
	function wishStore( ids ) { try { localStorage.setItem( 'vxs_wish', JSON.stringify( ids.slice( -200 ) ) ); } catch ( e ) {} }
	function marks() {
		if ( ! WISH ) { return; }
		$$( '[data-vxs-wish]' ).forEach( function ( b ) {
			var on = WISH.indexOf( +b.getAttribute( 'data-vxs-wish' ) ) > -1;
			b.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			b.title = on ? ( L.wishSaved || '' ) : ( L.wishSave || '' );
		} );
		$$( '[data-vxs-wishn]' ).forEach( function ( x ) { x.textContent = WISH.length; } );
	}
	function wishlist() {
		var signed = Array.isArray( C.wish );
		WISH = signed ? C.wish.map( Number ) : wishLocal();
		// Saved as a guest, then signed in: move the list into the account.
		if ( signed && wishLocal().length ) {
			rest( 'wish', { merge:wishLocal() } ).then( function ( r ) { if ( r.ids ) { WISH = r.ids.map( Number ); wishStore( [] ); marks(); } } ).catch( function () {} );
		}
		marks();
		document.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '[data-vxs-wish]' ); if ( ! b ) { return; }
			e.preventDefault();
			var id = +b.getAttribute( 'data-vxs-wish' ), on = WISH.indexOf( id ) < 0;
			WISH = on ? WISH.concat( [ id ] ) : WISH.filter( function ( x ) { return x !== id; } );
			marks();
			b.classList.remove( 'is-pop' ); void b.offsetWidth; if ( on ) { b.classList.add( 'is-pop' ); }
			if ( signed ) { rest( 'wish', { id:id, on:on ? 1 : 0 } ).then( function ( r ) { if ( r.ids ) { WISH = r.ids.map( Number ); marks(); } } ).catch( function () { toast( L.failed ); } ); }
			else { wishStore( WISH ); }
			toastLink( on ? L.wishAdd : L.wishDel, on ? L.wishView : '', C.account );
			// On the wishlist page itself, a removed product leaves the list.
			var page = b.closest( '[data-vxs-wishlist]' ); if ( page && ! on ) { var card = b.closest( '.vxs-card' ); if ( card ) { card.remove(); } }
		} );
		var page = $( '[data-vxs-wishlist][data-guest]' );
		if ( page ) {
			fetch( C.rest + 'cards?ids=' + encodeURIComponent( WISH.join( ',' ) ), { credentials:'same-origin' } ).then( function ( r ) { return r.json(); } ).then( function ( r ) { page.innerHTML = r.html; marks(); } ).catch( function () { page.innerHTML = ''; } );
		}
	}
	function toastLink( msg, label, href ) {
		toast( msg );
		var t = $( '[data-vxs-toast]' ); if ( ! t || ! label || ! href ) { return; }
		var a = document.createElement( 'a' ); a.href = href; a.textContent = label; a.className = 'vxs-toast-a'; t.appendChild( document.createTextNode( ' ' ) ); t.appendChild( a );
		clearTimeout( toastT ); toastT = setTimeout( function () { t.classList.remove( 'is-on' ); }, 5000 );
	}

	/* --------------------------------------------- colour dots: show that colour */
	function swatches() {
		document.addEventListener( 'mouseover', function ( e ) {
			var sw = e.target.closest && e.target.closest( '[data-vxs-swimg]' ); if ( ! sw ) { return; }
			var card = sw.closest( '.vxs-card' ), im = card && $( '.vxs-card-im', card ); if ( ! im ) { return; }
			if ( ! im._orig ) { im._orig = [ im.src, im.getAttribute( 'srcset' ) ]; }
			im.removeAttribute( 'srcset' ); im.src = sw.getAttribute( 'data-vxs-swimg' );
			card.classList.add( 'is-sw' );
		} );
		document.addEventListener( 'mouseout', function ( e ) {
			var sws = e.target.closest && e.target.closest( '.vxs-sws' ); if ( ! sws || sws.contains( e.relatedTarget ) ) { return; }
			var card = sws.closest( '.vxs-card' ), im = card && $( '.vxs-card-im', card ); if ( ! im || ! im._orig ) { return; }
			im.src = im._orig[ 0 ]; if ( im._orig[ 1 ] ) { im.setAttribute( 'srcset', im._orig[ 1 ] ); }
			card.classList.remove( 'is-sw' );
		} );
	}

	/* ---------------------------------- notify-me and review forms (REST, no reload) */
	function restForm( sel, path, done ) {
		document.addEventListener( 'submit', function ( e ) {
			var f = e.target.closest( sel ); if ( ! f || ! window.fetch ) { return; }
			e.preventDefault();
			var data = {}; new FormData( f ).forEach( function ( v, k ) { data[ k ] = v; } );
			var btn = $( '[type="submit"]', f ), msg = $( '[role="status"]', f );
			if ( btn ) { btn.disabled = true; btn.classList.add( 'is-busy' ); }
			rest( path, data ).then( function ( r ) {
				if ( btn ) { btn.disabled = false; btn.classList.remove( 'is-busy' ); }
				if ( msg ) { msg.textContent = r.msg || ''; msg.className = msg.className.replace( / is-(ok|bad)/g, '' ) + ( r.bad ? ' is-bad' : ' is-ok' ); }
				if ( ! r.bad && done ) { done( f ); }
			} ).catch( function () { if ( btn ) { btn.disabled = false; btn.classList.remove( 'is-busy' ); } if ( msg ) { msg.textContent = L.failed; } } );
		} );
	}

	/* ------------------------------------------ checkout: remind me (with consent) */
	function remind() {
		var box = $( '[data-vxs-remind]' ); if ( ! box ) { return; }
		var f = box.form, mail = f && f.querySelector( '[name="vx_email"]' ); if ( ! mail ) { return; }
		function send() { if ( /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test( mail.value.trim() ) ) { rest( 'remember', { email:mail.value.trim(), consent:box.checked ? 1 : 0 } ).catch( function () {} ); } }
		box.addEventListener( 'change', send );
		mail.addEventListener( 'change', function () { if ( box.checked ) { send(); } } );
	}

	/* -------------------------------------------------------- photos full screen */
	function lightbox() {
		var lb = null, list = [], at = 0, back = null, sx = 0;
		function show( i ) {
			at = ( i + list.length ) % list.length;
			var img = $( 'img', lb ); img.src = list[ at ].src; img.alt = list[ at ].alt || '';
			$( '.vxs-lb-c', lb ).textContent = list.length > 1 ? ( L.photo || '%1$d / %2$d' ).replace( '%1$d', at + 1 ).replace( '%2$d', list.length ) : '';
			// the next photo is ready before it is asked for
			if ( list.length > 1 ) { ( new Image() ).src = list[ ( at + 1 ) % list.length ].src; }
		}
		function close() {
			if ( ! lb ) { return; }
			lb.classList.remove( 'is-on' ); document.documentElement.style.overflow = '';
			var o = lb; lb = null; setTimeout( function () { o.remove(); }, 200 );
			if ( back ) { back.focus(); }
		}
		function open( g, i, from ) {
			try { list = JSON.parse( g.getAttribute( 'data-vxs-zoom' ) ) || []; } catch ( e ) { list = []; }
			if ( ! list.length ) { return; }
			back = from;
			var arrow = function ( d ) { return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' + d + '"/></svg>'; };
			lb = document.createElement( 'div' );
			lb.className = 'vxs-lb'; lb.setAttribute( 'role', 'dialog' ); lb.setAttribute( 'aria-modal', 'true' ); lb.setAttribute( 'aria-label', L.zoom || '' );
			lb.innerHTML = '<img alt="">' + '<button type="button" class="vxs-lb-x" aria-label="' + esc( L.close ) + '">' + arrow( 'M6 6l12 12M18 6 6 18' ) + '</button>'
				+ ( list.length > 1 ? '<button type="button" class="vxs-lb-p" aria-label="' + esc( L.prev ) + '">' + arrow( 'M15 18l-6-6 6-6' ) + '</button><button type="button" class="vxs-lb-n" aria-label="' + esc( L.next ) + '">' + arrow( 'M9 18l6-6-6-6' ) + '</button>' : '' )
				+ '<span class="vxs-lb-c" aria-live="polite"></span>';
			document.body.appendChild( lb ); document.documentElement.style.overflow = 'hidden';
			show( i );
			requestAnimationFrame( function () { lb.classList.add( 'is-on' ); $( '.vxs-lb-x', lb ).focus(); } );
			lb.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( '.vxs-lb-p' ) ) { show( at - 1 ); } else if ( e.target.closest( '.vxs-lb-n' ) ) { show( at + 1 ); } else if ( e.target.closest( '.vxs-lb-x' ) || e.target === lb ) { close(); }
			} );
			lb.addEventListener( 'touchstart', function ( e ) { sx = e.touches[ 0 ].clientX; }, { passive:true } );
			lb.addEventListener( 'touchend', function ( e ) { var dx = e.changedTouches[ 0 ].clientX - sx; if ( Math.abs( dx ) > 50 && list.length > 1 ) { show( at + ( dx < 0 ? 1 : -1 ) ); } } );
		}
		$$( '[data-vxs-zoom]' ).forEach( function ( g ) {
			$$( '.vxs-gal-stage img,.vxs-gal-item img', g ).forEach( function ( img ) { img.setAttribute( 'tabindex', '0' ); img.setAttribute( 'role', 'button' ); img.setAttribute( 'aria-label', L.zoom || '' ); } );
		} );
		function start( e ) {
			var img = e.target.closest && e.target.closest( '[data-vxs-zoom] .vxs-gal-stage img,[data-vxs-zoom] .vxs-gal-item img' );
			if ( ! img ) { return false; }
			var g = img.closest( '[data-vxs-zoom]' ), i = 0;
			var item = img.closest( '.vxs-gal-item' );
			if ( item ) { i = $$( '.vxs-gal-item', g ).indexOf( item ); } else { var th = $$( '.vxs-gal-th', g ), on = $( '.vxs-gal-th.is-on', g ); i = on ? th.indexOf( on ) : 0; }
			open( g, Math.max( 0, i ), img );
			return true;
		}
		document.addEventListener( 'click', function ( e ) { if ( start( e ) ) { e.preventDefault(); } } );
		document.addEventListener( 'keydown', function ( e ) {
			if ( lb ) {
				if ( 'Escape' === e.key ) { e.stopPropagation(); close(); } else if ( 'ArrowLeft' === e.key && list.length > 1 ) { show( at - 1 ); } else if ( 'ArrowRight' === e.key && list.length > 1 ) { show( at + 1 ); } else if ( 'Tab' === e.key ) {
					var f = $$( 'button', lb ), first = f[ 0 ], last = f[ f.length - 1 ];
					if ( e.shiftKey && document.activeElement === first ) { e.preventDefault(); last.focus(); } else if ( ! e.shiftKey && document.activeElement === last ) { e.preventDefault(); first.focus(); }
				}
				return;
			}
			if ( ( 'Enter' === e.key || ' ' === e.key ) && start( e ) ) { e.preventDefault(); }
		}, true );
	}

	/* --------------------------------------------------------------- buy bar */
	function buyBar() {
		var bar = $( '[data-vxs-sbar]' ), main = $( '.vxs-product .vxs-buy-btn' ) || $( '.vxs-product .vxs-buy' );
		if ( ! bar || ! main || ! window.IntersectionObserver ) { return; }
		var go = $( '[data-vxs-sbar-go]', bar );
		function set( on ) {
			bar.classList.toggle( 'is-on', on ); bar.setAttribute( 'aria-hidden', on ? 'false' : 'true' );
			if ( go ) { go.tabIndex = on ? 0 : -1; }
			document.documentElement.classList.toggle( 'vxs-sbar-on', on );
		}
		new IntersectionObserver( function ( en ) {
			var e = en[ 0 ]; set( ! e.isIntersecting && e.boundingClientRect.top < 0 );
		} ).observe( main );
		if ( go ) {
			go.addEventListener( 'click', function () {
				var w = $( '.vxs-product [data-vxs-variants]' ), btn = $( '.vxs-product .vxs-buy-btn' );
				if ( btn && ! btn.disabled && ( ! w || w._ready ) ) { btn.click(); return; }
				var box = $( '.vxs-product [data-vxs-buy]' ); if ( ! box ) { return; }
				box.scrollIntoView( { block:'center', behavior:matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth' } );
				var first = w && $$( '.vxs-opt', w ).filter( function ( o ) { return ! $( 'input:checked', o ); } )[ 0 ];
				setTimeout( function () { var t = first ? $( 'input', first ) : btn; if ( t ) { t.focus( { preventScroll:true } ); } }, 350 );
			} );
		}
	}

	/* ------------------------------------------------------------ notice bar */
	function notice() {
		var bar = $( '[data-vxs-notice]' ); if ( ! bar || bar.hidden ) { return; }
		var x = $( '[data-vxs-notice-x]', bar ), m = $$( '.vxs-notice-m', bar ), i = 0, paused = false;
		if ( x ) { x.addEventListener( 'click', function () { bar.hidden = true; try { sessionStorage.setItem( 'vxs_notice', bar.getAttribute( 'data-vxs-notice' ) ); } catch ( e ) {} } ); }
		if ( m.length < 2 ) { return; }
		bar.addEventListener( 'mouseenter', function () { paused = true; } ); bar.addEventListener( 'mouseleave', function () { paused = false; } );
		bar.addEventListener( 'focusin', function () { paused = true; } ); bar.addEventListener( 'focusout', function () { paused = false; } );
		setInterval( function () {
			if ( paused || document.hidden ) { return; }
			m[ i ].hidden = true; i = ( i + 1 ) % m.length; m[ i ].hidden = false;
		}, 5000 );
	}

	/* -------------------------------------------- account menu: centred under its icon, never off-screen */
	function accMenus() {
		$$( '.vxa-menu' ).forEach( function ( m ) {
			var pop = $( '.vxa-pop', m ); if ( ! pop ) { return; }
			function place() {
				pop.style.setProperty( '--vxa-dx', '0px' );
				var r = m.getBoundingClientRect(), w = pop.offsetWidth, c = r.left + r.width / 2, vw = document.documentElement.clientWidth, pad = 12, dx = 0;
				if ( c + w / 2 > vw - pad ) { dx = vw - pad - ( c + w / 2 ); }
				if ( c - w / 2 + dx < pad ) { dx = pad - ( c - w / 2 ); }
				pop.style.setProperty( '--vxa-dx', Math.round( dx ) + 'px' );
			}
			m.addEventListener( 'mouseenter', place ); m.addEventListener( 'focusin', place );
		} );
	}

	source();
	buyBoxes();
	accMenus();
	wishlist();
	swatches();
	remind();
	restForm( '[data-vxs-alert]', 'alert', function ( f ) { var b = $( '[type="submit"]', f ); if ( b ) { b.disabled = true; } } );
	restForm( '[data-vxs-review]', 'review', function ( f ) { f.reset(); [].forEach.call( f.querySelectorAll( 'input,textarea,button' ), function ( x ) { if ( 'checkbox' !== x.type ) { x.disabled = true; } } ); } );
	checkout();
	gridTools();
	lightbox();
	buyBar();
	notice();
	// Cached pages carry an old count: ask once when there is a cart.
	if ( hasCart() ) { rest( 'cart' ).then( render ).catch( function () {} ); } else { setCount( 0 ); }
}() );
