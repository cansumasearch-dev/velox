<?php
/**
 * Custom fields (ACF-style) — list of field groups + the group editor.
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$enabled = Velox_Settings::get( 'util_fields', false );
$base    = admin_url( 'admin.php?page=velox-utilities&tool=fields' );
$edit    = isset( $_GET['group'] ) ? sanitize_text_field( wp_unslash( $_GET['group'] ) ) : '';
?>

<?php if ( ! $enabled ) : ?>
	<div class="velox-section-head">
		<h1 class="velox-h2">Custom fields</h1>
		<p class="velox-sub">Add custom fields to posts, pages and any post type — ACF-style field groups with location rules and a <code>get_field()</code> API.</p>
	</div>
	<div class="velox-panel velox-mail-disable">
		<label class="velox-inline-toggle">
			<span><strong>Enable Custom fields</strong> <span class="velox-hint" style="display:inline;">— switch on to create field groups.</span></span>
			<span class="velox-switch"><input type="checkbox" data-setting="util_fields" id="velox-fields-toggle"><span class="velox-switch-track"></span></span>
		</label>
		<p class="velox-hint" style="margin-top:14px;">Once on, build field groups here and they'll appear on the matching post edit screens. Read values on the front end with <code>Velox_Fields::get_field('name')</code> or the <code>{field:name}</code> merge tag.</p>
	</div>

<?php elseif ( '' !== $edit ) :
	$group = ( 'new' === $edit ) ? Velox_Fields::blank() : Velox_Fields::get( (int) $edit );
	if ( ! $group ) { $group = Velox_Fields::blank(); }
	$types  = Velox_Fields::types();
	$params = Velox_Fields::location_params();
	$pres   = $group['presentation'];
	?>
	<div class="vfg" id="vfg-editor">
		<div class="vfg-bar">
			<a class="vfg-back" href="<?php echo esc_url( $base ); ?>" title="All field groups">
				<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
			</a>
			<div class="vfg-titlewrap">
				<input type="text" id="vfg-title" class="vfg-title" value="<?php echo esc_attr( $group['title'] ); ?>" placeholder="Field group name">
				<span class="vfg-sub" id="vfg-sub"></span>
			</div>
			<label class="vfg-active">
				<span class="velox-switch"><input type="checkbox" id="vfg-active" <?php checked( ! empty( $group['active'] ) ); ?>><span class="velox-switch-track"></span></span>
				<span id="vfg-active-label"><?php echo ! empty( $group['active'] ) ? 'Active' : 'Inactive'; ?></span>
			</label>
			<button class="velox-btn velox-btn--primary" id="vfg-save">Save group</button>
		</div>

		<div class="vfg-grid">
			<div class="vfg-main">
				<div class="vfg-fields-head"><h3>Fields</h3><span class="velox-hint">Drag the handle to reorder</span></div>
				<div id="vfg-fields"></div>
				<button class="vfg-addfield" id="vfg-addfield">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg> Add field
				</button>
			</div>

			<aside class="vfg-side">
				<div class="vfg-side-panel">
					<div class="vfg-side-head">
						<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
						Location rules
					</div>
					<div class="vfg-side-body">
						<p class="vfg-loc-help">Show this group where <strong>all</strong> rules in a box match, or <strong>any</strong> box matches.</p>
						<div id="vfg-location"></div>
						<button class="vfg-addgroup" id="vfg-addgroup">+ Add rule group</button>
					</div>
				</div>

				<div class="vfg-side-panel">
					<div class="vfg-side-head">
						<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9z"/></svg>
						Presentation
					</div>
					<div class="vfg-side-body">
						<div class="vfg-pres-row">
							<span class="vfg-pres-label">Label placement</span>
							<div class="vfg-seg" data-seg="label_placement">
								<button type="button" data-v="top" class="<?php echo 'left' !== $pres['label_placement'] ? 'is-on' : ''; ?>">Top</button>
								<button type="button" data-v="left" class="<?php echo 'left' === $pres['label_placement'] ? 'is-on' : ''; ?>">Left</button>
							</div>
						</div>
						<div class="vfg-pres-row">
							<span class="vfg-pres-label">Position</span>
							<div class="vfg-seg" data-seg="position">
								<button type="button" data-v="normal" class="<?php echo 'side' !== $pres['position'] ? 'is-on' : ''; ?>">Normal</button>
								<button type="button" data-v="side" class="<?php echo 'side' === $pres['position'] ? 'is-on' : ''; ?>">Side</button>
							</div>
						</div>
						<div class="vfg-pres-row">
							<span class="vfg-pres-label">Order number</span>
							<input type="number" id="vfg-order" class="velox-input" value="<?php echo (int) $pres['order']; ?>" style="width:70px;text-align:center;">
						</div>
					</div>
				</div>
			</aside>
		</div>
	</div>
	<script type="application/json" id="vfg-data"><?php echo wp_json_encode( $group ); ?></script>
	<script type="application/json" id="vfg-types"><?php echo wp_json_encode( $types ); ?></script>
	<script type="application/json" id="vfg-params"><?php echo wp_json_encode( $params ); ?></script>

<?php else :
	$groups = Velox_Fields::all();
	?>
	<div class="velox-section-head" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;">
		<div>
			<h1 class="velox-h2">Custom fields</h1>
			<p class="velox-sub">Field groups attach custom fields to your content based on location rules.</p>
		</div>
		<a class="velox-btn velox-btn--primary" href="<?php echo esc_url( $base . '&group=new' ); ?>">
			<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="margin-right:6px;"><path d="M12 5v14M5 12h14"/></svg> New field group
		</a>
	</div>

	<?php if ( empty( $groups ) ) : ?>
		<div class="velox-panel" style="text-align:center;padding:48px 20px;">
			<p style="font-size:15px;font-weight:600;margin:0 0 4px;">No field groups yet</p>
			<p class="velox-hint" style="margin:0 0 16px;">Create your first field group to start adding custom fields.</p>
			<a class="velox-btn velox-btn--primary" href="<?php echo esc_url( $base . '&group=new' ); ?>">Create field group</a>
		</div>
	<?php else : ?>
		<div class="velox-panel velox-panel--flush vfg-list">
			<?php foreach ( $groups as $g ) :
				$nfields = count( $g['fields'] ?? array() );
				$loc = '';
				if ( ! empty( $g['location'][0][0] ) ) {
					$r = $g['location'][0][0];
					$loc = ucfirst( str_replace( '_', ' ', $r['param'] ) ) . ' ' . ( $r['operator'] === 'is_not' ? 'is not' : 'is' ) . ' ' . $r['value'];
				}
				?>
				<div class="vfg-list-row">
					<a class="vfg-list-main" href="<?php echo esc_url( $base . '&group=' . (int) $g['id'] ); ?>">
						<span class="vfg-list-title"><?php echo esc_html( $g['title'] ); ?></span>
						<span class="vfg-list-meta"><?php echo (int) $nfields; ?> field<?php echo 1 === $nfields ? '' : 's'; ?><?php echo $loc ? ' · ' . esc_html( $loc ) : ''; ?></span>
					</a>
					<span class="vfg-list-status <?php echo ! empty( $g['active'] ) ? 'is-active' : ''; ?>"><?php echo ! empty( $g['active'] ) ? 'Active' : 'Inactive'; ?></span>
					<button class="vfg-list-del" data-id="<?php echo (int) $g['id']; ?>" data-title="<?php echo esc_attr( $g['title'] ); ?>" title="Delete">
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-13M9 7V4h6v3"/></svg>
					</button>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="velox-panel velox-mail-disable" style="margin-top:16px;">
		<label class="velox-inline-toggle">
			<span><strong>Custom fields is on</strong> <span class="velox-hint" style="display:inline;">— switch off to disable.</span></span>
			<span class="velox-switch"><input type="checkbox" data-setting="util_fields" id="velox-fields-toggle" checked><span class="velox-switch-track"></span></span>
		</label>
	</div>
<?php endif; ?>
