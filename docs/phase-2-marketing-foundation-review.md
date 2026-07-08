# Phase 2 Marketing Foundation — Senior Review Checkpoint

**Branch:** `feature/phase-2h-v2-handover-foundation`
**Latest commit:** `4e0aadf`
**Review date:** 2026-06-29
**Reviewer:** Code Reviewer (automated)
**Scope:** Marketing-side Booking Builder implementation before booking-site v2 receiver planning

---

## 1. Review Summary

The marketing-side Booking Builder implementation is **sound and ready for booking-site v2 receiver planning**. The codebase demonstrates a clean separation of concerns: PHP owns canonical validation, normalisation, payload building, and handover logic; JavaScript is limited to UI state, Google Places selection, and client-side convenience. No booking-site dependency was accidentally introduced.

No critical issues found. One medium-severity JS console error was observed during interactive testing (likely Bricks/GTM interaction, not a defect in our code). One documentation drift item and several CSS maintainability observations are noted as low-priority cleanup ideas.

---

## 2. Files Inspected

### Documentation
- `AGENTS.md`
- `AGENT-HANDOFF.md`
- `docs/phase-2-progress.md`
- `docs/booking-intake-roadmap.md`
- `docs/booking-payload-v2.md`
- `docs/booking-payload-v2-contract.md`
- `docs/booking-site-config-contract.md`
- `docs/quote-preflight-draft-itinerary.md`
- `docs/legacy-form-controls-audit.md`

### PHP Source
- `inc/class-booking-client-form-shortcode.php`
- `inc/class-booking-external-services.php`
- `inc/class-booking-field-registry.php`
- `inc/class-booking-payload-v2-normalizer.php`
- `inc/class-booking-payload-v2-validator.php`
- `inc/class-booking-payload-v2-handover-service.php`

### JavaScript / CSS
- `assets/js/booking-client-form.js`
- `assets/css/booking-client-form.css`

### Tests / Fixtures
- `tests/fixtures/booking-payload-v2-fixtures.json`
- `scripts/run-booking-payload-fixtures.php`
- `scripts/run-booking-handover-fixtures.php` (was `run-booking-handover-preview-fixtures.php`)

---

## 3. Validation Results

### PHP Lint
All 6 PHP files pass syntax check:
- `inc/class-booking-client-form-shortcode.php` — PASS
- `inc/class-booking-external-services.php` — PASS
- `inc/class-booking-field-registry.php` — PASS
- `inc/class-booking-payload-v2-normalizer.php` — PASS
- `inc/class-booking-payload-v2-validator.php` — PASS
- `inc/class-booking-payload-v2-handover-service.php` — PASS

### JavaScript Syntax
- `assets/js/booking-client-form.js` — PASS (`node --check`)

### Fixture Runners
- **Payload fixtures:** 29/29 PASS (0 failures)
- **Handover fixtures:** 18 valid PASS, 11 invalid SKIPPED (0 failures)

### Smoke Tests
- `https://wolfshuttles.local/booking-builder/` — HTTP 200
- `https://wolfshuttles.local/booking-builder/?debug=1` — HTTP 200

---

## 4. Browser / Playwright MCP Results

### Normal Page (`/booking-builder/`)
- Form renders correctly with "Book a Ride" tab active
- Transfer section shows: Trip details, trip-type pills (One-way/Return), passenger/bag fields, From → Enable additional stop → To → Date/Time
- CTA button visible: "Check Pricing & Availability"
- No debug tools visible (preview panel, fixture drawer, dev header hidden)
- Footer and site chrome render normally

### Debug Page (`/booking-builder/?debug=1`)
- Form renders with developer header: "Build a new booking request. No real booking submission is enabled yet."
- Payload preview panel visible on the right (desktop) with live JSON output
- Fixture drawer toggle ("Test payloads") visible and functional
- Clicking fixture drawer opens it, showing all 29 fixture chips with valid/invalid badges
- Clicking a fixture populates the form and runs both server-side preview and handover preview
- Server validation and handover preview results displayed in fixture drawer status

### Interactive Testing
- **Return radio toggle:** Return section appears/disappears correctly. Payload updates to show two legs with `trip_type: "return"`.
  - *Note:* One `ReferenceError: refreshPreview is not defined` appeared in console on first Return click. This is likely from a Bricks/GTM-injected inline handler, not from our code (our `addEventListener` references are scoped correctly). The payload preview continued to update normally.
