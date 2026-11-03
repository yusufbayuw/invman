<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G005M009ItemReservation;
use App\Models\User;
use App\Services\LoanAvailabilityService;
use App\Services\LoanRequestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoanAvailabilitySchedulingTest extends TestCase
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

    public function test_sequential_bookings_use_peak_simultaneous_quantity_not_sum_of_all_overlaps(): void
    {
        [$user, $item] = $this->fixtures();
        $this->book($user, $item, '2026-09-01 09:00', '2026-09-01 10:00', 3);
        $this->book($user, $item, '2026-09-01 11:00', '2026-09-01 12:00', 3);

        $service = app(LoanAvailabilityService::class);
        $start = Carbon::parse('2026-09-01 09:00');
        $end = Carbon::parse('2026-09-01 12:00');
        $this->assertSame(3, $service->reservedItemQuantity($item->id, $start, $end));
        $this->assertSame(2, $service->availableItemQuantity($item->id, $start, $end));
        $this->assertSame(2, $service->availableItemQuantity($item->id, $start, $end, locking: true));
        $this->assertStringContainsString('2 tersedia', $service->itemOptions($start, $end)[$item->id]);

        // Submission must use exactly the same peak algorithm under locks.
        $this->book($user, $item, '2026-09-01 09:00', '2026-09-01 12:00', 2);
        $this->assertSame(0, $service->availableItemQuantity($item->id, $start, $end));
        $this->assertArrayNotHasKey($item->id, $service->itemOptions($start, $end));
    }

    public function test_adjacent_bookings_have_no_boundary_overlap_but_genuine_overlap_adds_quantities(): void
    {
        [$user, $item] = $this->fixtures();
        $this->book($user, $item, '2026-09-01 09:00', '2026-09-01 10:00', 3);
        $this->book($user, $item, '2026-09-01 10:00', '2026-09-01 11:00', 3);
        $service = app(LoanAvailabilityService::class);
        $this->assertSame(2, $service->availableItemQuantity(
            $item->id, '2026-09-01 09:00', '2026-09-01 11:00',
        ));
        $this->book($user, $item, '2026-09-01 09:00', '2026-09-01 11:00', 2);

        $this->assertSame(0, $service->availableItemQuantity(
            $item->id, '2026-09-01 09:30', '2026-09-01 10:30',
        ));
        try {
            $this->book($user, $item, '2026-09-01 09:30', '2026-09-01 10:30', 1);
            $this->fail('A real overlap above capacity should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('data.needs.0.quantity', $exception->errors());
        }
        $this->assertSame(3, G005M009ItemReservation::query()->count());
    }

    public function test_on_time_checkout_can_be_booked_after_planned_return_but_overdue_checkout_blocks_future(): void
    {
        [$user, $item] = $this->fixtures();
        $activity = $this->book($user, $item, '2026-09-01 08:00', '2026-09-01 09:00', 3);
        $reservation = $activity->item_reservation()->firstOrFail();
        $reservation->updateQuietly(['status' => ReservationStatus::CheckedOut->value]);

        $service = app(LoanAvailabilityService::class);
        $this->assertSame(2, $service->availableItemQuantity(
            $item->id, '2026-09-01 08:00', '2026-09-01 09:00',
        ));
        $this->assertSame(5, $service->availableItemQuantity(
            $item->id, '2026-09-01 10:00', '2026-09-01 11:00',
        ));

        $this->book($user, $item, '2026-09-01 10:00', '2026-09-01 11:00', 2);
        Carbon::setTestNow('2026-09-01 09:01:00');

        // Still physically out after the promised return: reduce availability
        // for EVERY upcoming interval, including beyond the original end.
        $this->assertSame(0, $service->availableItemQuantity(
            $item->id, '2026-09-01 10:00', '2026-09-01 11:00',
        ));
        $this->assertSame(2, $service->availableItemQuantity(
            $item->id, '2026-09-01 12:00', '2026-09-01 13:00',
        ));
    }

    public function test_return_requested_is_not_treated_as_returned_and_blocks_when_overdue(): void
    {
        [$user, $item] = $this->fixtures();
        $activity = $this->book($user, $item, '2026-09-01 08:00', '2026-09-01 09:00', 2);
        $reservation = $activity->item_reservation()->firstOrFail();
        $reservation->updateQuietly(['status' => ReservationStatus::ReturnRequested->value]);
        Carbon::setTestNow('2026-09-01 09:01:00');

        $this->assertSame(3, app(LoanAvailabilityService::class)->availableItemQuantity(
            $item->id, '2026-09-01 13:00', '2026-09-01 14:00',
        ));
    }

    public function test_draft_submission_rejects_start_that_has_passed_since_draft_creation(): void
    {
        [$user, $item] = $this->fixtures();
        $service = app(LoanRequestService::class);
        $draft = $service->saveDraft($user, [
            'name' => 'Draf yang kedaluwarsa',
            'description' => 'Disimpan pagi hari, dikirim setelah jadwal.',
            'start_time' => '2026-09-01 09:00',
            'end_time' => '2026-09-01 10:00',
            'needs' => [['type' => 'item', 'item_id' => $item->id, 'quantity' => 1]],
        ]);
        Carbon::setTestNow('2026-09-01 09:01:00');

        try {
            $service->submitDraft($user, $draft);
            $this->fail('A draft should never submit after its start time.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('data.start_time', $exception->errors());
        }
        $this->assertSame(ReservationStatus::Draft->value, $draft->fresh()->status);
        $this->assertNull($draft->fresh()->hold_expires_at);
        $this->assertSame(ReservationStatus::Draft->value, $draft->item_reservation()->firstOrFail()->status);
    }

    private function fixtures(): array
    {
        $unit = G001M001Unit::query()->create(['name' => 'QA Loan Scheduling']);
        $user = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        Role::query()->firstOrCreate(['name' => config('role.sarpras'), 'guard_name' => 'web']);
        $user->assignRole(config('role.sarpras'));
        $this->actingAs($user);

        $item = G002M007Item::query()->create([
            'name' => 'Proyektor Jadwal',
            'g001_m001_unit_id' => $unit->id,
            'quantity' => 5,
            'available_quantity' => 5,
            'is_borrowable' => true,
            'status' => 'tersedia',
        ]);

        return [$user, $item];
    }

    private function book(User $user, G002M007Item $item, string $start, string $end, int $quantity): \App\Models\G004M008Activity
    {
        return app(LoanRequestService::class)->submit($user, [
            'name' => 'Peminjaman proyektor jadwal',
            'description' => 'QA pengaturan slot dan jumlah.',
            'start_time' => $start,
            'end_time' => $end,
            'needs' => [['type' => 'item', 'item_id' => $item->id, 'quantity' => $quantity]],
        ]);
    }
}
