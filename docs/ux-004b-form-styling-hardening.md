# UX-004B — Form Styling Hardening

## Branch
`feature/frontend-ux-location-input-polish` (continuation)

## Goal
Harden visible marketing booking-form controls - fix clear icon rendering, select dropdown styling, and terminology.

## Changes Made

### 1. Clear Icon CSS Fix
- **Problem**: SVG mask encoding in `.wsb-booking-client-place-clear::before` was brittle and unreliable across browsers.
- **Solution**: Replaced with simple text `×` character using system font.
- **File**: `assets/css/booking-client-form.css`
- **Result**: Clear button now reliably renders and is visible when fields have values.

### 2. Stale Warning Icon CSS Fix
- **Problem**: SVG mask encoding in `.wsb-booking-client-place-stale-message::before` was similarly brittle.
- **Solution**: Replaced with `!` character inside a circular badge using background-color.
- **File**: `assets/css/booking-client-form.css`
- **Result**: Warning icon now reliably renders when location is in stale state.

### 3. Number Input to Select Dropdown Conversion
- **Problem**: Field registry defined `passengers`, `baby_seats`, `check_in_bags`, `carry_on_bags` as `type => 'select'`, but shortcode rendered them as `type="number"` inputs (showing browser number steppers).
- **Solution**: Updated `render_number_field()` to output `<select>` elements with appropriate options.
- **Files**:
  - `inc/class-booking-client-form-shortcode.php` - Changed number inputs to select dropdowns
  - `assets/css/booking-client-form.css` - Added select styling with custom arrow icon
- **Result**: Passenger/bag dropdowns now render as proper premium select controls, no tiny steppers.

### 4. Terminology Update: "Same-day Hire" → "Single-day Hire"
- **Problem**: "Same-day hire" sounds like booking for today, which is confusing.
- **Solution**: Changed customer-facing copy to "Single-day hire".
- **Files**:
  - `inc/class-booking-client-form-shortcode.php` - Updated card copy and pill label
  - `assets/js/booking-client-form.js` - Updated charterLabel in preview summary
- **Result**: Terminology now reads "Single-day hire" consistently.

## Files Modified

### CSS
- `assets/css/booking-client-form.css`
  - Replaced SVG mask with text × for clear button
  - Replaced SVG warning icon with ! badge for stale message
  - Added select dropdown styling with arrow icon

### PHP
- `inc/class-booking-client-form-shortcode.php`
  - `render_number_field()` - Changed from `<input type="number">` to `<select>` element
  - Updated terminology: "same-day hire" → "single-day hire"

### JavaScript
- `assets/js/booking-client-form.js`
  - Updated `charterLabel` to show "Single-day hire" instead of "Same-day hire"

## Tests/Checks Run
- `php -l inc/class-booking-client-form-shortcode.php` — OK
- `node --check assets/js/booking-client-form.js` — OK
- Verified curl to booking-builder returns HTTP 200

## Browser/Playwright Results

### Book a Ride Tab
- Select dropdowns render correctly with 14px right padding for arrow
- Clear buttons have proper aria-label="Clear location"
- Clear button appears when location field has value
- Clear button clears value and resets field state correctly
- No browser number steppers visible

### Shuttle Hire Tab
- "Single-day hire" terminology displays correctly
- Select dropdowns for passengers/bags render identically to Book a Ride tab
- Location fields with clear buttons present

### Location Field States
- Stale message display: none (correct, when not stale)
- Clear button display: flex (correct, when has value)
- Input padding-right: 36px (correct for clear button accommodation)

### Mobile (400px width)
- Form stacks to single column
- Selects remain full-width
- No horizontal overflow

## Remaining Risks
1. Screen reader announcement of stale message may need additional aria-live refinement (existing risk from UX-004).
2. Multi-day charter day cards may need similar select styling verification if feature gate is enabled.