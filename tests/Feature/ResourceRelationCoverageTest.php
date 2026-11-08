<?php

namespace Tests\Feature;

use App\Filament\Resources as R;
use App\Filament\Resources\RelationManagers\ReadOnlyRelationManager;
use App\Filament\Resources\RelationManagers\ResourceRelationManager;
use App\Models as M;
use App\Services\LoanRequestService;
use App\Services\TicketService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResourceRelationCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
        $admin = M\User::factory()->create();
        $admin->assignRole(config('role.admin'));
        $this->actingAs($admin);
    }

    public function test_every_registered_relation_has_a_working_table_complete_schema_and_searchable_preloaded_selects(): void
    {
        $unit = M\G001M001Unit::create(['name' => 'Unit']);
        auth()->user()->update(['g001_m001_unit_id' => $unit->id]);
        $management = M\G002M003ItemManagement::create(['name' => 'Pengelola']);
        $type = M\G002M002ItemType::create(['name' => 'Jenis']);
        $building = M\G003M004Building::create(['name' => 'Gedung']);
        $floor = $building->floor()->create(['name' => 'Lantai']);
        $room = M\G003M006Room::create(['name' => 'Ruangan', 'g003_m005_floor_id' => $floor->id, 'is_borrowable' => true]);
        $item = M\G002M007Item::create(['name' => 'Barang', 'quantity' => 1, 'is_borrowable' => true, 'g001_m001_unit_id' => $unit->id, 'g002_m003_item_management_id' => $management->id, 'g002_m002_item_type_id' => $type->id]);
        $instance = $item->item_instance()->firstOrFail();
        $driver = M\G008M018Driver::create(['user_id' => auth()->id(), 'sim_number' => 'QA-001', 'sim_type' => 'A']);
        $vehicle = M\G008M017Vehicle::create(['name' => 'Kendaraan', 'license_plate' => 'QA 1234', 'is_borrowable' => true, 'default_driver_id' => $driver->id]);
        $assistant = M\VehicleAssistant::create(['name' => 'Pendamping']);
        $title = M\WorkOrderJobTitle::create(['name' => 'Jabatan', 'is_active' => true]);
        $team = M\TicketTeamMember::create(['user_id' => auth()->id(), 'team_name' => 'Tim', 'team_type' => 'field', 'position' => 'lead', 'is_active' => true]);
        $workflow = M\WorkOrderWorkflow::create(['version' => 1, 'status' => 'draft', 'steps' => [], 'created_by' => auth()->id()]);
        $category = M\TicketCategory::create(['name' => 'Perawatan', 'slug' => 'perawatan-qa', 'is_active' => true, 'reporting_enabled' => true]);
        $ticket = app(TicketService::class)->open(auth()->user(), ['ticket_category_id' => $category->id, 'title' => 'Kerusakan contoh', 'description' => 'Uji kelengkapan relasi perawatan']);
        $activity = app(LoanRequestService::class)->saveDraft(auth()->user(), ['name' => 'Draf relasi', 'description' => 'Uji kelengkapan relasi',
            'start_time' => now()->addMonth()->setTime(9, 0), 'end_time' => now()->addMonth()->setTime(10, 0), 'needs' => [
                ['type' => 'item', 'item_id' => $item->id, 'quantity' => 1], ['type' => 'room', 'room_id' => $room->id], ['type' => 'vehicle', 'vehicle_id' => $vehicle->id],
            ]]);
        $parents = [
            [R\G001M001UnitResource::class, $unit], [R\G002M002ItemTypeResource::class, $type],
            [R\G002M003ItemManagementResource::class, $management], [R\G002M007ItemResource::class, $item],
            [R\G002M015ItemInstanceResource::class, $instance], [R\G003M004BuildingResource::class, $building],
            [R\G003M005FloorResource::class, $floor], [R\G003M006RoomResource::class, $room],
            [R\G008M017VehicleResource::class, $vehicle], [R\G008M018DriverResource::class, $driver],
            [R\VehicleAssistantResource::class, $assistant], [R\WorkOrderJobTitleResource::class, $title],
            [R\TicketTeamMemberResource::class, $team], [R\WorkOrderWorkflowResource::class, $workflow],
            [R\TicketCategoryResource::class, $category], [R\TicketResource::class, $ticket],
            [R\LoanEventResource::class, M\LoanEvent::findOrFail($activity->loan_event_id)],
            [R\G004M008ActivityResource::class, $activity],
            [R\G005M009ItemReservationResource::class, $activity->item_reservation()->firstOrFail()],
            [R\G005M010RoomReservationResource::class, $activity->room_reservation()->firstOrFail()],
            [R\G005M019VehicleReservationResource::class, $activity->vehicle_reservation()->firstOrFail()],
            [R\UserResource::class, auth()->user()],
        ];
        $count = 0;
        foreach ($parents as [$resource, $owner]) {
            $page = $resource::getPages()['edit'] ?? $resource::getPages()['view'];
            $pageClass = $page->getPage();
            foreach ($resource::getRelations() as $managerClass) {
                if (! $managerClass::canViewForRecord($owner, $pageClass)) {
                    continue;
                }
                $component = Livewire::test($managerClass, ['ownerRecord' => $owner, 'pageClass' => $pageClass])->assertOk();
                $manager = $component->instance();
                $this->assertNotEmpty($manager->getTable()->getColumns(), $managerClass);
                if ($manager instanceof ResourceRelationManager || $manager instanceof ReadOnlyRelationManager) {
                    $form = $manager->form(Form::make($manager)->model($manager->getRelationship()->getRelated()));
                    $this->assertNotEmpty($form->getComponents(), $managerClass);
                    foreach ($form->getFlatComponents(withHidden: true) as $field) {
                        if ($field instanceof Select) {
                            $this->assertTrue($field->isSearchable(), $managerClass.' '.$field->getName());
                            $this->assertTrue($field->isPreloaded(), $managerClass.' '.$field->getName());
                        }
                    }
                }
                $count++;
            }
        }
        $this->assertGreaterThanOrEqual(90, $count, 'Every collection-bearing application resource is audited.');
    }
}
