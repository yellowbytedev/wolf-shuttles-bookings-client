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
- Handover preview fixture runner added.
- All Phase 2G fixtures still pass; handover runner covers valid fixtures only.

### Phase 2I status (completed)

- Added a debug-only developer fixture drawer / payload test lab behind `?debug=1`.

### Phase 2J status (completed)

- Schema extension scaffolds:
  - `route` scaffold (provider, selected_route_id, distance_meters, etc.)
  - `validation_flags` preservation
  - `charter` placeholder scaffold
  - `blockouts` diagnostic scaffold for vehicle-scoped blockout support
- Fixed BookingPayload v2 normaliser data loss by adding preservation of `service_group`, top-level `route`, `validation_flags`, and `charter`.
- Leg-scoped additional stops implemented.

### Phase 2K status (completed)

- Basic Shuttle Hire / Charter preview mode added to Booking Builder.
- Charter tab enabled (labelled "Shuttle Hire (preview)").
- Charter form fields added: passengers, baby_seats, check_in_bags, carry_on_bags, trailer, oversize_luggage, charter_pickup_location, charter_dropoff_location, charter_pickup_time, charter_dropoff_time, charter_additional_stop.
- Service mode toggle: transfer-only fields hidden when charter active; return leg hidden when charter active.
- Canonical charter payload shape implemented:
  - `trip_type: "charter"`
  - `service_group: "charter"`
  - `service_type: "charter_hire"`
  - `legs[0].type: "charter"` with `dropoff_time`
  - `charter.enabled: true`
  - `charter.type: "same_day"`
  - `charter.days[]` with day_index, date, times, locations, stops
- Normalizer updated for charter leg type and flat field support.
- Validator updated for charter-specific validation (dropoff_time required, time order check).
- Four new charter fixtures added (4 valid, 0 invalid — updated existing charter scaffold + 3 new).
- All 20 payload fixtures pass.
- All 13 valid handover fixtures pass (7 invalid skipped as expected).
- Dry-run handover preserves charter payloads.

### Phase 2K+ status (completed)

- UI interaction scaffold added: `WSB_BOOKING_UI_INTERACTIONS` JS adapter (no-op until library loaded).
- CSS hooks added: `.wsb-sortable-list`, `.wsb-sortable-item`, `.wsb-drag-handle`, `.wsb-sortable-placeholder`, `.wsb-sortable-chosen`.
- No third-party library installed; drag/drop is inactive.
- Future use cases documented: ordered stops, charter day segments, itinerary trip ordering.

### Phase 2N status (completed)

- Place snapshot scaffolding added to `legs[].place_snapshots`.
- Per-leg `place_snapshots.from`, `place_snapshots.to`, `place_snapshots.stops` scaffolded.
- No live Google API calls; placeholder/mock place IDs only.
- Labels are abstract; no client details exposed.

### Phase 2O status (completed)

- Booking-site config contract defined in `docs/booking-site-config-contract.md`.
- No live endpoint implemented; marketing-side config consumer scaffold planned for Phase 2?.

### Phase 2P status (completed)

- Quote preflight/draft itinerary concept documented.
- Booking-site config consumer scaffold added to `inc/class-booking-external-services.php`.
- Config fetch disabled by default; safe defaults exposed to frontend.
- Max attributes and time step applied to Booking Builder inputs.
- Google Places autocomplete must be mandatory for final production form.
- No route-cache reliance for pricing.
- Not implemented yet; follows booking-site v2 receiver foundations.

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
### Phase 2Q status (completed)

- Booking-site config consumer scaffold added.
- `get_booking_site_config_scaffold()`, `get_cached_booking_site_config()`, `get_default_booking_site_config()`, and `is_booking_site_config_fetch_enabled()` methods added.
- Config fetch disabled by default; safe defaults used.
- No live HTTP calls; no booking data exposed.

### Phase 2R status (completed)

- Date constraints applied using config lead times.
- `outbound_pickup_date` and `return_pickup_date` include min/max attributes.
- Minimum date calculated from `transfer_min_notice_minutes` (300 minutes / 5 hours).
- Maximum date calculated from `max_advance_booking_days` (365 days).
- Time step constraint (`step="300"`) applied to time inputs.
- Frontend `constrainTimeByDate()` sets min time when minimum date is selected.
- Server-side lead-time validation added for transfer and charter legs.
- Max advance booking window validation added.
- 4 new lead-time violation fixtures added (total 26 fixtures).
- All 26 payload fixtures pass.
- Global blockouts still pending (no implementation yet).
- Vehicle-scoped blockouts still do not affect marketing picker.

### Phase 2S status (completed)

- Date/time picker parity pass completed.
- Legacy jQuery UI Datepicker styling replaced with branded native `<input type="date">` CSS.
- Legacy jQuery ClockTimePicker replaced with native `<input type="time" step="300">`.
- Plugin-owned CSS added:
  - Custom calendar icon (masked SVG) for date inputs.
  - Blocked/out-of-range date state styling (`.wsb-date-blocked`).
  - AM/PM visual badge (`.wsb-time-ampm-badge`) for legacy-style time labels.
  - Picker status messages and blockout legend UI.
- Plugin-owned JS added:
  - Default date selection (tomorrow) for all date fields.
  - Charter default times (08:00 pickup, 17:00 dropoff).
  - AM/PM badge injection/update on time input changes.
  - Live picker status messages for lead-time and blockout violations.
  - `blocked_dates` scaffold from `bookingSiteConfig.blockouts`.
- Updated `render_date_field()` to include status containers for outbound/return pickers.
- No third-party picker library added; no CDN or npm dependencies.
- Company-profile max values remain future booking-site config work (not implemented in this phase).
