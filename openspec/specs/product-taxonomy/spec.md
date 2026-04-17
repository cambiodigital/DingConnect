# Delta for product-taxonomy

## MODIFIED Requirements

### Requirement: Catalog Scope and Taxonomy Model
The system MUST support a hybrid taxonomy for recargas and gift cards.
(Previously: taxonomy focused mainly on operator grouping for recargas.)

#### Scenario: Two-level taxonomy
- GIVEN products are loaded from Ding/saved bundles
- WHEN taxonomy is applied
- THEN level 1 is category (recargas, gift_cards)
- AND level 2 is operator/sub-type according to product metadata

#### Scenario: Same response template
- GIVEN user selects recarga or gift card
- WHEN confirmation data is prepared
- THEN system outputs the same response template shape
- AND only product-specific fields are conditionally filled

## ADDED Requirements

### Requirement: Offer Cap by Category
The system MUST enforce a configurable max-offers-per-category value from admin (default 6).

#### Scenario: Offer cap active
- GIVEN category has more products than configured cap
- WHEN offers are listed
- THEN only top N configured offers are shown
- AND ordering remains deterministic

### Requirement: Field Input Mode with Prefix Option
The system MUST support free phone input plus optional fixed prefix per landing context.

#### Scenario: Free input mode
- GIVEN landing does not define fixed prefix
- WHEN user enters beneficiary phone
- THEN full value is accepted and validated server-side

#### Scenario: Fixed prefix mode
- GIVEN landing defines fixed prefix +53
- WHEN user enters local number part
- THEN prefix is prepended before validation/matching
