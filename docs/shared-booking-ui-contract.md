# Shared Booking UI Contract (UI-CONTRACT-001)

**Status:** Documentation / contract only — no runtime code.
**Scope:** Visual and UX alignment between the marketing booking form (`ws-bookings-client`) and the future booking-site edit/stepper flow.
**Out of scope:** Runtime PHP/JS/CSS, payload contracts, pricing/route/toll/vehicle/checkout logic, and booking-site persistence. This document is a design contract, not a code authority.

---

## 0. Purpose

The marketing booking form and the future booking-site stepper/edit flow must look and behave like the same product. This contract defines the shared visual language so that:

- a customer moving from the marketing form to the booking-site stepper does not feel like they changed sites;
- shared states (confirmed location, stale edit, accordion, card, stepper, warning) render with identical meaning and styling intent;
- the booking site owns the parts it must own, and the marketing form never visually implies ownership of those parts.

This contract follows the existing conventions in `brand-system.md`, `frontend-ux-001-production-booking-form-spec.md`, and the `wolf-shuttles-ui-concepts` mockup set. It does not override code. Where drift is found, current runtime rendering and the head-engineer contract hierarchy in `phase-2x-marketing-booking-contract-field-map.md` win.

---

## 1. Shared section names

Use these exact section labels wherever the same grouping appears on both surfaces. Do not invent per-surface synonyms.

| Section | Shared label | Notes |
|---------|--------------|-------|
| Service choice | `Book a Ride` / `Shuttle Hire` | Service tabs/pills only. |
| Trip type | `One-way` / `Return` | Radio pills inside transfer. |
| Passengers & luggage | `Passengers & luggage` | Compact 2-column grid. |
| Extras | `Extras` | Trailer, oversize luggage, etc. |
| Outbound | `Outbound` | Always present for transfer. |
| Return | `Return` | Hidden until `Return` selected; accordion/card. |
| Additional stop | `Additional stop` | Optional toggle + field. |
| Pickup / Drop-off | `Pickup` / `Drop-off` | Charter uses these, not Outbound/Return. |
| Days | `Day 1`, `Day 2`, … | Charter day cards. |
| Booking summary | `Booking summary` | Review state on both surfaces. |
| Choose vehicle | `Choose vehicle` | Booking-site stepper step 2. |
| Checkout | `Checkout` | Booking-site stepper step 3. |
| Confirmation | `Confirmation` | Booking-site stepper step 4. |

In Plan Full Booking, each accordion item owns its own trip type (`One-way` / `Return` / `Charter`) inside the card, never above the entire list.

---

## 2. Shared field styling principles

- Use existing CSS variables; never hard-code hex. Primary `var(--primary)`, secondary `var(--secondary)`, tertiary `var(--tertiary)`, text `var(--base)` / `var(--base-ultra-dark)`, surfaces `var(--white)`, spacing `var(--space-*)`, radius `var(--radius*)`.
- Reuse existing class patterns: `ws-form`, `btn-cta`, `btn-cta--bg-primary`, and the `wsb-booking-client-field--*` state wrappers.
- Rounded controls, generous whitespace, clear hierarchy, premium transport feel.
- Inputs: `border-radius: 10px` baseline; consistent padding; visible focus ring.
- Labels sit above inputs; placeholders are hints, never required-reading substitutes.
- Field keys (e.g. `passengers`, `outbound_pickup_date`) stay stable in code; labels/placeholders are configured separately and may differ per surface only with explicit approval.
- Disabled fields must read as inert (reduced opacity, no focus ring), not just greyed.

---

## 3. Button hierarchy

| Level | Style | Usage |
|-------|-------|-------|
| Primary | `btn-cta btn-cta--bg-primary` (red→purple gradient) | Single main action per view: `Get quote` / `Continue` / `Confirm booking`. One primary per screen. |
| Secondary | outline/ghost, `var(--secondary)` border or text | `Use current location`, `Add another day`, `Back`. |
| Tertiary / icon | minimal, text or icon only | `Clear`, `Copy this day`, `Open/Close`, `Delete`. |
| Destructive | red text/border, no fill by default | `Remove day`, `Delete trip`. Confirm before destructive bulk actions. |

Rules:

- Only one primary CTA per view; stepper "Continue" is primary, "Back" is secondary.
- Icon-only buttons must carry an `aria-label`.
- Disabled primary must look disabled, not just lower-contrast primary.
- Never use booking-site-owned action styling (e.g. Woo checkout button) inside the marketing form.

---

## 4. Confirmed-location state

- Wrapper: `.wsb-booking-client-field--place-selected`.
- Visual: green border + subtle green background using `var(--success)`.
- Meaning: a Google Places snapshot is fully resolved (place_id, formatted address, coords) and not edited since selection.
- Stale flag is cleared on focus if present.
- This state is identical on marketing form and booking-site edit.

---

## 5. Warning / stale state

- Wrapper: `.wsb-booking-client-field--place-stale`.
- Visual: yellow/amber border + subtle background using `var(--warning)`; distinct but non-alarming.
- Trigger: user types after a confirmed selection → snapshot `stale = true`.
- Customer copy (no internal keys): *"Location was edited after selection. Please select a place again."*
- Submit is blocked when a quote-ready location is required and not re-confirmed.
- Non-alarming by intent: warns, does not error.

---

## 6. Stepper states

For the booking-site stepper (Step 1 Edit → Step 2 Vehicle → Step 3 Checkout → Step 4 Confirmation):

