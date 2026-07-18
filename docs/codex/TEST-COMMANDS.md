# Marketing test commands

Run from this repository unless marked “workspace parent.” Choose the narrowest relevant set.

## Static and fixture checks

```sh
find . -type f -name '*.php' -not -path './vendor/*' -exec php -l {} \;
node --check <changed-js-file>
php scripts/run-feature-gate-smoke.php
php scripts/run-form-semantics-smoke.php
php scripts/run-booking-payload-fixtures.php
php scripts/run-booking-handover-fixtures.php
php scripts/run-security-containment-tests.php
node scripts/audit-codex-operating-layer.mjs
```

Run only scripts that exist and match the changed surface. Record skips explicitly.

## WordPress and browser checks

From the workspace parent use `./scripts/wp-marketing.sh` for WordPress commands. Canonical page: `https://wolfshuttles.local/booking-builder/`. Verify form semantics, console, network, redirects, cookies, and the signed handoff destination. Inspect the marketing LocalWP site's actual `app/public/wp-content/debug.log`; do not use a plugin-relative path.

## Cross-repository contract

When payload or signed-handoff fields change, run the matching booking receiver/normalizer/security tests. Browser-computed price, availability, eligibility, booking identity, and checkout state are never authoritative.
