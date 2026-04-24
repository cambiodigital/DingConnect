<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('DC_Recargas_Admin')) {
    return;
}

class DC_Recargas_Admin {
    private $api;

    public function __construct($api) {
        $this->api = $api;

        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_dc_add_bundle', [$this, 'handle_add_bundle']);
        add_action('admin_post_dc_update_bundle', [$this, 'handle_update_bundle']);
        add_action('admin_post_dc_toggle_bundle', [$this, 'handle_toggle_bundle']);
        add_action('admin_post_dc_delete_bundle', [$this, 'handle_delete_bundle']);
        add_action('admin_post_dc_bulk_delete_bundles', [$this, 'handle_bulk_delete_bundles']);
        add_action('wp_ajax_dc_create_bundle_from_catalog', [$this, 'ajax_create_bundle_from_catalog']);
        add_action('wp_ajax_dc_get_transfer_logs', [$this, 'ajax_get_transfer_logs']);
        add_action('admin_post_dc_clear_logs', [$this, 'handle_clear_logs']);
        add_action('wp_ajax_dc_fetch_api_products', [$this, 'ajax_fetch_api_products']);
        add_action('admin_post_dc_add_landing_shortcode', [$this, 'handle_add_landing_shortcode']);
        add_action('admin_post_dc_clone_landing_shortcode', [$this, 'handle_clone_landing_shortcode']);
        add_action('admin_post_dc_update_landing_shortcode', [$this, 'handle_update_landing_shortcode']);
        add_action('admin_post_dc_delete_landing_shortcode', [$this, 'handle_delete_landing_shortcode']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function register_menu() {
        add_menu_page(
            'DingConnect - CambioDigital',
            'DingConnect CD',
            'manage_options',
            'dc-recargas',
            [$this, 'render_page'],
            'dashicons-smartphone',
            57
        );
    }

    public function register_settings() {
        register_setting('dc_recargas_group', 'dc_recargas_options', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_options'],
            'default' => [],
        ]);
    }

    public function enqueue_admin_assets($hook_suffix) {
        if ($hook_suffix !== 'toplevel_page_dc-recargas') {
            return;
        }
    }

    public function handle_add_landing_shortcode() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('dc_add_landing_shortcode');

        $name = sanitize_text_field((string) ($_POST['landing_name'] ?? ''));
        $key_input = sanitize_key((string) ($_POST['landing_key'] ?? ''));
        $title = sanitize_text_field((string) ($_POST['landing_title'] ?? ''));
        $subtitle = sanitize_text_field((string) ($_POST['landing_subtitle'] ?? ''));
        $raw_bundle_order = wp_unslash($_POST['bundle_order'] ?? []);
        $featured_bundle_id = sanitize_text_field((string) ($_POST['featured_bundle_id'] ?? ''));

        $raw_bundle_ids = wp_unslash($_POST['bundle_ids'] ?? []);
        $bundle_ids = [];
        if (is_array($raw_bundle_ids)) {
            foreach ($raw_bundle_ids as $bundle_id) {
                $bundle_id = sanitize_text_field((string) $bundle_id);
                if ($bundle_id !== '') {
                    $bundle_ids[] = $bundle_id;
                }
            }
        }
        $bundle_ids = array_values(array_unique($bundle_ids));

        if ($name === '' || empty($bundle_ids)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'dc-recargas',
                'dc_msg' => 'landing_shortcode_error',
            ], admin_url('admin.php')));
            exit;
        }

        $bundles = get_option('dc_recargas_bundles', []);
        if (!is_array($bundles)) {
            $bundles = [];
        }
        $valid_bundle_ids = [];
        foreach ($bundles as $bundle) {
            $bundle_id = sanitize_text_field((string) ($bundle['id'] ?? ''));
            if ($bundle_id !== '' && !empty($bundle['is_active'])) {
                $valid_bundle_ids[$bundle_id] = strtoupper((string) ($bundle['country_iso'] ?? ''));
            }
        }

        $selected_bundle_ids = [];
        $detected_countries = [];
        foreach ($bundle_ids as $bundle_id) {
            if (!isset($valid_bundle_ids[$bundle_id])) {
                continue;
            }
            $selected_bundle_ids[] = $bundle_id;
            if ($valid_bundle_ids[$bundle_id] !== '') {
                $detected_countries[] = $valid_bundle_ids[$bundle_id];
            }
        }

        $selected_bundle_ids = $this->order_selected_bundles($selected_bundle_ids, $raw_bundle_order);
        $featured_bundle_id = in_array($featured_bundle_id, $selected_bundle_ids, true) ? $featured_bundle_id : '';

        if (empty($selected_bundle_ids)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'dc-recargas',
                'dc_msg' => 'landing_shortcode_error',
            ], admin_url('admin.php')));
            exit;
        }

        $detected_countries = array_values(array_unique(array_filter(array_map('strval', $detected_countries))));

        $country_iso = count($detected_countries) === 1 ? (string) $detected_countries[0] : '';

        $shortcodes = get_option('dc_recargas_landing_shortcodes', []);
        if (!is_array($shortcodes)) {
            $shortcodes = [];
        }

        $key = $this->generate_unique_landing_key($key_input !== '' ? $key_input : sanitize_title($name), $shortcodes);

        $shortcodes[] = [
            'id' => uniqid('landing_', true),
            'name' => $name,
            'key' => $key,
            'title' => $title,
            'subtitle' => $subtitle,
            'country_iso' => $country_iso,
            'bundle_ids' => $selected_bundle_ids,
            'featured_bundle_id' => $featured_bundle_id,
            'created_at' => current_time('mysql'),
        ];

        update_option('dc_recargas_landing_shortcodes', $shortcodes);

        wp_safe_redirect(add_query_arg([
            'page' => 'dc-recargas',
            'dc_msg' => 'landing_shortcode_added',
        ], admin_url('admin.php')));
        exit;
    }

    public function handle_delete_landing_shortcode() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('dc_delete_landing_shortcode');

        $landing_id = sanitize_text_field((string) ($_GET['landing_id'] ?? ''));
        $shortcodes = get_option('dc_recargas_landing_shortcodes', []);
        if (!is_array($shortcodes)) {
            $shortcodes = [];
        }

        $new_shortcodes = array_values(array_filter($shortcodes, function ($item) use ($landing_id) {
            return sanitize_text_field((string) ($item['id'] ?? '')) !== $landing_id;
        }));

        update_option('dc_recargas_landing_shortcodes', $new_shortcodes);

        wp_safe_redirect(add_query_arg([
            'page' => 'dc-recargas',
            'dc_msg' => 'landing_shortcode_deleted',
        ], admin_url('admin.php')));
        exit;
    }

    public function handle_clone_landing_shortcode() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('dc_clone_landing_shortcode');

        $landing_id = sanitize_text_field((string) ($_GET['landing_id'] ?? ''));
        $shortcodes = get_option('dc_recargas_landing_shortcodes', []);
        if (!is_array($shortcodes)) {
            $shortcodes = [];
        }

        $source = null;
        foreach ($shortcodes as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (sanitize_text_field((string) ($item['id'] ?? '')) === $landing_id) {
                $source = $item;
                break;
            }
        }

        if (!$source) {
            wp_safe_redirect(add_query_arg([
                'page' => 'dc-recargas',
                'dc_msg' => 'landing_shortcode_error',
            ], admin_url('admin.php')));
            exit;
        }

        $base_name = sanitize_text_field((string) ($source['name'] ?? 'Landing'));
        $base_key = sanitize_key((string) ($source['key'] ?? sanitize_title($base_name)));

        $clone = [
            'id' => uniqid('landing_', true),
            'name' => $base_name . ' (copia)',
            'key' => $this->generate_unique_landing_key($base_key . '-copy', $shortcodes),
            'title' => sanitize_text_field((string) ($source['title'] ?? '')),
            'subtitle' => sanitize_text_field((string) ($source['subtitle'] ?? '')),
            'country_iso' => strtoupper(sanitize_text_field((string) ($source['country_iso'] ?? ''))),
            'bundle_ids' => is_array($source['bundle_ids'] ?? null) ? array_values(array_unique(array_map('sanitize_text_field', $source['bundle_ids']))) : [],
            'featured_bundle_id' => sanitize_text_field((string) ($source['featured_bundle_id'] ?? '')),
            'created_at' => current_time('mysql'),
            'cloned_from' => sanitize_text_field((string) ($source['id'] ?? '')),
        ];

        if (!in_array($clone['featured_bundle_id'], $clone['bundle_ids'], true)) {
            $clone['featured_bundle_id'] = '';
        }

        $shortcodes[] = $clone;
        update_option('dc_recargas_landing_shortcodes', array_values($shortcodes));

        wp_safe_redirect(add_query_arg([
            'page' => 'dc-recargas',
            'dc_msg' => 'landing_shortcode_cloned',
            'dc_edit_landing' => $clone['id'],
            'dc_tab' => 'tab_landings',
        ], admin_url('admin.php')));
        exit;
    }

    public function handle_update_landing_shortcode() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('dc_update_landing_shortcode');

        $landing_id = sanitize_text_field((string) ($_POST['landing_id'] ?? ''));
        $name = sanitize_text_field((string) ($_POST['landing_name'] ?? ''));
        $key_input = sanitize_key((string) ($_POST['landing_key'] ?? ''));
        $title = sanitize_text_field((string) ($_POST['landing_title'] ?? ''));
        $subtitle = sanitize_text_field((string) ($_POST['landing_subtitle'] ?? ''));
        $raw_bundle_order = wp_unslash($_POST['bundle_order'] ?? []);
        $featured_bundle_id = sanitize_text_field((string) ($_POST['featured_bundle_id'] ?? ''));

        $raw_bundle_ids = wp_unslash($_POST['bundle_ids'] ?? []);
        $bundle_ids = [];
        if (is_array($raw_bundle_ids)) {
            foreach ($raw_bundle_ids as $bundle_id) {
                $bundle_id = sanitize_text_field((string) $bundle_id);
                if ($bundle_id !== '') {
                    $bundle_ids[] = $bundle_id;
                }
            }
        }
        $bundle_ids = array_values(array_unique($bundle_ids));

        $shortcodes = get_option('dc_recargas_landing_shortcodes', []);
        if (!is_array($shortcodes)) {
            $shortcodes = [];
        }

        $landing_index = -1;
        foreach ($shortcodes as $idx => $item) {
            if (sanitize_text_field((string) ($item['id'] ?? '')) === $landing_id) {
                $landing_index = (int) $idx;
                break;
            }
        }

        if ($landing_index === -1 || $name === '' || empty($bundle_ids)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'dc-recargas',
                'dc_msg' => 'landing_shortcode_error',
            ], admin_url('admin.php')));
            exit;
        }

        $bundles = get_option('dc_recargas_bundles', []);
        if (!is_array($bundles)) {
            $bundles = [];
        }
        $valid_bundle_ids = [];
        foreach ($bundles as $bundle) {
            $bundle_id = sanitize_text_field((string) ($bundle['id'] ?? ''));
            if ($bundle_id !== '' && !empty($bundle['is_active'])) {
                $valid_bundle_ids[$bundle_id] = strtoupper((string) ($bundle['country_iso'] ?? ''));
            }
        }

        $selected_bundle_ids = [];
        $detected_countries = [];
        foreach ($bundle_ids as $bundle_id) {
            if (!isset($valid_bundle_ids[$bundle_id])) {
                continue;
            }
            $selected_bundle_ids[] = $bundle_id;
            if ($valid_bundle_ids[$bundle_id] !== '') {
                $detected_countries[] = $valid_bundle_ids[$bundle_id];
            }
        }

        $selected_bundle_ids = $this->order_selected_bundles($selected_bundle_ids, $raw_bundle_order);
        $featured_bundle_id = in_array($featured_bundle_id, $selected_bundle_ids, true) ? $featured_bundle_id : '';

        if (empty($selected_bundle_ids)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'dc-recargas',
                'dc_msg' => 'landing_shortcode_error',
            ], admin_url('admin.php')));
            exit;
        }

        $detected_countries = array_values(array_unique(array_filter(array_map('strval', $detected_countries))));

        $country_iso = count($detected_countries) === 1 ? (string) $detected_countries[0] : '';

        $existing_for_uniqueness = array_values(array_filter($shortcodes, function ($item) use ($landing_id) {
            return sanitize_text_field((string) ($item['id'] ?? '')) !== $landing_id;
        }));
        $default_key = sanitize_title($name);
        $key = $this->generate_unique_landing_key($key_input !== '' ? $key_input : $default_key, $existing_for_uniqueness);

        $shortcodes[$landing_index]['name'] = $name;
        $shortcodes[$landing_index]['key'] = $key;
        $shortcodes[$landing_index]['title'] = $title;
        $shortcodes[$landing_index]['subtitle'] = $subtitle;
        $shortcodes[$landing_index]['country_iso'] = $country_iso;
        $shortcodes[$landing_index]['bundle_ids'] = $selected_bundle_ids;
        $shortcodes[$landing_index]['featured_bundle_id'] = $featured_bundle_id;
        $shortcodes[$landing_index]['updated_at'] = current_time('mysql');

        update_option('dc_recargas_landing_shortcodes', array_values($shortcodes));

        wp_safe_redirect(add_query_arg([
            'page' => 'dc-recargas',
            'dc_msg' => 'landing_shortcode_updated',
        ], admin_url('admin.php')));
        exit;
    }

    public function sanitize_options($input) {
        $current_options = $this->api->get_options();
        $mode = sanitize_text_field((string) ($input['payment_mode'] ?? 'direct'));
        if (!in_array($mode, ['woocommerce', 'direct'], true)) {
            $mode = 'direct';
        }

        $raw_allowed_gateways = wp_unslash($input['woo_allowed_gateways'] ?? []);
        if (!is_array($raw_allowed_gateways)) {
            $raw_allowed_gateways = [];
        }

        $allowed_gateway_map = [];
        if (class_exists('WC_Payment_Gateways')) {
            $registered_gateways = WC_Payment_Gateways::instance()->payment_gateways();
            foreach ($registered_gateways as $gateway_id => $gateway) {
                $clean_gateway_id = sanitize_key((string) $gateway_id);
                if ($clean_gateway_id !== '') {
                    $allowed_gateway_map[$clean_gateway_id] = true;
                }
            }
        }

        $woo_allowed_gateways = [];
        foreach ($raw_allowed_gateways as $gateway_id) {
            $gateway_id = sanitize_key((string) $gateway_id);
            if ($gateway_id !== '' && isset($allowed_gateway_map[$gateway_id])) {
                $woo_allowed_gateways[] = $gateway_id;
            }
        }
        $woo_allowed_gateways = array_values(array_unique($woo_allowed_gateways));

        $submitted_retry_max_attempts = (int) ($input['submitted_retry_max_attempts'] ?? 4);
        if ($submitted_retry_max_attempts < 1) {
            $submitted_retry_max_attempts = 1;
        }
        if ($submitted_retry_max_attempts > 8) {
            $submitted_retry_max_attempts = 8;
        }

        $submitted_max_window_hours = (int) ($input['submitted_max_window_hours'] ?? 12);
        if ($submitted_max_window_hours < 1) {
            $submitted_max_window_hours = 1;
        }
        if ($submitted_max_window_hours > 168) {
            $submitted_max_window_hours = 168;
        }

        $raw_backoff = (string) ($input['submitted_retry_backoff_minutes'] ?? '10,20,40,80');
        $backoff_parts = preg_split('/[\s,;]+/', $raw_backoff);
        $backoff_values = [];
        foreach ((array) $backoff_parts as $part) {
            $minutes = (int) $part;
            if ($minutes < 1 || $minutes > 720) {
                continue;
            }
            $backoff_values[] = $minutes;
        }
        $backoff_values = array_values(array_unique($backoff_values));
        if (empty($backoff_values)) {
            $backoff_values = [10, 20, 40, 80];
        }
        $submitted_retry_backoff_minutes = implode(',', $backoff_values);

        $submitted_non_retryable_parts = preg_split('/[\s,;]+/', (string) ($input['submitted_non_retryable_codes'] ?? 'InsufficientBalance,AccountNumberInvalid,RechargeNotAllowed'));
        $submitted_non_retryable_codes = [];
        foreach ((array) $submitted_non_retryable_parts as $code) {
            $code = preg_replace('/[^A-Za-z0-9_]/', '', (string) $code);
            if ($code === '') {
                continue;
            }
            $submitted_non_retryable_codes[] = $code;
        }
        $submitted_non_retryable_codes = implode(',', array_values(array_unique($submitted_non_retryable_codes)));

        $submitted_escalation_email = sanitize_email((string) ($input['submitted_escalation_email'] ?? ''));

        // Convert recharge mode select to validate_only and allow_real_recharge flags
        $recharge_mode = sanitize_key((string) ($input['recharge_mode'] ?? 'test_simulate'));
        if (!in_array($recharge_mode, ['test_simulate', 'test_allow_change', 'production'], true)) {
            $recharge_mode = 'test_simulate';
        }

        $validate_only = 1;
        $allow_real_recharge = 0;
        if ($recharge_mode === 'test_allow_change') {
            $validate_only = 1;
            $allow_real_recharge = 1;
        } elseif ($recharge_mode === 'production') {
            $validate_only = 0;
            $allow_real_recharge = 1;
        }

        $sanitized = [
            'api_base' => esc_url_raw(trim((string) ($input['api_base'] ?? 'https://www.dingconnect.com/api/V1'))),
            'api_key' => sanitize_text_field((string) ($input['api_key'] ?? '')),
            'payment_mode' => $mode,
            'woo_allowed_gateways' => $woo_allowed_gateways,
            'recharge_mode' => $recharge_mode,
            'validate_only' => $validate_only,
            'allow_real_recharge' => $allow_real_recharge,
            'submitted_retry_max_attempts' => $submitted_retry_max_attempts,
            'submitted_retry_backoff_minutes' => $submitted_retry_backoff_minutes,
            'submitted_max_window_hours' => $submitted_max_window_hours,
            'submitted_escalation_email' => $submitted_escalation_email,
            'submitted_non_retryable_codes' => $submitted_non_retryable_codes,
        ];

        return $sanitized;
    }

    public function handle_add_bundle() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('dc_add_bundle');

        $bundle = [
            'id' => uniqid('bundle_', true),
            'country_iso' => strtoupper(sanitize_text_field($_POST['country_iso'] ?? '')),
            'label' => sanitize_text_field($_POST['label'] ?? ''),
            'sku_code' => sanitize_text_field($_POST['sku_code'] ?? ''),
            'send_value' => (float) ($_POST['send_value'] ?? 0),
            'send_currency_iso' => strtoupper(sanitize_text_field($_POST['send_currency_iso'] ?? 'USD')),
            'public_price' => (float) ($_POST['public_price'] ?? 0),
            'public_price_currency' => strtoupper(sanitize_text_field($_POST['public_price_currency'] ?? 'EUR')),
            'package_family' => $this->normalize_package_family($_POST['package_family'] ?? '', $_POST['product_type_raw'] ?? ''),
            'product_type_raw' => sanitize_text_field($_POST['product_type_raw'] ?? ''),
            'validity_raw' => sanitize_text_field($_POST['validity_raw'] ?? ''),
            'validity_days' => $this->parse_validity_days($_POST['validity_raw'] ?? ''),
            'provider_name' => sanitize_text_field($_POST['provider_name'] ?? ''),
            'description' => sanitize_text_field($_POST['description'] ?? ''),
            'is_active' => empty($_POST['is_active']) ? 0 : 1,
        ];
        $bundle = array_merge($bundle, $this->extract_rich_bundle_fields_from_request($_POST, false));

        if (empty($bundle['country_iso']) || empty($bundle['label']) || empty($bundle['sku_code'])) {
            wp_safe_redirect(add_query_arg([
                'page' => 'dc-recargas',
                'dc_msg' => 'bundle_error',
            ], admin_url('admin.php')));
            exit;
        }

        $bundles = get_option('dc_recargas_bundles', []);
        if (!is_array($bundles)) {
            $bundles = [];
        }
        $bundles[] = $bundle;
        update_option('dc_recargas_bundles', $bundles);

        wp_safe_redirect(add_query_arg([
            'page' => 'dc-recargas',
            'dc_msg' => 'bundle_added',
        ], admin_url('admin.php')));
        exit;
    }

    public function handle_update_bundle() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('dc_update_bundle');

        $bundle_id = sanitize_text_field(wp_unslash($_POST['bundle_id'] ?? ''));
        $bundles = get_option('dc_recargas_bundles', []);
        if (!is_array($bundles)) {
            $bundles = [];
        }
        $index = $this->find_bundle_index_by_id($bundles, $bundle_id);

        if ($index === -1) {
            wp_safe_redirect(add_query_arg([
                'page' => 'dc-recargas',
                'dc_msg' => 'bundle_not_found',
            ], admin_url('admin.php')));
            exit;
        }

        $existing_bundle = is_array($bundles[$index]) ? $bundles[$index] : [];

        $bundle = [
            'id' => $bundle_id,
            'country_iso' => strtoupper(sanitize_text_field(wp_unslash($_POST['country_iso'] ?? ''))),
            'label' => sanitize_text_field(wp_unslash($_POST['label'] ?? '')),
            'sku_code' => sanitize_text_field(wp_unslash($_POST['sku_code'] ?? '')),
            'send_value' => (float) (wp_unslash($_POST['send_value'] ?? 0)),
            'send_currency_iso' => strtoupper(sanitize_text_field(wp_unslash($_POST['send_currency_iso'] ?? 'USD'))),
            'public_price' => (float) (wp_unslash($_POST['public_price'] ?? 0)),
            'public_price_currency' => strtoupper(sanitize_text_field(wp_unslash($_POST['public_price_currency'] ?? 'EUR'))),
            'package_family' => $this->normalize_package_family(wp_unslash($_POST['package_family'] ?? ''), wp_unslash($_POST['product_type_raw'] ?? '')),
            'product_type_raw' => sanitize_text_field(wp_unslash($_POST['product_type_raw'] ?? '')),
            'validity_raw' => sanitize_text_field(wp_unslash($_POST['validity_raw'] ?? '')),
            'validity_days' => $this->parse_validity_days(wp_unslash($_POST['validity_raw'] ?? '')),
            'provider_name' => sanitize_text_field(wp_unslash($_POST['provider_name'] ?? '')),
            'description' => sanitize_text_field(wp_unslash($_POST['description'] ?? '')),
            'is_active' => empty($_POST['is_active']) ? 0 : 1,
        ];
        $bundle = array_merge($existing_bundle, $bundle, $this->extract_rich_bundle_fields_from_request($_POST, true));

        if (empty($bundle['country_iso']) || empty($bundle['label']) || empty($bundle['sku_code'])) {
            wp_safe_redirect(add_query_arg([
                'page' => 'dc-recargas',
                'dc_msg' => 'bundle_error',
                'dc_edit_bundle' => $bundle_id,
            ], admin_url('admin.php')));
            exit;
        }

        // Validación de duplicados deshabilitada — se permiten múltiples bundles con el mismo SKU.
        // if ($this->bundle_exists_by_country_sku($bundle['country_iso'], $bundle['sku_code'], $bundle_id)) { ... }

        $bundles[$index] = $bundle;
        update_option('dc_recargas_bundles', $bundles);

        wp_safe_redirect(add_query_arg([
            'page' => 'dc-recargas',
            'dc_tab' => 'tab_saved',
            'dc_msg' => 'bundle_updated',
        ], admin_url('admin.php')));
        exit;
    }

    public function handle_toggle_bundle() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('dc_toggle_bundle');

        $bundle_id = sanitize_text_field($_GET['bundle_id'] ?? '');
        $bundles = get_option('dc_recargas_bundles', []);
        if (!is_array($bundles)) {
            $bundles = [];
        }
        $index = $this->find_bundle_index_by_id($bundles, $bundle_id);

        if ($index === -1) {
            wp_safe_redirect(add_query_arg([
                'page' => 'dc-recargas',
                'dc_msg' => 'bundle_not_found',
            ], admin_url('admin.php')));
            exit;
        }

        $bundles[$index]['is_active'] = empty($bundles[$index]['is_active']) ? 1 : 0;
        $new_state = !empty($bundles[$index]['is_active']) ? 'active' : 'inactive';
        update_option('dc_recargas_bundles', $bundles);

        wp_safe_redirect(add_query_arg([
            'page' => 'dc-recargas',
            'dc_msg' => 'bundle_toggled',
            'dc_state' => $new_state,
        ], admin_url('admin.php')));
        exit;
    }

    public function handle_delete_bundle() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('dc_delete_bundle');

        $id = sanitize_text_field($_GET['bundle_id'] ?? '');
        $bundles = get_option('dc_recargas_bundles', []);
        if (!is_array($bundles)) {
            $bundles = [];
        }

        $bundles = array_values(array_filter($bundles, function ($bundle) use ($id) {
            return ($bundle['id'] ?? '') !== $id;
        }));

        update_option('dc_recargas_bundles', $bundles);

        wp_safe_redirect(add_query_arg([
            'page' => 'dc-recargas',
            'dc_msg' => 'bundle_deleted',
        ], admin_url('admin.php')));
        exit;
    }

    public function handle_bulk_delete_bundles() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('dc_bulk_delete_bundles');

        $raw_ids = wp_unslash($_POST['bundle_ids'] ?? []);
        $ids = [];

        if (is_array($raw_ids)) {
            foreach ($raw_ids as $id) {
                $clean_id = sanitize_text_field((string) $id);
                if ($clean_id !== '') {
                    $ids[] = $clean_id;
                }
            }
        }

        $ids = array_values(array_unique($ids));

        if (empty($ids)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'dc-recargas',
                'dc_msg' => 'bundle_bulk_empty',
            ], admin_url('admin.php')));
            exit;
        }

        $bundles = get_option('dc_recargas_bundles', []);
        if (!is_array($bundles)) {
            $bundles = [];
        }
        $total_before = count($bundles);

        $bundles = array_values(array_filter($bundles, function ($bundle) use ($ids) {
            $bundle_id = (string) ($bundle['id'] ?? '');
            return !in_array($bundle_id, $ids, true);
        }));

        $deleted_count = $total_before - count($bundles);

        update_option('dc_recargas_bundles', $bundles);

        wp_safe_redirect(add_query_arg([
            'page' => 'dc-recargas',
            'dc_msg' => 'bundle_bulk_deleted',
            'dc_count' => $deleted_count,
        ], admin_url('admin.php')));
        exit;
    }

    public function ajax_create_bundle_from_catalog() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No autorizado.'], 403);
        }

        check_ajax_referer('dc_catalog_admin', 'nonce');

        $country_iso = strtoupper(sanitize_text_field(wp_unslash($_POST['country_iso'] ?? '')));
        $sku_code = sanitize_text_field(wp_unslash($_POST['sku_code'] ?? ''));
        $operator = sanitize_text_field(wp_unslash($_POST['operator'] ?? ''));
        $receive = sanitize_text_field(wp_unslash($_POST['receive'] ?? ''));
        $send_value = (float) (wp_unslash($_POST['send_value'] ?? 0));
        $send_currency_iso = strtoupper(sanitize_text_field(wp_unslash($_POST['send_currency_iso'] ?? 'EUR')));
        $package_group = sanitize_text_field(wp_unslash($_POST['package_group'] ?? ''));
        $product_type_raw = sanitize_text_field(wp_unslash($_POST['product_type'] ?? ''));
        $validity_raw = sanitize_text_field(wp_unslash($_POST['validity'] ?? ''));
        $public_price = (float) (wp_unslash($_POST['public_price'] ?? 0));
        $public_price_currency = strtoupper(sanitize_text_field(wp_unslash($_POST['public_price_currency'] ?? 'EUR')));
        $is_active = empty($_POST['is_active']) ? 0 : 1;

        if (empty($country_iso) || empty($sku_code) || empty($operator)) {
            wp_send_json_error(['message' => 'Faltan datos obligatorios para crear el bundle.'], 400);
        }

        // Validación de duplicados deshabilitada — se permiten múltiples bundles con el mismo SKU.
        // if ($this->bundle_exists_by_country_sku($country_iso, $sku_code)) { ... }

        $bundle = [
            'id' => uniqid('bundle_', true),
            'country_iso' => $country_iso,
            'label' => $operator . ' - ' . (!empty($receive) ? $receive : $sku_code),
            'sku_code' => $sku_code,
            'send_value' => $send_value,
            'send_currency_iso' => !empty($send_currency_iso) ? $send_currency_iso : 'EUR',
            'public_price' => $public_price,
            'public_price_currency' => !empty($public_price_currency) ? $public_price_currency : 'EUR',
            'package_family' => $this->normalize_package_family($package_group, $product_type_raw),
            'product_type_raw' => $product_type_raw,
            'validity_raw' => $validity_raw,
            'validity_days' => $this->parse_validity_days($validity_raw),
            'provider_name' => $operator,
            'description' => $receive,
            'is_active' => $is_active,
        ];
        $bundle = array_merge($bundle, $this->extract_rich_bundle_fields_from_request($_POST, true));

        $bundles = get_option('dc_recargas_bundles', []);
        if (!is_array($bundles)) {
            $bundles = [];
        }
        $bundles[] = $bundle;
        update_option('dc_recargas_bundles', $bundles);

        wp_send_json_success([
            'message' => 'Bundle creado correctamente desde catálogo.',
            'bundle' => $bundle,
        ]);
    }

    public function ajax_fetch_api_products() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No autorizado.'], 403);
            return;
        }

        check_ajax_referer('dc_catalog_admin', 'nonce');

        $country_iso = strtoupper(sanitize_text_field(wp_unslash($_GET['country_iso'] ?? '')));

        if (strlen($country_iso) !== 2 || !ctype_alpha($country_iso)) {
            wp_send_json_error(['message' => 'Código ISO de país inválido. Debe tener exactamente 2 letras, ej: CU, CO, MX.']);
            return;
        }

        $raw = $this->api->get_products_by_country($country_iso);

        if (is_wp_error($raw)) {
            wp_send_json_error(['message' => $raw->get_error_message()]);
            return;
        }

        if (empty($raw) || !is_array($raw)) {
            wp_send_json_success(['items' => [], 'country_iso' => $country_iso, 'total' => 0]);
            return;
        }

        $raw_items = $raw['Items'] ?? [];
        if (!is_array($raw_items)) {
            $raw_items = [];
        }

        // Enriquecer con LogoUrl y ProviderName desde GetProviders (no vienen en GetProducts).
        $provider_codes = array_values(array_unique(array_filter(array_map(
            fn($p) => (string) ($p['ProviderCode'] ?? ''),
            $raw_items
        ))));
        $provider_map = [];
        if (!empty($provider_codes)) {
            $providers_raw = $this->api->get_providers_by_codes($provider_codes);
            $providers_list = !is_wp_error($providers_raw) ? ($providers_raw['Result'] ?? $providers_raw['Items'] ?? []) : [];
            if (!empty($providers_list) && is_array($providers_list)) {
                foreach ($providers_list as $prov) {
                    $code = (string) ($prov['ProviderCode'] ?? '');
                    if ($code !== '') {
                        $provider_map[$code] = $prov;
                    }
                }
            }
        }

        $items = [];
        $group_counts = [];
        $group_labels = $this->get_api_package_group_labels();
        foreach ($raw_items as $product) {
            $provider_code_key = (string) ($product['ProviderCode'] ?? '');
            $provider_details  = $provider_map[$provider_code_key] ?? [];
            // Inyectar campos del proveedor en el producto para reutilizar la lógica existente.
            if (empty($product['LogoUrl']) && !empty($provider_details['LogoUrl'])) {
                $product['LogoUrl'] = $provider_details['LogoUrl'];
            }
            if (empty($product['ProviderName']) && !empty($provider_details['Name'])) {
                $product['ProviderName'] = $provider_details['Name'];
            }
            $benefits = [];
            if (!empty($product['Benefits']) && is_array($product['Benefits'])) {
                foreach ($product['Benefits'] as $benefit) {
                    $amount = trim((string) ($benefit['Amount'] ?? ''));
                    $type   = trim((string) ($benefit['BenefitType'] ?? ''));
                    if ($amount !== '') {
                        $benefits[] = $type !== '' ? $amount . ' ' . $type : $amount;
                    }
                }
            }

            $receive = !empty($benefits) ? implode(', ', $benefits) : ($product['DefaultDisplayText'] ?? '');
            $label   = ($product['DefaultDisplayText'] ?? '') ?: ($product['SkuCode'] ?? '');
            $sku_code = (string) ($product['SkuCode'] ?? '');
            $minimum_price = is_array($product['Minimum'] ?? null) ? $product['Minimum'] : [];
            $maximum_price = is_array($product['Maximum'] ?? null) ? $product['Maximum'] : [];
            $send_value = isset($minimum_price['SendValue']) ? (float) $minimum_price['SendValue'] : (isset($product['SendValue']) ? (float) $product['SendValue'] : 0);
            $send_currency_iso = sanitize_text_field((string) ($minimum_price['SendCurrencyIso'] ?? ($product['SendCurrencyIso'] ?? 'USD')));
            $receive_value = isset($minimum_price['ReceiveValue']) ? (float) $minimum_price['ReceiveValue'] : (isset($product['ReceiveValue']) ? (float) $product['ReceiveValue'] : 0);
            $receive_currency_iso = sanitize_text_field((string) ($minimum_price['ReceiveCurrencyIso'] ?? ($product['ReceiveCurrencyIso'] ?? $send_currency_iso)));
            $minimum_send_value = isset($minimum_price['SendValue']) ? (float) $minimum_price['SendValue'] : $send_value;
            $maximum_send_value = isset($maximum_price['SendValue']) ? (float) $maximum_price['SendValue'] : $send_value;
            $minimum_receive_value = isset($minimum_price['ReceiveValue']) ? (float) $minimum_price['ReceiveValue'] : $receive_value;
            $maximum_receive_value = isset($maximum_price['ReceiveValue']) ? (float) $maximum_price['ReceiveValue'] : $receive_value;
            $is_range = abs($maximum_send_value - $minimum_send_value) > 0.00001;

            $package_group = $this->classify_api_package_group(
                $product,
                $receive,
                $label
            );

            if (!isset($group_counts[$package_group])) {
                $group_counts[$package_group] = 0;
            }
            $group_counts[$package_group]++;

            $items[] = [
                'country_iso'       => $country_iso,
                'sku_code'          => $sku_code,
                'operator'          => ($product['ProviderName'] ?? '') !== '' ? ($product['ProviderName'] ?? '') : ($product['ProviderCode'] ?? ''),
                'provider_code'     => sanitize_text_field((string) ($product['ProviderCode'] ?? '')),
                'product_type'      => $product['ProductType'] ?? '',
                'label'             => $label,
                'send_value'        => $send_value,
                'send_currency_iso' => $send_currency_iso,
                'receive_value'     => $receive_value,
                'receive_currency_iso' => $receive_currency_iso,
                'receive_value_excluding_tax' => isset($minimum_price['ReceiveValueExcludingTax']) ? (float) $minimum_price['ReceiveValueExcludingTax'] : (isset($product['ReceiveValueExcludingTax']) ? (float) $product['ReceiveValueExcludingTax'] : $receive_value),
                'minimum_send_value' => $minimum_send_value,
                'maximum_send_value' => $maximum_send_value,
                'minimum_receive_value' => $minimum_receive_value,
                'maximum_receive_value' => $maximum_receive_value,
                'customer_fee' => isset($minimum_price['CustomerFee']) ? (float) $minimum_price['CustomerFee'] : (isset($product['CustomerFee']) ? (float) $product['CustomerFee'] : 0),
                'distributor_fee' => isset($minimum_price['DistributorFee']) ? (float) $minimum_price['DistributorFee'] : (isset($product['DistributorFee']) ? (float) $product['DistributorFee'] : 0),
                'tax_rate' => isset($minimum_price['TaxRate']) ? (float) $minimum_price['TaxRate'] : (isset($product['TaxRate']) ? (float) $product['TaxRate'] : 0),
                'tax_name' => sanitize_text_field((string) ($minimum_price['TaxName'] ?? ($product['TaxName'] ?? ''))),
                'tax_calculation' => sanitize_text_field((string) ($minimum_price['TaxCalculation'] ?? ($product['TaxCalculation'] ?? ''))),
                'receive'           => $receive,
                'validity'          => $product['ValidityPeriodIso'] ?? '',
                'default_display_text' => sanitize_text_field((string) ($product['DefaultDisplayText'] ?? '')),
                'display_text' => sanitize_text_field((string) ($product['DefaultDisplayText'] ?? $label)),
                'description' => sanitize_text_field((string) ($product['Description'] ?? '')),
                'description_markdown' => sanitize_text_field((string) ($product['DescriptionMarkdown'] ?? '')),
                'read_more_markdown' => sanitize_text_field((string) ($product['ReadMoreMarkdown'] ?? '')),
                'additional_information' => sanitize_text_field((string) ($product['AdditionalInformation'] ?? '')),
                'is_promotion' => !empty($product['IsPromotion']),
                'is_range' => $is_range,
                'benefits' => $benefits,
                'redemption_mechanism' => sanitize_text_field((string) ($product['RedemptionMechanism'] ?? '')),
                'processing_mode' => sanitize_text_field((string) ($product['ProcessingMode'] ?? '')),
                'lookup_bills_required' => !empty($product['LookupBillsRequired']),
                'setting_definitions' => is_array($product['SettingDefinitions'] ?? null) ? $product['SettingDefinitions'] : [],
                'validation_regex' => sanitize_text_field((string) ($product['ValidationRegex'] ?? '')),
                'customer_care_number' => sanitize_text_field((string) ($product['CustomerCareNumber'] ?? '')),
                'logo_url' => esc_url_raw((string) ($product['LogoUrl'] ?? '')),
                'payment_types' => array_values(array_filter(array_map('sanitize_text_field', (array) ($product['PaymentTypes'] ?? [])))),
                'uat_number' => sanitize_text_field((string) ($product['UatNumber'] ?? '')),
                'region_code' => sanitize_text_field((string) ($product['RegionCode'] ?? '')),
                'region_codes' => array_values(array_filter(array_map('sanitize_text_field', (array) ($product['RegionCodes'] ?? [])))),
                'package_group'     => $package_group,
                'package_group_label' => $group_labels[$package_group] ?? ($group_labels['other'] ?? 'Otros'),
                'catalog_source'    => 'api',
            ];
        }

        wp_send_json_success([
            'items' => $items,
            'country_iso' => $country_iso,
            'total' => count($items),
            'group_counts' => $group_counts,
            'group_labels' => $group_labels,
        ]);
    }

    private function get_api_package_group_labels() {
        return [
            'saldo' => 'Saldo / top-up',
            'data' => 'Datos',
            'combo' => 'Combo / voz + datos',
            'other' => 'Otros',
        ];
    }

    private function classify_api_package_group($product, $receive = '', $label = '') {
        $product_type = strtolower(trim((string) ($product['ProductType'] ?? '')));
        $benefit_types = [];

        if (!empty($product['Benefits']) && is_array($product['Benefits'])) {
            foreach ($product['Benefits'] as $benefit) {
                $benefit_types[] = strtolower(trim((string) ($benefit['BenefitType'] ?? '')));
            }
        }

        $haystack = strtolower(trim(implode(' ', array_filter([
            (string) $receive,
            (string) $label,
            (string) ($product['DefaultDisplayText'] ?? ''),
            (string) ($product['Description'] ?? ''),
            implode(' ', $benefit_types),
            (string) ($product['ProviderCode'] ?? ''),
            (string) ($product['SkuCode'] ?? ''),
            $product_type,
        ]))));

        $has_data = preg_match('/\b(?:\d+(?:[\.,]\d+)?\s?(?:gb|mb)|data|internet|social|whatsapp|facebook|instagram|tiktok|youtube|stream(?:ing)?|navegaci(?:on|o)n|4g|5g|lte)\b/i', $haystack) === 1;
        $has_voice_sms = preg_match('/\b(?:\d+(?:[\.,]\d+)?\s?(?:min|mins|minutes|sms)|unlimited\s+(?:minutes|calls|texts)|voice|calls?|mins?|minutes|sms|texts|on-?net|off-?net|talk)\b/i', $haystack) === 1;
        $looks_like_topup = $this->looks_like_topup_amount($receive, $label, $product);

        if ($has_data && $has_voice_sms) {
            return 'combo';
        }

        if (strpos($product_type, 'bundle') !== false) {
            return 'combo';
        }

        if ($has_data || strpos($product_type, 'data') !== false) {
            return 'data';
        }

        if (strpos($product_type, 'top-up') !== false || strpos($product_type, 'topup') !== false || $looks_like_topup) {
            return 'saldo';
        }

        return 'other';
    }

    private function looks_like_topup_amount($receive, $label, $product) {
        $receive = trim((string) $receive);
        $label = trim((string) $label);

        if (!empty($product['Minimum']['SendValue']) && !empty($product['Maximum']['SendValue'])) {
            return true;
        }

        if ($receive !== '' && preg_match('/\b(?:min|max)\b/i', $receive)) {
            return true;
        }

        if ($receive !== '' && preg_match('/^[A-Z]{3}\s?[0-9]/', $receive)) {
            return true;
        }

        if ($label !== '' && preg_match('/\btop\s?-?up\b/i', $label)) {
            return true;
        }

        return false;
    }

    private function normalize_package_family($raw_family, $raw_product_type = '') {
        $family = strtolower(trim((string) $raw_family));
        $product_type = strtolower(trim((string) $raw_product_type));

        $aliases = [
            'saldo' => 'topup',
            'top-up' => 'topup',
            'topup' => 'topup',
            'data' => 'data',
            'combo' => 'combo',
            'voucher' => 'voucher',
            'pin' => 'voucher',
            'dth' => 'dth',
            'other' => 'other',
        ];

        if ($family !== '' && isset($aliases[$family])) {
            return $aliases[$family];
        }

        if (strpos($product_type, 'top-up') !== false || strpos($product_type, 'topup') !== false) {
            return 'topup';
        }

        if (strpos($product_type, 'data') !== false) {
            return 'data';
        }

        if (strpos($product_type, 'bundle') !== false) {
            return 'combo';
        }

        if (strpos($product_type, 'voucher') !== false || strpos($product_type, 'pin') !== false) {
            return 'voucher';
        }

        if (strpos($product_type, 'dth') !== false) {
            return 'dth';
        }

        return 'other';
    }

    private function parse_validity_days($raw_validity) {
        $validity = trim((string) $raw_validity);
        if ($validity === '' || strtoupper($validity) === 'N/A') {
            return null;
        }

        if (preg_match('/^P(\d+)D$/i', $validity, $iso_match)) {
            return (int) $iso_match[1];
        }

        if (preg_match('/\b(\d{1,4})\s*(?:d|day|days|dia|dias|días)\b/i', $validity, $days_match)) {
            return (int) $days_match[1];
        }

        return null;
    }

    private function extract_rich_bundle_fields_from_request($source, $unslash = false) {
        if (!is_array($source)) {
            return [];
        }

        $fields = [];
        $has = function ($key) use ($source) {
            return array_key_exists($key, $source);
        };
        $get = function ($key, $default = '') use ($source, $unslash) {
            $value = $source[$key] ?? $default;
            return $unslash ? wp_unslash($value) : $value;
        };

        if ($has('provider_code')) {
            $fields['provider_code'] = sanitize_text_field((string) $get('provider_code'));
        }
        if ($has('region_code')) {
            $fields['region_code'] = sanitize_text_field((string) $get('region_code'));
        }
        if ($has('region_codes')) {
            $fields['region_codes'] = $this->sanitize_text_list_input($get('region_codes'));
        }

        foreach ([
            'receive_value',
            'receive_value_excluding_tax',
            'minimum_send_value',
            'maximum_send_value',
            'minimum_receive_value',
            'maximum_receive_value',
            'customer_fee',
            'distributor_fee',
            'tax_rate',
        ] as $float_field) {
            if ($has($float_field)) {
                $fields[$float_field] = (float) $get($float_field);
            }
        }

        foreach ([
            'receive_currency_iso',
            'tax_name',
            'tax_calculation',
            'default_display_text',
            'display_text',
            'description_markdown',
            'read_more_markdown',
            'additional_information',
            'redemption_mechanism',
            'processing_mode',
            'validation_regex',
            'customer_care_number',
            'logo_url',
            'uat_number',
        ] as $text_field) {
            if ($has($text_field)) {
                $fields[$text_field] = sanitize_text_field((string) $get($text_field));
            }
        }

        if ($has('is_promotion')) {
            $fields['is_promotion'] = $this->sanitize_bool_input($get('is_promotion')) ? 1 : 0;
        }
        if ($has('is_range')) {
            $fields['is_range'] = $this->sanitize_bool_input($get('is_range')) ? 1 : 0;
        }
        if ($has('lookup_bills_required')) {
            $fields['lookup_bills_required'] = $this->sanitize_bool_input($get('lookup_bills_required')) ? 1 : 0;
        }

        if ($has('benefits')) {
            $fields['benefits'] = $this->sanitize_text_list_input($get('benefits'));
        }
        if ($has('payment_types')) {
            $fields['payment_types'] = $this->sanitize_text_list_input($get('payment_types'));
        }
        if ($has('setting_definitions')) {
            $fields['setting_definitions'] = $this->sanitize_setting_definitions_input($get('setting_definitions'));
        }

        return $fields;
    }

    private function sanitize_bool_input($value) {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function sanitize_text_list_input($value) {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (is_string($value)) {
            $value = preg_split('/[,\n\r]+/', $value) ?: [];
        }

        $items = [];
        foreach ((array) $value as $entry) {
            $clean = sanitize_text_field((string) $entry);
            if ($clean !== '') {
                $items[] = $clean;
            }
        }

        return array_values(array_unique($items));
    }

    private function sanitize_setting_definitions_input($value) {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (!is_array($value)) {
            return [];
        }

        $definitions = [];
        foreach ($value as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $name = sanitize_text_field((string) ($definition['Name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $allowed_values = [];
            foreach ((array) ($definition['AllowedValues'] ?? $definition['Values'] ?? []) as $allowed) {
                $clean_allowed = sanitize_text_field((string) $allowed);
                if ($clean_allowed !== '') {
                    $allowed_values[] = $clean_allowed;
                }
            }

            $definitions[] = [
                'Name' => $name,
                'Description' => sanitize_text_field((string) ($definition['Description'] ?? '')),
                'IsMandatory' => !empty($definition['IsMandatory']),
                'Type' => sanitize_text_field((string) ($definition['Type'] ?? $definition['DataType'] ?? 'text')),
                'ValidationRegex' => sanitize_text_field((string) ($definition['ValidationRegex'] ?? '')),
                'MinLength' => isset($definition['MinLength']) ? (int) $definition['MinLength'] : 0,
                'MaxLength' => isset($definition['MaxLength']) ? (int) $definition['MaxLength'] : 0,
                'MinValue' => isset($definition['MinValue']) ? (float) $definition['MinValue'] : 0,
                'MaxValue' => isset($definition['MaxValue']) ? (float) $definition['MaxValue'] : 0,
                'AllowedValues' => array_values(array_unique($allowed_values)),
            ];
        }

        return $definitions;
    }

    public function render_page() {
        $options = $this->api->get_options();
        $bundles = get_option('dc_recargas_bundles', []);
        if (!is_array($bundles)) {
            $bundles = [];
        }
        $wc_gateways = [];
        if (class_exists('WC_Payment_Gateways')) {
            $wc_gateways = WC_Payment_Gateways::instance()->payment_gateways();
        }
        $selected_woo_gateways = array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($options['woo_allowed_gateways'] ?? [])))));
        $landing_shortcodes = get_option('dc_recargas_landing_shortcodes', []);
        if (!is_array($landing_shortcodes)) {
            $landing_shortcodes = [];
        }
        $msg = sanitize_text_field($_GET['dc_msg'] ?? '');
        $editing_bundle_id = sanitize_text_field($_GET['dc_edit_bundle'] ?? '');
        $editing_bundle = $this->find_bundle_by_id($bundles, $editing_bundle_id);
        $editing_landing_id = sanitize_text_field($_GET['dc_edit_landing'] ?? '');
        $editing_landing = $this->find_landing_by_id($landing_shortcodes, $editing_landing_id);
        $catalog_nonce = wp_create_nonce('dc_catalog_admin');
        $active_tab = 'tab_setup';
        $landing_country_choices = $this->get_landing_country_choices($bundles, $landing_shortcodes);

        // Extract unique values from existing bundles for datalist dropdowns.
        $dl_country_iso     = array_column($landing_country_choices, 'iso');
        $dl_label           = array_values(array_unique(array_filter(array_column($bundles, 'label'))));
        $dl_send_currency   = array_values(array_unique(array_filter(array_column($bundles, 'send_currency_iso'))));
        $dl_public_currency = array_values(array_unique(array_filter(array_column($bundles, 'public_price_currency'))));
        $dl_provider_name   = array_values(array_unique(array_filter(array_column($bundles, 'provider_name'))));
        sort($dl_country_iso);
        sort($dl_label);
        sort($dl_send_currency);
        sort($dl_public_currency);
        sort($dl_provider_name);

        $total_bundles = count($bundles);
        $active_bundles = count(array_filter($bundles, function ($bundle) {
            return !empty($bundle['is_active']);
        }));
        $inactive_bundles = max(0, $total_bundles - $active_bundles);
        $landing_count = count($landing_shortcodes);
        $payment_mode_label = (($options['payment_mode'] ?? 'direct') === 'woocommerce') ? 'WooCommerce' : 'Directo';

        $requested_tab = sanitize_key($_GET['dc_tab'] ?? '');
        if (in_array($requested_tab, ['tab_setup', 'tab_catalog', 'tab_saved', 'tab_landings', 'tab_logs'], true)) {
            $active_tab = $requested_tab;
        }

        if (!empty($editing_bundle)) {
            $active_tab = 'tab_saved';
        }

        if (in_array($msg, ['bundle_error', 'bundle_duplicate'], true)) {
            $active_tab = !empty($editing_bundle) ? 'tab_saved' : 'tab_catalog';
        }

        if (in_array($msg, ['bundle_added', 'bundle_updated', 'bundle_toggled', 'bundle_deleted', 'bundle_bulk_deleted', 'bundle_bulk_empty'], true)) {
            $active_tab = 'tab_saved';
        }

        if (in_array($msg, ['landing_shortcode_added', 'landing_shortcode_updated', 'landing_shortcode_cloned', 'landing_shortcode_deleted', 'landing_shortcode_error'], true)) {
            $active_tab = 'tab_landings';
        }

        if (!empty($editing_landing)) {
            $active_tab = 'tab_landings';
        }
        ?>
        <div class="wrap dc-admin-wrap">
            <?php settings_errors('dc_recargas_options'); ?>
            <div class="dc-admin-hero">
                <div class="dc-admin-hero__content">
                    <p><em>Hecho por Cambiodigital.net, personalizado para cubakilos.com.</em></p>
                </div>
                <div class="dc-admin-hero__meta">
                    <span class="dc-admin-chip"><?php echo esc_html($payment_mode_label); ?></span>
                </div>
            </div>

            <div class="dc-admin-kpis" aria-label="Resumen rápido de operación">
                <article class="dc-admin-kpi">
                    <p class="dc-admin-kpi__label">Bundles totales</p>
                    <p class="dc-admin-kpi__value"><?php echo esc_html((string) $total_bundles); ?></p>
                </article>
                <article class="dc-admin-kpi">
                    <p class="dc-admin-kpi__label">Bundles activos</p>
                    <p class="dc-admin-kpi__value"><?php echo esc_html((string) $active_bundles); ?></p>
                </article>
                <article class="dc-admin-kpi">
                    <p class="dc-admin-kpi__label">Bundles inactivos</p>
                    <p class="dc-admin-kpi__value"><?php echo esc_html((string) $inactive_bundles); ?></p>
                </article>
                <article class="dc-admin-kpi">
                    <p class="dc-admin-kpi__label">Landings dinámicas</p>
                    <p class="dc-admin-kpi__value"><?php echo esc_html((string) $landing_count); ?></p>
                </article>
            </div>

            <?php $this->render_notice($msg); ?>

            <style>
                .dc-admin-wrap {
                    --dc-bg: #f4f7fc;
                    --dc-card: #ffffff;
                    --dc-text: #111827;
                    --dc-muted: #667085;
                    --dc-primary: #0f4aa3;
                    --dc-primary-soft: #eaf2ff;
                    --dc-border: #d7e2f2;
                    --dc-shadow: 0 18px 42px rgba(15, 23, 42, 0.07);
                    background:
                        radial-gradient(circle at 0% 0%, #e8f2ff 0%, rgba(232, 242, 255, 0.08) 42%),
                        linear-gradient(180deg, #f8fbff 0%, #f3f7fd 100%);
                    padding: 18px 20px 24px;
                    border-radius: 18px;
                    border: 1px solid #dbe7f8;
                }

                .dc-admin-hero {
                    display: flex;
                    justify-content: space-between;
                    gap: 18px;
                    align-items: flex-start;
                    margin-bottom: 14px;
                }

                .dc-admin-wrap h1 {
                    margin: 0;
                    font-size: 31px;
                    line-height: 1.18;
                    letter-spacing: -0.02em;
                    color: var(--dc-text);
                }

                .dc-admin-hero__content {
                    max-width: 920px;
                }

                .dc-admin-wrap > .dc-admin-hero p {
                    margin: 8px 0 0;
                    max-width: 860px;
                    color: #334155;
                    font-size: 14px;
                }

                .dc-admin-wrap > .dc-admin-hero p em {
                    color: var(--dc-muted);
                    font-style: normal;
                    font-weight: 500;
                }

                .dc-admin-hero__meta {
                    display: flex;
                    flex-wrap: wrap;
                    justify-content: flex-end;
                    gap: 8px;
                    min-width: 210px;
                }

                .dc-admin-chip {
                    display: inline-flex;
                    align-items: center;
                    border-radius: 999px;
                    border: 1px solid #cddcf3;
                    background: #ffffff;
                    color: #1d4c95;
                    font-size: 12px;
                    font-weight: 700;
                    letter-spacing: 0.01em;
                    padding: 6px 12px;
                }

                .dc-admin-kpis {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                    gap: 10px;
                    margin: 0 0 18px;
                }

                .dc-admin-kpi {
                    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
                    border: 1px solid var(--dc-border);
                    border-radius: 12px;
                    padding: 12px 14px;
                    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
                }

                .dc-admin-kpi__label {
                    margin: 0;
                    color: #64748b;
                    font-size: 12px;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    font-weight: 700;
                }

                .dc-admin-kpi__value {
                    margin: 8px 0 0;
                    color: #0f172a;
                    font-size: 26px;
                    line-height: 1;
                    font-weight: 700;
                }

                .dc-admin-tabs {
                    margin-top: 20px;
                }

                .dc-admin-wrap .nav-tab-wrapper {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    border-bottom: 0 !important;
                    padding: 0;
                    margin: 0;
                    position: sticky;
                    top: 32px;
                    z-index: 10;
                    backdrop-filter: blur(4px);
                }

                .dc-admin-wrap .nav-tab {
                    float: none;
                    border: 1px solid var(--dc-border);
                    background: rgba(255, 255, 255, 0.95);
                    color: #334155;
                    border-radius: 999px;
                    padding: 9px 14px;
                    margin: 0;
                    font-weight: 600;
                    transition: color 0.15s ease, border-color 0.15s ease, background 0.15s ease, transform 0.15s ease;
                }

                .dc-admin-wrap .nav-tab:hover {
                    color: var(--dc-primary);
                    border-color: #bdd0ef;
                    transform: translateY(-1px);
                }

                .dc-admin-wrap .nav-tab.nav-tab-active,
                .dc-admin-wrap .nav-tab.nav-tab-active:focus,
                .dc-admin-wrap .nav-tab.nav-tab-active:focus:active,
                .dc-admin-wrap .nav-tab.nav-tab-active:hover {
                    border-color: transparent;
                    background: linear-gradient(135deg, #145dc9 0%, #0f4aa3 100%);
                    color: #ffffff;
                }

                .dc-admin-wrap .nav-tab:focus-visible,
                .dc-admin-wrap .button:focus-visible,
                .dc-admin-wrap button:focus-visible,
                .dc-admin-wrap input:focus-visible,
                .dc-admin-wrap select:focus-visible,
                .dc-admin-wrap textarea:focus-visible {
                    outline: 2px solid #2563eb;
                    outline-offset: 1px;
                    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
                }

                .dc-tab-panel {
                    display: none;
                    margin-top: 12px;
                    background: var(--dc-card);
                    border: 1px solid var(--dc-border);
                    border-radius: 16px;
                    padding: 20px 22px 22px;
                    box-shadow: var(--dc-shadow);
                }

                .dc-tab-panel.is-active {
                    display: block;
                }

                .dc-tab-panel > h2:first-child {
                    margin-top: 0;
                }

                .dc-tab-panel h2 {
                    color: var(--dc-text);
                    font-size: 22px;
                    line-height: 1.3;
                    letter-spacing: -0.01em;
                }

                .dc-tab-panel h3 {
                    color: #1e293b;
                    font-size: 17px;
                    margin-bottom: 8px;
                }

                .dc-tab-panel p {
                    color: #334155;
                }

                .dc-tab-panel hr {
                    border: 0;
                    border-top: 1px solid #e2e8f0;
                    margin: 24px 0;
                }

                .dc-tab-panel.is-special {
                    background: linear-gradient(180deg, #ffffff 0%, #f3f8ff 100%);
                    border-left: 4px solid #0f4aa3;
                }

                .dc-admin-wrap .form-table th {
                    color: #1f2937;
                    font-weight: 600;
                    width: 260px;
                }

                .dc-admin-wrap .form-table input[type="text"],
                .dc-admin-wrap .form-table input[type="url"],
                .dc-admin-wrap .form-table input[type="number"],
                .dc-admin-wrap .form-table select,
                .dc-admin-wrap .form-table textarea {
                    border: 1px solid #c9d6ea;
                    border-radius: 8px;
                }

                .dc-admin-wrap .button-primary {
                    background: #0f4aa3;
                    border-color: #0f4aa3;
                }

                .dc-admin-wrap .button-primary:hover,
                .dc-admin-wrap .button-primary:focus {
                    background: #0d3f8b;
                    border-color: #0d3f8b;
                }

                .dc-admin-wrap .button,
                .dc-admin-wrap .button-primary,
                .dc-admin-wrap .button-secondary {
                    border-radius: 10px;
                    min-height: 34px;
                    padding-left: 12px;
                    padding-right: 12px;
                    transition: transform 0.14s ease, box-shadow 0.14s ease;
                }

                .dc-admin-wrap .button:hover,
                .dc-admin-wrap .button-primary:hover,
                .dc-admin-wrap .button-secondary:hover {
                    transform: translateY(-1px);
                }

                .dc-admin-wrap .widefat {
                    border: 1px solid var(--dc-border);
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 8px 16px rgba(15, 23, 42, 0.04);
                }

                .dc-admin-wrap .widefat thead th {
                    background: #f8fbff;
                    color: #334155;
                    font-weight: 700;
                }

                .dc-admin-wrap .widefat td,
                .dc-admin-wrap .widefat th {
                    padding-top: 11px;
                    padding-bottom: 11px;
                }

                .dc-admin-wrap .notice.inline {
                    border-radius: 10px;
                    border-width: 1px;
                }

                @media (max-width: 782px) {
                    .dc-admin-wrap {
                        padding: 12px;
                    }

                    .dc-admin-hero {
                        flex-direction: column;
                    }

                    .dc-admin-hero__meta {
                        justify-content: flex-start;
                    }

                    .dc-tab-panel {
                        padding: 16px;
                    }

                    .dc-admin-wrap .nav-tab {
                        width: 100%;
                        text-align: center;
                    }
                }

                .dc-edit-modal[hidden] {
                    display: none;
                }

                .dc-edit-modal {
                    position: fixed;
                    inset: 0;
                    z-index: 100000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                }

                .dc-edit-modal__backdrop {
                    position: absolute;
                    inset: 0;
                    background: rgba(15, 23, 42, 0.55);
                    backdrop-filter: blur(2px);
                }

                .dc-edit-modal__dialog {
                    position: relative;
                    width: min(760px, 100%);
                    max-height: calc(100vh - 48px);
                    overflow: auto;
                    background: #ffffff;
                    border-radius: 16px;
                    padding: 22px 24px;
                }

                .dc-edit-modal__header {
                    display: flex;
                    align-items: start;
                    justify-content: space-between;
                    gap: 16px;
                    margin-bottom: 12px;
                }

                .dc-edit-modal__header h3 {
                    margin: 0;
                    font-size: 22px;
                }

                .dc-edit-modal__header p {
                    margin: 6px 0 0;
                    color: var(--dc-muted);
                }

                .dc-edit-modal__close {
                    border: 1px solid var(--dc-border);
                    background: #ffffff;
                    border-radius: 999px;
                    width: 36px;
                    height: 36px;
                    font-size: 22px;
                    line-height: 1;
                    cursor: pointer;
                    color: #334155;
                }

                .dc-bundle-actions {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                }

                .dc-table-actions {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 6px;
                    align-items: center;
                }

                .dc-table-icon-btn {
                    display: inline-flex !important;
                    align-items: center;
                    justify-content: center;
                    width: 34px;
                    min-width: 34px;
                    height: 34px;
                    padding: 0 !important;
                    border-radius: 10px !important;
                    border-color: #d3dce8 !important;
                    background: #ffffff !important;
                    color: #334155 !important;
                }

                .dc-table-icon-btn .dashicons {
                    font-size: 16px;
                    width: 16px;
                    height: 16px;
                }

                .dc-table-icon-btn:hover {
                    background: #f8fbff !important;
                    border-color: #b8c7db !important;
                    color: #0f4aa3 !important;
                }

                .dc-row-editable {
                    cursor: pointer;
                }

                .dc-row-editable:hover td {
                    background: #f8fbff;
                }

                .dc-row-editable:focus-visible {
                    outline: 2px solid #0f4aa3;
                    outline-offset: -2px;
                }

                .dc-shortcodes-table-wrap {
                    border: 1px solid #d6dee9;
                    border-radius: 14px;
                    overflow: hidden;
                    background: #ffffff;
                    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
                }

                .dc-shortcodes-table {
                    margin: 0;
                    border: 0 !important;
                    box-shadow: none !important;
                }

                .dc-shortcodes-table thead th {
                    background: #f6f9fc;
                    border-bottom: 1px solid #dfe7f1;
                    color: #334155;
                    font-size: 11px;
                    letter-spacing: 0.05em;
                    text-transform: uppercase;
                    font-weight: 700;
                    padding: 12px 14px;
                }

                .dc-shortcodes-table tbody td {
                    border-top: 1px solid #edf2f8;
                    color: #1f2937;
                    padding: 12px 14px;
                    vertical-align: middle;
                }

                .dc-shortcodes-table tbody tr:first-child td {
                    border-top: 0;
                }

                .dc-shortcodes-key {
                    color: #0f172a;
                    font-size: 12px;
                    background: #f8fafd;
                    border: 1px solid #e4ebf5;
                    padding: 2px 6px;
                    border-radius: 6px;
                }

                .dc-shortcodes-badge {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 28px;
                    height: 24px;
                    padding: 0 8px;
                    border-radius: 999px;
                    background: #eef4fb;
                    color: #0f4aa3;
                    font-size: 12px;
                    font-weight: 700;
                }

                .dc-shortcode-copy-trigger {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 4px 8px;
                    border: 1px solid #d3dce8;
                    border-radius: 8px;
                    background: #ffffff;
                    color: #1f2937;
                    cursor: copy;
                }

                .dc-shortcode-copy-trigger:hover {
                    border-color: #b8c7db;
                    background: #f8fbff;
                }

                .dc-shortcode-copy-trigger:focus-visible {
                    outline: 2px solid #0f4aa3;
                    outline-offset: 1px;
                }

                .dc-shortcode-copy-trigger code {
                    color: #0f172a;
                }

                .dc-shortcode-copy-feedback {
                    font-size: 11px;
                    font-weight: 600;
                    color: #0f4aa3;
                    opacity: 0;
                    transition: opacity 0.16s ease;
                }

                .dc-shortcode-copy-trigger.is-copied .dc-shortcode-copy-feedback {
                    opacity: 1;
                }

                /* ─ Modal de Personalización de Shortcodes ─ */
                .dc-customize-compact {
                    display: flex;
                    gap: 16px;
                    align-items: flex-start;
                    margin-bottom: 12px;
                }

                .dc-customize-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 12px;
                    flex: 1;
                }

                .dc-customize-field {
                    display: flex;
                    flex-direction: column;
                    gap: 6px;
                }

                .dc-customize-field label {
                    margin: 0;
                    color: #334155;
                    font-size: 12px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }

                .dc-customize-field input[type="number"],
                .dc-customize-field select {
                    border: 1px solid #c9d6ea;
                    border-radius: 6px;
                    padding: 6px 8px;
                    font-size: 13px;
                    background: white;
                }

                .dc-customize-field input[type="color"] {
                    width: 100%;
                    height: 36px;
                    border: 1px solid #c9d6ea;
                    border-radius: 6px;
                    cursor: pointer;
                }

                .dc-customize-unit {
                    position: absolute;
                    font-size: 11px;
                    color: #64748b;
                    margin-top: -20px;
                    pointer-events: none;
                }

                .dc-customize-preview-compact {
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    padding: 12px;
                    min-width: 200px;
                    max-width: 240px;
                }

                .dc-customize-preview-compact h4 {
                    margin: 0 0 10px;
                    color: #334155;
                    font-size: 12px;
                    font-weight: 600;
                }

                .dc-customize-preview-container-compact {
                    background: white;
                    border-radius: 8px;
                    padding: 12px;
                    font-size: 12px;
                    color: #334155;
                }

                .dc-customize-preview-container-compact h2 {
                    margin: 0 0 6px;
                    font-size: 14px;
                    font-weight: 700;
                }

                .dc-customize-preview-container-compact p {
                    margin: 0 0 10px;
                    font-size: 11px;
                    opacity: 0.8;
                }

                .dc-customize-preview-container-compact button {
                    width: 100%;
                    padding: 6px;
                    font-size: 11px;
                    font-weight: 600;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    color: white;
                    margin-bottom: 6px;
                    transition: opacity 0.2s;
                }

                .dc-customize-preview-container-compact button:hover {
                    opacity: 0.9;
                }

                @media (max-width: 1024px) {
                    .dc-customize-compact {
                        flex-direction: column;
                    }

                    .dc-customize-grid {
                        grid-template-columns: repeat(3, 1fr);
                    }

                    .dc-customize-preview-compact {
                        width: 100%;
                        max-width: 100%;
                    }
                }

                @media (max-width: 768px) {
                    .dc-customize-grid {
                        grid-template-columns: repeat(2, 1fr);
                    }
                }

                /* Keep datalist-enhanced fields visually aligned with the admin UI. */
                .dc-combo-input {
                    border: 1px solid #c9d6ea;
                    border-radius: 8px;
                    min-height: 34px;
                    padding: 6px 10px;
                    background-color: #fff;
                }

                .dc-combo-wrap {
                    position: relative;
                    display: inline-block;
                    vertical-align: top;
                    width: min(420px, 100%);
                    max-width: 100%;
                }

                .dc-combo-wrap.dc-combo-wrap--small {
                    width: min(220px, 100%);
                }

                .dc-combo-wrap .dc-combo-input {
                    width: 100%;
                    padding-right: 30px;
                    margin: 0;
                }

                .dc-combo-toggle {
                    position: absolute;
                    right: 6px;
                    top: 50%;
                    transform: translateY(-50%);
                    border: 0;
                    background: transparent;
                    color: #64748b;
                    cursor: pointer;
                    padding: 0 2px;
                    line-height: 1;
                    font-size: 14px;
                }

                .dc-combo-wrap.is-open .dc-combo-toggle {
                    color: #0f172a;
                }

                .dc-combo-menu {
                    position: absolute;
                    left: 0;
                    top: calc(100% + 4px);
                    z-index: 1001;
                    width: 100%;
                    max-height: 220px;
                    overflow: auto;
                    border: 1px solid #cbd5e1;
                    border-radius: 8px;
                    background: #fff;
                    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.16);
                    display: none;
                }

                .dc-combo-wrap.is-open .dc-combo-menu {
                    display: block;
                }

                .dc-combo-option {
                    padding: 8px 10px;
                    font-size: 13px;
                    color: #1e293b;
                    cursor: pointer;
                    border-bottom: 1px solid #f1f5f9;
                }

                .dc-combo-option:last-child {
                    border-bottom: 0;
                }

                .dc-combo-option:hover,
                .dc-combo-option.is-active {
                    background: #eff6ff;
                }

                .dc-combo-input.small-text {
                    width: 100%;
                }

                .dc-combo-input.regular-text {
                    width: min(420px, 100%);
                    max-width: 100%;
                }

                .dc-combo-input:focus {
                    border-color: #3b82f6;
                    box-shadow: 0 0 0 1px #3b82f6;
                    outline: 0;
                }

                .dc-edit-modal .form-table td .dc-combo-input.regular-text {
                    width: min(100%, 480px);
                }

                .dc-admin-wrap select,
                .dc-edit-modal select,
                .dc-logs-toolbar select {
                    border: 1px solid #c9d6ea;
                    border-radius: 8px;
                    min-height: 34px;
                    padding: 5px 10px;
                    background-color: #fff;
                }

                .dc-edit-modal .dc-combo-wrap {
                    width: min(100%, 480px);
                }

                .dc-landing-bundles-filters {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 10px;
                    margin-bottom: 10px;
                }

                .dc-landing-bundles-filters label {
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                    font-size: 12px;
                    color: #334155;
                    font-weight: 600;
                }

                .dc-landing-bundles-filters select {
                    min-width: 150px;
                }

                .dc-landing-bundles-full-width {
                    width: 100%;
                }

                .dc-landing-bundles-checklist {
                    max-height: 320px;
                    overflow: auto;
                    border: 1px solid #c9d6ea;
                    border-radius: 8px;
                    background: #ffffff;
                    padding: 8px;
                    display: grid;
                    gap: 6px;
                    width: 100%;
                    box-sizing: border-box;
                }

                .dc-landing-bundles-checklist__item {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 8px;
                    font-size: 13px;
                    color: #1e293b;
                    padding: 4px 2px;
                    border-bottom: 1px dashed #e2e8f0;
                }

                .dc-landing-bundles-checklist__item[hidden] {
                    display: none !important;
                }

                .dc-landing-bundles-checklist__item.is-selected {
                    background: #f8fafc;
                }

                .dc-landing-bundles-checklist__item.is-inactive {
                    background: #fff7ed;
                    border-color: #fdba74;
                    color: #9a3412;
                }

                .dc-landing-bundles-checklist__item.is-inactive .dc-landing-bundles-drag-handle {
                    cursor: not-allowed;
                    opacity: 0.55;
                }

                .dc-landing-bundles-checklist__item.is-inactive .dc-landing-bundles-checklist__main {
                    opacity: 0.9;
                }

                .dc-landing-bundles-checklist__item.is-dragging {
                    opacity: 0.45;
                    border: 1px dashed #94a3b8;
                    border-radius: 8px;
                }

                .dc-landing-bundles-checklist__item.is-drag-over {
                    border-top: 2px solid #2563eb;
                }

                .dc-landing-bundles-checklist__item:last-child {
                    border-bottom: none;
                }

                .dc-landing-bundles-drag-handle {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 28px;
                    height: 28px;
                    border: 1px solid #cbd5e1;
                    border-radius: 6px;
                    background: #ffffff;
                    color: #334155;
                    cursor: grab;
                    font-size: 14px;
                    line-height: 1;
                    user-select: none;
                }

                .dc-landing-bundles-drag-handle:active {
                    cursor: grabbing;
                }

                .dc-landing-bundles-checklist__main {
                    display: inline-flex;
                    align-items: flex-start;
                    gap: 8px;
                    flex: 1;
                    min-width: 0;
                }

                .dc-landing-bundles-checklist__main span {
                    word-break: break-word;
                }

                .dc-landing-bundles-checklist__controls {
                    display: inline-flex;
                    align-items: center;
                    gap: 10px;
                    flex-wrap: wrap;
                }

                .dc-landing-bundles-checklist__featured {
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                    color: #92400e;
                    font-size: 12px;
                    font-weight: 600;
                    white-space: nowrap;
                }

                .dc-landing-bundles-checklist__status {
                    display: inline-flex;
                    align-items: center;
                    padding: 2px 8px;
                    border-radius: 999px;
                    background: #ffedd5;
                    color: #9a3412;
                    border: 1px solid #fdba74;
                    font-size: 11px;
                    font-weight: 700;
                    line-height: 1.3;
                    white-space: nowrap;
                }

                .dc-shortcodes-inactive-note {
                    display: inline-flex;
                    align-items: center;
                    margin-left: 6px;
                    padding: 2px 6px;
                    border-radius: 999px;
                    background: #ffedd5;
                    color: #9a3412;
                    border: 1px solid #fdba74;
                    font-size: 10px;
                    font-weight: 700;
                    letter-spacing: 0.02em;
                }

                .dc-landing-bundles-checklist__item input[type="checkbox"] {
                    margin-top: 2px;
                }

                datalist {
                    display: none;
                }

                .dc-balance-panel {
                    display: none;
                    margin-top: 10px;
                    border: 1px solid #dbe3f0;
                    border-radius: 12px;
                    background: #f8fafc;
                    padding: 14px;
                    max-width: 920px;
                }

                .dc-balance-panel.is-visible {
                    display: block;
                }

                .dc-balance-panel__top {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    justify-content: space-between;
                    gap: 10px;
                }

                .dc-balance-panel__label {
                    margin: 0;
                    color: #334155;
                    font-size: 13px;
                }

                .dc-balance-panel__amount {
                    margin: 4px 0 0;
                    color: #0f172a;
                    font-size: 28px;
                    font-weight: 700;
                    line-height: 1.15;
                    letter-spacing: -0.01em;
                }

                .dc-balance-panel__status {
                    display: inline-flex;
                    align-items: center;
                    border-radius: 999px;
                    padding: 4px 10px;
                    font-size: 12px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.02em;
                }

                .dc-balance-panel__status.is-ok {
                    background: #dcfce7;
                    color: #166534;
                }

                .dc-balance-panel__status.is-warn {
                    background: #fef3c7;
                    color: #92400e;
                }

                .dc-balance-panel__status.is-error {
                    background: #fee2e2;
                    color: #991b1b;
                }

                .dc-balance-panel__meta {
                    margin-top: 10px;
                    color: #475569;
                    font-size: 12px;
                }

                .dc-balance-panel__error {
                    margin: 0;
                    color: #b91c1c;
                    font-size: 14px;
                    font-weight: 600;
                }

                @media (max-width: 782px) {
                    .dc-edit-modal {
                        padding: 12px;
                        align-items: flex-start;
                    }

                    .dc-edit-modal__dialog {
                        max-height: calc(100vh - 24px);
                        padding: 18px;
                    }
                }

                .dc-catalog-subpanel__intro {
                    margin: 0 0 18px;
                    padding: 12px 16px;
                    background: var(--dc-primary-soft);
                    border-left: 3px solid var(--dc-primary);
                    border-radius: 0 8px 8px 0;
                    color: #1e3a6e;
                    font-size: 13px;
                }

                .dc-manual-bundle-heading {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    flex-wrap: wrap;
                }

                .dc-manual-bundle-source {
                    display: inline-flex;
                    align-items: center;
                    max-width: 100%;
                    padding: 2px 10px;
                    border-radius: 999px;
                    background: #e0ecff;
                    color: #1d4a9a;
                    font-size: 12px;
                    font-weight: 600;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }

                .dc-manual-modal__dialog {
                    width: min(820px, 100%);
                }

                .dc-manual-modal__intro {
                    margin: 0 0 18px;
                    color: var(--dc-muted);
                }

                .dc-admin-inline-warning {
                    margin: 0 0 8px;
                    color: #b45309;
                    font-weight: 600;
                }

                .dc-api-results-section {
                    width: 100%;
                    margin-top: 16px;
                    padding: 14px;
                    border: 1px solid #dbe5f3;
                    border-radius: 12px;
                    background: #ffffff;
                    box-sizing: border-box;
                }

                .dc-api-results-section__header {
                    margin-bottom: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    flex-wrap: wrap;
                }

                .dc-api-results-section__title {
                    margin: 0;
                    font-size: 15px;
                    color: #0f172a;
                }

                .dc-api-results-search {
                    margin-bottom: 10px;
                }

                .dc-api-results-search input {
                    width: 100%;
                    box-sizing: border-box;
                }

                .dc-api-table-wrap {
                    border: 1px solid #dbe5f3;
                    border-radius: 10px;
                    overflow: auto;
                    max-height: 520px;
                    background: #f8fbff;
                }

                .dc-saved-bundles-table-wrap {
                    border: 1px solid #dbe5f3;
                    border-radius: 10px;
                    overflow-x: auto;
                    overflow-y: hidden;
                    background: #ffffff;
                }

                .dc-saved-bundles-table-wrap .widefat {
                    min-width: 1080px;
                    border: 0;
                    border-radius: 0;
                    box-shadow: none;
                }

                .dc-saved-bundles-logo-url {
                    min-width: 220px;
                    max-width: 320px;
                    word-break: break-all;
                }

                .dc-saved-bundles-logo-url a {
                    display: inline-block;
                    max-width: 100%;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    vertical-align: middle;
                }

                .dc-saved-bundles-table-wrap .check-column {
                    width: 54px;
                    min-width: 54px;
                    text-align: center;
                    padding-left: 12px;
                    padding-right: 12px;
                    vertical-align: middle;
                }

                .dc-saved-bundles-table-wrap .check-column input[type="checkbox"] {
                    margin: 0 auto;
                    display: block;
                }

                .dc-saved-bundles-filters {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 10px;
                    margin: 10px 0 12px;
                }

                .dc-saved-bundles-filters input,
                .dc-saved-bundles-filters select {
                    flex: 1 1 180px;
                    max-width: none;
                }

                .dc-api-results-table {
                    width: 100%;
                    border-collapse: collapse;
                    min-width: 980px;
                    background: #ffffff;
                }

                .dc-api-results-table thead th {
                    position: sticky;
                    top: 0;
                    background: #eff6ff;
                    color: #1e3a8a;
                    z-index: 1;
                }

                .dc-api-results-table th,
                .dc-api-results-table td {
                    padding: 9px 10px;
                    border-bottom: 1px solid #e2e8f0;
                    text-align: left;
                    vertical-align: top;
                    font-size: 12px;
                }

                .dc-api-results-table tbody tr {
                    cursor: pointer;
                    transition: background-color 0.15s ease;
                }

                .dc-api-results-table tbody tr:hover {
                    background: #f8fafc;
                }

                .dc-api-results-table tbody tr.is-selected {
                    background: #dbeafe;
                    box-shadow: inset 0 0 0 1px #60a5fa;
                }

                .dc-api-results-table .dc-api-group-row td {
                    background: #f1f5f9;
                    color: #334155;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.04em;
                    font-size: 11px;
                    cursor: default;
                }

                .dc-api-cell-mono {
                    font-family: Menlo, Consolas, Monaco, monospace;
                    font-size: 11px;
                }

                .dc-api-source-badge {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 999px;
                    padding: 2px 8px;
                    font-size: 11px;
                    font-weight: 700;
                    line-height: 1.4;
                    white-space: nowrap;
                }

                .dc-api-source-badge.is-api {
                    background: #dcfce7;
                    color: #166534;
                }

                .dc-api-results-actions {
                    margin-top: 10px;
                    display: flex;
                    flex-wrap: wrap;
                    gap: 10px;
                    align-items: center;
                }

                .dc-api-results-actions .description {
                    margin: 0;
                }

            </style>

            <div class="dc-admin-tabs">
                <h2 class="nav-tab-wrapper" style="margin-bottom:0;">
                    <button type="button" class="nav-tab" data-dc-tab-btn="tab_setup">Credenciales</button>
                    <button type="button" class="nav-tab" data-dc-tab-btn="tab_catalog">Catálogo y alta</button>
                    <button type="button" class="nav-tab" data-dc-tab-btn="tab_saved">Productos guardados</button>
                    <button type="button" class="nav-tab" data-dc-tab-btn="tab_landings">Landings</button>
                    <button type="button" class="nav-tab" data-dc-tab-btn="tab_logs">Registros</button>
                </h2>

                <section id="dc-tab-setup" class="dc-tab-panel" data-dc-tab-panel="tab_setup">

            <h2>Credenciales y modo de operación</h2>
            <form method="post" action="options.php">
                <?php settings_fields('dc_recargas_group'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="dc_api_base">API Base URL</label></th>
                        <td><input type="url" id="dc_api_base" name="dc_recargas_options[api_base]" class="regular-text" value="<?php echo esc_attr($options['api_base']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_api_key">API Key DingConnect</label></th>
                        <td>
                            <input type="text" id="dc_api_key" name="dc_recargas_options[api_key]" class="regular-text" value="<?php echo esc_attr($options['api_key']); ?>">
                            <p class="description">Nunca publiques esta clave en JavaScript. El plugin la usa solo en el servidor.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Modo de pago</th>
                        <td>
                            <fieldset>
                                <label style="display:block;margin-bottom:6px;">
                                    <input type="radio" name="dc_recargas_options[payment_mode]" value="woocommerce" <?php checked(($options['payment_mode'] ?? 'direct'), 'woocommerce'); ?> <?php disabled(!class_exists('WooCommerce')); ?>>
                                    WooCommerce (cuenta, carrito y checkout)
                                    <?php if (!class_exists('WooCommerce')): ?>
                                        <span style="color:#d63638;">— WooCommerce no está activo</span>
                                    <?php endif; ?>
                                </label>
                                <label style="display:block;">
                                    <input type="radio" name="dc_recargas_options[payment_mode]" value="direct" <?php checked(($options['payment_mode'] ?? 'direct'), 'direct'); ?>>
                                    Pago directo (sin cuenta, el cliente ingresa su número y paga directamente)
                                </label>
                            </fieldset>
                            <p class="description">Define cómo se procesa el pago de las recargas en el frontend.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Pasarelas permitidas</th>
                        <td>
                            <?php if (!class_exists('WooCommerce')): ?>
                                <p class="description">Activa WooCommerce para configurar pasarelas de pago para recargas.</p>
                            <?php elseif (empty($wc_gateways)): ?>
                                <p class="description">No hay pasarelas de WooCommerce registradas.</p>
                            <?php else: ?>
                                <fieldset>
                                    <?php foreach ($wc_gateways as $gateway_id => $gateway): ?>
                                        <?php
                                        $clean_gateway_id = sanitize_key((string) $gateway_id);
                                        if ($clean_gateway_id === '') {
                                            continue;
                                        }
                                        $gateway_title = '';
                                        if (is_object($gateway) && method_exists($gateway, 'get_title')) {
                                            $gateway_title = (string) $gateway->get_title();
                                        }
                                        if ($gateway_title === '') {
                                            $gateway_title = $clean_gateway_id;
                                        }
                                        ?>
                                        <label style="display:block;margin-bottom:6px;">
                                            <input type="checkbox" name="dc_recargas_options[woo_allowed_gateways][]" value="<?php echo esc_attr($clean_gateway_id); ?>" <?php checked(in_array($clean_gateway_id, $selected_woo_gateways, true)); ?>>
                                            <?php echo esc_html(wp_strip_all_tags($gateway_title)); ?>
                                            <span style="color:#64748b;">(<?php echo esc_html($clean_gateway_id); ?>)</span>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                                <p class="description">
                                    Esta restricción aplica solo a carritos con recargas y solo en modo WooCommerce.<br>
                                    Si no seleccionas ninguna pasarela, se permitirán todas las pasarelas activas del checkout.
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_recharge_mode">Modo de recargas</label></th>
                        <td>
                            <select id="dc_recharge_mode" name="dc_recargas_options[recharge_mode]">
                                <option value="test_simulate" <?php selected(($options['recharge_mode'] ?? 'test_simulate'), 'test_simulate'); ?>>
                                    🔒 Pruebas (Simular siempre)
                                </option>
                                <option value="test_allow_change" <?php selected(($options['recharge_mode'] ?? ''), 'test_allow_change'); ?>>
                                    ⚙️ Pruebas (Permitir cambio desde frontend)
                                </option>
                                <option value="production" <?php selected(($options['recharge_mode'] ?? ''), 'production'); ?>>
                                    ⚡ Producción (Reales)
                                </option>
                            </select>
                            <p class="description">
                                <strong>Simular siempre:</strong> Todas las transacciones son simuladas, sin opción de cambio.<br>
                                <strong>Permitir cambio:</strong> Por defecto simula, pero frontend puede enviar <code>ValidateOnly: false</code> para probar reales.<br>
                                <strong>Producción:</strong> Transacciones reales sin simulación.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_submitted_retry_max_attempts">Política Submitted: intentos máximos</label></th>
                        <td>
                            <input type="number" id="dc_submitted_retry_max_attempts" name="dc_recargas_options[submitted_retry_max_attempts]" min="1" max="8" value="<?php echo esc_attr((string) ($options['submitted_retry_max_attempts'] ?? 4)); ?>" class="small-text">
                            <p class="description">Cantidad máxima de ciclos de reintento/reconsulta para estados pendientes como <code>Submitted</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_submitted_retry_backoff_minutes">Política Submitted: backoff (minutos)</label></th>
                        <td>
                            <input type="text" id="dc_submitted_retry_backoff_minutes" name="dc_recargas_options[submitted_retry_backoff_minutes]" class="regular-text" value="<?php echo esc_attr((string) ($options['submitted_retry_backoff_minutes'] ?? '10,20,40,80')); ?>" placeholder="10,20,40,80">
                            <p class="description">Secuencia de espera por ciclo separada por comas. Ejemplo recomendado: <code>10,20,40,80</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_submitted_max_window_hours">Política Submitted: ventana máxima (horas)</label></th>
                        <td>
                            <input type="number" id="dc_submitted_max_window_hours" name="dc_recargas_options[submitted_max_window_hours]" min="1" max="168" value="<?php echo esc_attr((string) ($options['submitted_max_window_hours'] ?? 12)); ?>" class="small-text">
                            <p class="description">Si una recarga permanece pendiente por más de este tiempo, se marca como <code>escalado_soporte</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_submitted_escalation_email">Política Submitted: correo de escalado</label></th>
                        <td>
                            <input type="email" id="dc_submitted_escalation_email" name="dc_recargas_options[submitted_escalation_email]" class="regular-text" value="<?php echo esc_attr((string) ($options['submitted_escalation_email'] ?? '')); ?>" placeholder="soporte@tu-dominio.com">
                            <p class="description">Opcional. Si se define, el plugin envía aviso cuando una recarga se escala por <code>Submitted</code> prolongado.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_submitted_non_retryable_codes">Códigos no reintentables</label></th>
                        <td>
                            <input type="text" id="dc_submitted_non_retryable_codes" name="dc_recargas_options[submitted_non_retryable_codes]" class="regular-text" value="<?php echo esc_attr((string) ($options['submitted_non_retryable_codes'] ?? 'InsufficientBalance,AccountNumberInvalid,RechargeNotAllowed')); ?>" placeholder="InsufficientBalance,AccountNumberInvalid">
                            <p class="description">Lista de códigos DingConnect que deben cortarse sin reintento (separados por coma).</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Guardar configuración'); ?>
            </form>

            <hr>
            <h2>Balance del agente (DingConnect)</h2>
            <p>Consulta el saldo disponible del API Key configurado sin salir de WordPress.</p>
            <p>
                <button type="button" class="button button-secondary" id="dc_check_balance_btn">Consultar balance ahora</button>
            </p>
            <div id="dc_balance_result" class="dc-balance-panel" aria-live="polite"></div>

                </section>

                <section id="dc-tab-landings" class="dc-tab-panel" data-dc-tab-panel="tab_landings">

            <h2>Landings y shortcodes dinámicos</h2>
            <p>Define objetivos de landing y asocia bundles concretos para generar shortcodes reutilizables.</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="dc_add_landing_shortcode">
                <?php wp_nonce_field('dc_add_landing_shortcode'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="dc_landing_name">Nombre del objetivo</label></th>
                        <td>
                            <input required type="text" id="dc_landing_name" name="landing_name" class="regular-text" placeholder="Ej: Cuba recargas mayo">
                            <p class="description">Nombre interno para identificar la landing.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_landing_key">Clave de shortcode (opcional)</label></th>
                        <td>
                            <input type="text" id="dc_landing_key" name="landing_key" class="regular-text" placeholder="ej: cuba-mayo-2026">
                            <p class="description">Si lo dejas vacío, se genera automáticamente.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_landing_title">Título del formulario</label></th>
                        <td><input type="text" id="dc_landing_title" name="landing_title" class="regular-text" placeholder="Recargas para Cuba"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_landing_subtitle">Subtítulo del formulario</label></th>
                        <td><input type="text" id="dc_landing_subtitle" name="landing_subtitle" class="regular-text" placeholder="Elige un paquete y confirma tu recarga"></td>
                    </tr>
                </table>

                <div class="dc-landing-bundles-full-width">
                    <h4 style="margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #334155;">Bundles de la landing</h4>
                    <div class="dc-landing-bundles-filters" data-dc-landing-filters>
                        <label>País
                            <select id="dc_landing_filter_country" class="small-text" data-filter="country">
                                <option value="all">Todos</option>
                            </select>
                        </label>
                        <label>Tipo de producto
                            <select id="dc_landing_filter_family" class="small-text" data-filter="family">
                                <option value="all">Todos</option>
                            </select>
                        </label>
                    </div>
                    <div id="dc_landing_bundle_ids" class="dc-landing-bundles-checklist" role="group" aria-label="Bundles disponibles para la landing">
                        <?php foreach ($bundles as $bundle) : ?>
                            <?php $bundle_id = sanitize_text_field((string) ($bundle['id'] ?? '')); ?>
                            <?php if ($bundle_id === '') { continue; } ?>
                            <?php $bundle_country = strtoupper((string) ($bundle['country_iso'] ?? '')); ?>
                            <?php $bundle_family = sanitize_key((string) ($bundle['package_family'] ?? 'other')); ?>
                            <?php $bundle_is_active = !empty($bundle['is_active']); ?>
                            <label class="dc-landing-bundles-checklist__item<?php echo $bundle_is_active ? '' : ' is-inactive'; ?>" data-bundle-id="<?php echo esc_attr($bundle_id); ?>" data-country-iso="<?php echo esc_attr($bundle_country); ?>" data-package-family="<?php echo esc_attr($bundle_family); ?>">
                                <button type="button" class="dc-landing-bundles-drag-handle" title="Arrastrar para cambiar orden" aria-label="Arrastrar para cambiar orden" <?php disabled(!$bundle_is_active); ?>>⋮⋮</button>
                                <span class="dc-landing-bundles-checklist__main">
                                    <input type="checkbox" class="dc-create-landing-bundle-checkbox" name="bundle_ids[]" value="<?php echo esc_attr($bundle_id); ?>" <?php disabled(!$bundle_is_active); ?>>
                                    <span>[<?php echo esc_html(strtoupper((string) ($bundle['country_iso'] ?? ''))); ?>] <?php echo esc_html((string) ($bundle['label'] ?? '')); ?> | <?php echo esc_html((string) ($bundle['sku_code'] ?? '')); ?></span>
                                </span>
                                <span class="dc-landing-bundles-checklist__controls">
                                    <span class="dc-landing-bundles-checklist__featured">
                                        <input type="radio" class="dc-create-landing-featured-radio" name="featured_bundle_id" value="<?php echo esc_attr($bundle_id); ?>" <?php disabled(!$bundle_is_active); ?>>
                                        Destacado
                                    </span>
                                    <?php if (!$bundle_is_active) : ?>
                                        <span class="dc-landing-bundles-checklist__status" title="Este producto está desactivado en Productos guardados">Desactivado</span>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="description">Arrastra los bundles para cambiar el orden. Marca los que deben aparecer y opcionalmente uno como destacado.</p>
                </div>

                <?php submit_button('Crear shortcode de landing'); ?>
            </form>

            <h3>Shortcodes creados</h3>
            <?php
                $landing_bundle_status_map = [];
                foreach ($bundles as $bundle) {
                    $bundle_id = sanitize_text_field((string) ($bundle['id'] ?? ''));
                    if ($bundle_id === '') {
                        continue;
                    }

                    $landing_bundle_status_map[$bundle_id] = !empty($bundle['is_active']);
                }
            ?>
            <div class="dc-shortcodes-table-wrap">
            <table class="widefat striped dc-shortcodes-table">
                <thead>
                    <tr>
                        <th>Objetivo</th>
                        <th>Clave</th>
                        <th>Bundles</th>
                        <th>Shortcode</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($landing_shortcodes)) : ?>
                        <tr><td colspan="5">No hay shortcodes dinámicos creados todavía.</td></tr>
                    <?php else : ?>
                        <?php foreach ($landing_shortcodes as $landing_cfg) : ?>
                            <?php
                                $landing_id = sanitize_text_field((string) ($landing_cfg['id'] ?? ''));
                                $landing_key = sanitize_key((string) ($landing_cfg['key'] ?? ''));
                                $landing_name = sanitize_text_field((string) ($landing_cfg['name'] ?? 'Landing'));
                                $landing_bundles = is_array($landing_cfg['bundle_ids'] ?? null) ? $landing_cfg['bundle_ids'] : [];
                                $landing_inactive_count = 0;
                                foreach ($landing_bundles as $landing_bundle_id) {
                                    $landing_bundle_id = sanitize_text_field((string) $landing_bundle_id);
                                    if ($landing_bundle_id === '') {
                                        continue;
                                    }

                                    if (isset($landing_bundle_status_map[$landing_bundle_id]) && !$landing_bundle_status_map[$landing_bundle_id]) {
                                        $landing_inactive_count++;
                                    }
                                }
                                $shortcode_text = '[dingconnect_recargas landing_key="' . $landing_key . '"]';
                            ?>
                            <tr class="dc-row-editable" tabindex="0" role="button" data-edit-landing="<?php echo esc_attr(wp_json_encode($landing_cfg)); ?>" aria-label="Editar shortcode <?php echo esc_attr($landing_name); ?>">
                                <td><?php echo esc_html($landing_name); ?></td>
                                <td><code class="dc-shortcodes-key"><?php echo esc_html($landing_key); ?></code></td>
                                <td>
                                    <span class="dc-shortcodes-badge"><?php echo esc_html((string) count($landing_bundles)); ?></span>
                                    <?php if ($landing_inactive_count > 0) : ?>
                                        <span class="dc-shortcodes-inactive-note" title="Incluye bundles desactivados">Desactivados: <?php echo esc_html((string) $landing_inactive_count); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="dc-shortcode-copy-trigger" data-copy-text="<?php echo esc_attr($shortcode_text); ?>" title="Copiar shortcode" aria-label="Copiar shortcode de <?php echo esc_attr($landing_name); ?>">
                                        <code><?php echo esc_html($shortcode_text); ?></code>
                                        <span class="dc-shortcode-copy-feedback" aria-live="polite">Copiado</span>
                                    </button>
                                </td>
                                <td>
                                    <div class="dc-table-actions">
                                    <button type="button" class="button dc-table-icon-btn dc-customize-shortcode-btn" data-shortcode-key="<?php echo esc_attr($landing_key); ?>" data-shortcode-text="<?php echo esc_attr($shortcode_text); ?>" title="Personalizar shortcode" aria-label="Personalizar shortcode <?php echo esc_attr($landing_name); ?>"><span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span></button>
                                    <a class="button dc-table-icon-btn" href="<?php echo esc_url(wp_nonce_url(add_query_arg([
                                        'action' => 'dc_clone_landing_shortcode',
                                        'landing_id' => $landing_id,
                                    ], admin_url('admin-post.php')), 'dc_clone_landing_shortcode')); ?>" title="Duplicar shortcode" aria-label="Duplicar shortcode <?php echo esc_attr($landing_name); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></a>
                                    <a class="button button-secondary dc-table-icon-btn" href="<?php echo esc_url(wp_nonce_url(add_query_arg([
                                        'action' => 'dc_delete_landing_shortcode',
                                        'landing_id' => $landing_id,
                                    ], admin_url('admin-post.php')), 'dc_delete_landing_shortcode')); ?>" onclick="return confirm('¿Eliminar shortcode de landing?');" title="Eliminar shortcode" aria-label="Eliminar shortcode <?php echo esc_attr($landing_name); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>

            <div id="dc-edit-landing-modal" class="dc-edit-modal" role="dialog" aria-modal="true" aria-labelledby="dc-edit-landing-modal-title" hidden>
                <div class="dc-edit-modal__backdrop" data-dc-landing-edit-close></div>
                <div class="dc-edit-modal__dialog">
                    <div class="dc-edit-modal__header">
                        <div>
                            <h3 id="dc-edit-landing-modal-title">Editar shortcode dinámico</h3>
                            <p>Actualiza objetivo, clave y bundles sin salir de la configuración.</p>
                        </div>
                        <button type="button" class="dc-edit-modal__close" aria-label="Cerrar edición" data-dc-landing-edit-close>&times;</button>
                    </div>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="dc_edit_landing_form">
                        <input type="hidden" name="action" value="dc_update_landing_shortcode">
                        <?php wp_nonce_field('dc_update_landing_shortcode'); ?>
                        <input type="hidden" id="dc_edit_landing_id" name="landing_id" value="">

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="dc_edit_landing_name">Nombre del objetivo</label></th>
                                <td><input required type="text" id="dc_edit_landing_name" name="landing_name" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="dc_edit_landing_key">Clave de shortcode</label></th>
                                <td><input type="text" id="dc_edit_landing_key" name="landing_key" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="dc_edit_landing_title">Título del formulario</label></th>
                                <td><input type="text" id="dc_edit_landing_title" name="landing_title" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="dc_edit_landing_subtitle">Subtítulo del formulario</label></th>
                                <td><input type="text" id="dc_edit_landing_subtitle" name="landing_subtitle" class="regular-text"></td>
                            </tr>
                        </table>

                        <div class="dc-landing-bundles-full-width">
                            <h4 style="margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #334155;">Bundles de la landing</h4>
                            <div class="dc-landing-bundles-filters" data-dc-landing-filters>
                                <label>País
                                    <select id="dc_edit_landing_filter_country" class="small-text" data-filter="country">
                                        <option value="all">Todos</option>
                                    </select>
                                </label>
                                <label>Tipo de producto
                                    <select id="dc_edit_landing_filter_family" class="small-text" data-filter="family">
                                        <option value="all">Todos</option>
                                    </select>
                                </label>
                            </div>
                            <div id="dc_edit_landing_bundle_ids" class="dc-landing-bundles-checklist" role="group" aria-label="Bundles disponibles para editar la landing">
                                <?php foreach ($bundles as $bundle) : ?>
                                    <?php $bundle_id = sanitize_text_field((string) ($bundle['id'] ?? '')); ?>
                                    <?php if ($bundle_id === '') { continue; } ?>
                                    <?php $bundle_country = strtoupper((string) ($bundle['country_iso'] ?? '')); ?>
                                    <?php $bundle_family = sanitize_key((string) ($bundle['package_family'] ?? 'other')); ?>
                                    <?php $bundle_is_active = !empty($bundle['is_active']); ?>
                                    <label class="dc-landing-bundles-checklist__item<?php echo $bundle_is_active ? '' : ' is-inactive'; ?>" data-bundle-id="<?php echo esc_attr($bundle_id); ?>" data-country-iso="<?php echo esc_attr($bundle_country); ?>" data-package-family="<?php echo esc_attr($bundle_family); ?>">
                                        <button type="button" class="dc-landing-bundles-drag-handle" title="Arrastrar para cambiar orden" aria-label="Arrastrar para cambiar orden" <?php disabled(!$bundle_is_active); ?>>⋮⋮</button>
                                        <span class="dc-landing-bundles-checklist__main">
                                            <input type="checkbox" class="dc-edit-landing-bundle-checkbox" name="bundle_ids[]" value="<?php echo esc_attr($bundle_id); ?>" <?php disabled(!$bundle_is_active); ?>>
                                            <span>[<?php echo esc_html($bundle_country); ?>] <?php echo esc_html((string) ($bundle['label'] ?? '')); ?> | <?php echo esc_html((string) ($bundle['sku_code'] ?? '')); ?></span>
                                        </span>
                                        <span class="dc-landing-bundles-checklist__controls">
                                            <span class="dc-landing-bundles-checklist__featured">
                                                <input type="radio" class="dc-edit-landing-featured-radio" name="featured_bundle_id" value="<?php echo esc_attr($bundle_id); ?>" <?php disabled(!$bundle_is_active); ?>>
                                                Destacado
                                            </span>
                                            <?php if (!$bundle_is_active) : ?>
                                                <span class="dc-landing-bundles-checklist__status" title="Este producto está desactivado en Productos guardados">Desactivado</span>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description">Arrastra los bundles para cambiar el orden. Marca los que deben aparecer y opcionalmente uno como destacado.</p>
                        </div>

                        <?php submit_button('Guardar cambios del shortcode', 'primary', 'submit', false); ?>
                        <button type="button" class="button button-secondary" data-dc-landing-edit-close>Cancelar</button>
                    </form>
                </div>
            </div>

            <div id="dc-customize-shortcode-modal" class="dc-edit-modal" role="dialog" aria-modal="true" aria-labelledby="dc-customize-shortcode-modal-title" hidden>
                <div class="dc-edit-modal__backdrop" data-dc-customize-close></div>
                <div class="dc-edit-modal__dialog" style="max-width: 720px;">
                    <div class="dc-edit-modal__header">
                        <div>
                            <h3 id="dc-customize-shortcode-modal-title">Personalizar Diseño</h3>
                            <p>Ajusta colores, tamaño y estilos (se aplican automáticamente).</p>
                        </div>
                        <button type="button" class="dc-edit-modal__close" aria-label="Cerrar" data-dc-customize-close>&times;</button>
                    </div>

                    <div class="dc-customize-compact">
                        <div class="dc-customize-grid">
                            <div class="dc-customize-field">
                                <label for="dc_customize_max_width">Ancho máximo</label>
                                <input type="number" id="dc_customize_max_width" min="300" max="800" step="10" value="480" class="small-text">
                                <span class="dc-customize-unit">px</span>
                            </div>
                            <div class="dc-customize-field">
                                <label for="dc_customize_bg_color">Fondo</label>
                                <input type="color" id="dc_customize_bg_color" value="#ffffff" class="dc-color-picker">
                            </div>
                            <div class="dc-customize-field">
                                <label for="dc_customize_primary_color">Botones</label>
                                <input type="color" id="dc_customize_primary_color" value="#2563eb" class="dc-color-picker">
                            </div>
                            <div class="dc-customize-field">
                                <label for="dc_customize_text_color">Texto</label>
                                <input type="color" id="dc_customize_text_color" value="#0f172a" class="dc-color-picker">
                            </div>
                            <div class="dc-customize-field">
                                <label for="dc_customize_border_radius">Bordes</label>
                                <input type="number" id="dc_customize_border_radius" min="0" max="30" step="2" value="16" class="small-text">
                                <span class="dc-customize-unit">px</span>
                            </div>
                            <div class="dc-customize-field">
                                <label for="dc_customize_padding">Espaciado</label>
                                <input type="number" id="dc_customize_padding" min="10" max="50" step="5" value="24" class="small-text">
                                <span class="dc-customize-unit">px</span>
                            </div>
                            <div class="dc-customize-field">
                                <label for="dc_customize_shadow_intensity">Sombra</label>
                                <select id="dc_customize_shadow_intensity" class="small-text">
                                    <option value="none">Ninguna</option>
                                    <option value="light" selected>Ligera</option>
                                    <option value="medium">Media</option>
                                    <option value="heavy">Fuerte</option>
                                </select>
                            </div>
                        </div>

                        <div class="dc-customize-preview-compact">
                            <h4 style="margin: 0 0 12px; font-size: 13px;">Vista previa</h4>
                            <div id="dc_customize_preview" class="dc-customize-preview-container-compact"></div>
                        </div>
                    </div>

                    <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e2e8f0; display: flex; gap: 10px; justify-content: space-between;">
                        <div id="dc_customize_status" style="color: #64748b; font-size: 13px; align-self: center;"></div>
                        <button type="button" class="button button-secondary" data-dc-customize-close>Cerrar</button>
                    </div>
                </div>
            </div>

                </section>

                <section id="dc-tab-catalog" class="dc-tab-panel" data-dc-tab-panel="tab_catalog">

            <h2>Catálogo y alta de bundles</h2>
            <p>Añade productos al catálogo visible en el frontend consultando DingConnect en tiempo real. El alta manual se abre como modal desde la selección del producto.</p>

            <p class="dc-catalog-subpanel__intro">Consulta SKUs, operador, beneficios, tipo, vigencia y coste directamente desde DingConnect en tiempo real. Los resultados se guardan en caché por <strong>10 minutos</strong> por país.</p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="dc_api_country_iso">País (código ISO)</label></th>
                    <td>
                        <p class="description dc-admin-inline-warning" id="dc_api_country_warning" hidden>Selecciona un país antes de buscar.</p>
                        <select id="dc_api_country_iso" class="regular-text">
                            <option value="">Selecciona un país...</option>
                            <option value="AR">Argentina (AR)</option>
                            <option value="BO">Bolivia (BO)</option>
                            <option value="BR">Brasil (BR)</option>
                            <option value="CL">Chile (CL)</option>
                            <option value="CO">Colombia (CO)</option>
                            <option value="CR">Costa Rica (CR)</option>
                            <option value="CU">Cuba (CU)</option>
                            <option value="DO">Rep. Dominicana (DO)</option>
                            <option value="EC">Ecuador (EC)</option>
                            <option value="SV">El Salvador (SV)</option>
                            <option value="ES">España (ES)</option>
                            <option value="GT">Guatemala (GT)</option>
                            <option value="HT">Haití (HT)</option>
                            <option value="HN">Honduras (HN)</option>
                            <option value="MX">México (MX)</option>
                            <option value="NI">Nicaragua (NI)</option>
                            <option value="PA">Panamá (PA)</option>
                            <option value="PY">Paraguay (PY)</option>
                            <option value="PE">Perú (PE)</option>
                            <option value="US">Estados Unidos (US)</option>
                            <option value="UY">Uruguay (UY)</option>
                            <option value="VE">Venezuela (VE)</option>
                        </select>
                        <button type="button" class="button" id="dc_api_fetch_btn">Buscar en API</button>
                        <p class="description">Resultados directos de DingConnect (caché de 10 minutos por país).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="dc_api_package_group">Tipo de paquete</label></th>
                    <td>
                        <select id="dc_api_package_group" class="regular-text" disabled>
                            <option value="all">Todos los tipos</option>
                        </select>
                        <p class="description">Filtro operativo derivado del catálogo: saldo/top-up, datos y combos. Se habilita después de consultar la API.</p>
                    </td>
                </tr>
            </table>

            <section class="dc-api-results-section" aria-labelledby="dc_api_results_title">
                <div class="dc-api-results-section__header">
                    <h3 id="dc_api_results_title" class="dc-api-results-section__title">Paquetes encontrados</h3>
                </div>
                <div class="dc-api-results-search">
                    <input type="text" id="dc_api_search" class="regular-text" placeholder="Buscar por operador, beneficio, SKU o vigencia..." disabled>
                </div>
                <div class="dc-api-table-wrap" role="region" aria-label="Tabla de paquetes encontrados" tabindex="0">
                    <table class="dc-api-results-table" aria-describedby="dc_api_help">
                        <thead>
                            <tr>
                                <th scope="col">Logo</th>
                                <th scope="col">Tipo</th>
                                <th scope="col">Operador</th>
                                <th scope="col">Beneficios / descripción</th>
                                <th scope="col">SKU</th>
                                <th scope="col">Coste DIN</th>
                                <th scope="col">Moneda</th>
                                <th scope="col">Vigencia</th>
                                <th scope="col">Fuente</th>
                            </tr>
                        </thead>
                        <tbody id="dc_api_results"></tbody>
                    </table>
                </div>
                <div class="dc-api-results-actions">
                    <button type="button" class="button button-primary" id="dc_api_load_manual_btn" disabled>Seleccionar producto</button>
                    <p class="description" id="dc_api_help">Selecciona un país y haz clic en «Buscar en API». Luego selecciona un paquete y usa el botón para abrir el modal de alta manual con la información precargada.</p>
                </div>
            </section>
            <script>
            (function () {
                 var apiNonce     = <?php echo wp_json_encode($catalog_nonce); ?>;
                 var apiStorageKey = 'dc_admin_api_last_search_v1';
                var apiCountryEl = document.getElementById('dc_api_country_iso');
                var apiCountryWarningEl = document.getElementById('dc_api_country_warning');
                var apiFetchBtn  = document.getElementById('dc_api_fetch_btn');
                var apiFilterEl  = document.getElementById('dc_api_package_group');
                var apiResultsEl = document.getElementById('dc_api_results');
                var apiHelpEl    = document.getElementById('dc_api_help');
                var apiLoadManualBtn = document.getElementById('dc_api_load_manual_btn');
                var manualModalEl = document.getElementById('dc-manual-modal');
                var apiSelected  = null;
                var apiSelectedRowEl = null;
                var apiItems = [];
                var apiGroupCounts = {};
                var apiSearchEl  = document.getElementById('dc_api_search');
                var apiGroupLabels = {
                    saldo: 'Saldo / top-up',
                    data: 'Datos',
                    combo: 'Combo / voz + datos',
                    other: 'Otros'
                };

                function apiGroupOrder(group) {
                    var order = { combo: 1, data: 2, saldo: 3, other: 4 };
                    return order[group] || 99;
                }

                function loadStoredApiState() {
                    try {
                        return JSON.parse(localStorage.getItem(apiStorageKey) || 'null');
                    } catch (e) {
                        return null;
                    }
                }

                function saveStoredApiState() {
                    var payload = {
                        country_iso: apiCountryEl ? String(apiCountryEl.value || '') : '',
                        package_group: apiFilterEl ? String(apiFilterEl.value || 'all') : 'all',
                        search_term: apiSearchEl ? String(apiSearchEl.value || '') : '',
                        items: apiItems,
                        group_counts: apiGroupCounts,
                        group_labels: apiGroupLabels,
                        saved_at: new Date().toISOString()
                    };

                    try {
                        localStorage.setItem(apiStorageKey, JSON.stringify(payload));
                    } catch (e) {
                        // Ignore storage errors in private mode or restricted browsers.
                    }
                }

                function restoreStoredApiState() {
                    var stored = loadStoredApiState();
                    if (!stored || !apiCountryEl) {
                        return;
                    }

                    apiCountryEl.value = String(stored.country_iso || '');
                    apiItems = Array.isArray(stored.items) ? stored.items : [];
                    apiGroupCounts = stored.group_counts && typeof stored.group_counts === 'object' ? stored.group_counts : {};
                    apiGroupLabels = stored.group_labels && typeof stored.group_labels === 'object' ? stored.group_labels : apiGroupLabels;

                    refreshApiResults();

                    if (apiFilterEl) {
                        var storedGroup = String(stored.package_group || 'all');
                        if (apiFilterEl.querySelector('option[value="' + storedGroup + '"]')) {
                            apiFilterEl.value = storedGroup;
                        }
                    }

                    if (apiSearchEl) {
                        apiSearchEl.value = String(stored.search_term || '');
                    }

                    renderApiResults(getFilteredApiItems());

                    if (stored.country_iso) {
                        apiHelpEl.textContent = apiItems.length > 0
                            ? 'Última búsqueda restaurada para ' + stored.country_iso + '. Puedes seguir filtrando, crear un bundle o volver a consultar la API.'
                            : 'Última búsqueda restaurada para ' + stored.country_iso + '. Esa consulta no devolvió paquetes.';
                    }
                }

                function setApiCountryWarning(message) {
                    if (!apiCountryWarningEl) {
                        return;
                    }

                    if (message) {
                        apiCountryWarningEl.textContent = message;
                        apiCountryWarningEl.hidden = false;
                        return;
                    }

                    apiCountryWarningEl.textContent = 'Selecciona un país antes de buscar.';
                    apiCountryWarningEl.hidden = true;
                }

                function apiSourceLabel(item) {
                    return 'API';
                }

                function syncApiFilterOptions() {
                    var current = apiFilterEl ? String(apiFilterEl.value || 'all') : 'all';
                    var order = ['combo', 'data', 'saldo', 'other'];

                    if (!apiFilterEl) {
                        return;
                    }

                    apiFilterEl.innerHTML = '';

                    var allOption = document.createElement('option');
                    allOption.value = 'all';
                    allOption.textContent = 'Todos los tipos (' + apiItems.length + ')';
                    apiFilterEl.appendChild(allOption);

                    order.forEach(function (group) {
                        var count = Number(apiGroupCounts[group] || 0);
                        if (!count) {
                            return;
                        }

                        var opt = document.createElement('option');
                        opt.value = group;
                        opt.textContent = (apiGroupLabels[group] || group) + ' (' + count + ')';
                        apiFilterEl.appendChild(opt);
                    });

                    apiFilterEl.disabled = apiItems.length === 0;

                    if (apiFilterEl.querySelector('option[value="' + current + '"]')) {
                        apiFilterEl.value = current;
                    } else {
                        apiFilterEl.value = 'all';
                    }
                }

                function getFilteredApiItems() {
                    var selectedGroup = apiFilterEl ? String(apiFilterEl.value || 'all') : 'all';
                    var searchTerm = apiSearchEl ? apiSearchEl.value.trim().toLowerCase() : '';

                    var items = apiItems.slice();

                    if (selectedGroup !== 'all') {
                        items = items.filter(function (item) {
                            return String(item.package_group || 'other') === selectedGroup;
                        });
                    }

                    if (searchTerm) {
                        items = items.filter(function (item) {
                            var haystack = [
                                item.operator || '',
                                item.receive || '',
                                item.label || '',
                                item.sku_code || '',
                                item.send_value ? String(item.send_value) : '',
                                item.send_currency_iso || '',
                                item.validity || '',
                                formatApiValidityNatural(item.validity) || ''
                            ].join(' ').toLowerCase();
                            return haystack.indexOf(searchTerm) !== -1;
                        });
                    }

                    return items;
                }

                function formatApiValidityNatural(rawValue) {
                    var raw = String(rawValue || '').trim();
                    if (!raw || raw.toUpperCase() === 'N/A') {
                        return '';
                    }

                    var iso = raw.match(/^P(\d+)([DWM])$/i);
                    if (iso) {
                        return formatValidityUnit(iso[1], iso[2]);
                    }

                    var text = raw.match(/(\d{1,4})\s*(d|day|days|dia|dias|días|w|week|weeks|semana|semanas|m|month|months|mes|meses)\b/i);
                    if (text) {
                        return formatValidityUnit(text[1], text[2]);
                    }

                    return raw;
                }

                function formatValidityUnit(value, unitToken) {
                    var amount = parseInt(value, 10);
                    if (!isFinite(amount) || amount <= 0) {
                        return String(value || '').trim();
                    }

                    var token = String(unitToken || '').toUpperCase();
                    if (token === 'D' || token === 'DAY' || token === 'DAYS' || token === 'DIA' || token === 'DIAS' || token === 'DÍAS') {
                        return amount + ' ' + (amount === 1 ? 'día' : 'días');
                    }

                    if (token === 'W' || token === 'WEEK' || token === 'WEEKS' || token === 'SEMANA' || token === 'SEMANAS') {
                        return amount + ' ' + (amount === 1 ? 'semana' : 'semanas');
                    }

                    if (token === 'M' || token === 'MONTH' || token === 'MONTHS' || token === 'MES' || token === 'MESES') {
                        return amount + ' ' + (amount === 1 ? 'mes' : 'meses');
                    }

                    return String(value || '').trim();
                }

                function getManualBundleSourceText(item) {
                    var text = item && (item.label || item.receive || item.sku_code || '');
                    return String(text || '').trim();
                }

                function updateManualBundleSource(item) {
                    // Búsqueda lazy del elemento
                    var sourceEl = document.getElementById('dc_manual_bundle_source');
                    if (!sourceEl) {
                        return;
                    }

                    var text = getManualBundleSourceText(item);
                    if (!text) {
                        sourceEl.textContent = '';
                        sourceEl.hidden = true;
                        return;
                    }

                    sourceEl.textContent = 'Paquete API: ' + text;
                    sourceEl.hidden = false;
                }

                function fillManualForm(item) {
                    // Búsqueda lazy de elementos: se buscan en el momento en que se necesitan
                    var countryEl = document.getElementById('dc_country_iso');
                    var labelEl = document.getElementById('dc_label');
                    var skuEl = document.getElementById('dc_sku_code');
                    var sendValueEl = document.getElementById('dc_send_value');
                    var sendCurrencyEl = document.getElementById('dc_send_currency_iso');
                    var providerEl = document.getElementById('dc_provider_name');
                    var descriptionEl = document.getElementById('dc_description');
                    var publicPriceEl = document.getElementById('dc_public_price');
                    var publicPriceCurrencyEl = document.getElementById('dc_public_price_currency');
                    var packageFamilyEl = document.getElementById('dc_package_family');
                    var productTypeRawEl = document.getElementById('dc_product_type_raw');
                    var validityRawEl = document.getElementById('dc_validity_raw');
                    var providerCodeEl = document.getElementById('dc_provider_code');
                    var regionCodeEl = document.getElementById('dc_region_code');
                    var regionCodesEl = document.getElementById('dc_region_codes');
                    var receiveValueEl = document.getElementById('dc_receive_value');
                    var receiveCurrencyIsoEl = document.getElementById('dc_receive_currency_iso');
                    var receiveValueExcludingTaxEl = document.getElementById('dc_receive_value_excluding_tax');
                    var minimumSendValueEl = document.getElementById('dc_minimum_send_value');
                    var maximumSendValueEl = document.getElementById('dc_maximum_send_value');
                    var minimumReceiveValueEl = document.getElementById('dc_minimum_receive_value');
                    var maximumReceiveValueEl = document.getElementById('dc_maximum_receive_value');
                    var customerFeeEl = document.getElementById('dc_customer_fee');
                    var distributorFeeEl = document.getElementById('dc_distributor_fee');
                    var taxRateEl = document.getElementById('dc_tax_rate');
                    var taxNameEl = document.getElementById('dc_tax_name');
                    var taxCalculationEl = document.getElementById('dc_tax_calculation');
                    var defaultDisplayTextEl = document.getElementById('dc_default_display_text');
                    var displayTextEl = document.getElementById('dc_display_text');
                    var descriptionMarkdownEl = document.getElementById('dc_description_markdown');
                    var readMoreMarkdownEl = document.getElementById('dc_read_more_markdown');
                    var additionalInformationEl = document.getElementById('dc_additional_information');
                    var isPromotionEl = document.getElementById('dc_is_promotion');
                    var isRangeEl = document.getElementById('dc_is_range');
                    var benefitsEl = document.getElementById('dc_benefits');
                    var redemptionMechanismEl = document.getElementById('dc_redemption_mechanism');
                    var processingModeEl = document.getElementById('dc_processing_mode');
                    var lookupBillsRequiredEl = document.getElementById('dc_lookup_bills_required');
                    var settingDefinitionsEl = document.getElementById('dc_setting_definitions');
                    var validationRegexEl = document.getElementById('dc_validation_regex');
                    var customerCareNumberEl = document.getElementById('dc_customer_care_number');
                    var logoUrlEl = document.getElementById('dc_logo_url');
                    var paymentTypesEl = document.getElementById('dc_payment_types');
                    var uatNumberEl = document.getElementById('dc_uat_number');

                    if (countryEl) countryEl.value = item.country_iso || '';
                    if (labelEl) labelEl.value = (item.operator || 'Producto') + ' - ' + (item.receive || item.label || item.sku_code || '');
                    if (skuEl) skuEl.value = item.sku_code || '';
                    if (sendValueEl) sendValueEl.value = item.send_value != null ? item.send_value : '';
                    if (sendCurrencyEl) sendCurrencyEl.value = item.send_currency_iso || 'USD';
                    if (publicPriceEl) publicPriceEl.value = '';
                    if (publicPriceCurrencyEl) publicPriceCurrencyEl.value = 'EUR';
                    if (packageFamilyEl) packageFamilyEl.value = item.package_group || 'other';
                    if (productTypeRawEl) productTypeRawEl.value = item.product_type || '';
                    if (validityRawEl) validityRawEl.value = item.validity || '';
                    if (providerEl) providerEl.value = item.operator || '';
                    if (descriptionEl) descriptionEl.value = item.receive || item.label || '';
                    if (providerCodeEl) providerCodeEl.value = item.provider_code || '';
                    if (regionCodeEl) regionCodeEl.value = item.region_code || '';
                    if (regionCodesEl) regionCodesEl.value = JSON.stringify(item.region_codes || []);
                    if (receiveValueEl) receiveValueEl.value = item.receive_value != null ? item.receive_value : '';
                    if (receiveCurrencyIsoEl) receiveCurrencyIsoEl.value = item.receive_currency_iso || '';
                    if (receiveValueExcludingTaxEl) receiveValueExcludingTaxEl.value = item.receive_value_excluding_tax != null ? item.receive_value_excluding_tax : '';
                    if (minimumSendValueEl) minimumSendValueEl.value = item.minimum_send_value != null ? item.minimum_send_value : '';
                    if (maximumSendValueEl) maximumSendValueEl.value = item.maximum_send_value != null ? item.maximum_send_value : '';
                    if (minimumReceiveValueEl) minimumReceiveValueEl.value = item.minimum_receive_value != null ? item.minimum_receive_value : '';
                    if (maximumReceiveValueEl) maximumReceiveValueEl.value = item.maximum_receive_value != null ? item.maximum_receive_value : '';
                    if (customerFeeEl) customerFeeEl.value = item.customer_fee != null ? item.customer_fee : '';
                    if (distributorFeeEl) distributorFeeEl.value = item.distributor_fee != null ? item.distributor_fee : '';
                    if (taxRateEl) taxRateEl.value = item.tax_rate != null ? item.tax_rate : '';
                    if (taxNameEl) taxNameEl.value = item.tax_name || '';
                    if (taxCalculationEl) taxCalculationEl.value = item.tax_calculation || '';
                    if (defaultDisplayTextEl) defaultDisplayTextEl.value = item.default_display_text || '';
                    if (displayTextEl) displayTextEl.value = item.display_text || '';
                    if (descriptionMarkdownEl) descriptionMarkdownEl.value = item.description_markdown || '';
                    if (readMoreMarkdownEl) readMoreMarkdownEl.value = item.read_more_markdown || '';
                    if (additionalInformationEl) additionalInformationEl.value = item.additional_information || '';
                    if (isPromotionEl) isPromotionEl.value = item.is_promotion ? '1' : '0';
                    if (isRangeEl) isRangeEl.value = item.is_range ? '1' : '0';
                    if (benefitsEl) benefitsEl.value = JSON.stringify(item.benefits || []);
                    if (redemptionMechanismEl) redemptionMechanismEl.value = item.redemption_mechanism || '';
                    if (processingModeEl) processingModeEl.value = item.processing_mode || '';
                    if (lookupBillsRequiredEl) lookupBillsRequiredEl.value = item.lookup_bills_required ? '1' : '0';
                    if (settingDefinitionsEl) settingDefinitionsEl.value = JSON.stringify(item.setting_definitions || []);
                    if (validationRegexEl) validationRegexEl.value = item.validation_regex || '';
                    if (customerCareNumberEl) customerCareNumberEl.value = item.customer_care_number || '';
                    if (logoUrlEl) logoUrlEl.value = item.logo_url || '';
                    if (paymentTypesEl) paymentTypesEl.value = JSON.stringify(item.payment_types || []);
                    if (uatNumberEl) uatNumberEl.value = item.uat_number || '';
                    updateManualBundleSource(item);
                }

                function openManualModal() {
                    var countryEl = document.getElementById('dc_country_iso');

                    // Lazy lookup: el HTML del modal está después del <script>, así que
                    // manualModalEl puede ser null al inicializar. Resolverlo aquí.
                    if (!manualModalEl) {
                        manualModalEl = document.getElementById('dc-manual-modal');
                    }

                    if (!manualModalEl) {
                        return false;
                    }

                    manualModalEl.hidden = false;
                    document.body.classList.add('modal-open');

                    if (countryEl && typeof countryEl.focus === 'function') {
                        countryEl.focus();
                    }

                    return true;
                }

                function closeManualModal() {
                    if (!manualModalEl) {
                        return;
                    }

                    manualModalEl.hidden = true;
                    document.body.classList.remove('modal-open');
                }

                function resetApiState(message) {
                    apiItems = [];
                    apiGroupCounts = {};
                    apiSelected = null;
                    apiSelectedRowEl = null;
                    apiResultsEl.innerHTML = '';
                    apiLoadManualBtn.disabled = true;

                    if (apiFilterEl) {
                        apiFilterEl.innerHTML = '<option value="all">Todos los tipos</option>';
                        apiFilterEl.value = 'all';
                        apiFilterEl.disabled = true;
                    }

                    if (apiSearchEl) {
                        apiSearchEl.value = '';
                        apiSearchEl.disabled = true;
                    }

                    if (message) {
                        apiHelpEl.textContent = message;
                    }
                }

                function setApiSelection(item, row) {
                    apiSelected = item || null;

                    if (apiSelectedRowEl) {
                        apiSelectedRowEl.classList.remove('is-selected');
                        apiSelectedRowEl.removeAttribute('aria-selected');
                    }

                    apiSelectedRowEl = row || null;

                    if (apiSelectedRowEl) {
                        apiSelectedRowEl.classList.add('is-selected');
                        apiSelectedRowEl.setAttribute('aria-selected', 'true');
                    }

                    apiLoadManualBtn.disabled = !apiSelected;
                }

                function renderApiResults(items) {
                    var grouped = {};

                    apiResultsEl.innerHTML = '';
                    setApiSelection(null, null);

                    if (!items || items.length === 0) {
                        apiHelpEl.textContent = apiItems.length > 0
                            ? 'No hay resultados para el tipo de paquete seleccionado.'
                            : 'No se encontraron paquetes para este país en la API.';
                        return;
                    }

                    items
                        .slice()
                        .sort(function (left, right) {
                            var groupCmp = apiGroupOrder(left.package_group) - apiGroupOrder(right.package_group);
                            if (groupCmp !== 0) {
                                return groupCmp;
                            }

                            var operatorCmp = String(left.operator || '').localeCompare(String(right.operator || ''));
                            if (operatorCmp !== 0) {
                                return operatorCmp;
                            }

                            return String(left.label || '').localeCompare(String(right.label || ''));
                        })
                        .forEach(function (item) {
                            var group = String(item.package_group || 'other');
                            if (!grouped[group]) {
                                grouped[group] = [];
                            }
                            grouped[group].push(item);
                        });

                    Object.keys(grouped)
                        .sort(function (left, right) {
                            return apiGroupOrder(left) - apiGroupOrder(right);
                        })
                        .forEach(function (group) {
                            var groupRow = document.createElement('tr');
                            groupRow.className = 'dc-api-group-row';
                            var groupCell = document.createElement('td');
                            groupCell.colSpan = 9;
                            groupCell.textContent = (apiGroupLabels[group] || group) + ' (' + grouped[group].length + ')';
                            groupRow.appendChild(groupCell);
                            apiResultsEl.appendChild(groupRow);

                            grouped[group].forEach(function (item) {
                                var row = document.createElement('tr');
                                row.setAttribute('role', 'row');
                                row.dataset.item = JSON.stringify(item);

                                var tdLogo = document.createElement('td');
                                tdLogo.className = 'dc-api-cell-logo';
                                if (item.logo_url) {
                                    var logoImg = document.createElement('img');
                                    logoImg.src = item.logo_url;
                                    logoImg.alt = item.operator || '';
                                    logoImg.width = 32;
                                    logoImg.height = 32;
                                    logoImg.style.cssText = 'display:block;object-fit:contain;';
                                    tdLogo.appendChild(logoImg);
                                }

                                var tdType = document.createElement('td');
                                tdType.textContent = apiGroupLabels[item.package_group] || item.package_group || 'Otros';

                                var tdOperator = document.createElement('td');
                                tdOperator.textContent = item.operator || '-';

                                var tdReceive = document.createElement('td');
                                tdReceive.textContent = item.receive || item.label || '-';

                                var tdSku = document.createElement('td');
                                tdSku.className = 'dc-api-cell-mono';
                                tdSku.textContent = item.sku_code || '-';

                                var tdSendValue = document.createElement('td');
                                tdSendValue.textContent = item.send_value != null && item.send_value !== '' ? String(item.send_value) : '-';

                                var tdCurrency = document.createElement('td');
                                tdCurrency.textContent = item.send_currency_iso || '-';

                                var tdValidity = document.createElement('td');
                                tdValidity.textContent = formatApiValidityNatural(item.validity) || '-';

                                var tdSource = document.createElement('td');
                                var badge = document.createElement('span');
                                badge.className = 'dc-api-source-badge is-api';
                                badge.textContent = apiSourceLabel(item);
                                tdSource.appendChild(badge);

                                row.appendChild(tdLogo);
                                row.appendChild(tdType);
                                row.appendChild(tdOperator);
                                row.appendChild(tdReceive);
                                row.appendChild(tdSku);
                                row.appendChild(tdSendValue);
                                row.appendChild(tdCurrency);
                                row.appendChild(tdValidity);
                                row.appendChild(tdSource);
                                apiResultsEl.appendChild(row);
                            });
                        });

                    if (apiFilterEl && apiFilterEl.value !== 'all') {
                        apiHelpEl.textContent = items.length + ' paquete(s) en «' + (apiGroupLabels[apiFilterEl.value] || apiFilterEl.value) + '». Selecciona uno para crear el bundle o cargarlo en alta manual.';
                        return;
                    }

                    apiHelpEl.textContent = items.length + ' paquete(s) encontrado(s), agrupados por tipo. Selecciona uno para abrir el modal de alta manual con los datos del paquete.';
                }

                function refreshApiResults() {
                    syncApiFilterOptions();
                    renderApiResults(getFilteredApiItems());
                    if (apiSearchEl) {
                        apiSearchEl.disabled = apiItems.length === 0;
                    }
                }

                apiFetchBtn.addEventListener('click', function () {
                    var iso = apiCountryEl.value;
                    if (!iso) {
                        setApiCountryWarning('Selecciona un país antes de buscar.');
                        apiCountryEl.focus();
                        return;
                    }

                    setApiCountryWarning('');
                    apiFetchBtn.disabled = true;
                    resetApiState('Consultando la API de DingConnect...');

                    var url = ajaxurl + '?action=dc_fetch_api_products&nonce=' + encodeURIComponent(apiNonce) + '&country_iso=' + encodeURIComponent(iso);

                    fetch(url, { credentials: 'same-origin' })
                        .then(function (res) { return res.json(); })
                        .then(function (data) {
                            apiFetchBtn.disabled = false;
                            if (data.success) {
                                apiItems = (data.data && data.data.items) ? data.data.items : [];
                                apiGroupCounts = (data.data && data.data.group_counts) ? data.data.group_counts : {};
                                apiGroupLabels = (data.data && data.data.group_labels) ? data.data.group_labels : apiGroupLabels;
                                refreshApiResults();
                                saveStoredApiState();
                            } else {
                                resetApiState();
                                apiHelpEl.textContent = 'Error: ' + (data.data && data.data.message ? data.data.message : 'No se pudo conectar a la API.');
                            }
                        })
                        .catch(function () {
                            apiFetchBtn.disabled = false;
                            resetApiState();
                            apiHelpEl.textContent = 'Error de red al consultar la API.';
                        });
                });

                if (apiFilterEl) {
                    apiFilterEl.addEventListener('change', function () {
                        renderApiResults(getFilteredApiItems());
                        saveStoredApiState();
                    });
                }

                if (apiCountryEl) {
                    apiCountryEl.addEventListener('change', function () {
                        if (apiCountryEl.value) {
                            setApiCountryWarning('');
                        }
                    });
                }

                if (apiSearchEl) {
                    apiSearchEl.addEventListener('input', function () {
                        renderApiResults(getFilteredApiItems());
                        saveStoredApiState();
                    });
                }

                apiResultsEl.addEventListener('click', function (event) {
                    var row = event.target && event.target.closest ? event.target.closest('tr[data-item]') : null;
                    if (!row) {
                        return;
                    }

                    try {
                        setApiSelection(JSON.parse(row.dataset.item), row);
                    } catch (e) {
                        setApiSelection(null, null);
                    }
                });

                apiResultsEl.addEventListener('dblclick', function (event) {
                    var row = event.target && event.target.closest ? event.target.closest('tr[data-item]') : null;
                    if (!row) {
                        return;
                    }

                    try {
                        setApiSelection(JSON.parse(row.dataset.item), row);
                    } catch (e) {
                        setApiSelection(null, null);
                    }

                    if (!apiSelected) {
                        return;
                    }

                    apiLoadManualBtn.click();
                });

                apiLoadManualBtn.addEventListener('click', function () {
                    if (!apiSelected) { return; }

                    try {
                        fillManualForm(apiSelected);
                        if (!openManualModal()) {
                            throw new Error('manual-modal-missing');
                        }
                        apiHelpEl.textContent = 'Producto cargado en el modal de alta manual. Revisa los campos y guarda el bundle cuando quieras.';
                    } catch (e) {
                        apiHelpEl.textContent = 'No se pudo cargar el producto seleccionado en el formulario manual.';
                    }
                });

                document.addEventListener('click', function (event) {
                    if (event.target && typeof event.target.closest === 'function' && event.target.closest('[data-dc-manual-close]')) {
                        closeManualModal();
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && manualModalEl && !manualModalEl.hidden) {
                        closeManualModal();
                    }
                });

                restoreStoredApiState();
            })();
            </script>

            <div id="dc-manual-modal" class="dc-edit-modal" role="dialog" aria-modal="true" aria-labelledby="dc-manual-modal-title" hidden>
                <div class="dc-edit-modal__backdrop" data-dc-manual-close></div>
                <div class="dc-edit-modal__dialog dc-manual-modal__dialog">
                    <div class="dc-edit-modal__header">
                        <div>
                            <h3 id="dc-manual-modal-title">Alta manual</h3>
                            <p>Revisa y ajusta los datos del producto seleccionado antes de guardarlo como bundle.</p>
                        </div>
                        <button type="button" class="dc-edit-modal__close" aria-label="Cerrar alta manual" data-dc-manual-close>&times;</button>
                    </div>

            <p class="dc-manual-modal__intro">El modal se precarga desde la búsqueda API para que completes el alta sin cambiar de pantalla.</p>

            <h4 class="dc-manual-bundle-heading">Datos del bundle <span id="dc_manual_bundle_source" class="dc-manual-bundle-source" hidden></span></h4>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="dc_add_bundle">
                <?php wp_nonce_field('dc_add_bundle'); ?>
                <input type="hidden" id="dc_package_family" name="package_family" value="other">
                <input type="hidden" id="dc_product_type_raw" name="product_type_raw" value="">
                <input type="hidden" id="dc_validity_raw" name="validity_raw" value="">
                <input type="hidden" id="dc_provider_code" name="provider_code" value="">
                <input type="hidden" id="dc_region_code" name="region_code" value="">
                <input type="hidden" id="dc_region_codes" name="region_codes" value="[]">
                <input type="hidden" id="dc_receive_value" name="receive_value" value="0">
                <input type="hidden" id="dc_receive_currency_iso" name="receive_currency_iso" value="">
                <input type="hidden" id="dc_receive_value_excluding_tax" name="receive_value_excluding_tax" value="0">
                <input type="hidden" id="dc_minimum_send_value" name="minimum_send_value" value="0">
                <input type="hidden" id="dc_maximum_send_value" name="maximum_send_value" value="0">
                <input type="hidden" id="dc_minimum_receive_value" name="minimum_receive_value" value="0">
                <input type="hidden" id="dc_maximum_receive_value" name="maximum_receive_value" value="0">
                <input type="hidden" id="dc_customer_fee" name="customer_fee" value="0">
                <input type="hidden" id="dc_distributor_fee" name="distributor_fee" value="0">
                <input type="hidden" id="dc_tax_rate" name="tax_rate" value="0">
                <input type="hidden" id="dc_tax_name" name="tax_name" value="">
                <input type="hidden" id="dc_tax_calculation" name="tax_calculation" value="">
                <input type="hidden" id="dc_default_display_text" name="default_display_text" value="">
                <input type="hidden" id="dc_display_text" name="display_text" value="">
                <input type="hidden" id="dc_description_markdown" name="description_markdown" value="">
                <input type="hidden" id="dc_read_more_markdown" name="read_more_markdown" value="">
                <input type="hidden" id="dc_additional_information" name="additional_information" value="">
                <input type="hidden" id="dc_is_promotion" name="is_promotion" value="0">
                <input type="hidden" id="dc_is_range" name="is_range" value="0">
                <input type="hidden" id="dc_benefits" name="benefits" value="[]">
                <input type="hidden" id="dc_redemption_mechanism" name="redemption_mechanism" value="">
                <input type="hidden" id="dc_processing_mode" name="processing_mode" value="">
                <input type="hidden" id="dc_lookup_bills_required" name="lookup_bills_required" value="0">
                <input type="hidden" id="dc_setting_definitions" name="setting_definitions" value="[]">
                <input type="hidden" id="dc_validation_regex" name="validation_regex" value="">
                <input type="hidden" id="dc_customer_care_number" name="customer_care_number" value="">
                <input type="hidden" id="dc_logo_url" name="logo_url" value="">
                <input type="hidden" id="dc_payment_types" name="payment_types" value="[]">
                <input type="hidden" id="dc_uat_number" name="uat_number" value="">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="dc_country_iso">País ISO</label></th>
                        <td><input required type="text" id="dc_country_iso" name="country_iso" class="small-text dc-combo-input" placeholder="CU" value="" list="dc_dl_country_iso"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_label">Nombre comercial</label></th>
                        <td><input required type="text" id="dc_label" name="label" class="regular-text dc-combo-input" placeholder="Cubacel 500 CUP" value="" list="dc_dl_label"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_sku_code">SKU Code</label></th>
                        <td><input required type="text" id="dc_sku_code" name="sku_code" class="regular-text" placeholder="SKU_REAL_DING" value=""></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_send_value">Coste DIN</label></th>
                        <td><input type="number" step="0.01" id="dc_send_value" name="send_value" class="small-text" value="0"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_send_currency_iso">Moneda coste</label></th>
                        <td><input type="text" id="dc_send_currency_iso" name="send_currency_iso" class="small-text dc-combo-input" value="USD" list="dc_dl_send_currency"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_public_price">Precio al Público</label></th>
                        <td><input type="number" step="0.01" id="dc_public_price" name="public_price" class="small-text" value=""></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_public_price_currency">Moneda pública</label></th>
                        <td><input type="text" id="dc_public_price_currency" name="public_price_currency" class="small-text dc-combo-input" value="EUR" list="dc_dl_public_currency"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_provider_name">Operador</label></th>
                        <td><input type="text" id="dc_provider_name" name="provider_name" class="regular-text dc-combo-input" placeholder="Cubacel" value="" list="dc_dl_provider_name"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_description">Beneficios recibidos</label></th>
                        <td>
                            <textarea id="dc_description" name="description" class="regular-text" rows="2" placeholder="Ej: Monthly 30GB, Daily 125 Min, USD 10"></textarea>
                            <p class="description">Lo que recibe el usuario (beneficio o descripción operativa del paquete). Texto corto y claro.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Activo</th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_active" value="1" checked>
                                Mostrar bundle en el frontend
                            </label>
                        </td>
                    </tr>
                </table>

                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:16px;">
                    <button type="button" class="button button-secondary" data-dc-manual-close>Cancelar</button>
                    <?php submit_button('Añadir bundle', 'primary', 'submit', false); ?>
                </div>
            </form>

                </div>
            </div>

                </section>

                <section id="dc-tab-saved" class="dc-tab-panel is-special" data-dc-tab-panel="tab_saved">

            <h2>Productos guardados</h2>
            <p>Estos productos aparecen como respaldo o catálogo inicial en el formulario frontal.</p>

            <?php
                $saved_family_labels = [
                    'topup' => 'Top-up',
                    'data' => 'Data',
                    'combo' => 'Combo',
                    'voucher' => 'Voucher',
                    'dth' => 'DTH',
                    'other' => 'Otros',
                ];
            ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="dc_bulk_delete_bundles_form">
                <input type="hidden" name="action" value="dc_bulk_delete_bundles">
                <?php wp_nonce_field('dc_bulk_delete_bundles'); ?>
                <p>
                    <button type="submit" class="button button-secondary" id="dc_bulk_delete_btn">Eliminar seleccionados</button>
                    <span class="description">Puedes seleccionar uno o varios productos desde la tabla.</span>
                </p>

            <div class="dc-saved-bundles-filters" aria-label="Filtros de productos guardados">
                <input type="text" id="dc_saved_products_search" class="regular-text" placeholder="Buscar por nombre, SKU, operador o país...">
                <select id="dc_saved_products_filter_family" class="regular-text">
                    <option value="all">Tipo de producto: Todos</option>
                </select>
                <select id="dc_saved_products_filter_country" class="regular-text">
                    <option value="all">País: Todos</option>
                </select>
                <select id="dc_saved_products_filter_operator" class="regular-text">
                    <option value="all">Operador: Todos</option>
                </select>
            </div>

            <div class="dc-saved-bundles-table-wrap" role="region" aria-label="Tabla de productos guardados">
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th class="check-column">
                            <input type="checkbox" id="dc_bundles_select_all" aria-label="Seleccionar todos los productos">
                        </th>
                        <th>Logo URL</th>
                        <th>País</th>
                        <th>Tipo</th>
                        <th>Nombre</th>
                        <th>SKU</th>
                        <th>Coste DIN</th>
                        <th>Moneda coste</th>
                        <th>Precio público</th>
                        <th>Moneda pública</th>
                        <th>Operador</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($bundles)) : ?>
                    <tr>
                        <td colspan="13">Aún no has agregado productos.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($bundles as $bundle) : ?>
                        <?php
                            $bundle_country_iso = strtoupper((string) ($bundle['country_iso'] ?? ''));
                            $bundle_package_family = sanitize_key((string) ($bundle['package_family'] ?? 'other'));
                            if ($bundle_package_family === '') {
                                $bundle_package_family = 'other';
                            }
                            $bundle_family_label = $saved_family_labels[$bundle_package_family] ?? ucfirst($bundle_package_family);
                            $bundle_operator_name = sanitize_text_field((string) ($bundle['provider_name'] ?? ''));
                            $bundle_label = sanitize_text_field((string) ($bundle['label'] ?? ''));
                            $bundle_sku_code = sanitize_text_field((string) ($bundle['sku_code'] ?? ''));
                            $bundle_logo_url = esc_url((string) ($bundle['logo_url'] ?? ''));
                            $bundle_product_type_raw = sanitize_text_field((string) ($bundle['product_type_raw'] ?? ''));
                            $bundle_search_index = strtolower(trim(implode(' ', array_filter([
                                $bundle_label,
                                $bundle_sku_code,
                                $bundle_operator_name,
                                $bundle_country_iso,
                                $bundle_family_label,
                                $bundle_product_type_raw,
                            ]))));
                        ?>
                        <tr class="dc-row-editable" tabindex="0" role="button" data-edit-bundle="<?php echo esc_attr(wp_json_encode($bundle)); ?>" data-family="<?php echo esc_attr($bundle_package_family); ?>" data-country-iso="<?php echo esc_attr($bundle_country_iso); ?>" data-operator-name="<?php echo esc_attr($bundle_operator_name); ?>" data-search-index="<?php echo esc_attr($bundle_search_index); ?>" aria-label="Editar producto <?php echo esc_attr($bundle['label'] ?? ''); ?>">
                            <td class="check-column">
                                <input type="checkbox" class="dc-bundle-checkbox" name="bundle_ids[]" value="<?php echo esc_attr($bundle['id'] ?? ''); ?>" aria-label="Seleccionar producto <?php echo esc_attr($bundle['label'] ?? ''); ?>">
                            </td>
                            <td class="dc-saved-bundles-logo-url">
                                <?php if ($bundle_logo_url !== '') : ?>
                                    <a href="<?php echo esc_url($bundle_logo_url); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr($bundle_logo_url); ?>"><?php echo esc_html($bundle_logo_url); ?></a>
                                <?php else : ?>
                                    <span class="description">Sin logo</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($bundle_country_iso); ?></td>
                            <td><?php echo esc_html($bundle_family_label); ?></td>
                            <td><?php echo esc_html($bundle_label); ?></td>
                            <td><?php echo esc_html($bundle_sku_code); ?></td>
                            <td><?php echo esc_html(number_format((float) ($bundle['send_value'] ?? 0), 2)); ?></td>
                            <td><?php echo esc_html($bundle['send_currency_iso'] ?? ''); ?></td>
                            <td><?php echo esc_html(isset($bundle['public_price']) && $bundle['public_price'] !== '' ? number_format((float) $bundle['public_price'], 2) : ''); ?></td>
                            <td><?php echo esc_html($bundle['public_price_currency'] ?? 'EUR'); ?></td>
                            <td><?php echo esc_html($bundle_operator_name); ?></td>
                            <td><?php echo !empty($bundle['is_active']) ? 'Activo' : 'Inactivo'; ?></td>
                            <td>
                                <div class="dc-bundle-actions dc-table-actions">
                                <a class="button dc-table-icon-btn" href="<?php echo esc_url(wp_nonce_url(add_query_arg([
                                    'action' => 'dc_toggle_bundle',
                                    'bundle_id' => $bundle['id'] ?? '',
                                ], admin_url('admin-post.php')), 'dc_toggle_bundle')); ?>" title="<?php echo !empty($bundle['is_active']) ? esc_attr('Desactivar producto') : esc_attr('Activar producto'); ?>" aria-label="<?php echo !empty($bundle['is_active']) ? esc_attr('Desactivar producto ' . ($bundle['label'] ?? '')) : esc_attr('Activar producto ' . ($bundle['label'] ?? '')); ?>">
                                    <span class="dashicons <?php echo !empty($bundle['is_active']) ? 'dashicons-hidden' : 'dashicons-visibility'; ?>" aria-hidden="true"></span>
                                </a>

                                <a class="button button-secondary dc-table-icon-btn" href="<?php echo esc_url(wp_nonce_url(add_query_arg([
                                    'action' => 'dc_delete_bundle',
                                    'bundle_id' => $bundle['id'] ?? '',
                                ], admin_url('admin-post.php')), 'dc_delete_bundle')); ?>" onclick="return confirm('¿Eliminar producto?');" title="Eliminar producto" aria-label="Eliminar producto <?php echo esc_attr($bundle['label'] ?? ''); ?>">
                                    <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
            <p id="dc_saved_products_no_results" class="description" hidden>No hay productos guardados que coincidan con los filtros.</p>
            </form>

            <div id="dc-edit-modal" class="dc-edit-modal" role="dialog" aria-modal="true" aria-labelledby="dc-edit-modal-title" <?php echo empty($editing_bundle) ? 'hidden' : ''; ?>>
                <div class="dc-edit-modal__backdrop" data-dc-edit-close></div>
                <div class="dc-edit-modal__dialog">
                    <div class="dc-edit-modal__header">
                        <div>
                            <h3 id="dc-edit-modal-title">Editar producto</h3>
                            <p>Modifica el producto sin salir de la tabla de productos guardados.</p>
                        </div>
                        <button type="button" class="dc-edit-modal__close" aria-label="Cerrar edición" data-dc-edit-close>&times;</button>
                    </div>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="dc_edit_bundle_form">
                        <input type="hidden" name="action" value="dc_update_bundle">
                        <?php wp_nonce_field('dc_update_bundle'); ?>
                        <input type="hidden" id="dc_edit_bundle_id" name="bundle_id" value="<?php echo esc_attr($editing_bundle['id'] ?? ''); ?>">
                        <input type="hidden" id="dc_edit_package_family" name="package_family" value="<?php echo esc_attr($editing_bundle['package_family'] ?? 'other'); ?>">
                        <input type="hidden" id="dc_edit_product_type_raw" name="product_type_raw" value="<?php echo esc_attr($editing_bundle['product_type_raw'] ?? ''); ?>">
                        <input type="hidden" id="dc_edit_validity_raw" name="validity_raw" value="<?php echo esc_attr($editing_bundle['validity_raw'] ?? ''); ?>">

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="dc_edit_country_iso">País ISO</label></th>
                                <td><input required type="text" id="dc_edit_country_iso" name="country_iso" class="small-text dc-combo-input" placeholder="CU" value="<?php echo esc_attr($editing_bundle['country_iso'] ?? ''); ?>" list="dc_dl_country_iso"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="dc_edit_label">Nombre comercial</label></th>
                                <td><input required type="text" id="dc_edit_label" name="label" class="regular-text dc-combo-input" placeholder="Cubacel 500 CUP" value="<?php echo esc_attr($editing_bundle['label'] ?? ''); ?>" list="dc_dl_label"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="dc_edit_sku_code">SKU Code</label></th>
                                <td><input required type="text" id="dc_edit_sku_code" name="sku_code" class="regular-text" placeholder="SKU_REAL_DING" value="<?php echo esc_attr($editing_bundle['sku_code'] ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="dc_edit_send_value">Coste DIN</label></th>
                                <td><input type="number" step="0.01" id="dc_edit_send_value" name="send_value" class="small-text" value="<?php echo esc_attr(isset($editing_bundle['send_value']) ? (float) $editing_bundle['send_value'] : 0); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="dc_edit_send_currency_iso">Moneda coste</label></th>
                                <td><input type="text" id="dc_edit_send_currency_iso" name="send_currency_iso" class="small-text dc-combo-input" value="<?php echo esc_attr($editing_bundle['send_currency_iso'] ?? 'USD'); ?>" list="dc_dl_send_currency"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="dc_edit_public_price">Precio al Público</label></th>
                                <td><input type="number" step="0.01" id="dc_edit_public_price" name="public_price" class="small-text" value="<?php echo esc_attr(isset($editing_bundle['public_price']) ? (float) $editing_bundle['public_price'] : ''); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="dc_edit_public_price_currency">Moneda pública</label></th>
                                <td><input type="text" id="dc_edit_public_price_currency" name="public_price_currency" class="small-text dc-combo-input" value="<?php echo esc_attr($editing_bundle['public_price_currency'] ?? 'EUR'); ?>" list="dc_dl_public_currency"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="dc_edit_provider_name">Operador</label></th>
                                <td><input type="text" id="dc_edit_provider_name" name="provider_name" class="regular-text dc-combo-input" placeholder="Cubacel" value="<?php echo esc_attr($editing_bundle['provider_name'] ?? ''); ?>" list="dc_dl_provider_name"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="dc_edit_description">Beneficios recibidos</label></th>
                                <td>
                                    <textarea id="dc_edit_description" name="description" class="regular-text" rows="2" placeholder="Ej: Monthly 30GB, Daily 125 Min, USD 10"><?php echo esc_textarea($editing_bundle['description'] ?? ''); ?></textarea>
                                    <p class="description">Lo que recibe el usuario (beneficio o descripción operativa del paquete).</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Activo</th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="dc_edit_is_active" name="is_active" value="1" <?php checked(!empty($editing_bundle['is_active'])); ?>>
                                        Mostrar bundle en el frontend
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button('Guardar cambios del producto', 'primary', 'submit', false); ?>
                        <button type="button" class="button button-secondary" data-dc-edit-close>Cancelar</button>
                    </form>
                </div>
            </div>

                </section>

                <section id="dc-tab-logs" class="dc-tab-panel" data-dc-tab-panel="tab_logs">
                    <h2>Registros y diagnóstico</h2>
                    <p>Historial de intentos de recarga, pagos exitosos, errores y respuestas de la API. Los números de teléfono se almacenan enmascarados por privacidad.</p>

                    <?php
                    $logs_nonce = wp_create_nonce('dc_get_logs');
                    $log_stats = $this->get_transfer_log_stats();
                    $submitted_rows = $this->get_submitted_monitor_rows(120);
                    ?>

                    <div class="dc-submitted-monitor">
                        <h3>Monitor de recargas pendientes (Submitted)</h3>
                        <p class="description">Seguimiento operativo de recargas WooCommerce en estado pendiente o escalado.</p>
                        <?php if (empty($submitted_rows)): ?>
                            <p class="description" style="margin:0 0 12px;">No hay recargas pendientes en este momento.</p>
                        <?php else: ?>
                            <div class="dc-submitted-table-wrap" role="region" aria-label="Monitor de recargas pendientes">
                                <table class="widefat striped">
                                    <thead>
                                        <tr>
                                            <th>Orden</th>
                                            <th>Teléfono</th>
                                            <th>Proveedor / SKU</th>
                                            <th>Estado</th>
                                            <th>Intentos</th>
                                            <th>En estado</th>
                                            <th>Próximo reintento</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($submitted_rows as $row): ?>
                                            <tr>
                                                <td><a href="<?php echo esc_url((string) $row['order_url']); ?>">#<?php echo esc_html((string) $row['order_id']); ?></a></td>
                                                <td><?php echo esc_html((string) $row['phone']); ?></td>
                                                <td>
                                                    <strong><?php echo esc_html((string) $row['provider']); ?></strong><br>
                                                    <span style="color:#64748b;"><?php echo esc_html((string) $row['sku_code']); ?></span>
                                                </td>
                                                <td><code><?php echo esc_html(strtoupper((string) $row['status'])); ?></code></td>
                                                <td><?php echo esc_html((string) $row['transfer_attempts']); ?> / <?php echo esc_html((string) $row['submitted_attempts']); ?></td>
                                                <td><?php echo esc_html((string) $row['age_minutes']); ?> min</td>
                                                <td><?php echo esc_html((string) ($row['next_retry_at'] !== '' ? $row['next_retry_at'] : 'N/A')); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="dc-logs-summary">
                        <div class="dc-logs-stat">
                            <strong><?php echo (int) $log_stats['total']; ?></strong>
                            <span>Total registros</span>
                        </div>
                        <div class="dc-logs-stat dc-logs-stat--success">
                            <strong><?php echo (int) $log_stats['success']; ?></strong>
                            <span>Exitosos</span>
                        </div>
                        <div class="dc-logs-stat dc-logs-stat--error">
                            <strong><?php echo (int) $log_stats['error']; ?></strong>
                            <span>Errores</span>
                        </div>
                        <div class="dc-logs-stat dc-logs-stat--validate">
                            <strong><?php echo (int) $log_stats['validate']; ?></strong>
                            <span>Simulados</span>
                        </div>
                    </div>

                    <div class="dc-logs-toolbar">
                        <input type="text" id="dc-logs-search" class="regular-text" placeholder="Buscar por teléfono o SKU...">
                        <select id="dc-logs-status">
                            <option value="">Todos los estados</option>
                            <option value="TransferSuccessful">Exitosos</option>
                            <option value="validate">Simulados (validate)</option>
                            <option value="error">Errores</option>
                            <option value="unknown">Desconocido</option>
                        </select>
                        <input type="date" id="dc-logs-date-from" title="Desde">
                        <input type="date" id="dc-logs-date-to" title="Hasta">
                        <button type="button" class="button" id="dc-logs-search-btn">Filtrar</button>
                        <button type="button" class="button" id="dc-logs-reset-btn">Limpiar filtros</button>
                    </div>

                    <div id="dc-logs-result">
                        <p class="dc-logs-loading">Cargando registros...</p>
                    </div>

                    <div class="dc-logs-footer">
                        <div id="dc-logs-pagination"></div>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('¿Eliminar todos los registros de transferencias? Esta acción no se puede deshacer.');">
                            <input type="hidden" name="action" value="dc_clear_logs">
                            <?php wp_nonce_field('dc_clear_logs'); ?>
                            <button type="submit" class="button dc-logs-clear-btn">Borrar todos los registros</button>
                        </form>
                    </div>

                    <style>
                        .dc-logs-summary {
                            display: flex;
                            flex-wrap: wrap;
                            gap: 12px;
                            margin-bottom: 18px;
                        }

                        .dc-submitted-monitor {
                            border: 1px solid #dbe4f0;
                            background: #f8fbff;
                            border-radius: 12px;
                            padding: 14px;
                            margin-bottom: 16px;
                        }

                        .dc-submitted-monitor h3 {
                            margin: 0 0 6px;
                        }

                        .dc-submitted-table-wrap {
                            overflow-x: auto;
                            margin-top: 10px;
                        }

                        .dc-logs-stat {
                            background: #f8fafc;
                            border: 1px solid #e2e8f0;
                            border-radius: 10px;
                            padding: 12px 18px;
                            min-width: 110px;
                            text-align: center;
                        }

                        .dc-logs-stat strong {
                            display: block;
                            font-size: 28px;
                            line-height: 1.1;
                            color: #0f172a;
                        }

                        .dc-logs-stat span {
                            font-size: 12px;
                            color: #64748b;
                            text-transform: uppercase;
                            letter-spacing: 0.04em;
                        }

                        .dc-logs-stat--success { border-color: #86efac; background: #f0fdf4; }
                        .dc-logs-stat--success strong { color: #15803d; }
                        .dc-logs-stat--error { border-color: #fca5a5; background: #fef2f2; }
                        .dc-logs-stat--error strong { color: #dc2626; }
                        .dc-logs-stat--validate { border-color: #fde68a; background: #fffbeb; }
                        .dc-logs-stat--validate strong { color: #b45309; }

                        .dc-logs-toolbar {
                            display: flex;
                            flex-wrap: wrap;
                            gap: 8px;
                            align-items: center;
                            margin-bottom: 14px;
                        }

                        .dc-logs-toolbar input[type="text"],
                        .dc-logs-toolbar input[type="date"],
                        .dc-logs-toolbar select {
                            border: 1px solid #c9d6ea;
                            border-radius: 8px;
                            padding: 5px 10px;
                            font-size: 13px;
                        }

                        .dc-logs-toolbar input[type="text"] {
                            min-width: 200px;
                        }

                        #dc-logs-result table {
                            width: 100%;
                            border-collapse: collapse;
                        }

                        #dc-logs-result th,
                        #dc-logs-result td {
                            text-align: left;
                            padding: 8px 10px;
                            font-size: 13px;
                            border-bottom: 1px solid #e9eef5;
                            vertical-align: top;
                        }

                        #dc-logs-result th {
                            background: #f1f5f9;
                            font-weight: 600;
                            color: #334155;
                        }

                        #dc-logs-result tr:hover td {
                            background: #f8faff;
                        }

                        .dc-log-badge {
                            display: inline-block;
                            padding: 2px 8px;
                            border-radius: 999px;
                            font-size: 11px;
                            font-weight: 700;
                            text-transform: uppercase;
                            letter-spacing: 0.04em;
                        }

                        .dc-log-badge--success { background: #dcfce7; color: #15803d; }
                        .dc-log-badge--error { background: #fee2e2; color: #dc2626; }
                        .dc-log-badge--validate { background: #fef9c3; color: #b45309; }
                        .dc-log-badge--unknown { background: #f1f5f9; color: #64748b; }

                        .dc-logs-expand-btn {
                            background: none;
                            border: none;
                            color: #0f4aa3;
                            cursor: pointer;
                            font-size: 12px;
                            padding: 0;
                            text-decoration: underline;
                        }

                        .dc-logs-raw {
                            display: none;
                            margin-top: 6px;
                            background: #0f172a;
                            color: #e2e8f0;
                            border-radius: 8px;
                            padding: 10px;
                            font-size: 11px;
                            font-family: monospace;
                            white-space: pre-wrap;
                            word-break: break-all;
                            max-height: 260px;
                            overflow: auto;
                        }

                        .dc-logs-loading {
                            color: #64748b;
                            font-style: italic;
                            padding: 20px 0;
                        }

                        .dc-logs-footer {
                            display: flex;
                            align-items: center;
                            justify-content: space-between;
                            flex-wrap: wrap;
                            gap: 12px;
                            margin-top: 16px;
                        }

                        #dc-logs-pagination {
                            display: flex;
                            gap: 6px;
                            flex-wrap: wrap;
                        }

                        #dc-logs-pagination button {
                            min-width: 34px;
                        }

                        #dc-logs-pagination button.is-current {
                            background: #0f4aa3;
                            border-color: #0f4aa3;
                            color: #fff;
                        }

                        .dc-logs-clear-btn {
                            color: #dc2626 !important;
                            border-color: #fca5a5 !important;
                        }

                        .dc-logs-clear-btn:hover {
                            background: #fef2f2 !important;
                        }

                        @media (max-width: 782px) {
                            .dc-logs-toolbar {
                                flex-direction: column;
                                align-items: stretch;
                            }

                            .dc-logs-toolbar input[type="text"] {
                                min-width: 0;
                            }
                        }
                    </style>

                    <script>
                    (function () {
                        var searchEl    = document.getElementById('dc-logs-search');
                        var statusEl    = document.getElementById('dc-logs-status');
                        var dateFromEl  = document.getElementById('dc-logs-date-from');
                        var dateToEl    = document.getElementById('dc-logs-date-to');
                        var searchBtn   = document.getElementById('dc-logs-search-btn');
                        var resetBtn    = document.getElementById('dc-logs-reset-btn');
                        var resultEl    = document.getElementById('dc-logs-result');
                        var paginEl     = document.getElementById('dc-logs-pagination');
                        var logsNonce   = '<?php echo esc_js($logs_nonce); ?>';
                        var currentPage = 1;

                        function badgeClass(status) {
                            if (!status) return 'dc-log-badge--unknown';
                            var s = status.toLowerCase();
                            if (s === 'transfersuccessful') return 'dc-log-badge--success';
                            if (s === 'error') return 'dc-log-badge--error';
                            if (s.indexOf('validate') !== -1) return 'dc-log-badge--validate';
                            return 'dc-log-badge--unknown';
                        }

                        function renderTable(logs) {
                            if (!logs || !logs.length) {
                                resultEl.innerHTML = '<p style="color:#64748b;padding:20px 0;">No se encontraron registros con los filtros aplicados.</p>';
                                return;
                            }

                            var html = '<table><thead><tr>'
                                + '<th>Fecha</th>'
                                + '<th>Teléfono</th>'
                                + '<th>SKU</th>'
                                + '<th>Monto</th>'
                                + '<th>Estado</th>'
                                + '<th>Ref. distribuidor</th>'
                                + '<th>Ref. transferencia</th>'
                                + '<th>Respuesta</th>'
                                + '</tr></thead><tbody>';

                            logs.forEach(function (log, i) {
                                var badge = '<span class="dc-log-badge ' + badgeClass(log.status) + '">' + esc(log.status || 'unknown') + '</span>';
                                var rawId = 'dc-log-raw-' + i;
                                html += '<tr>'
                                    + '<td>' + esc(log.date) + '</td>'
                                    + '<td>' + esc(log.account_number) + '</td>'
                                    + '<td>' + esc(log.sku_code) + '</td>'
                                    + '<td>' + esc(log.send_value) + ' ' + esc(log.currency) + '</td>'
                                    + '<td>' + badge + '</td>'
                                    + '<td style="font-size:11px;word-break:break-all;">' + esc(log.distributor_ref) + '</td>'
                                    + '<td style="font-size:11px;word-break:break-all;">' + esc(log.transfer_ref) + '</td>'
                                    + '<td>'
                                        + '<button type="button" class="dc-logs-expand-btn" data-target="' + rawId + '">Ver respuesta</button>'
                                        + '<pre id="' + rawId + '" class="dc-logs-raw">' + esc(log.raw_response) + '</pre>'
                                    + '</td>'
                                    + '</tr>';
                            });

                            html += '</tbody></table>';
                            resultEl.innerHTML = html;

                            resultEl.querySelectorAll('.dc-logs-expand-btn').forEach(function (btn) {
                                btn.addEventListener('click', function () {
                                    var pre = document.getElementById(btn.getAttribute('data-target'));
                                    if (!pre) return;
                                    var open = pre.style.display === 'block';
                                    pre.style.display = open ? 'none' : 'block';
                                    btn.textContent = open ? 'Ver respuesta' : 'Ocultar';
                                });
                            });
                        }

                        function renderPagination(page, totalPages) {
                            paginEl.innerHTML = '';
                            if (totalPages <= 1) return;

                            for (var p = 1; p <= totalPages; p++) {
                                (function (pg) {
                                    var btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'button' + (pg === page ? ' is-current' : '');
                                    btn.textContent = pg;
                                    btn.addEventListener('click', function () {
                                        currentPage = pg;
                                        load();
                                    });
                                    paginEl.appendChild(btn);
                                })(p);
                            }
                        }

                        function esc(str) {
                            if (str === null || str === undefined) return '';
                            return String(str)
                                .replace(/&/g, '&amp;')
                                .replace(/</g, '&lt;')
                                .replace(/>/g, '&gt;')
                                .replace(/"/g, '&quot;');
                        }

                        async function load() {
                            resultEl.innerHTML = '<p class="dc-logs-loading">Cargando registros...</p>';
                            paginEl.innerHTML = '';

                            try {
                                var params = new URLSearchParams({
                                    action: 'dc_get_transfer_logs',
                                    nonce: logsNonce,
                                    search: searchEl ? searchEl.value : '',
                                    status: statusEl ? statusEl.value : '',
                                    date_from: dateFromEl ? dateFromEl.value : '',
                                    date_to: dateToEl ? dateToEl.value : '',
                                    paged: currentPage,
                                });

                                var resp = await fetch(ajaxurl + '?' + params.toString());
                                var data = await resp.json();

                                if (!data || !data.success) {
                                    throw new Error((data && data.data && data.data.message) || 'Error al cargar registros.');
                                }

                                renderTable(data.data.logs);
                                renderPagination(data.data.page, data.data.total_pages);
                            } catch (err) {
                                resultEl.innerHTML = '<p style="color:#dc2626;">' + esc(err.message || 'Error inesperado.') + '</p>';
                            }
                        }

                        if (searchBtn) {
                            searchBtn.addEventListener('click', function () {
                                currentPage = 1;
                                load();
                            });
                        }

                        if (resetBtn) {
                            resetBtn.addEventListener('click', function () {
                                if (searchEl) searchEl.value = '';
                                if (statusEl) statusEl.value = '';
                                if (dateFromEl) dateFromEl.value = '';
                                if (dateToEl) dateToEl.value = '';
                                currentPage = 1;
                                load();
                            });
                        }

                        if (searchEl) {
                            searchEl.addEventListener('keydown', function (e) {
                                if (e.key === 'Enter') { currentPage = 1; load(); }
                            });
                        }

                        // Auto-load when the tab becomes active.
                        var logsLoaded = false;
                        document.addEventListener('dc:tab-activated', function (e) {
                            if (e.detail === 'tab_logs' && !logsLoaded) {
                                logsLoaded = true;
                                load();
                            }
                        });

                        // Also load if already the active tab on page load.
                        document.addEventListener('DOMContentLoaded', function () {
                            if (document.querySelector('[data-dc-tab-panel="tab_logs"].is-active')) {
                                logsLoaded = true;
                                load();
                            }
                        });
                    })();
                    </script>
                </section>
            </div>

        </div>

        <?php // Shared datalists for combobox fields — populated from existing bundles. ?>
        <datalist id="dc_dl_country_iso">
            <?php foreach ($landing_country_choices as $country_choice) : ?>
                <option value="<?php echo esc_attr((string) ($country_choice['iso'] ?? '')); ?>" label="<?php echo esc_attr((string) ($country_choice['label'] ?? ($country_choice['iso'] ?? ''))); ?>">
            <?php endforeach; ?>
        </datalist>
        <datalist id="dc_dl_label">
            <?php foreach ($dl_label as $v) : ?>
                <option value="<?php echo esc_attr($v); ?>">
            <?php endforeach; ?>
        </datalist>
        <datalist id="dc_dl_send_currency">
            <?php foreach ($dl_send_currency as $v) : ?>
                <option value="<?php echo esc_attr($v); ?>">
            <?php endforeach; ?>
        </datalist>
        <datalist id="dc_dl_public_currency">
            <?php foreach ($dl_public_currency as $v) : ?>
                <option value="<?php echo esc_attr($v); ?>">
            <?php endforeach; ?>
            <option value="EUR">
        </datalist>
        <datalist id="dc_dl_provider_name">
            <?php foreach ($dl_provider_name as $v) : ?>
                <option value="<?php echo esc_attr($v); ?>">
            <?php endforeach; ?>
        </datalist>

        <script>
            (function () {
                var tabButtons = document.querySelectorAll('[data-dc-tab-btn]');
                var tabPanels = document.querySelectorAll('[data-dc-tab-panel]');
                var activeTabFromPhp = '<?php echo esc_js($active_tab); ?>';
                var editModalEl = document.getElementById('dc-edit-modal');
                var editFormEl = document.getElementById('dc_edit_bundle_form');
                var editableBundleRows = document.querySelectorAll('tr[data-edit-bundle]');
                var selectAllBundlesEl = document.getElementById('dc_bundles_select_all');
                var bulkDeleteFormEl = document.getElementById('dc_bulk_delete_bundles_form');
                var bundleCheckboxEls = document.querySelectorAll('.dc-bundle-checkbox');
                var savedSearchEl = document.getElementById('dc_saved_products_search');
                var savedFamilyFilterEl = document.getElementById('dc_saved_products_filter_family');
                var savedCountryFilterEl = document.getElementById('dc_saved_products_filter_country');
                var savedOperatorFilterEl = document.getElementById('dc_saved_products_filter_operator');
                var savedNoResultsEl = document.getElementById('dc_saved_products_no_results');
                var savedBundleRows = document.querySelectorAll('#dc-tab-saved tr[data-edit-bundle]');
                var initialEditingBundle = <?php echo wp_json_encode($editing_bundle ? $editing_bundle : null); ?>;
                var initialEditingLanding = <?php echo wp_json_encode($editing_landing ? $editing_landing : null); ?>;
                var editIdEl = document.getElementById('dc_edit_bundle_id');
                var editCountryIsoEl = document.getElementById('dc_edit_country_iso');
                var editLabelEl = document.getElementById('dc_edit_label');
                var editSkuEl = document.getElementById('dc_edit_sku_code');
                var editSendValueEl = document.getElementById('dc_edit_send_value');
                var editSendCurrencyEl = document.getElementById('dc_edit_send_currency_iso');
                var editPublicPriceEl = document.getElementById('dc_edit_public_price');
                var editPublicPriceCurrencyEl = document.getElementById('dc_edit_public_price_currency');
                var editPackageFamilyEl = document.getElementById('dc_edit_package_family');
                var editProductTypeRawEl = document.getElementById('dc_edit_product_type_raw');
                var editValidityRawEl = document.getElementById('dc_edit_validity_raw');
                var editProviderEl = document.getElementById('dc_edit_provider_name');
                var editDescriptionEl = document.getElementById('dc_edit_description');
                var editIsActiveEl = document.getElementById('dc_edit_is_active');
                var landingEditModalEl = document.getElementById('dc-edit-landing-modal');
                var editableLandingRows = document.querySelectorAll('tr[data-edit-landing]');
                var landingEditIdEl = document.getElementById('dc_edit_landing_id');
                var landingEditNameEl = document.getElementById('dc_edit_landing_name');
                var landingEditKeyEl = document.getElementById('dc_edit_landing_key');
                var landingEditTitleEl = document.getElementById('dc_edit_landing_title');
                var landingEditSubtitleEl = document.getElementById('dc_edit_landing_subtitle');
                var landingCreateChecklistEl = document.getElementById('dc_landing_bundle_ids');
                var landingEditChecklistEl = document.getElementById('dc_edit_landing_bundle_ids');
                var landingCreateCountryFilterEl = document.getElementById('dc_landing_filter_country');
                var landingCreateFamilyFilterEl = document.getElementById('dc_landing_filter_family');
                var landingEditCountryFilterEl = document.getElementById('dc_edit_landing_filter_country');
                var landingEditFamilyFilterEl = document.getElementById('dc_edit_landing_filter_family');
                var landingCreateBundleCheckboxEls = document.querySelectorAll('.dc-create-landing-bundle-checkbox');
                var landingCreateFeaturedRadioEls = document.querySelectorAll('.dc-create-landing-featured-radio');
                var landingEditBundleCheckboxEls = document.querySelectorAll('#dc_edit_landing_form .dc-edit-landing-bundle-checkbox');
                var landingEditFeaturedRadioEls = document.querySelectorAll('#dc_edit_landing_form .dc-edit-landing-featured-radio');
                var checkBalanceBtn = document.getElementById('dc_check_balance_btn');
                var balanceResultEl = document.getElementById('dc_balance_result');
                var lastBalanceAutoAt = 0;
                var BALANCE_AUTO_REFRESH_MS = 30000;

                function getSelectedBundleCount() {
                    var count = 0;
                    bundleCheckboxEls.forEach(function (checkboxEl) {
                        if (checkboxEl.checked) {
                            count++;
                        }
                    });
                    return count;
                }

                function syncSelectAllState() {
                    if (!selectAllBundlesEl || bundleCheckboxEls.length === 0) {
                        return;
                    }

                    var selectedCount = getSelectedBundleCount();
                    selectAllBundlesEl.checked = selectedCount > 0 && selectedCount === bundleCheckboxEls.length;
                    selectAllBundlesEl.indeterminate = selectedCount > 0 && selectedCount < bundleCheckboxEls.length;
                }

                if (selectAllBundlesEl && bundleCheckboxEls.length > 0) {
                    selectAllBundlesEl.addEventListener('change', function () {
                        bundleCheckboxEls.forEach(function (checkboxEl) {
                            checkboxEl.checked = selectAllBundlesEl.checked;
                        });
                        syncSelectAllState();
                    });

                    bundleCheckboxEls.forEach(function (checkboxEl) {
                        checkboxEl.addEventListener('change', syncSelectAllState);
                    });

                    syncSelectAllState();
                }

                if (bulkDeleteFormEl) {
                    bulkDeleteFormEl.addEventListener('submit', function (event) {
                        var selectedCount = getSelectedBundleCount();
                        if (selectedCount === 0) {
                            event.preventDefault();
                            window.alert('Selecciona al menos un producto para eliminar.');
                            return;
                        }

                        if (!window.confirm('¿Eliminar ' + selectedCount + ' producto(s) seleccionado(s)?')) {
                            event.preventDefault();
                        }
                    });
                }

                function normalizeFilterValue(value) {
                    return String(value || '').trim().toLowerCase();
                }

                function getSavedFamilyLabel(family) {
                    var familyLabels = {
                        topup: 'Top-up',
                        data: 'Data',
                        combo: 'Combo',
                        voucher: 'Voucher',
                        dth: 'DTH',
                        other: 'Otros'
                    };
                    return familyLabels[family] || family || 'Otros';
                }

                function syncSavedFilterOptions() {
                    if (!savedBundleRows.length) {
                        return;
                    }

                    var families = {};
                    var countries = {};
                    var operators = {};

                    savedBundleRows.forEach(function (rowEl) {
                        var family = normalizeFilterValue(rowEl.getAttribute('data-family') || 'other') || 'other';
                        var country = String(rowEl.getAttribute('data-country-iso') || '').trim().toUpperCase();
                        var operator = String(rowEl.getAttribute('data-operator-name') || '').trim();

                        families[family] = true;
                        if (country) {
                            countries[country] = true;
                        }
                        if (operator) {
                            operators[operator] = true;
                        }
                    });

                    if (savedFamilyFilterEl) {
                        var currentFamily = String(savedFamilyFilterEl.value || 'all');
                        savedFamilyFilterEl.innerHTML = '<option value="all">Tipo de producto: Todos</option>';
                        Object.keys(families).sort().forEach(function (family) {
                            var optionEl = document.createElement('option');
                            optionEl.value = family;
                            optionEl.textContent = getSavedFamilyLabel(family);
                            savedFamilyFilterEl.appendChild(optionEl);
                        });
                        savedFamilyFilterEl.value = savedFamilyFilterEl.querySelector('option[value="' + currentFamily + '"]') ? currentFamily : 'all';
                    }

                    if (savedCountryFilterEl) {
                        var currentCountry = String(savedCountryFilterEl.value || 'all');
                        savedCountryFilterEl.innerHTML = '<option value="all">País: Todos</option>';
                        Object.keys(countries).sort().forEach(function (countryIso) {
                            var optionEl = document.createElement('option');
                            optionEl.value = countryIso;
                            optionEl.textContent = countryIso;
                            savedCountryFilterEl.appendChild(optionEl);
                        });
                        savedCountryFilterEl.value = savedCountryFilterEl.querySelector('option[value="' + currentCountry + '"]') ? currentCountry : 'all';
                    }

                    if (savedOperatorFilterEl) {
                        var currentOperator = String(savedOperatorFilterEl.value || 'all');
                        savedOperatorFilterEl.innerHTML = '<option value="all">Operador: Todos</option>';
                        Object.keys(operators).sort(function (left, right) {
                            return left.localeCompare(right);
                        }).forEach(function (operatorName) {
                            var optionEl = document.createElement('option');
                            optionEl.value = operatorName;
                            optionEl.textContent = operatorName;
                            savedOperatorFilterEl.appendChild(optionEl);
                        });
                        savedOperatorFilterEl.value = savedOperatorFilterEl.querySelector('option[value="' + currentOperator + '"]') ? currentOperator : 'all';
                    }
                }

                function applySavedProductsFilters() {
                    if (!savedBundleRows.length) {
                        if (savedNoResultsEl) {
                            savedNoResultsEl.hidden = true;
                        }
                        return;
                    }

                    var searchTerm = normalizeFilterValue(savedSearchEl ? savedSearchEl.value : '');
                    var selectedFamily = normalizeFilterValue(savedFamilyFilterEl ? savedFamilyFilterEl.value : 'all') || 'all';
                    var selectedCountryRaw = String(savedCountryFilterEl ? savedCountryFilterEl.value : 'all').trim();
                    var selectedCountry = selectedCountryRaw.toUpperCase();
                    var selectedOperator = String(savedOperatorFilterEl ? savedOperatorFilterEl.value : 'all').trim();
                    var visibleCount = 0;

                    savedBundleRows.forEach(function (rowEl) {
                        var rowFamily = normalizeFilterValue(rowEl.getAttribute('data-family') || 'other') || 'other';
                        var rowCountry = String(rowEl.getAttribute('data-country-iso') || '').trim().toUpperCase();
                        var rowOperator = String(rowEl.getAttribute('data-operator-name') || '').trim();
                        var rowSearchIndex = normalizeFilterValue(rowEl.getAttribute('data-search-index') || rowEl.textContent || '');

                        var familyMatch = selectedFamily === 'all' || rowFamily === selectedFamily;
                        var countryMatch = selectedCountryRaw === 'all' || rowCountry === selectedCountry;
                        var operatorMatch = selectedOperator === 'all' || rowOperator === selectedOperator;
                        var searchMatch = !searchTerm || rowSearchIndex.indexOf(searchTerm) !== -1;
                        var isVisible = familyMatch && countryMatch && operatorMatch && searchMatch;

                        rowEl.hidden = !isVisible;
                        if (isVisible) {
                            visibleCount++;
                        }
                    });

                    if (savedNoResultsEl) {
                        savedNoResultsEl.hidden = visibleCount !== 0;
                    }
                }

                if (savedBundleRows.length) {
                    syncSavedFilterOptions();
                    applySavedProductsFilters();

                    if (savedSearchEl) {
                        savedSearchEl.addEventListener('input', applySavedProductsFilters);
                    }
                    if (savedFamilyFilterEl) {
                        savedFamilyFilterEl.addEventListener('change', applySavedProductsFilters);
                    }
                    if (savedCountryFilterEl) {
                        savedCountryFilterEl.addEventListener('change', applySavedProductsFilters);
                    }
                    if (savedOperatorFilterEl) {
                        savedOperatorFilterEl.addEventListener('change', applySavedProductsFilters);
                    }
                }

                function escHtml(str) {
                    if (str === null || str === undefined) {
                        return '';
                    }

                    return String(str)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                }

                async function checkBalance() {
                    if (!checkBalanceBtn || !balanceResultEl) {
                        return;
                    }

                    var endpoint = '<?php echo esc_js(rest_url('dingconnect/v1/balance')); ?>';
                    var restNonce = '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>';

                    checkBalanceBtn.disabled = true;
                    checkBalanceBtn.textContent = 'Consultando...';
                    balanceResultEl.classList.add('is-visible');
                    balanceResultEl.innerHTML = '<p class="dc-balance-panel__label">Consultando DingConnect...</p>';

                    try {
                        var response = await fetch(endpoint, {
                            method: 'GET',
                            headers: {
                                'X-WP-Nonce': restNonce,
                            },
                            credentials: 'same-origin',
                        });

                        var data = await response.json();
                        if (!response.ok || !data || !data.ok) {
                            var message = (data && data.message) ? data.message : 'No se pudo consultar el balance.';
                            var dingCode = data && data.error && data.error.ding_error_code ? String(data.error.ding_error_code) : '';
                            var dingContext = data && data.error && data.error.ding_error_context ? String(data.error.ding_error_context) : '';
                            if (dingCode) {
                                message += ' [' + dingCode + (dingContext ? ' / ' + dingContext : '') + ']';
                            }
                            balanceResultEl.innerHTML = '<p class="dc-balance-panel__error">' + escHtml(message) + '</p>';
                            return;
                        }

                        var result = data.result || {};
                        var balance = Number(result.Balance);
                        if (!isFinite(balance)) {
                            balance = 0;
                        }

                        var currencyIso = String(result.CurrencyIso || 'USD');
                        var hasResultCode = result.ResultCode !== undefined && result.ResultCode !== null && String(result.ResultCode) !== '';
                        var resultCode = hasResultCode ? Number(result.ResultCode) : null;
                        var statusClass = balance > 0 ? 'is-ok' : 'is-warn';
                        var statusText = balance > 0 ? 'Saldo disponible' : 'Saldo en cero';

                        if (hasResultCode && resultCode !== 1) {
                            statusClass = 'is-error';
                            statusText = 'Respuesta con incidencia';
                        }

                        var now = new Date();
                        var hh = String(now.getHours()).padStart(2, '0');
                        var mm = String(now.getMinutes()).padStart(2, '0');
                        var ss = String(now.getSeconds()).padStart(2, '0');

                        balanceResultEl.innerHTML = ''
                            + '<div class="dc-balance-panel__top">'
                            + '  <div>'
                            + '    <p class="dc-balance-panel__label">Balance actual del agente</p>'
                            + '    <p class="dc-balance-panel__amount">' + escHtml(balance.toFixed(2)) + ' ' + escHtml(currencyIso) + '</p>'
                            + '  </div>'
                            + '  <span class="dc-balance-panel__status ' + statusClass + '">' + escHtml(statusText) + '</span>'
                            + '</div>'
                            + '<p class="dc-balance-panel__meta">Actualizado: ' + hh + ':' + mm + ':' + ss + ' · Código de resultado: ' + escHtml(hasResultCode ? String(resultCode) : 'N/D') + ' · Forma de respuesta: ' + escHtml(String(result.RawShape || 'unknown')) + '</p>';

                        lastBalanceAutoAt = Date.now();
                    } catch (err) {
                        balanceResultEl.innerHTML = '<p class="dc-balance-panel__error">' + escHtml(err && err.message ? err.message : 'Error inesperado al consultar balance.') + '</p>';
                    } finally {
                        checkBalanceBtn.disabled = false;
                        checkBalanceBtn.textContent = 'Consultar balance ahora';
                    }
                }

                if (checkBalanceBtn) {
                    checkBalanceBtn.addEventListener('click', checkBalance);
                }

                document.addEventListener('dc:tab-activated', function (e) {
                    if (e.detail !== 'tab_setup') {
                        return;
                    }

                    var shouldRefresh = (Date.now() - lastBalanceAutoAt) > BALANCE_AUTO_REFRESH_MS;
                    if (shouldRefresh) {
                        checkBalance();
                    }
                });

                function setActiveTab(tabId, updateUrl) {
                    tabButtons.forEach(function (btn) {
                        if (btn.getAttribute('data-dc-tab-btn') === tabId) {
                            btn.classList.add('nav-tab-active');
                            return;
                        }
                        btn.classList.remove('nav-tab-active');
                    });

                    tabPanels.forEach(function (panel) {
                        if (panel.getAttribute('data-dc-tab-panel') === tabId) {
                            panel.classList.add('is-active');
                            return;
                        }
                        panel.classList.remove('is-active');
                    });

                    if (updateUrl) {
                        var url = new URL(window.location.href);
                        url.searchParams.set('dc_tab', tabId);
                        window.history.replaceState({}, '', url.toString());
                    }

                    document.dispatchEvent(new CustomEvent('dc:tab-activated', { detail: tabId }));
                }

                function updateEditParam(bundleId) {
                    var url = new URL(window.location.href);

                    if (bundleId) {
                        url.searchParams.set('dc_edit_bundle', bundleId);
                        url.searchParams.set('dc_tab', 'tab_saved');
                    } else {
                        url.searchParams.delete('dc_edit_bundle');
                    }

                    window.history.replaceState({}, '', url.toString());
                }

                function populateEditForm(bundle) {
                    if (!bundle || !editFormEl) {
                        return;
                    }

                    if (editIdEl) editIdEl.value = bundle.id || '';
                    if (editCountryIsoEl) editCountryIsoEl.value = bundle.country_iso || '';
                    if (editLabelEl) editLabelEl.value = bundle.label || '';
                    if (editSkuEl) editSkuEl.value = bundle.sku_code || '';
                    if (editSendValueEl) editSendValueEl.value = typeof bundle.send_value !== 'undefined' ? bundle.send_value : 0;
                    if (editSendCurrencyEl) editSendCurrencyEl.value = bundle.send_currency_iso || 'USD';
                    if (editPublicPriceEl) editPublicPriceEl.value = typeof bundle.public_price !== 'undefined' ? bundle.public_price : '';
                    if (editPublicPriceCurrencyEl) editPublicPriceCurrencyEl.value = bundle.public_price_currency || 'EUR';
                    if (editPackageFamilyEl) editPackageFamilyEl.value = bundle.package_family || 'other';
                    if (editProductTypeRawEl) editProductTypeRawEl.value = bundle.product_type_raw || '';
                    if (editValidityRawEl) editValidityRawEl.value = bundle.validity_raw || '';
                    if (editProviderEl) editProviderEl.value = bundle.provider_name || '';
                    if (editDescriptionEl) editDescriptionEl.value = bundle.description || '';
                    if (editIsActiveEl) editIsActiveEl.checked = !!Number(bundle.is_active || 0) || bundle.is_active === true;
                }

                function openEditModal(bundle) {
                    if (!editModalEl || !bundle) {
                        return;
                    }

                    populateEditForm(bundle);
                    editModalEl.hidden = false;
                    document.body.classList.add('modal-open');
                    updateEditParam(bundle.id || '');
                    setActiveTab('tab_saved', true);

                    if (editCountryIsoEl) {
                        editCountryIsoEl.focus();
                    }
                }

                function closeEditModal() {
                    if (!editModalEl) {
                        return;
                    }

                    editModalEl.hidden = true;
                    document.body.classList.remove('modal-open');
                    updateEditParam('');
                }

                function closeLandingEditModal() {
                    if (!landingEditModalEl) {
                        return;
                    }

                    landingEditModalEl.hidden = true;
                    document.body.classList.remove('modal-open');

                    var url = new URL(window.location.href);
                    url.searchParams.delete('dc_edit_landing');
                    window.history.replaceState({}, '', url.toString());
                }

                function shouldIgnoreRowEdit(event) {
                    if (!event || !event.target || !event.target.closest) {
                        return false;
                    }

                    return !!event.target.closest('a,button,input,select,textarea,label');
                }

                function initLandingBundleChecklist(checklistEl, countryFilterEl, familyFilterEl) {
                    if (!checklistEl) {
                        return;
                    }

                    var draggingRowEl = null;
                    var pendingDragRowEl = null;

                    checklistEl.addEventListener('mousedown', function (event) {
                        var handleEl = event.target && event.target.closest ? event.target.closest('.dc-landing-bundles-drag-handle') : null;
                        if (handleEl) {
                            var rowEl = handleEl.closest('.dc-landing-bundles-checklist__item');
                            if (rowEl) {
                                pendingDragRowEl = rowEl;
                                rowEl.setAttribute('draggable', 'true');
                            }
                        } else {
                            pendingDragRowEl = null;
                            getRows().forEach(function (r) { r.setAttribute('draggable', 'false'); });
                        }
                    });

                    function getRows() {
                        return Array.prototype.slice.call(checklistEl.querySelectorAll('.dc-landing-bundles-checklist__item'));
                    }

                    function getOrderInputFromRow() {
                        return null;
                    }

                    function refreshOrderFromDom() {
                        // Order is now determined by DOM position — no order inputs to update.
                    }

                    function reorderRowsByOrderInput() {
                        // No-op: order is maintained by drag-and-drop and reorderBySelectedIds.
                    }

                    function reorderBySelectedIds(selectedIds) {
                        if (!Array.isArray(selectedIds) || selectedIds.length === 0) {
                            return;
                        }

                        var rows = getRows();
                        var orderMap = {};
                        selectedIds.forEach(function (bundleId, index) {
                            orderMap[String(bundleId)] = index;
                        });

                        var selected = [];
                        var unselected = [];

                        rows.forEach(function (rowEl) {
                            var bundleId = String(rowEl.getAttribute('data-bundle-id') || '');
                            if (bundleId in orderMap) {
                                selected.push({ el: rowEl, order: orderMap[bundleId] });
                            } else {
                                unselected.push(rowEl);
                            }
                        });

                        selected.sort(function (a, b) { return a.order - b.order; });

                        selected.forEach(function (item) { checklistEl.appendChild(item.el); });
                        unselected.forEach(function (rowEl) { checklistEl.appendChild(rowEl); });
                    }

                    function getCheckboxFromRow(rowEl) {
                        return rowEl ? rowEl.querySelector('input[type="checkbox"][name="bundle_ids[]"]') : null;
                    }

                    function getFamilyLabel(family) {
                        var labels = {
                            topup: 'Top-up',
                            data: 'Data',
                            combo: 'Combo',
                            voucher: 'Voucher',
                            dth: 'DTH',
                            other: 'Otros'
                        };
                        return labels[family] || family;
                    }

                    function syncSelectedState() {
                        getRows().forEach(function (rowEl) {
                            var checkboxEl = getCheckboxFromRow(rowEl);
                            rowEl.classList.toggle('is-selected', !!(checkboxEl && checkboxEl.checked));
                        });
                    }

                    function applyRowFilters() {
                        var selectedCountry = countryFilterEl ? String(countryFilterEl.value || 'all') : 'all';
                        var selectedFamily = familyFilterEl ? String(familyFilterEl.value || 'all') : 'all';

                        getRows().forEach(function (rowEl) {
                            var checkboxEl = getCheckboxFromRow(rowEl);
                            var isSelected = !!(checkboxEl && checkboxEl.checked);
                            var rowCountry = String(rowEl.getAttribute('data-country-iso') || '').toUpperCase();
                            var rowFamily = String(rowEl.getAttribute('data-package-family') || 'other').toLowerCase();
                            var countryMatch = selectedCountry === 'all' || rowCountry === selectedCountry;
                            var familyMatch = selectedFamily === 'all' || rowFamily === selectedFamily;
                            rowEl.hidden = !(isSelected || (countryMatch && familyMatch));
                        });
                    }

                    function syncFilterOptions() {
                        var countries = {};
                        var families = {};

                        getRows().forEach(function (rowEl) {
                            var rowCountry = String(rowEl.getAttribute('data-country-iso') || '').toUpperCase();
                            var rowFamily = String(rowEl.getAttribute('data-package-family') || 'other').toLowerCase();
                            if (rowCountry) {
                                countries[rowCountry] = true;
                            }
                            families[rowFamily] = true;
                        });

                        if (countryFilterEl) {
                            var currentCountry = String(countryFilterEl.value || 'all');
                            countryFilterEl.innerHTML = '<option value="all">Todos</option>';
                            Object.keys(countries).sort().forEach(function (countryIso) {
                                var optionEl = document.createElement('option');
                                optionEl.value = countryIso;
                                optionEl.textContent = countryIso;
                                countryFilterEl.appendChild(optionEl);
                            });
                            countryFilterEl.value = countryFilterEl.querySelector('option[value="' + currentCountry + '"]') ? currentCountry : 'all';
                        }

                        if (familyFilterEl) {
                            var currentFamily = String(familyFilterEl.value || 'all');
                            familyFilterEl.innerHTML = '<option value="all">Todos</option>';
                            Object.keys(families).sort().forEach(function (family) {
                                var optionEl = document.createElement('option');
                                optionEl.value = family;
                                optionEl.textContent = getFamilyLabel(family);
                                familyFilterEl.appendChild(optionEl);
                            });
                            familyFilterEl.value = familyFilterEl.querySelector('option[value="' + currentFamily + '"]') ? currentFamily : 'all';
                        }
                    }

                    function refreshOrderFromDom() {
                        getRows().forEach(function (rowEl, index) {
                            var orderInputEl = getOrderInputFromRow(rowEl);
                            if (orderInputEl) {
                                orderInputEl.value = String(index + 1);
                            }
                        });
                    }

                    function clearDragOverStyles() {
                        getRows().forEach(function (rowEl) {
                            rowEl.classList.remove('is-drag-over');
                        });
                    }

                    syncSelectedState();
                    syncFilterOptions();
                    applyRowFilters();

                    if (countryFilterEl) {
                        countryFilterEl.addEventListener('change', applyRowFilters);
                    }
                    if (familyFilterEl) {
                        familyFilterEl.addEventListener('change', applyRowFilters);
                    }

                    checklistEl.addEventListener('change', function (event) {
                        var target = event.target;
                        if (!target) return;

                        if (target.matches('input[type="checkbox"][name="bundle_ids[]"]')) {
                            syncSelectedState();
                            applyRowFilters();
                            return;
                        }

                        if (target.matches('input[type="radio"][name="featured_bundle_id"]') && target.checked) {
                            var featuredRowEl = target.closest('.dc-landing-bundles-checklist__item');
                            var featuredCheckboxEl = getCheckboxFromRow(featuredRowEl);
                            if (featuredCheckboxEl && !featuredCheckboxEl.disabled) {
                                featuredCheckboxEl.checked = true;
                            }
                            syncSelectedState();
                            applyRowFilters();
                        }
                    });

                    checklistEl.addEventListener('mousedown', function (event) {
                        var target = event.target;
                        if (!target || !target.matches('input[type="radio"][name="featured_bundle_id"]')) {
                            return;
                        }

                        if (target.checked) {
                            target.dataset.dcToggleOff = '1';
                        } else {
                            delete target.dataset.dcToggleOff;
                        }
                    });

                    checklistEl.addEventListener('click', function (event) {
                        var target = event.target;
                        if (!target || !target.matches('input[type="radio"][name="featured_bundle_id"]')) {
                            return;
                        }

                        if (target.dataset.dcToggleOff === '1') {
                            event.preventDefault();
                            target.checked = false;
                            delete target.dataset.dcToggleOff;
                            syncSelectedState();
                            applyRowFilters();
                        }
                    });

                    checklistEl.addEventListener('dragstart', function (event) {
                        var rowEl = event.target && event.target.closest ? event.target.closest('.dc-landing-bundles-checklist__item') : null;
                        if (!rowEl || rowEl !== pendingDragRowEl) {
                            event.preventDefault();
                            return;
                        }

                        draggingRowEl = rowEl;
                        rowEl.classList.add('is-dragging');

                        if (event.dataTransfer) {
                            event.dataTransfer.effectAllowed = 'move';
                            event.dataTransfer.setData('text/plain', rowEl.getAttribute('data-bundle-id') || '');
                        }
                    });

                    checklistEl.addEventListener('dragover', function (event) {
                        if (!draggingRowEl) {
                            return;
                        }

                        event.preventDefault();

                        var rowEl = event.target && event.target.closest ? event.target.closest('.dc-landing-bundles-checklist__item') : null;
                        if (!rowEl || rowEl === draggingRowEl) {
                            return;
                        }

                        clearDragOverStyles();
                        rowEl.classList.add('is-drag-over');

                        var rect = rowEl.getBoundingClientRect();
                        var shouldInsertAfter = (event.clientY - rect.top) > (rect.height / 2);

                        if (shouldInsertAfter) {
                            checklistEl.insertBefore(draggingRowEl, rowEl.nextSibling);
                        } else {
                            checklistEl.insertBefore(draggingRowEl, rowEl);
                        }
                    });

                    checklistEl.addEventListener('drop', function (event) {
                        if (!draggingRowEl) {
                            return;
                        }

                        event.preventDefault();
                        clearDragOverStyles();
                        refreshOrderFromDom();
                        syncFilterOptions();
                        applyRowFilters();
                    });

                    checklistEl.addEventListener('dragend', function () {
                        if (draggingRowEl) {
                            draggingRowEl.classList.remove('is-dragging');
                            draggingRowEl.setAttribute('draggable', 'false');
                            draggingRowEl = null;
                        }
                        pendingDragRowEl = null;
                        clearDragOverStyles();
                        refreshOrderFromDom();
                        syncFilterOptions();
                        applyRowFilters();
                    });

                    checklistEl.__dcReorderBySelectedIds = reorderBySelectedIds;
                    checklistEl.__dcRefreshOrderFromDom = refreshOrderFromDom;
                    checklistEl.__dcSyncSelectedState = syncSelectedState;
                    checklistEl.__dcApplyFilters = applyRowFilters;
                    checklistEl.__dcSyncFilterOptions = syncFilterOptions;
                }

                initLandingBundleChecklist(landingCreateChecklistEl, landingCreateCountryFilterEl, landingCreateFamilyFilterEl);
                initLandingBundleChecklist(landingEditChecklistEl, landingEditCountryFilterEl, landingEditFamilyFilterEl);

                function openLandingEditModal(landing) {
                    if (!landingEditModalEl || !landing) {
                        return;
                    }

                    if (landingEditIdEl) landingEditIdEl.value = landing.id || '';
                    if (landingEditNameEl) landingEditNameEl.value = landing.name || '';
                    if (landingEditKeyEl) landingEditKeyEl.value = landing.key || '';
                    if (landingEditTitleEl) landingEditTitleEl.value = landing.title || '';
                    if (landingEditSubtitleEl) landingEditSubtitleEl.value = landing.subtitle || '';

                    if (landingEditBundleCheckboxEls.length) {
                        var selectedMap = {};
                        if (Array.isArray(landing.bundle_ids)) {
                            landing.bundle_ids.forEach(function (bundleId) {
                                selectedMap[String(bundleId)] = true;
                            });
                        }

                        landingEditBundleCheckboxEls.forEach(function (checkboxEl) {
                            checkboxEl.checked = !!selectedMap[String(checkboxEl.value)];
                        });

                        if (landingEditFeaturedRadioEls.length) {
                            var featuredId = String(landing.featured_bundle_id || '');
                            landingEditFeaturedRadioEls.forEach(function (radioEl) {
                                radioEl.checked = String(radioEl.value) === featuredId;
                            });
                        }

                        if (landingEditChecklistEl && typeof landingEditChecklistEl.__dcReorderBySelectedIds === 'function') {
                            landingEditChecklistEl.__dcReorderBySelectedIds(Array.isArray(landing.bundle_ids) ? landing.bundle_ids : []);
                        }
                        if (landingEditChecklistEl && typeof landingEditChecklistEl.__dcSyncSelectedState === 'function') {
                            landingEditChecklistEl.__dcSyncSelectedState();
                        }
                        if (landingEditChecklistEl && typeof landingEditChecklistEl.__dcSyncFilterOptions === 'function') {
                            landingEditChecklistEl.__dcSyncFilterOptions();
                        }
                        if (landingEditChecklistEl && typeof landingEditChecklistEl.__dcApplyFilters === 'function') {
                            landingEditChecklistEl.__dcApplyFilters();
                        }
                    }

                    landingEditModalEl.hidden = false;
                    document.body.classList.add('modal-open');
                    setActiveTab('tab_landings', true);

                    var url = new URL(window.location.href);
                    if (landing.id) {
                        url.searchParams.set('dc_edit_landing', landing.id);
                        url.searchParams.set('dc_tab', 'tab_landings');
                        window.history.replaceState({}, '', url.toString());
                    }

                    if (landingEditNameEl) {
                        landingEditNameEl.focus();
                    }
                }

                if (tabButtons.length && tabPanels.length) {
                    tabButtons.forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            setActiveTab(btn.getAttribute('data-dc-tab-btn'), true);
                        });
                    });

                    setActiveTab(activeTabFromPhp || 'tab_setup', false);
                }

                if (editableBundleRows.length) {
                    editableBundleRows.forEach(function (rowEl) {
                        rowEl.addEventListener('click', function (event) {
                            if (shouldIgnoreRowEdit(event)) {
                                return;
                            }

                            try {
                                openEditModal(JSON.parse(rowEl.getAttribute('data-edit-bundle') || '{}'));
                            } catch (e) {
                                window.alert('No se pudo abrir el editor del bundle seleccionado.');
                            }
                        });

                        rowEl.addEventListener('keydown', function (event) {
                            if (event.key !== 'Enter' && event.key !== ' ') {
                                return;
                            }
                            event.preventDefault();

                            try {
                                openEditModal(JSON.parse(rowEl.getAttribute('data-edit-bundle') || '{}'));
                            } catch (e) {
                                window.alert('No se pudo abrir el editor del bundle seleccionado.');
                            }
                        });
                    });
                }

                document.addEventListener('click', function (event) {
                    if (event.target && typeof event.target.closest === 'function' && event.target.closest('[data-dc-edit-close]')) {
                        closeEditModal();
                    }
                });

                if (editableLandingRows.length) {
                    editableLandingRows.forEach(function (rowEl) {
                        rowEl.addEventListener('click', function (event) {
                            if (shouldIgnoreRowEdit(event)) {
                                return;
                            }

                            try {
                                openLandingEditModal(JSON.parse(rowEl.getAttribute('data-edit-landing') || '{}'));
                            } catch (e) {
                                window.alert('No se pudo abrir el editor del shortcode seleccionado.');
                            }
                        });

                        rowEl.addEventListener('keydown', function (event) {
                            if (event.key !== 'Enter' && event.key !== ' ') {
                                return;
                            }
                            event.preventDefault();

                            try {
                                openLandingEditModal(JSON.parse(rowEl.getAttribute('data-edit-landing') || '{}'));
                            } catch (e) {
                                window.alert('No se pudo abrir el editor del shortcode seleccionado.');
                            }
                        });
                    });
                }

                var shortcodeCopyButtons = document.querySelectorAll('.dc-shortcode-copy-trigger');

                function copyTextToClipboard(text) {
                    if (!text) {
                        return Promise.reject(new Error('Texto vacio'));
                    }

                    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                        return navigator.clipboard.writeText(text);
                    }

                    return new Promise(function (resolve, reject) {
                        var tempTextarea = document.createElement('textarea');
                        tempTextarea.value = text;
                        tempTextarea.setAttribute('readonly', 'readonly');
                        tempTextarea.style.position = 'fixed';
                        tempTextarea.style.left = '-9999px';
                        document.body.appendChild(tempTextarea);
                        tempTextarea.select();

                        try {
                            var success = document.execCommand('copy');
                            document.body.removeChild(tempTextarea);
                            if (success) {
                                resolve();
                            } else {
                                reject(new Error('No se pudo copiar'));
                            }
                        } catch (err) {
                            document.body.removeChild(tempTextarea);
                            reject(err);
                        }
                    });
                }

                if (shortcodeCopyButtons.length) {
                    shortcodeCopyButtons.forEach(function (btn) {
                        btn.addEventListener('click', function (event) {
                            event.preventDefault();
                            event.stopPropagation();

                            var text = btn.getAttribute('data-copy-text') || '';
                            copyTextToClipboard(text).then(function () {
                                btn.classList.add('is-copied');
                                setTimeout(function () {
                                    btn.classList.remove('is-copied');
                                }, 1200);
                            }).catch(function () {
                                window.alert('No se pudo copiar el shortcode al portapapeles.');
                            });
                        });
                    });
                }

                document.addEventListener('click', function (event) {
                    if (event.target && typeof event.target.closest === 'function' && event.target.closest('[data-dc-landing-edit-close]')) {
                        closeLandingEditModal();
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && editModalEl && !editModalEl.hidden) {
                        closeEditModal();
                    }

                    if (event.key === 'Escape' && landingEditModalEl && !landingEditModalEl.hidden) {
                        closeLandingEditModal();
                    }
                });

                if (initialEditingBundle && initialEditingBundle.id) {
                    openEditModal(initialEditingBundle);
                }

                if (initialEditingLanding && initialEditingLanding.id) {
                    openLandingEditModal(initialEditingLanding);
                }

                var countryIsoEl = document.getElementById('dc_country_iso');
                var labelEl = document.getElementById('dc_label');
                var skuEl = document.getElementById('dc_sku_code');
                var sendValueEl = document.getElementById('dc_send_value');
                var sendCurrencyEl = document.getElementById('dc_send_currency_iso');
                var publicPriceEl = document.getElementById('dc_public_price');
                var publicPriceCurrencyEl = document.getElementById('dc_public_price_currency');
                var packageFamilyEl = document.getElementById('dc_package_family');
                var productTypeRawEl = document.getElementById('dc_product_type_raw');
                var validityRawEl = document.getElementById('dc_validity_raw');
                var providerEl = document.getElementById('dc_provider_name');
                var descriptionEl = document.getElementById('dc_description');

                function fillForm(item) {
                    if (countryIsoEl) countryIsoEl.value = item.country_iso || '';
                    if (labelEl) labelEl.value = (item.operator || 'Producto') + ' - ' + (item.receive || item.sku_code || '');
                    if (skuEl) skuEl.value = item.sku_code || '';
                    if (sendValueEl) sendValueEl.value = item.send_value != null ? item.send_value : '';
                    if (sendCurrencyEl) sendCurrencyEl.value = item.send_currency_iso || 'EUR';
                    if (publicPriceEl) publicPriceEl.value = '';
                    if (publicPriceCurrencyEl) publicPriceCurrencyEl.value = 'EUR';
                    if (packageFamilyEl) packageFamilyEl.value = item.package_group || 'other';
                    if (productTypeRawEl) productTypeRawEl.value = item.product_type || '';
                    if (validityRawEl) validityRawEl.value = item.validity || '';
                    if (providerEl) providerEl.value = item.operator || '';
                    if (descriptionEl) descriptionEl.value = item.receive || '';
                }

                var datalistMap = {
                    dc_dl_country_iso: ['dc_country_iso', 'dc_edit_country_iso'],
                    dc_dl_label: ['dc_label', 'dc_edit_label'],
                    dc_dl_send_currency: ['dc_send_currency_iso', 'dc_edit_send_currency_iso'],
                    dc_dl_public_currency: ['dc_public_price_currency', 'dc_edit_public_price_currency'],
                    dc_dl_provider_name: ['dc_provider_name', 'dc_edit_provider_name']
                };
                var comboboxRegistry = [];

                function addToDatalist(datalistId, value) {
                    var dl = document.getElementById(datalistId);
                    var rawValue = String(value || '').trim();
                    if (!dl || !rawValue) {
                        return;
                    }

                    var normalizedValue = rawValue;
                    if (datalistId === 'dc_dl_country_iso') {
                        normalizedValue = rawValue.toUpperCase();
                    }
                    if (datalistId === 'dc_dl_send_currency') {
                        normalizedValue = rawValue.toUpperCase();
                    }

                    var existing = false;
                    dl.querySelectorAll('option').forEach(function (opt) {
                        if (String(opt.value || '').toLowerCase() === normalizedValue.toLowerCase()) {
                            existing = true;
                        }
                    });

                    if (existing) {
                        return;
                    }

                    var option = document.createElement('option');
                    option.value = normalizedValue;
                    dl.appendChild(option);
                }

                function getOptions(datalistId) {
                    var dl = document.getElementById(datalistId);
                    if (!dl) {
                        return [];
                    }

                    var options = [];
                    var seen = {};
                    dl.querySelectorAll('option').forEach(function (opt) {
                        var v = (opt.value || '').trim();
                        var label = (opt.getAttribute('label') || opt.textContent || v).trim();
                        if (!v) {
                            return;
                        }
                        var key = v.toLowerCase();
                        if (!seen[key]) {
                            seen[key] = true;
                            options.push({
                                value: v,
                                label: label || v,
                                searchText: ((label || v) + ' ' + v).toLowerCase()
                            });
                        }
                    });

                    return options;
                }

                function openCombo(combo) {
                    combo.wrap.classList.add('is-open');
                    renderMenu(combo, combo.input.value || '');
                }

                function closeCombo(combo) {
                    combo.wrap.classList.remove('is-open');
                }

                function closeAllCombos(except) {
                    comboboxRegistry.forEach(function (combo) {
                        if (combo !== except) {
                            closeCombo(combo);
                        }
                    });
                }

                function renderMenu(combo, filterValue) {
                    var term = (filterValue || '').trim().toLowerCase();
                    var all = getOptions(combo.datalistId);
                    var filtered = all.filter(function (item) {
                        return !term || item.searchText.indexOf(term) !== -1;
                    });

                    combo.menu.innerHTML = '';
                    if (!filtered.length) {
                        combo.wrap.classList.remove('is-open');
                        return;
                    }

                    filtered.forEach(function (option) {
                        var item = document.createElement('div');
                        item.className = 'dc-combo-option';
                        item.textContent = option.label;
                        item.addEventListener('mousedown', function (ev) {
                            ev.preventDefault();
                            combo.input.value = option.value;
                            combo.input.dispatchEvent(new Event('change', { bubbles: true }));
                            closeCombo(combo);
                        });
                        combo.menu.appendChild(item);
                    });
                }

                function initCombobox(inputId, datalistId) {
                    var input = document.getElementById(inputId);
                    if (!input) {
                        return;
                    }

                    // Disable native datalist/autocomplete popup to avoid double dropdowns.
                    input.removeAttribute('list');
                    input.setAttribute('autocomplete', 'off');

                    if (input.closest('.dc-combo-wrap')) {
                        return;
                    }

                    var wrap = document.createElement('div');
                    wrap.className = 'dc-combo-wrap';
                    if (input.classList.contains('small-text')) {
                        wrap.classList.add('dc-combo-wrap--small');
                    }

                    var menu = document.createElement('div');
                    menu.className = 'dc-combo-menu';

                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'dc-combo-toggle';
                    btn.setAttribute('aria-label', 'Mostrar opciones');
                    btn.innerHTML = '&#9662;';

                    input.parentNode.insertBefore(wrap, input);
                    wrap.appendChild(input);
                    wrap.appendChild(btn);
                    wrap.appendChild(menu);

                    var combo = {
                        input: input,
                        menu: menu,
                        wrap: wrap,
                        datalistId: datalistId
                    };
                    comboboxRegistry.push(combo);

                    input.addEventListener('focus', function () {
                        closeAllCombos(combo);
                        openCombo(combo);
                    });

                    input.addEventListener('click', function () {
                        closeAllCombos(combo);
                        openCombo(combo);
                    });

                    input.addEventListener('input', function () {
                        closeAllCombos(combo);
                        openCombo(combo);
                    });

                    btn.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        if (wrap.classList.contains('is-open')) {
                            closeCombo(combo);
                        } else {
                            closeAllCombos(combo);
                            openCombo(combo);
                            input.focus();
                        }
                    });
                }

                Object.keys(datalistMap).forEach(function (dlId) {
                    datalistMap[dlId].forEach(function (inputId) {
                        var el = document.getElementById(inputId);
                        if (el) {
                            initCombobox(inputId, dlId);

                            el.addEventListener('change', function () {
                                addToDatalist(dlId, el.value);
                            });
                        }
                    });
                });

                document.addEventListener('click', function (ev) {
                    var insideAnyCombo = ev.target.closest('.dc-combo-wrap');
                    if (!insideAnyCombo) {
                        closeAllCombos(null);
                    }
                });
            })();

            // -- Modal de Personalización de Shortcodes ----
            (function () {
                var customizeModal = document.getElementById('dc-customize-shortcode-modal');
                var customizeButtons = document.querySelectorAll('.dc-customize-shortcode-btn');
                var maxWidthInput = document.getElementById('dc_customize_max_width');
                var bgColorInput = document.getElementById('dc_customize_bg_color');
                var primaryColorInput = document.getElementById('dc_customize_primary_color');
                var textColorInput = document.getElementById('dc_customize_text_color');
                var borderRadiusInput = document.getElementById('dc_customize_border_radius');
                var paddingInput = document.getElementById('dc_customize_padding');
                var shadowIntensitySelect = document.getElementById('dc_customize_shadow_intensity');
                var preview = document.getElementById('dc_customize_preview');
                var customizeStatus = document.getElementById('dc_customize_status');
                var customizeInputs = [maxWidthInput, bgColorInput, primaryColorInput, textColorInput, borderRadiusInput, paddingInput, shadowIntensitySelect];
                var currentShortcodeKey = '';

                function closeCustomizeModal() {
                    if (customizeModal) {
                        customizeModal.hidden = true;
                        document.body.classList.remove('modal-open');
                    }
                }

                function updatePreview() {
                    var maxWidth = parseInt(maxWidthInput.value) || 480;
                    var bgColor = bgColorInput.value || '#ffffff';
                    var primaryColor = primaryColorInput.value || '#2563eb';
                    var textColor = textColorInput.value || '#0f172a';
                    var borderRadius = parseInt(borderRadiusInput.value) || 16;
                    var padding = parseInt(paddingInput.value) || 24;
                    var shadowIntensity = shadowIntensitySelect.value || 'light';

                    var shadowMap = {
                        'none': 'none',
                        'light': '0 1px 3px rgba(0, 0, 0, 0.06), 0 8px 24px rgba(0, 0, 0, 0.06)',
                        'medium': '0 4px 6px rgba(0, 0, 0, 0.1), 0 10px 40px rgba(0, 0, 0, 0.12)',
                        'heavy': '0 10px 25px rgba(0, 0, 0, 0.15), 0 15px 50px rgba(0, 0, 0, 0.2)'
                    };

                    var shadow = shadowMap[shadowIntensity] || shadowMap['light'];

                    var previewHTML = '<div class="dc-preview-content" style="'
                        + 'background: ' + bgColor + '; '
                        + 'border-radius: ' + borderRadius + 'px; '
                        + 'padding: ' + padding + 'px; '
                        + 'box-shadow: ' + shadow + '; '
                        + 'color: ' + textColor + '; '
                        + 'max-width: ' + maxWidth + 'px; '
                        + 'margin: 0 auto;'
                        + '">'
                        + '<h2 style="margin: 0 0 8px; color: ' + textColor + '; font-size: 14px; font-weight: 700;">Recargas</h2>'
                        + '<p style="margin: 0 0 10px; color: ' + textColor + '; opacity: 0.8; font-size: 11px;">Elige tu paquete</p>'
                        + '<input type="text" placeholder="Teléfono..." class="dc-preview-input" style="border-color: ' + primaryColor + '22; color: ' + textColor + '; margin-bottom: 8px; font-size: 11px;">'
                        + '<button class="dc-preview-button" style="background: ' + primaryColor + '; color: white; width: 100%; margin-bottom: 4px; font-size: 11px;">Buscar</button>'
                        + '<button class="dc-preview-button" style="background: white; color: ' + primaryColor + '; border: 2px solid ' + primaryColor + '; width: 100%; font-size: 11px;">Confirmar</button>'
                        + '</div>';

                    preview.innerHTML = previewHTML;
                }

                function saveCustomization() {
                    var customization = {
                        max_width: parseInt(maxWidthInput.value) || 480,
                        bg_color: bgColorInput.value || '#ffffff',
                        primary_color: primaryColorInput.value || '#2563eb',
                        text_color: textColorInput.value || '#0f172a',
                        border_radius: parseInt(borderRadiusInput.value) || 16,
                        padding: parseInt(paddingInput.value) || 24,
                        shadow_intensity: shadowIntensitySelect.value || 'light'
                    };

                    if (customizeStatus) {
                        customizeStatus.textContent = 'Guardando...';
                    }

                    wp.apiFetch({
                        path: '/dc-recargas/v1/save-shortcode-customization',
                        method: 'POST',
                        data: {
                            shortcode_key: currentShortcodeKey,
                            customization: customization
                        }
                    }).then(function (response) {
                        if (customizeStatus) {
                            customizeStatus.textContent = '✓ Guardado';
                            setTimeout(function () {
                                customizeStatus.textContent = '';
                            }, 2000);
                        }
                    }).catch(function (error) {
                        if (customizeStatus) {
                            customizeStatus.textContent = '✗ Error al guardar';
                        }
                        console.error('Error guardando personalización:', error);
                    });
                }

                customizeButtons.forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var key = btn.getAttribute('data-shortcode-key');
                        currentShortcodeKey = key;

                        // Reset status
                        if (customizeStatus) {
                            customizeStatus.textContent = '';
                        }

                        // Load saved customization if exists
                        wp.apiFetch({
                            path: '/dc-recargas/v1/get-shortcode-customization?key=' + key,
                            method: 'GET'
                        }).then(function (response) {
                            if (response && response.customization) {
                                var custom = response.customization;
                                if (maxWidthInput) maxWidthInput.value = custom.max_width || 480;
                                if (bgColorInput) bgColorInput.value = custom.bg_color || '#ffffff';
                                if (primaryColorInput) primaryColorInput.value = custom.primary_color || '#2563eb';
                                if (textColorInput) textColorInput.value = custom.text_color || '#0f172a';
                                if (borderRadiusInput) borderRadiusInput.value = custom.border_radius || 16;
                                if (paddingInput) paddingInput.value = custom.padding || 24;
                                if (shadowIntensitySelect) shadowIntensitySelect.value = custom.shadow_intensity || 'light';
                            }

                            updatePreview();
                            customizeModal.hidden = false;
                            document.body.classList.add('modal-open');
                        }).catch(function (error) {
                            console.warn('No saved customization found, using defaults', error);
                            updatePreview();
                            customizeModal.hidden = false;
                            document.body.classList.add('modal-open');
                        });
                    });
                });

                document.addEventListener('click', function (event) {
                    if (event.target && typeof event.target.closest === 'function' && event.target.closest('[data-dc-customize-close]')) {
                        closeCustomizeModal();
                    }
                });

                customizeInputs.forEach(function (input) {
                    if (input) {
                        input.addEventListener('change', function () {
                            updatePreview();
                            saveCustomization();
                        });
                        input.addEventListener('input', function () {
                            updatePreview();
                        });
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && customizeModal && !customizeModal.hidden) {
                        closeCustomizeModal();
                    }
                });
            })();

        </script>
        <?php
    }

    private function render_notice($msg) {
        $count = isset($_GET['dc_count']) ? (int) $_GET['dc_count'] : 0;
        $state = sanitize_text_field($_GET['dc_state'] ?? '');
        $toggle_text = $state === 'active' ? 'Producto activado correctamente.' : 'Producto desactivado correctamente.';
        $map = [
            'bundle_added' => ['success', 'Producto agregado correctamente.'],
            'bundle_updated' => ['success', 'Producto actualizado correctamente.'],
            'bundle_toggled' => ['success', $toggle_text],
            'bundle_deleted' => ['success', 'Producto eliminado correctamente.'],
            'bundle_bulk_deleted' => ['success', sprintf('Productos eliminados correctamente: %d.', $count)],
            'bundle_bulk_empty' => ['error', 'Selecciona al menos un producto para eliminar.'],
            'landing_shortcode_added' => ['success', 'Shortcode dinámico de landing creado correctamente.'],
            'landing_shortcode_updated' => ['success', 'Shortcode dinámico actualizado correctamente.'],
            'landing_shortcode_cloned' => ['success', 'Landing duplicada correctamente.'],
            'landing_shortcode_deleted' => ['success', 'Shortcode dinámico eliminado correctamente.'],
            'landing_shortcode_error' => ['error', 'Completa nombre y selecciona al menos un bundle válido para crear el shortcode dinámico.'],
            'bundle_updated' => ['success', 'Producto actualizado correctamente.'],
            'bundle_error' => ['error', 'Completa País ISO, Nombre y SKU para añadir un producto.'],
            'bundle_duplicate' => ['error', 'Ya existe otro producto con el mismo país y SKU.'],
            'bundle_not_found' => ['error', 'No se encontró el producto solicitado.'],
        ];

        if (!isset($map[$msg])) {
            return;
        }

        [$class, $text] = $map[$msg];
        echo '<div class="notice notice-' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($text) . '</p></div>';
    }

    private function generate_unique_landing_key($base_key, $existing_shortcodes) {
        $base_key = sanitize_key((string) $base_key);
        if ($base_key === '') {
            $base_key = 'landing';
        }

        $used = [];
        foreach ((array) $existing_shortcodes as $item) {
            if (!is_array($item)) {
                continue;
            }
            $item_key = sanitize_key((string) ($item['key'] ?? ''));
            if ($item_key !== '') {
                $used[$item_key] = true;
            }
        }

        if (!isset($used[$base_key])) {
            return $base_key;
        }

        $i = 2;
        while (isset($used[$base_key . '-' . $i])) {
            $i++;
        }

        return $base_key . '-' . $i;
    }

    private function order_selected_bundles($selected_bundle_ids, $raw_bundle_order) {
        $selected_bundle_ids = array_values(array_unique(array_filter(array_map('sanitize_text_field', (array) $selected_bundle_ids))));
        if (empty($selected_bundle_ids)) {
            return [];
        }

        $order_map = [];
        if (is_array($raw_bundle_order)) {
            foreach ($raw_bundle_order as $bundle_id => $order_value) {
                $bundle_id = sanitize_text_field((string) $bundle_id);
                $order_value = (int) $order_value;
                if ($bundle_id === '' || $order_value <= 0) {
                    continue;
                }

                $order_map[$bundle_id] = $order_value;
            }
        }

        $sortable = [];
        foreach ($selected_bundle_ids as $idx => $bundle_id) {
            $sortable[] = [
                'id' => $bundle_id,
                'position' => isset($order_map[$bundle_id]) ? $order_map[$bundle_id] : (100000 + $idx),
                'fallback' => $idx,
            ];
        }

        usort($sortable, function ($left, $right) {
            $position_compare = (int) ($left['position'] ?? 0) <=> (int) ($right['position'] ?? 0);
            if (0 !== $position_compare) {
                return $position_compare;
            }

            return (int) ($left['fallback'] ?? 0) <=> (int) ($right['fallback'] ?? 0);
        });

        return array_values(array_map(function ($item) {
            return sanitize_text_field((string) ($item['id'] ?? ''));
        }, $sortable));
    }

    private function get_landing_country_choices($bundles = null, $landings = null) {
        $bundles = is_array($bundles) ? $bundles : get_option('dc_recargas_bundles', []);
        $landings = is_array($landings) ? $landings : get_option('dc_recargas_landing_shortcodes', []);
        $reference_map = class_exists('DC_Recargas_Frontend') ? DC_Recargas_Frontend::get_country_reference_map() : [];
        $choices = [];

        $add_choice = function ($country_iso, $fallback_name = '') use (&$choices, $reference_map) {
            $country_iso = strtoupper(sanitize_text_field((string) $country_iso));
            if ($country_iso === '') {
                return;
            }

            $name = '';
            $dial = '';
            if (isset($reference_map[$country_iso])) {
                $name = sanitize_text_field((string) ($reference_map[$country_iso]['name'] ?? ''));
                $dial = sanitize_text_field((string) ($reference_map[$country_iso]['dial'] ?? ''));
            } elseif ($fallback_name !== '') {
                $name = sanitize_text_field((string) $fallback_name);
            }

            $choices[$country_iso] = [
                'iso' => $country_iso,
                'name' => $name,
                'dial' => $dial,
                'label' => $name !== '' ? sprintf('%s (%s)', $name, $country_iso) : $country_iso,
            ];
        };

        foreach ((array) $bundles as $bundle) {
            if (!is_array($bundle)) {
                continue;
            }

            $add_choice($bundle['country_iso'] ?? '');
        }

        foreach ((array) $landings as $landing) {
            if (!is_array($landing)) {
                continue;
            }

            $add_choice($landing['country_iso'] ?? '');
        }

        uasort($choices, function ($left, $right) {
            return strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return array_values($choices);
    }

    private function extract_first_number($text) {
        $text = (string) $text;
        if (preg_match('/([0-9]+(?:[\.,][0-9]+)?)/', $text, $matches)) {
            return (float) str_replace(',', '.', $matches[1]);
        }

        return 0.0;
    }

    private function extract_currency_from_send_amount($text) {
        $text = strtoupper((string) $text);
        if (preg_match('/\b([A-Z]{3})\b/', $text, $matches)) {
            return $matches[1];
        }

        return 'EUR';
    }

    private function country_name_to_iso($country_name) {
        $map = [
            'COLOMBIA' => 'CO',
            'SPAIN' => 'ES',
            'MEXICO' => 'MX',
            'CUBA' => 'CU',
            'ARGENTINA' => 'AR',
            'PERU' => 'PE',
            'BRAZIL' => 'BR',
            'VENEZUELA' => 'VE',
            'DOMINICAN REPUBLIC' => 'DO',
        ];

        $key = strtoupper(trim((string) $country_name));
        return $map[$key] ?? '';
    }

    private function bundle_exists_by_country_sku($country_iso, $sku_code, $exclude_bundle_id = '') {
        $country_iso = strtoupper((string) $country_iso);
        $sku_code = strtoupper((string) $sku_code);
        $exclude_bundle_id = (string) $exclude_bundle_id;
        $bundles = get_option('dc_recargas_bundles', []);
        if (!is_array($bundles)) {
            $bundles = [];
        }

        foreach ($bundles as $bundle) {
            $bundle_id = (string) ($bundle['id'] ?? '');
            if (!empty($exclude_bundle_id) && $bundle_id === $exclude_bundle_id) {
                continue;
            }

            $bundle_country = strtoupper((string) ($bundle['country_iso'] ?? ''));
            $bundle_sku = strtoupper((string) ($bundle['sku_code'] ?? ''));
            if ($bundle_country === $country_iso && $bundle_sku === $sku_code) {
                return true;
            }
        }

        return false;
    }

    private function find_bundle_index_by_id($bundles, $id) {
        $id = (string) $id;
        foreach ($bundles as $index => $bundle) {
            if ((string) ($bundle['id'] ?? '') === $id) {
                return (int) $index;
            }
        }

        return -1;
    }

    private function find_bundle_by_id($bundles, $id) {
        $index = $this->find_bundle_index_by_id($bundles, $id);
        if ($index === -1) {
            return null;
        }

        return is_array($bundles[$index]) ? $bundles[$index] : null;
    }

    private function find_landing_by_id($landings, $id) {
        $id = (string) $id;
        foreach ((array) $landings as $landing) {
            if (!is_array($landing)) {
                continue;
            }

            if ((string) ($landing['id'] ?? '') === $id) {
                return $landing;
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Logs tab — AJAX handlers
    // -------------------------------------------------------------------------

    private function mask_phone_for_admin($phone) {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return '';
        }

        $len = strlen($digits);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', max(0, $len - 4)) . substr($digits, -4);
    }

    private function get_submitted_monitor_rows($limit = 80) {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_orders')) {
            return [];
        }

        $order_statuses = [];
        if (function_exists('wc_get_order_statuses')) {
            $order_statuses = array_keys((array) call_user_func('wc_get_order_statuses'));
        }

        $orders = call_user_func('wc_get_orders', [
            'limit' => max(10, min(200, (int) $limit)),
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
            'status' => $order_statuses,
        ]);

        $rows = [];
        $pending_statuses = ['submitted', 'pending', 'processing', 'queued', 'inprogress', 'pending_retry', 'escalado_soporte'];

        foreach ($orders as $order) {
            if (!$order instanceof WC_Order) {
                continue;
            }

            foreach ($order->get_items() as $item) {
                if (!$item instanceof WC_Order_Item_Product || $item->get_meta('_dc_recarga') !== 'yes') {
                    continue;
                }

                $status = strtolower((string) $item->get_meta('_dc_transfer_status'));
                if (!in_array($status, $pending_statuses, true)) {
                    continue;
                }

                $submitted_since = (string) $item->get_meta('_dc_submitted_since');
                $submitted_ts = $submitted_since !== '' ? strtotime($submitted_since) : false;
                $age_minutes = $submitted_ts ? max(0, (int) floor((time() - $submitted_ts) / 60)) : 0;

                $rows[] = [
                    'order_id' => (int) $order->get_id(),
                    'order_url' => admin_url('post.php?post=' . (int) $order->get_id() . '&action=edit'),
                    'phone' => $this->mask_phone_for_admin((string) $item->get_meta('_dc_account_number')),
                    'sku_code' => (string) $item->get_meta('_dc_sku_code'),
                    'provider' => (string) $item->get_meta('_dc_provider_name'),
                    'status' => $status !== '' ? $status : 'pending',
                    'transfer_attempts' => (int) $item->get_meta('_dc_retry_attempts'),
                    'submitted_attempts' => (int) $item->get_meta('_dc_submitted_retry_attempts'),
                    'next_retry_at' => (string) $item->get_meta('_dc_next_retry_at'),
                    'submitted_since' => $submitted_since,
                    'age_minutes' => $age_minutes,
                ];
            }
        }

        usort($rows, function ($left, $right) {
            return (int) ($right['age_minutes'] ?? 0) <=> (int) ($left['age_minutes'] ?? 0);
        });

        return $rows;
    }

    /**
     * Returns aggregate counts per status for the stats summary cards.
     *
     * @return array{total:int, success:int, error:int, validate:int}
     */
    private function get_transfer_log_stats(): array {
        $counts = [
            'total'    => 0,
            'success'  => 0,
            'error'    => 0,
            'validate' => 0,
        ];

        $raw = wp_count_posts('dc_transfer_log');
        $counts['total'] = (int) ($raw->publish ?? 0);

        // Aggregate by status meta. Running three small queries is fast enough
        // for the number of log entries expected in this plugin.
        foreach (['TransferSuccessful' => 'success', 'error' => 'error', 'validate' => 'validate'] as $status => $key) {
            $q = new WP_Query([
                'post_type'      => 'dc_transfer_log',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => false,
                'meta_query'     => [
                    [
                        'key'     => '_dc_status',
                        'value'   => $status,
                        'compare' => '=',
                    ],
                ],
            ]);
            $counts[$key] = (int) $q->found_posts;
        }

        return $counts;
    }

    /**
     * AJAX handler: wp_ajax_dc_get_transfer_logs
     * Accepts GET params: nonce, search, status, date_from, date_to, paged.
     * Returns JSON with paginated log items and metadata.
     */
    public function ajax_get_transfer_logs(): void {
        check_ajax_referer('dc_get_logs', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sin permiso.'], 403);
        }

        $per_page = 20;
        $paged    = max(1, (int) ($_GET['paged'] ?? 1));
        $search   = sanitize_text_field($_GET['search'] ?? '');
        $status   = sanitize_text_field($_GET['status'] ?? '');
        $date_from = sanitize_text_field($_GET['date_from'] ?? '');
        $date_to   = sanitize_text_field($_GET['date_to'] ?? '');

        $args = [
            'post_type'      => 'dc_transfer_log',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        // Status filter.
        $status_clause = [];
        if ($status !== '') {
            $status_clause = [
                'key'     => '_dc_status',
                'value'   => $status,
                'compare' => '=',
            ];
        }

        // Search: match against account_number, sku_code, distributor_ref, or transfer_ref.
        // Wrapped in a nested OR clause so the status filter (AND) still applies.
        if ($search !== '') {
            $search_clause = [
                'relation' => 'OR',
            ];
            foreach (['_dc_account_number', '_dc_sku_code', '_dc_distributor_ref', '_dc_transfer_ref'] as $meta_key) {
                $search_clause[] = [
                    'key'     => $meta_key,
                    'value'   => $search,
                    'compare' => 'LIKE',
                ];
            }

            if ($status_clause) {
                $args['meta_query'] = [
                    'relation' => 'AND',
                    $status_clause,
                    $search_clause,
                ];
            } else {
                $args['meta_query'] = $search_clause;
            }
        } elseif ($status_clause) {
            $args['meta_query'] = [$status_clause];
        }

        // Date range filter.
        if ($date_from !== '' || $date_to !== '') {
            $date_query = ['inclusive' => true];
            if ($date_from !== '') {
                $date_query['after'] = $date_from . ' 00:00:00';
            }
            if ($date_to !== '') {
                $date_query['before'] = $date_to . ' 23:59:59';
            }
            $args['date_query'] = [$date_query];
        }

        $query = new WP_Query($args);
        $logs  = [];

        foreach ($query->posts as $post) {
            $raw = get_post_meta($post->ID, '_dc_raw_response', true);
            // Pretty-print the stored JSON for readability in the UI.
            $decoded = json_decode($raw, true);
            $pretty  = $decoded !== null ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : (string) $raw;

            $logs[] = [
                'id'              => $post->ID,
                'date'            => get_the_date('d/m/Y H:i:s', $post),
                'account_number'  => (string) get_post_meta($post->ID, '_dc_account_number', true),
                'sku_code'        => (string) get_post_meta($post->ID, '_dc_sku_code', true),
                'send_value'      => (string) get_post_meta($post->ID, '_dc_send_value', true),
                'currency'        => (string) get_post_meta($post->ID, '_dc_currency', true),
                'distributor_ref' => (string) get_post_meta($post->ID, '_dc_distributor_ref', true),
                'transfer_ref'    => (string) get_post_meta($post->ID, '_dc_transfer_ref', true),
                'status'          => (string) get_post_meta($post->ID, '_dc_status', true),
                'raw_response'    => $pretty,
            ];
        }

        wp_send_json_success([
            'logs'        => $logs,
            'total'       => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
            'page'        => $paged,
        ]);
    }

    /**
     * admin-post handler: admin_post_dc_clear_logs
     * Permanently deletes all dc_transfer_log posts.
     */
    public function handle_clear_logs(): void {
        check_admin_referer('dc_clear_logs');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sin permiso.', 'dingconnect-recargas'), 403);
        }

        $all = get_posts([
            'post_type'      => 'dc_transfer_log',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        foreach ($all as $post_id) {
            wp_delete_post((int) $post_id, true);
        }

        wp_redirect(add_query_arg([
            'page'   => 'dingconnect-recargas',
            'dc_tab' => 'tab_logs',
        ], admin_url('admin.php')));
        exit;
    }
}
