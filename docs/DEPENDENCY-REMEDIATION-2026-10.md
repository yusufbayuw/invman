# Dependency security remediation — 13 October 2026

## Scope

This change refreshes `composer.lock` and `package-lock.json` using Composer/npm dependency resolvers, **not manual lockfile editing**, while retaining Laravel 11, Filament 3 and PHP 8.2 compatibility.

| Dependency | Before | After |
|---|---|---|
| laravel/framework | v11.45.1 | v11.57.0 |
| laravel/reverb | v1.5.1 | v1.12.0 |
| livewire/livewire | v3.6.3 | v3.8.10 |
| filament/* | v3.3.30 | v3.3.57 |
| phpoffice/phpspreadsheet | 2.3.9 | 2.4.8 |
| league/commonmark | 2.7.0 | 2.10.3 |
| guzzlehttp/guzzle | 7.9.3 | 7.15.5 |
| symfony/http-foundation | v7.3.1 | v7.4.20 |

Other transitive versions also change. Refer to the actual lockfile diff and `composer install` output for the full inventory.

## Advisory baseline (as reported by GitHub Actions)

- Production Composer dependencies: **71 advisories / 21 packages → 4 advisories / 1 package** after compatible updates. **Not fully remediated**; see [issue #20](https://github.com/yusufbayuw/invman/issues/20).
- npm build/development dependency tree: **26 vulnerabilities → 8** (3 moderate, 5 high). Remaining advisories are related to Tailwind CSS v3's older transitive build dependencies and would require careful major-version migration; see [issue #21](https://github.com/yusufbayuw/invman/issues/21).
- `npm audit --omit=dev` previously reported 0 production Node vulnerabilities; the updated CI independently rechecks it.

These numbers are advisory scan snapshots, not guarantees of exploitability or safety. Always run the audits again as advisory feeds change.

## PHP compatibility safeguard

The root `composer.json` declares PHP `^8.2`, so the resolver now sets `config.platform.php=8.2.0` to avoid selecting PHP 8.3-only packages. This intentionally resolves compatible versions of image processing, OpenSpout and Excel-export dependencies. Quality Guard executes the main PHP tests on PHP 8.3 **and** the MySQL smoke job on PHP 8.2.

## Security resolution note

Composer 2.10's dependency policy blocks all Laravel 11 versions presently considered advisory-affected. **Only** the disposable one-time update runner set `COMPOSER_POLICY_ADVISORIES_BLOCK=0` for resolving compatible patches. That runner has been deleted from the final branch; no permanent exemption or ignore list was added to `composer.json`, `composer.lock`, or production CI. Normal `composer audit` remains visible as a security warning.

## Production rollout: do not upgrade blind

1. Back up application and database and verify recovery.
2. Test with the exact lockfiles in staging, including Filament user screens, Chatify, ticket V3 lifecycle, download/upload Excel/PDF, QR, asset booking, PWA and Reverb notifications.
3. Confirm server PHP version is 8.2 or later and that its extensions satisfy Composer.
4. Deploy the tested code and lockfiles together, run `composer install --no-dev --optimize-autoloader --no-interaction` and `npm ci && npm run build` **only if the server is intended to build assets**. Do not run `composer update` on the production server.
5. Run `php artisan migrate --force`, `php artisan optimize:clear`, `php artisan queue:restart` and `php artisan invman:security-audit`; validate scheduler and worker processes.
6. Continue triage of unresolved alerts, including risky spreadsheet inputs and build-time Tailwind dependency upgrades.

No application business-flow code or destructive data migrations are introduced by this dependency PR.
