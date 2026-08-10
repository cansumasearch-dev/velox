<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Velox Builder — Overview.
 *
 * The landing screen of the Builder section. Foundation version: shows the
 * stat header, quick actions, and a "recently edited" list. The list is empty
 * until documents exist; the engine + persistence layer fill it later.
 */
$new_url = Velox_Builder::edit_url();
?>
<div class="vba-shell">
	<header class="vba-head">
		<div>
			<h1><?php esc_html_e( 'Overview', 'velox' ); ?></h1>
			<p><?php esc_html_e( 'Your Velox Builder site at a glance.', 'velox' ); ?></p>
		</div>
		<div class="vba-head-actions">
			<a class="vba-btn vba-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=velox-builder-settings' ) ); ?>">
				<?php echo Velox_Admin::icon( 'gear', 15 ); // phpcs:ignore ?> <?php esc_html_e( 'Settings', 'velox' ); ?>
			</a>
			<a class="vba-btn vba-btn-primary" href="<?php echo esc_url( $new_url ); ?>">
				<?php echo Velox_Admin::icon( 'plug', 15 ); // phpcs:ignore ?> <?php esc_html_e( 'Open editor', 'velox' ); ?>
			</a>
		</div>
	</header>

	<div class="vba-stats">
		<div class="vba-stat"><span class="vba-stat-ic"><?php echo Velox_Admin::icon( 'bolt', 17 ); // phpcs:ignore ?></span><span class="vba-stat-v">—</span><span class="vba-stat-l"><?php esc_html_e( 'Avg PageSpeed', 'velox' ); ?></span></div>
		<div class="vba-stat"><span class="vba-stat-ic"><?php echo Velox_Admin::icon( 'file', 17 ); // phpcs:ignore ?></span><span class="vba-stat-v">0</span><span class="vba-stat-l"><?php esc_html_e( 'Templates', 'velox' ); ?></span></div>
		<div class="vba-stat"><span class="vba-stat-ic"><?php echo Velox_Admin::icon( 'globe', 17 ); // phpcs:ignore ?></span><span class="vba-stat-v">0</span><span class="vba-stat-l"><?php esc_html_e( 'Reusables', 'velox' ); ?></span></div>
		<div class="vba-stat"><span class="vba-stat-ic"><?php echo Velox_Admin::icon( 'tag', 17 ); // phpcs:ignore ?></span><span class="vba-stat-v">0</span><span class="vba-stat-l"><?php esc_html_e( 'Global classes', 'velox' ); ?></span></div>
	</div>

	<div class="vba-card">
		<div class="vba-card-h">
			<div><h2><?php esc_html_e( 'Recently edited', 'velox' ); ?></h2><p><?php esc_html_e( 'Jump back into what you were working on.', 'velox' ); ?></p></div>
			<a class="vba-btn vba-btn-primary vba-btn-sm" href="<?php echo esc_url( $new_url ); ?>"><?php echo Velox_Admin::icon( 'plug', 14 ); // phpcs:ignore ?> <?php esc_html_e( 'New page', 'velox' ); ?></a>
		</div>
		<div class="vba-empty">
			<span class="vba-empty-ic"><?php echo Velox_Admin::icon( 'grid', 24 ); // phpcs:ignore ?></span>
			<b><?php esc_html_e( 'Nothing built yet', 'velox' ); ?></b>
			<p><?php esc_html_e( 'Open the editor to build your first page with Velox Builder.', 'velox' ); ?></p>
			<a class="vba-btn vba-btn-primary" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Open the editor', 'velox' ); ?></a>
		</div>
	</div>
</div>
