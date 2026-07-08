# QA-A — Local Browser / Form Testing Plan for Wolf Shuttles V3 Booking Flow

## 1. Purpose and Scope

This document is a **test plan only**. It does not implement Playwright tests, install packages, modify runtime PHP/JS, fixtures, DB schema, REST endpoints, WooCommerce/session/cart/order logic, pricing, blockouts, vehicle availability, or build UI. It does not introduce customer name/email/phone as required initial marketing fields.

The goal of QA-A is to provide a practical, step-by-step manual browser test plan for validating the current marketing forms and booking handoff flow in a local development environment, preparing for future automated Playwright/MCP browser QA.

---

## 2. Environments and URLs

Document expected local URLs (use placeholders if exact domains differ):

- **Marketing site local URL**: `https://marketing.wolfshuttles.local`
- **Booking site local URL**: `https://bookings.wolfshuttles.local`
- **V3 booking resume URL**: `https://bookings.wolfshuttles.local/?booking_token=<token>`
- **Legacy URL**: `https://bookings.wolfshuttles.local/?hash=<legacy_hash>`

Assumptions:
- LocalWP or equivalent local development environment is running.
- Both marketing and booking sites are accessible via the above placeholders.
- The booking token is obtained from the marketing form successful submission.

---

## 3. Preflight Checks

Before running manual tests, verify:

- [ ] **Plugins active**:
  - `ws-bookings-client` (marketing side) is active.
  - `ws-bookings` (booking side) is active.
- [ ] **Feature gate overrides**:
  - Verified actual M3A marketing feature gates in `inc/class-booking-feature-gates.php`:
    - `enable_multi_day_charters`
    - `enable_multi_trip_bookings`
    - `enable_additional_stops`
    - `enable_route_options_payload`
    - `enable_route_alternatives_on_shuttles_page`
    - `enable_google_places_required`
    - `enable_drag_drop_itinerary_ordering`
    - `enable_day_duplicate_delete`
    - `enable_charter_poi_fields`
    - `enable_debug_free_text_locations_local_only`
  - `enable_google_places_required` is **true**
  - `enable_additional_stops` is **true**
  - Shuttle Hire form/tab availability is part of the existing form/product surface, not currently treated as an M3A feature gate unless the feature-gate class proves otherwise.
  - Booking resume is booking-side Phase 2G/2H behaviour, not a marketing-side M3A feature gate.
  - Legacy `?hash` support is a booking-side migration/coexistence requirement, not a marketing-side M3A feature gate.
- [ ] **Google Places key/config**: Present in `wp-config.php` or environment if required for autocomplete.
- [ ] **Root verification passes**: Site loads without PHP errors.
- [ ] **Marketing payload fixtures pass**: Validate sample payloads from `m3c-payload-fixtures-additional-stops-charter-metadata.md` against current form structure.
- [ ] **Booking DB verification passes**:
  - V3 booking intents table: `$wpdb->prefix . 'ws_booking_intents'`
  - Legacy trip bridge table: `$wpdb->prefix . 'ws_bookings_trips'`
  - Test data cleanup.

---

## 4. Manual Browser Test Matrix

### A. Book a Ride One-Way
- **Starting URL**: `https://marketing.wolfshuttles.local`
- **Gate assumptions**: Shuttle Hire form/tab availability is part of the existing form/product surface, not currently treated as an M3A feature gate unless the feature-gate class proves otherwise.
- **Fields to fill**:
  - Pickup & drop-off: Google Places selection.
  - No customer name/email/phone.
- **Expected frontend behavior**: Form validates, submit button enables.
- **Expected payload**: POST to `https://bookings.wolfshuttles.local/wp-json/ws-bookings/v2/intake` with compressed Google Places snippets.
- **Booking-side behavior**: Redirect to `/?booking_token=` with no session/cookies.
- **Pass criteria**: Token returned, trip summary accurate.

### B. Book a Ride Return
(Similar to A, with return-specific fields.)

### C. Book a Ride Missing Google Places Selection
- **Starting URL**: Same as A.
- **Gate assumptions**: `enable_google_places_required` is active.
- **Fields to fill**: Invalid/empty pickup/stop snapshot.
- **Expected frontend behavior**: Form blocks submit, shows inline validation.
- **Pass criteria**: No network request for invalid input.

### D. Book a Ride Stale Address After Manual Edit
- **Starting URL**: Same as A.
- **Gate assumptions**: `enable_google_places_required` is active.
- **Fields to fill**: Pickup selection stale on manual edit.
- **Expected frontend behavior**: Form blocks submit, UI shows usage hint.

### E. Book a Ride with Additional Stop, Gate Enabled
- **Starting URL**: Same as A.
- **Gate assumptions**: `enable_additional_stops` is active.
- **Fields to fill**: Add valid stop with place_id/description/lat/lng.
- **Payload behavior**: Preserves `additional_stops[]` array with snapshots.

### F. Book a Ride Additional Stop, Gate Disabled
- **Starting URL**: Same as A.
- **Gate assumptions**: `enable_additional_stops` is disabled.
- **Fields to fill**: Attempt to add stop; button is hidden.
- **Payload behavior**: `additional_stops[]` absent in payload.

### G. Shuttle Hire Same-Day
- **Starting URL**: `https://marketing.wolfshuttles.local`
- **Gate assumptions**: Shuttle Hire form/tab availability is part of the existing form/product surface, not currently treated as an M3A feature gate unless the feature-gate class proves otherwise.
- **Fields to fill**: Today's date, valid pickup/drop-off.
- **Payload behavior**: `is_same_day: true`, excludes route calculation fields.

