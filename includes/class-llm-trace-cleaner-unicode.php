<?php
/**
 * Limpieza contextual de Unicode (Layer A).
 *
 * Preserva pegamento de emoji, ZWNJ en escrituras complejas y bidi
 * equilibrado. Elimina portadores huérfanos y normaliza espacios homógrafos.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clasificador y scrubber Unicode.
 */
class LLM_Trace_Cleaner_Unicode {

	const ACTION_KEEP    = 'keep';
	const ACTION_STRIP   = 'strip';
	const ACTION_REPLACE = 'replace';

	/**
	 * @param string $text    Texto UTF-8.
	 * @param array  $options Opciones.
	 * @return array { text: string, stats: array }
	 */
	public static function clean( $text, $options = array() ) {
		$result = self::process( $text, $options );
		return array(
			'text'  => $result['text'],
			'stats' => $result['stats'],
		);
	}

	/**
	 * Cuenta solo lo que se eliminaría o reemplazaría (no lo preservado).
	 *
	 * @param string $text    Texto UTF-8.
	 * @param array  $options Opciones.
	 * @return array { found: array, total: int }
	 */
	public static function inspect( $text, $options = array() ) {
		$result = self::process( $text, $options );
		return array(
			'found' => $result['stats'],
			'total' => (int) array_sum( $result['stats'] ),
		);
	}

	/**
	 * @param string $text    Texto.
	 * @param array  $options Opciones.
	 * @return array
	 */
	private static function process( $text, $options ) {
		$options = self::normalize_options( $options );
		$chars   = self::split_chars( $text );
		$n       = count( $chars );
		$balanced = self::balanced_bidi_indices( $chars );

		$out        = array();
		$stats      = array();
		$prev_kept  = null;

		for ( $i = 0; $i < $n; $i++ ) {
			$ch       = $chars[ $i ];
			$cp       = self::ord_cp( $ch );
			$decision = self::decide( $cp, $i, $chars, $prev_kept, $balanced, $options );

			switch ( $decision['action'] ) {
				case self::ACTION_KEEP:
					$out[] = $ch;
					if ( ! self::is_emoji_glue( $cp ) ) {
						$prev_kept = $ch;
					}
					break;
				case self::ACTION_REPLACE:
					$out[]     = $decision['out'];
					$prev_kept = $decision['out'];
					self::bump( $stats, $decision['label'] );
					break;
				case self::ACTION_STRIP:
					self::bump( $stats, $decision['label'] );
					break;
				default:
					$out[]     = $ch;
					$prev_kept = $ch;
					break;
			}
		}

		return array(
			'text'  => implode( '', $out ),
			'stats' => $stats,
		);
	}

	/**
	 * @param array $options Opciones crudas.
	 * @return array
	 */
	private static function normalize_options( $options ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		return array(
			'normalize_nbsp'   => ! empty( $options['normalize_nbsp'] ),
			'normalize_spaces' => ! isset( $options['normalize_spaces'] ) || $options['normalize_spaces'],
			'strip_emoji_glue' => ! empty( $options['strip_emoji_glue'] ),
		);
	}

