# Paso 5: Pantalla De Resultado + Voucher Atómico Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generar un voucher canónico e idéntico para UI + email exactamente cuando el pago queda confirmado, con entrega confiable, idempotente y sin degradar el rendimiento de checkout en producción.

**Architecture:** Se introduce una capa de orquestación de voucher desacoplada del render actual de thank-you/email. El voucher se persiste como snapshot canónico por item de pedido, y la entrega de email pasa por outbox asíncrono con reintentos controlados. Se mantiene compatibilidad con flujo WooCommerce actual (hooks de pago y webhook DingConnect), usando locks + claves idempotentes para evitar duplicados.

**Tech Stack:** WordPress, WooCommerce, PHP 7.4+, WP Cron, CPT logs `dc_transfer_log`, REST `dingconnect/v1`, metadatos de order item.

---

### Task 1: Baseline Y Feature Flag De Paso 5

**Files:**
- Modify: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-api.php`
- Modify: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-admin.php`
- Modify: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\dingconnect-recargas.php`
- Test: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-admin.php`

- [ ] **Step 1: Añadir flags de activación gradual**

```php
// class-dc-api.php (defaults)
'voucher_v2_enabled' => 0,
'voucher_v2_shadow_mode' => 1,
'voucher_outbox_enabled' => 1,
'voucher_outbox_max_attempts' => 6,
'voucher_outbox_backoff_minutes' => '1,2,5,10,20,30',
```

- [ ] **Step 2: Exponer configuración en admin (Credenciales/Operación)**

```php
// class-dc-admin.php (sanitize + render)
$voucher_v2_enabled = !empty($input['voucher_v2_enabled']) ? 1 : 0;
$voucher_v2_shadow_mode = !empty($input['voucher_v2_shadow_mode']) ? 1 : 0;
```

- [ ] **Step 3: Versionar release del plugin**

```php
// dingconnect-recargas.php
define('DC_RECARGAS_VERSION', '2.8.00');
```

- [ ] **Step 4: Validar sintaxis**

Run: `php -l x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-api.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-api.php x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-admin.php x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/dingconnect-recargas.php
git commit -m "feat(voucher): add v2 feature flags and rollout controls"
```

### Task 2: Persistencia Canónica Del Voucher (DTO + Snapshot)

**Files:**
- Create: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-voucher.php`
- Modify: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-woocommerce.php`
- Modify: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\dingconnect-recargas.php`
- Test: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-voucher.php`

- [ ] **Step 1: Crear servicio `DC_Recargas_Voucher` con contrato v1**

```php
class DC_Recargas_Voucher {
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
}
```

- [ ] **Step 2: Persistir snapshot único por item**

```php
// class-dc-woocommerce.php
$voucher_payload = $this->voucher_service->build_snapshot($order, $item, $snapshot);
$item->update_meta_data('_dc_voucher_payload_v2', wp_json_encode($voucher_payload));
$item->update_meta_data('_dc_voucher_hash', (string) $voucher_payload['voucher_hash']);
$item->save();
```

- [ ] **Step 3: Cargar el servicio en bootstrap**

```php
// dingconnect-recargas.php
require_once $resolve_required_file('includes/class-dc-voucher.php', $base_paths);
```

- [ ] **Step 4: Prueba de idempotencia de hash (manual)**

Run: generar dos veces el snapshot para mismo item + mismos datos.
Expected: mismo `voucher_hash` y sin duplicar side effects.

- [ ] **Step 5: Commit**

```bash
git add x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-voucher.php x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-woocommerce.php x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/dingconnect-recargas.php
git commit -m "feat(voucher): add canonical voucher snapshot service with deterministic hash"
```

### Task 3: Outbox De Email (Confiabilidad + Rendimiento)

**Files:**
- Create: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-voucher-outbox.php`
- Modify: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-woocommerce.php`
- Modify: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\dingconnect-recargas.php`
- Test: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-voucher-outbox.php`

- [ ] **Step 1: Implementar cola por metadatos + cron (sin tabla nueva)**

