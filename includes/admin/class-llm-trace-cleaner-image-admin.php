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
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Modificar metadatos puede eliminar información de autoría o invalidar credenciales de procedencia. Usa simulación y copias de seguridad. Este módulo no elimina marcas de agua en píxeles ni garantiza indetectabilidad.', 'llm-trace-cleaner' );
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
}
