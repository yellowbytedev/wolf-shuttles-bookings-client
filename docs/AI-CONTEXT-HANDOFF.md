# Wolf Shuttles Booking Rebuild — AI Context Handoff

This file is the full context pack for another AI chat, agent, or developer taking over the Wolf Shuttles booking rebuild. Paste this into any AI conversation to give it complete project context.

---

## 1. Main Objective / Vision

Build a modular Wolf Shuttles booking system where a customer or admin can create one itinerary containing multiple trips, then pay through one WooCommerce order.

The future state looks like:

```text
Itinerary #1001
├── One-way transfer: Stellenbosch → Somerset West
├── Return transfer: Cape Town Airport ↔ Camps Bay
└── Charter hire: 5-day wedding itinerary
```

All trips in an itinerary are paid through a single WooCommerce order.

## 2. Why the Rebuild Exists

The existing booking flow is built from loose Fluent snippets with:
- No canonical data contract between marketing and booking sites
- Mixed client/server logic
- Hard-coded business rules scattered across JS and PHP
- No test fixtures or repeatable validation
- No clear ownership boundaries between marketing (intake) and booking (authority)

The rebuild introduces:
- A **canonical `BookingPayload v2`** schema with `schema_version: "2.0"`
- A **legs-based trip model** (`outbound`, `return`, `charter`)
- **Plugin-owned** PHP normalisation, validation, and handover logic
- An **HMAC-signed handover envelope** for secure handover to booking site
- **29 payload fixtures** + **18 handover fixtures** for repeatable validation
- **Clear separation**: marketing = intake, booking = authority

## 3. Old System Summary

The legacy system uses:
- **Bricks Builder** for the frontend booking form
- **Fluent Snippets** for PHP/JS logic (loaded conditionally from `inc/legacy-snippets/`)
- **Google Distance Matrix** for route distance/duration (`php/7-api-call-to-google.php`)
- **HERE Maps** for toll detection (`calculate_tolls`)
- **Google Places JavaScript** for autocomplete + geocode fallbacks
- **HMAC-signed remote POST** to booking-site endpoint (`wp_remote_post` to booking-site)
- **jQuery UI Datepicker** + **jQuery ClockTimePicker** for date/time selection
- **Smart Custom Fields (SCF)** for company-profile options (`max_passengers`)
- **WooCommerce session + cookies** for trip state (`ws_trip_id`, `ws_trip_sig`)

The legacy flow:
1. User fills Bricks form
2. JS calculates distance/tolls/direction via AJAX proxies
3. Form submits to Fluent snippet PHP
4. PHP validates, builds flat data array, HMAC-signs, POSTs to booking site
5. Booking site creates trip, redirects back with `booking_hash`

## 4. New Architecture Summary

```text
Marketing form/component
→ BookingPayload v2
→ PHP normaliser/validator
→ handover adapter
→ booking site
```

### Marketing site (`ws-bookings-client`)
- Renders `[ws_booking_client_form]` shortcode
- Captures booking intent into canonical `BookingPayload v2`
- Normalises flat form fields or nested legs structure
- Validates lead-time, capacity, leg completeness, charter rules
- Produces HMAC-signed handover envelope
- **Never** calls Google Routes/Distance Matrix, HERE, or booking-site APIs
- **Never** creates bookings, tokens, cart items, or database records

### Booking site (`ws-bookings`)
- Receives `BookingPayload v2` via REST endpoint (`/wp-json/ws-bookings/v2/intake`)
- Creates itinerary + trip from payload
- Runs route distance/duration, toll detection, direction classification
- Selects vehicle, calculates pricing via `price-router.php`
- Manages WooCommerce cart/checkout/order linking
- Owns blockouts, availability, and final validation

## 5. Marketing-Site Responsibilities

| Responsibility | Status |
|---------------|--------|
| Canonical form fields (`passengers`, `baby_seats`, `check_in_bags`, etc.) | ✅ Done |
| BookingPayload v2 shape (`schema_version: "2.0"`, legs model) | ✅ Done |
| PHP normaliser (flat fields → canonical legs) | ✅ Done |
| PHP validator (lead-time, capacity, leg completeness, charter rules) | ✅ Done |
| Dry-run HMAC handover envelope | ✅ Done |
| 29 payload fixtures + 18 handover fixtures | ✅ Done |
| Google Places Autocomplete + place snapshots | ✅ Done |
| Legacy clock-timepicker (5-min, AM/PM badges) | ✅ Done |
| Additional stops (leg-scoped for outbound/return) | ✅ Done |
| Charter preview mode (no additional stops) | ✅ Done |
| UI polish (tabs, cards, inputs matching legacy) | ✅ Done |
| Config scaffold (lead times, capacity, picker constraints) | ✅ Done |
| Date/time picker parity (native inputs, blocked dates) | ✅ Done |
| Real booking submission via v2 endpoint | ⏳ Next |
| Route/toll/classification API calls | ❌ Booking-site owned |

