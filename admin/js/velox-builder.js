/**
 * Velox Builder — standalone editor entry (foundation).
 *
 * Served on the full-screen editor route. This foundation version mounts the
 * editor shell (top bar, layers spine, canvas, inspector) so the route opens
 * as a real screen. The engine that was proven in the spike — central store,
 * live CSS injection, cascade resolver — is wired into this shell in the next
 * pass; the mount points and layout are established here.
 */
( function () {
	'use strict';

	var CFG = window.VELOX_BUILDER || {};
	var T = window.veloxT || function ( s ) { return s; };

	function el( tag, cls, html ) {
		var n = document.createElement( tag );
		if ( cls ) { n.className = cls; }
		if ( html != null ) { n.innerHTML = html; }
		return n;
	}

	function mount() {
		var root = document.getElementById( 'velox-builder-root' );
		if ( ! root ) { return; }
		root.innerHTML = '';
		root.className = 'vb-app';

		// Top bar
		var bar = el( 'div', 'vb-topbar' );
		bar.innerHTML =
			'<div class="vb-brand"><span class="vb-brand-m">V</span><b>Velox Builder</b></div>' +
			'<a class="vb-back" href="' + ( CFG.backUrl || '#' ) + '">&larr; ' + T( 'Exit' ) + '</a>' +
			'<div class="vb-spring"></div>' +
			'<button class="vb-publish">' + T( 'Publish' ) + '</button>';

		// Body: spine | canvas | inspector
		var body = el( 'div', 'vb-body' );
		body.innerHTML =
			'<aside class="vb-spine"><div class="vb-spine-h">' + T( 'Layers' ) + '</div>' +
				'<div class="vb-spine-empty">' + T( 'Foundation ready. Engine mounts here next.' ) + '</div></aside>' +
			'<main class="vb-canvas"><div class="vb-canvas-note">' +
				'<div class="vb-canvas-mark">V</div>' +
				'<b>' + T( 'Editor shell is live' ) + '</b>' +
				'<p>' + T( 'The proven engine — store, live CSS, cascade resolver — plugs into this canvas in the next build pass.' ) + '</p>' +
				'</div></main>' +
			'<aside class="vb-inspector"><div class="vb-insp-h">' + T( 'Style' ) + '</div>' +
				'<div class="vb-insp-empty">' + T( 'Select an element to style it.' ) + '</div></aside>';

		root.appendChild( bar );
		root.appendChild( body );

		injectShellStyles();
	}

	/* Minimal shell styles inlined so the foundation renders without depending
	   on the full editor stylesheet (which arrives with the engine). */
	function injectShellStyles() {
		if ( document.getElementById( 'vb-shell-style' ) ) { return; }
		var css =
			'.vb-app{position:fixed;inset:0;display:flex;flex-direction:column;background:var(--vbb-void);color:var(--vbb-text)}' +
			'.vb-topbar{height:52px;display:flex;align-items:center;gap:14px;padding:0 14px;background:var(--vbb-panel);border-bottom:1px solid var(--vbb-line)}' +
			'.vb-brand{display:flex;align-items:center;gap:9px;font-weight:650}' +
			'.vb-brand-m{width:26px;height:26px;border-radius:8px;background:linear-gradient(140deg,var(--vbb-accent),var(--vbb-accent-2));display:grid;place-items:center;color:#fff;font-weight:800;font-size:12px}' +
			'.vb-back{color:var(--vbb-text-2);text-decoration:none;font-size:12.5px;font-weight:600;padding:7px 12px;border-radius:8px}' +
			'.vb-back:hover{background:var(--vbb-raised-2);color:var(--vbb-text)}' +
			'.vb-spring{flex:1}' +
			'.vb-publish{padding:8px 17px;border-radius:9px;background:linear-gradient(180deg,#218ec4,#1a789f);color:#eef7fc;font-weight:700;font-size:12.5px;border:none;cursor:pointer}' +
			'.vb-body{flex:1;display:grid;grid-template-columns:220px 1fr 300px;min-height:0}' +
			'.vb-spine,.vb-inspector{background:var(--vbb-panel);display:flex;flex-direction:column;min-height:0}' +
			'.vb-spine{border-right:1px solid var(--vbb-line)}.vb-inspector{border-left:1px solid var(--vbb-line)}' +
			'.vb-spine-h,.vb-insp-h{padding:14px 16px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--vbb-text-3);border-bottom:1px solid var(--vbb-line)}' +
			'.vb-spine-empty,.vb-insp-empty{padding:18px 16px;font-size:12px;color:var(--vbb-text-3);line-height:1.5}' +
			'.vb-canvas{background:#16161b;display:flex;align-items:center;justify-content:center;padding:40px}' +
			'.vb-canvas-note{text-align:center;max-width:32ch;display:flex;flex-direction:column;align-items:center;gap:12px}' +
			'.vb-canvas-mark{width:48px;height:48px;border-radius:14px;background:linear-gradient(140deg,var(--vbb-accent),var(--vbb-accent-2));display:grid;place-items:center;color:#fff;font-weight:800;font-size:22px;box-shadow:0 6px 24px rgba(42,183,241,.4)}' +
			'.vb-canvas-note b{font-size:15px;color:var(--vbb-text)}' +
			'.vb-canvas-note p{font-size:12.5px;color:var(--vbb-text-3);line-height:1.55;margin:0}';
		var s = document.createElement( 'style' );
		s.id = 'vb-shell-style';
		s.textContent = css;
		document.head.appendChild( s );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', mount );
	} else {
		mount();
	}
}() );
