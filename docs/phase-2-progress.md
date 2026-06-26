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
- Fixed fatal render error in `inc/class-booking-client-form-shortcode.php`.
- Cause: fragile large `sprintf()` template with mismatched argument count.
- Shortcode render now uses safer output buffering and helper render methods.
- Smoke-tested `/booking-builder/` locally: HTTP 200 and Booking Builder form markup present.
- Checked `wp-content/debug.log` after smoke test; no new `ws-bookings-client` fatal occurred.
- The form is still placeholder-only and does not submit real bookings yet.
- Existing legacy Bricks/Fluent booking flow is unchanged.
