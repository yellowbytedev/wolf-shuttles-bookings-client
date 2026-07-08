# M5A — Multi-Trip Booking Builder Shell Plan

## 0. Status

Documentation and implementation plan only.

- No runtime/UI code is added by M5A.
- The `enable_multi_trip_bookings` gate remains `false` in every environment.
- The canonical ordering payload field is **`sort_order`** (per the master plan and `booking-payload-v2` contract). M5A does **not** introduce `display_order` as a new payload field; "display order" / "planning order" is UX wording only.

---

## 1. Multi-Trip Card Model

Each trip in the builder is represented by a card backed by a stable client-side model.

| Field | Type | Source | Payload | Notes |
|---|---|---|---|---|
| `trip_id` | string | client-generated | `itinerary.trips[].trip_id` | Stable ID; never reused after delete. |
| `sort_order` | integer | client-assigned | `itinerary.trips[].sort_order` | Manual planning/display order. Canonical ordering field. |
| `trip_type` | enum | user selection | `itinerary.trips[].trip_type` | Existing compatibility field; intent only. |
| `date` | date (ISO `YYYY-MM-DD`) | user input | `itinerary.trips[].date` | Trip date; stays attached to the trip. |
| `time` | time (`HH:mm`) | user input | `itinerary.trips[].time` | Trip time; stays attached to the trip. |
| `pickup` | object | user input + Places | `itinerary.trips[].legs[].pickup_location` | Exposed in the UI card model as a simple field; normalised into the leg payload shape (`place_snapshots.from`). |
| `drop-off` | object | user input + Places | `itinerary.trips[].legs[].dropoff_location` | Exposed in the UI card model as a simple field; normalised into the leg payload shape (`place_snapshots.to`). |
| `passengers` | object | user input | `itinerary.trips[].passengers` | Intent only. |
| `luggage` | object | user input | `itinerary.trips[].luggage` | Intent only. |
| `notes` | string | user input | `itinerary.trips[].notes` | Sensitive; do not log. |
| Google Places snapshots | object | Google Places | `itinerary.trips[].legs[].place_snapshots` | Exposed in the UI card model; normalised into the leg payload shape. Required per active endpoint when `enable_google_places_required` is on. |

Card model rules:

- Deleting a trip must not reuse its `trip_id`.
- Duplicating a trip must assign a new `trip_id`.
- Reordering updates `sort_order` only.
- Collapse/expand state is UI state and must not be submitted in the payload.
- `date` and `time` are always bound to the trip; reordering never moves them.

---

## 2. UX Terminology

- The user experiences `sort_order` as **manual display / planning order**.
- Do **not** call this a pricing order or a routing order.
- Marketing remains **intent-only**: it captures where/when the customer wants to travel, not the price, vehicle, route, or availability.
- Booking side remains authoritative for route calculation, distance, duration, tolls, classification, vehicle availability, pricing, WooCommerce session/cart/order.

---

## 3. Card Actions

Required actions:

- **Add trip** — appends a new empty trip card with a fresh `trip_id` and the next `sort_order`.
- **Duplicate trip** — copies labels, date/time, passenger/luggage values, notes, and Google Places snapshots (only while address labels are unchanged); assigns a new `trip_id`; keeps the same `sort_order` slot or re-sequences as designed.
- **Delete trip** — removes the card; never reuses its `trip_id`.
- **Collapse / expand** — per-card accordion toggle; keyboard accessible; label switches between Open/Close.
- **Collapse all / expand all** — bulk accordion control.
- **Move up / down** — required fallback for accessibility and for environments where drag/drop is disabled.
- **Drag/drop (future)** — pointer reorder, enhancement only, gated by `enable_drag_drop_itinerary_ordering`; move up/down must remain available.

---

## 4. Drag/Drop Rule

Aligned with `docs/ux-drag-drop-behaviour-rules.md` (UX-DND-001 §3).

- Drag/drop controls **manual planning / display order only**.
- Each trip keeps its own `date`/`time` values unchanged.
- If the visible order (by `sort_order`) conflicts with chronological order (by `date`/`time`), show a warning:

  > Trip 2 occurs before Trip 1.

- Include an action:

  > Arrange chronologically

- **Arrange chronologically updates `sort_order` only.** It must never change `date` or `time` values.
- The chronology warning state is advisory UI metadata, not authoritative booking data.

---

## 5. Future Payload Shape

### 5.1 UI / card model vs canonical payload model

These are two distinct representations:

- **UI / card model** — what the user sees and edits in the builder. A trip card may expose simple, user-friendly fields: `pickup`, `drop-off`, `date`, `time`, `passengers`, `luggage`, `notes`. This is the editing surface only.
- **Canonical payload model** — what marketing submits. It uses `itinerary.trips[]`, where each trip carries `sort_order` and its route data normalises into `legs[]`. Each leg carries the `pickup`/`drop-off` `place_snapshots` (and `pickup_date`/`pickup_time` where applicable), exactly as defined in the master plan (`plans/revised-marketing-feature-gate-multiday-plan.md` §6.5) and the reserved multi-trip fixtures.

The card model's simple `pickup`/`drop-off`/`date`/`time` values are mapped into the canonical `legs[]` shape during normalisation. The card model is never the submitted contract.

### 5.2 Canonical payload example

Preserve reserved multi-trip metadata. Use `itinerary.trips[]`, `sort_order`, and `legs[]`.

