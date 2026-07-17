# UX-004 — Location Input Visual Polish

## Branch
`feature/frontend-ux-location-input-polish`

## Goal
Make all location-related fields feel polished, premium, and client-facing while preserving Google Places state, quote-ready validation, field names, payload shape, and marketing/booking authority boundaries.

## Location Fields Covered

### Transfer (Book a Ride) Tab
- `outbound_from` — Outbound pickup location
- `outbound_to` — Outbound drop-off location
- `outbound_additional_stop` — Outbound additional stop (when enabled)
- `return_from` — Return pickup location (when return trip selected)
- `return_to` — Return drop-off location (when return trip selected)
- `return_additional_stop` — Return additional stop (when enabled)

### Shuttle Hire Tab
- `charter_pickup_location` — Same-day pickup location
- `charter_dropoff_location` — Same-day drop-off location
- `charter_day_pickup_location` — Multi-day day pickup location (per day card)
- `charter_day_dropoff_location` — Multi-day day drop-off location (per day card)

## Changes Made

### 1. Field Wrapper Polish (CSS)
- Added `.wsb-booking-client-field--location` class for location-specific styling
- Added right padding (36px) on inputs to accommodate clear button
- Premium rounded input treatment maintained with `border-radius: 10px`

### 2. Clear Button
- Added `.wsb-booking-client-place-clear` button inside location field wrappers
- Appears when field has value (controlled via `.wsb-booking-client-field--has-value`, `.wsb-booking-client-field--place-selected`, `.wsb-booking-client-field--place-stale` classes)
- Contains × symbol for clear action (implemented via SVG mask)
- Keyboard accessible with `aria-label="Clear location"`
- Clears visible value and in-memory Google Places snapshot on click
- Returns field to neutral state

### 3. Confirmed Location State
- Wrapper class: `.wsb-booking-client-field--place-selected`
- Visual: Green border (`var(--success)`) + subtle green background (`var(--success-trans-05)`)
- Copy remains clean — no place_id/lat/lng displayed in UI

### 4. Stale Edit State
- Wrapper class: `.wsb-booking-client-field--place-stale`
- Visual: Amber/yellow border (`var(--warning)`) + subtle background (`var(--warning-trans-05)`)
- Customer-facing message: "Location was edited after selection. Please select a place again."
- Warning icon with message shown via `.wsb-booking-client-place-stale-message`
- Quote-ready validation still blocks stale/unconfirmed Places when required

### 5. Current-Location Button
- **Not implemented** — No reliable geolocation/geocode API integration present
- Documented as future enhancement if requirements include GPS-based location selection
- Never expose raw coordinates to customers

### 6. Accessibility / Mobile
- Clear button reachable via keyboard (tab navigation)
- Focus indicator: outline + background change on hover/focus
- No input overlap with clear button (36px right padding)
- No horizontal scroll on mobile (full-width inputs)
- Visible focus states for all interactive elements

## Files Modified

### PHP
- `inc/class-booking-client-form-shortcode.php`
  - `render_text_field()` — Added location field detection and clear button markup with `--location` wrapper class
  - `render_additional_stop_field()` — Added location wrapper and clear button markup

### CSS
- `assets/css/booking-client-form.css`
  - Added `.wsb-booking-client-field--location` styles with `padding-right: 36px`
  - Added `.wsb-booking-client-place-clear` styles with SVG × icon
  - Added `.wsb-booking-client-place-stale-message` styles with warning icon
  - Updated confirmed/stale state styling for location fields

### JavaScript
- `assets/js/booking-client-form.js`
  - Added `updateLocationFieldState()` — Toggles has-value class on wrapper
  - Added `clearLocationField()` — Clears input value and removes state classes
  - Added `initClearButtons()` — Binds click handlers to clear buttons using `[data-wsb-place-clear]` selector
  - Added `outbound_additional_stop` and `return_additional_stop` to autocompleteFields array
  - Added `initClearButtons()` call in early return for non-Google Places environments

## Tests/Checks Run
- `php -l inc/class-booking-client-form-shortcode.php` — OK
- `node --check assets/js/booking-client-form.js` — OK
- `curl https://wolfshuttles.local/booking-builder/?debug=1` — HTTP 200
- Verified `wsb-booking-client-field--location` present on location fields
- Verified `place-clear` button markup rendered

## Browser Result
Tested on `/booking-builder/` and `/booking-builder/?debug=1`:
- Location fields display with clear button when empty
- Clear button appears when field has value
- Selecting from Google Places autocomplete applies green confirmed state
- Editing confirmed location triggers amber stale state with warning message
- Clearing location removes both value and snapshot, returns to neutral state
- No place_id/lat/lng visible in customer-facing UI
- Additional stop autocomplete works correctly

## Current-Location Decision
**Not implemented.** The current-location button was not added because:
1. No geolocation reverse-geocode flow is wired
2. Adding a button without reliable backend support would create a broken experience
3. Future enhancement: If GPS/Geolocation is required, implement full flow to populate place snapshot without exposing raw coordinates to customer

## Touched Outside Repo
None. All changes within `ws-bookings-client` plugin.

## Remaining Risks
1. CSS variable availability (`--warning`, `--success`) depends on theme implementation
2. Screen reader announcement of stale message may need aria-live refinement