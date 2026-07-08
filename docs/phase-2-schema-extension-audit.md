# Phase 2 — BookingPayload v2 Schema Extension Audit

**Date:** 2026-06-28
**Branch:** `feature/phase-2h-v2-handover-foundation`
**Scope:** Audit of BookingPayload v2 and handover schema for route, vehicle availability, multi-booking, and charter scaffolding.

---

## 1. Route / Google Routes Scaffolding

### 1.1 Current situation

The canonical payload shape documented in `docs/booking-payload-v2.md` includes a top-level `route` block:

```json
"route": {
  "place_ids": [],
  "toll_gates": [],
  "route_options": []
}
```

And each leg has a per-leg `route` field (empty object by default).

The legacy seed fixture file (`tests/fixtures/booking-intake-fixtures.v2.seed.json`) already carries real route data: `place_ids` (Google Place ID strings), `toll_gates` (JSON-encoded arrays like `"[null]"` or `"[\"huguenot\"]"`), and `route_options` (currently always `[]`).

### 1.2 What the normalizer does

- **Legs from structured payload** (`normalize_legs` via `normalize_leg_from_array`, line 168): preserves whatever `route` data was passed in, as long as it is an array.
- **Legs from flat marketing form** (`normalize_leg_from_flat_fields`, line 207): always sets `'route' => array()` — route data is lost.
- **Top-level `route` field**: Completely absent from the normalizer output. The normalizer's `normalize()` method (lines 24–44) never reads or writes a top-level `route` block.

### 1.3 What the JS sends

The JS `buildPayload()` function (line 192) sets `route: {}` on each leg. It never sends a top-level `route` object. Location objects include `place_id`, `coords.lat`, `coords.lng`, `formatted_address` — Google Places scaffolding exists in the location sub-object but is not yet populated by autocomplete.

### 1.4 What the validator does

The validator does not validate route data at all. No checks for `place_ids`, `toll_gates`, `route_options`, or per-leg `route`.

### 1.5 What the handover envelope does

The handover envelope wraps the full normalized payload (`payload => $payload`). Whatever route data survived normalization is passed through; data lost in normalization cannot be recovered.

### 1.6 Finding: ROUTE_TOP_LEVEL_MISSING

The top-level `route` block defined in the canonical docs is silently dropped by the normalizer and never sent by the JS. The legacy seed fixtures carry rich route data that has no path into the v2 normalizer output.

## 2. Vehicle Availability / Blocking Scaffolding

### 2.1 Current situation

There is zero vehicle or availability scaffolding anywhere in the v2 payload:

| Field | Status |
|-------|--------|
| `vehicle` / `available_vehicles` | Not present |
| `vehicle_id` / `selected_vehicle_id` | Not present |
| `vehicle_type` / `vehicle_category` | Not present |
| `capacity` / `capacity_required` | Not present |
| `blocking` / `availability` | Not present |
| `route_options` | Placeholder only, no processing |

The roadmap (Phase 5) mentions `selected_vehicle_id` as a cart line item field, but the intake payload has no vehicle-preference or capacity-requirement scaffolding.

### 2.2 Risk

Vehicle assignment is deferred entirely to the booking site. This is architecturally correct (marketing site does not own pricing or fleet logic), but the current payload has **no way to communicate vehicle capacity requirements** (e.g., "this booking needs a vehicle with 4+ passenger seats and a trailer hitch"). Without this, the booking site must re-request information or make assumptions.

### 2.3 Finding: VEHICLE_SCAFFOLDING_ABSENT

**Impact:** Medium-term. The booking site can still own vehicle selection, but the intake payload should signal minimal capacity requirements (`passengers`, `trailer`, `oversize_luggage` partially cover this). No vehicle-preference, vehicle-blocking, or capacity-requirement fields exist.

---

## 3. Multi-Booking / Itinerary Signalling

### 3.1 Current situation