| State | Visual |
|-------|--------|
| Upcoming | muted number, `var(--base)` low opacity, no fill. |
| Current | primary-filled number, label bold, connector active. |
| Completed | check icon, `var(--success)` tint, "Edit" affordance to return. |
| Blocked | disabled, not navigable until prerequisites met. |

Rules:

- Step labels use the shared section names from §1.
- Completed steps expose an `Edit` affordance; editing returns to that step without losing later-entered data where safe.
- The stepper must reflect the same confirmed/stale location semantics as the marketing form.
- Progress is linear; do not allow skipping required steps.

---

## 7. Accordion / card states

- Closed accordion cards: open/close arrow + copy + delete actions; no separate edit button.
- At least one trip/day always remains; delete/remove disabled when only one item exists.
- Expanded card: clear active/expanded styling distinct from collapsed (avoid relying on dashed-border-only; make interactivity obvious).
- Charter day cards: Day 1 starts expanded; days 2+ collapsed by default.
- `Open all` / `Close all` operate on visible cards.
- Active/expanded vs collapsed must be communicated by both colour and a visible affordance (arrow rotation, header weight), not colour alone (a11y).
- Drag handle (if enabled) must be visually distinct from expand/collapse, duplicate, and delete controls.

---

## 8. Icon placeholder policy

- Prefer clean inline SVG icons or simple Unicode glyphs.
- Avoid heavy icon libraries unless explicitly approved.
- Icon-only controls require an `aria-label`; do not rely on title alone.
- Placeholder icons (e.g. location pin, clock, calendar, chevron, trash, copy) must use the same glyph family on both surfaces.
- Never use an icon to communicate internal/technical state to the customer.
- When an icon is not yet designed, leave a labelled text fallback rather than a blank box.

---

## 9. Mobile rules

- Mobile-first: stacked single-column layout; no horizontal overflow (≤680px, ideally ≤400px).
- Touch targets min ~44px; primary actions easy to tap when scrolled.
- Compact 2-column grids collapse to one column on mobile.
- Date/time pickers open above the keyboard where possible; clock picker width correct on mobile.
- Full-width inputs; readable typography; spacing scales via `var(--space-*)`.
- No layout jump when sections (Return, additional stop) show/hide.
- Stepper may compress to a compact progress indicator but must keep step labels reachable.

---

## 10. Copy tone

- Clean, confident, premium transport.
- Plain, customer-facing language; short sentences; active voice.
- Reassuring status copy; warnings inform without alarming.
- South-Africa context where relevant (ZA Google Places restriction, local time/AM-PM).
- Consistent terminology across surfaces (use §1 labels).

---

## 11. Forbidden internal wording

The following must **not** appear in customer-facing UI: labels, placeholders, aria-labels, tooltips, status messages, or summaries. Debug-only panels (`?debug=1` / `wb-debug`) are exempt but must not leak into production.

Forbidden (from `frontend-ux-001` §4):

- `testing`, `beta`, `fixed slot`, `reserved slot`, `hidden slot`, `mock day`
- `feature gate`, `payload`, `metadata`, `non-authoritative`, `route authority`
- `V3`, `M4A`, `M4B`, `TODO`, `debug wording`
- `booking_token`, `token_hash`, `ws_trip_sig`, `ws_active_hash`
- `request_id`, `raw JSON`, `place_id`, `lat` / `lng` (and "latitude"/"longitude")
- `handover`, `envelope`, `schema_version`, `handover_mode`
- `sample data drawer`, `fixture drawer`, `loaded sample`, `expected valid/invalid`
- `server validation pending/success/warnings/failed`
- `booking summary initialised`, `sample not found`, `server preview unavailable/error`
- `Booking summary ready`, `Fixture:`, `updated:`

Additional contract rule: never show raw coordinates, internal field keys, or signing/envelope language to the customer on either surface.

---

## 12. What must stay booking-site-owned

The marketing form is a capture and handoff surface. The following remain booking-site-owned and must not be visually implied as marketing responsibilities:

- **Vehicle selection** (stepper step 2): booking site selects/validates vehicles from the canonical payload.
- **Pricing / quotes**: booking site owns price, route distance/duration, tolls, surge. Marketing route/toll values are advisory only, never pricing authority.
- **Availability / blockouts / lead-time enforcement**: booking site owns current enforcement; marketing may show clean status hints only.
- **Checkout / WooCommerce**: booking-site checkout only; do not embed checkout in the marketing shortcode.
- **Confirmation / post-payment**: booking-site-owned summary and logic.
- **Session / cookies / cart / order**: established on the booking page, not via marketing REST intake.
- **Signed handover envelope / token service**: internal; never surfaced.

Where the booking-site edit form reuses shared component classes for brand consistency, it must remain independent of the marketing shortcode lifecycle and security constraints.

---

## 13. Compliance checklist (for future implementers)

When building either surface, confirm:

- [ ] Section labels match §1 exactly.
- [ ] Field styling uses brand variables / shared classes (§2).
- [ ] One primary CTA per view; hierarchy per §3.
- [ ] Confirmed = green `var(--success)`; stale = amber `var(--warning)` (§4–5).
- [ ] Stepper states per §6; edit returns without data loss.
- [ ] Accordion/card states per §7; delete disabled at one item.
- [ ] Icons inline SVG/Unicode, aria-labelled, same family (§8).
- [ ] Mobile: no horizontal scroll, 44px targets, stacked (§9).
- [ ] Copy tone premium/plain; no forbidden wording (§10–11).
- [ ] Booking-site-owned areas not implied as marketing (§12).
