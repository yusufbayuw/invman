<?php

namespace Tests\Feature;

use App\Filament\Resources\G002M003ItemManagementResource\Pages\EditG002M003ItemManagement;
use App\Filament\Resources\UserResource\Pages\ManageUsers;
use App\Models\G002M003ItemManagement;
use App\Models\G002M007Item;
use App\Models\User;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderJobTitle;
use App\Services\AssetManagerRoleSynchronizer;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssetManagerRoleSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    private function prepareRoles(): void
    {
        $this->seed(RoleSeeder::class);
    }

    private function group(string $name): G002M003ItemManagement
    {
        return G002M003ItemManagement::query()->create(['name' => $name]);
    }

    public function test_bidirectional_membership_automatically_grants_and_revokes_only_asset_manager_role(): void
    {
        $this->prepareRoles();
        $it = $this->group('IT');
        $library = $this->group('Perpustakaan');
        $staff = User::factory()->create();
        $staff->assignRole(config('role.fasilitas'));

        $this->assertFalse($staff->hasRole(config('role.pengelola_barang')));

        $staff->itemManagements()->attach($it);
        $this->assertTrue($staff->fresh()->hasRole(config('role.pengelola_barang')));

        $library->users()->syncWithoutDetaching([$staff->id]);
        $staff->itemManagements()->detach($it->id);
        $this->assertTrue($staff->fresh()->hasRole(config('role.pengelola_barang')));

        $library->users()->detach($staff->id);
        $this->assertFalse($staff->fresh()->hasRole(config('role.pengelola_barang')));
        $this->assertTrue($staff->fresh()->hasRole(config('role.fasilitas')));
    }

    public function test_sync_reconciles_old_role_memberships_without_restoring_revoked_shield_permissions(): void
    {
        $this->prepareRoles();
        $managerRole = Role::query()->where('name', config('role.pengelola_barang'))->firstOrFail();
        $managerRole->revokePermissionTo('create_g002::m007::item');

        $member = User::factory()->create();
        $withoutGroup = User::factory()->create();
        $member->itemManagements()->attach($this->group('IT')->id);
        $member->removeRole(config('role.pengelola_barang'));
        $withoutGroup->assignRole(config('role.pengelola_barang'));

        app(AssetManagerRoleSynchronizer::class)->reconcileExisting();

        $this->assertTrue($member->fresh()->hasRole(config('role.pengelola_barang')));
        $this->assertFalse($withoutGroup->fresh()->hasRole(config('role.pengelola_barang')));
        $this->assertFalse($member->fresh()->can('create', G002M007Item::class));
        $this->assertFalse($managerRole->fresh()->hasPermissionTo('create_g002::m007::item'));
    }

    public function test_deleting_management_group_revokes_orphan_role_and_preserves_other_groups(): void
    {
        $this->prepareRoles();
        $it = $this->group('IT');
        $library = $this->group('Perpustakaan');
        $first = User::factory()->create();
        $second = User::factory()->create();
        $it->users()->sync([$first->id, $second->id]);
        $library->users()->attach($second);

        $it->delete();

        $this->assertFalse($first->fresh()->hasRole(config('role.pengelola_barang')));
        $this->assertTrue($second->fresh()->hasRole(config('role.pengelola_barang')));
    }

    public function test_work_order_job_scope_does_not_provision_inventory_membership_or_role(): void
    {
        $this->prepareRoles();
        $it = $this->group('IT');
        $staff = User::factory()->create();
        $title = WorkOrderJobTitle::query()->create(['name' => 'Supervisor IT', 'is_active' => true]);
        WorkOrderAssignment::query()->create([
            'user_id' => $staff->id,
            'work_order_job_title_id' => $title->id,
            'g002_m003_item_management_id' => $it->id,
            'is_active' => true,
        ]);

        $this->assertTrue($staff->workOrderAssignments()->exists());
        $this->assertFalse($staff->itemManagements()->exists());
        $this->assertFalse($staff->hasRole(config('role.pengelola_barang')));
    }

    public function test_editing_user_manual_roles_preserves_auto_role_and_revokes_it_when_last_group_removed(): void
    {
        $this->prepareRoles();
        $admin = User::factory()->create();
        $admin->assignRole(config('role.admin'));
        $it = $this->group('IT');
        $staff = User::factory()->create();
        $staff->itemManagements()->attach($it);
        $facilityRole = Role::query()->where('name', config('role.fasilitas'))->firstOrFail();

        Livewire::actingAs($admin)->test(ManageUsers::class)
            ->callTableAction('edit', $staff, [
                'roles' => [$facilityRole->id],
                'itemManagements' => [$it->id],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($staff->fresh()->hasRole(config('role.pengelola_barang')));
        $this->assertTrue($staff->fresh()->hasRole(config('role.fasilitas')));

        Livewire::actingAs($admin)->test(ManageUsers::class)
            ->callTableAction('edit', $staff, [
                'roles' => [$facilityRole->id],
                'itemManagements' => [],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertFalse($staff->fresh()->hasRole(config('role.pengelola_barang')));
        $this->assertTrue($staff->fresh()->hasRole(config('role.fasilitas')));
    }

    public function test_editing_group_users_revokes_removed_members_and_grants_new_members(): void
    {
        $this->prepareRoles();
        $admin = User::factory()->create();
        $admin->assignRole(config('role.admin'));
        $group = $this->group('IT');
        $old = User::factory()->create();
        $new = User::factory()->create();
        $group->users()->attach($old);

        Livewire::actingAs($admin)
            ->test(EditG002M003ItemManagement::class, ['record' => $group->getRouteKey()])
            ->fillForm(['users' => [$new->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($old->fresh()->hasRole(config('role.pengelola_barang')));
        $this->assertTrue($new->fresh()->hasRole(config('role.pengelola_barang')));
        $this->assertTrue($group->users()->whereKey($new->id)->exists());
    }
}
