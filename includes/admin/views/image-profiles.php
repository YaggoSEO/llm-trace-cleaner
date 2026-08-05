<?php
/**
 * Vista: Perfiles.
 *
 * @package LLM_Trace_Cleaner
 */

defined( 'ABSPATH' ) || exit;

$profiles = LLM_Trace_Cleaner_Image_Profile::all();
?>
<div class="llmtc-panel">
	<h2><?php esc_html_e( 'Perfiles de metadatos', 'llm-trace-cleaner' ); ?></h2>
	<p><?php esc_html_e( 'Utiliza “ubicación mostrada” para el lugar representado. No uses GPS de captura salvo fotografía real.', 'llm-trace-cleaner' ); ?></p>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'llm-trace-cleaner' ); ?></th>
				<th><?php esc_html_e( 'Nombre', 'llm-trace-cleaner' ); ?></th>
				<th><?php esc_html_e( 'Descripción', 'llm-trace-cleaner' ); ?></th>
				<th><?php esc_html_e( 'Bloqueado', 'llm-trace-cleaner' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $profiles as $id => $p ) : ?>
			<tr>
				<td><code><?php echo esc_html( $id ); ?></code></td>
				<td><?php echo esc_html( $p['name'] ); ?></td>
				<td><?php echo esc_html( $p['description'] ); ?></td>
				<td><?php echo ! empty( $p['locked'] ) ? 'Sí' : 'No'; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<form method="post" style="margin-top:1em;">
		<?php wp_nonce_field( 'llmtc_image_restore_profiles', 'llmtc_restore_profiles_nonce' ); ?>
		<input type="hidden" name="llmtc_restore_profiles" value="1">
		<?php submit_button( __( 'Restaurar perfiles predeterminados', 'llm-trace-cleaner' ), 'secondary', 'submit', false ); ?>
	</form>
</div>
<?php
if ( ! empty( $_POST['llmtc_restore_profiles'] ) && check_admin_referer( 'llmtc_image_restore_profiles', 'llmtc_restore_profiles_nonce' ) && current_user_can( 'manage_options' ) ) {
	LLM_Trace_Cleaner_Image_Profile::restore_defaults();
	echo '<div class="notice notice-success"><p>' . esc_html__( 'Perfiles restaurados.', 'llm-trace-cleaner' ) . '</p></div>';
}
?>
