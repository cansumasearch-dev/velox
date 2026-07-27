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
	<h1 class="velox-h2"><?php esc_html_e('SEO', 'velox'); ?></h1>
	<p class="velox-sub"><?php esc_html_e('Edit your robots.txt, control how each page appears in Google, and keep an XML sitemap in sync — the essentials, without a second heavyweight SEO plugin.', 'velox'); ?></p>
</div>

<!-- ============ One-click setup ============ -->
<div class="velox-panel velox-seo-oneclick">
	<div class="velox-seo-oneclick-row">
		<div>
			<h3 class="velox-panel-title"><?php esc_html_e('Recommended setup', 'velox'); ?></h3>
			<p class="velox-hint"><?php esc_html_e('Applies the standard robots.txt, switches on the sitemap and generates it right now — everything wired in one click.', 'velox'); ?></p>
		</div>
		<button class="velox-btn velox-btn--primary" id="velox-seo-apply"><?php esc_html_e('Apply recommended setup', 'velox'); ?></button>
	</div>
</div>

<div class="velox-grid-2">
	<!-- ============ robots.txt ============ -->
	<div class="velox-panel">
		<div class="velox-cache-status-row">
			<h3 class="velox-panel-title"><?php esc_html_e('robots.txt', 'velox'); ?></h3>
			<label class="velox-switch"><input type="checkbox" id="velox-seo-robots-enable" data-setting="seo_robots_enable" <?php checked( $robots_on ); ?>><span class="velox-switch-track"></span></label>
		</div>
		<p class="velox-hint"><?php printf( esc_html__( 'Served by WordPress at %s. Edit freely — the Sitemap line should point at your sitemap.xml.', 'velox' ), '<code>' . esc_html( home_url( '/robots.txt' ) ) . '</code>' ); ?></p>
		<?php if ( $physical ) : ?>
			<div class="velox-alert velox-alert--info"><?php printf( esc_html__( 'A physical %s exists in your site root and is being served directly. The editor keeps it in sync on save. This is the most reliable setup behind Nginx or a CDN — use "Back to virtual" to remove it.', 'velox' ), '<code>' . esc_html__( 'robots.txt', 'velox' ) . '</code>' ); ?></div>
		<?php endif; ?>
		<textarea class="velox-textarea velox-mono" id="velox-seo-robots" rows="8"><?php echo esc_textarea( $robots ); ?></textarea>
		<div class="velox-actions">
			<button class="velox-btn velox-btn--primary" id="velox-seo-robots-save"><?php esc_html_e('Save robots.txt', 'velox'); ?></button>
			<button class="velox-btn velox-btn--ghost" id="velox-seo-robots-reset"><?php esc_html_e('Reset to recommended', 'velox'); ?></button>
			<button class="velox-btn velox-btn--ghost" id="velox-seo-robots-view" data-url="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>"><?php esc_html_e('View live robots.txt', 'velox'); ?></button>
			<?php if ( $physical ) : ?>
				<button class="velox-btn velox-btn--ghost" id="velox-seo-robots-virtual"><?php esc_html_e('Back to virtual', 'velox'); ?></button>
			<?php else : ?>
				<button class="velox-btn velox-btn--ghost" id="velox-seo-robots-physical"><?php esc_html_e('Write to physical file', 'velox'); ?></button>
			<?php endif; ?>
		</div>
		<div class="velox-robots-snips">
			<span class="velox-robots-snips-label"><?php esc_html_e('Quick add:', 'velox'); ?></span>
			<button type="button" class="velox-chip" data-robots-snip="sitemap"><?php esc_html_e('Sitemap line', 'velox'); ?></button>
			<button type="button" class="velox-chip" data-robots-snip="admin"><?php esc_html_e('Protect wp-admin', 'velox'); ?></button>
			<button type="button" class="velox-chip" data-robots-snip="ai"><?php esc_html_e('Block AI crawlers', 'velox'); ?></button>
			<button type="button" class="velox-chip" data-robots-snip="allow"><?php esc_html_e('Allow everything', 'velox'); ?></button>
			<span class="velox-hint velox-robots-snips-hint"><?php esc_html_e('Appends a ready-made block to the editor above — review, then save.', 'velox'); ?></span>
		</div>
		<div id="velox-seo-robots-live" class="velox-seo-live" hidden>
			<div class="velox-seo-live-head"><span><?php printf( esc_html__( 'Live at %s', 'velox' ), '<code>' . esc_html( home_url( '/robots.txt' ) ) . '</code>' ); ?></span><span id="velox-seo-live-badge"></span></div>
			<pre id="velox-seo-live-out" class="velox-seo-live-out"></pre>
			<div id="velox-seo-live-cf" class="velox-alert velox-alert--warn" hidden><strong><?php esc_html_e('That "content signals" block is coming from Cloudflare — not Velox.', 'velox'); ?></strong> <?php printf( esc_html__( 'Velox is serving the clean robots.txt shown in the editor above. Cloudflare adds the signals block at the edge, so no WordPress plugin can remove it. To turn it off: open your Cloudflare dashboard → select this domain → %1$s (older accounts: %2$s) → switch off %3$s / managed robots.txt, then come back and click %4$s again.', 'velox' ), '<em>' . esc_html__( 'AI Crawl Control', 'velox' ) . '</em>', '<em>' . esc_html__( 'Bots', 'velox' ) . '</em>', '<em>' . esc_html__( 'Content Signals Policy', 'velox' ) . '</em>', '<em>' . esc_html__( 'View live robots.txt', 'velox' ) . '</em>' ); ?></div>
		</div>
		<div class="velox-alert velox-alert--warn velox-seo-cf-note">
			<strong><?php esc_html_e('Seeing AI "content signals" text instead of yours?', 'velox'); ?></strong> <?php printf( esc_html__( 'That\'s %1$s serving its own robots.txt at the edge, which overrides this. Fix it in your Cloudflare dashboard: %2$s. Writing a physical file here also helps, since Cloudflare only injects when your origin has no robots.txt.', 'velox' ), '<strong>' . esc_html__( 'Cloudflare', 'velox' ) . '</strong>', '<em>' . esc_html__( 'your zone → AI Crawl Control / Bots → uncheck "Display Content Signals Policy" / managed robots.txt', 'velox' ) . '</em>' ); ?>
		</div>
	</div>

	<!-- ============ Sitemap ============ -->
	<div class="velox-panel">
		<div class="velox-cache-status-row">
			<h3 class="velox-panel-title"><?php esc_html_e('XML sitemap', 'velox'); ?></h3>
			<label class="velox-switch"><input type="checkbox" id="velox-seo-sitemap-enable" data-setting="seo_sitemap_enable" <?php checked( $smap_on ); ?>><span class="velox-switch-track"></span></label>
		</div>
		<div class="velox-mini-stats">
			<div><span id="velox-seo-smap-count"><?php echo $sitemap['exists'] ? (int) $sitemap['urls'] : '—'; ?></span><small><?php esc_html_e('URLs', 'velox'); ?></small></div>
			<div><span><?php echo $sitemap['exists'] ? 'Live' : 'Not built'; ?></span><small><?php esc_html_e('Status', 'velox'); ?></small></div>
			<div><span style="font-size:14px;"><?php echo $sitemap['exists'] ? esc_html( $sitemap['modified'] ) : '—'; ?></span><small><?php esc_html_e('Updated', 'velox'); ?></small></div>
		</div>
		<p class="velox-hint"><?php esc_html_e('Home page first, then your chosen post types (A–Z). Exclude any single page from its editor (the', 'velox'); ?> <strong><?php esc_html_e('Velox SEO', 'velox'); ?></strong> <?php esc_html_e('box → "Exclude from sitemap").', 'velox'); ?></p>

		<div class="velox-smap-editor">
			<div class="velox-smap-opts">
				<div class="velox-smap-optrow"><span class="velox-smap-optlabel"><?php esc_html_e('Home page', 'velox'); ?></span><label class="velox-switch velox-switch--sm"><input type="checkbox" id="velox-smap-home" data-setting="seo_sitemap_home" <?php checked( ! empty( $s['seo_sitemap_home'] ) ); ?>><span class="velox-switch-track"></span></label></div>
				<div class="velox-smap-optrow"><span class="velox-smap-optlabel"><?php esc_html_e('Posts', 'velox'); ?></span><label class="velox-switch velox-switch--sm"><input type="checkbox" id="velox-smap-posts" data-setting="seo_sitemap_posts" <?php checked( ! empty( $s['seo_sitemap_posts'] ) ); ?>><span class="velox-switch-track"></span></label></div>
				<div class="velox-smap-optrow"><span class="velox-smap-optlabel"><?php esc_html_e('Pages', 'velox'); ?></span><label class="velox-switch velox-switch--sm"><input type="checkbox" id="velox-smap-pages" data-setting="seo_sitemap_pages" <?php checked( ! empty( $s['seo_sitemap_pages'] ) ); ?>><span class="velox-switch-track"></span></label></div>
				<div class="velox-smap-optrow"><span class="velox-smap-optlabel"><?php esc_html_e('Products', 'velox'); ?></span><label class="velox-switch velox-switch--sm"><input type="checkbox" id="velox-smap-products" data-setting="seo_sitemap_products" <?php checked( ! empty( $s['seo_sitemap_products'] ) ); ?>><span class="velox-switch-track"></span></label></div>
				<div class="velox-smap-optrow"><span class="velox-smap-optlabel"><?php esc_html_e('Change frequency', 'velox'); ?></span>
					<select class="velox-select velox-select--sm" id="velox-smap-changefreq" data-setting="seo_sitemap_changefreq">
						<?php foreach ( array( 'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never' ) as $cf ) : ?>
							<option value="<?php echo esc_attr( $cf ); ?>" <?php selected( $s['seo_sitemap_changefreq'], $cf ); ?>><?php echo esc_html( ucfirst( $cf ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="velox-smap-optrow"><span class="velox-smap-optlabel"><?php esc_html_e('Priority', 'velox'); ?></span><input type="number" class="velox-input velox-input--sm" id="velox-smap-priority" data-setting="seo_sitemap_priority" value="<?php echo esc_attr( $s['seo_sitemap_priority'] ); ?>" min="0" max="1" step="0.1" style="max-width:80px;"></div>
			</div>
			<?php
			$vx_smap_style  = isset( $s['seo_sitemap_style'] ) ? $s['seo_sitemap_style'] : 'none';
			$vx_smap_styles = array( 'none' => 'Classic', 'clean' => 'Clean', 'cards' => 'Cards', 'dark' => 'Dark', 'minimal' => 'Minimal', 'custom' => 'Custom' );
			?>
			<div class="velox-smap-styles">
				<span class="velox-smap-optlabel" style="width:100%;"><?php esc_html_e('Sitemap appearance', 'velox'); ?> <span class="velox-hint" style="font-weight:400;"><?php esc_html_e('— how sitemap.xml looks when opened in a browser. Search engines still read the plain XML.', 'velox'); ?></span></span>
				<div class="velox-smap-stylecards">
					<?php foreach ( $vx_smap_styles as $vx_k => $vx_lbl ) : ?>
						<button type="button" class="velox-smap-style<?php echo $vx_smap_style === $vx_k ? ' is-active' : ''; ?>" data-style="<?php echo esc_attr( $vx_k ); ?>">
							<span class="velox-smap-sw velox-smap-sw--<?php echo esc_attr( $vx_k ); ?>"></span>
							<span class="velox-smap-style-name"><?php echo esc_html( $vx_lbl ); ?></span>
							<?php if ( 'none' === $vx_k ) : ?><span class="velox-smap-style-note"><?php esc_html_e('Default', 'velox'); ?></span><?php endif; ?>
						</button>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="velox-smap-custom" id="velox-smap-custom"<?php echo 'custom' === $vx_smap_style ? '' : ' hidden'; ?>>
				<span class="velox-smap-optlabel"><?php esc_html_e('Custom', 'velox'); ?></span>
				<label class="velox-smap-cf"><span><?php esc_html_e('Background', 'velox'); ?></span><input type="color" id="velox-smap-bg" data-setting="seo_sitemap_bg" value="<?php echo esc_attr( isset( $s['seo_sitemap_bg'] ) ? $s['seo_sitemap_bg'] : '#ffffff' ); ?>"></label>
				<label class="velox-smap-cf"><span><?php esc_html_e('Text', 'velox'); ?></span><input type="color" id="velox-smap-fg" data-setting="seo_sitemap_fg" value="<?php echo esc_attr( isset( $s['seo_sitemap_fg'] ) ? $s['seo_sitemap_fg'] : '#1d1d1f' ); ?>"></label>
				<label class="velox-smap-cf"><span><?php esc_html_e('Accent', 'velox'); ?></span><input type="color" id="velox-smap-accent" data-setting="seo_sitemap_accent" value="<?php echo esc_attr( $s['seo_sitemap_accent'] ); ?>"></label>
				<label class="velox-smap-cf"><span><?php esc_html_e('Layout', 'velox'); ?></span>
					<select class="velox-select velox-input--sm" id="velox-smap-layout" data-setting="seo_sitemap_layout" style="max-width:140px;">
						<?php $vx_lay = isset( $s['seo_sitemap_layout'] ) ? $s['seo_sitemap_layout'] : 'table'; foreach ( array( 'table' => 'Table', 'list' => 'List', 'cards' => 'Cards' ) as $vk => $vl ) { printf( '<option value="%s"%s>%s</option>', esc_attr( $vk ), selected( $vx_lay, $vk, false ), esc_html( $vl ) ); } ?>
					</select>
				</label>
				<label class="velox-smap-cf"><span><?php esc_html_e('Spacing', 'velox'); ?></span>
					<select class="velox-select velox-input--sm" id="velox-smap-spacing" data-setting="seo_sitemap_spacing" style="max-width:140px;">
						<?php $vx_sp = isset( $s['seo_sitemap_spacing'] ) ? $s['seo_sitemap_spacing'] : 'normal'; foreach ( array( 'compact' => 'Compact', 'normal' => 'Normal', 'spacious' => 'Spacious' ) as $vk => $vl ) { printf( '<option value="%s"%s>%s</option>', esc_attr( $vk ), selected( $vx_sp, $vk, false ), esc_html( $vl ) ); } ?>
					</select>
				</label>
				<label class="velox-smap-cf"><span><?php esc_html_e('Heading', 'velox'); ?></span><input type="text" class="velox-input velox-input--sm" id="velox-smap-heading" data-setting="seo_sitemap_heading" value="<?php echo esc_attr( $s['seo_sitemap_heading'] ); ?>" style="max-width:160px;"></label>
				<label class="velox-smap-cf"><span><?php esc_html_e('Show logo / name', 'velox'); ?></span><label class="velox-switch velox-switch--sm"><input type="checkbox" id="velox-smap-logo" data-setting="seo_sitemap_logo" <?php checked( ! empty( $s['seo_sitemap_logo'] ) ); ?>><span class="velox-switch-track"></span></label></label>
			</div>
			<div class="velox-smap-preview-wrap">
				<div class="velox-smap-preview-head"><?php esc_html_e('Live preview', 'velox'); ?> <span><?php esc_html_e('example URLs — not your real site', 'velox'); ?></span></div>
				<pre class="velox-mono velox-smap-preview" id="velox-smap-preview"></pre>
				<?php
				$vx_smap_logo_id  = (int) get_theme_mod( 'custom_logo' );
				$vx_smap_logo_url = $vx_smap_logo_id ? wp_get_attachment_image_url( $vx_smap_logo_id, 'medium' ) : '';
				?>
				<div class="velox-smap-styled" id="velox-smap-styled" data-brand-name="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" data-logo-url="<?php echo esc_attr( $vx_smap_logo_url ); ?>" hidden></div>
			</div>
		</div>

		<div class="velox-actions">
			<button class="velox-btn velox-btn--primary" id="velox-seo-smap-gen"><?php esc_html_e('Regenerate sitemap', 'velox'); ?></button>
			<a class="velox-btn velox-btn--ghost" href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e('View sitemap', 'velox'); ?></a>
		</div>
	</div>
</div>

<!-- ============ Social cards (Open Graph) ============ -->
<div class="velox-panel">
	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label"><?php esc_html_e('Social cards (Open Graph)', 'velox'); ?></span>
			<span class="velox-toggle-desc">Adds Open Graph &amp; Twitter tags (<code><?php esc_html_e('og:title', 'velox'); ?></code>, <code><?php esc_html_e('og:image', 'velox'); ?></code><?php esc_html_e('…) so links shared to Facebook, LinkedIn, WhatsApp and X show a rich preview. Turn off if another tool already handles them.', 'velox'); ?></span>
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
		<h3 class="velox-panel-title"><?php esc_html_e('.htaccess', 'velox'); ?></h3>
		<label class="velox-inline-toggle" title="<?php esc_attr_e('Unlock to edit this file', 'velox'); ?>">
			<span><?php esc_html_e('Unlock editing', 'velox'); ?></span>
			<span class="velox-switch"><input type="checkbox" id="velox-ht-unlock"<?php disabled( ! $ht_writable ); ?>><span class="velox-switch-track"></span></span>
		</label>
	</div>
	<div class="velox-alert velox-alert--warn" style="margin-bottom:12px;">
		<strong><?php esc_html_e('Careful — this is your live server config.', 'velox'); ?></strong> <?php printf( esc_html__( 'A bad rule here can take the whole site down with a 500 error. Unlocking takes a snapshot first, so %s can always put it back exactly as it was when you unlocked.', 'velox' ), '<strong>' . esc_html__( 'Reset to default', 'velox' ) . '</strong>' ); ?>
	</div>
	<?php if ( ! $ht_writable ) : ?>
		<div class="velox-alert velox-alert--info" style="margin-bottom:12px;"><?php printf( esc_html__( 'The %s file isn\'t writable by WordPress, so editing is disabled. Adjust file permissions on the server to enable it.', 'velox' ), '<code>' . esc_html__( '.htaccess', 'velox' ) . '</code>' ); ?></div>
	<?php elseif ( ! $ht_exists ) : ?>
		<div class="velox-alert velox-alert--info" style="margin-bottom:12px;"><?php printf( esc_html__( 'No %s exists in your site root yet — saving will create one.', 'velox' ), '<code>' . esc_html__( '.htaccess', 'velox' ) . '</code>' ); ?></div>
	<?php endif; ?>
	<textarea class="velox-textarea velox-mono" id="velox-ht-content" rows="22" spellcheck="false" readonly<?php echo $ht_writable ? '' : ' disabled'; ?>><?php echo esc_textarea( $ht_content ); ?></textarea>
	<div class="velox-actions" style="margin-top:12px;">
		<button class="velox-btn velox-btn--primary" id="velox-ht-save" disabled><?php esc_html_e('Save .htaccess', 'velox'); ?></button>
		<button class="velox-btn velox-btn--ghost" id="velox-ht-reset" disabled><?php esc_html_e('Reset to default', 'velox'); ?></button>
	</div>
	<span class="velox-hint"><?php esc_html_e('Served by Apache / LiteSpeed from your site root. Has no effect on a pure-Nginx server.', 'velox'); ?></span>
</div>

<div class="velox-panel velox-panel--flush velox-seoh" id="velox-seoh">
	<div class="velox-seoh-top">
		<div>
			<div class="velox-seoh-t"><?php esc_html_e('SEO health', 'velox'); ?></div>
			<div class="velox-seoh-s" id="velox-seoh-sub"><?php esc_html_e('Checks every published page for the things that quietly cost you traffic.', 'velox'); ?></div>
		</div>
		<button class="velox-btn velox-btn--ghost velox-btn--sm" id="velox-seoh-scan">
			<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>
			<?php esc_html_e('Scan', 'velox'); ?>
		</button>
	</div>
	<div class="velox-seoh-tiles" id="velox-seoh-tiles" hidden></div>
	<div id="velox-seoh-issues"></div>
</div>
