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
- `charter` — optional; scaffold-only; no charter UI yet. Fields: `enabled` (bool), `type` (string|null), `days` (array)
- `validation_flags` — optional; freeform object (defaults to `{}`)
- `meta.preview_only` — always `true` in preview mode
- `meta.handover_mode` — `"preview"` for dry-run, `"live"` for real handover
- All other fields — as documented in `docs/booking-payload-v2.md`

## Normalizer behaviour
The normalizer (`WSB_Client_Booking_Payload_V2_Normalizer`) now:
- Preserves `service_group` or infers from `service_type`
- Preserves top-level `route` with safe empty scaffold when missing
- Preserves `charter` with disabled-by-default scaffold when missing
- Preserves `validation_flags` or defaults to `{}`
- Aligns meta: sets both `preview_only` and `handover_mode`

## Validator behaviour
The validator (`WSB_Client_Booking_Payload_V2_Validator`) accepts these scaffolds without error:
- `route` metadata is optional (no route validation)
- `charter` scaffold is optional (no charter validation)
- `validation_flags` is optional
- `service_group` is optional (defaults safely)

## Fixtures
The fixture corpus in `tests/fixtures/booking-payload-v2-fixtures.json` includes:
- `valid-with-route-scaffold` — empty top-level `route`
- `valid-with-route-options` — `route.route_options[]` populated
- `valid-with-validation-flags` — `validation_flags` populated
- `valid-with-charter-scaffold` — `charter.enabled: false`
- `invalid-missing-legs` — zero legs