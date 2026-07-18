---
name: ws-safe-git-promotion
description: Prepare the marketing booking-client branch for evidence-based promotion with boundary checks, focused commits, tests, and rollback notes. Use for checkpointing, committing, comparing, or readiness assessment; push, merge, tag, and deploy require separate explicit authority.
---

# WS Safe Git Promotion

1. Resolve the physical repository root, branch, HEAD, upstream, and worktree status.
2. Confirm the authorized terminal action; preparation or committing never implies push, merge, tag, deploy, or deletion.
3. Inspect every diff and untracked file. Preserve unrelated work and stop on unexplained overlap.
4. Run `../../../docs/codex/TEST-COMMANDS.md` checks relevant to the change and the operating-layer audit.
5. Check for secrets, signed values, customer data, local paths, generated artifacts, and unintended contract changes.
6. Stage explicit paths and create focused commits. Keep operating guidance separate from runtime work.
7. Re-check status and document commits, tests, risks, and rollback.

Never rewrite shared history or perform destructive cleanup without explicit, target-specific approval.
