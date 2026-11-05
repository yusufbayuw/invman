<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Filament\Forms\Components\ShieldCaptcha;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use MortezaAshrafi\FilamentShieldCaptcha\CaptchaManager;
use Tests\TestCase;

class AuthSessionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_existing_session_is_signed_out_on_admin_and_public_panel_requests(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(config('role.sarpras'));
        foreach (['/admin', '/'] as $url) {
            $user->update(['is_enabled' => false]);
            $this->actingAs($user)->get($url)->assertForbidden();
            $this->assertGuest();
            $user->update(['is_enabled' => true]);
            $this->get('/admin')->assertRedirect('/admin/login');
        }
    }
    private function attempt(User $user, string $identifier): \Livewire\Features\SupportTesting\Testable
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        config(['base_urls.login_turnstile' => false]);
        $page = Livewire::test(Login::class);
        $field = collect($page->instance()->form->getFlatComponents())->first(fn ($field) => $field instanceof ShieldCaptcha);
        $this->assertNotNull($field);
        // Unit fixture only: use the generated challenge held by the test process.
        // No browser session, external CAPTCHA service or production challenge is accessed.
        $manager = app(CaptchaManager::class);
        $challenge = $manager->ensureChallenge($field->getCaptchaContextKey(), $manager->optionsFromConfig([]));
        return $page->fillForm(['email' => $identifier, 'password' => 'Synthetic!Auth2026', 'captcha' => $challenge->answer])
            ->call('authenticate');
    }

    public function test_normal_filament_login_regenerates_session_for_enabled_user(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['username' => 'qa.session', 'password' => 'Synthetic!Auth2026']);
        $user->assignRole(config('role.sarpras'));
        $before = session()->getId();
        $this->attempt($user, $user->username)->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
    }

    public function test_disabled_user_cannot_authenticate_even_with_valid_password_and_local_challenge(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['password' => 'Synthetic!Auth2026', 'is_enabled' => false]);
        $user->assignRole(config('role.sarpras'));
        $this->attempt($user, $user->email)->assertHasFormErrors(['email']);
        $this->assertGuest();
    }
}

