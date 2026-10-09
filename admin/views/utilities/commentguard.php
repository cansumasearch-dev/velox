<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s  = Velox_Settings::all();
$on = ! empty( $s['util_commentguard'] );
$st = Velox_Comment_Guard::stats();

/** One switch row: label, one line of why, the switch. */
$cg_switch = function ( $key, $label, $desc ) use ( $s ) {
	?>
	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label"><?php echo esc_html( $label ); ?></span>
			<span class="velox-toggle-desc"><?php echo esc_html( $desc ); ?></span>
		</div>
		<label class="velox-switch"><input type="checkbox" data-setting="<?php echo esc_attr( $key ); ?>" <?php checked( ! empty( $s[ $key ] ) ); ?>><span class="velox-switch-track"></span></label>
	</div>
	<?php
};
?>
<div class="velox-page-head">
	<h1 class="velox-h2"><?php esc_html_e( 'Comment protection', 'velox' ); ?></h1>
	<p class="velox-sub"><?php esc_html_e( 'Stops spam comments before they reach you, and makes sure a link someone posts in a comment can never be clicked by accident — on your site, in the dashboard or in the notification email.', 'velox' ); ?></p>
</div>

<div class="velox-pf-status vxcg-stats" id="vxcg-stats">
	<div class="velox-pf-stat">
		<span class="k"><?php esc_html_e( 'Spam bots stopped', 'velox' ); ?></span>
		<span class="v"><?php echo esc_html( number_format_i18n( $st['blocked'] ) ); ?>
			<?php if ( $st['blocked_week'] ) : ?><small class="vxcg-sub"><?php printf( esc_html__( '%s this week', 'velox' ), esc_html( number_format_i18n( $st['blocked_week'] ) ) ); ?></small><?php endif; ?>
		</span>
	</div>
	<div class="velox-pf-stat"><span class="k"><?php esc_html_e( 'In the spam folder', 'velox' ); ?></span><span class="v" data-cg-stat="spam"><?php echo esc_html( number_format_i18n( $st['spam'] ) ); ?></span></div>
	<div class="velox-pf-stat"><span class="k"><?php esc_html_e( 'Waiting for approval', 'velox' ); ?></span><span class="v" data-cg-stat="pending"><?php echo esc_html( number_format_i18n( $st['pending'] ) ); ?></span></div>
</div>

<div class="velox-grid-2">
	<div class="velox-panel">
		<div class="velox-panel-head">
			<h3 class="velox-panel-title"><?php esc_html_e( 'Protection', 'velox' ); ?></h3>
			<label class="velox-switch" title="<?php esc_attr_e( 'Comment protection on/off', 'velox' ); ?>">
				<input type="checkbox" data-setting="util_commentguard" <?php checked( $on ); ?>>
				<span class="velox-switch-track"></span>
			</label>
		</div>
		<?php if ( ! $on ) : ?>
			<div class="velox-alert velox-alert--warn"><?php esc_html_e( 'Comment protection is off — spam can reach your comments and links in them are clickable.', 'velox' ); ?></div>
		<?php endif; ?>

		<?php
		$cg_switch( 'cg_block_bots', __( 'Keep spam bots out', 'velox' ), __( 'Comments must come from a real browser on your page. Bots that post directly are refused before anything is saved. Invisible to visitors — no CAPTCHA.', 'velox' ) );
		$cg_switch( 'cg_strip_links', __( 'Make links unclickable', 'velox' ), __( 'Links in visitors\' comments show as plain text on the site and in the dashboard, and are broken up in notification emails. Comments by admins keep their links.', 'velox' ) );
		$cg_switch( 'cg_remove_url_field', __( 'Remove the “Website” field', 'velox' ), __( 'The field spammers use to plant a link behind their name.', 'velox' ) );
		$cg_switch( 'cg_block_pings', __( 'Refuse trackbacks and pingbacks', 'velox' ), __( 'Almost all of them are spam today.', 'velox' ) );
		?>

		<div class="vxcg-nums">
			<label class="velox-field">
				<span class="velox-field-label"><?php esc_html_e( 'Links allowed per comment', 'velox' ); ?></span>
				<input class="velox-input velox-input--sm" type="number" min="0" max="20" data-setting="cg_max_links" value="<?php echo (int) $s['cg_max_links']; ?>">
				<span class="velox-hint"><?php esc_html_e( 'More than this and the comment goes straight to spam. 0 = no links at all.', 'velox' ); ?></span>
			</label>
			<label class="velox-field">
				<span class="velox-field-label"><?php esc_html_e( 'Empty spam after', 'velox' ); ?></span>
				<span class="vxcg-inline"><input class="velox-input velox-input--sm" type="number" min="0" max="365" data-setting="cg_autodelete_days" value="<?php echo (int) $s['cg_autodelete_days']; ?>"> <span class="velox-hint"><?php esc_html_e( 'days', 'velox' ); ?></span></span>
				<span class="velox-hint"><?php esc_html_e( 'Spam is deleted for good after this. 0 = keep it.', 'velox' ); ?></span>
			</label>
		</div>

		<div class="velox-tool-actions">
			<button class="velox-btn velox-btn--primary velox-util-save"><?php esc_html_e( 'Save', 'velox' ); ?></button>
		</div>
	</div>

	<div class="velox-panel vxcg-clean" id="vxcg-clean">
		<h3 class="velox-panel-title"><?php esc_html_e( 'Clean up', 'velox' ); ?></h3>

		<div class="vxcg-step">
			<div class="vxcg-step-text">
				<strong><?php esc_html_e( 'Find spam already in your comments', 'velox' ); ?></strong>
				<span class="velox-hint"><?php esc_html_e( 'Checks approved and waiting comments against the rules on the left. Matches are moved to the spam folder, where you can still restore them.', 'velox' ); ?></span>
			</div>
			<button type="button" class="velox-btn velox-btn--ghost" id="vxcg-scan"><?php esc_html_e( 'Scan comments', 'velox' ); ?></button>
		</div>
		<div class="vxcg-result" id="vxcg-result" hidden aria-live="polite"></div>

		<div class="vxcg-step">
			<div class="vxcg-step-text">
				<strong><?php esc_html_e( 'Empty the spam folder', 'velox' ); ?></strong>
				<span class="velox-hint"><?php esc_html_e( 'Deletes every comment in the spam folder for good.', 'velox' ); ?></span>
			</div>
			<button type="button" class="velox-btn velox-btn--ghost" id="vxcg-empty" data-count="<?php echo (int) $st['spam']; ?>" <?php disabled( 0 === $st['spam'] ); ?>><?php esc_html_e( 'Empty spam', 'velox' ); ?></button>
		</div>

		<div class="vxcg-step vxcg-step--last">
			<div class="vxcg-step-text">
				<strong><?php esc_html_e( 'Don\'t need comments at all?', 'velox' ); ?></strong>
				<span class="velox-hint"><?php esc_html_e( 'Most business sites don\'t. Closing them everywhere is the most complete protection — nothing can be posted. Click Save to apply.', 'velox' ); ?></span>
			</div>
			<label class="velox-switch"><input type="checkbox" data-setting="perf_disable_comments" <?php checked( ! empty( $s['perf_disable_comments'] ) ); ?>><span class="velox-switch-track"></span></label>
		</div>
	</div>
</div>
