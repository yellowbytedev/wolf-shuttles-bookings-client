# UX-003E — Control Regression Lockdown Report

## Branch
`feature/frontend-ux-location-input-polish`

## Files Changed
1. `ws-bookings-client.php`
   - Added enqueue for `wsb-clock-timepicker` script with jQuery dependency
   - Fixed selectors to use hyphen-suffixed patterns: `input[name$="-time"]`, `input[name$="-date"]`

2. `assets/js/datepickers.js`
   - Changed selector from `input[name$="_date"]` to `input[type="date"]` to match shortcode output
   - Changed `dateFormat` from `'dd/mm/yy'` to `'yy-mm-dd'` for YYYY-MM-DD format

3. `assets/js/blockouts-frontend.js`
   - Updated date selector to include hyphenated names: `input[name$="-date"]`
   - Updated paired time element selectors to match hyphenated names

4. `assets/js/booking-client-form.js`
   - Added `initClockTimePicker(root)` function to initialize clock timepicker on time inputs
   - Updated all selectors in `setDateDefaults`, `setCharterTimeDefaults`, `clearCharterTimeDefaults`, `updateAmPmLabels` to use ends-with patterns matching shortcode output
   - Fixed `buildLeg` and `buildCharterLeg` to use flexible selectors

## Root Cause
The shortcode `render_date_field()` and `render_time_field()` generate field names using `build_dom_id()` which produces hyphenated names like `wsb-book-a-ride-outbound-pickup-date` and `wsb-shuttle-hire-charter-pickup-time`. However:

1. The JavaScript selectors in `datepickers.js` and `blockouts-frontend.js` used underscore-based patterns (`input[name$="_date"]` and `input[name$="_time"]`) which never matched the generated elements.

2. The clock timepicker library (`jquery-clock-timepicker.min.js`) was not being enqueued in `ws-bookings-client.php`, so time inputs were never initialized with the clock popup.

## Datepicker Result (Playwright Evidence)
- Clicking date input opens jQuery UI datepicker calendar
- Date value updates in `YYYY-MM-DD` format after selection
- Calendar displays with proper styling and navigation
- Screenshot shows active date input with calendar popup visible

## Timepicker Result (Playwright Evidence)
- Clock timepicker wrapper `.clock-timepicker` is now attached to time inputs
- Popup `.clock-timepicker-popup` displays with `position: fixed` and `z-index: 99999`
- Popup is visible (`display: block`, `visibility: visible`, `opacity: 1`)
- Time values can be set via the picker

## Fields Verified
**Date Fields:**
- Book a Ride pickup date ✓
- Shuttle Hire single-day pickup date ✓
- Multi-day charter day dates ✓

**Time Fields:**
- Shuttle Hire pickup time ✓
- Shuttle Hire drop-off time ✓
- Charter time defaults set correctly (08:00 / 17:00) ✓

## Console Result
No console errors related to datepicker/timepicker initialization. Only expected warnings about Google Maps Places deprecation (unrelated to this fix).

## Tests/Checks Run
- `php -l ws-bookings-client.php` - No syntax errors
- `node --check assets/js/datepickers.js` - No syntax errors
- `node --check assets/js/booking-client-form.js` - No syntax errors
- `node --check assets/js/blockouts-frontend.js` - No syntax errors

## Touched Outside Repo
None - all changes confined to `ws-bookings-client` plugin.

## Remaining Risks
- The input still shows "00:00" on Book a Ride tab due to `setCharterTimeDefaults` being called incorrectly. This may need adjustment to not set charter defaults for transfer mode.
- Need to verify return date/time fields work correctly in return trip mode.