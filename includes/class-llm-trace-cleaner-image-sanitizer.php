<?php
/**
 * Construye el plan de saneamiento a partir de un perfil.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

/**
 * No conoce Imagick/GD; solo produce un plan normalizado.
 */
class LLM_Trace_Cleaner_Image_Sanitizer {

	/**
	 * @param array $audit   Informe del inspector.
	 * @param array $profile Perfil.
	 * @param array $settings Settings del módulo.
	 * @return array {remove,preserve,set,warnings,blocked}
	 */
	public function build_plan( array $audit, array $profile, array $settings ) {
		$plan = array(
			'remove'   => array(),
			'preserve' => array(),
			'set'      => array(),
			'warnings' => array(),
			'blocked'  => false,
		);

		$c2pa_status = isset( $audit['c2pa']['status'] ) ? $audit['c2pa']['status'] : 'not_detected';
		if ( ! empty( $settings['stop_on_c2pa'] ) && in_array( $c2pa_status, array( 'possibly_detected', 'detected_unverified', 'verified', 'confirmed' ), true ) ) {
			$plan['blocked']    = true;
			$plan['warnings'][] = 'Procesamiento detenido: posible C2PA detectado. Re-encodear puede invalidar credenciales. Acción explícita requerida.';
			return $plan;
		}

		$rules = isset( $profile['rules'] ) && is_array( $profile['rules'] ) ? $profile['rules'] : array();

		$remove_map = array(
			'gps'              => array( 'gps', 'location.created.latitude', 'location.created.longitude', 'location.created.altitude' ),
			'device'           => array( 'capture.make', 'capture.model', 'capture.lens' ),
			'serial'           => array( 'capture.serial' ),
			'software'         => array( 'digital.software', 'digital.creator_tool' ),
			'capture_dates'    => array( 'capture.capture_date' ),
			'comments'         => array( 'comments', 'user_comment' ),
			'generator'        => array( 'generator', 'prompt', 'seed', 'workflow' ),
			'edit_history'     => array( 'edit_history', 'xmp_history' ),
			'unknown'          => array( 'unknown_profiles' ),
			'location_created' => array( 'location.created' ),
		);

		foreach ( $rules as $rule => $action ) {
			if ( 'remove' === $action || 'clear_if_matches_generator' === $action ) {
				if ( isset( $remove_map[ $rule ] ) ) {
					foreach ( $remove_map[ $rule ] as $field ) {
						$plan['remove'][] = $field;
					}
				} else {
					$plan['remove'][] = $rule;
				}
			} elseif ( 'keep' === $action ) {
				$plan['preserve'][] = $rule;
			}
		}

		if ( ! empty( $settings['preserve_icc'] ) ) {
			$plan['preserve'][] = 'icc';
			$plan['remove']     = array_values( array_diff( $plan['remove'], array( 'icc' ) ) );
		}
		if ( ! empty( $settings['preserve_orientation'] ) ) {
			$plan['preserve'][] = 'orientation';
		}
		if ( ! empty( $settings['preserve_copyright'] ) ) {
			$plan['preserve'][] = 'copyright';
			$plan['preserve'][] = 'rights';
		}

		$meta = isset( $profile['metadata'] ) && is_array( $profile['metadata'] ) ? $profile['metadata'] : array();
		$set_rules = array( 'creator', 'rights', 'content', 'location_shown', 'digital_source' );
		foreach ( $set_rules as $key ) {
			$action = isset( $rules[ $key ] ) ? $rules[ $key ] : '';
			if ( ! in_array( $action, array( 'set', 'set_if_empty', 'copy_from_wordpress' ), true ) ) {
				continue;
			}
			$value = null;
			if ( 'location_shown' === $key && isset( $meta['location']['shown'] ) ) {
				$value = array( 'location' => array( 'shown' => $meta['location']['shown'] ) );
			} elseif ( 'digital_source' === $key && isset( $meta['digital'] ) ) {
				$value = array( 'digital' => $meta['digital'] );
			} elseif ( isset( $meta[ $key ] ) ) {
				$value = array( $key => $meta[ $key ] );
			}
			if ( null === $value ) {
				continue;
			}
			$plan['set'][] = array(
				'action' => $action,
				'data'   => $value,
			);
		}

		// Nunca inventar captura.
		$plan['warnings'][] = 'No se escriben cámara, objetivo, serie, fecha o GPS de captura ficticios.';

		$plan['remove']   = array_values( array_unique( $plan['remove'] ) );
		$plan['preserve'] = array_values( array_unique( $plan['preserve'] ) );

		return $plan;
	}
}
