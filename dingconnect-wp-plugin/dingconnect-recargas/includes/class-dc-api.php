<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('DC_Recargas_API')) {
    return;
}

class DC_Recargas_API {
    private $woocommerce_instance = null;

    public function get_options() {
        $defaults = [
            'api_base' => 'https://www.dingconnect.com/api/V1',
            'api_key' => '',
            'payment_mode' => 'direct',
            'validate_only' => 1,
            'allow_real_recharge' => 0,
            'woo_allowed_gateways' => [],
            'hide_acfw_store_credit_dc_only' => 1,
            'submitted_retry_max_attempts' => 4,
            'submitted_retry_backoff_minutes' => '10,20,40,80',
            'submitted_max_window_hours' => 12,
            'submitted_escalation_email' => '',
            'submitted_non_retryable_codes' => 'InsufficientBalance,AccountNumberInvalid,RechargeNotAllowed,ParameterOutOfRange,SendValue',
            'catalog_csv_path' => '',
            'catalog_csv_url' => '',
            'catalog_csv_uploaded_at' => '',
            'catalog_csv_original_name' => '',
            'webhook_enabled' => 0,
            'webhook_timestamp_tolerance_seconds' => 300,
            'webhook_signature_compat_mode' => 1,
            'woo_dispatch_stage_default' => 'payment_complete',
            'woo_dispatch_stage_by_gateway' => [],
            'voucher_v2_enabled' => 0,
            'voucher_v2_shadow_mode' => 1,
            'voucher_outbox_enabled' => 1,
            'voucher_outbox_max_attempts' => 6,
            'voucher_outbox_backoff_minutes' => '1,2,5,10,20,30',
        ];

        return wp_parse_args(get_option('dc_recargas_options', []), $defaults);
    }

    public function set_woocommerce($woocommerce_instance) {
        $this->woocommerce_instance = $woocommerce_instance;
    }

    public function get_woocommerce() {
        return $this->woocommerce_instance;
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

        // Performance Optimization: Cache promotion responses to reduce API calls
        $cache_key = 'dc_promotions_' . md5(wp_json_encode($query));
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        $result = $this->request('GET', 'GetPromotions', $query);

        if (!is_wp_error($result)) {
            set_transient($cache_key, $result, 10 * MINUTE_IN_SECONDS);
        }

        return $result;
    }

