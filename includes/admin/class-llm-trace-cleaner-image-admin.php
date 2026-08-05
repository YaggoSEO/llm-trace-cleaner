<?php
/**
 * Administración del módulo de imágenes.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

/**
 * UI y AJAX del módulo.
 */
class LLM_Trace_Cleaner_Image_Admin {

	/**
	 * @var self|null
	 */
	private static $instance = null;

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
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_action( 'wp_ajax_llmtc_image_audit', array( $this, 'ajax_audit' ) );
		add_action( 'wp_ajax_llmtc_image_process', array( $this, 'ajax_process' ) );
		add_action( 'wp_ajax_llmtc_image_restore', array( $this, 'ajax_restore' ) );
		add_action( 'wp_ajax_llmtc_image_batch_start', array( $this, 'ajax_batch_start' ) );
		add_action( 'wp_ajax_llmtc_image_batch_tick', array( $this, 'ajax_batch_tick' ) );
		add_action( 'wp_ajax_llmtc_image_batch_cancel', array( $this, 'ajax_batch_cancel' ) );
		add_action( 'wp_ajax_llmtc_image_save_profile', array( $this, 'ajax_save_profile' ) );
		add_action( 'wp_ajax_llmtc_image_duplicate_profile', array( $this, 'ajax_duplicate_profile' ) );
		add_action( 'wp_ajax_llmtc_image_import_profiles', array( $this, 'ajax_import_profiles' ) );
		add_action( 'wp_ajax_llmtc_image_export_profiles', array( $this, 'ajax_export_profiles' ) );
		add_action( 'wp_ajax_llmtc_image_test_exiftool', array( $this, 'ajax_test_exiftool' ) );
		add_action( 'wp_ajax_llmtc_image_export_report', array( $this, 'ajax_export_report' ) );