The payload has no fields to signal that it represents a single trip within a future multi-trip itinerary:

| Field | Status |
|-------|--------|
| `itinerary_id` / `trip_group` / `parent_id` | Not present |
| `trip_index` / `total_trips` | Not present |
| `booking_session` | Not present |
| `itinerary_meta` | Not present |

The roadmap (Phase 4) describes an itinerary parent table, but the payload has no scaffolding to carry itinerary context from the marketing site.

### 3.2 What exists

- The `tracking` object is generic and could absorb session IDs.
- The `meta` object exists but carries only `handover_mode` and `created_at`.

### 3.3 Finding: ITINERARY_SIGNALLING_ABSENT

**Impact:** Low right now (Phase 4 is not yet started), but the payload will need a backward-compatible way to carry an `itinerary_id` once the parent model exists. The `tracking` field could serve as a temporary carrier.

---

## 4. Charter Scaffolding

### 4.1 Current situation

The naming conventions and canonical docs define:

- `service_group`: `transfer` or `charter`
- `service_type`: includes `charter_hire`
- `trip_type`: includes `charter`
- `charter`: `null` at top level (placeholder)

### 4.2 What the JS sends

The JS `buildPayload()` includes `service_group` (inferred from service type), `service_type`, and `trip_type`. The JS does NOT send a `charter` field.

### 4.3 What the normalizer does

- Accepts `charter` as a `trip_type` (line 21).
- Accepts `charter_hire` as a `service_type` (line 22).
- **Drops `service_group`** entirely from output.
- **Drops `charter`** field entirely from output.
- **Does not include** any charter-specific fields: multi-day date ranges, multiple destinations, accommodation, event information.

### 4.4 What the fixtures cover

- The new v2 fixture corpus (`booking-payload-v2-fixtures.json`) has **zero charter fixtures**.
- The legacy seed fixtures have charter fixtures with `service_group: "charter"`, `trip_type: "charter"`, `service_type: "charter_hire"`, and real route/toll data — but these cannot pass through the v2 normalizer without data loss.

### 4.5 Finding: CHARTER_SCAFFOLDING_INCOMPLETE

The legs model (`sequence`, `leg_group`, `leg_type`) is flexible enough to represent multi-day charter legs, but:

1. `service_group` is dropped by the normalizer.
2. `charter` top-level block is dropped.
3. No charter-specific fields exist (e.g., `charter.date_range`, `charter.accommodation`, `charter.event_type`, `charter.daily_itinerary`).
4. No charter v2 fixtures exist.
5. The validator accepts `trip_type: charter` but has no charter-specific rules.

**Impact:** Low now (charter UI is not implemented), but the scaffolding that does exist (`service_group`, `charter`) is actively stripped by the normalizer.

---

## 5. Additional Stop Consistency

### 5.1 Current situation

Additional stops are represented consistently across all layers:

| Layer | Representation | Status |
|-------|---------------|--------|
| **Canonical doc** | `stops[{ type, location { label, name, town, neighbourhood, place_id, coords, formatted_address } }]` | Defined |
| **JS browser** | `outboundLeg.stops.push({ type: 'additional_stop', location: textLocation(additionalStop) })` | Consistent |
| **JS fixture drawer** | Loads stop data from fixture and populates leg `stops[]` | Consistent |
| **Normalizer (legs[])** | Reads `raw_leg['stops']`, normalizes each stop's location (line 143-158) | Preserves |
| **Normalizer (flat)** | Creates a single stop from `additional_stop_enabled` + `additional_stop` (line 189-197) | Consistent |
| **Validator** | Does not validate stop data at all | Permissive (acceptable) |
| **Fixtures** | `valid-one-way-additional-stop` uses `stops[{ type, location { label } }]` | Present |
| **Handover** | Envelope wraps payload -> stops pass through | Preserved |

### 5.2 Finding: STOP_REPRESENTATION_CONSISTENT