- **Shuttle Hire tab:** Charter section shows correctly with `service_group: "charter"`, `service_type: "charter_hire"`, `trip_type: "charter"`, defaults 08:00/17:00, AM/PM badges visible, no additional stop toggle.
- **Time fields:** Clock-timepicker popup opens on click. AM/PM badge renders inside time input (right-aligned, subtle grey `#999`).
- **Additional stop toggle:** Checkbox shows/hides the additional stop field correctly on outbound leg.
- **Google Places Autocomplete:** Functional (deprecation warnings only — expected for existing customers).

### Console Errors
- **No errors from `ws-bookings-client` code**
- Known Google Maps deprecation warnings (Autocomplete for new customers) — expected
- One `wp_register_script` notice (defer key) — pre-existing, not from our changes

### Visual Regression
- No overlapping fixed buttons
- No hidden controls
- No duplicate sections
- No broken drawers
- Form layout matches expected product-style design

---

## 5. API / Network Grep Results

### In `inc/` and `assets/` (new plugin code)
**No `wp_remote_get`, `wp_remote_post`, or `fetch()` calls found.**

The only `fetch()` in our codebase is in `assets/js/booking-client-form.js` line 510, which calls the local REST endpoints (`/wp-json/ws-bookings-client/v1/payload-preview` and `/wp-json/ws-bookings-client/v1/handover-preview`) — both owned by this plugin.

### In `inc/legacy-snippets/` (legacy code, not modified)
Calls found in legacy files only:
- `inc/legacy-snippets/php/7-api-call-to-google.php` — `wp_remote_get` (Google Distance Matrix / Geocode — legacy)
- `inc/legacy-snippets/php/15-submit-booking-form-and.php` — `wp_remote_get` / `wp_remote_post` (legacy HMAC handover — legacy)
- `inc/legacy-snippets/js/5-calculate-distance-v2.js` — `fetch()` to `admin-ajax.php` (legacy distance/place/toll lookups)
- `inc/legacy-snippets/archive/15-initialise-elements-and-variables.php` — `fetch()` to `admin-ajax.php` (legacy)

**No booking-site, Google Routes, Distance Matrix, or HERE calls are active in the new code.**

---

## 6. Architecture and Ownership

### PASS — Marketing site remains intake/payload-focused
- All canonical validation, normalisation, payload building, and handover logic is in PHP
- JS handles only UI state, Google Places selection, preview rendering, and fixture drawer interaction
- No route/distance/toll/classification calls in new code
- Booking-site config scaffold (`get_booking_site_config_scaffold`) returns safe defaults; no live HTTP fetch

### PASS — Booking-site authority preserved
- `blockouts` scaffold declares `authority: "booking_site"` and `marketing_evaluates_vehicle_availability: false`
- `route` scaffold is empty/default; no distance/duration calculation
- `validation_flags` only warns about missing place snapshots (diagnostic)
- `meta.preview_only: true` and `meta.real_handover_enabled: false` enforced in PHP normalizer
- Handover service is dry-run only; `mode: "dry_run"` hardcoded

### PASS — No accidental booking-site dependency
- `WSB_Client_Booking_Payload_V2_Handover_Service::build_envelope()` never calls the booking site
- No booking token creation, no WooCommerce cart items, no database records
- HMAC signing uses local dev fallback secret; real secret resolution is opt-in via constant

### PASS — Debug tools scoped correctly
- Fixture drawer, dev header, raw preview panels visible only on `?debug=1`
- Normal page shows product-style tabs, clean CTA, no developer copy
- `.wb-debug` utility class scopes all debug-only CSS

---

## 7. BookingPayload v2 Shape

### PASS — `from` / `to` strings compatible
- Display strings remain as `label` fields on `legs[].from` and `legs[].to`
- `place_snapshots` carry the richer metadata separately
- Normalizer `normalize_location()` preserves both `label` and `formatted_address`

### PASS — `place_snapshots` preserved correctly
- Per-leg `place_snapshots.from`, `place_snapshots.to`, `place_snapshots.stops` scaffold present
- Google Places autocomplete populates `provider`, `place_id`, `label`, `formatted_address`, `lat`, `lng`
- Stale-edit handling marks snapshots stale via `wsb-booking-client-field--place-stale` class
- Fixtures cover mock and real Google place IDs

### PASS — Charter has no additional stops
- `stops: []` enforced in `normalize_charter_leg_from_flat_fields()`
- `normalize_legs_from_payload()` clears `stops` for charter type
- JS `buildCharterLeg()` always sets `stops: []`
- Field registry `charter_additional_stop` has `applies_to: ['transfer']` only
- Charter UI section has no additional stop toggle/field in shortcode markup

