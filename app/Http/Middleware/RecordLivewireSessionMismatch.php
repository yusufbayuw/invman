<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Record only safe metadata for rejected Livewire requests, then let Laravel
 * keep its normal 419 response. Never log cookies, tokens or request bodies.
 */
final class RecordLivewireSessionMismatch
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('livewire/update') || ! $request->isMethod('POST')) {
            return $next($request);
        }

        try {
            return $next($request);
        } catch (TokenMismatchException $exception) {
            $this->record($request);
            throw $exception;
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() === 419) {
                $this->record($request);
            }

            throw $exception;
        }
    }

    private function record(Request $request): void
    {
        Log::warning('invman.livewire.csrf_mismatch', [
            'host' => $request->getHost(),
            'https' => $request->isSecure(),
            'session_driver' => config('session.driver'),
            'session_cookie_present' => $request->hasCookie((string) config('session.cookie')),
            'xsrf_cookie_present' => $request->hasCookie('XSRF-TOKEN'),
            'csrf_header_present' => $request->headers->has('X-CSRF-TOKEN')
                || $request->headers->has('X-XSRF-TOKEN'),
        ]);
    }
}
