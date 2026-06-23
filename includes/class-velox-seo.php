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
		add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_title' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'head_tags' ), 1 );

		// Editor meta box + save.
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta' ), 10, 1 );
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
		// Only emit a robots tag when it restricts something — index,follow is the default.
		if ( $noindex || $nofollow ) {
			$bits = array( $noindex ? 'noindex' : 'index', $nofollow ? 'nofollow' : 'follow' );
			echo '<meta name="robots" content="' . esc_attr( implode( ',', $bits ) ) . '">' . "\n";
		}
	}

	/* ------------------------------------------------------------- meta box */

	public static function add_meta_box() {
		if ( ! Velox_Settings::get( 'module_seo', true ) ) {
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

		self::generate_sitemap();
	}

	/* ------------------------------------------------------------- sitemap */

	/** Mirrors the agency's custom generator: home first, then post/page/product. */
	public static function generate_sitemap() {
		if ( ! Velox_Settings::get( 'module_seo', true ) || ! Velox_Settings::get( 'seo_sitemap_enable', true ) ) {
			return false;
		}
		$homepage_id  = (int) get_option( 'page_on_front' );
		$homepage_url = trailingslashit( home_url( '/' ) );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

		if ( $homepage_id && 'publish' === get_post_status( $homepage_id ) && '1' !== (string) get_post_meta( $homepage_id, 'sitemap_exclude', true ) ) {
			$xml .= self::sitemap_url( $homepage_url, get_the_modified_date( 'c', $homepage_id ) );
		}

		$q = new WP_Query( array(
			'post_type'      => self::POST_TYPES,
			'posts_per_page' => -1,
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
				if ( $url === $homepage_url ) {
					continue;
				}
				if ( '1' === (string) get_post_meta( $pid, 'sitemap_exclude', true ) ) {
					continue;
				}
				if ( '1' === (string) get_post_meta( $pid, '_velox_seo_noindex', true ) ) {
					continue; // noindex pages don't belong in the sitemap
				}
				$xml .= self::sitemap_url( $url, get_the_modified_date( 'c' ) );
			}
			wp_reset_postdata();
		}
		$xml .= '</urlset>';

		return (bool) @file_put_contents( ABSPATH . 'sitemap.xml', $xml );
	}

	private static function sitemap_url( $loc, $lastmod ) {
		$out  = '  <url>' . PHP_EOL;
		$out .= '    <loc>' . esc_url( $loc ) . '</loc>' . PHP_EOL;
		if ( $lastmod ) {
			$out .= '    <lastmod>' . esc_html( $lastmod ) . '</lastmod>' . PHP_EOL;
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
}
