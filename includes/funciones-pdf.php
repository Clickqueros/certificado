<?php
/**
 * Funciones para generación de PDF - Plugin Certificados Personalizados
 * VERSIÓN CON TCPDF - Librería principal para generación de PDF
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para manejo de PDF
 */
class CertificadosPersonalizadosPDF {
    
    /**
     * Generar certificado en PDF
     */
    public static function generar_certificado_pdf($certificado_id) {
        // Obtener datos del certificado
        $certificado = CertificadosPersonalizadosBD::obtener_certificado($certificado_id);
        
        if (!$certificado) {
            error_log('CertificadosPersonalizadosPDF: No se pudo obtener certificado ID: ' . $certificado_id);
            return false;
        }
        
        // Crear directorio si no existe
        $upload_dir = wp_upload_dir();
        $certificados_dir = $upload_dir['basedir'] . '/certificados/';
        
        if (!file_exists($certificados_dir)) {
            $creado = wp_mkdir_p($certificados_dir);
            if (!$creado) {
                error_log('CertificadosPersonalizadosPDF: No se pudo crear directorio: ' . $certificados_dir);
                return false;
            }
        }
        
        // Generar nombre del archivo
        $nombre_base = 'certificado_' . $certificado->codigo_unico;
        $ruta_pdf = $certificados_dir . $nombre_base . '.pdf';
        
        // Eliminar archivo anterior si existe para forzar regeneración
        if (file_exists($ruta_pdf)) {
            unlink($ruta_pdf);
            error_log('CertificadosPersonalizadosPDF: Archivo anterior eliminado: ' . $ruta_pdf);
        }
        
        // También eliminar archivo HTML si existe
        $ruta_html = $certificados_dir . $nombre_base . '.html';
        if (file_exists($ruta_html)) {
            unlink($ruta_html);
            error_log('CertificadosPersonalizadosPDF: Archivo HTML anterior eliminado: ' . $ruta_html);
        }
        
        // Intentar generar PDF real
        $pdf_generado = false;
        
        // 1. Intentar con TCPDF (PRINCIPAL)
        if (self::generar_pdf_con_tcpdf($certificado, $ruta_pdf)) {
            $pdf_generado = true;
        }
        // 2. Intentar con FPDF si TCPDF falló
        elseif (self::generar_pdf_con_fpdf_directo($certificado, $ruta_pdf)) {
            $pdf_generado = true;
        }
        // 3. Intentar con librería simple
        elseif (self::generar_pdf_simple_directo($certificado, $ruta_pdf)) {
            $pdf_generado = true;
        }
        // 4. Si todo falla, generar HTML como último recurso
        else {
            $ruta_html = $certificados_dir . $nombre_base . '.html';
            $html_content = self::generar_html_certificado($certificado);
            if (file_put_contents($ruta_html, $html_content)) {
                $pdf_generado = true;
                $ruta_pdf = $ruta_html; // Usar la ruta HTML
            }
        }
        
        if ($pdf_generado) {
            // Determinar la extensión correcta
            $extension = pathinfo($ruta_pdf, PATHINFO_EXTENSION);
            $nombre_final = $nombre_base . '.' . $extension;
            
            // Actualizar la ruta en la base de datos con timestamp para evitar caché
            $timestamp = time();
            $url_con_timestamp = $upload_dir['baseurl'] . '/certificados/' . $nombre_final . '?v=' . $timestamp;
            
            // Limpiar caché de WordPress si está disponible
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            
            // Limpiar caché de transients
            if (function_exists('delete_transient')) {
                delete_transient('certificado_pdf_' . $certificado_id);
            }
            
            $actualizado = CertificadosPersonalizadosBD::actualizar_certificado($certificado_id, array(
                'pdf_path' => $url_con_timestamp
            ));
            
            if ($actualizado) {
                error_log('CertificadosPersonalizadosPDF: Archivo generado exitosamente para ID: ' . $certificado_id . ' - Extensión: ' . $extension . ' - Timestamp: ' . $timestamp);
            } else {
                error_log('CertificadosPersonalizadosPDF: Error actualizando BD para ID: ' . $certificado_id);
            }
            
            return $actualizado;
        } else {
            error_log('CertificadosPersonalizadosPDF: Error generando archivo para ID: ' . $certificado_id);
        }
        
        return false;
    }
    
