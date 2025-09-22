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

// Process Excel upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['procesar_excel_masivo'])) {
    $mensaje = procesar_excel_masivo();
}

// Process redirect messages (from approval submission)
if (isset($_GET['mensaje']) && isset($_GET['texto'])) {
    $mensaje = array(
        'tipo' => sanitize_text_field($_GET['mensaje']),
        'mensaje' => sanitize_text_field($_GET['texto'])
    );
}

// Verificar si estamos en modo edición
$modo_edicion = false;
$certificado_edicion = null;

if (isset($_GET['editar']) && !empty($_GET['editar'])) {
    $certificado_id = intval($_GET['editar']);
    $certificado_edicion = CertificadosAntecoreBD::obtener_certificado_para_edicion($certificado_id);
    
    if ($certificado_edicion) {
        $modo_edicion = true;
        
        // Debug: Mostrar información del certificado (solo para desarrollo)
        if (current_user_can('manage_options') && isset($_GET['debug'])) {
            echo '<div class="notice notice-info">';
            echo '<p><strong>Debug - Certificado ID:</strong> ' . $certificado_edicion->id . '</p>';
            echo '<p><strong>Nombre Instalación:</strong> ' . esc_html($certificado_edicion->nombre_instalacion) . '</p>';
            echo '<p><strong>Actividad:</strong> ' . esc_html($certificado_edicion->actividad) . '</p>';
            echo '<p><strong>Fecha Aprobación:</strong> ' . esc_html($certificado_edicion->fecha_aprobacion) . '</p>';
            echo '<p><strong>Tipo Certificado:</strong> ' . esc_html($certificado_edicion->tipo_certificado) . '</p>';
            echo '<p><strong>Updated At:</strong> ' . esc_html($certificado_edicion->updated_at) . '</p>';
            echo '</div>';
        }
    }
}

// Get current user's certificates
$certificados = CertificadosAntecoreBD::obtener_certificados_usuario();

/**
 * Procesar Excel masivo
 */
function procesar_excel_masivo() {
    // Verificar nonce
    if (!wp_verify_nonce($_POST['excel_masivo_nonce'], 'procesar_excel_masivo')) {
        return array('tipo' => 'error', 'mensaje' => 'Error de seguridad.');
    }
    
    // Verificar que se subió un archivo
    if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) {
        return array('tipo' => 'error', 'mensaje' => 'Error al subir el archivo.');
    }
    
    // Validar archivo
    $errores_validacion = CertificadosAntecoreExcel::validar_archivo_subido($_FILES['archivo_excel']);
    
    if (!empty($errores_validacion)) {
        return array('tipo' => 'error', 'mensaje' => implode('; ', $errores_validacion));
    }
    
    // Procesar archivo
    $user_id = get_current_user_id();
    $resultados = CertificadosAntecoreExcel::procesar_archivo_excel($_FILES['archivo_excel']['tmp_name'], $user_id);
    
    // Limpiar archivo temporal
    CertificadosAntecoreExcel::limpiar_archivo_temporal($_FILES['archivo_excel']['tmp_name']);
    
    // Preparar mensaje de resultado
    $mensaje_tipo = 'info';
    $mensaje_texto = sprintf(
        'Procesamiento completado. Total filas: %d, Exitosos: %d, Errores: %d',
        $resultados['total_filas'],
        $resultados['exitosos'],
        count($resultados['errores'])
    );
    
    if ($resultados['exitosos'] > 0) {
        $mensaje_tipo = 'exito';
    }
    
    if (!empty($resultados['errores'])) {
        $mensaje_texto .= '. Errores: ' . implode('; ', array_slice($resultados['errores'], 0, 3));
        if (count($resultados['errores']) > 3) {
            $mensaje_texto .= '... (y ' . (count($resultados['errores']) - 3) . ' más)';
        }
    }
    
    return array('tipo' => $mensaje_tipo, 'mensaje' => $mensaje_texto);
}

/**
 * Procesar solicitud de certificado
 */
