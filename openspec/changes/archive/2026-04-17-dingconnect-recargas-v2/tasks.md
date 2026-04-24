# Tasks: DingConnect Recargas v2

## Continuity Rule (for next AI sessions)
- Always mark a task as `[x]` immediately after implementation is completed and verified.
- Do not leave implemented work as `[ ]`.
- Keep this file as the single source of truth for progress before ending each session.

## Current Progress Snapshot
- Phases 1 to 5 are implemented in code.
- Phase 6 remains pending runtime verification/documentation closure.

## Phase 1: Proposal-to-Config Baseline
- [x] 1.1 Add `wizard_enabled`, `wizard_max_offers_per_category` (default 6), entry mode defaults, and prefix settings to `includes/class-dc-admin.php` option schema. (wizard-ui, product-taxonomy)
- [x] 1.2 Add checkout mapping settings in `includes/class-dc-admin.php` for beneficiary custom field vs buyer Woo billing phone. (external-landing, payment-enforcement)
- [x] 1.3 Create `includes/class-dc-wizard.php` with session model, step definitions, and state transitions. (wizard-ui)
- [x] 1.4 Add wizard session storage and recovery logic
- [x] 1.5 Create database table for wizard session persistence
- [x] 1.6 Add wizard shortcode registration to class-dc-frontend.php
- [x] 2.2 Implement offer cap filtering with deterministic ordering in `includes/class-dc-wizard.php`. (product-taxonomy)
- [x] 2.3 Implement number-first and country-fixed entry handlers with optional fixed prefix logic in `includes/class-dc-wizard.php`. (wizard-ui, external-landing)
- [x] 2.4 Normalize confirmation payload shape (same template for all product types) in wizard/service layer. (product-taxonomy, wizard-ui)

## Phase 3: REST and Sync Operations
- [x] 3.1 Add wizard REST endpoints to `includes/class-dc-rest.php`: config, session save/recover, offers query. (wizard-ui)
- [x] 3.2 Add `sync now` operation and change notification payload in `includes/class-dc-rest.php` using current API client patterns. (external-landing)
- [x] 3.3 Ensure wizard endpoints keep backend-only Ding calls and normalized response contract. (payment-enforcement, product-taxonomy)

## Phase 4: Frontend Wizard + Landing Shortcodes
- [x] 4.1 Create `assets/js/wizard-core.js` with client state machine and step-by-step navigation. (wizard-ui)
- [x] 4.2 Create `assets/css/wizard.css` for mobile-first wizard layout and controls. (wizard-ui)
- [x] 4.3 Update `includes/class-dc-frontend.php` to register wizard shortcodes and pass landing presets/localized config. (external-landing)
- [x] 4.4 Enforce white-label rendering across wizard screens (no DingConnect branding). (external-landing, wizard-ui)

## Phase 5: WooCommerce Payment-first Enforcement
- [x] 5.1 Update `includes/class-dc-woocommerce.php` to gate transfer execution strictly on paid states and idempotency checks. (payment-enforcement)
- [x] 5.2 Add retry policy controls and execution logging for transient failures in `includes/class-dc-woocommerce.php`. (payment-enforcement)
- [x] 5.3 Add manual reconciliation actions from order admin and persist audit notes/status. (payment-enforcement)
- [x] 5.4 Add voucher data hook for on-screen confirmation + Woo email enrichment with same payload model. (wizard-ui)

## Phase 6: Verification and Documentation
- [ ] 6.1 Execute E2E recargas in number-first mode: selection -> add-to-cart -> checkout -> paid order -> voucher + email confirmation.
- [ ] 6.2 Execute E2E gift cards in country-fixed mode (`CU`) and validate shared confirmation payload contract.
- [ ] 6.3 Validate payment-first guard: pending/failed/canceled orders MUST NOT dispatch transfer in Woo hooks.
- [ ] 6.4 Validate paid-state dispatch idempotency: repeated status transitions MUST NOT duplicate transfers for same order item.
- [ ] 6.5 Run multi-gateway matrix (Stripe, PayPal, Redsys/Bizum or available equivalents) and record state transition evidence.
- [ ] 6.6 Verify retry policy + manual reconciliation path for transient provider errors and audit order notes/logs.
- [ ] 6.7 Validate shortcode variants and progressive enhancement fallback in at least one external landing theme.
- [x] 6.8 Update `Documentación/BACKLOG_FUNCIONAL_TECNICO.md` and technical docs with results, known gaps, and go-live recommendation.
