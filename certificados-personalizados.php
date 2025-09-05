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
        
        // Hook para regenerar PDF desde panel de administración
        add_action('admin_post_regenerar_pdf_admin', array($this, 'procesar_regenerar_pdf_admin'));
        
        // Hook para interceptar acceso a PDFs y agregar headers de no-caché
        add_action('init', array($this, 'interceptar_acceso_pdf'));
        
        // Hook para búsqueda AJAX de certificados
        add_action('wp_ajax_buscar_certificados', array($this, 'buscar_certificados_ajax'));
        add_action('wp_ajax_nopriv_buscar_certificados', array($this, 'buscar_certificados_ajax'));
        
        // Hook para manejar ver_pdf en el frontend
        add_action('init', array($this, 'manejar_ver_pdf'));
        
        // Cargar archivos necesarios
        $this->cargar_archivos();
    }
    
    /**
     * Cargar archivos del plugin
     */
    private function cargar_archivos() {
        try {
            // Verificar que los archivos existen antes de cargarlos
            $archivo_bd = CERTIFICADOS_PERSONALIZADOS_PLUGIN_PATH . 'includes/funciones-bd.php';
            $archivo_pdf = CERTIFICADOS_PERSONALIZADOS_PLUGIN_PATH . 'includes/funciones-pdf.php';
            
            if (!file_exists($archivo_bd)) {
                error_log('CertificadosPersonalizados: Archivo funciones-bd.php no encontrado en: ' . $archivo_bd);
                return;
            }
            
            if (!file_exists($archivo_pdf)) {
                error_log('CertificadosPersonalizados: Archivo funciones-pdf.php no encontrado en: ' . $archivo_pdf);
                return;
            }
            
            // Cargar funciones de base de datos
            require_once $archivo_bd;
            
            // Cargar funciones de PDF
            require_once $archivo_pdf;
            
        } catch (Exception $e) {
            error_log('CertificadosPersonalizados: Error al cargar archivos: ' . $e->getMessage());
        }
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
        
        // Verificar y actualizar base de datos si es necesario
        $this->verificar_y_actualizar_bd();
    }
    
    /**
     * Activar plugin
     */
    public function activar_plugin() {
        // Crear tabla de certificados
        $this->crear_tabla_certificados();
        
        // Actualizar tabla existente si es necesario
        $this->actualizar_tabla_existente();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Forzar actualización de tabla (para desarrollo)
     */
    public function forzar_actualizacion_tabla() {
        $this->crear_tabla_certificados();
        $this->actualizar_tabla_existente();
    }
    
    /**
     * Verificar y actualizar base de datos si es necesario
     */
    private function verificar_y_actualizar_bd() {
        global $wpdb;
        $tabla = $wpdb->prefix . 'certificados_personalizados';
        
        // Verificar si la tabla existe
        $tabla_existe = $wpdb->get_var("SHOW TABLES LIKE '$tabla'");
        
        if ($tabla_existe) {
            // Verificar si falta alguna columna nueva
            $columna_capacidad = $wpdb->get_var("SHOW COLUMNS FROM $tabla LIKE 'capacidad_almacenamiento'");
            
            if (!$columna_capacidad) {
                // Si falta la columna capacidad_almacenamiento, actualizar toda la tabla
                $this->actualizar_tabla_existente();
                error_log("CertificadosPersonalizados: Base de datos actualizada automáticamente.");
            }
        }
    }
    
    /**
     * Actualizar tabla existente con nuevas columnas
     */
    private function actualizar_tabla_existente() {
        global $wpdb;
        $tabla = $wpdb->prefix . 'certificados_personalizados';
        
        // Verificar si la tabla existe
        $tabla_existe = $wpdb->get_var("SHOW TABLES LIKE '$tabla'");
        
        if ($tabla_existe) {
            // Lista de columnas nuevas a agregar
            $columnas_nuevas = array(
                'capacidad_almacenamiento' => 'VARCHAR(50)',
                'numero_tanques' => 'INT',
                'nombre_instalacion' => 'VARCHAR(255)',
                'direccion_instalacion' => 'TEXT',
                'razon_social' => 'VARCHAR(255)',
                'nit' => 'VARCHAR(50)',
                'tipo_certificado' => 'VARCHAR(10)',
                'numero_certificado' => 'INT',
                'fecha_aprobacion' => 'DATE'
            );
            
            // Agregar columnas que no existen
            foreach ($columnas_nuevas as $columna => $tipo) {
                $columna_existe = $wpdb->get_var("SHOW COLUMNS FROM $tabla LIKE '$columna'");
                
                if (!$columna_existe) {
                    $sql = "ALTER TABLE $tabla ADD COLUMN $columna $tipo";
                    $wpdb->query($sql);
                    error_log("CertificadosPersonalizados: Columna '$columna' agregada a la tabla.");
                }
            }
            
            // Eliminar columnas antiguas si existen
            $columnas_antiguas = array('nombre', 'fecha', 'observaciones');
            
            foreach ($columnas_antiguas as $columna) {
                $columna_existe = $wpdb->get_var("SHOW COLUMNS FROM $tabla LIKE '$columna'");
                
                if ($columna_existe) {
                    $sql = "ALTER TABLE $tabla DROP COLUMN $columna";
                    $wpdb->query($sql);
                    error_log("CertificadosPersonalizados: Columna antigua '$columna' eliminada de la tabla.");
                }
            }
        }
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
            actividad VARCHAR(255) NOT NULL,
            estado VARCHAR(50) NOT NULL DEFAULT 'pendiente',
            pdf_path TEXT,
            codigo_unico VARCHAR(100) NOT NULL,
            notificado BOOLEAN NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            -- Campos para certificados de GLP
            capacidad_almacenamiento VARCHAR(50),
            numero_tanques INT,
            nombre_instalacion VARCHAR(255),
            direccion_instalacion TEXT,
            razon_social VARCHAR(255),
            nit VARCHAR(50),
            tipo_certificado VARCHAR(10),
            numero_certificado INT,
            fecha_aprobacion DATE,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY estado (estado),
            KEY codigo_unico (codigo_unico),
            KEY tipo_certificado (tipo_certificado)
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
            
            // Submenú para limpiar certificados
            add_submenu_page(
                'aprobacion-certificados',
                __('Limpiar Certificados', 'certificados-personalizados'),
                __('Limpiar Certificados', 'certificados-personalizados'),
                'manage_options',
                'limpiar-certificados',
                array($this, 'mostrar_limpiar_certificados')
            );
            
            // Submenú para actualizar base de datos
            add_submenu_page(
                'aprobacion-certificados',
                __('Actualizar Base de Datos', 'certificados-personalizados'),
                __('Actualizar BD', 'certificados-personalizados'),
                'manage_options',
                'actualizar-base-datos',
                array($this, 'mostrar_actualizar_base_datos')
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
     * Mostrar página de limpieza de certificados
     */
    public function mostrar_limpiar_certificados() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para acceder a esta página.', 'certificados-personalizados'));
        }
        
        // Cargar vista
        include CERTIFICADOS_PERSONALIZADOS_PLUGIN_PATH . 'admin/limpiar-certificados.php';
    }
    
    /**
     * Mostrar página de actualización de base de datos
     */
    public function mostrar_actualizar_base_datos() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para acceder a esta página.', 'certificados-personalizados'));
        }
        
        // Cargar vista
        include CERTIFICADOS_PERSONALIZADOS_PLUGIN_PATH . 'admin/actualizar-base-datos.php';
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
                wp_die('El campo ' . ucfirst(str_replace('_', ' ', $campo)) . ' es obligatorio.');
            }
        }
        
        // Validar fecha de aprobación
        $fecha_actual = date('Y-m-d');
        if ($fecha_aprobacion > $fecha_actual) {
            wp_die('La fecha de aprobación no puede ser futura.');
        }
        
        // Validar tipo de certificado
        $tipos_validos = array('PAGLP', 'TEGLP', 'PEGLP', 'DEGLP', 'PVGLP');
        if (!in_array($tipo_certificado, $tipos_validos)) {
            wp_die('Tipo de certificado no válido.');
        }
        
        // Validar que el número de tanques sea positivo
        if ($numero_tanques <= 0) {
            wp_die('El número de tanques debe ser mayor a 0.');
        }
        
        // Validar que el número de certificado sea positivo
        if ($numero_certificado <= 0) {
            wp_die('El número de certificado debe ser mayor a 0.');
        }
        
        // Preparar datos para actualización
        $datos_actualizados = array(
            'actividad' => $tipo_certificado, // Usamos tipo_certificado como actividad
            'capacidad_almacenamiento' => $capacidad_almacenamiento,
            'numero_tanques' => $numero_tanques,
            'nombre_instalacion' => $nombre_instalacion,
            'direccion_instalacion' => $direccion_instalacion,
            'razon_social' => $razon_social,
            'nit' => $nit,
            'tipo_certificado' => $tipo_certificado,
            'numero_certificado' => $numero_certificado,
            'fecha_aprobacion' => $fecha_aprobacion,
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
        
        // Validar datos básicos
        $estado = sanitize_text_field($_POST['estado']);
        
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
                wp_die('El campo ' . ucfirst(str_replace('_', ' ', $campo)) . ' es obligatorio.');
            }
        }
        
        // Validar fecha de aprobación
        $fecha_actual = date('Y-m-d');
        if ($fecha_aprobacion > $fecha_actual) {
            wp_die('La fecha de aprobación no puede ser futura.');
        }
        
        // Validar tipo de certificado
        $tipos_validos = array('PAGLP', 'TEGLP', 'PEGLP', 'DEGLP', 'PVGLP');
        if (!in_array($tipo_certificado, $tipos_validos)) {
            wp_die('Tipo de certificado no válido.');
        }
        
        // Validar que el número de tanques sea positivo
        if ($numero_tanques <= 0) {
            wp_die('El número de tanques debe ser mayor a 0.');
        }
        
        // Validar que el número de certificado sea positivo
        if ($numero_certificado <= 0) {
            wp_die('El número de certificado debe ser mayor a 0.');
        }
        
        // Validar estado
        $estados_validos = array('pendiente', 'aprobado', 'rechazado');
        if (!in_array($estado, $estados_validos)) {
            wp_die('Estado no válido.');
        }
        
        // Preparar datos para actualización
        $datos_actualizados = array(
            'nombre' => $nombre,
            'actividad' => $tipo_certificado, // Usamos tipo_certificado como actividad
            'fecha' => $fecha_evento,
            'observaciones' => $observaciones,
            'capacidad_almacenamiento' => $capacidad_almacenamiento,
            'numero_tanques' => $numero_tanques,
            'nombre_instalacion' => $nombre_instalacion,
            'direccion_instalacion' => $direccion_instalacion,
            'razon_social' => $razon_social,
            'nit' => $nit,
            'tipo_certificado' => $tipo_certificado,
            'numero_certificado' => $numero_certificado,
            'fecha_aprobacion' => $fecha_aprobacion,
            'estado' => $estado,
            'updated_at' => current_time('mysql')
        );
        
        // Actualizar certificado
        $resultado = CertificadosPersonalizadosBD::actualizar_certificado($certificado_id, $datos_actualizados);
        
        if ($resultado) {
            // Debug: Verificar datos actualizados
            CertificadosPersonalizadosBD::debug_certificado($certificado_id);
            
            // Obtener certificado actualizado para regeneración
            $certificado_actualizado = CertificadosPersonalizadosBD::obtener_certificado($certificado_id);
            
            // Eliminar archivos PDF existentes antes de regenerar
            if ($certificado_actualizado && $certificado_actualizado->pdf_path) {
                $upload_dir = wp_upload_dir();
                $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $certificado_actualizado->pdf_path);
                
                // Eliminar archivo PDF existente
                if (file_exists($local_path)) {
                    unlink($local_path);
                    error_log('CertificadosPersonalizados: Archivo PDF eliminado antes de regenerar - ' . $local_path);
                }
                
                // Eliminar archivo HTML si existe
                $html_path = str_replace('.pdf', '.html', $local_path);
                if (file_exists($html_path)) {
                    unlink($html_path);
                    error_log('CertificadosPersonalizados: Archivo HTML eliminado antes de regenerar - ' . $html_path);
                }
            }
            
            // Forzar regeneración completa del PDF
            $pdf_regenerado = CertificadosPersonalizadosPDF::forzar_regeneracion_pdf($certificado_id);
            
            // Verificar que el PDF se actualizó correctamente
            $pdf_verificado = CertificadosPersonalizadosPDF::verificar_pdf_actualizado($certificado_id);
            
            // Limpiar caché adicional para el panel de administración
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            
            // Limpiar transients específicos
            if (function_exists('delete_transient')) {
                delete_transient('certificado_pdf_' . $certificado_id);
                delete_transient('certificado_url_' . $certificado_id);
            }
            
            // Forzar actualización de la URL del PDF en la base de datos con timestamp completamente nuevo
            $certificado_final = CertificadosPersonalizadosBD::obtener_certificado($certificado_id);
            if ($certificado_final && $certificado_final->pdf_path) {
                $timestamp = time();
                $random_suffix = substr(md5(uniqid()), 0, 8);
                
                // Remover cualquier timestamp existente y agregar uno completamente nuevo
                $url_base = preg_replace('/\?v=\d+.*/', '', $certificado_final->pdf_path);
                $url_actualizada = $url_base . '?v=' . $timestamp . '&r=' . $random_suffix;
                
                CertificadosPersonalizadosBD::actualizar_certificado($certificado_id, array(
                    'pdf_path' => $url_actualizada
                ));
                
                error_log('CertificadosPersonalizados: URL del PDF actualizada para admin - ID: ' . $certificado_id . ' - Nueva URL: ' . $url_actualizada);
            }
            
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
    
    /**
     * Procesar regeneración de PDF desde panel de administración
     */
    public function procesar_regenerar_pdf_admin() {
        // Verificar permisos de administrador
        if (!current_user_can('administrator')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }
        
        // Verificar nonce
        if (!isset($_POST['regenerar_pdf_admin_nonce']) || 
            !wp_verify_nonce($_POST['regenerar_pdf_admin_nonce'], 'regenerar_pdf_admin')) {
            wp_die('Error de seguridad.');
        }
        
        $certificado_id = intval($_POST['certificado_id']);
        
        // Verificar que el certificado existe
        $certificado = CertificadosPersonalizadosBD::obtener_certificado($certificado_id);
        
        if (!$certificado) {
            wp_die('Certificado no encontrado.');
        }
        
        // Forzar regeneración completa del PDF
        $pdf_regenerado = CertificadosPersonalizadosPDF::forzar_regeneracion_pdf($certificado_id);
        
        // Verificar que el PDF se actualizó correctamente
        $pdf_verificado = CertificadosPersonalizadosPDF::verificar_pdf_actualizado($certificado_id);
        
        // Limpiar caché adicional
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Limpiar transients específicos
        if (function_exists('delete_transient')) {
            delete_transient('certificado_pdf_' . $certificado_id);
            delete_transient('certificado_url_' . $certificado_id);
        }
        
        // Forzar actualización de la URL del PDF en la base de datos
        $certificado_actualizado = CertificadosPersonalizadosBD::obtener_certificado($certificado_id);
        if ($certificado_actualizado && $certificado_actualizado->pdf_path) {
            $timestamp = time();
            $url_actualizada = preg_replace('/\?v=\d+/', '?v=' . $timestamp, $certificado_actualizado->pdf_path);
            if ($url_actualizada !== $certificado_actualizado->pdf_path) {
                CertificadosPersonalizadosBD::actualizar_certificado($certificado_id, array(
                    'pdf_path' => $url_actualizada
                ));
            }
        }
        
        if ($pdf_regenerado && $pdf_verificado) {
            $mensaje_texto = 'PDF regenerado correctamente y verificado.';
        } elseif ($pdf_regenerado) {
            $mensaje_texto = 'PDF regenerado correctamente (verificación pendiente).';
        } else {
            $mensaje_texto = 'Error al regenerar el PDF.';
        }
        
        // Redirigir con mensaje de éxito
        $url_redirect = admin_url('admin.php?page=aprobacion-certificados&mensaje=exito&texto=' . urlencode($mensaje_texto));
        wp_redirect($url_redirect);
        exit;
    }

    /**
     * Interceptar acceso a PDFs para agregar headers de no-caché
     */
    public function interceptar_acceso_pdf() {
        // No ejecutar durante la instalación o activación del plugin
        if (is_admin() || defined('WP_INSTALLING') || defined('WP_ACTIVATING')) {
            return;
        }

        // Verificar que las variables del servidor estén disponibles
        if (!isset($_SERVER['HTTP_HOST']) || !isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        try {
            // Obtener la URL actual de forma segura
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
            $current_url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

            // Verificar si la URL termina con .pdf
            if (pathinfo($current_url, PATHINFO_EXTENSION) === 'pdf') {
                // Agregar headers de no-caché solo si no se han enviado ya
                if (!headers_sent()) {
                    header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
                    header("Pragma: no-cache"); // HTTP 1.0
                    header("Expires: 0"); // Proxies
                }
            }
        } catch (Exception $e) {
            error_log('CertificadosPersonalizados: Error en interceptar_acceso_pdf: ' . $e->getMessage());
        }
    }
    
    /**
     * Búsqueda AJAX de certificados
     */
    public function buscar_certificados_ajax() {
        // Verificar nonce para seguridad
        if (!wp_verify_nonce($_POST['nonce'], 'buscar_certificados_nonce')) {
            wp_die('Error de seguridad');
        }
        
        $busqueda = sanitize_text_field($_POST['busqueda']);
        
        // Buscar certificados aprobados (con o sin búsqueda)
        $certificados = CertificadosPersonalizadosBD::obtener_certificados_aprobados($busqueda);
        
        // Debug: Log para verificar qué se está obteniendo
        error_log('CertificadosPersonalizados: Búsqueda: "' . $busqueda . '", Resultados: ' . count($certificados));
        
        if (empty($certificados)) {
            wp_send_json_success(array(
                'encontrados' => false,
                'mensaje' => 'No se encontraron certificados con ese criterio de búsqueda.',
                'debug' => 'Búsqueda: "' . $busqueda . '", Total encontrados: 0'
            ));
            return;
        }
        
        // Preparar resultados
        $resultados = array();
        foreach ($certificados as $certificado) {
            $resultados[] = array(
                'id' => $certificado->id,
                'nombre_instalacion' => $certificado->nombre_instalacion,
                'nit' => $certificado->nit,
                'codigo_unico' => $certificado->codigo_unico,
                'fecha_aprobacion' => date('d/m/Y', strtotime($certificado->fecha_aprobacion))
            );
        }
        
        wp_send_json_success(array(
            'encontrados' => true,
            'resultados' => $resultados,
            'total' => count($resultados)
        ));
    }
    
    /**
     * Manejar ver_pdf en el frontend
     */
    public function manejar_ver_pdf() {
        if (isset($_GET['ver_pdf']) && is_numeric($_GET['ver_pdf'])) {
            $certificado_id = intval($_GET['ver_pdf']);
            
            // Obtener el certificado
            $certificado = CertificadosPersonalizadosBD::obtener_certificado($certificado_id);
            
            if (!$certificado) {
                wp_die('Certificado no encontrado.');
            }
            
            // Verificar que el certificado esté aprobado
            if ($certificado->estado !== 'aprobado') {
                wp_die('Este certificado no está aprobado.');
            }
            
            // Generar o obtener el PDF
            $pdf_path = CertificadosPersonalizadosPDF::generar_pdf($certificado_id);
            
            if (!$pdf_path) {
                wp_die('Error al generar el PDF.');
            }
            
            // Redirigir al PDF
            wp_redirect($pdf_path);
            exit;
        }
    }
}

// Inicializar plugin
new CertificadosPersonalizados(); 