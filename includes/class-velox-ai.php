<?php
/**
 * Velox AI — control over AI crawlers and an llms.txt for AI systems.
 *
 * Two things, both part of the SEO module:
 *
 *  1. AI-crawler rules. Instead of one blunt "block all AI" dump in robots.txt,
 *     the bots are grouped by what they DO — training crawlers, AI-search
 *     crawlers, and on-demand user fetchers — and each group can be allowed or
 *     blocked. The chosen Disallow rules are appended to the robots.txt output.
 *
 *  2. llms.txt. A virtual /llms.txt (same approach as the virtual robots.txt)
 *     that lists the site and its key pages in the Markdown-ish format AI tools
 *     look for. Note: Google has said it ignores llms.txt, so this is for the AI
 *     systems that DO read it, not a Google-ranking play.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_Ai {

	/**
	 * The bot families we know about, grouped by purpose. Each group maps to a
	 * setting key; when that group is set to "block", every user-agent in it gets
	 * a Disallow in robots.txt.
	 */
	public static function bot_groups() {
		return array(
			'training' => array(
				'label'   => __( 'AI training crawlers', 'velox' ),
				'blurb'   => __( 'Collect content to train AI models. Block these if you don’t want your content used as training data.', 'velox' ),
				'setting' => 'seo_ai_block_training',
				'agents'  => array( 'GPTBot', 'ClaudeBot', 'anthropic-ai', 'CCBot', 'Google-Extended', 'Applebot-Extended', 'Meta-ExternalAgent', 'Bytespider', 'Amazonbot', 'cohere-ai', 'Diffbot', 'omgili' ),
			),
			'search' => array(
				'label'   => __( 'AI search crawlers', 'velox' ),
				'blurb'   => __( 'Index your site so it can appear in AI answers (ChatGPT Search, Perplexity, etc.). Most sites want these allowed for visibility.', 'velox' ),
				'setting' => 'seo_ai_block_search',
				'agents'  => array( 'OAI-SearchBot', 'PerplexityBot', 'ChatGPT-User', 'Perplexity-User', 'Google-CloudVertexBot' ),
			),
			'assistants' => array(
				'label'   => __( 'On-demand fetchers', 'velox' ),
				'blurb'   => __( 'Fetch a page only when a user pastes or asks about its link. Blocking these can stop your pages being summarised on request.', 'velox' ),
				'setting' => 'seo_ai_block_assistants',
				'agents'  => array( 'ChatGPT-User', 'Perplexity-User', 'Claude-User', 'Claude-Web' ),
			),
		);
	}

	public static function init() {
		if ( ! Velox_Settings::get( 'module_seo', true ) ) {
			return;
		}
		// Append AI rules to robots.txt (after Velox's own robots output).
		add_filter( 'robots_txt', array( __CLASS__, 'append_ai_rules' ), PHP_INT_MAX - 1, 2 );

		// Virtual /llms.txt.
		if ( Velox_Settings::get( 'seo_llms_enable', false ) ) {
			add_action( 'init', array( __CLASS__, 'add_rewrite' ) );
			add_filter( 'query_vars', array( __CLASS__, 'add_query_var' ) );
			add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_llms' ) );
		}
	}

	/* ---------------------------------------------------------- robots rules */

	/**
	 * Build the AI Disallow block from the group toggles. Returns '' when nothing
	 * is blocked, so we never add noise to robots.txt for the common allow-all case.
	 */
	public static function ai_rules_block() {
		$blocked = array();
		foreach ( self::bot_groups() as $g ) {
			if ( Velox_Settings::get( $g['setting'], false ) ) {
				foreach ( $g['agents'] as $ua ) {
					$blocked[ $ua ] = true; // de-dupe agents shared across groups
				}
			}
		}
		if ( empty( $blocked ) ) {
			return '';
		}
		$lines = array( '', '# AI crawlers — managed by Velox' );
		foreach ( array_keys( $blocked ) as $ua ) {
			$lines[] = 'User-agent: ' . $ua;
		}
		$lines[] = 'Disallow: /';
		return implode( "\n", $lines );
	}

	public static function append_ai_rules( $output, $public ) {
		if ( '0' === (string) $public ) {
			return $output;
		}
		$block = self::ai_rules_block();
		if ( '' === $block ) {
			return $output;
		}
		return rtrim( $output, "\n" ) . "\n" . $block . "\n";
	}

	/* --------------------------------------------------------------- llms.txt */

	public static function add_rewrite() {
		add_rewrite_rule( '^llms\.txt$', 'index.php?velox_llms=1', 'top' );
	}

	public static function add_query_var( $vars ) {
		$vars[] = 'velox_llms';
		return $vars;
	}

	public static function maybe_serve_llms() {
		if ( ! get_query_var( 'velox_llms' ) ) {
			return;
		}
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo self::llms_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Build the llms.txt body. Uses a custom saved version if the user edited one;
	 * otherwise generates from the site: title, tagline, then a list of the most
	 * relevant pages (front page, then recent pages/posts) as Markdown links.
	 */
	public static function llms_content() {
		$custom = (string) Velox_Settings::get( 'seo_llms_content', '' );
		if ( '' !== trim( $custom ) ) {
			return $custom;
		}
		return self::generate_llms();
	}

	public static function generate_llms() {
		$name    = get_bloginfo( 'name' );
		$tagline = get_bloginfo( 'description' );

		$out   = array();
		$out[] = '# ' . $name;
		if ( $tagline ) {
			$out[] = '';
			$out[] = '> ' . $tagline;
		}
		$out[] = '';
		$out[] = '## Pages';

		// Key pages: front page first, then published pages by menu order/title.
		$ids   = array();
		$front = (int) get_option( 'page_on_front' );
		if ( $front ) {
			$ids[] = $front;
		}
		$pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 40,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'exclude'        => $ids,
		) );
		foreach ( array_merge( $front ? array( get_post( $front ) ) : array(), $pages ) as $p ) {
			if ( ! $p ) {
				continue;
			}
			if ( get_post_meta( $p->ID, '_velox_seo_noindex', true ) || get_post_meta( $p->ID, 'sitemap_exclude', true ) ) {
				continue; // respect the site's own hide-from-search choices
			}
			$desc = self::page_summary( $p );
			$out[] = '- [' . self::clean( get_the_title( $p ) ) . '](' . get_permalink( $p ) . ')' . ( $desc ? ': ' . $desc : '' );
		}

		// Recent posts, if any.
		$posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
		) );
		if ( $posts ) {
			$out[] = '';
			$out[] = '## Posts';
			foreach ( $posts as $p ) {
				if ( get_post_meta( $p->ID, '_velox_seo_noindex', true ) ) {
					continue;
				}
				$desc = self::page_summary( $p );
				$out[] = '- [' . self::clean( get_the_title( $p ) ) . '](' . get_permalink( $p ) . ')' . ( $desc ? ': ' . $desc : '' );
			}
		}

		return implode( "\n", $out ) . "\n";
	}

	/** A one-line summary for a page: its SEO description, else its excerpt. */
	private static function page_summary( $p ) {
		$d = (string) get_post_meta( $p->ID, '_velox_seo_desc', true );
		if ( '' === trim( $d ) ) {
			$d = has_excerpt( $p ) ? get_the_excerpt( $p ) : '';
		}
		$d = self::clean( $d );
		return strlen( $d ) > 160 ? substr( $d, 0, 159 ) . '…' : $d;
	}

	private static function clean( $s ) {
		return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $s ) ) );
	}
}
