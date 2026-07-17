# FRONTEND-UX-001 — Production Booking Form UX Specification

**Status:** Documentation / audit only  
**Scope:** Marketing plugin UI polish planning  
**Out of scope:** Runtime PHP, JS, CSS, payload contracts, booking-site code, WooCommerce logic, pricing, route logic, vehicle logic, checkout logic, and feature-gate defaults.

---

## 1. Source references reviewed

### Visual concept folder
- `wolf-shuttles-ui-concepts/README.md`
- `wolf-shuttles-ui-concepts/01-book-a-ride-form/book-a-ride-one-way-confirmed-locations.png`
- `wolf-shuttles-ui-concepts/01-book-a-ride-form/book-a-ride-one-way-additional-stop-state.png`
- `wolf-shuttles-ui-concepts/01-book-a-ride-form/book-a-ride-return-details-expanded.png`
- `wolf-shuttles-ui-concepts/02-shuttle-hire-charter-concepts/shuttle-hire-state-board-expanded.png`
- `wolf-shuttles-ui-concepts/02-shuttle-hire-charter-concepts/shuttle-hire-state-board-compact.png`
- `wolf-shuttles-ui-concepts/02-shuttle-hire-charter-concepts/shuttle-hire-multiday-itinerary-overview.png`
- `wolf-shuttles-ui-concepts/03-plan-full-booking-itinerary/plan-full-booking-one-way-trip-expanded.png`
- `wolf-shuttles-ui-concepts/03-plan-full-booking-itinerary/plan-full-booking-return-trip-expanded.png`
- `wolf-shuttles-ui-concepts/03-plan-full-booking-itinerary/plan-full-booking-mixed-trip-list-with-charter-expanded.png`
- `wolf-shuttles-ui-concepts/03-plan-full-booking-itinerary/plan-full-booking-mixed-trip-list-collapsed.png`
- `wolf-shuttles-ui-concepts/03-plan-full-booking-itinerary/plan-full-booking-charter-trip-type-expanded.png`

### Marketing booking-builder files reviewed
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/inc/class-booking-client-form-shortcode.php`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/assets/js/booking-client-form.js`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/assets/css/booking-client-form.css`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/assets/js/jquery-clock-timepicker.min.js`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/assets/js/datepickers.js`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/assets/js/blockouts-frontend.js`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/inc/class-booking-field-registry.php`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/inc/class-booking-feature-gates.php`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/inc/class-booking-payload-v2-normalizer.php`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/inc/class-booking-payload-v2-validator.php`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/inc/class-booking-payload-builder.php`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/inc/class-booking-payload-v2-handover-service.php`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/inc/class-booking-external-services.php`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/ws-bookings-client.php`

