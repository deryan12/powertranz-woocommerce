<?php
/**
 * Plugin Name: Powertranz Woocommerce
 * Plugin URI: https://marketdosnueve.com
 * Description: Pasarela de pagos PowerTranz para WooCommerce con soporte para SPI y 3D-Secure.
 * Version: 1.0.0
 * Author: Deryan Monsalve
 * Author URI: https://marketdosnueve.com
 * Text Domain: powertranz-woocommerce
 * Domain Path: /i18n/languages/
 *
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *    
 * @package PowerTranz_WooCommerce
 */

defined('ABSPATH') || exit;

// Definir constantes del plugin
define('POWERTRANZ_VERSION', '1.0.0');
define('POWERTRANZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('POWERTRANZ_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Función principal para iniciar el plugin solo si WooCommerce está activo.
 */
function powertranz_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // Incluir la clase de la pasarela
    require_once POWERTRANZ_PLUGIN_DIR . 'includes/class-wc-gateway-powertranz.php';

    // Registrar la pasarela en WooCommerce
    add_filter('woocommerce_payment_gateways', 'add_powertranz_gateway');
}
add_action('plugins_loaded', 'powertranz_init');

/**
 * Añadir la pasarela a la lista de métodos de pago de WooCommerce.
 *
 * @param array $gateways Lista actual de pasarelas.
 * @return array Lista actualizada.
 */
function add_powertranz_gateway($gateways)
{
    $gateways[] = 'WC_Gateway_PowerTranz';
    return $gateways;
}

/**
 * Encolar scripts y estilos para el checkout (logos de tarjetas).
 */
function powertranz_checkout_assets()
{
    if (is_checkout()) {
        wp_enqueue_style('powertranz-checkout-css', POWERTRANZ_PLUGIN_URL . 'assets/css/powertranz-checkout.css', array(), POWERTRANZ_VERSION);
        wp_enqueue_script('powertranz-checkout-js', POWERTRANZ_PLUGIN_URL . 'assets/js/powertranz-checkout.js', array('jquery'), POWERTRANZ_VERSION, true);

        // Pasar URLs de imágenes a JS
        wp_localize_script('powertranz-checkout-js', 'powertranz_params', array(
            'plugin_url' => POWERTRANZ_PLUGIN_URL
        ));
    }
}
add_action('wp_enqueue_scripts', 'powertranz_checkout_assets');

/**
 * AJAX Callback Handler (Fallback for 403/Rest Issues)
 * URL: /wp-admin/admin-ajax.php?action=powertranz_callback
 */
add_action('wp_ajax_nopriv_powertranz_callback', 'powertranz_ajax_handler');
add_action('wp_ajax_powertranz_callback', 'powertranz_ajax_handler');

function powertranz_ajax_handler()
{
    // Ensure WC is loaded
    if (!class_exists('WC_Payment_Gateway')) {
        die('WooCommerce not active');
    }

    // Load gateway class if needed
    if (!class_exists('WC_Gateway_PowerTranz')) {
        require_once POWERTRANZ_PLUGIN_DIR . 'includes/class-wc-gateway-powertranz.php';
    }

    $gateway = new WC_Gateway_PowerTranz();

    // Log reachability
    if (class_exists('WC_Logger')) {
        $logger = new WC_Logger();
        $logger->add('powertranz', 'AJAX Callback Handled. Request Method: ' . $_SERVER['REQUEST_METHOD']);
    }

    // Call the handler
    $gateway->handler_callback();

    // Ajax handlers must die
    die();
}

/**
 * Custom AJAX Endpoint for 3DS Display (Bypasses WooCommerce Redirects)
 */
add_action('wp_ajax_nopriv_powertranz_3ds', 'powertranz_ajax_3ds_handler');
add_action('wp_ajax_powertranz_3ds', 'powertranz_ajax_3ds_handler');

function powertranz_ajax_3ds_handler()
{
    // Ensure WC is loaded
    if (!class_exists('WC_Payment_Gateway')) {
        die('WooCommerce not active');
    }

    // Load gateway class if needed
    if (!class_exists('WC_Gateway_PowerTranz')) {
        require_once POWERTRANZ_PLUGIN_DIR . 'includes/class-wc-gateway-powertranz.php';
    }

    $gateway = new WC_Gateway_PowerTranz();
    $gateway->render_3ds_page_handler();

    die();
}
