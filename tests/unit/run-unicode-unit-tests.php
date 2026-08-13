<?php
/**
 * Tests P0 Unicode contextual.
 * Ejecutar: php tests/unit/run-unicode-unit-tests.php
 */

define( 'ABSPATH', __DIR__ . '/' );

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		if ( ! is_array( $args ) ) {
			$args = array();
		}
		return array_merge( $defaults, $args );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		return $value;
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-llm-trace-cleaner-cleaner.php';

$fail = 0;

function assert_true( $cond, $msg ) {
	global $fail;
	if ( $cond ) {
		echo "OK  $msg\n";
	} else {
		echo "FAIL $msg\n";
		$fail++;
	}
}

function cp( $code ) {
	if ( function_exists( 'mb_chr' ) ) {
		return mb_chr( $code, 'UTF-8' );
	}
	return html_entity_decode( '&#' . (int) $code . ';', ENT_NOQUOTES, 'UTF-8' );
}

function clean_unicode_only( $html, $extra = array() ) {
	$cleaner = new LLM_Trace_Cleaner_Cleaner();
	$opts    = array_merge(
		array(
			'clean_attributes'          => false,
			'clean_unicode'             => true,
			'clean_content_references'  => false,
			'clean_utm_parameters'      => false,
			'track_locations'           => false,
		),
		$extra
	);
	return $cleaner->clean_html( $html, $opts );
}

$zwsp = cp( 0x200B );
$zwj  = cp( 0x200D );
$zwnj = cp( 0x200C );
$vs16 = cp( 0xFE0F );
$bom  = cp( 0xFEFF );

assert_true(
	clean_unicode_only( 'hola' . $zwsp . 'mundo' ) === 'holamundo',
	'ZWSP huérfano se elimina'
);

$man   = cp( 0x1F468 );
$woman = cp( 0x1F469 );
$girl  = cp( 0x1F467 );
$family = $man . $zwj . $woman . $zwj . $girl;
assert_true(
	clean_unicode_only( 'familia ' . $family ) === 'familia ' . $family,
	'ZWJ entre emoji de familia se preserva'
);

assert_true(
	clean_unicode_only( 'a' . $zwj . 'b' ) === 'ab',
	'ZWJ entre letras latinas se elimina'
);

$scales = cp( 0x2696 );
assert_true(
	clean_unicode_only( $scales . $vs16 ) === $scales . $vs16,
	'VS16 tras símbolo emoji se preserva'
);

assert_true(
	clean_unicode_only( 'x' . $vs16 . 'y' ) === 'xy',
	'VS16 huérfano entre letras se elimina'
);

$cgj = cp( 0x034F );
assert_true(
	clean_unicode_only( 'a' . $cgj . 'b' ) === 'ab',
	'Combining Grapheme Joiner huérfano se elimina'
);

$alm = cp( 0x061C );
assert_true(
	clean_unicode_only( 'hi' . $alm . 'there' ) === 'hithere',
	'Arabic Letter Mark huérfano se elimina'
);

$fa = cp( 0x2061 );
assert_true(
	clean_unicode_only( 'sin' . $fa . 'x' ) === 'sin' . $fa . 'x',
	'Function application entre visibles se preserva'
);

$iss = cp( 0x206A );
assert_true(
	clean_unicode_only( 'a' . $iss . 'b' ) === 'ab',
	'Inhibit Symmetric Swapping se elimina'
);

$ann = cp( 0xFFF9 );
assert_true(
	clean_unicode_only( 'a' . $ann . 'b' ) === 'ab',
	'Interlinear annotation se elimina'
);

$vs17 = cp( 0xE0100 );
assert_true(
	clean_unicode_only( 'x' . $vs17 . 'y' ) === 'xy',
	'VS17 huérfano se elimina'
);

$ideograph = cp( 0x4E00 );
assert_true(
	clean_unicode_only( $ideograph . $vs17 ) === $ideograph . $vs17,
	'VS17 tras ideógrafo se preserva'
);

$em_space = cp( 0x2003 );
assert_true(
	clean_unicode_only( 'hola' . $em_space . 'mundo' ) === 'hola mundo',
	'Em space se normaliza a espacio'
);

$ideo_space = cp( 0x3000 );
assert_true(
	clean_unicode_only( 'a' . $ideo_space . 'b' ) === 'a b',
	'Ideographic space se normaliza a espacio'
);

$nbsp = cp( 0x00A0 );
assert_true(
	clean_unicode_only( 'hola' . $nbsp . 'mundo' ) === 'hola' . $nbsp . 'mundo',
	'NBSP se preserva por defecto'
);

assert_true(
	clean_unicode_only( 'hola' . $nbsp . 'mundo', array( 'normalize_nbsp' => true ) ) === 'hola mundo',
	'NBSP se normaliza con normalize_nbsp'
);

$lre = cp( 0x202A );
$pdf = cp( 0x202C );
$arabic = cp( 0x0645 );
assert_true(
	clean_unicode_only( 'start' . $lre . $arabic . $pdf . 'end' ) === 'start' . $lre . $arabic . $pdf . 'end',
	'Bidi LRE/PDF equilibrado se preserva'
);

$rlo = cp( 0x202E );
assert_true(
	clean_unicode_only( 'abc' . $rlo . 'xyz' ) === 'abcxyz',
	'RLO huérfano se elimina'
);

$beh = cp( 0x0628 );
$yeh = cp( 0x064A );
assert_true(
	clean_unicode_only( $beh . $zwnj . $yeh ) === $beh . $zwnj . $yeh,
	'ZWNJ entre letras árabes se preserva'
);

assert_true(
	clean_unicode_only( 'foo' . $zwnj . 'bar' ) === 'foobar',
	'ZWNJ entre latín se elimina'
);

$tag_a = cp( 0xE0061 );
assert_true(
	clean_unicode_only( 'hi' . $tag_a . 'there' ) === 'hithere',
	'Tag character se elimina'
);

assert_true(
	clean_unicode_only( 'keep' . $bom ) === 'keep',
	'BOM se elimina'
);

$cleaner  = new LLM_Trace_Cleaner_Cleaner();
$analysis = $cleaner->analyze_content( 'familia ' . $family . $zwsp );
assert_true(
	isset( $analysis['unicode_found']['Zero Width Space (U+200B)'] ),
	'analyze cuenta ZWSP'
);
assert_true(
	empty( $analysis['unicode_found']['Zero Width Joiner (U+200D)'] ),
	'analyze no cuenta ZWJ de emoji preservado'
);

$analysis_zwj = $cleaner->analyze_content( 'a' . $zwj . 'b' );
assert_true(
	! empty( $analysis_zwj['unicode_found']['Zero Width Joiner (U+200D)'] ),
	'analyze sí cuenta ZWJ entre latín'
);

if ( $fail > 0 ) {
	echo "\n$fail fallos\n";
	exit( 1 );
}
echo "\nTodos los tests Unicode OK\n";
exit( 0 );
