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

## Server-side preview endpoint

- POST `/wp-json/ws-bookings-client/v1/payload-preview`
- Requires `Content-Type: application/json`
- Requires `X-WP-Nonce` header using a WP REST nonce
- Returns:
  - `ok`
  - `payload`
  - `normalized_payload`
  - `validation`
  - `meta`

## Current normalisation

- Converts nested `luggage` and flat `check_in_bags` / `carry_on_bags` inputs
- Normalizes inbound `legs[]` payloads and flat marketing form fields
- Ensures `customer` always has `name`, `email`, and `phone`
- Normalizes `meta.handover_mode` to `preview_only`

## Current validation

- `schema_version` must equal `2.0`
- `trip_type` must be one of `one_way`, `return`, or `charter`
- `passengers` must be at least `1`
- `legs` must be a non-empty array
- `from.label`, `to.label`, `pickup_date`, and `pickup_time` are required on each leg

## Limitations

- This endpoint is preview-only and does not create bookings.
- Booking-site handover is still pending.
- Google autocomplete remains pending.
