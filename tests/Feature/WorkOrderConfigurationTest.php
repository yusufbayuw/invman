<?php

namespace Tests\Feature;

use App\Models\G001M001Unit;
use App\Models\User;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderJobTitle;
use App\Models\WorkOrderWorkflow;
use App\Services\WorkOrderWorkflowService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkOrderConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_configuration_is_immutable_and_requires_admin(): void
    {
        $this->seed(RoleSeeder::class);
        $title = WorkOrderJobTitle::query()->create(['name' => 'Supervisor Fasilitas']);
        $unit = G001M001Unit::query()->create(['name' => 'SMA']);
        $admin = User::factory()->create();
        $admin->assignRole(config('role.admin'));
        $staff = User::factory()->create();
        $workflow = WorkOrderWorkflow::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'version' => 1, 'status' => 'draft', 'created_by' => $admin->id,
            'steps' => [
                ['key' => 'approve', 'label' => 'Persetujuan', 'checkpoint' => 'start',
                    'resolver' => 'job_title', 'job_title_id' => $title->id],
            ],
        ]);
        WorkOrderAssignment::query()->create([
            'user_id' => $admin->id, 'work_order_job_title_id' => $title->id,
            'g001_m001_unit_id' => $unit->id,
        ]);
        $this->assertSame('draft', $workflow->status);
        try {
            app(WorkOrderWorkflowService::class)->publish($workflow, $staff);
            $this->fail('Unauthorized publisher accepted.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        app(WorkOrderWorkflowService::class)->publish($workflow, $admin);
        $this->assertSame('published', $workflow->fresh()->status);
        $this->expectException(ValidationException::class);
        $workflow->fresh()->update(['steps' => []]);
    }

    public function test_supervisor_cannot_be_own_supervisor(): void
    {
        $title = WorkOrderJobTitle::query()->create(['name' => 'Kepala Sarpras']);
        $user = User::factory()->create();
        $this->expectException(ValidationException::class);
        WorkOrderAssignment::query()->create([
            'user_id' => $user->id,
            'work_order_job_title_id' => $title->id,
            'supervisor_user_id' => $user->id,
        ]);
    }

    public function test_publishing_requires_an_active_approver_assignment(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(config('role.admin'));
        $title = WorkOrderJobTitle::query()->create(['name' => 'SPV Perpustakaan']);
        $workflow = WorkOrderWorkflow::query()->create([
            'version' => 1, 'status' => 'draft', 'created_by' => $admin->id,
            'steps' => [
                ['key' => 'spv', 'label' => 'Approval SPV', 'checkpoint' => 'start',
                 'resolver' => 'job_title', 'job_title_id' => $title->id],
            ],
        ]);
        $this->expectException(ValidationException::class);
        app(WorkOrderWorkflowService::class)->publish($workflow, $admin);
    }

    public function test_indirect_supervisor_cycle_is_rejected(): void
    {
        $title = WorkOrderJobTitle::query()->create(['name' => 'Koordinator Teknisi']);
        $a = User::factory()->create();
        $b = User::factory()->create();
        WorkOrderAssignment::query()->create(['user_id' => $a->id,
            'work_order_job_title_id' => $title->id, 'supervisor_user_id' => $b->id]);
        $assignment = WorkOrderAssignment::query()->create(['user_id' => $b->id,
            'work_order_job_title_id' => $title->id]);
        $this->expectException(ValidationException::class);
        $assignment->update(['supervisor_user_id' => $a->id]);
    }

    public function test_invalid_or_duplicate_workflow_steps_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(WorkOrderWorkflowService::class)->validateSteps([
            ['key' => 'approval', 'label' => 'A', 'checkpoint' => 'start', 'resolver' => 'reporter_supervisor'],
            ['key' => 'approval', 'label' => 'B', 'checkpoint' => 'start', 'resolver' => 'reporter_supervisor'],
        ]);
    }
}
