# PW Browser Selector & Accessibility Inventory (QA-A)

## Purpose and Scope

This document is **documentation only**. It records stable Playwright selectors for the current
`ws_booking_client_form` shortcode (Book a Ride / Shuttle Hire booking builder) and lists the
accessibility checks still needed. It does not modify tests, runtime PHP/JS, fixtures, DB schema,
REST endpoints, WooCommerce/session/cart/order logic, pricing, blockouts, or vehicle availability.

It is the companion selector inventory to `qa-a-local-browser-form-testing-plan.md` and reflects the
DOM produced by `class-booking-client-form-shortcode.php` after M3B / M3B.1.

---

## 1. Stable Selector Strategy

### 1.1 Preferred selectors (in priority order)

1. **Form/section-scoped data-attribute containers** — stable, gate-independent, and already used by the
   existing spec (`qa-a-browser.spec.ts`):
   - `[data-wsb-booking-builder]` — outermost shell
   - `[data-wsb-booking-form]` — the `<form>`
   - `[data-wsb-transfer-fields]` — Book a Ride card
   - `[data-wsb-outbound-section]` / `[data-wsb-return-section]` / `[data-wsb-charter-section]`
   - `[data-wsb-outbound-additional-stop-section]` / `[data-wsb-return-additional-stop-section]`

2. **`name=` attributes for inputs** — the stable semantic field key is preserved in `name` (M3B.1 kept
   field keys unchanged). Use `input[name="..."]` when scoping to a section, e.g.
   `outboundSection.locator('input[name="outbound_additional_stop_enabled"]')`.

3. **`data-ws-field-key` attributes** — identical to `name` value; safe for field-level assertions,
   e.g. `input[data-ws-field-key="passengers"]`.

4. **Context-prefixed DOM `id`** (`wsb-<context>-<field-key>`, M3B.1) — unique per page, so `#id` is safe
   for single-field targeting. Examples: `#wsb-book-a-ride-passengers`,
   `#wsb-book-a-ride-outbound-from`, `#wsb-shuttle-hire-charter-pickup-location`.

5. **`getByLabel()` text** — already used by the spec for number fields; only valid because every
   rendered label has a matching `for` after M3B.1. Prefer scoping with `.locator(...).getByLabel()` so
   the same visible label in Book a Ride vs Shuttle Hire does not collide.

6. **`getByRole()`** — for tab buttons (`role=tab` via `data-wsb-service-tab`) and checkboxes by
   accessible name, e.g. `getByRole('checkbox', { name: 'Trailer required' })`.

### 1.2 Selectors to avoid

- **Bare semantic IDs that were de-duplicated in M3B.1** — do NOT use `#passengers`, `#baby_seats`,
  `#check_in_bags`, `#carry_on_bags`, `#outbound_pickup_date`. They were duplicate IDs before M3B.1 and
  are no longer the rendered IDs. Always use the `wsb-` prefixed ID.
- **Bare `name=` without section scope for shared keys** — `passengers`, `baby_seats`, `check_in_bags`,
  `carry_on_bags`, `trailer`, `oversize_luggage` appear in BOTH Book a Ride and Shuttle Hire. A
  page-level `input[name="passengers"]` matches 2 elements. Always scope to a section/context.
- **`getByText` / visible label text for structural assertions** — labels are translated strings and can
  change; prefer data attributes or IDs.
- **Feature-gate attributes (`data-ws-feature-gate`, `data-wsb-feature-gate`) as primary selectors** —
  see 4.2.

### 1.3 Form / card scoping rules

- Anchor every interaction to `[data-wsb-booking-builder]` first, then narrow to the active section.
- When targeting a shared field key (passengers, bags, trailer, oversize), scope to
  `[data-wsb-transfer-fields]` (Book a Ride) or `[data-wsb-charter-section]` (Shuttle Hire) before the
  input locator.
- Hidden sections (`[data-wsb-return-section]`, `[data-wsb-charter-section]`, additional-stop sections)
  use `wsb-booking-client-hidden`. Assert visibility/hidden state rather than assuming presence.
