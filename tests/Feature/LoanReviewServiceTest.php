<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Resources\G004M008ActivityResource\Pages\ListG004M008Activities;
use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use App\Models\G003M006Room;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M016ItemReservationDetail;
use App\Models\G005M019VehicleReservation;
use App\Models\G006M011ItemReview;
use App\Models\G008M017Vehicle;
use App\Models\LoanRequestChecklist;
use App\Models\User;
use App\Services\LoanReviewService;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoanReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-01 10:00:00');
        Role::query()->firstOrCreate(['name' => config('role.sarpras'), 'guard_name' => 'web']);
        if (! TextColumn::hasMacro('simpleLightbox')) {
            TextColumn::macro('simpleLightbox', function (...$arguments): TextColumn {
                return $this;
            });
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_saves_overall_and_returned_asset_reviews_atomically(): void
    {
        [$borrower, $activity, $itemReservation, $instances, $roomReservation, $vehicleReservation] = $this->returnedLoan();
        $service = app(LoanReviewService::class);
        $data = $service->formData($activity);
        $data['overall'] = ['rating' => 5, 'review' => 'Proses peminjaman sangat baik.'];
        $data['items'][0]['rating'] = 4;
        $data['items'][1]['review'] = 'Barang kedua bekerja dengan baik.';
        $data['rooms'][0]['rating'] = 5;
        $data['vehicles'][0]['review'] = 'Kendaraan bersih dan nyaman.';

        $service->save($activity, $borrower, $data);

        $this->assertDatabaseHas('loan_request_reviews', [
            'g004_m008_activity_id' => $activity->id,
            'user_id' => $borrower->id,
            'rating' => 5,
            'review' => 'Proses peminjaman sangat baik.',
        ]);
        $this->assertDatabaseHas('g006_m011_item_reviews', [
            'g004_m008_activity_id' => $activity->id,
            'g005_m009_item_reservation_id' => $itemReservation->id,
            'g002_m015_item_instance_id' => $instances[0]->id,
            'rating' => 4,
        ]);
        $this->assertDatabaseHas('g006_m011_item_reviews', [
            'g004_m008_activity_id' => $activity->id,
            'g002_m015_item_instance_id' => $instances[1]->id,
            'rating' => null,
            'review' => 'Barang kedua bekerja dengan baik.',
        ]);
        $this->assertDatabaseHas('g006_m012_room_reviews', [
            'g004_m008_activity_id' => $activity->id,
            'g005_m010_room_reservation_id' => $roomReservation->id,
            'rating' => 5,
        ]);
        $this->assertDatabaseHas('g006_m020_vehicle_reviews', [
            'g004_m008_activity_id' => $activity->id,
            'g005_m019_vehicle_reservation_id' => $vehicleReservation->id,
            'review' => 'Kendaraan bersih dan nyaman.',
        ]);
    }

    public function test_empty_values_remove_only_integrated_reviews_and_leave_checklist_untouched(): void
    {
        [$borrower, $activity, $itemReservation, $instances] = $this->returnedLoan();
        LoanRequestChecklist::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'user_id' => $borrower->id,
            'stage' => 'return',
            'is_ok' => false,
            'notes' => 'Ada goresan yang sudah dicatat.',
            'photo' => 'loan-return-checklists/proof.jpg',
        ]);
        $standalone = G006M011ItemReview::query()->create([
            'g002_m015_item_instance_id' => $instances[0]->id,
            'g005_m009_item_reservation_id' => $itemReservation->id,
            'user_id' => $borrower->id,
            'rating' => 3,
            'review' => 'Review standalone lama.',
        ]);

        $service = app(LoanReviewService::class);
        $data = $service->formData($activity);
        $data['overall']['review'] = 'Ulasan sementara.';
        $data['items'][0]['rating'] = 4;
        $service->save($activity, $borrower, $data);

        $empty = $service->formData($activity->fresh());
        $empty['overall'] = ['rating' => null, 'review' => '   '];
        foreach (['items', 'rooms', 'vehicles'] as $group) {
            foreach ($empty[$group] as &$row) {
                $row['rating'] = null;
                $row['review'] = '';
            }
            unset($row);
        }
        $service->save($activity->fresh(), $borrower, $empty);

        $this->assertDatabaseMissing('loan_request_reviews', ['g004_m008_activity_id' => $activity->id]);
        $this->assertDatabaseMissing('g006_m011_item_reviews', ['g004_m008_activity_id' => $activity->id]);
        $this->assertDatabaseMissing('g006_m012_room_reviews', ['g004_m008_activity_id' => $activity->id]);
        $this->assertDatabaseMissing('g006_m020_vehicle_reviews', ['g004_m008_activity_id' => $activity->id]);
        $this->assertDatabaseHas('g006_m011_item_reviews', ['id' => $standalone->id, 'g004_m008_activity_id' => null]);
        $this->assertDatabaseHas('loan_request_checklists', [
            'g004_m008_activity_id' => $activity->id,
            'is_ok' => false,
            'notes' => 'Ada goresan yang sudah dicatat.',
            'photo' => 'loan-return-checklists/proof.jpg',
        ]);
    }

    public function test_it_updates_existing_reviews_without_creating_duplicates(): void
    {
        [$borrower, $activity] = $this->returnedLoan();
        $service = app(LoanReviewService::class);
        $data = $service->formData($activity);
        $data['overall']['rating'] = 3;
        $data['items'][0]['rating'] = 2;
        $service->save($activity, $borrower, $data);

        $updated = $service->formData($activity->fresh());
        $updated['overall']['rating'] = 5;
        $updated['items'][0]['rating'] = 4;
        $service->save($activity->fresh(), $borrower, $updated);

        $this->assertSame(1, $activity->review()->count());
        $this->assertSame(1, $activity->item_reviews()->count());
        $this->assertSame(5, $activity->review()->value('rating'));
        $this->assertSame(4, $activity->item_reviews()->value('rating'));
    }

    public function test_borrower_can_submit_the_integrated_filament_action(): void
    {
        [$borrower, $activity] = $this->returnedLoan();
        $component = Livewire::actingAs($borrower)
            ->test(ListG004M008Activities::class)
            ->assertTableActionVisible('review', $activity)
            ->mountTableAction('review', $activity);
        $state = $component->get('mountedTableActionsData.0');
        $firstItemKey = array_key_first($state['items']);
        $component
            ->set('mountedTableActionsData.0.overall.rating', 5)
            ->set("mountedTableActionsData.0.items.{$firstItemKey}.review", 'Berfungsi normal.')
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('loan_request_reviews', [
            'g004_m008_activity_id' => $activity->id,
            'rating' => 5,
        ]);
        $this->assertDatabaseHas('g006_m011_item_reviews', [
            'g004_m008_activity_id' => $activity->id,
            'review' => 'Berfungsi normal.',
        ]);
    }

    public function test_review_is_available_for_mixed_terminal_statuses_but_not_blocking_statuses(): void
    {
        [$borrower, $activity, , , $roomReservation, $vehicleReservation] = $this->returnedLoan();
        $service = app(LoanReviewService::class);

        $roomReservation->updateQuietly(['status' => ReservationStatus::Rejected->value, 'returned_at' => null]);
        $vehicleReservation->updateQuietly(['status' => ReservationStatus::Cancelled->value, 'returned_at' => null]);
        $this->assertTrue($service->canReview($activity->fresh(), $borrower));
        $targets = $service->formData($activity->fresh());
        $this->assertCount(2, $targets['items']);
        $this->assertSame([], $targets['rooms']);
        $this->assertSame([], $targets['vehicles']);

        $roomReservation->updateQuietly(['status' => ReservationStatus::Approved->value]);
        $this->assertFalse($service->canReview($activity->fresh(), $borrower));
    }

    public function test_it_rejects_expired_unauthorized_invalid_and_tampered_submissions(): void
    {
        [$borrower, $activity] = $this->returnedLoan();
        $service = app(LoanReviewService::class);
        $valid = $service->formData($activity);
        $valid['overall']['review'] = 'Tidak boleh tersimpan sebagian.';

        $invalid = $valid;
        $invalid['items'][0]['rating'] = 6;
        $this->expectValidationException(fn () => $service->save($activity, $borrower, $invalid));
        $this->assertDatabaseMissing('loan_request_reviews', ['g004_m008_activity_id' => $activity->id]);

        $tampered = $valid;
        $tampered['items'][0]['item_instance_id'] = 999999;
        $this->expectValidationException(fn () => $service->save($activity, $borrower, $tampered));
        $this->assertDatabaseMissing('loan_request_reviews', ['g004_m008_activity_id' => $activity->id]);

        $otherUnit = G001M001Unit::query()->create(['name' => 'Unit Lain']);
        $outsider = User::factory()->create(['g001_m001_unit_id' => $otherUnit->id]);
        $outsider->assignRole(config('role.sarpras'));
        $this->expectValidationException(fn () => $service->save($activity, $outsider, $valid));

        foreach ([$activity->item_reservation(), $activity->room_reservation(), $activity->vehicle_reservation()] as $query) {
            $query->update(['returned_at' => now()->subDays(8)]);
        }
        $this->assertFalse($service->canReview($activity->fresh(), $borrower));
        $this->expectValidationException(fn () => $service->save($activity->fresh(), $borrower, $valid));
    }

    private function expectValidationException(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a validation exception.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    /** @return array{User, G004M008Activity, G005M009ItemReservation, list<G002M015ItemInstance>, G005M010RoomReservation, G005M019VehicleReservation} */
    private function returnedLoan(): array
    {
        $unit = G001M001Unit::query()->create(['name' => 'Unit Peminjam']);
        $borrower = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $borrower->assignRole(config('role.sarpras'));
        $activity = G004M008Activity::query()->create([
            'user_id' => $borrower->id,
            'g001_m001_unit_id' => $unit->id,
            'name' => 'Kegiatan Review',
            'start_time' => now()->subHours(3),
            'end_time' => now()->subHours(2),
            'status' => ReservationStatus::Returned->value,
        ]);

        $item = G002M007Item::query()->create(['name' => 'Laptop', 'quantity' => 2, 'available_quantity' => 2]);
        $itemReservation = G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g002_m007_item_id' => $item->id,
            'quantity' => 2,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
            'returned_at' => now()->subDay(),
            'status' => ReservationStatus::Returned->value,
        ]);
        $instances = collect(['LPT-01', 'LPT-02'])->map(function (string $code) use ($item, $itemReservation): G002M015ItemInstance {
            $instance = G002M015ItemInstance::query()->create([
                'g002_m007_item_id' => $item->id,
                'name' => "Laptop {$code}",
                'code' => $code,
                'is_available' => true,
                'is_borrowable' => true,
            ]);
            G005M016ItemReservationDetail::query()->create([
                'g005_m009_item_reservation_id' => $itemReservation->id,
                'g002_m015_item_instance_id' => $instance->id,
            ]);

            return $instance;
        })->values()->all();

        $room = G003M006Room::query()->create(['name' => 'Ruang Aula', 'g001_m001_unit_id' => $unit->id, 'is_borrowable' => true]);
        $roomReservation = G005M010RoomReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g003_m006_room_id' => $room->id,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
            'returned_at' => now()->subHours(12),
            'status' => ReservationStatus::Returned->value,
        ]);

        $vehicle = G008M017Vehicle::query()->create(['name' => 'Toyota Hiace', 'license_plate' => 'B 1234 CD', 'g001_m001_unit_id' => $unit->id, 'is_borrowable' => true]);
        $vehicleReservation = G005M019VehicleReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g008_m017_vehicle_id' => $vehicle->id,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
            'returned_at' => now()->subHour(),
            'status' => ReservationStatus::Returned->value,
        ]);

        return [$borrower, $activity, $itemReservation, $instances, $roomReservation, $vehicleReservation];
    }
}
