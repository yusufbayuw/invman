<?php

namespace App\Services;

final class ProductionSecurityAudit
{
    /** @return list<string> */
    public function findings(): array
    {
        $issues = [];

        if (! config('app.key')) {
            $issues[] = 'APP_KEY belum diatur.';
        }

        if (config('app.env') !== 'production') {
            return $issues;
        }

        if (config('app.debug')) {
            $issues[] = 'APP_DEBUG harus false di production.';
        }

        if (parse_url((string) config('app.url'), PHP_URL_SCHEME) !== 'https') {
            $issues[] = 'APP_URL wajib menggunakan https di production.';
        }

        if (config('session.secure') !== true) {
            $issues[] = 'SESSION_SECURE_COOKIE harus true di production.';
        }

        if (config('session.http_only') !== true) {
            $issues[] = 'SESSION_HTTP_ONLY harus true di production.';
        }

        if (! in_array(config('session.same_site'), ['lax', 'strict'], true)) {
            $issues[] = 'SESSION_SAME_SITE harus lax atau strict.';
        }

        $proxies = trim((string) config('security.trusted_proxies'));
        if ($proxies === '*' || str_contains($proxies, '0.0.0.0/0') || str_contains($proxies, '::/0')) {
            $issues[] = 'TRUSTED_PROXIES tidak boleh mengizinkan semua alamat.';
        }

        if (config('security.seed_demo_users')) {
            $issues[] = 'SEED_DEMO_USERS harus false di production.';
        }

        if (filled(config('security.seed_demo_password'))) {
            $issues[] = 'SEEDED_USER_PASSWORD tidak boleh tersedia di konfigurasi production.';
        }

        return $issues;
    }
}
