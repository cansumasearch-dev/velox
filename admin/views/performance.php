<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s = Velox_Settings::all();

/**
 * Field metadata. type: switch | number | text | textarea | select.
 * note: small amber hint shown under the row (overlap / caution).
 */
$fields = array(
	// Cache
	'cache_enable'          => array( 'switch', 'Enable page cache', 'Serve a static HTML copy of each page so visitors skip PHP and the database entirely. Velox\'s own cache — no WP Fastest Cache or WP Rocket needed.' ),
	'cache_ttl'             => array( 'number', 'Cache lifetime (seconds)', 'How long a cached page stays fresh before it\'s rebuilt. 36000 = 10 hours. 0 = until purged.' ),
	'cache_logged_in'       => array( 'switch', 'Cache for logged-in users', 'Off by default — logged-in visitors always see live pages. Only enable if your site looks identical when logged in.' ),
	'cache_mobile_separate' => array( 'switch', 'Separate mobile cache', 'Store a separate cached copy for mobile devices. Enable only if your theme serves different markup to phones.' ),
	'cache_gzip'            => array( 'switch', 'Pre-compress pages', 'Stores gzip (and Brotli where available) copies so the server sends compressed HTML without compressing on every request.' ),
	'cache_exclude_urls'    => array( 'textarea', 'Never cache these URLs', 'One path per line. Use * as a wildcard, e.g. /cart, /checkout, /my-account/*.' ),
	'cache_exclude_cookies' => array( 'textarea', 'Never cache with these cookies', 'One cookie name (or fragment) per line. Requests carrying a matching cookie always bypass the cache.' ),

	// General
	'perf_disable_emojis'        => array( 'switch', 'Disable emojis', 'Removes the emoji detection script and styles WordPress loads on every page.' ),
	'perf_minify_html'           => array( 'switch', 'Minify HTML', 'Strips comments and collapses whitespace in the final page HTML. Applies to cached pages and fails safely — script, style, pre and textarea blocks are left untouched.' ),
	'perf_disable_embeds'        => array( 'switch', 'Disable oEmbed', 'Stops WordPress loading the wp-embed script and embed discovery links.' ),
	'perf_remove_query_strings'  => array( 'switch', 'Remove query strings', 'Strips ?ver= from static CSS/JS so proxies can cache them better.' ),
	'perf_disable_xmlrpc'        => array( 'switch', 'Disable XML-RPC', 'Closes a common attack surface and removes the pingback header.' ),
	'perf_disable_self_pingbacks'=> array( 'switch', 'Disable self-pingbacks', 'Stops your site pinging itself when you link between your own posts.' ),
	'perf_clean_head'            => array( 'switch', 'Clean wp_head', 'Removes RSD, WLW, shortlink, generator and extra feed links.' ),
	'perf_disable_dashicons'     => array( 'switch', 'Disable Dashicons (front end)', 'Only for logged-out visitors — the admin bar keeps them.' ),
	'perf_remove_jquery_migrate' => array( 'switch', 'Remove jQuery Migrate', 'Drops the legacy migrate shim. Test forms/sliders after enabling.' ),
	'perf_disable_comments'      => array( 'switch', 'Disable comments', 'Closes comments site-wide and hides the comments UI. Only if you never use them.' ),
	'perf_disable_rss'           => array( 'switch', 'Disable RSS feeds', 'Turns off all RSS/Atom feeds. Skip this if anything consumes your feed.' ),
	'perf_disable_app_passwords' => array( 'switch', 'Disable Application Passwords', 'Minor hardening; disable if you don\'t use external app logins.' ),

	// CSS
	'perf_disable_block_css'     => array( 'switch', 'Disable Gutenberg block CSS', 'Removes wp-block-library on the front end. Safe on Oxygen sites that don\'t render blocks.' ),
	'perf_disable_global_styles' => array( 'switch', 'Disable global styles', 'Removes global-styles and classic-theme-styles inline CSS from the head.' ),
	'perf_disable_woo_css'       => array( 'switch', 'WooCommerce CSS off non-shop pages', 'Only loads Woo styles on shop/cart/checkout/account pages.' ),
	'perf_optimize_css_delivery' => array( 'switch', 'Optimize CSS delivery (non-render-blocking)', 'Loads stylesheets async so CSS stops blocking first paint. Pair with critical CSS below to avoid a flash of unstyled content.' ),
	'perf_critical_css'          => array( 'textarea', 'Critical CSS', 'Above-the-fold CSS to inline in the head. Generate it from a free online critical-CSS tool and paste it here.' ),
	'perf_css_async_exclude'     => array( 'textarea', 'Keep render-blocking', 'Stylesheet handles/URL fragments that must load normally (one per line). Oxygen + admin-bar are pre-filled.' ),
	'perf_remove_unused_css'     => array( 'switch', 'Remove unused CSS', 'Strips CSS rules whose selectors never appear in the page. Choose the engine below.' ),
	'perf_rucss_engine'          => array( 'select', 'Used-CSS engine', 'Auto = learns the real classes from your visitors\' browsers (no setup, JS-aware, self-improving). Cloudflare = renders each page via Browser Run (accurate on day one, needs a token). Local = reads server HTML (instant, but blind to JS-added classes).', array( 'auto' => 'Auto-learn from visitors (recommended)', 'cloudflare' => 'Cloudflare Browser Run', 'local' => 'Local (best-effort)' ) ),
	'cf_account_id'              => array( 'text', 'Cloudflare account ID', 'Found on your Cloudflare dashboard overview. Only used by the Browser Run engine.' ),
	'cf_api_token'               => array( 'text', 'Cloudflare API token', 'Create a token with the "Browser Rendering" permission. Stored on your site only.' ),
	'perf_rucss_urls'            => array( 'textarea', 'Pages to scan', 'One path per line (e.g. /, /about/, /kontakt/). Add one of each page template — that\'s all the engine needs.' ),
	'perf_rucss_safelist'        => array( 'textarea', 'Used-CSS safelist', 'Selectors/classes to NEVER strip, one per line. With the local engine, put anything JS adds here.' ),
	'perf_rucss_exclude'         => array( 'textarea', 'Used-CSS stylesheet exclusions', 'Stylesheet URL fragments to leave completely untouched, one per line.' ),

	// JS
	'perf_defer_scripts'         => array( 'switch', 'Defer JavaScript', 'Adds defer to scripts so they don\'t block rendering. Exclusions below.' ),
	'perf_defer_exclude'         => array( 'textarea', 'Defer exclusions', 'One handle or filename fragment per line. jQuery/Oxygen/Fluent Form are pre-filled.' ),
	'perf_delay_js'              => array( 'switch', 'Delay JavaScript until interaction', 'The big win: scripts run only after the first scroll/click/keypress (or after the timeout below).' ),
	'perf_delay_js_exclude'      => array( 'textarea', 'Delay exclusions', 'Anything that must run immediately (builder, forms) goes here, one per line.' ),
	'perf_delay_js_timeout'      => array( 'number', 'Delay fallback (seconds)', 'Run delayed scripts after this many seconds even with no interaction. 0 = wait for interaction only.' ),
	'perf_disable_woo_fragments' => array( 'switch', 'WooCommerce cart fragments off non-cart pages', 'Removes the wc-cart-fragments AJAX request away from cart/checkout.' ),

	// Images
	'perf_add_image_dimensions'  => array( 'switch', 'Add missing width/height', 'Gives images explicit dimensions to cut layout shift (CLS).' ),
	'perf_fetchpriority_lcp'     => array( 'switch', 'Prioritise the hero image (LCP)', 'Adds fetchpriority="high" to the featured image and stops it lazy-loading — the single biggest LCP win.' ),
	'perf_lazyload_iframes'      => array( 'switch', 'Lazy-load iframes', 'Adds loading="lazy" to iframes/embeds in content.' ),
	'perf_lazy_skip_count'       => array( 'number', 'Eager images above the fold', 'Keep the first N images out of lazy-loading so the hero/LCP loads instantly. 2 is a safe default; raise it for image-heavy headers.' ),
	'perf_youtube_facade'        => array( 'switch', 'YouTube facade', 'Swaps YouTube embeds for a click-to-load thumbnail — saves ~1MB+ on first load.' ),
	'perf_preload_lcp'           => array( 'text', 'Preload LCP image', 'Full URL of your hero image. Preloads it with high priority for a faster LCP.' ),
	'perf_content_visibility'    => array( 'switch', 'Lazy-render offscreen sections', 'Uses content-visibility:auto to skip rendering offscreen blocks. Can cause layout jumps — test scrolling.' ),
	'perf_content_visibility_selector' => array( 'textarea', 'Lazy-render selectors', 'CSS selector(s) to apply lazy-render to, one per line (e.g. .footer, .ct-section).' ),

	// Fonts
	'perf_fonts_preconnect'      => array( 'switch', 'Preconnect Google Fonts', 'Adds preconnect to fonts.googleapis.com and fonts.gstatic.com.' ),
	'perf_fonts_display_swap'    => array( 'switch', 'Force font-display: swap', 'Adds display=swap to Google Fonts so text shows while fonts load.' ),
	'perf_local_fonts'           => array( 'switch', 'Host Google Fonts locally', 'Downloads your Google Fonts and serves them from your own server — kills the third-party request and speeds up first paint. Click "Scan & download" below after enabling.' ),
	'perf_preload_fonts'         => array( 'textarea', 'Preload fonts', 'One font URL per line (.woff2 recommended). Preload only the 1–2 above-the-fold fonts.' ),
	'perf_system_fonts'          => array( 'switch', 'Use system fonts', 'Skips web fonts and uses the visitor\'s own system stack — zero font requests. This overrides theme fonts, so check your design after turning it on.' ),

	// CDN
	'perf_cdn_enable'            => array( 'switch', 'Rewrite assets to a CDN', 'Serve CSS, JS, images and fonts from your CDN host instead of your domain.' ),
	'perf_cdn_url'               => array( 'text', 'CDN URL', 'Your CDN base, e.g. https://cdn.yoursite.com. Matching asset URLs get rewritten to this host.' ),
	'perf_cdn_exclude'           => array( 'textarea', 'CDN exclusions', 'URL fragments to leave on your own domain, one per line.' ),

	// Preload / Network
	'perf_dns_prefetch'          => array( 'textarea', 'DNS prefetch', 'One origin per line. Resolves DNS for third-party domains early.' ),
	'perf_preconnect'            => array( 'textarea', 'Preconnect', 'One origin per line. Stronger than prefetch — opens the full connection early.' ),
	'perf_speculative_loading'   => array( 'select', 'Speculative loading', 'Prerenders the next page on hover/focus using the browser Speculation Rules API.', array( 'off' => 'Off', 'conservative' => 'Conservative (on hover)', 'moderate' => 'Moderate (eager)' ) ),
	'perf_preload_assets'        => array( 'textarea', 'Preload critical assets', 'One URL per line (css/js/image). Use sparingly for above-the-fold resources.' ),

	// Background
	'perf_heartbeat'             => array( 'select', 'Heartbeat API', 'Throttles WordPress\'s background pings. Slow = 60s, Off = disabled outside the editor.', array( 'default' => 'Default', 'slow' => 'Slow (60s)', 'off' => 'Off (except editor)' ) ),
	'perf_revisions_keep'        => array( 'number', 'Post revisions to keep', '0 = unlimited. Capping at 5–10 keeps the database lean.' ),
	'perf_autosave_interval'     => array( 'number', 'Autosave interval (seconds)', 'WordPress default is 60. Higher = fewer background autosaves.' ),
);

