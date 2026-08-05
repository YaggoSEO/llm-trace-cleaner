<?php
/**
 * Procesamiento por lotes de imágenes.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cola AJAX de adjuntos.
 */
class LLM_Trace_Cleaner_Image_Batch {

	const OPTION_PREFIX = 'llm_trace_cleaner_image_batch_';

	/**
	 * Crear lote.
	 *
	 * @param array $attachment_ids IDs.
	 * @param array $args Args.
	 * @return array
	 */
	public static function start( array $attachment_ids, array $args = array() ) {
		$settings = LLM_Trace_Cleaner_Image_Manager::settings();
		$id       = 'img_' . time() . '_' . wp_generate_password( 6, false );
		$state    = array(
			'id'         => $id,
			'status'     => 'running',
			'profile'    => isset( $args['profile'] ) ? sanitize_key( $args['profile'] ) : $settings['default_profile'],
			'dry_run'    => array_key_exists( 'dry_run', $args ) ? (bool) $args['dry_run'] : ! empty( $settings['dry_run'] ),
			'ids'        => array_map( 'absint', $attachment_ids ),
			'total'      => count( $attachment_ids ),
			'processed'  => 0,
			'changed'    => 0,
			'skipped'    => 0,
			'failed'     => 0,
			'cursor'     => 0,
			'started_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
			'errors'     => array(),
		);
		self::save( $id, $state );
		LLM_Trace_Cleaner_Image_Logger::log(
			array(
				'action'  => 'batch_started',
				'status'  => 'ok',
				'profile' => $state['profile'],
				'details' => array( 'batch_id' => $id, 'total' => $state['total'] ),
			)
		);
		return $state;
	}

	/**
	 * @param string $id ID.
	 * @param array  $state State.
	 */
	public static function save( $id, array $state ) {
		global $wpdb;
		$key     = '_transient_' . self::OPTION_PREFIX . $id;
		$key_to  = '_transient_timeout_' . self::OPTION_PREFIX . $id;
		$payload = maybe_serialize( $state );
		$timeout = time() + 2 * HOUR_IN_SECONDS;
		// Evitar object cache: escribir options directamente (mismo patrón que Admin HTML).
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')
				ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
				$key,
				$payload
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')
				ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
				$key_to,
				(string) $timeout
			)
		);
	}

	/**
	 * @param string $id ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		$raw = get_option( '_transient_' . self::OPTION_PREFIX . $id );
		if ( false === $raw ) {
			return null;
		}
		if ( is_string( $raw ) ) {
			$raw = maybe_unserialize( $raw );
		}
		return is_array( $raw ) ? $raw : null;
	}

	/**
	 * Procesar siguiente chunk.
	 *
	 * @param string $id ID.
	 * @return array|WP_Error
	 */
	public static function process_next( $id ) {
		$state = self::get( $id );
		if ( ! $state ) {
			return new WP_Error( 'batch_missing', 'Lote no encontrado.' );
		}
		if ( in_array( $state['status'], array( 'cancelled', 'completed', 'failed' ), true ) ) {
			return $state;
		}
		if ( 'paused' === $state['status'] ) {
			return $state;
		}

		$settings   = LLM_Trace_Cleaner_Image_Manager::settings();
		$batch_size = max( 1, min( 25, (int) $settings['batch_size'] ) );
		$manager    = LLM_Trace_Cleaner_Image_Manager::get_instance();
		$ids        = $state['ids'];
		$end        = min( count( $ids ), $state['cursor'] + $batch_size );

		for ( $i = $state['cursor']; $i < $end; $i++ ) {
			$att_id = (int) $ids[ $i ];
			$result = $manager->process_attachment(
				$att_id,
				array(
					'profile' => $state['profile'],
					'dry_run' => $state['dry_run'],
					'force'   => true,
					'action'  => 'apply_profile',
				)
			);
			$state['processed']++;
			if ( is_wp_error( $result ) ) {
				$state['failed']++;
				$state['errors'][] = array(
					'id'    => $att_id,
					'error' => $result->get_error_message(),
				);
			} elseif ( ! empty( $result['skipped'] ) ) {
				$state['skipped']++;
			} elseif ( ! empty( $result['success'] ) ) {
				$state['changed']++;
			} else {
				$state['failed']++;
			}
		}

		$state['cursor']     = $end;
		$state['updated_at'] = current_time( 'mysql' );

		if ( $state['cursor'] >= $state['total'] ) {
			$state['status'] = 'completed';
			LLM_Trace_Cleaner_Image_Logger::log(
				array(
					'action'  => 'batch_completed',
					'status'  => 'ok',
					'profile' => $state['profile'],
					'details' => array(
						'batch_id'  => $id,
						'processed' => $state['processed'],
						'changed'   => $state['changed'],
						'failed'    => $state['failed'],
					),
				)
			);
		}

		self::save( $id, $state );
		return $state;
	}

	/**
	 * @param string $id ID.
	 * @param string $status Status.
	 * @return array|WP_Error
	 */
	public static function set_status( $id, $status ) {
		$state = self::get( $id );
		if ( ! $state ) {
			return new WP_Error( 'batch_missing', 'Lote no encontrado.' );
		}
		$state['status']     = sanitize_key( $status );
		$state['updated_at'] = current_time( 'mysql' );
		self::save( $id, $state );
		return $state;
	}

	/**
	 * Consulta IDs de adjuntos filtrados.
	 *
	 * @param array $filters Filters.
	 * @return int[]
	 */
	public static function query_attachment_ids( array $filters = array() ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => isset( $filters['limit'] ) ? min( 500, absint( $filters['limit'] ) ) : 100,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);
		$mime = array( 'image/jpeg', 'image/png', 'image/webp' );
		if ( ! empty( $filters['mime'] ) ) {
			$mime = array( sanitize_text_field( $filters['mime'] ) );
		}
		$args['post_mime_type'] = $mime;

		if ( ! empty( $filters['author'] ) ) {
			$args['author'] = absint( $filters['author'] );
		}
		if ( ! empty( $filters['after'] ) ) {
			$args['date_query'] = array(
				array(
					'after' => sanitize_text_field( $filters['after'] ),
				),
			);
		}

		$query = new WP_Query( $args );
		return array_map( 'intval', $query->posts );
	}
}
