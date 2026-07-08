# M3B.1 - Form HTML ID Hygiene / Duplicate ID Cleanup

## Purpose

M3B.1 removes duplicate HTML `id` attributes from the rendered marketing booking forms while preserving the stable semantic field keys, payload shape, feature gates, and Google Places bindings established by M3B.

This pass does not:
- Build new UI
- Build multi-day UI
- Build multi-trip UI
- Build drag-and-drop UI
- Implement contextual help runtime
- Change booking-side runtime code
- Alter DB schema
- Modify REST endpoints
- Touch WooCommerce/session/cart/order logic
- Add route/distance/toll/pricing calculation
- Introduce customer name/email/phone as required initial marketing fields

## Duplicate IDs Found Before

The form-semantics smoke previously warned about duplicate IDs for:
- `passengers`
- `baby_seats`
- `check_in_bags`
- `carry_on_bags`
- `outbound_pickup_date`

These collisions came from the same semantic field keys being rendered in multiple booking cards and sections on the same page.

## Root Cause

The shortcode renderer used the semantic field key directly as the DOM `id`.

That worked when a field appeared once, but it broke when the same field key was reused in both:
- `Book a Ride`
- `Shuttle Hire`
- outbound and return sections
- additional-stop sections

As a result, several labels and inputs shared the same DOM `id` across the page.

## ID Naming Strategy

The renderer now generates context-prefixed DOM IDs using the pattern:

```text
wsb-<context>-<field-key>
```

Examples:
- `wsb-book-a-ride-passengers`
- `wsb-shuttle-hire-passengers`
- `wsb-book-a-ride-outbound-pickup-date`
- `wsb-book-a-ride-outbound-additional-stop`
- `wsb-book-a-ride-return-pickup-date`
- `wsb-shuttle-hire-charter-pickup-location`

The stable field keys remain unchanged in:
- `name`
- `data-ws-field-key`
- payload generation
- fixture expectations
- Google Places snapshot mapping

## Files Changed

- `inc/class-booking-client-form-shortcode.php`
- `scripts/run-form-semantics-smoke.php`
- `docs/m3b-form-field-semantics-and-gated-scaffolding.md`
- `docs/m3b-1-form-html-id-hygiene.md`

## Compatibility Notes

- Field keys were not renamed.
- `data-ws-field-key`, `data-ws-help`, `data-ws-help-context`, `data-ws-route-role`, `data-ws-place-role`, and `data-ws-feature-gate` semantics were preserved.
- The additional-stop section markers used by the existing JS toggles were preserved.
- Google Places continues to bind through the existing name- and data-attribute-driven selectors.
- The payload fixtures and handover fixtures remain unchanged.
- Booking-side runtime code was not modified.

## Verification Results

- `php -l marketing-site/app/public/wp-content/plugins/ws-bookings-client/inc/class-booking-client-form-shortcode.php` - PASS
- `php -l marketing-site/app/public/wp-content/plugins/ws-bookings-client/scripts/run-form-semantics-smoke.php` - PASS
- `php scripts/run-form-semantics-smoke.php` - PASS
- `php scripts/run-feature-gate-smoke.php` - PASS
- `php scripts/run-booking-payload-fixtures.php` - PASS
- `php scripts/run-booking-handover-fixtures.php` - PASS
- `./scripts/verify-wolf-booking-v2.sh` - PASS

The updated form-semantics smoke now asserts:
- no duplicate IDs remain in the rendered shortcode
- every label `for` value points to an existing input `id`

## Result

The rendered marketing forms now have unique DOM IDs, while the stable field keys and payload semantics remain intact.
