<?php
/**
 * Script para convertir fuentes DIN Pro - Ejecutar desde navegador
 * 
 * Acceder a: /wp-content/plugins/certificado/convertir-fuentes.php
 * (Asegúrate de que el plugin esté activo)
 */

// Cargar WordPress
$wp_load_paths = array(
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../../../wp-load.php',
);

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('ERROR: No se pudo cargar WordPress. Verifica la ruta.');
}

// Verificar permisos
if (!current_user_can('manage_options')) {
    die('ERROR: No tienes permisos para ejecutar este script.');
}

// Cargar TCPDF
require_once __DIR__ . '/includes/libs/tcpdf/tcpdf.php';

if (!class_exists('TCPDF_FONTS')) {
    die('ERROR: TCPDF_FONTS no está disponible.');
}

// Rutas
$fonts_dir = __DIR__ . '/includes/libs/tcpdf/fonts/';
$source_fonts_dir = __DIR__ . '/templates/fonts/';

// Definir K_PATH_FONTS
if (!defined('K_PATH_FONTS')) {
    define('K_PATH_FONTS', $fonts_dir);
}

// Header HTML
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Convertir Fuentes DIN Pro</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
        .success { color: #46b450; background: #ecf7ed; padding: 10px; border-left: 4px solid #46b450; margin: 10px 0; }
        .error { color: #dc3232; background: #fbeaea; padding: 10px; border-left: 4px solid #dc3232; margin: 10px 0; }
        .info { color: #0073aa; background: #e5f5fa; padding: 10px; border-left: 4px solid #0073aa; margin: 10px 0; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px; margin-top: 10px; }
        .btn:hover { background: #005177; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Conversión de Fuentes DIN Pro para TCPDF</h1>
        
        <?php
        echo '<div class="info">';
        echo '<strong>Directorio de fuentes fuente:</strong> ' . htmlspecialchars($source_fonts_dir) . '<br>';
        echo '<strong>Directorio de destino:</strong> ' . htmlspecialchars($fonts_dir) . '<br>';
        echo '<strong>Permisos del directorio:</strong> ' . substr(sprintf('%o', fileperms($fonts_dir)), -4);
        echo '</div>';
        
        $resultados = array();
        
        // Función para convertir fuente
        function convertir_fuente($archivo_fuente, $nombre_display, $fonts_dir, $source_fonts_dir) {
            $ruta_completa = $source_fonts_dir . $archivo_fuente;
            
            if (!file_exists($ruta_completa)) {
                return array('success' => false, 'message' => "Archivo no encontrado: $ruta_completa");
            }
            
            if (!is_writable($fonts_dir)) {
                return array('success' => false, 'message' => "El directorio de destino no tiene permisos de escritura: $fonts_dir");
            }
            
            try {
                $font_name = TCPDF_FONTS::addTTFfont($ruta_completa, 'TrueTypeUnicode', '', 32, $fonts_dir);
                
                if ($font_name !== false && !empty($font_name)) {
                    $php_file = $fonts_dir . $font_name . '.php';
                    $z_file = $fonts_dir . $font_name . '.z';
                    
                    if (file_exists($php_file) && file_exists($z_file)) {
                        return array(
                            'success' => true,
                            'font_name' => $font_name,
                            'php_size' => filesize($php_file),
                            'z_size' => filesize($z_file)
                        );
                    } else {
                        return array('success' => false, 'message' => 'Los archivos .php y .z no se crearon correctamente');
                    }
                } else {
                    return array('success' => false, 'message' => 'addTTFfont retornó false o vacío');
                }
            } catch (Exception $e) {
                return array('success' => false, 'message' => 'Excepción: ' . $e->getMessage());
            }
        }
        
        // Convertir DIN Pro Regular
        echo '<h2>1. DIN Pro Regular</h2>';
        $resultado_regular = convertir_fuente('dinpro.otf', 'DIN Pro Regular', $fonts_dir, $source_fonts_dir);
        
        if ($resultado_regular['success']) {
            echo '<div class="success">';
            echo '✓ <strong>Convertido exitosamente:</strong> ' . htmlspecialchars($resultado_regular['font_name']) . '<br>';
            echo 'Archivos creados:<br>';
            echo '  - ' . htmlspecialchars($resultado_regular['font_name']) . '.php (' . number_format($resultado_regular['php_size']) . ' bytes)<br>';
            echo '  - ' . htmlspecialchars($resultado_regular['font_name']) . '.z (' . number_format($resultado_regular['z_size']) . ' bytes)';
            echo '</div>';
            $resultados['regular'] = $resultado_regular['font_name'];
        } else {
            echo '<div class="error">';
            echo '✗ <strong>Error:</strong> ' . htmlspecialchars($resultado_regular['message']);
            echo '</div>';
        }
        
        // Convertir DIN Pro Bold
        echo '<h2>2. DIN Pro Bold</h2>';
        $resultado_bold = convertir_fuente('dinpro_bold.otf', 'DIN Pro Bold', $fonts_dir, $source_fonts_dir);
        
        if ($resultado_bold['success']) {
            echo '<div class="success">';
            echo '✓ <strong>Convertido exitosamente:</strong> ' . htmlspecialchars($resultado_bold['font_name']) . '<br>';
            echo 'Archivos creados:<br>';
            echo '  - ' . htmlspecialchars($resultado_bold['font_name']) . '.php (' . number_format($resultado_bold['php_size']) . ' bytes)<br>';
            echo '  - ' . htmlspecialchars($resultado_bold['font_name']) . '.z (' . number_format($resultado_bold['z_size']) . ' bytes)';
            echo '</div>';
            $resultados['bold'] = $resultado_bold['font_name'];
        } else {
            echo '<div class="error">';
            echo '✗ <strong>Error:</strong> ' . htmlspecialchars($resultado_bold['message']);
            echo '</div>';
        }
        
        // Resumen
        echo '<h2>📋 Resumen</h2>';
        
        if (isset($resultados['regular']) && isset($resultados['bold'])) {
            echo '<div class="success">';
            echo '<strong>✓ ¡Todas las fuentes se convirtieron exitosamente!</strong><br><br>';
            echo 'Ahora puedes usar las fuentes en tu código así:<br>';
            echo '<pre>';
            echo "\$pdf->SetFont('" . htmlspecialchars($resultados['regular']) . "', '', 12);\n";
            echo "\$pdf->SetFont('" . htmlspecialchars($resultados['bold']) . "', 'B', 12);";
            echo '</pre>';
            echo '</div>';
        } else {
            echo '<div class="error">';
            echo '<strong>✗ Hubo errores en la conversión.</strong> Revisa los mensajes anteriores.';
            echo '</div>';
        }
        ?>
        
        <br>
        <a href="<?php echo admin_url(); ?>" class="btn">← Volver al Dashboard</a>
    </div>
</body>
</html>
