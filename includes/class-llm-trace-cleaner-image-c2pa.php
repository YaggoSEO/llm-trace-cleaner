<?php
/**
 * Detección estructural de C2PA/JUMBF en PNG y JPEG (solo lectura).
 *
 * Recorre chunks PNG y segmentos JPEG. No escanea IDAT ni el entropy de SOS
 * (evita falsos positivos por coincidencia de bytes). No valida firmas COSE
 * ni elimina credenciales.
 *
 * Contenedores según C2PA: PNG `caBX`; JPEG APP11 + JUMBF.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Parser de contenedores C2PA.
 */
class LLM_Trace_Cleaner_Image_C2PA {

	const PNG_SIG  = "\x89PNG\r\n\x1a\n";
	const JPEG_SOI = "\xFF\xD8";

	const SOFT_BINDING_WARNING = 'Quitar EXIF/XMP no elimina marcas en el píxel ni manifiestos C2PA remotos (soft-binding). Este plugin no valida firmas ni borra credenciales incrustadas; por defecto detiene el saneamiento si hay un contenedor o referencia C2PA.';

	/**
	 * Chunks PNG que son contenedor C2PA/JUMBF (confirmed).
	 *
	 * @return array
	 */
	public static function png_container_types() {
		return array( 'caBX', 'juMB', 'jumb', 'C2PA', 'C2CI', 'C2CS' );
	}

	/**
	 * @return array
	 */
	public static function empty_result() {
		return array(
			'status'               => 'not_detected',
			'confidence'           => 'none',
			'findings'             => array(),
			'message'              => '',
			'soft_binding_warning' => '',
		);
	}

