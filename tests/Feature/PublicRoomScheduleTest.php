<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\G001M001Unit;
use App\Models\G003M006Room;
use App\Models\G004M008Activity;
use App\Models\G005M010RoomReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicRoomScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_qr_code_is_generated_automatically_with_a_public_schedule_url(): void
    {
        Storage::fake('public');

        $room = G003M006Room::query()->create([
            'name' => 'Ruang Rapat Utama',
            'is_borrowable' => true,
            'status' => 'Tersedia',
        ]);

        $room->refresh();

        $this->assertNotNull($room->qr_uuid);
        $this->assertSame('id', $room->getRouteKeyName());
        $this->assertSame("room-qrcodes/room-{$room->qr_uuid}.png", $room->qrcode);
        Storage::disk('public')->assertExists($room->fresh()->qrcode);
        $this->assertNotEmpty(Storage::disk('public')->get($room->fresh()->qrcode));
        $this->assertStringContainsString($room->qr_uuid, $room->publicScheduleUrl());
        $this->assertNotSame("/ruangan/{$room->id}", parse_url($room->publicScheduleUrl(), PHP_URL_PATH));
    }

    public function test_public_room_page_shows_current_status_and_only_confirmed_current_or_upcoming_bookings(): void
    {
        Storage::fake('public');
        Carbon::setTestNow('2026-08-31 10:00:00');

        $unit = G001M001Unit::query()->create(['name' => 'Unit SD']);
        $pendingUnit = G001M001Unit::query()->create(['name' => 'Unit Rahasia']);
        $room = G003M006Room::query()->create([
            'name' => 'Aula Sekolah',
            'is_borrowable' => true,
            'status' => 'Tersedia',
        ]);
        $activity = G004M008Activity::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'name' => 'Rapat Guru',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(2),
            'status' => ReservationStatus::Approved->value,
        ]);

        G005M010RoomReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g003_m006_room_id' => $room->id,
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(2),
            'status' => ReservationStatus::CheckedOut->value,
        ]);
        $pendingActivity = G004M008Activity::query()->create([
            'g001_m001_unit_id' => $pendingUnit->id,
            'name' => 'Pengajuan Belum Disetujui',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => ReservationStatus::Submitted->value,
        ]);
        G005M010RoomReservation::query()->create([
            'g004_m008_activity_id' => $pendingActivity->id,
            'g003_m006_room_id' => $room->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => ReservationStatus::Submitted->value,
        ]);

        $this->get($room->publicScheduleUrl())
            ->assertOk()
            ->assertSee('Aula Sekolah')
            ->assertSee('Dipakai Unit SD')
            ->assertSee('Nama Unit')
            ->assertSee('Hari &amp; Jam Mulai', false)
            ->assertSee('Senin, 31 Agustus 2026')
            ->assertSee('Unit SD')
            ->assertDontSee('Unit Rahasia');

        $this->get("/ruangan/{$room->id}")->assertNotFound();
    }

    public function test_room_qr_code_can_be_downloaded_as_an_a4_pdf(): void
    {
        Storage::fake('public');

        $room = G003M006Room::query()->create([
            'name' => 'Ruang Rapat Utama',
            'is_borrowable' => true,
            'status' => 'Tersedia',
        ]);

        $response = $this->get($room->qrCodePdfUrl());

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=ruang-rapat-utama-qrcode-a4.pdf');

        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }
}
