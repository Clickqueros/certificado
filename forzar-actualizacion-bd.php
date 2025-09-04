<?php
/**
 * Script para forzar la actualización de la base de datos
 * Ejecutar una sola vez desde el directorio del plugin
 */

// Incluir WordPress
require_once('../../../wp-config.php');

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

echo "<h2>🔧 Forzando Actualización de Base de Datos</h2>";

// Verificar si la tabla existe
$tabla_existe = $wpdb->get_var("SHOW TABLES LIKE '$tabla'");

if (!$tabla_existe) {
    echo "<p>❌ La tabla $tabla no existe. Activa el plugin primero.</p>";
    exit;
}

echo "<p>✅ Tabla encontrada: $tabla</p>";

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

echo "<h3>📝 Agregando columnas nuevas:</h3>";

// Agregar columnas que no existen
foreach ($columnas_nuevas as $columna => $tipo) {
    $columna_existe = $wpdb->get_var("SHOW COLUMNS FROM $tabla LIKE '$columna'");
    
    if (!$columna_existe) {
        $sql = "ALTER TABLE $tabla ADD COLUMN $columna $tipo";
        $resultado = $wpdb->query($sql);
        
        if ($resultado !== false) {
            echo "<p>✅ Columna '$columna' agregada correctamente.</p>";
        } else {
            echo "<p>❌ Error al agregar columna '$columna': " . $wpdb->last_error . "</p>";
        }
    } else {
        echo "<p>ℹ️ Columna '$columna' ya existe.</p>";
    }
}

echo "<h3>🗑️ Eliminando columnas antiguas:</h3>";

// Eliminar columnas antiguas si existen
$columnas_antiguas = array('nombre', 'fecha', 'observaciones');

foreach ($columnas_antiguas as $columna) {
    $columna_existe = $wpdb->get_var("SHOW COLUMNS FROM $tabla LIKE '$columna'");
    
    if ($columna_existe) {
        $sql = "ALTER TABLE $tabla DROP COLUMN $columna";
        $resultado = $wpdb->query($sql);
        
        if ($resultado !== false) {
            echo "<p>✅ Columna antigua '$columna' eliminada correctamente.</p>";
        } else {
            echo "<p>❌ Error al eliminar columna '$columna': " . $wpdb->last_error . "</p>";
        }
    } else {
        echo "<p>ℹ️ Columna antigua '$columna' no existe.</p>";
    }
}

echo "<h3>📊 Estado final de la tabla:</h3>";

// Mostrar estructura final de la tabla
$columnas = $wpdb->get_results("SHOW COLUMNS FROM $tabla");
echo "<ul>";
foreach ($columnas as $columna) {
    echo "<li><strong>" . $columna->Field . "</strong> - " . $columna->Type . "</li>";
}
echo "</ul>";

echo "<hr>";
echo "<p><strong>🎉 ¡Actualización completada!</strong></p>";
echo "<p>Ahora puedes crear certificados GLP sin errores.</p>";
echo "<p><a href='" . admin_url('admin.php?page=mis-certificados') . "'>← Volver a Mis Certificados</a></p>";
echo "<p><strong>⚠️ IMPORTANTE:</strong> Elimina este archivo después de usarlo por seguridad.</p>";
?>
