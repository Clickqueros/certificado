<?php
/**
 * Funciones para manejo de archivos Excel - Plugin Certificados Antecore
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para manejo de Excel
 */
class CertificadosAntecoreExcel {
    
    /**
     * Procesar archivo Excel para carga masiva de certificados
     */
    public static function procesar_archivo_excel($archivo_path, $user_id) {
        $resultados = [
            'exitosos' => 0,
            'errores' => [],
            'duplicados' => [],
            'total_filas' => 0
        ];
        
        try {
            // Verificar si el archivo existe
            if (!file_exists($archivo_path)) {
                $resultados['errores'][] = "El archivo no existe o no se pudo subir correctamente.";
                return $resultados;
            }
            
            // DEBUG: Log información del archivo
            $extension = strtolower(pathinfo($archivo_path, PATHINFO_EXTENSION));
            $tamaño_archivo = filesize($archivo_path);
            error_log("DEBUG Excel: Archivo = $archivo_path, Extensión = $extension, Tamaño = $tamaño_archivo bytes");
            
            // Leer archivo Excel usando método simple (sin PhpSpreadsheet)
            $datos_excel = self::leer_archivo_excel_simple($archivo_path);
            
            // DEBUG: Log resultado de lectura
            error_log("DEBUG Excel: Datos leídos = " . count($datos_excel) . " filas");
            
            if (empty($datos_excel)) {
                // Intentar diagnóstico del archivo
                $diagnostico = self::diagnosticar_archivo($archivo_path);
                
                // DEBUG: Log diagnóstico completo
                error_log("DEBUG Excel: Diagnóstico = " . json_encode($diagnostico));
                
                if ($extension === 'csv') {
                    $mensaje = "No se pudieron leer los datos del archivo CSV. ";
                    if ($diagnostico['tiene_contenido']) {
                        $mensaje .= "El archivo tiene contenido pero no se pudo parsear. ";
                        if ($diagnostico['separador_detectado']) {
                            $mensaje .= "Separador detectado: '" . $diagnostico['separador_detectado'] . "'. ";
                        }
                        if ($diagnostico['numero_columnas'] > 0) {
                            $mensaje .= "Columnas encontradas: " . $diagnostico['numero_columnas'] . ". ";
                        }
                        if (!$diagnostico['tiene_encabezados']) {
                            $mensaje .= "Encabezados no válidos. ";
                        }
                        $mensaje .= "Descargue la plantilla CSV oficial y compare el formato exacto.";
                    } else {
                        $mensaje .= "El archivo parece estar vacío o corrupto.";
                    }
                    $resultados['errores'][] = $mensaje;
                } else {
                    $mensaje = "No se pudieron leer los datos del archivo Excel. ";
                    $mensaje .= "Para archivos .xlsx/.xls, se recomienda convertirlos a CSV primero. ";
                    $mensaje .= "Pasos: 1) Abra el archivo en Excel, 2) Guarde como 'CSV UTF-8 (delimitado por comas)', ";
                    $mensaje .= "3) Descargue la plantilla CSV oficial y compare el formato.";
                    $resultados['errores'][] = $mensaje;
                }
                return $resultados;
            }
            
            $resultados['total_filas'] = count($datos_excel);
            
            // Procesar cada fila
            foreach ($datos_excel as $fila_num => $fila_datos) {
                $fila_real = $fila_num + 2; // +2 porque empezamos desde fila 2 y el array es 0-based
                
                // Validar datos de la fila
                $validacion = self::validar_datos_fila($fila_datos, $fila_real);
                
                if ($validacion['valido']) {
                    // Crear certificado
                    $certificado_id = self::crear_certificado_desde_excel($fila_datos, $user_id);
                    
                    if ($certificado_id) {
                        // Generar PDF automáticamente
                        $pdf_generado = CertificadosAntecorePDF::generar_certificado_pdf($certificado_id);
                        
                        if ($pdf_generado) {
                            $resultados['exitosos']++;
                            error_log("CertificadosAntecoreExcel: PDF generado para certificado ID: $certificado_id");
                        } else {
                            $resultados['exitosos']++; // Certificado creado pero sin PDF
                            $resultados['errores'][] = "Fila $fila_real: Certificado creado pero error generando PDF.";
                            error_log("CertificadosAntecoreExcel: Error generando PDF para certificado ID: $certificado_id");
                        }
                    } else {
                        $resultados['errores'][] = "Fila $fila_real: Error al crear el certificado en la base de datos.";
                    }
                } else {
                    $resultados['errores'][] = "Fila $fila_real: " . implode(', ', $validacion['errores']);
                }
            }
            
        } catch (Exception $e) {
            $resultados['errores'][] = "Error general: " . $e->getMessage();
        }
        
        return $resultados;
    }
    
