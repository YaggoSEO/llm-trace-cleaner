<?php
/**
 * Tests P1: data-ai*, JSON-LD de procedencia, generator CMS vs IA.
 * Ejecutar: php tests/unit/run-html-provenance-unit-tests.php
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
if ( ! function_exists( 'mb_convert_encoding' ) ) {
	function mb_convert_encoding( $s, $to, $from = null ) {
		return $s;
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

function clean_attrs( $html ) {
	$cleaner = new LLM_Trace_Cleaner_Cleaner();
	return $cleaner->clean_html(
		$html,
		array(
			'clean_attributes'         => true,
			'clean_unicode'            => false,
			'clean_content_references' => false,
			'clean_utm_parameters'     => false,
			'track_locations'          => false,
		)
	);
}

$out = clean_attrs( '<p data-ai-generated="true">hola</p>' );
assert_true( false === stripos( $out, 'data-ai-generated' ), 'quita data-ai-generated' );
assert_true( false !== strpos( $out, 'hola' ), 'conserva el texto' );

$out = clean_attrs( '<p data-ai-model="gpt-4" data-ai="1">hola</p>' );
assert_true( false === stripos( $out, 'data-ai-model' ), 'quita data-ai-model' );
assert_true( 1 !== preg_match( '/\sdata-ai=/i', $out ), 'quita data-ai exacto' );

$out = clean_attrs( '<p data-start="1" data-foo="ok">hola</p>' );
assert_true( false === stripos( $out, 'data-start' ), 'sigue quitando data-start' );
assert_true( false !== stripos( $out, 'data-foo' ), 'no toca data-foo' );

$out = clean_attrs( '<p data-air-quality="2">hola</p>' );
assert_true( false !== stripos( $out, 'data-air-quality' ), 'no confunde data-air con data-ai' );

$ai_json = '<script type="application/ld+json">{"@type":"ImageObject","digitalSourceType":"trainedAlgorithmicMedia","SoftwareAgent":"Midjourney"}</script><p>foto</p>';
$out     = clean_attrs( $ai_json );
assert_true( false === stripos( $out, 'trainedAlgorithmicMedia' ), 'quita JSON-LD de procedencia IA' );
assert_true( false !== strpos( $out, 'foto' ), 'conserva el resto tras JSON-LD IA' );

$article = '<script type="application/ld+json">{"@type":"Article","headline":"Hola mundo"}</script>';
$out     = clean_attrs( $article );
assert_true( false !== stripos( $out, 'Article' ), 'conserva JSON-LD de artículo' );
assert_true( false !== stripos( $out, 'Hola mundo' ), 'conserva headline JSON-LD' );

$out = clean_attrs( '<meta name="generator" content="WordPress 6.7">' );
assert_true( false !== stripos( $out, 'WordPress' ), 'conserva generator WordPress' );

$out = clean_attrs( '<meta name="generator" content="Elementor 3.24.0">' );
assert_true( false !== stripos( $out, 'Elementor' ), 'conserva generator Elementor' );

$out = clean_attrs( '<meta name="generator" content="ChatGPT">' );
assert_true( false === stripos( $out, 'ChatGPT' ), 'quita generator ChatGPT' );

$block = "<!-- wp:html -->\n<p data-ai-source=\"claude\">pegado</p>\n<!-- /wp:html -->";
$out   = clean_attrs( $block );
assert_true( false === stripos( $out, 'data-ai-source' ), 'quita data-ai* dentro de bloque HTML de Gutenberg' );
assert_true( false !== strpos( $out, 'pegado' ), 'conserva texto del bloque Gutenberg' );

$cleaner  = new LLM_Trace_Cleaner_Cleaner();
$analysis = $cleaner->analyze_content( '<p data-ai-model="x" data-start="1">z</p>' );
assert_true( ! empty( $analysis['attributes_found']['data-start'] ), 'analyze cuenta data-start' );
assert_true( $analysis['total_attributes'] >= 2, 'analyze cuenta data-ai* además de la lista fija' );

if ( $fail > 0 ) {
	echo "\n$fail fallos\n";
	exit( 1 );
}
echo "\nTodos los tests HTML provenance OK\n";
exit( 0 );
