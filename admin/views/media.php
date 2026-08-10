<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="velox-page-head">
	<h1 class="velox-h2"><?php esc_html_e( 'Media Editor — Alt text &amp; titles', 'velox' ); ?></h1>
	<p class="velox-sub"><?php esc_html_e('Add alt text, titles and captions to every image for SEO and accessibility, and rename files safely. File renames update every reference across your posts and Oxygen builder data automatically.', 'velox'); ?></p>
</div>

<div class="velox-alert velox-alert--info">
	<?php printf( esc_html__( 'Edit the %1$s, %2$s and %3$s on any image below, then hit %4$s. Use %5$s to change the actual filename without breaking links. For bulk work, use %6$s with the %7$s format.', 'velox' ), '<strong>' . esc_html__( 'Title', 'velox' ) . '</strong>', '<strong>' . esc_html__( 'Alt text', 'velox' ) . '</strong>', '<strong>' . esc_html__( 'Caption', 'velox' ) . '</strong>', '<strong>' . esc_html__( 'Save', 'velox' ) . '</strong>', '<strong>' . esc_html__( 'Rename file', 'velox' ) . '</strong>', '<strong>' . esc_html__( 'Export / Bulk import', 'velox' ) . '</strong>', '<code>' . esc_html__( 'Dateiname | Alt-Text | Titel', 'velox' ) . '</code>' ); ?>
</div>

<div class="velox-toolbar">
	<input type="search" id="velox-media-search" class="velox-input" placeholder="Search filename or title…">
	<div class="velox-media-perpage">
		<span class="velox-media-perpage-label"><?php esc_html_e('Show', 'velox'); ?></span>
		<div class="velox-media-perpage-btns" role="group">
			<button type="button" class="velox-btn velox-btn--ghost velox-btn--sm velox-pp-btn is-active" data-pp="20">20</button>
			<button type="button" class="velox-btn velox-btn--ghost velox-btn--sm velox-pp-btn" data-pp="50">50</button>
			<button type="button" class="velox-btn velox-btn--ghost velox-btn--sm velox-pp-btn" data-pp="100">100</button>
			<button type="button" class="velox-btn velox-btn--ghost velox-btn--sm velox-pp-btn" data-pp="-1"><?php esc_html_e('All', 'velox'); ?></button>
		</div>
		<input type="number" id="velox-media-perpage-custom" class="velox-input velox-input--sm" min="1" max="500" placeholder="<?php esc_attr_e('#', 'velox'); ?>" aria-label="<?php esc_attr_e('Custom number of images', 'velox'); ?>">
	</div>
	<div class="velox-toolbar-right">
		<button class="velox-btn velox-btn--ghost" id="velox-media-download"><?php esc_html_e('Download images', 'velox'); ?></button>
		<button class="velox-btn velox-btn--ghost" id="velox-pipe-export"><?php esc_html_e('Export pipe list', 'velox'); ?></button>
		<button class="velox-btn velox-btn--ghost" id="velox-pipe-open"><?php esc_html_e('Bulk import', 'velox'); ?></button>
	</div>
</div>

<div class="velox-media-selectbar" id="velox-media-selectbar" hidden>
	<span class="velox-hint" id="velox-media-selcount"><?php esc_html_e('Tick the images you want, then download. Alt text &amp; titles come along in a text file.', 'velox'); ?></span>
	<span class="velox-media-selectbar-actions">
		<button class="velox-btn velox-btn--ghost velox-btn--sm" id="velox-media-selectall"><?php esc_html_e('Select all', 'velox'); ?></button>
		<button class="velox-btn velox-btn--primary velox-btn--sm" id="velox-media-dl-go" disabled><?php esc_html_e('Download selected', 'velox'); ?></button>
		<button class="velox-btn velox-btn--ghost velox-btn--sm" id="velox-media-selectcancel"><?php esc_html_e('Cancel', 'velox'); ?></button>
	</span>
</div>

<div class="velox-media-grid" id="velox-media-grid"><div class="velox-loading"><?php esc_html_e('Loading media…', 'velox'); ?></div></div>

<div class="velox-pager">
	<button class="velox-btn velox-btn--ghost" id="velox-media-prev" disabled><?php esc_html_e('← Prev', 'velox'); ?></button>
	<span id="velox-media-pageinfo" class="velox-hint">—</span>
	<button class="velox-btn velox-btn--ghost" id="velox-media-next" disabled><?php esc_html_e('Next →', 'velox'); ?></button>
</div>

