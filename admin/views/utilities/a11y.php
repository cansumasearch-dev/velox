<?php
/**
 * Accessibility menu for visitors.
 *
 * Design pass: the one question people have is "is it on for my visitors?", so
 * the page opens with a big switch that saves at once and a status line that
 * says it in words, next to a small preview of the button. Below: where it sits,
 * its colour, and which aids it offers. And an honest note: an overlay helps
 * people adjust what they see; it does not make a site accessible by itself.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s     = Velox_Settings::all();
$on    = ! empty( $s['util_a11y'] );
$side  = 'left' === ( $s['a11y_side'] ?? 'right' ) ? 'left' : 'right';
$color = sanitize_hex_color( (string) ( $s['a11y_color'] ?? '#1d4ed8' ) );
$color = $color ? $color : '#1d4ed8';
$feats = array(
	'a11y_f_profiles' => array( __( 'Profiles', 'velox' ), __( 'One click sets several aids at once: low vision, dyslexia friendly, seizure safe, focus.', 'velox' ) ),
	'a11y_f_text'     => array( __( 'Text size', 'velox' ), __( 'Three steps up to +50 %.', 'velox' ) ),
	'a11y_f_spacing'  => array( __( 'Spacing', 'velox' ), __( 'More line, letter and word spacing.', 'velox' ) ),
	'a11y_f_contrast' => array( __( 'Contrast', 'velox' ), __( 'Dark, light or greyscale.', 'velox' ) ),
	'a11y_f_font'     => array( __( 'Readable font', 'velox' ), __( 'A plain, wide font instead of the design font.', 'velox' ) ),
	'a11y_f_links'    => array( __( 'Highlight links', 'velox' ), __( 'Every link underlined and outlined.', 'velox' ) ),
	'a11y_f_motion'   => array( __( 'Stop animations', 'velox' ), __( 'Animations, transitions and videos stop.', 'velox' ) ),
	'a11y_f_images'   => array( __( 'Hide images', 'velox' ), __( 'Pictures and background images fade out; the layout stays.', 'velox' ) ),
	'a11y_f_cursor'   => array( __( 'Big cursor', 'velox' ), __( 'A large, high-contrast mouse pointer.', 'velox' ) ),
	'a11y_f_guide'    => array( __( 'Reading guide', 'velox' ), __( 'Dims the page except a band around the pointer.', 'velox' ) ),
	'a11y_f_read'     => array( __( 'Read aloud', 'velox' ), __( 'Clicked text is read out by the browser\'s own voice. Hidden in browsers without one.', 'velox' ) ),
);
?>
<div class="velox-page-head">
	<h1 class="velox-h2"><?php esc_html_e( 'Accessibility menu', 'velox' ); ?></h1>
	<p class="velox-sub"><?php esc_html_e( 'A button on every page of your site that opens display aids for your visitors. Each visitor\'s choice is remembered in their own browser — nothing is stored on your server.', 'velox' ); ?></p>
</div>

<div class="velox-panel vxa-hero<?php echo $on ? ' is-on' : ''; ?>" id="vxa-hero">
	<div class="vxa-hero-main">
		<label class="velox-switch vxa-big-switch">
			<input type="checkbox" id="vxa-master" <?php checked( $on ); ?> aria-describedby="vxa-status">
			<span class="velox-switch-track"></span>
		</label>
		<div class="vxa-hero-text">
			<span class="vxa-hero-label"><?php esc_html_e( 'Accessibility menu for visitors', 'velox' ); ?></span>
			<span class="vxa-status" id="vxa-status" role="status"
				data-on="<?php esc_attr_e( 'On — visitors see the button on every page.', 'velox' ); ?>"
				data-off="<?php esc_attr_e( 'Off — visitors do not see it. Switch it on whenever you like.', 'velox' ); ?>">
				<?php echo esc_html( $on ? __( 'On — visitors see the button on every page.', 'velox' ) : __( 'Off — visitors do not see it. Switch it on whenever you like.', 'velox' ) ); ?>
			</span>
		</div>
		<?php if ( $on ) : ?>
			<a class="velox-btn velox-btn--ghost velox-btn--sm" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See it on your site', 'velox' ); ?></a>
		<?php endif; ?>
	</div>
	<div class="vxa-preview" aria-hidden="true" data-side="<?php echo esc_attr( $side ); ?>">
		<span class="vxa-preview-bar"></span>
		<span class="vxa-preview-line"></span><span class="vxa-preview-line is-short"></span>
		<span class="vxa-preview-btn" style="background:<?php echo esc_attr( $color ); ?>">
			<svg viewBox="0 0 24 24" width="18" height="18" fill="#fff"><circle cx="12" cy="4.2" r="2.2"/><path d="M3.6 7.2c-.6-.2-.9.8-.3 1.1l5.2 1.7V14l-1.8 6.6c-.2.8 1 1.2 1.3.4L10.6 15h2.8l2.6 6.4c.3.8 1.5.4 1.3-.4L15.5 14v-4l5.2-1.7c.6-.3.3-1.3-.3-1.1L12 9.5z"/></svg>
		</span>
	</div>
</div>

<div class="velox-panel velox-tool-form" id="vxa-form">
	<h2 class="velox-section-title"><?php esc_html_e( 'Look and place', 'velox' ); ?></h2>
	<div class="vxa-row">
		<div class="velox-field">
			<span class="velox-field-label"><?php esc_html_e( 'Position', 'velox' ); ?></span>
			<select class="velox-select" data-setting="a11y_side" id="vxa-side">
				<option value="right"<?php selected( 'right', $side ); ?>><?php esc_html_e( 'Bottom right', 'velox' ); ?></option>
				<option value="left"<?php selected( 'left', $side ); ?>><?php esc_html_e( 'Bottom left', 'velox' ); ?></option>
			</select>
			<span class="velox-hint"><?php esc_html_e( 'Pick the corner your cookie or chat button does not use.', 'velox' ); ?></span>
		</div>
		<div class="velox-field">
			<span class="velox-field-label"><?php esc_html_e( 'Colour', 'velox' ); ?></span>
			<input type="color" class="vxa-color" data-setting="a11y_color" id="vxa-color" value="<?php echo esc_attr( $color ); ?>">
			<span class="velox-hint"><?php esc_html_e( 'Use a strong colour: the white icon needs good contrast on it.', 'velox' ); ?></span>
		</div>
	</div>
	<div class="velox-toggle-row">
		<div class="velox-toggle-meta">
			<span class="velox-toggle-label"><?php esc_html_e( 'Show on phones', 'velox' ); ?></span>
			<span class="velox-toggle-desc"><?php esc_html_e( 'Phones have their own zoom and accessibility settings; switch this off if the button gets in the way there.', 'velox' ); ?></span>
		</div>
		<label class="velox-switch"><input type="checkbox" data-setting="a11y_mobile" <?php checked( ! empty( $s['a11y_mobile'] ) ); ?>><span class="velox-switch-track"></span></label>
	</div>
	<div class="velox-field">
		<span class="velox-field-label"><?php esc_html_e( 'Accessibility statement', 'velox' ); ?> <small><?php esc_html_e( 'optional', 'velox' ); ?></small></span>
		<input type="url" class="velox-input" data-setting="a11y_statement" value="<?php echo esc_attr( (string) ( $s['a11y_statement'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( home_url( '/barrierefreiheit/' ) ); ?>">
		<span class="velox-hint"><?php esc_html_e( 'Shops that fall under the BFSG need one. Linked at the bottom of the menu.', 'velox' ); ?></span>
	</div>

	<h2 class="velox-section-title"><?php esc_html_e( 'What the menu offers', 'velox' ); ?></h2>
	<div class="vxa-feats">
		<?php foreach ( $feats as $key => $f ) : ?>
			<div class="velox-toggle-row">
				<div class="velox-toggle-meta">
					<span class="velox-toggle-label"><?php echo esc_html( $f[0] ); ?></span>
					<span class="velox-toggle-desc"><?php echo esc_html( $f[1] ); ?></span>
				</div>
				<label class="velox-switch"><input type="checkbox" data-setting="<?php echo esc_attr( $key ); ?>" <?php checked( ! empty( $s[ $key ] ) ); ?>><span class="velox-switch-track"></span></label>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="velox-note vxa-honest">
		<strong><?php esc_html_e( 'What this menu is — and is not', 'velox' ); ?></strong>
		<p><?php esc_html_e( 'It lets visitors adjust how your pages look. It does not make a site accessible or BFSG / WCAG compliant on its own: that comes from the pages themselves — real headings, alt texts, enough contrast, labelled form fields, everything usable by keyboard. Velox Builder\'s page check finds most of these.', 'velox' ); ?></p>
	</div>

	<div class="velox-actions">
		<button class="velox-btn velox-btn--primary velox-util-save"><?php esc_html_e( 'Save', 'velox' ); ?></button>
	</div>
</div>

<script>
( function () {
	var hero = document.getElementById( 'vxa-hero' ), master = document.getElementById( 'vxa-master' ), status = document.getElementById( 'vxa-status' );
	var prev = hero.querySelector( '.vxa-preview' ), pbtn = hero.querySelector( '.vxa-preview-btn' );
	// The big switch saves at once: on or off should never wait for a Save button.
	master.addEventListener( 'change', function () {
		var on = master.checked, fd = new FormData();
		fd.append( 'action', 'velox' );
		fd.append( 'do', 'save_settings' );
		fd.append( 'nonce', ( window.VELOX || {} ).nonce || '' );
		fd.append( 'util_a11y', on ? '1' : '0' );
		master.disabled = true;
		fetch( ( window.VELOX || {} ).ajaxurl || ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( j ) {
				if ( ! j || ! j.success ) { throw new Error(); }
				hero.classList.toggle( 'is-on', on );
				status.textContent = status.getAttribute( on ? 'data-on' : 'data-off' );
			} )
			.catch( function () { master.checked = ! on; } )
			.then( function () { master.disabled = false; } );
	} );
	document.getElementById( 'vxa-side' ).addEventListener( 'change', function () { prev.setAttribute( 'data-side', this.value ); } );
	document.getElementById( 'vxa-color' ).addEventListener( 'input', function () { pbtn.style.background = this.value; } );
}() );
</script>
