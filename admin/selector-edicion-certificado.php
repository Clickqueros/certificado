<?php
/**
 * Selector de Certificados para Edición - Solo para Administradores
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

// Get filter parameter
$estado_filtro = isset($_GET['estado']) ? sanitize_text_field($_GET['estado']) : 'todos';

// Get certificates based on filter
if ($estado_filtro === 'todos') {
    $certificados = CertificadosAntecoreBD::obtener_todos_certificados();
} else {
    $certificados = CertificadosAntecoreBD::obtener_todos_certificados($estado_filtro);
}

// Get statistics
$estadisticas = CertificadosAntecoreBD::obtener_estadisticas();
?>

<div class="wrap">
    <h1><?php _e('Seleccionar Certificado para Editar', 'certificados-personalizados'); ?></h1>
    
    <!-- Navegación -->
    <p>
        <a href="<?php echo admin_url('admin.php?page=aprobacion-certificados'); ?>" class="button button-secondary">
            ← <?php _e('Ver Lista de Certificados', 'certificados-personalizados'); ?>
        </a>
    </p>
    
    <!-- Estadísticas -->
    <div class="estadisticas-certificados" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
        <h3><?php _e('Estadísticas', 'certificados-personalizados'); ?></h3>
        <p>
            <strong><?php _e('Total:', 'certificados-personalizados'); ?></strong> <?php echo $estadisticas['total']; ?> | 
            <strong><?php _e('Pendientes:', 'certificados-personalizados'); ?></strong> <?php echo $estadisticas['pendientes']; ?> | 
            <strong><?php _e('Aprobados:', 'certificados-personalizados'); ?></strong> <?php echo $estadisticas['aprobados']; ?> | 
            <strong><?php _e('Rechazados:', 'certificados-personalizados'); ?></strong> <?php echo $estadisticas['rechazados']; ?>
        </p>
    </div>
    
    <!-- Filtros -->
    <div class="filtros-certificados" style="margin: 20px 0;">
        <form method="get" style="display: inline-block;">
            <input type="hidden" name="page" value="editar-certificado">
            <select name="estado" onchange="this.form.submit()">
                <option value="todos" <?php selected($estado_filtro, 'todos'); ?>><?php _e('Todos los Estados', 'certificados-personalizados'); ?></option>
                <option value="pendiente" <?php selected($estado_filtro, 'pendiente'); ?>><?php _e('Pendientes', 'certificados-personalizados'); ?></option>
                <option value="aprobado" <?php selected($estado_filtro, 'aprobado'); ?>><?php _e('Aprobados', 'certificados-personalizados'); ?></option>
                <option value="rechazado" <?php selected($estado_filtro, 'rechazado'); ?>><?php _e('Rechazados', 'certificados-personalizados'); ?></option>
            </select>
        </form>
    </div>
    
    <!-- Lista de Certificados -->
    <?php if (!empty($certificados)): ?>
        <div class="tabla-certificados">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Código', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Tipo', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Número', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Instalación', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Capacidad', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Tanques', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Estado', 'certificados-personalizados'); ?></th>
                        <th><?php _e('PDF', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Fecha Solicitud', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Acciones', 'certificados-personalizados'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($certificados as $certificado): ?>
                        <tr>
                            <td><strong><?php echo esc_html($certificado->codigo_unico); ?></strong></td>
                            <td><?php echo esc_html($certificado->tipo_certificado); ?></td>
                            <td><?php echo esc_html($certificado->numero_certificado); ?></td>
                            <td>
                                <?php echo esc_html($certificado->nombre_instalacion); ?>
                                <?php if (!empty($certificado->razon_social)): ?>
                                    <br><small><?php echo esc_html($certificado->razon_social); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($certificado->capacidad_almacenamiento); ?> galones</td>
                            <td><?php echo esc_html($certificado->numero_tanques); ?></td>
                            <td>
                                <?php
                                $estado_class = '';
                                $estado_texto = '';
                                switch ($certificado->estado) {
                                    case 'pendiente':
                                        $estado_class = 'estado-pendiente';
                                        $estado_texto = __('PENDIENTE', 'certificados-personalizados');
                                        break;
                                    case 'aprobado':
                                        $estado_class = 'estado-aprobado';
                                        $estado_texto = __('APROBADO', 'certificados-personalizados');
                                        break;
                                    case 'rechazado':
                                        $estado_class = 'estado-rechazado';
                                        $estado_texto = __('RECHAZADO', 'certificados-personalizados');
                                        break;
                                }
                                ?>
                                <span class="estado-badge <?php echo $estado_class; ?>">
                                    <?php echo $estado_texto; ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($certificado->pdf_path)): ?>
                                    <a href="<?php echo esc_url($certificado->pdf_path); ?>" target="_blank" class="button button-small">
                                        📄 <?php _e('Ver PDF', 'certificados-personalizados'); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #666;"><?php _e('No disponible', 'certificados-personalizados'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($certificado->created_at)); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=editar-certificado&editar=' . $certificado->id); ?>" 
                                   class="button button-primary">
                                    ✏️ <?php _e('Editar', 'certificados-personalizados'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="notice notice-info">
            <p><?php _e('No se encontraron certificados para el filtro seleccionado.', 'certificados-personalizados'); ?></p>
        </div>
    <?php endif; ?>
</div>

<style>
.estado-badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.estado-pendiente {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.estado-aprobado {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.estado-rechazado {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.estadisticas-certificados h3 {
    margin-top: 0;
    color: #0073aa;
}

.tabla-certificados {
    margin-top: 20px;
}

.tabla-certificados th {
    background-color: #f1f1f1;
    font-weight: bold;
}

.tabla-certificados td {
    vertical-align: top;
}

.tabla-certificados .button {
    margin: 2px;
}
</style>