	/**
	 * @param int        $cp        Codepoint.
	 * @param int        $i         Índice.
	 * @param string[]   $chars     Caracteres.
	 * @param string|null $prev_kept Último carácter no-glue conservado.
	 * @param array      $balanced  Índices bidi emparejados.
	 * @param array      $options   Opciones.
	 * @return array
	 */
	private static function decide( $cp, $i, array $chars, $prev_kept, array $balanced, array $options ) {
		$prev_ch = ( $i > 0 ) ? $chars[ $i - 1 ] : '';
		$next_ch = ( $i + 1 < count( $chars ) ) ? $chars[ $i + 1 ] : '';

		if ( self::is_emoji_glue( $cp ) && empty( $options['strip_emoji_glue'] ) ) {
			if ( is_string( $prev_kept ) && self::is_emoji_base( self::ord_cp( $prev_kept ) ) ) {
				return array(
					'action' => self::ACTION_KEEP,
					'out'    => $chars[ $i ],
					'label'  => '',
				);
			}
		}

		if ( self::is_space_homoglyph( $cp ) ) {
			if ( 0x00A0 === $cp && empty( $options['normalize_nbsp'] ) ) {
				return array(
					'action' => self::ACTION_KEEP,
					'out'    => $chars[ $i ],
					'label'  => '',
				);
			}
			if ( ! empty( $options['normalize_spaces'] ) ) {
				return array(
					'action' => self::ACTION_REPLACE,
					'out'    => ' ',
					'label'  => 'Space homoglyphs',
				);
			}
		}

		if ( self::should_preserve_semantic( $cp, $i, $prev_ch, $next_ch, $balanced ) ) {
			return array(
				'action' => self::ACTION_KEEP,
				'out'    => $chars[ $i ],
				'label'  => '',
			);
		}

		if ( self::is_strip_cp( $cp ) ) {
			return array(
				'action' => self::ACTION_STRIP,
				'out'    => '',
				'label'  => self::label_for_cp( $cp ),
			);
		}

		return array(
			'action' => self::ACTION_KEEP,
			'out'    => $chars[ $i ],
			'label'  => '',
		);
	}

	/**
	 * @param int    $cp       Codepoint.
	 * @param int    $i        Índice.
	 * @param string $prev_ch  Anterior.
	 * @param string $next_ch  Siguiente.
	 * @param array  $balanced Índices bidi.
	 * @return bool
	 */
	private static function should_preserve_semantic( $cp, $i, $prev_ch, $next_ch, array $balanced ) {
		if ( isset( $balanced[ $i ] ) ) {
			return true;
		}

		if ( 0x200C === $cp ) {
			return self::is_complex_script_letter( self::ord_cp( $prev_ch ) )
				|| self::is_complex_script_letter( self::ord_cp( $next_ch ) );
		}

		if ( $cp >= 0x2061 && $cp <= 0x2064 ) {
			return self::is_visible_neighbor( $prev_ch ) && self::is_visible_neighbor( $next_ch );
		}

		if ( 0x034F === $cp ) {
			return self::is_combining_mark( $prev_ch ) || self::is_combining_mark( $next_ch );
		}

		if ( in_array( $cp, array( 0x061C, 0x200E, 0x200F ), true ) ) {
			return self::is_direction_boundary( $prev_ch, $next_ch );
		}

		if ( $cp >= 0x180B && $cp <= 0x180D ) {
			$prev_cp = self::ord_cp( $prev_ch );
			return $prev_cp >= 0x1800 && $prev_cp <= 0x18AF;
		}

		if ( 0x180E === $cp ) {
			return self::is_mongolian( self::ord_cp( $prev_ch ) ) || self::is_mongolian( self::ord_cp( $next_ch ) );
		}

		if ( ( $cp >= 0xFE00 && $cp <= 0xFE0D ) || ( $cp >= 0xE0100 && $cp <= 0xE01EF ) ) {
			return self::is_variation_base( self::ord_cp( $prev_ch ) );
		}

		return false;
	}

	/**
	 * @param int $cp Codepoint.
	 * @return bool
	 */
	public static function is_strip_cp( $cp ) {
		if ( isset( self::strip_set()[ $cp ] ) ) {
			return true;
		}
		if ( $cp >= 0xE0001 && $cp <= 0xE007F ) {
			return true;
		}
		if ( $cp >= 0xE0100 && $cp <= 0xE01EF ) {
			return true;
		}
		return false;
	}

	/**
	 * Caracteres que se pueden borrar en decode sin contexto.
	 *
	 * @param int $cp Codepoint.
	 * @return bool
	 */
	public static function is_always_strip( $cp ) {
		if ( self::is_emoji_glue( $cp ) || 0x200C === $cp ) {
			return false;
		}
		if ( $cp >= 0x202A && $cp <= 0x202E ) {
			return false;
		}
		if ( $cp >= 0x2061 && $cp <= 0x2069 ) {
			return false;
		}
		if ( 0x034F === $cp || 0x061C === $cp || 0x200E === $cp || 0x200F === $cp ) {
			return false;
		}
		if ( ( $cp >= 0xFE00 && $cp <= 0xFE0F ) || ( $cp >= 0xE0100 && $cp <= 0xE01EF ) ) {
			return false;
		}
		return self::is_strip_cp( $cp );
	}

