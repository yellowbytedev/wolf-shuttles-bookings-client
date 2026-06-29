# Legacy Form Controls Audit

## Purpose

Audit of legacy booking form controls and configuration to inform v2 Booking Builder implementation. Source: `inc/legacy-snippets/` directory.

## Files Inspected

| File | Type | Status |
|------|------|--------|
| `php/8-add-jquery.php` | PHP | Active (conditional) |
| `php/2-test-2.php` | PHP | Active (conditional) |
| `archive/3-custom-date-picker-on.php` | JS draft | Archived |
| `archive/15-initialise-elements-and-variables.php` | JS draft | Archived |
| `js/5-calculate-distance-v2.js` | JS | Active (conditional) |
| `php/19-bricks-builder-custom.php` | PHP | Active |
| `php/15-submit-booking-form-and.php` | PHP | Active |
| `php/10-helper-functions.php` | PHP | Active |
| `php/7-api-call-to-google.php` | PHP | Active |

---

## 1. Date Picker

### Which legacy files initialise it?

- **Active:** `php/8-add-jquery.php` — Enqueues `jquery-ui-datepicker` + jQuery UI CSS
- **Archived:** `archive/3-custom-date-picker-on.php` — Initialises datepickers on inputs matching `input[placeholder="Select Date"]`, `input[name="pickup_date"]`, `input[name="charter_pickup_date"]`

### What min date / lead time rules exist?

- **Min date:** `minDate: 0` (today allowed) in archived snippet
- **Default date:** Tomorrow (set via `setDate(tmr.getDate() + 1)`)
- **Date format:** `dd/mm/yy`
- **Lead time validation:** `php/15-submit-booking-form-and.php`
  - Charter: 48 hours minimum (`validate_time_gap` sets `required_duration = 48 * HOUR_IN_SECONDS`)
  - General transfer: 5 hours (between 04:00-21:00) or 12 hours (outside that window)

### What blockout rules exist?

- **Blocked dates map:** Hard-coded dates in archived snippet:
  ```js
  const BLOCKED_DATES = new Map([
    ["07/06/2025", "Holiday – no service"],
    ["19/10/2025", "Fully booked"],
  ]);
  ```
- **Server-side blockouts:** `php/15-submit-booking-form-and.php` uses `wsb_client_blockouts_store()` and `wsb_client_is_blocked()` to validate dates/times against cached blockouts from booking site

### Which rules are global vs vehicle-specific?

- **Global blockouts** (managed via `wsb_client_blockouts_store`):
  - Full-day blockouts (`days` array)
  - Time-range blockouts (checked via `wsb_client_is_blocked`)
- **Vehicle-specific blockouts:** Not evaluated on marketing site; `blockouts.authority` is always `"booking_site"`

### Current source of each value

- **Hard-coded:** Date format (`dd/mm/yy`), blocked dates map, thresholds (80km pickup, 30km dropoff)
- **SCF Options:** `max_passengers` from company-profile options page (`php/19-bricks-builder-custom.php`)

### Recommended new plugin service/class

- **Class:** `WSB_Client_Booking_External_Services` already has scaffold for no-op external calls
- **Add:** `get_blockouts_scaffold()` returning diagnostic structure
- **Future:** Server-side `wsb_client_is_blocked()` already exists; use for time-range validation

---

## 2. Time Picker

### Which legacy files initialise it?

- **Active:** `php/2-test-2.php` — Enqueues `jquery-clock-timepicker.min.js` (theme path: `/assets/js/jquery-clock-timepicker.min.js`)
- **Settings:** Precision 5 minutes (`precision: 5`), AM/PM label injected as sibling

### What time step / interval exists?

- **Step:** 5 minutes (`precision: 5`)
- **Default times:**
  - General: Tomorrow's current time
  - Charter pickup: 08:00 (with commented minimum 07:00, maximum 19:00)
  - Charter dropoff: 17:00 (with commented minimum 11:00, maximum 23:00)

### What disabled time ranges exist?

- **None actively enforced** — Legacy snippet has charter time constraints commented out
- **Lead time validation** enforces minimum booking window (5/12 hours)

### How are return times handled?

- **Separate field:** `return_pickup_time` input
- **Same picker settings** as outbound via shared selector `input[name='pickup_time'], input[placeholder='Select time']`

### Current source of each value

- **Hard-coded:** Time step (5min), charter defaults (08:00/17:00), AM/PM label styling

### Recommended new plugin service/class

- **JS:** Keep native `<input type="time">` (browser handles step via `step="300"` or form config)
- **Future:** Server-provided config for charter time windows via `WSB_Client_Booking_External_Services`

---

## 3. Capacity / Company Profile

### Where are max passengers pulled from?

- `php/19-bricks-builder-custom.php` — `ws_get_max_passengers_value()`
  - **Source:** Smart Custom Fields (SCF) options page `company-profile` → field `max_passengers`
  - **Fallback:** ACF `get_field('max_passengers', 'option')`
  - **Hard default:** 13

### Where are luggage / baby-seat limits pulled from?

