<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var Velox_Admin $admin */
$tools = Velox_Utilities::catalog();
?>
<div class="velox-page-head">
	<h1 class="velox-h1">Utilities</h1>
	<p class="velox-sub">A growing toolbox of site and admin helpers. Flip on what you need — each tool stays dormant until you switch it on.</p>
</div>

<div class="velox-util-grid">
	<?php foreach ( $tools as $id => $t ) :
		$ready = ! empty( $t['ready'] );
		$on    = $ready && Velox_Settings::get( $t['setting'] );
		?>
		<div class="velox-util-card<?php echo $ready ? '' : ' is-planned'; ?>">
			<div class="velox-util-ic"><?php echo Velox_Admin::icon( $t['icon'], 22 ); ?></div>
			<div class="velox-util-body">
				<div class="velox-util-top">
					<h3 class="velox-util-name"><?php echo esc_html( $t['label'] ); ?></h3>
					<?php if ( $ready ) : ?>
						<label class="velox-switch">
							<input type="checkbox" class="velox-util-toggle" data-key="<?php echo esc_attr( $t['setting'] ); ?>" <?php checked( $on ); ?>>
							<span class="velox-switch-track"></span>
						</label>
					<?php else : ?>
						<span class="velox-util-badge">Planned</span>
					<?php endif; ?>
				</div>
				<p class="velox-util-desc"><?php echo esc_html( $t['desc'] ); ?></p>
			</div>
		</div>
	<?php endforeach; ?>
</div>
