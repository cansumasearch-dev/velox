<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A small QR code encoder: byte mode, error correction level M, versions 1–10
 * (up to 213 bytes, plenty for an authenticator link). Draws an SVG, so no
 * secret ever leaves the site for an image service.
 *
 * Follows the structure of Project Nayuki's reference encoder (MIT).
 *
 * @package Velox
 */
class Velox_Qr {

	private static $ecc_per_block = array( -1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26 );
	private static $num_blocks    = array( -1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5 );

	private $size;
	private $mod  = array();
	private $func = array();

	/** An <svg> for the text, or '' when it is too long. */
	public static function svg( $text, $px = 200 ) {
		$m = self::matrix( (string) $text );
		if ( ! $m ) {
			return '';
		}
		$n    = count( $m );
		$q    = 4;
		$path = '';
		for ( $y = 0; $y < $n; $y++ ) {
			for ( $x = 0; $x < $n; $x++ ) {
				if ( $m[ $y ][ $x ] ) {
					$path .= 'M' . ( $x + $q ) . ' ' . ( $y + $q ) . 'h1v1h-1z';
				}
			}
		}
		$v = $n + 2 * $q;
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $v . ' ' . $v . '" width="' . (int) $px . '" height="' . (int) $px . '" shape-rendering="crispEdges" role="img"><rect width="100%" height="100%" fill="#fff"/><path fill="#000" d="' . $path . '"/></svg>';
	}

	/** Rows of booleans (true = dark), or null when the text does not fit. */
	public static function matrix( $text ) {
		$data = array_values( unpack( 'C*', $text ) ?: array() );
		$len  = count( $data );
		for ( $ver = 1; $ver <= 10; $ver++ ) {
			$cap_bits = self::data_codewords( $ver ) * 8;
			$need     = 4 + ( $ver < 10 ? 8 : 16 ) + $len * 8;
			if ( $need <= $cap_bits ) {
				$qr = new self( $ver, $data );
				return $qr->mod;
			}
		}
		return null;
	}

	private static function raw_modules( $ver ) {
		$r = ( 16 * $ver + 128 ) * $ver + 64;
		if ( $ver >= 2 ) {
			$na = intdiv( $ver, 7 ) + 2;
			$r -= ( 25 * $na - 10 ) * $na - 55;
			if ( $ver >= 7 ) {
				$r -= 36;
			}
		}
		return $r;
	}

	private static function data_codewords( $ver ) {
		return intdiv( self::raw_modules( $ver ), 8 ) - self::$ecc_per_block[ $ver ] * self::$num_blocks[ $ver ];
	}

	private function __construct( $ver, $bytes ) {
		$this->size = $ver * 4 + 17;
		$n          = $this->size;
		$this->mod  = array_fill( 0, $n, array_fill( 0, $n, false ) );
		$this->func = array_fill( 0, $n, array_fill( 0, $n, false ) );

		// Bits: mode 0100 (byte), length, data, terminator, padding.
		$bits = array();
		$push = function ( $val, $count ) use ( &$bits ) {
			for ( $i = $count - 1; $i >= 0; $i-- ) {
				$bits[] = ( $val >> $i ) & 1;
			}
		};
		$push( 4, 4 );
		$push( count( $bytes ), $ver < 10 ? 8 : 16 );
		foreach ( $bytes as $b ) {
			$push( $b, 8 );
		}
		$cap = self::data_codewords( $ver ) * 8;
		$push( 0, min( 4, $cap - count( $bits ) ) );
		$push( 0, ( 8 - count( $bits ) % 8 ) % 8 );
		for ( $pad = 0xEC; count( $bits ) < $cap; $pad ^= 0xEC ^ 0x11 ) {
			$push( $pad, 8 );
		}
		$data = array();
		for ( $i = 0; $i < count( $bits ); $i += 8 ) {
			$v = 0;
			for ( $j = 0; $j < 8; $j++ ) {
				$v = ( $v << 1 ) | $bits[ $i + $j ];
			}
			$data[] = $v;
		}

		$this->draw_function_patterns( $ver );
		$this->draw_codewords( $this->add_ecc( $ver, $data ) );

		// Choose the mask with the lowest penalty.
		$best = 0;
		$min  = PHP_INT_MAX;
		for ( $m = 0; $m < 8; $m++ ) {
			$this->apply_mask( $m );
			$this->draw_format_bits( $m );
			$p = $this->penalty();
			if ( $p < $min ) {
				$min  = $p;
				$best = $m;
			}
			$this->apply_mask( $m ); // undo (XOR)
		}
		$this->apply_mask( $best );
		$this->draw_format_bits( $best );
	}

	private function set( $x, $y, $dark ) {
		$this->mod[ $y ][ $x ]  = (bool) $dark;
		$this->func[ $y ][ $x ] = true;
	}

