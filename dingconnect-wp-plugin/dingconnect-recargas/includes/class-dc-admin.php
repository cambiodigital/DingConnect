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
        $valid_bundle_ids = [];
        foreach ($bundles as $bundle) {
            $bundle_id = sanitize_text_field((string) ($bundle['id'] ?? ''));
            if ($bundle_id !== '') {
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
            'created_at' => current_time('mysql'),
            'cloned_from' => sanitize_text_field((string) ($source['id'] ?? '')),
        ];

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
        $valid_bundle_ids = [];
        foreach ($bundles as $bundle) {
            $bundle_id = sanitize_text_field((string) ($bundle['id'] ?? ''));
            if ($bundle_id !== '') {
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
        $shortcodes[$landing_index]['updated_at'] = current_time('mysql');

        update_option('dc_recargas_landing_shortcodes', array_values($shortcodes));

        wp_safe_redirect(add_query_arg([
            'page' => 'dc-recargas',
            'dc_msg' => 'landing_shortcode_updated',
        ], admin_url('admin.php')));
        exit;
    }

    public function sanitize_options($input) {
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

        return [
            'api_base' => esc_url_raw(trim((string) ($input['api_base'] ?? 'https://www.dingconnect.com/api/V1'))),
            'api_key' => sanitize_text_field((string) ($input['api_key'] ?? '')),
            'payment_mode' => $mode,
            'woo_allowed_gateways' => $woo_allowed_gateways,
            'recharge_mode' => $recharge_mode,
            'validate_only' => $validate_only,
            'allow_real_recharge' => $allow_real_recharge,
        ];
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
            'provider_name' => sanitize_text_field($_POST['provider_name'] ?? ''),
            'description' => sanitize_text_field($_POST['description'] ?? ''),
            'is_active' => empty($_POST['is_active']) ? 0 : 1,
        ];

        if (empty($bundle['country_iso']) || empty($bundle['label']) || empty($bundle['sku_code'])) {
            wp_safe_redirect(add_query_arg([
                'page' => 'dc-recargas',
                'dc_msg' => 'bundle_error',
            ], admin_url('admin.php')));
            exit;
        }

        $bundles = get_option('dc_recargas_bundles', []);
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

        $bundle_id = sanitize_text_field($_POST['bundle_id'] ?? '');
        $bundles = get_option('dc_recargas_bundles', []);
        $index = $this->find_bundle_index_by_id($bundles, $bundle_id);

        if ($index === -1) {
            wp_safe_redirect(add_query_arg([
                'page' => 'dc-recargas',
                'dc_msg' => 'bundle_not_found',
            ], admin_url('admin.php')));
            exit;
        }

        $bundle = [
            'id' => $bundle_id,
            'country_iso' => strtoupper(sanitize_text_field($_POST['country_iso'] ?? '')),
            'label' => sanitize_text_field($_POST['label'] ?? ''),
            'sku_code' => sanitize_text_field($_POST['sku_code'] ?? ''),
            'send_value' => (float) ($_POST['send_value'] ?? 0),
            'send_currency_iso' => strtoupper(sanitize_text_field($_POST['send_currency_iso'] ?? 'USD')),
            'provider_name' => sanitize_text_field($_POST['provider_name'] ?? ''),
            'description' => sanitize_text_field($_POST['description'] ?? ''),
            'is_active' => empty($_POST['is_active']) ? 0 : 1,
        ];

        if (empty($bundle['country_iso']) || empty($bundle['label']) || empty($bundle['sku_code'])) {
            wp_safe_redirect(add_query_arg([
                'page' => 'dc-recargas',
                'dc_msg' => 'bundle_error',
                'dc_edit_bundle' => $bundle_id,
            ], admin_url('admin.php')));
            exit;
        }

        if ($this->bundle_exists_by_country_sku($bundle['country_iso'], $bundle['sku_code'], $bundle_id)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'dc-recargas',
                'dc_msg' => 'bundle_duplicate',
                'dc_edit_bundle' => $bundle_id,
            ], admin_url('admin.php')));
            exit;
        }

        $bundles[$index] = $bundle;
        update_option('dc_recargas_bundles', $bundles);

        wp_safe_redirect(add_query_arg([
            'page' => 'dc-recargas',
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
        $is_active = empty($_POST['is_active']) ? 0 : 1;

        if (empty($country_iso) || empty($sku_code) || empty($operator)) {
            wp_send_json_error(['message' => 'Faltan datos obligatorios para crear el bundle.'], 400);
        }

        if ($this->bundle_exists_by_country_sku($country_iso, $sku_code)) {
            wp_send_json_error(['message' => 'Ya existe un bundle con ese país y SKU.'], 409);
        }

        $bundle = [
            'id' => uniqid('bundle_', true),
            'country_iso' => $country_iso,
            'label' => $operator . ' - ' . (!empty($receive) ? $receive : $sku_code),
            'sku_code' => $sku_code,
            'send_value' => $send_value,
            'send_currency_iso' => !empty($send_currency_iso) ? $send_currency_iso : 'EUR',
            'provider_name' => $operator,
            'description' => $receive,
            'is_active' => $is_active,
        ];

        $bundles = get_option('dc_recargas_bundles', []);
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

        $items = [];
        $group_counts = [];
        $group_labels = $this->get_api_package_group_labels();
        foreach ($raw_items as $product) {
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
            $package_group = $this->classify_api_package_group($product, $receive, $label);

            if (!isset($group_counts[$package_group])) {
                $group_counts[$package_group] = 0;
            }
            $group_counts[$package_group]++;

            $items[] = [
                'country_iso'       => $country_iso,
                'sku_code'          => $product['SkuCode'] ?? '',
                'operator'          => $product['ProviderCode'] ?? '',
                'label'             => $label,
                'send_value'        => isset($product['Minimum']['SendValue']) ? (float) $product['Minimum']['SendValue'] : 0,
                'send_currency_iso' => $product['Minimum']['SendCurrencyIso'] ?? 'USD',
                'receive'           => $receive,
                'validity'          => $product['ValidityPeriodIso'] ?? '',
                'package_group'     => $package_group,
                'package_group_label' => $group_labels[$package_group] ?? ($group_labels['other'] ?? 'Otros'),
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

    public function render_page() {
        $options = $this->api->get_options();
        $bundles = get_option('dc_recargas_bundles', []);
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
        $dl_provider_name   = array_values(array_unique(array_filter(array_column($bundles, 'provider_name'))));
        sort($dl_country_iso);
        sort($dl_label);
        sort($dl_send_currency);
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
            <div class="dc-admin-hero">
                <div class="dc-admin-hero__content">
                    <h1>DingConnect Recargas - Configuración</h1>
                    <p>Configura tu cuenta de DingConnect, define el modo de prueba y administra bundles visibles en el frontend.</p>
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

                .dc-landing-bundles-checklist {
                    max-height: 240px;
                    overflow: auto;
                    border: 1px solid #c9d6ea;
                    border-radius: 8px;
                    background: #ffffff;
                    padding: 8px;
                    display: grid;
                    gap: 6px;
                    width: min(680px, 100%);
                }

                .dc-landing-bundles-checklist__item {
                    display: flex;
                    align-items: flex-start;
                    gap: 8px;
                    font-size: 13px;
                    color: #1e293b;
                    padding: 4px 2px;
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

                /* ── Sub-pestañas internas del Catálogo ────────────────── */
                .dc-catalog-subnav {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 6px;
                    margin-bottom: 18px;
                    padding-bottom: 14px;
                    border-bottom: 2px solid var(--dc-border);
                }

                .dc-catalog-subnav__btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    border: 1px solid var(--dc-border);
                    background: #f8fafc;
                    color: #334155;
                    border-radius: 8px;
                    padding: 8px 16px;
                    font-size: 13px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: color 0.15s, border-color 0.15s, background 0.15s;
                }

                .dc-catalog-subnav__btn:hover {
                    background: var(--dc-primary-soft);
                    color: var(--dc-primary);
                    border-color: #bdd0ef;
                }

                .dc-catalog-subnav__btn.is-active {
                    background: linear-gradient(135deg, #145dc9 0%, #0f4aa3 100%);
                    color: #ffffff;
                    border-color: transparent;
                }

                .dc-catalog-subnav__badge {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    background: rgba(255,255,255,0.22);
                    border-radius: 999px;
                    font-size: 10px;
                    font-weight: 700;
                    padding: 1px 7px;
                    line-height: 1.5;
                    letter-spacing: 0.02em;
                    text-transform: uppercase;
                }

                .dc-catalog-subnav__btn:not(.is-active) .dc-catalog-subnav__badge {
                    background: var(--dc-primary-soft);
                    color: var(--dc-primary);
                }

                .dc-catalog-subpanel {
                    display: none;
                }

                .dc-catalog-subpanel.is-active {
                    display: block;
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

                .dc-admin-inline-warning {
                    margin: 0 0 8px;
                    color: #b45309;
                    font-weight: 600;
                }

                @media (max-width: 782px) {
                    .dc-catalog-subnav__btn {
                        flex: 1;
                        justify-content: center;
                    }
                }
            </style>

            <div class="dc-admin-tabs">
                <h2 class="nav-tab-wrapper" style="margin-bottom:0;">
                    <button type="button" class="nav-tab" data-dc-tab-btn="tab_setup">Credenciales</button>
                    <button type="button" class="nav-tab" data-dc-tab-btn="tab_catalog">Catálogo y alta</button>
                    <button type="button" class="nav-tab" data-dc-tab-btn="tab_saved">Bundles guardados</button>
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
                    <tr>
                        <th scope="row"><label for="dc_landing_bundle_ids">Bundles de la landing</label></th>
                        <td>
                            <div id="dc_landing_bundle_ids" class="dc-landing-bundles-checklist" role="group" aria-label="Bundles disponibles para la landing">
                                <?php foreach ($bundles as $bundle) : ?>
                                    <?php $bundle_id = sanitize_text_field((string) ($bundle['id'] ?? '')); ?>
                                    <?php if ($bundle_id === '') { continue; } ?>
                                    <label class="dc-landing-bundles-checklist__item">
                                        <input type="checkbox" name="bundle_ids[]" value="<?php echo esc_attr($bundle_id); ?>">
                                        <span>[<?php echo esc_html(strtoupper((string) ($bundle['country_iso'] ?? ''))); ?>] <?php echo esc_html((string) ($bundle['label'] ?? '')); ?> | <?php echo esc_html((string) ($bundle['sku_code'] ?? '')); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description">Marca con checkbox cada bundle que quieras incluir en la landing.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Crear shortcode de landing'); ?>
            </form>

            <h3>Shortcodes creados</h3>
            <table class="widefat striped">
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
                                $shortcode_text = '[dingconnect_recargas landing_key="' . $landing_key . '"]';
                            ?>
                            <tr>
                                <td><?php echo esc_html($landing_name); ?></td>
                                <td><code><?php echo esc_html($landing_key); ?></code></td>
                                <td><?php echo esc_html((string) count($landing_bundles)); ?></td>
                                <td><code><?php echo esc_html($shortcode_text); ?></code></td>
                                <td>
                                    <button type="button" class="button button-secondary dc-edit-landing-btn" data-landing="<?php echo esc_attr(wp_json_encode($landing_cfg)); ?>">Editar</button>
                                    <button type="button" class="button dc-customize-shortcode-btn" data-shortcode-key="<?php echo esc_attr($landing_key); ?>" data-shortcode-text="<?php echo esc_attr($shortcode_text); ?>">Personalizar</button>
                                    <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg([
                                        'action' => 'dc_clone_landing_shortcode',
                                        'landing_id' => $landing_id,
                                    ], admin_url('admin-post.php')), 'dc_clone_landing_shortcode')); ?>">Duplicar</a>
                                    <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(add_query_arg([
                                        'action' => 'dc_delete_landing_shortcode',
                                        'landing_id' => $landing_id,
                                    ], admin_url('admin-post.php')), 'dc_delete_landing_shortcode')); ?>" onclick="return confirm('¿Eliminar shortcode de landing?');">Eliminar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

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
                            <tr>
                                <th scope="row"><label for="dc_edit_landing_bundle_ids">Bundles de la landing</label></th>
                                <td>
                                    <div id="dc_edit_landing_bundle_ids" class="dc-landing-bundles-checklist" role="group" aria-label="Bundles disponibles para editar la landing">
                                        <?php foreach ($bundles as $bundle) : ?>
                                            <?php $bundle_id = sanitize_text_field((string) ($bundle['id'] ?? '')); ?>
                                            <?php if ($bundle_id === '') { continue; } ?>
                                            <label class="dc-landing-bundles-checklist__item">
                                                <input type="checkbox" class="dc-edit-landing-bundle-checkbox" name="bundle_ids[]" value="<?php echo esc_attr($bundle_id); ?>">
                                                <span>[<?php echo esc_html(strtoupper((string) ($bundle['country_iso'] ?? ''))); ?>] <?php echo esc_html((string) ($bundle['label'] ?? '')); ?> | <?php echo esc_html((string) ($bundle['sku_code'] ?? '')); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="description">Marca con checkbox los bundles que deben quedar en este shortcode.</p>
                                </td>
                            </tr>
                        </table>

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
            <p>Añade productos al catálogo visible en el frontend usando búsqueda en API o alta manual.</p>

            <div class="dc-catalog-subtabs">
                <nav class="dc-catalog-subnav" aria-label="Métodos de alta de bundles">
                    <button type="button" class="dc-catalog-subnav__btn is-active" data-catalog-subtab="api">
                        🔌 Buscar en API
                    </button>
                    <button type="button" class="dc-catalog-subnav__btn" data-catalog-subtab="manual">
                        ✏️ Alta manual
                    </button>
                </nav>

                <!-- ── Sub-panel 2: API ── -->
                <div class="dc-catalog-subpanel is-active" data-catalog-panel="api">

            <p class="dc-catalog-subpanel__intro">Consulta los paquetes disponibles directamente desde DingConnect en tiempo real. No necesitas el CSV; los resultados se guardan en caché por <strong>10 minutos</strong> por país.</p>

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
                <tr>
                    <th scope="row"><label for="dc_api_results">Paquetes encontrados (doble click para alta manual)</label></th>
                    <td>
                        <input type="text" id="dc_api_search" class="regular-text" placeholder="Buscar por operador, beneficio o SKU..." disabled style="margin-bottom:6px;width:100%;box-sizing:border-box;">
                        <select id="dc_api_results" size="10" class="large-text"></select>
                        <p class="description" id="dc_api_help">Selecciona un país y haz clic en «Buscar en API». Luego puedes hacer doble click en un paquete para cargarlo en «Alta manual».</p>
                        <p>
                            <button type="button" class="button button-primary" id="dc_api_create_btn" disabled>Crear bundle desde API</button>
                        </p>
                    </td>
                </tr>
            </table>
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
                var apiCreateBtn = document.getElementById('dc_api_create_btn');
                var catalogSubtabsEl = document.querySelector('.dc-catalog-subtabs');
                var manualCountryIsoEl = document.getElementById('dc_country_iso');
                var manualLabelEl = document.getElementById('dc_label');
                var manualSkuEl = document.getElementById('dc_sku_code');
                var manualSendValueEl = document.getElementById('dc_send_value');
                var manualSendCurrencyEl = document.getElementById('dc_send_currency_iso');
                var manualProviderEl = document.getElementById('dc_provider_name');
                var manualDescriptionEl = document.getElementById('dc_description');
                var manualBundleSourceEl = document.getElementById('dc_manual_bundle_source');
                var apiSelected  = null;
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

                function apiOptionLabel(item) {
                    var parts = [item.operator, item.receive || item.label];
                    if (item.send_value && item.send_currency_iso) {
                        parts.push(item.send_value + ' ' + item.send_currency_iso);
                    }
                    if (item.validity) {
                        parts.push(item.validity);
                    }
                    return parts.filter(Boolean).join(' — ');
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
                                item.validity || ''
                            ].join(' ').toLowerCase();
                            return haystack.indexOf(searchTerm) !== -1;
                        });
                    }

                    return items;
                }

                function getManualBundleSourceText(item) {
                    var text = item && (item.label || item.receive || item.sku_code || '');
                    return String(text || '').trim();
                }

                function updateManualBundleSource(item) {
                    if (!manualBundleSourceEl) {
                        return;
                    }

                    var text = getManualBundleSourceText(item);
                    if (!text) {
                        manualBundleSourceEl.textContent = '';
                        manualBundleSourceEl.hidden = true;
                        return;
                    }

                    manualBundleSourceEl.textContent = 'Paquete API: ' + text;
                    manualBundleSourceEl.hidden = false;
                }

                function setCatalogSubtabState(tabId) {
                    if (!catalogSubtabsEl || !tabId) {
                        return false;
                    }

                    var tabFound = false;
                    var subBtns = catalogSubtabsEl.querySelectorAll('[data-catalog-subtab]');
                    var subPanels = catalogSubtabsEl.querySelectorAll('[data-catalog-panel]');

                    subBtns.forEach(function (btn) {
                        var isTarget = btn.getAttribute('data-catalog-subtab') === tabId;
                        btn.classList.toggle('is-active', isTarget);
                        btn.setAttribute('aria-pressed', isTarget ? 'true' : 'false');
                        if (isTarget) {
                            tabFound = true;
                        }
                    });

                    subPanels.forEach(function (panel) {
                        var isTarget = panel.getAttribute('data-catalog-panel') === tabId;
                        panel.classList.toggle('is-active', isTarget);
                        panel.hidden = !isTarget;
                        panel.setAttribute('aria-hidden', isTarget ? 'false' : 'true');
                    });

                    return tabFound;
                }

                window.dcSetCatalogSubtab = setCatalogSubtabState;

                function fillManualForm(item) {
                    if (manualCountryIsoEl) manualCountryIsoEl.value = item.country_iso || '';
                    if (manualLabelEl) manualLabelEl.value = (item.operator || 'Producto') + ' - ' + (item.receive || item.label || item.sku_code || '');
                    if (manualSkuEl) manualSkuEl.value = item.sku_code || '';
                    if (manualSendValueEl) manualSendValueEl.value = item.send_value != null ? item.send_value : '';
                    if (manualSendCurrencyEl) manualSendCurrencyEl.value = item.send_currency_iso || 'USD';
                    if (manualProviderEl) manualProviderEl.value = item.operator || '';
                    if (manualDescriptionEl) manualDescriptionEl.value = item.receive || item.label || '';
                    updateManualBundleSource(item);
                }

                function openManualSubtab() {
                    var opened = false;

                    if (typeof window.dcSetCatalogSubtab === 'function') {
                        opened = window.dcSetCatalogSubtab('manual');
                    }

                    if (!opened) {
                        var manualTabBtn = document.querySelector('[data-catalog-subtab="manual"]');
                        if (manualTabBtn) {
                            manualTabBtn.click();
                            opened = true;
                        }
                    }

                    if (opened && manualCountryIsoEl && typeof manualCountryIsoEl.focus === 'function') {
                        manualCountryIsoEl.focus();
                        if (typeof manualCountryIsoEl.scrollIntoView === 'function') {
                            manualCountryIsoEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }
                }

                function resetApiState(message) {
                    apiItems = [];
                    apiGroupCounts = {};
                    apiSelected = null;
                    apiResultsEl.innerHTML = '';
                    apiCreateBtn.disabled = true;

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

                function renderApiResults(items) {
                    var grouped = {};

                    apiResultsEl.innerHTML = '';
                    apiSelected = null;
                    apiCreateBtn.disabled = true;

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
                            var optgroup = document.createElement('optgroup');
                            optgroup.label = (apiGroupLabels[group] || group) + ' (' + grouped[group].length + ')';

                            grouped[group].forEach(function (item) {
                                var opt = document.createElement('option');
                                opt.value = item.sku_code;
                                opt.textContent = apiOptionLabel(item);
                                opt.dataset.item = JSON.stringify(item);
                                optgroup.appendChild(opt);
                            });

                            apiResultsEl.appendChild(optgroup);
                        });

                    if (apiFilterEl && apiFilterEl.value !== 'all') {
                        apiHelpEl.textContent = items.length + ' paquete(s) en «' + (apiGroupLabels[apiFilterEl.value] || apiFilterEl.value) + '». Selecciona uno para crear el bundle o haz doble click para cargarlo en alta manual.';
                        return;
                    }

                    apiHelpEl.textContent = items.length + ' paquete(s) encontrado(s), agrupados por tipo. Selecciona uno para crear el bundle o haz doble click para cargarlo en alta manual.';
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

                apiResultsEl.addEventListener('change', function () {
                    var opt = apiResultsEl.options[apiResultsEl.selectedIndex];
                    if (!opt) {
                        apiSelected = null;
                        apiCreateBtn.disabled = true;
                        return;
                    }
                    try {
                        apiSelected = JSON.parse(opt.dataset.item);
                        apiCreateBtn.disabled = false;
                    } catch (e) {
                        apiSelected = null;
                        apiCreateBtn.disabled = true;
                    }
                });

                apiResultsEl.addEventListener('dblclick', function () {
                    var opt = apiResultsEl.options[apiResultsEl.selectedIndex];
                    if (!opt) {
                        return;
                    }

                    try {
                        var item = JSON.parse(opt.dataset.item);
                        fillManualForm(item);
                        openManualSubtab();
                        apiHelpEl.textContent = 'Producto cargado en «Alta manual». Revisa los campos y guarda el bundle cuando quieras.';
                    } catch (e) {
                        apiHelpEl.textContent = 'No se pudo cargar el producto seleccionado en el formulario manual.';
                    }
                });

                apiCreateBtn.addEventListener('click', function () {
                    if (!apiSelected) { return; }

                    apiCreateBtn.disabled = true;
                    apiHelpEl.textContent = 'Creando bundle...';

                    var body = new URLSearchParams({
                        action: 'dc_create_bundle_from_catalog',
                        nonce: apiNonce,
                        country_iso: apiSelected.country_iso,
                        sku_code: apiSelected.sku_code,
                        operator: apiSelected.operator,
                        receive: apiSelected.receive || apiSelected.label,
                        send_value: apiSelected.send_value,
                        send_currency_iso: apiSelected.send_currency_iso,
                        is_active: '0',
                    });

                    fetch(ajaxurl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString(),
                    })
                        .then(function (res) { return res.json(); })
                        .then(function (data) {
                            apiCreateBtn.disabled = false;
                            if (data.success) {
                                var bundleLabel = data.data.bundle && data.data.bundle.label ? data.data.bundle.label : apiSelected.label;
                                apiItems = apiItems.filter(function (item) {
                                    return String(item.sku_code) !== String(apiSelected.sku_code);
                                });
                                apiGroupCounts = {};
                                apiItems.forEach(function (item) {
                                    var group = String(item.package_group || 'other');
                                    apiGroupCounts[group] = Number(apiGroupCounts[group] || 0) + 1;
                                });
                                apiSelected = null;
                                refreshApiResults();
                                saveStoredApiState();
                                apiHelpEl.textContent = 'Bundle «' + bundleLabel + '» creado correctamente.';
                            } else {
                                apiHelpEl.textContent = 'Error: ' + (data.data && data.data.message ? data.data.message : 'No se pudo crear el bundle.');
                            }
                        })
                        .catch(function () {
                            apiCreateBtn.disabled = false;
                            apiHelpEl.textContent = 'Error de red al crear el bundle.';
                        });
                });

                restoreStoredApiState();
            })();
            </script>

                </div><!-- /sub-panel api -->

                <!-- ── Sub-panel 3: Alta manual ── -->
                <div class="dc-catalog-subpanel" data-catalog-panel="manual">

            <p class="dc-catalog-subpanel__intro">Completa el formulario con los datos del bundle. Usa este método cuando conozcas el SKU exacto y quieras un control total sobre los valores que se muestran al usuario.</p>

            <h4 class="dc-manual-bundle-heading">Datos del bundle <span id="dc_manual_bundle_source" class="dc-manual-bundle-source" hidden></span></h4>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="dc_add_bundle">
                <?php wp_nonce_field('dc_add_bundle'); ?>

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
                        <th scope="row"><label for="dc_send_value">Monto</label></th>
                        <td><input type="number" step="0.01" id="dc_send_value" name="send_value" class="small-text" value="0"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_send_currency_iso">Moneda</label></th>
                        <td><input type="text" id="dc_send_currency_iso" name="send_currency_iso" class="small-text dc-combo-input" value="USD" list="dc_dl_send_currency"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_provider_name">Operador</label></th>
                        <td><input type="text" id="dc_provider_name" name="provider_name" class="regular-text dc-combo-input" placeholder="Cubacel" value="" list="dc_dl_provider_name"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_description">Beneficios recibidos</label></th>
                        <td>
                            <textarea id="dc_description" name="description" class="regular-text" rows="2" placeholder="Ej: Monthly 30GB, Daily 125 Min, USD 10"></textarea>
                            <p class="description">Lo que recibe el usuario (columna <em>Receive</em> del CSV). Texto corto y claro.</p>
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

                <?php submit_button('Añadir bundle'); ?>
            </form>

                </div><!-- /sub-panel manual -->

            </div><!-- /dc-catalog-subtabs -->

                </section>

                <section id="dc-tab-saved" class="dc-tab-panel is-special" data-dc-tab-panel="tab_saved">

            <h2>Bundles guardados</h2>
            <p>Estos bundles aparecen como respaldo o catálogo inicial en el formulario frontal.</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="dc_bulk_delete_bundles_form">
                <input type="hidden" name="action" value="dc_bulk_delete_bundles">
                <?php wp_nonce_field('dc_bulk_delete_bundles'); ?>
                <p>
                    <button type="submit" class="button button-secondary" id="dc_bulk_delete_btn">Eliminar seleccionados</button>
                    <span class="description">Puedes seleccionar uno o varios bundles desde la tabla.</span>
                </p>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th class="check-column">
                            <input type="checkbox" id="dc_bundles_select_all" aria-label="Seleccionar todos los bundles">
                        </th>
                        <th>País</th>
                        <th>Nombre</th>
                        <th>SKU</th>
                        <th>Monto</th>
                        <th>Moneda</th>
                        <th>Operador</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($bundles)) : ?>
                    <tr>
                        <td colspan="9">Aún no has agregado bundles.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($bundles as $bundle) : ?>
                        <tr>
                            <td class="check-column">
                                <input type="checkbox" class="dc-bundle-checkbox" name="bundle_ids[]" value="<?php echo esc_attr($bundle['id'] ?? ''); ?>" aria-label="Seleccionar bundle <?php echo esc_attr($bundle['label'] ?? ''); ?>">
                            </td>
                            <td><?php echo esc_html($bundle['country_iso'] ?? ''); ?></td>
                            <td><?php echo esc_html($bundle['label'] ?? ''); ?></td>
                            <td><?php echo esc_html($bundle['sku_code'] ?? ''); ?></td>
                            <td><?php echo esc_html(number_format((float) ($bundle['send_value'] ?? 0), 2)); ?></td>
                            <td><?php echo esc_html($bundle['send_currency_iso'] ?? ''); ?></td>
                            <td><?php echo esc_html($bundle['provider_name'] ?? ''); ?></td>
                            <td><?php echo !empty($bundle['is_active']) ? 'Activo' : 'Inactivo'; ?></td>
                            <td>
                                <div class="dc-bundle-actions">
                                <button
                                    type="button"
                                    class="button button-secondary dc-edit-bundle-btn"
                                    data-bundle="<?php echo esc_attr(wp_json_encode($bundle)); ?>">
                                    Editar
                                </button>

                                <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg([
                                    'action' => 'dc_toggle_bundle',
                                    'bundle_id' => $bundle['id'] ?? '',
                                ], admin_url('admin-post.php')), 'dc_toggle_bundle')); ?>">
                                    <?php echo !empty($bundle['is_active']) ? 'Desactivar' : 'Activar'; ?>
                                </a>

                                <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(add_query_arg([
                                    'action' => 'dc_delete_bundle',
                                    'bundle_id' => $bundle['id'] ?? '',
                                ], admin_url('admin-post.php')), 'dc_delete_bundle')); ?>" onclick="return confirm('¿Eliminar bundle?');">
                                    Eliminar
                                </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            </form>

            <div id="dc-edit-modal" class="dc-edit-modal" role="dialog" aria-modal="true" aria-labelledby="dc-edit-modal-title" <?php echo empty($editing_bundle) ? 'hidden' : ''; ?>>
                <div class="dc-edit-modal__backdrop" data-dc-edit-close></div>
                <div class="dc-edit-modal__dialog">
                    <div class="dc-edit-modal__header">
                        <div>
                            <h3 id="dc-edit-modal-title">Editar bundle</h3>
                            <p>Modifica el bundle sin salir de la tabla de bundles guardados.</p>
                        </div>
                        <button type="button" class="dc-edit-modal__close" aria-label="Cerrar edición" data-dc-edit-close>&times;</button>
                    </div>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="dc_edit_bundle_form">
                        <input type="hidden" name="action" value="dc_update_bundle">
                        <?php wp_nonce_field('dc_update_bundle'); ?>
                        <input type="hidden" id="dc_edit_bundle_id" name="bundle_id" value="<?php echo esc_attr($editing_bundle['id'] ?? ''); ?>">

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
                                <th scope="row"><label for="dc_edit_send_value">Monto</label></th>
                                <td><input type="number" step="0.01" id="dc_edit_send_value" name="send_value" class="small-text" value="<?php echo esc_attr(isset($editing_bundle['send_value']) ? (float) $editing_bundle['send_value'] : 0); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="dc_edit_send_currency_iso">Moneda</label></th>
                                <td><input type="text" id="dc_edit_send_currency_iso" name="send_currency_iso" class="small-text dc-combo-input" value="<?php echo esc_attr($editing_bundle['send_currency_iso'] ?? 'USD'); ?>" list="dc_dl_send_currency"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="dc_edit_provider_name">Operador</label></th>
                                <td><input type="text" id="dc_edit_provider_name" name="provider_name" class="regular-text dc-combo-input" placeholder="Cubacel" value="<?php echo esc_attr($editing_bundle['provider_name'] ?? ''); ?>" list="dc_dl_provider_name"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="dc_edit_description">Beneficios recibidos</label></th>
                                <td>
                                    <textarea id="dc_edit_description" name="description" class="regular-text" rows="2" placeholder="Ej: Monthly 30GB, Daily 125 Min, USD 10"><?php echo esc_textarea($editing_bundle['description'] ?? ''); ?></textarea>
                                    <p class="description">Lo que recibe el usuario (columna <em>Receive</em> del CSV).</p>
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

                        <?php submit_button('Guardar cambios del bundle', 'primary', 'submit', false); ?>
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
                    ?>

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
                var editButtons = document.querySelectorAll('.dc-edit-bundle-btn');
                var editCloseEls = document.querySelectorAll('[data-dc-edit-close]');
                var selectAllBundlesEl = document.getElementById('dc_bundles_select_all');
                var bulkDeleteFormEl = document.getElementById('dc_bulk_delete_bundles_form');
                var bundleCheckboxEls = document.querySelectorAll('.dc-bundle-checkbox');
                var initialEditingBundle = <?php echo wp_json_encode($editing_bundle ? $editing_bundle : null); ?>;
                var initialEditingLanding = <?php echo wp_json_encode($editing_landing ? $editing_landing : null); ?>;
                var editIdEl = document.getElementById('dc_edit_bundle_id');
                var editCountryIsoEl = document.getElementById('dc_edit_country_iso');
                var editLabelEl = document.getElementById('dc_edit_label');
                var editSkuEl = document.getElementById('dc_edit_sku_code');
                var editSendValueEl = document.getElementById('dc_edit_send_value');
                var editSendCurrencyEl = document.getElementById('dc_edit_send_currency_iso');
                var editProviderEl = document.getElementById('dc_edit_provider_name');
                var editDescriptionEl = document.getElementById('dc_edit_description');
                var editIsActiveEl = document.getElementById('dc_edit_is_active');
                var landingEditModalEl = document.getElementById('dc-edit-landing-modal');
                var landingEditButtons = document.querySelectorAll('.dc-edit-landing-btn');
                var landingEditCloseEls = document.querySelectorAll('[data-dc-landing-edit-close]');
                var landingEditIdEl = document.getElementById('dc_edit_landing_id');
                var landingEditNameEl = document.getElementById('dc_edit_landing_name');
                var landingEditKeyEl = document.getElementById('dc_edit_landing_key');
                var landingEditTitleEl = document.getElementById('dc_edit_landing_title');
                var landingEditSubtitleEl = document.getElementById('dc_edit_landing_subtitle');
                var landingEditBundleCheckboxEls = document.querySelectorAll('#dc_edit_landing_form .dc-edit-landing-bundle-checkbox');
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
                            window.alert('Selecciona al menos un bundle para eliminar.');
                            return;
                        }

                        if (!window.confirm('¿Eliminar ' + selectedCount + ' bundle(s) seleccionados?')) {
                            event.preventDefault();
                        }
                    });
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

                if (editButtons.length) {
                    editButtons.forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            try {
                                openEditModal(JSON.parse(btn.getAttribute('data-bundle') || '{}'));
                            } catch (e) {
                                window.alert('No se pudo abrir el editor del bundle seleccionado.');
                            }
                        });
                    });
                }

                if (editCloseEls.length) {
                    editCloseEls.forEach(function (el) {
                        el.addEventListener('click', closeEditModal);
                    });
                }

                if (landingEditButtons.length) {
                    landingEditButtons.forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            try {
                                openLandingEditModal(JSON.parse(btn.getAttribute('data-landing') || '{}'));
                            } catch (e) {
                                window.alert('No se pudo abrir el editor del shortcode seleccionado.');
                            }
                        });
                    });
                }

                if (landingEditCloseEls.length) {
                    landingEditCloseEls.forEach(function (el) {
                        el.addEventListener('click', closeLandingEditModal);
                    });
                }

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
                var providerEl = document.getElementById('dc_provider_name');
                var descriptionEl = document.getElementById('dc_description');

                function fillForm(item) {
                    if (countryIsoEl) countryIsoEl.value = item.country_iso || '';
                    if (labelEl) labelEl.value = (item.operator || 'Producto') + ' - ' + (item.receive || item.sku_code || '');
                    if (skuEl) skuEl.value = item.sku_code || '';
                    if (sendValueEl) sendValueEl.value = item.send_value != null ? item.send_value : '';
                    if (sendCurrencyEl) sendCurrencyEl.value = item.send_currency_iso || 'EUR';
                    if (providerEl) providerEl.value = item.operator || '';
                    if (descriptionEl) descriptionEl.value = item.receive || '';
                }

                var datalistMap = {
                    dc_dl_country_iso: ['dc_country_iso', 'dc_edit_country_iso'],
                    dc_dl_label: ['dc_label', 'dc_edit_label'],
                    dc_dl_send_currency: ['dc_send_currency_iso', 'dc_edit_send_currency_iso'],
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
                var customizeCloseEls = document.querySelectorAll('[data-dc-customize-close]');
                var maxWidthInput = document.getElementById('dc_customize_max_width');
                var bgColorInput = document.getElementById('dc_customize_bg_color');
                var primaryColorInput = document.getElementById('dc_customize_primary_color');
                var textColorInput = document.getElementById('dc_customize_text_color');
                var borderRadiusInput = document.getElementById('dc_customize_border_radius');
                var paddingInput = document.getElementById('dc_customize_padding');
                var shadowIntensitySelect = document.getElementById('dc_customize_shadow_intensity');
                var preview = document.getElementById('dc_customize_preview');
                var customizeStatus = document.getElementById('dc_customize_status');
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

                customizeCloseEls.forEach(function (el) {
                    el.addEventListener('click', closeCustomizeModal);
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

            // -- Sub-pestañas del Catálogo --------------------------------
            (function () {
                var catalogSubtabsEl = document.querySelector('.dc-catalog-subtabs');
                var subBtns = catalogSubtabsEl ? catalogSubtabsEl.querySelectorAll('[data-catalog-subtab]') : [];
                var subPanels = catalogSubtabsEl ? catalogSubtabsEl.querySelectorAll('[data-catalog-panel]') : [];

                if (!subBtns.length || !subPanels.length) {
                    return;
                }

                var setSubTab = typeof window.dcSetCatalogSubtab === 'function'
                    ? window.dcSetCatalogSubtab
                    : function (tabId) {
                        subBtns.forEach(function (btn) {
                            var isTarget = btn.getAttribute('data-catalog-subtab') === tabId;
                            btn.classList.toggle('is-active', isTarget);
                            btn.setAttribute('aria-pressed', isTarget ? 'true' : 'false');
                        });

                        subPanels.forEach(function (panel) {
                            var isTarget = panel.getAttribute('data-catalog-panel') === tabId;
                            panel.classList.toggle('is-active', isTarget);
                            panel.hidden = !isTarget;
                            panel.setAttribute('aria-hidden', isTarget ? 'false' : 'true');
                        });
                    };

                subBtns.forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        setSubTab(btn.getAttribute('data-catalog-subtab'));
                    });
                });

                var activeBtn = catalogSubtabsEl.querySelector('[data-catalog-subtab].is-active');
                setSubTab(activeBtn ? activeBtn.getAttribute('data-catalog-subtab') : 'api');
            })();
        </script>
        <?php
    }

    private function render_notice($msg) {
        $count = isset($_GET['dc_count']) ? (int) $_GET['dc_count'] : 0;
        $state = sanitize_text_field($_GET['dc_state'] ?? '');
        $toggle_text = $state === 'active' ? 'Bundle activado correctamente.' : 'Bundle desactivado correctamente.';
        $map = [
            'bundle_added' => ['success', 'Bundle agregado correctamente.'],
            'bundle_updated' => ['success', 'Bundle actualizado correctamente.'],
            'bundle_toggled' => ['success', $toggle_text],
            'bundle_deleted' => ['success', 'Bundle eliminado correctamente.'],
            'bundle_bulk_deleted' => ['success', sprintf('Bundles eliminados correctamente: %d.', $count)],
            'bundle_bulk_empty' => ['error', 'Selecciona al menos un bundle para eliminar.'],
            'landing_shortcode_added' => ['success', 'Shortcode dinámico de landing creado correctamente.'],
            'landing_shortcode_updated' => ['success', 'Shortcode dinámico actualizado correctamente.'],
            'landing_shortcode_cloned' => ['success', 'Landing duplicada correctamente.'],
            'landing_shortcode_deleted' => ['success', 'Shortcode dinámico eliminado correctamente.'],
            'landing_shortcode_error' => ['error', 'Completa nombre y selecciona al menos un bundle válido para crear el shortcode dinámico.'],
            'bundle_error' => ['error', 'Completa País ISO, Nombre y SKU para añadir un bundle.'],
            'bundle_duplicate' => ['error', 'Ya existe otro bundle con el mismo país y SKU.'],
            'bundle_not_found' => ['error', 'No se encontró el bundle solicitado.'],
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
