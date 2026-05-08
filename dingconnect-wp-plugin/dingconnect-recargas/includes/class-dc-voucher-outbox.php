<?php

if (!defined('ABSPATH')) {
    exit;
}

class DC_Recargas_Voucher_Outbox {
    private $api;

    public function __construct($api) {
        $this->api = $api;
        add_action('dc_voucher_send_email', [$this, 'handle_send_email_job'], 10, 4);
    }

    public function enqueue(int $order_id, int $item_id, string $voucher_hash): void {
        $job_key = sprintf('%d:%d:%s', $order_id, $item_id, $voucher_hash);
        if (get_transient('dc_voucher_outbox_' . md5($job_key))) { 
            return; 
        }
        set_transient('dc_voucher_outbox_' . md5($job_key), 1, DAY_IN_SECONDS);
        wp_schedule_single_event(time() + 10, 'dc_voucher_send_email', [$order_id, $item_id, $voucher_hash, 1]);
    }

    public function handle_send_email_job(int $order_id, int $item_id, string $voucher_hash, int $attempt = 1): void {
        $options = $this->api->get_options();
        $max_attempts = (int) ($options['voucher_outbox_max_attempts'] ?? 6);

        if (!class_exists('WC_Emails')) {
            WC()->mailer();
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        try {
            do_action('dc_send_voucher_email', $order_id, $item_id, $voucher_hash);
            
            $this->api->log_operational_event('voucher_email_sent', [
                'order_id' => $order_id,
                'item_id' => $item_id,
                'voucher_hash' => $voucher_hash,
                'attempt' => $attempt
            ]);
            
            delete_transient('dc_voucher_outbox_' . md5(sprintf('%d:%d:%s', $order_id, $item_id, $voucher_hash)));
        } catch (Exception $e) {
            $this->api->log_operational_event('voucher_email_failed', [
                'order_id' => $order_id,
                'item_id' => $item_id,
                'voucher_hash' => $voucher_hash,
                'attempt' => $attempt,
                'error' => $e->getMessage()
            ]);

            if ($attempt < $max_attempts) {
                $backoff_str = (string) ($options['voucher_outbox_backoff_minutes'] ?? '1,2,5,10,20,30');
                $backoffs = array_map('intval', explode(',', $backoff_str));
                $backoff_index = min($attempt - 1, count($backoffs) - 1);
                $delay_minutes = $backoffs[$backoff_index] ?? 5;
                
                wp_schedule_single_event(time() + ($delay_minutes * MINUTE_IN_SECONDS), 'dc_voucher_send_email', [$order_id, $item_id, $voucher_hash, $attempt + 1]);
            }
        }
    }
}