**Impact:** None. Additional stops are well-aligned. The only minor gap is that the JS `textLocation()` in the browser does not populate `name`, `town`, `neighbourhood`, `place_id`, or `coords` -- these are zero-value placeholders awaiting Google Places integration. The normalizer accepts richer location data when present.

---

## 6. Normalizer Payload Shape -- Gap Analysis vs Canonical Docs

Comparing the normalizer `normalize()` output (lines 24-44) against the canonical shape in `docs/booking-payload-v2.md` (lines 48-88):

| Canonical field | In normalizer output? | In JS output? | Notes |
|----------------|----------------------|---------------|-------|
| `schema_version` | Yes `'2.0'` | Yes `'2.0'` | |
| `source` (object) | No -- stored as flat string | No -- stored as flat string | Doc shows `{ site, channel, page_url, referrer }` but normalizer/JS use `sanitize_key('marketing_booking_builder')` |
| `service_group` | No -- **Dropped** | Yes -- Sent | Doc includes it; normalizer ignores it |
| `service_type` | Yes | Yes | |
| `trip_type` | Yes | Yes | |
| `customer` | Yes `{ name, email, phone }` | Yes `{ name, email, phone }` | |
| `passengers` | Yes | Yes | |
| `baby_seats` (top-level) | Yes | Yes | Doc shows `baby_seats` at top and in `add_ons.baby_seats` -- duplication exists |
| `luggage` | No -- **Flattened** | No -- **Flattened** | Doc shows `luggage: { check_in_bags, carry_on_bags }` but normalizer/JS use flat `check_in_bags`, `carry_on_bags` |
| `add_ons.trailer` | Yes | Yes | |
| `add_ons.oversize_luggage` | Yes | Yes | |
| `add_ons.baby_seats` | No -- Not in normalizer | No -- Not in JS | Doc shows it but neither layer outputs it |
| `legs` | Yes | Yes | |
| `route` (top-level) | No -- **Dropped** | No -- Not sent | Doc shows top-level `route` block |
| `tracking` | Yes | Yes | |
| `validation_flags` | No -- **Dropped** | Yes -- Sent but empty | Doc includes it |
| `meta.handover_mode` | Yes (as `meta.handover_mode`) | No -- Sends `meta.preview_only` | Mismatch between JS and PHP field name |
| `meta.created_at` | Yes | Yes | |
| `charter` | No -- **Dropped** | No -- Not sent | Doc includes `charter: null` |

### 6.1 Doc references that are stale

1. `docs/booking-payload-v2-contract.md` line 14 lists `service_group` as canonical -- but the normalizer drops it.
2. `docs/booking-payload-v2-contract.md` lines 27-28 list `route` and `charter` as canonical -- but neither is in the normalizer output.
3. `docs/booking-payload-v2.md` lines 22-30 list `baby_seats` in `add_ons` -- but `add_ons.baby_seats` is not in the normalizer output.

---

## 7. Recommended Payload Additions

These are additive only -- no existing fields should be removed.

### 7.1 Route / Google Routes

Add to top-level payload (normalizer output):

```json
"route": {
  "place_ids": [],
  "toll_gates": [],
  "route_options": [],
  "distance_meters": null,
  "duration_seconds": null,
  "polyline": ""
}
```

This should be:
- Populated by a future Google Routes middleware step (after normalization, before handover)
- Preserved through to handover
- Never required in validation

### 7.2 Vehicle capacity requirements

Add to top-level payload:

```json
"requirements": {
  "min_passenger_capacity": null,
  "needs_trailer_hitch": false,
  "needs_oversize_space": false,
  "wheelchair_accessible": false
}
```

This signals capacity needs without dictating vehicle selection.

### 7.3 Itinerary / multi-booking scaffolding

Add to `meta` block:

```json
"meta": {
  "itinerary_id": null,
  "trip_index": null,
  "booking_session": ""
}
```

