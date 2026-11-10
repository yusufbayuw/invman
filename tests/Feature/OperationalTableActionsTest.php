<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Pages\PeminjamanSaya;
use App\Filament\Resources\G005M009ItemReservationResource\Pages\ListG005M009ItemReservations;
use App\Filament\Resources\TicketResource\Pages\ListTickets;
use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderJobTitle;
use App\Models\WorkOrderWorkflow;
use App\Services\TicketService;
use App\Services\TicketMaintenanceService;
use App\Services\WorkOrderWorkflowService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalTableActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_facility_without_asset_membership_can_open_managed_loans_and_approve_a_reservation(): void
    {
        $this->seed(RoleSeeder::class);
        $unit = G001M001Unit::query()->create(['name' => 'SMA Uji Action']);
        $sarpras = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $sarpras->assignRole(config('role.sarpras'));
        $facility = User::factory()->create();
        $facility->assignRole(config('role.fasilitas'));
        $item = G002M007Item::query()->create([
            'name' => 'Proyektor', 'g001_m001_unit_id' => $unit->id,
            'is_borrowable' => true, 'quantity' => 3, 'available_quantity' => 3,
        ]);
        $activity = G004M008Activity::query()->create([
            'user_id' => $sarpras->id,
            'g001_m001_unit_id' => $unit->id,
            'name' => 'Presentasi Kelas',
            'start_time' => now()->addDay(), 'end_time' => now()->addDay()->addHour(),
            'status' => ReservationStatus::Submitted->value,
        ]);
        $reservation = G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g002_m007_item_id' => $item->id,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
            'status' => ReservationStatus::Submitted->value,
            'quantity' => 1,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($facility);
        $this->assertTrue(PeminjamanSaya::canAccess());
        $this->get(PeminjamanSaya::getUrl())->assertOk();
        Livewire::test(PeminjamanSaya::class)->assertSee('Proyektor');
        Livewire::test(ListG005M009ItemReservations::class)
            ->assertCanSeeTableRecords([$reservation])
            ->assertTableActionVisible('approve', $reservation)
            ->assertTableActionHidden('record_return', $reservation)
            ->callTableAction('approve', $reservation)
            ->assertHasNoTableActionErrors();

        $this->assertSame(ReservationStatus::Approved->value, $reservation->fresh()->status);
        $this->assertSame(ReservationStatus::Approved->value, $activity->fresh()->status);
        $this->assertSame($facility->id, $reservation->fresh()->decision_by);
        $this->assertNotNull($reservation->fresh()->decision_at);

        $this->actingAs($sarpras);
        $this->assertFalse($sarpras->managesReservation($reservation));
        $this->assertTrue(PeminjamanSaya::canAccess());
    }

    public function test_work_order_approver_has_action_on_ticket_list_without_global_facility_role(): void
    {
        $this->seed(RoleSeeder::class);
        $unit = G001M001Unit::query()->create(['name' => 'SMA Work Order']);
        $reporter = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $reporter->assignRole(config('role.sarpras'));
        $approver = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $jobTitle = WorkOrderJobTitle::query()->create(['name' => 'Persetujuan Teknisi']);
        WorkOrderAssignment::query()->create([
            'user_id' => $approver->id, 'work_order_job_title_id' => $jobTitle->id,
            'g001_m001_unit_id' => $unit->id,
        ]);
        WorkOrderWorkflow::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'version' => 1, 'status' => 'published', 'published_at' => now(),
            'created_by' => $approver->id,
            'steps' => [
                ['key' => 'approval', 'label' => 'Persetujuan Awal', 'checkpoint' => 'start',
                    'resolver' => 'job_title', 'job_title_id' => $jobTitle->id],
            ],
        ]);
        $category = TicketCategory::query()->where('slug', 'maintenance_hvac')->firstOrFail();
        $ticket = app(TicketService::class)->open($reporter, [
            'ticket_category_id' => $category->id,
            'location' => 'Lab Komputer', 'description' => 'AC tidak berfungsi saat pembelajaran.',
        ]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($approver);
        $this->assertFalse($approver->isFacility());
        Livewire::test(ListTickets::class)
            ->assertCanSeeTableRecords([$ticket])
            ->assertTableActionVisible('work_order_decision', $ticket)
            ->filterTable('status', 'open')
            ->assertTableActionVisible('work_order_decision', $ticket);
    }

    public function test_facility_can_reject_from_reservation_row_and_preserve_audit_reason(): void
    {
        $this->seed(RoleSeeder::class);
        $unit = G001M001Unit::query()->create(['name' => 'SMP Uji Penolakan']);
        $sarpras = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $sarpras->assignRole(config('role.sarpras'));
        $facility = User::factory()->create();
        $facility->assignRole(config('role.fasilitas'));
        $item = G002M007Item::query()->create([
            'name' => 'Proyektor Cadangan', 'g001_m001_unit_id' => $unit->id,
            'is_borrowable' => true, 'quantity' => 2, 'available_quantity' => 2,
        ]);
        $activity = G004M008Activity::query()->create([
            'user_id' => $sarpras->id, 'g001_m001_unit_id' => $unit->id,
            'name' => 'Pelatihan', 'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => ReservationStatus::Submitted->value,
        ]);
        $reservation = G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g002_m007_item_id' => $item->id,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
            'status' => ReservationStatus::Submitted->value, 'quantity' => 1,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::actingAs($facility)->test(ListG005M009ItemReservations::class)
            ->assertTableActionVisible('reject', $reservation)
            ->callTableAction('reject', $reservation, [
                'rejection_reason' => 'Barang perlu diprioritaskan untuk kegiatan lain.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(ReservationStatus::Rejected->value, $reservation->fresh()->status);
        $this->assertSame(ReservationStatus::Rejected->value, $activity->fresh()->status);
        $this->assertSame('Barang perlu diprioritaskan untuk kegiatan lain.', $reservation->fresh()->rejection_reason);
        $this->assertSame($facility->id, $reservation->fresh()->decision_by);
    }

    public function test_approval_queue_and_table_decisions_follow_sequence_verification_and_live_authority(): void
    {
        $this->seed(RoleSeeder::class);
        $unit = G001M001Unit::query()->create(['name' => 'SMA Uji Persetujuan Berjenjang']);
        $reporter = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $reporter->assignRole(config('role.sarpras'));
        $firstApprover = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $manager = User::factory()->create();
        $manager->assignRole(config('role.fasilitas'));
        $technician = User::factory()->create();
        $technician->assignRole(config('role.fasilitas'));

        $firstTitle = WorkOrderJobTitle::query()->create(['name' => 'Pemeriksa Sarpras']);
        $managerTitle = WorkOrderJobTitle::query()->create(['name' => 'Manajer Sarana']);
        WorkOrderAssignment::query()->create([
            'user_id' => $firstApprover->id, 'work_order_job_title_id' => $firstTitle->id,
            'g001_m001_unit_id' => $unit->id,
        ]);
        $managerAssignment = WorkOrderAssignment::query()->create([
            'user_id' => $manager->id, 'work_order_job_title_id' => $managerTitle->id,
            'g001_m001_unit_id' => $unit->id,
        ]);
        WorkOrderWorkflow::query()->create([
            'g001_m001_unit_id' => $unit->id, 'version' => 1,
            'status' => 'published', 'published_at' => now(), 'created_by' => $manager->id,
            'assign_job_title_id' => $managerTitle->id,
            'close_job_title_id' => $managerTitle->id,
            'steps' => [
                ['key' => 'check', 'label' => 'Pemeriksaan Awal', 'checkpoint' => 'start',
                    'resolver' => 'job_title', 'job_title_id' => $firstTitle->id],
                ['key' => 'manager_start', 'label' => 'Persetujuan Pelaksanaan', 'checkpoint' => 'start',
                    'resolver' => 'job_title', 'job_title_id' => $managerTitle->id],
                ['key' => 'manager_final', 'label' => 'Pengesahan Akhir', 'checkpoint' => 'final',
                    'resolver' => 'job_title', 'job_title_id' => $managerTitle->id],
            ],
        ]);
        $ticket = app(TicketService::class)->open($reporter, [
            'ticket_category_id' => TicketCategory::query()->where('slug', 'maintenance_hvac')->firstOrFail()->id,
            'title' => 'AC Laboratorium', 'location' => 'Gedung Kelas',
            'description' => 'AC mati saat proses pembelajaran berlangsung.',
        ]);
        $first = $ticket->workOrderApprovals()->where('step_key', 'check')->firstOrFail();
        $second = $ticket->workOrderApprovals()->where('step_key', 'manager_start')->firstOrFail();
        $final = $ticket->workOrderApprovals()->where('step_key', 'manager_final')->firstOrFail();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($manager);
        Livewire::test(ListTickets::class)
            ->set('activeTab', 'my_approvals')
            ->assertCanNotSeeTableRecords([$ticket])
            ->assertTableActionHidden('work_order_decision', $ticket);

        $this->actingAs($firstApprover);
        Livewire::test(ListTickets::class)
            ->set('activeTab', 'my_approvals')
            ->assertCanSeeTableRecords([$ticket])
            ->assertTableActionVisible('work_order_decision', $ticket)
            ->callTableAction('work_order_decision', $ticket, [
                'approval_id' => $first->id, 'decision' => 'approve',
                'notes' => 'Disetujui pemeriksa awal.',
            ])
            ->assertHasNoTableActionErrors();
        $this->assertSame('approved', $first->fresh()->status);

        // An active title is necessary even after the approval snapshot was taken.
        $managerAssignment->update(['is_active' => false]);
        $this->actingAs($manager);
        Livewire::test(ListTickets::class)
            ->set('activeTab', 'my_approvals')
            ->assertCanNotSeeTableRecords([$ticket]);
        $managerAssignment->update(['is_active' => true]);

        Livewire::test(ListTickets::class)
            ->set('activeTab', 'my_approvals')
            ->assertCanSeeTableRecords([$ticket])
            ->assertTableActionVisible('work_order_decision', $ticket)
            ->callTableAction('work_order_decision', $ticket, [
                'approval_id' => $second->id, 'decision' => 'approve',
                'notes' => 'Disetujui untuk ditugaskan.',
            ])
            ->assertHasNoTableActionErrors();
        $this->assertSame('approved', $second->fresh()->status);
        $this->assertSame('pending', $final->fresh()->status);

        // The final checkpoint must NOT be shown while the ticket is still open.
        Livewire::test(ListTickets::class)
            ->set('activeTab', 'my_approvals')
            ->assertCanNotSeeTableRecords([$ticket]);

        $maintenance = app(TicketMaintenanceService::class);
        $maintenance->approve($ticket, $manager, $technician->id, 'high');
        // Direct service calls do not update the original in-memory Eloquent model.
        // Livewire row-action assertions must receive the current ticket status.
        $ticket->refresh();
        $this->actingAs($technician);
        Livewire::test(ListTickets::class)
            ->assertTableActionVisible('start_work', $ticket)
            ->callTableAction('start_work', $ticket)
            ->assertHasNoTableActionErrors();
        $this->assertSame('in_progress', $ticket->fresh()->status);
        $maintenance->finish($ticket, $technician, 'Motor AC tidak menyala sejak pagi',
            'Unit AC diperbaiki dan kembali berfungsi', [], 'complete');
        $ticket->refresh();

        // Resolved without requester verification is still not actionable.
        $this->actingAs($manager);
        Livewire::test(ListTickets::class)
            ->set('activeTab', 'my_approvals')
            ->assertCanNotSeeTableRecords([$ticket]);

        $maintenance->verify($ticket, $reporter, true, 'Hasil pekerjaan sudah diperiksa.');
        $ticket->refresh();
        Livewire::test(ListTickets::class)
            ->set('activeTab', 'my_approvals')
            ->assertCanSeeTableRecords([$ticket])
            ->assertTableActionVisible('work_order_decision', $ticket)
            ->callTableAction('work_order_decision', $ticket, [
                'approval_id' => $final->id, 'decision' => 'approve',
                'notes' => 'Pengesahan akhir setelah verifikasi.',
            ])
            ->assertHasNoTableActionErrors();
        $this->assertSame('approved', $final->fresh()->status);
        $this->assertSame(3, $ticket->events()->where('action', 'work_order_approval_granted')->count());

        Livewire::test(ListTickets::class)
            ->set('activeTab', 'my_approvals')
            ->assertCanNotSeeTableRecords([$ticket]);
    }
}
