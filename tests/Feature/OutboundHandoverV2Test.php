<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Pages\PeminjamanSerahTerima;
use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G008M017Vehicle;
use App\Models\G005M019VehicleReservation;
use App\Models\User;
use App\Services\LoanCheckoutService;
use App\Services\LoanRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OutboundHandoverV2Test extends TestCase
{
    use RefreshDatabase;

    private function actors(): array
    {
        Role::query()->firstOrCreate(['name' => config('role.sarpras'), 'guard_name' => 'web']);
        $unit = G001M001Unit::query()->create(['name' => 'Unit Serah Terima V2']);
        $manager = User::factory()->create();
        $borrower = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $borrower->assignRole(config('role.sarpras'));
        $outsider = User::factory()->create();
        $management = G002M003ItemManagement::query()->create(['name' => 'Tim Gudang']);
        $management->users()->attach($manager);
        $activity = G004M008Activity::query()->create([
            'name' => 'Pelatihan Instrumentasi',
            'user_id' => $borrower->id,
            'g001_m001_unit_id' => $unit->id,
            'status' => ReservationStatus::Approved->value,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
        ]);

        return [$manager, $borrower, $outsider, $management, $activity];
    }

    private function room(): array
    {
        [$manager, $borrower, $outsider, $management, $activity] = $this->actors();
        $room = \App\Models\G003M006Room::query()->create([
            'name' => 'Studio QR V2',
            'g001_m001_unit_id' => $activity->g001_m001_unit_id,
            'g002_m003_item_management_id' => $management->id,
            'is_borrowable' => true,
        ]);
        $reservation = G005M010RoomReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g003_m006_room_id' => $room->id,
            'status' => ReservationStatus::Approved->value,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
        ]);

        return [$manager, $borrower, $outsider, $reservation];
    }

    public function test_manager_initial_checklist_does_not_checkout_until_different_borrower_confirms(): void
    {
        [$manager, $borrower, $outsider, $reservation] = $this->room();
        $service = app(LoanCheckoutService::class);

        $this->actingAs($manager);
        $receipt = $service->begin('room', $reservation->id, [
            'is_ok' => false, 'notes' => 'Kursi nomor 5 sudah rusak.',
            'receipt_notes' => 'Pengelola mencatat kondisi sebelum dipakai.',
        ]);

        $this->assertSame(ReservationStatus::Approved->value, $reservation->fresh()->status);
        $this->assertStringStartsWith('OUT-', $receipt->receipt_number);
        $this->assertNotNull($receipt->manager_confirmed_at);
        $this->assertNull($receipt->borrower_confirmed_at);
        $this->assertDatabaseHas('loan_checkout_checklists', [
            'reservation_type' => 'room', 'reservation_id' => $reservation->id,
            'is_ok' => false, 'notes' => 'Kursi nomor 5 sudah rusak.',
        ]);

        $this->expectException(ValidationException::class);
        $service->confirm('room', $reservation->id);
    }

    public function test_checkout_confirmation_is_scoped_and_concurrent_resubmission_is_rejected(): void
    {
        [$manager, $borrower, $outsider, $reservation] = $this->room();
        $service = app(LoanCheckoutService::class);
        $this->actingAs($manager);
        $service->begin('room', $reservation->id, ['is_ok' => true]);

        $this->actingAs($outsider);
        $this->assertFalse((bool) $service->canConfirm($reservation->fresh()));
        try {
            $service->confirm('room', $reservation->id);
            $this->fail('Outsider should not confirm checkout.');
        } catch (ValidationException) {
            $this->assertSame(ReservationStatus::Approved->value, $reservation->fresh()->status);
        }

        $this->actingAs($borrower);
        $service->confirm('room', $reservation->id);

        $this->assertSame(ReservationStatus::CheckedOut->value, $reservation->fresh()->status);
        $this->assertNotNull($reservation->fresh()->outboundReceipt->borrower_confirmed_at);
        $this->assertNotNull($reservation->fresh()->outboundReceipt->completed_at);
        $this->assertSame($borrower->id, $reservation->fresh()->outboundReceipt->borrower_confirmed_by);

        $this->expectException(ValidationException::class);
        $service->confirm('room', $reservation->id);
    }

    public function test_fallback_requires_reason_and_records_single_party_exception(): void
    {
        [$manager, $borrower, , $reservation] = $this->room();
        $service = app(LoanCheckoutService::class);
        $this->actingAs($manager);

        try {
            $service->fallback('room', $reservation->id, ['reason' => 'singkat']);
            $this->fail('Short reason accepted.');
        } catch (ValidationException) {
            $this->assertNull($reservation->fresh()->outboundReceipt);
        }

        $service->fallback('room', $reservation->id, [
            'reason' => 'Peminjam berhalangan hadir secara fisik; penerimaan disaksikan staf.',
        ]);

        $this->assertSame(ReservationStatus::CheckedOut->value, $reservation->fresh()->status);
        $receipt = $reservation->fresh()->outboundReceipt;
        $this->assertNotNull($receipt->manager_confirmed_at);
        $this->assertNull($receipt->borrower_confirmed_at);
        $this->assertNotNull($receipt->completed_at);
        $this->assertStringContainsString('berhalangan hadir', $receipt->fallback_reason);

        $this->actingAs($borrower);
        $this->assertFalse((bool) $service->canConfirm($reservation->fresh()));
    }

    public function test_initial_item_conditions_are_bound_to_exact_allocated_instances(): void
    {
        [$manager, $borrower, , $management, $activity] = $this->actors();
        $item = G002M007Item::query()->create([
            'name' => 'Alat Mikroskop',
            'g002_m003_item_management_id' => $management->id,
            'quantity' => 2, 'available_quantity' => 2, 'is_borrowable' => true,
        ]);
        $reservation = G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g002_m007_item_id' => $item->id,
            'quantity' => 2,
            'status' => ReservationStatus::Approved->value,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
        ]);
        foreach (['MIC-01', 'MIC-02'] as $code) {
            G002M015ItemInstance::query()->create([
                'g002_m007_item_id' => $item->id,
                'name' => $code,
                'code' => $code,
                'status' => 'tersedia',
                'is_available' => true, 'is_borrowable' => true,
            ]);
        }

        $service = app(LoanCheckoutService::class);
        $this->actingAs($manager);
        $options = $service->instanceOptions($reservation);
        $this->assertCount(2, $options);
        $options[0]['notes'] = 'Lensa perlu dibersihkan.';
        $options[0]['is_ok'] = false;

        $service->begin('item', $reservation->id, [
            'instances' => $options, 'receipt_notes' => 'Diserahkan oleh petugas inventaris',
        ]);
        $this->assertDatabaseCount('loan_checkout_checklists', 2);
        $this->assertSame(ReservationStatus::Approved->value, $reservation->fresh()->status);

        $this->actingAs($borrower);
        $service->confirm('item', $reservation->id);
        $this->assertSame(ReservationStatus::CheckedOut->value, $reservation->fresh()->status);

        $allocated = $reservation->fresh()->item_reservation_detail()
            ->pluck('g002_m015_item_instance_id')->sort()->values()->all();
        $expected = collect($options)->pluck('item_instance_id')->sort()->values()->all();
        $this->assertSame($expected, $allocated);
        $this->assertDatabaseHas('loan_checkout_checklists', [
            'reservation_id' => $reservation->id,
            'g002_m015_item_instance_id' => $options[0]['item_instance_id'],
            'is_ok' => false,
            'notes' => 'Lensa perlu dibersihkan.',
        ]);
    }

    public function test_outbound_vehicle_optional_odometer_and_no_status_change_until_confirmation(): void
    {
        [$manager, $borrower, , $management, $activity] = $this->actors();
        $vehicle = G008M017Vehicle::query()->create([
            'name' => 'Mobil Pool',
            'g002_m003_item_management_id' => $management->id,
            'license_plate' => 'D 9090 QW',
            'is_borrowable' => true,
        ]);
        $reservation = G005M019VehicleReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g008_m017_vehicle_id' => $vehicle->id,
            'status' => ReservationStatus::Approved->value,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
        ]);

        $this->actingAs($manager);
        app(LoanCheckoutService::class)->begin('vehicle', $reservation->id, [
            'is_ok' => true, 'checkout_odometer' => 18752,
        ]);
        $this->assertSame(18752, $reservation->fresh()->outboundReceipt->checkout_odometer);
        $this->assertSame(ReservationStatus::Approved->value, $reservation->fresh()->status);
    }

    public function test_legacy_direct_checkout_is_annotated_as_unverified_without_forged_borrower(): void
    {
        [$manager, $borrower, , $reservation] = $this->room();
        $this->actingAs($manager);
        app(LoanRequestService::class)->processReservation('room', $reservation->id, ReservationStatus::CheckedOut);
        $receipt = $reservation->fresh()->outboundReceipt;
        $this->assertNotNull($receipt);
        $this->assertNull($receipt->borrower_confirmed_by);
        $this->assertStringContainsString('legacy', $receipt->fallback_reason);
        $this->assertSame(ReservationStatus::CheckedOut->value, $reservation->fresh()->status);
    }

    public function test_qr_screen_shows_initial_condition_form_and_borrower_acceptance(): void
    {
        [$manager, $borrower, , $reservation] = $this->room();
        $q = ['type' => 'room', 'reservation' => $reservation->id];

        Livewire::withQueryParams($q)->actingAs($manager)
            ->test(PeminjamanSerahTerima::class)
            ->assertSee('Pemeriksaan Awal saat Penyerahan')
            ->fillForm(['is_ok' => true])
            ->call('submitCheckout')
            ->assertHasNoFormErrors();

        Livewire::withQueryParams($q)->actingAs($borrower)
            ->test(PeminjamanSerahTerima::class)
            ->assertSee('Konfirmasi Penerimaan')
            ->callAction('confirmCheckout')
            ->assertHasNoActionErrors();

        $this->assertSame(ReservationStatus::CheckedOut->value, $reservation->fresh()->status);
    }
}
