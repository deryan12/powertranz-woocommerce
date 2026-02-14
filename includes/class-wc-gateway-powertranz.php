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
     * Sanitizar texto para PowerTranz.
     * Elimina caracteres prohibidos (acentos, ñ, simbolos) y trunca al largo maximo.
     *
     * @param string $text  Texto a sanitizar.
     * @param int    $max   Largo maximo permitido.
     * @return string
     */
    private function sanitize_for_powertranz($text, $max = 50)
    {
        // 1. Transliterar acentos a su equivalente ASCII (á->a, é->e, ñ->n, etc.)
        if (function_exists('iconv')) {
            $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        }

        // 2. Eliminar cualquier caracter que no sea alfanumerico, espacio, punto, guion o coma
        $text = preg_replace('/[^a-zA-Z0-9\s.\-,]/', '', $text);

        // 3. Eliminar espacios multiples
        $text = preg_replace('/\s+/', ' ', trim($text));

        // 4. Truncar al largo maximo
        return substr($text, 0, $max);
    }

    /**
     * Sanitizar numero de telefono para PowerTranz.
     * Solo digitos, sin +, espacios ni guiones.
     *
     * @param string $phone
     * @return string
     */
    private function sanitize_phone_for_powertranz($phone)
    {
        return preg_replace('/\D/', '', $phone);
    }

    /**
     * Construir payload JSON para la API SPI
     */
    private function get_transaction_payload($order)
    {
        $order_id = $order->get_id();
        $amount = $order->get_total();
        $currency = $order->get_currency();
        $currency_code = $this->get_iso_numeric($currency);

        // Datos de tarjeta desde POST
        $card_number = isset($_POST['powertranz-card-number']) ? sanitize_text_field(str_replace(' ', '', $_POST['powertranz-card-number'])) : '';
        $card_expiry = isset($_POST['powertranz-card-expiry']) ? sanitize_text_field($_POST['powertranz-card-expiry']) : '';
        $card_cvc = isset($_POST['powertranz-card-cvc']) ? sanitize_text_field($_POST['powertranz-card-cvc']) : '';

        // Formatear expiracion (MM / YY -> YYMM)
        $card_expiry = str_replace(' ', '', $card_expiry);
        $exp_parts = explode('/', $card_expiry);

        $exp_str = '';
        if (count($exp_parts) === 2) {
            $month = str_pad($exp_parts[0], 2, '0', STR_PAD_LEFT);
            $year = $exp_parts[1];
            $exp_str = $year . $month;
        }

        // --- BillingAddress (Obligatorio para Honduras / 3DS) ---
        $billing_line1_raw = $order->get_billing_address_1();
        $billing_line2_raw = $order->get_billing_address_2();

        // Line1 max 30 chars. Si sobra, mover a Line2
        $billing_line1 = $this->sanitize_for_powertranz($billing_line1_raw, 30);
        $overflow = '';
        if (strlen($this->sanitize_for_powertranz($billing_line1_raw, 999)) > 30) {
            $overflow = substr($this->sanitize_for_powertranz($billing_line1_raw, 999), 30) . ' ';
        }
        $billing_line2 = $this->sanitize_for_powertranz($overflow . $billing_line2_raw, 50);

        $billing_city = $this->sanitize_for_powertranz($order->get_billing_city(), 50);
        $billing_country = $order->get_billing_country(); // ISO 2-letter code (ej. HN)
        $billing_phone = $this->sanitize_phone_for_powertranz($order->get_billing_phone());
        $billing_email = trim($order->get_billing_email());

        // Sanitizar CardholderName
        $cardholder_name = $this->sanitize_for_powertranz(
            $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            45
        );

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
            "TaxAmount" => 0.00, // Obligatorio para Honduras (banco lo requiere)
            "ThreeDSecure" => $this->enable_3ds === 'yes',
            "Source" => array(
                "CardPan" => $card_number,
                "CardCvv" => $card_cvc,
                "CardExpiration" => $exp_str,
                "CardholderName" => $cardholder_name,
            ),
            "BillingAddress" => array(
                "Line1" => $billing_line1,
                "Line2" => $billing_line2,
                "City" => $billing_city,
                "County" => $billing_country,
                "State" => "",
                "PostalCode" => "",
                "PhoneNumber" => $billing_phone,
                "EmailAddress" => $billing_email,
            ),
            "OrderIdentifier" => (string) $order_id,
            "AddressMatch" => false,
            "ExtendedData" => array(
                "ThreeDSecure" => array(
                    "ChallengeWindowSize" => 5,
                    "ChallengeIndicator" => "01"
                ),
                "MerchantResponseUrl" => admin_url('admin-ajax.php?action=powertranz_callback'),
                "BrowserInfo" => $browser_info
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
     * Formulario de campos de pago – Estilo CodePen quinlo/YONMEa
     */
    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }

        echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
        do_action('woocommerce_credit_card_form_start', $this->id);
        ?>

        <!-- ===== VISUAL CARD ===== -->
        <div class="container preload">
            <div class="creditcard">
                <div class="front">
                    <div id="ccsingle"></div>
                    <svg version="1.1" id="cardfront" xmlns="http://www.w3.org/2000/svg"
                        xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 750 471"
                        style="enable-background:new 0 0 750 471;" xml:space="preserve">
                        <g id="Front">
                            <g id="CardBackground">
                                <g id="Page-1_1_">
                                    <g id="amex_1_">
                                        <path id="Rectangle-1_1_" class="lightcolor grey" d="M40,0h670c22.1,0,40,17.9,40,40v391c0,22.1-17.9,40-40,40H40c-22.1,0-40-17.9-40-40V40
                                C0,17.9,17.9,0,40,0z" />
                                    </g>
                                </g>
                                <path class="darkcolor greydark"
                                    d="M750,431V193.2c-217.6-57.5-556.4-13.5-750,24.9V431c0,22.1,17.9,40,40,40h670C732.1,471,750,453.1,750,431z" />
                            </g>
                            <text transform="matrix(1 0 0 1 60.106 295.0121)" id="svgnumber" class="st2 st3 st4">0123 4567 8910
                                1112</text>
                            <text transform="matrix(1 0 0 1 54.1064 428.1723)" id="svgname" class="st2 st5 st6">JOHN DOE</text>
                            <text transform="matrix(1 0 0 1 54.1074 389.8793)" class="st7 st5 st8">cardholder name</text>
                            <text transform="matrix(1 0 0 1 479.7754 388.8793)" class="st7 st5 st8">expiration</text>
                            <text transform="matrix(1 0 0 1 65.1054 241.5)" class="st7 st5 st8">card number</text>
                            <g>
                                <text transform="matrix(1 0 0 1 574.4219 433.8095)" id="svgexpire"
                                    class="st2 st5 st9">01/23</text>
                                <text transform="matrix(1 0 0 1 479.3848 417.0097)" class="st2 st10 st11">VALID</text>
                                <text transform="matrix(1 0 0 1 479.3848 435.6762)" class="st2 st10 st11">THRU</text>
                                <polygon class="st2" points="554.5,421 540.4,414.2 540.4,427.9" />
                            </g>
                            <g id="cchip">
                                <g>
                                    <path class="st2" d="M168.1,143.6H82.9c-10.2,0-18.5-8.3-18.5-18.5V74.9c0-10.2,8.3-18.5,18.5-18.5h85.3
                            c10.2,0,18.5,8.3,18.5,18.5v50.2C186.6,135.3,178.3,143.6,168.1,143.6z" />
                                </g>
                                <g>
                                    <g>
                                        <rect x="82" y="70" class="st12" width="1.5" height="60" />
                                    </g>
                                    <g>
                                        <rect x="167.4" y="70" class="st12" width="1.5" height="60" />
                                    </g>
                                    <g>
                                        <path class="st12" d="M125.5,130.8c-10.2,0-18.5-8.3-18.5-18.5c0-4.6,1.7-8.9,4.7-12.3c-3-3.4-4.7-7.7-4.7-12.3
                                c0-10.2,8.3-18.5,18.5-18.5s18.5,8.3,18.5,18.5c0,4.6-1.7,8.9-4.7,12.3c3,3.4,4.7,7.7,4.7,12.3
                                C143.9,122.5,135.7,130.8,125.5,130.8z M125.5,70.8c-9.3,0-16.9,7.6-16.9,16.9c0,4.4,1.7,8.6,4.8,11.8l0.5,0.5l-0.5,0.5
                                c-3.1,3.2-4.8,7.4-4.8,11.8c0,9.3,7.6,16.9,16.9,16.9s16.9-7.6,16.9-16.9c0-4.4-1.7-8.6-4.8-11.8l-0.5-0.5l0.5-0.5
                                c3.1-3.2,4.8-7.4,4.8-11.8C142.4,78.4,134.8,70.8,125.5,70.8z" />
                                    </g>
                                    <g>
                                        <rect x="82.8" y="82.1" class="st12" width="25.8" height="1.5" />
                                    </g>
                                    <g>
                                        <rect x="82.8" y="117.9" class="st12" width="26.1" height="1.5" />
                                    </g>
                                    <g>
                                        <rect x="142.4" y="82.1" class="st12" width="25.8" height="1.5" />
                                    </g>
                                    <g>
                                        <rect x="142" y="117.9" class="st12" width="26.2" height="1.5" />
                                    </g>
                                </g>
                            </g>
                        </g>
                    </svg>
                </div>
                <div class="back">
                    <svg version="1.1" id="cardback" xmlns="http://www.w3.org/2000/svg"
                        xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 750 471"
                        style="enable-background:new 0 0 750 471;" xml:space="preserve">
                        <g id="Front">
                            <line class="st0" x1="35.3" y1="10.4" x2="36.7" y2="11" />
                        </g>
                        <g id="Back">
                            <g id="Page-1_2_">
                                <g id="amex_2_">
                                    <path id="Rectangle-1_2_" class="darkcolor greydark" d="M40,0h670c22.1,0,40,17.9,40,40v391c0,22.1-17.9,40-40,40H40c-22.1,0-40-17.9-40-40V40
                            C0,17.9,17.9,0,40,0z" />
                                </g>
                            </g>
                            <rect y="61.6" class="st2" width="750" height="78" />
                            <g>
                                <path class="st3" d="M701.1,249.1H48.9c-3.3,0-6-2.7-6-6v-52.5c0-3.3,2.7-6,6-6h652.1c3.3,0,6,2.7,6,6v52.5
                        C707.1,246.4,704.4,249.1,701.1,249.1z" />
                                <rect x="42.9" y="198.6" class="st4" width="664.1" height="10.5" />
                                <rect x="42.9" y="224.5" class="st4" width="664.1" height="10.5" />
                                <path class="st5"
                                    d="M701.1,184.6H618h-8h-10v64.5h10h8h83.1c3.3,0,6-2.7,6-6v-52.5C707.1,187.3,704.4,184.6,701.1,184.6z" />
                            </g>
                            <text transform="matrix(1 0 0 1 621.999 227.2734)" id="svgsecurity" class="st6 st7">985</text>
                            <g class="st8">
                                <text transform="matrix(1 0 0 1 518.083 280.0879)" class="st9 st6 st10">security code</text>
                            </g>
                            <rect x="58.1" y="378.6" class="st11" width="375.5" height="13.5" />
                            <rect x="58.1" y="405.6" class="st11" width="421.7" height="13.5" />
                            <text transform="matrix(1 0 0 1 59.5073 228.6099)" id="svgnameback" class="st12 st13">John
                                Doe</text>
                        </g>
                    </svg>
                </div>
            </div>
        </div>

        <!-- ===== FORM FIELDS ===== -->
        <div class="form-container">
            <div class="field-container">
                <label for="pt-name">Nombre del Titular</label>
                <input id="pt-name" maxlength="20" type="text">
            </div>
            <div class="field-container">
                <label for="pt-cardnumber">Número de Tarjeta</label>
                <input id="pt-cardnumber" type="text" pattern="[0-9]*" inputmode="numeric">
                <svg id="ccicon" class="ccicon" width="750" height="471" viewBox="0 0 750 471" version="1.1"
                    xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                </svg>
            </div>
            <div class="field-container">
                <label for="pt-expirationdate">Expiración (mm/yy)</label>
                <input id="pt-expirationdate" type="text" pattern="[0-9]*" inputmode="numeric">
            </div>
            <div class="field-container">
                <label for="pt-securitycode">Código de Seguridad</label>
                <input id="pt-securitycode" type="text" pattern="[0-9]*" inputmode="numeric">
            </div>
        </div>

        <!-- ===== HIDDEN FIELDS FOR WOOCOMMERCE ===== -->
        <input type="hidden" id="powertranz-card-number" name="powertranz-card-number" />
        <input type="hidden" id="powertranz-card-expiry" name="powertranz-card-expiry" />
        <input type="hidden" id="powertranz-card-cvc" name="powertranz-card-cvc" />

        <?php
        do_action('woocommerce_credit_card_form_end', $this->id);
        echo '<div class="clear"></div></fieldset>';
    }
}
