<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\G009M022ItemInstanceChecklist;
use App\Models\User;
use App\Services\LoanAvailabilityService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ObserverBusinessFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_instance_is_the_source_of_truth_for_stock_totals(): void
    {
        $item = G002M007Item::query()->create([
            'name' => 'Proyektor',
            'code' => 'PRJ',
            'quantity' => 1,
            'is_borrowable' => true,
        ]);

        $this->assertSame(1, $item->item_instance()->count());

        $instance = G002M015ItemInstance::query()->create([
            'g002_m007_item_id' => $item->id,
        ]);

        $this->assertSame('Proyektor 2', $instance->name);
        $this->assertSame('PRJ-2', $instance->code);
        $this->assertSame(2, (int) $item->fresh()->quantity);
        $this->assertSame(2, (int) $item->fresh()->available_quantity);

        $instance->update(['is_available' => false]);

        $this->assertSame(2, (int) $item->fresh()->quantity);
        $this->assertSame(1, (int) $item->fresh()->available_quantity);

        $instance->delete();

        $this->assertSame(1, (int) $item->fresh()->quantity);
        $this->assertSame(1, (int) $item->fresh()->available_quantity);
    }

    public function test_master_quantity_cannot_be_changed_directly_after_creation(): void
    {
        $item = G002M007Item::query()->create([
            'name' => 'Kamera',
            'quantity' => 2,
            'is_borrowable' => true,
        ]);

        try {
            $item->update(['quantity' => 5]);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Jumlah barang dikelola melalui data Barang Satuan.',
                $exception->errors()['quantity'][0],
            );
        }

        $this->assertSame(2, (int) $item->fresh()->quantity);
        $this->assertSame(2, $item->item_instance()->count());
    }

    public function test_reservation_created_under_a_draft_does_not_hold_inventory(): void
    {
        $item = G002M007Item::query()->create([
            'name' => 'Tablet',
            'quantity' => 3,
            'is_borrowable' => true,
        ]);
        $activity = G004M008Activity::query()->create([
            'name' => 'Draf kegiatan',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => ReservationStatus::Draft->value,
        ]);

        $reservation = G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g002_m007_item_id' => $item->id,
            'quantity' => 2,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
        ]);

        $this->assertSame(ReservationStatus::Draft->value, $reservation->status);
        $this->assertSame(3, app(LoanAvailabilityService::class)->availableItemQuantity(
            $item->id,
            $activity->start_time,
            $activity->end_time,
        ));
    }

    public function test_monthly_generation_does_not_mark_a_placeholder_as_inspected(): void
    {
        Carbon::setTestNow('2026-08-01 08:00:00');
        $item = G002M007Item::query()->create([
            'name' => 'Laptop',
            'quantity' => 1,
            'is_borrowable' => true,
        ]);
        $instance = $item->item_instance()->firstOrFail();

        $attributes = [
            'g002_m015_item_instance_id' => $instance->id,
            'date' => now()->startOfMonth(),
        ];

        $checklist = G009M022ItemInstanceChecklist::query()->updateOrCreate($attributes, []);
        Carbon::setTestNow('2026-08-02 08:00:00');
        G009M022ItemInstanceChecklist::query()->updateOrCreate($attributes, []);

        $checklist->refresh();
        $this->assertNull($checklist->is_ok);
        $this->assertNull($checklist->user_id);
        $this->assertNull($checklist->checklist_date);
    }

    public function test_first_inspection_is_stamped_once_for_both_good_and_bad_results(): void
    {
        Carbon::setTestNow('2026-08-03 09:00:00');
        $user = User::factory()->create();
        $item = G002M007Item::query()->create([
            'name' => 'Speaker',
            'quantity' => 1,
            'is_borrowable' => true,
        ]);
        $checklist = G009M022ItemInstanceChecklist::query()->create([
            'g002_m015_item_instance_id' => $item->item_instance()->firstOrFail()->id,
            'date' => now()->startOfMonth(),
        ]);

        $this->actingAs($user);
        $checklist->update(['is_ok' => false]);

        $this->assertSame($user->id, $checklist->fresh()->user_id);
        $this->assertTrue($checklist->fresh()->checklist_date->equalTo(now()));

        Carbon::setTestNow('2026-08-04 10:00:00');
        $checklist->update(['notes' => 'Perlu servis.']);

        $this->assertSame('2026-08-03 09:00:00', $checklist->fresh()->checklist_date->format('Y-m-d H:i:s'));
    }

    public function test_monthly_checklist_is_unique_per_item_instance(): void
    {
        $item = G002M007Item::query()->create([
            'name' => 'Monitor',
            'quantity' => 1,
            'is_borrowable' => true,
        ]);
        $attributes = [
            'g002_m015_item_instance_id' => $item->item_instance()->firstOrFail()->id,
            'date' => now()->startOfMonth(),
        ];
        G009M022ItemInstanceChecklist::query()->create($attributes);

        $this->expectException(QueryException::class);
        G009M022ItemInstanceChecklist::query()->create($attributes);
    }

    public function test_assets_with_checklist_history_cannot_be_deleted(): void
    {
        $item = G002M007Item::query()->create([
            'name' => 'Router',
            'quantity' => 1,
            'is_borrowable' => true,
        ]);
        $instance = $item->item_instance()->firstOrFail();
        G009M022ItemInstanceChecklist::query()->create([
            'g002_m015_item_instance_id' => $instance->id,
            'date' => now()->startOfMonth(),
        ]);

        try {
            $instance->delete();
            $this->fail('Expected instance deletion to be blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('item_instance', $exception->errors());
        }

        try {
            $item->delete();
            $this->fail('Expected item deletion to be blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('item', $exception->errors());
        }

        $this->assertDatabaseHas('g002_m007_items', ['id' => $item->id]);
        $this->assertDatabaseHas('g002_m015_item_instances', ['id' => $instance->id]);
    }

    public function test_submitted_requests_and_reservations_cannot_be_deleted(): void
    {
        $item = G002M007Item::query()->create([
            'name' => 'Kabel HDMI',
            'quantity' => 1,
            'is_borrowable' => true,
        ]);
        $activity = G004M008Activity::query()->create([
            'name' => 'Pengajuan terkirim',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => ReservationStatus::Submitted->value,
        ]);
        $reservation = G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g002_m007_item_id' => $item->id,
            'quantity' => 1,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
            'status' => ReservationStatus::Submitted->value,
        ]);

        try {
            $reservation->delete();
            $this->fail('Expected submitted reservation deletion to be blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        try {
            $activity->delete();
            $this->fail('Expected submitted activity deletion to be blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('activity', $exception->errors());
        }

        $this->assertDatabaseHas('g004_m008_activities', ['id' => $activity->id]);
        $this->assertDatabaseHas('g005_m009_item_reservations', ['id' => $reservation->id]);
    }
}
