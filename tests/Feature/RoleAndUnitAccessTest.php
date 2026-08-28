<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G002M003ItemManagement;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAndUnitAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_expected_units_roles_and_accounts(): void
    {
        $this->seed(UnitSeeder::class);
        $this->seed(RoleSeeder::class);
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

    public function test_assigned_asset_manager_decisions_are_audited_and_create_partial_approval_status(): void
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
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $first->update(['status' => ReservationStatus::Approved->value]);
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
            'name' => 'Kegiatan ' . $unit->name,
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
