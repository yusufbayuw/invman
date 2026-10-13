# InvMan — LIST: Inventory, Loans, Facilities & Ticketing

[![InvMan Quality Guard](https://github.com/yusufbayuw/invman/actions/workflows/ci.yml/badge.svg)](https://github.com/yusufbayuw/invman/actions/workflows/ci.yml)

A Laravel 11 + Filament 3 system for facility and asset inventory, requests/loans,
vehicle crews, QR-based handover, maintenance/ticketing, and audit history.

## Features

- Item master + instance inventory; rooms, units, floors, buildings and vehicles.
- Full and quick loan requests; availability, scheduled holds, approvals, borrower/manager handover and returns.
- Vehicle driver assignment and internal assistant/kenek.
- Private ticket attachments and PDF handover/maintenance evidence, with permission-scoped access.
- V3 maintenance: approval, technician acceptance, diagnostics, materials, requester verification, facility signoff, SLA escalation and asset safety holds.
- Historical V1 tickets are preserved. New reports use enabled V3 categories.
- Database/push notifications, Chatify, Filament access roles and offline license checks.

## Stack and prerequisites

- PHP **8.2+** with extensions appropriate for Composer/Laravel (CI: PHP 8.3).
- Composer 2, Node.js 22 and npm.
- MySQL 8 for production (CI tests SQLite and checks MySQL migration/foreign keys).
- A working Laravel **queue worker and scheduler**, plus valid application license.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configure DB_*, APP_URL, LICENSE_* and other relevant variables in .env.
php artisan migrate
npm ci
npm run build
php artisan serve
```

Do not treat the example environment as a production-ready configuration.
Never publish `.env` or live license tokens. License details:
[offline license documentation](docs/LISENSI.md).

## Initial admin and operations

- For live environments provision an admin interactively:
  `php artisan invman:provision-admin USERNAME EMAIL`.
  The command prompts for a strong password without printing it.
- `php artisan db:seed` is a **no-op in production**. Run only
  reviewed, explicitly named reference seeders after a database backup;
  do not seed demonstration vehicles/rooms/users into a populated production database.
- Keep `APP_DEBUG=false`, `APP_URL=https://...`,
  `SESSION_SECURE_COOKIE=true` and narrowly scoped `TRUSTED_PROXIES`.
- Keep `LOAN_ALLOW_LEGACY_DIRECT_CHECKOUT=false` in production.
- Run `php artisan invman:security-audit` before go-live.

## Queue worker, scheduler, and deployment

**Both** a long-running queue worker and scheduler are required. A queue worker
by itself will not expire pending loan holds, issue periodic overdue notices,
or escalate ticket SLAs.

Example commands (adapt PHP binary and project path to your server):

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
php artisan schedule:run
```

Run the worker under Supervisor (or systemd), and invoke scheduler *once per
minute* through system cron. The cron expression belongs in the scheduler
UI, not in the shell command field:

```cron
* * * * * cd /path/to/invman && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

The `routes/console.php` scheduler manages hold expiry every minute and
overdue-loan notifications and ticket escalations hourly. Restart workers after
a release (`php artisan queue:restart`); ensure the worker user can write
`storage/` and `bootstrap/cache/`.

Recommended zero-data-loss release sequence:

1. Backup the database and verify restore viability.
2. Deploy exactly one tested `main` commit and build frontend assets.
3. Run `php artisan migrate --force` (never `migrate:fresh` on live data).
4. Run `php artisan optimize:clear` and `php artisan invman:security-audit`.
5. Restart queue workers; verify cron, notification delivery and roles.
6. Smoke-test a loan and return, ticket report + repair, and protected PDF endpoints.

**Ticket SLA calendar:** configure `TICKET_SLA_TIMEZONE`,
`TICKET_SLA_WORKDAY_START`, `TICKET_SLA_WORKDAY_END`,
`TICKET_SLA_WORKING_WEEKDAYS`, and `TICKET_SLA_HOLIDAYS` in `.env`
to match approved office days/holidays; update yearly.
SLA deadlines are saved when tickets are created; changing the calendar
does not retroactively rewrite open ticket deadlines.

## Quality gates

```bash
php artisan test
composer audit --locked --no-dev --abandoned=ignore
npm audit --omit=dev --audit-level=high
npm run build
```

GitHub Actions also lint-checks PHP, exercises SQLite feature tests,
and validates schema plus historical foreign-key guards on MySQL 8.

## Further documentation

- [P1 audit/security rollout](docs/P1-AUDIT-HARDENING.md)
- [October 2026 dependency security updates and residual advisories](docs/DEPENDENCY-REMEDIATION-2026-10.md)
- [Laravel 12 framework security upgrade, staging checks and rollback](docs/LARAVEL-12-UPGRADE-2026-10.md)
- [Two-party outbound handover](docs/OUTBOUND-HANDOVER-V2.md)
- [GitHub Actions CI](.github/workflows/ci.yml)
- [Ticket SLA and maintenance configuration](config/ticket_sla.php)

## Upgrade safety

New code must preserve audit history, reservation state transitions, item-instance
allocation and return verification. Avoid destructive seeds or migrations,
bypassing both signatures, and global data access outside unit/manager scopes.
