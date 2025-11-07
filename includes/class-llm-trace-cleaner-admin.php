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
    }
    
    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        add_management_page(
            __('LLM Trace Cleaner', 'llm-trace-cleaner'),
            __('LLM Trace Cleaner', 'llm-trace-cleaner'),
            'manage_options',
            'llm-trace-cleaner',
            array($this, 'render_admin_page')
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
        
        // Guardar configuración de limpieza automática
        if (isset($_POST['llm_trace_cleaner_save_settings']) && check_admin_referer('llm_trace_cleaner_settings')) {
            $auto_clean = isset($_POST['llm_trace_cleaner_auto_clean']) ? true : false;
            update_option('llm_trace_cleaner_auto_clean', $auto_clean);
            
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
    }
    
    /**
     * Encolar scripts y estilos
     */
    public function enqueue_scripts($hook) {
        // Solo cargar en la página del plugin
        if ($hook !== 'tools_page_llm-trace-cleaner') {
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
        
        // Obtener total de posts y páginas publicados
        $total_posts = wp_count_posts('post')->publish;
        $total_pages = wp_count_posts('page')->publish;
        $total = $total_posts + $total_pages;
        
        // Inicializar el estado del proceso con tiempo extendido
        $process_id = 'llm_trace_clean_' . time();
        set_transient('llm_trace_cleaner_process_' . $process_id, array(
            'total' => $total,
            'processed' => 0,
            'modified' => 0,
            'stats' => array(),
            'started' => current_time('mysql'),
        ), 7200); // 2 horas para procesos largos
        
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
        
        $process_id = isset($_POST['process_id']) ? sanitize_text_field($_POST['process_id']) : '';
        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
        $batch_size = 10; // Procesar 10 posts por lote
        
        if (empty($process_id)) {
            wp_send_json_error(array('message' => __('ID de proceso inválido.', 'llm-trace-cleaner')));
        }
        
        // Obtener estado del proceso
        $process_state = get_transient('llm_trace_cleaner_process_' . $process_id);
        if (!$process_state) {
            wp_send_json_error(array('message' => __('Estado del proceso no encontrado.', 'llm-trace-cleaner')));
        }
        
        // Aumentar tiempo de ejecución y memoria para este lote
        @set_time_limit(120); // Aumentar a 120 segundos
        @ini_set('memory_limit', '256M'); // Aumentar memoria si es posible
        
        $cleaner = new LLM_Trace_Cleaner_Cleaner();
        $logger = new LLM_Trace_Cleaner_Logger();
        
        // Obtener lote de posts usando WP_Query para mejor control
        $query = new WP_Query(array(
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));
        
        $posts = $query->posts;
        
        $batch_modified = 0;
        $batch_stats = array();
        
        foreach ($posts as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                continue;
            }
            
            $original_content = $post->post_content;
            $cleaned_content = $cleaner->clean_html($original_content);
            
            if ($cleaned_content !== $original_content) {
                // Actualizar el post
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_content' => $cleaned_content
                ));
                
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
                $logger->log_action('manual', $post_id, $post->post_title, $stats, true);
            }
            
            // Limpiar memoria después de cada post
            unset($post);
            unset($original_content);
            unset($cleaned_content);
        }
        
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
        
        wp_send_json_success(array(
            'processed' => count($posts),
            'modified' => $batch_modified,
            'total_processed' => $process_state['processed'],
            'total_modified' => $process_state['modified'],
            'is_complete' => $process_state['processed'] >= $process_state['total'],
        ));
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
                        <?php echo esc_html__('Este proceso escaneará todas las entradas y páginas publicadas y eliminará los atributos de rastreo LLM encontrados. El proceso se ejecuta en lotes pequeños para evitar sobrecargar el servidor.', 'llm-trace-cleaner'); ?>
                    </p>
                    
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
                                id="llm-trace-cleaner-start-btn" 
                                class="button button-secondary">
                            <?php echo esc_html__('Escanear y limpiar contenido ahora', 'llm-trace-cleaner'); ?>
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
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var processId = null;
            var offset = 0;
            var totalPosts = 0;
            var isProcessing = false;
            var shouldStop = false;
            
            var ajaxNonce = '<?php echo wp_create_nonce('llm_trace_cleaner_ajax'); ?>';
            var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
            
            // Iniciar limpieza
            $('#llm-trace-cleaner-start-btn').on('click', function() {
                if (!confirm('<?php echo esc_js(__('¿Estás seguro de que quieres escanear y limpiar todo el contenido?', 'llm-trace-cleaner')); ?>')) {
                    return;
                }
                
                $(this).prop('disabled', true);
                $('#llm-trace-cleaner-stop-btn').show();
                $('#llm-trace-cleaner-progress').show();
                $('#llm-trace-cleaner-result').hide();
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
                        offset: offset
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
                            // Si es timeout, esperar más tiempo antes de reintentar
                            var waitTime = (status === 'timeout') ? 5000 : 2000;
                            $('#llm-trace-cleaner-status-text').text(
                                '<?php echo esc_js(__('Error de conexión. Reintentando en', 'llm-trace-cleaner')); ?> ' + 
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
                $('#llm-trace-cleaner-start-btn').prop('disabled', false);
                $('#llm-trace-cleaner-stop-btn').hide();
                $('#llm-trace-cleaner-progress-fill').css('width', '0%');
            }
        });
        </script>
        <?php
    }
}

