<?php
/**
 * Sistema de Actualizaciones desde GitHub
 * Soporte para repositorios públicos y privados mediante Personal Access Token
 *
 * @package LLM_Trace_Cleaner
 */

if (!defined('ABSPATH')) {
    exit;
}

class LLM_Trace_Cleaner_GitHub_Updater {
    
    private $plugin_file;
    private $github_user;
    private $github_repo;
    private $github_branch;
    private $plugin_slug;
    private $github_token;
    
    /**
     * Constructor
     *
     * @param string $plugin_file Ruta completa al archivo principal del plugin
     * @param string $github_user Usuario de GitHub
     * @param string $github_repo Nombre del repositorio
     * @param string $github_branch Rama del repositorio (default: main)
     * @param string|null $github_token Token de acceso personal (opcional para repos públicos)
     */
    public function __construct($plugin_file, $github_user, $github_repo, $github_branch = 'main', $github_token = null) {
        $this->plugin_file = $plugin_file;
        $this->github_user = $github_user;
        $this->github_repo = $github_repo;
        $this->github_branch = $github_branch;
        $this->plugin_slug = plugin_basename($plugin_file);
        
        // Limpiar y validar el token
        if (!empty($github_token)) {
            $clean_token = trim(preg_replace('/\s+/', '', $github_token));
            
            // Validar que no sea un token de ejemplo
            $is_example_token = (
                strpos($clean_token, 'xxxxx') !== false ||
                strpos($clean_token, 'xxxxxxxx') !== false ||
                strlen($clean_token) < 20
            );
            
            // Solo asignar si tiene un formato válido y no es un token de ejemplo
            if (!empty($clean_token) && !$is_example_token && 
                (strpos($clean_token, 'ghp_') === 0 || strpos($clean_token, 'github_pat_') === 0)) {
                $this->github_token = $clean_token;
            } else {
                // Token inválido o de ejemplo, no usar para repos públicos
                $this->github_token = null;
            }
        } else {
            $this->github_token = null;
        }
        
        // Hooks de WordPress para actualizaciones
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'), 10, 1);
        add_filter('plugins_api', array($this, 'plugin_information'), 10, 3);
        add_filter('upgrader_source_selection', array($this, 'upgrader_source_selection'), 10, 4);
        
        // Añadir headers de autenticación para descargas desde GitHub
        // Solo si tenemos un token válido (no para repos públicos)
        if (!empty($this->github_token)) {
            add_filter('http_request_args', array($this, 'add_github_auth_headers'), 10, 2);
        }
    }
    
