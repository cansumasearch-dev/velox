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
			<button class="velox-btn velox-btn--ghost" id="velox-seo-robots-view" data-url="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>">View live robots.txt</button>
			<?php if ( $physical ) : ?>
				<button class="velox-btn velox-btn--ghost" id="velox-seo-robots-virtual">Back to virtual</button>
			<?php else : ?>
				<button class="velox-btn velox-btn--ghost" id="velox-seo-robots-physical">Write to physical file</button>
			<?php endif; ?>
		</div>
		<div id="velox-seo-robots-live" class="velox-seo-live" hidden>
			<div class="velox-seo-live-head"><span>Live at <code><?php echo esc_html( home_url( '/robots.txt' ) ); ?></code></span><span id="velox-seo-live-badge"></span></div>
			<pre id="velox-seo-live-out" class="velox-seo-live-out"></pre>
			<div id="velox-seo-live-cf" class="velox-alert velox-alert--warn" hidden><strong>That "content signals" block is coming from Cloudflare — not Velox.</strong> Velox is serving the clean robots.txt shown in the editor above. Cloudflare adds the signals block at the edge, so no WordPress plugin can remove it. To turn it off: open your Cloudflare dashboard → select this domain → <em>AI Crawl Control</em> (older accounts: <em>Bots</em>) → switch off <em>Content Signals Policy</em> / managed robots.txt, then come back and click <em>View live robots.txt</em> again.</div>
		</div>
		<div class="velox-alert velox-alert--warn velox-seo-cf-note">
			<strong>Seeing AI "content signals" text instead of yours?</strong> That's <strong>Cloudflare</strong> serving its own robots.txt at the edge, which overrides this. Fix it in your Cloudflare dashboard: <em>your zone → AI Crawl Control / Bots → uncheck "Display Content Signals Policy" / managed robots.txt</em>. Writing a physical file here also helps, since Cloudflare only injects when your origin has no robots.txt.
		</div>
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
		<p class="velox-hint">Home page first, then your chosen post types (A–Z). Exclude any single page from its editor (the <strong>Velox SEO</strong> box → "Exclude from sitemap").</p>

		<div class="velox-smap-editor">
			<div class="velox-smap-opts">
				<div class="velox-smap-optrow"><span class="velox-smap-optlabel">Home page</span><label class="velox-switch velox-switch--sm"><input type="checkbox" id="velox-smap-home" data-setting="seo_sitemap_home" <?php checked( ! empty( $s['seo_sitemap_home'] ) ); ?>><span class="velox-switch-track"></span></label></div>
				<div class="velox-smap-optrow"><span class="velox-smap-optlabel">Posts</span><label class="velox-switch velox-switch--sm"><input type="checkbox" id="velox-smap-posts" data-setting="seo_sitemap_posts" <?php checked( ! empty( $s['seo_sitemap_posts'] ) ); ?>><span class="velox-switch-track"></span></label></div>
				<div class="velox-smap-optrow"><span class="velox-smap-optlabel">Pages</span><label class="velox-switch velox-switch--sm"><input type="checkbox" id="velox-smap-pages" data-setting="seo_sitemap_pages" <?php checked( ! empty( $s['seo_sitemap_pages'] ) ); ?>><span class="velox-switch-track"></span></label></div>
				<div class="velox-smap-optrow"><span class="velox-smap-optlabel">Products</span><label class="velox-switch velox-switch--sm"><input type="checkbox" id="velox-smap-products" data-setting="seo_sitemap_products" <?php checked( ! empty( $s['seo_sitemap_products'] ) ); ?>><span class="velox-switch-track"></span></label></div>
				<div class="velox-smap-optrow"><span class="velox-smap-optlabel">Change frequency</span>
					<select class="velox-select velox-select--sm" id="velox-smap-changefreq" data-setting="seo_sitemap_changefreq">
						<?php foreach ( array( 'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never' ) as $cf ) : ?>
							<option value="<?php echo esc_attr( $cf ); ?>" <?php selected( $s['seo_sitemap_changefreq'], $cf ); ?>><?php echo esc_html( ucfirst( $cf ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="velox-smap-optrow"><span class="velox-smap-optlabel">Priority</span><input type="number" class="velox-input velox-input--sm" id="velox-smap-priority" data-setting="seo_sitemap_priority" value="<?php echo esc_attr( $s['seo_sitemap_priority'] ); ?>" min="0" max="1" step="0.1" style="max-width:80px;"></div>
			</div>
			<?php
			$vx_smap_style  = isset( $s['seo_sitemap_style'] ) ? $s['seo_sitemap_style'] : 'none';
			$vx_smap_styles = array( 'none' => 'Classic', 'clean' => 'Clean', 'cards' => 'Cards', 'dark' => 'Dark', 'minimal' => 'Minimal', 'custom' => 'Custom' );
			?>
			<div class="velox-smap-styles">
				<span class="velox-smap-optlabel" style="width:100%;">Sitemap appearance <span class="velox-hint" style="font-weight:400;">— how sitemap.xml looks when opened in a browser. Search engines still read the plain XML.</span></span>
				<div class="velox-smap-stylecards">
					<?php foreach ( $vx_smap_styles as $vx_k => $vx_lbl ) : ?>
						<button type="button" class="velox-smap-style<?php echo $vx_smap_style === $vx_k ? ' is-active' : ''; ?>" data-style="<?php echo esc_attr( $vx_k ); ?>">
							<span class="velox-smap-sw velox-smap-sw--<?php echo esc_attr( $vx_k ); ?>"></span>
							<span class="velox-smap-style-name"><?php echo esc_html( $vx_lbl ); ?></span>
							<?php if ( 'none' === $vx_k ) : ?><span class="velox-smap-style-note">Default</span><?php endif; ?>
						</button>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="velox-smap-custom" id="velox-smap-custom"<?php echo 'custom' === $vx_smap_style ? '' : ' hidden'; ?>>
				<span class="velox-smap-optlabel">Custom</span>
				<label class="velox-smap-cf"><span>Background</span><input type="color" id="velox-smap-bg" data-setting="seo_sitemap_bg" value="<?php echo esc_attr( isset( $s['seo_sitemap_bg'] ) ? $s['seo_sitemap_bg'] : '#ffffff' ); ?>"></label>
				<label class="velox-smap-cf"><span>Text</span><input type="color" id="velox-smap-fg" data-setting="seo_sitemap_fg" value="<?php echo esc_attr( isset( $s['seo_sitemap_fg'] ) ? $s['seo_sitemap_fg'] : '#1d1d1f' ); ?>"></label>
				<label class="velox-smap-cf"><span>Accent</span><input type="color" id="velox-smap-accent" data-setting="seo_sitemap_accent" value="<?php echo esc_attr( $s['seo_sitemap_accent'] ); ?>"></label>
				<label class="velox-smap-cf"><span>Layout</span>
					<select class="velox-select velox-input--sm" id="velox-smap-layout" data-setting="seo_sitemap_layout" style="max-width:140px;">
						<?php $vx_lay = isset( $s['seo_sitemap_layout'] ) ? $s['seo_sitemap_layout'] : 'table'; foreach ( array( 'table' => 'Table', 'list' => 'List', 'cards' => 'Cards' ) as $vk => $vl ) { printf( '<option value="%s"%s>%s</option>', esc_attr( $vk ), selected( $vx_lay, $vk, false ), esc_html( $vl ) ); } ?>
					</select>
				</label>
				<label class="velox-smap-cf"><span>Spacing</span>
					<select class="velox-select velox-input--sm" id="velox-smap-spacing" data-setting="seo_sitemap_spacing" style="max-width:140px;">
						<?php $vx_sp = isset( $s['seo_sitemap_spacing'] ) ? $s['seo_sitemap_spacing'] : 'normal'; foreach ( array( 'compact' => 'Compact', 'normal' => 'Normal', 'spacious' => 'Spacious' ) as $vk => $vl ) { printf( '<option value="%s"%s>%s</option>', esc_attr( $vk ), selected( $vx_sp, $vk, false ), esc_html( $vl ) ); } ?>
					</select>
				</label>
				<label class="velox-smap-cf"><span>Heading</span><input type="text" class="velox-input velox-input--sm" id="velox-smap-heading" data-setting="seo_sitemap_heading" value="<?php echo esc_attr( $s['seo_sitemap_heading'] ); ?>" style="max-width:160px;"></label>
				<label class="velox-smap-cf"><span>Show logo / name</span><label class="velox-switch velox-switch--sm"><input type="checkbox" id="velox-smap-logo" data-setting="seo_sitemap_logo" <?php checked( ! empty( $s['seo_sitemap_logo'] ) ); ?>><span class="velox-switch-track"></span></label></label>
			</div>
			<div class="velox-smap-preview-wrap">
				<div class="velox-smap-preview-head">Live preview <span>example URLs — not your real site</span></div>
				<pre class="velox-mono velox-smap-preview" id="velox-smap-preview"></pre>
				<?php
				$vx_smap_logo_id  = (int) get_theme_mod( 'custom_logo' );
				$vx_smap_logo_url = $vx_smap_logo_id ? wp_get_attachment_image_url( $vx_smap_logo_id, 'medium' ) : '';
				?>
				<div class="velox-smap-styled" id="velox-smap-styled" data-brand-name="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" data-logo-url="<?php echo esc_attr( $vx_smap_logo_url ); ?>" hidden></div>
			</div>
		</div>

		<div class="velox-actions">
			<button class="velox-btn velox-btn--primary" id="velox-seo-smap-gen">Regenerate sitemap</button>
			<a class="velox-btn velox-btn--ghost" href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" target="_blank" rel="noopener">View sitemap</a>
		</div>
	</div>
</div>

<!-- ============ Social cards (Open Graph) ============ -->
<div class="velox-panel">
	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label">Social cards (Open Graph)</span>
			<span class="velox-toggle-desc">Adds Open Graph &amp; Twitter tags (<code>og:title</code>, <code>og:image</code>…) so links shared to Facebook, LinkedIn, WhatsApp and X show a rich preview. Turn off if another tool already handles them.</span>
		</div>
		<label class="velox-switch"><input type="checkbox" id="velox-seo-og-enable" data-setting="seo_og_enable" <?php checked( ! empty( $s['seo_og_enable'] ) ); ?>><span class="velox-switch-track"></span></label>
	</div>
</div>

<!-- ============ .htaccess editor ============ -->
<?php
$ht_exists   = Velox_Seo::htaccess_exists();
$ht_content  = Velox_Seo::htaccess_content();
$ht_writable = Velox_Seo::htaccess_writable();
?>
<div class="velox-panel" id="velox-htaccess">
	<div class="velox-cache-status-row">
		<h3 class="velox-panel-title">.htaccess</h3>
		<label class="velox-inline-toggle" title="Unlock to edit this file">
			<span>Unlock editing</span>
			<span class="velox-switch"><input type="checkbox" id="velox-ht-unlock"<?php disabled( ! $ht_writable ); ?>><span class="velox-switch-track"></span></span>
		</label>
	</div>
	<div class="velox-alert velox-alert--warn" style="margin-bottom:12px;">
		<strong>Careful — this is your live server config.</strong> A bad rule here can take the whole site down with a 500 error. Unlocking takes a snapshot first, so <strong>Reset to default</strong> can always put it back exactly as it was when you unlocked.
	</div>
	<?php if ( ! $ht_writable ) : ?>
		<div class="velox-alert velox-alert--info" style="margin-bottom:12px;">The <code>.htaccess</code> file isn't writable by WordPress, so editing is disabled. Adjust file permissions on the server to enable it.</div>
	<?php elseif ( ! $ht_exists ) : ?>
		<div class="velox-alert velox-alert--info" style="margin-bottom:12px;">No <code>.htaccess</code> exists in your site root yet — saving will create one.</div>
	<?php endif; ?>
	<textarea class="velox-textarea velox-mono" id="velox-ht-content" rows="22" spellcheck="false" readonly<?php echo $ht_writable ? '' : ' disabled'; ?>><?php echo esc_textarea( $ht_content ); ?></textarea>
	<div class="velox-actions" style="margin-top:12px;">
		<button class="velox-btn velox-btn--primary" id="velox-ht-save" disabled>Save .htaccess</button>
		<button class="velox-btn velox-btn--ghost" id="velox-ht-reset" disabled>Reset to default</button>
	</div>
	<span class="velox-hint">Served by Apache / LiteSpeed from your site root. Has no effect on a pure-Nginx server.</span>
</div>
