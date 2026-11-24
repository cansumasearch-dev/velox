<?php
require '/wordpress/wp-load.php'; ini_set('display_errors','1'); error_reporting(E_ALL);
require_once ABSPATH . 'wp-admin/includes/image.php';
$s = get_option( 'velox_settings', array() ); $s['util_shop'] = true; $s[ Velox_Builder::ENABLE_KEY ] = true; update_option( 'velox_settings', $s );
update_user_meta( 1, 'first_name', 'Kyrix' );
@mkdir( WP_CONTENT_DIR . '/mu-plugins' );
file_put_contents( WP_CONTENT_DIR . '/mu-plugins/vxmail.php', base64_decode( 'PD9waHAKLy8gU2FuZGJveCBvbmx5OiBrZWVwIG91dGdvaW5nIG1haWwgaW4gYW4gb3B0aW9uIGluc3RlYWQgb2Ygc2VuZGluZyBpdC4KYWRkX2ZpbHRlciggJ3ByZV93cF9tYWlsJywgZnVuY3Rpb24gKCAkciwgJGEgKSB7ICRsID0gZ2V0X29wdGlvbiggJ3Z4dF9tYWlscycsIGFycmF5KCkgKTsgJGxbXSA9IGFycmF5KCAndG8nID0+ICRhWyd0byddLCAncycgPT4gJGFbJ3N1YmplY3QnXSwgJ20nID0+ICRhWydtZXNzYWdlJ10sICd0JyA9PiB0aW1lKCkgKTsgdXBkYXRlX29wdGlvbiggJ3Z4dF9tYWlscycsIGFycmF5X3NsaWNlKCAkbCwgLTQwICksIGZhbHNlICk7IHJldHVybiB0cnVlOyB9LCAxMCwgMiApOwo=' ) );
update_option( 'permalink_structure', '/%postname%/' );
$mkpage = function ( $t, $slug, $c ) { return wp_insert_post( array( 'post_title' => $t, 'post_name' => $slug, 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => $c ) ); };
$o = get_option( 'velox_shop', array() );
$o['page_shop'] = $mkpage( 'Shop', 'shop', '[velox_shop]' );
$o['page_cart'] = $mkpage( 'Warenkorb', 'warenkorb', '[velox_cart]' );
$o['page_checkout'] = $mkpage( 'Kasse', 'kasse', '[velox_checkout]' );
$o['page_account'] = $mkpage( 'Mein Konto', 'konto', '[velox_account]' );
$o['page_privacy'] = $mkpage( 'Datenschutz', 'datenschutz', 'Datenschutz' );
$o['store_name'] = 'Testladen'; $o['pay_bank'] = '1'; $o['bank_iban'] = 'DE89370400440532013000'; $o['bank_owner'] = 'Testladen'; $o['setup_done'] = 1;
$o['grid_search'] = '1'; $o['per_page'] = '8';
update_option( 'velox_shop', $o );
// photos drawn with GD
$up = wp_upload_dir();
$img = function ( $name, $hex, $shape ) use ( $up ) {
	$im = imagecreatetruecolor( 800, 1000 ); $bg = imagecolorallocate( $im, 244, 242, 238 ); imagefill( $im, 0, 0, $bg );
	list( $r, $g, $b ) = sscanf( $hex, '#%02x%02x%02x' ); $c = imagecolorallocate( $im, $r, $g, $b );
	if ( 'vase' === $shape ) { imagefilledellipse( $im, 400, 620, 360, 460, $c ); imagefilledrectangle( $im, 350, 300, 450, 420, $c ); }
	elseif ( 'cup' === $shape ) { imagefilledrectangle( $im, 260, 380, 540, 760, $c ); imagesetthickness( $im, 30 ); imagearc( $im, 560, 570, 150, 200, 270, 90, $c ); }
	elseif ( 'plate' === $shape ) { imagefilledellipse( $im, 400, 620, 560, 200, $c ); }
	elseif ( 'pillow' === $shape ) { imagefilledrectangle( $im, 180, 330, 620, 770, $c ); }
	else { imagefilledrectangle( $im, 220, 220, 580, 780, imagecolorallocate( $im, 250, 248, 240 ) ); imagefilledellipse( $im, 400, 420, 220, 220, $c ); }
	$f = $up['path'] . '/' . $name . '.png'; imagepng( $im, $f );
	$a = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => $name, 'post_status' => 'inherit' ), $f );
	wp_update_attachment_metadata( $a, wp_generate_attachment_metadata( $a, $f ) );
	return $a;
};
$cat = function ( $n ) { $t = term_exists( $n, Velox_Shop::CAT ); if ( ! $t ) { $t = wp_insert_term( $n, Velox_Shop::CAT ); } return (int) $t['term_id']; };
$prod = function ( $title, $cats, $in, $thumb ) use ( $cat ) {
	$id = wp_insert_post( array( 'post_type' => Velox_Shop::PRODUCT, 'post_status' => 'publish', 'post_title' => $title, 'post_excerpt' => 'Handgemacht in kleiner Auflage.' ) );
	wp_set_object_terms( $id, array_map( $cat, $cats ), Velox_Shop::CAT );
	set_post_thumbnail( $id, $thumb );
	Velox_Shop::save_product( $id, $in );
	return $id;
};
$vb = $img( 'vase-blau', '#1e5bb8', 'vase' ); $vr = $img( 'vase-rot', '#c62828', 'vase' ); $vg = $img( 'vase-gruen', '#2e7d32', 'vase' );
$vase = $prod( 'Vase Linea', array( 'Keramik' ), array( 'price' => '89', 'track' => 1, 'options' => array( array( 'name' => 'Farbe', 'values' => array( 'Blau', 'Rot', 'Grün' ) ) ),
	'variants' => array( array( 'id' => 'blau', 'opts' => array( 'Blau' ), 'stock' => '3', 'image' => $vb ), array( 'id' => 'rot', 'opts' => array( 'Rot' ), 'stock' => '0', 'image' => $vr ), array( 'id' => 'gruen', 'opts' => array( 'Grün' ), 'stock' => '2', 'image' => $vg ) ) ), $vb );
