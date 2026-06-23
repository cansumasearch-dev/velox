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
		$hub_url = admin_url( 'admin.php?page=velox-utilities' );
		echo '<a class="velox-back" href="' . esc_url( $hub_url ) . '">&larr; All utilities</a>';
		include $sub;
		return;
	}
}
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Utilities</h1>
	<p class="velox-sub">A growing toolbox of site and admin helpers. Flip on what you need — each tool stays dormant until you switch it on.</p>
</div>

<div class="velox-util-grid">
	<?php
	foreach ( $tools as $id => $t ) :
		$ready    = ! empty( $t['ready'] );
		$has_page = ! empty( $t['page'] );
		$is_link  = ! empty( $t['link'] ); // opens an existing top-level view (e.g. Media Editor)
		// Tools with a clear on/off switch report it; action tools (installer,
		// unused media, redirects) just show an Open button, no pill.
		$enable_keys = array(
			'maintenance' => 'util_maintenance',
			'activity'    => 'util_activity',
			'scripts'     => 'util_scripts',
			'mail'        => 'util_mail',
		);
		$has_state = false;
		$on        = false;
		if ( $ready && $has_page ) {
			if ( isset( $enable_keys[ $id ] ) ) {
				$has_state = true;
				$on        = (bool) Velox_Settings::get( $enable_keys[ $id ] );
			} elseif ( 'loginurl' === $id ) {
				$has_state = true;
				$on        = ( '' !== Velox_Utilities::login_slug() );
			}
		} else {
			$on = $ready && ! empty( $t['setting'] ) && Velox_Settings::get( $t['setting'] );
		}
		?>
		<div class="velox-util-card<?php echo $ready ? '' : ' is-planned'; ?>">
			<div class="velox-util-ic"><?php echo Velox_Admin::icon( $t['icon'], 22 ); ?></div>
			<div class="velox-util-body">
				<div class="velox-util-top">
					<h3 class="velox-util-name"><?php echo esc_html( $t['label'] ); ?></h3>
					<?php if ( $ready && $has_page && $has_state ) : ?>
						<span class="velox-util-state<?php echo $on ? ' is-on' : ''; ?>"><?php echo $on ? 'On' : 'Off'; ?></span>
					<?php elseif ( $ready && ! $has_page ) : ?>
						<label class="velox-switch">
							<input type="checkbox" class="velox-util-toggle" data-key="<?php echo esc_attr( $t['setting'] ); ?>" <?php checked( $on ); ?>>
							<span class="velox-switch-track"></span>
						</label>
					<?php elseif ( ! $ready ) : ?>
						<span class="velox-util-badge">Planned</span>
					<?php endif; ?>
				</div>
				<p class="velox-util-desc"><?php echo esc_html( $t['desc'] ); ?></p>
				<?php if ( $ready && $has_page ) : ?>
					<a class="velox-btn velox-btn--ghost velox-util-open" href="<?php echo esc_url( admin_url( 'admin.php?page=velox-utilities&tool=' . $id ) ); ?>">Open</a>
				<?php elseif ( $ready && $is_link && $on ) : ?>
					<a class="velox-btn velox-btn--ghost velox-util-open" href="<?php echo esc_url( admin_url( 'admin.php?page=velox-' . $t['link'] ) ); ?>">Open</a>
				<?php endif; ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>
