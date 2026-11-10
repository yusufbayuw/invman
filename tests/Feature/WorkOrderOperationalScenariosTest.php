<?php

namespace Tests\Feature;

use App\Filament\Resources\TicketResource\Pages\ViewTicket;
use App\Models\G001M001Unit;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderJobTitle;
use App\Models\WorkOrderWorkflow;
use App\Services\TicketMaintenanceService;
use App\Services\TicketService;
use App\Services\WorkOrderHandlingService;
use App\Services\WorkOrderMaterialsService;
use App\Services\WorkOrderWorkflowService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class WorkOrderOperationalScenariosTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $this->seed(RoleSeeder::class);
        $unit = G001M001Unit::query()->create(['name' => 'SMA Work Order Operasional']);
        $reporter = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $reporter->assignRole(config('role.sarpras'));
        $manager = User::factory()->create();
        $manager->assignRole(config('role.fasilitas'));
        $inspector = User::factory()->create();
        $inspector->assignRole(config('role.fasilitas'));
        $technician = User::factory()->create();
        $technician->assignRole(config('role.fasilitas'));
        $outsider = User::factory()->create();
        $ticket = app(TicketService::class)->open($reporter, [
            'ticket_category_id' => TicketCategory::query()->where('slug', 'maintenance_building')->firstOrFail()->id,
            'location' => 'Ruang Kelas 3',
            'description' => 'Plafon ruangan retak dan berisiko jatuh ke ruang belajar.',
        ]);
        return compact('unit', 'reporter', 'manager', 'inspector', 'technician', 'outsider', 'ticket');
    }

    private function plan(array $d, string $mode = 'internal'): void
    {
        app(WorkOrderHandlingService::class)->recordPlan($d['ticket'], $d['manager'], 'repair',
            $mode, $mode === 'internal' ? null : 'Servis Bangunan Mandiri',
            $mode === 'internal' ? null : 'Penggantian dan perbaikan panel plafon yang rusak.',
            'Perbaikan diperlukan agar ruangan layak digunakan kembali.');
    }

    private function start(array $d): void
    {
        $m = app(TicketMaintenanceService::class);
        $m->approve($d['ticket'], $d['manager'], $d['technician']->id);
        $m->accept($d['ticket'], $d['technician']);
    }

    private function denies(callable $op): void
    {
        try {
            $op();
            $this->fail('Operasi tidak sah justru diterima.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_no_repair_decision_requires_reporter_acknowledgement_and_does_not_fake_work_history(): void
    {
        $d = $this->context();
        $h = app(WorkOrderHandlingService::class);
        $h->recordPlan($d['ticket'], $d['manager'], 'not_repairable', null, null, null,
            'Plafon tidak aman untuk diperbaiki, harus dibongkar dan diganti.');
        $this->denies(fn () => app(TicketMaintenanceService::class)
            ->approve($d['ticket'], $d['manager'], $d['technician']->id));
        $this->denies(fn () => $h->closeNoRepair($d['ticket'], $d['inspector'], 'Diusulkan dibongkar total.'));
        $this->denies(fn () => $h->acknowledgeNoRepair($d['ticket'], $d['outsider']));
        $h->acknowledgeNoRepair($d['ticket'], $d['reporter']);
        $h->closeNoRepair($d['ticket'], $d['inspector'], 'Keputusan penutupan administratif disahkan.');

        $this->assertSame('closed', $d['ticket']->fresh()->status);
        $this->assertSame('unrepairable', $d['ticket']->fresh()->maintenance->outcome);
        $this->assertNull($d['ticket']->fresh()->maintenance->work_finished_at);
        $this->assertNull($d['ticket']->fresh()->maintenance->verified_at);
        $this->assertNull($d['ticket']->fresh()->maintenance->asset_released_at);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $d['ticket']->id, 'action' => 'work_order_closed_without_repair',
        ]);
    }

    public function test_non_repair_final_approval_only_appears_after_start_and_reporter_acknowledgement(): void
    {
        $this->seed(RoleSeeder::class);
        $unit = G001M001Unit::query()->create(['name' => 'SMP Approval Terpisah']);
        $reporter = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $reporter->assignRole(config('role.sarpras'));
        $manager = User::factory()->create();
        $manager->assignRole(config('role.fasilitas'));
        $closer = User::factory()->create();
        $closer->assignRole(config('role.fasilitas'));

        $startTitle = WorkOrderJobTitle::query()->create(['name' => 'Penilai Kerusakan']);
        $finalTitle = WorkOrderJobTitle::query()->create(['name' => 'Pengesah Akhir']);
        WorkOrderAssignment::query()->create([
            'user_id' => $manager->id, 'work_order_job_title_id' => $startTitle->id,
            'g001_m001_unit_id' => $unit->id,
        ]);
        WorkOrderAssignment::query()->create([
            'user_id' => $closer->id, 'work_order_job_title_id' => $finalTitle->id,
            'g001_m001_unit_id' => $unit->id,
        ]);
        WorkOrderWorkflow::query()->create([
            'g001_m001_unit_id' => $unit->id, 'version' => 1,
            'status' => 'published', 'published_at' => now(), 'created_by' => $manager->id,
            'assign_job_title_id' => $startTitle->id,
            'close_job_title_id' => $finalTitle->id,
            'steps' => [
                ['key' => 'start', 'label' => 'Persetujuan Awal', 'checkpoint' => 'start',
                    'resolver' => 'job_title', 'job_title_id' => $startTitle->id],
                ['key' => 'final', 'label' => 'Pengesahan Tanpa Perbaikan', 'checkpoint' => 'final',
                    'resolver' => 'job_title', 'job_title_id' => $finalTitle->id],
            ],
        ]);
        $ticket = app(TicketService::class)->open($reporter, [
            'ticket_category_id' => TicketCategory::query()->where('slug', 'maintenance_building')->firstOrFail()->id,
            'location' => 'Lantai Dua', 'description' => 'Plafon retak dan tidak ekonomis untuk diperbaiki.',
        ]);
        $h = app(WorkOrderHandlingService::class);
        $w = app(WorkOrderWorkflowService::class);
        $h->recordPlan($ticket, $manager, 'replace', null, null, null,
            'Penggantian panel lebih aman dan ekonomis daripada servis.');
        $final = $w->next($ticket, 'final');
        $this->assertFalse($w->canDecide($final, $closer));
        $h->acknowledgeNoRepair($ticket, $reporter);
        $this->assertFalse($w->canDecide($final, $closer));
        $w->decide($w->next($ticket, 'start'), $manager, true, 'Disetujui untuk penggantian.');
        $this->assertTrue($w->canDecide($final, $closer));
        $this->denies(fn () => $h->closeNoRepair($ticket, $closer, 'Pengesahan akhir masih menunggu.'));
        $w->decide($final, $closer, true, 'Penanganan diganti, bukan pekerjaan perbaikan.');
        $h->closeNoRepair($ticket, $closer, 'Disahkan untuk tindak lanjut penggantian aset.');
        $this->assertSame('closed', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->maintenance->work_finished_at);
    }

    public function test_mixed_stock_and_purchase_qc_must_be_issued_before_resuming_work(): void
    {
        $d = $this->context(); $this->plan($d); $this->start($d);
        $m = app(WorkOrderMaterialsService::class);
        $maintenance = app(TicketMaintenanceService::class);
        $line = $m->addRequirement($d['ticket'], $d['technician'], 'Papan plafon', 'lembar', 10);
        $this->denies(fn () => $m->allocateStock($line, $d['outsider'], 4));
        $m->allocateStock($line, $d['manager'], 4);
        $order = $m->requestPurchase($line, $d['technician'], 6, 'Kekurangan dari gudang utama.');
        $this->denies(fn () => $m->requestPurchase($line, $d['technician'], 1, 'Permintaan ganda tidak sah.'));
        $this->denies(fn () => $m->approvePurchase($order, $d['technician'], true, 'Self approval.'));
        $m->approvePurchase($order, $d['manager'], true, 'Material dibutuhkan pekerjaan.');
        $m->placeOrder($order, $d['manager'], 'Toko Bangunan Raya', 'PO-WO-10001', '750000');

        $receipt = $m->receive($order, $d['manager'], 6, 'SJ-00001');
        $this->denies(fn () => $m->inspect($receipt, $d['manager'], 6, 'QC oleh penerima yang sama.'));
        $m->inspect($receipt, $d['inspector'], 5, 'Satu lembar pecah dan harus diganti.');
        $this->assertSame('1.000', $m->summary($line)['shortage']);

        $maintenance->waiting($d['ticket'], $d['technician'], true, 'Material papan plafon kurang satu.');
        $this->denies(fn () => $maintenance->waiting($d['ticket'], $d['technician'], false, 'Lanjut dulu.'));
        $order2 = $m->requestPurchase($line, $d['technician'], 1, 'Pengganti satu lembar yang ditolak QC.');
        $m->approvePurchase($order2, $d['manager'], true, 'Disetujui sebagai pengganti.');
        $m->placeOrder($order2, $d['manager'], 'Toko Bangunan Raya', 'PO-WO-10002', '125000');
        $receipt2 = $m->receive($order2, $d['manager'], 1, 'SJ-00002');
        $m->inspect($receipt2, $d['inspector'], 1, 'Barang pengganti sesuai spesifikasi.');
        $this->denies(fn () => $m->issue($line, $d['manager'], 11));
        $m->issue($line, $d['manager'], 10);
        $maintenance->waiting($d['ticket'], $d['technician'], false, 'Material sudah QC dan diserahkan.');
        $maintenance->finish($d['ticket'], $d['technician'],
            'Plafon retak akibat kerusakan rangka.', 'Ganti papan plafon dan uji keamanan.',
            [['name' => 'Papan plafon', 'quantity' => 10, 'unit' => 'lembar']], 'complete');

        $this->assertSame('resolved', $d['ticket']->fresh()->status);
        $this->assertSame('10.000', $m->summary($line)['issued']);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $d['ticket']->id, 'action' => 'work_order_material_inspected',
        ]);
    }

    public function test_vendor_and_hybrid_work_require_independent_qc_before_completion(): void
    {
        foreach (['external', 'hybrid'] as $mode) {
            $d = $this->context();
            $this->plan($d, $mode);
            $this->start($d);
            $h = app(WorkOrderHandlingService::class);
            $maintenance = app(TicketMaintenanceService::class);
            $this->denies(fn () => $maintenance->finish($d['ticket'], $d['technician'],
                'Panel rusak.', 'Pekerjaan vendor.', [], 'complete'));
            $h->dispatchVendor($d['ticket'], $d['manager'], 'SERVICE-WO-'.$mode);
            $h->vendorResult($d['ticket'], $d['manager'],
                'Panel plafon diservis dan dinyatakan selesai.', 'Berita Acara #001');
            $this->denies(fn () => $h->inspectVendor($d['ticket'], $d['manager'], true, 'Pemeriksaan oleh petugas yang sama.'));
            $h->inspectVendor($d['ticket'], $d['inspector'], false, 'Masih ada permukaan yang retak.');
            $this->denies(fn () => $maintenance->finish($d['ticket'], $d['technician'],
                'Panel rusak.', 'Hasil belum disetujui.', [], 'complete'));
            $h->vendorResult($d['ticket'], $d['manager'],
                'Vendor telah memperbaiki ulang dan memasang panel baru.', 'Berita Acara #002');
            $h->inspectVendor($d['ticket'], $d['inspector'], true, 'Panel sesuai spesifikasi dan aman.');
            $maintenance->finish($d['ticket'], $d['technician'],
                'Panel lama rusak.', 'Hasil servis diperiksa oleh PIC dan sesuai.', [], 'complete');
            $this->assertSame('resolved', $d['ticket']->fresh()->status);
            $this->assertTrue($d['ticket']->fresh()->handlingPlan->vendor_accepted);
        }
    }

    public function test_legacy_repair_flow_without_handling_plan_or_material_lines_remains_operational(): void
    {
        $d = $this->context(); $this->start($d);
        app(TicketMaintenanceService::class)->waiting($d['ticket'], $d['technician'], true, 'Menunggu material lama.');
        app(TicketMaintenanceService::class)->waiting($d['ticket'], $d['technician'], false, 'Material tersedia.');
        app(TicketMaintenanceService::class)->finish(
            $d['ticket'], $d['technician'], 'Bagian plafon terlepas.',
            'Sambungan plafon diperbaiki dan diperiksa.', [], 'temporary',
        );
        $this->assertSame('resolved', $d['ticket']->fresh()->status);
        $this->assertNull($d['ticket']->handlingPlan);
    }

    public function test_filament_handling_plan_action_records_without_bypassing_workflow(): void
    {
        $d = $this->context();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::actingAs($d['manager'])->test(ViewTicket::class, ['record' => $d['ticket']->id])
            ->assertActionVisible('handlingPlan')
            ->callAction('handlingPlan', [
                'decision' => 'repair', 'execution_mode' => 'hybrid',
                'vendor_name' => 'Servis Plafon Mandiri',
                'vendor_scope' => 'Servis bagian panel dan pemasangan ulang oleh vendor.',
                'reason' => 'Teknisi internal memerlukan bantuan pihak ketiga untuk pemasangan.',
            ])->assertHasNoActionErrors();
        $this->assertDatabaseHas('work_order_handling_plans', [
            'ticket_id' => $d['ticket']->id,
            'execution_mode' => 'hybrid',
            'vendor_name' => 'Servis Plafon Mandiri',
        ]);
    }
}
