<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Public\Widgets\ItemLoanSchedule;
use App\Filament\Public\Widgets\LoanScheduleStats;
use App\Filament\Public\Widgets\RoomLoanSchedule;
use App\Filament\Public\Widgets\VehicleLoanSchedule;
use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G003M006Room;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M019VehicleReservation;
use App\Models\G008M017Vehicle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class PublicLoanScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_data_is_masked_on_the_server_and_signed_in_schedule_keeps_visibility_rules(): void
    {
        Carbon::setTestNow('2026-08-27 10:00:00');

        $unit = G001M001Unit::query()->create(['name' => 'Unit Teknologi Informasi']);
        $activity = G004M008Activity::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'name' => 'Kegiatan internal',
            'description' => 'Presentasi hasil kerja tahunan',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(3),
            'status' => ReservationStatus::Approved->value,
        ]);

        $item = G002M007Item::query()->create(['name' => 'Proyektor Publik']);
        $room = G003M006Room::query()->create(['name' => 'Aula Publik']);
        $vehicle = G008M017Vehicle::query()->create([
            'name' => 'Minibus Publik',
            'license_plate' => 'B 1234 PUB',
        ]);

        $itemReservation = G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g002_m007_item_id' => $item->id,
            'quantity' => 2,
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(3),
            'status' => ReservationStatus::Approved->value,
        ]);
        $laterReservation = G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g002_m007_item_id' => $item->id,
            'quantity' => 1,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => ReservationStatus::Approved->value,
        ]);
        G005M010RoomReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g003_m006_room_id' => $room->id,
            'start_time' => now()->subMinutes(30),
            'end_time' => now()->addHours(2),
            'status' => ReservationStatus::CheckedOut->value,
        ]);
        G005M019VehicleReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g008_m017_vehicle_id' => $vehicle->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
            'status' => ReservationStatus::Approved->value,
        ]);

        $hiddenItem = G002M007Item::query()->create(['name' => 'Barang Masih Menunggu']);
        G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g002_m007_item_id' => $hiddenItem->id,
            'quantity' => 1,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => ReservationStatus::Submitted->value,
        ]);

        $expiredRoom = G003M006Room::query()->create(['name' => 'Ruangan Sudah Selesai']);
        G005M010RoomReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g003_m006_room_id' => $expiredRoom->id,
            'start_time' => now()->subDays(2),
            'end_time' => now()->subDay(),
            'status' => ReservationStatus::Approved->value,
        ]);

        $guest = $this->get('/')->assertOk()
            ->assertSee('disamarkan sebagian')
            ->assertSee('Masuk untuk Melihat Data')
            ->assertSee('fi-wi-stats-overview', false)
            ->assertSee('fi-ta-table', false)
            ->assertSee('Pro•••')->assertSee('Aul•••')->assertSee('Min•••')
            ->assertSee('B 1•••')->assertSee('Uni•••')->assertSee('Keg•••')
            ->assertSee(now()->subHour()->translatedFormat('d M Y, H:i'))->assertSee('2 unit')
            ->assertSee('Sedang Berjalan')->assertSee('Disetujui')
            ->assertDontSee('Proyektor Publik')->assertDontSee('Aula Publik')->assertDontSee('Minibus Publik')
            ->assertDontSee('B 1234 PUB')->assertDontSee('Unit Teknologi Informasi')
            ->assertDontSee('Kegiatan internal')->assertDontSee('Presentasi hasil kerja tahunan')
            ->assertDontSee('Barang Masih Menunggu')->assertDontSee('Ruangan Sudah Selesai')
            ->assertDontSee('Lihat Detail');
        $this->assertStringContainsString('no-store', $guest->headers->get('Cache-Control'));
        $this->assertStringContainsString('Cookie', $guest->headers->get('Vary'));

        Livewire::test(ItemLoanSchedule::class)
            ->assertCanSeeTableRecords([$itemReservation])
            ->assertTableActionHidden('view', $itemReservation)
            ->sortTable('start_time', 'desc')
            ->assertCanSeeTableRecords([$laterReservation, $itemReservation], inOrder: true)
            ->assertSee('Pro•••')
            ->assertDontSee('Proyektor Publik')
            // A crafted search must not act as an oracle for a masked name.
            ->set('tableSearch', 'does-not-match-any-asset')
            ->assertCanSeeTableRecords([$itemReservation]);

        $response = $this->actingAs(User::factory()->create(['is_enabled' => true]))->get('/');

        $response
            ->assertOk()
            ->assertSee('Jadwal Peminjaman')
            ->assertSee('Proyektor Publik')
            ->assertSee('Aula Publik')
            ->assertSee('Minibus Publik')
            ->assertSee('Unit Teknologi Informasi')
            ->assertSee('Sedang Berjalan')
            ->assertSee('Disetujui')
            ->assertSee('Kegiatan internal')
            ->assertSee('Presentasi hasil kerja tahunan')
            ->assertDontSee('Barang Masih Menunggu')
            ->assertDontSee('Ruangan Sudah Selesai');
    }

    public function test_guests_can_mount_native_schedule_widgets_but_cannot_call_raw_record_getters_or_details(): void
    {
        foreach ([ItemLoanSchedule::class, RoomLoanSchedule::class, VehicleLoanSchedule::class, LoanScheduleStats::class] as $widget) {
            Livewire::test($widget)->assertOk();
        }

        foreach ([ItemLoanSchedule::class, RoomLoanSchedule::class, VehicleLoanSchedule::class] as $widget) {
            Livewire::test($widget)->call('getTableRecords')->assertForbidden();
            Livewire::test($widget)->call('getTableRecord', 'some-record')->assertForbidden();
            Livewire::test($widget)->call('mountTableAction', 'view', 'some-record')->assertForbidden();
        }
    }

    public function test_a_previously_authenticated_widget_snapshot_uses_partial_masking_after_logout(): void
    {
        $user = User::factory()->create(['is_enabled' => true]);
        $item = G002M007Item::query()->create(['name' => 'Proyektor Rahasia']);
        $reservation = G005M009ItemReservation::query()->create([
            'g002_m007_item_id' => $item->id,
            'quantity' => 1,
            'start_time' => now(),
            'end_time' => now()->addHour(),
            'status' => ReservationStatus::Approved->value,
        ]);
        $page = $this->actingAs($user)->get('/')->assertOk()->assertSee('Proyektor Rahasia');
        $detail = Livewire::test(ItemLoanSchedule::class)
            ->mountTableAction('view', $reservation)
            ->assertTableActionMounted('view')
            ->assertSee('Proyektor Rahasia');
        preg_match_all('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
        $snapshot = null;
        foreach ($matches[1] as $encoded) {
            $json = html_entity_decode($encoded, ENT_QUOTES);
            if (str_ends_with(json_decode($json, true)['memo']['name'] ?? '', 'item-loan-schedule')) {
                $snapshot = $json;
                break;
            }
        }
        $this->assertNotNull($snapshot);
        auth()->logout();
        $detail->call('$refresh')->assertSee('Pro•••')->assertDontSee('Proyektor Rahasia');
        Livewire::test(ItemLoanSchedule::class)
            ->set('mountedTableActionRecord', $reservation->id)
            ->set('mountedTableActions', ['view'])
            ->assertSee('Pro•••')->assertDontSee('Proyektor Rahasia');
        $update = $this->postJson('/livewire/update', ['_token' => session()->token(), 'components' => [
            ['snapshot' => $snapshot, 'updates' => [], 'calls' => []],
        ]], ['X-Livewire' => 'true'])->assertOk();
        $this->assertStringContainsString('Pro•••', $update->json('components.0.effects.html'));
        $this->assertStringNotContainsString('Proyektor Rahasia', $update->getContent());
        $this->get('/')->assertOk()->assertSee('disamarkan sebagian')->assertDontSee('Proyektor Rahasia');
    }
}
