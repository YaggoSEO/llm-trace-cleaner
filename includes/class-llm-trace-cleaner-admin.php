<?php
/**
 * Clase para manejar la interfaz de administración
 *
 * @package LLM_Trace_Cleaner
 */

defined('ABSPATH') || exit;

/**
 * Clase LLM_Trace_Cleaner_Admin
 */
class LLM_Trace_Cleaner_Admin {
    
    /**
     * Instancia única (Singleton)
     */
    private static $instance = null;
    
    /**
     * Obtener instancia única
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_form_submissions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Registrar handlers AJAX
        add_action('wp_ajax_llm_trace_cleaner_get_total', array($this, 'ajax_get_total_posts'));
        add_action('wp_ajax_llm_trace_cleaner_process_batch', array($this, 'ajax_process_batch'));
        add_action('wp_ajax_llm_trace_cleaner_get_progress', array($this, 'ajax_get_progress'));
        add_action('wp_ajax_llm_trace_cleaner_log_error', array($this, 'ajax_log_error'));
        add_action('wp_ajax_llm_trace_cleaner_analyze_content', array($this, 'ajax_analyze_content'));
        add_action('wp_ajax_llm_trace_cleaner_analyze_all_posts', array($this, 'ajax_analyze_all_posts'));
    }
    
    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        // Menú principal en la barra de administración
        add_menu_page(
            __('LLM Trace Cleaner', 'llm-trace-cleaner'),
            __('LLM Trace Cleaner', 'llm-trace-cleaner'),
            'manage_options',
            'llm-trace-cleaner',
            array($this, 'render_admin_page'),
            'dashicons-admin-tools', // Icono de herramientas (más visible)
            30 // Posición en el menú
        );
        
        // Submenú: Página principal (mismo slug que el menú principal)
        add_submenu_page(
            'llm-trace-cleaner',
            __('Configuración', 'llm-trace-cleaner'),
            __('Configuración', 'llm-trace-cleaner'),
            'manage_options',
            'llm-trace-cleaner',
            array($this, 'render_admin_page')
        );
        
        // Submenú: Depuración y Errores
        add_submenu_page(
            'llm-trace-cleaner',
            __('Depuración', 'llm-trace-cleaner'),
            __('Depuración', 'llm-trace-cleaner'),
            'manage_options',
            'llm-trace-cleaner-debug',
            array($this, 'render_debug_page')
        );
    }
    
    /**
     * Manejar envíos de formularios
     */
    public function handle_form_submissions() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Guardar configuración de limpieza automática y caché
        if (isset($_POST['llm_trace_cleaner_save_settings']) && check_admin_referer('llm_trace_cleaner_settings')) {
            $auto_clean = isset($_POST['llm_trace_cleaner_auto_clean']) ? true : false;
            update_option('llm_trace_cleaner_auto_clean', $auto_clean);
            
            // Configuración de caché
            $disable_cache = isset($_POST['llm_trace_cleaner_disable_cache']) ? true : false;
            update_option('llm_trace_cleaner_disable_cache', $disable_cache);
            
            // Bots seleccionados
            $selected_bots = isset($_POST['llm_trace_cleaner_selected_bots']) ? 
                array_map('sanitize_text_field', $_POST['llm_trace_cleaner_selected_bots']) : array();
            update_option('llm_trace_cleaner_selected_bots', $selected_bots);
            
            // Bots personalizados
            $custom_bots = isset($_POST['llm_trace_cleaner_custom_bots']) ? 
                sanitize_textarea_field($_POST['llm_trace_cleaner_custom_bots']) : '';
            update_option('llm_trace_cleaner_custom_bots', $custom_bots);
            
            // Telemetría opt-in
            $telemetry_opt_in = isset($_POST['llm_trace_cleaner_telemetry_opt_in']) ? true : false;
            update_option('llm_trace_cleaner_telemetry_opt_in', $telemetry_opt_in);
            
            // Tamaño del lote
            $batch_size = isset($_POST['llm_trace_cleaner_batch_size']) ? absint($_POST['llm_trace_cleaner_batch_size']) : 10;
            // Validar que esté entre 1 y 100
            if ($batch_size < 1) $batch_size = 1;
            if ($batch_size > 100) $batch_size = 100;
            update_option('llm_trace_cleaner_batch_size', $batch_size);
            
            update_option('llm_trace_cleaner_clean_attributes', isset($_POST['llm_trace_cleaner_clean_attributes']));
            update_option('llm_trace_cleaner_clean_unicode', isset($_POST['llm_trace_cleaner_clean_unicode']));
            
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     esc_html__('Configuración guardada correctamente.', 'llm-trace-cleaner') . 
                     '</p></div>';
            });
        }
        
        // La limpieza manual ahora se hace por AJAX, no necesitamos procesarla aquí
        
        // Vaciar log
        if (isset($_POST['llm_trace_cleaner_clear_log']) && check_admin_referer('llm_trace_cleaner_clear_log')) {
            $logger = new LLM_Trace_Cleaner_Logger();
            $logger->clear_log();
            $logger->clear_file_log();
            
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     esc_html__('Log vaciado correctamente.', 'llm-trace-cleaner') . 
                     '</p></div>';
            });
        }
        
        // Descargar archivo de log
        if (isset($_GET['llm_trace_cleaner_download_log']) && check_admin_referer('llm_trace_cleaner_download_log')) {
            $logger = new LLM_Trace_Cleaner_Logger();
            $log_content = $logger->get_file_log_content(0);
            
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="llm-trace-cleaner-' . date('Y-m-d') . '.log"');
            echo $log_content;
            exit;
        }
        
        // Descargar log de depuración
        if (isset($_GET['llm_trace_cleaner_download_debug_log']) && check_admin_referer('llm_trace_cleaner_download_debug_log')) {
            $error_logs = $this->get_error_logs();
            $debug_logs = $this->get_debug_logs();
            
            $content = "=== LOG DE DEPURACIÓN LLM TRACE CLEANER ===\n";
            $content .= "Generado: " . current_time('mysql') . "\n\n";
            
            $content .= "=== ERRORES ===\n";
            if (!empty($error_logs)) {
                foreach ($error_logs as $log) {
                    $content .= "[" . $log['datetime'] . "] " . $log['message'] . "\n";
                    if (!empty($log['context'])) {
                        $content .= "  Contexto: " . $log['context'] . "\n";
                    }
                    $content .= "\n";
                }
            } else {
                $content .= "No hay errores registrados.\n\n";
            }
            
            $content .= "\n=== LOGS DE DEPURACIÓN ===\n";
            if (!empty($debug_logs)) {
                foreach ($debug_logs as $log) {
                    $content .= "[" . $log['datetime'] . "] " . $log['message'] . "\n";
                    if (!empty($log['data'])) {
                        $content .= print_r($log['data'], true) . "\n";
                    }
                    $content .= "\n";
                }
            } else {
                $content .= "No hay logs de depuración.\n";
            }
            
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="llm-trace-cleaner-debug-' . date('Y-m-d') . '.log"');
            echo $content;
            exit;
        }
    }
    
    /**
     * Encolar scripts y estilos
     */
    public function enqueue_scripts($hook) {
        // Cargar en las páginas del plugin (configuración y depuración)
        if ($hook !== 'llm-trace-cleaner_page_llm-trace-cleaner' && 
            $hook !== 'llm-trace-cleaner_page_llm-trace-cleaner-debug' &&
            $hook !== 'toplevel_page_llm-trace-cleaner') {
            return;
        }
        
        wp_enqueue_script('jquery');
    }
    
    /**
     * AJAX: Obtener total de posts a procesar
     */
    public function ajax_get_total_posts() {
        check_ajax_referer('llm_trace_cleaner_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos.', 'llm-trace-cleaner')));
        }
        
        // Obtener TODOS los IDs de posts y páginas publicados de una vez
        // Esto evita problemas con offset y filtros de plugins
        global $wpdb;
        $post_ids = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts} 
            WHERE post_type IN ('post', 'page') 
            AND post_status = 'publish' 
            ORDER BY ID ASC"
        );
        
        $total = count($post_ids);
        
        // Inicializar el estado del proceso con todos los IDs
        $process_id = 'llm_trace_clean_' . time();
        set_transient('llm_trace_cleaner_process_' . $process_id, array(
            'total' => $total,
            'processed' => 0,
            'modified' => 0,
            'stats' => array(),
            'started' => current_time('mysql'),
            'post_ids' => $post_ids, // Guardar todos los IDs para procesamiento por lotes
        ), 7200); // 2 horas para procesos largos
        
        $this->log_debug('Proceso iniciado', array(
            'total_posts' => $total,
            'process_id' => $process_id,
            'first_10_ids' => array_slice($post_ids, 0, 10) // Solo para logging
        ));
        
        wp_send_json_success(array(
            'total' => $total,
            'process_id' => $process_id,
        ));
    }
    
    /**
     * AJAX: Procesar un lote de posts
     */
    public function ajax_process_batch() {
        check_ajax_referer('llm_trace_cleaner_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos.', 'llm-trace-cleaner')));
        }
        
        try {
            $process_id = isset($_POST['process_id']) ? sanitize_text_field($_POST['process_id']) : '';
            $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
            $batch_size = get_option('llm_trace_cleaner_batch_size', 10); // Obtener tamaño del lote desde configuración
            
            // Registrar información del sistema solo en el primer lote (offset 0)
            if ($offset === 0) {
                $this->log_debug('Información del sistema al iniciar', array(
                    'active_plugins' => $this->get_active_plugins_info(),
                    'hooks_on_save_post' => $this->get_hooks_on_save_post(),
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time')
                ));
            }
            
            $this->log_debug('Lote iniciado', array(
                'process_id' => $process_id,
                'offset' => $offset,
                'batch_size' => $batch_size,
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
                'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
                'time_limit' => ini_get('max_execution_time'),
                'memory_limit' => ini_get('memory_limit')
            ));
            
            if (empty($process_id)) {
                $this->log_error('ID de proceso inválido', 'ajax_process_batch');
                wp_send_json_error(array('message' => __('ID de proceso inválido.', 'llm-trace-cleaner')));
            }
            
            // Obtener estado del proceso
            $process_state = get_transient('llm_trace_cleaner_process_' . $process_id);
            if (!$process_state) {
                $this->log_error('Estado del proceso no encontrado', "Process ID: {$process_id}");
                wp_send_json_error(array('message' => __('Estado del proceso no encontrado.', 'llm-trace-cleaner')));
            }
            
            // Desactivar caché durante el proceso de limpieza
            LLM_Trace_Cleaner_Cache::disable_cache_for_cleaning();
            
            // Aumentar tiempo de ejecución y memoria para este lote
            @set_time_limit(120); // Aumentar a 120 segundos
            @ini_set('memory_limit', '256M'); // Aumentar memoria si es posible
            
            $cleaner = new LLM_Trace_Cleaner_Cleaner();
            $logger = new LLM_Trace_Cleaner_Logger();
            
            // Obtener los IDs que faltan por procesar usando post__in en lugar de offset
            // Esto evita problemas cuando hay filtros de plugins que afectan la consulta
            $all_post_ids = isset($process_state['post_ids']) ? $process_state['post_ids'] : array();
            $processed_count = $process_state['processed'];
            $remaining_ids = array_slice($all_post_ids, $processed_count, $batch_size);
            
            if (empty($remaining_ids)) {
                // No hay más posts, marcar como completado
                $this->log_debug('No hay más posts para procesar', array(
                    'processed' => $process_state['processed'],
                    'total' => $process_state['total']
                ));
                
                $process_state['processed'] = $process_state['total'];
                set_transient('llm_trace_cleaner_process_' . $process_id, $process_state, 7200);
                
                // Si el proceso está completo, limpiar toda la caché y enviar telemetría
                if ($process_state['processed'] >= $process_state['total']) {
                    LLM_Trace_Cleaner_Cache::clear_all_cache();
                    
                    $this->log_debug('Proceso completado', array(
                        'total' => $process_state['total'],
                        'processed' => $process_state['processed'],
                        'modified' => $process_state['modified']
                    ));
                    
                    // Telemetría anónima (opt-in, activada por defecto)
                    if (get_option('llm_trace_cleaner_telemetry_opt_in', true)) {
                        $this->send_anonymous_telemetry($process_state);
                    }
                }
                
                wp_send_json_success(array(
                    'processed' => 0,
                    'modified' => 0,
                    'total_processed' => $process_state['processed'],
                    'total_modified' => $process_state['modified'],
                    'is_complete' => $process_state['processed'] >= $process_state['total'],
                ));
            }
            
            // Usar post__in en lugar de offset para evitar problemas con filtros de plugins
            $query = new WP_Query(array(
                'post_type' => array('post', 'page'),
                'post_status' => 'publish',
                'post__in' => $remaining_ids, // Usar IDs específicos en lugar de offset
                'posts_per_page' => $batch_size,
                'fields' => 'ids',
                'orderby' => 'post__in', // Mantener el orden de los IDs
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ));
            
            $posts = $query->posts;
            
            $this->log_debug('Posts obtenidos', array(
                'count' => count($posts),
                'post_ids' => $posts,
                'offset' => $offset,
                'processed_count' => $processed_count,
                'remaining_total' => count($all_post_ids) - $processed_count
            ));
            
            $batch_modified = 0;
            $batch_stats = array();
            $batch_start_time = microtime(true);
            
            foreach ($posts as $post_id) {
                try {
                    $post_start_time = microtime(true);
                    
                    $post = get_post($post_id);
                    if (!$post) {
                        $this->log_debug('Post no encontrado', array('post_id' => $post_id));
                        continue;
                    }
                    
                    $original_content = $post->post_content;
                    
                    $clean_options = array(
                        'clean_attributes' => get_option('llm_trace_cleaner_clean_attributes', false),
                        'clean_unicode' => get_option('llm_trace_cleaner_clean_unicode', false),
                        'track_locations' => true
                    );
                    
                    // Si viene desde análisis previo, usar selección del usuario
                    if (isset($_POST['selected_clean_types'])) {
                        $selected = json_decode(stripslashes($_POST['selected_clean_types']), true);
                        $clean_options['clean_attributes'] = isset($selected['attributes']) && $selected['attributes'];
                        $clean_options['clean_unicode'] = isset($selected['unicode']) && $selected['unicode'];
                    }
                    
                    $cleaned_content = $cleaner->clean_html($original_content, $clean_options);
                    
                    if ($cleaned_content !== $original_content) {
                        // Medir tiempo de actualización
                        $update_start_time = microtime(true);
                        
                        // Actualizar el post SIN hooks para evitar bloqueos de plugins pesados
                        // Esto evita ejecutar los 50+ hooks de save_post (WPML, WooCommerce, RankMath, etc.)
                        $update_result = $this->update_post_without_hooks($post_id, $cleaned_content);
                        
                        $update_time = microtime(true) - $update_start_time;
                        
                        // Si tarda más de 2 segundos, registrar como sospechoso
                        if ($update_time > 2) {
                            $this->log_debug('Post tardó mucho en actualizar', array(
                                'post_id' => $post_id,
                                'post_title' => $post->post_title,
                                'update_time' => round($update_time, 2) . ' segundos',
                                'warning' => 'Posible interferencia de plugin'
                            ));
                        }
                        
                        if ($update_result === false) {
                            $this->log_error('Error al actualizar post en la base de datos', "Post ID: {$post_id}, Título: {$post->post_title}");
                            continue;
                        }
                        
                        // Limpiar caché del post modificado
                        LLM_Trace_Cleaner_Cache::clear_post_cache($post_id);
                        
                        $batch_modified++;
                        
                        // Obtener estadísticas y acumular
                        $stats = $cleaner->get_last_stats();
                        foreach ($stats as $attr => $count) {
                            if (!isset($batch_stats[$attr])) {
                                $batch_stats[$attr] = 0;
                            }
                            $batch_stats[$attr] += $count;
                        }
                        
                        // Registrar en el log - forzar registro si el contenido cambió (incluso sin stats)
                        $change_locations = $cleaner->get_change_locations();
                        $logger->log_action('manual', $post_id, $post->post_title, $stats, true, $original_content, $cleaned_content, $change_locations);
                    }
                    
                    $post_total_time = microtime(true) - $post_start_time;
                    
                    // Si un post tarda más de 5 segundos en total, registrar como problema
                    if ($post_total_time > 5) {
                        $this->log_error('Post procesado muy lentamente', array(
                            'post_id' => $post_id,
                            'post_title' => $post->post_title,
                            'time' => round($post_total_time, 2) . ' segundos',
                            'update_time' => isset($update_time) ? round($update_time, 2) . ' segundos' : 'N/A',
                            'active_plugins_count' => count($this->get_active_plugins_info()),
                            'suggestion' => 'Revisa plugins que interceptan save_post'
                        ));
                    }
                    
                    // Limpiar memoria después de cada post
                    unset($post);
                    unset($original_content);
                    unset($cleaned_content);
                } catch (Exception $e) {
                    $this->log_error($e->getMessage(), "Post ID: {$post_id}");
                    continue; // Continuar con el siguiente post
                }
            }
            
            $batch_total_time = microtime(true) - $batch_start_time;
            
            // Limpiar memoria
            wp_reset_postdata();
            unset($query);
            unset($cleaner);
            
            // Actualizar estado del proceso con tiempo extendido
            $process_state['processed'] += count($posts);
            $process_state['modified'] += $batch_modified;
            foreach ($batch_stats as $attr => $count) {
                if (!isset($process_state['stats'][$attr])) {
                    $process_state['stats'][$attr] = 0;
                }
                $process_state['stats'][$attr] += $count;
            }
            
            // Extender el transient a 2 horas para procesos largos
            set_transient('llm_trace_cleaner_process_' . $process_id, $process_state, 7200);
            
            $this->log_debug('Lote completado', array(
                'processed' => count($posts),
                'modified' => $batch_modified,
                'total_processed' => $process_state['processed'],
                'total_remaining' => $process_state['total'] - $process_state['processed'],
                'progress_percent' => round(($process_state['processed'] / $process_state['total']) * 100, 2) . '%',
                'batch_time' => round($batch_total_time, 2) . ' segundos',
                'avg_time_per_post' => count($posts) > 0 ? round($batch_total_time / count($posts), 2) . ' segundos' : 'N/A',
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
                'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
                'is_complete' => $process_state['processed'] >= $process_state['total']
            ));
            
            // Si el proceso está completo, limpiar toda la caché y enviar telemetría
            if ($process_state['processed'] >= $process_state['total']) {
                LLM_Trace_Cleaner_Cache::clear_all_cache();
                
                $this->log_debug('Proceso completado', array(
                    'total' => $process_state['total'],
                    'processed' => $process_state['processed'],
                    'modified' => $process_state['modified']
                ));
                
                // Telemetría anónima (opt-in, activada por defecto)
                if (get_option('llm_trace_cleaner_telemetry_opt_in', true)) {
                    $this->send_anonymous_telemetry($process_state);
                }
            }
            
            wp_send_json_success(array(
                'processed' => count($posts),
                'modified' => $batch_modified,
                'total_processed' => $process_state['processed'],
                'total_modified' => $process_state['modified'],
                'is_complete' => $process_state['processed'] >= $process_state['total'],
            ));
            
        } catch (Exception $e) {
            $this->log_error($e->getMessage(), 'ajax_process_batch');
            wp_send_json_error(array('message' => __('Error durante el procesamiento. Revisa la pestaña de Depuración para más detalles.', 'llm-trace-cleaner')));
        }
    }
    
    /**
     * Obtener endpoint de telemetría (desencriptado)
     * 
     * @return string URL del endpoint o cadena vacía si no está configurado
     */
    private function get_telemetry_endpoint() {
        // Clave de encriptación fija (no modificable)
        $key = 'llm_trace_cleaner_2024';
        
        // URL encriptada (hardcodeada, no modificable)
        $encrypted = 'BBgZLwdITkwWPBEFFRVAAh0wVVxXGg8DAHAZEwIRCixMH0ogJQMLPFBIfAY4PyArHEsMCw4rUSgmLFgRCA5bWnNfFDReaEA1AxUCKjYCVxBWVzUAe0dVcAQpXjIXISgRB2ZTNT1UORUtCl0FcxsJFAg8';
        
        if (empty($encrypted)) {
            return ''; // No configurado
        }
        
        // Desencriptar usando la clave fija
        $decoded = base64_decode($encrypted, true); // Modo estricto
        
        if ($decoded === false) {
            return ''; // Error al decodificar
        }
        
        $decrypted = '';
        $key_len = strlen($key);
        
        for ($i = 0; $i < strlen($decoded); $i++) {
            $decrypted .= chr(ord($decoded[$i]) ^ ord($key[$i % $key_len]));
        }
        
        // Validar que la URL desencriptada sea válida y HTTPS
        if (empty($decrypted) || !filter_var($decrypted, FILTER_VALIDATE_URL)) {
            return ''; // URL inválida
        }
        
        // Solo permitir HTTPS por seguridad
        if (strpos($decrypted, 'https://') !== 0) {
            return ''; // Solo HTTPS permitido
        }
        
        return $decrypted;
    }
    
    /**
     * Enviar telemetría anónima
     * 
     * @param array $process_state Estado del proceso de limpieza
     */
    private function send_anonymous_telemetry($process_state) {
        $endpoint = $this->get_telemetry_endpoint();
        
        if (empty($endpoint)) {
            return; // No hay endpoint configurado
        }
        
        // Generar o obtener install_id
        $install_id = get_option('llm_trace_cleaner_install_id');
        if (!$install_id) {
            // Intentar usar wp_generate_uuid4() si está disponible (WordPress 6.3+)
            if (function_exists('wp_generate_uuid4')) {
                $install_id = wp_generate_uuid4();
            } else {
                // Generar UUID v4 manualmente
                $install_id = sprintf(
                    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
            }
            update_option('llm_trace_cleaner_install_id', $install_id);
        }
        
        // Preparar estadísticas seguras y estructuradas
        $stats = isset($process_state['stats']) && is_array($process_state['stats']) ? $process_state['stats'] : array();
        
        // Separar atributos de caracteres Unicode
        $attributes_found = array();
        $unicode_found = array();
        $attributes_count = 0;
        $unicode_count = 0;
        $other_stats = array();
        
        foreach ($stats as $k => $v) {
            $key = substr(preg_replace('/[^a-zA-Z0-9_\-\:\(\)\*\.]/', '', (string) $k), 0, 100);
            $val = absint($v);
            
            if ($key !== '' && $val > 0) {
                // Detectar si es un atributo (data-* o id con patrón)
                if (strpos($key, 'data-') === 0 || strpos($key, 'id:') === 0) {
                    $attributes_found[$key] = $val;
                    $attributes_count += $val;
                }
                // Detectar si es un carácter Unicode
                elseif (strpos($key, 'unicode:') === 0) {
                    $unicode_found[$key] = $val;
                    $unicode_count += $val;
                }
                // Otros tipos
                else {
                    $other_stats[$key] = $val;
                }
            }
        }
        
        // Lista de tipos de atributos encontrados (para análisis)
        $attribute_types = array_keys($attributes_found);
        $unicode_types = array_keys($unicode_found);
        
        // Preparar payload con estructura detallada
        $payload = array(
            'install_id'          => $install_id,
            'plugin_version'      => defined('LLM_TRACE_CLEANER_VERSION') ? LLM_TRACE_CLEANER_VERSION : '',
            'wp_version'          => get_bloginfo('version'),
            'php_version'         => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            'total'               => absint($process_state['total']),
            'processed'           => absint($process_state['processed']),
            'modified'            => absint($process_state['modified']),
            'timestamp'           => current_time('mysql'),
            'server_timestamp'    => current_time('mysql', true), // Timestamp del servidor (UTC)
            
            // Estadísticas agregadas
            'total_attributes_removed'    => $attributes_count,
            'total_unicode_removed'       => $unicode_count,
            'unique_attribute_types'      => count($attribute_types),
            'unique_unicode_types'        => count($unicode_types),
            
            // Tipos específicos encontrados (listas para análisis)
            'attribute_types_found'       => $attribute_types,
            'unicode_types_found'          => $unicode_types,
            
            // Contadores detallados por tipo (para medias)
            'attributes_detail'           => $attributes_found,
            'unicode_detail'              => $unicode_found,
            'other_stats'                 => $other_stats,
            
            // Stats completo (compatibilidad hacia atrás)
            'stats'                      => array_merge($attributes_found, $unicode_found, $other_stats),
        );
        
        // Enviar de forma asíncrona (no bloqueante)
        wp_remote_post($endpoint, array(
            'timeout'   => 3,
            'blocking'  => false,
            'headers'   => array('Content-Type' => 'application/json'),
            'body'      => wp_json_encode($payload),
        ));
    }
    
    /**
     * Registrar un error en el log
     */
    public function log_error($message, $context = '') {
        $logs = get_option('llm_trace_cleaner_error_logs', array());
        
        $logs[] = array(
            'datetime' => current_time('mysql'),
            'message' => $message,
            'context' => $context
        );
        
        // Mantener solo los últimos 100 errores
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('llm_trace_cleaner_error_logs', $logs);
    }
    
    /**
     * Registrar información de depuración
     */
    public function log_debug($message, $data = array()) {
        $logs = get_option('llm_trace_cleaner_debug_logs', array());
        
        $logs[] = array(
            'datetime' => current_time('mysql'),
            'message' => $message,
            'data' => $data
        );
        
        // Mantener solo los últimos 50 logs de depuración
        if (count($logs) > 50) {
            $logs = array_slice($logs, -50);
        }
        
        update_option('llm_trace_cleaner_debug_logs', $logs);
    }
    
    /**
     * Obtener logs de errores
     */
    private function get_error_logs() {
        $logs = get_option('llm_trace_cleaner_error_logs', array());
        return array_reverse($logs); // Más recientes primero
    }
    
    /**
     * Obtener logs de depuración
     */
    private function get_debug_logs() {
        $logs = get_option('llm_trace_cleaner_debug_logs', array());
        return array_reverse($logs); // Más recientes primero
    }
    
    /**
     * Limpiar logs de depuración
     */
    private function clear_debug_logs() {
        delete_option('llm_trace_cleaner_error_logs');
        delete_option('llm_trace_cleaner_debug_logs');
    }
    
    /**
     * Actualizar post sin ejecutar hooks de save_post
     * Esto evita bloqueos causados por plugins pesados como WPML, WooCommerce, RankMath, etc.
     * 
     * @param int $post_id ID del post a actualizar
     * @param string $post_content Contenido nuevo del post
     * @return int|false ID del post si se actualizó correctamente, false en caso de error
     */
    private function update_post_without_hooks($post_id, $post_content) {
        global $wpdb;
        
        // Actualizar directamente en la base de datos para evitar hooks
        $result = $wpdb->update(
            $wpdb->posts,
            array(
                'post_content' => $post_content,
                'post_modified' => current_time('mysql'),
                'post_modified_gmt' => current_time('mysql', 1)
            ),
            array('ID' => $post_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Limpiar caché de WordPress
            clean_post_cache($post_id);
            
            return $post_id;
        }
        
        return false;
    }
    
    /**
     * Convertir valor de memoria a bytes para comparación
     */
    private function convert_to_bytes($val) {
        $val = trim($val);
        if (empty($val) || $val === '-1') {
            return PHP_INT_MAX; // Sin límite
        }
        
        $last = strtolower($val[strlen($val)-1]);
        $val = (int) $val;
        
        switch($last) {
            case 'g':
                $val *= 1024;
                // fall through
            case 'm':
                $val *= 1024;
                // fall through
            case 'k':
                $val *= 1024;
        }
        
        return $val;
    }
    
    /**
     * Comparar valor actual con recomendado y devolver clase CSS
     */
    private function compare_value($current, $recommended, $type = 'number') {
        if ($type === 'memory') {
            $current_bytes = $this->convert_to_bytes($current);
            $recommended_bytes = $this->convert_to_bytes($recommended);
            // Si el valor actual es ilimitado (PHP_INT_MAX), siempre es OK
            if ($current_bytes === PHP_INT_MAX) {
                return 'status-ok';
            }
            return $current_bytes >= $recommended_bytes ? 'status-ok' : 'status-warning';
        } else {
            // Para números (tiempo de ejecución, etc.)
            $current_int = (int)$current;
            $recommended_int = (int)$recommended;
            // Si el valor actual es 0, significa sin límite, por lo que es OK
            if ($current_int === 0) {
                return 'status-ok';
            }
            return $current_int >= $recommended_int ? 'status-ok' : 'status-warning';
        }
    }
    
    /**
     * Obtener información de plugins activos
     */
    private function get_active_plugins_info() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $active_plugins = get_option('active_plugins', array());
        $all_plugins = get_plugins();
        $active_plugins_info = array();
        
        foreach ($active_plugins as $plugin) {
            if (isset($all_plugins[$plugin])) {
                $active_plugins_info[] = array(
                    'name' => $all_plugins[$plugin]['Name'],
                    'path' => $plugin,
                    'version' => $all_plugins[$plugin]['Version']
                );
            }
        }
        
        return $active_plugins_info;
    }
    
    /**
     * Obtener información de callbacks en un hook
     */
    private function get_callback_info($callback) {
        if (is_string($callback)) {
            return $callback;
        } elseif (is_array($callback)) {
            if (is_object($callback[0])) {
                return get_class($callback[0]) . '::' . $callback[1];
            } else {
                return $callback[0] . '::' . $callback[1];
            }
        } elseif (is_object($callback) && ($callback instanceof Closure)) {
            return 'Closure';
        }
        return 'Unknown';
    }
    
    /**
     * Obtener hooks relacionados con save_post que podrían interferir
     */
    private function get_hooks_on_save_post() {
        global $wp_filter;
        
        $hooks_info = array();
        
        // Hooks relacionados con save_post que podrían interferir
        $related_hooks = array(
            'save_post',
            'wp_insert_post',
            'wp_insert_post_data',
            'pre_post_update',
            'post_updated',
            'edit_post'
        );
        
        foreach ($related_hooks as $hook_name) {
            if (isset($wp_filter[$hook_name])) {
                $callbacks = array();
                foreach ($wp_filter[$hook_name]->callbacks as $priority => $functions) {
                    foreach ($functions as $function) {
                        $callbacks[] = array(
                            'priority' => $priority,
                            'function' => $this->get_callback_info($function['function'])
                        );
                    }
                }
                if (!empty($callbacks)) {
                    $hooks_info[$hook_name] = $callbacks;
                }
            }
        }
        
        return $hooks_info;
    }
    
    /**
     * Renderizar página de depuración
     */
    public function render_debug_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para acceder a esta página.', 'llm-trace-cleaner'));
        }
        
        // Procesar acciones
        if (isset($_POST['llm_trace_cleaner_clear_debug_log']) && check_admin_referer('llm_trace_cleaner_clear_debug_log')) {
            $this->clear_debug_logs();
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 esc_html__('Logs de depuración eliminados.', 'llm-trace-cleaner') . 
                 '</p></div>';
        }
        
        // Obtener logs de errores
        $error_logs = $this->get_error_logs();
        $debug_logs = $this->get_debug_logs();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Depuración y Errores', 'llm-trace-cleaner'); ?></h1>
            
            <div class="llm-trace-cleaner-admin">
                <!-- Información del sistema -->
                <div class="llm-trace-cleaner-section">
                    <h2><?php echo esc_html__('Información del Sistema', 'llm-trace-cleaner'); ?></h2>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th style="width: 200px;"><?php echo esc_html__('Parámetro', 'llm-trace-cleaner'); ?></th>
                                <th><?php echo esc_html__('Valor Actual', 'llm-trace-cleaner'); ?></th>
                                <th><?php echo esc_html__('Recomendado', 'llm-trace-cleaner'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th><?php echo esc_html__('PHP Version:', 'llm-trace-cleaner'); ?></th>
                                <td><?php echo esc_html(PHP_VERSION); ?></td>
                                <td><?php echo esc_html__('7.4 o superior', 'llm-trace-cleaner'); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html__('WordPress Version:', 'llm-trace-cleaner'); ?></th>
                                <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                                <td><?php echo esc_html__('5.0 o superior', 'llm-trace-cleaner'); ?></td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html__('Plugin Version:', 'llm-trace-cleaner'); ?></th>
                                <td><?php echo esc_html(defined('LLM_TRACE_CLEANER_VERSION') ? LLM_TRACE_CLEANER_VERSION : 'N/A'); ?></td>
                                <td>-</td>
                            </tr>
                            <?php
                            $memory_limit = ini_get('memory_limit');
                            $memory_recommended = '256M';
                            $memory_class = $this->compare_value($memory_limit, $memory_recommended, 'memory');
                            ?>
                            <tr>
                                <th><?php echo esc_html__('Memory Limit:', 'llm-trace-cleaner'); ?></th>
                                <td class="<?php echo esc_attr($memory_class); ?>">
                                    <strong><?php echo esc_html($memory_limit); ?></strong>
                                </td>
                                <td><?php echo esc_html($memory_recommended); ?></td>
                            </tr>
                            <?php
                            $max_execution_time = ini_get('max_execution_time');
                            $time_recommended = 120;
                            $time_class = $this->compare_value($max_execution_time, $time_recommended, 'number');
                            ?>
                            <tr>
                                <th><?php echo esc_html__('Max Execution Time:', 'llm-trace-cleaner'); ?></th>
                                <td class="<?php echo esc_attr($time_class); ?>">
                                    <strong><?php echo esc_html($max_execution_time); ?> segundos</strong>
                                </td>
                                <td><?php echo esc_html($time_recommended); ?> segundos</td>
                            </tr>
                            <tr>
                                <th><?php echo esc_html__('DOMDocument disponible:', 'llm-trace-cleaner'); ?></th>
                                <td class="<?php echo class_exists('DOMDocument') ? 'status-ok' : 'status-warning'; ?>">
                                    <strong><?php echo class_exists('DOMDocument') ? esc_html__('Sí', 'llm-trace-cleaner') : esc_html__('No', 'llm-trace-cleaner'); ?></strong>
                                </td>
                                <td><?php echo esc_html__('Sí (recomendado)', 'llm-trace-cleaner'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="description" style="margin-top: 10px;">
                        <span class="status-ok" style="display: inline-block; width: 12px; height: 12px; background: #46b450; border-radius: 50%; margin-right: 5px; vertical-align: middle;"></span>
                        <?php echo esc_html__('Verde: Valor correcto o superior al recomendado', 'llm-trace-cleaner'); ?><br>
                        <span class="status-warning" style="display: inline-block; width: 12px; height: 12px; background: #dc3232; border-radius: 50%; margin-right: 5px; vertical-align: middle;"></span>
                        <?php echo esc_html__('Rojo: Valor inferior al recomendado (puede causar problemas)', 'llm-trace-cleaner'); ?>
                    </p>
                </div>
                
                <!-- Información de plugins y hooks -->
                <div class="llm-trace-cleaner-section">
                    <h2><?php echo esc_html__('Plugins Activos y Hooks', 'llm-trace-cleaner'); ?></h2>
                    <p class="description">
                        <?php echo esc_html__('Información sobre plugins activos y hooks que podrían interferir con el proceso de limpieza.', 'llm-trace-cleaner'); ?>
                    </p>
                    
                    <?php
                    $active_plugins = $this->get_active_plugins_info();
                    $hooks_info = $this->get_hooks_on_save_post();
                    ?>
                    
                    <h3><?php echo esc_html__('Plugins Activos', 'llm-trace-cleaner'); ?></h3>
                    <p class="description">
                        <?php echo esc_html(sprintf(__('Total: %d plugins activos', 'llm-trace-cleaner'), count($active_plugins))); ?>
                    </p>
                    
                    <?php if (!empty($active_plugins)): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 60%;"><?php echo esc_html__('Nombre del Plugin', 'llm-trace-cleaner'); ?></th>
                                    <th style="width: 20%;"><?php echo esc_html__('Versión', 'llm-trace-cleaner'); ?></th>
                                    <th style="width: 20%;"><?php echo esc_html__('Ruta', 'llm-trace-cleaner'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_plugins as $plugin): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($plugin['name']); ?></strong></td>
                                        <td><?php echo esc_html($plugin['version']); ?></td>
                                        <td><code style="font-size: 11px;"><?php echo esc_html($plugin['path']); ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php echo esc_html__('No se pudieron obtener los plugins activos.', 'llm-trace-cleaner'); ?></p>
                    <?php endif; ?>
                    
                    <h3 style="margin-top: 30px;"><?php echo esc_html__('Hooks Relacionados con save_post', 'llm-trace-cleaner'); ?></h3>
                    <p class="description">
                        <?php echo esc_html__('Estos hooks pueden interceptar el proceso de actualización de posts y causar lentitud o bloqueos.', 'llm-trace-cleaner'); ?>
                    </p>
                    
                    <?php if (!empty($hooks_info)): ?>
                        <?php foreach ($hooks_info as $hook_name => $callbacks): ?>
                            <h4 style="margin-top: 20px; margin-bottom: 10px;">
                                <code><?php echo esc_html($hook_name); ?></code>
                                <span style="font-weight: normal; color: #666;">
                                    (<?php echo esc_html(count($callbacks)); ?> <?php echo esc_html__('callback(s)', 'llm-trace-cleaner'); ?>)
                                </span>
                            </h4>
                            <table class="wp-list-table widefat fixed striped" style="margin-bottom: 20px;">
                                <thead>
                                    <tr>
                                        <th style="width: 100px;"><?php echo esc_html__('Prioridad', 'llm-trace-cleaner'); ?></th>
                                        <th><?php echo esc_html__('Función/Callback', 'llm-trace-cleaner'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($callbacks as $callback): ?>
                                        <tr>
                                            <td><code><?php echo esc_html($callback['priority']); ?></code></td>
                                            <td><code><?php echo esc_html($callback['function']); ?></code></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p><?php echo esc_html__('No se encontraron hooks relacionados con save_post.', 'llm-trace-cleaner'); ?></p>
                    <?php endif; ?>
                    
                    <div class="notice notice-info" style="margin-top: 20px;">
                        <p>
                            <strong><?php echo esc_html__('Consejo:', 'llm-trace-cleaner'); ?></strong>
                            <?php echo esc_html__('Si el proceso de limpieza se detiene o es muy lento, revisa los logs de depuración para ver qué posts tardan mucho. Luego, desactiva temporalmente los plugins que aparecen en los hooks de save_post para identificar el conflicto.', 'llm-trace-cleaner'); ?>
                        </p>
                    </div>
                </div>
                
                <!-- Logs de errores -->
                <div class="llm-trace-cleaner-section">
                    <h2><?php echo esc_html__('Logs de Errores', 'llm-trace-cleaner'); ?></h2>
                    <p class="description">
                        <?php echo esc_html__('Errores capturados durante el proceso de limpieza.', 'llm-trace-cleaner'); ?>
                    </p>
                    
                    <div style="margin-bottom: 20px;">
                        <form method="post" action="" style="display: inline-block; margin-right: 10px;">
                            <?php wp_nonce_field('llm_trace_cleaner_clear_debug_log'); ?>
                            <input type="submit" 
                                   name="llm_trace_cleaner_clear_debug_log" 
                                   class="button button-secondary" 
                                   value="<?php echo esc_attr__('Limpiar todos los logs', 'llm-trace-cleaner'); ?>"
                                   onclick="return confirm('<?php echo esc_js(__('¿Estás seguro de que quieres eliminar todos los logs de depuración?', 'llm-trace-cleaner')); ?>');">
                        </form>
                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg('llm_trace_cleaner_download_debug_log', '1'), 'llm_trace_cleaner_download_debug_log')); ?>" 
                           class="button button-secondary">
                            <?php echo esc_html__('Descargar log de depuración', 'llm-trace-cleaner'); ?>
                        </a>
                    </div>
                    
                    <?php if (!empty($error_logs)): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 150px;"><?php echo esc_html__('Fecha/Hora', 'llm-trace-cleaner'); ?></th>
                                    <th><?php echo esc_html__('Error', 'llm-trace-cleaner'); ?></th>
                                    <th style="width: 200px;"><?php echo esc_html__('Contexto', 'llm-trace-cleaner'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($error_logs as $log): ?>
                                    <tr>
                                        <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $log['datetime'])); ?></td>
                                        <td><code><?php echo esc_html($log['message']); ?></code></td>
                                        <td><?php echo esc_html($log['context']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php echo esc_html__('No hay errores registrados.', 'llm-trace-cleaner'); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Logs de depuración -->
                <div class="llm-trace-cleaner-section">
                    <h2><?php echo esc_html__('Logs de Depuración', 'llm-trace-cleaner'); ?></h2>
                    <p class="description">
                        <?php echo esc_html__('Información detallada del proceso de limpieza para diagnóstico.', 'llm-trace-cleaner'); ?>
                    </p>
                    
                    <?php if (!empty($debug_logs)): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 150px;"><?php echo esc_html__('Fecha/Hora', 'llm-trace-cleaner'); ?></th>
                                    <th><?php echo esc_html__('Mensaje', 'llm-trace-cleaner'); ?></th>
                                    <th style="width: 300px;"><?php echo esc_html__('Datos', 'llm-trace-cleaner'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($debug_logs as $log): ?>
                                    <tr>
                                        <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $log['datetime'])); ?></td>
                                        <td><?php echo esc_html($log['message']); ?></td>
                                        <td><pre style="max-width: 300px; overflow: auto; font-size: 11px; white-space: pre-wrap;"><?php echo esc_html(print_r($log['data'], true)); ?></pre></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php echo esc_html__('No hay logs de depuración.', 'llm-trace-cleaner'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX: Registrar error desde el cliente
     */
    public function ajax_log_error() {
        check_ajax_referer('llm_trace_cleaner_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos.', 'llm-trace-cleaner')));
        }
        
        $error_data = isset($_POST['error']) ? sanitize_text_field($_POST['error']) : '';
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'JavaScript AJAX';
        
        if (!empty($error_data)) {
            $this->log_error('Error AJAX desde cliente: ' . $error_data, $context);
        }
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Obtener progreso del proceso
     */
    public function ajax_get_progress() {
        check_ajax_referer('llm_trace_cleaner_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos.', 'llm-trace-cleaner')));
        }
        
        $process_id = isset($_POST['process_id']) ? sanitize_text_field($_POST['process_id']) : '';
        
        if (empty($process_id)) {
            wp_send_json_error(array('message' => __('ID de proceso inválido.', 'llm-trace-cleaner')));
        }
        
        $process_state = get_transient('llm_trace_cleaner_process_' . $process_id);
        
        if (!$process_state) {
            wp_send_json_error(array('message' => __('Estado del proceso no encontrado.', 'llm-trace-cleaner')));
        }
        
        wp_send_json_success($process_state);
    }
    
    /**
     * Renderizar página de administración
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para acceder a esta página.', 'llm-trace-cleaner'));
        }
        
        $auto_clean_enabled = get_option('llm_trace_cleaner_auto_clean', false);
        $disable_cache = get_option('llm_trace_cleaner_disable_cache', false);
        $selected_bots = get_option('llm_trace_cleaner_selected_bots', array());
        $custom_bots = get_option('llm_trace_cleaner_custom_bots', '');
        
        // Bots por defecto disponibles
        $default_bots = array(
            'chatgpt' => 'ChatGPT',
            'claude' => 'Claude',
            'bard' => 'Bard',
            'gpt' => 'GPT',
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'googlebot' => 'Googlebot',
            'bingbot' => 'Bingbot',
            'crawler' => 'Crawler',
            'spider' => 'Spider',
            'bot' => 'Bot',
            'llm' => 'LLM',
            'grok' => 'Grok',
        );
        
        $logger = new LLM_Trace_Cleaner_Logger();
        
        // Paginación
        $per_page = 50;
        $current_page = isset($_GET['log_page']) ? absint($_GET['log_page']) : 1;
        $offset = ($current_page - 1) * $per_page;
        $total_logs = $logger->get_total_logs_count();
        $total_pages = ceil($total_logs / $per_page);
        
        $recent_logs = $logger->get_recent_logs($per_page, $offset);
        $scan_result = get_transient('llm_trace_cleaner_scan_result');
        
        // Limpiar el transient después de mostrarlo
        if ($scan_result) {
            delete_transient('llm_trace_cleaner_scan_result');
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('LLM Trace Cleaner', 'llm-trace-cleaner'); ?></h1>
            
            <div class="llm-trace-cleaner-admin">
                <!-- Configuración -->
                <div class="llm-trace-cleaner-section">
                    <h2><?php echo esc_html__('Configuración', 'llm-trace-cleaner'); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field('llm_trace_cleaner_settings'); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="llm_trace_cleaner_auto_clean">
                                        <?php echo esc_html__('Limpieza automática', 'llm-trace-cleaner'); ?>
                                    </label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" 
                                               name="llm_trace_cleaner_auto_clean" 
                                               id="llm_trace_cleaner_auto_clean" 
                                               value="1" 
                                               <?php checked($auto_clean_enabled, true); ?>>
                                        <?php echo esc_html__('Activar limpieza automática al guardar entradas/páginas', 'llm-trace-cleaner'); ?>
                                    </label>
                                    <p class="description">
                                        <?php echo esc_html__('Si está activada, el contenido se limpiará automáticamente cada vez que se guarde una entrada o página.', 'llm-trace-cleaner'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="llm_trace_cleaner_disable_cache">
                                        <?php echo esc_html__('Desactivar caché para bots/LLMs', 'llm-trace-cleaner'); ?>
                                    </label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" 
                                               name="llm_trace_cleaner_disable_cache" 
                                               id="llm_trace_cleaner_disable_cache" 
                                               value="1" 
                                               <?php checked($disable_cache, true); ?>>
                                        <?php echo esc_html__('Desactivar caché cuando se detecten bots o LLMs', 'llm-trace-cleaner'); ?>
                                    </label>
                                    <p class="description">
                                        <?php echo esc_html__('Evita que los plugins de caché interfieran cuando bots o herramientas LLM acceden al sitio. Compatible con LiteSpeed Cache, WP Rocket, W3 Total Cache, WP Super Cache y NitroPack.', 'llm-trace-cleaner'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr id="llm-trace-cleaner-bots-config" style="<?php echo $disable_cache ? '' : 'display:none;'; ?>">
                                <th scope="row">
                                    <label>
                                        <?php echo esc_html__('Bots/LLMs a detectar', 'llm-trace-cleaner'); ?>
                                    </label>
                                </th>
                                <td>
                                    <fieldset>
                                        <legend class="screen-reader-text">
                                            <span><?php echo esc_html__('Seleccionar bots', 'llm-trace-cleaner'); ?></span>
                                        </legend>
                                        <div style="margin-bottom:8px;">
                                            <button type="button"
                                                    id="llm-trace-cleaner-select-all-bots"
                                                    class="button button-secondary">
                                                <?php echo esc_html__('Seleccionar todos', 'llm-trace-cleaner'); ?>
                                            </button>
                                            <button type="button"
                                                    id="llm-trace-cleaner-unselect-all-bots"
                                                    class="button" style="margin-left:8px;">
                                                <?php echo esc_html__('Deseleccionar', 'llm-trace-cleaner'); ?>
                                            </button>
                                        </div>
                                        <?php foreach ($default_bots as $bot_key => $bot_label): ?>
                                            <label style="display: block; margin-bottom: 5px;">
                                                <input type="checkbox" 
                                                       name="llm_trace_cleaner_selected_bots[]" 
                                                       value="<?php echo esc_attr($bot_key); ?>"
                                                       <?php checked(in_array($bot_key, $selected_bots), true); ?>>
                                                <?php echo esc_html($bot_label); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </fieldset>
                                    <p class="description" style="margin-top: 10px;">
                                        <?php echo esc_html__('Selecciona los bots/LLMs que quieres detectar. Si no seleccionas ninguno, se usarán todos por defecto.', 'llm-trace-cleaner'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr id="llm-trace-cleaner-custom-bots-config" style="<?php echo $disable_cache ? '' : 'display:none;'; ?>">
                                <th scope="row">
                                    <label for="llm_trace_cleaner_custom_bots">
                                        <?php echo esc_html__('Bots personalizados', 'llm-trace-cleaner'); ?>
                                    </label>
                                </th>
                                <td>
                                    <textarea name="llm_trace_cleaner_custom_bots" 
                                              id="llm_trace_cleaner_custom_bots" 
                                              rows="5" 
                                              class="large-text code"
                                              placeholder="<?php echo esc_attr__('Escribe un bot por línea, por ejemplo:&#10;mi-bot-custom&#10;otro-bot'); ?>"><?php echo esc_textarea($custom_bots); ?></textarea>
                                    <p class="description">
                                        <?php echo esc_html__('Agrega bots personalizados, uno por línea. Se buscarán en el User-Agent del visitante.', 'llm-trace-cleaner'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="llm_trace_cleaner_batch_size">
                                        <?php echo esc_html__('Posts por lote', 'llm-trace-cleaner'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="number"
                                           name="llm_trace_cleaner_batch_size"
                                           id="llm_trace_cleaner_batch_size"
                                           value="<?php echo esc_attr(get_option('llm_trace_cleaner_batch_size', 10)); ?>"
                                           min="1"
                                           max="100"
                                           step="1"
                                           class="small-text">
                                    <p class="description">
                                        <?php echo esc_html__('Número de posts a procesar por lote. Se recomienda entre 10 y 30 dependiendo del servidor. Valores más altos pueden causar timeouts en servidores con recursos limitados.', 'llm-trace-cleaner'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php echo esc_html__('Tipos de limpieza', 'llm-trace-cleaner'); ?></label>
                                </th>
                                <td>
                                    <fieldset>
                                        <label style="display: block; margin-bottom: 10px;">
                                            <input type="checkbox" 
                                                   name="llm_trace_cleaner_clean_attributes" 
                                                   id="llm_trace_cleaner_clean_attributes" 
                                                   value="1" 
                                                   <?php checked(get_option('llm_trace_cleaner_clean_attributes', false), true); ?>>
                                            <strong><?php echo esc_html__('Limpiar parámetros y atributos de rastreo', 'llm-trace-cleaner'); ?></strong>
                                        </label>
                                        <p class="description" style="margin-left: 25px; margin-top: 5px; margin-bottom: 15px;">
                                            <?php echo esc_html__('Elimina atributos como data-llm, data-start, data-end, data-offset-key, etc.', 'llm-trace-cleaner'); ?>
                                        </p>
                                        <label style="display: block;">
                                            <input type="checkbox" 
                                                   name="llm_trace_cleaner_clean_unicode" 
                                                   id="llm_trace_cleaner_clean_unicode" 
                                                   value="1" 
                                                   <?php checked(get_option('llm_trace_cleaner_clean_unicode', false), true); ?>>
                                            <strong><?php echo esc_html__('Limpiar caracteres Unicode invisibles', 'llm-trace-cleaner'); ?></strong>
                                        </label>
                                        <p class="description" style="margin-left: 25px; margin-top: 5px;">
                                            <?php echo esc_html__('Elimina caracteres invisibles como Zero Width Space, Zero Width Non-Joiner, etc.', 'llm-trace-cleaner'); ?>
                                        </p>
                                    </fieldset>
                                    <p class="description" style="margin-top: 10px;">
                                        <strong><?php echo esc_html__('Nota:', 'llm-trace-cleaner'); ?></strong> 
                                        <?php echo esc_html__('Por defecto, ambos tipos de limpieza están desactivados. Actívalos según tus necesidades. El sistema realizará un análisis previo antes de limpiar para que puedas seleccionar qué limpiar.', 'llm-trace-cleaner'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="llm_trace_cleaner_telemetry_opt_in">
                                        <?php echo esc_html__('Compartir estadísticas anónimas', 'llm-trace-cleaner'); ?>
                                    </label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                               name="llm_trace_cleaner_telemetry_opt_in"
                                               id="llm_trace_cleaner_telemetry_opt_in"
                                               value="1"
                                               <?php checked(get_option('llm_trace_cleaner_telemetry_opt_in', true), true); ?>>
                                        <?php echo esc_html__('Ayuda a mejorar el plugin enviando métricas 100% anónimas (totales y tipos de rastros) para realizar estudios e investigaciones sobre LLMs y buscadores.', 'llm-trace-cleaner'); ?>
                                    </label>
                                    <p class="description">
                                        <?php echo esc_html__('No se envían URLs, títulos, IDs de post ni ningún dato personal o sensible. Agradecemos tu colaboración en estos estudios e investigaciones.', 'llm-trace-cleaner'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" 
                                   name="llm_trace_cleaner_save_settings" 
                                   class="button button-primary" 
                                   value="<?php echo esc_attr__('Guardar configuración', 'llm-trace-cleaner'); ?>">
                        </p>
                    </form>
                </div>
                
                <!-- Limpieza manual -->
                <div class="llm-trace-cleaner-section">
                    <h2><?php echo esc_html__('Limpieza manual', 'llm-trace-cleaner'); ?></h2>
                    <p>
                        <?php echo esc_html__('Primero se realizará un análisis del contenido para identificar qué elementos se pueden limpiar. Luego podrás seleccionar qué tipos de limpieza aplicar.', 'llm-trace-cleaner'); ?>
                    </p>
                    
                    <!-- Área de análisis previo (oculta inicialmente) -->
                    <div id="llm-trace-cleaner-analysis" style="display: none; margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                        <h3><?php echo esc_html__('Análisis previo', 'llm-trace-cleaner'); ?></h3>
                        <div id="llm-trace-cleaner-analysis-content">
                            <p><?php echo esc_html__('Analizando contenido...', 'llm-trace-cleaner'); ?></p>
                        </div>
                        
                        <div id="llm-trace-cleaner-selection" style="display: none; margin-top: 20px;">
                            <h4><?php echo esc_html__('Selecciona qué limpiar:', 'llm-trace-cleaner'); ?></h4>
                            <fieldset style="margin: 15px 0;">
                                <label style="display: block; margin-bottom: 10px;">
                                    <input type="checkbox" 
                                           id="llm-trace-cleaner-select-attributes" 
                                           value="1">
                                    <strong><?php echo esc_html__('Limpiar parámetros y atributos de rastreo', 'llm-trace-cleaner'); ?></strong>
                                    <span id="llm-trace-cleaner-attributes-count" style="color: #666; margin-left: 10px;"></span>
                                </label>
                                <label style="display: block;">
                                    <input type="checkbox" 
                                           id="llm-trace-cleaner-select-unicode" 
                                           value="1">
                                    <strong><?php echo esc_html__('Limpiar caracteres Unicode invisibles', 'llm-trace-cleaner'); ?></strong>
                                    <span id="llm-trace-cleaner-unicode-count" style="color: #666; margin-left: 10px;"></span>
                                </label>
                            </fieldset>
                            <p>
                                <button type="button" 
                                        id="llm-trace-cleaner-select-all" 
                                        class="button button-secondary">
                                    <?php echo esc_html__('Seleccionar todo', 'llm-trace-cleaner'); ?>
                                </button>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Área de progreso (oculta inicialmente) -->
                    <div id="llm-trace-cleaner-progress" style="display: none; margin: 20px 0;">
                        <div class="llm-trace-cleaner-progress-bar-container">
                            <div class="llm-trace-cleaner-progress-bar">
                                <div class="llm-trace-cleaner-progress-bar-fill" id="llm-trace-cleaner-progress-fill"></div>
                            </div>
                            <div class="llm-trace-cleaner-progress-text" id="llm-trace-cleaner-progress-text">
                                0 / 0
                            </div>
                        </div>
                        <p id="llm-trace-cleaner-status-text" style="margin-top: 10px;">
                            <?php echo esc_html__('Iniciando...', 'llm-trace-cleaner'); ?>
                        </p>
                    </div>
                    
                    <!-- Resultado del escaneo -->
                    <div id="llm-trace-cleaner-result" style="display: none;"></div>
                    
                    <p class="submit">
                        <button type="button" 
                                id="llm-trace-cleaner-analyze-btn" 
                                class="button button-primary">
                            <?php echo esc_html__('Analizar contenido', 'llm-trace-cleaner'); ?>
                        </button>
                        <button type="button" 
                                id="llm-trace-cleaner-start-btn" 
                                class="button button-secondary"
                                style="display: none;">
                            <?php echo esc_html__('Iniciar limpieza', 'llm-trace-cleaner'); ?>
                        </button>
                        <button type="button" 
                                id="llm-trace-cleaner-stop-btn" 
                                class="button button-secondary" 
                                style="display: none;">
                            <?php echo esc_html__('Detener', 'llm-trace-cleaner'); ?>
                        </button>
                    </p>
                </div>
                
                <!-- Log -->
                <div class="llm-trace-cleaner-section">
                    <h2><?php echo esc_html__('Registro de actividad', 'llm-trace-cleaner'); ?></h2>
                    <p class="description">
                        <?php echo esc_html__('Solo se muestran los posts/páginas que tenían atributos de rastreo eliminados.', 'llm-trace-cleaner'); ?>
                    </p>
                    <div style="margin-bottom: 20px;">
                        <form method="post" action="" onsubmit="return confirm('<?php echo esc_js(__('¿Estás seguro de que quieres vaciar el log?', 'llm-trace-cleaner')); ?>');" style="display: inline-block; margin-right: 10px;">
                            <?php wp_nonce_field('llm_trace_cleaner_clear_log'); ?>
                            <input type="submit" 
                                   name="llm_trace_cleaner_clear_log" 
                                   class="button button-secondary" 
                                   value="<?php echo esc_attr__('Vaciar log', 'llm-trace-cleaner'); ?>">
                        </form>
                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg('llm_trace_cleaner_download_log', '1'), 'llm_trace_cleaner_download_log')); ?>" 
                           class="button button-secondary">
                            <?php echo esc_html__('Descargar archivo de log', 'llm-trace-cleaner'); ?>
                        </a>
                        <span style="margin-left: 15px; color: #666;">
                            <?php 
                            printf(
                                esc_html__('Total: %d registros', 'llm-trace-cleaner'),
                                $total_logs
                            );
                            ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($recent_logs)): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Fecha/Hora', 'llm-trace-cleaner'); ?></th>
                                    <th><?php echo esc_html__('Tipo', 'llm-trace-cleaner'); ?></th>
                                    <th><?php echo esc_html__('ID Post', 'llm-trace-cleaner'); ?></th>
                                    <th><?php echo esc_html__('Título', 'llm-trace-cleaner'); ?></th>
                                    <th><?php echo esc_html__('Detalles', 'llm-trace-cleaner'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_logs as $log): ?>
                                    <tr>
                                        <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $log->datetime)); ?></td>
                                        <td>
                                            <?php 
                                            $type_label = ($log->action_type === 'auto') ? 
                                                __('Automático', 'llm-trace-cleaner') : 
                                                __('Manual', 'llm-trace-cleaner');
                                            echo esc_html($type_label);
                                            ?>
                                        </td>
                                        <td><?php echo esc_html($log->post_id); ?></td>
                                        <td>
                                            <?php if ($log->post_id): ?>
                                                <a href="<?php echo esc_url(get_edit_post_link($log->post_id)); ?>" target="_blank">
                                                    <?php echo esc_html($log->post_title); ?>
                                                </a>
                                            <?php else: ?>
                                                <?php echo esc_html($log->post_title); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($log->details); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if ($total_pages > 1): ?>
                            <div class="llm-trace-cleaner-pagination" style="margin-top: 20px;">
                                <?php
                                $base_url = remove_query_arg('log_page');
                                $base_url = add_query_arg('page', 'llm-trace-cleaner', $base_url);
                                
                                // Botón anterior
                                if ($current_page > 1):
                                    $prev_url = add_query_arg('log_page', $current_page - 1, $base_url);
                                ?>
                                    <a href="<?php echo esc_url($prev_url); ?>" class="button">
                                        <?php echo esc_html__('« Anterior', 'llm-trace-cleaner'); ?>
                                    </a>
                                <?php endif; ?>
                                
                                <span style="margin: 0 15px;">
                                    <?php
                                    printf(
                                        esc_html__('Página %d de %d', 'llm-trace-cleaner'),
                                        $current_page,
                                        $total_pages
                                    );
                                    ?>
                                </span>
                                
                                <?php
                                // Botón siguiente
                                if ($current_page < $total_pages):
                                    $next_url = add_query_arg('log_page', $current_page + 1, $base_url);
                                ?>
                                    <a href="<?php echo esc_url($next_url); ?>" class="button">
                                        <?php echo esc_html__('Siguiente »', 'llm-trace-cleaner'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p><?php echo esc_html__('No hay registros en el log.', 'llm-trace-cleaner'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <style>
            .llm-trace-cleaner-admin {
                max-width: 1200px;
            }
            .llm-trace-cleaner-section {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                padding: 20px;
                margin: 20px 0;
            }
            .llm-trace-cleaner-section h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }
            .llm-trace-cleaner-scan-result {
                background: #f0f0f1;
                padding: 15px;
                margin-top: 15px;
                border-left: 4px solid #2271b1;
            }
            .llm-trace-cleaner-scan-result h3 {
                margin-top: 0;
            }
            .llm-trace-cleaner-scan-result ul {
                margin: 10px 0;
            }
            .llm-trace-cleaner-scan-result li {
                margin: 5px 0;
            }
            .llm-trace-cleaner-progress-bar-container {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            .llm-trace-cleaner-progress-bar {
                flex: 1;
                height: 30px;
                background: #f0f0f1;
                border-radius: 4px;
                overflow: hidden;
                position: relative;
            }
            .llm-trace-cleaner-progress-bar-fill {
                height: 100%;
                background: #2271b1;
                width: 0%;
                transition: width 0.3s ease;
            }
            .llm-trace-cleaner-progress-text {
                min-width: 80px;
                text-align: right;
                font-weight: 600;
            }
            .llm-trace-cleaner-admin .status-ok,
            .llm-trace-cleaner-admin td.status-ok,
            .llm-trace-cleaner-admin td.status-ok strong {
                color: #46b450 !important;
                font-weight: 600;
            }
            .llm-trace-cleaner-admin .status-warning,
            .llm-trace-cleaner-admin td.status-warning,
            .llm-trace-cleaner-admin td.status-warning strong {
                color: #dc3232 !important;
                font-weight: 600;
            }
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Mostrar/ocultar configuración de bots según el estado del checkbox
            $('#llm_trace_cleaner_disable_cache').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#llm-trace-cleaner-bots-config, #llm-trace-cleaner-custom-bots-config').show();
                } else {
                    $('#llm-trace-cleaner-bots-config, #llm-trace-cleaner-custom-bots-config').hide();
                }
            });

            // Botones Seleccionar/Deseleccionar todos los bots
            $('#llm-trace-cleaner-select-all-bots').on('click', function() {
                $('input[name="llm_trace_cleaner_selected_bots[]"]').prop('checked', true);
            });
            $('#llm-trace-cleaner-unselect-all-bots').on('click', function() {
                $('input[name="llm_trace_cleaner_selected_bots[]"]').prop('checked', false);
            });
            
            var processId = null;
            var offset = 0;
            var totalPosts = 0;
            var isProcessing = false;
            var shouldStop = false;
            
            var ajaxNonce = '<?php echo wp_create_nonce('llm_trace_cleaner_ajax'); ?>';
            var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
            
            var selectedCleanTypes = {
                attributes: false,
                unicode: false
            };
            
            // Botón de análisis previo
            $('#llm-trace-cleaner-analyze-btn').on('click', function() {
                $(this).prop('disabled', true).text('<?php echo esc_js(__('Analizando...', 'llm-trace-cleaner')); ?>');
                $('#llm-trace-cleaner-analysis').show();
                $('#llm-trace-cleaner-analysis-content').html('<p><?php echo esc_js(__('Analizando contenido...', 'llm-trace-cleaner')); ?></p>');
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'llm_trace_cleaner_analyze_all_posts',
                        nonce: ajaxNonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            displayAnalysisResults(data);
                        } else {
                            $('#llm-trace-cleaner-analysis-content').html(
                                '<p style="color: red;">Error: ' + (response.data.message || 'Error desconocido') + '</p>'
                            );
                            $('#llm-trace-cleaner-analyze-btn').prop('disabled', false).text('<?php echo esc_js(__('Analizar contenido', 'llm-trace-cleaner')); ?>');
                        }
                    },
                    error: function() {
                        $('#llm-trace-cleaner-analysis-content').html(
                            '<p style="color: red;"><?php echo esc_js(__('Error de conexión. Por favor, intenta de nuevo.', 'llm-trace-cleaner')); ?></p>'
                        );
                        $('#llm-trace-cleaner-analyze-btn').prop('disabled', false).text('<?php echo esc_js(__('Analizar contenido', 'llm-trace-cleaner')); ?>');
                    }
                });
            });
            
            // Mostrar resultados del análisis
            function displayAnalysisResults(data) {
                var html = '<p><strong><?php echo esc_js(__('Total de posts:', 'llm-trace-cleaner')); ?></strong> ' + data.total_posts + '</p>';
                
                if (data.sample_size < data.total_posts) {
                    html += '<p style="color: #666; font-style: italic;"><?php echo esc_js(__('(Análisis basado en una muestra de', 'llm-trace-cleaner')); ?> ' + data.sample_size + ' <?php echo esc_js(__('posts)', 'llm-trace-cleaner')); ?></p>';
                }
                
                if (data.total_attributes > 0 || data.total_unicode > 0) {
                    html += '<h4><?php echo esc_js(__('Elementos encontrados:', 'llm-trace-cleaner')); ?></h4><ul>';
                    
                    if (data.total_attributes > 0) {
                        html += '<li><strong><?php echo esc_js(__('Atributos de rastreo:', 'llm-trace-cleaner')); ?></strong> ' + data.total_attributes;
                        if (Object.keys(data.attributes_found).length > 0) {
                            html += '<ul style="margin-left: 20px; margin-top: 5px;">';
                            for (var attr in data.attributes_found) {
                                html += '<li>' + attr + ': ' + data.attributes_found[attr] + '</li>';
                            }
                            html += '</ul>';
                        }
                        html += '</li>';
                    }
                    
                    if (data.total_unicode > 0) {
                        html += '<li><strong><?php echo esc_js(__('Caracteres Unicode invisibles:', 'llm-trace-cleaner')); ?></strong> ' + data.total_unicode;
                        if (Object.keys(data.unicode_found).length > 0) {
                            html += '<ul style="margin-left: 20px; margin-top: 5px;">';
                            for (var unicode in data.unicode_found) {
                                html += '<li>' + unicode + ': ' + data.unicode_found[unicode] + '</li>';
                            }
                            html += '</ul>';
                        }
                        html += '</li>';
                    }
                    
                    html += '</ul>';
                } else {
                    html += '<p style="color: green;"><strong><?php echo esc_js(__('No se encontraron elementos para limpiar.', 'llm-trace-cleaner')); ?></strong></p>';
                }
                
                $('#llm-trace-cleaner-analysis-content').html(html);
                
                // Mostrar opciones de selección si hay elementos para limpiar
                if (data.has_attributes || data.has_unicode) {
                    $('#llm-trace-cleaner-select-attributes').prop('checked', data.has_attributes);
                    $('#llm-trace-cleaner-select-unicode').prop('checked', data.has_unicode);
                    
                    if (data.total_attributes > 0) {
                        $('#llm-trace-cleaner-attributes-count').text('(' + data.total_attributes + ' encontrados)');
                    }
                    if (data.total_unicode > 0) {
                        $('#llm-trace-cleaner-unicode-count').text('(' + data.total_unicode + ' encontrados)');
                    }
                    
                    selectedCleanTypes.attributes = data.has_attributes;
                    selectedCleanTypes.unicode = data.has_unicode;
                    
                    $('#llm-trace-cleaner-selection').show();
                    $('#llm-trace-cleaner-start-btn').show();
                } else {
                    $('#llm-trace-cleaner-selection').hide();
                    $('#llm-trace-cleaner-start-btn').hide();
                }
                
                $('#llm-trace-cleaner-analyze-btn').prop('disabled', false).text('<?php echo esc_js(__('Reanalizar', 'llm-trace-cleaner')); ?>');
            }
            
            // Botón seleccionar todo
            $('#llm-trace-cleaner-select-all').on('click', function() {
                $('#llm-trace-cleaner-select-attributes').prop('checked', true);
                $('#llm-trace-cleaner-select-unicode').prop('checked', true);
                selectedCleanTypes.attributes = true;
                selectedCleanTypes.unicode = true;
            });
            
            // Actualizar selección cuando cambian los checkboxes
            $('#llm-trace-cleaner-select-attributes').on('change', function() {
                selectedCleanTypes.attributes = $(this).is(':checked');
            });
            
            $('#llm-trace-cleaner-select-unicode').on('change', function() {
                selectedCleanTypes.unicode = $(this).is(':checked');
            });
            
            // Iniciar limpieza
            $('#llm-trace-cleaner-start-btn').on('click', function() {
                // Verificar que al menos una opción esté seleccionada
                if (!selectedCleanTypes.attributes && !selectedCleanTypes.unicode) {
                    alert('<?php echo esc_js(__('Por favor, selecciona al menos un tipo de limpieza.', 'llm-trace-cleaner')); ?>');
                    return;
                }
                
                if (!confirm('<?php echo esc_js(__('¿Estás seguro de que quieres iniciar la limpieza con las opciones seleccionadas?', 'llm-trace-cleaner')); ?>')) {
                    return;
                }
                
                $(this).prop('disabled', true);
                $('#llm-trace-cleaner-stop-btn').show();
                $('#llm-trace-cleaner-progress').show();
                $('#llm-trace-cleaner-result').hide();
                $('#llm-trace-cleaner-analysis').hide();
                shouldStop = false;
                isProcessing = true;
                offset = 0;
                
                // Obtener total de posts
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'llm_trace_cleaner_get_total',
                        nonce: ajaxNonce
                    },
                    success: function(response) {
                        if (response.success) {
                            totalPosts = response.data.total;
                            processId = response.data.process_id;
                            $('#llm-trace-cleaner-progress-text').text('0 / ' + totalPosts);
                            processNextBatch();
                        } else {
                            alert('Error: ' + (response.data.message || 'Error desconocido'));
                            resetUI();
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Error de conexión. Por favor, intenta de nuevo.', 'llm-trace-cleaner')); ?>');
                        resetUI();
                    }
                });
            });
            
            // Detener limpieza
            $('#llm-trace-cleaner-stop-btn').on('click', function() {
                shouldStop = true;
                isProcessing = false;
                $('#llm-trace-cleaner-status-text').text('<?php echo esc_js(__('Deteniendo...', 'llm-trace-cleaner')); ?>');
            });
            
            // Procesar siguiente lote
            function processNextBatch() {
                if (shouldStop || !isProcessing) {
                    resetUI();
                    return;
                }
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    timeout: 150000, // 150 segundos de timeout (2.5 minutos)
                    data: {
                        action: 'llm_trace_cleaner_process_batch',
                        nonce: ajaxNonce,
                        process_id: processId,
                        offset: offset,
                        selected_clean_types: JSON.stringify(selectedCleanTypes)
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            offset += data.processed;
                            
                            // Actualizar progreso
                            var percentage = (data.total_processed / totalPosts) * 100;
                            $('#llm-trace-cleaner-progress-fill').css('width', percentage + '%');
                            $('#llm-trace-cleaner-progress-text').text(data.total_processed + ' / ' + totalPosts);
                            $('#llm-trace-cleaner-status-text').text(
                                '<?php echo esc_js(__('Procesando...', 'llm-trace-cleaner')); ?> ' + 
                                data.total_processed + ' / ' + totalPosts + 
                                ' (<?php echo esc_js(__('Modificados:', 'llm-trace-cleaner')); ?> ' + data.total_modified + ')' +
                                ' - <?php echo esc_js(__('Esperando...', 'llm-trace-cleaner')); ?>'
                            );
                            
                            if (data.is_complete) {
                                // Proceso completado
                                finishProcess();
                            } else {
                                // Continuar con el siguiente lote - aumentar pausa para dar tiempo al servidor
                                setTimeout(processNextBatch, 1000); // Aumentar a 1 segundo entre lotes
                            }
                        } else {
                            alert('Error: ' + (response.data.message || 'Error desconocido'));
                            resetUI();
                        }
                    },
                    error: function(xhr, status, error) {
                        if (!shouldStop) {
                            // Recopilar información del error
                            var errorInfo = {
                                status: status,
                                error: error,
                                readyState: xhr.readyState,
                                statusText: xhr.statusText,
                                statusCode: xhr.status,
                                responseText: xhr.responseText ? xhr.responseText.substring(0, 500) : 'Sin respuesta',
                                offset: offset,
                                processId: processId
                            };
                            
                            console.error('Error AJAX en proceso de limpieza:', errorInfo);
                            
                            // Enviar error al servidor para logging
                            $.ajax({
                                url: ajaxUrl,
                                type: 'POST',
                                timeout: 5000,
                                data: {
                                    action: 'llm_trace_cleaner_log_error',
                                    nonce: ajaxNonce,
                                    error: JSON.stringify(errorInfo),
                                    context: 'processNextBatch - offset: ' + offset
                                }
                            }).fail(function() {
                                console.error('No se pudo registrar el error en el servidor');
                            });
                            
                            // Si es timeout, esperar más tiempo antes de reintentar
                            var waitTime = (status === 'timeout') ? 5000 : 2000;
                            var errorMessage = 'Error: ' + status;
                            if (xhr.status) {
                                errorMessage += ' (HTTP ' + xhr.status + ')';
                            }
                            if (error) {
                                errorMessage += ' - ' + error;
                            }
                            
                            $('#llm-trace-cleaner-status-text').text(
                                errorMessage + ' - <?php echo esc_js(__('Reintentando en', 'llm-trace-cleaner')); ?> ' + 
                                (waitTime / 1000) + ' <?php echo esc_js(__('segundos...', 'llm-trace-cleaner')); ?>'
                            );
                            
                            setTimeout(processNextBatch, waitTime);
                        } else {
                            resetUI();
                        }
                    }
                });
            }
            
            // Finalizar proceso
            function finishProcess() {
                isProcessing = false;
                
                // Obtener resultado final
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'llm_trace_cleaner_get_progress',
                        nonce: ajaxNonce,
                        process_id: processId
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            var resultHtml = '<div class="llm-trace-cleaner-scan-result">' +
                                '<h3><?php echo esc_js(__('Limpieza completada', 'llm-trace-cleaner')); ?></h3>' +
                                '<ul>' +
                                '<li><strong><?php echo esc_js(__('Posts analizados:', 'llm-trace-cleaner')); ?></strong> ' + data.processed + '</li>' +
                                '<li><strong><?php echo esc_js(__('Posts modificados:', 'llm-trace-cleaner')); ?></strong> ' + data.modified + '</li>' +
                                '</ul>';
                            
                            if (data.stats && Object.keys(data.stats).length > 0) {
                                resultHtml += '<h4><?php echo esc_js(__('Atributos eliminados:', 'llm-trace-cleaner')); ?></h4><ul>';
                                for (var attr in data.stats) {
                                    resultHtml += '<li><strong>' + attr + ':</strong> ' + data.stats[attr] + '</li>';
                                }
                                resultHtml += '</ul>';
                            }
                            
                            resultHtml += '<p style="margin-top: 15px; color: #666; font-style: italic;"><?php echo esc_js(__('La página se recargará automáticamente en unos segundos para mostrar los nuevos logs...', 'llm-trace-cleaner')); ?></p>';
                            resultHtml += '</div>';
                            $('#llm-trace-cleaner-result').html(resultHtml).show();
                        }
                        
                        resetUI();
                        
                        // Recargar la página después de 3 segundos para mostrar los nuevos logs
                        setTimeout(function() {
                            window.location.reload();
                        }, 3000);
                    },
                    error: function() {
                        resetUI();
                        // Recargar la página incluso si hay error para mostrar el estado actual
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    }
                });
            }
            
            // Resetear UI
            function resetUI() {
                isProcessing = false;
                $('#llm-trace-cleaner-start-btn').prop('disabled', false).hide();
                $('#llm-trace-cleaner-stop-btn').hide();
                $('#llm-trace-cleaner-progress-fill').css('width', '0%');
                $('#llm-trace-cleaner-analyze-btn').prop('disabled', false).text('<?php echo esc_js(__('Analizar contenido', 'llm-trace-cleaner')); ?>');
            }
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Análisis previo del contenido
     */
    public function ajax_analyze_content() {
        check_ajax_referer('llm_trace_cleaner_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos.', 'llm-trace-cleaner')));
        }
        
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(array('message' => __('ID de post inválido.', 'llm-trace-cleaner')));
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post no encontrado.', 'llm-trace-cleaner')));
        }
        
        $cleaner = new LLM_Trace_Cleaner_Cleaner();
        $analysis = $cleaner->analyze_content($post->post_content);
        
        wp_send_json_success($analysis);
    }
    
    /**
     * AJAX: Analizar todos los posts antes de limpiar
     */
    public function ajax_analyze_all_posts() {
        check_ajax_referer('llm_trace_cleaner_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sin permisos.', 'llm-trace-cleaner')));
        }
        
        global $wpdb;
        
        // Obtener todos los IDs de posts publicados
        $all_post_ids = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type IN ('post', 'page') 
             AND post_status = 'publish' 
             ORDER BY ID ASC"
        );
        
        if (empty($all_post_ids)) {
            wp_send_json_success(array(
                'total_posts' => 0,
                'attributes_found' => array(),
                'unicode_found' => array(),
                'total_attributes' => 0,
                'total_unicode' => 0
            ));
        }
        
        $cleaner = new LLM_Trace_Cleaner_Cleaner();
        $total_attributes = 0;
        $total_unicode = 0;
        $attributes_found = array();
        $unicode_found = array();
        
        // Analizar una muestra de posts (máximo 100 para no sobrecargar)
        $sample_size = min(100, count($all_post_ids));
        $sample_ids = array_slice($all_post_ids, 0, $sample_size);
        
        foreach ($sample_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                continue;
            }
            
            $analysis = $cleaner->analyze_content($post->post_content);
            
            // Acumular resultados
            foreach ($analysis['attributes_found'] as $attr => $count) {
                if (!isset($attributes_found[$attr])) {
                    $attributes_found[$attr] = 0;
                }
                $attributes_found[$attr] += $count;
                $total_attributes += $count;
            }
            
            foreach ($analysis['unicode_found'] as $unicode => $count) {
                if (!isset($unicode_found[$unicode])) {
                    $unicode_found[$unicode] = 0;
                }
                $unicode_found[$unicode] += $count;
                $total_unicode += $count;
            }
        }
        
        wp_send_json_success(array(
            'total_posts' => count($all_post_ids),
            'sample_size' => $sample_size,
            'attributes_found' => $attributes_found,
            'unicode_found' => $unicode_found,
            'total_attributes' => $total_attributes,
            'total_unicode' => $total_unicode,
            'has_attributes' => $total_attributes > 0,
            'has_unicode' => $total_unicode > 0
        ));
    }
}

