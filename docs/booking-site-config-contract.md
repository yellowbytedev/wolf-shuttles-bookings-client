# Booking-Site Configuration Contract

## Purpose

Define the configuration contract for pulling operational settings from the booking site into the marketing site's Booking Builder. This enables the marketing site to constrain the Booking Builder UI based on authoritative values while remaining a preview-only layer.

## Authority Model

| Configuration | Owner | Marketing Role | Booking Site Role |
|---------------|-------|----------------|-----------------|
| Lead times | Booking site | Cache + apply as constraints | Authoritative source |
| Max passengers | Booking site | Cache + constrain picker | Authoritative source |
| Luggage/baby-seat limits | Booking site | Cache + constrain pickers | Authoritative source |
| Date/time picker constraints | Booking site | Apply as min/max/step | Authoritative source |
| Global blockouts | Booking site | Apply to date/time pickers | Authoritative source |
| Vehicle blockouts | Booking site | Cache only (no picker effects) | Authoritative source |

**Key principle:** The marketing site never enforces configuration as the final authority. It provides helpful constraints and early warnings, but the booking site must always re-validate.

## Proposed Configuration Payload

```json
{
  "version": 1,
  "updated_at": "2026-06-29T08:00:00+02:00",
  "source": "booking_site",
  "lead_times": {
    "transfer_min_notice_minutes": 300,
    "charter_min_notice_minutes": 2880,
    "max_advance_booking_days": 90
  },
  "capacity": {
    "max_passengers": 13,
    "max_baby_seats": 4,
    "max_check_in_bags": 6,
    "max_carry_on_bags": 8
  },
  "picker": {
    "time_step_minutes": 5,
    "date_format": "Y-m-d"
  },
  "blockouts": {
    "global_blockouts_supported": true,
    "vehicle_scoped_blockouts_supported": true,
    "vehicle_scoped_blockouts_affect_marketing_picker": false
  },
  "cache": {
    "recommended_ttl_seconds": 14400
  }
}
```

## Field Definitions

### `version`
Integer version for future schema evolution.

### `updated_at`
ISO 8601 timestamp of last configuration change. Null if no endpoint available.

### `source`
Always `"booking_site"` to identify the authoritative source.

### `lead_times`

| Field | Type | Description |
|-------|------|-------------|
| `transfer_min_notice_minutes` | integer | Minimum advance booking for transfers. Current: 300 (5 hours) during business hours, 720 (12 hours) off-hours |
| `charter_min_notice_minutes` | integer | Minimum advance booking for charters. Current: 2880 (48 hours) |
| `max_advance_booking_days` | integer | Maximum future date selectable. Current: Not explicitly defined |

### `capacity`

| Field | Type | Description |
|-------|------|-------------|
| `max_passengers` | integer | Maximum passengers per vehicle/trip. Current: 13 |
| `max_baby_seats` | integer | Maximum baby seats. Current: No explicit limit |
| `max_check_in_bags` | integer | Maximum checked bags. Current: No explicit limit |
| `max_carry_on_bags` | integer | Maximum carry-on bags. Current: No explicit limit |

### `picker`

| Field | Type | Description |
|-------|------|-------------|
| `time_step_minutes` | integer | Time picker increment. Current: 5 |
| `date_format` | string | PHP date format for display. Current: `dd/mm/yy` in legacy, `Y-m-d` preferred for v2 |

### `blockouts`

| Field | Type | Description |
|-------|------|-------------|
| `global_blockouts_supported` | boolean | Marketing can apply global calendar constraints to date/time pickers |
| `vehicle_scoped_blockouts_supported` | boolean | Booking site supports vehicle-specific blockouts |
| `vehicle_scoped_blockouts_affect_marketing_picker` | boolean | Always false. Vehicle blockouts must not affect marketing pickers |

### `cache`

| Field | Type | Description |
|-------|------|-------------|
| `recommended_ttl_seconds` | integer | Time-to-live for cached config. Default: 14400 (4 hours) |

## Lead-Time Terminology

### Minimum Notice / Lead Time
- **Transfer:** Earliest allowed pickup time from "now"
- **Charter:** Earliest allowed pickup time from "now"
- Currently varies by time-of-day:
  - 04:00-21:00 → 5 hours
  - Other times → 12 hours

### Maximum Advance Booking Window
- Furthest future date that can be selected
- Currently undefined in legacy systems

### Time-of-Day Dependent Rules
Marketing should handle time-of-day lead time variations by:
1. Picking up the minimum notice value
2. Checking current time against booking site rules
3. Applying the appropriate window dynamically

## Route / Toll / Classification Ownership

### Route Distance/Duration
- **Booking site owns:** Real-time Google Distance Matrix lookups
- **Marketing site provides:** Place IDs, coordinates, optional snapshots
- **Booking site re-calculates:** All route distance/duration values

### Toll Detection
- **Booking site owns:** HERE Maps API toll detection
- **Marketing site provides:** Origin/destination coordinates
- **Booking site decides:** Toll fees and inclusion

### Direction/Angle Classification
- **Booking site owns:** Bearing calculations for dispatch/return fee decisions
- **Marketing site provides:** Coordinates, optional `place_snapshots`
- **Booking site re-calculates:** Direction classification (`toward`/`away`/`lateral`/`neutral`)

### Vehicle Cards Loading
When marketing site provides incomplete data (no distances yet):
- Booking site shows skeleton/vehicle cards while computing
- Booking site displays loading states until route data arrives

## Implementation Plan

### Phase 2O — Booking-Site Config Contract (Planning)

1. **Booking-site endpoint design (future)**
   - Endpoint: `GET /wp-json/booking-api/v1/config`
   - Returns: Configuration payload JSON
   - Auth: Public endpoint (no auth required for operational config)

2. **Marketing-side cached fetch service (future)**
   - Add to `WSB_Client_Booking_External_Services`:
     - `get_config(): array` — cached fetch
     - `get_config_scaffold(): array` — fallback defaults
   - Cache in options table (same pattern as blockouts)

3. **Fallback defaults if endpoint unavailable**
   - Return `get_config_scaffold()` values
   - Log warning but do not block form
   - Never fail silently on config errors

4. **Booking Builder field constraints**
   - Constrain `passengers` max attribute from `max_passengers`
   - Constrain `baby_seats` max from `max_baby_seats`
   - Constrain `check_in_bags` max from `max_check_in_bags`
   - Constrain `carry_on_bags` max from `max_carry_on_bags`

5. **Date picker constraints**
   - Apply `transfer_min_notice_minutes` as min attribute
   - Apply `max_advance_booking_days` as max attribute
   - Apply global blockouts to disable blocked dates
   - Time-of-day logic for dynamic min windows

6. **Time picker constraints**
   - Apply `time_step_minutes` as step attribute
   - Charter time windows (if provided): min/max on charter time fields

7. **Validation fixtures**
   - Add `configs/valid-defaults.json` fixture
   - Add `configs/invalid-partial.json` fixture
   - Extend `WSB_Client_Booking_Payload_V2_Validator` for config-aware checks

8. **Browser MCP QA**
   - Test date picker with constrained values
   - Test time picker with step intervals
   - Test blocked date disabling
   - Verify no JS errors on config load

## Current State

- No live endpoint implemented
- Configuration contract defined for future planning
- Legacy `max_passengers` sourced from SCF options (see `php/19-bricks-builder-custom.php`)
- Legacy lead times hardcoded in `php/15-submit-booking-form-and.php`
- Next implementation task: Marketing-side config consumer scaffold or booking-side endpoint planning