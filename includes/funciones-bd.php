<?php
/**
 * Funciones de base de datos para el plugin Certificados Personalizados
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para manejo de base de datos
 */
class CertificadosPersonalizadosBD {
    
    /**
     * Obtener tabla de certificados
     */
    private static function obtener_tabla() {
        global $wpdb;
        return $wpdb->prefix . 'certificados_personalizados';
    }
    
    /**
     * Generar código único para certificado
     */
    public static function generar_codigo_unico() {
        $codigo = 'CERT-' . date('Y') . '-' . strtoupper(substr(md5(uniqid()), 0, 8));
        return $codigo;
    }
    
    /**
     * Crear nuevo certificado
     */
    public static function crear_certificado($datos) {
        global $wpdb;
        
        $tabla = self::obtener_tabla();
        
        $datos_default = array(
            'user_id' => get_current_user_id(),
            'codigo_unico' => self::generar_codigo_unico(),
            'estado' => 'pendiente',
            'notificado' => 0,
            'observaciones' => ''
        );
        
        $datos = wp_parse_args($datos, $datos_default);
        
        $resultado = $wpdb->insert($tabla, $datos);
        
        if ($resultado !== false) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Obtener certificados por usuario
     */
    public static function obtener_certificados_usuario($user_id = null, $estado = null) {
        global $wpdb;
        
        $tabla = self::obtener_tabla();
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        $where = array('user_id = %d');
        $valores = array($user_id);
        
        if ($estado !== null) {
            $where[] = 'estado = %s';
            $valores[] = $estado;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where);
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $tabla $where_clause ORDER BY created_at DESC",
            $valores
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Obtener todos los certificados (para administradores)
     */
    public static function obtener_todos_certificados($estado = null, $limit = 50) {
        global $wpdb;
        
        $tabla = self::obtener_tabla();
        
        $where = array();
        $valores = array();
        
        if ($estado !== null) {
            $where[] = 'estado = %s';
            $valores[] = $estado;
        }
        
        $where_clause = '';
        if (!empty($where)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where);
        }
        
        $sql = $wpdb->prepare(
            "SELECT c.*, u.display_name as nombre_usuario 
             FROM $tabla c 
             LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID 
             $where_clause 
             ORDER BY c.created_at DESC 
             LIMIT %d",
            array_merge($valores, array($limit))
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Obtener certificado por ID
     */
    public static function obtener_certificado($id) {
        global $wpdb;
        
        $tabla = self::obtener_tabla();
        
        $sql = $wpdb->prepare(
            "SELECT c.*, u.display_name as nombre_usuario 
             FROM $tabla c 
             LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID 
             WHERE c.id = %d",
            $id
        );
        
        return $wpdb->get_row($sql);
    }
    
    /**
     * Actualizar certificado
     */
    public static function actualizar_certificado($id, $datos) {
        global $wpdb;
        
        $tabla = self::obtener_tabla();
        
        // Asegurar que updated_at se actualice
        if (!isset($datos['updated_at'])) {
            $datos['updated_at'] = current_time('mysql');
        }
        
        $resultado = $wpdb->update(
            $tabla,
            $datos,
            array('id' => $id)
        );
        
        // Limpiar caché después de la actualización
        $wpdb->flush();
        
        return $resultado !== false;
    }
    
    /**
     * Cambiar estado de certificado
     */
    public static function cambiar_estado_certificado($id, $estado) {
        return self::actualizar_certificado($id, array(
            'estado' => $estado,
            'notificado' => 1
        ));
    }
    
    /**
     * Eliminar certificado
     */
    public static function eliminar_certificado($id) {
        global $wpdb;
        
        $tabla = self::obtener_tabla();
        
        $resultado = $wpdb->delete(
            $tabla,
            array('id' => $id)
        );
        
        return $resultado !== false;
    }
    
    /**
     * Verificar si un certificado es editable
     */
    public static function certificado_es_editable($certificado) {
        // Solo certificados pendientes que no han sido enviados
        return $certificado->estado === 'pendiente' && $certificado->notificado == 0;
    }
    
    /**
     * Obtener certificado por ID verificando permisos de usuario
     */
    public static function obtener_certificado_para_edicion($id) {
        global $wpdb;
        
        $tabla = self::obtener_tabla();
        $user_id = get_current_user_id();
        
        // Limpiar caché de consultas para asegurar datos frescos
        $wpdb->flush();
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $tabla WHERE id = %d AND user_id = %d",
            $id, $user_id
        );
        
        $certificado = $wpdb->get_row($sql);
        
        if (!$certificado) {
            return false;
        }
        
        // Verificar si es editable
        if (!self::certificado_es_editable($certificado)) {
            return false;
        }
        
        return $certificado;
    }
    
    /**
     * Función de debug para verificar datos de certificado
     */
    public static function debug_certificado($id) {
        global $wpdb;
        
        $tabla = self::obtener_tabla();
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $tabla WHERE id = %d",
            $id
        );
        
        $certificado = $wpdb->get_row($sql);
        
        if ($certificado) {
            error_log("Debug Certificado ID {$id}: " . print_r($certificado, true));
        }
        
        return $certificado;
    }
    
    /**
     * Obtener estadísticas de certificados
     */
    public static function obtener_estadisticas() {
        global $wpdb;
        
        $tabla = self::obtener_tabla();
        
        $sql = "SELECT 
                    estado,
                    COUNT(*) as total
                FROM $tabla 
                GROUP BY estado";
        
        $resultados = $wpdb->get_results($sql);
        
        $estadisticas = array(
            'pendiente' => 0,
            'aprobado' => 0,
            'rechazado' => 0,
            'total' => 0
        );
        
        foreach ($resultados as $resultado) {
            $estadisticas[$resultado->estado] = $resultado->total;
            $estadisticas['total'] += $resultado->total;
        }
        
        return $estadisticas;
    }
    
    /**
     * Verificar si código único existe
     */
    public static function codigo_unico_existe($codigo) {
        global $wpdb;
        
        $tabla = self::obtener_tabla();
        
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM $tabla WHERE codigo_unico = %s",
            $codigo
        );
        
        return $wpdb->get_var($sql) > 0;
    }
    
