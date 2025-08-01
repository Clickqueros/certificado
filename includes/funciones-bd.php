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
        
        $resultado = $wpdb->update(
            $tabla,
            $datos,
            array('id' => $id)
        );
        
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
} 