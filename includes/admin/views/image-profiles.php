<?php
/**
 * Vista: Perfiles + editor de metadata.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

$profiles  = LLM_Trace_Cleaner_Image_Profile::all();
$edit_id   = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : 'seo_local';
if ( ! isset( $profiles[ $edit_id ] ) ) {
	$edit_id = array_key_first( $profiles );
}
$profile = $profiles[ $edit_id ];
$meta    = isset( $profile['metadata'] ) && is_array( $profile['metadata'] ) ? $profile['metadata'] : array();
$base    = admin_url( 'admin.php?page=llm-trace-cleaner-images&tab=profiles' );

/**
 * Helper valor anidado.
 *
 * @param array  $arr Array.
 * @param string $path Path a.b.c.
 * @return string
 */
$val = function ( $arr, $path ) {
	$parts = explode( '.', $path );
	$cur   = $arr;
	foreach ( $parts as $p ) {
		if ( ! is_array( $cur ) || ! isset( $cur[ $p ] ) ) {
			return '';
		}
		$cur = $cur[ $p ];
	}
	if ( is_array( $cur ) ) {
		return implode( ', ', $cur );
	}
	return (string) $cur;
};
?>
<div class="llmtc-panel">
	<h2><?php esc_html_e( 'Perfiles de metadatos', 'llm-trace-cleaner' ); ?></h2>
	<p><?php esc_html_e( 'Utiliza “ubicación mostrada” para el lugar representado o el ámbito geográfico. No se escriben coordenadas GPS de captura desde estos formularios.', 'llm-trace-cleaner' ); ?></p>

	<table class="widefat striped" style="margin-bottom:1.5em;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'llm-trace-cleaner' ); ?></th>
				<th><?php esc_html_e( 'Nombre', 'llm-trace-cleaner' ); ?></th>
				<th><?php esc_html_e( 'Tipo', 'llm-trace-cleaner' ); ?></th>
				<th><?php esc_html_e( 'Acciones', 'llm-trace-cleaner' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $profiles as $id => $p ) : ?>
			<tr<?php echo ( $id === $edit_id ) ? ' style="background:#f0f6fc;"' : ''; ?>>
				<td><code><?php echo esc_html( $id ); ?></code></td>
				<td><?php echo esc_html( $p['name'] ); ?></td>
				<td><?php echo ! empty( $p['locked'] ) ? esc_html__( 'Predeterminado', 'llm-trace-cleaner' ) : esc_html__( 'Personalizado', 'llm-trace-cleaner' ); ?></td>
				<td>
					<a href="<?php echo esc_url( add_query_arg( 'edit', $id, $base ) ); ?>"><?php esc_html_e( 'Editar', 'llm-trace-cleaner' ); ?></a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<h3><?php echo esc_html( sprintf( /* translators: profile name */ __( 'Editar: %s', 'llm-trace-cleaner' ), $profile['name'] ) ); ?></h3>
	<p class="description"><?php echo esc_html( $profile['description'] ); ?></p>

	<?php if ( empty( $meta ) && ! empty( $profile['locked'] ) ) : ?>
		<div class="notice notice-info inline"><p>
			<?php esc_html_e( 'Este perfil no escribe metadatos nuevos (solo limpia o conserva). No hay campos editables.', 'llm-trace-cleaner' ); ?>
		</p></div>
	<?php else : ?>
		<form id="llmtc-profile-form" method="post">
			<input type="hidden" name="profile_id" id="llmtc-profile-id" value="<?php echo esc_attr( $edit_id ); ?>">
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Descripción', 'llm-trace-cleaner' ); ?></th>
					<td><textarea name="description" id="llmtc-profile-desc" class="large-text" rows="2"><?php echo esc_textarea( $profile['description'] ); ?></textarea></td>
				</tr>

				<?php if ( empty( $profile['locked'] ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'Nombre', 'llm-trace-cleaner' ); ?></th>
					<td><input type="text" name="name" id="llmtc-profile-name" class="regular-text" value="<?php echo esc_attr( $profile['name'] ); ?>"></td>
				</tr>
				<?php endif; ?>

				<?php if ( isset( $meta['creator'] ) || ! empty( $profile['rules']['creator'] ) ) : ?>
				<tr><th colspan="2"><h4 style="margin:0;"><?php esc_html_e( 'Autoría', 'llm-trace-cleaner' ); ?></h4></th></tr>
				<tr>
					<th><?php esc_html_e( 'Nombre / autor', 'llm-trace-cleaner' ); ?></th>
					<td><input type="text" class="regular-text llmtc-meta" data-path="creator.name" value="<?php echo esc_attr( $val( $meta, 'creator.name' ) ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Empresa', 'llm-trace-cleaner' ); ?></th>
					<td><input type="text" class="regular-text llmtc-meta" data-path="creator.company" value="<?php echo esc_attr( $val( $meta, 'creator.company' ) ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Crédito', 'llm-trace-cleaner' ); ?></th>
					<td><input type="text" class="regular-text llmtc-meta" data-path="creator.credit" value="<?php echo esc_attr( $val( $meta, 'creator.credit' ) ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Fuente', 'llm-trace-cleaner' ); ?></th>
					<td><input type="text" class="regular-text llmtc-meta" data-path="creator.source" value="<?php echo esc_attr( $val( $meta, 'creator.source' ) ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Web', 'llm-trace-cleaner' ); ?></th>
					<td><input type="url" class="regular-text llmtc-meta" data-path="creator.website" value="<?php echo esc_attr( $val( $meta, 'creator.website' ) ); ?>"></td>
				</tr>
				<?php endif; ?>

				<?php if ( isset( $meta['rights'] ) || ! empty( $profile['rules']['rights'] ) ) : ?>
				<tr><th colspan="2"><h4 style="margin:0;"><?php esc_html_e( 'Derechos', 'llm-trace-cleaner' ); ?></h4></th></tr>
				<tr>
					<th><?php esc_html_e( 'Copyright', 'llm-trace-cleaner' ); ?></th>
					<td><textarea class="large-text llmtc-meta" data-path="rights.copyright" rows="2"><?php echo esc_textarea( $val( $meta, 'rights.copyright' ) ); ?></textarea></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Licencia', 'llm-trace-cleaner' ); ?></th>
					<td><input type="text" class="regular-text llmtc-meta" data-path="rights.license" value="<?php echo esc_attr( $val( $meta, 'rights.license' ) ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Términos de uso', 'llm-trace-cleaner' ); ?></th>
					<td><input type="text" class="regular-text llmtc-meta" data-path="rights.usage" value="<?php echo esc_attr( $val( $meta, 'rights.usage' ) ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'URL de derechos', 'llm-trace-cleaner' ); ?></th>
					<td><input type="url" class="regular-text llmtc-meta" data-path="rights.rights_url" value="<?php echo esc_attr( $val( $meta, 'rights.rights_url' ) ); ?>"></td>
				</tr>
				<?php endif; ?>

				<?php if ( isset( $meta['content'] ) || ! empty( $profile['rules']['content'] ) ) : ?>
				<tr><th colspan="2"><h4 style="margin:0;"><?php esc_html_e( 'Contenido', 'llm-trace-cleaner' ); ?></h4></th></tr>
				<tr>
					<th><?php esc_html_e( 'Título', 'llm-trace-cleaner' ); ?></th>
					<td><input type="text" class="regular-text llmtc-meta" data-path="content.title" value="<?php echo esc_attr( $val( $meta, 'content.title' ) ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Descripción', 'llm-trace-cleaner' ); ?></th>
					<td><textarea class="large-text llmtc-meta" data-path="content.description" rows="3"><?php echo esc_textarea( $val( $meta, 'content.description' ) ); ?></textarea></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Keywords', 'llm-trace-cleaner' ); ?></th>
					<td>
						<input type="text" class="large-text llmtc-meta" data-path="content.keywords" value="<?php echo esc_attr( $val( $meta, 'content.keywords' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Separadas por comas.', 'llm-trace-cleaner' ); ?></p>
					</td>
				</tr>
				<?php endif; ?>

				<?php if ( isset( $meta['location']['shown'] ) || ! empty( $profile['rules']['location_shown'] ) ) : ?>
				<tr><th colspan="2"><h4 style="margin:0;"><?php esc_html_e( 'Ubicación mostrada (no GPS de captura)', 'llm-trace-cleaner' ); ?></h4></th></tr>
				<tr>
					<th><?php esc_html_e( 'Nombre del lugar', 'llm-trace-cleaner' ); ?></th>
					<td><input type="text" class="regular-text llmtc-meta" data-path="location.shown.name" value="<?php echo esc_attr( $val( $meta, 'location.shown.name' ) ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Ciudad', 'llm-trace-cleaner' ); ?></th>
					<td><input type="text" class="regular-text llmtc-meta" data-path="location.shown.city" value="<?php echo esc_attr( $val( $meta, 'location.shown.city' ) ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Provincia / región', 'llm-trace-cleaner' ); ?></th>
					<td><input type="text" class="regular-text llmtc-meta" data-path="location.shown.region" value="<?php echo esc_attr( $val( $meta, 'location.shown.region' ) ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'País', 'llm-trace-cleaner' ); ?></th>
					<td><input type="text" class="regular-text llmtc-meta" data-path="location.shown.country" value="<?php echo esc_attr( $val( $meta, 'location.shown.country' ) ); ?>"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Código país (ISO-2)', 'llm-trace-cleaner' ); ?></th>
					<td><input type="text" class="small-text llmtc-meta" data-path="location.shown.country_code" maxlength="2" value="<?php echo esc_attr( $val( $meta, 'location.shown.country_code' ) ); ?>" placeholder="ES"></td>
				</tr>
				<?php endif; ?>

				<?php if ( isset( $meta['digital'] ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'Tipo de fuente digital', 'llm-trace-cleaner' ); ?></th>
					<td><input type="text" class="regular-text llmtc-meta" data-path="digital.source_type" value="<?php echo esc_attr( $val( $meta, 'digital.source_type' ) ); ?>"></td>
				</tr>
				<?php endif; ?>
			</table>

			<p>
				<button type="button" class="button button-primary" id="llmtc-profile-save"><?php esc_html_e( 'Guardar perfil', 'llm-trace-cleaner' ); ?></button>
				<span id="llmtc-profile-save-msg" style="margin-left:8px;"></span>
			</p>
		</form>
	<?php endif; ?>

	<hr>
	<h3><?php esc_html_e( 'Duplicar / importar / exportar', 'llm-trace-cleaner' ); ?></h3>
	<p>
		<button type="button" class="button" id="llmtc-profile-duplicate" data-source="<?php echo esc_attr( $edit_id ); ?>"><?php esc_html_e( 'Duplicar perfil actual', 'llm-trace-cleaner' ); ?></button>
		<button type="button" class="button" id="llmtc-profile-export"><?php esc_html_e( 'Exportar JSON', 'llm-trace-cleaner' ); ?></button>
	</p>
	<p>
		<label><?php esc_html_e( 'Importar JSON', 'llm-trace-cleaner' ); ?>
			<textarea id="llmtc-profile-import-json" class="large-text code" rows="4" placeholder="{}"></textarea>
		</label>
		<button type="button" class="button" id="llmtc-profile-import"><?php esc_html_e( 'Importar', 'llm-trace-cleaner' ); ?></button>
	</p>

	<form method="post" style="margin-top:1em;">
		<?php wp_nonce_field( 'llmtc_image_restore_profiles', 'llmtc_restore_profiles_nonce' ); ?>
		<input type="hidden" name="llmtc_restore_profiles" value="1">
		<?php submit_button( __( 'Restaurar perfiles predeterminados', 'llm-trace-cleaner' ), 'secondary', 'submit', false ); ?>
	</form>
</div>
<?php
if ( ! empty( $_POST['llmtc_restore_profiles'] ) && check_admin_referer( 'llmtc_image_restore_profiles', 'llmtc_restore_profiles_nonce' ) && current_user_can( 'manage_options' ) ) {
	LLM_Trace_Cleaner_Image_Profile::restore_defaults();
	echo '<div class="notice notice-success"><p>' . esc_html__( 'Perfiles predeterminados restaurados (se conservan personalizados).', 'llm-trace-cleaner' ) . '</p></div>';
}
?>