    /**
     * Verificar si el PDF está actualizado
     */
    public static function verificar_pdf_actualizado($certificado_id) {
        $certificado = CertificadosPersonalizadosBD::obtener_certificado($certificado_id);
        
        if (!$certificado || !$certificado->pdf_path) {
            return false;
        }
        
        // Extraer la ruta del archivo sin parámetros
        $url_parts = parse_url($certificado->pdf_path);
        $ruta_archivo = $url_parts['path'];
        
        // Convertir URL a ruta del sistema
        $upload_dir = wp_upload_dir();
        $ruta_completa = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $ruta_archivo);
        
        // Verificar que el archivo existe y tiene contenido
        if (file_exists($ruta_completa) && filesize($ruta_completa) > 0) {
            // Verificar que el contenido contiene el nombre actualizado
            $contenido_archivo = file_get_contents($ruta_completa);
            if (strpos($contenido_archivo, $certificado->nombre) !== false) {
                error_log('CertificadosPersonalizadosPDF: PDF verificado correctamente para ID: ' . $certificado_id . ' - Tamaño: ' . filesize($ruta_completa) . ' bytes - Nombre encontrado: ' . $certificado->nombre);
                return true;
            } else {
                error_log('CertificadosPersonalizadosPDF: PDF no contiene el nombre actualizado para ID: ' . $certificado_id . ' - Nombre esperado: ' . $certificado->nombre);
                return false;
            }
        } else {
            error_log('CertificadosPersonalizadosPDF: PDF no encontrado o vacío para ID: ' . $certificado_id . ' - Ruta: ' . $ruta_completa);
            return false;
        }
    }
    
    /**
     * Generar HTML del certificado
     */
    public static function generar_html_certificado($certificado) {
        $plantilla_path = plugin_dir_path(__FILE__) . '../templates/plantilla-certificado.html';
        
        if (file_exists($plantilla_path)) {
            $html = file_get_contents($plantilla_path);
        } else {
            $html = self::generar_plantilla_por_defecto($certificado, $certificado->actividad);
        }
        
        // Reemplazar placeholders - tanto los antiguos como los nuevos
        $html = str_replace('[NOMBRE_COMPLETO]', htmlspecialchars($certificado->nombre), $html);
        $html = str_replace('[ACTIVIDAD_CURSO]', htmlspecialchars($certificado->actividad), $html);
        $html = str_replace('[FECHA_ACTIVIDAD]', htmlspecialchars($certificado->fecha), $html);
        $html = str_replace('[CODIGO_UNICO]', htmlspecialchars($certificado->codigo_unico), $html);
        $html = str_replace('[NOMBRE_DIRECTOR]', 'Director General', $html);
        $html = str_replace('[NOMBRE_COORDINADOR]', 'Coordinador de Recursos Humanos', $html);
        
        // Reemplazar placeholders con formato {{variable}}
        $html = str_replace('{{nombre}}', htmlspecialchars($certificado->nombre), $html);
        $html = str_replace('{{actividad}}', htmlspecialchars($certificado->actividad), $html);
        $html = str_replace('{{fecha}}', htmlspecialchars($certificado->fecha), $html);
        $html = str_replace('{{codigo}}', htmlspecialchars($certificado->codigo_unico), $html);
        $html = str_replace('{{observaciones}}', htmlspecialchars($certificado->observaciones), $html);
        
        // Debug: Log para verificar que se están reemplazando correctamente
        error_log('CertificadosPersonalizadosPDF: Reemplazando nombre "' . $certificado->nombre . '" en HTML');
        
        return $html;
    }
    
    /**
     * Generar plantilla por defecto
     */
    public static function generar_plantilla_por_defecto($certificado, $tipo_actividad) {
        $html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado de Participación</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .certificado {
            background: white;
            padding: 60px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 800px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }
        .certificado::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 8px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        .logo {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 20px;
        }
        .titulo {
            font-size: 36px;
            color: #333;
            margin-bottom: 40px;
            font-weight: bold;
        }
        .subtitulo {
            font-size: 24px;
            color: #666;
            margin-bottom: 50px;
        }
        .contenido {
            font-size: 18px;
            line-height: 1.6;
            color: #555;
            margin-bottom: 40px;
        }
        .destacado {
            font-size: 22px;
            color: #667eea;
            font-weight: bold;
            margin: 20px 0;
        }
        .codigo {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            font-family: monospace;
            font-size: 16px;
            color: #333;
            margin: 20px 0;
        }
        .fecha {
            font-size: 16px;
            color: #888;
            margin-top: 40px;
        }
        .firma {
            margin-top: 60px;
            border-top: 2px solid #ddd;
            padding-top: 20px;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="certificado">
        <div class="logo">🏆</div>
        <h1 class="titulo">Certificado de Participación</h1>
        <p class="subtitulo">Se otorga el presente certificado a:</p>
        
        <div class="destacado">{{nombre}}</div>
        
        <div class="contenido">
            Por su participación destacada en la actividad:<br>
            <strong>{{actividad}}</strong>
        </div>
        
        <div class="codigo">
            Código de Verificación: {{codigo}}
        </div>
        
        <div class="contenido">
            {{observaciones}}
        </div>
        
        <div class="fecha">
            Fecha: {{fecha}}
        </div>
        
        <div class="firma">
            <p>Este certificado es válido y puede ser verificado en nuestro sistema.</p>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Generar PDF con TCPDF
     */
    private static function generar_pdf_con_tcpdf($certificado, $ruta_archivo) {
        try {
            // Verificar si TCPDF está disponible
            $tcpdf_path = plugin_dir_path(__FILE__) . 'libs/tcpdf/tcpdf.php';
            
            if (!file_exists($tcpdf_path)) {
                error_log('CertificadosPersonalizadosPDF: TCPDF no encontrado en: ' . $tcpdf_path);
                return false;
            }
            
            // Incluir TCPDF
            require_once $tcpdf_path;
            
            // Verificar que la clase esté disponible
            if (!class_exists('TCPDF')) {
                error_log('CertificadosPersonalizadosPDF: Clase TCPDF no encontrada después de incluir archivos');
                return false;
            }
            
            // Crear nueva instancia de TCPDF
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Configurar información del documento
            $pdf->SetCreator('Certificados Personalizados');
            $pdf->SetAuthor('Sistema de Certificados');
            $pdf->SetTitle('Certificado de Participación');
            $pdf->SetSubject('Certificado para ' . $certificado->nombre);
            
            // Configurar márgenes
            $pdf->SetMargins(20, 20, 20);
            $pdf->SetHeaderMargin(0);
            $pdf->SetFooterMargin(0);
            
            // Configurar saltos de página automáticos
            $pdf->SetAutoPageBreak(TRUE, 25);
            
            // Configurar fuente
            $pdf->SetFont('helvetica', '', 12);
            
            // Agregar página
            $pdf->AddPage();
            
            // Generar contenido del PDF
            $html_content = self::generar_html_certificado($certificado);
            
            // Convertir HTML a contenido para TCPDF
            $contenido_pdf = self::convertir_html_para_tcpdf($html_content);
            
            // Escribir contenido
            $pdf->writeHTML($contenido_pdf, true, false, true, false, '');
            
            // Guardar PDF
            if ($pdf->Output($ruta_archivo, 'F')) {
                error_log('CertificadosPersonalizadosPDF: PDF generado exitosamente con TCPDF para: ' . basename($ruta_archivo));
                return true;
            } else {
                error_log('CertificadosPersonalizadosPDF: Error guardando PDF con TCPDF: ' . $ruta_archivo);
                return false;
            }
            
        } catch (Exception $e) {
            error_log('CertificadosPersonalizadosPDF: Error con TCPDF: ' . $e->getMessage());
            error_log('CertificadosPersonalizadosPDF: Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Convertir HTML para TCPDF
     */
    private static function convertir_html_para_tcpdf($html) {
        // Simplificar el HTML para TCPDF
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        
        // Reemplazar estilos complejos con estilos básicos para el template de certificado
        $html = str_replace('<div class="certificado-container">', '<div style="text-align: center; padding: 40px; border: 2px solid #2c3e50; background: white;">', $html);
        $html = str_replace('<div class="certificado-header">', '<div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #3498db; padding-bottom: 15px;">', $html);
        $html = str_replace('<div class="certificado-titulo">', '<div style="font-size: 28px; font-weight: bold; color: #2c3e50; margin-bottom: 10px; text-transform: uppercase;">', $html);
        $html = str_replace('<div class="certificado-subtitulo">', '<div style="font-size: 16px; color: #7f8c8d; font-style: italic;">', $html);
        $html = str_replace('<div class="certificado-contenido">', '<div style="text-align: center; margin: 30px 0; line-height: 1.6;">', $html);
        $html = str_replace('<div class="certificado-texto">', '<div style="font-size: 14px; color: #2c3e50; margin-bottom: 20px;">', $html);
        $html = str_replace('<div class="certificado-nombre">', '<div style="font-size: 20px; font-weight: bold; color: #2c3e50; margin: 15px 0; text-transform: uppercase; border-bottom: 1px solid #bdc3c7; padding-bottom: 8px;">', $html);
        $html = str_replace('<div class="certificado-actividad">', '<div style="font-size: 16px; color: #34495e; margin: 12px 0;">', $html);
        $html = str_replace('<div class="certificado-fecha">', '<div style="font-size: 14px; color: #7f8c8d; margin: 12px 0;">', $html);
        $html = str_replace('<div class="certificado-codigo">', '<div style="font-size: 12px; color: #95a5a6; margin-top: 25px; font-family: monospace;">', $html);
        $html = str_replace('<div class="certificado-footer">', '<div style="margin-top: 40px; display: flex; justify-content: space-between; align-items: center;">', $html);
        $html = str_replace('<div class="certificado-firma">', '<div style="text-align: center; flex: 1;">', $html);
        $html = str_replace('<div class="firma-linea">', '<div style="width: 150px; height: 1px; background-color: #2c3e50; margin: 8px auto;">', $html);
        $html = str_replace('<div class="firma-nombre">', '<div style="font-size: 12px; font-weight: bold; color: #2c3e50;">', $html);
        $html = str_replace('<div class="firma-cargo">', '<div style="font-size: 10px; color: #7f8c8d;">', $html);
        $html = str_replace('<div class="certificado-sello">', '<div style="position: absolute; top: 15px; right: 15px; width: 60px; height: 60px; border: 2px solid #e74c3c; border-radius: 50%; background: rgba(231, 76, 60, 0.1);">', $html);
        $html = str_replace('<div class="sello-texto">', '<div style="font-size: 8px; font-weight: bold; color: #e74c3c; text-align: center; text-transform: uppercase;">', $html);
        
        return $html;
    }
    
    /**
     * Generar PDF con FPDF
     */
    private static function generar_pdf_con_fpdf_directo($certificado, $ruta_archivo) {
        try {
            // Verificar si FPDF está disponible
            $fpdf_path = plugin_dir_path(__FILE__) . 'libs/fpdf/fpdf.php';
            
            if (!file_exists($fpdf_path)) {
                error_log('CertificadosPersonalizadosPDF: FPDF no encontrado en: ' . $fpdf_path);
                return false;
            }
            
            require_once $fpdf_path;
            
            if (!class_exists('FPDF')) {
                error_log('CertificadosPersonalizadosPDF: Clase FPDF no encontrada');
                return false;
            }
            
            // Crear nueva instancia de FPDF
            $pdf = new FPDF();
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            
            // Título
            $pdf->Cell(0, 20, 'Certificado de Participacion', 0, 1, 'C');
            $pdf->Ln(10);
            
            // Información del certificado
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, 'Se otorga el presente certificado a:', 0, 1, 'C');
            $pdf->Ln(5);
            
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, $certificado->nombre, 0, 1, 'C');
            $pdf->Ln(10);
            
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, 'Por su participacion destacada en la actividad:', 0, 1, 'C');
            $pdf->Ln(5);
            
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, $certificado->actividad, 0, 1, 'C');
            $pdf->Ln(10);
            
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, 'Codigo de Verificacion: ' . $certificado->codigo_unico, 0, 1, 'C');
            $pdf->Ln(10);
            
            if (!empty($certificado->observaciones)) {
                $pdf->MultiCell(0, 10, 'Observaciones: ' . $certificado->observaciones, 0, 'C');
                $pdf->Ln(10);
            }
            
            $pdf->Cell(0, 10, 'Fecha: ' . $certificado->fecha, 0, 1, 'C');
            
            // Guardar PDF
            if ($pdf->Output('F', $ruta_archivo)) {
                error_log('CertificadosPersonalizadosPDF: PDF generado exitosamente con FPDF para: ' . basename($ruta_archivo));
                return true;
            } else {
                error_log('CertificadosPersonalizadosPDF: Error guardando PDF con FPDF: ' . $ruta_archivo);
                return false;
            }
            
        } catch (Exception $e) {
            error_log('CertificadosPersonalizadosPDF: Error con FPDF: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generar PDF simple
     */
    private static function generar_pdf_simple_directo($certificado, $ruta_archivo) {
        try {
            // Crear contenido básico del PDF
            $contenido = self::generar_contenido_pdf_basico($certificado);
            
            // Intentar usar wkhtmltopdf si está disponible
            if (function_exists('shell_exec')) {
                $temp_html = tempnam(sys_get_temp_dir(), 'cert_') . '.html';
                file_put_contents($temp_html, $contenido);
                
                $comando = "wkhtmltopdf --page-size A4 --margin-top 20 --margin-bottom 20 --margin-left 20 --margin-right 20 \"$temp_html\" \"$ruta_archivo\" 2>&1";
                $output = shell_exec($comando);
                
                unlink($temp_html);
                
                if (file_exists($ruta_archivo) && filesize($ruta_archivo) > 0) {
                    error_log('CertificadosPersonalizadosPDF: PDF generado exitosamente con wkhtmltopdf para: ' . basename($ruta_archivo));
                    return true;
                }
            }
            
            // Si wkhtmltopdf no está disponible, generar HTML
            $ruta_html = str_replace('.pdf', '.html', $ruta_archivo);
            if (file_put_contents($ruta_html, $contenido)) {
                error_log('CertificadosPersonalizadosPDF: HTML generado como fallback para: ' . basename($ruta_html));
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('CertificadosPersonalizadosPDF: Error con método simple: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generar contenido PDF básico
     */
    private static function generar_contenido_pdf_basico($certificado) {
        $html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado de Participación</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        .subtitle {
            font-size: 16px;
            color: #666;
        }
        .content {
            margin: 30px 0;
        }
        .name {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
            text-align: center;
            margin: 20px 0;
        }
        .activity {
            font-size: 14px;
            margin: 15px 0;
        }
        .code {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            margin: 15px 0;
        }
        .date {
            margin-top: 30px;
            font-size: 12px;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Certificado de Participación</div>
        <div class="subtitle">Se otorga el presente certificado a:</div>
    </div>
    
    <div class="content">
        <div class="name">' . htmlspecialchars($certificado->nombre) . '</div>
        
        <div class="activity">
            Por su participación destacada en la actividad:<br>
            <strong>' . htmlspecialchars($certificado->actividad) . '</strong>
        </div>
        
        <div class="code">
            Código de Verificación: ' . htmlspecialchars($certificado->codigo_unico) . '
        </div>
        
        ' . (!empty($certificado->observaciones) ? '<div class="activity">Observaciones: ' . htmlspecialchars($certificado->observaciones) . '</div>' : '') . '
        
        <div class="date">
            Fecha: ' . htmlspecialchars($certificado->fecha) . '
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Obtener URL del PDF
     */
    public static function obtener_url_pdf($certificado_id) {
        $certificado = CertificadosPersonalizadosBD::obtener_certificado($certificado_id);
        
        if (!$certificado || empty($certificado->pdf_path)) {
            return false;
        }
        
        $upload_dir = wp_upload_dir();
        $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $certificado->pdf_path);
        
        // Si el archivo existe directamente, usar esa URL
        if (file_exists($local_path)) {
            return $certificado->pdf_path;
        }
        
        // Si no existe, buscar archivos con diferentes extensiones
        $path_info = pathinfo($local_path);
        $base_path = $path_info['dirname'] . '/' . $path_info['filename'];
        
        // Buscar archivos con diferentes extensiones
        $extensiones = ['pdf', 'html'];
        foreach ($extensiones as $ext) {
            $archivo_buscar = $base_path . '.' . $ext;
            if (file_exists($archivo_buscar)) {
                $url_correcta = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $archivo_buscar);
                return $url_correcta;
            }
        }
        
        return false;
    }
    
    /**
     * Verificar si existe el PDF
     */
    public static function existe_pdf($certificado_id) {
        $url = self::obtener_url_pdf($certificado_id);
        
        if (!$url) {
            return false;
        }
        
        $upload_dir = wp_upload_dir();
        $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
        
        return file_exists($local_path);
    }
    
    /**
     * Regenerar PDF del certificado
     */
    public static function regenerar_pdf_certificado($certificado_id) {
        return self::generar_certificado_pdf($certificado_id);
    }
    
    /**
     * Forzar regeneración completa del PDF
     */
    public static function forzar_regeneracion_pdf($certificado_id) {
        $certificado = CertificadosPersonalizadosBD::obtener_certificado($certificado_id);
        
        if (!$certificado) {
            error_log('CertificadosPersonalizadosPDF: No se pudo obtener certificado para regeneración forzada ID: ' . $certificado_id);
            return false;
        }
        
        // Limpiar archivo anterior completamente
        $upload_dir = wp_upload_dir();
        $certificados_dir = $upload_dir['basedir'] . '/certificados/';
        $nombre_base = 'certificado_' . $certificado->codigo_unico;
        
        // Eliminar todos los archivos relacionados
        $archivos_a_eliminar = [
            $certificados_dir . $nombre_base . '.pdf',
            $certificados_dir . $nombre_base . '.html'
        ];
        
        foreach ($archivos_a_eliminar as $archivo) {
            if (file_exists($archivo)) {
                unlink($archivo);
                error_log('CertificadosPersonalizadosPDF: Archivo eliminado para regeneración forzada: ' . basename($archivo));
            }
        }
        
        // Limpiar caché
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Regenerar PDF
        $resultado = self::generar_certificado_pdf($certificado_id);
        
        if ($resultado) {
            error_log('CertificadosPersonalizadosPDF: Regeneración forzada exitosa para ID: ' . $certificado_id);
        } else {
            error_log('CertificadosPersonalizadosPDF: Error en regeneración forzada para ID: ' . $certificado_id);
        }
        
        return $resultado;
    }
    
    /**
     * Limpiar archivos dobles
     */
    public static function limpiar_archivos_dobles() {
        $upload_dir = wp_upload_dir();
        $certificados_dir = $upload_dir['basedir'] . '/certificados/';
        
        if (!is_dir($certificados_dir)) {
            return;
        }
        
        $archivos = glob($certificados_dir . '*.html');
        foreach ($archivos as $archivo) {
            $pdf_equivalente = str_replace('.html', '.pdf', $archivo);
            if (file_exists($pdf_equivalente)) {
                unlink($archivo);
                error_log('CertificadosPersonalizadosPDF: Archivo HTML eliminado: ' . basename($archivo));
            }
        }
    }
} 