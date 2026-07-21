<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var Velox_Admin $admin */
$tools = Velox_Utilities::catalog();
$tool  = isset( $_GET['tool'] ) ? sanitize_key( wp_unslash( $_GET['tool'] ) ) : '';

// Route to a tool's own page.
if ( '' !== $tool && isset( $tools[ $tool ] ) && ! empty( $tools[ $tool ]['ready'] ) && ! empty( $tools[ $tool ]['page'] ) ) {
	$sub = VELOX_PATH . 'admin/views/utilities/' . $tool . '.php';
	if ( is_readable( $sub ) ) {
		$hub_url    = admin_url( 'admin.php?page=velox-utilities' );
		$tool_label = $tools[ $tool ]['label'];
		echo '<nav class="velox-breadcrumb" aria-label="Breadcrumb">'
			. '<a href="' . esc_url( admin_url( 'admin.php?page=velox' ) ) . '">Velox</a>'
			. '<span class="velox-breadcrumb-sep">/</span>'
			. '<a href="' . esc_url( $hub_url ) . '">Utilities</a>'
			. '<span class="velox-breadcrumb-sep">/</span>'
			. '<span class="velox-breadcrumb-cur">' . esc_html( $tool_label ) . '</span>'
			. '</nav>';
		include $sub;
		return;
	}
}
?>
<div class="velox-page-head">
	<h1 class="velox-h2">Utilities</h1>
	<p class="velox-sub">A toolbox of site and admin helpers. Switch on what you need — anything you enable appears under Utilities in the sidebar, ready to open.</p>
</div>

<?php
$vx_modules = array(
	array( 'id' => 'performance', 'key' => 'module_performance', 'label' => 'Performance', 'icon' => 'bolt',   'desc' => 'Caching, defer/delay JS, lazy-load, fonts and PageSpeed.' ),
	array( 'id' => 'images',      'key' => 'module_images',      'label' => 'Images',      'icon' => 'image',  'desc' => 'WebP/AVIF conversion and the image optimizer.' ),
	array( 'id' => 'seo',         'key' => 'module_seo',         'label' => 'SEO',         'icon' => 'search', 'desc' => 'XML sitemap, meta titles & descriptions and robots.' ),
);
?>
<h2 class="velox-util-sec">Core areas</h2>
<div class="velox-util-grid">
	<?php foreach ( $vx_modules as $mod ) :
		$mon = (bool) Velox_Settings::get( $mod['key'], true );
		?>
		<div class="velox-util-card">
			<div class="velox-util-head">
				<div class="velox-util-ic"><?php echo Velox_Admin::icon( $mod['icon'], 20 ); ?></div>
				<label class="velox-switch">
					<input type="checkbox" class="velox-util-toggle" data-key="<?php echo esc_attr( $mod['key'] ); ?>" <?php checked( $mon ); ?>>
					<span class="velox-switch-track"></span>
				</label>
			</div>
			<h3 class="velox-util-name"><?php echo esc_html( $mod['label'] ); ?></h3>
			<p class="velox-util-desc"><?php echo esc_html( $mod['desc'] ); ?></p>
			<?php if ( $mon ) : ?>
				<a class="velox-util-open" href="<?php echo esc_url( $admin->tab_url( $mod['id'] ) ); ?>">Open<span class="velox-util-arrow">&rarr;</span></a>
			<?php else : ?>
				<span class="velox-util-hint-off">Switch on to use</span>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>

<h2 class="velox-util-sec">Tools</h2>
<div class="velox-util-grid">
	<?php
	foreach ( $tools as $id => $t ) :
		$ready  = ! empty( $t['ready'] );
		$enable = isset( $t['enable'] ) ? $t['enable'] : '';
		$on     = '' !== $enable && (bool) Velox_Settings::get( $enable, false );
		$has_open = ! empty( $t['page'] ) || ! empty( $t['link'] );
		$danger = ! empty( $t['dangerous'] );
		$acked  = $danger && (bool) Velox_Settings::get( $enable . '_ack', false );
		// Where "Open" goes: linked tools (Media) open a top-level tab; the rest open their utility page.
		$open_url = ! empty( $t['link'] )
			? admin_url( 'admin.php?page=velox-' . $t['link'] )
			: admin_url( 'admin.php?page=velox-utilities&tool=' . $id );
		?>
		<div class="velox-util-card<?php echo $ready ? '' : ' is-planned'; ?><?php echo $danger ? ' is-dangerous' : ''; ?>">
			<div class="velox-util-head">
				<div class="velox-util-ic"><?php echo Velox_Admin::icon( $t['icon'], 20 ); ?></div>
				<?php if ( $ready && '' !== $enable ) : ?>
					<label class="velox-switch">
						<input type="checkbox" class="velox-util-toggle" data-key="<?php echo esc_attr( $enable ); ?>" data-tool="<?php echo esc_attr( $id ); ?>" data-dangerous="<?php echo $danger ? '1' : '0'; ?>" data-acked="<?php echo $acked ? '1' : '0'; ?>" <?php checked( $on ); ?>>
						<span class="velox-switch-track"></span>
					</label>
				<?php elseif ( ! $ready ) : ?>
					<span class="velox-util-badge">Planned</span>
				<?php endif; ?>
			</div>
			<h3 class="velox-util-name"><?php echo esc_html( $t['label'] ); ?></h3>
			<p class="velox-util-desc"><?php echo esc_html( $t['desc'] ); ?></p>
			<?php if ( $danger ) : ?>
				<p class="velox-util-danger">⚠ Editing files directly can break your site. Use with care.</p>
			<?php endif; ?>
			<?php if ( $ready && $has_open && $on ) : ?>
				<a class="velox-util-open" href="<?php echo esc_url( $open_url ); ?>">Open<span class="velox-util-arrow">&rarr;</span></a>
			<?php elseif ( $ready && $has_open && ! $on ) : ?>
				<span class="velox-util-hint-off">Switch on to use</span>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
<?php return; ?>
