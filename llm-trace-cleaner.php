<?php
/**
 * Plugin Name: LLM Trace Cleaner
 * Plugin URI: https://github.com/YaggoSEO/llm-trace-cleaner
 * Description: Elimina atributos de rastreo de herramientas LLM (ChatGPT, Claude, Bard, etc.) del contenido HTML de entradas y páginas.
 * Version: 1.1.0
 * Author: Yago Vázquez Gómez (Yaggoseo)
 * Author URI: https://yaggoseo.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: llm-trace-cleaner
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Prevenir acceso directo
defined('ABSPATH') || exit;

// Verificar si las constantes ya están definidas (evita conflictos en actualizaciones)
if (!defined('LLM_TRACE_CLEANER_VERSION')) {
    // Definir constantes del plugin
    define('LLM_TRACE_CLEANER_VERSION', '1.0.0');
    define('LLM_TRACE_CLEANER_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('LLM_TRACE_CLEANER_PLUGIN_URL', plugin_dir_url(__FILE__));
}

/**
 * Clase principal del plugin
 */
if (!class_exists('LLM_Trace_Cleaner')) {
    final class LLM_Trace_Cleaner {
    
    /**
     * Instancia única del plugin (Singleton)
     */
    private static $instance = null;
    
    /**
     * Obtener instancia única del plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor privado (Singleton)
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Inicializar el plugin
     */
    private function init() {
        // Cargar archivos necesarios
        $this->load_dependencies();
        
        // Registrar hooks de activación/desactivación
        register_activation_hook(__FILE__, array('LLM_Trace_Cleaner_Activator', 'activate'));
        register_deactivation_hook(__FILE__, array('LLM_Trace_Cleaner_Activator', 'deactivate'));
        
        // Inicializar componentes
        add_action('plugins_loaded', array($this, 'load_components'));
    }
    
    /**
     * Cargar archivos de dependencias
     */
    private function load_dependencies() {
        require_once LLM_TRACE_CLEANER_PLUGIN_DIR . 'includes/class-llm-trace-cleaner-activator.php';
        require_once LLM_TRACE_CLEANER_PLUGIN_DIR . 'includes/class-llm-trace-cleaner-cleaner.php';
        require_once LLM_TRACE_CLEANER_PLUGIN_DIR . 'includes/class-llm-trace-cleaner-logger.php';
        require_once LLM_TRACE_CLEANER_PLUGIN_DIR . 'includes/class-llm-trace-cleaner-admin.php';
    }
    
    /**
     * Cargar componentes del plugin
     */
    public function load_components() {
        // Inicializar administración
        if (is_admin()) {
            LLM_Trace_Cleaner_Admin::get_instance();
        }
        
        // Inicializar limpieza automática si está activada
        $auto_clean_enabled = get_option('llm_trace_cleaner_auto_clean', false);
        if ($auto_clean_enabled) {
            add_action('save_post', array($this, 'auto_clean_post'), 10, 2);
        }
    }
    
    /**
     * Limpiar automáticamente el contenido al guardar
     */
    public function auto_clean_post($post_id, $post) {
        // Verificar que no sea una revisión o autosave
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Solo procesar posts y páginas publicados
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // Solo procesar post types 'post' y 'page'
        if (!in_array($post->post_type, array('post', 'page'))) {
            return;
        }
        
        // Obtener el contenido actual
        $content = $post->post_content;
        
        // Limpiar el contenido
        $cleaner = new LLM_Trace_Cleaner_Cleaner();
        $cleaned_content = $cleaner->clean_html($content);
        
        // Si hubo cambios, actualizar el post
        if ($cleaned_content !== $content) {
            // Remover el hook para evitar loop infinito
            remove_action('save_post', array($this, 'auto_clean_post'), 10);
            
            // Actualizar el post
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $cleaned_content
            ));
            
            // Volver a agregar el hook
            add_action('save_post', array($this, 'auto_clean_post'), 10, 2);
            
            // Registrar en el log
            $logger = new LLM_Trace_Cleaner_Logger();
            $stats = $cleaner->get_last_stats();
            $logger->log_action('auto', $post_id, $post->post_title, $stats);
        }
    }
    }
}

/**
 * Inicializar el plugin
 */
if (!function_exists('llm_trace_cleaner_init')) {
    function llm_trace_cleaner_init() {
        if (!class_exists('LLM_Trace_Cleaner')) {
            return false;
        }
        return LLM_Trace_Cleaner::get_instance();
    }
}

// Iniciar el plugin solo si no se ha inicializado ya
if (!did_action('llm_trace_cleaner_loaded')) {
    llm_trace_cleaner_init();
    do_action('llm_trace_cleaner_loaded');
}

