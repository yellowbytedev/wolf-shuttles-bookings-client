# UX-003 - Date/time picker restoration and blockout parity audit/fix

**Branch:** `feature/frontend-ux-date-blockout-parity`  
**Plugin:** `ws-bookings-client`  
**Scope:** Marketing plugin only. No payload schema, field-name, REST contract, route authority, pricing authority, WooCommerce, cart, checkout, or booking-site code changes.

---

## 1. Objective

Restore production-ready date/time field behaviour in the booking builder and audit the marketing-side blockout parity against the current legacy/production expectations where it is safe to do so.

Chosen date picker approach:

- Keep the native `input[type="date"]` control as the visible picker.
- Restore the native browser affordance with CSS rather than re-wiring the legacy jQuery UI datepicker onto the new builder.
- Leave `assets/js/datepickers.js` as legacy-only wiring for the older selector set.

---

## 2. Files reviewed

- `assets/js/booking-client-form.js`
- `assets/js/blockouts-frontend.js`
- `assets/js/datepickers.js`
- `assets/css/booking-client-form.css`
- `inc/class-booking-client-form-shortcode.php`
- `inc/class-booking-field-registry.php`
- `docs/frontend-ux-001-production-booking-form-spec.md`
- `docs/ux-002-copy-cleanup-internal-wording.md`
- `docs/shared-booking-ui-contract.md`
- `docs/legacy-form-controls-audit.md`
- `docs/vehicle-scoped-blockouts-v2.md`

---

## 3. What was fixed

### Date fields

- Book a Ride outbound date is now section-scoped to the transfer panel.
- Return pickup date is now section-scoped to the return panel.
- Shuttle Hire same-day pickup date now uses the charter-specific minimum date.
- Multi-day charter day-card dates now default from their own `min` attribute.
- Native date affordance is restored in CSS so the picker is visibly usable again.

### Time fields

- Clock time picker dependency is registered before the booking builder script and is now loaded as a dependency.
- Time picker initialization now runs before AM/PM badges are applied.
- Transfer, return, same-day charter, and multi-day day-card time fields are bound with section-aware selectors.

### AM/PM badges

- AM/PM badge logic stays on all relevant time inputs, but it now runs after the clock picker is initialized.
- Badge updates are also re-run when date/time constraints change.

### Blockout parity

- Booking-builder status messages now evaluate date validity before requiring a time value.
- Min notice, max advance, and blocked-date checks are section-scoped.
- The marketing blockout adapter now recognizes:
  - transfer outbound dates/times
  - return dates/times
  - Shuttle Hire same-day dates/times
  - multi-day charter day-card dates/times
- The blockout adapter now clears stale validators when a date is reset.
- The blockout adapter now receives an explicit rescan signal after builder defaults and fixture hydration.
- The blockout rescan listener is guarded so repeated attaches do not stack duplicate listeners.

### Safe authority boundary

- Marketing still does not evaluate vehicle availability.
- Marketing still does not calculate pricing, route, tolls, or final booking authority.
- Any missing booking-site config or authority gap is documented rather than invented.

---

## 4. Changes made

- `inc/class-booking-client-form-shortcode.php`
  - Registered `jquery-clock-timepicker.min.js` before the booking builder script.
  - Added charter same-day minimum-date handling for the outbound date field in the Shuttle Hire context.
  - Removed the separate enqueue that was no longer needed once the dependency was registered.
- `inc/class-booking-field-registry.php`
  - Added a charter-specific minimum-date attribute to the shared outbound pickup date field definition.
- `assets/css/booking-client-form.css`
  - Restored native date-input appearance so the picker is visible/usable.
- `assets/js/booking-client-form.js`
  - Added section-scoped field helpers.
  - Scoped date/time payload reads and fixture hydration to the correct booking sections.
  - Restored date status handling so blocked/invalid dates warn even before a time is chosen.
  - Re-ordered timepicker initialization and AM/PM badge updates.
  - Added a lightweight blockout rescan hook after defaults and fixture loads.
- `assets/js/blockouts-frontend.js`
  - Expanded date selector coverage to the new builder fields.
  - Added section-aware pairing for transfer, return, same-day charter, and charter day-card time fields.
  - Added date-level blocked state handling for fully blocked days.
  - Added a one-time rescan listener guard.

---

## 5. Verification

### Syntax and static checks

- `php -l inc/class-booking-client-form-shortcode.php`
- `php -l inc/class-booking-field-registry.php`
- `node --check assets/js/booking-client-form.js`
- `node --check assets/js/blockouts-frontend.js`
- `git diff --check`

All of the above passed.

### Local page load / browser checks

Live browser checks could not be completed in this environment.

Attempts:

- `curl -I https://wolfshuttles.local/booking-builder/` failed to connect.
- `curl -I http://wolfshuttles.local/booking-builder/` failed to connect.
- `./scripts/wp-marketing.sh option get siteurl` failed because the local WP-CLI context reported a database prefix mismatch / site not installed.

So the production and debug pages were not visually confirmed in a live browser from this session.

---

## 6. Results by task

- **Date picker decision:** native date input kept; legacy datepicker was not rewired into the new booking builder.
- **Time picker fix/result:** the clock time picker is now wired as a dependency and scoped to the correct booking sections.
- **AM/PM badge result:** the badge logic remains in place and now runs after timepicker init, but live visual confirmation still needs a browser pass.
- **Blockout parity result:** section-scoped blockout handling is restored for transfer, return, Shuttle Hire same-day, and multi-day charter day cards. Marketing-side warnings and disabled states now follow the visible builder fields instead of legacy-only selectors.
- **Tests/checks run:** PHP lint, JS syntax checks, and `git diff --check` passed.
- **Normal page result:** not live-verified here because the local site did not respond.
- **Debug page result:** not live-verified here because the local site did not respond.
- **Touched outside repo:** none.

---

## 7. Remaining risks

- No live browser confirmation was possible in this environment, so the visible openability of the native date picker and the timepicker popup still need a real page pass once the local site is reachable.
- `blockouts-frontend.js` is now wired to the new builder sections, but it still depends on the page scripts and the local runtime configuration being present.
- The duplicate shared field names between transfer and Shuttle Hire still exist in the markup by design; the JS has been made section-aware to avoid cross-section reads/writes.
- Any future booking-site changes to vehicle-scoped blockout authority remain out of scope for the marketing plugin and would need booking-site work instead of frontend invention.
