<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Widgets\OperationalPriorityWidget;
use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\G003M006Room;
use App\Models\G004M008Activity;
use App\Models\G005M010RoomReservation;
use App\Models\LoanHandoverReceipt;
use App\Models\User;
use App\Services\LoanOperationalPulseService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalPriorityDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $this->seed(RoleSeeder::class);
        $unitA = G001M001Unit::query()->create(['name' => 'Unit Akademik']);
        $unitB = G001M001Unit::query()->create(['name' => 'Unit Rahasia']);
        $borrower = User::factory()->create(['g001_m001_unit_id' => $unitA->id]);
        $borrower->assignRole(config('role.sarpras'));
        $manager = User::factory()->create();
        $facility = User::factory()->create();
        $facility->assignRole(config('role.fasilitas'));
        $outsider = User::factory()->create();

        $management = G002M003ItemManagement::query()->create(['name' => 'Pengelola Akademik']);
        $management->users()->attach($manager);
        $otherManagement = G002M003ItemManagement::query()->create(['name' => 'Pengelola Rahasia']);

        return [$unitA, $unitB, $borrower, $manager, $facility, $outsider, $management, $otherManagement];
    }

    private function reservation(
        G001M001Unit $unit,
        G002M003ItemManagement $management,
        string $title,
        ReservationStatus $status,
        int $endOffset = 24,
        ?User $borrower = null,
    ): G005M010RoomReservation {
        $activity = G004M008Activity::query()->create([
            'user_id' => $borrower?->id,
            'g001_m001_unit_id' => $unit->id,
            'name' => $title,
            'status' => $status->value,
            'start_time' => now()->subHours(3),
            'end_time' => now()->addHours($endOffset),
        ]);
        $room = G003M006Room::query()->create([
            'name' => $title,
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'is_borrowable' => true,
        ]);

        return G005M010RoomReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g003_m006_room_id' => $room->id,
            'status' => $status->value,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
        ]);
    }

    public function test_unit_sees_all_six_real_time_operational_states_and_overdue_not_hidden(): void
    {
        [$unitA, $unitB, $borrower, $manager, , , $managed, $foreignManaged] = $this->context();

        $this->reservation($unitA, $managed, 'Belum Disetujui', ReservationStatus::Submitted, borrower: $borrower);
        $this->reservation($unitA, $managed, 'Siap Diserahkan', ReservationStatus::Approved, borrower: $borrower);
        $pendingAcceptance = $this->reservation($unitA, $managed, 'Belum Diterima', ReservationStatus::Approved, borrower: $borrower);
        LoanHandoverReceipt::query()->create([
            'receipt_number' => 'OUT-PRIORITY-001',
            'reservation_type' => 'room',
            'reservation_id' => $pendingAcceptance->id,
            'g004_m008_activity_id' => $pendingAcceptance->activity->id,
            'direction' => 'checkout',
            'initiated_by' => $manager->id,
            'manager_confirmed_by' => $manager->id,
            'manager_confirmed_at' => now(),
        ]);

        $this->reservation($unitA, $managed, 'Pinjaman Terlambat Dari Bulan Lalu', ReservationStatus::CheckedOut, -960, $borrower);
        $return = $this->reservation($unitA, $managed, 'Menunggu Pengembalian', ReservationStatus::ReturnRequested, -4, $borrower);
        LoanHandoverReceipt::query()->create([
            'receipt_number' => 'RTN-PRIORITY-001',
            'reservation_type' => 'room',
            'reservation_id' => $return->id,
            'g004_m008_activity_id' => $return->activity->id,
            'direction' => 'return',
            'initiated_by' => $borrower->id,
            'borrower_confirmed_by' => $borrower->id,
            'borrower_confirmed_at' => now(),
        ]);

        $this->reservation($unitB, $foreignManaged, 'ASET RAHASIA ANTAR UNIT', ReservationStatus::CheckedOut, -40);

        $service = app(LoanOperationalPulseService::class);
        $pulse = $service->snapshot($borrower);
        $this->assertSame([
            'approval' => 1,
            'handover' => 1,
            'acceptance' => 1,
            'active' => 1,
            'overdue' => 2,
            'returns' => 1,
        ], $pulse['counts']);
        $this->assertCount(5, $pulse['rows']); // one overdue active and one overdue return are de-duplicated
        $this->assertSame('overdue', $pulse['rows'][0]['category']);
        $this->assertStringNotContainsString('RAHASIA', json_encode($pulse, JSON_THROW_ON_ERROR));

        $acceptance = $service->snapshot($borrower, 'acceptance');
        $this->assertSame('Konfirmasi penerimaan', $acceptance['rows'][0]['action']);

        $approval = $service->snapshot($manager, 'approval');
        $this->assertSame('Tinjau persetujuan', $approval['rows'][0]['action']);

        Livewire::actingAs($borrower)->test(OperationalPriorityWidget::class)
            ->assertSee('Prioritas Operasional V2')
            ->assertSee('Pinjaman Terlambat Dari Bulan Lalu')
            ->assertDontSee('ASET RAHASIA ANTAR UNIT')
            ->call('selectCategory', 'acceptance')
            ->assertSee('Belum Diterima')
            ->assertDontSee('Pinjaman Terlambat Dari Bulan Lalu');
    }

    public function test_facility_can_filter_unit_but_nonfacility_cannot_widen_scope(): void
    {
        [$unitA, $unitB, $borrower, $manager, $facility, $outsider, $managed, $foreignManaged] = $this->context();
        $this->reservation($unitA, $managed, 'Ruang Unit A', ReservationStatus::CheckedOut);
        $this->reservation($unitB, $foreignManaged, 'Ruang Unit B', ReservationStatus::CheckedOut);

        $service = app(LoanOperationalPulseService::class);
        $this->assertSame(2, $service->snapshot($facility)['counts']['active']);
        $this->assertSame(1, $service->snapshot($facility, 'active', $unitA->id)['counts']['active']);
        $this->assertSame(1, $service->snapshot($borrower, 'active', $unitB->id)['counts']['active']);
        $this->assertSame(1, $service->snapshot($manager, 'active', $unitB->id)['counts']['active']);
        $this->assertSame(0, $service->snapshot($outsider)['counts']['active']);
        $this->assertSame([], $service->snapshot($outsider)['rows']);

        Livewire::actingAs($facility)->test(OperationalPriorityWidget::class)
            ->set('filters', ['unit_id' => $unitA->id])
            ->call('selectCategory', 'active')
            ->assertSee('Ruang Unit A')
            ->assertDontSee('Ruang Unit B');
    }

    public function test_missing_second_party_checkout_is_visible_even_when_approval_is_not(): void
    {
        [$unitA, , $borrower, $manager, , , $managed] = $this->context();
        $reservation = $this->reservation($unitA, $managed, 'Butuh Konfirmasi Penerima', ReservationStatus::Approved, borrower: $borrower);
        LoanHandoverReceipt::query()->create([
            'receipt_number' => 'OUT-PRIORITY-002',
            'reservation_type' => 'room',
            'reservation_id' => $reservation->id,
            'g004_m008_activity_id' => $reservation->activity->id,
            'direction' => 'checkout',
            'initiated_by' => $manager->id,
            'manager_confirmed_by' => $manager->id,
            'manager_confirmed_at' => now(),
        ]);

        $service = app(LoanOperationalPulseService::class);
        $managerPulse = $service->snapshot($manager, 'acceptance');
        $borrowerPulse = $service->snapshot($borrower, 'acceptance');
        $this->assertSame(1, $managerPulse['counts']['acceptance']);
        $this->assertSame('Lihat transaksi', $managerPulse['rows'][0]['action']);
        $this->assertSame('Konfirmasi penerimaan', $borrowerPulse['rows'][0]['action']);
        $this->assertSame(0, $service->snapshot($manager)['counts']['handover']);
    }

    public function test_dashboard_registers_new_priority_widget_without_removing_existing_actions(): void
    {
        [, , $borrower] = $this->context();

        $this->actingAs($borrower)->get('/admin')
            ->assertOk()
            ->assertSee('Prioritas Operasional V2')
            ->assertSee('Tindakan Cepat')
            ->assertSee('Ringkasan Operasional');
    }
}
