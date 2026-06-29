# Wolf Shuttles Booking Client Handoff

Current branch: `feature/phase-2h-v2-handover-foundation`

Read this file first, together with `AGENTS.md`, before starting substantial work.

## 1. Project Summary

`ws-bookings-client` is the Wolf Shuttles marketing-site booking plugin.

Its job is to capture booking intent on the marketing site, normalise that intent into `BookingPayload v2`, validate it, and hand it off to the booking-system site.

This repository is in Phase 2 of the booking intake foundation work. The new Booking Builder flow is being added beside the legacy Bricks/Fluent booking flow. The legacy flow must keep working until the booking-site v2 intake path is ready.

The canonical data direction is:

```text
Marketing form/component
→ BookingPayload v2
→ PHP normaliser/validator
→ handover adapter
→ booking site
```

## 2. Repository Boundaries

- The only writable repo for this phase is `ws-bookings-client`.
- Agents may read outside the repo for diagnostics only.
- Agents must not edit, stash, clean, reset, checkout, or initialise Git outside this repo.
- `nextbricks`, `ws-bookings`, Bricks, Fluent Snippets, themes, and WordPress core are off-limits for writes.
- Do not run `git init` anywhere.
- Do not create `.git` folders anywhere.

If a task needs context outside the repo, read only. Do not write outside `ws-bookings-client`.

## 3. Current Git Workflow

- Use feature branches for larger phase work.
- Treat `main` as a stable checkpoint only.
- Do not push unless explicitly instructed.
- Do not use VS Code "sync all repositories".
- Run every Git command from inside `ws-bookings-client`.
- Keep changes small, reviewable, and reversible when possible.

## 4. Current Roadmap Position

The roadmap lives in `docs/booking-intake-roadmap.md` and the phase log lives in `docs/phase-2-progress.md`.

- Phase 2E complete: realtime local payload preview.
- Phase 2F complete: server-side payload preview validation.
- Phase 2G complete: payload fixture runner.
- Phase 2H complete: v2 handover foundation, dry-run only.
- Phase 2I complete: developer fixture drawer / payload test lab.
- Phase 2J complete: schema extension scaffolds (route, validation_flags, charter, blockouts, leg-scoped stops).
- Phase 2K complete: charter preview mode (Shuttle Hire tab enabled, canonical charter payload shape, trip_type: "charter" fix applied).
- Phase 2K+ complete: UI interaction scaffold (sortable adapter, CSS hooks).
- Phase 2L complete: legacy external services audit, adapter scaffold (`inc/class-booking-external-services.php`).
- Phase 2M complete: charter additional stop support removed (charters do not offer additional stops).
- Phase 2N complete: place snapshot scaffolding added (per-leg place_snapshots block).
- Phase 2O planned: booking-site config contract defined for future lead time/capacity picker constraints.
- Phase 2P planned: quote preflight/draft itinerary roadmap documented.

If roadmap or phase status changes, update this file at the same time.

## 5. What Currently Works

- The `/booking-builder/` page renders the Booking Builder shortcode shell and smoke-tested at HTTP 200.
- The `/booking-builder/?debug=1` page renders the developer fixture drawer and smoke-tested at HTTP 200.
- Realtime BookingPayload v2 preview works in the browser.
- The server-side preview endpoint works at `POST /wp-json/ws-bookings-client/v1/payload-preview`.
- The payload fixture runner passes (22 fixtures).
- The handover preview fixture runner passes (13 passed, 7 skipped as invalid).
- The normalizer now preserves `service_group`, top-level `route`, `validation_flags`, and `charter` scaffolds.
- Meta fields are aligned: both `meta.preview_only` and `meta.handover_mode` are set in JS and PHP.
- The developer fixture drawer loads fixtures from `tests/fixtures/booking-payload-v2-fixtures.json`, populates the form, and re-runs preview checks.
- Each leg (outbound/return) has its own additional stop toggle and field; stops are stored in `legs[].stops[]`. Charter legs use empty stops.
- `blockouts` diagnostic scaffold added for future vehicle-scoped blockout support.
- **Charter preview mode**: Shuttle Hire tab enabled, charter leg type supported, `dropoff_time` preserved in legs, `charter` block populated, `trip_type: "charter"` when charter active. Charter does not offer additional stops.
- Legacy Bricks/Fluent booking flow is still untouched.
- No real booking submission is enabled yet.

## 6. What Is Deliberately Not Enabled Yet

- No real booking-site call.
- No booking token creation.
- No WooCommerce/cart item creation.
- No Google autocomplete yet.
- No route, distance, or toll integration yet in the new intake layer.
- No itinerary database table yet.
- No multi-trip cart yet.
- No production UI polish yet.
- Charter pricing not implemented.
- Multi-day charter drag/drop not implemented.

## 7. Key Files and Responsibilities

