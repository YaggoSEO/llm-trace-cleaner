<?php
/**
 * Tests unitarios mínimos sin bootstrap WordPress completo.
 * Ejecutar: php tests/unit/run-image-unit-tests.php
 */

define( 'ABSPATH', __DIR__ . '/' );

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) { return is_string( $s ) ? trim( strip_tags( $s ) ) : $s; }
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $s ) { return sanitize_text_field( $s ); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $s ) { return filter_var( $s, FILTER_SANITIZE_URL ); }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $d ) { return json_encode( $d ); }
}
if ( ! function_exists( 'mb_substr' ) ) {
	function mb_substr( $s, $a, $b = null ) { return substr( $s, $a, $b ); }
}

require_once dirname( __DIR__, 2 ) . '/includes/class-llm-trace-cleaner-image-inspector.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-llm-trace-cleaner-image-sanitizer.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-llm-trace-cleaner-image-capabilities.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-llm-trace-cleaner-image-profile.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-llm-trace-cleaner-image-tag-map.php';

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

assert_true( LLM_Trace_Cleaner_Image_Inspector::is_valid_latitude( 40.4 ), 'lat válida' );
assert_true( ! LLM_Trace_Cleaner_Image_Inspector::is_valid_latitude( 100 ), 'lat inválida' );
assert_true( LLM_Trace_Cleaner_Image_Inspector::is_valid_longitude( -3.7 ), 'lng válida' );
assert_true( ! LLM_Trace_Cleaner_Image_Inspector::is_valid_longitude( -200 ), 'lng inválida' );

$inspector = new LLM_Trace_Cleaner_Image_Inspector();
$score = $inspector->score_risk(
	array(
		'sensitive'       => array( array( 'type' => 'gps' ) ),
		'generator_hints' => array( array( 'pattern' => 'prompt' ) ),
	)
);
assert_true( $score === 45, "risk score gps+prompt = $score" );
assert_true( LLM_Trace_Cleaner_Image_Inspector::risk_level( 45 ) === 'high', 'risk level high' );

$patterns = LLM_Trace_Cleaner_Image_Inspector::generator_patterns();
assert_true( in_array( 'comfyui', $patterns, true ), 'pattern comfyui' );

$profile = array(
	'rules'    => array(
		'gps'       => 'remove',
		'generator' => 'remove',
		'icc'       => 'keep',
		'creator'   => 'set',
	),
	'metadata' => array(
		'creator' => array( 'name' => 'Test' ),
	),
);
$sanitizer = new LLM_Trace_Cleaner_Image_Sanitizer();
$plan      = $sanitizer->build_plan(
	array( 'c2pa' => array( 'status' => 'not_detected' ) ),
	$profile,
	array(
		'preserve_icc' => true,
		'stop_on_c2pa' => true,
	)
);
assert_true( in_array( 'gps', $plan['remove'], true ) || in_array( 'location.created.latitude', $plan['remove'], true ), 'plan remove gps' );
assert_true( in_array( 'icc', $plan['preserve'], true ), 'plan preserve icc' );
assert_true( ! empty( $plan['set'] ), 'plan has set' );

$blocked = $sanitizer->build_plan(
	array( 'c2pa' => array( 'status' => 'possibly_detected' ) ),
	$profile,
	array( 'stop_on_c2pa' => true )
);
assert_true( ! empty( $blocked['blocked'] ), 'c2pa blocks plan' );

$defaults = LLM_Trace_Cleaner_Image_Capabilities::default_settings();
assert_true( false === $defaults['enabled'], 'default disabled' );
assert_true( true === $defaults['dry_run'], 'default dry_run' );
assert_true( true === $defaults['stop_on_c2pa'], 'default stop_on_c2pa' );

$builtins = LLM_Trace_Cleaner_Image_Profile::builtins();
assert_true( isset( $builtins['privacy'], $builtins['corporate'], $builtins['seo_local'], $builtins['photography'], $builtins['ai_generated'] ), '5 perfiles built-in' );

$mapped = LLM_Trace_Cleaner_Image_Tag_Map::to_exiftool_args(
	array(
		'creator'  => array( 'name' => 'Ada' ),
		'location' => array( 'shown' => array( 'city' => 'A Coruña', 'country_code' => 'ES' ) ),
	)
);
assert_true( ! empty( $mapped['args'] ), 'tag-map genera args' );
$joined = implode( ' ', $mapped['args'] );
assert_true( false !== strpos( $joined, 'Artist=' ) || false !== strpos( $joined, '-Artist=' ), 'tag-map artist' );
assert_true( false === strpos( strtolower( $joined ), 'gpslatitude' ), 'tag-map no GPS captura' );
assert_true( false !== strpos( $joined, 'LocationShownCity' ) || false !== strpos( $joined, 'City=' ), 'tag-map city shown' );

$imagick_map = LLM_Trace_Cleaner_Image_Tag_Map::to_imagick_props(
	array(
		'creator' => array( 'name' => 'Ada' ),
		'location' => array( 'shown' => array( 'city' => 'A Coruña' ) ),
	)
);
assert_true( isset( $imagick_map['props']['exif:Artist'] ), 'imagick artist prop' );
assert_true( in_array( 'location.shown.city', $imagick_map['unsupported'], true ), 'imagick city unsupported' );

$meta = LLM_Trace_Cleaner_Image_Profile::sanitize_metadata(
	array(
		'location' => array(
			'shown' => array( 'city' => 'A Coruña', 'country_code' => 'es' ),
			'created' => array( 'latitude' => 43.3 ),
		),
	)
);
assert_true( isset( $meta['location']['shown']['country_code'] ) && 'ES' === $meta['location']['shown']['country_code'], 'country code upper' );
assert_true( ! isset( $meta['location']['created'] ), 'sanitize drops created GPS' );

echo $fail ? "\n$fail failed\n" : "\nAll passed\n";
exit( $fail ? 1 : 0 );
