# Proposal: DingConnect Recargas v2

## Intent
Ship a production-ready wizard flow for CubaKilos that sells recargas and gift cards with payment-first execution in WooCommerce, white-label UX, and reusable shortcodes for externally designed landing pages.

## Scope

### In Scope
- Step-by-step wizard (no long scrolling form), with progressive enhancement fallback.
- Product scope: recargas + gift cards.
- Hybrid taxonomy: category first, then operator/sub-type.
- Entry modes: number-first detection and landing country-fixed mode.
- Configurable max offers per category (default 6) from admin.
- External strategy: predefined shortcode variants for external landing pages.
- WooCommerce checkout mapping:
  - beneficiary phone -> plugin custom checkout field.
  - buyer phone -> native Woo billing phone.
- Field behavior: free input plus optional fixed prefix per landing.
- Confirmation: on-screen voucher + WooCommerce email enrichment.
- Payment-first enforcement: never send transfer before successful payment.
- Product sync operations: "sync now" + change notifications when Ding data/promo changes.

### Out of Scope
- New Ding verticals beyond recargas/gift cards (DTH, utilities).
- New proprietary payment gateway integrations (remain WooCommerce-agnostic).
- Landing page visual builder (external pages remain outside plugin).

## Capabilities

### New Capabilities
- wizard-ui: guided purchase flow for mixed product types.
- product-taxonomy: hybrid category/operator model with configurable offer limits.
- external-landing: predefined shortcode modes for external pages.
- payment-enforcement: post-payment transfer execution with retry/reconciliation.

### Modified Capabilities
- None.

## Approach
Extend the existing plugin classes, not a rewrite. Add a dedicated wizard domain class and wizard REST endpoints on namespace dingconnect/v1, reuse WooCommerce order-state hooks for payment-first dispatch, and keep current shortcode path as fallback via feature flag.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| includes/class-dc-wizard.php | New | Wizard state machine, entry modes, taxonomy, offer cap logic |
| includes/class-dc-frontend.php | Modified | Wizard shortcodes, asset loading, landing presets |
| includes/class-dc-rest.php | Modified | Wizard/session/sync endpoints and normalized responses |
| includes/class-dc-admin.php | Modified | Wizard config, checkout field mapping, sync controls, notifications |
| includes/class-dc-woocommerce.php | Modified | Payment-first hooks, retry and reconciliation controls |
| assets/js/wizard-core.js | New | Client wizard state machine and step orchestration |
| assets/css/wizard.css | New | Mobile-first step UI |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Gateway-specific order timing differences | Medium | Trigger on paid states only + idempotency marker |
| Catalog changes breaking curated offers | Medium | Sync-now + change notification + fallback |
| Landing misconfiguration | Low | Predefined shortcode templates + server-side validation |

## Rollback Plan
Disable wizard feature flag and keep existing `dingconnect_recargas` flow active. New endpoints/classes remain inert when flag is off.

## Dependencies
- Existing WooCommerce integration in class-dc-woocommerce.php.
- Existing Ding endpoints via class-dc-api.php and class-dc-rest.php.

## Success Criteria
- [ ] Wizard supports recargas and gift cards end-to-end.
- [ ] Both entry modes work in production (number-first and country-fixed landing).
- [ ] No transfer is sent before successful Woo payment state.
- [ ] Voucher is shown on screen and included in Woo email for both product types.
- [ ] Admin can tune max offers and checkout field mapping without code changes.
