<?php

namespace Tests\Feature;

use App\Models\G001M001Unit;
use App\Models\G004M008Activity;
use App\Models\User;
use App\Services\AdminProvisioner;
use App\Services\ProductionSecurityAudit;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;
use Database\Seeders\UserSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_accounts_are_opt_in_and_production_always_disables_them(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(UnitSeeder::class);

        $this->seed(UserSeeder::class);
        $this->assertSame(0, User::query()->count());

        config()->set('security.seed_demo_users', true);
        config()->set('security.seed_demo_password', 'DemoPass!2026#Secure');
        config()->set('app.env', 'production');
        $this->seed(UserSeeder::class);
        $this->assertSame(0, User::query()->count());

        config()->set('app.env', 'testing');
        $this->seed(UserSeeder::class);
        $this->assertSame(8, User::query()->count());
    }

    public function test_reseeding_never_resets_existing_password_role_or_identity(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(UnitSeeder::class);
        config()->set('security.seed_demo_users', true);
        config()->set('security.seed_demo_password', 'DemoPass!2026#Secure');
        $this->seed(UserSeeder::class);

        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $admin->update(['name' => 'Managed Administrator', 'password' => 'AnotherSecure!2026Password']);
        $admin->syncRoles([config('role.fasilitas')]);

        config()->set('security.seed_demo_password', 'ReplacedPassword!2026');
        $this->seed(UserSeeder::class);
        $admin->refresh();

        $this->assertSame(8, User::query()->count());
        $this->assertSame('Managed Administrator', $admin->name);
        $this->assertTrue(Hash::check('AnotherSecure!2026Password', $admin->password));
        $this->assertFalse(Hash::check('ReplacedPassword!2026', $admin->password));
        $this->assertTrue($admin->hasRole(config('role.fasilitas')));
    }

    public function test_weak_demo_password_is_rejected(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(UnitSeeder::class);
        config()->set('security.seed_demo_users', true);
        config()->set('security.seed_demo_password', 'password');

        $this->expectException(ValidationException::class);
        $this->seed(UserSeeder::class);
    }

    public function test_admin_provisioning_requires_strong_credentials_and_never_resets_existing_users(): void
    {
        $this->seed(RoleSeeder::class);
        $service = app(AdminProvisioner::class);
        $created = $service->provision('primary.admin', 'primary@invman.test', 'Provisioning!Secure2026');

        $this->assertTrue($created->hasRole(config('role.admin')));
        $this->assertTrue(Hash::check('Provisioning!Secure2026', $created->password));

        try {
            $service->provision('primary.admin', 'different@invman.test', 'Different!Secure2026');
            $this->fail('Provisioning must not modify an existing username.');
        } catch (ValidationException) {
            // Existing admin credentials remain unchanged.
        }

        $this->assertTrue(Hash::check('Provisioning!Secure2026', $created->fresh()->password));
        $this->assertSame(1, User::query()->count());
    }

    public function test_unguarded_models_are_disabled_and_record_identifiers_cannot_be_mass_assigned(): void
    {
        $this->assertFalse(\Illuminate\Database\Eloquent\Model::isUnguarded());
        $this->expectException(MassAssignmentException::class);

        G004M008Activity::query()->create([
            'id' => 'forged-record-id',
            'name' => 'Not allowed',
            'status' => 'draft',
        ]);
    }

    public function test_admin_panel_requires_operational_role_or_managed_assets(): void
    {
        $this->seed(RoleSeeder::class);
        $panel = Filament::getPanel('admin');
        $user = User::factory()->create();

        $this->assertFalse($user->canAccessPanel($panel));
        $user->assignRole(config('role.sarpras'));
        $this->assertTrue($user->canAccessPanel($panel));
    }

    public function test_production_audit_rejects_debug_http_insecure_cookies_and_open_proxies(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('app.debug', true);
        config()->set('app.url', 'http://invman.test');
        config()->set('session.secure', false);
        config()->set('session.http_only', false);
        config()->set('session.same_site', 'none');
        config()->set('security.trusted_proxies', '*');
        config()->set('security.seed_demo_users', true);

        $issues = app(ProductionSecurityAudit::class)->findings();
        $this->assertGreaterThanOrEqual(6, count($issues));

        config()->set('app.debug', false);
        config()->set('app.url', 'https://invman.test');
        config()->set('session.secure', true);
        config()->set('session.http_only', true);
        config()->set('session.same_site', 'lax');
        config()->set('security.trusted_proxies', '127.0.0.1,::1');
        config()->set('security.seed_demo_users', false);
        config()->set('security.seed_demo_password', null);
        $this->assertSame([], app(ProductionSecurityAudit::class)->findings());
    }
}
