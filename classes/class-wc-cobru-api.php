<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly 

/**
 * CobruWC_API
 *
 * Class to handle all API interactions.
 *
 * @since 1.0
 */

class CobruWC_API
{
	const BEARER = 'cobru-bearer';
	const OPTION_REFRESH = 'cobru-refresh';

	public $api_url;
	public $refresh_token;
	public $token;
	public $secret;
	public $bearer = false;
	public $payment_method_enabled;

	public function __construct($testmode, $refresh_token, $token, $secret, $payment_method_enabled)
	{
		$this->api_url  = $testmode ? 'https://dev.cobru.co/' : 'https://prod.cobru.co/';
		$this->refresh_token    = $refresh_token;
		$this->token    = $token;
		$this->secret   = $secret;
		$this->payment_method_enabled   = $payment_method_enabled;
	}

	public function url($path)
	{
		return $this->api_url . $path;
	}

	protected function get_header($include_bearer = true)
	{
		$headers = [
			'Accept'         => 'application/json',
			'Content-Type'   => 'application/json',
			'Api-Token'      => $this->token,
			'Api-Secret-Key' => $this->secret
		];
		// Comento esto temporalmente para pruebas
		if ($include_bearer) {
			$headers['Authorization'] = 'Bearer ' . $this->get_bearer();
		}

		return $headers;
	}

