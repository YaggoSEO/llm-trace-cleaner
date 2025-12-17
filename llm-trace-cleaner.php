<?php
/**
 * Plugin Name: LLM Trace Cleaner
 * Plugin URI: https://github.com/YaggoSEO/llm-trace-cleaner
 * Description: Elimina atributos de rastreo de herramientas LLM (ChatGPT, Claude, Bard, etc.) del contenido HTML de entradas y páginas.
 * Version: 1.4.0
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
    define('LLM_TRACE_CLEANER_VERSION', '1.4.0');
    define('LLM_TRACE_CLEANER_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('LLM_TRACE_CLEANER_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// ========================================
// SISTEMA DE ACTUALIZACIONES DESDE GITHUB
// ========================================

// Cargar variables de entorno desde .env
require_once plugin_dir_path(__FILE__) . 'includes/class-llm-trace-cleaner-env-loader.php';
LLM_Trace_Cleaner_Env_Loader::load(plugin_dir_path(__FILE__) . '.env');

// Configuración de GitHub (repositorio público)
if (!defined('LLM_TRACE_CLEANER_GITHUB_USER')) {
    define('LLM_TRACE_CLEANER_GITHUB_USER', 'YaggoSEO');
}
if (!defined('LLM_TRACE_CLEANER_GITHUB_REPO')) {
    define('LLM_TRACE_CLEANER_GITHUB_REPO', 'llm-trace-cleaner');
}
if (!defined('LLM_TRACE_CLEANER_GITHUB_BRANCH')) {
    define('LLM_TRACE_CLEANER_GITHUB_BRANCH', 'main');
}
// El token se carga desde .env (solo necesario para repositorios privados)

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
        
        // Inicializar componentes
        add_action('plugins_loaded', array($this, 'load_components'));
        
        // Verificar y ejecutar actualizaciones si es necesario
        add_action('admin_init', array($this, 'check_version'));
        
        // Inicializar sistema de actualizaciones desde GitHub
        $this->init_github_updater();
    }
    
    /**
     * Inicializar sistema de actualizaciones desde GitHub
     */
    private function init_github_updater() {
        if (defined('LLM_TRACE_CLEANER_GITHUB_USER') && defined('LLM_TRACE_CLEANER_GITHUB_REPO')) {
            $updater_file = LLM_TRACE_CLEANER_PLUGIN_DIR . 'includes/class-llm-trace-cleaner-github-updater.php';
            
            if (!file_exists($updater_file)) {
                return;
            }
            
            require_once $updater_file;
            
            if (!class_exists('LLM_Trace_Cleaner_GitHub_Updater')) {
                return;
            }
            
            // Obtener token (opcional para repos públicos)
            $token = defined('LLM_TRACE_CLEANER_GITHUB_TOKEN') ? LLM_TRACE_CLEANER_GITHUB_TOKEN : null;
            
            // Limpiar el token si existe
            if (!empty($token)) {
                $token = trim($token);
                $token = preg_replace('/\s+/', '', $token);
            }
            
            $branch = defined('LLM_TRACE_CLEANER_GITHUB_BRANCH') ? LLM_TRACE_CLEANER_GITHUB_BRANCH : 'main';
            
            // Inicializar updater
            new LLM_Trace_Cleaner_GitHub_Updater(
                LLM_TRACE_CLEANER_PLUGIN_DIR . 'llm-trace-cleaner.php',
                LLM_TRACE_CLEANER_GITHUB_USER,
                LLM_TRACE_CLEANER_GITHUB_REPO,
                $branch,
                $token
            );
        }
    }
    
    /**
     * Verificar versión y ejecutar actualizaciones si es necesario
     */
    public function check_version() {
        $installed_version = get_option('llm_trace_cleaner_version', '0.0.0');
        
        if (version_compare($installed_version, LLM_TRACE_CLEANER_VERSION, '<')) {
            // Ejecutar actualización
            $this->upgrade($installed_version);
            update_option('llm_trace_cleaner_version', LLM_TRACE_CLEANER_VERSION);
        }
    }
    
    /**
     * Ejecutar actualizaciones según la versión anterior
     */
    private function upgrade($old_version) {
        // Añadir nuevas opciones si no existen (para actualizaciones)
        if (version_compare($old_version, '1.1.2', '<')) {
            // Opciones añadidas en 1.1.2
            if (get_option('llm_trace_cleaner_telemetry_opt_in') === false) {
                add_option('llm_trace_cleaner_telemetry_opt_in', true);
            }
            if (get_option('llm_trace_cleaner_batch_size') === false) {
                add_option('llm_trace_cleaner_batch_size', 10);
            }
            if (get_option('llm_trace_cleaner_error_logs') === false) {
                add_option('llm_trace_cleaner_error_logs', array());
            }
            if (get_option('llm_trace_cleaner_debug_logs') === false) {
                add_option('llm_trace_cleaner_debug_logs', array());
            }
        }
        
        // Asegurar que la tabla de logs existe (por si acaso)
        if (class_exists('LLM_Trace_Cleaner_Activator')) {
            LLM_Trace_Cleaner_Activator::create_log_table();
        }
    }
    
    /**
     * Cargar archivos de dependencias
     */
    private function load_dependencies() {
        require_once LLM_TRACE_CLEANER_PLUGIN_DIR . 'includes/class-llm-trace-cleaner-activator.php';
        require_once LLM_TRACE_CLEANER_PLUGIN_DIR . 'includes/class-llm-trace-cleaner-cleaner.php';
        require_once LLM_TRACE_CLEANER_PLUGIN_DIR . 'includes/class-llm-trace-cleaner-logger.php';
        require_once LLM_TRACE_CLEANER_PLUGIN_DIR . 'includes/class-llm-trace-cleaner-cache.php';
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
        
        // Inicializar control de caché para bots
        LLM_Trace_Cleaner_Cache::get_instance();
        
        // Inicializar limpieza automática si está activada
        $auto_clean_enabled = get_option('llm_trace_cleaner_auto_clean', false);
        if ($auto_clean_enabled) {
            add_action('save_post', array($this, 'auto_clean_post'), 10, 2);
        }
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
        
        // Obtener opciones de configuración
        $clean_options = array(
            'clean_attributes' => get_option('llm_trace_cleaner_clean_attributes', false),
            'clean_unicode' => get_option('llm_trace_cleaner_clean_unicode', false),
            'track_locations' => true
        );
        
        // Si ambas opciones están desactivadas, no hacer nada
        if (!$clean_options['clean_attributes'] && !$clean_options['clean_unicode']) {
            return;
        }
        
        // Limpiar el contenido
        $cleaner = new LLM_Trace_Cleaner_Cleaner();
        $cleaned_content = $cleaner->clean_html($content, $clean_options);
        
        // Si hubo cambios, actualizar el post
        if ($cleaned_content !== $content) {
            // Desactivar caché durante la limpieza
            LLM_Trace_Cleaner_Cache::disable_cache_for_cleaning();
            
            // Remover el hook para evitar loop infinito
            remove_action('save_post', array($this, 'auto_clean_post'), 10);
            
            // Actualizar el post SIN hooks para evitar bloqueos de plugins pesados
            // Esto evita ejecutar los 50+ hooks de save_post (WPML, WooCommerce, RankMath, etc.)
            $update_result = $this->update_post_without_hooks($post_id, $cleaned_content);
            
            if ($update_result === false) {
                // Si falla la actualización directa, intentar con wp_update_post como fallback
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_content' => $cleaned_content
                ));
            }
            
            // Limpiar caché del post modificado
            LLM_Trace_Cleaner_Cache::clear_post_cache($post_id);
            
            // Volver a agregar el hook
            add_action('save_post', array($this, 'auto_clean_post'), 10, 2);
            
            // Registrar en el log - forzar registro si el contenido cambió (incluso sin stats)
            $logger = new LLM_Trace_Cleaner_Logger();
            $stats = $cleaner->get_last_stats();
            $change_locations = $cleaner->get_change_locations();
            $logger->log_action('auto', $post_id, $post->post_title, $stats, true, $content, $cleaned_content, $change_locations);
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

// Registrar hooks de activación/desactivación (debe estar fuera de la clase)
if (!function_exists('llm_trace_cleaner_register_hooks')) {
    function llm_trace_cleaner_register_hooks() {
        // Cargar el activador primero
        $activator_file = plugin_dir_path(__FILE__) . 'includes/class-llm-trace-cleaner-activator.php';
        if (file_exists($activator_file)) {
            require_once $activator_file;
        }
        
        // Registrar hooks
        register_activation_hook(__FILE__, array('LLM_Trace_Cleaner_Activator', 'activate'));
        register_deactivation_hook(__FILE__, array('LLM_Trace_Cleaner_Activator', 'deactivate'));
    }
    llm_trace_cleaner_register_hooks();
}

// Iniciar el plugin solo si no se ha inicializado ya
if (!did_action('llm_trace_cleaner_loaded')) {
    llm_trace_cleaner_init();
    do_action('llm_trace_cleaner_loaded');
}

