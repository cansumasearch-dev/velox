<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$items = array(
	'revisions'         => array( 'Post revisions', 'Old saved versions of posts and pages.' ),
	'auto_drafts'       => array( 'Auto-drafts', 'Abandoned drafts WordPress created automatically.' ),
	'trashed_posts'     => array( 'Trashed posts', 'Posts and pages still sitting in the trash.' ),
	'spam_comments'     => array( 'Spam comments', 'Comments marked as spam.' ),
	'trashed_comments'  => array( 'Trashed comments', 'Comments in the trash.' ),
	'unapproved_comments' => array( 'Unapproved comments', 'Pending comments awaiting moderation.' ),
	'pingbacks'         => array( 'Pingbacks & trackbacks', 'Link notifications from other sites — rarely useful.' ),
	'expired_transients'=> array( 'Expired transients', 'Cached values past their expiry.' ),
	'all_transients'    => array( 'All transients', 'Every cached transient (safe — they regenerate when needed).' ),
	'orphan_postmeta'   => array( 'Orphaned post meta', 'Meta rows whose post no longer exists.' ),
	'orphan_commentmeta'=> array( 'Orphaned comment meta', 'Meta rows whose comment no longer exists.' ),
);
?>
<div class="velox-page-head">
	<h1 class="velox-h2"><?php esc_html_e('Database', 'velox'); ?></h1>
	<p class="velox-sub"><?php esc_html_e('Counts are live. Nothing is deleted until you press a button.', 'velox'); ?></p>
</div>

<div class="velox-alert velox-alert--info">
	<?php esc_html_e('Take a database backup in Plesk before a big cleanup the first time. After that it\'s routine — revisions and transients pile up fast on builder sites.', 'velox'); ?>
</div>

<div class="velox-panel">
	<h3 class="velox-panel-title"><?php esc_html_e('Cleanup', 'velox'); ?></h3>
	<div class="velox-db-list" id="velox-db-list">
		<?php foreach ( $items as $key => $meta ) : ?>
			<div class="velox-db-row" data-item="<?php echo esc_attr( $key ); ?>">
				<div class="velox-db-meta">
					<span class="velox-db-label"><?php echo esc_html( $meta[0] ); ?></span>
					<span class="velox-db-desc"><?php echo esc_html( $meta[1] ); ?></span>
				</div>
				<span class="velox-db-count" data-count="<?php echo esc_attr( $key ); ?>">—</span>
				<button class="velox-btn velox-btn--ghost velox-db-clean" data-item="<?php echo esc_attr( $key ); ?>"><?php esc_html_e('Clean', 'velox'); ?></button>
			</div>
		<?php endforeach; ?>
	</div>
	<div class="velox-actions">
		<button class="velox-btn velox-btn--primary" id="velox-db-all"><?php esc_html_e('Clean everything', 'velox'); ?></button>
		<button class="velox-btn velox-btn--ghost" id="velox-db-optimize"><?php esc_html_e('Optimize tables', 'velox'); ?></button>
	</div>
</div>

<div class="velox-panel">
	<h3 class="velox-panel-title"><?php esc_html_e('Automation', 'velox'); ?></h3>
	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label"><?php esc_html_e('Weekly auto-clean', 'velox'); ?></span>
			<span class="velox-toggle-desc"><?php esc_html_e('Runs the cleanup (not table optimization) once a week via WP-Cron.', 'velox'); ?></span>
		</div>
		<label class="velox-switch">
			<input type="checkbox" data-setting="db_schedule_cleanup" id="velox-db-schedule" <?php checked( Velox_Settings::get( 'db_schedule_cleanup' ) ); ?>>
			<span class="velox-switch-track"></span>
		</label>
	</div>
	<div class="velox-actions">
		<button class="velox-btn velox-btn--primary" id="velox-db-save-auto"><?php esc_html_e('Save automation', 'velox'); ?></button>
	</div>
</div>
