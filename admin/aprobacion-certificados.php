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

// Verificar si estamos en modo edición
$modo_edicion = false;
$certificado_edicion = null;

if (isset($_GET['editar']) && !empty($_GET['editar'])) {
    $certificado_id = intval($_GET['editar']);
    $certificado_edicion = CertificadosAntecoreBD::obtener_certificado_para_edicion_admin($certificado_id);
    
    if ($certificado_edicion) {
        $modo_edicion = true;
    }
}

// Process redirect messages (from approve/reject actions)
if (isset($_GET['mensaje']) && isset($_GET['texto'])) {
    $mensaje = array(
        'tipo' => sanitize_text_field($_GET['mensaje']),
        'mensaje' => sanitize_text_field($_GET['texto'])
    );
}

// Get pending certificates
$certificados_pendientes = CertificadosAntecoreBD::obtener_todos_certificados('pendiente');

// Get filter parameter
$estado_filtro = isset($_GET['estado']) ? sanitize_text_field($_GET['estado']) : 'pendiente';

// Get certificates based on filter
$certificados = CertificadosAntecoreBD::obtener_todos_certificados($estado_filtro);

// Get statistics
$estadisticas = CertificadosAntecoreBD::obtener_estadisticas();

/**
 * Obtener tipos de actividad para mostrar
 */
