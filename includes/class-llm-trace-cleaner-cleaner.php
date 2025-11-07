<?php
/**
 * Clase para limpiar atributos de rastreo LLM del HTML
 *
 * @package LLM_Trace_Cleaner
 */

defined('ABSPATH') || exit;

/**
 * Clase LLM_Trace_Cleaner_Cleaner
 */
class LLM_Trace_Cleaner_Cleaner {
    
    /**
     * Atributos a eliminar
     */
    private $attributes_to_remove = array(
        'data-start',
        'data-end',
        'data-is-last-node',
        'data-is-only-node',
        'data-llm',
        'data-pm-slice',
        'data-llm-id',
        'data-llm-trace',
        'data-original-text',
        'data-source-text',
        'data-highlight',
        'data-entity',
        'data-mention',
    );
    
    /**
     * Patrón para IDs que empiezan con "model-response-message-contentr_"
     */
    private $id_pattern = '/^model-response-message-contentr_/';
    
    /**
     * Estadísticas de la última limpieza
     */
    private $last_stats = array();
    
    /**
     * Limpiar HTML eliminando atributos de rastreo LLM
     *
     * @param string $html Contenido HTML a limpiar
     * @return string HTML limpio
     */
    public function clean_html($html) {
        if (empty($html)) {
            return $html;
        }
        
        // Resetear estadísticas
        $this->last_stats = array();
        
        // Usar DOMDocument para un parsing robusto
        if (class_exists('DOMDocument')) {
            return $this->clean_with_dom($html);
        } else {
            // Fallback a expresiones regulares si DOMDocument no está disponible
            return $this->clean_with_regex($html);
        }
    }
    
    /**
     * Limpiar usando DOMDocument (método preferido)
     *
     * @param string $html Contenido HTML
     * @return string HTML limpio
     */
    private function clean_with_dom($html) {
        // Crear contexto para manejar HTML fragmentado
        $dom = new DOMDocument();
        
        // Suprimir errores de HTML mal formado
        libxml_use_internal_errors(true);
        
        // Codificar como UTF-8 y envolver en un contenedor temporal
        $html_encoded = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        
        // Intentar cargar el HTML
        // Usamos un wrapper para manejar fragmentos HTML
        $wrapper = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="llm-trace-cleaner-wrapper">' . $html_encoded . '</div></body></html>';
        
        @$dom->loadHTML($wrapper);
        
        // Limpiar errores de libxml
        libxml_clear_errors();
        
        // Obtener el wrapper
        $wrapper_element = $dom->getElementById('llm-trace-cleaner-wrapper');
        
        if (!$wrapper_element) {
            // Si falla DOMDocument, usar regex como fallback
            return $this->clean_with_regex($html);
        }
        
        // Procesar todos los elementos
        $xpath = new DOMXPath($dom);
        $elements = $xpath->query('//*');
        
        foreach ($elements as $element) {
            $this->clean_element($element);
        }
        
        // Extraer el contenido del wrapper
        $cleaned_html = '';
        foreach ($wrapper_element->childNodes as $child) {
            $cleaned_html .= $dom->saveHTML($child);
        }
        
        return $cleaned_html;
    }
    
    /**
     * Limpiar un elemento DOM
     *
     * @param DOMElement $element Elemento a limpiar
     */
    private function clean_element($element) {
        if (!$element->hasAttributes()) {
            return;
        }
        
        // Eliminar atributos específicos
        foreach ($this->attributes_to_remove as $attr) {
            if ($element->hasAttribute($attr)) {
                $element->removeAttribute($attr);
                $this->increment_stat($attr);
            }
        }
        
        // Eliminar atributo id si coincide con el patrón
        if ($element->hasAttribute('id')) {
            $id_value = $element->getAttribute('id');
            if (preg_match($this->id_pattern, $id_value)) {
                $element->removeAttribute('id');
                $this->increment_stat('id(model-response-message-contentr_*)');
            }
        }
    }
    
    /**
     * Limpiar usando expresiones regulares (fallback)
     *
     * @param string $html Contenido HTML
     * @return string HTML limpio
     */
    private function clean_with_regex($html) {
        $cleaned = $html;
        
        // Eliminar cada atributo usando regex
        foreach ($this->attributes_to_remove as $attr) {
            $pattern = '/\s+' . preg_quote($attr, '/') . '(?:\s*=\s*["\'][^"\']*["\'])?/i';
            $count = 0;
            $cleaned = preg_replace($pattern, '', $cleaned, -1, $count);
            if ($count > 0) {
                $this->increment_stat($attr, $count);
            }
        }
        
        // Eliminar atributos id que coincidan con el patrón
        $pattern = '/\s+id\s*=\s*["\']model-response-message-contentr_[^"\']*["\']/i';
        $count = 0;
        $cleaned = preg_replace($pattern, '', $cleaned, -1, $count);
        if ($count > 0) {
            $this->increment_stat('id(model-response-message-contentr_*)', $count);
        }
        
        return $cleaned;
    }
    
    /**
     * Incrementar contador de estadísticas
     *
     * @param string $key Clave del atributo
     * @param int $count Cantidad a incrementar
     */
    private function increment_stat($key, $count = 1) {
        if (!isset($this->last_stats[$key])) {
            $this->last_stats[$key] = 0;
        }
        $this->last_stats[$key] += $count;
    }
    
    /**
     * Obtener estadísticas de la última limpieza
     *
     * @return array Estadísticas
     */
    public function get_last_stats() {
        return $this->last_stats;
    }
    
    /**
     * Formatear estadísticas como texto para el log
     *
     * @param array $stats Estadísticas
     * @return string Texto formateado
     */
    public function format_stats($stats) {
        if (empty($stats)) {
            return 'Ningún atributo eliminado';
        }
        
        $parts = array();
        foreach ($stats as $attr => $count) {
            $parts[] = sprintf('%s: %d eliminado%s', $attr, $count, $count > 1 ? 's' : '');
        }
        
        return implode('; ', $parts);
    }
}

