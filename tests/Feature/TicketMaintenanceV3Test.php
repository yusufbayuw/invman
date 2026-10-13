<?php

namespace Tests\Feature;

use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\G003M006Room;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\LoanAvailabilityService;
use App\Services\TicketAssetSafetyService;
use App\Services\TicketMaintenanceService;
use App\Services\TicketService;
use App\Services\TicketSlaService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TicketMaintenanceV3Test extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $this->seed(RoleSeeder::class);
        $unit = G001M001Unit::query()->create(['name' => 'Kampus Perawatan']);
        $reporter = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $reporter->assignRole(config('role.sarpras'));
        $facility = User::factory()->create();
        $facility->assignRole(config('role.fasilitas'));
        $managerTeam = G002M003ItemManagement::query()->create(['name' => 'Teknisi Gedung']);
        $technician = User::factory()->create();
        $managerTeam->users()->attach($technician);
        $outsider = User::factory()->create();
        $room = G003M006Room::withoutEvents(fn () => G003M006Room::query()->create([
            'name' => 'Ruang Kelas', 'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $managerTeam->id, 'is_borrowable' => true,
            'status' => 'Tersedia',
        ]));
        $category = TicketCategory::query()->where('slug', 'maintenance_hvac')->firstOrFail();
        $ticket = app(TicketService::class)->open($reporter, [
            'ticket_category_id' => $category->id,
            'title' => 'AC kelas bocor',
            'description' => 'Pipa AC menetes ke lantai kelas sejak pagi.',
            'location' => 'Gedung A Lantai Dua',
            'reported_urgency' => 'urgent',
            'asset_type' => 'room', 'asset_id' => $room->id,
        ]);
        return compact('unit', 'reporter', 'facility', 'managerTeam', 'technician', 'outsider', 'room', 'ticket');
    }

    public function test_full_maintenance_signoffs_and_safety_release(): void
    {
        $d = $this->context();
        extract($d);
        $maintenance = app(TicketMaintenanceService::class);
        $availability = app(LoanAvailabilityService::class);
        $from = now()->addDay(); $to = $from->copy()->addHour();

        $this->assertNotNull($ticket->maintenance?->response_due_at);
        $this->assertSame('open', $ticket->status);
        $this->assertTrue(app(TicketAssetSafetyService::class)->isBlocked('room', $room->id));
        $this->assertFalse($availability->roomIsAvailable($room->id, $from, $to));

        try {
            app(TicketService::class)->transition($ticket, $reporter, 'triaged');
            $this->fail('V1 status bypass accepted');
        } catch (ValidationException) {
            $this->assertSame('open', $ticket->fresh()->status);
        }
        try {
            $maintenance->approve($ticket, $reporter, $technician->id, 'high');
            $this->fail('Reporter self-approved');
        } catch (ValidationException) {
            $this->assertNull($ticket->fresh()->maintenance->approved_at);
        }
        $maintenance->approve($ticket, $facility, $technician->id, 'high', 'Tim perawatan ditugaskan.');
        $this->assertSame('triaged', $ticket->fresh()->status);
        $this->assertSame($facility->id, $ticket->fresh()->maintenance->approved_by);
        $maintenance->accept($ticket, $technician);
        $this->assertSame('in_progress', $ticket->fresh()->status);
        $maintenance->waiting($ticket, $technician, true, 'Menunggu pipa baru');
        $this->assertSame('waiting_parts', $ticket->fresh()->status);
        $maintenance->waiting($ticket, $technician, false, 'Pipa baru telah datang');
        $maintenance->finish($ticket, $technician, 'Pipa retak pada sambungan.', 'Ganti pipa dan lakukan uji kebocoran.', [
            ['name' => 'Pipa AC', 'quantity' => 1, 'unit' => 'meter'],
        ], 'complete');
        $this->assertSame('resolved', $ticket->fresh()->status);
        $this->assertFalse($availability->roomIsAvailable($room->id, $from, $to));

        try {
            $maintenance->close($ticket, $facility, true);
            $this->fail('Closed without reporter verification');
        } catch (ValidationException) {
            $this->assertSame('resolved', $ticket->fresh()->status);
        }
        $maintenance->verify($ticket, $reporter, false, 'Masih ada air menetes dari pipa.');
        $this->assertSame('in_progress', $ticket->fresh()->status);
        $maintenance->finish($ticket, $technician, 'Seal sambungan rusak.', 'Segel ulang, tes selama satu jam.', [], 'complete');
        $maintenance->verify($ticket, $reporter, true, 'Sudah kering.');
        $maintenance->close($ticket, $facility, true, 'Sudah dicek dan aman.');
        $this->assertSame('closed', $ticket->fresh()->status);
        $this->assertSame($facility->id, $ticket->fresh()->maintenance->final_approved_by);
        $this->assertNotNull($ticket->fresh()->maintenance->asset_released_at);
        $this->assertFalse(app(TicketAssetSafetyService::class)->isBlocked('room', $room->id));
        $this->assertTrue($availability->roomIsAvailable($room->id, $from, $to));
        $this->assertDatabaseHas('ticket_events', ['ticket_id' => $ticket->id, 'action' => 'maintenance_closed']);
    }

    public function test_temporary_repair_cannot_release_asset_and_role_boundaries_apply(): void
    {
        $d = $this->context();
        $maintenance = app(TicketMaintenanceService::class);
        try {
            $maintenance->approve($d['ticket'], $d['facility'], $d['reporter']->id);
            $this->fail('Reporter assigned as own technician');
        } catch (ValidationException) {}
        $maintenance->approve($d['ticket'], $d['facility'], $d['technician']->id);
        try {
            $maintenance->accept($d['ticket'], $d['outsider']);
            $this->fail('Outsider accepted work');
        } catch (ValidationException) {}
        $maintenance->accept($d['ticket'], $d['technician']);
        $maintenance->finish($d['ticket'], $d['technician'], 'AC bermasalah.', 'Perbaikan sementara.', [], 'temporary');
        $maintenance->verify($d['ticket'], $d['reporter'], true);
        try {
            $maintenance->close($d['ticket'], $d['facility'], true);
            $this->fail('Unsafe repair released inventory');
        } catch (ValidationException) {}
        $maintenance->close($d['ticket'], $d['facility'], false, 'Menunggu penggantian perangkat.');
        $this->assertTrue(app(TicketAssetSafetyService::class)->isBlocked('room', $d['room']->id));
    }

    public function test_protected_pdf_marks_incomplete_and_does_not_leak_to_other_users(): void
    {
        $d = $this->context();
        $url = route('tickets.maintenance.pdf', ['ticket' => $d['ticket']->id]);
        $this->get($url)->assertRedirect();
        $this->actingAs($d['outsider'])->get($url)->assertNotFound();
        $response = $this->actingAs($d['reporter'])->get($url);
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->baseResponse->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }

    public function test_sla_business_calendar_and_escalations_are_idempotent(): void
    {
        $d = $this->context();
        $sla = app(TicketSlaService::class);
        $friday = Carbon::parse('2026-10-16 16:00:00', 'Asia/Jakarta');
        $this->assertSame('2026-10-19 09:00', $sla->addWorkingMinutes($friday, 120)->format('Y-m-d H:i'));

        DB::table('ticket_maintenance_cases')->where('ticket_id', $d['ticket']->id)
            ->update(['response_due_at' => now()->subHour(), 'resolution_due_at' => now()->subHour()]);
        $this->assertSame(2, $sla->escalateOverdue());
        $this->assertSame(0, $sla->escalateOverdue());
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $d['ticket']->id, 'action' => 'sla_response_breached',
        ]);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $d['ticket']->id, 'action' => 'sla_resolution_breached',
        ]);
    }

    public function test_single_item_safety_hold_reduces_available_stock_without_disabling_other_instances(): void
    {
        $d = $this->context();
        $now = now();
        $itemId = DB::table('g002_m007_items')->insertGetId([
            'g001_m001_unit_id' => $d['unit']->id,
            'g002_m003_item_management_id' => $d['managerTeam']->id,
            'name' => 'Proyektor LCD', 'code' => 'PJ',
            'quantity' => '2', 'is_borrowable' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $firstId = DB::table('g002_m015_item_instances')->insertGetId([
            'g002_m007_item_id' => $itemId,
            'g001_m001_unit_id' => $d['unit']->id,
            'name' => 'Proyektor 1', 'code' => 'PJ-001',
            'is_borrowable' => true, 'is_available' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('g002_m015_item_instances')->insert([
            'g002_m007_item_id' => $itemId,
            'g001_m001_unit_id' => $d['unit']->id,
            'name' => 'Proyektor 2', 'code' => 'PJ-002',
            'is_borrowable' => true, 'is_available' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $availability = app(LoanAvailabilityService::class);
        $from = now()->addDays(4); $to = $from->copy()->addHour();
        $this->assertSame(2, $availability->availableItemQuantity($itemId, $from, $to));
        $category = TicketCategory::query()->where('slug', 'maintenance_equipment')->firstOrFail();
        $ticket = app(TicketService::class)->open($d['reporter'], [
            'ticket_category_id' => $category->id,
            'location' => 'Ruang Kelas',
            'description' => 'Kabel proyektor yang pertama terkelupas.',
            'asset_type' => 'item_instance', 'asset_id' => $firstId,
        ]);
        $this->assertSame(1, $availability->availableItemQuantity($itemId, $from, $to));
        $this->assertArrayHasKey($itemId, $availability->itemOptions($from, $to));
        $maintenance = app(TicketMaintenanceService::class);
        $maintenance->approve($ticket, $d['facility'], $d['technician']->id);
        $maintenance->accept($ticket, $d['technician']);
        $maintenance->finish($ticket, $d['technician'], 'Isolasi kabel robek.', 'Ganti kabel dan uji arus.', [], 'complete');
        $maintenance->verify($ticket, $d['reporter'], true);
        $maintenance->close($ticket, $d['facility'], true);
        $this->assertSame(2, $availability->availableItemQuantity($itemId, $from, $to));
        $this->assertTrue((bool) DB::table('g002_m015_item_instances')->where('id', $firstId)->value('is_borrowable'));
    }

    public function test_batched_picker_limits_safety_queries_and_preserves_option_order(): void
    {
        $d = $this->context();
        $now = now();
        $rows = [];
        for ($i = 0; $i < 240; $i++) {
            $rows[] = [
                'name' => sprintf('Aset Demo %03d', $i),
                'code' => sprintf('DEMO-%03d', $i),
                'g001_m001_unit_id' => $d['unit']->id,
                'g002_m003_item_management_id' => $d['managerTeam']->id,
                'quantity' => 2,
                'is_borrowable' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('g002_m007_items')->insert($rows);

        $from = now()->addDays(2);
        $to = $from->copy()->addHours(2);
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        try {
            $options = app(LoanAvailabilityService::class)->itemOptions($from, $to);
            $sql = DB::connection()->getQueryLog();
        } finally {
            DB::connection()->disableQueryLog();
        }
        $holdQueries = collect($sql)->filter(fn (array $row): bool =>
            str_contains($row['query'], 'ticket_maintenance_cases'))->count();

        $this->assertCount(240, $options);
        $this->assertLessThanOrEqual(4, $holdQueries, 'Safety hold queries should scale with pages, not item count.');
        $names = array_values($options);
        $this->assertStringContainsString('Aset Demo 000', $names[0]);
        $this->assertStringContainsString('Aset Demo 239', end($names));
        $this->assertStringContainsString('2 tersedia', $names[0]);
    }

    public function test_safety_batch_preserves_room_and_instance_blocking(): void
    {
        $d = $this->context();
        $now = now();
        $from = now()->addDays(2);
        $to = $from->copy()->addHour();

        // The ticket from context() holds this room from loan selection.
        $roomOptions = app(LoanAvailabilityService::class)->roomOptions($from, $to);
        $this->assertArrayNotHasKey($d['room']->id, $roomOptions);
        $this->assertSame([$d['room']->id], app(TicketAssetSafetyService::class)
            ->blockedAssetIds('room', [$d['room']->id]));
        $this->assertSame([], app(TicketAssetSafetyService::class)
            ->blockedBorrowableItemQuantities([]));

        $itemId = DB::table('g002_m007_items')->insertGetId([
            'name' => 'Multistok', 'code' => 'STOCK-002',
            'g001_m001_unit_id' => $d['unit']->id,
            'g002_m003_item_management_id' => $d['managerTeam']->id,
            'quantity' => 2, 'is_borrowable' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $instanceId = DB::table('g002_m015_item_instances')->insertGetId([
            'g002_m007_item_id' => $itemId, 'g001_m001_unit_id' => $d['unit']->id,
            'name' => 'Baterai 001', 'code' => 'STOCK-002-1',
            'is_borrowable' => true, 'is_available' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('g002_m015_item_instances')->insert([
            'g002_m007_item_id' => $itemId, 'g001_m001_unit_id' => $d['unit']->id,
            'name' => 'Baterai 002', 'code' => 'STOCK-002-2',
            'is_borrowable' => true, 'is_available' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $category = TicketCategory::query()->where('slug', 'maintenance_equipment')->firstOrFail();
        $payload = [
            'ticket_category_id' => $category->id,
            'location' => 'Gudang',
            'description' => 'Baterai tidak aman digunakan, perlu diperiksa.',
            'asset_type' => 'item_instance',
            'asset_id' => $instanceId,
        ];
        // Two active tickets concerning one instance must subtract only 1.
        app(TicketService::class)->open($d['reporter'], $payload);
        app(TicketService::class)->open($d['reporter'], $payload);
        $this->assertSame(1, app(TicketAssetSafetyService::class)
            ->blockedBorrowableItemQuantities([$itemId])[$itemId]);
        $this->assertSame(1, app(LoanAvailabilityService::class)->availableItemQuantity($itemId, $from, $to));
        $this->assertStringContainsString('1 tersedia',
            app(LoanAvailabilityService::class)->itemOptions($from, $to)[$itemId]);
    }

    public function test_legacy_ticket_can_continue_v1_flow_without_v3_signoffs(): void
    {
        $d = $this->context();
        $category = TicketCategory::query()->where('slug', 'damage_room')->firstOrFail();
        $legacy = app(TicketService::class)->openLegacy($d['reporter'], [
            'ticket_category_id' => $category->id,
            'title' => 'Kerusakan pintu',
            'description' => 'Engsel pintu rusak dan sulit dibuka.',
        ]);
        $this->assertFalse($legacy->maintenance()->exists());
        app(TicketService::class)->transition($legacy, $d['facility'], 'triaged');
        $this->assertSame('triaged', $legacy->fresh()->status);
    }
}
