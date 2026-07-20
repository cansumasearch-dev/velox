<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$redirects = Velox_Redirects::list_redirects();
$log_on    = Velox_Settings::get( 'util_redirects_log_404', true );
$logs      = $log_on ? Velox_Redirects::list_404s() : array();
$types     = array( 301 => '301 Permanent', 302 => '302 Temporary', 307 => '307 Temporary', 410 => '410 Gone' );
$matches   = array( 'exact' => 'Exact URL', 'prefix' => 'URL starts with', 'regex' => 'Regex pattern' );

/**
 * Render the metadata badges shown on a redirect row.
 */
if ( ! function_exists( 'velox_redir_badges' ) ) {
	function velox_redir_badges( $r ) {
		$out = '';
		$mt  = isset( $r['match_type'] ) ? $r['match_type'] : 'exact';
		if ( 'prefix' === $mt ) {
			$out .= '<span class="velox-redir-badge">Prefix</span>';
		} elseif ( 'regex' === $mt ) {
			$out .= '<span class="velox-redir-badge">Regex</span>';
		}
		if ( ! empty( $r['category'] ) ) {
			$out .= '<span class="velox-redir-badge velox-redir-badge--cat">' . esc_html( $r['category'] ) . '</span>';
		}
		if ( isset( $r['active'] ) && ! $r['active'] ) {
			$out .= '<span class="velox-redir-badge velox-redir-badge--off">Off</span>';
		}
		return $out;
	}
}
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Redirects &amp; 404s</h1>
	<p class="velox-sub">Send old or moved URLs somewhere useful, and watch which missing pages your visitors actually hit so you can fix the ones that matter.</p>
</div>

<div class="velox-panel">
	<div class="velox-redir-head">
		<h3 class="velox-panel-title" style="margin:0;">Active redirects <span class="velox-count"><?php echo count( $redirects ); ?></span></h3>
		<button class="velox-btn velox-btn--primary" id="velox-redir-open">
			<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="margin-right:5px;"><path d="M12 5v14M5 12h14"/></svg>Add redirect
		</button>
	</div>
	<div id="velox-redir-list" class="velox-redir-list">
		<?php if ( empty( $redirects ) ) : ?>
			<p class="velox-hint" id="velox-redir-empty">No redirects yet. Add one to send an old URL somewhere useful.</p>
		<?php else : ?>
			<?php foreach ( $redirects as $r ) :
				$mt    = isset( $r['match_type'] ) ? $r['match_type'] : 'exact';
				$is_ex = ( 'exact' === $mt );
				?>
				<div class="velox-redir-row<?php echo ( isset( $r['active'] ) && ! $r['active'] ) ? ' is-off' : ''; ?>"
					data-id="<?php echo esc_attr( $r['id'] ); ?>"
					data-source="<?php echo esc_attr( $r['source'] ); ?>"
					data-target="<?php echo esc_attr( $r['target'] ); ?>"
					data-type="<?php echo esc_attr( $r['type'] ); ?>"
					data-match="<?php echo esc_attr( $mt ); ?>"
					data-priority="<?php echo esc_attr( isset( $r['priority'] ) ? $r['priority'] : 0 ); ?>"
					data-category="<?php echo esc_attr( isset( $r['category'] ) ? $r['category'] : '' ); ?>"
					data-description="<?php echo esc_attr( isset( $r['description'] ) ? $r['description'] : '' ); ?>"
					data-active="<?php echo ( ! isset( $r['active'] ) || $r['active'] ) ? '1' : '0'; ?>"
					data-ignore-case="<?php echo ( ! isset( $r['ignore_case'] ) || $r['ignore_case'] ) ? '1' : '0'; ?>"
					data-ignore-query="<?php echo ( ! isset( $r['ignore_query'] ) || $r['ignore_query'] ) ? '1' : '0'; ?>"
					data-ignore-slash="<?php echo ( ! isset( $r['ignore_slash'] ) || $r['ignore_slash'] ) ? '1' : '0'; ?>"
					data-visit="<?php echo $is_ex ? esc_url( home_url( $r['source'] ) ) : ''; ?>">
					<div class="velox-redir-main">
						<div class="velox-redir-line">
							<span class="velox-redir-src"><?php echo esc_html( $r['source'] ); ?></span>
							<span class="velox-redir-arrow">&rarr;</span>
							<span class="velox-redir-tgt"><?php echo 410 === (int) $r['type'] ? '<em>410 Gone</em>' : esc_html( $r['target'] ); ?></span>
						</div>
						<?php $badges = velox_redir_badges( $r ); ?>
						<?php if ( $badges || ! empty( $r['description'] ) ) : ?>
							<div class="velox-redir-meta">
								<?php echo $badges; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php if ( ! empty( $r['description'] ) ) : ?>
									<span class="velox-redir-desc"><?php echo esc_html( $r['description'] ); ?></span>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
					<span class="velox-redir-type"><?php echo esc_html( $r['type'] ); ?></span>
					<span class="velox-redir-hits"><?php echo (int) $r['hits']; ?> hits</span>
					<label class="velox-switch velox-switch--sm velox-redir-toggle" title="Enable or disable this redirect">
						<input type="checkbox" class="velox-redir-active" <?php checked( ! isset( $r['active'] ) || $r['active'] ); ?>>
						<span class="velox-switch-track"></span>
					</label>
					<?php if ( $is_ex ) : ?>
						<button class="velox-btn velox-btn--ghost velox-redir-visit" title="Open the source URL in a new tab to test it">Visit</button>
					<?php endif; ?>
					<button class="velox-btn velox-btn--ghost velox-redir-edit">Edit</button>
					<button class="velox-btn velox-btn--ghost velox-redir-del">Delete</button>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<span class="velox-hint">Source is a path on this site. Target can be a path (<code>/new</code>) or a full URL. Choose <strong>410 Gone</strong> to tell search engines a page is permanently removed.</span>
