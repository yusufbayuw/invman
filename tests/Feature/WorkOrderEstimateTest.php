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
use App\Services\WorkOrderEstimateService;
use App\Filament\Resources\TicketResource\Pages\ViewTicket;
use Filament\Facades\Filament;
use Livewire\Livewire;
use App\Services\WorkOrderWorkflowService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkOrderEstimateTest extends TestCase
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
                    'resolver' => 'reporter_supervisor', 'requires_estimate' => true],
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



    private function quote(string $amount = '2500000'): array
    {
        return ['execution_mode' => 'external', 'vendor_name' => 'Vendor QA <script>test</script>',
            'diagnosis' => 'Penggantian komponen oleh vendor', 'amount' => $amount,
            'materials' => [['name' => 'Panel', 'quantity' => 2, 'unit' => 'pcs']]];
    }

    private function deny(callable $callback): void
    {
        try { $callback(); $this->fail('Invalid estimate operation accepted'); }
        catch (ValidationException $exception) { $this->assertNotEmpty($exception->errors()); }
    }

    public function test_budget_approval_binds_exact_immutable_vendor_quote_before_assignment(): void
    {
        $d = $this->setupFlow(); $s = app(WorkOrderEstimateService::class); $w = app(WorkOrderWorkflowService::class);
        $first = $w->next($d['ticket'], 'start');
        $this->deny(fn () => $w->decide($first, $d['supervisor'], true, 'Tidak ada estimasi.'));
        $s->record($d['ticket'], $d['manager'], $this->quote(), 0);
        $estimate = $s->current($d['ticket']);
        $this->assertSame('2500000', $estimate->amount);
        $this->deny(fn () => $s->record($d['ticket'], $d['manager'], $this->quote('5000000'), 0));
        $w->decide($first, $d['supervisor'], true, 'Vendor dan Rp 2.500.000 disetujui.', $estimate->id);
        $this->assertSame($estimate->id, $first->fresh()->estimate_id);
        $this->deny(fn () => $s->record($d['ticket'], $d['manager'], $this->quote('5000000'), 1));
        $this->deny(fn () => $estimate->update(['amount' => '1']));
        app(TicketMaintenanceService::class)->approve($d['ticket'], $d['manager'], $d['technician']->id);
        $html = view('pdf.tickets.maintenance', ['ticket' => $d['ticket']->fresh()])->render();
        $this->assertStringContainsString('Rp 2.500.000', $html);
        $this->assertStringContainsString('Estimasi #'.$estimate->id, $html);
        $this->assertStringNotContainsString('<script>test</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;test&lt;/script&gt;', $html);
    }

    public function test_estimate_cannot_self_approve_and_reject_resubmit_does_not_reuse_prior_quote(): void
    {
        $d = $this->setupFlow(); $s = app(WorkOrderEstimateService::class); $w = app(WorkOrderWorkflowService::class);
        $d['supervisor']->assignRole(config('role.fasilitas'));
        $s->record($d['ticket'], $d['supervisor'], $this->quote(), 0);
        $first = $w->next($d['ticket'], 'start');
        $this->deny(fn () => $w->decide($first, $d['supervisor'], true, 'Pencatat menyetujui sendiri.', $s->current($d['ticket'])->id));
        $w->decide($first, $d['supervisor'], false, 'Perbaiki laporan dan estimasi awal.');
        app(\App\Services\WorkOrderRevisionService::class)->resubmit($d['ticket'], $d['reporter'], $first->id,
            'Detail kerusakan sudah diperbaiki.', array_merge($d['ticket']->only(['title', 'location', 'reported_urgency']), [
                'description' => 'Komponen rusak diperjelas pada lokasi plafon lantai dua.',
            ]));
        $this->assertNull($s->current($d['ticket']));
        $next = $w->next($d['ticket'], 'start');
        $this->deny(fn () => $w->decide($next, $d['supervisor'], true, 'Mencoba memakai estimasi lama.'));
        $s->record($d['ticket'], $d['manager'], $this->quote('2000000'), 1);
        $w->decide($next, $d['supervisor'], true, 'Estimasi baru disetujui.', $s->current($d['ticket'])->id);
        $this->assertSame(2, $d['ticket']->workOrderEstimates()->count());
        $this->assertSame($s->current($d['ticket'])->id, $next->fresh()->estimate_id);
    }

    public function test_estimate_validates_amount_vendor_scope_and_fresh_actor(): void
    {
        $d = $this->setupFlow(); $s = app(WorkOrderEstimateService::class);
        foreach (['-1', '2.50', '1e4', '1000000000000', '10,000'] as $amount) {
            $this->deny(fn () => $s->record($d['ticket'], $d['manager'], $this->quote($amount), 0));
        }
        $this->deny(fn () => $s->record($d['ticket'], $d['manager'], array_merge($this->quote(), ['vendor_name' => '']), 0));
        foreach ([$d['reporter'], $d['other'], $d['supervisor']] as $actor) {
            $this->deny(fn () => $s->record($d['ticket'], $actor, $this->quote(), 0));
        }
        User::whereKey($d['manager']->id)->update(['is_enabled' => false]);
        $this->deny(fn () => $s->record($d['ticket'], $d['manager'], $this->quote(), 0));
        $this->assertSame(0, $d['ticket']->workOrderEstimates()->count());
    }


    public function test_changed_quote_requires_explicit_new_version_and_cannot_be_approved_from_stale_modal(): void
    {
        $d = $this->setupFlow(); $s = app(WorkOrderEstimateService::class); $w = app(WorkOrderWorkflowService::class);
        $s->record($d['ticket'], $d['manager'], $this->quote(), 0);
        $old = $s->current($d['ticket']);
        $s->record($d['ticket'], $d['manager'], $this->quote('5000000'), 1);
        $step = $w->next($d['ticket'], 'start');
        $this->deny(fn () => $w->decide($step, $d['supervisor'], true, 'Menyetujui modal lama.', $old->id));
        $this->assertSame('pending', $step->fresh()->status);
        $w->decide($step, $d['supervisor'], true, 'Menyetujui Rp 5.000.000.', $s->current($d['ticket'])->id);
        $this->assertNotSame($old->id, $step->fresh()->estimate_id);
    }

    public function test_native_filament_estimate_action_records_the_explicit_amount(): void
    {
        $d = $this->setupFlow();
        Filament::setCurrentPanel(Filament::getPanel('admin')); $this->actingAs($d['manager']);
        Livewire::test(ViewTicket::class, ['record' => $d['ticket']->id])
            ->assertActionVisible('recordWorkOrderEstimate')
            ->callAction('recordWorkOrderEstimate', $this->quote() + ['expected_version' => 0])->assertHasNoActionErrors();
        $this->assertSame('2500000', app(WorkOrderEstimateService::class)->current($d['ticket'])->amount);
    }
}
