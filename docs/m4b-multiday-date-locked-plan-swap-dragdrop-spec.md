# M4B — Multi-Day Charter Date-Locked Plan-Swap Drag/Drop Spec

## 0. Status

Documentation and implementation specification only.

- No runtime PHP, JS, Playwright tests, fixtures, DB schema, pricing, route, Woo, cart, checkout, blockout, or vehicle logic is modified by this spec.
- The `enable_multi_day_charters` gate controls whether the multi-day charter shell/day cards exist.
- The `enable_drag_drop_itinerary_ordering` gate controls whether pointer drag/drop handles/behaviour are enabled.
- Runtime drag/drop requires both gates to be `true`.
- M4B is the visual drag/drop step that lands after M4A.

---

## 1. Purpose

M4B adds date-locked plan-swap drag/drop to the M4A multi-day charter builder shell.

The rule is explicit: **day slots are date/time locked. Drag/drop swaps the plan/content assigned to a slot, never the slot's day number, date, start time, or end time.**

---

## 2. Date-Locked Slot Model

Each day slot is a fixed position in the multi-day charter shell.

| Slot Attribute | Type | Payload | Fixed? |
|---|---|---|---|
| `day_index` | integer | `charter.days[].day_index` | Yes — never moves |
| `day_number` | integer | UI label (Day 1, Day 2, ...) | Yes — derived from slot position |
| `date` | ISO date (`YYYY-MM-DD`) | `charter.days[].date` | Yes — never changes via drag/drop |
| `start_time` | time (`HH:mm`) | `charter.days[].start_time` | Yes — never changes via drag/drop |
| `end_time` | time (`HH:mm`) | `charter.days[].end_time` | Yes — never changes via drag/drop |
| `sort_order` | integer | `charter.days[].sort_order` | Yes — preserved with the slot |

Slot rules:

- Slots are rendered in `sort_order` / `day_index` sequence.
- Dates and times are entered by the user and remain immutable to drag/drop.
- Drag/drop never renumbers a day, never shifts its date, and never mutates its start/end times.
- Drag/drop only reassigns the **plan/content** fields between slots.

---

## 3. Plan/Content Model

The content that moves with drag/drop is the charter day plan.

| Content Field | Type | Payload |
|---|---|---|
| `pickup_location` | object | `charter.days[].pickup_location` |
| `dropoff_location` | object | `charter.days[].dropoff_location` |
| `place_snapshots` | object | `charter.days[].place_snapshots` |
| `destinations` | array / string | `charter.days[].destinations` |
| `pois` | array / string | `charter.days[].pois` |
| `notes` | string | `charter.days[].notes` |
| `stops` | array | `charter.days[].stops` |
| `route_preferences` | object | `charter.days[].route_preferences` |
| `itinerary_intent` | string | `charter.days[].itinerary_intent` |

Content rules:

- Content is **intent-only** on the marketing side.
- Marketing does not calculate route distance, duration, tolls, pricing, availability, blockouts, or vehicle assignments for content fields.
- Place snapshots are advisory labels/IDs captured from Places selection; they are not authoritative routing data.
- `route_preferences` and `itinerary_intent` are freeform marketing intent; booking side interprets or ignores them.

---

## 4. Drag/Drop Behaviour

### 4.1 Gate Model

Two existing gates control M4B behaviour:

- `enable_multi_day_charters` — controls whether the multi-day charter shell and day cards exist.
- `enable_drag_drop_itinerary_ordering` — controls whether pointer drag/drop handles/behaviour are enabled.

Runtime drag/drop is enabled only when **both** gates are `true`:

- `enable_multi_day_charters === true`
- `enable_drag_drop_itinerary_ordering === true`

Fallback controls (move plan up, move plan down, swap with another day) may remain available when the multi-day charter shell is enabled, even if pointer drag/drop is disabled, because they are the accessible baseline.

### 4.2 Drag Handle

