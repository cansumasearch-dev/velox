<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Velox SEO — a focused Rank Math-style toolkit:
 *  - robots.txt editor (served virtually through WordPress)
 *  - per-page SEO title + meta description + noindex, with a Google snippet preview
 *  - XML sitemap with a per-page "exclude from sitemap" switch
 *  - one-click recommended setup
 *
 * The sitemap mirrors the agency's existing custom logic (home first, then
 * post/page/product, honouring the `sitemap_exclude` post meta), so sites that
 * already used that snippet stay compatible.
 */
class Velox_Seo {

	const POST_TYPES = array( 'post', 'page', 'product' );

	public static function init() {
		// robots.txt (virtual). Very high priority so Velox wins over other plugins
		// that also hook robots_txt. Note: a physical file or an edge CDN (Cloudflare's
		// managed robots.txt / Content Signals) still overrides this — see write_physical().
		add_filter( 'robots_txt', array( __CLASS__, 'filter_robots' ), PHP_INT_MAX, 2 );

		// <head> output.
		// Win any pre_get_document_title filter war (Oxygen / other SEO plugins) by
		// running last, and back it up with a wp_head output-buffer that fixes the
		// actual <title> tag when a custom SEO title is set (covers themes/builders
		// that print their own title instead of using wp_get_document_title()).
		add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_title' ), PHP_INT_MAX );
		add_action( 'wp_head', array( __CLASS__, 'buffer_title_start' ), -1 );
		add_action( 'wp_head', array( __CLASS__, 'buffer_title_end' ), 999 );
		add_action( 'wp_head', array( __CLASS__, 'head_tags' ), 1 );
		// Own the robots directives through WP's native filter so there's exactly one
		// robots tag, and so index,follow is emitted explicitly (not just left implicit).
		add_filter( 'wp_robots', array( __CLASS__, 'filter_wp_robots' ), 20 );

		// Editor meta box + save.
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta' ), 10, 1 );

		// Block editor: REST-exposed meta + the Velox SEO sidebar panel.
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_assets' ) );
		// The REST flow writes meta after save_post, so refresh the sitemap once the
		// editor save has fully landed (otherwise sitemap_exclude/noindex lag a save).
		foreach ( self::POST_TYPES as $pt ) {
			add_action( 'rest_after_insert_' . $pt, array( __CLASS__, 'rest_synced' ) );
		}
	}

	public static function rest_synced() {
		self::generate_sitemap();
	}

