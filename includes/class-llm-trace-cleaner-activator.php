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
            define('LLM_TRACE_CLEANER_VERSION', '1.3.0');
        }
        
        // Crear tabla de logs
        self::create_log_table();
        self::migrate_image_module();
        
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
        if (get_option('llm_trace_cleaner_clean_attributes') === false) {
            add_option('llm_trace_cleaner_clean_attributes', false); // Desactivado por defecto
        }
        if (get_option('llm_trace_cleaner_clean_unicode') === false) {
            add_option('llm_trace_cleaner_clean_unicode', false); // Desactivado por defecto
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

    /**
     * Migración segura del módulo de imágenes (idempotente).
     */
    public static function migrate_image_module() {
        $cap_file = plugin_dir_path( __FILE__ ) . 'class-llm-trace-cleaner-image-capabilities.php';
        $log_file = plugin_dir_path( __FILE__ ) . 'class-llm-trace-cleaner-image-logger.php';
        $prof_file = plugin_dir_path( __FILE__ ) . 'class-llm-trace-cleaner-image-profile.php';
        $backup_file = plugin_dir_path( __FILE__ ) . 'class-llm-trace-cleaner-image-backup.php';

        if ( file_exists( $cap_file ) ) {
            require_once $cap_file;
        }
        if ( file_exists( $log_file ) ) {
            require_once $log_file;
        }
        if ( file_exists( $prof_file ) ) {
            require_once $prof_file;
        }
        if ( file_exists( $backup_file ) ) {
            require_once $backup_file;
        }

        if ( class_exists( 'LLM_Trace_Cleaner_Image_Logger' ) ) {
            LLM_Trace_Cleaner_Image_Logger::create_table();
        }

        if ( class_exists( 'LLM_Trace_Cleaner_Image_Capabilities' ) ) {
            if ( false === get_option( 'llm_trace_cleaner_image_settings', false ) ) {
                add_option( 'llm_trace_cleaner_image_settings', LLM_Trace_Cleaner_Image_Capabilities::default_settings(), '', false );
            }
        }

        if ( class_exists( 'LLM_Trace_Cleaner_Image_Profile' ) ) {
            LLM_Trace_Cleaner_Image_Profile::seed_defaults();
        }

        if ( class_exists( 'LLM_Trace_Cleaner_Image_Backup' ) ) {
            LLM_Trace_Cleaner_Image_Backup::base_dir();
        }
    }
}

