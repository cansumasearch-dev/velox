<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox Builder — Help: the tour and answers to common questions. */
?>
<div class="vba-shell vba-help">
	<header class="vba-head">
		<div><h1><?php esc_html_e( 'Help', 'velox' ); ?></h1>
		<p><?php esc_html_e( 'A short tour of the builder, and answers to the questions that come up most.', 'velox' ); ?></p></div>
		<div class="vba-head-actions">
			<button type="button" class="vba-btn vba-btn-primary" data-vx-tour="builder"><?php esc_html_e( 'Start the tour', 'velox' ); ?></button>
		</div>
	</header>
	<section class="vba-card vba-help-card">
		<div class="vba-help-body" id="vba-help"></div>
	</section>
</div>
<script>
document.addEventListener( 'DOMContentLoaded', function () { if ( window.vxHelp ) { window.vxHelp( document.getElementById( 'vba-help' ), 'builder' ); } } );
</script>
