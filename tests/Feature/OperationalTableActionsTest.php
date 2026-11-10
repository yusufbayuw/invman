<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Pages\PeminjamanSaya;
use App\Filament\Resources\G005M009ItemReservationResource\Pages\ListG005M009ItemReservations;
use App\Filament\Resources\TicketResource\Pages\ListTickets;
use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderJobTitle;
use App\Models\WorkOrderWorkflow;
use App\Services\TicketService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalTableActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_facility_without_asset_membership_can_open_managed_loans_and_approve_a_reservation(): void
    {
        $this->seed(RoleSeeder::class);
        $unit = G001M001Unit::query()->create(['name' => 'SMA Uji Action']);
        $sarpras = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $sarpras->assignRole(config('role.sarpras'));
        $facility = User::factory()->create();
        $facility->assignRole(config('role.fasilitas'));
        $item = G002M007Item::query()->create([
            'name' => 'Proyektor', 'g001_m001_unit_id' => $unit->id,
            'is_borrowable' => true, 'quantity' => 3, 'available_quantity' => 3,
        ]);
        $activity = G004M008Activity::query()->create([
            'user_id' => $sarpras->id,
            'g001_m001_unit_id' => $unit->id,
            'name' => 'Presentasi Kelas',
            'start_time' => now()->addDay(), 'end_time' => now()->addDay()->addHour(),
            'status' => ReservationStatus::Submitted->value,
        ]);
        $reservation = G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g002_m007_item_id' => $item->id,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
            'status' => ReservationStatus::Submitted->value,
            'quantity' => 1,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($facility);
        $this->assertTrue(PeminjamanSaya::canAccess());
        $this->get(PeminjamanSaya::getUrl())->assertOk();
        Livewire::test(PeminjamanSaya::class)->assertSee('Proyektor');
        Livewire::test(ListG005M009ItemReservations::class)
            ->assertCanSeeTableRecords([$reservation])
            ->assertTableActionVisible('approve', $reservation)
            ->assertTableActionHidden('record_return', $reservation);

        $this->actingAs($sarpras);
        $this->assertFalse($sarpras->managesReservation($reservation));
        $this->assertTrue(PeminjamanSaya::canAccess());
    }

    public function test_work_order_approver_has_action_on_ticket_list_without_global_facility_role(): void
    {
        $this->seed(RoleSeeder::class);
        $unit = G001M001Unit::query()->create(['name' => 'SMA Work Order']);
        $reporter = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $reporter->assignRole(config('role.sarpras'));
        $approver = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $jobTitle = WorkOrderJobTitle::query()->create(['name' => 'Persetujuan Teknisi']);
        WorkOrderAssignment::query()->create([
            'user_id' => $approver->id, 'work_order_job_title_id' => $jobTitle->id,
            'g001_m001_unit_id' => $unit->id,
        ]);
        WorkOrderWorkflow::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'version' => 1, 'status' => 'published', 'published_at' => now(),
            'created_by' => $approver->id,
            'steps' => [
                ['key' => 'approval', 'label' => 'Persetujuan Awal', 'checkpoint' => 'start',
                    'resolver' => 'job_title', 'job_title_id' => $jobTitle->id],
            ],
        ]);
        $category = TicketCategory::query()->where('slug', 'maintenance_hvac')->firstOrFail();
        $ticket = app(TicketService::class)->open($reporter, [
            'ticket_category_id' => $category->id,
            'location' => 'Lab Komputer', 'description' => 'AC tidak berfungsi saat pembelajaran.',
        ]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($approver);
        $this->assertFalse($approver->isFacility());
        Livewire::test(ListTickets::class)
            ->assertCanSeeTableRecords([$ticket])
            ->assertTableActionVisible('work_order_decision', $ticket)
            ->filterTable('status', 'open')
            ->assertTableActionVisible('work_order_decision', $ticket);
    }
}
