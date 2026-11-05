<?php

namespace Tests\Feature;

use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\G002M007Item;
use App\Enums\ReservationStatus;
use App\Models\G003M006Room;
use App\Models\OperationalOverride;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderJobTitle;
use App\Models\WorkOrderWorkflow;
use App\Services\HierarchyOverrideService;
use App\Services\LoanRequestService;
use App\Services\TicketMaintenanceService;
use App\Services\TicketService;
use App\Services\WorkOrderWorkflowService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HierarchyOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $this->seed(RoleSeeder::class);
        $unit = G001M001Unit::query()->create(['name' => 'Unit Override']);
        $otherUnit = G001M001Unit::query()->create(['name' => 'Unit Lain']);
        $reporter = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $reporter->assignRole(config('role.sarpras'));
        $facility = User::factory()->create();
        $facility->assignRole(config('role.fasilitas'));
        $admin = User::factory()->create();
        $admin->assignRole(config('role.admin'));
        $technician = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $outsider = User::factory()->create(['g001_m001_unit_id' => $otherUnit->id]);
        $outsider->assignRole(config('role.sarpras'));
        $management = G002M003ItemManagement::query()->create(['name' => 'Elektronik Override']);
        $management->users()->attach($technician);
        $room = G003M006Room::withoutEvents(fn () => G003M006Room::query()->create([
            'name' => 'Ruang Demo Override', 'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'status' => 'tersedia', 'is_borrowable' => true,
        ]));
        $category = TicketCategory::query()->where('slug', 'maintenance_hvac')->firstOrFail();
        $ticket = app(TicketService::class)->open($reporter, [
            'ticket_category_id' => $category->id,
            'title' => 'AC ruang kelas rusak',
            'description' => 'AC tidak dingin dan berdengung keras sejak pagi.',
            'asset_type' => 'room', 'asset_id' => $room->id,
        ]);
        return compact('unit', 'otherUnit', 'reporter', 'facility', 'admin', 'technician', 'outsider',
            'management', 'room', 'ticket');
    }

    public function test_multi_role_technician_cannot_normally_approve_or_close_own_completed_work(): void
    {
        $d = $this->context();
        $maintenance = app(TicketMaintenanceService::class);
        $workflow = app(WorkOrderWorkflowService::class);
        $this->actingAs($d['facility']);
        $maintenance->approve($d['ticket'], $d['facility'], $d['technician']->id);
        $this->actingAs($d['technician']);
        $maintenance->accept($d['ticket'], $d['technician']);
        $maintenance->finish($d['ticket'], $d['technician'], 'Komponen rusak ditemukan.', 'Komponen diperbaiki dan diperiksa.', [], 'complete');
        $this->actingAs($d['reporter']);
        $maintenance->verify($d['ticket'], $d['reporter'], true);
        $d['technician']->assignRole(config('role.fasilitas'));
        $ticket = $d['ticket']->fresh();
        $this->actingAs($d['technician']);
        $this->assertFalse($workflow->canPerform($ticket, $d['technician'], 'close'));
        try {
            $maintenance->close($ticket, $d['technician'], false, 'Pengesahan pekerjaan sendiri.');
            $this->fail('Normal self-signoff must be denied');
        } catch (ValidationException) {
            $this->assertSame('resolved', $ticket->fresh()->status);
            $this->assertNull($ticket->fresh()->maintenance->final_approved_at);
        }
        $title = \App\Models\WorkOrderJobTitle::create(['name' => 'Synthetic dual role approver', 'is_active' => true]);
        \App\Models\WorkOrderAssignment::create(['user_id' => $d['technician']->id,
            'work_order_job_title_id' => $title->id, 'g001_m001_unit_id' => $d['unit']->id, 'is_active' => true]);
        $rule = \App\Models\WorkOrderWorkflow::create(['version' => 1, 'status' => 'published',
            'steps' => [['key' => 'final', 'label' => 'Final', 'checkpoint' => 'final', 'resolver' => 'job_title', 'job_title_id' => $title->id]],
            'created_by' => $d['admin']->id]);
        $approval = \App\Models\WorkOrderApproval::create(['ticket_id' => $ticket->id,
            'work_order_workflow_id' => $rule->id, 'step_key' => 'final', 'label' => 'Final',
            'checkpoint' => 'final', 'resolver' => 'job_title', 'job_title_id' => $title->id,
            'candidate_user_ids' => [$d['technician']->id], 'sequence' => 0, 'status' => 'pending']);
        $this->assertFalse($workflow->canDecide($approval->fresh(), $d['technician']));
        try {
            $workflow->decide($approval, $d['technician'], true, 'Pengesahan sendiri.');
            $this->fail('Normal dynamic self-approval must be denied');
        } catch (ValidationException) {
            $this->assertSame('pending', $approval->fresh()->status);
            $this->assertNull($approval->fresh()->decided_by);
        }
        $workflow->decide($approval->fresh(), $d['technician'], true, 'Pengecualian eksplisit.', null,
            'Manager pengganti sedang tidak tersedia; pengecualian dicatat.');
        $maintenance->close($ticket->fresh(), $d['technician'], false, 'Administratif, aset tetap ditahan.',
            'Manager pengganti tidak tersedia untuk pengesahan administratif.');
        $this->assertSame('closed', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->maintenance->asset_released_at);
        $entries = OperationalOverride::where('entity_id', $ticket->id)->get();
        $this->assertCount(2, $entries);
        $this->assertTrue($entries->every(fn ($entry) => $entry->actor_id === $d['technician']->id
            && $entry->details['self_approval'] === true));
    }

    public function test_maintenance_pdf_distinguishes_assigned_technician_from_actual_override_recorder(): void
    {
        $d = $this->context();
        $this->actingAs($d['facility']);
        $service = app(TicketMaintenanceService::class);
        $service->approve($d['ticket'], $d['facility'], $d['technician']->id);
        $service->accept($d['ticket'], $d['facility'], 'Teknisi bertugas di lokasi lain.');
        $service->finish($d['ticket'], $d['facility'], 'Diagnosis tercatat oleh atasan.',
            'Hasil lapangan dimasukkan oleh atasan.', [], 'complete', 'Teknisi belum dapat mengisi laporan.');
        $ticket = $d['ticket']->fresh();
        $html = view('pdf.tickets.maintenance', compact('ticket'))->render();
        $this->assertStringContainsString('Penerimaan dicatat oleh: <strong>'.e($d['facility']->name).'</strong>', $html);
        $this->assertStringContainsString('Hasil dicatat oleh: <strong>'.e($d['facility']->name).'</strong>', $html);
        $this->assertStringContainsString('<td class="key">Teknisi penanggung jawab</td><td>'.e($d['technician']->name).'</td>', $html);
        $this->assertStringContainsString('AUDIT PENGECUALIAN HIERARKI', $html);
        $this->assertStringContainsString('Teknisi bertugas di lokasi lain.', $html);
        $this->assertStringContainsString('Teknisi belum dapat mengisi laporan.', $html);
        $this->assertStringContainsString(e($d['technician']->name), $html);
        $this->assertSame($d['technician']->id, $ticket->assigned_to);
        $this->assertSame(2, $ticket->operationalOverrides()->count());
    }

    public function test_facility_can_complete_technician_and_reporter_work_with_real_actor_audit(): void
    {
        $d = $this->context();
        $this->actingAs($d['facility']);
        $service = app(TicketMaintenanceService::class);
        $ticket = $d['ticket'];
        $service->approve($ticket, $d['facility'], $d['technician']->id);
        $service->accept($ticket, $d['facility'], 'Teknisi sedang bertugas lapangan.');
        $service->finish($ticket, $d['facility'], 'Komponen AC rusak.', 'Perbaikan dilakukan dan diuji.', [],
            'complete', 'Laporan lapangan tidak dapat diinput teknisi.');
        $service->verify($ticket, $d['facility'], true, 'Kondisi sudah diperiksa.',
            'Pelapor belum dapat melakukan verifikasi.');
        $service->close($ticket, $d['facility'], false, 'Pengesahan administratif tanpa melepas aset.',
            'Atasan perlu menutup administrasi yang tertunda.');

        $this->assertSame('closed', $ticket->fresh()->status);
        $this->assertSame(4, OperationalOverride::query()->where('entity_type', 'ticket')
            ->where('entity_id', $ticket->id)->count());
        $this->assertDatabaseHas('operational_overrides', [
            'entity_id' => $ticket->id,
            'action' => 'finish_work',
            'actor_id' => $d['facility']->id,
            'subject_user_id' => $d['technician']->id,
        ]);
        $this->assertDatabaseHas('operational_overrides', [
            'entity_id' => $ticket->id,
            'action' => 'verify_work',
            'actor_id' => $d['facility']->id,
            'subject_user_id' => $d['reporter']->id,
        ]);
        $this->assertNull($ticket->fresh()->maintenance->asset_released_at);
    }

    public function test_no_lower_role_or_peer_facility_override_and_cannot_mutate_audit(): void
    {
        $d = $this->context();
        $authorization = app(HierarchyOverrideService::class);
        $this->actingAs($d['admin']);
        $this->assertTrue($authorization->can($d['admin'], $d['facility'], $d['unit']->id));
        $this->actingAs($d['facility']);
        $this->assertTrue($authorization->can($d['facility'], $d['reporter'], $d['unit']->id));
        $this->assertFalse($authorization->can($d['admin'], $d['facility'], $d['unit']->id),
            'An actor cannot pass another user identity to an override service.');
        $this->actingAs($d['reporter']);
        $this->assertFalse($authorization->can($d['reporter'], $d['facility'], $d['unit']->id));
        $this->actingAs($d['outsider']);
        $this->assertFalse($authorization->can($d['outsider'], $d['technician'], $d['unit']->id));

        $this->actingAs($d['outsider']);
        try {
            app(TicketMaintenanceService::class)->approve($d['ticket'], $d['outsider'],
                $d['technician']->id, 'normal', null, 'Saya mencoba mengambil alih tugas unit lain.');
            $this->fail('Unauthorized override must be rejected.');
        } catch (ValidationException) {
            $this->assertSame('open', $d['ticket']->fresh()->status);
        }

        $this->actingAs($d['facility']);
        app(TicketMaintenanceService::class)->approve($d['ticket'], $d['facility'],
            $d['technician']->id, 'normal', null, 'Manager sedang tidak bisa menyetujui pekerjaan.');
        $record = OperationalOverride::query()->firstOrFail();
        $this->expectException(ValidationException::class);
        $record->update(['reason' => 'Mencoba menghapus jejak persetujuan.']);
    }

    public function test_facility_overrides_work_order_step_but_original_candidate_is_preserved(): void
    {
        $d = $this->context();
        $supervisor = User::factory()->create(['g001_m001_unit_id' => $d['unit']->id]);
        $title = WorkOrderJobTitle::query()->create(['name' => 'Atasan Unit Override']);
        WorkOrderAssignment::query()->create([
            'user_id' => $d['reporter']->id, 'work_order_job_title_id' => $title->id,
            'g001_m001_unit_id' => $d['unit']->id,
            'supervisor_user_id' => $supervisor->id,
        ]);
        $workflow = WorkOrderWorkflow::query()->create([
            'g001_m001_unit_id' => $d['unit']->id, 'version' => 1, 'status' => 'published',
            'published_at' => now(), 'created_by' => $d['admin']->id,
            'steps' => [[
                'key' => 'supervisor', 'label' => 'Atasan Mengetahui', 'checkpoint' => 'start',
                'resolver' => 'reporter_supervisor',
            ]],
        ]);
        // The existing ticket predates the workflow. Snapshot is intentionally explicit.
        $this->actingAs($d['facility']);
        app(WorkOrderWorkflowService::class)->snapshot($d['ticket']);
        $approval = $d['ticket']->workOrderApprovals()->where('status', 'pending')->firstOrFail();
        $this->assertContains($supervisor->id, $approval->candidate_user_ids);
        $this->assertFalse(app(WorkOrderWorkflowService::class)->canDecide($approval, $d['facility']));
        $this->assertTrue(app(WorkOrderWorkflowService::class)->canOverrideDecide($approval, $d['facility']));
        app(WorkOrderWorkflowService::class)->decide($approval, $d['facility'], true,
            'Atasan unit berhalangan.', null, 'Fasilitas mengambil alih persetujuan atasan unit.');
        $approval->refresh();
        $this->assertSame('approved', $approval->status);
        $this->assertSame($d['facility']->id, $approval->decided_by);
        $this->assertContains($supervisor->id, $approval->candidate_user_ids);
        $this->assertDatabaseHas('operational_overrides', [
            'entity_id' => $d['ticket']->id,
            'action' => 'work_order_decision',
            'subject_user_id' => $supervisor->id,
        ]);
    }

    public function test_facility_can_report_ticket_for_named_sarpras_without_forged_actor(): void
    {
        $d = $this->context();
        $this->actingAs($d['facility']);
        $category = TicketCategory::query()->where('slug', 'maintenance_electrical')->firstOrFail();
        $ticket = app(TicketService::class)->openOnBehalf($d['facility'], $d['reporter'], [
            'ticket_category_id' => $category->id,
            'title' => 'Listrik tidak stabil',
            'description' => 'Listrik ruangan padam saat kegiatan dan perlu pemeriksaan.',
            'location' => 'Gedung D',
        ], 'Sarpras sedang tidak dapat mengakses sistem.');

        $this->assertSame($d['reporter']->id, $ticket->reporter_id);
        $this->assertSame($d['unit']->id, $ticket->g001_m001_unit_id);
        $this->assertDatabaseHas('operational_overrides', [
            'entity_type' => 'ticket', 'entity_id' => $ticket->id,
            'action' => 'open_on_behalf', 'actor_id' => $d['facility']->id,
            'subject_user_id' => $d['reporter']->id,
        ]);

        $this->actingAs($d['outsider']);
        $this->expectException(ValidationException::class);
        app(TicketService::class)->openOnBehalf($d['outsider'], $d['reporter'], [
            'ticket_category_id' => $category->id,
            'description' => 'Akun lain tidak berwenang membuat laporan pada unit ini.',
            'location' => 'Gedung E',
        ], 'Mencoba lapor lintas unit tanpa izin.');
    }

    public function test_facility_can_complete_return_without_forging_borrower_confirmation(): void
    {
        $d = $this->context();
        $room = G003M006Room::withoutEvents(fn () => G003M006Room::query()->create([
            'name' => 'Ruang Pinjam Override',
            'g001_m001_unit_id' => $d['unit']->id,
            'g002_m003_item_management_id' => $d['management']->id,
            'is_borrowable' => true, 'status' => 'tersedia',
        ]));
        $start = now()->addDays(4);
        $this->actingAs($d['reporter']);
        $loans = app(LoanRequestService::class);
        $activity = $loans->submit($d['reporter'], [
            'name' => 'Kegiatan peminjaman ruang', 'description' => 'Uji perawatan dan pengembalian.',
            'start_time' => $start, 'end_time' => $start->copy()->addHours(3),
            'needs' => [['type' => 'room', 'room_id' => $room->id]],
        ]);
        $reservation = $activity->room_reservation()->firstOrFail();
        $this->actingAs($d['facility']);
        $loans->processReservation('room', $reservation->id, ReservationStatus::Approved);
        $loans->processReservation('room', $reservation->id, ReservationStatus::CheckedOut);
        $loans->completeManagedReturn('room', $reservation->id, [
            'is_ok' => true,
            'notes' => 'Ruang telah kembali dan diperiksa secara fisik.',
            'receipt_notes' => 'Petugas pengelola menerima ruang secara langsung.',
        ]);
        $reservation->refresh();
        $this->assertSame(ReservationStatus::ReturnRequested->value, $reservation->status);
        $this->assertNull($reservation->returnReceipt?->borrower_confirmed_at);

        $loans->confirmReturnOverride('room', $reservation->id, $d['facility'],
            'Sarpras belum melakukan konfirmasi meski ruangan telah diperiksa.');
        $reservation->refresh();
        $this->assertSame(ReservationStatus::Returned->value, $reservation->status);
        $this->assertNull($reservation->returnReceipt?->borrower_confirmed_at);
        $this->assertNotNull($reservation->returnReceipt?->completed_at);
        $this->assertDatabaseHas('operational_overrides', [
            'entity_type' => 'loan', 'entity_id' => $activity->id,
            'actor_id' => $d['facility']->id, 'subject_user_id' => $d['reporter']->id,
            'action' => 'return_receipt_override',
        ]);
    }

    public function test_facility_can_create_and_submit_loan_as_named_sarpras_without_account_impersonation(): void
    {
        $d = $this->context();
        $item = G002M007Item::query()->create([
            'name' => 'Proyektor Override',
            'code' => 'OVRD-PROJ-001',
            'g001_m001_unit_id' => $d['unit']->id,
            'g002_m003_item_management_id' => $d['management']->id,
            'g003_m006_room_id' => $d['room']->id,
            'quantity' => '2', 'is_borrowable' => true,
        ]);
        $this->actingAs($d['facility']);
        $start = now()->addDays(8);
        $data = [
            'name' => 'Pengajuan pengganti Sarpras Unit', 'description' => 'Kegiatan unit belum sempat diajukan.',
            'start_time' => $start, 'end_time' => $start->copy()->addHours(2),
            'needs' => [['type' => 'item', 'item_id' => $item->id, 'quantity' => 1]],
        ];
        $service = app(LoanRequestService::class);
        $draft = $service->saveDraftOnBehalf($d['facility'], $d['reporter'], $data,
            'Sarpras belum sempat mencatat kebutuhan.');
        $this->assertSame('draft', $draft->status);
        $this->assertSame($d['reporter']->id, $draft->user_id);
        $submitted = $service->submitDraftOnBehalf($d['facility'], $d['reporter'],
            $draft, 'Fasilitas meneruskan pengajuan Sarpras.');
        $this->assertSame('submitted', $submitted->status);
        $this->assertSame(2, OperationalOverride::query()->where('entity_id', $draft->id)->count());
        $this->assertDatabaseHas('operational_overrides', [
            'entity_id' => $draft->id, 'action' => 'submit_on_behalf',
            'actor_id' => $d['facility']->id, 'subject_user_id' => $d['reporter']->id,
        ]);
        try {
            $service->saveDraftOnBehalf($d['outsider'], $d['reporter'], $data,
                'Mencoba membuat pengajuan unit lain.');
            $this->fail('Cross-unit peer override must be rejected.');
        } catch (ValidationException) {
            $this->assertSame(2, OperationalOverride::query()->where('entity_id', $draft->id)->count());
        }
    }
}
