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

        // Add-to-cart filter (delegated from REST class)
        add_filter('dc_recargas_add_to_cart', [$this, 'handle_add_to_cart'], 10, 2);

        // Cart customization
        add_action('woocommerce_before_calculate_totals', [$this, 'set_custom_price'], 20, 1);
        add_filter('woocommerce_get_item_data', [$this, 'display_cart_item_data'], 10, 2);
        add_filter('woocommerce_cart_item_name', [$this, 'custom_cart_item_name'], 10, 3);
        add_filter('woocommerce_cart_item_thumbnail', [$this, 'custom_cart_item_thumbnail'], 10, 3);

        // Order meta
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_order_item_meta'], 10, 4);

        // Mandatory registration
        add_filter('woocommerce_checkout_registration_required', [$this, 'force_registration']);
        add_filter('pre_option_woocommerce_enable_guest_checkout', [$this, 'disable_guest_checkout']);

        // Checkout fields
        add_filter('woocommerce_checkout_fields', [$this, 'customize_checkout_fields']);
        add_filter('woocommerce_new_customer_data', [$this, 'prefill_customer_phone']);

        // Phone login
        add_filter('authenticate', [$this, 'authenticate_by_phone'], 30, 3);

        // Order processing - fire DingConnect transfer on payment.
        //
        // Three hooks cover ALL gateway behaviors:
        // 1. woocommerce_payment_complete — canonical event when gateway calls
        //    $order->payment_complete(). Fires AFTER status change, so is_paid() is true.
        //    This is the primary trigger for well-behaved gateways (Stripe, PayPal, etc.).
        // 2. woocommerce_order_status_processing — fallback for gateways that set the
        //    status directly to "processing" without calling payment_complete().
        // 3. woocommerce_order_status_completed — fallback for gateways that mark the
        //    order as "completed" instead of "processing" on successful payment.
        //
        // Duplicate execution is prevented by is_item_already_successful() (checks
        // _dc_transfer_ref / _dc_transfer_status) and the 60s transient lock inside
        // attempt_transfer_for_item(). It is safe for all three hooks to fire.
        add_action('woocommerce_payment_complete', [$this, 'process_recarga_on_payment']);
        add_action('woocommerce_order_status_processing', [$this, 'process_recarga_on_payment']);
        add_action('woocommerce_order_status_completed', [$this, 'process_recarga_on_payment']);
        add_action('dc_recargas_retry_transfer', [$this, 'process_retry_transfer'], 10, 2);

        // Restrict checkout payment gateways for recarga carts
        add_filter('woocommerce_available_payment_gateways', [$this, 'filter_available_payment_gateways'], 20);

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

        update_option('dc_recargas_wc_product_id', $product_id);
        return $product_id;
    }

    /* ---------------------------------------------------------------
     * 2. WC Session Helper (critical for REST + WC cart)
     * ------------------------------------------------------------- */

    private function ensure_wc_session() {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            if (WC()->session === null) {
                WC()->session = new WC_Session_Handler();
                WC()->session->init();
            }
            if (WC()->cart === null) {
                WC()->cart = new WC_Cart();
                WC()->cart->get_cart();
            }
            if (WC()->customer === null) {
                WC()->customer = new WC_Customer(get_current_user_id());
            }
        }
    }


    /* ---------------------------------------------------------------
     * 3. Add-to-Cart Handler (called via dc_recargas_add_to_cart filter)
     * ------------------------------------------------------------- */

    public function handle_add_to_cart($result, $data) {
        $this->ensure_wc_session();

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
            'dc_provider_name'    => $data['provider_name'],
            'dc_bundle_label'     => $data['bundle_label'],
            'dc_logo_url'         => esc_url_raw((string) ($data['logo_url'] ?? '')),
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

        $added = WC()->cart->add_to_cart($product_id, 1, 0, [], $cart_item_data);

        if (!$added) {
            return new WP_Error('dc_cart_error', 'No se pudo añadir la recarga al carrito.');
        }

        return true;
    }

    /* ---------------------------------------------------------------
     * 5. Cart Customization Hooks
     * ------------------------------------------------------------- */

    /** Set the dynamic price for recarga cart items. */
    public function set_custom_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        if (did_action('woocommerce_before_calculate_totals') >= 2) return;

        foreach ($cart->get_cart() as $cart_item) {
            if (!empty($cart_item['dc_recarga']) && isset($cart_item['dc_send_value'])) {
                $cart_item['data']->set_price((float) $cart_item['dc_send_value']);
            }
        }
    }

    /** Display recarga details in cart/checkout line items. */
    public function display_cart_item_data($item_data, $cart_item) {
        if (empty($cart_item['dc_recarga'])) return $item_data;

        $item_data[] = ['key' => 'País',     'value' => strtoupper($cart_item['dc_country_iso'] ?? '')];
        $item_data[] = ['key' => 'Teléfono', 'value' => $cart_item['dc_account_number'] ?? ''];
        $item_data[] = ['key' => 'Operador', 'value' => $cart_item['dc_provider_name'] ?? ''];
        $item_data[] = ['key' => 'Paquete',  'value' => $cart_item['dc_bundle_label'] ?? ''];
        $item_data[] = ['key' => 'Moneda',   'value' => $cart_item['dc_send_currency_iso'] ?? ''];

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
            $provider = $cart_item['dc_provider_name'] ?? '';
            return esc_html($provider ? $provider . ' - ' . $label : $label);
        }
        return $name;
    }

    public function custom_cart_item_thumbnail($thumbnail, $cart_item, $cart_item_key) {
        if (empty($cart_item['dc_recarga'])) {
            return $thumbnail;
        }

        $logo_url = esc_url((string) ($cart_item['dc_logo_url'] ?? ''));
        if ($logo_url === '') {
            return $thumbnail;
        }

        $label = trim((string) ($cart_item['dc_bundle_label'] ?? 'Recarga'));
        $provider = trim((string) ($cart_item['dc_provider_name'] ?? ''));
        $alt = trim($provider !== '' ? $provider . ' - ' . $label : $label);

        return sprintf(
            '<img src="%1$s" alt="%2$s" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" loading="lazy" decoding="async" referrerpolicy="no-referrer" />',
            $logo_url,
            esc_attr($alt)
        );
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
        $item->add_meta_data('_dc_provider_name',     $values['dc_provider_name'] ?? '', true);
        $item->add_meta_data('_dc_bundle_label',      $values['dc_bundle_label'] ?? '', true);
        $item->add_meta_data('_dc_logo_url',          $values['dc_logo_url'] ?? '', true);
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

    public function force_registration($registration_required) {
        if ($this->cart_has_recargas()) {
            return true;
        }
        return $registration_required;
    }

    public function disable_guest_checkout($value) {
        if ($this->cart_has_recargas()) {
            return 'no';
        }
        return $value;
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

        return array_filter($gateways, function ($gateway, $gateway_id) use ($allowed_map) {
            return isset($allowed_map[sanitize_key((string) $gateway_id)]);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /* ---------------------------------------------------------------
     * 8. Checkout Fields & Phone Login
     * ------------------------------------------------------------- */

    /** Make billing phone required and prominent when cart has recargas. */
    public function customize_checkout_fields($fields) {
        if (!$this->cart_has_recargas()) return $fields;

        if (isset($fields['billing']['billing_phone'])) {
            $fields['billing']['billing_phone']['required'] = true;
            $fields['billing']['billing_phone']['priority'] = 15;
            $fields['billing']['billing_phone']['label']    = 'Número de celular';
        }

        return $fields;
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
            return;
        }

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

            if (!empty($sync_result['query_failed'])) {
                $all_success = false;
                $has_pending_retry = true;
                $scheduled = $this->schedule_submitted_retry($order, $item, 'sync_query_failed_on_payment');
                if (!$scheduled) {
                    $order->add_order_note(sprintf(
                        'Conciliación DingConnect no disponible para item %d. Se pospone reenvío para evitar duplicados.',
                        (int) $item->get_id()
                    ));
                }
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

            $order->save();
        }
    }

    public function process_retry_transfer($order_id, $item_id) {
        $order = wc_get_order((int) $order_id);
        if (!$order || !$order->is_paid()) {
            return;
        }

        $item = $order->get_item((int) $item_id);
        if (!$item instanceof WC_Order_Item_Product) {
            return;
        }

        if ($item->get_meta('_dc_recarga') !== 'yes' || $this->is_item_already_successful($item)) {
            return;
        }

        $sync_result = $this->sync_item_with_ding_status($order, $item);
        if (!empty($sync_result['success'])) {
            $order->save();
            return;
        }

        if (!empty($sync_result['pending'])) {
            $this->schedule_submitted_retry($order, $item, 'sync_on_retry');
            $order->save();
            return;
        }

        if (!empty($sync_result['query_failed'])) {
            $scheduled = $this->schedule_submitted_retry($order, $item, 'sync_query_failed');
            if (!$scheduled) {
                $order_item_id = (int) call_user_func([$item, 'get_id']);
                $order->add_order_note(sprintf(
                    'Reintento diferido: no se pudo conciliar estado DingConnect para item %d. Se evita nuevo SendTransfer hasta recuperar conciliación.',
                    $order_item_id
                ));
            }
            $order->save();
            return;
        }

        $this->attempt_transfer_for_item($order, $item, false);
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

            if (!empty($sync_result['query_failed'])) {
                $scheduled = $this->schedule_submitted_retry($order, $item, 'manual_reconcile_query_failed');
                if (!$scheduled) {
                    $order->add_order_note(sprintf(
                        'Conciliación manual pendiente para item %d: sin respuesta de ListTransferRecords; se evita reenviar SendTransfer.',
                        (int) $item->get_id()
                    ));
                }
                $processed++;
                continue;
            }

            $this->attempt_transfer_for_item($order, $item, true);
            $processed++;
        }

        if ($processed > 0) {
            $order->add_order_note(sprintf('Reconciliacion manual ejecutada para %d item(s) DingConnect.', $processed));
            $order->save();
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
            '_dc_bundle_label'    => 'Paquete',
            '_dc_transfer_ref'    => 'Ref. DingConnect',
            '_dc_distributor_ref' => 'Ref. Distribuidor',
            '_dc_transfer_status' => 'Estado transferencia',
            '_dc_transfer_error'  => 'Error',
        ];

        echo '<div class="dc-order-meta" style="margin-top:8px;padding:8px;background:#f8fafc;border-radius:6px;font-size:12px;">';
        echo '<strong style="display:block;margin-bottom:4px;color:#1e3a8a;">Datos de Recarga DingConnect</strong>';

        foreach ($fields as $meta_key => $label) {
            $value = $item->get_meta($meta_key);
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
        $transfer_ref = (string) $item->get_meta('_dc_transfer_ref');

        if ($transfer_ref !== '') {
            return true;
        }

        return $this->is_successful_transfer_status($status);
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
            return ['success' => false, 'pending_retry' => true, 'message' => 'lock'];
        }
        set_transient($lock_key, 1, 60);

        $attempt = (int) $item->get_meta('_dc_retry_attempts');
        $attempt++;
        $item->update_meta_data('_dc_retry_attempts', $attempt);
        $item->update_meta_data('_dc_last_attempt_at', current_time('mysql'));

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

        delete_transient($lock_key);

        return ['success' => $is_success, 'pending_retry' => $is_pending, 'status' => $snapshot['status']];
    }

    private function sync_item_with_ding_status($order, $item) {
        $transfer_ref = (string) $item->get_meta('_dc_transfer_ref');
        $distributor_ref = (string) $item->get_meta('_dc_distributor_ref');
        $account_number = (string) $item->get_meta('_dc_account_number');

        if ($transfer_ref === '' && $distributor_ref === '') {
            return ['synced' => false, 'success' => false, 'pending' => false, 'query_failed' => false, 'has_refs' => false];
        }

        $response = $this->api->list_transfer_records([
            'TransferRef' => $transfer_ref,
            'DistributorRef' => $distributor_ref,
            'AccountNumber' => $account_number,
            'Take' => 1,
        ]);

        if (is_wp_error($response)) {
            return ['synced' => false, 'success' => false, 'pending' => false, 'query_failed' => true, 'has_refs' => true];
        }

        $items = $response['Result'] ?? $response['Items'] ?? [];
        if (empty($items[0]) || !is_array($items[0])) {
            return ['synced' => false, 'success' => false, 'pending' => false, 'query_failed' => true, 'has_refs' => true];
        }

        $previous_status = strtolower((string) $item->get_meta('_dc_transfer_status'));
        $snapshot = $this->extract_transfer_snapshot($items[0], $distributor_ref);
        if ($snapshot['status'] === '') {
            return ['synced' => false, 'success' => false, 'pending' => false, 'query_failed' => true, 'has_refs' => true];
        }

        $send_value = (float) $item->get_meta('_dc_send_value');
        $this->apply_transfer_snapshot_to_item($item, $snapshot, $account_number, $send_value);

        if ($snapshot['status'] !== $previous_status) {
            $order->add_order_note(sprintf(
                'Reconciliacion DingConnect para %s (SKU: %s): estado %s.',
                $account_number,
                (string) $item->get_meta('_dc_sku_code'),
                $snapshot['status_label'] !== '' ? $snapshot['status_label'] : strtoupper($snapshot['status'])
            ));
        }

        return [
            'synced' => true,
            'success' => $this->is_successful_transfer_status($snapshot['status']),
            'pending' => $this->is_pending_transfer_status($snapshot['status']),
            'query_failed' => false,
            'has_refs' => true,
            'status' => $snapshot['status'],
        ];
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
            $amount = (float) $item->get_meta('_dc_send_value');
            $currency = (string) $item->get_meta('_dc_send_currency_iso');
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
                sprintf('%s %.2f', $currency, $amount),
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
