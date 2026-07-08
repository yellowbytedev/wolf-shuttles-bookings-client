# Marketing Form Field Semantics and Gated Scaffolding (M3B)

## 1. Purpose

M3B prepares the existing marketing booking forms for future features by standardising field names, data attributes, and form-section semantics. This is a foundation task that improves structure without building the full multi-day/multi-trip UI.

M3B does not:
- Build multi-day charter UI
- Build multi-trip booking UI
- Build drag-and-drop UI
- Implement contextual help runtime
- Change booking-side runtime code
- Alter DB schema
- Modify REST endpoints
- Touch WooCommerce/session/cart/order logic
- Touch booking-side pricing/blockouts/vehicle availability
- Make marketing the pricing authority
- Introduce customer name/email/phone as required initial marketing fields

## 2. Files Reviewed

- `docs/m3a-feature-gate-config-foundation.md`
- `docs/booking-payload-v2-contract.md`
- `docs/google-places-quote-ready-handoff.md`
- `docs/m3x-contextual-help-guided-assistance-plan.md`
- `inc/class-booking-feature-gates.php`
- `inc/class-booking-client-form-shortcode.php`
- `inc/class-booking-field-registry.php`
- `inc/class-booking-payload-v2-normalizer.php`
- `inc/class-booking-payload-v2-validator.php`
- `assets/js/booking-client-form.js`
- `tests/fixtures/booking-payload-v2-fixtures.json`
- `scripts/run-feature-gate-smoke.php`
- `scripts/run-booking-payload-fixtures.php`
- `scripts/run-booking-handover-fixtures.php`

## 3. Files Changed

- `inc/class-booking-field-registry.php`
- `inc/class-booking-client-form-shortcode.php`
- `assets/js/booking-client-form.js`
- `scripts/run-form-semantics-smoke.php`
- `docs/m3b-form-field-semantics-and-gated-scaffolding.md` (this file)
- `docs/m3a-feature-gate-config-foundation.md` (updated with M3B usage note)

## 4. Field Keys Added/Confirmed

M3B confirms the following stable field keys in the registry:

### Book a Ride (Transfer)
- `passengers`
- `baby_seats`
- `check_in_bags`
- `carry_on_bags`
- `trailer`
- `oversize_luggage`
- `outbound_from`
- `outbound_to`
- `outbound_pickup_date`
- `outbound_pickup_time`
- `return_from`
- `return_to`
- `return_pickup_date`
- `return_pickup_time`
- `outbound_additional_stop`
- `return_additional_stop`

### Shuttle Hire (Charter)
- `passengers`
- `baby_seats`
- `check_in_bags`
- `carry_on_bags`
- `trailer`
- `oversize_luggage`
- `charter_pickup_location`
- `charter_dropoff_location`
- `charter_pickup_time`
- `charter_dropoff_time`
- `charter_additional_stop`
- `charter_poi`
- `charter_notes`

## 5. Data Attributes Added/Confirmed

Each field registry entry now includes a `data_attributes` array with stable attributes:

| Attribute | Purpose | Example |
|-----------|---------|---------|
| `data-ws-field-key` | Stable server-friendly field identifier | `data-ws-field-key="passengers"` |
| `data-ws-form-section` | Logical form section grouping | `data-ws-form-section="outbound_locations"` |
| `data-ws-help` | Future help/tooltip mapping key | `data-ws-help="passengers"` |
| `data-ws-help-context` | Context scope for help content | `data-ws-help-context="book_a_ride,shuttle_hire"` |
| `data-ws-feature-gate` | Gate controlling visibility/behaviour | `data-ws-feature-gate="enable_additional_stops"` |
| `data-ws-route-role` | Route endpoint role | `data-ws-route-role="origin"` |
| `data-ws-place-role` | Google Places endpoint mapping | `data-ws-place-role="origin"` |

Key attribute mappings:
- **Book a Ride**: origin/destination fields get `route-role` and `place-role` of `origin`, `destination`, `return_origin`, `return_destination`
- **Shuttle Hire**: origin/destination fields get `route-role` and `place-role` of `charter_origin`, `charter_destination`
- **Additional stops**: get `route-role="stop"` and `place-role="stop"` plus `feature-gate="enable_additional_stops"`
- **Google Places fields**: all location inputs receive `data-ws-place-role` via JS initialization

## 6. Additional-Stop Gate Behaviour

The existing additional stop UI is controlled by the `enable_additional_stops` feature gate:

- **Gate disabled** (production default): additional stop toggles are hidden/disabled, stop fields remain inert
- **Gate enabled**: existing toggle/show/hide behaviour is preserved
- **Stable field keys**: `outbound_additional_stop` and `return_additional_stop` have stable `data-ws-field-key` attributes
- **No payload changes**: existing payload behaviour is unchanged unless code already supports it

The additional stop sections include:
```html
data-ws-feature-gate="enable_additional_stops"
```

The JS now includes `applyFeatureGateVisibility()` which:
- Disables additional stop toggles when the gate is off
- Hides additional stop sections when the gate is off
- Preserves existing toggle behaviour when the gate is on

## 7. Route Role Mapping

Field-level route roles are documented and hooked via data attributes:

