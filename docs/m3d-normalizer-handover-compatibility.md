# M3D - Normalizer / Handover Compatibility for Expanded Payload Structures

## Purpose

M3D hardens the marketing-side `BookingPayload v2` normalizer and dry-run handover flow so the expanded M3C payload shapes survive intact without turning marketing into route or pricing authority.

This phase is a compatibility and preservation task only.

It does not:
- Build multi-day charter UI
- Build multi-trip booking UI
- Build drag/drop UI
- Call Google, HERE, or any other external route API
- Calculate distance, duration, tolls, pricing, or route classification on the marketing side
- Modify booking-side runtime code
- Alter DB schema or REST endpoint contracts
- Touch WooCommerce, session, cart, or order logic
- Introduce customer name, email, or phone as required initial marketing fields

## Files Reviewed

- `docs/m3a-feature-gate-config-foundation.md`
- `docs/m3b-form-field-semantics-and-gated-scaffolding.md`
- `docs/m3c-payload-fixtures-additional-stops-charter-metadata.md`
- `docs/google-places-quote-ready-handoff.md`
- `docs/booking-payload-v2-contract.md`
- `inc/class-booking-payload-v2-normalizer.php`
- `inc/class-booking-payload-v2-validator.php`
- `inc/class-booking-payload-v2-handover-service.php`
- `inc/class-booking-feature-gates.php`
- `assets/js/booking-client-form.js`
- `scripts/run-booking-payload-fixtures.php`
- `scripts/run-booking-handover-fixtures.php`
- `tests/fixtures/booking-payload-v2-fixtures.json`
- `booking-site/app/public/wp-content/plugins/ws-bookings/docs/V3-IMPLEMENTATION-GATE-MAP.md`
- `booking-site/app/public/wp-content/plugins/ws-bookings/docs/phase-2x-security-privacy-logging-audit.md`
- `booking-site/app/public/wp-content/plugins/ws-bookings/docs/phase-2h-session-cookie-bootstrap.md`

## Normalizer Support Matrix

| Structure | Status | What the normalizer does |
|---|---|---|
| Additional stops on transfer legs | Supported | Preserves `legs[].stops[]` and `legs[].place_snapshots.stops[]` for outbound and return legs. |
| Charter additional stop metadata | Supported | Preserves `charter.additional_stop` from raw `charter.additional_stop` or `charter_additional_stop` input. |
| Same-day charter notes | Supported | Preserves `charter.notes` from raw `charter.notes` or `charter_notes`. |
| Same-day charter POI intent | Supported | Preserves `charter.poi` from raw `charter.poi` or `charter_poi`. |
| Route options | Supported | Preserves `route.route_options[]` verbatim, including nested `preferences` and `details`. |
| Route preferences / route details metadata | Supported as opaque metadata | If present in the `route` block, they pass through unchanged and are never promoted to authoritative route results. |
| Google Places place snapshots | Supported | Preserves `place_snapshots.from`, `place_snapshots.to`, and `place_snapshots.stops[]`. |
| `captured_at` / `stale` snapshot fields | Supported | Preserves `captured_at` and `stale`; `stale` remains a boolean validity flag. |
| Reserved multi-day charter structures | Pass-through only | `charter.days[]` is preserved when supplied, but there is still no multi-day builder UI. |
| Reserved multi-trip itinerary structures | Pass-through only | `itinerary.trips[]` is preserved when supplied, but there is still no multi-trip builder UI. |

## Handover Support Matrix

| Structure | Status | What the handover runner proves |
|---|---|---|
| Envelope signing | Supported | The dry-run envelope is signed and contains the normalized payload unchanged. |
| Supported metadata preservation | Supported | Route options, charter notes, charter POI, charter additional stop, and place snapshots survive envelope creation. |
| Google Places quote-ready enforcement | Supported | Invalid Google Places fixtures remain expected-fail under enforced gates, not skipped. |
| Optional customer fields | Supported | Empty customer name/email/phone remains valid in the fixture suite, so marketing does not require them. |
| Unsupported future scaffolds | Skipped intentionally | Reserved multi-day, reserved multi-trip, and mixed transfer/charter scaffolds remain fixture-skipped. |
| Authoritative route fields | Rejected | Distance, duration, price, and polyline authority are not accepted by the validator. |

## Supported Structures

- Transfer additional stops remain leg-scoped and use `legs[].stops[]`
- Charter POI and notes remain metadata on `charter`
- Route option preferences and details remain nested inside `route.route_options[]`
- Google Places snapshots remain the source of truth for quote-ready location selection
- `captured_at` and `stale` remain part of the snapshot contract

## Skipped / Unsupported Structures

- `reserved-multiday-charter-two-days`
- `reserved-multiday-charter-reordered-days`
- `reserved-multiday-charter-duplicated-day-new-day-id`
- `reserved-multitrip-two-transfers`
- `reserved-multitrip-mixed-transfer-charter-unsupported`

These stay skipped because the builder/UI/product decision is still pending.

## Rejected Unsafe Structures

- Authoritative route distance
- Authoritative route duration
- Authoritative route price
- Authoritative route polyline
- Missing, invalid, or stale Google Places snapshots when enforcement is active
- Client-controlled `validation_flags` as enforcement authority

Marketing is still not the authority for:
- Route calculation
- Distance calculation
- Toll detection
- Pricing
- Vehicle availability
- WooCommerce/session/cart/order state

## Verification

Commands run:

```bash
php scripts/run-feature-gate-smoke.php
php scripts/run-form-semantics-smoke.php
php scripts/run-booking-payload-fixtures.php
php scripts/run-booking-handover-fixtures.php
./scripts/verify-wolf-booking-v2.sh
```

PHP lint was run on the changed PHP files.

Current fixture runner results:

- `total: 59`
- `valid_pass: 25`
- `invalid_expected_fail: 29`
- `skipped_unsupported: 5`
- `unexpected_fail: 0`
- `unexpected_pass: 0`

The five Google Places invalid fixtures are expected-fail, not skipped:

- `invalid-one-way-missing-origin-place-id`
- `invalid-one-way-missing-destination-place-id`
- `invalid-return-missing-return-place-id`
- `invalid-additional-stop-without-place-id`
- `invalid-stale-place-snapshot`

## Next Recommended Phase

The next sensible phase is booking-side consumer alignment for the preserved metadata, especially:
- Route options and route preference/detail metadata
- Charter POI and notes
- Reserved multi-day / multi-trip scaffolds once product direction is final
