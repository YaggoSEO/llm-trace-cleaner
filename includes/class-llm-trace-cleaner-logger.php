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
     * @param bool $force_log Forzar registro incluso si las stats están vacías (cuando el contenido cambió)
     * @param string $original_content Contenido original (opcional, para detectar atributos cuando stats está vacío)
     * @param string $cleaned_content Contenido limpio (opcional, para detectar atributos cuando stats está vacío)
     * @param array $change_locations Ubicaciones de los cambios realizados
     * @return int|false ID del registro insertado o false en caso de error
     */
    public function log_action($action_type, $post_id, $post_title, $stats = array(), $force_log = false, $original_content = '', $cleaned_content = '', $change_locations = array()) {
        // Solo registrar si hay atributos eliminados, a menos que se fuerce
        if (empty($stats) && !$force_log) {
            return false;
        }
        
        global $wpdb;
        
        $cleaner = new LLM_Trace_Cleaner_Cleaner();
        
        // Si las stats están vacías pero se fuerza el log, intentar detectar qué atributos se eliminaron
        if (empty($stats) && $force_log) {
            $detected_attrs = $this->detect_removed_attributes($original_content, $cleaned_content);
            $detected_unicode = $this->detect_invisible_unicode_removed($original_content, $cleaned_content);

            // Combinar ambos conjuntos
            $combined = array();
            foreach (array($detected_attrs, $detected_unicode) as $arr) {
                foreach ($arr as $k => $v) {
                    if (!isset($combined[$k])) {
                        $combined[$k] = 0;
                    }
                    $combined[$k] += (int) $v;
                }
            }

            if (!empty($combined)) {
                $stats = $combined; // Usar stats detectadas
            } else {
                // Si no hay stats y no hay ubicaciones, usar mensaje genérico
                if (empty($change_locations)) {
                    $details = __('Contenido modificado (normalización de HTML o cambios menores)', 'llm-trace-cleaner');
                } else {
                    // Si hay ubicaciones pero no stats, formatear solo ubicaciones
                    $details = $this->format_stats_with_locations(array(), $change_locations);
                }
            }
        }
        
        // Formatear detalles con ubicaciones si están disponibles
        if (!empty($change_locations) && !empty($stats)) {
            $details = $this->format_stats_with_locations($stats, $change_locations);
        } elseif (empty($details)) {
            // Si no hay ubicaciones, usar formato estándar
            $details = $cleaner->format_stats($stats);
        }
        
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
        
        $log_id = $wpdb->insert_id;
        
        // Escribir también en el archivo de log
        $this->write_to_file_log($action_type, $post_id, $post_title, $details);
        
        return $log_id;
    }
    
    /**
     * Obtener logs recientes (solo los que tienen atributos eliminados)
     *
     * @param int $limit Número de registros a obtener
     * @param int $offset Offset para paginación
     * @return array Array de objetos con los logs
     */
    public function get_recent_logs($limit = 50, $offset = 0) {
        global $wpdb;
        
        $limit = absint($limit);
        $offset = absint($offset);
        
        // Obtener todos los logs que tengan detalles (excluir solo los vacíos y "Ningún atributo eliminado")
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                 WHERE details != %s AND details != '' AND details IS NOT NULL
                 ORDER BY datetime DESC 
                 LIMIT %d OFFSET %d",
                'Ningún atributo eliminado',
                $limit,
                $offset
            ),
            OBJECT
        );
        
        return $results ? $results : array();
    }
    
    /**
     * Obtener el total de logs con atributos eliminados
     *
     * @return int Total de registros
     */
    public function get_total_logs_count() {
        global $wpdb;
        
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} 
                 WHERE details != %s AND details != '' AND details IS NOT NULL",
                'Ningún atributo eliminado'
            )
        );
        
        return absint($count);
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
    
    /**
     * Escribir en el archivo de log
     *
     * @param string $action_type Tipo de acción
     * @param int $post_id ID del post
     * @param string $post_title Título del post
     * @param string $details Detalles de la limpieza
     * @return bool True si se escribió correctamente, false en caso contrario
     */
    private function write_to_file_log($action_type, $post_id, $post_title, $details) {
        $log_file = LLM_TRACE_CLEANER_PLUGIN_DIR . 'llm-trace-cleaner.log';
        
        $timestamp = current_time('Y-m-d H:i:s');
        $action_label = ($action_type === 'auto') ? 'Automático' : 'Manual';
        
        $log_entry = sprintf(
            "[%s] %s | Post ID: %d | Título: %s | %s\n",
            $timestamp,
            $action_label,
            $post_id,
            $post_title,
            $details
        );
        
        // Intentar escribir en el archivo
        $result = @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        if ($result === false) {
            error_log('LLM Trace Cleaner: Error al escribir en archivo de log');
            return false;
        }
        
        return true;
    }
    
    /**
     * Obtener el contenido del archivo de log
     *
     * @param int $lines Número de líneas a obtener (0 para todas)
     * @return string Contenido del log o mensaje de error
     */
    public function get_file_log_content($lines = 0) {
        $log_file = LLM_TRACE_CLEANER_PLUGIN_DIR . 'llm-trace-cleaner.log';
        
        if (!file_exists($log_file)) {
            return __('El archivo de log no existe aún.', 'llm-trace-cleaner');
        }
        
        if (!is_readable($log_file)) {
            return __('No se puede leer el archivo de log.', 'llm-trace-cleaner');
        }
        
        if ($lines > 0) {
            // Obtener solo las últimas N líneas
            $content = file($log_file);
            if ($content === false) {
                return __('Error al leer el archivo de log.', 'llm-trace-cleaner');
            }
            $content = array_slice($content, -$lines);
            return implode('', $content);
        } else {
            // Obtener todo el contenido
            $content = file_get_contents($log_file);
            return $content !== false ? $content : __('Error al leer el archivo de log.', 'llm-trace-cleaner');
        }
    }
    
    /**
     * Vaciar el archivo de log
     *
     * @return bool True si se vació correctamente, false en caso contrario
     */
    public function clear_file_log() {
        $log_file = LLM_TRACE_CLEANER_PLUGIN_DIR . 'llm-trace-cleaner.log';
        
        if (file_exists($log_file)) {
            return @file_put_contents($log_file, '') !== false;
        }
        
        return true;
    }
    
    /**
     * Detectar qué atributos se eliminaron comparando el contenido original y el limpio
     *
     * @param string $original_content Contenido original
     * @param string $cleaned_content Contenido limpio
     * @return array Array con los atributos detectados y su cantidad
     */
    private function detect_removed_attributes($original_content, $cleaned_content) {
        if (empty($original_content) || empty($cleaned_content)) {
            return array();
        }
        
        $detected = array();
        // Usar la misma lista que el limpiador (incluye filtros)
        $cleaner = new LLM_Trace_Cleaner_Cleaner();
        $attributes_to_check = $cleaner->get_attributes_to_remove();
        
        // Contar atributos en el contenido original
        foreach ($attributes_to_check as $attr) {
            // Buscar el atributo en el contenido original
            $pattern = '/\s+' . preg_quote($attr, '/') . '(?:\s*=\s*["\'][^"\']*["\'])?/i';
            preg_match_all($pattern, $original_content, $matches);
            $count = count($matches[0]);
            
            if ($count > 0) {
                // Verificar que no esté en el contenido limpio
                preg_match_all($pattern, $cleaned_content, $matches_cleaned);
                $count_cleaned = count($matches_cleaned[0]);
                
                if ($count > $count_cleaned) {
                    $removed_count = $count - $count_cleaned;
                    $detected[$attr] = $removed_count;
                }
            }
        }
        
        // Detectar IDs que empiezan con "model-response-message-contentr_"
        $id_pattern = '/\s+id\s*=\s*["\']model-response-message-contentr_[^"\']*["\']/i';
        preg_match_all($id_pattern, $original_content, $id_matches);
        $id_count = count($id_matches[0]);
        
        if ($id_count > 0) {
            preg_match_all($id_pattern, $cleaned_content, $id_matches_cleaned);
            $id_count_cleaned = count($id_matches_cleaned[0]);
            
            if ($id_count > $id_count_cleaned) {
                $removed_id_count = $id_count - $id_count_cleaned;
                $detected['id(model-response-message-contentr_*)'] = $removed_id_count;
            }
        }
        
        return $detected;
    }

    /**
     * Detectar caracteres Unicode invisibles eliminados.
     *
     * @param string $original_content
     * @param string $cleaned_content
     * @return array
     */
    private function detect_invisible_unicode_removed($original_content, $cleaned_content) {
        if (empty($original_content) || empty($cleaned_content)) {
            return array();
        }
        $detected = array();
        $cleaner = new LLM_Trace_Cleaner_Cleaner();
        $map = method_exists($cleaner, 'get_invisible_unicode_map') ? $cleaner->get_invisible_unicode_map() : array();
        if (empty($map)) {
            return array();
        }
        foreach ($map as $label => $pattern) {
            // contar en original
            preg_match_all($pattern, $original_content, $m1);
            $c1 = isset($m1[0]) ? count($m1[0]) : 0;
            if ($c1 <= 0) {
                continue;
            }
            // contar en limpio
            preg_match_all($pattern, $cleaned_content, $m2);
            $c2 = isset($m2[0]) ? count($m2[0]) : 0;
            if ($c1 > $c2) {
                $detected['unicode: ' . $label] = $c1 - $c2;
            }
        }
        return $detected;
    }

    /**
     * Formatear estadísticas con ubicaciones.
     *
     * @param array $stats Estadísticas de atributos eliminados.
     * @param array $locations Ubicaciones de los cambios.
     * @return string Detalles formateados con ubicaciones.
     */
    private function format_stats_with_locations($stats, $locations) {
        $parts = array();
        
        // Procesar atributos con ubicaciones
        foreach ($stats as $attr => $count) {
            // Saltar si es Unicode (se procesa después)
            if (strpos($attr, 'unicode:') === 0) {
                continue;
            }
            
            $part = sprintf('%s: %d eliminado%s', $attr, $count, $count > 1 ? 's' : '');
            
            // Añadir ubicaciones si están disponibles
            $location_key = 'attribute:' . $attr;
            if (isset($locations[$location_key])) {
                $location_parts = array();
                foreach ($locations[$location_key] as $loc => $loc_count) {
                    $location_parts[] = sprintf('%s (%d)', $loc, $loc_count);
                }
                if (!empty($location_parts)) {
                    // Limitar a 3 ubicaciones para no hacer el log muy largo
                    $part .= ' [en: ' . implode(', ', array_slice($location_parts, 0, 3)) . 
                            (count($location_parts) > 3 ? '...' : '') . ']';
                }
            }
            
            $parts[] = $part;
        }
        
        // Procesar Unicode con ubicaciones
        foreach ($stats as $key => $count) {
            if (strpos($key, 'unicode:') === 0) {
                $unicode_type = str_replace('unicode: ', '', $key);
                $part = sprintf('Unicode %s: %d eliminado%s', $unicode_type, $count, $count > 1 ? 's' : '');
                
                // Añadir ubicaciones si están disponibles
                $location_key = 'unicode:' . $unicode_type;
                if (isset($locations[$location_key])) {
                    $location_parts = array();
                    foreach ($locations[$location_key] as $loc => $loc_count) {
                        $location_parts[] = sprintf('%s (%d)', $loc, $loc_count);
                    }
                    if (!empty($location_parts)) {
                        $part .= ' [en: ' . implode(', ', array_slice($location_parts, 0, 3)) . 
                                (count($location_parts) > 3 ? '...' : '') . ']';
                    }
                }
                
                $parts[] = $part;
            }
        }
        
        // Si no hay stats pero hay ubicaciones, mostrar solo ubicaciones
        if (empty($parts) && !empty($locations)) {
            foreach ($locations as $key => $locs) {
                $type_parts = explode(':', $key, 2);
                $type = isset($type_parts[0]) ? $type_parts[0] : 'change';
                $item = isset($type_parts[1]) ? $type_parts[1] : '';
                
                $total = array_sum($locs);
                $part = sprintf('%s %s: %d cambio%s', 
                    ucfirst($type), 
                    $item, 
                    $total, 
                    $total > 1 ? 's' : ''
                );
                
                $location_parts = array();
                foreach ($locs as $loc => $loc_count) {
                    $location_parts[] = sprintf('%s (%d)', $loc, $loc_count);
                }
                if (!empty($location_parts)) {
                    $part .= ' [en: ' . implode(', ', array_slice($location_parts, 0, 3)) . 
                            (count($location_parts) > 3 ? '...' : '') . ']';
                }
                
                $parts[] = $part;
            }
        }
        
        return !empty($parts) ? implode('; ', $parts) : __('Contenido modificado (normalización de HTML o cambios menores)', 'llm-trace-cleaner');
    }
}

