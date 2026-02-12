<?php

defined('ABSPATH') || exit;

/**
 * WC_Gateway_PowerTranz Class.
 */
class WC_Gateway_PowerTranz extends WC_Payment_Gateway
{

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->id = 'powertranz';
        $this->icon = apply_filters('woocommerce_gateway_icon', '', $this->id);
        $this->has_fields = true;
        $this->method_title = __('PowerTranz', 'powertranz-woocommerce');
        $this->method_description = __('Acepta pagos con tarjeta de crédito vía PowerTranz SPI.', 'powertranz-woocommerce');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables.
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');
        $this->environment = $this->get_option('environment');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->password = $this->get_option('password');
        $this->enable_3ds = $this->get_option('enable_3ds');
        $this->debug = $this->get_option('debug');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_api_powertranz_callback', array($this, 'handler_callback'));
    }

    /**
     * Initialize Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Habilitar/Deshabilitar', 'powertranz-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Habilitar PowerTranz', 'powertranz-woocommerce'),
                'default' => 'yes',
            ),
            'environment' => array(
                'title' => __('Entorno', 'powertranz-woocommerce'),
                'type' => 'select',
                'description' => __('Selecciona el entorno de operación.', 'powertranz-woocommerce'),
                'default' => 'staging',
                'options' => array(
                    'staging' => __('Pruebas (Staging)', 'powertranz-woocommerce'),
                    'production' => __('Producción', 'powertranz-woocommerce'),
                ),
            ),
            'title' => array(
                'title' => __('Título', 'powertranz-woocommerce'),
                'type' => 'text',
                'description' => __('Título que ve el usuario durante el pago.', 'powertranz-woocommerce'),
                'default' => __('Tarjeta de Crédito/Débito (PowerTranz)', 'powertranz-woocommerce'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Descripción', 'powertranz-woocommerce'),
                'type' => 'textarea',
                'description' => __('Descripción que ve el usuario durante el pago.', 'powertranz-woocommerce'),
                'default' => __('Paga de forma segura con tu tarjeta de crédito o débito.', 'powertranz-woocommerce'),
            ),
            'merchant_id' => array(
                'title' => __('PowerTranz ID', 'powertranz-woocommerce'),
                'type' => 'text',
                'description' => __('Tu ID de comercio de PowerTranz.', 'powertranz-woocommerce'),
                'default' => '',
                'desc_tip' => true,
            ),
            'password' => array(
                'title' => __('Contraseña', 'powertranz-woocommerce'),
                'type' => 'password',
                'description' => __('Tu contraseña de API de PowerTranz.', 'powertranz-woocommerce'),
                'default' => '',
                'desc_tip' => true,
            ),
            'accepted_cards' => array(
                'title' => __('Tarjetas Aceptadas', 'powertranz-woocommerce'),
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select', // Utiliza Select2 de WooCommerce
                'css' => 'width: 400px;',
                'default' => array('visa', 'mastercard', 'amex'),
                'options' => array(
                    'visa' => 'Visa',
                    'mastercard' => 'MasterCard',
                    'amex' => 'American Express',
                ),
                'description' => __('Selecciona las tarjetas que deseas aceptar.', 'powertranz-woocommerce'),
            ),
            'enable_3ds' => array(
                'title' => __('3D-Secure', 'powertranz-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Habilitar 3D-Secure', 'powertranz-woocommerce'),
                'default' => 'yes',
                'description' => __('Activar para mayor seguridad (Verified by Visa, MasterCard SecureCode).', 'powertranz-woocommerce'),
            ),
            'debug' => array(
                'title' => __('Modo de Depuración', 'powertranz-woocommerce'),
                'type' => 'select',
                'description' => __('Opciones para el registro de errores.', 'powertranz-woocommerce'),
                'default' => 'no',
                'options' => array(
                    'no' => __('Apagado', 'powertranz-woocommerce'),
                    'checkout' => __('Mostrar en la Página de Pago', 'powertranz-woocommerce'),
                    'log' => __('Guardar en el registro', 'powertranz-woocommerce'),
                    'both' => __('Ambos', 'powertranz-woocommerce'),
                ),
            ),
        );
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        // 1. Preparar datos de la transacción
        $payload = $this->get_transaction_payload($order);

        // 2. Enviar solicitud a PowerTranz (Sale/Auth)
        $response = $this->send_api_request('spi/sale', $payload);

        if (is_wp_error($response)) {
            wc_add_notice('Error de conexión: ' . $response->get_error_message(), 'error');
            return array('result' => 'fail');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        $do_log = ($this->debug === 'log' || $this->debug === 'both');
        $do_show = ($this->debug === 'checkout' || $this->debug === 'both');

        if ($do_log) {
            $this->log('Response: ' . print_r($body, true));
        }

        if ($do_show) {
            wc_add_notice('<strong>PowerTranz Debug Response:</strong><br><pre>' . print_r($body, true) . '</pre>', 'notice');
        }

        // 3. Manejar respuesta
        if (isset($body['Errors']) && !empty($body['Errors'])) {
            // Manejar errores devueltos por la API
            foreach ($body['Errors'] as $error) {
                wc_add_notice($error['Message'], 'error');
            }
            return array('result' => 'fail');
        }

        // Caso 3DS / SP4 / Redirección HTML
        // PowerTranz puede devolver 'RedirHtml' (markup completo) o 'RedirectData' (solo JS para device fingerprinting con SP4)
        if ((isset($body['IsoResponseCode']) && $body['IsoResponseCode'] === 'SP4') || (isset($body['RedirHtml']) && !empty($body['RedirHtml']))) {

            $redir_html = '';

            // Caso 1: RedirHtml explícito (el gateway nos da el HTML completo)
            if (isset($body['RedirHtml']) && !empty($body['RedirHtml'])) {
                $redir_html = $body['RedirHtml'];
            }
            // Caso 2: SP4 con RedirectData (JS que requiere un form para device fingerprinting)
            elseif (isset($body['RedirectData']) && !empty($body['RedirectData'])) {
                $spi_token = $body['SpiToken'] ?? '';
                // Definir URL de destino. Para SP4 (Device Fingerprinting) se usa spi/Conector
                $base_url = ($this->environment === 'production') ? 'https://gateway.ptranz.com/api/' : 'https://staging.ptranz.com/api/';
                // Corrección: Usar 'spi/Conector' en lugar de 'spi/payment' para el paso de fingerprinting
                $action_url = $base_url . 'spi/Conector';

                $redir_html = '<form id="powertranz_3ds_form" action="' . esc_url($action_url) . '" method="post">';
                $redir_html .= '<input type="hidden" name="SpiToken" value="' . esc_attr($spi_token) . '" />';
                // Inputs requeridos por el script GetBrowserInfoAndSubmit de PowerTranz
                $redir_html .= '<input type="hidden" id="browserLanguage" name="browserLanguage" value="" />';
                $redir_html .= '<input type="hidden" id="browserWidth" name="browserWidth" value="" />';
                $redir_html .= '<input type="hidden" id="browserHeight" name="browserHeight" value="" />';
                $redir_html .= '<input type="hidden" id="browserTimeZone" name="browserTimeZone" value="" />';
                $redir_html .= '<input type="hidden" id="browserJavaEnabled" name="browserJavaEnabled" value="" />';
                $redir_html .= '<input type="hidden" id="browserJavascriptEnabled" name="browserJavascriptEnabled" value="" />';
                $redir_html .= '<input type="hidden" id="browserColorDepth" name="browserColorDepth" value="" />';
                $redir_html .= '<div class="powertranz-submit-wrapper" style="text-align:center; margin-top:20px;">';
                $redir_html .= '<p>' . __('Si no es redirigido automáticamente en unos segundos...', 'powertranz-woocommerce') . '</p>';
                $redir_html .= '<input type="submit" class="button" id="powertranz_submit_btn" value="' . __('Continuar', 'powertranz-woocommerce') . '" />';
                $redir_html .= '</div>';

                $redir_html .= '</form>';

                // Modificar el script para usar el ID del formulario en lugar de forms[0]
                $script = $body['RedirectData'];
                // Usar preg_replace para flexibilidad con forms[0] y reemplazarlo por nuestro ID
                // Nota: El script original hace document.forms[0].submit(). Lo cambiamos a document.getElementById(...).submit()
                $script = preg_replace('/document\.forms\[\s*0\s*\]/', "document.getElementById('powertranz_3ds_form')", $script);

                // Añadir un fallback script para asegurar el submit si el original falla o tarda
                // Mantenemos el fallback simple, ya que ahora el submit es estándar y no requiere fetch complejo
                $fallback_js = "
                setTimeout(function() {
                    var form = document.getElementById('powertranz_3ds_form');
                    var btn = document.getElementById('powertranz_submit_btn');
                    if(form) {
                        if(btn) btn.value = '" . __('Procesando...', 'powertranz-woocommerce') . "';
                        try {
                            // Intentar ejecutar la función original si existe y no se ha ejecutado
                            // Pero como hemos inyectado el script antes, ya debería haber corrido.
                            // Solo forzamos el submit si aún estamos aquí.
                            form.submit();
                        } catch(e) {
                            console.log('PowerTranz AutoSubmit Fallback Error:', e);
                            form.submit();
                        }
                    }
                }, 2000); // Dar tiempo al script original
                ";

                $redir_html .= '<script>' . $script . ' ' . $fallback_js . '</script>';
            }

            if (!empty($redir_html)) {
                set_transient('powertranz_3ds_' . $order_id, $redir_html, 10 * MINUTE_IN_SECONDS);
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true),
                );
            }
        }

        // Caso Éxito Directo (Sin 3DS)
        if (isset($body['Approved']) && $body['Approved'] === true) {
            $order->payment_complete($body['TransactionIdentifier']);
            $order->add_order_note(__('Pago completado vía PowerTranz. ID Transacción: ', 'powertranz-woocommerce') . $body['TransactionIdentifier']);

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }

        // Fallback catch-all
        wc_add_notice(__('Error desconocido procesando el pago. Por favor intenta de nuevo.', 'powertranz-woocommerce'), 'error');
        return array('result' => 'fail');
    }

    /**
     * Output for the order received page (3DS Intercept).
     */
    public function receipt_page($order_id)
    {
        $redir_html = get_transient('powertranz_3ds_' . $order_id);

        if ($redir_html) {
            echo '<div class="powertranz-3ds-container">';
            echo '<h3>' . __('Autenticación 3D-Secure Requerida', 'powertranz-woocommerce') . '</h3>';
            echo '<p>' . __('Por favor complete la verificación con su banco para continuar.', 'powertranz-woocommerce') . '</p>';
            echo $redir_html;
            echo '</div>';

            // script para auto-submit si es un form oculto, aunque RedirHtml suele ser completo.
            // PowerTranz RedirHtml suele incluir scripts de auto-submit.
        } else {
            echo '<p>' . __('Error: No se encontró información de redirección.', 'powertranz-woocommerce') . '</p>';
        }
    }

    /**
     * Handler for 3DS Callback via WC API.
     * URL: /wc-api/WC_Gateway_PowerTranz/
     */
    public function handler_callback()
    {
        // PowerTranz envía datos de vuelta. Usualmente POST.
        $spi_token = isset($_REQUEST['SpiToken']) ? sanitize_text_field($_REQUEST['SpiToken']) : null;

        if (!$spi_token) {
            wp_die('Acceso inválido a Callback de PowerTranz. Token no encontrado.', 'Error', array('response' => 400));
        }

        // Finalizar pago llamando a /api/spi/payment
        $payload = array("SpiToken" => $spi_token);
        $response = $this->send_api_request('spi/payment', $payload);

        if (is_wp_error($response)) {
            wp_die('Error de comunicación con PowerTranz: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($this->debug === 'yes' || $this->debug === 'log' || $this->debug === 'both') {
            $this->log('3DS Payment Response: ' . print_r($body, true));
        }

        // Recuperar Order ID de la respuesta de la API (OrderIdentifier)
        // PowerTranz devuelve el OrderIdentifier original en la respuesta de pago.
        $order_id = isset($body['OrderIdentifier']) ? absint($body['OrderIdentifier']) : 0;

        if ($order_id) {
            $order = wc_get_order($order_id);

            if (!$order) {
                wp_die('Error: Pedido no encontrado (' . $order_id . ')');
            }

            if (isset($body['Approved']) && $body['Approved'] === true) {
                $order->payment_complete($body['TransactionIdentifier'] ?? '');
                $order->add_order_note(__('Pago 3DS completado exitosamente.', 'powertranz-woocommerce'));
                wp_redirect($this->get_return_url($order));
                exit;
            } else {
                $msg = isset($body['Errors'][0]['Message']) ? $body['Errors'][0]['Message'] : 'Pago rechazado';
                // Añadir nota al pedido
                $order->add_order_note(__('Fallo en autenticación 3DS: ', 'powertranz-woocommerce') . $msg);

                wc_add_notice('Error en pago 3DS: ' . $msg, 'error');
                wp_redirect(wc_get_checkout_url());
                exit;
            }
        } else {
            // Fallback si no hay order_id en respuesta
            wp_die('Error: Order ID no retornado por PowerTranz en finalización de pago.');
        }
    }

    /**
     * Construir payload JSON para la API SPI
     */
    private function get_transaction_payload($order)
    {
        $order_id = $order->get_id();
        $amount = $order->get_total();
        $currency = $order->get_currency(); // Debería ser numérico ISO 4217 para PowerTranz? Docs dicen 'CurrencyCode'.
        // El usuario mencionó moneda Lempiras (HNL). PowerTranz usa códigos ISO numéricos usualmente (340 para HNL).
        // Vamos a asumir ISO numérico. Necesitaremos un mapa o función de conversión si WC usa 'HNL'.
        // *Revisión rápida*: PowerTranz docs usually expect ISO Numeric (e.g. 840 for USD).
        // HNL (Lempira) is 340.

        $currency_code = $this->get_iso_numeric($currency);

        // Datos de tarjeta desde POST
        // Nota: En un entorno real PCI, esto debe manejarse con extremo cuidado.
        // SPI asume que el servidor maneja los datos si se envían así.
        $card_number = isset($_POST['powertranz-card-number']) ? sanitize_text_field(str_replace(' ', '', $_POST['powertranz-card-number'])) : '';
        $card_expiry = isset($_POST['powertranz-card-expiry']) ? sanitize_text_field($_POST['powertranz-card-expiry']) : ''; // MM / YY
        $card_cvc = isset($_POST['powertranz-card-cvc']) ? sanitize_text_field($_POST['powertranz-card-cvc']) : '';

        // Formatear expiración (MM/YY o MM / YY -> YYMM)
        // Limpiamos espacios para manejar tanto "MM / YY" como "MM/YY"
        $card_expiry = str_replace(' ', '', $card_expiry); // Ahora es MM/YY
        $exp_parts = explode('/', $card_expiry);

        $exp_str = '';
        if (count($exp_parts) === 2) {
            // Asegurar que sean números y tengan longitud correcta
            $month = str_pad($exp_parts[0], 2, '0', STR_PAD_LEFT);
            $year = $exp_parts[1];
            // Si el año es de 2 dígitos, asumimos 20xx (aunque PowerTranz suele aceptar 2 dígitos si es YYMM)
            // La documentación de ejemplo (según recuerdo o común en estos gateways) suele ser YYMM.
            $exp_str = $year . $month;
        }

        $payload = array(
            "TransactionIdentifier" => wp_generate_uuid4(),
            "TotalAmount" => (float) $amount,
            "CurrencyCode" => $currency_code,
            "ThreeDSecure" => $this->enable_3ds === 'yes',
            "Source" => array(
                "CardPan" => $card_number,
                "CardCvv" => $card_cvc,
                "CardExpiration" => $exp_str,
                "CardholderName" => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            ),
            "OrderIdentifier" => (string) $order_id,
            "AddressMatch" => false,
            "ExtendedData" => array(
                "ThreeDSecure" => array(
                    "ChallengeWindowSize" => 5,
                    "ChallengeIndicator" => "01" // 01 = No preference, standard.
                ),
                "MerchantResponseUrl" => WC()->api_request_url('WC_Gateway_PowerTranz')
            )
        );

        return $payload;
    }

    /**
     * Enviar request a la API
     */
    private function send_api_request($endpoint, $data)
    {
        $base_url = ($this->environment === 'production')
            ? 'https://gateway.ptranz.com/api/'
            : 'https://staging.ptranz.com/api/';

        $url = $base_url . $endpoint;

        $args = array(
            'body' => json_encode($data, JSON_UNESCAPED_SLASHES),
            'headers' => array(
                'Content-Type' => 'application/json',
                'PowerTranz-PowerTranzId' => $this->merchant_id,
                'PowerTranz-PowerTranzPassword' => $this->password,
            ),
            'timeout' => 60,
        );

        $do_log = ($this->debug === 'log' || $this->debug === 'both');
        $do_show = ($this->debug === 'checkout' || $this->debug === 'both');

        if ($do_log) {
            $this->log('Request to ' . $url . ': ' . print_r($args, true));
        }

        if ($do_show) {
            wc_add_notice('<strong>PowerTranz Debug Request:</strong><br>URL: ' . $url . '<br><pre>' . print_r($args, true) . '</pre>', 'notice');
        }

        return wp_remote_post($url, $args);
    }

    /**
     * Map Currency to ISO Numeric
     */
    private function get_iso_numeric($currency_code)
    {
        $map = array(
            'HNL' => '340', // Lempira
            'USD' => '840', // US Dollar
            'EUR' => '978',
            'GBP' => '826',
            // Añadir más si es necesario
        );

        return isset($map[$currency_code]) ? $map[$currency_code] : '840'; // Default USD
    }

    /**
     * Helper log function
     */
    public function log($message)
    {
        if (empty($this->log)) {
            $this->log = new WC_Logger();
        }
        $this->log->add('powertranz', $message);
    }

    /**
     * Formulario de campos de pago
     */
    public function payment_fields()
    {
        // Renderizar formulario personalizado o estándar con clases para JS
        echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';

        do_action('woocommerce_credit_card_form_start', $this->id);

        // Número de tarjeta
        echo '<div class="form-row form-row-wide">
             <label>' . esc_html__('Card Number', 'woocommerce') . ' <span class="required">*</span></label>
             <div class="powertranz-card-input-wrapper">
                <input id="powertranz-card-number" class="input-text wc-credit-card-form-card-number" type="tel" maxlength="20" autocomplete="off" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" name="powertranz-card-number" />
                <span class="powertranz-card-icon"></span>
             </div>
             </div>';

        // Expiración y CVC
        echo '<div class="form-row form-row-first">
             <label>' . esc_html__('Expiry (MM/YY)', 'woocommerce') . ' <span class="required">*</span></label>
             <input id="powertranz-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="tel" autocomplete="off" placeholder="' . esc_attr__('MM / YY', 'woocommerce') . '" name="powertranz-card-expiry" />
             </div>';

        echo '<div class="form-row form-row-last">
             <label>' . esc_html__('Card Code', 'woocommerce') . ' <span class="required">*</span></label>
             <input id="powertranz-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="tel" autocomplete="off" placeholder="' . esc_attr__('CVC', 'woocommerce') . '" name="powertranz-card-cvc" />
             </div>';

        do_action('woocommerce_credit_card_form_end', $this->id);

        echo '<div class="clear"></div></fieldset>';
    }
}
