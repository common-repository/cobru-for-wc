<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

/**
 * CobruWC_Checkout
 *
 * Class to handle all API interactions.
 *
 * @since 1.0
 */

class CobruWC_Checkout
{
	/**
	 * Class instance, used to control plugin's action from a third party plugin
	 *
	 * @var $instance
	 */
	public static $instance;

	/**
	 * Adds all the plugin's hooks
	 */
	public static function init()
	{
		$self = self::instance();

		add_action('woocommerce_after_checkout_form', [$self, 'print_cobru_form']);
		add_action('wp_enqueue_scripts', [$self, 'cobru_load_js']);
	}
	/**
	 * Access to a class instance
	 *
	 * @return CobruWC_Checkout
	 */
	public static function instance()
	{
		if (is_null(self::$instance)) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * Loads JS code to create a Cobru
	 */
	public function cobru_load_js()
	{
		if (is_checkout()) {
			wp_enqueue_script(
				'cobru-for-wc',
				COBRU_PLUGIN_URL . 'assets/js/cobru.js',
				['jquery'],
				COBRU_PLUGIN_VER,
				['in_footer' => true]
			);
		}
	}

	public function print_cobru_form($args)
	{
		$apiUrl = get_rest_url(null, 'wc/v4/cobru');
		$siteName = get_bloginfo('name');

		echo '<form id="frm-cobru" method="post" enctype="text/plain">';
		echo '<input type="hidden" name="third_party" value="true" />';
		echo '<input type="hidden" name="third_party_name" value="' . esc_html($siteName) . '" />';
		echo '<input type="hidden" name="callback_url" value="' . esc_url($apiUrl) . '" />';
		echo '<input type="hidden" name="payer_redirect_url" value="https://eventu.co" />';
		echo '<input type="hidden" name="name" value="" />';
		echo '<input type="hidden" name="email" value="" />';
		echo '<input type="hidden" name="document_number" value="" />';
		echo '<input type="hidden" name="document_type" value="" />';
		echo '<input type="hidden" name="phone" value="" />';
		echo '</form>';
	}
}

add_action('plugins_loaded', ['CobruWC_Checkout', 'init']);
