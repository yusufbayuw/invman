<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

class CsrfHttpRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_actual_livewire_http_pipeline_marks_only_rejected_csrf_requests(): void
    {
        // Laravel skips CSRF during normal tests; enable the real check explicitly.
        $this->app->bind(VerifyCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends VerifyCsrfToken
            {
                protected function runningUnitTests()
                {
                    return false;
                }
            };
        });
        $this->withSession(['_token' => 'current-session-token'])->postJson('/livewire/update', ['_token' => 'old-token', 'components' => []], ['X-Livewire' => ''])
            ->assertStatus(419)->assertHeader('X-Invman-CSRF-Rejected', '1');
        Livewire::component('csrf-http-recovery-probe', CsrfHttpProbe::class);
        $html = Livewire::test(CsrfHttpProbe::class)->html(false);
        preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
        $this->assertNotEmpty($matches[1]);
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES);
        $response = $this->withSession(['_token' => 'current-session-token'])->postJson('/livewire/update', [
            '_token' => 'current-session-token', 'components' => [[
                'snapshot' => $snapshot, 'updates' => [], 'calls' => [['method' => 'increment', 'params' => []]],
            ]],
        ], ['X-Livewire' => '']);
        $response->assertOk()->assertHeaderMissing('X-Invman-CSRF-Rejected');
        $updated = json_decode($response->json('components.0.snapshot'), true);
        $this->assertSame(1, $updated['data']['counter']);
    }

    public function test_a_downstream_token_exception_after_dispatch_is_not_marked_as_safe_to_retry(): void
    {
        $this->app->bind(VerifyCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends VerifyCsrfToken
            {
                protected function runningUnitTests()
                {
                    return false;
                }
            };
        });
        $executed = 0;
        Route::post('/csrf-recovery-probe', function () use (&$executed) {
            $executed++;
            throw new TokenMismatchException('Downstream exception after an operation');
        })->middleware('web');
        $this->withSession(['_token' => 'valid-token'])->postJson('/csrf-recovery-probe', ['_token' => 'valid-token'], ['X-Livewire' => ''])
            ->assertStatus(419)->assertHeaderMissing('X-Invman-CSRF-Rejected');
        $this->assertSame(1, $executed);
    }
}

class CsrfHttpProbe extends Component
{
    public int $counter = 0;

    public function increment(): void
    {
        $this->counter++;
    }

    public function render()
    {
        return '<div>{{ $counter }}</div>';
    }
}
