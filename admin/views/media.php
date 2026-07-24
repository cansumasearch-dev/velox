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
<div class="velox-modal" id="velox-resize-modal" hidden>
	<div class="velox-modal-box velox-resize-box">
		<h3 class="velox-h4">Resize image</h3>
		<p class="velox-hint" id="velox-resize-current"></p>
		<div class="velox-resize-preview"><img id="velox-resize-img" alt=""></div>
		<div class="velox-resize-fields">
			<label class="velox-field">
				<span class="velox-field-label">Width</span>
				<span class="velox-resize-num"><input type="number" min="1" max="12000" id="velox-resize-w" class="velox-input"><span class="u">px</span></span>
			</label>
			<button type="button" class="velox-resize-link is-on" id="velox-resize-lock" title="Keep the original proportions" aria-pressed="true">
				<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>
			</button>
			<label class="velox-field">
				<span class="velox-field-label">Height</span>
				<span class="velox-resize-num"><input type="number" min="1" max="12000" id="velox-resize-h" class="velox-input"><span class="u">px</span></span>
			</label>
		</div>
		<div class="velox-resize-presets" id="velox-resize-presets">
			<button type="button" data-scale="0.5">50%</button>
			<button type="button" data-scale="0.75">75%</button>
			<button type="button" data-scale="1">Original</button>
			<button type="button" data-scale="1.5">150%</button>
			<button type="button" data-scale="2">200%</button>
		</div>
		<p class="velox-hint">The file is replaced in place and its thumbnails are rebuilt, so the filename and every link to it stay exactly as they are. This cannot be undone.</p>
		<div class="velox-modal-actions">
			<button class="velox-btn velox-btn--primary" id="velox-resize-go">Resize image</button>
			<button class="velox-btn velox-btn--ghost" id="velox-resize-cancel">Cancel</button>
		</div>
	</div>
</div>

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
