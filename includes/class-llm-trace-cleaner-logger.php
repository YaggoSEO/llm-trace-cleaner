<?php
/**
 * Clase para manejar el sistema de logging
 *
 * @package LLM_Trace_Cleaner
 */

defined('ABSPATH') || exit;

/**
 * Clase LLM_Trace_Cleaner_Logger
 */
class LLM_Trace_Cleaner_Logger {
    
    /**
     * Nombre de la tabla de logs
     */
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'llm_trace_cleaner_logs';
    }
    
    /**
     * Registrar una acción en el log
     *
     * @param string $action_type Tipo de acción ('auto' o 'manual')
     * @param int $post_id ID del post
     * @param string $post_title Título del post
     * @param array $stats Estadísticas de atributos eliminados
     * @return int|false ID del registro insertado o false en caso de error
     */
    public function log_action($action_type, $post_id, $post_title, $stats = array()) {
        global $wpdb;
        
        $cleaner = new LLM_Trace_Cleaner_Cleaner();
        $details = $cleaner->format_stats($stats);
        
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'datetime' => current_time('mysql'),
                'action_type' => sanitize_text_field($action_type),
                'post_id' => absint($post_id),
                'post_title' => sanitize_text_field($post_title),
                'details' => sanitize_textarea_field($details),
            ),
            array('%s', '%s', '%d', '%s', '%s')
        );
        
        if ($result === false) {
            error_log('LLM Trace Cleaner: Error al insertar log - ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Obtener logs recientes
     *
     * @param int $limit Número de registros a obtener
     * @return array Array de objetos con los logs
     */
    public function get_recent_logs($limit = 50) {
        global $wpdb;
        
        $limit = absint($limit);
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} ORDER BY datetime DESC LIMIT %d",
                $limit
            ),
            OBJECT
        );
        
        return $results ? $results : array();
    }
    
    /**
     * Vaciar el log
     *
     * @return int|false Número de filas eliminadas o false en caso de error
     */
    public function clear_log() {
        global $wpdb;
        
        $result = $wpdb->query("TRUNCATE TABLE {$this->table_name}");
        
        return $result !== false ? $result : false;
    }
    
    /**
     * Obtener estadísticas del log
     *
     * @return array Estadísticas
     */
    public function get_log_stats() {
        global $wpdb;
        
        $stats = array(
            'total_entries' => 0,
            'auto_clean_count' => 0,
            'manual_clean_count' => 0,
        );
        
        // Total de entradas
        $stats['total_entries'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name}"
        );
        
        // Contar por tipo de acción
        $auto_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE action_type = %s",
                'auto'
            )
        );
        $stats['auto_clean_count'] = absint($auto_count);
        
        $manual_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE action_type = %s",
                'manual'
            )
        );
        $stats['manual_clean_count'] = absint($manual_count);
        
        return $stats;
    }
}