function procesar_solicitud_certificado() {
    // Verificar nonce
    if (!wp_verify_nonce($_POST['certificado_nonce'], 'solicitar_certificado')) {
        return array('tipo' => 'error', 'mensaje' => 'Error de seguridad.');
    }
    
    // Validar nuevos campos
    $capacidad_almacenamiento = sanitize_text_field($_POST['capacidad_almacenamiento']);
    $numero_tanques = intval($_POST['numero_tanques']);
    $nombre_instalacion = sanitize_text_field($_POST['nombre_instalacion']);
    $direccion_instalacion = sanitize_textarea_field($_POST['direccion_instalacion']);
    $razon_social = sanitize_text_field($_POST['razon_social']);
    $nit = sanitize_text_field($_POST['nit']);
    $tipo_certificado = sanitize_text_field($_POST['tipo_certificado']);
    $numero_certificado = intval($_POST['numero_certificado']);
    $fecha_aprobacion = sanitize_text_field($_POST['fecha_aprobacion']);
    
    // Validaciones obligatorias
    $campos_obligatorios = array(
        'capacidad_almacenamiento' => $capacidad_almacenamiento,
        'numero_tanques' => $numero_tanques,
        'nombre_instalacion' => $nombre_instalacion,
        'direccion_instalacion' => $direccion_instalacion,
        'razon_social' => $razon_social,
        'nit' => $nit,
        'tipo_certificado' => $tipo_certificado,
        'numero_certificado' => $numero_certificado,
        'fecha_aprobacion' => $fecha_aprobacion
    );
    
    foreach ($campos_obligatorios as $campo => $valor) {
        if (empty($valor)) {
            return array('tipo' => 'error', 'mensaje' => 'El campo ' . ucfirst(str_replace('_', ' ', $campo)) . ' es obligatorio.');
        }
    }
    
    // Validar fecha de aprobación
    $fecha_actual = date('Y-m-d');
    if ($fecha_aprobacion > $fecha_actual) {
        return array('tipo' => 'error', 'mensaje' => 'La fecha de aprobación no puede ser futura.');
    }
    
    // Validar tipo de certificado
    $tipos_validos = array('PAGLP', 'TEGLP', 'PEGLP', 'DEGLP', 'PVGLP');
    if (!in_array($tipo_certificado, $tipos_validos)) {
        return array('tipo' => 'error', 'mensaje' => 'Tipo de certificado no válido.');
    }
    
    // Validar que el número de tanques sea positivo
    if ($numero_tanques <= 0) {
        return array('tipo' => 'error', 'mensaje' => 'El número de tanques debe ser mayor a 0.');
    }
    
    // Validar que el número de certificado sea positivo
    if ($numero_certificado <= 0) {
        return array('tipo' => 'error', 'mensaje' => 'El número de certificado debe ser mayor a 0.');
    }
    
    // Crear certificado
    $datos = array(
        'actividad' => $tipo_certificado, // Usamos tipo_certificado como actividad
        'capacidad_almacenamiento' => $capacidad_almacenamiento,
        'numero_tanques' => $numero_tanques,
        'nombre_instalacion' => $nombre_instalacion,
        'direccion_instalacion' => $direccion_instalacion,
        'razon_social' => $razon_social,
        'nit' => $nit,
        'tipo_certificado' => $tipo_certificado,
        'numero_certificado' => $numero_certificado,
        'fecha_aprobacion' => $fecha_aprobacion
    );
    
    $certificado_id = CertificadosAntecoreBD::crear_certificado($datos);
    
    if ($certificado_id) {
        // Generar PDF automáticamente
        $pdf_generado = CertificadosAntecorePDF::generar_certificado_pdf($certificado_id);
        
        // Enviar notificaciones a los emails configurados
        enviar_notificaciones_nueva_solicitud($certificado_id);
        
        $certificado = CertificadosAntecoreBD::obtener_certificado($certificado_id);
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
function obtener_tipos_certificado() {
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
        <!-- Pestañas de navegación -->
        <div class="nav-tab-wrapper">
            <a href="#certificado-individual" class="nav-tab nav-tab-active" data-tab="certificado-individual">
                <?php _e('Certificado Individual', 'certificados-personalizados'); ?>
            </a>
            <a href="#carga-masiva" class="nav-tab" data-tab="carga-masiva">
                <?php _e('Carga Masiva (Excel)', 'certificados-personalizados'); ?>
            </a>
        </div>
        
        <!-- Contenido de pestaña: Certificado Individual -->
        <div id="certificado-individual" class="tab-content">
            <!-- Formulario de solicitud -->
            <div class="certificado-formulario">
            <h2>
                <?php if ($modo_edicion): ?>
                    <?php _e('Editar Certificado', 'certificados-personalizados'); ?>
                    <a href="<?php echo admin_url('admin.php?page=mis-certificados'); ?>" class="button button-secondary" style="float: right;">
                        ← <?php _e('Volver a Lista', 'certificados-personalizados'); ?>
                    </a>
                <?php else: ?>
                    <?php _e('Solicitar Nuevo Certificado', 'certificados-personalizados'); ?>
                <?php endif; ?>
            </h2>
            
            <form method="post" action="<?php echo $modo_edicion ? admin_url('admin-post.php') : ''; ?>" id="formulario-certificado">
                <?php if ($modo_edicion): ?>
                    <?php wp_nonce_field('editar_certificado', 'editar_certificado_nonce'); ?>
                    <input type="hidden" name="action" value="editar_certificado">
                    <input type="hidden" name="certificado_id" value="<?php echo $certificado_edicion->id; ?>">
                <?php else: ?>
                    <?php wp_nonce_field('solicitar_certificado', 'certificado_nonce'); ?>
                <?php endif; ?>
                
                <table class="form-table">
                    <!-- Información de la Instalación -->
                    <tr>
                        <th scope="row">
                            <label for="nombre_instalacion"><?php _e('Nombre del Lugar/Instalación', 'certificados-personalizados'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" id="nombre_instalacion" name="nombre_instalacion" class="regular-text" 
                                   value="<?php echo $modo_edicion ? esc_attr($certificado_edicion->nombre_instalacion) : ''; ?>" required>
                            <p class="description"><?php _e('Nombre oficial de la instalación o lugar.', 'certificados-personalizados'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="direccion_instalacion"><?php _e('Dirección del Lugar', 'certificados-personalizados'); ?> *</label>
                        </th>
                        <td>
                            <textarea id="direccion_instalacion" name="direccion_instalacion" rows="3" cols="50" class="large-text" required><?php echo $modo_edicion ? esc_textarea($certificado_edicion->direccion_instalacion) : ''; ?></textarea>
                            <p class="description"><?php _e('Dirección completa de la instalación.', 'certificados-personalizados'); ?></p>
                        </td>
                    </tr>
                    
                    <!-- Información de la Empresa -->
                    <tr>
                        <th scope="row">
                            <label for="razon_social"><?php _e('Razón Social', 'certificados-personalizados'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" id="razon_social" name="razon_social" class="regular-text" 
                                   value="<?php echo $modo_edicion ? esc_attr($certificado_edicion->razon_social) : ''; ?>" required>
                            <p class="description"><?php _e('Razón social de la empresa.', 'certificados-personalizados'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="nit"><?php _e('NIT', 'certificados-personalizados'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" id="nit" name="nit" class="regular-text" 
                                   value="<?php echo $modo_edicion ? esc_attr($certificado_edicion->nit) : ''; ?>" required>
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
                                <option value=""><?php _e('Seleccionar tipo de certificado', 'certificados-personalizados'); ?></option>
                                <?php 
                                $tipos = obtener_tipos_certificado();
                                foreach ($tipos as $valor => $etiqueta): 
                                ?>
                                    <option value="<?php echo esc_attr($valor); ?>" 
                                            <?php echo ($modo_edicion && $certificado_edicion->tipo_certificado === $valor) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($etiqueta); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Selecciona el tipo de certificado GLP.', 'certificados-personalizados'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="numero_certificado"><?php _e('Número del Certificado', 'certificados-personalizados'); ?> *</label>
                        </th>
                        <td>
                            <input type="number" id="numero_certificado" name="numero_certificado" class="small-text" 
                                   value="<?php echo $modo_edicion ? esc_attr($certificado_edicion->numero_certificado) : ''; ?>" 
                                   min="1" required>
                            <p class="description"><?php _e('Número del certificado (solo números). Se mostrará como TIPO-NÚMERO en el PDF.', 'certificados-personalizados'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="fecha_aprobacion"><?php _e('Fecha de Aprobación del Certificado', 'certificados-personalizados'); ?> *</label>
                        </th>
                        <td>
                            <input type="date" id="fecha_aprobacion" name="fecha_aprobacion" 
                                   value="<?php echo $modo_edicion ? esc_attr($certificado_edicion->fecha_aprobacion) : ''; ?>" required>
                            <p class="description"><?php _e('Fecha de aprobación del certificado.', 'certificados-personalizados'); ?></p>
                        </td>
                    </tr>
                    
                    <!-- Información Técnica -->
                    <tr>
                        <th scope="row">
                            <label for="capacidad_almacenamiento"><?php _e('Capacidad de Almacenamiento', 'certificados-personalizados'); ?> *</label>
                        </th>
                        <td>
                            <input type="number" id="capacidad_almacenamiento" name="capacidad_almacenamiento" class="regular-text" 
                                   value="<?php echo $modo_edicion ? esc_attr($certificado_edicion->capacidad_almacenamiento) : ''; ?>" 
                                   placeholder="Ej: 4000" min="1" required>
                            <p class="description"><?php _e('Capacidad de almacenamiento en galones (solo números). Los puntos de miles se agregarán automáticamente en el PDF.', 'certificados-personalizados'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="numero_tanques"><?php _e('Número de Tanques', 'certificados-personalizados'); ?> *</label>
                        </th>
                        <td>
                            <input type="number" id="numero_tanques" name="numero_tanques" class="small-text" 
                                   value="<?php echo $modo_edicion ? esc_attr($certificado_edicion->numero_tanques) : ''; ?>" 
                                   min="1" required>
                            <p class="description"><?php _e('Número total de tanques de la instalación.', 'certificados-personalizados'); ?></p>
                        </td>
                    </tr>
                    
                    <!-- Información dinámica del certificado -->
                    <tr>
                        <th scope="row">
                            <label><?php _e('Información del Certificado', 'certificados-personalizados'); ?></label>
                        </th>
                        <td>
                            <div id="certificate-info" style="background: #f9f9f9; padding: 15px; border-radius: 5px; border-left: 4px solid #0073aa;">
                                <div id="certificate-scope" style="margin-bottom: 10px;">
                                    <strong>Alcance:</strong> <span id="scope-text">Selecciona un tipo de certificado para ver el alcance</span>
                                </div>
                                <div id="certificate-requirements" style="margin-bottom: 10px;">
                                    <strong>Requisitos:</strong> <span id="requirements-text">Selecciona un tipo de certificado para ver los requisitos</span>
                                </div>
                                <div id="certificate-validity" style="margin-bottom: 10px;">
                                    <strong>Vigencia:</strong> <span id="validity-text">Selecciona un tipo de certificado para ver la vigencia</span>
                                </div>
                                <div id="certificate-expiry" style="margin-bottom: 10px;">
                                    <strong>Fecha de Vencimiento:</strong> <span id="expiry-text">Selecciona fecha de aprobación para calcular vencimiento</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                    
                </table>
                
                <p class="submit">
                    <button type="button" id="btn-confirmar-certificado" class="button-primary">
                        <?php if ($modo_edicion): ?>
                            <?php _e('Actualizar Certificado', 'certificados-personalizados'); ?>
                        <?php else: ?>
                            <?php _e('Solicitar Certificado', 'certificados-personalizados'); ?>
                        <?php endif; ?>
                    </button>
                </p>
            </form>
        </div>
        </div>
        
        <!-- Contenido de pestaña: Carga Masiva -->
        <div id="carga-masiva" class="tab-content" style="display: none;">
            <div class="certificado-formulario">
                <h2><?php _e('Carga Masiva de Certificados', 'certificados-personalizados'); ?></h2>
                
                <div class="notice notice-info">
                    <p><strong><?php _e('Instrucciones paso a paso:', 'certificados-personalizados'); ?></strong></p>
                    <ol>
                        <li><strong><?php _e('Descargar plantilla:', 'certificados-personalizados'); ?></strong> <?php _e('Haga clic en "Descargar Plantilla CSV"', 'certificados-personalizados'); ?></li>
                        <li><strong><?php _e('Abrir plantilla:', 'certificados-personalizados'); ?></strong> <?php _e('Abra el archivo CSV descargado con Excel o LibreOffice', 'certificados-personalizados'); ?></li>
                        <li><strong><?php _e('Llenar datos:', 'certificados-personalizados'); ?></strong> <?php _e('Complete las filas con los datos de los certificados (mantenga los encabezados)', 'certificados-personalizados'); ?></li>
                        <li><strong><?php _e('Guardar como CSV:', 'certificados-personalizados'); ?></strong> <?php _e('Guarde como "CSV UTF-8 (delimitado por comas)" - NO como Excel', 'certificados-personalizados'); ?></li>
                        <li><strong><?php _e('Subir archivo:', 'certificados-personalizados'); ?></strong> <?php _e('Seleccione el archivo CSV guardado y haga clic en "Procesar Archivo"', 'certificados-personalizados'); ?></li>
                    </ol>
                    
                    <div class="notice notice-warning">
                        <p><strong><?php _e('⚠️ Importante:', 'certificados-personalizados'); ?></strong></p>
                        <ul>
                            <li><?php _e('Use SOLO la plantilla CSV oficial descargada del sistema', 'certificados-personalizados'); ?></li>
                            <li><?php _e('NO modifique los nombres de las columnas (encabezados)', 'certificados-personalizados'); ?></li>
                            <li><?php _e('Guarde SIEMPRE como CSV, nunca como Excel (.xlsx/.xls)', 'certificados-personalizados'); ?></li>
                            <li><?php _e('Use comas como separadores, no puntos y comas', 'certificados-personalizados'); ?></li>
                        </ul>
                    </div>
                </div>
                
                <div class="upload-section">
                    <h3><?php _e('1. Descargar Plantilla', 'certificados-personalizados'); ?></h3>
                    <p><?php _e('Descarga la plantilla CSV con el formato correcto:', 'certificados-personalizados'); ?></p>
                    <p>
                        <a href="<?php echo admin_url('admin-post.php?action=descargar_plantilla_excel'); ?>" class="button button-primary">
                            📥 <?php _e('Descargar Plantilla CSV', 'certificados-personalizados'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin-post.php?action=crear_archivo_simple'); ?>" class="button button-secondary">
                            🧪 <?php _e('Archivo Simple (Recomendado)', 'certificados-personalizados'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin-post.php?action=crear_archivo_prueba'); ?>" class="button button-secondary">
                            📋 <?php _e('Archivo Completo', 'certificados-personalizados'); ?>
                        </a>
                    </p>
                    <p class="description">
                        <strong><?php _e('Archivo Simple (Recomendado):', 'certificados-personalizados'); ?></strong> <?php _e('Formato básico que funciona en todos los casos.', 'certificados-personalizados'); ?><br>
                        <strong><?php _e('Archivo Completo:', 'certificados-personalizados'); ?></strong> <?php _e('Incluye descripciones y formato avanzado.', 'certificados-personalizados'); ?>
                    </p>
                </div>
                
                <div class="upload-section">
                    <h3><?php _e('2. Subir Archivo', 'certificados-personalizados'); ?></h3>
                    <form method="post" enctype="multipart/form-data" id="formulario-excel">
                        <?php wp_nonce_field('procesar_excel_masivo', 'excel_masivo_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="archivo_excel"><?php _e('Archivo Excel/CSV', 'certificados-personalizados'); ?> *</label>
                                </th>
                                <td>
                                    <input type="file" id="archivo_excel" name="archivo_excel" accept=".csv,.xlsx,.xls" required>
                                    <p class="description">
                                        <?php _e('Formatos permitidos: CSV (recomendado), XLSX, XLS. Máximo 5MB.', 'certificados-personalizados'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="procesar_excel_masivo" class="button button-primary" 
                                   value="<?php _e('Procesar Archivo', 'certificados-personalizados'); ?>">
                        </p>
                    </form>
                </div>
                
                <div class="upload-section">
                    <h3><?php _e('Información de la Plantilla', 'certificados-personalizados'); ?></h3>
                    <div class="plantilla-info">
                        <p><strong><?php _e('Columnas requeridas:', 'certificados-personalizados'); ?></strong></p>
                        <ul>
                            <li><strong>NOMBRE_INSTALACION:</strong> <?php _e('Nombre de la instalación', 'certificados-personalizados'); ?></li>
                            <li><strong>DIRECCION_INSTALACION:</strong> <?php _e('Dirección completa', 'certificados-personalizados'); ?></li>
                            <li><strong>RAZON_SOCIAL:</strong> <?php _e('Razón social de la empresa', 'certificados-personalizados'); ?></li>
                            <li><strong>NIT:</strong> <?php _e('Número de identificación tributaria', 'certificados-personalizados'); ?></li>
                            <li><strong>CAPACIDAD_ALMACENAMIENTO:</strong> <?php _e('Capacidad en galones', 'certificados-personalizados'); ?></li>
                            <li><strong>NUMERO_TANQUES:</strong> <?php _e('Cantidad de tanques', 'certificados-personalizados'); ?></li>
                            <li><strong>TIPO_CERTIFICADO:</strong> <?php _e('PAGLP, TEGLP, PEGLP, DEGLP, PVGLP', 'certificados-personalizados'); ?></li>
                            <li><strong>NUMERO_CERTIFICADO:</strong> <?php _e('Número del certificado', 'certificados-personalizados'); ?></li>
                            <li><strong>FECHA_APROBACION:</strong> <?php _e('Fecha en formato DD/MM/YYYY', 'certificados-personalizados'); ?></li>
                        </ul>
                    </div>
                    
                    <details class="troubleshooting">
                        <summary><strong><?php _e('🔧 Solución de problemas comunes:', 'certificados-personalizados'); ?></strong></summary>
                        <div class="troubleshooting-content">
                            <h4><?php _e('Problema: "No se pudieron leer los datos del archivo"', 'certificados-personalizados'); ?></h4>
                            <p><strong><?php _e('Solución:', 'certificados-personalizados'); ?></strong></p>
                            <ol>
                                <li><?php _e('Asegúrese de haber descargado la plantilla CSV oficial', 'certificados-personalizados'); ?></li>
                                <li><?php _e('Abra la plantilla con Excel y llene los datos', 'certificados-personalizados'); ?></li>
                                <li><?php _e('Al guardar, seleccione "CSV UTF-8 (delimitado por comas)"', 'certificados-personalizados'); ?></li>
                                <li><?php _e('NO guarde como Excel (.xlsx) - solo CSV', 'certificados-personalizados'); ?></li>
                            </ol>
                            
                            <h4><?php _e('Problema: "Encabezados incorrectos"', 'certificados-personalizados'); ?></h4>
                            <p><strong><?php _e('Solución:', 'certificados-personalizados'); ?></strong></p>
                            <ol>
                                <li><?php _e('NO modifique la primera fila (encabezados)', 'certificados-personalizados'); ?></li>
                                <li><?php _e('Mantenga exactamente estos nombres: NOMBRE_INSTALACION, DIRECCION_INSTALACION, etc.', 'certificados-personalizados'); ?></li>
                                <li><?php _e('Use solo mayúsculas y guiones bajos en los encabezados', 'certificados-personalizados'); ?></li>
                            </ol>
                            
                            <h4><?php _e('Problema: "Formato de fecha incorrecto"', 'certificados-personalizados'); ?></h4>
                            <p><strong><?php _e('Solución:', 'certificados-personalizados'); ?></strong></p>
                            <ol>
                                <li><?php _e('Use el formato DD/MM/YYYY (ejemplo: 15/12/2024)', 'certificados-personalizados'); ?></li>
                                <li><?php _e('NO use guiones (-) o puntos (.) en las fechas', 'certificados-personalizados'); ?></li>
                                <li><?php _e('La fecha no puede ser futura', 'certificados-personalizados'); ?></li>
                            </ol>
                        </div>
                    </details>
                </div>
            </div>
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
                            <th><?php _e('Tipo Certificado', 'certificados-personalizados'); ?></th>
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
                                <td>
                                    <strong><?php echo esc_html($certificado->codigo_unico); ?></strong>
                                </td>
                                <td>
                                    <?php 
                                    $tipos = obtener_tipos_certificado();
                                    $tipo_mostrar = isset($tipos[$certificado->tipo_certificado]) ? $tipos[$certificado->tipo_certificado] : $certificado->tipo_certificado;
                                    echo esc_html($tipo_mostrar);
                                    ?>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($certificado->tipo_certificado . '-' . str_pad($certificado->numero_certificado, 2, '0', STR_PAD_LEFT)); ?></strong>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($certificado->nombre_instalacion); ?></strong><br>
                                    <small><?php echo esc_html($certificado->razon_social); ?></small>
                                </td>
                                <td>
                                    <?php echo esc_html($certificado->capacidad_almacenamiento); ?> galones
                                </td>
                                <td>
                                    <?php echo esc_html($certificado->numero_tanques); ?>
                                </td>
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
                                    <?php 
                                    $es_editable = CertificadosAntecoreBD::certificado_es_editable($certificado);
                                    ?>
                                    
                                    <?php if ($es_editable): ?>
                                        <!-- Botón Editar -->
                                        <a href="<?php echo admin_url('admin.php?page=mis-certificados&editar=' . $certificado->id); ?>" 
                                           class="button button-secondary" style="margin-right: 5px;">
                                            ✏️ <?php _e('Editar', 'certificados-personalizados'); ?>
                                        </a>
                                        
                                        <!-- Botón Enviar para Aprobación -->
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                                            <input type="hidden" name="action" value="enviar_certificado_aprobacion">
                                            <input type="hidden" name="certificado_id" value="<?php echo $certificado->id; ?>">
                                            <?php wp_nonce_field('enviar_certificado_aprobacion', 'enviar_aprobacion_nonce'); ?>
                                            <button type="submit" class="button button-primary">
                                                <?php _e('Enviar para Aprobación', 'certificados-personalizados'); ?>
                                            </button>
                                        </form>
                                    <?php elseif ($certificado->estado === 'pendiente' && $certificado->notificado == 1): ?>
                                        <span class="estado-enviado" style="color: #0073aa; font-weight: bold;">
                                            <?php _e('✅ Enviado', 'certificados-personalizados'); ?>
                                        </span>
                                    <?php elseif ($certificado->estado === 'aprobado'): ?>
                                        <span class="estado-aprobado" style="color: #28a745; font-weight: bold;">
                                            <?php _e('✅ Aprobado', 'certificados-personalizados'); ?>
                                        </span>
                                    <?php elseif ($certificado->estado === 'rechazado'): ?>
                                        <span class="estado-rechazado" style="color: #dc3545; font-weight: bold;">
                                            <?php _e('❌ Rechazado', 'certificados-personalizados'); ?>
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
            <h3 id="modal-titulo">📋 Confirmar Certificado</h3>
        </div>
        <div class="modal-body">
            <div class="confirmacion-mensaje">
                <strong id="modal-mensaje">Por favor, revisa la información antes de generar el certificado:</strong>
            </div>
            
            <div class="confirmacion-item">
                <span class="confirmacion-label">Nombre de la Instalación</span>
                <div class="confirmacion-valor" id="confirm-nombre-instalacion"></div>
            </div>
            
            <div class="confirmacion-item">
                <span class="confirmacion-label">Dirección</span>
                <div class="confirmacion-valor" id="confirm-direccion"></div>
            </div>
            
            <div class="confirmacion-item">
                <span class="confirmacion-label">Razón Social</span>
                <div class="confirmacion-valor" id="confirm-razon-social"></div>
            </div>
            
            <div class="confirmacion-item">
                <span class="confirmacion-label">NIT</span>
                <div class="confirmacion-valor" id="confirm-nit"></div>
            </div>
            
            <div class="confirmacion-item">
                <span class="confirmacion-label">Tipo de Certificado</span>
                <div class="confirmacion-valor" id="confirm-tipo-certificado"></div>
            </div>
            
            <div class="confirmacion-item">
                <span class="confirmacion-label">Número de Certificado</span>
                <div class="confirmacion-valor" id="confirm-numero-certificado"></div>
            </div>
            
            <div class="confirmacion-item">
                <span class="confirmacion-label">Fecha de Aprobación</span>
                <div class="confirmacion-valor" id="confirm-fecha-aprobacion"></div>
            </div>
            
            <div class="confirmacion-item">
                <span class="confirmacion-label">Capacidad de Almacenamiento</span>
                <div class="confirmacion-valor" id="confirm-capacidad"></div>
            </div>
            
            <div class="confirmacion-item">
                <span class="confirmacion-label">Número de Tanques</span>
                <div class="confirmacion-valor" id="confirm-numero-tanques"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-cancelar" id="btn-cancelar">
                ❌ Cancelar
            </button>
            <button type="button" class="modal-btn modal-btn-confirmar" id="btn-confirmar">
                ✅ <span id="btn-confirmar-texto">Confirmar y Generar</span>
            </button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    console.log('jQuery cargado correctamente');
    console.log('Botón encontrado:', $('#btn-confirmar-certificado').length > 0);
    // Mapeo de tipos de certificado
    const tiposCertificado = {
        'PAGLP': 'PAGLP - Planta de Almacenamiento de GLP',
        'TEGLP': 'TEGLP - Tanque de Almacenamiento de GLP',
        'PEGLP': 'PEGLP - Planta de Envasado de GLP',
        'DEGLP': 'DEGLP - Distribuidora de GLP',
        'PVGLP': 'PVGLP - Punto de Venta de GLP'
    };
    
    // Información detallada de cada tipo de certificado
    const infoCertificados = {
        'PAGLP': {
            alcance: 'Certificación de Planta de Almacenamiento de GLP para redes de distribución.',
            requisitos: 'Resolución 40246 de marzo de 2016 del Ministerio de Minas y Energía\nCapítulo I - Capítulo II Artículos 6, 7 y 8\nResolución 40867 de septiembre de 2016 del Ministerio de Minas y Energía',
            vigencia: '5 años'
        },
        'TEGLP': {
            alcance: 'Certificación de tanques estacionarios de GLP instalados en domicilio de usuarios finales.',
            requisitos: 'Resolución 40246 de marzo de 2016 del Ministerio de Minas y Energía\nCapítulo I - Capítulo III Artículos 9, 10 y 11\nResolución 40867 de septiembre de 2016 del Ministerio de Minas y Energía',
            vigencia: '5 años'
        },
        'PEGLP': {
            alcance: 'Certificación de plantas de envasado de GLP.',
            requisitos: 'Resolución 40247 de marzo de 2016 del Ministerio de Minas y Energía\nResolución 40868 de septiembre de 2016 del Ministerio de Minas y Energía',
            vigencia: '5 años'
        },
        'DEGLP': {
            alcance: 'Certificación de depósitos de cilindros de GLP.',
            requisitos: 'Resolución 40248 de marzo de 2016 del Ministerio de Minas y Energía\nCapítulo I - Capítulo II Artículos 6, 7 y 8\nResolución 40869 de septiembre de 2016 del Ministerio de Minas y Energía',
            vigencia: '3 años'
        },
        'PVGLP': {
            alcance: 'Certificación de expendios y puntos de venta de cilindros de GLP.',
            requisitos: 'Resolución 40248 de marzo de 2016 del Ministerio de Minas y Energía\nCapítulo I - Capítulo III Artículos 9, 10 y 11\nResolución 40869 de septiembre de 2016 del Ministerio de Minas y Energía',
            vigencia: '3 años'
        }
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
    
    // Función para calcular fecha de vencimiento
    function calcularFechaVencimiento(fechaAprobacion, tipoCertificado) {
        if (!fechaAprobacion || !tipoCertificado) return 'Selecciona fecha y tipo';
        
        const fecha = new Date(fechaAprobacion);
        const info = infoCertificados[tipoCertificado];
        
        if (!info) return 'Tipo no válido';
        
        // Calcular años según el tipo
        const anos = info.vigencia === '5 años' ? 5 : 3;
        fecha.setFullYear(fecha.getFullYear() + anos);
        
        return fecha.toLocaleDateString('es-ES');
    }
    
    // Función para actualizar información del certificado
    function actualizarInfoCertificado() {
        const tipoCertificado = $('#tipo_certificado').val();
        const fechaAprobacion = $('#fecha_aprobacion').val();
        
        if (tipoCertificado && infoCertificados[tipoCertificado]) {
            const info = infoCertificados[tipoCertificado];
            
            // Actualizar alcance
            $('#scope-text').text(info.alcance);
            
            // Actualizar requisitos (reemplazar \n con <br>)
            const requisitosFormateados = info.requisitos.replace(/\n/g, '<br>');
            $('#requirements-text').html(requisitosFormateados);
            
            // Actualizar vigencia
            $('#validity-text').text(info.vigencia);
            
            // Calcular y actualizar fecha de vencimiento
            const fechaVencimiento = calcularFechaVencimiento(fechaAprobacion, tipoCertificado);
            $('#expiry-text').text(fechaVencimiento);
            
        } else {
            // Resetear información
            $('#scope-text').text('Selecciona un tipo de certificado para ver el alcance');
            $('#requirements-text').text('Selecciona un tipo de certificado para ver los requisitos');
            $('#validity-text').text('Selecciona un tipo de certificado para ver la vigencia');
            $('#expiry-text').text('Selecciona fecha de aprobación para calcular vencimiento');
        }
    }
    
    // Función para mostrar el modal
    function mostrarModal() {
        console.log('Función mostrarModal ejecutada');
        // Obtener valores del formulario
        const nombreInstalacion = $('#nombre_instalacion').val().trim();
        const direccion = $('#direccion_instalacion').val().trim();
        const razonSocial = $('#razon_social').val().trim();
        const nit = $('#nit').val().trim();
        const tipoCertificado = $('#tipo_certificado').val();
        const numeroCertificado = $('#numero_certificado').val();
        const fechaAprobacion = $('#fecha_aprobacion').val();
        const capacidad = $('#capacidad_almacenamiento').val();
        const numeroTanques = $('#numero_tanques').val();
        
        // Validar campos obligatorios
        if (!nombreInstalacion || !direccion || !razonSocial || !nit || !tipoCertificado || !numeroCertificado || !fechaAprobacion || !capacidad || !numeroTanques) {
            alert('Por favor, completa todos los campos obligatorios antes de continuar.');
            return false;
        }
        
        // Validar fecha futura
        const fechaActual = new Date().toISOString().split('T')[0];
        if (fechaAprobacion > fechaActual) {
            alert('La fecha de aprobación no puede ser futura.');
            return false;
        }
        
        // Configurar modal según el modo
        if (modoEdicion) {
            $('#modal-titulo').text('📝 Confirmar Edición');
            $('#modal-mensaje').text('Por favor, revisa la información antes de actualizar el certificado:');
            $('#btn-confirmar-texto').text('Confirmar y Actualizar');
        } else {
            $('#modal-titulo').text('📋 Confirmar Certificado');
            $('#modal-mensaje').text('Por favor, revisa la información antes de generar el certificado:');
            $('#btn-confirmar-texto').text('Confirmar y Generar');
        }
        
        // Llenar información en el modal
        $('#confirm-nombre-instalacion').text(nombreInstalacion);
        $('#confirm-direccion').text(direccion);
        $('#confirm-razon-social').text(razonSocial);
        $('#confirm-nit').text(nit);
        $('#confirm-tipo-certificado').text(tiposCertificado[tipoCertificado] || tipoCertificado);
        $('#confirm-numero-certificado').text(tipoCertificado + '-' + numeroCertificado.toString().padStart(2, '0'));
        $('#confirm-fecha-aprobacion').text(formatearFecha(fechaAprobacion));
        $('#confirm-capacidad').text(capacidad + ' galones');
        $('#confirm-numero-tanques').text(numeroTanques);
        
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
        console.log('Botón clickeado - mostrando modal...');
        mostrarModal();
    });
    
    // Verificar si estamos en modo edición
    const modoEdicion = <?php echo $modo_edicion ? 'true' : 'false'; ?>;
    
    // Evento para cancelar
    $('#btn-cancelar').on('click', function() {
        ocultarModal();
    });
    
    // Evento para confirmar y enviar
    $('#btn-confirmar').on('click', function() {
        // Deshabilitar botón para evitar doble envío
        const btnText = modoEdicion ? '⏳ Actualizando...' : '⏳ Generando...';
        $(this).prop('disabled', true).text(btnText);
        
        // Agregar campo hidden para indicar que es una confirmación (solo en modo creación)
        if (!modoEdicion && !$('#solicitar_certificado').length) {
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
    
    // Validación en tiempo real para habilitar/deshabilitar botón
    $('#nombre_instalacion, #direccion_instalacion, #razon_social, #nit, #tipo_certificado, #numero_certificado, #fecha_aprobacion, #capacidad_almacenamiento, #numero_tanques').on('input change', function() {
        verificarEstadoBoton();
    });
    
    // Actualizar información del certificado cuando cambie el tipo o la fecha
    $('#tipo_certificado, #fecha_aprobacion').on('change', function() {
        actualizarInfoCertificado();
    });
    
    // Función para verificar estado del botón
    function verificarEstadoBoton() {
        const nombreInstalacion = $('#nombre_instalacion').val().trim();
        const direccion = $('#direccion_instalacion').val().trim();
        const razonSocial = $('#razon_social').val().trim();
        const nit = $('#nit').val().trim();
        const tipoCertificado = $('#tipo_certificado').val();
        const numeroCertificado = $('#numero_certificado').val();
        const fechaAprobacion = $('#fecha_aprobacion').val();
        const capacidad = $('#capacidad_almacenamiento').val();
        const numeroTanques = $('#numero_tanques').val();
        
        // Verificar que todos los campos obligatorios estén llenos
        if (nombreInstalacion && direccion && razonSocial && nit && tipoCertificado && 
            numeroCertificado && fechaAprobacion && capacidad && numeroTanques) {
            $('#btn-confirmar-certificado').prop('disabled', false);
            console.log('Botón habilitado - todos los campos llenos');
        } else {
            $('#btn-confirmar-certificado').prop('disabled', true);
            console.log('Botón deshabilitado - campos faltantes');
        }
    }
    
    // Inicializar estado del botón (solo en modo creación)
    if (!modoEdicion) {
        $('#btn-confirmar-certificado').prop('disabled', true);
        // Verificar estado inicial después de un pequeño delay para asegurar que los campos estén cargados
        setTimeout(verificarEstadoBoton, 100);
    }
    
    // Inicializar información del certificado
    setTimeout(actualizarInfoCertificado, 100);
});
</script>

<?php
/**
 * Enviar notificaciones de nueva solicitud de certificado
 */
function enviar_notificaciones_nueva_solicitud($certificado_id) {
    // Obtener configuración de emails
    $emails_personalizados = get_option('certificados_emails_notificacion', []);
    $usuarios_seleccionados = get_option('certificados_usuarios_notificacion', []);
    
    // Obtener emails de usuarios seleccionados
    $emails_usuarios = [];
    if (!empty($usuarios_seleccionados)) {
        $usuarios = get_users(['include' => $usuarios_seleccionados]);
        foreach ($usuarios as $usuario) {
            if (is_email($usuario->user_email)) {
                $emails_usuarios[] = $usuario->user_email;
            }
        }
    }
    
    // Combinar todos los emails y eliminar duplicados
    $todos_emails = array_merge($emails_personalizados, $emails_usuarios);
    $todos_emails = array_unique($todos_emails);
    
    // Si no hay emails configurados, no enviar notificaciones
    if (empty($todos_emails)) {
        error_log('CertificadosAntecore: No hay emails configurados para notificaciones. Certificado ID: ' . $certificado_id);
        return false;
    }
    
    // Obtener datos del certificado
    $certificado = CertificadosAntecoreBD::obtener_certificado($certificado_id);
    if (!$certificado) {
        error_log('CertificadosAntecore: No se pudo obtener el certificado para notificación. ID: ' . $certificado_id);
        return false;
    }
    
    // Obtener datos del usuario que envió la solicitud
    $usuario_solicitante = get_userdata($certificado->user_id);
    $nombre_usuario = $usuario_solicitante ? $usuario_solicitante->display_name : 'Usuario desconocido';
    $email_usuario = $usuario_solicitante ? $usuario_solicitante->user_email : 'Email no disponible';
    
    // Preparar contenido del email
    $asunto = '🔔 Nueva solicitud de certificado - ' . esc_html($certificado->nombre_instalacion);
    
    $mensaje = '
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background-color: #542a1a; color: #fef7d4; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .info-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            .info-table th, .info-table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
            .info-table th { background-color: #542a1a; color: #fef7d4; }
            .button { display: inline-block; background-color: #542a1a; color: #fef7d4; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 15px 0; }
            .footer { background-color: #f5f0e8; padding: 15px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>🔔 Nueva Solicitud de Certificado</h1>
            <p>Sistema de Certificados Antecore</p>
        </div>
        
        <div class="content">
            <h2>📋 Información de la Solicitud</h2>
            
            <table class="info-table">
                <tr>
                    <th>🏢 Instalación</th>
                    <td>' . esc_html($certificado->nombre_instalacion) . '</td>
                </tr>
                <tr>
                    <th>🏷️ Código Único</th>
                    <td><strong>' . esc_html($certificado->codigo_unico) . '</strong></td>
                </tr>
                <tr>
                    <th>📄 Tipo de Certificado</th>
                    <td>' . esc_html($certificado->tipo_certificado) . '</td>
                </tr>
                <tr>
                    <th>🔢 Número de Certificado</th>
                    <td>' . esc_html($certificado->numero_certificado) . '</td>
                </tr>
                <tr>
                    <th>🏭 Razón Social</th>
                    <td>' . esc_html($certificado->razon_social) . '</td>
                </tr>
                <tr>
                    <th>🆔 NIT</th>
                    <td>' . esc_html($certificado->nit) . '</td>
                </tr>
                <tr>
                    <th>📍 Dirección</th>
                    <td>' . esc_html($certificado->direccion_instalacion) . '</td>
                </tr>
                <tr>
                    <th>⛽ Capacidad de Almacenamiento</th>
                    <td>' . esc_html($certificado->capacidad_almacenamiento) . '</td>
                </tr>
                <tr>
                    <th>🛢️ Número de Tanques</th>
                    <td>' . esc_html($certificado->numero_tanques) . '</td>
                </tr>
                <tr>
                    <th>📅 Fecha de Aprobación</th>
                    <td>' . esc_html($certificado->fecha_aprobacion) . '</td>
                </tr>
                <tr>
                    <th>👤 Solicitante</th>
                    <td>' . esc_html($nombre_usuario) . ' (' . esc_html($email_usuario) . ')</td>
                </tr>
                <tr>
                    <th>⏰ Fecha de Solicitud</th>
                    <td>' . date('d/m/Y H:i:s', strtotime($certificado->created_at)) . '</td>
                </tr>
            </table>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . admin_url('admin.php?page=aprobacion-certificados') . '" class="button">
                    🔍 Revisar en Panel de Administración
                </a>
            </div>
            
            <p><strong>📝 Nota:</strong> Esta solicitud está pendiente de revisión. Por favor, accede al panel de administración para aprobar o rechazar el certificado.</p>
        </div>
        
        <div class="footer">
            <p>Este es un mensaje automático del Sistema de Certificados Antecore.</p>
            <p>No responda a este email. Para consultas, contacte al administrador del sistema.</p>
        </div>
    </body>
    </html>';
    
    // Configurar headers del email
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Sistema Certificados Antecore <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>',
        'Reply-To: ' . get_option('admin_email')
    );
    
    // Enviar email a cada destinatario
    $emails_enviados = 0;
    foreach ($todos_emails as $email) {
        if (wp_mail($email, $asunto, $mensaje, $headers)) {
            $emails_enviados++;
        } else {
            error_log('CertificadosAntecore: Error al enviar notificación a: ' . $email);
        }
    }
    
    // Log del resultado
    error_log('CertificadosAntecore: Notificaciones enviadas. Certificado ID: ' . $certificado_id . ' | Emails enviados: ' . $emails_enviados . '/' . count($todos_emails));
    
    return $emails_enviados > 0;
}
?>

<script>
jQuery(document).ready(function($) {
    // Funcionalidad de pestañas
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Remover clase activa de todas las pestañas
        $('.nav-tab').removeClass('nav-tab-active');
        
        // Agregar clase activa a la pestaña clickeada
        $(this).addClass('nav-tab-active');
        
        // Ocultar todos los contenidos de pestañas
        $('.tab-content').hide();
        
        // Mostrar el contenido de la pestaña seleccionada
        var tab_id = $(this).data('tab');
        $('#' + tab_id).show();
    });
    
    // Validación del formulario Excel
    $('#formulario-excel').on('submit', function(e) {
        var archivo = $('#archivo_excel')[0].files[0];
        
        if (!archivo) {
            e.preventDefault();
            alert('Por favor, selecciona un archivo Excel/CSV.');
            return false;
        }
        
        // Verificar extensión
        var extensiones_permitidas = ['csv', 'xlsx', 'xls'];
        var extension = archivo.name.split('.').pop().toLowerCase();
        
        if (extensiones_permitidas.indexOf(extension) === -1) {
            e.preventDefault();
            alert('Tipo de archivo no permitido. Solo se permiten: CSV, XLSX, XLS');
            return false;
        }
        
        // Verificar tamaño (5MB máximo)
        if (archivo.size > 5 * 1024 * 1024) {
            e.preventDefault();
            alert('El archivo es demasiado grande. Máximo 5MB permitido.');
            return false;
        }
        
        // Mostrar mensaje de procesamiento
        if (!confirm('¿Estás seguro de que quieres procesar este archivo? Esta acción creará múltiples certificados.')) {
            e.preventDefault();
            return false;
        }
    });
});
</script>

<style>
/* Estilos para las pestañas */
.nav-tab-wrapper {
    border-bottom: 1px solid #ccd0d4;
    margin-bottom: 20px;
}

.nav-tab {
    background: #f1f1f1;
    border: 1px solid #ccd0d4;
    border-bottom: none;
    color: #50575e;
    display: inline-block;
    padding: 8px 12px;
    text-decoration: none;
    margin-right: 2px;
}

.nav-tab:hover {
    background: #f9f9f9;
    color: #135e96;
}

.nav-tab-active {
    background: #fff;
    border-bottom: 1px solid #fff;
    color: #135e96;
    margin-bottom: -1px;
}

.tab-content {
    margin-top: 20px;
}

.upload-section {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.upload-section h3 {
    margin-top: 0;
    color: #135e96;
}

.plantilla-info {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-top: 10px;
}

.plantilla-info ul {
    margin: 10px 0;
    padding-left: 20px;
}

.plantilla-info li {
    margin-bottom: 5px;
}

/* Estilos para el formulario Excel */
#formulario-excel .form-table th {
    width: 200px;
}

#formulario-excel input[type="file"] {
    width: 100%;
    max-width: 400px;
}

/* Estilos para solución de problemas */
.troubleshooting {
    margin-top: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #f9f9f9;
}

.troubleshooting summary {
    padding: 15px;
    cursor: pointer;
    background: #e7f3ff;
    border-bottom: 1px solid #ddd;
    font-weight: bold;
    color: #135e96;
}

.troubleshooting summary:hover {
    background: #d1ecf1;
}

.troubleshooting-content {
    padding: 20px;
    background: #fff;
}

.troubleshooting-content h4 {
    color: #d63384;
    margin-top: 20px;
    margin-bottom: 10px;
}

.troubleshooting-content h4:first-child {
    margin-top: 0;
}

.troubleshooting-content ol {
    margin-left: 20px;
    margin-bottom: 15px;
}

.troubleshooting-content li {
    margin-bottom: 5px;
}

/* Estilos para notificaciones */
.notice-warning {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    color: #856404;
}

.notice-warning ul {
    margin: 10px 0;
    padding-left: 20px;
}

.notice-warning li {
    margin-bottom: 5px;
}
</style>
?> 