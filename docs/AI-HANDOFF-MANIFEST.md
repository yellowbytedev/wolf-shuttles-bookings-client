# AI Handoff Manifest

This file lists exactly what to send to another AI chat or developer if you want to continue the Wolf Shuttles booking rebuild work elsewhere.

## What a Future AI Should Read First

1. **`START-HERE.md`** — 2-minute orientation: two-site architecture, what's done, what's forbidden, where to go next.
2. **`docs/AI-CONTEXT-HANDOFF.md`** — Full context: why the rebuild exists, terminology, business rules, architecture, roadmap, validation commands.
3. **`AGENT-HANDOFF.md`** — Canonical current-state document for the `ws-bookings-client` repo.
4. **`docs/booking-intake-roadmap.md`** — Phase 1–9 roadmap.
5. **`docs/phase-2-progress.md`** — Detailed milestone log for Phase 2.
6. **`docs/booking-site-v2-receiver-plan.md`** — The next major task: booking-site receiver plan.

## Minimum Paste Pack

If you can only paste text into an AI chat, send these five files:

| File | Purpose |
|------|---------|
| `START-HERE.md` | Entry point; orients in 2 minutes |
| `docs/AI-CONTEXT-HANDOFF.md` | Full project context, terminology, architecture, roadmap |
| `AGENT-HANDOFF.md` | Canonical current state of `ws-bookings-client` |
| `docs/booking-intake-roadmap.md` | Phase 1–9 roadmap |
| `docs/phase-2-progress.md` | Detailed Phase 2 milestone log |

## Recommended Full Zip Contents

If you can share a zip file, include everything below.

### Documentation (all `.md` files)
```
docs/
  AI-CONTEXT-HANDOFF.md          ← full context
  AI-HANDOFF-MANIFEST.md         ← this file
  booking-intake-roadmap.md
  booking-payload-v2.md
  booking-payload-v2-contract.md
  booking-site-config-contract.md
  booking-site-v2-receiver-plan.md
  phase-2-progress.md
  phase-2-marketing-foundation-review.md
  quote-preflight-draft-itinerary.md
  legacy-form-controls-audit.md
  legacy-external-services-audit.md
  vehicle-scoped-blockouts-v2.md
  ui-interaction-scaffold.md      (if it exists)
START-HERE.md                     ← root entry point
AGENTS.md                         ← agent rules
AGENT-HANDOFF.md                  ← canonical current state
```

### Core PHP source
```
inc/
  class-booking-client-form-shortcode.php
  class-booking-external-services.php
  class-booking-field-registry.php
  class-booking-payload-v2-normalizer.php
  class-booking-payload-v2-validator.php
  class-booking-payload-v2-handover-service.php
  class-booking-payload-preview-controller.php
  class-booking-payload-handover-preview-controller.php
  class-booking-intake-fixture-loader.php
  booking-client-config.php
  booking-client.php
```

### Core JS/CSS
```
assets/
  js/
    booking-client-form.js
    jquery-clock-timepicker.min.js
  css/
    booking-client-form.css
```

### Tests / Fixtures
```
tests/
  fixtures/
    booking-payload-v2-fixtures.json
scripts/
  run-booking-payload-fixtures.php
  run-booking-handover-fixtures.php
```

## What Can Be Safely Ignored Initially

A new AI/developer can skip these initially:

| Skip | Why |
|------|-----|
| `inc/legacy-snippets/` | Legacy code; never modify; use only as behavioural reference |
| `assets/js/jquery-clock-timepicker.min.js` | Vendor library; treat as black box |
| `inc/class-booking-intake-fixture-loader.php` | WP-CLI stub; not used by fixtures runners |
| `docs/ui-interaction-scaffold.md` | No-op SortableJS scaffold; drag/drop not yet implemented |
| Booking-site plugin files | Read-only for context; do not edit until receiver implementation phase |
| Google Maps / HERE API calls in legacy snippets | Active only in legacy flow; not part of new intake |

## Export Helper Script

A helper script is available at `scripts/create-ai-handoff-bundle.sh`. It creates a local zip of the key handoff docs and files.

**Usage:**
```bash
bash scripts/create-ai-handoff-bundle.sh
```

Output: `ai-handoff-bundle-<timestamp>.zip` in the repo root.

The script is safe to run; it only reads files and creates a zip. It does not modify any project files.
