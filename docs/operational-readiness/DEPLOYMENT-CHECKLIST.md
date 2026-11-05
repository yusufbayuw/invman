# Deployment checklist — InvMan operational UAT

Baseline: main `e7b8b9ac3508ab67570c8c75bf9f2c86db844676`. This checklist records gates; it does not authorize or perform deployment.

| Gate | QA evidence | Actual deployment gate |
|---|---|---|
| Hierarchical exceptions | Current main permits explicitly reasoned superior/self approval with immutable real-actor audit | BLOCKED: organizational approval of global authority, exception review and separation policy |
| Exact release revision, reviewed PR, green checks | PR #34; see final UAT report for tested SHA | BLOCKED until release owner approves the exact artifact; do not deploy a moving branch |
| PHP and required extensions | PHP 8.4 local; PHP 8.3 in Quality Guard; PHP 8.4 Nginx/FPM browser QA, MySQL/SQLite, gd, intl, sodium, mbstring, zip, bcmath, fileinfo | BLOCKED: inspect actual server version/extensions and dependency platform requirements |
| MySQL schema and upgrade | Full CI schema and synthetic legacy upgrade verified against baseline | BLOCKED: upgrade an authorized existing-data clone, check counts/FKs/history; no destructive migration on real data |
| Application key and environment | Fresh QA key, separate sessions/storage, debug disabled | BLOCKED: existing persistent APP_KEY, APP_DEBUG=false, demo seeding disabled, secure secret injection |
| License | Normal license middleware using repository example license on loopback | BLOCKED: verify actual licensed hostname and host identity; do not transfer QA settings |
| TLS/proxy/cookies | QA HTTP loopback only; authentication/CSRF remain active | BLOCKED: real TLS chain, exact trusted proxy addresses, secure/HttpOnly/SameSite cookies, consistent host/session domain |
| Private files | QA local private disk and file restore | BLOCKED: persistent private volume, ownership, deny direct public access, upload limits, retention and capacity |
| Queue | Real database queue worker with log mail sink | BLOCKED: supervised daemon, startup/restart policy, retries, failed-job alert, worker code version |
| Scheduler | Real schedule:run expires a synthetic hold | BLOCKED: installed cron/systemd timer, correct timezone, no duplicate schedulers, overlap locks and alerts |
| Notifications | QA database notifications and log-mail processing | BLOCKED: actual SMTP delivery and rejection handling; Web Push/broadcast credentials, endpoints, HTTPS permission flow |
| CAPTCHA | Official Turnstile test keys, browser widget and server verification | BLOCKED: authorized staging/production hostname and actual provider keys/availability; never disable middleware |
| Backup/recovery | Executed MySQL restore to a new database; exact table digests; private files restored | BLOCKED: operator-owned encrypted off-host backup, consistency boundary, RPO/RTO and production-equivalent restore rehearsal |
| Monitoring | Sanitized QA logs, CI status and failed jobs checked | BLOCKED: application/error/queue/disk/DB monitoring, alert destination and on-call ownership |
| Capacity | Controlled QA measurements in report, explicitly scoped | BLOCKED: agreed SLA, server specification, realistic authenticated mixed read/write workload and post-load invariants |
| Organization/workflow | Synthetic organization uses existing roles, titles and explicit coverage | BLOCKED: actual job holders, supervisors, central/unit coverage, absent/inactive replacements, cost approval separation and workflow publication review |

## Approved release procedure (run only after explicit deployment approval)

1. Freeze the exact reviewed revision and dependency lockfiles. Keep main unchanged during verification. Verify artifact checksums and successful Quality Guard/UAT runs for that revision.
2. Rehearse the upgrade and restore on a fresh non-production database and private volume. Record inventory/reservation counts, revision/approval history, job-title assignments and foreign-key checks.
3. Confirm the maintenance/write-freeze window, recovery owner, RPO/RTO and communications plan. Produce a consistent DB + private-volume + code/config backup before DDL.
4. Prepare dependencies/assets from lockfiles. Run Composer publish scripts so Livewire/Filament assets match the installed vendor versions. Do not reuse stale bootstrap/provider caches from another runtime.
5. With approval, apply reviewed forward migrations. Run the security audit with real deployment configuration without printing secrets. Build application caches only after secrets/host/proxy/session configuration is correct.
6. Restart workers gracefully (`php artisan queue:restart` with a supervising process manager) so jobs use the released code. Verify the scheduler, workers and persistent private disk independently.
7. Run normal-login browser smoke tests with distinct synthetic borrower/manager accounts; check one complete loan and Work Order, private PDF authorization, CSRF rejection, notifications and post-action UI state.
8. Inspect logs, failed jobs, stock/reservation invariants and monitoring. Open writes only when all critical gates have objective evidence and an owner accepts the release.

Do not treat CI configuration as proof that any production service is running. No merge, deployment, production configuration change, or real-data operation was performed by this audit.
