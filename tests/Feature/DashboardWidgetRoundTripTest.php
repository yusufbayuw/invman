<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Mechanisms\ComponentRegistry;
use Tests\TestCase;

class DashboardWidgetRoundTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_dashboard_widget_names_resolve_before_a_page_mounts_them(): void
    {
        Filament::getPanel('admin');
        $registry = app(ComponentRegistry::class);
        foreach (glob(app_path('Filament/Widgets/*.php')) as $file) {
            $class = 'App\\Filament\\Widgets\\'.basename($file, '.php');
            if (! is_subclass_of($class, Widget::class)) {
                continue;
            }
            $this->assertSame($class, $registry->getClass($registry->getName($class)), $class);
        }
    }

    public function test_dashboard_ticket_widget_can_poll_using_its_real_http_snapshot(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['is_enabled' => true]);
        $admin->assignRole(config('role.admin'));
        $page = $this->actingAs($admin)->get('/admin')->assertOk();
        preg_match_all('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
        $snapshot = null;
        foreach ($matches[1] as $encoded) {
            $json = html_entity_decode($encoded, ENT_QUOTES);
            $data = json_decode($json, true);
            if (($data['memo']['name'] ?? '') === 'app.filament.widgets.ticket-attention-widget') {
                $snapshot = $json;
                break;
            }
        }
        $this->assertNotNull($snapshot, 'The actual rendered dashboard must contain the polling widget.');
        $response = $this->postJson('/livewire/update', [
            '_token' => session()->token(),
            'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]],
        ], ['X-Livewire' => 'true']);
        $response->assertOk()->assertJsonStructure(['components' => [['snapshot', 'effects']]]);
        $updated = json_decode($response->json('components.0.snapshot'), true);
        $this->assertSame('app.filament.widgets.ticket-attention-widget', $updated['memo']['name']);
        // Later polling uses the response snapshot, not a freshly mounted component.
        for ($poll = 0; $poll < 2; $poll++) {
            $response = $this->postJson('/livewire/update', [
                '_token' => session()->token(),
                'components' => [['snapshot' => $response->json('components.0.snapshot'), 'updates' => [], 'calls' => []]],
            ], ['X-Livewire' => 'true'])->assertOk();
        }
        // The same Livewire release error must never be described as a CSRF rejection.
        $old = json_decode($response->json('components.0.snapshot'), true);
        $old['memo']['release'] = 'retired-release';
        $this->postJson('/livewire/update', [
            '_token' => session()->token(),
            'components' => [['snapshot' => json_encode($old), 'updates' => [], 'calls' => []]],
        ], ['X-Livewire' => 'true'])->assertStatus(419)->assertJsonPath('invman_session_issue', 'component-release')
            ->assertHeaderMissing('X-Invman-CSRF-Rejected');
    }
}
