<?php
/**
 * Plugin Name:     BinancePay for WooCommerce
 * Plugin URI:      https://wordpress.org/plugins/binancepay-for-woocommerce/
 * Description:     Adds a BinancePay payment gateway for WooCommerce so users can pay with their preferred cryptocurrency.
 * Author:          ndeet, Coincards
 * Author URI:      https://coincards.com
 * Text Domain:     binancepay-for-woocommerce
 * Domain Path:     /languages
 * Version:         0.1.0
 * Requires PHP:    7.4
 * Tested up to:    6.0
 * Requires at least: 5.2
 */

use BinancePay\WC\Client\BinanceOrder;
use BinancePay\WC\Helper\Logger;

defined( 'ABSPATH' ) || exit();

define( 'BINANCEPAY_VERSION', '0.1.0' );
define( 'BINANCEPAY_PLUGIN_FILE_PATH', plugin_dir_path( __FILE__ ) );
define( 'BINANCEPAY_PLUGIN_URL', plugin_dir_url(__FILE__ ) );
define( 'BINANCEPAY_PLUGIN_ID', 'binancepay-for-woocommerce' );

class BinancePayWCPlugin {

	private static $instance;

	public function __construct() {
		$this->includes();

		add_action('woocommerce_thankyou_binancepay', [$this, 'orderStatusThankYouPage'], 10, 1);

		if (is_admin()) {
			$this->dependenciesNotification();
			$this->notConfiguredNotification();
		}
	}

