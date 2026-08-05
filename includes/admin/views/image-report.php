<?php
/**
 * Vista: Informe individual.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

$attachment_id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0;
$profiles      = LLM_Trace_Cleaner_Image_Profile::all();
?>
<div class="llmtc-panel">
	<h2><?php esc_html_e( 'Informe de imagen', 'llm-trace-cleaner' ); ?></h2>
	<p>
		<label><?php esc_html_e( 'ID de adjunto', 'llm-trace-cleaner' ); ?>
			<input type="number" id="llmtc-report-id" value="<?php echo esc_attr( (string) $attachment_id ); ?>" min="1">
		</label>
		<button type="button" class="button" id="llmtc-report-audit"><?php esc_html_e( 'Auditar', 'llm-trace-cleaner' ); ?></button>
	</p>
	<p>
		<select id="llmtc-report-profile">
			<?php foreach ( $profiles as $id => $p ) : ?>
				<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $p['name'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="button" class="button" id="llmtc-report-dry"><?php esc_html_e( 'Simular limpieza', 'llm-trace-cleaner' ); ?></button>
		<button type="button" class="button button-primary" id="llmtc-report-apply"><?php esc_html_e( 'Aplicar perfil', 'llm-trace-cleaner' ); ?></button>
		<button type="button" class="button" id="llmtc-report-restore"><?php esc_html_e( 'Restaurar backup', 'llm-trace-cleaner' ); ?></button>
		<button type="button" class="button" id="llmtc-report-export-json"><?php esc_html_e( 'Exportar JSON', 'llm-trace-cleaner' ); ?></button>
		<button type="button" class="button" id="llmtc-report-export-csv"><?php esc_html_e( 'Exportar CSV', 'llm-trace-cleaner' ); ?></button>
	</p>
	<pre id="llmtc-report-out" class="llmtc-report-out"><?php esc_html_e( 'Selecciona un adjunto y audita.', 'llm-trace-cleaner' ); ?></pre>
	<?php if ( $attachment_id ) : ?>
		<p><?php echo wp_get_attachment_image( $attachment_id, 'medium' ); ?></p>
	<?php endif; ?>
</div>
