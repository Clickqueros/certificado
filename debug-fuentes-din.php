<?php
/**
 * Script de Diagnóstico Completo para Fuentes DIN Pro
 * 
 * Acceder desde: Certificados → Diagnóstico de Fuentes (en el admin de WordPress)
 */

// Si no se está ejecutando desde WordPress admin, intentar cargar WordPress
if (!defined('ABSPATH')) {
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
        die('ERROR: No se pudo cargar WordPress.');
    }
}

// Verificar permisos
if (!current_user_can('manage_options')) {
    wp_die('ERROR: No tienes permisos para ejecutar este script.');
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

// Si se está ejecutando desde admin de WordPress, no enviar headers directamente
if (!defined('ABSPATH')) {
    header('Content-Type: text/html; charset=utf-8');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>🔍 Diagnóstico de Fuentes DIN Pro</title>
    <?php if (defined('ABSPATH')): ?>
    <style>
        .wrap { margin: 20px 20px 0 0; }
    </style>
    <?php endif; ?>
    <style>
        body { font-family: Arial, sans-serif; <?php if (!defined('ABSPATH')): ?>padding: 20px; background: #f5f5f5;<?php endif; ?> }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        <?php if (defined('ABSPATH')): ?>
        .wrap { background: white; padding: 20px; border-radius: 8px; }
        <?php endif; ?>
        h1 { color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
        h2 { color: #0073aa; margin-top: 30px; border-left: 4px solid #0073aa; padding-left: 10px; }
        h3 { color: #0073aa; margin-top: 20px; }
        .success { color: #46b450; background: #ecf7ed; padding: 10px; border-left: 4px solid #46b450; margin: 10px 0; }
        .error { color: #dc3232; background: #fbeaea; padding: 10px; border-left: 4px solid #dc3232; margin: 10px 0; }
        .warning { color: #f0b849; background: #fff8e5; padding: 10px; border-left: 4px solid #f0b849; margin: 10px 0; }
        .info { color: #0073aa; background: #e5f5fa; padding: 10px; border-left: 4px solid #0073aa; margin: 10px 0; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        .test-section { background: #f9f9f9; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .btn { display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px; margin-top: 10px; }
        .btn:hover { background: #005177; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table td { padding: 8px; border: 1px solid #ddd; }
        table th { background: #0073aa; color: white; padding: 8px; text-align: left; }
        .status-ok { color: #46b450; font-weight: bold; }
        .status-error { color: #dc3232; font-weight: bold; }
        .status-warning { color: #f0b849; font-weight: bold; }
    </style>
</head>
<body>
    <?php if (defined('ABSPATH')): ?>
    <div class="wrap">
        <h1>🔍 Diagnóstico Completo de Fuentes DIN Pro</h1>
    <?php else: ?>
    <div class="container">
        <h1>🔍 Diagnóstico Completo de Fuentes DIN Pro</h1>
    <?php endif; ?>
        
        <?php
        $diagnostico = array();
        $errores = array();
        $advertencias = array();
        $exitos = array();
        
        // ============================================
        // TEST 1: Verificar archivos fuente .ttf o .otf
        // ============================================
        echo '<div class="test-section">';
        echo '<h2>1. Verificación de Archivos Fuente (.ttf o .otf)</h2>';
        
        // Buscar archivos .ttf primero (preferido), luego .otf como fallback
        $dinpro_regular_source = null;
        $dinpro_regular_ext = null;
        if (file_exists($source_fonts_dir . 'dinpro.ttf')) {
            $dinpro_regular_source = $source_fonts_dir . 'dinpro.ttf';
            $dinpro_regular_ext = '.ttf';
        } elseif (file_exists($source_fonts_dir . 'dinpro.otf')) {
            $dinpro_regular_source = $source_fonts_dir . 'dinpro.otf';
            $dinpro_regular_ext = '.otf';
        }
        
        $dinpro_bold_source = null;
        $dinpro_bold_ext = null;
        if (file_exists($source_fonts_dir . 'dinpro_bold.ttf')) {
            $dinpro_bold_source = $source_fonts_dir . 'dinpro_bold.ttf';
            $dinpro_bold_ext = '.ttf';
        } elseif (file_exists($source_fonts_dir . 'dinpro_bold.otf')) {
            $dinpro_bold_source = $source_fonts_dir . 'dinpro_bold.otf';
            $dinpro_bold_ext = '.otf';
        }
        
        $tests = array(
            'Directorio fuente existe' => array(
                'check' => file_exists($source_fonts_dir),
                'path' => $source_fonts_dir,
                'permisos' => file_exists($source_fonts_dir) ? substr(sprintf('%o', fileperms($source_fonts_dir)), -4) : 'N/A'
            ),
            'dinpro.ttf o dinpro.otf existe' => array(
                'check' => $dinpro_regular_source !== null,
                'path' => $dinpro_regular_source ? $dinpro_regular_source : 'No encontrado',
                'size' => $dinpro_regular_source ? filesize($dinpro_regular_source) : 0,
                'readable' => $dinpro_regular_source ? is_readable($dinpro_regular_source) : false,
                'ext' => $dinpro_regular_ext
            ),
            'dinpro_bold.ttf o dinpro_bold.otf existe' => array(
                'check' => $dinpro_bold_source !== null,
                'path' => $dinpro_bold_source ? $dinpro_bold_source : 'No encontrado',
                'size' => $dinpro_bold_source ? filesize($dinpro_bold_source) : 0,
                'readable' => $dinpro_bold_source ? is_readable($dinpro_bold_source) : false,
                'ext' => $dinpro_bold_ext
            )
        );
        
        echo '<table>';
        echo '<tr><th>Verificación</th><th>Estado</th><th>Detalles</th></tr>';
        foreach ($tests as $test_name => $test_data) {
            $status = $test_data['check'] ? '<span class="status-ok">✓ OK</span>' : '<span class="status-error">✗ ERROR</span>';
            $details = '';
            if (isset($test_data['path'])) {
                $details .= '<strong>Ruta:</strong> ' . htmlspecialchars($test_data['path']) . '<br>';
            }
            if (isset($test_data['size'])) {
                $details .= '<strong>Tamaño:</strong> ' . number_format($test_data['size']) . ' bytes<br>';
            }
            if (isset($test_data['permisos'])) {
                $details .= '<strong>Permisos:</strong> ' . $test_data['permisos'] . '<br>';
            }
            if (isset($test_data['readable'])) {
                $details .= '<strong>Legible:</strong> ' . ($test_data['readable'] ? 'SÍ' : 'NO') . '<br>';
            }
            if (isset($test_data['ext'])) {
                $details .= '<strong>Formato:</strong> ' . htmlspecialchars($test_data['ext']) . '<br>';
            }
            echo '<tr><td>' . $test_name . '</td><td>' . $status . '</td><td>' . $details . '</td></tr>';
            
            if (!$test_data['check']) {
                $errores[] = $test_name;
            } else {
                $exitos[] = $test_name;
            }
        }
        echo '</table>';
        echo '</div>';
        
        // ============================================
        // TEST 2: Verificar directorio de destino
        // ============================================
        echo '<div class="test-section">';
        echo '<h2>2. Verificación de Directorio de Destino</h2>';
        
        $tests_dest = array(
            'Directorio fonts existe' => array(
                'check' => file_exists($fonts_dir),
                'path' => $fonts_dir,
                'permisos' => file_exists($fonts_dir) ? substr(sprintf('%o', fileperms($fonts_dir)), -4) : 'N/A',
                'writable' => file_exists($fonts_dir) ? is_writable($fonts_dir) : false
            ),
            'K_PATH_FONTS definido' => array(
                'check' => defined('K_PATH_FONTS'),
                'value' => defined('K_PATH_FONTS') ? K_PATH_FONTS : 'N/A'
            )
        );
        
        echo '<table>';
        echo '<tr><th>Verificación</th><th>Estado</th><th>Detalles</th></tr>';
        foreach ($tests_dest as $test_name => $test_data) {
            $status = $test_data['check'] ? '<span class="status-ok">✓ OK</span>' : '<span class="status-error">✗ ERROR</span>';
            $details = '';
            if (isset($test_data['path'])) {
                $details .= '<strong>Ruta:</strong> ' . htmlspecialchars($test_data['path']) . '<br>';
            }
            if (isset($test_data['permisos'])) {
                $details .= '<strong>Permisos:</strong> ' . $test_data['permisos'] . '<br>';
                if ($test_data['permisos'] != '0755' && $test_data['permisos'] != '0775' && $test_data['permisos'] != '0777') {
                    $advertencias[] = 'Permisos del directorio pueden ser insuficientes (recomendado: 755 o 775)';
                }
            }
            if (isset($test_data['writable'])) {
                $details .= '<strong>Escribible:</strong> ' . ($test_data['writable'] ? 'SÍ' : 'NO') . '<br>';
                if (!$test_data['writable']) {
                    $errores[] = 'Directorio de destino no es escribible';
                }
            }
            if (isset($test_data['value'])) {
                $details .= '<strong>Valor:</strong> ' . htmlspecialchars($test_data['value']) . '<br>';
            }
            echo '<tr><td>' . $test_name . '</td><td>' . $status . '</td><td>' . $details . '</td></tr>';
        }
        echo '</table>';
        echo '</div>';
        
        // ============================================
        // TEST 3: Verificar archivos convertidos
        // ============================================
        echo '<div class="test-section">';
        echo '<h2>3. Verificación de Archivos Convertidos (.php y .z)</h2>';
        
        $dinpro_php_files = glob($fonts_dir . 'dinpro*.php');
        $dinpro_z_files = glob($fonts_dir . 'dinpro*.z');
        
        echo '<div class="info">';
        echo '<strong>Archivos .php encontrados:</strong> ' . count($dinpro_php_files) . '<br>';
        echo '<strong>Archivos .z encontrados:</strong> ' . count($dinpro_z_files) . '<br>';
        echo '</div>';
        
        if (count($dinpro_php_files) > 0) {
            echo '<table>';
            echo '<tr><th>Archivo</th><th>Tamaño</th><th>Archivo .z correspondiente</th></tr>';
            foreach ($dinpro_php_files as $php_file) {
                $basename = basename($php_file, '.php');
                $z_file = $fonts_dir . $basename . '.z';
                $z_exists = file_exists($z_file);
                $status = $z_exists ? '<span class="status-ok">✓ Existe</span>' : '<span class="status-error">✗ No existe</span>';
                
                echo '<tr>';
                echo '<td>' . htmlspecialchars(basename($php_file)) . '</td>';
                echo '<td>' . number_format(filesize($php_file)) . ' bytes</td>';
                echo '<td>' . $status . ($z_exists ? ' (' . basename($z_file) . ' - ' . number_format(filesize($z_file)) . ' bytes)' : '') . '</td>';
                echo '</tr>';
                
                if (!$z_exists) {
                    $advertencias[] = 'Archivo .z faltante para: ' . basename($php_file);
                }
            }
            echo '</table>';
        } else {
            echo '<div class="warning">No se encontraron archivos .php convertidos. Las fuentes necesitan ser convertidas.</div>';
        }
        echo '</div>';
        
        // ============================================
        // TEST 4: Intentar conversión
        // ============================================
        echo '<div class="test-section">';
        echo '<h2>4. Prueba de Conversión</h2>';
        
        // Intentar convertir DIN Pro Regular
        if ($dinpro_regular_source !== null && file_exists($dinpro_regular_source)) {
            echo '<h3>DIN Pro Regular</h3>';
            
            // Verificar tipo de archivo fuente
            $font_content = file_get_contents($dinpro_regular_source, false, null, 0, 12);
            $font_type_info = '';
            $is_otf_cff = false;
            
            if (substr($font_content, 0, 4) == 'OTTO') {
                $font_type_info = '<div class="error">⚠ Este archivo es OpenType con formato CFF (PostScript outlines). TCPDF NO soporta este formato directamente.</div>';
                $is_otf_cff = true;
            } elseif (substr($font_content, 0, 4) == "\x00\x01\x00\x00" || substr($font_content, 0, 4) == "true") {
                $font_type_info = '<div class="info">✓ Este archivo es TrueType/OpenType con TrueType outlines (compatible con TCPDF).</div>';
            } else {
                $hex_start = bin2hex(substr($font_content, 0, 4));
                $font_type_info = '<div class="warning">⚠ Tipo de archivo no reconocido. Primeros 4 bytes (hex): ' . $hex_start . '</div>';
            }
            echo $font_type_info;
            
            try {
                error_log('DEBUG: Intentando convertir DIN Pro Regular desde: ' . $dinpro_regular_source);
                error_log('DEBUG: Directorio destino: ' . $fonts_dir);
                error_log('DEBUG: Permisos directorio: ' . (file_exists($fonts_dir) ? substr(sprintf('%o', fileperms($fonts_dir)), -4) : 'N/A'));
                error_log('DEBUG: Directorio escribible: ' . (is_writable($fonts_dir) ? 'SÍ' : 'NO'));
                
                if ($is_otf_cff) {
                    echo '<div class="error">';
                    echo '✗ <strong>No se puede convertir:</strong> TCPDF no soporta fuentes OpenType con formato CFF (PostScript outlines).<br>';
                    echo '<strong>Solución:</strong> Necesitas convertir el archivo .otf a formato .ttf (TrueType) primero.<br>';
                    echo 'Puedes usar herramientas como:<br>';
                    echo '- FontForge (gratuito, multiplataforma)<br>';
                    echo '- Online converters (fontconverter.org, convertio.co)<br>';
                    echo '- Adobe Font Development Kit for OpenType (AFDKO)<br>';
                    echo '</div>';
                    $errores[] = 'DIN Pro Regular es OpenType CFF - no compatible con TCPDF';
                } else {
                    $font_name = TCPDF_FONTS::addTTFfont($dinpro_regular_source, 'TrueTypeUnicode', '', 32, $fonts_dir);
                    
                    if ($font_name !== false && !empty($font_name)) {
                    echo '<div class="success">';
                    echo '✓ <strong>Conversión exitosa:</strong> ' . htmlspecialchars($font_name) . '<br>';
                    
                    $php_file = $fonts_dir . $font_name . '.php';
                    $z_file = $fonts_dir . $font_name . '.z';
                    
                    if (file_exists($php_file) && file_exists($z_file)) {
                        echo '✓ Archivos creados:<br>';
                        echo '  - ' . htmlspecialchars($font_name) . '.php (' . number_format(filesize($php_file)) . ' bytes)<br>';
                        echo '  - ' . htmlspecialchars($font_name) . '.z (' . number_format(filesize($z_file)) . ' bytes)';
                        $exitos[] = 'DIN Pro Regular convertida exitosamente';
                    } else {
                        echo '⚠ Archivos no encontrados después de conversión<br>';
                        echo 'PHP existe: ' . (file_exists($php_file) ? 'SÍ' : 'NO') . '<br>';
                        echo 'Z existe: ' . (file_exists($z_file) ? 'SÍ' : 'NO');
                        $advertencias[] = 'Archivos .php/.z no creados para DIN Pro Regular';
                    }
                    echo '</div>';
                    } else {
                        echo '<div class="error">';
                        echo '✗ <strong>Error en conversión:</strong> addTTFfont retornó: ' . var_export($font_name, true) . '<br>';
                        echo '<strong>Posibles causas:</strong><br>';
                        echo '- El archivo puede estar corrupto<br>';
                        echo '- El formato puede no ser compatible<br>';
                        echo '- Puede haber un problema de memoria PHP<br>';
                        echo '- Verifica los logs de PHP para más detalles';
                        echo '</div>';
                        $errores[] = 'Error al convertir DIN Pro Regular';
                    }
                }
            } catch (Exception $e) {
                echo '<div class="error">';
                echo '✗ <strong>Excepción:</strong> ' . htmlspecialchars($e->getMessage()) . '<br>';
                echo '<strong>Stack trace:</strong><pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
                echo '</div>';
                $errores[] = 'Excepción al convertir DIN Pro Regular: ' . $e->getMessage();
            }
        } else {
            echo '<h3>DIN Pro Regular</h3>';
            echo '<div class="warning">⚠ No se encontró archivo dinpro.ttf ni dinpro.otf en el directorio de fuentes.</div>';
        }
        
        // Intentar convertir DIN Pro Bold
        if ($dinpro_bold_source !== null && file_exists($dinpro_bold_source)) {
            echo '<h3>DIN Pro Bold</h3>';
            
            // Verificar tipo de archivo fuente
            $font_content = file_get_contents($dinpro_bold_source, false, null, 0, 12);
            $font_type_info = '';
            $is_otf_cff = false;
            
            if (substr($font_content, 0, 4) == 'OTTO') {
                $font_type_info = '<div class="error">⚠ Este archivo es OpenType con formato CFF (PostScript outlines). TCPDF NO soporta este formato directamente.</div>';
                $is_otf_cff = true;
            } elseif (substr($font_content, 0, 4) == "\x00\x01\x00\x00" || substr($font_content, 0, 4) == "true") {
                $font_type_info = '<div class="info">✓ Este archivo es TrueType/OpenType con TrueType outlines (compatible con TCPDF).</div>';
            } else {
                $hex_start = bin2hex(substr($font_content, 0, 4));
                $font_type_info = '<div class="warning">⚠ Tipo de archivo no reconocido. Primeros 4 bytes (hex): ' . $hex_start . '</div>';
            }
            echo $font_type_info;
            
            try {
                error_log('DEBUG: Intentando convertir DIN Pro Bold desde: ' . $dinpro_bold_source);
                
                if ($is_otf_cff) {
                    echo '<div class="error">';
                    echo '✗ <strong>No se puede convertir:</strong> TCPDF no soporta fuentes OpenType con formato CFF (PostScript outlines).<br>';
                    echo '<strong>Solución:</strong> Necesitas convertir el archivo .otf a formato .ttf (TrueType) primero.<br>';
                    echo 'Puedes usar herramientas como:<br>';
                    echo '- FontForge (gratuito, multiplataforma)<br>';
                    echo '- Online converters (fontconverter.org, convertio.co)<br>';
                    echo '- Adobe Font Development Kit for OpenType (AFDKO)<br>';
                    echo '</div>';
                    $errores[] = 'DIN Pro Bold es OpenType CFF - no compatible con TCPDF';
                } else {
                    $font_name = TCPDF_FONTS::addTTFfont($dinpro_bold_source, 'TrueTypeUnicode', '', 32, $fonts_dir);
                    
                    if ($font_name !== false && !empty($font_name)) {
                    echo '<div class="success">';
                    echo '✓ <strong>Conversión exitosa:</strong> ' . htmlspecialchars($font_name) . '<br>';
                    
                    $php_file = $fonts_dir . $font_name . '.php';
                    $z_file = $fonts_dir . $font_name . '.z';
                    
                    if (file_exists($php_file) && file_exists($z_file)) {
                        echo '✓ Archivos creados:<br>';
                        echo '  - ' . htmlspecialchars($font_name) . '.php (' . number_format(filesize($php_file)) . ' bytes)<br>';
                        echo '  - ' . htmlspecialchars($font_name) . '.z (' . number_format(filesize($z_file)) . ' bytes)';
                        $exitos[] = 'DIN Pro Bold convertida exitosamente';
                    } else {
                        echo '⚠ Archivos no encontrados después de conversión<br>';
                        echo 'PHP existe: ' . (file_exists($php_file) ? 'SÍ' : 'NO') . '<br>';
                        echo 'Z existe: ' . (file_exists($z_file) ? 'SÍ' : 'NO');
                        $advertencias[] = 'Archivos .php/.z no creados para DIN Pro Bold';
                    }
                    echo '</div>';
                    } else {
                        echo '<div class="error">';
                        echo '✗ <strong>Error en conversión:</strong> addTTFfont retornó: ' . var_export($font_name, true) . '<br>';
                        echo '<strong>Posibles causas:</strong><br>';
                        echo '- El archivo puede estar corrupto<br>';
                        echo '- El formato puede no ser compatible<br>';
                        echo '- Puede haber un problema de memoria PHP<br>';
                        echo '- Verifica los logs de PHP para más detalles';
                        echo '</div>';
                        $errores[] = 'Error al convertir DIN Pro Bold';
                    }
                }
            } catch (Exception $e) {
                echo '<div class="error">';
                echo '✗ <strong>Excepción:</strong> ' . htmlspecialchars($e->getMessage()) . '<br>';
                echo '<strong>Stack trace:</strong><pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
                echo '</div>';
                $errores[] = 'Excepción al convertir DIN Pro Bold: ' . $e->getMessage();
            }
        } else {
            echo '<h3>DIN Pro Bold</h3>';
            echo '<div class="warning">⚠ No se encontró archivo dinpro_bold.ttf ni dinpro_bold.otf en el directorio de fuentes.</div>';
        }
        echo '</div>';
        
        // ============================================
        // TEST 5: Verificar registro en TCPDF
        // ============================================
        echo '<div class="test-section">';
        echo '<h2>5. Prueba de Registro en TCPDF</h2>';
        
        try {
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Buscar archivos convertidos
            $dinpro_regular_name = null;
            $dinpro_bold_name = null;
            
            $php_files = glob($fonts_dir . 'dinpro*.php');
            foreach ($php_files as $file) {
                $basename = basename($file, '.php');
                // El regular es exactamente "dinpro" sin sufijos
                if ($basename == 'dinpro') {
                    $z_file = $fonts_dir . $basename . '.z';
                    if (file_exists($z_file)) {
                        $dinpro_regular_name = $basename;
                        break;
                    }
                }
            }
            
            // Buscar archivos bold (pueden ser dinpro_b, dinprobold, dinpro_bold, etc.)
            $bold_php_files = glob($fonts_dir . 'dinpro*.php');
            foreach ($bold_php_files as $file) {
                $basename = basename($file, '.php');
                // Excluir el regular (dinpro sin sufijos)
                if ($basename == 'dinpro') {
                    continue;
                }
                // Buscar archivos que contengan "dinpro" y "bold" o "dinpro_b" (formato generado por TCPDF)
                if ((stripos($basename, 'dinpro') !== false && (stripos($basename, 'bold') !== false || stripos($basename, '_b') !== false)) ||
                    (stripos($basename, 'dinpro_b') === 0)) {
                    $z_file = $fonts_dir . $basename . '.z';
                    if (file_exists($z_file)) {
                        $dinpro_bold_name = $basename;
                        break;
                    }
                }
            }
            
            if ($dinpro_regular_name) {
                echo '<h3>Registro DIN Pro Regular</h3>';
                try {
                    $pdf->AddFont('dinpro', '', $dinpro_regular_name);
                    echo '<div class="success">✓ Fuente registrada exitosamente como "dinpro" desde archivo: ' . htmlspecialchars($dinpro_regular_name) . '</div>';
                    $exitos[] = 'DIN Pro Regular registrada en TCPDF';
                } catch (Exception $e) {
                    echo '<div class="error">✗ Error al registrar: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    $errores[] = 'Error al registrar DIN Pro Regular en TCPDF';
                }
            } else {
                echo '<div class="warning">⚠ No se encontró archivo convertido para DIN Pro Regular</div>';
            }
            
            if ($dinpro_bold_name) {
                echo '<h3>Registro DIN Pro Bold</h3>';
                try {
                    $pdf->AddFont('dinprobold', 'B', $dinpro_bold_name);
                    echo '<div class="success">✓ Fuente registrada exitosamente como "dinprobold" desde archivo: ' . htmlspecialchars($dinpro_bold_name) . '</div>';
                    $exitos[] = 'DIN Pro Bold registrada en TCPDF';
                } catch (Exception $e) {
                    echo '<div class="error">✗ Error al registrar: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    $errores[] = 'Error al registrar DIN Pro Bold en TCPDF';
                }
            } else {
                echo '<div class="warning">⚠ No se encontró archivo convertido para DIN Pro Bold</div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="error">✗ Error al crear instancia TCPDF: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $errores[] = 'Error al crear instancia TCPDF';
        }
        echo '</div>';
        
        // ============================================
        // RESUMEN FINAL
        // ============================================
        echo '<div class="test-section">';
        echo '<h2>📋 Resumen del Diagnóstico</h2>';
        
        echo '<div class="info">';
        echo '<strong>Exitos:</strong> ' . count($exitos) . '<br>';
        echo '<strong>Advertencias:</strong> ' . count($advertencias) . '<br>';
        echo '<strong>Errores:</strong> ' . count($errores) . '<br>';
        echo '</div>';
        
        if (count($exitos) > 0) {
            echo '<h3>✓ Exitos</h3>';
            echo '<ul>';
            foreach ($exitos as $exito) {
                echo '<li>' . htmlspecialchars($exito) . '</li>';
            }
            echo '</ul>';
        }
        
        if (count($advertencias) > 0) {
            echo '<h3>⚠ Advertencias</h3>';
            echo '<ul>';
            foreach ($advertencias as $advertencia) {
                echo '<li>' . htmlspecialchars($advertencia) . '</li>';
            }
            echo '</ul>';
        }
        
        if (count($errores) > 0) {
            echo '<h3>✗ Errores</h3>';
            echo '<ul>';
            foreach ($errores as $error) {
                echo '<li>' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul>';
        }
        
        if (count($errores) == 0 && count($advertencias) == 0) {
            echo '<div class="success"><strong>✓ ¡Todo está funcionando correctamente!</strong></div>';
        }
        
        echo '</div>';
        ?>
        
        <br>
        <?php if (defined('ABSPATH')): ?>
            <a href="<?php echo admin_url('admin.php?page=certificados'); ?>" class="btn">← Volver a Certificados</a>
            <a href="<?php echo admin_url('admin.php?page=diagnostico-fuentes-din'); ?>" class="btn" onclick="location.reload(); return false;">🔄 Actualizar Diagnóstico</a>
        <?php else: ?>
            <a href="<?php echo admin_url(); ?>" class="btn">← Volver al Dashboard</a>
        <?php endif; ?>
    </div>
</body>
</html>

