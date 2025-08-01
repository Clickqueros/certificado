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
        
        // Cargar archivos necesarios
        $this->cargar_archivos();
    }
    
    /**
     * Cargar archivos del plugin
     */
    private function cargar_archivos() {
        // Cargar funciones de base de datos
        require_once CERTIFICADOS_PERSONALIZADOS_PLUGIN_PATH . 'includes/funciones-bd.php';
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
}

// Inicializar plugin
new CertificadosPersonalizados(); 