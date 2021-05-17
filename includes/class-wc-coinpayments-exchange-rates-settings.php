<?php

class WC_Coinpayments_Exchange_Rates_Plugin_Settings
{

    private $options;


    public function __construct($plugin)
    {

        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_settings_page'));


        add_filter("plugin_action_links_$plugin", array($this, 'add_settings_link'));

    }

    public function validate($credentials)
    {
        global $wpdb;

        $error = false;
        if (!empty($credentials['client_id']) && !empty($credentials['client_secret'])) {
            $coinpayments = new WC_Coinpayments_Exchange_Rates_API_Handler($credentials['client_id'], $credentials['client_secret']);
            try {

                $accepted_currencies = $coinpayments->get_accepted_currencies();
                if (!isset($accepted_currencies['items'])) {
                    $error = true;
                } else {
                    $wpdb->query('CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'coinpayments_exchange_rates (`id` int auto_increment
        primary key,`from_code` VARCHAR(8), `to_code` VARCHAR(8),`rate` DECIMAL (40,30), `decimal_place` tinyint(16)   not null, status bool null)');
                    $wpdb->query('create index coinpayments_exchange_rates_from_code_index ' . $wpdb->prefix . 'coinpayments_exchange_rates (from_code);');
                }
            } catch (Exception $e) {
                $error = true;
            }
        } else {
            $error = true;
        }

        if ($error) {
            add_settings_error('coinpayments-exchange-rates', 'coinpayments-exchange-rates', __('CoinPayments.NET credentials is not valid!', 'coinpayments-exchange-rates'));
        }


        return $credentials;
    }

    public function register_settings()
    {

        register_setting('coinpayments-exchange-rates', 'coinpayments_exchange_rates_options', array($this, 'validate'));

        add_settings_section(
            'coinpayments_exchange_rates_section',
            'Coinpayments.NET Exchange Rate Setting',
            function () {
                return '';
            },
            'coinpayments-exchange-rates'
        );

        add_settings_field(
            'client_id',
            __('Client ID', 'coinpayments-exchange-rates'),
            function () {
                printf(
                    '<input type="text" id="client_id" name="coinpayments_exchange_rates_options[client_id]" value="%s" />',
                    isset($this->options['client_id']) ? esc_attr($this->options['client_id']) : ''
                );
            },
            'coinpayments-exchange-rates',
            'coinpayments_exchange_rates_section'
        );

        add_settings_field(
            'client_secret',
            __('Client Secret', 'coinpayments-exchange-rates'),
            function () {
                printf(
                    '<input type="text" id="client_secret" name="coinpayments_exchange_rates_options[client_secret]" value="%s" />',
                    isset($this->options['client_secret']) ? esc_attr($this->options['client_secret']) : ''
                );
            },
            'coinpayments-exchange-rates',
            'coinpayments_exchange_rates_section'
        );

    }

    public function add_settings_page()
    {
        add_submenu_page('woocommerce', __('WooCommerce CoinPayments.net Exchange Rates setting', 'coinpayments-exchange-rates'), __('CoinPayments.net Currency Rates', 'coinpayments-exchange-rates'), 'manage_options', 'coinpayments-exchange-rates', array($this, 'render_plugin_settings_page'));
    }

    public function add_settings_link($links)
    {
        $settings_link = '<a href="options-general.php?page=coinpayments-exchange-rates">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function render_plugin_settings_page()
    {

        $this->options = get_option('coinpayments_exchange_rates_options');
        ?>
        <?php settings_errors(); ?>
        <div class="wrap">
            <h1>Coinpayments.NET exchange rates</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('coinpayments-exchange-rates');
                do_settings_sections('coinpayments-exchange-rates');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}