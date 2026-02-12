<?php

defined('ABSPATH') || exit;

/**
 * WC_Gateway_PowerTranz Class.
 */
class WC_Gateway_PowerTranz extends WC_Payment_Gateway
{

    /** @var WC_Logger Logger instance */
    public $log = false;

    public $instructions;
    public $environment;
    public $merchant_id;
    public $password;
    public $enable_3ds;
    public $debug;

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
        add_action('woocommerce_api_powertranz', array($this, 'handler_callback'));
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
                // Usamos el HTML proporcionado por PowerTranz tal cual.
                // Este HTML contiene un form que POSTea a Conductor, y un script que lo auto-envía.
                $redir_html = $body['RedirectData'];
            }

            if (!empty($redir_html)) {
                set_transient('powertranz_3ds_' . $order_id, $redir_html, 10 * MINUTE_IN_SECONDS);

                // Use Custom AJAX Endpoint to bypass WooCommerce Redirects
                $pay_url = admin_url('admin-ajax.php');
                $pay_url = add_query_arg(
                    array(
                        'action' => 'powertranz_3ds',
                        'order_id' => $order_id,
                        'key' => $order->get_order_key(),
                    ),
                    $pay_url
                );

                if ($this->debug === 'yes' || $this->debug === 'log' || $this->debug === 'both') {
                    $this->log('Redirecting to Custom 3DS AJAX Endpoint: ' . $pay_url);
                }

                return array(
                    'result' => 'success',
                    'redirect' => $pay_url,
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
    /**
     * Output for the order received page (3DS Intercept).
     * Kept for backward compatibility but likely unused now.
     */
    public function receipt_page($order_id)
    {
        error_log('PowerTranz DEBUG: Legacy Receipt Page Hit (Unexpected) for Order ID: ' . $order_id);
    }

    /**
     * Handle the Custom AJAX 3DS Page
     */
    public function render_3ds_page_handler()
    {
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';

        if (!$order_id || empty($key)) {
            wp_die('Invalid Request');
        }

        $order = wc_get_order($order_id);
        if (!$order || $order->get_order_key() !== $key) {
            wp_die('Invalid Order Key');
        }

        if ($this->debug === 'yes' || $this->debug === 'log' || $this->debug === 'both') {
            $this->log('Custom AJAX 3DS Handler Hit for Order ID: ' . $order_id);
        }

        $redir_html = get_transient('powertranz_3ds_' . $order_id);

        if ($redir_html) {
            if ($this->debug === 'yes' || $this->debug === 'log' || $this->debug === 'both') {
                $this->log('Ajax Handler Found Transient HTML. Rendering...');
            }
            echo $redir_html;
            exit; // Stop execution
        } else {
            if ($this->debug === 'yes' || $this->debug === 'log' || $this->debug === 'both') {
                $this->log('ERROR: Ajax Handler - No transient found for order ' . $order_id);
            }
            wp_die(__('Error: No se encontró información de redirección.', 'powertranz-woocommerce'));
        }
    }

    /**
     * Force Callback Intercept on Init.
     */
    public function force_callback_and_exit()
    {
        // Check standard WC API query var
        if (isset($_GET['wc-api']) && $_GET['wc-api'] === 'powertranz') {

            // Ensure logger
            if (empty($this->log)) {
                $this->log = new WC_Logger();
            }
            $this->log->add('powertranz', 'Force Intercept: Callback detected on Init. Method: ' . $_SERVER['REQUEST_METHOD']);

            $this->handler_callback();
            exit;
        }
    }

    /**
     * Handler for 3DS Callback via WC API.
     * URL: /wc-api/powertranz/
     */
    public function handler_callback()
    {
        // Debug: Validar que el endpoint es alcanzable (GET Request check)
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_REQUEST['SpiToken']) && !isset($_POST['Response'])) {
            wp_die('PowerTranz Callback Endpoint Reached successfully. (Method: GET)', 'PowerTranz OK');
        }

        $spi_token = null;
        $incoming_response_data = null;


        // 1. Check for 'Response' field (Standard SP4/3DS return from Conductor)
        $raw_post = file_get_contents('php://input');

        if (isset($_POST['Response'])) {
            $spi_token_json = stripslashes($_POST['Response']);

            // LOGGING CRÍTICO: Ver qué nos devuelve Conductor
            if (!empty($this->log)) {
                $this->log->add('powertranz', 'Callback Incoming $_POST: ' . print_r($_POST, true));
                $this->log->add('powertranz', 'Callback Incoming Body: ' . $raw_post);
            }

            $postData = json_decode($spi_token_json, true);
        } elseif (!empty($raw_post)) {
            // Fallback: Try decoding raw input if $_POST is empty (e.g. JSON payload)
            if (!empty($this->log)) {
                $this->log->add('powertranz', 'Callback Fallback: Reading raw input. ' . $raw_post);
            }
            $postData = json_decode($raw_post, true);

            // If raw post is query string formatted (unexpected but possible)
            if (empty($postData)) {
                parse_str($raw_post, $postData);
                if (isset($postData['Response'])) {
                    $postData = json_decode(stripslashes($postData['Response']), true);
                }
            }
        }

        if (isset($postData['SpiToken'])) {
            $spi_token = sanitize_text_field($postData['SpiToken']);
            if (!empty($this->log)) {
                $this->log->add('powertranz', 'Callback found SpiToken via JSON/Raw: ' . $spi_token);
            }
        }

        $incoming_response_data = $postData;

        // 2. Fallback to direct SpiToken field
        if (!$spi_token && isset($_REQUEST['SpiToken'])) {
            $spi_token = sanitize_text_field($_REQUEST['SpiToken']);
        }

        if (!$spi_token) {
            wp_die('Acceso inválido a Callback de PowerTranz. Token no encontrado.', 'Error', array('response' => 400));
        }

        // IMPORTANTE: spi/payment espera SOLO el token como String en el body (JSON encoded string).
        // Enviar un objeto JSON causa error 500 o respuesta vacía.
        $response = $this->send_api_request('spi/payment', $spi_token);

        if (is_wp_error($response)) {
            wp_die('Error de comunicación con PowerTranz: ' . $response->get_error_message());
        }

        $body_string = wp_remote_retrieve_body($response);
        $body = json_decode($body_string, true);

        if ($this->debug === 'yes' || $this->debug === 'log' || $this->debug === 'both') {
            $this->log('3DS Payment Response (Raw): ' . $body_string);
        }

        if (empty($body)) {
            // Si la respuesta es vacía, es posible que el token string sea rechazado por alguna razón,
            // pero el log anterior nos dirá qué pasó.
            wp_die('Error: Respuesta inválida o vacía de PowerTranz en spi/payment. Raw Body: ' . htmlspecialchars($body_string));
        }

        // Recuperar Order ID
        $order_id = isset($body['OrderIdentifier']) ? absint($body['OrderIdentifier']) : 0;

        if ($order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                // Order not found logic if needed
            }

            if (isset($body['Approved']) && $body['Approved'] === true) {
                $order->payment_complete($body['TransactionIdentifier'] ?? '');
                $order->add_order_note(__('Pago 3DS completado exitosamente.', 'powertranz-woocommerce'));
                wp_redirect($this->get_return_url($order));
                exit;
            } else {
                // Manejar errores (ej: 330 Not Permitted)
                $msg = isset($body['Errors'][0]['Message']) ? $body['Errors'][0]['Message'] : 'Pago rechazado';
                if (isset($body['IsoResponseCode']) && $body['IsoResponseCode'] !== '00') {
                    $msg .= ' (Code: ' . $body['IsoResponseCode'] . ')';
                }

                $order->add_order_note(__('Fallo en autenticación 3DS: ', 'powertranz-woocommerce') . $msg);
                wc_add_notice('Error en pago 3DS: ' . $msg, 'error');
                wp_redirect(wc_get_checkout_url());
                exit;
            }
        } else {
            wp_die('Error: Order ID no retornado por PowerTranz en finalización de pago. Body: ' . print_r($body, true));
        }
    }

    /**
     * Helper for REST API Callback.
     * Called by powertranz-woocommerce.php
     */
    public function rest_handler_callback($request)
    {
        // Debug Log
        if (empty($this->log)) {
            $this->log = new WC_Logger();
        }
        $this->log->add('powertranz', 'REST API Callback Reached. Method: ' . $_SERVER['REQUEST_METHOD']);

        // Inject request params into $_POST/$_REQUEST for the legacy handler to work without rewrite
        $params = $request->get_params();
        if (!empty($params)) {
            $_POST = array_merge($_POST, $params);
            $_REQUEST = array_merge($_REQUEST, $params);
        }

        // Execute logic
        $this->handler_callback();

        // Return valid REST response
        return new WP_REST_Response(array('status' => 'success', 'message' => 'Processed'), 200);
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

        // Recopilar BrowserInfo de los campos ocultos del frontend
        $browser_info = array(
            'ScreenWidth' => isset($_POST['powertranz_browser_width']) ? sanitize_text_field($_POST['powertranz_browser_width']) : '',
            'ScreenHeight' => isset($_POST['powertranz_browser_height']) ? sanitize_text_field($_POST['powertranz_browser_height']) : '',
            'ColorDepth' => isset($_POST['powertranz_browser_color_depth']) ? sanitize_text_field($_POST['powertranz_browser_color_depth']) : '',
            'Language' => isset($_POST['powertranz_browser_language']) ? sanitize_text_field($_POST['powertranz_browser_language']) : '',
            'TimeZone' => isset($_POST['powertranz_browser_timezone']) ? sanitize_text_field($_POST['powertranz_browser_timezone']) : '',
            'JavaEnabled' => (isset($_POST['powertranz_browser_java_enabled']) && $_POST['powertranz_browser_java_enabled'] === 'true'),
            'JavascriptEnabled' => true,
            'UserAgent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'AcceptHeader' => isset($_SERVER['HTTP_ACCEPT']) ? sanitize_text_field($_SERVER['HTTP_ACCEPT']) : '',
            'IP' => WC_Geolocation::get_ip_address()
        );

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
                // Use AJAX handler to avoid 403 and Rewrite issues
                "MerchantResponseUrl" => admin_url('admin-ajax.php?action=powertranz_callback')
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
        // Debug URL removed per user request

        // Renderizar formulario
        echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';

        do_action('woocommerce_credit_card_form_start', $this->id);

        // Número de tarjeta
        echo '<div class="form-row form-row-wide">
             <label>' . esc_html__('Número de Tarjeta', 'powertranz-woocommerce') . ' <span class="required">*</span></label>
             <div class="powertranz-card-input-wrapper">
                <input id="powertranz-card-number" class="input-text wc-credit-card-form-card-number" type="tel" inputmode="numeric" maxlength="23" autocomplete="cc-number" placeholder="0000 0000 0000 0000" name="powertranz-card-number" />
                <span class="powertranz-card-icon"></span>
             </div>
             </div>';

        // Expiración y CVC
        echo '<div class="form-row form-row-first">
             <label>' . esc_html__('Caducidad (MM / AA)', 'powertranz-woocommerce') . ' <span class="required">*</span></label>
             <input id="powertranz-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="tel" inputmode="numeric" maxlength="9" autocomplete="cc-exp" placeholder="' . esc_attr__('MM / AA', 'powertranz-woocommerce') . '" name="powertranz-card-expiry" />
             </div>';

        echo '<div class="form-row form-row-last">
             <label>' . esc_html__('Código de Seguridad (CVC)', 'powertranz-woocommerce') . ' <span class="required">*</span></label>
             <input id="powertranz-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="password" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="' . esc_attr__('CVC', 'powertranz-woocommerce') . '" name="powertranz-card-cvc" />
             </div>';

        do_action('woocommerce_credit_card_form_end', $this->id);

        echo '<div class="clear"></div></fieldset>';
    }
}
