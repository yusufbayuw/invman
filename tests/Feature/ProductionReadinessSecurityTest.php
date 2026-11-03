<?php

namespace Tests\Feature;

use App\Models\{G001M001Unit, TicketCategory, User, WorkOrderAssignment, WorkOrderJobTitle, WorkOrderWorkflow};
use App\Services\{TicketMaintenanceService, TicketService, TicketVisibility, WorkOrderWorkflowService};
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductionReadinessSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function flow(bool $dynamic = true): array
    {
        $this->seed(RoleSeeder::class);
        $unit = G001M001Unit::query()->create(['name' => 'Unit Audit']);
        $reporter = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $reporter->assignRole(config('role.sarpras'));
        $manager = User::factory()->create();
        $manager->assignRole(config('role.fasilitas'));
        $technician = User::factory()->create();
        $technician->assignRole(config('role.fasilitas'));
        $approver = User::factory()->create();
        $title = WorkOrderJobTitle::query()->create(['name' => 'Approver Audit']);
        $assignment = WorkOrderAssignment::query()->create([
            'user_id' => $approver->id, 'work_order_job_title_id' => $title->id,
            'g001_m001_unit_id' => $unit->id,
        ]);
        if ($dynamic) {
            WorkOrderWorkflow::query()->create([
                'g001_m001_unit_id' => $unit->id, 'version' => 1, 'status' => 'published',
                'created_by' => $manager->id, 'assign_job_title_id' => $title->id,
                'close_job_title_id' => $title->id,
                'steps' => [['key' => 'approve', 'label' => 'Persetujuan', 'checkpoint' => 'start',
                    'resolver' => 'job_title', 'job_title_id' => $title->id]],
            ]);
        }
        $ticket = app(TicketService::class)->open($reporter, [
            'ticket_category_id' => TicketCategory::query()->where('slug', 'maintenance_hvac')->firstOrFail()->id,
            'title' => 'Laporan audit keamanan', 'description' => 'Data sintetis terbatas untuk pengujian.',
            'location' => 'Ruang QA',
        ]);
        return compact('unit', 'reporter', 'manager', 'technician', 'approver', 'title', 'assignment', 'ticket');
    }

    public function test_revoked_candidate_loses_ticket_and_private_pdf_access(): void
    {
        $d = $this->flow();
        $access = app(TicketVisibility::class);
        $this->assertTrue($access->canView($d['approver'], $d['ticket']));
        $d['assignment']->update(['is_active' => false]);
        $this->assertFalse($access->canView($d['approver'], $d['ticket']));
        $this->actingAs($d['approver'])->get(route('tickets.maintenance.pdf', $d['ticket']))->assertNotFound();
        $this->assertFalse($d['approver']->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_inactive_job_title_revokes_candidate_visibility(): void
    {
        $d = $this->flow();
        $d['title']->update(['is_active' => false]);
        $this->assertFalse(app(TicketVisibility::class)->canView($d['approver'], $d['ticket']));
        $this->assertFalse($d['approver']->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_title_candidate_visibility_preserves_legacy_ticket_with_null_reporter(): void
    {
        $d = $this->flow();
        $d['ticket']->update(['reporter_id' => null]);
        $this->assertTrue(app(TicketVisibility::class)->canView($d['approver'], $d['ticket']->fresh()));
        $this->assertTrue(app(WorkOrderWorkflowService::class)->canDecide($d['ticket']->workOrderApprovals()->firstOrFail(), $d['approver']));
    }

    public function test_disabled_facility_cannot_assign_legacy_maintenance_via_service(): void
    {
        $d = $this->flow(false);
        $d['manager']->update(['is_enabled' => false]);
        $this->expectException(ValidationException::class);
        app(TicketMaintenanceService::class)->approve($d['ticket'], $d['manager'], $d['technician']->id);
    }

    public function test_disabled_facility_cannot_cancel_legacy_maintenance_via_service(): void
    {
        $d = $this->flow(false);
        $d['manager']->update(['is_enabled' => false]);
        $this->expectException(ValidationException::class);
        app(TicketMaintenanceService::class)->cancel($d['ticket'], $d['manager'], 'Pembatalan pengujian tidak sah.');
    }

    public function test_disabled_reporter_cannot_verify_completed_work_via_service(): void
    {
        $d = $this->flow(false);
        $maintenance = app(TicketMaintenanceService::class);
        $maintenance->approve($d['ticket'], $d['manager'], $d['technician']->id);
        $maintenance->accept($d['ticket'], $d['technician']);
        $maintenance->finish($d['ticket'], $d['technician'], 'Kerusakan komponen', 'Komponen selesai diganti', [], 'complete');
        $d['reporter']->update(['is_enabled' => false]);
        $this->expectException(ValidationException::class);
        $maintenance->verify($d['ticket'], $d['reporter'], true);
    }

    public function test_assignment_authority_is_checked_inside_ticket_transaction(): void
    {
        $d = $this->flow();
        $approval = $d['ticket']->workOrderApprovals()->firstOrFail();
        app(WorkOrderWorkflowService::class)->decide($approval, $d['approver'], true, null);
        $injected = false;
        DB::connection()->beforeExecuting(function (string $sql) use (&$injected, $d): void {
            // Simulate revocation after eligibility was read but before ticket locking.
            if (! $injected && preg_match('/select \\* from ["`]tickets["`]/', $sql)) {
                $injected = true;
                $d['assignment']->update(['is_active' => false]);
            }
        });
        try {
            app(TicketMaintenanceService::class)->approve($d['ticket'], $d['approver'], $d['technician']->id);
            $this->fail('Revoked assignment bypassed the transaction boundary.');
        } catch (ValidationException $exception) {
            $this->assertTrue($injected);
            $this->assertSame('open', $d['ticket']->fresh()->status);
        }
    }

    public function test_reporter_with_facility_role_cannot_approve_own_legacy_report(): void
    {
        $d = $this->flow(false);
        $d['reporter']->assignRole(config('role.fasilitas'));
        $this->expectException(ValidationException::class);
        app(TicketMaintenanceService::class)->approve($d['ticket'], $d['reporter'], $d['technician']->id);
    }

    public function test_approval_only_user_sees_pending_ticket_on_dashboard(): void
    {
        $d = $this->flow();
        \Livewire\Livewire::actingAs($d['approver'])->test(\App\Filament\Widgets\TicketAttentionWidget::class)
            ->assertCanSeeTableRecords([$d['ticket']]);
    }

    public function test_multi_position_hierarchy_cannot_hide_a_cycle_on_second_assignment(): void
    {
        $d = $this->flow(false);
        $second = WorkOrderJobTitle::create(['name' => 'Jabatan Kedua QA']);
        $a = User::factory()->create(); $b = User::factory()->create(); $c = User::factory()->create();
        WorkOrderAssignment::create(['user_id' => $a->id, 'work_order_job_title_id' => $d['title']->id, 'supervisor_user_id' => $b->id]);
        WorkOrderAssignment::create(['user_id' => $a->id, 'work_order_job_title_id' => $second->id, 'supervisor_user_id' => $c->id]);
        WorkOrderAssignment::create(['user_id' => $b->id, 'work_order_job_title_id' => $d['title']->id]);
        $this->expectException(ValidationException::class);
        WorkOrderAssignment::create(['user_id' => $c->id, 'work_order_job_title_id' => $d['title']->id, 'supervisor_user_id' => $a->id]);
    }

    public function test_default_active_assignment_cannot_reference_disabled_user(): void
    {
        $title = WorkOrderJobTitle::create(['name' => 'Jabatan QA']);
        $user = User::factory()->create(['is_enabled' => false]);
        $this->expectException(ValidationException::class);
        WorkOrderAssignment::create(['user_id' => $user->id, 'work_order_job_title_id' => $title->id]);
    }

    public function test_reporter_holding_required_title_and_multiple_roles_cannot_decide_own_snapshot(): void
    {
        $d = $this->flow();
        $d['reporter']->assignRole([config('role.admin'), config('role.fasilitas')]);
        WorkOrderAssignment::create(['user_id' => $d['reporter']->id, 'work_order_job_title_id' => $d['title']->id,
            'g001_m001_unit_id' => $d['unit']->id]);
        $ticket = app(TicketService::class)->open($d['reporter'], $d['ticket']->only('ticket_category_id', 'title', 'description', 'location'));
        $approval = $ticket->workOrderApprovals()->firstOrFail();
        $this->assertContains($d['reporter']->id, $approval->candidate_user_ids);
        $this->assertFalse(app(WorkOrderWorkflowService::class)->canPerform($ticket, $d['reporter'], 'assign'));
        $this->expectException(ValidationException::class);
        app(WorkOrderWorkflowService::class)->decide($approval, $d['reporter'], true, 'Self approval tidak sah.');
    }

    public function test_disabled_approver_cannot_decide_pending_dynamic_step(): void
    {
        $d = $this->flow(); $d['approver']->update(['is_enabled' => false]);
        $this->expectException(ValidationException::class);
        app(WorkOrderWorkflowService::class)->decide($d['ticket']->workOrderApprovals()->firstOrFail(), $d['approver'], true, 'Keputusan akun nonaktif.');
    }

    public function test_all_start_steps_must_precede_any_final_step(): void
    {
        $this->expectException(ValidationException::class);
        app(WorkOrderWorkflowService::class)->validateSteps([
            ['key' => 'start_a', 'label' => 'Awal A', 'checkpoint' => 'start', 'resolver' => 'reporter_supervisor'],
            ['key' => 'final_a', 'label' => 'Akhir A', 'checkpoint' => 'final', 'resolver' => 'reporter_supervisor'],
            ['key' => 'start_b', 'label' => 'Awal B', 'checkpoint' => 'start', 'resolver' => 'reporter_supervisor'],
        ]);
    }
}
