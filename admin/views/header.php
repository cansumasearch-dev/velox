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
			<span class="velox-side-name"><?php esc_html_e('Velox', 'velox'); ?></span>
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
				'Content & media' => array(
					array( 'tab', 'media', 'Media Editor', 'tag' ),
					array( 'util', 'unusedmedia', 'Unused Media', 'broom' ),
					array( 'util', 'fields', 'Custom Fields', 'grid' ),
					array( 'util', 'snippets', 'Code Snippets', 'code' ),
					array( 'util', 'scripts', 'Script Manager', 'code' ),
				),
				'Site & visitors' => array(
					array( 'util', 'redirects', 'Redirects & 404s', 'redirect' ),
					array( 'util', 'cookies', 'Cookie Banner', 'cookie' ),
					array( 'util', 'mail', 'Mail & Forms', 'mail' ),
					array( 'util', 'maintenance', 'Maintenance Mode', 'cone' ),
					array( 'util', 'loginurl', 'Login URL', 'lock' ),
					array( 'util', 'htmllang', 'HTML Lang', 'globe' ),
				),
				'System'      => array(
					array( 'tab', 'database', 'Database', 'db' ),
					array( 'util', 'backup', 'Backup & Restore', 'package' ),
					array( 'util', 'installer', 'Bulk Installer', 'plug' ),
					array( 'util', 'october', 'OctoberCMS Theme', 'package' ),
					array( 'util', 'filemanager', 'File Manager', 'folder' ),
				),
			);
			$vx_cat = Velox_Utilities::catalog();
			// Whole areas that can be switched off in Settings → Modules.
			$vx_tab_modules = array(
				'performance' => 'module_performance',
				'pagespeed'   => 'module_performance',
				'images'      => 'module_images',
				'seo'         => 'module_seo',
			);
			// Drop PageSpeed in right under Performance when the module is on.
			if ( class_exists( 'Velox_Pagespeed' ) && Velox_Pagespeed::enabled() ) {
				array_splice( $vx_full_nav['Essentials'], 1, 0, array( array( 'tab', 'pagespeed', 'PageSpeed', 'search' ) ) );
			}
			foreach ( $vx_full_nav as $vx_section => $vx_items ) {
				$vx_rows = '';
				foreach ( $vx_items as $vx_it ) {
					list( $vx_kind, $vx_id, $vx_lbl, $vx_icon ) = $vx_it;
					if ( 'tab' === $vx_kind ) {
						// Hide whole areas whose module is switched off.
						if ( isset( $vx_tab_modules[ $vx_id ] ) && ! Velox_Settings::get( $vx_tab_modules[ $vx_id ], true ) ) {
							continue;
						}
						$vx_url = $admin->tab_url( $vx_id );
						$vx_act = ( $current === $vx_id );
					} else {
						// Switched-off utilities disappear from the sidebar (always-on tools stay).
						if ( ! Velox_Utilities::is_available( $vx_id ) ) {
							continue;
						}
						$vx_url  = Velox_Utilities::tool_url( $vx_id );
						$vx_link = isset( $vx_cat[ $vx_id ]['link'] ) ? $vx_cat[ $vx_id ]['link'] : '';
						$vx_act  = $vx_link ? ( $current === $vx_link ) : ( 'utilities' === $current && $vx_cur_tool === $vx_id );
					}
					$vx_rows .= sprintf(
						'<a href="%s" class="velox-side-item%s"><span class="velox-side-ic">%s</span><span class="velox-side-label">%s</span></a>',
						esc_url( $vx_url ),
						$vx_act ? ' is-active' : '',
						Velox_Admin::icon( $vx_icon, 18 ),
						esc_html( $vx_lbl )
					);
				}
				if ( '' === $vx_rows ) {
					continue; // whole group is switched off — don't render an empty header
				}
				echo '<div class="velox-side-group"><div class="velox-side-grouplabel">' . esc_html( $vx_section ) . '</div>' . $vx_rows . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
		</nav>

		<div class="velox-side-foot">
			<a class="velox-side-foot-link" href="https://www.sumasearch.de/" target="_blank" rel="noopener">
				<img src="<?php echo esc_url( VELOX_URL . 'assets/menu-icon.png' ); ?>" alt="" width="14" height="14">
				<?php esc_html_e('by Sumasearch', 'velox'); ?>
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
					<h2 class="velox-wizard-h"><?php esc_html_e('Which page builder do you use?', 'velox'); ?></h2>
					<p class="velox-wizard-p"><?php printf( esc_html__( 'Pick yours below — every builder needs different speed settings. Not sure? %s', 'velox' ), '<a href="#" id="velox-wiz-detect">' . esc_html__( 'Detect it for me →', 'velox' ) . '</a>' ); ?></p>
					<div class="velox-wiz-grid" id="velox-wiz-grid">
						<?php foreach ( $vx_builders as $bid => $blabel ) : ?>
							<button type="button" class="velox-wiz-builder<?php echo $bid === $vx_detected && 'wordpress' !== $bid ? ' is-detected' : ''; ?>" data-builder="<?php echo esc_attr( $bid ); ?>">
								<span class="velox-wiz-builder-name"><?php echo esc_html( $blabel ); ?></span>
								<?php if ( $bid === $vx_detected && 'wordpress' !== $bid ) : ?><span class="velox-wiz-detected-tag"><?php esc_html_e('Detected', 'velox'); ?></span><?php endif; ?>
							</button>
						<?php endforeach; ?>
					</div>
					<p class="velox-hint" style="margin-top:14px;"><?php printf( esc_html__( 'Builder not listed? %s', 'velox' ), '<a href="#" id="velox-wizard-req-open">' . esc_html__( 'Request it →', 'velox' ) . '</a>' ); ?></p>
					<div id="velox-wizard-req" hidden>
						<div style="display:flex;gap:8px;margin-top:6px;">
							<input type="text" class="velox-input" id="velox-wizard-req-name" placeholder="e.g. Breakdance, Cwicly, Zion…">
							<button class="velox-btn velox-btn--ghost" id="velox-wizard-req-send"><?php esc_html_e('Send', 'velox'); ?></button>
						</div>
					</div>
					<div class="velox-wizard-actions">
						<button class="velox-btn velox-btn--ghost" id="velox-wizard-skip"><?php esc_html_e('Skip for now', 'velox'); ?></button>
						<button class="velox-btn velox-btn--primary" id="velox-wiz-to-path" disabled><?php esc_html_e('Next', 'velox'); ?></button>
					</div>
				</div>

				<!-- STEP 2: choose path -->
				<div class="velox-wizard-step" data-step="path" hidden>
					<h2 class="velox-wizard-h"><?php esc_html_e('How do you want to set up', 'velox'); ?> <span id="velox-wiz-blabel"><?php esc_html_e('Velox', 'velox'); ?></span>?</h2>
					<p class="velox-wizard-p"><?php esc_html_e('Pick the recommended path and Velox scans your plugins and tunes everything for you — or configure it yourself.', 'velox'); ?></p>
					<div class="velox-wiz-paths">
						<button type="button" class="velox-wiz-path is-selected" data-path="auto">
							<span class="velox-wiz-path-badge"><?php esc_html_e('Recommended', 'velox'); ?></span>
							<span class="velox-wiz-path-t">Detect &amp; recommend</span>
							<span class="velox-wiz-path-d"><?php esc_html_e('Velox scans your builder and installed plugins, then shows tuned settings you can review and tweak before applying.', 'velox'); ?></span>
						</button>
						<button type="button" class="velox-wiz-path" data-path="manual">
							<span class="velox-wiz-path-t"><?php esc_html_e('I\'ll configure it myself', 'velox'); ?></span>
							<span class="velox-wiz-path-d"><?php esc_html_e('Skip the automatic tuning and head straight to Settings → Performance to set everything by hand.', 'velox'); ?></span>
						</button>
					</div>
					<div class="velox-wizard-actions">
						<button class="velox-btn velox-btn--ghost" id="velox-wiz-back-builder"><?php esc_html_e('Back', 'velox'); ?></button>
						<button class="velox-btn velox-btn--primary" id="velox-wiz-path-next"><?php esc_html_e('Next', 'velox'); ?></button>
					</div>
				</div>

				<!-- STEP 3: review -->
				<div class="velox-wizard-step" data-step="review" hidden>
					<h2 class="velox-wizard-h"><?php esc_html_e('Recommended for', 'velox'); ?> <span id="velox-wiz-rlabel"><?php esc_html_e('your builder', 'velox'); ?></span></h2>
					<p class="velox-wizard-p" id="velox-wiz-rnote"></p>
					<div id="velox-wiz-advisories"></div>
					<div id="velox-wiz-plugins" class="velox-wiz-plugins"></div>
					<div class="velox-wiz-review" id="velox-wiz-review"><p class="velox-hint" style="padding:14px;"><?php esc_html_e('Scanning…', 'velox'); ?></p></div>
					<div class="velox-wizard-actions">
						<button class="velox-btn velox-btn--ghost" id="velox-wiz-back-path"><?php esc_html_e('Back', 'velox'); ?></button>
						<button class="velox-btn velox-btn--primary" id="velox-wizard-apply"><?php esc_html_e('Apply selected', 'velox'); ?></button>
					</div>
				</div>

				<!-- STEP 4: done -->
				<div class="velox-wizard-step" data-step="done" hidden>
					<div class="velox-wiz-done-mark">⚡</div>
					<h2 class="velox-wizard-h"><?php esc_html_e('You\'re all set', 'velox'); ?></h2>
					<p class="velox-wizard-p" id="velox-wizard-donemsg"></p>
					<div class="velox-wizard-actions">
						<a class="velox-btn velox-btn--ghost" id="velox-wizard-toperf" href="<?php echo esc_url( $admin->tab_url( 'performance' ) ); ?>"><?php esc_html_e('Open Performance', 'velox'); ?></a>
						<button class="velox-btn velox-btn--primary" id="velox-wizard-finish"><?php esc_html_e('Done', 'velox'); ?></button>
					</div>
				</div>
			</div>
		</div>

		<main class="velox-main">

		<?php
		// Global language switcher — pinned top-right on every Velox page.
		$vx_lang_cur = (string) Velox_Settings::get( 'admin_language', '' );
		?>
		<div class="velox-topbar">
			<div class="velox-langswitch" title="<?php esc_attr_e( 'Velox admin language', 'velox' ); ?>">
				<svg class="velox-ic" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
				<select class="velox-langswitch-select" id="velox-langswitch" aria-label="<?php esc_attr_e( 'Velox admin language', 'velox' ); ?>">
					<?php
					foreach ( Velox_Settings::admin_languages() as $vx_code => $vx_label ) {
						printf(
							'<option value="%s"%s>%s</option>',
							esc_attr( $vx_code ),
							selected( $vx_lang_cur, $vx_code, false ),
							esc_html( $vx_label )
						);
					}
					?>
				</select>
			</div>
		</div>

