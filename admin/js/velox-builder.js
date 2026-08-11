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
		droplet:'<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/>'
	};
	function svg( name, size ) {
		size = size || 16;
		return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + ( ICON[ name ] || '' ) + '</svg>';
	}
	function elIcon( n ) { return { Section:'section', Heading:'heading', Text:'text', Button:'button', Div:'div', Image:'image', Columns:'columns', Grid:'grid' }[ n ] || 'div'; }

	/* ============================================================
	   1. STORE
	   ============================================================ */
	var store = {
		state:null, history:[], future:[], listeners:[],
		init:function ( d ) { this.state = d; this.history = [ JSON.stringify( d ) ]; this.emit(); },
		snapshot:function () { this.history.push( JSON.stringify( this.state ) ); if ( this.history.length > 120 ) { this.history.shift(); } this.future = []; },
		commit:function ( fn ) { this.snapshot(); fn( this.state ); this.emit(); },
		undo:function () { if ( this.history.length <= 1 ) { return; } this.future.push( this.history.pop() ); this.state = JSON.parse( this.history[ this.history.length - 1 ] ); this.emit(); },
		redo:function () { if ( ! this.future.length ) { return; } var s = this.future.pop(); this.history.push( s ); this.state = JSON.parse( s ); this.emit(); },
		subscribe:function ( fn ) { this.listeners.push( fn ); },
		emit:function () { for ( var i = 0; i < this.listeners.length; i++ ) { this.listeners[ i ]( this.state ); } }
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
			doc.write( '<!DOCTYPE html><html><head><meta charset="utf-8"><style id="vb-reset">*{box-sizing:border-box;margin:0}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,sans-serif}[data-node]{outline:1px solid transparent;outline-offset:-1px;transition:outline-color .1s}[data-node]:hover{outline-color:rgba(42,183,241,.45)}[data-node].vb-sel{outline:2px solid #2ab7f1}.vb-img-ph{display:flex;align-items:center;justify-content:center;min-height:120px;color:#8a94a0;font-size:13px;background:repeating-linear-gradient(45deg,#eef1f4,#eef1f4 10px,#e6eaee 10px,#e6eaee 20px)}.vb-empty-canvas{min-height:70vh;display:flex;align-items:center;justify-content:center;color:#9aa3ad}.vb-ec-inner{text-align:center}.vb-ec-inner b{display:block;font-size:16px;color:#5b6673;margin-bottom:6px}.vb-ec-inner p{font-size:13px}.vb-reuse{position:relative;outline:1px dashed rgba(160,107,255,.5);outline-offset:-1px}.vb-reuse-tag{position:absolute;top:0;left:0;background:#a06bff;color:#fff;font:600 10px/1 -apple-system,sans-serif;padding:3px 7px;border-radius:0 0 6px 0;z-index:2}</style><style id="vb-style"></style></head><body></body></html>' );
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
				if ( n ) { e.preventDefault(); store.commit( function ( s ) { s.selection = n.getAttribute( 'data-node' ); resetActiveClass( s ); } ); }
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
		if ( store.state.content[ id ] !== text ) { store.commit( function ( s ) { s.content[ id ] = text; } ); }
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
		} );
	}
	function removeProp( prop ) { store.commit( function ( s ) { var c = s.activeClass, key = ruleKey( s.breakpoint, s.state ); if ( s.classes[ c ] && s.classes[ c ][ key ] ) { delete s.classes[ c ][ key ][ prop ]; } } ); }
	/* rules for the "normal" state live under the plain breakpoint key (back-compat);
	   pseudo-states get a suffixed key like "base:hover" / "tablet:focus". */
	function ruleKey( bp, state ) { return ( ! state || state === 'normal' ) ? bp : bp + ':' + state; }

	/* ---------- node operations (insert / duplicate / delete) ---------- */
	var idSeq = 100;
	function uid( base ) { idSeq += 1; return ( base || 'el' ) + '-' + idSeq.toString( 36 ); }

	/* element catalog: what "Add" can insert. Each seeds a default class + rules. */
	var CATALOG = [
		{ key:'section', el:'Section', tag:'section', label:'Section', cls:'.section', rules:{ paddingTop:'48', paddingBottom:'48', paddingLeft:'32', paddingRight:'32' } },
		{ key:'div', el:'Div', tag:'div', label:'Div block', cls:'.block', rules:{} },
		{ key:'heading', el:'Heading', tag:'h2', label:'Heading', cls:'.heading', rules:{ fontSize:'32', fontWeight:'700' }, text:'New heading' },
		{ key:'text', el:'Text', tag:'p', label:'Text', cls:'.text', rules:{ fontSize:'16' }, text:'New text block.' },
		{ key:'button', el:'Button', tag:'a', label:'Button', cls:'.btn', rules:{ display:'inline-block', paddingTop:'12', paddingBottom:'12', paddingLeft:'20', paddingRight:'20', borderRadius:'8', background:'#2ab7f1', color:'#04222f' }, text:'Button' },
		{ key:'image', el:'Image', tag:'div', label:'Image', cls:'.image', rules:{ background:'#e9edf1', paddingTop:'40', paddingBottom:'40' }, text:'' },
		{ key:'columns', el:'Columns', tag:'div', label:'Columns', cls:'.columns', rules:{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'20' } }
	];

	function insertNode( catKey ) {
		var cat = CATALOG.filter( function ( c ) { return c.key === catKey; } )[ 0 ] || CATALOG[ 0 ];
		store.commit( function ( s ) {
			var id = uid( cat.key );
			var node = { id:id, el:cat.el, tag:cat.tag, classes:[ cat.cls ], overrides:{}, children:[] };
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
		} );
	}
	function isContainer( n ) { return n.el === 'Section' || n.el === 'Div' || n.el === 'Columns'; }
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
		} );
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
		} );
	}
	function cloneWithNewIds( node, s ) {
		var nid = uid( node.el.toLowerCase() );
		if ( s.content[ node.id ] != null ) { s.content[ nid ] = s.content[ node.id ]; }
		return { id:nid, el:node.el, tag:node.tag, classes:node.classes.slice(), overrides:JSON.parse( JSON.stringify( node.overrides || {} ) ), children:( node.children || [] ).map( function ( c ) { return cloneWithNewIds( c, s ); } ) };
	}
	function deleteNode( id ) {
		store.commit( function ( s ) {
			var parent = findParent( s.tree, id ), node = findNode( s.tree, id );
			if ( parent ) { parent.children.splice( parent.children.indexOf( node ), 1 ); s.selection = parent.id; }
			else { var i = s.tree.indexOf( node ); if ( i >= 0 ) { s.tree.splice( i, 1 ); } s.selection = s.tree.length ? s.tree[ Math.max( 0, i - 1 ) ].id : null; }
			if ( s.selection ) { resetActiveClass( s ); }
		} );
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
	var docId = CFG.docId || 0, docTitle = 'Untitled', saving = false, postId = CFG.postId || 0, docKind = CFG.kind || 'page';
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
			.then( function ( res ) { if ( res && res.success && res.data.model ) { docId = res.data.id; docTitle = res.data.title || 'Untitled'; pubStatus = res.data.status || 'draft'; pubUrl = res.data.url || ''; var ti = document.getElementById( 'vb-title' ); if ( ti ) { ti.value = docTitle; } store.init( res.data.model ); setTimeout( function () { setPubState( 'idle' ); }, 30 ); } } );
	}
	function setSaveState( state ) {
		var el = document.getElementById( 'vb-save' ); if ( ! el ) { return; }
		var map = { saving:T( 'Saving…' ), saved:T( 'Saved' ), error:T( 'Save failed' ), idle:T( 'Save' ) };
		el.textContent = map[ state ] || map.idle;
		el.className = 'vb-save vb-save--' + state;
	}

	/* ---------- publish ---------- */
	var pubStatus = 'draft', pubUrl = '';
	function publishDoc() {
		if ( ! CFG.ajaxurl ) { return; }
		// Save first so the published page reflects the latest edits, then publish.
		var afterSave = function () {
			var body = new URLSearchParams();
			body.set( 'action', 'velox' ); body.set( 'do', 'builder_publish' ); body.set( 'nonce', CFG.nonce || '' ); body.set( 'id', docId );
			setPubState( 'publishing' );
			fetch( CFG.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res && res.success ) { pubStatus = 'published'; pubUrl = res.data.url || ''; setPubState( 'published' ); }
					else { setPubState( 'error' ); alert( ( res && res.data && res.data.message ) || T( 'Publish failed' ) ); }
				} )
				.catch( function () { setPubState( 'error' ); } );
		};
		saveThen( afterSave );
	}
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
	function setPubState( state ) {
		var btn = document.getElementById( 'vb-publish' ), view = document.getElementById( 'vb-view' );
		if ( ! btn ) { return; }
		if ( state === 'publishing' ) { btn.textContent = T( 'Publishing…' ); btn.disabled = true; }
		else if ( state === 'published' ) {
			btn.textContent = T( 'Published' ); btn.disabled = false; btn.classList.add( 'is-live' );
			if ( view && pubUrl ) { view.href = pubUrl; view.style.display = ''; }
		} else if ( state === 'error' ) { btn.textContent = T( 'Publish' ); btn.disabled = false; }
		else { btn.textContent = pubStatus === 'published' ? T( 'Published' ) : T( 'Publish' ); btn.disabled = false; btn.classList.toggle( 'is-live', pubStatus === 'published' ); if ( view && pubUrl ) { view.href = pubUrl; view.style.display = ''; } }
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
			'<div class="vb-body">' + spineHTML() +
				'<main class="vb-stage"><iframe id="vb-canvas" title="Canvas"></iframe><div class="vb-css" id="vb-css"></div></main>' +
				'<aside class="vb-inspector" id="vb-inspector"></aside>' +
			'</div>' +
			'<div class="vb-addmenu" id="vb-addmenu"></div>';
		injectStyles();
		wireEvents();
		store.subscribe( renderAll );
		var fr = document.getElementById( 'vb-canvas' );
		fr.addEventListener( 'load', function () { canvasReady = true; if ( ! store.state ) { boot(); } else { injectCanvas(); } } );
		setTimeout( function () { if ( ! store.state ) { boot(); } }, 60 );
	}
	function boot() {
		if ( CFG.docId ) { loadDoc( CFG.docId ); setTimeout( function () { if ( ! store.state ) { store.init( initialDoc ); } }, 1200 ); }
		else { store.init( initialDoc ); }
	}
	function topbarHTML() {
		return '<div class="vb-top">' +
			'<div class="vb-brand"><span class="vb-brand-m">V</span></div>' +
			'<input id="vb-title" class="vb-title" type="text" value="' + escapeHtml( docTitle ) + '" placeholder="' + T( 'Untitled' ) + '" spellcheck="false">' +
			'<button class="vb-ic" data-add title="' + T( 'Add element' ) + '">' + svg( 'plus', 17 ) + '</button>' +
			'<div class="vb-sep"></div>' +
			'<div class="vb-bp" id="vb-bp">' +
				'<button data-bp="base" class="on" title="Desktop">' + svg( 'monitor', 15 ) + '</button>' +
				'<button data-bp="tablet" title="Tablet">' + svg( 'tablet', 14 ) + '</button>' +
				'<button data-bp="mobile" title="Mobile">' + svg( 'smartphone', 14 ) + '</button>' +
			'</div>' +
			'<div class="vb-editing"><small>' + T( 'Editing' ) + '</small><b id="vb-bplabel">Desktop</b></div>' +
			'<button class="vb-ic" id="vb-undo" title="Undo">' + svg( 'undo', 16 ) + '</button>' +
			'<button class="vb-ic" id="vb-redo" title="Redo">' + svg( 'redo', 16 ) + '</button>' +
			'<div class="vb-spring"></div>' +
			'<button class="vb-save vb-save--idle" id="vb-save">' + T( 'Save' ) + '</button>' +
			'<a class="vb-exit" href="' + ( CFG.backUrl || '#' ) + '">' + T( 'Exit' ) + '</a>' +
			'<a class="vb-view" id="vb-view" href="#" target="_blank" rel="noopener" style="display:none">' + T( 'View page' ) + '</a>' +
			'<button class="vb-publish" id="vb-publish">' + T( 'Publish' ) + '</button>' +
		'</div>';
	}
	function spineHTML() {
		return '<aside class="vb-spine">' +
			'<div class="vb-spine-top"><button class="vb-add-big" data-add>' + svg( 'plus', 17 ) + ' ' + T( 'Add element' ) + '</button></div>' +
			'<div class="vb-spine-h">' + T( 'Layers' ) + '</div>' +
			'<div class="vb-tree" id="vb-tree"></div>' +
		'</aside>';
	}

	function renderAll( state ) {
		renderTree( state ); renderInspector( state ); renderTopbar( state ); renderCSSPanel();
		injectCanvas();
	}
	function renderTopbar( state ) {
		var b = document.querySelectorAll( '#vb-bp button' );
		for ( var i = 0; i < b.length; i++ ) { b[ i ].classList.toggle( 'on', b[ i ].getAttribute( 'data-bp' ) === state.breakpoint ); }
		document.getElementById( 'vb-bplabel' ).textContent = BP_META[ state.breakpoint ].label;
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
		var chips = node.classes.map( function ( c, i ) {
			return '<span class="vb-chip ' + ( i === 0 ? 'base' : 'combo' ) + ' ' + ( c === ac ? 'active' : '' ) + '" data-cls="' + c + '">' + c + '</span>';
		} ).join( '' );
		var acKind = node.classes.indexOf( ac ) === 0 ? 'base' : 'combo';
		var imgBtn = node.el === 'Image' ? '<button class="vb-imgbtn" data-pickimg="' + node.id + '">' + svg( 'image', 15 ) + ' ' + ( store.state.content[ node.id ] ? T( 'Replace image' ) : T( 'Choose image' ) ) + '</button>' : '';
		var body = '';
		CONTROLS.forEach( function ( g, gi ) {
			var closed = gi > 2 ? ' closed' : ''; // first 3 open, rest collapsed by default
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
			imgBtn +
			'<div class="vb-controls">' + body + '</div>';
	}
	function renderCSSPanel() {
		var box = document.getElementById( 'vb-css' ); if ( ! box ) { return; }
		if ( ! cssShown ) { box.style.display = 'none'; return; }
		box.style.display = 'block';
		box.innerHTML = '<div class="vb-css-h"><span class="vb-live">' + T( 'live CSS' ) + '</span><span>' + ( new Blob( [ genCSS() ] ).size ) + ' bytes</span></div><pre>' + genCSS().replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ) + '</pre>';
	}

	function wireEvents() {
		document.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-add]' ) ) { toggleAddMenu( e.target.closest( '[data-add]' ) ); return; }
			var ins = e.target.closest( '[data-insert]' ); if ( ins ) { insertNode( ins.getAttribute( 'data-insert' ) ); closeAddMenu(); return; }
			var insr = e.target.closest( '[data-insert-reuse]' ); if ( insr ) { insertReusable( +insr.getAttribute( 'data-insert-reuse' ) ); closeAddMenu(); return; }
			if ( e.target.closest( '[data-dup]' ) ) { duplicateNode( store.state.selection ); return; }
			if ( e.target.closest( '[data-del]' ) ) { deleteNode( store.state.selection ); return; }
			var pick = e.target.closest( '[data-pickimg]' ); if ( pick ) { openMediaPicker( pick.getAttribute( 'data-pickimg' ) ); return; }
			if ( e.target.closest( '#vb-save' ) ) { saveDoc(); return; }
			if ( e.target.closest( '#vb-publish' ) ) { publishDoc(); return; }
			var tn = e.target.closest( '.vb-tn' ); if ( tn ) { store.commit( function ( s ) { s.selection = tn.getAttribute( 'data-node' ); resetActiveClass( s ); } ); return; }
			var chip = e.target.closest( '.vb-chip' ); if ( chip ) { store.commit( function ( s ) { s.activeClass = chip.getAttribute( 'data-cls' ); } ); return; }
			var blk = e.target.closest( '[data-block]' ); if ( blk ) { blk.parentElement.classList.toggle( 'closed' ); return; }
			var stbtn = e.target.closest( '[data-state]' ); if ( stbtn ) { store.commit( function ( s ) { s.state = stbtn.getAttribute( 'data-state' ); } ); return; }
			var seg = e.target.closest( '[data-set][data-val]' ); if ( seg ) { setProp( seg.getAttribute( 'data-set' ), seg.getAttribute( 'data-val' ) ); return; }
			var bp = e.target.closest( '#vb-bp button' ); if ( bp ) { store.commit( function ( s ) { s.breakpoint = bp.getAttribute( 'data-bp' ); } ); resizeCanvas( bp.getAttribute( 'data-bp' ) ); return; }
			if ( ! e.target.closest( '.vb-addmenu' ) ) { closeAddMenu(); }
		} );
		document.addEventListener( 'input', function ( e ) {
			if ( e.target.id === 'vb-title' ) { docTitle = e.target.value.trim() || 'Untitled'; setSaveState( 'idle' ); return; }
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
		var html = '<div class="vb-am-h">' + T( 'Add element' ) + '</div>' + CATALOG.map( function ( c ) {
			return '<button class="vb-am-i" data-insert="' + c.key + '"><span class="vb-am-ic">' + svg( elIcon( c.el ), 15 ) + '</span>' + c.label + '</button>';
		} ).join( '' );
		var reuse = CFG.reusables || [];
		if ( reuse.length ) {
			html += '<div class="vb-am-h">' + T( 'Reusables' ) + '</div>' + reuse.map( function ( r ) {
				return '<button class="vb-am-i" data-insert-reuse="' + r.id + '"><span class="vb-am-ic">' + svg( 'copy', 15 ) + '</span>' + escapeHtml( r.title ) + '</button>';
			} ).join( '' );
		}
		m.innerHTML = html;
		var r = anchor.getBoundingClientRect();
		m.style.left = Math.min( r.left, window.innerWidth - 240 ) + 'px';
		m.style.top = ( r.bottom + 6 ) + 'px';
		m.classList.add( 'open' );
	}
	function escapeHtml( s ) { return String( s ).replace( /[&<>"]/g, function ( c ) { return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;' }[ c ]; } ); }
	function reusableById( id ) { return ( CFG.reusables || [] ).filter( function ( r ) { return r.id === +id; } )[ 0 ] || null; }
	function closeAddMenu() { var m = document.getElementById( 'vb-addmenu' ); if ( m ) { m.classList.remove( 'open' ); } }

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
			'.vb-app{position:fixed;inset:0;display:flex;flex-direction:column;background:#0a0a0c;color:#f4f4f6;font-size:12.5px}',
			'.vb-app svg{display:block}',
			'.vb-top{height:52px;display:flex;align-items:center;gap:8px;padding:0 12px;background:#121216;border-bottom:1px solid rgba(255,255,255,.07)}',
			'.vb-brand-m{width:28px;height:28px;border-radius:8px;background:linear-gradient(140deg,#2ab7f1,#a06bff);display:grid;place-items:center;color:#fff;font-weight:800;font-size:13px}',
			'.vb-ic{width:32px;height:32px;border-radius:8px;color:#9d9da8;display:grid;place-items:center;background:none;border:none;cursor:pointer}',
			'.vb-ic:hover{background:#26262f;color:#f4f4f6}',
			'.vb-sep{width:1px;height:22px;background:rgba(255,255,255,.11);margin:0 3px}',
			'.vb-bp{display:flex;gap:2px;background:#0a0a0c;padding:3px;border-radius:9px}',
			'.vb-bp button{width:32px;height:26px;border-radius:6px;color:#606069;display:grid;place-items:center;background:none;border:none;cursor:pointer}',
			'.vb-bp button.on{background:#1d1d24;color:#2ab7f1}',
			'.vb-editing{display:flex;flex-direction:column;justify-content:center;padding:0 10px}',
			'.vb-title{background:transparent;border:1px solid transparent;border-radius:8px;color:#f4f4f6;font-size:13px;font-weight:600;padding:7px 11px;width:190px;outline:none;transition:.1s;font-family:inherit}',
			'.vb-title:hover{background:#1d1d24}',
			'.vb-title:focus{background:#1d1d24;border-color:#2ab7f1;width:230px}',
			'.vb-title::placeholder{color:#606069;font-weight:500}',
			'.vb-editing small{font-size:9px;color:#606069;line-height:1}.vb-editing b{font-size:12px;font-weight:600}',
			'.vb-spring{flex:1}',
			'.vb-exit{color:#9d9da8;text-decoration:none;font-weight:600;padding:8px 13px;border-radius:9px}',
			'.vb-exit:hover{background:#26262f;color:#f4f4f6}',
			'.vb-publish{padding:8px 17px;border-radius:9px;background:linear-gradient(180deg,#218ec4,#1a789f);color:#eef7fc;font-weight:700;border:none;cursor:pointer}',
			'.vb-publish.is-live{background:linear-gradient(180deg,#3aa96a,#2d8b55);color:#eafaf0}',
			'.vb-publish:disabled{opacity:.6;cursor:default}',
			'.vb-view{color:#43d17f;text-decoration:none;font-weight:600;padding:8px 12px;border-radius:9px;border:1px solid rgba(67,209,127,.3)}',
			'.vb-view:hover{background:rgba(67,209,127,.12)}',
			'.vb-save{padding:8px 15px;border-radius:9px;background:#1d1d24;color:#9d9da8;font-weight:600;border:1px solid rgba(255,255,255,.07);cursor:pointer}',
			'.vb-save:hover{background:#26262f;color:#f4f4f6}',
			'.vb-save--saving{color:#f5a742}.vb-save--saved{color:#43d17f;border-color:rgba(67,209,127,.3)}.vb-save--error{color:#f56a5c;border-color:rgba(245,106,92,.35)}',
			'.vb-insp-acts{margin-left:auto;display:flex;gap:2px}',
			'.vb-ia{width:26px;height:26px;border-radius:7px;color:#606069;display:grid;place-items:center;background:none;border:none;cursor:pointer}',
			'.vb-ia:hover{background:#26262f;color:#f4f4f6}.vb-ia-del:hover{background:rgba(245,106,92,.15);color:#f56a5c}',
			'.vb-addmenu{position:fixed;width:230px;background:#121216;border:1px solid rgba(255,255,255,.11);border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.6);padding:7px;z-index:200;display:none}',
			'.vb-addmenu.open{display:block}',
			'.vb-am-h{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#606069;padding:8px 10px 6px}',
			'.vb-am-i{width:100%;display:flex;align-items:center;gap:11px;padding:9px 11px;border-radius:9px;color:#9d9da8;font-size:13px;background:none;border:none;cursor:pointer;text-align:left}',
			'.vb-am-i:hover{background:#26262f;color:#f4f4f6}',
			'.vb-am-ic{width:28px;height:28px;border-radius:8px;background:#0a0a0c;display:grid;place-items:center;color:#9d9da8}',
			'.vb-am-i:hover .vb-am-ic{color:#2ab7f1}',
			'.vb-body{flex:1;display:grid;grid-template-columns:220px 1fr 306px;min-height:0}',
			'.vb-spine{background:#121216;border-right:1px solid rgba(255,255,255,.07);display:flex;flex-direction:column;min-height:0}',
			'.vb-spine-top{padding:12px}',
			'.vb-add-big{width:100%;padding:11px;border-radius:11px;background:linear-gradient(180deg,#218ec4,#1a789f);color:#eef7fc;font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:8px;border:none;cursor:pointer}',
			'.vb-add-big:hover{background:linear-gradient(180deg,#2597ce,#1d82ab)}',
			'.vb-spine-h{padding:6px 14px 10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#606069}',
			'.vb-tree{overflow-y:auto;flex:1;padding:0 8px 12px}',
			'.vb-tn{display:flex;align-items:center;gap:7px;padding:7px 8px;border-radius:7px;cursor:pointer;color:#9d9da8}',
			'.vb-tn:hover{background:#26262f;color:#f4f4f6}',
			'.vb-tn.sel{background:rgba(42,183,241,.14);color:#f4f4f6}',
			'.vb-tn-ic{color:#606069;display:grid;place-items:center}.vb-tn.sel .vb-tn-ic{color:#2ab7f1}',
			'.vb-tn-name{flex:1}.vb-tn-cls{font-family:ui-monospace,Menlo,monospace;font-size:9.5px;color:#606069}',
			'.vb-tn.dragging{opacity:.4}',
			'.vb-tn.drop-before{box-shadow:inset 0 2px 0 #2ab7f1}',
			'.vb-tn.drop-after{box-shadow:inset 0 -2px 0 #2ab7f1}',
			'.vb-tn.drop-inside{background:rgba(42,183,241,.14);box-shadow:inset 0 0 0 1px rgba(42,183,241,.5)}',
			'.vb-stage{background:#16161b;display:flex;flex-direction:column;align-items:center;min-height:0;overflow:auto;padding:22px}',
			'#vb-canvas{width:100%;max-width:1000px;min-height:70vh;background:#fff;border:none;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.5);transition:max-width .25s}',
			'.vb-css{width:100%;max-width:1000px;margin-top:12px;background:#121216;border:1px solid rgba(255,255,255,.07);border-radius:12px;overflow:hidden}',
			'.vb-css-h{display:flex;justify-content:space-between;padding:9px 12px;font-size:10.5px;color:#606069;border-bottom:1px solid rgba(255,255,255,.07)}',
			'.vb-live{color:#43d17f;display:flex;align-items:center;gap:6px}',
			'.vb-live::before{content:"";width:6px;height:6px;border-radius:50%;background:#43d17f;box-shadow:0 0 8px #43d17f}',
			'.vb-css pre{margin:0;padding:12px;font-family:ui-monospace,Menlo,monospace;font-size:11px;line-height:1.6;color:#9d9da8;white-space:pre;overflow:auto;max-height:220px}',
			'.vb-inspector{background:#121216;border-left:1px solid rgba(255,255,255,.07);display:flex;flex-direction:column;min-height:0;overflow-y:auto}',
			'.vb-insp-empty{padding:20px;color:#606069}',
			'.vb-insp-head{display:flex;align-items:center;gap:10px;padding:14px}',
			'.vb-insp-ic{width:32px;height:32px;border-radius:9px;background:rgba(42,183,241,.14);color:#2ab7f1;display:grid;place-items:center}',
			'.vb-insp-tx b{font-size:13.5px;font-weight:650;display:block}',
			'.vb-insp-tx small{font-size:10.5px;color:#606069;font-family:ui-monospace,Menlo,monospace}',
			'.vb-classbar{margin:0 12px 4px;padding:13px;background:linear-gradient(180deg,#17171d,#0a0a0c);border:1px solid rgba(255,255,255,.07);border-radius:13px}',
			'.vb-imgbtn{display:flex;align-items:center;justify-content:center;gap:8px;width:calc(100% - 24px);margin:8px 12px 0;padding:10px;border-radius:10px;background:#1d1d24;border:1px solid rgba(255,255,255,.07);color:#f4f4f6;font-size:12.5px;font-weight:600;cursor:pointer}',
			'.vb-imgbtn:hover{background:#26262f}',
			'.vb-cb-l{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#606069;margin-bottom:9px}',
			'.vb-active-class{display:flex;align-items:center;gap:10px;padding:11px 12px;background:#0a0a0c;border:1px solid rgba(255,255,255,.07);border-radius:10px;margin-bottom:10px}',
			'.vb-ac-dot{width:9px;height:9px;border-radius:3px}',
			'.vb-active-class.base .vb-ac-dot{background:#2ab7f1}.vb-active-class.combo .vb-ac-dot{background:#a06bff}',
			'.vb-ac-name{flex:1;font-family:ui-monospace,Menlo,monospace;font-size:14px;font-weight:600}',
			'.vb-active-class.base .vb-ac-name{color:#2ab7f1}.vb-active-class.combo .vb-ac-name{color:#a06bff}',
			'.vb-ac-kind{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:3px 8px;border-radius:6px}',
			'.vb-active-class.base .vb-ac-kind{background:rgba(42,183,241,.14);color:#2ab7f1}.vb-active-class.combo .vb-ac-kind{background:rgba(160,107,255,.15);color:#a06bff}',
			'.vb-chips{display:flex;flex-wrap:wrap;gap:5px}',
			'.vb-states{display:flex;gap:3px;margin-top:10px;padding:3px;background:#0a0a0c;border-radius:9px}',
			'.vb-state{flex:1;padding:6px;border-radius:6px;color:#606069;font-size:11px;font-weight:600;background:none;border:none;cursor:pointer;font-family:ui-monospace,Menlo,monospace}',
			'.vb-state:hover{color:#9d9da8}',
			'.vb-state.on{background:#1d1d24;color:#a06bff}',
			'.vb-chip{padding:4px 9px;border-radius:7px;font-family:ui-monospace,Menlo,monospace;font-size:11px;cursor:pointer;background:#1d1d24;color:#9d9da8;border:1px solid transparent}',
			'.vb-chip:hover{background:#26262f;color:#f4f4f6}',
			'.vb-chip.active.base{background:rgba(42,183,241,.15);color:#2ab7f1;border-color:rgba(42,183,241,.4)}',
			'.vb-chip.active.combo{background:rgba(160,107,255,.15);color:#a06bff;border-color:rgba(160,107,255,.4)}',
			'.vb-bp-note{font-size:10px;color:#606069;margin-top:9px}',
			'.vb-controls{padding:12px}',
			'.vb-block{margin-bottom:8px;background:#17171d;border:1px solid rgba(255,255,255,.07);border-radius:12px;overflow:hidden}',
			'.vb-block-h{display:flex;align-items:center;gap:9px;padding:11px 13px;cursor:pointer;user-select:none}',
			'.vb-block-ic{color:#9d9da8;display:grid;place-items:center}.vb-block-h b{font-size:12px;font-weight:600;flex:1}',
			'.vb-block-cv{color:#606069;transition:transform .12s}',
			'.vb-block.closed .vb-block-cv{transform:rotate(-90deg)}',
			'.vb-block.closed .vb-block-b{display:none}',
			'.vb-block-b{padding:2px 13px 13px}',
			'.vb-f{margin-bottom:12px}.vb-f:last-child{margin-bottom:2px}',
			'.vb-f-lbl{display:flex;align-items:center;gap:7px;font-size:11px;color:#9d9da8;margin-bottom:6px;font-weight:500}',
			'.vb-src{width:8px;height:8px;border-radius:2.5px;flex:0 0 auto;cursor:help}',
			'.vb-src.blue{background:#2ab7f1}.vb-src.orange{background:#f5a742}.vb-src.pink{background:#f265ab}.vb-src.none{background:#26262f;box-shadow:inset 0 0 0 1px rgba(255,255,255,.11)}',
			'.vb-seg{display:flex;background:#0a0a0c;border:1px solid rgba(255,255,255,.07);border-radius:8px;padding:3px;gap:2px}',
			'.vb-seg button{flex:1;padding:6px;border-radius:6px;color:#606069;font-size:11px;background:none;border:none;cursor:pointer}',
			'.vb-seg button:hover:not(.on){color:#9d9da8}.vb-seg button.on{background:#26262f;color:#2ab7f1}',
			'.vb-row{display:flex;gap:6px}',
			'.vb-inp{flex:1;background:#0a0a0c;border:1px solid rgba(255,255,255,.07);border-radius:8px;padding:7px 9px;color:#f4f4f6;font-size:12px;outline:none;min-width:0}',
			'.vb-inp:focus{border-color:#2ab7f1}.vb-inp.num{max-width:70px;font-family:ui-monospace,Menlo,monospace;text-align:center}',
			'.vb-unit{background:#0a0a0c;border:1px solid rgba(255,255,255,.07);border-radius:8px;padding:0 8px;display:grid;place-items:center;color:#606069;font-size:10px}',
			'.vb-swatch{width:28px;height:28px;border-radius:8px;border:1px solid rgba(255,255,255,.11);cursor:pointer;padding:0;background:none}'
		].join( '' );
		var s = document.createElement( 'style' ); s.id = 'vb-editor-style'; s.textContent = css; document.head.appendChild( s );
	}

	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', mount ); } else { mount(); }
}() );
