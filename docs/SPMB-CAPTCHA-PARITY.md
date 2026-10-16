# CAPTCHA login: parity with SPMB

InvMan's local Filament login CAPTCHA uses the same `mortezaashrafi/filament-shield-captcha` v1.0.1 component, image UI, and default rules as `yusufbayuw/spmb`: five readable characters, case-insensitive validation, 300-second TTL, five attempts, light/dark renderings, and an inline refresh action.

The existing email-or-username login, account `is_enabled` guard, and Remember Me remain unchanged. The existing Turnstile choice (when `config('base_urls.login_turnstile')` is enabled) is retained for backwards compatibility; otherwise the SPMB-style image CAPTCHA is used.

Deployment: run `composer install --no-dev --optimize-autoloader` after pulling the changed `composer.lock`, verify PHP GD and FreeType support, then `php artisan optimize:clear`. Use a shared, persistent cache store across web nodes so challenge state is stable; the default is the configured Laravel cache store. Test the CAPTCHA on the login screen, incorrect codes, refresh, and disabled-account rejection. No database migration or credential changes are introduced.
