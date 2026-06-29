# Phase 2 Progress

## Current Status Summary

- Fixture corpus at 20 fixtures.
- Leg-scoped additional stops implemented (outbound and return legs each have their own stop controls).
- Blockouts diagnostic scaffold added; marketing payload always includes scaffold with `authority: "booking_site"`.
- Route scaffold preserved; charter preview mode added.
- Developer Fixture Drawer works at `/booking-builder/?debug=1`.
- No real booking submission enabled.
- Legacy Bricks/Fluent flow unchanged.
- Latest smoke tests passed (curl HTTP 200, no critical errors, no plugin fatal).

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

## Phase 2G — Repeatable BookingPayload v2 fixture runner

- Created `tests/fixtures/booking-payload-v2-fixtures.json` with 10 fixtures:
  - 4 valid (one-way, return, additional stop, trailer/oversize)
  - 6 invalid (missing from, missing to, passengers=0, missing return leg, passengers=0+no legs, bad schema version)
- Created `scripts/run-booking-payload-fixtures.php` — terminal fixture runner that:
  - Bootstraps WordPress, loads the actual v2 normalizer and validator classes
  - Normalises each fixture payload
  - Validates each normalised payload
  - Compares actual `valid` result to `expected_ok` from the fixture
  - Prints per-fixture pass/fail with error details on mismatch
  - Exits 0 if all match, 1 if any fail
- Runner is local/dev only — no database records, no bookings created, no external API calls.
- Legacy Bricks/Fluent form unchanged.
- Booking-site handover has advanced to dry-run foundation (Phase 2H).
- Google autocomplete is still pending.

## Phase 2H — V2 Handover Foundation

- V2 handover envelope service (`WSB_Client_Booking_Payload_V2_Handover_Service`) added.
  - Accepts normalised + validated BookingPayload v2, returns signed envelope.
  - Envelope: `handover_version=2.0`, `schema_version=2.0`, `mode=dry_run`, `source_site=marketing`, `target_site=booking`, `payload`, `integrity.signature`, `meta.preview_only=true`, `meta.real_handover_enabled=false`.
  - HMAC signing via `hash_hmac('sha256', ...)`.
  - Deterministic canonical signing: recursive key sort + stable JSON encode.
  - Secret from `wsb_client_v2_handover_secret()` never exposed to JS.
- HMAC signing support added.
  - Secret resolved: constructor param → `WSB_CLIENT_V2_HANDOVER_SECRET` → `local_v2_handover_preview_secret` fallback (WP_DEBUG) → empty string.
  - Empty secret intentionally produces empty `integrity.signature` for local/terminal tests.
- Dry-run handover preview REST endpoint: `POST /wp-json/ws-bookings-client/v1/handover-preview`.
  - Requires `X-WP-Nonce` (`wp_rest`).
  - Validates payload, returns `handover_envelope` only on valid payloads.
  - Never calls the booking site, never creates a token, never writes DB records.
- Handover preview fixture runner: `php scripts/run-booking-handover-preview-fixtures.php`.
  - Valid fixtures only, asserts envelope structure and signing fields.
- All Phase 2G fixtures still pass; existing legacy flow unchanged.

### Phase 2H verification check

- Verified the current `feature/phase-2h-v2-handover-foundation` branch with lint, fixture runners, page smoke tests, REST preview tests, and debug-log inspection.
- `php scripts/run-booking-payload-fixtures.php` passed: 20/20 fixtures matched expectations.
- `php scripts/run-booking-handover-preview-fixtures.php` passed: 13 valid fixtures passed, 7 invalid fixtures skipped, 0 failures.
- `/booking-builder/` returned HTTP 200.
- `/booking-builder/?debug=1` returned HTTP 200.
- Valid handover preview request returned `200 OK`, `ok: true`, and a dry-run handover envelope.
- Invalid handover preview request returned `200 OK`, `ok: false`, and validation errors without a handover envelope.
- Fresh `wp-content/debug.log` entries from this run showed no new `ws-bookings-client` fatal or critical error.
- Historical fatal entries remain in the log from earlier failed iterations, but they were not reintroduced by the latest smoke test.

