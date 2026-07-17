# Wolf Shuttles Booking Rebuild — Start Here

**Branch:** `main`
**Current checkpoint:** Booking V4 Phase 0.9 repository consolidation; local builder and V2 handover history merged, production/staging gates fail closed.

## What this is

Wolf Shuttles is rebuilding its booking system across two WordPress sites:

| Site | Plugin | Role |
|------|--------|------|
| `wolfshuttlescoza` | `ws-bookings-client` | **Marketing intake** — captures booking intent, normalises to `BookingPayload v2`, validates, and hands off |
| `wolf-shuttles-booking-site` | `ws-bookings` | **Booking authority** — owns pricing, availability, routes, tolls, blockouts, vehicle selection, WooCommerce cart/orders |

The marketing plugin produces a validated `BookingPayload v2` with
`schema_version: "2.0"` and an HMAC-signed handover envelope. In local development
it can submit that envelope to the Booking Site V2 intake endpoint and follow the
returned booking-token redirect; it does not own pricing, payment, or order creation.

## What has been completed

- `BookingPayload v2` canonical shape, including `one_way`, `return`, `charter`, and gated `multi_trip` intent
- PHP normaliser + validator with lead-time and capacity rules
- HMAC-signed handover envelope (deterministic signing, 1-hour expiry)
- Expanded payload and handover fixture corpus (with known absolute-date clock debt documented at the Phase 0.9 checkpoint)
- Google Places Autocomplete + place snapshots per leg (active when Google API key is configured)
- Legacy clock-timepicker restored (5-min precision, AM/PM badges)
- Fixture drawer / payload test lab (debug-only)
- Charter preview mode + additional stop support (leg-scoped)
- UI polish matching legacy form tabs/cards/inputs
- Senior review checkpoint — no critical issues
- Local multi-trip itinerary builder and reserved multi-day charter experience
- Local V2 intake handover and booking-token redirect, with non-local defaults disabled

## What must NOT be done accidentally

1. Do not edit Bricks/Fluent legacy form.
2. Do not enable booking-site handover or multi-trip gates in production/staging without explicit release approval.
3. Do not call Google Distance Matrix, HERE Maps, or any route/toll API from the marketing plugin.
4. Do not create bookings, tokens, cart items, or database records in the marketing plugin.
5. Do not push without explicit instruction.
6. Do not edit files outside `ws-bookings-client`.

## Next recommended step

**Begin Booking V4 Phase 1 from the consolidated, tagged two-repository checkpoint.**

See `docs/AI-CONTEXT-HANDOFF.md` → "Next roadmap steps" for the full roadmap.

## Where to read next

1. **This file** (`START-HERE.md`) — you are here.
2. **`docs/AI-CONTEXT-HANDOFF.md`** — full project context, terminology, architecture, business rules, roadmap. Paste this into any AI chat to give it full context.
3. **`AGENT-HANDOFF.md`** — canonical current-state document for this repo.
4. **`docs/booking-intake-roadmap.md`** — Phase 1–9 roadmap.
5. **`docs/phase-2-progress.md`** — detailed Phase 2 milestone log.
6. **`docs/booking-site-v2-receiver-plan.md`** — proposed booking-site v2 intake endpoint.

## Quick orientation for a new AI

- All canonical validation, normalisation, payload building, and handover logic is in **PHP**.
- JavaScript handles only **UI state**, **Google Places selection**, and **client-side convenience**.
- The marketing site **never** decides vehicle availability, pricing, or route validity.
- The booking site is the **final authority** for trip storage, vehicle selection, and pricing.
- Legacy Bricks/Fluent flow must remain untouched.
- Every new payload declares `schema_version: "2.0"`.
