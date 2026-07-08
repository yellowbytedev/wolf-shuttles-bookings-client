# M4A Multi-Day Charter Builder Shell

## Purpose

M4A adds the first visual multi-day charter shell to the marketing booking-builder page.

The shell is feature-gated behind `enable_multi_day_charters` and stays on the marketing side only. It does not change booking-side runtime behaviour, pricing, route authority, availability logic, or Woo/session/cart/order flow.

## Gate Behaviour

- When `enable_multi_day_charters` is off, the multi-day shell is hidden.
- When `enable_multi_day_charters` is on, the Shuttle Hire panel shows:
  - the existing same-day charter fields
  - a multi-day shell with a same-day / multi-day mode switch
  - a fixed-slot day builder

Same-day remains the default mode so the existing charter flow stays intact.

## Shell Behaviour

The shell exposes three fixed day slots:

- Day 1
- Day 2
- Day 3

Each day card includes:

- date
- start time
- end time
- pickup location
- drop-off location
- destinations / POI intent
- notes

Day cards can:

- expand and collapse
- reveal the next hidden slot via Add day
- duplicate the current plan into the next available slot
- delete a slot when at least one day remains visible
- collapse all
- expand all

There is no drag/drop yet. The DOM is structured so M4B can add date-locked plan swapping later without moving the slot date/time model.

## Payload Behaviour

The shell preserves the reserved multi-day charter payload shape already used in the fixture corpus:

- `charter.enabled: true`
- `charter.type: "reserved"` in multi-day mode
- `charter.days[]` with fixed slot metadata

The marketing builder still does not act as the authority for:

- route distance
- route duration
- tolls
- pricing
- vehicle availability

Multi-day day payload entries preserve slot order and include non-authoritative plan text alongside place snapshots when available.

## Playwright Coverage

The browser suite now checks:

- gate disabled hides the shell
- gate enabled shows the shell
- add day works
- duplicate day works
- delete day works
- collapse and expand work

## Remaining Limitations

- No drag/drop or plan swapping yet
- No route or pricing calculation on the marketing side
- No authoritative availability checks on the marketing side
- Only three fixed day slots are supported for the shell
- Add day only reveals the next reserved hidden slot; it does not create unlimited days
