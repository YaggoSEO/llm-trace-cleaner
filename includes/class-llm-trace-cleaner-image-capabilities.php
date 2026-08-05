<?php
/**
 * Diagnóstico de capacidades del módulo de imágenes.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Detecta Imagick/GD y formatos soportados.
 */
class LLM_Trace_Cleaner_Image_Capabilities {

	/**
	 * @return array
	 */
	public static function detect() {
		$imagick = extension_loaded( 'imagick' ) && class_exists( 'Imagick' );
		$gd      = extension_loaded( 'gd' ) && function_exists( 'imagecreatefromjpeg' );

		$mimes = array();
		foreach ( array( 'image/jpeg', 'image/png', 'image/webp' ) as $mime ) {
			$mimes[ $mime ] = array(
				'imagick' => $imagick && self::imagick_supports_mime( $mime ),
				'gd'      => $gd && self::gd_supports_mime( $mime ),
			);
		}

		$preferred = 'none';
		if ( $imagick ) {
			$preferred = 'imagick';
		} elseif ( $gd ) {
			$preferred = 'gd';
		}

		return array(
			'imagick'           => $imagick,
			'gd'                => $gd,
			'preferred_engine'  => $preferred,
			'mimes'             => $mimes,
			'cleanup_only'      => ! $imagick && $gd,
			'rich_write'        => $imagick,
			'exiftool'          => false,
			'webp_imagick'      => $imagick && self::imagick_supports_mime( 'image/webp' ),
			'webp_gd'           => $gd && self::gd_supports_mime( 'image/webp' ),
		);
	}

	/**
	 * @param string $mime MIME.
	 * @return bool
	 */
	public static function imagick_supports_mime( $mime ) {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			return false;
		}
		$map = array(
			'image/jpeg' => 'JPEG',
			'image/png'  => 'PNG',
			'image/webp' => 'WEBP',
		);
		if ( ! isset( $map[ $mime ] ) ) {
			return false;
		}
		try {
			$formats = array_map( 'strtoupper', Imagick::queryFormats( $map[ $mime ] ) );
			return ! empty( $formats );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * @param string $mime MIME.
	 * @return bool
	 */
	public static function gd_supports_mime( $mime ) {
		if ( ! extension_loaded( 'gd' ) ) {
			return false;
		}
		switch ( $mime ) {
			case 'image/jpeg':
				return function_exists( 'imagecreatefromjpeg' ) && function_exists( 'imagejpeg' );
			case 'image/png':
				return function_exists( 'imagecreatefrompng' ) && function_exists( 'imagepng' );
			case 'image/webp':
				return function_exists( 'imagecreatefromwebp' ) && function_exists( 'imagewebp' );
			default:
				return false;
		}
	}

	/**
	 * Resuelve el mejor motor disponible para un MIME.
	 *
	 * @param string $mime MIME.
	 * @return LLM_Trace_Cleaner_Image_Engine_Interface|null
	 */
	public static function resolve_engine( $mime ) {
		$caps = self::detect();

		if ( $caps['imagick'] && ! empty( $caps['mimes'][ $mime ]['imagick'] ) ) {
			$engine = new LLM_Trace_Cleaner_Image_Engine_Imagick();
			if ( $engine->is_available() && $engine->supports( $mime ) ) {
				return $engine;
			}
		}

		if ( $caps['gd'] && ! empty( $caps['mimes'][ $mime ]['gd'] ) ) {
			$engine = new LLM_Trace_Cleaner_Image_Engine_Gd();
			if ( $engine->is_available() && $engine->supports( $mime ) ) {
				return $engine;
			}
		}

		return null;
	}

	/**
	 * Settings por defecto del módulo.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'enabled'                     => false,
			'auto_process_uploads'        => false,
			'default_profile'             => 'privacy',
			'dry_run'                     => true,
			'strict_mode'                 => false,
			'backup_enabled'              => true,
			'backup_retention_days'       => 30,
			'backup_max_storage_mb'       => 1024,
			'process_subsizes'            => 'large_only',
			'batch_size'                  => 5,
			'allowed_mimes'               => array( 'image/jpeg', 'image/png', 'image/webp' ),
			'preserve_icc'                => true,
			'preserve_orientation'        => true,
			'preserve_copyright'          => true,
			'preserve_c2pa'               => true,
			'stop_on_c2pa'                => true,
			'exiftool_enabled'            => false,
			'exiftool_path'               => '',
			'delete_backups_on_uninstall' => false,
			'wp_field_sync'               => 'set_if_empty',
		);
	}
}