    /**
     * Leer archivo Excel de forma simple (sin PhpSpreadsheet)
     */
    private static function leer_archivo_excel_simple($archivo_path) {
        $datos = [];
        $extension = strtolower(pathinfo($archivo_path, PATHINFO_EXTENSION));
        
        // DEBUG: Log extensión detectada
        error_log("DEBUG leer_archivo_excel_simple: Extensión detectada = '$extension'");
        
        // Para archivos CSV
        if ($extension === 'csv') {
            error_log("DEBUG: Procesando como CSV");
            return self::leer_archivo_csv($archivo_path);
        }
        
        // Para archivos Excel, intentar leer como CSV también (por si se guardó mal)
        if (in_array($extension, ['xlsx', 'xls'])) {
            error_log("DEBUG: Procesando como Excel");
            // Primero intentar como CSV (por si se guardó mal como Excel)
            $datos_csv = self::leer_archivo_csv($archivo_path);
            if (!empty($datos_csv)) {
                error_log("DEBUG: Excel procesado exitosamente como CSV");
                return $datos_csv;
            }
            
            // Si no funciona como CSV, intentar como Excel básico
            error_log("DEBUG: Intentando como Excel básico");
            return self::leer_archivo_excel_basico($archivo_path);
        }
        
        // Para archivos sin extensión o con extensión desconocida, intentar como CSV
        error_log("DEBUG: Extensión desconocida '$extension', intentando como CSV");
        return self::leer_archivo_csv($archivo_path);
    }
    
    /**
     * Leer archivo CSV
     */
    private static function leer_archivo_csv($archivo_path) {
        $datos = [];
        $separadores = [',', ';', '\t']; // Probar diferentes separadores
        
        // DEBUG: Log inicio de lectura CSV
        error_log("DEBUG CSV: Iniciando lectura de $archivo_path");
        
        foreach ($separadores as $separador) {
            if (($handle = fopen($archivo_path, "r")) !== FALSE) {
                // Leer encabezados
                $encabezados = fgetcsv($handle, 1000, $separador);
                
                if (!$encabezados) {
                    error_log("DEBUG CSV: No se pudieron leer encabezados con separador '$separador'");
                    fclose($handle);
                    continue;
                }
                
                // DEBUG: Log encabezados encontrados
                error_log("DEBUG CSV: Encabezados con separador '$separador': " . json_encode($encabezados));
                
                // Verificar si los encabezados coinciden con lo esperado
                $encabezados_limpios = array_map('trim', $encabezados);
                $encabezados_lower = array_map('strtolower', $encabezados_limpios);
                
                $columnas_esperadas = ['nombre_instalacion', 'direccion_instalacion', 'razon_social', 'nit', 'capacidad_almacenamiento', 'numero_tanques', 'tipo_certificado', 'numero_certificado', 'fecha_aprobacion'];
                $columnas_esperadas_lower = array_map('strtolower', $columnas_esperadas);
                
                $match_count = 0;
                foreach ($columnas_esperadas_lower as $columna) {
                    foreach ($encabezados_lower as $encabezado) {
                        // Buscar coincidencias parciales también
                        if ($encabezado === $columna || 
                            strpos($encabezado, $columna) !== false || 
                            strpos($columna, $encabezado) !== false) {
                            $match_count++;
                            break;
                        }
                    }
                }
                
                // DEBUG: Log resultado de validación
                error_log("DEBUG CSV: Match count = $match_count de " . count($columnas_esperadas));
                
                // Si al menos 5 de 9 columnas coinciden, procesar (más flexible)
                if ($match_count >= 5) {
                    error_log("DEBUG CSV: Validación exitosa, procesando datos...");
                    while (($fila = fgetcsv($handle, 1000, $separador)) !== FALSE) {
                        if (count($fila) >= 9) { // Verificar que tenga al menos 9 columnas
                            // Limpiar datos y asegurar que no estén vacíos
                            $fila_limpia = array_map('trim', $fila);
                            
                            // Solo agregar si no es una fila completamente vacía
                            if (!empty(array_filter($fila_limpia))) {
                                // Orden correcto: nombre, direccion, razon_social, nit, tipo, numero, fecha, capacidad, tanques
                                $datos[] = [
                                    'nombre_instalacion' => isset($fila_limpia[0]) ? $fila_limpia[0] : '',
                                    'direccion_instalacion' => isset($fila_limpia[1]) ? $fila_limpia[1] : '',
                                    'razon_social' => isset($fila_limpia[2]) ? $fila_limpia[2] : '',
                                    'nit' => isset($fila_limpia[3]) ? $fila_limpia[3] : '',
                                    'tipo_certificado' => isset($fila_limpia[4]) ? $fila_limpia[4] : '',
                                    'numero_certificado' => isset($fila_limpia[5]) ? $fila_limpia[5] : '',
                                    'fecha_aprobacion' => isset($fila_limpia[6]) ? $fila_limpia[6] : '',
                                    'capacidad_almacenamiento' => isset($fila_limpia[7]) ? $fila_limpia[7] : '',
                                    'numero_tanques' => isset($fila_limpia[8]) ? $fila_limpia[8] : ''
                                ];
                            }
                        }
                    }
                    fclose($handle);
                    error_log("DEBUG CSV: Datos procesados exitosamente: " . count($datos) . " filas");
                    break; // Salir del bucle si encontramos datos
                } else {
                    error_log("DEBUG CSV: Validación fallida con separador '$separador'");
                }
                fclose($handle);
            }
        }
        
        error_log("DEBUG CSV: Total de datos final: " . count($datos) . " filas");
        return $datos;
    }
    
