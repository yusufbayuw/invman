<?php

namespace App\Http\Middleware;

use App\Services\ApplicationSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureChatEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(ApplicationSettings::class)->chatEnabled(), 403, 'Chat sedang dinonaktifkan.');

        return $next($request);
    }
}
