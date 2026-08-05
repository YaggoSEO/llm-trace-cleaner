<?php
/**
 * Vista: Escáner y lotes.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

$profiles = LLM_Trace_Cleaner_Image_Profile::all();
$settings = LLM_Trace_Cleaner_Image_Manager::settings();
?>
<div class="llmtc-panel">
	<h2><?php esc_html_e( 'Escáner de biblioteca', 'llm-trace-cleaner' ); ?></h2>
	<p><?php esc_html_e( 'Procesa adjuntos JPEG/PNG/WebP por lotes. No se aceptan rutas de archivo desde el navegador.', 'llm-trace-cleaner' ); ?></p>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Perfil', 'llm-trace-cleaner' ); ?></th>
			<td>
				<select id="llmtc-batch-profile">
					<?php foreach ( $profiles as $id => $p ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $settings['default_profile'], $id ); ?>><?php echo esc_html( $p['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Límite', 'llm-trace-cleaner' ); ?></th>
			<td><input type="number" id="llmtc-batch-limit" min="1" max="500" value="50"></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'MIME', 'llm-trace-cleaner' ); ?></th>
			<td>
				<select id="llmtc-batch-mime">
					<option value=""><?php esc_html_e( 'Todos (jpeg/png/webp)', 'llm-trace-cleaner' ); ?></option>
					<option value="image/jpeg">JPEG</option>
					<option value="image/png">PNG</option>
					<option value="image/webp">WebP</option>
				</select>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Simulación', 'llm-trace-cleaner' ); ?></th>
			<td><label><input type="checkbox" id="llmtc-batch-dry" <?php checked( ! empty( $settings['dry_run'] ) ); ?>> <?php esc_html_e( 'Dry run', 'llm-trace-cleaner' ); ?></label></td>
		</tr>
	</table>

	<p>
		<button type="button" class="button button-primary" id="llmtc-batch-start"><?php esc_html_e( 'Iniciar lote', 'llm-trace-cleaner' ); ?></button>
		<button type="button" class="button" id="llmtc-batch-cancel" disabled><?php esc_html_e( 'Cancelar', 'llm-trace-cleaner' ); ?></button>
	</p>

	<div id="llmtc-batch-progress" class="llmtc-progress" style="display:none;">
		<div class="llmtc-progress-bar"><span id="llmtc-batch-bar"></span></div>
		<p id="llmtc-batch-status"></p>
	</div>
</div>
