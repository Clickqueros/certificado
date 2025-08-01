<?php
/**
 * Formulario para colaboradores - Solicitud de certificados
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar que el usuario tenga rol contributor
$user = wp_get_current_user();
$user_roles = $user->roles;

if (!in_array('contributor', $user_roles)) {
    wp_die(__('Acceso no autorizado. Solo los colaboradores pueden acceder a esta página.', 'certificados-personalizados'));
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
    $fecha_evento = sanitize_text_field($_POST['fecha_evento']);
    $tipo_actividad = sanitize_text_field($_POST['tipo_actividad']);
    $observaciones = sanitize_textarea_field($_POST['observaciones']);
    
    // Validaciones
    if (empty($nombre) || empty($fecha_evento) || empty($tipo_actividad)) {
        return array('tipo' => 'error', 'mensaje' => 'Los campos Nombre, Fecha del evento y Tipo de actividad son obligatorios.');
    }
    
    // Validar fecha
    $fecha_actual = date('Y-m-d');
    if ($fecha_evento > $fecha_actual) {
        return array('tipo' => 'error', 'mensaje' => 'La fecha del evento no puede ser futura.');
    }
    
    // Validar tipo de actividad
    $tipos_validos = array('curso', 'taller', 'seminario', 'conferencia', 'workshop', 'otro');
    if (!in_array($tipo_actividad, $tipos_validos)) {
        return array('tipo' => 'error', 'mensaje' => 'Tipo de actividad no válido.');
    }
    
    // Crear certificado
    $datos = array(
        'nombre' => $nombre,
        'actividad' => $tipo_actividad,
        'fecha' => $fecha_evento,
        'observaciones' => $observaciones
    );
    
    $certificado_id = CertificadosPersonalizadosBD::crear_certificado($datos);
    
    if ($certificado_id) {
        $certificado = CertificadosPersonalizadosBD::obtener_certificado($certificado_id);
        return array(
            'tipo' => 'exito', 
            'mensaje' => 'Certificado solicitado correctamente. Código: ' . $certificado->codigo_unico,
            'certificado_id' => $certificado_id
        );
    } else {
        // Agregar información de debug
        global $wpdb;
        $error_msg = 'Error al crear el certificado.';
        if ($wpdb->last_error) {
            $error_msg .= ' Error DB: ' . $wpdb->last_error;
        }
        return array('tipo' => 'error', 'mensaje' => $error_msg);
    }
}

/**
 * Obtener tipos de actividad
 */
function obtener_tipos_actividad() {
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
    <h1><?php _e('Mis Certificados', 'certificados-personalizados'); ?></h1>
    
    <?php if (isset($mensaje)): ?>
        <div class="notice notice-<?php echo $mensaje['tipo']; ?> is-dismissible">
            <p><?php echo esc_html($mensaje['mensaje']); ?></p>
            <?php if ($mensaje['tipo'] === 'exito' && isset($mensaje['certificado_id'])): ?>
                <p>
                    <button class="button button-secondary" disabled>
                        <?php _e('Enviar para aprobación', 'certificados-personalizados'); ?>
                    </button>
                    <small><?php _e('(Funcionalidad disponible próximamente)', 'certificados-personalizados'); ?></small>
                </p>
            <?php endif; ?>
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
                            <label for="nombre"><?php _e('Nombre Completo', 'certificados-personalizados'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" id="nombre" name="nombre" class="regular-text" 
                                   value="<?php echo esc_attr(wp_get_current_user()->display_name); ?>" required>
                            <p class="description"><?php _e('Nombre completo de la persona que solicita el certificado.', 'certificados-personalizados'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="fecha_evento"><?php _e('Fecha del Evento', 'certificados-personalizados'); ?> *</label>
                        </th>
                        <td>
                            <input type="date" id="fecha_evento" name="fecha_evento" required>
                            <p class="description"><?php _e('Fecha en que se realizó la actividad o evento.', 'certificados-personalizados'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="tipo_actividad"><?php _e('Tipo de Actividad', 'certificados-personalizados'); ?> *</label>
                        </th>
                        <td>
                            <select id="tipo_actividad" name="tipo_actividad" required>
                                <option value=""><?php _e('Seleccionar tipo de actividad', 'certificados-personalizados'); ?></option>
                                <?php 
                                $tipos = obtener_tipos_actividad();
                                foreach ($tipos as $valor => $etiqueta): 
                                ?>
                                    <option value="<?php echo esc_attr($valor); ?>">
                                        <?php echo esc_html($etiqueta); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Selecciona el tipo de actividad o evento.', 'certificados-personalizados'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="observaciones"><?php _e('Observaciones', 'certificados-personalizados'); ?></label>
                        </th>
                        <td>
                            <textarea id="observaciones" name="observaciones" rows="4" cols="50" class="large-text"></textarea>
                            <p class="description"><?php _e('Información adicional sobre la actividad, curso o evento (opcional).', 'certificados-personalizados'); ?></p>
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
                            <th><?php _e('Tipo de Actividad', 'certificados-personalizados'); ?></th>
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
                                <td>
                                    <?php 
                                    $tipos = obtener_tipos_actividad();
                                    $tipo_mostrar = isset($tipos[$certificado->actividad]) ? $tipos[$certificado->actividad] : $certificado->actividad;
                                    echo esc_html($tipo_mostrar);
                                    ?>
                                </td>
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

.notice p {
    margin: 0.5em 0;
}

.notice p:first-child {
    margin-top: 0;
}

.notice p:last-child {
    margin-bottom: 0;
}
</style> 