<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox Builder — Settings: how the builder outputs CSS and renders pages. */
$s = Velox_Builder::settings();
?>
<div class="vba-shell">
	<header class="vba-head">
		<div><h1><?php esc_html_e( 'Settings', 'velox' ); ?></h1>
		<p><?php esc_html_e( 'How Velox Builder outputs CSS and renders your pages.', 'velox' ); ?></p></div>
		<div class="vba-head-actions">
			<button type="button" class="vba-btn vba-btn-primary" id="vba-settings-save"><?php esc_html_e( 'Save changes', 'velox' ); ?></button>
		</div>
	</header>
	<div class="vba-card">
		<div class="vba-setting">
			<div class="vba-setting-tx"><b><?php esc_html_e( 'CSS output', 'velox' ); ?></b><span><?php esc_html_e( 'Write a static .css file (best for caching) or inline it in the page head.', 'velox' ); ?></span></div>
			<select id="vba-css-mode" class="vba-setting-ctrl">
				<option value="file" <?php selected( $s['css_mode'], 'file' ); ?>><?php esc_html_e( 'Static file', 'velox' ); ?></option>
				<option value="inline" <?php selected( $s['css_mode'], 'inline' ); ?>><?php esc_html_e( 'Inline', 'velox' ); ?></option>
			</select>
		</div>
		<div class="vba-setting">
			<div class="vba-setting-tx"><b><?php esc_html_e( 'Minify CSS', 'velox' ); ?></b><span><?php esc_html_e( 'Strip whitespace from generated CSS. Off writes it readable, one property per line.', 'velox' ); ?></span></div>
			<label class="vba-switch"><input type="checkbox" id="vba-minify" <?php checked( $s['minify'], 1 ); ?>><span></span></label>
		</div>
		<div class="vba-setting">
			<div class="vba-setting-tx"><b><?php esc_html_e( 'Use templates on pages not built with Velox', 'velox' ); ?></b><span><?php esc_html_e( 'Pages and posts made in the WordPress editor get your template (navbar, footer) with their content in its Inner Content slot. Off: they keep the theme.', 'velox' ); ?></span></div>
			<label class="vba-switch"><input type="checkbox" id="vba-wrap-legacy" <?php checked( (int) get_option( 'velox_builder_wrap_legacy', 0 ), 1 ); ?>><span></span></label>
		</div>
		<div class="vba-setting">
			<div class="vba-setting-tx"><b><?php esc_html_e( 'Page width', 'velox' ); ?></b><span><?php
				/* One setting, one home: page width belongs with the other global styles. */
				esc_html_e( 'Max width for centred content. This lives with the rest of your global styles.', 'velox' );
			?></span></div>
			<a class="vba-btn vba-btn-ghost vba-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Velox_Builder::SLUG . '-styles' ) ); ?>">
				<?php
				$gw = Velox_Builder::global_styles()['width']['page'];
				// The width may carry its own unit (70rem, 90%); a bare number is px.
				$gw = ( '' !== (string) $gw && is_numeric( $gw ) ) ? $gw . 'px' : $gw;
				echo esc_html( $gw ? sprintf( __( 'Currently %s — edit', 'velox' ), $gw ) : __( 'Set it in Global styles', 'velox' ) );
				?>
			</a>
		</div>
	</div>

	<?php if ( class_exists( 'Velox_Builder_Roles' ) ) :
		$vb_roles = Velox_Builder_Roles::settings(); ?>
	<div class="vba-card vba-roles">
		<div class="vba-card-h">
			<div><h2><?php esc_html_e( 'Who can use the builder', 'velox' ); ?></h2>
			<p><?php esc_html_e( 'Administrators can always do everything. Give other roles access to the pages they may edit in WordPress:', 'velox' ); ?></p>
			<ul class="vba-roles-legend">
				<li><b><?php esc_html_e( 'Client', 'velox' ); ?></b> — <?php esc_html_e( 'changes text, images, links and component fields. Layout and styles stay as they are.', 'velox' ); ?></li>
				<li><b><?php esc_html_e( 'Designer', 'velox' ); ?></b> — <?php esc_html_e( 'changes layout, styles and element settings, and can start a Velox layout for a page. No custom CSS or JavaScript.', 'velox' ); ?></li>
			</ul>
			<p><?php esc_html_e( 'Templates, components, global styles, fonts and these settings stay with administrators.', 'velox' ); ?></p></div>
		</div>
		<div class="vba-roles-list">
			<?php foreach ( Velox_Builder_Roles::roles() as $slug => $name ) :
				$cur = $vb_roles[ $slug ] ?? array( 'level' => '', 'publish' => 0 ); ?>
				<div class="vba-setting vba-role-row" data-role="<?php echo esc_attr( $slug ); ?>">
					<div class="vba-setting-tx"><b><?php echo esc_html( $name ); ?></b></div>
					<div class="vba-role-ctrls">
						<select class="vba-setting-ctrl" data-role-level aria-label="<?php echo esc_attr( sprintf( /* translators: %s: role name. */ __( 'Builder access for %s', 'velox' ), $name ) ); ?>">
							<option value="" <?php selected( $cur['level'], '' ); ?>><?php esc_html_e( 'No access', 'velox' ); ?></option>
							<option value="client" <?php selected( $cur['level'], 'client' ); ?>><?php esc_html_e( 'Client', 'velox' ); ?></option>
							<option value="designer" <?php selected( $cur['level'], 'designer' ); ?>><?php esc_html_e( 'Designer', 'velox' ); ?></option>
						</select>
						<label class="vba-role-pub"><input type="checkbox" data-role-publish <?php checked( ! empty( $cur['publish'] ) ); ?><?php disabled( '' === $cur['level'] ); ?>> <?php esc_html_e( 'May publish', 'velox' ); ?></label>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="vba-hint vba-roles-hint"><?php esc_html_e( 'Without “May publish”, their changes are saved and wait for an administrator to publish them. Every save is kept under History → Saved versions.', 'velox' ); ?></p>
	</div>
	<?php endif; ?>
