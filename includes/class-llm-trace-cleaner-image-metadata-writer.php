<?php
/**
 * Valida y entrega metadatos canónicos al motor.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Writer de metadatos.
 */
class LLM_Trace_Cleaner_Image_Metadata_Writer {

	/**
	 * Modelo canónico vacío.
	 *
	 * @return array
	 */
	public static function empty_model() {
		return array(
			'creator'  => array(
				'name'    => '',
				'company' => '',
				'credit'  => '',
				'source'  => '',
				'website' => '',
			),
			'rights'   => array(
				'copyright'  => '',
				'license'    => '',
				'usage'      => '',
				'rights_url' => '',
			),
			'content'  => array(
				'title'       => '',
				'headline'    => '',
				'description' => '',
				'keywords'    => array(),
				'alt_text'    => '',
			),
			'location' => array(
				'created' => array(
					'city'         => '',
					'region'       => '',
					'country'      => '',
					'country_code' => '',
					'latitude'     => null,
					'longitude'    => null,
					'altitude'     => null,
				),
				'shown'   => array(
					'name'         => '',
					'city'         => '',
					'region'       => '',
					'country'      => '',
					'country_code' => '',
				),
			),
			'capture'  => array(
				'make'         => '',
				'model'        => '',
				'lens'         => '',
				'serial'       => '',
				'capture_date' => '',
			),
			'digital'  => array(
				'software'      => '',
				'creator_tool'  => '',
				'source_type'   => '',
				'modified_date' => '',
			),
		);
	}

	/**
	 * Sanitiza un modelo parcial.
	 *
	 * @param array $data Data.
	 * @return array
	 */
	public function sanitize_model( array $data ) {
		$clean = array();
		if ( isset( $data['creator'] ) && is_array( $data['creator'] ) ) {
			foreach ( array( 'name', 'company', 'credit', 'source' ) as $k ) {
				if ( ! empty( $data['creator'][ $k ] ) ) {
					$clean['creator'][ $k ] = sanitize_text_field( $data['creator'][ $k ] );
				}
			}
			if ( ! empty( $data['creator']['website'] ) ) {
				$clean['creator']['website'] = esc_url_raw( $data['creator']['website'] );
			}
		}
		if ( isset( $data['rights'] ) && is_array( $data['rights'] ) ) {
			foreach ( array( 'copyright', 'license', 'usage' ) as $k ) {
				if ( ! empty( $data['rights'][ $k ] ) ) {
					$clean['rights'][ $k ] = sanitize_textarea_field( $data['rights'][ $k ] );
				}
			}
			if ( ! empty( $data['rights']['rights_url'] ) ) {
				$clean['rights']['rights_url'] = esc_url_raw( $data['rights']['rights_url'] );
			}
		}
		if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
			foreach ( array( 'title', 'headline', 'alt_text' ) as $k ) {
				if ( ! empty( $data['content'][ $k ] ) ) {
					$clean['content'][ $k ] = sanitize_text_field( $data['content'][ $k ] );
				}
			}
			if ( ! empty( $data['content']['description'] ) ) {
				$clean['content']['description'] = sanitize_textarea_field( $data['content']['description'] );
			}
			if ( ! empty( $data['content']['keywords'] ) && is_array( $data['content']['keywords'] ) ) {
				$clean['content']['keywords'] = array_values(
					array_filter(
						array_map( 'sanitize_text_field', $data['content']['keywords'] )
					)
				);
			}
		}
		if ( isset( $data['location']['shown'] ) && is_array( $data['location']['shown'] ) ) {
			foreach ( array( 'name', 'city', 'region', 'country' ) as $k ) {
				if ( ! empty( $data['location']['shown'][ $k ] ) ) {
					$clean['location']['shown'][ $k ] = sanitize_text_field( $data['location']['shown'][ $k ] );
				}
			}
			if ( ! empty( $data['location']['shown']['country_code'] ) ) {
				$code = strtoupper( sanitize_text_field( $data['location']['shown']['country_code'] ) );
				if ( preg_match( '/^[A-Z]{2}$/', $code ) ) {
					$clean['location']['shown']['country_code'] = $code;
				}
			}
		}
		if ( isset( $data['digital']['source_type'] ) ) {
			$clean['digital']['source_type'] = sanitize_text_field( $data['digital']['source_type'] );
		}
		// Rechazar captura inventada / GPS created.
		return $clean;
	}

	/**
	 * Fusiona sets del plan en un modelo.
	 *
	 * @param array $plan Plan.
	 * @return array
	 */
	public function model_from_plan( array $plan ) {
		$model = array();
		if ( empty( $plan['set'] ) || ! is_array( $plan['set'] ) ) {
			return $model;
		}
		foreach ( $plan['set'] as $item ) {
			if ( empty( $item['data'] ) || ! is_array( $item['data'] ) ) {
				continue;
			}
			$model = array_replace_recursive( $model, $item['data'] );
		}
		return $this->sanitize_model( $model );
	}

	/**
	 * @param LLM_Trace_Cleaner_Image_Engine_Interface $engine Engine.
	 * @param string                                     $path Path.
	 * @param array                                      $plan Plan.
	 * @return array
	 */
	public function apply( LLM_Trace_Cleaner_Image_Engine_Interface $engine, $path, array $plan ) {
		$model = $this->model_from_plan( $plan );
		if ( empty( $model ) ) {
			return array(
				'success'            => true,
				'written'            => array(),
				'unsupported_fields' => array(),
				'warnings'           => array(),
			);
		}
		$caps = $engine->get_capabilities();
		if ( ! empty( $caps['cleanup_only'] ) ) {
			return array(
				'success'            => false,
				'written'            => array(),
				'unsupported_fields' => array_keys( $model ),
				'warnings'           => array( 'El motor actual no permite escritura de metadatos estructurados.' ),
			);
		}
		return $engine->write_metadata( $path, $model );
	}
}
