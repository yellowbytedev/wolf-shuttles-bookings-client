# Booking Intake Testing Engine

## Goal

Avoid manually filling in the Booking Builder form every time.

The test runner should load known v2 payload fixtures, pass them through the same normalizer and validator as the form/REST preview endpoint, and output:

- fixture id
- trip type
- service type
- validation status
- normalized payload summary
- warnings/errors

## Future command

Suggested WP-CLI command:

```bash
wp wsb-client test-payloads
```

Optional fixture file:

```bash
wp wsb-client test-payloads --file=tests/fixtures/booking-payload-v2-core.json
```

## What it should not do yet

- It must not create real bookings.
- It must not call the booking site.
- It must not add WooCommerce cart items.

## What it should test now

- one-way payloads
- return payloads
- additional stop payloads
- invalid missing required fields
- invalid return leg without return date/time
- invalid numeric values
