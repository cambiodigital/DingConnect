# Design: DingConnect Recargas v2

## Technical Approach
Implement the change as an extension of the current plugin architecture:
- keep existing shortcode and REST namespace `dingconnect/v1`.
- add wizard domain (`class-dc-wizard.php`) plus dedicated JS/CSS assets.
- enforce payment-first dispatch in WooCommerce hooks with idempotency.
- expose admin controls for offer caps, entry modes, checkout mapping, and sync notifications.

## Architecture Decisions

| Decision | Options | Chosen | Why |
|---|---|---|---|
| Wizard orchestration | Extend frontend.js only; new wizard class + JS core | New wizard class + `wizard-core.js` | Clear boundaries and easier evolution |
| Taxonomy model | Flat operator list; hybrid category->operator/sub-type | Hybrid | Matches Rene UX and supports gift cards cleanly |
| Entry modes | Single mode; dual mode | Dual mode | Required for both number-first and country-fixed landings |
| Transfer trigger | Pre-checkout/direct; paid-state only | Paid-state only | Enforces no transfer before payment |
| Gateway strategy | Gateway-specific logic; Woo generic states | Woo generic | Keeps plugin gateway-agnostic |

## Data Flow
1. Landing shortcode initializes wizard context (entry mode, defaults, prefix, offer cap).
2. `wizard-core.js` drives step transitions and validation; state persisted per session.
3. REST wizard endpoints return normalized offers using taxonomy and caps.
4. Wizard sends selected item to Woo cart via existing add-to-cart pipeline.
5. Woo checkout completes payment.
6. `class-dc-woocommerce.php` executes transfer only on paid state and records references.
7. Voucher payload is rendered on screen and injected into Woo email template context.

## File Changes

| File | Action | Description |
|---|---|---|
| dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-wizard.php | Create | Wizard state machine, taxonomy, entry mode/prefix rules |
| dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-frontend.php | Modify | Register wizard shortcodes and wizard assets/localization |
| dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-rest.php | Modify | Wizard state endpoints, sync-now trigger, change notification endpoint |
| dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-admin.php | Modify | Admin settings: offer cap, entry presets, checkout field mapping, sync controls |
| dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-woocommerce.php | Modify | Paid-state enforcement, retries, manual reconciliation actions, voucher data hooks |
| dingconnect-wp-plugin/dingconnect-recargas/assets/js/wizard-core.js | Create | Client state machine and step components |
| dingconnect-wp-plugin/dingconnect-recargas/assets/css/wizard.css | Create | Mobile step UI |

## Interfaces / Contracts
- Reuse `dingconnect/v1` with added wizard endpoints:
  - `GET /wizard/config`
  - `POST /wizard/session`
  - `GET /wizard/offers`
  - `POST /wizard/sync-now`
- Canonical confirmation payload (same for recargas/gift cards):
  - `transaction_id`, `status`, `operator`, `amount_sent`, `amount_received`, `beneficiary_phone`, `timestamp`, `promotion`, `voucher_lines`.

## Testing Strategy

| Layer | What to Test | Approach |
|---|---|---|
| Unit | Taxonomy mapping, offer cap, entry mode/prefix normalization | PHPUnit for wizard/service helpers |
| Integration | REST wizard endpoints + Woo add-to-cart + paid-state dispatch | WP integration tests/manual API checks |
| E2E | Number-first and country-fixed journeys for recargas and gift cards | Manual scripted runs in staging |

## Migration / Rollout
- Add feature flag `wizard_enabled` default false.
- Keep current shortcode functional while wizard ramps.
- Enable wizard per landing/site after smoke tests.

## Open Questions
- None blocking for planning.
