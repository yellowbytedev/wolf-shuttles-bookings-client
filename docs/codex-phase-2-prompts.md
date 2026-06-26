# Codex Phase 2 Prompts

## Prompt 1 — Documentation, skeleton, and no behaviour changes

Use this first. It should create the foundation without changing the existing legacy form behaviour.

```text
You are working in the Wolf Shuttles marketing plugin repository: `ws-bookings-client`.

Current phase: Phase 2 — booking intake foundation.

Read these files first:
- AGENTS.md
- docs/booking-intake-current-flow.md
- docs/booking-payload-v2.md
- docs/booking-intake-roadmap.md
- docs/testing-engine-plan.md
- docs/known-issues-debug-log.md

Important constraints:
- Do not break or replace the current legacy Bricks/Fluent-snippet booking form.
- Do not remove any legacy snippet files.
- Do not use Bricks hashed field IDs as the new data contract.
- Do not make hidden fields the source of truth.
- Keep JavaScript light; canonical validation/normalisation/payload building belongs in PHP.
- One-way and return transfer support are required first.
- Charter should be scaffolded in the schema but does not need a finished UI in this step.
- Additive changes only unless a change is required for safe loading.

Task:
1. Inspect the current plugin structure and legacy snippets folder.
2. Add a clean Phase 2 skeleton for the new intake layer.
3. Create PHP class/file stubs for:
   - BookingPayloadBuilder
   - BookingPayloadNormalizer
   - BookingPayloadValidator
   - BookingSiteClient
   - BookingClientFormShortcode
   - optional HandoverMode/Settings helper if useful
4. Register a shortcode named `[ws_booking_client_form]` that renders a minimal placeholder/shell form only. It must not submit real bookings yet.
5. Enqueue placeholder JS/CSS for the shortcode only when the shortcode is present.
6. Add a small setting/config constant or filter for handover mode with allowed values:
   - `legacy_hash`
   - `v2_token`
7. Add a minimal fixture-loader or test-runner scaffold that can read `tests/fixtures/booking-intake-fixtures.v2.seed.json`, but it does not need to fully validate or submit yet.
8. Update or create a short implementation note in `docs/phase-2-progress.md` explaining what was added and what remains.

Acceptance criteria:
- Existing legacy booking form still works unchanged.
- `[ws_booking_client_form]` renders on a new page without PHP errors.
- No live handover behaviour is changed.
- New files/classes are namespaced or prefixed consistently with the plugin.
- `php -l` passes for all new PHP files.
- `git diff` is easy to review and mostly additive.
```

## Prompt 2 — Payload v2 builder/normaliser/validator

Use only after Prompt 1 has been reviewed.

```text
Implement BookingPayload v2 building, normalisation, and validation for one-way and return transfer submissions from the shortcode form. Keep legacy Bricks form untouched. The shortcode form may submit to a plugin-owned endpoint/action. Do not POST to the booking site yet; first return/debug-log the normalised payload locally.
```

## Prompt 3 — Google Places and route metadata

Use only after Prompt 2 has been reviewed.

```text
Wire the shortcode form to Google Places autocomplete and the existing Google/HERE proxy concepts. Keep JavaScript focused on UI and route metadata collection. PHP remains the final normaliser and validator.
```

## Prompt 4 — Handover mode testing

Use only after Prompt 3 has been reviewed.

```text
Add handover support for both `legacy_hash` and `v2_token` development modes. In `legacy_hash`, convert v2 into the current legacy handover shape where needed. In `v2_token`, prepare the future booking-site URL format without requiring booking-site v2 support yet.
```