	private function draw_function_patterns( $ver ) {
		$n = $this->size;
		for ( $i = 0; $i < $n; $i++ ) {
			$this->set( 6, $i, 0 === $i % 2 );
			$this->set( $i, 6, 0 === $i % 2 );
		}
		foreach ( array( array( 3, 3 ), array( $n - 4, 3 ), array( 3, $n - 4 ) ) as $c ) {
			for ( $dy = -4; $dy <= 4; $dy++ ) {
				for ( $dx = -4; $dx <= 4; $dx++ ) {
					$x = $c[0] + $dx;
					$y = $c[1] + $dy;
					if ( $x >= 0 && $x < $n && $y >= 0 && $y < $n ) {
						$d = max( abs( $dx ), abs( $dy ) );
						$this->set( $x, $y, 2 !== $d && 4 !== $d );
					}
				}
			}
		}
		$pos = array();
		if ( $ver > 1 ) {
			$na   = intdiv( $ver, 7 ) + 2;
			$step = (int) ( ceil( ( $ver * 4 + 4 ) / ( $na * 2 - 2 ) ) * 2 );
			$pos  = array( 6 );
			for ( $p = $n - 7; count( $pos ) < $na; $p -= $step ) {
				array_splice( $pos, 1, 0, array( $p ) );
			}
		}
		$cnt = count( $pos );
		for ( $i = 0; $i < $cnt; $i++ ) {
			for ( $j = 0; $j < $cnt; $j++ ) {
				if ( ( 0 === $i && 0 === $j ) || ( 0 === $i && $cnt - 1 === $j ) || ( $cnt - 1 === $i && 0 === $j ) ) {
					continue;
				}
				for ( $dy = -2; $dy <= 2; $dy++ ) {
					for ( $dx = -2; $dx <= 2; $dx++ ) {
						$this->set( $pos[ $i ] + $dx, $pos[ $j ] + $dy, 1 !== max( abs( $dx ), abs( $dy ) ) );
					}
				}
			}
		}
		$this->draw_format_bits( 0 );
		if ( $ver >= 7 ) {
			$rem = $ver;
			for ( $i = 0; $i < 12; $i++ ) {
				$rem = ( $rem << 1 ) ^ ( ( $rem >> 11 ) * 0x1F25 );
			}
			$vb = ( $ver << 12 ) | $rem;
			for ( $i = 0; $i < 18; $i++ ) {
				$bit = ( $vb >> $i ) & 1;
				$a   = $n - 11 + $i % 3;
				$b   = intdiv( $i, 3 );
				$this->set( $a, $b, $bit );
				$this->set( $b, $a, $bit );
			}
		}
	}

	private function draw_format_bits( $mask ) {
		$n    = $this->size;
		$data = ( 0 << 3 ) | $mask; // level M = 0
		$rem  = $data;
		for ( $i = 0; $i < 10; $i++ ) {
			$rem = ( $rem << 1 ) ^ ( ( $rem >> 9 ) * 0x537 );
		}
		$bits = ( ( $data << 10 ) | $rem ) ^ 0x5412;
		$g    = function ( $i ) use ( $bits ) { return ( $bits >> $i ) & 1; };
		for ( $i = 0; $i <= 5; $i++ ) {
			$this->set( 8, $i, $g( $i ) );
		}
		$this->set( 8, 7, $g( 6 ) );
		$this->set( 8, 8, $g( 7 ) );
		$this->set( 7, 8, $g( 8 ) );
		for ( $i = 9; $i < 15; $i++ ) {
			$this->set( 14 - $i, 8, $g( $i ) );
		}
		for ( $i = 0; $i < 8; $i++ ) {
			$this->set( $n - 1 - $i, 8, $g( $i ) );
		}
		for ( $i = 8; $i < 15; $i++ ) {
			$this->set( 8, $n - 15 + $i, $g( $i ) );
		}
		$this->set( 8, $n - 8, true );
	}

	private static function gf_mul( $x, $y ) {
		$z = 0;
		for ( $i = 7; $i >= 0; $i-- ) {
			$z  = ( ( $z << 1 ) ^ ( ( $z >> 7 ) * 0x11D ) ) & 0xFF;
			$z ^= ( ( $y >> $i ) & 1 ) * $x;
		}
		return $z;
	}

	private static function rs_divisor( $degree ) {
		$r = array_fill( 0, $degree, 0 );
		$r[ $degree - 1 ] = 1;
		$root = 1;
		for ( $i = 0; $i < $degree; $i++ ) {
			for ( $j = 0; $j < $degree; $j++ ) {
				$r[ $j ] = self::gf_mul( $r[ $j ], $root );
				if ( $j + 1 < $degree ) {
					$r[ $j ] ^= $r[ $j + 1 ];
				}
			}
			$root = self::gf_mul( $root, 0x02 );
		}
		return $r;
	}

	private static function rs_remainder( $data, $div ) {
		$r = array_fill( 0, count( $div ), 0 );
		foreach ( $data as $b ) {
			$f = $b ^ array_shift( $r );
			$r[] = 0;
			foreach ( $div as $i => $c ) {
				$r[ $i ] ^= self::gf_mul( $c, $f );
			}
		}
		return $r;
	}