	/**
	 * @return array<int, true>
	 */
	private static function strip_set() {
		static $set = null;
		if ( null !== $set ) {
			return $set;
		}
		$codes = array(
			0x00AD, 0x034F, 0x061C, 0x115F, 0x1160, 0x17B4, 0x17B5,
			0x180B, 0x180C, 0x180D, 0x180E,
			0x200B, 0x200C, 0x200D, 0x200E, 0x200F,
			0x202A, 0x202B, 0x202C, 0x202D, 0x202E,
			0x2060, 0x2061, 0x2062, 0x2063, 0x2064,
			0x2066, 0x2067, 0x2068, 0x2069,
			0x206A, 0x206B, 0x206C, 0x206D, 0x206E, 0x206F,
			0xFEFF, 0xFFFC,
			0xFE00, 0xFE01, 0xFE02, 0xFE03, 0xFE04, 0xFE05, 0xFE06, 0xFE07,
			0xFE08, 0xFE09, 0xFE0A, 0xFE0B, 0xFE0C, 0xFE0D, 0xFE0E, 0xFE0F,
			0xFFF9, 0xFFFA, 0xFFFB,
		);
		$set = array();
		foreach ( $codes as $code ) {
			$set[ $code ] = true;
		}
		return $set;
	}

	/**
	 * @param int $cp Codepoint.
	 * @return bool
	 */
	private static function is_emoji_glue( $cp ) {
		return 0x200D === $cp || 0xFE0E === $cp || 0xFE0F === $cp;
	}

