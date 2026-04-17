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
            'payment_mode' => 'direct',
            'validate_only' => 1,
            'allow_real_recharge' => 0,
            'wizard_enabled' => 0,
            'wizard_max_offers_per_category' => 6,
            'wizard_checkout_mapping_mode' => 'both',
            'wizard_checkout_beneficiary_meta_key' => '_dc_beneficiary_phone',
            'wizard_transfer_retry_attempts' => 2,
            'wizard_transfer_retry_delay_minutes' => 15,
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

    public function get_products_by_country($country_iso, $take = 250) {
        $country_iso = strtoupper((string) $country_iso);
        $cache_key = 'dc_products_country_' . md5($country_iso . '_' . $take);
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        $query = array_merge(
            $this->build_array_query('CountryIsos', [$country_iso]),
            ['Take' => (int) $take]
        );

        $result = $this->request('GET', 'GetProducts', $query);

        if (!is_wp_error($result)) {
            set_transient($cache_key, $result, 10 * MINUTE_IN_SECONDS);
        }

        return $result;
    }

    public function get_providers_by_codes($provider_codes = []) {
        $provider_codes = array_values(array_unique(array_filter(array_map('strval', (array) $provider_codes))));
        if (empty($provider_codes)) {
            return ['Result' => []];
        }

        $cache_key = 'dc_providers_codes_' . md5(wp_json_encode($provider_codes));
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        $result = $this->request('GET', 'GetProviders', $this->build_array_query('ProviderCodes', $provider_codes));

        if (!is_wp_error($result)) {
            set_transient($cache_key, $result, 10 * MINUTE_IN_SECONDS);
        }

        return $result;
    }

    public function get_providers_by_country($country_iso) {
        $country_iso = strtoupper((string) $country_iso);
        if (empty($country_iso)) {
            return ['Result' => []];
        }

        $cache_key = 'dc_providers_country_' . md5($country_iso);
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        $result = $this->request('GET', 'GetProviders', $this->build_array_query('CountryIsos', [$country_iso]));

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

    public function get_balance() {
        return $this->request('GET', 'GetBalance');
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
            $friendly_message = 'DingConnect respondió con error HTTP.';
            $error_code = '';
            $error_context = '';

            $transfer_record = (is_array($data) && isset($data['TransferRecord']) && is_array($data['TransferRecord']))
                ? $data['TransferRecord']
                : [];
            $transfer_ref = sanitize_text_field($transfer_record['TransferId']['TransferRef'] ?? '');
            $distributor_ref = sanitize_text_field($transfer_record['TransferId']['DistributorRef'] ?? '');
            $processing_state = sanitize_text_field($transfer_record['ProcessingState'] ?? '');

            if (is_array($data) && !empty($data['ErrorCodes']) && is_array($data['ErrorCodes'])) {
                $first_error = $data['ErrorCodes'][0] ?? [];
                $error_code = sanitize_text_field($first_error['Code'] ?? '');
                $error_context = sanitize_text_field($first_error['Context'] ?? '');

                switch ($error_code) {
                    case 'InsufficientBalance':
                        $friendly_message = 'Saldo insuficiente en DingConnect. Recarga balance del agente para continuar.';
                        break;
                    case 'AccountNumberInvalid':
                        $friendly_message = 'El número de destino no es válido para este producto.';
                        break;
                    case 'RateLimited':
                        $friendly_message = 'DingConnect limitó temporalmente la operación. Intenta de nuevo en unos segundos.';
                        break;
                    case 'RechargeNotAllowed':
                        $friendly_message = 'La recarga no está permitida para esta cuenta o producto.';
                        break;
                    case 'ProviderError':
                        if ('ProviderUnknownError' === $error_context) {
                            $friendly_message = 'El proveedor rechazó temporalmente la recarga. Intenta nuevamente en unos minutos o prueba otro SKU.';
                        } else {
                            $friendly_message = 'El proveedor rechazó la operación para este producto.';
                        }
                        break;
                }
            }

            return new WP_Error(
                'dc_http_error',
                $friendly_message,
                [
                    'status' => $status,
                    'ding_error_code' => $error_code,
                    'ding_error_context' => $error_context,
                    'transfer_ref' => $transfer_ref,
                    'distributor_ref' => $distributor_ref,
                    'processing_state' => $processing_state,
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

    private function build_array_query($name, $values) {
        $query = [];

        foreach (array_values(array_filter((array) $values, function ($value) {
            return '' !== (string) $value;
        })) as $index => $value) {
            $query[$name . '[' . $index . ']'] = $value;
        }

        return $query;
    }
}
