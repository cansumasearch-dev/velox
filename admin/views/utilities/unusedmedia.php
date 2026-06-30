<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Unused media</h1>
	<p class="velox-sub">Finds images nothing in your content or page-builder data points at. It's deliberately cautious — it won't list a file that looks referenced anywhere — but always eyeball the list before deleting, since some references (external CSS, exports) can't be detected.</p>
</div>

<div class="velox-panel">
	<div class="velox-tool-actions" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 4px;">
		<button class="velox-btn velox-btn--primary" id="velox-media-scan">Scan media library</button>
		<span class="velox-seg" id="velox-media-filter" hidden>
			<button type="button" class="velox-seg-btn is-on" data-mediafilter="unused">Unused</button>
			<button type="button" class="velox-seg-btn" data-mediafilter="used">Used</button>
		</span>
		<button class="velox-btn velox-btn--danger" id="velox-media-delete" hidden>Delete selected</button>
		<span class="velox-hint" id="velox-media-summary"></span>
	</div>
	<div id="velox-media-results" class="velox-media-results"></div>
</div>
