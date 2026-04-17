# Delta for wizard-ui

## MODIFIED Requirements

### Requirement: Wizard Interaction Model
The system MUST implement a strict step-by-step flow for selection and checkout preparation.
(Previously: wizard behavior was generic and fallback-oriented.)

#### Scenario: Step progression
- GIVEN user completes current step with valid data
- WHEN user presses next
- THEN wizard advances to next step only
- AND no long single-page stacked form is rendered

#### Scenario: Back navigation
- GIVEN user is on step N > 1
- WHEN user goes back
- THEN prior inputs remain populated and editable

## ADDED Requirements

### Requirement: Dual Entry Modes in UI
The wizard MUST support number-first and country-fixed landing start modes.

#### Scenario: Number-first start
- GIVEN wizard is configured for number-first
- WHEN user enters beneficiary number
- THEN detection sets country/operator context for next steps

#### Scenario: Country-fixed start
- GIVEN wizard is configured for fixed-country landing
- WHEN page opens
- THEN wizard starts with fixed country context and continues to category/operator/product steps

### Requirement: Voucher and Email Confirmation
The UI MUST show voucher on-screen and trigger enriched WooCommerce email confirmation using same template model.

#### Scenario: Confirmation rendering
- GIVEN payment and transfer outcome are available
- WHEN confirmation step is reached
- THEN on-screen voucher displays transaction fields
- AND same data model is available to Woo email templates

### Requirement: White-label Presentation
The UI MUST preserve white-label rules across all steps and confirmations.

#### Scenario: Full journey
- GIVEN customer completes the flow
- WHEN reviewing screens and communications
- THEN no DingConnect-facing brand artifact is shown
