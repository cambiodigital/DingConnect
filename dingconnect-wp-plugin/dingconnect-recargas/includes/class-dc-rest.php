<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('DC_Recargas_REST')) {
    return;
}

class DC_Recargas_REST {
    private $api;

    public function __construct($api) {
        $this->api = $api;
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

        register_rest_route('dingconnect/v1', '/landing-config', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'landing_config'],
            'permission_callback' => '__return_true',
            'args' => [
                'landing_key' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        register_rest_route('dingconnect/v1', '/products', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'products'],
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
                'provider_code' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'region_code' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'benefit' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'sku_code' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'redemption_mechanism' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'allowed_bundle_ids' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('dingconnect/v1', '/provider-status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'provider_status'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('dingconnect/v1', '/estimate-prices', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'estimate_prices'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('dingconnect/v1', '/lookup-bills', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'lookup_bills'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('dingconnect/v1', '/transfer-status', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'transfer_status'],
            'permission_callback' => '__return_true',
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
        if (!is_array($bundles)) {
            $bundles = [];
        }
        $active = array_values(array_filter($bundles, function ($bundle) {
            return !empty($bundle['is_active']);
        }));

        return rest_ensure_response([
            'ok' => true,
            'result' => $active,
        ]);
    }

    public function landing_config(WP_REST_Request $request) {
        $landing_key = sanitize_key((string) ($request->get_param('landing_key') ?? ''));
        if ($landing_key === '') {
            return new WP_REST_Response([
                'ok' => false,
                'message' => 'Debes indicar landing_key.',
            ], 400);
        }

        $configs = get_option('dc_recargas_landing_shortcodes', []);
        if (!is_array($configs)) {
            $configs = [];
        }

        $found = null;
        foreach ($configs as $config) {
            if (!is_array($config)) {
                continue;
            }

            if (sanitize_key((string) ($config['key'] ?? '')) === $landing_key) {
                $found = $config;
                break;
            }
        }

        if (!is_array($found)) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => 'No se encontró configuración para la landing indicada.',
            ], 404);
        }

        $bundle_ids = [];
        foreach ((array) ($found['bundle_ids'] ?? []) as $bundle_id) {
            $bundle_id = sanitize_text_field((string) $bundle_id);
            if ($bundle_id !== '') {
                $bundle_ids[] = $bundle_id;
            }
        }
        $bundle_ids = array_values(array_unique($bundle_ids));

        $featured_bundle_id = sanitize_text_field((string) ($found['featured_bundle_id'] ?? ''));
        if (!in_array($featured_bundle_id, $bundle_ids, true)) {
            $featured_bundle_id = '';
        }

