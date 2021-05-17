<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Plugin Name: WooCommerce CoinPayments.net Exchange Rates
 * Plugin URI: https://www.coinpayments.net/
 * Description:  Provides a CoinPayments.net Exchange Rates.
 * Author: CoinPayments.net
 * Author URI: https://www.coinpayments.net/
 * Version: 2.0.0
 */

if (function_exists('coinpayments_exchange_load')) {
    $reflFunc = new ReflectionFunction('coinpayments_exchange_load');
    $pluginFile = $reflFunc->getFileName();
    $pluginFileParts = array_slice(explode('/', $pluginFile), -2);
    $pluginPath = implode('/', $pluginFileParts);
    deactivate_plugins(array($pluginPath), true);
} else {
    add_action('plugins_loaded', 'coinpayments_exchange_load', 0);

    function coinpayments_exchange_load()
    {

        class WC_Coinpayments_Exchange_Rates_Plugin
        {

            public function __construct()
            {

                $this->load_textdomain();
                $this->includes();

            }

            public function load_textdomain()
            {
                load_plugin_textdomain('coinpayments-exchange-rates-for-woocommerce', false, plugin_basename(dirname(__FILE__)) . '/i18n/languages');
            }

            public function includes()
            {
                include_once dirname(__FILE__) . '/includes/class-wc-coinpayments-exchange-rates-api-handler.php';
                if (is_admin()) {
                    include_once dirname(__FILE__) . '/includes/class-wc-coinpayments-exchange-rates-settings.php';
                    new WC_Coinpayments_Exchange_Rates_Plugin_Settings(plugin_basename(__FILE__));
                } else {
                    include_once dirname(__FILE__) . '/includes/class-wc-coinpayments-exchange-rates-template.php';
                    new WC_Coinpayments_Exchange_Rates_Plugin_Template(plugin_basename(__FILE__));
                }


            }

        }

        if (class_exists('WC_Payment_Gateway')) {
            new WC_Coinpayments_Exchange_Rates_Plugin();
        }
    }
}
