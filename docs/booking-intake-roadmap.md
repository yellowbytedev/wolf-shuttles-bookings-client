# Booking Intake and Multi-Trip Roadmap

## End goal

Build a modular Wolf Shuttles booking system where a customer or admin can create one itinerary containing multiple trips, then pay through one WooCommerce order.

Example future state:

```text
Itinerary #1001
├── One-way transfer: Stellenbosch → Somerset West
├── Return transfer: Cape Town Airport ↔ Camps Bay
└── Charter hire: 5-day wedding itinerary
```

## Phase 1 — Completed

Loose Fluent snippets were moved into plugin control for both:

```text
ws-bookings-client = marketing site
ws-bookings        = booking system site
```

## Phase 2 — Booking intake foundation

Build the new marketing-side intake layer beside the legacy flow.

Milestones:

1. Add documentation and agent instructions.
2. Define BookingPayload v2.
3. Build PHP payload builder, normaliser, validator, and booking-site client classes.
4. Build a shortcode shell: `[ws_booking_client_form]`.
5. Create a new official local page, e.g. `/booking-builder/`, that renders the shortcode and live BookingPayload v2 preview UI.
6. Support one-way and return transfers.
7. Keep charter scaffolded in the schema but not necessarily in the first UI.
8. Support both `legacy_hash` and `v2_token` handover modes during development.
9. Keep existing Bricks form untouched.
10. Add a minimal fixture-driven test runner.

### Phase 2F status (completed)

- Server-side preview endpoint added.
- Payload normalizer and validator wired.
- Validation UI added to the shortcode preview panel.
- REST endpoint tested with valid and invalid payloads.
- Clean debug-log verification passed.
- Preview remains local only; no real booking submission occurs.
- Booking-site handover is still pending.
- Google autocomplete is still pending.

### Phase 2G status (completed)

- Repeatable BookingPayload v2 fixture runner added.
- Fixture JSON (`tests/fixtures/booking-payload-v2-fixtures.json`) with 10 test cases:
  - 4 valid (one-way, return, additional stop, trailer/oversize)
  - 6 invalid (missing from, missing to, passengers=0, missing return leg, passengers=0+no legs, bad schema version)
- Terminal runner: `php scripts/run-booking-payload-fixtures.php`
- Runner normalises each payload through `WSB_Client_Booking_Payload_V2_Normalizer`, validates through `WSB_Client_Booking_Payload_V2_Validator`, and compares `expected_ok` vs actual result.
- Exit code 0 when all expectations match; exit code 1 on mismatch.
- No database records, no bookings created, no external API calls.
- Booking-site v2 intake endpoint is still pending (Phase 3).
- Google autocomplete is still pending.
- Legacy Bricks/Fluent form untouched.

### Phase 2H status (completed)

- V2 handover envelope service added (`WSB_Client_Booking_Payload_V2_Handover_Service`).
- Deterministic canonical signing helper implemented: recursively sorts associative keys, encodes with `json_encode(JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)`, then `hash_hmac('sha256', ...)`.
- HMAC signing config helper `wsb_client_v2_handover_secret()` added.
- Config placeholder `WSB_CLIENT_V2_HANDOVER_SECRET` added to `inc/booking-client-config.php`.
- Dev-only local fallback secret `local_v2_handover_preview_secret` (documented, not for production).
- Dry-run handover preview REST endpoint added: `POST /wp-json/ws-bookings-client/v1/handover-preview`.
  - Requires `X-WP-Nonce` header (`wp_rest` action).
  - Normalises, validates, then returns `handover_envelope` only for valid payloads.
  - Never calls the booking site.
  - Never creates a booking token.
  - Never creates database records.
- Handover preview fixture runner added: `php scripts/run-booking-handover-preview-fixtures.php`.
- All Phase 2G fixtures still pass; handover runner covers valid fixtures only.
- Booking-site receiver is still pending.
- Itinerary table is still pending.
- Google autocomplete is still pending.
- Legacy Bricks/Fluent form untouched.

### Phase 2I status (completed)

- Added a debug-only developer fixture drawer / payload test lab behind `?debug=1`.
- The drawer loads fixture payloads from `tests/fixtures/booking-payload-v2-fixtures.json`.
- Clicking a fixture populates the Booking Builder form and runs the existing preview checks.
- The drawer compares the live browser result against the expected fixture outcome without enabling real booking submission.
- The normal `/booking-builder/` page stays clean and the legacy Bricks/Fluent flow remains untouched.
- The payload fixture runner and handover preview runner still pass after the drawer changes.

## Phase 3 — Booking-site v2 intake

Add booking-system support for v2 payloads.

The booking site should branch like this:

```text
no schema_version / legacy payload → current processing
schema_version = 2.0              → new v2 processing
```

Initial v2 booking-site behaviour can still create one trip only.

## Phase 4 — Itinerary parent model

Add an itinerary parent table, likely:

```text
ws_bookings_itineraries
```

Then link existing trips to itineraries:

```text
ws_bookings_trips.itinerary_id
```

At first:

```text
one itinerary → one trip
```

This preserves current behaviour while preparing multi-trip support.

## Phase 5 — Trip-to-cart line item model

Change cart behaviour from:

```text
one active booking replaces/clears cart
```

to:

```text
one trip = one WooCommerce cart line item
```

Each cart item should store:

```text
itinerary_id
trip_id
selected_vehicle_id
quote_snapshot
```

## Phase 6 — Add another booking

Allow the user to add another trip under the same itinerary from the booking or checkout page.

Flow:

```text
Add another booking
→ open booking builder form/modal/page
→ create new trip under same itinerary
→ return to shuttle selection for that trip
→ add/update that trip as its own cart line item
```

## Phase 7 — Checkout/cart UX

Render each trip as a collapsible line item:

```text
One-way: Stellenbosch → Somerset West      R697
[Edit] [Remove] [Expand]
```

Expanded details should show route, passengers, luggage, add-ons, selected vehicle, and quote breakdown.

## Phase 8 — Admin itinerary builder

Allow operations/admin staff to create and edit itineraries manually.

Admin-created itineraries should support:

```text
non-expiring drafts
payment links
manual edits
future deposit/balance support
```

## Phase 9 — Modular pricing features

Once the data model is clean, add modular pricing features:

- return saver discount
- airport-only multiplier
- peak/holiday multiplier
- route choice pricing
- manual admin adjustment
- deposit/balance payments
- additional stops

Each feature should plug into a pricing pipeline rather than being hardcoded into form snippets.
