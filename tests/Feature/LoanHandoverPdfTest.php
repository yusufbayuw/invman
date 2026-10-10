<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Pages\PeminjamanSerahTerima;
use App\Filament\Pages\PeminjamanSaya;
use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use App\Models\G003M006Room;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M016ItemReservationDetail;
use App\Models\G005M019VehicleReservation;
use App\Models\G008M017Vehicle;
use App\Models\G008M018Driver;
use App\Models\LoanCheckoutChecklist;
use App\Models\LoanHandoverReceipt;
use App\Models\LoanReservationChecklist;
use App\Models\User;
use App\Services\LoanHandoverDocumentService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LoanHandoverPdfTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, User, User, G001M001Unit, G002M003ItemManagement, G004M008Activity} */
    private function context(string $status = 'returned'): array
    {
        $this->seed(RoleSeeder::class);
        $unit = G001M001Unit::query()->create(['name' => 'Unit Bioteknologi']);
        $borrower = User::factory()->create(['g001_m001_unit_id' => $unit->id, 'name' => 'Penerima Biotek']);
        $borrower->assignRole(config('role.sarpras'));
        $manager = User::factory()->create(['name' => 'Pengelola Inventaris']);
        $outsider = User::factory()->create(['name' => 'Orang Luar']);
        $management = G002M003ItemManagement::query()->create(['name' => 'Gudang Biotek']);
        $management->users()->attach($manager);
        $activity = G004M008Activity::query()->create([
            'name' => 'Praktikum Pengukuran',
            'description' => 'Serah terima untuk kegiatan pengukuran.',
            'user_id' => $borrower->id,
            'g001_m001_unit_id' => $unit->id,
            'status' => $status,
            'start_time' => now()->subHours(4),
            'end_time' => now()->addHours(2),
        ]);

        return [$borrower, $manager, $outsider, $unit, $management, $activity];
    }

    private function room(string $status = 'returned'): array
    {
        [$borrower, $manager, $outsider, $unit, $management, $activity] = $this->context($status);
        $room = G003M006Room::query()->create([
            'name' => 'Aula Utama Biotek',
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'is_borrowable' => true,
        ]);
        $reservation = G005M010RoomReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g003_m006_room_id' => $room->id,
            'status' => $status,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
        ]);

        return [$borrower, $manager, $outsider, $reservation];
    }

    private function url(string $type, string $id): string
    {
        return route('loans.handover.pdf', ['type' => $type, 'reservation' => $id]);
    }

    public function test_pdf_download_requires_auth_and_specific_reservation_ownership(): void
    {
        [$borrower, $manager, $outsider, $reservation] = $this->room();
        $url = $this->url('room', $reservation->id);

        $this->get($url)->assertRedirect();
        $this->actingAs($outsider)->get($url)->assertNotFound();
        $this->actingAs($borrower)->get($this->url('vehicle', $reservation->id))->assertNotFound();
        $this->actingAs($borrower)->get('/pinjam/bukti/room/not-a-uuid/pdf')->assertNotFound();

        $response = $this->actingAs($borrower)->get($url);
        $response->assertOk();
        $this->assertStringStartsWith('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->actingAs($manager)->get($url)->assertOk();
    }

    public function test_complete_evidence_displays_real_roles_times_condition_and_notes(): void
    {
        [$borrower, $manager, , $reservation] = $this->room();
        LoanHandoverReceipt::query()->create([
            'receipt_number' => 'OUT-TEST-2201', 'reservation_type' => 'room',
            'reservation_id' => $reservation->id,
            'g004_m008_activity_id' => $reservation->activity->id,
            'direction' => 'checkout', 'initiated_by' => $manager->id,
            'manager_confirmed_by' => $manager->id,
            'manager_confirmed_at' => now()->subHours(4),
            'borrower_confirmed_by' => $borrower->id,
            'borrower_confirmed_at' => now()->subHours(3),
            'completed_at' => now()->subHours(3),
            'notes' => 'Kondisi awal aula bersih.',
        ]);
        LoanHandoverReceipt::query()->create([
            'receipt_number' => 'RTN-TEST-2201', 'reservation_type' => 'room',
            'reservation_id' => $reservation->id,
            'g004_m008_activity_id' => $reservation->activity->id,
            'direction' => 'return', 'initiated_by' => $borrower->id,
            'borrower_confirmed_by' => $borrower->id,
            'borrower_confirmed_at' => now()->subMinutes(50),
            'manager_confirmed_by' => $manager->id,
            'manager_confirmed_at' => now()->subMinutes(20),
            'completed_at' => now()->subMinutes(20),
            'notes' => 'Aset dikembalikan.',
        ]);
        LoanCheckoutChecklist::query()->create([
            'reservation_type' => 'room', 'reservation_id' => $reservation->id,
            'g004_m008_activity_id' => $reservation->activity->id,
            'checked_by' => $manager->id, 'is_ok' => true,
            'notes' => 'Seluruh kursi tersedia.', 'checked_at' => now()->subHours(4),
        ]);
        LoanReservationChecklist::query()->create([
            'reservation_type' => 'room', 'reservation_id' => $reservation->id,
            'g004_m008_activity_id' => $reservation->activity->id,
            'checked_by' => $borrower->id, 'is_ok' => false,
            'notes' => 'Satu kursi retak.', 'checked_at' => now()->subMinutes(40),
        ]);

        $data = app(LoanHandoverDocumentService::class)->document('room', $reservation->fresh());
        $this->assertTrue($data['complete']);
        $this->assertFalse($data['hasException']);
        $this->assertSame('Penerima Biotek', $data['checkout']['borrower']);
        $this->assertSame('Pengelola Inventaris', $data['returnEvidence']['manager']);
        $this->assertFalse($data['returnRows'][0]['good']);
        $this->assertSame('Satu kursi retak.', $data['returnRows'][0]['notes']);

        $html = view('pdf.loans.handover-evidence', $data)->render();
        foreach (['RIWAYAT SERAH-TERIMA LENGKAP', 'OUT-TEST-2201', 'RTN-TEST-2201',
            'Penerima Biotek', 'Pengelola Inventaris', 'Seluruh kursi tersedia.',
            'Satu kursi retak.', 'Praktikum Pengukuran'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }

        $this->actingAs($borrower)->get($this->url('room', $reservation->id))
            ->assertOk();
    }

    public function test_incomplete_and_exception_do_not_forge_borrower_confirmation(): void
    {
        [$borrower, $manager, , $reservation] = $this->room(ReservationStatus::CheckedOut->value);
        LoanHandoverReceipt::query()->create([
            'receipt_number' => 'OUT-LEGACY-PDF-1', 'reservation_type' => 'room',
            'reservation_id' => $reservation->id,
            'g004_m008_activity_id' => $reservation->activity->id,
            'direction' => 'checkout', 'initiated_by' => $manager->id,
            'manager_confirmed_by' => $manager->id,
            'manager_confirmed_at' => now()->subHour(),
            'fallback_reason' => 'Peminjam tidak dapat konfirmasi karena kondisi darurat.',
            'completed_at' => now()->subHour(),
        ]);

        $data = app(LoanHandoverDocumentService::class)->document('room', $reservation->fresh());
        $this->assertFalse($data['complete']);
        $this->assertTrue($data['hasException']);
        $this->assertSame('Belum dikonfirmasi', $data['checkout']['borrower']);

        $html = view('pdf.loans.handover-evidence', $data)->render();
        $this->assertStringContainsString('PENYERAHAN KHUSUS', $html);
        $this->assertStringContainsString('Peminjam tidak dapat konfirmasi', $html);
        $this->assertStringContainsString('Belum dikonfirmasi', $html);
        $this->assertStringContainsString('Bukti pengembalian belum tersedia', $html);
        $this->actingAs($borrower)->get($this->url('room', $reservation->id))->assertOk();
    }

    public function test_item_instance_and_vehicle_specific_details_are_in_document(): void
    {
        [$borrower, $manager, , $unit, $management, $activity] = $this->context();
        $item = G002M007Item::query()->create([
            'name' => 'Mikroskop Bersama', 'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'quantity' => 1, 'available_quantity' => 1,
            'is_borrowable' => true,
        ]);
        $instance = G002M015ItemInstance::query()->create([
            'g002_m007_item_id' => $item->id, 'code' => 'MIC-004',
            'name' => 'Mikroskop 004', 'is_available' => true, 'is_borrowable' => true,
        ]);
        $reservation = G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g002_m007_item_id' => $item->id,
            'status' => ReservationStatus::Returned->value, 'quantity' => 1,
            'start_time' => $activity->start_time, 'end_time' => $activity->end_time,
        ]);
        G005M016ItemReservationDetail::query()->create([
            'g005_m009_item_reservation_id' => $reservation->id,
            'g002_m015_item_instance_id' => $instance->id,
        ]);
        LoanCheckoutChecklist::query()->create([
            'reservation_type' => 'item',
            'reservation_id' => $reservation->id,
            'g004_m008_activity_id' => $activity->id,
            'g002_m015_item_instance_id' => $instance->id,
            'checked_by' => $manager->id, 'is_ok' => true,
            'checked_at' => now(),
        ]);

        $data = app(LoanHandoverDocumentService::class)->document('item', $reservation);
        $this->assertSame(['MIC-004'], $data['unitItems']);
        $this->assertSame('MIC-004', $data['checkoutRows'][0]['instance']);
        $this->assertStringContainsString('MIC-004', view('pdf.loans.handover-evidence', $data)->render());

        $driverAccount = User::factory()->create(['name' => 'Sopir Operasional']);
        $driver = G008M018Driver::query()->create(['user_id' => $driverAccount->id]);
        $vehicle = G008M017Vehicle::query()->create([
            'name' => 'Mobil Sekolah',
            'g002_m003_item_management_id' => $management->id,
            'license_plate' => 'D 1234 AB', 'is_borrowable' => true,
        ]);
        $vehicleReservation = G005M019VehicleReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g008_m017_vehicle_id' => $vehicle->id,
            'g008_m018_driver_id' => $driver->id,
            'status' => ReservationStatus::CheckedOut->value,
            'start_time' => $activity->start_time, 'end_time' => $activity->end_time,
        ]);
        LoanHandoverReceipt::query()->create([
            'receipt_number' => 'OUT-MOBIL-001', 'reservation_type' => 'vehicle',
            'reservation_id' => $vehicleReservation->id,
            'g004_m008_activity_id' => $activity->id,
            'direction' => 'checkout',
            'initiated_by' => $manager->id,
            'checkout_odometer' => 18752,
        ]);
        $vehicleData = app(LoanHandoverDocumentService::class)->document('vehicle', $vehicleReservation);
        $html = view('pdf.loans.handover-evidence', $vehicleData)->render();
        $this->assertStringContainsString('D 1234 AB', $html);
        $this->assertStringContainsString('Sopir Operasional', $html);
        $this->assertStringContainsString('18.752', $html);
    }

    public function test_only_local_safe_images_are_inlined_and_notes_are_escaped(): void
    {
        [$borrower, $manager, , $reservation] = $this->room();
        config()->set('filament.default_filesystem_disk', 'local');
        Storage::fake('local');
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==');
        Storage::disk('local')->put('loan-checkout-receipts/evidence.png', $pixel);
        LoanHandoverReceipt::query()->create([
            'receipt_number' => 'OUT-IMAGE-001',
            'reservation_type' => 'room',
            'reservation_id' => $reservation->id,
            'g004_m008_activity_id' => $reservation->activity->id,
            'direction' => 'checkout',
            'initiated_by' => $manager->id,
            'manager_confirmed_by' => $manager->id,
            'manager_confirmed_at' => now(),
            'proof_path' => 'loan-checkout-receipts/evidence.png',
            'notes' => '<script>alert("x")</script>',
        ]);
        $data = app(LoanHandoverDocumentService::class)->document('room', $reservation->fresh());
        $this->assertStringStartsWith('data:image/png;base64,', $data['checkout']['proofImage']);

        $html = view('pdf.loans.handover-evidence', $data)->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);

        $reservation->outboundReceipt->forceFill([
            'proof_path' => 'https://evil.example/test.png',
        ])->save();
        $data = app(LoanHandoverDocumentService::class)->document('room', $reservation->fresh());
        $this->assertNull($data['checkout']['proofImage']);
    }

    public function test_visible_unit_can_find_pdf_action_in_familiar_loan_pages(): void
    {
        [$borrower, , , $reservation] = $this->room(ReservationStatus::CheckedOut->value);
        Livewire::actingAs($borrower)->test(PeminjamanSerahTerima::class, [
            // Filament page takes identifiers from the URL query.
        ])->assertSee('Bukti PDF');

        $component = Livewire::actingAs($borrower)->test(PeminjamanSaya::class);
        $record = $component->instance()->getTableRecords()
            ->firstWhere('reservation_id', $reservation->id);
        $this->assertNotNull($record);
        $component->assertTableActionVisible('download_handover_pdf', $record);
    }
}
