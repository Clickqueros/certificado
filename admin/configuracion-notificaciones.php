<?php
/**
 * Configuración de Notificaciones - Certificados Antecore
 * 
 * @package CertificadosAntecore
 * @since 1.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class ConfiguracionNotificaciones {
    
    /**
     * Mostrar la página de configuración de notificaciones
     */
    public static function mostrar_pagina_configuracion() {
        // Obtener configuración actual
        $emails_notificacion = get_option('certificados_emails_notificacion', []);
        $usuarios_notificacion = get_option('certificados_usuarios_notificacion', []);
        
        // Obtener todos los usuarios con rol administrador
        $administradores = get_users(['role' => 'administrator']);
        
        // Procesar guardado de configuración
        if (isset($_POST['guardar_configuracion']) && wp_verify_nonce($_POST['_wpnonce'], 'guardar_configuracion_notificaciones')) {
            self::guardar_configuracion();
            // Recargar configuración después de guardar
            $emails_notificacion = get_option('certificados_emails_notificacion', []);
            $usuarios_notificacion = get_option('certificados_usuarios_notificacion', []);
        }
        ?>
        
        <div class="wrap">
            <h1>🔔 Configuración de Notificaciones</h1>
            <p>Configura quién recibirá notificaciones cuando se envíen nuevas solicitudes de certificados.</p>
            
            <form method="post" action="">
                <?php wp_nonce_field('guardar_configuracion_notificaciones'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="emails_personalizados">📧 Emails personalizados</label>
                        </th>
                        <td>
                            <textarea 
                                name="emails_personalizados" 
                                id="emails_personalizados"
                                rows="4" 
                                cols="60" 
                                class="large-text"
                                placeholder="programacion@anetcore.com, juan.granados@anetcore.com, gerencia@anetcore.com"><?php echo esc_textarea(implode(', ', $emails_notificacion)); ?></textarea>
                            <p class="description">
                                <strong>Instrucciones:</strong> Separar cada email con una coma. Ejemplo: email1@empresa.com, email2@empresa.com
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label>👥 Usuarios administradores</label>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <span>Seleccionar usuarios administradores para notificaciones</span>
                                </legend>
                                
                                <?php if (empty($administradores)): ?>
                                    <p class="description">No hay usuarios administradores en el sistema.</p>
                                <?php else: ?>
                                    <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                                        <?php foreach ($administradores as $admin): ?>
                                            <label style="display: block; margin-bottom: 8px;">
                                                <input 
                                                    type="checkbox" 
                                                    name="usuarios_notificacion[]" 
                                                    value="<?php echo esc_attr($admin->ID); ?>"
                                                    <?php checked(in_array($admin->ID, $usuarios_notificacion)); ?>
                                                    style="margin-right: 8px;">
                                                <strong><?php echo esc_html($admin->display_name); ?></strong>
                                                <span style="color: #666;">(<?php echo esc_html($admin->user_email); ?>)</span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="description">
                                        <strong>Nota:</strong> Solo se notificará a los usuarios administradores seleccionados.
                                    </p>
                                <?php endif; ?>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label>📋 Vista previa de configuración</label>
                        </th>
                        <td>
                            <div style="background: #f0f0f1; padding: 15px; border-left: 4px solid #0073aa;">
                                <h4 style="margin-top: 0;">Emails que recibirán notificaciones:</h4>
                                <div id="vista-previa-emails">
                                    <?php 
                                    $todos_emails = array_merge($emails_notificacion, self::obtener_emails_usuarios_seleccionados($usuarios_notificacion));
                                    if (empty($todos_emails)) {
                                        echo '<p style="color: #d63638; font-style: italic;">⚠️ No hay emails configurados. Las notificaciones no se enviarán.</p>';
                                    } else {
                                        echo '<ul style="margin: 0;">';
                                        foreach ($todos_emails as $email) {
                                            echo '<li>📧 ' . esc_html($email) . '</li>';
                                        }
                                        echo '</ul>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('💾 Guardar Configuración', 'primary', 'guardar_configuracion'); ?>
            </form>
            
            <hr>
            
            <div class="card">
                <h3>ℹ️ Información sobre las notificaciones</h3>
                <ul>
                    <li><strong>Cuándo se envían:</strong> Cada vez que un colaborador envía una nueva solicitud de certificado</li>
                    <li><strong>Contenido:</strong> Incluye datos del certificado y enlace al panel de administración</li>
                    <li><strong>Formato:</strong> Email HTML con información detallada</li>
                    <li><strong>Configuración:</strong> Se puede modificar en cualquier momento desde esta página</li>
                </ul>
            </div>
        </div>
        
        <script>
        // Actualizar vista previa en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            const emailsTextarea = document.getElementById('emails_personalizados');
            const checkboxes = document.querySelectorAll('input[name="usuarios_notificacion[]"]');
            const vistaPrevia = document.getElementById('vista-previa-emails');
            
            function actualizarVistaPrevia() {
                // Obtener emails del textarea
                const emailsTexto = emailsTextarea.value;
                const emailsArray = emailsTexto.split(',').map(email => email.trim()).filter(email => email);
                
                // Obtener emails de usuarios seleccionados
                const usuariosSeleccionados = Array.from(checkboxes)
                    .filter(cb => cb.checked)
                    .map(cb => {
                        const label = cb.parentElement;
                        const emailMatch = label.textContent.match(/\(([^)]+)\)/);
                        return emailMatch ? emailMatch[1] : null;
                    })
                    .filter(email => email);
                
                // Combinar todos los emails
                const todosEmails = [...new Set([...emailsArray, ...usuariosSeleccionados])];
                
                // Actualizar vista previa
                if (todosEmails.length === 0) {
                    vistaPrevia.innerHTML = '<p style="color: #d63638; font-style: italic;">⚠️ No hay emails configurados. Las notificaciones no se enviarán.</p>';
                } else {
                    let html = '<ul style="margin: 0;">';
                    todosEmails.forEach(email => {
                        html += '<li>📧 ' + email + '</li>';
                    });
                    html += '</ul>';
                    vistaPrevia.innerHTML = html;
                }
            }
            
            emailsTextarea.addEventListener('input', actualizarVistaPrevia);
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', actualizarVistaPrevia);
            });
            
            // Actualizar vista previa inicial
            actualizarVistaPrevia();
        });
        </script>
        <?php
    }
    
    /**
     * Guardar la configuración de notificaciones
     */
    private static function guardar_configuracion() {
        // Validar nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'guardar_configuracion_notificaciones')) {
            wp_die('Error de seguridad. Inténtalo de nuevo.');
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }
        
        // Procesar emails personalizados
        $emails_texto = sanitize_textarea_field($_POST['emails_personalizados']);
        $emails = array_map('trim', explode(',', $emails_texto));
        $emails = array_filter($emails, function($email) {
            return is_email($email);
        });
        
        // Guardar emails personalizados
        update_option('certificados_emails_notificacion', $emails);
        
        // Procesar usuarios seleccionados
        $usuarios = isset($_POST['usuarios_notificacion']) ? array_map('intval', $_POST['usuarios_notificacion']) : [];
        update_option('certificados_usuarios_notificacion', $usuarios);
        
        // Mostrar mensaje de éxito
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Configuración guardada correctamente.</strong> Las notificaciones se enviarán a los emails configurados.</p></div>';
        });
        
        // Log de la acción
        error_log('CertificadosAntecore: Configuración de notificaciones actualizada. Emails: ' . implode(', ', $emails) . ' | Usuarios: ' . implode(', ', $usuarios));
    }
    
    /**
     * Obtener emails de usuarios seleccionados
     */
    private static function obtener_emails_usuarios_seleccionados($usuarios_ids) {
        if (empty($usuarios_ids)) {
            return [];
        }
        
        $usuarios = get_users(['include' => $usuarios_ids]);
        $emails = [];
        
        foreach ($usuarios as $usuario) {
            if (is_email($usuario->user_email)) {
                $emails[] = $usuario->user_email;
            }
        }
        
        return $emails;
    }
    
    /**
     * Obtener todos los emails configurados para notificaciones
     */
    public static function obtener_emails_notificacion() {
        $emails_personalizados = get_option('certificados_emails_notificacion', []);
        $usuarios_seleccionados = get_option('certificados_usuarios_notificacion', []);
        $emails_usuarios = self::obtener_emails_usuarios_seleccionados($usuarios_seleccionados);
        
        // Combinar y eliminar duplicados
        $todos_emails = array_merge($emails_personalizados, $emails_usuarios);
        return array_unique($todos_emails);
    }
}
