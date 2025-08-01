<?php
/**
 * Script de prueba para generar certificados HTML
 * Colocar este archivo en la raíz del plugin temporalmente
 */

// Incluir WordPress
require_once('../../../wp-load.php');

// Verificar que el plugin esté activo
if (!class_exists('CertificadosPersonalizadosBD')) {
    die('Plugin no está activo');
}

echo "<h1>🧪 Prueba de Generación de Certificados</h1>\n";

// Obtener todos los certificados
$certificados = CertificadosPersonalizadosBD::obtener_todos_certificados();

if (empty($certificados)) {
    die('❌ No hay certificados para probar. Crea un certificado primero desde el formulario.');
}

echo "<h2>📋 Certificados encontrados: " . count($certificados) . "</h2>\n";

// Probar con el primer certificado
$certificado = $certificados[0];
echo "<h3>🎯 Probando con certificado: " . $certificado->codigo_unico . "</h3>\n";
echo "<p><strong>Nombre:</strong> " . $certificado->nombre . "</p>\n";
echo "<p><strong>Actividad:</strong> " . $certificado->actividad . "</p>\n";
echo "<p><strong>Fecha:</strong> " . $certificado->fecha . "</p>\n";

// Generar HTML
echo "<h3>🔧 Generando HTML...</h3>\n";
$html_content = CertificadosPersonalizadosPDF::generar_html_certificado($certificado);

if (empty($html_content)) {
    die('❌ Error generando HTML del certificado');
}

echo "✅ HTML generado correctamente (" . strlen($html_content) . " caracteres)\n";

// Crear directorio
$upload_dir = wp_upload_dir();
$certificados_dir = $upload_dir['basedir'] . '/certificados/';

echo "<h3>📁 Verificando directorio...</h3>\n";
echo "<p><strong>Directorio:</strong> " . $certificados_dir . "</p>\n";

if (!file_exists($certificados_dir)) {
    echo "📁 Creando directorio...\n";
    $creado = wp_mkdir_p($certificados_dir);
    if ($creado) {
        echo "✅ Directorio creado exitosamente\n";
    } else {
        echo "❌ Error creando directorio\n";
        die();
    }
} else {
    echo "✅ Directorio ya existe\n";
}

// Guardar archivo HTML
$nombre_archivo = 'certificado_' . $certificado->codigo_unico . '.html';
$ruta_completa = $certificados_dir . $nombre_archivo;

echo "<h3>💾 Guardando archivo...</h3>\n";
echo "<p><strong>Archivo:</strong> " . $ruta_completa . "</p>\n";

$guardado = file_put_contents($ruta_completa, $html_content);

if ($guardado) {
    echo "✅ Archivo HTML generado: " . $ruta_completa . "\n";
    echo "📄 URL: " . $upload_dir['baseurl'] . '/certificados/' . $nombre_archivo . "\n";
    
    // Actualizar base de datos
    echo "<h3>🗄️ Actualizando base de datos...</h3>\n";
    $actualizado = CertificadosPersonalizadosBD::actualizar_certificado($certificado->id, array(
        'pdf_path' => $upload_dir['baseurl'] . '/certificados/' . $nombre_archivo
    ));
    
    if ($actualizado) {
        echo "✅ Base de datos actualizada\n";
        echo "<h3>🎉 ¡Prueba exitosa!</h3>\n";
        echo "<p><a href='" . $upload_dir['baseurl'] . '/certificados/' . $nombre_archivo . "' target='_blank'>📄 Ver Certificado HTML</a></p>\n";
        echo "<p><a href='" . admin_url('admin.php?page=aprobacion-certificados') . "'>🔙 Volver al Panel de Administración</a></p>\n";
    } else {
        echo "❌ Error actualizando base de datos\n";
    }
} else {
    echo "❌ Error guardando archivo\n";
    echo "<p><strong>Permisos del directorio:</strong> " . substr(sprintf('%o', fileperms($certificados_dir)), -4) . "</p>\n";
}

echo "<h3>📊 Información adicional:</h3>\n";
echo "<p><strong>Upload dir basedir:</strong> " . $upload_dir['basedir'] . "</p>\n";
echo "<p><strong>Upload dir baseurl:</strong> " . $upload_dir['baseurl'] . "</p>\n";
echo "<p><strong>Directorio certificados:</strong> " . $certificados_dir . "</p>\n";
echo "<p><strong>Archivo generado:</strong> " . $ruta_completa . "</p>\n";
echo "<p><strong>URL del archivo:</strong> " . $upload_dir['baseurl'] . '/certificados/' . $nombre_archivo . "</p>\n";
?> 