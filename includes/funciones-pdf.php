<?php
/**
 * Funciones para generación de PDF - Plugin Certificados Antecore
 * VERSIÓN CON TCPDF - Librería principal para generación de PDF
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para manejo de PDF
 */
class CertificadosAntecorePDF {
    
    /**
     * Generar certificado en PDF
     */
    public static function generar_certificado_pdf($certificado_id) {
        // Obtener datos del certificado
        $certificado = CertificadosAntecoreBD::obtener_certificado($certificado_id);
        
        if (!$certificado) {
            error_log('CertificadosAntecorePDF: No se pudo obtener certificado ID: ' . $certificado_id);
            return false;
        }
        
        // Crear directorio si no existe
        $upload_dir = wp_upload_dir();
        $certificados_dir = $upload_dir['basedir'] . '/certificados/';
        
        if (!file_exists($certificados_dir)) {
            $creado = wp_mkdir_p($certificados_dir);
            if (!$creado) {
                error_log('CertificadosAntecorePDF: No se pudo crear directorio: ' . $certificados_dir);
                return false;
            }
        }
        
        // Generar nombre del archivo
        $nombre_base = 'certificado_' . $certificado->codigo_unico;
        $ruta_pdf = $certificados_dir . $nombre_base . '.pdf';
        
        // Eliminar archivo anterior si existe para forzar regeneración
        if (file_exists($ruta_pdf)) {
            unlink($ruta_pdf);
            error_log('CertificadosAntecorePDF: Archivo anterior eliminado: ' . $ruta_pdf);
        }
        
        // También eliminar archivo HTML si existe
        $ruta_html = $certificados_dir . $nombre_base . '.html';
        if (file_exists($ruta_html)) {
            unlink($ruta_html);
            error_log('CertificadosAntecorePDF: Archivo HTML anterior eliminado: ' . $ruta_html);
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
            
            $actualizado = CertificadosAntecoreBD::actualizar_certificado($certificado_id, array(
                'pdf_path' => $url_con_timestamp
            ));
            
            if ($actualizado) {
                error_log('CertificadosAntecorePDF: Archivo generado exitosamente para ID: ' . $certificado_id . ' - Extensión: ' . $extension . ' - Timestamp: ' . $timestamp);
            } else {
                error_log('CertificadosAntecorePDF: Error actualizando BD para ID: ' . $certificado_id);
            }
            
            return $url_con_timestamp;
        } else {
            error_log('CertificadosAntecorePDF: Error generando archivo para ID: ' . $certificado_id);
        }
        
        return false;
    }
    
