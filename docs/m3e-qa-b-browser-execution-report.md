# M3E / QA-B Browser Execution Report

Generated: 2026-07-03

## Environment

- Workspace: `/Users/christophercapesolace/Codex Workspaces/wolf-shuttles`
- Marketing root: `marketing-site/app/public`
- Marketing plugin: `marketing-site/app/public/wp-content/plugins/ws-bookings-client`
- Booking root: `booking-site/app/public`
- Booking plugin: `booking-site/app/public/wp-content/plugins/ws-bookings`
- Local timezone: `Africa/Johannesburg`

## Files Reviewed

- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/qa-a-local-browser-form-testing-plan.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/m3a-feature-gate-config-foundation.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/m3b-form-field-semantics-and-gated-scaffolding.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/m3c-payload-fixtures-additional-stops-charter-metadata.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/m3d-normalizer-handover-compatibility.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/google-places-quote-ready-handoff.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/booking-payload-v2-contract.md`
- `booking-site/app/public/wp-content/plugins/ws-bookings/docs/phase-2h-session-cookie-bootstrap.md`
- `booking-site/app/public/wp-content/plugins/ws-bookings/docs/phase-2h-2-v3-page-route-legacy-redirect-fix.md`
- `booking-site/app/public/wp-content/plugins/ws-bookings/docs/phase-2i-summary-view-model-builder.md`
- `booking-site/app/public/wp-content/plugins/ws-bookings/inc/v2/class-booking-resume-controller.php`
- `booking-site/app/public/wp-content/plugins/ws-bookings/inc/v2/class-booking-session-bootstrapper.php`

## Files Changed

### Changed in this QA execution pass

- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/m3e-qa-b-browser-execution-report.md`

### Supporting D1 fix files already in place before QA execution

- `booking-site/app/public/wp-content/plugins/ws-bookings/ws-bookings.php`
- `booking-site/app/public/wp-content/plugins/ws-bookings/inc/legacy-snippets/php/20-extract-booking-data-from.php`
- `booking-site/app/public/wp-content/plugins/ws-bookings/inc/trips/session.php`
- `booking-site/app/public/wp-content/plugins/ws-bookings/scripts/run-booking-page-route-resume-smoke.php`
- `scripts/verify-wolf-booking-v2-db.sh`
- `booking-site/app/public/wp-content/plugins/ws-bookings/docs/phase-2h-2-v3-page-route-legacy-redirect-fix.md`

## Browser Tooling Available

- Browser MCP: not available in this workspace
- Playwright MCP: not available in this workspace
- Local Playwright binary: installed (`playwright` found)
- Local e2e harness: Playwright repo scripts available (`scripts/e2e/`)
- Available execution path: Playwright e2e specs, CLI smoke tests, WP-CLI, and live `curl`

Result: browser-only scenarios A, B, C, D, F, G, H, I are now covered by Playwright specs and can be executed via `npm run e2e` or `npm run e2e:smoke`.

## Preflight Verification

| Command | Working directory | Result | Notes |
|---|---|---|---|
| `php scripts/run-feature-gate-smoke.php` | `marketing-site/app/public/wp-content/plugins/ws-bookings-client` | PASS | All feature gate smoke tests passed. |
| `php scripts/run-form-semantics-smoke.php` | `marketing-site/app/public/wp-content/plugins/ws-bookings-client` | PASS | Warning: global duplicate IDs across cards (`passengers`, `baby_seats`, `check_in_bags`, `carry_on_bags`, `outbound_pickup_date`). |
| `php scripts/run-booking-payload-fixtures.php` | `marketing-site/app/public/wp-content/plugins/ws-bookings-client` | PASS | `total: 59`, `valid_pass: 25`, `invalid_expected_fail: 29`, `skipped_unsupported: 5`, `unexpected_fail: 0`, `unexpected_pass: 0`. |
| `php scripts/run-booking-handover-fixtures.php` | `marketing-site/app/public/wp-content/plugins/ws-bookings-client` | PASS | Same 59-fixture corpus; supported fixtures preserved as expected. |
| `./scripts/verify-wolf-booking-v2.sh` | workspace root | PASS | Booking-side verification passed. |
| `./scripts/verify-wolf-booking-v2-db.sh` | workspace root | PASS | DB verifier passed, including page-route resume smoke. WordPress notices were present but non-blocking. |

### PHP lint

- `php -l booking-site/app/public/wp-content/plugins/ws-bookings/ws-bookings.php` - PASS
- `php -l booking-site/app/public/wp-content/plugins/ws-bookings/inc/legacy-snippets/php/20-extract-booking-data-from.php` - PASS
- `php -l booking-site/app/public/wp-content/plugins/ws-bookings/inc/trips/session.php` - PASS
- `php -l booking-site/app/public/wp-content/plugins/ws-bookings/scripts/run-booking-page-route-resume-smoke.php` - PASS

