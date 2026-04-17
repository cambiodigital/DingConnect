<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('DC_Recargas_Wizard')) {
    return;
}

class DC_Recargas_Wizard {
    const SESSION_TABLE_SUFFIX = 'dc_wizard_sessions';

    private $api;

    public function __construct($api) {
        $this->api = $api;
    }

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::SESSION_TABLE_SUFFIX;
    }

    public static function maybe_create_sessions_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            state longtext NOT NULL,
            context longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY session_id (session_id),
            KEY expires_at (expires_at)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    public function get_config() {
        $opts = $this->api->get_options();

        return [
            'enabled' => !empty($opts['wizard_enabled']),
            'default_step' => 'category',
            'steps' => $this->get_steps(),
            'max_offers_per_category' => (int) ($opts['wizard_max_offers_per_category'] ?? 6),
            'checkout_mapping_mode' => sanitize_key((string) ($opts['wizard_checkout_mapping_mode'] ?? 'both')),
            'checkout_beneficiary_meta_key' => sanitize_key((string) ($opts['wizard_checkout_beneficiary_meta_key'] ?? '_dc_beneficiary_phone')),
        ];
    }

    public function get_steps() {
        return ['category', 'country', 'operator', 'product', 'review'];
    }

    public function get_initial_state($context = []) {
        $state = [
            'current_step' => 'category',
            'category' => '',
            'country_iso' => '',
            'operator' => '',
            'product' => [],
            'account_number' => '',
            'updated_at' => current_time('mysql'),
        ];

        if (is_array($context)) {
            if (!empty($context['country_iso'])) {
                $state['country_iso'] = strtoupper(sanitize_text_field((string) $context['country_iso']));
            }
            if (!empty($context['account_number'])) {
                $state['account_number'] = preg_replace('/\D+/', '', (string) $context['account_number']);
            }
        }

        return $state;
    }

    public function validate_state($state, $context = []) {
        if (!is_array($state)) {
            return false;
        }

        $allowed_steps = $this->get_steps();
        $current_step = sanitize_text_field((string) ($state['current_step'] ?? ''));
        $entry_mode = sanitize_key((string) (($context['entry_mode'] ?? 'number_first')));
        if (!in_array($entry_mode, ['number_first', 'country_fixed'], true)) {
            $entry_mode = 'number_first';
        }

        $category = sanitize_key((string) ($state['category'] ?? ''));
        $country_iso = strtoupper(sanitize_text_field((string) ($state['country_iso'] ?? '')));
        $operator = sanitize_text_field((string) ($state['operator'] ?? ''));
        $account_number = $this->normalize_account_number((string) ($state['account_number'] ?? ''), (string) ($context['fixed_prefix'] ?? ''));
        $product = is_array($state['product'] ?? null) ? $state['product'] : [];

        if (!in_array($current_step, $allowed_steps, true)) {
            return false;
        }

        if (in_array($current_step, ['country', 'operator', 'product', 'review'], true) && $category === '') {
            return false;
        }

        if (in_array($current_step, ['operator', 'product', 'review'], true)) {
            if ($entry_mode === 'country_fixed' && $country_iso === '') {
                return false;
            }
            if ($account_number === '' || strlen($account_number) < 6) {
                return false;
            }
        }

        if (in_array($current_step, ['product', 'review'], true) && $operator === '') {
            return false;
        }

        if ($current_step === 'review' && empty($product['sku_code'])) {
            return false;
        }

        return true;
    }

    public function can_transition($from_step, $to_step) {
        $flow = [
            'category' => ['country'],
            'country' => ['operator'],
            'operator' => ['product'],
            'product' => ['review'],
            'review' => [],
        ];

        $from_step = sanitize_text_field((string) $from_step);
        $to_step = sanitize_text_field((string) $to_step);

        if (!isset($flow[$from_step])) {
            return false;
        }

        return in_array($to_step, $flow[$from_step], true);
    }

    public function new_session_id() {
        return 'wiz_' . wp_generate_uuid4();
    }

    private function is_allowed_navigation($from_step, $to_step) {
        $steps = $this->get_steps();
        $from_index = array_search(sanitize_text_field((string) $from_step), $steps, true);
        $to_index = array_search(sanitize_text_field((string) $to_step), $steps, true);

        if ($from_index === false || $to_index === false) {
            return false;
        }

        return abs((int) $from_index - (int) $to_index) <= 1;
    }

    public function save_session($session_id, $state, $context = []) {
        global $wpdb;

        $session_id = sanitize_text_field((string) $session_id);
        $context = is_array($context) ? $context : [];

        if ($session_id === '' || !$this->validate_state($state, $context)) {
            return new WP_Error('dc_wizard_invalid_state', 'Estado de wizard inválido.', ['status' => 400]);
        }

        $table = self::get_table_name();
        $now = current_time('mysql');
        $expires_at = gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS);

        $payload = [
            'session_id' => $session_id,
            'state' => wp_json_encode($state),
            'context' => wp_json_encode($context),
            'updated_at' => $now,
            'expires_at' => $expires_at,
        ];

        $existing = $this->get_session($session_id);

        if (is_wp_error($existing)) {
            return $existing;
        }

        if ($existing && is_array($existing['state'] ?? null)) {
            $prev_step = sanitize_text_field((string) ($existing['state']['current_step'] ?? ''));
            $next_step = sanitize_text_field((string) ($state['current_step'] ?? ''));

            if ($prev_step !== '' && $next_step !== '' && $prev_step !== $next_step && !$this->is_allowed_navigation($prev_step, $next_step)) {
                return new WP_Error('dc_wizard_invalid_transition', 'No se permite saltar pasos del wizard.', ['status' => 400]);
            }
        }

        if ($existing) {
            $updated = $wpdb->update(
                $table,
                [
                    'state' => $payload['state'],
                    'context' => $payload['context'],
                    'updated_at' => $payload['updated_at'],
                    'expires_at' => $payload['expires_at'],
                ],
                ['session_id' => $session_id],
                ['%s', '%s', '%s', '%s'],
                ['%s']
            );

            if ($updated === false) {
                return new WP_Error('dc_wizard_save_error', 'No se pudo actualizar la sesión del wizard.', ['status' => 500]);
            }
        } else {
            $payload['created_at'] = $now;

            $inserted = $wpdb->insert(
                $table,
                $payload,
                ['%s', '%s', '%s', '%s', '%s', '%s']
            );

            if ($inserted === false) {
                return new WP_Error('dc_wizard_save_error', 'No se pudo guardar la sesión del wizard.', ['status' => 500]);
            }
        }

        return [
            'session_id' => $session_id,
            'state' => $state,
            'context' => $context,
            'updated_at' => $now,
            'expires_at' => $expires_at,
        ];
    }

    public function get_session($session_id) {
        global $wpdb;

        $session_id = sanitize_text_field((string) $session_id);
        if ($session_id === '') {
            return null;
        }

        $table = self::get_table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE session_id = %s LIMIT 1", $session_id),
            ARRAY_A
        );

        if (empty($row)) {
            return null;
        }

        if (strtotime((string) ($row['expires_at'] ?? '')) < time()) {
            $wpdb->delete($table, ['session_id' => $session_id], ['%s']);
            return null;
        }

        return [
            'session_id' => $row['session_id'],
            'state' => json_decode((string) ($row['state'] ?? '{}'), true) ?: [],
            'context' => json_decode((string) ($row['context'] ?? '{}'), true) ?: [],
            'created_at' => $row['created_at'] ?? '',
            'updated_at' => $row['updated_at'] ?? '',
            'expires_at' => $row['expires_at'] ?? '',
        ];
    }

    public function normalize_account_number($raw_phone, $fixed_prefix = '') {
        $phone = preg_replace('/\D+/', '', (string) $raw_phone);
        $prefix = preg_replace('/\D+/', '', (string) $fixed_prefix);

        if ($prefix !== '' && $phone !== '' && strpos($phone, $prefix) !== 0) {
            $phone = $prefix . $phone;
        }

        return $phone;
    }

    public function get_offers($params = []) {
        $params = is_array($params) ? $params : [];

        $opts = $this->api->get_options();
        $default_entry_mode = sanitize_key((string) ($opts['wizard_default_entry_mode'] ?? 'number_first'));
        if (!in_array($default_entry_mode, ['number_first', 'country_fixed'], true)) {
            $default_entry_mode = 'number_first';
        }
        $default_fixed_prefix = preg_replace('/[^0-9+]/', '', (string) ($opts['wizard_fixed_prefix'] ?? ''));

        $entry_mode = sanitize_key((string) ($params['entry_mode'] ?? $default_entry_mode));
        if (!in_array($entry_mode, ['number_first', 'country_fixed'], true)) {
            $entry_mode = $default_entry_mode;
        }

        $country_iso = strtoupper(sanitize_text_field((string) ($params['country_iso'] ?? '')));
        $category = sanitize_key((string) ($params['category'] ?? ''));
        $fixed_prefix = (string) ($params['fixed_prefix'] ?? $default_fixed_prefix);
        $account_number = $this->normalize_account_number((string) ($params['account_number'] ?? ''), $fixed_prefix);

        if ($entry_mode === 'country_fixed' && $country_iso === '') {
            return new WP_Error('dc_wizard_country_required', 'Debes indicar país para entry_mode country_fixed.', ['status' => 400]);
        }

        if ($entry_mode === 'number_first' && strlen($account_number) < 6 && $country_iso === '') {
            return new WP_Error('dc_wizard_phone_required', 'Debes indicar un número válido o país fijo.', ['status' => 400]);
        }

        $response = $country_iso !== ''
            ? $this->api->get_products_by_country($country_iso, 250)
            : $this->api->get_products($account_number, 250);

        if (is_wp_error($response)) {
            return $response;
        }

        $items = $response['Result'] ?? $response['Items'] ?? [];
        $normalized = $this->normalize_offers($items);
        $filtered = $this->apply_offer_filters($normalized, $category);
        $resolved_country_iso = $country_iso !== '' ? $country_iso : $this->infer_country_iso_from_offers($filtered);

        return [
            'entry_mode' => $entry_mode,
            'country_iso' => $resolved_country_iso,
            'account_number' => $account_number,
            'category' => $category,
            'offers' => $filtered,
        ];
    }

    public function build_confirmation_payload($selection = [], $transfer_result = []) {
        $selection = is_array($selection) ? $selection : [];
        $transfer_result = is_array($transfer_result) ? $transfer_result : [];

        return [
            'transaction_id' => sanitize_text_field((string) ($transfer_result['TransferRef'] ?? $transfer_result['DistributorRef'] ?? '')),
            'status' => sanitize_text_field((string) ($transfer_result['ResultCode'] ?? $transfer_result['Status'] ?? 'Pending')),
            'operator' => sanitize_text_field((string) ($selection['provider_name'] ?? $selection['operator'] ?? '')),
            'amount_sent' => (float) ($selection['send_value'] ?? $transfer_result['SendValue'] ?? 0),
            'amount_received' => (float) ($transfer_result['ReceiveValue'] ?? $selection['receive_value'] ?? 0),
            'beneficiary_phone' => $this->normalize_account_number((string) ($selection['account_number'] ?? '')),
            'timestamp' => current_time('mysql'),
            'promotion' => sanitize_text_field((string) ($selection['promotion'] ?? $transfer_result['PromotionDescription'] ?? '')),
            'voucher_lines' => array_values(array_filter([
                sanitize_text_field((string) ($selection['label'] ?? '')),
                sanitize_text_field((string) ($selection['description'] ?? '')),
            ])),
        ];
    }

    private function normalize_offers($items) {
        $normalized = [];

        foreach ((array) $items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $sku_code = sanitize_text_field((string) ($item['SkuCode'] ?? ''));
            if ($sku_code === '') {
                continue;
            }

            $send_value = (float) ($item['SendValue'] ?? ($item['Minimum']['SendValue'] ?? 0));
            $send_currency_iso = strtoupper(sanitize_text_field((string) ($item['SendCurrencyIso'] ?? ($item['Minimum']['SendCurrencyIso'] ?? 'USD'))));

            $normalized[] = [
                'sku_code' => $sku_code,
                'provider_name' => sanitize_text_field((string) ($item['ProviderName'] ?? $item['ProviderCode'] ?? '')),
                'provider_code' => sanitize_text_field((string) ($item['ProviderCode'] ?? '')),
                'label' => sanitize_text_field((string) ($item['DefaultDisplayText'] ?? $sku_code)),
                'description' => sanitize_text_field((string) ($item['Description'] ?? '')),
                'send_value' => $send_value,
                'send_currency_iso' => $send_currency_iso,
                'receive_value' => (float) ($item['ReceiveValue'] ?? 0),
                'receive_currency_iso' => strtoupper(sanitize_text_field((string) ($item['ReceiveCurrencyIso'] ?? ''))),
                'country_iso' => strtoupper(sanitize_text_field((string) ($item['CountryIso'] ?? $item['CountryISO'] ?? ''))),
                'category' => $this->detect_category($item),
                'is_range' => !empty($item['IsRange']),
            ];
        }

        usort($normalized, function ($left, $right) {
            $category_cmp = strcmp((string) ($left['category'] ?? ''), (string) ($right['category'] ?? ''));
            if ($category_cmp !== 0) {
                return $category_cmp;
            }

            $provider_cmp = strcasecmp((string) ($left['provider_name'] ?? ''), (string) ($right['provider_name'] ?? ''));
            if ($provider_cmp !== 0) {
                return $provider_cmp;
            }

            $amount_cmp = (float) ($left['send_value'] ?? 0) <=> (float) ($right['send_value'] ?? 0);
            if ($amount_cmp !== 0) {
                return $amount_cmp;
            }

            return strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return $normalized;
    }

    private function detect_category($item) {
        $haystack = strtolower(implode(' ', [
            (string) ($item['ProductType'] ?? ''),
            (string) ($item['RedemptionMechanism'] ?? ''),
            (string) ($item['DefaultDisplayText'] ?? ''),
            (string) ($item['Description'] ?? ''),
        ]));

        if (strpos($haystack, 'gift') !== false || strpos($haystack, 'card') !== false || strpos($haystack, 'pin') !== false) {
            return 'gift_cards';
        }

        return 'recargas';
    }

    private function apply_offer_filters($offers, $category = '') {
        $offers = is_array($offers) ? $offers : [];
        $category = sanitize_key((string) $category);
        $max = (int) ($this->api->get_options()['wizard_max_offers_per_category'] ?? 6);
        if ($max < 1) {
            $max = 1;
        }

        if ($category !== '') {
            $offers = array_values(array_filter($offers, function ($offer) use ($category) {
                return sanitize_key((string) ($offer['category'] ?? '')) === $category;
            }));
        }

        $grouped = [];
        foreach ($offers as $offer) {
            $offer_category = sanitize_key((string) ($offer['category'] ?? 'recargas'));
            if (!isset($grouped[$offer_category])) {
                $grouped[$offer_category] = [];
            }
            $grouped[$offer_category][] = $offer;
        }

        $limited = [];
        foreach ($grouped as $offer_category => $category_offers) {
            $limited = array_merge($limited, array_slice($category_offers, 0, $max));
        }

        return array_values($limited);
    }

    private function infer_country_iso_from_offers($offers) {
        foreach ((array) $offers as $offer) {
            $country_iso = strtoupper(sanitize_text_field((string) ($offer['country_iso'] ?? '')));
            if ($country_iso !== '') {
                return $country_iso;
            }
        }

        return '';
    }
}
