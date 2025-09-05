/**
 * JavaScript para el frontend de certificados aprobados
 * Plugin: Certificados Antecore
 */

jQuery(document).ready(function($) {
    
    // Animación de entrada para las tarjetas
    function animateCards() {
        $('.certificado-card').each(function(index) {
            $(this).css({
                'opacity': '0',
                'transform': 'translateY(30px)'
            });
            
            setTimeout(function() {
                $(this).animate({
                    'opacity': '1',
                    'transform': 'translateY(0)'
                }, 600);
            }.bind(this), index * 100);
        });
    }
    
    // Ejecutar animación cuando se carga la página
    if ($('.certificado-card').length > 0) {
        animateCards();
    }
    
    // Mejorar la experiencia de búsqueda
    $('.busqueda-input').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            $(this).closest('form').submit();
        }
    });
    
    // Auto-focus en el campo de búsqueda
    if ($('.busqueda-input').val() === '') {
        $('.busqueda-input').focus();
    }
    
    // Efecto hover mejorado para botones
    $('.ver-pdf-boton').hover(
        function() {
            $(this).addClass('hover');
        },
        function() {
            $(this).removeClass('hover');
        }
    );
    
    // Copiar código al portapapeles
    $('.codigo-valor').on('click', function() {
        const codigo = $(this).text();
        const tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(codigo).select();
        document.execCommand('copy');
        tempInput.remove();
        
        // Mostrar notificación
        showNotification('Código copiado al portapapeles', 'success');
    });
    
    // Función para mostrar notificaciones
    function showNotification(message, type = 'info') {
        const notification = $(`
            <div class="certificados-notification ${type}">
                <span class="notification-message">${message}</span>
                <button class="notification-close">&times;</button>
            </div>
        `);
        
        $('body').append(notification);
        
        // Animar entrada
        notification.css({
            'opacity': '0',
            'transform': 'translateY(-20px)'
        }).animate({
            'opacity': '1',
            'transform': 'translateY(0)'
        }, 300);
        
        // Auto-remover después de 3 segundos
        setTimeout(function() {
            notification.animate({
                'opacity': '0',
                'transform': 'translateY(-20px)'
            }, 300, function() {
                notification.remove();
            });
        }, 3000);
        
        // Cerrar manualmente
        notification.find('.notification-close').on('click', function() {
            notification.animate({
                'opacity': '0',
                'transform': 'translateY(-20px)'
            }, 300, function() {
                notification.remove();
            });
        });
    }
    
    // Lazy loading para imágenes (si se agregan en el futuro)
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
    
    // Mejorar accesibilidad
    $('.certificado-card').on('keydown', function(e) {
        if (e.which === 13 || e.which === 32) { // Enter o Space
            e.preventDefault();
            $(this).find('.ver-pdf-boton').click();
        }
    });
    
    // Contador de caracteres para búsqueda (opcional)
    $('.busqueda-input').on('input', function() {
        const length = $(this).val().length;
        const maxLength = 100; // Límite opcional
        
        if (length > maxLength) {
            $(this).val($(this).val().substring(0, maxLength));
            showNotification('Límite de caracteres alcanzado', 'warning');
        }
    });
    
    // Filtro por tipo de actividad (funcionalidad futura)
    $('.filtro-actividad').on('change', function() {
        const actividad = $(this).val();
        
        if (actividad === '') {
            $('.certificado-card').show();
        } else {
            $('.certificado-card').hide();
            $('.certificado-card').each(function() {
                const cardActividad = $(this).find('.certificado-actividad').text();
                if (cardActividad.toLowerCase().includes(actividad.toLowerCase())) {
                    $(this).show();
                }
            });
        }
    });
    
    // Scroll suave para enlaces internos
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        const target = $(this.getAttribute('href'));
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 100
            }, 800);
        }
    });
    
    // Mejorar la experiencia móvil
    if (window.innerWidth <= 768) {
        // Ajustar comportamiento en móviles
        $('.certificado-card').on('touchstart', function() {
            $(this).addClass('touch-active');
        }).on('touchend', function() {
            setTimeout(() => {
                $(this).removeClass('touch-active');
            }, 150);
        });
    }
    
    // Debug: Log de eventos importantes
    if (window.location.search.includes('debug')) {
        console.log('Certificados Public JS loaded');
        console.log('Certificados encontrados:', $('.certificado-card').length);
    }
});

// Estilos CSS adicionales para notificaciones
const notificationStyles = `
<style>
.certificados-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    padding: 15px 20px;
    z-index: 9999;
    display: flex;
    align-items: center;
    gap: 10px;
    max-width: 300px;
    border-left: 4px solid #667eea;
}

.certificados-notification.success {
    border-left-color: #28a745;
}

.certificados-notification.warning {
    border-left-color: #ffc107;
}

.certificados-notification.error {
    border-left-color: #dc3545;
}

.notification-message {
    flex: 1;
    font-size: 14px;
    color: #495057;
}

.notification-close {
    background: none;
    border: none;
    font-size: 18px;
    color: #6c757d;
    cursor: pointer;
    padding: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notification-close:hover {
    color: #495057;
}

.certificado-card.touch-active {
    transform: scale(0.98);
}

@media (max-width: 768px) {
    .certificados-notification {
        top: 10px;
        right: 10px;
        left: 10px;
        max-width: none;
    }
}
</style>
`;

// Insertar estilos en el head
document.head.insertAdjacentHTML('beforeend', notificationStyles); 