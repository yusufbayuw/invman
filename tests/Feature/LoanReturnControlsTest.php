<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use App\Models\G003M006Room;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M016ItemReservationDetail;
use App\Models\User;
use App\Services\LoanRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoanReturnControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_reminder_is_sent_to_borrower_and_manager_at_most_once_per_day(): void
    {
        [$borrower, $manager, $unit, $management] = $this->actors();
        $activity = $this->activity($borrower, $unit, ReservationStatus::CheckedOut);
        $room = G003M006Room::query()->create([
            'name' => 'Aula',
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'is_borrowable' => true,
        ]);
        $reservation = G005M010RoomReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g003_m006_room_id' => $room->id,
            'start_time' => now()->subDays(2),
            'end_time' => now()->subDay(),
            'status' => ReservationStatus::CheckedOut->value,
        ]);

        $service = app(LoanRequestService::class);
        $this->assertSame(1, $service->notifyOverdueLoans());
        $this->assertSame(0, $service->notifyOverdueLoans());
        $this->assertContains('Peminjaman terlambat dikembalikan', $borrower->notifications()->get()->pluck('data.title'));
        $this->assertContains('Peminjaman terlambat dikembalikan', $manager->notifications()->get()->pluck('data.title'));
        $this->assertNotNull($reservation->fresh()->overdue_notified_at);

        $reservation->updateQuietly(['overdue_notified_at' => now()->subDays(2)]);
        $this->assertSame(1, $service->notifyOverdueLoans());
    }

    public function test_item_return_records_each_instance_and_requires_two_party_confirmation(): void
    {
        [$borrower, $manager, $unit, $management] = $this->actors();
        $activity = $this->activity($borrower, $unit, ReservationStatus::CheckedOut);
        $item = G002M007Item::query()->create([
            'name' => 'Kamera',
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'is_borrowable' => true,
            'quantity' => 2,
            'available_quantity' => 0,
        ]);
        $reservation = G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g002_m007_item_id' => $item->id,
            'quantity' => 2,
            'start_time' => now()->subHours(2),
            'end_time' => now()->addHour(),
            'status' => ReservationStatus::CheckedOut->value,
        ]);
        $instances = collect(['CAM-01', 'CAM-02'])->map(fn (string $code) => G002M015ItemInstance::query()->create([
            'g002_m007_item_id' => $item->id,
            'name' => $code,
            'code' => $code,
            'status' => ReservationStatus::CheckedOut->value,
            'is_available' => false,
            'is_borrowable' => true,
        ]));
        foreach ($instances as $instance) {
            G005M016ItemReservationDetail::query()->create([
                'g005_m009_item_reservation_id' => $reservation->id,
                'g002_m015_item_instance_id' => $instance->id,
            ]);
        }

        $this->actingAs($borrower);
        app(LoanRequestService::class)->requestReservationReturn('item', $reservation->id, [
            'instances' => [
                ['item_instance_id' => $instances[0]->id, 'is_ok' => true],
                ['item_instance_id' => $instances[1]->id, 'is_ok' => false, 'notes' => 'Lensa retak.'],
            ],
            'proof_path' => 'loan-return-receipts/bukti.pdf',
        ]);

        $this->assertSame(ReservationStatus::ReturnRequested->value, $reservation->fresh()->status);
        $this->assertDatabaseCount('loan_reservation_checklists', 2);
        $this->assertDatabaseHas('loan_handover_receipts', [
            'reservation_id' => $reservation->id,
            'borrower_confirmed_by' => $borrower->id,
            'manager_confirmed_by' => null,
            'proof_path' => 'loan-return-receipts/bukti.pdf',
        ]);

        $this->actingAs($manager);
        app(LoanRequestService::class)->confirmReturn('item', $reservation->id);

        $receipt = $reservation->fresh()->returnReceipt;
        $this->assertSame(ReservationStatus::Returned->value, $reservation->fresh()->status);
        $this->assertNotNull($receipt->receipt_number);
        $this->assertNotNull($receipt->borrower_confirmed_at);
        $this->assertNotNull($receipt->manager_confirmed_at);
        $this->assertNotNull($receipt->completed_at);
        $this->assertTrue((bool) $instances[0]->fresh()->is_available);
        $this->assertFalse((bool) $instances[1]->fresh()->is_available);
        $this->assertFalse((bool) $instances[1]->fresh()->is_borrowable);
    }

    public function test_admin_correction_reopens_return_without_deleting_original_history(): void
    {
        [$borrower, , $unit, $management] = $this->actors();
        $admin = User::factory()->create();
        $admin->assignRole(config('role.admin'));
        $activity = $this->activity($borrower, $unit, ReservationStatus::Returned);
        $room = G003M006Room::query()->create([
            'name' => 'Ruang Rapat',
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'is_borrowable' => true,
        ]);
        $reservation = G005M010RoomReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g003_m006_room_id' => $room->id,
            'start_time' => now()->subHours(3),
            'end_time' => now()->subHour(),
            'returned_at' => now()->subMinutes(10),
            'status' => ReservationStatus::Returned->value,
        ]);

        $this->actingAs($admin);
        app(LoanRequestService::class)->correctReservationStatus(
            'room',
            $reservation->id,
            ReservationStatus::CheckedOut->value,
            'Pengembalian tercatat pada reservasi yang salah.',
        );

        $this->assertSame(ReservationStatus::CheckedOut->value, $reservation->fresh()->status);
        $this->assertNull($reservation->fresh()->returned_at);
        $this->assertDatabaseHas('loan_reservation_corrections', [
            'reservation_id' => $reservation->id,
            'old_value' => ReservationStatus::Returned->value,
            'new_value' => ReservationStatus::CheckedOut->value,
            'corrected_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('loan_reservation_status_histories', [
            'reservation_id' => $reservation->id,
            'from_status' => ReservationStatus::Returned->value,
            'to_status' => ReservationStatus::CheckedOut->value,
            'changed_by' => $admin->id,
        ]);
    }

    private function actors(): array
    {
        Role::query()->firstOrCreate(['name' => config('role.admin'), 'guard_name' => 'web']);
        Role::query()->firstOrCreate(['name' => config('role.sarpras'), 'guard_name' => 'web']);
        $unit = G001M001Unit::query()->create(['name' => 'Unit Pengujian']);
        $borrower = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $borrower->assignRole(config('role.sarpras'));
        $manager = User::factory()->create();
        $management = G002M003ItemManagement::query()->create(['name' => 'Pengelola Aset']);
        $management->users()->attach($manager);

        return [$borrower, $manager, $unit, $management];
    }

    private function activity(User $borrower, G001M001Unit $unit, ReservationStatus $status): G004M008Activity
    {
        return G004M008Activity::query()->create([
            'user_id' => $borrower->id,
            'g001_m001_unit_id' => $unit->id,
            'name' => 'Kegiatan Pengujian',
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
            'status' => $status->value,
        ]);
    }
}