<!-- Rename modal -->
<div class="velox-modal" id="velox-rename-modal" hidden>
	<div class="velox-modal-box">
		<h3 class="velox-panel-title"><?php esc_html_e('Rename file', 'velox'); ?></h3>
		<p class="velox-hint" id="velox-rename-current"></p>
		<label class="velox-field">
			<span class="velox-field-label"><?php esc_html_e('New file name', 'velox'); ?></span>
			<input type="text" id="velox-rename-input" class="velox-input" placeholder="erste-hilfe-kurs-neuss-team">
			<span class="velox-hint"><?php esc_html_e('No extension needed. Spaces &amp; caps become kebab-case. All thumbnail sizes and WebP twins are renamed too.', 'velox'); ?></span>
		</label>
		<div class="velox-actions">
			<button class="velox-btn velox-btn--primary" id="velox-rename-go"><?php esc_html_e('Rename &amp; fix references', 'velox'); ?></button>
			<button class="velox-btn velox-btn--ghost" id="velox-rename-cancel"><?php esc_html_e('Cancel', 'velox'); ?></button>
		</div>
	</div>
</div>

<!-- Pipe import modal -->
<div class="velox-modal" id="velox-resize-modal" hidden>
	<div class="velox-modal-box velox-resize-box">
		<h3 class="velox-h4"><?php esc_html_e('Resize image', 'velox'); ?></h3>
		<p class="velox-hint" id="velox-resize-current"></p>
		<div class="velox-resize-preview"><img id="velox-resize-img" alt=""></div>
		<div class="velox-resize-fields">
			<label class="velox-field">
				<span class="velox-field-label"><?php esc_html_e('Width', 'velox'); ?></span>
				<span class="velox-resize-num"><input type="number" min="1" max="12000" id="velox-resize-w" class="velox-input"><span class="u"><?php esc_html_e('px', 'velox'); ?></span></span>
			</label>
			<button type="button" class="velox-resize-link is-on" id="velox-resize-lock" title="<?php esc_attr_e('Keep the original proportions', 'velox'); ?>" aria-pressed="true">
				<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>
			</button>
			<label class="velox-field">
				<span class="velox-field-label"><?php esc_html_e('Height', 'velox'); ?></span>
				<span class="velox-resize-num"><input type="number" min="1" max="12000" id="velox-resize-h" class="velox-input"><span class="u"><?php esc_html_e('px', 'velox'); ?></span></span>
			</label>
		</div>
		<div class="velox-resize-presets" id="velox-resize-presets">
			<button type="button" data-scale="0.5">50%</button>
			<button type="button" data-scale="0.75">75%</button>
			<button type="button" data-scale="1"><?php esc_html_e('Original', 'velox'); ?></button>
			<button type="button" data-scale="1.5">150%</button>
			<button type="button" data-scale="2">200%</button>
		</div>
		<p class="velox-hint"><?php esc_html_e('The file is replaced in place and its thumbnails are rebuilt, so the filename and every link to it stay exactly as they are. This cannot be undone.', 'velox'); ?></p>
		<div class="velox-modal-actions">
			<button class="velox-btn velox-btn--primary" id="velox-resize-go"><?php esc_html_e('Resize image', 'velox'); ?></button>
			<button class="velox-btn velox-btn--ghost" id="velox-resize-cancel"><?php esc_html_e('Cancel', 'velox'); ?></button>
		</div>
	</div>
</div>

<div class="velox-modal" id="velox-pipe-modal" hidden>
	<div class="velox-modal-box velox-modal-box--lg">
		<h3 class="velox-panel-title"><?php esc_html_e('Bulk import — pipe format', 'velox'); ?></h3>
		<p class="velox-hint"><?php printf( esc_html__( 'One row per image: %s. Filenames are matched to your library; a header row is ignored.', 'velox' ), '<code>' . esc_html__( 'Dateiname | Alt-Text | Titel', 'velox' ) . '</code>' ); ?></p>
		<textarea id="velox-pipe-text" class="velox-textarea" rows="12" placeholder="erste-hilfe-kurs-neuss-team.webp | Team beim Erste-Hilfe-Kurs in Neuss | Erste-Hilfe-Kurs Neuss"></textarea>
		<div class="velox-actions">
			<button class="velox-btn velox-btn--primary" id="velox-pipe-apply"><?php esc_html_e('Apply to library', 'velox'); ?></button>
			<button class="velox-btn velox-btn--ghost" id="velox-pipe-cancel"><?php esc_html_e('Close', 'velox'); ?></button>
		</div>
		<div id="velox-pipe-result" class="velox-hint"></div>
	</div>
</div>
