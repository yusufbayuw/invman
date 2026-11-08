<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Livewire\Exceptions\LivewireReleaseTokenMismatchException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Record only safe metadata for rejected Livewire requests, then let Laravel
 * keep its 419 response and mark verified CSRF rejections. Never log cookies, tokens or request bodies.
 */
final class RecordLivewireSessionMismatch
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('livewire/update') || ! $request->isMethod('POST')) {
            return $next($request);
        }

        try {
            $response = $next($request);
            if ($response->getStatusCode() === 419 && $response->headers->get('X-Invman-CSRF-Rejected') !== '1') {
                $exception = $response->exception ?? null;
                $this->record($request, 'invman.livewire.http_419', $exception instanceof \Throwable ? $exception : null);
                if ($exception instanceof LivewireReleaseTokenMismatchException) {
                    return response()->json(['invman_session_issue' => 'component-release'], 419)
                        ->header('Cache-Control', 'no-store, private');
                }
            }

            return $response;
        } catch (TokenMismatchException $exception) {
            $this->record($request);

            throw $exception;
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() === 419) {
                $this->record($request, 'invman.livewire.http_419');
            }

            throw $exception;
        }
    }

    /** Called only when the verifier has not dispatched the protected handler. */
    public function rejectedBeforeDispatch(Request $request): Response
    {
        $this->record($request);

        return response()->json(['message' => 'Sesi halaman perlu diperbarui.'], 419)
            ->header('X-Invman-CSRF-Rejected', '1')
            ->header('Cache-Control', 'no-store, private');
    }

    private function record(Request $request, string $event = 'invman.livewire.csrf_mismatch', ?\Throwable $exception = null): void
    {
        $context = [
            'host' => $request->getHost(),
            'https' => $request->isSecure(),
            'session_driver' => config('session.driver'),
            'session_cookie_present' => $request->hasCookie((string) config('session.cookie')),
            'xsrf_cookie_present' => $request->hasCookie('XSRF-TOKEN'),
            'csrf_header_present' => $request->headers->has('X-CSRF-TOKEN')
                || $request->headers->has('X-XSRF-TOKEN'),
        ];
        if ($exception) {
            $context['exception_type'] = $exception::class;
        }
        Log::warning($event, $context);
    }
}
