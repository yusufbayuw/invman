<?php

namespace Tests\Feature;

use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use App\Models\G004M008Activity;
use App\Models\User;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderWorkflow;
use Database\Seeders\InvManRoleMatrixDemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class InvManRoleMatrixDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private function enableDemo(): void
    {
        config()->set('security.seed_demo_users', true);
        config()->set('security.seed_demo_password', 'SafeTest!Demo2026#Password');
        $this->seed(RoleSeeder::class);
    }

    public function test_demo_is_opt_in_and_refuses_production(): void
    {
        config()->set('security.seed_demo_password', 'SafeTest!Demo2026#Password');
        $this->expectException(RuntimeException::class);
        $this->seed(InvManRoleMatrixDemoSeeder::class);
    }

    public function test_demo_refuses_production_even_with_opt_in(): void
    {
        $this->enableDemo();
        config()->set('app.env', 'production');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('production');
        $this->seed(InvManRoleMatrixDemoSeeder::class);
    }

    public function test_it_creates_five_units_ten_users_and_scoped_inventory_without_submissions(): void
    {
        $this->enableDemo();
        $this->seed(InvManRoleMatrixDemoSeeder::class);

        $this->assertSame(5, G001M001Unit::query()->where('name', 'like', '[DEMO-ORG]%')->count());
        $this->assertSame(10, User::query()->where('username', 'like', 'invman.orgdemo.%')->count());
        $this->assertSame(5, User::query()->where('username', 'like', 'invman.orgdemo.sarpras.%')->count());
        $this->assertSame(15, G002M007Item::query()->where('code', 'like', 'INVMAN-ORG-%')->count());
        $this->assertSame(45, G002M015ItemInstance::query()->whereHas('item',
            fn ($query) => $query->where('code', 'like', 'INVMAN-ORG-%'))->count());
        $this->assertSame(5, G004M008Activity::query()->where('name', 'like', '[DEMO-ORG]%')->count());
        $this->assertSame(0, G004M008Activity::query()->where('name', 'like', '[DEMO-ORG]%')
            ->where('status', '!=', 'draft')->count());
        $this->assertSame(5, WorkOrderWorkflow::query()->where('status', 'draft')->count());
        $this->assertSame(0, WorkOrderWorkflow::query()->where('status', 'published')->count());

        foreach (['tk', 'sd', 'smp', 'sma', 'universitas'] as $slug) {
            $borrower = User::query()->where('username', 'invman.orgdemo.sarpras.'.$slug)->firstOrFail();
            $this->assertTrue($borrower->isSarpras());
            $this->assertNotNull($borrower->g001_m001_unit_id);
        }
        foreach (['staf', 'supervisor', 'manager'] as $suffix) {
            $facility = User::query()->where('username', 'invman.orgdemo.fasilitas.'.$suffix)->firstOrFail();
            $this->assertTrue($facility->isFacility());
        }

        $it = User::query()->where('username', 'invman.orgdemo.pengelola.it')->firstOrFail();
        $library = User::query()->where('username', 'invman.orgdemo.pengelola.perpustakaan')->firstOrFail();
        $this->assertFalse($it->isFacility());
        $this->assertFalse($library->isFacility());
        $this->assertSame(1, $it->itemManagements()->count());
        $this->assertSame(1, $library->itemManagements()->count());
        $this->assertNotSame($it->itemManagements()->firstOrFail()->id,
            $library->itemManagements()->firstOrFail()->id);

        $itAsset = G002M007Item::query()->where('code', 'INVMAN-ORG-SD-IT')->firstOrFail();
        $libAsset = G002M007Item::query()->where('code', 'INVMAN-ORG-SD-LIB')->firstOrFail();
        $this->assertTrue($it->itemManagements()->whereKey($itAsset->g002_m003_item_management_id)->exists());
        $this->assertFalse($it->itemManagements()->whereKey($libAsset->g002_m003_item_management_id)->exists());
    }

    public function test_second_run_is_idempotent_and_preserves_credentials_and_existing_user(): void
    {
        $this->enableDemo();
        $real = User::factory()->create();
        $this->seed(InvManRoleMatrixDemoSeeder::class);
        $user = User::query()->where('username', 'invman.orgdemo.sarpras.sd')->firstOrFail();
        $hash = $user->password;
        $counts = [
            User::query()->count(),
            G001M001Unit::query()->count(),
            G002M007Item::query()->count(),
            G002M015ItemInstance::query()->count(),
            G004M008Activity::query()->count(),
            WorkOrderAssignment::query()->count(),
            WorkOrderWorkflow::query()->count(),
        ];
        config()->set('security.seed_demo_password', 'AnotherValid!Demo2026#Password');
        $this->seed(InvManRoleMatrixDemoSeeder::class);
        $this->assertSame($counts, [
            User::query()->count(),
            G001M001Unit::query()->count(),
            G002M007Item::query()->count(),
            G002M015ItemInstance::query()->count(),
            G004M008Activity::query()->count(),
            WorkOrderAssignment::query()->count(),
            WorkOrderWorkflow::query()->count(),
        ]);
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertTrue(Hash::check('SafeTest!Demo2026#Password', $user->password));
        $this->assertNotNull($real->fresh());
    }

    public function test_non_demo_username_collision_fails_without_mutation(): void
    {
        $this->enableDemo();
        $real = User::factory()->create([
            'username' => 'invman.orgdemo.sarpras.tk',
            'email' => 'real-person@example.org',
        ]);
        $hash = $real->password;
        try {
            $this->seed(InvManRoleMatrixDemoSeeder::class);
            $this->fail('Username collision was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('bertabrakan', $exception->getMessage());
        }
        $this->assertSame($hash, $real->fresh()->password);
        $this->assertSame(0, G001M001Unit::query()->where('name', 'like', '[DEMO-ORG]%')->count());
    }
}
