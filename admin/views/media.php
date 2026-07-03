<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Media Editor — Alt text &amp; titles</h1>
	<p class="velox-sub">Add alt text, titles and captions to every image for SEO and accessibility, and rename files safely. File renames update every reference across your posts and Oxygen builder data automatically.</p>
</div>

<div class="velox-alert velox-alert--info">
	Edit the <strong>Title</strong>, <strong>Alt text</strong> and <strong>Caption</strong> on any image below, then hit <strong>Save</strong>. Use <strong>Rename file</strong> to change the actual filename without breaking links. For bulk work, use <strong>Export / Bulk import</strong> with the <code>Dateiname | Alt-Text | Titel</code> format.
</div>

<div class="velox-toolbar">
	<input type="search" id="velox-media-search" class="velox-input" placeholder="Search filename or title…">
	<div class="velox-toolbar-right">
		<button class="velox-btn velox-btn--ghost" id="velox-media-download">Download images</button>
		<button class="velox-btn velox-btn--ghost" id="velox-pipe-export">Export pipe list</button>
		<button class="velox-btn velox-btn--ghost" id="velox-pipe-open">Bulk import</button>
	</div>
</div>

<div class="velox-media-selectbar" id="velox-media-selectbar" hidden>
	<span class="velox-hint" id="velox-media-selcount">Tick the images you want, then download. Alt text &amp; titles come along in a text file.</span>
	<span class="velox-media-selectbar-actions">
		<button class="velox-btn velox-btn--ghost velox-btn--sm" id="velox-media-selectall">Select all</button>
		<button class="velox-btn velox-btn--primary velox-btn--sm" id="velox-media-dl-go" disabled>Download selected</button>
		<button class="velox-btn velox-btn--ghost velox-btn--sm" id="velox-media-selectcancel">Cancel</button>
	</span>
</div>

<div class="velox-media-grid" id="velox-media-grid"><div class="velox-loading">Loading media…</div></div>

<div class="velox-pager">
	<button class="velox-btn velox-btn--ghost" id="velox-media-prev" disabled>← Prev</button>
	<span id="velox-media-pageinfo" class="velox-hint">—</span>
	<button class="velox-btn velox-btn--ghost" id="velox-media-next" disabled>Next →</button>
</div>

<!-- Rename modal -->
<div class="velox-modal" id="velox-rename-modal" hidden>
	<div class="velox-modal-box">
		<h3 class="velox-panel-title">Rename file</h3>
		<p class="velox-hint" id="velox-rename-current"></p>
		<label class="velox-field">
			<span class="velox-field-label">New file name</span>
			<input type="text" id="velox-rename-input" class="velox-input" placeholder="erste-hilfe-kurs-neuss-team">
			<span class="velox-hint">No extension needed. Spaces &amp; caps become kebab-case. All thumbnail sizes and WebP twins are renamed too.</span>
		</label>
		<div class="velox-actions">
			<button class="velox-btn velox-btn--primary" id="velox-rename-go">Rename &amp; fix references</button>
			<button class="velox-btn velox-btn--ghost" id="velox-rename-cancel">Cancel</button>
		</div>
	</div>
</div>

<!-- Pipe import modal -->
<div class="velox-modal" id="velox-pipe-modal" hidden>
	<div class="velox-modal-box velox-modal-box--lg">
		<h3 class="velox-panel-title">Bulk import — pipe format</h3>
		<p class="velox-hint">One row per image: <code>Dateiname | Alt-Text | Titel</code>. Filenames are matched to your library; a header row is ignored.</p>
		<textarea id="velox-pipe-text" class="velox-textarea" rows="12" placeholder="erste-hilfe-kurs-neuss-team.webp | Team beim Erste-Hilfe-Kurs in Neuss | Erste-Hilfe-Kurs Neuss"></textarea>
		<div class="velox-actions">
			<button class="velox-btn velox-btn--primary" id="velox-pipe-apply">Apply to library</button>
			<button class="velox-btn velox-btn--ghost" id="velox-pipe-cancel">Close</button>
		</div>
		<div id="velox-pipe-result" class="velox-hint"></div>
	</div>
</div>