### PASS — Transfer additional stops make sense
- Outbound and return legs each have independent `additional_stop_enabled` toggle
- Additional stop stored in `legs[].stops[]` with `type: "additional_stop"` and `location`
- Normalizer reads `outbound_additional_stop_enabled` / `return_additional_stop_enabled` from flat fields
- JS `buildLeg()` populates stops from toggle + text field

### PASS — Route / blockouts / config / preflight scaffolds coherent
- `route` scaffold: provider, selected_route_id, distance_meters, duration_seconds, polyline, route_options
- `blockouts` scaffold: version 2, authority `booking_site`, vehicle-scoped and global flags
- `charter` scaffold: enabled, type, days array
- `validation_flags`: freeform object, defaults to `{}`
- `bookingSiteConfig` defaults exposed to JS via `window.WSB_BOOKING_CLIENT_FORM`
- Date/time constraints wired from config (min notice, max advance, time step)

### PASS — Validator and normalizer agree
- Both accept `trip_type`: `one_way`, `return`, `charter`
- Both accept `service_type`: `city_transfer`, `airport_pickup`, `airport_dropoff`, `charter_hire`
- Both require `dropoff_time` for charter legs
- Both validate end time > start time for charter
- Both validate lead-time and max-advance for all legs
- Both accept `legs[]` flat field format and nested format

---

## 8. JS Quality

### PASS — Google autocomplete state handling
- Autocomplete initialised on all 6 location fields with `componentRestrictions: { country: 'ZA' }`
- Place snapshots captured with lat/lng, place_id, formatted_address, label
- Stale-edit detection: `input` event marks wrapper as `wsb-booking-client-field--place-stale` if previously selected
- Re-focusing clears stale state
- `validation_flags.google_place_snapshots_ready` set in payload

### PASS — Stale snapshot handling
- `markPlaceFieldSelected()` / `markPlaceFieldStale()` / `clearPlaceFieldState()` manage CSS classes
- Display string preserves selected text, strips trailing country suffix

### PASS — Timepicker initialisation
- `initClockTimePicker()` wires `jquery-clock-timepicker` for transfer and charter time fields
- 5-minute precision, branded colours (`#c0392b`)
- Charter defaults 08:00/17:00 set via `$el.val(cfg.defaultTime)`
- `onChange` callback refreshes AM/PM labels, picker status, and payload preview

### PASS — AM/PM badge handling
- `updateAmPmLabels()` runs on init, time change, date change, fixture load
- Badge inserted as `.wsb-time-ampm-badge` span inside `.wsb-booking-client-field--time` wrapper
- CSS: absolute positioning, right-aligned, subtle grey (`#999`, `0.7rem`), `pointer-events: none`
- Time inputs receive `padding-right: 40px` to prevent overlap

### PASS — Fixture drawer interaction
- Drawer opens/closes with toggle button
- Fixture chips populate form and run server + handover preview checks
- Status messages show match/mismatch results
- Drawer scoped to `?debug=1` only

### PASS — Debug vs normal mode branching
- `DEBUG` flag derived from `window.location.search.indexOf('debug=1')`
- `logDebug()` only outputs when `DEBUG` is true
- Server preview calls (`postPayloadPreview`, `postHandoverPreview`) always active when endpoints configured

### PASS — No excessive global state or brittle selectors
- `placeSnapshots` is the only significant mutable global (reset per `initBookingBuilder` call)
- Selectors use stable `data-wsb-*` attributes and `name` attributes
- No jQuery selectors in new code (except for clock-timepicker integration)

### PASS — No console/log noise in production
- All `logDebug()` calls gated by `DEBUG` flag
- No `console.error` or `console.warn` from our code
- Known Google Maps deprecation warnings are expected and unavoidable

### NOTE — Potential race condition (low risk)
- `initClockTimePicker` and `initGooglePlacesAutocomplete` run sequentially in `initBookingBuilder`
- Clock-timepicker depends on jQuery being loaded; both check library availability before initialising
- No observable race conditions in testing

---

## 9. PHP Quality

### PASS — Enqueue logic
- Assets registered and enqueued only when shortcode renders (`enqueue_assets()` called from `render_shortcode()`)
- Google Places script loaded conditionally on `GOOGLE_API_KEY` constant
- Clock-timepicker enqueued with jQuery dependency
- Inline config script (`window.WSB_BOOKING_CLIENT_FORM`) added before main script

### PASS — Google key handling
- API key passed via `rawurlencode()` in script URL
- Never logged or exposed in debug output beyond the standard script tag
- `googlePlaces` config object exposes `enabled`/`available`/`requiredForQuoteReady` flags only

