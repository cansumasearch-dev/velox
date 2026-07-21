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
	<h1 class="velox-h2">File Manager</h1>
	<p class="velox-sub">Browse and edit your site's files, like SFTP or the Plesk file manager. Changes write straight to the server &mdash; there is no undo.</p>
</div>

<div class="velox-alert velox-alert--warn"><strong>Careful:</strong> editing core, <code>wp-config.php</code>, or a theme's <code>functions.php</code> can take the whole site down. If you're unsure, make a backup first (Utilities &rarr; Backup &amp; restore).</div>

<div class="velox-fm" id="velox-fm">
	<div class="velox-fm-browser">
		<div class="velox-fm-crumbs" id="velox-fm-crumbs"></div>
		<div class="velox-fm-list" id="velox-fm-list"><div class="velox-loading">Loading&hellip;</div></div>
	</div>
	<div class="velox-fm-editor" id="velox-fm-editor">
		<div class="velox-fm-empty">
			<svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 12px;display:block;opacity:.5"><path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.7.7l3.6 3.6A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/></svg>
			Pick a file on the left to open it here.
		</div>
	</div>
</div>
