<?php
/**
 * Velox — Google Reviews utility page.
 * Manage API connections + design presets, and grab the shortcode to embed.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$store       = Velox_Reviews::store();
$connections = $store['connections'];
$presets     = $store['presets'];
$def_style   = Velox_Reviews::default_style();
?>
<div class="velox-reviews-admin" id="velox-reviews-admin">
<script type="text/javascript">
	window.VELOX_REVIEWS_PRESETS = <?php echo wp_json_encode( $presets ); ?>;
</script>

	<div class="velox-panel">
		<div class="velox-panel-head">
			<h3 class="velox-panel-title"><?php esc_html_e( 'Connections', 'velox' ); ?></h3>
		</div>
		<p class="velox-hint"><?php esc_html_e( 'Connect a Google reviews source. Featurable is free and caches many reviews; the Google Places API is official but returns at most 5. Every connection needs a name.', 'velox' ); ?></p>

		<div class="velox-conn-list" id="velox-conn-list">
			<?php if ( empty( $connections ) ) : ?>
				<p class="velox-empty" id="velox-conn-empty"><?php esc_html_e( 'No connections yet — add one below.', 'velox' ); ?></p>
			<?php else : ?>
				<?php foreach ( $connections as $c ) : ?>
					<div class="velox-conn-row" data-id="<?php echo esc_attr( $c['id'] ); ?>">
						<span class="velox-conn-name"><?php echo esc_html( $c['name'] ); ?></span>
						<span class="velox-conn-badge"><?php echo esc_html( 'google' === $c['provider'] ? 'Google API' : 'Featurable' ); ?></span>
						<code class="velox-conn-sc">[velox_reviews connection="<?php echo esc_attr( $c['id'] ); ?>" preset="…"]</code>
						<button type="button" class="velox-btn velox-btn--ghost velox-conn-test" data-id="<?php echo esc_attr( $c['id'] ); ?>"><?php esc_html_e( 'Test', 'velox' ); ?></button>
						<button type="button" class="velox-btn velox-btn--ghost velox-conn-del" data-id="<?php echo esc_attr( $c['id'] ); ?>"><?php esc_html_e( 'Delete', 'velox' ); ?></button>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<div class="velox-conn-form">
			<h4 class="velox-sub-h"><?php esc_html_e( 'Add a connection', 'velox' ); ?></h4>
			<div class="velox-field">
				<span class="velox-field-label"><?php esc_html_e( 'Name (required)', 'velox' ); ?></span>
				<input type="text" class="velox-input" id="vxr-conn-name" placeholder="<?php esc_attr_e( 'e.g. MPU Profi — Google', 'velox' ); ?>">
			</div>
			<div class="velox-field">
				<span class="velox-field-label"><?php esc_html_e( 'Provider', 'velox' ); ?></span>
				<select class="velox-input" id="vxr-conn-provider">
					<option value="featurable"><?php esc_html_e( 'Featurable (free, more reviews)', 'velox' ); ?></option>
					<option value="google"><?php esc_html_e( 'Google Places API (max 5)', 'velox' ); ?></option>
				</select>
			</div>
			<div class="velox-field vxr-p-featurable">
				<span class="velox-field-label"><?php esc_html_e( 'Featurable widget ID', 'velox' ); ?></span>
				<input type="text" class="velox-input velox-mono" id="vxr-conn-widget" placeholder="<?php esc_attr_e( 'from featurable.com', 'velox' ); ?>">
			</div>
			<div class="velox-field vxr-p-google" hidden>
				<span class="velox-field-label"><?php esc_html_e( 'Google API key', 'velox' ); ?></span>
				<input type="text" class="velox-input velox-mono" id="vxr-conn-key">
			</div>
			<div class="velox-field vxr-p-google" hidden>
				<span class="velox-field-label"><?php esc_html_e( 'Google Place ID', 'velox' ); ?></span>
				<input type="text" class="velox-input velox-mono" id="vxr-conn-place">
			</div>
			<div class="velox-actions">
				<button type="button" class="velox-btn velox-btn--primary" id="vxr-conn-save"><?php esc_html_e( 'Save connection', 'velox' ); ?></button>
			</div>
		</div>
	</div>

	<div class="velox-panel">
		<div class="velox-panel-head">
			<h3 class="velox-panel-title"><?php esc_html_e( 'Design presets', 'velox' ); ?></h3>
		</div>
		<p class="velox-hint"><?php esc_html_e( 'Build reusable designs — a slider or a static grid — then reference a preset in the shortcode. Everything is adjustable: layout, colours, sizes, spacing, and whether to show avatars, dates and stars.', 'velox' ); ?></p>

		<div class="velox-preset-list" id="velox-preset-list">
			<?php foreach ( $presets as $p ) : ?>
				<div class="velox-preset-row" data-id="<?php echo esc_attr( $p['id'] ); ?>">
					<span class="velox-conn-name"><?php echo esc_html( $p['name'] ); ?></span>
					<span class="velox-conn-badge"><?php echo esc_html( 'static' === $p['type'] ? __( 'Static grid', 'velox' ) : __( 'Slider', 'velox' ) ); ?></span>
					<code class="velox-conn-sc">preset="<?php echo esc_attr( $p['id'] ); ?>"</code>
					<button type="button" class="velox-btn velox-btn--ghost velox-preset-edit" data-id="<?php echo esc_attr( $p['id'] ); ?>"><?php esc_html_e( 'Edit', 'velox' ); ?></button>
					<button type="button" class="velox-btn velox-btn--ghost velox-preset-del" data-id="<?php echo esc_attr( $p['id'] ); ?>"><?php esc_html_e( 'Delete', 'velox' ); ?></button>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="velox-preset-builder" id="velox-preset-builder">
			<h4 class="velox-sub-h"><?php esc_html_e( 'Preset builder', 'velox' ); ?></h4>
			<input type="hidden" id="vxr-preset-id" value="">
			<div class="velox-grid-2">
				<div class="velox-field">
					<span class="velox-field-label"><?php esc_html_e( 'Preset name (required)', 'velox' ); ?></span>
					<input type="text" class="velox-input" id="vxr-preset-name">
				</div>
				<div class="velox-field">
					<span class="velox-field-label"><?php esc_html_e( 'Layout', 'velox' ); ?></span>
					<select class="velox-input" id="vxr-preset-type">
						<option value="slider"><?php esc_html_e( 'Slider', 'velox' ); ?></option>
						<option value="static"><?php esc_html_e( 'Static grid', 'velox' ); ?></option>
					</select>
				</div>
			</div>

			<div class="velox-style-grid" id="vxr-style-grid">
				<?php
				// Field definitions: key => [label, type]
				$fields = array(
					'count'          => array( __( 'Number of reviews', 'velox' ), 'num' ),
					'min_rating'     => array( __( 'Minimum star rating', 'velox' ), 'num' ),
					'columns'        => array( __( 'Grid columns (static)', 'velox' ), 'num' ),
					'slides_desktop' => array( __( 'Slides on desktop', 'velox' ), 'num' ),
					'slides_tablet'  => array( __( 'Slides on tablet', 'velox' ), 'num' ),
					'slides_mobile'  => array( __( 'Slides on mobile', 'velox' ), 'num' ),
					'autoplay_speed' => array( __( 'Autoplay speed (ms)', 'velox' ), 'num' ),
					'card_radius'    => array( __( 'Card corner radius (px)', 'velox' ), 'num' ),
					'card_padding'   => array( __( 'Card padding (px)', 'velox' ), 'num' ),
					'card_gap'       => array( __( 'Gap between cards (px)', 'velox' ), 'num' ),
					'name_size'      => array( __( 'Name font size (px)', 'velox' ), 'num' ),
					'text_size'      => array( __( 'Review font size (px)', 'velox' ), 'num' ),
					'avatar_size'    => array( __( 'Avatar size (px)', 'velox' ), 'num' ),
					'card_bg'        => array( __( 'Card background', 'velox' ), 'color' ),
					'text_color'     => array( __( 'Text colour', 'velox' ), 'color' ),
					'meta_color'     => array( __( 'Date/meta colour', 'velox' ), 'color' ),
					'star_color'     => array( __( 'Star colour', 'velox' ), 'color' ),
					'show_avatar'    => array( __( 'Show avatar', 'velox' ), 'bool' ),
					'show_date'      => array( __( 'Show date', 'velox' ), 'bool' ),
					'show_rating'    => array( __( 'Show stars', 'velox' ), 'bool' ),
					'card_shadow'    => array( __( 'Card shadow', 'velox' ), 'bool' ),
					'autoplay'       => array( __( 'Autoplay (slider)', 'velox' ), 'bool' ),
				);
				foreach ( $fields as $key => $f ) :
					$dv = $def_style[ $key ];
					?>
					<div class="velox-style-field" data-type="<?php echo esc_attr( $f[1] ); ?>">
						<label class="velox-style-label"><?php echo esc_html( $f[0] ); ?></label>
						<?php if ( 'bool' === $f[1] ) : ?>
							<label class="velox-switch velox-switch--sm"><input type="checkbox" data-style="<?php echo esc_attr( $key ); ?>" <?php checked( (bool) $dv ); ?>><span class="velox-switch-track"></span></label>
						<?php elseif ( 'color' === $f[1] ) : ?>
							<input type="color" class="velox-color" data-style="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $dv ); ?>">
						<?php else : ?>
							<input type="number" class="velox-input velox-input--sm" data-style="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $dv ); ?>">
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="velox-actions">
				<button type="button" class="velox-btn velox-btn--primary" id="vxr-preset-save"><?php esc_html_e( 'Save preset', 'velox' ); ?></button>
				<button type="button" class="velox-btn velox-btn--ghost" id="vxr-preset-reset"><?php esc_html_e( 'Reset fields', 'velox' ); ?></button>
			</div>
		</div>
	</div>
</div>

<?php
/**
 * Example reviews.
 *
 * You cannot style a review block against an empty box, and connecting Google
 * just to see what it looks like is backwards. These are fake, clearly labelled,
 * and never leave this screen.
 */
