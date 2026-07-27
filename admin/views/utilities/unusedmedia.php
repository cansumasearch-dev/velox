<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="velox-page-head">
	<h1 class="velox-h2"><?php esc_html_e('Unused media', 'velox'); ?></h1>
	<p class="velox-sub"><?php esc_html_e('Finds images nothing in your content or page-builder data points at. It\'s deliberately cautious — it won\'t list a file that looks referenced anywhere — but always eyeball the list before deleting, since some references (external CSS, exports) can\'t be detected.', 'velox'); ?></p>
</div>

<div class="velox-panel velox-media-tool">
	<div class="velox-tool-actions" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0;">
		<button class="velox-btn velox-btn--primary" id="velox-media-scan"><?php esc_html_e('Scan media library', 'velox'); ?></button>
		<span class="velox-seg" id="velox-media-filter" hidden>
			<button type="button" class="velox-seg-btn is-on" data-mediafilter="unused"><?php esc_html_e('Not in use', 'velox'); ?></button>
			<button type="button" class="velox-seg-btn" data-mediafilter="used"><?php esc_html_e('In use', 'velox'); ?></button>
		</span>
		<button class="velox-btn velox-btn--danger" id="velox-media-delete" hidden><?php esc_html_e('Delete selected', 'velox'); ?></button>
		<span class="velox-hint" id="velox-media-summary"></span>
	</div>
	<div id="velox-media-results" class="velox-media-results"></div>
</div>
