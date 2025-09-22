<?php
/**
 * Formulario de Edición de Certificado - Solo para Administradores
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

// Verificar si tenemos un certificado para editar
if (!$certificado_edicion) {
    wp_die(__('Certificado no encontrado.', 'certificados-personalizados'));
}

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
    <h1><?php _e('Editar Certificado', 'certificados-personalizados'); ?></h1>
    
    <!-- Navegación -->
    <p>
        <a href="<?php echo admin_url('admin.php?page=editar-certificado'); ?>" class="button button-secondary">
            ← <?php _e('Seleccionar Otro Certificado', 'certificados-personalizados'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=aprobacion-certificados'); ?>" class="button button-secondary">
            ← <?php _e('Ver Lista de Certificados', 'certificados-personalizados'); ?>
        </a>
    </p>
    
    <!-- Información del Certificado -->
    <div class="info-certificado" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
        <h3><?php _e('Información del Certificado', 'certificados-personalizados'); ?></h3>
        <p>
            <strong><?php _e('Código:', 'certificados-personalizados'); ?></strong> <?php echo esc_html($certificado_edicion->codigo_unico); ?> | 
            <strong><?php _e('Estado:', 'certificados-personalizados'); ?></strong> <?php echo ucfirst($certificado_edicion->estado); ?> | 
            <strong><?php _e('Creado:', 'certificados-personalizados'); ?></strong> <?php echo date('d/m/Y H:i', strtotime($certificado_edicion->created_at)); ?>
        </p>
        <?php if (!empty($certificado_edicion->updated_at) && $certificado_edicion->updated_at !== $certificado_edicion->created_at): ?>
            <p><strong><?php _e('Última actualización:', 'certificados-personalizados'); ?></strong> <?php echo date('d/m/Y H:i', strtotime($certificado_edicion->updated_at)); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Formulario de Edición -->
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="formulario-edicion-admin">
        <?php wp_nonce_field('editar_certificado_admin', 'editar_certificado_admin_nonce'); ?>
        <input type="hidden" name="action" value="editar_certificado_admin">
        <input type="hidden" name="certificado_id" value="<?php echo $certificado_edicion->id; ?>">
        
        <table class="form-table">
            <!-- Información de la Instalación -->
            <tr>
                <th scope="row">
                    <label for="nombre_instalacion"><?php _e('Nombre del Lugar/Instalación', 'certificados-personalizados'); ?> *</label>
                </th>
                <td>
                    <input type="text" id="nombre_instalacion" name="nombre_instalacion" class="regular-text" 
                           value="<?php echo esc_attr($certificado_edicion->nombre_instalacion); ?>" required>
                    <p class="description"><?php _e('Nombre oficial de la instalación o lugar.', 'certificados-personalizados'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="direccion_instalacion"><?php _e('Dirección del Lugar', 'certificados-personalizados'); ?> *</label>
                </th>
                <td>
                    <textarea id="direccion_instalacion" name="direccion_instalacion" rows="3" cols="50" required><?php echo esc_textarea($certificado_edicion->direccion_instalacion); ?></textarea>
                    <p class="description"><?php _e('Dirección completa de la instalación.', 'certificados-personalizados'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="razon_social"><?php _e('Razón Social', 'certificados-personalizados'); ?> *</label>
                </th>
                <td>
                    <input type="text" id="razon_social" name="razon_social" class="regular-text" 
                           value="<?php echo esc_attr($certificado_edicion->razon_social); ?>" required>
                    <p class="description"><?php _e('Razón social de la empresa.', 'certificados-personalizados'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="nit"><?php _e('NIT', 'certificados-personalizados'); ?> *</label>
                </th>
                <td>
                    <input type="text" id="nit" name="nit" class="regular-text" 
                           value="<?php echo esc_attr($certificado_edicion->nit); ?>" required>
                    <p class="description"><?php _e('Número de identificación tributaria.', 'certificados-personalizados'); ?></p>
                </td>
            </tr>
            
            <!-- Información del Certificado -->
            <tr>
                <th scope="row">
                    <label for="tipo_certificado"><?php _e('Tipo de Certificado', 'certificados-personalizados'); ?> *</label>
                </th>
                <td>
                    <select id="tipo_certificado" name="tipo_certificado" required>
                        <option value=""><?php _e('Seleccionar tipo...', 'certificados-personalizados'); ?></option>
                        <?php foreach (obtener_tipos_certificado_admin() as $valor => $texto): ?>
                            <option value="<?php echo esc_attr($valor); ?>" <?php selected($certificado_edicion->tipo_certificado, $valor); ?>>
                                <?php echo esc_html($texto); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Tipo de certificado de GLP.', 'certificados-personalizados'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="numero_certificado"><?php _e('Número del Certificado', 'certificados-personalizados'); ?> *</label>
                </th>
                <td>
                    <input type="number" id="numero_certificado" name="numero_certificado" class="small-text" 
                           value="<?php echo esc_attr($certificado_edicion->numero_certificado); ?>" min="1" required>
                    <p class="description"><?php _e('Número secuencial del certificado.', 'certificados-personalizados'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="fecha_aprobacion"><?php _e('Fecha de Aprobación del Certificado', 'certificados-personalizados'); ?> *</label>
                </th>
                <td>
                    <input type="date" id="fecha_aprobacion" name="fecha_aprobacion" 
                           value="<?php echo esc_attr($certificado_edicion->fecha_aprobacion); ?>" required>
                    <p class="description"><?php _e('Fecha en que fue aprobado el certificado.', 'certificados-personalizados'); ?></p>
                </td>
            </tr>
            
            <!-- Información Técnica -->
            <tr>
                <th scope="row">
                    <label for="capacidad_almacenamiento"><?php _e('Capacidad de Almacenamiento', 'certificados-personalizados'); ?> *</label>
                </th>
                <td>
                    <input type="number" id="capacidad_almacenamiento" name="capacidad_almacenamiento" class="small-text" 
                           value="<?php echo esc_attr($certificado_edicion->capacidad_almacenamiento); ?>" min="0" step="0.01" required>
                    <span>galones</span>
                    <p class="description"><?php _e('Capacidad total de almacenamiento en galones.', 'certificados-personalizados'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="numero_tanques"><?php _e('Número de Tanques', 'certificados-personalizados'); ?> *</label>
                </th>
                <td>
                    <input type="number" id="numero_tanques" name="numero_tanques" class="small-text" 
                           value="<?php echo esc_attr($certificado_edicion->numero_tanques); ?>" min="1" required>
                    <p class="description"><?php _e('Cantidad de tanques de almacenamiento.', 'certificados-personalizados'); ?></p>
                </td>
            </tr>
            
            <!-- Estado del Certificado -->
            <tr>
                <th scope="row">
                    <label for="estado"><?php _e('Estado del Certificado', 'certificados-personalizados'); ?> *</label>
                </th>
                <td>
                    <select id="estado" name="estado" required>
                        <option value="pendiente" <?php selected($certificado_edicion->estado, 'pendiente'); ?>><?php _e('Pendiente', 'certificados-personalizados'); ?></option>
                        <option value="aprobado" <?php selected($certificado_edicion->estado, 'aprobado'); ?>><?php _e('Aprobado', 'certificados-personalizados'); ?></option>
                        <option value="rechazado" <?php selected($certificado_edicion->estado, 'rechazado'); ?>><?php _e('Rechazado', 'certificados-personalizados'); ?></option>
                    </select>
                    <p class="description"><?php _e('Estado actual del certificado.', 'certificados-personalizados'); ?></p>
                </td>
            </tr>
        </table>
        
        <!-- Botones de Acción -->
        <div class="botones-accion" style="margin: 20px 0;">
            <?php submit_button(__('Guardar Cambios', 'certificados-personalizados'), 'primary', 'guardar_cambios', false); ?>
            
            <a href="<?php echo admin_url('admin.php?page=editar-certificado'); ?>" class="button button-secondary">
                <?php _e('Cancelar', 'certificados-personalizados'); ?>
            </a>
            
            <?php if (!empty($certificado_edicion->pdf_path)): ?>
                <a href="<?php echo esc_url($certificado_edicion->pdf_path); ?>" target="_blank" class="button button-secondary">
                    📄 <?php _e('Ver PDF Actual', 'certificados-personalizados'); ?>
                </a>
            <?php endif; ?>
            
            <button type="submit" name="regenerar_pdf" value="1" class="button button-secondary" 
                    onclick="return confirm('¿Regenerar el PDF con los nuevos datos?')">
                🔄 <?php _e('Regenerar PDF', 'certificados-personalizados'); ?>
            </button>
        </div>
    </form>
</div>

<style>
.info-certificado h3 {
    margin-top: 0;
    color: #0073aa;
}

.form-table th {
    width: 200px;
    font-weight: bold;
}

.form-table td {
    padding: 15px 10px;
}

.botones-accion {
    border-top: 1px solid #ddd;
    padding-top: 20px;
}

.botones-accion .button {
    margin-right: 10px;
}

#formulario-edicion-admin {
    background: white;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin: 20px 0;
}

.small-text {
    width: 80px;
}

.regular-text {
    width: 300px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Validación del formulario
    $('#formulario-edicion-admin').on('submit', function(e) {
        var fecha = $('#fecha_aprobacion').val();
        var hoy = new Date().toISOString().split('T')[0];
        
        if (fecha > hoy) {
            alert('La fecha de aprobación no puede ser futura.');
            e.preventDefault();
            return false;
        }
    });
    
    // Actualizar actividad cuando cambie el tipo de certificado
    $('#tipo_certificado').on('change', function() {
        // Si necesitas sincronizar con otro campo, puedes hacerlo aquí
    });
});
</script>
