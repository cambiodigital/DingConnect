<?php

if (!defined('ABSPATH')) {
    exit;
}

class DC_Recargas_API {
    public function get_options() {
        $defaults = [
            'api_base' => 'https://www.dingconnect.com/api/V1',
            'api_key' => '',
            'validate_only' => 1,
            'allow_real_recharge' => 0,
        ];

        return wp_parse_args(get_option('dc_recargas_options', []), $defaults);
    }

    public function is_configured() {
        $options = $this->get_options();
        return !empty($options['api_key']);
    }

    public function get_products($account_number, $take = 50) {
        return $this->request('GET', 'GetProducts', [
            'AccountNumber' => $account_number,
            'Take' => (int) $take,
        ]);
    }

    public function get_promotions($country_iso, $take = 10) {
        return $this->request('GET', 'GetPromotions', [
            'CountryIso' => strtoupper($country_iso),
            'Take' => (int) $take,
        ]);
    }

    public function get_product_descriptions($sku_codes = []) {
        $params = [];
        foreach ($sku_codes as $index => $sku) {
            $params['SkuCodes[' . $index . ']'] = $sku;
        }

        return $this->request('GET', 'GetProductDescriptions', $params);
    }

    public function send_transfer($payload) {
        $options = $this->get_options();
        $validate_only = !empty($options['validate_only']);

        if (!empty($options['allow_real_recharge']) && isset($payload['ValidateOnly'])) {
            $validate_only = (bool) $payload['ValidateOnly'];
        }

        $body = [
            'DistributorRef' => sanitize_text_field($payload['DistributorRef'] ?? $this->new_ref()),
            'AccountNumber' => sanitize_text_field($payload['AccountNumber'] ?? ''),
            'SkuCode' => sanitize_text_field($payload['SkuCode'] ?? ''),
            'SendValue' => (float) ($payload['SendValue'] ?? 0),
            'SendCurrencyIso' => sanitize_text_field($payload['SendCurrencyIso'] ?? 'USD'),
            'ValidateOnly' => $validate_only,
        ];

        return $this->request('POST', 'SendTransfer', [], $body);
    }

    public function new_ref() {
        return 'WP-' . gmdate('YmdHis') . '-' . strtoupper(wp_generate_password(6, false, false));
    }

    private function request($method, $path, $query = [], $body = null) {
        $options = $this->get_options();
        $api_key = trim((string) $options['api_key']);

        if (empty($api_key)) {
            return new WP_Error('dc_missing_api_key', 'No has configurado el API Key de DingConnect.');
        }

        $base = untrailingslashit((string) $options['api_base']);
        $url = $base . '/' . ltrim($path, '/');

        if (!empty($query)) {
            $url = add_query_arg($query, $url);
        }

        $args = [
            'method' => strtoupper($method),
            'timeout' => 30,
            'headers' => [
                'api_key' => $api_key,
                'Content-Type' => 'application/json',
            ],
        ];

        if (null !== $body) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $data = json_decode($raw_body, true);

        if ($status < 200 || $status >= 300) {
            return new WP_Error(
                'dc_http_error',
                'DingConnect respondió con error HTTP.',
                [
                    'status' => $status,
                    'body' => $data ?: $raw_body,
                ]
            );
        }

        return is_array($data) ? $data : ['raw' => $raw_body];
    }
}
