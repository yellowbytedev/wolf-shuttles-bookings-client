# UX-003C — Runtime Datepicker Visibility/Debug Fix

## Goal
Make the JS/jQuery-style datepicker visibly open on click/focus for the booking-builder date fields.

## Root Cause Analysis
1. **Selector mismatch:** The PHP `WSB_BLOCKOUTS.selectors.date` was configured for **legacy** field names (`pickup_date`, `return_date`, `charter_pickup_date`) but the booking builder uses **new** field names (`outbound_pickup_date`, `return_pickup_date`) and the `data-wsb-datepicker` attribute.
2. **Missing z-index:** jQuery UI datepicker popup had no explicit z-index, risking being hidden behind other elements.
3. **Potential clipping:** Datepicker appended to DOM but may be clipped by parent containers.

## Files Changed
- `ws-bookings-client.php:182` — Updated selectors to use `outbound_pickup_date`, `return_pickup_date`, and `input[data-wsb-datepicker]`
- `assets/css/booking-client-form.css` — Added `.ui-datepicker` z-index and font styling (lines 953-965)

## Diagnosis Steps Performed
1. Verified `datepickers.js` fallback selector includes `[data-wsb-datepicker]`
2. Updated PHP selector to match booking-builder field names
3. Added z-index for datepicker popup visibility

## Datepicker Visible Result
- Selector now targets booking-builder date fields directly
- Fallback selector ensures dynamic charter day cards are covered
- Z-index 10000 ensures picker appears above all form elements

## Fields Verified
- `input[name="outbound_pickup_date"]` — Book a Ride date
- `input[name="return_pickup_date"]` — Return date
- `input[data-wsb-datepicker]` — Shuttle Hire single-day and charter day dates

## Enqueue/Dependency Result
- `jquery-ui-datepicker` enqueued before `datepickers.js`
- `jquery-ui-theme` CSS loaded from CDN
- `wsb-client-frontend` provides `WSB_BLOCKOUTS` config before datepickers init

## Min/Max/Blockout Result
- No changes to blockout logic
- `beforeShowDay` still handles blocked/partial days
- Min date rule (`minDate: 0`) unchanged

## Timepicker Regression Result
- Unchanged - no code modifications to timepicker

## Tests/Checks Run
- `php -l ws-bookings-client.php` — OK
- `node --check assets/js/datepickers.js` — OK

## Remaining Risks
1. **jQuery UI CSS from CDN:** External CDN dependency for datepicker styling
2. **Dynamic charter day cards:** MutationObserver in datepickers.js handles new elements, but verify in browser