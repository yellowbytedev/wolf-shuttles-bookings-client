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
- Phase 2H current: v2 handover foundation, dry-run only.
- Phase 2I next: developer fixture drawer / payload test lab.

If roadmap or phase status changes, update this file at the same time.

## 5. What Currently Works

- The `/booking-builder/` page renders the Booking Builder shortcode shell and smoke-tested at HTTP 200.
- Realtime BookingPayload v2 preview works in the browser.
- The server-side preview endpoint works at `POST /wp-json/ws-bookings-client/v1/payload-preview`.
- The payload fixture runner passes.
- The dry-run v2 handover foundation exists at `POST /wp-json/ws-bookings-client/v1/handover-preview` and was smoke-tested with valid and invalid requests at HTTP 200.
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
- `assets/js/booking-client-form.js` - lightweight UI state, preview rendering, and REST preview submission.
- `assets/css/booking-client-form.css` - Booking Builder styling.
- `tests/fixtures/booking-payload-v2-fixtures.json` - canonical v2 fixture corpus.
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

## 9. Debug Log Policy

- Agents may read `wp-content/debug.log`.
- Agents may not write outside the repo except for temporary files in `/tmp` or the configured temp directory.
- If the page shows a critical error, fixing that becomes the immediate priority.
- A task is not complete until `wp-content/debug.log` has no fresh `ws-bookings-client` fatal after the relevant smoke test.

## 10. Agent Tiers / Workflow Expectation

- Small or free agents should stick to small implementation tasks, fixtures, or doc updates.
- Review agents should inspect diffs, tests, logs, and architecture for regressions.
- Stronger agents should handle architecture changes and risky debugging.
- Keep tasks small, reversible, and easy to verify.

## 11. Next Recommended Tasks

1. Finish or re-verify Phase 2H if any gaps remain.
2. Expand the developer fixture drawer into a proper payload test lab.
3. Add more payload fixtures, especially edge cases for return legs and stops.
4. Prepare the booking-site v2 receiver plan in `ws-bookings`.
5. Design the itinerary parent table and trip linkage for Phase 4.
6. Prepare the booking token flow for the future v2 handover path.
7. Wire a real v2 receiver in the booking plugin when that repo is ready.
8. Revisit Google autocomplete and route / toll / distance integration later.

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

## Keeping This Handoff Current

`AGENT-HANDOFF.md` is the canonical current-state document for this repo.

If the roadmap, phase status, testing workflow, or current implementation changes, update this file in the same change.
