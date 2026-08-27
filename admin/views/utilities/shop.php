<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s   = Velox_Settings::all();
$on  = ! empty( $s['util_shop'] );
$cnt = $on ? wp_count_posts( Velox_Shop::PRODUCT ) : null;
$ord = $on ? wp_count_posts( Velox_Shop::ORDER ) : null;
?>
<div class="velox-page-head">
	<h1 class="velox-h2"><?php esc_html_e( 'Shop', 'velox' ); ?></h1>
	<p class="velox-sub"><?php esc_html_e( 'Sell individual works: a catalogue with prices and availability, a cart, orders and a customer account area.', 'velox' ); ?></p>
</div>

<div class="velox-grid-2">
	<div class="velox-panel">
		<div class="velox-panel-head">
			<h3 class="velox-panel-title"><?php esc_html_e( 'Enable shop', 'velox' ); ?></h3>
			<label class="velox-switch">
				<input type="checkbox" data-setting="util_shop" <?php checked( $on ); ?>>
				<span class="velox-switch-track"></span>
			</label>
		</div>
		<p class="velox-hint"><?php esc_html_e( 'Adds a Products area to the admin menu, with Orders beneath it.', 'velox' ); ?></p>

		<div class="velox-note velox-note--info">
			<strong><?php esc_html_e( 'What this deliberately does not do', 'velox' ); ?></strong>
			<p><?php esc_html_e( 'No card details are taken or stored anywhere in Velox. Placing an order records it and emails both sides; you agree payment out of band. A real payment provider belongs behind a redirect and can be added later without changing anything here.', 'velox' ); ?></p>
			<p><?php esc_html_e( 'Customer accounts are ordinary WordPress users. Sign-in, registration and password resets all use WordPress itself, so no second set of credentials exists to be leaked.', 'velox' ); ?></p>
		</div>

		<div class="velox-field">
			<label class="velox-label" for="vxs-cur"><?php esc_html_e( 'Currency symbol', 'velox' ); ?></label>
			<input class="velox-input velox-input--sm" id="vxs-cur" type="text" data-setting="shop_currency" value="<?php echo esc_attr( $s['shop_currency'] ); ?>" maxlength="4">
		</div>
		<div class="velox-field">
			<label class="velox-label" for="vxs-pos"><?php esc_html_e( 'Symbol position', 'velox' ); ?></label>
			<select class="velox-select" id="vxs-pos" data-setting="shop_currency_pos">
				<option value="right" <?php selected( $s['shop_currency_pos'], 'right' ); ?>><?php esc_html_e( 'After the amount — 149 €', 'velox' ); ?></option>
				<option value="left" <?php selected( $s['shop_currency_pos'], 'left' ); ?>><?php esc_html_e( 'Before the amount — € 149', 'velox' ); ?></option>
			</select>
		</div>

		<div class="velox-tool-actions">
			<button class="velox-btn velox-btn--primary velox-util-save"><?php esc_html_e( 'Save', 'velox' ); ?></button>
		</div>
	</div>

	<div class="velox-panel">
		<div class="velox-panel-head">
			<h3 class="velox-panel-title"><?php esc_html_e( 'Putting it on a page', 'velox' ); ?></h3>
		</div>
		<p class="velox-hint"><?php esc_html_e( 'Four shortcodes. Drop them into a Velox Builder text element or any page.', 'velox' ); ?></p>
		<ul class="velox-kv">
			<li><code>[velox_shop columns="3" count="12"]</code><span><?php esc_html_e( 'The catalogue grid', 'velox' ); ?></span></li>
			<li><code>[velox_cart]</code><span><?php esc_html_e( 'Current cart contents', 'velox' ); ?></span></li>
			<li><code>[velox_checkout]</code><span><?php esc_html_e( 'Checkout form and the thank-you state', 'velox' ); ?></span></li>
			<li><code>[velox_account]</code><span><?php esc_html_e( 'Sign in, and order history once signed in', 'velox' ); ?></span></li>
		</ul>

		<?php if ( $on ) : ?>
			<div class="velox-stat-row">
				<div class="velox-stat">
					<div class="velox-stat-v"><?php echo (int) ( $cnt->publish ?? 0 ); ?></div>
					<div class="velox-stat-k"><?php esc_html_e( 'Published works', 'velox' ); ?></div>
				</div>
				<div class="velox-stat">
					<div class="velox-stat-v"><?php echo (int) ( $ord->publish ?? 0 ); ?></div>
					<div class="velox-stat-k"><?php esc_html_e( 'Orders', 'velox' ); ?></div>
				</div>
			</div>
			<div class="velox-tool-actions">
				<a class="velox-btn velox-btn--primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Velox_Shop::PRODUCT ) ); ?>"><?php esc_html_e( 'Add a work', 'velox' ); ?></a>
				<a class="velox-btn velox-btn--ghost" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Velox_Shop::ORDER ) ); ?>"><?php esc_html_e( 'View orders', 'velox' ); ?></a>
			</div>
		<?php endif; ?>
	</div>
</div>
