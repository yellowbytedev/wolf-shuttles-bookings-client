# BookingPayload v2 Contract

## Purpose
BookingPayload v2 is the canonical marketing-site booking intake shape for the Booking Builder. The live shortcode preview now renders this payload in real time as the form changes, including page load, input, change, blur, trip-type toggles, additional-stop toggles, and submit.

## Naming decisions
Use these terms:
```text
schema_version: "2.0"
service_group: transfer | charter
service_type: airport_pickup | airport_dropoff | city_transfer | charter_hire
trip_type: one_way | return | charter
```

Avoid `service_family`. Keep the canonical marketing field names:
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
outbound_additional_stop_enabled
outbound_additional_stop
return_from
return_to
return_pickup_date
return_pickup_time
return_additional_stop_enabled
return_additional_stop
charter_pickup_location
charter_dropoff_location
charter_pickup_time
charter_dropoff_time
```

Additional stops are transfer-leg-scoped only. Each transfer leg (outbound or return) may have its own `stops[]` array. Charter legs always have an empty `stops[]` array per business rules.

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
  "blockouts": {
    "version": 2,
    "authority": "booking_site",
    "marketing_evaluates_vehicle_availability": false,
    "vehicle_scoped_blockouts_supported": true,
    "global_picker_blockouts_supported": true,
    "config_hash": null,
    "marketing_evaluated_at": null,
    "notes": []
  },
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
  "type": "outbound",
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

A charter leg includes `dropoff_time`:
```json
{
  "type": "charter",
  "from": { "label": "Pickup location" },
  "to": { "label": "Drop-off location" },
  "pickup_date": "2026-08-15",
  "pickup_time": "09:00",
  "dropoff_time": "17:00",
  "stops": [],
  "route": {}
}
```

An additional stop is stored on any leg:
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

## Place snapshots (Phase 2N)

Per-leg `place_snapshots` carries Google Places metadata separately from the display strings. This is scaffold-only; no live Google calls yet.

```json
"place_snapshots": {
  "from": {
    "provider": "google_places",
    "place_id": "mock_origin_place_id",
    "label": "Origin location",
    "formatted_address": "Street Address, City, Country",
    "lat": -33.9249,
    "lng": 18.4241
  },
  "to": {
    "provider": "google_places",
    "place_id": "mock_destination_place_id",
    "label": "Destination location",
    "formatted_address": "Street Address, City, Country",
    "lat": -33.9333,
    "lng": 18.8467
  },
  "stops": []
}
```

Rules:
- `from` and `to` strings remain the display label (unchanged)
- `place_snapshots` is optional; defaults to null values if absent
- `provider` is `google_places` when Google data is available
- `place_id` is optional; placeholder values like `mock_origin_place_id` are used in fixtures
- No real API keys or client details are included

## Preview behavior
The live preview should update:
- on page load
- on input
- on change
- on blur
- when the service mode toggles (transfer ↔ charter)
- when the trip type toggles (one-way ↔ return)
- when the additional stop toggle changes
- on submit
Submit remains intercepted. The preview is local only. If `?debug=1` is present, the browser logs the generated payload to the console.

## Server-side preview endpoint
The shortcode also supports a server-side preview endpoint for validation only:
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
- Accepts nested `luggage` values or flat `check_in_bags` / `carry_on_bags` values
- Normalizes `legs[]` payloads, including `from`, `to`, `pickup_date`, `pickup_time`, `stops`, and `route`
- Normalizes `customer` into `name`, `email`, and `phone`
- Preserves `service_group` or infers it from `service_type`
- Normalizes top-level `route` block with safe scaffold
- Normalizes `charter` block: builds from flat fields for charter trips, disabled-by-default otherwise
- Preserves `validation_flags` or defaults to `{}`
- Normalizes `blockouts` with diagnostic scaffold (authority: booking_site)
- Sets `meta.preview_only` to `true` and `meta.handover_mode` to `preview`

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
- Lead-time validation:
  - Transfer/charter legs: `pickup_date` + `pickup_time` must be at least `transfer_min_notice_minutes` (default: 300) in the future
  - Charter legs: `pickup_date` + `pickup_time` must be at least `charter_min_notice_minutes` (default: 2880 / 48 hours) in the future
  - All legs: `pickup_date` + `pickup_time` must not exceed `max_advance_booking_days` (default: 365) in the future
- Charter legs additionally require:
  - `dropoff_time`
  - end time must be after start time on same day

### Security note
The preview endpoint uses `X-WP-Nonce` protection and verifies a WP REST nonce with action `wp_rest`.

### Current limitations
- Preview is validation-only and does not submit a real booking
- Booking-site handover is pending (v2 handover envelope dry-run foundation added)
- Google autocomplete is still pending
- Charter is preview-only; no real pricing yet
- Multi-day charter drag/drop not implemented

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
- `mode` is always `dry_run` during Phase 2
- `payload` is always the normalised and validated BookingPayload v2
- `integrity.signature` is computed with `hash_hmac('sha256', ...)`
- The signing secret is resolved in this order: constructor parameter → `WSB_CLIENT_V2_HANDOVER_SECRET` constant → dev fallback string when `WP_DEBUG` is true → empty string
- An empty secret produces an empty `signature` — this is intentional for local fixture tests that run outside WordPress
- Envelope production is deterministic: the same normalised payload with the same `request_id`, `created_at`, and `expires_at` produces the same `signed_fields` subset and the same HMAC signature

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
- The endpoint requires a valid `X-WP-Nonce` header (`wp_rest` action)
- No booking is created
- No booking token is created
- No booking-site API call is made
- No database records are created
- Secrets are never exposed to JavaScript