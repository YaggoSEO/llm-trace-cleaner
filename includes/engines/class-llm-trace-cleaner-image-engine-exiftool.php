<?php
/**
 * Motor ExifTool (opcional).
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lectura/escritura avanzada vía binario ExifTool.
 */
class LLM_Trace_Cleaner_Image_Engine_Exiftool implements LLM_Trace_Cleaner_Image_Engine_Interface {

	/**
	 * @var string
	 */
	private $binary = '';

	/**
	 * @var int
	 */
	private $timeout = 30;

	/**
	 * Constructor.
	 *
	 * @param string $binary Path.
	 * @param int    $timeout Timeout.
	 */
	public function __construct( $binary = '', $timeout = 30 ) {
		$settings = LLM_Trace_Cleaner_Image_Manager::settings();
		$this->binary  = $binary ? $binary : ( isset( $settings['exiftool_path'] ) ? $settings['exiftool_path'] : '' );
		$this->timeout = $timeout > 0 ? (int) $timeout : ( isset( $settings['exiftool_timeout'] ) ? (int) $settings['exiftool_timeout'] : 30 );
	}

	/**
	 * @return string
	 */
	public function get_name() {
		return 'exiftool';
	}

	/**
	 * @return bool
	 */
	public function is_available() {
		$settings = LLM_Trace_Cleaner_Image_Manager::settings();
		if ( empty( $settings['exiftool_enabled'] ) ) {
			return false;
		}
		return (bool) self::probe( $this->binary );
	}

	/**
	 * Probar binario.
	 *
	 * @param string $path Path.
	 * @return array|false {version, path} o false.
	 */
	public static function probe( $path ) {
		$path = trim( (string) $path );
		if ( '' === $path || false !== strpos( $path, "\0" ) ) {
			return false;
		}
		// Solo rutas absolutas.
		if ( ! preg_match( '#^(/|[A-Za-z]:[\\\\/])#', $path ) ) {
			return false;
		}
		if ( ! file_exists( $path ) || ! is_executable( $path ) ) {
			return false;
		}
		$result = self::run_static( $path, array( '-ver' ), 10 );
		if ( empty( $result['ok'] ) || empty( $result['stdout'] ) ) {
			return false;
		}
		$ver = trim( $result['stdout'] );
		if ( ! preg_match( '/^\d+(\.\d+)*/', $ver ) ) {
			return false;
		}
		return array(
			'path'    => $path,
			'version' => $ver,
		);
	}

	/**
	 * @param string $mime MIME.
	 * @return bool
	 */
	public function supports( $mime ) {
		$allowed = array( 'image/jpeg', 'image/png', 'image/webp', 'image/avif' );
		return in_array( $mime, $allowed, true ) && $this->is_available();
	}

	/**
	 * @return array
	 */
	public function get_capabilities() {
		return array(
			'audit'           => true,
			'reencode'        => false,
			'strip_exif'      => true,
			'strip_xmp'       => true,
			'preserve_icc'    => true,
			'write_copyright' => true,
			'write_location'  => true,
			'cleanup_only'    => false,
			'rich_write'      => true,
		);
	}

	/**
	 * @param string $path Path.
	 * @return array
	 */
	public function inspect( $path ) {
		$result = array(
			'metadata'  => array(),
			'technical' => array( 'engine' => 'exiftool' ),
			'warnings'  => array(),
		);
		$validated = LLM_Trace_Cleaner_Image_Manager::validate_path( $path );
		if ( is_wp_error( $validated ) ) {
			$result['warnings'][] = $validated->get_error_message();
			return $result;
		}
		$run = $this->run( array( '-json', '-a', '-G1', '-s', $validated ) );
		if ( empty( $run['ok'] ) ) {
			$result['warnings'][] = 'ExifTool inspect falló.';
			return $result;
		}
		$decoded = json_decode( $run['stdout'], true );
		if ( is_array( $decoded ) && isset( $decoded[0] ) && is_array( $decoded[0] ) ) {
			$result['metadata']['exiftool'] = $decoded[0];
		}
		return $result;
	}

