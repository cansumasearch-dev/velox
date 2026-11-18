<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** Velox Builder — Popups: every popup and slide-in panel on the site, in one list. */
global $wpdb;
$rows = $wpdb->get_results( 'SELECT id, kind, title, status, post_id, data, draft FROM ' . Velox_Builder::table() . " WHERE kind IN ('page','template','reusable') ORDER BY title ASC", ARRAY_A );

$trig_words = array(
	'click'   => __( 'when a visitor clicks a link or button to it', 'velox' ),
	'load'    => __( 'as soon as the page loads', 'velox' ),
	'delay'   => __( 'after %s seconds', 'velox' ),
	'scroll'  => __( 'after scrolling %s%% of the page', 'velox' ),
	'element' => __( 'when %s scrolls into view', 'velox' ),
	'idle'    => __( 'after %s seconds without activity', 'velox' ),
	'exit'    => __( 'when the visitor moves to leave', 'velox' ),
);
$freq_words = array(
	'always'  => __( 'every time', 'velox' ),
	'session' => __( 'once per visit', 'velox' ),
	'day'     => __( 'once a day', 'velox' ),
	'days'    => __( 'once every %s days', 'velox' ),
	'ever'    => __( 'once, ever', 'velox' ),
);
$kinds = array(
	'page'     => __( 'Page', 'velox' ),
	'template' => __( 'Template — every page using it', 'velox' ),
	'reusable' => __( 'Component — every page using it', 'velox' ),
);

