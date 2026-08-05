<?php
/**
 * Vista: Ajustes del módulo de imágenes.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

$settings = LLM_Trace_Cleaner_Image_Manager::settings();
$profiles = LLM_Trace_Cleaner_Image_Profile::all();
?>
<div class="llmtc-panel">
	<form method="post" action="options.php">
		<?php settings_fields( 'llmtc_image_settings_group' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Activar módulo', 'llm-trace-cleaner' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <?php esc_html_e( 'Habilitado', 'llm-trace-cleaner' ); ?></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Procesar nuevas subidas', 'llm-trace-cleaner' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[auto_process_uploads]" value="1" <?php checked( ! empty( $settings['auto_process_uploads'] ) ); ?>></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Simulación (dry run)', 'llm-trace-cleaner' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[dry_run]" value="1" <?php checked( ! empty( $settings['dry_run'] ) ); ?>> <?php esc_html_e( 'No escribir cambios', 'llm-trace-cleaner' ); ?></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Perfil por defecto', 'llm-trace-cleaner' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[default_profile]">
						<?php foreach ( $profiles as $id => $p ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $settings['default_profile'], $id ); ?>><?php echo esc_html( $p['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Tamaños derivados', 'llm-trace-cleaner' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[process_subsizes]">
						<option value="none" <?php selected( $settings['process_subsizes'], 'none' ); ?>><?php esc_html_e( 'Solo original', 'llm-trace-cleaner' ); ?></option>
						<option value="large_only" <?php selected( $settings['process_subsizes'], 'large_only' ); ?>><?php esc_html_e( 'Original y grandes', 'llm-trace-cleaner' ); ?></option>
						<option value="all" <?php selected( $settings['process_subsizes'], 'all' ); ?>><?php esc_html_e( 'Todos', 'llm-trace-cleaner' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Copia de seguridad', 'llm-trace-cleaner' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[backup_enabled]" value="1" <?php checked( ! empty( $settings['backup_enabled'] ) ); ?>></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Retención (días)', 'llm-trace-cleaner' ); ?></th>
				<td><input type="number" min="1" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[backup_retention_days]" value="<?php echo esc_attr( $settings['backup_retention_days'] ); ?>"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Máx. almacenamiento (MB)', 'llm-trace-cleaner' ); ?></th>
				<td><input type="number" min="10" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[backup_max_storage_mb]" value="<?php echo esc_attr( $settings['backup_max_storage_mb'] ); ?>"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Tamaño de lote', 'llm-trace-cleaner' ); ?></th>
				<td><input type="number" min="1" max="25" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[batch_size]" value="<?php echo esc_attr( $settings['batch_size'] ); ?>"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Preservar ICC', 'llm-trace-cleaner' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[preserve_icc]" value="1" <?php checked( ! empty( $settings['preserve_icc'] ) ); ?>></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Preservar orientación', 'llm-trace-cleaner' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[preserve_orientation]" value="1" <?php checked( ! empty( $settings['preserve_orientation'] ) ); ?>></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Preservar copyright', 'llm-trace-cleaner' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[preserve_copyright]" value="1" <?php checked( ! empty( $settings['preserve_copyright'] ) ); ?>></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Detener ante C2PA', 'llm-trace-cleaner' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[stop_on_c2pa]" value="1" <?php checked( ! empty( $settings['stop_on_c2pa'] ) ); ?>></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Modo estricto', 'llm-trace-cleaner' ); ?></th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[strict_mode]" value="1" <?php checked( ! empty( $settings['strict_mode'] ) ); ?>> <?php esc_html_e( 'Bloquear subida si falla el procesamiento', 'llm-trace-cleaner' ); ?></label></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Sincronizar campos WP', 'llm-trace-cleaner' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[wp_field_sync]">
						<option value="none" <?php selected( $settings['wp_field_sync'], 'none' ); ?>><?php esc_html_e( 'No sincronizar', 'llm-trace-cleaner' ); ?></option>
						<option value="set_if_empty" <?php selected( $settings['wp_field_sync'], 'set_if_empty' ); ?>><?php esc_html_e( 'Solo si vacío', 'llm-trace-cleaner' ); ?></option>
						<option value="overwrite" <?php selected( $settings['wp_field_sync'], 'overwrite' ); ?>><?php esc_html_e( 'Sobrescribir', 'llm-trace-cleaner' ); ?></option>
					</select>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Guardar ajustes', 'llm-trace-cleaner' ) ); ?>
	</form>
</div>
