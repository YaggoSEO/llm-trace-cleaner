<?php
/**
 * Perfiles de metadatos de imágenes.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gestión de perfiles built-in y personalizados.
 */
class LLM_Trace_Cleaner_Image_Profile {

	const OPTION = 'llm_trace_cleaner_image_profiles';

	/**
	 * Perfiles predeterminados.
	 *
	 * @return array
	 */
	public static function builtins() {
		return array(
			'privacy'      => array(
				'id'          => 'privacy',
				'name'        => 'Privacidad máxima',
				'description' => 'Elimina GPS, dispositivo, software, prompts y comentarios. Conserva ICC y orientación.',
				'locked'      => true,
				'rules'       => array(
					'gps'            => 'remove',
					'device'         => 'remove',
					'serial'         => 'remove',
					'software'       => 'remove',
					'capture_dates'  => 'remove',
					'comments'       => 'remove',
					'generator'      => 'remove',
					'edit_history'   => 'remove',
					'unknown'        => 'remove',
					'icc'            => 'keep',
					'orientation'    => 'keep',
					'copyright'      => 'keep',
				),
				'metadata'    => array(),
			),
			'corporate'    => array(
				'id'          => 'corporate',
				'name'        => 'Corporativo',
				'description' => 'Limpia datos sensibles y aplica autoría/copyright corporativos. Sin GPS ni cámara.',
				'locked'      => true,
				'rules'       => array(
					'gps'           => 'remove',
					'device'        => 'remove',
					'serial'        => 'remove',
					'software'      => 'remove',
					'generator'     => 'remove',
					'comments'      => 'remove',
					'edit_history'  => 'remove',
					'icc'           => 'keep',
					'orientation'   => 'keep',
					'creator'       => 'set',
					'rights'        => 'set',
					'content'       => 'set_if_empty',
				),
				'metadata'    => array(
					'creator' => array(
						'name'    => '',
						'company' => '',
						'credit'  => '',
						'source'  => '',
						'website' => '',
					),
					'rights'  => array(
						'copyright'  => '',
						'license'    => '',
						'usage'      => '',
						'rights_url' => '',
					),
					'content' => array(
						'title'       => '',
						'description' => '',
						'keywords'    => array(),
					),
				),
			),
			'seo_local'    => array(
				'id'          => 'seo_local',
				'name'        => 'SEO local',
				'description' => 'Añade ámbito geográfico mostrado. No escribe GPS de captura ni dispositivo ficticio.',
				'locked'      => true,
				'rules'       => array(
					'gps'          => 'remove',
					'device'       => 'remove',
					'serial'       => 'remove',
					'generator'    => 'remove',
					'location_created' => 'remove',
					'location_shown'   => 'set',
					'creator'      => 'set_if_empty',
					'content'      => 'set',
					'icc'          => 'keep',
				),
				'metadata'    => array(
					'creator'  => array(
						'company' => '',
						'website' => '',
					),
					'content'  => array(
						'title'       => '',
						'description' => '',
						'keywords'    => array(),
					),
					'location' => array(
						'shown' => array(
							'name'         => '',
							'city'         => '',
							'region'       => '',
							'country'      => '',
							'country_code' => '',
						),
					),
				),
			),
			'photography'  => array(
				'id'          => 'photography',
				'name'        => 'Fotografía original',
				'description' => 'Conserva captura técnica y autoría. Elimina historial de edición y comentarios internos.',
				'locked'      => true,
				'rules'       => array(
					'device'        => 'keep',
					'capture_dates' => 'keep',
					'gps'           => 'keep',
					'creator'       => 'keep',
					'copyright'     => 'keep',
					'icc'           => 'keep',
					'edit_history'  => 'remove',
					'comments'      => 'remove',
					'generator'     => 'remove',
					'serial'        => 'remove',
				),
				'metadata'    => array(),
			),
			'ai_generated' => array(
				'id'          => 'ai_generated',
				'name'        => 'Imagen generada o editada con IA',
				'description' => 'Elimina prompts, seeds y workflows. Permite autoría y ubicación mostrada. No inventa cámara.',
				'locked'      => true,
				'rules'       => array(
					'generator'        => 'remove',
					'comments'         => 'remove',
					'gps'              => 'remove',
					'device'           => 'remove',
					'serial'           => 'remove',
					'location_created' => 'remove',
					'creator'          => 'set',
					'rights'           => 'set',
					'content'          => 'set',
					'location_shown'   => 'set_if_empty',
					'digital_source'   => 'set',
					'icc'              => 'keep',
				),
				'metadata'    => array(
					'creator' => array(
						'name'    => '',
						'credit'  => '',
						'source'  => '',
					),
					'rights'  => array(
						'copyright' => '',
					),
					'content' => array(
						'description' => '',
					),
					'digital' => array(
						'source_type' => 'digitalCreation',
					),
				),
			),
		);
	}

	/**
	 * Sembrar perfiles si no existen.
	 */
	public static function seed_defaults() {
		$existing = get_option( self::OPTION, false );
		if ( false === $existing || ! is_array( $existing ) || empty( $existing ) ) {
			update_option( self::OPTION, self::builtins(), false );
			return;
		}
		$builtins = self::builtins();
		$changed  = false;
		foreach ( $builtins as $id => $profile ) {
			if ( ! isset( $existing[ $id ] ) ) {
				$existing[ $id ] = $profile;
				$changed         = true;
			}
		}
		if ( $changed ) {
			update_option( self::OPTION, $existing, false );
		}
	}

	/**
	 * @return array
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return self::builtins();
		}
		return $stored;
	}

	/**
	 * @param string $id ID del perfil.
	 * @return array|null
	 */
	public static function get( $id ) {
		$all = self::all();
		$id  = sanitize_key( $id );
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * @param array $profile Perfil.
	 * @return true|WP_Error
	 */
	public static function save( array $profile ) {
		$id = isset( $profile['id'] ) ? sanitize_key( $profile['id'] ) : '';
		if ( '' === $id ) {
			return new WP_Error( 'invalid_id', 'ID de perfil inválido.' );
		}
		$all = self::all();
		if ( isset( $all[ $id ]['locked'] ) && $all[ $id ]['locked'] && empty( $profile['force'] ) ) {
			// Solo permitir actualizar metadata de locked, no rules estructurales.
			$all[ $id ]['metadata']    = isset( $profile['metadata'] ) && is_array( $profile['metadata'] ) ? $profile['metadata'] : $all[ $id ]['metadata'];
			$all[ $id ]['description'] = isset( $profile['description'] ) ? sanitize_textarea_field( $profile['description'] ) : $all[ $id ]['description'];
		} else {
			$all[ $id ] = array(
				'id'          => $id,
				'name'        => sanitize_text_field( isset( $profile['name'] ) ? $profile['name'] : $id ),
				'description' => sanitize_textarea_field( isset( $profile['description'] ) ? $profile['description'] : '' ),
				'locked'      => ! empty( $profile['locked'] ),
				'rules'       => isset( $profile['rules'] ) && is_array( $profile['rules'] ) ? $profile['rules'] : array(),
				'metadata'    => isset( $profile['metadata'] ) && is_array( $profile['metadata'] ) ? $profile['metadata'] : array(),
			);
		}
		update_option( self::OPTION, $all, false );
		return true;
	}

	/**
	 * Restaurar built-ins.
	 */
	public static function restore_defaults() {
		update_option( self::OPTION, self::builtins(), false );
	}
}
