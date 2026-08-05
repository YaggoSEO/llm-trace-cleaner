<?php
/**
 * Diagnóstico de capacidades del módulo de imágenes.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Detecta Imagick/GD/ExifTool y formatos soportados.
 */
class LLM_Trace_Cleaner_Image_Capabilities {

	/**
	 * @return array
	 */
	public static function detect() {
		$imagick = extension_loaded( 'imagick' ) && class_exists( 'Imagick' );
		$gd      = extension_loaded( 'gd' ) && function_exists( 'imagecreatefromjpeg' );

		$settings = class_exists( 'LLM_Trace_Cleaner_Image_Manager' )
			? LLM_Trace_Cleaner_Image_Manager::settings()
			: self::default_settings();

		$exiftool_info = false;
		if ( ! empty( $settings['exiftool_enabled'] ) && ! empty( $settings['exiftool_path'] ) && class_exists( 'LLM_Trace_Cleaner_Image_Engine_Exiftool' ) ) {
			$exiftool_info = LLM_Trace_Cleaner_Image_Engine_Exiftool::probe( $settings['exiftool_path'] );
		}
		$exiftool = (bool) $exiftool_info;

		$mimes_list = array( 'image/jpeg', 'image/png', 'image/webp' );
		if ( ! empty( $settings['allow_avif'] ) ) {
			$mimes_list[] = 'image/avif';
		}

		$mimes = array();
		foreach ( $mimes_list as $mime ) {
			$mimes[ $mime ] = array(
				'imagick'  => $imagick && self::imagick_supports_mime( $mime ),
				'gd'       => $gd && self::gd_supports_mime( $mime ),
				'exiftool' => $exiftool,
			);
		}

		$preferred = 'none';
		if ( $imagick ) {
			$preferred = 'imagick';
		} elseif ( $gd ) {
			$preferred = 'gd';
		}

		$avif = ( $imagick && self::imagick_supports_mime( 'image/avif' ) ) || $exiftool;

		return array(
			'imagick'          => $imagick,
			'gd'               => $gd,
			'exiftool'         => $exiftool,
			'exiftool_version' => $exiftool_info ? $exiftool_info['version'] : '',
			'preferred_engine' => $preferred,
			'mimes'            => $mimes,
			'cleanup_only'     => ! $imagick && $gd && ! $exiftool,
			'rich_write'       => $exiftool || $imagick,
			'avif'             => $avif,
			'webp_imagick'     => $imagick && self::imagick_supports_mime( 'image/webp' ),
			'webp_gd'          => $gd && self::gd_supports_mime( 'image/webp' ),
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
			'image/avif' => 'AVIF',
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
	 * Motor para strip/re-encode.
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
	 * Motor preferente para escritura rica.
	 *
	 * @param string $mime MIME.
	 * @return LLM_Trace_Cleaner_Image_Engine_Interface|null
	 */
	public static function resolve_write_engine( $mime ) {
		$settings = LLM_Trace_Cleaner_Image_Manager::settings();
		if ( ! empty( $settings['exiftool_enabled'] ) && class_exists( 'LLM_Trace_Cleaner_Image_Engine_Exiftool' ) ) {
			$et = new LLM_Trace_Cleaner_Image_Engine_Exiftool();
			if ( $et->is_available() && $et->supports( $mime ) ) {
				return $et;
			}
		}
		$engine = self::resolve_engine( $mime );
		if ( $engine && empty( $engine->get_capabilities()['cleanup_only'] ) ) {
			return $engine;
		}
		return $engine;
	}

	/**
	 * Settings por defecto.
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
			'allow_avif'                  => false,
			'preserve_icc'                => true,
			'preserve_orientation'        => true,
			'preserve_copyright'          => true,
			'preserve_c2pa'               => true,
			'stop_on_c2pa'                => true,
			'exiftool_enabled'            => false,
			'exiftool_path'               => '/usr/bin/exiftool',
			'exiftool_timeout'            => 30,
			'delete_backups_on_uninstall' => false,
			'wp_field_sync'               => 'set_if_empty',
			'conditional_rules'           => array(),
		);
	}
}