        return rest_ensure_response([
            'ok' => true,
            'result' => [
                'landing_key' => $landing_key,
                'bundle_ids' => $bundle_ids,
                'featured_bundle_id' => $featured_bundle_id,
                'country_iso' => strtoupper(sanitize_text_field((string) ($found['country_iso'] ?? ''))),
                'updated_at' => sanitize_text_field((string) ($found['updated_at'] ?? ($found['created_at'] ?? ''))),
            ],
        ]);
    }

    public function products(WP_REST_Request $request) {
        if (!$this->check_rate_limit('products', 20)) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Demasiadas solicitudes. Intenta en un minuto.'], 429);
        }

        $account_number = $this->sanitize_phone($request->get_param('account_number'));
        $country_iso = strtoupper(sanitize_text_field($request->get_param('country_iso') ?? ''));
        $provider_code = sanitize_text_field((string) ($request->get_param('provider_code') ?? ''));
        $region_code = sanitize_text_field((string) ($request->get_param('region_code') ?? ''));
        $benefit = sanitize_text_field((string) ($request->get_param('benefit') ?? ''));
        $sku_code = sanitize_text_field((string) ($request->get_param('sku_code') ?? ''));
        $redemption_mechanism = sanitize_text_field((string) ($request->get_param('redemption_mechanism') ?? ''));
        $allowed_bundle_ids = $this->parse_bundle_ids((string) ($request->get_param('allowed_bundle_ids') ?? ''));

        if (empty($account_number) && empty($country_iso) && empty($provider_code) && empty($sku_code)) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => 'Debes indicar al menos un número, país, proveedor o SKU para consultar productos.',
            ], 400);
        }

        if (!empty($account_number) && strlen($account_number) < 8) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => 'Número de móvil inválido.',
            ], 400);
        }

        // Catálogo curado: para shortcodes de landing, respetar bundles configurados;
        // en modo general, usar bundles activos por país.
        $saved = $this->filter_bundles_by_country($country_iso, $allowed_bundle_ids);
        if (!empty($saved)) {
            return rest_ensure_response([
                'ok' => true,
                'source' => 'saved',
                'result' => $saved,
            ]);
        }

        // Sin bundles guardados: usar catálogo live de DingConnect.
        $response = $this->api->get_products_catalog([
            'account_number' => $account_number,
            'country_isos' => !empty($country_iso) ? [$country_iso] : [],
            'provider_codes' => !empty($provider_code) ? [$provider_code] : [],
            'region_codes' => !empty($region_code) ? [$region_code] : [],
            'benefits' => !empty($benefit) ? [$benefit] : [],
            'sku_codes' => !empty($sku_code) ? [$sku_code] : [],
            'take' => 250,
        ]);

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

        $api_items = $this->normalize_products_for_frontend($response['Result'] ?? $response['Items'] ?? [], $country_iso, [
            'account_number' => $account_number,
            'country_iso' => $country_iso,
            'provider_code' => $provider_code,
            'region_code' => $region_code,
            'benefit' => $benefit,
            'redemption_mechanism' => $redemption_mechanism,
            'sku_code' => $sku_code,
        ]);

        return rest_ensure_response([
            'ok' => true,
            'source' => 'dingconnect',
            'result' => $api_items,
        ]);
    }

    public function provider_status(WP_REST_Request $request) {
        if (!$this->check_rate_limit('provider_status', 30)) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Demasiadas solicitudes. Intenta en un minuto.'], 429);
        }

        $provider_codes = $this->sanitize_string_list($request->get_param('provider_codes') ?? $request->get_param('provider_code') ?? '');
        if (empty($provider_codes)) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Debes indicar al menos un provider_code.'], 400);
        }

        $response = $this->api->get_provider_status($provider_codes);
        if (is_wp_error($response)) {
            return $this->wp_error_to_rest_response($response);
        }

        $items = $response['Result'] ?? $response['Items'] ?? [];

        return rest_ensure_response([
            'ok' => true,
            'result' => array_values(array_map(function ($item) {
                return [
                    'ProviderCode' => sanitize_text_field((string) ($item['ProviderCode'] ?? '')),
                    'IsProcessingTransfers' => !empty($item['IsProcessingTransfers']),
                    'Message' => sanitize_text_field((string) ($item['Message'] ?? '')),
                ];
            }, array_filter((array) $items, 'is_array'))),
        ]);
    }

    public function estimate_prices(WP_REST_Request $request) {
        if (!$this->check_rate_limit('estimate_prices', 20)) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Demasiadas solicitudes. Intenta en un minuto.'], 429);
        }

        $params = $request->get_json_params();
        $items = [];

        if (!empty($params['items']) && is_array($params['items'])) {
            $items = $params['items'];
        } else {
            $items[] = [
                'SkuCode' => sanitize_text_field((string) ($params['sku_code'] ?? '')),
                'SendValue' => (float) ($params['send_value'] ?? 0),
                'SendCurrencyIso' => strtoupper(sanitize_text_field((string) ($params['send_currency_iso'] ?? ''))),
                'ReceiveValue' => (float) ($params['receive_value'] ?? 0),
                'BatchItemRef' => sanitize_text_field((string) ($params['batch_item_ref'] ?? 'ITEM-1')),
            ];
        }

        $response = $this->api->estimate_prices($items);
        if (is_wp_error($response)) {
            return $this->wp_error_to_rest_response($response);
        }

        $items = $response['Result'] ?? $response['Items'] ?? [];

        return rest_ensure_response([
            'ok' => true,
            'result' => array_values(array_map(function ($item) {
                $price = is_array($item['Price'] ?? null) ? $item['Price'] : [];

                return [
                    'SkuCode' => sanitize_text_field((string) ($item['SkuCode'] ?? '')),
                    'BatchItemRef' => sanitize_text_field((string) ($item['BatchItemRef'] ?? '')),
                    'SendValue' => (float) ($price['SendValue'] ?? 0),
                    'SendCurrencyIso' => sanitize_text_field((string) ($price['SendCurrencyIso'] ?? '')),
                    'ReceiveValue' => (float) ($price['ReceiveValue'] ?? 0),
                    'ReceiveCurrencyIso' => sanitize_text_field((string) ($price['ReceiveCurrencyIso'] ?? '')),
                    'ReceiveValueExcludingTax' => (float) ($price['ReceiveValueExcludingTax'] ?? 0),
                    'CustomerFee' => (float) ($price['CustomerFee'] ?? 0),
                    'DistributorFee' => (float) ($price['DistributorFee'] ?? 0),
                    'TaxRate' => (float) ($price['TaxRate'] ?? 0),
                    'TaxName' => sanitize_text_field((string) ($price['TaxName'] ?? '')),
                    'TaxCalculation' => sanitize_text_field((string) ($price['TaxCalculation'] ?? '')),
                    'ResultCode' => isset($item['ResultCode']) ? (int) $item['ResultCode'] : 0,
                    'ErrorCodes' => array_values((array) ($item['ErrorCodes'] ?? [])),
                ];
            }, array_filter((array) $items, 'is_array'))),
        ]);
    }

    public function lookup_bills(WP_REST_Request $request) {
        if (!$this->check_rate_limit('lookup_bills', 10)) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Demasiadas solicitudes. Intenta en un minuto.'], 429);
        }

        $params = $request->get_json_params();
        $sku_code = sanitize_text_field((string) ($params['sku_code'] ?? ''));
        $account_number = $this->sanitize_phone($params['account_number'] ?? '');
        $settings = $this->sanitize_settings($params['settings'] ?? []);

        $response = $this->api->lookup_bills($sku_code, $account_number, $settings);
        if (is_wp_error($response)) {
            return $this->wp_error_to_rest_response($response);
        }

        $items = $response['Result'] ?? $response['Items'] ?? [];

        return rest_ensure_response([
            'ok' => true,
            'result' => array_values(array_map(function ($item) {
                $price = is_array($item['Price'] ?? null) ? $item['Price'] : [];
                $error_codes = [];
                foreach ((array) ($item['ErrorCodes'] ?? []) as $error_code) {
                    if (is_array($error_code)) {
                        $error_codes[] = [
                            'Code' => sanitize_text_field((string) ($error_code['Code'] ?? '')),
                            'Context' => sanitize_text_field((string) ($error_code['Context'] ?? '')),
                            'Message' => sanitize_text_field((string) ($error_code['Message'] ?? '')),
                        ];
                        continue;
                    }

                    $error_codes[] = [
                        'Code' => sanitize_text_field((string) $error_code),
                        'Context' => '',
                        'Message' => '',
                    ];
                }

                return [
                    'BillRef' => sanitize_text_field((string) ($item['BillRef'] ?? '')),
                    'AdditionalInfo' => is_array($item['AdditionalInfo'] ?? null) ? $item['AdditionalInfo'] : [],
                    'SendValue' => (float) ($price['SendValue'] ?? 0),
                    'SendCurrencyIso' => sanitize_text_field((string) ($price['SendCurrencyIso'] ?? '')),
                    'ReceiveValue' => (float) ($price['ReceiveValue'] ?? 0),
                    'ReceiveCurrencyIso' => sanitize_text_field((string) ($price['ReceiveCurrencyIso'] ?? '')),
                    'ReceiveValueExcludingTax' => (float) ($price['ReceiveValueExcludingTax'] ?? 0),
                    'CustomerFee' => (float) ($price['CustomerFee'] ?? 0),
                    'DistributorFee' => (float) ($price['DistributorFee'] ?? 0),
                    'TaxRate' => (float) ($price['TaxRate'] ?? 0),
                    'TaxName' => sanitize_text_field((string) ($price['TaxName'] ?? '')),
                    'TaxCalculation' => sanitize_text_field((string) ($price['TaxCalculation'] ?? '')),
                    'ResultCode' => isset($item['ResultCode']) ? (int) $item['ResultCode'] : 0,
                    'ErrorCodes' => $error_codes,
                ];
            }, array_filter((array) $items, 'is_array'))),
        ]);
    }

    public function transfer_status(WP_REST_Request $request) {
        if (!$this->check_rate_limit('transfer_status', 20)) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Demasiadas solicitudes. Intenta en un minuto.'], 429);
        }

        $params = $request->get_json_params();
        $payload = [
            'TransferRef' => sanitize_text_field((string) ($params['transfer_ref'] ?? '')),
            'DistributorRef' => sanitize_text_field((string) ($params['distributor_ref'] ?? '')),
            'AccountNumber' => $this->sanitize_phone($params['account_number'] ?? ''),
            'Take' => max(1, (int) ($params['take'] ?? 1)),
            'Skip' => max(0, (int) ($params['skip'] ?? 0)),
        ];

        $response = $this->api->list_transfer_records($payload);
        if (is_wp_error($response)) {
            return $this->wp_error_to_rest_response($response);
        }

        $items = $response['Result'] ?? $response['Items'] ?? [];

        return rest_ensure_response([
            'ok' => true,
            'there_are_more_items' => !empty($response['ThereAreMoreItems']),
            'result' => $this->normalize_transfer_record_items($items),
        ]);
    }

    public function transfer(WP_REST_Request $request) {
        if (!$this->check_rate_limit('transfer', 5)) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Demasiadas solicitudes. Intenta en un minuto.'], 429);
        }

        $params = $request->get_json_params();
        $country_iso = strtoupper(sanitize_text_field((string) ($params['country_iso'] ?? '')));

        $payload = [
            'DistributorRef' => sanitize_text_field($params['distributor_ref'] ?? $this->api->new_ref()),
            'AccountNumber' => $this->sanitize_phone($params['account_number'] ?? ''),
            'SkuCode' => sanitize_text_field($params['sku_code'] ?? ''),
            'SendValue' => (float) ($params['send_value'] ?? 0),
            'SendCurrencyIso' => strtoupper(sanitize_text_field($params['send_currency_iso'] ?? '')),
            'ValidateOnly' => isset($params['validate_only']) ? (bool) $params['validate_only'] : null,
            'Settings' => $this->sanitize_settings($params['settings'] ?? []),
            'BillRef' => sanitize_text_field((string) ($params['bill_ref'] ?? '')),
        ];

        if (empty($payload['AccountNumber']) || empty($payload['SkuCode']) || $payload['SendValue'] <= 0) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => 'Datos incompletos para procesar la recarga.',
            ], 400);
        }

        $amount_validation = $this->validate_send_value_against_bundle($payload['SkuCode'], $country_iso, (float) $payload['SendValue']);
        if (is_wp_error($amount_validation)) {
            return $this->wp_error_to_rest_response($amount_validation);
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
        $public_price = (float) ($params['public_price'] ?? $send_value);
        $public_price_currency = strtoupper(sanitize_text_field($params['public_price_currency'] ?? $send_currency_iso));
        $provider_name = sanitize_text_field($params['provider_name'] ?? '');
        $bundle_label = sanitize_text_field($params['bundle_label'] ?? '');
        $bundle_benefit = sanitize_text_field((string) ($params['bundle_benefit'] ?? ''));
        $bundle_id = sanitize_text_field((string) ($params['bundle_id'] ?? ''));
        $product_type = sanitize_text_field((string) ($params['product_type'] ?? ''));
        $redemption_mechanism = sanitize_text_field((string) ($params['redemption_mechanism'] ?? ''));
        $lookup_bills_required = !empty($params['lookup_bills_required']);
        $customer_care_number = sanitize_text_field((string) ($params['customer_care_number'] ?? ''));
        $is_range = !empty($params['is_range']);
        $settings = $this->sanitize_settings($params['settings'] ?? []);
        $bill_ref = sanitize_text_field((string) ($params['bill_ref'] ?? ''));

        if (empty($account_number) || empty($sku_code) || $send_value <= 0) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => 'Datos incompletos para añadir al carrito.',
            ], 400);
        }

        $amount_validation = $this->validate_send_value_against_bundle($sku_code, $country_iso, $send_value);
        if (is_wp_error($amount_validation)) {
            return $this->wp_error_to_rest_response($amount_validation);
        }

        // Precio comercial robusto: prioriza el precio público guardado del bundle.
        // Solo usa coste Ding cuando no existe precio público (> 0).
        $resolved_public_price = $this->resolve_public_price_for_cart($bundle_id, $sku_code, $country_iso, $public_price);
        if ($resolved_public_price > 0) {
            $public_price = $resolved_public_price;
        } elseif ($public_price <= 0) {
            $public_price = $send_value;
        }

        // Delegate to WooCommerce class via filter
        $result = apply_filters('dc_recargas_add_to_cart', null, [
            'account_number' => $account_number,
            'country_iso' => $country_iso,
            'sku_code' => $sku_code,
            'send_value' => $send_value,
            'send_currency_iso' => $send_currency_iso,
            'public_price' => $public_price,
            'public_price_currency' => $public_price_currency,
            'provider_name' => $provider_name,
            'bundle_label' => $bundle_label,
            'bundle_benefit' => $bundle_benefit,
            'bundle_id' => $bundle_id,
            'product_type' => $product_type,
            'redemption_mechanism' => $redemption_mechanism,
            'lookup_bills_required' => $lookup_bills_required,
            'customer_care_number' => $customer_care_number,
            'is_range' => $is_range,
            'settings' => $settings,
            'bill_ref' => $bill_ref,
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

    private function sanitize_string_list($value) {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = explode(',', (string) $value);
        }

        $clean = [];

        foreach ($items as $item) {
            $item = sanitize_text_field((string) $item);
            if ('' !== $item) {
                $clean[] = $item;
            }
        }

        return array_values(array_unique($clean));
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

    private function validate_send_value_against_bundle($sku_code, $country_iso, $send_value) {
        $sku_code = sanitize_text_field((string) $sku_code);
        $country_iso = strtoupper(sanitize_text_field((string) $country_iso));
        $send_value = (float) $send_value;

        if ('' === $sku_code || $send_value <= 0) {
            return true;
        }

        $bundle = $this->find_bundle_for_amount_validation($sku_code, $country_iso);
        if (empty($bundle)) {
            return true;
        }

        $options = $this->api->get_options();
        $manual_amount_mode = sanitize_key((string) ($options['manual_amount_mode'] ?? 'range_products'));
        $allow_manual_amount = ($manual_amount_mode === 'range_products');

        $bundle_send_value = (float) ($bundle['send_value'] ?? 0);
        $min_send_value = isset($bundle['minimum_send_value']) ? (float) $bundle['minimum_send_value'] : $bundle_send_value;
        $max_send_value = isset($bundle['maximum_send_value']) ? (float) $bundle['maximum_send_value'] : $bundle_send_value;
        $stored_is_range = !empty($bundle['is_range']);
        $calculated_is_range = abs($max_send_value - $min_send_value) > 0.00001;
        $is_range = $allow_manual_amount && ($stored_is_range || $calculated_is_range);

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
                        'status' => 400,
                        'code' => 'amount_out_of_range',
                        'min_send_value' => $min_send_value,
                        'max_send_value' => $max_send_value,
                        'sku_code' => $sku_code,
                        'country_iso' => $country_iso,
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
                    'status' => 400,
                    'code' => 'amount_fixed_only',
                    'fixed_send_value' => $bundle_send_value,
                    'sku_code' => $sku_code,
                    'country_iso' => $country_iso,
                ]
            );
        }

        return true;
    }

    private function find_bundle_for_amount_validation($sku_code, $country_iso = '') {
        $bundles = get_option('dc_recargas_bundles', []);
        if (!is_array($bundles) || empty($bundles)) {
            return null;
        }

        $sku_code = strtoupper(sanitize_text_field((string) $sku_code));
        $country_iso = strtoupper(sanitize_text_field((string) $country_iso));
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

    private function wp_error_to_rest_response($error) {
        $error_data = $error->get_error_data();
        $status_code = 500;

        if (is_array($error_data) && isset($error_data['status']) && is_numeric($error_data['status'])) {
            $status_code = (int) $error_data['status'];
        }

        return new WP_REST_Response([
            'ok' => false,
            'message' => $error->get_error_message(),
            'error' => $error_data,
        ], $status_code);
    }

    private function normalize_transfer_record_items($items) {
        return array_values(array_map(function ($item) {
            $entry = is_array($item['TransferRecord'] ?? null) ? $item['TransferRecord'] : (is_array($item) ? $item : []);
            $transfer_id = is_array($entry['TransferId'] ?? null) ? $entry['TransferId'] : [];
            $price = is_array($entry['Price'] ?? null) ? $entry['Price'] : [];
            $receipt_params = is_array($entry['ReceiptParams'] ?? null) ? $entry['ReceiptParams'] : [];

            return [
                'TransferRef' => sanitize_text_field((string) ($transfer_id['TransferRef'] ?? '')),
                'DistributorRef' => sanitize_text_field((string) ($transfer_id['DistributorRef'] ?? '')),
                'SkuCode' => sanitize_text_field((string) ($entry['SkuCode'] ?? '')),
                'AccountNumber' => sanitize_text_field((string) ($entry['AccountNumber'] ?? '')),
                'ProcessingState' => sanitize_text_field((string) ($entry['ProcessingState'] ?? '')),
                'ReceiptText' => sanitize_text_field((string) ($entry['ReceiptText'] ?? '')),
                'ReceiptParams' => $receipt_params,
                'StartedUtc' => sanitize_text_field((string) ($entry['StartedUtc'] ?? '')),
                'CompletedUtc' => sanitize_text_field((string) ($entry['CompletedUtc'] ?? '')),
                'SendValue' => (float) ($price['SendValue'] ?? 0),
                'SendCurrencyIso' => sanitize_text_field((string) ($price['SendCurrencyIso'] ?? '')),
                'ReceiveValue' => (float) ($price['ReceiveValue'] ?? 0),
                'ReceiveCurrencyIso' => sanitize_text_field((string) ($price['ReceiveCurrencyIso'] ?? '')),
                'ReceiveValueExcludingTax' => (float) ($price['ReceiveValueExcludingTax'] ?? 0),
                'CustomerFee' => (float) ($price['CustomerFee'] ?? 0),
                'DistributorFee' => (float) ($price['DistributorFee'] ?? 0),
                'TaxRate' => (float) ($price['TaxRate'] ?? 0),
                'TaxName' => sanitize_text_field((string) ($price['TaxName'] ?? '')),
                'TaxCalculation' => sanitize_text_field((string) ($price['TaxCalculation'] ?? '')),
                'ResultCode' => isset($item['ResultCode']) ? (int) $item['ResultCode'] : 0,
                'ErrorCodes' => array_values((array) ($item['ErrorCodes'] ?? [])),
            ];
        }, array_filter((array) $items, 'is_array')));
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

    private function filter_bundles_by_country($country_iso, $allowed_bundle_ids = []) {
        $bundles = get_option('dc_recargas_bundles', []);
        if (!is_array($bundles)) {
            $bundles = [];
        }
        $allowed_bundle_ids = array_values(array_unique(array_filter(array_map('strval', (array) $allowed_bundle_ids))));
        $has_allowed_filter = !empty($allowed_bundle_ids);
        $allowed_map = $has_allowed_filter ? array_fill_keys($allowed_bundle_ids, true) : [];

        $active = array_values(array_filter($bundles, function ($bundle) use ($country_iso, $has_allowed_filter, $allowed_map) {
            $bundle_id = sanitize_text_field((string) ($bundle['id'] ?? ''));

            if ($has_allowed_filter) {
                if ($bundle_id === '' || !isset($allowed_map[$bundle_id])) {
                    return false;
                }
            } elseif (empty($bundle['is_active'])) {
                return false;
            }

            if (empty($country_iso)) {
                return true;
            }

            return strtoupper((string) ($bundle['country_iso'] ?? '')) === $country_iso;
        }));

        if ($has_allowed_filter) {
            $order_map = [];
            foreach ($allowed_bundle_ids as $idx => $bundle_id) {
                $order_map[$bundle_id] = (int) $idx;
            }

            usort($active, function ($left, $right) use ($order_map) {
                $left_id = sanitize_text_field((string) ($left['id'] ?? ''));
                $right_id = sanitize_text_field((string) ($right['id'] ?? ''));

                $left_order = isset($order_map[$left_id]) ? (int) $order_map[$left_id] : 99999;
                $right_order = isset($order_map[$right_id]) ? (int) $order_map[$right_id] : 99999;

                if ($left_order !== $right_order) {
                    return $left_order <=> $right_order;
                }

                return strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
            });
        }

        $options = $this->api->get_options();
        $manual_amount_mode = sanitize_key((string) ($options['manual_amount_mode'] ?? 'range_products'));
        $allow_manual_amount = ($manual_amount_mode === 'range_products');

        return array_map(function ($bundle) use ($allow_manual_amount) {
            $send_value = (float) ($bundle['send_value'] ?? 0);
            $send_currency = strtoupper(sanitize_text_field((string) ($bundle['send_currency_iso'] ?? '')));
            $public_price = (float) ($bundle['public_price'] ?? $send_value);
            $public_currency = strtoupper(sanitize_text_field((string) ($bundle['public_price_currency'] ?? $send_currency)));

            $minimum_send_value = isset($bundle['minimum_send_value']) ? (float) $bundle['minimum_send_value'] : $send_value;
            $maximum_send_value = isset($bundle['maximum_send_value']) ? (float) $bundle['maximum_send_value'] : $send_value;
            $minimum_receive_value = isset($bundle['minimum_receive_value']) ? (float) $bundle['minimum_receive_value'] : $public_price;
            $maximum_receive_value = isset($bundle['maximum_receive_value']) ? (float) $bundle['maximum_receive_value'] : $public_price;

            $stored_is_range = !empty($bundle['is_range']);
            $calculated_is_range = abs($maximum_send_value - $minimum_send_value) > 0.00001 || abs($maximum_receive_value - $minimum_receive_value) > 0.00001;
            $is_range = $allow_manual_amount && ($stored_is_range || $calculated_is_range);

            if (!$is_range) {
                $minimum_send_value = $send_value;
                $maximum_send_value = $send_value;
                $minimum_receive_value = $public_price;
                $maximum_receive_value = $public_price;
            }

            $benefits = [];
            foreach ((array) ($bundle['benefits'] ?? []) as $benefit) {
                $clean_benefit = sanitize_text_field((string) $benefit);
                if ($clean_benefit !== '') {
                    $benefits[] = $clean_benefit;
                }
            }

            $setting_definitions = [];
            foreach ((array) ($bundle['setting_definitions'] ?? []) as $definition) {
                if (!is_array($definition)) {
                    continue;
                }

                $name = sanitize_text_field((string) ($definition['Name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $allowed_values = [];
                foreach ((array) ($definition['AllowedValues'] ?? []) as $allowed_value) {
                    $clean_allowed = sanitize_text_field((string) $allowed_value);
                    if ($clean_allowed !== '') {
                        $allowed_values[] = $clean_allowed;
                    }
                }

                $setting_definitions[] = [
                    'Name' => $name,
                    'Description' => sanitize_text_field((string) ($definition['Description'] ?? '')),
                    'IsMandatory' => !empty($definition['IsMandatory']),
                    'Type' => sanitize_text_field((string) ($definition['Type'] ?? 'text')),
                    'ValidationRegex' => sanitize_text_field((string) ($definition['ValidationRegex'] ?? '')),
                    'MinLength' => isset($definition['MinLength']) ? (int) $definition['MinLength'] : 0,
                    'MaxLength' => isset($definition['MaxLength']) ? (int) $definition['MaxLength'] : 0,
                    'MinValue' => isset($definition['MinValue']) ? (float) $definition['MinValue'] : 0,
                    'MaxValue' => isset($definition['MaxValue']) ? (float) $definition['MaxValue'] : 0,
                    'AllowedValues' => array_values(array_unique($allowed_values)),
                ];
            }

            return [
                'BundleId' => $bundle['id'] ?? '',
                'SkuCode' => $bundle['sku_code'] ?? '',
                'ProviderCode' => sanitize_text_field((string) ($bundle['provider_code'] ?? '')),
                'ProviderName' => $bundle['provider_name'] ?? '',
                'ProductType' => sanitize_text_field((string) ($bundle['product_type_raw'] ?? '')),
                'SendValue' => $send_value,
                'SendCurrencyIso' => $send_currency,
                'ReceiveValue' => $public_price,
                'ReceiveCurrencyIso' => $public_currency,
                // Para bundles guardados usamos el precio comercial como referencia canónica
                // de frontend y evitamos arrastrar valores heredados en otra escala/moneda.
                'ReceiveValueExcludingTax' => $public_price,
                'MinimumSendValue' => $minimum_send_value,
                'MaximumSendValue' => $maximum_send_value,
                'MinimumReceiveValue' => $minimum_receive_value,
                'MaximumReceiveValue' => $maximum_receive_value,
                'CustomerFee' => 0.0,
                'DistributorFee' => 0.0,
                'TaxRate' => 0.0,
                'TaxName' => sanitize_text_field((string) ($bundle['tax_name'] ?? '')),
                'TaxCalculation' => sanitize_text_field((string) ($bundle['tax_calculation'] ?? '')),
                'DefaultDisplayText' => sanitize_text_field((string) ($bundle['default_display_text'] ?? ($bundle['label'] ?? ''))),
                'DisplayText' => sanitize_text_field((string) ($bundle['display_text'] ?? ($bundle['label'] ?? ''))),
                'Description' => $bundle['description'] ?? '',
                'DescriptionMarkdown' => sanitize_text_field((string) ($bundle['description_markdown'] ?? '')),
                'ReadMoreMarkdown' => sanitize_text_field((string) ($bundle['read_more_markdown'] ?? '')),
                'AdditionalInformation' => sanitize_text_field((string) ($bundle['additional_information'] ?? ($bundle['description'] ?? ''))),
                'CountryIso' => strtoupper(sanitize_text_field((string) ($bundle['country_iso'] ?? ''))),
                'RegionCode' => sanitize_text_field((string) ($bundle['region_code'] ?? '')),
                'RegionCodes' => array_values(array_filter(array_map('sanitize_text_field', (array) ($bundle['region_codes'] ?? [])))),
                'ValidationRegex' => sanitize_text_field((string) ($bundle['validation_regex'] ?? '')),
                'CustomerCareNumber' => sanitize_text_field((string) ($bundle['customer_care_number'] ?? '')),
                'LogoUrl' => esc_url_raw((string) ($bundle['logo_url'] ?? '')),
                'IsPromotion' => !empty($bundle['is_promotion']),
                'IsRange' => $is_range,
                'Benefits' => $benefits,
                'ValidityPeriodIso' => sanitize_text_field((string) ($bundle['validity_raw'] ?? '')),
                'RedemptionMechanism' => sanitize_text_field((string) ($bundle['redemption_mechanism'] ?? 'Immediate')),
                'ProcessingMode' => sanitize_text_field((string) ($bundle['processing_mode'] ?? 'Instant')),
                'LookupBillsRequired' => !empty($bundle['lookup_bills_required']),
                'SettingDefinitions' => $setting_definitions,
                'PaymentTypes' => array_values(array_filter(array_map('sanitize_text_field', (array) ($bundle['payment_types'] ?? [])))),
                'UatNumber' => sanitize_text_field((string) ($bundle['uat_number'] ?? '')),
            ];
        }, $active);
    }

    private function parse_bundle_ids($raw_ids) {
        $parts = array_map('trim', explode(',', (string) $raw_ids));
        $clean = [];

        foreach ($parts as $id) {
            $id = sanitize_text_field($id);
            if ($id !== '') {
                $clean[] = $id;
            }
        }

        return array_values(array_unique($clean));
    }

    private function resolve_public_price_for_cart($bundle_id, $sku_code, $country_iso, $incoming_public_price) {
        $incoming = (float) $incoming_public_price;

        $options = $this->api->get_options();
        $bundles = (array) ($options['bundles'] ?? []);
        if (empty($bundles)) {
            return $incoming;
        }

        $bundle_id = sanitize_text_field((string) $bundle_id);
        $sku_code = sanitize_text_field((string) $sku_code);
        $country_iso = strtoupper(sanitize_text_field((string) $country_iso));

        $matched = null;
        if ($bundle_id !== '') {
            foreach ($bundles as $bundle) {
                if (!is_array($bundle)) {
                    continue;
                }

                $candidate_id = sanitize_text_field((string) ($bundle['id'] ?? ''));
                if ($candidate_id !== '' && $candidate_id === $bundle_id) {
                    $matched = $bundle;
                    break;
                }
            }
        }

        if ($matched === null) {
            foreach ($bundles as $bundle) {
                if (!is_array($bundle)) {
                    continue;
                }

                $candidate_sku = sanitize_text_field((string) ($bundle['sku_code'] ?? ''));
                if ($candidate_sku === '' || $candidate_sku !== $sku_code) {
                    continue;
                }

                $candidate_country = strtoupper(sanitize_text_field((string) ($bundle['country_iso'] ?? '')));
                if ($country_iso !== '' && $candidate_country !== '' && $candidate_country !== $country_iso) {
                    continue;
                }

                $matched = $bundle;
                break;
            }
        }

        if (!is_array($matched)) {
            return $incoming;
        }

        $stored_public = (float) ($matched['public_price'] ?? 0);
        if ($stored_public > 0) {
            return $stored_public;
        }

        return $incoming;
    }

    private function normalize_products_for_frontend($items, $country_iso, $query_context = []) {
        if (empty($items) || !is_array($items)) {
            return [];
        }

        $provider_map = $this->get_provider_details_map($items, $country_iso);
        $description_map = $this->get_product_description_map($items);
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
            $provider_details = $provider_map[$provider_code] ?? [];
            $provider_name = sanitize_text_field($item['ProviderName'] ?? ($provider_details['ProviderName'] ?? $provider_code));
            $price = $this->extract_product_price($item);
            $localization_key = sanitize_text_field((string) ($item['LocalizationKey'] ?? ''));
            $description_details = $description_map[$localization_key] ?? [];

            $normalized_item = [
                'ProviderCode' => $provider_code,
                'ProviderName' => $provider_name,
                'SkuCode' => $sku_code,
                'ProductType' => sanitize_text_field($item['ProductType'] ?? ''),
                'CountryIso' => sanitize_text_field((string) ($provider_details['CountryIso'] ?? $country_iso)),
                'RegionCode' => sanitize_text_field((string) ($item['RegionCode'] ?? '')),
                'RegionCodes' => array_values(array_filter(array_map('sanitize_text_field', (array) ($provider_details['RegionCodes'] ?? [])))),
                'SendValue' => $price['SendValue'],
                'SendCurrencyIso' => $price['SendCurrencyIso'],
                'ReceiveValue' => $price['ReceiveValue'],
                'ReceiveCurrencyIso' => $price['ReceiveCurrencyIso'],
                'ReceiveValueExcludingTax' => $price['ReceiveValueExcludingTax'],
                'MinimumSendValue' => $price['MinimumSendValue'],
                'MaximumSendValue' => $price['MaximumSendValue'],
                'MinimumReceiveValue' => $price['MinimumReceiveValue'],
                'MaximumReceiveValue' => $price['MaximumReceiveValue'],
                'CustomerFee' => $price['CustomerFee'],
                'DistributorFee' => $price['DistributorFee'],
                'TaxRate' => $price['TaxRate'],
                'TaxName' => $price['TaxName'],
                'TaxCalculation' => $price['TaxCalculation'],
                'DefaultDisplayText' => sanitize_text_field($item['DefaultDisplayText'] ?? ($description_details['DisplayText'] ?? $sku_code)),
                'DisplayText' => sanitize_text_field((string) ($description_details['DisplayText'] ?? ($item['DefaultDisplayText'] ?? $sku_code))),
                'Description' => $this->build_product_description($item, $description_details),
                'DescriptionMarkdown' => sanitize_text_field((string) ($description_details['DescriptionMarkdown'] ?? '')),
                'ReadMoreMarkdown' => sanitize_text_field((string) ($description_details['ReadMoreMarkdown'] ?? '')),
                'AdditionalInformation' => sanitize_text_field((string) ($item['AdditionalInformation'] ?? '')),
                'IsPromotion' => !empty($item['IsPromotion']),
                'IsRange' => $this->is_range_product($item),
                'Benefits' => array_values(array_filter(array_map('sanitize_text_field', (array) ($item['Benefits'] ?? [])))),
                'ValidityPeriodIso' => sanitize_text_field($item['ValidityPeriodIso'] ?? ''),
                'RedemptionMechanism' => sanitize_text_field($item['RedemptionMechanism'] ?? ''),
                'ProcessingMode' => sanitize_text_field((string) ($item['ProcessingMode'] ?? '')),
                'LookupBillsRequired' => !empty($item['LookupBillsRequired']),
                'SettingDefinitions' => $this->normalize_setting_definitions($item['SettingDefinitions'] ?? []),
                'ValidationRegex' => sanitize_text_field((string) ($provider_details['ValidationRegex'] ?? '')),
                'CustomerCareNumber' => sanitize_text_field((string) ($provider_details['CustomerCareNumber'] ?? '')),
                'LogoUrl' => esc_url_raw((string) ($provider_details['LogoUrl'] ?? '')),
                'PaymentTypes' => array_values(array_filter(array_map('sanitize_text_field', (array) ($item['PaymentTypes'] ?? ($provider_details['PaymentTypes'] ?? []))))),
                'UatNumber' => sanitize_text_field((string) ($item['UatNumber'] ?? '')),
            ];

            if (!$this->product_matches_query_context($normalized_item, $query_context)) {
                continue;
            }

            $normalized[] = $normalized_item;
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

    private function get_provider_details_map($items, $country_iso) {
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
            $map[$provider_code] = [
                'ProviderName' => $provider_name,
                'CountryIso' => sanitize_text_field((string) ($provider['CountryIso'] ?? '')),
                'ValidationRegex' => sanitize_text_field((string) ($provider['ValidationRegex'] ?? '')),
                'CustomerCareNumber' => sanitize_text_field((string) ($provider['CustomerCareNumber'] ?? '')),
                'RegionCodes' => array_values(array_filter(array_map('sanitize_text_field', (array) ($provider['RegionCodes'] ?? [])))),
                'PaymentTypes' => array_values(array_filter(array_map('sanitize_text_field', (array) ($provider['PaymentTypes'] ?? [])))),
                'LogoUrl' => esc_url_raw((string) ($provider['LogoUrl'] ?? '')),
            ];
        }

        return $map;
    }

    private function get_product_description_map($items) {
        $sku_codes = [];

        foreach ((array) $items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $sku_code = sanitize_text_field((string) ($item['SkuCode'] ?? ''));
            if ('' !== $sku_code) {
                $sku_codes[] = $sku_code;
            }
        }

        $sku_codes = array_values(array_unique($sku_codes));
        if (empty($sku_codes)) {
            return [];
        }

        $preferred_languages = $this->get_preferred_language_codes();
        $response = $this->api->get_product_descriptions($sku_codes, $preferred_languages);
        if (is_wp_error($response)) {
            return [];
        }

        $descriptions = $response['Result'] ?? $response['Items'] ?? [];
        $map = [];

        foreach ((array) $descriptions as $description) {
            if (!is_array($description)) {
                continue;
            }

            $localization_key = sanitize_text_field((string) ($description['LocalizationKey'] ?? ''));
            if ('' === $localization_key) {
                continue;
            }

            $language_code = strtolower((string) ($description['LanguageCode'] ?? ''));
            $priority = array_search(substr($language_code, 0, 2), $preferred_languages, true);
            $priority = false === $priority ? 999 : (int) $priority;

            if (isset($map[$localization_key]) && $priority >= $map[$localization_key]['_priority']) {
                continue;
            }

            $map[$localization_key] = [
                '_priority' => $priority,
                'DisplayText' => sanitize_text_field((string) ($description['DisplayText'] ?? '')),
                'DescriptionMarkdown' => sanitize_text_field((string) ($description['DescriptionMarkdown'] ?? '')),
                'ReadMoreMarkdown' => sanitize_text_field((string) ($description['ReadMoreMarkdown'] ?? '')),
            ];
        }

        foreach ($map as $key => $value) {
            unset($map[$key]['_priority']);
        }

        return $map;
    }

    private function extract_product_price($item) {
        $minimum = is_array($item['Minimum'] ?? null) ? $item['Minimum'] : [];
        $maximum = is_array($item['Maximum'] ?? null) ? $item['Maximum'] : [];
        $price = !empty($minimum) ? $minimum : $maximum;

        $send_currency = sanitize_text_field($item['SendCurrencyIso'] ?? ($price['SendCurrencyIso'] ?? ''));
        if ('' === $send_currency) {
            error_log('[DingConnect] extract_product_price: SendCurrencyIso ausente en producto SKU=' . ($item['SkuCode'] ?? 'desconocido'));
        }

        return [
            'SendValue' => (float) ($item['SendValue'] ?? ($price['SendValue'] ?? 0)),
            'SendCurrencyIso' => $send_currency,
            'ReceiveValue' => (float) ($item['ReceiveValue'] ?? ($price['ReceiveValue'] ?? 0)),
            'ReceiveCurrencyIso' => sanitize_text_field($item['ReceiveCurrencyIso'] ?? ($price['ReceiveCurrencyIso'] ?? '')),
            'ReceiveValueExcludingTax' => (float) ($item['ReceiveValueExcludingTax'] ?? ($price['ReceiveValueExcludingTax'] ?? 0)),
            'MinimumSendValue' => (float) ($minimum['SendValue'] ?? ($item['SendValue'] ?? ($price['SendValue'] ?? 0))),
            'MaximumSendValue' => (float) ($maximum['SendValue'] ?? ($item['SendValue'] ?? ($price['SendValue'] ?? 0))),
            'MinimumReceiveValue' => (float) ($minimum['ReceiveValue'] ?? ($item['ReceiveValue'] ?? ($price['ReceiveValue'] ?? 0))),
            'MaximumReceiveValue' => (float) ($maximum['ReceiveValue'] ?? ($item['ReceiveValue'] ?? ($price['ReceiveValue'] ?? 0))),
            'CustomerFee' => (float) ($item['CustomerFee'] ?? ($price['CustomerFee'] ?? 0)),
            'DistributorFee' => (float) ($item['DistributorFee'] ?? ($price['DistributorFee'] ?? 0)),
            'TaxRate' => (float) ($item['TaxRate'] ?? ($price['TaxRate'] ?? 0)),
            'TaxName' => sanitize_text_field((string) ($item['TaxName'] ?? ($price['TaxName'] ?? ''))),
            'TaxCalculation' => sanitize_text_field((string) ($item['TaxCalculation'] ?? ($price['TaxCalculation'] ?? ''))),
        ];
    }

    private function build_product_description($item, $description_details = []) {
        $description = sanitize_text_field($item['Description'] ?? '');
        if ('' !== $description) {
            return $description;
        }

        $additional = sanitize_text_field($item['AdditionalInformation'] ?? '');
        if ('' !== $additional) {
            return $additional;
        }

        $description_markdown = sanitize_text_field((string) ($description_details['DescriptionMarkdown'] ?? ''));
        if ('' !== $description_markdown) {
            return $description_markdown;
        }

        $benefits = array_values(array_filter(array_map('sanitize_text_field', (array) ($item['Benefits'] ?? []))));
        return implode(' · ', array_slice($benefits, 0, 3));
    }

    private function normalize_setting_definitions($definitions) {
        $normalized = [];

        foreach ((array) $definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $name = sanitize_text_field((string) ($definition['Name'] ?? ''));
            if ('' === $name) {
                continue;
            }

            $normalized[] = [
                'Name' => $name,
                'Description' => sanitize_text_field((string) ($definition['Description'] ?? '')),
                'IsMandatory' => !empty($definition['IsMandatory']),
                'Type' => sanitize_text_field((string) ($definition['Type'] ?? $definition['DataType'] ?? 'text')),
                'ValidationRegex' => sanitize_text_field((string) ($definition['ValidationRegex'] ?? '')),
                'MinLength' => isset($definition['MinLength']) ? (int) $definition['MinLength'] : 0,
                'MaxLength' => isset($definition['MaxLength']) ? (int) $definition['MaxLength'] : 0,
                'MinValue' => isset($definition['MinValue']) ? (float) $definition['MinValue'] : 0,
                'MaxValue' => isset($definition['MaxValue']) ? (float) $definition['MaxValue'] : 0,
                'AllowedValues' => array_values(array_filter(array_map(function ($value) {
                    return sanitize_text_field((string) $value);
                }, (array) ($definition['AllowedValues'] ?? $definition['Values'] ?? [])), function ($value) {
                    return $value !== '';
                })),
            ];
        }

        return $normalized;
    }

    private function product_matches_query_context($item, $query_context) {
        $query_context = is_array($query_context) ? $query_context : [];

        if (!empty($query_context['provider_code']) && strcasecmp((string) ($item['ProviderCode'] ?? ''), (string) $query_context['provider_code']) !== 0) {
            return false;
        }

        if (!empty($query_context['region_code']) && strcasecmp((string) ($item['RegionCode'] ?? ''), (string) $query_context['region_code']) !== 0) {
            $regions = array_map('strtoupper', (array) ($item['RegionCodes'] ?? []));
            if (!in_array(strtoupper((string) $query_context['region_code']), $regions, true)) {
                return false;
            }
        }

        if (!empty($query_context['benefit'])) {
            $benefits = array_map('strtoupper', (array) ($item['Benefits'] ?? []));
            if (!in_array(strtoupper((string) $query_context['benefit']), $benefits, true)) {
                return false;
            }
        }

        if (!empty($query_context['redemption_mechanism']) && strcasecmp((string) ($item['RedemptionMechanism'] ?? ''), (string) $query_context['redemption_mechanism']) !== 0) {
            return false;
        }

        if (!empty($query_context['sku_code']) && strcasecmp((string) ($item['SkuCode'] ?? ''), (string) $query_context['sku_code']) !== 0) {
            return false;
        }

        return true;
    }

    private function get_preferred_language_codes() {
        $locale = strtolower((string) get_option('WPLANG', 'en'));
        $language = substr($locale, 0, 2);
        $codes = ['en'];

        if ($language !== '' && $language !== 'en') {
            array_unshift($codes, $language);
        }

        return array_values(array_unique($codes));
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

            if ((empty($merged[$sku_code]['ProductType']) && !empty($item['ProductType'])) || (!isset($merged[$sku_code]['ProductType']) && isset($item['ProductType']))) {
                $merged[$sku_code]['ProductType'] = $item['ProductType'];
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