- **No separate limits found** — Same `max_passengers` value used for luggage options via `ws_luggage_options()`

### Source of each value

| Config | Source | Current Default |
|--------|--------|-----------------|
| `max_passengers` | SCF `company-profile` → `max_passengers` | 13 |
| `large_bags` | Form field only (no limit) | 0 (min) |
| `carry_on_bags` | Form field only (no limit) | 0 (min) |
| `baby_seats` | Form field only (no limit) | 0 (min) |
| `trailer` | Form field (checkbox) | false |
| `oversize_luggage` | Form field (checkbox) | false |

### Recommended new plugin service/class

- **Class:** Add to `BookingFieldRegistry` or create `WSB_Client_Field_Config`
- **Fields to expose:** `max_passengers`, future charter time windows, step intervals

---

## 4. Legacy JS Business Logic

### Pure UI / input handling (safe to port)

| Function | Purpose | Location |
|----------|---------|----------|
| `debounce()` | Prevent excessive API calls | `archive/15-initialise-elements-and-variables.php` |
| `updateState()` | Centralized state object | `archive/15-initialise-elements-and-variables.php` |
| `setAirportDistances()` | Map distances to leg structure | `js/5-calculate-distance-v2.js` |
| `displayTravelData()` | Populate form display fields | `archive/15-initialise-elements-and-variables.php`, `js/5-calculate-distance-v2.js` |
| `attachAutoComplete()` | Google Places attachment | `js/5-calculate-distance-v2.js` |

### Route / API related (booking-site owned)

| Function | Purpose | Location |
|----------|---------|----------|
| `calculateDistanceBetweenLocations()` | Google Distance Matrix calls | Both JS files |
| `validateTripDistances()` | Route validation (zone/threshold) | Both JS files |
| `checkTollGates()` | HERE API toll lookup | `js/5-calculate-distance-v2.js` |
| `classifyDirectionRelativeToHQ()` | Bearing/angle math for direction classification | `js/5-calculate-distance-v2.js` |
| `decideEmptyLegs()` | Dispatch/return fee logic | `js/5-calculate-distance-v2.js` |

### Pricing / business logic (booking-site owned)

| Logic | Location |
|-------|----------|
| Charter code derivation (`determine_charter_codes`) | `php/15-submit-booking-form-and.php` |
| POI distance mapping | `php/15-submit-booking-form-and.php` |
| Duration-based package selection | `php/15-submit-booking-form-and.php` |
| Wedding tier classification | `php/15-submit-booking-form-and.php` |

### What is safe to port now

- **State management pattern** — Replicate `state` object structure in new JS
- **AM/PM label injection** — For time picker UX
- **Field population** — From payload to form (already in `applyFixtureToForm`)
- **Debounce helper** — Already in `booking-client-form.js`

---

## 5. Direction / Angle Logic

### Files / functions involved

- `js/5-calculate-distance-v2.js`:
  - `initialBearing(lat1, lon1, lat2, lon2)` — Initial bearing calculation
  - `smallestAngleDiff(a, b)` — Smallest angle between bearings
  - `haversineKm(lat1, lon1, lat2, lon2)` — Distance calculation
  - `classifyDirectionRelativeToHQ(origin, destination)` — Returns `toward` \| `away` \| `lateral` \| `neutral`
  - `decideEmptyLegs()` — Uses direction to classify dispatch/return fees

### Reference points

```js
PRICING_RULES.hq = {
  lat: -33.9696,
  lng: 18.5978,
  placeId: "ChIJvQtA90JFzB0RkF7P43l1SEA"  // CTIA
}

PRICING_RULES.thresholds = {
  pickup_far_threshold_km: 80,
  dropoff_near_threshold_km: 30,
  direction: {
    toward_max_deg: 60,
    away_min_deg: 120,
    radial_min_km: 10,
    angular_eps_deg: 0.25
  }
}
```

### Direction classification logic

1. Calculate bearing from origin → destination (`o2d`)
2. Calculate bearing from origin → HQ (`o2h`)
3. Compute smallest angle difference
4. If angle ≤ 60° AND radial change toward HQ ≥ 10km → `toward`
5. If angle ≥ 120° AND radial change away from HQ ≥ 10km → `away`
6. Otherwise → `lateral` or `neutral`

### Marketing-side vs booking-side

- **Currently:** Marketing site performs all calculations (JS)
- **Should migrate to:** PHP (for v2, booking site is authoritative)
- **Purpose:** Dispatch/return fee decisions, zone validation
- **Not critical for Phase 2:** Can remain as no-op scaffold

---

## 6. Hidden / Bricks Field IDs

### Legacy field mappings (for reference only, DO NOT adopt)

| Semantic Name | Bricks field ID (legacy) |
|---------------|------------------------|
| `passengers` | `rliwwi`, `zbrayu` |
| `large_bags` | `bcsxgw` |
| `carry-on_bags` | `henfgr` |
| `pickup_date` | `3c8aa9` |
| `pickup_time` | `yzwoxy` |
| `return_date` | `ldmuex` |
| `return_time` | `jhfygx` |
| `charter_pickup_date` | form field key |
| `charter_pickup_time` | form field key |
| `charter_drop-off_time` | `charter_drop-off_time` (note hyphen) |

