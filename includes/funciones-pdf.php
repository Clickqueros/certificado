<?php
/**
 * Funciones para generación de PDF - Plugin Certificados Personalizados
 * VERSIÓN CORREGIDA - Métodos públicos para acceso desde scripts externos
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
        
        // Intentar generar PDF real
        $pdf_generado = false;
        
        // 1. Intentar con Dompdf (PRINCIPAL)
        if (self::generar_pdf_con_dompdf($certificado, $ruta_pdf)) {
            $pdf_generado = true;
        }
        // 2. Intentar con TCPDF si Dompdf falló
        elseif (self::generar_pdf_desde_html(self::generar_html_certificado($certificado), $ruta_pdf)) {
            $pdf_generado = true;
        }
        // 3. Intentar con FPDF si TCPDF falló
        elseif (self::generar_pdf_con_fpdf_directo($certificado, $ruta_pdf)) {
            $pdf_generado = true;
        }
        // 4. Intentar con librería simple
        elseif (self::generar_pdf_simple_directo($certificado, $ruta_pdf)) {
            $pdf_generado = true;
        }
        // 5. Si todo falla, generar HTML como último recurso
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
            
            // Actualizar la ruta en la base de datos
            $actualizado = CertificadosPersonalizadosBD::actualizar_certificado($certificado_id, array(
                'pdf_path' => $upload_dir['baseurl'] . '/certificados/' . $nombre_final
            ));
            
            if ($actualizado) {
                error_log('CertificadosPersonalizadosPDF: Archivo generado exitosamente para ID: ' . $certificado_id . ' - Extensión: ' . $extension);
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
     * Generar HTML del certificado
     */
    public static function generar_html_certificado($certificado) {
        // Obtener tipos de actividad
        $tipos_actividad = array(
            'curso' => 'Curso de Capacitación',
            'taller' => 'Taller Práctico',
            'seminario' => 'Seminario',
            'conferencia' => 'Conferencia',
            'workshop' => 'Workshop',
            'otro' => 'Otro'
        );
        
        $tipo_mostrar = isset($tipos_actividad[$certificado->actividad]) ? 
            $tipos_actividad[$certificado->actividad] : $certificado->actividad;
        
        // Cargar plantilla HTML
        $plantilla_path = CERTIFICADOS_PERSONALIZADOS_PLUGIN_PATH . 'templates/plantilla-certificado.html';
        
        if (!file_exists($plantilla_path)) {
            // Plantilla por defecto si no existe
            $html = self::generar_plantilla_por_defecto($certificado, $tipo_mostrar);
        } else {
            $html = file_get_contents($plantilla_path);
            
            // Reemplazar placeholders
            $html = str_replace('[NOMBRE_COMPLETO]', $certificado->nombre, $html);
            $html = str_replace('[ACTIVIDAD_CURSO]', $tipo_mostrar, $html);
            $html = str_replace('[FECHA_ACTIVIDAD]', date('d/m/Y', strtotime($certificado->fecha)), $html);
            $html = str_replace('[CODIGO_UNICO]', $certificado->codigo_unico, $html);
            $html = str_replace('[NOMBRE_DIRECTOR]', 'Director General', $html);
            $html = str_replace('[NOMBRE_COORDINADOR]', 'Coordinador de Recursos Humanos', $html);
        }
        
        return $html;
    }
    
    /**
     * Generar plantilla por defecto si no existe el archivo
     */
    public static function generar_plantilla_por_defecto($certificado, $tipo_actividad) {
        $fecha_formateada = date('d/m/Y', strtotime($certificado->fecha));
        
        return '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Certificado</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 0;
                    padding: 40px;
                    background: #fff;
                    color: #333;
                }
                .certificado-container {
                    max-width: 800px;
                    margin: 0 auto;
                    border: 3px solid #2c3e50;
                    padding: 40px;
                    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
                    position: relative;
                }
                .certificado-sello {
                    position: absolute;
                    top: 20px;
                    right: 20px;
                    width: 80px;
                    height: 80px;
                    background: #e74c3c;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 12px;
                    font-weight: bold;
                    text-align: center;
                    line-height: 1.2;
                }
                .certificado-header {
                    text-align: center;
                    margin-bottom: 40px;
                    border-bottom: 2px solid #3498db;
                    padding-bottom: 20px;
                }
                .certificado-titulo {
                    font-size: 36px;
                    font-weight: bold;
                    color: #2c3e50;
                    margin-bottom: 10px;
                    text-transform: uppercase;
                }
                .certificado-subtitulo {
                    font-size: 18px;
                    color: #7f8c8d;
                    font-style: italic;
                }
                .certificado-contenido {
                    text-align: center;
                    margin: 40px 0;
                    line-height: 1.8;
                }
                .certificado-texto {
                    font-size: 16px;
                    margin: 15px 0;
                    color: #555;
                }
                .certificado-nombre {
                    font-size: 24px;
                    font-weight: bold;
                    color: #2c3e50;
                    margin: 20px 0;
                    text-transform: uppercase;
                    border-bottom: 2px solid #3498db;
                    padding-bottom: 10px;
                    display: inline-block;
                }
                .certificado-actividad {
                    font-size: 20px;
                    font-weight: bold;
                    color: #3498db;
                    margin: 20px 0;
                    text-transform: uppercase;
                }
                .certificado-fecha {
                    font-size: 18px;
                    font-weight: bold;
                    color: #e74c3c;
                    margin: 20px 0;
                }
                .certificado-codigo {
                    text-align: center;
                    margin: 30px 0;
                    padding: 15px;
                    background: #ecf0f1;
                    border-radius: 5px;
                    font-family: monospace;
                    font-size: 14px;
                    color: #2c3e50;
                }
                .certificado-footer {
                    display: flex;
                    justify-content: space-between;
                    margin-top: 60px;
                    padding-top: 30px;
                    border-top: 1px solid #bdc3c7;
                }
                .certificado-firma {
                    text-align: center;
                    flex: 1;
                    margin: 0 20px;
                }
                .firma-linea {
                    width: 150px;
                    height: 2px;
                    background: #2c3e50;
                    margin: 20px auto 10px;
                }
                .firma-nombre {
                    font-weight: bold;
                    color: #2c3e50;
                    margin-bottom: 5px;
                }
                .firma-cargo {
                    font-size: 12px;
                    color: #7f8c8d;
                    text-transform: uppercase;
                }
            </style>
        </head>
        <body>
            <div class="certificado-container">
                <div class="certificado-sello">
                    CERTIFICADO<br>VÁLIDO
                </div>
                
                <div class="certificado-header">
                    <div class="certificado-titulo">Certificado</div>
                    <div class="certificado-subtitulo">de Participación y Aprobación</div>
                </div>
                
                <div class="certificado-contenido">
                    <div class="certificado-texto">Se certifica que</div>
                    <div class="certificado-nombre">' . $certificado->nombre . '</div>
                    <div class="certificado-texto">ha participado exitosamente en</div>
                    <div class="certificado-actividad">' . $tipo_actividad . '</div>
                    <div class="certificado-texto">realizado el día</div>
                    <div class="certificado-fecha">' . $fecha_formateada . '</div>
                    <div class="certificado-texto">Este certificado es otorgado en reconocimiento a su participación y cumplimiento de los objetivos establecidos.</div>
                </div>
                
                <div class="certificado-codigo">Código de Validación: ' . $certificado->codigo_unico . '</div>
                
                <div class="certificado-footer">
                    <div class="certificado-firma">
                        <div class="firma-linea"></div>
                        <div class="firma-nombre">Director General</div>
                        <div class="firma-cargo">Firma Autorizada</div>
                    </div>
                    <div class="certificado-firma">
                        <div class="firma-linea"></div>
                        <div class="firma-nombre">Coordinador RRHH</div>
                        <div class="firma-cargo">Firma Autorizada</div>
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Instalar TCPDF automáticamente si no está disponible
     */
    private static function instalar_tcpdf() {
        $tcpdf_dir = CERTIFICADOS_PERSONALIZADOS_PLUGIN_PATH . 'includes/libs/tcpdf/';
        
        // Si ya existe, no hacer nada
        if (file_exists($tcpdf_dir . 'tcpdf.php')) {
            return true;
        }
        
        // Crear directorio si no existe
        if (!file_exists($tcpdf_dir)) {
            wp_mkdir_p($tcpdf_dir);
        }
        
        // Intentar descargar TCPDF desde GitHub
        $tcpdf_url = 'https://github.com/tecnickcom/TCPDF/archive/refs/tags/6.6.5.zip';
        $zip_file = $tcpdf_dir . 'tcpdf.zip';
        
        // Descargar archivo
        $response = wp_remote_get($tcpdf_url);
        if (is_wp_error($response)) {
            error_log('CertificadosPersonalizadosPDF: Error descargando TCPDF');
            return false;
        }
        
        $zip_content = wp_remote_retrieve_body($response);
        if (empty($zip_content)) {
            error_log('CertificadosPersonalizadosPDF: Contenido ZIP vacío');
            return false;
        }
        
        // Guardar ZIP
        file_put_contents($zip_file, $zip_content);
        
        // Extraer ZIP
        $zip = new ZipArchive;
        if ($zip->open($zip_file) === TRUE) {
            $zip->extractTo($tcpdf_dir);
            $zip->close();
            
            // Mover archivos a la ubicación correcta
            $extracted_dir = $tcpdf_dir . 'TCPDF-6.6.5/';
            if (file_exists($extracted_dir)) {
                // Copiar todos los archivos
                $files = glob($extracted_dir . '*');
                foreach ($files as $file) {
                    $filename = basename($file);
                    if (is_dir($file)) {
                        // Copiar directorio completo
                        self::copy_directory($file, $tcpdf_dir . $filename);
                    } else {
                        copy($file, $tcpdf_dir . $filename);
                    }
                }
                
                // Limpiar directorio extraído
                self::delete_directory($extracted_dir);
            }
            
            // Limpiar archivo ZIP
            unlink($zip_file);
            
            return file_exists($tcpdf_dir . 'tcpdf.php');
        }
        
        return false;
    }
    
    /**
     * Copiar directorio recursivamente
     */
    private static function copy_directory($src, $dst) {
        $dir = opendir($src);
        if (!file_exists($dst)) {
            mkdir($dst, 0755, true);
        }
        while (($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    self::copy_directory($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
    
    /**
     * Eliminar directorio recursivamente
     */
    private static function delete_directory($dir) {
        if (!file_exists($dir)) {
            return true;
        }
        
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            
            if (!self::delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        
        return rmdir($dir);
    }

    /**
     * Generar PDF desde HTML usando TCPDF
     */
    private static function generar_pdf_desde_html($html_content, $ruta_archivo) {
        // Verificar si TCPDF está disponible
        if (!class_exists('TCPDF')) {
            // Intentar cargar TCPDF desde el directorio local
            $tcpdf_path = CERTIFICADOS_PERSONALIZADOS_PLUGIN_PATH . 'includes/libs/tcpdf/tcpdf.php';
            if (file_exists($tcpdf_path)) {
                require_once($tcpdf_path);
            } else {
                // Intentar instalar TCPDF automáticamente
                if (self::instalar_tcpdf()) {
                    require_once($tcpdf_path);
                } else {
                    // Si no está disponible, usar una alternativa simple
                    return self::generar_pdf_simple($html_content, $ruta_archivo);
                }
            }
        }
        
        try {
            // Crear nueva instancia de TCPDF
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Configurar información del documento
            $pdf->SetCreator('Certificados Personalizados');
            $pdf->SetAuthor('Sistema de Certificados');
            $pdf->SetTitle('Certificado de Participación');
            $pdf->SetSubject('Certificado');
            
            // Configurar márgenes
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetHeaderMargin(5);
            $pdf->SetFooterMargin(10);
            
            // Configurar saltos de página automáticos
            $pdf->SetAutoPageBreak(TRUE, 25);
            
            // Configurar fuente
            $pdf->SetFont('helvetica', '', 12);
            
            // Agregar página
            $pdf->AddPage();
            
            // Escribir HTML
            $pdf->writeHTML($html_content, true, false, true, false, '');
            
            // Guardar archivo
            $pdf->Output($ruta_archivo, 'F');
            
            return file_exists($ruta_archivo);
            
        } catch (Exception $e) {
            error_log('Error generando PDF: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generar PDF simple como fallback
     */
    private static function generar_pdf_simple($html_content, $ruta_archivo) {
        // Intentar usar wkhtmltopdf si está disponible
        if (function_exists('shell_exec')) {
            // Crear un archivo HTML temporal
            $html_temp = $ruta_archivo . '.temp.html';
            file_put_contents($html_temp, $html_content);
            
            $comando = "wkhtmltopdf --quiet --page-size A4 --margin-top 0.75in --margin-right 0.75in --margin-bottom 0.75in --margin-left 0.75in --encoding UTF-8 '$html_temp' '$ruta_archivo'";
            $resultado = shell_exec($comando);
            
            // Limpiar archivo temporal
            if (file_exists($html_temp)) {
                unlink($html_temp);
            }
            
            if (file_exists($ruta_archivo)) {
                return true;
            }
        }
        
        // Si no hay conversor disponible, crear un PDF básico usando FPDF o similar
        return self::generar_pdf_basico($html_content, $ruta_archivo);
    }
    
    /**
     * Generar PDF básico sin dependencias externas
     */
    private static function generar_pdf_basico($html_content, $ruta_archivo) {
        // Extraer información del HTML usando expresiones regulares
        preg_match('/<div class="certificado-nombre">(.*?)<\/div>/s', $html_content, $matches_nombre);
        preg_match('/<div class="certificado-actividad">(.*?)<\/div>/s', $html_content, $matches_actividad);
        preg_match('/<div class="certificado-fecha">(.*?)<\/div>/s', $html_content, $matches_fecha);
        preg_match('/Código de Validación: (.*?)</', $html_content, $matches_codigo);
        
        $nombre = isset($matches_nombre[1]) ? strip_tags($matches_nombre[1]) : 'Nombre no encontrado';
        $actividad = isset($matches_actividad[1]) ? strip_tags($matches_actividad[1]) : 'Actividad no encontrada';
        $fecha = isset($matches_fecha[1]) ? strip_tags($matches_fecha[1]) : 'Fecha no encontrada';
        $codigo = isset($matches_codigo[1]) ? strip_tags($matches_codigo[1]) : 'Código no encontrado';
        
        // Intentar instalar FPDF si no está disponible
        if (!class_exists('FPDF')) {
            self::instalar_fpdf();
        }
        
        // Crear contenido PDF básico usando FPDF si está disponible
        if (class_exists('FPDF')) {
            return self::generar_pdf_con_fpdf($nombre, $actividad, $fecha, $codigo, $ruta_archivo);
        }
        
        // Si no hay FPDF, crear un archivo HTML con estilos mejorados para impresión
        $html_mejorado = '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Certificado - ' . $nombre . '</title>
            <style>
                @media print {
                    body { margin: 0; }
                    .certificado-container { 
                        page-break-inside: avoid;
                        border: 2px solid #000;
                        padding: 20px;
                        margin: 10px;
                        background: white;
                    }
                }
                body {
                    font-family: Arial, sans-serif;
                    margin: 0;
                    padding: 20px;
                    background: #f5f5f5;
                }
                .certificado-container {
                    max-width: 800px;
                    margin: 0 auto;
                    border: 3px solid #2c3e50;
                    padding: 40px;
                    background: white;
                    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                }
                .certificado-header {
                    text-align: center;
                    margin-bottom: 30px;
                    border-bottom: 2px solid #3498db;
                    padding-bottom: 20px;
                }
                .certificado-titulo {
                    font-size: 32px;
                    font-weight: bold;
                    color: #2c3e50;
                    margin-bottom: 10px;
                    text-transform: uppercase;
                }
                .certificado-subtitulo {
                    font-size: 16px;
                    color: #7f8c8d;
                    font-style: italic;
                }
                .certificado-contenido {
                    text-align: center;
                    margin: 30px 0;
                    line-height: 1.6;
                }
                .certificado-texto {
                    font-size: 14px;
                    margin: 10px 0;
                    color: #555;
                }
                .certificado-nombre {
                    font-size: 20px;
                    font-weight: bold;
                    color: #2c3e50;
                    margin: 15px 0;
                    text-transform: uppercase;
                    border-bottom: 2px solid #3498db;
                    padding-bottom: 8px;
                    display: inline-block;
                }
                .certificado-actividad {
                    font-size: 18px;
                    font-weight: bold;
                    color: #3498db;
                    margin: 15px 0;
                    text-transform: uppercase;
                }
                .certificado-fecha {
                    font-size: 16px;
                    font-weight: bold;
                    color: #e74c3c;
                    margin: 15px 0;
                }
                .certificado-codigo {
                    text-align: center;
                    margin: 25px 0;
                    padding: 12px;
                    background: #ecf0f1;
                    border-radius: 5px;
                    font-family: monospace;
                    font-size: 12px;
                    color: #2c3e50;
                }
                .certificado-footer {
                    display: flex;
                    justify-content: space-between;
                    margin-top: 40px;
                    padding-top: 20px;
                    border-top: 1px solid #bdc3c7;
                }
                .certificado-firma {
                    text-align: center;
                    flex: 1;
                    margin: 0 15px;
                }
                .firma-linea {
                    width: 120px;
                    height: 2px;
                    background: #2c3e50;
                    margin: 15px auto 8px;
                }
                .firma-nombre {
                    font-weight: bold;
                    color: #2c3e50;
                    margin-bottom: 4px;
                }
                .firma-cargo {
                    font-size: 10px;
                    color: #7f8c8d;
                    text-transform: uppercase;
                }
                .certificado-sello {
                    position: absolute;
                    top: 15px;
                    right: 15px;
                    width: 60px;
                    height: 60px;
                    background: #e74c3c;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 8px;
                    font-weight: bold;
                    text-align: center;
                    line-height: 1.1;
                }
            </style>
        </head>
        <body>
            <div class="certificado-container">
                <div class="certificado-sello">
                    CERTIFICADO<br>VÁLIDO
                </div>
                
                <div class="certificado-header">
                    <div class="certificado-titulo">Certificado</div>
                    <div class="certificado-subtitulo">de Participación y Aprobación</div>
                </div>
                
                <div class="certificado-contenido">
                    <div class="certificado-texto">Se certifica que</div>
                    <div class="certificado-nombre">' . htmlspecialchars($nombre) . '</div>
                    <div class="certificado-texto">ha participado exitosamente en</div>
                    <div class="certificado-actividad">' . htmlspecialchars($actividad) . '</div>
                    <div class="certificado-texto">realizado el día</div>
                    <div class="certificado-fecha">' . htmlspecialchars($fecha) . '</div>
                    <div class="certificado-texto">Este certificado es otorgado en reconocimiento a su participación y cumplimiento de los objetivos establecidos.</div>
                </div>
                
                <div class="certificado-codigo">Código de Validación: ' . htmlspecialchars($codigo) . '</div>
                
                <div class="certificado-footer">
                    <div class="certificado-firma">
                        <div class="firma-linea"></div>
                        <div class="firma-nombre">Director General</div>
                        <div class="firma-cargo">Firma Autorizada</div>
                    </div>
                    <div class="certificado-firma">
                        <div class="firma-linea"></div>
                        <div class="firma-nombre">Coordinador RRHH</div>
                        <div class="firma-cargo">Firma Autorizada</div>
                    </div>
                </div>
            </div>
        </body>
        </html>';
        
        // Guardar como HTML con extensión .pdf para que se abra como PDF en el navegador
        $html_final = $ruta_archivo . '.html';
        file_put_contents($html_final, $html_mejorado);
        
        return file_exists($html_final);
    }
    
    /**
     * Instalar FPDF automáticamente si no está disponible
     */
    private static function instalar_fpdf() {
        $fpdf_dir = CERTIFICADOS_PERSONALIZADOS_PLUGIN_PATH . 'includes/libs/fpdf/';
        
        // Si ya existe, no hacer nada
        if (file_exists($fpdf_dir . 'fpdf.php')) {
            require_once($fpdf_dir . 'fpdf.php');
            return true;
        }
        
        // Crear directorio si no existe
        if (!file_exists($fpdf_dir)) {
            wp_mkdir_p($fpdf_dir);
        }
        
        // Intentar descargar FPDF desde GitHub
        $fpdf_url = 'https://github.com/Setasign/FPDF/archive/refs/tags/1.85.zip';
        $zip_file = $fpdf_dir . 'fpdf.zip';
        
        // Descargar archivo
        $response = wp_remote_get($fpdf_url);
        if (is_wp_error($response)) {
            error_log('CertificadosPersonalizadosPDF: Error descargando FPDF');
            return false;
        }
        
        $zip_content = wp_remote_retrieve_body($response);
        if (empty($zip_content)) {
            error_log('CertificadosPersonalizadosPDF: Contenido ZIP vacío');
            return false;
        }
        
        // Guardar ZIP
        file_put_contents($zip_file, $zip_content);
        
        // Extraer ZIP
        $zip = new ZipArchive;
        if ($zip->open($zip_file) === TRUE) {
            $zip->extractTo($fpdf_dir);
            $zip->close();
            
            // Mover archivos a la ubicación correcta
            $extracted_dir = $fpdf_dir . 'FPDF-1.85/';
            if (file_exists($extracted_dir)) {
                // Copiar todos los archivos
                $files = glob($extracted_dir . '*');
                foreach ($files as $file) {
                    $filename = basename($file);
                    if (is_dir($file)) {
                        // Copiar directorio completo
                        self::copy_directory($file, $fpdf_dir . $filename);
                    } else {
                        copy($file, $fpdf_dir . $filename);
                    }
                }
                
                // Limpiar directorio extraído
                self::delete_directory($extracted_dir);
            }
            
            // Limpiar archivo ZIP
            unlink($zip_file);
            
            if (file_exists($fpdf_dir . 'fpdf.php')) {
                require_once($fpdf_dir . 'fpdf.php');
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generar PDF usando FPDF si está disponible
     */
    private static function generar_pdf_con_fpdf($nombre, $actividad, $fecha, $codigo, $ruta_archivo) {
        try {
            $pdf = new FPDF();
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            
            // Título
            $pdf->Cell(0, 10, 'CERTIFICADO', 0, 1, 'C');
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, 'de Participación y Aprobación', 0, 1, 'C');
            $pdf->Ln(10);
            
            // Contenido
            $pdf->Cell(0, 10, 'Se certifica que', 0, 1, 'C');
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, $nombre, 0, 1, 'C');
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, 'ha participado exitosamente en', 0, 1, 'C');
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, $actividad, 0, 1, 'C');
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, 'realizado el día', 0, 1, 'C');
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, $fecha, 0, 1, 'C');
            $pdf->Ln(10);
            
            // Código
            $pdf->SetFont('Courier', '', 10);
            $pdf->Cell(0, 10, 'Código de Validación: ' . $codigo, 0, 1, 'C');
            
            $pdf->Output('F', $ruta_archivo);
            return file_exists($ruta_archivo);
            
        } catch (Exception $e) {
            error_log('Error generando PDF con FPDF: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generar PDF usando FPDF directamente si está disponible
     */
    private static function generar_pdf_con_fpdf_directo($certificado, $ruta_archivo) {
        try {
            $pdf = new FPDF();
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            
            // Título
            $pdf->Cell(0, 10, 'CERTIFICADO', 0, 1, 'C');
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, 'de Participación y Aprobación', 0, 1, 'C');
            $pdf->Ln(10);
            
            // Contenido
            $pdf->Cell(0, 10, 'Se certifica que', 0, 1, 'C');
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, $certificado->nombre, 0, 1, 'C');
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, 'ha participado exitosamente en', 0, 1, 'C');
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, $certificado->actividad, 0, 1, 'C');
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, 'realizado el día', 0, 1, 'C');
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, date('d/m/Y', strtotime($certificado->fecha)), 0, 1, 'C');
            $pdf->Ln(10);
            
            // Código
            $pdf->SetFont('Courier', '', 10);
            $pdf->Cell(0, 10, 'Código de Validación: ' . $certificado->codigo_unico, 0, 1, 'C');
            
            $pdf->Output('F', $ruta_archivo);
            return file_exists($ruta_archivo);
            
        } catch (Exception $e) {
            error_log('Error generando PDF con FPDF: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generar PDF simple directamente sin dependencias externas
     */
    private static function generar_pdf_simple_directo($certificado, $ruta_archivo) {
        // Intentar usar wkhtmltopdf si está disponible
        $html_content = self::generar_html_certificado($certificado);
        $html_temp = $ruta_archivo . '.temp.html';
        
        if (file_put_contents($html_temp, $html_content)) {
            $comando = "wkhtmltopdf --quiet --page-size A4 --margin-top 0.75in --margin-right 0.75in --margin-bottom 0.75in --margin-left 0.75in --encoding UTF-8 '$html_temp' '$ruta_archivo'";
            $resultado = shell_exec($comando);
            
            if (file_exists($ruta_archivo)) {
                if (file_exists($html_temp)) {
                    unlink($html_temp);
                }
                return true;
            }
        }
        
        if (file_exists($html_temp)) {
            unlink($html_temp);
        }
        
        // Si wkhtmltopdf no funciona, intentar con una librería PHP simple
        return self::generar_pdf_con_libreria_simple($certificado, $ruta_archivo);
    }
    
    /**
     * Generar PDF usando una librería PHP simple
     */
    private static function generar_pdf_con_libreria_simple($certificado, $ruta_archivo) {
        // Crear contenido PDF básico usando funciones nativas de PHP
        $pdf_content = self::generar_contenido_pdf_basico($certificado);
        
        // Intentar usar la librería mPDF si está disponible
        if (class_exists('mPDF')) {
            return self::generar_pdf_con_mpdf($certificado, $ruta_archivo);
        }
        
        // Si no hay librerías disponibles, crear un PDF básico
        return self::generar_pdf_basico_nativo($certificado, $ruta_archivo);
    }
    
    /**
     * Generar contenido PDF básico
     */
    private static function generar_contenido_pdf_basico($certificado) {
        $fecha_formateada = date('d/m/Y', strtotime($certificado->fecha));
        
        return "%PDF-1.4
1 0 obj
<<
/Type /Catalog
/Pages 2 0 R
>>
endobj

2 0 obj
<<
/Type /Pages
/Kids [3 0 R]
/Count 1
>>
endobj

3 0 obj
<<
/Type /Page
/Parent 2 0 R
/MediaBox [0 0 612 792]
/Contents 4 0 R
/Resources <<
/Font <<
/F1 5 0 R
>>
>>
>>
endobj

4 0 obj
<<
/Length 1000
>>
stream
BT
/F1 24 Tf
306 720 Td
(CERTIFICADO) Tj
ET
BT
/F1 12 Tf
306 680 Td
(de Participacion y Aprobacion) Tj
ET
BT
/F1 14 Tf
306 640 Td
(Se certifica que) Tj
ET
BT
/F1 16 Tf
306 600 Td
(" . $certificado->nombre . ") Tj
ET
BT
/F1 12 Tf
306 560 Td
(ha participado exitosamente en) Tj
ET
BT
/F1 14 Tf
306 520 Td
(" . $certificado->actividad . ") Tj
ET
BT
/F1 12 Tf
306 480 Td
(realizado el dia) Tj
ET
BT
/F1 14 Tf
306 440 Td
(" . $fecha_formateada . ") Tj
ET
BT
/F1 10 Tf
306 400 Td
(Codigo de Validacion: " . $certificado->codigo_unico . ") Tj
ET
endstream
endobj

5 0 obj
<<
/Type /Font
/Subtype /Type1
/BaseFont /Helvetica
>>
endobj

xref
0 6
0000000000 65535 f 
0000000009 00000 n 
0000000058 00000 n 
0000000115 00000 n 
0000000250 00000 n 
0000000350 00000 n 
trailer
<<
/Size 6
/Root 1 0 R
>>
startxref
4000
%%EOF";
    }
    
    /**
     * Generar PDF básico nativo
     */
    private static function generar_pdf_basico_nativo($certificado, $ruta_archivo) {
        // Crear un PDF muy básico usando funciones nativas
        $fecha_formateada = date('d/m/Y', strtotime($certificado->fecha));
        
        $pdf_content = "%PDF-1.4
1 0 obj
<<
/Type /Catalog
/Pages 2 0 R
>>
endobj

2 0 obj
<<
/Type /Pages
/Kids [3 0 R]
/Count 1
>>
endobj

3 0 obj
<<
/Type /Page
/Parent 2 0 R
/MediaBox [0 0 612 792]
/Contents 4 0 R
/Resources <<
/Font <<
/F1 5 0 R
>>
>>
>>
endobj

4 0 obj
<<
/Length 500
>>
stream
BT
/F1 24 Tf
306 720 Td
(CERTIFICADO) Tj
ET
BT
/F1 12 Tf
306 680 Td
(de Participacion) Tj
ET
BT
/F1 14 Tf
306 640 Td
(Se certifica que) Tj
ET
BT
/F1 16 Tf
306 600 Td
(" . $certificado->nombre . ") Tj
ET
BT
/F1 12 Tf
306 560 Td
(ha participado en) Tj
ET
BT
/F1 14 Tf
306 520 Td
(" . $certificado->actividad . ") Tj
ET
BT
/F1 12 Tf
306 480 Td
(realizado el dia) Tj
ET
BT
/F1 14 Tf
306 440 Td
(" . $fecha_formateada . ") Tj
ET
BT
/F1 10 Tf
306 400 Td
(Codigo: " . $certificado->codigo_unico . ") Tj
ET
endstream
endobj

5 0 obj
<<
/Type /Font
/Subtype /Type1
/BaseFont /Helvetica
>>
endobj

xref
0 6
0000000000 65535 f 
0000000009 00000 n 
0000000058 00000 n 
0000000115 00000 n 
0000000250 00000 n 
0000000350 00000 n 
trailer
<<
/Size 6
/Root 1 0 R
>>
startxref
2000
%%EOF";
        
        return file_put_contents($ruta_archivo, $pdf_content) !== false;
    }
    
    /**
     * Generar PDF usando Dompdf
     */
    private static function generar_pdf_con_dompdf($certificado, $ruta_archivo) {
        try {
            // Verificar si Dompdf está disponible
            $dompdf_path = plugin_dir_path(__FILE__) . 'libs/dompdf/src/Dompdf.php';
            
            if (!file_exists($dompdf_path)) {
                error_log('CertificadosPersonalizadosPDF: Dompdf no encontrado en: ' . $dompdf_path);
                return false;
            }
            
            // Incluir todas las dependencias necesarias de Dompdf
            require_once $dompdf_path;
            require_once plugin_dir_path(__FILE__) . 'libs/dompdf/src/Options.php';
            require_once plugin_dir_path(__FILE__) . 'libs/dompdf/src/Canvas.php';
            require_once plugin_dir_path(__FILE__) . 'libs/dompdf/src/CanvasFactory.php';
            require_once plugin_dir_path(__FILE__) . 'libs/dompdf/src/Exception.php';
            require_once plugin_dir_path(__FILE__) . 'libs/dompdf/src/Adapter/CPDF.php';
            require_once plugin_dir_path(__FILE__) . 'libs/dompdf/src/Adapter/GD.php';
            require_once plugin_dir_path(__FILE__) . 'libs/dompdf/src/Helpers.php';
            require_once plugin_dir_path(__FILE__) . 'libs/dompdf/src/FontMetrics.php';
            require_once plugin_dir_path(__FILE__) . 'libs/dompdf/src/Frame.php';
            require_once plugin_dir_path(__FILE__) . 'libs/dompdf/src/LineBox.php';
            require_once plugin_dir_path(__FILE__) . 'libs/dompdf/src/Renderer.php';
            
            // Verificar que las clases principales estén disponibles
            if (!class_exists('\\Dompdf\\Dompdf')) {
                error_log('CertificadosPersonalizadosPDF: Clase Dompdf no encontrada después de incluir archivos');
                return false;
            }
            
            if (!class_exists('\\Dompdf\\Adapter\\CPDF')) {
                error_log('CertificadosPersonalizadosPDF: Clase CPDF no encontrada después de incluir archivos');
                return false;
            }
            
            // Generar HTML del certificado
            $html_content = self::generar_html_certificado($certificado);
            
            // Configurar Dompdf con opciones más básicas
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isPhpEnabled', false);
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'Arial');
            $options->set('defaultPaperSize', 'a4');
            $options->set('defaultPaperOrientation', 'portrait');
            
            // Crear instancia de Dompdf
            $dompdf = new \Dompdf\Dompdf($options);
            
            // Cargar HTML
            $dompdf->loadHtml($html_content);
            
            // Renderizar PDF
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            // Guardar PDF
            $output = $dompdf->output();
            
            if (file_put_contents($ruta_archivo, $output)) {
                error_log('CertificadosPersonalizadosPDF: PDF generado exitosamente con Dompdf para: ' . basename($ruta_archivo));
                return true;
            } else {
                error_log('CertificadosPersonalizadosPDF: Error guardando PDF con Dompdf: ' . $ruta_archivo);
                return false;
            }
            
        } catch (Exception $e) {
            error_log('CertificadosPersonalizadosPDF: Error con Dompdf: ' . $e->getMessage());
            error_log('CertificadosPersonalizadosPDF: Stack trace: ' . $e->getTraceAsString());
            return false;
        }
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
        
        // Si el pdf_path termina en .pdf pero el archivo no existe, buscar el .html correspondiente
        if (strpos($certificado->pdf_path, '.pdf') !== false) {
            $html_path = str_replace('.pdf', '.html', $local_path);
            if (file_exists($html_path)) {
                $html_url = str_replace('.pdf', '.html', $certificado->pdf_path);
                return $html_url;
            }
        }
        
        // Si el pdf_path termina en .html pero el archivo no existe, buscar el .pdf correspondiente
        if (strpos($certificado->pdf_path, '.html') !== false) {
            $pdf_path = str_replace('.html', '.pdf', $local_path);
            if (file_exists($pdf_path)) {
                $pdf_url = str_replace('.html', '.pdf', $certificado->pdf_path);
                return $pdf_url;
            }
        }
        
        return $certificado->pdf_path;
    }
    
    /**
     * Verificar si existe el PDF
     */
    public static function existe_pdf($certificado_id) {
        $certificado = CertificadosPersonalizadosBD::obtener_certificado($certificado_id);
        
        if (!$certificado || empty($certificado->pdf_path)) {
            return false;
        }
        
        // Convert URL to local path for file_exists check
        $upload_dir = wp_upload_dir();
        $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $certificado->pdf_path);
        
        // Verificar si existe el archivo directamente (ya sea PDF o HTML)
        if (file_exists($local_path)) {
            return true;
        }
        
        // Buscar archivos con doble extensión y limpiarlos
        $nombre_base = 'certificado_' . $certificado->codigo_unico;
        $certificados_dir = $upload_dir['basedir'] . '/certificados/';
        
        // Buscar archivos con doble extensión
        $archivos_dobles = glob($certificados_dir . $nombre_base . '.*.*');
        foreach ($archivos_dobles as $archivo_doble) {
            $extension = pathinfo($archivo_doble, PATHINFO_EXTENSION);
            if ($extension === 'html') {
                // Renombrar archivo .pdf.html a .html
                $nuevo_nombre = str_replace('.pdf.html', '.html', $archivo_doble);
                rename($archivo_doble, $nuevo_nombre);
                error_log('CertificadosPersonalizadosPDF: Archivo renombrado de ' . basename($archivo_doble) . ' a ' . basename($nuevo_nombre));
            }
        }
        
        // Si el pdf_path termina en .pdf pero el archivo no existe, buscar el .html correspondiente
        if (strpos($certificado->pdf_path, '.pdf') !== false) {
            $html_path = str_replace('.pdf', '.html', $local_path);
            if (file_exists($html_path)) {
                return true;
            }
        }
        
        // Si el pdf_path termina en .html pero el archivo no existe, buscar el .pdf correspondiente
        if (strpos($certificado->pdf_path, '.html') !== false) {
            $pdf_path = str_replace('.html', '.pdf', $local_path);
            if (file_exists($pdf_path)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Regenerar PDF para certificados existentes
     */
    public static function regenerar_pdf_certificado($certificado_id) {
        return self::generar_certificado_pdf($certificado_id);
    }
    
    /**
     * Limpiar archivos con doble extensión
     */
    public static function limpiar_archivos_dobles() {
        $upload_dir = wp_upload_dir();
        $certificados_dir = $upload_dir['basedir'] . '/certificados/';
        
        if (!file_exists($certificados_dir)) {
            return false;
        }
        
        $archivos_limpiados = 0;
        
        // Buscar todos los archivos con doble extensión
        $archivos_dobles = glob($certificados_dir . '*.pdf.html');
        foreach ($archivos_dobles as $archivo_doble) {
            $nuevo_nombre = str_replace('.pdf.html', '.html', $archivo_doble);
            if (rename($archivo_doble, $nuevo_nombre)) {
                $archivos_limpiados++;
                error_log('CertificadosPersonalizadosPDF: Archivo limpiado: ' . basename($archivo_doble) . ' -> ' . basename($nuevo_nombre));
            }
        }
        
        return $archivos_limpiados;
    }
} 