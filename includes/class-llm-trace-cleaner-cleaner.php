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
        
        // Primero, decodificar secuencias Unicode como u003c, u003e, etc.
        // Esto corrige problemas donde el HTML aparece como u003ccodeu003e en lugar de <code>
        $html = $this->decode_unicode_sequences($html);
        
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
     * Decodificar secuencias Unicode como u003c, u003e, etc. a sus caracteres correspondientes
     * Esto corrige problemas donde el HTML aparece mal formateado después de eliminar Unicode
     *
     * @param string $html Contenido HTML
     * @return string HTML con secuencias Unicode decodificadas
     */
    private function decode_unicode_sequences($html) {
        // Decodificar diferentes formatos de secuencias Unicode:
        // 1. Formato u003c (sin backslash)
        // 2. Formato \u003c (con backslash)
        // 3. Formato &#x003c; (entidad HTML hexadecimal)
        // 4. Formato &#60; (entidad HTML decimal)
        
        // Formato 1: u003c (sin backslash) - el más común en el problema reportado
        $html = preg_replace_callback('/u([0-9a-fA-F]{4})/u', function($matches) {
            return $this->decode_unicode_char($matches[1]);
        }, $html);
        
        // Formato 2: \u003c (con backslash) - formato estándar de escape Unicode
        $html = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/u', function($matches) {
            return $this->decode_unicode_char($matches[1]);
        }, $html);
        
        // Formato 3: &#x003c; (entidad HTML hexadecimal) - ya debería estar manejado por html_entity_decode
        // pero lo verificamos por si acaso
        $html = preg_replace_callback('/&#x([0-9a-fA-F]{1,6});/iu', function($matches) {
            $code = intval($matches[1], 16);
            // Solo decodificar si no es un carácter invisible que estamos eliminando
            if (!$this->is_invisible_unicode_char($code)) {
                return $this->decode_unicode_char($matches[1], 16);
            }
            return $matches[0]; // Mantener si es invisible
        }, $html);
        
        return $html;
    }
    
    /**
     * Decodificar un código Unicode a su carácter correspondiente
     *
     * @param string $hex_code Código hexadecimal (4 dígitos)
     * @param int $base Base numérica (16 para hexadecimal, 10 para decimal)
     * @return string Carácter decodificado
     */
    private function decode_unicode_char($hex_code, $base = 16) {
        $code = intval($hex_code, $base);
        
        // No decodificar caracteres invisibles que estamos eliminando
        if ($this->is_invisible_unicode_char($code)) {
            return ''; // Eliminar caracteres invisibles
        }
        
        // Solo decodificar si es un carácter válido
        if ($code >= 32 && $code <= 126) { // Caracteres ASCII imprimibles
            return chr($code);
        } elseif ($code > 126 && $code <= 0x10FFFF) { // Caracteres Unicode válidos
            // Para caracteres Unicode más allá de ASCII, usar mb_chr si está disponible
            if (function_exists('mb_chr')) {
                return mb_chr($code, 'UTF-8');
            } else {
                // Fallback: convertir a entidad HTML
                return '&#x' . str_pad(dechex($code), 4, '0', STR_PAD_LEFT) . ';';
            }
        }
        
        return ''; // Eliminar caracteres inválidos
    }
    
    /**
     * Verificar si un código Unicode es un carácter invisible que estamos eliminando
     *
     * @param int $code Código Unicode
     * @return bool True si es un carácter invisible que eliminamos
     */
    private function is_invisible_unicode_char($code) {
        // Lista de códigos Unicode invisibles que eliminamos
        $invisible_codes = array(
            0x200B, // Zero Width Space
            0x200C, // Zero Width Non-Joiner
            0x200D, // Zero Width Joiner
            0xFEFF, // Zero Width No-Break Space / BOM
            0x2060, // Word Joiner
            0x00AD, // Soft Hyphen
            0x2063, // Invisible Separator
            0x2064, // Invisible Plus
            0x2062, // Invisible Times
            0x200E, // Left-to-Right Mark
            0x200F, // Right-to-Left Mark
            0x202A, // Left-to-Right Embedding
            0x202B, // Right-to-Left Embedding
            0x202C, // Pop Directional Formatting
            0x202D, // Left-to-Right Override
            0x202E, // Right-to-Left Override
            0x180E, // Mongolian Vowel Separator
            0x3000, // Invisible Ideographic Space
            0xFFFC, // Object Replacement Character
        );
        
        // Rangos de caracteres invisibles
        if (($code >= 0x2066 && $code <= 0x2069) || // Bidirectional Isolates
            ($code >= 0xE0000 && $code <= 0xE007F) || // Tag Characters
            ($code >= 0xFE00 && $code <= 0xFE0F)) { // Variation Selectors
            return true;
        }
        
        return in_array($code, $invisible_codes);
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
        
        // Asegurar que las entidades HTML estén correctamente formateadas después de eliminar Unicode
        // Decodificar entidades HTML que puedan haberse corrompido
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Volver a codificar solo las entidades necesarias para mantener el HTML válido
        // Esto asegura que < y > se mantengan como caracteres, no como entidades
        $html = str_replace(
            array('&lt;', '&gt;', '&amp;', '&quot;', '&#039;'),
            array('<', '>', '&', '"', "'"),
            $html
        );
        
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

