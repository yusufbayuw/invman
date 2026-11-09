<?php

namespace Tests\Feature;

use App\Models\AssetRelation;
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
            'relations' => AssetRelation::query()->count(),
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
            'relations' => AssetRelation::query()->count(),
            'drafts' => G004M008Activity::query()->count(),
            'tickets' => Ticket::query()->count(),
            'workflows' => WorkOrderWorkflow::query()->count(),
        ]);
        $this->assertSame(1, G001M001Unit::query()->whereKey($real->id)->count());
        $demoBorrower->refresh();
        $this->assertSame($originalPassword, $demoBorrower->password);
        $this->assertSame($originalRoleNames, $demoBorrower->getRoleNames()->all());
        $this->assertTrue(Hash::check('DemoSecure!2026#Password', $demoBorrower->password));
        $this->assertSame(25, $counts['items']);
        $this->assertSame(28, $counts['instances']);
        $this->assertSame(12, $counts['relations']);
        $this->assertStructure('INVMAN-DEMO-SD-STRUCT-');
        $this->assertStructure('INVMAN-DEMO-SMA-STRUCT-');
        $this->assertSame(3, $counts['drafts']);
        $this->assertSame(2, $counts['tickets']);
        $this->assertSame(1, $counts['workflows']);
        $this->assertEquals(0, WorkOrderWorkflow::query()->where('status', 'published')->count());
        $this->assertEquals(0, G004M008Activity::query()->where('status', '!=', 'draft')->count());
        $this->assertEquals(0, User::query()->where('username', 'invman.demo.admin')->count());
    }

    private function assertStructure(string $prefix): void
    {
        $bulk = G002M007Item::query()->where('code', $prefix.'BULK')->firstOrFail();
        $this->assertSame('bulk', $bulk->tracking_mode);
        $this->assertSame(80, (int) $bulk->quantity);
        $this->assertSame(80, (int) $bulk->available_quantity);
        $this->assertSame(0, $bulk->item_instance()->count());

        $links = AssetRelation::query()
            ->whereHas('parentInstance.item', fn ($query) => $query->where('code', 'like', $prefix.'%'))
            ->get();
        $this->assertCount(6, $links);
        $this->assertSame(['bundle' => 2, 'component' => 2, 'contained' => 1, 'installed' => 1],
            $links->groupBy('relation_type')->map->count()->sortKeys()->all());
        foreach ($links as $link) {
            $this->assertSame($link->parentInstance->g001_m001_unit_id, $link->childInstance->g001_m001_unit_id);
            $this->assertSame($link->parentInstance->item->g002_m003_item_management_id,
                $link->childInstance->item->g002_m003_item_management_id);
            if (in_array($link->relation_type, ['component', 'installed'], true)) {
                $this->assertFalse((bool) $link->childInstance->is_borrowable);
            }
            if ($link->relation_type === 'bundle') {
                $this->assertFalse((bool) $link->parentInstance->is_borrowable);
            }
        }
    }

    public function test_existing_demo_item_changes_are_not_silently_overwritten(): void
    {
        config()->set('security.seed_demo_password', 'DemoSecure!2026#Password');
        $this->seed(RoleSeeder::class);
        $this->seed(InvManFullDemoSeeder::class);
        $item = G002M007Item::query()->where('code', 'INVMAN-DEMO-SD-STRUCT-BULK')->firstOrFail();
        $item->update(['name' => '[DEMO-INVMAN] Ubah nama oleh operator']);
        $original = $item->fresh()->name;
        try {
            $this->seed(InvManFullDemoSeeder::class);
            $this->fail('Seeder must not silently overwrite an edited inventory item.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('tidak ditimpa', $exception->getMessage());
        }
        $this->assertSame($original, $item->fresh()->name);
        $this->assertSame(12, AssetRelation::query()->count());
    }

    public function test_demo_has_one_named_employee_per_role_including_optional_global_roles(): void
    {
        $this->seed(RoleSeeder::class);
        config()->set('security.seed_demo_password', 'DemoSecure!2026#Password');
        config()->set('security.full_demo_create_privileged', true);
        $this->seed(InvManFullDemoSeeder::class);

        $this->assertSame(10, User::query()->where('username', 'like', 'invman.demo.%')->count());
        $this->assertSame(1, WorkOrderWorkflow::query()->where('status', 'published')->count());
        $this->assertSame(2, Ticket::query()->where('source_key', 'like', 'invman:full-demo:%')->count());
        foreach (Ticket::query()->where('source_key', 'like', 'invman:full-demo:%')->get() as $ticket) {
            $this->assertSame(4, $ticket->workOrderApprovals()->count());
        }

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
