<?php
/**
 * Snippets — list screen (redesigned, unified Apple system).
 *
 * @var array  $snippets
 * @var array  $counts
 * @var string $filter
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$scope_labels = array(
	'everywhere' => 'Everywhere',
	'admin'      => 'Admin only',
	'front'      => 'Front-end',
	'once'       => 'Run once',
);
$tabs = array(
	'all'      => 'All',
	'active'   => 'Active',
	'inactive' => 'Inactive',
	'trash'    => 'Trash',
);
$type_meta = array(
	'php'  => array( 'Functions & hooks', 'is-php' ),
	'css'  => array( 'Styles',            'is-css' ),
	'js'   => array( 'Scripts',           'is-js' ),
	'html' => array( 'Markup',            'is-html' ),
);
$safe_mode = Velox_Snippets::safe_mode();
?>

<div class="velox-page-head velox-snip-pagehead">
	<div>
		<h1 class="velox-h2">Code Snippets</h1>
		<p class="velox-sub">Add PHP, CSS, JS or HTML that runs by location and priority. A bad PHP snippet auto-disables, and Safe Mode keeps a crash from ever locking you out.</p>
	</div>
	<button class="velox-btn velox-btn--primary" id="velox-snip-add-btn" type="button">
		<svg class="velox-ic" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
		Add snippet
	</button>
</div>

<?php if ( $safe_mode ) : ?>
	<div class="velox-snip-safe">
		<div class="velox-snip-safe-ic" aria-hidden="true">
			<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
		</div>
		<div class="velox-snip-safe-body">
			<strong>Safe Mode is active — PHP snippets are not running.</strong>
			<p>Velox skipped your PHP snippets because one crashed the site (or you asked for Safe Mode). CSS, JS and HTML snippets are unaffected. Fix or switch off the offending snippet, then clear Safe Mode.</p>
			<div class="velox-snip-safe-actions">
				<button class="velox-btn velox-btn--ghost" id="velox-snip-disable-all" type="button">Switch off all PHP snippets</button>
				<button class="velox-btn velox-btn--primary" id="velox-snip-clear-panic" type="button">Clear Safe Mode</button>
			</div>
		</div>
	</div>
<?php endif; ?>

<!-- Type picker modal -->
<div class="velox-snip-modal" id="velox-snip-modal" hidden>
	<div class="velox-snip-modal-backdrop" data-close></div>
	<div class="velox-snip-modal-card" role="dialog" aria-modal="true" aria-label="Choose snippet type">
		<div class="velox-snip-modal-head">
			<h2>What kind of snippet?</h2>
			<button class="velox-snip-modal-x" type="button" data-close aria-label="Close">&times;</button>
		</div>
		<div class="velox-snip-types">
			<a class="velox-snip-type" href="<?php echo esc_url( Velox_Snippets::new_url( 'php' ) ); ?>">
				<span class="velox-snip-badge is-php">PHP</span>
				<span class="velox-snip-type-t">Functions &amp; hooks</span>
				<span class="velox-snip-type-d">Run PHP early — add_action, add_filter, custom logic. Lint-checked and crash-guarded.</span>
			</a>
			<a class="velox-snip-type" href="<?php echo esc_url( Velox_Snippets::new_url( 'css' ) ); ?>">
				<span class="velox-snip-badge is-css">CSS</span>
				<span class="velox-snip-type-t">Styles</span>
				<span class="velox-snip-type-d">Inject CSS into the page head — front-end, admin, or both.</span>
			</a>
			<a class="velox-snip-type" href="<?php echo esc_url( Velox_Snippets::new_url( 'js' ) ); ?>">
				<span class="velox-snip-badge is-js">JS</span>
				<span class="velox-snip-type-t">Scripts</span>
				<span class="velox-snip-type-d">Print JavaScript before &lt;/body&gt; for the chosen location.</span>
			</a>
			<a class="velox-snip-type" href="<?php echo esc_url( Velox_Snippets::new_url( 'html' ) ); ?>">
				<span class="velox-snip-badge is-html">HTML</span>
				<span class="velox-snip-type-t">Markup</span>
				<span class="velox-snip-type-d">Footer HTML, also embeddable with [velox_snippet id="…"].</span>
			</a>
		</div>
	</div>
</div>

<div class="velox-snip-tabs">
	<?php foreach ( $tabs as $key => $label ) : ?>
		<a class="velox-snip-tab<?php echo $filter === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url( Velox_Snippets::list_url( $key ) ); ?>">
			<?php echo esc_html( $label ); ?> <span class="velox-snip-count"><?php echo (int) $counts[ $key ]; ?></span>
		</a>
	<?php endforeach; ?>
</div>

<div class="velox-panel velox-panel--flush velox-snip-list" id="velox-snip-list">
	<?php if ( empty( $snippets ) ) : ?>
		<div class="velox-snip-empty">
			<p>No snippets here yet.</p>
			<button class="velox-btn velox-btn--primary" id="velox-snip-add-btn-2" type="button">Add your first snippet</button>
		</div>
	<?php else : ?>
		<?php foreach ( $snippets as $s ) :
			$is_trash = (int) $s['trashed'] === 1;
			$active   = (int) $s['active'] === 1;
			$tmeta    = $type_meta[ $s['type'] ] ?? array( strtoupper( $s['type'] ), '' );
			?>
			<div class="velox-snip-row" data-id="<?php echo esc_attr( $s['id'] ); ?>" data-active="<?php echo $active ? '1' : '0'; ?>">
				<span class="velox-snip-status<?php echo $active ? ' is-on' : ''; ?>" title="<?php echo $active ? 'Active' : 'Inactive'; ?>"></span>
				<span class="velox-snip-badge is-<?php echo esc_attr( $s['type'] ); ?>"><?php echo esc_html( strtoupper( $s['type'] ) ); ?></span>
				<a class="velox-snip-name" href="<?php echo esc_url( Velox_Snippets::edit_url( $s['id'] ) ); ?>">
					<span class="velox-snip-name-t"><?php echo esc_html( $s['name'] ); ?></span>
					<span class="velox-snip-meta">
						<?php echo esc_html( $scope_labels[ $s['scope'] ] ?? $s['scope'] ); ?>
						<span class="velox-snip-dot">·</span> priority <?php echo (int) $s['priority']; ?>
						<?php if ( ! empty( $s['description'] ) ) : ?>
							<span class="velox-snip-dot">·</span> <?php echo esc_html( wp_trim_words( $s['description'], 10 ) ); ?>
						<?php endif; ?>
					</span>
				</a>
				<span class="velox-snip-actions">
					<?php if ( $is_trash ) : ?>
						<button class="velox-btn velox-btn--ghost velox-btn--sm velox-snip-restore" type="button">Restore</button>
						<button class="velox-btn velox-btn--ghost velox-btn--sm velox-snip-delete" type="button">Delete forever</button>
					<?php else : ?>
						<button class="velox-snip-toggle-sw<?php echo $active ? ' is-on' : ''; ?> velox-snip-toggle" type="button" role="switch" aria-checked="<?php echo $active ? 'true' : 'false'; ?>" title="<?php echo $active ? 'Deactivate' : 'Activate'; ?>"><span></span></button>
						<div class="velox-snip-menu">
							<button class="velox-snip-menu-btn" type="button" aria-label="More actions" aria-haspopup="true">
								<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>
							</button>
							<div class="velox-snip-menu-pop" hidden>
								<a href="<?php echo esc_url( Velox_Snippets::edit_url( $s['id'] ) ); ?>">Edit</a>
								<button type="button" class="velox-snip-clone">Duplicate</button>
								<a href="<?php echo esc_url( Velox_Snippets::export_url( $s['id'] ) ); ?>">Export as plugin</a>
								<button type="button" class="velox-snip-trash velox-snip-menu-danger">Move to trash</button>
							</div>
						</div>
					<?php endif; ?>
				</span>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