Initially all null when no itinerary context exists; populated when Phase 4 starts.

### 7.4 Charter-specific fields

Expand the top-level `charter` block:

```json
"charter": null
```

Future expansion:

```json
"charter": {
  "start_date": "",
  "end_date": "",
  "event_type": "",
  "accommodation": "",
  "daily_schedule": [],
  "special_requirements": ""
}
```

The legs model already supports multi-leg charters. The `charter` block carries booking-level metadata.

### 7.5 Normalizer alignment

Add to normalizer output:

| Field | Source | Notes |
|-------|--------|-------|
| `service_group` | Pass through from raw, or infer from service_type | JS already sends it |
| `route` (top-level) | Pass through from raw | For future Google Routes data |
| `validation_flags` | Pass through from raw | JS already sends it |
| `charter` | Pass through from raw | Future |
| `meta.handover_mode` | Already present -- keep | Align JS `meta.preview_only` -> `meta.handover_mode` |

---

## 8. Recommended Handover Additions

### 8.1 Current envelope

The handover envelope already passes the full normalized payload. No envelope-structural changes are needed unless the booking site requires:
- A `vehicle` block in the envelope header for fleet routing hints.
- Route summary data copied to envelope top-level for quick inspection.

### 8.2 Recommended: route_summary in envelope meta

```json
"meta": {
  "route_summary": {
    "total_legs": 0,
    "has_tolls": false,
    "total_distance_meters": null
  }
}
```

### 8.3 Recommended: vehicle_request in envelope meta

```json
"meta": {
  "vehicle_request": {
    "min_passengers": 0,
    "needs_trailer": false,
    "needs_oversize": false
  }
}
```

---

## 9. Recommended Fixture Additions

### 9.1 Route scaffolding fixtures

- `valid-one-way-with-route-data` -- payload with populated `place_ids`, `toll_gates`, per-leg `route` data
- `valid-return-with-route-data` -- return trip with route metadata on both legs

### 9.2 Charter fixtures

- `valid-charter-minimal` -- `trip_type: charter`, `service_type: charter_hire`, single outbound leg, `charter: null`
- `valid-charter-multi-day` -- multi-leg charter with `charter` block populated

### 9.3 Multi-booking fixtures

- `valid-with-itinerary-id` -- payload with `meta.itinerary_id` set
- `valid-with-booking-session` -- payload with `meta.booking_session` set

### 9.4 Capacity requirement fixtures

- `valid-with-requirements` -- payload with `requirements` block

### 9.5 Edge-case stops fixtures

- `valid-additional-stop-full-location` -- stop with `place_id`, `coords`, `formatted_address`
- `valid-multiple-stops` -- two additional stops on one leg (scaffold for future multi-stop support)

---

## 10. Risk Notes

### 10.1 Medium risks

1. **Normalizer data loss**: The normalizer silently drops `service_group`, top-level `route`, `validation_flags`, and `charter`. These fields exist in the canonical docs or are sent by the JS but are absent from normalized output. Any downstream code reading the normalized payload will not see them.

2. **JS/PHP field name mismatch**: `meta.preview_only` vs `meta.handover_mode` will cause confusion when the JS preview payload is compared with the server-normalized payload.

3. **Legacy seed fixture obsolescence**: The legacy seed fixture has rich route, toll, and charter data but no v2 normalizer path to preserve it. When the legacy flow is retired, this data lineage must be reproduced in the v2 normalizer.

### 10.2 Low risks

4. **`add_ons.baby_seats` doc inaccuracy**: Documented but not implemented in any layer. Fix by removing from doc or adding to all layers.
5. **`luggage` nested shape doc inaccuracy**: Documented but flattened in normalizer and JS. Align doc with implementation or restructure.
6. **Charter validation gap**: Validator accepts `trip_type: charter` but has no charter-specific rules. A charter payload with zero legs would pass through to handover.

