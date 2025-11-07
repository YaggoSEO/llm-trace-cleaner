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
        // Crear tabla de logs
        self::create_log_table();
        
        // Establecer opciones por defecto
        add_option('llm_trace_cleaner_auto_clean', false);
        add_option('llm_trace_cleaner_version', LLM_TRACE_CLEANER_VERSION);
        
        // Limpiar cache de rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Desactivar el plugin
     */
    public static function deactivate() {
        // Limpiar cache de rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Crear tabla de logs en la base de datos
     */
    private static function create_log_table() {
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

