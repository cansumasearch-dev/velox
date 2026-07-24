<?php
/**
 * HTML lang switcher.
 *
 * Design pass: a plain text field would ask you to take it on faith that the
 * change landed — but the whole reason for this tool is that the normal
 * WordPress setting *didn't* land. So the page reads the live front page in the
 * browser and shows the attribute that is actually being served, then a single
 * dropdown and a before/after line. Nothing else: no per-page overrides, no
 * locale search, no second "force" switch.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s       = Velox_Settings::all();
$on      = ! empty( $s['util_htmllang'] );
$value   = Velox_Utilities::sanitize_lang( isset( $s['util_htmllang_value'] ) ? $s['util_htmllang_value'] : '' );
$wp_lang = Velox_Utilities::sanitize_lang( get_bloginfo( 'language' ) );
$choices = Velox_Utilities::lang_choices();
$known   = ( '' !== $value ) && ( isset( $choices['installed'][ $value ] ) || isset( $choices['common'][ $value ] ) );
?>
<div class="velox-page-head">
	<h1 class="velox-h2">HTML lang switcher</h1>
	<p class="velox-sub">Sets the <code>lang</code> attribute on the <code>&lt;html&gt;</code> tag. Handy when a theme or builder hard-codes it and the WordPress site language never actually reaches the page — screen readers and search engines both read this attribute to decide what language the page is in.</p>
</div>

<div class="velox-panel velox-tool-form" id="vxlang"
	data-home="<?php echo esc_url( home_url( '/' ) ); ?>"
	data-site="<?php echo esc_attr( $wp_lang ); ?>">

	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label">Override the lang attribute</span>
			<span class="velox-toggle-desc">While this is off, the page keeps whatever WordPress and your theme produce.</span>
		</div>
		<label class="velox-switch">
			<input type="checkbox" data-setting="util_htmllang" <?php checked( $on ); ?>>
			<span class="velox-switch-track"></span>
		</label>
	</div>

	<div class="velox-field">
		<span class="velox-field-label">In your page source right now</span>
		<div class="vxlang-live" id="vxlang-live" data-state="loading">
			<span class="vxlang-live-dot" aria-hidden="true"></span>
			<code class="vxlang-live-val" id="vxlang-live-val">Reading your front page&hellip;</code>
			<button type="button" class="velox-btn velox-btn--ghost velox-btn--sm" id="vxlang-recheck">Re-check</button>
		</div>
		<span class="velox-hint">Read from your live front page in this browser, so it shows what visitors and crawlers really get rather than what the setting claims. WordPress itself is set to <code><?php echo esc_html( $wp_lang ? $wp_lang : 'unknown' ); ?></code>.</span>
	</div>

	<div class="velox-field">
		<span class="velox-field-label">Switch to</span>
		<select class="velox-select" id="vxlang-pick">
			<option value=""<?php selected( '' === $value ); ?>>Leave unchanged</option>
			<?php if ( ! empty( $choices['installed'] ) ) : ?>
				<optgroup label="Installed on this site">
					<?php foreach ( $choices['installed'] as $tag => $label ) : ?>
						<option value="<?php echo esc_attr( $tag ); ?>"<?php selected( $tag === $value ); ?>><?php echo esc_html( $label . ' — ' . $tag ); ?></option>
					<?php endforeach; ?>
				</optgroup>
			<?php endif; ?>
			<optgroup label="Common">
				<?php foreach ( $choices['common'] as $tag => $label ) : ?>
					<option value="<?php echo esc_attr( $tag ); ?>"<?php selected( $tag === $value ); ?>><?php echo esc_html( $label . ' — ' . $tag ); ?></option>
				<?php endforeach; ?>
			</optgroup>
			<option value="__custom"<?php selected( '' !== $value && ! $known ); ?>>Something else&hellip;</option>
		</select>
		<input type="text" class="velox-input vxlang-custom" id="vxlang-custom"
			data-setting="util_htmllang_value"
			value="<?php echo esc_attr( $value ); ?>"
			placeholder="e.g. de-DE"
			<?php echo ( '' !== $value && ! $known ) ? '' : 'hidden'; ?>>
		<span class="velox-hint">Use the short region form, like <code>de-DE</code> or <code>en-GB</code>. Underscores are converted to hyphens for you.</span>
	</div>

	<div class="vxlang-diff" id="vxlang-diff" data-empty="1">
		<div class="vxlang-diff-row">
			<span class="vxlang-diff-tag">Now</span>
			<code class="vxlang-diff-code" id="vxlang-diff-old">&lt;html lang="&hellip;"&gt;</code>
		</div>
		<div class="vxlang-diff-sep" aria-hidden="true"></div>
		<div class="vxlang-diff-row is-new">
			<span class="vxlang-diff-tag">After saving</span>
			<code class="vxlang-diff-code" id="vxlang-diff-new">&lt;html lang="&hellip;"&gt;</code>
		</div>
	</div>

	<p class="velox-hint vxlang-off-note" id="vxlang-off-note"<?php echo $on ? ' hidden' : ''; ?>>The override is switched off, so saving stores the choice without changing the page yet.</p>

	<div class="velox-tool-actions">
		<button class="velox-btn velox-btn--primary velox-util-save">Save</button>
	</div>
</div>
