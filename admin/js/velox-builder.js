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
		trash:'<path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>'
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

	var initialDoc = {
		selection:'hero', activeClass:'.hero', breakpoint:'base',
		tree:[
			{ id:'hero', el:'Section', tag:'section', classes:[ '.hero', '.is-dark' ], overrides:{}, children:[
				{ id:'title', el:'Heading', tag:'h1', classes:[ '.title' ], overrides:{}, children:[] },
				{ id:'sub', el:'Text', tag:'p', classes:[ '.sub' ], overrides:{}, children:[] },
				{ id:'cta', el:'Button', tag:'a', classes:[ '.btn', '.btn--primary' ], overrides:{}, children:[] }
			] },
			{ id:'feats', el:'Section', tag:'section', classes:[ '.features' ], overrides:{}, children:[
				{ id:'card1', el:'Div', tag:'div', classes:[ '.card' ], overrides:{}, children:[] },
				{ id:'card2', el:'Div', tag:'div', classes:[ '.card' ], overrides:{}, children:[] }
			] }
		],
		classes:{
			'.hero':{ base:{ display:'flex', flexDirection:'column', alignItems:'stretch', paddingTop:'64', paddingBottom:'64', paddingLeft:'52', paddingRight:'52', gap:'18', color:'#ffffff' }, tablet:{ paddingLeft:'32', paddingRight:'32' }, mobile:{ paddingTop:'40', paddingBottom:'40', paddingLeft:'20', paddingRight:'20' } },
			'.is-dark':{ base:{ background:'#0e1622' } },
			'.title':{ base:{ fontSize:'44', fontWeight:'800', color:'#ffffff', marginBottom:'14' }, mobile:{ fontSize:'30' } },
			'.sub':{ base:{ fontSize:'17', color:'#aeb9c6', marginBottom:'26' } },
			'.btn':{ base:{ display:'inline-block', paddingTop:'13', paddingBottom:'13', paddingLeft:'22', paddingRight:'22', borderRadius:'10', fontWeight:'600' } },
			'.btn--primary':{ base:{ background:'#2ab7f1', color:'#04222f' } },
			'.features':{ base:{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'20', paddingTop:'44', paddingBottom:'44', paddingLeft:'52', paddingRight:'52', background:'#ffffff' } },
			'.card':{ base:{ paddingTop:'24', paddingBottom:'24', paddingLeft:'24', paddingRight:'24', borderRadius:'14', background:'#f7f9fb', color:'#12151a' } }
		},
		content:{ title:'Build fast sites without the bloat.', sub:'Only the CSS your page needs — nothing more.', cta:'Start building', card1:'Only-used CSS', card2:'Class-first styling' }
	};

	var CSS_PROP = { display:'display', flexDirection:'flex-direction', alignItems:'align-items', gap:'gap', paddingTop:'padding-top', paddingRight:'padding-right', paddingBottom:'padding-bottom', paddingLeft:'padding-left', marginTop:'margin-top', marginRight:'margin-right', marginBottom:'margin-bottom', marginLeft:'margin-left', fontSize:'font-size', fontWeight:'font-weight', color:'color', background:'background', borderRadius:'border-radius', gridTemplateColumns:'grid-template-columns' };
	var UNIT_PROPS = { gap:1, paddingTop:1, paddingRight:1, paddingBottom:1, paddingLeft:1, marginTop:1, marginRight:1, marginBottom:1, marginLeft:1, fontSize:1, borderRadius:1 };
	var BP_ORDER = [ 'base', 'tablet', 'mobile' ];
	var BP_META = { base:{ label:'Desktop', mq:null }, tablet:{ label:'Tablet ≤991', mq:'(max-width: 991px)' }, mobile:{ label:'Mobile ≤767', mq:'(max-width: 767px)' } };

	function walkTree( nodes, fn ) { for ( var i = 0; i < nodes.length; i++ ) { fn( nodes[ i ] ); if ( nodes[ i ].children ) { walkTree( nodes[ i ].children, fn ); } } }
	function findNode( nodes, id ) { for ( var i = 0; i < nodes.length; i++ ) { if ( nodes[ i ].id === id ) { return nodes[ i ]; } if ( nodes[ i ].children ) { var f = findNode( nodes[ i ].children, id ); if ( f ) { return f; } } } return null; }

	/* ============================================================
	   3. CASCADE RESOLVER
	   ============================================================ */
	function bpChain( bp ) { var i = BP_ORDER.indexOf( bp ), c = []; for ( var j = i; j >= 0; j-- ) { c.push( BP_ORDER[ j ] ); } return c; }
	function resolveProperty( node, bp, prop ) {
		var S = store.state, chain = bpChain( bp ), b, k;
		var ov = node.overrides || {};
		for ( k = 0; k < chain.length; k++ ) { b = chain[ k ]; if ( ov[ b ] && ov[ b ][ prop ] != null ) { return { value:ov[ b ][ prop ], source:'element', bp:b }; } }
		var stack = node.classes.slice().reverse();
		for ( var s = 0; s < stack.length; s++ ) {
			var rules = S.classes[ stack[ s ] ]; if ( ! rules ) { continue; }
			for ( k = 0; k < chain.length; k++ ) { b = chain[ k ]; if ( rules[ b ] && rules[ b ][ prop ] != null ) { return { value:rules[ b ][ prop ], source:'class', cls:stack[ s ], bp:b }; } }
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
		for ( var i = 0; i < BP_ORDER.length; i++ ) {
			var bp = BP_ORDER[ i ], meta = BP_META[ bp ], body = '';
			for ( var cls in S.classes ) {
				if ( ! S.classes.hasOwnProperty( cls ) ) { continue; }
				var rules = S.classes[ cls ][ bp ];
				if ( rules && Object.keys( rules ).length ) { body += cls + ' {\n' + declBlock( rules ) + '}\n'; }
			}
			walkTree( S.tree, function ( node ) {
				var ov = node.overrides && node.overrides[ bp ];
				if ( ov && Object.keys( ov ).length ) { body += '#' + node.id + ' {\n' + declBlock( ov ) + '}\n'; }
			} );
			if ( ! body ) { continue; }
			if ( meta.mq ) { out += '@media ' + meta.mq + ' {\n' + body.replace( /^/gm, '  ' ) + '}\n'; }
			else { out += body; }
			out += '\n';
		}
		return out.replace( /\n{3,}/g, '\n\n' ).trim();
	}
	function genHTML() {
		var S = store.state;
		function render( node ) {
			var cls = node.classes.map( function ( c ) { return c.slice( 1 ); } ).join( ' ' );
			var txt = S.content[ node.id ] || '';
			var kids = node.children.map( render ).join( '' );
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
			doc.write( '<!DOCTYPE html><html><head><meta charset="utf-8"><style id="vb-reset">*{box-sizing:border-box;margin:0}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,sans-serif}[data-node]{outline:1px solid transparent;outline-offset:-1px;transition:outline-color .1s}[data-node]:hover{outline-color:rgba(42,183,241,.45)}[data-node].vb-sel{outline:2px solid #2ab7f1}</style><style id="vb-style"></style></head><body></body></html>' );
			doc.close();
		}
		return doc;
	}
	function injectCanvas() {
		var doc = ensureCanvasDoc(); if ( ! doc || ! doc.body ) { return; }
		var html = genHTML();
		if ( doc.body.getAttribute( 'data-html' ) !== html ) {
			doc.body.innerHTML = html; doc.body.setAttribute( 'data-html', html );
			doc.addEventListener( 'click', function ( e ) {
				var n = e.target.closest ? e.target.closest( '[data-node]' ) : null;
				if ( n ) { e.preventDefault(); store.commit( function ( s ) { s.selection = n.getAttribute( 'data-node' ); resetActiveClass( s ); } ); }
			} );
		}
		doc.getElementById( 'vb-style' ).textContent = genCSS();
		var prev = doc.querySelector( '.vb-sel' ); if ( prev ) { prev.classList.remove( 'vb-sel' ); }
		var selEl = doc.getElementById( store.state.selection ); if ( selEl ) { selEl.classList.add( 'vb-sel' ); }
	}
	function resetActiveClass( s ) { var n = findNode( s.tree, s.selection ); s.activeClass = n && n.classes.length ? n.classes[ 0 ] : null; }

	function setProp( prop, value, elementOverride ) {
		store.commit( function ( s ) {
			var node = findNode( s.tree, s.selection ), bp = s.breakpoint;
			if ( elementOverride ) { node.overrides[ bp ] = node.overrides[ bp ] || {}; node.overrides[ bp ][ prop ] = value; }
			else { var c = s.activeClass; s.classes[ c ] = s.classes[ c ] || {}; s.classes[ c ][ bp ] = s.classes[ c ][ bp ] || {}; s.classes[ c ][ bp ][ prop ] = value; }
		} );
	}
	function removeProp( prop ) { store.commit( function ( s ) { var c = s.activeClass, bp = s.breakpoint; if ( s.classes[ c ] && s.classes[ c ][ bp ] ) { delete s.classes[ c ][ bp ][ prop ]; } } ); }

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

	/* ---------- persistence (save / load via AJAX) ---------- */
	var docId = CFG.docId || 0, docTitle = 'Untitled', saving = false;
	function saveDoc( silent ) {
		if ( saving || ! CFG.ajaxurl ) { return; }
		saving = true; setSaveState( 'saving' );
		var body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', 'builder_save' ); body.set( 'nonce', CFG.nonce || '' );
		body.set( 'id', docId ); body.set( 'title', docTitle ); body.set( 'kind', 'page' );
		body.set( 'data', JSON.stringify( store.state ) );
		body.set( 'css_size', String( new Blob( [ genCSS() ] ).size ) );
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
			.then( function ( res ) { if ( res && res.success && res.data.model ) { docId = res.data.id; docTitle = res.data.title || 'Untitled'; store.init( res.data.model ); } } );
	}
	function setSaveState( state ) {
		var el = document.getElementById( 'vb-save' ); if ( ! el ) { return; }
		var map = { saving:T( 'Saving…' ), saved:T( 'Saved' ), error:T( 'Save failed' ), idle:T( 'Save' ) };
		el.textContent = map[ state ] || map.idle;
		el.className = 'vb-save vb-save--' + state;
	}

	/* ============================================================
	   UI
	   ============================================================ */
	var CONTROLS = [
		{ group:'Layout', icon:'layout', items:[
			{ prop:'display', label:'Display', type:'seg', opts:[ 'block', 'flex', 'grid', 'inline-block' ] },
			{ prop:'flexDirection', label:'Direction', type:'seg', opts:[ 'row', 'column' ] },
			{ prop:'alignItems', label:'Align', type:'seg', opts:[ 'flex-start', 'center', 'stretch', 'flex-end' ] },
			{ prop:'gap', label:'Gap', type:'num', unit:'px' }
		] },
		{ group:'Spacing', icon:'move', items:[
			{ prop:'paddingTop', label:'Padding top', type:'num', unit:'px' },
			{ prop:'paddingBottom', label:'Padding bottom', type:'num', unit:'px' },
			{ prop:'paddingLeft', label:'Padding left', type:'num', unit:'px' },
			{ prop:'marginBottom', label:'Margin bottom', type:'num', unit:'px' }
		] },
		{ group:'Type & color', icon:'type', items:[
			{ prop:'fontSize', label:'Font size', type:'num', unit:'px' },
			{ prop:'fontWeight', label:'Weight', type:'seg', opts:[ '400', '600', '700', '800' ] },
			{ prop:'color', label:'Text color', type:'color' },
			{ prop:'background', label:'Background', type:'color' }
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
			'<button class="vb-publish">' + T( 'Publish' ) + '</button>' +
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
				html += '<div class="vb-tn ' + ( n.id === state.selection ? 'sel' : '' ) + '" data-node="' + n.id + '" style="padding-left:' + ( 8 + depth * 14 ) + 'px">' +
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
		var ac = state.activeClass, bp = state.breakpoint;
		var chips = node.classes.map( function ( c, i ) {
			return '<span class="vb-chip ' + ( i === 0 ? 'base' : 'combo' ) + ' ' + ( c === ac ? 'active' : '' ) + '" data-cls="' + c + '">' + c + '</span>';
		} ).join( '' );
		var acKind = node.classes.indexOf( ac ) === 0 ? 'base' : 'combo';
		var body = '';
		CONTROLS.forEach( function ( g ) {
			body += '<div class="vb-block"><div class="vb-block-h"><span class="vb-block-ic">' + svg( g.icon, 15 ) + '</span><b>' + g.group + '</b></div><div class="vb-block-b">';
			g.items.forEach( function ( it ) {
				var res = resolveProperty( node, bp, it.prop ), dot = dotFor( res, ac, bp ), val = res.value, ctrl = '';
				if ( it.type === 'seg' ) {
					ctrl = '<div class="vb-seg">' + it.opts.map( function ( o ) { return '<button class="' + ( val === o ? 'on' : '' ) + '" data-set="' + it.prop + '" data-val="' + o + '">' + o.replace( 'flex-', '' ) + '</button>'; } ).join( '' ) + '</div>';
				} else if ( it.type === 'num' ) {
					ctrl = '<div class="vb-row"><input class="vb-inp num" data-setnum="' + it.prop + '" value="' + ( val != null ? val : '' ) + '" placeholder="—"><span class="vb-unit">' + it.unit + '</span></div>';
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
				'<div class="vb-bp-note">' + ( bp === 'base' ? T( 'Editing at desktop' ) : T( 'Editing at' ) + ' ' + bp ) + '</div></div>' +
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
			if ( e.target.closest( '[data-dup]' ) ) { duplicateNode( store.state.selection ); return; }
			if ( e.target.closest( '[data-del]' ) ) { deleteNode( store.state.selection ); return; }
			if ( e.target.closest( '#vb-save' ) ) { saveDoc(); return; }
			var tn = e.target.closest( '.vb-tn' ); if ( tn ) { store.commit( function ( s ) { s.selection = tn.getAttribute( 'data-node' ); resetActiveClass( s ); } ); return; }
			var chip = e.target.closest( '.vb-chip' ); if ( chip ) { store.commit( function ( s ) { s.activeClass = chip.getAttribute( 'data-cls' ); } ); return; }
			var seg = e.target.closest( '[data-set][data-val]' ); if ( seg ) { setProp( seg.getAttribute( 'data-set' ), seg.getAttribute( 'data-val' ) ); return; }
			var bp = e.target.closest( '#vb-bp button' ); if ( bp ) { store.commit( function ( s ) { s.breakpoint = bp.getAttribute( 'data-bp' ); } ); resizeCanvas( bp.getAttribute( 'data-bp' ) ); return; }
			if ( ! e.target.closest( '.vb-addmenu' ) ) { closeAddMenu(); }
		} );
		document.addEventListener( 'input', function ( e ) {
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
	}
	function toggleAddMenu( anchor ) {
		var m = document.getElementById( 'vb-addmenu' );
		if ( m.classList.contains( 'open' ) ) { closeAddMenu(); return; }
		m.innerHTML = '<div class="vb-am-h">' + T( 'Add element' ) + '</div>' + CATALOG.map( function ( c ) {
			return '<button class="vb-am-i" data-insert="' + c.key + '"><span class="vb-am-ic">' + svg( elIcon( c.el ), 15 ) + '</span>' + c.label + '</button>';
		} ).join( '' );
		var r = anchor.getBoundingClientRect();
		m.style.left = Math.min( r.left, window.innerWidth - 240 ) + 'px';
		m.style.top = ( r.bottom + 6 ) + 'px';
		m.classList.add( 'open' );
	}
	function closeAddMenu() { var m = document.getElementById( 'vb-addmenu' ); if ( m ) { m.classList.remove( 'open' ); } }
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
			'.vb-editing small{font-size:9px;color:#606069;line-height:1}.vb-editing b{font-size:12px;font-weight:600}',
			'.vb-spring{flex:1}',
			'.vb-exit{color:#9d9da8;text-decoration:none;font-weight:600;padding:8px 13px;border-radius:9px}',
			'.vb-exit:hover{background:#26262f;color:#f4f4f6}',
			'.vb-publish{padding:8px 17px;border-radius:9px;background:linear-gradient(180deg,#218ec4,#1a789f);color:#eef7fc;font-weight:700;border:none;cursor:pointer}',
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
			'.vb-cb-l{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#606069;margin-bottom:9px}',
			'.vb-active-class{display:flex;align-items:center;gap:10px;padding:11px 12px;background:#0a0a0c;border:1px solid rgba(255,255,255,.07);border-radius:10px;margin-bottom:10px}',
			'.vb-ac-dot{width:9px;height:9px;border-radius:3px}',
			'.vb-active-class.base .vb-ac-dot{background:#2ab7f1}.vb-active-class.combo .vb-ac-dot{background:#a06bff}',
			'.vb-ac-name{flex:1;font-family:ui-monospace,Menlo,monospace;font-size:14px;font-weight:600}',
			'.vb-active-class.base .vb-ac-name{color:#2ab7f1}.vb-active-class.combo .vb-ac-name{color:#a06bff}',
			'.vb-ac-kind{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:3px 8px;border-radius:6px}',
			'.vb-active-class.base .vb-ac-kind{background:rgba(42,183,241,.14);color:#2ab7f1}.vb-active-class.combo .vb-ac-kind{background:rgba(160,107,255,.15);color:#a06bff}',
			'.vb-chips{display:flex;flex-wrap:wrap;gap:5px}',
			'.vb-chip{padding:4px 9px;border-radius:7px;font-family:ui-monospace,Menlo,monospace;font-size:11px;cursor:pointer;background:#1d1d24;color:#9d9da8;border:1px solid transparent}',
			'.vb-chip:hover{background:#26262f;color:#f4f4f6}',
			'.vb-chip.active.base{background:rgba(42,183,241,.15);color:#2ab7f1;border-color:rgba(42,183,241,.4)}',
			'.vb-chip.active.combo{background:rgba(160,107,255,.15);color:#a06bff;border-color:rgba(160,107,255,.4)}',
			'.vb-bp-note{font-size:10px;color:#606069;margin-top:9px}',
			'.vb-controls{padding:12px}',
			'.vb-block{margin-bottom:8px;background:#17171d;border:1px solid rgba(255,255,255,.07);border-radius:12px;overflow:hidden}',
			'.vb-block-h{display:flex;align-items:center;gap:9px;padding:11px 13px}',
			'.vb-block-ic{color:#9d9da8;display:grid;place-items:center}.vb-block-h b{font-size:12px;font-weight:600}',
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
