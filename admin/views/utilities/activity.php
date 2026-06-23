<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$on      = Velox_Settings::get( 'util_activity', false );
$filter  = isset( $_GET['action_filter'] ) ? sanitize_key( wp_unslash( $_GET['action_filter'] ) ) : '';
$events  = $on ? Velox_Activity::list_events( $filter ) : array();
$present = $on ? Velox_Activity::actions_present() : array();

// colour group per action for the little dot
$tone = array(
	'login' => 'ok', 'post_publish' => 'ok', 'user_register' => 'ok',
	'login_failed' => 'bad', 'post_trash' => 'bad', 'user_delete' => 'bad', 'plugin_deactivate' => 'bad',
);
$base = admin_url( 'admin.php?page=velox-utilities&tool=activity' );
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Activity log</h1>
	<p class="velox-sub">A running record of who did what — logins, content changes, plugin and theme changes, user changes and updates. Handy for client sites and shared logins.</p>
</div>

<div class="velox-panel">
	<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
		<label class="velox-inline-toggle">
			<span><strong>Record activity</strong></span>
			<span class="velox-switch"><input type="checkbox" data-setting="util_activity" id="velox-activity-toggle" <?php checked( $on ); ?>><span class="velox-switch-track"></span></span>
		</label>
		<?php if ( $on && ! empty( $events ) ) : ?>
			<button class="velox-btn velox-btn--ghost" id="velox-activity-clear">Clear log</button>
		<?php endif; ?>
	</div>

	<?php if ( ! $on ) : ?>
		<p class="velox-hint" style="margin-top:14px;">Logging is off. Flip it on to start recording events from this point forward.</p>
	<?php else : ?>
		<?php if ( ! empty( $present ) ) : ?>
			<div class="velox-activity-filters">
				<a class="velox-chip<?php echo '' === $filter ? ' is-active' : ''; ?>" href="<?php echo esc_url( $base ); ?>">All</a>
				<?php foreach ( $present as $a ) : ?>
					<a class="velox-chip<?php echo $filter === $a ? ' is-active' : ''; ?>" href="<?php echo esc_url( $base . '&action_filter=' . $a ); ?>"><?php echo esc_html( Velox_Activity::label( $a ) ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="velox-activity-list">
			<?php if ( empty( $events ) ) : ?>
				<p class="velox-hint" style="margin-top:14px;">Nothing logged yet<?php echo $filter ? ' for this filter' : ''; ?>.</p>
			<?php else : ?>
				<?php foreach ( $events as $e ) : ?>
					<?php $t = isset( $tone[ $e['action'] ] ) ? $tone[ $e['action'] ] : 'neutral'; ?>
					<div class="velox-activity-row">
						<span class="velox-activity-dot is-<?php echo esc_attr( $t ); ?>"></span>
						<span class="velox-activity-act"><?php echo esc_html( Velox_Activity::label( $e['action'] ) ); ?></span>
						<span class="velox-activity-obj"><?php echo esc_html( $e['object'] ); ?><?php echo $e['detail'] ? ' <em>(' . esc_html( $e['detail'] ) . ')</em>' : ''; ?></span>
						<span class="velox-activity-who"><?php echo esc_html( $e['user_name'] ); ?></span>
						<span class="velox-activity-when"><?php echo esc_html( human_time_diff( strtotime( $e['created'] ), current_time( 'timestamp' ) ) ); ?> ago</span>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
