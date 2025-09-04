<?php
/**
 * Script simple para actualizar la base de datos
 * Ejecutar desde el navegador
 */

// Incluir WordPress
require_once('../../../wp-config.php');

// Verificar permisos
if (!current_user_can('administrator')) {
    die('Solo administradores pueden ejecutar este script');
}

global $wpdb;
$tabla = $wpdb->prefix . 'certificados_personalizados';

echo "<h1>🔧 Actualizando Base de Datos</h1>";

// Verificar si la tabla existe
$tabla_existe = $wpdb->get_var("SHOW TABLES LIKE '$tabla'");

if (!$tabla_existe) {
    echo "<p>❌ La tabla no existe. Activa el plugin primero.</p>";
    exit;
}

echo "<p>✅ Tabla encontrada: $tabla</p>";

// Columnas a agregar
$columnas = [
    'capacidad_almacenamiento' => 'VARCHAR(50)',
    'numero_tanques' => 'INT',
    'nombre_instalacion' => 'VARCHAR(255)',
    'direccion_instalacion' => 'TEXT',
    'razon_social' => 'VARCHAR(255)',
    'nit' => 'VARCHAR(50)',
    'tipo_certificado' => 'VARCHAR(10)',
    'numero_certificado' => 'INT',
    'fecha_aprobacion' => 'DATE'
];

echo "<h2>Agregando columnas:</h2>";

foreach ($columnas as $columna => $tipo) {
    $existe = $wpdb->get_var("SHOW COLUMNS FROM $tabla LIKE '$columna'");
    
    if (!$existe) {
        $sql = "ALTER TABLE $tabla ADD COLUMN $columna $tipo";
        $resultado = $wpdb->query($sql);
        
        if ($resultado !== false) {
            echo "<p>✅ $columna agregada</p>";
        } else {
            echo "<p>❌ Error en $columna: " . $wpdb->last_error . "</p>";
        }
    } else {
        echo "<p>ℹ️ $columna ya existe</p>";
    }
}

echo "<h2>Eliminando columnas antiguas:</h2>";

$columnas_antiguas = ['nombre', 'fecha', 'observaciones'];

foreach ($columnas_antiguas as $columna) {
    $existe = $wpdb->get_var("SHOW COLUMNS FROM $tabla LIKE '$columna'");
    
    if ($existe) {
        $sql = "ALTER TABLE $tabla DROP COLUMN $columna";
        $resultado = $wpdb->query($sql);
        
        if ($resultado !== false) {
            echo "<p>✅ $columna eliminada</p>";
        } else {
            echo "<p>❌ Error eliminando $columna: " . $wpdb->last_error . "</p>";
        }
    } else {
        echo "<p>ℹ️ $columna no existe</p>";
    }
}

echo "<hr>";
echo "<h2>🎉 ¡Actualización completada!</h2>";
echo "<p>Ahora puedes crear certificados sin errores.</p>";
echo "<p><a href='" . admin_url('admin.php?page=mis-certificados') . "'>← Ir a Mis Certificados</a></p>";
echo "<p><strong>⚠️ Elimina este archivo después de usarlo.</strong></p>";
?>
