# UX-003B — JS Datepicker Restoration

## Goal
Restore the previous JS/jQuery-style datepicker experience for the booking builder while preserving field names, values, blockout behaviour, min/max date rules, and payload shape.

## Old/Legacy Picker Source Found
- `assets/js/datepickers.js` — Contains jQuery UI datepicker initialization with blockout support
- `ws-bookings-client.php:180-212` — Enqueues jQuery UI datepicker + CSS via `wp_enqueue_script('jquery-ui-datepicker')` and `wp_enqueue_style('jquery-ui-theme', ...)`
- `inc/legacy-snippets/archive/3-custom-date-picker-on.php` — Legacy reference implementation

## Implementation Approach (Already Complete)
1. **Date inputs use `type="text"`** in `render_date_field()` to allow jQuery UI datepicker to attach (line 310)
2. **`data-wsb-datepicker` attribute** identifies all booking builder date fields
3. **Selector in datepickers.js** includes `[data-wsb-datepicker]` alongside legacy selectors (line 7)
4. **Date format is `yy-mm-dd`** in jQuery UI datepicker to maintain ISO-style date values (YYYY-MM-DD) (line 111)
5. **Calendar icon visible** in CSS (`opacity: 1; display: block;`) (line 485-486)
6. **CSS rules target `data-wsb-datepicker` attribute** for focus/hover states

## Fields Covered
- Book a Ride outbound pickup date (`input[name="outbound_pickup_date"]`)
- Return pickup date (`input[name="return_pickup_date"]`)
- Shuttle Hire same-day date (`input[name="outbound_pickup_date"]` in charter panel)
- Multi-day charter day-card dates (`input[data-wsb-charter-day-field="date"]`)

## Native Date Input Decision
- **Decision: Use `type="text"` instead of `type="date"`** — Native HTML5 date inputs trigger browser-native pickers which cannot be overridden by jQuery UI
- **Reason:** Native date pickers do not provide the custom blockout styling, tooltips, or calendar UX desired
- **Trade-off:** Browser-native date validation (min/max) is handled by jQuery UI datepicker instead

## Blockout/Min/Max Result
- **Min date:** `minDate: 0` allows today selection (configurable via field attributes)
- **Max date:** Handled by jQuery UI datepicker `maxDate` if provided
- **Blockouts:** Fully blocked days disabled via `beforeShowDay` returning `[false, 'wsb-day-blocked', tooltip]`
- **Partial blockouts:** Days with time restrictions shown as selectable with tooltip
- **Section-scoped:** Blockout status shown in `.wsb-picker-status` for each picker group

## Timepicker Regression Result
- Timepicker uses `jquery-clock-timepicker` via `wsb-clock-timepicker` script
- Dependencies unchanged — jQuery UI datepicker addition does not affect timepicker
- AM/PM badges still injected via `updateAmPmLabels()` in `booking-client-form.js`

## Tests/Checks Run
- `php -l inc/class-booking-client-form-shortcode.php` — OK
- `php -l inc/class-booking-field-registry.php` — OK (no changes needed)
- `node --check assets/js/datepickers.js` — OK
- `node --check assets/js/booking-client-form.js` — OK

## Browser QA Checklist
- [ ] Load `/booking-builder/`
- [ ] Load `/booking-builder/?debug=1`
- [ ] Click directly inside each date input and confirm the JS calendar opens
- [ ] Select a date and confirm the input updates
- [ ] Confirm the value format remains `YYYY-MM-DD`
- [ ] Confirm blocked/min/max dates still behave correctly
- [ ] Confirm timepicker still opens
- [ ] Confirm AM/PM badge still works
- [ ] Confirm no internal wording appears

## Remaining Risks
1. **jQuery UI CSS conflicts:** jQuery UI base CSS may override custom styles. Test visually to confirm styling remains intact.
2. **Date format in legacy code:** If any legacy code expects `dd/mm/yy` format, the change to `yy-mm-dd` may cause issues.