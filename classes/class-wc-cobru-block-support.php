<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Gateway_Cobru_Blocks extends AbstractPaymentMethodType
{
    private $gateway;
    protected $name = 'cobru'; // your payment gateway name
    public function initialize()
    {
        $this->settings = get_option('woocommerce_cobru_settings', []);
        $this->gateway = new WC_Gateway_Cobru();
    }
    public function is_active()
    {
        return $this->gateway->is_available();
    }
    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'cobru-blocks-integration',
            COBRU_JS_URL . 'cobru-blocks.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('cobru-blocks-integration');
        }
        return ['cobru-blocks-integration'];
    }
    public function get_payment_method_data()
    {
        return [
            'title' => "<strong> {$this->gateway->title} </strong>",
            'description' => "<p>" . $this->gateway->description . "</p> <div class='cobru-checkout-logos'><img src='{$this->gateway->icon}'></div>",
        ];
    }
}
