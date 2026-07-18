---
name: ws-handoff-authority-review
description: Review the marketing-to-booking signed handoff, field ownership, trust boundaries, and booking authority. Use for payload fields, signatures, redirect contracts, public-form state, booking receiver compatibility, or any cross-repository handoff change.
---

# WS Handoff Authority Review

1. Identify the marketing producer, booking consumer, contract version, signature boundary, and current canonical contract references in `../../../docs/codex/README.md`.
2. Inventory each field's source, normalization, optionality, sensitivity, and authoritative owner.
3. Treat browser values as untrusted. Price, availability, eligibility, booking identity, and checkout state remain booking-side decisions.
4. Verify canonical serialization, signature coverage, expiry/replay behavior, allowlisted destinations, size limits, and privacy-safe errors.
5. Test valid, missing, malformed, duplicated, stale, tampered, and backward-compatible payloads as relevant.
6. Run matching fixtures in both repositories when a shared field changes.
7. Report compatibility, migration needs, evidence, and unresolved authority questions before implementation proceeds.

Never expose a signing key, raw production signature, or real customer payload in evidence.
