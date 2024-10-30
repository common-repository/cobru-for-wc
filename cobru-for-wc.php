<?php

/**
 * Cobru para WooCommerce
 * 
 * Plugin oficial del API de Cobru para ser usado con WooCommerce en Wordpress.
 * 
 * @link              https://cobru.co/desarrolladores/
 * @since             1.0.0
 * @package           cobru-for-wc
 *
 * @wordpress-plugin 
 * Plugin Name: 	Cobru for WC
 * Plugin URI: 		https://cobru.co/desarrolladores/
 * Description: 	Plugin oficial del API de Cobru para ser usado con WooCommerce en Wordpress.
 * Author: 			COBRU.CO
 * Author URI: 		https://github.com/CobruApp/cobru-for-wc
 * Version: 		1.6.1
 * License: 		GPLv3
 * License URI: 	https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: 	cobru-for-wc
 * Domain Path:  	/languages
 */
if (!defined('ABSPATH')) exit; // Exit if accessed directly 

define('COBRU_PLUGIN_URL', plugins_url('/', __FILE__));
define('COBRU_PLUGIN_VER', '1.6.1');

define('COBRU_PLUGIN_FILE', __FILE__);
define('COBRU_ROOT_DIR', __DIR__);
define('COBRU_JS_DIR', __DIR__ . '/js');
define('COBRU_VENDOR_DIR', __DIR__ . '/vendor');
define('COBRU_ASSETS_DIR', __DIR__ . '/assets');
define('COBRU_ROOT_URL', plugin_dir_url(__FILE__));
define('COBRU_ASSETS_URL', COBRU_ROOT_URL . 'assets/');
define('COBRU_JS_URL', COBRU_ASSETS_URL . 'js/');

/**
 * @since 1.0
 */
include 'classes/class-wc-cobru-api.php';
include 'classes/class-wc-cobru-rest-api.php';

add_action('plugins_loaded', 'cobru_load_class');
function cobru_load_class()
{
	include 'classes/class-wc-gateway-cobru.php';
	add_filter('woocommerce_payment_gateways', 'cobru_add_gw', 10);
	/**
	 * Add cobru gateway to WooCommerce.
	 *
	 * @param array $gateways List of gateways.
	 *
	 * @return array New list of gateways.
	 */
	function cobru_add_gw($gateways)
	{
		$gateways[] = 'WC_Gateway_Cobru';
		return $gateways;
	}
}
/**
 * @since 1.5
 */
add_action('plugins_loaded', 'cobru_direct_load_class');
function cobru_direct_load_class()
{
	$cobru_settings = get_option('woocommerce_cobru_settings');
	if ($cobru_settings['credit_card_direct_gw'] === 'yes') {
		include 'classes/class-wc-direct-gateway-cobru.php';
		add_filter('woocommerce_payment_gateways', 'cobru_direct_add_gw', 10);
		/**
		 * Add cobru direct gateway to WooCommerce.
		 *
		 * @param array $gateways List of gateways.
		 *
		 * @return array New list of gateways.
		 */
		function cobru_direct_add_gw($gateways)
		{
			$gateways[] = 'WC_Gateway_Cobru_Direct';
			return $gateways;
		}
	}
}

/**
 * Loads plugin's language file.
 * @since 1.0
 */
function cobru_load_language()
{
	$plugin_rel_path = basename(dirname(__FILE__)) . '/languages';
	load_plugin_textdomain('cobru-for-wc', false, $plugin_rel_path);
}
add_action('plugins_loaded', 'cobru_load_language');


/**
 * Woocommerce Checkout Block Support
 * @since 1.6
 * @autor @j0hnd03
 */

/**
 * Step 1: Declare the plugin support WooCommerce Checkout Blocks.
 */

 /**
 * Custom function to declare compatibility with cart_checkout_blocks feature 
 */
function declare_cart_checkout_blocks_compatibility()
{
	// Check if the required class exists
	if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
		// Declare compatibility for 'cart_checkout_blocks'
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
	}
}
// Hook the custom function to the 'before_woocommerce_init' action
add_action('before_woocommerce_init', 'declare_cart_checkout_blocks_compatibility');

/**
 * Step 2: Register WooCommerce Checkout Blocks payment method type registration.
 */

// Hook the custom function to the 'woocommerce_blocks_loaded' action
add_action('woocommerce_blocks_loaded', 'oawoo_register_order_approval_payment_method_type');
/**
 * Custom function to register a payment method type
 */
function oawoo_register_order_approval_payment_method_type()
{
	// Check if the required class exists
	if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
		return;
	}
	// Include the custom Blocks Checkout class
	include 'classes/class-wc-cobru-block-support.php';

	// Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
			// Register an instance of My_Custom_Gateway_Blocks
			$payment_method_registry->register(new WC_Gateway_Cobru_Blocks);
		}
	);
}
