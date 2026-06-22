<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var Velox_Admin $admin */
$current = $admin->current_tab();
?>
<div class="velox-wrap" data-tab="<?php echo esc_attr( $current ); ?>">

	<header class="velox-topbar">
		<div class="velox-brand">
			<span class="velox-logo" aria-hidden="true">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M13 2 4.5 13.5H11l-1 8.5L19.5 10H13l0-8Z" fill="currentColor"/></svg>
			</span>
			<span class="velox-name">Velox</span>
			<span class="velox-ver">v<?php echo esc_html( VELOX_VERSION ); ?></span>
		</div>
		<nav class="velox-nav">
			<?php foreach ( $admin->enabled_tabs() as $key => $tab ) : ?>
				<a href="<?php echo esc_url( $admin->tab_url( $key ) ); ?>"
				   class="velox-nav-item<?php echo $current === $key ? ' is-active' : ''; ?>">
					<?php echo Velox_Admin::icon( $tab['icon'], 18 ); ?>
					<span><?php echo esc_html( $tab['label'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
	</header>

	<div class="velox-toast" id="velox-toast"></div>
	<main class="velox-main">
