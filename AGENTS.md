# Marketing booking-client instructions

This repository owns the public booking form, marketing-side UX, payload construction, and signed handoff initiation. It does not own authoritative price, availability, eligibility, booking identity, or checkout state.

## Before editing

Read `docs/codex/CURRENT-STATE.md`, `docs/codex/TEST-COMMANDS.md`, and the task's approved specification. For cross-repository work, use the canonical references in `docs/codex/README.md`.

## Rules

- Preserve existing public-form behavior unless the approved task changes it.
- Treat all browser state as untrusted. Never expose secrets, signing keys, raw signed handoffs, or customer data in logs or fixtures.
- Keep the signed handoff and payload contract compatible with the booking repository; use `$ws-handoff-authority-review` for boundary work.
- Use `$ws-localwp-https-qa` for browser or WordPress verification and `$ws-safe-git-promotion` only for promotion preparation.
- Use canonical LocalWP HTTPS surfaces and the parent wrapper scripts for WordPress operations.
- Do not push, merge, deploy, or begin a later booking phase without explicit authority.
- Do not rewrite unrelated user changes. One writer per worktree; subagents should be bounded and read-only by default.

## Verification

Start with the smallest relevant commands in `docs/codex/TEST-COMMANDS.md`. Run `node scripts/audit-codex-operating-layer.mjs` after instruction or skill changes. Keep operating-layer commits separate from runtime changes.
