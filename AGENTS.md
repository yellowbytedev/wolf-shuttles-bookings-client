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

## Page smoke testing for shortcode/UI changes

Any task that changes shortcode rendering, frontend form output, WooCommerce hooks, or page-facing PHP must:
- run PHP lint
- confirm Local is running or ask the user to start it
- curl the affected local URL
- check HTTP status
- inspect saved HTML for critical errors
- inspect `wp-content/debug.log`
- not mark the task complete if the page returns a critical error

Every phase task must update `docs/booking-intake-roadmap.md` and `docs/phase-2-progress.md` when roadmap state changes.
Any UI/shortcode task must run the page smoke-test workflow before completion.

## Browser MCP visual QA for UI changes

Any task that changes shortcode markup, Booking Builder UI, CSS, frontend JS, fixture drawer UI, charter UI, additional stop UI, preview panels, or form layout must run browser/Playwright MCP visual QA when available.

When browser/Playwright MCP is available, the agent must:
- Open `https://wolfshuttles.local/booking-builder/` in the browser
- Open `https://wolfshuttles.local/booking-builder/?debug=1` in the browser
- Inspect the rendered page visually, not only the HTML response
- Check browser console errors
- Interact with the changed UI (click buttons, toggle controls, submit forms)
- Check for visual regressions such as:
  - overlapping fixed buttons
  - hidden controls
  - duplicate sections
  - broken drawers
  - unreachable fields
  - confusing layout

If browser/Playwright MCP is unavailable, the agent must:
- Explicitly report that visual QA could not be run
- Still run curl smoke tests plus debug-log checks
- Not report a UI task complete if the page is visually broken or the changed UI cannot be used

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

Before starting substantial work, read `AGENT-HANDOFF.md` as well. It is the canonical current-state document for this repo.

If roadmap or phase progress changes, update `AGENT-HANDOFF.md` in the same change so the handoff stays current.

## Repository boundary and Git safety

The only active Git repository for this project phase is `ws-bookings-client`.

### Non-negotiable rules

1. Do not run `git init` anywhere.
2. Do not create `.git` folders anywhere.
3. Do not commit, stash, reset, clean, checkout, or restore files in any other plugin folder.
4. Do not edit `nextbricks`, `nextbricks/licensing`, `ws-bookings`, Bricks, Fluent Snippets, themes, or WordPress core.
5. You may inspect/read other plugin files only if needed for context.
6. Any write operation must stay inside `ws-bookings-client`.
7. Any Git operation must be run from inside `ws-bookings-client`.
8. If the current Git root is not `ws-bookings-client`, stop and report the issue.

### Required preflight check for every task

Before any task begins, run:

```
pwd
git rev-parse --show-toplevel
basename "$(git rev-parse --show-toplevel)"
```

The basename must be:

```
ws-bookings-client
```

### Cross-repository boundary rule

If a task requires looking outside `ws-bookings-client`, the agent may only read files. It must not write, move, delete, stash, clean, reset, or initialise Git outside `ws-bookings-client`.

## First deliverable target

Create a new shortcode-rendered booking builder form that can submit one-way and return transfer payloads in v2 format while leaving the existing Bricks form untouched.
