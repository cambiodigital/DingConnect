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
            'woo_allowed_gateways' => [],
            'submitted_retry_max_attempts' => 4,
            'submitted_retry_backoff_minutes' => '10,20,40,80',
            'submitted_max_window_hours' => 12,
            'submitted_escalation_email' => '',
            'submitted_non_retryable_codes' => 'InsufficientBalance,AccountNumberInvalid,RechargeNotAllowed',
        ];

        return wp_parse_args(get_option('dc_recargas_options', []), $defaults);
    }

    public function is_configured() {
        $options = $this->get_options();
        return !empty($options['api_key']);
    }

    public function get_countries() {
        $cache_key = 'dc_countries_all';
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        $result = $this->request('GET', 'GetCountries');

        if (!is_wp_error($result)) {
            set_transient($cache_key, $result, DAY_IN_SECONDS);
        }

        return $result;
    }

    public function get_currencies() {
        $cache_key = 'dc_currencies_all';
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        $result = $this->request('GET', 'GetCurrencies');

        if (!is_wp_error($result)) {
            set_transient($cache_key, $result, DAY_IN_SECONDS);
        }

        return $result;
    }

    public function get_regions($country_isos = []) {
        $country_isos = array_values(array_unique(array_filter(array_map(function ($value) {
            return strtoupper((string) $value);
        }, (array) $country_isos))));

        $cache_key = 'dc_regions_' . md5(wp_json_encode($country_isos));
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        $query = !empty($country_isos)
            ? $this->build_array_query('CountryIsos', $country_isos)
            : [];

        $result = $this->request('GET', 'GetRegions', $query);

        if (!is_wp_error($result)) {
            set_transient($cache_key, $result, DAY_IN_SECONDS);
        }

        return $result;
    }

    public function get_products($account_number, $take = 50) {
        return $this->get_products_catalog([
            'account_number' => (string) $account_number,
            'take' => (int) $take,
        ]);
    }

    public function get_products_by_country($country_iso, $take = 250) {
        return $this->get_products_catalog([
            'country_isos' => [strtoupper((string) $country_iso)],
            'take' => (int) $take,
        ]);
    }

    public function get_products_catalog($filters = []) {
        $filters = is_array($filters) ? $filters : [];

        $query = [
            'Take' => max(1, (int) ($filters['take'] ?? 250)),
        ];

        $account_number = sanitize_text_field((string) ($filters['account_number'] ?? ''));
        if ('' !== $account_number) {
            $query['AccountNumber'] = $account_number;
        }

        $array_filters = [
            'CountryIsos' => array_map('strtoupper', (array) ($filters['country_isos'] ?? [])),
            'ProviderCodes' => (array) ($filters['provider_codes'] ?? []),
            'RegionCodes' => (array) ($filters['region_codes'] ?? []),
            'Benefits' => (array) ($filters['benefits'] ?? []),
            'SkuCodes' => (array) ($filters['sku_codes'] ?? []),
        ];

        foreach ($array_filters as $name => $values) {
            $query = array_merge($query, $this->build_array_query($name, $values));
        }

        $cache_key = 'dc_products_catalog_' . md5(wp_json_encode($query));
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

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

    public function get_provider_status($provider_codes = []) {
        $provider_codes = array_values(array_unique(array_filter(array_map('strval', (array) $provider_codes))));
        if (empty($provider_codes)) {
            return ['Result' => []];
        }

        $cache_key = 'dc_provider_status_' . md5(wp_json_encode($provider_codes));
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        $result = $this->request('GET', 'GetProviderStatus', $this->build_array_query('ProviderCodes', $provider_codes));

        if (!is_wp_error($result)) {
            set_transient($cache_key, $result, MINUTE_IN_SECONDS);
        }

        return $result;
    }

    public function get_promotions($filters = [], $take = 10) {
        if (!is_array($filters)) {
            $filters = ['country_isos' => [strtoupper((string) $filters)]];
        }

        $query = [
            'Take' => max(1, (int) ($filters['take'] ?? $take)),
        ];

        $account_number = sanitize_text_field((string) ($filters['account_number'] ?? ''));
        if ('' !== $account_number) {
            $query['AccountNumber'] = $account_number;
        }

        $query = array_merge($query, $this->build_array_query('CountryIsos', array_map('strtoupper', (array) ($filters['country_isos'] ?? []))));
        $query = array_merge($query, $this->build_array_query('ProviderCodes', (array) ($filters['provider_codes'] ?? [])));

        return $this->request('GET', 'GetPromotions', $query);
    }

    public function get_promotion_descriptions($language_codes = []) {
        return $this->request('GET', 'GetPromotionDescriptions', $this->build_array_query('LanguageCodes', (array) $language_codes));
    }

    public function get_product_descriptions($sku_codes = [], $language_codes = []) {
        $params = [];

        $params = array_merge($params, $this->build_array_query('SkuCodes', (array) $sku_codes));
        $params = array_merge($params, $this->build_array_query('LanguageCodes', (array) $language_codes));

        return $this->request('GET', 'GetProductDescriptions', $params);
    }

    public function get_account_lookup($account_number) {
        $account_number = sanitize_text_field((string) $account_number);
        if ('' === $account_number) {
            return ['Result' => []];
        }

        return $this->request('GET', 'GetAccountLookup', [
            'AccountNumber' => $account_number,
        ]);
    }

    public function estimate_prices($items = []) {
        $normalized_items = [];

        foreach ((array) $items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $sku_code = sanitize_text_field((string) ($item['SkuCode'] ?? ''));
            if ('' === $sku_code) {
                continue;
            }

            $payload_item = [
                'SkuCode' => $sku_code,
                'SendValue' => (float) ($item['SendValue'] ?? 0),
                'ReceiveValue' => (float) ($item['ReceiveValue'] ?? 0),
                'BatchItemRef' => sanitize_text_field((string) ($item['BatchItemRef'] ?? ('ITEM-' . ($index + 1)))),
            ];

            $send_currency = sanitize_text_field((string) ($item['SendCurrencyIso'] ?? ''));
            if ('' !== $send_currency) {
                $payload_item['SendCurrencyIso'] = $send_currency;
            }

            $normalized_items[] = $payload_item;
        }

        if (empty($normalized_items)) {
            return new WP_Error('dc_estimate_items_missing', 'Debes indicar al menos un SKU para estimar precios.', ['status' => 400]);
        }

        return $this->request('POST', 'EstimatePrices', [], $normalized_items);
    }

    public function lookup_bills($sku_code, $account_number, $settings = []) {
        $sku_code = sanitize_text_field((string) $sku_code);
        $account_number = sanitize_text_field((string) $account_number);

        if ('' === $sku_code || '' === $account_number) {
            return new WP_Error('dc_lookup_bills_missing_fields', 'SkuCode y AccountNumber son requeridos para LookupBills.', ['status' => 400]);
        }

        $body = [
            'SkuCode' => $sku_code,
            'AccountNumber' => $account_number,
        ];

        $normalized_settings = $this->sanitize_settings($settings);
        if (!empty($normalized_settings)) {
            $body['Settings'] = $normalized_settings;
        }

        return $this->request('POST', 'LookupBills', [], $body);
    }

    public function list_transfer_records($payload = []) {
        $payload = is_array($payload) ? $payload : [];

        $body = [
            'Take' => max(1, (int) ($payload['Take'] ?? $payload['take'] ?? 1)),
        ];

        foreach (['TransferRef', 'DistributorRef', 'AccountNumber'] as $field) {
            $value = sanitize_text_field((string) ($payload[$field] ?? $payload[strtolower($field)] ?? ''));
            if ('' !== $value) {
                $body[$field] = $value;
            }
        }

        $skip = (int) ($payload['Skip'] ?? $payload['skip'] ?? 0);
        if ($skip > 0) {
            $body['Skip'] = $skip;
        }

        return $this->request('POST', 'ListTransferRecords', [], $body);
    }

    public function get_error_code_descriptions() {
        $cache_key = 'dc_error_code_descriptions_all';
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        $result = $this->request('GET', 'GetErrorCodeDescriptions');

        if (!is_wp_error($result)) {
            set_transient($cache_key, $result, DAY_IN_SECONDS);
        }

        return $result;
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

        $send_currency = sanitize_text_field($payload['SendCurrencyIso'] ?? '');

        $body = [
            'DistributorRef' => sanitize_text_field($payload['DistributorRef'] ?? $this->new_ref()),
            'AccountNumber' => sanitize_text_field($payload['AccountNumber'] ?? ''),
            'SkuCode' => sanitize_text_field($payload['SkuCode'] ?? ''),
            'SendValue' => (float) ($payload['SendValue'] ?? 0),
            'ValidateOnly' => $validate_only,
        ];

        if ('' !== $send_currency) {
            $body['SendCurrencyIso'] = $send_currency;
        }

        $settings = $this->sanitize_settings($payload['Settings'] ?? []);
        if (!empty($settings)) {
            $body['Settings'] = $settings;
        }

        $bill_ref = sanitize_text_field((string) ($payload['BillRef'] ?? ''));
        if ('' !== $bill_ref) {
            $body['BillRef'] = $bill_ref;
        }

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
            $transfer_record = (isset($response['TransferRecord']) && is_array($response['TransferRecord']))
                ? $response['TransferRecord']
                : [];

            if (!empty($items[0]['Status'])) {
                $status = sanitize_text_field((string) $items[0]['Status']);
            } elseif (!empty($items[0]['ProcessingState'])) {
                $status = sanitize_text_field((string) $items[0]['ProcessingState']);
            } elseif (!empty($transfer_record['ProcessingState'])) {
                $status = sanitize_text_field((string) $transfer_record['ProcessingState']);
            }

            if (isset($transfer_record['TransferId']) && is_array($transfer_record['TransferId'])) {
                $transfer_ref = sanitize_text_field((string) ($transfer_record['TransferId']['TransferRef'] ?? ''));
            }

            if ('' === $transfer_ref) {
                $transfer_ref = sanitize_text_field((string) ($response['TransferRef'] ?? ''));
            }
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

    private function sanitize_settings($settings) {
        $normalized = [];

        foreach ((array) $settings as $setting) {
            if (!is_array($setting)) {
                continue;
            }

            $name = sanitize_text_field((string) ($setting['Name'] ?? $setting['name'] ?? ''));
            if ('' === $name) {
                continue;
            }

            $normalized[] = [
                'Name' => $name,
                'Value' => sanitize_text_field((string) ($setting['Value'] ?? $setting['value'] ?? '')),
            ];
        }

        return $normalized;
    }
}
