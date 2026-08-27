<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G003M006Room;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M019VehicleReservation;
use App\Models\G008M017Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PublicLoanScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_only_shows_approved_or_checked_out_current_and_upcoming_reservations(): void
    {
        Carbon::setTestNow('2026-08-27 10:00:00');

        $unit = G001M001Unit::query()->create(['name' => 'Unit Teknologi Informasi']);
        $activity = G004M008Activity::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'name' => 'Kegiatan internal',
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

        G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g002_m007_item_id' => $item->id,
            'quantity' => 2,
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(3),
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

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Jadwal Peminjaman')
            ->assertSee('Proyektor Publik')
            ->assertSee('Aula Publik')
            ->assertSee('Minibus Publik')
            ->assertSee('Unit Teknologi Informasi')
            ->assertSee('Sedang Berjalan')
            ->assertSee('Disetujui')
            ->assertDontSee('Kegiatan internal')
            ->assertDontSee('Barang Masih Menunggu')
            ->assertDontSee('Ruangan Sudah Selesai');
    }
}
