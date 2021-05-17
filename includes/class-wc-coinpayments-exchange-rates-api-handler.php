<?php
/**
 * Class WC_Coinpayments_Exchange_Rates_API_Handler file.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles Refunds and other API requests such as capture.
 *
 * @since 3.0.0
 */
class WC_Coinpayments_Exchange_Rates_API_Handler
{

    const API_URL = 'https://api.coinpayments.net';
    const API_VERSION = '1';

    const API_CURRENCIES_ACTION = 'currencies';
    const API_MERCHANT_CURRENCIES_ACTION = 'merchant/currencies';
    const API_RATES_ACTION = 'rates';


    const FIAT_TYPE = 'fiat';
    const TOKEN_TYPE = 'token';

    /**
     * @var string
     */
    protected $client_id;

    /**
     * @var string
     */
    protected $client_secret;


    /**
     * WC_Coinpayments_Exchange_Rates_API_Handler constructor.
     * @param $client_id
     * @param $client_secret
     */
    public function __construct($client_id, $client_secret)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
    }


    /**
     * @return bool|mixed
     * @throws Exception
     */
    public function get_accepted_currencies()
    {
        return $this->send_request('GET', self::API_MERCHANT_CURRENCIES_ACTION, $this->client_id, null, $this->client_secret);
    }

    /**
     * @param $currency_list
     * @param $default_currency
     * @return bool|mixed
     * @throws Exception
     */
    public function get_currencies_rates($currency_list, $default_currency)
    {
        $params = array(
            'from' => $default_currency,
            'to' => implode(',', array_map(function ($currency) {
                return $currency['currency']['id'];
            }, $currency_list)),
        );

        return $this->send_request('GET', self::API_RATES_ACTION, false, $params);
    }

    /**
     * @param $name
     * @return mixed
     * @throws Exception
     */
    public function get_coin_currency($name)
    {

        $params = array(
            'q' => $name,
        );
        $items = array();

        $listData = $this->get_coin_currencies($params);
        if (!empty($listData['items'])) {
            $items = array_filter($listData['items'], function ($currency) use ($name) {
                return $currency['symbol'] == $name;
            });
        }

        return array_shift($items);
    }

    /**
     * @param array $params
     * @return bool|mixed
     * @throws Exception
     */
    public function get_coin_currencies($params = array())
    {
        return $this->send_request('GET', self::API_CURRENCIES_ACTION, false, $params);
    }

    /**
     * @param $signature
     * @param $content
     * @param $event
     * @return bool
     */
    public function check_data_signature($signature, $content, $event)
    {

        $request_url = $this->get_notification_url($event);
        $signature_string = sprintf('%s%s', $request_url, $content);
        $encoded_pure = $this->encode_signature_string($signature_string, $this->client_secret);
        return $signature == $encoded_pure;
    }

    /**
     * @param $signature_string
     * @param $client_secret
     * @return string
     */
    public function encode_signature_string($signature_string, $client_secret)
    {
        return base64_encode(hash_hmac('sha256', $signature_string, $client_secret, true));
    }

    /**
     * @param $action
     * @return string
     */
    public function get_api_url($action)
    {
        return sprintf('%s/api/v%s/%s', self::API_URL, self::API_VERSION, $action);
    }

    /**
     * @param $method
     * @param $api_action
     * @param $client_id
     * @param null $params
     * @param null $client_secret
     * @return bool|mixed
     * @throws Exception
     */
    protected function send_request($method, $api_action, $client_id, $params = null, $client_secret = null)
    {

        $response = false;

        $api_url = $this->get_api_url($api_action);
        $date = new \Datetime();
        try {

            $curl = curl_init();

            $options = array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
            );

            $headers = array(
                'Content-Type: application/json',
            );

            if ($client_secret) {
                $signature = $this->create_signature($method, $api_url, $client_id, $date, $client_secret, $params);
                $headers[] = 'X-CoinPayments-Client: ' . $client_id;
                $headers[] = 'X-CoinPayments-Timestamp: ' . $date->format('c');
                $headers[] = 'X-CoinPayments-Signature: ' . $signature;

            }

            $options[CURLOPT_HTTPHEADER] = $headers;
            $options[CURLOPT_HEADER] = true;

            if ($method == 'POST') {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = json_encode($params);
            } elseif ($method == 'GET' && !empty($params)) {
                $api_url .= '?' . http_build_query($params);
            }

            $options[CURLOPT_URL] = $api_url;

            curl_setopt_array($curl, $options);

            $result = curl_exec($curl);

            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);

            $body = substr($result, $headerSize);

            if (substr($http_code, 0, 1) == 2) {
                $response = json_decode($body, true);
            } elseif (curl_error($curl)) {
                throw new Exception($body, $http_code);
            } elseif ($http_code == 400) {
                throw new Exception($body, 400);
            } elseif (substr($http_code, 0, 1) == 4) {
                throw new Exception(__('CoinPayments.NET authentication failed!', 'coinpayments-exchange-rates'), $http_code);
            }
            curl_close($curl);

        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
        return $response;
    }

    /**
     * @param $method
     * @param $api_url
     * @param $client_id
     * @param $date
     * @param $client_secret
     * @param $params
     * @return string
     */
    protected function create_signature($method, $api_url, $client_id, $date, $client_secret, $params)
    {

        if (!empty($params)) {
            $params = json_encode($params);
        }

        $signature_data = array(
            chr(239),
            chr(187),
            chr(191),
            $method,
            $api_url,
            $client_id,
            $date->format('c'),
            $params
        );

        $signature_string = implode('', $signature_data);

        return $this->encode_signature_string($signature_string, $client_secret);
    }

}