---

## 7. Recommended Next Implementation Tasks

### Immediate (safe to port)

1. **Native date/time inputs** — Already using `<input type="date">` and `<input type="time">` in shortcode
2. **Step attribute for time** — Add `step="300"` (5 min) to time fields in `render_time_field()`
3. **Max passengers config** — Create `WSB_Client_Field_Config` class or extend `BookingFieldRegistry` to expose `max_passengers` as server-provided config

### Near-term (requires scaffold)

4. **Place snapshots scaffold** — Already added in Phase 2N; integrate with Google Places in future
5. **Direction classification** — Port `classifyDirectionRelativeToHQ` to PHP (no API calls)
6. **CTIA distance logic** — Port `haversineKm` and thresholds to PHP for `place_snapshots` enrichment

### Booking-site owned (no immediate action)

7. **Charter pricing codes** — `determine_charter_codes()` stays on booking site
8. **Toll detection** — `checkTollGates()` remains on booking site (HERE API)
9. **Real-time distance matrix** — Google Distance Matrix calls stay on booking side
10. **Vehicle blockouts** — Bookings site owns `wsb_client_is_blocked()` logic

---

## 8. Risks / Unknowns

| Area | Risk | Notes |
|------|------|-------|
| `max_passengers` | SCF dependency | Requires Smart Custom Fields or ACF to be active |
| Charter time windows | Commented out | Need to confirm actual operational hours with business |
| Blocked dates map | Hard-coded | Should be server-provided dynamic list |
| POI distance mapping | Static values | Should be database-driven or API-sourced |
| `charter_drop-off_time` | Hyphenated key | Verify Bricks form field naming in production |

---

## 9. Summary: What Goes Where

| Control | Current State | v2 Plugin Side | Booking Site Side |
|---------|---------------|----------------|-------------------|
| Date picker UI | jQuery UI Datepicker | Native `<input type="date">` | - |
| Time picker UI | jQuery ClockTimePicker | Native `<input type="time">` | - |
| Time step | JS precision: 5 min | `step="300"` attribute | - |
| Max passengers | SCF `company-profile` | Config service (SCF/ACF) | Override/validate |
| Luggage limits | None | Same as passengers | Override/validate |
| Calendar blockouts | Cached from booking site | Diagnostic scaffold only | Full evaluation |
| Vehicle blockouts | Not evaluated | Diagnostic scaffold | Full evaluation |
| Distance to CTIA | JS haversine | PHP scaffold | Real-time via Google |
| Direction classification | JS bearings | PHP scaffold | Real-time via Google |
| Toll detection | HERE API via AJAX | Scaffold only | Full evaluation |
| Charter codes | Hard-coded map | - | Full business logic |
## 10. Date/Time Picker Parity (Phase 2S)

### Date picker — what was ported

| Legacy behavior | Plugin-owned implementation |
|----------------|----------------------------|
| jQuery UI Datepicker with `minDate: 0`, `defaultDate: tomorrow` | Native `<input type="date">` with `min`/`max` from config; JS defaults to tomorrow |
| `beforeShowDay` blockout styling (`wsb-blocked` class + tooltip) | `.wsb-date-blocked` CSS class + `.wsb-picker-status` inline messages |
| `readonly` enforcement | Native date input constraint (`min`/`max` attributes) + JS status feedback |
| Hard-coded `BLOCKED_DATES` Map | `getBlockedDatesFromConfig()` scaffold reading `bookingSiteConfig.blockouts.blocked_dates` |
| Date format `dd/mm/yy` | Native ISO `Y-m-d` format (browser-localized display) |
| Custom calendar icon overlap with native indicator | Native indicator hidden (`display: none`); custom branded icon retained |

### Time picker — what was ported

| Legacy behavior | Plugin-owned implementation |
|----------------|----------------------------|
| jQuery ClockTimePicker `precision: 5` | Native `<input type="time" step="300">` |
| Charter defaults 08:00 pickup / 17:00 dropoff | JS `setCharterTimeDefaults()` sets these values automatically |
| AM/PM label injected as sibling | `.wsb-time-ampm-badge` span inserted next to time input |
| `min` time enforcement on min date | `constrainTimeByDate()` sets `timeInput.min` when date equals config min |
| Clock-style popover on click/focus | Browser-native `<input type="time">` picker opens on click/focus (exact jQuery plugin not vendored) |

### What was deliberately not ported

- jQuery UI library dependency → replaced with native HTML5 inputs
- CDN-loaded jQuery UI CSS → replaced with plugin-owned CSS
- Hard-coded blocked dates Map → replaced with config scaffold (empty until booking site provides data)
- Vehicle-specific blockouts → explicitly excluded from marketing picker per `blockouts.vehicle_scoped_blockouts_affect_marketing_picker: false`
