<?php

use App\Http\Middleware\EnsureApplicationIsLicensed;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Restrict X-Forwarded-* headers to explicitly trusted reverse proxies.
        $trustedProxies = array_values(array_filter(array_map('trim', explode(',', (string) config('security.trusted_proxies', '')))));
        $middleware->trustProxies(
            at: $trustedProxies,
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );
        $middleware->prepend(EnsureApplicationIsLicensed::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
