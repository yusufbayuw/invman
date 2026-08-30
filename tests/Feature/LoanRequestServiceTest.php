<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\G002M007Item;
use App\Models\G003M006Room;
use App\Models\G005M009ItemReservation;
use App\Models\G008M017Vehicle;
use App\Models\User;
use App\Services\LoanAvailabilityService;
use App\Services\LoanRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoanRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_one_activity_with_multiple_types_of_needs(): void
    {
        [$user, $item, $room, $vehicle] = $this->fixtures();
        $this->actingAs($user);

        $activity = app(LoanRequestService::class)->submit($user, [
            'name' => 'Rapat koordinasi',
            'description' => 'Rapat bulanan unit.',
            'notes' => 'Mohon disiapkan sebelum acara.',
            'start_time' => '2026-09-01 09:00:00',
            'end_time' => '2026-09-01 11:00:00',
            'needs' => [
                ['type' => 'item', 'item_id' => $item->id, 'quantity' => 2],
                ['type' => 'room', 'room_id' => $room->id],
                ['type' => 'vehicle', 'vehicle_id' => $vehicle->id],
            ],
        ]);

        $this->assertSame($user->id, $activity->user_id);
        $this->assertSame($user->g001_m001_unit_id, $activity->g001_m001_unit_id);
        $this->assertSame(ReservationStatus::Submitted->value, $activity->status);
        $this->assertCount(1, $activity->item_reservation);
        $this->assertCount(1, $activity->room_reservation);
        $this->assertCount(1, $activity->vehicle_reservation);
        $this->assertNotEmpty($activity->vehicle_reservation->first()->id);

        $service = app(LoanRequestService::class);
        $itemReservation = $activity->item_reservation->first();
        $roomReservation = $activity->room_reservation->first();
        $vehicleReservation = $activity->vehicle_reservation->first();

        $service->processReservation('item', $itemReservation->id, ReservationStatus::Approved);
        $service->processReservation('room', $roomReservation->id, ReservationStatus::Approved);
        $service->processReservation('vehicle', $vehicleReservation->id, ReservationStatus::Approved);
        $this->assertSame(ReservationStatus::Approved->value, $activity->fresh()->status);

        $service->processReservation('item', $itemReservation->id, ReservationStatus::CheckedOut);
        $service->processReservation('room', $roomReservation->id, ReservationStatus::CheckedOut);
        $service->processReservation('vehicle', $vehicleReservation->id, ReservationStatus::CheckedOut);
        $this->assertSame(ReservationStatus::CheckedOut->value, $activity->fresh()->status);
        $this->assertSame(2, $itemReservation->item_reservation_detail()->count());
        $this->assertSame(2, $item->item_instance()->where('is_available', false)->count());
        $this->assertSame(3, app(LoanAvailabilityService::class)->availableItemQuantity(
            $item->id,
            $activity->start_time,
            $activity->end_time,
        ));

        $service->requestReturn($activity->fresh(), [
            'is_ok' => true,
            'notes' => 'Seluruh aset kembali dalam kondisi baik.',
        ]);
        $service->processReservation('item', $itemReservation->id, ReservationStatus::Returned);
        $service->processReservation('room', $roomReservation->id, ReservationStatus::Returned);
        $service->processReservation('vehicle', $vehicleReservation->id, ReservationStatus::Returned);
        $this->assertSame(ReservationStatus::Returned->value, $activity->fresh()->status);
        $this->assertSame(5, $item->item_instance()->where('is_available', true)->count());
        $this->assertTrue((bool) $room->fresh()->is_borrowable);
        $this->assertTrue((bool) $vehicle->fresh()->is_borrowable);
    }

    public function test_return_request_requires_a_checklist(): void
    {
        [$user, $item] = $this->fixtures();
        $this->actingAs($user);
        $activity = app(LoanRequestService::class)->submit($user, [
            'name' => 'Pengembalian tanpa checklist',
            'description' => 'Menguji enforcement service.',
            'start_time' => '2026-09-01 09:00:00',
            'end_time' => '2026-09-01 11:00:00',
            'needs' => [['type' => 'item', 'item_id' => $item->id, 'quantity' => 1]],
        ]);
        $reservation = $activity->item_reservation()->firstOrFail();
        $service = app(LoanRequestService::class);
        $service->processReservation('item', $reservation->id, ReservationStatus::Approved);
        $service->processReservation('item', $reservation->id, ReservationStatus::CheckedOut);

        try {
            $service->requestReturn($activity->fresh());
            $this->fail('Expected the return checklist to be required.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('checklist', $exception->errors());
        }

        try {
            $service->requestReturn($activity->fresh(), ['is_ok' => false]);
            $this->fail('Expected damage notes to be required for a bad return.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('checklist.notes', $exception->errors());
        }

        $this->assertSame(ReservationStatus::CheckedOut->value, $reservation->fresh()->status);
        $this->assertDatabaseMissing('loan_request_checklists', [
            'g004_m008_activity_id' => $activity->id,
            'stage' => 'return',
        ]);
    }

    public function test_failed_item_allocation_rolls_back_the_checkout(): void
    {
        [$user, $item] = $this->fixtures();
        $this->actingAs($user);
        $activity = app(LoanRequestService::class)->submit($user, [
            'name' => 'Alokasi atomik',
            'description' => 'Menguji rollback alokasi serial.',
            'start_time' => '2026-09-01 09:00:00',
            'end_time' => '2026-09-01 11:00:00',
            'needs' => [['type' => 'item', 'item_id' => $item->id, 'quantity' => 2]],
        ]);
        $reservation = $activity->item_reservation()->firstOrFail();
        $service = app(LoanRequestService::class);
        $service->processReservation('item', $reservation->id, ReservationStatus::Approved);
        $item->item_instance()->orderBy('id')->skip(1)->take(4)->get()
            ->each->update(['is_available' => false]);

        try {
            $service->processReservation('item', $reservation->id, ReservationStatus::CheckedOut);
            $this->fail('Expected allocation to fail when instances are unavailable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(ReservationStatus::Approved->value, $reservation->fresh()->status);
        $this->assertSame(0, $reservation->item_reservation_detail()->count());
    }

    public function test_bad_return_keeps_allocated_item_instances_out_of_service(): void
    {
        [$user, $item, $room, $vehicle] = $this->fixtures();
        $this->actingAs($user);
        $service = app(LoanRequestService::class);
        $activity = $service->submit($user, [
            'name' => 'Pengembalian rusak',
            'description' => 'Menguji karantina aset.',
            'start_time' => '2026-09-01 09:00:00',
            'end_time' => '2026-09-01 11:00:00',
            'needs' => [
                ['type' => 'item', 'item_id' => $item->id, 'quantity' => 1],
                ['type' => 'room', 'room_id' => $room->id],
                ['type' => 'vehicle', 'vehicle_id' => $vehicle->id],
            ],
        ]);
        $itemReservation = $activity->item_reservation()->firstOrFail();
        $roomReservation = $activity->room_reservation()->firstOrFail();
        $vehicleReservation = $activity->vehicle_reservation()->firstOrFail();

        $service->processReservation('item', $itemReservation->id, ReservationStatus::Approved);
        $service->processReservation('room', $roomReservation->id, ReservationStatus::Approved);
        $service->processReservation('vehicle', $vehicleReservation->id, ReservationStatus::Approved);
        $service->processReservation('item', $itemReservation->id, ReservationStatus::CheckedOut);
        $service->processReservation('room', $roomReservation->id, ReservationStatus::CheckedOut);
        $service->processReservation('vehicle', $vehicleReservation->id, ReservationStatus::CheckedOut);
        $service->requestReturn($activity->fresh(), [
            'is_ok' => false,
            'notes' => 'Lensa retak.',
        ]);
        $service->processReservation('item', $itemReservation->id, ReservationStatus::Returned);
        $service->processReservation('room', $roomReservation->id, ReservationStatus::Returned);
        $service->processReservation('vehicle', $vehicleReservation->id, ReservationStatus::Returned);
        $instance = $itemReservation->item_reservation_detail()->firstOrFail()->item_instance;

        $this->assertFalse((bool) $instance->is_available);
        $this->assertFalse((bool) $instance->is_borrowable);
        $this->assertSame('perlu_perbaikan', $instance->status);
        $this->assertSame(4, (int) $item->fresh()->available_quantity);
        $this->assertFalse((bool) $room->fresh()->is_borrowable);
        $this->assertSame('perlu_perbaikan', $room->fresh()->status);
        $this->assertFalse((bool) $vehicle->fresh()->is_borrowable);
        $this->assertSame('perlu_perbaikan', $vehicle->fresh()->status);
    }

    public function test_a_stale_second_decision_cannot_overwrite_the_first_decision(): void
    {
        [$user, $item] = $this->fixtures();
        $this->actingAs($user);
        $service = app(LoanRequestService::class);
        $activity = $service->submit($user, [
            'name' => 'Keputusan serentak',
            'description' => 'Menguji keputusan stale.',
            'start_time' => '2026-09-01 09:00:00',
            'end_time' => '2026-09-01 11:00:00',
            'needs' => [['type' => 'item', 'item_id' => $item->id, 'quantity' => 1]],
        ]);
        $reservation = $activity->item_reservation()->firstOrFail();
        $service->processReservation('item', $reservation->id, ReservationStatus::Approved);

        try {
            $service->processReservation('item', $reservation->id, ReservationStatus::Rejected, 'Keputusan kedua.');
            $this->fail('Expected the stale decision to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(ReservationStatus::Approved->value, $reservation->fresh()->status);
        $this->assertSame(1, $reservation->statusHistories()
            ->where('to_status', ReservationStatus::Approved->value)
            ->count());
        $this->assertDatabaseMissing('loan_reservation_status_histories', [
            'reservation_id' => $reservation->id,
            'to_status' => ReservationStatus::Rejected->value,
        ]);
    }

    public function test_it_rejects_an_item_quantity_that_is_not_available_for_an_overlapping_period(): void
    {
        [$user, $item] = $this->fixtures();
        $this->actingAs($user);

        $existingActivity = app(LoanRequestService::class)->submit($user, [
            'name' => 'Kegiatan pertama',
            'description' => 'Memakai sebagian besar stok.',
            'start_time' => '2026-09-01 08:00:00',
            'end_time' => '2026-09-01 12:00:00',
            'needs' => [
                ['type' => 'item', 'item_id' => $item->id, 'quantity' => 4],
            ],
        ]);

        $this->assertNotNull($existingActivity);

        try {
            app(LoanRequestService::class)->submit($user, [
                'name' => 'Kegiatan kedua',
                'description' => 'Jadwalnya bertabrakan.',
                'start_time' => '2026-09-01 10:00:00',
                'end_time' => '2026-09-01 13:00:00',
                'needs' => [
                    ['type' => 'item', 'item_id' => $item->id, 'quantity' => 2],
                ],
            ]);

            $this->fail('Expected a validation exception for insufficient stock.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('data.needs.0.quantity', $exception->errors());
        }

        $this->assertSame(1, G005M009ItemReservation::query()->count());
    }

    public function test_it_rejects_the_same_item_twice_in_one_request(): void
    {
        [$user, $item] = $this->fixtures();

        try {
            app(LoanRequestService::class)->submit($user, [
                'name' => 'Kegiatan dengan barang ganda',
                'description' => 'Barang yang sama tidak boleh dipilih dua kali.',
                'start_time' => '2026-09-01 10:00:00',
                'end_time' => '2026-09-01 12:00:00',
                'needs' => [
                    ['type' => 'item', 'item_id' => $item->id, 'quantity' => 1],
                    ['type' => 'item', 'item_id' => $item->id, 'quantity' => 1],
                ],
            ]);

            $this->fail('Expected a validation exception for a duplicate item.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('data.needs.1.item_id', $exception->errors());
        }

        $this->assertSame(0, G005M009ItemReservation::query()->count());
    }

    private function fixtures(): array
    {
        $unit = G001M001Unit::query()->create(['name' => 'Unit Pengujian']);
        $user = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        Role::query()->firstOrCreate(['name' => config('role.sarpras'), 'guard_name' => 'web']);
        $user->assignRole(config('role.sarpras'));
        $management = G002M003ItemManagement::query()->create(['name' => 'Pengelola Pengujian']);
        $management->users()->attach($user);
        $item = G002M007Item::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'name' => 'Proyektor',
            'is_borrowable' => true,
            'quantity' => 5,
            'available_quantity' => 5,
            'status' => 'tersedia',
        ]);
        $room = G003M006Room::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'name' => 'Aula',
            'is_borrowable' => true,
            'capacity' => 100,
            'status' => 'tersedia',
        ]);
        $vehicle = G008M017Vehicle::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'name' => 'Minibus',
            'license_plate' => 'B 1234 TEST',
            'is_borrowable' => true,
            'status' => 'tersedia',
        ]);

        return [$user, $item, $room, $vehicle];
    }
}