		add_filter( 'media_row_actions', array( $this, 'media_row_actions' ), 10, 2 );
	}

	/**
	 * Menú.
	 */
	public function add_menu() {
		add_submenu_page(
			'llm-trace-cleaner',
			__( 'Imágenes', 'llm-trace-cleaner' ),
			__( 'Imágenes', 'llm-trace-cleaner' ),
			'manage_options',
			'llm-trace-cleaner-images',
			array( $this, 'render_page' )
		);
	}

	/**
	 * @param string $hook Hook.
	 */
	public function enqueue( $hook ) {
		if ( false === strpos( $hook, 'llm-trace-cleaner-images' ) ) {
			return;
		}
		wp_enqueue_style(
			'llmtc-images-admin',
			LLM_TRACE_CLEANER_PLUGIN_URL . 'assets/css/llm-trace-cleaner-images-admin.css',
			array(),
			LLM_TRACE_CLEANER_VERSION
		);
		wp_enqueue_script(
			'llmtc-images-admin',
			LLM_TRACE_CLEANER_PLUGIN_URL . 'assets/js/llm-trace-cleaner-images-admin.js',
			array( 'jquery' ),
			LLM_TRACE_CLEANER_VERSION,
			true
		);
		wp_localize_script(
			'llmtc-images-admin',
			'llmtcImages',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonces'  => array(
					'audit'   => wp_create_nonce( 'llmtc_image_audit' ),
					'process' => wp_create_nonce( 'llmtc_image_process' ),
					'restore' => wp_create_nonce( 'llmtc_image_restore' ),
					'batch'   => wp_create_nonce( 'llmtc_image_batch' ),
					'profile' => wp_create_nonce( 'llmtc_image_profile' ),
					'settings'=> wp_create_nonce( 'llmtc_image_settings' ),
				),
			)
		);
	}

	/**
	 * Registrar settings.
	 */
	public function register_settings() {
		register_setting(
			'llmtc_image_settings_group',
			LLM_Trace_Cleaner_Image_Manager::OPTION,
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * @param array $input Input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$defaults = LLM_Trace_Cleaner_Image_Capabilities::default_settings();
		$out      = $defaults;
		if ( ! is_array( $input ) ) {
			return $out;
		}
		$out['enabled']              = ! empty( $input['enabled'] );
		$out['auto_process_uploads'] = ! empty( $input['auto_process_uploads'] );
		$out['dry_run']              = ! empty( $input['dry_run'] );
		$out['strict_mode']          = ! empty( $input['strict_mode'] );
		$out['backup_enabled']       = ! empty( $input['backup_enabled'] );
		$out['preserve_icc']         = ! empty( $input['preserve_icc'] );
		$out['preserve_orientation'] = ! empty( $input['preserve_orientation'] );
		$out['preserve_copyright']   = ! empty( $input['preserve_copyright'] );
		$out['preserve_c2pa']        = ! empty( $input['preserve_c2pa'] );
		$out['stop_on_c2pa']         = ! empty( $input['stop_on_c2pa'] );
		$out['default_profile']      = sanitize_key( isset( $input['default_profile'] ) ? $input['default_profile'] : 'privacy' );
		$out['process_subsizes']     = in_array( $input['process_subsizes'] ?? '', array( 'none', 'large_only', 'all' ), true )
			? $input['process_subsizes']
			: 'large_only';
		$out['batch_size']           = max( 1, min( 25, absint( $input['batch_size'] ?? 5 ) ) );
		$out['backup_retention_days']= max( 1, absint( $input['backup_retention_days'] ?? 30 ) );
		$out['backup_max_storage_mb']= max( 10, absint( $input['backup_max_storage_mb'] ?? 1024 ) );
		$out['wp_field_sync']        = sanitize_key( $input['wp_field_sync'] ?? 'set_if_empty' );
		$out['exiftool_enabled']     = ! empty( $input['exiftool_enabled'] );
		$out['exiftool_path']        = isset( $input['exiftool_path'] ) ? sanitize_text_field( $input['exiftool_path'] ) : '';
		$out['exiftool_timeout']     = max( 5, min( 120, absint( $input['exiftool_timeout'] ?? 30 ) ) );
		$out['allow_avif']           = ! empty( $input['allow_avif'] );
		$out['allowed_mimes']        = array( 'image/jpeg', 'image/png', 'image/webp' );
		if ( $out['allow_avif'] ) {
			$out['allowed_mimes'][] = 'image/avif';
		}

		$rules_raw = isset( $input['conditional_rules'] ) ? $input['conditional_rules'] : '';
		$rules     = array();
		if ( is_string( $rules_raw ) && '' !== $rules_raw ) {
			$decoded = json_decode( wp_unslash( $rules_raw ), true );
			if ( is_array( $decoded ) ) {
				$rules_raw = $decoded;
			}
		}
		if ( is_array( $rules_raw ) ) {
			foreach ( $rules_raw as $rule ) {
				if ( ! is_array( $rule ) || empty( $rule['profile'] ) ) {
					continue;
				}
				$rules[] = array(
					'profile'   => sanitize_key( $rule['profile'] ),
					'mime'      => isset( $rule['mime'] ) ? sanitize_text_field( $rule['mime'] ) : '',
					'folder'    => isset( $rule['folder'] ) ? sanitize_text_field( $rule['folder'] ) : '',
					'max_bytes' => isset( $rule['max_bytes'] ) ? absint( $rule['max_bytes'] ) : 0,
					'author'    => isset( $rule['author'] ) ? absint( $rule['author'] ) : 0,
				);
			}
		}
		$out['conditional_rules'] = $rules;
		return $out;
	}

	/**
	 * Render página.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'status';
		$allowed = array( 'status', 'settings', 'profiles', 'scanner', 'report', 'logs' );
		if ( ! in_array( $tab, $allowed, true ) ) {
			$tab = 'status';
		}

		echo '<div class="wrap llmtc-images-wrap">';
		echo '<h1>' . esc_html__( 'LLM Trace Cleaner — Imágenes', 'llm-trace-cleaner' ) . '</h1>';

		// Bloque explicativo (mismo estilo que Configuración HTML).
		echo '<div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px 20px; margin: 20px 0; border-radius: 4px;">';
		echo '<h3 style="margin-top: 0; color: #2271b1;">' . esc_html__( '¿Cómo funciona el módulo de imágenes?', 'llm-trace-cleaner' ) . '</h3>';
		echo '<p style="margin-bottom: 10px;">';
		echo esc_html__( 'Este módulo audita y sanea metadatos de imágenes (JPEG, PNG y WebP): EXIF, IPTC, XMP y propiedades internas. Sirve para privacidad, normalización y autoría legítima, no para falsificar la procedencia de una imagen.', 'llm-trace-cleaner' );
		echo '</p>';
		echo '<p style="margin-bottom: 10px;">' . esc_html__( 'Puede:', 'llm-trace-cleaner' ) . '</p>';
		echo '<ul style="margin-left: 20px; margin-bottom: 10px;">';
		echo '<li>' . esc_html__( 'Detectar GPS, datos de dispositivo, software, prompts/workflows y otros rastros sensibles', 'llm-trace-cleaner' ) . '</li>';
		echo '<li>' . esc_html__( 'Aplicar perfiles (privacidad, corporativo, SEO local, fotografía, imagen generada/editada con IA)', 'llm-trace-cleaner' ) . '</li>';
		echo '<li>' . esc_html__( 'Simular cambios (dry run), crear copias de seguridad y restaurar el original', 'llm-trace-cleaner' ) . '</li>';
		echo '<li>' . esc_html__( 'Procesar adjuntos individuales o por lotes desde la biblioteca multimedia', 'llm-trace-cleaner' ) . '</li>';
		echo '</ul>';
		echo '<p style="margin-bottom: 10px;"><strong>' . esc_html__( 'Motores:', 'llm-trace-cleaner' ) . '</strong> ';
		echo esc_html__( 'Imagick es preferente (auditoría y escritura parcial). GD solo limpia por re-encode y no escribe autoría/ubicación estructurada.', 'llm-trace-cleaner' );
		echo '</p>';
		echo '<p style="margin-bottom: 0; padding-top: 10px; border-top: 1px solid #c3c4c7;"><strong>' . esc_html__( 'Importante:', 'llm-trace-cleaner' ) . '</strong> ';
		echo esc_html__( 'No elimina marcas de agua en píxeles ni garantiza indetectabilidad. Distingue ubicación creada de ubicación mostrada; no inventa cámara ni GPS. Si detecta posible C2PA, detiene el procesamiento por defecto.', 'llm-trace-cleaner' );
		echo '</p>';
		echo '</div>';

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Modificar metadatos puede eliminar información de autoría o invalidar credenciales de procedencia. Usa simulación y copias de seguridad antes de procesar en producción.', 'llm-trace-cleaner' );
		echo '</p></div>';

		$base = admin_url( 'admin.php?page=llm-trace-cleaner-images' );
		$tabs = array(
			'status'   => __( 'Estado', 'llm-trace-cleaner' ),
			'settings' => __( 'Ajustes', 'llm-trace-cleaner' ),
			'profiles' => __( 'Perfiles', 'llm-trace-cleaner' ),
			'scanner'  => __( 'Escáner', 'llm-trace-cleaner' ),
			'report'   => __( 'Informe', 'llm-trace-cleaner' ),
			'logs'     => __( 'Registros', 'llm-trace-cleaner' ),
		);
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			$class = ( $tab === $key ) ? ' nav-tab-active' : '';
			printf(
				'<a class="nav-tab%s" href="%s">%s</a>',
				esc_attr( $class ),
				esc_url( add_query_arg( 'tab', $key, $base ) ),
				esc_html( $label )
			);
		}
		echo '</h2>';

		$view = LLM_TRACE_CLEANER_PLUGIN_DIR . 'includes/admin/views/image-' . $tab . '.php';
		if ( file_exists( $view ) ) {
			include $view;
		}
		echo '</div>';
	}

	/**
	 * @param array   $actions Actions.
	 * @param WP_Post $post Post.
	 * @return array
	 */
	public function media_row_actions( $actions, $post ) {
		if ( 'attachment' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}
		if ( 0 !== strpos( $post->post_mime_type, 'image/' ) ) {
			return $actions;
		}
		$url = admin_url( 'admin.php?page=llm-trace-cleaner-images&tab=report&attachment_id=' . (int) $post->ID );
		$actions['llmtc_audit'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Auditar metadatos', 'llm-trace-cleaner' ) . '</a>';
		return $actions;
	}

	/**
	 * Cap check media.
	 *
	 * @param int $attachment_id ID.
	 * @return bool
	 */
	private function can_edit_attachment( $attachment_id ) {
		return current_user_can( 'upload_files' ) && current_user_can( 'edit_post', $attachment_id );
	}

	/**
	 * AJAX audit.
	 */
	public function ajax_audit() {
		check_ajax_referer( 'llmtc_image_audit', 'nonce' );
		$id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( ! $id || ! $this->can_edit_attachment( $id ) ) {
			wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
		}
		$manager = LLM_Trace_Cleaner_Image_Manager::get_instance();
		wp_send_json_success( $manager->audit_attachment( $id ) );
	}

	/**
	 * AJAX process.
	 */
	public function ajax_process() {
		check_ajax_referer( 'llmtc_image_process', 'nonce' );
		$id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( ! $id || ! $this->can_edit_attachment( $id ) ) {
			wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
		}
		$profile = isset( $_POST['profile'] ) ? sanitize_key( wp_unslash( $_POST['profile'] ) ) : '';
		$dry     = ! empty( $_POST['dry_run'] );
		$force   = ! empty( $_POST['force'] );
		$manager = LLM_Trace_Cleaner_Image_Manager::get_instance();
		$result  = $manager->process_attachment(
			$id,
			array(
				'profile' => $profile ? $profile : null,
				'dry_run' => $dry,
				'force'   => $force,
				'action'  => 'apply_profile',
			)
		);
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( $result );
	}

	/**
	 * AJAX restore.
	 */
	public function ajax_restore() {
		check_ajax_referer( 'llmtc_image_restore', 'nonce' );
		$id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( ! $id || ! $this->can_edit_attachment( $id ) ) {
			wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
		}
		$manager = LLM_Trace_Cleaner_Image_Manager::get_instance();
		$result  = $manager->restore_attachment( $id, array( 'regen_thumbs' => ! empty( $_POST['regen_thumbs'] ) ) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( $result );
	}

	/**
	 * AJAX batch start.
	 */
	public function ajax_batch_start() {
		check_ajax_referer( 'llmtc_image_batch', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
		}
		$ids = array();
		if ( ! empty( $_POST['attachment_ids'] ) && is_array( $_POST['attachment_ids'] ) ) {
			$ids = array_map( 'absint', wp_unslash( $_POST['attachment_ids'] ) );
		} else {
			$ids = LLM_Trace_Cleaner_Image_Batch::query_attachment_ids(
				array(
					'limit' => isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 50,
					'mime'  => isset( $_POST['mime'] ) ? sanitize_text_field( wp_unslash( $_POST['mime'] ) ) : '',
				)
			);
		}
		$state = LLM_Trace_Cleaner_Image_Batch::start(
			$ids,
			array(
				'profile' => isset( $_POST['profile'] ) ? sanitize_key( wp_unslash( $_POST['profile'] ) ) : '',
				'dry_run' => ! empty( $_POST['dry_run'] ),
			)
		);
		wp_send_json_success( $state );
	}

	/**
	 * AJAX batch tick.
	 */
	public function ajax_batch_tick() {
		check_ajax_referer( 'llmtc_image_batch', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
		}
		$id     = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';
		$result = LLM_Trace_Cleaner_Image_Batch::process_next( $id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( $result );
	}

	/**
	 * AJAX batch cancel.
	 */
	public function ajax_batch_cancel() {
		check_ajax_referer( 'llmtc_image_batch', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
		}
		$id     = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';
		$result = LLM_Trace_Cleaner_Image_Batch::set_status( $id, 'cancelled' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( $result );
	}

	/**
	 * AJAX save profile metadata.
	 */
	public function ajax_save_profile() {
		check_ajax_referer( 'llmtc_image_profile', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
		}
		$raw = isset( $_POST['profile'] ) ? json_decode( wp_unslash( $_POST['profile'] ), true ) : null;
		if ( ! is_array( $raw ) ) {
			wp_send_json_error( array( 'message' => 'Perfil inválido.' ) );
		}
		$result = LLM_Trace_Cleaner_Image_Profile::save( $raw );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'saved' => true ) );
	}

	/**
	 * AJAX duplicate profile.
	 */
	public function ajax_duplicate_profile() {
		check_ajax_referer( 'llmtc_image_profile', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
		}
		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$new_id = isset( $_POST['new_id'] ) ? sanitize_key( wp_unslash( $_POST['new_id'] ) ) : '';
		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( ! $new_id ) {
			$new_id = $source . '_copy_' . wp_generate_password( 4, false );
		}
		$result = LLM_Trace_Cleaner_Image_Profile::duplicate( $source, $new_id, $name );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'id' => $new_id ) );
	}

	/**
	 * AJAX import profiles.
	 */
	public function ajax_import_profiles() {
		check_ajax_referer( 'llmtc_image_profile', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
		}
		$raw = isset( $_POST['json'] ) ? json_decode( wp_unslash( $_POST['json'] ), true ) : null;
		if ( ! is_array( $raw ) ) {
			wp_send_json_error( array( 'message' => 'JSON inválido.' ) );
		}
		$result = LLM_Trace_Cleaner_Image_Profile::import( $raw );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'imported' => true ) );
	}

	/**
	 * AJAX export profiles.
	 */
	public function ajax_export_profiles() {
		check_ajax_referer( 'llmtc_image_profile', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
		}
		$id = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
		wp_send_json_success( array( 'profiles' => LLM_Trace_Cleaner_Image_Profile::export( $id ? $id : null ) ) );
	}

	/**
	 * AJAX test ExifTool.
	 */
	public function ajax_test_exiftool() {
		check_ajax_referer( 'llmtc_image_settings', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
		}
		$path = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
		$probe = LLM_Trace_Cleaner_Image_Engine_Exiftool::probe( $path );
		if ( ! $probe ) {
			wp_send_json_error( array( 'message' => 'ExifTool no disponible en esa ruta (debe ser absoluta y ejecutable).' ) );
		}
		wp_send_json_success( $probe );
	}

	/**
	 * AJAX export report JSON/CSV.
	 */
	public function ajax_export_report() {
		check_ajax_referer( 'llmtc_image_audit', 'nonce' );
		$id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( ! $id || ! $this->can_edit_attachment( $id ) ) {
			wp_send_json_error( array( 'message' => 'Permiso denegado.' ), 403 );
		}
		$format = isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : 'json';
		$manager = LLM_Trace_Cleaner_Image_Manager::get_instance();
		$report  = $manager->audit_attachment( $id );
		if ( isset( $report['path'] ) ) {
			$report['path'] = basename( $report['path'] );
		}
		if ( 'csv' === $format ) {
			$lines   = array( 'key,value' );
			$flat    = array(
				'mime'       => isset( $report['mime'] ) ? $report['mime'] : '',
				'width'      => isset( $report['width'] ) ? $report['width'] : '',
				'height'     => isset( $report['height'] ) ? $report['height'] : '',
				'engine'     => isset( $report['engine'] ) ? $report['engine'] : '',
				'risk_score' => isset( $report['risk_score'] ) ? $report['risk_score'] : '',
				'c2pa'       => isset( $report['c2pa']['status'] ) ? $report['c2pa']['status'] : '',
				'hash'       => isset( $report['hash_sha256'] ) ? $report['hash_sha256'] : '',
			);
			foreach ( $flat as $k => $v ) {
				$lines[] = $k . ',' . '"' . str_replace( '"', '""', (string) $v ) . '"';
			}
			wp_send_json_success( array( 'format' => 'csv', 'content' => implode( "\n", $lines ) ) );
		}
		wp_send_json_success( array( 'format' => 'json', 'content' => wp_json_encode( $report ) ) );
	}
}
