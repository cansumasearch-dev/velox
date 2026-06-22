<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database cleanup. Every item is counted live and only removed when the user
 * presses a button (or via the optional weekly schedule). Queries mirror the
 * safe, well-established patterns used by WP-Optimize / Advanced DB Cleaner.
 */
class Velox_Database {

	public function __construct() {
		if ( Velox_Settings::get( 'db_schedule_cleanup' ) ) {
			add_action( 'velox_weekly_cleanup', array( $this, 'run_all' ) );
			add_filter( 'cron_schedules', array( $this, 'weekly_schedule' ) );
			if ( ! wp_next_scheduled( 'velox_weekly_cleanup' ) ) {
				wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'velox_weekly_cleanup' );
			}
		}
	}

	public function weekly_schedule( $schedules ) {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array( 'interval' => WEEK_IN_SECONDS, 'display' => __( 'Once Weekly', 'velox' ) );
		}
		return $schedules;
	}

	/** Live counts for every cleanup item. */
	public static function counts() {
		global $wpdb;
		return array(
			'revisions'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" ),
			'auto_drafts'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" ),
			'trashed_posts'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" ),
			'spam_comments'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" ),
			'trashed_comments'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" ),
			'unapproved_comments' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = '0'" ),
			'pingbacks'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type IN ('pingback','trackback')" ),
			'expired_transients'=> (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_%' AND option_value < UNIX_TIMESTAMP()" ),
			'all_transients'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%' OR option_name LIKE '\_site\_transient\_%'" ),
			'orphan_postmeta'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" ),
			'orphan_commentmeta'=> (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_ID IS NULL" ),
		);
	}

	/** Run a single cleanup item, returning the number of rows affected. */
	public function clean( $item ) {
		global $wpdb;
		switch ( $item ) {
			case 'revisions':
				$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision'" );
				foreach ( $ids as $id ) {
					wp_delete_post_revision( $id );
				}
				return count( $ids );

			case 'auto_drafts':
				$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
				foreach ( $ids as $id ) {
					wp_delete_post( $id, true );
				}
				return count( $ids );

			case 'trashed_posts':
				$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash'" );
				foreach ( $ids as $id ) {
					wp_delete_post( $id, true );
				}
				return count( $ids );

			case 'spam_comments':
				$ids = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );
				foreach ( $ids as $id ) {
					wp_delete_comment( $id, true );
				}
				return count( $ids );

			case 'trashed_comments':
				$ids = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );
				foreach ( $ids as $id ) {
					wp_delete_comment( $id, true );
				}
				return count( $ids );

			case 'unapproved_comments':
				$ids = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = '0'" );
				foreach ( $ids as $id ) {
					wp_delete_comment( $id, true );
				}
				return count( $ids );

			case 'pingbacks':
				$ids = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_type IN ('pingback','trackback')" );
				foreach ( $ids as $id ) {
					wp_delete_comment( $id, true );
				}
				return count( $ids );

			case 'expired_transients':
				$count = 0;
				$timeouts = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_%'" );
				$now = time();
				foreach ( $timeouts as $t ) {
					if ( (int) $t->option_value < $now ) {
						$name = str_replace( '_transient_timeout_', '', $t->option_name );
						delete_transient( $name );
						$count++;
					}
				}
				return $count;

			case 'all_transients':
				$names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%' AND option_name NOT LIKE '\_transient\_timeout\_%'" );
				foreach ( $names as $n ) {
					delete_transient( str_replace( '_transient_', '', $n ) );
				}
				$site = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\_site\_transient\_%' AND option_name NOT LIKE '\_site\_transient\_timeout\_%'" );
				foreach ( $site as $n ) {
					delete_site_transient( str_replace( '_site_transient_', '', $n ) );
				}
				return count( $names ) + count( $site );

			case 'orphan_postmeta':
				$wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" );
				return (int) $wpdb->rows_affected;

			case 'orphan_commentmeta':
				$wpdb->query( "DELETE cm FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_ID IS NULL" );
				return (int) $wpdb->rows_affected;

			case 'optimize_tables':
				$tables = $wpdb->get_col( 'SHOW TABLES' );
				foreach ( $tables as $table ) {
					$wpdb->query( "OPTIMIZE TABLE `{$table}`" );
				}
				return count( $tables );

			case 'all':
				return $this->run_all();
		}
		return 0;
	}

	public function run_all() {
		$total = 0;
		foreach ( array( 'revisions', 'auto_drafts', 'trashed_posts', 'spam_comments', 'trashed_comments', 'unapproved_comments', 'pingbacks', 'expired_transients', 'orphan_postmeta', 'orphan_commentmeta' ) as $item ) {
			$total += (int) $this->clean( $item );
		}
		return $total;
	}
}
