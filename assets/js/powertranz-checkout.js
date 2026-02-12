jQuery(function ($) {
    // Función para reinicializar si WooCommerce actualiza el checkout
    function initPowerTranzForm() {
        var cardNumberInput = $('#powertranz-card-number');
        var cardExpiryInput = $('#powertranz-card-expiry');
        var cardCvcInput = $('#powertranz-card-cvc');
        var iconSpan = $('.powertranz-card-icon');

        if (cardNumberInput.length === 0) return;

        // Desvincular eventos previos para evitar duplicados
        cardNumberInput.off('input keyup');
        cardExpiryInput.off('input keyup');
        cardCvcInput.off('input keyup');

        // --- 1. Formato Número de Tarjeta (Espacios) ---
        cardNumberInput.on('input', function () {
            var raw = $(this).val().replace(/\D/g, ''); // Solo números
            var formatted = '';

            // Agrupar de 4 en 4
            for (var i = 0; i < raw.length; i++) {
                if (i > 0 && i % 4 === 0) {
                    formatted += ' ';
                }
                formatted += raw[i];
            }

            // Limitar a longitud estándar (16 dígitos + 3 espacios = 19)
            // Algunas tarjetas pueden ser más largas, ajustamos a 23 chars por seguridad
            if (formatted.length > 23) {
                formatted = formatted.substring(0, 23);
            }

            $(this).val(formatted);

            // Detectar tipo para el icono
            var type = 'unknown';
            if (/^4/.test(raw)) {
                type = 'visa';
            } else if (/^5[1-5]/.test(raw) || /^2[2-7]/.test(raw)) {
                type = 'mastercard';
            } else if (/^3[47]/.test(raw)) {
                type = 'amex';
            }

            iconSpan.removeClass('visa mastercard amex unknown').addClass(type);
        });

        // --- 2. Formato Expiración (MM/AA) ---
        cardExpiryInput.on('input', function (e) {
            var input = $(this).val();
            // Si el usuario está borrando, no forzar formato
            if (e.originalEvent && e.originalEvent.inputType === 'deleteContentBackward') {
                return;
            }

            var raw = input.replace(/\D/g, '');
            var formatted = raw;

            if (raw.length >= 2) {
                formatted = raw.substring(0, 2) + ' / ' + raw.substring(2, 4);
            }

            $(this).val(formatted);
        });

        // --- 3. CVC (Solo números) ---
        cardCvcInput.on('input', function () {
            var val = $(this).val().replace(/\D/g, '').substring(0, 4);
            $(this).val(val);
        });
    }

    // Inicializar al cargar
    initPowerTranzForm();

    // Re-inicializar cuando WooCommerce actualiza el checkout (AJAX)
    $(document.body).on('updated_checkout', function () {
        initPowerTranzForm();
    });
});
