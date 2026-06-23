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
            'permission_callback' => [$this, 'can_manage_options'],
        ]);

        register_rest_route('dingconnect/v1', '/landing-config', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'landing_config'],
            'permission_callback' => [$this, 'can_manage_options'],
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

        register_rest_route('dingconnect/v1', '/precheck', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'precheck'],
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

        register_rest_route('dingconnect/v1', '/webhook', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'webhook'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('dingconnect/v1', '/order-voucher-status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'order_voucher_status'],
            'permission_callback' => '__return_true',
            'args' => [
                'order_id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
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

    public function order_voucher_status(WP_REST_Request $request) {
        $order_id = (int) $request->get_param('order_id');
        if ($order_id < 1) {
            return new WP_REST_Response(['ok' => false, 'message' => 'ID de pedido no válido.'], 400);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Pedido no encontrado.'], 404);
        }

        $has_dc = false;
        $all_terminal = true;

        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_dc_recarga') !== 'yes') {
                continue;
            }

            $has_dc = true;
            $status = strtolower((string) $item->get_meta('_dc_transfer_status'));

            if ($status === '' || $status === 'not_started') {
                $all_terminal = false;
                break;
            }

            $is_success_status = $this->api->is_successful_transfer_status($status);
            $is_pending_status = $this->api->is_pending_transfer_status($status);

            if ($is_pending_status) {
                $all_terminal = false;
                break;
            }

            if ($is_success_status && !$this->api->is_confirmed_transfer_reference((string) $item->get_meta('_dc_transfer_ref'))) {
                $all_terminal = false;
                break;
            }
        }

        if (!$has_dc) {
            return rest_ensure_response(['ok' => true, 'terminal' => true]);
        }

        return rest_ensure_response(['ok' => true, 'terminal' => $all_terminal]);
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

    public function precheck(WP_REST_Request $request) {
        if (!$this->check_rate_limit('precheck', 8)) {
            return $this->precheck_error('RATE_LIMIT', 'Hemos alcanzado el límite de solicitudes. Intenta en unos segundos.', 429, true);
        }

        $params = $request->get_json_params();
        $params = is_array($params) ? $params : [];
        $payload = $this->normalize_recharge_payload($params);

        if ($payload['account_number'] === '' || $payload['country_iso'] === '' || $payload['sku_code'] === '' || $payload['send_value'] <= 0) {
            return $this->precheck_error('MISSING_FIELDS', 'Datos incompletos para validar la recarga.', 400, false);
        }

        if (strlen($payload['account_number']) < 8) {
            return $this->precheck_error('NUMBER_INVALID', 'El número introducido no es válido o no admite recargas. Verifica el número e inténtalo de nuevo.', 400, false);
        }

        $matched_bundle = $this->find_saved_bundle_for_cart($payload['bundle_id'], $payload['sku_code'], $payload['country_iso']);
        $landing_validation = $this->validate_bundle_for_landing($payload['landing_key'], $payload['bundle_id'], $payload['allowed_bundle_ids']);
        if (is_wp_error($landing_validation)) {
            return $this->precheck_wp_error_response($landing_validation, 'PRODUCT_NOT_AVAILABLE');
        }

        $amount_validation = $this->validate_send_value_against_bundle($payload['sku_code'], $payload['country_iso'], $payload['send_value'], $payload['bundle_id']);
        if (is_wp_error($amount_validation)) {
            return $this->precheck_error('AMOUNT_NOT_ALLOWED', $amount_validation->get_error_message(), 400, false, $amount_validation->get_error_data());
        }

        $lookup = $this->with_retries(function () use ($payload) {
            return $this->api->get_account_lookup($payload['account_number']);
        });
        if (is_wp_error($lookup)) {
            $this->api->log_operational_event('precheck_lookup_unavailable', [
                'status' => 'warning',
                'account_number' => $payload['account_number'],
                'sku_code' => $payload['sku_code'],
                'send_value' => $payload['send_value'],
                'currency' => $payload['send_currency_iso'],
                'raw_response' => [
                    'reason' => 'lookup_error_continue_to_validate_only',
                    'error' => $lookup->get_error_message(),
                    'error_data' => $lookup->get_error_data(),
                ],
            ]);
            $lookup = [];
        }

        $lookup_context = $this->extract_lookup_context($lookup);
        if (empty($lookup_context['items']) && $lookup_context['country_iso'] === '' && $lookup_context['provider_code'] === '') {
            $this->api->log_operational_event('precheck_lookup_empty', [
                'status' => 'warning',
                'account_number' => $payload['account_number'],
                'sku_code' => $payload['sku_code'],
                'send_value' => $payload['send_value'],
                'currency' => $payload['send_currency_iso'],
                'raw_response' => [
                    'reason' => 'lookup_empty_continue_to_validate_only',
                ],
            ]);
        }

        if ($lookup_context['country_iso'] !== '' && $payload['country_iso'] !== '' && $lookup_context['country_iso'] !== $payload['country_iso']) {
            $this->api->log_operational_event('precheck_lookup_country_mismatch', [
                'status' => 'warning',
                'account_number' => $payload['account_number'],
                'sku_code' => $payload['sku_code'],
                'send_value' => $payload['send_value'],
                'currency' => $payload['send_currency_iso'],
                'raw_response' => [
                    'reason' => 'country_mismatch_continue_to_validate_only',
                    'lookup_country_iso' => $lookup_context['country_iso'],
                    'selected_country_iso' => $payload['country_iso'],
                ],
            ]);
        }

        $product_response = $this->with_retries(function () use ($payload, $lookup_context) {
            return $this->api->get_products_catalog([
                'account_number' => $payload['account_number'],
                'country_isos' => [$payload['country_iso']],
                'provider_codes' => $lookup_context['provider_code'] !== '' ? [$lookup_context['provider_code']] : [],
                'sku_codes' => [$payload['sku_code']],
                'take' => 250,
            ]);
        });
        $product_items = [];
        if (is_wp_error($product_response)) {
            $this->api->log_operational_event('precheck_products_unavailable', [
                'status' => 'warning',
                'account_number' => $payload['account_number'],
                'sku_code' => $payload['sku_code'],
                'send_value' => $payload['send_value'],
                'currency' => $payload['send_currency_iso'],
                'raw_response' => [
                    'reason' => 'products_error_continue_with_saved_bundle',
                    'error' => $product_response->get_error_message(),
                    'error_data' => $product_response->get_error_data(),
                ],
            ]);
        } else {
            $product_items = $this->extract_response_items($product_response);
        }

        if (empty($product_items)) {
            $fallback_response = $this->with_retries(function () use ($payload) {
                return $this->api->get_products_catalog([
                    'country_isos' => [$payload['country_iso']],
                    'sku_codes' => [$payload['sku_code']],
                    'take' => 250,
                ]);
            }, 2);
            if (!is_wp_error($fallback_response)) {
                $product_items = $this->extract_response_items($fallback_response);
            }
        }

        $product = $this->find_product_by_sku($product_items, $payload['sku_code']);
        if (!$product && is_array($matched_bundle)) {
            $product = $this->build_product_from_saved_bundle_for_precheck($matched_bundle, $payload);
            $this->api->log_operational_event('precheck_saved_bundle_fallback', [
                'status' => 'warning',
                'account_number' => $payload['account_number'],
                'sku_code' => $payload['sku_code'],
                'send_value' => $payload['send_value'],
                'currency' => $payload['send_currency_iso'],
                'raw_response' => [
                    'reason' => 'catalog_missing_continue_to_validate_only',
                    'bundle_id' => $payload['bundle_id'],
                ],
            ]);
        }
        if ($product) {
            $product = $this->enrich_product_for_precheck($product, $payload['country_iso']);
        }
        if (!$product) {
            return $this->precheck_error('PRODUCT_NOT_AVAILABLE', 'Este paquete ya no está disponible para el número indicado. Selecciona otro paquete o número.', 400, false, [
                'requested_sku' => $payload['sku_code'],
            ]);
        }

        if (!$this->product_matches_lookup($product, $lookup_context, $matched_bundle)) {
            $this->api->log_operational_event('precheck_lookup_product_mismatch', [
                'status' => 'warning',
                'account_number' => $payload['account_number'],
                'sku_code' => $payload['sku_code'],
                'send_value' => $payload['send_value'],
                'currency' => $payload['send_currency_iso'],
                'raw_response' => [
                    'reason' => 'mismatch_continue_to_validate_only',
                    'provider_code' => sanitize_text_field((string) ($product['ProviderCode'] ?? '')),
                    'lookup_provider_code' => $lookup_context['provider_code'],
                ],
            ]);
        }

        $account_validation = $this->validate_account_number_against_product($payload, $product, $matched_bundle);
        if (is_wp_error($account_validation)) {
            $this->api->log_operational_event('precheck_account_regex_failed', [
                'status' => 'error',
                'account_number' => $payload['account_number'],
                'sku_code' => $payload['sku_code'],
                'send_value' => $payload['send_value'],
                'currency' => $payload['send_currency_iso'],
                'raw_response' => $account_validation->get_error_data(),
            ]);

            return $this->precheck_wp_error_response($account_validation, 'AccountNumberInvalid');
        }

        $product_amount_validation = $this->validate_amount_against_product($product, $payload['send_value']);
        if (is_wp_error($product_amount_validation)) {
            return $this->precheck_error('AMOUNT_NOT_ALLOWED', $product_amount_validation->get_error_message(), 400, false, $product_amount_validation->get_error_data());
        }

        $settings_validation = $this->validate_settings_for_precheck($payload['settings'], $product, $matched_bundle);
        if (is_wp_error($settings_validation)) {
            return $this->precheck_wp_error_response($settings_validation, 'SettingRequired');
        }

        $lookup_bills_required = !empty($product['LookupBillsRequired']) || (is_array($matched_bundle) && !empty($matched_bundle['lookup_bills_required']));
        if ($lookup_bills_required && $payload['bill_ref'] === '') {
            return $this->precheck_error('LookupBillsRequired', 'Debes consultar y seleccionar la factura antes de continuar.', 400, false);
        }

        $balance_validation = $this->validate_seller_balance_for_precheck($payload['send_value'], $payload['send_currency_iso']);
        if (is_wp_error($balance_validation)) {
            $this->api->log_operational_event('precheck_insufficient_seller_balance', [
                'status' => 'error',
                'account_number' => $payload['account_number'],
                'sku_code' => $payload['sku_code'],
                'send_value' => $payload['send_value'],
                'currency' => $payload['send_currency_iso'],
                'raw_response' => $balance_validation->get_error_data(),
            ]);

            return $this->wp_error_to_rest_response($balance_validation);
        }

        $distributor_ref = 'PRECHECK-' . gmdate('YmdHis') . '-' . strtoupper(wp_generate_password(8, false, false));
        $validate = $this->with_retries(function () use ($payload, $distributor_ref) {
            return $this->api->send_transfer([
                'DistributorRef' => $distributor_ref,
                'AccountNumber' => $payload['account_number'],
                'SkuCode' => $payload['sku_code'],
                'SendValue' => $payload['send_value'],
                'SendCurrencyIso' => $payload['send_currency_iso'],
                'Settings' => $payload['settings'],
                'BillRef' => $payload['bill_ref'],
                'ValidateOnly' => true,
            ]);
        });

        $this->api->log_transfer($payload['account_number'], $payload['sku_code'], $payload['send_value'], $payload['send_currency_iso'], $distributor_ref, $validate);
        if (is_wp_error($validate)) {
            return $this->precheck_wp_error_response($validate, 'VALIDATE_ONLY_FAILED');
        }

        if (!$this->is_ding_success($validate)) {
            $code = $this->extract_first_error_code($validate);
            if ($code === '') {
                $code = 'PARTIAL_RESPONSE';
            }

            return $this->precheck_error($code, $this->user_message_for_code($code), $this->is_retryable_code($code) ? 503 : 400, $this->is_retryable_code($code), [
                'raw_response' => $validate,
            ]);
        }

        $token = 'dc_precheck_' . wp_generate_password(32, false, false);
        set_transient($token, [
            'fingerprint' => $this->build_precheck_fingerprint($payload),
            'account_number' => $payload['account_number'],
            'country_iso' => $payload['country_iso'],
            'sku_code' => $payload['sku_code'],
            'bundle_id' => $payload['bundle_id'],
            'send_value' => $payload['send_value'],
            'send_currency_iso' => $payload['send_currency_iso'],
            'provider_code' => $lookup_context['provider_code'],
            'created_at' => time(),
        ], 5 * MINUTE_IN_SECONDS);

        $this->api->log_operational_event('precheck_validated', [
            'status' => 'success',
            'account_number' => $payload['account_number'],
            'sku_code' => $payload['sku_code'],
            'send_value' => $payload['send_value'],
            'currency' => $payload['send_currency_iso'],
            'distributor_ref' => $distributor_ref,
            'raw_response' => [
                'provider_code' => $lookup_context['provider_code'],
                'country_iso' => $payload['country_iso'],
                'bundle_id' => $payload['bundle_id'],
            ],
        ]);

        return rest_ensure_response([
            'ok' => true,
            'code' => 'PRECHECK_OK',
            'message' => 'La recarga fue validada correctamente.',
            'precheck_token' => $token,
            'expires_in' => 300,
            'normalized' => [
                'account_number' => $payload['account_number'],
                'country_iso' => $payload['country_iso'],
                'provider_code' => $lookup_context['provider_code'],
                'sku_code' => $payload['sku_code'],
                'send_value' => $payload['send_value'],
                'send_currency_iso' => $payload['send_currency_iso'],
            ],
        ]);
    }

    public function transfer(WP_REST_Request $request) {
        if (!$this->check_rate_limit('transfer', 5)) {
            return new WP_REST_Response(['ok' => false, 'message' => 'Demasiadas solicitudes. Intenta en un minuto.'], 429);
        }

        $options = $this->api->get_options();
        $payment_mode = sanitize_text_field((string) ($options['payment_mode'] ?? 'direct'));
        if ($payment_mode === 'woocommerce') {
            $this->api->log_operational_event('transfer_blocked_payment_mode', [
                'status' => 'error',
                'account_number' => $this->sanitize_phone($request->get_param('account_number') ?? ''),
                'sku_code' => sanitize_text_field((string) ($request->get_param('sku_code') ?? '')),
                'send_value' => (float) ($request->get_param('send_value') ?? 0),
                'currency' => strtoupper(sanitize_text_field((string) ($request->get_param('send_currency_iso') ?? ''))),
                'raw_response' => ['reason' => 'payment_mode_woocommerce'],
            ]);
            return new WP_REST_Response([
                'ok' => false,
                'message' => 'Transferencia directa deshabilitada en modo WooCommerce. Usa add-to-cart y completa el pago en checkout.',
            ], 403);
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

        $amount_validation = $this->validate_send_value_against_bundle(
            $payload['SkuCode'],
            $country_iso,
            (float) $payload['SendValue'],
            sanitize_text_field((string) ($params['bundle_id'] ?? ''))
        );
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

        $precheck_validation = $this->validate_precheck_token($params);
        if (is_wp_error($precheck_validation)) {
            return $this->wp_error_to_rest_response($precheck_validation);
        }

        $amount_validation = $this->validate_send_value_against_bundle($sku_code, $country_iso, $send_value, $bundle_id);
        if (is_wp_error($amount_validation)) {
            return $this->wp_error_to_rest_response($amount_validation);
        }

        $matched_bundle = $this->find_saved_bundle_for_cart($bundle_id, $sku_code, $country_iso);
        if (is_array($matched_bundle)) {
            $resolved_benefit = $this->extract_bundle_benefit_for_checkout($matched_bundle);
            if ($bundle_benefit === '' && $resolved_benefit !== '') {
                $bundle_benefit = $resolved_benefit;
            }

            if ($bundle_label === '') {
                $bundle_label = sanitize_text_field((string) ($matched_bundle['label'] ?? ''));
            }

            $stored_public_currency = strtoupper(sanitize_text_field((string) ($matched_bundle['public_price_currency'] ?? '')));
            if ($stored_public_currency !== '') {
                $public_price_currency = $stored_public_currency;
            }
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

        $this->consume_precheck_token($params);

        return rest_ensure_response([
            'ok' => true,
            'redirect' => wc_get_checkout_url(),
            'message' => 'Recarga añadida al carrito.',
        ]);
    }

    public function webhook(WP_REST_Request $request) {
        $options = $this->api->get_options();
        if (empty($options['webhook_enabled'])) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => 'No encontrado.',
            ], 404);
        }

        $signature = (string) $request->get_header('x-ding-webhook-signature');
        $timestamp = (string) $request->get_header('x-ding-webhook-timestamp');
        $algorithm = (string) $request->get_header('x-ding-webhook-algorithm');
        $kid = (string) $request->get_header('x-ding-webhook-key-id');
        $raw_body = (string) $request->get_body();

        $verification = $this->api->verify_webhook_signature($raw_body, $signature, $timestamp, $algorithm, $kid);
        if (empty($verification['ok'])) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => (string) ($verification['message'] ?? 'Firma inválida.'),
            ], 401);
        }

        $seen_key = 'dc_webhook_seen_' . md5((string) ($verification['kid'] ?? '') . '|' . (string) ($verification['timestamp'] ?? '') . '|' . $signature);
        if (get_transient($seen_key)) {
            return rest_ensure_response([
                'ok' => true,
                'duplicate' => true,
                'variant' => (string) ($verification['variant'] ?? ''),
            ]);
        }

        $payload = json_decode($raw_body, true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $result = [
            'matched' => 0,
            'processed' => 0,
            'updated' => 0,
            'emailed' => 0,
        ];

        try {
            $woocommerce = $this->api->get_woocommerce();
            if ($woocommerce && method_exists($woocommerce, 'handle_dingconnect_webhook')) {
                $result = (array) $woocommerce->handle_dingconnect_webhook($payload, [
                    'variant' => (string) ($verification['variant'] ?? ''),
                    'kid' => (string) ($verification['kid'] ?? ''),
                    'timestamp' => (int) ($verification['timestamp'] ?? 0),
                ]);
            } else {
                $this->api->log_transfer('unknown', 'webhook', 0, '', 'webhook-' . md5($raw_body), $payload);
            }
        } catch (Throwable $e) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => 'Error interno al procesar webhook.',
            ], 500);
        }

        set_transient($seen_key, 1, DAY_IN_SECONDS);

        return rest_ensure_response([
            'ok' => true,
            'variant' => (string) ($verification['variant'] ?? ''),
            'kid' => (string) ($verification['kid'] ?? ''),
            'result' => $result,
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

    private function normalize_recharge_payload($params) {
        $params = is_array($params) ? $params : [];

        return [
            'account_number' => $this->sanitize_phone($params['account_number'] ?? ''),
            'country_iso' => strtoupper(sanitize_text_field((string) ($params['country_iso'] ?? ''))),
            'sku_code' => sanitize_text_field((string) ($params['sku_code'] ?? '')),
            'bundle_id' => sanitize_text_field((string) ($params['bundle_id'] ?? '')),
            'send_value' => (float) ($params['send_value'] ?? 0),
            'send_currency_iso' => strtoupper(sanitize_text_field((string) ($params['send_currency_iso'] ?? ''))),
            'provider_code' => sanitize_text_field((string) ($params['provider_code'] ?? '')),
            'settings' => $this->sanitize_settings($params['settings'] ?? []),
            'bill_ref' => sanitize_text_field((string) ($params['bill_ref'] ?? '')),
            'landing_key' => sanitize_key((string) ($params['landing_key'] ?? '')),
            'allowed_bundle_ids' => $this->parse_bundle_ids(is_array($params['allowed_bundle_ids'] ?? null) ? implode(',', $params['allowed_bundle_ids']) : (string) ($params['allowed_bundle_ids'] ?? '')),
        ];
    }

    private function precheck_error($code, $message, $status = 400, $retryable = false, $details = []) {
        $status = (int) $status;
        $response_status = ($retryable || $status === 429 || $status >= 500) ? $status : 200;

        return new WP_REST_Response([
            'ok' => false,
            'code' => sanitize_text_field((string) $code),
            'message' => (string) $message,
            'retryable' => (bool) $retryable,
            'status' => $status,
            'details' => is_array($details) ? $details : [],
        ], $response_status);
    }

    private function precheck_wp_error_response($error, $fallback_code) {
        $data = $error instanceof WP_Error ? $error->get_error_data() : [];
        $data = is_array($data) ? $data : [];
        $status = isset($data['status']) && is_numeric($data['status']) ? (int) $data['status'] : 500;
        $code = sanitize_text_field((string) ($data['ding_error_code'] ?? $fallback_code));
        if ($code === '') {
            $code = sanitize_text_field((string) $fallback_code);
        }

        $this->api->log_operational_event('precheck_failed', [
            'status' => 'error',
            'raw_response' => [
                'code' => $code,
                'message' => $error instanceof WP_Error ? $error->get_error_message() : '',
                'error_data' => $data,
            ],
        ]);

        return $this->precheck_error(
            $code,
            $this->user_message_for_code($code, $error instanceof WP_Error ? $error->get_error_message() : ''),
            $this->is_retryable_code($code) ? max($status, 429) : $status,
            $this->is_retryable_code($code),
            $data
        );
    }

    private function with_retries(callable $operation, $max_attempts = 3) {
        $delays_ms = [250, 750, 1500];
        $last_error = null;

        for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
            $result = $operation();
            if (!is_wp_error($result)) {
                return $result;
            }

            $last_error = $result;
            if (!$this->is_transient_wp_error($result)) {
                return $result;
            }

            if ($attempt < ($max_attempts - 1)) {
                usleep($delays_ms[$attempt] * 1000);
            }
        }

        return $last_error ?: new WP_Error('dc_timeout', 'Error temporal en el servicio de recargas.', ['status' => 504]);
    }

    private function is_transient_wp_error($error) {
        if (!$error instanceof WP_Error) {
            return false;
        }

        $data = $error->get_error_data();
        $status = is_array($data) && isset($data['status']) ? (int) $data['status'] : 0;
        if (in_array($status, [408, 429], true) || $status >= 500) {
            return true;
        }

        return in_array($error->get_error_code(), ['http_request_failed', 'dc_timeout'], true);
    }

    private function extract_response_items($response) {
        if (!is_array($response)) {
            return [];
        }

        if (isset($response['Result']) && is_array($response['Result'])) {
            return array_values(array_filter($response['Result'], 'is_array'));
        }

        if (isset($response['Items']) && is_array($response['Items'])) {
            return array_values(array_filter($response['Items'], 'is_array'));
        }

        return [];
    }

    private function extract_lookup_context($lookup) {
        $lookup = is_array($lookup) ? $lookup : [];
        $items = $this->extract_response_items($lookup);
        $first = is_array($items[0] ?? null) ? $items[0] : [];

        return [
            'items' => $items,
            'provider_code' => sanitize_text_field((string) ($first['ProviderCode'] ?? $lookup['ProviderCode'] ?? '')),
            'country_iso' => strtoupper(sanitize_text_field((string) ($lookup['CountryIso'] ?? $first['CountryIso'] ?? ''))),
            'region_code' => sanitize_text_field((string) ($first['RegionCode'] ?? $lookup['RegionCode'] ?? '')),
        ];
    }

    private function find_product_by_sku($items, $sku_code) {
        $sku_code = strtoupper(sanitize_text_field((string) $sku_code));
        foreach ((array) $items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $candidate = strtoupper(sanitize_text_field((string) ($item['SkuCode'] ?? '')));
            if ($candidate !== '' && $candidate === $sku_code) {
                return $item;
            }
        }

        return null;
    }

    private function build_product_from_saved_bundle_for_precheck($bundle, $payload) {
        $bundle = is_array($bundle) ? $bundle : [];
        $payload = is_array($payload) ? $payload : [];

        $send_value = (float) ($payload['send_value'] ?? ($bundle['send_value'] ?? 0));
        $send_currency = strtoupper(sanitize_text_field((string) ($payload['send_currency_iso'] ?? ($bundle['send_currency_iso'] ?? ''))));
        $min_send = isset($bundle['minimum_send_value']) ? (float) $bundle['minimum_send_value'] : (float) ($bundle['send_value'] ?? $send_value);
        $max_send = isset($bundle['maximum_send_value']) ? (float) $bundle['maximum_send_value'] : (float) ($bundle['send_value'] ?? $send_value);

        if ($min_send <= 0) {
            $min_send = $send_value;
        }
        if ($max_send <= 0) {
            $max_send = $send_value;
        }

        return [
            'SkuCode' => sanitize_text_field((string) ($bundle['sku_code'] ?? ($payload['sku_code'] ?? ''))),
            'ProviderCode' => sanitize_text_field((string) ($bundle['provider_code'] ?? ($payload['provider_code'] ?? ''))),
            'CountryIso' => strtoupper(sanitize_text_field((string) ($bundle['country_iso'] ?? ($payload['country_iso'] ?? '')))),
            'RegionCode' => sanitize_text_field((string) ($bundle['region_code'] ?? '')),
            'RegionCodes' => array_values(array_filter(array_map('sanitize_text_field', (array) ($bundle['region_codes'] ?? [])))),
            'SendValue' => $send_value,
            'SendCurrencyIso' => $send_currency,
            'Minimum' => [
                'SendValue' => $min_send,
                'SendCurrencyIso' => $send_currency,
            ],
            'Maximum' => [
                'SendValue' => $max_send,
                'SendCurrencyIso' => $send_currency,
            ],
            'LookupBillsRequired' => !empty($bundle['lookup_bills_required']),
            'SettingDefinitions' => $this->normalize_setting_definitions($bundle['setting_definitions'] ?? []),
            'ValidationRegex' => sanitize_text_field((string) ($bundle['validation_regex'] ?? '')),
        ];
    }

    private function enrich_product_for_precheck($product, $country_iso) {
        $product = is_array($product) ? $product : [];
        $provider_code = sanitize_text_field((string) ($product['ProviderCode'] ?? ''));
        if ($provider_code === '') {
            return $product;
        }

        $provider_map = $this->get_provider_details_map([$product], $country_iso);
        $provider = $provider_map[$provider_code] ?? [];
        if (is_array($provider) && empty($product['ValidationRegex']) && !empty($provider['ValidationRegex'])) {
            $product['ValidationRegex'] = sanitize_text_field((string) $provider['ValidationRegex']);
        }
        if (is_array($provider) && empty($product['CountryIso']) && !empty($provider['CountryIso'])) {
            $product['CountryIso'] = strtoupper(sanitize_text_field((string) $provider['CountryIso']));
        }

        return $product;
    }

    private function product_matches_lookup($product, $lookup_context, $matched_bundle = null) {
        $product = is_array($product) ? $product : [];
        $matched_bundle = is_array($matched_bundle) ? $matched_bundle : [];
        $lookup_context = is_array($lookup_context) ? $lookup_context : [];

        $lookup_provider = sanitize_text_field((string) ($lookup_context['provider_code'] ?? ''));
        $product_provider = sanitize_text_field((string) ($product['ProviderCode'] ?? ($matched_bundle['provider_code'] ?? '')));
        if ($lookup_provider !== '' && $product_provider !== '' && strcasecmp($lookup_provider, $product_provider) !== 0) {
            return false;
        }

        $lookup_country = strtoupper(sanitize_text_field((string) ($lookup_context['country_iso'] ?? '')));
        $product_country = strtoupper(sanitize_text_field((string) ($product['CountryIso'] ?? ($matched_bundle['country_iso'] ?? ''))));
        if ($lookup_country !== '' && $product_country !== '' && $lookup_country !== $product_country) {
            return false;
        }

        $lookup_region = strtoupper(sanitize_text_field((string) ($lookup_context['region_code'] ?? '')));
        $product_region = strtoupper(sanitize_text_field((string) ($product['RegionCode'] ?? ($matched_bundle['region_code'] ?? ''))));
        $product_regions = array_map('strtoupper', array_filter(array_map('sanitize_text_field', (array) ($product['RegionCodes'] ?? ($matched_bundle['region_codes'] ?? [])))));
        if ($lookup_region !== '' && $product_region !== '' && $lookup_region !== $product_region && !in_array($lookup_region, $product_regions, true)) {
            return false;
        }

        return true;
    }

    private function validate_account_number_against_product($payload, $product, $matched_bundle = null) {
        $payload = is_array($payload) ? $payload : [];
        $product = is_array($product) ? $product : [];
        $matched_bundle = is_array($matched_bundle) ? $matched_bundle : [];

        $account_number = $this->sanitize_phone($payload['account_number'] ?? '');
        if ($account_number === '') {
            return new WP_Error('dc_account_missing', 'El número introducido no es válido o no admite recargas.', [
                'status' => 400,
                'ding_error_code' => 'AccountNumberInvalid',
                'ding_error_context' => 'AccountNumberMissing',
            ]);
        }

        $regex = trim((string) ($product['ValidationRegex'] ?? ($matched_bundle['validation_regex'] ?? '')));
        $source = $regex !== '' ? 'provider_validation_regex' : '';
        if ($regex === '') {
            $fallback = $this->fallback_account_number_regex($payload, $product, $matched_bundle);
            $regex = (string) ($fallback['regex'] ?? '');
            $source = (string) ($fallback['source'] ?? '');
        }

        if ($regex === '') {
            return true;
        }

        $candidates = $this->account_number_candidates($account_number, (string) ($payload['country_iso'] ?? ''));
        $pattern = $this->compile_validation_regex($regex);
        if ($pattern === '') {
            $this->api->log_operational_event('precheck_account_regex_unsupported', [
                'status' => 'warning',
                'account_number' => $account_number,
                'sku_code' => sanitize_text_field((string) ($payload['sku_code'] ?? '')),
                'raw_response' => [
                    'regex' => $regex,
                    'source' => $source,
                ],
            ]);
            return true;
        }

        foreach ($candidates as $candidate) {
            if (@preg_match($pattern, $candidate) === 1) {
                return true;
            }
        }

        return new WP_Error('dc_account_failed_regex', 'El número introducido no es válido o no admite recargas.', [
            'status' => 400,
            'ding_error_code' => 'AccountNumberInvalid',
            'ding_error_context' => 'AccountNumberFailedRegex',
            'validation_regex_source' => $source,
            'validation_regex' => $regex,
        ]);
    }

    private function fallback_account_number_regex($payload, $product, $matched_bundle = null) {
        $payload = is_array($payload) ? $payload : [];
        $product = is_array($product) ? $product : [];
        $matched_bundle = is_array($matched_bundle) ? $matched_bundle : [];

        $country_iso = strtoupper(sanitize_text_field((string) ($payload['country_iso'] ?? ($product['CountryIso'] ?? ($matched_bundle['country_iso'] ?? '')))));
        $sku_code = strtoupper(sanitize_text_field((string) ($payload['sku_code'] ?? ($product['SkuCode'] ?? ($matched_bundle['sku_code'] ?? '')))));
        $product_type = strtolower(sanitize_text_field((string) ($product['ProductType'] ?? ($matched_bundle['product_type_raw'] ?? ''))));
        $provider = strtolower(sanitize_text_field((string) ($product['ProviderName'] ?? ($matched_bundle['provider_name'] ?? ''))));

        $is_mobile_like = $product_type === '' || strpos($product_type, 'bundle') !== false || strpos($product_type, 'topup') !== false || strpos($product_type, 'mobile') !== false || strpos($provider, 'claro') !== false;
        if ($country_iso === 'CO' && $is_mobile_like && strpos($sku_code, 'CO') !== false) {
            return [
                'regex' => '^(57)?3[0-9]{9}$',
                'source' => 'fallback_colombia_mobile',
            ];
        }

        return [
            'regex' => '',
            'source' => '',
        ];
    }

    private function account_number_candidates($account_number, $country_iso = '') {
        $account_number = $this->sanitize_phone($account_number);
        $candidates = [];
        if ($account_number !== '') {
            $candidates[] = $account_number;
        }

        $dial = $this->country_dial_code($country_iso);
        if ($dial !== '' && strpos($account_number, $dial) === 0 && strlen($account_number) > strlen($dial)) {
            $candidates[] = substr($account_number, strlen($dial));
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function country_dial_code($country_iso) {
        $country_iso = strtoupper(sanitize_text_field((string) $country_iso));
        $map = [
            'CO' => '57',
            'CU' => '53',
            'DO' => '1809',
            'VE' => '58',
            'MX' => '52',
            'PE' => '51',
            'EC' => '593',
            'CL' => '56',
            'BR' => '55',
            'ES' => '34',
        ];

        return $map[$country_iso] ?? '';
    }

    private function compile_validation_regex($regex) {
        $regex = trim((string) $regex);
        if ($regex === '') {
            return '';
        }

        $delimiter = '~';
        $pattern = $delimiter . str_replace($delimiter, '\\' . $delimiter, $regex) . $delimiter;
        return @preg_match($pattern, '') === false ? '' : $pattern;
    }

    private function validate_amount_against_product($product, $send_value) {
        $product = is_array($product) ? $product : [];
        $price = $this->extract_product_price($product);
        $send_value = (float) $send_value;
        $min = (float) ($price['MinimumSendValue'] ?? 0);
        $max = (float) ($price['MaximumSendValue'] ?? 0);
        $fixed = (float) ($price['SendValue'] ?? 0);

        if ($min > 0 && $send_value < ($min - 0.00001)) {
            return new WP_Error('dc_amount_product_min', sprintf('El importe seleccionado está por debajo del mínimo permitido. Mínimo: %.2f.', $min), [
                'status' => 400,
                'min_send_value' => $min,
                'max_send_value' => $max,
            ]);
        }

        if ($max > 0 && $send_value > ($max + 0.00001)) {
            return new WP_Error('dc_amount_product_max', sprintf('El importe seleccionado supera el máximo permitido. Máximo: %.2f.', $max), [
                'status' => 400,
                'min_send_value' => $min,
                'max_send_value' => $max,
            ]);
        }

        if ($fixed > 0 && $min > 0 && $max > 0 && abs($min - $max) <= 0.00001 && abs($send_value - $fixed) > 0.00001) {
            return new WP_Error('dc_amount_product_fixed', sprintf('Este producto usa monto fijo. Importe permitido: %.2f.', $fixed), [
                'status' => 400,
                'fixed_send_value' => $fixed,
            ]);
        }

        return true;
    }

    private function validate_settings_for_precheck($settings, $product, $matched_bundle = null) {
        $definitions = $this->normalize_setting_definitions($product['SettingDefinitions'] ?? []);
        if (empty($definitions) && is_array($matched_bundle)) {
            $definitions = $this->normalize_setting_definitions($matched_bundle['setting_definitions'] ?? []);
        }

        if (empty($definitions)) {
            return true;
        }

        $setting_map = [];
        foreach ((array) $settings as $setting) {
            if (!is_array($setting)) {
                continue;
            }

            $name = sanitize_text_field((string) ($setting['Name'] ?? ''));
            if ($name !== '') {
                $setting_map[$name] = sanitize_text_field((string) ($setting['Value'] ?? ''));
            }
        }

        foreach ($definitions as $definition) {
            $name = sanitize_text_field((string) ($definition['Name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $value = (string) ($setting_map[$name] ?? '');
            if (!empty($definition['IsMandatory']) && trim($value) === '') {
                return new WP_Error('dc_setting_required', 'Completa los datos requeridos por el operador antes de continuar.', [
                    'status' => 400,
                    'code' => 'SettingRequired',
                    'setting' => $name,
                ]);
            }

            if ($value === '') {
                continue;
            }

            $min_length = (int) ($definition['MinLength'] ?? 0);
            $max_length = (int) ($definition['MaxLength'] ?? 0);
            if ($min_length > 0 && strlen($value) < $min_length) {
                return new WP_Error('dc_setting_invalid', 'Uno de los datos requeridos no cumple la longitud mínima.', ['status' => 400, 'setting' => $name]);
            }
            if ($max_length > 0 && strlen($value) > $max_length) {
                return new WP_Error('dc_setting_invalid', 'Uno de los datos requeridos supera la longitud máxima.', ['status' => 400, 'setting' => $name]);
            }

            $allowed_values = array_map('strval', (array) ($definition['AllowedValues'] ?? []));
            if (!empty($allowed_values) && !in_array($value, $allowed_values, true)) {
                return new WP_Error('dc_setting_invalid', 'Uno de los datos requeridos no es una opción permitida.', ['status' => 400, 'setting' => $name]);
            }

            $regex = trim((string) ($definition['ValidationRegex'] ?? ''));
            if ($regex !== '') {
                $pattern = '/' . str_replace('/', '\/', $regex) . '/';
                if (@preg_match($pattern, '') !== false && !preg_match($pattern, $value)) {
                    return new WP_Error('dc_setting_invalid', 'Uno de los datos requeridos no cumple el formato del operador.', ['status' => 400, 'setting' => $name]);
                }
            }
        }

        return true;
    }

    private function validate_seller_balance_for_precheck($send_value, $currency_iso) {
        $currency_iso = strtoupper(sanitize_text_field((string) $currency_iso));
        $cache_key = 'dc_precheck_balance_' . md5($currency_iso);
        $balance = get_transient($cache_key);

        if (false === $balance) {
            $response = $this->api->get_balance();
            if (is_wp_error($response)) {
                $this->api->log_operational_event('precheck_balance_unavailable', [
                    'status' => 'warning',
                    'raw_response' => $response->get_error_data(),
                ]);
                return true;
            }

            $balance = $this->normalize_balance_response($response);
            set_transient($cache_key, $balance, MINUTE_IN_SECONDS);
        }

        if (!is_array($balance)) {
            return true;
        }

        if (($balance['RawShape'] ?? 'unknown') === 'unknown') {
            return true;
        }

        $balance_currency = strtoupper(sanitize_text_field((string) ($balance['CurrencyIso'] ?? '')));
        $balance_amount = (float) ($balance['Balance'] ?? 0);
        if ($currency_iso !== '' && $balance_currency !== '' && $balance_currency === $currency_iso && $balance_amount < (float) $send_value) {
            return new WP_Error('dc_seller_balance_insufficient', 'No podemos procesar esta recarga en este momento. Intenta más tarde.', [
                'status' => 503,
                'code' => 'SELLER_BALANCE_INSUFFICIENT',
                'balance' => $balance_amount,
                'currency' => $balance_currency,
            ]);
        }

        return true;
    }

    private function validate_bundle_for_landing($landing_key, $bundle_id, $allowed_bundle_ids = []) {
        $landing_key = sanitize_key((string) $landing_key);
        $bundle_id = sanitize_text_field((string) $bundle_id);
        $allowed_bundle_ids = array_values(array_filter(array_map('strval', (array) $allowed_bundle_ids)));

        if ($bundle_id === '') {
            return true;
        }

        if (!empty($allowed_bundle_ids) && !in_array($bundle_id, $allowed_bundle_ids, true)) {
            return new WP_Error('dc_bundle_not_allowed', 'Este paquete no pertenece a la landing actual. Actualiza la página y vuelve a intentarlo.', [
                'status' => 400,
                'code' => 'PRODUCT_NOT_AVAILABLE',
            ]);
        }

        if ($landing_key === '') {
            return true;
        }

        $configs = get_option('dc_recargas_landing_shortcodes', []);
        if (!is_array($configs)) {
            return true;
        }

        foreach ($configs as $config) {
            if (!is_array($config) || sanitize_key((string) ($config['key'] ?? '')) !== $landing_key) {
                continue;
            }

            $landing_bundle_ids = array_values(array_filter(array_map('strval', (array) ($config['bundle_ids'] ?? []))));
            if (!empty($landing_bundle_ids) && !in_array($bundle_id, $landing_bundle_ids, true)) {
                return new WP_Error('dc_bundle_not_allowed', 'Este paquete ya no está disponible en esta landing. Actualiza la página y elige otro paquete.', [
                    'status' => 400,
                    'code' => 'PRODUCT_NOT_AVAILABLE',
                ]);
            }
        }

        return true;
    }

    private function build_precheck_fingerprint($payload) {
        $payload = is_array($payload) ? $payload : [];
        $settings = $this->sanitize_settings($payload['settings'] ?? []);
        usort($settings, function ($left, $right) {
            return strcasecmp((string) ($left['Name'] ?? ''), (string) ($right['Name'] ?? ''));
        });

        return md5(wp_json_encode([
            'account_number' => $this->sanitize_phone($payload['account_number'] ?? ''),
            'country_iso' => strtoupper(sanitize_text_field((string) ($payload['country_iso'] ?? ''))),
            'sku_code' => sanitize_text_field((string) ($payload['sku_code'] ?? '')),
            'bundle_id' => sanitize_text_field((string) ($payload['bundle_id'] ?? '')),
            'send_value' => round((float) ($payload['send_value'] ?? 0), 4),
            'send_currency_iso' => strtoupper(sanitize_text_field((string) ($payload['send_currency_iso'] ?? ''))),
            'settings' => $settings,
            'bill_ref' => sanitize_text_field((string) ($payload['bill_ref'] ?? '')),
        ]));
    }

    private function validate_precheck_token($params) {
        $payload = $this->normalize_recharge_payload($params);
        $token = sanitize_text_field((string) ($params['precheck_token'] ?? ''));
        if ($token === '') {
            return new WP_Error('dc_precheck_required', 'Primero debemos validar la recarga antes de pasar al pago.', [
                'status' => 400,
                'code' => 'PRECHECK_REQUIRED',
            ]);
        }

        $snapshot = get_transient($token);
        if (!is_array($snapshot)) {
            return new WP_Error('dc_precheck_expired', 'La validación expiró. Pulsa Continuar para validar nuevamente.', [
                'status' => 400,
                'code' => 'PRECHECK_EXPIRED',
            ]);
        }

        $fingerprint = $this->build_precheck_fingerprint($payload);
        if (!hash_equals((string) ($snapshot['fingerprint'] ?? ''), $fingerprint)) {
            return new WP_Error('dc_precheck_mismatch', 'Los datos cambiaron después de la validación. Valida nuevamente antes de pagar.', [
                'status' => 400,
                'code' => 'PRECHECK_MISMATCH',
            ]);
        }

        return true;
    }

    private function consume_precheck_token($params) {
        $token = sanitize_text_field((string) ($params['precheck_token'] ?? ''));
        if ($token !== '') {
            delete_transient($token);
        }
    }

    private function is_ding_success($response) {
        if (!is_array($response)) {
            return false;
        }

        if (isset($response['ResultCode']) && is_numeric($response['ResultCode'])) {
            $result_code = (int) $response['ResultCode'];
            return $result_code === 1 || $result_code === 2;
        }

        $items = $this->extract_response_items($response);
        foreach ($items as $item) {
            if (isset($item['ResultCode'])) {
                $item_result_code = (int) $item['ResultCode'];
                if ($item_result_code !== 1 && $item_result_code !== 2) {
                    return false;
                }
                continue;
            }

            if (!empty($item['ErrorCodes'])) {
                return false;
            }
        }

        return isset($response['TransferRecord']) || !empty($items);
    }

    private function extract_first_error_code($response) {
        $candidates = [];
        if (is_array($response)) {
            $candidates[] = $response['ErrorCodes'] ?? [];
            foreach ($this->extract_response_items($response) as $item) {
                $candidates[] = $item['ErrorCodes'] ?? [];
            }
        }

        foreach ($candidates as $error_codes) {
            foreach ((array) $error_codes as $error_code) {
                $code = is_array($error_code)
                    ? sanitize_text_field((string) ($error_code['Code'] ?? ''))
                    : sanitize_text_field((string) $error_code);
                if ($code !== '') {
                    return $code;
                }
            }
        }

        return '';
    }

    private function is_retryable_code($code) {
        $code = strtolower(sanitize_text_field((string) $code));
        return in_array($code, [
            'ratelimited',
            'providertimedout',
            'transientprovidererror',
            'ding_5xx',
            'ding_timeout',
            'partial_response',
            'lookup_failed',
            'products_failed',
            'validate_only_failed',
        ], true);
    }

    private function user_message_for_code($code, $fallback = '') {
        $messages = [
            'NUMBER_INVALID' => 'El número introducido no es válido o no admite recargas.',
            'LOOKUP_EMPTY' => 'No encontramos este número para recargas. Revisa el país y el número.',
            'AccountNumberInvalid' => 'El número introducido no es válido o no admite recargas.',
            'AccountNumberFailedRegex' => 'El formato del número no es válido para este operador.',
            'InvalidRecipient' => 'No es posible recargar este destinatario. Contacta soporte.',
            'OPERATOR_NOT_SUPPORTED' => 'El paquete seleccionado no corresponde al operador detectado para este número.',
            'PROVIDER_MISMATCH' => 'El paquete seleccionado no corresponde al operador detectado para este número.',
            'ProviderRefusedRequest' => 'El operador rechazó la validación de este número. Verifica los datos.',
            'NO_PRODUCTS' => 'No hay paquetes disponibles para este número. Selecciona otro paquete o número.',
            'PRODUCT_NOT_AVAILABLE' => 'Este paquete ya no está disponible para el número indicado.',
            'ProductUnavailable' => 'El paquete seleccionado no está disponible en este momento.',
            'AMOUNT_NOT_ALLOWED' => 'El importe seleccionado no está permitido para este paquete.',
            'ParameterOutOfRange' => 'El importe seleccionado está fuera del rango permitido.',
            'SendValue' => 'El importe seleccionado no es válido para este paquete.',
            'LookupBillsRequired' => 'Debes consultar y seleccionar la factura antes de continuar.',
            'BillRefInvalid' => 'La referencia de factura ya no es válida. Vuelve a consultar la factura.',
            'SettingRequired' => 'Completa los datos requeridos por el operador antes de continuar.',
            'InsufficientBalance' => 'No podemos procesar esta recarga en este momento. Intenta más tarde.',
            'SELLER_BALANCE_INSUFFICIENT' => 'No podemos procesar esta recarga en este momento. Intenta más tarde.',
            'RateLimited' => 'Hemos alcanzado el límite de solicitudes. Intenta en unos segundos.',
            'ProviderTimedOut' => 'Error temporal en el servicio de recargas. Intenta de nuevo en unos minutos.',
            'TransientProviderError' => 'El operador no respondió correctamente. Intenta nuevamente en unos minutos.',
            'VALIDATE_ONLY_FAILED' => 'No podemos confirmar la recarga con el operador en este momento. Intenta más tarde.',
            'PARTIAL_RESPONSE' => 'No pudimos confirmar la disponibilidad de la recarga. Intenta nuevamente.',
        ];

        if (isset($messages[$code])) {
            return $messages[$code];
        }

        return $fallback !== '' ? $fallback : 'No pudimos validar la recarga. Revisa los datos e inténtalo de nuevo.';
    }

    private function validate_send_value_against_bundle($sku_code, $country_iso, $send_value, $bundle_id = '') {
        return $this->api->validate_send_value_against_bundle($sku_code, $country_iso, $send_value, $bundle_id);
    }

    private function find_bundle_for_amount_validation($sku_code, $country_iso = '', $bundle_id = '') {
        return $this->api->find_bundle_for_amount_validation($sku_code, $country_iso, $bundle_id);
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
            $bundle_allow_manual_amount = array_key_exists('allow_manual_amount', $bundle)
                ? !empty($bundle['allow_manual_amount'])
                : true;
            $is_range = $allow_manual_amount && $bundle_allow_manual_amount && ($stored_is_range || $calculated_is_range);

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

            $bundle_description = sanitize_text_field((string) ($bundle['description'] ?? ''));
            if ($bundle_description === '') {
                $bundle_description = sanitize_text_field((string) ($bundle['receive'] ?? ''));
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
                'DefaultDisplayText' => sanitize_text_field((string) (!empty($bundle['label']) ? $bundle['label'] : ($bundle['default_display_text'] ?? ''))),
                'DisplayText' => sanitize_text_field((string) (!empty($bundle['label']) ? $bundle['label'] : ($bundle['display_text'] ?? ''))),
                'Description' => $bundle_description,
                'DescriptionMarkdown' => sanitize_text_field((string) ($bundle['description_markdown'] ?? '')),
                'ReadMoreMarkdown' => sanitize_text_field((string) ($bundle['read_more_markdown'] ?? '')),
                'AdditionalInformation' => sanitize_text_field((string) ($bundle['additional_information'] ?? $bundle_description)),
                'CountryIso' => strtoupper(sanitize_text_field((string) ($bundle['country_iso'] ?? ''))),
                'RegionCode' => sanitize_text_field((string) ($bundle['region_code'] ?? '')),
                'RegionCodes' => array_values(array_filter(array_map('sanitize_text_field', (array) ($bundle['region_codes'] ?? [])))),
                'ValidationRegex' => sanitize_text_field((string) ($bundle['validation_regex'] ?? '')),
                'CustomerCareNumber' => sanitize_text_field((string) ($bundle['customer_care_number'] ?? '')),
                'LogoUrl' => esc_url_raw((string) ($bundle['logo_url'] ?? '')),
                'IsPromotion' => !empty($bundle['is_promotion']),
                'IsRange' => $is_range,
                'AllowManualAmount' => $bundle_allow_manual_amount,
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

        $matched = $this->find_saved_bundle_for_cart($bundle_id, $sku_code, $country_iso);
        if (!is_array($matched)) {
            return $incoming;
        }

        $stored_public = (float) ($matched['public_price'] ?? 0);
        if ($stored_public > 0) {
            return $stored_public;
        }

        return $incoming;
    }

    private function find_saved_bundle_for_cart($bundle_id, $sku_code, $country_iso) {
        $bundles = get_option('dc_recargas_bundles', []);
        $bundles = is_array($bundles) ? $bundles : [];
        if (empty($bundles)) {
            return null;
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
            return null;
        }

        return $matched;
    }

    private function extract_bundle_benefit_for_checkout($bundle) {
        if (!is_array($bundle)) {
            return '';
        }

        $description = sanitize_text_field((string) ($bundle['description'] ?? ''));
        if ($description !== '') {
            return $description;
        }

        $legacy_receive = sanitize_text_field((string) ($bundle['receive'] ?? ''));
        if ($legacy_receive !== '') {
            return $legacy_receive;
        }

        $benefits = [];
        foreach ((array) ($bundle['benefits'] ?? []) as $benefit) {
            $clean = sanitize_text_field((string) $benefit);
            if ($clean !== '') {
                $benefits[] = $clean;
            }
        }

        if (!empty($benefits)) {
            return implode(' · ', array_slice($benefits, 0, 3));
        }

        return sanitize_text_field((string) ($bundle['label'] ?? ''));
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
            'MinimumSendValue' => (float) ($minimum['SendValue'] ?? ($item['MinimumSendValue'] ?? ($item['SendValue'] ?? ($price['SendValue'] ?? 0)))),
            'MaximumSendValue' => (float) ($maximum['SendValue'] ?? ($item['MaximumSendValue'] ?? ($item['SendValue'] ?? ($price['SendValue'] ?? 0)))),
            'MinimumReceiveValue' => (float) ($minimum['ReceiveValue'] ?? ($item['MinimumReceiveValue'] ?? ($item['ReceiveValue'] ?? ($price['ReceiveValue'] ?? 0)))),
            'MaximumReceiveValue' => (float) ($maximum['ReceiveValue'] ?? ($item['MaximumReceiveValue'] ?? ($item['ReceiveValue'] ?? ($price['ReceiveValue'] ?? 0)))),
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