```php
class DC_Recargas_Voucher_Outbox {
    public function enqueue(int $order_id, int $item_id, string $voucher_hash): void {
        $job_key = sprintf('%d:%d:%s', $order_id, $item_id, $voucher_hash);
        if (get_transient('dc_voucher_outbox_' . md5($job_key))) { return; }
        set_transient('dc_voucher_outbox_' . md5($job_key), 1, DAY_IN_SECONDS);
        wp_schedule_single_event(time() + 10, 'dc_voucher_send_email', [$order_id, $item_id, $voucher_hash, 1]);
    }
}
```

- [ ] **Step 2: Reemplazar envío inline por enqueue**

```php
// class-dc-woocommerce.php (attempt_transfer_for_item/sync_item_with_ding_status/webhook)
$this->voucher_outbox->enqueue((int) $order->get_id(), (int) $item->get_id(), (string) $voucher_hash);
```

- [ ] **Step 3: Handler con retry/backoff y corte por max attempts**

```php
add_action('dc_voucher_send_email', [$this, 'handle_send_email_job'], 10, 4);
```

- [ ] **Step 4: Verificación de rendimiento**

Run: compra de prueba con recarga exitosa.
Expected: respuesta checkout/thank-you no bloqueada por SMTP; job encolado y ejecutado por cron.

- [ ] **Step 5: Commit**

```bash
git add x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-voucher-outbox.php x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-woocommerce.php x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/dingconnect-recargas.php
git commit -m "feat(voucher): move email delivery to async outbox with retries"
```

### Task 4: Renderer Único E Idéntico (Thank-you + Email)

**Files:**
- Create: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-voucher-renderer.php`
- Modify: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-woocommerce.php`
- Modify: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-email-recarga-confirmacion.php`
- Test: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-voucher-renderer.php`

- [ ] **Step 1: Crear renderer canónico**

```php
class DC_Recargas_Voucher_Renderer {
    public function render_html(array $voucher): string {}
    public function render_plain(array $voucher): string {}
    public function render_rows(array $voucher): array {}
}
```

- [ ] **Step 2: Thank-you consume renderer**

```php
// class-dc-woocommerce.php
$voucher = $this->voucher_service->get_item_voucher_v2($item);
echo $this->voucher_renderer->render_html($voucher);
```

- [ ] **Step 3: Email custom consume el mismo renderer**

```php
// class-dc-email-recarga-confirmacion.php
$html = $this->voucher_renderer->render_html($this->recarga_data_v2);
$plain = $this->voucher_renderer->render_plain($this->recarga_data_v2);
```

- [ ] **Step 4: Prueba de identidad**

Run: comparar `voucher_hash` + contenido normalizado de UI/email para mismo item.
Expected: mismos campos y mismo orden semántico.

- [ ] **Step 5: Commit**

```bash
git add x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-voucher-renderer.php x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-woocommerce.php x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-email-recarga-confirmacion.php
git commit -m "feat(voucher): unify thankyou and email rendering through canonical renderer"
```

### Task 5: Idempotencia Fuerte Y Guardas De Concurrencia

**Files:**
- Modify: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-woocommerce.php`
- Modify: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-rest.php`
- Test: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-woocommerce.php`

- [ ] **Step 1: Lock por item en generación de voucher**

```php
$lock = 'dc_voucher_lock_' . md5($order_id . ':' . $item_id);
if (get_transient($lock)) { return; }
set_transient($lock, 1, 60);
try { /* generar snapshot + enqueue */ } finally { delete_transient($lock); }
```

- [ ] **Step 2: Evitar doble envío por webhook + hooks de pago**

```php
if ((string) $item->get_meta('_dc_voucher_hash') === $new_hash) {
    return; // ya generado para mismo estado/snapshot
}
```

- [ ] **Step 3: Reglas de éxito canónico**

```php
// éxito solo si estado exitoso + transfer ref confirmado
$is_success = $this->is_successful_transfer_status($snapshot['status']) 
    && $this->api->is_confirmed_transfer_reference($snapshot['transfer_ref']);
