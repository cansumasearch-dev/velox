<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s     = Velox_Settings::all();
$on    = ! empty( $s['util_loginguard'] );
$locks = class_exists( 'Velox_Login_Guard' ) ? Velox_Login_Guard::locks() : array();
uasort( $locks, function ( $a, $b ) {
	return ( $b['when'] ?? 0 ) <=> ( $a['when'] ?? 0 );
} );
?>
<div class="velox-page-head">
	<h1 class="velox-h2"><?php esc_html_e( 'Login protection', 'velox' ); ?></h1>
	<p class="velox-sub"><?php esc_html_e( 'Block an address after repeated failed sign-ins, until you let it back in.', 'velox' ); ?></p>
</div>

<div class="velox-grid-2">
	<div class="velox-panel">
		<div class="velox-panel-head">
			<h3 class="velox-panel-title"><?php esc_html_e( 'Enable', 'velox' ); ?></h3>
			<label class="velox-switch">
				<input type="checkbox" data-setting="util_loginguard" <?php checked( $on ); ?>>
				<span class="velox-switch-track"></span>
			</label>
		</div>

		<div class="velox-field">
			<label class="velox-label" for="lg-max"><?php esc_html_e( 'Block after this many failures', 'velox' ); ?></label>
			<input class="velox-input velox-input--sm" id="lg-max" type="number" min="1" max="50"
				data-setting="loginguard_max" value="<?php echo (int) $s['loginguard_max']; ?>">
			<p class="velox-hint"><?php esc_html_e( 'Counted per address. A successful sign-in resets the count.', 'velox' ); ?></p>
		</div>

		<div class="velox-row">
			<span><?php esc_html_e( 'Email me when an address is blocked', 'velox' ); ?></span>
			<label class="velox-switch">
				<input type="checkbox" data-setting="loginguard_notify" <?php checked( ! empty( $s['loginguard_notify'] ) ); ?>>
				<span class="velox-switch-track"></span>
			</label>
		</div>
		<p class="velox-hint"><?php esc_html_e( 'The email carries a one-click unlock link, so you can never be shut out of your own site by this.', 'velox' ); ?></p>

		<div class="velox-row">
			<span><?php esc_html_e( 'This site sits behind a proxy or CDN', 'velox' ); ?></span>
			<label class="velox-switch">
				<input type="checkbox" data-setting="loginguard_behind_proxy" <?php checked( ! empty( $s['loginguard_behind_proxy'] ) ); ?>>
				<span class="velox-switch-track"></span>
			</label>
		</div>
		<p class="velox-hint"><?php esc_html_e( 'Only switch this on if it is true. It makes Velox trust the forwarded-address header — and on a site that is not behind a proxy, anyone could forge that header and dodge every block.', 'velox' ); ?></p>

		<div class="velox-note velox-note--info">
			<strong><?php esc_html_e( 'Blocked by address, not by account', 'velox' ); ?></strong>
			<p><?php esc_html_e( 'Blocking an account after failed attempts would let anyone lock you out of your own site just by typing your username wrong a few times. Velox blocks where the attempts come from instead.', 'velox' ); ?></p>
		</div>

		<div class="velox-tool-actions">
			<button class="velox-btn velox-btn--primary velox-util-save"><?php esc_html_e( 'Save', 'velox' ); ?></button>
		</div>
	</div>

	<div class="velox-panel">
		<div class="velox-panel-head">
			<h3 class="velox-panel-title"><?php esc_html_e( 'Blocked addresses', 'velox' ); ?></h3>
			<?php if ( $locks ) : ?>
				<button class="velox-btn velox-btn--ghost velox-btn--sm" id="velox-lg-unlockall"><?php esc_html_e( 'Unlock all', 'velox' ); ?></button>
			<?php endif; ?>
		</div>

		<?php if ( ! $locks ) : ?>
			<p class="velox-hint"><?php esc_html_e( 'Nothing is blocked.', 'velox' ); ?></p>
		<?php else : ?>
			<table class="velox-table" id="velox-lg-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Address', 'velox' ); ?></th>
						<th><?php esc_html_e( 'Attempts', 'velox' ); ?></th>
						<th><?php esc_html_e( 'Username tried', 'velox' ); ?></th>
						<th><?php esc_html_e( 'When', 'velox' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $locks as $k => $l ) : ?>
					<tr data-lock="<?php echo esc_attr( $k ); ?>">
						<td><code><?php echo esc_html( $l['ip'] ?? '' ); ?></code></td>
						<td><?php echo (int) ( $l['count'] ?? 0 ); ?></td>
						<td><?php echo esc_html( $l['user'] ?? '—' ); ?></td>
						<td><?php echo esc_html( human_time_diff( (int) ( $l['when'] ?? time() ) ) ); ?></td>
						<td><button class="velox-btn velox-btn--ghost velox-btn--sm velox-lg-unlock"><?php esc_html_e( 'Unlock', 'velox' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