### H. Shuttle Hire Missing Endpoint
- **Starting URL**: Same as G.
- **Gate assumptions**: Marketing gates active as above.
- **Fields to fill**: Missing drop-off; form blocks submit.

### I. Shuttle Hire with Trailer/Oversize
- **Starting URL**: Same as G.
- **Gate assumptions**: Marketing gates active as above.
- **Fields to fill**: Check trailer/oversize checkbox.
- **Payload behavior**: `trailer_oversize: true`.

### J. booking_token Handoff
- **Starting URL**: Marketing form after valid submit.
- **Expected behavior**: Redirect to `/?booking_token=`.
- **Payload behavior**: Short-lived opaque token, no session/cookies set (`/v2/intake`).

### K. Booking Page Resume
- **Starting URL**: `/?booking_token=<valid-token>`
- **Gate assumptions**: Booking resume is booking-side Phase 2G/2H behaviour, not a marketing-side M3A feature gate.
- **Behavior**: Loads pre-filled trip data without `/v2/intake` call.

### L. Session/Cookie Bootstrap (Phase 2H)
- **Starting URL**: Booking page with `booking_token`.
- **Gate assumptions**: Phase 2H implemented, `enable_session_bootstrap` active.
- **Behavior**: Sets `ws_trip_id`/`ws_trip_sig` cookies only on valid token resume.

### M. Legacy ?hash Flow
- **Starting URL**: `/?hash=<legacy_hash>`
- **Gate assumptions**: Legacy `?hash` support is a booking-side migration/coexistence requirement, not a marketing-side M3A feature gate.
- **Expected behavior**: Graceful error or trip resolution (if migration implemented).

---

## 5. Payload Inspection Checklist

Use DevTools Network Tab:
- **Endpoint**: POST `https://bookings.wolfshuttles.local/wp-json/ws-bookings/v2/intake` (marketing site).
- **Signed payload**: Response includes `booking_token` and `redirect_url`.
- **No customer fields**: Request body excludes name/email/phone.
- **Place snapshots**: Includes `place_id`, `description`, `lat/lng` for pickup/drop-off/stops.
- **No route/pricing data**: Marketing payload omits distance, price, or availability.

---

## 6. Booking-Side Resume Checklist

Post-token-resume on booking site:
- **Token resolution**: Valid `booking_token` maps to DB intent record in `$wpdb->prefix . 'ws_booking_intents'`.
- **Legacy trip row**: Uses prefix-aware table `$wpdb->prefix . 'ws_bookings_trips'` (not `wp_ws_bookings_trips`).
- **Session keys**: minimal `trip_details`, no `ws_trip_id` or cookies at resume.
- **Security**: No raw token stored; session created only after token expiration check.

---

## 7. UI Issue Backlog

- Excessive whitespace above form.
- Tab alignment and active state visibility.
- Input width inconsistency.
- Shuttle Hire date/time grouping alignment.
- AM/PM suffix positioning.
- Label/input spacing.
- Additional stop reveal/hide clarity (drag/drop magic).
- Mobile layout testing required later.

---

## 8. Accessibility Checks

- Keyboard tab order logical.
- Focus state visible.
- Label/ARIA associations valid.
- Google Places dropdown accessible (TABS/ENTER).
- Shuttle Hire date/time fields usable via keyboard.

---

## 9. Future Playwright/MCP Plan

Smoke tests to verify:
- Valid one-way flow with token.
- Invalid Google Places validation.
- Stale snapshot blocking submit.
- Additional stop gate behavior.
- Token resume.
- Legacy hash functionality.

---

## 10. Evidence Capture

- Screenshots: clean/filled form, validation errors, cookie inspection.
- Network request/response.
- Booking page after resume.

---

## 11. Risks and Blockers

- Google Places API key required for autocomplete validation.
- LocalWP may affect cookie behavior.
- CLI tests cannot validate browser cookies.
- Route/pricing authority remains booking-side.
- Multi-day UI not built.

---

## Final Report

### Files Reviewed:
- `m3a-feature-gate-config-foundation.md`
- `phase-2h-session-cookie-bootstrap.md`
- `V3-IMPLEMENTATION-GATE-MAP.md`
- All prior QA-B phases.

### Files Changed:
- Updated `qa-a-local-browser-form-testing-plan.md` with corrected gate terminology.

### Accuracy Corrections:
- Verified the actual M3A marketing feature gates from `inc/class-booking-feature-gates.php` and removed the undefined gate labels from the plan.
- Qualified Shuttle Hire availability, booking resume, and legacy `?hash` support as non-M3A behaviors instead of feature gates.
- Clarified endpoint authority: Marketing POSTs to booking-site `v2/intake`.
- Used prefix-aware DB table language: `$wpdb->prefix . 'ws_booking_intents'` for V3 booking intents, `$wpdb->prefix . 'ws_bookings_trips'` for legacy trip bridge.
- Corrected cookie terminology (`ws_trip_id` as visible key, `ws_trip_sig` as signature).
- Clarified payload fields (e.g., place snapshots instead of hardcoded array names).
- Highlighted routing authority: Marketing preserves `route_options` but does not calculate distance/price.

### Confirmation No Runtime Changes:
- This pass was purely documentation/correction. No PHP/JS, fixtures, DB schemas, REST endpoints, or UI components were modified.