	/**
	 * @param int $cp Codepoint.
	 * @return bool
	 */
	private static function is_emoji_base( $cp ) {
		if ( $cp >= 0x1F000 && $cp <= 0x1FAFF ) {
			return true;
		}
		if ( $cp >= 0x2600 && $cp <= 0x27BF ) {
			return true;
		}
		if ( $cp >= 0x2B00 && $cp <= 0x2BFF ) {
			return true;
		}
		if ( $cp >= 0x1F1E6 && $cp <= 0x1F1FF ) {
			return true;
		}
		if ( in_array( $cp, array( 0x00A9, 0x00AE, 0x2122, 0x3030, 0x303D, 0x3297, 0x3299 ), true ) ) {
			return true;
		}
		if ( 0x0023 === $cp || 0x002A === $cp || ( $cp >= 0x0030 && $cp <= 0x0039 ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param int $cp Codepoint.
	 * @return bool
	 */
	private static function is_space_homoglyph( $cp ) {
		if ( 0x00A0 === $cp || 0x1680 === $cp || 0x202F === $cp || 0x205F === $cp || 0x3000 === $cp ) {
			return true;
		}
		return $cp >= 0x2000 && $cp <= 0x200A;
	}

	/**
	 * @param int $cp Codepoint.
	 * @return bool
	 */
	private static function is_complex_script_letter( $cp ) {
		if ( $cp < 1 ) {
			return false;
		}
		return ( $cp >= 0x0590 && $cp <= 0x0DFF )
			|| ( $cp >= 0x0E80 && $cp <= 0x0EFF )
			|| ( $cp >= 0x0F00 && $cp <= 0x0FFF )
			|| ( $cp >= 0x1000 && $cp <= 0x109F )
			|| ( $cp >= 0x1780 && $cp <= 0x17FF )
			|| ( $cp >= 0x1800 && $cp <= 0x18AF )
			|| ( $cp >= 0xFB1D && $cp <= 0xFDFF )
			|| ( $cp >= 0xFE70 && $cp <= 0xFEFC );
	}

	/**
	 * @param int $cp Codepoint.
	 * @return bool
	 */
	private static function is_rtl_letter( $cp ) {
		return ( $cp >= 0x0590 && $cp <= 0x08FF )
			|| ( $cp >= 0xFB1D && $cp <= 0xFDFF )
			|| ( $cp >= 0xFE70 && $cp <= 0xFEFC );
	}

	/**
	 * @param int $cp Codepoint.
	 * @return bool
	 */
	private static function is_mongolian( $cp ) {
		return $cp >= 0x1800 && $cp <= 0x18AF;
	}

	/**
	 * @param int $cp Codepoint.
	 * @return bool
	 */
	private static function is_variation_base( $cp ) {
		if ( $cp < 1 ) {
			return false;
		}
		return ( $cp >= 0x2E80 && $cp <= 0x9FFF )
			|| ( $cp >= 0xF900 && $cp <= 0xFAFF )
			|| ( $cp >= 0x3400 && $cp <= 0x4DBF );
	}

	/**
	 * @param string $ch Carácter.
	 * @return bool
	 */
	private static function is_visible_neighbor( $ch ) {
		if ( '' === $ch || null === $ch ) {
			return false;
		}
		if ( preg_match( '/\s/u', $ch ) ) {
			return false;
		}
		return 1 !== preg_match( '/^\p{C}$/u', $ch );
	}

	/**
	 * @param string $ch Carácter.
	 * @return bool
	 */
	private static function is_combining_mark( $ch ) {
		if ( '' === $ch ) {
			return false;
		}
		return 1 === preg_match( '/^\p{M}$/u', $ch );
	}

	/**
	 * @param string $prev_ch Anterior.
	 * @param string $next_ch Siguiente.
	 * @return bool
	 */
	private static function is_direction_boundary( $prev_ch, $next_ch ) {
		if ( ! self::is_visible_neighbor( $prev_ch ) || ! self::is_visible_neighbor( $next_ch ) ) {
			return false;
		}
		$left_rtl  = self::is_rtl_letter( self::ord_cp( $prev_ch ) );
		$right_rtl = self::is_rtl_letter( self::ord_cp( $next_ch ) );
		return $left_rtl !== $right_rtl;
	}

	/**
	 * @param string[] $chars Caracteres.
	 * @return array<int, true>
	 */
	private static function balanced_bidi_indices( array $chars ) {
		$embed_open   = array( 0x202A => true, 0x202B => true, 0x202D => true, 0x202E => true );
		$isolate_open = array( 0x2066 => true, 0x2067 => true, 0x2068 => true );
		$stack        = array();
		$balanced     = array();

		foreach ( $chars as $i => $ch ) {
			$cp = self::ord_cp( $ch );
			if ( isset( $embed_open[ $cp ] ) ) {
				$stack[] = array( 'embed', $i );
			} elseif ( isset( $isolate_open[ $cp ] ) ) {
				$stack[] = array( 'isolate', $i );
			} elseif ( 0x202C === $cp && ! empty( $stack ) && 'embed' === $stack[ count( $stack ) - 1 ][0] ) {
				$start = array_pop( $stack );
				$balanced[ $start[1] ] = true;
				$balanced[ $i ]        = true;
			} elseif ( 0x2069 === $cp && ! empty( $stack ) && 'isolate' === $stack[ count( $stack ) - 1 ][0] ) {
				$start = array_pop( $stack );
				$balanced[ $start[1] ] = true;
				$balanced[ $i ]        = true;
			}
		}

		return $balanced;
	}

	/**
	 * @param string $text Texto.
	 * @return string[]
	 */
	private static function split_chars( $text ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return array();
		}
		$parts = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $parts ) ? $parts : array();
	}

	/**
	 * @param string $ch Carácter.
	 * @return int
	 */
	private static function ord_cp( $ch ) {
		if ( ! is_string( $ch ) || '' === $ch ) {
			return 0;
		}
		if ( function_exists( 'mb_ord' ) ) {
			$ord = mb_ord( $ch, 'UTF-8' );
			return false === $ord ? 0 : $ord;
		}
		if ( class_exists( 'IntlChar' ) ) {
			$ord = IntlChar::ord( $ch );
			if ( is_int( $ord ) ) {
				return $ord;
			}
		}
		$bytes = unpack( 'C*', $ch );
		if ( ! is_array( $bytes ) ) {
			return 0;
		}
		$b  = array_values( $bytes );
		$c0 = $b[0];
		if ( $c0 < 0x80 ) {
			return $c0;
		}
		if ( $c0 < 0xE0 && isset( $b[1] ) ) {
			return ( ( $c0 & 0x1F ) << 6 ) | ( $b[1] & 0x3F );
		}
		if ( $c0 < 0xF0 && isset( $b[1], $b[2] ) ) {
			return ( ( $c0 & 0x0F ) << 12 ) | ( ( $b[1] & 0x3F ) << 6 ) | ( $b[2] & 0x3F );
		}
		if ( isset( $b[1], $b[2], $b[3] ) ) {
			return ( ( $c0 & 0x07 ) << 18 ) | ( ( $b[1] & 0x3F ) << 12 ) | ( ( $b[2] & 0x3F ) << 6 ) | ( $b[3] & 0x3F );
		}
		return 0;
	}

	/**
	 * @param array  $stats Stats.
	 * @param string $label Etiqueta.
	 */
	private static function bump( array &$stats, $label ) {
		if ( '' === $label ) {
			return;
		}
		if ( ! isset( $stats[ $label ] ) ) {
			$stats[ $label ] = 0;
		}
		$stats[ $label ]++;
	}

	/**
	 * @param int $cp Codepoint.
	 * @return string
	 */
	private static function label_for_cp( $cp ) {
		static $labels = array(
			0x00AD => 'Soft Hyphen (U+00AD)',
			0x034F => 'Combining Grapheme Joiner (U+034F)',
			0x061C => 'Arabic Letter Mark (U+061C)',
			0x180E => 'Mongolian Vowel Separator (U+180E)',
			0x200B => 'Zero Width Space (U+200B)',
			0x200C => 'Zero Width Non-Joiner (U+200C)',
			0x200D => 'Zero Width Joiner (U+200D)',
			0x200E => 'Left-to-Right Mark (U+200E)',
			0x200F => 'Right-to-Left Mark (U+200F)',
			0x202A => 'Left-to-Right Embedding (U+202A)',
			0x202B => 'Right-to-Left Embedding (U+202B)',
			0x202C => 'Pop Directional Formatting (U+202C)',
			0x202D => 'Left-to-Right Override (U+202D)',
			0x202E => 'Right-to-Left Override (U+202E)',
			0x2060 => 'Word Joiner (U+2060)',
			0x2061 => 'Function Application (U+2061)',
			0x2062 => 'Invisible Times (U+2062)',
			0x2063 => 'Invisible Separator (U+2063)',
			0x2064 => 'Invisible Plus (U+2064)',
			0xFEFF => 'Zero Width No-Break Space / BOM (U+FEFF)',
			0xFFFC => 'Object Replacement Character (U+FFFC)',
		);
		if ( isset( $labels[ $cp ] ) ) {
			return $labels[ $cp ];
		}
		if ( $cp >= 0x2066 && $cp <= 0x2069 ) {
			return 'Bidirectional Isolates (U+2066–U+2069)';
		}
		if ( $cp >= 0x206A && $cp <= 0x206F ) {
			return 'Deprecated format controls (U+206A–U+206F)';
		}
		if ( $cp >= 0xFE00 && $cp <= 0xFE0F ) {
			return 'Variation Selectors (U+FE00–U+FE0F)';
		}
		if ( $cp >= 0xE0000 && $cp <= 0xE007F ) {
			return 'Tag Characters (U+E0000–U+E007F)';
		}
		if ( $cp >= 0xE0100 && $cp <= 0xE01EF ) {
			return 'Variation Selectors (U+E0100–U+E01EF)';
		}
		if ( $cp >= 0xFFF9 && $cp <= 0xFFFB ) {
			return 'Interlinear annotation (U+FFF9–U+FFFB)';
		}
		return sprintf( 'U+%04X', $cp );
	}
}
