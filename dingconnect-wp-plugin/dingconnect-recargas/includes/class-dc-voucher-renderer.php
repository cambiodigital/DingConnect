<?php

if (!defined('ABSPATH')) {
    exit;
}

class DC_Recargas_Voucher_Renderer {
    public function render_html(array $voucher): string {
        $html = '<div class="dc-voucher-container" style="margin-top: 20px; padding: 16px; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc;">';
        $html .= '<h3 style="margin-top: 0; margin-bottom: 14px; color: #0f172a; font-size: 1.05em; letter-spacing: -0.2px;">Detalle de la recarga</h3>';
        
        $html .= '<table style="width: 100%; border-collapse: collapse; text-align: left;">';
        $html .= '<tbody>';
        
        foreach ($this->render_rows($voucher) as $label => $value) {
            if ($value === '') {
                continue;
            }
            $html .= '<tr>';
            $html .= '<th style="padding: 8px 10px; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: normal; width: 42%;">' . esc_html($label) . '</th>';
            $html .= '<td style="padding: 8px 10px; border-bottom: 1px solid #e2e8f0; color: #0f172a; font-weight: 500;">' . esc_html($value) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';

        if (!empty($voucher['receipt_text'])) {
            $html .= '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #cbd5e1; font-size: 0.9em; color: #475569;">';
            $html .= nl2br(esc_html($voucher['receipt_text']));
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    public function render_plain(array $voucher): string {
        $text = "Detalles de la Recarga:\n";
        $text .= "------------------------\n";
        
        foreach ($this->render_rows($voucher) as $label => $value) {
            if ($value === '') {
                continue;
            }
            $text .= $label . ': ' . $value . "\n";
        }
        
        if (!empty($voucher['receipt_text'])) {
            $text .= "\nInstrucciones/Recibo:\n";
            $text .= "------------------------\n";
            $text .= $voucher['receipt_text'] . "\n";
        }
        
        return $text;
    }

    public function render_rows(array $voucher): array {
        $beneficiary = (string) ($voucher['beneficiary'] ?? ($voucher['beneficiary_phone'] ?? ''));
        $status = self::translate_status((string) ($voucher['status'] ?? ''));
        $ref = (string) ($voucher['transaction_id'] ?? '');
        $country = (string) ($voucher['country_iso'] ?? '');
        $bundle = (string) ($voucher['bundle'] ?? '');

        $paid_amount = '';
        if (isset($voucher['public_price']) && (float) $voucher['public_price'] > 0) {
            $paid_amount = number_format((float) $voucher['public_price'], 2);
            $paid_currency = (string) ($voucher['public_currency'] ?? '');
            if ($paid_currency !== '') {
                $paid_amount .= ' ' . $paid_currency;
            }
        } elseif (isset($voucher['amount_sent'])) {
            $paid_amount = number_format((float) $voucher['amount_sent'], 2);
            $sent_currency = (string) ($voucher['amount_sent_currency'] ?? '');
            if ($sent_currency !== '') {
                $paid_amount .= ' ' . $sent_currency;
            }
        }

        $received_amount = '';
        if (isset($voucher['amount_received']) && (float) $voucher['amount_received'] > 0) {
            $received_amount = number_format((float) $voucher['amount_received'], 2);
            $receive_currency = (string) ($voucher['amount_received_currency'] ?? '');
            if ($receive_currency !== '') {
                $received_amount .= ' ' . $receive_currency;
            }
        }

        $timestamp = (string) ($voucher['timestamp'] ?? '');
        if ($timestamp !== '') {
            $ts = strtotime($timestamp);
            if ($ts) {
                $timestamp = function_exists('date_i18n') ? date_i18n('d/m/Y H:i', $ts) : date('d/m/Y H:i', $ts);
            }
        }

        $receipt_params = is_array($voucher['receipt_params'] ?? null) ? $voucher['receipt_params'] : [];
        $pin = '';
        $provider_ref = '';
        foreach ($receipt_params as $key => $value) {
            $k = strtolower((string) $key);
            if ($pin === '' && $k === 'pin') {
                $pin = sanitize_text_field((string) $value);
            }
            if ($provider_ref === '' && $k === 'providerref') {
                $provider_ref = sanitize_text_field((string) $value);
            }
        }
        if ($pin === '' && isset($voucher['pin'])) {
            $pin = sanitize_text_field((string) $voucher['pin']);
        }
        if ($provider_ref === '' && isset($voucher['provider_ref'])) {
            $provider_ref = sanitize_text_field((string) $voucher['provider_ref']);
        }

        $bill_ref = sanitize_text_field((string) ($voucher['bill_ref'] ?? ''));

        $rows = [
            'Operador' => $voucher['operator'] ?? '',
            'Número de destino' => $beneficiary,
            'País' => $country,
            'Paquete' => $bundle,
            'Importe pagado' => $paid_amount,
            'Monto recibido' => $received_amount,
            'Factura' => $bill_ref,
            'PIN' => $pin,
            'Ref. proveedor' => $provider_ref,
            'ID de transacción' => $ref,
            'Estado' => $status,
            'Fecha' => $timestamp,
        ];

        return $rows;
    }

    public static function translate_status($raw): string {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return 'Procesando';
        }
        $map = [
            'complete' => 'Completada',
            'completed' => 'Completada',
            'transfersuccessful' => 'Completada',
            'success' => 'Exitosa',
            'submitted' => 'Enviada',
            'pending' => 'Pendiente',
            'processing' => 'Procesando',
            'inprogress' => 'En curso',
            'queued' => 'En cola',
            'pending_retry' => 'Reintento pendiente',
            'pending_confirmation' => 'Pendiente de confirmación',
            'escalado_soporte' => 'Escalada a soporte',
            'failed_permanent' => 'Fallida',
            'error' => 'Error',
            'validate_only' => 'Validación',
            'blocked_gateway' => 'Pasarela bloqueada',
            'not_started' => 'Pendiente de inicio',
            'cancelled_before_dispatch' => 'Cancelada',
            'ok' => 'Exitosa',
            'approved' => 'Aprobada',
        ];
        $lower = strtolower($raw);
        return $map[$lower] ?? $raw;
    }
}
