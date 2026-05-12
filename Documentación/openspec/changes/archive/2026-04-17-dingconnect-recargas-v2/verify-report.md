# Verify Report: DingConnect Recargas v2

---

## Phase 1 Verification — 2026-04-17

**Scope:** Phase 1 — Proposal-to-Config Baseline (tasks 1.1–1.6)  
**Mode:** Standard Verify (no test runner)

### Summary

| Metric | Valor |
|--------|-------|
| Tasks Phase 1 | 6 |
| PASS | 4 |
| WARNING | 2 |
| CRITICAL | 0 |
| **Overall** | **⚠️ WARNING** |

### Task-by-Task Results

#### 1.1 Admin options schema — ⚠️ WARNING
`wizard_enabled` (default 0) y `wizard_max_offers_per_category` (default 6) correctos.  
**Gap:** `wizard_default_entry_mode` y `wizard_fixed_prefix` NO están en el schema de admin — solo como fallbacks hardcodeados en `class-dc-wizard.php`.

#### 1.2 Checkout mapping settings — ✅ PASS
Tres modos (both / beneficiary_only / buyer_only) + `wizard_checkout_beneficiary_meta_key` en `sanitize_options()` y UI.

#### 1.3 class-dc-wizard.php — session model, steps, transitions — ✅ PASS
Steps `['category','country','operator','product','review']`, `can_transition()`, entry modes implementados.

#### 1.4 Session storage and recovery — ✅ PASS
`save_session()` con upsert, `get_session()` con expiry validation y auto-delete. UUID session key.

#### 1.5 Database table creation — ✅ PASS
`maybe_create_sessions_table()` con `dbDelta`, activation hook + lazy upgrade guard con schema versioning.

#### 1.6 Shortcode registration — ✅ PASS
Cuatro shortcodes registrados: `dingconnect_wizard`, `_recargas`, `_giftcards`, `_cuba`. Assets + `wp_localize_script` con `DC_WIZARD_DATA`.

### Fix Requerido (1.1)

Agregar a `class-dc-admin.php` `sanitize_options()`, activation hook en `dingconnect-recargas.php`, y UI:
- `wizard_default_entry_mode` — select (number_first / country_fixed), default `number_first`
- `wizard_fixed_prefix` — text input, default `''`

En `class-dc-wizard.php`, `get_offers()` debe leer `wizard_default_entry_mode` desde `get_options()` en lugar del hardcoded `'number_first'`.

---

## Phase 6 Verification Scope
- Phase 6 verification for wizard v2:
  - E2E recargas (number-first)
  - E2E gift cards (country-fixed)
  - Payment-first enforcement and idempotency
  - Multi-gateway validation
  - Retry and manual reconciliation

## Environment
- WordPress: not running in this workspace (CLI/environment unavailable)
- WooCommerce: not running in this workspace (requires WP runtime)
- Gateways enabled in test: not available in local verification context
- Ding mode: `validate_only=true` (recommended during verification)
- Execution constraints captured:
  - `php` CLI is not installed/available in current shell (`CommandNotFoundException`).
  - No `composer.json` and no `phpunit.xml` were found in plugin root.
  - `docker` is not installed/available in current shell (`CommandNotFoundException`) while running `./scripts/staging-up.ps1`.
  - Staging automation assets were created in repo (`staging/docker-compose.yml`, `scripts/staging-up.ps1`, `scripts/run-matrix-6.ps1`) but cannot execute on this station until Docker is available.

## Results Summary
- Overall status: Partial verification completed (code-path + execution constraints documented)
- Blocking issues:
  - No executable WordPress/WooCommerce staging runtime in current environment.
  - No PHP CLI/test runner available to execute E2E or automated PHP tests.

## Test Cases

### 6.1 E2E Recargas (number-first)
- Status: Partial (flow wiring validated, E2E runtime blocked)
- Evidence:
  - Number-first wizard flow implemented in frontend state machine (`category -> country -> operator -> product -> review`) and session persistence.
  - Checkout/add-to-cart handoff implemented in REST + Woo integration (`/add-to-cart` + `dc_recargas_add_to_cart` filter path).
  - Payment-only dispatch hook present (`woocommerce_order_status_processing` and `woocommerce_order_status_completed` -> `process_recarga_on_payment`).
  - Voucher rendering hook present in thank-you and email metadata hooks.
  - Runtime blocker: no order ID/transfer ref could be generated without active WP+Woo runtime.
- Notes:
  - Código preparado para el flujo completo; falta ejecución sobre staging con gateway real.

