<?php
/**
 * Clase para manejar la activación y desactivación del plugin
 *
 * @package LLM_Trace_Cleaner
 */

defined('ABSPATH') || exit;

/**
 * Clase LLM_Trace_Cleaner_Activator
 */
class LLM_Trace_Cleaner_Activator {
    
    /**
     * Activar el plugin
     */
    public static function activate() {
        // Verificar que las constantes estén definidas
        if (!defined('LLM_TRACE_CLEANER_VERSION')) {
            define('LLM_TRACE_CLEANER_VERSION', '1.1.8');
        }
        
        // Crear tabla de logs
        self::create_log_table();
        
        // Establecer opciones por defecto (solo si no existen)
        if (get_option('llm_trace_cleaner_auto_clean') === false) {
            add_option('llm_trace_cleaner_auto_clean', false);
        }
        if (get_option('llm_trace_cleaner_version') === false) {
            add_option('llm_trace_cleaner_version', LLM_TRACE_CLEANER_VERSION);
        } else {
            update_option('llm_trace_cleaner_version', LLM_TRACE_CLEANER_VERSION);
        }
        if (get_option('llm_trace_cleaner_disable_cache') === false) {
            add_option('llm_trace_cleaner_disable_cache', false);
        }
        if (get_option('llm_trace_cleaner_selected_bots') === false) {
            add_option('llm_trace_cleaner_selected_bots', array());
        }
        if (get_option('llm_trace_cleaner_custom_bots') === false) {
            add_option('llm_trace_cleaner_custom_bots', '');
        }
        if (get_option('llm_trace_cleaner_telemetry_opt_in') === false) {
            add_option('llm_trace_cleaner_telemetry_opt_in', true); // Activado por defecto
        }
        if (get_option('llm_trace_cleaner_batch_size') === false) {
            add_option('llm_trace_cleaner_batch_size', 10); // Tamaño del lote por defecto
        }
        if (get_option('llm_trace_cleaner_error_logs') === false) {
            add_option('llm_trace_cleaner_error_logs', array());
        }
        if (get_option('llm_trace_cleaner_debug_logs') === false) {
            add_option('llm_trace_cleaner_debug_logs', array());
        }
        
        // Limpiar cache de rewrite rules
        flush_rewrite_rules();
        
        // NO redirigir durante la activación - WordPress maneja esto automáticamente
        // La redirección manual puede causar problemas durante actualizaciones
    }
    
    /**
     * Desactivar el plugin
     */
    public static function deactivate() {
        // Limpiar cache de rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Crear tabla de logs en la base de datos (público para actualizaciones)
     */
    public static function create_log_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'llm_trace_cleaner_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            datetime datetime NOT NULL,
            action_type varchar(20) NOT NULL,
            post_id bigint(20) UNSIGNED DEFAULT NULL,
            post_title text,
            details text,
            PRIMARY KEY (id),
            KEY datetime (datetime),
            KEY action_type (action_type),
            KEY post_id (post_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

