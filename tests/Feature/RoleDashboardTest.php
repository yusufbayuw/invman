<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\RingkasanPeminjaman;
use App\Filament\Pages\SemuaMenu;
use App\Filament\Pages\TugasTeknisi;
use App\Filament\Resources\TicketResource;
use App\Filament\Widgets\DashboardShortcuts;
use App\Filament\Widgets\QuickActionsWidget;
use App\Filament\Widgets\TechnicianTaskStats;
use App\Filament\Widgets\TicketAttentionWidget;
use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketTeamMember;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function user(?string $role = null): User
    {
        $unit = G001M001Unit::query()->firstOrCreate(['name' => 'Unit Uji']);
        $user = User::factory()->create(['g001_m001_unit_id' => $unit->id, 'is_enabled' => true]);
        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function technician(): User
    {
        $user = $this->user();
        TicketTeamMember::query()->create([
            'user_id' => $user->id, 'team_name' => 'Pemeliharaan',
            'team_type' => 'field', 'position' => 'lead', 'is_active' => true,
        ]);

        return $user;
    }

    public function test_sarpras_dashboard_starts_with_daily_actions_and_has_no_technical_or_analytical_widgets(): void
    {
        $this->actingAs($this->user(config('role.sarpras')))->get('/admin')->assertOk()
            ->assertSee('Ajukan Peminjaman')->assertSee('Peminjaman Saya')
            ->assertSee('Lihat Jadwal')->assertSee('Lapor Kerusakan')
            ->assertSee('Perlu Tindakan Anda')->assertSee('Lihat pemantauan aset')->assertDontSee('Prioritas Reservasi Aset')
            ->assertDontSee('Filter Dasbor')->assertDontSee('Ringkasan Pengajuan')
            ->assertDontSee('Komposisi Status')->assertDontSee('Cari menu');
        $this->assertSame([DashboardShortcuts::class, QuickActionsWidget::class, TicketAttentionWidget::class], app(Dashboard::class)->getWidgets());
        $this->get('/admin/ringkasan-peminjaman')->assertOk()->assertSee('Ringkasan Pengajuan')->assertSee('Pengajuan Terbaru')->assertSee('Prioritas Reservasi Aset');
        $this->get(SemuaMenu::getUrl())->assertForbidden();
    }

    public function test_facility_and_administrator_keep_operational_access_and_secondary_tools(): void
    {
        foreach ([config('role.fasilitas'), config('role.admin')] as $role) {
            $this->actingAs($this->user($role))->get('/admin')->assertOk()
                ->assertSee('Perlu Tindakan Anda')->assertSee('Tiket yang Perlu Perhatian')
                ->assertDontSee('Komposisi Status')->assertDontSee('Cari menu');
            $this->get(RingkasanPeminjaman::getUrl())->assertOk()->assertSee('Ringkasan Pengajuan');
            $this->get(SemuaMenu::getUrl())->assertOk()->assertSee('Cari menu');
        }
    }

    public function test_technician_dashboard_contains_only_own_work_and_cannot_open_loan_tools(): void
    {
        $technician = $this->technician();
        $this->workTicket($technician);
        $this->actingAs($technician)->get('/admin')->assertOk()
            ->assertSee('Tugas Saya')->assertSee('Pekerjaan Saya')->assertSee('Perlu Diterima')
            ->assertDontSee('Prioritas Reservasi Aset')->assertDontSee('Perlu Tindakan Anda')
            ->assertDontSee('Ajukan Peminjaman')->assertDontSee('Komposisi Status');
        $this->assertSame([DashboardShortcuts::class, TicketAttentionWidget::class, TechnicianTaskStats::class], app(Dashboard::class)->getWidgets());
        $this->get('/admin/ringkasan-peminjaman')->assertForbidden();
        $this->get('/admin/peminjaman-cepat')->assertForbidden();
        $this->get('/admin/ajukan-peminjaman')->assertForbidden();
    }

    public function test_asset_manager_keeps_managed_work_without_gaining_loan_creation(): void
    {
        $manager = $this->user();
        $management = G002M003ItemManagement::query()->create(['name' => 'Pengelola Uji']);
        $management->users()->attach($manager);
        $this->actingAs($manager)->get('/admin')->assertOk()->assertSee('Peminjaman Dikelola')
            ->assertSee('Perlu Tindakan Anda')->assertDontSee('Ajukan Peminjaman');
        $this->get('/admin/peminjaman-cepat')->assertForbidden();
        $this->get('/admin/ringkasan-peminjaman')->assertOk();
    }

    public function test_combined_sarpras_and_technician_keeps_both_workflows(): void
    {
        $user = $this->technician();
        $user->assignRole(config('role.sarpras'));
        $this->workTicket($user);
        $this->actingAs($user);
        $this->assertContains(QuickActionsWidget::class, app(Dashboard::class)->getWidgets());
        $this->assertContains(TechnicianTaskStats::class, app(Dashboard::class)->getWidgets());
        $this->get('/admin')->assertOk()->assertSee('Ajukan Peminjaman')->assertSee('Perlu Diterima');
    }

    public function test_ticket_widget_preserves_reporter_visibility_and_never_leaks_other_reporters(): void
    {
        $reporter = $this->user(config('role.sarpras'));
        $other = $this->user(config('role.sarpras'));
        $own = $this->ticket($reporter, 'LAPORAN SAYA');
        $foreign = $this->ticket($other, 'LAPORAN RAHASIA');
        $closed = $this->ticket($reporter, 'LAPORAN SELESAI', 'closed');
        Livewire::actingAs($reporter)->test(TicketAttentionWidget::class)
            ->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$foreign, $closed]);
        Livewire::actingAs($this->user(config('role.fasilitas')))->test(TicketAttentionWidget::class)
            ->assertCanSeeTableRecords([$own, $foreign])->assertCanNotSeeTableRecords([$closed]);
    }

    public function test_technician_work_queue_is_assigned_to_self_even_for_a_team_lead(): void
    {
        $technician = $this->technician();
        $other = $this->technician();
        $reporter = $this->user(config('role.sarpras'));
        $own = $this->ticket($reporter, 'TUGAS SAYA');
        $own->update(['assigned_to' => $technician->id]);
        $foreign = $this->ticket($reporter, 'TUGAS PETUGAS LAIN');
        $foreign->update(['assigned_to' => $other->id]);
        Livewire::actingAs($technician)->test(TicketAttentionWidget::class)
            ->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_navigation_groups_are_native_collapsible_and_all_old_urls_are_registered(): void
    {
        $this->actingAs($this->user(config('role.admin')));
        $groups = collect(Filament::getPanel('admin')->getNavigationGroups())->keyBy(fn ($group) => $group->getLabel());
        foreach (['Kelola Peminjaman', 'Opsi Peminjaman', 'Data Aset', 'Pemeriksaan & Riwayat', 'Pengaturan'] as $name) {
            $this->assertTrue($groups[$name]->isCollapsed());
        }
        $paths = collect(Route::getRoutes())->map->uri();
        foreach (['admin', 'admin/peminjaman-cepat', 'admin/ajukan-peminjaman', 'admin/peminjaman-saya', 'admin/peminjaman-serah-terima', 'admin/tiket', 'admin/activity'] as $path) {
            $this->assertContains($path, $paths);
        }
    }

    public function test_technician_links_open_the_exact_ticket_and_preserve_existing_work_guards(): void
    {
        $technician = $this->technician();
        $triaged = $this->workTicket($technician);
        $working = $this->workTicket($technician, 'in_progress');
        $waiting = $this->workTicket($technician, 'waiting_parts');
        $unapproved = $this->workTicket($technician);
        $unapproved->maintenance->forceFill(['approved_at' => null])->save();
        $unaccepted = $this->workTicket($technician, 'in_progress');
        $unaccepted->maintenance->forceFill(['accepted_at' => null])->save();
        $resolved = $this->workTicket($technician, 'resolved');
        $other = $this->workTicket($this->technician());
        $widget = Livewire::actingAs($technician)->test(TicketAttentionWidget::class)
            ->set('tableRecordsPerPage', 10)
            ->assertCanNotSeeTableRecords([$other]);
        foreach ([$triaged, $working, $waiting] as $ticket) {
            $url = TicketResource::getUrl('view', ['record' => $ticket]);
            $widget->assertTableActionVisible('work', $ticket)
                ->assertTableActionHasUrl('work', $url, $ticket);
            $this->get($url)->assertOk()->assertSee($ticket->number);
        }
        foreach ([$unapproved, $unaccepted, $resolved] as $ticket) {
            $widget->assertTableActionHidden('work', $ticket);
        }
        $this->assertNotSame(TicketResource::getUrl('view', ['record' => $triaged]), TicketResource::getUrl('view', ['record' => $working]));
        $this->get(TicketResource::getUrl('view', ['record' => $other]))->assertNotFound();
    }

    public function test_administrative_access_alone_does_not_offer_technician_work(): void
    {
        $manager = $this->user();
        $management = G002M003ItemManagement::query()->create(['name' => 'Pengelola Tanpa Tugas']);
        $management->users()->attach($manager);
        foreach ([$this->user(config('role.admin')), $this->user(config('role.fasilitas')), $manager, $this->technician()] as $user) {
            $this->actingAs($user);
            $this->assertTrue(TugasTeknisi::canAccess());
            $this->assertNotContains(TechnicianTaskStats::class, app(Dashboard::class)->getWidgets());
            $this->assertNotContains('Tugas Saya', array_column(app(DashboardShortcuts::class)->shortcuts(), 'label'));
            // Direct access remains available for history and bookmarks.
            $this->get(TugasTeknisi::getUrl())->assertOk();
        }
    }

    public function test_completed_or_foreign_work_does_not_make_technician_shortcut_relevant(): void
    {
        $administrator = $this->user(config('role.admin'));
        $this->workTicket($administrator, 'closed');
        $this->workTicket($this->technician());
        $this->actingAs($administrator);
        $this->assertFalse(TugasTeknisi::hasActiveTasks());
        $this->assertFalse(TugasTeknisi::shouldRegisterNavigation());
        $own = $this->workTicket($administrator);
        $this->assertTrue(TugasTeknisi::hasActiveTasks());
        $this->assertTrue(TugasTeknisi::shouldRegisterNavigation());
        Livewire::test(TicketAttentionWidget::class)->assertTableActionVisible('work', $own);
    }

    public function test_ticket_unit_filter_is_local_and_cannot_expand_nonfacility_scope(): void
    {
        $reporter = $this->user(config('role.sarpras'));
        $otherUnit = G001M001Unit::query()->create(['name' => 'Unit Asing']);
        $otherReporter = $this->user(config('role.sarpras'));
        $otherReporter->update(['g001_m001_unit_id' => $otherUnit->id]);
        $own = $this->ticket($reporter, 'TIKET UNIT SENDIRI');
        $foreign = $this->ticket($otherReporter, 'TIKET UNIT ASING');
        Livewire::actingAs($this->user(config('role.fasilitas')))->test(TicketAttentionWidget::class)
            ->assertCanSeeTableRecords([$own, $foreign])
            ->filterTable('unit', $otherUnit->id)
            ->assertCanSeeTableRecords([$foreign])->assertCanNotSeeTableRecords([$own]);
        Livewire::actingAs($reporter)->test(TicketAttentionWidget::class)
            ->set('tableFilters', ['unit' => ['value' => $otherUnit->id]])
            ->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_work_action_is_hidden_after_assignment_or_qualification_changes(): void
    {
        $technician = $this->technician();
        $own = $this->workTicket($technician);
        $this->actingAs($this->user(config('role.fasilitas')));
        Livewire::test(TicketAttentionWidget::class)->assertTableActionHidden('work', $own);
        $technician->ticketTeamMember->update(['is_active' => false]);
        Livewire::actingAs($technician)->test(TicketAttentionWidget::class)->assertTableActionHidden('work', $own);
    }

    private function workTicket(User $technician, string $status = 'triaged'): Ticket
    {
        $ticket = $this->ticket($this->user(config('role.sarpras')), 'TUGAS '.Str::uuid(), $status);
        $ticket->update(['assigned_to' => $technician->id]);
        $ticket->maintenance()->create([])->forceFill([
            'approved_at' => now(),
            'accepted_at' => in_array($status, ['in_progress', 'waiting_parts', 'resolved', 'closed']) ? now() : null,
        ])->save();

        return $ticket->fresh();
    }

    private function ticket(User $reporter, string $title, string $status = 'open'): Ticket
    {
        return Ticket::query()->create([
            'number' => 'UI-'.Str::ulid(),
            'ticket_category_id' => TicketCategory::query()->firstOrFail()->id,
            'reporter_id' => $reporter->id, 'g001_m001_unit_id' => $reporter->g001_m001_unit_id,
            'title' => $title, 'description' => 'Fixture pengujian antarmuka',
            'status' => $status, 'priority' => 'normal',
        ]);
    }
}
