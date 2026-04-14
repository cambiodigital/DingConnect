<?php

if (!defined('ABSPATH')) {
    exit;
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
        $account_number = $this->sanitize_phone($request->get_param('account_number'));
        $country_iso = strtoupper(sanitize_text_field($request->get_param('country_iso') ?? ''));

        if (empty($account_number) || strlen($account_number) < 8) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => 'Número de móvil inválido.',
            ], 400);
        }

        $response = $this->api->get_products($account_number, 50);
        if (is_wp_error($response)) {
            $fallback = $this->filter_bundles_by_country($country_iso);
            return new WP_REST_Response([
                'ok' => false,
                'source' => 'fallback',
                'message' => $response->get_error_message(),
                'error' => $response->get_error_data(),
                'result' => $fallback,
            ], 200);
        }

        return rest_ensure_response([
            'ok' => true,
            'source' => 'dingconnect',
            'result' => $response['Result'] ?? [],
            'raw' => $response,
        ]);
    }

    public function transfer(WP_REST_Request $request) {
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
        if (is_wp_error($response)) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => $response->get_error_message(),
                'error' => $response->get_error_data(),
            ], 500);
        }

        return rest_ensure_response([
            'ok' => true,
            'result' => $response,
        ]);
    }

    private function sanitize_phone($phone) {
        $raw = preg_replace('/[^\d+]/', '', (string) $phone);
        if (strpos($raw, '+') !== 0) {
            $raw = '+' . ltrim($raw, '+');
        }

        return $raw;
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
                'SkuCode' => $bundle['sku_code'] ?? '',
                'ProviderName' => $bundle['provider_name'] ?? '',
                'SendValue' => (float) ($bundle['send_value'] ?? 0),
                'SendCurrencyIso' => $bundle['send_currency_iso'] ?? 'USD',
                'DefaultDisplayText' => $bundle['label'] ?? '',
                'Description' => $bundle['description'] ?? '',
                'IsPromotion' => false,
                'IsRange' => false,
            ];
        }, $active);
    }
}