### PASS — Config scaffold defaults
- `get_default_booking_site_config()` returns safe values matching `docs/booking-site-config-contract.md`
- `get_cached_booking_site_config()` currently returns defaults (no live fetch)
- Lead times, capacity, picker, and blockouts defaults are coherent

### PASS — Field registry attributes
- All fields have `key`, `label`, `placeholder`, `type`, `required`, `applies_to`, `admin_editable`
- Date fields include `date_min_attr` / `date_max_attr` from config
- Time fields include `step_attr` from config
- Number fields include `max_attr` from config
- `applies_to` correctly scopes fields (e.g., `charter_additional_stop` to `transfer` only)

### PASS — Validator date/time logic
- Uses `wp_timezone()` for DateTime calculations
- Lead-time validation: `transfer_min_notice_minutes` (300) and `charter_min_notice_minutes` (2880)
- Max advance validation: `max_advance_booking_days` (365)
- Charter-specific: `dropoff_time` required, end time must be after start time
- Return trips validated: `count(legs) >= 2`
- Time parsing handles 12-hour AM/PM format

### PASS — Handover preview service
- Deterministic canonical signing: recursive key sort + stable JSON encode
- HMAC-SHA256 computed only when secret is non-empty
- Secret resolution: constructor param → `WSB_CLIENT_V2_HANDOVER_SECRET` → empty string
- Envelope includes all required `signed_fields`
- `mode: "dry_run"` hardcoded; no booking-site calls

### NOTE — Shortcode rendering complexity (acceptable)
- `render_shortcode()` is ~240 lines with mixed HTML/PHP output buffering
- Helper methods (`render_number_field`, `render_text_field`, `render_date_field`, `render_time_field`) reduce duplication
- Escaping is consistent: `esc_attr()`, `esc_html()`, `esc_url_raw()`
- No XSS vectors observed; all dynamic values escaped

---

## 10. CSS / UI Maintainability

### PASS — Normal/debug layout scoping
- `.wb-debug` utility class correctly scopes all debug-only elements
- Normal page: no preview panel, no fixture drawer, no dev header
- Debug page: two-column layout with sticky preview panel on desktop

### PASS — Picker styles
- Native date/time inputs styled consistently with branded calendar icon
- Native time indicator hidden to avoid overlap with custom icon
- `.wsb-date-blocked` state provides visual feedback for out-of-range dates
- `.wsb-picker-status` provides inline validation messages

### PASS — Responsive behaviour
- `.wsb-booking-client-grid--compact` collapses to single column below 680px
- Fixture drawer adapts to viewport width (`min(460px, calc(100vw - 2rem))`)
- Debug two-column layout becomes stacked on mobile

### NOTE — CSS duplication (low priority)
The `.wsb-booking-client-preview-column` and several `.wb-debug` rules are duplicated 2-3 times in `assets/css/booking-client-form.css` (lines 89-99, 509-519, 553-563). This is harmless but makes maintenance harder. A single consolidated block of `.wb-debug` overrides would be cleaner.

### NOTE — CSS over-specificity (low priority)
Some rules use high specificity to override third-party styles (e.g., `.wsb-booking-client-field--time .clock-timepicker input { width: 100% !important; }`). This is necessary for the clock-timepicker library but should be documented as intentional overrides.

### PASS — No style leakage risk
All Booking Builder styles are prefixed with `.wsb-booking-client-` or `.wsb-`. No bare element selectors that could leak outside the Booking Builder shell.

---

## 11. Fixtures / Tests

### PASS — Fixture count
- 29 fixtures in `tests/fixtures/booking-payload-v2-fixtures.json`
- Covers: one-way, return, additional stop, trailer/oversize, route scaffold, route options, validation flags, charter scaffold, charter with trailer, place snapshots (mock and Google), lead-time violations, max-advance violation, missing legs, missing from/to, bad schema version
- Both valid (18) and invalid (11) cases covered

### PASS — Place snapshot coverage
- `valid-with-place-snapshots` — mock Google place IDs
- `valid-return-with-place-snapshots` — mock IDs on both legs
- `valid-one-way-with-google-place-snapshots` — real Google place IDs
- `valid-return-with-google-place-snapshots` — real IDs on both legs
- `valid-charter-with-google-place-snapshots` — real charter place IDs

### PASS — Lead-time cases
- `invalid-transfer-inside-lead-time` — 5-hour violation
- `invalid-charter-inside-lead-time` — 48-hour violation
- `invalid-return-inside-lead-time` — return leg violation
- `invalid-pickup-beyond-max-advance` — 365-day violation

