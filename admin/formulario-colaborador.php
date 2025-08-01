<?php
/**
 * Formulario para colaboradores - Solicitud de certificados
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Procesar formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar_certificado'])) {
    $mensaje = procesar_solicitud_certificado();
}

// Obtener certificados del usuario actual
$certificados = CertificadosPersonalizadosBD::obtener_certificados_usuario();

/**
 * Procesar solicitud de certificado
 */
function procesar_solicitud_certificado() {
    // Verificar nonce
    if (!wp_verify_nonce($_POST['certificado_nonce'], 'solicitar_certificado')) {
        return array('tipo' => 'error', 'mensaje' => 'Error de seguridad.');
    }
    
    // Validar datos
    $nombre = sanitize_text_field($_POST['nombre']);
    $actividad = sanitize_text_field($_POST['actividad']);
    $fecha = sanitize_text_field($_POST['fecha']);
    
    if (empty($nombre) || empty($actividad) || empty($fecha)) {
        return array('tipo' => 'error', 'mensaje' => 'Todos los campos son obligatorios.');
    }
    
    // Validar fecha
    $fecha_actual = date('Y-m-d');
    if ($fecha > $fecha_actual) {
        return array('tipo' => 'error', 'mensaje' => 'La fecha no puede ser futura.');
    }
    
    // Crear certificado
    $datos = array(
        'nombre' => $nombre,
        'actividad' => $actividad,
        'fecha' => $fecha
    );
    
    $certificado_id = CertificadosPersonalizadosBD::crear_certificado($datos);
    
    if ($certificado_id) {
        return array('tipo' => 'exito', 'mensaje' => 'Certificado solicitado correctamente. Código: ' . CertificadosPersonalizadosBD::generar_codigo_unico());
    } else {
        return array('tipo' => 'error', 'mensaje' => 'Error al crear el certificado.');
    }
}
?>

<div class="wrap">
    <h1><?php _e('Mis Certificados', 'certificados-personalizados'); ?></h1>
    
    <?php if (isset($mensaje)): ?>
        <div class="notice notice-<?php echo $mensaje['tipo']; ?> is-dismissible">
            <p><?php echo esc_html($mensaje['mensaje']); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="certificados-container">
        <!-- Formulario de solicitud -->
        <div class="certificado-formulario">
            <h2><?php _e('Solicitar Nuevo Certificado', 'certificados-personalizados'); ?></h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('solicitar_certificado', 'certificado_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="nombre"><?php _e('Nombre Completo', 'certificados-personalizados'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="nombre" name="nombre" class="regular-text" 
                                   value="<?php echo esc_attr(wp_get_current_user()->display_name); ?>" required>
                            <p class="description"><?php _e('Nombre completo de la persona que solicita el certificado.', 'certificados-personalizados'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="actividad"><?php _e('Actividad o Curso', 'certificados-personalizados'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="actividad" name="actividad" class="regular-text" required>
                            <p class="description"><?php _e('Descripción de la actividad, curso o evento.', 'certificados-personalizados'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="fecha"><?php _e('Fecha de la Actividad', 'certificados-personalizados'); ?></label>
                        </th>
                        <td>
                            <input type="date" id="fecha" name="fecha" required>
                            <p class="description"><?php _e('Fecha en que se realizó la actividad.', 'certificados-personalizados'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="solicitar_certificado" class="button-primary" 
                           value="<?php _e('Solicitar Certificado', 'certificados-personalizados'); ?>">
                </p>
            </form>
        </div>
        
        <!-- Lista de certificados -->
        <div class="certificado-lista">
            <h2><?php _e('Mis Certificados', 'certificados-personalizados'); ?></h2>
            
            <?php if (empty($certificados)): ?>
                <p><?php _e('No tienes certificados solicitados.', 'certificados-personalizados'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Código', 'certificados-personalizados'); ?></th>
                            <th><?php _e('Nombre', 'certificados-personalizados'); ?></th>
                            <th><?php _e('Actividad', 'certificados-personalizados'); ?></th>
                            <th><?php _e('Fecha', 'certificados-personalizados'); ?></th>
                            <th><?php _e('Estado', 'certificados-personalizados'); ?></th>
                            <th><?php _e('Fecha Solicitud', 'certificados-personalizados'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($certificados as $certificado): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($certificado->codigo_unico); ?></strong>
                                </td>
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.certificados-container {
    margin-top: 20px;
}

.certificado-formulario {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 30px;
}

.certificado-lista {
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