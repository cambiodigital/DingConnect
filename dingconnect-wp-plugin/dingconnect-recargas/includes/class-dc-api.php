<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('DC_Recargas_API')) {
    return;
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
        $cache_key = 'dc_products_' . md5($account_number . '_' . $take);
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        $result = $this->request('GET', 'GetProducts', [
            'AccountNumber' => $account_number,
            'Take' => (int) $take,
        ]);

        if (!is_wp_error($result)) {
            set_transient($cache_key, $result, 10 * MINUTE_IN_SECONDS);
        }

        return $result;
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

    public static function register_transfer_log_cpt() {
        register_post_type('dc_transfer_log', [
            'labels' => [
                'name' => 'Transfer Logs',
                'singular_name' => 'Transfer Log',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'dingconnect-recargas',
            'supports' => ['title'],
            'capability_type' => 'post',
            'capabilities' => [
                'create_posts' => 'do_not_allow',
            ],
            'map_meta_cap' => true,
        ]);
    }

    public function log_transfer($account_number, $sku_code, $send_value, $currency, $distributor_ref, $response) {
        $status = 'unknown';
        $transfer_ref = '';

        if (is_wp_error($response)) {
            $status = 'error';
        } elseif (is_array($response)) {
            $items = $response['Items'] ?? $response['Result'] ?? [];
            if (!empty($items[0]['Status'])) {
                $status = sanitize_text_field($items[0]['Status']);
            }
            $transfer_ref = sanitize_text_field($response['TransferRef'] ?? '');
        }

        // Mask phone for privacy: +573001234567 -> +5730***4567
        $masked = strlen($account_number) > 7
            ? substr($account_number, 0, 4) . '***' . substr($account_number, -4)
            : $account_number;

        $post_id = wp_insert_post([
            'post_type' => 'dc_transfer_log',
            'post_title' => $masked . ' — ' . $sku_code . ' — ' . $status,
            'post_status' => 'publish',
        ]);

        if ($post_id && !is_wp_error($post_id)) {
            update_post_meta($post_id, '_dc_account_number', $masked);
            update_post_meta($post_id, '_dc_sku_code', $sku_code);
            update_post_meta($post_id, '_dc_send_value', $send_value);
            update_post_meta($post_id, '_dc_currency', $currency);
            update_post_meta($post_id, '_dc_distributor_ref', $distributor_ref);
            update_post_meta($post_id, '_dc_transfer_ref', $transfer_ref);
            update_post_meta($post_id, '_dc_status', $status);
            update_post_meta($post_id, '_dc_raw_response', wp_json_encode($response));
        }

        return $post_id;
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

        // Normalize DingConnect response: Items -> Result
        if (is_array($data) && isset($data['Items']) && !isset($data['Result'])) {
            $data['Result'] = $data['Items'];
        }

        return is_array($data) ? $data : ['raw' => $raw_body];
    }
}