## 6. Booking-Site Responsibilities

| Responsibility | Status |
|---------------|--------|
| v2 intake REST endpoint (`/wp-json/ws-bookings/v2/intake`) | 🟡 Scaffold exists; needs implementation |
| HMAC/shared-secret verification | 🟡 Permission callback is `WP_DEBUG` gate only |
| Itinerary parent table (`ws_bookings_itineraries`) | 🟡 Scaffold (`class-itinerary-repository.php`) |
| Trip creation + payload adapter | 🟡 Scaffold (`class-v2-payload-adapter.php`) |
| Booking token generation | 🟡 Scaffold (`class-booking-token-service.php`) |
| Route distance/duration (Google Distance Matrix) | ✅ Legacy paths exist in `legacy-snippets/` |
| Toll detection (HERE Maps) | ✅ Legacy paths exist |
| Direction classification (toward/away/lateral) | ✅ Legacy paths exist in JS |
| Vehicle selection + availability | ✅ WooCommerce product variation system |
| Pricing (`price-router.php`: Zone, Tariffs v2, Charter) | ✅ Functional; needs v2 adapter wiring |
| Blockouts validation (`blockouts-validation.php`) | ✅ Functional; reads from `wsb-blockouts.json` |
| WooCommerce cart/session/checkout | ✅ Functional legacy system |
| Multi-trip itinerary/cart support | ⏳ Phase 5+ |

## 7. Terminology

### Itinerary
A parent container for one or more trips. In the future state, one WooCommerce order pays for one itinerary. Currently scaffolded as `ws_bookings_itineraries` table.

### Trip
A single journey with one or more legs. In the current legacy system, a trip maps 1:1 to an order. In the future state, one itinerary contains multiple trips.

### Leg
One segment of a trip. The v2 model uses:
- `outbound` — the first leg (pickup → destination)
- `return` — the return leg (if `trip_type: "return"`)
- `charter` — a charter hire with `pickup_time` and `dropoff_time`

### Place Snapshot
Per-leg metadata captured from Google Places Autocomplete:
- `provider`: `"google_places"`
- `place_id`: Google place ID
- `label`: Display text
- `formatted_address`: Full address
- `lat`, `lng`: Coordinates

Place snapshots are scaffold-only in Phase 2. They are required for production quote-ready payloads.

### Route Scaffold
Top-level `route` block in `BookingPayload v2`:
- `provider`, `selected_route_id`, `selected_route_label`
- `distance_meters`, `duration_seconds`
- `polyline`, `route_options[]`

Currently empty/default in marketing. Booking site fills with real Google Distance Matrix data.

### Blockouts
Dates/times when no booking can be made. Two scopes:
- **Global blockouts**: affect all bookings (managed via `wsb-blockouts.json`)
- **Vehicle-scoped blockouts**: affect specific vehicles only (not evaluated by marketing picker)

Marketing includes `blockouts` as a diagnostic scaffold with `authority: "booking_site"`.

### Vehicle-Scoped Blockouts
Blockouts that apply to specific vehicles (e.g., "Vehicle 3 is serviced on Tuesdays"). Marketing **never** evaluates these. Booking site owns them. The `blockouts` scaffold includes `vehicle_scoped_blockouts_supported: true` and `marketing_evaluates_vehicle_availability: false`.

### Handover Envelope
The signed wrapper around a validated `BookingPayload v2`:
- `handover_version: "2.0"`
- `schema_version: "2.0"`
- `action`: e.g., `"preview"`, `"fixture"`, `"submit"`
- `request_id`: UUID v4
- `created_at`, `expires_at`: ISO 8601 timestamps
- `source_site: "marketing"`, `target_site: "booking"`
- `payload`: normalised BookingPayload v2
- `integrity.algorithm: "hash_hmac_sha256"`
- `integrity.signature`: HMAC-SHA256 hex string
- `integrity.signed_fields`: subset of envelope fields used for signing
- `meta.handover_mode`: `"preview"` during testing, `"live"` for production handover

### Booking Token
A short-lived token identifying a draft booking. Generated by `WSB_Bookings_Booking_Token_Service` (12-char lowercase alphanum via `wp_generate_password`). Used in the redirect query string (`?booking_token=abc123`).

### Quote Preflight / Draft Itinerary
Future performance optimisation. Marketing sends a debounced draft payload to booking site, which starts expensive route/toll/classification work early and returns a `draft_token`. Final submit includes the draft token for reuse. **Draft work must not create WooCommerce cart items or orders.**

## 8. Core Business Rules

1. **Charter has no additional stops.** Charter legs always use `stops: []`. The normaliser, validator, shortcode, and JS all enforce this.

