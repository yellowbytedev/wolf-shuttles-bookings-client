# UX-003A - Booking Form Controls Parity Fix

**Branch:** `feature/frontend-ux-form-controls-parity`  
**Plugin:** `ws-bookings-client`  
**Scope:** Marketing plugin only. No payload schema, field-name, REST contract, route authority, pricing authority, WooCommerce, cart, checkout, or booking-site code changes.

---

## 1. Objective

Restore control parity for the marketing booking builder by fixing three remaining UI control issues:

1. Ensure date picker opens naturally on click/focus for all date fields
2. Ensure Google Places autocomplete initializes on all location inputs including additional stops
3. Convert passenger/luggage quantity controls from browser number steppers to select dropdowns

---

## 2. Files reviewed

- `assets/js/booking-client-form.js`
- `assets/js/datepickers.js`
- `assets/js/blockouts-frontend.js`
- `assets/css/booking-client-form.css`
- `inc/class-booking-client-form-shortcode.php`
- `inc/class-booking-field-registry.php`
- `docs/ux-003-date-time-blockout-parity.md`
- `docs/legacy-form-controls-audit.md`
- `docs/google-places-quote-ready-handoff.md`

---

## 3. Changes made

### 3.1 Date picker selector enhancement

**File:** `assets/js/datepickers.js`

- Extended the date selector to include charter day-card dates: `input[data-wsb-charter-day-field="date"]`
- Added `initNativeDatePickers()` function to ensure native date inputs open on click/focus
- Added retry logic for dynamically added date inputs

### 3.2 Google Places autocomplete expansion

**File:** `assets/js/booking-client-form.js`

- Added `outbound_additional_stop` to `autocompleteFields` array with snapshot key and route role
- Added `return_additional_stop` to `autocompleteFields` array with snapshot key and route role
- Both fields now properly initialize Google Places autocomplete when enabled
- Snapshot keys follow the existing pattern: `outbound_additional_stop`, `return_additional_stop`

### 3.3 Passenger/luggage dropdown conversion

**File:** `inc/class-booking-field-registry.php`

- Changed `passengers` type from `number` to `select`
- Changed `baby_seats` type from `number` to `select`
- Changed `check_in_bags` type from `number` to `select`
- Changed `carry_on_bags` type from `number` to `select`
- Added `options` array to each field containing range of valid values

**File:** `inc/class-booking-client-form-shortcode.php`

- Replaced `render_number_field()` with `render_select_field()` method
- Updated field rendering calls to use select instead of number inputs

**File:** `assets/css/booking-client-form.css`

- Added select styling to match existing input styling
- Added dropdown arrow SVG icon for visual consistency
- Added focus/hover states for select elements

---

## 4. Datepicker result

- **Decision:** Native date input retained (no jQuery UI datepicker rewired onto booking builder)
- **Fields covered:**
  - Book a Ride pickup date (`outbound_pickup_date`)
  - Return pickup date (`return_pickup_date`)
  - Shuttle Hire single-day date (`outbound_pickup_date` in charter context)
  - Multi-day charter day-card dates (`input[data-wsb-charter-day-field="date"]`)

---

## 5. Timepicker regression result

- Timepicker/AM-PM badge behavior from UX-003 preserved
- `initClockTimePicker()` in `booking-client-form.js` remains intact
- `updateAmPmLabels()` continues to work with select fields (time inputs unchanged)
- No changes to time-related JavaScript required

---

## 6. Autocomplete fields covered

| Field | Snapshot Key | Route Role |
|-------|--------------|------------|
| Outbound pickup (`outbound_from`) | `outbound_from` | `origin` |
| Outbound drop-off (`outbound_to`) | `outbound_to` | `destination` |
| Outbound additional stop (`outbound_additional_stop`) | `outbound_additional_stop` | `stop` |
| Return pickup (`return_from`) | `return_from` | `return_origin` |
| Return drop-off (`return_to`) | `return_to` | `return_destination` |
| Return additional stop (`return_additional_stop`) | `return_additional_stop` | `stop` |
| Charter pickup (`charter_pickup_location`) | `charter_pickup_location` | `charter_origin` |
| Charter drop-off (`charter_dropoff_location`) | `charter_dropoff_location` | `charter_destination` |
| Charter day pickup (dynamic) | via `getCharterDaySnapshotKey()` | `charter_day_origin` |
| Charter day drop-off (dynamic) | via `getCharterDaySnapshotKey()` | `charter_day_destination` |

---

## 7. Dropdown fields changed

| Field | Type Change | Options |
|-------|-------------|---------|
| `passengers` | `number` → `select` | 1 to max_passengers (default 13) |
| `baby_seats` | `number` → `select` | 0 to max_passengers (default 13) |
| `check_in_bags` | `number` → `select` | 0 to max_check_in_bags (default 13) |
| `carry_on_bags` | `number` → `select` | 0 to max_check_in_bags (default 13) |

---

## 8. Blockout parity result

- No changes to blockout logic
- Existing `blockouts-frontend.js` continues to handle blocked date/time enforcement
- Blockout validation remains with booking-site authority (`blockouts.authority = "booking_site"`)

---

## 9. Tests/checks run

- `php -l inc/class-booking-client-form-shortcode.php` - Passed
- `php -l inc/class-booking-field-registry.php` - Passed
- `node --check assets/js/booking-client-form.js` - Passed
- `node --check assets/js/datepickers.js` - Passed
- `node --check assets/js/blockouts-frontend.js` - Passed

---

## 10. Remaining risks

- Select dropdown values are strings in HTML but parsed as integers in `getNumberValue()` - verified to work correctly
- Browser compatibility for native date picker on mobile devices depends on browser implementation
- Google Places autocomplete on additional stop fields requires `enable_additional_stops` feature gate to be enabled
- No live browser confirmation was possible in this environment

---

## 11. Scope reminders

This task did NOT include:
- General location polish (UX-004)
- Shuttle Hire card redesign (UX-007)
- Drag/drop implementation (UX-008)
- Changes to booking-site code
- Changes to payload schema or REST contracts