/**
 * Velox SEO — block-editor sidebar panel.
 * Adds a Velox button to the editor top bar that opens a Rank-Math-style SEO
 * panel, bound directly to the post's REST meta (so it saves with the post).
 */
( function ( wp ) {
	if ( ! wp || ! wp.plugins || ! wp.element || ! wp.data || ! wp.components ) {
		return;
	}

	var el       = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var c        = wp.components;
	var useSelect   = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var editPost = wp.editPost || {};
	var editor   = wp.editor || {};
	var PluginSidebar             = editor.PluginSidebar || editPost.PluginSidebar;
	var PluginSidebarMoreMenuItem = editor.PluginSidebarMoreMenuItem || editPost.PluginSidebarMoreMenuItem;
	if ( ! PluginSidebar ) {
		return;
	}

	var DATA = window.VeloxSeoData || { postTypes: [ 'post', 'page', 'product' ], icon: '' };

	function icon() {
		return DATA.icon
			? el( 'img', { src: DATA.icon, alt: '', style: { width: 20, height: 20, display: 'block' } } )
			: 'megaphone';
	}

	// Inject the small preview styling once.
	( function injectStyles() {
		if ( document.getElementById( 'velox-gseo-css' ) ) { return; }
		var s = document.createElement( 'style' );
		s.id = 'velox-gseo-css';
		s.textContent =
			'.velox-gseo{padding:0;overflow-x:hidden}' +
			'.velox-gseo *{min-width:0}' +
			'.velox-gseo-score{display:flex;align-items:center;gap:12px;padding:14px 16px;background:#f6f7f7;border-bottom:1px solid #e0e0e3}' +
			'.velox-gseo-ring{width:46px;height:46px;border-radius:50%;flex:none;display:flex;align-items:center;justify-content:center}' +
			'.velox-gseo-ring i{width:36px;height:36px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;font-style:normal;font-size:12.5px;font-weight:700}' +
			'.velox-gseo-verdict{font-size:13px;font-weight:650;color:#1d2327}' +
			'.velox-gseo-blurb{font-size:11.5px;color:#646970;margin-top:2px;line-height:1.4}' +
			'.velox-gseo-checks{padding:6px 16px 10px;border-bottom:1px solid #e0e0e3}' +
			'.velox-gseo-ck{display:flex;gap:9px;align-items:flex-start;padding:8px 0;border-top:1px solid #f0f0f1}' +
			'.velox-gseo-ck:first-child{border-top:0}' +
			'.velox-gseo-m{width:16px;height:16px;border-radius:50%;flex:none;margin-top:1px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;line-height:1}' +
			'.velox-gseo-m.g{background:#1d8a4e}.velox-gseo-m.r{background:#c8362f}.velox-gseo-m.a{background:#e8a33d}' +
			'.velox-gseo-ct{font-size:12px;color:#1d2327;line-height:1.45;overflow-wrap:anywhere}' +
			/* Search preview card — host and breadcrumb on their own lines, the way
			   Google actually renders it, instead of one break-all URL string. */
			'.velox-gseo-preview{border:1px solid #dcdcde;border-radius:8px;padding:12px 14px;background:#fff;overflow:hidden}' +
			'.velox-gseo-site{display:flex;align-items:flex-start;gap:9px;margin:0 0 7px}' +
			'.velox-gseo-fav{width:20px;height:20px;border-radius:50%;background:#e8eaed;flex:none}' +
			'.velox-gseo-host{font-size:12.5px;color:#202124;line-height:1.35;overflow-wrap:anywhere}' +
			'.velox-gseo-crumb{font-size:12px;color:#5f6368;line-height:1.35;overflow-wrap:anywhere}' +
			'.velox-gseo-title{color:#1a0dab;font-size:16px;line-height:1.3;font-weight:500;overflow-wrap:anywhere}' +
			'.velox-gseo-desc{color:#4d5156;font-size:13px;line-height:1.5;margin-top:3px;overflow-wrap:anywhere}' +
			'.velox-gseo-desc.is-empty{color:#8c8f94;font-style:italic}' +
			/* Field block: label left, live counter right, meter directly under the
			   input. The meter used to carry margin-top:-8px, which dragged it up on
			   top of the help text and struck the character count through. */
			'.velox-gseo-field{margin:0 0 18px}' +
			'.velox-gseo-field:last-child{margin-bottom:0}' +
			'.velox-gseo-fh{display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin:0 0 6px}' +
			'.velox-gseo-fl{font-size:13px;font-weight:600;color:#1d2327}' +
			'.velox-gseo-fc{font-size:12px;color:#787c82;flex:none;font-variant-numeric:tabular-nums}' +
			'.velox-gseo-fc.is-over{color:#c8362f;font-weight:600}' +
			'.velox-gseo-hint{margin:6px 0 0;font-size:12px;color:#646970;line-height:1.4}' +
			'.velox-gseo-bar{height:3px;border-radius:99px;background:#e8e8ea;overflow:hidden;margin:7px 0 0}' +
			'.velox-gseo-bar span{display:block;height:100%;border-radius:99px;transition:width .15s}' +
			'.velox-gseo-field .components-base-control{margin-bottom:0}' +
			'.velox-gseo-seg{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 16px;flex-wrap:wrap}' +
			'.velox-gseo-seg-label{font-weight:600;font-size:13px}' +
			'.velox-gseo-out{margin:4px 0 0;color:#646970;font-size:12px}' +
			'.velox-gseo-out code{background:#f0f0f1;padding:2px 6px;border-radius:4px}' +
			/* Drag handle on the sidebar's left edge. WordPress pins the editor
			   sidebar at 280px with no way to resize it, so we add one. */
			'.velox-seo-resizer{position:absolute;top:0;left:-3px;width:7px;height:100%;cursor:col-resize;z-index:120;touch-action:none}' +
			'.velox-seo-resizer::before{content:"";position:absolute;top:0;left:2px;width:3px;height:100%;background:transparent;transition:background .12s}' +
			'.velox-seo-resizer:hover::before{background:#2ab7f1}' +
			'body.velox-seo-resizing{cursor:col-resize;user-select:none}' +
			'body.velox-seo-resizing .velox-seo-resizer::before{background:#2ab7f1}' +
			'body.velox-seo-resizing iframe{pointer-events:none}';
		document.head.appendChild( s );
	} )();

	function VeloxSeoPanel() {
		var meta = useSelect( function ( s ) {
			return s( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );
		var postType = useSelect( function ( s ) {
			return s( 'core/editor' ).getCurrentPostType();
		}, [] );
		var postTitle = useSelect( function ( s ) {
			return s( 'core/editor' ).getEditedPostAttribute( 'title' ) || '';
		}, [] );
		var link = useSelect( function ( s ) {
			return s( 'core/editor' ).getEditedPostAttribute( 'link' ) || '';
		}, [] );
		var dispatch = useDispatch( 'core/editor' );

		if ( DATA.postTypes.indexOf( postType ) === -1 ) {
			return null;
		}

		function setMeta( k, v ) {
			var patch = {};
			patch[ k ] = v;
			dispatch.editPost( { meta: Object.assign( {}, meta, patch ) } );
		}

		var seoTitle = meta._velox_seo_title || '';
		var seoDesc  = meta._velox_seo_desc || '';
		var noindex  = meta._velox_seo_noindex === '1';
		var nofollow = meta._velox_seo_nofollow === '1';
		var exclude  = meta.sitemap_exclude === '1';
		var canonical = meta._velox_seo_canonical || '';
		var focusKw   = meta._velox_seo_focus_kw || '';
		var ogTitle   = meta._velox_seo_og_title || '';
		var ogDesc    = meta._velox_seo_og_desc || '';
		var ogImage   = meta._velox_seo_og_image || '';

		// Blocks let us judge the page itself, not just its meta. Pages built with
		// a builder have no blocks, so those checks are skipped rather than failed.
		var blocks = useSelect( function ( s ) {
			return s( 'core/block-editor' ) ? s( 'core/block-editor' ).getBlocks() : [];
		}, [] );

		function flatten( list, out ) {
			( list || [] ).forEach( function ( b ) {
				out.push( b );
				if ( b.innerBlocks && b.innerBlocks.length ) { flatten( b.innerBlocks, out ); }
			} );
			return out;
		}
		var all = flatten( blocks, [] );
		var hasBlocks = all.length > 0;
		var hasH1 = all.some( function ( b ) {
			return 'core/heading' === b.name && 1 === parseInt( b.attributes && b.attributes.level, 10 );
		} );
		var imgs = all.filter( function ( b ) { return 'core/image' === b.name; } );
		var imgsNoAlt = imgs.filter( function ( b ) {
			return ! ( b.attributes && b.attributes.alt && String( b.attributes.alt ).trim() );
		} ).length;

		var effTitle = seoTitle || postTitle || '';
		var kwLower = focusKw.trim().toLowerCase();

		// Each check: pass/warn/fail plus the reason it matters.
		var checks = [];
		checks.push( ! seoTitle
			? { s: 'r', t: 'No search title — Google uses the page title' }
			: ( seoTitle.length > 60
				? { s: 'a', t: 'Search title is ' + seoTitle.length + ' characters — over 60 gets cut off' }
				: { s: 'g', t: 'Search title set, ' + seoTitle.length + ' of 60 characters' } ) );
		checks.push( ! seoDesc
			? { s: 'r', t: 'No meta description — Google writes its own' }
			: ( seoDesc.length > 160
				? { s: 'a', t: 'Description is ' + seoDesc.length + ' characters — over 160 gets cut off' }
				: { s: 'g', t: 'Description set, ' + seoDesc.length + ' of 160 characters' } ) );
		if ( ! kwLower ) {
			checks.push( { s: 'a', t: 'No focus keyword set — add one to check it is actually used' } );
		} else {
			checks.push( effTitle.toLowerCase().indexOf( kwLower ) !== -1
				? { s: 'g', t: 'Keyword “' + focusKw + '” is in the title' }
				: { s: 'a', t: 'Keyword “' + focusKw + '” is not in the title' } );
			checks.push( seoDesc.toLowerCase().indexOf( kwLower ) !== -1
				? { s: 'g', t: 'Keyword appears in the description' }
				: { s: 'a', t: 'Keyword is not in the description' } );
		}
		checks.push( noindex
			? { s: 'a', t: 'Set to noindex — this page will not appear in search' }
			: { s: 'g', t: 'Page is indexable' } );
		checks.push( exclude
			? { s: 'a', t: 'Excluded from the sitemap' }
			: { s: 'g', t: 'Included in the sitemap' } );
		if ( hasBlocks ) {
			checks.push( hasH1
				? { s: 'g', t: 'Page has an H1 heading' }
				: { s: 'a', t: 'No H1 heading — search engines use it to read the page' } );
			if ( imgs.length ) {
				checks.push( imgsNoAlt
					? { s: 'a', t: imgsNoAlt + ' image' + ( 1 === imgsNoAlt ? '' : 's' ) + ' here have no alt text' }
					: { s: 'g', t: 'All images here have alt text' } );
			}
		}

		var passed = checks.filter( function ( ck ) { return 'g' === ck.s; } ).length;
		var pct = checks.length ? Math.round( ( passed / checks.length ) * 100 ) : 0;
		var tone = pct >= 100 ? '#1d8a4e' : ( pct >= 60 ? '#e8a33d' : '#c8362f' );
		var verdict = pct >= 100 ? 'Looking good' : ( pct >= 60 ? 'Nearly there' : 'Needs work' );
		var blurb = pct >= 100
			? 'Title, description and keyword all line up.'
			: ( ! seoTitle && ! seoDesc
				? 'This page can be found, but you\u2019re leaving how it looks up to Google.'
				: 'A few things are still worth fixing.' );

		var scoreEl = el( 'div', { className: 'velox-gseo-score' },
			el( 'span', {
				className: 'velox-gseo-ring',
				style: { background: 'conic-gradient(' + tone + ' 0 ' + pct + '%, #e0e0e3 0)' }
			}, el( 'i', { style: { color: tone } }, passed + '/' + checks.length ) ),
			el( 'span', {},
				el( 'div', { className: 'velox-gseo-verdict' }, verdict ),
				el( 'div', { className: 'velox-gseo-blurb' }, blurb )
			)
		);

		var checklistEl = el( 'div', { className: 'velox-gseo-checks' },
			checks.map( function ( ck, i ) {
				return el( 'div', { className: 'velox-gseo-ck', key: 'ck' + i },
					el( 'span', { className: 'velox-gseo-m ' + ck.s }, 'g' === ck.s ? '\u2713' : '!' ),
					el( 'span', { className: 'velox-gseo-ct' }, ck.t )
				);
			} )
		);

		// Google shows the host and a breadcrumb, not the raw URL. Rendering the
		// full link with word-break:break-all snapped domains mid-word.
		var host = '', crumb = '';
		try {
			var u = new URL( link || window.location.href );
			host  = u.hostname;
			crumb = u.pathname.split( '/' ).filter( Boolean ).join( ' \u203a ' );
		} catch ( e ) {
			host = link || '';
		}

		var preview = el( 'div', { className: 'velox-gseo-preview' },
			el( 'div', { className: 'velox-gseo-site' },
				el( 'span', { className: 'velox-gseo-fav' } ),
				el( 'span', {},
					el( 'div', { className: 'velox-gseo-host' }, host ),
					crumb ? el( 'div', { className: 'velox-gseo-crumb' }, '\u203a ' + crumb ) : null
				)
			),
			el( 'div', { className: 'velox-gseo-title' }, effTitle || 'Page title' ),
			el( 'div', { className: 'velox-gseo-desc' + ( seoDesc ? '' : ' is-empty' ) },
				seoDesc || 'No description yet — Google will pick a sentence from the page.' )
		);

		/**
		 * A labelled field with the character count sitting on the same line as
		 * the label and the meter directly beneath the input.
		 */
		function field( label, control, hint, len, max ) {
			var counted = 'number' === typeof len && max;
			var over = counted && len > max;
			var near = counted && ! over && len > max * 0.9;
			var w    = counted ? Math.min( 100, Math.round( ( len / max ) * 100 ) ) : 0;
			var col  = over ? '#c8362f' : ( near ? '#e8a33d' : '#1d8a4e' );
			return el( 'div', { className: 'velox-gseo-field' },
				el( 'div', { className: 'velox-gseo-fh' },
					el( 'span', { className: 'velox-gseo-fl' }, label ),
					counted ? el( 'span', { className: 'velox-gseo-fc' + ( over ? ' is-over' : '' ) }, len + ' / ' + max ) : null
				),
				control,
				counted ? el( 'div', { className: 'velox-gseo-bar' },
					el( 'span', { style: { width: w + '%', background: len ? col : 'transparent' } } ) ) : null,
				hint ? el( 'p', { className: 'velox-gseo-hint' }, hint ) : null
			);
		}

		var body = el( 'div', { className: 'velox-gseo' },
			scoreEl,
			checklistEl,
			el( c.PanelBody, { title: 'Preview', initialOpen: true }, preview ),
			el( c.PanelBody, { title: 'Search appearance', initialOpen: true },
				field( 'Focus keyword',
					el( c.TextControl, {
						value: focusKw,
						__nextHasNoMarginBottom: true,
						onChange: function ( v ) { setMeta( '_velox_seo_focus_kw', v ); }
					} ),
					'The phrase this page should rank for.'
				),
				field( 'Search title',
					el( c.TextControl, {
						value: seoTitle,
						placeholder: postTitle ? 'Using the page title: \u201c' + postTitle + '\u201d' : 'Using the page title',
						__nextHasNoMarginBottom: true,
						onChange: function ( v ) { setMeta( '_velox_seo_title', v ); }
					} ),
					null, seoTitle.length, 60
				),
				field( 'Meta description',
					el( c.TextareaControl, {
						value: seoDesc, rows: 4,
						placeholder: 'Write what should show under the title\u2026',
						__nextHasNoMarginBottom: true,
						onChange: function ( v ) { setMeta( '_velox_seo_desc', v ); }
					} ),
					null, seoDesc.length, 160
				)
			),
			el( c.PanelBody, { title: 'Search engines', initialOpen: false },
				el( 'div', { className: 'velox-gseo-seg' },
					el( 'span', { className: 'velox-gseo-seg-label' }, 'Indexing' ),
					el( c.ButtonGroup, {},
						el( c.Button, { variant: noindex ? 'secondary' : 'primary', onClick: function () { setMeta( '_velox_seo_noindex', '0' ); } }, 'Index' ),
						el( c.Button, { variant: noindex ? 'primary' : 'secondary', onClick: function () { setMeta( '_velox_seo_noindex', '1' ); } }, 'Noindex' )
					)
				),
				el( 'div', { className: 'velox-gseo-seg' },
					el( 'span', { className: 'velox-gseo-seg-label' }, 'Links' ),
					el( c.ButtonGroup, {},
						el( c.Button, { variant: nofollow ? 'secondary' : 'primary', onClick: function () { setMeta( '_velox_seo_nofollow', '0' ); } }, 'Follow' ),
						el( c.Button, { variant: nofollow ? 'primary' : 'secondary', onClick: function () { setMeta( '_velox_seo_nofollow', '1' ); } }, 'Nofollow' )
					)
				),
				el( c.ToggleControl, { label: 'Exclude this page from the sitemap', checked: exclude, onChange: function ( v ) { setMeta( 'sitemap_exclude', v ? '1' : '0' ); } } ),
				el( 'p', { className: 'velox-gseo-out' }, 'Search engines will be told: ',
					el( 'code', {}, ( noindex ? 'noindex' : 'index' ) + ', ' + ( nofollow ? 'nofollow' : 'follow' ) )
				)
			),
			el( c.PanelBody, { title: 'Social (Open Graph)', initialOpen: false },
				el( c.TextControl, { label: 'Social title', value: ogTitle, help: 'Shown when shared on Facebook, LinkedIn, X. Falls back to the SEO title.', onChange: function ( v ) { setMeta( '_velox_seo_og_title', v ); } } ),
				el( c.TextareaControl, { label: 'Social description', value: ogDesc, rows: 3, help: 'Falls back to the meta description.', onChange: function ( v ) { setMeta( '_velox_seo_og_desc', v ); } } ),
				el( c.TextControl, { label: 'Social image URL', value: ogImage, help: 'Defaults to the featured image. Recommended 1200\u00d7630.', onChange: function ( v ) { setMeta( '_velox_seo_og_image', v ); } } )
			),
			el( c.PanelBody, { title: 'Advanced', initialOpen: false },
				el( c.TextControl, { label: 'Canonical URL', value: canonical, help: 'Leave empty to use this page\u2019s own URL.', onChange: function ( v ) { setMeta( '_velox_seo_canonical', v ); } } )
			)
		);

		return el( Fragment, {},
			el( PluginSidebarMoreMenuItem, { target: 'velox-seo', icon: icon() }, 'Velox SEO' ),
			el( PluginSidebar, { name: 'velox-seo', title: 'Velox SEO', icon: icon() }, body )
		);
	}

	wp.plugins.registerPlugin( 'velox-seo', { render: VeloxSeoPanel, icon: icon() } );
} )( window.wp );

	/**
	 * Opening from the SEO health list: pop this sidebar open automatically and
	 * put the cursor in whichever field is still empty.
	 */
	( function () {
		if ( ! /[?&]velox-seo=1/.test( window.location.search ) ) {
			return;
		}
		var TARGET = 'velox-seo/velox-seo';

		function openSidebar() {
			try {
				var iface = wp.data.dispatch( 'core/interface' );
				if ( iface && iface.enableComplementaryArea ) {
					iface.enableComplementaryArea( 'core/edit-post', TARGET );
					return true;
				}
			} catch ( e ) {}
			try {
				var ep = wp.data.dispatch( 'core/edit-post' );
				if ( ep && ep.openGeneralSidebar ) {
					ep.openGeneralSidebar( TARGET );
					return true;
				}
			} catch ( e2 ) {}
			return false;
		}

		function focusFirstEmpty() {
			var panel = document.querySelector( '.velox-gseo' );
			if ( ! panel ) { return false; }
			var fields = panel.querySelectorAll( 'input[type="text"], textarea' );
			for ( var i = 0; i < fields.length; i++ ) {
				if ( ! fields[ i ].value ) { fields[ i ].focus(); return true; }
			}
			if ( fields.length ) { fields[0].focus(); return true; }
			return false;
		}

		// The editor mounts asynchronously, so keep trying briefly rather than
		// firing once and hoping.
		var tries = 0;
		var opened = false;
		var timer = setInterval( function () {
			tries++;
			if ( ! opened ) { opened = openSidebar(); }
			if ( opened ) {
				// Give the panel a moment to mount, then stop regardless.
				if ( focusFirstEmpty() || tries > 12 ) { clearInterval( timer ); return; }
			}
			if ( tries > 40 ) { clearInterval( timer ); }
		}, 250 );
	}() );

	/**
	 * Resizable sidebar.
	 *
	 * WordPress pins the editor sidebar at a fixed 280px — there is no core
	 * resize — so we add a drag handle on its left edge while the Velox SEO
	 * panel is mounted, and put the width back when it unmounts so the Page and
	 * Block tabs are unaffected. Width is remembered per browser.
	 */
	( function veloxSeoResizer() {
		var MIN = 280;
		var KEY = 'veloxSeoSidebarWidth';
		var shell = null;
		var handle = null;
		var stretched = [];
		var queued = false;

		function maxWidth() {
			return Math.max( MIN, Math.min( 760, window.innerWidth - 360 ) );
		}

		function stored() {
			try {
				var v = parseInt( window.localStorage.getItem( KEY ), 10 );
				return ( v >= MIN ) ? Math.min( v, maxWidth() ) : 0;
			} catch ( e ) { return 0; }
		}

		function save( w ) {
			try { window.localStorage.setItem( KEY, String( Math.round( w ) ) ); } catch ( e ) {}
		}

		// Class names have moved between editor packages, so walk up rather than
		// hard-coding one selector.
		function findShell( node ) {
			var n = node;
			while ( n && n !== document.body ) {
				if ( n.classList && (
					n.classList.contains( 'interface-interface-skeleton__sidebar' ) ||
					n.classList.contains( 'editor-interface-skeleton__sidebar' )
				) ) { return n; }
				n = n.parentElement;
			}
			var area = node.closest ? node.closest( '.interface-complementary-area' ) : null;
			return area ? area.parentElement : null;
		}

		function apply( w ) {
			if ( ! shell ) { return; }
			shell.style.width = w + 'px';
			shell.style.flexBasis = w + 'px';
			shell.style.maxWidth = 'none';
		}

		// Widening the shell alone is not enough: WordPress puts one or more
		// wrappers between it and our panel, and those carry their own 280px.
		// The class names have moved between editor packages, so stretch
		// whatever is actually in the chain rather than naming them.
		function stretch() {
			unstretch();
			var panel = document.querySelector( '.velox-gseo' );
			if ( ! panel || ! shell ) { return; }
			var n = panel.parentElement;
			while ( n && n !== shell && n !== document.body ) {
				n.style.width = '100%';
				n.style.maxWidth = 'none';
				n.style.minWidth = '0';
				stretched.push( n );
				n = n.parentElement;
			}
		}

		function unstretch() {
			stretched.forEach( function ( n ) {
				n.style.width = '';
				n.style.maxWidth = '';
				n.style.minWidth = '';
			} );
			stretched = [];
		}

		function reset() {
			unstretch();
			if ( ! shell ) { return; }
			shell.style.width = '';
			shell.style.flexBasis = '';
			shell.style.maxWidth = '';
			if ( handle && handle.parentElement ) { handle.parentElement.removeChild( handle ); }
			handle = null;
			shell = null;
		}

		function startDrag( e ) {
			if ( ! shell || 0 !== e.button ) { return; }
			e.preventDefault();
			var startX = e.clientX;
			var startW = shell.getBoundingClientRect().width;
			var live = startW;

			// Pointer capture keeps the move events coming even once the cursor
			// crosses into the editor canvas iframe.
			try { handle.setPointerCapture( e.pointerId ); } catch ( err ) {}
			document.body.classList.add( 'velox-seo-resizing' );

			function move( ev ) {
				live = Math.max( MIN, Math.min( maxWidth(), startW + ( startX - ev.clientX ) ) );
				apply( live );
			}
			function stop() {
				handle.removeEventListener( 'pointermove', move );
				handle.removeEventListener( 'pointerup', stop );
				handle.removeEventListener( 'pointercancel', stop );
				document.body.classList.remove( 'velox-seo-resizing' );
				save( live );
			}
			handle.addEventListener( 'pointermove', move );
			handle.addEventListener( 'pointerup', stop );
			handle.addEventListener( 'pointercancel', stop );
		}

		function sync() {
			queued = false;
			var panel = document.querySelector( '.velox-gseo' );
			if ( ! panel ) { reset(); return; }

			if ( ! shell || ! shell.isConnected || ! handle || ! handle.isConnected ) {
				shell = findShell( panel );
				if ( ! shell ) { return; }
				if ( 'static' === window.getComputedStyle( shell ).position ) {
					shell.style.position = 'relative';
				}

				handle = document.createElement( 'div' );
				handle.className = 'velox-seo-resizer';
				handle.setAttribute( 'role', 'separator' );
				handle.setAttribute( 'aria-orientation', 'vertical' );
				handle.setAttribute( 'title', 'Drag to resize — double-click to reset' );
				handle.addEventListener( 'pointerdown', startDrag );
				handle.addEventListener( 'dblclick', function () {
					try { window.localStorage.removeItem( KEY ); } catch ( e ) {}
					shell.style.width = '';
					shell.style.flexBasis = '';
				} );
				shell.appendChild( handle );

				var w = stored();
				if ( w ) { apply( w ); }
				stretch();
				return;
			}

			// A re-render can swap the wrappers out from under us, which drops
			// the inline widths and snaps the content back to 280px.
			if ( ! stretched.length || ! stretched[ 0 ].isConnected ) { stretch(); }
		}

		function schedule() {
			if ( queued ) { return; }
			queued = true;
			window.requestAnimationFrame( sync );
		}

		function boot() {
			new window.MutationObserver( schedule ).observe( document.body, { childList: true, subtree: true } );
			schedule();
		}

		if ( 'loading' === document.readyState ) {
			document.addEventListener( 'DOMContentLoaded', boot );
		} else {
			boot();
		}
	}() );
