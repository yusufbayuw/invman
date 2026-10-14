<?php

namespace Tests\Feature;

use App\Filament\Pages\TugasTeknisi;
use App\Filament\Resources\TicketResource\Pages\ListTickets;
use App\Filament\Resources\TicketResource\Pages\ViewTicket;
use App\Filament\Resources\TicketTeamMemberResource;
use App\Filament\Resources\TicketTeamMemberResource\Pages\ListTicketTeamMembers;
use App\Models\G001M001Unit;
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

    public function test_field_workspace_is_scoped_to_the_assignee_and_can_start_task_without_approval_bypass(): void
    {
        $d = $this->fixtures();
        $maintenance = app(TicketMaintenanceService::class);
        $this->actingAs($d['field']);
        $this->assertTrue(TugasTeknisi::canAccess());
        $this->assertFalse(TugasTeknisi::mayAccept($d['ticket']));
        Livewire::actingAs($d['field'])->test(TugasTeknisi::class)
            ->assertDontSee($d['ticket']->number);

        $maintenance->approve($d['ticket'], $d['facility'], $d['field']->id, 'high');
        $ticket = $d['ticket']->fresh();
        $this->actingAs($d['field']);
        $this->assertTrue(TugasTeknisi::mayAccept($ticket));
        Livewire::actingAs($d['field'])->test(TugasTeknisi::class)
            ->assertSee($ticket->number)
            ->assertSee('Terima & Mulai')
            ->assertSee('Detail Tiket')
            ->assertTableActionVisible('accept', $ticket)
            ->callTableAction('accept', $ticket)
            ->assertHasNoTableActionErrors();

        $ticket = $ticket->fresh();
        $this->assertSame('in_progress', $ticket->status);
        $this->actingAs($d['field']);
        $this->assertTrue(TugasTeknisi::mayWork($ticket));
        Livewire::actingAs($d['field'])->test(TugasTeknisi::class)
            ->assertSee('Bukti Foto Kerja')
            ->assertTableActionVisible('finish', $ticket);
        $this->actingAs($d['outsider']);
        $this->assertFalse(TugasTeknisi::canAccess());
        $this->assertFalse(TugasTeknisi::mayWork($ticket));
        $this->get(TugasTeknisi::getUrl())->assertForbidden();
    }

    public function test_disabled_technician_cannot_accept_or_continue_existing_task(): void
    {
        $d = $this->fixtures();
        $maintenance = app(TicketMaintenanceService::class);
        $maintenance->approve($d['ticket'], $d['facility'], $d['field']->id);
        TicketTeamMember::query()->where('user_id', $d['field']->id)
            ->firstOrFail()->update(['is_active' => false]);

        $ticket = $d['ticket']->fresh();
        $this->actingAs($d['field']);
        $this->assertFalse(TugasTeknisi::canAccess());
        $this->assertFalse(TugasTeknisi::mayAccept($ticket));
        try {
            $maintenance->accept($ticket, $d['field']);
            $this->fail('Inactive technician should not accept a task');
        } catch (ValidationException) {
            $this->assertSame('triaged', $ticket->fresh()->status);
        }
        TicketTeamMember::query()->where('user_id', $d['field']->id)
            ->firstOrFail()->update(['is_active' => true]);
        $maintenance->accept($ticket->fresh(), $d['field']);
        TicketTeamMember::query()->where('user_id', $d['field']->id)
            ->firstOrFail()->update(['is_active' => false]);
        try {
            $maintenance->finish($ticket->fresh(), $d['field'], 'AC kurang dingin', 'Mengganti komponen AC', [], 'complete');
            $this->fail('Inactive technician should not finish a task');
        } catch (ValidationException) {
            $this->assertSame('in_progress', $ticket->fresh()->status);
        }
    }

    public function test_team_hierarchy_cannot_orphan_active_subordinates(): void
    {
        $d = $this->fixtures();
        $lead = TicketTeamMember::query()->where('user_id', $d['lead']->id)->firstOrFail();
        foreach ([
            ['is_active' => false],
            ['team_name' => 'Tim berbeda'],
            ['position' => 'technician'],
        ] as $change) {
            try {
                $lead->update($change);
                $this->fail('Supervisor changed with active descendants');
            } catch (ValidationException) {
                $this->assertSame('Lapangan Utama', $lead->fresh()->team_name);
                $this->assertSame('lead', $lead->fresh()->position);
                $this->assertTrue($lead->fresh()->is_active);
            }
        }
    }

    public function test_general_field_teams_cannot_cross_unit_scope_when_tickets_have_no_asset_manager(): void
    {
        $d = $this->fixtures();
        $unit = G001M001Unit::query()->create(['name' => 'Unit Layanan A']);
        $foreign = G001M001Unit::query()->create(['name' => 'Unit Layanan B']);
        $d['ticket']->forceFill([
            'g002_m003_item_management_id' => null,
            'g001_m001_unit_id' => $unit->id,
        ])->save();

        $generic = User::factory()->create(['g001_m001_unit_id' => $foreign->id]);
        TicketTeamMember::query()->create([
            'user_id' => $generic->id, 'team_name' => 'Tim Layanan',
            'position' => 'lead', 'team_type' => 'field',
        ]);
        $this->assertFalse(TicketTeamMember::qualifiesFor($d['ticket']->fresh(), $generic));
        $generic->update(['g001_m001_unit_id' => $unit->id]);
        $this->assertTrue(TicketTeamMember::qualifiesFor($d['ticket']->fresh(), $generic));
    }


}
