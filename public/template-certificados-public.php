<?php
/**
 * Template para mostrar certificados aprobados en el frontend
 * Este archivo es incluido por el shortcode [certificados_aprobados]
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="certificados-public-container">
    <div class="certificados-header">
        <h2 class="certificados-titulo">Certificados Aprobados</h2>
        <p class="certificados-subtitulo">Busca y visualiza los certificados aprobados por nombre o código</p>
    </div>

    <!-- Formulario de búsqueda -->
    <div class="certificados-busqueda">
        <form method="GET" class="busqueda-form">
            <div class="busqueda-input-group">
                <input 
                    type="text" 
                    name="buscar_certificado" 
                    value="<?php echo esc_attr($busqueda); ?>" 
                    placeholder="Buscar por nombre o código de certificado..."
                    class="busqueda-input"
                >
                <button type="submit" class="busqueda-boton">
                    <span class="busqueda-icono">🔍</span>
                    Buscar
                </button>
            </div>
            <?php if (!empty($busqueda)): ?>
                <div class="busqueda-resultados">
                    <p>Resultados para: <strong><?php echo esc_html($busqueda); ?></strong></p>
                    <a href="<?php echo esc_url(remove_query_arg('buscar_certificado')); ?>" class="limpiar-busqueda">
                        Limpiar búsqueda
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Lista de certificados -->
    <div class="certificados-lista">
        <?php if (empty($certificados)): ?>
            <div class="certificados-vacio">
                <?php if (!empty($busqueda)): ?>
                    <div class="vacio-icono">🔍</div>
                    <h3>No se encontraron certificados</h3>
                    <p>No hay certificados aprobados que coincidan con tu búsqueda.</p>
                    <a href="<?php echo esc_url(remove_query_arg('buscar_certificado')); ?>" class="ver-todos-boton">
                        Ver todos los certificados
                    </a>
                <?php else: ?>
                    <div class="vacio-icono">📋</div>
                    <h3>No hay certificados aprobados</h3>
                    <p>Aún no hay certificados aprobados disponibles para mostrar.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="certificados-grid">
                <?php foreach ($certificados as $certificado): ?>
                    <div class="certificado-card">
                        <div class="certificado-header">
                            <div class="certificado-estado aprobado">
                                <span class="estado-icono">✅</span>
                                Aprobado
                            </div>
                            <div class="certificado-fecha">
                                <?php echo date('d/m/Y', strtotime($certificado->fecha)); ?>
                            </div>
                        </div>
                        
                        <div class="certificado-contenido">
                            <h3 class="certificado-nombre">
                                <?php echo esc_html($certificado->nombre); ?>
                            </h3>
                            
                            <div class="certificado-actividad">
                                <strong>Actividad:</strong> <?php echo esc_html($certificado->actividad); ?>
                            </div>
                            
                            <?php if (!empty($certificado->observaciones)): ?>
                                <div class="certificado-observaciones">
                                    <strong>Observaciones:</strong> <?php echo esc_html($certificado->observaciones); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="certificado-codigo">
                                <strong>Código:</strong> 
                                <span class="codigo-valor"><?php echo esc_html($certificado->codigo_unico); ?></span>
                            </div>
                        </div>
                        
                        <div class="certificado-footer">
                            <?php 
                            $pdf_url = CertificadosPersonalizadosPDF::obtener_url_pdf($certificado->id);
                            if ($pdf_url): 
                            ?>
                                <a href="<?php echo esc_url($pdf_url); ?>" 
                                   target="_blank" 
                                   class="ver-pdf-boton">
                                    <span class="pdf-icono">📄</span>
                                    Ver PDF
                                </a>
                            <?php else: ?>
                                <span class="pdf-no-disponible">
                                    <span class="pdf-icono">❌</span>
                                    PDF no disponible
                                </span>
                            <?php endif; ?>
                            
                            <div class="certificado-fecha-creacion">
                                Creado: <?php echo date('d/m/Y', strtotime($certificado->created_at)); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="certificados-info">
                <p class="certificados-contador">
                    Mostrando <?php echo count($certificados); ?> certificado<?php echo count($certificados) !== 1 ? 's' : ''; ?>
                    <?php if (!empty($busqueda)): ?>
                        que coinciden con tu búsqueda
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div> 