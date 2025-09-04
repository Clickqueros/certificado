<?php
/**
 * Página de administración para limpiar certificados de prueba
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos
if (!current_user_can('administrator')) {
    wp_die('No tienes permisos para acceder a esta página.');
}

// Procesar limpieza si se envió el formulario
if (isset($_POST['limpiar_certificados']) && wp_verify_nonce($_POST['_wpnonce'], 'limpiar_certificados')) {
    global $wpdb;
    $tabla = $wpdb->prefix . 'certificados_personalizados';
    
    // Contar certificados antes de eliminar
    $total_antes = $wpdb->get_var("SELECT COUNT(*) FROM $tabla");
    
    // Eliminar todos los certificados
    $resultado = $wpdb->query("DELETE FROM $tabla");
    
    if ($resultado !== false) {
        $mensaje = "✅ Se eliminaron $resultado certificados de prueba correctamente.";
        $tipo_mensaje = 'success';
    } else {
        $mensaje = "❌ Error al eliminar los certificados.";
        $tipo_mensaje = 'error';
    }
    
    // Verificar que se eliminaron todos
    $total_despues = $wpdb->get_var("SELECT COUNT(*) FROM $tabla");
}

// Obtener estadísticas actuales
global $wpdb;
$tabla = $wpdb->prefix . 'certificados_personalizados';
$total_actual = $wpdb->get_var("SELECT COUNT(*) FROM $tabla");
$pendientes = $wpdb->get_var("SELECT COUNT(*) FROM $tabla WHERE estado = 'pendiente'");
$aprobados = $wpdb->get_var("SELECT COUNT(*) FROM $tabla WHERE estado = 'aprobado'");
$rechazados = $wpdb->get_var("SELECT COUNT(*) FROM $tabla WHERE estado = 'rechazado'");
?>

<div class="wrap">
    <h1>🧹 Limpiar Certificados de Prueba</h1>
    
    <?php if (isset($mensaje)): ?>
        <div class="notice notice-<?php echo $tipo_mensaje; ?> is-dismissible">
            <p><?php echo esc_html($mensaje); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <h2>📊 Estadísticas Actuales</h2>
        <table class="widefat">
            <tr>
                <td><strong>Total de Certificados:</strong></td>
                <td><?php echo $total_actual; ?></td>
            </tr>
            <tr>
                <td><strong>Pendientes:</strong></td>
                <td><?php echo $pendientes; ?></td>
            </tr>
            <tr>
                <td><strong>Aprobados:</strong></td>
                <td><?php echo $aprobados; ?></td>
            </tr>
            <tr>
                <td><strong>Rechazados:</strong></td>
                <td><?php echo $rechazados; ?></td>
            </tr>
        </table>
    </div>
    
    <?php if ($total_actual > 0): ?>
        <div class="card">
            <h2>⚠️ Advertencia</h2>
            <p><strong>Esta acción eliminará TODOS los certificados de la base de datos.</strong></p>
            <p>Esto incluye:</p>
            <ul>
                <li>✅ Certificados pendientes</li>
                <li>✅ Certificados aprobados</li>
                <li>✅ Certificados rechazados</li>
                <li>✅ Archivos PDF asociados</li>
            </ul>
            
            <form method="post" onsubmit="return confirm('¿Estás seguro de que quieres eliminar TODOS los certificados? Esta acción no se puede deshacer.');">
                <?php wp_nonce_field('limpiar_certificados'); ?>
                <p class="submit">
                    <input type="submit" name="limpiar_certificados" class="button-primary" 
                           value="🗑️ Eliminar Todos los Certificados" 
                           style="background-color: #dc3232; border-color: #dc3232;">
                </p>
            </form>
        </div>
    <?php else: ?>
        <div class="card">
            <h2>✅ Base de Datos Limpia</h2>
            <p>No hay certificados en la base de datos. Puedes crear nuevos certificados desde el formulario.</p>
            <p>
                <a href="<?php echo admin_url('admin.php?page=mis-certificados'); ?>" class="button-primary">
                    📝 Ir a Mis Certificados
                </a>
            </p>
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

.widefat {
    width: 100%;
    border-collapse: collapse;
}

.widefat td {
    padding: 10px;
    border-bottom: 1px solid #eee;
}

.widefat tr:last-child td {
    border-bottom: none;
}
</style>
