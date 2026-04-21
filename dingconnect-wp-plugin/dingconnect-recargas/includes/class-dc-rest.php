<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('DC_Recargas_REST')) {
    return;
}

class DC_Recargas_REST {
    private $api;
    private $wizard;

    public function __construct($api, $wizard = null) {
        $this->api = $api;
        $this->wizard = $wizard;
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('dingconnect/v1', '/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'status'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('dingconnect/v1', '/balance', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'balance'],
            'permission_callback' => [$this, 'can_manage_options'],
        ]);

        register_rest_route('dingconnect/v1', '/bundles', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'bundles'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('dingconnect/v1', '/products', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'products'],
            'permission_callback' => '__return_true',
            'args' => [
                'account_number' => [
                    'required' => true,
                    'sanitize_callback' => [$this, 'sanitize_phone'],
                ],
                'country_iso' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('dingconnect/v1', '/transfer', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'transfer'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('dingconnect/v1', '/add-to-cart', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'add_to_cart'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('dingconnect/v1', '/wizard/config', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'wizard_config'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('dingconnect/v1', '/wizard/session', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'wizard_save_session'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('dingconnect/v1', '/wizard/session/(?P<session_id>[a-zA-Z0-9\-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'wizard_get_session'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('dingconnect/v1', '/wizard/offers', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'wizard_offers'],
            'permission_callback' => '__return_true',
            'args' => [
                'account_number' => [
                    'required' => false,
                    'sanitize_callback' => [$this, 'sanitize_phone'],
                ],
                'country_iso' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'category' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_key',
                ],
                'entry_mode' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_key',
                ],
                'fixed_prefix' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('dingconnect/v1', '/wizard/sync-now', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'wizard_sync_now'],
            'permission_callback' => [$this, 'can_manage_options'],
        ]);
    }

    public function wizard_config() {
        if (!$this->wizard instanceof DC_Recargas_Wizard) {
            return $this->wizard_error('wizard_config', 'Wizard no disponible en esta instalación.', 501);
        }

        return $this->wizard_success('wizard_config', $this->wizard->get_config());
    }

    public function wizard_save_session(WP_REST_Request $request) {
        if (!$this->wizard instanceof DC_Recargas_Wizard) {
            return $this->wizard_error('wizard_session', 'Wizard no disponible en esta instalación.', 501);
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }

        $session_id = sanitize_text_field((string) ($params['session_id'] ?? $this->wizard->new_session_id()));
        $state = $params['state'] ?? $this->wizard->get_initial_state($params['context'] ?? []);
        $context = is_array($params['context'] ?? null) ? $params['context'] : [];

        if (!$this->wizard->validate_state($state, $context)) {
            return $this->wizard_error('wizard_session', 'Estado de wizard inválido.', 400);
        }

        $saved = $this->wizard->save_session($session_id, $state, $context);

        if (is_wp_error($saved)) {
            $status = (int) (($saved->get_error_data()['status'] ?? 500));

            return $this->wizard_error('wizard_session', $saved->get_error_message(), $status, $saved->get_error_data());
        }

        return $this->wizard_success('wizard_session', $saved);
    }

    public function wizard_get_session(WP_REST_Request $request) {
        if (!$this->wizard instanceof DC_Recargas_Wizard) {
            return $this->wizard_error('wizard_session_get', 'Wizard no disponible en esta instalación.', 501);
        }

        $session_id = sanitize_text_field((string) $request->get_param('session_id'));
        $session = $this->wizard->get_session($session_id);

        if (!$session) {
            return $this->wizard_error('wizard_session_get', 'Sesión wizard no encontrada o expirada.', 404);
        }

        return $this->wizard_success('wizard_session_get', $session);
    }

    public function wizard_offers(WP_REST_Request $request) {
        if (!$this->wizard instanceof DC_Recargas_Wizard) {
            return $this->wizard_error('wizard_offers', 'Wizard no disponible en esta instalación.', 501);
        }

        if (!$this->check_rate_limit('wizard_offers', 20)) {
            return $this->wizard_error('wizard_offers', 'Demasiadas solicitudes. Intenta en un minuto.', 429);
        }

        $result = $this->wizard->get_offers([
            'account_number' => $request->get_param('account_number'),
            'country_iso' => strtoupper(sanitize_text_field((string) $request->get_param('country_iso'))),
            'category' => sanitize_key((string) $request->get_param('category')),
            'entry_mode' => sanitize_key((string) $request->get_param('entry_mode')),
            'fixed_prefix' => sanitize_text_field((string) $request->get_param('fixed_prefix')),
        ]);

        if (is_wp_error($result)) {
            $status = (int) (($result->get_error_data()['status'] ?? 500));

            return $this->wizard_error('wizard_offers', $result->get_error_message(), $status, $result->get_error_data());
        }

        return $this->wizard_success('wizard_offers', $result, [
            'source' => 'dingconnect',
            'category' => sanitize_key((string) $request->get_param('category')),
        ]);
    }

    public function wizard_sync_now(WP_REST_Request $request) {
        $country_iso = strtoupper(sanitize_text_field((string) $request->get_param('country_iso')));
        $target_countries = $country_iso !== '' ? [$country_iso] : $this->wizard_sync_countries();

        $stored_fingerprints = get_option('dc_wizard_sync_fingerprints', []);
        if (!is_array($stored_fingerprints)) {
            $stored_fingerprints = [];
        }

        $summary = [
            'synced_countries' => [],
            'changed_countries' => [],
            'notifications' => [],
            'errors' => [],
        ];

        foreach ($target_countries as $iso) {
            $response = $this->api->get_products_by_country($iso, 250);
            if (is_wp_error($response)) {
                $summary['errors'][] = [
                    'country_iso' => $iso,
                    'message' => $response->get_error_message(),
                ];
                continue;
            }

            $products = $this->normalize_products_for_frontend($response['Result'] ?? $response['Items'] ?? [], $iso);
            $fingerprint = md5(wp_json_encode(array_map(function ($product) {
                return [
                    'SkuCode' => $product['SkuCode'] ?? '',
                    'SendValue' => (float) ($product['SendValue'] ?? 0),
                    'ReceiveValue' => (float) ($product['ReceiveValue'] ?? 0),
                ];
            }, $products)));

            $previous_fingerprint = (string) ($stored_fingerprints[$iso] ?? '');
            $has_changed = $previous_fingerprint !== '' && $previous_fingerprint !== $fingerprint;

            $stored_fingerprints[$iso] = $fingerprint;

            $summary['synced_countries'][] = [
                'country_iso' => $iso,
                'products_count' => count($products),
                'changed' => $has_changed,
            ];

            if ($has_changed) {
                $summary['changed_countries'][] = $iso;
                $summary['notifications'][] = [
                    'type' => 'catalog_change_detected',
                    'country_iso' => $iso,
                    'message' => sprintf('Se detectaron cambios en catálogo para %s. Revisa textos/promociones visibles en landings.', $iso),
                ];
            }
        }

        update_option('dc_wizard_sync_fingerprints', $stored_fingerprints, false);
        update_option('dc_wizard_last_sync_at', current_time('mysql'), false);

        return $this->wizard_success('wizard_sync_now', [
            'summary' => $summary,
            'last_sync_at' => get_option('dc_wizard_last_sync_at', ''),
        ]);
    }

    public function status() {
        $options = $this->api->get_options();

        return rest_ensure_response([
            'ok' => true,
            'configured' => $this->api->is_configured(),
            'validate_only' => !empty($options['validate_only']),
            'allow_real_recharge' => !empty($options['allow_real_recharge']),
        ]);
    }

    public function balance() {
        $response = $this->api->get_balance();
        if (is_wp_error($response)) {
            $error_data = $response->get_error_data();
            $status_code = 500;
            if (is_array($error_data) && isset($error_data['status']) && is_numeric($error_data['status'])) {
                $status_code = (int) $error_data['status'];
            }

            return new WP_REST_Response([
                'ok' => false,
                'message' => $response->get_error_message(),
                'error' => $error_data,
            ], $status_code);
        }

        $normalized_balance = $this->normalize_balance_response($response);

        return rest_ensure_response([
            'ok' => true,
            'result' => $normalized_balance,
            'raw' => $response,
        ]);
    }

    public function bundles() {
        $bundles = get_option('dc_recargas_bundles', []);
        $active = array_values(array_filter($bundles, function ($bundle) {
            return !empty($bundle['is_active']);
        }));

        return rest_ensure_response([
            'ok' => true,
            'result' => $active,
        ]);
    }

    public function products(WP_REST_Request $request) {
        if (!$this->check_rate_limit('products', 20)) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Demasiadas solicitudes. Intenta en un minuto.'], 429);
        }

        $account_number = $this->sanitize_phone($request->get_param('account_number'));
        $country_iso = strtoupper(sanitize_text_field($request->get_param('country_iso') ?? ''));

        if (empty($account_number) || strlen($account_number) < 8) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => 'Número de móvil inválido.',
            ], 400);
        }

