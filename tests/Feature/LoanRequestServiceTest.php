<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G003M006Room;
use App\Models\G005M009ItemReservation;
use App\Models\G008M017Vehicle;
use App\Models\User;
use App\Services\LoanRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoanRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_one_activity_with_multiple_types_of_needs(): void
    {
        [$user, $item, $room, $vehicle] = $this->fixtures();

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

        $activity->item_reservation->each->update(['status' => ReservationStatus::Approved->value]);
        $activity->room_reservation->each->update(['status' => ReservationStatus::Approved->value]);
        $activity->vehicle_reservation->each->update(['status' => ReservationStatus::Approved->value]);
        $this->assertSame(ReservationStatus::Approved->value, $activity->fresh()->status);

        $activity->item_reservation->each->update(['status' => ReservationStatus::CheckedOut->value]);
        $activity->room_reservation->each->update(['status' => ReservationStatus::CheckedOut->value]);
        $activity->vehicle_reservation->each->update(['status' => ReservationStatus::CheckedOut->value]);
        $this->assertSame(ReservationStatus::CheckedOut->value, $activity->fresh()->status);

        $activity->item_reservation->each->update(['status' => ReservationStatus::Returned->value]);
        $activity->room_reservation->each->update(['status' => ReservationStatus::Returned->value]);
        $activity->vehicle_reservation->each->update(['status' => ReservationStatus::Returned->value]);
        $this->assertSame(ReservationStatus::Returned->value, $activity->fresh()->status);
    }

    public function test_it_rejects_an_item_quantity_that_is_not_available_for_an_overlapping_period(): void
    {
        [$user, $item] = $this->fixtures();

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

    private function fixtures(): array
    {
        $unit = G001M001Unit::query()->create(['name' => 'Unit Pengujian']);
        $user = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $item = G002M007Item::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'name' => 'Proyektor',
            'is_borrowable' => true,
            'quantity' => 5,
            'available_quantity' => 5,
            'status' => 'tersedia',
        ]);
        $room = G003M006Room::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'name' => 'Aula',
            'is_borrowable' => true,
            'capacity' => 100,
            'status' => 'tersedia',
        ]);
        $vehicle = G008M017Vehicle::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'name' => 'Minibus',
            'license_plate' => 'B 1234 TEST',
            'is_borrowable' => true,
            'status' => 'tersedia',
        ]);

        return [$user, $item, $room, $vehicle];
    }
}
