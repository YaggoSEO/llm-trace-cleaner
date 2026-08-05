<?php
/**
 * Motor GD (fallback cleanup_only).
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Re-encode elimina la mayoría de metadatos; sin escritura rica.
 */
class LLM_Trace_Cleaner_Image_Engine_Gd implements LLM_Trace_Cleaner_Image_Engine_Interface {

	/**
	 * @return bool
	 */
	public function is_available() {
		return extension_loaded( 'gd' );
	}

	/**
	 * @param string $mime MIME.
	 * @return bool
	 */
	public function supports( $mime ) {
		return LLM_Trace_Cleaner_Image_Capabilities::gd_supports_mime( $mime );
	}

	/**
	 * @return string
	 */
	public function get_name() {
		return 'gd';
	}

	/**
	 * @return array
	 */
	public function get_capabilities() {
		return array(
			'audit'           => false,
			'reencode'        => true,
			'strip_exif'      => true,
			'strip_xmp'       => true,
			'preserve_icc'    => false,
			'write_copyright' => false,
			'write_location'  => false,
			'cleanup_only'    => true,
			'rich_write'      => false,
		);
	}

	/**
	 * @param string $path Path.
	 * @return array
	 */
	public function inspect( $path ) {
		$result = array(
			'metadata'  => array(),
			'technical' => array( 'engine' => 'gd', 'note' => 'Auditoría limitada con GD.' ),
			'warnings'  => array( 'GD solo ofrece inspección parcial; use Imagick para auditoría completa.' ),
		);
		if ( function_exists( 'exif_read_data' ) ) {
			$exif = @exif_read_data( $path, null, true );
			if ( is_array( $exif ) ) {
				$result['metadata']['exif'] = $exif;
			}
		}
		$size = @getimagesize( $path );
		if ( is_array( $size ) ) {
			$result['technical']['width']  = $size[0];
			$result['technical']['height'] = $size[1];
			$result['technical']['mime']   = isset( $size['mime'] ) ? $size['mime'] : '';
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
			'removed'  => array( 'metadata_via_reencode' ),
			'written'  => array(),
			'warnings' => array( 'GD es cleanup_only: no escribe autoría/copyright/ubicación estructurada.' ),
		);

		if ( ! empty( $plan['set'] ) ) {
			$out['warnings'][] = 'Se ignoraron campos set porque GD no soporta escritura rica.';
		}

		$info = @getimagesize( $source );
		if ( ! is_array( $info ) || empty( $info['mime'] ) ) {
			$out['warnings'][] = 'No se pudo leer la imagen con GD.';
			return $out;
		}
		$mime = $info['mime'];
		$im   = null;

		switch ( $mime ) {
			case 'image/jpeg':
				$im = @imagecreatefromjpeg( $source );
				break;
			case 'image/png':
				$im = @imagecreatefrompng( $source );
				break;
			case 'image/webp':
				if ( function_exists( 'imagecreatefromwebp' ) ) {
					$im = @imagecreatefromwebp( $source );
				}
				break;
		}

		if ( ! $im ) {
			$out['warnings'][] = 'GD no pudo abrir la imagen.';
			return $out;
		}

		if ( 'image/png' === $mime ) {
			imagealphablending( $im, false );
			imagesavealpha( $im, true );
		}

		$ok = false;
		switch ( $mime ) {
			case 'image/jpeg':
				$ok = imagejpeg( $im, $destination, 90 );
				break;
			case 'image/png':
				$ok = imagepng( $im, $destination, 6 );
				break;
			case 'image/webp':
				$ok = function_exists( 'imagewebp' ) ? imagewebp( $im, $destination, 90 ) : false;
				break;
		}
		imagedestroy( $im );

		$out['success'] = $ok && file_exists( $destination ) && filesize( $destination ) > 0;
		return $out;
	}

	/**
	 * @param string $path Path.
	 * @param array  $metadata Meta.
	 * @return array
	 */
	public function write_metadata( $path, array $metadata ) {
		return array(
			'success'  => false,
			'removed'  => array(),
			'written'  => array(),
			'warnings' => array( 'GD no soporta write_metadata rico.' ),
		);
	}
}
