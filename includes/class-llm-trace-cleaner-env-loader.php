<?php
/**
 * Cargador simple de archivo .env para WordPress
 * Lee variables de entorno desde un archivo .env en la raíz del plugin
 *
 * @package LLM_Trace_Cleaner
 */

if (!defined('ABSPATH')) {
    exit;
}

class LLM_Trace_Cleaner_Env_Loader {
    
    private static $loaded = false;
    private static $env_file = null;
    
    /**
     * Cargar archivo .env si existe
     * Si no existe, intenta crearlo automáticamente desde env.example
     */
    public static function load($env_file = null) {
        if (self::$loaded) {
            return;
        }
        
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        
        if ($env_file === null) {
            $env_file = $plugin_dir . '.env';
        }
        
        self::$env_file = $env_file;
        
        // NO crear automáticamente el .env desde env.example
        // Solo cargar si el archivo .env ya existe (creado manualmente por el usuario)
        if (!file_exists($env_file)) {
            self::$loaded = true;
            return;
        }
        
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignorar comentarios
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parsear línea clave=valor
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                
                $key = trim($key);
                $value = trim($value);
                
                // Remover comillas si las hay
                $value = trim($value, '"\'');
                
                // Para tokens de GitHub, limpiar espacios y saltos de línea
                if (strpos($key, 'GITHUB_TOKEN') !== false) {
                    $value = preg_replace('/\s+/', '', $value);
                    
                    // NO cargar tokens de ejemplo (que contengan "xxxxx" o sean muy cortos)
                    if (strpos($value, 'xxxxx') !== false || strlen($value) < 20) {
                        continue; // Saltar este token, es un ejemplo
                    }
                }
                
                // Definir constante si no existe
                if (!defined($key)) {
                    define($key, $value);
                }
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Obtener valor de una variable de entorno
     */
    public static function get($key, $default = null) {
        if (defined($key)) {
            return constant($key);
        }
        return $default;
    }
}

