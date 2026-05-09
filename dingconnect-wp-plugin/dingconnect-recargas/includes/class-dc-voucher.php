<?php

if (!defined('ABSPATH')) {
    exit;
}

class DC_Recargas_Voucher {
    /**
     * @param WC_Order              $order
     * @param WC_Order_Item_Product $item
     * @param array                 $snapshot
     * @return array
     */
    public function build_snapshot($order, $item, array $snapshot): array {
        /** @var WC_Order $order */
        /** @var WC_Order_Item_Product $item */
        $send_currency = (string) $item->get_meta('_dc_send_currency_iso');
        $public_price = (float) $item->get_meta('_dc_public_price');
        $public_currency = (string) $item->get_meta('_dc_public_currency_iso');
        if ($public_price <= 0) {
            $public_price = (float) $item->get_meta('_dc_send_value');
        }
        if ($public_currency === '') {
            $public_currency = $send_currency;
        }

        $payload = [
            'contract_version' => 'voucher.v1',
            'order_id' => (int) $order->get_id(),
            'order_item_id' => (int) call_user_func([$item, 'get_id']),
            'transaction_id' => (string) ($snapshot['transfer_ref'] ?? ''),
            'distributor_ref' => (string) ($snapshot['distributor_ref'] ?? ''),
            'status' => (string) ($snapshot['status_label'] ?? ''),
            'operator' => (string) $item->get_meta('_dc_provider_name'),
            'public_price' => $public_price,
            'public_currency' => $public_currency,
            'amount_sent' => (float) $item->get_meta('_dc_send_value'),
            'amount_sent_currency' => $send_currency,
            'amount_received' => (float) ($snapshot['receive_value'] ?? 0),
            'country_iso' => (string) $item->get_meta('_dc_country_iso'),
            'bundle' => (string) $item->get_meta('_dc_bundle_label'),
            'beneficiary' => (string) $item->get_meta('_dc_account_number'),
            'timestamp' => current_time('mysql'),
            'receipt_text' => (string) ($snapshot['receipt_text'] ?? ''),
            'receipt_params' => (array) ($snapshot['receipt_params'] ?? []),
        ];
        $payload['voucher_hash'] = hash('sha256', wp_json_encode($payload));
        return $payload;
    }

    /**
     * @param WC_Order_Item_Product $item
     * @return array|null
     */
    public function get_item_voucher_v2($item): ?array {
        /** @var WC_Order_Item_Product $item */
        $meta = $item->get_meta('_dc_voucher_payload_v2');
        if (empty($meta)) {
            return null;
        }
        $decoded = json_decode((string) $meta, true);
        return is_array($decoded) ? $decoded : null;
    }
}
