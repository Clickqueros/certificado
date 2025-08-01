<?php
/**
 * Script de prueba para Dompdf
 * Visitar: https://clickqueros.com/wp-content/plugins/certificados-personalizados/test-dompdf.php
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar que estamos en el plugin correcto
if (!defined('CERTIFICADOS_PERSONALIZADOS_VERSION')) {
    die('Este script debe ejecutarse desde el plugin Certificados Personalizados');
}

echo "<h1>Prueba de Dompdf</h1>";

// Verificar si Dompdf está disponible
$dompdf_path = plugin_dir_path(__FILE__) . 'includes/libs/dompdf/src/Dompdf.php';
echo "<h2>Verificando Dompdf</h2>";
echo "<p>Ruta de Dompdf: " . $dompdf_path . "</p>";

if (file_exists($dompdf_path)) {
    echo "<p style='color: green;'>✅ Dompdf encontrado</p>";
    
    try {
        // Incluir Dompdf
        require_once $dompdf_path;
        require_once plugin_dir_path(__FILE__) . 'includes/libs/dompdf/src/Options.php';
        require_once plugin_dir_path(__FILE__) . 'includes/libs/dompdf/src/Canvas.php';
        require_once plugin_dir_path(__FILE__) . 'includes/libs/dompdf/src/CanvasFactory.php';
        require_once plugin_dir_path(__FILE__) . 'includes/libs/dompdf/src/Exception.php';
        
        echo "<p style='color: green;'>✅ Librerías de Dompdf cargadas</p>";
        
        // Crear HTML de prueba
        $html_test = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Certificado de Prueba</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .certificado { border: 2px solid #333; padding: 30px; text-align: center; }
                .titulo { font-size: 24px; color: #333; margin-bottom: 20px; }
                .contenido { font-size: 16px; line-height: 1.6; }
                .codigo { font-weight: bold; color: #666; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="certificado">
                <div class="titulo">Certificado de Participación</div>
                <div class="contenido">
                    <p>Se otorga el presente certificado a:</p>
                    <h2>Juan Pérez</h2>
                    <p>Por su participación en:</p>
                    <h3>Curso de Capacitación</h3>
                    <p>Fecha: 15 de Diciembre de 2024</p>
                    <p>Observaciones: Excelente participación y compromiso durante todo el curso.</p>
                </div>
                <div class="codigo">Código: CERT-2024-001</div>
            </div>
        </body>
        </html>';
        
        // Configurar Dompdf
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Arial');
        
        echo "<p style='color: green;'>✅ Opciones de Dompdf configuradas</p>";
        
        // Crear instancia de Dompdf
        $dompdf = new \Dompdf\Dompdf($options);
        
        // Cargar HTML
        $dompdf->loadHtml($html_test);
        
        // Renderizar PDF
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        echo "<p style='color: green;'>✅ PDF renderizado exitosamente</p>";
        
        // Guardar PDF de prueba
        $upload_dir = wp_upload_dir();
        $certificados_dir = $upload_dir['basedir'] . '/certificados/';
        
        if (!file_exists($certificados_dir)) {
            wp_mkdir_p($certificados_dir);
        }
        
        $ruta_pdf = $certificados_dir . 'test-dompdf.pdf';
        $output = $dompdf->output();
        
        if (file_put_contents($ruta_pdf, $output)) {
            echo "<p style='color: green;'>✅ PDF guardado exitosamente</p>";
            echo "<p>Archivo guardado en: " . $ruta_pdf . "</p>";
            echo "<p>URL del archivo: " . $upload_dir['baseurl'] . '/certificados/test-dompdf.pdf' . "</p>";
            echo "<p><a href='" . $upload_dir['baseurl'] . '/certificados/test-dompdf.pdf' . "' target='_blank'>Ver PDF de prueba</a></p>";
        } else {
            echo "<p style='color: red;'>❌ Error guardando PDF</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error con Dompdf: " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ Dompdf no encontrado</p>";
    echo "<p>Verificando estructura de directorios:</p>";
    
    $libs_dir = plugin_dir_path(__FILE__) . 'includes/libs/';
    if (file_exists($libs_dir)) {
        echo "<p>✅ Directorio libs existe</p>";
        $dompdf_dir = $libs_dir . 'dompdf/';
        if (file_exists($dompdf_dir)) {
            echo "<p>✅ Directorio dompdf existe</p>";
            $src_dir = $dompdf_dir . 'src/';
            if (file_exists($src_dir)) {
                echo "<p>✅ Directorio src existe</p>";
            } else {
                echo "<p>❌ Directorio src no existe</p>";
            }
        } else {
            echo "<p>❌ Directorio dompdf no existe</p>";
        }
    } else {
        echo "<p>❌ Directorio libs no existe</p>";
    }
}

echo "<h2>Información del servidor</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Servidor: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Directorio actual: " . __DIR__ . "</p>";
echo "<p>Plugin path: " . plugin_dir_path(__FILE__) . "</p>";
?> 