```json
{
  "schema_version": "2.0",
  "itinerary_type": "multi_trip",
  "itinerary": {
    "trips": [
      {
        "trip_id": "trip_1",
        "sort_order": 10,
        "trip_type": "one_way",
        "date": "2026-08-02",
        "time": "08:00",
        "passengers": { "total": 3 },
        "luggage": { "check_in_bags": 0, "carry_on_bags": 0 },
        "notes": "",
        "legs": [
          {
            "leg_id": "leg_1",
            "pickup_date": "2026-08-02",
            "pickup_time": "08:00",
            "pickup_location": { "label": "Stellenbosch" },
            "dropoff_location": { "label": "Cape Town Airport" },
            "place_snapshots": {
              "from": { "place_id": "...", "formatted_address": "...", "lat": -33.93, "lng": 18.86, "source": "google_places" },
              "to": { "place_id": "...", "formatted_address": "...", "lat": -33.97, "lng": 18.60, "source": "google_places" }
            }
          }
        ]
      },
      {
        "trip_id": "trip_2",
        "sort_order": 20,
        "trip_type": "one_way",
        "date": "2026-07-30",
        "time": "17:00",
        "passengers": { "total": 3 },
        "luggage": { "check_in_bags": 0, "carry_on_bags": 0 },
        "notes": "",
        "legs": [
          {
            "leg_id": "leg_2",
            "pickup_date": "2026-07-30",
            "pickup_time": "17:00",
            "pickup_location": { "label": "Cape Town Airport" },
            "dropoff_location": { "label": "Stellenbosch" },
            "place_snapshots": { "from": {}, "to": {} }
          }
        ]
      }
    ],
    "chronology_warning": {
      "active": true,
      "message": "Trip 2 occurs before Trip 1.",
      "resolved_by": "arrange_chronologically"
    }
  }
}
```

Rules:

- `sort_order` is the canonical ordering field (no `display_order`).
- Route data normalises into `legs[]`; pickup/drop-off/place_snapshots live on the leg, matching the master plan and reserved fixtures.
- `chronology_warning` is optional and included only if useful; it is advisory metadata.
- Marketing must **not** include authoritative route distance, duration, tolls, price, availability, blockout, cart, or order data.
- Stale Google Places snapshots block quote-ready submit when `enable_google_places_required` is on.

Forbidden marketing-authority fields (must be stripped/rejected):

```json
{
  "final_price": 1234,
  "authoritative_distance_km": 55.2,
  "authoritative_toll_total": 100,
  "vehicle_price": 999,
  "availability_confirmed": true
}
```

---

## 6. Future Playwright Coverage

Tests should target the LocalWP marketing URL (`MARKETING_BASE_URL=https://wolfshuttles.local/booking-builder/`) and read the gate from `window.WSB_BOOKING_CLIENT_FORM.featureGates`.

1. **Gate disabled hides shell** — with `enable_multi_trip_bookings=false`, the multi-trip builder shell is not rendered/visible.
2. **Gate enabled shows shell** — with `enable_multi_trip_bookings=true` (local only), the shell renders.
3. **Add trip works** — adding a trip creates a card with a new `trip_id` and incremented `sort_order`.
4. **Duplicate trip works** — duplicating creates a new `trip_id`, copies fields, preserves snapshots.
5. **Delete trip works** — deleting removes the card; `trip_id` is not reused.
6. **Collapse/expand works** — per-card and collapse-all/expand-all toggles update UI state.
7. **Chronology warning appears** — when `sort_order` conflicts with chronological order, the warning "Trip 2 occurs before Trip 1." is shown.
8. **Arrange chronologically works** — the action re-sequences `sort_order` only; `date`/`time` values are unchanged.

---

## 7. Risks / Conflicts with M4A

M4A is the multi-day charter builder (day cards, `charter.days[]`, `day_id`, `sort_order`, drag/drop, duplicate/delete). M5A reuses the same concepts for trips.

Potential conflicts:

- **Shared form renderer / state files** — both builders may live under the same `[data-wsb-booking-builder]` wrapper and the same JS state model in `assets/js/booking-client-form.js`. Coordinating `sort_order` semantics between `charter.days[]` and `itinerary.trips[]` requires a shared, clearly scoped ordering helper to avoid cross-contamination.
- **Drag/drop UX** — M4A uses date-locked plan swap; M5A uses manual planning order. The two modes must not be confused in one shared drag/drop handler.
- **Gate coupling** — `enable_multi_trip_bookings` is independent of `enable_multi_day_charters`; the shell must not appear when only the charter gate is on.
- **Normalizer/validator** — gate-aware validation must distinguish trip payloads from charter payloads; do not let charter validation reject trip fields or vice versa.

Recommendation: avoid implementing the runtime multi-trip shell in parallel with M4A unless the shared renderer/state module is explicitly coordinated. Keep M5A as documentation/planning until M4A's builder surface is stable.

---

## 8. Final Report

### Files reviewed

- `plans/revised-marketing-feature-gate-multiday-plan.md` — master plan; multi-trip model `itinerary.trips[]`, `sort_order`, product decisions.
- `docs/m3a-feature-gate-config-foundation.md` — `enable_multi_trip_bookings` gate, PHP-authoritative, JS mirror, defaults.
- `docs/ux-drag-drop-behaviour-rules.md` — UX-DND-001 §3 multi-trip manual planning order + warning copy.
- `docs/booking-payload-v2-contract.md` — `sort_order` canonical ordering field.
- `assets/js/booking-client-form.js` — existing `[data-wsb-booking-builder]` wrapper and state model.
- `tests/e2e/wolf-shuttles/*` + `playwright.config.ts` + `.env.playwright.example` — LocalWP test URL and gate-read pattern.

### Files changed

- None (runtime).
- Created: `docs/m5a-multitrip-builder-shell-plan.md` (this document).

### Confirmation

- Documentation / planning only. No runtime code changed.
- `sort_order` is the canonical payload ordering field for trips; `display_order` is not introduced.
- `enable_multi_trip_bookings` remains `false` in all environments.
- Marketing remains intent-only; no pricing/routing authority is added.
