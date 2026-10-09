<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Pages\AjukanPeminjaman;
use App\Filament\Pages\PeminjamanCepat;
use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G003M006Room;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\G005M019VehicleReservation;
use App\Models\G008M017Vehicle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuickLoanRequestTest extends TestCase
{
    use RefreshDatabase;

    private function requester(): User
    {
        $unit = G001M001Unit::query()->create(['name' => 'Unit Peminjaman Cepat']);
        $user = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        Role::query()->firstOrCreate(['name' => config('role.sarpras'), 'guard_name' => 'web']);
        $user->assignRole(config('role.sarpras'));

        return $user;
    }

    public function test_borrower_sees_quick_request_as_primary_dashboard_action_and_full_form_remains_available(): void
    {
        $user = $this->requester();

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Pinjam Barang')
            ->assertSee('Pinjam Kendaraan')
            ->assertSee('Pengajuan Lengkap');

        $this->actingAs($user)
            ->get(PeminjamanCepat::getUrl(['type' => 'vehicle']))
            ->assertOk()
            ->assertSee('Peminjaman Cepat')
            ->assertSee('Pengajuan lengkap');

        $this->actingAs($user)
            ->get(AjukanPeminjaman::getUrl())
            ->assertOk()
            ->assertSee('Tambah kebutuhan');
    }

    public function test_one_item_can_be_requested_with_only_one_purpose_and_asset_selection(): void
    {
        $user = $this->requester();
        $item = G002M007Item::query()->create([
            'g001_m001_unit_id' => $user->g001_m001_unit_id,
            'name' => 'Proyektor Cepat',
            'is_borrowable' => true,
            'quantity' => 2,
            'available_quantity' => 2,
        ]);

        Livewire::actingAs($user)
            ->test(PeminjamanCepat::class)
            ->assertSet('data.type', 'item')
            ->fillForm([
                'purpose' => 'Mengajar di ruang kelas',
                'asset_id' => $item->id,
            ])
            ->call('submit')
            ->assertHasNoFormErrors();

        $activity = G004M008Activity::query()->sole();
        $this->assertSame(ReservationStatus::Submitted->value, $activity->status);
        $this->assertSame('Mengajar di ruang kelas', $activity->name);
        $this->assertSame('Mengajar di ruang kelas', $activity->description);
        $this->assertSame($user->id, $activity->user_id);
        $this->assertSame($user->g001_m001_unit_id, $activity->g001_m001_unit_id);
        $this->assertSame($item->id, $activity->item_reservation()->sole()->g002_m007_item_id);
        $this->assertSame(1, $activity->item_reservation()->sole()->quantity);
        $this->assertTrue($activity->hold_expires_at->isFuture());
    }

    public function test_vehicle_request_uses_same_workflow_without_driver_or_assistant_input(): void
    {
        $user = $this->requester();
        $vehicle = G008M017Vehicle::query()->create([
            'g001_m001_unit_id' => $user->g001_m001_unit_id,
            'name' => 'Mobil Operasional',
            'license_plate' => 'D 1234 UX',
            'is_borrowable' => true,
        ]);

        Livewire::withQueryParams(['type' => 'vehicle'])
            ->actingAs($user)
            ->test(PeminjamanCepat::class)
            ->assertSet('data.type', 'vehicle')
            ->fillForm([
                'purpose' => 'Antar peserta lomba',
                'asset_id' => $vehicle->id,
            ])
            ->call('submit')
            ->assertHasNoFormErrors();

        $reservation = G005M019VehicleReservation::query()->sole();
        $this->assertSame($vehicle->id, $reservation->g008_m017_vehicle_id);
        $this->assertNull($reservation->g008_m018_driver_id);
        $this->assertNull($reservation->vehicle_assistant_id);
        $this->assertSame(ReservationStatus::Submitted->value, $reservation->status);
    }

    public function test_room_request_is_available_without_compromising_single_need_flow(): void
    {
        $user = $this->requester();
        $room = G003M006Room::query()->create([
            'g001_m001_unit_id' => $user->g001_m001_unit_id,
            'name' => 'Aula Cepat',
            'is_borrowable' => true,
        ]);

        Livewire::withQueryParams(['type' => 'room'])
            ->actingAs($user)
            ->test(PeminjamanCepat::class)
            ->fillForm([
                'purpose' => 'Briefing wali kelas',
                'asset_id' => $room->id,
            ])
            ->call('submit')
            ->assertHasNoFormErrors();

        $this->assertSame($room->id, G004M008Activity::query()->sole()->room_reservation()->sole()->g003_m006_room_id);
    }

    public function test_unavailable_asset_cannot_be_reserved_twice(): void
    {
        $user = $this->requester();
        $vehicle = G008M017Vehicle::query()->create([
            'name' => 'Mobil Tunggal',
            'is_borrowable' => true,
        ]);

        $start = now()->addDay()->startOfHour()->format('Y-m-d H:i:s');
        $end = now()->addDay()->addHours(2)->startOfHour()->format('Y-m-d H:i:s');

        $first = [
            'type' => 'vehicle',
            'asset_id' => $vehicle->id,
            'purpose' => 'Perjalanan pertama',
            'start_time' => $start,
            'end_time' => $end,
        ];

        Livewire::actingAs($user)
            ->test(PeminjamanCepat::class)
            ->fillForm($first)
            ->call('submit')
            ->assertHasNoFormErrors();

        Livewire::actingAs($user)
            ->test(PeminjamanCepat::class)
            ->fillForm([...$first, 'purpose' => 'Perjalanan kedua'])
            ->call('submit')
            ->assertHasErrors();

        $this->assertSame(1, G005M019VehicleReservation::query()->count());
    }

    public function test_untrusted_form_state_cannot_assign_a_different_user_unit_or_status(): void
    {
        $user = $this->requester();
        $otherUnit = G001M001Unit::query()->create(['name' => 'Unit Palsu']);
        $otherUser = User::factory()->create(['g001_m001_unit_id' => $otherUnit->id]);
        $item = G002M007Item::query()->create([
            'name' => 'Laptop Internal',
            'quantity' => 2,
            'available_quantity' => 2,
            'is_borrowable' => true,
        ]);

        Livewire::actingAs($user)
            ->test(PeminjamanCepat::class)
            ->fillForm([
                'purpose' => 'Rapat unit',
                'asset_id' => $item->id,
            ])
            ->set('data.user_id', $otherUser->id)
            ->set('data.g001_m001_unit_id', $otherUnit->id)
            ->set('data.status', ReservationStatus::Approved->value)
            ->call('submit')
            ->assertHasNoFormErrors();

        $activity = G004M008Activity::query()->sole();
        $this->assertSame($user->id, $activity->user_id);
        $this->assertSame($user->g001_m001_unit_id, $activity->g001_m001_unit_id);
        $this->assertSame(ReservationStatus::Submitted->value, $activity->status);
    }

    public function test_quick_request_blocks_unauthorized_accounts_and_invalid_time(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/peminjaman-cepat')
            ->assertForbidden();

        $borrower = $this->requester();
        Livewire::actingAs($borrower)
            ->test(PeminjamanCepat::class)
            ->fillForm([
                'purpose' => 'Jadwal tidak valid',
                'start_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'end_time' => now()->addDay()->format('Y-m-d H:i:s'),
            ])
            ->call('submit')
            ->assertHasFormErrors(['start_time' => 'before', 'end_time' => 'after']);

        $this->assertSame(0, G004M008Activity::query()->count());
    }
}