## Phase 2I — Developer fixture drawer / payload test lab

- Added a debug-only fixture drawer behind the Booking Builder shortcode.
- The drawer is exposed on `/booking-builder/?debug=1` and stays hidden on the normal page.
- Added a fixed `Test payloads` button, fixture chips, drawer status text, and close control.
- The drawer reads from `tests/fixtures/booking-payload-v2-fixtures.json` and can populate the live form with fixture payloads.
- Clicking a fixture runs the existing server-side payload preview and the dry-run handover preview so the browser can compare expected vs actual results.
- The payload fixture corpus was expanded to support the drawer workflow while keeping the existing fixture runner passing.
- Phase 2I keeps the legacy Bricks/Fluent flow untouched and does not enable real booking submission.

## Phase 2J — Schema extension scaffold alignment

- Fixed BookingPayload v2 normaliser data loss by adding preservation of `service_group`, top-level `route`, `validation_flags`, and `charter`.
- Added `normalize_service_group()`, `normalize_route()`, and `normalize_charter()` helper methods to the normalizer.
- Route scaffold now includes: `provider`, `selected_route_id`, `selected_route_label`, `distance_meters`, `duration_seconds`, `polyline`, `route_options`.
- Charter scaffold defaults to `{ enabled: false, type: null, days: [] }`.
- `validation_flags` is preserved or defaulted to `{}`.
- Aligned meta naming: both `meta.preview_only` (true) and `meta.handover_mode` ("preview") are now set in JS and PHP normalizer.
- Added 4 new fixtures proving the scaffolds survive normalisation and handover:
   - `valid-with-route-scaffold` — empty top-level route
   - `valid-with-route-options` — route with route_options[] populated
   - `valid-with-validation-flags` — validation_flags populated
   - `valid-with-charter-scaffold` — charter.enabled: false
   - `invalid-missing-legs` — flat field format with empty legs array
- All 17 fixtures pass (11 original legs-based + 5 scaffold + 1 return-with-stop + 1 blockouts).
- Handover runner passes: 10 valid pass, 5 invalid skipped.
- Updated docs: `docs/booking-payload-v2.md`, `docs/booking-payload-v2-contract.md`, `docs/phase-2-progress.md`.
- No Google API calls, no charter UI, no booking-site handover, no legacy form changes.
- Leg-scoped additional stops implemented.
- Vehicle blockout diagnostic scaffold implemented.

## Phase 2K — Charter preview mode

- Enabled Shuttle Hire / Charter tab in Booking Builder (labelled "Shuttle Hire (preview)").
- Added charter-specific form fields:
  - `charter_pickup_location`, `charter_dropoff_location`
  - `charter_pickup_time`, `charter_dropoff_time`
  - `charter_additional_stop` with toggle
- Service mode switching: transfer-only fields (trip-type pills, return leg) hidden when charter active.
- Added `updateServiceMode()` JS function to handle tab switching and UI visibility.
- Built canonical charter payload in JS:
  - `legs[0].type = "charter"`
  - `legs[0].dropoff_time` included
  - `charter.enabled = true`
  - `charter.type = "same_day"`
  - `charter.days[0]` with full day data
- Fixed charter mode to use `trip_type: "charter"` directly when Shuttle Hire tab is active (previously inherited `trip_type: "one_way"` from the hidden transfer radio).
- Fixed charter mode to use `service_type: "charter_hire"` directly when Shuttle Hire tab is active.
- Normalizer updated:
  - `service_type: "charter_hire"` now accepted
  - `normalize_charter_leg_from_flat_fields()` added for charter flat fields
  - `normalize_legs_from_payload()` accepts `charter` leg type and preserves `dropoff_time`
