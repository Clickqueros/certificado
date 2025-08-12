<?php
/**
 * Plugin Name: Certificados Personalizados
 * Plugin URI: https://github.com/Clickqueros/certificados-personalizados
 * Description: Plugin para gestión y aprobación de certificados internos para empleados
 * Version: 1.0.0
 * Author: Clickqueros
 * Author URI: https://github.com/Clickqueros
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: certificados-personalizados
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * 
 * README:
 * Este plugin permite a los colaboradores solicitar certificados y a los administradores
 * aprobarlos. Los certificados se almacenan en una tabla personalizada y pueden ser
 * generados como PDF posteriormente.
 * 
 * Funcionalidades:
 * - Formulario para colaboradores solicitar certificados
 * - Panel de aprobación para administradores
 * - Gestión de estados: pendiente, aprobado, rechazado
 * - Sistema de notificaciones
 * - Generación de códigos únicos para cada certificado
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('CERTIFICADOS_PERSONALIZADOS_VERSION', '1.0.0');
define('CERTIFICADOS_PERSONALIZADOS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CERTIFICADOS_PERSONALIZADOS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CERTIFICADOS_PERSONALIZADOS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Clase principal del plugin
 */
class CertificadosPersonalizados {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hooks de activación/desactivación
        register_activation_hook(__FILE__, array($this, 'activar_plugin'));
        register_deactivation_hook(__FILE__, array($this, 'desactivar_plugin'));
        
        // Hooks de inicialización
        add_action('init', array($this, 'inicializar_plugin'));
        add_action('admin_menu', array($this, 'agregar_menus_admin'));
        add_action('init', array($this, 'registrar_shortcodes'));
        
        // Hook para actualización manual de tabla
        add_action('admin_post_actualizar_tabla_certificados', array($this, 'forzar_actualizacion_tabla'));
        
        // Hook para enviar certificado para aprobación
        add_action('admin_post_enviar_certificado_aprobacion', array($this, 'procesar_envio_aprobacion'));
        
        // Hooks para aprobar/rechazar certificados
        add_action('admin_post_aprobar_certificado', array($this, 'procesar_aprobar_certificado'));
        add_action('admin_post_rechazar_certificado', array($this, 'procesar_rechazar_certificado'));
        
        // Hook para editar certificados
        add_action('admin_post_editar_certificado', array($this, 'procesar_edicion_certificado'));
        
        // Hook para editar certificados por administradores
        add_action('admin_post_editar_certificado_admin', array($this, 'procesar_edicion_certificado_admin'));
        
