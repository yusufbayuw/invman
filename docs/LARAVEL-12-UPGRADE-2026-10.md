# Laravel 12 security upgrade — 13 October 2026

## Summary

- Upgrade the InvMan framework from Laravel 11.57.0 to **Laravel 12.69.3**, retaining PHP **8.2** as the minimum supported platform and Filament **3.3.57**.
- The prior 4 Composer security advisories (all in Laravel 11) fall to **0 published production Composer advisories**, based on the dependency audit at implementation time. Re-run audits at deployment.
- Update `solution-forest/filament-simplelightbox` **0.0.10 → 0.0.11**, the last Filament 3 series; its old Illuminate 10/11-only constraint prevented Laravel 12. The feature is still used in floor, building, asset checklist and loan review UI.
- Update `dominion-solutions/filament-captcha` **0.1.0 → 0.2.0**, preserving the CAPTCHA component in `app/Filament/Auth/Login.php`. Both Cloudflare Turnstile (when enabled) and fallback image CAPTCHA paths remain present.
- Remove **`hayderhatem/filament-excel-import`**, which was in `composer.json` but has no references in application source. Its constraint forbade Laravel 12. This removal also prunes PhpSpreadsheet because no other installed dependency requires it; it does **not** remove the existing Filament export package `alperenersoy/filament-export` or `spatie/simple-excel`.
- Dependency resolutions were performed by Composer under `config.platform.php=8.2.0`. No fabricated lockfile version or committed Composer vulnerability policy bypass.

## Compatibility and rollout

This is a framework and dependency upgrade, not an app business-flow refactor. No destructive database migration was added, but PHP/library behavior may change. Test on a staging copy before production deployment.

Recommended staging checklist:
1. Verify PHP CLI and PHP-FPM >= 8.2, supported extensions, backups and correct APP_ENV / APP_DEBUG / HTTPS.
2. Deploy **the full source including composer.json and composer.lock** to staging; run `composer install --no-dev --optimize-autoloader --no-interaction` under the intended PHP binary; **never `composer update` on the production server**.
3. Run `php artisan optimize:clear`, `php artisan migrate --force` if needed and `php artisan filament:upgrade`, then restart queue workers and long-lived Reverb services where used.
4. Log in using the **fallback image CAPTCHA** and Turnstile configurations; verify disabled-account rejection, profile password change, Filament resources, Shield role checks, asset photo previews and checklist evidence preview.
5. Exercise stock availability, vehicle/room bookings, two-party handover, ticket maintenance workflows and SLA, notifications, export CSV/Excel/PDF, and any bulk operations.
6. Run `php artisan test`, `php artisan invman:security-audit`, and `composer audit --locked --no-dev --abandoned=ignore`; inspect queue logs after staging rollout.
7. Ensure Vite assets built from the checked-in npm lockfile (either build in CI and deploy artifacts or run `npm ci && npm run build` in a controlled build environment).

CI covers SQLite suite, MySQL schema / role boundary smoke on PHP 8.2, npm production-only audit and Vite build. Passing automated tests does **not** substitute for manual staging checks on real Filament/Livewire screens.

## Rollback

If staging or deployment detects regression, restore the **matching previous code and composer.lock** (main before this change) and run `composer install` against that lockfile. Restore tested build assets. No new migration in this PR requires a database rollback; nonetheless keep DB backups before touching a production system.

See [dependency security baseline](DEPENDENCY-REMEDIATION-2026-10.md) and [issue #21](https://github.com/yusufbayuw/invman/issues/21) for remaining npm build dependencies.
