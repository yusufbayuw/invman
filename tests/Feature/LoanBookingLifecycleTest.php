<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\G002M007Item;
use App\Models\G003M006Room;
use App\Models\G008M017Vehicle;
use App\Models\G008M018Driver;
use App\Models\User;
use App\Services\LoanAvailabilityService;
use App\Services\LoanRequestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoanBookingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-01 07:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_active_submitted_hold_blocks_items_rooms_and_vehicles(): void
    {
        [$user, $item, $room, $vehicle] = $this->fixtures();
        $activity = $this->submit($user, $item, $room, $vehicle);
        $availability = app(LoanAvailabilityService::class);

        $this->assertTrue($activity->hold_expires_at->isFuture());
        $this->assertSame(3, $availability->availableItemQuantity($item->id, '2026-09-01 10:00', '2026-09-01 11:00'));
        $this->assertFalse($availability->roomIsAvailable($room->id, now()->parse('2026-09-01 10:00'), now()->parse('2026-09-01 11:00')));
        $this->assertFalse($availability->vehicleIsAvailable($vehicle->id, now()->parse('2026-09-01 10:00'), now()->parse('2026-09-01 11:00')));
    }

    public function test_draft_and_elapsed_submitted_holds_do_not_block_availability(): void
    {
        [$user, $item, $room, $vehicle] = $this->fixtures();
        $activity = $this->submit($user, $item, $room, $vehicle);
        $activity->item_reservation()->first()->updateQuietly(['status' => ReservationStatus::Draft->value]);
        $activity->updateQuietly(['hold_expires_at' => now()->subMinute()]);
        $availability = app(LoanAvailabilityService::class);

        $this->assertSame(5, $availability->availableItemQuantity($item->id, '2026-09-01 10:00', '2026-09-01 11:00'));
        $this->assertTrue($availability->roomIsAvailable($room->id, now()->parse('2026-09-01 10:00'), now()->parse('2026-09-01 11:00')));
        $this->assertTrue($availability->vehicleIsAvailable($vehicle->id, now()->parse('2026-09-01 10:00'), now()->parse('2026-09-01 11:00')));
    }

    public function test_unavailable_assets_can_remain_in_a_draft_but_cannot_be_submitted(): void
    {
        [$user, $item] = $this->fixtures();
        $service = app(LoanRequestService::class);
        $draftData = [
            'name' => 'Draf sebelum bentrok',
            'description' => 'Draf ini belum menahan stok.',
            'start_time' => '2026-09-01 10:00:00',
            'end_time' => '2026-09-01 11:00:00',
            'needs' => [
                ['type' => 'item', 'item_id' => $item->id, 'quantity' => 4],
            ],
        ];
        $draft = $service->saveDraft($user, $draftData);

        $service->submit($user, [
            'name' => 'Pengajuan yang menahan stok',
            'description' => 'Pengajuan lain masuk setelah draf disimpan.',
            'start_time' => '2026-09-01 10:00:00',
            'end_time' => '2026-09-01 11:00:00',
            'needs' => [
                ['type' => 'item', 'item_id' => $item->id, 'quantity' => 3],
            ],
        ]);

        $draftData['description'] = 'Draf masih dapat diperbarui meskipun stok berubah.';
        $updatedDraft = $service->saveDraft($user, $draftData, $draft);

        $this->assertSame(ReservationStatus::Draft->value, $updatedDraft->status);
        $this->assertSame('Draf masih dapat diperbarui meskipun stok berubah.', $updatedDraft->description);
        $this->assertSame(4, $updatedDraft->item_reservation()->value('quantity'));

        try {
            $service->submitDraft($user, $updatedDraft);
            $this->fail('Expected the unavailable draft to be rejected on submit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(ReservationStatus::Draft->value, $updatedDraft->fresh()->status);
    }

    public function test_expiry_releases_all_pending_needs_and_marks_the_activity_expired(): void
    {
        [$user, $item, $room, $vehicle] = $this->fixtures();
        $activity = $this->submit($user, $item, $room, $vehicle);
        $activity->updateQuietly(['hold_expires_at' => now()->subMinute()]);

        $this->assertSame(1, app(LoanRequestService::class)->expireStaleHolds());

        $activity->refresh();
        $this->assertSame(ReservationStatus::Expired->value, $activity->status);
        $this->assertNotNull($activity->expired_at);
        $this->assertSame([ReservationStatus::Expired->value], $activity->item_reservation()->distinct()->pluck('status')->all());
        $this->assertSame([ReservationStatus::Expired->value], $activity->room_reservation()->distinct()->pluck('status')->all());
        $this->assertSame([ReservationStatus::Expired->value], $activity->vehicle_reservation()->distinct()->pluck('status')->all());
        $this->assertSame(0, app(LoanRequestService::class)->expireStaleHolds());
    }

    public function test_expiry_keeps_approved_needs_and_only_releases_pending_needs(): void
    {
        [$user, $item, $room, $vehicle] = $this->fixtures();
        $activity = $this->submit($user, $item, $room, $vehicle);
        $activity->item_reservation()->first()->update(['status' => ReservationStatus::Approved->value]);
        $activity->updateQuietly(['hold_expires_at' => now()->subMinute()]);

        app(LoanRequestService::class)->expireStaleHolds();

        $activity->refresh();
        $this->assertSame(ReservationStatus::PartiallyApproved->value, $activity->status);
        $this->assertSame(ReservationStatus::Approved->value, $activity->item_reservation()->first()->status);
        $this->assertSame(ReservationStatus::Expired->value, $activity->room_reservation()->first()->status);
        $this->assertSame(ReservationStatus::Expired->value, $activity->vehicle_reservation()->first()->status);
        $this->assertSame(3, app(LoanAvailabilityService::class)->availableItemQuantity($item->id, '2026-09-01 10:00', '2026-09-01 11:00'));
    }

    public function test_decision_is_rejected_after_the_hold_deadline(): void
    {
        [$user, $item, $room, $vehicle] = $this->fixtures();
        $activity = $this->submit($user, $item, $room, $vehicle);
        $activity->updateQuietly(['hold_expires_at' => now()->subMinute()]);

        $this->expectException(ValidationException::class);
        $activity->item_reservation()->first()->update(['status' => ReservationStatus::Approved->value]);
    }

    public function test_expiry_command_processes_elapsed_holds(): void
    {
        [$user, $item, $room, $vehicle] = $this->fixtures();
        $activity = $this->submit($user, $item, $room, $vehicle);
        $activity->updateQuietly(['hold_expires_at' => now()->subMinute()]);

        $this->artisan('loans:expire-holds')
            ->expectsOutput('1 hold peminjaman kedaluwarsa telah diproses.')
            ->assertSuccessful();
    }

    public function test_item_availability_follows_the_complete_status_matrix(): void
    {
        [$user, $item, $room, $vehicle] = $this->fixtures();
        $activity = $this->submit($user, $item, $room, $vehicle);
        $reservation = $activity->item_reservation()->first();
        $availability = app(LoanAvailabilityService::class);
        $available = fn (): int => $availability->availableItemQuantity(
            $item->id,
            '2026-09-01 10:00',
            '2026-09-01 11:00',
        );

        foreach ([
            ReservationStatus::Submitted,
            ReservationStatus::Approved,
            ReservationStatus::PartiallyApproved,
            ReservationStatus::CheckedOut,
        ] as $status) {
            $reservation->updateQuietly(['status' => $status->value]);
            $this->assertSame(3, $available(), "{$status->value} harus menahan stok.");
        }

        foreach ([
            ReservationStatus::Draft,
            ReservationStatus::Rejected,
            ReservationStatus::Returned,
            ReservationStatus::Cancelled,
            ReservationStatus::Expired,
        ] as $status) {
            $reservation->updateQuietly(['status' => $status->value]);
            $this->assertSame(5, $available(), "{$status->value} harus melepaskan stok.");
        }
    }

    public function test_open_overdue_usage_blocks_future_item_room_and_vehicle_availability(): void
    {
        [$user, $item, $room, $vehicle] = $this->fixtures();
        $activity = $this->submit($user, $item, $room, $vehicle);
        $activity->item_reservation()->update([
            'status' => ReservationStatus::CheckedOut->value,
            'start_time' => now()->subDays(2),
            'end_time' => now()->subDay(),
        ]);
        $activity->room_reservation()->update([
            'status' => ReservationStatus::ReturnRequested->value,
            'start_time' => now()->subDays(2),
            'end_time' => now()->subDay(),
        ]);
        $activity->vehicle_reservation()->update([
            'status' => ReservationStatus::CheckedOut->value,
            'start_time' => now()->subDays(2),
            'end_time' => now()->subDay(),
        ]);
        $availability = app(LoanAvailabilityService::class);
        $futureStart = now()->addDay();
        $futureEnd = now()->addDay()->addHour();

        $this->assertSame(3, $availability->availableItemQuantity($item->id, $futureStart, $futureEnd));
        $this->assertFalse($availability->roomIsAvailable($room->id, $futureStart, $futureEnd));
        $this->assertFalse($availability->vehicleIsAvailable($vehicle->id, $futureStart, $futureEnd));
    }

    private function submit(User $user, G002M007Item $item, G003M006Room $room, G008M017Vehicle $vehicle)
    {
        return app(LoanRequestService::class)->submit($user, [
            'name' => 'Kegiatan terjadwal',
            'description' => 'Pengujian siklus booking.',
            'start_time' => '2026-09-01 09:00:00',
            'end_time' => '2026-09-01 12:00:00',
            'needs' => [
                ['type' => 'item', 'item_id' => $item->id, 'quantity' => 2],
                ['type' => 'room', 'room_id' => $room->id],
                ['type' => 'vehicle', 'vehicle_id' => $vehicle->id],
            ],
        ]);
    }

    private function fixtures(): array
    {
        $unit = G001M001Unit::query()->create(['name' => 'Unit Booking']);
        $user = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        Role::query()->firstOrCreate(['name' => config('role.sarpras'), 'guard_name' => 'web']);
        $user->assignRole(config('role.sarpras'));
        $management = G002M003ItemManagement::query()->create(['name' => 'Pengelola Booking']);
        $management->users()->attach($user);
        $this->actingAs($user);
        $item = G002M007Item::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'name' => 'Proyektor Booking',
            'is_borrowable' => true,
            'quantity' => 5,
            'available_quantity' => 5,
            'status' => 'tersedia',
        ]);
        $room = G003M006Room::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'name' => 'Aula Booking',
            'is_borrowable' => true,
            'status' => 'tersedia',
        ]);
        $vehicle = G008M017Vehicle::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'name' => 'Mobil Booking',
            'license_plate' => 'B 1000 TEST',
            'is_borrowable' => true,
            'status' => 'tersedia',
        ]);

        $driver = G008M018Driver::query()->create([
            'user_id' => $user->id,
            'sim_number' => 'SIM-TEST',
            'sim_type' => 'B1',
        ]);
        $vehicle->update(['default_driver_id' => $driver->id]);

        return [$user, $item, $room, $vehicle];
    }
}
