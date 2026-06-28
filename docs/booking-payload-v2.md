# BookingPayload v2 Contract

## Purpose

BookingPayload v2 is the canonical marketing-site booking intake shape for the Booking Builder.

The live shortcode preview now renders this payload in real time as the form changes, including page load, input, change, blur, trip-type toggles, additional-stop toggles, and submit.

## Naming decisions

Use these terms:

```text
schema_version: "2.0"
service_group: transfer | charter
service_type: airport_pickup | airport_dropoff | city_transfer | charter_hire
trip_type: one_way | return | charter
```

Avoid `service_family`.

Keep the canonical marketing field names:

```text
passengers
baby_seats
check_in_bags
carry_on_bags
trailer
oversize_luggage
outbound_from
outbound_to
outbound_pickup_date
outbound_pickup_time
return_from
return_to
return_pickup_date
return_pickup_time
additional_stop_enabled
additional_stop
```

## Core structure

The browser preview uses this shape:

```json
{
  "schema_version": "2.0",
  "source": "marketing_booking_builder",
  "service_group": "transfer",
  "service_type": "city_transfer",
  "trip_type": "one_way",
  "customer": {
    "name": "",
    "email": "",
    "phone": ""
  },
  "passengers": 1,
  "baby_seats": 0,
  "check_in_bags": 0,
  "carry_on_bags": 0,
  "add_ons": {
    "trailer": false,
    "oversize_luggage": false
  },
  "route": {
    "provider": null,
    "selected_route_id": null,
    "selected_route_label": null,
    "distance_meters": null,
    "duration_seconds": null,
    "polyline": null,
    "route_options": []
  },
  "charter": {
    "enabled": false,
    "type": null,
    "days": []
  },
  "validation_flags": {},
  "legs": [],
  "tracking": {},
  "meta": {
    "preview_only": true,
    "handover_mode": "preview",
    "created_at": ""
  }
}
```

## Legs model

A direct one-way trip has one leg:

```json
{
  "sequence": 1,
  "leg_group": "outbound",
  "leg_type": "direct",
  "service_type": "city_transfer",
  "from": {
    "label": "",
    "name": "",
    "town": "",
    "neighbourhood": "",
    "place_id": "",
    "coords": {
      "lat": null,
      "lng": null
    },
    "formatted_address": ""
  },
  "to": {
    "label": "",
    "name": "",
    "town": "",
    "neighbourhood": "",
    "place_id": "",
    "coords": {
      "lat": null,
      "lng": null
    },
    "formatted_address": ""
  },
  "pickup_date": "",
  "pickup_time": "",
  "pickup_datetime": "",
  "stops": [],
  "route": {}
}
```

A return trip adds a second leg with `leg_group: "return"` and `sequence: 2`.

An additional stop is stored on the outbound leg:

```json
{
  "type": "additional_stop",
  "location": {
    "label": "",
    "name": "",
    "town": "",
    "neighbourhood": "",
    "place_id": "",
    "coords": {
      "lat": null,
      "lng": null
    },
    "formatted_address": ""
  }
}
```

## Preview behavior

The live preview should update:

- on page load
- on input
- on change
- on blur
- when the trip type toggles
- when the additional stop toggle changes
- on submit

Submit remains intercepted. The preview is local only.

If `?debug=1` is present, the browser logs the generated payload to the console.

## Server-side preview endpoint

The shortcode also supports a server-side preview endpoint for validation only.

- Path: `/wp-json/ws-bookings-client/v1/payload-preview`
- Method: `POST`
- Headers: `Content-Type: application/json`, `X-WP-Nonce: <wp_rest nonce>`

### Request shape

The endpoint accepts the canonical BookingPayload v2 payload shape, including:

- `schema_version`
- `source`
- `service_type`
- `trip_type`
- `passengers`
- `baby_seats`
- `luggage.check_in_bags`
- `luggage.carry_on_bags`
- `add_ons`
- `legs[]`
- `meta.preview_only`

It also accepts the marketing shortcode form's flat field names and normalizes them into the canonical legs structure.

### Response shape

The endpoint returns JSON with:

- `ok` — always `true` for a valid preview request
- `payload` — the normalized payload
- `normalized_payload` — alias of the normalized payload
- `validation` — validation result object
- `meta.preview_only` — `true`
- `meta.generated_at` — ISO timestamp

