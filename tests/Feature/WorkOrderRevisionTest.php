<?php

namespace Tests\Feature;

use App\Models\G001M001Unit;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderJobTitle;
use App\Models\WorkOrderWorkflow;
use App\Services\TicketMaintenanceService;
use App\Services\TicketService;
use App\Services\WorkOrderRevisionService;
use App\Filament\Resources\TicketResource\Pages\ViewTicket;
use Filament\Facades\Filament;
use Livewire\Livewire;
use App\Services\WorkOrderWorkflowService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkOrderRevisionTest extends TestCase
{
    use RefreshDatabase;

    private function setupFlow(): array
    {
        $this->seed(RoleSeeder::class);
        $unit = G001M001Unit::query()->create(['name' => 'SMA Simulasi WO']);
        $reporter = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $reporter->assignRole(config('role.sarpras'));
        $supervisor = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $manager = User::factory()->create();
        $manager->assignRole(config('role.fasilitas'));
        $technician = User::factory()->create();
        $technician->assignRole(config('role.fasilitas'));
        $other = User::factory()->create();

        $headTitle = WorkOrderJobTitle::query()->create(['name' => 'Kepala Sarpras']);
        $managerTitle = WorkOrderJobTitle::query()->create(['name' => 'Manager Fasilitas']);
        WorkOrderAssignment::query()->create([
            'user_id' => $reporter->id, 'work_order_job_title_id' => $headTitle->id,
            'g001_m001_unit_id' => $unit->id, 'supervisor_user_id' => $supervisor->id,
        ]);
        $assignment = WorkOrderAssignment::query()->create([
            'user_id' => $manager->id, 'work_order_job_title_id' => $managerTitle->id,
            'g001_m001_unit_id' => $unit->id,
        ]);
        $workflow = WorkOrderWorkflow::query()->create([
            'g001_m001_unit_id' => $unit->id, 'version' => 1,
            'status' => 'published', 'published_at' => now(), 'created_by' => $manager->id,
            'assign_job_title_id' => $managerTitle->id,
            'close_job_title_id' => $managerTitle->id,
            'steps' => [
                ['key' => 'supervisor', 'label' => 'Diketahui Atasan', 'checkpoint' => 'start',
                    'resolver' => 'reporter_supervisor'],
                ['key' => 'manager', 'label' => 'Pengesahan Manager', 'checkpoint' => 'final',
                    'resolver' => 'job_title', 'job_title_id' => $managerTitle->id],
            ],
        ]);
        $ticket = app(TicketService::class)->open($reporter, [
            'ticket_category_id' => TicketCategory::query()->where('slug', 'maintenance_hvac')->firstOrFail()->id,
            'title' => 'Perbaikan fasilitas ruang sekolah',
            'description' => 'Plafon ruangan rusak dan perlu ditinjau oleh petugas.',
            'location' => 'Gedung SMA Ruang 101',
        ]);
        return compact('unit', 'reporter', 'supervisor', 'manager', 'technician', 'other',
            'assignment', 'ticket', 'workflow');
    }


    private function rejectStart(array $d): \App\Models\WorkOrderApproval
    {
        $step = $d['ticket']->workOrderApprovals()->where('checkpoint', 'start')->firstOrFail();
        app(WorkOrderWorkflowService::class)->decide($step, $d['supervisor'], false, 'Lengkapi keterangan lokasi kerusakan.');
        return $step->fresh();
    }

    private function corrected(array $d): array
    {
        return $d['ticket']->only(['title', 'description', 'location', 'reported_urgency'])
            + []; // retain the original reporting context
    }

    private function resubmit(array $d, int $id): void
    {
        $report = $this->corrected($d);
        $report['description'] = 'Panel plafon lantai dua rusak, terdapat dua sambungan longgar.';
        app(WorkOrderRevisionService::class)->resubmit($d['ticket'], $d['reporter'], $id,
            'Lokasi dan kondisi kerusakan sudah dilengkapi.', $report);
    }

    private function deny(callable $action): void
    {
        try { $action(); $this->fail('Unauthorized or stale mutation accepted'); }
        catch (ValidationException $exception) { $this->assertNotEmpty($exception->errors()); }
    }

    private function finishAndVerify(array $d): void
    {
        $m = app(TicketMaintenanceService::class);
        $m->finish($d['ticket'], $d['technician'], 'Panel plafon longgar', 'Sambungan diganti dan diuji', [], 'complete');
        $m->verify($d['ticket'], $d['reporter'], true, 'Hasil sudah diperiksa.');
    }

    public function test_rejected_report_resubmits_on_same_ticket_and_preserves_decisions_and_workflow(): void
    {
        $d = $this->setupFlow(); $w = app(WorkOrderWorkflowService::class);
        $old = $this->rejectStart($d);
        $this->resubmit($d, $old->id);
        $ticket = $d['ticket']->fresh();
        $this->assertSame('open', $ticket->status);
        $this->assertSame('rejected', $old->fresh()->status);
        $this->assertNotNull($old->fresh()->superseded_at);
        $this->assertSame('Lengkapi keterangan lokasi kerusakan.', $old->fresh()->notes);
        $this->assertSame(4, $ticket->workOrderApprovalHistory()->count());
        $this->assertSame(2, $ticket->workOrderApprovals()->count());
        $this->assertSame(2, $ticket->workOrderApprovals()->min('revision'));
        $revision = $ticket->workOrderRevisions()->firstOrFail();
        $this->assertSame($d['ticket']->description, $revision->before['description']);
        $this->assertSame($ticket->description, $revision->after['description']);
        $this->assertSame($d['workflow']->id, $w->next($ticket, 'start')->work_order_workflow_id);
        $this->deny(fn () => $w->decide($old, $d['supervisor'], true, 'Stale replay'));
        $this->deny(fn () => $this->resubmit($d, $old->id));
        $this->deny(fn () => app(TicketMaintenanceService::class)->approve($ticket, $d['manager'], $d['technician']->id));
        $w->decide($w->next($ticket, 'start'), $d['supervisor'], true, 'Laporan revisi disetujui.');
        $m = app(TicketMaintenanceService::class);
        $m->approve($ticket, $d['manager'], $d['technician']->id);
        $m->accept($ticket, $d['technician']);
        $this->finishAndVerify($d);
        $w->decide($w->next($ticket, 'final'), $d['manager'], true, 'Pengesahan akhir.');
        $m->close($ticket, $d['manager'], true);
        $this->assertSame('closed', $ticket->fresh()->status);
        $this->assertSame(1, $ticket->events()->where('action', 'work_order_revision_submitted')->count());
        $html = view('pdf.tickets.maintenance', ['ticket' => $ticket->fresh()])->render();
        $this->assertStringContainsString('Revisi 1 · Arsip', $html);
        $this->assertStringContainsString('Lengkapi keterangan lokasi kerusakan.', $html);
        $this->assertStringContainsString('Pengiriman ulang revisi 2', $html);
    }

    public function test_revision_rejects_foreign_actor_disabled_stale_identity_and_protected_payload(): void
    {
        $d = $this->setupFlow(); $old = $this->rejectStart($d); $s = app(WorkOrderRevisionService::class);
        $before = $d['ticket']->fresh()->toArray();
        foreach ([$d['other'], $d['supervisor'], $d['manager'], $d['technician']] as $actor) {
            $this->deny(fn () => $s->resubmit($d['ticket'], $actor, $old->id, 'Mencoba mengganti data.', $this->corrected($d)));
        }
        $this->deny(fn () => $s->resubmit($d['ticket'], $d['reporter'], $old->id, 'Mencoba mengganti data.', $this->corrected($d) + ['status' => 'closed']));
        $this->deny(fn () => $s->resubmit($d['ticket'], $d['reporter'], $old->id, 'Tidak mengubah laporan.', $this->corrected($d)));
        User::whereKey($d['reporter']->id)->update(['is_enabled' => false]);
        $this->deny(fn () => $this->resubmit($d, $old->id));
        $this->assertSame($before, $d['ticket']->fresh()->toArray());
        $this->assertSame(0, $d['ticket']->workOrderRevisions()->count());
    }

    public function test_superseded_pending_id_and_revoked_candidates_cannot_decide_or_reassign(): void
    {
        $d = $this->setupFlow(); $oldFinal = $d['ticket']->workOrderApprovals()->where('checkpoint', 'final')->firstOrFail();
        $rejected = $this->rejectStart($d); $this->resubmit($d, $rejected->id);
        $w = app(WorkOrderWorkflowService::class);
        $admin = User::factory()->create(); $admin->assignRole(config('role.admin'));
        $this->deny(fn () => $w->reassign($oldFinal, $admin, $d['manager'], 'Tidak boleh mengubah tahap arsip.'));
        $d['reporter']->workOrderAssignments()->firstOrFail()->update(['is_active' => false]);
        $pending = $w->next($d['ticket'], 'start');
        $this->deny(fn () => $w->decide($pending, $d['supervisor'], true, 'Tidak berwenang lagi.'));
        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_final_rejection_requires_rework_new_reporter_verification_and_fresh_signoff(): void
    {
        $d = $this->setupFlow(); $w = app(WorkOrderWorkflowService::class); $m = app(TicketMaintenanceService::class);
        $w->decide($w->next($d['ticket'], 'start'), $d['supervisor'], true, 'Laporan sesuai.');
        $m->approve($d['ticket'], $d['manager'], $d['technician']->id);
        $m->accept($d['ticket'], $d['technician']); $this->finishAndVerify($d);
        $old = $w->next($d['ticket'], 'final');
        $w->decide($old, $d['manager'], false, 'Perbaiki sambungan sebelum disahkan.');
        $s = app(WorkOrderRevisionService::class);
        $this->deny(fn () => $s->resubmit($d['ticket'], $d['reporter'], $old->id, 'Pelapor mencoba melewati pengerjaan.'));
        $s->resubmit($d['ticket'], $d['technician'], $old->id, 'Akan mengganti sambungan sesuai catatan manager.');
        $ticket = $d['ticket']->fresh();
        $this->assertSame('in_progress', $ticket->status);
        $this->assertNull($ticket->maintenance->verified_at);
        $this->assertNull($ticket->maintenance->work_finished_at);
        $this->assertNull($ticket->maintenance->asset_released_at);
        $this->assertSame(1, $ticket->workOrderApprovals()->where('checkpoint', 'start')->where('status', 'approved')->count());
        $this->deny(fn () => $m->close($ticket, $d['manager'], true));
        $this->deny(fn () => $w->decide($w->next($ticket, 'final'), $d['manager'], true, 'Belum diverifikasi ulang.'));
        $this->finishAndVerify($d);
        $w->decide($w->next($ticket, 'final'), $d['manager'], true, 'Revisi hasil sudah sesuai.');
        $m->close($ticket, $d['manager'], true);
        $this->assertSame('closed', $ticket->fresh()->status);
        $this->assertSame('rejected', $old->fresh()->status);
        $this->assertSame(1, $ticket->workOrderRevisions()->count());
    }

    public function test_native_filament_revision_action_revalidates_and_submits_corrected_report(): void
    {
        $d = $this->setupFlow(); $old = $this->rejectStart($d);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($d['reporter']);
        Livewire::test(ViewTicket::class, ['record' => $d['ticket']->id])
            ->assertActionVisible('reviseWorkOrder')
            ->callAction('reviseWorkOrder', array_merge($this->corrected($d), [
                'description' => 'Detail baru sesuai penolakan untuk diperiksa kembali.',
                'rejection_id' => $old->id, 'reason' => 'Melengkapi data sesuai hasil pemeriksaan.',
            ]))->assertHasNoActionErrors();
        $this->assertSame(1, $d['ticket']->workOrderRevisions()->count());
    }
}
