<?php
if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('DC_Recargas_WooCommerce')) {
    return;
}

/**
 * WooCommerce integration for DingConnect Recargas.
 *
 * Handles: virtual product management, cart customization, checkout enforcement,
 * phone-based login, and order processing (real DingConnect transfer on payment).
 *
 * IMPORTANT: For real transfers to execute after payment, the admin must enable
 * "allow_real_recharge" in plugin settings (DingConnect Recargas > Ajustes).
 * Without it, all transfers will be validate-only regardless of WooCommerce flow.
 */
class DC_Recargas_WooCommerce {

    /** @var DC_Recargas_API */
    private $api;

    public function __construct($api) {
        $this->api = $api;

        // Add-to-cart is handled by DC_Recargas_REST, which delegates here via filter

        // Compatibility mitigation: initialize PHP session early on checkout requests
        // when third-party gateways (e.g., Tropipay) start sessions too late.
        add_action('init', [$this, 'stabilize_checkout_php_session_compat'], 0);

        // Initialize WC cart/session early for REST requests (must be on woocommerce_init, not inside the callback)
        add_action('woocommerce_init', [$this, 'maybe_load_cart_for_rest']);

        // Add-to-cart filter (delegated from REST class)
        add_filter('dc_recargas_add_to_cart', [$this, 'handle_add_to_cart'], 10, 2);

        // Cart customization
        add_action('woocommerce_before_calculate_totals', [$this, 'set_custom_price'], 20, 1);
        add_filter('woocommerce_get_item_data', [$this, 'display_cart_item_data'], 10, 2);
        add_filter('woocommerce_cart_item_name', [$this, 'custom_cart_item_name'], 10, 3);
        add_filter('woocommerce_cart_item_thumbnail', [$this, 'suppress_recarga_cart_item_thumbnail'], 10, 3);
        add_filter('woocommerce_is_purchasable', [$this, 'force_recarga_product_purchasable'], 10, 2);
        add_filter('woocommerce_cart_item_is_purchasable', [$this, 'force_recarga_cart_item_purchasable'], 10, 3);
        add_filter('woocommerce_cart_item_is_in_stock', [$this, 'force_recarga_cart_item_in_stock'], 10, 3);
        add_filter('woocommerce_add_error', [$this, 'suppress_generic_cart_issue_error_for_dc_only'], 10, 1);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'restore_recarga_cart_item_from_session'], 20, 3);
        add_action('woocommerce_cart_loaded_from_session', [$this, 'normalize_dc_cart_after_session_load'], 20);
        add_action('woocommerce_check_cart_items', [$this, 'diagnose_dc_cart_item_issues'], 1);
        add_action('woocommerce_check_cart_items', [$this, 'cleanup_dc_only_generic_cart_notice_late'], 999);
        add_action('woocommerce_before_checkout_form', [$this, 'cleanup_dc_only_generic_cart_notice_late'], 1);
        add_action('woocommerce_checkout_process', [$this, 'cleanup_dc_only_generic_cart_notice_late'], 1);

        // Order meta
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_order_item_meta'], 10, 4);

        // Redirect DC-only cart directly to checkout (skip cart page)
        add_action('template_redirect', [$this, 'redirect_dc_only_cart_to_checkout'], 5);

        // Mandatory registration (only when cart is mixed DC + regular products)
        add_filter('woocommerce_checkout_registration_required', [$this, 'force_registration']);
        add_filter('pre_option_woocommerce_enable_guest_checkout', [$this, 'disable_guest_checkout']);

        // Checkout fields + address validation bypass for DC-only
        add_filter('woocommerce_checkout_fields', [$this, 'customize_checkout_fields']);
        add_filter('woocommerce_new_customer_data', [$this, 'prefill_customer_phone']);
        add_action('woocommerce_after_checkout_validation', [$this, 'clear_address_validation_errors_for_dc'], 10, 2);

        // Phone login
        add_filter('authenticate', [$this, 'authenticate_by_phone'], 30, 3);

        // Order processing - fire DingConnect transfer on payment
        // Three hooks to cover all gateway patterns:
        // - payment_complete: Stripe, PayPal IPN and other gateways that call $order->payment_complete() directly
        // - status_processing: gateways that transition to "processing" after payment
        // - status_completed: gateways that go directly to "completed" (e.g., free/manual orders)
        add_action('woocommerce_payment_complete', [$this, 'process_recarga_on_payment']);
        add_action('woocommerce_order_status_processing', [$this, 'process_recarga_on_payment']);
        add_action('woocommerce_order_status_completed', [$this, 'process_recarga_on_payment']);
        add_action('dc_recargas_retry_transfer', [$this, 'process_retry_transfer'], 10, 2);

        // Restrict checkout payment gateways for recarga carts
        add_filter('woocommerce_payment_gateways', [$this, 'filter_gateway_classes_for_dc_checkout'], 1);
        add_filter('woocommerce_available_payment_gateways', [$this, 'filter_available_payment_gateways'], 20);

        // Optional UI guard: hide Advanced Coupons store credit block in DC-only checkout
        add_action('wp_footer', [$this, 'maybe_hide_acfw_store_credit_ui'], 99);

        // Manual reconciliation + voucher rendering
        add_filter('woocommerce_order_actions', [$this, 'register_manual_reconcile_action']);
        add_action('woocommerce_order_action_dc_recargas_manual_reconcile', [$this, 'handle_manual_reconcile_action']);
        add_action('woocommerce_thankyou', [$this, 'render_thankyou_voucher_summary'], 25);
        add_filter('woocommerce_email_order_meta_fields', [$this, 'inject_voucher_meta_into_email'], 10, 3);

        // Admin order display
        add_action('woocommerce_after_order_itemmeta', [$this, 'display_order_item_recarga_meta'], 10, 3);
    }

    /* ---------------------------------------------------------------
     * 1. Base Product Management
     * ------------------------------------------------------------- */

    private function get_or_create_base_product() {
        $product_id = (int) get_option('dc_recargas_wc_product_id', 0);

        if ($product_id && get_post_status($product_id) === 'publish') {
            $existing = wc_get_product($product_id);
            if ($existing instanceof WC_Product_Simple) {
                // Keep base recarga product always purchasable for checkout/cart validation.
                $existing->set_catalog_visibility('hidden');
                $existing->set_virtual(true);
                $existing->set_sold_individually(false);
                $existing->set_regular_price('0');
                $existing->save();
            }

            // Stock flags via meta to stay compatible with local WP stubs.
            update_post_meta($product_id, '_manage_stock', 'no');
            update_post_meta($product_id, '_stock_status', 'instock');
            update_post_meta($product_id, '_backorders', 'no');
            delete_post_meta($product_id, '_thumbnail_id');
            delete_post_meta($product_id, '_product_image_gallery');

            return $product_id;
        }

        $product = new WC_Product_Simple();
        $product->set_name('Recarga Internacional DingConnect');
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_regular_price('0');
        $product->set_virtual(true);
        $product->set_sold_individually(false);
        $product_id = (int) $product->save();

        update_post_meta($product_id, '_manage_stock', 'no');
        update_post_meta($product_id, '_stock_status', 'instock');
        update_post_meta($product_id, '_backorders', 'no');
        delete_post_meta($product_id, '_thumbnail_id');
        delete_post_meta($product_id, '_product_image_gallery');

        update_option('dc_recargas_wc_product_id', $product_id);
        return $product_id;
    }

    /* ---------------------------------------------------------------
     * 2. WC Session Helper (critical for REST + WC cart)
     * ------------------------------------------------------------- */

    /**
     * Load WC cart/session for REST requests.
     * Must run on woocommerce_init (early), not inside the REST callback.
     */
    public function maybe_load_cart_for_rest() {
        if (!defined('REST_REQUEST') || !REST_REQUEST) return;
        if (function_exists('wc_load_cart')) {
            wc_load_cart();
        }
    }

    /**
     * Defensive session/cart bootstrap for REST add-to-cart.
     * Some hosting/plugin combinations skip cart bootstrap before REST callbacks.
     */
    private function ensure_wc_session() {
        if (!defined('REST_REQUEST') || !REST_REQUEST) return;

        if (function_exists('wc_load_cart') && (WC()->cart === null || WC()->session === null)) {
            wc_load_cart();
        }

        if (WC()->session === null) {
            WC()->session = new WC_Session_Handler();
            WC()->session->init();
        }

        if (WC()->customer === null) {
            WC()->customer = new WC_Customer(get_current_user_id());
        }

        if (WC()->cart === null) {
            WC()->cart = new WC_Cart();
            WC()->cart->get_cart();
        }
    }


    /* ---------------------------------------------------------------
     * 3. Add-to-Cart Handler (called via dc_recargas_add_to_cart filter)
     * ------------------------------------------------------------- */

    public function handle_add_to_cart($result, $data) {
        $this->ensure_wc_session();

        if (WC()->cart === null) {
            return new WP_Error('dc_cart_unavailable', 'No se pudo inicializar el carrito en este entorno.');
        }

        $product_type = sanitize_text_field((string) ($data['product_type'] ?? ''));
        $redemption_mechanism = sanitize_text_field((string) ($data['redemption_mechanism'] ?? ''));
        $lookup_bills_required = !empty($data['lookup_bills_required']);
        $customer_care_number = sanitize_text_field((string) ($data['customer_care_number'] ?? ''));
        $is_range = !empty($data['is_range']);
        $flow_kind = $this->infer_flow_kind(
            $product_type,
            $redemption_mechanism,
            $lookup_bills_required,
            $is_range,
            (string) ($data['bundle_label'] ?? '')
        );

        $product_id = $this->get_or_create_base_product();
        if (!$product_id) {
            return new WP_Error('dc_product_error', 'No se pudo crear el producto base de recarga.');
        }

        $cart_item_data = [
            'dc_recarga'          => true,
            'dc_account_number'   => $data['account_number'],
            'dc_country_iso'      => $data['country_iso'],
            'dc_sku_code'         => $data['sku_code'],
            'dc_send_value'       => $data['send_value'],
            'dc_send_currency_iso'=> $data['send_currency_iso'],
            'dc_public_price'     => (float) ($data['public_price'] ?? $data['send_value']),
            'dc_public_currency_iso' => (string) ($data['public_price_currency'] ?? $data['send_currency_iso']),
            'dc_provider_name'    => $data['provider_name'],
            'dc_bundle_label'     => $data['bundle_label'],
            'dc_bundle_benefit'   => $data['bundle_benefit'] ?? '',
            'dc_bundle_id'        => $data['bundle_id'] ?? '',
            'dc_product_type'     => $product_type,
            'dc_redemption_mechanism' => $redemption_mechanism,
            'dc_lookup_bills_required' => $lookup_bills_required,
            'dc_customer_care_number' => $customer_care_number,
            'dc_is_range'         => $is_range,
            'dc_flow_kind'        => $flow_kind,
            'dc_settings'         => is_array($data['settings'] ?? null) ? $data['settings'] : [],
            'dc_bill_ref'         => sanitize_text_field((string) ($data['bill_ref'] ?? '')),
            'unique_key'          => md5($data['account_number'] . $data['sku_code'] . microtime()),
        ];

        if (function_exists('wc_clear_notices')) {
            wc_clear_notices();
        }

        $added = WC()->cart->add_to_cart($product_id, 1, 0, [], $cart_item_data);

        if (!$added) {
            return new WP_Error('dc_cart_error', $this->build_add_to_cart_error_message($product_id));
        }

        return true;
    }

    private function build_add_to_cart_error_message($product_id) {
        $notice_messages = [];
        if (function_exists('wc_get_notices')) {
            $notices = wc_get_notices('error');
            if (is_array($notices)) {
                foreach ($notices as $notice) {
                    $raw = is_array($notice) ? (string) ($notice['notice'] ?? '') : (string) $notice;
                    $clean = trim(wp_strip_all_tags($raw));
                    if ($clean !== '') {
                        $notice_messages[] = $clean;
                    }
                }
            }
        }

        if (function_exists('wc_clear_notices')) {
            wc_clear_notices();
        }

        if (!empty($notice_messages)) {
            return implode(' | ', array_slice($notice_messages, 0, 2));
        }

        $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
        if (!$product) {
            return 'No se pudo añadir la recarga al carrito (producto base no disponible).';
        }

        if (!$product->is_purchasable()) {
            return 'No se pudo añadir la recarga al carrito (producto no comprable en este entorno).';
        }

        return 'No se pudo añadir la recarga al carrito.';
    }

    public function force_recarga_product_purchasable($is_purchasable, $product) {
        if (!$product || !is_object($product) || !method_exists($product, 'get_id')) {
            return $is_purchasable;
        }

        $recarga_product_id = (int) get_option('dc_recargas_wc_product_id', 0);
        if ($recarga_product_id > 0 && (int) $product->get_id() === $recarga_product_id) {
            return true;
        }

        return $is_purchasable;
    }

    public function force_recarga_cart_item_purchasable($is_purchasable, $values, $product) {
        if (!empty($values['dc_recarga'])) {
            return true;
        }
        return $is_purchasable;
    }

    public function force_recarga_cart_item_in_stock($is_in_stock, $values, $product) {
        if (!empty($values['dc_recarga'])) {
            return true;
        }
        return $is_in_stock;
    }

    public function restore_recarga_cart_item_from_session($session_data, $values, $cart_item_key) {
        $is_dc_recarga = !empty($session_data['dc_recarga']) || !empty($values['dc_recarga']);
        if (!$is_dc_recarga) {
            return $session_data;
        }

        $product_id = (int) ($session_data['product_id'] ?? 0);
        $product = $product_id > 0 ? wc_get_product($product_id) : null;
        if (!$product || !$product->exists()) {
            $product_id = $this->get_or_create_base_product();
            $product = $product_id > 0 ? wc_get_product($product_id) : null;
        }

        if ($product && $product->exists()) {
            $session_data['product_id'] = (int) $product->get_id();
            $session_data['variation_id'] = 0;
            $session_data['data'] = $product;
            $session_data['dc_recarga'] = true;
            if (empty($session_data['quantity']) || (int) $session_data['quantity'] < 1) {
                $session_data['quantity'] = 1;
            }
        }

        $this->hydrate_recarga_cart_item_data($session_data);

        return $session_data;
    }

    public function diagnose_dc_cart_item_issues() {
        if (!WC()->cart) {
            return;
        }

        $has_dc = false;
        $issues = [];
        $healed = false;

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if (empty($cart_item['dc_recarga'])) {
                continue;
            }

            $has_dc = true;
            $item_issues = [];
            $product = $cart_item['data'] ?? null;

            if (!$product || !is_object($product) || !method_exists($product, 'exists') || !$product->exists()) {
                $base_product_id = $this->get_or_create_base_product();
                $base_product = $base_product_id > 0 ? wc_get_product($base_product_id) : null;
                if ($base_product && $base_product->exists()) {
                    WC()->cart->cart_contents[$cart_item_key]['product_id'] = (int) $base_product->get_id();
                    WC()->cart->cart_contents[$cart_item_key]['variation_id'] = 0;
                    WC()->cart->cart_contents[$cart_item_key]['data'] = $base_product;
                    $product = $base_product;
                    $healed = true;
                } else {
                    $item_issues[] = 'missing_product';
                }
            }

            if (!$product || !is_object($product) || !method_exists($product, 'is_purchasable') || !$product->is_purchasable()) {
                $item_issues[] = 'not_purchasable';
            }

            if (!$product || !is_object($product) || !method_exists($product, 'is_in_stock') || !$product->is_in_stock()) {
                $item_issues[] = 'out_of_stock';
            }

            if (!empty($item_issues)) {
                $issues[] = [
                    'cart_item_key' => (string) $cart_item_key,
                    'product_id' => (int) ($cart_item['product_id'] ?? 0),
                    'sku_code' => (string) ($cart_item['dc_sku_code'] ?? ''),
                    'issues' => array_values(array_unique($item_issues)),
                ];
            }
        }

        if ($healed && method_exists(WC()->cart, 'set_session')) {
            WC()->cart->set_session();
        }

        if (!$has_dc) {
            return;
        }

        // Heartbeat log: confirms the checkout validator runs for DC carts in production.
        error_log('[DingConnect][checkout_cart_validation] ' . wp_json_encode([
            'issues_count' => count($issues),
            'has_dc_only_cart' => $this->cart_has_only_recargas(),
        ]));

        if (empty($issues)) {
            $this->relax_generic_dc_cart_notice_if_needed();
            return;
        }

        $error_notices = [];
        if (function_exists('wc_get_notices')) {
            foreach ((array) wc_get_notices('error') as $notice) {
                $raw = is_array($notice) ? (string) ($notice['notice'] ?? '') : (string) $notice;
                $clean = trim(wp_strip_all_tags($raw));
                if ($clean !== '') {
                    $error_notices[] = $clean;
                }
            }
        }

        error_log('[DingConnect][checkout_cart_validation] ' . wp_json_encode([
            'issues' => $issues,
            'error_notices' => array_values(array_unique($error_notices)),
        ]));

        $this->relax_generic_dc_cart_notice_if_needed();
    }

    public function normalize_dc_cart_after_session_load($cart) {
        if (!$cart || !method_exists($cart, 'get_cart')) {
            return;
        }

        $updated = false;
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (empty($cart_item['dc_recarga'])) {
                continue;
            }

            $product = $cart_item['data'] ?? null;
            if ($product && is_object($product) && method_exists($product, 'exists') && $product->exists()) {
                continue;
            }

            $base_product_id = $this->get_or_create_base_product();
            $base_product = $base_product_id > 0 ? wc_get_product($base_product_id) : null;
            if (!$base_product || !$base_product->exists()) {
                continue;
            }

            WC()->cart->cart_contents[$cart_item_key]['product_id'] = (int) $base_product->get_id();
            WC()->cart->cart_contents[$cart_item_key]['variation_id'] = 0;
            WC()->cart->cart_contents[$cart_item_key]['data'] = $base_product;
            WC()->cart->cart_contents[$cart_item_key]['dc_recarga'] = true;
            if (empty(WC()->cart->cart_contents[$cart_item_key]['quantity']) || (int) WC()->cart->cart_contents[$cart_item_key]['quantity'] < 1) {
                WC()->cart->cart_contents[$cart_item_key]['quantity'] = 1;
            }

            $updated = true;
        }

        if ($this->hydrate_recarga_cart_item_data(WC()->cart->cart_contents[$cart_item_key])) {
            $updated = true;
        }

        if ($updated && method_exists($cart, 'set_session')) {
            $cart->set_session();
            error_log('[DingConnect][cart_loaded_from_session] normalizacion aplicada a items de recarga');
        }
    }

    public function cleanup_dc_only_generic_cart_notice_late() {
        $this->relax_generic_dc_cart_notice_if_needed();
    }

    public function stabilize_checkout_php_session_compat() {
        if (is_admin()) {
            return;
        }

        $is_checkout_page = function_exists('is_checkout') && is_checkout();
        $wc_ajax = isset($_REQUEST['wc-ajax']) ? sanitize_key((string) wp_unslash($_REQUEST['wc-ajax'])) : '';
        $is_checkout_ajax = in_array($wc_ajax, ['checkout', 'update_order_review'], true);

        if (!$is_checkout_page && !$is_checkout_ajax) {
            return;
        }

        if (function_exists('session_status') && session_status() === PHP_SESSION_NONE) {
            @session_start();
            if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
                error_log('[DingConnect][checkout_env] php_session_iniciada_temprano_para_compatibilidad_checkout');
            }
        }
    }

    public function suppress_generic_cart_issue_error_for_dc_only($message) {
        if (!$this->is_dc_only_checkout_context()) {
            return $message;
        }

        $clean = trim(wp_strip_all_tags((string) $message));
        $is_suppressible_checkout_notice = $this->is_suppressible_dc_only_checkout_notice($clean);

        if ($is_suppressible_checkout_notice) {
            error_log('[DingConnect][checkout_cart_validation] generic_error_suppressed_at_source');
            return '';
        }

        if ($clean !== '') {
            error_log('[DingConnect][checkout_cart_validation] notice_no_suprimido: ' . $clean);
        }

        return $message;
    }

    private function relax_generic_dc_cart_notice_if_needed() {
        if (!$this->is_dc_only_checkout_context() || !function_exists('wc_get_notices') || !function_exists('wc_clear_notices') || !function_exists('wc_add_notice')) {
            return;
        }

        $errors = (array) wc_get_notices('error');
        if (empty($errors)) {
            return;
        }

        $keep = [];
        $removed = 0;

        foreach ($errors as $notice) {
            $raw = is_array($notice) ? (string) ($notice['notice'] ?? '') : (string) $notice;
            $clean = trim(wp_strip_all_tags($raw));
            $is_generic_cart_issue = $this->is_suppressible_dc_only_checkout_notice($clean);

            if ($is_generic_cart_issue) {
                $removed++;
                continue;
            }

            $keep[] = $clean;
        }

        if ($removed < 1) {
            return;
        }

        wc_clear_notices();
        foreach ($keep as $message) {
            if ($message !== '') {
                wc_add_notice($message, 'error');
            }
        }

        error_log('[DingConnect][checkout_cart_validation] notice_generico_carrito_removido para carrito DC-only: ' . (string) $removed);
    }

    private function is_suppressible_dc_only_checkout_notice($message) {
        $clean = trim((string) $message);
        if ($clean === '') {
            return false;
        }

        $decoded = html_entity_decode($clean, ENT_QUOTES, 'UTF-8');
        $normalized_raw = function_exists('remove_accents') ? remove_accents($decoded) : $decoded;
        $normalized = strtolower($normalized_raw);
        $normalized = preg_replace('/\s+/u', ' ', $normalized);
        $normalized = trim((string) $normalized);

        $patterns = [
            'problemas con los articulos de tu carrito',
            'problemas con algunos articulos de tu carrito',
            'vuelve a la pagina del carrito',
            'resuelve los problemas antes de pagar',
            'para poder hacer un pedido',
            'el total del carro de compra debe ser de al menos',
            'el total del carrito debe ser de al menos',
            'pedido minimo',
            'compra minima',
            'problems with the items in your cart',
            'please go back to the cart page',
            'before checking out',
            'minimum order',
            'must be at least',
        ];

        foreach ($patterns as $pattern) {
            if (strpos($normalized, $pattern) !== false) {
                return true;
            }
        }

        $has_cart_problem = (strpos($normalized, 'problemas con') !== false || strpos($normalized, 'problems with') !== false)
            && (strpos($normalized, 'articulos de tu carrito') !== false || strpos($normalized, 'items in your cart') !== false);
        $has_return_to_cart = strpos($normalized, 'vuelve a la pagina del carrito') !== false
            || strpos($normalized, 'please go back to the cart page') !== false;
        $has_minimum_order_problem = (strpos($normalized, 'pedido') !== false || strpos($normalized, 'order') !== false)
            && ((strpos($normalized, 'al menos') !== false) || (strpos($normalized, 'at least') !== false) || (strpos($normalized, 'minim') !== false));
        $has_cart_total_minimum = (strpos($normalized, 'total del carro de compra') !== false || strpos($normalized, 'total del carrito') !== false)
            && (strpos($normalized, 'al menos') !== false || strpos($normalized, 'at least') !== false);

        if ($has_cart_problem || ($has_return_to_cart && strpos($normalized, 'pagar') !== false) || $has_minimum_order_problem || $has_cart_total_minimum) {
            return true;
        }

        return false;
    }

    private function is_dc_only_checkout_context() {
        if ($this->cart_has_only_recargas()) {
            return true;
        }

        if (!function_exists('WC') || !WC() || !WC()->session) {
            return false;
        }

        $session_cart = (array) WC()->session->get('cart', []);
        if (empty($session_cart)) {
            return false;
        }

        $has_dc = false;
        foreach ($session_cart as $item) {
            $is_dc = !empty($item['dc_recarga']);
            if (!$is_dc) {
                return false;
            }
            $has_dc = true;
        }

        return $has_dc;
    }

    /* ---------------------------------------------------------------
     * 5. Cart Customization Hooks
     * ------------------------------------------------------------- */

    /** Set the dynamic price for recarga cart items. */
    public function set_custom_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        if (!$cart || !method_exists($cart, 'get_cart')) return;

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!empty($cart_item['dc_recarga']) && isset($cart_item['dc_send_value'])) {
                $this->hydrate_recarga_cart_item_data($cart->cart_contents[$cart_item_key]);
                $cart_item = $cart->cart_contents[$cart_item_key];

                $public_price = isset($cart_item['dc_public_price']) ? (float) $cart_item['dc_public_price'] : 0.0;
                $public_currency = (string) ($cart_item['dc_public_currency_iso'] ?? ($cart_item['dc_send_currency_iso'] ?? ''));
                $send_value = isset($cart_item['dc_send_value']) ? (float) $cart_item['dc_send_value'] : 0.0;
                $send_currency = (string) ($cart_item['dc_send_currency_iso'] ?? '');

                $billing_price = $public_price > 0
                    ? $this->normalize_price_for_store_currency($public_price, $public_currency)
                    : $this->normalize_price_for_store_currency($send_value, $send_currency);

                if (isset($cart_item['data']) && is_object($cart_item['data']) && method_exists($cart_item['data'], 'set_price')) {
                    $cart_item['data']->set_price($billing_price);
                }
            }
        }
    }

    /** Display recarga details in cart/checkout line items. */
    public function display_cart_item_data($item_data, $cart_item) {
        if (empty($cart_item['dc_recarga'])) return $item_data;

        $benefit_text = trim((string) ($cart_item['dc_bundle_benefit'] ?? ''));
        $bundle_label = trim((string) ($cart_item['dc_bundle_label'] ?? ''));

        $item_data[] = ['key' => 'País',     'value' => strtoupper($cart_item['dc_country_iso'] ?? '')];
        $item_data[] = ['key' => 'Teléfono', 'value' => $cart_item['dc_account_number'] ?? ''];
        $item_data[] = ['key' => 'Operador', 'value' => $cart_item['dc_provider_name'] ?? ''];

        if ($benefit_text !== '') {
            $item_data[] = ['key' => 'Beneficios', 'value' => $benefit_text];
        }

        if ($bundle_label !== '' && strcasecmp($bundle_label, $benefit_text) !== 0) {
            $item_data[] = ['key' => 'Paquete', 'value' => $bundle_label];
        }

        $public_price = isset($cart_item['dc_public_price']) ? (float) $cart_item['dc_public_price'] : 0.0;
        $public_currency = (string) ($cart_item['dc_public_currency_iso'] ?? ($cart_item['dc_send_currency_iso'] ?? ''));
        if ($public_price > 0) {
            $item_data[] = ['key' => 'Precio al público', 'value' => sprintf('%s %.2f', $public_currency, $public_price)];
        }

        $item_data[] = ['key' => 'Moneda operación', 'value' => $cart_item['dc_send_currency_iso'] ?? ''];

        if (!empty($cart_item['dc_bill_ref'])) {
            $item_data[] = ['key' => 'Factura', 'value' => $cart_item['dc_bill_ref']];
        }

        if (!empty($cart_item['dc_settings']) && is_array($cart_item['dc_settings'])) {
            foreach ($cart_item['dc_settings'] as $setting) {
                if (!is_array($setting) || empty($setting['Name'])) {
                    continue;
                }

                $item_data[] = [
                    'key' => 'Dato ' . $setting['Name'],
                    'value' => (string) ($setting['Value'] ?? ''),
                ];
            }
        }

        return $item_data;
    }

    /** Override product name in cart with provider + bundle label. */
    public function custom_cart_item_name($name, $cart_item, $cart_item_key) {
        if (!empty($cart_item['dc_recarga'])) {
            $label    = $cart_item['dc_bundle_label'] ?? 'Recarga';
            $benefit  = trim((string) ($cart_item['dc_bundle_benefit'] ?? ''));
            if ($benefit !== '') {
                return esc_html($benefit);
            }
            $provider = $cart_item['dc_provider_name'] ?? '';
            return esc_html($provider ? $provider . ' - ' . $label : $label);
        }
        return $name;
    }

    private function hydrate_recarga_cart_item_data(&$cart_item) {
        if (!is_array($cart_item) || empty($cart_item['dc_recarga'])) {
            return false;
        }

        $matched_bundle = $this->find_saved_bundle_for_cart_item($cart_item);
        if (!is_array($matched_bundle)) {
            return false;
        }

        $updated = false;

        if (empty($cart_item['dc_bundle_id']) && !empty($matched_bundle['id'])) {
            $cart_item['dc_bundle_id'] = sanitize_text_field((string) $matched_bundle['id']);
            $updated = true;
        }

        if (empty($cart_item['dc_bundle_label']) && !empty($matched_bundle['label'])) {
            $cart_item['dc_bundle_label'] = sanitize_text_field((string) $matched_bundle['label']);
            $updated = true;
        }

        if (empty($cart_item['dc_provider_name']) && !empty($matched_bundle['provider_name'])) {
            $cart_item['dc_provider_name'] = sanitize_text_field((string) $matched_bundle['provider_name']);
            $updated = true;
        }

        if (empty($cart_item['dc_public_currency_iso']) && !empty($matched_bundle['public_price_currency'])) {
            $cart_item['dc_public_currency_iso'] = strtoupper(sanitize_text_field((string) $matched_bundle['public_price_currency']));
            $updated = true;
        }

        if ((!isset($cart_item['dc_public_price']) || (float) $cart_item['dc_public_price'] <= 0) && isset($matched_bundle['public_price'])) {
            $stored_public_price = (float) $matched_bundle['public_price'];
            if ($stored_public_price > 0) {
                $cart_item['dc_public_price'] = $stored_public_price;
                $updated = true;
            }
        }

        $benefit_text = $this->extract_bundle_benefit_for_checkout($matched_bundle);
        if ($benefit_text !== '' && empty($cart_item['dc_bundle_benefit'])) {
            $cart_item['dc_bundle_benefit'] = $benefit_text;
            $updated = true;
        }

        return $updated;
    }

    private function find_saved_bundle_for_cart_item(array $cart_item) {
        $options = $this->api->get_options();
        $bundles = (array) ($options['bundles'] ?? []);
        if (empty($bundles)) {
            return null;
        }

        $bundle_id = sanitize_text_field((string) ($cart_item['dc_bundle_id'] ?? ''));
        $sku_code = sanitize_text_field((string) ($cart_item['dc_sku_code'] ?? ''));
        $country_iso = strtoupper(sanitize_text_field((string) ($cart_item['dc_country_iso'] ?? '')));

        if ($bundle_id !== '') {
            foreach ($bundles as $bundle) {
                if (!is_array($bundle)) {
                    continue;
                }

                $candidate_id = sanitize_text_field((string) ($bundle['id'] ?? ''));
                if ($candidate_id !== '' && $candidate_id === $bundle_id) {
                    return $bundle;
                }
            }
        }

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

            return $bundle;
        }

        return null;
    }

    private function extract_bundle_benefit_for_checkout(array $bundle) {
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

        return !empty($benefits) ? implode(', ', $benefits) : '';
    }

    private function normalize_price_for_store_currency($amount, $amount_currency) {
        $normalized_amount = (float) $amount;
        if ($normalized_amount <= 0) {
            return 0.0;
        }

        $source_currency = strtoupper(sanitize_text_field((string) $amount_currency));
        $store_currency = strtoupper(sanitize_text_field((string) get_option('woocommerce_currency', '')));
        if ($source_currency === '' || $store_currency === '' || $source_currency === $store_currency) {
            return $normalized_amount;
        }

        if (!class_exists('WOOMULTI_CURRENCY_Data') || !method_exists('WOOMULTI_CURRENCY_Data', 'get_ins')) {
            return $normalized_amount;
        }

        $multi_currency_settings = WOOMULTI_CURRENCY_Data::get_ins();
        if (!$multi_currency_settings || !method_exists($multi_currency_settings, 'get_list_currencies')) {
            return $normalized_amount;
        }

        $currencies = (array) $multi_currency_settings->get_list_currencies();
        $source_rate = isset($currencies[$source_currency]['rate']) ? (float) $currencies[$source_currency]['rate'] : 0.0;
        if ($source_rate <= 0) {
            return $normalized_amount;
        }

        return $normalized_amount / $source_rate;
    }

    /**
     * Avoid media attachment metadata lookups for synthetic recarga items.
     */
    public function suppress_recarga_cart_item_thumbnail($thumbnail, $cart_item, $cart_item_key) {
        if (!empty($cart_item['dc_recarga'])) {
            return '';
        }

        return $thumbnail;
    }

    /* ---------------------------------------------------------------
     * 6. Order Item Meta
     * ------------------------------------------------------------- */

    /** Persist recarga data from cart into order line item meta. */
    public function save_order_item_meta($item, $cart_item_key, $values, $order) {
        if (empty($values['dc_recarga'])) return;

        $item->add_meta_data('_dc_recarga',           'yes', true);
        $item->add_meta_data('_dc_account_number',    $values['dc_account_number'] ?? '', true);
        $item->add_meta_data('_dc_country_iso',       $values['dc_country_iso'] ?? '', true);
        $item->add_meta_data('_dc_sku_code',          $values['dc_sku_code'] ?? '', true);
        $item->add_meta_data('_dc_send_value',        $values['dc_send_value'] ?? 0, true);
        $item->add_meta_data('_dc_send_currency_iso', $values['dc_send_currency_iso'] ?? '', true);
        $item->add_meta_data('_dc_public_price',      $values['dc_public_price'] ?? ($values['dc_send_value'] ?? 0), true);
        $item->add_meta_data('_dc_public_currency_iso', $values['dc_public_currency_iso'] ?? ($values['dc_send_currency_iso'] ?? ''), true);
        $item->add_meta_data('_dc_provider_name',     $values['dc_provider_name'] ?? '', true);
        $item->add_meta_data('_dc_bundle_label',      $values['dc_bundle_label'] ?? '', true);
        $item->add_meta_data('_dc_bundle_benefit',    $values['dc_bundle_benefit'] ?? '', true);
        $item->add_meta_data('_dc_product_type',      $values['dc_product_type'] ?? '', true);
        $item->add_meta_data('_dc_redemption_mechanism', $values['dc_redemption_mechanism'] ?? '', true);
        $item->add_meta_data('_dc_lookup_bills_required', !empty($values['dc_lookup_bills_required']) ? 'yes' : '', true);
        $item->add_meta_data('_dc_customer_care_number', $values['dc_customer_care_number'] ?? '', true);
        $item->add_meta_data('_dc_is_range',          !empty($values['dc_is_range']) ? 'yes' : '', true);
        $item->add_meta_data('_dc_flow_kind',         $values['dc_flow_kind'] ?? '', true);
        $item->add_meta_data('_dc_bill_ref',          $values['dc_bill_ref'] ?? '', true);
        $item->add_meta_data('_dc_settings',          !empty($values['dc_settings']) ? wp_json_encode($values['dc_settings']) : '', true);
    }

    /* ---------------------------------------------------------------
     * 7. Mandatory Registration (no guest checkout for recargas)
     * ------------------------------------------------------------- */

    /**
     * Force registration only for mixed carts (DC recargas + other products).
     * DC-only checkouts allow guest to keep the flow as simple as possible.
     */
    public function force_registration($registration_required) {
        if ($this->cart_has_recargas() && !$this->cart_has_only_recargas()) {
            return true;
        }
        return $registration_required;
    }

    public function disable_guest_checkout($value) {
        if ($this->cart_has_recargas() && !$this->cart_has_only_recargas()) {
            return 'no';
        }
        return $value;
    }

    /**
     * When the cart contains ONLY DC recargas, redirect /cart to /checkout
     * so the user never sees the generic cart page.
     */
    public function redirect_dc_only_cart_to_checkout() {
        if (!is_cart()) return;
        if (!$this->cart_has_only_recargas()) return;
        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }

    private function cart_has_recargas() {
        if (!WC()->cart) return false;
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (!empty($cart_item['dc_recarga'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true only when EVERY item in the cart is a DC recarga.
     * Used to apply DC-specific checkout simplifications without affecting
     * regular WooCommerce products.
     */
    private function cart_has_only_recargas() {
        // WC cart is not ready before wp_loaded. Calling get_cart() before that
        // triggers wc_doing_it_wrong and cascades into other early-call errors
        // (e.g. when Mollie fires woocommerce_payment_gateways during woocommerce_init).
        if (!did_action('wp_loaded')) return false;
        if (!WC()->cart) return false;
        $items = WC()->cart->get_cart();
        if (empty($items)) return false;
        foreach ($items as $cart_item) {
            if (empty($cart_item['dc_recarga'])) return false;
        }
        return true;
    }

    public function filter_available_payment_gateways($gateways) {
        if (!is_array($gateways) || empty($gateways)) {
            return $gateways;
        }

        if (!$this->cart_has_recargas()) {
            return $gateways;
        }

        $options = $this->api->get_options();
        $payment_mode = sanitize_text_field((string) ($options['payment_mode'] ?? 'direct'));
        if ($payment_mode !== 'woocommerce') {
            return $gateways;
        }

        $allowed = array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($options['woo_allowed_gateways'] ?? [])))));
        if (empty($allowed)) {
            return $gateways;
        }

        $allowed_map = array_fill_keys($allowed, true);

        $filtered = array_filter($gateways, function ($gateway, $gateway_id) use ($allowed_map) {
            return isset($allowed_map[sanitize_key((string) $gateway_id)]);
        }, ARRAY_FILTER_USE_BOTH);

        if (empty($filtered)) {
            error_log('[DingConnect][checkout_gateways] allowed_gateways configuradas sin coincidencias disponibles; se usa fallback a gateways activos de WooCommerce');
            return $gateways;
        }

        error_log('[DingConnect][checkout_gateways] gateways aplicadas para recarga: ' . implode(',', array_keys($filtered)));
        return $filtered;
    }

    public function filter_gateway_classes_for_dc_checkout($gateway_classes) {
        if (!is_array($gateway_classes) || empty($gateway_classes)) {
            return $gateway_classes;
        }

        if (!$this->is_checkout_request_context() || !$this->is_dc_only_checkout_context()) {
            return $gateway_classes;
        }

        $options = $this->api->get_options();
        $payment_mode = sanitize_text_field((string) ($options['payment_mode'] ?? 'direct'));
        if ($payment_mode !== 'woocommerce') {
            return $gateway_classes;
        }

        $allowed = array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($options['woo_allowed_gateways'] ?? [])))));
        if (empty($allowed)) {
            return $gateway_classes;
        }

        $tropipay_allowed = in_array('tropipay', $allowed, true) || in_array('wc_tropipay', $allowed, true);
        if ($tropipay_allowed) {
            return $gateway_classes;
        }

        $filtered = [];
        $removed = false;
        foreach ($gateway_classes as $key => $gateway_class) {
            $key_norm = sanitize_key((string) $key);
            $class_norm = sanitize_key(is_string($gateway_class) ? $gateway_class : '');
            $is_tropipay = (strpos($key_norm, 'tropipay') !== false) || (strpos($class_norm, 'tropipay') !== false);
            if ($is_tropipay) {
                $removed = true;
                continue;
            }
            $filtered[$key] = $gateway_class;
        }

        if ($removed) {
            error_log('[DingConnect][checkout_gateways] clase gateway Tropipay excluida en checkout DC-only por configuracion de pasarelas permitidas');
            return $filtered;
        }

        return $gateway_classes;
    }

    private function is_checkout_request_context() {
        if (is_admin()) {
            return false;
        }

        $is_checkout_page = function_exists('is_checkout') && is_checkout();
        $wc_ajax = isset($_REQUEST['wc-ajax']) ? sanitize_key((string) wp_unslash($_REQUEST['wc-ajax'])) : '';
        $is_checkout_ajax = in_array($wc_ajax, ['checkout', 'update_order_review'], true);

        return $is_checkout_page || $is_checkout_ajax;
    }

    private function should_hide_acfw_store_credit_ui() {
        if (!$this->is_checkout_request_context()) {
            return false;
        }

        if (!$this->cart_has_only_recargas()) {
            return false;
        }

        $options = $this->api->get_options();
        $payment_mode = sanitize_text_field((string) ($options['payment_mode'] ?? 'direct'));
        if ($payment_mode !== 'woocommerce') {
            return false;
        }

        return !empty($options['hide_acfw_store_credit_dc_only']);
    }

    public function maybe_hide_acfw_store_credit_ui() {
        if (!$this->should_hide_acfw_store_credit_ui()) {
            return;
        }

        ?>
        <style id="dc-hide-acfw-store-credit">
            .dc-hide-store-credit {
                display: none !important;
            }

            .woocommerce-checkout [class*="acfw"][class*="store-credit"],
            .woocommerce-checkout [class*="acfw"][class*="credit"],
            .woocommerce-checkout [id*="acfw"][id*="credit"] {
                display: none !important;
            }
        </style>
        <script id="dc-hide-acfw-store-credit-script">
            (function () {
                function normalize(text) {
                    return String(text || '')
                        .toLowerCase()
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .replace(/\s+/g, ' ')
                        .trim();
                }

                function hasStoreCreditText(text) {
                    var value = normalize(text);
                    if (!value) {
                        return false;
                    }

                    return value.indexOf('apply store credit discounts') !== -1
                        || value.indexOf('available store credits') !== -1
                        || value.indexOf('introduce la cantidad de creditos en la tienda') !== -1
                        || value.indexOf('introduce la cantidad') !== -1
                        || value.indexOf('creditos en la tienda') !== -1;
                }

                function hideNode(node) {
                    if (!node || !node.classList) {
                        return;
                    }
                    node.classList.add('dc-hide-store-credit');
                }

                function findTarget(node) {
                    if (!node || !node.closest) {
                        return null;
                    }

                    return node.closest(
                        '.acfw-checkout-ui, .acfw-checkout-store-credit, .acfw-store-credits, .acfw-widget, [class*="acfw"], [id*="acfw"], .woocommerce-form-coupon-toggle, .wc-block-components-panel, .wc-block-components-totals-item, .woocommerce-checkout-review-order'
                    ) || node.parentElement;
                }

                function run() {
                    var selectors = [
                        '.acfw-checkout-ui',
                        '.acfw-checkout-store-credit',
                        '.acfw-store-credits',
                        '.acfw-widget',
                        '[class*="acfw"][class*="credit"]',
                        '[id*="acfw"][id*="credit"]',
                        'summary',
                        'label',
                        'h2',
                        'h3',
                        'p',
                        'span'
                    ];

                    for (var i = 0; i < selectors.length; i++) {
                        var nodes = document.querySelectorAll(selectors[i]);
                        for (var j = 0; j < nodes.length; j++) {
                            var node = nodes[j];
                            var className = normalize(node.className || '');
                            var idName = normalize(node.id || '');
                            var isAcfwCandidate = className.indexOf('acfw') !== -1 || idName.indexOf('acfw') !== -1;

                            if (isAcfwCandidate || hasStoreCreditText(node.textContent)) {
                                hideNode(findTarget(node));
                            }
                        }
                    }
                }

                run();

                if (typeof MutationObserver !== 'undefined') {
                    var observer = new MutationObserver(function () {
                        run();
                    });
                    observer.observe(document.body, { childList: true, subtree: true });
                }
            })();
        </script>
        <?php
    }

    /* ---------------------------------------------------------------
     * 8. Checkout Fields & Phone Login
     * ------------------------------------------------------------- */

    /**
     * Customize checkout fields.
     *
     * - DC-only cart: simplify to first name, last name, email and billing phone.
     *   All address/shipping/order fields are removed so the checkout is minimal.
     * - Mixed cart (DC + regular products): keep all WC fields, just enforce phone.
     */
    public function customize_checkout_fields($fields) {
        if (!$this->cart_has_recargas()) return $fields;

        if ($this->cart_has_only_recargas()) {
            // Minimal DC checkout: name + email + phone only
            $allowed_billing = ['billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone'];

            if (isset($fields['billing']) && is_array($fields['billing'])) {
                foreach (array_keys($fields['billing']) as $key) {
                    if (!in_array($key, $allowed_billing, true)) {
                        unset($fields['billing'][$key]);
                    }
                }
            }

            // Shipping and order comment fields are not needed for virtual products
            $fields['shipping'] = [];
            $fields['order']    = [];
        }

        // Always ensure billing_phone is required and labelled clearly
        if (isset($fields['billing']['billing_phone'])) {
            $fields['billing']['billing_phone']['required']    = true;
            $fields['billing']['billing_phone']['priority']    = 30;
            $fields['billing']['billing_phone']['label']       = 'Tu número de teléfono';
            $fields['billing']['billing_phone']['placeholder'] = 'Ej: +34 600 000 000';
        }

        return $fields;
    }

    /**
     * Safety net: remove any WC address validation errors that may fire
     * even after fields are removed via the checkout_fields filter.
     * Only active for DC-only carts.
     */
    public function clear_address_validation_errors_for_dc($data, $errors) {
        if (!$this->cart_has_only_recargas()) return;

        $address_fields = [
            'billing_address_1', 'billing_address_2', 'billing_city',
            'billing_state', 'billing_postcode', 'billing_country', 'billing_company',
            'shipping_first_name', 'shipping_last_name', 'shipping_address_1',
            'shipping_address_2', 'shipping_city', 'shipping_state',
            'shipping_postcode', 'shipping_country',
        ];

        foreach ($address_fields as $field) {
            $errors->remove($field);
        }
    }

    /** Pre-fill billing phone from recarga data during registration. */
    public function prefill_customer_phone($customer_data) {
        if (!WC()->cart) return $customer_data;

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (!empty($cart_item['dc_recarga']) && !empty($cart_item['dc_account_number'])) {
                $customer_data['billing_phone'] = sanitize_text_field($cart_item['dc_account_number']);
                break;
            }
        }

        return $customer_data;
    }

    /**
     * Allow login by phone number (billing_phone usermeta).
     * Uses direct DB query because WP has no usermeta-by-value lookup function.
     */
    public function authenticate_by_phone($user, $username, $password) {
        if ($user instanceof WP_User) return $user;

        $cleaned = preg_replace('/[^\d+]/', '', $username);
        if (strlen($cleaned) >= 8) {
            global $wpdb;
            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'billing_phone' AND meta_value = %s LIMIT 1",
                $cleaned
            ));

            if ($user_id) {
                $phone_user = get_user_by('id', $user_id);
                if ($phone_user && wp_check_password($password, $phone_user->user_pass, $phone_user->ID)) {
                    return $phone_user;
                }
            }
        }

        return $user;
    }

    /* ---------------------------------------------------------------
     * 9. Order Processing - Execute Real DingConnect Transfer
     * ------------------------------------------------------------- */

    /**
     * Fire the real DingConnect transfer when payment is confirmed.
     *
     * IMPORTANT: The admin must enable "allow_real_recharge" in plugin settings
     * for real transfers to execute. Without it, send_transfer() forces
     * ValidateOnly=true regardless of what we pass here.
     */
    public function process_recarga_on_payment($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        if (!$order->is_paid()) {
            $order->add_order_note('DingConnect: se omitio despacho porque la orden aun no figura como pagada.');
            return;
        }

        $order->add_order_note(sprintf(
            'DingConnect: inicio de evaluacion post-pago (hook: %s, estado actual: %s).',
            'post_pago',
            $this->get_order_status_slug($order)
        ));

        $has_recargas = false;
        $all_success  = true;
        $has_pending_retry = false;

        foreach ($order->get_items() as $item_id => $item) {
            if ($item->get_meta('_dc_recarga') !== 'yes') continue;

            $has_recargas = true;

            if ($this->is_item_already_successful($item)) {
                continue;
            }

            $sync_result = $this->sync_item_with_ding_status($order, $item);
            if (!empty($sync_result['success'])) {
                continue;
            }

            if (!empty($sync_result['pending'])) {
                $all_success = false;
                $has_pending_retry = true;
                $this->schedule_submitted_retry($order, $item, 'sync_on_payment');
                continue;
            }

            $attempt_result = $this->attempt_transfer_for_item($order, $item, false);

            if (!empty($attempt_result['pending_retry'])) {
                $has_pending_retry = true;
            }

            if (empty($attempt_result['success'])) {
                $all_success = false;
            }
        }

        if ($has_recargas) {
            if ($all_success && !$has_pending_retry) {
                $order->update_meta_data('_dc_recargas_processed', current_time('mysql'));
                $order->delete_meta_data('_dc_recargas_has_errors');
            } else {
                $order->update_meta_data('_dc_recargas_has_errors', 'yes');
            }

            if ($has_pending_retry) {
                $order->add_order_note('Hay recargas con reintento programado. Se intentara nuevamente segun configuracion.');
            } elseif (!$all_success) {
                $order->add_order_note('Algunas recargas fallaron. Revisa los detalles de cada item o ejecuta reconciliacion manual.');
            }

            $this->sync_order_status_with_recarga_outcome($order, 'post_pago');
            $order->save();
        }
    }

    public function process_retry_transfer($order_id, $item_id) {
        $order = wc_get_order((int) $order_id);
        if (!$order || !$order->is_paid()) {
            return;
        }

        $order->add_order_note(sprintf(
            'DingConnect: ejecucion de reintento programado para item #%d.',
            (int) $item_id
        ));

        $item = $order->get_item((int) $item_id);
        if (!$item instanceof WC_Order_Item_Product) {
            $order->add_order_note(sprintf(
                'DingConnect: reintento omitido, item #%d no encontrado en la orden.',
                (int) $item_id
            ));
            return;
        }

        if ($item->get_meta('_dc_recarga') !== 'yes' || $this->is_item_already_successful($item)) {
            $order->add_order_note(sprintf(
                'DingConnect: reintento omitido para item #%d porque no es recarga o ya estaba confirmado.',
                (int) $item_id
            ));
            return;
        }

        $sync_result = $this->sync_item_with_ding_status($order, $item);
        if (!empty($sync_result['success'])) {
            $this->sync_order_status_with_recarga_outcome($order, 'retry_sync_success');
            $order->save();
            return;
        }

        if (!empty($sync_result['pending'])) {
            $this->schedule_submitted_retry($order, $item, 'sync_on_retry');
            $this->sync_order_status_with_recarga_outcome($order, 'retry_sync_pending');
            $order->save();
            return;
        }

        $this->attempt_transfer_for_item($order, $item, false);
        $this->sync_order_status_with_recarga_outcome($order, 'retry_send_transfer');
        $order->save();
    }

    public function register_manual_reconcile_action($actions) {
        $actions['dc_recargas_manual_reconcile'] = __('Reintentar recargas DingConnect', 'dingconnect-recargas');
        return $actions;
    }

    public function handle_manual_reconcile_action($order) {
        if (!$order instanceof WC_Order) {
            return;
        }

        $order->add_order_note('DingConnect: inicio de reconciliacion manual solicitada por operador.');

        $processed = 0;
        foreach ($order->get_items() as $item_id => $item) {
            if ($item->get_meta('_dc_recarga') !== 'yes') {
                continue;
            }

            if ($this->is_item_already_successful($item)) {
                continue;
            }

            $sync_result = $this->sync_item_with_ding_status($order, $item);
            if (!empty($sync_result['success'])) {
                $processed++;
                continue;
            }

            if (!empty($sync_result['pending'])) {
                $this->schedule_submitted_retry($order, $item, 'manual_reconcile');
                $processed++;
                continue;
            }

            $this->attempt_transfer_for_item($order, $item, true);
            $processed++;
        }

        if ($processed > 0) {
            $order->add_order_note(sprintf('Reconciliacion manual ejecutada para %d item(s) DingConnect.', $processed));
            $this->sync_order_status_with_recarga_outcome($order, 'manual_reconcile');
            $order->save();
        } else {
            $order->add_order_note('DingConnect: reconciliacion manual sin items pendientes para procesar.');
        }
    }

    /* ---------------------------------------------------------------
     * 10. Admin Display: Recarga Meta on Order Page
     * ------------------------------------------------------------- */

    public function display_order_item_recarga_meta($item_id, $item, $product) {
        if (!($item instanceof WC_Order_Item_Product)) return;
        if ($item->get_meta('_dc_recarga') !== 'yes') return;

        $fields = [
            '_dc_account_number'  => 'Teléfono',
            '_dc_country_iso'     => 'País',
            '_dc_provider_name'   => 'Operador',
            '_dc_bundle_benefit'  => 'Beneficios',
            '_dc_bundle_label'    => 'Paquete',
            '_dc_public_price'    => 'Precio al público',
            '_dc_public_currency_iso' => 'Moneda precio público',
            '_dc_send_value'      => 'Coste Ding',
            '_dc_send_currency_iso' => 'Moneda operación',
            '_dc_transfer_ref'    => 'Ref. DingConnect',
            '_dc_distributor_ref' => 'Ref. Distribuidor',
            '_dc_transfer_status' => 'Estado transferencia',
            '_dc_transfer_error'  => 'Error',
        ];

        echo '<div class="dc-order-meta" style="margin-top:8px;padding:8px;background:#f8fafc;border-radius:6px;font-size:12px;">';
        echo '<strong style="display:block;margin-bottom:4px;color:#1e3a8a;">Datos de Recarga DingConnect</strong>';

        foreach ($fields as $meta_key => $label) {
            $value = $item->get_meta($meta_key);

            if ($meta_key === '_dc_public_price' && $value !== '') {
                $public_currency = (string) $item->get_meta('_dc_public_currency_iso');
                $value = sprintf('%s %.2f', $public_currency !== '' ? $public_currency : 'EUR', (float) $value);
            }

            if ($meta_key === '_dc_send_value' && $value !== '') {
                $send_currency = (string) $item->get_meta('_dc_send_currency_iso');
                $value = sprintf('%s %.2f', $send_currency !== '' ? $send_currency : 'EUR', (float) $value);
            }

            if ($value) {
                $css = ($meta_key === '_dc_transfer_error') ? 'color:#991b1b;' : '';
                echo '<div style="' . $css . '"><span style="color:#64748b;">' . esc_html($label) . ':</span> ' . esc_html($value) . '</div>';
            }
        }

        echo '</div>';
    }

    public function render_thankyou_voucher_summary($order_id) {
        $order = wc_get_order((int) $order_id);
        if (!$order) {
            return;
        }

        $voucher_rows = $this->collect_order_voucher_rows($order);
        if (empty($voucher_rows)) {
            return;
        }

        $has_pending = $this->order_has_pending_recargas($order);

        echo '<section class="woocommerce-order-details" style="margin-top:22px;">';
        echo '<h2>Resumen final de tu compra DingConnect</h2>';
        if ($has_pending) {
            echo '<p style="margin:0 0 14px;color:#7c2d12;">Tu pedido contiene operaciones pendientes en DingConnect. No repitas la compra mientras el estado siga Submitted o Pending; el sistema seguira conciliando segun la politica configurada.</p>';
        }
        echo '<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">';
        foreach ($voucher_rows as $row) {
            echo '<li class="woocommerce-order-overview__item">';
            echo esc_html($row);
            echo '</li>';
        }
        echo '</ul>';
        echo '</section>';
    }

    public function inject_voucher_meta_into_email($fields, $sent_to_admin, $order) {
        if (!$order instanceof WC_Order) {
            return $fields;
        }

        $voucher_rows = $this->collect_order_voucher_rows($order);
        if (empty($voucher_rows)) {
            return $fields;
        }

        $fields['dc_recargas_voucher_summary'] = [
            'label' => __('Resumen de recarga', 'dingconnect-recargas'),
            'value' => implode(' | ', $voucher_rows),
        ];

        return $fields;
    }

    /* ---------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------- */

    private function is_item_already_successful($item) {
        $status = strtolower((string) $item->get_meta('_dc_transfer_status'));
        return $this->is_successful_transfer_status($status);
    }

    /**
     * Dispara el email de confirmación de recarga al cliente.
     * Delega al sistema de emails de WooCommerce (wp_mail + cualquier SMTP plugin activo).
     *
     * @param WC_Order                  $order
     * @param WC_Order_Item_Product     $item
     * @param array                     $snapshot  Snapshot del transfer.
     */
    private function send_recarga_confirmacion_email($order, $item, array $snapshot) {
        $emails = WC()->mailer()->get_emails();
        if (isset($emails['WC_DC_Email_Recarga_Confirmacion'])) {
            $emails['WC_DC_Email_Recarga_Confirmacion']->trigger($order->get_id(), $item, $snapshot);
        }
    }

    private function attempt_transfer_for_item($order, $item, $manual = false) {
        $account_number = (string) $item->get_meta('_dc_account_number');
        $sku_code = (string) $item->get_meta('_dc_sku_code');
        $send_value = (float) $item->get_meta('_dc_send_value');
        $send_currency_iso = (string) $item->get_meta('_dc_send_currency_iso');
        $bill_ref = (string) $item->get_meta('_dc_bill_ref');
        $settings = json_decode((string) $item->get_meta('_dc_settings'), true);
        if (!is_array($settings)) {
            $settings = [];
        }

        $lock_key = 'dc_transfer_lock_' . md5($order->get_id() . '_' . $item->get_id());
        if (get_transient($lock_key)) {
            $order->add_order_note(sprintf(
                'DingConnect: item #%d omitido temporalmente por lock anti-duplicado activo (60s).',
                (int) $item->get_id()
            ));
            return ['success' => false, 'pending_retry' => true, 'message' => 'lock'];
        }
        set_transient($lock_key, 1, 60);

        $attempt = (int) $item->get_meta('_dc_retry_attempts');
        $attempt++;
        $item->update_meta_data('_dc_retry_attempts', $attempt);
        $item->update_meta_data('_dc_last_attempt_at', current_time('mysql'));

        $order->add_order_note(sprintf(
            'DingConnect: intento #%d para item #%d (telefono: %s, sku: %s).',
            $attempt,
            (int) $item->get_id(),
            $account_number,
            $sku_code
        ));

        $distributor_ref = $this->api->new_ref();
        $payload = [
            'DistributorRef' => $distributor_ref,
            'AccountNumber' => $account_number,
            'SkuCode' => $sku_code,
            'SendValue' => $send_value,
            'SendCurrencyIso' => $send_currency_iso,
            'ValidateOnly' => false,
            'Settings' => $settings,
            'BillRef' => $bill_ref,
        ];

        $response = $this->api->send_transfer($payload);
        $this->api->log_transfer($account_number, $sku_code, $send_value, $send_currency_iso, $distributor_ref, $response);

        if (is_wp_error($response)) {
            $is_non_retryable = $this->is_non_retryable_error($response);
            $item->update_meta_data('_dc_transfer_status', 'error');
            $item->update_meta_data('_dc_transfer_error', $response->get_error_message());
            $item->update_meta_data('_dc_distributor_ref', $distributor_ref);

            $retry_budget = $this->get_retry_attempt_limit();
            $can_schedule_retry = !$manual && !$is_non_retryable && $attempt <= $retry_budget;

            if ($can_schedule_retry) {
                $retry_ts = time() + (60 * $this->get_retry_delay_minutes($attempt));
                $item->update_meta_data('_dc_transfer_status', 'pending_retry');
                $item->update_meta_data('_dc_next_retry_at', gmdate('Y-m-d H:i:s', $retry_ts));
                wp_clear_scheduled_hook('dc_recargas_retry_transfer', [(int) $order->get_id(), (int) $item->get_id()]);
                wp_schedule_single_event($retry_ts, 'dc_recargas_retry_transfer', [(int) $order->get_id(), (int) $item->get_id()]);
            } elseif ($is_non_retryable) {
                $item->update_meta_data('_dc_transfer_status', 'failed_permanent');
                $item->delete_meta_data('_dc_next_retry_at');
            }

            $item->save();
            $order->add_order_note(sprintf(
                'Recarga FALLIDA para %s (SKU: %s), intento %d: %s%s',
                $account_number,
                $sku_code,
                $attempt,
                $response->get_error_message(),
                $is_non_retryable ? ' [sin reintento automático]' : ''
            ));

            delete_transient($lock_key);

            return [
                'success' => false,
                'pending_retry' => $can_schedule_retry,
                'message' => $response->get_error_message(),
            ];
        }

        $snapshot = $this->extract_transfer_snapshot($response, $distributor_ref);
        $this->apply_transfer_snapshot_to_item($item, $snapshot, $account_number, $send_value);
        $status = $snapshot['status_label'];
        $transfer_ref = $snapshot['transfer_ref'];
        $receive_value = $snapshot['receive_value'];
        $receive_currency = $snapshot['receive_currency'];
        $is_success = $this->is_successful_transfer_status($snapshot['status']);
        $is_pending = $this->is_pending_transfer_status($snapshot['status']);

        if ($is_pending && !$manual) {
            $this->schedule_submitted_retry($order, $item, 'send_transfer_pending');
        }

        if ($is_success) {
            $note = sprintf(
                'Recarga EXITOSA para %s (SKU: %s), intento %d - Ref: %s - Estado: %s',
                $account_number,
                $sku_code,
                $attempt,
                $transfer_ref,
                $status
            );
        } elseif ($is_pending) {
            $note = sprintf(
                'Recarga enviada a DingConnect para conciliacion posterior para %s (SKU: %s), intento %d - Ref: %s - Estado: %s',
                $account_number,
                $sku_code,
                $attempt,
                $transfer_ref !== '' ? $transfer_ref : $distributor_ref,
                $status
            );
        } else {
            $note = sprintf(
                'Recarga registrada con estado no terminal para %s (SKU: %s), intento %d - Ref: %s - Estado: %s',
                $account_number,
                $sku_code,
                $attempt,
                $transfer_ref !== '' ? $transfer_ref : $distributor_ref,
                $status
            );
        }

        if ($receive_value > 0) {
            $note .= sprintf(' - Recibe: %s %s', $receive_currency, $receive_value);
        }

        $order->add_order_note($note);

        // Enviar email de confirmación al cliente cuando la recarga es exitosa
        if ($is_success) {
            $this->send_recarga_confirmacion_email($order, $item, $snapshot);
        }

        delete_transient($lock_key);

        return ['success' => $is_success, 'pending_retry' => $is_pending, 'status' => $snapshot['status']];
    }

    private function sync_item_with_ding_status($order, $item) {
        $transfer_ref = (string) $item->get_meta('_dc_transfer_ref');
        $distributor_ref = (string) $item->get_meta('_dc_distributor_ref');
        $account_number = (string) $item->get_meta('_dc_account_number');

        if ($transfer_ref === '' && $distributor_ref === '') {
            return ['synced' => false, 'success' => false, 'pending' => false];
        }

        $response = $this->api->list_transfer_records([
            'TransferRef' => $transfer_ref,
            'DistributorRef' => $distributor_ref,
            'AccountNumber' => $account_number,
            'Take' => 1,
        ]);

        if (is_wp_error($response)) {
            $order->add_order_note(sprintf(
                'DingConnect: no se pudo reconciliar estado para item #%d (%s). Motivo: %s',
                (int) $item->get_id(),
                $account_number,
                $response->get_error_message()
            ));
            return ['synced' => false, 'success' => false, 'pending' => false];
        }

        $items = $response['Result'] ?? $response['Items'] ?? [];
        if (empty($items[0]) || !is_array($items[0])) {
            $order->add_order_note(sprintf(
                'DingConnect: reconciliacion sin registros para item #%d (%s). Se mantiene estado local.',
                (int) $item->get_id(),
                $account_number
            ));
            return ['synced' => false, 'success' => false, 'pending' => false];
        }

        $previous_status = strtolower((string) $item->get_meta('_dc_transfer_status'));
        $snapshot = $this->extract_transfer_snapshot($items[0], $distributor_ref);
        if ($snapshot['status'] === '') {
            return ['synced' => false, 'success' => false, 'pending' => false];
        }

        $send_value = (float) $item->get_meta('_dc_send_value');
        $this->apply_transfer_snapshot_to_item($item, $snapshot, $account_number, $send_value);

        $is_now_success = $this->is_successful_transfer_status($snapshot['status']);

        if ($snapshot['status'] !== $previous_status) {
            $order->add_order_note(sprintf(
                'Reconciliacion DingConnect para %s (SKU: %s): estado %s.',
                $account_number,
                (string) $item->get_meta('_dc_sku_code'),
                $snapshot['status_label'] !== '' ? $snapshot['status_label'] : strtoupper($snapshot['status'])
            ));

            // Enviar email de confirmación cuando la reconciliación transiciona a éxito
            if ($is_now_success && !$this->is_successful_transfer_status($previous_status)) {
                $this->send_recarga_confirmacion_email($order, $item, $snapshot);
            }
        }

        return [
            'synced' => true,
            'success' => $is_now_success,
            'pending' => $this->is_pending_transfer_status($snapshot['status']),
            'status' => $snapshot['status'],
        ];
    }

    private function sync_order_status_with_recarga_outcome($order, $context = '') {
        if (!$order instanceof WC_Order) {
            return;
        }

        $summary = $this->build_recarga_status_summary($order);
        if ($summary['total'] < 1) {
            return;
        }

        $current_status = $this->get_order_status_slug($order);
        $blocked_statuses = ['cancelled', 'refunded', 'failed'];
        if (in_array($current_status, $blocked_statuses, true)) {
            $order->add_order_note(sprintf(
                'DingConnect: politica de estado omitida porque la orden esta en %s. Resumen recargas: %s',
                strtoupper($current_status),
                $this->format_recarga_summary_text($summary)
            ));
            return;
        }

        $target_status = 'processing';
        $decision = 'Pago confirmado y recarga en curso/pending.';

        if ($summary['success'] === $summary['total']) {
            $target_status = 'completed';
            $decision = 'Todas las recargas DingConnect quedaron confirmadas como exitosas.';
        } elseif ($summary['error'] > 0) {
            $target_status = 'on-hold';
            $decision = 'Existen recargas con error definitivo o escaladas a soporte.';
        }

        $reason = sprintf(
            'DingConnect policy (%s): %s Resumen: %s',
            $context !== '' ? $context : 'runtime',
            $decision,
            $this->format_recarga_summary_text($summary)
        );

        $order->update_meta_data('_dc_recargas_status_summary', wp_json_encode($summary));
        $order->update_meta_data('_dc_recargas_status_context', sanitize_text_field((string) $context));
        $order->update_meta_data('_dc_recargas_status_synced_at', current_time('mysql'));

        if ($current_status !== $target_status) {
            $this->update_order_status_slug($order, $target_status, $reason);
            return;
        }

        $order->add_order_note($reason);
    }

    private function build_recarga_status_summary($order) {
        $summary = [
            'total' => 0,
            'success' => 0,
            'pending' => 0,
            'error' => 0,
            'status_map' => [],
        ];

        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_dc_recarga') !== 'yes') {
                continue;
            }

            $summary['total']++;
            $status = strtolower(trim((string) $item->get_meta('_dc_transfer_status')));

            if ($status === '') {
                $status = 'not_started';
            }

            if (!isset($summary['status_map'][$status])) {
                $summary['status_map'][$status] = 0;
            }
            $summary['status_map'][$status]++;

            if ($this->is_successful_transfer_status($status)) {
                $summary['success']++;
                continue;
            }

            if ($this->is_pending_order_level_status($status)) {
                $summary['pending']++;
                continue;
            }

            $summary['error']++;
        }

        ksort($summary['status_map']);

        return $summary;
    }

    private function is_pending_order_level_status($status) {
        if ($this->is_pending_transfer_status($status)) {
            return true;
        }

        return in_array(strtolower((string) $status), ['pending_retry', 'not_started'], true);
    }

    private function format_recarga_summary_text(array $summary) {
        $parts = [
            sprintf('total=%d', (int) ($summary['total'] ?? 0)),
            sprintf('success=%d', (int) ($summary['success'] ?? 0)),
            sprintf('pending=%d', (int) ($summary['pending'] ?? 0)),
            sprintf('error=%d', (int) ($summary['error'] ?? 0)),
        ];

        $status_map = (array) ($summary['status_map'] ?? []);
        if (!empty($status_map)) {
            $status_parts = [];
            foreach ($status_map as $status => $count) {
                $status_parts[] = sprintf('%s:%d', (string) $status, (int) $count);
            }

            $parts[] = 'states=[' . implode(', ', $status_parts) . ']';
        }

        return implode(' | ', $parts);
    }

    private function get_order_status_slug($order) {
        if (!($order instanceof WC_Order)) {
            return '';
        }

        $status = get_post_status((int) $order->get_id());
        if (!is_string($status)) {
            return '';
        }

        return strpos($status, 'wc-') === 0 ? substr($status, 3) : $status;
    }

    private function update_order_status_slug($order, $target_status, $reason = '') {
        if (!($order instanceof WC_Order)) {
            return false;
        }

        $target_status = sanitize_key((string) $target_status);
        if ($target_status === '') {
            return false;
        }

        $updated = call_user_func('wp_update_post', [
            'ID' => (int) $order->get_id(),
            'post_status' => 'wc-' . $target_status,
        ], true);

        if (is_wp_error($updated)) {
            $order->add_order_note(sprintf(
                'DingConnect: no se pudo actualizar estado de orden a %s. Motivo: %s',
                strtoupper($target_status),
                $updated->get_error_message()
            ));
            return false;
        }

        if ($reason !== '') {
            $order->add_order_note($reason);
        }

        return true;
    }

    private function extract_transfer_snapshot($payload, $fallback_distributor_ref = '') {
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
            'receive_value' => (float) ($price['ReceiveValue'] ?? $first_item['ReceiveValue'] ?? 0),
            'receive_currency' => sanitize_text_field((string) ($price['ReceiveCurrencyIso'] ?? $first_item['ReceiveCurrencyIso'] ?? '')),
            'receipt_text' => sanitize_text_field((string) ($record['ReceiptText'] ?? $payload['ReceiptText'] ?? '')),
            'receipt_params' => is_array($record['ReceiptParams'] ?? null) ? $record['ReceiptParams'] : [],
        ];
    }

    private function apply_transfer_snapshot_to_item($item, $snapshot, $account_number, $send_value) {
        if (!empty($snapshot['transfer_ref'])) {
            $item->update_meta_data('_dc_transfer_ref', $snapshot['transfer_ref']);
        }

        if (!empty($snapshot['distributor_ref'])) {
            $item->update_meta_data('_dc_distributor_ref', $snapshot['distributor_ref']);
        }

        if (!empty($snapshot['status'])) {
            $item->update_meta_data('_dc_transfer_status', $snapshot['status']);
        }

        if ($this->is_pending_transfer_status($snapshot['status'])) {
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

        if ($this->is_successful_transfer_status($snapshot['status'])) {
            $item->delete_meta_data('_dc_transfer_error');
            $item->delete_meta_data('_dc_next_retry_at');
            $item->delete_meta_data('_dc_submitted_since');
            $item->delete_meta_data('_dc_submitted_retry_attempts');
        }

        $voucher_payload = [
            'transaction_id' => $snapshot['transfer_ref'],
            'status' => $snapshot['status_label'],
            'operator' => (string) $item->get_meta('_dc_provider_name'),
            'flow_kind' => (string) $item->get_meta('_dc_flow_kind'),
            'product_type' => (string) $item->get_meta('_dc_product_type'),
            'amount_sent' => $send_value,
            'amount_received' => $snapshot['receive_value'],
            'beneficiary_phone' => $account_number,
            'timestamp' => current_time('mysql'),
            'promotion' => '',
            'receipt_text' => $snapshot['receipt_text'],
            'receipt_params' => $snapshot['receipt_params'],
            'processing_state' => $snapshot['processing_state'],
            'bill_ref' => (string) $item->get_meta('_dc_bill_ref'),
            'customer_care_number' => (string) $item->get_meta('_dc_customer_care_number'),
        ];
        $item->update_meta_data('_dc_voucher_payload', wp_json_encode($voucher_payload));
        $item->save();
    }

    private function is_successful_transfer_status($status) {
        return in_array(strtolower((string) $status), ['success', 'completed', 'ok', 'approved'], true);
    }

    private function is_pending_transfer_status($status) {
        return in_array(strtolower((string) $status), ['submitted', 'pending', 'processing', 'queued', 'inprogress'], true);
    }

    private function get_retry_attempt_limit() {
        $options = $this->api->get_options();
        $attempts = (int) ($options['submitted_retry_max_attempts'] ?? 4);

        if ($attempts < 1) {
            $attempts = 1;
        }
        if ($attempts > 8) {
            $attempts = 8;
        }

        return $attempts;
    }

    private function get_retry_delay_minutes($attempt = 1) {
        $attempt = max(1, (int) $attempt);
        $schedule = $this->get_retry_backoff_schedule();
        $index = min($attempt - 1, count($schedule) - 1);

        return (int) $schedule[$index];
    }

    private function get_retry_backoff_schedule() {
        $options = $this->api->get_options();
        $raw = (string) ($options['submitted_retry_backoff_minutes'] ?? '10,20,40,80');
        $parts = preg_split('/[\s,;]+/', $raw);
        $minutes = [];

        foreach ((array) $parts as $part) {
            $value = (int) $part;
            if ($value < 1 || $value > 720) {
                continue;
            }
            $minutes[] = $value;
        }

        $minutes = array_values(array_unique($minutes));
        if (empty($minutes)) {
            $minutes = [10, 20, 40, 80];
        }

        return $minutes;
    }

    private function get_submitted_max_window_hours() {
        $options = $this->api->get_options();
        $hours = (int) ($options['submitted_max_window_hours'] ?? 12);
        if ($hours < 1) {
            $hours = 1;
        }
        if ($hours > 168) {
            $hours = 168;
        }

        return $hours;
    }

    private function get_non_retryable_error_codes() {
        $options = $this->api->get_options();
        $raw = (string) ($options['submitted_non_retryable_codes'] ?? 'InsufficientBalance,AccountNumberInvalid,RechargeNotAllowed');
        $parts = preg_split('/[\s,;]+/', $raw);
        $codes = [];

        foreach ((array) $parts as $part) {
            $clean = preg_replace('/[^A-Za-z0-9_]/', '', (string) $part);
            if ($clean !== '') {
                $codes[] = strtolower($clean);
            }
        }

        return array_values(array_unique($codes));
    }

    private function is_non_retryable_error($error) {
        if (!$error instanceof WP_Error) {
            return false;
        }

        $data = $error->get_error_data();
        $code = strtolower((string) ($data['ding_error_code'] ?? ''));
        if ($code === '') {
            return false;
        }

        return in_array($code, $this->get_non_retryable_error_codes(), true);
    }

    private function schedule_submitted_retry($order, $item, $reason = '') {
        if (!($order instanceof WC_Order) || !($item instanceof WC_Order_Item_Product)) {
            return false;
        }

        /** @var WC_Order_Item_Product $item */

        $status = strtolower((string) $item->get_meta('_dc_transfer_status'));
        if (!$this->is_pending_transfer_status($status) && $status !== 'pending_retry') {
            return false;
        }

        $submitted_since = (string) $item->get_meta('_dc_submitted_since');
        if ($submitted_since === '') {
            $submitted_since = current_time('mysql');
            call_user_func([$item, 'update_meta_data'], '_dc_submitted_since', $submitted_since);
        }

        $submitted_ts = strtotime($submitted_since);
        $max_window_seconds = $this->get_submitted_max_window_hours() * HOUR_IN_SECONDS;
        if ($submitted_ts && (time() - $submitted_ts) >= $max_window_seconds) {
            $this->escalate_submitted_item($order, $item, 'timeout_window');
            return false;
        }

        $attempt = (int) $item->get_meta('_dc_submitted_retry_attempts');
        $attempt++;
        call_user_func([$item, 'update_meta_data'], '_dc_submitted_retry_attempts', $attempt);

        if ($attempt > $this->get_retry_attempt_limit()) {
            $this->escalate_submitted_item($order, $item, 'attempt_limit');
            return false;
        }

        $delay_minutes = $this->get_retry_delay_minutes($attempt);
        $retry_ts = time() + ($delay_minutes * MINUTE_IN_SECONDS);

        call_user_func([$item, 'update_meta_data'], '_dc_transfer_status', 'pending_retry');
        call_user_func([$item, 'update_meta_data'], '_dc_next_retry_at', gmdate('Y-m-d H:i:s', $retry_ts));
        call_user_func([$item, 'save']);

        $item_id = (int) call_user_func([$item, 'get_id']);

        wp_clear_scheduled_hook('dc_recargas_retry_transfer', [(int) $order->get_id(), $item_id]);
        wp_schedule_single_event($retry_ts, 'dc_recargas_retry_transfer', [(int) $order->get_id(), $item_id]);

        $order->add_order_note(sprintf(
            'Recarga en estado pendiente (razón: %s). Reintento %d/%d en %d min para item %d.',
            $reason !== '' ? $reason : 'status_pending',
            $attempt,
            $this->get_retry_attempt_limit(),
            $delay_minutes,
            $item_id
        ));

        return true;
    }

    private function escalate_submitted_item($order, $item, $reason = '') {
        $item->update_meta_data('_dc_transfer_status', 'escalado_soporte');
        $item->delete_meta_data('_dc_next_retry_at');
        $item->save();

        wp_clear_scheduled_hook('dc_recargas_retry_transfer', [(int) $order->get_id(), (int) $item->get_id()]);

        $phone = (string) $item->get_meta('_dc_account_number');
        $sku = (string) $item->get_meta('_dc_sku_code');
        $since = (string) $item->get_meta('_dc_submitted_since');
        $note = sprintf(
            'ESCALADO SOPORTE: recarga pendiente prolongada para %s (SKU: %s). Motivo: %s. Desde: %s.',
            $phone,
            $sku,
            $reason !== '' ? $reason : 'submitted_prolongado',
            $since !== '' ? $since : 'N/A'
        );

        $order->add_order_note($note);

        $options = $this->api->get_options();
        $escalation_email = sanitize_email((string) ($options['submitted_escalation_email'] ?? ''));
        if ($escalation_email !== '' && function_exists('wp_mail')) {
            call_user_func('wp_mail',
                $escalation_email,
                sprintf('DingConnect escalado: Orden #%d', (int) $order->get_id()),
                $note
            );
        }
    }

    private function infer_flow_kind($product_type, $redemption_mechanism, $lookup_bills_required, $is_range, $bundle_label = '', $receipt_params = []) {
        $product_type = strtolower((string) $product_type);
        $redemption_mechanism = strtolower((string) $redemption_mechanism);
        $bundle_label = strtolower((string) $bundle_label);

        if (!empty($receipt_params['pin']) || $redemption_mechanism === 'readreceipt' || preg_match('/voucher|pin|gift|digital/', $product_type)) {
            return 'voucher';
        }

        if ($lookup_bills_required || preg_match('/electric|bill|utility|power/', $product_type)) {
            return 'electricity';
        }

        if (preg_match('/dth|satellite|tv|dish/', $product_type . ' ' . $bundle_label)) {
            return 'dth';
        }

        if ($is_range) {
            return 'range';
        }

        return 'mobile';
    }

    private function get_item_voucher_payload($item) {
        $payload = json_decode((string) $item->get_meta('_dc_voucher_payload'), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        if (!isset($payload['flow_kind']) || $payload['flow_kind'] === '') {
            $payload['flow_kind'] = (string) $item->get_meta('_dc_flow_kind');
        }

        if ($payload['flow_kind'] === '') {
            $payload['flow_kind'] = $this->infer_flow_kind(
                (string) $item->get_meta('_dc_product_type'),
                (string) $item->get_meta('_dc_redemption_mechanism'),
                (string) $item->get_meta('_dc_lookup_bills_required') === 'yes',
                (string) $item->get_meta('_dc_is_range') === 'yes',
                (string) $item->get_meta('_dc_bundle_label'),
                is_array($payload['receipt_params'] ?? null) ? $payload['receipt_params'] : []
            );
        }

        if (!isset($payload['bill_ref']) || $payload['bill_ref'] === '') {
            $payload['bill_ref'] = (string) $item->get_meta('_dc_bill_ref');
        }

        return $payload;
    }

    private function get_item_copy_title($flow_kind, $status) {
        $state_key = 'error';
        if ($this->is_successful_transfer_status($status)) {
            $state_key = 'success';
        } elseif ($this->is_pending_transfer_status($status) || in_array($status, ['pending_retry', 'escalado_soporte'], true)) {
            $state_key = 'pending';
        }

        $titles = [
            'voucher' => [
                'success' => 'Voucher listo para usar',
                'pending' => 'Voucher en validacion',
                'error' => 'Voucher no confirmado',
            ],
            'electricity' => [
                'success' => 'Pago del servicio registrado',
                'pending' => 'Pago del servicio en validacion',
                'error' => 'Pago del servicio no confirmado',
            ],
            'dth' => [
                'success' => 'Recarga DTH registrada',
                'pending' => 'Recarga DTH en validacion',
                'error' => 'Recarga DTH no confirmada',
            ],
            'range' => [
                'success' => 'Recarga movil confirmada',
                'pending' => 'Recarga movil en validacion',
                'error' => 'Recarga movil no confirmada',
            ],
            'mobile' => [
                'success' => 'Recarga procesada',
                'pending' => 'Recarga en validacion',
                'error' => 'Recarga no confirmada',
            ],
        ];

        $flow_titles = $titles[$flow_kind] ?? $titles['mobile'];
        return $flow_titles[$state_key] ?? $flow_titles['pending'];
    }

    private function get_item_receipt_param($payload, $expected_key) {
        $expected_key = strtolower((string) $expected_key);
        $receipt_params = is_array($payload['receipt_params'] ?? null) ? $payload['receipt_params'] : [];

        foreach ($receipt_params as $key => $value) {
            if (strtolower((string) $key) === $expected_key) {
                return sanitize_text_field((string) $value);
            }
        }

        return '';
    }

    private function order_has_pending_recargas($order) {
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_dc_recarga') !== 'yes') {
                continue;
            }

            $status = strtolower((string) $item->get_meta('_dc_transfer_status'));
            if ($this->is_pending_transfer_status($status) || in_array($status, ['pending_retry', 'escalado_soporte'], true)) {
                return true;
            }
        }

        return false;
    }

    private function collect_order_voucher_rows($order) {
        $rows = [];

        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_dc_recarga') !== 'yes') {
                continue;
            }

            $phone = (string) $item->get_meta('_dc_account_number');
            $status = strtoupper((string) $item->get_meta('_dc_transfer_status'));
            $ref = (string) $item->get_meta('_dc_transfer_ref');
            $provider = (string) $item->get_meta('_dc_provider_name');
            $public_amount = (float) $item->get_meta('_dc_public_price');
            $public_currency = (string) $item->get_meta('_dc_public_currency_iso');
            $send_amount = (float) $item->get_meta('_dc_send_value');
            $send_currency = (string) $item->get_meta('_dc_send_currency_iso');
            $payload = $this->get_item_voucher_payload($item);
            $flow_kind = (string) ($payload['flow_kind'] ?? 'mobile');
            $pin = $this->get_item_receipt_param($payload, 'pin');
            $provider_ref = $this->get_item_receipt_param($payload, 'providerRef');
            $bill_ref = sanitize_text_field((string) ($payload['bill_ref'] ?? ''));
            $title = $this->get_item_copy_title($flow_kind, strtolower($status));
            $parts = [
                $title . ': ' . trim($provider . ' ' . $phone),
                (string) $item->get_meta('_dc_bundle_label'),
                $status !== '' ? $status : 'PENDING',
                sprintf('Precio %s %.2f', $public_currency !== '' ? $public_currency : $send_currency, $public_amount > 0 ? $public_amount : $send_amount),
                sprintf('Operación %s %.2f', $send_currency, $send_amount),
            ];

            if ($bill_ref !== '') {
                $parts[] = 'Factura ' . $bill_ref;
            }

            if ($pin !== '') {
                $parts[] = 'PIN ' . $pin;
            }

            if ($provider_ref !== '') {
                $parts[] = 'Ref prov. ' . $provider_ref;
            }

            $parts[] = 'Ref ' . ($ref !== '' ? $ref : 'N/A');

            if (in_array(strtolower($status), ['submitted', 'pending', 'processing', 'pending_retry', 'escalado_soporte'], true)) {
                $parts[] = 'No repetir compra';
            }

            $rows[] = implode(' | ', array_filter($parts, static function ($part) {
                return $part !== '';
            }));
        }

        return $rows;
    }

    private function sanitize_phone($phone) {
        $raw = preg_replace('/[^\d+]/', '', (string) $phone);
        if (strpos($raw, '+') !== 0) {
            $raw = '+' . ltrim($raw, '+');
        }
        return $raw;
    }
}