- The drag handle is visible on every expanded day card only when both gates are enabled.
- When `enable_multi_day_charters` is `true` but `enable_drag_drop_itinerary_ordering` is `false`, drag handles are hidden or disabled, and fallback controls remain available.
- When `enable_multi_day_charters` is `false`, the multi-day shell is hidden entirely; drag/drop controls are not rendered.
- The drag handle is visually distinct from expand/collapse, duplicate, and delete controls.

### 4.3 Drag/Drop Action

- Pointer drag reorders **plan/content** between day slots.
- The dragged day card's content moves to the target day slot.
- The target day card's content moves to the source day slot (swap semantics).
- Day slot metadata (`day_index`, `date`, `start_time`, `end_time`, `sort_order`) stays with its slot.

### 4.4 Prohibited Silent Changes

Drag/drop must **not** silently change:

- Day number
- Date
- Start time
- End time
- Slot `day_index` or `sort_order`

If a user action would imply a date/time change, the UI must either block the action or require explicit confirmation that is not a drag/drop gesture.

---

## 5. Fallback Controls

Fallback controls must always be available, regardless of drag/drop support.

### 5.1 Move Plan Up

- Moves the current day's plan/content to the previous day slot.
- If the current day is already the first slot, the control is disabled.
- Date/time metadata of both slots remains unchanged.

### 5.2 Move Plan Down

- Moves the current day's plan/content to the next day slot.
- If the current day is already the last slot, the control is disabled.
- Date/time metadata of both slots remains unchanged.

### 5.3 Swap with Another Day

- Provides a day-picker dropdown or direct target selector.
- User selects the target day; the two day plans are exchanged.
- Date/time metadata of both slots remains unchanged.

### 5.4 Keyboard Accessible Controls

- All fallback controls are keyboard accessible.
- Each day card supports focus management and ARIA labels for move up, move down, and swap actions.
- Drag handle supports keyboard activation where the platform allows it; otherwise, the fallback controls are the keyboard path.
- Focus order follows the visual day sequence.

---

## 6. Example

Before:

| Day | Date | Plan |
|---|---|---|
| Day 1 | 8 July | Cape Town city tour |
| Day 2 | 9 July | Garden Route day |

User swaps plans via drag/drop or fallback control.

After:

| Day | Date | Plan |
|---|---|---|
| Day 1 | 8 July | Garden Route day |
| Day 2 | 9 July | Cape Town city tour |

Dates and day numbers did not change. Only the plan/content moved.

---

## 7. Payload Shape

### 7.1 Charter Days Array

`charter.days[]` preserves fixed slot metadata and reassigns plan content between slots.

```json
{
  "charter": {
    "enabled": true,
    "type": "reserved",
    "days": [
      {
        "day_index": 0,
        "date": "2026-07-08",
        "start_time": "09:00",
        "end_time": "17:00",
        "sort_order": 10,
        "pickup_location": { "label": "Cape Town CBD" },
        "dropoff_location": { "label": "Cape Town CBD" },
        "place_snapshots": {
          "from": { "label": "Cape Town CBD", "place_id": null, "formatted_address": null, "lat": null, "lng": null, "provider": null },
          "to": { "label": "Cape Town CBD", "place_id": null, "formatted_address": null, "lat": null, "lng": null, "provider": null }
        },
        "destinations": ["Garden Route"],
        "pois": [],
        "notes": "Full-day Garden Route experience.",
        "stops": [],
        "route_preferences": {},
        "itinerary_intent": "Scenic coastal drive"
      },
      {
        "day_index": 1,
        "date": "2026-07-09",
        "start_time": "09:00",
        "end_time": "17:00",
        "sort_order": 20,
        "pickup_location": { "label": "Cape Town CBD" },
        "dropoff_location": { "label": "Cape Town CBD" },
        "place_snapshots": {
          "from": { "label": "Cape Town CBD", "place_id": null, "formatted_address": null, "lat": null, "lng": null, "provider": null },
          "to": { "label": "Cape Town CBD", "place_id": null, "formatted_address": null, "lat": null, "lng": null, "provider": null }
        },
        "destinations": ["Cape Town city"],
        "pois": ["Table Mountain"],
        "notes": "City tour with Table Mountain visit.",
        "stops": [],
        "route_preferences": {},
        "itinerary_intent": "Urban sightseeing"
      }
    ]
  }
}
```