- Validator updated:
  - Accepts `charter` leg type in validation
  - Requires `dropoff_time` for charter legs
  - Validates end time > start time for same-day charters
- Added 4 charter fixtures:
  - `valid-same-day-charter` — valid charter with all required fields
  - `valid-same-day-charter-with-stop` — valid charter with additional stop
  - `invalid-charter-missing-dropoff` — missing dropoff_time
  - `invalid-charter-end-time-before-start` — end time before start time
- All 20 payload fixtures pass.
- All 13 valid handover fixtures pass (7 invalid skipped as expected).
- Smoke tests: `/booking-builder/` HTTP 200, `/booking-builder/?debug=1` HTTP 200.
- Debug log: no new `ws-bookings-client` fatal errors.
- Browser/Playwright MCP visual QA confirmed charter mode now produces correct payload.

## Phase 2K+ — UI Interaction Scaffold (scaffold only)

- Added no-op sortable adapter scaffold in `assets/js/booking-client-form.js`:
  - `WSB_BOOKING_UI_INTERACTIONS.isSortableAvailable()` — false until library loaded
  - `WSB_BOOKING_UI_INTERACTIONS.initSortableList(root)` — no-op placeholder
  - `WSB_BOOKING_UI_INTERACTIONS.destroySortableList(root)` — no-op placeholder
- Added PHP config flag `WSB_CLIENT_UI_INTERACTIONS_ENABLED` (default false).
- Added `uiInteractionsEnabled` to JS config via `inc/class-booking-client-form-shortcode.php`.
- Created `docs/ui-interaction-scaffold.md` documenting future use cases and library decisions.
- Added CSS hooks in `assets/css/booking-client-form.css`:
  - `.wsb-sortable-list`, `.wsb-sortable-item`, `.wsb-drag-handle`
  - `.wsb-sortable-placeholder`, `.wsb-sortable-chosen`
- No third-party library loaded; no functional changes to existing UI; all tests pass.

## Phase 2L — Legacy External Services Audit

- Audited `inc/legacy-snippets/` for external service/API-style behavior.
- Created `docs/legacy-external-services-audit.md` documenting:
  - Google Maps/Places API integration (distance matrix, place details, autocomplete, geocode)
  - HERE Maps Routing API (toll lookup via `calculate_tolls` function)
  - Booking-site handover (HMAC-signed remote POST)
  - CTIA distance logic (geo calculations for dispatch fees)
  - WordPress REST endpoints (local tracking, not part of new flow)
- Created `inc/class-booking-external-services.php` adapter scaffold with:
  - `get_route_scaffold()` — returns null/empty route structure
  - `get_toll_scaffold()` — returns false/empty toll structure
  - `get_place_scaffold()` — returns null/empty place structure
  - `get_handover_scaffold()` — returns dry_run handover structure
  - `is_google_enabled()` — feature flag (default false)
  - `is_here_enabled()` — feature flag (default false)
  - `is_handover_live()` — feature flag (default false)
- All methods are no-op, returning safe empty arrays compatible with BookingPayload v2.
- No real API calls, no API keys exposed.
- Wired adapter into `inc/booking-client.php` bootstrap.
- Legacy Bricks/Fluent flow remains unchanged.

## Phase 2N — Place snapshot scaffolding

- Added optional per-leg `place_snapshots` block to BookingPayload v2 normalizer.
- Legs now include `place_snapshots.from`, `place_snapshots.to`, `place_snapshots.stops` scaffold.
- JS preview builder includes empty `place_snapshots` scaffold in built legs.
- Added 2 new fixtures with mock place snapshot data:
  - `valid-with-place-snapshots` — one-way with mock Google place IDs
  - `valid-return-with-place-snapshots` — return trip with place snapshots on both legs
