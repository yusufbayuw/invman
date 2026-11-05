<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Resources\G002M007ItemResource;
use App\Filament\Resources\G002M015ItemInstanceResource;
use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use App\Models\G003M006Room;
use App\Models\G004M008Activity;
use App\Models\G005M010RoomReservation;
use App\Models\User;
use App\Services\AssetManagerMutationGuard;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SarprasScopedInventoryAndRoomTest extends TestCase
{
    use RefreshDatabase;

    private function setupActors(): array
    {
        $this->seed(RoleSeeder::class);
        $sd = G001M001Unit::query()->create(['name' => 'SD QA']);
        $smp = G001M001Unit::query()->create(['name' => 'SMP QA']);
        $group = G002M003ItemManagement::query()->create(['name' => 'Fasilitas QA']);
        $sarpras = User::factory()->create(['g001_m001_unit_id' => $sd->id]);
        $sarpras->assignRole(config('role.sarpras'));
        $facility = User::factory()->create();
        $facility->assignRole(config('role.fasilitas'));
        $manager = User::factory()->create();
        $manager->assignRole(config('role.pengelola_barang'));
        $group->users()->attach($manager);

        return compact('sd', 'smp', 'group', 'sarpras', 'facility', 'manager');
    }

    private function item(G001M001Unit $unit, G002M003ItemManagement $group, string $name): G002M007Item
    {
        return G002M007Item::query()->create([
            'name' => $name,
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $group->id,
            'quantity' => 1, 'is_borrowable' => true, 'tracking_mode' => 'individual',
        ]);
    }

    public function test_sarpras_can_only_view_and_edit_own_unit_inventory(): void
    {
        extract($this->setupActors());
        $own = $this->item($sd, $group, 'Laptop SD');
        $foreign = $this->item($smp, $group, 'Laptop SMP');
        $ownInstance = $own->item_instance()->firstOrFail();
        $foreignInstance = $foreign->item_instance()->firstOrFail();

        $this->assertTrue($sarpras->can('viewAny', G002M007Item::class));
        $this->assertTrue($sarpras->can('create', G002M007Item::class));
        $this->assertTrue($sarpras->can('view', $own));
        $this->assertTrue($sarpras->can('update', $own));
        $this->assertFalse($sarpras->can('view', $foreign));
        $this->assertFalse($sarpras->can('update', $foreign));
        $this->assertFalse($sarpras->can('delete', $foreign));
        $this->assertTrue($sarpras->can('viewAny', G002M015ItemInstance::class));
        $this->assertTrue($sarpras->can('create', G002M015ItemInstance::class));
        $this->assertTrue($sarpras->can('update', $ownInstance));
        $this->assertFalse($sarpras->can('view', $foreignInstance));
        $this->assertFalse($sarpras->can('update', $foreignInstance));

        $this->actingAs($sarpras);
        $this->assertSame([$own->id], G002M007ItemResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$ownInstance->id], G002M015ItemInstanceResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_sarpras_forged_unit_and_parent_ids_are_rejected_on_save(): void
    {
        extract($this->setupActors());
        $own = $this->item($sd, $group, 'Proyektor SD');
        $foreign = $this->item($smp, $group, 'Proyektor SMP');
        $guard = app(AssetManagerMutationGuard::class);

        $input = $guard->item([
            'g001_m001_unit_id' => $sd->id,
            'g002_m003_item_management_id' => $group->id,
        ], $sarpras);
        $this->assertSame($sd->id, $input['g001_m001_unit_id']);

        try {
            $guard->item([
                'g001_m001_unit_id' => $smp->id,
                'g002_m003_item_management_id' => $group->id,
            ], $sarpras);
            $this->fail('Sarpras must never create inventory belonging to another unit.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        try {
            $guard->item(['g001_m001_unit_id' => $sd->id], $sarpras, $foreign);
            $this->fail('Sarpras must not reassign a foreign existing item.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        try {
            $guard->instance(['g002_m007_item_id' => $foreign->id], $sarpras);
            $this->fail('Sarpras must not create foreign unit instances.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $instanceData = $guard->instance(['g002_m007_item_id' => $own->id], $sarpras);
        $this->assertSame($sd->id, $instanceData['g001_m001_unit_id']);
    }

    public function test_ruangan_tanpa_unit_is_a_location_and_managed_only_by_facility(): void
    {
        extract($this->setupActors());
        $withoutUnit = G003M006Room::query()->create([
            'name' => 'Gudang Pusat QA',
            'g001_m001_unit_id' => null,
            'g002_m003_item_management_id' => $group->id,
            'is_borrowable' => false,
        ]);
        $smpRoom = G003M006Room::query()->create([
            'name' => 'Ruang SMP QA',
            'g001_m001_unit_id' => $smp->id,
            'g002_m003_item_management_id' => $group->id,
        ]);
        $this->assertNull($withoutUnit->unit);
        $this->assertFalse((bool) $withoutUnit->is_borrowable);
        $this->assertTrue($facility->can('viewAny', G003M006Room::class));
        $this->assertFalse($sarpras->can('viewAny', G003M006Room::class));

        $own = $this->item($sd, $group, 'Kursi SD');
        $guard = app(AssetManagerMutationGuard::class);
        $allowed = $guard->item([
            'g001_m001_unit_id' => $sd->id,
            'g003_m006_room_id' => $withoutUnit->id,
        ], $sarpras, $own);
        $this->assertSame($sd->id, $allowed['g001_m001_unit_id']);
        $this->assertSame($withoutUnit->id, $allowed['g003_m006_room_id']);

        try {
            $guard->item([
                'g001_m001_unit_id' => $sd->id,
                'g003_m006_room_id' => $smpRoom->id,
            ], $sarpras, $own);
            $this->fail('Foreign unit room placement must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('g003_m006_room_id', $exception->errors());
        }

        $activity = G004M008Activity::query()->create([
            'name' => 'Pemakaian Gudang Pusat',
            'user_id' => $sarpras->id,
            'g001_m001_unit_id' => $sd->id,
            'status' => ReservationStatus::Draft->value,
            'start_time' => now()->addHours(3),
            'end_time' => now()->addHours(5),
        ]);
        $reservation = G005M010RoomReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g003_m006_room_id' => $withoutUnit->id,
            'status' => ReservationStatus::Draft->value,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
        ]);
        $this->assertFalse($manager->managesReservation($reservation));
        $this->assertTrue($facility->managesReservation($reservation));
    }

    public function test_sarpras_unit_boundary_still_applies_when_manager_role_is_also_assigned(): void
    {
        extract($this->setupActors());
        $own = $this->item($sd, $group, 'Monitor SD');
        $foreign = $this->item($smp, $group, 'Monitor SMP');
        $group->users()->attach($sarpras);
        $sarpras->assignRole(config('role.pengelola_barang'));

        $this->assertTrue($sarpras->can('view', $own));
        $this->assertFalse($sarpras->can('view', $foreign));
        $this->actingAs($sarpras);
        $this->assertSame([$own->id], G002M007ItemResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_reseeding_does_not_restore_shield_permissions_removed_by_admin(): void
    {
        $this->seed(RoleSeeder::class);
        $role = Role::query()->where('name', config('role.sarpras'))->firstOrFail();
        $role->revokePermissionTo('create_g002::m007::item');
        $this->seed(RoleSeeder::class);
        $this->assertFalse($role->fresh()->hasPermissionTo('create_g002::m007::item'));
    }
}