### 7.2 Payload Rules

- `day_index` is fixed per slot and never changes due to drag/drop.
- `date`, `start_time`, `end_time` are fixed per slot and never change due to drag/drop.
- `sort_order` is preserved with the slot.
- Plan/content fields (`pickup_location`, `dropoff_location`, `place_snapshots`, `destinations`, `pois`, `notes`, `stops`, `route_preferences`, `itinerary_intent`) are reassigned between slots.
- Marketing does not add authoritative route, price, distance, duration, toll, availability, blockout, vehicle, cart, or order data to `charter.days[]`.
- The payload shape remains compatible with the existing `booking-payload-v2-contract.md` charter days shape.

---

## 8. Marketing Authority Boundaries

Marketing remains **intent-only** for multi-day charter plan swapping.

Marketing must **not** add, imply, or calculate:

- Final price or quote
- Authoritative route distance / duration
- Toll totals
- Vehicle selection or availability
- Blockout validation
- WooCommerce cart / order / session data
- Route geometry or turn-by-turn directions

Marketing may capture and preserve:

- User-entered pickup/drop-off labels
- Places selection labels and snapshots
- Destinations, POIs, notes, stops
- Route preferences (e.g., "avoid tolls", "scenic route")
- Itinerary intent text

---

## 9. Playwright Plan

Tests target the LocalWP marketing URL (`https://wolfshuttles.local/booking-builder/`) and read gates from `window.WSB_BOOKING_CLIENT_FORM.featureGates`.

1. **Shell hidden when `enable_multi_day_charters=false`** — the multi-day charter shell is not rendered or visible.
2. **Drag handles hidden when `enable_drag_drop_itinerary_ordering=false`** — with `enable_multi_day_charters=true` and `enable_drag_drop_itinerary_ordering=false`, no drag handles are rendered or they are disabled/hidden.
3. **Drag handles visible only when both gates are true** — with `enable_multi_day_charters=true` and `enable_drag_drop_itinerary_ordering=true`, each day card shows a visible drag handle.
4. **Fallback controls still work when shell is enabled and drag/drop gate is false** — with `enable_multi_day_charters=true` and `enable_drag_drop_itinerary_ordering=false`, move up/down/swap fallbacks remain available and functional.
5. **Dragging swaps plan content only** — perform a drag from Day 1 to Day 2; verify Day 1 plan content moves to Day 2 slot and Day 2 plan content moves to Day 1 slot; verify `date`, `start_time`, `end_time`, and `day_number` are unchanged on both slots.
6. **Dates/times unchanged** — after any drag/drop or fallback move, assert that each day slot's `date`, `start_time`, and `end_time` match their pre-action values.
7. **Move up fallback works** — click move up on Day 2; verify Day 2 plan content moves to Day 1; verify Day 1 plan content moves to Day 2; verify dates/times unchanged.
8. **Move down fallback works** — click move down on Day 1; verify Day 1 plan content moves to Day 2; verify Day 2 plan content moves to Day 1; verify dates/times unchanged.
9. **Swap with another day works** — use swap control to exchange Day 1 and Day 3 plans; verify content is exchanged and dates/times unchanged.
10. **Keyboard accessible controls** — tab to a day card's fallback controls and execute move up/move down/swap via keyboard; verify focus management and ARIA labels.
11. **Payload preview reflects swapped plan content** — open payload preview after a swap; assert `charter.days[]` entries show plan content at the new slots while `date`, `start_time`, `end_time`, and `day_index` remain at their original slots.
12. **No pricing/routing authority added** — inspect payload preview after swap; assert no new authoritative route, price, distance, duration, toll, availability, blockout, vehicle, cart, or order fields were injected.