	/**
	 * @param string $path         Ruta.
	 * @param array  $meta_strings Strings de metadatos ya extraídos.
	 * @return array
	 */
	public static function inspect_file( $path, array $meta_strings = array() ) {
		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
			return self::empty_result();
		}
		$bytes = file_get_contents( $path );
		if ( ! is_string( $bytes ) || '' === $bytes ) {
			return self::empty_result();
		}
		return self::inspect_bytes( $bytes, $meta_strings );
	}

	/**
	 * @param string $bytes        Contenido binario.
	 * @param array  $meta_strings Strings de metadatos.
	 * @return array
	 */
	public static function inspect_bytes( $bytes, array $meta_strings = array() ) {
		if ( ! is_string( $bytes ) ) {
			$bytes = '';
		}

		$findings = array();
		if ( 0 === strpos( $bytes, self::PNG_SIG ) ) {
			$findings = self::inspect_png( $bytes );
		} elseif ( 0 === strpos( $bytes, self::JPEG_SOI ) ) {
			$findings = self::inspect_jpeg( $bytes );
		}

		foreach ( $meta_strings as $str ) {
			if ( is_string( $str ) && '' !== $str ) {
				self::scan_meta_payload( $str, 'metadata', $findings );
			}
		}

		return self::fold( $findings );
	}

	/**
	 * @param string $data PNG completo.
	 * @return array
	 */
	private static function inspect_png( $data ) {
		$findings = array();
		$pos      = 8;
		$n        = strlen( $data );
		$containers = self::png_container_types();

		while ( $pos + 8 <= $n ) {
			$un     = unpack( 'Nlen', substr( $data, $pos, 4 ) );
			$length = isset( $un['len'] ) ? $un['len'] : 0;
			$type   = substr( $data, $pos + 4, 4 );
			$start  = $pos + 8;
			if ( $length < 0 || $start + $length + 4 > $n ) {
				break;
			}
			$payload = substr( $data, $start, $length );

			if ( in_array( $type, $containers, true ) ) {
				$findings[] = array(
					'kind'       => 'png-' . strtolower( $type ),
					'confidence' => 'confirmed',
					'detail'     => 'PNG chunk ' . $type . ' (contenedor C2PA/JUMBF)',
				);
			} elseif ( in_array( $type, array( 'tEXt', 'iTXt', 'eXIf' ), true ) ) {
				self::scan_meta_payload( $payload, 'png-' . $type, $findings );
			} elseif ( 'zTXt' === $type ) {
				$nul    = strpos( $payload, "\0" );
				$prefix = false !== $nul ? substr( $payload, 0, $nul ) : $payload;
				self::scan_meta_payload( $prefix, 'png-zTXt', $findings );
			}

			if ( 'IEND' === $type ) {
				break;
			}
			$pos = $start + $length + 4;
		}

		return $findings;
	}

	/**
	 * Recorre marcadores hasta SOS. No lee el entropy-coded scan.
	 *
	 * @param string $data JPEG completo.
	 * @return array
	 */
	private static function inspect_jpeg( $data ) {
		$findings = array();
		$i        = 2;
		$n        = strlen( $data );

		while ( $i + 1 < $n ) {
			if ( 0xFF !== ord( $data[ $i ] ) ) {
				$i++;
				continue;
			}
			while ( $i < $n && 0xFF === ord( $data[ $i ] ) ) {
				$i++;
			}
			if ( $i >= $n ) {
				break;
			}
			$marker = ord( $data[ $i ] );
			$i++;

			if ( 0xD8 === $marker || 0xD9 === $marker ) {
				continue;
			}
			if ( 0xDA === $marker ) {
				break;
			}
			if ( $marker >= 0xD0 && $marker <= 0xD7 ) {
				continue;
			}
			if ( $i + 2 > $n ) {
				break;
			}

			$un     = unpack( 'nlen', substr( $data, $i, 2 ) );
			$seglen = isset( $un['len'] ) ? $un['len'] : 0;
			if ( $seglen < 2 || $i + $seglen > $n ) {
				break;
			}
			$payload = substr( $data, $i + 2, $seglen - 2 );
			$i      += $seglen;

			if ( 0xEB === $marker ) {
				if ( self::payload_has_jumbf_or_c2pa( $payload ) ) {
					$findings[] = array(
						'kind'       => 'jpeg-app11',
						'confidence' => 'confirmed',
						'detail'     => 'JPEG APP11 con JUMBF/C2PA',
					);
				} else {
					$findings[] = array(
						'kind'       => 'jpeg-app11',
						'confidence' => 'informational',
						'detail'     => 'JPEG APP11 sin JUMBF/C2PA confirmado (puede ser JPEG XT/360)',
					);
				}
			} elseif ( in_array( $marker, array( 0xE1, 0xE2, 0xED, 0xEE ), true ) ) {
				self::scan_meta_payload( $payload, 'jpeg-app' . ( $marker - 0xE0 ), $findings );
			} elseif ( 0xFE === $marker ) {
				self::scan_meta_payload( $payload, 'jpeg-com', $findings );
			}
		}

		return $findings;
	}

	/**
	 * @param string $payload Payload APP11.
	 * @return bool
	 */
	private static function payload_has_jumbf_or_c2pa( $payload ) {
		$lower = strtolower( $payload );
		return false !== strpos( $lower, 'jumb' )
			|| false !== strpos( $lower, 'c2pa' )
			|| false !== strpos( $lower, 'contentcredentials' );
	}

	/**
	 * @param string $payload  Texto o bytes de metadatos (no IDAT/SOS).
	 * @param string $kind     Origen.
	 * @param array  $findings Findings por referencia.
	 * @return void
	 */
	private static function scan_meta_payload( $payload, $kind, array &$findings ) {
		if ( ! is_string( $payload ) || '' === $payload ) {
			return;
		}
		if ( preg_match( '/c2pa|contentcredentials|claim.?generator/i', $payload ) ) {
			$findings[] = array(
				'kind'       => $kind,
				'confidence' => 'probable',
				'detail'     => 'Referencia C2PA en metadatos (' . $kind . ')',
			);
		}
		if ( preg_match( '/soft[\s\-]?binding|hashed_ext_uri|manifest repository/i', $payload ) ) {
			$findings[] = array(
				'kind'       => 'soft-binding',
				'confidence' => 'informational',
				'detail'     => 'Indicio de soft-binding o manifiesto remoto',
			);
		}
	}

	/**
	 * @param array $findings Findings.
	 * @return array
	 */
	private static function fold( array $findings ) {
		$rank = array(
			'none'          => 0,
			'informational' => 1,
			'probable'      => 2,
			'confirmed'     => 3,
		);
		$best = 'none';
		foreach ( $findings as $finding ) {
			$c = isset( $finding['confidence'] ) ? $finding['confidence'] : 'none';
			if ( ! isset( $rank[ $c ] ) ) {
				continue;
			}
			if ( $rank[ $c ] > $rank[ $best ] ) {
				$best = $c;
			}
		}

		$status = 'not_detected';
		if ( 'confirmed' === $best ) {
			$status = 'detected_unverified';
		} elseif ( 'probable' === $best ) {
			$status = 'possibly_detected';
		}

		$message = '';
		switch ( $best ) {
			case 'confirmed':
				$message = 'Contenedor C2PA/JUMBF detectado (sin validar firma). Re-encodear puede invalidar credenciales.';
				break;
			case 'probable':
				$message = 'Referencia a C2PA en metadatos. No es un manifiesto embebido confirmado.';
				break;
			case 'informational':
				$message = 'Aviso informativo (soft-binding o APP11 no C2PA). No se detiene el saneamiento solo por esto.';
				break;
			case 'none':
				$message = '';
				break;
			default:
				$message = '';
				break;
		}

		$need_warning = in_array( $best, array( 'confirmed', 'probable' ), true );
		if ( ! $need_warning ) {
			foreach ( $findings as $finding ) {
				if ( isset( $finding['kind'] ) && 'soft-binding' === $finding['kind'] ) {
					$need_warning = true;
					break;
				}
			}
		}

		return array(
			'status'               => $status,
			'confidence'           => $best,
			'findings'             => $findings,
			'message'              => $message,
			'soft_binding_warning' => $need_warning ? self::SOFT_BINDING_WARNING : '',
		);
	}
}
