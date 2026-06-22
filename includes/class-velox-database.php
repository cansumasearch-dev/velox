<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database cleanup. Each item is counted before it's cleaned, runs only on an
 * explicit button press (or an optional weekly cron), and never touches anything
 * outside the standard WordPress junk tables.
 */
class Velox_Database {

	public function __construct() {
		if ( Velox_Settings::get( 'db_schedule_cleanup' ) ) {
			add_action( 'velox_weekly_cleanup', array( $this, 'run_all' ) );
			if ( ! wp_next_scheduled( 'velox_weekly_cleanup' ) ) {
				wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'velox_weekly_cleanup' );
			}
		} else {
			$ts = wp_next_scheduled( 'velox_weekly_cleanup' );
			if ( $ts ) {
				wp_unschedule_event( $ts, 'velox_weekly_cleanup' );
			}
		}
	}

	public function counts() {
		global $wpdb;
		return array(
			'revisions'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" ),
			'auto_drafts'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" ),
			'trashed_posts'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" ),
			'spam_comments'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" ),
			'trashed_comments'=> (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" ),
			'expired_transients' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%'" ),
			'orphan_postmeta' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" ),
		);
	}

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

			case 'orphan_postmeta':
				$wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" );
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
		foreach ( array( 'revisions', 'auto_drafts', 'trashed_posts', 'spam_comments', 'trashed_comments', 'expired_transients', 'orphan_postmeta' ) as $item ) {
			$total += (int) $this->clean( $item );
		}
		return $total;
	}
}

// Make sure a weekly schedule exists.
add_filter( 'cron_schedules', function ( $schedules ) {
	if ( ! isset( $schedules['weekly'] ) ) {
		$schedules['weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once Weekly', 'velox' ),
		);
	}
	return $schedules;
} );
