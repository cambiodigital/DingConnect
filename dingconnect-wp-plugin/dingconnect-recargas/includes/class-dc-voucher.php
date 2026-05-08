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
    public function build_snapshot(WC_Order $order, WC_Order_Item_Product $item, array $snapshot): array {
        $payload = [
            'contract_version' => 'voucher.v1',
            'order_id' => (int) $order->get_id(),
            'order_item_id' => (int) $item->get_id(),
            'transaction_id' => (string) ($snapshot['transfer_ref'] ?? ''),
            'distributor_ref' => (string) ($snapshot['distributor_ref'] ?? ''),
            'status' => (string) ($snapshot['status_label'] ?? ''),
            'operator' => (string) $item->get_meta('_dc_provider_name'),
            'amount_sent' => (float) $item->get_meta('_dc_send_value'),
            'amount_received' => (float) ($snapshot['receive_value'] ?? 0),
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
    public function get_item_voucher_v2(WC_Order_Item_Product $item): ?array {
        $meta = $item->get_meta('_dc_voucher_payload_v2');
        if (empty($meta)) {
            return null;
        }
        $decoded = json_decode((string) $meta, true);
        return is_array($decoded) ? $decoded : null;
    }
}
