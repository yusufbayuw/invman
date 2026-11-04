<?php

namespace Tests\Feature;

use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use App\Models\G004M008Activity;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrderWorkflow;
use Database\Seeders\InvManFullDemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class InvManFullDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_demo_is_explicit_idempotent_and_preserves_existing_data(): void
    {
        config()->set('security.seed_demo_password', 'DemoSecure!2026#Password');
        $this->seed(RoleSeeder::class);
        $real = G001M001Unit::query()->create(['name' => 'Unit Production Existing']);

        $this->seed(InvManFullDemoSeeder::class);
        $demoBorrower = User::query()->where('username', 'invman.demo.sarpras.sd')->firstOrFail();
        $originalPassword = $demoBorrower->password;
        $originalRoleNames = $demoBorrower->getRoleNames()->all();
        $counts = [
            'unit' => G001M001Unit::query()->count(),
            'users' => User::query()->count(),
            'items' => G002M007Item::query()->count(),
            'instances' => G002M015ItemInstance::query()->count(),
            'drafts' => G004M008Activity::query()->count(),
            'tickets' => Ticket::query()->count(),
            'workflows' => WorkOrderWorkflow::query()->count(),
        ];

        config()->set('security.seed_demo_password', 'DifferentSecure!2026#Password');
        $this->seed(InvManFullDemoSeeder::class);

        $this->assertEquals($counts, [
            'unit' => G001M001Unit::query()->count(),
            'users' => User::query()->count(),
            'items' => G002M007Item::query()->count(),
            'instances' => G002M015ItemInstance::query()->count(),
            'drafts' => G004M008Activity::query()->count(),
            'tickets' => Ticket::query()->count(),
            'workflows' => WorkOrderWorkflow::query()->count(),
        ]);
        $this->assertSame(1, G001M001Unit::query()->whereKey($real->id)->count());
        $demoBorrower->refresh();
        $this->assertSame($originalPassword, $demoBorrower->password);
        $this->assertSame($originalRoleNames, $demoBorrower->getRoleNames()->all());
        $this->assertTrue(Hash::check('DemoSecure!2026#Password', $demoBorrower->password));
        $this->assertSame(3, $counts['items']);
        $this->assertSame(8, $counts['instances']);
        $this->assertSame(3, $counts['drafts']);
        $this->assertSame(2, $counts['tickets']);
        $this->assertSame(1, $counts['workflows']);
        $this->assertEquals(0, WorkOrderWorkflow::query()->where('status', 'published')->count());
        $this->assertEquals(0, G004M008Activity::query()->where('status', '!=', 'draft')->count());
        $this->assertEquals(0, User::query()->where('username', 'invman.demo.admin')->count());
    }

    public function test_demo_has_one_named_employee_per_role_including_optional_global_roles(): void
    {
        $this->seed(RoleSeeder::class);
        config()->set('security.seed_demo_password', 'DemoSecure!2026#Password');
        config()->set('security.full_demo_create_privileged', true);
        $this->seed(InvManFullDemoSeeder::class);

        $this->assertSame(10, User::query()->where('username', 'like', 'invman.demo.%')->count());
        $this->assertSame(1, User::role(config('role.sarpras'))->count());
        $this->assertSame(1, User::role(config('role.fasilitas'))->count());
        $this->assertSame(1, User::role(config('role.admin'))->count());
        $this->assertDatabaseMissing('users', ['username' => 'invman.demo.sarpras.sma']);
        $this->assertSame(0, User::query()->where('username', 'invman.demo.staf.fasilitas')
            ->firstOrFail()->roles()->count());
        $this->assertNotSame(
            User::query()->where('username', 'invman.demo.manager.fasilitas')->value('id'),
            User::query()->where('username', 'invman.demo.admin')->value('id'),
        );
    }

    public function test_production_direct_database_seeding_is_rejected(): void
    {
        config()->set('app.env', 'production');
        config()->set('security.seed_demo_password', 'DemoSecure!2026#Password');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invman:seed-full-demo');
        $this->seed(InvManFullDemoSeeder::class);
    }

    public function test_existing_non_demo_username_collision_aborts_without_touching_user(): void
    {
        config()->set('security.seed_demo_password', 'DemoSecure!2026#Password');
        $this->seed(RoleSeeder::class);
        $other = User::factory()->create([
            'username' => 'invman.demo.sarpras.sd',
            'email' => 'real@example.org',
        ]);
        $beforePassword = $other->password;

        try {
            $this->seed(InvManFullDemoSeeder::class);
            $this->fail('Expected a username collision rejection.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Username demo', $exception->getMessage());
        }
        $this->assertSame($beforePassword, $other->fresh()->password);
        $this->assertSame(0, G001M001Unit::query()->where('name', 'like', '[DEMO-INVMAN]%')->count());
    }
}