	public function get_bearer()
	{
		$bearer = false; //get_transient(self::BEARER);


		if ($bearer === false) {

			$refresh = get_option(self::OPTION_REFRESH, false);

			if ($refresh) {
				$response = wp_remote_post($this->url('/token/refresh/'), [
					'method'  => 'POST',
					'headers' => $this->get_header(false),
					'body'    => wp_json_encode([
						'refresh' => $this->refresh_token,
					]),
				]);

				if (is_wp_error($response)) {
					$error_message = $response->get_error_message();
					echo esc_html(__("Something went wrong: ", 'cobru-for-wc') . $error_message);

					return;
				} else {
					$data   = json_decode($response['body']);
					$bearer = $data->access;
				}
			} else {
				$bearer  = $data->access;
				$refresh = $this->refresh_token;
				update_option(self::OPTION_REFRESH, $refresh);
			}
		}

		if (!empty($bearer)) {
			set_transient(self::BEARER, $bearer, 14 * MINUTE_IN_SECONDS);
		}

		return $bearer;
	}
	/**
	 * Creates cobru and retrieves URL to redirect.
	 **/
	public function create_cobru($order)
	{
		$items = $order->get_items();
		$localidades = "";

		foreach ($items as $item) {
			$product = wc_get_product($item['product_id']);
			$localidades = $localidades . ', ' . $product->get_name();
			// TICKERA FIX FOR NON EVENTU SITES
			if (class_exists('TC_Event')) {
				$event = new TC_Event($product->get_meta('_event_name'));
			} else {
				$event = false;
			}
		}
		$args = [
			'amount'                 => round($order->get_total()),
			'description'            => __('Order', 'cobru-for-wc') . ' #' . $order->get_order_number() . (($event === false) ? '' : ' :: ' . $event->details->post_title), // TICKERA FIX FOR NON EVENTU SITES
			'expiration_days'        => 0,
			'client_assume_costs'    => false,
			'payment_method_enabled' => $this->payment_method_enabled,
			'iva'               	 => 0,
			'platform'               => "API",
			'payer_redirect_url'     => $order->get_checkout_order_received_url(),
			'callback'               => get_home_url() . '/wp-json/wc/v4/cobru?orderId=' . $order->get_order_number()
		];


		$response = wp_remote_post($this->url('/cobru/'), [
			'method'  => 'POST',
			'headers' => $this->get_header(),
			'body'    => wp_json_encode($args),
		]);

		if (is_wp_error($response) || isset($response['response']) && $response['response']['code'] != 201) {
			if (is_wp_error($response)) {
				$error_message = $response->get_error_message();
			} else {
				$data          = json_decode($response['body']);
				if (is_object($data)) {
					if (property_exists($data, "detail")) {
						$error_message =  $data->detail;
					} elseif (property_exists($data, "amount")) {
						$error_message =  $data->amount[0];
					} else {
						$error_message = $response['body'];
					}
				} else {
					$error_message = $response['body'];
				}
			}

			$cobru_response = [
				'result'  => 'error',
				'message' => $error_message
			];
			return $cobru_response;
		} else {
			$data = json_decode($response['body']);

			if ($data) {
				$cobru_response = [
					'result'     => 'success',
					'message'    => __('Cobru created', 'cobru-for-wc'),
					'pk'         => $data->pk,
					'url'        => $data->url,
					'amount' 	 => $data->amount,
					'fee_amount' => $data->fee_amount,
					'iva_amount' => $data->iva_amount,
				];
				return $cobru_response;
			} else {

				return [
					'result'  => 'error',
					'message' => $response['body']
				];
			}
		}
	}
	/**
	 * @param WC_Order $order
	 * @param WC_Gateway_Cobru_Direct $gw
	 * @return array
	 * @since 1.5
	 */
	public function send_payment($order, $gw)
	{
		// HTTP timeout hacks
		add_filter('http_request_timeout', function ($timeout) {
			return 60;
		});
		ini_set("default_socket_timeout", 60);

		$cobru_meta = WC_Gateway_Cobru_Direct::META_URL;
		$cobru_url = get_post_meta($order->get_id(), $cobru_meta)[0];

		if (!empty($cobru_url)) {
			$note = sprintf(
				"Payment details:\nBIN: %s\nCARD: %s",
				$gw->credit_card_number_bin,
				$gw->credit_card_number_last_4,
			);

			$order->add_order_note($note, false);

			$args = [
				'name'  			=> $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				'payment' 			=> 'credit_card',
				'cc'			 	=> get_post_meta($order->get_id(), 'document_number', true), // 
				'document_type'		=> 'CC',
				'email' 			=> $order->get_billing_email(),
				'phone' 			=> $order->get_billing_phone(),
				'phone_nequi' 		=> '',
				'address' 			=> $order->get_billing_address_1(),
				'push'				=> false,
				'bank'				=> null,
				'amount'			=> round($order->get_total()),
				'platform'			=> 'API',
				'credit_card'		=> $gw->credit_card_number,
				'expiration_date'	=> $gw->credit_card_expiration_date,
				'cvv'				=> $gw->credit_card_cvv,
				'dues'				=> $gw->credit_card_dues,
			];

			$response = wp_remote_post($this->url("{$cobru_url}"), [
				'method'  => 'POST',
				'headers' => $this->get_header(),
				'body'    => wp_json_encode($args),
			]);

			if (is_wp_error($response) || isset($response['response']) && $response['response']['code'] != 200) {
				// HTTP ERRORS
				if (is_wp_error($response)) {
					$error_message = $response->get_error_message();
				} else {

					$data          = json_decode($response['body'], TRUE);
					$error_message = '';

					if ($data !== null) {
						foreach ($data as $title => $message) {
							$error_message .= "{$title} : {$message}<br>\n";
						}
					} else {
						$error_message = $response['body'];
					}
				}

				return [
					'result'  => 'error',
					'message' => $error_message
				];
			} else {
				$data = json_decode($response['body'], true);
				$data_bug = json_decode($data, true); // WTF !!

				if (!empty($data_bug)) {
					$data = $data_bug;
				}

				if ($data) {
					if (is_array($data) && isset($data[1]['fields']['state'])) {

						$fields_to_note = [
							'owner',
							'date_created',
							'date_payed',
							'description',
							'amount',
							'before_amount',
							'payed_amount',
							'fee_iva_amount',
							'fee_amount',
							'currency_code',
							'iva_amount',
							'fee_iva',
							'amount_exceeded',
							'tax_amount_reteiva',
							'tax_amount_reteica',
							'tax_amount_retefuente',
							'state',
							'payment_method',
							'franchise',
							'reference_cobru',
							'payer_country_code',
							'payer_name',
							'payer_email',
							'payer_id_type',
							'payer_id',
							'payer_phone',
							'payer_address',
							'payer_redirect_url',
							'payment_order',
							'client_assume_costs',
							'confirmation_url',
							'payment_method_enabled',
							'expiration_date',
							'expiration_days',
						];
						$payment_data_to_note = [];
						$payment_data_to_note['gateway'] = $order->get_payment_method();
						$payment_data_to_note['gateway_title'] = $order->get_payment_method_title();

						foreach ($fields_to_note as $field) {
							$payment_data_to_note[$field] = $data[1]['fields'][$field];
						}

						$debug_note   = __('COBRU DATA: ', 'cobru-for-wc') . var_export($payment_data_to_note, true);
						$order->add_order_note($debug_note, false);

						if ($data[1]['fields']['state'] == 3) {


							$note   = __('Payment has been approved, New staus : ', 'cobru-for-wc');

							$order->set_status($gw->status_to_set, $note);
							$order->save();
							return [
								'result' => 'success',
								'redirect' => $gw->get_return_url($order),
							];
						} else if ($data[1]['fields']['state'] == 1) { // Fix for payments reaching Cobru server timeout for direct payments


							$note   = __('Payment is processing on the clients bank, lets give it some minutes to wait the response : ', 'cobru-for-wc');

							$order->set_status('pending', $note);
							$order->save();
							return [
								'result' => 'success',
								'redirect' => $gw->get_return_url($order),
							];
						} else {
							$cubru_states = array(
								0 => 'Creado',
								1 => 'En proceso',
								2 => 'No pagado',
								3 => 'Pagado',
								4 => 'Reembolsado',
								5 => 'Expirado'
							);
							$note = sprintf(
								"Cobru state: %s - %s",
								$data[1]['fields']['state'],
								$cubru_states[$data[1]['fields']['state']],
							);
							$order->add_order_note($note, false);
							return [
								'result'  => 'error',
								'message' => $note
							];
						}
					} else {
						return [
							'result'  => 'error',
							'message' => $data['cause']
						];
					}
				} else {
					return [
						'result'  => 'error',
						'message' => $data
					];
				}
			}
		} else {
			return false;
		}
	}
}
