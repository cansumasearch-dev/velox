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
				<div class="velox-field"><span class="velox-field-label">Logo URL <span class="velox-hint" style="display:inline;font-weight:400;">(optional)</span></span><input type="text" class="velox-input vxck-live" data-setting="cookie_logo" value="<?php echo esc_attr( $s['cookie_logo'] ); ?>" placeholder="https://…/logo.svg"></div>
				<div class="velox-field"><span class="velox-field-label">Small print <span class="velox-hint" style="display:inline;font-weight:400;">(optional — e.g. legal note)</span></span><textarea class="velox-textarea vxck-live" data-setting="cookie_small_text" rows="2"><?php echo esc_textarea( $s['cookie_small_text'] ); ?></textarea></div>
			</div>

			<div class="velox-panel velox-tool-form">
				<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:4px;">
					<h3 class="velox-panel-title" style="margin:0;">Buttons</h3>
					<button type="button" class="velox-btn velox-btn--ghost" id="ckb-add">
						<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="margin-right:5px;"><path d="M12 5v14M5 12h14"/></svg>Add button
					</button>
				</div>
				<p class="velox-hint" style="margin:0 0 12px;">Add, remove and reorder buttons. Each one can be a button or a link, do something (accept, reject, open preferences, save) or point to a URL, and be styled individually.</p>
				<input type="hidden" data-setting="cookie_buttons" id="ckb-data" value="<?php echo esc_attr( $s['cookie_buttons'] ); ?>">
				<div id="ckb-list" class="ckb-list"></div>
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

			<div class="velox-panel velox-tool-form">
				<h3 class="velox-panel-title">Typography &amp; advanced</h3>
				<div class="velox-grid-2">
					<div class="velox-field"><span class="velox-field-label">Heading size (px) <span class="velox-hint" style="display:inline;font-weight:400;">0 = auto</span></span><input type="number" class="velox-input velox-input--sm vxck-live" data-setting="cookie_heading_size" value="<?php echo esc_attr( (int) $s['cookie_heading_size'] ); ?>"></div>
					<div class="velox-field"><span class="velox-field-label">Heading weight</span><input type="number" class="velox-input velox-input--sm vxck-live" data-setting="cookie_heading_weight" value="<?php echo esc_attr( (int) $s['cookie_heading_weight'] ); ?>" placeholder="0 = auto"></div>
					<div class="velox-field"><span class="velox-field-label">Heading colour</span><input type="text" class="velox-input vxck-live" data-setting="cookie_heading_color" value="<?php echo esc_attr( $s['cookie_heading_color'] ); ?>" placeholder="inherit"></div>
					<div class="velox-field"><span class="velox-field-label">Body size (px)</span><input type="number" class="velox-input velox-input--sm vxck-live" data-setting="cookie_body_size" value="<?php echo esc_attr( (int) $s['cookie_body_size'] ); ?>" placeholder="0 = auto"></div>
					<div class="velox-field"><span class="velox-field-label">Body colour</span><input type="text" class="velox-input vxck-live" data-setting="cookie_body_color" value="<?php echo esc_attr( $s['cookie_body_color'] ); ?>" placeholder="inherit"></div>
					<div class="velox-field"><span class="velox-field-label">Legal-link colour</span><input type="text" class="velox-input vxck-live" data-setting="cookie_link_color" value="<?php echo esc_attr( $s['cookie_link_color'] ); ?>" placeholder="inherit"></div>
					<div class="velox-field"><span class="velox-field-label">Button gap (px)</span><input type="number" class="velox-input velox-input--sm vxck-live" data-setting="cookie_btn_gap" value="<?php echo esc_attr( (int) $s['cookie_btn_gap'] ); ?>"></div>
					<div class="velox-field"><span class="velox-field-label">Button font size (px)</span><input type="number" class="velox-input velox-input--sm vxck-live" data-setting="cookie_btn_font_size" value="<?php echo esc_attr( (int) $s['cookie_btn_font_size'] ); ?>"></div>
					<div class="velox-field"><span class="velox-field-label">Button font weight</span><input type="number" class="velox-input velox-input--sm vxck-live" data-setting="cookie_btn_font_weight" value="<?php echo esc_attr( (int) $s['cookie_btn_font_weight'] ); ?>"></div>
					<div class="velox-field"><span class="velox-field-label">Overlay colour</span><input type="text" class="velox-input vxck-live" data-setting="cookie_overlay_color" value="<?php echo esc_attr( $s['cookie_overlay_color'] ); ?>" placeholder="rgba(10,12,20,.45)"></div>
					<div class="velox-field"><span class="velox-field-label">Overlay blur (px)</span><input type="number" class="velox-input velox-input--sm vxck-live" data-setting="cookie_backdrop_blur" value="<?php echo esc_attr( (int) $s['cookie_backdrop_blur'] ); ?>"></div>
					<div class="velox-field"><span class="velox-field-label">Max height (px) <span class="velox-hint" style="display:inline;font-weight:400;">0 = none</span></span><input type="number" class="velox-input velox-input--sm vxck-live" data-setting="cookie_max_height" value="<?php echo esc_attr( (int) $s['cookie_max_height'] ); ?>"></div>
					<div class="velox-field"><span class="velox-field-label">z-index <span class="velox-hint" style="display:inline;font-weight:400;">0 = default</span></span><input type="number" class="velox-input velox-input--sm vxck-live" data-setting="cookie_z_index" value="<?php echo esc_attr( (int) $s['cookie_z_index'] ); ?>"></div>
				</div>
				<label class="velox-toggle-row" style="margin-top:6px;">
					<div class="velox-toggle-meta"><span class="velox-toggle-label">Underline legal links</span></div>
					<span class="velox-switch"><input type="checkbox" class="vxck-live" data-setting="cookie_link_underline" <?php checked( ! empty( $s['cookie_link_underline'] ) ); ?>><span class="velox-switch-track"></span></span>
				</label>
			</div>

			<div class="velox-panel velox-tool-form">
				<h3 class="velox-panel-title">Custom CSS</h3>
				<p class="velox-hint" style="margin:0 0 10px;">Full control — target any element inside the banner. Scope your rules with <code>.vxck</code> (the banner) or <code>.vxck-b-&lt;id&gt;</code> (a specific button). Applied on top of everything above.</p>
				<textarea class="velox-textarea vxck-live" data-setting="cookie_custom_css" rows="6" spellcheck="false" placeholder=".vxck{font-family:Comfortaa,sans-serif;}&#10;.vxck-b-b1:hover{transform:translateY(-2px);}"><?php echo esc_textarea( $s['cookie_custom_css'] ); ?></textarea>
			</div>

			<div class="velox-panel velox-tool-form vxck-layout-panel">
				<div class="vxck-layout-head">
					<div>
						<h3 class="velox-panel-title" style="margin:0;">Layout</h3>
						<p class="velox-hint" style="margin:4px 0 0;">Control the box like you would in Oxygen — display, direction, spacing and alignment. Switch to Custom to unlock the controls.</p>
					</div>
					<div class="vxck-mode-seg" role="tablist" aria-label="Layout mode">
						<button type="button" class="vxck-mode-btn<?php echo 'custom' !== $s['cookie_layout_mode'] ? ' is-active' : ''; ?>" data-mode="preset">Preset</button>
						<button type="button" class="vxck-mode-btn<?php echo 'custom' === $s['cookie_layout_mode'] ? ' is-active' : ''; ?>" data-mode="custom">Custom</button>
					</div>
				</div>
				<input type="hidden" class="vxck-live" data-setting="cookie_layout_mode" id="ck-layout-mode" value="<?php echo esc_attr( $s['cookie_layout_mode'] ); ?>">

				<div class="vxck-layout-controls<?php echo 'custom' === $s['cookie_layout_mode'] ? '' : ' is-locked'; ?>" id="ck-layout-controls">
					<div class="velox-field">
						<span class="velox-field-label">Display</span>
						<div class="vxck-seg" data-seg="cookie_display">
							<?php foreach ( array( 'flex' => 'Flex', 'grid' => 'Grid', 'block' => 'Block' ) as $v => $l ) : ?>
								<button type="button" class="vxck-seg-btn vxck-live<?php echo $s['cookie_display'] === $v ? ' is-active' : ''; ?>" data-setting="cookie_display" data-value="<?php echo esc_attr( $v ); ?>"><?php echo esc_html( $l ); ?></button>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="vxck-when-flex"<?php echo 'flex' !== $s['cookie_display'] ? ' hidden' : ''; ?>>
						<div class="velox-field">
							<span class="velox-field-label">Direction</span>
							<div class="vxck-seg" data-seg="cookie_direction">
								<?php foreach ( array( 'row' => 'Row →', 'column' => 'Column ↓' ) as $v => $l ) : ?>
									<button type="button" class="vxck-seg-btn vxck-live<?php echo $s['cookie_direction'] === $v ? ' is-active' : ''; ?>" data-setting="cookie_direction" data-value="<?php echo esc_attr( $v ); ?>"><?php echo esc_html( $l ); ?></button>
								<?php endforeach; ?>
							</div>
						</div>
						<div class="velox-grid-2">
							<div class="velox-field">
								<span class="velox-field-label">Align items</span>
								<select class="velox-select vxck-live" data-setting="cookie_align">
									<?php foreach ( array( 'flex-start' => 'Start', 'center' => 'Center', 'flex-end' => 'End', 'stretch' => 'Stretch' ) as $v => $l ) : ?>
										<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $s['cookie_align'], $v ); ?>><?php echo esc_html( $l ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="velox-field">
								<span class="velox-field-label">Justify content</span>
								<select class="velox-select vxck-live" data-setting="cookie_justify">
									<?php foreach ( array( 'flex-start' => 'Start', 'center' => 'Center', 'flex-end' => 'End', 'space-between' => 'Space between', 'space-around' => 'Space around' ) as $v => $l ) : ?>
										<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $s['cookie_justify'], $v ); ?>><?php echo esc_html( $l ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>
					</div>

					<div class="vxck-when-grid"<?php echo 'grid' !== $s['cookie_display'] ? ' hidden' : ''; ?>>
						<div class="velox-field">
							<span class="velox-field-label">Grid columns</span>
							<input type="number" min="1" max="4" class="velox-input velox-input--sm vxck-live" data-setting="cookie_grid_cols" value="<?php echo esc_attr( (int) $s['cookie_grid_cols'] ); ?>">
						</div>
					</div>

					<div class="velox-field">
						<span class="velox-field-label">Gap (px)</span>
						<input type="range" min="0" max="64" class="velox-range vxck-live" data-setting="cookie_gap" value="<?php echo esc_attr( (int) $s['cookie_gap'] ); ?>" oninput="this.nextElementSibling.textContent=this.value+'px'">
						<span class="vxck-range-val"><?php echo (int) $s['cookie_gap']; ?>px</span>
					</div>

					<div class="velox-grid-2">
						<div class="velox-field">
							<span class="velox-field-label">Padding Y (px)</span>
							<input type="number" min="0" max="80" class="velox-input velox-input--sm vxck-live" data-setting="cookie_pad_y" value="<?php echo esc_attr( (int) $s['cookie_pad_y'] ); ?>">
						</div>
						<div class="velox-field">
							<span class="velox-field-label">Padding X (px)</span>
							<input type="number" min="0" max="80" class="velox-input velox-input--sm vxck-live" data-setting="cookie_pad_x" value="<?php echo esc_attr( (int) $s['cookie_pad_x'] ); ?>">
						</div>
					</div>

					<div class="velox-field">
						<span class="velox-field-label">Outer margin (px)</span>
						<input type="number" min="0" max="60" class="velox-input velox-input--sm vxck-live" data-setting="cookie_margin" value="<?php echo esc_attr( (int) $s['cookie_margin'] ); ?>">
						<span class="velox-hint">Space around the whole box. Useful for floating layouts.</span>
					</div>
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
			// Segmented control: read the active button's data-value.
			var seg = root.querySelector( '.vxck-seg-btn.is-active[data-setting="' + key + '"]' );
			if ( seg ) { return seg.getAttribute( 'data-value' ); }
			var el = root.querySelector( '[data-setting="' + key + '"]' );
			if ( ! el ) { return ''; }
			return el.type === 'checkbox' ? ( el.checked ? 1 : 0 ) : el.value;
		}

		// Keys the banner renderer understands (mirror of Velox_Cookies::options()).
		var KEYS = [ 'cookie_layout','cookie_layout_mobile','cookie_heading','cookie_body',
			'cookie_small_text','cookie_logo',
			'cookie_link1_label','cookie_link1_url','cookie_link2_label','cookie_link2_url',
			'cookie_cat_analytics','cookie_cat_marketing','cookie_bg','cookie_text','cookie_accent',
			'cookie_accent_text','cookie_btn2_bg','cookie_btn2_text','cookie_border_color','cookie_border_width',
			'cookie_radius','cookie_shadow','cookie_overlay','cookie_offset','cookie_width','cookie_font_size',
			'cookie_btn_full_mobile',
			'cookie_layout_mode','cookie_display','cookie_direction','cookie_align','cookie_justify',
			'cookie_gap','cookie_grid_cols','cookie_pad_y','cookie_pad_x','cookie_margin',
			'cookie_buttons','cookie_custom_css',
			'cookie_heading_size','cookie_heading_weight','cookie_heading_color',
			'cookie_body_size','cookie_body_color','cookie_link_color','cookie_link_underline',
			'cookie_btn_gap','cookie_btn_font_size','cookie_btn_font_weight',
			'cookie_backdrop_blur','cookie_overlay_color','cookie_max_height','cookie_z_index' ];

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

		// --- Oxygen-style layout controls ---
		var modeInput = document.getElementById( 'ck-layout-mode' );
		var controls  = document.getElementById( 'ck-layout-controls' );

		// Preset / Custom mode segment.
		root.querySelectorAll( '.vxck-mode-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var mode = btn.getAttribute( 'data-mode' );
				root.querySelectorAll( '.vxck-mode-btn' ).forEach( function ( b ) { b.classList.toggle( 'is-active', b === btn ); } );
				if ( modeInput ) { modeInput.value = mode; }
				if ( controls ) { controls.classList.toggle( 'is-locked', mode !== 'custom' ); }
				rerender();
			} );
		} );

		// Segmented value controls (display / direction).
		root.querySelectorAll( '.vxck-seg-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var key = btn.getAttribute( 'data-setting' );
				root.querySelectorAll( '.vxck-seg-btn[data-setting="' + key + '"]' ).forEach( function ( b ) {
					b.classList.toggle( 'is-active', b === btn );
				} );
				if ( key === 'cookie_display' ) { syncDisplay(); }
				rerender();
			} );
		} );

		// Show the flex- or grid-specific controls based on the chosen display.
		function syncDisplay() {
			var disp = val( 'cookie_display' );
			var flexEl = root.querySelector( '.vxck-when-flex' );
			var gridEl = root.querySelector( '.vxck-when-grid' );
			if ( flexEl ) { flexEl.hidden = ( disp !== 'flex' ); }
			if ( gridEl ) { gridEl.hidden = ( disp !== 'grid' ); }
		}
		syncDisplay();

		// Device tabs — toggle a class the scoped CSS keys its mobile rules off.
		document.querySelectorAll( '.vxck-device' ).forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				document.querySelectorAll( '.vxck-device' ).forEach( function ( t ) { t.classList.toggle( 'is-active', t === tab ); } );
				var d = tab.getAttribute( 'data-device' );
				stage.setAttribute( 'data-device', d );
				stage.classList.toggle( 'is-mobile', d === 'mobile' );
			} );
		} );

		/* =====================  Button manager  ===================== */
		( function () {
			var dataEl = document.getElementById( 'ckb-data' );
			var listEl = document.getElementById( 'ckb-list' );
			var addBtn = document.getElementById( 'ckb-add' );
			if ( ! dataEl || ! listEl ) { return; }

			var ACTIONS = { accept: 'Accept all cookies', reject: 'Reject non-essential', preferences: 'Open preferences', save: 'Save chosen preferences', link: 'Go to a URL (link)' };
			var VARIANTS = { primary: 'Primary', secondary: 'Secondary', ghost: 'Ghost / text', custom: 'Custom (use styles below)' };
			var STYLE_FIELDS = [
				[ 'bg', 'Background', 'color' ], [ 'color', 'Text colour', 'color' ],
				[ 'hover_bg', 'Hover background', 'color' ], [ 'hover_color', 'Hover text', 'color' ],
				[ 'border_color', 'Border colour', 'color' ], [ 'border_width', 'Border width', 'num' ],
				[ 'radius', 'Corner radius', 'num' ], [ 'pad_y', 'Padding Y', 'num' ], [ 'pad_x', 'Padding X', 'num' ],
				[ 'font_size', 'Font size', 'num' ], [ 'font_weight', 'Font weight', 'num' ]
			];
			var openIdx = -1;
			var list = [];
			try { list = JSON.parse( dataEl.value || '[]' ); } catch ( e ) { list = []; }
			if ( ! Array.isArray( list ) || ! list.length ) {
				list = [ { id: 'b1', label: 'Accept all', action: 'accept', element: 'button', url: '', variant: 'primary' } ];
			}

			function esc( s ) { return ( s == null ? '' : String( s ) ).replace( /[&<>"]/g, function ( c ) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[ c ]; } ); }
			function uid() { return 'b' + Math.random().toString( 36 ).slice( 2, 7 ); }

			function sync() {
				dataEl.value = JSON.stringify( list );
				rerender();
			}
			function opt( map, sel ) { return Object.keys( map ).map( function ( k ) { return '<option value="' + k + '"' + ( k === sel ? ' selected' : '' ) + '>' + esc( map[ k ] ) + '</option>'; } ).join( '' ); }

			function render() {
				listEl.innerHTML = '';
				if ( ! list.length ) { listEl.innerHTML = '<div class="ckb-empty">No buttons. Click “Add button”.</div>'; sync(); return; }
				list.forEach( function ( b, i ) {
					b.style = b.style || {};
					var open = i === openIdx;
					var card = document.createElement( 'div' );
					card.className = 'ckb-item' + ( open ? ' is-open' : '' );
					var actLabel = ACTIONS[ b.action ] || b.action;
					var head = '<div class="ckb-row">' +
						'<span class="ckb-handle" title="Drag to reorder"><svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/><circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/><circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/></svg></span>' +
						'<span class="ckb-pill ckb-pill--' + esc( b.element ) + '">' + ( b.element === 'link' ? 'Link' : 'Button' ) + '</span>' +
						'<span class="ckb-main"><span class="ckb-label">' + esc( b.label || 'Untitled' ) + '</span><span class="ckb-meta">' + esc( actLabel ) + '</span></span>' +
						'<span class="ckb-acts">' +
							'<button type="button" class="ckb-ic" data-act="dup" title="Duplicate"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="9" y="9" width="11" height="11" rx="2.5"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg></button>' +
							'<button type="button" class="ckb-ic ckb-del" data-act="del" title="Delete"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-13M9 7V4h6v3"/></svg></button>' +
							'<button type="button" class="ckb-ic" data-act="toggle"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7"><path d="' + ( open ? 'M18 15l-6-6-6 6' : 'M6 9l6 6 6-6' ) + '"/></svg></button>' +
						'</span></div>';
					var body = '';
					if ( open ) {
						var styleRows = STYLE_FIELDS.map( function ( f ) {
							var v = b.style[ f[0] ] != null ? b.style[ f[0] ] : '';
							if ( f[2] === 'color' ) {
								return '<div class="ckb-mini"><span class="ckb-mini-l">' + f[1] + '</span><input class="velox-input ckb-sf" data-sk="' + f[0] + '" value="' + esc( v ) + '" placeholder="inherit"></div>';
							}
							return '<div class="ckb-mini"><span class="ckb-mini-l">' + f[1] + '</span><input type="number" class="velox-input velox-input--sm ckb-sf" data-sk="' + f[0] + '" value="' + esc( v ) + '"></div>';
						} ).join( '' );
						body = '<div class="ckb-body"><div class="ckb-grid">' +
							'<div class="ckb-mini"><span class="ckb-mini-l">Label</span><input class="velox-input ckb-f" data-k="label" value="' + esc( b.label ) + '"></div>' +
							'<div class="ckb-mini"><span class="ckb-mini-l">Type</span><select class="velox-select ckb-f" data-k="element"><option value="button"' + ( b.element !== 'link' ? ' selected' : '' ) + '>Button</option><option value="link"' + ( b.element === 'link' ? ' selected' : '' ) + '>Link</option></select></div>' +
							'<div class="ckb-mini"><span class="ckb-mini-l">Action</span><select class="velox-select ckb-f" data-k="action">' + opt( ACTIONS, b.action ) + '</select></div>' +
							'<div class="ckb-mini ckb-url"' + ( b.action === 'link' ? '' : ' hidden' ) + '><span class="ckb-mini-l">URL</span><input class="velox-input ckb-f" data-k="url" value="' + esc( b.url || '' ) + '" placeholder="https://…"></div>' +
							'<div class="ckb-mini ckb-mini--full"><span class="ckb-mini-l">Preset style</span><select class="velox-select ckb-f" data-k="variant">' + opt( VARIANTS, b.variant ) + '</select></div>' +
							'</div>' +
							'<div class="ckb-style-h">Per-button styling <span class="velox-hint" style="font-weight:400;">(leave blank to use the preset)</span></div>' +
							'<div class="ckb-grid ckb-style-grid">' + styleRows + '</div>' +
						'</div>';
					}
					card.innerHTML = head + body;

					card.querySelector( '.ckb-row' ).addEventListener( 'click', function ( e ) {
						if ( e.target.closest( '.ckb-ic' ) || e.target.closest( '.ckb-handle' ) ) { return; }
						openIdx = open ? -1 : i; render();
					} );
					card.querySelectorAll( '.ckb-ic' ).forEach( function ( btn ) {
						btn.addEventListener( 'click', function ( e ) {
							e.stopPropagation();
							var act = btn.getAttribute( 'data-act' );
							if ( act === 'del' ) { list.splice( i, 1 ); if ( openIdx >= list.length ) { openIdx = list.length - 1; } render(); }
							else if ( act === 'dup' ) { var c = JSON.parse( JSON.stringify( b ) ); c.id = uid(); list.splice( i + 1, 0, c ); openIdx = i + 1; render(); }
							else { openIdx = open ? -1 : i; render(); }
						} );
					} );
					card.querySelectorAll( '.ckb-f' ).forEach( function ( el ) {
						var ev = ( el.tagName === 'SELECT' ) ? 'change' : 'input';
						el.addEventListener( ev, function () {
							var k = el.getAttribute( 'data-k' );
							b[ k ] = el.value;
							if ( k === 'action' ) { var u = card.querySelector( '.ckb-url' ); if ( u ) { u.hidden = ( el.value !== 'link' ); } }
							if ( k === 'element' || k === 'action' || k === 'label' ) { render(); } else { sync(); }
						} );
					} );
					card.querySelectorAll( '.ckb-sf' ).forEach( function ( el ) {
						el.addEventListener( 'input', function () {
							var sk = el.getAttribute( 'data-sk' );
							if ( el.value === '' ) { delete b.style[ sk ]; } else { b.style[ sk ] = el.value; }
							sync();
						} );
					} );
					// drag to reorder
					card.setAttribute( 'draggable', 'true' );
					card.addEventListener( 'dragstart', function () { window.__ckbFrom = i; card.classList.add( 'is-drag' ); } );
					card.addEventListener( 'dragend', function () { card.classList.remove( 'is-drag' ); } );
					card.addEventListener( 'dragover', function ( e ) { e.preventDefault(); } );
					card.addEventListener( 'drop', function ( e ) {
						e.preventDefault();
						var from = window.__ckbFrom;
						if ( from == null || from === i ) { return; }
						var moved = list.splice( from, 1 )[0];
						list.splice( i, 0, moved ); openIdx = i; window.__ckbFrom = null; render();
					} );
					listEl.appendChild( card );
				} );
				sync();
			}

			if ( addBtn ) {
				addBtn.addEventListener( 'click', function () {
					list.push( { id: uid(), label: 'New button', action: 'accept', element: 'button', url: '', variant: 'secondary', style: {} } );
					openIdx = list.length - 1; render();
				} );
			}
			render();
		} )();
	} )();
	</script>
<?php endif; ?>