2. **Transfer additional stops are leg-scoped.** Outbound and return legs each have independent `additional_stop_enabled` toggles and `stops[]` arrays.

3. **Marketing does not decide vehicle availability.** The `blockouts` scaffold declares `marketing_evaluates_vehicle_availability: false`. Vehicle-scoped blockouts never affect the marketing picker.

4. **Booking site owns pricing, availability, route, toll, and classification.** Marketing may collect place snapshots and route metadata, but the booking site is the final authority.

5. **Google Places Autocomplete is mandatory for production.** Free-text input may exist only as debug/fallback. `validation_flags.google_place_snapshots_ready` indicates quote-ready status.

6. **Route caching must not be relied on for pricing.** Any route cache must be keyed by exact place IDs, stop IDs, date/time, and service type. Small distance differences significantly affect pricing.

7. **Preflight draft must not create WooCommerce cart/order.** Draft itineraries are a performance optimisation, not a source of truth. Final validation always runs before checkout.

## 9. Completed Phases

### BookingPayload v2
- Canonical schema defined in `docs/booking-payload-v2.md` and `docs/booking-payload-v2-contract.md`
- `schema_version: "2.0"`, legs-based model
- Nested `customer`, `legs[]`, `route`, `charter`, `blockouts`, `meta`
- Both flat form-field format and nested `legs[]` format supported
- Commit: multiple Phase 2 commits leading to `4e0aadf`

### Fixture Runners
- `tests/fixtures/booking-payload-v2-fixtures.json`: 29 fixtures
- `scripts/run-booking-payload-fixtures.php`: terminal runner, no WordPress bootstrap needed
- `scripts/run-booking-handover-fixtures.php`: handover envelope assertions (was `run-booking-handover-preview-fixtures.php`)
- All 29 payload fixtures pass; 18 valid handover pass, 11 invalid skipped

### Handover Envelope
- `inc/class-booking-payload-v2-handover-service.php`: HMAC-SHA256 signing
- REST endpoint: `POST /wp-json/ws-bookings-client/v1/handover-preview`
- Secret resolution: constructor param → `WSB_CLIENT_V2_HANDOVER_SECRET` → dev fallback → empty string

### Fixture Drawer
- Debug-only developer tool at `?debug=1`
- Reads from fixture JSON, populates form, runs server + handover preview checks
- Fixture chips with valid/invalid badges

### Charter Preview
- Shuttle Hire tab enabled in Booking Builder
- Charter-specific fields: pickup/dropoff locations and times, passengers, luggage
- `trip_type: "charter"`, `service_type: "charter_hire"`, `legs[0].type: "charter"`
- Charter days scaffold with `day_index`, `date`, `start_time`, `end_time`, locations
- No additional stops for charter (enforced everywhere)

### External Services Scaffold
- `inc/class-booking-external-services.php`: no-op adapter
- Methods: `get_route_scaffold()`, `get_toll_scaffold()`, `get_place_scaffold()`, `get_handover_scaffold()`
- Feature flags: `is_google_enabled()`, `is_here_enabled()`, `is_handover_live()`
- Config consumer: `get_booking_site_config_scaffold()`, `get_cached_booking_site_config()`

### Config Scaffold
- Booking-site config contract defined in `docs/booking-site-config-contract.md`
- Lead times: `transfer_min_notice_minutes` (300), `charter_min_notice_minutes` (2880), `max_advance_booking_days` (365)
- Capacity: `max_passengers` (13), luggage/baby-seat limits
- Picker constraints: `time_step_minutes` (5)
- Blockouts: `global_blockouts_supported`, `vehicle_scoped_blockouts_supported`, `vehicle_scoped_blockouts_affect_marketing_picker: false`

### Lead-Time / Date-Time Constraints
- Validator enforces transfer 5-hour lead time, charter 48-hour lead time, 365-day max advance
- Date picker uses `min`/`max` attributes from config
- Time picker uses `step="300"` (5-minute precision)
- Frontend `constrainTimeByDate()` sets min time when minimum date selected

### Date/Time Picker Parity
- Native `<input type="date">` + `<input type="time">` replacing jQuery UI Datepicker
- Custom calendar icon (masked SVG), blocked date styling
- jQuery ClockTimePicker copied into plugin, enqueued as `wsb-clock-timepicker`
- AM/PM badge integration, charter defaults 08:00/17:00

### Google Places Autocomplete / Place Snapshots
- Plugin-owned PHP enqueue loads Google Maps JS API with Places library
- Autocomplete on all 6 location fields with `componentRestrictions: { country: 'ZA' }`
- Place snapshot capture: `provider`, `place_id`, `label`, `formatted_address`, `lat`, `lng`
- Stale-edit detection: edits after selection mark snapshot stale
- `validation_flags.google_place_snapshots_ready` for quote-ready diagnostic

