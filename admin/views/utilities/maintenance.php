<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s            = Velox_Settings::all();
$logo_default = VELOX_URL . 'assets/logo.png';
?>
<div class="velox-page-head">
	<h1 class="velox-h2"><?php esc_html_e('Maintenance mode', 'velox'); ?></h1>
	<p class="velox-sub"><?php esc_html_e('Shows visitors a branded holding page while you work. You and any other admins keep seeing the live site, and wp-admin stays reachable. Sends a 503 so search engines know it\'s temporary.', 'velox'); ?></p>
</div>

<div class="velox-maint-layout">
	<div class="velox-panel velox-tool-form" data-tool="maintenance">
		<div class="velox-toggle-row">
			<div class="velox-toggle-meta">
				<span class="velox-toggle-label"><?php esc_html_e('Enable maintenance mode', 'velox'); ?></span>
				<span class="velox-toggle-desc"><?php esc_html_e('Front end shows the holding page to everyone except logged-in admins.', 'velox'); ?></span>
			</div>
			<label class="velox-switch"><input type="checkbox" data-setting="util_maintenance" <?php checked( ! empty( $s['util_maintenance'] ) ); ?>><span class="velox-switch-track"></span></label>
		</div>

		<div class="velox-alert velox-alert--info">
			<strong><?php esc_html_e('While maintenance is on, everything is hidden from search.', 'velox'); ?></strong>
			<?php esc_html_e('Every page and post is set to noindex, nofollow, and anything you create or duplicate meanwhile starts hidden too. Pages you had already set to noindex are left alone. When you switch maintenance back off, Velox asks what to do with them.', 'velox'); ?>
		</div>

		<?php
		$vx_marked = class_exists( 'Velox_Utilities' ) ? Velox_Utilities::maintenance_seo_count( 'marked' ) : 0;
		if ( $vx_marked ) :
			?>
			<div class="velox-alert velox-alert--info">
				<strong><?php echo (int) $vx_marked; ?> item<?php echo 1 === $vx_marked ? '' : 's'; ?> currently hidden by maintenance.</strong>
				<?php esc_html_e('Pages that were already set to noindex before you switched this on were left alone, so their own setting is untouched.', 'velox'); ?>
			</div>
		<?php endif; ?>

		<div class="velox-field">
			<span class="velox-field-label"><?php esc_html_e('Heading', 'velox'); ?></span>
			<input type="text" class="velox-input" data-setting="util_maintenance_title" value="<?php echo esc_attr( $s['util_maintenance_title'] ); ?>">
		</div>

		<div class="velox-field">
			<span class="velox-field-label"><?php esc_html_e('Message', 'velox'); ?></span>
			<textarea class="velox-textarea" data-setting="util_maintenance_message" rows="3"><?php echo esc_textarea( $s['util_maintenance_message'] ); ?></textarea>
			<span class="velox-hint"><?php esc_html_e('Shown under the heading. Line breaks are kept.', 'velox'); ?></span>
		</div>

		<div class="velox-field">
			<span class="velox-field-label"><?php esc_html_e('Footer text', 'velox'); ?> <span style="color:var(--vx-ink-3);font-weight:500;"><?php esc_html_e('(optional)', 'velox'); ?></span></span>
			<input type="text" class="velox-input" data-setting="util_maintenance_brand" value="<?php echo esc_attr( $s['util_maintenance_brand'] ); ?>" placeholder="e.g. your brand name — leave empty to hide">
			<span class="velox-hint"><?php esc_html_e('Small line at the very bottom. Empty = nothing shown (no site name forced in).', 'velox'); ?></span>
		</div>

		<div class="velox-field">
			<span class="velox-field-label"><?php esc_html_e('Logo', 'velox'); ?></span>
			<div class="velox-media-row">
				<input type="text" class="velox-input" data-setting="util_maintenance_logo" value="<?php echo esc_attr( $s['util_maintenance_logo'] ); ?>" placeholder="<?php echo esc_attr( $logo_default ); ?>">
				<button type="button" class="velox-btn velox-btn--ghost velox-media-pick" data-target="util_maintenance_logo"><?php esc_html_e('Choose', 'velox'); ?></button>
				<button type="button" class="velox-btn velox-btn--ghost velox-media-clear" data-target="util_maintenance_logo"><?php esc_html_e('Reset', 'velox'); ?></button>
			</div>
			<span class="velox-hint"><?php esc_html_e('Image, GIF, or Lottie (.json / .lottie) URL. Leave empty to use the default Velox mark.', 'velox'); ?></span>
		</div>

		<div class="velox-field">
			<span class="velox-field-label"><?php esc_html_e('Background image', 'velox'); ?> <span style="color:var(--vx-ink-3);font-weight:500;"><?php esc_html_e('(optional)', 'velox'); ?></span></span>
			<div class="velox-media-row">
				<input type="text" class="velox-input" data-setting="util_maintenance_bgimage" value="<?php echo esc_attr( $s['util_maintenance_bgimage'] ); ?>" placeholder="No background image">
				<button type="button" class="velox-btn velox-btn--ghost velox-media-pick" data-target="util_maintenance_bgimage"><?php esc_html_e('Choose', 'velox'); ?></button>
				<button type="button" class="velox-btn velox-btn--ghost velox-media-clear" data-target="util_maintenance_bgimage"><?php esc_html_e('Clear', 'velox'); ?></button>
			</div>
			<span class="velox-hint"><?php esc_html_e('Sits behind a tint of your background colour so text stays readable.', 'velox'); ?></span>
		</div>

		<div class="velox-field">
			<span class="velox-field-label"><?php esc_html_e('Colours', 'velox'); ?></span>
			<div class="velox-color-grid">
				<label class="velox-color-item"><span><?php esc_html_e('Background', 'velox'); ?></span><input type="color" data-setting="util_maintenance_bg" value="<?php echo esc_attr( $s['util_maintenance_bg'] ); ?>"></label>
				<label class="velox-color-item"><span><?php esc_html_e('Text', 'velox'); ?></span><input type="color" data-setting="util_maintenance_text" value="<?php echo esc_attr( $s['util_maintenance_text'] ); ?>"></label>
				<label class="velox-color-item"><span><?php esc_html_e('Accent', 'velox'); ?></span><input type="color" data-setting="util_maintenance_accent" value="<?php echo esc_attr( $s['util_maintenance_accent'] ); ?>"></label>
			</div>
		</div>

		<div class="velox-field">
			<span class="velox-field-label"><?php esc_html_e('Loading animation', 'velox'); ?></span>
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
			<span class="velox-field-label"><?php esc_html_e('Lottie animation file', 'velox'); ?></span>
			<div class="velox-media-row">
				<input type="text" class="velox-input" data-setting="util_maintenance_lottie" value="<?php echo esc_attr( $s['util_maintenance_lottie'] ); ?>" placeholder="https://… .json or .lottie">
				<button type="button" class="velox-btn velox-btn--ghost velox-media-pick" data-target="util_maintenance_lottie" data-mediatype="any"><?php esc_html_e('Choose', 'velox'); ?></button>
				<button type="button" class="velox-btn velox-btn--ghost velox-media-clear" data-target="util_maintenance_lottie"><?php esc_html_e('Clear', 'velox'); ?></button>
			</div>
			<span class="velox-hint"><?php printf( esc_html__( 'Used when %1$s is set to %2$s. Upload a %3$s or %4$s from your media library, or paste a link (e.g. from LottieFiles).', 'velox' ), '<strong>' . esc_html__( 'Loading animation', 'velox' ) . '</strong>', '<strong>' . esc_html__( 'Lottie animation', 'velox' ) . '</strong>', '<code>' . esc_html__( '.json', 'velox' ) . '</code>', '<code>' . esc_html__( '.lottie', 'velox' ) . '</code>' ); ?></span>
		</div>

		<div class="velox-field">
			<span class="velox-field-label"><?php esc_html_e('Button', 'velox'); ?> <span style="color:var(--vx-ink-3);font-weight:500;"><?php esc_html_e('(optional)', 'velox'); ?></span></span>
			<div class="velox-media-row">
				<input type="text" class="velox-input" data-setting="util_maintenance_btn_text" value="<?php echo esc_attr( $s['util_maintenance_btn_text'] ); ?>" placeholder="Button label (e.g. Contact us)">
				<input type="text" class="velox-input" data-setting="util_maintenance_btn_url" value="<?php echo esc_attr( $s['util_maintenance_btn_url'] ); ?>" placeholder="https://…">
			</div>
			<span class="velox-hint"><?php esc_html_e('Shown only when both a label and a link are set.', 'velox'); ?></span>
		</div>

		<div class="velox-tool-actions" style="display:flex;gap:10px;flex-wrap:wrap;">
			<button class="velox-btn velox-btn--primary velox-util-save"><?php esc_html_e('Save', 'velox'); ?></button>
			<button type="button" class="velox-btn velox-btn--ghost" id="velox-maint-reset"><?php esc_html_e('Reset to default', 'velox'); ?></button>
		</div>
	</div>

	<div class="velox-panel velox-maint-preview-wrap">
		<span class="velox-field-label"><?php esc_html_e('Live preview', 'velox'); ?></span>
		<div class="velox-maint-preview" id="velox-maint-preview" data-default-logo="<?php echo esc_url( $logo_default ); ?>">
			<img class="vmp-logo" id="vmp-logo" src="<?php echo esc_url( $s['util_maintenance_logo'] ? $s['util_maintenance_logo'] : $logo_default ); ?>" alt="">
			<h3 class="vmp-title" id="vmp-title"></h3>
			<p class="vmp-msg" id="vmp-msg"></p>
			<a class="vmp-btn" id="vmp-btn" style="display:none"></a>
			<div class="vmp-anim" id="vmp-anim"></div>
			<div class="vmp-brand" id="vmp-brand" style="display:none"></div>
		</div>
		<span class="velox-hint"><?php esc_html_e('Updates as you type. Admins still see the live site.', 'velox'); ?></span>
	</div>
</div>
