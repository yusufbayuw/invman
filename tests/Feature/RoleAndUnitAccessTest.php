<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Resources\G004M008ActivityResource\Pages\ViewG004M008Activity;
use App\Filament\Resources\G004M008ActivityResource\RelationManagers\ItemReservationRelationManager;
use App\Filament\Resources\G005M009ItemReservationResource;
use App\Filament\Resources\G005M010RoomReservationResource;
use App\Filament\Resources\G005M019VehicleReservationResource;
use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M016ItemReservationDetail;
use App\Models\G005M019VehicleReservation;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAndUnitAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_expected_units_roles_and_accounts(): void
    {
        $this->seed(UnitSeeder::class);
        $this->seed(RoleSeeder::class);
        config()->set('security.seed_demo_users', true);
        config()->set('security.seed_demo_password', 'DemoPass!2026#Secure');
        $this->seed(UserSeeder::class);

        $this->assertSame(7, G001M001Unit::query()->count());
        $this->assertDatabaseHas('g001_m001_units', ['name' => 'Organ Yayasan']);

        foreach (['admin', 'fasilitas', 'sarpras'] as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role, 'guard_name' => 'web']);
        }
        $this->assertSame(['admin', 'fasilitas', 'sarpras'], Role::query()->orderBy('name')->pluck('name')->all());

        $this->assertTrue(User::query()->where('username', 'admin')->firstOrFail()->hasRole('admin'));
        $facility = User::query()->where('username', 'fasilitas')->firstOrFail();
        $this->assertTrue($facility->hasRole('fasilitas'));
        $this->assertSame(6, User::role('sarpras')->count());
        $this->assertFalse(
            User::role('sarpras')->whereHas('unit', fn ($query) => $query->where('name', 'Organ Yayasan'))->exists(),
        );
        $this->assertTrue(Gate::forUser(User::query()->where('username', 'admin')->firstOrFail())->allows('viewAny', User::class));
        $this->assertFalse(Gate::forUser($facility)->allows('viewAny', User::class));
    }

    public function test_sarpras_policy_is_scoped_to_unit_and_locks_approved_activity(): void
    {
        Role::query()->create(['name' => 'sarpras', 'guard_name' => 'web']);
        [$unitA, $unitB] = [
            G001M001Unit::query()->create(['name' => 'SD']),
            G001M001Unit::query()->create(['name' => 'SMP']),
        ];
        $sarpras = User::factory()->create(['g001_m001_unit_id' => $unitA->id]);
        $sarpras->assignRole('sarpras');

        $sameUnit = $this->activity($unitA, ReservationStatus::Submitted);
        $otherUnit = $this->activity($unitB, ReservationStatus::Submitted);

        $this->assertTrue($sarpras->can('view', $sameUnit));
        $this->assertFalse($sarpras->can('update', $sameUnit));
        $this->assertFalse($sarpras->can('view', $otherUnit));
        $this->assertFalse($sarpras->can('update', $otherUnit));

        $sameUnit->update(['status' => ReservationStatus::Approved->value]);
        $this->assertFalse($sarpras->can('update', $sameUnit->fresh()));
    }

    public function test_facility_can_access_and_manage_all_reservation_types(): void
    {
        $this->seed(RoleSeeder::class);

        $facility = User::factory()->create();
        $facility->assignRole(config('role.fasilitas'));
        $this->actingAs($facility);

        $this->assertTrue($facility->can('viewAny', G005M009ItemReservation::class));
        $this->assertTrue($facility->can('viewAny', G005M010RoomReservation::class));
        $this->assertTrue($facility->can('viewAny', G005M019VehicleReservation::class));
        $this->assertTrue(G005M009ItemReservationResource::shouldRegisterNavigation());
        $this->assertTrue(G005M010RoomReservationResource::shouldRegisterNavigation());
        $this->assertTrue(G005M019VehicleReservationResource::shouldRegisterNavigation());
        $this->assertTrue(G005M009ItemReservationResource::canAccess());
        $this->assertTrue(G005M010RoomReservationResource::canAccess());
        $this->assertTrue(G005M019VehicleReservationResource::canAccess());
    }

    public function test_facility_can_decide_reservation_without_asset_manager_assignment(): void
    {
        Role::query()->create(['name' => 'fasilitas', 'guard_name' => 'web']);
        $manager = User::factory()->create();
        $legacyFacility = User::factory()->create();
        $legacyFacility->assignRole('fasilitas');
        $management = G002M003ItemManagement::query()->create(['name' => 'Elektronik']);
        $management->users()->attach($manager);
        $unit = G001M001Unit::query()->create(['name' => 'SMA']);
        $activity = $this->activity($unit, ReservationStatus::Submitted);
        $firstItem = G002M007Item::query()->create([
            'name' => 'Proyektor',
            'g002_m003_item_management_id' => $management->id,
        ]);
        $secondItem = G002M007Item::query()->create([
            'name' => 'Speaker',
            'g002_m003_item_management_id' => $management->id,
        ]);

        $first = $this->reservation($activity, $firstItem);
        $second = $this->reservation($activity, $secondItem);

        $this->actingAs($legacyFacility);
        $first->update(['status' => ReservationStatus::Approved->value]);

        $this->assertSame(ReservationStatus::Approved->value, $first->fresh()->status);
        $this->assertSame($legacyFacility->id, $first->fresh()->decision_by);
        $this->assertNotNull($first->fresh()->decision_at);
    }

    public function test_facility_approval_action_advances_the_reservation(): void
    {
        Role::query()->create(['name' => 'fasilitas', 'guard_name' => 'web']);
        $facility = User::factory()->create();
        $facility->assignRole('fasilitas');
        $management = G002M003ItemManagement::query()->create(['name' => 'Elektronik']);
        $unit = G001M001Unit::query()->create(['name' => 'SMA']);
        $activity = $this->activity($unit, ReservationStatus::Submitted);
        $item = G002M007Item::query()->create([
            'name' => 'Proyektor',
            'g002_m003_item_management_id' => $management->id,
        ]);
        $reservation = $this->reservation($activity, $item);

        Livewire::actingAs($facility)
            ->test(ItemReservationRelationManager::class, [
                'ownerRecord' => $activity,
                'pageClass' => ViewG004M008Activity::class,
            ])
            ->assertTableActionVisible('konfirmasi', $reservation)
            ->callTableAction('konfirmasi', $reservation)
            ->assertHasNoTableActionErrors();

        $this->assertSame(ReservationStatus::Approved->value, $reservation->fresh()->status);
        $this->assertSame(ReservationStatus::Approved->value, $activity->fresh()->status);
        $this->assertSame($facility->id, $reservation->fresh()->decision_by);
    }

    public function test_assigned_manager_can_record_a_direct_return_from_activity_relation_table(): void
    {
        Role::query()->firstOrCreate(['name' => config('role.sarpras'), 'guard_name' => 'web']);
        $manager = User::factory()->create();
        $management = G002M003ItemManagement::query()->create(['name' => 'Elektronik']);
        $management->users()->attach($manager);
        $unit = G001M001Unit::query()->create(['name' => 'SMA']);
        $activity = $this->activity($unit, ReservationStatus::CheckedOut);
        $item = G002M007Item::query()->create([
            'name' => 'Proyektor',
            'g002_m003_item_management_id' => $management->id,
        ]);
        $reservation = $this->reservation($activity, $item);
        $reservation->updateQuietly(['status' => ReservationStatus::CheckedOut->value]);
        $instance = G002M015ItemInstance::query()->create([
            'g002_m007_item_id' => $item->id,
            'name' => 'Proyektor 01',
            'code' => 'PRJ-01',
            'status' => ReservationStatus::CheckedOut->value,
            'is_available' => false,
            'is_borrowable' => true,
        ]);
        G005M016ItemReservationDetail::query()->create([
            'g005_m009_item_reservation_id' => $reservation->id,
            'g002_m015_item_instance_id' => $instance->id,
        ]);

        Livewire::actingAs($manager)
            ->test(ItemReservationRelationManager::class, [
                'ownerRecord' => $activity,
                'pageClass' => ViewG004M008Activity::class,
            ])
            ->assertTableActionVisible('catat_pengembalian', $reservation)
            ->callTableAction('catat_pengembalian', $reservation, [
                'is_ok' => true,
                'notes' => 'Diterima langsung oleh pengelola.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(ReservationStatus::ReturnRequested->value, $reservation->fresh()->status);
        $this->assertSame(ReservationStatus::ReturnRequested->value, $activity->fresh()->status);
        $this->assertDatabaseHas('loan_reservation_checklists', [
            'g004_m008_activity_id' => $activity->id,
            'reservation_type' => 'item',
            'reservation_id' => $reservation->id,
            'g002_m015_item_instance_id' => $instance->id,
            'checked_by' => $manager->id,
            'is_ok' => true,
        ]);
        $this->assertDatabaseHas('loan_handover_receipts', [
            'reservation_type' => 'item',
            'reservation_id' => $reservation->id,
            'manager_confirmed_by' => $manager->id,
            'borrower_confirmed_by' => null,
        ]);

        $borrower = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $borrower->assignRole(config('role.sarpras'));
        $this->actingAs($borrower);
        app(\App\Services\LoanRequestService::class)->confirmReturn('item', $reservation->id);

        $this->assertSame(ReservationStatus::Returned->value, $reservation->fresh()->status);
        $this->assertSame(ReservationStatus::Returned->value, $activity->fresh()->status);
        $this->assertTrue((bool) $instance->fresh()->is_available);
    }

    public function test_manager_status_changes_record_actor_and_full_history(): void
    {
        $manager = User::factory()->create();
        $management = G002M003ItemManagement::query()->create(['name' => 'Elektronik']);
        $management->users()->attach($manager);
        $unit = G001M001Unit::query()->create(['name' => 'SMA']);
        $activity = $this->activity($unit, ReservationStatus::Submitted);
        $firstItem = G002M007Item::query()->create([
            'name' => 'Proyektor',
            'g002_m003_item_management_id' => $management->id,
        ]);
        $secondItem = G002M007Item::query()->create([
            'name' => 'Speaker',
            'g002_m003_item_management_id' => $management->id,
        ]);
        $first = $this->reservation($activity, $firstItem);
        $second = $this->reservation($activity, $secondItem);

        $this->actingAs($manager);
        $first->update(['status' => ReservationStatus::Approved->value]);
        $second->update([
            'status' => ReservationStatus::Rejected->value,
            'rejection_reason' => 'Stok sedang dalam perawatan.',
        ]);

        $this->assertSame(ReservationStatus::PartiallyApproved->value, $activity->fresh()->status);
        $this->assertSame($manager->id, $first->fresh()->decision_by);
        $this->assertNotNull($first->fresh()->decision_at);
        $this->assertSame($manager->id, $first->fresh()->status_changed_by);
        $this->assertNotNull($first->fresh()->status_changed_at);
        $this->assertSame($manager->id, $second->fresh()->decision_by);
        $this->assertSame('Stok sedang dalam perawatan.', $second->fresh()->rejection_reason);
        $this->assertDatabaseHas('loan_reservation_status_histories', [
            'reservation_type' => 'item',
            'reservation_id' => $first->id,
            'from_status' => ReservationStatus::Submitted->value,
            'to_status' => ReservationStatus::Approved->value,
            'changed_by' => $manager->id,
        ]);
    }

    private function activity(G001M001Unit $unit, ReservationStatus $status): G004M008Activity
    {
        return G004M008Activity::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'name' => 'Kegiatan '.$unit->name,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => $status->value,
        ]);
    }

    private function reservation(G004M008Activity $activity, G002M007Item $item): G005M009ItemReservation
    {
        return G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g002_m007_item_id' => $item->id,
            'quantity' => 1,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
            'status' => ReservationStatus::Submitted->value,
        ]);
    }
}