	private function add_ecc( $ver, $data ) {
		$nb     = self::$num_blocks[ $ver ];
		$ecl    = self::$ecc_per_block[ $ver ];
		$raw    = intdiv( self::raw_modules( $ver ), 8 );
		$short  = $nb - $raw % $nb;
		$slen   = intdiv( $raw, $nb );
		$div    = self::rs_divisor( $ecl );
		$blocks = array();
		$k      = 0;
		for ( $i = 0; $i < $nb; $i++ ) {
			$dl  = $slen - $ecl + ( $i < $short ? 0 : 1 );
			$dat = array_slice( $data, $k, $dl );
			$k  += $dl;
			$ecc = self::rs_remainder( $dat, $div );
			if ( $i < $short ) {
				$dat[] = 0;
			}
			$blocks[] = array_merge( $dat, $ecc );
		}
		$out = array();
		$bl  = count( $blocks[0] );
		for ( $i = 0; $i < $bl; $i++ ) {
			foreach ( $blocks as $j => $b ) {
				if ( $i !== $slen - $ecl || $j >= $short ) {
					$out[] = $b[ $i ];
				}
			}
		}
		return $out;
	}

	private function draw_codewords( $data ) {
		$n     = $this->size;
		$i     = 0;
		$total = count( $data ) * 8;
		for ( $right = $n - 1; $right >= 1; $right -= 2 ) {
			if ( 6 === $right ) {
				$right = 5;
			}
			for ( $v = 0; $v < $n; $v++ ) {
				for ( $j = 0; $j < 2; $j++ ) {
					$x  = $right - $j;
					$up = 0 === ( ( $right + 1 ) & 2 );
					$y  = $up ? $n - 1 - $v : $v;
					if ( ! $this->func[ $y ][ $x ] && $i < $total ) {
						$this->mod[ $y ][ $x ] = (bool) ( ( $data[ $i >> 3 ] >> ( 7 - ( $i & 7 ) ) ) & 1 );
						$i++;
					}
				}
			}
		}
	}

	private function apply_mask( $m ) {
		$n = $this->size;
		for ( $y = 0; $y < $n; $y++ ) {
			for ( $x = 0; $x < $n; $x++ ) {
				if ( $this->func[ $y ][ $x ] ) {
					continue;
				}
				switch ( $m ) {
					case 0: $inv = 0 === ( $x + $y ) % 2; break;
					case 1: $inv = 0 === $y % 2; break;
					case 2: $inv = 0 === $x % 3; break;
					case 3: $inv = 0 === ( $x + $y ) % 3; break;
					case 4: $inv = 0 === ( intdiv( $x, 3 ) + intdiv( $y, 2 ) ) % 2; break;
					case 5: $inv = 0 === $x * $y % 2 + $x * $y % 3; break;
					case 6: $inv = 0 === ( $x * $y % 2 + $x * $y % 3 ) % 2; break;
					default: $inv = 0 === ( ( $x + $y ) % 2 + $x * $y % 3 ) % 2;
				}
				if ( $inv ) {
					$this->mod[ $y ][ $x ] = ! $this->mod[ $y ][ $x ];
				}
			}
		}
	}

	/** Penalty rules 1–4 of the standard (runs, boxes, finder-like patterns, balance). */
	private function penalty() {
		$n = $this->size;
		$m = $this->mod;
		$p = 0;
		$lines = array();
		for ( $y = 0; $y < $n; $y++ ) {
			$lines[] = $m[ $y ];
		}
		for ( $x = 0; $x < $n; $x++ ) {
			$col = array();
			for ( $y = 0; $y < $n; $y++ ) {
				$col[] = $m[ $y ][ $x ];
			}
			$lines[] = $col;
		}
		foreach ( $lines as $line ) {
			$run = 1;
			for ( $i = 1; $i <= $n; $i++ ) {
				if ( $i < $n && $line[ $i ] === $line[ $i - 1 ] ) {
					$run++;
					continue;
				}
				if ( $run >= 5 ) {
					$p += 3 + $run - 5;
				}
				$run = 1;
			}
			$s = '';
			foreach ( $line as $c ) {
				$s .= $c ? '1' : '0';
			}
			$s  = '0000' . $s . '0000';
			$p += 40 * ( substr_count( $s, '10111010000' ) + substr_count( $s, '00001011101' ) );
		}
		$dark = 0;
		for ( $y = 0; $y < $n; $y++ ) {
			for ( $x = 0; $x < $n; $x++ ) {
				if ( $m[ $y ][ $x ] ) {
					$dark++;
				}
				if ( $y < $n - 1 && $x < $n - 1 && $m[ $y ][ $x ] === $m[ $y ][ $x + 1 ] && $m[ $y ][ $x ] === $m[ $y + 1 ][ $x ] && $m[ $y ][ $x ] === $m[ $y + 1 ][ $x + 1 ] ) {
					$p += 3;
				}
			}
		}
		$total = $n * $n;
		$k     = (int) ceil( abs( $dark * 20 - $total * 10 ) / $total ) - 1;
		return $p + max( 0, $k ) * 10;
	}
}