- `inc/booking-client.php` - plugin bootstrap; loads the v2 controllers, shortcode, and fixture hooks.
- `inc/booking-client-config.php` - handover mode config and v2 handover secret resolution.
- `inc/class-booking-client-form-shortcode.php` - renders the Booking Builder shell, enqueues assets, and exposes preview config to JS.
- `inc/class-booking-field-registry.php` - canonical field definitions and labels for the Booking Builder.
- `inc/class-booking-payload-v2-normalizer.php` - canonical v2 normaliser for flat form data or structured legs payloads.
- `inc/class-booking-payload-v2-validator.php` - v2 validation rules.
- `inc/class-booking-payload-preview-controller.php` - REST preview endpoint for validation-only payload inspection.
- `inc/class-booking-payload-v2-handover-service.php` - deterministic dry-run envelope builder and signing logic.
- `inc/class-booking-payload-handover-preview-controller.php` - dry-run handover preview REST endpoint.
- `inc/class-booking-handover-v2-service.php` - scaffold for future `legacy_hash` / `v2_token` handover branching.
- `inc/class-booking-intake-fixture-loader.php` - WP-CLI fixture loader stub.
- `inc/class-booking-external-services.php` - no-op adapter scaffold for Google/HERE/route/toll integrations.
- `assets/js/booking-client-form.js` - lightweight UI state, preview rendering, and REST preview submission.
- `assets/css/booking-client-form.css` - Booking Builder styling.
- `tests/fixtures/booking-payload-v2-fixtures.json` - canonical v2 fixture corpus (22 fixtures).
- `tests/fixtures/booking-intake-fixtures.v2.seed.json` - legacy seed fixture reference.
- `scripts/run-booking-payload-fixtures.php` - terminal v2 payload fixture runner.
- `scripts/run-booking-handover-preview-fixtures.php` - terminal dry-run handover fixture runner.

## 8. Testing Requirements

Before finishing any shortcode, UI, or page-facing PHP change, run the smoke-test workflow.

```bash
find inc scripts -name '*.php' -print0 | xargs -0 -n1 php -l
node --check assets/js/booking-client-form.js
php scripts/run-booking-payload-fixtures.php
php scripts/run-booking-handover-preview-fixtures.php
curl -i https://wolfshuttles.local/booking-builder/
curl -i "https://wolfshuttles.local/booking-builder/?debug=1"
tail -n 200 wp-content/debug.log
```

Requirements:

- Confirm Local is running, or ask the user to start it before smoke testing.
- Check the HTTP status code for the affected page.
- Inspect the saved HTML for critical errors.
- Inspect `wp-content/debug.log` after the smoke test.
- Do not mark the task complete if the page returns a critical error or if a fresh plugin fatal appears in the log.

**Note:** For UI changes (shortcode markup, CSS, frontend JS, fixture drawer, additional stop UI, preview panels, form layout), see `AGENTS.md` for browser/Playwright MCP visual QA requirements.

## 9. Debug Log Policy

- Agents may read `wp-content/debug.log`.
- Agents may not write outside the repo except for temporary files in `/tmp` or the configured temp directory.
- If the page shows a critical error, fixing that becomes the immediate priority.
- A task is not complete until `wp-content/debug.log` has no fresh `ws-bookings-client` fatal after the relevant smoke test.

## 10. Agent Tiers / Workflow Expectation

See `AGENTS.md` for specialist agent roles and sub-agent rules.

- Small or free agents should stick to small implementation tasks, fixtures, or doc updates.
- Review agents should inspect diffs, tests, logs, and architecture for regressions.
- Stronger agents should handle architecture changes and risky debugging.
- Keep tasks small, reversible, and easy to verify.

## 11. Next Recommended Tasks

1. Prepare the booking-site v2 receiver plan in `ws-bookings`.
2. Design the itinerary parent table and trip linkage for Phase 4.
3. Prepare the booking token flow for the future v2 handover path.
4. Wire a real v2 receiver in the booking plugin when that repo is ready.
5. Revisit Google autocomplete and route / toll / distance integration later.
6. Consider charter pricing integration once booking-site v2 endpoint is ready.

## 12. Standard Agent Completion Report

When finishing a task, report in this format:

```text
Branch:
Files changed:
Tests run:
Smoke-test results:
Debug-log result:
Touched outside repo:
Commit hash:
Remaining risks:
```

If something was skipped, say why. If nothing outside the repo was touched, state that explicitly.

## 13. UI Interaction Scaffold

A no-op sortable adapter scaffold exists in `assets/js/booking-client-form.js`:

- `WSB_BOOKING_UI_INTERACTIONS.isSortableAvailable()` — returns false until a sortable library is loaded
- `WSB_BOOKING_UI_INTERACTIONS.initSortableList(root)` — no-op placeholder for future SortableJS integration
- `WSB_BOOKING_UI_INTERACTIONS.destroySortableList(root)` — no-op placeholder for cleanup

PHP flag `WSB_CLIENT_UI_INTERACTIONS_ENABLED` (default false) controls via config.

CSS hooks added in `assets/css/booking-client-form.css`:
- `.wsb-sortable-list`, `.wsb-sortable-item`
- `.wsb-drag-handle`, `.wsb-sortable-placeholder`, `.wsb-sortable-chosen`

No third-party library is loaded yet. See `docs/ui-interaction-scaffold.md` for full details.

## Keeping This Handoff Current

`AGENT-HANDOFF.md` is the canonical current-state document for this repo.

If the roadmap, phase status, testing workflow, or current implementation changes, update this file in the same change.