- Trip-type radio (`name="trip_type"`) controls return-section visibility; toggle it before asserting
  return fields.

### 1.4 Google Places field selectors

Google Places auto-binds (`initGooglePlacesAutocomplete`) to these inputs by `name` (stable):

| Field | Input selector | DOM `id` |
|---|---|---|
| Outbound from (origin) | `input[name="outbound_from"]` | `#wsb-book-a-ride-outbound-from` |
| Outbound to (destination) | `input[name="outbound_to"]` | `#wsb-book-a-ride-outbound-to` |
| Return from | `input[name="return_from"]` | `#wsb-book-a-ride-return-from` |
| Return to | `input[name="return_to"]` | `#wsb-book-a-ride-return-to` |
| Charter pickup | `input[name="charter_pickup_location"]` | `#wsb-shuttle-hire-charter-pickup-location` |
| Charter drop-off | `input[name="charter_dropoff_location"]` | `#wsb-shuttle-hire-charter-dropoff-location` |

Notes:
- The widget sets `data-ws-route-role` and `data-ws-place-role` dynamically on these inputs; you may
  assert `input[data-ws-place-role="origin"]` etc., but the value is JS-injected so only assert after
  the form/JS has initialized.
- Selection state is reflected by wrapper classes: `wsb-booking-client-field--place-selected` and
  `wsb-booking-client-field--place-stale`. Use `.wsb-booking-client-field--place-selected input` to
  confirm a successful dropdown selection.

### 1.5 Additional stop selectors

| Element | Selector | Notes |
|---|---|---|
| Outbound toggle | `[data-wsb-outbound-additional-stop-toggle]` (checkbox `name="outbound_additional_stop_enabled"`) | also carries `data-ws-feature-gate="enable_additional_stops"` |
| Outbound stop section | `[data-wsb-outbound-additional-stop-section]` | hidden + input `disabled` when off |
| Outbound stop input | `input[name="outbound_additional_stop"]` (`#wsb-book-a-ride-outbound-additional-stop`) | `disabled` by default |
| Return toggle | `[data-wsb-return-additional-stop-toggle]` (checkbox `name="return_additional_stop_enabled"`) | |
| Return stop section | `[data-wsb-return-additional-stop-section]` | |
| Return stop input | `input[name="return_additional_stop"]` | |

### 1.6 Shuttle Hire selectors

Charter tab is `button[data-wsb-service-tab="charter"]`. After clicking it,
`[data-wsb-charter-section]` becomes visible and `[data-wsb-transfer-fields]` /
`[data-wsb-outbound-section]` / `[data-wsb-return-section]` are hidden.

| Field | Input selector | DOM `id` |
|---|---|---|
| Passengers | scoped `input[name="passengers"]` | `#wsb-shuttle-hire-passengers` |
| Baby seats | scoped `input[name="baby_seats"]` | `#wsb-shuttle-hire-baby-seats` |
| Check-in bags | scoped `input[name="check_in_bags"]` | `#wsb-shuttle-hire-check-in-bags` |
| Carry-on bags | scoped `input[name="carry_on_bags"]` | `#wsb-shuttle-hire-carry-on-bags` |
| Pickup location | `input[name="charter_pickup_location"]` | `#wsb-shuttle-hire-charter-pickup-location` |
| Drop-off location | `input[name="charter_dropoff_location"]` | `#wsb-shuttle-hire-charter-dropoff-location` |
| Pickup date | `input[name="outbound_pickup_date"]` | `#wsb-shuttle-hire-outbound-pickup-date` |
| Pickup time | `input[name="charter_pickup_time"]` | `#wsb-shuttle-hire-charter-pickup-time` |
| Drop-off time | `input[name="charter_dropoff_time"]` | `#wsb-shuttle-hire-charter-dropoff-time` |
| Trailer | `getByRole('checkbox', { name: 'Trailer required' })` (name `trailer`) | |
| Oversize luggage | `getByRole('checkbox', { name: 'Oversize luggage' })` (name `oversize_luggage`) | |