$sections   = Velox_Settings::perf_sections();
$risky_keys = Velox_Settings::perf_risky_keys();

function velox_perf_field( $key, $meta, $s, $is_risky = false ) {
	list( $type, $label, $desc ) = $meta;
	$opts      = $meta[3] ?? array();
	$risky_att = $is_risky ? ' data-risky="1"' : '';
	$badge     = $is_risky ? ' <span class="velox-risky-tag">Risky</span>' : '';
	if ( 'switch' === $type ) {
		?>
		<div class="velox-toggle-row"<?php echo $risky_att; ?>>
			<div class="velox-toggle-meta">
				<span class="velox-toggle-label"><?php echo esc_html( $label ); ?><?php echo $badge; ?></span>
				<span class="velox-toggle-desc"><?php echo esc_html( $desc ); ?></span>
			</div>
			<label class="velox-switch"><input type="checkbox" data-setting="<?php echo esc_attr( $key ); ?>" <?php checked( ! empty( $s[ $key ] ) ); ?>><span class="velox-switch-track"></span></label>
		</div>
		<?php
		return;
	}
	?>
	<div class="velox-field"<?php echo $risky_att; ?>>
		<span class="velox-field-label"><?php echo esc_html( $label ); ?><?php echo $badge; ?></span>
		<?php if ( 'number' === $type ) : ?>
			<input type="number" class="velox-input velox-input--sm" data-setting="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>">
		<?php elseif ( 'text' === $type ) : ?>
			<input type="text" class="velox-input" data-setting="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" placeholder="https://…">
		<?php elseif ( 'select' === $type ) : ?>
			<select class="velox-select" data-setting="<?php echo esc_attr( $key ); ?>">
				<?php foreach ( $opts as $val => $text ) : ?>
					<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s[ $key ], $val ); ?>><?php echo esc_html( $text ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php else : ?>
			<textarea class="velox-textarea" data-setting="<?php echo esc_attr( $key ); ?>" rows="3"><?php echo esc_textarea( $s[ $key ] ); ?></textarea>
		<?php endif; ?>
		<span class="velox-hint"><?php echo esc_html( $desc ); ?></span>
	</div>
	<?php
}
?>
<div class="velox-page-head velox-page-head--row">
	<div>
		<h1 class="velox-h2">Performance</h1>
		<p class="velox-sub">A complete performance toolkit — page cache, asset optimization, fonts, preloading and more. Velox works standalone, and plays nicely with Cloudflare and Oxygen. Flip one setting at a time and re-test PageSpeed.</p>
	</div>
	<label class="velox-risky-switch">
		<span class="velox-risky-switch-text">
			<span class="velox-risky-switch-label">Risky mode</span>
			<span class="velox-risky-switch-sub">Show settings that <em>might</em> break the site</span>
		</span>
		<span class="velox-switch"><input type="checkbox" id="velox-risky-toggle" data-setting="perf_risky_mode" <?php checked( ! empty( $s['perf_risky_mode'] ) ); ?>><span class="velox-switch-track"></span></span>
	</label>