    /**
     * Obtener certificados pendientes de notificación
     */
    public static function obtener_pendientes_notificacion() {
        global $wpdb;
        
        $tabla = self::obtener_tabla();
        
        $sql = "SELECT * FROM $tabla WHERE notificado = 0 ORDER BY created_at ASC";
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Enviar notificación de certificado pendiente a administradores
     */
    public static function enviar_notificacion_aprobacion($certificado_id) {
        $certificado = self::obtener_certificado($certificado_id);
        
        if (!$certificado) {
            return false;
        }
        
        // Obtener administradores
        $administradores = get_users(['role' => 'administrator']);
        
        if (empty($administradores)) {
            return false;
        }
        
        // Preparar datos del correo
        $asunto = 'Nuevo certificado pendiente de aprobación';
        $admin_url = admin_url('admin.php?page=aprobacion-certificados');
        
        $tipos_actividad = array(
            'curso' => 'Curso de Capacitación',
            'taller' => 'Taller Práctico',
            'seminario' => 'Seminario',
            'conferencia' => 'Conferencia',
            'workshop' => 'Workshop',
            'otro' => 'Otro'
        );
        
        $tipo_mostrar = isset($tipos_actividad[$certificado->actividad]) ? 
            $tipos_actividad[$certificado->actividad] : $certificado->actividad;
        
        $mensaje = "
        <h2>Nuevo Certificado Pendiente de Aprobación</h2>
        
        <p><strong>Colaborador:</strong> {$certificado->nombre_usuario}</p>
        <p><strong>Nombre del Certificado:</strong> {$certificado->nombre}</p>
        <p><strong>Tipo de Actividad:</strong> {$tipo_mostrar}</p>
        <p><strong>Fecha de Actividad:</strong> " . date('d/m/Y', strtotime($certificado->fecha)) . "</p>
        <p><strong>Código del Certificado:</strong> {$certificado->codigo_unico}</p>
        
        " . (!empty($certificado->observaciones) ? "<p><strong>Observaciones:</strong> {$certificado->observaciones}</p>" : "") . "
        
        <p><strong>Fecha de Solicitud:</strong> " . date('d/m/Y H:i', strtotime($certificado->created_at)) . "</p>
        
        <p><a href='{$admin_url}' style='background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;'>Ver en Panel de Administración</a></p>
        
        <hr>
        <p><small>Este correo fue enviado automáticamente por el plugin Certificados Personalizados.</small></p>
        ";
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        // Enviar correo a cada administrador
        $enviado = false;
        foreach ($administradores as $admin) {
            $resultado = wp_mail($admin->user_email, $asunto, $mensaje, $headers);
            if ($resultado) {
                $enviado = true;
            }
        }
        
        return $enviado;
    }
    
    /**
     * Marcar certificado como notificado
     */
    public static function marcar_como_notificado($certificado_id) {
        return self::actualizar_certificado($certificado_id, array(
            'notificado' => 1
        ));
    }
    
    /**
     * Obtener certificados aprobados para el shortcode público
     */
    public static function obtener_certificados_aprobados($busqueda = '') {
        global $wpdb;
        
        $tabla = self::obtener_tabla();
        
        $where_conditions = array("estado = 'aprobado'");
        $where_values = array();
        
        // Agregar búsqueda si se proporciona
        if (!empty($busqueda)) {
            $where_conditions[] = "(nombre LIKE %s OR codigo_unico LIKE %s)";
            $busqueda_like = '%' . $wpdb->esc_like($busqueda) . '%';
            $where_values[] = $busqueda_like;
            $where_values[] = $busqueda_like;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "SELECT * FROM $tabla WHERE $where_clause ORDER BY created_at DESC";
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        return $wpdb->get_results($sql);
    }
} 