<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Resources\TicketResource;
use App\Filament\Resources\TicketResource\Pages\CreateTicket;
use App\Filament\Resources\TicketResource\Pages\ListTickets;
use App\Filament\Resources\TicketResource\Pages\ViewTicket;
use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\G003M006Room;
use App\Models\G004M008Activity;
use App\Models\G005M010RoomReservation;
use App\Models\LoanReservationChecklist;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketEvent;
use App\Models\User;
use App\Services\LoanRequestService;
use App\Services\TicketService;
use App\Services\TicketVisibility;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TicketingCoreTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $this->seed(RoleSeeder::class);
        $unit = G001M001Unit::query()->create(['name' => 'Unit Operasional']);
        $foreignUnit = G001M001Unit::query()->create(['name' => 'Unit Asing']);
        $reporter = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $reporter->assignRole(config('role.sarpras'));
        $manager = User::factory()->create();
        $outsider = User::factory()->create(['g001_m001_unit_id' => $foreignUnit->id]);
        $outsider->assignRole(config('role.sarpras'));
        $facility = User::factory()->create();
        $facility->assignRole(config('role.fasilitas'));
        $management = G002M003ItemManagement::query()->create(['name' => 'Tim Pemeliharaan']);
        $management->users()->attach($manager);
        $room = G003M006Room::query()->create([
            'name' => 'Ruang Seminar',
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'is_borrowable' => true,
        ]);

        return [$reporter, $manager, $outsider, $facility, $room, $management, $unit, $foreignUnit];
    }

    private function data(G003M006Room $room): array
    {
        return [
            'ticket_category_id' => TicketCategory::query()->where('slug','damage_room')->value('id'),
            'title' => 'AC ruangan rusak',
            'description' => 'AC ruangan mati saat kegiatan, mohon diperiksa oleh pengelola.',
            'asset_type' => 'room',
            'asset_id' => $room->id,
        ];
    }

    public function test_ticket_created_with_asset_assignment_scope_and_immutable_audit(): void
    {
        [$reporter, $manager, $outsider, $facility, $room, $management] = $this->context();
        $service = app(TicketService::class);
        $ticket = $service->open($reporter, $this->data($room));

        $this->assertStringStartsWith('TKT-', $ticket->number);
        $this->assertSame('open', $ticket->status);
        $this->assertSame('normal', $ticket->priority);
        $this->assertSame($management->id, $ticket->g002_m003_item_management_id);
        $this->assertDatabaseHas('ticket_asset_links', [
            'ticket_id' => $ticket->id, 'asset_type' => 'room', 'asset_id' => $room->id,
        ]);
        $this->assertDatabaseHas('ticket_events', ['ticket_id' => $ticket->id, 'action' => 'created']);
        $visibility = app(TicketVisibility::class);
        $this->assertTrue($visibility->canView($reporter, $ticket));
        $this->assertTrue($visibility->canView($manager, $ticket));
        $this->assertTrue($visibility->canView($facility, $ticket));
        $this->assertFalse($visibility->canView($outsider, $ticket));
        $this->assertFalse($visibility->canManage($reporter, $ticket));
        $this->assertTrue($visibility->canManage($manager, $ticket));

        $this->expectException(ValidationException::class);
        $ticket->delete();
    }

    public function test_no_cross_unit_asset_forgery_or_non_activated_category(): void
    {
        [$reporter, , $outsider, , $room] = $this->context();
        $service = app(TicketService::class);
        try {
            $service->open($outsider, $this->data($room));
            $this->fail('Foreign user accepted asset from unrelated unit.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('tickets', 0);
        }

        $inactive = TicketCategory::query()->where('slug', 'damage_room')->firstOrFail();
        $inactive->update(['is_active' => false]);
        $this->expectException(ValidationException::class);
        $service->open($reporter, $this->data($room));
    }

    public function test_ticket_workflow_assignment_and_internal_notes_are_guarded(): void
    {
        [$reporter, $manager, $outsider, $facility, $room] = $this->context();
        $service = app(TicketService::class);
        $ticket = $service->open($reporter, $this->data($room));
        $this->assertSame([], $service->availableTransitions($ticket, $outsider));

        try {
            $service->transition($ticket, $reporter, 'resolved');
            $this->fail('Requester cannot resolve a ticket.');
        } catch (ValidationException) {
            $this->assertSame('open', $ticket->fresh()->status);
        }

        $service->assign($ticket, $manager, $manager->id);
        $this->assertSame($manager->id, $ticket->fresh()->assigned_to);

        try {
            $service->assign($ticket, $manager, $outsider->id);
            $this->fail('Foreign assignee accepted.');
        } catch (ValidationException) {
            $this->assertSame($manager->id, $ticket->fresh()->assigned_to);
        }

        $service->transition($ticket, $manager, 'triaged');
        $service->transition($ticket, $manager, 'in_progress');
        $service->comment($ticket, $manager, 'Petugas telah mengecek kompresor.', true);
        $service->comment($ticket, $reporter, 'Kapan perkiraan selesai?', false);
        $this->assertSame(2, $ticket->comments()->count());
        $this->assertSame(1, $ticket->visibleComments()->count());
        $this->actingAs($manager);
        $this->assertSame(2, $ticket->visibleComments()->count());

        try {
            $service->comment($ticket, $reporter, 'Internal tanpa izin', true);
            $this->fail('Reporter may not add internal notes.');
        } catch (ValidationException) {
            $this->assertSame(2, $ticket->comments()->count());
        }

        $service->transition($ticket, $manager, 'resolved', 'AC telah berfungsi.');
        $this->assertNotNull($ticket->fresh()->resolved_at);
        $service->transition($ticket, $reporter, 'closed');
        $this->assertSame('closed', $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->closed_at);
        $service->transition($ticket, $reporter, 'reopened', 'Masih ada kebocoran.');
        $this->assertSame('reopened', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->closed_at);
        $this->assertNull($ticket->fresh()->resolved_at);
        $this->assertGreaterThanOrEqual(6, $ticket->events()->count());

        $event = TicketEvent::query()->where('ticket_id', $ticket->id)->firstOrFail();
        $this->expectException(ValidationException::class);
        $event->delete();
    }

    public function test_private_attachments_require_ticket_permissions(): void
    {
        [$reporter, $manager, $outsider, , $room] = $this->context();
        Storage::fake('local');
        $service = app(TicketService::class);
        $ticket = $service->open($reporter, $this->data($room));
        $path = 'tickets/attachments/test.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 test');
        $this->actingAs($manager);
        $privateComment = $service->comment($ticket, $manager, 'Internal diagnosis', true);
        $service->attach($ticket, $manager, [$path], $privateComment);
        $attachment = $ticket->attachments()->firstOrFail();

        $url = route('tickets.attachment', ['attachment' => $attachment->id]);
        $this->actingAs($reporter)->get($url)->assertNotFound();
        $this->assertSame(0, $ticket->visibleAttachments()->count());
        $this->actingAs($outsider)->get($url)->assertNotFound();
        $this->actingAs($manager)->get($url)->assertOk()
            ->assertHeader('Content-Disposition');
        $this->assertSame(1, $ticket->visibleAttachments()->count());
        $this->assertDatabaseHas('ticket_attachments', [
            'ticket_id' => $ticket->id, 'ticket_comment_id' => $privateComment->id, 'disk' => 'local',
        ]);
    }

    public function test_damaged_room_return_opens_one_ticket_without_modifying_loan_status(): void
    {
        [$reporter, $manager, , , $room, , $unit] = $this->context();
        $activity = G004M008Activity::query()->create([
            'user_id' => $reporter->id, 'g001_m001_unit_id' => $unit->id,
            'name' => 'Kegiatan Pelatihan', 'status' => 'checked_out',
            'start_time' => now()->subHours(3), 'end_time' => now()->addHours(2),
        ]);
        $reservation = G005M010RoomReservation::query()->create([
            'g004_m008_activity_id' => $activity->id, 'g003_m006_room_id' => $room->id,
            'status' => 'checked_out', 'start_time' => $activity->start_time, 'end_time' => $activity->end_time,
        ]);
        LoanReservationChecklist::query()->create([
            'reservation_type' => 'room', 'reservation_id' => $reservation->id,
            'g004_m008_activity_id' => $activity->id,
            'checked_by' => $manager->id, 'is_ok' => false,
            'notes' => 'Pintu ruang rusak setelah kegiatan.', 'checked_at' => now(),
        ]);
        $this->actingAs($manager);
        app(TicketService::class)->damagedReturn('room', $reservation);
        app(TicketService::class)->damagedReturn('room', $reservation);
        $this->assertDatabaseCount('tickets', 1);
        $ticket = Ticket::query()->firstOrFail();
        $this->assertSame('high', $ticket->priority);
        $this->assertSame('Kondisi tidak baik: Ruang Seminar', $ticket->title);
        $this->assertSame($activity->id, $ticket->g004_m008_activity_id);
        $this->assertSame('checked_out', $reservation->fresh()->status);
        $this->assertStringStartsWith('return:room:', $ticket->source_key);
    }

    public function test_filament_ticket_resource_is_visible_to_authorized_user_and_scopes_tickets(): void
    {
        [$reporter, , $outsider, , $room] = $this->context();
        $ticket = app(TicketService::class)->open($reporter, $this->data($room));

        Livewire::actingAs($reporter)->test(ListTickets::class)
            ->assertSee($ticket->number)
            ->assertSee('AC ruangan rusak');
        Livewire::actingAs($reporter)->test(ViewTicket::class, ['record' => $ticket->id])
            ->assertSee($ticket->number)
            ->assertSee('Ruang Seminar')
            ->assertSee('Tambah Komentar');

        Livewire::actingAs($outsider)->test(ListTickets::class)
            ->assertDontSee($ticket->number);
        $this->actingAs($outsider)->get(TicketResource::getUrl('view', ['record' => $ticket]))->assertNotFound();

        Livewire::actingAs($reporter)->test(CreateTicket::class)
            ->fillForm($this->data($room))
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertDatabaseCount('tickets', 2);
    }
}
