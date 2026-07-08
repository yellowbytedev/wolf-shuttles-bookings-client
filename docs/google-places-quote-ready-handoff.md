# Google Places Quote-Ready Handoff

## Overview

For production quote-ready handoff, Google Places autocomplete selection is mandatory for all route endpoints. This ensures the booking side receives accurate place_id, coordinates, and address snapshots needed for route calculation and pricing.

## Why Google Places Selection is Mandatory

The V3 booking flow depends on accurate Google Places place snapshots. Typed free-text addresses are insufficient because:

1. **Place ID**: Required for reliable geocoding and route calculation
2. **Coordinates**: Needed for distance computation and pricing
3. **Address snapshot**: Ensures consistency between displayed address and stored data
4. **Provider source**: Identifies the origin of the location data

Marketing captures form data and Google Places snapshots. Booking owns route alternatives, route calculation, tolls, distance, classification, pricing, vehicle availability, and Woo/session/cart/order logic.

## Required Google Places Snapshots

### Transfer One-Way
- `legs[0].from` — origin must have valid place snapshot
- `legs[0].to` — destination must have valid place snapshot

### Transfer Return
- `legs[0].from` — outbound origin
- `legs[0].to` — outbound destination
- `legs[1].from` — return origin
- `legs[1].to` — return destination

### Charter
- `legs[0].from` — pickup location
- `legs[0].to` — dropoff location

### Additional Stops
If an additional stop is enabled and has a label, it must also have a valid place snapshot.

## Valid Snapshot Requirements

A valid snapshot must include:

| Field | Type | Required |
|-------|------|----------|
| `provider` | string | Yes (must be "google_places") |
| `place_id` | string | Yes (Google Place ID) |
| `label` | string | Yes (display name) |
| `formatted_address` | string | No (if available) |
| `lat` | number | Yes (latitude) |
| `lng` | number | Yes (longitude) |
| `captured_at` | string | No (ISO timestamp) |
| `stale` | boolean | No (defaults false) |

## Stale Snapshot Invalidation

If a user selects a Google Places result and then manually edits the field:

1. The field wrapper gets CSS class `wsb-booking-client-field--place-stale`
2. The old `place_id` is ignored for submit readiness
3. User sees warning: "Location was edited after selection. Please select a place again."
4. Submit remains blocked until a new autocomplete selection is made

Stale snapshots are marked via `stale: true` in the place_snapshots structure.

## Production vs Local/Debug Behaviour

- **Production**: `requiredForQuoteReady: true` — free-text-only addresses block submit
- **Local/Debug** (`?debug=1` or `GOOGLE_API_KEY` unavailable): Allows preview/testing without strict enforcement
- **Environment gate**: Uses `wp_get_environment_type()` in PHP for secret resolution

## Validation Flags

`validation_flags.google_place_snapshots_ready` indicates quote-ready status:

- `true` — All required endpoints have valid Google Places snapshots
- `false` — One or more required endpoints missing place_id, coordinates, or marked stale

The validator adds an error when:
- Required `place_id` is missing
- Coordinates (`lat`/`lng`) are missing
- Snapshot is marked `stale: true`
- Required enabled stop has no place snapshot

## Route-Related Data Preservation

Marketing preserves route-related fields for future booking-side route alternatives:

- `route.route_options[]` — Array of route options if user has preferences
- `route.provider` — Route data origin (null until booking side computes)
- `route.selected_route_id` — Selected route identifier
- `route.selected_route_label` — Human-readable route label
- `route.distance_meters` — Distance (null until booking side computes)
- `route.duration_seconds` — Duration (null until booking side computes)
- `route.polyline` — Encoded route geometry

**Important**: Marketing must NOT:
- Calculate final distance
- Calculate tolls
- Calculate final route price
- Decide final route applicability
- Cache route results as pricing authority

## What Remains Booking-Side Authority

| Domain | Authority Owner | Marketing Role |
|--------|-----------------|--------------|
| Route alternatives | Booking | Provides place_ids as input |
| Distance calculation | Booking | Provides coordinates as input |
| Toll detection | Booking | Provides route context |
| Direction classification | Booking | N/A |
| Vehicle selection | Booking | Provides passenger count |
| Pricing | Booking | N/A |
| Availability | Booking | N/A |
| WooCommerce cart | Booking | N/A |

## Submit Blocking Behaviour

On final submit:
1. Check `placeSnapshots` object for required fields
2. If any required endpoint lacks valid snapshot:
   - Block handoff
   - Show error: "Please select the address from the dropdown so we can calculate your route accurately."
   - Show which fields are missing in the message
3. Do not submit a signed handover envelope until location readiness passes

## Payload Flow

```
Marketing form
→ buildPayload() with placeSnapshots
→ validator.validate() checks place_id + lat/lng + provider
→ If valid, handover service builds envelope
→ POST to booking-site /wp-json/ws-bookings/v2/intake
```

## Files Involved

- `inc/class-booking-payload-v2-normalizer.php` — Normalizes place_snapshots with captured_at/stale fields
- `inc/class-booking-payload-v2-validator.php` — Validates Google Places snapshot completeness
- `assets/js/booking-client-form.js` — Place selection, stale detection, submit blocking
- `tests/fixtures/booking-payload-v2-fixtures.json` — Test cases for validation scenarios