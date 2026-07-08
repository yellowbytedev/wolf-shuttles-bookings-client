# Testing Engine Plan

## Purpose

The Booking Test Engine provides repeatable, automated testing for the full booking system. It supports CLI/developer runs and WP admin-accessible test runs, with baseline comparison capabilities.

Reference screenshots have been added in the booking-side repo under:
```
docs/reference-screenshots/booking-system-v2-v3/
```
V2 screenshots show current production behaviour. V3 screenshots show directional ideas for multi-trip and multi-day charter presentation.

## Test Categories

| Category | CLI Command | WP Admin Screen |
|----------|-------------|-----------------|
| All tests | `wp wsb-intake tests:run-all` | Full test suite tab |
| Airport tests | `wp wsb-intake tests:run-airport` | Airport transfers tab |
| City transfer tests | `wp wsb-intake tests:run-city-transfer` | Point-to-point tab |
| Charter tests | `wp wsb-intake tests:run-charter` | Charter hire tab |
| Surcharge/peak-pricing tests | `wp wsb-intake tests:run-surcharges` | Pricing rules tab |
| Checkout summary tests | `wp wsb-intake tests:run-checkout` | Checkout tab |
| Email output tests | `wp wsb-intake tests:run-email` | Email content tab |

## Initial fixture file

```text
tests/fixtures/booking-payload-v2-fixtures.json
```

29 fixtures total (18 valid, 11 invalid for testing error paths).

## Fixture Storage Format

```json
{
  "id": "wsb-v2-001-airport-pickup",
  "description": "Valid one-way airport pickup",
  "expected_ok": true,
  "expected_validation": { "valid": true, "warnings": [] },
  "expected_response_shape": { "ok": true, "trip_preview": {} },
  "expected_email_recipients": { "customer": "test@example.com", "admin": "admin@example.com" },
  "baseline_key": "v2.0.0.wsb-v2-001-airport-pickup",
  "payload": { /* BookingPayload v2 */ }
}
```

## Booking Test Engine Implementation

### WP-CLI Commands

```bash
wp wsb-intake fixtures:list
wp wsb-intake fixtures:show wsb-v2-001-airport-pickup
wp wsb-intake fixtures:validate wsb-v2-001-airport-pickup
wp wsb-intake fixtures:handover wsb-v2-001-airport-pickup --mode=live
wp wsb-intake tests:run-all
wp wsb-intake tests:run-airport
wp wsb-intake tests:run-city-transfer
wp wsb-intake tests:run-charter
wp wsb-intake tests:run-surcharges
wp wsb-intake tests:run-checkout
wp wsb-intake tests:run-email
wp wsb-intake tests:compare-baseline
```

### WP Admin Screen

Location: `/wp-admin/admin.php?page=wsb-test-engine`

Features:
- Filter by test category
- Select recipients for test emails
- Run individual or all tests
- View results compared against baseline
- Export results

### Baseline Storage and Comparison

- Baseline files stored in `tests/baselines/` directory
- Format: `v2.x.x.{fixture-id}.json` for versioned baselines
- Baseline comparison: diff current output vs approved output
- Version/change-log linkage for tracking changes

### Local Feature Flags

Temporary local flags for developer testing before formal version bumps:
- `WSB_TEST_MODE_OVERRIDE` — bypass production restrictions
- `WSB_TEST_RECIPIENT_OVERRIDE` — override email recipients
- `WSB_TEST_USE_LOCAL_FIXTURES` — use local fixture versions

## Shared Contract Documentation

The canonical data contract between marketing and booking sites is documented in:
- `docs/booking-payload-v2-contract.md`
- `docs/booking-site-v2-receiver-plan.md` (Booking-Site section)

The contract prevents drift and covers:
- BookingPayload v2 schema
- schema_version 2.0 requirements
- Signed handover envelope format
- Required Google place snapshot expectations
- Leg model structure
- Transfer vs charter rules
- Return trip and additional stop handling
- Charter/multi-day handling
- Booking-site authority boundaries

## Existing Terminal Fixture Runner (Phase 2G)

A terminal fixture runner already exists at `scripts/run-booking-payload-fixtures.php`:

- Loads fixtures from `tests/fixtures/booking-payload-v2-fixtures.json`
- Runs each through `WSB_Client_Booking_Payload_V2_Normalizer::normalize()`
- Validates each normalised payload through `WSB_Client_Booking_Payload_V2_Validator::validate()`
- Compares `expected_ok` against actual validation result
- Prints per-fixture pass/fail with error details