### Marketing docs reviewed
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/START-HERE.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/AGENTS.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/AGENT-HANDOFF.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/booking-payload-v2.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/booking-payload-v2-contract.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/booking-intake-current-flow.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/booking-intake-roadmap.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/phase-2-progress.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/brand-system.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/legacy-form-controls-audit.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/vehicle-scoped-blockouts-v2.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/ux-drag-drop-behaviour-rules.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/ui-interaction-scaffold.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/m4a-multiday-charter-builder-shell.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/m4b-multiday-date-locked-plan-swap-dragdrop-spec.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/m5a-multitrip-builder-shell-plan.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/google-places-quote-ready-handoff.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/known-issues-debug-log.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/booking-site-v2-receiver-plan.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/qa-a-local-browser-form-testing-plan.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/pw-browser-selector-a11y-inventory.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/testing-engine.md`
- `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/testing-engine-plan.md`

### Booking-site docs reviewed (flow consistency only)
- `booking-site/app/public/wp-content/plugins/ws-bookings/inc/v2/class-v2-intake-controller.php`
- `booking-site/app/public/wp-content/plugins/ws-bookings/inc/calendar/blockouts-validation.php`
- `booking-site/app/public/wp-content/plugins/ws-bookings/inc/calendar/blockouts-store.php`
- `booking-site/app/public/wp-content/plugins/ws-bookings/docs/booking-site-v2-intake-plan.md`
- `booking-site/app/public/wp-content/plugins/ws-bookings/docs/v2-intake-endpoint.md`

---

## 2. Current UI inventory

The current customer-facing booking builder is rendered by `ws_booking_client_form` shortcode inside a `[data-wsb-booking-builder]` wrapper.

### Top-level structure
- Header is hidden in production; shown only when `?debug=1` (`wb-debug` class).
- Service tabs render as `Book a Ride` and `Shuttle Hire`.
- A two-column grid is used in debug mode (main form + sticky preview panel); in production the preview column is hidden.

### Book a Ride tab (transfer)
- Trip type pills: `One-way` / `Return` radio inputs.
- Passenger/bag counts: `passengers`, `baby_seats`, `check_in_bags`, `carry_on_bags` in a compact 2-column grid.
- Extras: `Trailer required` and `Oversize luggage` checkboxes.
- Outbound section: location inputs, optional additional-stop toggle + field, date/time inputs, blockout status message.
- Return section: hidden by default; shown when `return` trip type is selected. Contains matching location and date/time fields plus lead-time note.

### Shuttle Hire tab (charter)
- Same passenger/bag/extras fields as Book a Ride, but note a current audit issue: the shortcode renders duplicate `name` attributes for shared fields (e.g., `passengers`, `baby_seats`, `check_in_bags`, `carry_on_bags`, `trailer`, `oversize_luggage`) in both transfer and charter cards. `buildPayload()` uses `form.querySelector()` which returns the first DOM match, so values may not reliably switch when tabs change. This should be remedied before or during UX-007 if Shuttle Hire gets increased visual polish.
- Same-day panel: pickup location, drop-off location, pickup date, pickup time, drop-off time.
- Multi-day shell (feature-gated): same-day / multi-day radio pills, day-card list, toolbar (Add another day, Open all, Close all).
- Day cards: date, start time, end time, pickup location, drop-off location, places/POI intent, notes.
- Day actions per card: Open/Close toggle, Copy this day, Remove day.

### Debug-only areas
- Preview column with raw JSON preview (`[data-wsb-payload-preview]`), validation output, status text.
- Fixture drawer (`?debug=1` only): toggle button, drawer with sample chips, status badge.
- Dev header with eyebrow/title.
- Picker legends explaining blocked dates/time.
- Submit message area.

### Current CSS approach
- Rounded controls with `border-radius: 10px` on inputs.
- Primary action uses linear-gradient red-to-purple (#c0392b → #8e44ad).
- Confirmed location state uses green border/background.
- Stale edit state uses yellow/border warning style.
- Blocked dates use red border/background.
- Mobile breakpoint collapses compact grids to single column.
- Debug-only content toggled via `.wb-debug` parent class.

---

## 3. Visual target summary

The form should feel premium, client-facing, and Wolf Shuttles-branded—not a copied competitor flow.

- **Tone:** Clean, confident, premium transport. Rounded inputs and cards. Generous whitespace. Clear hierarchy.
- **Brand baseline:** Red/black/purple staging where applicable (current submit CTA already uses red-to-purple gradient). Existing brand variables (`--primary`, `--secondary`, `--base`, `--white`, `--radius-*`) must be respected.
- **Confirmed locations:** Green-coded success state for fully resolved Google Places locations.
- **Stale edits:** Distinct but non-alarming warning state when user edits after selection.
- **Cards/accordions:** Polished expand/collapse for return section and day cards. Clear headers, consistent action spacing.
- **Icons:** Use clean inline SVG icons or simple Unicode glyphs. Avoid heavy icon libraries unless approved.
- **Mobile-first:** Stacked layout, no horizontal overflow, touch-friendly tap targets (min ~44px), readable typography.
- **Accessibility:** Focus rings, ARIA states, keyboard-navigable date/time pickers and accordions.

### What should NOT be visual targets to copy
- Competitor checkout flows.
- Generic datepicker libraries.
- Cluttered multi-column desktop-only layouts on mobile.

---

## 4. Hard copy rules

The following internal/developer wording must **not** appear in customer-facing UI text, labels, placeholders, aria-labels (unless tucked in dev-only screens), or tooltips:

- `testing`, `beta`, `fixed slot`, `reserved slot`, `hidden slot`, `mock day`
- `feature gate`, `payload`, `metadata`, `non-authoritative`, `route authority`
- `V3`, `M4A`, `M4B`, `TODO`, `debug wording`
- `booking_token`, `token_hash`, `ws_trip_sig`, `ws_active_hash`
- `request_id`, `raw JSON`, `place_id`, `lat`/`lng` (internal data keys; strings like “latitude” or “longitude” are also forbidden in customer-facing copy)
- `handover`, `envelope`, `schema_version`, `handover_mode`
- `sample data drawer`, `fixture drawer`, `loaded sample`, `expected valid/invalid`
- `server validation pending/success/warnings/failed`
- `booking summary initialised`, `sample not found`, `server preview unavailable/error`
- `Booking summary ready`, `Fixture:`, `updated:`

**Rule:** If a word is shown only in `?debug=1` mode and is clearly scoped to the debug panel, it is acceptable inside that panel only. It must not leak into the production form.

---

## 5. Proposed implementation phases

### UX-002: Copy cleanup and remove internal wording
- **Goal:** Purge all developer-only copy from customer-facing UI. Replace with user-friendly labels, status text, and aria-labels.
- **Files likely to change:** `inc/class-booking-client-form-shortcode.php`, `assets/js/booking-client-form.js`, `assets/css/booking-client-form.css`.
- **Functionality that must not change:** No field keys, payload shapes, JS state keys, or form submission flow.
- **Tests to run:** PHP lint for shortcode file; JS syntax check; inspect rendered HTML in both normal and `?debug=1` modes.
- **Visual QA notes:** Read every visible label on the booking builder page in normal mode; confirm debug mode still shows its debug-specific copy correctly isolated.
- **Rollback risk:** Low (copy-only).
- **Branching:** Small commit on frontend UX branch is enough.

### UX-003: Date/time picker restoration + blockout parity audit
- **Goal:** Restore native date/time inputs to production-ready parity with legacy behaviour while removing visual debug artifacts. Audit blockout behaviour end-to-end.
- **Files likely to change:** `inc/class-booking-field-registry.php` (min/max dates), `inc/class-booking-client-form-shortcode.php` (render wrappers), `assets/js/datepickers.js`, `assets/js/blockouts-frontend.js`, `assets/js/booking-client-form.js` (lead-time status messages), `assets/css/booking-client-form.css`.
- **Functionality that must not change:** Lead-time rules, min notice minutes, max advance days, time step precision, and blocked-dates validation rules.
- **Tests to run:** PHP lint; JS syntax check; Playwright smoke on date selection, time selection, blocked date, lead-time time edge case.
- **Visual QA notes:** Verify date fields show min/max state, blocked dates are visually distinct, time fields keep AM/PM badges, clock picker width is correct on mobile.
- **Rollback risk:** Medium (picker parity is core UX).
- **Branching:** Separate branch recommended (`frontend-ux/date-blockout-parity`).

### UX-004: Location input polish
- **Goal:** Make location fields feel premium and clear. Improve confirmed / stale / cleared states. Add subtle clear button and use-current-location affordance (if API available).
- **Files likely to change:** `inc/class-booking-client-form-shortcode.php` (field wrappers), `assets/js/booking-client-form.js` (Google Places state, clear/reset), `assets/css/booking-client-form.css` (location states, icons).
- **Functionality that must not change:** Place snapshot payload shape, `place_id`/`lat`/`lng` handling in JS memory, stale-detection logic, submit-blocking rules.
- **Tests to run:** JS syntax check; Playwright smoke selecting a place, editing, clearing, re-selecting, checking preview payload/UI labels for no `place_id`/`lat`/`lng` leakage.
- **Visual QA notes:** Confirmed state uses `var(--success)` green; stale state uses `var(--warning)`; clear button does not overlap text on mobile; use-current-location button is secondary and unobtrusive.
- **Rollback risk:** Medium.
- **Branching:** Separate branch or small commit on UX branch depending on UX-003 scope; if UX-003 is small, keep on same branch.

### UX-005: Return section polish
- **Goal:** Elevate return transfer to a premium accordion/card. Smooth expand/collapse, clear header, consistent Date/time and location treatment.
- **Files likely to change:** `inc/class-booking-client-form-shortcode.php`, `assets/css/booking-client-form.css`, possibly small JS tweaks in `booking-client-form.js`.
- **Functionality that must not change:** Return visibility toggling, place snapshot hooks, field names, payload population.
- **Tests to run:** PHP lint; Playwright smoke toggling return on, filling fields, checking payload preview.
- **Visual QA notes:** Return header reads clearly; fields align with outbound section; no layout jump when hidden/shown.
- **Rollback risk:** Low.
- **Branching:** Small commit on UX branch is enough.

### UX-006: Additional stop add/remove polish
- **Goal:** Refine the optional additional-stop toggle and field reveal. Use cleaner spacing, clearer label, and consistent disabled/enabled styling.
- **Files likely to change:** `inc/class-booking-client-form-shortcode.php`, `assets/css/booking-client-form.css`, `assets/js/booking-client-form.js` (disabled state).
- **Functionality that must not change:** Feature gate behaviour (`enable_additional_stops`), stop place-snapshot behaviour, payload stop inclusion.
- **Tests to run:** JS syntax check; Playwright smoke enabling/disabling stop in outbound and return (when visible).
- **Visual QA notes:** Toggle is easy to find; disabled stop field looks inert; focus management is smooth.
- **Rollback risk:** Low.
- **Branching:** Small commit on UX branch.

### UX-007: Shuttle Hire + multi-day charter card polish
- **Goal:** Apply premium card treatment to Shuttle Hire tabs, same-day panel, and multi-day day cards. Polish active/inactive states and action buttons.
- **Files likely to change:** `inc/class-booking-client-form-shortcode.php`, `assets/css/booking-client-form.css`.
- **Functionality that must not change:** Same-day vs multi-day switch, add/duplicate/delete day rules, collapse/expand behaviour, day card payload structure.
- **Tests to run:** PHP lint; JS syntax check; Playwright smoke open/close all, add/copy/remove day, verify disabled states at 1 day.
- **Visual QA notes:** Day cards read as premium itinerary entries; active card styling is clear; action buttons are large enough for touch.
- **Rollback risk:** Medium (wide CSS surface on Shuttle Hire).
- **Branching:** Separate branch recommended.

### UX-008: M4B multi-day date-locked plan-swap drag/drop
- **Goal:** Implement the drag/drop spec already defined in `docs/m4b-multiday-date-locked-plan-swap-dragdrop-spec.md`.
- **Files likely to change:** `assets/js/booking-client-form.js` (drag/drop adapter), `assets/css/booking-client-form.css` (drag states), `docs/ux-drag-drop-behaviour-rules.md` (update if needed).
- **Functionality that must not change:** Date, start time, end time, day number, day index, sort_order must remain with slot. Fallback controls must remain available. Keyboard accessibility must remain.
- **Tests to run:** JS syntax check; Playwright drag/drop smoke (if library approved) plus fallback move up/down/swap tests; payload fixture assertions.
- **Visual QA notes:** Drag handle only visible when both gates are true; drop placeholder is visible; no layout shift after swap.
- **Rollback risk:** High (interaction complexity).
- **Branching:** Separate branch required.

### UX-009: Mobile polish
- **Goal:** Harden all previous phases for mobile. Responsive spacing, font scaling, scroll behaviour, address field behaviour, calendar popover, and clock picker positioning.
- **Files likely to change:** `assets/css/booking-client-form.css`, small responsive JS tweaks in `booking-client-form.js`.
- **Functionality that must not change:** All existing flows and business rules.
- **Tests to run:** Playwright at mobile viewport (≤680px, ≤400px) smoke on full form fill for one-way, return, shuttle hire same-day, multi-day.
- **Visual QA notes:** No horizontal scroll; inputs are full-width; date/time pickers open above the keyboard where possible; action buttons are easy to tap when scrolled.
- **Rollback risk:** Medium.
- **Branching:** Can be combined with UX-007 branch or separate small commit.

---

## 6. Date/time picker section

### Current date picker behaviour
- Uses native `<input type="date">` rendered by `BookingFieldRegistry` with `date_min_attr` / `date_max_attr`.
- Defaults to tomorrow via JS `setDateDefaults()` when empty.
- jQuery UI datepicker legacy code in `assets/js/datepickers.js` is present but currently **not wired** to the new booking-builder inputs (selector mismatch). It is prepared for `input[placeholder="Select Date"]` / `input[name$="_date"]` patterns.
- Status message elements (`.wsb-picker-status`) show blocked-dates and lead-time warnings via `refreshPickerStatusMessages()`.

### Current clock time picker behaviour
- Uses legacy `jquery-clock-timepicker.min.js` (copied from theme into plugin).
- Initialised in `initClockTimePicker()` on:
  - `outbound_pickup_time`
  - `return_pickup_time`
  - charter-day `start_time` and `end_time`
  - `charter_pickup_time`
  - `charter_dropoff_time`
- Charter pickup defaults to `08:00`, dropoff to `17:00` via `setCharterTimeDefaults()`.
- Precision: 5 minutes (`precision: 5`).
- Colours: `#c0392b` red header and selector colour.
- `onChange` refreshes AM/PM labels and picker statuses.

