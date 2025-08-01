<?php
/**
 * Panel de Aprobación de Certificados - Solo para Administradores
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar que el usuario tenga rol administrator
$user = wp_get_current_user();
$user_roles = $user->roles;

if (!in_array('administrator', $user_roles)) {
    wp_die(__('Acceso no autorizado. Solo los administradores pueden acceder a esta página.', 'certificados-personalizados'));
}

// Process redirect messages (from approve/reject actions)
if (isset($_GET['mensaje']) && isset($_GET['texto'])) {
    $mensaje = array(
        'tipo' => sanitize_text_field($_GET['mensaje']),
        'mensaje' => sanitize_text_field($_GET['texto'])
    );
}

// Get pending certificates
$certificados_pendientes = CertificadosPersonalizadosBD::obtener_todos_certificados('pendiente');

// Get statistics
$estadisticas = CertificadosPersonalizadosBD::obtener_estadisticas();

/**
 * Obtener tipos de actividad para mostrar
 */
function obtener_tipos_actividad_admin() {
    return array(
        'curso' => 'Curso de Capacitación',
        'taller' => 'Taller Práctico',
        'seminario' => 'Seminario',
        'conferencia' => 'Conferencia',
        'workshop' => 'Workshop',
        'otro' => 'Otro'
    );
}
?>

<div class="wrap">
    <h1><?php _e('Aprobación de Certificados', 'certificados-personalizados'); ?></h1>
    
    <?php if (isset($mensaje)): ?>
        <div class="notice notice-<?php echo $mensaje['tipo']; ?> is-dismissible">
            <p><?php echo esc_html($mensaje['mensaje']); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Estadísticas -->
    <div class="estadisticas-certificados">
        <h2><?php _e('Estadísticas', 'certificados-personalizados'); ?></h2>
        <div class="estadisticas-grid">
            <div class="estadistica-item">
                <span class="estadistica-numero"><?php echo $estadisticas['pendiente']; ?></span>
                <span class="estadistica-label"><?php _e('Pendientes', 'certificados-personalizados'); ?></span>
            </div>
            <div class="estadistica-item">
                <span class="estadistica-numero"><?php echo $estadisticas['aprobado']; ?></span>
                <span class="estadistica-label"><?php _e('Aprobados', 'certificados-personalizados'); ?></span>
            </div>
            <div class="estadistica-item">
                <span class="estadistica-numero"><?php echo $estadisticas['rechazado']; ?></span>
                <span class="estadistica-label"><?php _e('Rechazados', 'certificados-personalizados'); ?></span>
            </div>
            <div class="estadistica-item">
                <span class="estadistica-numero"><?php echo $estadisticas['total']; ?></span>
                <span class="estadistica-label"><?php _e('Total', 'certificados-personalizados'); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Lista de certificados pendientes -->
    <div class="certificados-pendientes">
        <h2><?php _e('Certificados Pendientes de Aprobación', 'certificados-personalizados'); ?></h2>
        
        <?php if (empty($certificados_pendientes)): ?>
            <div class="notice notice-info">
                <p><?php _e('No hay certificados pendientes de aprobación.', 'certificados-personalizados'); ?></p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Código', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Colaborador', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Nombre del Certificado', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Tipo de Actividad', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Fecha', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Observaciones', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Fecha Solicitud', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Acciones', 'certificados-personalizados'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($certificados_pendientes as $certificado): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($certificado->codigo_unico); ?></strong>
                            </td>
                            <td><?php echo esc_html($certificado->nombre_usuario); ?></td>
                            <td><?php echo esc_html($certificado->nombre); ?></td>
                            <td>
                                <?php 
                                $tipos = obtener_tipos_actividad_admin();
                                $tipo_mostrar = isset($tipos[$certificado->actividad]) ? $tipos[$certificado->actividad] : $certificado->actividad;
                                echo esc_html($tipo_mostrar);
                                ?>
                            </td>
                            <td><?php echo esc_html(date('d/m/Y', strtotime($certificado->fecha))); ?></td>
                            <td>
                                <?php if (!empty($certificado->observaciones)): ?>
                                    <span class="observaciones-texto" title="<?php echo esc_attr($certificado->observaciones); ?>">
                                        <?php echo esc_html(substr($certificado->observaciones, 0, 50)); ?>
                                        <?php if (strlen($certificado->observaciones) > 50): ?>...<?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="sin-observaciones">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(date('d/m/Y H:i', strtotime($certificado->created_at))); ?></td>
                            <td>
                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                                    <input type="hidden" name="action" value="aprobar_certificado">
                                    <input type="hidden" name="certificado_id" value="<?php echo $certificado->id; ?>">
                                    <?php wp_nonce_field('aprobar_certificado', 'aprobar_certificado_nonce'); ?>
                                    <button type="submit" class="button button-primary" 
                                            onclick="return confirm('¿Estás seguro de que quieres aprobar este certificado?')">
                                        <?php _e('✅ Aprobar', 'certificados-personalizados'); ?>
                                    </button>
                                </form>
                                
                                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                                    <input type="hidden" name="action" value="rechazar_certificado">
                                    <input type="hidden" name="certificado_id" value="<?php echo $certificado->id; ?>">
                                    <?php wp_nonce_field('rechazar_certificado', 'rechazar_certificado_nonce'); ?>
                                    <button type="submit" class="button button-secondary" 
                                            onclick="return confirm('¿Estás seguro de que quieres rechazar este certificado?')">
                                        <?php _e('❌ Rechazar', 'certificados-personalizados'); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<style>
.estadisticas-certificados {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 30px;
}

.estadisticas-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.estadistica-item {
    text-align: center;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
    border: 1px solid #e1e1e1;
}

.estadistica-numero {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #0073aa;
}

.estadistica-label {
    display: block;
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    margin-top: 5px;
}

.certificados-pendientes {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.observaciones-texto {
    color: #666;
    font-style: italic;
}

.sin-observaciones {
    color: #999;
    font-style: italic;
}

.wp-list-table th {
    font-weight: bold;
    background: #f1f1f1;
}

.wp-list-table td {
    vertical-align: middle;
}

.button {
    margin-right: 5px;
}

.notice {
    margin: 20px 0;
}
</style> 