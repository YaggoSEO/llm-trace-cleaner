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
			<tr><th><?php esc_html_e( 'Motor preferente', 'llm-trace-cleaner' ); ?></th><td><?php echo esc_html( $caps['preferred_engine'] ); ?></td></tr>
			<tr><th>Imagick</th><td><?php echo $caps['imagick'] ? 'Sí' : 'No'; ?></td></tr>
			<tr><th>GD</th><td><?php echo $caps['gd'] ? 'Sí' : 'No'; ?><?php echo $caps['cleanup_only'] ? ' (solo limpieza básica)' : ''; ?></td></tr>
			<tr><th>ExifTool</th><td><?php esc_html_e( 'Desactivado en MVP', 'llm-trace-cleaner' ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Simulación (dry run)', 'llm-trace-cleaner' ); ?></th><td><?php echo ! empty( $settings['dry_run'] ) ? 'Sí' : 'No'; ?></td></tr>
			<tr><th><?php esc_html_e( 'Backups (MB usados)', 'llm-trace-cleaner' ); ?></th><td><?php echo esc_html( (string) $storage ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Detener ante C2PA', 'llm-trace-cleaner' ); ?></th><td><?php echo ! empty( $settings['stop_on_c2pa'] ) ? 'Sí' : 'No'; ?></td></tr>
		</tbody>
	</table>

	<h3><?php esc_html_e( 'Formatos', 'llm-trace-cleaner' ); ?></h3>
	<table class="widefat striped">
		<thead><tr><th>MIME</th><th>Imagick</th><th>GD</th></tr></thead>
		<tbody>
		<?php foreach ( $caps['mimes'] as $mime => $support ) : ?>
			<tr>
				<td><?php echo esc_html( $mime ); ?></td>
				<td><?php echo ! empty( $support['imagick'] ) ? 'Sí' : 'No'; ?></td>
				<td><?php echo ! empty( $support['gd'] ) ? 'Sí' : 'No'; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $caps['cleanup_only'] ) : ?>
		<div class="notice notice-info inline"><p>
			<?php esc_html_e( 'Solo GD está disponible: el módulo puede limpiar metadatos mediante re-encode, pero no escribir autoría/copyright/ubicación estructurada. Instala Imagick para escritura parcial.', 'llm-trace-cleaner' ); ?>
		</p></div>
	<?php endif; ?>
</div>
