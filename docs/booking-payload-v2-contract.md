# BookingPayload v2 Contract

This file is the short-form contract summary for the Booking Builder.

The browser preview now renders the canonical BookingPayload v2 shape in real time and keeps the payload aligned with the current shortcode form.

## Canonical fields

- `schema_version: "2.0"`
- `source.site`
- `source.channel`
- `source.page_url`
- `source.referrer`
- `service_group`
- `service_type`
- `trip_type`
- `customer`
- `passengers`
- `baby_seats`
- `luggage.check_in_bags`
- `luggage.carry_on_bags`
- `add_ons.baby_seats`
- `add_ons.trailer`
- `add_ons.oversize_luggage`
- `legs`
- `route`
- `tracking`
- `validation_flags`
- `meta`
- `charter`

## Legs

- One-way uses one outbound leg.
- Return uses outbound plus return legs.
- Additional stops are stored on the outbound leg in `stops[]`.
- Each leg includes `pickup_datetime` for convenience in the preview and downstream validation.

## Preview behavior

The preview updates on:

- page load
- input
- change
- blur
- trip type toggle
- additional stop toggle
- submit

Submit remains intercepted, and `?debug=1` logs the generated payload to the console.
