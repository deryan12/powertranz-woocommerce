jQuery(document).ready(function($) {
    // Lógica para detección de tarjetas se implementará aquí
    var cardNumberInput = $('#powertranz-card-number');
    var iconSpan = $('.powertranz-card-icon');

    cardNumberInput.on('input', function() {
        var number = $(this).val().replace(/\D/g, '');
        var type = 'unknown';
        
        // Expresiones regulares simples para detección
        if (/^4/.test(number)) {
            type = 'visa';
        } else if (/^5[1-5]/.test(number)) {
            type = 'mastercard';
        } else if (/^3[47]/.test(number)) {
            type = 'amex';
        }

        // Actualizar clase para mostrar logo vía CSS
        iconSpan.removeClass('visa mastercard amex unknown').addClass(type);
    });
});