```

- [ ] **Step 4: Pruebas de carrera**

Run: disparar `process_recarga_on_payment` y webhook con mismo item en ventana corta.
Expected: 1 snapshot final, 1 job útil de email, 0 correos duplicados.

- [ ] **Step 5: Commit**

```bash
git add x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-woocommerce.php x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-rest.php
git commit -m "fix(voucher): enforce idempotency and concurrency guards across payment and webhook paths"
```

### Task 6: Observabilidad Operativa Y Recuperación

**Files:**
- Modify: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-api.php`
- Modify: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-admin.php`
- Modify: `x:\Proyectos\DingConnect\Documentación\BACKLOG_FUNCIONAL_TECNICO.md`
- Modify: `x:\Proyectos\DingConnect\Documentación\GUIA_TECNICA_DING_CONNECT.md`
- Modify: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\dingconnect-recargas.php`

- [ ] **Step 1: Nuevos eventos de log**

```php
$this->api->log_operational_event('voucher_generated', [...]);
$this->api->log_operational_event('voucher_email_queued', [...]);
$this->api->log_operational_event('voucher_email_sent', [...]);
$this->api->log_operational_event('voucher_email_failed', [...]);
```

- [ ] **Step 2: Acción manual “Reenviar voucher” desde pedido**

```php
add_filter('woocommerce_order_actions', ...);
add_action('woocommerce_order_action_dc_resend_voucher', ...);
```

- [ ] **Step 3: Actualizar docs obligatorias + bump versión**

```php
// dingconnect-recargas.php
Version: 2.8.00
```

- [ ] **Step 4: Validación de regresión rápida**

Run: `php -l` sobre archivos modificados.
Expected: sin errores de sintaxis.

- [ ] **Step 5: Commit**

```bash
git add x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-api.php x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-admin.php x:/Proyectos/DingConnect/Documentación/BACKLOG_FUNCIONAL_TECNICO.md x:/Proyectos/DingConnect/Documentación/GUIA_TECNICA_DING_CONNECT.md x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/dingconnect-recargas.php
git commit -m "chore(voucher): add observability, resend action, docs and version bump"
```

### Task 7: Matriz De Validación En Producción Controlada

**Files:**
- Test: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-woocommerce.php`
- Test: `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-email-recarga-confirmacion.php`

- [ ] **Step 1: Caso feliz por gateway**

Run: compra real/sandbox por cada gateway activo (Stripe, PayPal, Redsys/Bizum si aplican).
Expected: voucher visible inmediato + email entregado + logs `voucher_*`.

- [ ] **Step 2: Fallo SMTP**

Run: simular `wp_mail` fallando.
Expected: outbox en retry, UI no bloqueada, recuperación posterior.

- [ ] **Step 3: Duplicidad de callback/webhook**

Run: reinyectar webhook ya procesado.
Expected: idempotencia (sin nuevo voucher ni nuevo email).

- [ ] **Step 4: Estado pendiente prolongado**

Run: caso `Submitted/Pending`.
Expected: mensaje “No repetir compra”, sin marcar éxito terminal.

- [ ] **Step 5: Criterio GO/NO-GO**

Expected GO:
- 0 duplicados de voucher por item.
- 0 envíos de email dobles por mismo `voucher_hash`.
- 95p de checkout no degradado por cola de email.
- Trazabilidad completa en pedido + `dc_transfer_log`.

---

## Riesgos Y Mitigaciones Incluidos En El Plan

- **Riesgo: duplicados por múltiples hooks de pago + webhook.**
Mitigación: lock transitorio por item + hash determinístico + short-circuit por `_dc_voucher_hash`.
- **Riesgo: degradación de performance en checkout por envío SMTP inline.**
Mitigación: outbox asíncrono con `wp_schedule_single_event`.
- **Riesgo: inconsistencia UI/email.**
Mitigación: renderer único (`DC_Recargas_Voucher_Renderer`) y snapshot canónico único.
- **Riesgo: falsos éxitos con `TransferRef` no confirmado.**
Mitigación: regla estricta de éxito (estado exitoso + referencia confirmada).
- **Riesgo: pérdida operativa ante fallos de correo.**
Mitigación: retries con backoff + logs + acción manual de reenvío.

## Recomendaciones Operativas De Rollout

- Activar primero con `voucher_v2_shadow_mode=1` durante 48h.
- Comparar hash/salida entre flujo legacy y v2 antes de exponer v2 al cliente.
- Activar `voucher_v2_enabled=1` por gateway en ventanas controladas.
- Mantener `validate_only` según política vigente hasta cerrar UAT runtime del flujo completo.

