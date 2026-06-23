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
		// robots.txt (virtual — only when no physical file shadows it).
		add_filter( 'robots_txt', array( __CLASS__, 'filter_robots' ), 20, 2 );

		// <head> output.
		add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_title' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'head_tags' ), 1 );

		// Editor meta box + save.
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta' ), 10, 1 );
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
		$noindex = get_post_meta( $id, '_velox_seo_noindex', true );

		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $desc ) ) . '">' . "\n";
		}
		if ( '1' === (string) $noindex ) {
			echo '<meta name="robots" content="noindex,follow">' . "\n";
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
		$noindex = get_post_meta( $post->ID, '_velox_seo_noindex', true );
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
			<p>
				<label><input type="checkbox" name="velox_seo_noindex" value="1" <?php checked( '1', (string) $noindex ); ?>> Noindex this page <span style="color:#646970">(hide from search engines)</span></label><br>
				<label><input type="checkbox" name="sitemap_exclude" value="1" <?php checked( '1', (string) $exclude ); ?>> Exclude from sitemap</label>
			</p>
		</div>
		<style>
			.velox-seo-preview{border:1px solid #dcdcde;border-radius:8px;padding:12px 14px;margin-bottom:14px;background:#fff}
			.velox-seo-preview-url{color:#202124;font-size:12px;margin-bottom:2px}
			.velox-seo-preview-title{color:#1a0dab;font-size:18px;line-height:1.3;overflow:hidden}
			.velox-seo-preview-desc{color:#4d5156;font-size:13px;line-height:1.5;margin-top:2px}
			.velox-seo-count{font-size:11px;color:#646970}
			.velox-seo-box label{font-size:13px}
		</style>
		<script>
		(function(){
			var t=document.getElementById('velox-seo-title'),d=document.getElementById('velox-seo-desc');
			var pt=document.getElementById('velox-seo-pv-title'),pd=document.getElementById('velox-seo-pv-desc');
			var ct=document.getElementById('velox-seo-title-count'),cd=document.getElementById('velox-seo-desc-count');
			var fbT=<?php echo wp_json_encode( $fallback_title ); ?>;
			function upd(){
				if(pt)pt.textContent=t.value||fbT;
				if(pd)pd.textContent=d.value||'Add a meta description to control how this page looks in Google.';
				if(ct)ct.textContent=t.value.length+' chars'+(t.value.length>60?' — a bit long':'');
				if(cd)cd.textContent=d.value.length+' chars'+(d.value.length>160?' — a bit long':'');
			}
			if(t&&d){t.addEventListener('input',upd);d.addEventListener('input',upd);upd();}
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
		update_post_meta( $post_id, '_velox_seo_noindex', isset( $_POST['velox_seo_noindex'] ) ? '1' : '0' );
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
		return array( 'ok' => true, 'physical' => self::physical_robots_exists() );
	}
}
