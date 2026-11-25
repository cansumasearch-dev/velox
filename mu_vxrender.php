<?php
// Sandbox only: draw a builder model exactly as the live page would (HTML + the
// element CSS), so a test can compare it with the editor after every change.
add_action( 'wp_ajax_vxt_render', function () {
	$m = json_decode( wp_unslash( (string) ( $_POST['model'] ?? '' ) ), true );
	if ( ! is_array( $m ) ) {
		wp_send_json_error( 'bad model' );
	}
	$r = new ReflectionClass( 'Velox_Builder_Render' );
	foreach ( array( 'float_css' => '', 'runtime_used' => array(), 'css_only' => false, 'previewing' => true ) as $k => $v ) {
		if ( $r->hasProperty( $k ) ) {
			$p = $r->getProperty( $k );
			$p->setAccessible( true );
			$p->setValue( null, $v );
		}
	}
	$errs = array();
	set_error_handler( function ( $no, $str, $file, $line ) use ( &$errs ) {
		if ( false !== strpos( $file, '/velox/' ) ) {
			$errs[] = $str . ' @' . basename( $file ) . ':' . $line;
		}
		return true;
	} );
	ob_start();
	try {
		$html = Velox_Builder_Render::render_tree( $m['tree'] ?? array(), $m );
	} catch ( Throwable $e ) {
		$html = '';
		$errs[] = 'EXC ' . $e->getMessage() . ' @' . basename( $e->getFile() ) . ':' . $e->getLine();
	}
	$echoed = ob_get_clean();
	$fp = $r->hasProperty( 'float_css' ) ? $r->getProperty( 'float_css' ) : null;
	if ( $fp ) { $fp->setAccessible( true ); }
	$float = $fp ? (string) $fp->getValue() : '';
	$css = Velox_Builder_Render::element_base_css();
	$doccss = '';
	try { $doccss = (string) Velox_Builder_Render::generate_css( $m ); } catch ( Throwable $e ) { $errs[] = 'CSS EXC ' . $e->getMessage(); }
	restore_error_handler();
	wp_send_json_success( array( 'html' => $html . $echoed, 'css' => $css, 'doc_css' => $doccss, 'float' => $float, 'errs' => $errs ) );
} );
