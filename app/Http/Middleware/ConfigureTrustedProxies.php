<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class ConfigureTrustedProxies
{
    /**
     * The config repository is available when handle() runs, unlike bootstrap/app.php
     * withMiddleware(), which is invoked while resolving the HTTP kernel.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $configured = trim((string) config('security.trusted_proxies', ''));
        $entries = array_values(array_filter(
            array_map('trim', explode(',', $configured)),
            fn (string $entry): bool => $entry !== '',
        ));

        // Fail closed for open/invalid proxy trust settings: never trust all
        // forwarding headers merely because an invalid value was configured.
        $trusted = [];
        foreach ($entries as $entry) {
            if (str_contains($entry, '/')) {
                $parts = explode('/', $entry, 2);
                $ip = $parts[0];
                $version = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 32
                    : (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 128 : null);
                $bits = $parts[1];
                if ($version === null || ! ctype_digit($bits)
                    || (int) $bits < 1 || (int) $bits > $version) {
                    $trusted = [];
                    break;
                }
            } elseif (! filter_var($entry, FILTER_VALIDATE_IP)) {
                $trusted = [];
                break;
            }
            $trusted[] = $entry;
        }

        Request::setTrustedProxies(
            $trusted,
            Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );

        return $next($request);
    }
}
