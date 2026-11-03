<?php

namespace Tests\Feature;

use App\Filament\Resources\TicketResource;
use App\Filament\Resources\TicketResource\Pages\ViewTicket;
use App\Models\G001M001Unit;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderJobTitle;
use App\Models\WorkOrderWorkflow;
use App\Services\TicketMaintenanceService;
use App\Services\TicketService;
use App\Services\TicketVisibility;
use App\Services\WorkOrderWorkflowService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
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

        $headTitle = WorkOrderJobTitle::query()->firstOrCreate(['name' => 'Kepala Sarpras']);
        $managerTitle = WorkOrderJobTitle::query()->firstOrCreate(['name' => 'Manager Fasilitas']);
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

    public function test_approver_only_decision_redirects_when_the_pending_scope_ends(): void
    {
        foreach ([true, false] as $approve) {
            $d = $this->setupFlow();
            Filament::setCurrentPanel(Filament::getPanel('admin'));
            $this->flushSession();
            $this->actingAs($d['supervisor']);
            $step = $d['ticket']->workOrderApprovals()->where('checkpoint', 'start')->firstOrFail();
            Livewire::test(ViewTicket::class, ['record' => $d['ticket']->id])
                ->call('mountAction', 'workOrderApproval')
                ->set('mountedActionsData.0.decision', $approve ? 'approve' : 'reject')
                ->set('mountedActionsData.0.notes', 'Browser regression: scope ends after the decision.')
                ->call('callMountedAction')
                ->assertStatus(200)
                ->assertHasNoActionErrors()
                ->assertRedirect(TicketResource::getUrl('index'));
            $this->assertSame($approve ? 'approved' : 'rejected', $step->fresh()->status);
            $this->assertFalse(app(TicketVisibility::class)->canView($d['supervisor'], $d['ticket']));
            $this->assertContains($this->get(TicketResource::getUrl('view', ['record' => $d['ticket']]))->status(), [403, 404]);
            $this->assertSame(1, $d['ticket']->events()->where('action', $approve ? 'work_order_approval_granted' : 'work_order_approval_rejected')->count());
        }
    }

    public function test_native_assignment_modal_does_not_require_an_optional_super_admin_role(): void
    {
        $d = $this->setupFlow();
        $this->assertFalse(Role::where('name', 'super_admin')->exists());
        $step = $d['ticket']->workOrderApprovals()->where('checkpoint', 'start')->firstOrFail();
        app(WorkOrderWorkflowService::class)->decide($step, $d['supervisor'], true, 'Required checkpoint approved.');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($d['manager']);
        Livewire::test(ViewTicket::class, ['record' => $d['ticket']->id])
            ->callAction('approveMaintenance', ['technician' => $d['technician']->id, 'priority' => 'high', 'notes' => 'Native browser assignment regression.'])
            ->assertHasNoActionErrors();
        $this->assertSame('triaged', $d['ticket']->fresh()->status);
        $this->assertSame($d['technician']->id, $d['ticket']->fresh()->assigned_to);
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

    public function test_stale_reassignment_cannot_overwrite_a_new_candidate_or_duplicate_the_event(): void
    {
        $d = $this->setupFlow();
        $w = app(WorkOrderWorkflowService::class);
        $old = $d['ticket']->workOrderApprovals()->where('checkpoint', 'start')->firstOrFail();
        $admin = User::factory()->create();
        $admin->assignRole(config('role.admin'));
        $d['reporter']->workOrderAssignments()->firstOrFail()->update(['supervisor_user_id' => $d['other']->id]);
        $w->reassign($old, $admin, $d['other'], 'Pengganti telah ditetapkan sesuai rotasi.');
        try {
            $w->reassign($old, $admin, $d['other'], 'Modal lama mencoba menimpa pengalihan baru.');
            $this->fail('Stale candidate snapshot accepted');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('approval', $exception->errors());
        }
        $this->assertSame([(int) $d['other']->id], $old->fresh()->candidate_user_ids);
        $this->assertSame(1, $d['ticket']->events()->where('action', 'work_order_approver_reassigned')->count());
    }
}
