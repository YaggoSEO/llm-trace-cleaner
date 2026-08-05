<?php
/**
 * Vista: Estado / diagnóstico.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

$caps     = LLM_Trace_Cleaner_Image_Capabilities::detect();
$settings = LLM_Trace_Cleaner_Image_Manager::settings();
$storage  = LLM_Trace_Cleaner_Image_Backup::storage_mb();
?>
<div class="llmtc-panel">
	<h2><?php esc_html_e( 'Estado del módulo', 'llm-trace-cleaner' ); ?></h2>
	<table class="widefat striped">
		<tbody>
			<tr><th><?php esc_html_e( 'Módulo activo', 'llm-trace-cleaner' ); ?></th><td><?php echo ! empty( $settings['enabled'] ) ? 'Sí' : 'No'; ?></td></tr>
			<tr><th><?php esc_html_e( 'Motor preferente (strip)', 'llm-trace-cleaner' ); ?></th><td><?php echo esc_html( $caps['preferred_engine'] ); ?></td></tr>
			<tr>
				<th>Imagick</th>
				<td>
					<?php
					if ( ! empty( $caps['imagick'] ) ) {
						echo esc_html__( 'Sí (recomendado en hosting compartido)', 'llm-trace-cleaner' );
					} else {
						echo esc_html__( 'No — pide al hosting la extensión PHP imagick', 'llm-trace-cleaner' );
					}
					?>
				</td>
			</tr>
			<tr><th>GD</th><td><?php echo $caps['gd'] ? 'Sí' : 'No'; ?><?php echo ! empty( $caps['cleanup_only'] ) ? ' (solo limpieza básica)' : ''; ?></td></tr>
			<tr>
				<th>ExifTool</th>
				<td>
					<?php
					if ( ! empty( $caps['exiftool'] ) ) {
						echo 'Sí — v' . esc_html( $caps['exiftool_version'] );
					} elseif ( ! empty( $settings['exiftool_enabled'] ) ) {
						echo esc_html__( 'Activado pero no detectado (normal en compartido; usa Imagick)', 'llm-trace-cleaner' );
					} else {
						echo esc_html__( 'Desactivado (opcional, solo VPS)', 'llm-trace-cleaner' );
					}
					?>
				</td>
			</tr>
			<tr><th><?php esc_html_e( 'Escritura rica', 'llm-trace-cleaner' ); ?></th><td><?php echo ! empty( $caps['rich_write'] ) ? 'Sí' : 'No'; ?></td></tr>
			<tr><th>AVIF</th><td><?php echo ! empty( $caps['avif'] ) ? ( ! empty( $settings['allow_avif'] ) ? 'Sí (habilitado)' : 'Soportado (deshabilitado en ajustes)' ) : 'No'; ?></td></tr>
			<tr><th><?php esc_html_e( 'Simulación (dry run)', 'llm-trace-cleaner' ); ?></th><td><?php echo ! empty( $settings['dry_run'] ) ? 'Sí' : 'No'; ?></td></tr>
			<tr><th><?php esc_html_e( 'Backups (MB usados)', 'llm-trace-cleaner' ); ?></th><td><?php echo esc_html( (string) $storage ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Detener ante C2PA', 'llm-trace-cleaner' ); ?></th><td><?php echo ! empty( $settings['stop_on_c2pa'] ) ? 'Sí' : 'No'; ?></td></tr>
		</tbody>
	</table>

	<h3><?php esc_html_e( 'Formatos', 'llm-trace-cleaner' ); ?></h3>
	<table class="widefat striped">
		<thead><tr><th>MIME</th><th>Imagick</th><th>GD</th><th>ExifTool</th></tr></thead>
		<tbody>
		<?php foreach ( $caps['mimes'] as $mime => $support ) : ?>
			<tr>
				<td><?php echo esc_html( $mime ); ?></td>
				<td><?php echo ! empty( $support['imagick'] ) ? 'Sí' : 'No'; ?></td>
				<td><?php echo ! empty( $support['gd'] ) ? 'Sí' : 'No'; ?></td>
				<td><?php echo ! empty( $support['exiftool'] ) ? 'Sí' : 'No'; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( empty( $caps['imagick'] ) ) : ?>
		<div class="notice notice-warning inline"><p>
			<?php esc_html_e( 'Sin Imagick: en hosting compartido es lo que debes pedir al proveedor (extensión PHP imagick). Con solo GD puedes limpiar por re-encode, pero no escribir bien autoría/copyright/ubicación. ExifTool no suele estar disponible en compartido (no es extensión PHP).', 'llm-trace-cleaner' ); ?>
		</p></div>
	<?php elseif ( empty( $caps['exiftool'] ) ) : ?>
		<div class="notice notice-info inline"><p>
			<?php esc_html_e( 'Imagick activo: suficiente para la mayoría de sitios en compartido (limpieza + artist/copyright/description). ExifTool solo aporta IPTC/XMP completo en VPS donde puedas instalar el binario.', 'llm-trace-cleaner' ); ?>
		</p></div>
	<?php endif; ?>
</div>
