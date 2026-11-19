<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox Builder — AI agents: let AI tools (over MCP) read the design system and build drafts. */
$on        = Velox_Builder_Agents::enabled();
$supported = Velox_Builder_Agents::supported();
$routes    = function_exists( 'rest_get_server' ) ? rest_get_server()->get_routes() : array();
$adapter   = isset( $routes['/mcp/mcp-adapter-default-server'] );
$endpoint  = rest_url( 'mcp/mcp-adapter-default-server' );
$user      = wp_get_current_user();
$config    = wp_json_encode(
	array(
		'mcpServers' => array(
			sanitize_title( get_bloginfo( 'name' ) ? get_bloginfo( 'name' ) : 'wordpress' ) => array(
				'command' => 'npx',
				'args'    => array( '-y', '@automattic/mcp-wordpress-remote@latest' ),
				'env'     => array(
					'WP_API_URL'      => $endpoint,
					'WP_API_USERNAME' => $user->user_login,
					'WP_API_PASSWORD' => __( 'your-application-password', 'velox' ),
				),
			),
		),
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);
$install = admin_url( 'plugin-install.php?s=' . rawurlencode( 'MCP Adapter' ) . '&tab=search&type=term' );
$check   = function ( $ok ) {
	return '<span class="vba-ag-st ' . ( $ok ? 'is-ok' : 'is-no' ) . '" aria-hidden="true">' . ( $ok ? '&#10003;' : '&#8211;' ) . '</span>';
};
?>
<div class="vba-shell vba-ag">
	<header class="vba-head">
		<div>
			<h1><?php esc_html_e( 'AI agents', 'velox' ); ?></h1>
			<p><?php esc_html_e( 'Let AI tools such as Claude, ChatGPT or Cursor read your design system and build pages with Velox Builder. They work in drafts; you check and publish.', 'velox' ); ?></p>
		</div>
	</header>

	<section class="vba-card">
		<label class="vba-setting">
			<div class="vba-setting-tx"><b><?php esc_html_e( 'Let AI agents work on this site', 'velox' ); ?></b>
			<span><?php esc_html_e( 'An agent signs in as a WordPress user and can only do what that user may do in the builder.', 'velox' ); ?></span></div>
			<span class="vba-switch"><input type="checkbox" id="vba-ag-on" <?php checked( $on ); ?> <?php disabled( ! $supported ); ?>><span></span></span>
		</label>
		<div class="vba-setting">
			<div class="vba-setting-tx"><b><?php echo $check( $supported ); // phpcs:ignore ?> <?php esc_html_e( 'WordPress 6.9 or newer', 'velox' ); ?></b>
			<span><?php echo $supported ? esc_html__( 'This WordPress can describe what plugins can do to AI tools (the Abilities API).', 'velox' ) : esc_html__( 'Update WordPress to use AI agents.', 'velox' ); ?></span></div>
		</div>
		<div class="vba-setting">
			<div class="vba-setting-tx"><b><?php echo $check( $adapter ); // phpcs:ignore ?> <?php esc_html_e( 'MCP Adapter plugin', 'velox' ); ?></b>
			<span><?php echo $adapter ? esc_html__( 'Installed and active. AI tools connect through it.', 'velox' ) : esc_html__( 'The official WordPress plugin that connects AI tools (over MCP). Install and activate it.', 'velox' ); ?></span></div>
			<?php if ( ! $adapter && current_user_can( 'install_plugins' ) ) : ?>
				<a class="vba-btn vba-btn-ghost vba-btn-sm" href="<?php echo esc_url( $install ); ?>"><?php esc_html_e( 'Find it in Plugins', 'velox' ); ?></a>
			<?php endif; ?>
		</div>
	</section>

	<section class="vba-card">
		<div class="vba-card-h"><div>
			<h2><?php esc_html_e( 'Connect your AI tool', 'velox' ); ?></h2>
			<p><?php esc_html_e( 'Once, on the computer where the AI tool runs.', 'velox' ); ?></p>
		</div></div>
		<ol class="vba-ag-steps">
			<li>
				<b><?php esc_html_e( 'Create an application password', 'velox' ); ?></b>
				<span><?php esc_html_e( 'In your profile, under Application Passwords, give it a name such as “Claude” and copy the password it shows. You can revoke it there at any time.', 'velox' ); ?></span>
				<a class="vba-btn vba-btn-ghost vba-btn-sm" href="<?php echo esc_url( admin_url( 'profile.php#application-passwords-section' ) ); ?>"><?php esc_html_e( 'Open my profile', 'velox' ); ?></a>
			</li>
			<li>
				<b><?php esc_html_e( 'Add this server to the AI tool', 'velox' ); ?></b>
				<span><?php esc_html_e( 'In Claude Desktop: Settings → Developer → Edit config. Other tools have a similar MCP settings file. Put the application password in place of the placeholder. Node.js must be installed.', 'velox' ); ?></span>
				<div class="vba-ag-code"><pre id="vba-ag-config"><?php echo esc_html( $config ); ?></pre><button type="button" class="vba-btn vba-btn-ghost vba-btn-sm" id="vba-ag-copy"><?php esc_html_e( 'Copy', 'velox' ); ?></button></div>
			</li>
			<li>
				<b><?php esc_html_e( 'Ask for something', 'velox' ); ?></b>
				<span><?php esc_html_e( 'For example: “Look at the Velox design system, then build a landing page for our spring offer with a hero, three benefits and a contact form.”', 'velox' ); ?></span>
			</li>
		</ol>
	</section>

	<section class="vba-card">
		<div class="vba-card-h"><div>
			<h2><?php esc_html_e( 'What an agent can do', 'velox' ); ?></h2>
			<p><?php esc_html_e( 'It works only in drafts: nothing changes for visitors until a person publishes. Every change is kept under History → Saved versions, marked “By AI”.', 'velox' ); ?></p>
		</div></div>
		<ul class="vba-ag-list">
			<?php foreach ( Velox_Builder_Agents::abilities() as $name => $a ) : ?>
				<li><b><?php echo esc_html( $a['label'] ); ?></b><code><?php echo esc_html( $name ); ?></code></li>
			<?php endforeach; ?>
		</ul>
	</section>
</div>
<script>
( function () {
	var T = <?php echo wp_json_encode( array( 'copied' => __( 'Copied', 'velox' ), 'copy' => __( 'Copy', 'velox' ), 'failed' => __( 'Save failed', 'velox' ) ) ); ?>;
	function cfg() { return window.VELOX_BUILDER || {}; }
	var sw = document.getElementById( 'vba-ag-on' );
	sw.addEventListener( 'change', function () {
		var C = cfg(), body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', 'builder_agents_toggle' ); body.set( 'nonce', C.nonce || '' ); body.set( 'on', sw.checked ? '1' : '' );
		fetch( C.ajaxurl || window.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) { if ( ! res || ! res.success ) { throw new Error(); } sw.checked = !! res.data.on; } )
			.catch( function () { sw.checked = ! sw.checked; window.vbaDialog ? vbaDialog.alert( { title:T.failed, text:T.failed } ) : alert( T.failed ); } );
	} );
	var cp = document.getElementById( 'vba-ag-copy' );
	cp.addEventListener( 'click', function () {
		var txt = document.getElementById( 'vba-ag-config' ).textContent;
		( navigator.clipboard ? navigator.clipboard.writeText( txt ) : Promise.reject() ).then( function () { cp.textContent = T.copied; setTimeout( function () { cp.textContent = T.copy; }, 1500 ); } ).catch( function () {
			var r = document.createRange(); r.selectNodeContents( document.getElementById( 'vba-ag-config' ) ); var s = getSelection(); s.removeAllRanges(); s.addRange( r );
		} );
	} );
}() );
</script>
