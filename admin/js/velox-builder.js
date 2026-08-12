/**
 * Velox Builder — the editor.
 *
 * The real editing engine, mounted into the standalone full-screen route.
 * Machinery proven in the engine spike — a central store (single source of
 * truth with undo/redo), live CSS generation + injection into the canvas
 * iframe via CSSOM, and a cascade resolver that COMPUTES which class owns each
 * property (the blue / orange / pink indicators) — dressed in the Velox Builder
 * UI: a slim layers spine, a selectable canvas frame, and a class-first inspector.
 *
 * Persistence (save/load via REST) and front-end rendering hook into the marked
 * points; this bundle is self-contained and boots offline.
 */
( function () {
	'use strict';

	var CFG = window.VELOX_BUILDER || {};
	var T = window.veloxT || function ( s ) { return s; };

	/* ---------- icons (real Velox/Lucide set) ---------- */
	var ICON = {
		section:'<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/>',
		div:'<rect x="3" y="3" width="18" height="18" rx="2"/>',
		columns:'<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 3v18"/>',
		grid:'<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
		heading:'<path d="M6 12h12"/><path d="M6 20V4"/><path d="M18 20V4"/>',
		text:'<path d="M17 6.1H3"/><path d="M21 12.1H3"/><path d="M15.1 18H3"/>',
		button:'<rect width="20" height="10" x="2" y="7" rx="5"/><circle cx="8" cy="12" r="1"/>',
		image:'<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
		bolt:'<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>',
		code:'<path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/>',
		search:'<path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/>',
		plus:'<path d="M5 12h14"/><path d="M12 5v14"/>',
		undo:'<path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/>',
		redo:'<path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7"/>',
		monitor:'<rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/>',
		tablet:'<rect width="16" height="20" x="4" y="2" rx="2"/><line x1="12" x2="12.01" y1="18" y2="18"/>',
		smartphone:'<rect width="14" height="20" x="5" y="2" rx="2"/><path d="M12 18h.01"/>',
		move:'<path d="M12 2v20"/><path d="m5 9-3 3 3 3"/><path d="m9 5 3-3 3 3"/><path d="m15 19 3 3 3-3"/><path d="m19 9 3 3-3 3"/><path d="M2 12h20"/>',
		type:'<path d="M4 7V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v2"/><path d="M12 4v16"/><path d="M9 20h6"/>',
		layout:'<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18"/>',
		copy:'<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
		trash:'<path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
		chevron:'<path d="m6 9 6 6 6-6"/>',
		home:'<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
		gear:'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 8 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H2a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 3.6 8a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H8a1.65 1.65 0 0 0 1-1.51V2a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V8a1.65 1.65 0 0 0 1.51 1H22a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
		video:'<path d="m22 8-6 4 6 4V8z"/><rect x="2" y="6" width="14" height="12" rx="2"/>',
		star:'<path d="m12 2 3 7 7 .5-5.5 4.5 2 7-6.5-4-6.5 4 2-7L2 9.5 9 9z"/>',
		link:'<path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
		list:'<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
		x:'<path d="M18 6 6 18M6 6l12 12"/>',
		clock:'<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
		structure:'<path d="M3 3h7v7H3zM14 3h7v4h-7zM14 10h7v4h-7zM14 17h7v4h-7z"/><path d="M10 6h4M10 12h4M10 19h4"/>',
		exit:'<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
		external:'<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6M10 14 21 3"/>',
		eye:'<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
		droplet:'<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/>'
	};
	function svg( name, size ) {
		size = size || 16;
		return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + ( ICON[ name ] || '' ) + '</svg>';
	}
	function elIcon( n ) { return { Section:'layout', Heading:'type', Text:'type', Button:'link', Div:'layout', Image:'image', Columns:'columns', Grid:'grid', Video:'video', Icon:'star', WP:'wp', Reviews:'star', Reusable:'copy' }[ n ] || 'layout'; }

	/* ============================================================
	   1. STORE
	   ============================================================ */
	var store = {
		state:null, history:[], future:[], listeners:[], log:[], logListeners:[],
		init:function ( d ) { this.state = d; this.history = [ JSON.stringify( d ) ]; this.log = []; this.future = []; this.emitLog(); this.emit(); },
		snapshot:function () { var snap = JSON.stringify( this.state ); if ( this.history[ this.history.length - 1 ] === snap ) { return false; } this.history.push( snap ); if ( this.history.length > 120 ) { this.history.shift(); } this.future = []; return true; },
		/* commit: fn mutates state; snapshot the RESULT so history holds real edit
		   states. quiet=true (selection, hover) doesn't record history/log. */
		commit:function ( fn, label ) { fn( this.state ); if ( label !== false ) { var pushed = this.snapshot(); if ( pushed && label ) { this.log.push( { label:label, at:Date.now(), idx:this.history.length } ); if ( this.log.length > 120 ) { this.log.shift(); } this.emitLog(); } } this.emit(); },
		undo:function () { if ( this.history.length <= 1 ) { return; } this.future.push( this.history.pop() ); this.state = JSON.parse( this.history[ this.history.length - 1 ] ); this.emit(); },
		redo:function () { if ( ! this.future.length ) { return; } var s = this.future.pop(); this.history.push( s ); this.state = JSON.parse( s ); this.emit(); },
		/* Revert to a specific snapshot (session history). idx = history length at
		   that point, so history[idx-1] is that state. */
		revertTo:function ( idx ) { if ( idx < 1 || idx > this.history.length ) { return; } this.state = JSON.parse( this.history[ idx - 1 ] ); this.history = this.history.slice( 0, idx ); this.future = []; this.log = this.log.filter( function ( l ) { return l.idx <= idx; } ); this.emitLog(); this.emit(); },
		subscribe:function ( fn ) { this.listeners.push( fn ); },
		subscribeLog:function ( fn ) { this.logListeners.push( fn ); },
		emit:function () { for ( var i = 0; i < this.listeners.length; i++ ) { this.listeners[ i ]( this.state ); } },
		emitLog:function () { for ( var i = 0; i < this.logListeners.length; i++ ) { this.logListeners[ i ]( this.log ); } }
	};

	/* A new page starts empty — no demo content. */
	var initialDoc = {
		selection: null, activeClass: null, breakpoint: 'base', state: 'normal',
		tree: [],
		classes: {},
		content: {}
	};

	var CSS_PROP = {
		display:'display', flexDirection:'flex-direction', flexWrap:'flex-wrap', alignItems:'align-items', justifyContent:'justify-content', gap:'gap',
		paddingTop:'padding-top', paddingRight:'padding-right', paddingBottom:'padding-bottom', paddingLeft:'padding-left',
		marginTop:'margin-top', marginRight:'margin-right', marginBottom:'margin-bottom', marginLeft:'margin-left',
		width:'width', maxWidth:'max-width', height:'height', minHeight:'min-height',
		fontSize:'font-size', fontWeight:'font-weight', lineHeight:'line-height', letterSpacing:'letter-spacing', textAlign:'text-align', textDecoration:'text-decoration', textTransform:'text-transform',
		color:'color', background:'background', opacity:'opacity',
		borderWidth:'border-width', borderStyle:'border-style', borderColor:'border-color', borderRadius:'border-radius',
		boxShadow:'box-shadow', gridTemplateColumns:'grid-template-columns'
	};
	var UNIT_PROPS = {
		gap:1, paddingTop:1, paddingRight:1, paddingBottom:1, paddingLeft:1, marginTop:1, marginRight:1, marginBottom:1, marginLeft:1,
		width:1, maxWidth:1, height:1, minHeight:1, fontSize:1, letterSpacing:1, borderWidth:1, borderRadius:1
	};
	var BP_ORDER = [ 'base', 'tablet', 'mobile' ];
	var BP_META = { base:{ label:'Desktop', mq:null }, tablet:{ label:'Tablet ≤991', mq:'(max-width: 991px)' }, mobile:{ label:'Mobile ≤767', mq:'(max-width: 767px)' } };

	function walkTree( nodes, fn ) { for ( var i = 0; i < nodes.length; i++ ) { fn( nodes[ i ] ); if ( nodes[ i ].children ) { walkTree( nodes[ i ].children, fn ); } } }
	function findNode( nodes, id ) { for ( var i = 0; i < nodes.length; i++ ) { if ( nodes[ i ].id === id ) { return nodes[ i ]; } if ( nodes[ i ].children ) { var f = findNode( nodes[ i ].children, id ); if ( f ) { return f; } } } return null; }

	/* ============================================================
	   3. CASCADE RESOLVER
	   ============================================================ */
	function bpChain( bp ) { var i = BP_ORDER.indexOf( bp ), c = []; for ( var j = i; j >= 0; j-- ) { c.push( BP_ORDER[ j ] ); } return c; }
	function resolveProperty( node, bp, prop, state ) {
		var S = store.state, chain = bpChain( bp ), b, k;
		// When editing a pseudo-state, that state's keys win; otherwise fall to normal.
		var keysFor = function ( breakpoint ) {
			return ( state && state !== 'normal' ) ? [ breakpoint + ':' + state, breakpoint ] : [ breakpoint ];
		};
		var ov = node.overrides || {};
		for ( k = 0; k < chain.length; k++ ) { var ks = keysFor( chain[ k ] ); for ( var ki = 0; ki < ks.length; ki++ ) { if ( ov[ ks[ ki ] ] && ov[ ks[ ki ] ][ prop ] != null ) { return { value:ov[ ks[ ki ] ][ prop ], source:'element', bp:chain[ k ], st:ks[ ki ].indexOf( ':' ) > -1 }; } } }
		var stack = node.classes.slice().reverse();
		for ( var s = 0; s < stack.length; s++ ) {
			var rules = S.classes[ stack[ s ] ]; if ( ! rules ) { continue; }
			for ( k = 0; k < chain.length; k++ ) { var kk = keysFor( chain[ k ] ); for ( var kj = 0; kj < kk.length; kj++ ) { if ( rules[ kk[ kj ] ] && rules[ kk[ kj ] ][ prop ] != null ) { return { value:rules[ kk[ kj ] ][ prop ], source:'class', cls:stack[ s ], bp:chain[ k ], st:kk[ kj ].indexOf( ':' ) > -1 }; } } }
		}
		return { value:null, source:'none' };
	}
	function dotFor( res, activeClass, bp ) {
		if ( res.source === 'none' ) { return { cls:'none', tip:'Not set' }; }
		if ( res.source === 'element' ) { return { cls:'pink', tip: res.bp === bp ? 'Element override · this breakpoint' : 'Element override · from ' + res.bp }; }
		if ( res.cls === activeClass && res.bp === bp ) { return { cls:'blue', tip:'Set on ' + activeClass + ( bp !== 'base' ? ' · ' + bp : '' ) }; }
		var from;
		if ( res.cls !== activeClass && res.bp !== bp ) { from = res.cls + ' · ' + res.bp; }
		else if ( res.cls !== activeClass ) { from = res.cls; }
		else { from = 'wider breakpoint (' + res.bp + ')'; }
		return { cls:'orange', tip:'Inherited from ' + from + ' — set here to override' };
	}

	/* ============================================================
	   2. CSS GEN + LIVE INJECTION
	   ============================================================ */
	function declBlock( obj ) {
		var out = '';
		for ( var p in obj ) {
			if ( ! obj.hasOwnProperty( p ) ) { continue; }
			var kebab = CSS_PROP[ p ] || p, v = obj[ p ];
			if ( UNIT_PROPS[ p ] && /^-?\d+(\.\d+)?$/.test( v ) ) { v = v + 'px'; }
			out += '  ' + kebab + ': ' + v + ';\n';
		}
		return out;
	}
	function genCSS() {
		var S = store.state, out = '';
		var STATES = [ 'normal', 'hover', 'focus' ];
		// Merge in classes from any reusables referenced on the page, so they
		// display styled in the canvas exactly as they will on the front end.
		var merged = {};
		for ( var c0 in S.classes ) { if ( S.classes.hasOwnProperty( c0 ) ) { merged[ c0 ] = S.classes[ c0 ]; } }
		( function collectReuse( nodes ) {
			nodes.forEach( function ( n ) {
				if ( n.el === 'Reusable' ) { var r = reusableById( n.ref ); if ( r && r.classes ) { for ( var rc in r.classes ) { if ( r.classes.hasOwnProperty( rc ) && ! merged[ rc ] ) { merged[ rc ] = r.classes[ rc ]; } } } }
				if ( n.children ) { collectReuse( n.children ); }
			} );
		}( S.tree ) );
		for ( var i = 0; i < BP_ORDER.length; i++ ) {
			var bp = BP_ORDER[ i ], meta = BP_META[ bp ], body = '';
			for ( var si = 0; si < STATES.length; si++ ) {
				var st = STATES[ si ], key = st === 'normal' ? bp : bp + ':' + st, pseudo = st === 'normal' ? '' : ':' + st;
				for ( var cls in merged ) {
					if ( ! merged.hasOwnProperty( cls ) ) { continue; }
					var rules = merged[ cls ][ key ];
					if ( rules && Object.keys( rules ).length ) { body += cls + pseudo + ' {\n' + declBlock( rules ) + '}\n'; }
				}
				walkTree( S.tree, function ( node ) {
					var ov = node.overrides && node.overrides[ key ];
					if ( ov && Object.keys( ov ).length ) { body += '#' + node.id + pseudo + ' {\n' + declBlock( ov ) + '}\n'; }
				} );
			}
			if ( ! body ) { continue; }
			if ( meta.mq ) { out += '@media ' + meta.mq + ' {\n' + body.replace( /^/gm, '  ' ) + '}\n'; }
			else { out += body; }
			out += '\n';
		}
		return out.replace( /\n{3,}/g, '\n\n' ).trim();
	}
	function genHTML() {
		var S = store.state;
		function renderReuseTree( nodes, r ) {
			return nodes.map( function ( n ) {
				var cls = ( n.classes || [] ).map( function ( c ) { return c.slice( 1 ); } ).join( ' ' );
				var kids = ( n.children || [] ).length ? renderReuseTree( n.children, r ) : '';
				if ( n.el === 'Image' ) {
					var s = ( r.content || {} )[ n.id ] || '';
					return '<div id="' + n.id + '" class="' + cls + '">' + ( s ? '<img src="' + s + '" alt="" style="display:block;max-width:100%;height:auto">' : '' ) + '</div>';
				}
				var t = ( r.content || {} )[ n.id ] || '';
				return '<' + n.tag + ' id="' + n.id + '" class="' + cls + '">' + t + kids + '</' + n.tag + '>';
			} ).join( '' );
		}
		function render( node ) {
			var cls = node.classes.map( function ( c ) { return c.slice( 1 ); } ).join( ' ' );
			var kids = node.children.map( render ).join( '' );
			// Reusable: render the referenced block inline, framed + non-interactive.
			if ( node.el === 'Reusable' ) {
				var r = reusableById( node.ref );
				var inner = r ? renderReuseTree( r.tree || [], r ) : '<span class="vb-img-ph">' + T( 'Missing reusable' ) + '</span>';
				return '<div id="' + node.id + '" class="' + cls + ' vb-reuse" data-node="' + node.id + '" data-reuse="' + node.ref + '"><span class="vb-reuse-tag">' + escapeHtml( r ? r.title : '?' ) + '</span>' + inner + '</div>';
			}
			// Google Reviews: labeled placeholder in the editor (real reviews render on the front end).
			if ( node.el === 'Reviews' ) {
				var rc = ( CFG.reviewConnections || [] ).filter( function ( c ) { return c.id === node.conn; } )[ 0 ];
				var label = node.conn ? ( T( 'Google Reviews' ) + ( rc ? ' · ' + escapeHtml( rc.name ) : '' ) ) : T( 'Google Reviews — pick a connection & preset in Settings' );
				return '<div id="' + node.id + '" class="' + cls + ' vb-ph-el" data-node="' + node.id + '"><span class="vb-ph-ic">' + svg( 'star', 22 ) + '</span><span class="vb-ph-l">' + label + '</span></div>';
			}
			// WordPress-data: labeled placeholder in the editor (live data renders on the front end).
			if ( node.el === 'WP' ) {
				var wpl = { title:T( 'Post title' ), content:T( 'Post content' ), featured:T( 'Featured image' ), menu:T( 'Menu / Nav' ) }[ node.wp ] || 'WordPress';
				return '<div id="' + node.id + '" class="' + cls + ' vb-ph-el" data-node="' + node.id + '"><span class="vb-ph-ic">' + svg( 'wp', 22 ) + '</span><span class="vb-ph-l">' + wpl + ' — ' + T( 'live WordPress data' ) + '</span></div>';
			}
			// Image element: render a real <img> when a src is stored, else a placeholder.
			if ( node.el === 'Image' ) {
				var src = S.content[ node.id ] || '';
				var inner2 = src ? '<img src="' + src + '" alt="" style="display:block;max-width:100%;height:auto">' : '<span class="vb-img-ph">' + T( 'Double-click to choose an image' ) + '</span>';
				return '<div id="' + node.id + '" class="' + cls + '" data-node="' + node.id + '">' + inner2 + '</div>';
			}
			var txt = S.content[ node.id ] || '';
			return '<' + node.tag + ' id="' + node.id + '" class="' + cls + '" data-node="' + node.id + '">' + txt + kids + '</' + node.tag + '>';
		}
		return S.tree.map( render ).join( '' );
	}
	function ensureCanvasDoc() {
		var fr = document.getElementById( 'vb-canvas' );
		if ( ! fr ) { return null; }
		var doc = fr.contentDocument;
		if ( ! doc.getElementById( 'vb-style' ) ) {
			doc.open();
			doc.write( '<!DOCTYPE html><html><head><meta charset="utf-8"><style id="vb-reset">*{box-sizing:border-box;margin:0}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,sans-serif}[data-node]{outline:1px solid transparent;outline-offset:-1px;transition:outline-color .1s}[data-node]:hover{outline-color:rgba(42,183,241,.45)}[data-node].vb-sel{outline:2px solid #2ab7f1}.vb-img-ph{display:flex;align-items:center;justify-content:center;min-height:120px;color:#8a94a0;font-size:13px;background:repeating-linear-gradient(45deg,#eef1f4,#eef1f4 10px,#e6eaee 10px,#e6eaee 20px)}.vb-empty-canvas{min-height:70vh;display:flex;align-items:center;justify-content:center;color:#9aa3ad}.vb-ec-inner{text-align:center}.vb-ec-inner b{display:block;font-size:16px;color:#5b6673;margin-bottom:6px}.vb-ec-inner p{font-size:13px}.vb-reuse{position:relative;outline:1px dashed rgba(160,107,255,.5);outline-offset:-1px}.vb-reuse-tag{position:absolute;top:0;left:0;background:#a06bff;color:#fff;font:600 10px/1 -apple-system,sans-serif;padding:3px 7px;border-radius:0 0 6px 0;z-index:2}.vb-ph-el{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;min-height:110px;padding:24px;border:1px dashed #c7ced6;border-radius:10px;background:#f6f8fa;color:#5b6673;text-align:center}.vb-ph-el .vb-ph-ic{color:#2ab7f1}.vb-ph-el .vb-ph-l{font:600 13px/1.4 -apple-system,sans-serif}</style><style id="vb-style"></style></head><body></body></html>' );
			doc.close();
		}
		return doc;
	}
	function injectCanvas() {
		var doc = ensureCanvasDoc(); if ( ! doc || ! doc.body ) { return; }
		var html = genHTML();
		// Empty page → friendly prompt instead of a blank white void.
		if ( ! store.state.tree.length ) {
			html = '<div class="vb-empty-canvas"><div class="vb-ec-inner"><b>' + T( 'Empty page' ) + '</b><p>' + T( 'Click “Add element” to start building.' ) + '</p></div></div>';
		}
		if ( doc.body.getAttribute( 'data-html' ) !== html ) {
			doc.body.innerHTML = html; doc.body.setAttribute( 'data-html', html );
			doc.addEventListener( 'click', function ( e ) {
				if ( e.target.isContentEditable ) { return; }
				var n = e.target.closest ? e.target.closest( '[data-node]' ) : null;
				if ( n ) { e.preventDefault(); store.commit( function ( s ) { s.selection = n.getAttribute( 'data-node' ); resetActiveClass( s ); }, false ); }
			} );
			// Double-click a text-bearing element to edit its text right on the canvas.
			doc.addEventListener( 'dblclick', function ( e ) {
				var n = e.target.closest ? e.target.closest( '[data-node]' ) : null;
				if ( ! n ) { return; }
				var id = n.getAttribute( 'data-node' ), node = findNode( store.state.tree, id );
				if ( ! node ) { return; }
				e.preventDefault();
				if ( node.el === 'Image' ) { openMediaPicker( id ); return; }
				if ( isContainer( node ) ) { return; } // only leaf/text nodes edit inline
				startInlineEdit( n, id );
			} );
		}
		doc.getElementById( 'vb-style' ).textContent = genCSS();
		var prev = doc.querySelector( '.vb-sel' ); if ( prev ) { prev.classList.remove( 'vb-sel' ); }
		var selEl = doc.getElementById( store.state.selection ); if ( selEl ) { selEl.classList.add( 'vb-sel' ); }
	}
	function resetActiveClass( s ) { var n = findNode( s.tree, s.selection ); s.activeClass = n && n.classes.length ? n.classes[ 0 ] : null; s.state = 'normal'; }

	/* ---------- inline text editing (double-click on canvas) ---------- */
	var editing = null; // { id, el }
	function startInlineEdit( elNode, id ) {
		if ( editing ) { commitInlineEdit(); }
		editing = { id: id, el: elNode };
		elNode.setAttribute( 'contenteditable', 'true' );
		elNode.style.outline = '2px solid #a06bff';
		elNode.focus();
		// place caret at end
		var doc = elNode.ownerDocument, range = doc.createRange(), selr = doc.defaultView.getSelection();
		range.selectNodeContents( elNode ); range.collapse( false );
		selr.removeAllRanges(); selr.addRange( range );
		elNode.addEventListener( 'keydown', inlineKey );
		elNode.addEventListener( 'blur', commitInlineEdit );
	}
	function inlineKey( e ) {
		if ( e.key === 'Enter' && ! e.shiftKey ) { e.preventDefault(); e.target.blur(); }
		if ( e.key === 'Escape' ) { e.preventDefault(); cancelInlineEdit(); }
		e.stopPropagation();
	}
	function commitInlineEdit() {
		if ( ! editing ) { return; }
		var elNode = editing.el, id = editing.id;
		var text = elNode.textContent.trim();
		elNode.removeAttribute( 'contenteditable' ); elNode.style.outline = '';
		elNode.removeEventListener( 'keydown', inlineKey ); elNode.removeEventListener( 'blur', commitInlineEdit );
		var prev = editing; editing = null;
		if ( store.state.content[ id ] !== text ) { store.commit( function ( s ) { s.content[ id ] = text; }, T( 'Edit text' ) ); }
	}
	function cancelInlineEdit() {
		if ( ! editing ) { return; }
		var elNode = editing.el;
		elNode.textContent = store.state.content[ editing.id ] || '';
		elNode.removeAttribute( 'contenteditable' ); elNode.style.outline = '';
		elNode.removeEventListener( 'keydown', inlineKey ); elNode.removeEventListener( 'blur', commitInlineEdit );
		editing = null;
	}

	/* ---------- image picker (WordPress media library) ---------- */
	var mediaFrame = null;
	function openMediaPicker( id ) {
		if ( ! window.wp || ! window.wp.media ) { alert( T( 'The media library is unavailable.' ) ); return; }
		if ( ! mediaFrame ) {
			mediaFrame = window.wp.media( { title: T( 'Choose an image' ), multiple: false, library: { type: 'image' }, button: { text: T( 'Use image' ) } } );
		}
		// rebind select each open so it targets the right node
		mediaFrame.off( 'select' );
		mediaFrame.on( 'select', function () {
			var a = mediaFrame.state().get( 'selection' ).first().toJSON();
			var url = ( a.sizes && a.sizes.large ) ? a.sizes.large.url : a.url;
			store.commit( function ( s ) { s.content[ id ] = url; s.selection = id; resetActiveClass( s ); } );
		} );
		mediaFrame.open();
	}

	function setProp( prop, value, elementOverride ) {
		store.commit( function ( s ) {
			var node = findNode( s.tree, s.selection ), key = ruleKey( s.breakpoint, s.state );
			if ( elementOverride ) { node.overrides[ key ] = node.overrides[ key ] || {}; node.overrides[ key ][ prop ] = value; }
			else { var c = s.activeClass; s.classes[ c ] = s.classes[ c ] || {}; s.classes[ c ][ key ] = s.classes[ c ][ key ] || {}; s.classes[ c ][ key ][ prop ] = value; }
		}, T( 'Style' ) + ': ' + prop );
	}
	function removeProp( prop ) { store.commit( function ( s ) { var c = s.activeClass, key = ruleKey( s.breakpoint, s.state ); if ( s.classes[ c ] && s.classes[ c ][ key ] ) { delete s.classes[ c ][ key ][ prop ]; } } ); }
	/* rules for the "normal" state live under the plain breakpoint key (back-compat);
	   pseudo-states get a suffixed key like "base:hover" / "tablet:focus". */
	function ruleKey( bp, state ) { return ( ! state || state === 'normal' ) ? bp : bp + ':' + state; }

	/* ---------- node operations (insert / duplicate / delete) ---------- */
	var idSeq = 100;
	function uid( base ) { idSeq += 1; return ( base || 'el' ) + '-' + idSeq.toString( 36 ); }

	/* element catalog: what "Add" can insert. Each seeds a default class + rules. */
	/* Element catalog, grouped into the accordion categories shown in the panel.
	   Each element: key, el(type), tag, label, starter class + rules, optional text. */
	var CAT_GROUPS = [
		{ name:'Containers', icon:'layout', items:[
			{ key:'section', el:'Section', tag:'section', label:'Section', cls:'.section', rules:{ paddingTop:'48', paddingBottom:'48', paddingLeft:'32', paddingRight:'32' } },
			{ key:'div', el:'Div', tag:'div', label:'Div block', cls:'.block', rules:{} },
			{ key:'columns', el:'Columns', tag:'div', label:'Columns', cls:'.columns', rules:{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'20' } },
			{ key:'grid', el:'Div', tag:'div', label:'Grid', cls:'.grid', rules:{ display:'grid', gridTemplateColumns:'1fr 1fr 1fr', gap:'20' } },
			{ key:'spacer', el:'Div', tag:'div', label:'Spacer', cls:'.spacer', rules:{ height:'40' } },
			{ key:'divider', el:'Div', tag:'div', label:'Divider', cls:'.divider', rules:{ height:'1', background:'#e2e8f0' } }
		] },
		{ name:'Text', icon:'type', items:[
			{ key:'heading', el:'Heading', tag:'h2', label:'Heading', cls:'.heading', rules:{ fontSize:'32', fontWeight:'700' }, text:'New heading' },
			{ key:'text', el:'Text', tag:'p', label:'Text', cls:'.text', rules:{ fontSize:'16' }, text:'New text block.' },
			{ key:'list', el:'Text', tag:'ul', label:'List', cls:'.list', rules:{ fontSize:'16' }, text:'<li>First item</li><li>Second item</li>' },
			{ key:'quote', el:'Text', tag:'blockquote', label:'Quote', cls:'.quote', rules:{ fontSize:'20', paddingLeft:'20', borderWidth:'4', borderStyle:'solid', borderColor:'#2ab7f1' }, text:'A memorable quote.' }
		] },
		{ name:'Links', icon:'link', items:[
			{ key:'button', el:'Button', tag:'a', label:'Button', cls:'.btn', rules:{ display:'inline-block', paddingTop:'12', paddingBottom:'12', paddingLeft:'20', paddingRight:'20', borderRadius:'8', background:'#2ab7f1', color:'#04222f', textDecoration:'none' }, text:'Button' },
			{ key:'textlink', el:'Button', tag:'a', label:'Text link', cls:'.link', rules:{ color:'#2ab7f1' }, text:'Text link' }
		] },
		{ name:'Media', icon:'image', items:[
			{ key:'image', el:'Image', tag:'div', label:'Image', cls:'.image', rules:{} },
			{ key:'video', el:'Video', tag:'div', label:'Video', cls:'.video', rules:{ background:'#0b0f14' }, text:'' },
			{ key:'icon', el:'Icon', tag:'div', label:'Icon', cls:'.icon', rules:{ color:'#2ab7f1' }, text:'' }
		] },
		{ name:'WordPress', icon:'wp', items:[
			{ key:'wp_title', el:'WP', tag:'h1', label:'Post title', cls:'.wp-title', rules:{ fontSize:'40', fontWeight:'800' }, wp:'title' },
			{ key:'wp_content', el:'WP', tag:'div', label:'Post content', cls:'.wp-content', rules:{}, wp:'content' },
			{ key:'wp_featured', el:'WP', tag:'div', label:'Featured image', cls:'.wp-featured', rules:{}, wp:'featured' },
			{ key:'wp_menu', el:'WP', tag:'nav', label:'Menu / Nav', cls:'.wp-menu', rules:{}, wp:'menu' }
		] },
		{ name:'Velox', icon:'star', items:[
			{ key:'reviews', el:'Reviews', tag:'div', label:'Google Reviews', cls:'.reviews', rules:{}, badge:'plugin' }
		] }
	];
	// flat lookup used by insertNode
	var CATALOG = ( function () { var a = []; CAT_GROUPS.forEach( function ( g ) { a = a.concat( g.items ); } ); return a; }() );

	function insertNode( catKey ) {
		var cat = CATALOG.filter( function ( c ) { return c.key === catKey; } )[ 0 ] || CATALOG[ 0 ];
		store.commit( function ( s ) {
			var id = uid( cat.key );
			var node = { id:id, el:cat.el, tag:cat.tag, classes:[ cat.cls ], overrides:{}, children:[] };
			if ( cat.wp ) { node.wp = cat.wp; }         // WordPress-data element kind
			if ( cat.el === 'Reviews' ) { node.conn = ''; node.preset = ''; }
			// register the class + its starter rules (only if new)
			if ( ! s.classes[ cat.cls ] ) { s.classes[ cat.cls ] = { base: Object.assign( {}, cat.rules ) }; }
			if ( cat.text ) { s.content[ id ] = cat.text; }
			// insert after the current selection's parent context, else append to root
			var sel = findNode( s.tree, s.selection );
			if ( sel && isContainer( sel ) ) { sel.children.push( node ); }
			else {
				var parent = findParent( s.tree, s.selection );
				if ( parent ) { var i = parent.children.indexOf( sel ); parent.children.splice( i + 1, 0, node ); }
				else {
					var ri = s.tree.map( function ( n ) { return n.id; } ).indexOf( s.selection );
					s.tree.splice( ri < 0 ? s.tree.length : ri + 1, 0, node );
				}
			}
			s.selection = id; resetActiveClass( s );
		}, T( 'Add' ) + ' ' + cat.label );
	}
	function isContainer( n ) { return n.el === 'Section' || n.el === 'Div' || n.el === 'Columns'; }
	/* Add a class to the selected node (creating the class if new). */
	function addClassToSelected() {
		var name = prompt( T( 'Class name (letters, numbers, dashes):' ), '' );
		if ( ! name ) { return; }
		name = '.' + name.replace( /^\./, '' ).replace( /[^A-Za-z0-9_-]/g, '' );
		if ( name === '.' ) { return; }
		store.commit( function ( s ) {
			var n = findNode( s.tree, s.selection ); if ( ! n ) { return; }
			if ( n.classes.indexOf( name ) > -1 ) { return; }
			n.classes.push( name );
			if ( ! s.classes[ name ] ) { s.classes[ name ] = { base:{} }; }
			s.activeClass = name;
		}, T( 'Add class' ) + ' ' + name );
	}
	/* Remove a class from the selected node (keeps the class definition around). */
	function removeClassFromNode( cls ) {
		store.commit( function ( s ) {
			var n = findNode( s.tree, s.selection ); if ( ! n ) { return; }
			var i = n.classes.indexOf( cls ); if ( i < 0 ) { return; }
			n.classes.splice( i, 1 );
			if ( s.activeClass === cls ) { s.activeClass = n.classes[ 0 ] || null; }
		}, T( 'Remove class' ) + ' ' + cls );
	}
	/* Change the HTML tag of the selected node (e.g. heading level). */
	function setNodeTag( tag ) {
		store.commit( function ( s ) { var n = findNode( s.tree, s.selection ); if ( n ) { n.tag = tag; } } );
	}
	/* Insert a reference to a reusable — its content renders inline and updates
	   everywhere the reusable is used. */
	function insertReusable( refId ) {
		var r = reusableById( refId ); if ( ! r ) { return; }
		store.commit( function ( s ) {
			var id = uid( 'reuse' );
			var node = { id:id, el:'Reusable', tag:'div', ref:refId, classes:[], overrides:{}, children:[] };
			var sel = findNode( s.tree, s.selection );
			if ( sel && isContainer( sel ) ) { sel.children.push( node ); }
			else {
				var parent = findParent( s.tree, s.selection );
				if ( parent ) { var i = parent.children.indexOf( sel ); parent.children.splice( i + 1, 0, node ); }
				else { var ri = s.tree.map( function ( n ) { return n.id; } ).indexOf( s.selection ); s.tree.splice( ri < 0 ? s.tree.length : ri + 1, 0, node ); }
			}
			s.selection = id; resetActiveClass( s );
		}, T( 'Add reusable' ) );
	}
	function findParent( nodes, id, parent ) {
		for ( var i = 0; i < nodes.length; i++ ) {
			if ( nodes[ i ].id === id ) { return parent || null; }
			if ( nodes[ i ].children ) { var f = findParent( nodes[ i ].children, id, nodes[ i ] ); if ( f !== undefined && f !== null ) { return f; } if ( f === null && hasDescendant( nodes[ i ], id ) ) { return nodes[ i ]; } }
		}
		return null;
	}
	function hasDescendant( node, id ) { var found = false; walkTree( node.children || [], function ( n ) { if ( n.id === id ) { found = true; } } ); return found; }

	function duplicateNode( id ) {
		store.commit( function ( s ) {
			var node = findNode( s.tree, id ); if ( ! node ) { return; }
			var copy = cloneWithNewIds( node, s );
			var parent = findParent( s.tree, id );
			if ( parent ) { var i = parent.children.indexOf( node ); parent.children.splice( i + 1, 0, copy ); }
			else { var ri = s.tree.indexOf( node ); s.tree.splice( ri + 1, 0, copy ); }
			s.selection = copy.id; resetActiveClass( s );
		}, T( 'Duplicate' ) + ' ' + ( node.el || 'element' ) );
	}
	function cloneWithNewIds( node, s ) {
		var nid = uid( node.el.toLowerCase() );
		if ( s.content[ node.id ] != null ) { s.content[ nid ] = s.content[ node.id ]; }
		return { id:nid, el:node.el, tag:node.tag, classes:node.classes.slice(), overrides:JSON.parse( JSON.stringify( node.overrides || {} ) ), children:( node.children || [] ).map( function ( c ) { return cloneWithNewIds( c, s ); } ) };
	}
	function deleteNode( id ) {
		var delNode = findNode( store.state.tree, id );
		var delLabel = T( 'Delete' ) + ' ' + ( ( delNode || {} ).el || 'element' );
		store.commit( function ( s ) {
			var parent = findParent( s.tree, id ), node = findNode( s.tree, id );
			if ( parent ) { parent.children.splice( parent.children.indexOf( node ), 1 ); s.selection = parent.id; }
			else { var i = s.tree.indexOf( node ); if ( i >= 0 ) { s.tree.splice( i, 1 ); } s.selection = s.tree.length ? s.tree[ Math.max( 0, i - 1 ) ].id : null; }
			if ( s.selection ) { resetActiveClass( s ); }
		}, delLabel );
	}

	/* ---------- drag-reorder (layers panel) ---------- */
	function moveNode( dragId, targetId, position ) {
		if ( dragId === targetId ) { return; }
		store.commit( function ( s ) {
			var node = findNode( s.tree, dragId );
			var target = findNode( s.tree, targetId );
			if ( ! node || ! target ) { return; }
			// don't drop a node into its own descendants
			if ( hasDescendant( node, targetId ) ) { return; }
			// detach
			var dp = findParent( s.tree, dragId );
			if ( dp ) { dp.children.splice( dp.children.indexOf( node ), 1 ); }
			else { s.tree.splice( s.tree.indexOf( node ), 1 ); }
			// re-attach
			if ( position === 'inside' && isContainer( target ) ) {
				target.children.push( node );
			} else {
				var tp = findParent( s.tree, targetId );
				var arr = tp ? tp.children : s.tree;
				var idx = arr.indexOf( target );
				arr.splice( position === 'before' ? idx : idx + 1, 0, node );
			}
			s.selection = dragId; resetActiveClass( s );
		} );
	}

	/* ---------- persistence (save / load via AJAX) ---------- */
	var docId = CFG.docId || 0, docTitle = ( CFG.seedTitle || 'Untitled' ), saving = false, postId = CFG.postId || 0, docKind = CFG.kind || 'page';
	function saveDoc( silent ) {
		if ( saving || ! CFG.ajaxurl ) { return; }
		saving = true; setSaveState( 'saving' );
		var body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', 'builder_save' ); body.set( 'nonce', CFG.nonce || '' );
		body.set( 'id', docId ); body.set( 'title', docTitle ); body.set( 'kind', docKind );
		body.set( 'data', JSON.stringify( store.state ) );
		body.set( 'css_size', String( new Blob( [ genCSS() ] ).size ) ); if ( postId ) { body.set( 'post_id', postId ); }
		fetch( CFG.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) { saving = false; if ( res && res.success ) { docId = res.data.id; setSaveState( 'saved' ); } else { setSaveState( 'error' ); } } )
			.catch( function () { saving = false; setSaveState( 'error' ); } );
	}
	function loadDoc( id ) {
		if ( ! CFG.ajaxurl || ! id ) { return; }
		var body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', 'builder_load' ); body.set( 'nonce', CFG.nonce || '' ); body.set( 'id', id );
		fetch( CFG.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) { if ( res && res.success && res.data.model ) { docId = res.data.id; docTitle = res.data.title || 'Untitled'; pubStatus = res.data.status || 'draft'; pubUrl = res.data.url || ''; everPublished = ( pubStatus === 'published' ); dirty = false; var ti = document.getElementById( 'vb-title' ); if ( ti ) { ti.value = docTitle; } store.init( res.data.model ); setTimeout( function () { renderActions(); }, 30 ); } } );
	}
	/* ---------- Save / Publish action buttons (state-aware) ----------
	   New page (never published): a single Publish button. After first publish
	   the page "exists", so we show two buttons — Save (admin-only draft) and
	   Publish (pushes live for everyone). Save state shows on the Save button. */
	var everPublished = false, dirty = false, pubStatus = 'draft', pubUrl = '', inspTab = 'ess';
	function publishDoc() {
		if ( ! CFG.ajaxurl ) { return; }
		var afterSave = function () {
			var body = new URLSearchParams();
			body.set( 'action', 'velox' ); body.set( 'do', 'builder_publish' ); body.set( 'nonce', CFG.nonce || '' ); body.set( 'id', docId );
			setPubState( 'publishing' );
			fetch( CFG.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res && res.success ) { pubStatus = 'published'; pubUrl = res.data.url || ''; setPubState( 'published' ); }
					else { renderActions(); alert( ( res && res.data && res.data.message ) || T( 'Publish failed' ) ); }
				} )
				.catch( function () { renderActions(); } );
		};
		saveThen( afterSave );
	}
	function renderActions() {
		var host = document.getElementById( 'vb-actions' ); if ( ! host ) { return; }
		if ( ! everPublished ) {
			host.innerHTML = '<button class="vb-btn vb-btn-primary" id="vb-publish">' + ( saving ? T( 'Publishing…' ) : T( 'Publish' ) ) + '</button>';
		} else {
			host.innerHTML =
				'<button class="vb-btn vb-btn-ghost" id="vb-save">' + saveLabel() + '</button>' +
				'<button class="vb-btn vb-btn-primary" id="vb-publish">' + ( pubStatus === 'published' && ! dirty ? T( 'Published' ) : T( 'Publish' ) ) + '</button>';
			var pb = document.getElementById( 'vb-publish' );
			if ( pubStatus === 'published' && ! dirty ) { pb.classList.add( 'is-live' ); }
		}
	}
	var _saveState = 'idle';
	function saveLabel() { return { idle:T( 'Save' ), saving:T( 'Saving…' ), saved:T( 'Saved' ), error:T( 'Save failed' ) }[ _saveState ] || T( 'Save' ); }
	function setSaveState( s ) { _saveState = s; var el = document.getElementById( 'vb-save' ); if ( el ) { el.textContent = saveLabel(); el.className = 'vb-btn-save vb-save--' + s; } }
	function setPubState( state ) {
		var view = document.getElementById( 'vb-view' );
		if ( state === 'published' ) { everPublished = true; dirty = false; }
		renderActions();
		if ( view && pubUrl ) { view.href = pubUrl; view.style.display = ''; }
		var pb = document.getElementById( 'vb-publish' );
		if ( pb && state === 'publishing' ) { pb.textContent = T( 'Publishing…' ); pb.disabled = true; }
	}
	function markDirty() { dirty = true; if ( everPublished ) { renderActions(); } }

	/* ---------- publish ---------- */
	function saveThen( cb ) {
		if ( saving || ! CFG.ajaxurl ) { cb(); return; }
		saving = true; setSaveState( 'saving' );
		var body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', 'builder_save' ); body.set( 'nonce', CFG.nonce || '' );
		body.set( 'id', docId ); body.set( 'title', docTitle ); body.set( 'kind', docKind );
		body.set( 'data', JSON.stringify( store.state ) ); body.set( 'css_size', String( new Blob( [ genCSS() ] ).size ) ); if ( postId ) { body.set( 'post_id', postId ); }
		fetch( CFG.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) { saving = false; if ( res && res.success ) { docId = res.data.id; setSaveState( 'saved' ); } cb(); } )
			.catch( function () { saving = false; setSaveState( 'error' ); cb(); } );
	}

	/* ============================================================
	   UI
	   ============================================================ */
	var CONTROLS = [
		{ group:'Layout', icon:'layout', items:[
			{ prop:'display', label:'Display', type:'seg', opts:[ 'block', 'flex', 'grid', 'inline-block' ] },
			{ prop:'flexDirection', label:'Direction', type:'seg', opts:[ 'row', 'column' ] },
			{ prop:'flexWrap', label:'Wrap', type:'seg', opts:[ 'nowrap', 'wrap' ] },
			{ prop:'justifyContent', label:'Justify', type:'seg', opts:[ 'flex-start', 'center', 'space-between', 'flex-end' ] },
			{ prop:'alignItems', label:'Align', type:'seg', opts:[ 'flex-start', 'center', 'stretch', 'flex-end' ] },
			{ prop:'gap', label:'Gap', type:'num', unit:'px' },
			{ prop:'gridTemplateColumns', label:'Grid columns', type:'text', ph:'1fr 1fr' }
		] },
		{ group:'Size', icon:'move', items:[
			{ prop:'width', label:'Width', type:'text', ph:'auto' },
			{ prop:'maxWidth', label:'Max width', type:'num', unit:'px' },
			{ prop:'height', label:'Height', type:'text', ph:'auto' },
			{ prop:'minHeight', label:'Min height', type:'num', unit:'px' }
		] },
		{ group:'Spacing', icon:'move', items:[
			{ prop:'paddingTop', label:'Padding top', type:'num', unit:'px' },
			{ prop:'paddingRight', label:'Padding right', type:'num', unit:'px' },
			{ prop:'paddingBottom', label:'Padding bottom', type:'num', unit:'px' },
			{ prop:'paddingLeft', label:'Padding left', type:'num', unit:'px' },
			{ prop:'marginTop', label:'Margin top', type:'num', unit:'px' },
			{ prop:'marginBottom', label:'Margin bottom', type:'num', unit:'px' }
		] },
		{ group:'Typography', icon:'type', items:[
			{ prop:'fontSize', label:'Font size', type:'num', unit:'px' },
			{ prop:'fontWeight', label:'Weight', type:'seg', opts:[ '400', '600', '700', '800' ] },
			{ prop:'lineHeight', label:'Line height', type:'text', ph:'1.5' },
			{ prop:'letterSpacing', label:'Letter spacing', type:'num', unit:'px' },
			{ prop:'textAlign', label:'Align', type:'seg', opts:[ 'left', 'center', 'right', 'justify' ] },
			{ prop:'textTransform', label:'Transform', type:'seg', opts:[ 'none', 'uppercase', 'capitalize' ] },
			{ prop:'textDecoration', label:'Decoration', type:'seg', opts:[ 'none', 'underline' ] },
			{ prop:'color', label:'Text color', type:'color' }
		] },
		{ group:'Background & effects', icon:'droplet', items:[
			{ prop:'background', label:'Background', type:'color' },
			{ prop:'opacity', label:'Opacity', type:'text', ph:'1' },
			{ prop:'boxShadow', label:'Box shadow', type:'text', ph:'0 4px 12px rgba(0,0,0,.1)' }
		] },
		{ group:'Border', icon:'layout', items:[
			{ prop:'borderWidth', label:'Width', type:'num', unit:'px' },
			{ prop:'borderStyle', label:'Style', type:'seg', opts:[ 'none', 'solid', 'dashed' ] },
			{ prop:'borderColor', label:'Color', type:'color' },
			{ prop:'borderRadius', label:'Radius', type:'num', unit:'px' }
		] }
	];
	var canvasReady = false, cssShown = false, dbTimer;

	function mount() {
		var root = document.getElementById( 'velox-builder-root' );
		if ( ! root ) { return; }
		root.className = 'vb-app';
		root.innerHTML =
			topbarHTML() +
			'<div class="vb-body">' +
				spineHTML() +
				'<aside class="vb-inspector" id="vb-inspector"></aside>' +
				'<main class="vb-stage"><iframe id="vb-canvas" title="Canvas"></iframe></main>' +
			'</div>' +
			'<div class="vb-csspanel" id="vb-css"></div>' +
			'<div class="vb-histpanel" id="vb-hist"></div>' +
			'<div class="vb-structpanel" id="vb-struct"></div>' +
			'<div class="vb-addmenu" id="vb-addmenu"></div>';
		injectStyles();
		wireEvents();
		setNavUrls();
		store.subscribe( renderAll );
		store.subscribe( markDirty );
		store.subscribeLog( function () { if ( historyShown ) { renderHistoryPanel(); } } );
		renderActions();
		var fr = document.getElementById( 'vb-canvas' );
		fr.addEventListener( 'load', function () { canvasReady = true; if ( ! store.state ) { boot(); } else { injectCanvas(); } injectGlobalCss(); } );
		setTimeout( function () { if ( ! store.state ) { boot(); } }, 60 );
	}
	function boot() {
		if ( CFG.docId ) { loadDoc( CFG.docId ); setTimeout( function () { if ( ! store.state ) { store.init( initialDoc ); } }, 1200 ); }
		else { store.init( initialDoc ); }
	}
	function topbarHTML() {
		return '<div class="vb-top">' +
			// LEFT: logo (menu) + page-title picker
			'<div class="vb-tbc">' +
				'<button class="vb-brand" id="vb-brand" title="Velox">' + veloxLogo() + '</button>' +
				'<div class="vb-tsep"></div>' +
				'<div class="vb-pagepick" id="vb-pagepick">' +
					'<small>' + T( 'Editing' ) + '</small>' +
					'<b><input id="vb-title" class="vb-title" type="text" value="' + escapeHtml( docTitle ) + '" placeholder="' + T( 'Untitled' ) + '" spellcheck="false">' +
					'<button class="vb-pp-caret" id="vb-pp-caret" title="' + T( 'Switch page' ) + '">' + svg( 'chevron', 12 ) + '</button></b>' +
				'</div>' +
			'</div>' +
			// CENTER: breakpoints + undo/redo
			'<div class="vb-tbc vb-tbc-center">' +
				'<div class="vb-bp" id="vb-bp">' +
					'<button data-bp="base" class="on" title="Desktop">' + svg( 'monitor', 15 ) + '</button>' +
					'<button data-bp="tablet" title="Tablet">' + svg( 'tablet', 14 ) + '</button>' +
					'<button data-bp="mobile" title="Mobile">' + svg( 'smartphone', 14 ) + '</button>' +
				'</div>' +
				'<div class="vb-tsep"></div>' +
				'<button class="vb-ic" id="vb-undo" title="Undo">' + svg( 'undo', 16 ) + '</button>' +
				'<button class="vb-ic" id="vb-redo" title="Redo">' + svg( 'redo', 16 ) + '</button>' +
			'</div>' +
			// RIGHT: tool icons · [Save Publish View] · Exit-icon (with menu)
			'<div class="vb-tbc">' +
				'<button class="vb-ic" id="vb-structure" title="' + T( 'Structure' ) + '">' + svg( 'structure', 16 ) + '</button>' +
				'<button class="vb-ic" id="vb-search" title="' + T( 'Search' ) + '">' + svg( 'search', 16 ) + '</button>' +
				'<button class="vb-ic" id="vb-code" title="' + T( 'Page CSS / JS' ) + '">' + svg( 'code', 16 ) + '</button>' +
				'<button class="vb-ic" id="vb-history" title="' + T( 'History' ) + '">' + svg( 'clock', 16 ) + '</button>' +
				'<div class="vb-tsep"></div>' +
				'<span id="vb-actions"></span>' +
				'<a class="vb-btn vb-btn-ghost" id="vb-view" href="#" target="_blank" rel="noopener">' + T( 'View page' ) + '</a>' +
				'<div class="vb-tsep"></div>' +
				'<div class="vb-exitwrap">' +
					'<button class="vb-ic" id="vb-exit" title="' + T( 'Exit' ) + '">' + svg( 'exit', 17 ) + '</button>' +
					'<div class="vb-exitmenu" id="vb-exitmenu">' +
						'<a class="vb-bm-i" id="vb-exit-front" href="#" target="_blank" rel="noopener">' + svg( 'external', 15 ) + ' ' + T( 'Open frontend' ) + '</a>' +
						'<a class="vb-bm-i" id="vb-exit-back" href="' + ( CFG.backUrl || '#' ) + '">' + svg( 'home', 15 ) + ' ' + T( 'Go to backend' ) + '</a>' +
						'<a class="vb-bm-i" id="vb-exit-view" href="#" target="_blank" rel="noopener">' + svg( 'eye', 15 ) + ' ' + T( 'View page (new tab)' ) + '</a>' +
					'</div>' +
				'</div>' +
			'</div>' +
			// logo dropdown (hidden until brand clicked)
			'<div class="vb-brandmenu" id="vb-brandmenu">' +
				'<a class="vb-bm-i" href="' + ( CFG.settingsUrl || '#' ) + '">' + svg( 'gear', 15 ) + ' ' + T( 'Velox Builder settings' ) + '</a>' +
				'<a class="vb-bm-i" href="' + ( CFG.backUrl || '#' ) + '">' + svg( 'home', 15 ) + ' ' + T( 'Back to WordPress' ) + '</a>' +
			'</div>' +
			// page switcher (hidden until caret clicked)
			'<div class="vb-pageswitch" id="vb-pageswitch">' +
				'<div class="vb-ps-search"><span class="vb-ss-ic">' + svg( 'search', 13 ) + '</span><input id="vb-ps-search" placeholder="' + T( 'Search pages, posts, reusables…' ) + '"></div>' +
				'<div class="vb-ps-filters" id="vb-ps-filters">' +
					'<button class="vb-ps-f on" data-filter="all">' + T( 'All' ) + '</button>' +
					'<button class="vb-ps-f" data-filter="page">' + T( 'Pages' ) + '</button>' +
					'<button class="vb-ps-f" data-filter="post">' + T( 'Posts' ) + '</button>' +
					'<button class="vb-ps-f" data-filter="template">' + T( 'Templates' ) + '</button>' +
					'<button class="vb-ps-f" data-filter="reusable">' + T( 'Reusables' ) + '</button>' +
				'</div>' +
				'<div class="vb-ps-list" id="vb-ps-list"><div class="vb-ps-loading">' + T( 'Loading…' ) + '</div></div>' +
			'</div>' +
		'</div>';
	}
	function veloxLogo() {
		return '<svg viewBox="0 0 32 32" width="22" height="22" aria-hidden="true"><path d="M4 6l8 18 8-18h-4.2L12 16.5 8.2 6H4z" fill="#2ab7f1"/><path d="M20 6l-3.4 7.6L20.7 24 28 6h-8z" fill="#a06bff"/></svg>';
	}
	function spineHTML() {
		return '<aside class="vb-spine">' +
			'<div class="vb-spine-top">' +
				'<button class="vb-add-big" data-add>' + svg( 'plus', 17 ) + ' ' + T( 'Add element' ) + '</button>' +
				'<div class="vb-quick">' +
					'<a class="vb-qt" href="' + ( CFG.reusablesUrl || '#' ) + '">' + svg( 'copy', 15 ) + ' ' + T( 'Reusables' ) + '</a>' +
					'<a class="vb-qt" href="' + ( CFG.settingsUrl || '#' ) + '">' + svg( 'gear', 15 ) + ' ' + T( 'Settings' ) + '</a>' +
				'</div>' +
			'</div>' +
			'<div class="vb-spine-h"><span>' + T( 'Layers' ) + '</span></div>' +
			'<div class="vb-spine-search"><span class="vb-ss-ic">' + svg( 'search', 13 ) + '</span><input id="vb-layer-search" placeholder="' + T( 'Find layer…' ) + '"></div>' +
			'<div class="vb-tree" id="vb-tree"></div>' +
		'</aside>';
	}

	function renderAll( state ) {
		renderTree( state ); renderInspector( state ); renderTopbar( state ); renderCSSPanel();
		if ( structShown ) { renderStructPanel(); }
		injectCanvas();
	}
	function renderTopbar( state ) {
		var b = document.querySelectorAll( '#vb-bp button' );
		for ( var i = 0; i < b.length; i++ ) { b[ i ].classList.toggle( 'on', b[ i ].getAttribute( 'data-bp' ) === state.breakpoint ); }
		var bpl = document.getElementById( 'vb-bplabel' ); if ( bpl ) { bpl.textContent = BP_META[ state.breakpoint ].label; }
		document.getElementById( 'vb-undo' ).style.opacity = store.history.length > 1 ? 1 : 0.4;
		document.getElementById( 'vb-redo' ).style.opacity = store.future.length ? 1 : 0.4;
	}
	function renderTree( state ) {
		var html = '';
		function walk( nodes, depth ) {
			nodes.forEach( function ( n ) {
				html += '<div class="vb-tn ' + ( n.id === state.selection ? 'sel' : '' ) + '" data-node="' + n.id + '" draggable="true" style="padding-left:' + ( 8 + depth * 14 ) + 'px">' +
					'<span class="vb-tn-ic">' + svg( elIcon( n.el ), 13 ) + '</span><span class="vb-tn-name">' + n.el + '</span>' +
					'<span class="vb-tn-cls">' + ( n.classes[ 0 ] || '' ) + '</span></div>';
				if ( n.children ) { walk( n.children, depth + 1 ); }
			} );
		}
		walk( state.tree, 0 );
		document.getElementById( 'vb-tree' ).innerHTML = html;
	}
	function renderInspector( state ) {
		var node = findNode( state.tree, state.selection ), insp = document.getElementById( 'vb-inspector' );
		if ( ! node ) { insp.innerHTML = '<div class="vb-insp-empty">' + T( 'Select an element to style it.' ) + '</div>'; return; }
		var ac = state.activeClass, bp = state.breakpoint, st = state.state || 'normal';
		var tab = inspTab; // 'ess' | 'all' | 'set'
		var chips = node.classes.map( function ( c, i ) {
			return '<span class="vb-chip ' + ( i === 0 ? 'base' : 'combo' ) + ' ' + ( c === ac ? 'active' : '' ) + '" data-cls="' + c + '">' + c + '<span class="vb-chip-x" data-delchip="' + c + '">' + svg( 'x', 11 ) + '</span></span>';
		} ).join( '' );
		chips += '<span class="vb-chip vb-chip-add" data-addclass>+ ' + T( 'class' ) + '</span>';
		var acKind = node.classes.indexOf( ac ) === 0 ? 'base' : 'combo';
		var imgBtn = node.el === 'Image' ? '<button class="vb-imgbtn" data-pickimg="' + node.id + '">' + svg( 'image', 15 ) + ' ' + ( store.state.content[ node.id ] ? T( 'Replace image' ) : T( 'Choose image' ) ) + '</button>' : '';

		// Per-element inspector profiles: the ORDER groups appear in, and which of
		// them count as "Essentials" for this element type. Text-like elements lead
		// with Typography; containers lead with Layout; media with Size; etc.
		var PROFILES = {
			Heading:  { order:[ 'Typography', 'Spacing', 'Background & effects', 'Layout', 'Size', 'Border' ], ess:[ 'Typography', 'Spacing', 'Background & effects' ] },
			Text:     { order:[ 'Typography', 'Spacing', 'Background & effects', 'Layout', 'Size', 'Border' ], ess:[ 'Typography', 'Spacing', 'Background & effects' ] },
			Button:   { order:[ 'Typography', 'Background & effects', 'Spacing', 'Border', 'Layout', 'Size' ], ess:[ 'Typography', 'Background & effects', 'Spacing', 'Border' ] },
			Image:    { order:[ 'Size', 'Spacing', 'Border', 'Background & effects', 'Layout' ], ess:[ 'Size', 'Spacing', 'Border' ] },
			Video:    { order:[ 'Size', 'Spacing', 'Border', 'Background & effects', 'Layout' ], ess:[ 'Size', 'Spacing', 'Border' ] },
			Icon:     { order:[ 'Typography', 'Size', 'Spacing', 'Background & effects', 'Layout' ], ess:[ 'Typography', 'Size', 'Spacing' ] },
			Section:  { order:[ 'Layout', 'Spacing', 'Size', 'Background & effects', 'Border', 'Typography' ], ess:[ 'Layout', 'Spacing', 'Background & effects' ] },
			Columns:  { order:[ 'Layout', 'Spacing', 'Size', 'Background & effects', 'Border', 'Typography' ], ess:[ 'Layout', 'Spacing', 'Background & effects' ] },
			Div:      { order:[ 'Layout', 'Spacing', 'Size', 'Background & effects', 'Border', 'Typography' ], ess:[ 'Layout', 'Spacing', 'Background & effects' ] },
			Reviews:  { order:[ 'Spacing', 'Size', 'Background & effects', 'Border', 'Layout' ], ess:[ 'Spacing', 'Size' ] },
			WP:       { order:[ 'Typography', 'Spacing', 'Layout', 'Size', 'Background & effects', 'Border' ], ess:[ 'Typography', 'Spacing' ] }
		};
		var DEFAULT_PROFILE = { order:[ 'Layout', 'Size', 'Spacing', 'Typography', 'Background & effects', 'Border' ], ess:[ 'Layout', 'Spacing', 'Typography', 'Background & effects' ] };
		var profile = PROFILES[ node.el ] || DEFAULT_PROFILE;
		var essSet = {}; profile.ess.forEach( function ( g ) { essSet[ g ] = 1; } );
		// build the ordered, filtered group list for this element
		var byName = {}; CONTROLS.forEach( function ( g ) { byName[ g.group ] = g; } );
		var ordered = profile.order.map( function ( n ) { return byName[ n ]; } ).filter( Boolean );

		var body = '';
		if ( tab === 'set' ) {
			body = settingsTabHTML( node );
		} else {
			var groups = ordered.filter( function ( g ) { return tab === 'all' ? true : essSet[ g.group ]; } );
			groups.forEach( function ( g, gi ) {
				var closed = gi > 1 ? ' closed' : '';
				body += '<div class="vb-block' + closed + '"><div class="vb-block-h" data-block><span class="vb-block-ic">' + svg( g.icon, 15 ) + '</span><b>' + g.group + '</b><span class="vb-block-cv">' + svg( 'chevron', 12 ) + '</span></div><div class="vb-block-b">';
				g.items.forEach( function ( it ) {
					var res = resolveProperty( node, bp, it.prop, st ), dot = dotFor( res, ac, bp ), val = res.value, ctrl = '';
					if ( it.type === 'seg' ) {
						ctrl = '<div class="vb-seg">' + it.opts.map( function ( o ) { return '<button class="' + ( val === o ? 'on' : '' ) + '" data-set="' + it.prop + '" data-val="' + o + '">' + o.replace( 'flex-', '' ).replace( 'space-', '' ) + '</button>'; } ).join( '' ) + '</div>';
					} else if ( it.type === 'num' ) {
						ctrl = '<div class="vb-row"><input class="vb-inp num" data-setnum="' + it.prop + '" value="' + ( val != null ? val : '' ) + '" placeholder="—"><span class="vb-unit">' + it.unit + '</span></div>';
					} else if ( it.type === 'text' ) {
						ctrl = '<div class="vb-row"><input class="vb-inp" data-set="' + it.prop + '" value="' + ( val != null ? String( val ).replace( /"/g, '&quot;' ) : '' ) + '" placeholder="' + ( it.ph || '' ) + '"></div>';
					} else if ( it.type === 'color' ) {
						var hex = ( val && val[ 0 ] === '#' ) ? val : '#000000';
						ctrl = '<div class="vb-row"><input type="color" class="vb-swatch" data-setcolor="' + it.prop + '" value="' + hex + '"><input class="vb-inp" data-set="' + it.prop + '" value="' + ( val != null ? val : '' ) + '" placeholder="—"></div>';
					}
					body += '<div class="vb-f"><div class="vb-f-lbl"><span class="vb-src ' + dot.cls + '" title="' + dot.tip + '"></span> ' + it.label + '</div>' + ctrl + '</div>';
				} );
				body += '</div></div>';
			} );
		}
		insp.innerHTML =
			'<div class="vb-insp-head"><span class="vb-insp-ic">' + svg( elIcon( node.el ), 16 ) + '</span><div class="vb-insp-tx"><b>' + node.el + '</b><small>#' + node.id + ' · ' + node.tag + '</small></div>' +
				'<span class="vb-insp-acts"><button class="vb-ia" data-dup title="' + T( 'Duplicate' ) + '">' + svg( 'copy', 14 ) + '</button><button class="vb-ia vb-ia-del" data-del title="' + T( 'Delete' ) + '">' + svg( 'trash', 14 ) + '</button></span></div>' +
			'<div class="vb-classbar"><div class="vb-cb-l">' + T( 'Styling class' ) + '</div>' +
				'<div class="vb-active-class ' + acKind + '"><span class="vb-ac-dot"></span><span class="vb-ac-name">' + ac + '</span><span class="vb-ac-kind">' + ( acKind === 'base' ? 'Base' : 'Combo' ) + '</span></div>' +
				'<div class="vb-chips">' + chips + '</div>' +
				'<div class="vb-states">' +
					[ 'normal', 'hover', 'focus' ].map( function ( s2 ) { return '<button class="vb-state' + ( st === s2 ? ' on' : '' ) + '" data-state="' + s2 + '">' + ( s2 === 'normal' ? T( 'Normal' ) : ':' + s2 ) + '</button>'; } ).join( '' ) +
				'</div>' +
				'<div class="vb-bp-note">' + ( st !== 'normal' ? T( 'Editing' ) + ' :' + st + ' — ' + T( 'falls back to normal' ) : ( bp === 'base' ? T( 'Editing at desktop' ) : T( 'Editing at' ) + ' ' + bp ) ) + '</div></div>' +
			'<div class="vb-tabs">' +
				'<button class="vb-tab' + ( tab === 'ess' ? ' on' : '' ) + '" data-tab="ess">' + T( 'Essentials' ) + '</button>' +
				'<button class="vb-tab' + ( tab === 'all' ? ' on' : '' ) + '" data-tab="all">' + T( 'All styles' ) + '</button>' +
				'<button class="vb-tab' + ( tab === 'set' ? ' on' : '' ) + '" data-tab="set">' + T( 'Settings' ) + '</button>' +
			'</div>' +
			imgBtn +
			'<div class="vb-controls">' + body + '</div>';
	}
	/* Settings tab: element tag, link href, custom ID, and (for Reviews) the
	   connection + preset pickers. */
	function settingsTabHTML( node ) {
		var s = '<div class="vb-setwrap">';
		// Heading level
		if ( node.el === 'Heading' ) {
			s += '<div class="vb-f"><div class="vb-f-lbl">' + T( 'Heading level' ) + '</div><div class="vb-seg">' +
				[ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ].map( function ( t ) { return '<button class="' + ( node.tag === t ? 'on' : '' ) + '" data-settag="' + t + '">' + t.toUpperCase() + '</button>'; } ).join( '' ) + '</div></div>';
		}
		// Link href
		if ( node.el === 'Button' || node.tag === 'a' ) {
			s += '<div class="vb-f"><div class="vb-f-lbl">' + T( 'Link URL' ) + '</div><div class="vb-row"><input class="vb-inp" data-sethref value="' + ( node.href ? String( node.href ).replace( /"/g, '&quot;' ) : '' ) + '" placeholder="https://"></div></div>';
		}
		// Reviews connection + preset (wave 3 wiring — dropdowns from plugin data)
		if ( node.el === 'Reviews' ) {
			var conns = CFG.reviewConnections || [], presets = CFG.reviewPresets || [];
			s += '<div class="vb-f"><div class="vb-f-lbl">' + T( 'Reviews connection' ) + '</div><div class="vb-row"><select class="vb-inp" data-setconn>' +
				'<option value="">' + T( '— choose —' ) + '</option>' + conns.map( function ( c ) { return '<option value="' + c.id + '"' + ( node.conn === c.id ? ' selected' : '' ) + '>' + escapeHtml( c.name ) + '</option>'; } ).join( '' ) + '</select></div></div>';
			s += '<div class="vb-f"><div class="vb-f-lbl">' + T( 'Design preset' ) + '</div><div class="vb-row"><select class="vb-inp" data-setpreset>' +
				'<option value="">' + T( '— choose —' ) + '</option>' + presets.map( function ( p ) { return '<option value="' + p.id + '"' + ( node.preset === p.id ? ' selected' : '' ) + '>' + escapeHtml( p.name ) + '</option>'; } ).join( '' ) + '</select></div></div>';
		}
		// Custom ID note
		s += '<div class="vb-f"><div class="vb-f-lbl">' + T( 'Element ID' ) + '</div><div class="vb-row"><input class="vb-inp" value="' + node.id + '" readonly style="opacity:.6"></div></div>';
		s += '</div>';
		return s;
	}
	/* ---------- Global CSS files editor (right-side panel) ---------- */
	var cssFiles = ( CFG.globalCss && CFG.globalCss.length ) ? CFG.globalCss.slice() : [ { name:'global.css', css:'' } ];
	var cssActive = 0, cssSaveTimer;
	function renderCSSPanel() {
		var box = document.getElementById( 'vb-css' ); if ( ! box ) { return; }
		if ( ! cssShown ) { box.style.display = 'none'; injectGlobalCss(); return; }
		box.style.display = 'flex';
		var f = cssFiles[ cssActive ] || cssFiles[ 0 ];
		box.innerHTML =
			'<div class="vb-css-top"><b>' + T( 'Global CSS' ) + '</b><button class="vb-css-x" id="vb-css-close">' + svg( 'x', 14 ) + '</button></div>' +
			'<div class="vb-css-files">' +
				cssFiles.map( function ( fl, i ) { return '<button class="vb-css-file' + ( i === cssActive ? ' on' : '' ) + '" data-cssfile="' + i + '">' + svg( 'code', 12 ) + '<span>' + escapeHtml( fl.name ) + '</span></button>'; } ).join( '' ) +
				'<button class="vb-css-new" id="vb-css-new">' + svg( 'plus', 13 ) + ' ' + T( 'New file' ) + '</button>' +
			'</div>' +
			'<div class="vb-css-name"><input id="vb-css-name" value="' + escapeHtml( f.name ) + '" spellcheck="false">' +
				( cssFiles.length > 1 ? '<button class="vb-css-del" id="vb-css-del" title="' + T( 'Delete file' ) + '">' + svg( 'trash', 13 ) + '</button>' : '' ) + '</div>' +
			'<textarea id="vb-css-code" class="vb-css-code" spellcheck="false" placeholder="/* ' + T( 'Global CSS — applies to every page' ) + ' */">' + escapeHtml( f.css ) + '</textarea>' +
			'<div class="vb-css-foot"><span id="vb-css-status">' + T( 'Applies to every Velox page' ) + '</span></div>';
	}
	/* Inject the concatenated global CSS live into the canvas iframe. */
	function injectGlobalCss() {
		var doc = document.getElementById( 'vb-canvas' ); doc = doc && doc.contentDocument; if ( ! doc ) { return; }
		var tag = doc.getElementById( 'vb-global-css' );
		if ( ! tag ) { tag = doc.createElement( 'style' ); tag.id = 'vb-global-css'; doc.head.appendChild( tag ); }
		tag.textContent = cssFiles.map( function ( f ) { return f.css || ''; } ).join( '\n' );
	}
	function saveGlobalCss() {
		if ( ! CFG.ajaxurl ) { return; }
		var st = document.getElementById( 'vb-css-status' ); if ( st ) { st.textContent = T( 'Saving…' ); }
		var body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', 'builder_css_save' ); body.set( 'nonce', CFG.nonce || '' );
		body.set( 'files', JSON.stringify( cssFiles ) );
		fetch( CFG.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) { var s = document.getElementById( 'vb-css-status' ); if ( s ) { s.textContent = res && res.success ? T( 'Saved — applies to every page' ) : T( 'Save failed' ); } } )
			.catch( function () { var s = document.getElementById( 'vb-css-status' ); if ( s ) { s.textContent = T( 'Save failed' ); } } );
	}
	function scheduleCssSave() { clearTimeout( cssSaveTimer ); cssSaveTimer = setTimeout( saveGlobalCss, 700 ); injectGlobalCss(); }

	/* ---------- Structure panel (collapsible full-page outline) ---------- */
	var structShown = false, structCollapsed = {};
	function renderStructPanel() {
		var box = document.getElementById( 'vb-struct' ); if ( ! box ) { return; }
		if ( ! structShown ) { box.style.display = 'none'; return; }
		box.style.display = 'flex';
		var sel = store.state ? store.state.selection : null;
		function row( node, depth ) {
			var kids = node.children || [];
			var hasKids = kids.length > 0;
			var collapsed = !! structCollapsed[ node.id ];
			var name = ( node.el || 'El' ) + ( node.classes && node.classes[ 0 ] ? ' · ' + node.classes[ 0 ] : '' );
			var caret = hasKids ? '<span class="vb-st-caret' + ( collapsed ? ' closed' : '' ) + '" data-stcaret="' + node.id + '">' + svg( 'chevron', 11 ) + '</span>' : '<span class="vb-st-spacer"></span>';
			var html = '<div class="vb-st-row' + ( node.id === sel ? ' sel' : '' ) + '" data-stnode="' + node.id + '" style="padding-left:' + ( 8 + depth * 15 ) + 'px">' + caret + '<span class="vb-st-ic">' + svg( elIcon( node.el ), 13 ) + '</span><span class="vb-st-l">' + escapeHtml( name ) + '</span></div>';
			if ( hasKids && ! collapsed ) { html += kids.map( function ( k ) { return row( k, depth + 1 ); } ).join( '' ); }
			return html;
		}
		var tree = store.state ? store.state.tree : [];
		var body = tree.length ? tree.map( function ( n ) { return row( n, 0 ); } ).join( '' ) : '<div class="vb-hist-empty">' + T( 'Nothing on the page yet.' ) + '</div>';
		box.innerHTML =
			'<div class="vb-hist-top"><b>' + T( 'Structure' ) + '</b><button class="vb-css-x" id="vb-struct-close">' + svg( 'x', 14 ) + '</button></div>' +
			'<div class="vb-st-tree">' + body + '</div>';
	}

	/* ---------- Session history (in-memory, cleared on close) ---------- */
	var historyShown = false;
	function timeAgo( ts ) {
		var s = Math.floor( ( Date.now() - ts ) / 1000 );
		if ( s < 5 ) { return T( 'just now' ); }
		if ( s < 60 ) { return s + 's ' + T( 'ago' ); }
		if ( s < 3600 ) { return Math.floor( s / 60 ) + 'm ' + T( 'ago' ); }
		return Math.floor( s / 3600 ) + 'h ' + T( 'ago' );
	}
	function renderHistoryPanel() {
		var box = document.getElementById( 'vb-hist' ); if ( ! box ) { return; }
		if ( ! historyShown ) { box.style.display = 'none'; return; }
		box.style.display = 'flex';
		var log = store.log;
		var items = log.length
			? log.slice().reverse().map( function ( l ) {
				return '<button class="vb-hist-i" data-revert="' + l.idx + '"><span class="vb-hist-dot"></span><span class="vb-hist-l">' + escapeHtml( l.label ) + '</span><span class="vb-hist-t">' + timeAgo( l.at ) + '</span></button>';
			} ).join( '' )
			: '<div class="vb-hist-empty">' + T( 'No changes yet. Your edits this session will appear here.' ) + '</div>';
		box.innerHTML =
			'<div class="vb-hist-top"><b>' + T( 'History' ) + '</b><button class="vb-css-x" id="vb-hist-close">' + svg( 'x', 14 ) + '</button></div>' +
			'<div class="vb-hist-note">' + T( 'This session only — cleared when you close the editor.' ) + '</div>' +
			'<div class="vb-hist-list">' + items + '</div>';
	}

	function wireEvents() {
		document.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-add]' ) ) { toggleAddMenu( e.target.closest( '[data-add]' ) ); return; }
			var ins = e.target.closest( '[data-insert]' ); if ( ins ) { insertNode( ins.getAttribute( 'data-insert' ) ); closeAddMenu(); return; }
			var insr = e.target.closest( '[data-insert-reuse]' ); if ( insr ) { insertReusable( +insr.getAttribute( 'data-insert-reuse' ) ); closeAddMenu(); return; }
			var acc = e.target.closest( '[data-acc]' ); if ( acc ) { acc.parentElement.classList.toggle( 'closed' ); return; }
			if ( e.target.closest( '[data-dup]' ) ) { duplicateNode( store.state.selection ); return; }
			if ( e.target.closest( '[data-del]' ) ) { deleteNode( store.state.selection ); return; }
			var pick = e.target.closest( '[data-pickimg]' ); if ( pick ) { openMediaPicker( pick.getAttribute( 'data-pickimg' ) ); return; }
			if ( e.target.closest( '#vb-code' ) ) { cssShown = ! cssShown; if ( cssShown ) { closeAllPanels( 'css' ); } renderCSSPanel(); return; }
			if ( e.target.closest( '#vb-css-close' ) ) { cssShown = false; renderCSSPanel(); return; }
			if ( e.target.closest( '#vb-css-new' ) ) { cssFiles.push( { name:'file-' + ( cssFiles.length + 1 ) + '.css', css:'' } ); cssActive = cssFiles.length - 1; renderCSSPanel(); saveGlobalCss(); return; }
			if ( e.target.closest( '#vb-css-del' ) ) { if ( cssFiles.length > 1 && confirm( T( 'Delete this CSS file?' ) ) ) { cssFiles.splice( cssActive, 1 ); cssActive = 0; renderCSSPanel(); scheduleCssSave(); } return; }
			var cf = e.target.closest( '[data-cssfile]' ); if ( cf ) { cssActive = +cf.getAttribute( 'data-cssfile' ); renderCSSPanel(); return; }
			if ( e.target.closest( '#vb-search' ) ) { toggleSwitcher(); e.stopPropagation(); return; }
			if ( e.target.closest( '#vb-exit' ) ) { var em = document.getElementById( 'vb-exitmenu' ); var was = em.classList.contains( 'open' ); closeAllPanels(); em.classList.toggle( 'open', ! was ); e.stopPropagation(); return; }
			if ( ! e.target.closest( '.vb-exitwrap' ) ) { var em2 = document.getElementById( 'vb-exitmenu' ); if ( em2 ) { em2.classList.remove( 'open' ); } }
			if ( e.target.closest( '#vb-structure' ) ) { structShown = ! structShown; if ( structShown ) { closeAllPanels( 'struct' ); } renderStructPanel(); return; }
			if ( e.target.closest( '#vb-struct-close' ) ) { structShown = false; renderStructPanel(); return; }
			var stc = e.target.closest( '[data-stcaret]' ); if ( stc ) { var sid = stc.getAttribute( 'data-stcaret' ); structCollapsed[ sid ] = ! structCollapsed[ sid ]; renderStructPanel(); e.stopPropagation(); return; }
			var stn = e.target.closest( '[data-stnode]' ); if ( stn ) { store.commit( function ( s ) { s.selection = stn.getAttribute( 'data-stnode' ); resetActiveClass( s ); }, false ); return; }
			if ( e.target.closest( '#vb-history' ) ) { historyShown = ! historyShown; if ( historyShown ) { closeAllPanels( 'hist' ); } renderHistoryPanel(); return; }
			if ( e.target.closest( '#vb-hist-close' ) ) { historyShown = false; renderHistoryPanel(); return; }
			var hr = e.target.closest( '[data-revert]' ); if ( hr ) { store.revertTo( +hr.getAttribute( 'data-revert' ) ); return; }
			if ( e.target.closest( '#vb-save' ) ) { saveDoc(); return; }
			if ( e.target.closest( '#vb-publish' ) ) { publishDoc(); return; }
			if ( e.target.closest( '#vb-pp-caret' ) || e.target.id === 'vb-title' && false ) { toggleSwitcher(); e.stopPropagation(); return; }
			var psf = e.target.closest( '.vb-ps-f' ); if ( psf ) { switcherFilter = psf.getAttribute( 'data-filter' ); document.querySelectorAll( '.vb-ps-f' ).forEach( function ( x ) { x.classList.remove( 'on' ); } ); psf.classList.add( 'on' ); renderSwitcher(); e.stopPropagation(); return; }
			if ( ! e.target.closest( '#vb-pageswitch' ) && ! e.target.closest( '#vb-pp-caret' ) ) { var ps = document.getElementById( 'vb-pageswitch' ); if ( ps ) { ps.classList.remove( 'open' ); } }
			if ( e.target.closest( '#vb-brand' ) ) { var wasOpen = document.getElementById( 'vb-brandmenu' ).classList.contains( 'open' ); closeAllPanels( 'brand' ); if ( ! wasOpen ) { document.getElementById( 'vb-brandmenu' ).classList.add( 'open' ); } e.stopPropagation(); return; }
			if ( ! e.target.closest( '#vb-brandmenu' ) ) { var bm = document.getElementById( 'vb-brandmenu' ); if ( bm ) { bm.classList.remove( 'open' ); } }
			var tn = e.target.closest( '.vb-tn' ); if ( tn ) { store.commit( function ( s ) { s.selection = tn.getAttribute( 'data-node' ); resetActiveClass( s ); }, false ); return; }
			var tabBtn = e.target.closest( '[data-tab]' ); if ( tabBtn ) { inspTab = tabBtn.getAttribute( 'data-tab' ); renderInspector( store.state ); return; }
			var delchip = e.target.closest( '[data-delchip]' ); if ( delchip ) { e.stopPropagation(); removeClassFromNode( delchip.getAttribute( 'data-delchip' ) ); return; }
			if ( e.target.closest( '[data-addclass]' ) ) { addClassToSelected(); return; }
			var settag = e.target.closest( '[data-settag]' ); if ( settag ) { setNodeTag( settag.getAttribute( 'data-settag' ) ); return; }
			var chip = e.target.closest( '.vb-chip' ); if ( chip && ! chip.classList.contains( 'vb-chip-add' ) ) { store.commit( function ( s ) { s.activeClass = chip.getAttribute( 'data-cls' ); }, false ); return; }
			var blk = e.target.closest( '[data-block]' ); if ( blk ) { blk.parentElement.classList.toggle( 'closed' ); return; }
			var stbtn = e.target.closest( '[data-state]' ); if ( stbtn ) { store.commit( function ( s ) { s.state = stbtn.getAttribute( 'data-state' ); }, false ); return; }
			var seg = e.target.closest( '[data-set][data-val]' ); if ( seg ) { setProp( seg.getAttribute( 'data-set' ), seg.getAttribute( 'data-val' ) ); return; }
			var bp = e.target.closest( '#vb-bp button' ); if ( bp ) { store.commit( function ( s ) { s.breakpoint = bp.getAttribute( 'data-bp' ); }, false ); resizeCanvas( bp.getAttribute( 'data-bp' ) ); return; }
			if ( ! e.target.closest( '.vb-addmenu' ) ) { closeAddMenu(); }
		} );
		document.addEventListener( 'input', function ( e ) {
			if ( e.target.id === 'vb-title' ) { docTitle = e.target.value.trim() || 'Untitled'; markDirty(); if ( everPublished ) { setSaveState( 'idle' ); } return; }
			if ( e.target.id === 'vb-layer-search' ) { filterLayers( e.target.value.trim().toLowerCase() ); return; }
			if ( e.target.id === 'vb-ap-search' ) { renderAddBody( e.target.value ); return; }
			if ( e.target.id === 'vb-ps-search' ) { switcherQuery = e.target.value.trim(); renderSwitcher(); return; }
			if ( e.target.id === 'vb-css-code' ) { if ( cssFiles[ cssActive ] ) { cssFiles[ cssActive ].css = e.target.value; scheduleCssSave(); } return; }
			if ( e.target.id === 'vb-css-name' ) { if ( cssFiles[ cssActive ] ) { cssFiles[ cssActive ].name = e.target.value; var tab = document.querySelector( '.vb-css-file.on span' ); if ( tab ) { tab.textContent = e.target.value; } scheduleCssSave(); } return; }
			if ( e.target.hasAttribute( 'data-sethref' ) ) { var hv = e.target.value; store.commit( function ( s ) { var n = findNode( s.tree, s.selection ); if ( n ) { n.href = hv; } } ); return; }
			if ( e.target.hasAttribute( 'data-setconn' ) ) { var cv = e.target.value; store.commit( function ( s ) { var n = findNode( s.tree, s.selection ); if ( n ) { n.conn = cv; } } ); return; }
			if ( e.target.hasAttribute( 'data-setpreset' ) ) { var pv = e.target.value; store.commit( function ( s ) { var n = findNode( s.tree, s.selection ); if ( n ) { n.preset = pv; } } ); return; }
			var n = e.target.closest( '[data-setnum]' );
			if ( n ) { var v = e.target.value.trim(); clearTimeout( dbTimer ); dbTimer = setTimeout( function () { if ( v === '' ) { removeProp( n.getAttribute( 'data-setnum' ) ); } else { setProp( n.getAttribute( 'data-setnum' ), v ); } }, 150 ); return; }
			var c = e.target.closest( '[data-setcolor]' ); if ( c ) { setProp( c.getAttribute( 'data-setcolor' ), e.target.value ); return; }
			var t = e.target.closest( 'input.vb-inp[data-set]' ); if ( t ) { clearTimeout( dbTimer ); dbTimer = setTimeout( function () { setProp( t.getAttribute( 'data-set' ), e.target.value ); }, 150 ); return; }
		} );
		document.getElementById( 'vb-undo' ).addEventListener( 'click', function () { store.undo(); } );
		document.getElementById( 'vb-redo' ).addEventListener( 'click', function () { store.redo(); } );
		document.addEventListener( 'keydown', function ( e ) {
			if ( ( e.metaKey || e.ctrlKey ) && e.key === 'z' ) { e.preventDefault(); if ( e.shiftKey ) { store.redo(); } else { store.undo(); } }
			if ( ( e.metaKey || e.ctrlKey ) && e.key === 's' ) { e.preventDefault(); saveDoc(); }
			if ( e.key === 'Escape' ) { closeAddMenu(); }
			if ( ( e.key === 'Delete' || e.key === 'Backspace' ) && e.target === document.body && store.state && store.state.selection ) { e.preventDefault(); deleteNode( store.state.selection ); }
		} );
		wireDrag();
	}
	function toggleAddMenu( anchor ) {
		var m = document.getElementById( 'vb-addmenu' );
		if ( m.classList.contains( 'open' ) ) { closeAddMenu(); return; }
		closeAllPanels( 'add' );
		renderAddPanel( '' );
		m.classList.add( 'open' );
		var si = document.getElementById( 'vb-ap-search' ); if ( si ) { si.focus(); }
	}
	function accGroups() {
		var groups = CAT_GROUPS.slice();
		var reuse = CFG.reusables || [];
		if ( reuse.length ) { groups = groups.concat( [ { name:'Reusables', icon:'copy', reuse:true, items:reuse.map( function ( r ) { return { key:'reuse-' + r.id, label:r.title, el:'Reusable', reuseId:r.id }; } ) } ] ); }
		return groups;
	}
	function addBodyHTML( filter ) {
		var q = ( filter || '' ).toLowerCase();
		var body = accGroups().map( function ( g, gi ) {
			var items = g.items.filter( function ( it ) { return ! q || it.label.toLowerCase().indexOf( q ) > -1; } );
			if ( ! items.length ) { return ''; }
			var open = q ? true : gi < 2;
			var cells = items.map( function ( it ) {
				var attr = g.reuse ? 'data-insert-reuse="' + it.reuseId + '"' : 'data-insert="' + it.key + '"';
				var badge = it.badge ? '<span class="vb-el-badge">' + it.badge + '</span>' : '';
				return '<button class="vb-el" ' + attr + '><span class="vb-el-ic">' + svg( elIcon( it.el ), 18 ) + '</span><span class="vb-el-l">' + escapeHtml( it.label ) + badge + '</span></button>';
			} ).join( '' );
			return '<div class="vb-acc' + ( open ? '' : ' closed' ) + '"><div class="vb-acc-h" data-acc><span class="vb-acc-ic">' + svg( g.icon, 17 ) + '</span><b>' + g.name + '</b><span class="vb-acc-ct">' + items.length + '</span><span class="vb-acc-cv">' + svg( 'chevron', 12 ) + '</span></div><div class="vb-acc-b">' + cells + '</div></div>';
		} ).join( '' );
		return body || '<div class="vb-ap-none">' + T( 'No elements match.' ) + '</div>';
	}
	function renderAddBody( filter ) { var b = document.querySelector( '#vb-addmenu .vb-ap-body' ); if ( b ) { b.innerHTML = addBodyHTML( filter ); } }
	function renderAddPanel( filter ) {
		var m = document.getElementById( 'vb-addmenu' );
		m.innerHTML =
			'<div class="vb-ap-h"><span class="vb-ap-plus">' + svg( 'plus', 15 ) + '</span><b>' + T( 'Add element' ) + '</b><button class="vb-ap-x" data-add>' + svg( 'x', 15 ) + '</button></div>' +
			'<div class="vb-ap-search"><span class="vb-ss-ic">' + svg( 'search', 13 ) + '</span><input id="vb-ap-search" placeholder="' + T( 'Type to filter elements…' ) + '" value="' + escapeHtml( filter || '' ) + '"></div>' +
			'<div class="vb-ap-body">' + addBodyHTML( filter ) + '</div>';
	}
	function escapeHtml( s ) { return String( s ).replace( /[&<>"]/g, function ( c ) { return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;' }[ c ]; } ); }
	function reusableById( id ) { return ( CFG.reusables || [] ).filter( function ( r ) { return r.id === +id; } )[ 0 ] || null; }
	/* Populate the View button + exit-menu destinations from the boot config. */
	function setNavUrls() {
		var front = CFG.frontUrl || '', preview = CFG.previewUrl || CFG.frontUrl || '', back = CFG.backUrl || '#';
		function set( id, href, hide ) { var el = document.getElementById( id ); if ( ! el ) { return; } if ( href ) { el.href = href; el.style.display = ''; } else if ( hide ) { el.style.display = 'none'; } }
		set( 'vb-view', preview, true );
		set( 'vb-exit-front', front, false );
		set( 'vb-exit-view', preview, false );
		var b = document.getElementById( 'vb-exit-back' ); if ( b ) { b.href = back; }
	}
	function closeAddMenu() { var m = document.getElementById( 'vb-addmenu' ); if ( m ) { m.classList.remove( 'open' ); } }
	/* Only one side panel / dropdown open at a time. */
	function closeAllPanels( except ) {
		if ( except !== 'add' ) { var a = document.getElementById( 'vb-addmenu' ); if ( a ) { a.classList.remove( 'open' ); } }
		if ( except !== 'css' ) { cssShown = false; var c = document.getElementById( 'vb-css' ); if ( c ) { c.style.display = 'none'; } }
		if ( except !== 'hist' ) { historyShown = false; var h = document.getElementById( 'vb-hist' ); if ( h ) { h.style.display = 'none'; } }
		if ( except !== 'struct' ) { structShown = false; var st = document.getElementById( 'vb-struct' ); if ( st ) { st.style.display = 'none'; } }
		if ( except !== 'brand' ) { var bm = document.getElementById( 'vb-brandmenu' ); if ( bm ) { bm.classList.remove( 'open' ); } }
		if ( except !== 'switch' ) { var ps = document.getElementById( 'vb-pageswitch' ); if ( ps ) { ps.classList.remove( 'open' ); } }
	}

	/* ---------- page switcher ---------- */
	var switcherData = null, switcherFilter = 'all', switcherQuery = '';
	function toggleSwitcher() {
		var d = document.getElementById( 'vb-pageswitch' );
		if ( d.classList.contains( 'open' ) ) { d.classList.remove( 'open' ); return; }
		closeAllPanels( 'switch' );
		d.classList.add( 'open' );
		var si = document.getElementById( 'vb-ps-search' ); if ( si ) { si.focus(); }
		if ( ! switcherData ) { fetchSwitcher(); } else { renderSwitcher(); }
	}
	function fetchSwitcher() {
		if ( ! CFG.ajaxurl ) { return; }
		var body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', 'builder_switcher_list' ); body.set( 'nonce', CFG.nonce || '' );
		fetch( CFG.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) { switcherData = res && res.success ? res.data : { velox:[], wp:[] }; renderSwitcher(); } )
			.catch( function () { switcherData = { velox:[], wp:[] }; renderSwitcher(); } );
	}
	function renderSwitcher() {
		var list = document.getElementById( 'vb-ps-list' ); if ( ! list || ! switcherData ) { return; }
		var q = switcherQuery.toLowerCase(), f = switcherFilter;
		var velox = ( switcherData.velox || [] ).filter( function ( d ) {
			if ( f !== 'all' && d.kind !== f ) { return false; }
			return ! q || d.title.toLowerCase().indexOf( q ) > -1;
		} );
		var wp = ( switcherData.wp || [] ).filter( function ( d ) {
			if ( f !== 'all' && d.type !== f ) { return false; }
			return ! q || d.title.toLowerCase().indexOf( q ) > -1;
		} );
		var badge = { published:[ T( 'Published' ), 'pub' ], draft:[ T( 'Draft' ), 'draft' ], template:[ T( 'Template' ), 'draft' ], reusable:[ T( 'Reusable' ), 'draft' ] };
		var html = '';
		if ( velox.length ) {
			html += '<div class="vb-ps-sec">' + T( 'Velox pages' ) + '</div>';
			html += velox.map( function ( d ) {
				var b = d.kind === 'reusable' ? badge.reusable : ( d.kind === 'template' ? badge.template : ( badge[ d.status ] || badge.draft ) );
				var ic = d.kind === 'reusable' ? 'copy' : 'file';
				return '<a class="vb-ps-i" href="' + d.url + '"><span class="vb-ps-ic">' + svg( ic, 15 ) + '</span><span class="vb-ps-t">' + escapeHtml( d.title ) + '</span><span class="vb-ps-badge b-' + b[ 1 ] + '">' + b[ 0 ] + '</span></a>';
			} ).join( '' );
		}
		if ( wp.length ) {
			html += '<div class="vb-ps-sec">' + T( 'WordPress — not built with Velox' ) + '</div>';
			html += wp.map( function ( d ) {
				return '<a class="vb-ps-i" href="' + d.url + '"><span class="vb-ps-ic">' + svg( 'file', 15 ) + '</span><span class="vb-ps-t">' + escapeHtml( d.title ) + '</span><span class="vb-ps-badge b-wp">' + T( 'Build' ) + ' →</span></a>';
			} ).join( '' );
		}
		list.innerHTML = html || '<div class="vb-ps-loading">' + T( 'Nothing found.' ) + '</div>';
	}
	function filterLayers( q ) {
		var rows = document.querySelectorAll( '#vb-tree .vb-tn' );
		for ( var i = 0; i < rows.length; i++ ) {
			var txt = rows[ i ].textContent.toLowerCase();
			rows[ i ].style.display = ( ! q || txt.indexOf( q ) > -1 ) ? '' : 'none';
		}
	}

	/* ---------- drag-and-drop wiring for the layers tree ---------- */
	var dragId = null;
	function wireDrag() {
		var tree = document.getElementById( 'vb-tree' );
		tree.addEventListener( 'dragstart', function ( e ) {
			var tn = e.target.closest( '.vb-tn' ); if ( ! tn ) { return; }
			dragId = tn.getAttribute( 'data-node' );
			e.dataTransfer.effectAllowed = 'move';
			try { e.dataTransfer.setData( 'text/plain', dragId ); } catch ( err ) {}
			setTimeout( function () { tn.classList.add( 'dragging' ); }, 0 );
		} );
		tree.addEventListener( 'dragend', function () {
			dragId = null;
			clearDropMarks();
			var d = tree.querySelector( '.dragging' ); if ( d ) { d.classList.remove( 'dragging' ); }
		} );
		tree.addEventListener( 'dragover', function ( e ) {
			var tn = e.target.closest( '.vb-tn' ); if ( ! tn || ! dragId ) { return; }
			e.preventDefault();
			clearDropMarks();
			var r = tn.getBoundingClientRect(), y = e.clientY - r.top, h = r.height;
			var pos = y < h * 0.28 ? 'before' : ( y > h * 0.72 ? 'after' : 'inside' );
			tn.classList.add( 'drop-' + pos );
		} );
		tree.addEventListener( 'drop', function ( e ) {
			var tn = e.target.closest( '.vb-tn' ); if ( ! tn || ! dragId ) { return; }
			e.preventDefault();
			var r = tn.getBoundingClientRect(), y = e.clientY - r.top, h = r.height;
			var pos = y < h * 0.28 ? 'before' : ( y > h * 0.72 ? 'after' : 'inside' );
			var target = tn.getAttribute( 'data-node' );
			clearDropMarks();
			moveNode( dragId, target, pos );
			dragId = null;
		} );
	}
	function clearDropMarks() {
		var marks = document.querySelectorAll( '.drop-before,.drop-after,.drop-inside' );
		for ( var i = 0; i < marks.length; i++ ) { marks[ i ].classList.remove( 'drop-before', 'drop-after', 'drop-inside' ); }
	}
	function resizeCanvas( bp ) { var fr = document.getElementById( 'vb-canvas' ); fr.style.maxWidth = bp === 'mobile' ? '390px' : bp === 'tablet' ? '768px' : ''; }

	function injectStyles() {
		if ( document.getElementById( 'vb-editor-style' ) ) { return; }
		var css = [
			'.vb-app{position:fixed;inset:0;display:flex;flex-direction:column;background:#1a1b20;color:#f4f4f6;font-size:12.5px}',
			'.vb-app svg{display:block}',
			'.vb-top{width:100%;height:54px;display:flex;align-items:center;justify-content:space-between;gap:8px;padding:0 14px;background:#232429;border-bottom:1px solid rgba(255,255,255,.07);position:relative;box-sizing:border-box}',
			'.vb-tbc{display:flex;align-items:center;gap:8px}',
			'.vb-tbc-center{position:absolute;left:50%;transform:translateX(-50%);background:#232429;padding:0 10px}',
			'.vb-brand{width:36px;height:34px;border-radius:9px;background:#313339;border:1px solid rgba(255,255,255,.07);display:grid;place-items:center;cursor:pointer;padding:0}',
			'.vb-brand:hover{background:#3c3e46}',
			'.vb-tsep{width:1px;height:22px;background:rgba(255,255,255,.11);margin:0 3px}',
			'.vb-pagepick{display:flex;flex-direction:column;justify-content:center;padding:0 4px}',
			'.vb-pagepick small{font-size:9px;color:#606069;line-height:1;text-transform:uppercase;letter-spacing:.5px;padding-left:11px}',
			'.vb-pagepick b{display:flex;align-items:center;gap:2px}',
			'.vb-title{background:transparent;border:1px solid transparent;border-radius:8px;color:#f4f4f6;font-size:14px;font-weight:700;padding:5px 11px;width:180px;outline:none;transition:.1s;font-family:inherit}',
			'.vb-title:hover{background:#313339}.vb-title:focus{background:#313339;border-color:#2ab7f1}',
			'.vb-title::placeholder{color:#606069;font-weight:500}',
			'.vb-pp-caret{background:none;border:none;color:#606069;cursor:pointer;padding:4px;border-radius:6px;display:grid;place-items:center}',
			'.vb-pp-caret:hover{background:#3c3e46;color:#f4f4f6}',
			'.vb-bp{display:flex;gap:2px;background:#1a1b20;padding:3px;border-radius:9px}',
			'.vb-brandmenu{position:absolute;top:50px;left:14px;width:230px;background:#2a2c32;border:1px solid rgba(255,255,255,.11);border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.6);padding:6px;z-index:300;display:none}',
			'.vb-brandmenu.open{display:block}',
			'.vb-pageswitch{position:absolute;top:50px;left:64px;width:380px;background:#2a2c32;border:1px solid rgba(255,255,255,.12);border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.6);z-index:300;display:none}',
			'.vb-pageswitch.open{display:block}',
			'.vb-ps-search{display:flex;align-items:center;gap:9px;padding:12px 14px;border-bottom:1px solid rgba(255,255,255,.07)}',
			'.vb-ps-search input{flex:1;background:none;border:none;outline:none;color:#f4f4f6;font-size:13px}.vb-ps-search input::placeholder{color:#606069}',
			'.vb-ps-filters{display:flex;gap:5px;padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.07);flex-wrap:wrap}',
			'.vb-ps-f{padding:5px 11px;border-radius:7px;background:#313339;color:#8a8a94;font-size:11.5px;font-weight:600;border:none;cursor:pointer}',
			'.vb-ps-f.on{background:#2ab7f1;color:#04222f}',
			'.vb-ps-list{max-height:340px;overflow:auto;padding:6px}',
			'.vb-ps-loading{color:#606069;font-size:12px;text-align:center;padding:24px}',
			'.vb-ps-sec{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#606069;padding:9px 10px 5px}',
			'.vb-ps-i{display:flex;align-items:center;gap:11px;padding:9px 10px;border-radius:9px;cursor:pointer;text-decoration:none}.vb-ps-i:hover{background:#3c3e46}',
			'.vb-ps-ic{width:28px;height:28px;border-radius:8px;background:#1a1b20;display:grid;place-items:center;color:#2ab7f1;flex:0 0 auto}',
			'.vb-ps-t{flex:1;font-weight:600;color:#f4f4f6;font-size:13px}',
			'.vb-ps-badge{font-size:9.5px;font-weight:700;text-transform:uppercase;padding:2px 7px;border-radius:5px}',
			'.b-pub{background:rgba(67,209,127,.14);color:#43d17f}.b-draft{background:#313339;color:#8a8a94}.b-wp{background:rgba(160,107,255,.14);color:#a06bff}',
			'.vb-bm-i{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:9px;color:#9d9da8;font-size:13px;font-weight:500;text-decoration:none}',
			'.vb-bm-i:hover{background:#3c3e46;color:#f4f4f6}',
			'.vb-btn{padding:9px 16px;border-radius:9px;font-weight:600;font-size:13px;border:1px solid transparent;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;line-height:1;font-family:inherit}',
			'.vb-btn-ghost{background:#313339;color:#c9c9d1;border-color:rgba(255,255,255,.10)}.vb-btn-ghost:hover{background:#3c3e46;color:#f4f4f6}',
			'.vb-btn-primary{background:#2ab7f1;color:#08222e;border-color:transparent}.vb-btn-primary:hover{filter:brightness(1.06)}',
			'.vb-btn-primary.is-live{background:#43d17f;color:#08281a}',
			'#vb-actions{display:inline-flex;gap:8px}',
			'.vb-exitwrap{position:relative}',
			'.vb-exitmenu{position:absolute;top:42px;right:0;width:210px;background:#2a2c32;border:1px solid rgba(255,255,255,.12);border-radius:11px;box-shadow:0 18px 50px rgba(0,0,0,.5);padding:6px;z-index:300;display:none}',
			'.vb-exitmenu.open{display:block}',
			'.vb-save--saving{color:#f5a742}.vb-save--saved{color:#43d17f}.vb-save--error{color:#f56a5c}',
			'.vb-ic{width:32px;height:32px;border-radius:8px;color:#9d9da8;display:grid;place-items:center;background:none;border:none;cursor:pointer}',
			'.vb-ic:hover{background:#3c3e46;color:#f4f4f6}',
			'.vb-sep{width:1px;height:22px;background:rgba(255,255,255,.11);margin:0 3px}',
			'.vb-bp{display:flex;gap:2px;background:#1a1b20;padding:3px;border-radius:9px}',
			'.vb-bp button{width:32px;height:26px;border-radius:6px;color:#606069;display:grid;place-items:center;background:none;border:none;cursor:pointer}',
			'.vb-bp button.on{background:#313339;color:#2ab7f1}',
			'.vb-editing{display:flex;flex-direction:column;justify-content:center;padding:0 10px}',
			'.vb-title{background:transparent;border:1px solid transparent;border-radius:8px;color:#f4f4f6;font-size:13px;font-weight:600;padding:7px 11px;width:190px;outline:none;transition:.1s;font-family:inherit}',
			'.vb-title:hover{background:#313339}',
			'.vb-title:focus{background:#313339;border-color:#2ab7f1;width:230px}',
			'.vb-title::placeholder{color:#606069;font-weight:500}',
			'.vb-editing small{font-size:9px;color:#606069;line-height:1}.vb-editing b{font-size:12px;font-weight:600}',
			'.vb-spring{flex:1}',
			'.vb-save--saving-x{}',
			'.vb-save--saving{color:#f5a742}.vb-save--saved{color:#43d17f;border-color:rgba(67,209,127,.3)}.vb-save--error{color:#f56a5c;border-color:rgba(245,106,92,.35)}',
			'.vb-insp-acts{margin-left:auto;display:flex;gap:2px}',
			'.vb-ia{width:26px;height:26px;border-radius:7px;color:#606069;display:grid;place-items:center;background:none;border:none;cursor:pointer}',
			'.vb-ia:hover{background:#3c3e46;color:#f4f4f6}.vb-ia-del:hover{background:rgba(245,106,92,.15);color:#f56a5c}',
			'.vb-addmenu{position:absolute;top:54px;left:0;bottom:0;width:300px;background:#232429;border-right:1px solid rgba(255,255,255,.12);box-shadow:8px 0 40px rgba(0,0,0,.5);z-index:120;display:none;flex-direction:column}',
			'.vb-addmenu.open{display:flex}',
			'.vb-ap-h{display:flex;align-items:center;gap:9px;padding:13px 14px;border-bottom:1px solid rgba(255,255,255,.07)}',
			'.vb-ap-plus{color:#2ab7f1;display:grid;place-items:center}.vb-ap-h b{flex:1;font-size:13px}',
			'.vb-ap-x{background:none;border:none;color:#606069;cursor:pointer;display:grid;place-items:center;padding:4px;border-radius:6px}.vb-ap-x:hover{background:#3c3e46;color:#f4f4f6}',
			'.vb-ap-search{display:flex;align-items:center;gap:8px;margin:12px;padding:9px 11px;background:#1a1b20;border:1px solid rgba(255,255,255,.07);border-radius:9px}',
			'.vb-ap-search input{flex:1;background:none;border:none;outline:none;color:#f4f4f6;font-size:12.5px}.vb-ap-search input::placeholder{color:#606069}',
			'.vb-ap-body{flex:1;overflow:auto;padding:0 8px 12px}',
			'.vb-ap-none{color:#606069;font-size:12px;text-align:center;padding:24px}',
			'.vb-acc{margin-bottom:4px}',
			'.vb-acc-h{display:flex;align-items:center;gap:8px;padding:10px;border-radius:9px;cursor:pointer;color:#c9c9d1}.vb-acc-h:hover{background:#313339}',
			'.vb-acc-ic{color:#2ab7f1;display:grid;place-items:center}.vb-acc-h b{flex:1;font-size:12.5px;font-weight:600}',
			'.vb-acc-ct{font-size:10px;color:#606069;background:#1a1b20;padding:2px 7px;border-radius:20px}',
			'.vb-acc-cv{color:#606069;transition:transform .12s}.vb-acc.closed .vb-acc-cv{transform:rotate(-90deg)}',
			'.vb-acc-b{display:grid;grid-template-columns:1fr 1fr;gap:6px;padding:6px 4px 10px}.vb-acc.closed .vb-acc-b{display:none}',
			'.vb-el{display:flex;flex-direction:column;align-items:center;gap:7px;padding:12px 8px;border-radius:10px;background:#1a1b20;border:1px solid rgba(255,255,255,.07);cursor:pointer;text-align:center}',
			'.vb-el:hover{background:#3c3e46;border-color:#2ab7f1}.vb-el-ic{color:#c9c9d1}.vb-el:hover .vb-el-ic{color:#2ab7f1}',
			'.vb-el-l{font-size:11px;color:#c9c9d1;display:flex;align-items:center;gap:5px}',
			'.vb-el-badge{font-size:8px;font-weight:700;color:#a06bff;background:rgba(160,107,255,.15);padding:1px 5px;border-radius:4px}',
			'.vb-body{flex:1;display:grid;grid-template-columns:220px 306px minmax(0,1fr);min-height:0;width:100%}',
			'.vb-spine{background:#232429;border-right:1px solid rgba(255,255,255,.07);display:flex;flex-direction:column;min-height:0}',
			'.vb-spine-top{padding:12px}',
			'.vb-quick{display:flex;gap:6px;margin-top:8px}',
			'.vb-qt{flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:8px;border-radius:8px;background:#313339;border:1px solid rgba(255,255,255,.06);color:#9d9da8;font-size:11.5px;font-weight:600;text-decoration:none}',
			'.vb-qt:hover{background:#3c3e46;color:#f4f4f6}',
			'.vb-spine-search{display:flex;align-items:center;gap:7px;margin:0 12px 8px;padding:7px 10px;background:#1a1b20;border:1px solid rgba(255,255,255,.06);border-radius:9px}',
			'.vb-ss-ic{color:#606069;display:grid;place-items:center}',
			'.vb-spine-search input{flex:1;background:none;border:none;outline:none;color:#f4f4f6;font-size:12px}',
			'.vb-spine-search input::placeholder{color:#606069}',
			'.vb-add-big{width:100%;padding:11px;border-radius:11px;background:linear-gradient(180deg,#218ec4,#1a789f);color:#eef7fc;font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:8px;border:none;cursor:pointer}',
			'.vb-add-big:hover{background:linear-gradient(180deg,#2597ce,#1d82ab)}',
			'.vb-spine-h{padding:6px 14px 10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#606069}',
			'.vb-tree{overflow-y:auto;flex:1;padding:0 8px 12px}',
			'.vb-tn{display:flex;align-items:center;gap:7px;padding:7px 8px;border-radius:7px;cursor:pointer;color:#9d9da8}',
			'.vb-tn:hover{background:#3c3e46;color:#f4f4f6}',
			'.vb-tn.sel{background:rgba(42,183,241,.14);color:#f4f4f6}',
			'.vb-tn-ic{color:#606069;display:grid;place-items:center}.vb-tn.sel .vb-tn-ic{color:#2ab7f1}',
			'.vb-tn-name{flex:1}.vb-tn-cls{font-family:ui-monospace,Menlo,monospace;font-size:9.5px;color:#606069}',
			'.vb-tn.dragging{opacity:.4}',
			'.vb-tn.drop-before{box-shadow:inset 0 2px 0 #2ab7f1}',
			'.vb-tn.drop-after{box-shadow:inset 0 -2px 0 #2ab7f1}',
			'.vb-tn.drop-inside{background:rgba(42,183,241,.14);box-shadow:inset 0 0 0 1px rgba(42,183,241,.5)}',
			'.vb-stage{background:#16161b;display:flex;flex-direction:column;align-items:center;min-height:0;overflow:auto;padding:16px}',
			'#vb-canvas{width:100%;max-width:1200px;min-height:78vh;background:#fff;border:none;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.5);transition:max-width .25s}',
			'.vb-csspanel{position:absolute;top:54px;right:0;bottom:0;width:380px;background:#232429;border-left:1px solid rgba(255,255,255,.12);box-shadow:-8px 0 40px rgba(0,0,0,.5);z-index:130;display:none;flex-direction:column}',
			'.vb-css-top{display:flex;align-items:center;justify-content:space-between;padding:13px 14px;border-bottom:1px solid rgba(255,255,255,.07)}.vb-css-top b{font-size:13px}',
			'.vb-css-x{background:none;border:none;color:#606069;cursor:pointer;display:grid;place-items:center;padding:4px;border-radius:6px}.vb-css-x:hover{background:#3c3e46;color:#f4f4f6}',
			'.vb-css-files{display:flex;flex-wrap:wrap;gap:5px;padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.07)}',
			'.vb-css-file{display:flex;align-items:center;gap:5px;padding:6px 10px;border-radius:7px;background:#313339;border:1px solid rgba(255,255,255,.06);color:#9d9da8;font-size:11.5px;cursor:pointer;font-family:ui-monospace,Menlo,monospace}',
			'.vb-css-file.on{background:rgba(42,183,241,.14);color:#2ab7f1;border-color:transparent}',
			'.vb-css-new{display:flex;align-items:center;gap:4px;padding:6px 10px;border-radius:7px;background:none;border:1px dashed rgba(255,255,255,.14);color:#8a8a94;font-size:11.5px;cursor:pointer}.vb-css-new:hover{color:#f4f4f6}',
			'.vb-css-name{display:flex;gap:6px;padding:10px 12px}',
			'.vb-css-name input{flex:1;background:#1a1b20;border:1px solid rgba(255,255,255,.07);border-radius:8px;padding:8px 11px;color:#f4f4f6;font-family:ui-monospace,Menlo,monospace;font-size:12px;outline:none}.vb-css-name input:focus{border-color:#2ab7f1}',
			'.vb-css-del{background:#313339;border:1px solid rgba(255,255,255,.07);border-radius:8px;color:#8a8a94;cursor:pointer;width:34px;display:grid;place-items:center}.vb-css-del:hover{background:rgba(245,106,92,.15);color:#f56a5c}',
			'.vb-css-code{flex:1;margin:0 12px;background:#1a1b20;border:1px solid rgba(255,255,255,.07);border-radius:9px;padding:12px;color:#e6f7ff;font-family:ui-monospace,Menlo,monospace;font-size:12.5px;line-height:1.6;resize:none;outline:none}.vb-css-code:focus{border-color:#2ab7f1}',
			'.vb-css-foot{padding:10px 14px;font-size:11px;color:#606069}',
			'.vb-histpanel{position:absolute;top:54px;right:0;bottom:0;width:320px;background:#232429;border-left:1px solid rgba(255,255,255,.12);box-shadow:-8px 0 40px rgba(0,0,0,.5);z-index:130;display:none;flex-direction:column}',
			'.vb-hist-top{display:flex;align-items:center;justify-content:space-between;padding:13px 14px;border-bottom:1px solid rgba(255,255,255,.07)}.vb-hist-top b{font-size:13px}',
			'.vb-hist-note{padding:10px 14px;font-size:11px;color:#606069;border-bottom:1px solid rgba(255,255,255,.07)}',
			'.vb-hist-list{flex:1;overflow:auto;padding:6px}',
			'.vb-hist-empty{color:#606069;font-size:12px;text-align:center;padding:30px 20px;line-height:1.6}',
			'.vb-hist-i{display:flex;align-items:center;gap:10px;width:100%;padding:9px 10px;border-radius:9px;background:none;border:none;color:#c9c9d1;cursor:pointer;text-align:left}',
			'.vb-hist-i:hover{background:#3c3e46}',
			'.vb-hist-dot{width:7px;height:7px;border-radius:50%;background:#2ab7f1;flex:0 0 auto}',
			'.vb-hist-l{flex:1;font-size:12.5px}',
			'.vb-hist-t{font-size:10.5px;color:#606069}',
			'.vb-structpanel{position:absolute;top:54px;right:0;bottom:0;width:300px;background:#232429;border-left:1px solid rgba(255,255,255,.12);box-shadow:-8px 0 40px rgba(0,0,0,.5);z-index:130;display:none;flex-direction:column}',
			'.vb-st-tree{flex:1;overflow:auto;padding:6px}',
			'.vb-st-row{display:flex;align-items:center;gap:6px;padding:6px 8px;border-radius:7px;cursor:pointer;color:#c9c9d1}',
			'.vb-st-row:hover{background:#3c3e46}.vb-st-row.sel{background:rgba(42,183,241,.14);color:#f4f4f6}',
			'.vb-st-caret{display:grid;place-items:center;color:#606069;cursor:pointer;transition:transform .12s;width:14px}.vb-st-caret.closed{transform:rotate(-90deg)}',
			'.vb-st-spacer{width:14px;flex:0 0 auto}',
			'.vb-st-ic{color:#8a8a94;display:grid;place-items:center}.vb-st-row.sel .vb-st-ic{color:#2ab7f1}',
			'.vb-st-l{font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}',
			'.vb-live{color:#43d17f;display:flex;align-items:center;gap:6px}',
			'.vb-live::before{content:"";width:6px;height:6px;border-radius:50%;background:#43d17f;box-shadow:0 0 8px #43d17f}',
			'.vb-css pre{margin:0;padding:12px;font-family:ui-monospace,Menlo,monospace;font-size:11px;line-height:1.6;color:#9d9da8;white-space:pre;overflow:auto;max-height:220px}',
			'.vb-inspector{background:#232429;border-right:1px solid rgba(255,255,255,.07);display:flex;flex-direction:column;min-height:0;overflow-y:auto}',
			'.vb-insp-empty{padding:20px;color:#606069}',
			'.vb-insp-head{display:flex;align-items:center;gap:10px;padding:14px}',
			'.vb-insp-ic{width:32px;height:32px;border-radius:9px;background:rgba(42,183,241,.14);color:#2ab7f1;display:grid;place-items:center}',
			'.vb-insp-tx b{font-size:13.5px;font-weight:650;display:block}',
			'.vb-insp-tx small{font-size:10.5px;color:#606069;font-family:ui-monospace,Menlo,monospace}',
			'.vb-classbar{margin:0 12px 4px;padding:13px;background:linear-gradient(180deg,#2a2c32,#1a1b20);border:1px solid rgba(255,255,255,.07);border-radius:13px}',
			'.vb-tabs{display:flex;gap:3px;margin:6px 12px 10px;padding:3px;background:#1a1b20;border-radius:9px}',
			'.vb-tab{flex:1;padding:7px;border-radius:6px;border:none;background:none;color:#8a8a94;font-size:12px;font-weight:600;cursor:pointer}',
			'.vb-tab.on{background:#313339;color:#f4f4f6}',
			'.vb-chip{display:inline-flex;align-items:center;gap:4px;padding:5px 8px;border-radius:7px;font-family:ui-monospace,Menlo,monospace;font-size:11px;cursor:pointer}',
			'.vb-chip.base{background:rgba(42,183,241,.13);color:#2ab7f1}.vb-chip.combo{background:rgba(160,107,255,.14);color:#a06bff}',
			'.vb-chip.active{outline:1px solid currentColor}',
			'.vb-chip-x{opacity:.5;display:grid;place-items:center;border-radius:4px}.vb-chip-x:hover{opacity:1;background:rgba(255,255,255,.12)}',
			'.vb-chip-add{background:#313339;color:#8a8a94;border:1px dashed rgba(255,255,255,.14)}.vb-chip-add:hover{color:#f4f4f6}',
			'.vb-setwrap{padding:2px 0}',
			'.vb-imgbtn{display:flex;align-items:center;justify-content:center;gap:8px;width:calc(100% - 24px);margin:8px 12px 0;padding:10px;border-radius:10px;background:#313339;border:1px solid rgba(255,255,255,.07);color:#f4f4f6;font-size:12.5px;font-weight:600;cursor:pointer}',
			'.vb-imgbtn:hover{background:#3c3e46}',
			'.vb-cb-l{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#606069;margin-bottom:9px}',
			'.vb-active-class{display:flex;align-items:center;gap:10px;padding:11px 12px;background:#1a1b20;border:1px solid rgba(255,255,255,.07);border-radius:10px;margin-bottom:10px}',
			'.vb-ac-dot{width:9px;height:9px;border-radius:3px}',
			'.vb-active-class.base .vb-ac-dot{background:#2ab7f1}.vb-active-class.combo .vb-ac-dot{background:#a06bff}',
			'.vb-ac-name{flex:1;font-family:ui-monospace,Menlo,monospace;font-size:14px;font-weight:600}',
			'.vb-active-class.base .vb-ac-name{color:#2ab7f1}.vb-active-class.combo .vb-ac-name{color:#a06bff}',
			'.vb-ac-kind{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:3px 8px;border-radius:6px}',
			'.vb-active-class.base .vb-ac-kind{background:rgba(42,183,241,.14);color:#2ab7f1}.vb-active-class.combo .vb-ac-kind{background:rgba(160,107,255,.15);color:#a06bff}',
			'.vb-chips{display:flex;flex-wrap:wrap;gap:5px}',
			'.vb-states{display:flex;gap:3px;margin-top:10px;padding:3px;background:#1a1b20;border-radius:9px}',
			'.vb-state{flex:1;padding:6px;border-radius:6px;color:#606069;font-size:11px;font-weight:600;background:none;border:none;cursor:pointer;font-family:ui-monospace,Menlo,monospace}',
			'.vb-state:hover{color:#9d9da8}',
			'.vb-state.on{background:#313339;color:#a06bff}',
			'.vb-chip{padding:4px 9px;border-radius:7px;font-family:ui-monospace,Menlo,monospace;font-size:11px;cursor:pointer;background:#313339;color:#9d9da8;border:1px solid transparent}',
			'.vb-chip:hover{background:#3c3e46;color:#f4f4f6}',
			'.vb-chip.active.base{background:rgba(42,183,241,.15);color:#2ab7f1;border-color:rgba(42,183,241,.4)}',
			'.vb-chip.active.combo{background:rgba(160,107,255,.15);color:#a06bff;border-color:rgba(160,107,255,.4)}',
			'.vb-bp-note{font-size:10px;color:#606069;margin-top:9px}',
			'.vb-controls{padding:12px}',
			'.vb-block{margin-bottom:8px;background:#2a2c32;border:1px solid rgba(255,255,255,.07);border-radius:12px;overflow:hidden}',
			'.vb-block-h{display:flex;align-items:center;gap:9px;padding:11px 13px;cursor:pointer;user-select:none}',
			'.vb-block-ic{color:#9d9da8;display:grid;place-items:center}.vb-block-h b{font-size:12px;font-weight:600;flex:1}',
			'.vb-block-cv{color:#606069;transition:transform .12s}',
			'.vb-block.closed .vb-block-cv{transform:rotate(-90deg)}',
			'.vb-block.closed .vb-block-b{display:none}',
			'.vb-block-b{padding:2px 13px 13px}',
			'.vb-f{margin-bottom:12px}.vb-f:last-child{margin-bottom:2px}',
			'.vb-f-lbl{display:flex;align-items:center;gap:7px;font-size:11px;color:#9d9da8;margin-bottom:6px;font-weight:500}',
			'.vb-src{width:8px;height:8px;border-radius:2.5px;flex:0 0 auto;cursor:help}',
			'.vb-src.blue{background:#2ab7f1}.vb-src.orange{background:#f5a742}.vb-src.pink{background:#f265ab}.vb-src.none{background:#3c3e46;box-shadow:inset 0 0 0 1px rgba(255,255,255,.11)}',
			'.vb-seg{display:flex;background:#1a1b20;border:1px solid rgba(255,255,255,.07);border-radius:8px;padding:3px;gap:2px}',
			'.vb-seg button{flex:1;padding:6px;border-radius:6px;color:#606069;font-size:11px;background:none;border:none;cursor:pointer}',
			'.vb-seg button:hover:not(.on){color:#9d9da8}.vb-seg button.on{background:#3c3e46;color:#2ab7f1}',
			'.vb-row{display:flex;gap:6px}',
			'.vb-inp{flex:1;background:#1a1b20;border:1px solid rgba(255,255,255,.07);border-radius:8px;padding:7px 9px;color:#f4f4f6;font-size:12px;outline:none;min-width:0}',
			'.vb-inp:focus{border-color:#2ab7f1}.vb-inp.num{max-width:70px;font-family:ui-monospace,Menlo,monospace;text-align:center}',
			'.vb-unit{background:#1a1b20;border:1px solid rgba(255,255,255,.07);border-radius:8px;padding:0 8px;display:grid;place-items:center;color:#606069;font-size:10px}',
			'.vb-swatch{width:28px;height:28px;border-radius:8px;border:1px solid rgba(255,255,255,.11);cursor:pointer;padding:0;background:none}'
		].join( '' );
		var s = document.createElement( 'style' ); s.id = 'vb-editor-style'; s.textContent = css; document.head.appendChild( s );
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', mount ); } else { mount(); }
}() );
