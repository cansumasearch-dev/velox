<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s    = Velox_Settings::all();
$slug = trim( (string) $s['util_login_slug'], '/' );
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Custom login URL</h1>
	<p class="velox-sub">Moves the login page off the default <code>/wp-login.php</code> to a path only you know — most brute-force bots hammer wp-login, so this quietly cuts that traffic.</p>
</div>

<div class="velox-panel velox-tool-form">
	<div class="velox-field">
		<span class="velox-field-label">Login slug</span>
		<input type="text" class="velox-input" data-setting="util_login_slug" value="<?php echo esc_attr( $slug ); ?>" placeholder="e.g. control-room">
		<span class="velox-hint">Leave empty to keep the default wp-login. With a slug set, your login lives at:
			<code><?php echo esc_html( home_url( '/' . ( $slug ? $slug : 'your-slug' ) . '/' ) ); ?></code></span>
	</div>

	<div class="velox-alert velox-alert--warn">
		<strong>Bookmark your login URL before leaving this page.</strong> Once enabled, <code>/wp-login.php</code> and <code>/wp-admin</code> redirect away for logged-out visitors. If you ever lock yourself out, clear the <code>util_login_slug</code> value (or deactivate Velox) via your hosting file manager to restore the default login.
	</div>

	<div class="velox-tool-actions">
		<button class="velox-btn velox-btn--primary velox-util-save">Save</button>
	</div>
</div>
