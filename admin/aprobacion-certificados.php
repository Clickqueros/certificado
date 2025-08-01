<?php
/**
 * Página de aprobación de certificados para administradores
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Procesar acciones si se enviaron
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_certificado'])) {
    $mensaje = procesar_accion_certificado();
}

// Obtener certificados según filtro
$estado_filtro = isset($_GET['estado']) ? sanitize_text_field($_GET['estado']) : '';
$certificados = CertificadosPersonalizadosBD::obtener_todos_certificados($estado_filtro);

// Obtener estadísticas
$estadisticas = CertificadosPersonalizadosBD::obtener_estadisticas();

/**
 * Procesar acción de certificado
 */
function procesar_accion_certificado() {
    // Verificar nonce
    if (!wp_verify_nonce($_POST['certificado_nonce'], 'accion_certificado')) {
        return array('tipo' => 'error', 'mensaje' => 'Error de seguridad.');
    }
    
    $certificado_id = intval($_POST['certificado_id']);
    $accion = sanitize_text_field($_POST['accion']);
    
    if (!in_array($accion, array('aprobar', 'rechazar', 'eliminar'))) {
        return array('tipo' => 'error', 'mensaje' => 'Acción no válida.');
    }
    
    switch ($accion) {
        case 'aprobar':
            $resultado = CertificadosPersonalizadosBD::cambiar_estado_certificado($certificado_id, 'aprobado');
            $mensaje = 'Certificado aprobado correctamente.';
            break;
            
        case 'rechazar':
            $resultado = CertificadosPersonalizadosBD::cambiar_estado_certificado($certificado_id, 'rechazado');
            $mensaje = 'Certificado rechazado correctamente.';
            break;
            
        case 'eliminar':
            $resultado = CertificadosPersonalizadosBD::eliminar_certificado($certificado_id);
            $mensaje = 'Certificado eliminado correctamente.';
            break;
    }
    
    if ($resultado) {
        return array('tipo' => 'exito', 'mensaje' => $mensaje);
    } else {
        return array('tipo' => 'error', 'mensaje' => 'Error al procesar la acción.');
    }
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
                <span class="estadistica-numero"><?php echo $estadisticas['total']; ?></span>
                <span class="estadistica-label"><?php _e('Total', 'certificados-personalizados'); ?></span>
            </div>
            <div class="estadistica-item pendiente">
                <span class="estadistica-numero"><?php echo $estadisticas['pendiente']; ?></span>
                <span class="estadistica-label"><?php _e('Pendientes', 'certificados-personalizados'); ?></span>
            </div>
            <div class="estadistica-item aprobado">
                <span class="estadistica-numero"><?php echo $estadisticas['aprobado']; ?></span>
                <span class="estadistica-label"><?php _e('Aprobados', 'certificados-personalizados'); ?></span>
            </div>
            <div class="estadistica-item rechazado">
                <span class="estadistica-numero"><?php echo $estadisticas['rechazado']; ?></span>
                <span class="estadistica-label"><?php _e('Rechazados', 'certificados-personalizados'); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="filtros-certificados">
        <h2><?php _e('Filtrar Certificados', 'certificados-personalizados'); ?></h2>
        <form method="get" action="">
            <input type="hidden" name="page" value="aprobacion-certificados">
            <select name="estado">
                <option value=""><?php _e('Todos los estados', 'certificados-personalizados'); ?></option>
                <option value="pendiente" <?php selected($estado_filtro, 'pendiente'); ?>>
                    <?php _e('Pendientes', 'certificados-personalizados'); ?>
                </option>
                <option value="aprobado" <?php selected($estado_filtro, 'aprobado'); ?>>
                    <?php _e('Aprobados', 'certificados-personalizados'); ?>
                </option>
                <option value="rechazado" <?php selected($estado_filtro, 'rechazado'); ?>>
                    <?php _e('Rechazados', 'certificados-personalizados'); ?>
                </option>
            </select>
            <input type="submit" class="button" value="<?php _e('Filtrar', 'certificados-personalizados'); ?>">
        </form>
    </div>
    
    <!-- Lista de certificados -->
    <div class="certificados-lista-admin">
        <h2><?php _e('Certificados', 'certificados-personalizados'); ?></h2>
        
        <?php if (empty($certificados)): ?>
            <p><?php _e('No hay certificados que mostrar.', 'certificados-personalizados'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Código', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Solicitante', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Nombre', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Actividad', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Fecha', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Estado', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Fecha Solicitud', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Acciones', 'certificados-personalizados'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($certificados as $certificado): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($certificado->codigo_unico); ?></strong>
                            </td>
                            <td><?php echo esc_html($certificado->nombre_usuario); ?></td>
                            <td><?php echo esc_html($certificado->nombre); ?></td>
                            <td><?php echo esc_html($certificado->actividad); ?></td>
                            <td><?php echo esc_html(date('d/m/Y', strtotime($certificado->fecha))); ?></td>
                            <td>
                                <?php
                                $estado_clase = '';
                                $estado_texto = '';
                                
                                switch ($certificado->estado) {
                                    case 'pendiente':
                                        $estado_clase = 'estado-pendiente';
                                        $estado_texto = __('Pendiente', 'certificados-personalizados');
                                        break;
                                    case 'aprobado':
                                        $estado_clase = 'estado-aprobado';
                                        $estado_texto = __('Aprobado', 'certificados-personalizados');
                                        break;
                                    case 'rechazado':
                                        $estado_clase = 'estado-rechazado';
                                        $estado_texto = __('Rechazado', 'certificados-personalizados');
                                        break;
                                }
                                ?>
                                <span class="estado-certificado <?php echo $estado_clase; ?>">
                                    <?php echo $estado_texto; ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(date('d/m/Y H:i', strtotime($certificado->created_at))); ?></td>
                            <td>
                                <?php if ($certificado->estado === 'pendiente'): ?>
                                    <form method="post" action="" style="display: inline;">
                                        <?php wp_nonce_field('accion_certificado', 'certificado_nonce'); ?>
                                        <input type="hidden" name="certificado_id" value="<?php echo $certificado->id; ?>">
                                        <input type="hidden" name="accion" value="aprobar">
                                        <button type="submit" class="button button-small button-primary" 
                                                onclick="return confirm('¿Aprobar este certificado?')">
                                            <?php _e('Aprobar', 'certificados-personalizados'); ?>
                                        </button>
                                    </form>
                                    
                                    <form method="post" action="" style="display: inline;">
                                        <?php wp_nonce_field('accion_certificado', 'certificado_nonce'); ?>
                                        <input type="hidden" name="certificado_id" value="<?php echo $certificado->id; ?>">
                                        <input type="hidden" name="accion" value="rechazar">
                                        <button type="submit" class="button button-small button-secondary" 
                                                onclick="return confirm('¿Rechazar este certificado?')">
                                            <?php _e('Rechazar', 'certificados-personalizados'); ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <form method="post" action="" style="display: inline;">
                                    <?php wp_nonce_field('accion_certificado', 'certificado_nonce'); ?>
                                    <input type="hidden" name="certificado_id" value="<?php echo $certificado->id; ?>">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <button type="submit" class="button button-small button-link-delete" 
                                            onclick="return confirm('¿Eliminar este certificado? Esta acción no se puede deshacer.')">
                                        <?php _e('Eliminar', 'certificados-personalizados'); ?>
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
    margin-bottom: 20px;
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
    border-radius: 4px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
}

.estadistica-item.pendiente {
    background: #fff3cd;
    border-color: #ffeaa7;
}

.estadistica-item.aprobado {
    background: #d4edda;
    border-color: #c3e6cb;
}

.estadistica-item.rechazado {
    background: #f8d7da;
    border-color: #f5c6cb;
}

.estadistica-numero {
    display: block;
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 5px;
}

.estadistica-label {
    font-size: 12px;
    text-transform: uppercase;
    font-weight: bold;
}

.filtros-certificados {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 20px;
}

.certificados-lista-admin {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.estado-certificado {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
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

.form-table th {
    width: 200px;
}
</style> 