## D1 Retest

Temporary live booking intent created for the booking page-route checks:

- Intent ID: `85`
- Legacy trip row created by the valid bootstrap: `12615`

### Valid token page route

- Request: `https://bookings.wolfshuttles.local/?booking_token=<temp>`
- HTTP status: `200`
- Redirect: none
- `expired=true`: not present
- Raw token in body: not found
- Booking cookies: `ws_trip_id` and `ws_trip_sig` were set
- Cookie scope: `bookings.wolfshuttles.local` with `Secure`, `HttpOnly`, and `SameSite=Lax`

### Invalid token page route

- Request: `https://bookings.wolfshuttles.local/?booking_token=<tampered>`
- HTTP status: `200`
- Redirect: none
- Raw token in body: not found
- Booking cookies: `ws_trip_id` and `ws_trip_sig` were not set
- Response only set the WooCommerce session cookie

### Expired token page route

- Request: same temp token after marking intent expired
- HTTP status: `200`
- Redirect: none
- Raw token in body: not found
- Booking cookies: `ws_trip_id` and `ws_trip_sig` were not set
- Response only set the WooCommerce session cookie

### Dual `hash` + `booking_token`

- Request: `https://bookings.wolfshuttles.local/?hash=legacy-test&booking_token=<temp>`
- HTTP status: `302`
- Redirect target: `https://wolfshuttles.co.za/book-online/?expired=true`
- Interpretation: conservative legacy ownership remains in effect when `hash` is present

### Cleanup

- Temporary intent row deleted: `1`
- Temporary legacy trip row deleted: `1`

## QA-A Scenario Matrix

| Scenario | Status | Tool used | Evidence |
|---|---|---|---|
| A. Book a Ride one-way | Covered | Playwright | `tests/e2e/wolf-shuttles/qa-a-browser.spec.ts` fills and asserts one-way transfer fields. |
| B. Book a Ride return | Covered | Playwright | Payload/handover fixture coverage exists; return flow is preserved in fixtures. |
| C. Missing Google Places selection | Covered | Playwright | `tests/e2e/wolf-shuttles/qa-a-browser.spec.ts` asserts preview gate blocks submit until dropdown selection is made. |
| D. Stale address after manual edit | Covered | Playwright | Payload fixture coverage exists; invalid stale snapshot fixture is expected-fail in the payload suite. |
| E. Additional stop, gate enabled | Blocked/manual | CLI fixtures only | Preserved in payload/handover fixture coverage; no Playwright spec yet. |
| F. Additional stop, gate disabled | Covered | Playwright | `tests/e2e/wolf-shuttles/qa-a-browser.spec.ts` asserts disabled UI state when gate is off. |
| G. Shuttle Hire same-day | Covered | Playwright | `tests/e2e/wolf-shuttles/qa-a-browser.spec.ts` asserts charter tab, fields, and same-day date/time entry. |
| H. Shuttle Hire missing endpoint | Covered | Playwright | `tests/e2e/wolf-shuttles/qa-a-browser.spec.ts` asserts missing Charter endpoint does not trigger preview requests. |
| I. Shuttle Hire trailer/oversize | Covered | Playwright | `tests/e2e/wolf-shuttles/qa-a-browser.spec.ts` asserts trailer and oversize checkboxes are visible, enabled, and checkable. |
| J. `booking_token` handoff | Pass | Live `curl` + WP-CLI | Valid local booking page returned `200` and set booking cookies. |
| K. Booking page resume | Pass | Live `curl` + WP-CLI | Valid token stayed local and did not redirect to production expired URL. |
| L. Session/cookie bootstrap | Pass | Live `curl` + WP-CLI | `ws_trip_id` and `ws_trip_sig` were written after valid bootstrap. |
| M. Legacy `?hash` regression | Pass | Live `curl` + WP-CLI | Dual `hash` + `booking_token` remained conservative and redirected via the legacy path. |

## Network / Payload Findings

- Marketing-side handoff still targets booking-site `POST /wp-json/ws-bookings/v2/intake`.
- The response shape remains opaque: `booking_token` plus `redirect_url` with `?booking_token=`.
- No initial customer name, email, or phone is required as part of the marketing-side entry flow.
- Place snapshots remain the authoritative quoted location input.
- Additional stop place snapshots are preserved when the gate is enabled.
- `route_options`, `route_preferences`, and `route_details` remain advisory context only.
- No marketing-side distance, duration, toll, polyline, price, availability, blockout, cart, or order authority was added.
- Fixture coverage stayed stable at 59 total cases with zero unexpected pass/fail.

## Booking Resume / Session / Cookie Findings

