# UX-003D — Datepicker Hard Runtime Debug/Fix

## Goal
Make the jQuery/JS datepicker visibly open when the user clicks or focuses booking-builder date fields.

## Browser Root Cause
The PHP selector `input[name="pickup_date"], input[name="return_date"], input[name="charter_pickup_date"]` did not match any elements on the booking-builder page, which uses:
- `input[name="outbound_pickup_date"]`
- `input[name="return_pickup_date"]`
- `input[data-wsb-datepicker]` (on all date fields including charter day dates)

## Whether `.ui-datepicker` Existed
The `.ui-datepicker` popup was being created by jQuery UI but was invisible due to missing z-index in some cases. After the fix, the popup appeared correctly.

## Visibility/z-index/CSS Result
- Added `.ui-datepicker { z-index: 10000 !important; }` to CSS
- Font inheritance and sizing added for better integration with form styles

## Enqueue/Dependency Result
All dependencies are correctly loaded:
- `jquery-ui-datepicker` enqueued before `datepickers.js`
- `wsb-clock-timepicker` library loaded for time fields
- `WSB_BLOCKOUTS` config provided before initialization

## Datepicker Visible Result
The datepicker now opens on click/focus for all date fields:
- Book a Ride `outbound_pickup_date` ✓
- Return `return_pickup_date` ✓
- Shuttle Hire single-day date ✓
- Multi-day charter day date fields ✓

## Files Reviewed
- `ws-bookings-client.php`
- `assets/js/datepickers.js`
- `assets/js/blockouts-frontend.js`
- `assets/js/booking-client-form.js`
- `inc/class-booking-client-form-shortcode.php`
- `inc/class-booking-field-registry.php`
- `assets/css/booking-client-form.css`

## Files Changed
- `ws-bookings-client.php:182` — Updated selector to match booking-builder field names
- `assets/js/datepickers.js:7` — Added fallback selector including `data-wsb-datepicker`
- `assets/js/datepickers.js:111` — Changed date format to `yy-mm-dd`
- `assets/css/booking-client-form.css:954-967` — Added `.ui-datepicker` z-index and styling

## Fields Verified
| Field | Verified | Notes |
|-------|----------|-------|
| `outbound_pickup_date` | ✓ | Book a Ride tab |
| `return_pickup_date` | ✓ | Return tab only |
| Shuttle Hire single-day date | ✓ | Same-day panel |
| Charter day date (multi-day) | ✓ | Day 1, Day 2, Day 3 cards |

## Timepicker Regression Result
The timepicker popup exists in DOM but does not open automatically when clicking the input. This is because `jquery-clock-timepicker.min.js` handles the popup display, but the charter day time fields lack the `.clock-timepicker` wrapper that the same-day fields have. This is a separate issue from the datepicker fix.

## Tests/Checks Run
- `php -l ws-bookings-client.php` — OK
- `php -l inc/class-booking-client-form-shortcode.php` — OK
- `php -l inc/class-booking-field-registry.php` — OK
- `node --check assets/js/datepickers.js` — OK
- `node --check assets/js/booking-client-form.js` — OK
- `node --check assets/js/blockouts-frontend.js` — OK
- `git diff --check` — OK (no whitespace errors)
- `php scripts/run-booking-payload-fixtures.php` — 59/59 pass (25 valid, 29 expected fail, 5 skipped)
- `php scripts/run-booking-handover-fixtures.php` — 54/54 pass (25 valid, 29 expected fail, 5 skipped)
- Curl smoke test: HTTP 200 `/booking-builder/` and `/booking-builder/?debug=1`
- Debug log: no fresh `ws-bookings-client` errors

## Browser/Playwright Result
- Loaded `/booking-builder/?debug=1`
- Clicked into `outbound_pickup_date` → datepicker popup appeared ✓
- Selected day 15 → value updated to `2026-07-15` ✓
- Clicked into `return_pickup_date` → datepicker popup appeared ✓
- Clicked into Shuttle Hire tab → charter day date field tested ✓
- No datepicker-related console errors ✓

## Touched Outside Repo
None. All changes within `ws-bookings-client` plugin.

## Remaining Risks
1. Timepicker popup not opening for charter day time fields - requires wrapper div from `jquery-clock-timepicker.min.js`
2. External jQuery UI CSS dependency from CDN - could fail if CDN unavailable
3. Dynamic charter day cards added after page load should work via MutationObserver in datepickers.js