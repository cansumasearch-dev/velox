<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s            = Velox_Settings::all();
$logo_default = VELOX_URL . 'assets/logo.png';
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Maintenance mode</h1>
	<p class="velox-sub">Shows visitors a branded holding page while you work. You and any other admins keep seeing the live site, and wp-admin stays reachable. Sends a 503 so search engines know it's temporary.</p>
</div>

<div class="velox-maint-layout">
	<div class="velox-panel velox-tool-form" data-tool="maintenance">
		<div class="velox-toggle-row">
			<div class="velox-toggle-meta">
				<span class="velox-toggle-label">Enable maintenance mode</span>
				<span class="velox-toggle-desc">Front end shows the holding page to everyone except logged-in admins.</span>
			</div>
			<label class="velox-switch"><input type="checkbox" data-setting="util_maintenance" <?php checked( ! empty( $s['util_maintenance'] ) ); ?>><span class="velox-switch-track"></span></label>
		</div>

		<div class="velox-field">
			<span class="velox-field-label">Heading</span>
			<input type="text" class="velox-input" data-setting="util_maintenance_title" value="<?php echo esc_attr( $s['util_maintenance_title'] ); ?>">
		</div>

		<div class="velox-field">
			<span class="velox-field-label">Message</span>
			<textarea class="velox-textarea" data-setting="util_maintenance_message" rows="3"><?php echo esc_textarea( $s['util_maintenance_message'] ); ?></textarea>
			<span class="velox-hint">Shown under the heading. Line breaks are kept.</span>
		</div>

		<div class="velox-field">
			<span class="velox-field-label">Footer text <span style="color:var(--vx-ink-3);font-weight:500;">(optional)</span></span>
			<input type="text" class="velox-input" data-setting="util_maintenance_brand" value="<?php echo esc_attr( $s['util_maintenance_brand'] ); ?>" placeholder="e.g. your brand name — leave empty to hide">
			<span class="velox-hint">Small line at the very bottom. Empty = nothing shown (no site name forced in).</span>
		</div>

		<div class="velox-field">
			<span class="velox-field-label">Logo</span>
			<div class="velox-media-row">
				<input type="text" class="velox-input" data-setting="util_maintenance_logo" value="<?php echo esc_attr( $s['util_maintenance_logo'] ); ?>" placeholder="<?php echo esc_attr( $logo_default ); ?>">
				<button type="button" class="velox-btn velox-btn--ghost velox-media-pick" data-target="util_maintenance_logo">Choose</button>
				<button type="button" class="velox-btn velox-btn--ghost velox-media-clear" data-target="util_maintenance_logo">Reset</button>
			</div>
			<span class="velox-hint">Image, GIF, or Lottie (.json / .lottie) URL. Leave empty to use the default Velox mark.</span>
		</div>

		<div class="velox-field">
			<span class="velox-field-label">Background image <span style="color:var(--vx-ink-3);font-weight:500;">(optional)</span></span>
			<div class="velox-media-row">
				<input type="text" class="velox-input" data-setting="util_maintenance_bgimage" value="<?php echo esc_attr( $s['util_maintenance_bgimage'] ); ?>" placeholder="No background image">
				<button type="button" class="velox-btn velox-btn--ghost velox-media-pick" data-target="util_maintenance_bgimage">Choose</button>
				<button type="button" class="velox-btn velox-btn--ghost velox-media-clear" data-target="util_maintenance_bgimage">Clear</button>
			</div>
			<span class="velox-hint">Sits behind a tint of your background colour so text stays readable.</span>
		</div>

		<div class="velox-field">
			<span class="velox-field-label">Colours</span>
			<div class="velox-color-grid">
				<label class="velox-color-item"><span>Background</span><input type="color" data-setting="util_maintenance_bg" value="<?php echo esc_attr( $s['util_maintenance_bg'] ); ?>"></label>
				<label class="velox-color-item"><span>Text</span><input type="color" data-setting="util_maintenance_text" value="<?php echo esc_attr( $s['util_maintenance_text'] ); ?>"></label>
				<label class="velox-color-item"><span>Accent</span><input type="color" data-setting="util_maintenance_accent" value="<?php echo esc_attr( $s['util_maintenance_accent'] ); ?>"></label>
			</div>
		</div>

		<div class="velox-field">
			<span class="velox-field-label">Loading animation</span>
			<select class="velox-select" data-setting="util_maintenance_anim">
				<?php
				$anims = array( 'bar' => 'Sliding bar', 'pulse' => 'Pulsing dot', 'dots' => 'Bouncing dots', 'spinner' => 'Spinner', 'lottie' => 'Lottie animation', 'none' => 'None' );
				foreach ( $anims as $av => $al ) :
					?>
					<option value="<?php echo esc_attr( $av ); ?>" <?php selected( $s['util_maintenance_anim'], $av ); ?>><?php echo esc_html( $al ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="velox-field" id="velox-maint-lottie-field" hidden>
			<span class="velox-field-label">Lottie animation file</span>
			<div class="velox-media-row">
				<input type="text" class="velox-input" data-setting="util_maintenance_lottie" value="<?php echo esc_attr( $s['util_maintenance_lottie'] ); ?>" placeholder="https://… .json or .lottie">
				<button type="button" class="velox-btn velox-btn--ghost velox-media-pick" data-target="util_maintenance_lottie" data-mediatype="any">Choose</button>
				<button type="button" class="velox-btn velox-btn--ghost velox-media-clear" data-target="util_maintenance_lottie">Clear</button>
			</div>
			<span class="velox-hint">Used when <strong>Loading animation</strong> is set to <strong>Lottie animation</strong>. Upload a <code>.json</code> or <code>.lottie</code> from your media library, or paste a link (e.g. from LottieFiles).</span>
		</div>

		<div class="velox-field">
			<span class="velox-field-label">Button <span style="color:var(--vx-ink-3);font-weight:500;">(optional)</span></span>
			<div class="velox-media-row">
				<input type="text" class="velox-input" data-setting="util_maintenance_btn_text" value="<?php echo esc_attr( $s['util_maintenance_btn_text'] ); ?>" placeholder="Button label (e.g. Contact us)">
				<input type="text" class="velox-input" data-setting="util_maintenance_btn_url" value="<?php echo esc_attr( $s['util_maintenance_btn_url'] ); ?>" placeholder="https://…">
			</div>
			<span class="velox-hint">Shown only when both a label and a link are set.</span>
		</div>

		<div class="velox-tool-actions" style="display:flex;gap:10px;flex-wrap:wrap;">
			<button class="velox-btn velox-btn--primary velox-util-save">Save</button>
			<button type="button" class="velox-btn velox-btn--ghost" id="velox-maint-reset">Reset to default</button>
		</div>
	</div>

	<div class="velox-panel velox-maint-preview-wrap">
		<span class="velox-field-label">Live preview</span>
		<div class="velox-maint-preview" id="velox-maint-preview" data-default-logo="<?php echo esc_url( $logo_default ); ?>">
			<img class="vmp-logo" id="vmp-logo" src="<?php echo esc_url( $s['util_maintenance_logo'] ? $s['util_maintenance_logo'] : $logo_default ); ?>" alt="">
			<h3 class="vmp-title" id="vmp-title"></h3>
			<p class="vmp-msg" id="vmp-msg"></p>
			<a class="vmp-btn" id="vmp-btn" style="display:none"></a>
			<div class="vmp-anim" id="vmp-anim"></div>
			<div class="vmp-brand" id="vmp-brand" style="display:none"></div>
		</div>
		<span class="velox-hint">Updates as you type. Admins still see the live site.</span>
	</div>
</div>
