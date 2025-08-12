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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar_certificado'])) {
    $mensaje = procesar_solicitud_certificado();
}

// Process redirect messages (from approval submission)
if (isset($_GET['mensaje']) && isset($_GET['texto'])) {
    $mensaje = array(
        'tipo' => sanitize_text_field($_GET['mensaje']),
        'mensaje' => sanitize_text_field($_GET['texto'])
    );
}

// Get current user's certificates
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
        // Generar PDF automáticamente
        $pdf_generado = CertificadosPersonalizadosPDF::generar_certificado_pdf($certificado_id);
        
        $certificado = CertificadosPersonalizadosBD::obtener_certificado($certificado_id);
        return array(
            'tipo' => 'exito', 
            'mensaje' => 'Certificado solicitado correctamente. Código: ' . $certificado->codigo_unico,
            'certificado_id' => $certificado_id,
            'pdf_generado' => $pdf_generado
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
    
    <!-- Botón para actualizar tabla (solo para desarrollo) -->
    <?php if (current_user_can('manage_options')): ?>
        <div class="notice notice-info">
            <p>
                <strong>Desarrollo:</strong> 
                <a href="<?php echo admin_url('admin-post.php?action=actualizar_tabla_certificados'); ?>" 
                   class="button button-secondary">
                    Actualizar Tabla de Base de Datos
                </a>
                <small>(Solo para administradores)</small>
            </p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($mensaje)): ?>
        <div class="notice notice-<?php echo $mensaje['tipo']; ?> is-dismissible">
            <p><?php echo esc_html($mensaje['mensaje']); ?></p>
            <?php if ($mensaje['tipo'] === 'exito' && isset($mensaje['certificado_id'])): ?>
                <p>
                    <strong><?php _e('✅ Certificado creado exitosamente', 'certificados-personalizados'); ?></strong><br>
                    <small><?php _e('Ahora puedes enviarlo para aprobación usando el botón en la tabla de abajo.', 'certificados-personalizados'); ?></small>
                    <?php if (isset($mensaje['pdf_generado']) && $mensaje['pdf_generado']): ?>
                        <br><small style="color: #28a745;"><?php _e('📄 PDF generado automáticamente', 'certificados-personalizados'); ?></small>
                    <?php else: ?>
                        <br><small style="color: #ffc107;"><?php _e('⚠️ PDF no se pudo generar (se creará después)', 'certificados-personalizados'); ?></small>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div class="certificados-container">
        <!-- Formulario de solicitud -->
        <div class="certificado-formulario">
            <h2><?php _e('Solicitar Nuevo Certificado', 'certificados-personalizados'); ?></h2>
            
            <form method="post" action="" id="formulario-certificado">
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
                    <button type="button" id="btn-confirmar-certificado" class="button-primary">
                        <?php _e('Solicitar Certificado', 'certificados-personalizados'); ?>
                    </button>
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
                            <th><?php _e('PDF', 'certificados-personalizados'); ?></th>
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
                                <td>
                                    <?php if ($certificado->pdf_path): ?>
                                        <a href="<?php echo esc_url($certificado->pdf_path); ?>" target="_blank" class="button button-small">
                                            <?php _e('Ver PDF', 'certificados-personalizados'); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="estado-no-enviado" style="color: #666;">
                                            <?php _e('No disponible', 'certificados-personalizados'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(date('d/m/Y H:i', strtotime($certificado->created_at))); ?></td>
                                <td>
                                    <?php if ($certificado->estado === 'pendiente' && $certificado->notificado == 0): ?>
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                                            <input type="hidden" name="action" value="enviar_certificado_aprobacion">
                                            <input type="hidden" name="certificado_id" value="<?php echo $certificado->id; ?>">
                                            <?php wp_nonce_field('enviar_certificado_aprobacion', 'enviar_aprobacion_nonce'); ?>
                                            <button type="submit" class="button button-primary">
                                                <?php _e('Enviar para Aprobación', 'certificados-personalizados'); ?>
                                            </button>
                                        </form>
                                    <?php elseif ($certificado->notificado == 1): ?>
                                        <span class="estado-enviado" style="color: #0073aa; font-weight: bold;">
                                            <?php _e('✅ Enviado', 'certificados-personalizados'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="estado-no-enviado" style="color: #666;">
                                            <?php _e('No disponible', 'certificados-personalizados'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
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

/* Estilos para el modal de confirmación */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
}

.modal-confirmacion {
    background: white;
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px) scale(0.9);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px 8px 0 0;
    text-align: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
}

.modal-body {
    padding: 25px;
}

.confirmacion-item {
    margin-bottom: 15px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 4px solid #667eea;
}

.confirmacion-label {
    font-weight: 600;
    color: #495057;
    display: block;
    margin-bottom: 5px;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.confirmacion-valor {
    color: #2c3e50;
    font-size: 1rem;
    line-height: 1.4;
}

.confirmacion-mensaje {
    text-align: center;
    margin: 20px 0;
    padding: 15px;
    background: #e3f2fd;
    border-radius: 6px;
    border: 1px solid #bbdefb;
    color: #1565c0;
    font-weight: 500;
}

.modal-footer {
    padding: 20px 25px 25px;
    text-align: center;
    border-top: 1px solid #e9ecef;
}

.modal-btn {
    padding: 12px 25px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin: 0 10px;
    min-width: 120px;
}

.modal-btn-cancelar {
    background: #6c757d;
    color: white;
}

.modal-btn-cancelar:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

.modal-btn-confirmar {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
}

.modal-btn-confirmar:hover {
    background: linear-gradient(135deg, #218838 0%, #1ea085 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
}

.modal-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

/* Responsive */
@media (max-width: 768px) {
    .modal-confirmacion {
        width: 95%;
        margin: 20px;
    }
    
    .modal-footer {
        padding: 15px 20px 20px;
    }
    
    .modal-btn {
        display: block;
        width: 100%;
        margin: 10px 0;
    }
}
</style>

<!-- Modal de Confirmación -->
<div class="modal-overlay" id="modal-confirmacion">
    <div class="modal-confirmacion">
        <div class="modal-header">
            <h3>📋 Confirmar Certificado</h3>
        </div>
        <div class="modal-body">
            <div class="confirmacion-mensaje">
                <strong>Por favor, revisa la información antes de generar el certificado:</strong>
            </div>
            
            <div class="confirmacion-item">
                <span class="confirmacion-label">Nombre Completo</span>
                <div class="confirmacion-valor" id="confirm-nombre"></div>
            </div>
            
            <div class="confirmacion-item">
                <span class="confirmacion-label">Fecha del Evento</span>
                <div class="confirmacion-valor" id="confirm-fecha"></div>
            </div>
            
            <div class="confirmacion-item">
                <span class="confirmacion-label">Tipo de Actividad</span>
                <div class="confirmacion-valor" id="confirm-actividad"></div>
            </div>
            
            <div class="confirmacion-item" id="confirm-observaciones-container" style="display: none;">
                <span class="confirmacion-label">Observaciones</span>
                <div class="confirmacion-valor" id="confirm-observaciones"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-cancelar" id="btn-cancelar">
                ❌ Cancelar
            </button>
            <button type="button" class="modal-btn modal-btn-confirmar" id="btn-confirmar">
                ✅ Confirmar y Generar
            </button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Mapeo de tipos de actividad
    const tiposActividad = {
        'curso': 'Curso de Capacitación',
        'taller': 'Taller Práctico',
        'seminario': 'Seminario',
        'conferencia': 'Conferencia',
        'workshop': 'Workshop',
        'otro': 'Otro'
    };
    
    // Función para formatear fecha
    function formatearFecha(fecha) {
        if (!fecha) return 'No especificada';
        const fechaObj = new Date(fecha);
        return fechaObj.toLocaleDateString('es-ES', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
    
    // Función para mostrar el modal
    function mostrarModal() {
        // Obtener valores del formulario
        const nombre = $('#nombre').val().trim();
        const fecha = $('#fecha_evento').val();
        const tipoActividad = $('#tipo_actividad').val();
        const observaciones = $('#observaciones').val().trim();
        
        // Validar campos obligatorios
        if (!nombre || !fecha || !tipoActividad) {
            alert('Por favor, completa todos los campos obligatorios antes de continuar.');
            return false;
        }
        
        // Validar fecha futura
        const fechaActual = new Date().toISOString().split('T')[0];
        if (fecha > fechaActual) {
            alert('La fecha del evento no puede ser futura.');
            return false;
        }
        
        // Llenar información en el modal
        $('#confirm-nombre').text(nombre);
        $('#confirm-fecha').text(formatearFecha(fecha));
        $('#confirm-actividad').text(tiposActividad[tipoActividad] || tipoActividad);
        
        // Mostrar observaciones solo si hay contenido
        if (observaciones) {
            $('#confirm-observaciones').text(observaciones);
            $('#confirm-observaciones-container').show();
        } else {
            $('#confirm-observaciones-container').hide();
        }
        
        // Mostrar modal
        $('#modal-confirmacion').fadeIn(300).css('display', 'flex');
        
        return true;
    }
    
    // Función para ocultar el modal
    function ocultarModal() {
        $('#modal-confirmacion').fadeOut(300);
    }
    
    // Evento para el botón de confirmar certificado
    $('#btn-confirmar-certificado').on('click', function(e) {
        e.preventDefault();
        mostrarModal();
    });
    
    // Evento para cancelar
    $('#btn-cancelar').on('click', function() {
        ocultarModal();
    });
    
    // Evento para confirmar y enviar
    $('#btn-confirmar').on('click', function() {
        // Deshabilitar botón para evitar doble envío
        $(this).prop('disabled', true).text('⏳ Generando...');
        
        // Agregar campo hidden para indicar que es una confirmación
        if (!$('#solicitar_certificado').length) {
            $('<input>').attr({
                type: 'hidden',
                name: 'solicitar_certificado',
                value: '1'
            }).appendTo('#formulario-certificado');
        }
        
        // Enviar formulario
        $('#formulario-certificado').submit();
    });
    
    // Cerrar modal al hacer clic fuera de él
    $('#modal-confirmacion').on('click', function(e) {
        if (e.target === this) {
            ocultarModal();
        }
    });
    
    // Cerrar modal con ESC
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#modal-confirmacion').is(':visible')) {
            ocultarModal();
        }
    });
    
    // Validación en tiempo real
    $('#nombre, #fecha_evento, #tipo_actividad').on('input change', function() {
        const nombre = $('#nombre').val().trim();
        const fecha = $('#fecha_evento').val();
        const tipoActividad = $('#tipo_actividad').val();
        
        if (nombre && fecha && tipoActividad) {
            $('#btn-confirmar-certificado').prop('disabled', false);
        } else {
            $('#btn-confirmar-certificado').prop('disabled', true);
        }
    });
    
    // Inicializar estado del botón
    $('#btn-confirmar-certificado').prop('disabled', true);
});
</script> 