### AM/PM badge behaviour
- Badges (`<span class="wsb-time-ampm-badge">`) are injected dynamically by `updateAmPmLabels()`.
- Derived from hour value: `h >= 12` → `PM`, else `AM`.
- Badges are removed when the field is empty.
- Wrapper class `.wsb-booking-client-field--time` is applied for layout.

### Where date/time fields exist
- Outbound: `outbound_pickup_date`, `outbound_pickup_time`
- Return: `return_pickup_date`, `return_pickup_time`
- Charter same-day: `outbound_pickup_date`, `charter_pickup_time`, `charter_dropoff_time`
- Charter multi-day per card: `charter_day_date`, `charter_day_start_time`, `charter_day_end_time`

### What appears broken
- Legacy `datepickers.js` blockout calendar styling is **not active** on the current booking-builder inputs because the jQuery UI datepicker instance is not initialised on them in the new form. Blockout awareness is therefore limited to native `min`/`max` plus the `.wsb-date-blocked` class set by JS after validation.
- `blockouts-frontend.js` also targets legacy selectors and will not activate on the new inputs unless re-scoped or re-initialised.
- On same-day charter, changing the date only constrains `charter_pickup_time` min; `charter_dropoff_time` is not paired for lead-time constraining today.

### What legacy/production parity should be checked
- Min notice parity: transfer = 300 minutes; charter = 2880 minutes (48 hours).
- Max advance parity: from booking-site config (default 365 days).
- Time step parity: 5 minutes.
- Default date parity: tomorrow.
- Blocked-date parity: if booking site provides `blockouts.blocked_dates`, they must appear as unavailable; user-facing messaging must stay clean (no internal words).
- Time-range blockout parity: selected time must be rejected if it falls within a blocked range.

