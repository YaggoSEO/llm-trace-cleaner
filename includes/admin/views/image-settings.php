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
				<th scope="row"><?php esc_html_e( 'Preservar ICC', 'llm-trace-cleaner' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[preserve_icc]" value="1" <?php checked( ! empty( $settings['preserve_icc'] ) ); ?>>
						<?php esc_html_e( 'Conservar el perfil de color ICC tras limpiar', 'llm-trace-cleaner' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'El perfil ICC describe cómo deben verse los colores (sRGB, Adobe RGB, etc.). Si lo eliminas, la imagen puede verse distinta en monitores, móviles o impresoras. Recomendado: activado. Con GD la conservación de ICC es limitada o nula.', 'llm-trace-cleaner' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Preservar orientación', 'llm-trace-cleaner' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[preserve_orientation]" value="1" <?php checked( ! empty( $settings['preserve_orientation'] ) ); ?>>
						<?php esc_html_e( 'Aplicar y normalizar la orientación EXIF', 'llm-trace-cleaner' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Muchas fotos de móvil guardan la rotación en metadatos, no en los píxeles. Al limpiar EXIF sin normalizar, la imagen puede mostrarse girada. Con esta opción, Imagick aplica la orientación a los píxeles antes de quitar metadatos. Recomendado: activado.', 'llm-trace-cleaner' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Preservar copyright', 'llm-trace-cleaner' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[preserve_copyright]" value="1" <?php checked( ! empty( $settings['preserve_copyright'] ) ); ?>>
						<?php esc_html_e( 'No borrar campos de derechos de autor existentes', 'llm-trace-cleaner' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Si la imagen ya trae copyright, crédito o autoría, el perfil no los eliminará al limpiar. Útil para fotos con licencia o material de terceros. Los perfiles corporativos pueden añadir autoría nueva además de conservar la existente.', 'llm-trace-cleaner' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Detener ante C2PA', 'llm-trace-cleaner' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[stop_on_c2pa]" value="1" <?php checked( ! empty( $settings['stop_on_c2pa'] ) ); ?>>
						<?php esc_html_e( 'No modificar si hay indicios de credenciales de contenido', 'llm-trace-cleaner' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'C2PA (Content Credentials) firma la procedencia de la imagen. Re-encodear o limpiar metadatos puede invalidar esa firma. Si se detecta un contenedor (PNG caBX o JPEG APP11/JUMBF) o una referencia en metadatos, el procesamiento se detiene. No se escanea el pixel data (IDAT/SOS) para evitar falsos positivos. La detección no valida la firma criptográfica. Quitar EXIF no elimina marcas en el píxel ni manifiestos remotos (soft-binding). Recomendado: activado.', 'llm-trace-cleaner' ); ?>
					</p>
				</td>
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
			<tr>
				<th><?php esc_html_e( 'Permitir AVIF', 'llm-trace-cleaner' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[allow_avif]" value="1" <?php checked( ! empty( $settings['allow_avif'] ) ); ?>>
						<?php esc_html_e( 'Procesar image/avif si el servidor lo soporta', 'llm-trace-cleaner' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'ExifTool (solo VPS)', 'llm-trace-cleaner' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[exiftool_enabled]" value="1" <?php checked( ! empty( $settings['exiftool_enabled'] ) ); ?>>
						<?php esc_html_e( 'Activar motor ExifTool (escritura EXIF/IPTC/XMP completa)', 'llm-trace-cleaner' ); ?>
					</label>
					<p>
						<input type="text" class="large-text code" id="llmtc-exiftool-path" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[exiftool_path]" value="<?php echo esc_attr( $settings['exiftool_path'] ? $settings['exiftool_path'] : '/usr/bin/exiftool' ); ?>" placeholder="/usr/bin/exiftool">
					</p>
					<p>
						<label><?php esc_html_e( 'Timeout (s)', 'llm-trace-cleaner' ); ?>
							<input type="number" min="5" max="120" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[exiftool_timeout]" value="<?php echo esc_attr( isset( $settings['exiftool_timeout'] ) ? $settings['exiftool_timeout'] : 30 ); ?>">
						</label>
						<button type="button" class="button" id="llmtc-test-exiftool"><?php esc_html_e( 'Probar ExifTool', 'llm-trace-cleaner' ); ?></button>
						<span id="llmtc-exiftool-msg"></span>
					</p>
					<p class="description">
						<?php esc_html_e( 'No es una extensión PHP: es un binario del sistema (Perl). En hosting compartido (cPanel/Plesk) casi nunca está disponible y no podrás instalarlo. Déjalo desactivado y pide Imagick al hosting. Solo útil en VPS/dedicado tras instalar p. ej. libimage-exiftool-perl; ruta típica /usr/bin/exiftool.', 'llm-trace-cleaner' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Reglas condicionales', 'llm-trace-cleaner' ); ?></th>
				<td>
					<textarea class="large-text code" rows="6" name="<?php echo esc_attr( LLM_Trace_Cleaner_Image_Manager::OPTION ); ?>[conditional_rules]"><?php echo esc_textarea( wp_json_encode( isset( $settings['conditional_rules'] ) ? $settings['conditional_rules'] : array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'JSON array. Cada regla: {"profile":"seo_local","mime":"image/jpeg","folder":"2024/08","max_bytes":0,"author":0}. Primera coincidencia gana. folder = ruta relativa dentro de uploads.', 'llm-trace-cleaner' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Guardar ajustes', 'llm-trace-cleaner' ) ); ?>
	</form>
</div>
