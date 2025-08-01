<?php
/**
 * Script de Debug Completo para PDF - Plugin Certificados Personalizados
 * Este script prueba cada paso del proceso de generación de PDF
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar que el plugin esté activo
if (!defined('CERTIFICADOS_PERSONALIZADOS_PLUGIN_PATH')) {
    die('❌ Error: Plugin no está activo');
}

echo "<h1>🔍 Debug Completo de Generación de PDF</h1>";
echo "<hr>";

// Paso 1: Verificar que las clases estén disponibles
echo "<h2>1️⃣ Verificando clases disponibles</h2>";
if (class_exists('CertificadosPersonalizadosBD')) {
    echo "✅ Clase CertificadosPersonalizadosBD disponible<br>";
} else {
    echo "❌ Clase CertificadosPersonalizadosBD NO disponible<br>";
    die('Error: Clase de BD no encontrada');
}

if (class_exists('CertificadosPersonalizadosPDF')) {
    echo "✅ Clase CertificadosPersonalizadosPDF disponible<br>";
} else {
    echo "❌ Clase CertificadosPersonalizadosPDF NO disponible<br>";
    die('Error: Clase de PDF no encontrada');
}

// Paso 2: Obtener certificados existentes
echo "<h2>2️⃣ Buscando certificados en la base de datos</h2>";
$certificados = CertificadosPersonalizadosBD::obtener_todos_certificados();

if (empty($certificados)) {
    echo "❌ No hay certificados en la base de datos<br>";
    echo "💡 Crea un certificado desde el formulario primero<br>";
    die();
}

echo "✅ Encontrados " . count($certificados) . " certificados<br>";

// Tomar el primer certificado para pruebas
$certificado = $certificados[0];
echo "📋 Usando certificado ID: " . $certificado->id . "<br>";
echo "📋 Código: " . $certificado->codigo_unico . "<br>";
echo "📋 Nombre: " . $certificado->nombre . "<br>";
echo "📋 PDF Path actual: " . ($certificado->pdf_path ?: 'Vacío') . "<br>";

// Paso 3: Verificar directorio de uploads
echo "<h2>3️⃣ Verificando directorio de uploads</h2>";
$upload_dir = wp_upload_dir();
echo "📁 Base dir: " . $upload_dir['basedir'] . "<br>";
echo "📁 Base URL: " . $upload_dir['baseurl'] . "<br>";

$certificados_dir = $upload_dir['basedir'] . '/certificados/';
echo "📁 Directorio certificados: " . $certificados_dir . "<br>";

if (file_exists($certificados_dir)) {
    echo "✅ Directorio existe<br>";
    echo "📊 Permisos: " . substr(sprintf('%o', fileperms($certificados_dir)), -4) . "<br>";
    echo "📊 Contenido: " . count(scandir($certificados_dir)) . " archivos<br>";
} else {
    echo "❌ Directorio NO existe<br>";
    echo "🔧 Intentando crear directorio...<br>";
    $creado = wp_mkdir_p($certificados_dir);
    if ($creado) {
        echo "✅ Directorio creado exitosamente<br>";
    } else {
        echo "❌ No se pudo crear el directorio<br>";
    }
}

// Paso 4: Probar generación de HTML
echo "<h2>4️⃣ Probando generación de HTML</h2>";
try {
    $html_content = CertificadosPersonalizadosPDF::generar_html_certificado($certificado);
    if ($html_content) {
        echo "✅ HTML generado correctamente<br>";
        echo "📏 Longitud del HTML: " . strlen($html_content) . " caracteres<br>";
        
        // Guardar HTML para inspección
        $html_test_file = $certificados_dir . 'test_html_' . $certificado->codigo_unico . '.html';
        file_put_contents($html_test_file, $html_content);
        echo "📄 HTML guardado en: " . $html_test_file . "<br>";
    } else {
        echo "❌ HTML NO se generó<br>";
    }
} catch (Exception $e) {
    echo "❌ Error generando HTML: " . $e->getMessage() . "<br>";
}

// Paso 5: Probar generación de PDF
echo "<h2>5️⃣ Probando generación de PDF</h2>";
$nombre_archivo = 'certificado_' . $certificado->codigo_unico . '.pdf';
$ruta_completa = $certificados_dir . $nombre_archivo;

echo "📄 Archivo a generar: " . $ruta_completa . "<br>";

try {
    $pdf_generado = CertificadosPersonalizadosPDF::generar_certificado_pdf($certificado->id);
    
    if ($pdf_generado) {
        echo "✅ PDF generado exitosamente<br>";
        
        // Verificar si el archivo existe
        if (file_exists($ruta_completa)) {
            echo "✅ Archivo PDF existe en disco<br>";
            echo "📊 Tamaño: " . filesize($ruta_completa) . " bytes<br>";
        } else {
            echo "❌ Archivo PDF NO existe en disco<br>";
        }
        
        // Verificar base de datos
        $certificado_actualizado = CertificadosPersonalizadosBD::obtener_certificado($certificado->id);
        echo "📋 PDF Path en BD: " . ($certificado_actualizado->pdf_path ?: 'Vacío') . "<br>";
        
    } else {
        echo "❌ PDF NO se generó<br>";
    }
} catch (Exception $e) {
    echo "❌ Error generando PDF: " . $e->getMessage() . "<br>";
}

// Paso 6: Verificar archivos generados
echo "<h2>6️⃣ Verificando archivos generados</h2>";
$archivos = scandir($certificados_dir);
foreach ($archivos as $archivo) {
    if ($archivo != '.' && $archivo != '..') {
        $ruta_completa_archivo = $certificados_dir . $archivo;
        $tamaño = filesize($ruta_completa_archivo);
        $fecha = date('Y-m-d H:i:s', filemtime($ruta_completa_archivo));
        echo "📄 $archivo - $tamaño bytes - $fecha<br>";
    }
}

// Paso 7: Probar función de verificación
echo "<h2>7️⃣ Probando verificación de PDF</h2>";
$existe_pdf = CertificadosPersonalizadosPDF::existe_pdf($certificado->id);
echo "PDF existe: " . ($existe_pdf ? '✅ Sí' : '❌ No') . "<br>";

$url_pdf = CertificadosPersonalizadosPDF::obtener_url_pdf($certificado->id);
echo "URL del PDF: " . ($url_pdf ?: 'No disponible') . "<br>";

echo "<hr>";
echo "<h2>🎯 Resumen</h2>";
echo "✅ Script completado. Revisa los resultados arriba.<br>";
echo "💡 Si hay errores, revisa los logs de WordPress.<br>";
echo "🔗 <a href='$url_pdf' target='_blank'>Ver PDF generado</a><br>";
?> 