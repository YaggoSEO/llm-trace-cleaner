<?php
/**
 * Motor Imagick.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Motor preferente: strip, re-encode, ICC, orientación.
 */
class LLM_Trace_Cleaner_Image_Engine_Imagick implements LLM_Trace_Cleaner_Image_Engine_Interface {

	/**
	 * @return bool
	 */
	public function is_available() {
		return extension_loaded( 'imagick' ) && class_exists( 'Imagick' );
	}

	/**
	 * @param string $mime MIME.
	 * @return bool
	 */
	public function supports( $mime ) {
		return LLM_Trace_Cleaner_Image_Capabilities::imagick_supports_mime( $mime );
	}

	/**
	 * @return string
	 */
	public function get_name() {
		return 'imagick';
	}

	/**
	 * @return array
	 */
	public function get_capabilities() {
		return array(
			'audit'           => true,
			'reencode'        => true,
			'strip_exif'      => true,
			'strip_xmp'       => true,
			'preserve_icc'    => true,
			'write_copyright' => true,
			'write_location'  => false,
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
			'technical' => array(),
			'warnings'  => array(),
		);
		if ( ! $this->is_available() ) {
			$result['warnings'][] = 'Imagick no disponible.';
			return $result;
		}
		try {
			$img = new Imagick( $path );
			$result['technical'] = array(
				'format'      => $img->getImageFormat(),
				'colorspace'  => (string) $img->getImageColorspace(),
				'compression' => (string) $img->getImageCompression(),
				'has_icc'     => false,
			);
			$profiles = $img->getImageProfiles( '*', false );
			if ( is_array( $profiles ) ) {
				$result['technical']['profiles'] = $profiles;
				$result['technical']['has_icc']  = in_array( 'icc', $profiles, true ) || in_array( 'icm', $profiles, true );
			}
			$props = $img->getImageProperties( '*' );
			if ( is_array( $props ) ) {
				$result['metadata']['properties'] = $props;
			}
			if ( function_exists( 'exif_read_data' ) ) {
				$exif = @exif_read_data( $path, null, true );
				if ( is_array( $exif ) ) {
					$result['metadata']['exif'] = $exif;
				}
			}
			$img->clear();
			$img->destroy();
		} catch ( Exception $e ) {
			$result['warnings'][] = 'Imagick inspect: ' . $e->getMessage();
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
		if ( ! $this->is_available() ) {
			$out['warnings'][] = 'Imagick no disponible.';
			return $out;
		}
		try {
			$img = new Imagick( $source );

			$icc = null;
			$preserve_icc = in_array( 'icc', isset( $plan['preserve'] ) ? $plan['preserve'] : array(), true );
			if ( $preserve_icc ) {
				try {
					$icc = $img->getImageProfile( 'icc' );
				} catch ( Exception $e ) {
					$icc = null;
				}
			}

			if ( in_array( 'orientation', isset( $plan['preserve'] ) ? $plan['preserve'] : array(), true ) ) {
				if ( method_exists( $img, 'autoOrientImage' ) ) {
					$img->autoOrientImage();
				}
			}

			$img->stripImage();
			$out['removed'][] = 'all_embedded_profiles_and_properties';

			if ( $preserve_icc && ! empty( $icc ) ) {
				$img->setImageProfile( 'icc', $icc );
				$out['written'][] = 'icc';
			}

			if ( ! empty( $plan['set'] ) && is_array( $plan['set'] ) ) {
				foreach ( $plan['set'] as $item ) {
					$data = isset( $item['data'] ) ? $item['data'] : array();
					$this->apply_simple_props( $img, $data, $out );
				}
			}

			$format = strtoupper( $img->getImageFormat() );
			if ( in_array( $format, array( 'JPEG', 'JPG' ), true ) ) {
				$img->setImageCompression( Imagick::COMPRESSION_JPEG );
				$img->setImageCompressionQuality( 90 );
			}

			$img->writeImage( $destination );
			$img->clear();
			$img->destroy();
			$out['success'] = file_exists( $destination ) && filesize( $destination ) > 0;
		} catch ( Exception $e ) {
			$out['warnings'][] = 'Imagick sanitize: ' . $e->getMessage();
			$out['success']    = false;
		}
		return $out;
	}

	/**
	 * @param string $path Path.
	 * @param array  $metadata Meta.
	 * @return array
	 */
	public function write_metadata( $path, array $metadata ) {
		$mapped = LLM_Trace_Cleaner_Image_Tag_Map::to_imagick_props( $metadata );
		$out    = array(
			'success'            => false,
			'written'            => array(),
			'unsupported_fields' => $mapped['unsupported'],
			'warnings'           => array(),
		);
		if ( empty( $mapped['props'] ) ) {
			$out['success']  = true;
			$out['warnings'][] = 'Imagick: sin propiedades simples que escribir.';
			return $out;
		}
		try {
			$img = new Imagick( $path );
			foreach ( $mapped['props'] as $key => $value ) {
				$img->setImageProperty( $key, $value );
				$out['written'][] = $key;
			}
			$img->writeImage( $path );
			$img->clear();
			$img->destroy();
			$out['success'] = true;
		} catch ( Exception $e ) {
			$out['warnings'][] = 'Imagick write: ' . $e->getMessage();
		}
		return $out;
	}

	/**
	 * Escritura limitada de propiedades.
	 *
	 * @param Imagick $img Image.
	 * @param array   $data Data.
	 * @param array   $out Out ref.
	 */
	private function apply_simple_props( Imagick $img, array $data, array &$out ) {
		if ( ! empty( $data['rights']['copyright'] ) ) {
			$img->setImageProperty( 'exif:Copyright', (string) $data['rights']['copyright'] );
			$out['written'][] = 'rights.copyright';
		}
		if ( ! empty( $data['creator']['name'] ) ) {
			$img->setImageProperty( 'exif:Artist', (string) $data['creator']['name'] );
			$out['written'][] = 'creator.name';
		}
		if ( ! empty( $data['content']['description'] ) ) {
			$img->setImageProperty( 'exif:ImageDescription', (string) $data['content']['description'] );
			$out['written'][] = 'content.description';
		}
		if ( ! empty( $data['content']['title'] ) ) {
			$img->setImageProperty( 'exif:ImageDescription', (string) $data['content']['title'] );
			$out['written'][] = 'content.title';
		}
	}
}
