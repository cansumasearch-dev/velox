<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Velox Builder — AI agents.
 * 1. Claude with your own Claude account: Velox is a connector (MCP + sign-in).
 * 2. Connected apps: who is connected, to which pages; disconnect.
 * 3. Optional: Claude inside the editor with an Anthropic API key.
 */
$on       = Velox_Builder_Agents::enabled();
$url      = Velox_MCP::mcp_url();
$site     = get_bloginfo( 'name' );
$slug     = sanitize_title( $site ) ? sanitize_title( $site ) : 'velox';
$https    = 0 === strpos( $url, 'https://' );
$entry    = wp_json_encode( array( 'command' => 'npx', 'args' => array( '-y', 'mcp-remote', $url ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
$entry    = '"' . $slug . '": ' . $entry;
$indent   = function ( $txt, $pad ) {
	return implode( "\n", array_map( function ( $l ) use ( $pad ) { return $pad . $l; }, explode( "\n", $txt ) ) );
};
$block    = "\"mcpServers\": {\n" . $indent( $entry, '  ' ) . "\n}";
$apps     = Velox_MCP::list_for_admin();
$choices  = Velox_MCP::choices();
$ai       = Velox_Builder_Assistant::settings();
$ready    = Velox_Builder_Assistant::ready();
$lost     = ! $ready && '' !== $ai['key'];
$usage    = Velox_Builder_Assistant::usage_line();
$models   = Velox_AI_Claude::models();
$money    = function ( $v ) {
	return '$' . number_format_i18n( (float) $v, 2 );
};
$copy_btn = function ( $target, $label = '' ) {
	return '<button type="button" class="vba-btn vba-btn-ghost vba-btn-sm vba-copy" data-copy="' . esc_attr( $target ) . '">' . esc_html( $label ? $label : __( 'Copy', 'velox' ) ) . '</button>';
};
?>
<div class="vba-shell vba-ag">
	<header class="vba-head">
		<div>
			<h1><?php esc_html_e( 'AI agents', 'velox' ); ?></h1>
			<p><?php esc_html_e( 'Build and change pages by chatting with Claude, with your own Claude account. Everything it does is a draft you check and publish.', 'velox' ); ?></p>
		</div>
	</header>

	<section class="vba-card vba-cn" id="vba-cn" data-on="<?php echo $on ? '1' : '0'; ?>">
		<div class="vba-card-h"><div>
			<h2><?php esc_html_e( 'Claude with your Claude account', 'velox' ); ?></h2>
			<p><?php esc_html_e( 'Add this site to Claude as a connector. You chat in Claude (web, desktop app or Claude Code) and it works on your pages. Your Claude plan covers it: no API key, no extra costs.', 'velox' ); ?></p>
		</div>
		<span class="vba-ai-state <?php echo $on ? 'is-on' : ''; ?>" id="vba-cn-state"><?php echo $on ? esc_html__( 'On', 'velox' ) : esc_html__( 'Off', 'velox' ); ?></span>
		</div>

		<label class="vba-setting">
			<div class="vba-setting-tx"><b><?php esc_html_e( 'Let Claude work on this site', 'velox' ); ?></b>
			<span><?php esc_html_e( 'Claude signs in with your WordPress account, can only do what you may do, and only on the pages you allow.', 'velox' ); ?></span></div>
			<span class="vba-switch"><input type="checkbox" id="vba-ag-on" <?php checked( $on ); ?>><span></span></span>
		</label>

		<div class="vba-cn-body" id="vba-cn-body"<?php echo $on ? '' : ' hidden'; ?>>
			<?php if ( ! $https ) : ?>
				<p class="vba-ai-warn"><?php esc_html_e( 'Claude only connects to https addresses. Switch the site to https first.', 'velox' ); ?></p>
			<?php endif; ?>
			<div class="vba-cn-url">
				<span class="vba-cn-lbl"><?php esc_html_e( 'Connector address', 'velox' ); ?></span>
				<div class="vba-cn-urlrow">
					<input type="text" class="vba-input-wide" id="vba-cn-url" value="<?php echo esc_attr( $url ); ?>" readonly aria-label="<?php esc_attr_e( 'Connector address', 'velox' ); ?>">
					<?php echo $copy_btn( 'vba-cn-url' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
			</div>

			<div class="vba-seg" role="tablist" aria-label="<?php esc_attr_e( 'Where you use Claude', 'velox' ); ?>">
				<button type="button" role="tab" class="is-on" aria-selected="true" data-tab="claude"><?php esc_html_e( 'Claude (web and app)', 'velox' ); ?></button>
				<button type="button" role="tab" aria-selected="false" data-tab="code"><?php esc_html_e( 'Claude Code', 'velox' ); ?></button>
				<button type="button" role="tab" aria-selected="false" data-tab="file"><?php esc_html_e( 'Other apps', 'velox' ); ?></button>
			</div>

			<div class="vba-tabp" data-panel="claude" role="tabpanel">
				<ol class="vba-ag-steps">
					<li><b><?php esc_html_e( 'Open the connectors in Claude', 'velox' ); ?></b>
						<span><?php esc_html_e( 'On claude.ai or in the Claude app: Customize → Connectors (in some versions Settings → Connectors). Click + and choose “Add custom connector”.', 'velox' ); ?></span>
						<a class="vba-btn vba-btn-ghost vba-btn-sm" href="https://claude.ai/" target="_blank" rel="noopener"><?php esc_html_e( 'Open Claude', 'velox' ); ?> <span aria-hidden="true">↗</span></a></li>
					<li><b><?php esc_html_e( 'Paste the address', 'velox' ); ?></b>
						<span><?php /* translators: %s: site name */ printf( esc_html__( 'Name: %s. Address: the connector address above. Leave the advanced settings empty, then click Add.', 'velox' ), '<b class="vba-cn-k">' . esc_html( $site ) . '</b>' ); ?></span></li>
					<li><b><?php esc_html_e( 'Sign in and allow', 'velox' ); ?></b>
						<span><?php esc_html_e( 'Click Connect. This site\'s sign-in opens: sign in, choose the whole site or single pages, and click Allow.', 'velox' ); ?></span></li>
					<li><b><?php esc_html_e( 'Chat', 'velox' ); ?></b>
						<span><?php esc_html_e( 'In a chat, switch the connector on in the tools menu and say what to build. In the editor, “Work on this page in Claude” starts a chat about the page you have open.', 'velox' ); ?></span></li>
				</ol>
				<p class="vba-hint vba-cn-note"><?php esc_html_e( 'The connector works in the browser, the desktop app and the mobile app alike. Free plans can add one custom connector. In Team and Enterprise plans an owner adds it for the organisation first.', 'velox' ); ?></p>
			</div>

			<div class="vba-tabp" data-panel="code" role="tabpanel" hidden>
				<ol class="vba-ag-steps">
					<li><b><?php esc_html_e( 'Add the server', 'velox' ); ?></b>
						<span><?php esc_html_e( 'Run this in a terminal:', 'velox' ); ?></span>
						<div class="vba-ag-code"><pre id="vba-cn-cmd">claude mcp add --transport http <?php echo esc_html( $slug . ' ' . $url ); ?></pre><?php echo $copy_btn( 'vba-cn-cmd' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div></li>
					<li><b><?php esc_html_e( 'Sign in', 'velox' ); ?></b>
						<span><?php esc_html_e( 'In Claude Code type /mcp, choose the server and Authenticate. Your browser opens this site\'s sign-in.', 'velox' ); ?></span></li>
				</ol>
			</div>

			<div class="vba-tabp" data-panel="file" role="tabpanel" hidden>
				<p class="vba-cn-p"><?php esc_html_e( 'Apps that connect to remote MCP servers with a sign-in (Cursor, VS Code, ChatGPT and others) take the connector address above. For an app that is set up with a configuration file, add the lines below to it. Node.js must be installed. Claude Desktop does not need this: the connector you add in Claude works there too.', 'velox' ); ?></p>
				<div class="vba-cfg">
					<div class="vba-cfg-h"><b><?php esc_html_e( 'Where it goes', 'velox' ); ?></b>
						<span><?php esc_html_e( 'The file is one pair of curly braces with entries separated by commas. Keep everything that is in it.', 'velox' ); ?></span></div>
					<pre class="vba-cfg-demo" aria-hidden="true"><span class="is-old">{
  "<?php esc_html_e( 'what is already in the file', 'velox' ); ?>": { … },</span>
<span class="is-new"><?php echo esc_html( $indent( $block, '  ' ) ); ?></span>
<span class="is-old">}</span></pre>
					<ul class="vba-cfg-cases">
						<li><b><?php esc_html_e( 'The file has no "mcpServers" yet', 'velox' ); ?></b>
							<span><?php esc_html_e( 'Put a comma after the last entry, then paste the whole block before the last closing brace.', 'velox' ); ?></span>
							<?php echo $copy_btn( 'vba-cfg-block', __( 'Copy the block', 'velox' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></li>
						<li><b><?php esc_html_e( 'It already has "mcpServers"', 'velox' ); ?></b>
							<span><?php esc_html_e( 'Paste only the entry inside its braces, with a comma after the entry before it.', 'velox' ); ?></span>
							<?php echo $copy_btn( 'vba-cfg-entry', __( 'Copy the entry', 'velox' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></li>
					</ul>
					<pre id="vba-cfg-block" hidden><?php echo esc_html( $block ); ?></pre>
					<pre id="vba-cfg-entry" hidden><?php echo esc_html( $entry ); ?></pre>
					<p class="vba-hint"><?php esc_html_e( 'Claude Desktop\'s file, if you want it anyway: Windows %APPDATA%\Claude\claude_desktop_config.json (type it into the Explorer address bar), Mac ~/Library/Application Support/Claude/claude_desktop_config.json. Restart the app after saving.', 'velox' ); ?></p>
				</div>
			</div>

			<div class="vba-cn-check">
				<button type="button" class="vba-btn vba-btn-ghost vba-btn-sm" id="vba-cn-check"><?php esc_html_e( 'Check the connection', 'velox' ); ?></button>
				<span class="vba-hint"><?php esc_html_e( 'Checks from this server what Claude will see.', 'velox' ); ?></span>
				<ul class="vba-chk" id="vba-cn-rows" hidden></ul>
			</div>
		</div>
	</section>

	<section class="vba-card vba-apps-card" id="vba-apps-card"<?php echo $on || $apps ? '' : ' hidden'; ?>>
		<div class="vba-card-h"><div>
			<h2><?php esc_html_e( 'Connected apps', 'velox' ); ?></h2>
			<p><?php esc_html_e( 'Who is connected, and to which pages. Disconnecting signs the app out at once.', 'velox' ); ?></p>
		</div></div>
		<ul class="vba-apps" id="vba-apps"></ul>
	</section>

	<details class="vba-card vba-ai vba-ag-adv" id="vba-ai" data-ready="<?php echo $ready ? '1' : '0'; ?>"<?php echo $ready ? ' open' : ''; ?>>
		<summary class="vba-card-h"><div>
			<h2><?php esc_html_e( 'Claude inside the editor (with an API key)', 'velox' ); ?></h2>
			<p><?php esc_html_e( 'Optional: a chat panel right in the editor. It needs an Anthropic API key, and you pay Anthropic per use.', 'velox' ); ?></p>
		</div>
		<span class="vba-ai-state <?php echo $ready ? 'is-on' : ''; ?>" id="vba-ai-state"><?php echo $ready ? esc_html__( 'Connected', 'velox' ) : esc_html__( 'Not connected', 'velox' ); ?></span><span class="vba-ag-chev" aria-hidden="true"></span>
		</summary>

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
	</details>

	<details class="vba-card vba-ag-adv">
		<summary class="vba-card-h"><div>
			<h2><?php esc_html_e( 'What Claude can do here', 'velox' ); ?></h2>
			<p><?php esc_html_e( 'It works only in drafts: nothing changes for visitors until a person publishes. Every change is kept under History → Saved versions, marked “By AI”.', 'velox' ); ?></p>
		</div><span class="vba-ag-chev" aria-hidden="true"></span></summary>
		<ul class="vba-ag-list">
			<?php foreach ( Velox_Builder_Agents::abilities() as $name => $a ) : ?>
				<li><b><?php echo esc_html( $a['label'] ); ?></b><code><?php echo esc_html( Velox_Builder_Agents::tool_name( $name ) ); ?></code></li>
			<?php endforeach; ?>
		</ul>
	</details>
</div>
<script>
( function () {
	var T = <?php echo wp_json_encode( array(
		'copied'   => __( 'Copied', 'velox' ),
		'failed'   => __( 'Save failed', 'velox' ),
		'check'    => __( 'Checking the key…', 'velox' ),
		'checking' => __( 'Checking…', 'velox' ),
		'nokey'    => __( 'Paste your API key first.', 'velox' ),
		'saved'    => __( 'Saved.', 'velox' ),
		'remove'   => __( 'Remove the API key? The assistant stops working until a key is added again.', 'velox' ),
		'removeT'  => __( 'Remove key', 'velox' ),
		'tail'     => __( 'Ending in …%s, stored encrypted.', 'velox' ),
		'on'       => __( 'Connected', 'velox' ),
		'off'      => __( 'Not connected', 'velox' ),
		'cnOn'     => __( 'On', 'velox' ),
		'cnOff'    => __( 'Off', 'velox' ),
		'netErr'   => __( 'No answer from the server. Check your connection and try again.', 'velox' ),
		'none'     => __( 'Nothing connected yet. Add the connector in Claude as described above.', 'velox' ),
		'all'      => __( 'Whole site', 'velox' ),
		'pages'    => __( '%d pages', 'velox' ),
		'page1'    => __( '1 page', 'velox' ),
		'by'       => __( 'by %s', 'velox' ),
		'used'     => __( 'last used %s ago', 'velox' ),
		'never'    => __( 'not used yet', 'velox' ),
		'units'    => array( __( '%d s', 'velox' ), __( '%d min', 'velox' ), __( '%d h', 'velox' ), __( '%d d', 'velox' ) ),
		'local'    => __( 'on a computer', 'velox' ),
		'edit'     => __( 'Pages', 'velox' ),
		'disc'     => __( 'Disconnect', 'velox' ),
		'discT'    => __( 'Disconnect this app?', 'velox' ),
		'discX'    => __( 'It is signed out at once. To use it again, connect it again in the app.', 'velox' ),
		'reachAll' => __( 'The whole site', 'velox' ),
		'reachSel' => __( 'Only these pages', 'velox' ),
		'find'     => __( 'Search pages', 'velox' ),
		'save'     => __( 'Save', 'velox' ),
		'cancel'   => __( 'Cancel', 'velox' ),
		'pick'     => __( 'Choose at least one page, or allow everything.', 'velox' ),
		'kinds'    => array( 'page' => __( 'Page', 'velox' ), 'template' => __( 'Template', 'velox' ), 'reusable' => __( 'Component', 'velox' ) ),
	) ); ?>;
	var APPS = <?php echo wp_json_encode( $apps ); ?>, DOCS = <?php echo wp_json_encode( $choices ); ?>;
	function cfg() { return window.VELOX_BUILDER || {}; }
	function post( fields ) {
		var C = cfg(), body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'nonce', C.nonce || '' );
		Object.keys( fields ).forEach( function ( k ) { body.set( k, fields[ k ] ); } );
		return fetch( C.ajaxurl || window.ajaxurl, { method:'POST', credentials:'same-origin', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } );
	}
	function esc( s ) { return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) { return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[ c ]; } ); }
	function say( el, text, bad ) { el.hidden = ! text; el.textContent = text || ''; el.classList.toggle( 'is-bad', !! bad ); }
	function busy( b, on, label ) { b.disabled = on; if ( on ) { b.dataset.l = b.textContent; b.textContent = label; } else if ( b.dataset.l ) { b.textContent = b.dataset.l; } }
	function ago( t ) {
		var s = Math.max( 1, Math.round( Date.now() / 1000 - t ) );
		var u = T.units, f = function ( i, n ) { return u[ i ].replace( '%d', n ); };
		if ( s < 90 ) { return f( 0, s ); } if ( s < 5400 ) { return f( 1, Math.round( s / 60 ) ); } if ( s < 129600 ) { return f( 2, Math.round( s / 3600 ) ); }
		return f( 3, Math.round( s / 86400 ) );
	}

	/* ---- Copy buttons ---- */
	document.querySelectorAll( '.vba-copy' ).forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			var el = document.getElementById( b.getAttribute( 'data-copy' ) ), txt = el ? ( 'value' in el && 'INPUT' === el.tagName ? el.value : el.textContent ) : '';
			var label = b.textContent;
			( navigator.clipboard ? navigator.clipboard.writeText( txt ) : Promise.reject() ).then( function () {
				b.textContent = T.copied; setTimeout( function () { b.textContent = label; }, 1500 );
			} ).catch( function () { if ( 'INPUT' === el.tagName ) { el.select(); } } );
		} );
	} );

	/* ---- Connector switch, tabs, check ---- */
	var sw = document.getElementById( 'vba-ag-on' ), cnBody = document.getElementById( 'vba-cn-body' ), cnState = document.getElementById( 'vba-cn-state' );
	sw.addEventListener( 'change', function () {
		post( { do:'builder_agents_toggle', on:sw.checked ? '1' : '' } )
			.then( function ( res ) {
				if ( ! res || ! res.success ) { throw new Error(); }
				var on = !! res.data.on; sw.checked = on; cnBody.hidden = ! on;
				cnState.textContent = on ? T.cnOn : T.cnOff; cnState.classList.toggle( 'is-on', on );
				document.getElementById( 'vba-apps-card' ).hidden = ! on && ! APPS.length;
				document.getElementById( 'vba-cn-rows' ).hidden = true;
			} )
			.catch( function () { sw.checked = ! sw.checked; window.vbaDialog ? vbaDialog.alert( { title:T.failed, text:T.netErr } ) : alert( T.failed ); } );
	} );
	var tabs = document.querySelectorAll( '.vba-seg [data-tab]' );
	tabs.forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			tabs.forEach( function ( x ) { var on = x === b; x.classList.toggle( 'is-on', on ); x.setAttribute( 'aria-selected', on ? 'true' : 'false' ); } );
			document.querySelectorAll( '.vba-tabp' ).forEach( function ( p ) { p.hidden = p.getAttribute( 'data-panel' ) !== b.getAttribute( 'data-tab' ); } );
		} );
	} );
	var chk = document.getElementById( 'vba-cn-check' ), rows = document.getElementById( 'vba-cn-rows' );
	chk.addEventListener( 'click', function () {
		busy( chk, true, T.checking );
		post( { do:'mcp_check' } ).then( function ( res ) {
			busy( chk, false );
			var list = res && res.success ? res.data.rows : [ { ok:false, label:T.failed, text:( res && res.data && res.data.message ) || T.netErr } ];
			rows.innerHTML = list.map( function ( r ) {
				var st = true === r.ok ? 'is-ok' : ( false === r.ok ? 'is-bad' : 'is-mid' );
				return '<li class="' + st + '"><span class="vba-chk-i" aria-hidden="true">' + ( true === r.ok ? '✓' : ( false === r.ok ? '!' : '–' ) ) + '</span><span><b>' + esc( r.label ) + '</b> ' + esc( r.text ) + '</span></li>';
			} ).join( '' );
			rows.hidden = false;
		} ).catch( function () { busy( chk, false ); } );
	} );

	/* ---- Connected apps ---- */
	var appsEl = document.getElementById( 'vba-apps' );
	function reach( a ) { return 'all' === a.docs ? T.all : ( 1 === a.docs.length ? T.page1 : T.pages.replace( '%d', a.docs.length ) ); }
	function renderApps() {
		if ( ! APPS.length ) { appsEl.innerHTML = '<li class="vba-apps-empty">' + esc( T.none ) + '</li>'; return; }
		appsEl.innerHTML = APPS.map( function ( a ) {
			var meta = [ T.by.replace( '%s', a.user ), reach( a ), a.used ? T.used.replace( '%s', ago( a.used ) ) : T.never ];
			if ( 'local' === a.kind ) { meta.splice( 1, 0, T.local ); }
			return '<li data-grant="' + esc( a.id ) + '"><span class="vba-app-ic is-' + esc( a.kind ) + '" aria-hidden="true">' + ( 'claude' === a.kind ? <?php echo wp_json_encode( Velox_MCP::claude_mark() ); ?> : esc( ( a.name || '?' ).charAt( 0 ).toUpperCase() ) ) + '</span>' +
				'<span class="vba-app-tx"><b>' + esc( a.name ) + '</b><span>' + meta.map( esc ).join( ' · ' ) + '</span></span>' +
				'<span class="vba-app-acts"><button type="button" class="vba-btn vba-btn-ghost vba-btn-sm" data-act="scope">' + esc( T.edit ) + '</button><button type="button" class="vba-btn vba-btn-ghost vba-btn-sm" data-act="revoke">' + esc( T.disc ) + '</button></span></li>';
		} ).join( '' );
	}
	function scopeForm( li, a ) {
		var old = appsEl.querySelector( '.vba-app-scope' ); if ( old ) { old.remove(); }
		var all = 'all' === a.docs, sel = all ? [] : a.docs;
		var f = document.createElement( 'li' ); f.className = 'vba-app-scope';
		f.innerHTML = '<label class="vba-rad"><input type="radio" name="vba-reach" value="all"' + ( all ? ' checked' : '' ) + '><span>' + esc( T.reachAll ) + '</span></label>' +
			'<label class="vba-rad"><input type="radio" name="vba-reach" value="chosen"' + ( all ? '' : ' checked' ) + '><span>' + esc( T.reachSel ) + '</span></label>' +
			'<div class="vba-scope-docs"' + ( all ? ' hidden' : '' ) + '><input type="search" class="vba-input-wide" placeholder="' + esc( T.find ) + '" aria-label="' + esc( T.find ) + '"><ul>' +
			DOCS.map( function ( d ) { return '<li><label><input type="checkbox" value="' + d.id + '"' + ( sel.indexOf( d.id ) > -1 ? ' checked' : '' ) + '><span>' + esc( d.title ) + '</span><em>' + esc( T.kinds[ d.kind ] || d.kind ) + '</em></label></li>'; } ).join( '' ) +
			'</ul></div><p class="vba-ai-msg is-bad" hidden></p><div class="vba-scope-acts"><button type="button" class="vba-btn vba-btn-ghost vba-btn-sm" data-act="cancel">' + esc( T.cancel ) + '</button><button type="button" class="vba-btn vba-btn-primary vba-btn-sm" data-act="save">' + esc( T.save ) + '</button></div>';
		li.after( f );
		var docsBox = f.querySelector( '.vba-scope-docs' );
		f.addEventListener( 'change', function ( e ) { if ( 'vba-reach' === e.target.name ) { docsBox.hidden = 'chosen' !== e.target.value; } } );
		f.querySelector( 'input[type=search]' ).addEventListener( 'input', function ( e ) {
			var q = e.target.value.toLowerCase(); docsBox.querySelectorAll( 'li' ).forEach( function ( x ) { x.hidden = q && x.textContent.toLowerCase().indexOf( q ) < 0; } );
		} );
		f.querySelector( '[data-act=cancel]' ).addEventListener( 'click', function () { f.remove(); } );
		f.querySelector( '[data-act=save]' ).addEventListener( 'click', function () {
			var everything = 'all' === f.querySelector( 'input[name=vba-reach]:checked' ).value;
			var ids = [].map.call( docsBox.querySelectorAll( 'input[type=checkbox]:checked' ), function ( c ) { return c.value; } );
			var msg = f.querySelector( '.vba-ai-msg' );
			if ( ! everything && ! ids.length ) { say( msg, T.pick, true ); return; }
			post( { do:'mcp_scope', grant:a.id, all:everything ? '1' : '', docs:ids.join( ',' ) } ).then( function ( res ) {
				if ( ! res || ! res.success ) { say( msg, ( res && res.data && res.data.message ) || T.failed, true ); return; }
				APPS = res.data.apps; renderApps();
			} );
		} );
	}
	appsEl.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '[data-act]' ), li = b && b.closest( '[data-grant]' );
		if ( ! li ) { return; }
		var a = APPS.filter( function ( x ) { return x.id === li.getAttribute( 'data-grant' ); } )[ 0 ]; if ( ! a ) { return; }
		if ( 'scope' === b.getAttribute( 'data-act' ) ) { scopeForm( li, a ); return; }
		var go = function () { post( { do:'mcp_revoke', grant:a.id } ).then( function ( res ) { if ( res && res.success ) { APPS = res.data.apps; renderApps(); } } ); };
		if ( window.vbaDialog && vbaDialog.confirm ) { vbaDialog.confirm( { title:T.discT, text:T.discX, ok:T.disc, danger:true }, go ); }
		else if ( window.confirm( T.discT ) ) { go(); }
	} );
	renderApps();

	/* ---- Claude in the editor (API key) ---- */
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
}() );
</script>
