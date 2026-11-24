<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Velox Builder — AI agents.
 * 1. Claude in the editor: connect it with the site's own API key.
 * 2. Advanced: outside AI tools (Claude Desktop, Cursor …) over MCP.
 */
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
$ai     = Velox_Builder_Assistant::settings();
$ready  = Velox_Builder_Assistant::ready();
$lost   = ! $ready && '' !== $ai['key'];
$usage  = Velox_Builder_Assistant::usage_line();
$models = Velox_AI_Claude::models();
$money  = function ( $v ) {
	return '$' . number_format_i18n( (float) $v, 2 );
};
?>
<div class="vba-shell vba-ag">
	<header class="vba-head">
		<div>
			<h1><?php esc_html_e( 'AI agents', 'velox' ); ?></h1>
			<p><?php esc_html_e( 'Build and change pages by chatting with Claude right in the editor. Everything it does is a draft you check and publish.', 'velox' ); ?></p>
		</div>
	</header>

	<section class="vba-card vba-ai" id="vba-ai" data-ready="<?php echo $ready ? '1' : '0'; ?>">
		<div class="vba-card-h"><div>
			<h2><?php esc_html_e( 'Claude in the editor', 'velox' ); ?></h2>
			<p><?php esc_html_e( 'Your site talks to Claude with your own Anthropic account. You pay Anthropic directly for what you use.', 'velox' ); ?></p>
		</div>
		<span class="vba-ai-state <?php echo $ready ? 'is-on' : ''; ?>" id="vba-ai-state"><?php echo $ready ? esc_html__( 'Connected', 'velox' ) : esc_html__( 'Not connected', 'velox' ); ?></span>
		</div>

		<div class="vba-ai-connect" id="vba-ai-connect"<?php echo $ready ? ' hidden' : ''; ?>>
			<?php if ( $lost ) : ?>
				<p class="vba-ai-warn"><?php esc_html_e( 'The saved key can no longer be read, because the site\'s secret keys (in wp-config.php) changed. Enter the key again.', 'velox' ); ?></p>
			<?php endif; ?>
			<ol class="vba-ag-steps vba-ai-steps">
				<li>
					<b><?php esc_html_e( 'Create an API key', 'velox' ); ?></b>
					<span><?php esc_html_e( 'Sign in to the Claude Console (or create an account), add a little credit under Billing, then create a key named after this site.', 'velox' ); ?></span>
					<a class="vba-btn vba-btn-ghost vba-btn-sm" href="<?php echo esc_url( Velox_AI_Claude::keys_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the Claude Console', 'velox' ); ?> <span aria-hidden="true">↗</span></a>
				</li>
				<li>
					<b><?php esc_html_e( 'Paste it here', 'velox' ); ?></b>
					<span><?php esc_html_e( 'Velox checks the key, then stores it encrypted. It is never shown again or sent to the browser.', 'velox' ); ?></span>
					<div class="vba-ai-keyrow">
						<input type="password" class="vba-input-wide" id="vba-ai-key" autocomplete="off" spellcheck="false" placeholder="sk-ant-…" aria-label="<?php esc_attr_e( 'Anthropic API key', 'velox' ); ?>">
						<button type="button" class="vba-btn vba-btn-primary" id="vba-ai-connect-btn"><?php esc_html_e( 'Connect', 'velox' ); ?></button>
					</div>
					<p class="vba-ai-msg" id="vba-ai-msg" role="status" hidden></p>
				</li>
			</ol>
		</div>

		<div class="vba-ai-on" id="vba-ai-on"<?php echo $ready ? '' : ' hidden'; ?>>
			<div class="vba-setting">
				<div class="vba-setting-tx"><b><?php esc_html_e( 'API key', 'velox' ); ?></b>
				<span id="vba-ai-tail"><?php /* translators: %s: last four characters of the key */ printf( esc_html__( 'Ending in …%s, stored encrypted.', 'velox' ), esc_html( $ai['tail'] ) ); ?></span></div>
				<div class="vba-ai-acts">
					<button type="button" class="vba-btn vba-btn-ghost vba-btn-sm" id="vba-ai-test"><?php esc_html_e( 'Test connection', 'velox' ); ?></button>
					<button type="button" class="vba-btn vba-btn-ghost vba-btn-sm" id="vba-ai-remove"><?php esc_html_e( 'Remove key', 'velox' ); ?></button>
				</div>
			</div>
			<div class="vba-setting">
				<div class="vba-setting-tx"><b><?php esc_html_e( 'Model', 'velox' ); ?></b>
				<span id="vba-ai-model-note"><?php echo esc_html( $models[ $ai['model'] ]['note'] ); ?></span></div>
				<select id="vba-ai-model" class="vba-setting-ctrl">
					<?php foreach ( $models as $id => $m ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" data-note="<?php echo esc_attr( $m['note'] ); ?>" <?php selected( $ai['model'], $id ); ?>><?php echo esc_html( $m['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="vba-setting">
				<div class="vba-setting-tx"><b><?php esc_html_e( 'Monthly limit', 'velox' ); ?></b>
				<span><?php esc_html_e( 'The assistant stops for the rest of the month when Velox counts this much. 0 means no limit. Your Anthropic account can have its own limit too.', 'velox' ); ?></span></div>
				<label class="vba-ai-limit"><span aria-hidden="true">$</span><input type="number" id="vba-ai-limit" min="0" step="1" value="<?php echo esc_attr( (string) $ai['limit'] ); ?>" aria-label="<?php esc_attr_e( 'Monthly limit in US dollars', 'velox' ); ?>"><span class="vba-ai-per"><?php esc_html_e( 'per month', 'velox' ); ?></span></label>
			</div>
			<div class="vba-setting vba-ai-usage">
				<div class="vba-setting-tx"><b><?php esc_html_e( 'This month', 'velox' ); ?></b>
				<span id="vba-ai-usage-tx"><?php
					/* translators: 1: amount spent, 2: number of requests */
					printf( esc_html__( 'About %1$s in %2$s requests (counted by Velox from Anthropic\'s prices).', 'velox' ), esc_html( $money( $usage['usd'] ) ), esc_html( number_format_i18n( $usage['requests'] ) ) );
				?></span></div>
				<?php if ( $usage['limit'] > 0 ) : ?>
					<div class="vba-ai-meter" role="img" aria-label="<?php echo esc_attr( sprintf( '%d%%', $usage['pct'] ) ); ?>"><i style="width:<?php echo (int) $usage['pct']; ?>%"></i></div>
				<?php endif; ?>
			</div>
			<p class="vba-hint vba-ai-where"><?php esc_html_e( 'Open any page in the editor and click Claude in the top bar. Designers and administrators can use it; it can only do what their role allows.', 'velox' ); ?></p>
			<p class="vba-ai-msg" id="vba-ai-msg2" role="status" hidden></p>
		</div>
	</section>

	<details class="vba-card vba-ag-adv"<?php echo $on ? ' open' : ''; ?>>
		<summary class="vba-card-h"><div>
			<h2><?php esc_html_e( 'Your own AI tools (advanced)', 'velox' ); ?></h2>
			<p><?php esc_html_e( 'Let tools such as Claude Desktop, ChatGPT or Cursor work on this site from your computer, over MCP.', 'velox' ); ?></p>
		</div><span class="vba-ag-chev" aria-hidden="true"></span></summary>

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
		</ol>
		<div class="vba-ag-what">
			<p><?php esc_html_e( 'What an agent can do. It works only in drafts: nothing changes for visitors until a person publishes. Every change is kept under History → Saved versions, marked “By AI”.', 'velox' ); ?></p>
			<ul class="vba-ag-list">
				<?php foreach ( Velox_Builder_Agents::abilities() as $name => $a ) : ?>
					<li><b><?php echo esc_html( $a['label'] ); ?></b><code><?php echo esc_html( $name ); ?></code></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</details>
</div>
<script>
( function () {
	var T = <?php echo wp_json_encode( array(
		'copied'  => __( 'Copied', 'velox' ),
		'copy'    => __( 'Copy', 'velox' ),
		'failed'  => __( 'Save failed', 'velox' ),
		'check'   => __( 'Checking the key…', 'velox' ),
		'nokey'   => __( 'Paste your API key first.', 'velox' ),
		'saved'   => __( 'Saved.', 'velox' ),
		'remove'  => __( 'Remove the API key? The assistant stops working until a key is added again.', 'velox' ),
		'removeT' => __( 'Remove key', 'velox' ),
		'tail'    => __( 'Ending in …%s, stored encrypted.', 'velox' ),
		'on'      => __( 'Connected', 'velox' ),
		'off'     => __( 'Not connected', 'velox' ),
		'netErr'  => __( 'No answer from the server. Check your connection and try again.', 'velox' ),
	) ); ?>;
	function cfg() { return window.VELOX_BUILDER || {}; }
	function post( fields ) {
		var C = cfg(), body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'nonce', C.nonce || '' );
		Object.keys( fields ).forEach( function ( k ) { body.set( k, fields[ k ] ); } );
		return fetch( C.ajaxurl || window.ajaxurl, { method:'POST', credentials:'same-origin', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } );
	}
	function say( el, text, bad ) { el.hidden = ! text; el.textContent = text || ''; el.classList.toggle( 'is-bad', !! bad ); }
	function busy( b, on, label ) { b.disabled = on; if ( on ) { b.dataset.l = b.textContent; b.textContent = label; } else if ( b.dataset.l ) { b.textContent = b.dataset.l; } }

	/* ---- Claude in the editor ---- */
	var box = document.getElementById( 'vba-ai' );
	var msg = document.getElementById( 'vba-ai-msg' ), msg2 = document.getElementById( 'vba-ai-msg2' );
	function show( ready, tail ) {
		box.dataset.ready = ready ? '1' : '0';
		document.getElementById( 'vba-ai-connect' ).hidden = ready;
		document.getElementById( 'vba-ai-on' ).hidden = ! ready;
		var st = document.getElementById( 'vba-ai-state' ); st.textContent = ready ? T.on : T.off; st.classList.toggle( 'is-on', ready );
		if ( tail ) { document.getElementById( 'vba-ai-tail' ).textContent = T.tail.replace( '%s', tail ); }
	}
	var key = document.getElementById( 'vba-ai-key' ), cb = document.getElementById( 'vba-ai-connect-btn' );
	function connect() {
		var v = key.value.trim();
		if ( ! v ) { say( msg, T.nokey, true ); key.focus(); return; }
		busy( cb, true, T.check ); say( msg, '' );
		post( { do:'ai_settings', key:v } ).then( function ( res ) {
			busy( cb, false );
			if ( ! res || ! res.success ) { say( msg, ( res && res.data && res.data.message ) || T.failed, true ); return; }
			key.value = ''; show( true, res.data.tail );
		} ).catch( function () { busy( cb, false ); say( msg, T.netErr, true ); } );
	}
	cb.addEventListener( 'click', connect );
	key.addEventListener( 'keydown', function ( e ) { if ( 'Enter' === e.key ) { e.preventDefault(); connect(); } } );

	var test = document.getElementById( 'vba-ai-test' );
	test.addEventListener( 'click', function () {
		busy( test, true, T.check ); say( msg2, '' );
		post( { do:'ai_test' } ).then( function ( res ) {
			busy( test, false );
			say( msg2, res && res.data && res.data.message || T.failed, ! ( res && res.success ) );
		} ).catch( function () { busy( test, false ); say( msg2, T.netErr, true ); } );
	} );
	document.getElementById( 'vba-ai-remove' ).addEventListener( 'click', function () {
		var go = function () {
			post( { do:'ai_settings', remove:'1' } ).then( function ( res ) { if ( res && res.success ) { show( false ); } } );
		};
		if ( window.vbaDialog && vbaDialog.confirm ) { vbaDialog.confirm( { title:T.removeT, text:T.remove, ok:T.removeT, danger:true }, go ); }
		else if ( window.confirm( T.remove ) ) { go(); }
	} );
	var model = document.getElementById( 'vba-ai-model' ), limit = document.getElementById( 'vba-ai-limit' );
	function saveOpts() {
		post( { do:'ai_settings', model:model.value, limit:limit.value } ).then( function ( res ) {
			say( msg2, res && res.success ? T.saved : ( ( res && res.data && res.data.message ) || T.failed ), ! ( res && res.success ) );
			clearTimeout( saveOpts.t ); saveOpts.t = setTimeout( function () { say( msg2, '' ); }, 2500 );
		} );
	}
	model.addEventListener( 'change', function () {
		var o = model.options[ model.selectedIndex ]; document.getElementById( 'vba-ai-model-note' ).textContent = o.getAttribute( 'data-note' ) || '';
		saveOpts();
	} );
	limit.addEventListener( 'change', saveOpts );

	/* ---- Outside tools (MCP) ---- */
	var sw = document.getElementById( 'vba-ag-on' );
	sw.addEventListener( 'change', function () {
		post( { do:'builder_agents_toggle', on:sw.checked ? '1' : '' } )
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