	public function includes(): void {
		$autoloader = BINANCEPAY_PLUGIN_FILE_PATH . 'vendor/autoload.php';
		if (file_exists($autoloader)) {
			/** @noinspection PhpIncludeInspection */
			require_once $autoloader;
		}

		// Make sure WP internal functions are available.
		if ( ! function_exists('is_plugin_active') ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	public static function initPaymentGateway($gateways): array {
		// Add BinancePay gateway to WooCommerce.
		$gateways[] = \BinancePay\WC\Gateway\BinancePayGateway::class;

		return $gateways;
	}

	/**
	 * Displays notice (and link to config page) on admin dashboard if the plugin is not configured yet.
	 */
	public function notConfiguredNotification(): void {
		/*
		if (!\BinancePay\WC\Helper\GreenfieldApiHelper::getConfig()) {
			$message = sprintf(
				esc_html__(
					'Plugin not configured yet, please %1$sconfigure the plugin here%2$s',
					'binancepay-for-woocommerce'
				),
				'<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=binancepay_settings')) . '">',
				'</a>'
			);

			\BinancePay\WC\Admin\Notice::addNotice('error', $message);
		}
		*/
	}

	/**
	 * Checks and displays notice on admin dashboard if PHP version is too low or WooCommerce not installed.
	 */
	public function dependenciesNotification() {
		// Check PHP version.
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			$versionMessage = sprintf( __( 'Your PHP version is %s but BinancePay Greenfield Payment plugin requires version 7.4+.', 'binancepay-for-woocommerce' ), PHP_VERSION );
			\BinancePay\WC\Admin\Notice::addNotice('error', $versionMessage);
		}

		// Check if WooCommerce is installed.
		if ( ! is_plugin_active('woocommerce/woocommerce.php') ) {
			$wcMessage = __('WooCommerce seems to be not installed. Make sure you do before you activate BinancePay Payment Gateway.', 'binancepay-for-woocommerce');
			\BinancePay\WC\Admin\Notice::addNotice('error', $wcMessage);
		}

	}

	public function updateOrderFields(\WC_Order &$order, array $fields) {
		// todo list of specific stuff to update.
		foreach ($fields as $field) {
			$method = 'set_' . $field['name'];
			if (method_exists($order, $method)) {
				$order->$method(wc_clean(wp_unslash($field['value'])));
			}
		}
	}

	/**
	 * The payment received page is used as the callbackUrl of BinancePay to check the PaymentRequest status and update
	 * the order. The PaymentRequest check is done on each visit of that page but stops if the payment failed or succeeded.
	 */
	public function orderStatusThankYouPage($order_id)
	{
		Logger::debug('Running BinancePayWCPlugin::orderStatusThankYouPage().');

		if (!$order = wc_get_order($order_id)) {
			return;
		}

		$currentOrderStatus = $order->get_status();
		$prepayId = $order->get_meta('BinancePay_prepayId');

		if (
			!in_array($currentOrderStatus, ['processing', 'completed', 'failed']) &&
			$prepayId
		) {
			// Check Binance Order status.
			try {
				$gatewayConfig = get_option('woocommerce_binancepay_settings');

				$client = new BinanceOrder( $gatewayConfig['url'], $gatewayConfig['apikey'], $gatewayConfig['apisecret']);
				$binanceOrder = $client->queryOrder($prepayId);

				Logger::debug('Fetched binance order: ' . print_r($binanceOrder, true));

				if (isset($binanceOrder['data']['status'])) {
					switch ( $binanceOrder['data']['status'] ) {
						case "PAID":
							$order->payment_complete();
							$order->add_meta_data('BinancePay_transactionId', $binanceOrder['data']['transactionId']);
							$order->add_meta_data('BinancePay_transactTime', $binanceOrder['data']['transactTime']);
							$order->add_meta_data('BinancePay_orderAmount', $binanceOrder['data']['orderAmount']);
							$order->add_meta_data('BinancePay_merchantTradeNo', $binanceOrder['data']['merchantTradeNo']);
							$order->add_meta_data('BinancePay_status', $binanceOrder['data']['status']);
							$order->add_meta_data('BinancePay_openUserId', $binanceOrder['data']['openUserId']);
							$order->save();
							Logger::debug('Marked order as complete, BinanceOrder status: PAID');
							break;
						case "CANCELED":
						case "EXPIRED":
						case "ERROR":
							$order->update_status( 'failed' );
							$order->add_order_note( _x( 'Payment failed, please make a new order or get in contact with us.', 'binancepay-for-woocommerce' ), 1 );
							$order->add_meta_data('BinancePay_status', $binanceOrder['data']['status']);
							$order->save();
							Logger::debug('Marked order as failed, BinanceOrder status: ' . $binanceOrder['data']['status']);
							break;
						case "INITIAL":
						case "PENDING":
							// Do nothing, keeps order in pending state.
						default:
							// Do nothing.
					}
				}

			} catch ( \Throwable $e ) {
				Logger::debug('Problem fetching PaymentRequest status:', true);
				Logger::debug( $e->getMessage(), true );
			}
		}

		$newStatus = $order->get_status();

		switch ($newStatus)
		{
			case 'on-hold':
			case 'pending':
				$statusDesc = _x('Waiting for payment settlement', 'binancepay-for-woocommerce');
				break;
			case 'processing':
				$statusDesc = _x('Payment completed, processing your order.', 'binancepay-for-woocommerce');
				break;
			case 'completed':
				$statusDesc = _x('Payment completed', 'binancepay-for-woocommerce');
				break;
			case 'failed':
				$statusDesc = _x('Payment failed', 'binancepay-for-woocommerce');
				break;
			default:
				$statusDesc = _x(ucfirst($newStatus), 'binancepay-for-woocommerce');
				break;
		}

		$title = _x('Payment Status', 'binancepay-for-woocommerce');

		echo "
		<section class='woocommerce-order-payment-status'>
		    <h2 class='woocommerce-order-payment-status-title'>{$title}</h2>
		    <p><strong>{$statusDesc}</strong></p>
		</section>
		";
	}

	/**
	 * Gets the main plugin loader instance.
	 *
	 * Ensures only one instance can be loaded.
	 *
	 * @return \BinancePayWCPlugin
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

// Start everything up.
function init_binancepay() {
	\BinancePayWCPlugin::instance();
}

/**
 * Bootstrap stuff on init.
 */
add_action('init', function() {
	// Adding textdomain and translation support.
	load_plugin_textdomain('binancepay-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');
});

// Initialize payment gateways and plugin.
add_filter( 'woocommerce_payment_gateways', [ 'BinancePayWCPlugin', 'initPaymentGateway' ] );
add_action( 'plugins_loaded', 'init_binancepay', 0 );
