<?php

namespace Tests\Feature;

use App\Models\AssetRelation;
use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use App\Models\G004M008Activity;
use App\Models\G008M018Driver;
use App\Models\TicketTeamMember;
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
        $this->seed(RoleSeeder::class);
    }

    public function test_demo_is_opt_in_and_refuses_production(): void
    {
        $this->expectException(RuntimeException::class);
        $this->seed(InvManRoleMatrixDemoSeeder::class);
    }

    public function test_demo_refuses_production_even_with_opt_in(): void
    {
        $this->enableDemo();
        config()->set('app.env', 'production');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('staging/production');
        $this->seed(InvManRoleMatrixDemoSeeder::class);
    }

    public function test_it_creates_five_units_thirteen_users_and_scoped_inventory_without_submissions(): void
    {
        $this->enableDemo();
        $this->seed(InvManRoleMatrixDemoSeeder::class);

        $this->assertSame(5, G001M001Unit::query()->where('name', 'like', '[DEMO-ORG]%')->count());
        $this->assertSame(13, User::query()->whereIn('username', ['admin', 'sarpras.tk', 'sarpras.sd', 'sarpras.smp', 'sarpras.sma', 'sarpras.univ', 'staf.fas', 'spv.fas', 'man.fas', 'teknisi', 'it', 'perpus', 'driver'])->count());
        $this->assertSame(5, User::query()->whereIn('username', ['sarpras.tk', 'sarpras.sd', 'sarpras.smp', 'sarpras.sma', 'sarpras.univ'])->count());
        $this->assertSame(70, G002M007Item::query()->where('code', 'like', 'INVMAN-ORG-%')->count());
        $this->assertSame(95, G002M015ItemInstance::query()->whereHas('item',
            fn ($query) => $query->where('code', 'like', 'INVMAN-ORG-%'))->count());
        $this->assertSame(30, AssetRelation::query()->count());
        foreach (['TK', 'SD', 'SMP', 'SMA', 'UNIVERSITAS'] as $slug) {
            $prefix = 'INVMAN-ORG-'.$slug.'-STRUCT-';
            $this->assertSame(11, G002M007Item::query()->where('code', 'like', $prefix.'%')->count());
            $bulk = G002M007Item::query()->where('code', $prefix.'BULK')->firstOrFail();
            $this->assertSame('bulk', $bulk->tracking_mode);
            $this->assertSame(80, (int) $bulk->available_quantity);
            $this->assertSame(0, $bulk->item_instance()->count());
            $links = AssetRelation::query()->whereHas('parentInstance.item',
                fn ($query) => $query->where('code', 'like', $prefix.'%'))->get();
            $this->assertSame(['bundle' => 2, 'component' => 2, 'contained' => 1, 'installed' => 1],
                $links->groupBy('relation_type')->map->count()->sortKeys()->all());
            foreach ($links as $link) {
                $this->assertSame($link->parentInstance->item->g001_m001_unit_id,
                    $link->childInstance->item->g001_m001_unit_id);
                if (in_array($link->relation_type, ['component', 'installed'], true)) {
                    $this->assertFalse((bool) $link->childInstance->is_borrowable);
                }
                if ($link->relation_type === 'bundle') {
                    $this->assertFalse((bool) $link->parentInstance->is_borrowable);
                }
            }
        }
        $this->assertSame(5, G004M008Activity::query()->where('name', 'like', '[DEMO-ORG]%')->count());
        $this->assertSame(0, G004M008Activity::query()->where('name', 'like', '[DEMO-ORG]%')
            ->where('status', '!=', 'draft')->count());
        $this->assertSame(5, WorkOrderWorkflow::query()->where('status', 'draft')->count());
        $this->assertSame(0, WorkOrderWorkflow::query()->where('status', 'published')->count());

        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $this->assertSame('admin@invman.invalid', $admin->email);
        $this->assertTrue($admin->is_enabled);
        $this->assertTrue($admin->hasRole(config('role.admin')));
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->isFacility());
        $this->assertTrue($admin->can('page_AjukanPeminjaman'));
        $this->assertTrue($admin->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));
        $this->assertNull($admin->g001_m001_unit_id);
        $this->assertTrue(Hash::check('password', $admin->password));

        foreach (['tk', 'sd', 'smp', 'sma', 'univ'] as $slug) {
            $borrower = User::query()->where('username', 'sarpras.'.$slug)->firstOrFail();
            $this->assertTrue($borrower->isSarpras());
            $this->assertNotNull($borrower->g001_m001_unit_id);
        }
        foreach (['staf.fas', 'spv.fas', 'man.fas'] as $username) {
            $facility = User::query()->where('username', $username)->firstOrFail();
            $this->assertTrue($facility->isFacility());
        }

        $driverUser = User::query()->where('username', 'driver')->firstOrFail();
        $this->assertSame('A', G008M018Driver::query()->where('user_id', $driverUser->id)->firstOrFail()->sim_type);
        $this->assertFalse($driverUser->isFacility());
        $this->assertFalse($driverUser->isSarpras());
        $this->assertSame('[DEMO-ORG] Pengemudi',
            WorkOrderAssignment::query()->where('user_id', $driverUser->id)->firstOrFail()->jobTitle->name);
        $technician = User::query()->where('username', 'teknisi')->firstOrFail();
        $staff = User::query()->where('username', 'staf.fas')->firstOrFail();
        $this->assertFalse($technician->isFacility());
        $this->assertFalse($technician->isSarpras());
        $assignment = WorkOrderAssignment::query()->where('user_id', $technician->id)->firstOrFail();
        $this->assertSame($staff->id, $assignment->supervisor_user_id);
        $this->assertSame('[DEMO-ORG] Teknisi', $assignment->jobTitle->name);
        $teamLead = TicketTeamMember::query()->where('user_id', $staff->id)->firstOrFail();
        $teamTechnician = TicketTeamMember::query()->where('user_id', $technician->id)->firstOrFail();
        $this->assertSame('lead', $teamLead->position);
        $this->assertSame('technician', $teamTechnician->position);
        $this->assertSame($staff->id, $teamTechnician->supervisor_user_id);
        $this->assertSame([$technician->id], TicketTeamMember::descendantsOf($staff));
        foreach (WorkOrderWorkflow::query()->get() as $workflow) {
            $this->assertNotNull($workflow->dispatch_job_title_id);
        }

        $it = User::query()->where('username', 'it')->firstOrFail();
        $library = User::query()->where('username', 'perpus')->firstOrFail();
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

        $this->actingAs(User::query()->where('username', 'sarpras.tk')->firstOrFail());
        $this->assertSame(11, \App\Filament\Resources\G002M007ItemResource::getEloquentQuery()
            ->where('code', 'like', 'INVMAN-ORG-TK-STRUCT-%')->count());
        $this->assertSame(0, \App\Filament\Resources\G002M007ItemResource::getEloquentQuery()
            ->where('code', 'like', 'INVMAN-ORG-SD-STRUCT-%')->count());
    }

    public function test_second_run_is_idempotent_and_preserves_credentials_and_existing_user(): void
    {
        $this->enableDemo();
        $real = User::factory()->create();
        $this->seed(InvManRoleMatrixDemoSeeder::class);
        $user = User::query()->where('username', 'sarpras.sd')->firstOrFail();
        $hash = $user->password;
        $counts = [
            User::query()->count(),
            G001M001Unit::query()->count(),
            G002M007Item::query()->count(),
            G002M015ItemInstance::query()->count(),
            AssetRelation::query()->count(),
            G004M008Activity::query()->count(),
            WorkOrderAssignment::query()->count(),
            G008M018Driver::query()->count(),
            TicketTeamMember::query()->count(),
            WorkOrderWorkflow::query()->count(),
        ];
        $this->seed(InvManRoleMatrixDemoSeeder::class);
        $this->assertSame($counts, [
            User::query()->count(),
            G001M001Unit::query()->count(),
            G002M007Item::query()->count(),
            G002M015ItemInstance::query()->count(),
            AssetRelation::query()->count(),
            G004M008Activity::query()->count(),
            WorkOrderAssignment::query()->count(),
            G008M018Driver::query()->count(),
            TicketTeamMember::query()->count(),
            WorkOrderWorkflow::query()->count(),
        ]);
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertTrue(Hash::check('password', $user->password));
        foreach (['sarpras.tk', 'sarpras.sd', 'sarpras.smp', 'sarpras.sma', 'sarpras.univ', 'staf.fas', 'spv.fas', 'man.fas', 'teknisi', 'it', 'perpus', 'driver', 'admin'] as $username) {
            $this->assertTrue(Hash::check('password', User::query()->where('username', $username)->firstOrFail()->password));
        }
        $this->assertNotNull($real->fresh());
    }

    public function test_existing_non_demo_admin_is_not_hijacked_or_reset(): void
    {
        $this->enableDemo();
        $real = User::factory()->create([
            'username' => 'admin',
            'email' => 'admin-institusi@example.org',
        ]);
        $originalHash = $real->password;
        $originalRoles = $real->getRoleNames()->all();

        try {
            $this->seed(InvManRoleMatrixDemoSeeder::class);
            $this->fail('Akun administrator yang sudah ada tidak boleh diambil alih.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('bertabrakan', $exception->getMessage());
        }

        $this->assertSame($originalHash, $real->fresh()->password);
        $this->assertSame($originalRoles, $real->fresh()->getRoleNames()->all());
        $this->assertSame(0, G001M001Unit::query()->where('name', 'like', '[DEMO-ORG]%')->count());
    }

    public function test_non_demo_username_collision_fails_without_mutation(): void
    {
        $this->enableDemo();
        $real = User::factory()->create([
            'username' => 'sarpras.tk',
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