	/** Expose the SEO meta to the REST API so the Gutenberg panel can read/write it. */
	public static function register_meta() {
		if ( ! Velox_Settings::get( 'module_seo', true ) ) {
			return;
		}
		$keys = array(
			'_velox_seo_title', '_velox_seo_desc', '_velox_seo_noindex', '_velox_seo_nofollow', 'sitemap_exclude',
			'_velox_seo_canonical', '_velox_seo_focus_kw',
			'_velox_seo_og_title', '_velox_seo_og_desc', '_velox_seo_og_image',
		);
		foreach ( self::POST_TYPES as $pt ) {
			foreach ( $keys as $key ) {
				register_post_meta( $pt, $key, array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				) );
			}
		}
	}

	/** Load the block-editor SEO panel on supported post-type screens. */
	public static function editor_assets() {
		if ( ! Velox_Settings::get( 'module_seo', true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ! in_array( $screen->post_type, self::POST_TYPES, true ) ) {
			return;
		}
		wp_enqueue_script(
			'velox-seo-editor',
			VELOX_URL . 'admin/js/velox-seo-editor.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-components', 'wp-data' ),
			VELOX_VERSION,
			true
		);
		wp_localize_script( 'velox-seo-editor', 'VeloxSeoData', array(
			'icon'      => VELOX_URL . 'assets/menu-icon.png',
			'postTypes' => array_values( self::POST_TYPES ),
		) );
	}

	/* ---------------------------------------------------- physical robots.txt */

	/**
	 * Write a real robots.txt to the web root. More reliable than the virtual
	 * filter when a server (Nginx on Plesk) serves /robots.txt directly, or when
	 * a CDN only respects an origin file. Returns true on success.
	 */
	public static function write_physical( $content ) {
		$content = trim( (string) $content );
		if ( '' === $content ) {
			$content = self::robots_content();
		}
		return (bool) @file_put_contents( ABSPATH . 'robots.txt', $content . "\n" );
	}

	public static function delete_physical() {
		if ( file_exists( ABSPATH . 'robots.txt' ) ) {
			return (bool) @unlink( ABSPATH . 'robots.txt' );
		}
		return true;
	}

	/* ----------------------------------------------------------- robots.txt */

	public static function default_robots() {
		$sitemap = home_url( '/sitemap.xml' );
		return "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\nSitemap: " . $sitemap;
	}

	public static function robots_content() {
		$c = (string) Velox_Settings::get( 'seo_robots_content', '' );
		return '' !== trim( $c ) ? $c : self::default_robots();
	}

	public static function filter_robots( $output, $public ) {
		if ( ! Velox_Settings::get( 'module_seo', true ) || ! Velox_Settings::get( 'seo_robots_enable', true ) ) {
			return $output;
		}
		if ( '0' === (string) $public ) {
			return $output; // site is set to discourage search engines — don't fight it
		}
		return self::robots_content() . "\n";
	}

	/** A physical robots.txt at the web root shadows the virtual one. */
	public static function physical_robots_exists() {
		return file_exists( ABSPATH . 'robots.txt' );
	}

	/* ---------------------------------------------------------- head output */

	public static function filter_title( $title ) {
		if ( ! Velox_Settings::get( 'module_seo', true ) || ! is_singular() ) {
			return $title;
		}
		$custom = get_post_meta( get_queried_object_id(), '_velox_seo_title', true );
		return $custom ? $custom : $title;
	}

	/** The custom SEO title for the current singular view, or '' if none set. */
	private static function custom_title() {
		if ( ! Velox_Settings::get( 'module_seo', true ) || ! is_singular() ) { return ''; }
		$t = get_post_meta( get_queried_object_id(), '_velox_seo_title', true );
		return is_string( $t ) ? trim( $t ) : '';
	}

	/** Start buffering wp_head so we can guarantee the <title> when a custom one is set. */
	public static function buffer_title_start() {
		if ( '' !== self::custom_title() ) { ob_start(); }
	}

	/**
	 * Replace the <title> emitted inside wp_head with the custom SEO title. Replace-only
	 * (never injects a second tag) so themes/builders that print their own title get
	 * corrected without ever producing a duplicate title.
	 */
	public static function buffer_title_end() {
		$custom = self::custom_title();
		if ( '' === $custom || 0 === ob_get_level() ) { return; }
		$html = ob_get_clean();
		if ( preg_match( '/<title\b[^>]*>.*?<\/title>/is', $html ) ) {
			$html = preg_replace( '/<title\b[^>]*>.*?<\/title>/is', '<title>' . esc_html( $custom ) . '</title>', $html, 1 );
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapingOutput -- pre-rendered head HTML; injected title is escaped above.
	}

	public static function head_tags() {
		if ( ! Velox_Settings::get( 'module_seo', true ) || ! is_singular() ) {
			return;
		}
		$id   = get_queried_object_id();
		$desc = get_post_meta( $id, '_velox_seo_desc', true );
		$noindex  = '1' === (string) get_post_meta( $id, '_velox_seo_noindex', true );
		$nofollow = '1' === (string) get_post_meta( $id, '_velox_seo_nofollow', true );

		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $desc ) ) . '">' . "\n";
		}
		// The robots tag is emitted via the wp_robots filter (filter_wp_robots) so it
		// stays a single, canonical tag — see init().

		// Canonical — fall back to the post's own permalink when none is set.
		$canonical = get_post_meta( $id, '_velox_seo_canonical', true );
		$canonical = $canonical ? $canonical : get_permalink( $id );
		if ( $canonical ) {
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
		}

		// Open Graph — fall back to the SEO title/description, then the post's own.
		if ( ! Velox_Settings::get( 'seo_og_enable', true ) ) {
			return; // OG/Twitter cards disabled globally in SEO settings.
		}
		$og_title = get_post_meta( $id, '_velox_seo_og_title', true );
		$og_desc  = get_post_meta( $id, '_velox_seo_og_desc', true );
		$og_image = get_post_meta( $id, '_velox_seo_og_image', true );
		$og_title = $og_title ? $og_title : ( get_post_meta( $id, '_velox_seo_title', true ) ? get_post_meta( $id, '_velox_seo_title', true ) : get_the_title( $id ) );
		$og_desc  = $og_desc ? $og_desc : $desc;
		if ( ! $og_image && has_post_thumbnail( $id ) ) {
			$og_image = get_the_post_thumbnail_url( $id, 'full' );
		}
		echo '<meta property="og:type" content="article">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( wp_strip_all_tags( $og_title ) ) . '">' . "\n";
		if ( $og_desc ) {
			echo '<meta property="og:description" content="' . esc_attr( wp_strip_all_tags( $og_desc ) ) . '">' . "\n";
		}
		echo '<meta property="og:url" content="' . esc_url( $canonical ) . '">' . "\n";
		if ( $og_image ) {
			echo '<meta property="og:image" content="' . esc_url( $og_image ) . '">' . "\n";
			echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
		}
		echo '<meta name="twitter:title" content="' . esc_attr( wp_strip_all_tags( $og_title ) ) . '">' . "\n";
		if ( $og_desc ) {
			echo '<meta name="twitter:description" content="' . esc_attr( wp_strip_all_tags( $og_desc ) ) . '">' . "\n";
		}
	}

	/**
	 * Emit an explicit robots directive through WP's native wp_robots filter, so the
	 * page always states its intent — "index, follow" when allowed, "noindex"/"nofollow"
	 * when restricted — instead of leaving the allow-case implicit (and invisible).
	 */
	public static function filter_wp_robots( $robots ) {
		if ( ! Velox_Settings::get( 'module_seo', true ) || ! is_singular() ) {
			return $robots;
		}
		$id       = get_queried_object_id();
		$noindex  = '1' === (string) get_post_meta( $id, '_velox_seo_noindex', true );
		$nofollow = '1' === (string) get_post_meta( $id, '_velox_seo_nofollow', true );

		if ( $noindex ) {
			$robots['noindex'] = true;
			unset( $robots['index'] );
		} else {
			$robots['index'] = true;
			unset( $robots['noindex'] );
		}
		if ( $nofollow ) {
			$robots['nofollow'] = true;
			unset( $robots['follow'] );
		} else {
			$robots['follow'] = true;
			unset( $robots['nofollow'] );
		}
		return $robots;
	}

	/* ------------------------------------------------------------- meta box */

	public static function add_meta_box() {
		if ( ! Velox_Settings::get( 'module_seo', true ) ) {
			return;
		}
		// In the block editor the Velox SEO sidebar panel replaces this meta box.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			return;
		}
		foreach ( self::POST_TYPES as $pt ) {
			add_meta_box( 'velox_seo', 'Velox SEO', array( __CLASS__, 'meta_box_html' ), $pt, 'normal', 'high' );
		}
	}

	public static function meta_box_html( $post ) {
		wp_nonce_field( 'velox_seo_save', 'velox_seo_nonce' );
		$title   = get_post_meta( $post->ID, '_velox_seo_title', true );
		$desc    = get_post_meta( $post->ID, '_velox_seo_desc', true );
		$noindex = '1' === (string) get_post_meta( $post->ID, '_velox_seo_noindex', true );
		$nofollow = '1' === (string) get_post_meta( $post->ID, '_velox_seo_nofollow', true );
		$exclude = get_post_meta( $post->ID, 'sitemap_exclude', true );
		$url     = get_permalink( $post->ID );
		$fallback_title = get_the_title( $post->ID );
		?>
		<div class="velox-seo-box">
			<div class="velox-seo-preview">
				<div class="velox-seo-preview-url"><?php echo esc_html( $url ); ?></div>
				<div class="velox-seo-preview-title" id="velox-seo-pv-title"><?php echo esc_html( $title ? $title : $fallback_title ); ?></div>
				<div class="velox-seo-preview-desc" id="velox-seo-pv-desc"><?php echo esc_html( $desc ? $desc : 'Add a meta description to control how this page looks in Google.' ); ?></div>
			</div>
			<p>
				<label for="velox-seo-title"><strong>SEO title</strong></label><br>
				<input type="text" id="velox-seo-title" name="velox_seo_title" value="<?php echo esc_attr( $title ); ?>" style="width:100%;" placeholder="<?php echo esc_attr( $fallback_title ); ?>">
				<span class="velox-seo-count" id="velox-seo-title-count"></span>
			</p>
			<p>
				<label for="velox-seo-desc"><strong>Meta description</strong></label><br>
				<textarea id="velox-seo-desc" name="velox_seo_desc" rows="3" style="width:100%;" placeholder="A short, compelling summary for search results…"><?php echo esc_textarea( $desc ); ?></textarea>
				<span class="velox-seo-count" id="velox-seo-desc-count"></span>
			</p>
			<div class="velox-seo-robots">
				<div class="velox-seo-seg">
					<span class="velox-seo-seg-label">Search engines</span>
					<div class="velox-seo-seg-btns">
						<label class="<?php echo $noindex ? '' : 'is-on'; ?>"><input type="radio" name="velox_seo_index" value="index" <?php checked( ! $noindex ); ?>> Index</label>
						<label class="<?php echo $noindex ? 'is-on' : ''; ?>"><input type="radio" name="velox_seo_index" value="noindex" <?php checked( $noindex ); ?>> Noindex</label>
					</div>
				</div>
				<div class="velox-seo-seg">
					<span class="velox-seo-seg-label">Links</span>
					<div class="velox-seo-seg-btns">
						<label class="<?php echo $nofollow ? '' : 'is-on'; ?>"><input type="radio" name="velox_seo_follow" value="follow" <?php checked( ! $nofollow ); ?>> Follow</label>
						<label class="<?php echo $nofollow ? 'is-on' : ''; ?>"><input type="radio" name="velox_seo_follow" value="nofollow" <?php checked( $nofollow ); ?>> Nofollow</label>
					</div>
				</div>
				<label class="velox-seo-exclude"><input type="checkbox" name="sitemap_exclude" value="1" <?php checked( '1', (string) $exclude ); ?>> Exclude this page from the sitemap</label>
				<p class="velox-seo-robots-out">Search engines will be told: <code id="velox-seo-robots-out">index, follow</code></p>
			</div>
			<?php
			$canonical = get_post_meta( $post->ID, '_velox_seo_canonical', true );
			$focus_kw  = get_post_meta( $post->ID, '_velox_seo_focus_kw', true );
			$og_title  = get_post_meta( $post->ID, '_velox_seo_og_title', true );
			$og_desc   = get_post_meta( $post->ID, '_velox_seo_og_desc', true );
			$og_image  = get_post_meta( $post->ID, '_velox_seo_og_image', true );
			?>
			<p style="margin-top:14px;">
				<label for="velox-seo-focus"><strong>Focus keyword</strong></label><br>
				<input type="text" id="velox-seo-focus" name="velox_seo_focus_kw" value="<?php echo esc_attr( $focus_kw ); ?>" style="width:100%;" placeholder="The main phrase you want to rank for">
			</p>
			<p>
				<label for="velox-seo-canonical"><strong>Canonical URL</strong></label><br>
				<input type="text" id="velox-seo-canonical" name="velox_seo_canonical" value="<?php echo esc_attr( $canonical ); ?>" style="width:100%;" placeholder="<?php echo esc_attr( $url ); ?>">
			</p>
			<p>
				<label for="velox-seo-og-title"><strong>Social title</strong> <span style="font-weight:400;color:#777;">(Open Graph)</span></label><br>
				<input type="text" id="velox-seo-og-title" name="velox_seo_og_title" value="<?php echo esc_attr( $og_title ); ?>" style="width:100%;" placeholder="Falls back to the SEO title">
			</p>
			<p>
				<label for="velox-seo-og-desc"><strong>Social description</strong></label><br>
				<textarea id="velox-seo-og-desc" name="velox_seo_og_desc" rows="2" style="width:100%;" placeholder="Falls back to the meta description"><?php echo esc_textarea( $og_desc ); ?></textarea>
			</p>
			<p>
				<label for="velox-seo-og-image"><strong>Social image URL</strong></label><br>
				<input type="text" id="velox-seo-og-image" name="velox_seo_og_image" value="<?php echo esc_attr( $og_image ); ?>" style="width:100%;" placeholder="Defaults to the featured image · 1200×630">
			</p>
		</div>
		<style>
			.velox-seo-preview{border:1px solid #dcdcde;border-radius:8px;padding:12px 14px;margin-bottom:14px;background:#fff}
			.velox-seo-preview-url{color:#202124;font-size:12px;margin-bottom:2px}
			.velox-seo-preview-title{color:#1a0dab;font-size:18px;line-height:1.3;overflow:hidden}
			.velox-seo-preview-desc{color:#4d5156;font-size:13px;line-height:1.5;margin-top:2px}
			.velox-seo-count{font-size:11px;color:#646970}
			.velox-seo-box label{font-size:13px}
			.velox-seo-robots{border-top:1px solid #e0e0e0;margin-top:6px;padding-top:12px}
			.velox-seo-seg{display:flex;align-items:center;gap:12px;margin-bottom:10px}
			.velox-seo-seg-label{font-weight:600;min-width:110px}
			.velox-seo-seg-btns{display:inline-flex;border:1px solid #c3c4c7;border-radius:6px;overflow:hidden}
			.velox-seo-seg-btns label{margin:0;padding:5px 14px;cursor:pointer;background:#fff;color:#50575e;border-right:1px solid #e0e0e0}
			.velox-seo-seg-btns label:last-child{border-right:0}
			.velox-seo-seg-btns label.is-on{background:#2271b1;color:#fff;font-weight:600}
			.velox-seo-seg-btns input{display:none}
			.velox-seo-exclude{display:block;margin-top:4px}
			.velox-seo-robots-out{margin:10px 0 0;color:#646970;font-size:12px}
			.velox-seo-robots-out code{background:#f0f0f1;padding:2px 6px;border-radius:4px}
		</style>
		<script>
		(function(){
			var t=document.getElementById('velox-seo-title'),d=document.getElementById('velox-seo-desc');
			var pt=document.getElementById('velox-seo-pv-title'),pd=document.getElementById('velox-seo-pv-desc');
			var ct=document.getElementById('velox-seo-title-count'),cd=document.getElementById('velox-seo-desc-count');
			var out=document.getElementById('velox-seo-robots-out');
			var fbT=<?php echo wp_json_encode( $fallback_title ); ?>;
			function upd(){
				if(pt)pt.textContent=t.value||fbT;
				if(pd)pd.textContent=d.value||'Add a meta description to control how this page looks in Google.';
				if(ct)ct.textContent=t.value.length+' chars'+(t.value.length>60?' — a bit long':'');
				if(cd)cd.textContent=d.value.length+' chars'+(d.value.length>160?' — a bit long':'');
			}
			if(t&&d){t.addEventListener('input',upd);d.addEventListener('input',upd);upd();}
			// Segmented toggles: visual state + live robots readout.
			function seg(name){return document.querySelector('input[name="'+name+'"]:checked');}
			function paint(){
				document.querySelectorAll('.velox-seo-seg-btns label').forEach(function(l){
					var r=l.querySelector('input'); l.classList.toggle('is-on', r&&r.checked);
				});
				var i=seg('velox_seo_index'),f=seg('velox_seo_follow');
				if(out)out.textContent=((i?i.value:'index'))+', '+((f?f.value:'follow'));
			}
			document.querySelectorAll('.velox-seo-seg-btns input').forEach(function(r){r.addEventListener('change',paint);});
			paint();
		})();
		</script>
		<?php
	}

	public static function save_meta( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['velox_seo_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['velox_seo_nonce'] ), 'velox_seo_save' ) ) {
			// No SEO box on this save (e.g. quick edit) — still keep the sitemap fresh.
			self::generate_sitemap();
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_velox_seo_title', sanitize_text_field( wp_unslash( $_POST['velox_seo_title'] ?? '' ) ) );
		update_post_meta( $post_id, '_velox_seo_desc', sanitize_textarea_field( wp_unslash( $_POST['velox_seo_desc'] ?? '' ) ) );
		// Segmented index/follow controls (fall back to the legacy checkbox if present).
		$noindex  = isset( $_POST['velox_seo_index'] ) ? ( 'noindex' === $_POST['velox_seo_index'] ) : isset( $_POST['velox_seo_noindex'] );
		$nofollow = isset( $_POST['velox_seo_follow'] ) && 'nofollow' === $_POST['velox_seo_follow'];
		update_post_meta( $post_id, '_velox_seo_noindex', $noindex ? '1' : '0' );
		update_post_meta( $post_id, '_velox_seo_nofollow', $nofollow ? '1' : '0' );
		update_post_meta( $post_id, 'sitemap_exclude', isset( $_POST['sitemap_exclude'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_velox_seo_canonical', esc_url_raw( wp_unslash( $_POST['velox_seo_canonical'] ?? '' ) ) );
		update_post_meta( $post_id, '_velox_seo_focus_kw', sanitize_text_field( wp_unslash( $_POST['velox_seo_focus_kw'] ?? '' ) ) );
		update_post_meta( $post_id, '_velox_seo_og_title', sanitize_text_field( wp_unslash( $_POST['velox_seo_og_title'] ?? '' ) ) );
		update_post_meta( $post_id, '_velox_seo_og_desc', sanitize_textarea_field( wp_unslash( $_POST['velox_seo_og_desc'] ?? '' ) ) );
		update_post_meta( $post_id, '_velox_seo_og_image', esc_url_raw( wp_unslash( $_POST['velox_seo_og_image'] ?? '' ) ) );

		self::generate_sitemap();
	}

	/* ------------------------------------------------------------- sitemap */

	/** Mirrors the agency's custom generator: home first, then post/page/product. */
	public static function generate_sitemap() {
		if ( ! Velox_Settings::get( 'module_seo', true ) || ! Velox_Settings::get( 'seo_sitemap_enable', true ) ) {
			return false;
		}
		$xml   = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
		$style = (string) Velox_Settings::get( 'seo_sitemap_style', 'none' );
		if ( 'none' !== $style ) {
			self::write_sitemap_xsl( $style );
			$xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url( home_url( '/velox-sitemap.xsl' ) ) . '"?>' . PHP_EOL;
		} else {
			$xsl = ABSPATH . 'velox-sitemap.xsl';
			if ( file_exists( $xsl ) ) { @unlink( $xsl ); } // phpcs:ignore
		}
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
		foreach ( self::sitemap_entries() as $e ) {
			$xml .= self::sitemap_url( $e['loc'], $e['lastmod'], $e['priority'], $e['changefreq'] );
		}
		$xml .= '</urlset>';

		return (bool) @file_put_contents( ABSPATH . 'sitemap.xml', $xml ); // phpcs:ignore
	}

	/**
	 * The real sitemap entries — home first, then the included post types (A–Z),
	 * honouring the per-page exclude and noindex meta. This is the single source of
	 * truth: both the written sitemap.xml and the admin live preview use it, so the
	 * preview matches the real site exactly. Pass a $limit to cap it for the preview.
	 *
	 * @return array<int,array{loc:string,lastmod:string,priority:string,changefreq:string}>
	 */
	public static function sitemap_entries( $limit = 0 ) {
		if ( ! Velox_Settings::get( 'module_seo', true ) || ! Velox_Settings::get( 'seo_sitemap_enable', true ) ) {
			return array();
		}
		$homepage_id  = (int) get_option( 'page_on_front' );
		$homepage_url = trailingslashit( home_url( '/' ) );
		$changefreq   = (string) Velox_Settings::get( 'seo_sitemap_changefreq', 'weekly' );
		$priority     = (string) Velox_Settings::get( 'seo_sitemap_priority', '0.7' );

		$type_map = array( 'post' => 'seo_sitemap_posts', 'page' => 'seo_sitemap_pages', 'product' => 'seo_sitemap_products' );
		$types    = array();
		foreach ( self::POST_TYPES as $pt ) {
			if ( ! isset( $type_map[ $pt ] ) || (bool) Velox_Settings::get( $type_map[ $pt ], true ) ) {
				$types[] = $pt;
			}
		}

		$entries = array();
		if ( (bool) Velox_Settings::get( 'seo_sitemap_home', true ) && $homepage_id && 'publish' === get_post_status( $homepage_id ) && '1' !== (string) get_post_meta( $homepage_id, 'sitemap_exclude', true ) ) {
			$entries[] = array( 'loc' => $homepage_url, 'lastmod' => (string) get_the_modified_date( 'c', $homepage_id ), 'priority' => '1.0', 'changefreq' => $changefreq );
		}
		if ( ! empty( $types ) ) {
			$q = new WP_Query( array(
				'post_type'      => $types,
				'posts_per_page' => $limit > 0 ? (int) $limit : -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			) );
			if ( $q->have_posts() ) {
				while ( $q->have_posts() ) {
					$q->the_post();
					$pid = get_the_ID();
					$url = trailingslashit( get_permalink() );
					if ( $url === $homepage_url ) { continue; }
					if ( '1' === (string) get_post_meta( $pid, 'sitemap_exclude', true ) ) { continue; }
					if ( '1' === (string) get_post_meta( $pid, '_velox_seo_noindex', true ) ) { continue; }
					$entries[] = array( 'loc' => $url, 'lastmod' => (string) get_the_modified_date( 'c' ), 'priority' => $priority, 'changefreq' => $changefreq );
				}
				wp_reset_postdata();
			}
		}
		return $entries;
	}

	/**
	 * Write the XSL stylesheet that styles how sitemap.xml renders in a browser.
	 * Search engines ignore it and read the raw XML; this is purely cosmetic for people.
	 */
	private static function write_sitemap_xsl( $style ) {
		$accent  = (string) Velox_Settings::get( 'seo_sitemap_accent', '#2ab7f1' );
		$heading = wp_strip_all_tags( (string) Velox_Settings::get( 'seo_sitemap_heading', 'XML Sitemap' ) );
		$logo_on = (bool) Velox_Settings::get( 'seo_sitemap_logo', true );
		if ( ! preg_match( '/^#[0-9a-fA-F]{3,8}$/', $accent ) ) { $accent = '#2ab7f1'; }
		if ( '' === $heading ) { $heading = 'XML Sitemap'; }

		switch ( $style ) {
			case 'dark':
				$bg = '#1d1f21'; $fg = '#d3dbe2'; $muted = '#8b96a0'; $border = 'rgba(255,255,255,.09)'; $thbg = '#22262a'; $link = '#7ec7ff'; $bar = $accent; break;
			case 'minimal':
				$bg = '#ffffff'; $fg = '#1d1d1f'; $muted = '#6e6e73'; $border = '#eeeeee'; $thbg = '#fafafa'; $link = '#0f7ab5'; $bar = 'transparent'; break;
			case 'custom':
			case 'clean':
			default:
				$bg = '#ffffff'; $fg = '#1d1d1f'; $muted = '#6e6e73'; $border = '#eeeeee'; $thbg = '#f5f7f9'; $link = '#0f7ab5'; $bar = $accent; break;
		}

		$brand = '';
		if ( $logo_on ) {
			$logo_id  = (int) get_theme_mod( 'custom_logo' );
			$logo_src = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
			$brand    = $logo_src
				? '<img src="' . esc_url( $logo_src ) . '" alt="" style="height:30px;width:auto;margin-bottom:8px;display:block;"/>'
				: '<div style="font-size:12px;font-weight:600;color:' . $muted . ';margin-bottom:4px;">' . esc_html( get_bloginfo( 'name' ) ) . '</div>';
		}

		$css = 'body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:' . $bg . ';color:' . $fg . ';}'
			. '.vx-wrap{max-width:1040px;margin:0 auto;padding:0 20px 40px;}'
			. 'header.vx-h{padding:22px 0 18px;border-bottom:3px solid ' . $bar . ';margin-bottom:6px;}'
			. 'h1{font-size:20px;margin:0;font-weight:600;}'
			. '.vx-sub{color:' . $muted . ';font-size:13px;margin-top:4px;}'
			. 'table{width:100%;border-collapse:collapse;font-size:13px;}'
			. 'th{text-align:left;background:' . $thbg . ';color:' . $muted . ';padding:10px 12px;font-weight:600;}'
			. 'td{padding:9px 12px;border-top:1px solid ' . $border . ';vertical-align:top;}'
			. 'a{color:' . $link . ';text-decoration:none;}a:hover{text-decoration:underline;}'
			. '.vx-num{color:' . $muted . ';white-space:nowrap;}';

		$xsl = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
			. '<xsl:output method="html" encoding="UTF-8" indent="yes"/>' . "\n"
			. '<xsl:template match="/">' . "\n"
			. '<html><head><meta charset="UTF-8"/><title>' . esc_html( $heading ) . '</title><style>' . $css . '</style></head>'
			. '<body><div class="vx-wrap">'
			. '<header class="vx-h">' . $brand . '<h1>' . esc_html( $heading ) . '</h1>'
			. '<div class="vx-sub">Generated by Velox &#183; <xsl:value-of select="count(s:urlset/s:url)"/> URLs</div></header>'
			. '<table><thead><tr><th>URL</th><th>Priority</th><th>Change frequency</th><th>Last modified</th></tr></thead><tbody>'
			. '<xsl:for-each select="s:urlset/s:url">'
			. '<tr>'
			. '<td><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td>'
			. '<td class="vx-num"><xsl:value-of select="s:priority"/></td>'
			. '<td class="vx-num"><xsl:value-of select="s:changefreq"/></td>'
			. '<td class="vx-num"><xsl:value-of select="s:lastmod"/></td>'
			. '</tr>'
			. '</xsl:for-each>'
			. '</tbody></table>'
			. '</div></body></html>'
			. '</xsl:template></xsl:stylesheet>';

		return (bool) @file_put_contents( ABSPATH . 'velox-sitemap.xsl', $xsl ); // phpcs:ignore
	}

	private static function sitemap_url( $loc, $lastmod, $priority = '', $changefreq = '' ) {
		$out  = '  <url>' . PHP_EOL;
		$out .= '    <loc>' . esc_url( $loc ) . '</loc>' . PHP_EOL;
		if ( $lastmod ) {
			$out .= '    <lastmod>' . esc_html( $lastmod ) . '</lastmod>' . PHP_EOL;
		}
		if ( '' !== $changefreq ) {
			$out .= '    <changefreq>' . esc_html( $changefreq ) . '</changefreq>' . PHP_EOL;
		}
		if ( '' !== $priority ) {
			$out .= '    <priority>' . esc_html( $priority ) . '</priority>' . PHP_EOL;
		}
		$out .= '  </url>' . PHP_EOL;
		return $out;
	}

	public static function sitemap_stats() {
		$file = ABSPATH . 'sitemap.xml';
		if ( ! is_readable( $file ) ) {
			return array( 'exists' => false, 'urls' => 0, 'modified' => '' );
		}
		$xml = (string) file_get_contents( $file );
		return array(
			'exists'   => true,
			'urls'     => substr_count( $xml, '<loc>' ),
			'modified' => date_i18n( 'Y-m-d H:i', filemtime( $file ) ),
		);
	}

	/* ----------------------------------------------------------- 1-click ops */

	public static function apply_recommended() {
		$all = Velox_Settings::all();
		$all['module_seo']        = true;
		$all['seo_robots_enable'] = true;
		$all['seo_robots_content'] = self::default_robots();
		$all['seo_sitemap_enable'] = true;
		Velox_Settings::save( $all );
		$ok = self::generate_sitemap();
		return array(
			'ok'             => true,
			'sitemap'        => $ok,
			'physical_robots' => self::physical_robots_exists(),
			'message'        => 'Recommended SEO setup applied — robots.txt and sitemap are live.',
		);
	}

	public static function save_robots( $content ) {
		$all = Velox_Settings::all();
		$all['seo_robots_content'] = sanitize_textarea_field( $content );
		Velox_Settings::save( $all );
		// If a physical robots.txt is in play, keep it in sync with the editor.
		if ( self::physical_robots_exists() ) {
			self::write_physical( $all['seo_robots_content'] );
		}
		return array( 'ok' => true, 'physical' => self::physical_robots_exists() );
	}

	/* ----------------------------------------------------------- .htaccess */

	const HTACCESS_SNAPSHOT = 'velox_htaccess_snapshot';

	public static function htaccess_path() {
		return ABSPATH . '.htaccess';
	}

	/** Current .htaccess contents ('' when the file doesn't exist). */
	public static function htaccess_content() {
		$p = self::htaccess_path();
		if ( ! file_exists( $p ) ) {
			return '';
		}
		$c = @file_get_contents( $p ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return is_string( $c ) ? $c : '';
	}

	public static function htaccess_exists() {
		return file_exists( self::htaccess_path() );
	}

	/** Can we actually write the file (or create it in the site root)? */
	public static function htaccess_writable() {
		$p = self::htaccess_path();
		return file_exists( $p ) ? is_writable( $p ) : is_writable( ABSPATH );
	}

	/** The snapshot taken when the editor was unlocked (null = none yet). */
	public static function htaccess_snapshot() {
		$s = get_option( self::HTACCESS_SNAPSHOT, null );
		return ( null === $s || false === $s ) ? null : $s;
	}

	/** Capture a snapshot of the current file so Reset can revert to it. Called on unlock. */
	public static function htaccess_unlock() {
		update_option( self::HTACCESS_SNAPSHOT, self::htaccess_content(), false );
		return array( 'ok' => true, 'snapshot' => true );
	}

	/**
	 * Write new contents to .htaccess. Refuses an empty file (that would 500 the
	 * whole site) and snapshots the existing file first if we somehow have none.
	 */
	public static function htaccess_save( $content ) {
		$content = (string) $content;
		if ( '' === trim( $content ) ) {
			return array( 'ok' => false, 'message' => 'Refusing to write an empty .htaccess — that would take your site down. Use Reset to default instead.' );
		}
		if ( ! self::htaccess_writable() ) {
			return array( 'ok' => false, 'message' => 'The .htaccess file is not writable — check its file permissions.' );
		}
		if ( null === self::htaccess_snapshot() ) {
			update_option( self::HTACCESS_SNAPSHOT, self::htaccess_content(), false );
		}
		$content = str_replace( "\r\n", "\n", $content );
		$ok = @file_put_contents( self::htaccess_path(), rtrim( $content, "\n" ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $ok ) {
			return array( 'ok' => false, 'message' => 'Could not write .htaccess — check file permissions.' );
		}
		return array( 'ok' => true );
	}

	/** Revert .htaccess to the snapshot captured when the editor was unlocked. */
	public static function htaccess_reset() {
		$snap = self::htaccess_snapshot();
		if ( null === $snap ) {
			return array( 'ok' => false, 'message' => 'There is no snapshot to reset to yet.' );
		}
		if ( ! self::htaccess_writable() ) {
			return array( 'ok' => false, 'message' => 'The .htaccess file is not writable — check its file permissions.' );
		}
		$ok = @file_put_contents( self::htaccess_path(), $snap ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $ok ) {
			return array( 'ok' => false, 'message' => 'Could not restore .htaccess — check file permissions.' );
		}
		return array( 'ok' => true, 'content' => $snap );
	}
}