	/**
	 * @param string $source Source.
	 * @param string $destination Dest.
	 * @param array  $plan Plan.
	 * @return array
	 */
	public function sanitize( $source, $destination, array $plan ) {
		$out = array(
			'success'  => false,
			'removed'  => array(),
			'written'  => array(),
			'warnings' => isset( $plan['warnings'] ) ? $plan['warnings'] : array(),
		);
		$src = LLM_Trace_Cleaner_Image_Manager::validate_path( $source );
		if ( is_wp_error( $src ) ) {
			$out['warnings'][] = $src->get_error_message();
			return $out;
		}

		// Copiar a destino y limpiar in-place sobre la copia.
		if ( ! @copy( $src, $destination ) ) {
			$out['warnings'][] = 'No se pudo copiar para ExifTool.';
			return $out;
		}
		$dest = LLM_Trace_Cleaner_Image_Manager::validate_path( $destination );
		if ( is_wp_error( $dest ) ) {
			@unlink( $destination );
			$out['warnings'][] = $dest->get_error_message();
			return $out;
		}

		$args = array( '-overwrite_original', '-all=' );
		$preserve = isset( $plan['preserve'] ) ? $plan['preserve'] : array();
		if ( in_array( 'icc', $preserve, true ) ) {
			$args[] = '--icc_profile:all';
		}
		if ( in_array( 'copyright', $preserve, true ) || in_array( 'rights', $preserve, true ) ) {
			$args[] = '--Copyright';
			$args[] = '--IPTC:CopyrightNotice';
		}
		$args[] = $dest;

		$run = $this->run( $args );
		$out['removed'][] = 'exiftool_strip';
		$out['success']   = ! empty( $run['ok'] ) && file_exists( $dest ) && filesize( $dest ) > 0;
		if ( ! $out['success'] ) {
			$out['warnings'][] = 'ExifTool sanitize falló.';
			@unlink( $destination );
		}

		// Aplicar set del plan.
		if ( $out['success'] && ! empty( $plan['set'] ) ) {
			$writer = new LLM_Trace_Cleaner_Image_Metadata_Writer();
			$model  = $writer->model_from_plan( $plan );
			$write  = $this->write_metadata( $dest, $model );
			$out['written']  = isset( $write['written'] ) ? $write['written'] : array();
			$out['warnings'] = array_merge( $out['warnings'], isset( $write['warnings'] ) ? $write['warnings'] : array() );
		}

		return $out;
	}

	/**
	 * @param string $path Path.
	 * @param array  $metadata Meta.
	 * @return array
	 */
	public function write_metadata( $path, array $metadata ) {
		$out = array(
			'success'            => false,
			'written'            => array(),
			'unsupported_fields' => array(),
			'warnings'           => array(),
		);
		$validated = LLM_Trace_Cleaner_Image_Manager::validate_path( $path );
		if ( is_wp_error( $validated ) ) {
			$out['warnings'][] = $validated->get_error_message();
			return $out;
		}
		$mapped = LLM_Trace_Cleaner_Image_Tag_Map::to_exiftool_args( $metadata );
		if ( empty( $mapped['args'] ) ) {
			$out['success']            = true;
			$out['unsupported_fields'] = $mapped['skipped'];
			return $out;
		}
		$args   = array_merge( array( '-overwrite_original' ), $mapped['args'], array( $validated ) );
		$run    = $this->run( $args );
		$out['written']            = $mapped['written'];
		$out['unsupported_fields'] = $mapped['skipped'];
		$out['success']            = ! empty( $run['ok'] );
		if ( ! $out['success'] ) {
			$out['warnings'][] = 'ExifTool write_metadata falló.';
		}
		return $out;
	}

	/**
	 * Ejecutar ExifTool.
	 *
	 * @param array $args Args (sin binario).
	 * @return array
	 */
	private function run( array $args ) {
		return self::run_static( $this->binary, $args, $this->timeout );
	}

	/**
	 * @param string $binary Binary.
	 * @param array  $args Args.
	 * @param int    $timeout Timeout.
	 * @return array
	 */
	private static function run_static( $binary, array $args, $timeout ) {
		if ( ! function_exists( 'proc_open' ) ) {
			return array( 'ok' => false, 'stdout' => '', 'stderr' => 'proc_open disabled', 'code' => -1 );
		}
		$cmd = array_merge( array( $binary ), $args );
		// Escapar para shell string (Windows/Unix).
		$parts = array();
		foreach ( $cmd as $part ) {
			$parts[] = escapeshellarg( $part );
		}
		$line = implode( ' ', $parts );

		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);
		$process = @proc_open( $line, $descriptors, $pipes, null, null );
		if ( ! is_resource( $process ) ) {
			return array( 'ok' => false, 'stdout' => '', 'stderr' => 'proc_open failed', 'code' => -1 );
		}
		fclose( $pipes[0] );
		stream_set_blocking( $pipes[1], false );
		stream_set_blocking( $pipes[2], false );

		$stdout  = '';
		$stderr  = '';
		$start   = time();
		$max_out = 1024 * 1024;

		do {
			$status  = proc_get_status( $process );
			$stdout .= stream_get_contents( $pipes[1] );
			$stderr .= stream_get_contents( $pipes[2] );
			if ( strlen( $stdout ) > $max_out ) {
				$stdout = substr( $stdout, 0, $max_out );
			}
			if ( strlen( $stderr ) > $max_out ) {
				$stderr = substr( $stderr, 0, $max_out );
			}
			if ( ! $status['running'] ) {
				break;
			}
			if ( ( time() - $start ) >= $timeout ) {
				proc_terminate( $process );
				fclose( $pipes[1] );
				fclose( $pipes[2] );
				proc_close( $process );
				return array( 'ok' => false, 'stdout' => $stdout, 'stderr' => 'timeout', 'code' => -1 );
			}
			usleep( 50000 );
		} while ( true );

		$stdout .= stream_get_contents( $pipes[1] );
		$stderr .= stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$code = proc_close( $process );

		return array(
			'ok'     => ( 0 === (int) $code ),
			'stdout' => $stdout,
			'stderr' => $stderr,
			'code'   => (int) $code,
		);
	}
}