    /**
     * Leer archivo Excel básico (solo para archivos simples)
     */
    private static function leer_archivo_excel_basico($archivo_path) {
        $datos = [];
        
        // Intentar leer como CSV con diferentes separadores
        $separadores = [',', ';', '\t'];
        
        foreach ($separadores as $separador) {
            if (($handle = fopen($archivo_path, "r")) !== FALSE) {
                $encabezados = fgetcsv($handle, 1000, $separador);
                
                // Verificar si los encabezados tienen el formato esperado
                if ($encabezados && count($encabezados) >= 9) {
                    // Verificar si contiene las columnas esperadas
                    $columnas_esperadas = ['NOMBRE_INSTALACION', 'DIRECCION_INSTALACION', 'RAZON_SOCIAL', 'NIT', 'CAPACIDAD_ALMACENAMIENTO', 'NUMERO_TANQUES', 'TIPO_CERTIFICADO', 'NUMERO_CERTIFICADO', 'FECHA_APROBACION'];
                    
                    $encabezados_lower = array_map('strtolower', array_map('trim', $encabezados));
                    $columnas_esperadas_lower = array_map('strtolower', $columnas_esperadas);
                    
                    $match_count = 0;
                    foreach ($columnas_esperadas_lower as $columna) {
                        if (in_array($columna, $encabezados_lower)) {
                            $match_count++;
                        }
                    }
                    
                    // Si al menos 7 de 9 columnas coinciden, es probable que sea el formato correcto
                    if ($match_count >= 7) {
                        // Leer datos con este separador
                        while (($fila = fgetcsv($handle, 1000, $separador)) !== FALSE) {
                            if (count($fila) >= 9) {
                                // Orden correcto: nombre, direccion, razon_social, nit, tipo, numero, fecha, capacidad, tanques
                                $datos[] = [
                                    'nombre_instalacion' => isset($fila[0]) ? trim($fila[0]) : '',
                                    'direccion_instalacion' => isset($fila[1]) ? trim($fila[1]) : '',
                                    'razon_social' => isset($fila[2]) ? trim($fila[2]) : '',
                                    'nit' => isset($fila[3]) ? trim($fila[3]) : '',
                                    'tipo_certificado' => isset($fila[4]) ? trim($fila[4]) : '',
                                    'numero_certificado' => isset($fila[5]) ? trim($fila[5]) : '',
                                    'fecha_aprobacion' => isset($fila[6]) ? trim($fila[6]) : '',
                                    'capacidad_almacenamiento' => isset($fila[7]) ? trim($fila[7]) : '',
                                    'numero_tanques' => isset($fila[8]) ? trim($fila[8]) : ''
                                ];
                            }
                        }
                        fclose($handle);
                        break; // Salir del bucle si encontramos datos
                    }
                }
                fclose($handle);
            }
        }
        
        return $datos;
    }
    
