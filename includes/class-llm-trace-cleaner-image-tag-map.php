<?php
/**
 * Mapa modelo canónico → tags ExifTool / propiedades.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nunca incluye GPS de captura para ubicación mostrada.
 */
class LLM_Trace_Cleaner_Image_Tag_Map {

	/**
	 * Flatten modelo a lista path => value.
	 *
	 * @param array  $data   Modelo.
	 * @param string $prefix Prefix.
	 * @return array
	 */
	public static function flatten( array $data, $prefix = '' ) {
		$out = array();
		foreach ( $data as $key => $value ) {
			$path = $prefix ? $prefix . '.' . $key : $key;
			if ( is_array( $value ) && self::is_assoc( $value ) ) {
				$out = array_merge( $out, self::flatten( $value, $path ) );
			} elseif ( is_array( $value ) ) {
				$out[ $path ] = implode( ', ', array_map( 'strval', $value ) );
			} elseif ( null !== $value && '' !== $value ) {
				$out[ $path ] = (string) $value;
			}
		}
		return $out;
	}

	/**
	 * @param array $arr Array.
	 * @return bool
	 */
	private static function is_assoc( array $arr ) {
		if ( array() === $arr ) {
			return false;
		}
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}

	/**
	 * Tags ExifTool permitidos por path canónico.
	 * No incluye GPSLatitude/GPSLongitude para location.shown.*.
	 *
	 * @return array path => list of -Tag
	 */
	public static function exiftool_tags() {
		return array(
			'creator.name'                 => array( 'Artist', 'IPTC:By-line', 'XMP-dc:Creator' ),
			'creator.company'              => array( 'IPTC:By-lineTitle', 'XMP-photoshop:Credit' ),
			'creator.credit'               => array( 'IPTC:Credit', 'XMP-photoshop:Credit' ),
			'creator.source'               => array( 'IPTC:Source', 'XMP-photoshop:Source' ),
			'creator.website'              => array( 'XMP-xmp:BaseURL', 'IPTC:Source' ),
			'rights.copyright'             => array( 'Copyright', 'IPTC:CopyrightNotice', 'XMP-dc:Rights' ),
			'rights.license'               => array( 'XMP-xmpRights:UsageTerms' ),
			'rights.usage'                 => array( 'XMP-xmpRights:UsageTerms' ),
			'rights.rights_url'            => array( 'XMP-xmpRights:WebStatement' ),
			'content.title'                => array( 'IPTC:ObjectName', 'XMP-dc:Title', 'Title' ),
			'content.description'          => array( 'ImageDescription', 'IPTC:Caption-Abstract', 'XMP-dc:Description' ),
			'content.keywords'             => array( 'IPTC:Keywords', 'XMP-dc:Subject' ),
			'content.alt_text'             => array( 'XMP-dc:Description' ),
			'location.shown.name'          => array( 'XMP-iptcExt:LocationShown', 'IPTC:Sub-location' ),
			'location.shown.city'          => array( 'XMP-iptcExt:LocationShownCity', 'IPTC:City', 'City' ),
			'location.shown.region'        => array( 'XMP-iptcExt:LocationShownProvinceState', 'IPTC:Province-State' ),
			'location.shown.country'       => array( 'XMP-iptcExt:LocationShownCountryName', 'IPTC:Country-PrimaryLocationName' ),
			'location.shown.country_code'  => array( 'XMP-iptcExt:LocationShownCountryCode', 'IPTC:Country-PrimaryLocationCode' ),
			'digital.source_type'          => array( 'XMP-photoshop:Source', 'XMP-dc:Type' ),
		);
	}

	/**
	 * Construye args ExifTool `-TAG=value` desde modelo (whitelist).
	 *
	 * @param array $model Modelo sanitizado.
	 * @return array { args: string[], written: string[], skipped: string[] }
	 */
	public static function to_exiftool_args( array $model ) {
		$map     = self::exiftool_tags();
		$flat    = self::flatten( $model );
		$args    = array();
		$written = array();
		$skipped = array();

		foreach ( $flat as $path => $value ) {
			// Bloqueo duro GPS captura.
			if ( preg_match( '/latitude|longitude|altitude|gps/i', $path ) && 0 === strpos( $path, 'location.created' ) ) {
				$skipped[] = $path;
				continue;
			}
			if ( ! isset( $map[ $path ] ) ) {
				$skipped[] = $path;
				continue;
			}
			foreach ( $map[ $path ] as $tag ) {
				if ( ! preg_match( '/^[A-Za-z0-9_:\-]+$/', $tag ) ) {
					continue;
				}
				$args[]    = '-' . $tag . '=' . $value;
				$written[] = $path . '->' . $tag;
			}
		}

		return array(
			'args'    => $args,
			'written' => $written,
			'skipped' => $skipped,
		);
	}

	/**
	 * Props simples para Imagick setImageProperty.
	 *
	 * @param array $model Modelo.
	 * @return array { props: path=>exifKey, unsupported: [] }
	 */
	public static function to_imagick_props( array $model ) {
		$flat         = self::flatten( $model );
		$supported    = array(
			'creator.name'        => 'exif:Artist',
			'rights.copyright'    => 'exif:Copyright',
			'content.description' => 'exif:ImageDescription',
			'content.title'       => 'exif:ImageDescription',
		);
		$props        = array();
		$unsupported  = array();
		foreach ( $flat as $path => $value ) {
			if ( isset( $supported[ $path ] ) ) {
				$props[ $supported[ $path ] ] = $value;
			} else {
				$unsupported[] = $path;
			}
		}
		return array(
			'props'        => $props,
			'unsupported'  => $unsupported,
		);
	}
}
