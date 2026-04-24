# Delta for payment-enforcement

## MODIFIED Requirements

### Requirement: Payment-first Transfer Dispatch
The system MUST dispatch SendTransfer only after WooCommerce confirms successful payment state.
(Previously: transfer execution depended on generic order processing/completed hooks without explicit payment-first contract.)

#### Scenario: Successful payment
- GIVEN order has DingConnect items
- WHEN order transitions to paid state accepted by WooCommerce gateway
- THEN transfer dispatch runs once per item with idempotency guard
- AND order notes store distributor/transfer references

#### Scenario: Failed or pending payment
- GIVEN order payment is pending, failed, or canceled
- WHEN no paid state has been reached
- THEN transfer dispatch MUST NOT run

### Requirement: WooCommerce-agnostic Gateway Compatibility
The system MUST keep enforcement independent from specific payment gateways.

#### Scenario: Different gateways
- GIVEN Stripe, PayPal, Redsys, Bizum, or other Woo-supported gateway
- WHEN gateway marks order as paid
- THEN the same dispatch policy applies
- AND no gateway-specific hard coupling is required

## ADDED Requirements

### Requirement: Retry and Manual Reconciliation
The system MUST provide configurable retry attempts and manual admin reconciliation for paid-but-failed transfers.

#### Scenario: Automatic retry
- GIVEN payment succeeded and transfer failed transiently
- WHEN retry policy is enabled and attempt budget remains
- THEN system retries per configured interval and logs each attempt

#### Scenario: Manual reconciliation
- GIVEN retries exhausted or operator error persisted
- WHEN admin triggers manual retry from order context
- THEN a new attempt is executed with audit note and status update