    /**
     * Validar datos de una fila
     */
    private static function validar_datos_fila($datos, $fila_num) {
        $errores = [];
        
        // Campos obligatorios
        $campos_obligatorios = [
            'nombre_instalacion' => 'Nombre de Instalación',
            'direccion_instalacion' => 'Dirección de Instalación',
            'razon_social' => 'Razón Social',
            'nit' => 'NIT',
            'capacidad_almacenamiento' => 'Capacidad de Almacenamiento',
            'numero_tanques' => 'Número de Tanques',
            'tipo_certificado' => 'Tipo de Certificado',
            'numero_certificado' => 'Número de Certificado',
            'fecha_aprobacion' => 'Fecha de Aprobación'
        ];
        
        foreach ($campos_obligatorios as $campo => $nombre) {
            if (empty($datos[$campo])) {
                $errores[] = "$nombre es obligatorio";
            }
        }
        
        // Validaciones específicas
        if (!empty($datos['nit'])) {
            // Verificar si el NIT ya existe
            if (self::nit_existe($datos['nit'])) {
                $errores[] = "NIT ya existe en el sistema";
            }
        }
        
        if (!empty($datos['tipo_certificado'])) {
            $tipos_validos = ['PAGLP', 'TEGLP', 'PEGLP', 'DEGLP', 'PVGLP'];
            if (!in_array($datos['tipo_certificado'], $tipos_validos)) {
                $errores[] = "Tipo de certificado inválido. Debe ser: " . implode(', ', $tipos_validos);
            }
        }
        
        if (!empty($datos['numero_tanques']) && !is_numeric($datos['numero_tanques'])) {
            $errores[] = "Número de tanques debe ser numérico";
        } elseif (!empty($datos['numero_tanques']) && intval($datos['numero_tanques']) <= 0) {
            $errores[] = "Número de tanques debe ser mayor a 0";
        }
        
        if (!empty($datos['numero_certificado']) && !is_numeric($datos['numero_certificado'])) {
            $errores[] = "Número de certificado debe ser numérico";
        } elseif (!empty($datos['numero_certificado']) && intval($datos['numero_certificado']) <= 0) {
            $errores[] = "Número de certificado debe ser mayor a 0";
        }
        
        if (!empty($datos['capacidad_almacenamiento']) && !is_numeric($datos['capacidad_almacenamiento'])) {
            $errores[] = "Capacidad de almacenamiento debe ser numérica";
        }
        
        // Validar fecha
        if (!empty($datos['fecha_aprobacion'])) {
            $fecha = self::convertir_fecha($datos['fecha_aprobacion']);
            if (!$fecha) {
                $errores[] = "Fecha de aprobación inválida. Use formato DD/MM/YYYY";
            } elseif ($fecha > date('Y-m-d')) {
                $errores[] = "La fecha de aprobación no puede ser futura";
            }
        }
        
        return [
            'valido' => empty($errores),
            'errores' => $errores
        ];
    }
    
    /**
     * Verificar si un NIT ya existe
     */
    private static function nit_existe($nit) {
        global $wpdb;
        $tabla = $wpdb->prefix . 'certificados_antecore';
        
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM $tabla WHERE nit = %s",
            $nit
        );
        
