<?php

/*
 * Plugin Name: WooCommerce - Enot Payment Gateway ENGLISH
 * Description: Adds to WooCommerce the ability to accept payments through the Enot payment system
 * Author URI:  https://enot.io/
 * Author: ISPlicense
 * Version: 1.0
*/

define('THIS_PLUGIN_DIR', dirname(__FILE__));

add_action('plugins_loaded', 'init_woo_enot', 0);

function init_woo_enot() {

	if (!class_exists('WC_Payment_Gateway')) return;

	class WC_Gateway_Enot extends WC_Payment_Gateway {
		
		function __construct() {

			global $woocommerce;

			$plugin_dir = plugin_dir_url(__FILE__);

			$this->id = 'enot';
			$this->icon = apply_filters('woocommerce_enot_icon', $plugin_dir . 'creditcardlogo.png');
			$this->method_title = 'Enot';
			$this->method_description = 'Adds to WooCommerce the ability to accept payments through the Enot payment system';

			$this->init_form_fields();
			$this->init_settings();

			$this->title = $this->get_option('title');

			if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=') ) {
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
			} else {
				add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
			}

			add_action('woocommerce_api_wc_gateway_enot', array(&$this, 'callback'));
		}

		function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title' => __('Enable/Disable', 'woocommerce'),
					'type' => 'checkbox',
					'default' => 'yes',
				),
				'title' => array(
					'title' => __('Description', 'woocommerce'),
					'type' => 'textarea',
					'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
					'default' => 'Enot',
					'desc_tip' => true,
				),
				'merchant_id' => array(
					'title' => 'Store ID',
					'type' => 'text',
				),
				'secret_word' => array(
					'title' => 'Secret Password',
					'type' => 'password',
				),
				'secret_word2' => array(
					'title' => 'Additional Key',
					'type' => 'text',
				),
			);
		}
	
		function process_payment($order_id) {
			global $woocommerce;

			$order = new WC_Order($order_id);

			$amount = $order->get_total();
			$amount = str_replace(',', '.', $amount);
			$amount = number_format($amount, 2, '.', '');

			$url = 'https://enot.io/_/pay?';

			$merchant_id = $this->get_option("merchant_id");
			$secret_word = $this->get_option("secret_word");

			$description = get_bloginfo('name').' – Заказ #'.$order_id;

			$currency = get_woocommerce_currency();

			$params = array(
				'm' => $merchant_id,
				'oa' => $amount,
				'cr' => $currency,
				'o' => $order_id,
				'c' => $description,
				'success_url' => $order->get_checkout_order_received_url(),
				's' => md5($merchant_id.':'.$amount.':'.$secret_word.':'.$order_id),
			);

			$url .= http_build_query($params, null, '&');

			$woocommerce->cart->empty_cart();

			return array('result' => 'success', 'redirect' => $url);
		}

		public function callback() {
			$order_id = (int) $_POST['merchant_id'];
			if (empty($order_id)) exit;

			$order = new WC_Order($order_id);

			$amount = $order->get_total();
			$amount = str_replace(',', '.', $amount);
			$amount = number_format($amount, 2, '.', '');

			$merchant_id = $this->get_option("merchant_id");
			$secret_word2 = $this->get_option("secret_word2");

			$sign2 = $_POST['sign_2'];

			$check_sign2 = md5($merchant_id.':'.$amount.':'.$secret_word2.':'.$order_id);

			if (hash_equals($sign2, $check_sign2)) {
				$order->payment_complete();
			}
			exit;
		}
	}
}

function add_woo_enot($methods) {
	$methods[] = 'WC_Gateway_Enot'; 
	return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_woo_enot');