# Audit P1 Hardening — InvMan (13 Oktober 2026)

This is an additive, fail-safe hardening of five audited production concerns.

## Database seeding
- `php artisan db:seed --force` (default `DatabaseSeeder`) now makes **no changes in production**.
- Use explicit `php artisan db:seed --class=RoleSeeder --force` during provisioning, after backup.
- Existing vehicle, room and category master records are not reset by default database seeding.
- Do not run `migrate:fresh` or `db:wipe` on databases containing live data.

## Account revocation and passwords
- New `users.is_enabled` column defaults to true, preserving access for every existing account.
- `users.active_status` is Chatify presence; it remains untouched.
- Admin users can deactivate another account from the Users resource. Revoked sessions are logged out on the next web request; disabled accounts cannot sign in again via Filament.
- Provisioning, UserResource and EditProfile enforce the same minimum 16-character mixed-case numeric/symbol password.
- Verify `/admin/login`, existing sessions, and internal messages after migration.

## Checkout compatibility
- Normal two-party and reason-coded special handover are unchanged.
- Direct checkout via `LoanRequestService::processReservation(... CheckedOut)` with no outbound receipt is disabled by default **in production**.
- For a controlled temporary transition only, `LOAN_ALLOW_LEGACY_DIRECT_CHECKOUT=true` restores the old programmatic path, but production security audit flags this as unsafe. Prefer migrating callers to `LoanCheckoutService`.
- In local/testing, compatibility is temporarily enabled by default so existing legacy regression tests remain runnable.

## Ticket maintenance
- New reporting through `TicketService::open()` accepts only active `reporting_enabled` categories, always creating maintenance V3.
- Historical V1 callers can explicitly use `TicketService::openLegacy()` to preserve old transition behavior.
- Existing tickets are not rewritten, migrated or removed.

## Deployment
1. Backup and test restoration of production database.
2. Run staging migrations, complete role, login, legacy integration, and handover tests.
3. Execute `php artisan migrate --force`, `php artisan optimize:clear`, `php artisan invman:security-audit`.
4. Run `php artisan test` and verify MySQL foreign key constraints.
5. Verify scheduler and queue worker, then roll out with `LOAN_ALLOW_LEGACY_DIRECT_CHECKOUT=false`.
