<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$on = Velox_Settings::get( 'util_cookies', false );
$s  = Velox_Settings::all();
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Cookie banner</h1>
	<p class="velox-sub">A fully styleable consent banner wired to Google Consent Mode v2. Edit every colour, text and link, pick where it sits, and it stores the visitor's choice — analytics and ad tags only fire once they agree.</p>
</div>

<?php if ( ! $on ) : ?>
	<div class="velox-panel">
		<label class="velox-inline-toggle">
			<span><strong>Enable cookie banner</strong></span>
			<span class="velox-switch"><input type="checkbox" data-setting="util_cookies" id="velox-cookies-toggle"><span class="velox-switch-track"></span></span>
		</label>
		<p class="velox-hint" style="margin-top:14px;">Turn this on to show the consent banner on your site's front end and unlock the editor below.</p>
	</div>
<?php else : ?>

	<div class="vxck-admin">
		<div class="vxck-admin-controls">

			<div class="velox-panel velox-tool-form">
				<h3 class="velox-panel-title">Behaviour &amp; API</h3>
				<div class="velox-field">
					<span class="velox-field-label">Placement (desktop)</span>
					<select class="velox-select vxck-live" data-setting="cookie_layout" id="ck-layout">
						<?php
						$layouts = array(
							'bar-bottom'   => 'Bottom bar (full width)',
							'bar-top'      => 'Top bar (full width)',
							'box-bl'       => 'Floating box — bottom left',
							'box-br'       => 'Floating box — bottom right',
							'box-tl'       => 'Floating box — top left',
							'box-tr'       => 'Floating box — top right',
							'modal-center' => 'Centred modal',
						);
						foreach ( $layouts as $v => $l ) {
							printf( '<option value="%s"%s>%s</option>', esc_attr( $v ), selected( $s['cookie_layout'], $v, false ), esc_html( $l ) );
						}
						?>
					</select>
				</div>
				<div class="velox-field">
					<span class="velox-field-label">Placement (mobile)</span>
					<select class="velox-select vxck-live" data-setting="cookie_layout_mobile" id="ck-layout-mobile">
						<?php
						$layouts_m = array( 'inherit' => 'Same as desktop' ) + $layouts;
						foreach ( $layouts_m as $v => $l ) {
							printf( '<option value="%s"%s>%s</option>', esc_attr( $v ), selected( $s['cookie_layout_mobile'], $v, false ), esc_html( $l ) );
						}
						?>
					</select>
					<span class="velox-hint">Phones often work best as a bottom or top bar even when desktop is a floating box.</span>
				</div>
				<label class="velox-toggle-row">
					<div class="velox-toggle-meta">
						<span class="velox-toggle-label">Google Consent Mode v2</span>
						<span class="velox-toggle-desc">Sets consent to denied by default and updates Google tags when the visitor chooses. The correct way to stay compliant.</span>
					</div>
					<span class="velox-switch"><input type="checkbox" data-setting="cookie_consent_mode" <?php checked( ! empty( $s['cookie_consent_mode'] ) ); ?>><span class="velox-switch-track"></span></span>
				</label>
				<div class="velox-grid-2">
					<div class="velox-field"><span class="velox-field-label">GA4 / GTM ID <span class="velox-hint" style="display:inline;font-weight:400;">(optional)</span></span><input type="text" class="velox-input" data-setting="cookie_ga_id" value="<?php echo esc_attr( $s['cookie_ga_id'] ); ?>" placeholder="G-XXXXXXX or GTM-XXXXXX"></div>
					<div class="velox-field"><span class="velox-field-label">Re-ask after (days)</span><input type="number" class="velox-input velox-input--sm" data-setting="cookie_reconsent_days" value="<?php echo esc_attr( (int) $s['cookie_reconsent_days'] ); ?>"></div>
				</div>
				<div class="velox-grid-2">
					<label class="velox-toggle-row">
						<div class="velox-toggle-meta"><span class="velox-toggle-label">Offer Analytics</span></div>
						<span class="velox-switch"><input type="checkbox" data-setting="cookie_cat_analytics" <?php checked( ! empty( $s['cookie_cat_analytics'] ) ); ?>><span class="velox-switch-track"></span></span>
					</label>
					<label class="velox-toggle-row">
						<div class="velox-toggle-meta"><span class="velox-toggle-label">Offer Marketing</span></div>
						<span class="velox-switch"><input type="checkbox" data-setting="cookie_cat_marketing" <?php checked( ! empty( $s['cookie_cat_marketing'] ) ); ?>><span class="velox-switch-track"></span></span>
					</label>
				</div>
				<p class="velox-hint" style="margin-top:6px;">Tip: drop <code>&lt;a href="#cookie-settings"&gt;Cookie settings&lt;/a&gt;</code> anywhere (e.g. your footer) and it reopens this banner.</p>
			</div>

			<div class="velox-panel velox-tool-form">
				<h3 class="velox-panel-title">Text</h3>
				<div class="velox-field"><span class="velox-field-label">Heading</span><input type="text" class="velox-input vxck-live" data-setting="cookie_heading" value="<?php echo esc_attr( $s['cookie_heading'] ); ?>"></div>
				<div class="velox-field"><span class="velox-field-label">Body</span><textarea class="velox-textarea vxck-live" data-setting="cookie_body" rows="3"><?php echo esc_textarea( $s['cookie_body'] ); ?></textarea></div>
				<div class="velox-grid-2">
					<div class="velox-field"><span class="velox-field-label">Accept button</span><input type="text" class="velox-input vxck-live" data-setting="cookie_btn_accept" value="<?php echo esc_attr( $s['cookie_btn_accept'] ); ?>"></div>
					<div class="velox-field"><span class="velox-field-label">Reject button</span><input type="text" class="velox-input vxck-live" data-setting="cookie_btn_reject" value="<?php echo esc_attr( $s['cookie_btn_reject'] ); ?>"></div>
					<div class="velox-field"><span class="velox-field-label">Preferences button</span><input type="text" class="velox-input vxck-live" data-setting="cookie_btn_settings" value="<?php echo esc_attr( $s['cookie_btn_settings'] ); ?>"></div>
					<div class="velox-field"><span class="velox-field-label">Logo URL <span class="velox-hint" style="display:inline;font-weight:400;">(optional)</span></span><input type="text" class="velox-input vxck-live" data-setting="cookie_logo" value="<?php echo esc_attr( $s['cookie_logo'] ); ?>" placeholder="https://…/logo.svg"></div>
				</div>
				<div class="velox-field"><span class="velox-field-label">Small print <span class="velox-hint" style="display:inline;font-weight:400;">(optional — e.g. legal note)</span></span><textarea class="velox-textarea vxck-live" data-setting="cookie_small_text" rows="2"><?php echo esc_textarea( $s['cookie_small_text'] ); ?></textarea></div>
			</div>

			<div class="velox-panel velox-tool-form">
				<h3 class="velox-panel-title">Legal links</h3>
				<div class="velox-grid-2">
					<div class="velox-field"><span class="velox-field-label">Link 1 label</span><input type="text" class="velox-input vxck-live" data-setting="cookie_link1_label" value="<?php echo esc_attr( $s['cookie_link1_label'] ); ?>"></div>
					<div class="velox-field"><span class="velox-field-label">Link 1 URL</span><input type="text" class="velox-input" data-setting="cookie_link1_url" value="<?php echo esc_attr( $s['cookie_link1_url'] ); ?>"></div>
					<div class="velox-field"><span class="velox-field-label">Link 2 label</span><input type="text" class="velox-input vxck-live" data-setting="cookie_link2_label" value="<?php echo esc_attr( $s['cookie_link2_label'] ); ?>"></div>
					<div class="velox-field"><span class="velox-field-label">Link 2 URL</span><input type="text" class="velox-input" data-setting="cookie_link2_url" value="<?php echo esc_attr( $s['cookie_link2_url'] ); ?>"></div>
				</div>
			</div>

			<div class="velox-panel velox-tool-form">
				<h3 class="velox-panel-title">Style</h3>
				<div class="vxck-colors">
					<?php
					$colors = array(
						'cookie_bg'          => 'Background',
						'cookie_text'        => 'Text',
						'cookie_accent'      => 'Accept button',
						'cookie_accent_text' => 'Accept text',
						'cookie_btn2_bg'     => 'Other buttons',
						'cookie_btn2_text'   => 'Other btn text',
						'cookie_border_color'=> 'Border',
					);
					foreach ( $colors as $k => $lbl ) : ?>
						<label class="vxck-color">
							<input type="color" class="vxck-live" data-setting="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $s[ $k ] ); ?>">
							<span><?php echo esc_html( $lbl ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<div class="velox-grid-2" style="margin-top:14px;">
					<div class="velox-field"><span class="velox-field-label">Border width (px)</span><input type="number" class="velox-input velox-input--sm vxck-live" data-setting="cookie_border_width" value="<?php echo esc_attr( (int) $s['cookie_border_width'] ); ?>"></div>
					<div class="velox-field"><span class="velox-field-label">Corner radius (px)</span><input type="number" class="velox-input velox-input--sm vxck-live" data-setting="cookie_radius" value="<?php echo esc_attr( (int) $s['cookie_radius'] ); ?>"></div>
					<div class="velox-field"><span class="velox-field-label">Edge offset (px)</span><input type="number" class="velox-input velox-input--sm vxck-live" data-setting="cookie_offset" value="<?php echo esc_attr( (int) $s['cookie_offset'] ); ?>"></div>
					<div class="velox-field"><span class="velox-field-label">Box / modal width (px)</span><input type="number" class="velox-input velox-input--sm vxck-live" data-setting="cookie_width" value="<?php echo esc_attr( (int) $s['cookie_width'] ); ?>"><span class="velox-hint">Floating boxes &amp; modal only — bars are full width.</span></div>
					<div class="velox-field"><span class="velox-field-label">Base font size (px)</span><input type="number" class="velox-input velox-input--sm vxck-live" data-setting="cookie_font_size" value="<?php echo esc_attr( (int) $s['cookie_font_size'] ); ?>"></div>
				</div>
				<div class="velox-grid-2">
					<label class="velox-toggle-row">
						<div class="velox-toggle-meta"><span class="velox-toggle-label">Drop shadow</span></div>
						<span class="velox-switch"><input type="checkbox" class="vxck-live" data-setting="cookie_shadow" <?php checked( ! empty( $s['cookie_shadow'] ) ); ?>><span class="velox-switch-track"></span></span>
					</label>
					<label class="velox-toggle-row">
						<div class="velox-toggle-meta"><span class="velox-toggle-label">Dim background (modal)</span></div>
						<span class="velox-switch"><input type="checkbox" class="vxck-live" data-setting="cookie_overlay" <?php checked( ! empty( $s['cookie_overlay'] ) ); ?>><span class="velox-switch-track"></span></span>
					</label>
					<label class="velox-toggle-row">
						<div class="velox-toggle-meta"><span class="velox-toggle-label">Full-width buttons on mobile</span></div>
						<span class="velox-switch"><input type="checkbox" class="vxck-live" data-setting="cookie_btn_full_mobile" <?php checked( ! empty( $s['cookie_btn_full_mobile'] ) ); ?>><span class="velox-switch-track"></span></span>
					</label>
				</div>
			</div>

			<div class="velox-tool-actions">
				<button class="velox-btn velox-btn--primary velox-util-save">Save settings</button>
			</div>

			<div class="velox-panel velox-mail-disable">
				<label class="velox-inline-toggle">
					<span><strong>Cookie banner is on</strong> <span class="velox-hint" style="display:inline;">— switch off to remove it from the site.</span></span>
					<span class="velox-switch"><input type="checkbox" data-setting="util_cookies" id="velox-cookies-toggle" checked><span class="velox-switch-track"></span></span>
				</label>
			</div>
		</div>

		<aside class="vxck-admin-preview">
			<div class="vxck-preview-bar">
				<span class="vxck-preview-label">Live preview</span>
				<div class="vxck-device-tabs">
					<button type="button" class="vxck-device is-active" data-device="desktop">Desktop</button>
					<button type="button" class="vxck-device" data-device="mobile">Mobile</button>
				</div>
			</div>
			<?php
			$preview_opts = Velox_Cookies::options();
			$preview_css  = Velox_Cookies::style_block( $preview_opts, '#vxck-stage' );
			$preview_html = Velox_Cookies::markup( $preview_opts, true );
			?>
			<style id="vxck-prev-style"><?php echo $preview_css; // phpcs:ignore WordPress.Security.EscapeOutput ?></style>
			<div class="vxck-stage" id="vxck-stage" data-device="desktop">
				<div class="vxck-stage-page">
					<span class="vxck-stage-faux-h"></span>
					<span class="vxck-stage-faux-l"></span>
					<span class="vxck-stage-faux-l short"></span>
				</div>
				<div class="vxck-root" id="vxck-prev-root">
					<?php echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</div>
			</div>
			<p class="velox-hint" style="margin-top:10px;">This is the real banner — same markup and CSS your visitors get. Switch the device tabs to check both layouts.</p>
		</aside>
	</div>

	<script>
	( function () {
		var root = document.querySelector( '.vxck-admin' );
		if ( ! root ) { return; }
		var stage   = document.getElementById( 'vxck-stage' );
		var styleEl = document.getElementById( 'vxck-prev-style' );
		var rootBox = document.getElementById( 'vxck-prev-root' );
		if ( ! stage || ! styleEl || ! rootBox ) { return; }

		function val( key ) {
			var el = root.querySelector( '[data-setting="' + key + '"]' );
			if ( ! el ) { return ''; }
			return el.type === 'checkbox' ? ( el.checked ? 1 : 0 ) : el.value;
		}

		// Keys the banner renderer understands (mirror of Velox_Cookies::options()).
		var KEYS = [ 'cookie_layout','cookie_layout_mobile','cookie_heading','cookie_body',
			'cookie_btn_accept','cookie_btn_reject','cookie_btn_settings','cookie_small_text','cookie_logo',
			'cookie_link1_label','cookie_link1_url','cookie_link2_label','cookie_link2_url',
			'cookie_cat_analytics','cookie_cat_marketing','cookie_bg','cookie_text','cookie_accent',
			'cookie_accent_text','cookie_btn2_bg','cookie_btn2_text','cookie_border_color','cookie_border_width',
			'cookie_radius','cookie_shadow','cookie_overlay','cookie_offset','cookie_width','cookie_font_size',
			'cookie_btn_full_mobile' ];

		var rerenderTimer = null;
		function payload() {
			var o = {};
			KEYS.forEach( function ( k ) { o[ k ] = val( k ); } );
			return o;
		}

		// Ask the server to re-render the banner CSS+HTML with the live values, so
		// the preview is byte-identical to the front end. Debounced.
		function rerender() {
			clearTimeout( rerenderTimer );
			rerenderTimer = setTimeout( function () {
				api( 'cookie_preview', { opts: JSON.stringify( payload() ) } )
					.then( function ( r ) {
						if ( r && r.css != null ) { styleEl.textContent = r.css; }
						if ( r && r.html != null ) { rootBox.innerHTML = r.html; }
					} )
					.catch( function () {} );
			}, 200 );
		}

		root.addEventListener( 'input', rerender );
		root.addEventListener( 'change', rerender );

		// Device tabs — toggle a class the scoped CSS keys its mobile rules off.
		document.querySelectorAll( '.vxck-device' ).forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				document.querySelectorAll( '.vxck-device' ).forEach( function ( t ) { t.classList.toggle( 'is-active', t === tab ); } );
				var d = tab.getAttribute( 'data-device' );
				stage.setAttribute( 'data-device', d );
				stage.classList.toggle( 'is-mobile', d === 'mobile' );
			} );
		} );
	} )();
	</script>
<?php endif; ?>
