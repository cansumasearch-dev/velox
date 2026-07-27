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
		<h1 class="velox-h2"><?php esc_html_e('Custom fields', 'velox'); ?></h1>
		<p class="velox-sub"><?php printf( esc_html__( 'Add custom fields to posts, pages and any post type — ACF-style field groups with location rules and a %s API.', 'velox' ), '<code>' . esc_html__( 'get_field()', 'velox' ) . '</code>' ); ?></p>
	</div>
	<div class="velox-panel velox-mail-disable">
		<label class="velox-inline-toggle">
			<span><strong><?php esc_html_e('Enable Custom fields', 'velox'); ?></strong> <span class="velox-hint" style="display:inline;"><?php esc_html_e('— switch on to create field groups.', 'velox'); ?></span></span>
			<span class="velox-switch"><input type="checkbox" data-setting="util_fields" id="velox-fields-toggle"><span class="velox-switch-track"></span></span>
		</label>
		<p class="velox-hint" style="margin-top:14px;"><?php printf( esc_html__( 'Once on, build field groups here and they\'ll appear on the matching post edit screens. Read values on the front end with %1$s or the %2$s merge tag.', 'velox' ), '<code>' . esc_html__( "Velox_Fields::get_field('name')", 'velox' ) . '</code>', '<code>{field:name}</code>' ); ?></p>
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
			<a class="vfg-back" href="<?php echo esc_url( $base ); ?>" title="<?php esc_attr_e('All field groups', 'velox'); ?>">
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
			<button class="velox-btn velox-btn--primary" id="vfg-save"><?php esc_html_e('Save group', 'velox'); ?></button>
		</div>

		<div class="vfg-grid">
			<div class="vfg-main">
				<div class="vfg-fields-head"><h3><?php esc_html_e('Fields', 'velox'); ?></h3><span class="velox-hint"><?php esc_html_e('Drag the handle to reorder', 'velox'); ?></span></div>
				<div id="vfg-fields"></div>
				<button class="vfg-addfield" id="vfg-addfield">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg> <?php esc_html_e('Add field', 'velox'); ?>
				</button>
			</div>

			<aside class="vfg-side">
				<div class="vfg-side-panel">
					<div class="vfg-side-head">
						<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
						<?php esc_html_e('Location rules', 'velox'); ?>
					</div>
					<div class="vfg-side-body">
						<p class="vfg-loc-help"><?php printf( esc_html__( 'Show this group where %1$s rules in a box match, or %2$s box matches.', 'velox' ), '<strong>' . esc_html__( 'all', 'velox' ) . '</strong>', '<strong>' . esc_html__( 'any', 'velox' ) . '</strong>' ); ?></p>
						<div id="vfg-location"></div>
						<button class="vfg-addgroup" id="vfg-addgroup"><?php esc_html_e('+ Add rule group', 'velox'); ?></button>
					</div>
				</div>

				<div class="vfg-side-panel">
					<div class="vfg-side-head">
						<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9z"/></svg>
						<?php esc_html_e('Presentation', 'velox'); ?>
					</div>
					<div class="vfg-side-body">
						<div class="vfg-pres-row">
							<span class="vfg-pres-label"><?php esc_html_e('Label placement', 'velox'); ?></span>
							<div class="vfg-seg" data-seg="label_placement">
								<button type="button" data-v="top" class="<?php echo 'left' !== $pres['label_placement'] ? 'is-on' : ''; ?>"><?php esc_html_e('Top', 'velox'); ?></button>
								<button type="button" data-v="left" class="<?php echo 'left' === $pres['label_placement'] ? 'is-on' : ''; ?>"><?php esc_html_e('Left', 'velox'); ?></button>
							</div>
						</div>
						<div class="vfg-pres-row">
							<span class="vfg-pres-label"><?php esc_html_e('Position', 'velox'); ?></span>
							<div class="vfg-seg" data-seg="position">
								<button type="button" data-v="normal" class="<?php echo 'side' !== $pres['position'] ? 'is-on' : ''; ?>"><?php esc_html_e('Normal', 'velox'); ?></button>
								<button type="button" data-v="side" class="<?php echo 'side' === $pres['position'] ? 'is-on' : ''; ?>"><?php esc_html_e('Side', 'velox'); ?></button>
							</div>
						</div>
						<div class="vfg-pres-row">
							<span class="vfg-pres-label"><?php esc_html_e('Order number', 'velox'); ?></span>
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
		<h1 class="velox-h2"><?php esc_html_e('Custom fields', 'velox'); ?></h1>
		<p class="velox-sub"><?php esc_html_e('Create custom post types and taxonomies, then attach field groups to them — all without code.', 'velox'); ?></p>
	</div>

	<div class="vfx-tabs">
		<a class="vfx-tab<?php echo 'groups' === $tab ? ' is-on' : ''; ?>" href="<?php echo esc_url( $base . '&tab=groups' ); ?>"><?php esc_html_e('Field groups', 'velox'); ?> <span class="vfx-tab-n"><?php echo count( $groups ); ?></span></a>
		<a class="vfx-tab<?php echo 'post-types' === $tab ? ' is-on' : ''; ?>" href="<?php echo esc_url( $base . '&tab=post-types' ); ?>"><?php esc_html_e('Post types', 'velox'); ?> <span class="vfx-tab-n"><?php echo count( $cpts ); ?></span></a>
		<a class="vfx-tab<?php echo 'taxonomies' === $tab ? ' is-on' : ''; ?>" href="<?php echo esc_url( $base . '&tab=taxonomies' ); ?>"><?php esc_html_e('Taxonomies', 'velox'); ?> <span class="vfx-tab-n"><?php echo count( $taxes ); ?></span></a>
		<a class="vfx-tab<?php echo 'options' === $tab ? ' is-on' : ''; ?>" href="<?php echo esc_url( $base . '&tab=options' ); ?>"><?php esc_html_e('Options pages', 'velox'); ?> <span class="vfx-tab-n"><?php echo count( $optpages ); ?></span></a>
	</div>

	<?php if ( 'post-types' === $tab ) : ?>
		<div class="vfx-head-row">
			<p class="velox-hint" style="margin:0;"><?php esc_html_e('Custom post types appear in the admin sidebar next to Posts and Pages.', 'velox'); ?></p>
			<button class="velox-btn velox-btn--primary" id="vpt-add"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="margin-right:6px;"><path d="M12 5v14M5 12h14"/></svg> <?php esc_html_e('Add post type', 'velox'); ?></button>
		</div>
		<div class="velox-panel velox-panel--flush vfx-list" id="vpt-list">
			<?php if ( empty( $cpts ) ) : ?>
				<p class="velox-hint" style="padding:26px;"><?php esc_html_e('No custom post types yet. Add one and it shows up in the sidebar straight away.', 'velox'); ?></p>
			<?php else : foreach ( $cpts as $pt ) : ?>
				<div class="vfx-row" data-slug="<?php echo esc_attr( $pt['slug'] ); ?>" data-json="<?php echo esc_attr( wp_json_encode( $pt ) ); ?>">
					<button type="button" class="vfx-row-main vpt-edit">
						<span class="vfx-row-title"><?php echo esc_html( $pt['plural'] ?: $pt['slug'] ); ?></span>
						<span class="vfx-row-meta"><code><?php echo esc_html( $pt['slug'] ); ?></code> · <?php echo ! empty( $pt['hierarchical'] ) ? 'hierarchical' : 'flat'; ?><?php echo ! empty( $pt['has_archive'] ) ? ' · archive' : ''; ?></span>
					</button>
					<span class="vfx-row-active">
						<span class="vfx-row-status <?php echo ! empty( $pt['active'] ) ? 'is-active' : ''; ?>"><?php echo ! empty( $pt['active'] ) ? 'Active' : 'Inactive'; ?></span>
						<label class="velox-switch vfx-row-toggle" data-vtype="posttype" data-id="<?php echo esc_attr( $pt['slug'] ); ?>" title="<?php esc_attr_e('Toggle active', 'velox'); ?>"><input type="checkbox" <?php checked( ! empty( $pt['active'] ) ); ?>><span class="velox-switch-track"></span></label>
					</span>
					<button class="vfx-row-del vpt-del" data-slug="<?php echo esc_attr( $pt['slug'] ); ?>" title="<?php esc_attr_e('Delete', 'velox'); ?>"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-13M9 7V4h6v3"/></svg></button>
				</div>
			<?php endforeach; endif; ?>
		</div>

		<div class="velox-panel vfx-editor" id="vpt-editor" hidden>
			<h3 class="velox-panel-title" id="vpt-editor-title"><?php esc_html_e('Add post type', 'velox'); ?></h3>
			<div class="velox-grid-2">
				<div class="velox-field"><span class="velox-field-label"><?php esc_html_e('Singular label', 'velox'); ?></span><input type="text" class="velox-input" id="vpt-singular" placeholder="Movie"></div>
				<div class="velox-field"><span class="velox-field-label"><?php esc_html_e('Plural label', 'velox'); ?></span><input type="text" class="velox-input" id="vpt-plural" placeholder="Movies"></div>
			</div>
			<div class="velox-field"><span class="velox-field-label"><?php esc_html_e('Slug', 'velox'); ?> <em><?php esc_html_e('(lowercase, max 20 chars — this is the post type key)', 'velox'); ?></em></span><input type="text" class="velox-input" id="vpt-slug" placeholder="movie" maxlength="20"></div>
			<div class="velox-grid-2">
				<div class="velox-field"><span class="velox-field-label"><?php esc_html_e('Menu icon', 'velox'); ?> <em><?php esc_html_e('(dashicons-… or image URL)', 'velox'); ?></em></span><input type="text" class="velox-input" id="vpt-icon" placeholder="dashicons-video-alt2"></div>
				<div class="velox-field"><span class="velox-field-label"><?php esc_html_e('Menu position', 'velox'); ?></span><input type="number" class="velox-input velox-input--sm" id="vpt-menupos" value="25"></div>
			</div>
			<div class="velox-field">
				<span class="velox-field-label"><?php esc_html_e('Supports', 'velox'); ?></span>
				<div class="vfx-checks" id="vpt-supports">
					<?php foreach ( $supports as $sk => $sl ) : ?>
						<label class="vfx-check"><input type="checkbox" value="<?php echo esc_attr( $sk ); ?>"<?php echo in_array( $sk, array( 'title', 'editor', 'thumbnail', 'custom-fields' ), true ) ? ' checked' : ''; ?>> <span><?php echo esc_html( $sl ); ?></span></label>
					<?php endforeach; ?>
				</div>
			</div>
			<?php if ( ! empty( $taxes ) ) : ?>
			<div class="velox-field">
				<span class="velox-field-label"><?php esc_html_e('Attach taxonomies', 'velox'); ?></span>
				<div class="vfx-checks" id="vpt-taxonomies">
					<?php foreach ( $taxes as $tx ) : ?>
						<label class="vfx-check"><input type="checkbox" value="<?php echo esc_attr( $tx['slug'] ); ?>"> <span><?php echo esc_html( $tx['plural'] ?: $tx['slug'] ); ?></span></label>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>
			<div class="velox-grid-2 vfx-toggles">
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label"><?php esc_html_e('Active', 'velox'); ?></span></div><span class="velox-switch"><input type="checkbox" id="vpt-active" checked><span class="velox-switch-track"></span></span></label>
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label"><?php esc_html_e('Public', 'velox'); ?></span></div><span class="velox-switch"><input type="checkbox" id="vpt-public" checked><span class="velox-switch-track"></span></span></label>
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label"><?php esc_html_e('Show in sidebar menu', 'velox'); ?></span></div><span class="velox-switch"><input type="checkbox" id="vpt-menu" checked><span class="velox-switch-track"></span></span></label>
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label"><?php esc_html_e('Show in REST (Gutenberg)', 'velox'); ?></span></div><span class="velox-switch"><input type="checkbox" id="vpt-rest" checked><span class="velox-switch-track"></span></span></label>
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label"><?php esc_html_e('Has archive page', 'velox'); ?></span></div><span class="velox-switch"><input type="checkbox" id="vpt-archive" checked><span class="velox-switch-track"></span></span></label>
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label"><?php esc_html_e('Hierarchical (page-like)', 'velox'); ?></span></div><span class="velox-switch"><input type="checkbox" id="vpt-hier"><span class="velox-switch-track"></span></span></label>
			</div>
			<div class="vfx-editor-actions">
				<button class="velox-btn velox-btn--ghost" id="vpt-cancel" type="button"><?php esc_html_e('Cancel', 'velox'); ?></button>
				<button class="velox-btn velox-btn--primary" id="vpt-save" type="button"><?php esc_html_e('Save post type', 'velox'); ?></button>
			</div>
		</div>

	<?php elseif ( 'taxonomies' === $tab ) : ?>
		<div class="vfx-head-row">
			<p class="velox-hint" style="margin:0;"><?php esc_html_e('Taxonomies group your content — like Categories (hierarchical) or Tags (flat).', 'velox'); ?></p>
			<button class="velox-btn velox-btn--primary" id="vtx-add"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="margin-right:6px;"><path d="M12 5v14M5 12h14"/></svg> <?php esc_html_e('Add taxonomy', 'velox'); ?></button>
		</div>
		<div class="velox-panel velox-panel--flush vfx-list" id="vtx-list">
			<?php if ( empty( $taxes ) ) : ?>
				<p class="velox-hint" style="padding:26px;"><?php esc_html_e('No custom taxonomies yet.', 'velox'); ?></p>
			<?php else : foreach ( $taxes as $tx ) : ?>
				<div class="vfx-row" data-slug="<?php echo esc_attr( $tx['slug'] ); ?>" data-json="<?php echo esc_attr( wp_json_encode( $tx ) ); ?>">
					<button type="button" class="vfx-row-main vtx-edit">
						<span class="vfx-row-title"><?php echo esc_html( $tx['plural'] ?: $tx['slug'] ); ?></span>
						<span class="vfx-row-meta"><code><?php echo esc_html( $tx['slug'] ); ?></code> · <?php echo ! empty( $tx['hierarchical'] ) ? 'category-like' : 'tag-like'; ?> · <?php echo esc_html( implode( ', ', $tx['object_types'] ) ); ?></span>
					</button>
					<span class="vfx-row-active">
						<span class="vfx-row-status <?php echo ! empty( $tx['active'] ) ? 'is-active' : ''; ?>"><?php echo ! empty( $tx['active'] ) ? 'Active' : 'Inactive'; ?></span>
						<label class="velox-switch vfx-row-toggle" data-vtype="taxonomy" data-id="<?php echo esc_attr( $tx['slug'] ); ?>" title="<?php esc_attr_e('Toggle active', 'velox'); ?>"><input type="checkbox" <?php checked( ! empty( $tx['active'] ) ); ?>><span class="velox-switch-track"></span></label>
					</span>
					<button class="vfx-row-del vtx-del" data-slug="<?php echo esc_attr( $tx['slug'] ); ?>" title="<?php esc_attr_e('Delete', 'velox'); ?>"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-13M9 7V4h6v3"/></svg></button>
				</div>
			<?php endforeach; endif; ?>
		</div>

		<div class="velox-panel vfx-editor" id="vtx-editor" hidden>
			<h3 class="velox-panel-title" id="vtx-editor-title"><?php esc_html_e('Add taxonomy', 'velox'); ?></h3>
			<div class="velox-grid-2">
				<div class="velox-field"><span class="velox-field-label"><?php esc_html_e('Singular label', 'velox'); ?></span><input type="text" class="velox-input" id="vtx-singular" placeholder="Genre"></div>
				<div class="velox-field"><span class="velox-field-label"><?php esc_html_e('Plural label', 'velox'); ?></span><input type="text" class="velox-input" id="vtx-plural" placeholder="Genres"></div>
			</div>
			<div class="velox-field"><span class="velox-field-label"><?php esc_html_e('Slug', 'velox'); ?> <em><?php esc_html_e('(lowercase, max 32 chars)', 'velox'); ?></em></span><input type="text" class="velox-input" id="vtx-slug" placeholder="genre" maxlength="32"></div>
			<div class="velox-field">
				<span class="velox-field-label"><?php esc_html_e('Attach to post types', 'velox'); ?></span>
				<div class="vfx-checks" id="vtx-objects">
					<?php foreach ( $sel_pts as $ptslug => $ptlabel ) : ?>
						<label class="vfx-check"><input type="checkbox" value="<?php echo esc_attr( $ptslug ); ?>"<?php echo 'post' === $ptslug ? ' checked' : ''; ?>> <span><?php echo esc_html( $ptlabel ); ?></span></label>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="velox-grid-2 vfx-toggles">
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label"><?php esc_html_e('Active', 'velox'); ?></span></div><span class="velox-switch"><input type="checkbox" id="vtx-active" checked><span class="velox-switch-track"></span></span></label>
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label"><?php esc_html_e('Public', 'velox'); ?></span></div><span class="velox-switch"><input type="checkbox" id="vtx-public" checked><span class="velox-switch-track"></span></span></label>
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label"><?php esc_html_e('Hierarchical (category-like)', 'velox'); ?></span></div><span class="velox-switch"><input type="checkbox" id="vtx-hier" checked><span class="velox-switch-track"></span></span></label>
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label"><?php esc_html_e('Show in REST (Gutenberg)', 'velox'); ?></span></div><span class="velox-switch"><input type="checkbox" id="vtx-rest" checked><span class="velox-switch-track"></span></span></label>
				<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label"><?php esc_html_e('Show admin column', 'velox'); ?></span></div><span class="velox-switch"><input type="checkbox" id="vtx-col" checked><span class="velox-switch-track"></span></span></label>
			</div>
			<div class="vfx-editor-actions">
				<button class="velox-btn velox-btn--ghost" id="vtx-cancel" type="button"><?php esc_html_e('Cancel', 'velox'); ?></button>
				<button class="velox-btn velox-btn--primary" id="vtx-save" type="button"><?php esc_html_e('Save taxonomy', 'velox'); ?></button>
			</div>
		</div>

	<?php elseif ( 'options' === $tab ) : ?>
		<div class="vfx-head-row">
			<p class="velox-hint" style="margin:0;"><?php printf( esc_html__( 'Options pages are admin screens for global settings (read with %s). Target one from a field group\'s location rule.', 'velox' ), '<code>' . esc_html__( "Velox_Fields::get_field('name','option')", 'velox' ) . '</code>' ); ?></p>
			<button class="velox-btn velox-btn--primary" id="vop-add"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="margin-right:6px;"><path d="M12 5v14M5 12h14"/></svg> <?php esc_html_e('Add options page', 'velox'); ?></button>
		</div>
		<div class="velox-panel velox-panel--flush vfx-list" id="vop-list">
			<?php if ( empty( $optpages ) ) : ?>
				<p class="velox-hint" style="padding:26px;"><?php esc_html_e('No options pages yet.', 'velox'); ?></p>
			<?php else : foreach ( $optpages as $op ) : ?>
				<div class="vfx-row" data-slug="<?php echo esc_attr( $op['slug'] ); ?>" data-json="<?php echo esc_attr( wp_json_encode( $op ) ); ?>">
					<button type="button" class="vfx-row-main vop-edit">
						<span class="vfx-row-title"><?php echo esc_html( $op['menu_title'] ?: $op['title'] ); ?></span>
						<span class="vfx-row-meta"><code><?php echo esc_html( $op['slug'] ); ?></code> · <?php echo '' === $op['parent'] ? 'top-level menu' : esc_html( 'under ' . $op['parent'] ); ?></span>
					</button>
					<?php $op_active = ! isset( $op['active'] ) || ! empty( $op['active'] ); ?>
					<span class="vfx-row-active">
						<span class="vfx-row-status <?php echo $op_active ? 'is-active' : ''; ?>"><?php echo $op_active ? 'Active' : 'Inactive'; ?></span>
						<label class="velox-switch vfx-row-toggle" data-vtype="optionpage" data-id="<?php echo esc_attr( $op['slug'] ); ?>" title="<?php esc_attr_e('Toggle active', 'velox'); ?>"><input type="checkbox" <?php checked( $op_active ); ?>><span class="velox-switch-track"></span></label>
					</span>
					<button class="vfx-row-del vop-del" data-slug="<?php echo esc_attr( $op['slug'] ); ?>" title="<?php esc_attr_e('Delete', 'velox'); ?>"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-13M9 7V4h6v3"/></svg></button>
				</div>
			<?php endforeach; endif; ?>
		</div>

		<div class="velox-panel vfx-editor" id="vop-editor" hidden>
			<h3 class="velox-panel-title" id="vop-editor-title"><?php esc_html_e('Add options page', 'velox'); ?></h3>
			<div class="velox-grid-2">
				<div class="velox-field"><span class="velox-field-label"><?php esc_html_e('Page title', 'velox'); ?></span><input type="text" class="velox-input" id="vop-title" placeholder="Theme Settings"></div>
				<div class="velox-field"><span class="velox-field-label"><?php esc_html_e('Menu title', 'velox'); ?> <em><?php esc_html_e('(optional)', 'velox'); ?></em></span><input type="text" class="velox-input" id="vop-menu" placeholder="Settings"></div>
			</div>
			<div class="velox-grid-2">
				<div class="velox-field"><span class="velox-field-label"><?php esc_html_e('Slug', 'velox'); ?> <em><?php esc_html_e('(lowercase, max 32)', 'velox'); ?></em></span><input type="text" class="velox-input" id="vop-slug" placeholder="theme-settings" maxlength="32"></div>
				<div class="velox-field"><span class="velox-field-label"><?php esc_html_e('Parent menu', 'velox'); ?></span>
					<select class="velox-select" id="vop-parent">
						<option value=""><?php esc_html_e('Top-level menu', 'velox'); ?></option>
						<option value="velox"><?php esc_html_e('Under Velox', 'velox'); ?></option>
						<option value="options-general.php"><?php esc_html_e('Under Settings', 'velox'); ?></option>
						<option value="themes.php"><?php esc_html_e('Under Appearance', 'velox'); ?></option>
						<option value="tools.php"><?php esc_html_e('Under Tools', 'velox'); ?></option>
						<option value="edit.php"><?php esc_html_e('Under Posts', 'velox'); ?></option>
						<option value="upload.php"><?php esc_html_e('Under Media', 'velox'); ?></option>
					</select>
				</div>
			</div>
			<div class="velox-grid-2">
				<div class="velox-field"><span class="velox-field-label"><?php esc_html_e('Menu icon', 'velox'); ?> <em><?php esc_html_e('(top-level only)', 'velox'); ?></em></span>
					<div class="vop-icon-row">
						<button type="button" class="velox-btn velox-btn--ghost" id="vop-icon-pick"><span class="vop-icon-prev" id="vop-icon-prev" aria-hidden="true"></span> <?php esc_html_e('Choose icon', 'velox'); ?></button>
						<input type="text" class="velox-input velox-input--sm" id="vop-icon" placeholder="bi:gift / dashicons-… / URL">
					</div>
					<span class="velox-hint"><?php printf( esc_html__( 'Pick a Bootstrap icon, or type a %s class or image URL.', 'velox' ), '<code>' . esc_html__( 'dashicons-…', 'velox' ) . '</code>' ); ?></span>
				</div>
				<div class="velox-field"><span class="velox-field-label"><?php esc_html_e('Menu position', 'velox'); ?></span><input type="number" class="velox-input" id="vop-position" value="80"></div>
			</div>
			<label class="velox-toggle-row"><div class="velox-toggle-meta"><span class="velox-toggle-label"><?php esc_html_e('Active', 'velox'); ?></span><span class="velox-toggle-desc"><?php esc_html_e('Turn off to hide this page from the admin menu without deleting it.', 'velox'); ?></span></div><span class="velox-switch"><input type="checkbox" id="vop-active" checked><span class="velox-switch-track"></span></span></label>
			<div class="vfx-editor-actions">
				<button class="velox-btn velox-btn--ghost" id="vop-cancel" type="button"><?php esc_html_e('Cancel', 'velox'); ?></button>
				<button class="velox-btn velox-btn--primary" id="vop-save" type="button"><?php esc_html_e('Save options page', 'velox'); ?></button>
			</div>
		</div>

	<?php else : ?>
	<div class="vfx-head-row">
		<p class="velox-hint" style="margin:0;"><?php esc_html_e('Field groups attach custom fields to your content based on location rules.', 'velox'); ?></p>
		<a class="velox-btn velox-btn--primary" href="<?php echo esc_url( $base . '&group=new' ); ?>">
			<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="margin-right:6px;"><path d="M12 5v14M5 12h14"/></svg> <?php esc_html_e('New field group', 'velox'); ?>
		</a>
	</div>

	<?php if ( empty( $groups ) ) : ?>
		<div class="velox-panel" style="text-align:center;padding:48px 20px;">
			<p style="font-size:15px;font-weight:600;margin:0 0 4px;"><?php esc_html_e('No field groups yet', 'velox'); ?></p>
			<p class="velox-hint" style="margin:0 0 16px;"><?php esc_html_e('Create your first field group to start adding custom fields.', 'velox'); ?></p>
			<a class="velox-btn velox-btn--primary" href="<?php echo esc_url( $base . '&group=new' ); ?>"><?php esc_html_e('Create field group', 'velox'); ?></a>
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
					<span class="vfx-row-active">
						<span class="vfg-list-status <?php echo ! empty( $g['active'] ) ? 'is-active' : ''; ?>"><?php echo ! empty( $g['active'] ) ? 'Active' : 'Inactive'; ?></span>
						<label class="velox-switch vfx-row-toggle" data-vtype="group" data-id="<?php echo (int) $g['id']; ?>" title="<?php esc_attr_e('Toggle active', 'velox'); ?>"><input type="checkbox" <?php checked( ! empty( $g['active'] ) ); ?>><span class="velox-switch-track"></span></label>
					</span>
					<button class="vfg-list-del" data-id="<?php echo (int) $g['id']; ?>" data-title="<?php echo esc_attr( $g['title'] ); ?>" title="<?php esc_attr_e('Delete', 'velox'); ?>">
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-13M9 7V4h6v3"/></svg>
					</button>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php endif; // tab switch ?>

	<div class="velox-panel velox-mail-disable" style="margin-top:16px;">
		<label class="velox-inline-toggle">
			<span><strong><?php esc_html_e('Custom fields is on', 'velox'); ?></strong> <span class="velox-hint" style="display:inline;"><?php esc_html_e('— switch off to disable.', 'velox'); ?></span></span>
			<span class="velox-switch"><input type="checkbox" data-setting="util_fields" id="velox-fields-toggle" checked><span class="velox-switch-track"></span></span>
		</label>
	</div>
<?php endif; ?>

<div class="velox-modal" id="vop-icon-modal" hidden>
	<div class="velox-modal-box velox-modal-box--lg">
		<div class="velox-modal-head">
			<span class="velox-modal-title"><?php esc_html_e('Choose a menu icon', 'velox'); ?></span>
			<button type="button" class="velox-modal-x" id="vop-icon-close" aria-label="Close">&times;</button>
		</div>
		<div class="velox-modal-body">
			<input type="text" class="velox-input" id="vop-icon-search" placeholder="Search icons (e.g. gift, gear, cart)…" autocomplete="off" style="margin-bottom:12px;">
			<div class="vop-icon-grid" id="vop-icon-grid"></div>
		</div>
	</div>
</div>
