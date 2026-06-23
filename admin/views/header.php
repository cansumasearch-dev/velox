<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var Velox_Admin $admin */
$current  = $admin->current_tab();
$enabled  = $admin->enabled_tabs();

// Option B information architecture: 5 primary areas, with Database nested under
// Performance and Media Editor nested under Utilities. Children only render when
// their module is enabled.
$vx_groups = array(
	'dashboard'   => array(),
	'performance' => array( 'database' ),
	'images'      => array(),
	'seo'         => array(),
	'utilities'   => array( 'media' ),
	'settings'    => array(),
);

$vx_builders  = Velox_Builders::choices();
$vx_registry  = Velox_Builders::registry();
$vx_current   = Velox_Builders::current();
$vx_detected  = Velox_Builders::detect();
$vx_wizard    = (bool) Velox_Settings::get( 'wizard_done' );
$vx_forceopen = isset( $_GET['velox_wizard'] );
$vx_autoopen  = $vx_forceopen || ( ! $vx_wizard && '' === $vx_current );

if ( ! function_exists( 'velox_side_item' ) ) {
	function velox_side_item( $admin, $tab, $key, $current, $sub = false ) {
		$active = ( $current === $key ) ? ' is-active' : '';
		$cls    = 'velox-side-item' . ( $sub ? ' velox-side-item--sub' : '' ) . $active;
		printf(
			'<a href="%s" class="%s"><span class="velox-side-ic">%s</span><span class="velox-side-label">%s</span></a>',
			esc_url( $admin->tab_url( $key ) ),
			esc_attr( $cls ),
			Velox_Admin::icon( $tab['icon'], 18 ),
			esc_html( $tab['label'] )
		);
	}
}
?>
<div class="velox-wrap" data-tab="<?php echo esc_attr( $current ); ?>">
<div class="velox-app">

	<aside class="velox-sidebar">
		<div class="velox-side-brand">
			<img class="velox-side-logo" src="<?php echo esc_url( VELOX_URL . 'assets/menu-icon.png' ); ?>" alt="" width="24" height="24">
			<span class="velox-side-name">Velox</span>
			<span class="velox-ver">v<?php echo esc_html( VELOX_VERSION ); ?></span>
		</div>

		<nav class="velox-side-nav">
			<?php
			foreach ( $vx_groups as $key => $children ) {
				if ( ! isset( $enabled[ $key ] ) ) {
					continue;
				}
				velox_side_item( $admin, $enabled[ $key ], $key, $current );
				foreach ( $children as $child ) {
					if ( isset( $enabled[ $child ] ) ) {
						velox_side_item( $admin, $enabled[ $child ], $child, $current, true );
					}
				}
			}
			?>
		</nav>

		<div class="velox-side-foot">
			<a class="velox-side-foot-link" href="https://www.sumasearch.de/" target="_blank" rel="noopener">by Sumasearch</a>
		</div>
	</aside>

	<div class="velox-content">
		<div class="velox-toast" id="velox-toast"></div>

		<!-- Setup wizard -->
		<div class="velox-wizard-overlay" id="velox-wizard"
			data-autoopen="<?php echo $vx_autoopen ? '1' : '0'; ?>"
			data-current="<?php echo esc_attr( $vx_current ); ?>"
			data-detected="<?php echo esc_attr( $vx_detected ); ?>" hidden>
			<div class="velox-wizard" role="dialog" aria-modal="true" aria-label="Velox setup">
				<button class="velox-wizard-x" id="velox-wizard-close" aria-label="Close">&times;</button>
				<img class="velox-wizard-logo" src="<?php echo esc_url( VELOX_URL . 'assets/menu-icon.png' ); ?>" alt="Velox" width="44" height="44">

				<div class="velox-wizard-step" data-step="intro">
					<h2 class="velox-wizard-h">Let's tune Velox to your stack</h2>
					<p class="velox-wizard-p">Every page builder needs different speed settings. Run a quick check and Velox will configure the safe, fast defaults for your exact setup — you can fine-tune everything afterwards.</p>
					<div class="velox-wizard-actions">
						<button class="velox-btn velox-btn--ghost" id="velox-wizard-skip">Skip for now</button>
						<button class="velox-btn velox-btn--primary" id="velox-wizard-check">Run builder check</button>
					</div>
				</div>

				<div class="velox-wizard-step" data-step="detected" hidden>
					<h2 class="velox-wizard-h" id="velox-wizard-dtitle">Detected your builder</h2>
					<p class="velox-wizard-p" id="velox-wizard-dnote"></p>
					<label class="velox-wizard-label" for="velox-wizard-select">This is what you're using:</label>
					<select class="velox-input" id="velox-wizard-select">
						<?php foreach ( $vx_builders as $bid => $blabel ) : ?>
							<option value="<?php echo esc_attr( $bid ); ?>" data-note="<?php echo esc_attr( $vx_registry[ $bid ]['note'] ); ?>"><?php echo esc_html( $blabel ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="velox-hint">Builder not listed? <a href="#" id="velox-wizard-req-open">Request it →</a></p>
					<div id="velox-wizard-req" hidden>
						<input type="text" class="velox-input" id="velox-wizard-req-name" placeholder="e.g. Breakdance, Cwicly, Zion…">
						<button class="velox-btn velox-btn--ghost" id="velox-wizard-req-send">Send request</button>
					</div>
					<div class="velox-wizard-actions">
						<button class="velox-btn velox-btn--ghost" id="velox-wizard-back">Back</button>
						<button class="velox-btn velox-btn--primary" id="velox-wizard-apply">Configure for this builder</button>
					</div>
				</div>

				<div class="velox-wizard-step" data-step="done" hidden>
					<h2 class="velox-wizard-h">You're all set ⚡</h2>
					<p class="velox-wizard-p" id="velox-wizard-donemsg"></p>
					<div class="velox-wizard-actions">
						<a class="velox-btn velox-btn--ghost" id="velox-wizard-toperf" href="<?php echo esc_url( $admin->tab_url( 'performance' ) ); ?>">Open Performance</a>
						<button class="velox-btn velox-btn--primary" id="velox-wizard-finish">Done</button>
					</div>
				</div>
			</div>
		</div>

		<main class="velox-main">