$items = array();
foreach ( (array) $rows as $row ) {
	$live  = json_decode( (string) $row['data'], true );
	$draft = ( null !== $row['draft'] && '' !== $row['draft'] ) ? json_decode( $row['draft'], true ) : null;
	$model = is_array( $draft ) ? $draft : $live;
	if ( ! is_array( $model ) || empty( $model['tree'] ) ) {
		continue;
	}
	$content = is_array( $model['content'] ?? null ) ? $model['content'] : array();
	$walk    = function ( $nodes ) use ( &$walk, &$items, $row, $content, $trig_words, $freq_words ) {
		foreach ( (array) $nodes as $n ) {
			if ( ! is_array( $n ) ) {
				continue;
			}
			if ( in_array( $n['el'] ?? '', array( 'Modal', 'Offcanvas' ), true ) ) {
				$s    = is_array( $n['settings'] ?? null ) ? $n['settings'] : array();
				$trig = (string) ( $s['trigType'] ?? 'click' );
				$trig = isset( $trig_words[ $trig ] ) ? $trig : 'click';
				$arg  = array( 'delay' => $s['trigDelay'] ?? '3', 'scroll' => $s['trigScroll'] ?? '50', 'element' => $s['trigTarget'] ?? '', 'idle' => $s['trigIdle'] ?? '20' );
				$when = sprintf( $trig_words[ $trig ], esc_html( (string) ( $arg[ $trig ] ?? '' ) ) );
				$once = (string) ( $s['trigOnce'] ?? 'session' );
				$freq = 'click' === $trig ? '' : sprintf( $freq_words[ $once ] ?? $freq_words['session'], (int) ( $s['trigDays'] ?? 7 ) );
				$extra = array();
				if ( (int) ( $s['trigPages'] ?? 0 ) > 1 ) {
					/* translators: %d: number of pages. */
					$extra[] = sprintf( __( 'after %d pages in the visit', 'velox' ), (int) $s['trigPages'] );
				}
				if ( ! empty( $s['trigDevice'] ) ) {
					$extra[] = 'desktop' === $s['trigDevice'] ? __( 'desktop only', 'velox' ) : __( 'tablet and phone only', 'velox' );
				}
				if ( (float) ( $s['autoClose'] ?? 0 ) > 0 ) {
					/* translators: %s: seconds. */
					$extra[] = sprintf( __( 'closes by itself after %s s', 'velox' ), (float) $s['autoClose'] );
				}
				if ( ! empty( $s['stopOnClick'] ) ) {
					$extra[] = __( 'not again after a click inside', 'velox' );
				}
				if ( ! empty( $n['cond'] ) ) {
					$extra[] = __( 'has conditions', 'velox' );
				}
				// A name: its own, the screen-reader name, or the first heading inside.
				$name = (string) ( $n['name'] ?? '' );
				if ( '' === $name ) {
					$name = (string) ( $s['dlgLabel'] ?? '' );
				}
				if ( '' === $name ) {
					$find = function ( $kids ) use ( &$find, $content ) {
						foreach ( (array) $kids as $k ) {
							if ( 'Heading' === ( $k['el'] ?? '' ) && ! empty( $content[ $k['id'] ] ) ) {
								return wp_strip_all_tags( (string) $content[ $k['id'] ] );
							}
							$r = $find( $k['children'] ?? array() );
							if ( '' !== $r ) {
								return $r;
							}
						}
						return '';
					};
					$name = $find( $n['children'] ?? array() );
				}
				$items[] = array(
					'id'    => (string) ( $n['id'] ?? '' ),
					'type'  => 'Modal' === $n['el'] ? __( 'Popup', 'velox' ) : __( 'Slide-in panel', 'velox' ),
					'name'  => '' !== $name ? $name : __( 'Unnamed popup', 'velox' ),
					'when'  => $when,
					'freq'  => $freq,
					'extra' => $extra,
					'doc'   => $row,
				);
			}
			$walk( $n['children'] ?? array() );
		}
	};
	$walk( $model['tree'] );
}
?>
<div class="vba-shell">
	<header class="vba-head">
		<div>
			<h1><?php esc_html_e( 'Popups', 'velox' ); ?></h1>
			<p><?php esc_html_e( 'Every popup and slide-in panel on the site: when it appears, how often, and where it lives. A popup in a template or component shows on every page that uses it.', 'velox' ); ?></p>
		</div>
	</header>
	<div class="vba-card">
		<?php if ( ! $items ) : ?>
			<div class="vba-empty">
				<span class="vba-empty-ic"><?php echo Velox_Admin::icon( 'grid', 24 ); // phpcs:ignore ?></span>
				<b><?php esc_html_e( 'No popups yet', 'velox' ); ?></b>
				<p><?php esc_html_e( 'In the editor, open Add → Interactive and pick “Popup / modal” or one of the ready-made popups. Put it in a template to show it across the site.', 'velox' ); ?></p>
			</div>
		<?php else : ?>
			<div class="vba-doclist vba-poplist">
				<?php foreach ( $items as $it ) :
					$d       = $it['doc'];
					$edit    = Velox_Builder::edit_url( (int) $d['id'], 'page' === $d['kind'] ? '' : $d['kind'] );
					$where   = ( 'page' === $d['kind'] && (int) $d['post_id'] ) ? get_permalink( (int) $d['post_id'] ) : home_url( '/' );
					$preview = $where ? $where . '#' . rawurlencode( $it['id'] ) : '';
					?>
					<div class="vba-docrow vba-poprow">
						<span class="vba-doc-ic"><?php echo Velox_Admin::icon( 'grid', 15 ); // phpcs:ignore ?></span>
						<div class="vba-pop-main">
							<a class="vba-doc-title" href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $it['name'] ); ?></a>
							<span class="vba-pop-when"><?php
								echo esc_html( $it['type'] . ' · ' . ucfirst( $it['when'] ) . ( $it['freq'] ? ', ' . $it['freq'] : '' ) );
								if ( $it['extra'] ) {
									echo esc_html( ' · ' . implode( ', ', $it['extra'] ) );
								}
							?></span>
						</div>
						<span class="vba-pop-where"><?php echo esc_html( ( $kinds[ $d['kind'] ] ?? $d['kind'] ) . ': ' . ( $d['title'] ? $d['title'] : __( 'Untitled', 'velox' ) ) ); ?></span>
						<?php if ( 'published' === $d['status'] ) : ?>
							<span class="vba-doc-status vba-status-published"><?php esc_html_e( 'Live', 'velox' ); ?></span>
						<?php else : ?>
							<span class="vba-doc-status vba-status-draft"><?php esc_html_e( 'Draft', 'velox' ); ?></span>
						<?php endif; ?>
						<span class="vba-doc-actions">
							<a class="vba-mini" href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Edit', 'velox' ); ?></a>
							<?php if ( $preview && 'published' === $d['status'] ) : ?>
								<a class="vba-mini" target="_blank" rel="noopener" href="<?php echo esc_url( $preview ); ?>" title="<?php esc_attr_e( 'Opens the page with this popup open', 'velox' ); ?>"><?php esc_html_e( 'Preview', 'velox' ); ?></a>
							<?php endif; ?>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