### 6.2 E2E Gift Cards (country-fixed)
- Status: Partial (country-fixed + payload contract validated in code, E2E runtime blocked)
- Evidence:
  - Landing shortcode `dingconnect_wizard_cuba` fija `entry_mode=country_fixed`, `country=CU`, `fixed_prefix=53`.
  - `entry_mode=country_fixed` validado en backend wizard (`country_iso` obligatorio).
  - Shared confirmation payload contract defined in wizard (`transaction_id`, `status`, `operator`, `amount_sent`, `amount_received`, `beneficiary_phone`, `timestamp`, `promotion`, `voucher_lines`).
  - Woo voucher payload persisted with shared core fields (`transaction_id/status/operator/amount_sent/amount_received/beneficiary_phone/timestamp`).
  - Runtime blocker: no checkout order ID available in this environment.
- Notes:
  - La parte contractual está consistente entre wizard y WooCommerce; falta evidencia de ejecución real en checkout.

### 6.3 Payment-first Guard (negative states)
- Status: Pass (code-level verification)
- Scenarios:
  - pending: no dispatch
  - failed: no dispatch
  - canceled: no dispatch
- Evidence:
  - `process_recarga_on_payment` y `process_retry_transfer` cortan ejecución cuando `!$order->is_paid()`.
  - Dispatch sólo ocurre en hooks de estados pagados (`processing`/`completed`).
  - No order notes/transfer logs runtime were produced due to missing WP runtime.
- Notes:
  - Guard principal implementado correctamente; pendiente validación dinámica por estado en Woo admin.

### 6.4 Idempotency on Paid-State Re-entry
- Status: Pass (code-level verification)
- Scenario:
  - repeated order status updates after paid state
- Expected:
  - no duplicated transfer per order item
- Evidence:
  - Item-level idempotency guard: if transfer ref exists or status already successful, item is skipped.
  - Lock key per order-item (`dc_transfer_lock_{md5(order_id_item_id)}`) prevents concurrent duplicate attempts.
  - No runtime order-note/internal-log sample available in this workspace.
- Notes:
  - Controles de idempotencia presentes en dos capas (estado persistido + lock transitorio).

### 6.5 Multi-gateway Matrix
| Gateway | Paid-state reached | Transfer dispatched once | Voucher/Email ok | Result |
|---|---|---|---|---|
| Stripe | Not executed | Not executed | Not executed | Blocked (gateway/runtime unavailable) |
| PayPal | Not executed | Not executed | Not executed | Blocked (gateway/runtime unavailable) |
| Redsys/Bizum (or equivalent) | Not executed | Not executed | Not executed | Blocked (gateway/runtime unavailable) |

### 6.6 Retry + Manual Reconciliation
- Status: Partial (mechanics validated in code, runtime execution blocked)
- Scenarios:
  - transient failure triggers automatic retry
  - manual reconcile action creates new audited attempt
- Evidence:
  - Automatic retry scheduling implemented with `wp_schedule_single_event` on `dc_recargas_retry_transfer`.
  - Retry policy configurable by options (`wizard_transfer_retry_attempts`, `wizard_transfer_retry_delay_minutes`).
  - Manual reconciliation action registered in Woo order actions and writes audited order note.
  - Runtime retry schedule/order note evidence unavailable without WP cron + Woo order lifecycle.
- Notes:
  - Ruta de reintentos y reconciliación manual está cableada; falta prueba con error transitorio real (UAT number).

### 6.7 Shortcodes + Progressive Enhancement
- Status: Partial (shortcode variants + JS defensive fallback validated, theme runtime blocked)
- Scope:
  - `dingconnect_wizard`
  - `dingconnect_wizard_recargas`
  - `dingconnect_wizard_giftcards`
  - `dingconnect_wizard_cuba`
- Evidence:
  - All shortcode variants registered in frontend class.
  - Preset behavior validated in shortcode wrappers (`recargas`, `gift_cards`, `country_fixed` for Cuba).
  - Progressive enhancement guard present in JS: wizard aborts safely if required DOM nodes are missing.
  - Theme-level rendering validation pending because no WordPress theme runtime is active here.
- Notes:
  - Se validó fallback defensivo a nivel de código; resta validación visual funcional con tema externo real.

## Known Gaps / Risks
- Mandatory runtime evidence is still pending in a staging environment with active Woo gateways.
- No local PHP runtime prevents CLI lint and automated test execution in this workspace.
- `ListTransferRecords` reconciliation flow should be included when timeout simulation is performed.

## Go-live Recommendation
- Conditional NO-GO from this environment: runtime evidence for 6.1, 6.2, 6.5, 6.6 and 6.7 is incomplete.
- Do not enable real recharge (`allow_real_recharge`) until staging executes all pending runtime checks with gateway matrix and order evidence.