?>
<div class="velox-panel" id="velox-rv-demo-panel">
	<div class="velox-panel-head">
		<h3 class="velox-panel-title"><?php esc_html_e( 'Preview with example reviews', 'velox' ); ?></h3>
		<label class="velox-switch"><input type="checkbox" id="velox-rv-demo"><span class="velox-switch-track"></span></label>
	</div>
	<p class="velox-hint"><?php esc_html_e( 'Fake reviews so you can see how a block looks before connecting Google. Nothing here is published.', 'velox' ); ?></p>
	<div class="velox-rv-demo" id="velox-rv-demo-out" hidden></div>
</div>
<script>
( function () {
	var sw  = document.getElementById( 'velox-rv-demo' );
	var out = document.getElementById( 'velox-rv-demo-out' );
	if ( ! sw || ! out ) { return; }
	var PEOPLE = [
		[ 'Anna Weber', 5, 'Sehr freundliches Team und schnelle Umsetzung. Jederzeit wieder!', '2 Wochen' ],
		[ 'Michael Braun', 5, 'Top Beratung, faire Preise und alles pünktlich fertig geworden.', '1 Monat' ],
		[ 'Sarah Klein', 4, 'Gute Arbeit, kleine Verzögerung — aber das Ergebnis stimmt.', '1 Monat' ],
		[ 'Thomas Fischer', 5, 'Kompetent, zuverlässig und sehr sauber gearbeitet.', '3 Monate' ],
		[ 'Julia Hoffmann', 5, 'Von der ersten Anfrage bis zur Übergabe alles reibungslos.', '4 Monate' ],
		[ 'Daniel Schulz', 4, 'Sehr zufrieden mit dem Ergebnis und der Kommunikation.', '6 Monate' ]
	];
	function esc( t ) { return String( t ).replace( /[&<>"]/g, function ( c ) {
		return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;' }[ c ]; } ); }
	function render() {
		out.innerHTML = PEOPLE.map( function ( p ) {
			return '<div class="velox-rv-card">' +
				'<div class="velox-rv-head"><span class="velox-rv-av">' + esc( p[0].charAt( 0 ) ) + '</span>' +
				'<span class="velox-rv-name">' + esc( p[0] ) + '</span><span class="velox-rv-g">G</span></div>' +
				'<div class="velox-rv-stars">' + '★'.repeat( p[1] ) + '☆'.repeat( 5 - p[1] ) + '</div>' +
				'<p class="velox-rv-text">' + esc( p[2] ) + '</p>' +
				'<span class="velox-rv-date">vor ' + esc( p[3] ) + '</span></div>';
		} ).join( '' );
	}
	sw.addEventListener( 'change', function () {
		out.hidden = ! sw.checked;
		if ( sw.checked && ! out.innerHTML ) { render(); }
	} );
}() );
</script>
