<?php
/**
 * SEO health: counts, issues, and the exact pages behind each one.
 *
 * Everything here reads the database directly, so a scan is instant and needs no
 * crawling. Checks that need rendered HTML (missing H1, broken internal links)
 * are intentionally not here yet — those will reuse the media scanner's crawl
 * rather than adding a second one.
 *
 * @package Velox
 */

defined( 'ABSPATH' ) || exit;

class Velox_SEO_Health {

	const TITLE_MAX = 60;

	/** Public post types Velox manages SEO for, plus anything else public. */
	private static function post_types() {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );
		foreach ( Velox_MediaScan::builder_types() as $t ) {
			unset( $types[ $t ] );
		}
		return array_values( $types );
	}

	public static function scan() {
		$posts = get_posts( array(
			'post_type'      => self::post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => 2000,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$total    = count( $posts );
		$noindex  = array();
		$no_desc  = array();
		$long     = array();
		$titles   = array();
		$dupes    = array();

		foreach ( $posts as $p ) {
			$desc  = trim( (string) get_post_meta( $p->ID, '_velox_seo_desc', true ) );
			$stitle = trim( (string) get_post_meta( $p->ID, '_velox_seo_title', true ) );
			$ni    = get_post_meta( $p->ID, '_velox_seo_noindex', true );
			$eff   = '' !== $stitle ? $stitle : $p->post_title;

			$row = self::row( $p );

			if ( $ni && '0' !== (string) $ni ) {
				$noindex[] = $row;
			}
			if ( '' === $desc ) {
				$no_desc[] = $row;
			}
			if ( mb_strlen( $eff ) > self::TITLE_MAX ) {
				$long[] = $row + array( 'note' => mb_strlen( $eff ) . ' characters' );
			}
			$key = mb_strtolower( trim( $eff ) );
			if ( '' !== $key ) {
				if ( isset( $titles[ $key ] ) ) {
					$dupes[ $key ][] = $row;
					if ( 1 === count( $dupes[ $key ] ) ) {
						array_unshift( $dupes[ $key ], $titles[ $key ] );
					}
				} else {
					$titles[ $key ] = $row;
				}
			}
		}

		$dupe_rows = array();
		foreach ( $dupes as $set ) {
			foreach ( $set as $r ) {
				$dupe_rows[] = $r;
			}
		}

		$alt = self::images_without_alt();

		$issues = array(
			self::issue( 'desc', 'bad', 'Pages with no meta description',
				'Google writes its own snippet instead of yours', $no_desc ),
			self::issue( 'alt', 'bad', 'Images with no alt text',
				'Hurts accessibility and image search', $alt['rows'], $alt['count'], 'media' ),
			self::issue( 'long', 'warn', 'Titles longer than ' . self::TITLE_MAX . ' characters',
				'Will be cut off in search results', $long ),
			self::issue( 'dupe', 'warn', 'Duplicate titles',
				'Two pages compete for the same search', $dupe_rows ),
			self::issue( 'noindex', 'warn', 'Pages set to noindex',
				'Deliberate? Worth confirming none are by accident', $noindex ),
		);

		return array(
			'stats' => array(
				'total'     => $total,
				'indexable' => $total - count( $noindex ),
				'noindex'   => count( $noindex ),
				'with_desc' => $total - count( $no_desc ),
				'no_desc'   => count( $no_desc ),
			),
			'issues'  => $issues,
			'scanned' => time(),
		);
	}

	private static function row( $p ) {
		return array(
			'id'    => (int) $p->ID,
			'title' => $p->post_title ? $p->post_title : ( '#' . $p->ID ),
			'url'   => get_permalink( $p->ID ),
			'path'  => wp_make_link_relative( (string) get_permalink( $p->ID ) ),
			'edit'  => get_edit_post_link( $p->ID, 'raw' ),
			'note'  => '',
		);
	}

	private static function issue( $key, $level, $label, $desc, $rows, $count = null, $goto = '' ) {
		return array(
			'key'   => $key,
			'level' => empty( $rows ) && ! $count ? 'ok' : $level,
			'label' => $label,
			'desc'  => $desc,
			'count' => null === $count ? count( $rows ) : (int) $count,
			'goto'  => $goto,
			'rows'  => array_slice( $rows, 0, 200 ),
		);
	}

	/** Attachments with an empty alt — the Media Editor is where these get fixed. */
	private static function images_without_alt() {
		global $wpdb;
		$ids = $wpdb->get_col(
			"SELECT p.ID FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} m
			   ON m.post_id = p.ID AND m.meta_key = '_wp_attachment_image_alt'
			 WHERE p.post_type = 'attachment'
			   AND p.post_mime_type LIKE 'image/%'
			   AND p.post_mime_type <> 'image/svg+xml'
			   AND ( m.meta_value IS NULL OR TRIM(m.meta_value) = '' )
			 ORDER BY p.ID DESC LIMIT 200"
		);
		$rows = array();
		foreach ( (array) $ids as $id ) {
			$file   = get_post_meta( (int) $id, '_wp_attached_file', true );
			$rows[] = array(
				'id'    => (int) $id,
				'title' => get_the_title( (int) $id ),
				'url'   => wp_get_attachment_url( (int) $id ),
				'path'  => $file ? wp_basename( $file ) : '',
				'edit'  => get_edit_post_link( (int) $id, 'raw' ),
				'note'  => '',
			);
		}
		// Count separately: the list is capped but the number should be honest.
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} m
			   ON m.post_id = p.ID AND m.meta_key = '_wp_attachment_image_alt'
			 WHERE p.post_type = 'attachment'
			   AND p.post_mime_type LIKE 'image/%'
			   AND p.post_mime_type <> 'image/svg+xml'
			   AND ( m.meta_value IS NULL OR TRIM(m.meta_value) = '' )"
		);
		return array( 'rows' => $rows, 'count' => $count );
	}
}
