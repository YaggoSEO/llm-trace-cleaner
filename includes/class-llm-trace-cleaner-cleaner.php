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
     * Atributos a eliminar (base). Se puede ampliar vía filtro 'llm_trace_cleaner_attributes'
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
        // Nuevos atributos
        'data-offset-key',
        'data-message-id',
        'data-sender',
        'data-role',
        'data-token-index',
        'data-model',
        'data-render-timestamp',
        'data-update-timestamp',
        'data-confidence',
        'data-temperature',
        'data-seed',
        'data-step',
        'data-lang',
        'data-format',
        'data-annotation',
        'data-reference',
        'data-version',
        'data-error',
        'data-stream-id',
        'data-chunk',
        'data-context-id',
        'data-user-id',
        'data-ui-state',
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
        
        // Eliminar caracteres Unicode invisibles y registrar estadísticas
        $cleaned_html = $this->remove_invisible_unicode($cleaned_html);
        
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
        foreach ($this->get_attributes_to_remove() as $attr) {
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
        foreach ($this->get_attributes_to_remove() as $attr) {
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
        
        // Eliminar caracteres Unicode invisibles y registrar estadísticas
        $cleaned = $this->remove_invisible_unicode($cleaned);
        
        return $cleaned;
    }

    /**
     * Mapa de caracteres Unicode invisibles a eliminar. Extensible vía filtro 'llm_trace_cleaner_unicode_map'.
     * Clave: etiqueta legible; Valor: patrón PCRE (con /u).
     */
    private $invisible_unicode_map = array(
        'Zero Width Space (U+200B)' => '/\x{200B}/u',
        'Zero Width Non-Joiner (U+200C)' => '/\x{200C}/u',
        'Zero Width Joiner (U+200D)' => '/\x{200D}/u',
        'Zero Width No-Break Space / BOM (U+FEFF)' => '/\x{FEFF}/u',
        'Word Joiner (U+2060)' => '/\x{2060}/u',
        'Soft Hyphen (U+00AD)' => '/\x{00AD}/u',
        'Invisible Separator (U+2063)' => '/\x{2063}/u',
        'Invisible Plus (U+2064)' => '/\x{2064}/u',
        'Invisible Times (U+2062)' => '/\x{2062}/u',
        'Left-to-Right Mark (U+200E)' => '/\x{200E}/u',
        'Right-to-Left Mark (U+200F)' => '/\x{200F}/u',
        'Left-to-Right Embedding (U+202A)' => '/\x{202A}/u',
        'Right-to-Left Embedding (U+202B)' => '/\x{202B}/u',
        'Pop Directional Formatting (U+202C)' => '/\x{202C}/u',
        'Left-to-Right Override (U+202D)' => '/\x{202D}/u',
        'Right-to-Left Override (U+202E)' => '/\x{202E}/u',
        'Bidirectional Isolates (U+2066–U+2069)' => '/[\x{2066}-\x{2069}]/u',
        'Mongolian Vowel Separator (U+180E)' => '/\x{180E}/u',
        'Tag Characters (U+E0000–U+E007F)' => '/[\x{E0000}-\x{E007F}]/u',
        'Invisible Ideographic Space (U+3000)' => '/\x{3000}/u',
        'Object Replacement Character (U+FFFC)' => '/\x{FFFC}/u',
        'Variation Selectors (U+FE00–U+FE0F)' => '/[\x{FE00}-\x{FE0F}]/u',
    );

    /**
     * Eliminar caracteres Unicode invisibles y acumular estadísticas.
     *
     * @param string $html
     * @return string
     */
    private function remove_invisible_unicode($html) {
        $map = apply_filters('llm_trace_cleaner_unicode_map', $this->invisible_unicode_map);
        if (empty($map) || !is_array($map)) {
            return $html;
        }
        foreach ($map as $label => $pattern) {
            $count = 0;
            $html = preg_replace($pattern, '', $html, -1, $count);
            if ($count > 0) {
                $this->increment_stat('unicode: ' . $label, $count);
            }
        }
        return $html;
    }

    /**
     * Obtener mapa de Unicode invisible (tras filtros)
     *
     * @return array
     */
    public function get_invisible_unicode_map() {
        return apply_filters('llm_trace_cleaner_unicode_map', $this->invisible_unicode_map);
    }

    /**
     * Obtener lista de atributos (después de aplicar filtros)
     *
     * @return array
     */
    public function get_attributes_to_remove() {
        $attrs = $this->attributes_to_remove;
        /**
         * Permite ampliar o modificar la lista de atributos a eliminar.
         *
         * @param array $attrs
         */
        $attrs = apply_filters('llm_trace_cleaner_attributes', $attrs);
        return array_values(array_unique(array_filter($attrs)));
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

