<?php
/**
 * Interfaz de motores de procesamiento de imágenes.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Contrato común para Imagick, GD y futuros motores.
 */
interface LLM_Trace_Cleaner_Image_Engine_Interface {

	/**
	 * @return bool
	 */
	public function is_available();

	/**
	 * @param string $mime MIME type.
	 * @return bool
	 */
	public function supports( $mime );

	/**
	 * Lectura de metadatos (no modifica el archivo).
	 *
	 * @param string $path Ruta absoluta.
	 * @return array
	 */
	public function inspect( $path );

	/**
	 * Aplica un plan de saneamiento escribiendo en $destination.
	 *
	 * @param string $source      Origen.
	 * @param string $destination Destino (puede ser temporal).
	 * @param array  $plan        Plan {remove,preserve,set,warnings}.
	 * @return array Resultado con written/removed/warnings/success.
	 */
	public function sanitize( $source, $destination, array $plan );

	/**
	 * Escribe metadatos canónicos en un archivo existente.
	 *
	 * @param string $path     Ruta.
	 * @param array  $metadata Modelo canónico o subset.
	 * @return array
	 */
	public function write_metadata( $path, array $metadata );

	/**
	 * @return array Capabilidades del motor.
	 */
	public function get_capabilities();

	/**
	 * @return string
	 */
	public function get_name();
}