    public function get_promotion_descriptions($language_codes = []) {
        // Performance Optimization: Cache promotion descriptions to reduce API calls
        $cache_key = 'dc_promo_desc_' . md5(wp_json_encode($language_codes));
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        $result = $this->request('GET', 'GetPromotionDescriptions', $this->build_array_query('LanguageCodes', (array) $language_codes));

        if (!is_wp_error($result)) {
            set_transient($cache_key, $result, 10 * MINUTE_IN_SECONDS);
        }

        return $result;
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
        $validate_only = $this->is_effective_validate_only($payload);

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

    public function is_effective_validate_only($payload = []) {
        $options = $this->get_options();
        $validate_only = !empty($options['validate_only']);
        $payload = is_array($payload) ? $payload : [];

        if (!empty($options['allow_real_recharge']) && isset($payload['ValidateOnly'])) {
            $validate_only = (bool) $payload['ValidateOnly'];
        }

        return (bool) $validate_only;
    }

    public function new_ref() {
        return 'WP-' . gmdate('YmdHis') . '-' . strtoupper(wp_generate_password(6, false, false));
    }

    /**
     * Valida que el send_value esté dentro del rango/monto fijo del bundle guardado.
     * Devuelve true si es válido o no hay bundle de referencia; WP_Error si está fuera de rango.
     * Usado tanto en REST (add-to-cart) como en WooCommerce (pre-SendTransfer).
     */
    public function validate_send_value_against_bundle($sku_code, $country_iso, $send_value, $bundle_id = '') {
        $sku_code   = sanitize_text_field((string) $sku_code);
        $country_iso = strtoupper(sanitize_text_field((string) $country_iso));
        $bundle_id  = sanitize_text_field((string) $bundle_id);
        $send_value = (float) $send_value;

        if ('' === $sku_code || $send_value <= 0) {
            return true;
        }

        $bundle = $this->find_bundle_for_amount_validation($sku_code, $country_iso, $bundle_id);
        if (empty($bundle)) {
            return true;
        }

        $options = $this->get_options();
        $manual_amount_mode   = sanitize_key((string) ($options['manual_amount_mode'] ?? 'range_products'));
        $allow_manual_amount  = ($manual_amount_mode === 'range_products');

        $bundle_send_value   = (float) ($bundle['send_value'] ?? 0);
        $min_send_value      = isset($bundle['minimum_send_value']) ? (float) $bundle['minimum_send_value'] : $bundle_send_value;
        $max_send_value      = isset($bundle['maximum_send_value']) ? (float) $bundle['maximum_send_value'] : $bundle_send_value;
        $stored_is_range     = !empty($bundle['is_range']);
        $calculated_is_range = abs($max_send_value - $min_send_value) > 0.00001;
        $bundle_allow_manual = array_key_exists('allow_manual_amount', $bundle)
            ? !empty($bundle['allow_manual_amount'])
            : true;
        $is_range = $allow_manual_amount && $bundle_allow_manual && ($stored_is_range || $calculated_is_range);

        if ($is_range) {
            if ($send_value < ($min_send_value - 0.00001) || $send_value > ($max_send_value + 0.00001)) {
                return new WP_Error(
                    'dc_amount_out_of_range',
                    sprintf(
                        'El importe enviado está fuera del rango permitido para este producto. Permitido: %.2f a %.2f.',
                        $min_send_value,
                        $max_send_value
                    ),
                    [
                        'status'         => 400,
                        'code'           => 'amount_out_of_range',
                        'min_send_value' => $min_send_value,
                        'max_send_value' => $max_send_value,
                        'sku_code'       => $sku_code,
                        'country_iso'    => $country_iso,
                    ]
                );
            }
            return true;
        }

        if ($bundle_send_value > 0 && abs($send_value - $bundle_send_value) > 0.00001) {
            return new WP_Error(
                'dc_amount_fixed_only',
                sprintf('Este producto usa monto fijo. Importe permitido: %.2f.', $bundle_send_value),
                [
                    'status'            => 400,
                    'code'              => 'amount_fixed_only',
                    'fixed_send_value'  => $bundle_send_value,
                    'sku_code'          => $sku_code,
                    'country_iso'       => $country_iso,
                ]
            );
        }

        return true;
    }

    /**
     * Busca el bundle de referencia para validar el monto, priorizando bundle_id,
     * luego sku_code+country_iso, luego solo sku_code.
     */
    public function find_bundle_for_amount_validation($sku_code, $country_iso = '', $bundle_id = '') {
        $bundles = get_option('dc_recargas_bundles', []);
        if (!is_array($bundles) || empty($bundles)) {
            return null;
        }

        $sku_code    = strtoupper(sanitize_text_field((string) $sku_code));
        $country_iso = strtoupper(sanitize_text_field((string) $country_iso));
        $bundle_id   = sanitize_text_field((string) $bundle_id);

        if ('' !== $bundle_id) {
            foreach ($bundles as $bundle) {
                if (!is_array($bundle)) {
                    continue;
                }
                $candidate_id = sanitize_text_field((string) ($bundle['id'] ?? ($bundle['bundle_id'] ?? '')));
                if ($candidate_id === $bundle_id) {
                    return $bundle;
                }
            }
        }

        $first_sku_match = null;
        foreach ($bundles as $bundle) {
            if (!is_array($bundle)) {
                continue;
            }
            $bundle_sku = strtoupper(sanitize_text_field((string) ($bundle['sku_code'] ?? '')));
            if ('' === $bundle_sku || $bundle_sku !== $sku_code) {
                continue;
            }
            if (null === $first_sku_match) {
                $first_sku_match = $bundle;
            }
            if ('' === $country_iso) {
                continue;
            }
            $bundle_country = strtoupper(sanitize_text_field((string) ($bundle['country_iso'] ?? '')));
            if ($bundle_country === $country_iso) {
                return $bundle;
            }
        }

        return $first_sku_match;
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
        $raw_response = $response;

        if (is_wp_error($response)) {
            $error_data = $response->get_error_data();
            $status = sanitize_text_field((string) ($error_data['ding_error_code'] ?? $response->get_error_code()));
            if ($status === '') {
                $status = 'error';
            }
            $raw_response = [
                'error_code' => $response->get_error_code(),
                'error_message' => $response->get_error_message(),
                'error_data' => $error_data,
            ];
        } elseif (is_array($response)) {
            $items = $response['Items'] ?? $response['Result'] ?? [];
            if (!empty($items[0]['Status'])) {
                $status = sanitize_text_field((string) $items[0]['Status']);
            }
            $transfer_ref = sanitize_text_field((string) ($response['TransferRef'] ?? ''));
        }

        return $this->create_log_entry([
            'account_number' => $account_number,
            'sku_code' => $sku_code,
            'send_value' => $send_value,
            'currency' => $currency,
            'distributor_ref' => $distributor_ref,
            'transfer_ref' => $transfer_ref,
            'status' => $status,
            'event_type' => 'transfer_dispatch',
            'raw_response' => $raw_response,
        ]);
    }

    public function log_operational_event($event_type, $context = []) {
        $context = is_array($context) ? $context : [];
        $status = sanitize_text_field((string) ($context['status'] ?? $event_type));
        if ($status === '') {
            $status = sanitize_text_field((string) $event_type);
        }

        return $this->create_log_entry([
            'account_number' => (string) ($context['account_number'] ?? ''),
            'sku_code' => (string) ($context['sku_code'] ?? ''),
            'send_value' => (float) ($context['send_value'] ?? 0),
            'currency' => (string) ($context['currency'] ?? ''),
            'distributor_ref' => (string) ($context['distributor_ref'] ?? ''),
            'transfer_ref' => (string) ($context['transfer_ref'] ?? ''),
            'status' => $status,
            'event_type' => sanitize_key((string) $event_type),
            'raw_response' => $context['raw_response'] ?? $context,
            'order_id' => isset($context['order_id']) ? (int) $context['order_id'] : 0,
            'item_id' => isset($context['item_id']) ? (int) $context['item_id'] : 0,
            'payment_method' => (string) ($context['payment_method'] ?? ''),
        ]);
    }

    private function create_log_entry($entry) {
        $entry = is_array($entry) ? $entry : [];
        $account_number = sanitize_text_field((string) ($entry['account_number'] ?? ''));
        $sku_code = sanitize_text_field((string) ($entry['sku_code'] ?? ''));
        $send_value = (float) ($entry['send_value'] ?? 0);
        $currency = sanitize_text_field((string) ($entry['currency'] ?? ''));
        $distributor_ref = sanitize_text_field((string) ($entry['distributor_ref'] ?? ''));
        $transfer_ref = sanitize_text_field((string) ($entry['transfer_ref'] ?? ''));
        $status = $this->normalize_log_status((string) ($entry['status'] ?? 'unknown'));
        $event_type = sanitize_key((string) ($entry['event_type'] ?? 'general'));
        $raw_response = $entry['raw_response'] ?? [];

        // Mask phone for privacy: +573001234567 -> +5730***4567
        $masked = strlen($account_number) > 7
            ? substr($account_number, 0, 4) . '***' . substr($account_number, -4)
            : $account_number;

        $title_account = $masked !== '' ? $masked : 'N/A';
        $title_sku = $sku_code !== '' ? $sku_code : 'event';
        $post_id = wp_insert_post([
            'post_type' => 'dc_transfer_log',
            'post_title' => $title_account . ' — ' . $title_sku . ' — ' . $status,
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
            update_post_meta($post_id, '_dc_event_type', $event_type);
            update_post_meta($post_id, '_dc_raw_response', wp_json_encode($raw_response));

            $order_id = isset($entry['order_id']) ? (int) $entry['order_id'] : 0;
            $item_id = isset($entry['item_id']) ? (int) $entry['item_id'] : 0;
            $payment_method = sanitize_text_field((string) ($entry['payment_method'] ?? ''));
            if ($order_id > 0) {
                update_post_meta($post_id, '_dc_order_id', $order_id);
            }
            if ($item_id > 0) {
                update_post_meta($post_id, '_dc_order_item_id', $item_id);
            }
            if ($payment_method !== '') {
                update_post_meta($post_id, '_dc_payment_method', $payment_method);
            }
        }

        return $post_id;
    }

    private function normalize_log_status($status) {
        $raw = sanitize_text_field((string) $status);
        if ($raw === '') {
            return 'unknown';
        }

        $normalized = strtolower($raw);
        if (in_array($normalized, ['error', 'dc_http_error', 'providererror', 'blocked_gateway', 'failed_permanent'], true)) {
            return 'error';
        }
        if (strpos($normalized, 'validate') !== false) {
            return 'validate';
        }
        if (in_array($normalized, ['transfersuccessful', 'complete', 'completed', 'success', 'ok', 'approved'], true)) {
            return 'TransferSuccessful';
        }
        if (in_array($normalized, ['submitted', 'pending', 'processing', 'queued', 'inprogress', 'pending_retry', 'escalado_soporte'], true)) {
            return 'pending';
        }

        return $raw;
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
                    case 'ParameterOutOfRange':
                        $friendly_message = 'El importe enviado está fuera del rango permitido para este SKU (ParameterOutOfRange). Verifica los rangos del catálogo y refresca los bundles del plugin.';
                        break;
                    case 'SendValue':
                        $friendly_message = 'El valor de envío es inválido para este SKU (SendValue). Refresca los bundles y verifica el monto configurado.';
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

    public function extract_transfer_snapshot($payload, $fallback_distributor_ref = '') {
        $payload = is_array($payload) ? $payload : [];
        $record = is_array($payload['TransferRecord'] ?? null) ? $payload['TransferRecord'] : $payload;
        $transfer_id = is_array($record['TransferId'] ?? null) ? $record['TransferId'] : [];
        $items_data = $payload['Items'] ?? $payload['Result'] ?? [];
        $first_item = is_array($items_data[0] ?? null) ? $items_data[0] : [];
        $price = is_array($record['Price'] ?? null) ? $record['Price'] : [];

        $processing_state = sanitize_text_field((string) ($record['ProcessingState'] ?? $first_item['ProcessingState'] ?? ''));
        $status_label = sanitize_text_field((string) ($first_item['Status'] ?? ''));
        if ($status_label === '') {
            $status_label = $processing_state !== '' ? $processing_state : 'Completed';
        }

        return [
            'transfer_ref' => sanitize_text_field((string) ($transfer_id['TransferRef'] ?? $payload['TransferRef'] ?? $first_item['TransferRef'] ?? '')),
            'distributor_ref' => sanitize_text_field((string) ($transfer_id['DistributorRef'] ?? $payload['DistributorRef'] ?? $first_item['DistributorRef'] ?? $fallback_distributor_ref)),
            'status' => strtolower($status_label),
            'status_label' => $status_label,
            'processing_state' => $processing_state !== '' ? $processing_state : $status_label,
            'receive_value' => isset($price['ReceiveValue']) ? (float) $price['ReceiveValue'] : (isset($payload['ReceiveValue']) ? (float) $payload['ReceiveValue'] : 0.0),
            'receive_currency' => sanitize_text_field((string) ($price['ReceiveCurrencyIso'] ?? $payload['ReceiveCurrencyIso'] ?? '')),
            'receipt_text' => sanitize_text_field((string) ($record['ReceiptText'] ?? $payload['ReceiptText'] ?? '')),
            'receipt_params' => is_array($record['ReceiptParams'] ?? null) ? $record['ReceiptParams'] : (is_array($payload['ReceiptParams'] ?? null) ? $payload['ReceiptParams'] : []),
        ];
    }

    public function apply_transfer_snapshot_to_item($item, $snapshot, $account_number, $send_value) {
        if (!is_array($snapshot)) {
            $snapshot = [];
        }

        if (array_key_exists('transfer_ref', $snapshot) && (string) $snapshot['transfer_ref'] !== '') {
            $item->update_meta_data('_dc_transfer_ref', $snapshot['transfer_ref']);
        }

        if (!empty($snapshot['distributor_ref'])) {
            $item->update_meta_data('_dc_distributor_ref', $snapshot['distributor_ref']);
        }

        if (!empty($snapshot['status'])) {
            $item->update_meta_data('_dc_transfer_status', $snapshot['status']);
        }

        if ($this->is_pending_transfer_status($snapshot['status'] ?? '')) {
            if ((string) $item->get_meta('_dc_submitted_since') === '') {
                $item->update_meta_data('_dc_submitted_since', current_time('mysql'));
            }
        }

        if (!empty($snapshot['processing_state'])) {
            $item->update_meta_data('_dc_processing_state', $snapshot['processing_state']);
        }

        if (!empty($snapshot['receipt_text'])) {
            $item->update_meta_data('_dc_receipt_text', $snapshot['receipt_text']);
        }

        if (!empty($snapshot['receipt_params'])) {
            $item->update_meta_data('_dc_receipt_params', wp_json_encode($snapshot['receipt_params']));
        }

        if ($this->is_successful_transfer_status($snapshot['status'] ?? '')) {
            $item->delete_meta_data('_dc_transfer_error');
            $item->delete_meta_data('_dc_next_retry_at');
            $item->delete_meta_data('_dc_retry_attempts');
            $item->delete_meta_data('_dc_transfer_http_status');
            $item->delete_meta_data('_dc_transfer_error_code');
            $item->delete_meta_data('_dc_transfer_error_context');
        }

        $item->update_meta_data('_dc_account_number', $account_number);
        $item->update_meta_data('_dc_send_value', $send_value);
    }

    public function is_successful_transfer_status($status) {
        return in_array(strtolower((string) $status), ['success', 'complete', 'completed', 'transfersuccessful', 'ok', 'approved'], true);
    }

    public function is_pending_transfer_status($status) {
        return in_array(strtolower((string) $status), ['submitted', 'pending', 'processing', 'queued', 'inprogress', 'pending_confirmation'], true);
    }

    public function is_confirmed_transfer_reference($transfer_ref) {
        $transfer_ref = trim((string) $transfer_ref);
        if ($transfer_ref === '') {
            return false;
        }

        return $transfer_ref !== '0';
    }

    public function verify_webhook_signature($raw_body, $signature_header, $timestamp_header, $algorithm_header, $kid_header) {
        $raw_body = (string) $raw_body;
        $signature_header = trim((string) $signature_header);
        $timestamp_header = trim((string) $timestamp_header);
        $algorithm_header = strtoupper(trim((string) $algorithm_header));
        $kid_header = trim((string) $kid_header);

        if ($signature_header === '' || $timestamp_header === '' || $kid_header === '') {
            return [
                'ok' => false,
                'message' => 'Faltan headers de firma requeridos.',
            ];
        }

        if ($algorithm_header !== '' && $algorithm_header !== 'RS256') {
            return [
                'ok' => false,
                'message' => 'Algoritmo de firma no soportado.',
            ];
        }

        $timestamp = is_numeric($timestamp_header) ? (int) $timestamp_header : 0;
        if ($timestamp <= 0) {
            return [
                'ok' => false,
                'message' => 'Timestamp inválido.',
            ];
        }

        $options = $this->get_options();
        $tolerance = max(0, (int) ($options['webhook_timestamp_tolerance_seconds'] ?? 300));
        if ($tolerance > 0 && abs(time() - $timestamp) > $tolerance) {
            return [
                'ok' => false,
                'message' => 'Timestamp fuera de tolerancia.',
            ];
        }

        $public_key_pem = $this->get_webhook_public_key_pem_by_kid($kid_header);
        if (is_wp_error($public_key_pem)) {
            return [
                'ok' => false,
                'message' => $public_key_pem->get_error_message(),
            ];
        }

        $signature_bytes = base64_decode($signature_header, true);
        if ($signature_bytes === false) {
            $signature_bytes = $this->base64url_decode($signature_header);
        }
        if ($signature_bytes === false || $signature_bytes === '') {
            return [
                'ok' => false,
                'message' => 'Firma inválida (base64).',
            ];
        }

        $compat_mode = !empty($options['webhook_signature_compat_mode']);
        $candidates = $compat_mode
            ? [
                $raw_body . '.' . $timestamp_header,
                $timestamp_header . '.' . $raw_body,
                $raw_body . $timestamp_header,
                $timestamp_header . $raw_body,
            ]
            : [
                $raw_body . '.' . $timestamp_header,
            ];

        $verified_variant = '';
        foreach ($candidates as $candidate) {
            $result = openssl_verify($candidate, $signature_bytes, $public_key_pem, OPENSSL_ALGO_SHA256);
            if ($result === 1) {
                $verified_variant = $candidate === ($raw_body . '.' . $timestamp_header)
                    ? 'raw_body.timestamp'
                    : ($candidate === ($timestamp_header . '.' . $raw_body) ? 'timestamp.raw_body' : 'other');
                break;
            }
        }

        if ($verified_variant === '') {
            return [
                'ok' => false,
                'message' => 'Firma no válida.',
            ];
        }

        return [
            'ok' => true,
            'kid' => $kid_header,
            'timestamp' => $timestamp,
            'variant' => $verified_variant,
        ];
    }

    private function get_webhook_public_key_pem_by_kid($kid) {
        $kid = trim((string) $kid);
        if ($kid === '') {
            return new WP_Error('dc_webhook_kid_missing', 'Key-Id vacío.');
        }

        $cache_key = 'dc_webhook_pem_' . md5($kid);
        $cached = get_transient($cache_key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $jwks = $this->fetch_webhook_jwks();
        if (is_wp_error($jwks)) {
            return $jwks;
        }

        foreach ((array) ($jwks['keys'] ?? []) as $jwk) {
            if (!is_array($jwk)) {
                continue;
            }
            if ((string) ($jwk['kid'] ?? '') !== $kid) {
                continue;
            }

            $pem = $this->jwk_rsa_to_pem($jwk);
            if (is_wp_error($pem)) {
                return $pem;
            }

            set_transient($cache_key, $pem, 6 * HOUR_IN_SECONDS);
            return $pem;
        }

        return new WP_Error('dc_webhook_kid_not_found', 'No se encontró la clave pública para kid.');
    }

    private function fetch_webhook_jwks() {
        $cache_key = 'dc_webhook_jwks';
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get('https://idp.ding.com/.well-known/webhook-keys', [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status < 200 || $status >= 300 || !is_array($data)) {
            return new WP_Error('dc_webhook_jwks_http_error', 'No se pudieron recuperar las claves públicas del webhook.', [
                'status' => $status,
            ]);
        }

        set_transient($cache_key, $data, HOUR_IN_SECONDS);
        return $data;
    }

    private function jwk_rsa_to_pem($jwk) {
        $jwk = is_array($jwk) ? $jwk : [];
        if (($jwk['kty'] ?? '') !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) {
            return new WP_Error('dc_webhook_jwk_invalid', 'JWK inválida para RSA.');
        }

        $n = $this->base64url_decode((string) $jwk['n']);
        $e = $this->base64url_decode((string) $jwk['e']);
        if ($n === false || $e === false) {
            return new WP_Error('dc_webhook_jwk_decode', 'No se pudo decodificar la JWK.');
        }

        $rsa_public_key = $this->asn1_sequence(
            $this->asn1_integer($n) . $this->asn1_integer($e)
        );

        $algorithm_identifier = $this->asn1_sequence(
            $this->asn1_oid('1.2.840.113549.1.1.1') . $this->asn1_null()
        );

        $subject_public_key_info = $this->asn1_sequence(
            $algorithm_identifier . $this->asn1_bit_string($rsa_public_key)
        );

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($subject_public_key_info), 64, "\n")
            . "-----END PUBLIC KEY-----\n";

        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            return new WP_Error('dc_webhook_pem_invalid', 'La clave pública generada no es válida para OpenSSL.');
        }

        return $pem;
    }

    private function base64url_decode($data) {
        $data = (string) $data;
        if ($data === '') {
            return false;
        }
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'), true);
    }

    private function asn1_length($length) {
        $length = (int) $length;
        if ($length <= 0x7F) {
            return chr($length);
        }
        $temp = ltrim(pack('N', $length), "\x00");
        return chr(0x80 | strlen($temp)) . $temp;
    }

    private function asn1_integer($bytes) {
        $bytes = (string) $bytes;
        if ($bytes === '') {
            $bytes = "\x00";
        }
        if (ord($bytes[0]) > 0x7F) {
            $bytes = "\x00" . $bytes;
        }
        return "\x02" . $this->asn1_length(strlen($bytes)) . $bytes;
    }

    private function asn1_sequence($data) {
        return "\x30" . $this->asn1_length(strlen($data)) . $data;
    }

    private function asn1_null() {
        return "\x05\x00";
    }

    private function asn1_bit_string($data) {
        return "\x03" . $this->asn1_length(strlen($data) + 1) . "\x00" . $data;
    }

    private function asn1_oid($oid) {
        $parts = array_map('intval', explode('.', (string) $oid));
        if (count($parts) < 2) {
            return '';
        }
        $first = (40 * $parts[0]) + $parts[1];
        $encoded = chr($first);
        for ($i = 2; $i < count($parts); $i++) {
            $encoded .= $this->asn1_base128_int($parts[$i]);
        }
        return "\x06" . $this->asn1_length(strlen($encoded)) . $encoded;
    }

    private function asn1_base128_int($value) {
        $value = (int) $value;
        if ($value === 0) {
            return "\x00";
        }
        $result = '';
        while ($value > 0) {
            $result = chr($value & 0x7F) . $result;
            $value >>= 7;
        }
        $len = strlen($result);
        for ($i = 0; $i < $len - 1; $i++) {
            $result[$i] = chr(ord($result[$i]) | 0x80);
        }
        return $result;
    }
}
