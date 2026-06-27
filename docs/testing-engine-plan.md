# Testing Engine Plan

## Purpose

The testing engine should let us test booking payload creation and handover without manually filling in the form every time.

This should start small and grow with the project.

## Initial fixture file

Seed fixtures live here:

```text
tests/fixtures/booking-intake-fixtures.v2.seed.json
```

The seed file was generated from the uploaded production debug logs. It includes one-way, return, charter, add-on, long-distance, invalid/outside-radius, airport pickup, airport drop-off, and city transfer examples.

## First useful commands

Add a WP-CLI command group later, for example:

```bash
wp wsb-intake fixtures:list
wp wsb-intake fixtures:show wsb-v2-001-airport-dropoff-early
wp wsb-intake fixtures:validate wsb-v2-001-airport-dropoff-early
wp wsb-intake fixtures:handover wsb-v2-001-airport-dropoff-early --mode=legacy_hash
wp wsb-intake fixtures:handover wsb-v2-001-airport-dropoff-early --mode=v2_token
```

## Minimal first implementation

The first testing milestone does not need full browser automation.

It only needs to:

1. Load a fixture JSON payload.
2. Run it through the v2 validator.
3. Run it through the v2 normaliser.
4. Build the final handover payload.
5. Print the result in the terminal.
6. Optionally POST it to the booking site in local mode.

## Later testing layers

Later, add:

- expected legacy payload comparison
- expected booking-site response comparison
- expected route/service classification
- expected price snapshots
- cart line item tests
- multi-trip itinerary tests
- admin itinerary tests

## Fixture runner (Phase 2G)

A terminal fixture runner was added at:

```text
scripts/run-booking-payload-fixtures.php
```

Usage:

```bash
php scripts/run-booking-payload-fixtures.php
```

The runner:

1. Loads fixtures from `tests/fixtures/booking-payload-v2-fixtures.json`.
2. Runs each through the actual `WSB_Client_Booking_Payload_V2_Normalizer::normalize()`.
3. Validates each normalised payload through `WSB_Client_Booking_Payload_V2_Validator::validate()`.
4. Compares `expected_ok` (from the fixture) to the actual validation result.
5. Prints per-fixture pass/fail with error details on mismatch.
6. Exits code `0` if all expectations match, `1` if any fixture fails.

### Adding new fixtures

Add entries to `tests/fixtures/booking-payload-v2-fixtures.json`. Each fixture requires:

- `id` — unique slug
- `description` — human-readable summary
- `expected_ok` — `true` if the payload should validate, `false` if it should fail
- `payload` — the raw input (flat fields or structured legs) to pass to the normalizer

### Runner constraints

- No database records created.
- No bookings created.
- No external API calls.
- No Google/HERE keys required.
- The runner does not test handover, only normalise + validate against `expected_ok`.

## Important rule

Do not make pricing snapshots authoritative until the booking-site pricing engine has a stable test harness. For now, fixture prices from logs can be used as references, not as hard assertions.