</div>
<script>
( function () {
	var T = {
		saving: '<?php echo esc_js( __( 'Saving…', 'velox' ) ); ?>',
		saved: '<?php echo esc_js( __( 'Saved', 'velox' ) ); ?>',
		saveFailed: '<?php echo esc_js( __( 'Save failed', 'velox' ) ); ?>',
		saveChanges: '<?php echo esc_js( __( 'Save changes', 'velox' ) ); ?>'
	};
	// The config is printed in the footer, after this script, so read it when used.
	function cfg() { return window.VELOX_BUILDER || {}; }
	document.getElementById( 'vba-settings-save' ).addEventListener( 'click', function () {
		var btn = this; btn.textContent = T.saving;
		var C = cfg();
		var body = new URLSearchParams();
		body.set( 'action', 'velox' ); body.set( 'do', 'builder_settings_save' ); body.set( 'nonce', C.nonce || '' );
		body.set( 'css_mode', document.getElementById( 'vba-css-mode' ).value );
		body.set( 'minify', document.getElementById( 'vba-minify' ).checked ? '1' : '' );
		body.set( 'wrap_legacy', document.getElementById( 'vba-wrap-legacy' ).checked ? '1' : '0' );
		var roles = {};
		document.querySelectorAll( '.vba-role-row' ).forEach( function ( row ) {
			roles[ row.getAttribute( 'data-role' ) ] = { level:row.querySelector( '[data-role-level]' ).value, publish:row.querySelector( '[data-role-publish]' ).checked ? 1 : 0 };
		} );
		body.set( 'roles', JSON.stringify( roles ) );
		fetch( C.ajaxurl || window.ajaxurl, { method:'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded' }, body:body.toString() } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) { btn.textContent = res && res.success ? T.saved : T.saveFailed; setTimeout( function () { btn.textContent = T.saveChanges; }, 1500 ); } )
			.catch( function () { btn.textContent = T.saveFailed; setTimeout( function () { btn.textContent = T.saveChanges; }, 2500 ); } );
	} );
	// "May publish" only means something with access.
	document.addEventListener( 'change', function ( e ) {
		var lv = e.target.closest && e.target.closest( '[data-role-level]' );
		if ( ! lv ) { return; }
		var pub = lv.closest( '.vba-role-row' ).querySelector( '[data-role-publish]' );
		pub.disabled = ! lv.value;
		if ( ! lv.value ) { pub.checked = false; }
	} );
}() );
</script>
