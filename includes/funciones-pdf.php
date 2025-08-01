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
        $nombre_archivo = 'certificado_' . $certificado->codigo_unico;
        $ruta_completa = $certificados_dir . $nombre_archivo . '.pdf';
        
        // Generar contenido HTML del certificado
        $html_content = self::generar_html_certificado($certificado);
        
        // Generar PDF usando TCPDF
        $pdf_generado = self::generar_pdf_desde_html($html_content, $ruta_completa);
        
        if ($pdf_generado) {
            // Determinar la extensión del archivo generado
            $extension = '.pdf';
            $nombre_final = $nombre_archivo . '.pdf';
            
            if (file_exists($ruta_completa . '.html')) {
                $extension = '.html';
                $nombre_final = $nombre_archivo . '.html';
            }
            
            // Actualizar la ruta en la base de datos
            $actualizado = CertificadosPersonalizadosBD::actualizar_certificado($certificado_id, array(
                'pdf_path' => $upload_dir['baseurl'] . '/certificados/' . $nombre_final
            ));
            
            if ($actualizado) {
                error_log('CertificadosPersonalizadosPDF: PDF generado exitosamente para ID: ' . $certificado_id);
            } else {
                error_log('CertificadosPersonalizadosPDF: Error actualizando BD para ID: ' . $certificado_id);
            }
            
            return $actualizado;
        } else {
            error_log('CertificadosPersonalizadosPDF: Error generando PDF para ID: ' . $certificado_id);
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
                // Si no está disponible, usar una alternativa simple
                return self::generar_pdf_simple($html_content, $ruta_archivo);
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
} 