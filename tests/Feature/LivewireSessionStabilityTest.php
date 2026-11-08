<?php

namespace Tests\Feature;

use App\Http\Middleware\RecordLivewireSessionMismatch;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class LivewireSessionStabilityTest extends TestCase
{
    public function test_raw_csrf_exception_is_logged_without_tokens_and_rethrown_without_claiming_execution_status(): void
    {
        config(['session.cookie' => 'invman_session', 'session.driver' => 'database']);
        $request = Request::create('https://invman.test/livewire/update', 'POST');
        $request->cookies->set('invman_session', 'fake-test-value');
        $request->headers->set('X-CSRF-TOKEN', 'fake-test-token');

        Log::shouldReceive('warning')->once()->with(
            'invman.livewire.csrf_mismatch',
            Mockery::on(fn (array $context): bool => $context === [
                'host' => 'invman.test',
                'https' => true,
                'session_driver' => 'database',
                'session_cookie_present' => true,
                'xsrf_cookie_present' => false,
                'csrf_header_present' => true,
            ]),
        );

        $this->expectException(TokenMismatchException::class);
        (new RecordLivewireSessionMismatch)->handle(
            $request,
            fn (): Response => throw new TokenMismatchException,
        );
    }

    public function test_real_csrf_middleware_still_rejects_invalid_tokens_before_the_operation_runs(): void
    {
        $csrf = new class(app(), app('encrypter')) extends \App\Http\Middleware\VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        };
        $session = app('session.store');
        $session->start();
        $request = Request::create('https://invman.test/livewire/update', 'POST', ['_token' => 'invalid']);
        $request->setLaravelSession($session);
        $executed = 0;
        $operation = function () use (&$executed): Response {
            $executed++;

            return new Response('done');
        };
        $observer = new RecordLivewireSessionMismatch;
        $response = $observer->handle($request, fn ($request) => $csrf->handle($request, $operation));
        $this->assertSame(419, $response->getStatusCode());
        $this->assertSame('1', $response->headers->get('X-Invman-CSRF-Rejected'));
        $this->assertSame(0, $executed);
        $request->merge(['_token' => $session->token()]);
        $response = $observer->handle($request, fn ($request) => $csrf->handle($request, $operation));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($response->headers->get('X-Invman-CSRF-Rejected'));
        $this->assertSame(1, $executed);
    }

    public function test_other_routes_and_successful_livewire_requests_are_unchanged(): void
    {
        Log::shouldReceive('warning')->never();

        $middleware = new RecordLivewireSessionMismatch;
        $response = $middleware->handle(
            Request::create('https://invman.test/admin', 'GET'),
            fn (): Response => new Response('normal'),
        );
        $this->assertSame('normal', $response->getContent());

        $response = $middleware->handle(
            Request::create('https://invman.test/livewire/update', 'POST'),
            fn (): Response => new Response('ok'),
        );
        $this->assertSame('ok', $response->getContent());
    }

    public function test_panel_and_widget_polling_are_reduced_without_affecting_loan_availability(): void
    {
        $provider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
        $quickActions = file_get_contents(resource_path('views/filament/widgets/quick-actions-widget.blade.php'));
        $priorities = file_get_contents(resource_path('views/filament/widgets/operational-priority-widget.blade.php'));
        $availability = file_get_contents(resource_path('views/filament/pages/ajukan-peminjaman.blade.php'));

        $this->assertStringContainsString("databaseNotificationsPolling('60s')", $provider);
        $this->assertStringContainsString("view('filament.components.session-recovery')", $provider);
        $this->assertStringContainsString('wire:poll.visible.60s', $quickActions);
        $this->assertStringContainsString('wire:poll.visible.60s', $priorities);
        $this->assertStringContainsString('wire:poll.15s="refreshAvailability"', $availability);

        // Keep the same middleware/CSRF security behavior for expired sessions.
        $this->assertStringContainsString('VerifyCsrfToken::class', $provider);
    }
}