| Field | Route Role | Place Role |
|-------|-----------|------------|
| `outbound_from` | `origin` | `origin` |
| `outbound_to` | `destination` | `destination` |
| `return_from` | `return_origin` | `return_origin` |
| `return_to` | `return_destination` | `return_destination` |
| `charter_pickup_location` | `charter_origin` | `charter_origin` |
| `charter_dropoff_location` | `charter_destination` | `charter_destination` |
| `outbound_additional_stop` | `stop` | `stop` |
| `return_additional_stop` | `stop` | `stop` |

## 8. Google Places Hook Mapping

Fields requiring Google Places selection have stable attributes:

```html
<input
    name="outbound_from"
    data-ws-field-key="outbound_from"
    data-ws-place-role="origin"
    data-ws-route-role="origin"
/>
```

The JS `initGooglePlacesAutocomplete` now:
- Maps each autocomplete field to its `snapshotKey`
- Sets `data-ws-route-role` and `data-ws-place-role` attributes dynamically
- Sets `data-ws-field-key` if not already present by the server renderer
- Preserves all existing place selection, stale detection, and snapshot behaviour

## 9. Contextual Help Hook Mapping

Stable help hooks are added to core fields via `data-ws-help` and `data-ws-help-context`:

| Field | Help Key | Context |
|-------|----------|---------|
| `passengers` | `passengers` | `book_a_ride,shuttle_hire` |
| `baby_seats` | `baby_seats` | `book_a_ride,shuttle_hire` |
| `check_in_bags` | `check_in_bags` | `book_a_ride,shuttle_hire` |
| `carry_on_bags` | `carry_on_bags` | `book_a_ride,shuttle_hire` |
| `trailer` | `trailer` | `book_a_ride,shuttle_hire` |
| `oversize_luggage` | `oversize_luggage` | `book_a_ride,shuttle_hire` |
| `outbound_from` | `pickup_location` | `book_a_ride` |
| `outbound_to` | `dropoff_location` | `book_a_ride` |
| `return_from` | `pickup_location` | `book_a_ride` |
| `return_to` | `dropoff_location` | `book_a_ride` |
| `outbound_additional_stop` | `additional_stop` | `book_a_ride` |
| `return_additional_stop` | `additional_stop` | `book_a_ride` |
| `outbound_pickup_date` | `pickup_date` | `book_a_ride` |
| `outbound_pickup_time` | `pickup_time` | `book_a_ride` |
| `charter_pickup_location` | `pickup_location` | `shuttle_hire` |
| `charter_dropoff_location` | `dropoff_location` | `shuttle_hire` |
| `charter_pickup_time` | `pickup_time` | `shuttle_hire` |
| `charter_dropoff_time` | `dropoff_time` | `shuttle_hire` |
| `charter_poi` | `charter_poi` | `shuttle_hire` |
| `charter_notes` | `charter_notes` | `shuttle_hire` |

No help library is loaded. No tooltip JS is added. Only structural attributes are present.

## 10. Reserved Future Multi-Day/Multi-Trip Keys

Documented but not built:

- `charter_days`
- `charter_day_id`
- `charter_day_sort_order`
- `charter_day_date`
- `charter_day_start_time`
- `charter_day_end_time`
- `charter_day_pickup_location`
- `charter_day_dropoff_location`
- `itinerary_trips`
- `itinerary_trip_id`
- `itinerary_trip_sort_order`
- `itinerary_trip_legs`

These keys are reserved in documentation only. No UI or payload schema changes were made for them.

## 11. Additional Stop Gated Scaffolding

Existing additional stop UI is fully retained. The only M3B change is:
- Feature gate marker `data-ws-feature-gate="enable_additional_stops"` added to toggles and sections
- JS gate visibility function added
- Stable field keys confirmed in registry

No new stop UI was built. No additional stop payload behaviour was changed.
DOM ID hygiene is handled in the M3B.1 follow-on pass, which prefixes rendered field IDs by card/context while keeping the stable field keys unchanged.

## 12. Payload Compatibility

- All existing valid payload fixtures pass after M3B changes
- Existing form submit behaviour is unchanged
- No required customer/contact fields added
- No multi-day/multi-trip payload emitted
- No booking-side runtime code changed
- No REST endpoint contracts altered

## 13. Tests Run

| Test | Result |
|------|--------|
| `php scripts/run-feature-gate-smoke.php` | PASS |
| `php scripts/run-form-semantics-smoke.php` | PASS |
| `php scripts/run-booking-payload-fixtures.php` | PASS (35/35) |
| `php -l inc/class-booking-field-registry.php` | No syntax errors |
| `php -l inc/class-booking-client-form-shortcode.php` | No syntax errors |
| `php -l assets/js/booking-client-form.js` | No syntax errors |

## 14. Known UI Issues Left for Later

- Date/time field layout and AM/PM suffix positioning
- Tab alignment and active state consistency
- Input width inconsistencies
- Shuttle Hire date/time grouping visual association
- Mobile responsive behaviour
- Visual polish deferred until functionality is stable

## 15. Recommended Next Phase

- **M3C** — Wire data attributes into a lightweight JS field-semantics layer (no visible UI), preparing selectors for future Google Places enforcement, help hooks, and route role mapping
- Or proceed with the next marketing-side feature that needs stable field semantics