    /**
     * Verificar si hay actualizaciones disponibles
     */
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            if (!isset($transient->checked)) {
                $transient->checked = array();
            }
        }
        
        // Obtener la versión local
        $local_version = $this->get_local_version();
        
        // Buscar nuestro plugin en la lista de plugins activos
        $active_plugins = get_option('active_plugins', array());
        $found_slug = null;
        $main_file = basename($this->plugin_file);
        
        foreach ($active_plugins as $plugin_path) {
            if (strpos($plugin_path, $main_file) !== false) {
                $found_slug = $plugin_path;
                break;
            }
        }
        
        if (!$found_slug) {
            $found_slug = $this->plugin_slug;
        }
        
        $this->plugin_slug = $found_slug;
        
        // Obtener versión remota
        $remote_version = $this->get_remote_version();
        
        // Log para diagnóstico
        $this->log_update_check($local_version, $remote_version);
        
        if ($remote_version && version_compare($local_version, $remote_version, '<')) {
            $update_data = (object) array(
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $remote_version,
                'url' => $this->get_plugin_info_url(),
                'package' => $this->get_download_url(),
                'icons' => array(),
                'banners' => array(),
                'banners_rtl' => array(),
                'tested' => '',
                'requires_php' => '7.4',
                'compatibility' => new stdClass()
            );
            
            if (!isset($transient->response)) {
                $transient->response = array();
            }
            
            $transient->response[$this->plugin_slug] = $update_data;
        } else {
            // Añadir a no_update para indicar que está actualizado
            if (!isset($transient->no_update)) {
                $transient->no_update = array();
            }
            $transient->no_update[$this->plugin_slug] = (object) array(
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $local_version,
                'url' => $this->get_plugin_info_url(),
                'package' => ''
            );
        }
        
        return $transient;
    }
    
    /**
     * Obtener información del plugin para el diálogo de actualización
     */
    public function plugin_information($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        // Verificar si es nuestro plugin
        $dominated_slug = dirname($this->plugin_slug);
        if (!isset($args->slug) || $args->slug !== $dominated_slug) {
            return $result;
        }
        
        $info = new stdClass();
        $plugin_data = get_plugin_data($this->plugin_file);
        
        $info->name = $plugin_data['Name'];
        $info->slug = $dominated_slug;
        $info->version = $this->get_remote_version();
        $info->last_updated = $this->get_last_commit_date();
        $info->download_link = $this->get_download_url();
        $info->author = $plugin_data['Author'];
        $info->homepage = $plugin_data['PluginURI'];
        $info->requires = '5.0';
        $info->requires_php = '7.4';
        $info->tested = get_bloginfo('version');
        $info->sections = array(
            'description' => $plugin_data['Description'],
            'changelog' => $this->get_changelog()
        );
        
        return $info;
    }
    
    /**
     * Corregir la estructura del directorio al descargar desde GitHub
     * GitHub descarga como "repo-branch/" y necesitamos renombrarlo
     */
    public function upgrader_source_selection($source, $remote_source, $upgrader, $hook_extra) {
        global $wp_filesystem;
        
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_slug) {
            return $source;
        }
        
        // Obtener el nombre correcto del directorio del plugin
        $proper_folder_name = dirname($this->plugin_slug);
        
        // GitHub descarga como "repo-branch/" (ej: llm-trace-cleaner-main/)
        // Necesitamos renombrarlo a "repo/" (ej: llm-trace-cleaner/)
        $new_source = trailingslashit($remote_source) . $proper_folder_name . '/';
        
        // Si el directorio fuente tiene el sufijo -main o -master, renombrarlo
        if ($source !== $new_source && $wp_filesystem->exists($source)) {
            // Intentar renombrar el directorio
            if ($wp_filesystem->move($source, $new_source)) {
                $this->log_debug('Directorio renombrado de ' . $source . ' a ' . $new_source);
                return $new_source;
            } else {
                // Si falla el move, intentar copiar y eliminar
                if ($wp_filesystem->copy($source, $new_source, true)) {
                    $wp_filesystem->delete($source, true);
                    $this->log_debug('Directorio copiado y renombrado de ' . $source . ' a ' . $new_source);
                    return $new_source;
                }
            }
        }
        
        return $source;
    }
    
    /**
     * Obtener la versión remota desde GitHub
     */
    private function get_remote_version() {
        $cache_key = 'llm_trace_cleaner_remote_version';
        $version = get_transient($cache_key);
        
        if (false !== $version) {
            return $version;
        }
        
        // Construir URL de la API de GitHub
        $url = sprintf('https://api.github.com/repos/%s/%s/contents/llm-trace-cleaner.php?ref=%s', 
            $this->github_user, 
            $this->github_repo,
            $this->github_branch
        );
        
        // Headers para la API de GitHub
        $headers = array(
            'User-Agent' => 'WordPress-LLM-Trace-Cleaner-Updater',
            'Accept' => 'application/vnd.github.v3.raw'
        );
        
        // Para repos públicos, NO usar token (causa error 401 si el token es inválido)
        // Solo usar token si es explícitamente necesario y válido
        // NO añadir header Authorization para repos públicos sin token válido
        
        $response = wp_remote_get($url, array(
            'headers' => $headers,
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            $this->log_error('Error al verificar actualizaciones: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            $this->log_error('Error al verificar actualizaciones. Código HTTP: ' . $response_code);
            return false;
        }
        
        $content = wp_remote_retrieve_body($response);
        
        // Buscar la versión en el contenido del archivo PHP
        if (preg_match('/\*\s*Version:\s*([0-9.]+)/i', $content, $matches)) {
            $version = $matches[1];
            set_transient($cache_key, $version, HOUR_IN_SECONDS);
            return $version;
        }
        
        $this->log_error('No se pudo encontrar la versión en el archivo remoto');
        return false;
    }
    
    /**
     * Obtener la versión local del plugin
     */
    private function get_local_version() {
        $plugin_data = get_plugin_data($this->plugin_file);
        return isset($plugin_data['Version']) ? $plugin_data['Version'] : '0.0.0';
    }
    
    /**
     * Añadir headers de autenticación para descargas desde GitHub
     * Solo para repos privados con token válido
     */
    public function add_github_auth_headers($args, $url) {
        if (strpos($url, 'github.com') !== false && 
            strpos($url, $this->github_user . '/' . $this->github_repo) !== false) {
            if (!isset($args['headers'])) {
                $args['headers'] = array();
            }
            
            // Solo añadir token si es válido (no para repos públicos)
            // Este método solo se llama si $this->github_token no está vacío
            if (!empty($this->github_token)) {
                $clean_token = trim($this->github_token);
                // Verificar que no sea un token de ejemplo
                if (strpos($clean_token, 'xxxxx') === false && 
                    strpos($clean_token, 'xxxxxxxx') === false && 
                    strlen($clean_token) >= 20) {
                    if (strpos($clean_token, 'ghp_') === 0) {
                        $args['headers']['Authorization'] = 'token ' . $clean_token;
                    } elseif (strpos($clean_token, 'github_pat_') === 0) {
                        $args['headers']['Authorization'] = 'Bearer ' . $clean_token;
                    }
                }
            }
            
            if (strpos($url, '.zip') !== false) {
                $args['headers']['Accept'] = 'application/octet-stream';
            }
        }
        return $args;
    }
    
    /**
     * Obtener URL de descarga del ZIP desde GitHub
     */
    private function get_download_url() {
        return sprintf('https://github.com/%s/%s/archive/%s.zip',
            $this->github_user,
            $this->github_repo,
            $this->github_branch
        );
    }
    
    /**
     * Obtener URL de información del plugin
     */
    private function get_plugin_info_url() {
        return sprintf('https://github.com/%s/%s',
            $this->github_user,
            $this->github_repo
        );
    }
    
    /**
     * Obtener fecha del último commit
     */
    private function get_last_commit_date() {
        $cache_key = 'llm_trace_cleaner_last_commit';
        $cached = get_transient($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $url = sprintf('https://api.github.com/repos/%s/%s/commits/%s',
            $this->github_user,
            $this->github_repo,
            $this->github_branch
        );
        
        $headers = array(
            'User-Agent' => 'WordPress-LLM-Trace-Cleaner-Updater'
        );
        
        // Para repos públicos, NO usar token
        // Solo usar si es válido y necesario
        
        $response = wp_remote_get($url, array(
            'headers' => $headers,
            'timeout' => 15
        ));
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $commit = json_decode(wp_remote_retrieve_body($response));
            if (isset($commit->commit->author->date)) {
                $date = date_i18n(get_option('date_format'), strtotime($commit->commit->author->date));
                set_transient($cache_key, $date, HOUR_IN_SECONDS);
                return $date;
            }
        }
        
        return '';
    }
    
    /**
     * Obtener changelog del repositorio
     */
    private function get_changelog() {
        $cache_key = 'llm_trace_cleaner_changelog';
        $cached = get_transient($cache_key);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $url = sprintf('https://api.github.com/repos/%s/%s/contents/CHANGELOG.md?ref=%s',
            $this->github_user,
            $this->github_repo,
            $this->github_branch
        );
        
        $headers = array(
            'User-Agent' => 'WordPress-LLM-Trace-Cleaner-Updater',
            'Accept' => 'application/vnd.github.v3.raw'
        );
        
        // Para repos públicos, NO usar token
        // Solo usar si es válido y necesario
        
        $response = wp_remote_get($url, array(
            'headers' => $headers,
            'timeout' => 15
        ));
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $changelog = wp_remote_retrieve_body($response);
            // Convertir Markdown a HTML básico
            $changelog = nl2br(esc_html($changelog));
            set_transient($cache_key, $changelog, HOUR_IN_SECONDS);
            return $changelog;
        }
        
        return '<p>Consulta el repositorio de GitHub para ver el historial de cambios.</p>';
    }
    
    /**
     * Log de verificación de actualizaciones
     */
    private function log_update_check($local_version, $remote_version) {
        // Guardar solo el último log en lugar de un historial
        $log = array(
            'datetime' => current_time('mysql'),
            'local_version' => $local_version,
            'remote_version' => $remote_version ?: 'No disponible',
            'update_available' => $remote_version && version_compare($local_version, $remote_version, '<'),
            'has_token' => !empty($this->github_token)
        );
        
        update_option('llm_trace_cleaner_updater_logs', $log);
    }
    
    /**
     * Log de errores
     */
    private function log_error($message) {
        // Guardar solo el último error en lugar de un historial
        $log = array(
            'datetime' => current_time('mysql'),
            'message' => $message
        );
        
        update_option('llm_trace_cleaner_updater_errors', $log);
    }
    
    /**
     * Log de debug
     */
    private function log_debug($message) {
        $logs = get_option('llm_trace_cleaner_updater_debug', array());
        
        $logs[] = array(
            'datetime' => current_time('mysql'),
            'message' => $message
        );
        
        // Mantener solo los últimos 20 logs de debug
        if (count($logs) > 20) {
            $logs = array_slice($logs, -20);
        }
        
        update_option('llm_trace_cleaner_updater_debug', $logs);
    }
    
    /**
     * Obtener información de estado para diagnóstico
     */
    public function get_status_info() {
        return array(
            'local_version' => $this->get_local_version(),
            'remote_version' => $this->get_remote_version(),
            'github_user' => $this->github_user,
            'github_repo' => $this->github_repo,
            'github_branch' => $this->github_branch,
            'has_token' => !empty($this->github_token),
            'last_commit' => $this->get_last_commit_date(),
            'download_url' => $this->get_download_url(),
            'plugin_slug' => $this->plugin_slug
        );
    }
    
    /**
     * Limpiar caché de actualizaciones
     */
    public static function clear_cache() {
        delete_transient('llm_trace_cleaner_remote_version');
        delete_transient('llm_trace_cleaner_last_commit');
        delete_transient('llm_trace_cleaner_changelog');
        delete_site_transient('update_plugins');
    }
}

