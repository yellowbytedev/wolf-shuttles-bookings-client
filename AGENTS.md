# Wolf Shuttles Booking Client — Agent Instructions

## Project role

This repository is the marketing-site booking client for Wolf Shuttles. It captures booking intent on the marketing site, normalises and validates that intent, and hands it to the booking-system site.

The paired booking-system plugin is `ws-bookings`. The marketing plugin is `ws-bookings-client`.

## Current phase

We are in Phase 2: booking intake foundation.

The goal is not to replace the live booking flow immediately. The goal is to build a plugin-owned intake layer beside the legacy Bricks/Fluent-snippet flow, then prove that the new flow can create equivalent bookings.

## Non-negotiable rules

1. Keep the current legacy form flow working unless explicitly instructed otherwise.
2. Do not blindly copy old Fluent snippet code. Use it as behavioural reference only.
3. Do not make Bricks hashed field IDs part of the new data contract.
4. Do not use hidden fields as the source of truth for the new system.
5. Keep JavaScript light: UI state, Google Places selection, client-side convenience only.
6. Put canonical validation, normalisation, payload building, and handover logic in PHP.
7. Every new payload must declare a schema version.
8. New payloads use `schema_version: "2.0"` and a legs-based trip model.
9. One-way and return transfers must be supported first. Charter is next, but the schema must already allow it.
10. Preserve backwards compatibility with the current hash handover until the booking-site v2 intake endpoint is ready.
11. Add or update tests/fixtures/docs when behaviour changes.
12. Never change production/staging URLs or secrets without confirmation.

## Architecture direction

The desired direction is:

```text
Marketing form/component
→ BookingPayload v2
→ PHP normaliser/validator
→ handover adapter
→ booking site
```

The marketing site should not own pricing. It may collect route metadata, Google/HERE results, and validation-supporting facts, but the booking system remains the final authority for trip storage, vehicle selection, and pricing.

## Naming preferences

Use:

- `service_group`: `transfer` or `charter`
- `service_type`: `airport_pickup`, `airport_dropoff`, `city_transfer`, `charter_hire`
- `trip_type`: `one_way`, `return`, `charter`
- `check_in_bags`, not `largeBags`
- `carry_on_bags`, not `carryOnBags`
- `baby_seats`, not `babySeatCount`

Avoid the term `service_family`.

## Documentation files to read first

- `docs/booking-intake-current-flow.md`
- `docs/booking-payload-v2.md`
- `docs/booking-intake-roadmap.md`
- `docs/testing-engine-plan.md`
- `docs/known-issues-debug-log.md`

## First deliverable target

Create a new shortcode-rendered booking builder form that can submit one-way and return transfer payloads in v2 format while leaving the existing Bricks form untouched.