### UI Polish
- Tabs: "Book a Ride" (active red) / "Shuttle Hire"
- Hero card simplified; product-style language
- Debug tools hidden on normal page, visible on `?debug=1`
- CTA: "Check Pricing & Availability"
- Google place display: name + street/area/city without country suffix
- Date icon overlap fixed; native time indicator hidden
- Additional stop reordered between From and To
- AM/PM badge positioned inside time input

### Senior Review Checkpoint
- Review document: `docs/phase-2-marketing-foundation-review.md`
- No critical issues
- Branch marked safe for booking-site v2 receiver planning
- No booking-site/Google Routes/Distance Matrix/HERE calls in new code

## 10. Current Status

| Metric | Value |
|--------|-------|
| Payload fixtures | 29 |
| Handover valid fixtures | 18 |
| Handover invalid/skipped | 11 |
| Booking-site calls active | None |
| Route/toll/classification calls active | None |
| PHP lint | All 6 files pass |
| JS syntax | Pass (`node --check`) |
| Smoke tests | HTTP 200 on `/booking-builder/` and `?debug=1` |
| Browser MCP QA | Passed (normal + debug pages, fixture drawer, charter, timepicker, autocomplete) |
| Console errors | None from `ws-bookings-client` code |

## 11. Known Low-Priority Issues

1. **CSS consolidation** — `.wb-debug` rule blocks duplicated 2-3 times in `assets/css/booking-client-form.css`
2. **Fixture count doc drift** — `docs/booking-payload-v2-contract.md` previously said 22 fixtures; updated to 29
3. **`refreshPreview` inline handler** — One `ReferenceError` observed when clicking Return radio; likely Bricks/GTM-injected, not our code
4. **WP 7.0 `wp_register_script` notice** — `defer` key not recognised; pre-existing, use `strategy => 'defer'` instead

## 12. Validation Commands

```bash
# PHP lint
php -l inc/class-booking-client-form-shortcode.php
php -l inc/class-booking-external-services.php
php -l inc/class-booking-field-registry.php
php -l inc/class-booking-payload-v2-normalizer.php
php -l inc/class-booking-payload-v2-validator.php
php -l inc/class-booking-payload-v2-handover-service.php

# JS syntax
node --check assets/js/booking-client-form.js

# Fixture runners
php scripts/run-booking-payload-fixtures.php   # 29/29 pass
php scripts/run-booking-handover-fixtures.php  # 18 pass, 11 skip (was run-booking-handover-preview-fixtures.php)

# Smoke tests
curl -k -s -o /dev/null -w "%{http_code}\n" https://wolfshuttles.local/booking-builder/
curl -k -s -o /dev/null -w "%{http_code}\n" "https://wolfshuttles.local/booking-builder/?debug=1"

# Debug log
tail -n 200 wp-content/debug.log
```

## 13. Browser MCP QA Expectations

For any UI/shortcode change:
1. Open `https://wolfshuttles.local/booking-builder/` — verify normal page (no debug tools)
2. Open `https://wolfshuttles.local/booking-builder/?debug=1` — verify debug page (fixture drawer, preview panel)
3. Check browser console for errors
4. Interact: toggle trip type (one-way/return), switch to Shuttle Hire, toggle additional stops, pick dates/times, click fixture drawer, submit
5. Check for visual regressions: overlapping buttons, hidden controls, duplicate sections, broken drawers

Reference screenshots have been added in the booking-side repo under:
```
docs/reference-screenshots/booking-system-v2-v3/
```
V2 screenshots show current production behaviour. V3 screenshots show directional ideas for multi-trip and multi-day charter presentation. These are design references, not final pixel-perfect specs.

## 15. Next Roadmap Steps

1. **Booking-site v2 intake endpoint** (`docs/booking-site-v2-receiver-plan.md`)
   - Implement v2 intake in `ws-bookings`
   - Accept marketing handover envelope, verify HMAC, validate expiry, normalise to itinerary/trip
   - Early phase: validate and return response without DB writes; later phases: create records

2. **Booking-site receiver fixture runner**
   - Mirror marketing fixture runner in `ws-bookings`
   - Test envelope verification, payload adaptation, responses

3. **Booking token + draft itinerary storage**
   - Wire `WSB_Bookings_Booking_Token_Service` + `WSB_Bookings_Itinerary_Repository`
   - Create `ws_bookings_itineraries` table + `ws_bookings_trips` linkage

4. **Route/toll/classification/availability integration**
   - Wire Google Distance Matrix, HERE toll detection, direction classification
   - Vehicle availability against WooCommerce product variations

5. **WooCommerce cart/session integration**
   - One trip = one WooCommerce cart line item
   - Multi-trip itinerary support

6. **Booking Test Engine**
   - WP-CLI commands for automated testing
   - WP admin test interface
   - Baseline comparison against approved runs
   - Email recipient configuration for test runs
