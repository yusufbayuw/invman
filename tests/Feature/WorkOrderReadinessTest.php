<?php

namespace Tests\Feature;

use App\Filament\Resources\WorkOrderAssignmentResource\Pages\CreateAssignment;
use App\Filament\Resources\WorkOrderJobTitleResource\Pages\CreateJobTitle;
use App\Filament\Resources\WorkOrderWorkflowResource\Pages\CreateWorkflow;
use App\Filament\Resources\WorkOrderWorkflowResource\Pages\ListWorkflows;
use App\Models\{G001M001Unit, G002M003ItemManagement, G003M006Room, TicketCategory, User, WorkOrderAssignment, WorkOrderJobTitle, WorkOrderWorkflow};
use App\Services\{TicketMaintenanceService, TicketService, WorkOrderWorkflowService};
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class WorkOrderReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_actions_refresh_work_order_state_and_next_action_without_reload(): void
    {
        $d = $this->organization();
        $workflows = app(WorkOrderWorkflowService::class);
        $maintenance = app(TicketMaintenanceService::class);
        $workflows->publish($d['workflow'], $d['admin']);
        $reporter = User::where('username', 'sarpras.sd')->firstOrFail();
        $ticket = $this->report($reporter);
        $head = $reporter->workOrderAssignments()->whereNotNull('supervisor_user_id')->firstOrFail()->supervisor;
        foreach ([$head, $d['staff'], $d['supervisor']] as $actor) {
            $workflows->decide($workflows->next($ticket, 'start'), $actor, true, 'Synthetic browser prerequisite');
        }
        $maintenance->approve($ticket, $d['supervisor'], $d['technician']->id, 'high');
        Livewire::actingAs($d['technician'])
            ->test(\App\Filament\Resources\TicketResource\Pages\ViewTicket::class, ['record' => $ticket->id])
            ->callAction('acceptMaintenance')
            ->assertHasNoActionErrors()
            ->assertSet('record.status', 'in_progress')
            ->assertActionHidden('acceptMaintenance')
            ->assertActionVisible('finishMaintenance')
            ->callAction('finishMaintenance', data: [
                'diagnosis' => 'Synthetic loose connector', 'actions' => 'Repaired and checked safely',
                'materials' => [], 'outcome' => 'complete',
            ])
            ->assertHasNoActionErrors()
            ->assertSet('record.status', 'resolved')
            ->assertActionHidden('finishMaintenance');
        Livewire::actingAs($reporter)
            ->test(\App\Filament\Resources\TicketResource\Pages\ViewTicket::class, ['record' => $ticket->id])
            ->callAction('verifyMaintenance', data: ['accepted' => 'yes', 'notes' => 'Checked physically'])
            ->assertHasNoActionErrors()
            ->assertActionHidden('verifyMaintenance');
        $final = $workflows->next($ticket->fresh(), 'final');
        Livewire::actingAs($d['manager'])
            ->test(\App\Filament\Resources\TicketResource\Pages\ViewTicket::class, ['record' => $ticket->id])
            ->callAction('workOrderApproval', data: ['approval_id' => $final->id, 'decision' => 'approve', 'notes' => 'Approved'])
            ->assertHasNoActionErrors()
            ->assertActionVisible('closeMaintenance')
            ->callAction('closeMaintenance', data: ['safe' => true, 'notes' => 'Safe to use'])
            ->assertHasNoActionErrors()
            ->assertSet('record.status', 'closed')
            ->assertActionHidden('closeMaintenance');
    }
    private function organization(): array
    {
        config(['security.seed_demo_users' => true, 'security.seed_demo_password' => 'Synthetic!Readiness2026']);
        Notification::fake();
        $this->seed(DatabaseSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = fn (string $name) => User::where('username', $name)->firstOrFail();
        return [
            'admin' => $user('admin'), 'staff' => $user('wo.demo.fasilitas.staff'),
            'supervisor' => $user('wo.demo.fasilitas.spv'), 'manager' => $user('wo.demo.fasilitas.manager'),
            'technician' => $user('wo.demo.teknisi.petugas'),
            'workflow' => WorkOrderWorkflow::whereNull('g001_m001_unit_id')->firstOrFail(),
        ];
    }

    private function report(User $reporter, bool $withAsset = true): \App\Models\Ticket
    {
        $data = ['ticket_category_id' => TicketCategory::where('slug', 'maintenance_hvac')->firstOrFail()->id,
            'title' => 'Perbaikan sintetis '.$reporter->username, 'description' => 'Komponen rusak <script>alert("qa-xss")</script>',
            'location' => 'Ruang QA '.$reporter->g001_m001_unit_id, 'reported_urgency' => 'urgent'];
        if ($withAsset) {
            $room = G003M006Room::create(['name' => 'QA '.$reporter->username,
                'g001_m001_unit_id' => $reporter->g001_m001_unit_id,
                'g002_m003_item_management_id' => G002M003ItemManagement::where('name', 'Fasilitas')->firstOrFail()->id,
                'is_borrowable' => true]);
            $data += ['asset_type' => 'room', 'asset_id' => $room->id];
        }
        return app(TicketService::class)->open($reporter, $data);
    }

    public function test_configured_ytb_backend_sequence_completes_for_all_six_units_with_managed_assets(): void
    {
        $d = $this->organization();
        $workflows = app(WorkOrderWorkflowService::class); $maintenance = app(TicketMaintenanceService::class);
        $workflows->publish($d['workflow'], $d['admin']);
        foreach (['daycare', 'sd', 'smp', 'sma', 'tbu', 'adm'] as $slug) {
            $reporter = User::where('username', 'sarpras.'.$slug)->firstOrFail();
            $ticket = $this->report($reporter);
            $head = $reporter->workOrderAssignments()->whereNotNull('supervisor_user_id')->firstOrFail()->supervisor;
            foreach ([$head, $d['staff'], $d['supervisor']] as $actor) {
                $approval = $workflows->next($ticket, 'start');
                $this->assertTrue($workflows->canDecide($approval, $actor));
                $workflows->decide($approval, $actor, true, 'Tahap diperiksa sesuai jabatan.');
                $this->assertNotNull($approval->fresh()->decided_at);
                $this->assertSame($actor->id, $approval->fresh()->decided_by);
            }
            $maintenance->approve($ticket, $d['supervisor'], $d['technician']->id, 'high');
            try {
                $maintenance->close($ticket, $d['technician'], true, 'Mencoba melewati verifikasi.');
                $this->fail('Technician bypassed final approval');
            } catch (ValidationException $exception) {
                $this->assertNotSame('closed', $ticket->fresh()->status);
            }
            $maintenance->accept($ticket, $d['technician']);
            $maintenance->finish($ticket, $d['technician'], 'Komponen longgar dan rusak', 'Komponen diperbaiki dan dikencangkan', [['name' => 'Baut', 'quantity' => 2, 'unit' => 'pcs']], 'complete');
            $maintenance->verify($ticket, $reporter, true, 'Hasil sudah diperiksa.');
            $final = $workflows->next($ticket, 'final');
            $this->assertFalse($workflows->canDecide($final, $d['staff']));
            $workflows->decide($final, $d['manager'], true, 'Pengesahan akhir.');
            $maintenance->close($ticket, $d['manager'], true, 'Layak dipakai.');
            $this->assertSame('closed', $ticket->fresh()->status);
            $this->assertSame(4, $ticket->workOrderApprovals()->where('status', 'approved')->count());
            $this->assertNotNull($ticket->fresh()->maintenance->final_approved_at);
            $ticket->load(['workOrderApprovals.decisionMaker', 'workOrderApprovals.workflow']);
            $html = view('pdf.tickets.maintenance', ['ticket' => $ticket])->render();
            $this->assertStringContainsString('Jejak persetujuan Work Order', $html);
            $this->assertStringContainsString(e($head->name), $html);
            $this->assertStringContainsString('Pengesahan akhir.', $html);
            $this->assertStringNotContainsString('<script>alert("qa-xss")</script>', $html);
            $this->assertStringContainsString('&lt;script&gt;alert(&quot;qa-xss&quot;)&lt;/script&gt;', $html);
            // Each synthetic persona starts with its own session (AuthenticateSession binds password hashes).
            $this->flushSession();
            $response = $this->actingAs($reporter)->get(\App\Filament\Resources\TicketResource::getUrl('view', ['record' => $ticket]));
            $this->assertSame(200, $response->status(), $response->headers->get('Location', ''));
            $response->assertDontSee('<script>alert("qa-xss")</script>', false);
            $this->actingAs($reporter)->get(route('tickets.maintenance.pdf', $ticket))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
    }


    public function test_explicit_dispatch_title_enables_assetless_central_work_without_facility_role(): void
    {
        $d = $this->organization(); $w = app(WorkOrderWorkflowService::class); $m = app(TicketMaintenanceService::class);
        $w->publish($d['workflow'], $d['admin']);
        $reporter = User::where('username', 'sarpras.sma')->firstOrFail(); $ticket = $this->report($reporter, false);
        $this->assertFalse($d['technician']->isFacility());
        $this->assertTrue(\App\Models\TicketTeamMember::qualifiesFor($ticket, $d['technician']));
        $head = $reporter->workOrderAssignments()->whereNotNull('supervisor_user_id')->firstOrFail()->supervisor;
        foreach ([$head, $d['staff'], $d['supervisor']] as $actor) {
            $w->decide($w->next($ticket, 'start'), $actor, true, 'Laporan diperiksa.');
        }
        $m->approve($ticket, $d['supervisor'], $d['technician']->id);
        $m->accept($ticket, $d['technician']);
        $m->finish($ticket, $d['technician'], 'Plafon rusak tanpa aset', 'Plafon diperbaiki dan diuji', [], 'complete');
        $m->verify($ticket, $reporter, true);
        $w->decide($w->next($ticket, 'final'), $d['manager'], true, 'Sesuai hasil pemeriksaan.');
        $m->close($ticket, $d['manager'], true);
        $this->assertSame('closed', $ticket->fresh()->status);
    }

    public function test_dispatch_title_scope_revocation_and_inactive_team_remain_fail_closed(): void
    {
        $d = $this->organization(); $w = app(WorkOrderWorkflowService::class);
        $w->publish($d['workflow'], $d['admin']);
        $reporter = User::where('username', 'sarpras.sma')->firstOrFail(); $ticket = $this->report($reporter, false);
        $technician = $d['technician'];
        $assignment = $technician->workOrderAssignments()->where('work_order_job_title_id', $d['workflow']->dispatch_job_title_id)->firstOrFail();
        $assignment->update(['g001_m001_unit_id' => G001M001Unit::where('name', 'SD')->value('id')]);
        $this->assertFalse(\App\Models\TicketTeamMember::qualifiesFor($ticket, $technician));
        $assignment->update(['g001_m001_unit_id' => $reporter->g001_m001_unit_id]);
        $this->assertTrue(\App\Models\TicketTeamMember::qualifiesFor($ticket, $technician));
        $assignment->update(['is_active' => false]);
        $this->assertFalse(\App\Models\TicketTeamMember::qualifiesFor($ticket, $technician));
        $assignment->update(['is_active' => true]);
        $technician->ticketTeamMember->update(['is_active' => false]);
        $this->assertFalse(\App\Models\TicketTeamMember::qualifiesFor($ticket, $technician));
    }

    public function test_assetless_report_does_not_grant_central_technician_cross_unit_authority(): void
    {
        $d = $this->organization();
        $reporter = User::where('username', 'sarpras.sma')->firstOrFail();
        $ticket = $this->report($reporter, false);
        $this->assertFalse(\App\Models\TicketTeamMember::qualifiesFor($ticket, $d['technician']));
        $this->expectException(ValidationException::class);
        app(TicketMaintenanceService::class)->approve($ticket, $d['supervisor'], $d['technician']->id);
    }

    public function test_rejection_is_fail_closed_and_duplicate_decision_is_not_replayed(): void
    {
        $d = $this->organization(); $service = app(WorkOrderWorkflowService::class);
        $service->publish($d['workflow'], $d['admin']);
        $reporter = User::where('username', 'sarpras.sma')->firstOrFail(); $ticket = $this->report($reporter);
        $first = $service->next($ticket, 'start'); $head = User::findOrFail($first->target_user_id);
        $service->decide($first, $head, false, 'Data laporan perlu diperbaiki.');
        $this->assertFalse($service->canDecide($service->next($ticket, 'start'), $d['staff']));
        try { $service->decide($first->fresh(), $head, true, null); $this->fail('Rejected decision replayed'); }
        catch (ValidationException $exception) { $this->assertSame(1, $ticket->events()->where('action', 'work_order_approval_rejected')->count()); }
        $this->expectException(ValidationException::class);
        app(TicketMaintenanceService::class)->approve($ticket, $d['supervisor'], $d['technician']->id);
    }

    public function test_administrator_can_manage_titles_assignments_and_publish_using_filament_forms(): void
    {
        $d = $this->organization();
        Livewire::actingAs($d['admin'])->test(CreateJobTitle::class)
            ->fillForm(['name' => 'Supervisor Audit', 'description' => 'Jabatan sintetis', 'is_active' => true])
            ->call('create')->assertHasNoFormErrors();
        $title = WorkOrderJobTitle::where('name', 'Supervisor Audit')->firstOrFail();
        Livewire::test(CreateAssignment::class)->fillForm(['user_id' => $d['supervisor']->id,
            'work_order_job_title_id' => $title->id, 'g001_m001_unit_id' => null,
            'supervisor_user_id' => $d['manager']->id, 'is_active' => true])->call('create')->assertHasNoFormErrors();
        $unit = G001M001Unit::where('name', 'SMA')->firstOrFail();
        Livewire::test(CreateWorkflow::class)->fillForm(['g001_m001_unit_id' => $unit->id,
            'assign_job_title_id' => $title->id, 'close_job_title_id' => $title->id,
            'steps' => [['key' => 'approve', 'label' => 'Persetujuan Audit', 'checkpoint' => 'start', 'resolver' => 'job_title', 'job_title_id' => $title->id]]])
            ->call('create')->assertHasNoFormErrors();
        $workflow = WorkOrderWorkflow::where('g001_m001_unit_id', $unit->id)->firstOrFail();
        Livewire::test(ListWorkflows::class)->callTableAction('publish', $workflow)->assertHasNoTableActionErrors();
        $this->assertSame('published', $workflow->fresh()->status);
        $this->get('/admin/work-order-workflows/'.$workflow->id.'/edit')->assertForbidden();
    }

    public function test_job_title_named_administrator_does_not_grant_system_administration(): void
    {
        $d = $this->organization();
        $user = User::factory()->create(); $title = WorkOrderJobTitle::create(['name' => 'Administrator']);
        WorkOrderAssignment::create(['user_id' => $user->id, 'work_order_job_title_id' => $title->id]);
        $this->assertFalse($user->isAdmin());
        $this->actingAs($user)->get('/admin/work-order-workflows')->assertForbidden();
        Livewire::actingAs($user)->test(CreateJobTitle::class)->assertForbidden();
    }
    public function test_new_published_workflow_does_not_rewrite_existing_ticket_snapshot(): void
    {
        $d = $this->organization(); $service = app(WorkOrderWorkflowService::class);
        $service->publish($d['workflow'], $d['admin']);
        $ticket = $this->report(User::where('username', 'sarpras.sd')->firstOrFail());
        $before = $ticket->workOrderApprovals()->get()->toArray();
        $new = WorkOrderWorkflow::create(['version' => 2, 'status' => 'draft', 'created_by' => $d['admin']->id,
            'steps' => [['key' => 'new_step', 'label' => 'Aturan baru', 'checkpoint' => 'start', 'resolver' => 'reporter_supervisor']]]);
        $service->publish($new, $d['admin']);
        $this->assertSame($new->id, $service->current($ticket->g001_m001_unit_id)->id);
        $this->assertSame($before, $ticket->workOrderApprovals()->get()->toArray());
        $this->assertSame($d['workflow']->id, $ticket->workOrderApprovals()->firstOrFail()->work_order_workflow_id);
    }

    public function test_missing_supervisor_is_blocked_until_active_assignment_and_audited_reassignment(): void
    {
        $d = $this->organization(); $service = app(WorkOrderWorkflowService::class);
        $service->publish($d['workflow'], $d['admin']);
        $reporter = User::where('username', 'sarpras.smp')->firstOrFail();
        $assignment = $reporter->workOrderAssignments()->firstOrFail(); $head = $assignment->supervisor;
        $assignment->update(['is_active' => false]);
        $ticket = $this->report($reporter); $first = $service->next($ticket, 'start');
        $this->assertSame([], $first->candidate_user_ids);
        $this->assertFalse($service->canDecide($first, $head));
        $this->assertFalse($service->canDecide($first, $d['admin']));
        $assignment->update(['is_active' => true]);
        $service->reassign($first, $d['admin'], $head, 'Atasan kembali aktif setelah rotasi sintetis.');
        $this->assertTrue($service->canDecide($first->fresh(), $head));
        $this->assertSame(1, $ticket->events()->where('action', 'work_order_approver_reassigned')->count());
    }

    public function test_unit_transfer_revokes_snapshot_candidate_and_multi_role_cannot_skip_sequence(): void
    {
        $d = $this->organization(); $service = app(WorkOrderWorkflowService::class);
        $service->publish($d['workflow'], $d['admin']);
        $ticket = $this->report(User::where('username', 'sarpras.sma')->firstOrFail());
        $first = $service->next($ticket, 'start'); $head = User::findOrFail($first->target_user_id);
        $head->assignRole([config('role.admin'), config('role.fasilitas')]);
        $final = $ticket->workOrderApprovals()->where('checkpoint', 'final')->firstOrFail();
        $this->assertFalse($service->canDecide($final, $head));
        $reporterAssignment = $ticket->reporter->workOrderAssignments()->firstOrFail();
        $reporterAssignment->update(['g001_m001_unit_id' => G001M001Unit::where('name', 'SD')->value('id')]);
        $this->assertFalse($service->canDecide($first, $head));
        // Administrative visibility remains deliberate, but does not confer a decision.
        $this->expectException(ValidationException::class);
        $service->decide($first, $head, true, 'Manipulasi keputusan lintas unit.');
    }

}