- No live Google API calls; no API keys exposed.
- Place IDs are mock/placeholder values (e.g., `mock_origin_place_id`).
- Labels are abstract; no client names/details included.

## Phase 2O — Booking-site config contract (planning)

- Created `docs/booking-site-config-contract.md` defining the configuration contract.
- Contract covers: lead times, capacity limits, picker constraints, blockouts settings.
- Documented ownership: booking site authoritative, marketing site caches and constrains.
- No live endpoint implemented yet; next task is booking-site endpoint or marketing consumer scaffold.

## Phase 2P — Quote preflight/draft itinerary roadmap (documented)

- Created `docs/quote-preflight-draft-itinerary.md` documenting the preflight concept.
- Google Places autocomplete mandatory for final production form.
- No route-cache reliance for pricing.
- Preflight must not create WooCommerce cart items or orders.
- Draft work is performance optimisation, not source of truth.

## Phase 2Q — Booking-site config consumer scaffold (completed)

- Added config scaffold methods to `inc/class-booking-external-services.php`:
  - `get_booking_site_config_scaffold()`
  - `get_cached_booking_site_config()`
  - `get_default_booking_site_config()`
  - `is_booking_site_config_fetch_enabled()`
- Config fetch disabled by default; no live HTTP calls.
- Safe defaults expose `bookingSiteConfig` to frontend via `window.WSB_BOOKING_CLIENT_FORM`.
- Added `max` attribute to passengers, baby seats, check-in bags, carry-on bags fields.
- Added `step="300"` to time inputs based on config default.
- No visual regressions; all fixtures pass.

- Removed charter additional stop UI from the Booking Builder.
- Charter legs now always use `stops: []` per business rules.
- Transfer legs (outbound/return) still support additional stops.
- Updated `inc/class-booking-client-form-shortcode.php` to remove charter stop toggle/field.
- Updated `inc/class-booking-field-registry.php` to change `charter_additional_stop` applies_to to transfer-only.
- Updated `assets/js/booking-client-form.js`:
  - Removed `buildCharterLeg` stop logic
  - Changed charter days stops to always be `[]`
  - Removed charter additional stop event listeners
  - Removed charter stop UI initialization
- Updated `inc/class-booking-payload-v2-normalizer.php`:
  - Removed stop handling from `normalize_charter_leg_from_flat_fields`
  - Added charter stop clearing in `normalize_legs_from_payload`
- Updated `tests/fixtures/booking-payload-v2-fixtures.json`:
  - Renamed `valid-same-day-charter-with-stop` to `valid-same-day-charter-with-trailer`
  - Removed stops from both legs and charter.days arrays
  - Added trailer: true for the new fixture
- Updated `docs/booking-payload-v2.md` to clarify stop rules.
- All fixture runners still pass (20 fixtures).
## Phase 2R — Booking-site config date/time constraints (completed)

- Added date constraints to `inc/class-booking-field-registry.php`:
  - `outbound_pickup_date` and `return_pickup_date` now include `date_min_attr` and `date_max_attr`
  - Minimum date calculated from `transfer_min_notice_minutes` (300 = 5 hours)
  - Maximum date calculated from `max_advance_booking_days` (365 days)
- Updated `inc/class-booking-client-form-shortcode.php` to render date min/max attributes.
- Added frontend time constraint logic in `assets/js/booking-client-form.js`:
  - `constrainTimeByDate()` sets min time attribute when selected date equals minimum allowed date
  - Reads constraints from `CONFIG.bookingSiteConfig.lead_times`
- Enhanced `inc/class-booking-payload-v2-validator.php` with lead-time validation:
  - Validates transfer/charter pickup datetime against respective minimum notice windows
  - Validates max advance booking days for all legs
  - Uses WordPress timezone via `wp_timezone()`
