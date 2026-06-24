<?php
/**
 * Snippets — list screen.
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
?>
<div class="velox-snip-head">
	<div>
		<h1 class="velox-snip-title">Snippets</h1>
		<p class="velox-snip-sub">Add PHP, CSS, JS or HTML that runs by location and priority. PHP snippets that error get switched off automatically.</p>
	</div>
	<div class="velox-snip-add">
		<button class="velox-btn velox-btn--primary" id="velox-snip-add-btn" type="button">+ Add snippet</button>
		<div class="velox-snip-add-menu" id="velox-snip-add-menu" hidden>
			<a href="<?php echo esc_url( Velox_Snippets::new_url( 'php' ) ); ?>"><span class="velox-snip-badge is-php">PHP</span> Functions / hooks</a>
			<a href="<?php echo esc_url( Velox_Snippets::new_url( 'css' ) ); ?>"><span class="velox-snip-badge is-css">CSS</span> Styles</a>
			<a href="<?php echo esc_url( Velox_Snippets::new_url( 'js' ) ); ?>"><span class="velox-snip-badge is-js">JS</span> Scripts</a>
			<a href="<?php echo esc_url( Velox_Snippets::new_url( 'html' ) ); ?>"><span class="velox-snip-badge is-html">HTML</span> Markup</a>
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

<div class="velox-panel velox-snip-list" id="velox-snip-list">
	<?php if ( empty( $snippets ) ) : ?>
		<p class="velox-hint" style="padding:18px;">No snippets here yet. Hit <strong>Add snippet</strong> to create one.</p>
	<?php else : ?>
		<?php foreach ( $snippets as $s ) :
			$is_trash = (int) $s['trashed'] === 1;
			$active   = (int) $s['active'] === 1;
			?>
			<div class="velox-snip-row" data-id="<?php echo esc_attr( $s['id'] ); ?>" data-active="<?php echo $active ? '1' : '0'; ?>">
				<span class="velox-snip-status<?php echo $active ? ' is-on' : ''; ?>" title="<?php echo $active ? 'Active' : 'Inactive'; ?>"></span>
				<span class="velox-snip-badge is-<?php echo esc_attr( $s['type'] ); ?>"><?php echo esc_html( strtoupper( $s['type'] ) ); ?></span>
				<a class="velox-snip-name" href="<?php echo esc_url( Velox_Snippets::edit_url( $s['id'] ) ); ?>">
					<?php echo esc_html( $s['name'] ); ?>
					<?php if ( ! empty( $s['description'] ) ) : ?>
						<small><?php echo esc_html( wp_trim_words( $s['description'], 14 ) ); ?></small>
					<?php endif; ?>
				</a>
				<span class="velox-snip-scope"><?php echo esc_html( $scope_labels[ $s['scope'] ] ?? $s['scope'] ); ?></span>
				<span class="velox-snip-prio" title="Priority"><?php echo (int) $s['priority']; ?></span>
				<span class="velox-snip-actions">
					<?php if ( $is_trash ) : ?>
						<button class="velox-btn velox-btn--ghost velox-snip-restore" type="button">Restore</button>
						<button class="velox-btn velox-btn--ghost velox-snip-delete" type="button">Delete forever</button>
					<?php else : ?>
						<button class="velox-btn velox-btn--ghost velox-snip-toggle" type="button"><?php echo $active ? 'Deactivate' : 'Activate'; ?></button>
						<a class="velox-btn velox-btn--ghost" href="<?php echo esc_url( Velox_Snippets::edit_url( $s['id'] ) ); ?>">Edit</a>
						<button class="velox-btn velox-btn--ghost velox-snip-clone" type="button">Clone</button>
						<button class="velox-btn velox-btn--ghost velox-snip-trash" type="button">Trash</button>
					<?php endif; ?>
				</span>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