$cs = $img( 'tasse-schwarz', '#151515', 'cup' ); $cw = $img( 'tasse-weiss', '#ffffff', 'cup' );
$prod( 'Tasse Mono', array( 'Keramik' ), array( 'price' => '24', 'track' => 1, 'options' => array( array( 'name' => 'Farbe', 'values' => array( 'Schwarz', 'Weiß' ) ), array( 'name' => 'Größe', 'values' => array( 'S', 'M' ) ) ),
	'variants' => array( array( 'id' => 'ss', 'opts' => array( 'Schwarz', 'S' ), 'stock' => '5', 'image' => $cs, 'compare' => '29' ), array( 'id' => 'sm', 'opts' => array( 'Schwarz', 'M' ), 'stock' => '5', 'image' => $cs ), array( 'id' => 'ws', 'opts' => array( 'Weiß', 'S' ), 'stock' => '4', 'image' => $cw ), array( 'id' => 'wm', 'opts' => array( 'Weiß', 'M' ), 'stock' => '0', 'image' => $cw ) ) ), $cs );
$prod( 'Teller Rund', array( 'Keramik' ), array( 'price' => '39', 'track' => 1, 'stock' => '0' ), $img( 'teller', '#c9b79c', 'plate' ) );
$prod( 'Druck A3', array( 'Drucke' ), array( 'price' => '39' ), $img( 'druck', '#d0603e', 'print' ) );
$prod( 'Druck A2', array( 'Drucke' ), array( 'price' => '59', 'compare' => '69' ), $img( 'druck2', '#1f5f6b', 'print' ) );
$kb = $img( 'kissen-beige', '#e3d5bd', 'pillow' ); $ks = $img( 'kissen-senf', '#c9a227', 'pillow' ); $kg = $img( 'kissen-salbei', '#9caf88', 'pillow' );
$prod( 'Kissen Lino', array( 'Textil' ), array( 'price' => '45', 'options' => array( array( 'name' => 'Farbe', 'values' => array( 'Beige', 'Senf', 'Salbei' ) ) ),
	'variants' => array( array( 'id' => 'b', 'opts' => array( 'Beige' ), 'image' => $kb ), array( 'id' => 's', 'opts' => array( 'Senf' ), 'image' => $ks ), array( 'id' => 'g', 'opts' => array( 'Salbei' ), 'image' => $kg ) ) ), $kb );
$prod( 'Schale Unikat', array( 'Keramik' ), array( 'price' => '120', 'unique' => 1, 'stock' => '0' ), $img( 'schale', '#6f8f7a', 'plate' ) );
for ( $i = 1; $i <= 5; $i++ ) { $prod( 'Becher ' . $i, array( 'Keramik' ), array( 'price' => (string) ( 15 + $i * 3 ) ), $img( 'becher' . $i, '#8a6a4f', 'cup' ) ); }
// a paid order by a test customer (for "verified purchase")
$p = Velox_Shop::product( $vase ); $v = Velox_Shop::variant( $p, 'blau' );
$lines = Velox_Shop::lines( array( $vase . ':blau' => 1 ) );
$t = Velox_Shop::totals( $lines, 'DE' );
$c = array( 'customer' => array( 'email' => 'gerhard@example.test', 'first' => 'Gerhard', 'last' => 'Test', 'phone' => '', 'company' => '', 'address' => array( 'line1' => 'Teststr. 1', 'line2' => '', 'zip' => '12345', 'city' => 'Berlin', 'country' => 'DE' ) ), 'ship_to' => null, 'note' => '' );
$ord = Velox_Shop::create_order( $c, $lines, $t, 'bank' ); Velox_Shop::mark_paid( $ord['id'], '', true, false );
flush_rewrite_rules();
echo 'ok';
