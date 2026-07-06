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
	'utilities'   => array(), // children are the switched-on utilities, rendered dynamically below
	'settings'    => array(),
);

$vx_cur_tool = isset( $_GET['tool'] ) ? sanitize_key( wp_unslash( $_GET['tool'] ) ) : '';

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

if ( ! function_exists( 'velox_side_util_item' ) ) {
	/** Render a switched-on utility as a Utilities sub-item, linking to its page. */
	function velox_side_util_item( $admin, $id, $current, $cur_tool ) {
		$cat = Velox_Utilities::catalog();
		if ( ! isset( $cat[ $id ] ) ) {
			return;
		}
		$t = $cat[ $id ];
		if ( ! empty( $t['link'] ) ) { // e.g. Media Editor → its own top-level view
			$url    = $admin->tab_url( $t['link'] );
			$active = ( $current === $t['link'] );
		} else {
			$url    = admin_url( 'admin.php?page=velox-utilities&tool=' . $id );
			$active = ( 'utilities' === $current && $cur_tool === $id );
		}
		printf(
			'<a href="%s" class="velox-side-item velox-side-item--sub%s"><span class="velox-side-ic">%s</span><span class="velox-side-label">%s</span></a>',
			esc_url( $url ),
			$active ? ' is-active' : '',
			Velox_Admin::icon( $t['icon'], 18 ),
			esc_html( $t['label'] )
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
			<button type="button" class="velox-side-collapse" id="velox-side-collapse" aria-label="Collapse menu" title="Collapse menu">
				<svg class="velox-ic" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
			</button>
		</div>

		<nav class="velox-side-nav">
			<?php
			// Full Velox catalogue — every area and utility, grouped. ('tab' = primary
			// area via $admin->tab_url(); 'util' = utility via Velox_Utilities::tool_url().)
			$vx_full_nav = array(
				'Overview'    => array(
					array( 'tab', 'dashboard', 'Dashboard', 'home' ),
					array( 'tab', 'utilities', 'Utilities', 'grid' ),
				),
				'Essentials'  => array(
					array( 'tab', 'performance', 'Performance', 'bolt' ),
					array( 'tab', 'images', 'Images', 'image' ),
					array( 'tab', 'seo', 'SEO', 'search' ),
				),
				'More'        => array(
					array( 'tab', 'database', 'Database', 'db' ),
					array( 'util', 'fields', 'Custom Fields', 'grid' ),
					array( 'tab', 'media', 'Media Editor', 'tag' ),
					array( 'util', 'redirects', 'Redirects & 404s', 'redirect' ),
					array( 'util', 'snippets', 'Code Snippets', 'code' ),
					array( 'util', 'cookies', 'Cookie Banner', 'cookie' ),
					array( 'util', 'mail', 'Mail & Forms', 'mail' ),
					array( 'util', 'scripts', 'Script Manager', 'code' ),
					array( 'util', 'unusedmedia', 'Unused Media', 'broom' ),
					array( 'util', 'maintenance', 'Maintenance Mode', 'cone' ),
					array( 'util', 'loginurl', 'Login URL', 'lock' ),
					array( 'util', 'installer', 'Bulk Installer', 'plug' ),
					array( 'util', 'backup', 'Backup & Restore', 'package' ),
					array( 'util', 'october', 'OctoberCMS Theme', 'package' ),
				),
			);
			$vx_cat = Velox_Utilities::catalog();
			// Drop PageSpeed in right under Performance when the module is on.
			if ( class_exists( 'Velox_Pagespeed' ) && Velox_Pagespeed::enabled() ) {
				array_splice( $vx_full_nav['Essentials'], 1, 0, array( array( 'tab', 'pagespeed', 'PageSpeed', 'search' ) ) );
			}
			foreach ( $vx_full_nav as $vx_section => $vx_items ) {
				echo '<div class="velox-side-group">';
				echo '<div class="velox-side-grouplabel">' . esc_html( $vx_section ) . '</div>';
				foreach ( $vx_items as $vx_it ) {
					list( $vx_kind, $vx_id, $vx_lbl, $vx_icon ) = $vx_it;
					if ( 'tab' === $vx_kind ) {
						$vx_url = $admin->tab_url( $vx_id );
						$vx_act = ( $current === $vx_id );
					} else {
						$vx_url  = Velox_Utilities::tool_url( $vx_id );
						$vx_link = isset( $vx_cat[ $vx_id ]['link'] ) ? $vx_cat[ $vx_id ]['link'] : '';
						$vx_act  = $vx_link ? ( $current === $vx_link ) : ( 'utilities' === $current && $vx_cur_tool === $vx_id );
					}
					printf(
						'<a href="%s" class="velox-side-item%s"><span class="velox-side-ic">%s</span><span class="velox-side-label">%s</span></a>',
						esc_url( $vx_url ),
						$vx_act ? ' is-active' : '',
						Velox_Admin::icon( $vx_icon, 18 ),
						esc_html( $vx_lbl )
					);
				}
				echo '</div>';
			}
			?>
		</nav>

		<div class="velox-side-foot">
			<a class="velox-side-foot-link" href="https://www.sumasearch.de/" target="_blank" rel="noopener">
				<img src="<?php echo esc_url( VELOX_URL . 'assets/menu-icon.png' ); ?>" alt="" width="14" height="14">
				by Sumasearch
			</a>
			<a class="velox-side-foot-gear<?php echo ( 'settings' === $current ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( $admin->tab_url( 'settings' ) ); ?>" aria-label="Settings" title="Settings">
				<?php echo Velox_Admin::icon( 'gear', 18 ); ?>
			</a>
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

				<!-- progress dots -->
				<div class="velox-wiz-steps" aria-hidden="true">
					<span class="velox-wiz-dot is-on" data-dot="builder"></span>
					<span class="velox-wiz-dot" data-dot="path"></span>
					<span class="velox-wiz-dot" data-dot="review"></span>
					<span class="velox-wiz-dot" data-dot="done"></span>
				</div>

				<!-- STEP 1: pick builder -->
				<div class="velox-wizard-step" data-step="builder">
					<img class="velox-wizard-logo" src="<?php echo esc_url( VELOX_URL . 'assets/menu-icon.png' ); ?>" alt="Velox" width="40" height="40">
					<h2 class="velox-wizard-h">Which page builder do you use?</h2>
					<p class="velox-wizard-p">Pick yours below — every builder needs different speed settings. Not sure? <a href="#" id="velox-wiz-detect">Detect it for me →</a></p>
					<div class="velox-wiz-grid" id="velox-wiz-grid">
						<?php foreach ( $vx_builders as $bid => $blabel ) : ?>
							<button type="button" class="velox-wiz-builder<?php echo $bid === $vx_detected && 'wordpress' !== $bid ? ' is-detected' : ''; ?>" data-builder="<?php echo esc_attr( $bid ); ?>">
								<span class="velox-wiz-builder-name"><?php echo esc_html( $blabel ); ?></span>
								<?php if ( $bid === $vx_detected && 'wordpress' !== $bid ) : ?><span class="velox-wiz-detected-tag">Detected</span><?php endif; ?>
							</button>
						<?php endforeach; ?>
					</div>
					<p class="velox-hint" style="margin-top:14px;">Builder not listed? <a href="#" id="velox-wizard-req-open">Request it →</a></p>
					<div id="velox-wizard-req" hidden>
						<div style="display:flex;gap:8px;margin-top:6px;">
							<input type="text" class="velox-input" id="velox-wizard-req-name" placeholder="e.g. Breakdance, Cwicly, Zion…">
							<button class="velox-btn velox-btn--ghost" id="velox-wizard-req-send">Send</button>
						</div>
					</div>
					<div class="velox-wizard-actions">
						<button class="velox-btn velox-btn--ghost" id="velox-wizard-skip">Skip for now</button>
						<button class="velox-btn velox-btn--primary" id="velox-wiz-to-path" disabled>Next</button>
					</div>
				</div>

				<!-- STEP 2: choose path -->
				<div class="velox-wizard-step" data-step="path" hidden>
					<h2 class="velox-wizard-h">How do you want to set up <span id="velox-wiz-blabel">Velox</span>?</h2>
					<p class="velox-wizard-p">Pick the recommended path and Velox scans your plugins and tunes everything for you — or configure it yourself.</p>
					<div class="velox-wiz-paths">
						<button type="button" class="velox-wiz-path is-selected" data-path="auto">
							<span class="velox-wiz-path-badge">Recommended</span>
							<span class="velox-wiz-path-t">Detect &amp; recommend</span>
							<span class="velox-wiz-path-d">Velox scans your builder and installed plugins, then shows tuned settings you can review and tweak before applying.</span>
						</button>
						<button type="button" class="velox-wiz-path" data-path="manual">
							<span class="velox-wiz-path-t">I'll configure it myself</span>
							<span class="velox-wiz-path-d">Skip the automatic tuning and head straight to Settings → Performance to set everything by hand.</span>
						</button>
					</div>
					<div class="velox-wizard-actions">
						<button class="velox-btn velox-btn--ghost" id="velox-wiz-back-builder">Back</button>
						<button class="velox-btn velox-btn--primary" id="velox-wiz-path-next">Next</button>
					</div>
				</div>

				<!-- STEP 3: review -->
				<div class="velox-wizard-step" data-step="review" hidden>
					<h2 class="velox-wizard-h">Recommended for <span id="velox-wiz-rlabel">your builder</span></h2>
					<p class="velox-wizard-p" id="velox-wiz-rnote"></p>
					<div id="velox-wiz-advisories"></div>
					<div id="velox-wiz-plugins" class="velox-wiz-plugins"></div>
					<div class="velox-wiz-review" id="velox-wiz-review"><p class="velox-hint" style="padding:14px;">Scanning…</p></div>
					<div class="velox-wizard-actions">
						<button class="velox-btn velox-btn--ghost" id="velox-wiz-back-path">Back</button>
						<button class="velox-btn velox-btn--primary" id="velox-wizard-apply">Apply selected</button>
					</div>
				</div>

				<!-- STEP 4: done -->
				<div class="velox-wizard-step" data-step="done" hidden>
					<div class="velox-wiz-done-mark">⚡</div>
					<h2 class="velox-wizard-h">You're all set</h2>
					<p class="velox-wizard-p" id="velox-wizard-donemsg"></p>
					<div class="velox-wizard-actions">
						<a class="velox-btn velox-btn--ghost" id="velox-wizard-toperf" href="<?php echo esc_url( $admin->tab_url( 'performance' ) ); ?>">Open Performance</a>
						<button class="velox-btn velox-btn--primary" id="velox-wizard-finish">Done</button>
					</div>
				</div>
			</div>
		</div>

		<main class="velox-main">
