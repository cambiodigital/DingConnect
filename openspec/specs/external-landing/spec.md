# Delta for external-landing

## ADDED Requirements

### Requirement: Predefined Landing Shortcodes
The system MUST provide predefined shortcode variants for externally designed landing pages.

#### Scenario: Country-fixed landing
- GIVEN a page uses a country-fixed shortcode preset
- WHEN the wizard loads
- THEN country is preselected and locked when configured
- AND only matching products are shown

#### Scenario: Product-category landing
- GIVEN a page uses a category preset
- WHEN the wizard loads
- THEN the first step starts in that category
- AND visible offers are limited by admin max-offers setting

### Requirement: Entry Mode Control per Shortcode
The system MUST support both number-first and landing-country-fixed entry modes through shortcode attributes.

#### Scenario: Number-first
- GIVEN shortcode entry_mode="number_first"
- WHEN user types beneficiary number
- THEN country/operator detection runs before offer selection

#### Scenario: Country-fixed
- GIVEN shortcode entry_mode="country_fixed" with country="CU"
- WHEN user starts flow
- THEN country step is skipped or locked to CU

### Requirement: White-label Frontend Output
The system MUST NOT expose DingConnect branding in customer-facing landing flows.

#### Scenario: Render external landing
- GIVEN any wizard shortcode rendering
- WHEN user navigates all steps
- THEN no DingConnect brand text or logos appear in UI
- AND voucher/email template uses merchant branding only