        // Cargar archivos necesarios
        $this->cargar_archivos();
    }
    
    /**
     * Cargar archivos del plugin
     */
    private function cargar_archivos() {
        // Cargar funciones de base de datos
        require_once CERTIFICADOS_PERSONALIZADOS_PLUGIN_PATH . 'includes/funciones-bd.php';
        
        // Cargar funciones de PDF
        require_once CERTIFICADOS_PERSONALIZADOS_PLUGIN_PATH . 'includes/funciones-pdf.php';
    }
    
    /**
     * Inicializar plugin
     */
    public function inicializar_plugin() {
        // Cargar traducciones
        load_plugin_textdomain(
            'certificados-personalizados',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    /**
     * Activar plugin
     */
    public function activar_plugin() {
        // Crear tabla de certificados
        $this->crear_tabla_certificados();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Forzar actualización de tabla (para desarrollo)
     */
    public function forzar_actualizacion_tabla() {
        $this->crear_tabla_certificados();
    }
    
    /**
     * Procesar envío de certificado para aprobación
     */
    public function procesar_envio_aprobacion() {
        // Verificar nonce
        if (!isset($_POST['enviar_aprobacion_nonce']) || 
            !wp_verify_nonce($_POST['enviar_aprobacion_nonce'], 'enviar_certificado_aprobacion')) {
            wp_die('Error de seguridad.');
        }
        
        // Verificar que el usuario esté logueado
        if (!is_user_logged_in()) {
            wp_die('Debes estar logueado para realizar esta acción.');
        }
        
        $certificado_id = intval($_POST['certificado_id']);
        $user_id = get_current_user_id();
        
        // Verificar que el certificado existe y pertenece al usuario
        $certificado = CertificadosPersonalizadosBD::obtener_certificado($certificado_id);
        
        if (!$certificado || $certificado->user_id != $user_id) {
            wp_die('Certificado no encontrado o no tienes permisos.');
        }
        
        // Verificar que no haya sido notificado ya
        if ($certificado->notificado == 1) {
            wp_die('Este certificado ya fue enviado para aprobación.');
        }
        
        // Enviar notificación por correo
        $correo_enviado = CertificadosPersonalizadosBD::enviar_notificacion_aprobacion($certificado_id);
        
        // Marcar como notificado
        $marcado = CertificadosPersonalizadosBD::marcar_como_notificado($certificado_id);
        
        if ($correo_enviado && $marcado) {
            $mensaje = 'exito';
            $texto = 'Tu solicitud ha sido enviada al administrador.';
        } else {
            $mensaje = 'error';
            $texto = 'Error al enviar la notificación. Inténtalo de nuevo.';
        }
        
        // Redirigir de vuelta al formulario
        $redirect_url = admin_url('admin.php?page=mis-certificados&mensaje=' . $mensaje . '&texto=' . urlencode($texto));
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Procesar aprobación de certificado
     */
    public function procesar_aprobar_certificado() {
        // Verificar permisos de administrador
        if (!current_user_can('administrator')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }
        
        // Verificar nonce
        if (!isset($_POST['aprobar_certificado_nonce']) || 
            !wp_verify_nonce($_POST['aprobar_certificado_nonce'], 'aprobar_certificado')) {
            wp_die('Error de seguridad.');
        }
        
        $certificado_id = intval($_POST['certificado_id']);
        
        // Verificar que el certificado existe y está pendiente
        $certificado = CertificadosPersonalizadosBD::obtener_certificado($certificado_id);
        
        if (!$certificado) {
            wp_die('Certificado no encontrado.');
        }
        
        if ($certificado->estado !== 'pendiente') {
            wp_die('Este certificado ya no está pendiente de aprobación.');
        }
        
        // Aprobar certificado
        $resultado = CertificadosPersonalizadosBD::cambiar_estado_certificado($certificado_id, 'aprobado');
        
        if ($resultado) {
            $mensaje = 'exito';
            $texto = 'Certificado aprobado correctamente.';
        } else {
            $mensaje = 'error';
            $texto = 'Error al aprobar el certificado.';
        }
        
        // Redirigir de vuelta al panel de aprobación
        $redirect_url = admin_url('admin.php?page=aprobacion-certificados&mensaje=' . $mensaje . '&texto=' . urlencode($texto));
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Procesar rechazo de certificado
     */
    public function procesar_rechazar_certificado() {
        // Verificar permisos de administrador
        if (!current_user_can('administrator')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }
        
        // Verificar nonce
        if (!isset($_POST['rechazar_certificado_nonce']) || 
            !wp_verify_nonce($_POST['rechazar_certificado_nonce'], 'rechazar_certificado')) {
            wp_die('Error de seguridad.');
        }
        
        $certificado_id = intval($_POST['certificado_id']);
        
        // Verificar que el certificado existe y está pendiente
        $certificado = CertificadosPersonalizadosBD::obtener_certificado($certificado_id);
        
        if (!$certificado) {
            wp_die('Certificado no encontrado.');
        }
        
        if ($certificado->estado !== 'pendiente') {
            wp_die('Este certificado ya no está pendiente de aprobación.');
        }
        
        // Rechazar certificado
        $resultado = CertificadosPersonalizadosBD::cambiar_estado_certificado($certificado_id, 'rechazado');
        
        if ($resultado) {
            $mensaje = 'exito';
            $texto = 'Certificado rechazado correctamente.';
        } else {
            $mensaje = 'error';
            $texto = 'Error al rechazar el certificado.';
        }
        
        // Redirigir de vuelta al panel de aprobación
        $redirect_url = admin_url('admin.php?page=aprobacion-certificados&mensaje=' . $mensaje . '&texto=' . urlencode($texto));
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Desactivar plugin
     */
    public function desactivar_plugin() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Crear tabla de certificados
     */
    private function crear_tabla_certificados() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $tabla_certificados = $wpdb->prefix . 'certificados_personalizados';
        
        $sql = "CREATE TABLE $tabla_certificados (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            nombre VARCHAR(255) NOT NULL,
            actividad VARCHAR(255) NOT NULL,
            fecha DATE NOT NULL,
            observaciones TEXT,
            estado VARCHAR(50) NOT NULL DEFAULT 'pendiente',
            pdf_path TEXT,
            codigo_unico VARCHAR(100) NOT NULL,
            notificado BOOLEAN NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY estado (estado),
            KEY codigo_unico (codigo_unico)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Agregar menús de administración
     */
    public function agregar_menus_admin() {
        // Obtener rol del usuario actual
        $user = wp_get_current_user();
        $user_roles = $user->roles;
        
        // Menú para colaboradores (contributor)
        if (in_array('contributor', $user_roles)) {
            add_menu_page(
                __('Mis Certificados', 'certificados-personalizados'),
                __('Mis Certificados', 'certificados-personalizados'),
                'read',
                'mis-certificados',
                array($this, 'mostrar_formulario_colaborador'),
                'dashicons-awards',
                30
            );
        }
        
        // Menú para administradores
        if (in_array('administrator', $user_roles)) {
            add_menu_page(
                __('Aprobación de Certificados', 'certificados-personalizados'),
                __('Aprobación de Certificados', 'certificados-personalizados'),
                'manage_options',
                'aprobacion-certificados',
                array($this, 'mostrar_aprobacion_certificados'),
                'dashicons-yes-alt',
                31
            );
        }
    }
    
    /**
     * Mostrar formulario de colaborador
     */
    public function mostrar_formulario_colaborador() {
        // Verificar permisos
        if (!current_user_can('read')) {
            wp_die(__('No tienes permisos para acceder a esta página.', 'certificados-personalizados'));
        }
        
        // Cargar vista
        include CERTIFICADOS_PERSONALIZADOS_PLUGIN_PATH . 'admin/formulario-colaborador.php';
    }
    
    /**
     * Mostrar página de aprobación
     */
    public function mostrar_aprobacion_certificados() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para acceder a esta página.', 'certificados-personalizados'));
        }
        
        // Cargar vista
        include CERTIFICADOS_PERSONALIZADOS_PLUGIN_PATH . 'admin/aprobacion-certificados.php';
    }
    
    /**
     * Registrar shortcode para mostrar certificados aprobados
     */
    public function registrar_shortcodes() {
        add_shortcode('certificados_aprobados', array($this, 'mostrar_certificados_aprobados'));
    }
    
    /**
     * Shortcode para mostrar certificados aprobados con buscador
     */
    public function mostrar_certificados_aprobados($atts) {
        // Procesar búsqueda si se envió
        $busqueda = isset($_GET['buscar_certificado']) ? sanitize_text_field($_GET['buscar_certificado']) : '';
        
        // Obtener certificados aprobados
        $certificados = CertificadosPersonalizadosBD::obtener_certificados_aprobados($busqueda);
        
        // Incluir estilos CSS
        wp_enqueue_style('certificados-public', plugin_dir_url(__FILE__) . 'public/css/certificados-public.css', array(), '1.0.0');
        
        // Incluir JavaScript
        wp_enqueue_script('certificados-public', plugin_dir_url(__FILE__) . 'public/js/certificados-public.js', array('jquery'), '1.0.0', true);
        
        // Iniciar buffer de salida
        ob_start();
        
        // Incluir template
        include CERTIFICADOS_PERSONALIZADOS_PLUGIN_PATH . 'public/template-certificados-public.php';
        
        // Retornar contenido
        return ob_get_clean();
    }
    
    /**
     * Procesar edición de certificado
     */
    public function procesar_edicion_certificado() {
        // Verificar nonce
        if (!isset($_POST['editar_certificado_nonce']) || 
            !wp_verify_nonce($_POST['editar_certificado_nonce'], 'editar_certificado')) {
            wp_die('Error de seguridad.');
        }
        
        // Verificar que el usuario esté logueado
        if (!is_user_logged_in()) {
            wp_die('Debes estar logueado para realizar esta acción.');
        }
        
        $certificado_id = intval($_POST['certificado_id']);
        
        // Obtener certificado verificando permisos y editabilidad
        $certificado = CertificadosPersonalizadosBD::obtener_certificado_para_edicion($certificado_id);
        
        if (!$certificado) {
            wp_die('Certificado no encontrado, no tienes permisos o no es editable.');
        }
        
        // Validar datos
        $nombre = sanitize_text_field($_POST['nombre']);
        $fecha_evento = sanitize_text_field($_POST['fecha_evento']);
        $tipo_actividad = sanitize_text_field($_POST['tipo_actividad']);
        $observaciones = sanitize_textarea_field($_POST['observaciones']);
        
        // Validaciones
        if (empty($nombre) || empty($fecha_evento) || empty($tipo_actividad)) {
            wp_die('Los campos Nombre, Fecha del evento y Tipo de actividad son obligatorios.');
        }
        
        // Validar fecha
        $fecha_actual = date('Y-m-d');
        if ($fecha_evento > $fecha_actual) {
            wp_die('La fecha del evento no puede ser futura.');
        }
        
        // Validar tipo de actividad
        $tipos_validos = array('curso', 'taller', 'seminario', 'conferencia', 'workshop', 'otro');
        if (!in_array($tipo_actividad, $tipos_validos)) {
            wp_die('Tipo de actividad no válido.');
        }
        
        // Preparar datos para actualización
        $datos_actualizados = array(
            'nombre' => $nombre,
            'actividad' => $tipo_actividad,
            'fecha' => $fecha_evento,
            'observaciones' => $observaciones,
            'updated_at' => current_time('mysql')
        );
        
        // Actualizar certificado
        $resultado = CertificadosPersonalizadosBD::actualizar_certificado($certificado_id, $datos_actualizados);
        
        if ($resultado) {
            // Debug: Verificar datos actualizados
            CertificadosPersonalizadosBD::debug_certificado($certificado_id);
            
            // Verificar que la actualización fue exitosa obteniendo los datos actualizados
            $certificado_actualizado = CertificadosPersonalizadosBD::obtener_certificado($certificado_id);
            
            // Forzar regeneración completa del PDF
            $pdf_regenerado = CertificadosPersonalizadosPDF::forzar_regeneracion_pdf($certificado_id);
            
            // Verificar que el PDF se actualizó correctamente
            $pdf_verificado = CertificadosPersonalizadosPDF::verificar_pdf_actualizado($certificado_id);
            
            if ($pdf_regenerado && $pdf_verificado) {
                $mensaje_texto = 'Certificado actualizado correctamente. PDF regenerado y verificado.';
            } elseif ($pdf_regenerado) {
                $mensaje_texto = 'Certificado actualizado correctamente. PDF regenerado (verificación pendiente).';
            } else {
                $mensaje_texto = 'Certificado actualizado correctamente. Error al regenerar PDF.';
            }
            
            // Redirigir con mensaje de éxito
            $url_redirect = admin_url('admin.php?page=mis-certificados&mensaje=exito&texto=' . urlencode($mensaje_texto));
            wp_redirect($url_redirect);
            exit;
        } else {
            wp_die('Error al actualizar el certificado.');
        }
    }
    
    /**
     * Procesar edición de certificado por administrador
     */
    public function procesar_edicion_certificado_admin() {
        // Verificar permisos de administrador
        if (!current_user_can('administrator')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }
        
        // Verificar nonce
        if (!isset($_POST['editar_certificado_admin_nonce']) || 
            !wp_verify_nonce($_POST['editar_certificado_admin_nonce'], 'editar_certificado_admin')) {
            wp_die('Error de seguridad.');
        }
        
        $certificado_id = intval($_POST['certificado_id']);
        
        // Obtener certificado para administrador (sin restricciones)
        $certificado = CertificadosPersonalizadosBD::obtener_certificado_para_edicion_admin($certificado_id);
        
        if (!$certificado) {
            wp_die('Certificado no encontrado.');
        }
        
        // Validar datos
        $nombre = sanitize_text_field($_POST['nombre']);
        $fecha_evento = sanitize_text_field($_POST['fecha_evento']);
        $tipo_actividad = sanitize_text_field($_POST['tipo_actividad']);
        $observaciones = sanitize_textarea_field($_POST['observaciones']);
        $estado = sanitize_text_field($_POST['estado']);
        
        // Validaciones
        if (empty($nombre) || empty($fecha_evento) || empty($tipo_actividad)) {
            wp_die('Los campos Nombre, Fecha del evento y Tipo de actividad son obligatorios.');
        }
        
        // Validar fecha
        $fecha_actual = date('Y-m-d');
        if ($fecha_evento > $fecha_actual) {
            wp_die('La fecha del evento no puede ser futura.');
        }
        
        // Validar tipo de actividad
        $tipos_validos = array('curso', 'taller', 'seminario', 'conferencia', 'workshop', 'otro');
        if (!in_array($tipo_actividad, $tipos_validos)) {
            wp_die('Tipo de actividad no válido.');
        }
        
        // Validar estado
        $estados_validos = array('pendiente', 'aprobado', 'rechazado');
        if (!in_array($estado, $estados_validos)) {
            wp_die('Estado no válido.');
        }
        
        // Preparar datos para actualización
        $datos_actualizados = array(
            'nombre' => $nombre,
            'actividad' => $tipo_actividad,
            'fecha' => $fecha_evento,
            'observaciones' => $observaciones,
            'estado' => $estado,
            'updated_at' => current_time('mysql')
        );
        
        // Actualizar certificado
        $resultado = CertificadosPersonalizadosBD::actualizar_certificado($certificado_id, $datos_actualizados);
        
        if ($resultado) {
            // Debug: Verificar datos actualizados
            CertificadosPersonalizadosBD::debug_certificado($certificado_id);
            
            // Forzar regeneración completa del PDF
            $pdf_regenerado = CertificadosPersonalizadosPDF::forzar_regeneracion_pdf($certificado_id);
            
            // Verificar que el PDF se actualizó correctamente
            $pdf_verificado = CertificadosPersonalizadosPDF::verificar_pdf_actualizado($certificado_id);
            
            if ($pdf_regenerado && $pdf_verificado) {
                $mensaje_texto = 'Certificado actualizado correctamente. PDF regenerado y verificado.';
            } elseif ($pdf_regenerado) {
                $mensaje_texto = 'Certificado actualizado correctamente. PDF regenerado (verificación pendiente).';
            } else {
                $mensaje_texto = 'Certificado actualizado correctamente. Error al regenerar PDF.';
            }
            
            // Redirigir con mensaje de éxito
            $url_redirect = admin_url('admin.php?page=aprobacion-certificados&mensaje=exito&texto=' . urlencode($mensaje_texto));
            wp_redirect($url_redirect);
            exit;
        } else {
            wp_die('Error al actualizar el certificado.');
        }
    }
}

// Inicializar plugin
new CertificadosPersonalizados(); 