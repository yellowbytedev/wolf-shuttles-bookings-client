# BookingPayload v2 Contract

## Purpose
BookingPayload v2 is the canonical marketing-site booking intake shape for the Booking Builder.

## Naming decisions
Use these terms:
```text
schema_version: "2.0"
service_group: transfer | charter
service_type: airport_pickup | airport_dropoff | city_transfer | charter_hire
trip_type: one_way | return | charter
```
Avoid `service_family`.

## Core payload shape
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

## Field rules
- `schema_version` — must be `"2.0"`
- `service_group` — optional (defaults to `"transfer"`); inferred from `service_type` if not provided
- `service_type` — canonical: `airport_pickup`, `airport_dropoff`, `city_transfer`, `charter_hire`
- `trip_type` — canonical: `one_way`, `return`, `charter`
- `route` — optional; scaffold-only; no Google call yet. Fields: `provider`, `selected_route_id`, `selected_route_label`, `distance_meters`, `duration_seconds`, `polyline`, `route_options`
- `charter` — optional; preview-only UI now active; no real charter pricing yet. Fields: `enabled` (bool), `type` (string|null), `days` (array)
- `validation_flags` — optional; freeform object (defaults to `{}`)
- `meta.preview_only` — always `true` in preview mode
- `meta.handover_mode` — `"preview"` for dry-run, `"live"` for real handover
- All other fields — as documented in `docs/booking-payload-v2.md`

## Transfer leg shape
```json
{
  "type": "outbound",
  "from": { "label": "Origin location" },
  "to": { "label": "Destination location" },
  "pickup_date": "2026-07-15",
  "pickup_time": "14:00",
  "pickup_datetime": "2026-07-15 14:00",
  "stops": [],
  "route": {},
  "place_snapshots": {
    "from": { "provider": null, "place_id": null, "label": null, "formatted_address": null, "lat": null, "lng": null },
    "to": { "provider": null, "place_id": null, "label": null, "formatted_address": null, "lat": null, "lng": null },
    "stops": []
  }
}
```

## Charter leg shape (Phase 2K+)
Same-day charter legs include an additional `dropoff_time` field and scaffold for `place_snapshots`:
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

## Charter days shape
When `charter.enabled: true`, the `days[]` array contains:
```json
{
  "day_index": 0,
  "date": "2026-08-15",
  "start_time": "09:00",
  "end_time": "17:00",
  "pickup_location": { "label": "Pickup location" },
  "dropoff_location": { "label": "Drop-off location" },
  "stops": []
}
```

## Normalizer behaviour
The normalizer (`WSB_Client_Booking_Payload_V2_Normalizer`) now:
- Preserves `service_group` or infers from `service_type`
- Preserves top-level `route` with safe empty scaffold when missing
- Preserves `charter` with disabled-by-default scaffold when missing, or builds charter days from charter legs
- Preserves `validation_flags` or defaults to `{}`
- Aligns meta: sets both `preview_only` and `handover_mode`

## Validator behaviour
The validator (`WSB_Client_Booking_Payload_V2_Validator`) now:
- Accepts `charter` leg type
- Requires `dropoff_time` for charter legs
- Validates end time > start time for charter legs
- All transfer validation rules unchanged

## Fixtures
The fixture corpus in `tests/fixtures/booking-payload-v2-fixtures.json` includes:
- `valid-with-route-scaffold` — empty top-level `route`
- `valid-with-route-options` — `route.route_options[]` populated
- `valid-with-validation-flags` — `validation_flags` populated
- `valid-with-charter-scaffold` — `charter.enabled: false` (updated to full charter leg)
- `invalid-missing-legs` — zero legs
- `valid-same-day-charter` — valid charter with pickup and dropoff times
- `valid-same-day-charter-with-trailer` — valid charter with trailer enabled
- `valid-with-place-snapshots` — one-way transfer with mock Google place snapshots
- `valid-return-with-place-snapshots` — return trip with place snapshots on both legs
- `invalid-charter-missing-dropoff` — missing dropoff_time
- `invalid-charter-end-time-before-start` — end time before start time

Total fixtures: 29

Valid fixtures (pass validation): 18
Invalid fixtures (intentionally fail validation): 11