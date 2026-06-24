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
			'.velox-gseo{padding:4px 0}' +
			'.velox-gseo-preview{border:1px solid #dcdcde;border-radius:8px;padding:12px 14px;margin-bottom:16px;background:#fff}' +
			'.velox-gseo-url{color:#202124;font-size:12px;margin-bottom:2px;word-break:break-all}' +
			'.velox-gseo-title{color:#1a0dab;font-size:16px;line-height:1.3;font-weight:500}' +
			'.velox-gseo-desc{color:#4d5156;font-size:13px;line-height:1.5;margin-top:3px}' +
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

		var kwHelp = 'The main phrase you want this page to rank for.';
		if ( focusKw ) {
			var kw = focusKw.toLowerCase();
			var inTitle = ( seoTitle || postTitle ).toLowerCase().indexOf( kw ) !== -1;
			var inDesc  = seoDesc.toLowerCase().indexOf( kw ) !== -1;
			kwHelp = ( inTitle ? '\u2713 in title' : '\u2022 not in title' ) + '   \u00b7   ' + ( inDesc ? '\u2713 in description' : '\u2022 not in description' );
		}

		var preview = el( 'div', { className: 'velox-gseo-preview' },
			el( 'div', { className: 'velox-gseo-url' }, link || '' ),
			el( 'div', { className: 'velox-gseo-title' }, seoTitle || postTitle || 'Page title' ),
			el( 'div', { className: 'velox-gseo-desc' }, seoDesc || 'Add a meta description to control how this page looks in Google.' )
		);

		var titleHelp = seoTitle.length + ' / 60 chars' + ( seoTitle.length > 60 ? ' — a bit long' : '' );
		var descHelp  = seoDesc.length + ' / 160 chars' + ( seoDesc.length > 160 ? ' — a bit long' : '' );

		var body = el( 'div', { className: 'velox-gseo' },
			el( c.PanelBody, { title: 'Search appearance', initialOpen: true },
				preview,
				el( c.TextControl, { label: 'SEO title', value: seoTitle, help: titleHelp, onChange: function ( v ) { setMeta( '_velox_seo_title', v ); } } ),
				el( c.TextareaControl, { label: 'Meta description', value: seoDesc, rows: 4, help: descHelp, onChange: function ( v ) { setMeta( '_velox_seo_desc', v ); } } )
			),
			el( c.PanelBody, { title: 'Search engines', initialOpen: true },
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
				el( c.TextControl, { label: 'Canonical URL', value: canonical, help: 'Leave empty to use this page\u2019s own URL.', onChange: function ( v ) { setMeta( '_velox_seo_canonical', v ); } } ),
				el( c.TextControl, { label: 'Focus keyword', value: focusKw, help: kwHelp, onChange: function ( v ) { setMeta( '_velox_seo_focus_kw', v ); } } )
			)
		);

		return el( Fragment, {},
			el( PluginSidebarMoreMenuItem, { target: 'velox-seo', icon: icon() }, 'Velox SEO' ),
			el( PluginSidebar, { name: 'velox-seo', title: 'Velox SEO', icon: icon() }, body )
		);
	}

	wp.plugins.registerPlugin( 'velox-seo', { render: VeloxSeoPanel, icon: icon() } );
} )( window.wp );
