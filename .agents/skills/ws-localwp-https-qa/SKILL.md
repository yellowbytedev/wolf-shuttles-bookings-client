---
name: ws-localwp-https-qa
description: Verify the marketing booking client through canonical LocalWP HTTPS origins, wrapper commands, browser evidence, and privacy-safe logs. Use for public-form QA, redirects, signed handoff smoke tests, cookies, WordPress integration, or cross-site browser behavior.
---

# WS LocalWP HTTPS QA

1. Read `../../../docs/codex/TEST-COMMANDS.md` and select the smallest relevant check.
2. Confirm LocalWP is running and use the workspace `scripts/wp-marketing.sh` wrapper for WordPress commands.
3. Use `https://wolfshuttles.local` and documented paths. Do not downgrade to HTTP or invent alternate hosts.
4. Establish a baseline, then test the success path and relevant invalid/tampered paths.
5. Inspect form semantics, console, network requests, redirects, cookies, destination behavior, and the marketing site's debug log.
6. Redact signatures, tokens, customer details, and credentials from all evidence.
7. Restore temporary settings or fixtures and report commands, outcomes, cleanup, and unverified assumptions.

Do not deploy, alter production, bypass TLS globally, or commit machine-specific settings.
