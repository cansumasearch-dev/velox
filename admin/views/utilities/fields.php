<?php
/**
 * Custom fields (ACF-style) — list of field groups + the group editor.
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
// Bootstrap Icons webfont — powers the options-page icon picker grid + preview.
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<?php

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
	$paramchoices = Velox_Fields::location_choices();
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
	<script type="application/json" id="vfg-paramchoices"><?php echo wp_json_encode( $paramchoices ); ?></script>

<?php else :
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'groups'; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! in_array( $tab, array( 'groups', 'post-types', 'taxonomies', 'options' ), true ) ) { $tab = 'groups'; }
	$groups   = Velox_Fields::all();
	$cpts     = Velox_Post_Types::all_post_types();
	$taxes    = Velox_Post_Types::all_taxonomies();
	$optpages = Velox_Fields::all_option_pages();
	$supports = Velox_Post_Types::supports_options();
	$sel_pts  = Velox_Post_Types::selectable_post_types();
	?>
	<div class="velox-section-head">
		<h1 class="velox-h2">Custom fields</h1>
		<p class="velox-sub">Create custom post types and taxonomies, then attach field groups to them — all without code.</p>
	</div>

	<div class="vfx-tabs">
		<a class="vfx-tab<?php echo 'groups' === $tab ? ' is-on' : ''; ?>" href="<?php echo esc_url( $base . '&tab=groups' ); ?>">Field groups <span class="vfx-tab-n"><?php echo count( $groups ); ?></span></a>
		<a class="vfx-tab<?php echo 'post-types' === $tab ? ' is-on' : ''; ?>" href="<?php echo esc_url( $base . '&tab=post-types' ); ?>">Post types <span class="vfx-tab-n"><?php echo count( $cpts ); ?></span></a>
		<a class="vfx-tab<?php echo 'taxonomies' === $tab ? ' is-on' : ''; ?>" href="<?php echo esc_url( $base . '&tab=taxonomies' ); ?>">Taxonomies <span class="vfx-tab-n"><?php echo count( $taxes ); ?></span></a>
		<a class="vfx-tab<?php echo 'options' === $tab ? ' is-on' : ''; ?>" href="<?php echo esc_url( $base . '&tab=options' ); ?>">Options pages <span class="vfx-tab-n"><?php echo count( $optpages ); ?></span></a>
	</div>

	<?php if ( 'post-types' === $tab ) : ?>
		<div class="vfx-head-row">
			<p class="velox-hint" style="margin:0;">Custom post types appear in the admin sidebar next to Posts and Pages.</p>
			<button class="velox-btn velox-btn--primary" id="vpt-add"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="margin-right:6px;"><path d="M12 5v14M5 12h14"/></svg> Add post type</button>
		</div>
		<div class="velox-panel velox-panel--flush vfx-list" id="vpt-list">
			<?php if ( empty( $cpts ) ) : ?>
				<p class="velox-hint" style="padding:26px;">No custom post types yet. Add one and it shows up in the sidebar straight away.</p>
			<?php else : foreach ( $cpts as $pt ) : ?>
				<div class="vfx-row" data-slug="<?php echo esc_attr( $pt['slug'] ); ?>" data-json="<?php echo esc_attr( wp_json_encode( $pt ) ); ?>">
					<button type="button" class="vfx-row-main vpt-edit">
						<span class="vfx-row-title"><?php echo esc_html( $pt['plural'] ?: $pt['slug'] ); ?></span>
						<span class="vfx-row-meta"><code><?php echo esc_html( $pt['slug'] ); ?></code> · <?php echo ! empty( $pt['hierarchical'] ) ? 'hierarchical' : 'flat'; ?><?php echo ! empty( $pt['has_archive'] ) ? ' · archive' : ''; ?></span>
					</button>
					<span class="vfx-row-status <?php echo ! empty( $pt['active'] ) ? 'is-active' : ''; ?>"><?php echo ! empty( $pt['active'] ) ? 'Active' : 'Inactive'; ?></span>
					<button class="vfx-row-del vpt-del" data-slug="<?php echo esc_attr( $pt['slug'] ); ?>" title="Delete"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-13M9 7V4h6v3"/></svg></button>
				</div>
			<?php endforeach; endif; ?>
		</div>

		<div class="velox-panel vfx-editor" id="vpt-editor" hidden>
			<h3 class="velox-panel-title" id="vpt-editor-title">Add post type</h3>
			<div class="velox-grid-2">
				<div class="velox-field"><span class="velox-field-label">Singular label</span><input type="text" class="velox-input" id="vpt-singular" placeholder="Movie"></div>
				<div class="velox-field"><span class="velox-field-label">Plural label</span><input type="text" class="velox-input" id="vpt-plural" placeholder="Movies"></div>
			</div>
			<div class="velox-field"><span class="velox-field-label">Slug <em>(lowercase, max 20 chars — this is the post type key)</em></span><input type="text" class="velox-input" id="vpt-slug" placeholder="movie" maxlength="20"></div>
			<div class="velox-grid-2">
				<div class="velox-field"><span class="velox-field-label">Menu icon <em>(dashicons-… or image URL)</em></span><input type="text" class="velox-input" id="vpt-icon" placeholder="dashicons-video-alt2"></div>
				<div class="velox-field"><span class="velox-field-label">Menu position</span><input type="number" class="velox-input velox-input--sm" id="vpt-menupos" value="25"></div>
			</div>
			<div class="velox-field">
				<span class="velox-field-label">Supports</span>
				<div class="vfx-checks" id="vpt-supports">
					<?php foreach ( $supports as $sk => $sl ) : ?>
						<label class="vfx-check"><input type="checkbox" value="<?php echo esc_attr( $sk ); ?>"<?php echo in_array( $sk, array( 'title', 'editor', 'thumbnail', 'custom-fields' ), true ) ? ' checked' : ''; ?>> <span><?php echo esc_html( $sl ); ?></span></label>
					<?php endforeach; ?>
				</div>
			</div>
			<?php if ( ! empty( $taxes ) ) : ?>
			<div class="velox-field">
				<span class="velox-field-label">Attach taxonomies</span>
				<div class="vfx-checks" id="vpt-taxonomies">
					<?php foreach ( $taxes as $tx ) : ?>
						<label class="vfx-check"><input type="checkbox" value="<?php echo esc_attr( $tx['slug'] ); ?>"> <span><?php echo esc_html( $tx['plural'] ?: $tx['slug'] ); ?></span></label>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>
			<div class="velox-grid-2 vfx-toggles">
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label">Active</span></div><span class="velox-switch"><input type="checkbox" id="vpt-active" checked><span class="velox-switch-track"></span></span></label>
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label">Public</span></div><span class="velox-switch"><input type="checkbox" id="vpt-public" checked><span class="velox-switch-track"></span></span></label>
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label">Show in sidebar menu</span></div><span class="velox-switch"><input type="checkbox" id="vpt-menu" checked><span class="velox-switch-track"></span></span></label>
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label">Show in REST (Gutenberg)</span></div><span class="velox-switch"><input type="checkbox" id="vpt-rest" checked><span class="velox-switch-track"></span></span></label>
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label">Has archive page</span></div><span class="velox-switch"><input type="checkbox" id="vpt-archive" checked><span class="velox-switch-track"></span></span></label>
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label">Hierarchical (page-like)</span></div><span class="velox-switch"><input type="checkbox" id="vpt-hier"><span class="velox-switch-track"></span></span></label>
			</div>
			<div class="vfx-editor-actions">
				<button class="velox-btn velox-btn--ghost" id="vpt-cancel" type="button">Cancel</button>
				<button class="velox-btn velox-btn--primary" id="vpt-save" type="button">Save post type</button>
			</div>
		</div>

	<?php elseif ( 'taxonomies' === $tab ) : ?>
		<div class="vfx-head-row">
			<p class="velox-hint" style="margin:0;">Taxonomies group your content — like Categories (hierarchical) or Tags (flat).</p>
			<button class="velox-btn velox-btn--primary" id="vtx-add"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="margin-right:6px;"><path d="M12 5v14M5 12h14"/></svg> Add taxonomy</button>
		</div>
		<div class="velox-panel velox-panel--flush vfx-list" id="vtx-list">
			<?php if ( empty( $taxes ) ) : ?>
				<p class="velox-hint" style="padding:26px;">No custom taxonomies yet.</p>
			<?php else : foreach ( $taxes as $tx ) : ?>
				<div class="vfx-row" data-slug="<?php echo esc_attr( $tx['slug'] ); ?>" data-json="<?php echo esc_attr( wp_json_encode( $tx ) ); ?>">
					<button type="button" class="vfx-row-main vtx-edit">
						<span class="vfx-row-title"><?php echo esc_html( $tx['plural'] ?: $tx['slug'] ); ?></span>
						<span class="vfx-row-meta"><code><?php echo esc_html( $tx['slug'] ); ?></code> · <?php echo ! empty( $tx['hierarchical'] ) ? 'category-like' : 'tag-like'; ?> · <?php echo esc_html( implode( ', ', $tx['object_types'] ) ); ?></span>
					</button>
					<span class="vfx-row-status <?php echo ! empty( $tx['active'] ) ? 'is-active' : ''; ?>"><?php echo ! empty( $tx['active'] ) ? 'Active' : 'Inactive'; ?></span>
					<button class="vfx-row-del vtx-del" data-slug="<?php echo esc_attr( $tx['slug'] ); ?>" title="Delete"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-13M9 7V4h6v3"/></svg></button>
				</div>
			<?php endforeach; endif; ?>
		</div>

		<div class="velox-panel vfx-editor" id="vtx-editor" hidden>
			<h3 class="velox-panel-title" id="vtx-editor-title">Add taxonomy</h3>
			<div class="velox-grid-2">
				<div class="velox-field"><span class="velox-field-label">Singular label</span><input type="text" class="velox-input" id="vtx-singular" placeholder="Genre"></div>
				<div class="velox-field"><span class="velox-field-label">Plural label</span><input type="text" class="velox-input" id="vtx-plural" placeholder="Genres"></div>
			</div>
			<div class="velox-field"><span class="velox-field-label">Slug <em>(lowercase, max 32 chars)</em></span><input type="text" class="velox-input" id="vtx-slug" placeholder="genre" maxlength="32"></div>
			<div class="velox-field">
				<span class="velox-field-label">Attach to post types</span>
				<div class="vfx-checks" id="vtx-objects">
					<?php foreach ( $sel_pts as $ptslug => $ptlabel ) : ?>
						<label class="vfx-check"><input type="checkbox" value="<?php echo esc_attr( $ptslug ); ?>"<?php echo 'post' === $ptslug ? ' checked' : ''; ?>> <span><?php echo esc_html( $ptlabel ); ?></span></label>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="velox-grid-2 vfx-toggles">
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label">Active</span></div><span class="velox-switch"><input type="checkbox" id="vtx-active" checked><span class="velox-switch-track"></span></span></label>
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label">Public</span></div><span class="velox-switch"><input type="checkbox" id="vtx-public" checked><span class="velox-switch-track"></span></span></label>
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label">Hierarchical (category-like)</span></div><span class="velox-switch"><input type="checkbox" id="vtx-hier" checked><span class="velox-switch-track"></span></span></label>
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label">Show in REST (Gutenberg)</span></div><span class="velox-switch"><input type="checkbox" id="vtx-rest" checked><span class="velox-switch-track"></span></span></label>
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label">Show admin column</span></div><span class="velox-switch"><input type="checkbox" id="vtx-col" checked><span class="velox-switch-track"></span></span></label>
			</div>
			<div class="vfx-editor-actions">
				<button class="velox-btn velox-btn--ghost" id="vtx-cancel" type="button">Cancel</button>
				<button class="velox-btn velox-btn--primary" id="vtx-save" type="button">Save taxonomy</button>
			</div>
		</div>

	<?php elseif ( 'options' === $tab ) : ?>
		<div class="vfx-head-row">
			<p class="velox-hint" style="margin:0;">Options pages are admin screens for global settings (read with <code>Velox_Fields::get_field('name','option')</code>). Target one from a field group's location rule.</p>
			<button class="velox-btn velox-btn--primary" id="vop-add"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="margin-right:6px;"><path d="M12 5v14M5 12h14"/></svg> Add options page</button>
		</div>
		<div class="velox-panel velox-panel--flush vfx-list" id="vop-list">
			<?php if ( empty( $optpages ) ) : ?>
				<p class="velox-hint" style="padding:26px;">No options pages yet.</p>
			<?php else : foreach ( $optpages as $op ) : ?>
				<div class="vfx-row" data-slug="<?php echo esc_attr( $op['slug'] ); ?>" data-json="<?php echo esc_attr( wp_json_encode( $op ) ); ?>">
					<button type="button" class="vfx-row-main vop-edit">
						<span class="vfx-row-title"><?php echo esc_html( $op['menu_title'] ?: $op['title'] ); ?></span>
						<span class="vfx-row-meta"><code><?php echo esc_html( $op['slug'] ); ?></code> · <?php echo '' === $op['parent'] ? 'top-level menu' : esc_html( 'under ' . $op['parent'] ); ?></span>
					</button>
					<?php $op_active = ! isset( $op['active'] ) || ! empty( $op['active'] ); ?>
					<span class="vfx-row-status <?php echo $op_active ? 'is-active' : ''; ?>"><?php echo $op_active ? 'Active' : 'Inactive'; ?></span>
					<button class="vfx-row-del vop-del" data-slug="<?php echo esc_attr( $op['slug'] ); ?>" title="Delete"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-13M9 7V4h6v3"/></svg></button>
				</div>
			<?php endforeach; endif; ?>
		</div>

		<div class="velox-panel vfx-editor" id="vop-editor" hidden>
			<h3 class="velox-panel-title" id="vop-editor-title">Add options page</h3>
			<div class="velox-grid-2">
				<div class="velox-field"><span class="velox-field-label">Page title</span><input type="text" class="velox-input" id="vop-title" placeholder="Theme Settings"></div>
				<div class="velox-field"><span class="velox-field-label">Menu title <em>(optional)</em></span><input type="text" class="velox-input" id="vop-menu" placeholder="Settings"></div>
			</div>
			<div class="velox-grid-2">
				<div class="velox-field"><span class="velox-field-label">Slug <em>(lowercase, max 32)</em></span><input type="text" class="velox-input" id="vop-slug" placeholder="theme-settings" maxlength="32"></div>
				<div class="velox-field"><span class="velox-field-label">Parent menu</span>
					<select class="velox-select" id="vop-parent">
						<option value="">Top-level menu</option>
						<option value="velox">Under Velox</option>
						<option value="options-general.php">Under Settings</option>
						<option value="themes.php">Under Appearance</option>
						<option value="tools.php">Under Tools</option>
						<option value="edit.php">Under Posts</option>
						<option value="upload.php">Under Media</option>
					</select>
				</div>
			</div>
			<div class="velox-grid-2">
				<div class="velox-field"><span class="velox-field-label">Menu icon <em>(top-level only)</em></span>
					<div class="vop-icon-row">
						<button type="button" class="velox-btn velox-btn--ghost" id="vop-icon-pick"><span class="vop-icon-prev" id="vop-icon-prev" aria-hidden="true"></span> Choose icon</button>
						<input type="text" class="velox-input velox-input--sm" id="vop-icon" placeholder="bi:gift / dashicons-… / URL">
					</div>
					<span class="velox-hint">Pick a Bootstrap icon, or type a <code>dashicons-…</code> class or image URL.</span>
				</div>
				<div class="velox-field"><span class="velox-field-label">Menu position</span><input type="number" class="velox-input" id="vop-position" value="80"></div>
			</div>
			<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label">Active</span><span class="velox-toggle-desc">Turn off to hide this page from the admin menu without deleting it.</span></div><span class="velox-switch"><input type="checkbox" id="vop-active" checked><span class="velox-switch-track"></span></span></label>
			<div class="vfx-editor-actions">
				<button class="velox-btn velox-btn--ghost" id="vop-cancel" type="button">Cancel</button>
				<button class="velox-btn velox-btn--primary" id="vop-save" type="button">Save options page</button>
			</div>
		</div>

	<?php else : ?>
	<div class="vfx-head-row">
		<p class="velox-hint" style="margin:0;">Field groups attach custom fields to your content based on location rules.</p>
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
	<?php endif; // tab switch ?>

	<div class="velox-panel velox-mail-disable" style="margin-top:16px;">
		<label class="velox-inline-toggle">
			<span><strong>Custom fields is on</strong> <span class="velox-hint" style="display:inline;">— switch off to disable.</span></span>
			<span class="velox-switch"><input type="checkbox" data-setting="util_fields" id="velox-fields-toggle" checked><span class="velox-switch-track"></span></span>
		</label>
	</div>
<?php endif; ?>

<div class="velox-modal" id="vop-icon-modal" hidden>
	<div class="velox-modal-box velox-modal-box--lg">
		<div class="velox-modal-head">
			<span class="velox-modal-title">Choose a menu icon</span>
			<button type="button" class="velox-modal-x" id="vop-icon-close" aria-label="Close">&times;</button>
		</div>
		<div class="velox-modal-body">
			<input type="text" class="velox-input" id="vop-icon-search" placeholder="Search icons (e.g. gift, gear, cart)…" autocomplete="off" style="margin-bottom:12px;">
			<div class="vop-icon-grid" id="vop-icon-grid"></div>
		</div>
	</div>
</div>
