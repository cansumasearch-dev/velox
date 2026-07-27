<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s    = Velox_Settings::all();
$slug = trim( (string) $s['util_login_slug'], '/' );
?>
<div class="velox-page-head">
	<h1 class="velox-h2"><?php esc_html_e('Custom login URL', 'velox'); ?></h1>
	<p class="velox-sub"><?php printf( esc_html__( 'Moves the login page off the default %s to a path only you know — most brute-force bots hammer wp-login, so this quietly cuts that traffic.', 'velox' ), '<code>' . esc_html__( '/wp-login.php', 'velox' ) . '</code>' ); ?></p>
</div>

<div class="velox-panel velox-tool-form">
	<div class="velox-field">
		<span class="velox-field-label"><?php esc_html_e('Login slug', 'velox'); ?></span>
		<input type="text" class="velox-input" data-setting="util_login_slug" value="<?php echo esc_attr( $slug ); ?>" placeholder="e.g. control-room">
		<span class="velox-hint"><?php esc_html_e('Leave empty to keep the default wp-login. With a slug set, your login lives at:', 'velox'); ?>
			<code><?php echo esc_html( home_url( '/' . ( $slug ? $slug : 'your-slug' ) . '/' ) ); ?></code></span>
	</div>

	<?php if ( $slug ) : ?>
		<div class="velox-alert velox-alert--info">
			<strong><?php esc_html_e('Bookmark your always-works recovery URL:', 'velox'); ?></strong>
			<code><?php echo esc_html( home_url( '/wp-login.php?' . $slug ) ); ?></code><br>
			<?php esc_html_e('This one hits the real login file directly, so it works even if the pretty URL above is blocked by your server or a CDN. If you ever can\'t reach the pretty URL, use this.', 'velox'); ?>
		</div>
	<?php endif; ?>

	<div class="velox-alert velox-alert--warn">
		<strong><?php esc_html_e('Bookmark your login URL before leaving this page.', 'velox'); ?></strong> <?php printf( esc_html__( 'Once enabled, %1$s and %2$s redirect away for logged-out visitors. If you ever lock yourself out, clear the %3$s value (or deactivate Velox) via your hosting file manager to restore the default login.', 'velox' ), '<code>' . esc_html__( '/wp-login.php', 'velox' ) . '</code>', '<code>' . esc_html__( '/wp-admin', 'velox' ) . '</code>', '<code>' . esc_html__( 'util_login_slug', 'velox' ) . '</code>' ); ?>
	</div>

	<div class="velox-tool-actions">
		<button class="velox-btn velox-btn--primary velox-util-save"><?php esc_html_e('Save', 'velox'); ?></button>
	</div>
</div>