### What must be restored before or during UX-003
- Ensure picker status messages show blocked/lead-time copy in clean user language.
- Ensure `wsb-date-blocked` state is visible without debug-only legends unless explicitly approved for debug only.
- Ensure jQuery UI datepicker migration decision is locked: keep native inputs for production, or officially re-wire `datepickers.js` with *clean* copy. The current spec recommends native inputs with inline status rather than a jQuery UI calendar popup.
- Pair `charter_dropoff_time` with the same lead-time / blockout scrutiny as pickup.

---

## 7. Blockout parity section

### Audited behaviour
- Booking-site authority blockout file: `booking-site/app/public/wp-content/plugins/ws-bookings/inc/calendar/blockouts-validation.php` (`wsb_is_blocked` / `wsb_validate_blockouts_payload`).
- Marketing-side scaffold: `docs/vehicle-scoped-blockouts-v2.md` and `buildPayload()` include a diagnostic `blockouts` scaffold but do not perform time-range gating.
- Marketing-side `getBlockedDatesFromConfig()` is a shallow scaffold reading `bookingSiteConfig.blockouts.blocked_dates`. It supports only fully-blocked dates, not time ranges.

### Does the current booking builder respect blockouts where expected?
- **Dates:** Partially. Native `min`/`max` enforce lead-time and advance windows. Blocked dates are detected in JS (`validateDateAgainstBlockouts`) and styled with `.wsb-date-blocked`, but the user can still attempt to submit and may only see an inline status message.
- **Time ranges:** Not respected on the marketing side. `blockouts-frontend.js` contains the logic but is unborn to the new booking-builder time inputs.
- **Vehicle-scoped blockouts:** Explicitly **not** evaluated in marketing. `blockouts.authority = "booking_site"` and `marketing_evaluates_vehicle_availability = false`.