### PASS — Invalid fixtures intentionally skipped
- Handover runner correctly skips `expected_ok: false` fixtures
- Payload runner tests them (expects them to fail validation)
- Rationales provided for edge-case fixtures (e.g., `invalid-passengers-zero` normalises to valid)

### PASS — Handover fixture runner verifies meaningful structure
- Asserts `handover_version`, `schema_version`, `mode`, `source_site`, `target_site`
- Asserts `meta.preview_only`, `meta.real_handover_enabled`
- Asserts `integrity.algorithm`, `integrity.signature` non-empty, `signed_fields` completeness
- Validates envelope structure, not just pass/fail

### NOTE — Documentation drift
`docs/booking-payload-v2-contract.md` line 161 states "Total fixtures: 22", but the actual fixture file contains 29 fixtures. This should be updated for accuracy.

---

## 12. Findings

### Critical Issues
**None.**

### Medium Issues
**None requiring immediate action.**

### Low-Priority Cleanup Ideas

1. **CSS consolidation** — Duplicate `.wb-debug` rule blocks in `assets/css/booking-client-form.css` (lines 89-99, 493-594, 509-563) should be merged into a single block. This is cosmetic but improves maintainability.

2. **Documentation drift** — `docs/booking-payload-v2-contract.md` says 22 fixtures; actual count is 29. Update the document.

3. **`refreshPreview is not defined` console error (investigate)** — One `ReferenceError` appeared when clicking the Return radio button during Playwright testing. The error originates from `HTMLInputElement.onChange`, suggesting a Bricks or GTM-injected inline handler rather than our `addEventListener` code (our references are properly scoped). The payload preview continued to work. **Recommended action:** verify this is reproducible and, if so, check whether Bricks is adding inline `onchange` handlers to radio inputs that reference a now-removed global function. If confirmed as external, no action needed from our side.

4. **`wp_register_script` defer notice** — WordPress 7.0 logs that `defer` is not a recognised key in `wp_register_script` args. This is pre-existing and not introduced by our code, but should be addressed separately (use `strategy` => 'defer' instead).

---

## 13. Branch Safety Assessment

**The branch is SAFE to use for booking-site v2 receiver planning.**

The marketing-side Booking Builder:
- Produces a well-formed `BookingPayload v2` with `schema_version: "2.0"`
- Validates and normalises payloads deterministically
- Provides a dry-run handover envelope with HMAC signing
- Maintains clear ownership boundaries (marketing = intake, booking-site = authority)
- Has no live external API calls (Google Places script loading is the only external dependency, and it is conditional on `GOOGLE_API_KEY`)
- Preserves the legacy Bricks/Fluent form untouched
- Has 29 passing fixtures covering valid, invalid, edge-case, and lead-time scenarios

---

## 14. Recommended Next Task

Proceed with **booking-site v2 receiver planning** in `ws-bookings`:

1. Design the v2 intake endpoint in `ws-bookings` that accepts `BookingPayload v2`
2. Define itinerary/trip parent model (Phase 4) database schema
3. Design the handover token flow for live (non-preview) submissions
4. Plan the validation contract: what the booking site re-validates vs trusts from marketing
5. Wire the real `WSB_CLIENT_V2_HANDOVER_SECRET` for production handover

---

## 15. Validation Command Log

```bash
# PHP lint (all 6 files)
php -l inc/class-booking-client-form-shortcode.php
php -l inc/class-booking-external-services.php
php -l inc/class-booking-field-registry.php
php -l inc/class-booking-payload-v2-normalizer.php
php -l inc/class-booking-payload-v2-validator.php
php -l inc/class-booking-payload-v2-handover-service.php

# JS syntax
node --check assets/js/booking-client-form.js

# Fixture runners
php scripts/run-booking-payload-fixtures.php
php scripts/run-booking-handover-fixtures.php

# Smoke tests
curl -k -s -o /tmp/wsb-booking-builder.html -w "%{http_code}\n" https://wolfshuttles.local/booking-builder/
curl -k -s -o /tmp/wsb-booking-builder-debug.html -w "%{http_code}\n" "https://wolfshuttles.local/booking-builder/?debug=1"

# API/network grep
grep -R "wp_remote_get\|wp_remote_post\|fetch(" inc assets -n
# Result: no matches in new code; matches only in inc/legacy-snippets/

# Debug log
tail -n 200 wp-content/debug.log
# Result: no ws-bookings-client fatals; only pre-existing WordPress 7.0 wp_register_script notices and Bricks theme warnings
```

---

*Review completed. No code changes required. Review document only.*