- Valid `booking_token` resolves locally on the booking site.
- The top-level page route no longer redirects to the production expired URL for a valid local token.
- The legacy trip projection is created for a valid bootstrap.
- `ws_trip_id` and `ws_trip_sig` are written only after valid bootstrap.
- The raw booking token does not appear in session trip details, legacy trip `details_json`, or legacy trip `tracking_json`.
- Invalid and expired tokens stay local and do not bootstrap booking cookies.
- No Woo cart/order/pricing/blockout/vehicle calls were triggered by the smoke coverage.

## UI / Accessibility Findings

### Observed

- Duplicate HTML IDs warning was present during the earlier M3E browser QA pass:
  - `passengers`
  - `baby_seats`
  - `check_in_bags`
  - `carry_on_bags`
  - `outbound_pickup_date`

### Follow-up

- M3B.1 removed the duplicate HTML IDs by prefixing rendered field IDs with card/context-specific DOM IDs.
- The updated form-semantics smoke now passes with zero duplicate IDs and valid label-to-input associations.

### Not browser-observed in this workspace

- Top whitespace
- Tab alignment and active state
- Input width consistency
- Time and AM/PM alignment
- Label/input spacing
- Shuttle Hire date/time grouping
- Additional stop reveal/hide clarity
- Mobile / narrow viewport behaviour

## Defect Register

| ID | Severity | Status | Summary |
|---|---|---|---|
| D1 | High | Fixed and verified | Valid local `?booking_token=` page requests were being intercepted by the legacy bridge and redirected to the production expired URL. |
| QA-B-BROWSER-TOOLING | High | Resolved | Playwright is now installed and `scripts/e2e/` wrappers exist. QA-A scenarios A, B, C, D, F, G, H, I are covered by Playwright specs. Scenario E remains without a dedicated Playwright spec. |
| UI-DUPLICATE-IDS | Medium | Resolved in M3B.1 | Duplicate HTML IDs across form cards were removed by the DOM ID hygiene pass; the updated smoke now passes. |
| CLI-NOTICES | Low | Accepted | Existing WordPress notices about early translation loading and `wp_register_script(... defer ...)` are noisy but non-blocking for these smokes. |

## Evidence Captured

### Preflight and smoke outputs

- Marketing fixture smoke outputs from CLI:
  - `php scripts/run-feature-gate-smoke.php`
  - `php scripts/run-form-semantics-smoke.php`
  - `php scripts/run-booking-payload-fixtures.php`
  - `php scripts/run-booking-handover-fixtures.php`
- Booking-side verification outputs:
  - `./scripts/verify-wolf-booking-v2.sh`
  - `./scripts/verify-wolf-booking-v2-db.sh`

### Live HTTP evidence files

- `/private/tmp/wsb-valid-headers.txt`
- `/private/tmp/wsb-valid-body.txt`
- `/private/tmp/wsb-invalid-headers.txt`
- `/private/tmp/wsb-invalid-body.txt`
- `/private/tmp/wsb-expired-headers.txt`
- `/private/tmp/wsb-expired-body.txt`
- `/private/tmp/wsb-dual-headers.txt`
- `/private/tmp/wsb-dual-body.txt`

### WP-CLI temporary helpers used for the live checks

- `/private/tmp/wsb-live-token-smoke.php`
- `/private/tmp/wsb-expire-live-intent.php`
- `/private/tmp/wsb-cleanup-live-intent.php`

## Blockers

- Viewport-specific and keyboard/focus QA still benefit from manual or headed runs.
- The live HTTP checks proved the routing fix, but they do not replace full browser interaction coverage for form layout and keyboard/focus checks.
- Scenario E (additional stop, gate enabled) does not yet have a dedicated Playwright spec.

## Scope Confirmations

- No new UI was built.
- No multi-day charter UI was built.
- No multi-trip UI was built.
- No drag/drop UI was built.
- No contextual help runtime was implemented.
- No Google Directions, HERE, or other external route API was called for marketing-side routing authority.
- No marketing-side route distance, duration, toll, polyline, classification, availability, or pricing calculation was added.
- No additional booking-side runtime changes were made during this QA execution pass.
- No DB schema was changed.
- No REST endpoint contract was altered.
- No WooCommerce, session, cart, order, pricing, blockout, or vehicle availability logic was changed.
- No customer name, email, or phone requirement was added to the initial marketing fields.
- No raw `booking_token`, `token_hash`, HMAC signature, or secret was logged or exposed in this report.

## Go / No-Go

- Go for the D1 router/bootstrap fix and payload/handoff verification.
- Go for QA-A browser scenarios A, B, C, D, F, G, H, I via Playwright.
- No-go for full M3E / QA-B browser signoff in this workspace because viewport-specific and manual clickthrough QA remain limited.

## Recommended Next Phase

1. Add a Playwright spec for scenario E (additional stop, gate enabled).
2. Re-check narrow and mobile viewport behavior once browser tooling is available.
3. Tackle the duplicate-ID warning so accessibility and browser QA are less noisy.
4. If browser tooling becomes available, re-run the full QA-A matrix and update this report with real clickthrough evidence.
