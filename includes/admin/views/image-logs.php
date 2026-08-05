<?php
/**
 * Vista: Logs de imágenes.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

$logs = LLM_Trace_Cleaner_Image_Logger::get_recent( 50 );
?>
<div class="llmtc-panel">
	<h2><?php esc_html_e( 'Registros de imágenes', 'llm-trace-cleaner' ); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th>ID</th>
				<th><?php esc_html_e( 'Fecha', 'llm-trace-cleaner' ); ?></th>
				<th><?php esc_html_e( 'Acción', 'llm-trace-cleaner' ); ?></th>
				<th><?php esc_html_e( 'Estado', 'llm-trace-cleaner' ); ?></th>
				<th><?php esc_html_e( 'Adjunto', 'llm-trace-cleaner' ); ?></th>
				<th><?php esc_html_e( 'Perfil', 'llm-trace-cleaner' ); ?></th>
				<th><?php esc_html_e( 'Motor', 'llm-trace-cleaner' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $logs ) ) : ?>
			<tr><td colspan="7"><?php esc_html_e( 'Sin registros.', 'llm-trace-cleaner' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $logs as $row ) : ?>
				<tr>
					<td><?php echo esc_html( (string) $row['id'] ); ?></td>
					<td><?php echo esc_html( $row['created_at'] ); ?></td>
					<td><?php echo esc_html( $row['action'] ); ?></td>
					<td><?php echo esc_html( $row['status'] ); ?></td>
					<td><?php echo esc_html( (string) $row['attachment_id'] ); ?></td>
					<td><?php echo esc_html( (string) $row['profile'] ); ?></td>
					<td><?php echo esc_html( (string) $row['engine'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
</div>
