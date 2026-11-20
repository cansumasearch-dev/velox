/**
 * Velox accessibility menu (visitor side).
 *
 * The button and panel live in a shadow root on #vxa11y, so the site's CSS
 * cannot break them and the page effects never reach them. Choices are kept in
 * localStorage ('vxa11y'); the head script in Velox_A11y::boot() puts the
 * class-based ones on <html> before the first paint, this file does the rest
 * (text size, reading guide, read aloud) and the panel.
 */
( function () {
	'use strict';
	var C = window.VELOX_A11Y;
	if ( ! C || document.getElementById( 'vxa11y' ) ) {
		return;
	}
	var T = C.t || {};
	var KEY = 'vxa11y';
	var html = document.documentElement;
	var canSpeak = 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;

	// Levels per tool: how many steps besides "off".
	var LEVELS = { text: 3, spacing: 2, contrast: 3, font: 1, links: 1, motion: 1, images: 1, cursor: 1, guide: 1, read: 1 };
	var FEATS = ( C.features || [] ).filter( function ( f ) {
		return LEVELS[ f ] && ( 'read' !== f || canSpeak );
	} );
	var PROFILES = [
		{ key: 'vision', set: { text: 2, links: 1, cursor: 1 } },
		{ key: 'dyslexia', set: { font: 1, spacing: 1, guide: 1 } },
		{ key: 'seizure', set: { motion: 1, contrast: 3 } },
		{ key: 'focus', set: { motion: 1, guide: 1 } }
	].filter( function ( p ) {
		return Object.keys( p.set ).every( function ( k ) { return FEATS.indexOf( k ) > -1; } );
	} );
	var SCALE = [ 1, 1.15, 1.3, 1.5 ];

	var state = {};
	try { state = JSON.parse( localStorage.getItem( KEY ) || '{}' ) || {}; } catch ( e ) { state = {}; }

	function save() {
		try { localStorage.setItem( KEY, JSON.stringify( state ) ); } catch ( e ) {}
	}
	function active() {
		return Object.keys( state ).some( function ( k ) { return state[ k ]; } );
	}

	/* ---------------------------------------------------------------- page effects */

	function applyClasses() {
		Array.prototype.slice.call( html.classList ).forEach( function ( c ) {
			if ( 0 === c.indexOf( 'vxa-' ) ) { html.classList.remove( c ); }
		} );
		Object.keys( state ).forEach( function ( k ) {
			var v = state[ k ];
			if ( v && 'text' !== k ) { html.classList.add( 'vxa-' + k + ( v > 1 ? '-' + v : '' ) ); }
		} );
	}

	// Text size: every element that carries text gets its own size times the
	// factor. Sizes are measured with no scaling in place, so nested em sizes
	// are never scaled twice.
	var scaled = [];
	var TEXT_SKIP = { SCRIPT: 1, STYLE: 1, NOSCRIPT: 1, SVG: 1, PATH: 1, IMG: 1, VIDEO: 1, IFRAME: 1, CANVAS: 1, BR: 1, HTML: 1, HEAD: 1, META: 1, LINK: 1 };
	function hasText( el ) {
		if ( /^(INPUT|TEXTAREA|SELECT|BUTTON)$/.test( el.tagName ) ) { return true; }
		for ( var n = el.firstChild; n; n = n.nextSibling ) {
			if ( 3 === n.nodeType && n.nodeValue.trim() ) { return true; }
		}
		return false;
	}
	function unscale() {
		scaled.forEach( function ( el ) {
			el.style.removeProperty( 'font-size' );
			el.style.removeProperty( 'line-height' );
			if ( el.__vxaInl ) { el.style.setProperty( 'font-size', el.__vxaInl[ 0 ], el.__vxaInl[ 1 ] ); }
			if ( el.__vxaInl && el.__vxaInl[ 2 ] ) { el.style.setProperty( 'line-height', el.__vxaInl[ 2 ], el.__vxaInl[ 3 ] ); }
		} );
		scaled = [];
	}
	function applyText() {
		unscale();
		var f = SCALE[ state.text || 0 ] || 1;
		if ( 1 === f || ! document.body ) { return; }
		var list = [];
		var all = document.body.getElementsByTagName( '*' );
		for ( var i = 0; i < all.length; i++ ) {
			var el = all[ i ];
			if ( TEXT_SKIP[ el.tagName.toUpperCase() ] || 'vxa11y' === el.id || ! hasText( el ) ) { continue; }
			var cs = getComputedStyle( el );
			var lh = cs.lineHeight;
			list.push( [ el, parseFloat( cs.fontSize ), /px$/.test( lh ) ? parseFloat( lh ) : 0 ] );
		}
		list.forEach( function ( r ) {
			var el = r[ 0 ];
			var inl = el.style.getPropertyValue( 'font-size' );
			var inlLh = el.style.getPropertyValue( 'line-height' );
			el.__vxaInl = ( inl || inlLh ) ? [ inl, el.style.getPropertyPriority( 'font-size' ), inlLh, el.style.getPropertyPriority( 'line-height' ) ] : null;
			el.style.setProperty( 'font-size', ( r[ 1 ] * f ).toFixed( 2 ) + 'px', 'important' );
			if ( r[ 2 ] ) { el.style.setProperty( 'line-height', ( r[ 2 ] * f ).toFixed( 2 ) + 'px', 'important' ); }
			scaled.push( el );
		} );
	}
	var mo = null, moTimer = 0;
	function watchText() {
		if ( ! window.MutationObserver ) { return; }
		if ( state.text && ! mo ) {
			mo = new MutationObserver( function ( recs ) {
				var mine = recs.every( function ( r ) { return r.target === host || ( host && host.contains( r.target ) ); } );
				if ( mine ) { return; }
				clearTimeout( moTimer );
				moTimer = setTimeout( function () { mo.disconnect(); applyText(); mo.observe( document.body, { childList: true, subtree: true } ); }, 400 );
			} );
			mo.observe( document.body, { childList: true, subtree: true } );
		} else if ( ! state.text && mo ) {
			mo.disconnect();
			mo = null;
		}
	}

	function applyMotion() {
		if ( ! state.motion ) { return; }
		Array.prototype.forEach.call( document.querySelectorAll( 'video' ), function ( v ) {
			try { v.pause(); v.removeAttribute( 'autoplay' ); } catch ( e ) {}
		} );
	}

	/* reading guide: a calm window around the pointer, the rest dimmed */
	var guideTop, guideBot;
	function onPointer( e ) {
		var y = e.clientY, h = 64;
		guideTop.style.height = Math.max( 0, y - h ) + 'px';
		guideBot.style.top = ( y + h ) + 'px';
	}
	function applyGuide() {
		var on = !! state.guide;
		guideTop.hidden = guideBot.hidden = ! on;
		window[ on ? 'addEventListener' : 'removeEventListener' ]( 'pointermove', onPointer, { passive: true } );
	}

	/* read aloud: click (or focus) text to hear it */
	var lastLink = null, lastAt = 0;
	function speakText( text ) {
		if ( ! canSpeak || ! text ) { return; }
		window.speechSynthesis.cancel();
		var u = new SpeechSynthesisUtterance( text.replace( /\s+/g, ' ' ).trim().slice( 0, 1500 ) );
		u.lang = C.lang || html.lang || 'de-DE';
		window.speechSynthesis.speak( u );
	}
	function readTarget( el ) {
		return el && el.closest ? el.closest( 'p,h1,h2,h3,h4,h5,h6,li,td,th,dt,dd,blockquote,figcaption,label,a,button,summary,caption' ) : null;
	}
	function onReadClick( e ) {
		if ( ! state.read || ( host && host.contains( e.target ) ) || e.composedPath && e.composedPath().indexOf( host ) > -1 ) { return; }
		var el = readTarget( e.target );
		if ( ! el ) { return; }
		var link = el.closest( 'a[href]' );
		// First click on a link reads it, a second click within 4 s follows it.
		if ( link ) {
			if ( lastLink === link && Date.now() - lastAt < 4000 ) { lastLink = null; return; }
			e.preventDefault();
			lastLink = link;
			lastAt = Date.now();
		}
		speakText( el.innerText || el.textContent || el.getAttribute( 'aria-label' ) );
	}
	function onReadFocus( e ) {
		if ( state.read && ! ( host && host.contains( e.target ) ) ) {
			var el = readTarget( e.target );
			if ( el ) { speakText( el.innerText || el.getAttribute( 'aria-label' ) ); }
		}
	}
	function applyRead() {
		if ( ! state.read && canSpeak ) { window.speechSynthesis.cancel(); }
	}

	function applyAll() {
		applyClasses();
		applyText();
		watchText();
		applyMotion();
		applyGuide();
		applyRead();
		save();
		render();
	}

	/* ---------------------------------------------------------------- the menu */

	var ICON = {
		access: '<circle cx="12" cy="4.2" r="2.2"/><path d="M3.6 7.2c-.6-.2-.9.8-.3 1.1l5.2 1.7V14l-1.8 6.6c-.2.8 1 1.2 1.3.4L10.6 15h2.8l2.6 6.4c.3.8 1.5.4 1.3-.4L15.5 14v-4l5.2-1.7c.6-.3.3-1.3-.3-1.1L12 9.5z"/>',
		text: '<path d="M3 19 8.5 5h2L16 19h-2.3l-1.4-3.8H6.7L5.3 19zm4.4-5.8h4.2L9.5 7.6zM18 5h2v3h3v2h-3v3h-2v-3h-3V8h3z"/>',
		spacing: '<path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h10v2H4zM18 15l3 2.5-3 2.5z"/>',
		contrast: '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20m0 2v16a8 8 0 0 1 0-16"/>',
		font: '<path d="M2 19 6.6 6h2.2L13.4 19h-2.1l-1.2-3.5H5.4L4.1 19zm4-5.3h3.5L7.7 8.5zM17.5 10c2.3 0 3.5 1.1 3.5 3.2V19h-1.9v-1.1c-.6.9-1.5 1.3-2.7 1.3-1.8 0-2.9-1-2.9-2.5 0-1.6 1.2-2.5 3.5-2.5h2v-.3c0-1-.6-1.6-1.8-1.6-.9 0-1.7.3-2.3.8l-.8-1.4c.9-.6 2.1-.9 3.4-.9m-.7 7.6c1 0 1.8-.5 2.1-1.3v-1h-1.8c-1.2 0-1.6.4-1.6 1 0 .8.5 1.3 1.3 1.3"/>',
		links: '<path d="M10.6 13.4a1 1 0 0 1 0-1.4l3.5-3.5a1 1 0 1 1 1.4 1.4L12 13.4a1 1 0 0 1-1.4 0m-3.5 7a5 5 0 0 1-3.5-8.5l2.1-2.1a1 1 0 1 1 1.4 1.4L5 13.3a3 3 0 1 0 4.2 4.2l2.1-2.1a1 1 0 1 1 1.4 1.4l-2.1 2.1a5 5 0 0 1-3.5 1.5m10.6-7.8a1 1 0 0 1-.7-1.7L19 8.8a3 3 0 1 0-4.2-4.2l-2.1 2.1a1 1 0 0 1-1.4-1.4l2.1-2.1a5 5 0 0 1 7 7.1l-2.1 2.1a1 1 0 0 1-.6.2"/>',
		motion: '<path d="M7 5h3v14H7zm7 0h3v14h-3z"/>',
		images: '<path d="M3.3 2 2 3.3l2 2V19a1 1 0 0 0 1 1h13.7l2 2 1.3-1.3zM6 18v-1.6l3-3 2 2 1.2-1.2 3.8 3.8zm14-1.8L7.8 4H19a1 1 0 0 1 1 1z"/>',
		cursor: '<path d="M5 2v17l4.4-4.2 3 6.7 2.8-1.3-3-6.6H18z"/>',
		guide: '<path d="M2 4h20v4H2zm0 12h20v4H2z" opacity=".35"/><path d="M2 10h20v4H2z"/>',
		read: '<path d="M3 9v6h4l5 5V4L7 9zm13.5 3A4.5 4.5 0 0 0 14 8v8a4.5 4.5 0 0 0 2.5-4M14 3.2v2.1a7 7 0 0 1 0 13.4v2.1a9 9 0 0 0 0-17.6"/>',
		close: '<path d="M6.4 5 5 6.4 10.6 12 5 17.6 6.4 19l5.6-5.6 5.6 5.6 1.4-1.4-5.6-5.6L19 6.4 17.6 5 12 10.6z"/>',
		reset: '<path d="M12 5V2L7 6l5 4V7a5 5 0 1 1-5 5H5a7 7 0 1 0 7-7"/>'
	};
	function svg( name, size ) {
		return '<svg viewBox="0 0 24 24" width="' + ( size || 20 ) + '" height="' + ( size || 20 ) + '" fill="currentColor" aria-hidden="true" focusable="false">' + ICON[ name ] + '</svg>';
	}
	function esc( s ) {
		return String( null == s ? '' : s ).replace( /[&<>"]/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ]; } );
	}
	function levelText( f ) {
		var v = state[ f ] || 0;
		var names = T[ 'f_' + f + '_v' ];
		if ( names ) { return names[ v ] || ''; }
		return v ? T.on : T.off;
	}
	function profileOn( p ) {
		return Object.keys( p.set ).every( function ( k ) { return ( state[ k ] || 0 ) === p.set[ k ]; } );
	}

	var CSS = ':host{all:initial}'
		+ '*{box-sizing:border-box}'
		+ '.b{position:fixed;bottom:24px;' + C.side + ':24px;z-index:2147483600;width:56px;height:56px;border-radius:50%;border:0;cursor:pointer;display:grid;place-items:center;color:#fff;background:var(--c);box-shadow:0 10px 30px -8px rgba(0,0,0,.45),0 0 0 3px #fff;transition:transform .15s}'
		+ '.b:hover{transform:scale(1.06)}.b:focus-visible{outline:3px solid #111;outline-offset:4px}'
		+ '.b i{position:absolute;top:2px;' + ( 'left' === C.side ? 'right' : 'left' ) + ':2px;width:14px;height:14px;border-radius:50%;background:#16a34a;border:2px solid #fff}'
		+ '.p{position:fixed;bottom:92px;' + C.side + ':24px;z-index:2147483601;width:min(380px,calc(100vw - 32px));max-height:calc(100vh - 120px);overflow:auto;background:#fff;color:#111827;border-radius:18px;box-shadow:0 24px 60px -12px rgba(0,0,0,.4),0 0 0 1px rgba(0,0,0,.06);font:400 14px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;letter-spacing:0;word-spacing:0;text-align:left}'
		+ '.p[hidden]{display:none}'
		+ '.h{position:sticky;top:0;display:flex;align-items:center;gap:12px;padding:16px 16px 14px 20px;background:var(--c);color:#fff;border-radius:18px 18px 0 0}'
		+ '.h svg{flex:none}.h b{font-size:17px;font-weight:700;flex:1}'
		+ '.x{border:0;background:rgba(255,255,255,.18);color:#fff;width:34px;height:34px;border-radius:50%;cursor:pointer;display:grid;place-items:center}.x:hover{background:rgba(255,255,255,.3)}'
		+ '.in{padding:16px 20px 20px;display:flex;flex-direction:column;gap:18px}'
		+ '.lead{margin:0;color:#4b5563;font-size:13px}'
		+ '.t{margin:0;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#6b7280}'
		+ '.g{display:grid;grid-template-columns:1fr 1fr;gap:8px}'
		+ '.o{appearance:none;border:1.5px solid #e5e7eb;background:#f9fafb;color:#111827;border-radius:12px;padding:12px;cursor:pointer;display:flex;flex-direction:column;align-items:flex-start;gap:6px;text-align:left;font:inherit;min-height:84px;transition:border-color .15s,background .15s}'
		+ '.o:hover{border-color:var(--c)}.o:focus-visible{outline:3px solid var(--c);outline-offset:2px}'
		+ '.o b{font-size:13.5px;font-weight:600;line-height:1.25}.o small{font-size:12px;color:#6b7280;line-height:1.3}'
		+ '.o[aria-pressed=true]{border-color:var(--c);background:var(--cs);}.o[aria-pressed=true] small{color:#111827}'
		+ '.o .ic{color:var(--c)}'
		+ '.d{display:flex;gap:4px;margin-top:auto}.d span{width:16px;height:4px;border-radius:2px;background:#d1d5db}.d span.on{background:var(--c)}'
		+ '.f{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-top:4px;border-top:1px solid #f0f0f0}'
		+ '.r{appearance:none;border:1.5px solid #e5e7eb;background:#fff;color:#111827;border-radius:999px;padding:9px 14px;cursor:pointer;display:inline-flex;align-items:center;gap:8px;font:600 13px/1 system-ui,sans-serif}.r:hover{border-color:#111827}.r:focus-visible{outline:3px solid var(--c);outline-offset:2px}'
		+ '.s{color:var(--ct);font-size:13px;font-weight:600}'
		+ '.n{margin:0;font-size:12px;color:#6b7280}'
		+ '.gt,.gb{position:fixed;left:0;right:0;background:rgba(0,0,0,.55);pointer-events:none;z-index:2147483590}.gt{top:0}.gb{bottom:0}'
		+ '@media (max-width:767px){' + ( C.mobile ? '.b{bottom:16px;' + C.side + ':16px;width:52px;height:52px}.p{' + C.side + ':16px;bottom:80px}' : '.b,.p{display:none!important}' ) + '}'
		+ '@media (prefers-reduced-motion:reduce){.b,.o{transition:none}}';

	var host = document.createElement( 'div' );
	host.id = 'vxa11y';
	var root = host.attachShadow ? host.attachShadow( { mode: 'open' } ) : host;
	var col = /^#[0-9a-f]{6}$/i.test( C.color || '' ) ? C.color : ( /^#[0-9a-f]{3}$/i.test( C.color || '' ) ? '#' + C.color.slice( 1 ).replace( /./g, '$&$&' ) : '#1d4ed8' );
	host.style.setProperty( '--c', col );
	host.style.setProperty( '--cs', col + '1a' );
	host.style.setProperty( '--ct', col );

	function render() {
		if ( ! panel ) { return; }
		var h = '<div class="h">' + svg( 'access', 24 ) + '<b id="vxa-t">' + esc( T.title ) + '</b><button type="button" class="x" data-x aria-label="' + esc( T.close ) + '">' + svg( 'close', 18 ) + '</button></div><div class="in"><p class="lead">' + esc( T.intro ) + '</p>';
		if ( C.profiles && PROFILES.length ) {
			h += '<p class="t">' + esc( T.profiles ) + '</p><div class="g">';
			PROFILES.forEach( function ( p ) {
				h += '<button type="button" class="o" data-p="' + p.key + '" aria-pressed="' + profileOn( p ) + '"><b>' + esc( T[ 'p_' + p.key ] ) + '</b><small>' + esc( T[ 'p_' + p.key + '_d' ] ) + '</small></button>';
			} );
			h += '</div>';
		}
		h += '<p class="t">' + esc( T.tools ) + '</p><div class="g">';
		FEATS.forEach( function ( f ) {
			var v = state[ f ] || 0, max = LEVELS[ f ], dots = '';
			if ( max > 1 ) {
				for ( var i = 1; i <= max; i++ ) { dots += '<span class="' + ( i <= v ? 'on' : '' ) + '"></span>'; }
				dots = '<span class="d" aria-hidden="true">' + dots + '</span>';
			}
			h += '<button type="button" class="o" data-f="' + f + '" aria-pressed="' + ( v > 0 ) + '"><span class="ic">' + svg( f, 22 ) + '</span><b>' + esc( T[ 'f_' + f ] ) + '</b><small>' + esc( 'read' === f && v ? T.read_hint : levelText( f ) ) + '</small>' + dots + '</button>';
		} );
		h += '</div><div class="f"><button type="button" class="r" data-reset>' + svg( 'reset', 16 ) + esc( T.reset ) + '</button>'
			+ ( C.statement ? '<a class="s" href="' + esc( C.statement ) + '">' + esc( T.statement ) + '</a>' : '' ) + '</div>'
			+ '<p class="n">' + esc( T.note ) + '</p></div>';
		var keep = root.activeElement && ( root.activeElement.getAttribute( 'data-f' ) || root.activeElement.getAttribute( 'data-p' ) );
		panel.innerHTML = h;
		if ( keep ) {
			var again = panel.querySelector( '[data-f="' + keep + '"],[data-p="' + keep + '"]' );
			if ( again ) { again.focus(); }
		}
		btn.querySelector( 'i' ).hidden = ! active();
	}

	var style = document.createElement( 'style' );
	style.textContent = CSS;
	var btn = document.createElement( 'button' );
	btn.type = 'button';
	btn.className = 'b';
	btn.setAttribute( 'aria-label', T.open );
	btn.setAttribute( 'title', T.open );
	btn.setAttribute( 'aria-expanded', 'false' );
	btn.setAttribute( 'aria-controls', 'vxa-p' );
	btn.innerHTML = svg( 'access', 30 ) + '<i hidden></i>';
	var panel = document.createElement( 'div' );
	panel.className = 'p';
	panel.id = 'vxa-p';
	panel.hidden = true;
	panel.setAttribute( 'role', 'dialog' );
	panel.setAttribute( 'aria-labelledby', 'vxa-t' );
	guideTop = document.createElement( 'div' );
	guideTop.className = 'gt';
	guideTop.hidden = true;
	guideBot = document.createElement( 'div' );
	guideBot.className = 'gb';
	guideBot.hidden = true;
	[ style, guideTop, guideBot, btn, panel ].forEach( function ( n ) { root.appendChild( n ); } );

	function open( on ) {
		panel.hidden = ! on;
		btn.setAttribute( 'aria-expanded', on ? 'true' : 'false' );
		if ( on ) {
			render();
			var first = panel.querySelector( '.o' );
			if ( first ) { first.focus(); }
		} else {
			btn.focus();
		}
	}
	btn.addEventListener( 'click', function () { open( panel.hidden ); } );
	panel.addEventListener( 'click', function ( e ) {
		var t = e.target.closest( 'button' );
		if ( ! t ) { return; }
		if ( t.hasAttribute( 'data-x' ) ) { open( false ); return; }
		if ( t.hasAttribute( 'data-reset' ) ) { state = {}; applyAll(); return; }
		var f = t.getAttribute( 'data-f' );
		if ( f ) {
			state[ f ] = ( ( state[ f ] || 0 ) + 1 ) % ( LEVELS[ f ] + 1 );
			if ( ! state[ f ] ) { delete state[ f ]; }
			applyAll();
			return;
		}
		var pk = t.getAttribute( 'data-p' );
		if ( pk ) {
			var p = PROFILES.filter( function ( x ) { return x.key === pk; } )[ 0 ];
			var on = profileOn( p );
			Object.keys( p.set ).forEach( function ( k ) {
				if ( on ) { delete state[ k ]; } else { state[ k ] = p.set[ k ]; }
			} );
			applyAll();
		}
	} );
	root.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && ! panel.hidden ) { e.stopPropagation(); open( false ); }
	} );
	document.addEventListener( 'click', function ( e ) {
		if ( ! panel.hidden && e.composedPath && e.composedPath().indexOf( host ) < 0 ) { panel.hidden = true; btn.setAttribute( 'aria-expanded', 'false' ); }
	} );
	document.addEventListener( 'click', onReadClick, true );
	document.addEventListener( 'focusin', onReadFocus );

	function start() {
		document.body.appendChild( host );
		applyAll();
	}
	if ( document.body ) { start(); } else { document.addEventListener( 'DOMContentLoaded', start ); }
}() );