- Added 4 new lead-time violation fixtures:
  - `invalid-transfer-inside-lead-time` — pickup too soon for transfer
  - `invalid-charter-inside-lead-time` — pickup too soon for charter (requires 48 hours)
  - `invalid-pickup-beyond-max-advance` — pickup date > 365 days in future
  - `invalid-return-inside-lead-time` — return leg pickup too soon
- All 26 payload fixtures pass (26 total, up from 22).
- All 15 valid handover fixtures pass (11 invalid skipped as expected).

## Phase 2S — Date/time picker parity (completed)

- Ported legacy date/time picker styling and behavior into plugin-owned files.
- Added custom CSS in `assets/css/booking-client-form.css`:
  - Branded date/time input styling with custom calendar icon (masked SVG)
  - Blocked/out-of-range date state (`.wsb-date-blocked`)
  - Picker status messages (`.wsb-picker-status`, `.wsb-picker-status--ok`)
  - AM/PM visual badge (`.wsb-time-ampm-badge`) for legacy-style time labels
  - Blockout legend UI (`.wsb-booking-client-picker-legend`)
- Added date/time helper functions in `assets/js/booking-client-form.js`:
  - `setDateDefaults()` — defaults outbound/return/charter dates to tomorrow
  - `setCharterTimeDefaults()` / `clearCharterTimeDefaults()` — 08:00/17:00 defaults
  - `deriveAmPmLabel()` / `updateAmPmLabels()` — legacy-style AM/PM badges
  - `getBlockedDatesFromConfig()` / `validateDateAgainstBlockouts()` — scaffold for global blockouts
  - `refreshPickerStatusMessages()` — live lead-time/blockout status feedback
- Updated `inc/class-booking-client-form-shortcode.php`:
  - `render_date_field()` now wraps dates in `.wsb-booking-client-picker-group` with status containers
  - Added picker legend copy (blocked dates, charter 48-hour lead time, return lead time)
  - Added translatable picker status strings
- Updated `inc/class-booking-external-services.php`:
  - Added `blocked_dates` empty array to default config scaffold
- Transfer rules verified: one-way, return, additional stops, Shuttle Hire, charter 48-hour lead time.
- Company-profile max values remain future booking-site config work (not implemented).
- No third-party picker library added; no CDN dependencies; pure CSS/JS over native inputs.
- All 26 payload fixtures pass.
- All 15 valid handover fixtures pass (11 invalid skipped as expected).
- Browser MCP visual QA: form renders correctly, charter/return tabs switch mode, fixture drawer opens and populates form.

## Phase 2V — Legacy clock-style timepicker restored (completed)

- Copied `jquery-clock-timepicker.min.js` from theme `themes/bricks-child/assets/js/` into plugin `assets/js/jquery-clock-timepicker.min.js`.
- Registered `wsb-clock-timepicker` script handle in `inc/class-booking-client-form-shortcode.php` with jQuery dependency.
- Added `initClockTimePicker(root)` function in `assets/js/booking-client-form.js`:
  - Initializes legacy jQuery ClockTimePicker on transfer pickup/return pickup times
  - Initializes charter start/end times with defaults 08:00 / 17:00
  - 5-minute precision (`precision: 5`)
  - AM/PM label integration via `updateAmPmLabels`
  - `onChange` callback triggers AM/PM refresh, picker status refresh, and payload preview
  - Color styling uses `#c0392b` (matching plugin CTA color)
- Wired `initClockTimePicker(root)` into `initBookingBuilder` after picker defaults and before Google Places init.
- Debug page layout fixed: removed `.wb-debug .wsb-booking-client-main-column { display: none }` bug; both form and preview now visible side by side on desktop.
- All validation and fixture runners still pass.

## Phase 2W — Section wording and hero cleanup (completed)

- Removed dark "Plan your transfer" hero card and eyebrow from the transfer section; replaced with minimal card header.
- Updated section headings:
  - "Outbound leg" → "Trip details" (first transfer section, no large heading needed)
  - "Return leg" → "Return transfer" (return section keeps clear heading for return-specific info)
  - "Charter details" → "Shuttle Hire" heading with "Need a custom quote? Click to mail us your itinerary" subtext