</div>

<div class="velox-alert velox-alert--info">
	These are <strong>100% safe</strong> by default. Turn on <strong>Risky mode</strong> (top right) to reveal aggressive settings like Delay JavaScript — they give bigger wins but should be tested by clicking through forms, sliders and your builder afterwards.
</div>

<div class="velox-perf">
	<nav class="velox-perf-nav" id="velox-perf-nav">
		<?php $first = true; foreach ( $sections as $id => $sec ) : ?>
			<button type="button" class="velox-perf-navitem<?php echo $first ? ' is-active' : ''; ?>" data-section="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $sec['label'] ); ?></button>
		<?php $first = false; endforeach; ?>
	</nav>

	<div class="velox-perf-body">
		<?php $first = true; foreach ( $sections as $id => $sec ) : ?>
			<section class="velox-perf-panel<?php echo $first ? ' is-active' : ''; ?>" data-section="<?php echo esc_attr( $id ); ?>">
				<?php if ( 'general' === $id ) : ?>
					<div class="velox-panel velox-cache-panel">
						<h3 class="velox-panel-title">Clear cache</h3>
						<p class="velox-hint">Purge caches across your whole stack in one click. Velox talks to WP Fastest Cache, Oxygen and Cloudflare directly.</p>
						<div class="velox-cache-btns">
							<button class="velox-btn velox-btn--primary velox-cache-btn" data-which="all">Clear all caches</button>
							<button class="velox-btn velox-btn--ghost velox-cache-btn" data-which="minified">Minified CSS/JS</button>
							<button class="velox-btn velox-btn--ghost velox-cache-btn" data-which="oxygen">Regenerate Oxygen CSS</button>
							<button class="velox-btn velox-btn--ghost velox-cache-btn" data-which="cloudflare">Cloudflare</button>
						</div>
					</div>
				<?php endif; ?>
				<?php if ( 'cache' === $id ) :
					$cache_stats = class_exists( 'Velox_Cache' ) ? Velox_Cache::stats() : array( 'pages' => 0, 'bytes' => 0, 'dropin_active' => false );
					$cache_on    = ! empty( $s['cache_enable'] );
					$pill_cls    = $cache_on ? 'ok' : 'muted';
					$pill_txt    = $cache_on ? ( $cache_stats['dropin_active'] ? 'Active · early serve' : 'Active' ) : 'Off';
					?>
					<div class="velox-panel velox-cache-status">
						<div class="velox-cache-status-row">
							<div>
								<h3 class="velox-panel-title">Page cache status</h3>
								<p class="velox-hint" id="velox-cache-summary">
									<?php if ( $cache_on ) : ?>
										<?php echo (int) $cache_stats['pages']; ?> pages cached · <?php echo esc_html( size_format( $cache_stats['bytes'] ) ); ?> on disk
									<?php else : ?>
										Turn on the page cache below to make Velox a complete, standalone performance solution — no third-party cache plugin required.
									<?php endif; ?>
								</p>
							</div>
							<span class="velox-pill velox-pill--<?php echo esc_attr( $pill_cls ); ?>" id="velox-cache-pill"><?php echo esc_html( $pill_txt ); ?></span>
						</div>
						<div class="velox-cache-btns">
							<button class="velox-btn velox-btn--ghost" id="velox-cache-purge">Purge page cache</button>
							<button class="velox-btn velox-btn--ghost" id="velox-cache-preload">Preload now</button>
						</div>
						<div class="velox-alert velox-alert--warn velox-cache-note" id="velox-cache-note" hidden></div>
					</div>
				<?php endif; ?>
				<div class="velox-panel">
					<h3 class="velox-panel-title"><?php echo esc_html( $sec['label'] ); ?></h3>
					<?php foreach ( $sec['keys'] as $key ) : ?>
						<?php if ( isset( $fields[ $key ] ) ) { velox_perf_field( $key, $fields[ $key ], $s, in_array( $key, $risky_keys, true ) ); } ?>
					<?php endforeach; ?>
					<?php if ( 'css' === $id ) :
						$learn = Velox_CSS::learn_stats(); ?>
						<div class="velox-fonts-tool">
							<div class="velox-fonts-status" id="velox-rucss-status">
								<?php if ( 'auto' === $s['perf_rucss_engine'] && $learn['pages'] > 0 ) : ?>
									<span class="velox-fonts-ok">✓ Auto-learn: <?php echo (int) $learn['built']; ?> of <?php echo (int) $learn['pages']; ?> tracked page(s) optimized from real visitors</span>
								<?php else : ?>
									<span class="velox-hint">Auto-learn builds itself from real traffic — no setup needed. Cloudflare/Local can be scanned manually below.</span>
								<?php endif; ?>
							</div>
							<div class="velox-fonts-btns">
								<button class="velox-btn velox-btn--primary" id="velox-rucss-scan">Scan &amp; build (Cloudflare/Local)</button>
								<button class="velox-btn velox-btn--ghost" id="velox-clear-usedcss">Clear used-CSS cache</button>
								<button class="velox-btn velox-btn--ghost" id="velox-rucss-reset">Reset auto-learn</button>
							</div>
							<p class="velox-hint">Auto-learn needs no action — it optimizes each page after a few visits and keeps improving. Use Scan only for the Cloudflare or Local engines. Clear/Reset after a design change or if something looks off.</p>
						</div>
					<?php endif; ?>
					<?php if ( 'fonts' === $id ) :
						$font_status = Velox_Fonts::status(); ?>
						<div class="velox-fonts-tool">
							<div class="velox-fonts-status" id="velox-fonts-status">
								<?php if ( ! empty( $font_status['active'] ) ) : ?>
									<span class="velox-fonts-ok">✓ <?php echo (int) $font_status['files']; ?> font file(s) hosted locally<?php echo ! empty( $font_status['families'] ) ? ' — ' . esc_html( implode( ', ', $font_status['families'] ) ) : ''; ?></span>
								<?php else : ?>
									<span class="velox-hint">No fonts hosted locally yet. Enable the toggle, then scan.</span>
								<?php endif; ?>
							</div>
							<div class="velox-fonts-btns">
								<button class="velox-btn velox-btn--primary" id="velox-fonts-scan">Scan &amp; download fonts</button>
								<button class="velox-btn velox-btn--ghost" id="velox-fonts-clear">Remove local fonts</button>
							</div>
							<p class="velox-hint">Velox loads your front page, finds the Google Fonts it uses, downloads the woff2 files into your uploads folder and serves those instead. Re-scan after you change fonts.</p>
						</div>
					<?php endif; ?>
				</div>
			</section>
		<?php $first = false; endforeach; ?>
	</div>
</div>

<div class="velox-actions velox-actions--sticky">
	<button class="velox-btn velox-btn--primary" id="velox-perf-save">Save performance settings</button>
</div>