---

## 2. Field Selector Map

All inputs below exist for Book a Ride unless marked Charter. Use scoped locators per section 1.3.

### 2.1 Passengers
- `transferFields.getByLabel('Passengers')` → `input[name="passengers"]` (`#wsb-book-a-ride-passengers`)
- Charter: `charterSection.locator('input[name="passengers"]')` (`#wsb-shuttle-hire-passengers`)

### 2.2 Baby seats
- `transferFields.getByLabel('Baby seats')` → `input[name="baby_seats"]` (`#wsb-book-a-ride-baby-seats`)
- Charter: `#wsb-shuttle-hire-baby-seats`

### 2.3 Luggage fields
- Check-in bags: `transferFields.getByLabel('Check-in bags')` → `input[name="check_in_bags"]`
  (`#wsb-book-a-ride-check-in-bags`)
- Carry-on bags: `transferFields.getByLabel('Carry-on bags')` → `input[name="carry_on_bags"]`
  (`#wsb-book-a-ride-carry-on-bags`)
- Charter equivalents scoped to `#wsb-shuttle-hire-*` IDs.

### 2.4 Pickup / drop-off
- Outbound from: `#wsb-book-a-ride-outbound-from` (`input[name="outbound_from"]`)
- Outbound to: `#wsb-book-a-ride-outbound-to` (`input[name="outbound_to"]`)
- Return from: `#wsb-book-a-ride-return-from` (`input[name="return_from"]`)
- Return to: `#wsb-book-a-ride-return-to` (`input[name="return_to"]`)
- Charter pickup: `#wsb-shuttle-hire-charter-pickup-location` (`input[name="charter_pickup_location"]`)
- Charter drop-off: `#wsb-shuttle-hire-charter-dropoff-location`
  (`input[name="charter_dropoff_location"]`)

### 2.5 Pickup / drop-off date & time
- Outbound date: `#wsb-book-a-ride-outbound-pickup-date` (`input[name="outbound_pickup_date"]`)
- Outbound time: `#wsb-book-a-ride-outbound-pickup-time` (`input[name="outbound_pickup_time"]`)
- Return date: `#wsb-book-a-ride-return-pickup-date` (`input[name="return_pickup_date"]`)
- Return time: `#wsb-book-a-ride-return-pickup-time` (`input[name="return_pickup_time"]`)
- Charter date: `#wsb-shuttle-hire-outbound-pickup-date` (`input[name="outbound_pickup_date"]`,
  shared key — must scope to charter section)
- Charter pickup time: `#wsb-shuttle-hire-charter-pickup-time` (`input[name="charter_pickup_time"]`)
- Charter drop-off time: `#wsb-shuttle-hire-charter-dropoff-time` (`input[name="charter_dropoff_time"]`)

### 2.6 Return fields
Gated by `name="trip_type"` radio value `return` → reveal `[data-wsb-return-section]`.
Return from/to/date/time listed in 2.4 / 2.5. Return stop toggle + section in 1.5.

### 2.7 Additional stops
See 1.5. Toggle checkboxes: `outbound_additional_stop_enabled`, `return_additional_stop_enabled`.
Stop text inputs: `outbound_additional_stop`, `return_additional_stop` (name = field key, see
`render_additional_stop_field`).

### 2.8 Charter fields
See 1.6. Plus trailer / oversize checkboxes (section 2.10) and the unused-but-registered
`charter_poi` / `charter_notes` / `charter_additional_stop` keys (not in current shortcode render).

### 2.9 Trailer / oversize
- Transfer and Charter both render `name="trailer"` and `name="oversize_luggage"` inside their
  respective cards. They share the visible label text "Trailer required" / "Oversize luggage".
- Prefer `getByRole('checkbox', { name: 'Trailer required' })` scoped to the active section, or
  `section.locator('input[name="trailer"]')`.

