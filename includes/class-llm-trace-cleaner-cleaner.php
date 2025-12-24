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
     * Añadir propiedades para rastrear ubicaciones
     */
    private $change_locations = array();
    
    /**
     * Limpiar HTML eliminando atributos de rastreo LLM
     *
     * @param string $html Contenido HTML a limpiar
     * @param array $options Opciones de limpieza ('clean_attributes', 'clean_unicode', 'track_locations')
     * @return string HTML limpio
     */
    public function clean_html($html, $options = array()) {
        if (empty($html)) {
            return $html;
        }
        
        // Opciones por defecto
        $default_options = array(
            'clean_attributes' => true,
            'clean_unicode' => true,
            'clean_content_references' => true,
            'clean_utm_parameters' => true,
            'track_locations' => true
        );
        $options = wp_parse_args($options, $default_options);
        
        // Resetear estadísticas y ubicaciones
        $this->last_stats = array();
        $this->change_locations = array();
        
        // Primero, decodificar secuencias Unicode como u003c, u003e, etc.
        // Esto corrige problemas donde el HTML aparece como u003ccodeu003e en lugar de <code>
        $html = $this->decode_unicode_sequences($html);
        
        // Extraer y preservar comentarios de bloques de Gutenberg
        // Esto evita que se eliminen o corrompan los bloques (ej: RankMath FAQ)
        $gutenberg_data = $this->extract_gutenberg_blocks($html);
        $html = $gutenberg_data['html'];
        
        // Guardar HTML original para rastrear ubicaciones
        $original_html = $html;
        
        // Limpiar atributos si está activado
        if ($options['clean_attributes']) {
            // Usar DOMDocument para un parsing robusto
            if (class_exists('DOMDocument')) {
                $cleaned_html = $this->clean_with_dom($html, $options);
            } else {
                // Fallback a expresiones regulares si DOMDocument no está disponible
                $cleaned_html = $this->clean_with_regex($html, $options);
            }
        } else {
            $cleaned_html = $html;
        }
        
        // Limpiar Unicode si está activado
        if ($options['clean_unicode']) {
            // #region agent log
            $log_dir = dirname(dirname(__DIR__)) . '/.cursor';
            if (!is_dir($log_dir)) {
                @mkdir($log_dir, 0755, true);
            }
            $log_file = $log_dir . '/debug.log';
            $unicode_before_clean = preg_match_all('/\x{200B}/u', $cleaned_html);
            $log_data = json_encode(array(
                'sessionId' => 'debug-session',
                'runId' => 'run1',
                'hypothesisId' => 'B',
                'location' => 'class-llm-trace-cleaner-cleaner.php:125',
                'message' => 'Antes de remove_invisible_unicode',
                'data' => array(
                    'clean_unicode_enabled' => $options['clean_unicode'],
                    'unicode_200B_count' => $unicode_before_clean,
                    'html_length' => strlen($cleaned_html)
                ),
                'timestamp' => round(microtime(true) * 1000)
            )) . "\n";
            @file_put_contents($log_file, $log_data, FILE_APPEND);
            // #endregion
            
            $cleaned_html = $this->remove_invisible_unicode($cleaned_html, $options);
            
            // #region agent log
            $unicode_after_clean = preg_match_all('/\x{200B}/u', $cleaned_html);
            $log_data = json_encode(array(
                'sessionId' => 'debug-session',
                'runId' => 'run1',
                'hypothesisId' => 'B',
                'location' => 'class-llm-trace-cleaner-cleaner.php:127',
                'message' => 'Después de remove_invisible_unicode',
                'data' => array(
                    'unicode_200B_before' => $unicode_before_clean,
                    'unicode_200B_after' => $unicode_after_clean,
                    'unicode_removed' => ($unicode_before_clean > $unicode_after_clean),
                    'html_length' => strlen($cleaned_html)
                ),
                'timestamp' => round(microtime(true) * 1000)
            )) . "\n";
            @file_put_contents($log_file, $log_data, FILE_APPEND);
            // #endregion
        }
        
        // Limpiar referencias de contenido (ContentReference) si está activado
        if ($options['clean_content_references']) {
            $cleaned_html = $this->remove_content_references($cleaned_html, $options);
        }
        
        // Limpiar parámetros UTM de enlaces si está activado
        if ($options['clean_utm_parameters']) {
            $cleaned_html = $this->remove_utm_parameters($cleaned_html, $options);
        }
        
        // Limpiar Unicode en los bloques de Gutenberg antes de restaurarlos
        // Esto evita que los caracteres Unicode se reintroduzcan al restaurar los bloques
        if ($options['clean_unicode'] && !empty($gutenberg_data['blocks'])) {
            $cleaned_blocks = array();
            foreach ($gutenberg_data['blocks'] as $block) {
                $cleaned_blocks[] = $this->remove_invisible_unicode($block, $options);
            }
            $gutenberg_data['blocks'] = $cleaned_blocks;
        }
        
        // Restaurar comentarios de bloques de Gutenberg después de la limpieza
        $cleaned_html = $this->restore_gutenberg_blocks(
            $cleaned_html, 
            $gutenberg_data['blocks'], 
            $gutenberg_data['placeholders']
        );
        
        return $cleaned_html;
    }
    
    /**
     * Extraer y preservar bloques completos de Gutenberg (comentarios + contenido)
     * Los bloques de Gutenberg usan comentarios HTML como: <!-- wp:namespace/block-name {...} -->
     * DOMDocument puede eliminar o corromper estos comentarios, por lo que preservamos todo el bloque
     * 
     * @param string $html Contenido HTML
     * @return array Array con 'html' (sin bloques), 'blocks' (bloques preservados) y 'placeholders'
     */
    private function extract_gutenberg_blocks($html) {
        $blocks = array();
        $placeholders = array();
        $counter = 0;
        
        // Primero, intentar capturar bloques con comentarios de Gutenberg
        // Formato: <!-- wp:namespace/block-name {...} --> ... contenido ... <!-- /wp:namespace/block-name -->
        $pattern = '/<!--\s*(wp:[^\s]+(?:\s+[^>]+)?)\s-->(.*?)<!--\s*(\/wp:[^\s]+)\s-->/s';
        
        // Procesar múltiples veces para manejar bloques anidados
        $max_iterations = 10; // Límite de seguridad
        $iteration = 0;
        
        while ($iteration < $max_iterations) {
            $found = false;
            $html = preg_replace_callback($pattern, function($matches) use (&$blocks, &$placeholders, &$counter, &$found) {
                // Verificar que el comentario de cierre corresponda al de apertura
                $opening_block = trim($matches[1]);
                $closing_block = trim($matches[3]);
                $content = $matches[2];
                
                // Extraer el nombre del bloque (después de wp:)
                preg_match('/wp:([^\s]+)/', $opening_block, $opening_match);
                preg_match('/\/wp:([^\s]+)/', $closing_block, $closing_match);
                
                // Solo preservar si los nombres coinciden
                if (isset($opening_match[1]) && isset($closing_match[1]) && $opening_match[1] === $closing_match[1]) {
                    // Verificar que el contenido no contenga placeholders ya procesados
                    // (para evitar procesar bloques anidados dos veces)
                    if (strpos($content, '[[LLM_TRACE_CLEANER_GUTENBERG_BLOCK_') === false) {
                        // Guardar el bloque completo: comentario de apertura + contenido + comentario de cierre
                        $full_block = $matches[0];
                        
                        // Usar placeholder de texto (no comentario HTML) para que DOMDocument no lo elimine
                        $placeholder = '[[LLM_TRACE_CLEANER_GUTENBERG_BLOCK_' . $counter . ']]';
                        
                        $blocks[] = $full_block;
                        $placeholders[] = $placeholder;
                        $counter++;
                        $found = true;
                        return $placeholder;
                    }
                }
                
                // Si no coinciden o ya tiene placeholder, mantener el original
                return $matches[0];
            }, $html, -1, $count);
            
            // Si no se encontraron más bloques, salir del bucle
            if ($count === 0 || !$found) {
                break;
            }
            
            $iteration++;
        }
        
        // Si no hay comentarios de Gutenberg, detectar bloques por clases CSS específicas
        // RankMath FAQ Block - formato wp-block-rank-math-faq-block
        $html = $this->extract_blocks_by_class($html, 'wp-block-rank-math-faq-block', $blocks, $placeholders, $counter);
        
        // RankMath FAQ Block - formato renderizado rank-math-block
        $html = $this->extract_blocks_by_class($html, 'rank-math-block', $blocks, $placeholders, $counter);
        
        // Page Builders: Extraer bloques de todos los page builders conocidos
        // Estos page builders generan estructuras complejas que pueden contener caracteres Unicode
        
        // Elementor
        $html = $this->extract_blocks_by_class($html, 'elementor-element', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class($html, 'elementor-widget', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class($html, 'elementor-section', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class($html, 'elementor-container', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class($html, 'elementor-column', $blocks, $placeholders, $counter);
        
        // Divi Builder
        $html = $this->extract_blocks_by_class($html, 'et_pb_section', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class($html, 'et_pb_row', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class($html, 'et_pb_column', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class($html, 'et_pb_module', $blocks, $placeholders, $counter);
        
        // Bricks Builder (usa prefijos de clase como brxe-container, brxe-section, etc.)
        $html = $this->extract_blocks_by_class_prefix($html, 'brxe-', $blocks, $placeholders, $counter);
        
        // WPBakery (Visual Composer)
        $html = $this->extract_blocks_by_class($html, 'vc_row', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class($html, 'vc_column', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class($html, 'wpb_', $blocks, $placeholders, $counter);
        
        // Beaver Builder
        $html = $this->extract_blocks_by_class($html, 'fl-row', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class($html, 'fl-col', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class($html, 'fl-module', $blocks, $placeholders, $counter);
        
        // Oxygen Builder (usa prefijos de clase como oxy-*)
        $html = $this->extract_blocks_by_class_prefix($html, 'oxy-', $blocks, $placeholders, $counter);
        
        // Thrive Architect
        $html = $this->extract_blocks_by_class_prefix($html, 'thrv_', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class($html, 'tve_', $blocks, $placeholders, $counter);
        
        // Brizy Builder (usa prefijos de clase como brz-*)
        $html = $this->extract_blocks_by_class_prefix($html, 'brz-', $blocks, $placeholders, $counter);
        
        // SiteOrigin Page Builder
        $html = $this->extract_blocks_by_class_prefix($html, 'siteorigin-panels-', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class($html, 'panel-grid', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class($html, 'panel-row-style', $blocks, $placeholders, $counter);
        
        // Kadence Blocks
        $html = $this->extract_blocks_by_class_prefix($html, 'kt-', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class_prefix($html, 'kadence-', $blocks, $placeholders, $counter);
        
        // GeneratePress Blocks
        $html = $this->extract_blocks_by_class_prefix($html, 'gb-', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class($html, 'generateblocks-', $blocks, $placeholders, $counter);
        
        // Astra Blocks
        $html = $this->extract_blocks_by_class_prefix($html, 'ast-', $blocks, $placeholders, $counter);
        
        // Spectra (Ultimate Addons for Gutenberg)
        $html = $this->extract_blocks_by_class_prefix($html, 'uagb-', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class($html, 'spectra-', $blocks, $placeholders, $counter);
        
        // Stackable
        $html = $this->extract_blocks_by_class_prefix($html, 'stk-', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class($html, 'wp-block-stk-', $blocks, $placeholders, $counter);
        
        // Zion Builder
        $html = $this->extract_blocks_by_class_prefix($html, 'zn-', $blocks, $placeholders, $counter);
        
        // Live Composer
        $html = $this->extract_blocks_by_class_prefix($html, 'dslc-', $blocks, $placeholders, $counter);
        
        // Themify Builder
        $html = $this->extract_blocks_by_class_prefix($html, 'themify_builder_', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class($html, 'module-', $blocks, $placeholders, $counter);
        
        // Cornerstone (X Theme)
        $html = $this->extract_blocks_by_class_prefix($html, 'x-', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class($html, 'cs-', $blocks, $placeholders, $counter);
        
        // Fusion Builder (Avada Theme)
        $html = $this->extract_blocks_by_class_prefix($html, 'fusion-', $blocks, $placeholders, $counter);
        $html = $this->extract_blocks_by_class($html, 'fusion_builder_', $blocks, $placeholders, $counter);
        
        // KingComposer
        $html = $this->extract_blocks_by_class_prefix($html, 'kc-', $blocks, $placeholders, $counter);
        
        // Qubely
        $html = $this->extract_blocks_by_class_prefix($html, 'qubely-', $blocks, $placeholders, $counter);
        
        // Gutentor
        $html = $this->extract_blocks_by_class_prefix($html, 'gutentor-', $blocks, $placeholders, $counter);
        
        // Neve Blocks
        $html = $this->extract_blocks_by_class_prefix($html, 'nv-', $blocks, $placeholders, $counter);
        
        // CoBlocks
        $html = $this->extract_blocks_by_class($html, 'wp-block-coblocks-', $blocks, $placeholders, $counter);
        
        // SeedProd
        $html = $this->extract_blocks_by_class_prefix($html, 'seedprod-', $blocks, $placeholders, $counter);
        
        // GreenShift (Animated Gutenberg Blocks)
        $html = $this->extract_blocks_by_class_prefix($html, 'gspb_', $blocks, $placeholders, $counter);
        
        // Getwid (Gutenberg Blocks)
        $html = $this->extract_blocks_by_class($html, 'wp-block-getwid-', $blocks, $placeholders, $counter);
        
        // Atomic Blocks
        $html = $this->extract_blocks_by_class($html, 'wp-block-atomic-blocks-', $blocks, $placeholders, $counter);
        
        // Advanced Gutenberg
        $html = $this->extract_blocks_by_class($html, 'wp-block-advgb-', $blocks, $placeholders, $counter);
        
        // Pootle Page Builder
        $html = $this->extract_blocks_by_class_prefix($html, 'ppb-', $blocks, $placeholders, $counter);
        
        // MotoPress Content Editor
        $html = $this->extract_blocks_by_class_prefix($html, 'mpce-', $blocks, $placeholders, $counter);
        
        // BoldGrid Post and Page Builder
        $html = $this->extract_blocks_by_class_prefix($html, 'boldgrid-', $blocks, $placeholders, $counter);
        
        // Page Builder Sandwich
        $html = $this->extract_blocks_by_class_prefix($html, 'pbs-', $blocks, $placeholders, $counter);
        
        // WP Page Builder (Themeum)
        $html = $this->extract_blocks_by_class_prefix($html, 'wppb-', $blocks, $placeholders, $counter);
        
        // Visual Composer Website Builder (nueva versión)
        $html = $this->extract_blocks_by_class_prefix($html, 'vce-', $blocks, $placeholders, $counter);
        
        // Gutenberg Block Collection
        $html = $this->extract_blocks_by_class($html, 'wp-block-block-collection-', $blocks, $placeholders, $counter);
        
        return array(
            'html' => $html,
            'blocks' => $blocks,
            'placeholders' => $placeholders
        );
    }
    
    /**
     * Extraer bloques completos por clase CSS específica
     * Útil cuando no hay comentarios de Gutenberg disponibles
     * 
     * @param string $html HTML a procesar
     * @param string $class_name Nombre de la clase CSS a buscar
     * @param array &$blocks Array de bloques preservados (por referencia)
     * @param array &$placeholders Array de placeholders (por referencia)
     * @param int &$counter Contador de placeholders (por referencia)
     * @return string HTML con bloques reemplazados por placeholders
     */
    private function extract_blocks_by_class($html, $class_name, &$blocks, &$placeholders, &$counter) {
        // Buscar todas las ocurrencias de divs con la clase específica
        $pattern = '/<div[^>]*class="[^"]*' . preg_quote($class_name, '/') . '[^"]*"[^>]*>/i';
        
        $offset = 0;
        while (preg_match($pattern, $html, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $match_pos = $matches[0][1];
            
            // Verificar que no esté dentro de un placeholder ya procesado
            $before_match = substr($html, 0, $match_pos);
            if (strpos($before_match, '[[LLM_TRACE_CLEANER_GUTENBERG_BLOCK_') !== false) {
                // Verificar si el placeholder más cercano está después de este match
                $last_placeholder_pos = strrpos($before_match, '[[LLM_TRACE_CLEANER_GUTENBERG_BLOCK_');
                if ($last_placeholder_pos !== false) {
                    $placeholder_end = strpos($html, ']]', $last_placeholder_pos);
                    if ($placeholder_end !== false && $placeholder_end > $match_pos) {
                        // Este match está dentro de un placeholder, saltarlo
                        $offset = $match_pos + strlen($matches[0][0]);
                        continue;
                    }
                }
            }
            
            // Extraer el bloque completo desde esta posición
            $full_block = $this->extract_complete_div_block($html, $match_pos);
            
            if ($full_block && strpos($full_block, '[[LLM_TRACE_CLEANER_GUTENBERG_BLOCK_') === false) {
                $placeholder = '[[LLM_TRACE_CLEANER_GUTENBERG_BLOCK_' . $counter . ']]';
                
                $blocks[] = $full_block;
                $placeholders[] = $placeholder;
                
                // Reemplazar el bloque con el placeholder
                $html = substr_replace($html, $placeholder, $match_pos, strlen($full_block));
                
                $counter++;
                $offset = $match_pos + strlen($placeholder);
            } else {
                $offset = $match_pos + strlen($matches[0][0]);
            }
        }
        
        return $html;
    }
    
    /**
     * Extraer bloques completos por prefijo de clase CSS
     * Útil para page builders como Bricks que usan prefijos (ej: brxe-container, brxe-section)
     * 
     * @param string $html HTML a procesar
     * @param string $class_prefix Prefijo de clase CSS a buscar
     * @param array &$blocks Array de bloques preservados (por referencia)
     * @param array &$placeholders Array de placeholders (por referencia)
     * @param int &$counter Contador de placeholders (por referencia)
     * @return string HTML con bloques reemplazados por placeholders
     */
    private function extract_blocks_by_class_prefix($html, $class_prefix, &$blocks, &$placeholders, &$counter) {
        // Buscar todas las ocurrencias de divs con clases que comienzan con el prefijo
        $pattern = '/<div[^>]*class="[^"]*' . preg_quote($class_prefix, '/') . '[^"]*"[^>]*>/i';
        
        $offset = 0;
        while (preg_match($pattern, $html, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $match_pos = $matches[0][1];
            
            // Verificar que no esté dentro de un placeholder ya procesado
            $before_match = substr($html, 0, $match_pos);
            if (strpos($before_match, '[[LLM_TRACE_CLEANER_GUTENBERG_BLOCK_') !== false) {
                // Verificar si el placeholder más cercano está después de este match
                $last_placeholder_pos = strrpos($before_match, '[[LLM_TRACE_CLEANER_GUTENBERG_BLOCK_');
                if ($last_placeholder_pos !== false) {
                    $placeholder_end = strpos($html, ']]', $last_placeholder_pos);
                    if ($placeholder_end !== false && $placeholder_end > $match_pos) {
                        // Este match está dentro de un placeholder, saltarlo
                        $offset = $match_pos + strlen($matches[0][0]);
                        continue;
                    }
                }
            }
            
            // Extraer el bloque completo desde esta posición
            $full_block = $this->extract_complete_div_block($html, $match_pos);
            
            if ($full_block && strpos($full_block, '[[LLM_TRACE_CLEANER_GUTENBERG_BLOCK_') === false) {
                $placeholder = '[[LLM_TRACE_CLEANER_GUTENBERG_BLOCK_' . $counter . ']]';
                
                $blocks[] = $full_block;
                $placeholders[] = $placeholder;
                
                // Reemplazar el bloque con el placeholder
                $html = substr_replace($html, $placeholder, $match_pos, strlen($full_block));
                
                $counter++;
                $offset = $match_pos + strlen($placeholder);
            } else {
                $offset = $match_pos + strlen($matches[0][0]);
            }
        }
        
        return $html;
    }
    
    /**
     * Extraer un bloque div completo contando las etiquetas de apertura y cierre
     * 
     * @param string $html HTML completo
     * @param int $start_pos Posición inicial del div de apertura
     * @return string|false Bloque completo o false si no se encuentra el cierre
     */
    private function extract_complete_div_block($html, $start_pos) {
        $pos = $start_pos;
        $depth = 0;
        $start = $start_pos;
        
        // Buscar la etiqueta de apertura completa
        $open_tag_end = strpos($html, '>', $pos);
        if ($open_tag_end === false) {
            return false;
        }
        
        // Verificar si es auto-cerrado
        $open_tag = substr($html, $pos, $open_tag_end - $pos + 1);
        if (preg_match('/\/\s*>$/', $open_tag)) {
            // Es auto-cerrado, devolver solo esta etiqueta
            return $open_tag;
        }
        
        $depth = 1;
        $pos = $open_tag_end + 1;
        
        // Buscar todas las etiquetas div hasta encontrar el cierre correspondiente
        while ($depth > 0 && $pos < strlen($html)) {
            // Buscar siguiente etiqueta div (apertura o cierre)
            $next_open = strpos($html, '<div', $pos);
            $next_close = strpos($html, '</div>', $pos);
            
            // Determinar cuál viene primero
            if ($next_open === false && $next_close === false) {
                // No hay más etiquetas, no se encontró el cierre
                return false;
            }
            
            if ($next_open === false) {
                // Solo hay cierre
                $depth--;
                if ($depth === 0) {
                    // Encontramos el cierre correspondiente
                    $end_pos = $next_close + 6; // 6 = longitud de '</div>'
                    return substr($html, $start, $end_pos - $start);
                }
                $pos = $next_close + 6;
            } elseif ($next_close === false) {
                // Solo hay apertura
                $depth++;
                $pos = strpos($html, '>', $next_open) + 1;
            } else {
                // Hay ambas, verificar cuál viene primero
                if ($next_open < $next_close) {
                    // Apertura viene primero
                    $depth++;
                    $pos = strpos($html, '>', $next_open) + 1;
                } else {
                    // Cierre viene primero
                    $depth--;
                    if ($depth === 0) {
                        // Encontramos el cierre correspondiente
                        $end_pos = $next_close + 6;
                        return substr($html, $start, $end_pos - $start);
                    }
                    $pos = $next_close + 6;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Restaurar bloques completos de Gutenberg después de la limpieza
     * 
     * @param string $html HTML limpio
     * @param array $blocks Array de bloques preservados
     * @param array $placeholders Array de placeholders
     * @return string HTML con bloques restaurados
     */
    private function restore_gutenberg_blocks($html, $blocks, $placeholders) {
        if (empty($blocks) || empty($placeholders)) {
            return $html;
        }
        
        // Restaurar los bloques en el orden inverso para evitar conflictos
        // si hay placeholders que contienen otros placeholders
        for ($i = count($placeholders) - 1; $i >= 0; $i--) {
            if (isset($placeholders[$i]) && isset($blocks[$i])) {
                // Buscar el placeholder (puede estar escapado como entidad HTML)
                $placeholder = $placeholders[$i];
                $placeholder_escaped = htmlspecialchars($placeholder, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                
                // Intentar reemplazar tanto el placeholder original como el escapado
                $html = str_replace($placeholder_escaped, $blocks[$i], $html);
                $html = str_replace($placeholder, $blocks[$i], $html);
            }
        }
        return $html;
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
     * @param array $options Opciones de limpieza
     * @return string HTML limpio
     */
    private function clean_with_dom($html, $options = array()) {
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
            $this->clean_element($element, $html, $options);
        }
        
        // Extraer el contenido del wrapper preservando placeholders de bloques de Gutenberg
        // Usamos saveHTML() y luego decodificamos para preservar placeholders de texto
        $cleaned_html = $dom->saveHTML($wrapper_element);
        
        // Remover el wrapper div que añadimos
        $cleaned_html = preg_replace('/^<div[^>]*id="llm-trace-cleaner-wrapper"[^>]*>/', '', $cleaned_html);
        $cleaned_html = preg_replace('/<\/div>$/', '', $cleaned_html);
        
        // Decodificar entidades HTML para preservar placeholders de texto
        $cleaned_html = html_entity_decode($cleaned_html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $cleaned_html;
    }
    
    /**
     * Limpiar un elemento DOM
     *
     * @param DOMElement $element Elemento a limpiar
     * @param string $original_html HTML original para rastrear ubicaciones
     * @param array $options Opciones de limpieza
     */
    private function clean_element($element, $original_html = '', $options = array()) {
        if (!$element->hasAttributes()) {
            return;
        }
        
        // Obtener ubicación del elemento si se requiere rastreo
        $location = null;
        if (!empty($options['track_locations']) && !empty($original_html)) {
            $location = $this->get_element_location($element, $original_html);
        }
        
        // Eliminar atributos específicos
        foreach ($this->get_attributes_to_remove() as $attr) {
            if ($element->hasAttribute($attr)) {
                $element->removeAttribute($attr);
                $this->increment_stat($attr);
                
                // Registrar ubicación si se requiere
                if ($location) {
                    $this->record_change_location('attribute', $attr, $location);
                }
            }
        }
        
        // Eliminar atributo id si coincide con el patrón
        if ($element->hasAttribute('id')) {
            $id_value = $element->getAttribute('id');
            if (preg_match($this->id_pattern, $id_value)) {
                $element->removeAttribute('id');
                $this->increment_stat('id(model-response-message-contentr_*)');
                
                // Registrar ubicación si se requiere
                if ($location) {
                    $this->record_change_location('attribute', 'id(model-response-message-contentr_*)', $location);
                }
            }
        }
    }
    
    /**
     * Limpiar usando expresiones regulares (fallback)
     *
     * @param string $html Contenido HTML
     * @param array $options Opciones de limpieza
     * @return string HTML limpio
     */
    private function clean_with_regex($html, $options = array()) {
        $cleaned = $html;
        
        // Eliminar cada atributo usando regex
        foreach ($this->get_attributes_to_remove() as $attr) {
            $pattern = '/\s+' . preg_quote($attr, '/') . '(?:\s*=\s*["\'][^"\']*["\'])?/i';
            $count = 0;
            $cleaned = preg_replace($pattern, '', $cleaned, -1, $count);
            if ($count > 0) {
                $this->increment_stat($attr, $count);
                
                // Registrar ubicación genérica para regex (no podemos determinar ubicación exacta)
                if (!empty($options['track_locations'])) {
                    $this->record_change_location('attribute', $attr, array(
                        'block_type' => 'HTML Element',
                        'block_name' => null,
                        'class' => null
                    ));
                }
            }
        }
        
        // Eliminar atributos id que coincidan con el patrón
        $pattern = '/\s+id\s*=\s*["\']model-response-message-contentr_[^"\']*["\']/i';
        $count = 0;
        $cleaned = preg_replace($pattern, '', $cleaned, -1, $count);
        if ($count > 0) {
            $this->increment_stat('id(model-response-message-contentr_*)', $count);
            
            // Registrar ubicación genérica
            if (!empty($options['track_locations'])) {
                $this->record_change_location('attribute', 'id(model-response-message-contentr_*)', array(
                    'block_type' => 'HTML Element',
                    'block_name' => null,
                    'class' => null
                ));
            }
        }
        
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
     * @param array $options Opciones de limpieza
     * @return string
     */
    private function remove_invisible_unicode($html, $options = array()) {
        // #region agent log
        $log_dir = dirname(dirname(__DIR__)) . '/.cursor';
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }
        $log_file = $log_dir . '/debug.log';
        $unicode_200B_before = preg_match_all('/\x{200B}/u', $html);
        $log_data = json_encode(array(
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'B',
            'location' => 'class-llm-trace-cleaner-cleaner.php:683',
            'message' => 'Inicio remove_invisible_unicode',
            'data' => array(
                'html_length' => strlen($html),
                'unicode_200B_count' => $unicode_200B_before
            ),
            'timestamp' => round(microtime(true) * 1000)
        )) . "\n";
        @file_put_contents($log_file, $log_data, FILE_APPEND);
        // #endregion
        
        $map = apply_filters('llm_trace_cleaner_unicode_map', $this->invisible_unicode_map);
        if (empty($map) || !is_array($map)) {
            // #region agent log
            $log_data = json_encode(array(
                'sessionId' => 'debug-session',
                'runId' => 'run1',
                'hypothesisId' => 'B',
                'location' => 'class-llm-trace-cleaner-cleaner.php:686',
                'message' => 'Mapa Unicode vacío o inválido',
                'data' => array('map_empty' => empty($map), 'is_array' => is_array($map)),
                'timestamp' => round(microtime(true) * 1000)
            )) . "\n";
            @file_put_contents($log_file, $log_data, FILE_APPEND);
            // #endregion
            return $html;
        }
        
        $total_removed = 0;
        foreach ($map as $label => $pattern) {
            $count = 0;
            $html = preg_replace($pattern, '', $html, -1, $count);
            if ($count > 0) {
                $total_removed += $count;
                $this->increment_stat('unicode: ' . $label, $count);
                
                // Registrar ubicación genérica para Unicode (no podemos determinar ubicación exacta sin DOM)
                if (!empty($options['track_locations'])) {
                    $this->record_change_location('unicode', $label, array(
                        'block_type' => 'Text Content',
                        'block_name' => null,
                        'class' => null
                    ));
                }
            }
        }
        
        // #region agent log
        $unicode_200B_after = preg_match_all('/\x{200B}/u', $html);
        $log_data = json_encode(array(
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'B',
            'location' => 'class-llm-trace-cleaner-cleaner.php:704',
            'message' => 'Fin remove_invisible_unicode',
            'data' => array(
                'unicode_200B_before' => $unicode_200B_before,
                'unicode_200B_after' => $unicode_200B_after,
                'total_removed' => $total_removed,
                'html_length' => strlen($html)
            ),
            'timestamp' => round(microtime(true) * 1000)
        )) . "\n";
        @file_put_contents($log_file, $log_data, FILE_APPEND);
        // #endregion
        
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
     * Eliminar referencias de contenido LLM (ContentReference)
     * Ejemplos: ContentReference [oaicite:=0](index=0), ContentReference [oaicite:0](index=0)
     *
     * @param string $html Contenido HTML
     * @param array $options Opciones de limpieza
     * @return string HTML sin referencias de contenido
     */
    private function remove_content_references($html, $options = array()) {
        // Patrones para detectar referencias de contenido LLM
        $patterns = array(
            // Formato estándar: ContentReference [oaicite:=0](index=0)
            '/ContentReference\s*\[\s*oaicite\s*[:=]\s*\d+\s*\]\s*\(\s*index\s*=\s*\d+\s*\)/i',
            // Variación sin "index=": ContentReference [oaicite:=0]()
            '/ContentReference\s*\[\s*oaicite\s*[:=]\s*\d+\s*\]\s*\(\s*\)/i',
            // Variación solo con oaicite: [oaicite:=0] o [oaicite:0]
            '/\[\s*oaicite\s*[:=]\s*\d+\s*\]/i',
        );
        
        $total_removed = 0;
        
        foreach ($patterns as $pattern) {
            $count = 0;
            $html = preg_replace($pattern, '', $html, -1, $count);
            if ($count > 0) {
                $total_removed += $count;
            }
        }
        
        if ($total_removed > 0) {
            $this->increment_stat('content_reference', $total_removed);
            
            // Registrar ubicación genérica
            if (!empty($options['track_locations'])) {
                $this->record_change_location('content_reference', 'ContentReference', array(
                    'block_type' => 'Text Content',
                    'block_name' => null,
                    'class' => null
                ));
            }
        }
        
        return $html;
    }

    /**
     * Eliminar parámetros UTM de los enlaces
     * Elimina parámetros como ?utm_source=chatgpt.com, ?utm_medium=chat, etc.
     *
     * @param string $html Contenido HTML
     * @param array $options Opciones de limpieza
     * @return string HTML sin parámetros UTM
     */
    private function remove_utm_parameters($html, $options = array()) {
        $total_removed = 0;
        
        // Procesar enlaces en atributos href
        $pattern = '/(<a[^>]+href=["\'])([^"\']+)(["\'])/i';
        $cleaned_html = preg_replace_callback($pattern, function($matches) use (&$total_removed) {
            $before = $matches[1]; // <a ... href="
            $url = $matches[2]; // URL completa
            $quote = $matches[3]; // " o '
            
            // Parsear la URL
            $parsed = parse_url($url);
            if ($parsed === false || !isset($parsed['query'])) {
                return $matches[0]; // No hay query string, devolver original
            }
            
            // Parsear los parámetros de la query string
            parse_str($parsed['query'], $params);
            
            // Contar parámetros UTM antes de eliminarlos
            $utm_count = 0;
            foreach ($params as $key => $value) {
                if (strpos($key, 'utm_') === 0) {
                    $utm_count++;
                    unset($params[$key]);
                }
            }
            
            if ($utm_count > 0) {
                $total_removed += $utm_count;
                
                // Reconstruir la URL sin parámetros UTM
                $new_query = !empty($params) ? http_build_query($params) : '';
                
                // Reconstruir la URL completa
                $new_url = $parsed['scheme'] . '://';
                if (isset($parsed['user'])) {
                    $new_url .= $parsed['user'];
                    if (isset($parsed['pass'])) {
                        $new_url .= ':' . $parsed['pass'];
                    }
                    $new_url .= '@';
                }
                $new_url .= $parsed['host'];
                if (isset($parsed['port'])) {
                    $new_url .= ':' . $parsed['port'];
                }
                if (isset($parsed['path'])) {
                    $new_url .= $parsed['path'];
                }
                if (!empty($new_query)) {
                    $new_url .= '?' . $new_query;
                }
                if (isset($parsed['fragment'])) {
                    $new_url .= '#' . $parsed['fragment'];
                }
                
                return $before . $new_url . $quote;
            }
            
            return $matches[0]; // No había parámetros UTM
        }, $html);
        
        // También procesar URLs en texto plano (no en atributos)
        $pattern2 = '/(https?:\/\/[^\s<>"\']+)/i';
        $cleaned_html = preg_replace_callback($pattern2, function($matches) use (&$total_removed) {
            $url = $matches[1];
            
            // Parsear la URL
            $parsed = parse_url($url);
            if ($parsed === false || !isset($parsed['query'])) {
                return $url; // No hay query string
            }
            
            // Parsear los parámetros
            parse_str($parsed['query'], $params);
            
            // Eliminar parámetros UTM
            $utm_count = 0;
            foreach ($params as $key => $value) {
                if (strpos($key, 'utm_') === 0) {
                    $utm_count++;
                    unset($params[$key]);
                }
            }
            
            if ($utm_count > 0) {
                $total_removed += $utm_count;
                
                // Reconstruir la URL
                $new_query = !empty($params) ? http_build_query($params) : '';
                
                $new_url = $parsed['scheme'] . '://';
                if (isset($parsed['user'])) {
                    $new_url .= $parsed['user'];
                    if (isset($parsed['pass'])) {
                        $new_url .= ':' . $parsed['pass'];
                    }
                    $new_url .= '@';
                }
                $new_url .= $parsed['host'];
                if (isset($parsed['port'])) {
                    $new_url .= ':' . $parsed['port'];
                }
                if (isset($parsed['path'])) {
                    $new_url .= $parsed['path'];
                }
                if (!empty($new_query)) {
                    $new_url .= '?' . $new_query;
                }
                if (isset($parsed['fragment'])) {
                    $new_url .= '#' . $parsed['fragment'];
                }
                
                return $new_url;
            }
            
            return $url; // No había parámetros UTM
        }, $cleaned_html);
        
        if ($total_removed > 0) {
            $this->increment_stat('utm_parameters', $total_removed);
            
            // Registrar ubicación genérica
            if (!empty($options['track_locations'])) {
                $this->record_change_location('utm_parameters', 'UTM Parameters', array(
                    'block_type' => 'Link',
                    'block_name' => null,
                    'class' => null
                ));
            }
        }
        
        return $cleaned_html;
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

    /**
     * Nuevo método para análisis previo
     *
     * @param string $html Contenido HTML a analizar
     * @return array Array con 'attributes_found', 'unicode_found', 'total_attributes', 'total_unicode'
     */
    public function analyze_content($html) {
        $analysis = array(
            'attributes_found' => array(),
            'unicode_found' => array(),
            'content_references_found' => array(),
            'utm_parameters_found' => array(),
            'total_attributes' => 0,
            'total_unicode' => 0,
            'total_content_references' => 0,
            'total_utm_parameters' => 0
        );
        
        // Analizar atributos sin limpiar
        $attributes_to_check = $this->get_attributes_to_remove();
        foreach ($attributes_to_check as $attr) {
            $pattern = '/\s+' . preg_quote($attr, '/') . '(?:\s*=\s*["\'][^"\']*["\'])?/i';
            preg_match_all($pattern, $html, $matches);
            $count = count($matches[0]);
            if ($count > 0) {
                $analysis['attributes_found'][$attr] = $count;
                $analysis['total_attributes'] += $count;
            }
        }
        
        // Analizar Unicode
        $unicode_map = $this->get_invisible_unicode_map();
        foreach ($unicode_map as $label => $pattern) {
            preg_match_all($pattern, $html, $matches);
            $count = count($matches[0]);
            if ($count > 0) {
                $analysis['unicode_found'][$label] = $count;
                $analysis['total_unicode'] += $count;
            }
        }
        
        // Analizar referencias de contenido (ContentReference)
        $content_ref_patterns = array(
            '/ContentReference\s*\[\s*oaicite\s*[:=]\s*\d+\s*\]\s*\(\s*index\s*=\s*\d+\s*\)/i',
            '/ContentReference\s*\[\s*oaicite\s*[:=]\s*\d+\s*\]\s*\(\s*\)/i',
            '/\[\s*oaicite\s*[:=]\s*\d+\s*\]/i',
        );
        
        foreach ($content_ref_patterns as $pattern) {
            preg_match_all($pattern, $html, $matches);
            $count = count($matches[0]);
            if ($count > 0) {
                $analysis['content_references_found']['ContentReference'] = 
                    ($analysis['content_references_found']['ContentReference'] ?? 0) + $count;
                $analysis['total_content_references'] += $count;
            }
        }
        
        // Analizar parámetros UTM en enlaces
        // Buscar en atributos href
        $pattern1 = '/(<a[^>]+href=["\'])([^"\']+)(["\'])/i';
        preg_match_all($pattern1, $html, $href_matches);
        $utm_urls = array();
        foreach ($href_matches[2] as $url) {
            $parsed = parse_url($url);
            if ($parsed !== false && isset($parsed['query'])) {
                parse_str($parsed['query'], $params);
                $has_utm = false;
                foreach ($params as $key => $value) {
                    if (strpos($key, 'utm_') === 0) {
                        $has_utm = true;
                        break;
                    }
                }
                if ($has_utm) {
                    $utm_urls[] = $url;
                }
            }
        }
        
        // Buscar en URLs en texto plano
        $pattern2 = '/(https?:\/\/[^\s<>"\']+)/i';
        preg_match_all($pattern2, $html, $url_matches);
        foreach ($url_matches[1] as $url) {
            // Verificar que no esté dentro de un atributo href (ya lo procesamos arriba)
            $parsed = parse_url($url);
            if ($parsed !== false && isset($parsed['query'])) {
                parse_str($parsed['query'], $params);
                $has_utm = false;
                foreach ($params as $key => $value) {
                    if (strpos($key, 'utm_') === 0) {
                        $has_utm = true;
                        break;
                    }
                }
                if ($has_utm) {
                    // Verificar que no esté en un href ya procesado
                    $pos = strpos($html, $url);
                    if ($pos !== false) {
                        $before = substr($html, max(0, $pos - 20), 20);
                        if (strpos($before, 'href=') === false) {
                            // Evitar duplicados
                            if (!in_array($url, $utm_urls)) {
                                $utm_urls[] = $url;
                            }
                        }
                    }
                }
            }
        }
        
        if (!empty($utm_urls)) {
            $analysis['utm_parameters_found']['UTM Parameters'] = count($utm_urls);
            $analysis['utm_urls_found'] = $utm_urls; // Guardar URLs completas
            $analysis['total_utm_parameters'] = count($utm_urls);
        }
        
        return $analysis;
    }

    /**
     * Nuevo método para obtener ubicación del elemento
     *
     * @param DOMElement $node Elemento DOM
     * @param string $original_html Contenido HTML original
     * @return array Array con 'tag', 'class', 'id', 'parent_tag', 'parent_class', 'block_type', 'block_name'
     */
    private function get_element_location($node, $original_html) {
        $location = array(
            'tag' => $node->tagName,
            'class' => $node->getAttribute('class'),
            'id' => $node->getAttribute('id'),
            'parent_tag' => $node->parentNode ? $node->parentNode->tagName : null,
            'parent_class' => $node->parentNode ? $node->parentNode->getAttribute('class') : null,
        );
        
        // Identificar tipo de bloque
        if (strpos($location['class'], 'wp-block-') !== false) {
            $location['block_type'] = 'Gutenberg Block';
            preg_match('/wp-block-([^\s]+)/', $location['class'], $matches);
            if (!empty($matches[1])) {
                $location['block_name'] = $matches[1];
            }
        } elseif (strpos($location['class'], 'rank-math') !== false) {
            $location['block_type'] = 'RankMath Block';
            if (strpos($location['class'], 'faq') !== false) {
                $location['block_name'] = 'FAQ';
            }
        } elseif ($location['tag'] === 'p') {
            $location['block_type'] = 'Paragraph';
        } elseif (in_array($location['tag'], array('h1', 'h2', 'h3', 'h4', 'h5', 'h6'))) {
            $location['block_type'] = 'Heading (' . strtoupper($location['tag']) . ')';
        } elseif ($location['tag'] === 'div') {
            $location['block_type'] = 'Div';
            if (!empty($location['class'])) {
                $location['block_name'] = $location['class'];
            }
        } elseif ($location['tag'] === 'span') {
            $location['block_type'] = 'Span';
        } else {
            $location['block_type'] = ucfirst($location['tag']) . ' Element';
        }
        
        return $location;
    }

    /**
     * Nuevo método para registrar ubicación de cambios
     *
     * @param string $type Tipo de cambio (e.g., 'attribute', 'unicode')
     * @param string $item Nombre del atributo/caracter Unicode
     * @param array $location Ubicación del cambio
     */
    private function record_change_location($type, $item, $location) {
        $key = $type . ':' . $item;
        if (!isset($this->change_locations[$key])) {
            $this->change_locations[$key] = array();
        }
        
        // Construir clave de ubicación más descriptiva
        $location_parts = array($location['block_type']);
        
        if (!empty($location['block_name'])) {
            $location_parts[] = '(' . $location['block_name'] . ')';
        }
        
        // Añadir clase si es relevante y no está ya incluida en block_name
        if (!empty($location['class']) && strpos($location['block_type'], $location['class']) === false) {
            // Limitar longitud de clase para evitar claves muy largas
            $class_display = strlen($location['class']) > 50 ? substr($location['class'], 0, 50) . '...' : $location['class'];
            $location_parts[] = 'class: ' . $class_display;
        }
        
        $location_key = implode(' ', $location_parts);
        
        if (!isset($this->change_locations[$key][$location_key])) {
            $this->change_locations[$key][$location_key] = 0;
        }
        $this->change_locations[$key][$location_key]++;
    }

    /**
     * Nuevo método para obtener ubicaciones
     *
     * @return array Array de ubicaciones
     */
    public function get_change_locations() {
        return $this->change_locations;
    }
}

