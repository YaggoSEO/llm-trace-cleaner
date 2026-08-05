<?php
/**
 * Orquestador del módulo de imágenes.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manager principal.
 */
class LLM_Trace_Cleaner_Image_Manager {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Flag anti-loop.
	 *
	 * @var bool
	 */
	private $processing = false;

	/**
	 * Flag restauración.
	 *
	 * @var bool
	 */
	private $restoring = false;

	const META_HASH    = '_llmtc_image_last_hash';
	const META_PROFILE = '_llmtc_image_last_profile';
	const META_AT      = '_llmtc_image_last_processed_at';
	const META_STATUS  = '_llmtc_image_status';
	const OPTION       = 'llm_trace_cleaner_image_settings';

	/**
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->register_hooks();
	}

	/**
	 * Settings.
	 *
	 * @return array
	 */
	public static function settings() {
		$defaults = LLM_Trace_Cleaner_Image_Capabilities::default_settings();
		$stored   = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( $defaults, $stored );
	}

	/**
	 * Registrar hooks.
	 */
	public function register_hooks() {
		$settings = self::settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		if ( ! empty( $settings['auto_process_uploads'] ) ) {
			add_filter( 'wp_handle_upload', array( $this, 'process_uploaded_file' ), 20, 2 );
		}
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'process_generated_metadata' ), 20, 3 );
	}

	/**
	 * Validar path dentro de uploads.
	 *
	 * @param string $path Path.
	 * @return string|WP_Error Path real.
	 */
	public static function validate_path( $path ) {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'upload_dir', $uploads['error'] );
		}
		$base = realpath( $uploads['basedir'] );
		$real = realpath( $path );
		if ( false === $base || false === $real ) {
			return new WP_Error( 'invalid_path', 'Ruta inválida.' );
		}
		if ( 0 !== strpos( $real, $base ) ) {
			return new WP_Error( 'path_outside', 'La ruta está fuera de uploads.' );
		}
		if ( preg_match( '#^(php|phar|data|expect):#i', $path ) ) {
			return new WP_Error( 'wrapper', 'Wrapper PHP no permitido.' );
		}
		return $real;
	}

	/**
	 * @param string $path Path.
	 * @param string $mime MIME.
	 * @return bool
	 */
	public function should_process( $path, $mime ) {
		$settings = self::settings();
		$allowed  = isset( $settings['allowed_mimes'] ) ? $settings['allowed_mimes'] : array();
		if ( ! in_array( $mime, $allowed, true ) ) {
			return false;
		}
		if ( 'image/gif' === $mime ) {
			return false;
		}
		if ( ! file_exists( $path ) ) {
			return false;
		}
		// GIF animado / no imagen.
		$info = @getimagesize( $path );
		if ( ! is_array( $info ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @param array $upload Upload.
	 * @param string $context Context.
	 * @return array
	 */
	public function process_uploaded_file( $upload, $context = 'upload' ) {
		if ( $this->processing || $this->restoring || ! is_array( $upload ) || empty( $upload['file'] ) ) {
			return $upload;
		}
		$mime = isset( $upload['type'] ) ? $upload['type'] : '';
		$path = $upload['file'];
		if ( ! $this->should_process( $path, $mime ) ) {
			return $upload;
		}
		$result = $this->process_file( $path, array( 'mime' => $mime, 'source' => 'upload' ) );
		if ( is_wp_error( $result ) && ! empty( self::settings()['strict_mode'] ) ) {
			$upload['error'] = $result->get_error_message();
		}
		return $upload;
	}

	/**
	 * @param array $metadata Meta.
	 * @param int   $attachment_id ID.
	 * @param string $context Context.
	 * @return array
	 */
	public function process_generated_metadata( $metadata, $attachment_id, $context = 'create' ) {
		if ( $this->processing || $this->restoring || ! is_array( $metadata ) ) {
			return $metadata;
		}
		$settings = self::settings();
		if ( empty( $settings['enabled'] ) || empty( $settings['auto_process_uploads'] ) ) {
			return $metadata;
		}
		$this->process_attachment( (int) $attachment_id, array( 'from_metadata' => true ) );
		$this->process_subsizes( (int) $attachment_id, $metadata );
		return $metadata;
	}

	/**
	 * @param int   $attachment_id ID.
	 * @param array $metadata Meta WP.
	 * @return array
	 */
	public function process_subsizes( $attachment_id, array $metadata ) {
		$settings = self::settings();
		$mode     = isset( $settings['process_subsizes'] ) ? $settings['process_subsizes'] : 'large_only';
		if ( 'none' === $mode || empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
			return array();
		}
		$file = get_attached_file( $attachment_id );
		if ( ! $file ) {
			return array();
		}
		$dir     = trailingslashit( dirname( $file ) );
		$results = array();
		foreach ( $metadata['sizes'] as $size => $info ) {
			if ( 'large_only' === $mode && ! in_array( $size, array( 'large', 'medium_large' ), true ) ) {
				continue;
			}
			if ( empty( $info['file'] ) ) {
				continue;
			}
			$path = $dir . $info['file'];
			$mime = isset( $info['mime-type'] ) ? $info['mime-type'] : get_post_mime_type( $attachment_id );
			$results[ $size ] = $this->process_file(
				$path,
				array(
					'mime'          => $mime,
					'attachment_id' => $attachment_id,
					'subsize'       => $size,
					'skip_backup'   => true,
					'light_meta'    => true,
				)
			);
		}
		return $results;
	}

	/**
	 * @param int $attachment_id ID.
	 * @return array
	 */
	public function audit_attachment( $attachment_id ) {
		$path = get_attached_file( $attachment_id );
		if ( ! $path ) {
			return array( 'error' => 'unsupported_remote_storage' );
		}
		$validated = self::validate_path( $path );
		if ( is_wp_error( $validated ) ) {
			return array( 'error' => $validated->get_error_message() );
		}
		$mime      = get_post_mime_type( $attachment_id );
		$inspector = new LLM_Trace_Cleaner_Image_Inspector();
		$report    = $inspector->inspect( $validated, $mime );
		LLM_Trace_Cleaner_Image_Logger::log(
			array(
				'attachment_id' => $attachment_id,
				'action'        => 'audit',
				'status'        => 'ok',
				'engine'        => $report['engine'],
				'original_hash' => $report['hash_sha256'],
				'original_size' => $report['filesize'],
				'details'       => array(
					'risk_score' => $report['risk_score'],
					'c2pa'       => $report['c2pa']['status'],
				),
			)
		);
		return $report;
	}

	/**
	 * @param int   $attachment_id ID.
	 * @param array $args Args.
	 * @return array|WP_Error
	 */
	public function process_attachment( $attachment_id, array $args = array() ) {
		if ( $this->processing ) {
			return array( 'skipped' => true, 'reason' => 'already_processing' );
		}
		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! file_exists( $path ) ) {
			return new WP_Error( 'unsupported_remote_storage', 'Sin ruta local.' );
		}
		$args['attachment_id'] = $attachment_id;
		$args['mime']          = get_post_mime_type( $attachment_id );
		return $this->process_file( $path, $args );
	}

	/**
	 * @param string $path Path.
	 * @param array  $context Context.
	 * @return array|WP_Error
	 */
	public function process_file( $path, array $context = array() ) {
		$settings = self::settings();
		$validated = self::validate_path( $path );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		$path = $validated;
		$mime = isset( $context['mime'] ) ? $context['mime'] : '';
		if ( ! $mime ) {
			$info = @getimagesize( $path );
			$mime = is_array( $info ) && ! empty( $info['mime'] ) ? $info['mime'] : '';
		}
		if ( ! $this->should_process( $path, $mime ) ) {
			return array( 'skipped' => true, 'reason' => 'mime_not_allowed' );
		}

		$attachment_id = isset( $context['attachment_id'] ) ? (int) $context['attachment_id'] : 0;
		$profile_id    = ! empty( $context['profile'] ) ? $context['profile'] : $settings['default_profile'];
		$profile       = LLM_Trace_Cleaner_Image_Profile::get( $profile_id );
		if ( ! $profile ) {
			return new WP_Error( 'profile', 'Perfil no encontrado.' );
		}

		$dry_run = array_key_exists( 'dry_run', $context ) ? (bool) $context['dry_run'] : ! empty( $settings['dry_run'] );
		$hash    = hash_file( 'sha256', $path );

		if ( $attachment_id && empty( $context['force'] ) ) {
			$last_hash    = get_post_meta( $attachment_id, self::META_HASH, true );
			$last_profile = get_post_meta( $attachment_id, self::META_PROFILE, true );
			if ( $last_hash === $hash && $last_profile === $profile_id && empty( $context['subsize'] ) ) {
				return array( 'skipped' => true, 'reason' => 'unchanged' );
			}
		}

		$this->processing = true;
		$inspector        = new LLM_Trace_Cleaner_Image_Inspector();
		$audit            = $inspector->inspect( $path, $mime );
		$sanitizer        = new LLM_Trace_Cleaner_Image_Sanitizer();
		$plan             = $sanitizer->build_plan( $audit, $profile, $settings );

		if ( ! empty( $plan['blocked'] ) ) {
			$this->processing = false;
			LLM_Trace_Cleaner_Image_Logger::log(
				array(
					'attachment_id' => $attachment_id,
					'action'        => 'sanitize',
					'status'        => 'blocked_c2pa',
					'profile'       => $profile_id,
					'warnings'      => $plan['warnings'],
					'original_hash' => $hash,
				)
			);
			return array(
				'success' => false,
				'blocked' => true,
				'audit'   => $audit,
				'plan'    => $plan,
			);
		}

		if ( $dry_run ) {
			$this->processing = false;
			LLM_Trace_Cleaner_Image_Logger::log(
				array(
					'attachment_id' => $attachment_id,
					'action'        => 'dry_run',
					'status'        => 'ok',
					'profile'       => $profile_id,
					'engine'        => $audit['engine'],
					'original_hash' => $hash,
					'details'       => array(
						'remove' => $plan['remove'],
						'set'    => count( $plan['set'] ),
					),
					'warnings'      => $plan['warnings'],
				)
			);
			return array(
				'success' => true,
				'dry_run' => true,
				'audit'   => $audit,
				'plan'    => $plan,
			);
		}

		$engine = LLM_Trace_Cleaner_Image_Capabilities::resolve_engine( $mime );
		if ( ! $engine ) {
			$this->processing = false;
			return new WP_Error( 'no_engine', 'No hay motor disponible.' );
		}

		if ( $attachment_id && empty( $context['skip_backup'] ) && ! empty( $settings['backup_enabled'] ) ) {
			$backup = LLM_Trace_Cleaner_Image_Backup::create( $attachment_id, $path );
			if ( is_wp_error( $backup ) ) {
				$this->processing = false;
				return $backup;
			}
		}

		$tmp = $path . '.llmtc.' . wp_generate_password( 6, false ) . '.tmp';
		$res = $engine->sanitize( $path, $tmp, $plan );

		if ( empty( $res['success'] ) || ! file_exists( $tmp ) || filesize( $tmp ) <= 0 ) {
			@unlink( $tmp );
			$this->processing = false;
			LLM_Trace_Cleaner_Image_Logger::log(
				array(
					'attachment_id' => $attachment_id,
					'action'        => 'error',
					'status'        => 'write_failed',
					'profile'       => $profile_id,
					'engine'        => $engine->get_name(),
					'warnings'      => isset( $res['warnings'] ) ? $res['warnings'] : array(),
				)
			);
			return new WP_Error( 'write_failed', 'Fallo al escribir imagen procesada.' );
		}

		$check = @getimagesize( $tmp );
		if ( ! is_array( $check ) || (int) $check[0] !== (int) $audit['width'] && (int) $audit['width'] > 0 ) {
			// Permitir orientación que intercambie dims; solo fallar si no abre.
			if ( ! is_array( $check ) ) {
				@unlink( $tmp );
				$this->processing = false;
				return new WP_Error( 'invalid_output', 'Salida no es una imagen válida.' );
			}
		}

		if ( ! @rename( $tmp, $path ) ) {
			@unlink( $tmp );
			$this->processing = false;
			return new WP_Error( 'atomic_rename', 'No se pudo reemplazar el archivo.' );
		}

		$new_hash = hash_file( 'sha256', $path );
		if ( $attachment_id && empty( $context['subsize'] ) ) {
			update_post_meta( $attachment_id, self::META_HASH, $new_hash );
			update_post_meta( $attachment_id, self::META_PROFILE, $profile_id );
			update_post_meta( $attachment_id, self::META_AT, current_time( 'mysql' ) );
			update_post_meta( $attachment_id, self::META_STATUS, 'processed' );

			if ( empty( $context['light_meta'] ) ) {
				$this->maybe_sync_wp_fields( $attachment_id, $profile, $settings );
			}
		}

		LLM_Trace_Cleaner_Image_Logger::log(
			array(
				'attachment_id'  => $attachment_id,
				'action'         => isset( $context['action'] ) ? $context['action'] : 'sanitize',
				'status'         => 'ok',
				'profile'        => $profile_id,
				'engine'         => $engine->get_name(),
				'original_hash'  => $hash,
				'result_hash'    => $new_hash,
				'original_size'  => $audit['filesize'],
				'result_size'    => filesize( $path ),
				'removed_count'  => count( $plan['remove'] ),
				'written_count'  => isset( $res['written'] ) ? count( $res['written'] ) : 0,
				'warnings'       => isset( $res['warnings'] ) ? $res['warnings'] : array(),
			)
		);

		$this->processing = false;
		return array(
			'success' => true,
			'dry_run' => false,
			'audit'   => $audit,
			'plan'    => $plan,
			'result'  => $res,
			'hash'    => $new_hash,
		);
	}

	/**
	 * Sync campos WP ↔ metadatos (básico).
	 *
	 * @param int   $attachment_id ID.
	 * @param array $profile Profile.
	 * @param array $settings Settings.
	 */
	private function maybe_sync_wp_fields( $attachment_id, array $profile, array $settings ) {
		$mode = isset( $settings['wp_field_sync'] ) ? $settings['wp_field_sync'] : 'set_if_empty';
		if ( 'none' === $mode || empty( $profile['metadata'] ) ) {
			return;
		}
		$meta = $profile['metadata'];
		if ( ! empty( $meta['content']['title'] ) ) {
			$post = get_post( $attachment_id );
			if ( $post && ( 'overwrite' === $mode || ( 'set_if_empty' === $mode && '' === $post->post_title ) ) ) {
				wp_update_post(
					array(
						'ID'         => $attachment_id,
						'post_title' => sanitize_text_field( $meta['content']['title'] ),
					)
				);
			}
		}
		if ( ! empty( $meta['content']['description'] ) ) {
			$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			if ( 'overwrite' === $mode || ( 'set_if_empty' === $mode && '' === $alt ) ) {
				if ( ! empty( $meta['content']['alt_text'] ) ) {
					update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $meta['content']['alt_text'] ) );
				}
			}
			$post = get_post( $attachment_id );
			if ( $post && ( 'overwrite' === $mode || ( 'set_if_empty' === $mode && '' === $post->post_content ) ) ) {
				wp_update_post(
					array(
						'ID'           => $attachment_id,
						'post_content' => sanitize_textarea_field( $meta['content']['description'] ),
					)
				);
			}
		}
	}

	/**
	 * @param int   $attachment_id ID.
	 * @param array $args Args.
	 * @return array|WP_Error
	 */
	public function restore_attachment( $attachment_id, array $args = array() ) {
		$this->restoring = true;
		$result          = LLM_Trace_Cleaner_Image_Backup::restore( $attachment_id, ! empty( $args['regen_thumbs'] ) );
		delete_post_meta( $attachment_id, self::META_HASH );
		delete_post_meta( $attachment_id, self::META_PROFILE );
		update_post_meta( $attachment_id, self::META_STATUS, 'restored' );
		$this->restoring = false;
		return $result;
	}
}
