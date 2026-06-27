# Phase 2 Progress

## Added in this milestone

- Added a new Phase 2 plugin intake scaffold under `inc/`.
- Created PHP class stubs for:
  - `WSB_Booking_Client\BookingPayloadBuilder`
  - `WSB_Booking_Client\BookingPayloadNormalizer`
  - `WSB_Booking_Client\BookingPayloadValidator`
  - `WSB_Booking_Client\BookingSiteClient`
  - `WSB_Booking_Client\BookingClientFormShortcode`
  - `WSB_Booking_Client\BookingIntakeFixtureLoader`
- Registered a new shortcode: `[ws_booking_client_form]`.
- Added placeholder JS/CSS assets for the shortcode shell.
- Added a handover mode config constant and filter allowing:
  - `legacy_hash`
  - `v2_token`
- Added a minimal fixture loader scaffold that can read `tests/fixtures/booking-intake-fixtures.v2.seed.json`.

## What remains

- Build the actual intake form UI and field mapping.
- Implement canonical payload normalization and schema validation.
- Add payload handover logic for real booking submission.
- Add the new marketing page content for the shortcode.
- Add deeper tests for validator, normalizer, and handover payload creation.
- Wire the booking-site side of `schema_version: "2.0"` intake processing.

## Phase 2 branding and field registry

- Brand reference files were added under `resources/bricks/`.
- Field registry scaffold added via `inc/class-booking-field-registry.php`.
- Booking Builder layout added.
- One-way/return UI added.
- Additional stop scaffold added.
- The shortcode now renders a styled Booking Builder form shell with stable canonical input names.
- Added local BookingPayload v2 preview behavior in JS, including one-way/return legs, richer location objects, nested luggage/add-on fields, and additional stop support.
- The preview now refreshes live on page load, input, change, blur, trip-type toggle, additional-stop toggle, and submit.
- Added stable form markup selectors and a top preview/status panel for immediate feedback.
- Fixed browser realtime preview issue by loading the shortcode script via shortcode render path and aligning JS selectors with the rendered form markup.
- `?debug=1` now logs the generated payload and missing selector diagnostics to the browser console.
- Fixed fatal render error in `inc/class-booking-client-form-shortcode.php`.
- Cause: fragile large `sprintf()` template with mismatched argument count.
- Shortcode render now uses safer output buffering and helper render methods.
- Added server-side preview validation endpoint: `/wp-json/ws-bookings-client/v1/payload-preview`.
- Wired `WSB_Client_Booking_Payload_V2_Normalizer` and `WSB_Client_Booking_Payload_V2_Validator`.
- Added validation result UI to the booking builder preview panel.
- REST endpoint tested with valid and invalid payloads.
- Smoke-tested `/booking-builder/` locally: HTTP 200 and Booking Builder form markup present.
- Checked `wp-content/debug.log` after smoke test; no new `ws-bookings-client` fatal occurred.
- The form is still preview-only and does not submit real bookings yet.
- Existing legacy Bricks/Fluent booking flow is unchanged.

## Phase 2F verification (clean debug-log check)

- Clean debug-log verification completed:
  - Backed up pre-check log, cleared active `debug.log`.
  - Re-ran `/booking-builder/` page smoke test — HTTP 200, all form markup markers present.
  - Re-ran valid REST payload-preview test — `200 OK`, `ok: true`, empty validation errors, normalized payload returned.
  - Re-ran invalid REST payload-preview test — `200 OK`, `ok: false`, useful validation errors, no PHP fatal.
  - Inspected fresh `debug.log` — no fatal from `ws-bookings-client`, `[ws_booking_client_form]` shortcode, or REST endpoint.
- Previous shortcode fatal (`class-booking-client-form-shortcode.php` line 27) was confirmed as stale from the earlier render issue. Current line 27 is a plain HTML `<div>` tag inside the output buffer — no fatal-risk path.
- Phase 2F verified complete.
