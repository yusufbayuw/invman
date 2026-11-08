<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Session\TokenMismatchException;

class VerifyCsrfToken extends \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken
{
    public function handle($request, Closure $next)
    {
        $dispatched = false;
        try {
            return parent::handle($request, function ($request) use ($next, &$dispatched) {
                $dispatched = true;

                return $next($request);
            });
        } catch (TokenMismatchException $exception) {
            // Catch at the verifier, before Laravel's routing pipeline renders
            // the exception. A downstream exception is not proof of rejection.
            if ($dispatched || ! $request->isMethod('POST')
                || ! ($request->is('livewire/update') || $request->headers->has('X-Livewire'))) {
                throw $exception;
            }

            return app(RecordLivewireSessionMismatch::class)->rejectedBeforeDispatch($request);
        }
    }
}