### Normalisation rules

- Accepts nested `luggage` values or flat `check_in_bags` / `carry_on_bags` values.
- Normalizes `legs[]` payloads, including `from`, `to`, `pickup_date`, `pickup_time`, `stops`, and `route`.
- Normalizes `customer` into `name`, `email`, and `phone`.
- Preserves `service_group` or infers it from `service_type`.
- Normalizes top-level `route` block with safe scaffold (provider, selected_route_id, distance_meters, duration_seconds, polyline, route_options).
- Normalizes `charter` block with disabled-by-default scaffold (enabled: false, type: null, days: []).
- Preserves `validation_flags` or defaults to empty object.
- Sets `meta.preview_only` to `true` and `meta.handover_mode` to `preview`.
- The scaffolds are stretch goals — no Google API call, no route calculation, no charter UI yet.

### Validation rules

- `schema_version` must be `2.0`
- `trip_type` must be `one_way`, `return`, or `charter`
- `passengers` must be at least `1`
- At least one leg is required
- Each leg must have:
  - `type`
  - `from.label`
  - `to.label`
  - `pickup_date`
  - `pickup_time`

### Security note

The preview endpoint uses `X-WP-Nonce` protection and verifies a WP REST nonce with action `wp_rest`.

### Current limitations

- Preview is validation-only and does not submit a real booking.
- Booking-site handover is pending (v2 handover envelope dry-run foundation added).
- Google autocomplete is still pending.

## V2 Handover Envelope

The dry-run handover service wraps a validated BookingPayload v2 in a signed envelope.

### Envelope shape

```json
{
  "handover_version": "2.0",
  "schema_version": "2.0",
  "mode": "dry_run",
  "action": "handover_preview",
  "request_id": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
  "created_at": "2026-07-01T12:00:00+00:00",
  "expires_at": "2026-07-01T13:00:00+00:00",
  "source_site": "marketing",
  "target_site": "booking",
  "payload": { /* normalized BookingPayload v2 */ },
  "integrity": {
    "algorithm": "hash_hmac_sha256",
    "signature": "<HMAC-SHA256 hex string or empty if no secret>",
    "signed_fields": [
      "handover_version",
      "schema_version",
      "action",
      "request_id",
      "created_at",
      "expires_at",
      "payload"
    ]
  },
  "meta": {
    "preview_only": true,
    "real_handover_enabled": false
  }
}
```

### Key rules

- `mode` is always `dry_run` during Phase 2H.
- `payload` is always the normalised and validated BookingPayload v2.
- `integrity.signature` is computed with `hash_hmac('sha256', ...)`.
- The signing secret is resolved in this order: constructor parameter → `WSB_CLIENT_V2_HANDOVER_SECRET` constant → dev fallback string when `WP_DEBUG` is true → empty string.
- An empty secret produces an empty `signature` — this is intentional for local fixture tests that run outside WordPress.
- Envelope production is deterministic: the same normalised payload with the same `request_id`, `created_at`, and `expires_at` produces the same `signed_fields` subset and the same HMAC signature.

### REST endpoint

- Path: `/wp-json/ws-bookings-client/v1/handover-preview`
- Method: `POST`
- Headers: `Content-Type: application/json`, `X-WP-Nonce: <wp_rest nonce>`
- Request body: a BookingPayload v2 JSON payload (flat form fields or full legs structure)

Response (valid payload):
```json
{
  "ok": true,
  "payload": { /* normalised payload */ },
  "normalised_payload": { /* same as payload */ },
  "validation": { "valid": true, "errors": [], "warnings": [] },
  "handover_envelope": { /* envelope as above */ },
  "meta": {
    "preview_only": true,
    "real_handover_enabled": false,
    "generated_at": "2026-07-01T12:00:00+00:00"
  }
}
```

Response (invalid payload):
```json
{
  "ok": false,
  "payload": { /* normalised payload */ },
  "validation": { "valid": false, "errors": [...], "warnings": [...] },
  "meta": {
    "preview_only": true,
    "real_handover_enabled": false,
    "generated_at": "2026-07-01T12:00:00+00:00"
  }
}
```

### Security rules

- The endpoint requires a valid `X-WP-Nonce` header (`wp_rest` action).
- No booking is created.
- No booking token is created.
- No booking-site API call is made.
- No database records are created.
- Secrets are never exposed to JavaScript.
