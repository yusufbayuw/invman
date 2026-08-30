<?php

namespace Tests\Feature;

use App\Licensing\LicenseManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_domain_wildcard_and_ip_hosts_are_allowed(): void
    {
        $this->configureValidTestLicense('portal.example.com,*.school.example.com', '127.0.0.1,192.168.0.0/16');

        $this->get('http://portal.example.com/')->assertOk();
        $this->get('http://campus.school.example.com/')->assertOk();
        $this->get('http://192.168.5.20/')->assertOk();
    }

    public function test_missing_token_returns_generic_locked_html_without_leaking_configuration(): void
    {
        config()->set('license.token', '');

        $response = $this->get('http://localhost/');

        $response
            ->assertStatus(423)
            ->assertSee('Aplikasi terkunci')
            ->assertSee('LICENSE_LOCKED')
            ->assertDontSee('localhost,127.0.0.1')
            ->assertDontSee('v1.');
    }

    public function test_json_and_major_route_groups_are_locked_before_application_logic(): void
    {
        config()->set('license.token', '');

        $this->get('http://localhost/admin')->assertStatus(423);
        $this->get('http://localhost/captive-portal')->assertStatus(423);
        $this->getJson('http://localhost/chatify/api/getContacts')
            ->assertStatus(423)
            ->assertExactJson([
                'message' => 'Application license is invalid.',
                'code' => 'LICENSE_LOCKED',
            ]);
        $this->postJson('http://localhost/livewire/update', [])->assertStatus(423);
    }

    public function test_health_check_remains_available_when_license_is_invalid(): void
    {
        config()->set('license.token', '');

        $this->get('http://unlicensed.example/up')->assertOk();
    }

    public function test_forwarded_host_cannot_spoof_an_unlicensed_host(): void
    {
        $this->configureValidTestLicense('licensed.example.com', '');

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeaders([
                'X-Forwarded-Host' => 'licensed.example.com',
            ])
            ->get('http://unlicensed.example.com/')
            ->assertStatus(423);
    }

    public function test_license_commands_work_while_locked_and_business_command_stops(): void
    {
        config()->set('license.token', '');

        $this->artisan('license:request')->assertSuccessful();
        $this->artisan('license:status')->assertFailed();
        $this->artisan('loans:expire-holds')
            ->expectsOutput('License check failed: missing_token')
            ->assertFailed();
    }

    public function test_scheduler_filter_rejects_business_schedule_when_license_is_invalid(): void
    {
        config()->set('license.token', '');
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains($event->command ?? '', 'loans:expire-holds'));

        $this->assertNotNull($event);
        $this->assertFalse($event->filtersPass($this->app));
    }

    public function test_valid_license_allows_business_command_and_schedule(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains($event->command ?? '', 'loans:expire-holds'));

        $this->assertNotNull($event);
        $this->assertTrue($event->filtersPass($this->app));
        $this->artisan('loans:expire-holds')
            ->expectsOutput('0 hold peminjaman kedaluwarsa telah diproses.')
            ->assertSuccessful();
    }

    public function test_status_uses_app_url_host_for_console_validation(): void
    {
        $this->configureValidTestLicense('licensed.example.com', '');
        config()->set('app.url', 'https://unlicensed.example.com');

        $this->assertFalse($this->app->make(LicenseManager::class)->validateAppUrl()->valid);
        $this->artisan('license:status')->assertFailed();
    }
}