### 10.3 Non-risks (acknowledged good design)

7. **Vehicle selection deferred to booking site**: Correct. Marketing site should not own pricing or fleet logic.
8. **Validator permissive on optional metadata**: Correct. Route, charter, tracking, stops should not be required for a valid payload.
9. **Handover envelope structure**: Clean. No changes needed unless the booking site requests new envelope-level fields.

---

## 11. Suggested Small Implementation Tasks

These are ordered by dependency and risk. Each should be a separate small commit.

### Task 1: Align normalizer with canonical doc shape

**Files:** `inc/class-booking-payload-v2-normalizer.php`

Add pass-through or default-initialization for:
- `service_group` (infer from `service_type` or pass through from raw)
- `route` (top-level, pass through or empty default)
- `validation_flags` (pass through or empty default)
- `charter` (pass through or null)

**Result:** Normalized payload matches canonical doc shape. JS `service_group` and `validation_flags` are preserved.

### Task 2: Add top-level route scaffolding to normalizer

**Files:** `inc/class-booking-payload-v2-normalizer.php`

Add `route { place_ids, toll_gates, route_options }` to the normalize output, pass-through from raw input.

### Task 3: Align JS meta field name with PHP

**Files:** `assets/js/booking-client-form.js`

Change `meta.preview_only: true` to `meta.handover_mode: 'preview_only'`.

### Task 4: Add charter v2 fixtures

**Files:** `tests/fixtures/booking-payload-v2-fixtures.json`

Add 2-3 charter fixtures (minimum valid, multi-leg, with `charter: null`).

### Task 5: Add route-aware v2 fixtures

**Files:** `tests/fixtures/booking-payload-v2-fixtures.json`

Add 2 fixtures with populated `place_ids`, `toll_gates`, and per-leg `route` data.

### Task 6: Correct doc references

**Files:** `docs/booking-payload-v2.md`, `docs/booking-payload-v2-contract.md`

Remove or update inaccurate references to:
- `add_ons.baby_seats` (not in normalizer)
- `luggage` nested container (flattened in normalizer)
- `service_group` as canonical (ensure it's restored first via Task 1)

### Task 7: Add capacity requirements scaffolding (optional)

**Files:** `inc/class-booking-payload-v2-normalizer.php`

Add minimal `requirements` block to normalized output. Keep it optional.

### Task 8: Add itinerary scaffolding (optional, Phase 4 prep)

**Files:** `inc/class-booking-payload-v2-normalizer.php`

Add `meta.itinerary_id` and `meta.booking_session` pass-through fields.

---

## 12. Summary Table

| # | Question | Finding | Status |
|---|----------|---------|--------|
| Q1 | Route/Google Routes scaffolding | Partially present. Per-leg `route` preserved; top-level `route` dropped by normalizer. Location objects have Places fields but populate none yet. | Partial gap |
| Q2 | Normalizer preserves route metadata? | Partially. Structured leg input preserved; flat-field input reset to `{}`. Top-level route dropped. | Partial gap |
| Q3 | Validator allows route metadata? | Yes, completely permissive. No route-related validation. | Good |
| Q4 | Handover preserves route metadata? | Yes, envelope wraps normalized payload as-is. Whatever survives normalization passes through. | Good |
| Q5 | Vehicle availability/blocking? | Zero scaffolding. No fields exist. | Missing |
| Q6 | Handover preserves vehicle context? | N/A -- nothing to preserve. | Missing |
| Q7 | Multi-booking signalling? | None. No itinerary_id, trip_group, or booking_session fields. | Missing |
| Q8 | Charter path? | Partial. `charter: null` placeholder, legs model works. But normalizer drops `service_group` and `charter` block. No charter fixtures. | Partial gap |
| Q9 | Additional stop consistency? | Consistent across all layers. Minor: JS location object has zero-value fields awaiting Places integration. | Good |


