<?php

namespace Tests\Feature;

use App\Filament\Resources\TicketResource\Pages\ListTickets;
use App\Filament\Resources\TicketResource\Pages\ViewTicket;
use App\Filament\Resources\TicketTeamMemberResource;
use App\Filament\Resources\TicketTeamMemberResource\Pages\ListTicketTeamMembers;
use App\Models\G002M003ItemManagement;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketTeamMember;
use App\Models\User;
use App\Services\TicketMaintenanceService;
use App\Services\TicketVisibility;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TicketTeamHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $this->seed(RoleSeeder::class);
        $facility = User::factory()->create();
        $facility->assignRole(config('role.fasilitas'));
        $reporter = User::factory()->create();
        $reporter->assignRole(config('role.sarpras'));
        $lead = User::factory()->create();
        $assistantLead = User::factory()->create();
        $field = User::factory()->create();
        $outsider = User::factory()->create();
        $otherTeam = User::factory()->create();
        $management = G002M003ItemManagement::query()->create(['name' => 'Fasilitas Gedung A']);

        TicketTeamMember::query()->create([
            'user_id' => $lead->id, 'team_name' => 'Lapangan Utama',
            'team_type' => 'field', 'position' => 'lead',
            'g002_m003_item_management_id' => $management->id,
        ]);
        TicketTeamMember::query()->create([
            'user_id' => $assistantLead->id, 'supervisor_user_id' => $lead->id,
            'team_name' => 'Lapangan Utama', 'team_type' => 'field', 'position' => 'lead',
            'g002_m003_item_management_id' => $management->id,
        ]);
        TicketTeamMember::query()->create([
            'user_id' => $field->id, 'supervisor_user_id' => $assistantLead->id,
            'team_name' => 'Lapangan Utama', 'team_type' => 'field', 'position' => 'technician',
            'g002_m003_item_management_id' => $management->id,
        ]);
        TicketTeamMember::query()->create([
            'user_id' => $otherTeam->id, 'team_name' => 'Lapangan Lain',
            'team_type' => 'support', 'position' => 'lead',
        ]);

        $ticket = Ticket::query()->create([
            'number' => 'TKT-2026-TEAM-001',
            'reporter_id' => $reporter->id,
            'ticket_category_id' => TicketCategory::query()->firstOrFail()->id,
            'g002_m003_item_management_id' => $management->id,
            'title' => 'Pemeriksaan Instalasi Listrik',
            'description' => 'Saklar panel gedung mengeluarkan bunyi saat dinyalakan.',
            'status' => 'open',
        ]);
        app(TicketMaintenanceService::class)->initialize($ticket);

        return compact('facility', 'reporter', 'lead', 'assistantLead', 'field', 'outsider', 'otherTeam', 'management', 'ticket');
    }

    public function test_field_hierarchy_delegates_to_descendants_without_granting_approval_or_cross_team_access(): void
    {
        $d = $this->fixtures();
        $maintenance = app(TicketMaintenanceService::class);
        $this->assertSame([$d['assistantLead']->id, $d['field']->id], TicketTeamMember::descendantsOf($d['lead']));
        $this->assertTrue($d['field']->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));
        $this->assertFalse($maintenance->canDelegate($d['ticket'], $d['lead']));
        $maintenance->approve($d['ticket'], $d['facility'], $d['lead']->id, 'high');

        $ticket = $d['ticket']->fresh();
        $this->assertTrue($maintenance->canDelegate($ticket, $d['lead']));
        $this->assertFalse($maintenance->canDelegate($ticket, $d['field']));
        $this->assertArrayHasKey($d['field']->id, $maintenance->delegationOptions($ticket, $d['lead']));
        $this->assertArrayNotHasKey($d['otherTeam']->id, $maintenance->delegationOptions($ticket, $d['lead']));
        $this->assertArrayNotHasKey($d['reporter']->id, $maintenance->delegationOptions($ticket, $d['lead']));

        try {
            $maintenance->delegate($ticket, $d['lead'], $d['otherTeam']->id, 'Tugas lintas tim');
            $this->fail('Cross-team delegation was accepted');
        } catch (ValidationException) {
            $this->assertSame($d['lead']->id, $ticket->fresh()->assigned_to);
        }
        $maintenance->delegate($ticket, $d['lead'], $d['field']->id, 'Periksa kelistrikan lapangan');
        $ticket = $ticket->fresh();
        $this->assertSame($d['field']->id, $ticket->assigned_to);
        $this->assertDatabaseHas('ticket_events', [
            'ticket_id' => $ticket->id,
            'actor_id' => $d['lead']->id,
            'action' => 'maintenance_delegated',
            'to_value' => (string) $d['field']->id,
        ]);
        $this->assertTrue(app(TicketVisibility::class)->canView($d['lead'], $ticket));
        $this->assertTrue(app(TicketVisibility::class)->canView($d['assistantLead'], $ticket));
        $this->assertTrue(app(TicketVisibility::class)->canView($d['field'], $ticket));
        $this->assertFalse(app(TicketVisibility::class)->canView($d['outsider'], $ticket));
        $this->assertFalse(app(TicketVisibility::class)->canView($d['otherTeam'], $ticket));

        Livewire::actingAs($d['field'])->test(ListTickets::class)
            ->assertSee($ticket->number)
            ->assertDontSee('Buat Laporan Kerusakan');
        Livewire::actingAs($d['lead'])->test(ViewTicket::class, ['record' => $ticket->id])
            ->assertSee('Delegasikan ke Tim');

        $maintenance->accept($ticket, $d['field']);
        $this->assertFalse($maintenance->canDelegate($ticket->fresh(), $d['lead']));
        try {
            $maintenance->delegate($ticket, $d['lead'], $d['assistantLead']->id, 'Alih tugas saat pekerjaan berlangsung');
            $this->fail('Accepted task reassignment was accepted');
        } catch (ValidationException) {
            $this->assertSame($d['field']->id, $ticket->fresh()->assigned_to);
        }
    }

    public function test_cycles_foreign_supervisors_and_unassigned_technicians_are_rejected(): void
    {
        $d = $this->fixtures();
        $root = TicketTeamMember::query()->where('user_id', $d['lead']->id)->firstOrFail();
        try {
            $root->update(['supervisor_user_id' => $d['assistantLead']->id]);
            $this->fail('Circular supervision accepted');
        } catch (ValidationException) {
            $this->assertNull($root->fresh()->supervisor_user_id);
        }

        try {
            TicketTeamMember::query()->create([
                'user_id' => $d['outsider']->id,
                'team_name' => 'Lapangan Utama', 'team_type' => 'field',
                'position' => 'technician', 'supervisor_user_id' => $d['otherTeam']->id,
                'g002_m003_item_management_id' => $d['management']->id,
            ]);
            $this->fail('Foreign supervisor was accepted');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('ticket_team_members', ['user_id' => $d['outsider']->id]);
        }

        try {
            TicketTeamMember::query()->create([
                'user_id' => $d['outsider']->id, 'team_name' => 'Lapangan Utama',
                'team_type' => 'field', 'position' => 'technician',
            ]);
            $this->fail('Orphan technician was accepted');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('ticket_team_members', ['user_id' => $d['outsider']->id]);
        }
        $this->actingAs($d['lead']);
        $this->assertFalse(TicketTeamMemberResource::canViewAny());
        $this->actingAs($d['facility']);
        $this->assertTrue(TicketTeamMemberResource::canViewAny());
        Livewire::actingAs($d['facility'])->test(ListTicketTeamMembers::class)
            ->assertSee('Lapangan Utama')
            ->assertSee($d['field']->name);
    }

    public function test_disabling_field_member_removes_delegation_but_does_not_change_old_tickets(): void
    {
        $d = $this->fixtures();
        $maintenance = app(TicketMaintenanceService::class);
        $maintenance->approve($d['ticket'], $d['facility'], $d['lead']->id);
        TicketTeamMember::query()->where('user_id', $d['field']->id)->firstOrFail()
            ->update(['is_active' => false]);

        $this->assertArrayNotHasKey($d['field']->id,
            $maintenance->delegationOptions($d['ticket']->fresh(), $d['lead']));
        $this->assertSame('triaged', $d['ticket']->fresh()->status);
    }
}
