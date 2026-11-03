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
use App\Services\WorkOrderWorkflowService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DynamicWorkOrderApprovalFlowTest extends TestCase
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

    public function test_dynamic_work_order_runs_full_approval_and_maintenance_sequence(): void
    {
        $d = $this->setupFlow();
        $workflow = app(WorkOrderWorkflowService::class);
        $maintenance = app(TicketMaintenanceService::class);
        $ticket = $d['ticket'];
        $this->assertSame(2, $ticket->workOrderApprovals()->count());

        try {
            $maintenance->approve($ticket, $d['manager'], $d['technician']->id);
            $this->fail('Technician assignment bypassed mandatory approval');
        } catch (ValidationException $exception) {
            $this->assertSame('open', $ticket->fresh()->status);
        }

        $first = $ticket->workOrderApprovals()->where('step_key', 'supervisor')->firstOrFail();
        $final = $ticket->workOrderApprovals()->where('step_key', 'manager')->firstOrFail();
        $this->assertFalse($workflow->canDecide($first, $d['other']));
        $this->assertFalse($workflow->canDecide($final, $d['manager']));
        $workflow->decide($first, $d['supervisor'], true, 'Laporan telah diketahui.');
        $maintenance->approve($ticket, $d['manager'], $d['technician']->id, 'high');
        $maintenance->accept($ticket, $d['technician']);
        $maintenance->finish($ticket, $d['technician'], 'Panel plafon rusak dan longgar',
            'Panel plafon diperbaiki dan dikencangkan', [], 'complete');
        $maintenance->verify($ticket, $d['reporter'], true, 'Sudah diperiksa.');
        $this->assertTrue($workflow->canDecide($final->fresh(), $d['manager']));
        $workflow->decide($final->fresh(), $d['manager'], true, 'Pengesahan akhir.');
        $maintenance->close($ticket, $d['manager'], true, 'Layak dipakai setelah pemeriksaan.');
        $this->assertSame('closed', $ticket->fresh()->status);
        $this->assertSame(2, $ticket->workOrderApprovals()->where('status', 'approved')->count());
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id, 'action' => 'work_order_approval_granted',
        ]);
    }

    public function test_revoked_title_cannot_approve_and_admin_reassigns_with_audit(): void
    {
        $d = $this->setupFlow();
        $workflow = app(WorkOrderWorkflowService::class);
        $first = $d['ticket']->workOrderApprovals()->where('step_key', 'supervisor')->firstOrFail();
        $other = $d['other'];
        $this->assertFalse($workflow->canDecide($first, $other));
        $d['reporter']->workOrderAssignments()->firstOrFail()
            ->update(['supervisor_user_id' => $other->id]);
        $this->assertFalse($workflow->canDecide($first->fresh(), $d['supervisor']));
        $admin = User::factory()->create();
        $admin->assignRole(config('role.admin'));
        $workflow->reassign($first->fresh(), $admin, $other, 'Perubahan atasan pelapor karena rotasi pegawai.');
        $this->assertTrue($workflow->canDecide($first->fresh(), $other));
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $d['ticket']->id, 'action' => 'work_order_approver_reassigned',
            'actor_id' => $admin->id,
        ]);
        $d['assignment']->update(['is_active' => false]);
        $final = $d['ticket']->workOrderApprovals()->where('step_key', 'manager')->firstOrFail();
        $this->assertFalse($workflow->canPerform($d['ticket'], $d['manager'], 'close'));
        $this->assertFalse($workflow->canDecide($final, $d['manager']));
    }
}