### 2.10 Submit / preview buttons
- Submit/preview: `button[data-wsb-preview-submit]` (label "Check Pricing & Availability").
- Validation output: `[data-wsb-validation-output]` (`aria-live="polite"`).
- Submit message: `[data-wsb-submit-message]` (`aria-live="polite"`).
- Payload preview: `[data-wsb-payload-preview]`.
- Service tabs: `[data-wsb-service-tab="transfer"]` / `[data-wsb-service-tab="charter"]`.

---

## 3. Accessibility Inventory

### 3.1 Label / `for` status — PASS (post M3B.1)
Every rendered field uses `<label class="wsb-form__label" for="<dom_id>">` with a matching input
`id="<dom_id>"`. The M3B.1 smoke asserts every label `for` points to an existing input `id`. Shared
keys are disambiguated by context prefix, and `getByLabel()` resolves uniquely when scoped per section.

Open checks (still worth an automated assertion):
- `getByLabel()` resolves to exactly one input inside each section for the four shared number fields.

### 3.2 Duplicate ID status after M3B.1 — RESOLVED
M3B.1 removed the pre-existing duplicate IDs (`passengers`, `baby_seats`, `check_in_bags`,
`carry_on_bags`, `outbound_pickup_date`). Rendered IDs are now `wsb-<context>-<field-key>` and unique.
The form-semantics smoke asserts no duplicate IDs remain in the rendered shortcode.

Guardrail to keep it that way: assert `expect(page.locator('#wsb-book-a-ride-passengers')).toHaveCount(1)`
and the same for each known ID, so a future regression that re-introduces a duplicate ID fails fast.

### 3.3 Keyboard tab order checks needed
- [ ] Tab order follows visual/card order: Book a Ride card → outbound → (return when visible) → actions.
- [ ] Shuttle Hire tab keystroke reveals charter section and the tab sequence updates (hidden transfer
      fields are removed from the tab order via `hidden` class, not just `display`).
- [ ] Trip-type radio (`name="trip_type"`) is keyboard reachable and arrow-key selectable.
- [ ] Additional-stop toggles are reachable and toggle via Space; revealing the stop input inserts it
      into tab order.
- [ ] Google Places inputs remain in tab order and the autocomplete suggestions are reachable by
      arrow keys (see 3.6).

### 3.4 Visible focus checks needed
- [ ] Each input, checkbox, radio, tab button, and the submit button shows a visible focus indicator.
- [ ] The dynamic AM/PM badge and date/time wrappers do not swallow focus.
- [ ] Focus ring is not clipped by `overflow:hidden` card containers.

### 3.5 Error message association checks needed
- [ ] `[data-wsb-validation-output]` (`aria-live="polite"`) announces server/client validation text
      (e.g. "Please select the address from the dropdown").
- [ ] Field-level errors (future) are associated via `aria-describedby` / `aria-invalid` so screen
      readers map the message to the right input.
- [ ] When the Google Places gate blocks submit, focus/announcement lands on the offending location
      field or its error, not silently dropped.
- [ ] `[data-wsb-submit-message]` announcements do not conflict with validation output.

### 3.6 Google Places dropdown keyboard checks needed
- [ ] Typing in a place field opens the dropdown without a mouse.
- [ ] Arrow keys move through predictions; Enter selects; Escape closes.
- [ ] After selection, the input shows the selected label and wrapper gains
      `wsb-booking-client-field--place-selected`.
- [ ] Manual edit after selection flips wrapper to `wsb-booking-client-field--place-stale` and the
      stale copy is announced; re-selecting clears stale state.
- [ ] When no selection is made, submit is blocked and the address-dropdown prompt is announced.
- [ ] `aria-autocomplete` / role semantics of the Google widget are preserved (Google owns the widget
      DOM; only assert the input remains labelled and operable).

### 3.7 Additional stop toggle `aria` checks needed
- [ ] Toggle checkboxes have an associated visible label ("Enable additional stop") via the wrapping
      `<label>` (already present) and are announced as checkboxes.
- [ ] When the `enable_additional_stops` gate is off, the toggle label wrapper receives
      `wsb-booking-client-hidden` and is removed from the a11y tree; the stop input is `disabled`.
