<?php
/**
 * Inspector de metadatos de imágenes (solo lectura).
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-llm-trace-cleaner-image-c2pa.php';

/**
 * Audita metadatos sin modificar el archivo.
 */
class LLM_Trace_Cleaner_Image_Inspector {

	/**
	 * Patrones heurísticos de generadores.
	 *
	 * @return array
	 */
	public static function generator_patterns() {
		return array(
			'prompt',
			'negative prompt',
			'seed',
			'sampler',
			'scheduler',
			'steps',
			'cfg scale',
			'model hash',
			'model name',
			'workflow',
			'comfyui',
			'automatic1111',
			'stable diffusion',
			'midjourney',
			'dall-e',
			'dalle',
			'openai',
			'firefly',
			'generative fill',
			'invokeai',
			'fooocus',
			'flux',
			'latent',
			'checkpoint',
			'loras',
			'controlnet',
			'digitalsourcetype',
			'trainedalgorithmicmedia',
			'compositewithtrainedalgorithmicmedia',
			'algorithmicmedia',
			'aigc',
		);
	}

	/**
	 * @param string $path Ruta absoluta validada.
	 * @param string $mime MIME.
	 * @return array
	 */
	public function inspect( $path, $mime = '' ) {
		$report = array(
			'path'            => $path,
			'mime'            => $mime,
			'extension'       => strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ),
			'filesize'        => file_exists( $path ) ? filesize( $path ) : 0,
			'width'           => 0,
			'height'          => 0,
			'engine'          => '',
			'hash_sha256'     => file_exists( $path ) ? hash_file( 'sha256', $path ) : '',
			'metadata'        => array(),
			'sensitive'       => array(),
			'generator_hints' => array(),
			'c2pa'            => LLM_Trace_Cleaner_Image_C2PA::empty_result(),
			'technical'       => array(),
			'warnings'        => array(),
			'risk_score'      => 0,
		);

		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			$report['warnings'][] = 'Archivo no legible.';
			return $report;
		}

		$size = @getimagesize( $path );
		if ( is_array( $size ) ) {
			$report['width']  = (int) $size[0];
			$report['height'] = (int) $size[1];
			if ( empty( $mime ) && ! empty( $size['mime'] ) ) {
				$report['mime'] = $size['mime'];
				$mime           = $size['mime'];
			}
		}

		$engine = LLM_Trace_Cleaner_Image_Capabilities::resolve_engine( $mime ? $mime : 'image/jpeg' );
		if ( $engine ) {
			$report['engine']   = $engine->get_name();
			$engine_data        = $engine->inspect( $path );
			$report['metadata'] = isset( $engine_data['metadata'] ) ? $engine_data['metadata'] : array();
			$report['technical'] = isset( $engine_data['technical'] ) ? $engine_data['technical'] : array();
			if ( ! empty( $engine_data['warnings'] ) ) {
				$report['warnings'] = array_merge( $report['warnings'], $engine_data['warnings'] );
			}
		} else {
			$report['warnings'][] = 'Ningún motor disponible para inspeccionar este MIME.';
			$report['metadata']   = $this->fallback_exif( $path );
		}

		$flat = $this->flatten_strings( $report['metadata'] );
		$report['generator_hints'] = $this->detect_generator_hints( $flat );
		$report['sensitive']       = $this->detect_sensitive( $report['metadata'], $flat );
		$report['c2pa']            = LLM_Trace_Cleaner_Image_C2PA::inspect_file( $path, $flat );
		$report['risk_score']      = $this->score_risk( $report );

		return $report;
	}

	/**
	 * EXIF básico sin motor.
	 *
	 * @param string $path Path.
	 * @return array
	 */
	private function fallback_exif( $path ) {
		$out = array();
		if ( function_exists( 'exif_read_data' ) ) {
			$exif = @exif_read_data( $path, null, true );
			if ( is_array( $exif ) ) {
				$out['exif'] = $exif;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $data Datos.
	 * @return array Lista de strings.
	 */
	private function flatten_strings( $data ) {
		$out = array();
		if ( is_string( $data ) ) {
			$out[] = $data;
			return $out;
		}
		if ( ! is_array( $data ) ) {
			return $out;
		}
		foreach ( $data as $value ) {
			$out = array_merge( $out, $this->flatten_strings( $value ) );
		}
		return $out;
	}

	/**
	 * @param array $strings Strings planos.
	 * @return array
	 */
	private function detect_generator_hints( array $strings ) {
		$hints    = array();
		$patterns = self::generator_patterns();
		foreach ( $strings as $str ) {
			$lower = strtolower( $str );
			foreach ( $patterns as $pattern ) {
				if ( false !== strpos( $lower, $pattern ) ) {
					$hints[] = array(
						'pattern'      => $pattern,
						'classification' => 'probable_generator_hint',
						'snippet'      => mb_substr( $str, 0, 120 ),
						'message'      => 'Posible rastro de herramienta generativa.',
					);
				}
			}
			if ( preg_match( '#[A-Za-z]:\\\\|/Users/|/home/[^/]+/#', $str ) ) {
				$hints[] = array(
					'pattern'        => 'local_path',
					'classification' => 'sensitive_metadata',
					'snippet'        => mb_substr( $str, 0, 120 ),
					'message'        => 'Posible ruta local o de usuario.',
				);
			}
		}
		return $hints;
	}

	/**
	 * @param array $metadata Metadata.
	 * @param array $flat     Strings.
	 * @return array
	 */
	private function detect_sensitive( array $metadata, array $flat ) {
		$sensitive = array();
		$blob      = strtolower( wp_json_encode( $metadata ) );

		if ( preg_match( '/gps|latitude|longitude/i', $blob ) ) {
			$sensitive[] = array(
				'type'    => 'gps',
				'message' => 'Metadatos de ubicación GPS detectados.',
			);
		}
		if ( preg_match( '/serial|bodySERIAL|lensserial/i', $blob ) ) {
			$sensitive[] = array(
				'type'    => 'serial',
				'message' => 'Posible número de serie de dispositivo.',
			);
		}
		foreach ( $flat as $str ) {
			if ( strlen( $str ) > 500 && ( false !== strpos( strtolower( $str ), 'workflow' ) || false !== strpos( $str, '{' ) ) ) {
				$sensitive[] = array(
					'type'    => 'large_unknown',
					'message' => 'Metadato desconocido grande o workflow JSON.',
				);
				break;
			}
		}
		return $sensitive;
	}

	/**
	 * @param array $report Informe.
	 * @return int
	 */
	public function score_risk( array $report ) {
		$score = 0;
		foreach ( $report['sensitive'] as $item ) {
			$type = isset( $item['type'] ) ? $item['type'] : '';
			if ( 'gps' === $type ) {
				$score += 25;
			} elseif ( 'serial' === $type ) {
				$score += 20;
			} elseif ( 'large_unknown' === $type ) {
				$score += 5;
			}
		}
		foreach ( $report['generator_hints'] as $hint ) {
			$p = isset( $hint['pattern'] ) ? $hint['pattern'] : '';
			if ( 'local_path' === $p ) {
				$score += 20;
			} elseif ( in_array( $p, array( 'prompt', 'negative prompt', 'workflow', 'comfyui', 'digitalsourcetype', 'trainedalgorithmicmedia', 'aigc' ), true ) ) {
				$score += 20;
			} elseif ( in_array( $p, array( 'seed', 'model hash', 'stable diffusion', 'midjourney', 'dall-e', 'openai', 'flux' ), true ) ) {
				$score += 10;
			} else {
				$score += 5;
			}
		}
		return (int) $score;
	}

	/**
	 * Nivel textual del riesgo.
	 *
	 * @param int $score Score.
	 * @return string
	 */
	public static function risk_level( $score ) {
		$score = (int) $score;
		if ( $score >= 60 ) {
			return 'critical';
		}
		if ( $score >= 30 ) {
			return 'high';
		}
		if ( $score >= 10 ) {
			return 'medium';
		}
		return 'low';
	}

	/**
	 * Validar latitud.
	 *
	 * @param mixed $lat Lat.
	 * @return bool
	 */
	public static function is_valid_latitude( $lat ) {
		return is_numeric( $lat ) && (float) $lat >= -90 && (float) $lat <= 90;
	}

	/**
	 * Validar longitud.
	 *
	 * @param mixed $lng Lng.
	 * @return bool
	 */
	public static function is_valid_longitude( $lng ) {
		return is_numeric( $lng ) && (float) $lng >= -180 && (float) $lng <= 180;
	}
}