- Removed "Preview only" badge from charter section header.
- Developer header text ("Build a new booking request. No real booking submission is enabled yet.") scoped to `?debug=1` only.
- CTA button text remains "Check Pricing & Availability".
- Additional stop toggles/fields preserved on outbound and return legs; charter section has no additional stops.


## Phase 2T — Google Places Autocomplete scaffold (completed)

- Added plugin-owned Google Places script enqueue in `inc/class-booking-client-form-shortcode.php`.
  - Only enqueues when `GOOGLE_API_KEY` constant is defined and non-empty.
  - Only loads on Booking Builder shortcode pages.
  - Does not hard-code or log the API key.
- Added `googlePlaces` frontend config flag:
  - `enabled`, `available`, `requiredForQuoteReady`.
- Added Google Places Autocomplete to Booking Builder location fields:
  - `outbound_from`, `outbound_to`
  - `return_from`, `return_to`
  - `charter_pickup_location`, `charter_dropoff_location`
- Place snapshot capture stores in JS state:
  - `provider`: `google_places`
  - `place_id`, `label`, `formatted_address`
  - `lat`, `lng` (numeric)
- Stale-edit handling:
  - If user edits text after selection, snapshot is marked stale via `.wsb-booking-client-field--place-stale`.
  - Re-focusing field clears stale state.
- Payload integration:
  - `buildLeg()` and `buildCharterLeg()` now include `place_snapshots` populated from JS state.
  - `from`/`to` display strings remain for compatibility.
  - `validation_flags.google_place_snapshots_ready` indicates quote-ready status.
- Styling:
  - `.wsb-booking-client-field--place-selected` for valid selections.
  - `.wsb-booking-client-field--place-stale` for edited-after-selection state.
- Validator added optional warning for missing place snapshots.
- No route/distance/toll calls made; no booking-site calls made.
- Legacy componentRestriction: country `ZA` preserved.
- 3 new fixtures added (total 29):
  - `valid-one-way-with-google-place-snapshots`
  - `valid-return-with-google-place-snapshots`
  - `valid-charter-with-google-place-snapshots`
- All 29 payload fixtures pass.
- All 18 valid handover fixtures pass (11 invalid skipped as expected).

## Phase 2U — Booking Builder UI polish (completed)

- Refreshed frontend CSS and shortcode markup to move closer to legacy booking form styling.
- Tabs updated: "Book a Ride" (active red) and "Shuttle Hire".
- Hero card simplified: removed developer preview copy; normal page shows product-like heading only.
- Preview summary and badge hidden on normal page via CSS; debug page restores them via `.wb-debug` utility class.
- CTA button updated to "Check Pricing & Availability".
- Rounded inputs and clean white card styling applied; dark gradient hero retained.
- Google Places display string improved:
  - New `formatAddressForDisplay()` helper builds readable location from `address_components`.
  - Strips trailing "South Africa" from `formatted_address` when needed.
  - Still preserves `place_id`, `lat`, `lng`, and raw `formatted_address` in payload.
- Date picker icon overlap fixed:
  - Native browser date/time picker indicators hidden (`display: none`) to avoid overlap with custom calendar icon.
  - Custom branded calendar icon retained via `::after` mask.
- Time picker opening behaviour:
  - Native `<input type="time">` used with `step="300"`.
  - Browser-native picker opens on click/focus on macOS/Chrome.
  - Legacy jQuery ClockTimePicker not reused (would require theme asset or vendor approval); exact legacy behaviour documented as limitation.
- Normal `/booking-builder/` page no longer exposes developer preview copy or fixture drawer.
- Debug page `/booking-builder/?debug=1` keeps fixture drawer, dev header, and raw preview panels.
- All 29 payload fixtures pass.
