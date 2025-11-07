<?php
/**
 * Clase para manejar la desactivación de caché para bots/LLMs
 *
 * @package LLM_Trace_Cleaner
 */

defined('ABSPATH') || exit;

/**
 * Clase LLM_Trace_Cleaner_Cache
 */
class LLM_Trace_Cleaner_Cache {
    
    /**
     * Instancia única (Singleton)
     */
    private static $instance = null;
    
    /**
     * Lista de bots/LLMs comunes
     */
    private $default_bots = array(
        'chatgpt',
        'claude',
        'bard',
        'gpt',
        'openai',
        'anthropic',
        'googlebot',
        'bingbot',
        'crawler',
        'spider',
        'bot',
        'llm',
        'grok',
    );
    
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
        // Inicializar hooks temprano para evitar caché
        add_action('init', array($this, 'disable_cache_for_bots'), 1);
        
        // Hooks específicos para diferentes plugins de caché
        add_filter('litespeed_cache_check_cookies', array($this, 'litespeed_bypass_for_bots'), 10, 1);
        add_action('litespeed_init', array($this, 'litespeed_control_init'));
        add_filter('do_rocket_generate_caching_files', array($this, 'exclude_bots_from_wp_rocket'));
        add_filter('w3tc_can_cache', array($this, 'w3tc_exclude_bots'), 10, 1);
        add_filter('wp_cache_themes_persist', array($this, 'wp_super_cache_exclude_bots'), 10, 1);
    }
    
    /**
     * Obtener User-Agent efectivo
     * 
     * @return string User-Agent
     */
    private function get_effective_user_agent() {
        // Intentar obtener desde diferentes fuentes
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            return $_SERVER['HTTP_USER_AGENT'];
        }
        
        if (isset($_SERVER['HTTP_X_ORIGINAL_USER_AGENT'])) {
            return $_SERVER['HTTP_X_ORIGINAL_USER_AGENT'];
        }
        
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR_USER_AGENT'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR_USER_AGENT'];
        }
        
        return '';
    }
    
    /**
     * Detectar si es un iPhone sin JavaScript (posible Grok u otro LLM)
     * 
     * @param string $ua User-Agent
     * @return bool
     */
    private function is_iphone_no_js_llm($ua) {
        // Detectar iPhone
        if (stripos($ua, 'iphone') === false) {
            return false;
        }
        
        // Verificar que no tenga indicadores de navegador completo
        $browser_indicators = array('safari', 'chrome', 'firefox', 'opera', 'edge', 'version');
        foreach ($browser_indicators as $indicator) {
            if (stripos($ua, $indicator) !== false) {
                return false;
            }
        }
        
        // Si es iPhone pero sin indicadores de navegador, probablemente es un LLM
        return true;
    }
    
    /**
     * Obtener lista de bots configurados
     * 
     * @return array Lista de bots
     */
    private function get_configured_bots() {
        $bots = array();
        
        // Bots seleccionados desde la configuración
        $selected_bots = get_option('llm_trace_cleaner_selected_bots', array());
        if (is_array($selected_bots)) {
            $bots = array_merge($bots, $selected_bots);
        }
        
        // Bots personalizados
        $custom_bots = get_option('llm_trace_cleaner_custom_bots', '');
        if (!empty($custom_bots)) {
            $custom_list = array_filter(array_map('trim', explode("\n", $custom_bots)));
            $bots = array_merge($bots, $custom_list);
        }
        
        // Si no hay bots configurados, usar los por defecto
        if (empty($bots)) {
            $bots = $this->default_bots;
        }
        
        return array_unique(array_filter($bots));
    }
    
    /**
     * Verificar si el User-Agent es un bot configurado
     * 
     * @param string $ua User-Agent (opcional, se obtiene automáticamente si no se proporciona)
     * @return bool
     */
    public function is_bot($ua = null) {
        if ($ua === null) {
            $ua = strtolower($this->get_effective_user_agent());
        } else {
            $ua = strtolower($ua);
        }
        
        if (empty($ua)) {
            return false;
        }
        
        // Verificar si está desactivada la funcionalidad
        $disable_cache = get_option('llm_trace_cleaner_disable_cache', false);
        if (!$disable_cache) {
            return false;
        }
        
        // Obtener lista de bots
        $allowed_bots = $this->get_configured_bots();
        
        // Verificar cada bot
        foreach ($allowed_bots as $bot) {
            if (!empty($bot) && stripos($ua, $bot) !== false) {
                return true;
            }
        }
        
        // Verificar iPhone sin JS (Grok)
        if ($this->is_iphone_no_js_llm($ua)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Desactivar caché para bots
     */
    public function disable_cache_for_bots() {
        // No hacer nada en AJAX
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        // Verificar si es un bot
        if (!$this->is_bot()) {
            return;
        }
        
        // Desactivar todas las cachés de WordPress
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        if (!defined('DONOTCACHEOBJECT')) {
            define('DONOTCACHEOBJECT', true);
        }
        if (!defined('DONOTCACHEDB')) {
            define('DONOTCACHEDB', true);
        }
        
        // LiteSpeed Cache
        if (!defined('LSCACHE_NO_CACHE')) {
            define('LSCACHE_NO_CACHE', true);
        }
        do_action('litespeed_control_set_nocache', 'llm-trace-cleaner: bot detected');
        
        // NitroPack
        if (!defined('NITROPACK_DISABLE_CACHE')) {
            define('NITROPACK_DISABLE_CACHE', true);
        }
        
        // WP Rocket
        if (!defined('DONOTROCKETOPTIMIZE')) {
            define('DONOTROCKETOPTIMIZE', true);
        }
        
        // W3 Total Cache
        if (!defined('DONOTMINIFY')) {
            define('DONOTMINIFY', true);
        }
        
        // WP Super Cache
        if (function_exists('wp_cache_no_cache_for_ip')) {
            wp_cache_no_cache_for_ip();
        }
        
        // Añadir headers HTTP para asegurar que no se cachea
        if (!headers_sent()) {
            header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('X-LLM-Trace-Cleaner: Cache-Disabled');
        }
    }
    
    /**
     * Hook para LiteSpeed Cache - Bypass basado en cookies
     * 
     * @param bool $can_cache Si se puede cachear
     * @return bool
     */
    public function litespeed_bypass_for_bots($can_cache) {
        if ($this->is_bot()) {
            return false; // No cachear
        }
        return $can_cache;
    }
    
    /**
     * Hook para LiteSpeed Cache - Control de inicialización
     */
    public function litespeed_control_init() {
        if ($this->is_bot()) {
            do_action('litespeed_control_set_nocache', 'llm-trace-cleaner: bot detected');
        }
    }
    
    /**
     * Hook para WP Rocket - Excluir bots de la caché
     * 
     * @param bool $can_cache Si se puede cachear
     * @return bool
     */
    public function exclude_bots_from_wp_rocket($can_cache) {
        if ($this->is_bot()) {
            return false; // No cachear
        }
        return $can_cache;
    }
    
    /**
     * Hook para W3 Total Cache - Excluir bots
     * 
     * @param bool $can_cache Si se puede cachear
     * @return bool
     */
    public function w3tc_exclude_bots($can_cache) {
        if ($this->is_bot()) {
            return false; // No cachear
        }
        return $can_cache;
    }
    
    /**
     * Hook para WP Super Cache - Excluir bots
     * 
     * @param bool $persist Si debe persistir
     * @return bool
     */
    public function wp_super_cache_exclude_bots($persist) {
        if ($this->is_bot()) {
            return false; // No persistir caché
        }
        return $persist;
    }
}