- [ ] When the gate is on and the toggle is checked, the stop section becomes visible and the input is
      enabled and added to the a11y tree.
- [ ] The stop input, when enabled, carries `data-ws-place-role="stop"` and participates in the Google
      Places keyboard checks above.

---

## 4. Playwright Recommendations

### 4.1 Use locator scoping with form containers
Always anchor to a stable container, then descend:
```ts
const builder = page.locator('[data-wsb-booking-builder]');
const transfer = builder.locator('[data-wsb-transfer-fields]');
const passengers = transfer.getByLabel('Passengers');
```
This prevents the Book-a-Ride vs Shuttle-Hire duplicate of shared keys from matching two elements.

### 4.2 Avoid broad feature-gate selectors
Do NOT use `data-ws-feature-gate` / `data-wsb-feature-gate` as primary locators. Gate attributes only
appear on the additional-stop toggle + section and reflect runtime config that can change between
environments. Select by stable `data-wsb-*` section markers and `name`/ID instead, then assert the
gate-driven visibility/hidden state as a separate expectation.

### 4.3 Avoid visible text when data attributes exist
Prefer `input[name="outbound_from"]` / `#wsb-book-a-ride-outbound-from` over
`getByText('Pick up from')`. Labels are translatable; data attributes and IDs are stable.

### 4.4 Use `.first()` only when justified and documented
`.first()` is unnecessary for the current form because M3B.1 guarantees unique IDs and scoped
`getByLabel()` resolves to one element. Only use `.first()` if a future change reintroduces duplicate
matches, and then document why (e.g. "two charter stop inputs during multi-day preview").

### 4.5 Use `expect(locator).toHaveCount()` for guardrails
Add count guardrails to catch regressions early:
```ts
await expect(page.locator('#wsb-book-a-ride-passengers')).toHaveCount(1);
await expect(builder.locator('[data-wsb-service-tab]')).toHaveCount(2);
await expect(transfer.locator('input[name="passengers"]')).toHaveCount(1);
```
This is the automated backstop for the M3B.1 "no duplicate IDs" and "label-for resolves" guarantees.

---

## Final Report

1. **Files reviewed**
   - `tests/e2e/wolf-shuttles/qa-a-browser.spec.ts`
   - `inc/class-booking-client-form-shortcode.php`
   - `inc/class-booking-field-registry.php`
   - `assets/js/booking-client-form.js`
   - `docs/m3b-form-field-semantics-and-gated-scaffolding.md`
   - `docs/m3b-1-form-html-id-hygiene.md`
   - `docs/qa-a-local-browser-form-testing-plan.md`
   - `docs/hlp-d-contextual-help-content-map.md`

2. **Files changed**
   - Created `docs/pw-browser-selector-a11y-inventory.md` (this file). No other files modified.

3. **Selector inventory created**
   - Stable strategy (preferred selectors, selectors to avoid, scoping rules, Google Places, additional
     stop, Shuttle Hire).
   - Field selector map covering passengers, baby seats, luggage, pickup/drop-off, date/time, return,
     additional stops, charter, trailer/oversize, and submit/preview.
   - Accessibility inventory with status + open checks for label/for, duplicate IDs, tab order, focus,
     error association, Google Places keyboard, and additional-stop aria.
   - Playwright recommendations (scoping, avoid gate selectors, avoid text, `.first()` discipline,
     `toHaveCount` guardrails).

4. **Accessibility checks listed**
   - Keyboard tab order (3.3), visible focus (3.4), error message association (3.5), Google Places
     dropdown keyboard (3.6), additional-stop toggle aria (3.7). Label/for (3.1) and duplicate-ID (3.2)
     status confirmed PASS after M3B.1 but recommended as automated guardrails.

5. **Confirmation no runtime/test changes**
   - Documentation only. No tests, runtime PHP/JS, fixtures, DB schema, REST endpoints, WooCommerce/
     session/cart/order logic, pricing, blockouts, or vehicle availability were modified.

---

*Document version: QA-A SEL-INV v1.0 | Last updated: 2026-07-06*