### Proposed smallest safe marketing-side parity task
1. Re-scope `blockouts-frontend.js` selectors to the new builder **without** violating the authority boundary.
2. Wire blocked-time validation to the new text-based time pickers and native time inputs.
3. Keep `blockouts.authority` as `"booking_site"`.
4. Keep vehicle-specific blockouts on the booking site only.
5. Add a marketing-side config scaffold for time ranges if booking site provides them; otherwise remain no-op and show no misleading UI.

---

## 8. Location input section

### Google Places selection behaviour
- Autocomplete fields are initialised in `initGooglePlacesAutocomplete()`.
- Supported fields include outbound from/to, return from/to, charter pickup/dropoff, and charter-day pickup/dropoff.
- Inputs are set to `types: ['establishment', 'geocode']` with `componentRestrictions: { country: 'ZA' }`.
- On selection, the input is overwritten with the display label and a `placeSnapshots` entry is stored.

### Confirmed state
- Wrapper class `.wsb-booking-client-field--place-selected` (green border + subtle green background).
- Stale flag is cleared on focus if present.

### Stale edit behaviour
- When user types after a confirmed selection, wrapper gains `.wsb-booking-client-field--place-stale` (yellow warning style).
- Stale snapshots set `snapshot.stale = true` in memory.
- Submit blocks when `google_place_snapshots_ready` is false and `requiredForQuoteReady` is true.
- Customer-facing warning copy: *"Location was edited after selection. Please select a place again."* (must not expose internal keys).

