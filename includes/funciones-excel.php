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
            
            // Leer archivo Excel usando método simple (sin PhpSpreadsheet)
            $datos_excel = self::leer_archivo_excel_simple($archivo_path);
            
            if (empty($datos_excel)) {
                $resultados['errores'][] = "No se pudieron leer los datos del archivo Excel.";
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
                        $resultados['exitosos']++;
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
        
        // Para archivos CSV (convertir Excel a CSV primero)
        if (pathinfo($archivo_path, PATHINFO_EXTENSION) === 'csv') {
            return self::leer_archivo_csv($archivo_path);
        }
        
        // Para archivos Excel, usar método básico
        return self::leer_archivo_excel_basico($archivo_path);
    }
    
    /**
     * Leer archivo CSV
     */
    private static function leer_archivo_csv($archivo_path) {
        $datos = [];
        
        if (($handle = fopen($archivo_path, "r")) !== FALSE) {
            $encabezados = fgetcsv($handle, 1000, ","); // Leer encabezados
            
            while (($fila = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($fila) >= 9) { // Verificar que tenga al menos 9 columnas
                    $datos[] = [
                        'nombre_instalacion' => trim($fila[0]),
                        'direccion_instalacion' => trim($fila[1]),
                        'razon_social' => trim($fila[2]),
                        'nit' => trim($fila[3]),
                        'capacidad_almacenamiento' => trim($fila[4]),
                        'numero_tanques' => trim($fila[5]),
                        'tipo_certificado' => trim($fila[6]),
                        'numero_certificado' => trim($fila[7]),
                        'fecha_aprobacion' => trim($fila[8])
                    ];
                }
            }
            fclose($handle);
        }
        
        return $datos;
    }
    
    /**
     * Leer archivo Excel básico (solo para archivos simples)
     */
    private static function leer_archivo_excel_basico($archivo_path) {
        // Por ahora, solo soportamos CSV
        // En una implementación completa, aquí se usaría PhpSpreadsheet
        return [];
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
        $encabezados = [
            'NOMBRE_INSTALACION',
            'DIRECCION_INSTALACION',
            'RAZON_SOCIAL',
            'NIT',
            'CAPACIDAD_ALMACENAMIENTO',
            'NUMERO_TANQUES',
            'TIPO_CERTIFICADO',
            'NUMERO_CERTIFICADO',
            'FECHA_APROBACION'
        ];
        
        $ejemplos = [
            'Ejemplo: Estación de Servicio ABC',
            'Ejemplo: Calle 123 #45-67, Bogotá',
            'Ejemplo: Servicios ABC S.A.S.',
            'Ejemplo: 900123456-1',
            'Ejemplo: 10000',
            'Ejemplo: 5',
            'Ejemplo: PAGLP',
            'Ejemplo: 001',
            'Ejemplo: 15/12/2024'
        ];
        
        $contenido = implode(',', $encabezados) . "\n";
        $contenido .= implode(',', $ejemplos) . "\n";
        
        return $contenido;
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
}
