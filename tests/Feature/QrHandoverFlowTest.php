<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Pages\PeminjamanSerahTerima;
use App\Filament\Pages\PeminjamanSaya;
use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\G003M006Room;
use App\Models\G004M008Activity;
use App\Models\G005M010RoomReservation;
use App\Models\User;
use App\Services\LoanHandoverQrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QrHandoverFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, User, User, G005M010RoomReservation} */
    private function actorsAndRoom(string $status = 'approved'): array
    {
        $unit = G001M001Unit::query()->create(['name' => 'Unit Serah Terima']);
        Role::query()->firstOrCreate(['name' => config('role.sarpras'), 'guard_name' => 'web']);
        $borrower = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $borrower->assignRole(config('role.sarpras'));
        $manager = User::factory()->create();
        $outsider = User::factory()->create();
        $management = G002M003ItemManagement::query()->create(['name' => 'Pengelola Ruang']);
        $management->users()->attach($manager);

        $room = G003M006Room::query()->create([
            'name' => 'Aula Besar',
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'is_borrowable' => true,
        ]);

        $activity = G004M008Activity::query()->create([
            'user_id' => $borrower->id,
            'g001_m001_unit_id' => $unit->id,
            'name' => 'Pelatihan Guru',
            'description' => 'Kegiatan pelatihan',
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'status' => $status,
        ]);

        $reservation = G005M010RoomReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g003_m006_room_id' => $room->id,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
            'status' => $status,
        ]);

        return [$borrower, $manager, $outsider, $reservation];
    }

    public function test_signed_qr_opens_exact_reservation_for_authorized_manager_and_borrower(): void
    {
        [$borrower, $manager, $outsider, $reservation] = $this->actorsAndRoom();
        $service = app(LoanHandoverQrService::class);
        $url = $service->signedScanUrl('room', $reservation);
        $handover = PeminjamanSerahTerima::getUrl([
            'type' => 'room',
            'reservation' => $reservation->id,
        ]);

        $this->assertStringStartsWith('data:image/png;base64,', $service->pngDataUri('room', $reservation));

        $this->get($url)->assertRedirect();
        $this->actingAs($manager)->get($url)->assertRedirect($handover);
        $this->actingAs($borrower)->get($url)->assertRedirect($handover);
        $this->actingAs($outsider)->get($url)->assertNotFound();
        $this->actingAs($outsider)->get($handover)->assertForbidden();
        $this->assertSame(ReservationStatus::Approved->value, $reservation->fresh()->status);
    }

    public function test_tampered_or_expired_qr_is_rejected(): void
    {
        [$borrower, , , $reservation] = $this->actorsAndRoom();
        $url = app(LoanHandoverQrService::class)->signedScanUrl('room', $reservation);
        $this->actingAs($borrower);
        $this->get(str_replace('/room/', '/vehicle/', $url))->assertForbidden();
        $this->travel(31)->minutes();
        $this->get($url)->assertForbidden();
        $this->travelBack();
    }

    public function test_qr_checkout_then_borrower_return_then_manager_confirmation(): void
    {
        [$borrower, $manager, , $reservation] = $this->actorsAndRoom();
        $q = ['type' => 'room', 'reservation' => $reservation->id];

        Livewire::withQueryParams($q)->actingAs($manager)
            ->test(PeminjamanSerahTerima::class)
            ->assertSee('Aula Besar')
            ->assertSee('Pelatihan Guru')
            ->fillForm(['is_ok' => true, 'notes' => 'Kondisi awal baik'])
            ->call('submitCheckout')
            ->assertHasNoFormErrors();

        $this->assertSame(ReservationStatus::Approved->value, $reservation->fresh()->status);
        $this->assertNotNull($reservation->fresh()->outboundReceipt?->manager_confirmed_at);

        Livewire::withQueryParams($q)->actingAs($borrower)
            ->test(PeminjamanSerahTerima::class)
            ->callAction('confirmCheckout')
            ->assertHasNoActionErrors();

        $this->assertSame(ReservationStatus::CheckedOut->value, $reservation->fresh()->status);
        $this->assertNotNull($reservation->fresh()->outboundReceipt?->borrower_confirmed_at);

        Livewire::withQueryParams($q)->actingAs($borrower)
            ->test(PeminjamanSerahTerima::class)
            ->fillForm(['is_ok' => true, 'notes' => 'Aula bersih'])
            ->call('submitReturn')
            ->assertHasNoFormErrors();

        $this->assertSame(ReservationStatus::ReturnRequested->value, $reservation->fresh()->status);
        $receipt = $reservation->fresh()->returnReceipt;
        $this->assertNotNull($receipt->borrower_confirmed_at);
        $this->assertNull($receipt->manager_confirmed_at);

        Livewire::withQueryParams($q)->actingAs($manager)
            ->test(PeminjamanSerahTerima::class)
            ->assertSee('Konfirmasi Pengembalian')
            ->callAction('confirmReturn')
            ->assertHasNoActionErrors();

        $this->assertSame(ReservationStatus::Returned->value, $reservation->fresh()->status);
        $this->assertNotNull($reservation->fresh()->returnReceipt->completed_at);
        $this->assertDatabaseHas('loan_reservation_checklists', [
            'reservation_type' => 'room', 'reservation_id' => $reservation->id,
            'checked_by' => $borrower->id, 'is_ok' => true,
        ]);
    }

    public function test_manager_records_physical_return_and_borrower_confirms_from_qr(): void
    {
        [$borrower, $manager, , $reservation] = $this->actorsAndRoom(ReservationStatus::CheckedOut->value);
        $q = ['type' => 'room', 'reservation' => $reservation->id];

        Livewire::withQueryParams($q)->actingAs($manager)
            ->test(PeminjamanSerahTerima::class)
            ->fillForm(['is_ok' => true, 'notes' => 'Kunci telah diterima'])
            ->call('submitReturn')->assertHasNoFormErrors();

        $receipt = $reservation->fresh()->returnReceipt;
        $this->assertNotNull($receipt->manager_confirmed_at);
        $this->assertNull($receipt->borrower_confirmed_at);

        Livewire::withQueryParams($q)->actingAs($borrower)
            ->test(PeminjamanSerahTerima::class)
            ->callAction('confirmReturn')->assertHasNoActionErrors();

        $this->assertSame(ReservationStatus::Returned->value, $reservation->fresh()->status);
        $this->assertNotNull($reservation->fresh()->returnReceipt->completed_at);
    }

    public function test_unrelated_user_cannot_mutate_checkout_or_return_by_changing_query(): void
    {
        [$borrower, , $outsider, $reservation] = $this->actorsAndRoom();
        $q = ['type' => 'room', 'reservation' => $reservation->id];

        Livewire::withQueryParams($q)->actingAs($borrower)
            ->test(PeminjamanSerahTerima::class)
            ->assertActionHidden('checkoutFallback')
            ->assertActionHidden('confirmCheckout');
        $this->assertSame(ReservationStatus::Approved->value, $reservation->fresh()->status);

        $this->actingAs($outsider)
            ->get(PeminjamanSerahTerima::getUrl($q))->assertForbidden();

        Livewire::withQueryParams($q)->actingAs($borrower)
            ->test(PeminjamanSerahTerima::class)
            ->call('submitReturn')->assertForbidden();
    }

    public function test_unit_list_exposes_qr_action_for_authorized_reservations(): void
    {
        [$borrower, , , $reservation] = $this->actorsAndRoom();
        $component = Livewire::actingAs($borrower)->test(PeminjamanSaya::class);
        $record = $component->instance()->getTableRecords()->firstWhere('reservation_id', $reservation->id);
        $this->assertNotNull($record);
        $component->assertTableActionVisible('handover_qr', $record);
    }
}
