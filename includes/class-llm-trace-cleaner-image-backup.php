<?php
/**
 * Copias de seguridad de imágenes.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

/**
 * Backup seguro bajo uploads.
 */
class LLM_Trace_Cleaner_Image_Backup {

	const META_PATH       = '_llmtc_image_backup_path';
	const META_HASH       = '_llmtc_image_backup_hash';
	const META_CREATED    = '_llmtc_image_backup_created_at';
	const META_SIZE       = '_llmtc_image_backup_filesize';

	/**
	 * Directorio base de backups.
	 *
	 * @return string|WP_Error
	 */
	public static function base_dir() {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'upload_dir', $uploads['error'] );
		}
		$dir = trailingslashit( $uploads['basedir'] ) . 'llm-trace-cleaner-backups';
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
			self::protect_dir( $dir );
		}
		return $dir;
	}

	/**
	 * @param string $dir Dir.
	 */
	private static function protect_dir( $dir ) {
		$index = trailingslashit( $dir ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
		$ht = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $ht ) ) {
			file_put_contents( $ht, "Deny from all\n" );
		}
	}

	/**
	 * Crear backup del adjunto si no existe.
	 *
	 * @param int    $attachment_id ID.
	 * @param string $source_path   Path validado.
	 * @return array|WP_Error
	 */
	public static function create( $attachment_id, $source_path ) {
		$existing = get_post_meta( $attachment_id, self::META_PATH, true );
		if ( $existing && file_exists( $existing ) ) {
			return array(
				'path'    => $existing,
				'hash'    => get_post_meta( $attachment_id, self::META_HASH, true ),
				'skipped' => true,
			);
		}

		$base = self::base_dir();
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$subdir = trailingslashit( $base ) . gmdate( 'Y/m' );
		wp_mkdir_p( $subdir );
		self::protect_dir( $subdir );

		$ext  = pathinfo( $source_path, PATHINFO_EXTENSION );
		$name = 'att' . (int) $attachment_id . '-' . wp_generate_password( 12, false ) . '.' . $ext;
		$dest = trailingslashit( $subdir ) . $name;

		if ( ! @copy( $source_path, $dest ) ) {
			return new WP_Error( 'backup_copy', 'No se pudo crear la copia de seguridad.' );
		}

		$hash = hash_file( 'sha256', $dest );
		$src_hash = hash_file( 'sha256', $source_path );
		if ( $hash !== $src_hash ) {
			@unlink( $dest );
			return new WP_Error( 'backup_hash', 'El hash del backup no coincide.' );
		}

		update_post_meta( $attachment_id, self::META_PATH, $dest );
		update_post_meta( $attachment_id, self::META_HASH, $hash );
		update_post_meta( $attachment_id, self::META_CREATED, current_time( 'mysql' ) );
		update_post_meta( $attachment_id, self::META_SIZE, filesize( $dest ) );

		LLM_Trace_Cleaner_Image_Logger::log(
			array(
				'attachment_id'  => $attachment_id,
				'action'         => 'backup_created',
				'status'         => 'ok',
				'original_hash'  => $src_hash,
				'result_hash'    => $hash,
				'original_size'  => filesize( $source_path ),
				'result_size'    => filesize( $dest ),
			)
		);

		return array(
			'path'    => $dest,
			'hash'    => $hash,
			'skipped' => false,
		);
	}

	/**
	 * Restaurar backup.
	 *
	 * @param int  $attachment_id ID.
	 * @param bool $regen_thumbs  Regenerar miniaturas.
	 * @return array|WP_Error
	 */
	public static function restore( $attachment_id, $regen_thumbs = false ) {
		$backup = get_post_meta( $attachment_id, self::META_PATH, true );
		if ( ! $backup || ! file_exists( $backup ) ) {
			return new WP_Error( 'no_backup', 'No hay copia de seguridad disponible.' );
		}

		$target = get_attached_file( $attachment_id, true );
		if ( ! $target ) {
			return new WP_Error( 'no_file', 'Adjunto sin archivo local.' );
		}

		$validated = LLM_Trace_Cleaner_Image_Manager::validate_path( $target );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$tmp = $target . '.llmtc.restore.tmp';
		if ( ! @copy( $backup, $tmp ) ) {
			return new WP_Error( 'restore_copy', 'Fallo al copiar backup.' );
		}
		if ( filesize( $tmp ) <= 0 || ! @getimagesize( $tmp ) ) {
			@unlink( $tmp );
			return new WP_Error( 'restore_invalid', 'Backup inválido.' );
		}
		if ( ! @rename( $tmp, $target ) ) {
			@unlink( $tmp );
			return new WP_Error( 'restore_rename', 'No se pudo restaurar de forma atómica.' );
		}

		clean_post_cache( $attachment_id );
		if ( $regen_thumbs && function_exists( 'wp_generate_attachment_metadata' ) ) {
			$meta = wp_generate_attachment_metadata( $attachment_id, $target );
			if ( is_array( $meta ) ) {
				wp_update_attachment_metadata( $attachment_id, $meta );
			}
		}

		LLM_Trace_Cleaner_Image_Logger::log(
			array(
				'attachment_id' => $attachment_id,
				'action'        => 'restore',
				'status'        => 'ok',
				'result_hash'   => hash_file( 'sha256', $target ),
				'result_size'   => filesize( $target ),
			)
		);

		return array(
			'success' => true,
			'path'    => $target,
		);
	}

	/**
	 * Espacio usado por backups (MB).
	 *
	 * @return float
	 */
	public static function storage_mb() {
		$base = self::base_dir();
		if ( is_wp_error( $base ) || ! is_dir( $base ) ) {
			return 0;
		}
		$size = 0;
		$it   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			if ( $file->isFile() ) {
				$size += $file->getSize();
			}
		}
		return round( $size / 1048576, 2 );
	}

	/**
	 * Aplicar retención.
	 *
	 * @param int $days Días.
	 * @param int $max_mb Máximo MB.
	 */
	public static function apply_retention( $days, $max_mb ) {
		$base = self::base_dir();
		if ( is_wp_error( $base ) || ! is_dir( $base ) ) {
			return;
		}
		$cutoff = time() - ( max( 1, (int) $days ) * DAY_IN_SECONDS );
		$it     = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			if ( $file->isFile() && $file->getMTime() < $cutoff && ! in_array( $file->getFilename(), array( 'index.php', '.htaccess' ), true ) ) {
				@unlink( $file->getPathname() );
			}
		}
		// Cap de almacenamiento: borrar más antiguos si se supera.
		if ( self::storage_mb() > (float) $max_mb ) {
			$files = array();
			$it2   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it2 as $file ) {
				if ( $file->isFile() && ! in_array( $file->getFilename(), array( 'index.php', '.htaccess' ), true ) ) {
					$files[] = array( 'path' => $file->getPathname(), 'mtime' => $file->getMTime() );
				}
			}
			usort(
				$files,
				function ( $a, $b ) {
					return $a['mtime'] - $b['mtime'];
				}
			);
			foreach ( $files as $f ) {
				if ( self::storage_mb() <= (float) $max_mb ) {
					break;
				}
				@unlink( $f['path'] );
			}
		}
	}
}
