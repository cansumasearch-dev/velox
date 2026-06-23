<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$redirects = Velox_Redirects::list_redirects();
$logs      = Velox_Redirects::list_404s();
$log_on    = Velox_Settings::get( 'util_redirects_log_404', true );
$types     = array( 301 => '301 Permanent', 302 => '302 Temporary', 307 => '307 Temporary', 410 => '410 Gone' );
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Redirects &amp; 404s</h1>
	<p class="velox-sub">Send old or moved URLs somewhere useful, and watch which missing pages your visitors actually hit so you can fix the ones that matter.</p>
</div>

<div class="velox-panel velox-tool-form">
	<h3 class="velox-panel-title">Add a redirect</h3>
	<div class="velox-redir-add">
		<input type="text" class="velox-input" id="velox-redir-source" placeholder="/old-page">
		<span class="velox-redir-arrow">&rarr;</span>
		<input type="text" class="velox-input" id="velox-redir-target" placeholder="/new-page or https://…">
		<select class="velox-select" id="velox-redir-type">
			<?php foreach ( $types as $code => $label ) : ?>
				<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<button class="velox-btn velox-btn--primary" id="velox-redir-add">Add</button>
	</div>
	<span class="velox-hint">Source is a path on this site. Target can be a path (<code>/new</code>) or a full URL. Choose <strong>410 Gone</strong> to tell search engines a page is permanently removed.</span>
</div>

<div class="velox-panel">
	<h3 class="velox-panel-title">Active redirects <span class="velox-count"><?php echo count( $redirects ); ?></span></h3>
	<div id="velox-redir-list" class="velox-redir-list">
		<?php if ( empty( $redirects ) ) : ?>
			<p class="velox-hint" id="velox-redir-empty">No redirects yet.</p>
		<?php else : ?>
			<?php foreach ( $redirects as $r ) : ?>
				<div class="velox-redir-row" data-id="<?php echo esc_attr( $r['id'] ); ?>">
					<span class="velox-redir-src"><?php echo esc_html( $r['source'] ); ?></span>
					<span class="velox-redir-arrow">&rarr;</span>
					<span class="velox-redir-tgt"><?php echo 410 === (int) $r['type'] ? '<em>410 Gone</em>' : esc_html( $r['target'] ); ?></span>
					<span class="velox-redir-type"><?php echo esc_html( $r['type'] ); ?></span>
					<span class="velox-redir-hits"><?php echo (int) $r['hits']; ?> hits</span>
					<button class="velox-btn velox-btn--ghost velox-redir-del">Delete</button>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>

<div class="velox-panel">
	<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
		<h3 class="velox-panel-title" style="margin:0;">404 log <span class="velox-count"><?php echo count( $logs ); ?></span></h3>
		<div style="display:flex;align-items:center;gap:14px;">
			<label class="velox-inline-toggle">
				<span>Log 404s</span>
				<span class="velox-switch"><input type="checkbox" data-setting="util_redirects_log_404" id="velox-log-toggle" <?php checked( $log_on ); ?>><span class="velox-switch-track"></span></span>
			</label>
			<button class="velox-btn velox-btn--ghost" id="velox-log-clear"<?php echo empty( $logs ) ? ' hidden' : ''; ?>>Clear log</button>
		</div>
	</div>
	<div id="velox-log-list" class="velox-log-list">
		<?php if ( empty( $logs ) ) : ?>
			<p class="velox-hint" id="velox-log-empty">No 404s logged yet — that's a good thing.</p>
		<?php else : ?>
			<?php foreach ( $logs as $l ) : ?>
				<div class="velox-log-row" data-id="<?php echo esc_attr( $l['id'] ); ?>" data-path="<?php echo esc_attr( $l['path'] ); ?>">
					<span class="velox-log-path"><?php echo esc_html( $l['path'] ); ?></span>
					<span class="velox-log-hits"><?php echo (int) $l['hits']; ?> hits</span>
					<button class="velox-btn velox-btn--ghost velox-log-fix">&rarr; Redirect</button>
					<button class="velox-btn velox-btn--ghost velox-log-forget">Forget</button>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>
