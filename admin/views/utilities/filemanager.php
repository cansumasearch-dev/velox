<?php
/**
 * File Manager tool page. The browser + editor are populated by initFileManager().
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="velox-page-head">
	<h1 class="velox-h2"><?php esc_html_e('File Manager', 'velox'); ?></h1>
	<p class="velox-sub"><?php esc_html_e( "Browse and edit your site's files, like SFTP or the Plesk file manager. Changes write straight to the server &mdash; there is no undo.", 'velox' ); ?></p>
</div>

<div class="velox-alert velox-alert--warn"><strong><?php esc_html_e('Careful:', 'velox'); ?></strong> <?php printf( esc_html__( 'editing core, %1$s, or a theme\'s %2$s can take the whole site down. If you\'re unsure, make a backup first (Utilities &rarr; Backup &amp; restore).', 'velox' ), '<code>' . esc_html__( 'wp-config.php', 'velox' ) . '</code>', '<code>' . esc_html__( 'functions.php', 'velox' ) . '</code>' ); ?></div>

<div class="velox-fm" id="velox-fm">
	<div class="velox-fm-browser">
		<div class="velox-fm-crumbs" id="velox-fm-crumbs"></div>
		<div class="velox-fm-list" id="velox-fm-list"><div class="velox-loading"><?php esc_html_e('Loading&hellip;', 'velox'); ?></div></div>
	</div>
	<div class="velox-fm-editor" id="velox-fm-editor">
		<div class="velox-fm-empty">
			<svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 12px;display:block;opacity:.5"><path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.7.7l3.6 3.6A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/></svg>
			<?php esc_html_e('Pick a file on the left to open it here.', 'velox'); ?>
		</div>
	</div>
</div>
