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
        add_action('wp_ajax_dc_search_csv_products', [$this, 'ajax_search_csv_products']);
        add_action('wp_ajax_dc_create_bundle_from_csv', [$this, 'ajax_create_bundle_from_csv']);
        add_action('admin_post_dc_upload_csv', [$this, 'handle_upload_csv']);
        add_action('wp_ajax_dc_get_transfer_logs', [$this, 'ajax_get_transfer_logs']);
        add_action('admin_post_dc_clear_logs', [$this, 'handle_clear_logs']);
        add_action('wp_ajax_dc_fetch_api_products', [$this, 'ajax_fetch_api_products']);
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

    public function sanitize_options($input) {
        $mode = sanitize_text_field((string) ($input['payment_mode'] ?? 'direct'));
        if (!in_array($mode, ['woocommerce', 'direct'], true)) {
            $mode = 'direct';
        }

        $checkout_mapping_mode = sanitize_key((string) ($input['wizard_checkout_mapping_mode'] ?? 'both'));
        if (!in_array($checkout_mapping_mode, ['both', 'beneficiary_only', 'buyer_only'], true)) {
            $checkout_mapping_mode = 'both';
        }

        $wizard_max_offers = (int) ($input['wizard_max_offers_per_category'] ?? 6);
        if ($wizard_max_offers < 1) {
            $wizard_max_offers = 1;
        }
        if ($wizard_max_offers > 20) {
            $wizard_max_offers = 20;
        }

        $wizard_entry_mode = sanitize_key((string) ($input['wizard_default_entry_mode'] ?? 'number_first'));
        if (!in_array($wizard_entry_mode, ['number_first', 'country_fixed'], true)) {
            $wizard_entry_mode = 'number_first';
        }

        $wizard_fixed_prefix = preg_replace('/[^0-9+]/', '', (string) ($input['wizard_fixed_prefix'] ?? ''));

        $retry_attempts = (int) ($input['wizard_transfer_retry_attempts'] ?? 2);
        if ($retry_attempts < 0) {
            $retry_attempts = 0;
        }
        if ($retry_attempts > 5) {
            $retry_attempts = 5;
        }

        $retry_delay_minutes = (int) ($input['wizard_transfer_retry_delay_minutes'] ?? 15);
        if ($retry_delay_minutes < 1) {
            $retry_delay_minutes = 1;
        }
        if ($retry_delay_minutes > 240) {
            $retry_delay_minutes = 240;
        }

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
            'recharge_mode' => $recharge_mode,
            'validate_only' => $validate_only,
            'allow_real_recharge' => $allow_real_recharge,
            'wizard_enabled' => empty($input['wizard_enabled']) ? 0 : 1,
            'wizard_max_offers_per_category' => $wizard_max_offers,
            'wizard_default_entry_mode' => $wizard_entry_mode,
            'wizard_fixed_prefix' => $wizard_fixed_prefix,
            'wizard_checkout_mapping_mode' => $checkout_mapping_mode,
            'wizard_checkout_beneficiary_meta_key' => sanitize_key((string) ($input['wizard_checkout_beneficiary_meta_key'] ?? '_dc_beneficiary_phone')),
            'wizard_transfer_retry_attempts' => $retry_attempts,
            'wizard_transfer_retry_delay_minutes' => $retry_delay_minutes,
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

    public function handle_upload_csv() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('dc_upload_csv');

        if (empty($_FILES['csv_file']['tmp_name'])) {
            wp_safe_redirect(add_query_arg([
                'page' => 'dc-recargas',
                'dc_msg' => 'csv_no_file',
            ], admin_url('admin.php')));
            exit;
        }

        $file = $_FILES['csv_file'];

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            wp_safe_redirect(add_query_arg([
                'page' => 'dc-recargas',
                'dc_msg' => 'csv_upload_error',
            ], admin_url('admin.php')));
            exit;
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            wp_safe_redirect(add_query_arg([
                'page' => 'dc-recargas',
                'dc_msg' => 'csv_upload_error',
            ], admin_url('admin.php')));
            exit;
        }

        $header = fgetcsv($handle);
        fclose($handle);

        if (!is_array($header)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'dc-recargas',
                'dc_msg' => 'csv_upload_error',
            ], admin_url('admin.php')));
            exit;
        }

        $required_columns = ['Operator', 'Country', 'Send amount', 'Receive', 'Product type', 'Validity', 'SkuCode'];
        $header_map = array_flip($header);
        foreach ($required_columns as $col) {
            if (!isset($header_map[$col])) {
                wp_safe_redirect(add_query_arg([
                    'page' => 'dc-recargas',
                    'dc_msg' => 'csv_upload_error',
                ], admin_url('admin.php')));
                exit;
            }
        }

        $upload_dir = wp_upload_dir();
        $dest_dir = trailingslashit($upload_dir['basedir']) . 'dingconnect';
        if (!file_exists($dest_dir)) {
            wp_mkdir_p($dest_dir);
        }
        $dest = trailingslashit($dest_dir) . 'Products-with-sku.csv';
        $moved = move_uploaded_file($file['tmp_name'], $dest);

        if (!$moved) {
            wp_safe_redirect(add_query_arg([
                'page' => 'dc-recargas',
                'dc_msg' => 'csv_upload_error',
            ], admin_url('admin.php')));
            exit;
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'dc-recargas',
            'dc_msg' => 'csv_uploaded',
        ], admin_url('admin.php')));
        exit;
    }

    public function ajax_search_csv_products() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No autorizado.'], 403);
        }

        check_ajax_referer('dc_csv_search', 'nonce');

        $query = sanitize_text_field(wp_unslash($_GET['q'] ?? ''));
        $country = sanitize_text_field(wp_unslash($_GET['country'] ?? ''));
        $initial = !empty($_GET['initial']);

        if ($initial) {
            $items = $this->search_products_csv_main_countries(10);
        } else {
            $items = $this->search_products_csv($query, $country, 120);
        }

        $csv_path = $this->get_products_csv_path();
        wp_send_json_success([
            'items' => $items,
            'csv_found' => !empty($csv_path),
        ]);
    }

    public function ajax_create_bundle_from_csv() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No autorizado.'], 403);
        }

        check_ajax_referer('dc_csv_search', 'nonce');

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
            'message' => 'Bundle creado correctamente desde CSV.',
            'bundle' => $bundle,
        ]);
    }

    public function ajax_fetch_api_products() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No autorizado.'], 403);
            return;
        }

        check_ajax_referer('dc_csv_search', 'nonce');

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

            $items[] = [
                'country_iso'       => $country_iso,
                'sku_code'          => $product['SkuCode'] ?? '',
                'operator'          => $product['ProviderCode'] ?? '',
                'label'             => $label,
                'send_value'        => isset($product['Minimum']['SendValue']) ? (float) $product['Minimum']['SendValue'] : 0,
                'send_currency_iso' => $product['Minimum']['SendCurrencyIso'] ?? 'USD',
                'receive'           => $receive,
                'validity'          => $product['ValidityPeriodIso'] ?? '',
            ];
        }

        wp_send_json_success(['items' => $items, 'country_iso' => $country_iso, 'total' => count($items)]);
    }

    public function render_page() {
        $options = $this->api->get_options();
        $bundles = get_option('dc_recargas_bundles', []);
        $msg = sanitize_text_field($_GET['dc_msg'] ?? '');
        $editing_bundle_id = sanitize_text_field($_GET['dc_edit_bundle'] ?? '');
        $editing_bundle = $this->find_bundle_by_id($bundles, $editing_bundle_id);
        $csv_path = $this->get_products_csv_path();
        $csv_found = !empty($csv_path);
        $csv_countries = $this->get_csv_countries();
        $csv_nonce = wp_create_nonce('dc_csv_search');
        $active_tab = 'tab_setup';

        // Extract unique values from existing bundles for datalist dropdowns.
        $dl_country_iso     = array_values(array_unique(array_filter(array_column($bundles, 'country_iso'))));
        $dl_label           = array_values(array_unique(array_filter(array_column($bundles, 'label'))));
        $dl_send_currency   = array_values(array_unique(array_filter(array_column($bundles, 'send_currency_iso'))));
        $dl_provider_name   = array_values(array_unique(array_filter(array_column($bundles, 'provider_name'))));
        sort($dl_country_iso);
        sort($dl_label);
        sort($dl_send_currency);
        sort($dl_provider_name);

        $requested_tab = sanitize_key($_GET['dc_tab'] ?? '');
        if (in_array($requested_tab, ['tab_setup', 'tab_catalog', 'tab_saved', 'tab_logs'], true)) {
            $active_tab = $requested_tab;
        }

        if (!empty($editing_bundle)) {
            $active_tab = 'tab_saved';
        }

        if (in_array($msg, ['csv_uploaded', 'csv_upload_error', 'csv_no_file', 'bundle_error', 'bundle_duplicate'], true)) {
            $active_tab = !empty($editing_bundle) ? 'tab_saved' : 'tab_catalog';
        }

        if (in_array($msg, ['bundle_added', 'bundle_updated', 'bundle_toggled', 'bundle_deleted', 'bundle_bulk_deleted', 'bundle_bulk_empty'], true)) {
            $active_tab = 'tab_saved';
        }
        ?>
        <div class="wrap dc-admin-wrap">
            <h1>DingConnect Recargas - Configuración</h1>
            <p>Configura tu cuenta de DingConnect, define el modo de prueba y administra bundles visibles en el frontend.</p>
            <p><em>Hecho por Cambiodigital.net, personalizado para cubakilos.com.</em></p>

            <?php $this->render_notice($msg); ?>

            <style>
                .dc-admin-wrap {
                    --dc-bg: #f4f7fc;
                    --dc-card: #ffffff;
                    --dc-text: #0f172a;
                    --dc-muted: #64748b;
                    --dc-primary: #0f4aa3;
                    --dc-primary-soft: #e6efff;
                    --dc-border: #d9e1ef;
                    --dc-shadow: none;
                    background: radial-gradient(circle at top left, #eaf3ff 0%, #f7fafe 35%, #f4f7fc 100%);
                    padding: 14px 18px 22px;
                    border-radius: 14px;
                }

                .dc-admin-wrap > h1 {
                    margin: 0;
                    font-size: 30px;
                    line-height: 1.2;
                    color: var(--dc-text);
                    letter-spacing: -0.01em;
                }

                .dc-admin-wrap > p {
                    margin: 10px 0 0;
                    max-width: 860px;
                    color: #334155;
                    font-size: 14px;
                }

                .dc-admin-wrap > p em {
                    color: var(--dc-muted);
                    font-style: normal;
                    font-weight: 500;
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
                }

                .dc-admin-wrap .nav-tab {
                    float: none;
                    border: 1px solid var(--dc-border);
                    background: #ffffff;
                    color: #334155;
                    border-radius: 999px;
                    padding: 9px 14px;
                    margin: 0;
                    font-weight: 600;
                    transition: color 0.15s ease, border-color 0.15s ease, background 0.15s ease;
                }

                .dc-admin-wrap .nav-tab:hover {
                    color: var(--dc-primary);
                    border-color: #bdd0ef;
                }

                .dc-admin-wrap .nav-tab.nav-tab-active,
                .dc-admin-wrap .nav-tab.nav-tab-active:focus,
                .dc-admin-wrap .nav-tab.nav-tab-active:focus:active,
                .dc-admin-wrap .nav-tab.nav-tab-active:hover {
                    border-color: transparent;
                    background: linear-gradient(135deg, #145dc9 0%, #0f4aa3 100%);
                    color: #ffffff;
                }

                .dc-tab-panel {
                    display: none;
                    margin-top: 12px;
                    background: var(--dc-card);
                    border: 1px solid var(--dc-border);
                    border-radius: 14px;
                    padding: 20px 22px 22px;
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

                @media (max-width: 782px) {
                    .dc-admin-wrap {
                        padding: 12px;
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
                    width: 120px;
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
                    width: 120px;
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
            </style>

            <div class="dc-admin-tabs">
                <h2 class="nav-tab-wrapper" style="margin-bottom:0;">
                    <button type="button" class="nav-tab" data-dc-tab-btn="tab_setup">Credenciales</button>
                    <button type="button" class="nav-tab" data-dc-tab-btn="tab_catalog">Catálogo y alta</button>
                    <button type="button" class="nav-tab" data-dc-tab-btn="tab_saved">Bundles guardados</button>
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
                        <th scope="row">Wizard paso a paso</th>
                        <td>
                            <label>
                                <input type="checkbox" name="dc_recargas_options[wizard_enabled]" value="1" <?php checked(!empty($options['wizard_enabled'])); ?>>
                                Activar flujo wizard para landings y sesiones recuperables.
                            </label>
                            <p class="description">Cuando está desactivado, el shortcode clásico sigue siendo la experiencia principal.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_wizard_max_offers">Máximo de ofertas por categoría</label></th>
                        <td>
                            <input type="number" id="dc_wizard_max_offers" name="dc_recargas_options[wizard_max_offers_per_category]" min="1" max="20" step="1" value="<?php echo esc_attr((int) ($options['wizard_max_offers_per_category'] ?? 6)); ?>">
                            <p class="description">Controla cuántas ofertas se muestran por categoría en el wizard (1-20).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_wizard_entry_mode">Modo de entrada predeterminado</label></th>
                        <td>
                            <select id="dc_wizard_entry_mode" name="dc_recargas_options[wizard_default_entry_mode]">
                                <option value="number_first" <?php selected(($options['wizard_default_entry_mode'] ?? 'number_first'), 'number_first'); ?>>Número primero (number_first)</option>
                                <option value="country_fixed" <?php selected(($options['wizard_default_entry_mode'] ?? 'number_first'), 'country_fixed'); ?>>País fijo (country_fixed)</option>
                            </select>
                            <p class="description">Define cómo inicia el wizard: por número de teléfono o por país preseleccionado.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_wizard_fixed_prefix">Prefijo fijo de país</label></th>
                        <td>
                            <input type="text" id="dc_wizard_fixed_prefix" name="dc_recargas_options[wizard_fixed_prefix]" class="small-text" value="<?php echo esc_attr((string) ($options['wizard_fixed_prefix'] ?? '')); ?>" placeholder="ej: 53">
                            <p class="description">Solo dígitos. Se antepone al número en modo <code>country_fixed</code>. Dejar vacío si no aplica.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_wizard_checkout_mapping_mode">Mapeo de teléfono en checkout</label></th>
                        <td>
                            <select id="dc_wizard_checkout_mapping_mode" name="dc_recargas_options[wizard_checkout_mapping_mode]">
                                <option value="both" <?php selected(($options['wizard_checkout_mapping_mode'] ?? 'both'), 'both'); ?>>Ambos (beneficiario + comprador)</option>
                                <option value="beneficiary_only" <?php selected(($options['wizard_checkout_mapping_mode'] ?? 'both'), 'beneficiary_only'); ?>>Solo beneficiario</option>
                                <option value="buyer_only" <?php selected(($options['wizard_checkout_mapping_mode'] ?? 'both'), 'buyer_only'); ?>>Solo comprador</option>
                            </select>
                            <p class="description">Define si el wizard guarda solo teléfono del beneficiario, solo del comprador o ambos.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_wizard_checkout_beneficiary_key">Meta key teléfono beneficiario</label></th>
                        <td>
                            <input type="text" id="dc_wizard_checkout_beneficiary_key" name="dc_recargas_options[wizard_checkout_beneficiary_meta_key]" class="regular-text" value="<?php echo esc_attr((string) ($options['wizard_checkout_beneficiary_meta_key'] ?? '_dc_beneficiary_phone')); ?>">
                            <p class="description">Meta key que usará WooCommerce para guardar el teléfono del beneficiario cuando aplique.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_wizard_retry_attempts">Reintentos automáticos por transferencia</label></th>
                        <td>
                            <input type="number" id="dc_wizard_retry_attempts" name="dc_recargas_options[wizard_transfer_retry_attempts]" min="0" max="5" step="1" value="<?php echo esc_attr((int) ($options['wizard_transfer_retry_attempts'] ?? 2)); ?>">
                            <p class="description">Cantidad de reintentos automáticos para errores transitorios después de pago exitoso (0-5).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_wizard_retry_delay">Espera entre reintentos (minutos)</label></th>
                        <td>
                            <input type="number" id="dc_wizard_retry_delay" name="dc_recargas_options[wizard_transfer_retry_delay_minutes]" min="1" max="240" step="1" value="<?php echo esc_attr((int) ($options['wizard_transfer_retry_delay_minutes'] ?? 15)); ?>">
                            <p class="description">Minutos de espera antes de ejecutar el próximo intento automático.</p>
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

            <hr>
            <h2>Uso en frontend</h2>
            <p>Crea una página y coloca el shortcode:</p>
            <p><code>[dingconnect_recargas]</code></p>

                </section>

                <section id="dc-tab-catalog" class="dc-tab-panel" data-dc-tab-panel="tab_catalog">

            <h3>Método 1: Buscar en catálogo CSV</h3>
            <p>Busca en todo el catálogo, selecciona un producto y crea el bundle automáticamente.</p>

            <h4>Catálogo de productos (Products-with-sku.csv)</h4>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="dc_upload_csv">
                <?php wp_nonce_field('dc_upload_csv'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="dc_csv_file"><?php echo $csv_found ? 'Actualizar CSV' : 'Importar CSV'; ?></label></th>
                        <td>
                            <?php if ($csv_found) : ?>
                                <div class="notice notice-success inline" style="margin:0 0 10px"><p>Catálogo cargado correctamente. Última modificación: <strong><?php echo esc_html(date('d/m/Y H:i', filemtime($csv_path))); ?></strong></p></div>
                            <?php else : ?>
                                <div class="notice notice-warning inline" style="margin:0 0 10px"><p>No hay catálogo cargado. Sube el archivo para habilitar la búsqueda de productos.</p></div>
                            <?php endif; ?>
                            <input type="file" id="dc_csv_file" name="csv_file" accept=".csv">
                            <p class="description">Sube el archivo <strong>Products-with-sku.csv</strong> exportado desde <a href="https://www.dingconnect.com/en-US/PricingAndPromotions/Products" target="_blank">DingConnect &rarr; Pricing &amp; Promotions &rarr; Products</a>. Puedes actualizarlo en cualquier momento.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button($csv_found ? 'Actualizar catálogo' : 'Importar catálogo', 'secondary', 'submit', false); ?>
            </form>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="dc_csv_query">Buscar producto</label></th>
                    <td>
                        <input type="text" id="dc_csv_query" class="regular-text" placeholder="SKU, operador, país, descripción...">
                        <select id="dc_csv_country" class="regular-text">
                            <option value="">Todos los países</option>
                            <?php foreach ($csv_countries as $country_name) : ?>
                                <option value="<?php echo esc_attr($country_name); ?>"><?php echo esc_html($country_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="button" id="dc_csv_search_btn">Buscar</button>
                        <p class="description">Consejo: escribe al menos 3 caracteres para resultados más precisos.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="dc_csv_results">Resultados</label></th>
                    <td>
                        <select id="dc_csv_results" size="10" class="large-text"></select>
                        <p class="description" id="dc_csv_help">Cargando resultados iniciales...</p>
                        <p>
                            <label>
                                <input type="checkbox" id="dc_csv_auto_active" checked>
                                Publicar bundle inmediatamente (activo)
                            </label>
                        </p>
                        <p>
                            <button type="button" class="button button-primary" id="dc_csv_create_btn">Crear bundle automáticamente desde selección</button>
                        </p>
                    </td>
                </tr>
            </table>

            <hr>
            <h3>Buscar paquetes en la API de DingConnect por país</h3>
            <p class="description">Consulta los paquetes disponibles directamente desde DingConnect. No necesitas el CSV; los resultados se traen en tiempo real y se guardan en caché por 10 minutos.</p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="dc_api_country_iso">País (código ISO)</label></th>
                    <td>
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
                    <th scope="row"><label for="dc_api_results">Paquetes encontrados</label></th>
                    <td>
                        <select id="dc_api_results" size="10" class="large-text"></select>
                        <p class="description" id="dc_api_help">Selecciona un país y haz clic en «Buscar en API».</p>
                        <p>
                            <label>
                                <input type="checkbox" id="dc_api_auto_active" checked>
                                Publicar bundle inmediatamente (activo)
                            </label>
                        </p>
                        <p>
                            <button type="button" class="button button-primary" id="dc_api_create_btn" disabled>Crear bundle desde API</button>
                        </p>
                    </td>
                </tr>
            </table>
            <script>
            (function () {
                var apiNonce     = <?php echo wp_json_encode($csv_nonce); ?>;
                var apiCountryEl = document.getElementById('dc_api_country_iso');
                var apiFetchBtn  = document.getElementById('dc_api_fetch_btn');
                var apiResultsEl = document.getElementById('dc_api_results');
                var apiHelpEl    = document.getElementById('dc_api_help');
                var apiActiveEl  = document.getElementById('dc_api_auto_active');
                var apiCreateBtn = document.getElementById('dc_api_create_btn');
                var apiSelected  = null;

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

                function renderApiResults(items) {
                    apiResultsEl.innerHTML = '';
                    apiSelected = null;
                    apiCreateBtn.disabled = true;

                    if (!items || items.length === 0) {
                        apiHelpEl.textContent = 'No se encontraron paquetes para este país en la API.';
                        return;
                    }

                    items.forEach(function (item) {
                        var opt = document.createElement('option');
                        opt.value = item.sku_code;
                        opt.textContent = apiOptionLabel(item);
                        opt.dataset.item = JSON.stringify(item);
                        apiResultsEl.appendChild(opt);
                    });

                    apiHelpEl.textContent = items.length + ' paquete(s) encontrado(s). Selecciona uno para crear el bundle.';
                }

                apiFetchBtn.addEventListener('click', function () {
                    var iso = apiCountryEl.value;
                    if (!iso) {
                        apiHelpEl.textContent = 'Selecciona un país antes de buscar.';
                        return;
                    }

                    apiFetchBtn.disabled = true;
                    apiHelpEl.textContent = 'Consultando la API de DingConnect...';
                    apiResultsEl.innerHTML = '';
                    apiSelected = null;
                    apiCreateBtn.disabled = true;

                    var url = ajaxurl + '?action=dc_fetch_api_products&nonce=' + encodeURIComponent(apiNonce) + '&country_iso=' + encodeURIComponent(iso);

                    fetch(url, { credentials: 'same-origin' })
                        .then(function (res) { return res.json(); })
                        .then(function (data) {
                            apiFetchBtn.disabled = false;
                            if (data.success) {
                                renderApiResults(data.data.items);
                            } else {
                                apiHelpEl.textContent = 'Error: ' + (data.data && data.data.message ? data.data.message : 'No se pudo conectar a la API.');
                            }
                        })
                        .catch(function () {
                            apiFetchBtn.disabled = false;
                            apiHelpEl.textContent = 'Error de red al consultar la API.';
                        });
                });

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

                apiCreateBtn.addEventListener('click', function () {
                    if (!apiSelected) { return; }

                    apiCreateBtn.disabled = true;
                    apiHelpEl.textContent = 'Creando bundle...';

                    var body = new URLSearchParams({
                        action: 'dc_create_bundle_from_csv',
                        nonce: apiNonce,
                        country_iso: apiSelected.country_iso,
                        sku_code: apiSelected.sku_code,
                        operator: apiSelected.operator,
                        receive: apiSelected.receive || apiSelected.label,
                        send_value: apiSelected.send_value,
                        send_currency_iso: apiSelected.send_currency_iso,
                        is_active: apiActiveEl.checked ? '1' : '0',
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
                                apiHelpEl.textContent = 'Bundle «' + bundleLabel + '» creado correctamente.';
                                var opts = apiResultsEl.options;
                                for (var i = opts.length - 1; i >= 0; i--) {
                                    if (opts[i].value === apiSelected.sku_code) {
                                        apiResultsEl.remove(i);
                                        break;
                                    }
                                }
                                apiSelected = null;
                            } else {
                                apiHelpEl.textContent = 'Error: ' + (data.data && data.data.message ? data.data.message : 'No se pudo crear el bundle.');
                            }
                        })
                        .catch(function () {
                            apiCreateBtn.disabled = false;
                            apiHelpEl.textContent = 'Error de red al crear el bundle.';
                        });
                });
            })();
            </script>

            <h2>Añadir bundle curado</h2>
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
            <?php foreach ($dl_country_iso as $v) : ?>
                <option value="<?php echo esc_attr($v); ?>">
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
                var searchBtn = document.getElementById('dc_csv_search_btn');
                var queryEl = document.getElementById('dc_csv_query');
                var countryEl = document.getElementById('dc_csv_country');
                var resultsEl = document.getElementById('dc_csv_results');
                var helpEl = document.getElementById('dc_csv_help');
                var createBtn = document.getElementById('dc_csv_create_btn');
                var autoActiveEl = document.getElementById('dc_csv_auto_active');
                var autoSearchTimer = null;
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
                var editIdEl = document.getElementById('dc_edit_bundle_id');
                var editCountryIsoEl = document.getElementById('dc_edit_country_iso');
                var editLabelEl = document.getElementById('dc_edit_label');
                var editSkuEl = document.getElementById('dc_edit_sku_code');
                var editSendValueEl = document.getElementById('dc_edit_send_value');
                var editSendCurrencyEl = document.getElementById('dc_edit_send_currency_iso');
                var editProviderEl = document.getElementById('dc_edit_provider_name');
                var editDescriptionEl = document.getElementById('dc_edit_description');
                var editIsActiveEl = document.getElementById('dc_edit_is_active');
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

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && editModalEl && !editModalEl.hidden) {
                        closeEditModal();
                    }
                });

                if (initialEditingBundle && initialEditingBundle.id) {
                    openEditModal(initialEditingBundle);
                }

                var countryIsoEl = document.getElementById('dc_country_iso');
                var labelEl = document.getElementById('dc_label');
                var skuEl = document.getElementById('dc_sku_code');
                var sendValueEl = document.getElementById('dc_send_value');
                var sendCurrencyEl = document.getElementById('dc_send_currency_iso');
                var providerEl = document.getElementById('dc_provider_name');
                var descriptionEl = document.getElementById('dc_description');

                if (!searchBtn || !queryEl || !resultsEl) {
                    return;
                }

                function optionLabel(item) {
                    return '[' + (item.country_iso || '--') + '] ' + item.operator + ' | ' + item.send_amount + ' | ' + item.receive + ' | ' + item.sku_code;
                }

                function fillForm(item) {
                    if (countryIsoEl) countryIsoEl.value = item.country_iso || '';
                    if (labelEl) labelEl.value = (item.operator || 'Producto') + ' - ' + (item.receive || item.sku_code || '');
                    if (skuEl) skuEl.value = item.sku_code || '';
                    if (sendValueEl) sendValueEl.value = item.send_value || '';
                    if (sendCurrencyEl) sendCurrencyEl.value = item.send_currency_iso || 'EUR';
                    if (providerEl) providerEl.value = item.operator || '';
                    if (descriptionEl) descriptionEl.value = item.receive || '';
                }

                function renderResults(items) {
                    resultsEl.innerHTML = '';

                    if (!items || !items.length) {
                        var none = document.createElement('option');
                        none.value = '';
                        none.textContent = 'Sin resultados para el filtro actual.';
                        resultsEl.appendChild(none);
                        helpEl.textContent = 'No se encontraron coincidencias.';
                        if (createBtn) createBtn.disabled = true;
                        return;
                    }

                    items.forEach(function (item) {
                        var opt = document.createElement('option');
                        opt.value = item.sku_code;
                        opt.textContent = optionLabel(item);
                        opt.dataset.item = JSON.stringify(item);
                        resultsEl.appendChild(opt);
                    });

                    helpEl.textContent = 'Se encontraron ' + items.length + ' resultados. Selecciona uno para cargarlo en el formulario.';
                    if (createBtn) createBtn.disabled = false;
                }

                async function createBundleFromSelection() {
                    var selected = resultsEl.options[resultsEl.selectedIndex];
                    if (!selected || !selected.dataset.item) {
                        helpEl.textContent = 'Selecciona primero un producto del listado.';
                        return;
                    }

                    var item;
                    try {
                        item = JSON.parse(selected.dataset.item);
                    } catch (e) {
                        helpEl.textContent = 'No se pudo leer el resultado seleccionado.';
                        return;
                    }

                    if (!createBtn) {
                        return;
                    }

                    createBtn.disabled = true;
                    createBtn.textContent = 'Creando...';

                    try {
                        var body = new URLSearchParams();
                        body.append('action', 'dc_create_bundle_from_csv');
                        body.append('nonce', '<?php echo esc_js($csv_nonce); ?>');
                        body.append('country_iso', item.country_iso || '');
                        body.append('sku_code', item.sku_code || '');
                        body.append('operator', item.operator || '');
                        body.append('receive', item.receive || '');
                        body.append('send_value', item.send_value || 0);
                        body.append('send_currency_iso', item.send_currency_iso || 'EUR');
                        body.append('is_active', (autoActiveEl && autoActiveEl.checked) ? '1' : '0');

                        var response = await fetch(ajaxurl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                            },
                            body: body.toString()
                        });

                        var data = await response.json();
                        if (!data || !data.success) {
                            var errorMsg = (data && data.data && data.data.message) ? data.data.message : 'No se pudo crear el bundle.';
                            throw new Error(errorMsg);
                        }

                        fillForm(item);
                        helpEl.textContent = 'Bundle creado correctamente. Recargando lista de bundles...';
                        window.location.href = '<?php echo esc_url(add_query_arg(['page' => 'dc-recargas', 'dc_msg' => 'bundle_added'], admin_url('admin.php'))); ?>';
                    } catch (err) {
                        helpEl.textContent = err.message || 'Error al crear bundle automático.';
                    } finally {
                        createBtn.disabled = false;
                        createBtn.textContent = 'Crear bundle automáticamente desde selección';
                    }
                }

                async function doSearch() {
                    var query = (queryEl.value || '').trim();
                    var country = countryEl ? countryEl.value : '';

                    searchBtn.disabled = true;
                    searchBtn.textContent = 'Buscando...';
                    helpEl.textContent = 'Consultando catálogo...';

                    try {
                        var url = ajaxurl + '?action=dc_search_csv_products'
                            + '&nonce=' + encodeURIComponent('<?php echo esc_js($csv_nonce); ?>')
                            + '&q=' + encodeURIComponent(query)
                            + '&country=' + encodeURIComponent(country);

                        var response = await fetch(url);
                        var data = await response.json();

                        if (!data || !data.success) {
                            throw new Error('No se pudo leer el catálogo CSV.');
                        }

                        renderResults((data.data && data.data.items) ? data.data.items : []);
                    } catch (err) {
                        resultsEl.innerHTML = '';
                        var errorOpt = document.createElement('option');
                        errorOpt.value = '';
                        errorOpt.textContent = 'Error al consultar catálogo CSV.';
                        resultsEl.appendChild(errorOpt);
                        helpEl.textContent = err.message || 'Error inesperado.';
                    } finally {
                        searchBtn.disabled = false;
                        searchBtn.textContent = 'Buscar';
                    }
                }

                async function loadInitialResults() {
                    searchBtn.disabled = true;
                    searchBtn.textContent = 'Cargando...';
                    helpEl.textContent = 'Cargando resultados de países principales...';

                    try {
                        var url = ajaxurl + '?action=dc_search_csv_products'
                            + '&nonce=' + encodeURIComponent('<?php echo esc_js($csv_nonce); ?>')
                            + '&initial=1';

                        var response = await fetch(url);
                        var data = await response.json();

                        if (!data || !data.success) {
                            throw new Error('No se pudo leer el catálogo CSV.');
                        }

                        var payload = data.data || {};
                        if (!payload.csv_found) {
                            renderResults([]);
                            helpEl.textContent = 'No hay ningún CSV cargado. Importa Products-with-sku.csv para ver resultados.';
                            if (createBtn) createBtn.disabled = true;
                            return;
                        }

                        renderResults(payload.items || []);
                        if (payload.items && payload.items.length) {
                            helpEl.textContent = 'Se cargaron los primeros 10 resultados de países principales.';
                        }
                    } catch (err) {
                        resultsEl.innerHTML = '';
                        var errorOpt = document.createElement('option');
                        errorOpt.value = '';
                        errorOpt.textContent = 'Error al cargar resultados iniciales.';
                        resultsEl.appendChild(errorOpt);
                        helpEl.textContent = err.message || 'Error inesperado.';
                        if (createBtn) createBtn.disabled = true;
                    } finally {
                        searchBtn.disabled = false;
                        searchBtn.textContent = 'Buscar';
                    }
                }

                searchBtn.addEventListener('click', doSearch);
                queryEl.addEventListener('input', function () {
                    if (autoSearchTimer) {
                        clearTimeout(autoSearchTimer);
                    }

                    // Debounce to avoid firing one request per keystroke.
                    autoSearchTimer = setTimeout(function () {
                        doSearch();
                    }, 300);
                });

                if (countryEl) {
                    countryEl.addEventListener('change', doSearch);
                }

                resultsEl.addEventListener('change', function () {
                    var selected = resultsEl.options[resultsEl.selectedIndex];
                    if (!selected || !selected.dataset.item) {
                        return;
                    }

                    try {
                        fillForm(JSON.parse(selected.dataset.item));
                    } catch (e) {
                        helpEl.textContent = 'No se pudo interpretar el producto seleccionado.';
                    }
                });

                if (createBtn) {
                    createBtn.disabled = true;
                    createBtn.addEventListener('click', createBundleFromSelection);
                }

                loadInitialResults();
            })();

            // -- Datalist sync: ensure new values typed in any field are added
            // -- to the shared datalist so other bundles see them immediately.
            (function () {
                var datalistMap = {
                    dc_dl_country_iso: ['dc_country_iso', 'dc_edit_country_iso'],
                    dc_dl_label: ['dc_label', 'dc_edit_label'],
                    dc_dl_send_currency: ['dc_send_currency_iso', 'dc_edit_send_currency_iso'],
                    dc_dl_provider_name: ['dc_provider_name', 'dc_edit_provider_name']
                };

                var comboboxRegistry = [];

                function addToDatalist(datalistId, value) {
                    if (!value || !value.trim()) return;
                    value = value.trim();
                    var dl = document.getElementById(datalistId);
                    if (!dl) return;
                    var opts = dl.querySelectorAll('option');
                    for (var i = 0; i < opts.length; i++) {
                        if (opts[i].value === value) return; // already exists
                    }
                    var opt = document.createElement('option');
                    opt.value = value;
                    dl.appendChild(opt);

                    // Refresh open combobox menus that depend on this datalist.
                    comboboxRegistry.forEach(function (combo) {
                        if (combo.datalistId === datalistId && combo.wrap.classList.contains('is-open')) {
                            renderMenu(combo, combo.input.value || '');
                        }
                    });
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
                        if (!v) {
                            return;
                        }
                        var key = v.toLowerCase();
                        if (!seen[key]) {
                            seen[key] = true;
                            options.push(v);
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
                        return !term || item.toLowerCase().indexOf(term) !== -1;
                    });

                    combo.menu.innerHTML = '';
                    if (!filtered.length) {
                        combo.wrap.classList.remove('is-open');
                        return;
                    }

                    filtered.forEach(function (value) {
                        var item = document.createElement('div');
                        item.className = 'dc-combo-option';
                        item.textContent = value;
                        item.addEventListener('mousedown', function (ev) {
                            ev.preventDefault();
                            combo.input.value = value;
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
        </script>
        <?php
    }

    private function get_csv_countries() {
        $path = $this->get_products_csv_path();
        if (empty($path)) {
            return [];
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            return [];
        }

        $header = fgetcsv($handle);
        if (!is_array($header)) {
            fclose($handle);
            return [];
        }

        $indexes = array_flip($header);
        if (!isset($indexes['Country'])) {
            fclose($handle);
            return [];
        }

        $countries_map = [];
        while (($row = fgetcsv($handle)) !== false) {
            $country_name = trim((string) ($row[$indexes['Country']] ?? ''));
            if ($country_name === '') {
                continue;
            }

            $countries_map[strtoupper($country_name)] = $country_name;
        }

        fclose($handle);

        if (empty($countries_map)) {
            return [];
        }

        natcasesort($countries_map);
        return array_values($countries_map);
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
            'bundle_error' => ['error', 'Completa País ISO, Nombre y SKU para añadir un bundle.'],
            'bundle_duplicate' => ['error', 'Ya existe otro bundle con el mismo país y SKU.'],
            'bundle_not_found' => ['error', 'No se encontró el bundle solicitado.'],
            'csv_uploaded' => ['success', 'Archivo Products-with-sku.csv importado correctamente. Ya puedes buscar productos.'],
            'csv_upload_error' => ['error', 'No se pudo importar el archivo CSV. Verifica que sea un archivo .csv válido.'],
            'csv_no_file' => ['error', 'No se seleccionó ningún archivo para importar.'],
        ];

        if (!isset($map[$msg])) {
            return;
        }

        [$class, $text] = $map[$msg];
        echo '<div class="notice notice-' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($text) . '</p></div>';
    }

    private function get_products_csv_path() {
        $upload_dir = wp_upload_dir();
        $path = trailingslashit($upload_dir['basedir']) . 'dingconnect/Products-with-sku.csv';

        if (file_exists($path) && is_readable($path)) {
            return $path;
        }

        return '';
    }

    private function search_products_csv($query, $country, $limit = 120) {
        $path = $this->get_products_csv_path();
        if (empty($path)) {
            return [];
        }

        $query = strtolower(trim((string) $query));
        $country = trim((string) $country);
        $limit = max(1, min((int) $limit, 300));

        $results = [];
        $handle = fopen($path, 'r');
        if (!$handle) {
            return [];
        }

        $header = fgetcsv($handle);
        if (!is_array($header)) {
            fclose($handle);
            return [];
        }

        $indexes = array_flip($header);
        $required = ['Operator', 'Country', 'Send amount', 'Receive', 'Product type', 'Validity', 'SkuCode'];
        foreach ($required as $key) {
            if (!isset($indexes[$key])) {
                fclose($handle);
                return [];
            }
        }

        while (($row = fgetcsv($handle)) !== false) {
            $operator = trim((string) ($row[$indexes['Operator']] ?? ''));
            $country_name = trim((string) ($row[$indexes['Country']] ?? ''));
            $send_amount = trim((string) ($row[$indexes['Send amount']] ?? ''));
            $receive = trim((string) ($row[$indexes['Receive']] ?? ''));
            $product_type = trim((string) ($row[$indexes['Product type']] ?? ''));
            $validity = trim((string) ($row[$indexes['Validity']] ?? ''));
            $sku_code = trim((string) ($row[$indexes['SkuCode']] ?? ''));

            if (empty($sku_code) || empty($operator)) {
                continue;
            }

            if (!empty($country) && strcasecmp($country_name, $country) !== 0) {
                continue;
            }

            if (!empty($query)) {
                $haystack = strtolower(implode(' ', [$operator, $country_name, $send_amount, $receive, $product_type, $validity, $sku_code]));
                if (strpos($haystack, $query) === false) {
                    continue;
                }
            }

            $country_iso = $this->country_name_to_iso($country_name);
            $send_value = $this->extract_first_number($send_amount);
            $send_currency = $this->extract_currency_from_send_amount($send_amount);

            $results[] = [
                'operator' => $operator,
                'country' => $country_name,
                'country_iso' => $country_iso,
                'send_amount' => $send_amount,
                'send_value' => $send_value,
                'send_currency_iso' => $send_currency,
                'receive' => $receive,
                'product_type' => $product_type,
                'validity' => $validity,
                'sku_code' => $sku_code,
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        fclose($handle);
        return $results;
    }

    private function search_products_csv_main_countries($limit = 10) {
        $path = $this->get_products_csv_path();
        if (empty($path)) {
            return [];
        }

        $limit = max(1, min((int) $limit, 100));
        $main_countries = [
            'COLOMBIA' => true,
            'SPAIN' => true,
            'MEXICO' => true,
            'CUBA' => true,
        ];

        $results = [];
        $handle = fopen($path, 'r');
        if (!$handle) {
            return [];
        }

        $header = fgetcsv($handle);
        if (!is_array($header)) {
            fclose($handle);
            return [];
        }

        $indexes = array_flip($header);
        $required = ['Operator', 'Country', 'Send amount', 'Receive', 'Product type', 'Validity', 'SkuCode'];
        foreach ($required as $key) {
            if (!isset($indexes[$key])) {
                fclose($handle);
                return [];
            }
        }

        while (($row = fgetcsv($handle)) !== false) {
            $operator = trim((string) ($row[$indexes['Operator']] ?? ''));
            $country_name = trim((string) ($row[$indexes['Country']] ?? ''));
            $send_amount = trim((string) ($row[$indexes['Send amount']] ?? ''));
            $receive = trim((string) ($row[$indexes['Receive']] ?? ''));
            $product_type = trim((string) ($row[$indexes['Product type']] ?? ''));
            $validity = trim((string) ($row[$indexes['Validity']] ?? ''));
            $sku_code = trim((string) ($row[$indexes['SkuCode']] ?? ''));

            if (empty($sku_code) || empty($operator)) {
                continue;
            }

            $country_key = strtoupper($country_name);
            if (!isset($main_countries[$country_key])) {
                continue;
            }

            $results[] = [
                'operator' => $operator,
                'country' => $country_name,
                'country_iso' => $this->country_name_to_iso($country_name),
                'send_amount' => $send_amount,
                'send_value' => $this->extract_first_number($send_amount),
                'send_currency_iso' => $this->extract_currency_from_send_amount($send_amount),
                'receive' => $receive,
                'product_type' => $product_type,
                'validity' => $validity,
                'sku_code' => $sku_code,
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        fclose($handle);
        return $results;
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
