<?php

if (!defined('ABSPATH')) {
    exit;
}

class DC_Recargas_Voucher_Renderer {
    public function render_html(array $voucher): string {
        $html = '<div class="dc-voucher-container" style="margin-top: 20px; padding: 15px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc;">';
        $html .= '<h3 style="margin-top: 0; margin-bottom: 15px; color: #1e293b; font-size: 1.1em;">Detalles de la Recarga</h3>';
        
        $html .= '<table style="width: 100%; border-collapse: collapse; text-align: left;">';
        $html .= '<tbody>';
        
        foreach ($this->render_rows($voucher) as $label => $value) {
            if ($value === '') {
                continue;
            }
            $html .= '<tr>';
            $html .= '<th style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: normal; width: 40%;">' . esc_html($label) . '</th>';
            $html .= '<td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; color: #0f172a; font-weight: 500;">' . esc_html($value) . '</td>';
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
        $rows = [
            'Operador' => $voucher['operator'] ?? '',
            'Número / Beneficiario' => $voucher['beneficiary'] ?? ($voucher['beneficiary_phone'] ?? ''),
            'Monto Enviado' => isset($voucher['amount_sent']) ? number_format((float) $voucher['amount_sent'], 2) : '',
            'Monto Recibido' => isset($voucher['amount_received']) && (float) $voucher['amount_received'] > 0 ? number_format((float) $voucher['amount_received'], 2) : '',
            'Referencia (Tx)' => $voucher['transaction_id'] ?? '',
            'Estado' => $voucher['status'] ?? '',
            'Fecha' => $voucher['timestamp'] ?? '',
        ];

        return $rows;
    }
}