### Clear button
- Not currently present. UX-004 should add a small clear icon/button inside or adjacent to the field wrapper.
- Clearing must wipe the input value AND the in-memory snapshot (set to empty snapshot).

### Use-current-location button
- Optional enhancement for UX-004.
- Should be a small subtle button (e.g., GPS icon + “Use current location”).
- Must populate the same snapshot path via a reverse-geocode-like flow or, if unavailable, text-only snapshot with `stale = true` and a clear message.
- Do **not** expose raw lat/lng to the user in the UI; coordinates remain in payload only.

### No place_id / lat/lng leakage in UI
- City inputs must never display place_id, lat, or lng strings.
- Debug preview panel is exempt if gated behind `?debug=1`.

### Expected tests
- Playwright smoke: select place → confirm green state → edit text → confirm warning state → re-select → confirm green state.
- Inspect preview payload for `place_snapshots` but assert that DOM textContent/aria-labels do not contain internal keys.

---

## 9. Shuttle Hire / multi-day section

### Same-day / multi-day switch
- Rendered as radio pills: `Same-day hire` and `Multi-day hire`.
- JS switches visibility of same-day panel and multi-day shell/day list.
- Must preserve form values scoped to the active mode when switching.

### One day minimum
- `Remove day` must be disabled when only one visible day remains.
- `Delete` handler enforces `visibleCards.length <= 1` guard.

### Day 1 starts expanded
- Initial render: Day 1 card is `visible=true` and `collapsed=false`.
- Days 2+ are hidden/collapsed by default.

