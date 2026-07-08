# Booking Test Engine

## Purpose

The Booking Test Engine provides repeatable, automated testing for the full booking system. It supports CLI/developer runs and WP admin-accessible test runs, with baseline comparison capabilities.

## Test Categories

| Category | Description |
|----------|-------------|
| Airport tests | Airport pickup/dropoff transfer validation |
| City transfer tests | Point-to-point transfer flows |
| Charter tests | Charter hire (same-day and multi-day) validation |
| Surcharge/peak-pricing tests | Peak period pricing rules |
| Checkout summary tests | Booking/checkout rendering verification |
| Email output tests | Customer and admin email content |
| All tests | Full test suite execution |

## Current Fixture File

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

## WP-CLI Commands

```bash
wp wsb-intake fixtures:list
wp wsb-intake fixtures:show wsb-v2-001-airport-pickup
wp wsb-intake fixtures:validate wsb-v2-001-airport-pickup
wp wsb-intake fixtures:handover wsb-v2-001-airport-pickup --mode=live
wp wsb-intake fixtures:run-all
wp wsb-intake tests:run-airport
wp wsb-intake tests:run-city-transfer
wp wsb-intake tests:run-charter
wp wsb-intake tests:run-surcharges
wp wsb-intake tests:run-checkout
wp wsb-intake tests:run-email
wp wsb-intake tests:compare-baseline
```

## WP Admin Screen

Admin-accessible test interface at `/wp-admin/admin.php?page=wsb-test-engine`:
- Filter by test category
- Select recipients for test emails
- Run individual or all tests
- View results compared against baseline
- Export results

## Baseline Storage

- Baseline files stored in `tests/baselines/` directory
- Format: `v2.x.x.{fixture-id}.json` for versioned baselines
- Baseline comparison: diff current output vs approved output
- Version/change-log linkage for tracking changes

## Local Feature Flags

Temporary local flags for developer testing before formal version bumps:
- `WSB_TEST_MODE_OVERRIDE` — bypass production restrictions
- `WSB_TEST_RECIPIENT_OVERRIDE` — override email recipients
- `WSB_TEST_USE_LOCAL_FIXTURES` — use local fixture versions

## Integration with Booking Site

- Tests send to `POST /wp-json/ws-bookings/v2/intake` (in local mode)
- Compare responses against expected shapes
- Validate HMAC signature verification
- Check itinerary/trip creation (when enabled)

## Important Rules

- Do not create real bookings during automated testing.
- Do not add WooCommerce cart items during testing.
- Fixtures should not require Google/HERE API keys.
- Baseline comparison is optional; tests pass if validation expectations match.