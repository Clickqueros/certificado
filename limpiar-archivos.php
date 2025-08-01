<?php
/**
 * Script de limpieza para archivos de certificados
 * Ejecutar desde el navegador: https://clickqueros.com/wp-content/plugins/certificados-personalizados/limpiar-archivos.php
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar que el plugin esté activo
if (!defined('CERTIFICADOS_PERSONALIZADOS_PLUGIN_PATH')) {
    die('Plugin no está activo');
}

// Incluir funciones del plugin
require_once(CERTIFICADOS_PERSONALIZADOS_PLUGIN_PATH . 'includes/funciones-pdf.php');

echo '<h1>🔧 Limpieza de Archivos de Certificados</h1>';

// Limpiar archivos con doble extensión
echo '<h2>1. Limpiando archivos con doble extensión...</h2>';
$archivos_limpiados = CertificadosPersonalizadosPDF::limpiar_archivos_dobles();
echo '<p>✅ Archivos limpiados: ' . $archivos_limpiados . '</p>';

// Obtener todos los certificados
echo '<h2>2. Regenerando certificados...</h2>';
$certificados = CertificadosPersonalizadosBD::obtener_todos_los_certificados();
$regenerados = 0;

foreach ($certificados as $certificado) {
    echo '<p>Procesando certificado ID: ' . $certificado->id . ' - Código: ' . $certificado->codigo_unico . '</p>';
    
    if (CertificadosPersonalizadosPDF::generar_certificado_pdf($certificado->id)) {
        $regenerados++;
        echo '<p style="color: green;">✅ Regenerado exitosamente</p>';
    } else {
        echo '<p style="color: red;">❌ Error al regenerar</p>';
    }
}

echo '<h2>3. Resumen</h2>';
echo '<p>📁 Archivos limpiados: ' . $archivos_limpiados . '</p>';
echo '<p>🔄 Certificados regenerados: ' . $regenerados . '</p>';
echo '<p>📊 Total de certificados: ' . count($certificados) . '</p>';

echo '<h2>4. Verificar archivos actuales</h2>';
$upload_dir = wp_upload_dir();
$certificados_dir = $upload_dir['basedir'] . '/certificados/';

if (file_exists($certificados_dir)) {
    $archivos = glob($certificados_dir . '*');
    echo '<p>Archivos en el directorio:</p>';
    echo '<ul>';
    foreach ($archivos as $archivo) {
        $nombre = basename($archivo);
        $tamaño = filesize($archivo);
        echo '<li>' . $nombre . ' (' . number_format($tamaño) . ' bytes)</li>';
    }
    echo '</ul>';
} else {
    echo '<p style="color: red;">❌ Directorio no existe</p>';
}

echo '<hr>';
echo '<p><a href="' . admin_url('admin.php?page=aprobacion-certificados') . '">← Volver al panel de administración</a></p>';
?> 