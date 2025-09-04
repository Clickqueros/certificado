<?php
/**
 * Script para actualizar la base de datos con las nuevas columnas GLP
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos
if (!current_user_can('administrator')) {
    wp_die('No tienes permisos para acceder a esta página.');
}

// Procesar actualización si se envió el formulario
if (isset($_POST['actualizar_bd']) && wp_verify_nonce($_POST['_wpnonce'], 'actualizar_bd')) {
    global $wpdb;
    $tabla = $wpdb->prefix . 'certificados_personalizados';
    
    $errores = array();
    $exitos = array();
    
    // Verificar si la tabla existe
    $tabla_existe = $wpdb->get_var("SHOW TABLES LIKE '$tabla'");
    
    if (!$tabla_existe) {
        $errores[] = "La tabla $tabla no existe.";
    } else {
        // Lista de columnas a agregar
        $columnas_nuevas = array(
            'capacidad_almacenamiento' => 'VARCHAR(50)',
            'numero_tanques' => 'INT',
            'nombre_instalacion' => 'VARCHAR(255)',
            'direccion_instalacion' => 'TEXT',
            'razon_social' => 'VARCHAR(255)',
            'nit' => 'VARCHAR(50)',
            'tipo_certificado' => 'VARCHAR(10)',
            'numero_certificado' => 'INT',
            'fecha_aprobacion' => 'DATE'
        );
        
        // Verificar y agregar columnas que no existen
        foreach ($columnas_nuevas as $columna => $tipo) {
            $columna_existe = $wpdb->get_var("SHOW COLUMNS FROM $tabla LIKE '$columna'");
            
            if (!$columna_existe) {
                $sql = "ALTER TABLE $tabla ADD COLUMN $columna $tipo";
                $resultado = $wpdb->query($sql);
                
                if ($resultado !== false) {
                    $exitos[] = "Columna '$columna' agregada correctamente.";
                } else {
                    $errores[] = "Error al agregar columna '$columna': " . $wpdb->last_error;
                }
            } else {
                $exitos[] = "Columna '$columna' ya existe.";
            }
        }
        
        // Verificar columnas antiguas que deben eliminarse
        $columnas_antiguas = array('nombre', 'fecha', 'observaciones');
        
        foreach ($columnas_antiguas as $columna) {
            $columna_existe = $wpdb->get_var("SHOW COLUMNS FROM $tabla LIKE '$columna'");
            
            if ($columna_existe) {
                $sql = "ALTER TABLE $tabla DROP COLUMN $columna";
                $resultado = $wpdb->query($sql);
                
                if ($resultado !== false) {
                    $exitos[] = "Columna antigua '$columna' eliminada correctamente.";
                } else {
                    $errores[] = "Error al eliminar columna '$columna': " . $wpdb->last_error;
                }
            }
        }
    }
    
    $mensaje_tipo = empty($errores) ? 'success' : 'warning';
    $mensaje_texto = empty($errores) ? 'Base de datos actualizada correctamente.' : 'Base de datos actualizada con algunos errores.';
}

// Obtener información actual de la tabla
global $wpdb;
$tabla = $wpdb->prefix . 'certificados_personalizados';
$tabla_existe = $wpdb->get_var("SHOW TABLES LIKE '$tabla'");

$columnas_actuales = array();
if ($tabla_existe) {
    $columnas = $wpdb->get_results("SHOW COLUMNS FROM $tabla");
    foreach ($columnas as $columna) {
        $columnas_actuales[] = $columna->Field;
    }
}
?>

<div class="wrap">
    <h1>🔧 Actualizar Base de Datos</h1>
    
    <?php if (isset($mensaje_tipo)): ?>
        <div class="notice notice-<?php echo $mensaje_tipo; ?> is-dismissible">
            <p><strong><?php echo $mensaje_texto; ?></strong></p>
            
            <?php if (!empty($exitos)): ?>
                <h4>✅ Operaciones exitosas:</h4>
                <ul>
                    <?php foreach ($exitos as $exito): ?>
                        <li><?php echo esc_html($exito); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <?php if (!empty($errores)): ?>
                <h4>❌ Errores encontrados:</h4>
                <ul>
                    <?php foreach ($errores as $error): ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <h2>📊 Estado Actual de la Base de Datos</h2>
        
        <?php if ($tabla_existe): ?>
            <p><strong>✅ Tabla existe:</strong> <?php echo $tabla; ?></p>
            
            <h3>Columnas actuales:</h3>
            <ul>
                <?php foreach ($columnas_actuales as $columna): ?>
                    <li><?php echo esc_html($columna); ?></li>
                <?php endforeach; ?>
            </ul>
            
            <h3>Columnas requeridas para certificados GLP:</h3>
            <ul>
                <li>capacidad_almacenamiento</li>
                <li>numero_tanques</li>
                <li>nombre_instalacion</li>
                <li>direccion_instalacion</li>
                <li>razon_social</li>
                <li>nit</li>
                <li>tipo_certificado</li>
                <li>numero_certificado</li>
                <li>fecha_aprobacion</li>
            </ul>
            
            <h3>Columnas antiguas a eliminar:</h3>
            <ul>
                <li>nombre</li>
                <li>fecha</li>
                <li>observaciones</li>
            </ul>
            
        <?php else: ?>
            <p><strong>❌ La tabla no existe:</strong> <?php echo $tabla; ?></p>
            <p>La tabla se creará automáticamente cuando actives el plugin.</p>
        <?php endif; ?>
    </div>
    
    <?php if ($tabla_existe): ?>
        <div class="card">
            <h2>⚠️ Advertencia</h2>
            <p><strong>Esta operación modificará la estructura de la base de datos.</strong></p>
            <p>Se realizarán las siguientes acciones:</p>
            <ul>
                <li>✅ Agregar columnas nuevas para certificados GLP</li>
                <li>🗑️ Eliminar columnas antiguas (nombre, fecha, observaciones)</li>
                <li>⚠️ <strong>Los datos en las columnas antiguas se perderán</strong></li>
            </ul>
            
            <form method="post" onsubmit="return confirm('¿Estás seguro de que quieres actualizar la base de datos? Esta acción no se puede deshacer.');">
                <?php wp_nonce_field('actualizar_bd'); ?>
                <p class="submit">
                    <input type="submit" name="actualizar_bd" class="button-primary" 
                           value="🔧 Actualizar Base de Datos">
                </p>
            </form>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <h2>🔗 Enlaces Útiles</h2>
        <p>
            <a href="<?php echo admin_url('admin.php?page=mis-certificados'); ?>" class="button">
                📋 Mis Certificados
            </a>
            <a href="<?php echo admin_url('admin.php?page=aprobacion-certificados'); ?>" class="button">
                ✅ Aprobación de Certificados
            </a>
            <a href="<?php echo admin_url('admin.php?page=limpiar-certificados'); ?>" class="button">
                🧹 Limpiar Certificados
            </a>
        </p>
    </div>
</div>

<style>
.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.card h2 {
    margin-top: 0;
    color: #23282d;
}

.card h3 {
    color: #0073aa;
    border-bottom: 1px solid #eee;
    padding-bottom: 5px;
}

.card ul {
    margin: 10px 0;
    padding-left: 20px;
}

.card li {
    margin: 5px 0;
}
</style>