        // Catálogo curado: si existen bundles activos para el país, mostrar solo esos.
        $saved = $this->filter_bundles_by_country($country_iso);
        if (!empty($saved)) {
            return rest_ensure_response([
                'ok' => true,
                'source' => 'saved',
                'result' => $saved,
            ]);
        }

        // Sin bundles guardados: usar catálogo live de DingConnect.
        $response = !empty($country_iso)
            ? $this->api->get_products_by_country($country_iso, 250)
            : $this->api->get_products($account_number, 250);

        $response_items = is_wp_error($response) ? [] : ($response['Result'] ?? $response['Items'] ?? []);

        if ((is_wp_error($response) || empty($response_items)) && !empty($account_number)) {
            $account_response = $this->api->get_products($account_number, 250);
            if (!is_wp_error($account_response)) {
                $response = $account_response;
            }
        }

        if (is_wp_error($response)) {
            return new WP_REST_Response([
                'ok' => false,
                'source' => 'fallback',
                'message' => $response->get_error_message(),
                'error' => $response->get_error_data(),
                'result' => [],
            ], 200);
        }

        $api_items = $this->normalize_products_for_frontend($response['Result'] ?? $response['Items'] ?? [], $country_iso);

        return rest_ensure_response([
            'ok' => true,
            'source' => 'dingconnect',
            'result' => $api_items,
        ]);
    }

    public function transfer(WP_REST_Request $request) {
        if (!$this->check_rate_limit('transfer', 5)) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Demasiadas solicitudes. Intenta en un minuto.'], 429);
        }

        $params = $request->get_json_params();

        $payload = [
            'DistributorRef' => sanitize_text_field($params['distributor_ref'] ?? $this->api->new_ref()),
            'AccountNumber' => $this->sanitize_phone($params['account_number'] ?? ''),
            'SkuCode' => sanitize_text_field($params['sku_code'] ?? ''),
            'SendValue' => (float) ($params['send_value'] ?? 0),
            'SendCurrencyIso' => strtoupper(sanitize_text_field($params['send_currency_iso'] ?? 'USD')),
            'ValidateOnly' => isset($params['validate_only']) ? (bool) $params['validate_only'] : null,
        ];

        if (empty($payload['AccountNumber']) || empty($payload['SkuCode']) || $payload['SendValue'] <= 0) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => 'Datos incompletos para procesar la recarga.',
            ], 400);
        }

        $response = $this->api->send_transfer($payload);

        $this->api->log_transfer(
            $payload['AccountNumber'],
            $payload['SkuCode'],
            $payload['SendValue'],
            $payload['SendCurrencyIso'],
            $payload['DistributorRef'],
            $response
        );

        if (is_wp_error($response)) {
            $error_data = $response->get_error_data();
            $status_code = 500;
            if (is_array($error_data) && isset($error_data['status']) && is_numeric($error_data['status'])) {
                $status_code = (int) $error_data['status'];
            }

            return new WP_REST_Response([
                'ok' => false,
                'message' => $response->get_error_message(),
                'error' => $error_data,
            ], $status_code);
        }

        return rest_ensure_response([
            'ok' => true,
            'result' => $response,
        ]);
    }

    public function add_to_cart(WP_REST_Request $request) {
        if (!class_exists('WooCommerce')) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => 'WooCommerce no está activo.',
            ], 400);
        }

        if (!$this->check_rate_limit('add_to_cart', 10)) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => 'Demasiadas solicitudes. Intenta en un minuto.',
            ], 429);
        }

        $params = $request->get_json_params();

        $account_number = $this->sanitize_phone($params['account_number'] ?? '');
        $country_iso = strtoupper(sanitize_text_field($params['country_iso'] ?? ''));
        $sku_code = sanitize_text_field($params['sku_code'] ?? '');
        $send_value = (float) ($params['send_value'] ?? 0);
        $send_currency_iso = strtoupper(sanitize_text_field($params['send_currency_iso'] ?? 'EUR'));
        $provider_name = sanitize_text_field($params['provider_name'] ?? '');
        $bundle_label = sanitize_text_field($params['bundle_label'] ?? '');

        if (empty($account_number) || empty($sku_code) || $send_value <= 0) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => 'Datos incompletos para añadir al carrito.',
            ], 400);
        }

        // Delegate to WooCommerce class via filter
        $result = apply_filters('dc_recargas_add_to_cart', null, [
            'account_number' => $account_number,
            'country_iso' => $country_iso,
            'sku_code' => $sku_code,
            'send_value' => $send_value,
            'send_currency_iso' => $send_currency_iso,
            'provider_name' => $provider_name,
            'bundle_label' => $bundle_label,
        ]);

        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => $result->get_error_message(),
            ], 400);
        }

        return rest_ensure_response([
            'ok' => true,
            'redirect' => wc_get_checkout_url(),
            'message' => 'Recarga añadida al carrito.',
        ]);
    }

    public function sanitize_phone($phone) {
        // DingConnect valida AccountNumber por regex y rechaza simbolos como '+'.
        return preg_replace('/\D+/', '', (string) $phone);
    }

    private function check_rate_limit($action, $limit_per_minute = 10) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'dc_rate_' . md5($action . '_' . $ip);
        $count = (int) get_transient($key);

        if ($count >= $limit_per_minute) {
            return false;
        }

        set_transient($key, $count + 1, MINUTE_IN_SECONDS);
        return true;
    }

    public function can_manage_options() {
        return current_user_can('manage_options');
    }

    private function wizard_success($endpoint, $result, $meta = []) {
        $payload = [
            'ok' => true,
            'endpoint' => $endpoint,
            'contract_version' => '1.0',
            'result' => $result,
            'meta' => wp_parse_args($meta, [
                'backend_only' => true,
                'namespace' => 'dingconnect/v1',
            ]),
        ];

        return rest_ensure_response($payload);
    }

    private function wizard_error($endpoint, $message, $status = 500, $error = null) {
        return new WP_REST_Response([
            'ok' => false,
            'endpoint' => $endpoint,
            'contract_version' => '1.0',
            'message' => $message,
            'error' => $error,
        ], (int) $status);
    }

    private function wizard_sync_countries() {
        $bundles = get_option('dc_recargas_bundles', []);
        $countries = [];

        foreach ((array) $bundles as $bundle) {
            $iso = strtoupper(sanitize_text_field((string) ($bundle['country_iso'] ?? '')));
            if ($iso !== '') {
                $countries[] = $iso;
            }
        }

        if (empty($countries)) {
            $countries = ['CU'];
        }

        return array_values(array_unique($countries));
    }

    private function filter_bundles_by_country($country_iso) {
        $bundles = get_option('dc_recargas_bundles', []);
        $active = array_values(array_filter($bundles, function ($bundle) use ($country_iso) {
            if (empty($bundle['is_active'])) {
                return false;
            }

            if (empty($country_iso)) {
                return true;
            }

            return strtoupper((string) ($bundle['country_iso'] ?? '')) === $country_iso;
        }));

        return array_map(function ($bundle) {
            return [
                'BundleId' => $bundle['id'] ?? '',
                'SkuCode' => $bundle['sku_code'] ?? '',
                'ProviderName' => $bundle['provider_name'] ?? '',
                'SendValue' => (float) ($bundle['send_value'] ?? 0),
                'SendCurrencyIso' => $bundle['send_currency_iso'] ?? 'USD',
                'DefaultDisplayText' => $bundle['label'] ?? '',
                'Description' => $bundle['description'] ?? '',
                'CountryIso' => strtoupper((string) ($bundle['country_iso'] ?? '')),
                'IsPromotion' => false,
                'IsRange' => false,
            ];
        }, $active);
    }

    private function normalize_products_for_frontend($items, $country_iso) {
        if (empty($items) || !is_array($items)) {
            return [];
        }

        $provider_map = $this->get_provider_name_map($items, $country_iso);
        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $sku_code = sanitize_text_field($item['SkuCode'] ?? '');
            if ('' === $sku_code) {
                continue;
            }

            $provider_code = sanitize_text_field($item['ProviderCode'] ?? '');
            $provider_name = sanitize_text_field($item['ProviderName'] ?? ($provider_map[$provider_code] ?? $provider_code));
            $price = $this->extract_product_price($item);

            $normalized[] = [
                'ProviderCode' => $provider_code,
                'ProviderName' => $provider_name,
                'SkuCode' => $sku_code,
                'SendValue' => $price['SendValue'],
                'SendCurrencyIso' => $price['SendCurrencyIso'],
                'ReceiveValue' => $price['ReceiveValue'],
                'ReceiveCurrencyIso' => $price['ReceiveCurrencyIso'],
                'DefaultDisplayText' => sanitize_text_field($item['DefaultDisplayText'] ?? $sku_code),
                'Description' => $this->build_product_description($item),
                'IsPromotion' => !empty($item['IsPromotion']),
                'IsRange' => $this->is_range_product($item),
                'Benefits' => array_values(array_filter(array_map('sanitize_text_field', (array) ($item['Benefits'] ?? [])))),
                'ValidityPeriodIso' => sanitize_text_field($item['ValidityPeriodIso'] ?? ''),
                'RedemptionMechanism' => sanitize_text_field($item['RedemptionMechanism'] ?? ''),
            ];
        }

        usort($normalized, function ($left, $right) {
            $provider_compare = strcasecmp((string) ($left['ProviderName'] ?? ''), (string) ($right['ProviderName'] ?? ''));
            if (0 !== $provider_compare) {
                return $provider_compare;
            }

            $price_compare = (float) ($left['SendValue'] ?? 0) <=> (float) ($right['SendValue'] ?? 0);
            if (0 !== $price_compare) {
                return $price_compare;
            }

            return strcasecmp((string) ($left['DefaultDisplayText'] ?? ''), (string) ($right['DefaultDisplayText'] ?? ''));
        });

        return $normalized;
    }

    private function get_provider_name_map($items, $country_iso) {
        $provider_codes = [];

        foreach ((array) $items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $provider_code = sanitize_text_field($item['ProviderCode'] ?? '');
            if ('' !== $provider_code) {
                $provider_codes[] = $provider_code;
            }
        }

        $provider_codes = array_values(array_unique($provider_codes));
        if (empty($provider_codes)) {
            return [];
        }

        $response = $this->api->get_providers_by_codes($provider_codes);

        if (is_wp_error($response) && !empty($country_iso)) {
            $response = $this->api->get_providers_by_country($country_iso);
        }

        if (is_wp_error($response)) {
            return [];
        }

        $providers = $response['Result'] ?? $response['Items'] ?? [];
        $map = [];

        foreach ((array) $providers as $provider) {
            if (!is_array($provider)) {
                continue;
            }

            $provider_code = sanitize_text_field($provider['ProviderCode'] ?? '');
            if ('' === $provider_code) {
                continue;
            }

            $provider_name = sanitize_text_field($provider['Name'] ?? ($provider['ShortName'] ?? $provider_code));
            $map[$provider_code] = $provider_name;
        }

        return $map;
    }

    private function extract_product_price($item) {
        $minimum = is_array($item['Minimum'] ?? null) ? $item['Minimum'] : [];
        $maximum = is_array($item['Maximum'] ?? null) ? $item['Maximum'] : [];
        $price = !empty($minimum) ? $minimum : $maximum;

        return [
            'SendValue' => (float) ($item['SendValue'] ?? ($price['SendValue'] ?? 0)),
            'SendCurrencyIso' => sanitize_text_field($item['SendCurrencyIso'] ?? ($price['SendCurrencyIso'] ?? 'USD')),
            'ReceiveValue' => (float) ($item['ReceiveValue'] ?? ($price['ReceiveValue'] ?? 0)),
            'ReceiveCurrencyIso' => sanitize_text_field($item['ReceiveCurrencyIso'] ?? ($price['ReceiveCurrencyIso'] ?? '')),
        ];
    }

    private function build_product_description($item) {
        $description = sanitize_text_field($item['Description'] ?? '');
        if ('' !== $description) {
            return $description;
        }

        $additional = sanitize_text_field($item['AdditionalInformation'] ?? '');
        if ('' !== $additional) {
            return $additional;
        }

        $benefits = array_values(array_filter(array_map('sanitize_text_field', (array) ($item['Benefits'] ?? []))));
        return implode(' · ', array_slice($benefits, 0, 3));
    }

    private function is_range_product($item) {
        $minimum_value = (float) ($item['Minimum']['SendValue'] ?? 0);
        $maximum_value = (float) ($item['Maximum']['SendValue'] ?? 0);

        return $minimum_value > 0 && $maximum_value > 0 && abs($maximum_value - $minimum_value) > 0.00001;
    }

    private function merge_products_by_sku($primary_items, $secondary_items) {
        $merged = [];

        foreach ((array) $primary_items as $item) {
            $sku_code = strtoupper((string) ($item['SkuCode'] ?? ''));
            if ('' !== $sku_code) {
                $merged[$sku_code] = $item;
            }
        }

        foreach ((array) $secondary_items as $item) {
            $sku_code = strtoupper((string) ($item['SkuCode'] ?? ''));
            if ('' === $sku_code) {
                continue;
            }

            if (!isset($merged[$sku_code])) {
                $merged[$sku_code] = $item;
                continue;
            }

            foreach (['ProviderName', 'Description', 'DefaultDisplayText', 'SendValue', 'SendCurrencyIso'] as $field) {
                if ((empty($merged[$sku_code][$field]) && !empty($item[$field])) || (!isset($merged[$sku_code][$field]) && isset($item[$field]))) {
                    $merged[$sku_code][$field] = $item[$field];
                }
            }
        }

        return array_values($merged);
    }

    private function normalize_balance_response($response) {
        $raw = is_array($response) ? $response : [];
        $candidate = $raw;

        if (isset($raw['Result'])) {
            if (is_array($raw['Result']) && isset($raw['Result'][0]) && is_array($raw['Result'][0])) {
                $candidate = array_merge($candidate, $raw['Result'][0]);
            } elseif (is_array($raw['Result'])) {
                $candidate = array_merge($candidate, $raw['Result']);
            }
        }

        if ((!isset($candidate['Balance']) || '' === (string) $candidate['Balance']) && isset($raw['Items']) && is_array($raw['Items']) && isset($raw['Items'][0]) && is_array($raw['Items'][0])) {
            $candidate = array_merge($candidate, $raw['Items'][0]);
        }

        $balance = isset($candidate['Balance']) ? (float) $candidate['Balance'] : 0.0;
        $currency_iso = sanitize_text_field((string) ($candidate['CurrencyIso'] ?? 'USD'));
        $result_code = isset($candidate['ResultCode']) && '' !== (string) $candidate['ResultCode']
            ? (int) $candidate['ResultCode']
            : null;

        return [
            'Balance' => $balance,
            'CurrencyIso' => '' !== $currency_iso ? $currency_iso : 'USD',
            'ResultCode' => $result_code,
            'RawShape' => $this->detect_balance_shape($raw),
        ];
    }

    private function detect_balance_shape($raw) {
        if (isset($raw['Balance'])) {
            return 'top_level';
        }

        if (isset($raw['Result']) && is_array($raw['Result']) && isset($raw['Result'][0]) && is_array($raw['Result'][0])) {
            return 'result_array';
        }

        if (isset($raw['Result']) && is_array($raw['Result'])) {
            return 'result_object';
        }

        if (isset($raw['Items']) && is_array($raw['Items']) && isset($raw['Items'][0]) && is_array($raw['Items'][0])) {
            return 'items_array';
        }

        return 'unknown';
    }
}
