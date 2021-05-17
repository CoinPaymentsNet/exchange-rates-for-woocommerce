<?php
/**
 * Class WC_Coinpayments_Exchange_Rates_Plugin_Template file.
 */

class WC_Coinpayments_Exchange_Rates_Plugin_Template
{

    const OUTPUT_ROWS_SIZE = 10;
    const UPDATE_PERIOD = 600;

    public function __construct()
    {
        add_action('woocommerce_get_price_html', array($this, 'price_block'), 10, 2);
        add_action('woocommerce_single_product_summary', array($this, 'price_block_filter'), 10);
    }

    public function price_block_filter()
    {
        remove_action('woocommerce_get_price_html', array($this, 'price_block'));
    }

    public function price_block($html, $product)
    {

        $prices = $this->get_product_prices($product);

        if (!empty($prices)) {
            $size = count($prices) > self::OUTPUT_ROWS_SIZE ? self::OUTPUT_ROWS_SIZE : count($prices);

            $html = $html . "<br/><div><select size='" . $size . "' multiple style='width: 100%' disabled>";
            foreach ($prices as $price) {
                $html = $html . "<option style='color:#000'>" . $price . "</option>";
            }
            $html = $html . "</select></div>";
        }

        return $html;
    }

    public function get_product_prices($product)
    {

        $product_prices = array();


        $default_currency = get_woocommerce_currency();
        $coin_rates = $this->get_coin_rates($default_currency);

        if (!empty($coin_rates)) {
            foreach ($coin_rates as $coin_rate) {
                $cost = number_format($product->get_price() * $coin_rate['rate'], $coin_rate['decimal_place']);
                $cost = $cost <= 0 ? 1 : $cost; # NEO fix
                $product_prices[$coin_rate['to_code']] = sprintf('%s: %s', $coin_rate['to_code'], $cost);
            }
        }


        return $product_prices;
    }

    public function get_coin_rates($default_currency)
    {


        try {
            $last_update = get_option('coinpayments_exchange_rates_updated');

            if ($last_update === FALSE) {
                add_option('coinpayments_exchange_rates_updated', strval(time()), '', 'yes');
            }

            $last_update = intval($last_update);
            if ((time() - $last_update) >= self::UPDATE_PERIOD) {
                update_option('coinpayments_exchange_rates_updated', strval(time()));
                $coin_rates = array_filter($this->get_updated_coin_rates($default_currency), function ($currency) {
                    return $currency['status'];
                });
            } else {
                $coin_rates = $this->get_db_coin_rates($default_currency);
            }
        } catch (Exception $e) {

        }
        return $coin_rates;
    }

    public function get_db_coin_rates($default_currency)
    {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'coinpayments_exchange_rates WHERE from_code="' . $default_currency . '"  AND status = 1', ARRAY_A);
    }

    public function get_updated_coin_rates($default_currency)
    {
        $credentials = get_option('coinpayments_exchange_rates_options');
        $coinpayments = new WC_Coinpayments_Exchange_Rates_API_Handler($credentials['client_id'], $credentials['client_secret']);
        $currencies_list = $coinpayments->get_accepted_currencies();
        $rates = array();
        if (isset($currencies_list['items']) && !empty($currencies_list['items'])) {
            $currencies_list['items'] = array_filter($currencies_list['items'], function ($currency) {
                return $currency['currency']['type'] != WC_Coinpayments_Exchange_Rates_API_Handler::TOKEN_TYPE;
            });

            $this->update_accepted_currencies($default_currency, $currencies_list['items']);
            $rates = $this->update_accepted_currencies_rates($default_currency, $currencies_list['items']);
        }

        return $rates;
    }

    protected function update_accepted_currencies($default_currency, $currencies_list)
    {
        global $wpdb;

        $symbols = array_map(function ($currency) {
            return $currency['currency']['symbol'];
        }, $currencies_list);


        $results = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'coinpayments_exchange_rates WHERE from_code="' . $default_currency . '" AND  to_code IN ("' . implode('", "', $symbols) . '")', ARRAY_A);
        $exists = array_map(function ($currency) {
            return $currency['to_code'];
        }, $results);

        foreach ($currencies_list as $currency) {
            if (!in_array($currency['currency']['symbol'], $exists)) {
                $this->create_coin_currency_rate($default_currency, $currency);
            }
        }

    }

    private function create_coin_currency_rate($default_currency, $currency)
    {
        global $wpdb;

        $values = array(
            'from_code' => $default_currency,
            'to_code' => $currency['currency']['symbol'],
            'decimal_place' => $currency['currency']['decimalPlaces'],
            'status' => $currency['currency']['status'] == 'active' && $currency['switcherStatus'] == 'enabled',
        );

        $wpdb->query("INSERT INTO " . $wpdb->prefix . "coinpayments_exchange_rates (from_code, to_code, decimal_place, status) VALUES ('" . implode("', '", $values) . "')");
    }

    protected function update_accepted_currencies_rates($default_currency, $currencies_list)
    {
        global $wpdb;

        $credentials = get_option('coinpayments_exchange_rates_options');
        $coinpayments = new WC_Coinpayments_Exchange_Rates_API_Handler($credentials['client_id'], $credentials['client_secret']);
        $coin_default_currency = $coinpayments->get_coin_currency($default_currency);

        $coin_rates = $coinpayments->get_currencies_rates(
            $currencies_list,
            $coin_default_currency['id']
        );


        $coin_currency_ids = array();
        $rates = array();
        foreach ($currencies_list as $currency) {
            $coin_currency_ids[$currency['currency']['id']] = $currency['currency']['symbol'];
            $rates[$currency['currency']['id']] = array(
                'from_code' => $default_currency,
                'to_code' => $currency['currency']['symbol'],
                'decimal_place' => $currency['currency']['decimalPlaces'],
                'status' => $currency['currency']['status'] == 'active' && $currency['switcherStatus'] == 'enabled',
            );
        }

        $rate_conditions = array();
        $status_conditions = array();
        if (!empty($coin_rates['items'])) {
            foreach ($coin_rates['items'] as $rate) {
                $rates[$rate['quoteCurrencyId']]['rate'] = $rate['rate'];
                $rate_conditions[] = sprintf("WHEN '%s' THEN '%s'", $coin_currency_ids[$rate['quoteCurrencyId']], $rate['rate']);
                $status_conditions[] = sprintf("WHEN '%s' THEN '%s'", $coin_currency_ids[$rate['quoteCurrencyId']], $rates[$rate['quoteCurrencyId']]['status']);
            }
        }

        $wpdb->query("
        UPDATE " . $wpdb->prefix . "coinpayments_exchange_rates 
        SET rate = (CASE to_code
        " . implode("\n", $rate_conditions) . "
        END),
        status = (CASE to_code
        " . implode("\n", $status_conditions) . "
        END)
        WHERE from_code = '" . $default_currency . "' AND to_code IN ('" . implode("', '", $coin_currency_ids) . "')");

        return $rates;
    }
}
