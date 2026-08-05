<?php
/**
 * Logger del módulo de imágenes.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tabla independiente de logs de imágenes.
 */
class LLM_Trace_Cleaner_Image_Logger {

	/**
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'llm_trace_cleaner_image_logs';
	}

	/**
	 * Crear / actualizar esquema.
	 */
	public static function create_table() {
		global $wpdb;
		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			attachment_id BIGINT UNSIGNED NULL,
			action VARCHAR(50) NOT NULL,
			status VARCHAR(30) NOT NULL,
			profile VARCHAR(100) NULL,
			engine VARCHAR(50) NULL,
			original_hash CHAR(64) NULL,
			result_hash CHAR(64) NULL,
			original_size BIGINT UNSIGNED NULL,
			result_size BIGINT UNSIGNED NULL,
			removed_count INT UNSIGNED DEFAULT 0,
			written_count INT UNSIGNED DEFAULT 0,
			warnings LONGTEXT NULL,
			details LONGTEXT NULL,
			user_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY attachment_id (attachment_id),
			KEY action (action),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * @param array $row Datos del log.
	 * @return int|false
	 */
	public static function log( array $row ) {
		global $wpdb;

		$warnings = isset( $row['warnings'] ) ? $row['warnings'] : array();
		$details  = isset( $row['details'] ) ? $row['details'] : array();

		if ( is_array( $warnings ) ) {
			$warnings = wp_json_encode( $warnings );
		}
		if ( is_array( $details ) ) {
			$details = wp_json_encode( self::sanitize_details( $details ) );
		}

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'attachment_id'  => isset( $row['attachment_id'] ) ? (int) $row['attachment_id'] : null,
				'action'         => sanitize_key( isset( $row['action'] ) ? $row['action'] : 'unknown' ),
				'status'         => sanitize_key( isset( $row['status'] ) ? $row['status'] : 'ok' ),
				'profile'        => isset( $row['profile'] ) ? sanitize_text_field( $row['profile'] ) : null,
				'engine'         => isset( $row['engine'] ) ? sanitize_text_field( $row['engine'] ) : null,
				'original_hash'  => isset( $row['original_hash'] ) ? substr( sanitize_text_field( $row['original_hash'] ), 0, 64 ) : null,
				'result_hash'    => isset( $row['result_hash'] ) ? substr( sanitize_text_field( $row['result_hash'] ), 0, 64 ) : null,
				'original_size'  => isset( $row['original_size'] ) ? absint( $row['original_size'] ) : null,
				'result_size'    => isset( $row['result_size'] ) ? absint( $row['result_size'] ) : null,
				'removed_count'  => isset( $row['removed_count'] ) ? absint( $row['removed_count'] ) : 0,
				'written_count'  => isset( $row['written_count'] ) ? absint( $row['written_count'] ) : 0,
				'warnings'       => $warnings,
				'details'        => $details,
				'user_id'        => get_current_user_id() ? get_current_user_id() : null,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * @param array $details Detalles.
	 * @return array
	 */
	private static function sanitize_details( array $details ) {
		$out = array();
		foreach ( $details as $key => $value ) {
			$key = sanitize_key( $key );
			if ( is_array( $value ) ) {
				$out[ $key ] = self::sanitize_details( $value );
			} elseif ( is_scalar( $value ) ) {
				$out[ $key ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
			}
		}
		return $out;
	}

	/**
	 * @param int $limit Límite.
	 * @return array
	 */
	public static function get_recent( $limit = 50 ) {
		global $wpdb;
		$table = self::table_name();
		$limit = max( 1, min( 200, (int) $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
	}

	/**
	 * Vaciar tabla.
	 */
	public static function clear() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( 'TRUNCATE TABLE ' . self::table_name() );
	}
}
