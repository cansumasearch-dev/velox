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
					<span class="velox-field-label">Placement</span>
					<select class="velox-select" data-setting="cookie_layout" id="ck-layout">
						<?php
						$layouts = array(
							'bar-bottom'   => 'Bottom bar (full width)',
							'box-bl'       => 'Floating box — bottom left',
							'box-br'       => 'Floating box — bottom right',
							'modal-center' => 'Centred modal',
						);
						foreach ( $layouts as $v => $l ) {
							printf( '<option value="%s"%s>%s</option>', esc_attr( $v ), selected( $s['cookie_layout'], $v, false ), esc_html( $l ) );
						}
						?>
					</select>
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
					<div class="velox-field"><span class="velox-field-label">Edge offset (px)</span><input type="number" class="velox-input velox-input--sm" data-setting="cookie_offset" value="<?php echo esc_attr( (int) $s['cookie_offset'] ); ?>"></div>
				</div>
				<div class="velox-grid-2">
					<label class="velox-toggle-row">
						<div class="velox-toggle-meta"><span class="velox-toggle-label">Drop shadow</span></div>
						<span class="velox-switch"><input type="checkbox" class="vxck-live" data-setting="cookie_shadow" <?php checked( ! empty( $s['cookie_shadow'] ) ); ?>><span class="velox-switch-track"></span></span>
					</label>
					<label class="velox-toggle-row">
						<div class="velox-toggle-meta"><span class="velox-toggle-label">Dim background (modal)</span></div>
						<span class="velox-switch"><input type="checkbox" data-setting="cookie_overlay" <?php checked( ! empty( $s['cookie_overlay'] ) ); ?>><span class="velox-switch-track"></span></span>
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
			<span class="vxck-preview-label">Live preview</span>
			<div class="vxck-stage" id="vxck-stage">
				<div class="vxck-prev" id="vxck-prev">
					<img class="vxck-prev-logo" id="ck-logo" alt="" hidden>
					<p class="vxck-prev-h" id="ck-h"></p>
					<p class="vxck-prev-body" id="ck-body"></p>
					<div class="vxck-prev-links" id="ck-links"></div>
					<div class="vxck-prev-actions">
						<button class="vxck-prev-btn" id="ck-accept"></button>
						<button class="vxck-prev-btn vxck-prev-btn2" id="ck-reject"></button>
						<button class="vxck-prev-btn vxck-prev-btn2" id="ck-prefs"></button>
					</div>
					<p class="vxck-prev-small" id="ck-small"></p>
				</div>
			</div>
		</aside>
	</div>

	<script>
	( function () {
		var root = document.querySelector( '.vxck-admin' );
		if ( ! root ) { return; }
		function val( key ) { var el = root.querySelector( '[data-setting="' + key + '"]' ); if ( ! el ) { return ''; } return el.type === 'checkbox' ? el.checked : el.value; }
		function px( k, d ) { var n = parseInt( val( k ), 10 ); return isNaN( n ) ? d : n; }
		var prev = document.getElementById( 'vxck-prev' );
		function render() {
			var bg = val('cookie_bg'), tx = val('cookie_text'), ac = val('cookie_accent'), act = val('cookie_accent_text'),
				b2 = val('cookie_btn2_bg'), b2t = val('cookie_btn2_text'), bc = val('cookie_border_color'),
				bw = px('cookie_border_width',1), rad = px('cookie_radius',16);
			prev.style.background = bg; prev.style.color = tx;
			prev.style.border = bw + 'px solid ' + bc;
			prev.style.borderRadius = rad + 'px';
			prev.style.boxShadow = val('cookie_shadow') ? '0 18px 50px rgba(15,18,30,.18)' : 'none';
			var logo = document.getElementById('ck-logo'), lu = val('cookie_logo');
			if ( lu ) { logo.src = lu; logo.hidden = false; } else { logo.hidden = true; }
			document.getElementById('ck-h').textContent = val('cookie_heading');
			document.getElementById('ck-h').style.display = val('cookie_heading') ? '' : 'none';
			document.getElementById('ck-body').textContent = val('cookie_body');
			var links = document.getElementById('ck-links'); links.innerHTML = '';
			[['cookie_link1_label'],['cookie_link2_label']].forEach( function ( p ) {
				var t = val(p[0]); if ( t ) { var a = document.createElement('a'); a.textContent = t; a.href='#'; a.style.color = ac; links.appendChild(a); }
			} );
			var accept = document.getElementById('ck-accept'); accept.textContent = val('cookie_btn_accept'); accept.style.background = ac; accept.style.color = act;
			var rej = document.getElementById('ck-reject'); rej.textContent = val('cookie_btn_reject'); rej.style.background = b2; rej.style.color = b2t;
			var pf = document.getElementById('ck-prefs'); pf.textContent = val('cookie_btn_settings'); pf.style.background = b2; pf.style.color = b2t;
			var sm = document.getElementById('ck-small'); sm.textContent = val('cookie_small_text'); sm.style.display = val('cookie_small_text') ? '' : 'none';
			prev.querySelectorAll('.vxck-prev-btn').forEach(function(b){ b.style.borderRadius = Math.max(6, Math.round(rad*0.6)) + 'px'; });
		}
		root.addEventListener( 'input', render );
		root.addEventListener( 'change', render );
		render();
	} )();
	</script>
<?php endif; ?>
