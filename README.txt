CoinPayments.net Cryptocurrency Prices for WooCommerce
Copyright 2014-2015 CoinPayments.net. Licensed under the GNU General Public License version 2.0.

Installation Instructions:

1. Open coinpayments-exchange-rates-for-woocommerce/class-wc-rates-coinpayments.php in your text editor.
2. Near the top, set $coinpayments_wp_rates_public_key and $coinpayments_wp_rates_private_key to one of your CoinPayments API keys with the 'rates' permission.
3. Modify the $coinpayments_wp_rates_wanted_coins array to include the cryptocurrencies you want to display. Make sure to also include the currency your items are listed in; be it USD, EUR, etc.
4. Save the file and close it.

5. Upload the coinpayments-exchange-rates-for-woocommerce and woocommerce folders to your wp-content/plugins directory on your server.
Note: If you aren't using the default WooCommerce theme you may have to make template modifications manually, see the included prices.php template for an example.
Note 2: If you are displaying many currencies, you may want to switch the template to use coinpayments_wp_rates_dropdown instead of coinpayments_wp_rates_list to take up less screen real estate.

6. In your WordPress admin panel, go to the plugins page and Activate 'CoinPayments.net Exchange Rates for WordPress'.

You should see the alternate pricing when you view items now.

