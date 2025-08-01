<?php
/**
 * Script para probar librerías PDF disponibles
 * Ejecutar desde el navegador: https://clickqueros.com/wp-content/plugins/certificados-personalizados/test-pdf-libs.php
 */

// Cargar WordPress
require_once('../../../wp-load.php');

echo '<h1>🔍 Test de Librerías PDF Disponibles</h1>';

echo '<h2>1. Verificar librerías instaladas</h2>';

$librerias = array(
    'TCPDF' => 'class_exists("TCPDF")',
    'FPDF' => 'class_exists("FPDF")',
    'mPDF' => 'class_exists("mPDF")',
    'Dompdf' => 'class_exists("Dompdf\\Dompdf")',
    'wkhtmltopdf' => 'function_exists("shell_exec") && shell_exec("which wkhtmltopdf")'
);

foreach ($librerias as $nombre => $test) {
    $disponible = eval("return $test;");
    $icono = $disponible ? '✅' : '❌';
    echo "<p>$icono $nombre: " . ($disponible ? 'Disponible' : 'No disponible') . '</p>';
}

echo '<h2>2. Verificar funciones del sistema</h2>';

$funciones = array(
    'shell_exec' => 'function_exists("shell_exec")',
    'exec' => 'function_exists("exec")',
    'system' => 'function_exists("system")',
    'ZipArchive' => 'class_exists("ZipArchive")',
    'file_put_contents' => 'function_exists("file_put_contents")',
    'wp_remote_get' => 'function_exists("wp_remote_get")'
);

foreach ($funciones as $nombre => $test) {
    $disponible = eval("return $test;");
    $icono = $disponible ? '✅' : '❌';
    echo "<p>$icono $nombre: " . ($disponible ? 'Disponible' : 'No disponible') . '</p>';
}

echo '<h2>3. Verificar directorios</h2>';

$upload_dir = wp_upload_dir();
$certificados_dir = $upload_dir['basedir'] . '/certificados/';

echo '<p>📁 Directorio de uploads: ' . $upload_dir['basedir'] . '</p>';
echo '<p>📁 Directorio de certificados: ' . $certificados_dir . '</p>';
echo '<p>🔍 Directorio certificados existe: ' . (file_exists($certificados_dir) ? '✅ Sí' : '❌ No') . '</p>';

if (file_exists($certificados_dir)) {
    $archivos = glob($certificados_dir . '*');
    echo '<p>📄 Archivos en el directorio: ' . count($archivos) . '</p>';
    if (count($archivos) > 0) {
        echo '<ul>';
        foreach ($archivos as $archivo) {
            $nombre = basename($archivo);
            $tamaño = filesize($archivo);
            echo '<li>' . $nombre . ' (' . number_format($tamaño) . ' bytes)</li>';
        }
        echo '</ul>';
    }
}

echo '<h2>4. Probar generación de PDF básico</h2>';

// Crear un certificado de prueba
$certificado_prueba = new stdClass();
$certificado_prueba->nombre = 'TEST PDF';
$certificado_prueba->actividad = 'Prueba de Librería';
$certificado_prueba->fecha = '2025-01-01';
$certificado_prueba->codigo_unico = 'TEST-001';

$ruta_prueba = $certificados_dir . 'test_pdf_basico.pdf';

echo '<p>🔄 Generando PDF de prueba...</p>';

// Incluir funciones del plugin
require_once(CERTIFICADOS_PERSONALIZADOS_PLUGIN_PATH . 'includes/funciones-pdf.php');

// Probar generación básica
$pdf_generado = CertificadosPersonalizadosPDF::generar_pdf_basico_nativo($certificado_prueba, $ruta_prueba);

if ($pdf_generado && file_exists($ruta_prueba)) {
    echo '<p style="color: green;">✅ PDF básico generado exitosamente</p>';
    echo '<p>📄 Archivo: ' . basename($ruta_prueba) . '</p>';
    echo '<p>📏 Tamaño: ' . number_format(filesize($ruta_prueba)) . ' bytes</p>';
    echo '<p>🔗 <a href="' . $upload_dir['baseurl'] . '/certificados/' . basename($ruta_prueba) . '" target="_blank">Ver PDF</a></p>';
} else {
    echo '<p style="color: red;">❌ Error generando PDF básico</p>';
}

echo '<h2>5. Información del servidor</h2>';

echo '<p>🖥️ PHP Version: ' . phpversion() . '</p>';
echo '<p>🖥️ Server Software: ' . $_SERVER['SERVER_SOFTWARE'] . '</p>';
echo '<p>🖥️ OS: ' . php_uname() . '</p>';

echo '<hr>';
echo '<p><a href="' . admin_url('admin.php?page=aprobacion-certificados') . '">← Volver al panel de administración</a></p>';
?> 