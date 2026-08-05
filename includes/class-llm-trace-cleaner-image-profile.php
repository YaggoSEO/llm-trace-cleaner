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
					'gps'           => 'remove',
					'device'        => 'remove',
					'serial'        => 'remove',
					'software'      => 'remove',
					'capture_dates' => 'remove',
					'comments'      => 'remove',
					'generator'     => 'remove',
					'edit_history'  => 'remove',
					'unknown'       => 'remove',
					'icc'           => 'keep',
					'orientation'   => 'keep',
					'copyright'     => 'keep',
				),
				'metadata'    => array(),
			),
			'corporate'    => array(
				'id'          => 'corporate',
				'name'        => 'Corporativo',
				'description' => 'Limpia datos sensibles y aplica autoría/copyright corporativos. Sin GPS ni cámara.',
				'locked'      => true,
				'rules'       => array(
					'gps'          => 'remove',
					'device'       => 'remove',
					'serial'       => 'remove',
					'software'     => 'remove',
					'generator'    => 'remove',
					'comments'     => 'remove',
					'edit_history' => 'remove',
					'icc'          => 'keep',
					'orientation'  => 'keep',
					'creator'      => 'set',
					'rights'       => 'set',
					'content'      => 'set_if_empty',
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
					'gps'              => 'remove',
					'device'           => 'remove',
					'serial'           => 'remove',
					'generator'        => 'remove',
					'location_created' => 'remove',
					'location_shown'   => 'set',
					'creator'          => 'set_if_empty',
					'content'          => 'set',
					'icc'              => 'keep',
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
	 * Sanitiza metadata canónica (sin GPS de captura).
	 *
	 * @param array $meta Meta.
	 * @return array
	 */
	public static function sanitize_metadata( array $meta ) {
		$out = array();
		if ( isset( $meta['creator'] ) && is_array( $meta['creator'] ) ) {
			foreach ( array( 'name', 'company', 'credit', 'source' ) as $k ) {
				if ( isset( $meta['creator'][ $k ] ) ) {
					$out['creator'][ $k ] = sanitize_text_field( $meta['creator'][ $k ] );
				}
			}
			if ( ! empty( $meta['creator']['website'] ) ) {
				$out['creator']['website'] = esc_url_raw( $meta['creator']['website'] );
			}
		}
		if ( isset( $meta['rights'] ) && is_array( $meta['rights'] ) ) {
			foreach ( array( 'copyright', 'license', 'usage' ) as $k ) {
				if ( isset( $meta['rights'][ $k ] ) ) {
					$out['rights'][ $k ] = sanitize_textarea_field( $meta['rights'][ $k ] );
				}
			}
			if ( ! empty( $meta['rights']['rights_url'] ) ) {
				$out['rights']['rights_url'] = esc_url_raw( $meta['rights']['rights_url'] );
			}
		}
		if ( isset( $meta['content'] ) && is_array( $meta['content'] ) ) {
			foreach ( array( 'title', 'headline', 'alt_text' ) as $k ) {
				if ( isset( $meta['content'][ $k ] ) ) {
					$out['content'][ $k ] = sanitize_text_field( $meta['content'][ $k ] );
				}
			}
			if ( isset( $meta['content']['description'] ) ) {
				$out['content']['description'] = sanitize_textarea_field( $meta['content']['description'] );
			}
			if ( isset( $meta['content']['keywords'] ) ) {
				$kw = $meta['content']['keywords'];
				if ( is_string( $kw ) ) {
					$kw = array_map( 'trim', explode( ',', $kw ) );
				}
				if ( is_array( $kw ) ) {
					$out['content']['keywords'] = array_values( array_filter( array_map( 'sanitize_text_field', $kw ) ) );
				}
			}
		}
		if ( isset( $meta['location']['shown'] ) && is_array( $meta['location']['shown'] ) ) {
			foreach ( array( 'name', 'city', 'region', 'country' ) as $k ) {
				if ( isset( $meta['location']['shown'][ $k ] ) ) {
					$out['location']['shown'][ $k ] = sanitize_text_field( $meta['location']['shown'][ $k ] );
				}
			}
			if ( ! empty( $meta['location']['shown']['country_code'] ) ) {
				$code = strtoupper( sanitize_text_field( $meta['location']['shown']['country_code'] ) );
				if ( preg_match( '/^[A-Z]{2}$/', $code ) ) {
					$out['location']['shown']['country_code'] = $code;
				}
			}
		}
		if ( isset( $meta['digital']['source_type'] ) ) {
			$out['digital']['source_type'] = sanitize_text_field( $meta['digital']['source_type'] );
		}
		return $out;
	}

	/**
	 * @param array $profile Perfil.
	 * @return true|WP_Error
	 */
	public static function save( array $profile ) {
		$id = isset( $profile['id'] ) ? sanitize_key( $profile['id'] ) : '';
		if ( '' === $id || ! preg_match( '/^[a-z0-9_\-]+$/', $id ) ) {
			return new WP_Error( 'invalid_id', 'ID de perfil inválido.' );
		}
		$all      = self::all();
		$metadata = isset( $profile['metadata'] ) && is_array( $profile['metadata'] )
			? self::sanitize_metadata( $profile['metadata'] )
			: array();

		if ( isset( $all[ $id ]['locked'] ) && $all[ $id ]['locked'] && empty( $profile['force'] ) ) {
			$all[ $id ]['metadata']    = $metadata;
			$all[ $id ]['description'] = isset( $profile['description'] )
				? sanitize_textarea_field( $profile['description'] )
				: $all[ $id ]['description'];
		} else {
			$rules = isset( $profile['rules'] ) && is_array( $profile['rules'] ) ? $profile['rules'] : array();
			$clean_rules = array();
			foreach ( $rules as $rk => $rv ) {
				$clean_rules[ sanitize_key( $rk ) ] = sanitize_key( $rv );
			}
			$all[ $id ] = array(
				'id'          => $id,
				'name'        => sanitize_text_field( isset( $profile['name'] ) ? $profile['name'] : $id ),
				'description' => sanitize_textarea_field( isset( $profile['description'] ) ? $profile['description'] : '' ),
				'locked'      => false,
				'rules'       => $clean_rules,
				'metadata'    => $metadata,
			);
		}
		update_option( self::OPTION, $all, false );
		return true;
	}

	/**
	 * Duplicar perfil.
	 *
	 * @param string $source_id Source.
	 * @param string $new_id    New ID.
	 * @param string $new_name  Name.
	 * @return true|WP_Error
	 */
	public static function duplicate( $source_id, $new_id, $new_name = '' ) {
		$src = self::get( $source_id );
		if ( ! $src ) {
			return new WP_Error( 'missing', 'Perfil origen no encontrado.' );
		}
		$new_id = sanitize_key( $new_id );
		if ( '' === $new_id || self::get( $new_id ) ) {
			return new WP_Error( 'exists', 'ID destino inválido o ya existe.' );
		}
		$src['id']     = $new_id;
		$src['name']   = $new_name ? sanitize_text_field( $new_name ) : ( $src['name'] . ' (copia)' );
		$src['locked'] = false;
		$src['force']  = true;
		return self::save( $src );
	}

	/**
	 * Eliminar perfil custom.
	 *
	 * @param string $id ID.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		$id  = sanitize_key( $id );
		$all = self::all();
		if ( ! isset( $all[ $id ] ) ) {
			return new WP_Error( 'missing', 'Perfil no encontrado.' );
		}
		if ( ! empty( $all[ $id ]['locked'] ) ) {
			return new WP_Error( 'locked', 'No se puede eliminar un perfil predeterminado.' );
		}
		unset( $all[ $id ] );
		update_option( self::OPTION, $all, false );
		return true;
	}

	/**
	 * Exportar perfiles a array JSON-safe.
	 *
	 * @param string|null $id Solo uno o todos.
	 * @return array
	 */
	public static function export( $id = null ) {
		if ( $id ) {
			$p = self::get( $id );
			return $p ? array( $id => $p ) : array();
		}
		return self::all();
	}

	/**
	 * Importar perfiles desde array.
	 *
	 * @param array $data Data.
	 * @return true|WP_Error
	 */
	public static function import( array $data ) {
		foreach ( $data as $id => $profile ) {
			if ( ! is_array( $profile ) ) {
				continue;
			}
			$profile['id']     = isset( $profile['id'] ) ? $profile['id'] : $id;
			$profile['locked'] = false;
			$profile['force']  = true;
			$builtins          = self::builtins();
			if ( isset( $builtins[ $profile['id'] ] ) ) {
				// No sobrescribir locked builtins via import force de rules; solo metadata.
				$existing = self::get( $profile['id'] );
				if ( $existing && ! empty( $existing['locked'] ) ) {
					unset( $profile['force'] );
					$profile['locked'] = true;
				}
			}
			$result = self::save( $profile );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return true;
	}

	/**
	 * Restaurar built-ins (conserva customs).
	 */
	public static function restore_defaults() {
		$all     = self::all();
		$customs = array();
		foreach ( $all as $id => $p ) {
			if ( empty( $p['locked'] ) ) {
				$customs[ $id ] = $p;
			}
		}
		update_option( self::OPTION, array_merge( self::builtins(), $customs ), false );
	}
}
