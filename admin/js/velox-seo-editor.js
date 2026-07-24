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
			'.velox-gseo{padding:0}' +
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
			'.velox-gseo-ct{font-size:12px;color:#1d2327;line-height:1.45}' +
			'.velox-gseo-preview{border:1px solid #dcdcde;border-radius:8px;padding:12px 14px;background:#fff}' +
			'.velox-gseo-url{color:#202124;font-size:12px;margin-bottom:2px;word-break:break-all}' +
			'.velox-gseo-title{color:#1a0dab;font-size:16px;line-height:1.3;font-weight:500}' +
			'.velox-gseo-desc{color:#4d5156;font-size:13px;line-height:1.5;margin-top:3px}' +
			'.velox-gseo-desc.is-empty{color:#8c8f94;font-style:italic}' +
			'.velox-gseo-bar{height:3px;border-radius:99px;background:#e8e8ea;overflow:hidden;margin:-8px 0 16px}' +
			'.velox-gseo-bar span{display:block;height:100%;border-radius:99px;transition:width .15s}' +
			'.velox-gseo-seg{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 16px}' +
			'.velox-gseo-seg-label{font-weight:600;font-size:13px}' +
			'.velox-gseo-out{margin:4px 0 0;color:#646970;font-size:12px}' +
			'.velox-gseo-out code{background:#f0f0f1;padding:2px 6px;border-radius:4px}';
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

		var preview = el( 'div', { className: 'velox-gseo-preview' },
			el( 'div', { className: 'velox-gseo-url' }, link || '' ),
			el( 'div', { className: 'velox-gseo-title' }, effTitle || 'Page title' ),
			el( 'div', { className: 'velox-gseo-desc' + ( seoDesc ? '' : ' is-empty' ) },
				seoDesc || 'No description yet — Google will pick a sentence from the page.' )
		);

		function meter( len, max ) {
			var w = Math.min( 100, max ? Math.round( ( len / max ) * 100 ) : 0 );
			var col = len > max ? '#c8362f' : ( w > 90 ? '#e8a33d' : '#1d8a4e' );
			return el( 'div', { className: 'velox-gseo-bar' },
				el( 'span', { style: { width: w + '%', background: len ? col : 'transparent' } } ) );
		}

		var body = el( 'div', { className: 'velox-gseo' },
			scoreEl,
			checklistEl,
			el( c.PanelBody, { title: 'Preview', initialOpen: true }, preview ),
			el( c.PanelBody, { title: 'Search appearance', initialOpen: true },
				el( c.TextControl, {
					label: 'Focus keyword', value: focusKw,
					help: 'The phrase this page should rank for.',
					onChange: function ( v ) { setMeta( '_velox_seo_focus_kw', v ); }
				} ),
				el( c.TextControl, {
					label: 'Search title', value: seoTitle,
					help: seoTitle.length + ' / 60 characters',
					onChange: function ( v ) { setMeta( '_velox_seo_title', v ); }
				} ),
				meter( seoTitle.length, 60 ),
				el( c.TextareaControl, {
					label: 'Meta description', value: seoDesc, rows: 4,
					help: seoDesc.length + ' / 160 characters',
					onChange: function ( v ) { setMeta( '_velox_seo_desc', v ); }
				} ),
				meter( seoDesc.length, 160 )
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
