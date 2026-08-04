/**
 * Velox SEO — block-editor sidebar panel.
 * Adds a Velox button to the editor top bar that opens a Rank-Math-style SEO
 * panel, bound directly to the post's REST meta (so it saves with the post).
 */
( function ( wp ) {
	if ( ! wp || ! wp.plugins || ! wp.element || ! wp.data || ! wp.components ) {
		return;
	}

	// Translation helper — reads the dictionary shipped with VeloxSeoData.i18n,
	// falls back to the English source string.
	var VX_I18N = ( window.VeloxSeoData && window.VeloxSeoData.i18n ) ? window.VeloxSeoData.i18n : {};
	function vxT( s ) {
		return ( VX_I18N && Object.prototype.hasOwnProperty.call( VX_I18N, s ) ) ? VX_I18N[ s ] : s;
	}

	var el       = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
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
			'.velox-gseo{padding:0;overflow-x:hidden;background:#fff}' +
			'.velox-gseo *{min-width:0}' +
			'.velox-gseo button{font-family:inherit}' +
			/* Device toggle row. WordPress draws its own sidebar header above this,
			   so the panel does not repeat the "Velox SEO" title. */
			'.velox-gseo-top{display:flex;align-items:center;justify-content:flex-end;padding:12px 16px;border-bottom:1px solid #e6e7e9}' +
			/* Segmented control, used for the device toggle and for index/follow.
			   WordPress styles every button in the admin, so these are hard reset. */
			'.velox-gseo-seg2{display:flex;background:#f0f0f1;border-radius:8px;padding:3px;gap:3px}' +
			'.velox-gseo-seg2--tight{display:inline-flex}' +
			'.velox-gseo-seg2 button{flex:1 1 auto;border:0!important;background:transparent!important;border-radius:6px!important;padding:7px 16px!important;margin:0!important;font-size:13px!important;font-weight:500!important;line-height:1.3!important;color:#646970!important;cursor:pointer;box-shadow:none!important;min-height:0!important;height:auto!important;text-shadow:none!important;text-decoration:none!important}' +
			'.velox-gseo-seg2 button:focus{outline:0!important}' +
			'.velox-gseo-seg2 button.is-on{background:#fff!important;color:#1d2327!important;font-weight:600!important;box-shadow:0 1px 2px rgba(0,0,0,.09)!important}' +
			'.velox-gseo-seg2--wide button.is-on{color:#2271b1!important}' +
			/* Status pills replacing the score ring and checklist. */
			'.velox-gseo-pills{display:flex;flex-wrap:wrap;gap:8px;padding:14px 16px;background:#fafafa;border-bottom:1px solid #e6e7e9}' +
			'.velox-gseo-pill{display:inline-flex;align-items:center;gap:7px;padding:6px 12px;border-radius:999px;font-size:12.5px;font-weight:600;line-height:1.2;border:1px solid}' +
			'.velox-gseo-pill::before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor;flex:none}' +
			'.velox-gseo-pill.g{color:#1d8a4e;border-color:#b7e0c4;background:#f2fbf5}' +
			'.velox-gseo-pill.a{color:#9a6212;border-color:#f0d9a8;background:#fdf8ef}' +
			'.velox-gseo-pill.r{color:#c8362f;border-color:#f5c2c0;background:#fef5f4}' +
			/* Sections and collapsed rows. */
			'.velox-gseo-sec{padding:18px 16px;border-top:1px solid #e6e7e9}' +
			'.velox-gseo-sl{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#8c8f94;margin:0 0 13px}' +
			'.velox-gseo-col{display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;box-sizing:border-box;padding:18px 16px;border:0!important;border-top:1px solid #e6e7e9!important;border-radius:0!important;background:transparent!important;cursor:pointer;text-align:left;box-shadow:none!important;min-height:0!important;height:auto!important}' +
			'.velox-gseo-col:hover{background:#fafafa!important}' +
			'.velox-gseo-cl{font-size:13px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#1d2327}' +
			'.velox-gseo-ch{font-size:12.5px;color:#a7aaad;font-weight:400;text-align:right}' +
			'.velox-gseo-cbody{padding:0 16px 18px}' +
			/* Search preview card. */
			'.velox-gseo-preview{border:1px solid #e6e7e9;border-radius:10px;padding:14px;background:#fff;overflow:hidden}' +
			'.velox-gseo-preview.is-mobile{max-width:340px}' +
			'.velox-gseo-site{display:flex;align-items:flex-start;gap:10px;margin:0 0 8px}' +
			'.velox-gseo-fav{width:22px;height:22px;border-radius:50%;background:#e8eaed;flex:none}' +
			'.velox-gseo-host{font-size:13px;color:#202124;line-height:1.35;overflow-wrap:anywhere}' +
			'.velox-gseo-crumb{font-size:12.5px;color:#5f6368;line-height:1.35;overflow-wrap:anywhere}' +
			'.velox-gseo-title{color:#1a0dab;font-size:18px;line-height:1.3;font-weight:400;overflow-wrap:anywhere}' +
			'.velox-gseo-preview.is-mobile .velox-gseo-title{font-size:17px}' +
			'.velox-gseo-desc{color:#4d5156;font-size:13.5px;line-height:1.55;margin-top:6px;overflow-wrap:anywhere}' +
			'.velox-gseo-desc.is-empty{color:#9aa0a6;font-style:italic}' +
			/* Field block: label left, live counter right, meter under the input. */
			'.velox-gseo-field{margin:0 0 20px}' +
			'.velox-gseo-field:last-child{margin-bottom:0}' +
			'.velox-gseo-fh{display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin:0 0 8px}' +
			'.velox-gseo-fl{font-size:14px;font-weight:500;color:#1d2327}' +
			'.velox-gseo-fc{font-size:12.5px;color:#a7aaad;flex:none;font-variant-numeric:tabular-nums}' +
			'.velox-gseo-fc.is-over{color:#c8362f;font-weight:600}' +
			'.velox-gseo-hint{margin:8px 0 0;font-size:12.5px;color:#787c82;line-height:1.5}' +
			'.velox-gseo-bar{height:3px;border-radius:99px;background:#e8e8ea;overflow:hidden;margin:10px 0 0}' +
			'.velox-gseo-bar span{display:block;height:100%;border-radius:99px;transition:width .15s}' +
			/* WordPress styles every input and textarea in the admin (border,
			   padding, min-height:30px), and that outranks plugin CSS, so the
			   controls need a scoped hard reset or they render as boxes in boxes. */
			'.velox-gseo-input,.velox-gseo-area{display:block!important;box-sizing:border-box!important;width:100%!important;max-width:none!important;margin:0!important;padding:11px 13px!important;border:1px solid #dcdcde!important;border-radius:7px!important;background:#fff!important;color:#1d2327!important;font-family:inherit!important;font-size:13.5px!important;line-height:1.45!important;min-height:0!important;height:auto!important;box-shadow:none!important;outline:0!important}' +
			'.velox-gseo-area{min-height:104px!important;resize:vertical!important}' +
			'.velox-gseo-input:focus,.velox-gseo-area:focus{border-color:#2ab7f1!important;box-shadow:0 0 0 1px #2ab7f1!important}' +
			'.velox-gseo-input::placeholder,.velox-gseo-area::placeholder{color:#a7aaad!important;opacity:1}' +
			/* Labelled control rows. */
			'.velox-gseo-ctl{margin:0 0 16px}' +
			'.velox-gseo-ctl:last-child{margin-bottom:0}' +
			'.velox-gseo-ctl>span{display:block;font-size:13.5px;color:#3c434a;margin:0 0 8px}' +
			'.velox-gseo-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:18px 0 0}' +
			'.velox-gseo-row>span{font-size:13.5px;color:#3c434a}' +
			'.velox-gseo-out{margin:14px 0 0;color:#646970;font-size:12.5px;line-height:1.55}' +
			'.velox-gseo-out b{color:#1d2327;font-weight:600}' +
			/* Toggle switch. */
			'.velox-gseo-sw{position:relative;width:44px;flex:none;border:0!important;border-radius:999px!important;background:#c3c4c7!important;padding:0!important;margin:0!important;cursor:pointer;box-shadow:none!important;min-height:0!important;height:24px!important;transition:background .15s}' +
			'.velox-gseo-sw.is-on{background:#2ab7f1!important}' +
			'.velox-gseo-sw i{position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;transition:transform .15s;display:block}' +
			'.velox-gseo-sw.is-on i{transform:translateX(20px)}' +
			/* Drag handle on the left edge of the sidebar. WordPress pins the
			   editor sidebar at 280px with no way to resize it, so we add one. */
			'.velox-seo-resizer{position:absolute;top:0;left:-3px;width:7px;height:100%;cursor:col-resize;z-index:120;touch-action:none}' +
			'.velox-seo-resizer::before{content:"";position:absolute;top:0;left:2px;width:3px;height:100%;background:transparent;transition:background .12s}' +
			'.velox-seo-resizer:hover::before{background:#2ab7f1}' +
			'body.velox-seo-resizing{cursor:col-resize;user-select:none}' +
			'body.velox-seo-resizing .velox-seo-resizer::before{background:#2ab7f1}' +
			'body.velox-seo-resizing iframe{pointer-events:none}' +
			/* The sidebar width has to be driven from a stylesheet, not inline
			   styles. WordPress rewrites its own inline width on
			   .interface-complementary-area__fill after we set ours, so an inline
			   value always loses the last write. A rule carrying !important beats
			   an inline style that has none, so the drag only updates a custom
			   property and CSS does the rest. Scoped to .velox-seo-open, which is
			   only on the body while our panel is mounted. */
			'body.velox-seo-open .interface-interface-skeleton__sidebar,' +
			'body.velox-seo-open .editor-interface-skeleton__sidebar{' +
			'width:var(--velox-seo-w,280px)!important;flex-basis:var(--velox-seo-w,280px)!important;max-width:none!important}' +
			'body.velox-seo-open .interface-complementary-area__fill,' +
			'body.velox-seo-open .interface-complementary-area,' +
			'body.velox-seo-open .editor-sidebar,' +
			'body.velox-seo-open .velox-seo-stretch{' +
			'width:100%!important;max-width:none!important;min-width:0!important;flex-basis:auto!important}' +
			'body.velox-seo-open .interface-complementary-area .components-panel{width:100%!important;max-width:none!important}';
		document.head.appendChild( s );
	} )();

	function VeloxSeoPanel() {
		// Every hook runs before the early return below, or React sees a
		// different number of hooks between renders.
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
		// Pages built with a builder have no blocks, so those checks are skipped
		// rather than failed.
		var blocks = useSelect( function ( s ) {
			return s( 'core/block-editor' ) ? s( 'core/block-editor' ).getBlocks() : [];
		}, [] );
		var dispatch = useDispatch( 'core/editor' );

		var deviceState = useState( 'desktop' );
		var socialState = useState( false );
		var advState    = useState( false );
		var device    = deviceState[ 0 ];
		var setDevice = deviceState[ 1 ];

		if ( DATA.postTypes.indexOf( postType ) === -1 ) {
			return null;
		}

		function setMeta( k, v ) {
			var patch = {};
			patch[ k ] = v;
			dispatch.editPost( { meta: Object.assign( {}, meta, patch ) } );
		}

		var seoTitle  = meta._velox_seo_title || '';
		var seoDesc   = meta._velox_seo_desc || '';
		var noindex   = meta._velox_seo_noindex === true || meta._velox_seo_noindex === '1';
		var nofollow  = meta._velox_seo_nofollow === true || meta._velox_seo_nofollow === '1';
		var exclude   = meta.sitemap_exclude === true || meta.sitemap_exclude === '1';
		var canonical = meta._velox_seo_canonical || '';
		var focusKw   = meta._velox_seo_focus_kw || '';
		var ogTitle   = meta._velox_seo_og_title || '';
		var ogDesc    = meta._velox_seo_og_desc || '';
		var ogImage   = meta._velox_seo_og_image || '';

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
		var kwLower  = focusKw.trim().toLowerCase();

		// Status pills: the checklist compressed to one scannable row.
		var pills = [];
		pills.push( noindex ? { s: 'r', t: vxT( 'Noindex' ) } : { s: 'g', t: vxT( 'Indexed' ) } );
		pills.push( exclude ? { s: 'a', t: vxT( 'Not in sitemap' ) } : { s: 'g', t: vxT( 'In sitemap' ) } );
		pills.push( ! seoTitle
			? { s: 'r', t: vxT( 'No title' ) }
			: ( seoTitle.length > 60 ? { s: 'a', t: vxT( 'Title too long' ) } : { s: 'g', t: vxT( 'Title set' ) } ) );
		pills.push( ! seoDesc
			? { s: 'r', t: vxT( 'No description' ) }
			: ( seoDesc.length > 160 ? { s: 'a', t: vxT( 'Description too long' ) } : { s: 'g', t: vxT( 'Description set' ) } ) );
		pills.push( ! kwLower
			? { s: 'a', t: vxT( 'No focus keyword' ) }
			: ( effTitle.toLowerCase().indexOf( kwLower ) !== -1
				? { s: 'g', t: vxT( 'Keyword in title' ) }
				: { s: 'a', t: vxT( 'Keyword not in title' ) } ) );
		if ( hasBlocks ) {
			pills.push( hasH1 ? { s: 'g', t: vxT( 'Has H1' ) } : { s: 'a', t: vxT( 'No H1' ) } );
			if ( imgs.length ) {
				pills.push( imgsNoAlt
					? { s: 'a', t: imgsNoAlt + ' image' + ( 1 === imgsNoAlt ? '' : 's' ) + ' without alt' }
					: { s: 'g', t: vxT( 'All images have alt' ) } );
			}
		}

		// ── building blocks ──────────────────────────────────────────────────
		function seg( options, value, onPick, wide ) {
			return el( 'div', { className: 'velox-gseo-seg2 ' + ( wide ? 'velox-gseo-seg2--wide' : 'velox-gseo-seg2--tight' ) },
				options.map( function ( o ) {
					return el( 'button', {
						key: o.v,
						type: 'button',
						className: o.v === value ? 'is-on' : '',
						onClick: function () { onPick( o.v ); }
					}, o.t );
				} )
			);
		}

		function sw( on, onChange ) {
			return el( 'button', {
				type: 'button',
				className: 'velox-gseo-sw' + ( on ? ' is-on' : '' ),
				'aria-pressed': on ? 'true' : 'false',
				onClick: function () { onChange( ! on ); }
			}, el( 'i', {} ) );
		}

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

		// Children are spread rather than passed as an array, so React does not
		// ask for keys on static content.
		function section( label ) {
			var kids = Array.prototype.slice.call( arguments, 1 );
			return el.apply( null, [ 'div', { className: 'velox-gseo-sec' },
				el( 'div', { className: 'velox-gseo-sl' }, label ) ].concat( kids ) );
		}

		function collapsible( label, hint, state, children ) {
			var open = state[ 0 ];
			return el( Fragment, {},
				el( 'button', {
					type: 'button',
					className: 'velox-gseo-col',
					'aria-expanded': open ? 'true' : 'false',
					onClick: function () { state[ 1 ]( ! open ); }
				},
					el( 'span', { className: 'velox-gseo-cl' }, label ),
					el( 'span', { className: 'velox-gseo-ch' }, open ? '' : hint )
				),
				open ? el( 'div', { className: 'velox-gseo-cbody' }, children ) : null
			);
		}

		function text( value, placeholder, key ) {
			return el( 'input', {
				type: 'text',
				className: 'velox-gseo-input',
				value: value,
				placeholder: placeholder || '',
				onChange: function ( e ) { setMeta( key, e.target.value ); }
			} );
		}

		// ── search preview ───────────────────────────────────────────────────
		// Google shows the host and a breadcrumb, not the raw URL.
		var host = '', crumb = '';
		try {
			var u = new URL( link || window.location.href );
			host  = u.hostname;
			crumb = u.pathname.split( '/' ).filter( Boolean ).join( ' \u203a ' );
		} catch ( e ) {
			host = link || '';
		}

		var isMobile  = 'mobile' === device;
		var descLimit = isMobile ? 120 : 160;
		var shownDesc = seoDesc.length > descLimit
			? seoDesc.slice( 0, descLimit ).replace( /\s+\S*$/, '' ) + ' \u2026'
			: seoDesc;

		var preview = el( 'div', { className: 'velox-gseo-preview' + ( isMobile ? ' is-mobile' : '' ) },
			el( 'div', { className: 'velox-gseo-site' },
				el( 'span', { className: 'velox-gseo-fav' } ),
				el( 'span', {},
					el( 'div', { className: 'velox-gseo-host' }, host ),
					crumb ? el( 'div', { className: 'velox-gseo-crumb' }, '\u203a ' + crumb ) : null
				)
			),
			el( 'div', { className: 'velox-gseo-title' }, effTitle || vxT( 'Page title' ) ),
			el( 'div', { className: 'velox-gseo-desc' + ( seoDesc ? '' : ' is-empty' ) },
				shownDesc || 'No description yet \u2014 Google will pick a sentence from the page.' )
		);

		// ── panel ────────────────────────────────────────────────────────────
		var body = el( 'div', { className: 'velox-gseo' },
			el( 'div', { className: 'velox-gseo-top' },
				seg( [ { v: 'desktop', t: vxT( 'Desktop' ) }, { v: 'mobile', t: vxT( 'Mobile' ) } ], device, setDevice, false )
			),
			el( 'div', { className: 'velox-gseo-pills' },
				pills.map( function ( p, i ) {
					return el( 'span', { key: 'p' + i, className: 'velox-gseo-pill ' + p.s }, p.t );
				} )
			),
			section( vxT( 'Preview' ), preview ),
			section( vxT( 'Search appearance' ),
				field( vxT( 'Title' ),
					text( seoTitle, postTitle ? 'Using the page title: \u201c' + postTitle + '\u201d' : vxT( 'Using the page title' ), '_velox_seo_title' ),
					null, seoTitle.length, 60 ),
				field( vxT( 'Description' ),
					el( 'textarea', {
						className: 'velox-gseo-area',
						value: seoDesc,
						placeholder: 'Write what should show under the title\u2026',
						onChange: function ( e ) { setMeta( '_velox_seo_desc', e.target.value ); }
					} ),
					null, seoDesc.length, 160 )
			),
			section( vxT( 'Search engines' ),
				el( 'div', { className: 'velox-gseo-ctl' },
					el( 'span', {}, vxT( 'Indexing' ) ),
					seg( [ { v: 'index', t: vxT( 'Index' ) }, { v: 'noindex', t: vxT( 'Noindex' ) } ],
						noindex ? 'noindex' : 'index',
						function ( v ) { setMeta( '_velox_seo_noindex', 'noindex' === v ); }, true )
				),
				el( 'div', { className: 'velox-gseo-ctl' },
					el( 'span', {}, vxT( 'Links' ) ),
					seg( [ { v: 'follow', t: vxT( 'Follow' ) }, { v: 'nofollow', t: vxT( 'Nofollow' ) } ],
						nofollow ? 'nofollow' : 'follow',
						function ( v ) { setMeta( '_velox_seo_nofollow', 'nofollow' === v ); }, true )
				),
				el( 'p', { className: 'velox-gseo-out' },
					vxT( 'Crawlers are told ' ),
					el( 'b', {}, ( noindex ? 'noindex' : 'index' ) + ', ' + ( nofollow ? 'nofollow' : 'follow' ) ),
					noindex ? ' \u2014 this page will not appear in search.' : ' \u2014 this page can appear in search.'
				)
			),
			collapsible( vxT( 'Social preview' ),
				( ogTitle || ogDesc || ogImage ) ? vxT( 'Customised' ) : vxT( 'Using page defaults' ),
				socialState,
				el( Fragment, {},
					field( vxT( 'Social title' ), text( ogTitle, vxT( 'Falls back to the search title' ), '_velox_seo_og_title' ),
						vxT( 'Shown when shared on Facebook, LinkedIn or X.' ) ),
					field( vxT( 'Social description' ),
						el( 'textarea', {
							className: 'velox-gseo-area',
							value: ogDesc,
							placeholder: vxT( 'Falls back to the meta description' ),
							onChange: function ( e ) { setMeta( '_velox_seo_og_desc', e.target.value ); }
						} ) ),
					field( vxT( 'Social image URL' ), text( ogImage, vxT( 'Defaults to the featured image' ), '_velox_seo_og_image' ),
						'Recommended size 1200\u00d7630.' )
				)
			),
			collapsible( vxT( 'Advanced' ), vxT( 'Canonical, focus keyword' ), advState,
				el( Fragment, {},
					field( vxT( 'Focus keyword' ), text( focusKw, '', '_velox_seo_focus_kw' ),
						vxT( 'The phrase this page should rank for.' ) ),
					field( vxT( 'Canonical URL' ), text( canonical, '', '_velox_seo_canonical' ),
						'Leave empty to use this page\u2019s own URL.' )
				)
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
	 * WordPress pins the editor sidebar at 280px and offers no way to change it,
	 * so we add a drag handle on its left edge while the Velox SEO panel is
	 * mounted. The width itself is applied by the stylesheet above through the
	 * --velox-seo-w custom property: WordPress rewrites its own inline width on
	 * the sidebar wrappers, so anything we set inline gets clobbered on the next
	 * render. Everything is scoped to the .velox-seo-open body class, so the Page
	 * and Block tabs are untouched.
	 */
	( function veloxSeoResizer() {
		var MIN = 280;
		var KEY = 'veloxSeoSidebarWidth';
		var shell = null;
		var handle = null;
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

		function apply( w ) {
			document.documentElement.style.setProperty( '--velox-seo-w', w + 'px' );
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

		// Tag every wrapper between the panel and the shell, so the stylesheet
		// covers wrappers whose class names we do not know about.
		function mark( panel ) {
			var n = panel.parentElement;
			while ( n && n !== shell && n !== document.body ) {
				if ( ! n.classList.contains( 'velox-seo-stretch' ) ) {
					n.classList.add( 'velox-seo-stretch' );
				}
				n = n.parentElement;
			}
		}

		function reset() {
			document.body.classList.remove( 'velox-seo-open' );
			document.documentElement.style.removeProperty( '--velox-seo-w' );
			Array.prototype.forEach.call(
				document.querySelectorAll( '.velox-seo-stretch' ),
				function ( n ) { n.classList.remove( 'velox-seo-stretch' ); }
			);
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
				try { window.localStorage.setItem( KEY, String( Math.round( live ) ) ); } catch ( e2 ) {}
			}
			handle.addEventListener( 'pointermove', move );
			handle.addEventListener( 'pointerup', stop );
			handle.addEventListener( 'pointercancel', stop );
		}

		function sync() {
			queued = false;
			var panel = document.querySelector( '.velox-gseo' );
			if ( ! panel ) {
				if ( shell || document.body.classList.contains( 'velox-seo-open' ) ) { reset(); }
				return;
			}

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
				handle.setAttribute( 'title', vxT( 'Drag to resize — double-click to reset' ) );
				handle.addEventListener( 'pointerdown', startDrag );
				handle.addEventListener( 'dblclick', function () {
					try { window.localStorage.removeItem( KEY ); } catch ( e ) {}
					document.documentElement.style.removeProperty( '--velox-seo-w' );
				} );
				shell.appendChild( handle );

				var w = stored();
				if ( w ) { apply( w ); }
			}

			// Cheap enough to redo on every batch: a re-render can drop the class
			// from wrappers it recreates.
			document.body.classList.add( 'velox-seo-open' );
			mark( panel );
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

	/* ---- Make the Velox SEO sidebar horizontally resizable ----
	   Gutenberg's plugin sidebar is a fixed ~280px. We add a drag handle on its
	   left edge and override the width, persisting the chosen width per browser.
	   This overrides core layout, so it's defensive: it only acts when the Velox
	   panel is the active sidebar, and cleans up when it closes. */
	( function () {
		var KEY = 'veloxSeoSidebarWidth';
		var MIN = 280, MAX = 720;
		var handle = null;

		function stored() {
			var v = parseInt( window.localStorage.getItem( KEY ), 10 );
			return ( v && v >= MIN && v <= MAX ) ? v : 0;
		}

		// The region that holds the active plugin sidebar.
		function region() {
			// Works across WP versions: the complementary area on the right.
			return document.querySelector( '.interface-interface-skeleton__sidebar[aria-label], .interface-complementary-area' )
				? document.querySelector( '.interface-interface-skeleton__sidebar' )
				: null;
		}

		function veloxOpen() {
			// The Velox panel renders a .velox-gseo inside the sidebar when active.
			return !! document.querySelector( '.interface-interface-skeleton__sidebar .velox-gseo' );
		}

		function apply( px ) {
			var sb = region();
			if ( ! sb ) { return; }
			sb.style.width = px + 'px';
			sb.style.flexBasis = px + 'px';
			sb.style.maxWidth = 'none';
		}
		function reset() {
			var sb = region();
			if ( sb ) { sb.style.width = ''; sb.style.flexBasis = ''; sb.style.maxWidth = ''; }
		}

		function ensureHandle() {
			var sb = region();
			if ( ! sb || ! veloxOpen() ) {
				if ( handle && handle.parentNode ) { handle.parentNode.removeChild( handle ); handle = null; reset(); }
				return;
			}
			if ( stored() ) { apply( stored() ); }
			if ( handle && handle.isConnected ) { return; }

			handle = document.createElement( 'div' );
			handle.className = 'velox-sb-resize';
			handle.title = 'Drag to resize';
			handle.style.cssText = 'position:absolute;left:0;top:0;width:6px;height:100%;cursor:ew-resize;z-index:100;background:transparent;';
			handle.addEventListener( 'mouseenter', function () { handle.style.background = 'rgba(42,183,241,.35)'; } );
			handle.addEventListener( 'mouseleave', function () { if ( ! dragging ) { handle.style.background = 'transparent'; } } );

			// The sidebar needs position for the absolute handle.
			if ( getComputedStyle( sb ).position === 'static' ) { sb.style.position = 'relative'; }
			sb.appendChild( handle );

			var dragging = false, startX = 0, startW = 0;
			handle.addEventListener( 'mousedown', function ( e ) {
				dragging = true;
				startX = e.clientX;
				startW = sb.getBoundingClientRect().width;
				document.body.style.userSelect = 'none';
				document.body.style.cursor = 'ew-resize';
				e.preventDefault();
			} );
			document.addEventListener( 'mousemove', function ( e ) {
				if ( ! dragging ) { return; }
				// dragging left edge: moving left grows the (right-docked) sidebar
				var w = Math.round( startW + ( startX - e.clientX ) );
				w = Math.max( MIN, Math.min( MAX, w ) );
				apply( w );
			} );
			document.addEventListener( 'mouseup', function () {
				if ( ! dragging ) { return; }
				dragging = false;
				document.body.style.userSelect = '';
				document.body.style.cursor = '';
				handle.style.background = 'transparent';
				var sb2 = region();
				if ( sb2 ) { window.localStorage.setItem( KEY, String( Math.round( sb2.getBoundingClientRect().width ) ) ); }
			} );
		}

		function tick() { try { ensureHandle(); } catch ( e ) {} }
		if ( 'loading' === document.readyState ) {
			document.addEventListener( 'DOMContentLoaded', function () {
				new window.MutationObserver( tick ).observe( document.body, { childList: true, subtree: true } );
				tick();
			} );
		} else {
			new window.MutationObserver( tick ).observe( document.body, { childList: true, subtree: true } );
			tick();
		}
	}() );
