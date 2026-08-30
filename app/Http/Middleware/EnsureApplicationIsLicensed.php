<?php

namespace App\Http\Middleware;

use App\Licensing\LicenseManager;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final readonly class EnsureApplicationIsLicensed
{
    public function __construct(private LicenseManager $licenses) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        if ($request->is('up')) {
            return $next($request);
        }

        $host = (string) $request->headers->get('host', '');
        $result = $this->licenses->validateHost($host);

        if ($result->valid) {
            return $next($request);
        }

        Log::warning('Application request rejected by license guard.', [
            'license_status' => $result->code->value,
            'request_host' => mb_substr($host, 0, 255),
            'license_id' => $result->context['license_id'] ?? null,
            'allowlist_fingerprint' => $result->context['fingerprint'] ?? null,
        ]);

        if ($request->expectsJson()
            || $request->is('chatify/api/*')
            || $request->is('livewire/*')
            || $request->is('broadcasting/*')) {
            return new JsonResponse([
                'message' => 'Application license is invalid.',
                'code' => 'LICENSE_LOCKED',
            ], 423);
        }

        return new Response(view('errors.license-locked'), 423);
    }
}
