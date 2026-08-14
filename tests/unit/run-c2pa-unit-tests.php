<?php
/**
 * Tests P2: parser C2PA estructural (PNG caBX, JPEG APP11/JUMBF).
 * Ejecutar: php tests/unit/run-c2pa-unit-tests.php
 */

define( 'ABSPATH', __DIR__ . '/' );

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $d ) {
		return json_encode( $d );
	}
}
if ( ! function_exists( 'mb_substr' ) ) {
	function mb_substr( $s, $a, $b = null ) {
		return substr( $s, $a, $b );
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-llm-trace-cleaner-image-c2pa.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-llm-trace-cleaner-image-sanitizer.php';

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

/**
 * @param string $type    4 chars.
 * @param string $payload Payload.
 * @return string
 */
function png_chunk( $type, $payload ) {
	return pack( 'N', strlen( $payload ) ) . $type . $payload . pack( 'N', crc32( $type . $payload ) );
}

/**
 * @param array $extra Lista de [type, payload] tras IHDR y antes de IEND.
 * @return string
 */
function make_png( array $extra ) {
	$ihdr = pack( 'NNCCCCC', 1, 1, 8, 2, 0, 0, 0 );
	$out  = "\x89PNG\r\n\x1a\n" . png_chunk( 'IHDR', $ihdr );
	foreach ( $extra as $chunk ) {
		$out .= png_chunk( $chunk[0], $chunk[1] );
	}
	$out .= png_chunk( 'IEND', '' );
	return $out;
}

/**
 * @param int    $marker  Marker byte (p.ej. 0xEB).
 * @param string $payload Payload sin length.
 * @return string
 */
function jpeg_seg( $marker, $payload ) {
	return "\xFF" . chr( $marker ) . pack( 'n', strlen( $payload ) + 2 ) . $payload;
}

/**
 * @param array  $app     Segmentos APP/COM antes de SOS.
 * @param string $entropy Bytes tras SOS (no se deben escanear).
 * @return string
 */
function make_jpeg( array $app, $entropy = '' ) {
	$out = "\xFF\xD8";
	foreach ( $app as $seg ) {
		$out .= jpeg_seg( $seg[0], $seg[1] );
	}
	$out .= jpeg_seg( 0xDA, "\x01\x01\x00\x00\x00\x00" );
	$out .= $entropy;
	$out .= "\xFF\xD9";
	return $out;
}

$plain_png = make_png( array( array( 'IDAT', 'not-a-credential' ) ) );
$r         = LLM_Trace_Cleaner_Image_C2PA::inspect_bytes( $plain_png );
assert_true( 'not_detected' === $r['status'], 'PNG limpio: not_detected' );
assert_true( 'none' === $r['confidence'], 'PNG limpio: confidence none' );

$idat_trap = make_png( array( array( 'IDAT', 'xxxxc2pajumbcontentcredentialsyyyy' ) ) );
$r         = LLM_Trace_Cleaner_Image_C2PA::inspect_bytes( $idat_trap );
assert_true( 'not_detected' === $r['status'], 'no flag por c2pa dentro de IDAT' );
assert_true( 'none' === $r['confidence'], 'IDAT coincidencia: confidence none' );

$cabx = make_png(
	array(
		array( 'caBX', 'jumb-dummy-manifest' ),
		array( 'IDAT', 'pixels' ),
	)
);
$r    = LLM_Trace_Cleaner_Image_C2PA::inspect_bytes( $cabx );
assert_true( 'detected_unverified' === $r['status'], 'PNG caBX: detected_unverified' );
assert_true( 'confirmed' === $r['confidence'], 'PNG caBX: confirmed' );
assert_true( ! empty( $r['soft_binding_warning'] ), 'PNG caBX: aviso soft-binding' );

$jumb_png = make_png( array( array( 'juMB', 'x' ), array( 'IDAT', 'p' ) ) );
$r        = LLM_Trace_Cleaner_Image_C2PA::inspect_bytes( $jumb_png );
assert_true( 'confirmed' === $r['confidence'], 'PNG juMB: confirmed' );

$text_c2pa = make_png( array( array( 'tEXt', "Comment\0c2pa credentials" ), array( 'IDAT', 'p' ) ) );
$r         = LLM_Trace_Cleaner_Image_C2PA::inspect_bytes( $text_c2pa );
assert_true( 'possibly_detected' === $r['status'], 'PNG tEXt c2pa: possibly_detected' );
assert_true( 'probable' === $r['confidence'], 'PNG tEXt c2pa: probable' );

$soft_only = make_png( array( array( 'tEXt', "Comment\0soft-binding watermark" ), array( 'IDAT', 'p' ) ) );
$r         = LLM_Trace_Cleaner_Image_C2PA::inspect_bytes( $soft_only );
assert_true( 'not_detected' === $r['status'], 'solo soft-binding: no bloquea (not_detected)' );
assert_true( 'informational' === $r['confidence'], 'solo soft-binding: informational' );
assert_true( ! empty( $r['soft_binding_warning'] ), 'solo soft-binding: aviso presente' );

$plain_jpg = make_jpeg( array( array( 0xE0, "JFIF\0\x01\x02" ) ) );
$r         = LLM_Trace_Cleaner_Image_C2PA::inspect_bytes( $plain_jpg );
assert_true( 'not_detected' === $r['status'], 'JPEG limpio: not_detected' );

$sos_trap = make_jpeg( array( array( 0xE0, "JFIF\0" ) ), 'entropy-c2pa-jumb-here' );
$r        = LLM_Trace_Cleaner_Image_C2PA::inspect_bytes( $sos_trap );
assert_true( 'not_detected' === $r['status'], 'no flag por c2pa en SOS/entropy' );

$app11 = make_jpeg( array( array( 0xEB, "JP\0jumb\x00c2pa-store" ) ) );
$r     = LLM_Trace_Cleaner_Image_C2PA::inspect_bytes( $app11 );
assert_true( 'detected_unverified' === $r['status'], 'JPEG APP11+JUMBF: detected_unverified' );
assert_true( 'confirmed' === $r['confidence'], 'JPEG APP11+JUMBF: confirmed' );

$app11_empty = make_jpeg( array( array( 0xEB, "JP\0jpeg-360-other" ) ) );
$r           = LLM_Trace_Cleaner_Image_C2PA::inspect_bytes( $app11_empty );
assert_true( 'not_detected' === $r['status'], 'APP11 sin JUMBF/C2PA: no confirmed (evita JPEG 360)' );
assert_true( in_array( $r['confidence'], array( 'none', 'informational' ), true ), 'APP11 genérico: none o informational' );

$xmp = make_jpeg( array( array( 0xE1, "http://ns.adobe.com/xmp/1.0/\0<x:xmpmeta>c2pa</x:xmpmeta>" ) ) );
$r   = LLM_Trace_Cleaner_Image_C2PA::inspect_bytes( $xmp );
assert_true( 'possibly_detected' === $r['status'], 'JPEG APP1 XMP c2pa: possibly_detected' );
assert_true( 'probable' === $r['confidence'], 'JPEG APP1 XMP c2pa: probable' );

$from_meta = LLM_Trace_Cleaner_Image_C2PA::inspect_bytes(
	$plain_png,
	array( 'ClaimGenerator: c2pa-rs' )
);
assert_true( 'possibly_detected' === $from_meta['status'], 'string de metadatos c2pa: probable' );
assert_true( 'probable' === $from_meta['confidence'], 'metadatos: confidence probable' );

$sanitizer = new LLM_Trace_Cleaner_Image_Sanitizer();
$profile   = array( 'rules' => array( 'gps' => 'remove' ) );

$blocked_confirmed = $sanitizer->build_plan(
	array( 'c2pa' => array( 'status' => 'detected_unverified', 'confidence' => 'confirmed' ) ),
	$profile,
	array( 'stop_on_c2pa' => true )
);
assert_true( ! empty( $blocked_confirmed['blocked'] ), 'confirmed bloquea el plan' );

$blocked_probable = $sanitizer->build_plan(
	array( 'c2pa' => array( 'status' => 'possibly_detected', 'confidence' => 'probable' ) ),
	$profile,
	array( 'stop_on_c2pa' => true )
);
assert_true( ! empty( $blocked_probable['blocked'] ), 'probable bloquea el plan' );

$free_info = $sanitizer->build_plan(
	array( 'c2pa' => array( 'status' => 'not_detected', 'confidence' => 'informational' ) ),
	$profile,
	array( 'stop_on_c2pa' => true )
);
assert_true( empty( $free_info['blocked'] ), 'informational no bloquea el plan' );

if ( $fail > 0 ) {
	echo "\n$fail fallos\n";
	exit( 1 );
}
echo "\nTodos los tests C2PA OK\n";
exit( 0 );
