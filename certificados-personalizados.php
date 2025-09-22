<?php
/**
 * Plugin Name: Certificados Antecore
 * Plugin URI: https://github.com/Anetcore/certificados-antecore
 * Description: Plugin para gestión y aprobación de certificados internos para empleados
 * Version: 1.0.0
 * Author: Anetcore
 * Author URI: https://github.com/Anetcore
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: certificados-antecore
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
define('CERTIFICADOS_ANTECORE_VERSION', '1.0.0');
define('CERTIFICADOS_ANTECORE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CERTIFICADOS_ANTECORE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CERTIFICADOS_ANTECORE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Clase principal del plugin
 */
class CertificadosAntecore {
    
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
        
        // Hook para procesar carga masiva de Excel
        add_action('admin_post_procesar_excel_masivo', array($this, 'procesar_excel_masivo'));
        
        // Hook para descargar plantilla Excel
        add_action('admin_post_descargar_plantilla_excel', array($this, 'descargar_plantilla_excel'));
        
        // Hook para crear archivo de prueba
        add_action('admin_post_crear_archivo_prueba', array($this, 'crear_archivo_prueba'));
        
        // Hook para crear archivo simple
        add_action('admin_post_crear_archivo_simple', array($this, 'crear_archivo_simple'));
        
        // Cargar archivos necesarios
        $this->cargar_archivos();
    }
    
    /**
     * Cargar archivos del plugin
     */
    private function cargar_archivos() {
        try {
            // Verificar que los archivos existen antes de cargarlos
            $archivo_bd = CERTIFICADOS_ANTECORE_PLUGIN_PATH . 'includes/funciones-bd.php';
            $archivo_pdf = CERTIFICADOS_ANTECORE_PLUGIN_PATH . 'includes/funciones-pdf.php';
            $archivo_excel = CERTIFICADOS_ANTECORE_PLUGIN_PATH . 'includes/funciones-excel.php';
            
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
            
            // Cargar funciones de Excel si existe
            if (file_exists($archivo_excel)) {
                require_once $archivo_excel;
            }
            
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
        $tabla_nueva = $wpdb->prefix . 'certificados_antecore';
        $tabla_antigua = $wpdb->prefix . 'certificados_personalizados';
        
        // Verificar si la tabla nueva existe
        $tabla_nueva_existe = $wpdb->get_var("SHOW TABLES LIKE '$tabla_nueva'");
        
        // Verificar si la tabla antigua existe
        $tabla_antigua_existe = $wpdb->get_var("SHOW TABLES LIKE '$tabla_antigua'");
        
        // Si la tabla antigua existe pero la nueva no, renombrar
        if ($tabla_antigua_existe && !$tabla_nueva_existe) {
            $sql = "RENAME TABLE $tabla_antigua TO $tabla_nueva";
            $wpdb->query($sql);
            error_log("CertificadosAntecore: Tabla renombrada de '$tabla_antigua' a '$tabla_nueva'");
            $tabla_nueva_existe = true; // Actualizar flag
        }
        
        if ($tabla_nueva_existe) {
            // Verificar si falta alguna columna nueva
            $columna_capacidad = $wpdb->get_var("SHOW COLUMNS FROM $tabla_nueva LIKE 'capacidad_almacenamiento'");
            
            if (!$columna_capacidad) {
                // Si falta la columna capacidad_almacenamiento, actualizar toda la tabla
                $this->actualizar_tabla_existente();
                error_log("CertificadosAntecore: Base de datos actualizada automáticamente.");
            }
        }
    }
    
    /**
     * Actualizar tabla existente con nuevas columnas
     */
    private function actualizar_tabla_existente() {
        global $wpdb;
        $tabla_nueva = $wpdb->prefix . 'certificados_antecore';
        $tabla_antigua = $wpdb->prefix . 'certificados_personalizados';
        
        // Verificar si la tabla nueva existe
        $tabla_nueva_existe = $wpdb->get_var("SHOW TABLES LIKE '$tabla_nueva'");
        
        // Verificar si la tabla antigua existe
        $tabla_antigua_existe = $wpdb->get_var("SHOW TABLES LIKE '$tabla_antigua'");
        
        // Si la tabla antigua existe pero la nueva no, renombrar
        if ($tabla_antigua_existe && !$tabla_nueva_existe) {
            $sql = "RENAME TABLE $tabla_antigua TO $tabla_nueva";
            $wpdb->query($sql);
            error_log("CertificadosAntecore: Tabla renombrada de '$tabla_antigua' a '$tabla_nueva'");
        }
        
        // Verificar si la tabla nueva existe (después del renombrado)
        $tabla_existe = $wpdb->get_var("SHOW TABLES LIKE '$tabla_nueva'");
        
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
                $columna_existe = $wpdb->get_var("SHOW COLUMNS FROM $tabla_nueva LIKE '$columna'");
                
                if (!$columna_existe) {
                    $sql = "ALTER TABLE $tabla_nueva ADD COLUMN $columna $tipo";
                    $wpdb->query($sql);
                    error_log("CertificadosAntecore: Columna '$columna' agregada a la tabla.");
                }
            }
            
            // Eliminar columnas antiguas si existen
            $columnas_antiguas = array('nombre', 'fecha', 'observaciones');
            
            foreach ($columnas_antiguas as $columna) {
                $columna_existe = $wpdb->get_var("SHOW COLUMNS FROM $tabla_nueva LIKE '$columna'");
                
                if ($columna_existe) {
                    $sql = "ALTER TABLE $tabla_nueva DROP COLUMN $columna";
                    $wpdb->query($sql);
                    error_log("CertificadosAntecore: Columna antigua '$columna' eliminada de la tabla.");
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
        $certificado = CertificadosAntecoreBD::obtener_certificado($certificado_id);
        
        if (!$certificado || $certificado->user_id != $user_id) {
            wp_die('Certificado no encontrado o no tienes permisos.');
        }
        
        // Verificar que no haya sido notificado ya
        if ($certificado->notificado == 1) {
            wp_die('Este certificado ya fue enviado para aprobación.');
        }
        
        // Marcar como notificado (las notificaciones se envían automáticamente al crear el certificado)
        $marcado = CertificadosAntecoreBD::marcar_como_notificado($certificado_id);
        
        if ($marcado) {
            $mensaje = 'exito';
            $texto = 'Tu solicitud ha sido enviada al administrador.';
        } else {
            $mensaje = 'error';
            $texto = 'Error al procesar la solicitud. Inténtalo de nuevo.';
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
        $certificado = CertificadosAntecoreBD::obtener_certificado($certificado_id);
        
        if (!$certificado) {
            wp_die('Certificado no encontrado.');
        }
        
        if ($certificado->estado !== 'pendiente') {
            wp_die('Este certificado ya no está pendiente de aprobación.');
        }
        
        // Aprobar certificado
        $resultado = CertificadosAntecoreBD::cambiar_estado_certificado($certificado_id, 'aprobado');
        
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
        $certificado = CertificadosAntecoreBD::obtener_certificado($certificado_id);
        
        if (!$certificado) {
            wp_die('Certificado no encontrado.');
        }
        
        if ($certificado->estado !== 'pendiente') {
            wp_die('Este certificado ya no está pendiente de aprobación.');
        }
        
        // Rechazar certificado
        $resultado = CertificadosAntecoreBD::cambiar_estado_certificado($certificado_id, 'rechazado');
        
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
        $tabla_certificados = $wpdb->prefix . 'certificados_antecore';
        
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
            
            // Submenú de configuración de notificaciones
            add_submenu_page(
                'aprobacion-certificados',
                __('Configuración de Notificaciones', 'certificados-personalizados'),
                __('Configurar Notificaciones', 'certificados-personalizados'),
                'manage_options',
                'configuracion-notificaciones',
                array($this, 'mostrar_configuracion_notificaciones')
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
        include CERTIFICADOS_ANTECORE_PLUGIN_PATH . 'admin/formulario-colaborador.php';
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
        include CERTIFICADOS_ANTECORE_PLUGIN_PATH . 'admin/aprobacion-certificados.php';
    }
    
    /**
     * Mostrar configuración de notificaciones
     */
    public function mostrar_configuracion_notificaciones() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para acceder a esta página.', 'certificados-personalizados'));
        }
        
        // Cargar vista
        include CERTIFICADOS_ANTECORE_PLUGIN_PATH . 'admin/configuracion-notificaciones.php';
        ConfiguracionNotificaciones::mostrar_pagina_configuracion();
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
        $certificados = CertificadosAntecoreBD::obtener_certificados_aprobados($busqueda);
        
        // Incluir estilos CSS con versión dinámica para evitar cache
        $css_version = filemtime(CERTIFICADOS_ANTECORE_PLUGIN_PATH . 'public/css/certificados-public.css');
        wp_enqueue_style('certificados-public', plugin_dir_url(__FILE__) . 'public/css/certificados-public.css', array(), $css_version);
        
        // Incluir JavaScript con versión dinámica para evitar cache
        $js_version = filemtime(CERTIFICADOS_ANTECORE_PLUGIN_PATH . 'public/js/certificados-public.js');
        wp_enqueue_script('certificados-public', plugin_dir_url(__FILE__) . 'public/js/certificados-public.js', array('jquery'), $js_version, true);
        
        // Iniciar buffer de salida
        ob_start();
        
        // Incluir template
        include CERTIFICADOS_ANTECORE_PLUGIN_PATH . 'public/template-certificados-public.php';
        
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
        $certificado = CertificadosAntecoreBD::obtener_certificado_para_edicion($certificado_id);
        
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
        $resultado = CertificadosAntecoreBD::actualizar_certificado($certificado_id, $datos_actualizados);
        
        if ($resultado) {
            // Debug: Verificar datos actualizados
            CertificadosAntecoreBD::debug_certificado($certificado_id);
            
            // Verificar que la actualización fue exitosa obteniendo los datos actualizados
            $certificado_actualizado = CertificadosAntecoreBD::obtener_certificado($certificado_id);
            
            // Forzar regeneración completa del PDF
            $pdf_regenerado = CertificadosAntecorePDF::forzar_regeneracion_pdf($certificado_id);
            
            // Verificar que el PDF se actualizó correctamente
            $pdf_verificado = CertificadosAntecorePDF::verificar_pdf_actualizado($certificado_id);
            
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
        // Debug: Log para verificar que se está ejecutando
        error_log('CertificadosPersonalizados: Iniciando procesar_edicion_certificado_admin');
        
        // Verificar permisos de administrador
        if (!current_user_can('administrator')) {
            error_log('CertificadosPersonalizados: Error - Usuario no es administrador');
            wp_die('No tienes permisos para realizar esta acción.');
        }
        
        // Verificar nonce
        if (!isset($_POST['editar_certificado_admin_nonce']) || 
            !wp_verify_nonce($_POST['editar_certificado_admin_nonce'], 'editar_certificado_admin')) {
            error_log('CertificadosPersonalizados: Error - Nonce inválido');
            wp_die('Error de seguridad.');
        }
        
        $certificado_id = intval($_POST['certificado_id']);
        error_log('CertificadosPersonalizados: Certificado ID: ' . $certificado_id);
        
        // Obtener certificado para administrador (sin restricciones)
        $certificado = CertificadosAntecoreBD::obtener_certificado_para_edicion_admin($certificado_id);
        
        if (!$certificado) {
            error_log('CertificadosPersonalizados: Error - Certificado no encontrado para ID: ' . $certificado_id);
            wp_die('Certificado no encontrado.');
        }
        
        error_log('CertificadosPersonalizados: Certificado encontrado, continuando con validación');
        
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
            'estado' => $estado,
            'updated_at' => current_time('mysql')
        );
        
        // Actualizar certificado
        error_log('CertificadosPersonalizados: Intentando actualizar certificado con datos: ' . print_r($datos_actualizados, true));
        $resultado = CertificadosAntecoreBD::actualizar_certificado($certificado_id, $datos_actualizados);
        
        error_log('CertificadosPersonalizados: Resultado de actualización: ' . ($resultado ? 'ÉXITO' : 'FALLO'));
        
        if ($resultado) {
            // Debug: Verificar datos actualizados
            CertificadosAntecoreBD::debug_certificado($certificado_id);
            
            // Obtener certificado actualizado para regeneración
            $certificado_actualizado = CertificadosAntecoreBD::obtener_certificado($certificado_id);
            
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
            $pdf_regenerado = CertificadosAntecorePDF::forzar_regeneracion_pdf($certificado_id);
            
            // Verificar que el PDF se actualizó correctamente
            $pdf_verificado = CertificadosAntecorePDF::verificar_pdf_actualizado($certificado_id);
            
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
            $certificado_final = CertificadosAntecoreBD::obtener_certificado($certificado_id);
            if ($certificado_final && $certificado_final->pdf_path) {
                $timestamp = time();
                $random_suffix = substr(md5(uniqid()), 0, 8);
                
                // Remover cualquier timestamp existente y agregar uno completamente nuevo
                $url_base = preg_replace('/\?v=\d+.*/', '', $certificado_final->pdf_path);
                $url_actualizada = $url_base . '?v=' . $timestamp . '&r=' . $random_suffix;
                
                CertificadosAntecoreBD::actualizar_certificado($certificado_id, array(
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
        $certificado = CertificadosAntecoreBD::obtener_certificado($certificado_id);
        
        if (!$certificado) {
            wp_die('Certificado no encontrado.');
        }
        
        // Forzar regeneración completa del PDF
        $pdf_regenerado = CertificadosAntecorePDF::forzar_regeneracion_pdf($certificado_id);
        
        // Verificar que el PDF se actualizó correctamente
        $pdf_verificado = CertificadosAntecorePDF::verificar_pdf_actualizado($certificado_id);
        
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
        $certificado_actualizado = CertificadosAntecoreBD::obtener_certificado($certificado_id);
        if ($certificado_actualizado && $certificado_actualizado->pdf_path) {
            $timestamp = time();
            $url_actualizada = preg_replace('/\?v=\d+/', '?v=' . $timestamp, $certificado_actualizado->pdf_path);
            if ($url_actualizada !== $certificado_actualizado->pdf_path) {
                CertificadosAntecoreBD::actualizar_certificado($certificado_id, array(
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
        $certificados = CertificadosAntecoreBD::obtener_certificados_aprobados($busqueda);
        
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
            $certificado = CertificadosAntecoreBD::obtener_certificado($certificado_id);
            
            // Debug: Log para verificar el certificado
            error_log('CertificadosPersonalizados: Certificado ID: ' . $certificado_id);
            error_log('CertificadosPersonalizados: ¿Certificado existe? ' . ($certificado ? 'SÍ' : 'NO'));
            
            if (!$certificado) {
                wp_die('Certificado no encontrado.');
            }
            
            // Debug: Log del estado del certificado
            error_log('CertificadosPersonalizados: Estado del certificado: ' . $certificado->estado);
            
            // Verificar que el certificado esté aprobado
            if ($certificado->estado !== 'aprobado') {
                wp_die('Este certificado no está aprobado. Estado actual: ' . $certificado->estado);
            }
            
            // Generar o obtener el PDF
            $pdf_path = CertificadosAntecorePDF::generar_certificado_pdf($certificado_id);
            
            // Debug: Log para verificar la generación del PDF
            error_log('CertificadosPersonalizados: PDF generado - Ruta: ' . $pdf_path);
            
            if (!$pdf_path) {
                wp_die('Error al generar el PDF.');
            }
            
            // Obtener la ruta física del archivo (sin parámetros de query)
            $upload_dir = wp_upload_dir();
            $pdf_path_clean = strtok($pdf_path, '?'); // Remover parámetros de query
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $pdf_path_clean);
            
            // Debug: Log para verificar la ruta del archivo
            error_log('CertificadosPersonalizados: Ruta física del archivo: ' . $file_path);
            error_log('CertificadosPersonalizados: ¿Archivo existe? ' . (file_exists($file_path) ? 'SÍ' : 'NO'));
            
            if (!file_exists($file_path)) {
                wp_die('Archivo PDF no encontrado. Ruta: ' . $file_path);
            }
            
            // Enviar el PDF directamente
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="certificado-' . $certificado_id . '.pdf"');
            header('Content-Length: ' . filesize($file_path));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            readfile($file_path);
            exit;
        }
    }
    
    /**
     * Procesar carga masiva de certificados desde Excel
     */
    public function procesar_excel_masivo() {
        // Verificar nonce
        if (!isset($_POST['excel_masivo_nonce']) || 
            !wp_verify_nonce($_POST['excel_masivo_nonce'], 'procesar_excel_masivo')) {
            wp_die('Error de seguridad.');
        }
        
        // Verificar que el usuario esté logueado y sea contributor
        if (!is_user_logged_in()) {
            wp_die('Debes estar logueado para realizar esta acción.');
        }
        
        $user = wp_get_current_user();
        if (!in_array('contributor', $user->roles)) {
            wp_die('No tienes permisos para realizar esta acción.');
        }
        
        $user_id = get_current_user_id();
        
        // Verificar que se subió un archivo
        if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) {
            $redirect_url = admin_url('admin.php?page=mis-certificados&mensaje=error&texto=' . urlencode('Error al subir el archivo.'));
            wp_redirect($redirect_url);
            exit;
        }
        
        // Validar archivo
        $errores_validacion = CertificadosAntecoreExcel::validar_archivo_subido($_FILES['archivo_excel']);
        
        if (!empty($errores_validacion)) {
            $redirect_url = admin_url('admin.php?page=mis-certificados&mensaje=error&texto=' . urlencode(implode('; ', $errores_validacion)));
            wp_redirect($redirect_url);
            exit;
        }
        
        // Procesar archivo
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
        
        // Redirigir con mensaje
        $redirect_url = admin_url('admin.php?page=mis-certificados&mensaje=' . $mensaje_tipo . '&texto=' . urlencode($mensaje_texto));
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Descargar plantilla Excel/CSV
     */
    public function descargar_plantilla_excel() {
        // Verificar que el usuario esté logueado
        if (!is_user_logged_in()) {
            wp_die('Debes estar logueado para realizar esta acción.');
        }
        
        $user = wp_get_current_user();
        if (!in_array('contributor', $user->roles)) {
            wp_die('No tienes permisos para realizar esta acción.');
        }
        
        // Generar contenido de la plantilla
        $contenido = CertificadosAntecoreExcel::generar_plantilla();
        
        // Configurar headers para descarga
        $nombre_archivo = 'plantilla-certificados-antecore.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Agregar BOM para UTF-8 (para que Excel abra correctamente caracteres especiales)
        echo "\xEF\xBB\xBF";
        
        echo $contenido;
        exit;
    }
    
    /**
     * Crear archivo de prueba con datos de ejemplo
     */
    public function crear_archivo_prueba() {
        // Verificar que el usuario esté logueado
        if (!is_user_logged_in()) {
            wp_die('Debes estar logueado para realizar esta acción.');
        }
        
        $user = wp_get_current_user();
        if (!in_array('contributor', $user->roles)) {
            wp_die('No tienes permisos para realizar esta acción.');
        }
        
        // Crear contenido con datos de prueba - ORDEN IGUAL AL FORMULARIO MANUAL
        $encabezados = [
            'NOMBRE_INSTALACION',
            'DIRECCION_INSTALACION',
            'RAZON_SOCIAL',
            'NIT',
            'TIPO_CERTIFICADO',
            'NUMERO_CERTIFICADO',
            'FECHA_APROBACION',
            'CAPACIDAD_ALMACENAMIENTO',
            'NUMERO_TANQUES'
        ];
        
        $datos_prueba = [
            [
                'Estación de Servicio ABC',
                'Calle 123 #45-67, Bogotá',
                'Servicios ABC S.A.S.',
                '900123456-1',
                'PAGLP',
                '001',
                '15/12/2024',
                '10000',
                '5'
            ],
            [
                'Planta de Almacenamiento XYZ',
                'Carrera 456 #78-90, Medellín',
                'Almacenamiento XYZ Ltda.',
                '900987654-3',
                'TEGLP',
                '002',
                '20/12/2024',
                '25000',
                '8'
            ],
            [
                'Distribuidora GLP Central',
                'Avenida 789 #12-34, Cali',
                'Distribuidora Central S.A.S.',
                '900555666-7',
                'DEGLP',
                '003',
                '25/12/2024',
                '15000',
                '3'
            ]
        ];
        
        // Crear contenido CSV con BOM para UTF-8
        $contenido = "\xEF\xBB\xBF"; // BOM para UTF-8
        
        // Función para escapar CSV
        $escapar_csv = function($datos) {
            $resultado = [];
            foreach ($datos as $dato) {
                // Escapar comillas dobles y envolver en comillas si contiene comas, comillas o saltos de línea
                if (strpos($dato, ',') !== false || strpos($dato, '"') !== false || strpos($dato, "\n") !== false) {
                    $dato = '"' . str_replace('"', '""', $dato) . '"';
                }
                $resultado[] = $dato;
            }
            return implode(',', $resultado);
        };
        
        // Agregar encabezados
        $contenido .= $escapar_csv($encabezados) . "\n";
        
        // Agregar datos de prueba
        foreach ($datos_prueba as $fila) {
            $contenido .= $escapar_csv($fila) . "\n";
        }
        
        // Configurar headers para descarga
        $nombre_archivo = 'archivo-prueba-certificados.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $contenido;
        exit;
    }
    
    /**
     * Crear archivo simple sin escape complejo
     */
    public function crear_archivo_simple() {
        // Verificar que el usuario esté logueado
        if (!is_user_logged_in()) {
            wp_die('Debes estar logueado para realizar esta acción.');
        }
        
        $user = wp_get_current_user();
        if (!in_array('contributor', $user->roles)) {
            wp_die('No tienes permisos para realizar esta acción.');
        }
        
        // Crear contenido simple - ORDEN IGUAL AL FORMULARIO MANUAL
        $contenido = "NOMBRE_INSTALACION,DIRECCION_INSTALACION,RAZON_SOCIAL,NIT,TIPO_CERTIFICADO,NUMERO_CERTIFICADO,FECHA_APROBACION,CAPACIDAD_ALMACENAMIENTO,NUMERO_TANQUES\n";
        $contenido .= "Estación de Servicio ABC,Calle 123 #45-67 Bogotá,Servicios ABC S.A.S.,900123456-1,PAGLP,001,15/12/2024,10000,5\n";
        $contenido .= "Planta de Almacenamiento XYZ,Carrera 456 #78-90 Medellín,Almacenamiento XYZ Ltda.,900987654-3,TEGLP,002,20/12/2024,25000,8\n";
        $contenido .= "Distribuidora GLP Central,Avenida 789 #12-34 Cali,Distribuidora Central S.A.S.,900555666-7,DEGLP,003,25/12/2024,15000,3\n";
        
        // Configurar headers para descarga
        $nombre_archivo = 'archivo-simple-certificados.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $contenido;
        exit;
    }
}

// Inicializar plugin
new CertificadosAntecore(); 