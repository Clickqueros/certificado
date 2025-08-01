<?php
/**
 * Funciones para generación de PDF - Plugin Certificados Personalizados
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
            return false;
        }
        
        // Crear directorio si no existe
        $upload_dir = wp_upload_dir();
        $certificados_dir = $upload_dir['basedir'] . '/certificados/';
        
        if (!file_exists($certificados_dir)) {
            wp_mkdir_p($certificados_dir);
        }
        
        // Generar nombre del archivo
        $nombre_archivo = 'certificado_' . $certificado->codigo_unico . '.pdf';
        $ruta_completa = $certificados_dir . $nombre_archivo;
        
        // Generar contenido HTML del certificado
        $html_content = self::generar_html_certificado($certificado);
        
        // Generar PDF usando TCPDF
        $pdf_generado = self::generar_pdf_desde_html($html_content, $ruta_completa);
        
        if ($pdf_generado) {
            // Actualizar la ruta en la base de datos
            $actualizado = CertificadosPersonalizadosBD::actualizar_certificado($certificado_id, array(
                'pdf_path' => $ruta_completa
            ));
            
            return $actualizado;
        }
        
        return false;
    }
    
    /**
     * Generar HTML del certificado
     */
    private static function generar_html_certificado($certificado) {
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
    private static function generar_plantilla_por_defecto($certificado, $tipo_actividad) {
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
        // Crear un archivo HTML temporal
        $html_temp = $ruta_archivo . '.html';
        file_put_contents($html_temp, $html_content);
        
        // Intentar convertir usando wkhtmltopdf si está disponible
        if (function_exists('shell_exec')) {
            $comando = "wkhtmltopdf --quiet --page-size A4 --margin-top 0.75in --margin-right 0.75in --margin-bottom 0.75in --margin-left 0.75in --encoding UTF-8 '$html_temp' '$ruta_archivo'";
            shell_exec($comando);
            
            // Limpiar archivo temporal
            if (file_exists($html_temp)) {
                unlink($html_temp);
            }
            
            return file_exists($ruta_archivo);
        }
        
        // Si no hay conversor disponible, crear un archivo HTML
        $html_final = $ruta_archivo . '.html';
        file_put_contents($html_final, $html_content);
        
        return file_exists($html_final);
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
        $ruta_relativa = str_replace($upload_dir['basedir'], '', $certificado->pdf_path);
        
        return $upload_dir['baseurl'] . $ruta_relativa;
    }
    
    /**
     * Verificar si existe el PDF
     */
    public static function existe_pdf($certificado_id) {
        $certificado = CertificadosPersonalizadosBD::obtener_certificado($certificado_id);
        
        if (!$certificado || empty($certificado->pdf_path)) {
            return false;
        }
        
        return file_exists($certificado->pdf_path);
    }
} 