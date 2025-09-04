<?php
/**
 * Script para eliminar todos los certificados de prueba
 * Ejecutar una sola vez desde el directorio del plugin
 */

// Verificar si estamos en WordPress
if (!defined('ABSPATH')) {
    // Intentar incluir WordPress desde diferentes ubicaciones
    $wp_config_paths = [
        '../../../wp-config.php',
        '../../../../wp-config.php',
        '../../../../../wp-config.php',
        dirname(__FILE__) . '/../../../wp-config.php',
        dirname(__FILE__) . '/../../../../wp-config.php'
    ];
    
    $wp_loaded = false;
    foreach ($wp_config_paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die('No se pudo encontrar wp-config.php. Asegúrate de que el archivo esté en el directorio correcto del plugin.');
    }
}

// Verificar que estamos en el contexto correcto
if (!defined('ABSPATH')) {
    die('Acceso directo no permitido');
}

// Verificar permisos de administrador
if (!current_user_can('administrator')) {
    die('Solo los administradores pueden ejecutar este script');
}

global $wpdb;
$tabla = $wpdb->prefix . 'certificados_personalizados';

echo "<h2>🧹 Limpiando Certificados de Prueba</h2>";

// Contar certificados antes de eliminar
$total_antes = $wpdb->get_var("SELECT COUNT(*) FROM $tabla");
echo "<p>📊 Total de certificados antes: <strong>$total_antes</strong></p>";

// Eliminar todos los certificados
$resultado = $wpdb->query("DELETE FROM $tabla");

if ($resultado !== false) {
    echo "<p>✅ <strong>Se eliminaron $resultado certificados de prueba</strong></p>";
    
    // Verificar que se eliminaron todos
    $total_despues = $wpdb->get_var("SELECT COUNT(*) FROM $tabla");
    echo "<p>📊 Total de certificados después: <strong>$total_despues</strong></p>";
    
    if ($total_despues == 0) {
        echo "<p>🎉 <strong>¡Base de datos limpia! Ya puedes crear nuevos certificados.</strong></p>";
    }
} else {
    echo "<p>❌ Error al eliminar los certificados</p>";
}

echo "<hr>";
echo "<p><a href='" . admin_url('admin.php?page=mis-certificados') . "'>← Volver a Mis Certificados</a></p>";
echo "<p><strong>⚠️ IMPORTANTE:</strong> Elimina este archivo después de usarlo por seguridad.</p>";
?>
