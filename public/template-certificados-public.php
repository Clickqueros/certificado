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
        <p class="certificados-subtitulo">Busca por nombre de instalación o NIT</p>
    </div>

    <!-- Campo de búsqueda AJAX -->
    <div class="certificados-busqueda">
        <div class="busqueda-input-group">
            <input 
                type="text" 
                id="busqueda-certificados" 
                placeholder="Escribe el nombre de la instalación o NIT..."
                class="busqueda-input"
            >
            <div class="busqueda-loading" id="busqueda-loading" style="display: none;">
                <span>Buscando...</span>
            </div>
        </div>
    </div>

    <!-- Contenedor de resultados -->
    <div class="certificados-resultados" id="certificados-resultados">
        <div class="resultados-mensaje" id="resultados-mensaje">
            <p>Escribe en el campo de búsqueda para encontrar certificados</p>
        </div>
    </div>
</div>

<style>
.certificados-public-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    font-family: Arial, sans-serif;
}

.certificados-header {
    text-align: center;
    margin-bottom: 30px;
}

.certificados-titulo {
    color: #333;
    margin-bottom: 10px;
    font-size: 28px;
}

.certificados-subtitulo {
    color: #666;
    font-size: 16px;
    margin: 0;
}

.certificados-busqueda {
    margin-bottom: 30px;
}

.busqueda-input-group {
    position: relative;
}

.busqueda-input {
    width: 100%;
    padding: 15px 20px;
    font-size: 16px;
    border: 2px solid #ddd;
    border-radius: 8px;
    box-sizing: border-box;
    transition: border-color 0.3s ease;
}

.busqueda-input:focus {
    outline: none;
    border-color: #0073aa;
}

.busqueda-loading {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #666;
    font-size: 14px;
}

.certificados-resultados {
    min-height: 200px;
}

.resultados-mensaje {
    text-align: center;
    color: #666;
    font-style: italic;
    padding: 40px 20px;
}

.certificados-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-top: 20px;
}

.certificado-item {
    background: #fef7d4;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: box-shadow 0.3s ease;
}

.certificado-item:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

/* Responsive: una columna en móviles */
@media (max-width: 768px) {
    .certificados-grid {
        grid-template-columns: 1fr;
    }
}

.certificado-nombre {
    color: #542a1a;
    font-size: 18px;
    font-weight: bold;
    margin: 0 0 10px 0;
}

.certificado-nit {
    color: #542a1a;
    font-size: 14px;
    margin: 0 0 15px 0;
}

/* Forzar colores marrones para títulos h3 y otros elementos */
.certificado-item h3,
.certificado-item .certificado-nombre,
.certificados-grid h3,
h3.certificado-nombre {
    color: #542a1a !important;
    font-weight: bold !important;
}

.certificado-acciones {
    text-align: right;
}

.btn-ver-pdf {
    background: #542a1a;
    color: #fef7d4;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 5px;
    font-size: 14px;
    transition: background-color 0.3s ease;
}

.btn-ver-pdf:hover {
    background: #6b3420;
    color: #fef7d4;
}

.no-resultados {
    text-align: center;
    color: #999;
    font-style: italic;
    padding: 40px 20px;
}
</style>

<script>
jQuery(document).ready(function($) {
    let timeoutId;
    
    // Función para realizar búsqueda AJAX
    function buscarCertificados(termino) {
        // Si no hay término o es muy corto, mostrar mensaje inicial
        if (termino.length < 2) {
            mostrarMensajeInicial();
            return;
        }
        
        $('#busqueda-loading').show();
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'buscar_certificados',
                busqueda: termino,
                nonce: '<?php echo wp_create_nonce('buscar_certificados_nonce'); ?>'
            },
            success: function(response) {
                $('#busqueda-loading').hide();
                
                if (response.success) {
                    if (response.data.encontrados) {
                        mostrarResultados(response.data.resultados);
                    } else {
                        mostrarNoResultados(response.data.mensaje);
                    }
                } else {
                    mostrarError('Error en la búsqueda: ' + response.data);
                }
            },
            error: function() {
                $('#busqueda-loading').hide();
                mostrarError('Error de conexión. Intenta nuevamente.');
            }
        });
    }
    
    // Función para mostrar resultados
    function mostrarResultados(resultados) {
        let html = '<div class="certificados-grid">';
        
        resultados.forEach(function(certificado) {
            html += `
                <div class="certificado-item">
                    <h3 class="certificado-nombre">${certificado.nombre_instalacion}</h3>
                    <p class="certificado-nit">NIT: ${certificado.nit}</p>
                    <div class="certificado-acciones">
                        <a href="?ver_pdf=${certificado.id}" class="btn-ver-pdf" target="_blank">
                            📄 Ver PDF
                        </a>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        $('#certificados-resultados').html(html);
    }
    
    // Función para mostrar mensaje de no resultados
    function mostrarNoResultados(mensaje) {
        $('#certificados-resultados').html(`
            <div class="no-resultados">
                <p>${mensaje}</p>
            </div>
        `);
    }
    
    // Función para mostrar mensaje inicial
    function mostrarMensajeInicial() {
        $('#certificados-resultados').html(`
            <div class="resultados-mensaje">
                <p>Escribe en el campo de búsqueda para encontrar certificados</p>
            </div>
        `);
    }
    
    // Función para mostrar error
    function mostrarError(mensaje) {
        $('#certificados-resultados').html(`
            <div class="no-resultados">
                <p style="color: #d63638;">${mensaje}</p>
            </div>
        `);
    }
    
    // Mostrar mensaje inicial al cargar la página
    function inicializarPagina() {
        mostrarMensajeInicial();
    }
    
    // Inicializar página sin cargar certificados
    inicializarPagina();
    
    // Evento de búsqueda en tiempo real
    $('#busqueda-certificados').on('input', function() {
        const termino = $(this).val().trim();
        
        // Limpiar timeout anterior
        clearTimeout(timeoutId);
        
        // Establecer nuevo timeout para evitar muchas peticiones
        timeoutId = setTimeout(function() {
            buscarCertificados(termino);
        }, 300);
    });
    
    // Manejar clic en botón Ver PDF
    $(document).on('click', '.btn-ver-pdf', function(e) {
        e.preventDefault();
        const url = $(this).attr('href');
        window.open(url, '_blank');
    });
});
</script>