### Add another day below the last visible card
- Toolbar button reveals the next hidden card in sequence.
- Replaces hidden card rather than creating unlimited new DOM nodes.
- Must preserve this slot-based model unless an approved redesign expands it.

### Copy this day
- Duplicates pickup/dropoff labels, POI, notes, and place snapshots into the next hidden card.
- Sets target visible and expanded.

### Remove day disabled when only one day remains
- Enforced in `updateCharterDayButtons()` and in the click handler.

### Open / Close
- Per-card toggle switches text and `aria-expanded`.
- Body visibility toggled via `wsb-booking-client-hidden`.

### Open all / Close all
- Toolbar buttons iterate visible cards and apply collapse/expand.

### Active card styling
- Day cards should have a clear active/expanded state vs collapsed state.
- Collapsed state uses dashed border style today; polish should make it more obviously interactive while keeping premium feel.

### Summary behaviour
- No live pricing summary is shown in production (preview column is `display: none`). UX-007 should ensure the Hide-revealed card treatment reads clearly even without a side panel.

---

## 10. M4B drag/drop section

Documented implementation rules only. No implementation in this phase.

### Gate dependency
- Pointer drag/drop is enabled **only** when **both** gates are `true`:
  - `enable_multi_day_charters`
  - `enable_drag_drop_itinerary_ordering`

### What moves vs what stays
- Drag/drop swaps **plan/content** between day slots.
- The following **stay fixed** with the slot:
  - `day_index`
  - `day_number`
  - `date`
  - `start_time`
  - `end_time`
  - `sort_order`

### Fallback controls
- Move plan up / Move plan down must remain the primary accessible path.
- Swap with another day control must remain available.
- Keyboard accessibility must remain (focus management, ARIA labels).

### Payload rule
- Payload reflects swapped plan content at the new slots.
- No pricing, routing, distance, duration, toll, availability, vehicle, cart, or order authority is added by the marketing side.

### Visual rule
- Drag handle must be visually distinct from expand/collapse, duplicate, and delete controls.
- Drop target/placeholder must be visually obvious.

---

## 11. Multi-trip section

Documented as future design foundation only. No full multi-trip submit implementation yet.

### Gate state
- `enable_multi_trip_bookings` remains `false` unless explicitly approved in a later phase.

### Ordering rules
- Canonical payload field is **`sort_order`** (not `display_order`).
- Reordering changes planning/display order only.
- Trip `date`/`time` stay bound to each trip.

### Future UX foundations
- Chronology warning and “Arrange chronologically” action are future UX items.
- Marketing captures intent only—no pricing/routing authority.

---

## 12. Future booking-site stepper/editing flow

### Scope
- Tracked as a separate future audit task: `BOOKING-UX-STEP-001`.
- Not part of current implementation phases UX-002 through UX-009.

### Coverage
- **Step 1 Edit booking:** Should the booking site reuse the marketing form visually, or build its own booking-side edit form?
  - Recommendation: Preserve brand consistency by standardising shared component classes where possible, but keep booking-side editing independent to avoid shortcode lifecycle and security constraints.
- **Step 2 Vehicle selection:** Booking-site-owned. Marketing passes canonical payload; booking site selects and validates vehicles.
- **Step 3 Checkout:** WooCommerce checkout remains on the booking site. Risks of embedding checkout inside a custom marketing stepper are high (session state, nonce, add-to-cart order-item meta, payment gateways).
- **Step 4 Confirmation:** Booking-site-owned summary. Marketing may influence pre-checkout review copy but not post-payment confirmation logic.

### Key questions for future task
- Whether the marketing shortcode should be reused or whether a booking-side edit form should be built.
- How edited details rerun booking-side route/pricing/vehicle selection safely.
- How WooCommerce checkout remains compatible.
- Risks of embedding checkout into a custom stepper (session loss, broken cart resumes, nonce conflicts).
- Recommendation: do not merge checkout into the marketing shortcode before the booking-site v2 intake endpoint is complete.

---

## 13. Testing plan

### Per-phase expectations

