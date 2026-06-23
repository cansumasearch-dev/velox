<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s         = Velox_Settings::all();
$robots    = Velox_Seo::robots_content();
$physical  = Velox_Seo::physical_robots_exists();
$sitemap   = Velox_Seo::sitemap_stats();
$robots_on = ! empty( $s['seo_robots_enable'] );
$smap_on   = ! empty( $s['seo_sitemap_enable'] );
?>
<div class="velox-page-head">
	<h1 class="velox-h2">SEO</h1>
	<p class="velox-sub">Edit your robots.txt, control how each page appears in Google, and keep an XML sitemap in sync — the essentials, without a second heavyweight SEO plugin.</p>
</div>

<!-- ============ One-click setup ============ -->
<div class="velox-panel velox-seo-oneclick">
	<div class="velox-seo-oneclick-row">
		<div>
			<h3 class="velox-panel-title">Recommended setup</h3>
			<p class="velox-hint">Applies the standard robots.txt, switches on the sitemap and generates it right now — everything wired in one click.</p>
		</div>
		<button class="velox-btn velox-btn--primary" id="velox-seo-apply">Apply recommended setup</button>
	</div>
</div>

<div class="velox-grid-2">
	<!-- ============ robots.txt ============ -->
	<div class="velox-panel">
		<div class="velox-cache-status-row">
			<h3 class="velox-panel-title">robots.txt</h3>
			<label class="velox-switch"><input type="checkbox" id="velox-seo-robots-enable" data-setting="seo_robots_enable" <?php checked( $robots_on ); ?>><span class="velox-switch-track"></span></label>
		</div>
		<p class="velox-hint">Served by WordPress at <code><?php echo esc_html( home_url( '/robots.txt' ) ); ?></code>. Edit freely — the Sitemap line should point at your sitemap.xml.</p>
		<?php if ( $physical ) : ?>
			<div class="velox-alert velox-alert--info">A physical <code>robots.txt</code> exists in your site root and is being served directly. The editor keeps it in sync on save. This is the most reliable setup behind Nginx or a CDN — use "Back to virtual" to remove it.</div>
		<?php endif; ?>
		<textarea class="velox-textarea velox-mono" id="velox-seo-robots" rows="8"><?php echo esc_textarea( $robots ); ?></textarea>
		<div class="velox-actions">
			<button class="velox-btn velox-btn--primary" id="velox-seo-robots-save">Save robots.txt</button>
			<button class="velox-btn velox-btn--ghost" id="velox-seo-robots-reset">Reset to recommended</button>
			<?php if ( $physical ) : ?>
				<button class="velox-btn velox-btn--ghost" id="velox-seo-robots-virtual">Back to virtual</button>
			<?php else : ?>
				<button class="velox-btn velox-btn--ghost" id="velox-seo-robots-physical">Write to physical file</button>
			<?php endif; ?>
		</div>
		<div class="velox-alert velox-alert--warn velox-seo-cf-note">
			<strong>Seeing AI "content signals" text instead of yours?</strong> That's <strong>Cloudflare</strong> serving its own robots.txt at the edge, which overrides this. Fix it in your Cloudflare dashboard: <em>your zone → AI Crawl Control / Bots → uncheck "Display Content Signals Policy" / managed robots.txt</em>. Writing a physical file here also helps, since Cloudflare only injects when your origin has no robots.txt.
	</div>

	<!-- ============ Sitemap ============ -->
	<div class="velox-panel">
		<div class="velox-cache-status-row">
			<h3 class="velox-panel-title">XML sitemap</h3>
			<label class="velox-switch"><input type="checkbox" id="velox-seo-sitemap-enable" data-setting="seo_sitemap_enable" <?php checked( $smap_on ); ?>><span class="velox-switch-track"></span></label>
		</div>
		<div class="velox-mini-stats">
			<div><span id="velox-seo-smap-count"><?php echo $sitemap['exists'] ? (int) $sitemap['urls'] : '—'; ?></span><small>URLs</small></div>
			<div><span><?php echo $sitemap['exists'] ? 'Live' : 'Not built'; ?></span><small>Status</small></div>
			<div><span style="font-size:14px;"><?php echo $sitemap['exists'] ? esc_html( $sitemap['modified'] ) : '—'; ?></span><small>Updated</small></div>
		</div>
		<p class="velox-hint">Includes your home page first, then published posts, pages and products (A–Z). Exclude any single page from its editor (the <strong>Velox SEO</strong> box → "Exclude from sitemap").</p>
		<div class="velox-actions">
			<button class="velox-btn velox-btn--primary" id="velox-seo-smap-gen">Regenerate sitemap</button>
			<a class="velox-btn velox-btn--ghost" href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" target="_blank" rel="noopener">View sitemap</a>
		</div>
	</div>
</div>

<!-- ============ Per-page meta ============ -->
<div class="velox-panel">
	<h3 class="velox-panel-title">Per-page title &amp; description</h3>
	<p class="velox-hint">Open any post, page or product and you'll find a <strong>Velox SEO</strong> box under the content with a live Google preview. Set a custom SEO title, meta description, mark a page <em>noindex</em>, or exclude it from the sitemap — all in one place.</p>
	<div class="velox-seo-fauxbox">
		<div class="velox-seo-preview">
			<div class="velox-seo-preview-url"><?php echo esc_html( home_url( '/your-page/' ) ); ?></div>
			<div class="velox-seo-preview-title">Your custom SEO title shows here</div>
			<div class="velox-seo-preview-desc">…and your meta description previews exactly how Google will render it, with live character counts.</div>
		</div>
	</div>
</div>
