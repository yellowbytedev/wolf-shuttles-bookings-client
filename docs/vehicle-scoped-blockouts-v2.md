# Vehicle-Scoped Blockouts v2

## Purpose

This document describes the diagnostic blockout scaffold added to BookingPayload v2. It prepares the data contract for future vehicle-scoped blockout logic on the booking site.

## Authority Model

| Blockout Type | Who Evaluates | Who is Authoritative |
|---------------|---------------|----------------------|
| Global full-day | Marketing (date picker) | Booking site (final) |
| Global time-range | Marketing (time picker) | Booking site (final) |
| Vehicle-specific full-day | Marketing (no picker change) | Booking site |
| Vehicle-specific time-range | Marketing (no picker change) | Booking site |

## Marketing Site Behavior

- The marketing site **does not** disable date/time pickers based on vehicle-specific blockouts.
- The marketing site includes a diagnostic `blockouts` scaffold in the payload.
- The `blockouts.authority` is always `"booking_site"`.
- The `blockouts.marketing_evaluates_vehicle_availability` is always `false`.

## Booking Site Responsibilities (Future)

When the booking site receives a payload with `blockouts`:

1. Evaluate vehicle availability for each leg against WooCommerce product blockouts.
2. Check global calendar blockouts and apply to the marketing date picker if needed.
3. For add-to-cart/checkout: re-check vehicle availability server-side against product variations.

## Payload Scaffold

```json
"blockouts": {
  "version": 2,
  "authority": "booking_site",
  "marketing_evaluates_vehicle_availability": false,
  "vehicle_scoped_blockouts_supported": true,
  "global_picker_blockouts_supported": true,
  "config_hash": null,
  "marketing_evaluated_at": null,
  "notes": []
}
```

## Field Meanings

- `version` — Scaffold version number.
- `authority` — Always `"booking_site"`; marketing never overrides.
- `marketing_evaluates_vehicle_availability` — Always `false`.
- `vehicle_scoped_blockouts_supported` — Always `true` to signal support.
- `global_picker_blockouts_supported` — Always `true` to signal support.
- `config_hash` — Optional; for future config versioning.
- `marketing_evaluated_at` — Optional; for future timestamping.
- `notes` — Optional; freeform array for diagnostics.

## Implementation Status

- Added to `buildPayload()` in JS for local preview.
- Added to `normalize_blockouts()` in PHP normalizer.
- Payload fixtures include optional populated scaffold for testing.
- No actual blockout lookup is performed in Phase 2.