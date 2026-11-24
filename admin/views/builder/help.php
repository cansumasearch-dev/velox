<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox Builder — Help: the complete guide, common questions and the tour. */
?>
<div class="vba-shell vba-help">
	<header class="vba-head">
		<div><h1><?php esc_html_e( 'Help', 'velox' ); ?></h1>
		<p><?php esc_html_e( 'The complete guide to the builder, answers to common questions, and a short tour.', 'velox' ); ?></p></div>
		<div class="vba-head-actions">
			<button type="button" class="vba-btn vba-btn-primary" data-vx-tour="builder"><?php esc_html_e( 'Start the tour', 'velox' ); ?></button>
		</div>
	</header>
	<section class="vba-card vba-help-card">
		<div class="vba-help-body" id="vba-help" data-vx-help="builder"></div>
	</section>
</div>
