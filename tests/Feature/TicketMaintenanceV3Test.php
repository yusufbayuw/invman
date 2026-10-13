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
        $response->assertHeader('Cache-Control', 'private, no-store, max-age=0');
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

    public function test_legacy_ticket_can_continue_v1_flow_without_v3_signoffs(): void
    {
        $d = $this->context();
        $category = TicketCategory::query()->where('slug', 'damage_room')->firstOrFail();
        $legacy = app(TicketService::class)->open($d['reporter'], [
            'ticket_category_id' => $category->id,
            'title' => 'Kerusakan pintu',
            'description' => 'Engsel pintu rusak dan sulit dibuka.',
        ]);
        $this->assertFalse($legacy->maintenance()->exists());
        app(TicketService::class)->transition($legacy, $d['facility'], 'triaged');
        $this->assertSame('triaged', $legacy->fresh()->status);
    }
}
