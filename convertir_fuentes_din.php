<?php
/**
 * Script para convertir fuentes DIN Pro a formato TCPDF
 * 
 * Ejecutar este script una sola vez para convertir las fuentes .otf a .php y .z
 * 
 * Uso:
 * - Desde WordPress: Acceder a /wp-admin/admin.php?page=convertir-fuentes-din
 * - Desde línea de comandos: php convertir_fuentes_din.php
 */

// Si se ejecuta desde WordPress
if (defined('ABSPATH')) {
    // Verificar permisos de administrador
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para ejecutar este script.');
    }
    
    // Cargar WordPress
    require_once plugin_dir_path(__FILE__) . 'includes/libs/tcpdf/tcpdf.php';
} else {
    // Si se ejecuta directamente desde línea de comandos
    // Ajustar la ruta según tu instalación
    require_once __DIR__ . '/includes/libs/tcpdf/tcpdf.php';
}

// Verificar que TCPDF_FONTS esté disponible
if (!class_exists('TCPDF_FONTS')) {
    die('ERROR: TCPDF_FONTS no está disponible. Verifica que TCPDF esté correctamente instalado.');
}

// Rutas
$fonts_dir = __DIR__ . '/includes/libs/tcpdf/fonts/';
$source_fonts_dir = __DIR__ . '/templates/fonts/';

// Asegurar que los directorios existen
if (!file_exists($fonts_dir)) {
    mkdir($fonts_dir, 0755, true);
}

if (!file_exists($source_fonts_dir)) {
    die("ERROR: Directorio de fuentes fuente no encontrado: $source_fonts_dir\n");
}

// Definir K_PATH_FONTS si no está definido
if (!defined('K_PATH_FONTS')) {
    define('K_PATH_FONTS', $fonts_dir);
}

echo "=== Conversión de Fuentes DIN Pro para TCPDF ===\n\n";
echo "Directorio de fuentes fuente: $source_fonts_dir\n";
echo "Directorio de destino: $fonts_dir\n\n";

// Función para convertir una fuente
function convertir_fuente($archivo_fuente, $nombre_display) {
    global $fonts_dir, $source_fonts_dir;
    
    $ruta_completa = $source_fonts_dir . $archivo_fuente;
    
    if (!file_exists($ruta_completa)) {
        echo "ERROR: Archivo no encontrado: $ruta_completa\n";
        return false;
    }
    
    echo "Convirtiendo $nombre_display...\n";
    echo "  Archivo fuente: $ruta_completa\n";
    echo "  Tamaño: " . filesize($ruta_completa) . " bytes\n";
    
    // Verificar permisos del directorio de destino
    if (!is_writable($fonts_dir)) {
        echo "  ERROR: El directorio de destino no tiene permisos de escritura: $fonts_dir\n";
        echo "  Permisos actuales: " . substr(sprintf('%o', fileperms($fonts_dir)), -4) . "\n";
        return false;
    }
    
    // Convertir la fuente
    try {
        $font_name = TCPDF_FONTS::addTTFfont($ruta_completa, 'TrueTypeUnicode', '', 32, $fonts_dir);
        
        if ($font_name !== false && !empty($font_name)) {
            echo "  ✓ Convertido exitosamente como: $font_name\n";
            
            // Verificar que los archivos se crearon
            $php_file = $fonts_dir . $font_name . '.php';
            $z_file = $fonts_dir . $font_name . '.z';
            
            if (file_exists($php_file) && file_exists($z_file)) {
                echo "  ✓ Archivos verificados:\n";
                echo "    - $font_name.php (" . filesize($php_file) . " bytes)\n";
                echo "    - $font_name.z (" . filesize($z_file) . " bytes)\n";
                return $font_name;
            } else {
                echo "  ADVERTENCIA: Los archivos .php y .z no se encontraron después de la conversión\n";
                echo "    PHP existe: " . (file_exists($php_file) ? 'SI' : 'NO') . "\n";
                echo "    Z existe: " . (file_exists($z_file) ? 'SI' : 'NO') . "\n";
                return false;
            }
        } else {
            echo "  ERROR: La conversión falló. addTTFfont retornó: " . var_export($font_name, true) . "\n";
            return false;
        }
    } catch (Exception $e) {
        echo "  ERROR: Excepción durante la conversión: " . $e->getMessage() . "\n";
        return false;
    }
}

// Convertir DIN Pro Regular
echo "1. DIN Pro Regular\n";
echo str_repeat('-', 50) . "\n";
$dinpro_regular = convertir_fuente('dinpro.otf', 'DIN Pro Regular');

echo "\n";

// Convertir DIN Pro Bold
echo "2. DIN Pro Bold\n";
echo str_repeat('-', 50) . "\n";
$dinpro_bold = convertir_fuente('dinpro_bold.otf', 'DIN Pro Bold');

echo "\n";
echo str_repeat('=', 50) . "\n";
echo "RESUMEN:\n";
echo str_repeat('=', 50) . "\n";

if ($dinpro_regular) {
    echo "✓ DIN Pro Regular convertida correctamente: $dinpro_regular\n";
} else {
    echo "✗ DIN Pro Regular NO se pudo convertir\n";
}

if ($dinpro_bold) {
    echo "✓ DIN Pro Bold convertida correctamente: $dinpro_bold\n";
} else {
    echo "✗ DIN Pro Bold NO se pudo convertir\n";
}

if ($dinpro_regular && $dinpro_bold) {
    echo "\n✓ ¡Todas las fuentes se convirtieron exitosamente!\n";
    echo "\nAhora puedes usar las fuentes en tu código así:\n";
    echo "  \$pdf->SetFont('$dinpro_regular', '', 12);\n";
    echo "  \$pdf->SetFont('$dinpro_bold', 'B', 12);\n";
} else {
    echo "\n✗ Hubo errores en la conversión. Revisa los mensajes anteriores.\n";
}