    /**
     * Verificar si el PDF está actualizado
     */
    public static function verificar_pdf_actualizado($certificado_id) {
        $certificado = CertificadosAntecoreBD::obtener_certificado($certificado_id);
        
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
                error_log('CertificadosAntecorePDF: PDF verificado correctamente para ID: ' . $certificado_id . ' - Tamaño: ' . filesize($ruta_completa) . ' bytes - Nombre encontrado: ' . $certificado->nombre);
                return true;
            } else {
                error_log('CertificadosAntecorePDF: PDF no contiene el nombre actualizado para ID: ' . $certificado_id . ' - Nombre esperado: ' . $certificado->nombre);
                return false;
            }
        } else {
            error_log('CertificadosAntecorePDF: PDF no encontrado o vacío para ID: ' . $certificado_id . ' - Ruta: ' . $ruta_completa);
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

        // Convertir ruta relativa de imagen a ruta absoluta para TCPDF
        $imagen_path = plugin_dir_path(__FILE__) . '../images/bg-pdf-antecore.jpg';
        if (file_exists($imagen_path)) {
            $html = str_replace("url('images/bg-pdf-antecore.jpg')", "url('" . $imagen_path . "')", $html);
        }
        
        // Obtener información del tipo de certificado
        $info_certificado = self::obtener_info_certificado($certificado->tipo_certificado);
        
        // Calcular fecha de vencimiento según el tipo
        $fecha_vencimiento = self::calcular_fecha_vencimiento($certificado->fecha_aprobacion, $certificado->tipo_certificado);
        
        // Reemplazar placeholders del certificado de GLP
        $html = str_replace('[NOMBRE_INSTALACION]', htmlspecialchars($certificado->nombre_instalacion), $html);
        $html = str_replace('[DIRECCION_INSTALACION]', htmlspecialchars($certificado->direccion_instalacion), $html);
        $html = str_replace('[RAZON_SOCIAL]', htmlspecialchars(strtoupper($certificado->razon_social)), $html);
        $html = str_replace('[NIT]', htmlspecialchars($certificado->nit), $html);
        $html = str_replace('[NUMERO_CERTIFICADO]', $certificado->tipo_certificado . '-' . str_pad($certificado->numero_certificado, 3, '0', STR_PAD_LEFT), $html);
        $html = str_replace('[FECHA_APROBACION]', date('d-m-Y', strtotime($certificado->fecha_aprobacion)), $html);
        $html = str_replace('[FECHA_VENCIMIENTO]', date('d-m-Y', strtotime($fecha_vencimiento)), $html);
        
        // Formatear capacidad con puntos de miles automáticamente
        $capacidad_formateada = number_format($certificado->capacidad_almacenamiento, 0, ',', '.');
        $html = str_replace('[CAPACIDAD_ALMACENAMIENTO]', htmlspecialchars($capacidad_formateada), $html);
        $html = str_replace('[NUMERO_TANQUES]', htmlspecialchars($certificado->numero_tanques), $html);
        
        // Reemplazar alcance y requisitos según el tipo de certificado
        $html = str_replace('[ALCANCE_CERTIFICADO]', htmlspecialchars($info_certificado['alcance']), $html);
        // No usar htmlspecialchars en requisitos porque contiene <br> para saltos de línea
        $html = str_replace('[REQUISITOS_CERTIFICADO]', $info_certificado['requisitos'], $html);
        
        return $html;
    }
    
    /**
     * Obtener información del tipo de certificado
     */
    private static function obtener_info_certificado($tipo_certificado) {
        $info_certificados = array(
            'PAGLP' => array(
                'alcance' => 'Certificación de Planta de Almacenamiento de GLP para redes de distribución.',
                'requisitos' => 'Resolución 40246 de marzo de 2016 del Ministerio de Minas y Energía Capítulo I –' . '<br>' . 
                              'Capítulo II Artículos 6, 7 y 8' . '<br>' . 
                              'Resolución 40867 de septiembre de 2016 del Ministerio de Minas y Energía'
            ),
            'TEGLP' => array(
                'alcance' => 'Certificación de Tanque Estacionario de GLP.',
                'requisitos' => 'Resolución 40246 de marzo de 2016 del Ministerio de Minas y Energía Capítulo I /' . '<br>' . 
                              'Capítulo III Artículos 9,10 y 11' . '<br>' . 
                              'Resolución 40867 de septiembre de 2016 del Ministerio de Minas y Energía'
            ),
            'PEGLP' => array(
                'alcance' => 'Certificación de Planta de Envasado de cilindros de GLP.',
                'requisitos' => 'Resolución 40247 de marzo de 2016 del Ministerio de Minas y Energía' . '<br>' . 
                              'Resolución 40868 de septiembre de 2016 del Ministerio de Minas y Energía'
            ),
            'DEGLP' => array(
                'alcance' => 'Certificación de Tanque Estacionario de GLP',
                'requisitos' => 'Resolución 40248 de marzo de 2016 del Ministerio de Minas y Energía Capítulo I /' . '<br>' . 
                              'Capítulo III Artículos 6, 7 y 8' . '<br>' . 
                              'Resolución 40869 de septiembre de 2016 del Ministerio de Minas y Energía'
            ),
            'PVGLP' => array(
                'alcance' => 'Certificación de Punto de Venta de Cilindros de GLP',
                'requisitos' => 'Resolución 40248 de marzo de 2016 del Ministerio de Minas y Energía Capítulo I /' . '<br>' . 
                              'Capítulo III Artículos 9,10 y 11' . '<br>' . 
                              'Resolución 40869 de septiembre de 2016 del Ministerio de Minas y Energía'
            )
        );
        
        return isset($info_certificados[$tipo_certificado]) ? $info_certificados[$tipo_certificado] : $info_certificados['PAGLP'];
    }
    
    /**
     * Calcular fecha de vencimiento según el tipo de certificado
     * La fecha de vencimiento será un día antes de la fecha calculada (X años después)
     */
    private static function calcular_fecha_vencimiento($fecha_aprobacion, $tipo_certificado) {
        // Certificados con vigencia de 5 años
        $certificados_5_anos = array('PAGLP', 'TEGLP', 'PEGLP');
        
        // Certificados con vigencia de 3 años
        $certificados_3_anos = array('DEGLP', 'PVGLP');
        
        if (in_array($tipo_certificado, $certificados_5_anos)) {
            // Sumar 5 años y restar 1 día
            return date('Y-m-d', strtotime($fecha_aprobacion . ' +5 years -1 day'));
        } elseif (in_array($tipo_certificado, $certificados_3_anos)) {
            // Sumar 3 años y restar 1 día
            return date('Y-m-d', strtotime($fecha_aprobacion . ' +3 years -1 day'));
        } else {
            // Por defecto, 5 años y restar 1 día
            return date('Y-m-d', strtotime($fecha_aprobacion . ' +5 years -1 day'));
        }
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
     * Asegurar que las fuentes DIN Pro estén convertidas a formato TCPDF
     * Retorna array con los nombres de las fuentes convertidas
     */
    private static function asegurar_fuentes_dinpro() {
        // Incluir TCPDF si no está incluido
        $tcpdf_path = plugin_dir_path(__FILE__) . 'libs/tcpdf/tcpdf.php';
        if (file_exists($tcpdf_path) && !class_exists('TCPDF_FONTS')) {
            require_once $tcpdf_path;
        }
        
        if (!class_exists('TCPDF_FONTS')) {
            error_log('CertificadosAntecorePDF: TCPDF_FONTS no disponible');
            return array('regular' => null, 'bold' => null);
        }
        
        $fonts_dir = plugin_dir_path(__FILE__) . 'libs/tcpdf/fonts/';
        $source_fonts_dir = plugin_dir_path(__FILE__) . '../templates/fonts/';
        
        // Asegurar que el directorio de fuentes existe
        if (!file_exists($fonts_dir)) {
            wp_mkdir_p($fonts_dir);
        }
        
        // Definir K_PATH_FONTS si no está definido
        if (!defined('K_PATH_FONTS')) {
            define('K_PATH_FONTS', $fonts_dir);
            error_log('CertificadosAntecorePDF: K_PATH_FONTS definido como: ' . $fonts_dir);
        }
        
        // Verificar que el directorio de fuentes fuente existe
        if (!file_exists($source_fonts_dir)) {
            error_log('CertificadosAntecorePDF: Directorio de fuentes fuente no existe: ' . $source_fonts_dir);
            return array('regular' => null, 'bold' => null);
        }
        
        $font_names = array('regular' => null, 'bold' => null);
        
        // Verificar y convertir DIN Pro Regular
        // Buscar archivos .php y .z para verificar que la fuente ya está convertida
        $dinpro_regular_name = null;
        $dinpro_php_files = glob($fonts_dir . 'dinpro*.php');
        foreach ($dinpro_php_files as $file) {
            $basename = basename($file, '.php');
            // Excluir versión bold
            if (stripos($basename, 'bold') === false) {
                // Verificar que también existe el archivo .z correspondiente
                $z_file = $fonts_dir . $basename . '.z';
                if (file_exists($z_file)) {
                    $dinpro_regular_name = $basename;
                    error_log('CertificadosAntecorePDF: DIN Pro Regular ya existe: ' . $dinpro_regular_name . ' (archivos: ' . $basename . '.php, ' . $basename . '.z)');
                    $font_names['regular'] = $dinpro_regular_name;
                    break;
                }
            }
        }
        
        // Si no existe, convertir desde .ttf (preferido) o .otf (fallback)
        if (!$dinpro_regular_name) {
            // Intentar primero con .ttf (TrueType, compatible con TCPDF)
            $source_font = $source_fonts_dir . 'dinpro.ttf';
            $font_ext = '.ttf';
            
            // Si no existe .ttf, intentar con .otf como fallback
            if (!file_exists($source_font)) {
                $source_font = $source_fonts_dir . 'dinpro.otf';
                $font_ext = '.otf';
            }
            
            if (file_exists($source_font)) {
                error_log('CertificadosAntecorePDF: Convirtiendo DIN Pro Regular desde: ' . $source_font . ' (formato: ' . $font_ext . ')');
                error_log('CertificadosAntecorePDF: Directorio de destino: ' . $fonts_dir);
                error_log('CertificadosAntecorePDF: Permisos del directorio: ' . substr(sprintf('%o', fileperms($fonts_dir)), -4));
                
                // Verificar si es OpenType CFF (no compatible)
                $font_content = file_get_contents($source_font, false, null, 0, 12);
                if (substr($font_content, 0, 4) == 'OTTO') {
                    error_log('CertificadosAntecorePDF: ADVERTENCIA - El archivo es OpenType CFF (PostScript), TCPDF no lo soporta. Necesitas convertir a .ttf primero.');
                }
                
                // Convertir la fuente usando addTTFfont
                $font_name = TCPDF_FONTS::addTTFfont($source_font, 'TrueTypeUnicode', '', 32, $fonts_dir);
                
                if ($font_name !== false && !empty($font_name)) {
                    error_log('CertificadosAntecorePDF: DIN Pro Regular convertido exitosamente como: ' . $font_name);
                    // Verificar que los archivos se crearon
                    $php_file = $fonts_dir . $font_name . '.php';
                    $z_file = $fonts_dir . $font_name . '.z';
                    if (file_exists($php_file) && file_exists($z_file)) {
                        error_log('CertificadosAntecorePDF: Archivos de fuente verificados: ' . $font_name . '.php y ' . $font_name . '.z');
                        $font_names['regular'] = $font_name;
                    } else {
                        error_log('CertificadosAntecorePDF: ADVERTENCIA - Archivos de fuente no encontrados después de conversión');
                    }
                } else {
                    error_log('CertificadosAntecorePDF: ERROR al convertir DIN Pro Regular - addTTFfont retornó: ' . var_export($font_name, true));
                    if ($font_ext == '.otf') {
                        error_log('CertificadosAntecorePDF: SUGERENCIA - El archivo .otf puede ser OpenType CFF. Intenta convertir a .ttf usando FontForge o convertidores online.');
                    }
                }
            } else {
                error_log('CertificadosAntecorePDF: Archivo fuente no encontrado (buscado: dinpro.ttf y dinpro.otf en ' . $source_fonts_dir . ')');
            }
        }
        
        // Verificar y convertir DIN Pro Bold
        $dinpro_bold_name = null;
        // Buscar archivos que puedan ser DIN Pro Bold
        // TCPDF puede generar nombres como: dinpro_b, dinprobold, dinpro_bold, etc.
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
                // Verificar que también existe el archivo .z correspondiente
                $z_file = $fonts_dir . $basename . '.z';
                if (file_exists($z_file)) {
                    $dinpro_bold_name = $basename;
                    error_log('CertificadosAntecorePDF: DIN Pro Bold ya existe: ' . $dinpro_bold_name . ' (archivos: ' . $basename . '.php, ' . $basename . '.z)');
                    $font_names['bold'] = $dinpro_bold_name;
                    break;
                }
            }
        }
        
        // Si no existe, convertir desde .ttf (preferido) o .otf (fallback)
        if (!$dinpro_bold_name) {
            // Intentar primero con .ttf (TrueType, compatible con TCPDF)
            $source_font = $source_fonts_dir . 'dinpro_bold.ttf';
            $font_ext = '.ttf';
            
            // Si no existe .ttf, intentar con .otf como fallback
            if (!file_exists($source_font)) {
                $source_font = $source_fonts_dir . 'dinpro_bold.otf';
                $font_ext = '.otf';
            }
            
            if (file_exists($source_font)) {
                error_log('CertificadosAntecorePDF: Convirtiendo DIN Pro Bold desde: ' . $source_font . ' (formato: ' . $font_ext . ')');
                error_log('CertificadosAntecorePDF: Directorio de destino: ' . $fonts_dir);
                
                // Verificar si es OpenType CFF (no compatible)
                $font_content = file_get_contents($source_font, false, null, 0, 12);
                if (substr($font_content, 0, 4) == 'OTTO') {
                    error_log('CertificadosAntecorePDF: ADVERTENCIA - El archivo es OpenType CFF (PostScript), TCPDF no lo soporta. Necesitas convertir a .ttf primero.');
                }
                
                // Convertir la fuente usando addTTFfont
                $font_name = TCPDF_FONTS::addTTFfont($source_font, 'TrueTypeUnicode', '', 32, $fonts_dir);
                
                if ($font_name !== false && !empty($font_name)) {
                    error_log('CertificadosAntecorePDF: DIN Pro Bold convertido exitosamente como: ' . $font_name);
                    // Verificar que los archivos se crearon
                    $php_file = $fonts_dir . $font_name . '.php';
                    $z_file = $fonts_dir . $font_name . '.z';
                    if (file_exists($php_file) && file_exists($z_file)) {
                        error_log('CertificadosAntecorePDF: Archivos de fuente verificados: ' . $font_name . '.php y ' . $font_name . '.z');
                        $font_names['bold'] = $font_name;
                    } else {
                        error_log('CertificadosAntecorePDF: ADVERTENCIA - Archivos de fuente no encontrados después de conversión');
                    }
                } else {
                    error_log('CertificadosAntecorePDF: ERROR al convertir DIN Pro Bold - addTTFfont retornó: ' . var_export($font_name, true));
                    if ($font_ext == '.otf') {
                        error_log('CertificadosAntecorePDF: SUGERENCIA - El archivo .otf puede ser OpenType CFF. Intenta convertir a .ttf usando FontForge o convertidores online.');
                    }
                }
            } else {
                error_log('CertificadosAntecorePDF: Archivo fuente no encontrado (buscado: dinpro_bold.ttf y dinpro_bold.otf en ' . $source_fonts_dir . ')');
            }
        }
        
        return $font_names;
    }
    
    /**
     * Generar PDF con TCPDF
     */
    private static function generar_pdf_con_tcpdf($certificado, $ruta_archivo) {
        try {
            // Definir K_PATH_FONTS ANTES de incluir TCPDF
            // Esto indica a TCPDF dónde buscar las fuentes convertidas (.php y .z)
            $fonts_dir = plugin_dir_path(__FILE__) . 'libs/tcpdf/fonts/';
            if (!defined('K_PATH_FONTS')) {
                define('K_PATH_FONTS', $fonts_dir);
                error_log('CertificadosAntecorePDF: K_PATH_FONTS definido como: ' . $fonts_dir);
            }
            
            // Verificar si TCPDF está disponible
            $tcpdf_path = plugin_dir_path(__FILE__) . 'libs/tcpdf/tcpdf.php';
            
            if (!file_exists($tcpdf_path)) {
                error_log('CertificadosAntecorePDF: TCPDF no encontrado en: ' . $tcpdf_path);
                return false;
            }
            
            // Incluir TCPDF (K_PATH_FONTS ya debe estar definido)
            require_once $tcpdf_path;
            
            // Verificar que la clase esté disponible
            if (!class_exists('TCPDF')) {
                error_log('CertificadosAntecorePDF: Clase TCPDF no encontrada después de incluir archivos');
                return false;
            }
            
            // Asegurar que las fuentes DIN Pro estén convertidas y obtener sus nombres exactos
            $font_names = self::asegurar_fuentes_dinpro();
            
            // Crear nueva instancia de TCPDF
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Registrar fuentes DIN Pro directamente después de crear TCPDF
            // Si ya están convertidas, usamos los nombres retornados; si no, addTTFfont las convertirá automáticamente
            $source_fonts_dir = plugin_dir_path(__FILE__) . '../templates/fonts/';
            $font_regular = null;
            $font_bold = null;
            $dinpro_registered = false;
            $dinprobold_registered = false;
            
            // Si ya tenemos los nombres de fuentes convertidas, usarlas
            if (!empty($font_names['regular'])) {
                $font_regular = $font_names['regular'];
                error_log('CertificadosAntecorePDF: Usando fuente DIN Pro Regular ya convertida: ' . $font_regular);
            } else {
                // Si no está convertida, convertirla ahora (buscar .ttf primero, luego .otf)
                $source_font_regular = $source_fonts_dir . 'dinpro.ttf';
                if (!file_exists($source_font_regular)) {
                    $source_font_regular = $source_fonts_dir . 'dinpro.otf';
                }
                if (file_exists($source_font_regular)) {
                    error_log('CertificadosAntecorePDF: Convirtiendo DIN Pro Regular desde: ' . $source_font_regular);
                    $font_regular = TCPDF_FONTS::addTTFfont($source_font_regular, 'TrueTypeUnicode', '', 32, $fonts_dir);
                    if ($font_regular !== false && !empty($font_regular)) {
                        error_log('CertificadosAntecorePDF: DIN Pro Regular convertido exitosamente como: ' . $font_regular);
                    } else {
                        error_log('CertificadosAntecorePDF: ERROR al convertir DIN Pro Regular');
                        $font_regular = null;
                    }
                } else {
                    error_log('CertificadosAntecorePDF: Archivo fuente no encontrado (buscado: dinpro.ttf y dinpro.otf)');
                }
            }
            
            // Registrar DIN Pro Regular si está disponible
            if (!empty($font_regular)) {
                try {
                    // Registrar usando la ruta completa del archivo .php
                    $font_regular_file = $fonts_dir . $font_regular . '.php';
                    if (file_exists($font_regular_file)) {
                        // Registrar como 'dinpro' para uso en CSS - AddFont(alias, estilo, ruta_completa_archivo)
                        $pdf->AddFont('dinpro', '', $font_regular_file);
                        error_log('CertificadosAntecorePDF: Fuente DIN Pro Regular registrada como "dinpro" desde archivo: ' . $font_regular_file);
                        $dinpro_registered = true;
                    } else {
                        error_log('CertificadosAntecorePDF: ERROR - Archivo de fuente no encontrado: ' . $font_regular_file);
                        // Intentar con solo el nombre base como fallback
                        $pdf->AddFont('dinpro', '', $font_regular);
                        error_log('CertificadosAntecorePDF: Fuente DIN Pro Regular registrada con nombre base: ' . $font_regular);
                        $dinpro_registered = true;
                    }
                } catch (Exception $e) {
                    error_log('CertificadosAntecorePDF: Error al registrar fuente dinpro desde ' . $font_regular . ': ' . $e->getMessage());
                }
            }
            
            // Si ya tenemos los nombres de fuentes convertidas, usarlas
            if (!empty($font_names['bold'])) {
                $font_bold = $font_names['bold'];
                error_log('CertificadosAntecorePDF: Usando fuente DIN Pro Bold ya convertida: ' . $font_bold);
            } else {
                // Si no está convertida, convertirla ahora (buscar .ttf primero, luego .otf)
                $source_font_bold = $source_fonts_dir . 'dinpro_bold.ttf';
                if (!file_exists($source_font_bold)) {
                    $source_font_bold = $source_fonts_dir . 'dinpro_bold.otf';
                }
                if (file_exists($source_font_bold)) {
                    error_log('CertificadosAntecorePDF: Convirtiendo DIN Pro Bold desde: ' . $source_font_bold);
                    $font_bold = TCPDF_FONTS::addTTFfont($source_font_bold, 'TrueTypeUnicode', '', 32, $fonts_dir);
                    if ($font_bold !== false && !empty($font_bold)) {
                        error_log('CertificadosAntecorePDF: DIN Pro Bold convertido exitosamente como: ' . $font_bold);
                    } else {
                        error_log('CertificadosAntecorePDF: ERROR al convertir DIN Pro Bold');
                        $font_bold = null;
                    }
                } else {
                    error_log('CertificadosAntecorePDF: Archivo fuente no encontrado (buscado: dinpro_bold.ttf y dinpro_bold.otf)');
                }
            }
            
            // Registrar DIN Pro Bold si está disponible
            if (!empty($font_bold)) {
                try {
                    // Registrar usando la ruta completa del archivo .php
                    $font_bold_file = $fonts_dir . $font_bold . '.php';
                    if (file_exists($font_bold_file)) {
                        // Registrar como 'dinprobold' para uso en CSS - AddFont(alias, estilo, ruta_completa_archivo)
                        $pdf->AddFont('dinpro', 'B', $font_bold_file);
                        error_log('CertificadosAntecorePDF: Fuente DIN Pro Bold registrada como "dinprobold" desde archivo: ' . $font_bold_file);
                        $dinprobold_registered = true;
                    } else {
                        error_log('CertificadosAntecorePDF: ERROR - Archivo de fuente no encontrado: ' . $font_bold_file);
                        // Intentar con solo el nombre base como fallback
                        $pdf->AddFont('dinpro', 'B', $font_bold);
                        error_log('CertificadosAntecorePDF: Fuente DIN Pro Bold registrada con nombre base: ' . $font_bold);
                        $dinprobold_registered = true;
                    }
                } catch (Exception $e) {
                    error_log('CertificadosAntecorePDF: Error al registrar fuente dinprobold desde ' . $font_bold . ': ' . $e->getMessage());
                }
            }
            
            // Log final del estado de registro
            if (!$dinpro_registered) {
                error_log('CertificadosAntecorePDF: ADVERTENCIA - No se pudo registrar DIN Pro Regular. Se usará helvetica como fallback.');
            }
            if (!$dinprobold_registered) {
                error_log('CertificadosAntecorePDF: ADVERTENCIA - No se pudo registrar DIN Pro Bold. Se usará helvetica como fallback.');
            }
            
            // Desactivar header y footer automáticos
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
            // Configurar información del documento
            $pdf->SetCreator('Certificados Personalizados');
            $pdf->SetAuthor('Sistema de Certificados');
            $pdf->SetTitle('Certificado de Participación');
            $pdf->SetSubject('Certificado para ' . $certificado->nombre_instalacion);
            
            // Configurar márgenes
            // Configurar página A4 horizontal
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetHeaderMargin(0);
            $pdf->SetFooterMargin(0);
            
            // Desactivar saltos de página automáticos para mantener en una sola página
            $pdf->SetAutoPageBreak(false);
            
            // Intentar usar dinpro como fuente por defecto solo si se registró correctamente
            if ($dinpro_registered) {
                try {
                    $pdf->SetFont('dinpro', '', 12);
                    error_log('CertificadosAntecorePDF: Fuente por defecto establecida como: dinpro');
                } catch (Exception $e) {
                    $pdf->SetFont('helvetica', '', 12);
                    error_log('CertificadosAntecorePDF: Fuente por defecto establecida como: helvetica (error al usar dinpro: ' . $e->getMessage() . ')');
                }
            } else {
                $pdf->SetFont('helvetica', '', 12);
                error_log('CertificadosAntecorePDF: Fuente por defecto establecida como: helvetica (dinpro no se registró correctamente)');
            }
            
            // Agregar página A4 horizontal
            $pdf->AddPage('L', 'A4');

                        /**
             * DIBUJAR IMAGEN DE FONDO
             * - Usa ruta absoluta (WP)
             * - Cubre toda la página
             * - Debe ir ANTES de escribir el HTML
             */
            $bg_path = plugin_dir_path(__FILE__) . '../images/bg-pdf-antecore.jpg';
            
            if (file_exists($bg_path) && is_readable($bg_path)) {
                // Opcional: calidad JPEG (si fuese JPG)
                if (function_exists('imagecreatefromjpeg')) {
                    $pdf->setJPEGQuality(95);
                }
                
                // Coordenadas y tamaño: cubrir página completa
                $pageW = $pdf->getPageWidth();
                $pageH = $pdf->getPageHeight();
                
                // Si quieres mantener márgenes pero que la imagen igual sea full-bleed, deja (0,0)
                $pdf->Image(
                    $bg_path,          // archivo
                    0,                 // x
                    0,                 // y
                    $pageW,            // ancho
                    $pageH,            // alto
                    '',                // type (auto)
                    '',                // link
                    '',                // align
                    false,             // resize
                    300,               // dpi
                    '',                // palign
                    false,             // ismask
                    false,             // imgmask
                    0,                 // border
                    false,             // fitbox
                    false,             // hidden
                    false              // fitonpage
                );
                
                // Asegura que el contenido HTML quede POR ENCIMA del fondo
                $pdf->setPageMark();
            }
            
            // Generar contenido del PDF
            $html_content = self::generar_html_certificado($certificado);
            
            // Convertir HTML a contenido para TCPDF (pasar información de fuentes registradas)
            $contenido_pdf = self::convertir_html_para_tcpdf($html_content, $dinpro_registered, $dinprobold_registered);
            
            // Escribir contenido
            $pdf->writeHTML($contenido_pdf, true, false, true, false, '');
            
            // Guardar PDF
            if ($pdf->Output($ruta_archivo, 'F')) {
                error_log('CertificadosAntecorePDF: PDF generado exitosamente con TCPDF para: ' . basename($ruta_archivo));
                return true;
            } else {
                error_log('CertificadosAntecorePDF: Error guardando PDF con TCPDF: ' . $ruta_archivo);
                return false;
            }
            
        } catch (Exception $e) {
            error_log('CertificadosAntecorePDF: Error con TCPDF: ' . $e->getMessage());
            error_log('CertificadosAntecorePDF: Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Convertir HTML para TCPDF
     */
    private static function convertir_html_para_tcpdf($html, $dinpro_registered = false, $dinprobold_registered = false) {
        // Simplificar el HTML para TCPDF
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        
        // TCPDF tiene problemas con comillas en nombres de fuente en CSS inline
        // Reemplazar referencias a fuentes con comillas por referencias sin comillas
        // Solo hacer esto si las fuentes están registradas
        
        if ($dinpro_registered) {
            // Reemplazar 'dinpro' y "dinpro" por dinpro (sin comillas)
            $html = preg_replace("/font-family:\s*['\"]dinpro['\"]/i", "font-family: dinpro", $html);
            $html = preg_replace("/font-family:\s*['\"]dinpro['\"],/i", "font-family: dinpro,", $html);
        } else {
            // Si dinpro no está registrada, reemplazar por helvetica
            $html = preg_replace("/font-family:\s*['\"]?dinpro['\"]?/i", "font-family: helvetica", $html);
        }
        
        if ($dinprobold_registered) {
            // Reemplazar 'dinprobold' y "dinprobold" por dinprobold (sin comillas)
            $html = preg_replace("/font-family:\s*['\"]dinprobold['\"]/i", "font-family: dinprobold", $html);
            $html = preg_replace("/font-family:\s*['\"]dinprobold['\"],/i", "font-family: dinprobold,", $html);
        } else {
            // Si dinprobold no está registrada, reemplazar por helvetica
            $html = preg_replace("/font-family:\s*['\"]?dinprobold['\"]?/i", "font-family: helvetica", $html);
        }
        
        // También manejar casos donde hay múltiples fuentes en la lista (font-family: 'dinprobold', 'dinpro', Arial)
        // TCPDF necesita que el nombre de la fuente esté primero sin comillas
        if ($dinprobold_registered) {
            $html = preg_replace("/font-family:\s*['\"]dinprobold['\"],\s*['\"]dinpro['\"]/i", "font-family: dinprobold, dinpro", $html);
        } elseif ($dinpro_registered) {
            $html = preg_replace("/font-family:\s*['\"]dinprobold['\"],\s*['\"]dinpro['\"]/i", "font-family: dinpro", $html);
        }
        
        if ($dinpro_registered) {
            $html = preg_replace("/font-family:\s*['\"]dinpro['\"],\s*['\"]dinprobold['\"]/i", "font-family: dinpro, dinprobold", $html);
        }
        
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
                error_log('CertificadosAntecorePDF: FPDF no encontrado en: ' . $fpdf_path);
                return false;
            }
            
            require_once $fpdf_path;
            
            if (!class_exists('FPDF')) {
                error_log('CertificadosAntecorePDF: Clase FPDF no encontrada');
                return false;
            }
            
            // Crear nueva instancia de FPDF A4 horizontal
            $pdf = new FPDF('L', 'mm', 'A4');
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
                error_log('CertificadosAntecorePDF: PDF generado exitosamente con FPDF para: ' . basename($ruta_archivo));
                return true;
            } else {
                error_log('CertificadosAntecorePDF: Error guardando PDF con FPDF: ' . $ruta_archivo);
                return false;
            }
            
        } catch (Exception $e) {
            error_log('CertificadosAntecorePDF: Error con FPDF: ' . $e->getMessage());
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
                    error_log('CertificadosAntecorePDF: PDF generado exitosamente con wkhtmltopdf para: ' . basename($ruta_archivo));
                    return true;
                }
            }
            
            // Si wkhtmltopdf no está disponible, generar HTML
            $ruta_html = str_replace('.pdf', '.html', $ruta_archivo);
            if (file_put_contents($ruta_html, $contenido)) {
                error_log('CertificadosAntecorePDF: HTML generado como fallback para: ' . basename($ruta_html));
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('CertificadosAntecorePDF: Error con método simple: ' . $e->getMessage());
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
        <div class="name">' . htmlspecialchars($certificado->nombre_instalacion) . '</div>
        
        <div class="activity">
            Por su participación destacada en la actividad:<br>
            <strong>' . htmlspecialchars($certificado->actividad) . '</strong>
        </div>
        
        <div class="code">
            Código de Verificación: ' . htmlspecialchars($certificado->codigo_unico) . '
        </div>
        
        ' . (!empty($certificado->observaciones) ? '<div class="activity">Observaciones: ' . htmlspecialchars($certificado->observaciones) . '</div>' : '') . '
        
        <div class="date">
            Fecha: ' . htmlspecialchars($certificado->fecha_aprobacion) . '
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Obtener URL del PDF con timestamp para evitar caché
     */
    public static function obtener_url_pdf($certificado_id) {
        $certificado = CertificadosAntecoreBD::obtener_certificado($certificado_id);
        
        if (!$certificado || empty($certificado->pdf_path)) {
            return false;
        }
        
        $upload_dir = wp_upload_dir();
        $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $certificado->pdf_path);
        
        // Si el archivo existe directamente, usar esa URL con timestamp
        if (file_exists($local_path)) {
            $timestamp = time();
            $url_con_timestamp = $certificado->pdf_path;
            
            // Si ya tiene timestamp, reemplazarlo; si no, agregarlo
            if (strpos($url_con_timestamp, '?v=') !== false) {
                $url_con_timestamp = preg_replace('/\?v=\d+/', '?v=' . $timestamp, $url_con_timestamp);
            } else {
                $url_con_timestamp .= '?v=' . $timestamp;
            }
            
            return $url_con_timestamp;
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
                $timestamp = time();
                return $url_correcta . '?v=' . $timestamp;
            }
        }
        
        return false;
    }
    
    /**
     * Obtener URL del PDF para administradores (sin caché)
     */
    public static function obtener_url_pdf_admin($certificado_id) {
        $certificado = CertificadosAntecoreBD::obtener_certificado($certificado_id);
        
        if (!$certificado || empty($certificado->pdf_path)) {
            return false;
        }
        
        $upload_dir = wp_upload_dir();
        $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $certificado->pdf_path);
        
        // Para administradores, siempre generar URL completamente nueva
        $timestamp = time();
        $random_suffix = substr(md5(uniqid()), 0, 8);
        
        // Si el archivo existe directamente, usar esa URL con timestamp y random
        if (file_exists($local_path)) {
            $url_base = $certificado->pdf_path;
            
            // Remover cualquier timestamp existente
            $url_base = preg_replace('/\?v=\d+.*/', '', $url_base);
            
            // Agregar timestamp y random para forzar recarga
            $url_con_timestamp = $url_base . '?v=' . $timestamp . '&r=' . $random_suffix;
            
            return $url_con_timestamp;
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
                return $url_correcta . '?v=' . $timestamp . '&r=' . $random_suffix;
            }
        }
        
        return false;
    }
    
    /**
     * Obtener URL del PDF para administradores con parámetros adicionales para forzar recarga
     */
    public static function obtener_url_pdf_admin_forzada($certificado_id) {
        $certificado = CertificadosAntecoreBD::obtener_certificado($certificado_id);
        
        if (!$certificado || empty($certificado->pdf_path)) {
            return false;
        }
        
        $upload_dir = wp_upload_dir();
        $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $certificado->pdf_path);
        
        // Para administradores, generar URL con múltiples parámetros para forzar recarga
        $timestamp = time();
        $random_suffix = substr(md5(uniqid()), 0, 8);
        $session_id = session_id() ?: uniqid();
        
        // Si el archivo existe directamente, usar esa URL con múltiples parámetros
        if (file_exists($local_path)) {
            $url_base = $certificado->pdf_path;
            
            // Remover cualquier parámetro existente
            $url_base = preg_replace('/\?.*/', '', $url_base);
            
            // Agregar múltiples parámetros para forzar recarga completa
            $url_con_parametros = $url_base . '?v=' . $timestamp . '&r=' . $random_suffix . '&s=' . $session_id . '&force=1&nocache=' . $timestamp;
            
            return $url_con_parametros;
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
                return $url_correcta . '?v=' . $timestamp . '&r=' . $random_suffix . '&s=' . $session_id . '&force=1&nocache=' . $timestamp;
            }
        }
        
        return false;
    }
    
    /**
     * Verificar si existe el PDF
     */
    public static function existe_pdf($certificado_id) {
        $certificado = CertificadosAntecoreBD::obtener_certificado($certificado_id);
        
        if (!$certificado) {
            error_log('CertificadosPersonalizados: Certificado no encontrado para verificar PDF - ID: ' . $certificado_id);
            return false;
        }
        
        $upload_dir = wp_upload_dir();
        $certificados_dir = $upload_dir['basedir'] . '/certificados/';
        
        // Verificar múltiples ubicaciones posibles
        $codigo_unico = $certificado->codigo_unico;
        $archivos_posibles = array(
            $certificados_dir . 'certificado_' . $codigo_unico . '.pdf',
            $certificados_dir . 'certificado_' . $codigo_unico . '.html',
            $certificados_dir . $codigo_unico . '.pdf',
            $certificados_dir . $codigo_unico . '.html'
        );
        
        // Si hay una ruta específica en la base de datos, verificar esa también
        if (!empty($certificado->pdf_path)) {
            $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $certificado->pdf_path);
            // Remover parámetros de la URL
            $local_path = preg_replace('/\?.*/', '', $local_path);
            $archivos_posibles[] = $local_path;
        }
        
        foreach ($archivos_posibles as $archivo) {
            if (file_exists($archivo) && filesize($archivo) > 0) {
                error_log('CertificadosPersonalizados: PDF encontrado en - ' . $archivo . ' - Tamaño: ' . filesize($archivo) . ' bytes');
                return true;
            }
        }
        
        error_log('CertificadosPersonalizados: No se encontró PDF para certificado ID: ' . $certificado_id . ' - Código: ' . $codigo_unico);
        return false;
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
        $certificado = CertificadosAntecoreBD::obtener_certificado($certificado_id);
        
        if (!$certificado) {
            error_log('CertificadosPersonalizados: Certificado no encontrado para regeneración - ID: ' . $certificado_id);
            return false;
        }
        
        // Obtener directorio de uploads
        $upload_dir = wp_upload_dir();
        $certificados_dir = $upload_dir['basedir'] . '/certificados/';
        
        // Asegurar que el directorio existe
        if (!file_exists($certificados_dir)) {
            mkdir($certificados_dir, 0755, true);
        }
        
        // Eliminar archivos existentes relacionados con este certificado
        $codigo_unico = $certificado->codigo_unico;
        $archivos_a_eliminar = array(
            $certificados_dir . 'certificado_' . $codigo_unico . '.pdf',
            $certificados_dir . 'certificado_' . $codigo_unico . '.html',
            $certificados_dir . $codigo_unico . '.pdf',
            $certificados_dir . $codigo_unico . '.html'
        );
        
        foreach ($archivos_a_eliminar as $archivo) {
            if (file_exists($archivo)) {
                unlink($archivo);
                error_log('CertificadosPersonalizados: Archivo eliminado antes de regenerar - ' . $archivo);
            }
        }
        
        // Limpiar caché de WordPress
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        if (function_exists('delete_transient')) {
            delete_transient('certificado_pdf_' . $certificado_id);
            delete_transient('certificado_url_' . $certificado_id);
        }
        
        // Regenerar PDF completamente
        $resultado = self::generar_certificado_pdf($certificado_id);
        
        if ($resultado) {
            error_log('CertificadosPersonalizados: PDF regenerado exitosamente - ID: ' . $certificado_id);
            
            // Verificar que el archivo se creó correctamente
            $nuevo_pdf = CertificadosAntecoreBD::obtener_certificado($certificado_id);
            if ($nuevo_pdf && $nuevo_pdf->pdf_path) {
                $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $nuevo_pdf->pdf_path);
                if (file_exists($local_path)) {
                    error_log('CertificadosPersonalizados: Archivo PDF verificado después de regeneración - ' . $local_path);
                    return true;
                }
            }
        }
        
        error_log('CertificadosPersonalizados: Error al regenerar PDF - ID: ' . $certificado_id);
        return false;
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
                error_log('CertificadosAntecorePDF: Archivo HTML eliminado: ' . basename($archivo));
            }
        }
    }
} 