#### PHP lint
- Run `php -l` on any PHP file changed (shortcode, field registry, normaliser if touched).
- Verify no syntax errors introduced.

#### JS syntax check
- Run `node --check` or equivalent lint on any JS file changed.
- Warn-only lint is acceptable; hard errors block promotion.

#### Payload fixtures
- Run existing fixture scripts:
  - `marketing-site/app/public/wp-content/plugins/ws-bookings-client/scripts/run-booking-payload-fixtures.php`
- Ensure no fixture failures were introduced by UI changes.

#### Handover fixtures
- Run `run-booking-handover-fixtures.php` and `run-booking-handover-preview-fixtures.php` when payload structure is touched (UX-003+ require X-ray review; preferred is no schema change).

#### Playwright smoke / e2e
- Execute local browser smoke on `https://wolfshuttles.local/booking-builder/` (or configured Local URL).
- Normal mode and `?debug=1` mode.
- Interact with each changed section.
- Check for console errors, visual regressions, unreachable fields, duplicate sections.

#### Visual QA
- Screenshot or snapshot at desktop (`≥920px`) and mobile (`≤680px`, ideally `≤400px`).
- Verify rounded controls, spacing, card borders, focus indicators, and palette.

#### Internal wording scan
- Grep changed files for forbidden terms in customer-facing strings (not internals).
- Confirm debug-only copy is gated behind `wb-debug` / `?debug=1`.

#### No payload/schema change
- Any phase that needs schema change requires explicit task approval.
- Default rule: keep `schema_version: "2.0"` and existing field keys stable.

---

## 14. Final recommended execution order

### First
- **UX-002 (Copy cleanup):** Cleans the wording surface before visual rewrites. Cheap and reduces reuse risk.
- **UX-003 (Date/time picker + blockout parity):** Fixes the most behaviourally broken area and restores parity with legacy confidence before other UI work.

### Can be parallelised safely
- **UX-004 (Location polish)** can start alongside UX-003 because it touches different DOM regions and does not change date/time logic.
- **UX-005 (Return polish)** and **UX-006 (Additional stop polish)** are small CSS/label changes and can be bundled into a single small commit or a second low-risk branch after UX-002.

### Must wait
- **UX-007 (Shuttle Hire / multi-day polish):** Wait until UX-003 is stable because multi-day cards include date/time fields whose styling must be consistent.
- **UX-008 (M4B drag/drop):** Wait for UX-007 to stabilise card surfaces and for explicit SortableJS/approval. Cannot start until M4B spec gates are confirmed true.
- **UX-009 (Mobile polish):** Run as a final pass after all prior phases are merged and visually verified.

---

## Completion report

- **Branch:** N/A (spec-only, no branch created).
- **Files reviewed:** Listed in `1. Source references reviewed`.
- **Files changed:** 1 new file created — `marketing-site/app/public/wp-content/plugins/ws-bookings-client/docs/frontend-ux-001-production-booking-form-spec.md`.
- **Concept/mockup paths used:** `wolf-shuttles-ui-concepts/01-book-a-ride-form/`, `02-shuttle-hire-charter-concepts/`, `03-plan-full-booking-itinerary/`, `04-booking-site-stepper-flow/`.
- **Recommended implementation phases:** UX-002 → UX-003 → (UX-004//UX-005+UX-006) → UX-007 → UX-008 → UX-009.
- **Tests/checks run:** No runtime tests executed (docs/spec task only). PHP lint and fixture runs must be run in the implementation phases.
- **Touched outside repo:** None.
- **Remaining risks:**
  - Legacy `datepickers.js` and `blockouts-frontend.js` are not currently wired to the new builder; rewiring requires careful selector coordination to avoid double-binding.
  - jQuery UI dependency removal is implicit; confirm no other plugin path depends on it before deleting legacy snippets.
  - Feature-gate defaults for `enable_multi_day_charters` and `enable_drag_drop_itinerary_ordering` are `false` in production; any UX-007/UX-008 work must gracefully degrade when gates are off.
  - Multi-trip remains unsupported; any horizontal expansion into `itinerary.trips[]` must wait for M5A approval.
