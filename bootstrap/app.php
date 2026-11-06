<?php

use App\Http\Middleware\EnsureApplicationIsLicensed;
use App\Http\Middleware\EnsureAccountEnabled;
use App\Http\Middleware\RecordLivewireSessionMismatch;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\ConfigureTrustedProxies;
use Illuminate\Http\Middleware\TrustProxies;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // The HTTP kernel is resolved before the config repository is bootstrapped.
        // Read security.trusted_proxies at request-time, not in this callback.
        $middleware->replace(TrustProxies::class, ConfigureTrustedProxies::class);
        // Observe CSRF failures without weakening Laravel or Livewire protection.
        $middleware->web(prepend: [RecordLivewireSessionMismatch::class]);
        $middleware->prepend(EnsureApplicationIsLicensed::class);
        // Runs after web session middleware and signs out revoked accounts on the next request.
        $middleware->web(append: [EnsureAccountEnabled::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