---

## 10. Implementation Risks

1. **Shared drag/drop handler with M5A** — M5A uses manual planning order (`sort_order` reorder). M4B uses date-locked plan swap. A shared handler must branch clearly by builder mode; mixing the two semantics will corrupt payloads.
2. **Slot content vs slot metadata confusion** — Implementers must ensure `date`, `start_time`, `end_time`, `day_index`, and `sort_order` stay bound to the slot DOM node, not the dragged content clone.
3. **Keyboard parity** — Drag/drop is a pointer enhancement. Move up/down/swap fallbacks must remain the primary accessible path and must not be removed once drag/drop is implemented.
4. **Payload mutation bugs** — If the normalizer re-sorts `charter.days[]` by content instead of `day_index`/`sort_order`, plans can appear on wrong dates. The normalizer must treat `charter.days[]` as an ordered array keyed by slot, not by content.
5. **Place snapshot staleness** — Swapping plans also swaps place snapshots. If stale snapshots are treated as authoritative later, they can cause incorrect routing assumptions. Marketing must continue to mark them as advisory.

---

## 11. Coordination with M4A and M5A

- **M4A** provides the fixed-slot day card shell, expand/collapse, duplicate, delete, and the payload scaffold (`charter.enabled`, `charter.days[]`).
- **M4B** adds date-locked plan-swap drag/drop and fallback controls on top of the M4A shell.
- **M5A** adds manual planning order for trips (`itinerary.trips[]`, `sort_order`). M4B does **not** use trip ordering semantics for charter days; charter days use fixed `day_index` and optional `sort_order` per slot.

Shared concerns:

- `sort_order` semantics must be coordinated. For charters, `sort_order` belongs to the slot. For trips, `sort_order` belongs to the trip and may be reordered. Do not let a shared sort helper conflate the two.
- Gate independence: `enable_multi_day_charters`, `enable_multi_trip_bookings`, and `enable_drag_drop_itinerary_ordering` are independent. The multi-trip shell must not render when only the charter gate is on, and vice versa. Pointer drag/drop requires both `enable_multi_day_charters` and `enable_drag_drop_itinerary_ordering` to be `true`.

---

## 12. Final Report

### Files reviewed

- `docs/ux-drag-drop-behaviour-rules.md` — UX-DND-001 date-locked plan swap rules and multi-trip manual planning order rules.
- `docs/m4a-multiday-charter-builder-shell.md` — M4A fixed-slot day builder shell, payload scaffold, and M4B handoff note.
- `docs/m5a-multitrip-builder-shell-plan.md` — M5A manual planning order, `sort_order` canonical ordering field, and coordination risks.
- `docs/booking-payload-v2-contract.md` — charter days shape, authority boundaries, and multi-day handling contract.

### Files changed

- Created: `docs/m4b-multiday-date-locked-plan-swap-dragdrop-spec.md` (this document).
- No runtime PHP, JS, Playwright tests, fixtures, DB schema, pricing, route, Woo, cart, checkout, blockout, or vehicle logic was modified.

### Spec created

- `docs/m4b-multiday-date-locked-plan-swap-dragdrop-spec.md`

### Implementation risks

- Shared drag/drop handler semantics with M5A.
- Slot metadata vs content mutation risk.
- Normalizer re-sorting risk.
- Keyboard parity risk.
- Place snapshot staleness after swaps.

### Confirmation

- Documentation / spec only.
- No runtime code changed.
- `enable_multi_day_charters` controls multi-day charter shell visibility; `enable_drag_drop_itinerary_ordering` controls pointer drag/drop. Both must be `true` for drag/drop to be active.
- Marketing remains intent-only; no pricing/routing/vehicle/blockout/cart/checkout authority is added.
- Drag/drop is a pointer enhancement; move up/down/swap fallbacks are the accessible baseline.
- Fallback controls remain available when the multi-day charter shell is enabled, even if `enable_drag_drop_itinerary_ordering` is `false`.
