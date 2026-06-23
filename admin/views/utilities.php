<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var Velox_Admin $admin */
$tools = Velox_Utilities::catalog();
$tool  = isset( $_GET['tool'] ) ? sanitize_key( wp_unslash( $_GET['tool'] ) ) : '';

// Route to a tool's own page.
if ( '' !== $tool && isset( $tools[ $tool ] ) && ! empty( $tools[ $tool ]['ready'] ) && ! empty( $tools[ $tool ]['page'] ) ) {
	$sub = VELOX_PATH . 'admin/views/utilities/' . $tool . '.php';
	if ( is_readable( $sub ) ) {
		$hub_url    = admin_url( 'admin.php?page=velox-utilities' );
		$tool_label = $tools[ $tool ]['label'];
		echo '<nav class="velox-breadcrumb" aria-label="Breadcrumb">'
			. '<a href="' . esc_url( admin_url( 'admin.php?page=velox' ) ) . '">Velox</a>'
			. '<span class="velox-breadcrumb-sep">/</span>'
			. '<a href="' . esc_url( $hub_url ) . '">Utilities</a>'
			. '<span class="velox-breadcrumb-sep">/</span>'
			. '<span class="velox-breadcrumb-cur">' . esc_html( $tool_label ) . '</span>'
			. '</nav>';
		include $sub;
		return;
	}
}
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Utilities</h1>
	<p class="velox-sub">A toolbox of site and admin helpers. Switch on what you need — anything you enable appears under Utilities in the sidebar, ready to open.</p>
</div>

<div class="velox-util-grid">
	<?php
	foreach ( $tools as $id => $t ) :
		$ready  = ! empty( $t['ready'] );
		$enable = isset( $t['enable'] ) ? $t['enable'] : '';
		$on     = '' !== $enable && (bool) Velox_Settings::get( $enable, false );
		$has_open = ! empty( $t['page'] ) || ! empty( $t['link'] );
		// Where "Open" goes: linked tools (Media) open a top-level tab; the rest open their utility page.
		$open_url = ! empty( $t['link'] )
			? admin_url( 'admin.php?page=velox-' . $t['link'] )
			: admin_url( 'admin.php?page=velox-utilities&tool=' . $id );
		?>
		<div class="velox-util-card<?php echo $ready ? '' : ' is-planned'; ?>">
			<div class="velox-util-ic"><?php echo Velox_Admin::icon( $t['icon'], 22 ); ?></div>
			<div class="velox-util-body">
				<div class="velox-util-top">
					<h3 class="velox-util-name"><?php echo esc_html( $t['label'] ); ?></h3>
					<?php if ( $ready && '' !== $enable ) : ?>
						<label class="velox-switch">
							<input type="checkbox" class="velox-util-toggle" data-key="<?php echo esc_attr( $enable ); ?>" data-tool="<?php echo esc_attr( $id ); ?>" <?php checked( $on ); ?>>
							<span class="velox-switch-track"></span>
						</label>
					<?php elseif ( ! $ready ) : ?>
						<span class="velox-util-badge">Planned</span>
					<?php endif; ?>
				</div>
				<p class="velox-util-desc"><?php echo esc_html( $t['desc'] ); ?></p>
				<?php if ( $ready && $has_open && $on ) : ?>
					<a class="velox-btn velox-btn--ghost velox-util-open" href="<?php echo esc_url( $open_url ); ?>">Open</a>
				<?php elseif ( $ready && $has_open && ! $on ) : ?>
					<span class="velox-util-hint-off">Switch on to use</span>
				<?php endif; ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>
<?php return; ?>
