# Operational UAT harness

This harness uses an archived application clone under the ignored `output/operational-uat-runtime/app` directory. Never point it at an existing application database or copy a production environment file. The CI workflow creates a disposable MySQL schema, generates QA-only secrets, and keeps private runtime files outside published evidence.

The workflow `.github/workflows/operational-uat.yml` is the reproducible reference: dependencies and frontend assets come from lockfiles; the application runs behind an isolated Nginx/PHP-FPM pool; Chromium logs in normally through the actual Turnstile widget/server verification with official test keys. Authentication, licensing, CSRF, authorization and the application's login limiter remain enabled. Logins are paced at least fifteen seconds apart. Local browser runs use installed Edge by default; set `UAT_BROWSER_CHANNEL=chromium` for the CI browser.

Run the workflow on the reviewed branch to reproduce the complete synthetic QA sequence. Each PHP fixture refuses a database outside the isolated runtime or the exact CI QA schema. `prepare.php` refuses an existing environment file. Recovery refuses an existing restore target. Database dumps, generated passwords, environment files, cookie jars and signed QR payloads are not published.

The scripts deliberately distinguish evidence layers:

- `browser-operational.cjs`, `browser-loans.cjs`, `browser-work-order-retest.cjs`, `browser-cost.cjs`, `browser-hierarchy.cjs` and `browser-security.cjs` operate real browser forms and modal actions.
- `browser-invariants.php` independently reconciles the browser-created transactions, two-party receipts, stock and damaged instances.
- `transaction-probes.php` uses real MySQL worker transactions and barriers; these are service/database probes, not browser tests.
- `infra-recovery.php` executes a database queue worker, scheduler, MySQL dump/import into a new schema, table/FK comparisons and private-file restore. Log mail is a sink, not SMTP delivery proof.
- `http-load.cjs` reuses sessions established through normal browser login. It measures concurrent read requests across ten distinct identities, not 250 users or mixed transaction capacity. This limitation remains a release gate.
- `suite.php` runs PHPUnit inside the isolated clone. The latest expanded full suite required more than the local default 128 MiB; a 512 MiB test-process budget completed at 154 MiB. This is not permission to alter production PHP settings.

Results go to `docs/operational-readiness/evidence/2026-11-04`. CI moves committed historical evidence aside before the run so new artifacts cannot silently reuse old results. `source-revision.json` records the tested checkout SHA; the PR event's merge SHA in an artifact name is not substituted for that provenance. See the final report and matrix for exact coverage and remaining BLOCKED gates.