</div>

<div class="velox-panel">
	<div class="velox-redir-head">
		<h3 class="velox-panel-title" style="margin:0;">404 log <span class="velox-count"><?php echo count( $logs ); ?></span></h3>
		<div class="velox-redir-head-actions">
			<label class="velox-inline-toggle">
				<span>Log 404s</span>
				<span class="velox-switch"><input type="checkbox" data-setting="util_redirects_log_404" id="velox-log-toggle" <?php checked( $log_on ); ?>><span class="velox-switch-track"></span></span>
			</label>
			<button class="velox-btn velox-btn--ghost" id="velox-log-clear"<?php echo empty( $logs ) ? ' hidden' : ''; ?>>Clear log</button>
		</div>
	</div>
	<div id="velox-log-list" class="velox-log-list">
		<?php if ( empty( $logs ) ) : ?>
			<p class="velox-hint" id="velox-log-empty"><?php echo $log_on ? 'No 404s logged yet — that\'s a good thing.' : 'Logging is off, so the 404 log is hidden. Turn it back on to collect and show missing URLs again.'; ?></p>
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

<!-- Add / edit redirect modal -->
<div class="velox-modal" id="velox-redir-modal" hidden>
	<div class="velox-modal-box velox-modal-box--lg" role="dialog" aria-modal="true" aria-label="Redirect">
		<div class="velox-modal-head">
			<h3 class="velox-modal-title" id="velox-redir-modal-title">New redirect</h3>
			<button type="button" class="velox-modal-x" data-redir-close aria-label="Close">
				<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
			</button>
		</div>
		<div class="velox-modal-body">
			<input type="hidden" id="velox-redir-id" value="0">
			<div class="velox-field">
				<label class="velox-field-label" for="velox-redir-source">From</label>
				<input type="text" class="velox-input" id="velox-redir-source" placeholder="/old-page">
			</div>
			<div class="velox-field" id="velox-redir-target-field">
				<label class="velox-field-label" for="velox-redir-target">To</label>
				<input type="text" class="velox-input" id="velox-redir-target" placeholder="/new-page or https://…">
			</div>
			<div class="velox-grid-2">
				<div class="velox-field">
					<label class="velox-field-label" for="velox-redir-type">HTTP status</label>
					<select class="velox-select" id="velox-redir-type">
						<?php foreach ( $types as $code => $label ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="velox-field">
					<label class="velox-field-label" for="velox-redir-match">Match type</label>
					<select class="velox-select" id="velox-redir-match">
						<?php foreach ( $matches as $mk => $mlabel ) : ?>
							<option value="<?php echo esc_attr( $mk ); ?>"><?php echo esc_html( $mlabel ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="velox-grid-2">
				<div class="velox-field">
					<label class="velox-field-label" for="velox-redir-priority">Priority</label>
					<input type="number" class="velox-input" id="velox-redir-priority" value="0">
					<span class="velox-hint">Higher numbers are checked first.</span>
				</div>
				<div class="velox-field">
					<label class="velox-field-label" for="velox-redir-category">Category</label>
					<input type="text" class="velox-input" id="velox-redir-category" placeholder="e.g. Blog, Shop">
				</div>
			</div>
			<div class="velox-field">
				<label class="velox-field-label" for="velox-redir-desc">Description</label>
				<input type="text" class="velox-input" id="velox-redir-desc" placeholder="Optional note for your team">
			</div>
			<div class="velox-redir-flags">
				<label class="velox-toggle-row">
					<div class="velox-toggle-meta"><span class="velox-toggle-label">Active</span></div>
					<span class="velox-switch"><input type="checkbox" id="velox-redir-active" checked><span class="velox-switch-track"></span></span>
				</label>
				<label class="velox-toggle-row">
					<div class="velox-toggle-meta"><span class="velox-toggle-label">Ignore case</span></div>
					<span class="velox-switch"><input type="checkbox" id="velox-redir-ic" checked><span class="velox-switch-track"></span></span>
				</label>
				<label class="velox-toggle-row">
					<div class="velox-toggle-meta"><span class="velox-toggle-label">Ignore query parameters</span></div>
					<span class="velox-switch"><input type="checkbox" id="velox-redir-iq" checked><span class="velox-switch-track"></span></span>
				</label>
				<label class="velox-toggle-row">
					<div class="velox-toggle-meta"><span class="velox-toggle-label">Ignore trailing slash</span></div>
					<span class="velox-switch"><input type="checkbox" id="velox-redir-is" checked><span class="velox-switch-track"></span></span>
				</label>
			</div>
		</div>
		<div class="velox-modal-foot">
			<button type="button" class="velox-btn velox-btn--ghost" data-redir-close>Cancel</button>
			<button type="button" class="velox-btn velox-btn--primary" id="velox-redir-save">Save redirect</button>
		</div>
	</div>
</div>