        return $wpdb->get_var($sql) > 0;
    }
    
    /**
     * Crear certificado desde datos de Excel
     */
    private static function crear_certificado_desde_excel($datos, $user_id) {
        $datos_certificado = [
            'user_id' => $user_id,
            'nombre_instalacion' => sanitize_text_field($datos['nombre_instalacion']),
            'direccion_instalacion' => sanitize_textarea_field($datos['direccion_instalacion']),
            'razon_social' => sanitize_text_field($datos['razon_social']),
            'nit' => sanitize_text_field($datos['nit']),
            'capacidad_almacenamiento' => sanitize_text_field($datos['capacidad_almacenamiento']),
            'numero_tanques' => intval($datos['numero_tanques']),
            'tipo_certificado' => sanitize_text_field($datos['tipo_certificado']),
            'numero_certificado' => intval($datos['numero_certificado']),
            'fecha_aprobacion' => self::convertir_fecha($datos['fecha_aprobacion']),
            'actividad' => sanitize_text_field($datos['tipo_certificado']), // Usar tipo como actividad
            'estado' => 'pendiente',
            'notificado' => 0
        ];
        
        return CertificadosAntecoreBD::crear_certificado($datos_certificado);
    }
    
    /**
     * Convertir fecha de diferentes formatos a Y-m-d
     */
    private static function convertir_fecha($fecha) {
        if (empty($fecha)) {
            return false;
        }
        
        // Intentar diferentes formatos
        $formatos = ['d/m/Y', 'm/d/Y', 'Y-m-d', 'd-m-Y', 'm-d-Y'];
        
        foreach ($formatos as $formato) {
            $fecha_obj = DateTime::createFromFormat($formato, $fecha);
            if ($fecha_obj !== false) {
                return $fecha_obj->format('Y-m-d');
            }
        }
        
        // Si no funciona con formatos específicos, intentar con strtotime
        $timestamp = strtotime($fecha);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        return false;
    }
    
    /**
     * Generar plantilla Excel/CSV
     */
    public static function generar_plantilla() {
        // ORDEN IGUAL AL FORMULARIO MANUAL
        $encabezados = [
            'NOMBRE_INSTALACION',
            'DIRECCION_INSTALACION',
            'RAZON_SOCIAL',
            'NIT',
            'TIPO_CERTIFICADO',
            'NUMERO_CERTIFICADO',
            'FECHA_APROBACION',
            'CAPACIDAD_ALMACENAMIENTO',
            'NUMERO_TANQUES'
        ];
        
        $ejemplos = [
            'Estación de Servicio ABC',
            'Calle 123 #45-67, Bogotá',
            'Servicios ABC S.A.S.',
            '900123456-1',
            'PAGLP',
            '001',
            '15/12/2024',
            '10000',
            '5'
        ];
        
        $descripciones = [
            'Nombre de la instalación o lugar',
            'Dirección completa de la instalación',
            'Razón social de la empresa',
            'Número de identificación tributaria',
            'PAGLP, TEGLP, PEGLP, DEGLP, PVGLP',
            'Número del certificado',
            'Fecha en formato DD/MM/YYYY',
            'Capacidad en galones',
            'Cantidad de tanques'
        ];
        
        // Crear contenido CSV con BOM para UTF-8
        $contenido = "\xEF\xBB\xBF"; // BOM para UTF-8
        
        // Agregar encabezados
        $contenido .= self::escapar_csv($encabezados) . "\n";
        
        // Agregar ejemplos
        $contenido .= self::escapar_csv($ejemplos) . "\n";
        
        // Agregar descripciones
        $contenido .= self::escapar_csv($descripciones) . "\n";
        
        // Agregar fila vacía para datos del usuario
        $contenido .= str_repeat(',', count($encabezados) - 1) . "\n";
        
        return $contenido;
    }
    
    /**
     * Escapar datos para CSV
     */
    private static function escapar_csv($datos) {
        $resultado = [];
        foreach ($datos as $dato) {
            // Escapar comillas dobles y envolver en comillas si contiene comas, comillas o saltos de línea
            if (strpos($dato, ',') !== false || strpos($dato, '"') !== false || strpos($dato, "\n") !== false) {
                $dato = '"' . str_replace('"', '""', $dato) . '"';
            }
            $resultado[] = $dato;
        }
        return implode(',', $resultado);
    }
    
    /**
     * Validar archivo subido
     */
    public static function validar_archivo_subido($archivo) {
        $errores = [];
        
        // Verificar que se subió un archivo
        if (!isset($archivo['tmp_name']) || empty($archivo['tmp_name'])) {
            $errores[] = "No se seleccionó ningún archivo";
            return $errores;
        }
        
        // Verificar errores de subida
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            $errores[] = "Error al subir el archivo: " . self::obtener_mensaje_error_subida($archivo['error']);
            return $errores;
        }
        
        // Verificar tamaño (máximo 5MB)
        $tamaño_maximo = 5 * 1024 * 1024; // 5MB
        if ($archivo['size'] > $tamaño_maximo) {
            $errores[] = "El archivo es demasiado grande. Máximo 5MB permitido";
        }
        
        // Verificar extensión
        $extensiones_permitidas = ['csv', 'xlsx', 'xls'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $extensiones_permitidas)) {
            $errores[] = "Tipo de archivo no permitido. Solo se permiten: " . implode(', ', $extensiones_permitidas);
        }
        
        // Verificar que el archivo no esté vacío
        if ($archivo['size'] === 0) {
            $errores[] = "El archivo está vacío";
        }
        
        return $errores;
    }
    
    /**
     * Obtener mensaje de error de subida
     */
    private static function obtener_mensaje_error_subida($error_code) {
        $mensajes = [
            UPLOAD_ERR_INI_SIZE => "El archivo excede el tamaño máximo permitido por el servidor",
            UPLOAD_ERR_FORM_SIZE => "El archivo excede el tamaño máximo permitido por el formulario",
            UPLOAD_ERR_PARTIAL => "El archivo se subió parcialmente",
            UPLOAD_ERR_NO_FILE => "No se seleccionó ningún archivo",
            UPLOAD_ERR_NO_TMP_DIR => "No hay directorio temporal",
            UPLOAD_ERR_CANT_WRITE => "No se puede escribir en el disco",
            UPLOAD_ERR_EXTENSION => "La subida fue detenida por una extensión"
        ];
        
        return isset($mensajes[$error_code]) ? $mensajes[$error_code] : "Error desconocido";
    }
    
    /**
     * Limpiar archivos temporales
     */
    public static function limpiar_archivo_temporal($archivo_path) {
        if (file_exists($archivo_path)) {
            unlink($archivo_path);
        }
    }
    
    /**
     * Diagnosticar archivo para detectar problemas
     */
    public static function diagnosticar_archivo($archivo_path) {
        $diagnostico = [
            'tiene_contenido' => false,
            'tiene_encabezados' => false,
            'separador_detectado' => null,
            'numero_columnas' => 0,
            'problemas' => []
        ];
        
        if (!file_exists($archivo_path)) {
            $diagnostico['problemas'][] = 'El archivo no existe';
            return $diagnostico;
        }
        
        $contenido = file_get_contents($archivo_path);
        if (empty($contenido)) {
            $diagnostico['problemas'][] = 'El archivo está vacío';
            return $diagnostico;
        }
        
        $diagnostico['tiene_contenido'] = true;
        
        // DEBUG: Log contenido del archivo (primeras 500 caracteres)
        $contenido_preview = substr($contenido, 0, 500);
        error_log("DEBUG Diagnóstico: Contenido preview = " . $contenido_preview);
        
        // Detectar separador más común
        $separadores = [',', ';', '\t', '|'];
        $conteo_separadores = [];
        
        foreach ($separadores as $sep) {
            $conteo = substr_count($contenido, $sep);
            $conteo_separadores[$sep] = $conteo;
        }
        
        $separador_mas_comun = array_search(max($conteo_separadores), $conteo_separadores);
        $diagnostico['separador_detectado'] = $separador_mas_comun;
        
        // Leer primera línea para verificar encabezados
        $lineas = explode("\n", $contenido);
        if (!empty($lineas[0])) {
            $primera_linea = $lineas[0];
            $columnas = explode($separador_mas_comun, $primera_linea);
            $diagnostico['numero_columnas'] = count($columnas);
            
            // Verificar si parece ser encabezados
            $encabezados_esperados = ['nombre_instalacion', 'direccion_instalacion', 'razon_social', 'nit'];
            $encabezados_lower = array_map('strtolower', array_map('trim', $columnas));
            
            $match_count = 0;
            foreach ($encabezados_esperados as $esperado) {
                foreach ($encabezados_lower as $encabezado) {
                    if (strpos($encabezado, $esperado) !== false) {
                        $match_count++;
                        break;
                    }
                }
            }
            
            if ($match_count >= 2) {
                $diagnostico['tiene_encabezados'] = true;
            } else {
                $diagnostico['problemas'][] = 'No se detectaron encabezados válidos';
            }
        }
        
        return $diagnostico;
    }
}