function obtener_tipos_certificado_admin() {
    return array(
        'PAGLP' => 'PAGLP - Planta de Almacenamiento de GLP',
        'TEGLP' => 'TEGLP - Tanque de Almacenamiento de GLP',
        'PEGLP' => 'PEGLP - Planta de Envasado de GLP',
        'DEGLP' => 'DEGLP - Distribuidora de GLP',
        'PVGLP' => 'PVGLP - Punto de Venta de GLP'
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
    
    <!-- Filtros -->
    <div class="filtros-certificados">
        <h2><?php _e('Filtrar Certificados', 'certificados-personalizados'); ?></h2>
        <form method="get" action="">
            <input type="hidden" name="page" value="aprobacion-certificados">
            <select name="estado" onchange="this.form.submit()">
                <option value="pendiente" <?php selected($estado_filtro, 'pendiente'); ?>>
                    <?php _e('Pendientes de Aprobación', 'certificados-personalizados'); ?>
                </option>
                <option value="aprobado" <?php selected($estado_filtro, 'aprobado'); ?>>
                    <?php _e('Aprobados', 'certificados-personalizados'); ?>
                </option>
                <option value="rechazado" <?php selected($estado_filtro, 'rechazado'); ?>>
                    <?php _e('Rechazados', 'certificados-personalizados'); ?>
                </option>
                <option value="" <?php selected($estado_filtro, ''); ?>>
                    <?php _e('Todos los Certificados', 'certificados-personalizados'); ?>
                </option>
            </select>
            <input type="submit" class="button" value="<?php _e('Filtrar', 'certificados-personalizados'); ?>">
        </form>
    </div>
    
    <!-- Lista de certificados pendientes -->
    <div class="certificados-pendientes">
        <h2>
            <?php 
            switch($estado_filtro) {
                case 'pendiente':
                    _e('Certificados Pendientes de Aprobación', 'certificados-personalizados');
                    break;
                case 'aprobado':
                    _e('Certificados Aprobados', 'certificados-personalizados');
                    break;
                case 'rechazado':
                    _e('Certificados Rechazados', 'certificados-personalizados');
                    break;
                default:
                    _e('Todos los Certificados', 'certificados-personalizados');
                    break;
            }
            ?>
        </h2>
        
        <?php if (empty($certificados)): ?>
            <div class="notice notice-info">
                <p><?php _e('No hay certificados que mostrar con el filtro seleccionado.', 'certificados-personalizados'); ?></p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Código', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Tipo Certificado', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Número', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Instalación', 'certificados-personalizados'); ?></th>
                        <th><?php _e('Empresa', 'certificados-personalizados'); ?></th>
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
                            <td>
                                <strong><?php echo esc_html($certificado->codigo_unico); ?></strong>
                            </td>
                            <td>
                                <?php 
                                $tipos = obtener_tipos_certificado_admin();
                                $tipo_mostrar = isset($tipos[$certificado->tipo_certificado]) ? $tipos[$certificado->tipo_certificado] : $certificado->tipo_certificado;
                                echo esc_html($tipo_mostrar);
                                ?>
                            </td>
                            <td>
                                <strong><?php echo esc_html($certificado->tipo_certificado . '-' . str_pad($certificado->numero_certificado, 2, '0', STR_PAD_LEFT)); ?></strong>
                            </td>
                            <td>
                                <strong><?php echo esc_html($certificado->nombre_instalacion); ?></strong><br>
                                <small><?php echo esc_html($certificado->direccion_instalacion); ?></small>
                            </td>
                            <td>
                                <strong><?php echo esc_html($certificado->razon_social); ?></strong><br>
                                <small>NIT: <?php echo esc_html($certificado->nit); ?></small>
                            </td>
                            <td>
                                <?php echo esc_html($certificado->capacidad_almacenamiento); ?> galones
                            </td>
                            <td>
                                <?php echo esc_html($certificado->numero_tanques); ?>
                            </td>
                            <td>
                                <?php 
                                $estados = array(
                                    'pendiente' => __('Pendiente', 'certificados-personalizados'),
                                    'aprobado' => __('Aprobado', 'certificados-personalizados'),
                                    'rechazado' => __('Rechazado', 'certificados-personalizados')
                                );
                                echo esc_html($estados[$certificado->estado] ?? 'Desconocido');
                                ?>
                            </td>
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
                            <td>
                                <?php 
                                // Verificar si existe el PDF, si no, intentar generarlo automáticamente
                                if (!CertificadosAntecorePDF::existe_pdf($certificado->id)) {
                                    // Intentar generar el PDF automáticamente
                                    $pdf_generado = CertificadosAntecorePDF::generar_certificado_pdf($certificado->id);
                                    if ($pdf_generado) {
                                        error_log('CertificadosPersonalizados: PDF generado automáticamente para certificado ID: ' . $certificado->id);
                                    } else {
                                        error_log('CertificadosPersonalizados: Error al generar PDF automáticamente para certificado ID: ' . $certificado->id);
                                    }
                                }
                                
                                // Verificar nuevamente si existe el PDF
                                if (CertificadosAntecorePDF::existe_pdf($certificado->id)): 
                                ?>
                                    <a href="<?php echo esc_url(CertificadosAntecorePDF::obtener_url_pdf_admin_forzada($certificado->id)); ?>" 
                                       target="_blank" class="button button-small">
                                         <?php _e('📄 Ver PDF', 'certificados-personalizados'); ?>
                                     </a>
                                 <?php else: ?>
                                     <span class="sin-observaciones">
                                         <?php _e('No disponible', 'certificados-personalizados'); ?>
                                     </span>
                                     <?php if (current_user_can('manage_options')): ?>
                                         <br>
                                         <small style="color: #999;">
                                             <?php _e('(Ruta: ', 'certificados-personalizados'); ?>
                                             <?php echo esc_html($certificado->pdf_path ?: 'Vacía'); ?>
                                             <?php _e(')', 'certificados-personalizados'); ?>
                                         </small>
                                     <?php endif; ?>
                                 <?php endif; ?>
                             </td>
                             <td><?php echo esc_html(date('d/m/Y H:i', strtotime($certificado->created_at))); ?></td>
                             <td>
                                 <?php if ($certificado->estado === 'pendiente'): ?>
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
                                 <?php else: ?>
                                     <span class="estado-finalizado" style="color: #666; font-style: italic;">
                                         <?php _e('Sin acciones disponibles', 'certificados-personalizados'); ?>
                                     </span>
                                 <?php endif; ?>
                                 
                                 <!-- Botón de Editar para Administradores -->
                                 <br><br>
                                 <a href="<?php echo admin_url('admin.php?page=editar-certificado&editar=' . $certificado->id); ?>" 
                                    class="button button-secondary">
                                     ✏️ <?php _e('Editar', 'certificados-personalizados'); ?>
                                 </a>
                             </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- El formulario de edición ahora está en su propio menú -->
    <?php if (false && $modo_edicion && $certificado_edicion): ?>
        <div class="formulario-edicion-admin">
            <h2><?php _e('Editar Certificado', 'certificados-personalizados'); ?></h2>
            <p><a href="<?php echo admin_url('admin.php?page=aprobacion-certificados'); ?>" class="button button-secondary">
                ← <?php _e('Volver a Lista', 'certificados-personalizados'); ?>
            </a></p>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="formulario-edicion-admin">
                <?php wp_nonce_field('editar_certificado_admin', 'editar_certificado_admin_nonce'); ?>
                <input type="hidden" name="action" value="editar_certificado_admin">
                <input type="hidden" name="certificado_id" value="<?php echo $certificado_edicion->id; ?>">
                
                <table class="form-table">
                    <!-- Información de la Instalación -->
                    <tr>
                        <th scope="row">
                            <label for="nombre_instalacion"><?php _e('Nombre del Lugar/Instalación', 'certificados-personalizados'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="nombre_instalacion" name="nombre_instalacion" class="regular-text" 
                                   value="<?php echo esc_attr($certificado_edicion->nombre_instalacion); ?>" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="direccion_instalacion"><?php _e('Dirección del Lugar', 'certificados-personalizados'); ?></label>
                        </th>
                        <td>
                            <textarea id="direccion_instalacion" name="direccion_instalacion" rows="3" cols="50" class="large-text" required><?php echo esc_textarea($certificado_edicion->direccion_instalacion); ?></textarea>
                        </td>
                    </tr>
                    
                    <!-- Información de la Empresa -->
                    <tr>
                        <th scope="row">
                            <label for="razon_social"><?php _e('Razón Social', 'certificados-personalizados'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="razon_social" name="razon_social" class="regular-text" 
                                   value="<?php echo esc_attr($certificado_edicion->razon_social); ?>" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="nit"><?php _e('NIT', 'certificados-personalizados'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="nit" name="nit" class="regular-text" 
                                   value="<?php echo esc_attr($certificado_edicion->nit); ?>" required>
                        </td>
                    </tr>
                    
                    <!-- Información del Certificado -->
                    <tr>
                        <th scope="row">
                            <label for="tipo_certificado"><?php _e('Tipo de Certificado', 'certificados-personalizados'); ?></label>
                        </th>
                        <td>
                            <select id="tipo_certificado" name="tipo_certificado" required>
                                <?php 
                                $tipos = obtener_tipos_certificado_admin();
                                foreach ($tipos as $valor => $etiqueta): 
                                ?>
                                    <option value="<?php echo esc_attr($valor); ?>" 
                                            <?php selected($certificado_edicion->tipo_certificado, $valor); ?>>
                                        <?php echo esc_html($etiqueta); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="numero_certificado"><?php _e('Número del Certificado', 'certificados-personalizados'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="numero_certificado" name="numero_certificado" class="small-text" 
                                   value="<?php echo esc_attr($certificado_edicion->numero_certificado); ?>" min="1" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="fecha_aprobacion"><?php _e('Fecha de Aprobación del Certificado', 'certificados-personalizados'); ?></label>
                        </th>
                        <td>
                            <input type="date" id="fecha_aprobacion" name="fecha_aprobacion" 
                                   value="<?php echo esc_attr($certificado_edicion->fecha_aprobacion); ?>" required>
                        </td>
                    </tr>
                    
                    <!-- Información Técnica -->
                    <tr>
                        <th scope="row">
                            <label for="capacidad_almacenamiento"><?php _e('Capacidad de Almacenamiento', 'certificados-personalizados'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="capacidad_almacenamiento" name="capacidad_almacenamiento" class="regular-text" 
                                   value="<?php echo esc_attr($certificado_edicion->capacidad_almacenamiento); ?>" min="1" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="numero_tanques"><?php _e('Número de Tanques', 'certificados-personalizados'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="numero_tanques" name="numero_tanques" class="small-text" 
                                   value="<?php echo esc_attr($certificado_edicion->numero_tanques); ?>" min="1" required>
                        </td>
                    </tr>
                    
                    
                    <tr>
                        <th scope="row">
                            <label for="estado"><?php _e('Estado', 'certificados-personalizados'); ?></label>
                        </th>
                        <td>
                            <select id="estado" name="estado" required>
                                <option value="pendiente" <?php selected($certificado_edicion->estado, 'pendiente'); ?>>
                                    <?php _e('Pendiente', 'certificados-personalizados'); ?>
                                </option>
                                <option value="aprobado" <?php selected($certificado_edicion->estado, 'aprobado'); ?>>
                                    <?php _e('Aprobado', 'certificados-personalizados'); ?>
                                </option>
                                <option value="rechazado" <?php selected($certificado_edicion->estado, 'rechazado'); ?>>
                                    <?php _e('Rechazado', 'certificados-personalizados'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php _e('Actualizar Certificado', 'certificados-personalizados'); ?>
                    </button>
                </p>
            </form>
        </div>
    <?php endif; ?>
</div>

<style>
.estadisticas-certificados {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 30px;
}

.filtros-certificados {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 20px;
}

.filtros-certificados select {
    margin-right: 10px;
    min-width: 200px;
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

.formulario-edicion-admin {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-top: 20px;
}

.formulario-edicion-admin h2 {
    margin-top: 0;
    color: #23282d;
}

.formulario-edicion-admin .form-table th {
    width: 200px;
    padding: 15px 10px 15px 0;
}

.formulario-edicion-admin .form-table td {
    padding: 15px 10px;
}

.formulario-edicion-admin input[type="text"],
.formulario-edicion-admin input[type="date"],
.formulario-edicion-admin select,
.formulario-edicion-admin textarea {
    width: 100%;
    max-width: 400px;
}

.formulario-edicion-admin .submit {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}
</style> 