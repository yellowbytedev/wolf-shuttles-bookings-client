# UX-DND-001 — Drag/Drop Behaviour Rules

## 1. Scope and Out of Scope

- **In scope**: Multi-day charter itinerary planning UX, multi-trip booking planning/display UX, internal configuration naming, advisory payload metadata.
- **Out of scope**: Simple "Book a Ride" form. Do not add drag/drop to the single-trip simple booking flow.

## 2. Multi-Day Charter Rule: Date-Locked Plan Swap

- Use fixed date/time day slots.
- Drag/drop swaps the **plan/content** assigned to a day slot.
- The following must remain fixed and auditable:
  - Day number
  - Date
  - Start time
  - End time
- Moved content may include:
  - Destination
  - POIs
  - Stops
  - Notes
  - Route preferences
  - Day itinerary intent
- **Do not silently move dates.**

Example:

Before:
- Day 1 — 8 July — Cape Town city tour
- Day 2 — 9 July — Garden Route day

After swapping plans:
- Day 1 — 8 July — Garden Route day
- Day 2 — 9 July — Cape Town city tour

## 3. Multi-Trip Booking Rule: Manual Planning/Display Order

- Drag/drop controls manual planning/display order only.
- Each trip keeps its own date/time values unchanged.
- If the visible order conflicts with chronological order, show a warning.
- Warning copy example:
  > Trip 2 occurs before Trip 1.
- Include action:
  > Arrange chronologically
- **Auto-sort must reorder display order only; it must not change trip date/time values.**

## 4. Suggested Internal Config Names

- `drag_drop_mode`: `disabled` | `date_locked_plan_swap` | `manual_planning_order` | `chronological_locked`
- `multi_day_charter.drag_drop_mode = date_locked_plan_swap`
- `multi_trip.drag_drop_mode = manual_planning_order`
- `multi_trip.chronology_warning = true`
- `multi_trip.allow_auto_sort_chronologically = true`

## 5. Implementation Notes

- Marketing captures intent only.
- Marketing does not calculate final route, price, distance, tolls, availability, or blockouts.
- Booking side remains authoritative.
- Drag/drop order must be represented as advisory/order metadata in the payload.
- Original date/time values must remain explicit and auditable.
