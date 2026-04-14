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

        // Order processing - fire DingConnect transfer on payment
        add_action('woocommerce_order_status_processing', [$this, 'process_recarga_on_payment']);
        add_action('woocommerce_order_status_completed', [$this, 'process_recarga_on_payment']);

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
        $product->save();

        update_option('dc_recargas_wc_product_id', $product->get_id());
        return $product->get_id();
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
            'unique_key'          => md5($data['account_number'] . $data['sku_code'] . microtime()),
        ];

        $added = WC()->cart->add_to_cart($product_id, 1, 0, [], $cart_item_data);

        if (!$added) {
            return new WP_Error('dc_cart_error', 'No se pudo a�adir la recarga al carrito.');
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

        $item_data[] = ['key' => 'Pa�s',     'value' => strtoupper($cart_item['dc_country_iso'] ?? '')];
        $item_data[] = ['key' => 'Tel�fono', 'value' => $cart_item['dc_account_number'] ?? ''];
        $item_data[] = ['key' => 'Operador', 'value' => $cart_item['dc_provider_name'] ?? ''];
        $item_data[] = ['key' => 'Paquete',  'value' => $cart_item['dc_bundle_label'] ?? ''];
        $item_data[] = ['key' => 'Moneda',   'value' => $cart_item['dc_send_currency_iso'] ?? ''];

        return $item_data;
    }

    /** Override product name in cart with provider + bundle label. */
    public function custom_cart_item_name($name, $cart_item, $cart_item_key) {
        if (!empty($cart_item['dc_recarga'])) {
            $label    = $cart_item['dc_bundle_label'] ?? 'Recarga';
            $provider = $cart_item['dc_provider_name'] ?? '';
            return esc_html($provider ? $provider . ' � ' . $label : $label);
        }
        return $name;
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

    /* ---------------------------------------------------------------
     * 8. Checkout Fields & Phone Login
     * ------------------------------------------------------------- */

    /** Make billing phone required and prominent when cart has recargas. */
    public function customize_checkout_fields($fields) {
        if (!$this->cart_has_recargas()) return $fields;

        if (isset($fields['billing']['billing_phone'])) {
            $fields['billing']['billing_phone']['required'] = true;
            $fields['billing']['billing_phone']['priority'] = 15;
            $fields['billing']['billing_phone']['label']    = 'N�mero de celular';
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
     * 9. Order Processing � Execute Real DingConnect Transfer
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

        // Prevent double processing
        if ($order->get_meta('_dc_recargas_processed')) return;

        $has_recargas = false;
        $all_success  = true;

        foreach ($order->get_items() as $item_id => $item) {
            if ($item->get_meta('_dc_recarga') !== 'yes') continue;

            $has_recargas = true;

            // Skip if this specific item was already processed
            if ($item->get_meta('_dc_transfer_ref')) continue;

            $account_number   = $item->get_meta('_dc_account_number');
            $sku_code         = $item->get_meta('_dc_sku_code');
            $send_value       = (float) $item->get_meta('_dc_send_value');
            $send_currency_iso = $item->get_meta('_dc_send_currency_iso');

            $distributor_ref = $this->api->new_ref();

            $payload = [
                'DistributorRef'  => $distributor_ref,
                'AccountNumber'   => $account_number,
                'SkuCode'         => $sku_code,
                'SendValue'       => $send_value,
                'SendCurrencyIso' => $send_currency_iso,
                'ValidateOnly'    => false, // REAL transfer � payment already confirmed
            ];

            $response = $this->api->send_transfer($payload);

            // Log the transfer
            $this->api->log_transfer($account_number, $sku_code, $send_value, $send_currency_iso, $distributor_ref, $response);

            if (is_wp_error($response)) {
                $all_success = false;
                $item->add_meta_data('_dc_transfer_status', 'error', true);
                $item->add_meta_data('_dc_transfer_error', $response->get_error_message(), true);
                $item->save();

                $order->add_order_note(sprintf(
                    'Recarga FALLIDA para %s (SKU: %s): %s',
                    $account_number,
                    $sku_code,
                    $response->get_error_message()
                ));
            } else {
                $items_data      = $response['Items'] ?? $response['Result'] ?? [];
                $first_item      = $items_data[0] ?? [];
                $status           = $first_item['Status'] ?? 'Unknown';
                $transfer_ref     = $response['TransferRef'] ?? '';
                $receive_value    = $first_item['ReceiveValue'] ?? '';
                $receive_currency = $first_item['ReceiveCurrencyIso'] ?? '';

                $item->add_meta_data('_dc_transfer_ref',    $transfer_ref, true);
                $item->add_meta_data('_dc_transfer_status', $status, true);
                $item->add_meta_data('_dc_distributor_ref', $distributor_ref, true);
                $item->save();

                $note = sprintf(
                    'Recarga EXITOSA para %s (SKU: %s) � Ref: %s � Estado: %s',
                    $account_number,
                    $sku_code,
                    $transfer_ref,
                    $status
                );
                if ($receive_value) {
                    $note .= sprintf(' � Recibe: %s %s', $receive_currency, $receive_value);
                }
                $order->add_order_note($note);
            }
        }

        if ($has_recargas) {
            $order->update_meta_data('_dc_recargas_processed', current_time('mysql'));

            if (!$all_success) {
                $order->update_meta_data('_dc_recargas_has_errors', 'yes');
                $order->add_order_note('?? Algunas recargas fallaron. Revisa los detalles de cada item.');
            }

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
            '_dc_account_number'  => 'Tel�fono',
            '_dc_country_iso'     => 'Pa�s',
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

    /* ---------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------- */

    private function sanitize_phone($phone) {
        $raw = preg_replace('/[^\d+]/', '', (string) $phone);
        if (strpos($raw, '+') !== 0) {
            $raw = '+' . ltrim($raw, '+');
        }
        return $raw;
    }
}
