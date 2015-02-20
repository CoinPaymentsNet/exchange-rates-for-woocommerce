<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Plugin Name: CoinPayments.net Exchange Rates for WordPress
 * Plugin URI: https://www.coinpayments.net/
 * Description:  Provides access to CoinPayments.net Exchange Rates.
 * Author: CoinPayments.net
 * Author URI: https://www.coinpayments.net/
 * Version: 1.0.0
 */
 
$coinpayments_wp_rates_public_key = '';
$coinpayments_wp_rates_private_key = '';
// Make sure you include the currency you have items listed in in this array (USD, EUR, etc.)
$coinpayments_wp_rates_wanted_coins = array('USD','BTC','LTC','DOGE');
 
function coinpayments_api_call($cmd, $req = array()) {
    global $coinpayments_wp_rates_public_key, $coinpayments_wp_rates_private_key;
    
    // Set the API command and required fields
    $req['version'] = 1;
    $req['cmd'] = $cmd;
    $req['key'] = $coinpayments_wp_rates_public_key;
    $req['format'] = 'json'; //supported values are json and xml
    
    // Generate the query string
    $post_data = http_build_query($req, '', '&');
    
    // Calculate the HMAC signature on the POST data
    $hmac = hash_hmac('sha512', $post_data, $coinpayments_wp_rates_private_key);
    
    // Create cURL handle and initialize (if needed)
    static $ch = NULL;
    if ($ch === NULL) {
        $ch = curl_init('https://www.coinpayments.net/api.php');
        curl_setopt($ch, CURLOPT_FAILONERROR, TRUE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('HMAC: '.$hmac));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    
    // Execute the call and close cURL handle     
    $data = curl_exec($ch);                
    // Parse and return data if successful.
    if ($data !== FALSE) {
        if (PHP_INT_SIZE < 8 && version_compare(PHP_VERSION, '5.4.0') >= 0) {
            // We are on 32-bit PHP, so use the bigint as string option. If you are using any API calls with Satoshis it is highly NOT recommended to use 32-bit PHP
            $dec = json_decode($data, TRUE, 512, JSON_BIGINT_AS_STRING);
        } else {
            $dec = json_decode($data, TRUE);
        }
        if ($dec !== NULL && count($dec)) {
            return $dec;
        } else {
            // If you are using PHP 5.5.0 or higher you can use json_last_error_msg() for a better error message
            return array('error' => 'Unable to parse JSON result ('.json_last_error().')');
        }
    } else {
        return array('error' => 'cURL error: '.curl_error($ch));
    }
}

function coinpayments_wp_rates_update() {
	global $wpdb,$coinpayments_wp_rates_wanted_coins;
		
	$wpdb->query('CREATE TABLE IF NOT EXISTS '.$wpdb->prefix.'coinpayments_exchange_rates (`Coin` VARCHAR(8) PRIMARY KEY, `Rate` DECIMAL (40,24), `LastUpdate` INT DEFAULT 0)');

	$arr = coinpayments_api_call('rates', array('short' => '1'));
	if ($arr === FALSE || $arr['error'] != 'ok') {
		return FALSE;
	}
	if (!is_array($arr['result']) || count($arr['result']) == 0) {
		return FALSE;
	}
	foreach ($arr['result'] as $coin => $tmp) {
		if (in_array($coin, $coinpayments_wp_rates_wanted_coins)) {
			$insert = array(
				'Coin' => $coin,
				'Rate' => $tmp['rate_btc'],
				'LastUpdate' => $tmp['last_update'],
			);
			$wpdb->replace($wpdb->prefix.'coinpayments_exchange_rates', $insert);
		}
	}
	return TRUE;
}

$coinpayments_wp_rates_checked_update = FALSE;
function coinpayments_wp_check_update() {
	global $coinpayments_wp_rates_checked_update;
	if (!$coinpayments_wp_rates_checked_update) {
		$last_update = get_option('coinpayments_rates_updated');
		if ($last_update === FALSE) {
			add_option('coinpayments_rates_updated', strval(time()), '', 'yes');
		} else {
			update_option('coinpayments_rates_updated', strval(time()));
		}
		$coinpayments_wp_rates_checked_update = TRUE;
		$last_update = intval($last_update);
		if ((time() - $last_update) >= 900) {
			coinpayments_wp_rates_update();
		}
	}
}
   
function coinpayments_wp_rates_dropdown($price, $currency) {
	global $wpdb,$coinpayments_wp_rates_wanted_coins;
	coinpayments_wp_check_update();
	
	$results = $wpdb->get_results('SELECT * FROM '.$wpdb->prefix.'coinpayments_exchange_rates', ARRAY_A );
	$rates = array();
	foreach($results as $tmp) {
		$rates[$tmp['Coin']] = $tmp;
	}
	unset($results);
	
	if (!isset($rates[$currency])) {
		return 'Could not find exchange rate for '.$currency.'!';
	}
	$btc_value = $rates[$currency]['Rate'] * $price;
	
	$ret = '<select style="text-align:right;">';
	foreach ($rates as $coin => $tmp) {
		if (in_array($coin, $coinpayments_wp_rates_wanted_coins) && $coin != $currency) {
			$value = round($btc_value * (1/$tmp['Rate']), 6);
			$ret .= '<option style="text-align:right;">'.sprintf('%.06f %s', $value, $coin).'</option>';
		}
	}
	$ret .= '</select>';
	return $ret;
}

function coinpayments_wp_rates_list($price, $currency) {
	global $wpdb,$coinpayments_wp_rates_wanted_coins;
	coinpayments_wp_check_update();
	
	$results = $wpdb->get_results('SELECT * FROM '.$wpdb->prefix.'coinpayments_exchange_rates', ARRAY_A );
	$rates = array();
	foreach($results as $tmp) {
		$rates[$tmp['Coin']] = $tmp;
	}
	unset($results);
	
	if (!isset($rates[$currency])) {
		return 'Could not find exchange rate for '.$currency.'!';
	}
	$btc_value = $rates[$currency]['Rate'] * $price;
	
	$ret = 'Approximate Cryptocurrency Prices:<br />';
	foreach ($rates as $coin => $tmp) {
		if (in_array($coin, $coinpayments_wp_rates_wanted_coins) && $coin != $currency) {
			$value = round($btc_value * (1/$tmp['Rate']), 6);
			$ret .= '&middot; '.sprintf('%s: %.06f', $coin, $value).'<br />';
		}
	}
	return $ret;
}
