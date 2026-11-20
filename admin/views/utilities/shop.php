<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s   = Velox_Settings::all();
$on  = ! empty( $s['util_shop'] );
$cnt = $on ? wp_count_posts( Velox_Shop::PRODUCT ) : null;
?>
<div class="velox-page-head">
	<h1 class="velox-h2"><?php esc_html_e( 'Shop', 'velox' ); ?></h1>
	<p class="velox-sub"><?php esc_html_e( 'Sell products, downloads, services or one-of-a-kind pieces: variants, stock, cart, checkout with card, PayPal, bank transfer, invoice or cash on delivery, invoices and German legal requirements built in.', 'velox' ); ?></p>
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
		<p class="velox-hint"><?php esc_html_e( 'Adds “Velox Shop” to the admin menu: orders, products, coupons, customers and all shop settings in one place.', 'velox' ); ?></p>
		<div class="velox-tool-actions">
			<button class="velox-btn velox-btn--primary velox-util-save"><?php esc_html_e( 'Save', 'velox' ); ?></button>
			<?php if ( $on ) : ?>
				<a class="velox-btn velox-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=velox-shop' ) ); ?>"><?php esc_html_e( 'Open Velox Shop', 'velox' ); ?></a>
			<?php endif; ?>
		</div>
	</div>

	<div class="velox-panel">
		<div class="velox-panel-head">
			<h3 class="velox-panel-title"><?php esc_html_e( 'Putting it on a page', 'velox' ); ?></h3>
		</div>
		<p class="velox-hint"><?php esc_html_e( 'The setup in Velox Shop creates the shop pages for you. In Velox Builder the shop elements (product grid, add to cart, cart, checkout …) are under “Shop” in the Add panel. Shortcodes work anywhere:', 'velox' ); ?></p>
		<ul class="velox-kv">
			<li><code>[velox_shop columns="3" count="12" filter="1"]</code><span><?php esc_html_e( 'The product grid', 'velox' ); ?></span></li>
			<li><code>[velox_cart]</code><span><?php esc_html_e( 'The cart', 'velox' ); ?></span></li>
			<li><code>[velox_checkout]</code><span><?php esc_html_e( 'Checkout and the order page after buying', 'velox' ); ?></span></li>
			<li><code>[velox_account]</code><span><?php esc_html_e( 'Sign in and order history', 'velox' ); ?></span></li>
			<li><code>[velox_withdraw]</code><span><?php esc_html_e( 'The withdrawal form (§ 356a BGB)', 'velox' ); ?></span></li>
			<li><code>[velox_cart_button]</code><span><?php esc_html_e( 'Cart icon with count, opens the mini cart', 'velox' ); ?></span></li>
		</ul>
		<?php if ( $on ) : ?>
			<p class="velox-hint"><?php echo esc_html( sprintf( /* translators: %d: number of products */ _n( '%d product is live.', '%d products are live.', (int) ( $cnt->publish ?? 0 ), 'velox' ), (int) ( $cnt->publish ?? 0 ) ) ); ?></p>
		<?php endif; ?>
	</div>
</div>
