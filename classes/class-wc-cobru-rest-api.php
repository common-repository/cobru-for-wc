<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly 

/**
 * CobruWC_Rest_Api
 *
 * Class to implement WP API Rest.
 *
 * @since 1.0
 */

class CobruWC_Rest_Api extends WP_REST_Controller
{

    public function register_routes()
    {
        $version   = '4';
        $namespace = 'wc/v' . $version;
        $base      = 'cobru';

        register_rest_route($namespace, '/' . $base, [
            [
                'methods'               => WP_REST_Server::EDITABLE,
                'callback'              => [$this, 'received_callback_data'],
                'args'                  => $this->get_endpoint_args_for_item_schema(false),
                'permission_callback'   => '__return_true',
            ],
        ]);
    }

    /**
     * Receives Cobru callback to confirm payment
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function received_callback_data($request)
    {
        $data   = $request->get_params();
        /*$orders = wc_get_orders([
            'meta_query' => [
                [
                    'key'   => WC_Gateway_Cobru::META_URL,
                    'value' => $data['url'],
                ]
            ]
        ]);*/
        $order     = wc_get_order($data['orderId']); // ocastellar 2021/08/23
        $payment_method = $order->get_payment_method();

        // if (is_array($orders) && count($orders)) { NO aplica por que siempre debe venir un id
        //  $order = $orders[0]; ya no es un array
        $order->add_order_note(print_r($data, true), false);


        if (array_key_exists('state', $data)) {


            if ($data['state'] == 3) {

                if ($payment_method == 'cobru') {
                    // LOAD OPTIONS
                    $cobru_settings  = get_option('woocommerce_cobru_settings');

                    // CREDIT CARD MEASURES
                    if ($data['payment_method'] === 'credit_card') { // 1.3.0 @j0hnd03
                        $credit_card_precaution = $cobru_settings['credit_card_precaution'];
                        if ($credit_card_precaution == 'yes') {
                            $order_status = 'on-hold';
                            $note   = __('Payment approved and pending because it is with a Credit Card.', 'cobru-for-wc');
                        } else {
                            $max_safe_ammount = $cobru_settings['max_safe_ammount'];
                            if ($data['amount'] <= $max_safe_ammount) {
                                $order_status   = 'completed';
                                $note   = __('Payment has been approved, secure amount.', 'cobru-for-wc');
                            } else {
                                $order_status = 'on-hold';
                                $note   = __('Payment approved and pending because it is with a Credit Card.', 'cobru-for-wc');
                            }
                        }
                    } else {
                        // ALL OTHER PAYMENT METHODS
                        // GET SPECIFIC OPTIONS
                        $order_status   = $cobru_settings['status_to_set'];
                        $note   = __('Payment approved.', 'cobru-for-wc');
                    }
                }
                /**
                 * @since 1.5
                 */
                else if ($payment_method == 'cobru-direct') {
                    if (($order->get_status() == 'pending')) {
                        $cobru_settings  = get_option('woocommerce_cobru-direct_settings');

                        $note   = __('Payment approved.', 'cobru-for-wc');
                        $order_status   = $cobru_settings['status_to_set'];
                    } else {
                        $note   = __('The API Call Back has been reached but the CC direct payment has already set a order status.', 'cobru-for-wc');
                        $order_status = false;
                    }
                }
            } else if ($data['state'] == 1) {
                $order_status = 'on-hold';
                $note = __('Payment processing.', 'cobru-for-wc');
            } else if ($data['state'] == 2) {
                $order_status = 'failed'; // was processing, and should be 'failed'
                $note = __('Payment NOT approved.', 'cobru-for-wc');
            } else if ($data['state'] == 4) {
                $order_status = 'canceled';
                $note = __('Payment refunded.', 'cobru-for-wc');
            } else {
                $order_status = 'failed';
                $note = __('Payment response not expected.', 'cobru-for-wc');
            }
            if ($order_status !== false) {
                $order->set_status($order_status, $note);
                $order->save();
            } else {
                $order->add_order_note($note, false);
            }

            try {
                return new WP_REST_Response($data, 200);
            } catch (Exception $e) {
                return new WP_Error('cant-create', __('message', 'cobru-for-wc'), ['status' => 500]);
            }
        }
    }
}

$cobru_rest_api = new CobruWC_Rest_Api();

add_action('rest_api_init', [$cobru_rest_api, 'register_routes']);
