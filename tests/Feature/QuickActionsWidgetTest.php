<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Widgets\QuickActionsWidget;
use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use App\Models\G003M006Room;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M016ItemReservationDetail;
use App\Models\G005M019VehicleReservation;
use App\Models\G008M017Vehicle;
use App\Models\LoanHandoverReceipt;
use App\Models\User;
use App\Services\LoanQuickActionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class QuickActionsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_queue_only_contains_actionable_assets_from_assigned_managements(): void
    {
        $this->seed(RoleSeeder::class);
        [$unit, $manager, $managed, $other] = $this->managerFixtures();

        $submitted = $this->itemReservation($unit, $managed, ReservationStatus::Submitted, 'Proyektor');
        $approved = $this->roomReservation($unit, $managed, ReservationStatus::Approved, 'Aula');
        $checkedOut = $this->vehicleReservation($unit, $managed, ReservationStatus::CheckedOut, 'Minibus');
        $outsideManagement = $this->itemReservation($unit, $other, ReservationStatus::Submitted, 'Kamera');
        $returned = $this->itemReservation($unit, $managed, ReservationStatus::Returned, 'Speaker');
        $expired = $this->itemReservation($unit, $managed, ReservationStatus::Submitted, 'Laptop');
        $expired->activity->update(['hold_expires_at' => now()->subMinute()]);

        $queue = app(LoanQuickActionService::class)->forUser($manager)->keyBy('key');

        $this->assertSame([
            'item:'.$submitted->id,
            'room:'.$approved->id,
            'vehicle:'.$checkedOut->id,
        ], $queue->keys()->all());
        $this->assertSame(['approve', 'reject'], $queue['item:'.$submitted->id]['actions']);
        $this->assertSame(['checkout'], $queue['room:'.$approved->id]['actions']);
        $this->assertSame(['recordReturn'], $queue['vehicle:'.$checkedOut->id]['actions']);
        $this->assertArrayNotHasKey('item:'.$outsideManagement->id, $queue);
        $this->assertArrayNotHasKey('item:'.$returned->id, $queue);
        $this->assertArrayNotHasKey('item:'.$expired->id, $queue);
    }

    public function test_sarpras_queue_is_scoped_to_own_unit_and_counterparty_confirmation(): void
    {
        $this->seed(RoleSeeder::class);
        $unit = G001M001Unit::query()->create(['name' => 'Unit Pemohon']);
        $otherUnit = G001M001Unit::query()->create(['name' => 'Unit Lain']);
        $management = G002M003ItemManagement::query()->create(['name' => 'Fasilitas']);
        $manager = User::factory()->create();
        $management->users()->attach($manager);
        $sarpras = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $sarpras->assignRole(config('role.sarpras'));

        $ownCheckedOut = $this->roomReservation($unit, $management, ReservationStatus::CheckedOut, 'Ruang Rapat', $sarpras);
        $ownSubmitted = $this->itemReservation($unit, $management, ReservationStatus::Submitted, 'Laptop', $sarpras);
        $otherCheckedOut = $this->roomReservation($otherUnit, $management, ReservationStatus::CheckedOut, 'Aula Lain');
        $waitingBorrower = $this->vehicleReservation($unit, $management, ReservationStatus::ReturnRequested, 'Mobil Operasional', $sarpras);
        LoanHandoverReceipt::query()->create([
            'receipt_number' => 'RET-TEST-001',
            'reservation_type' => 'vehicle',
            'reservation_id' => $waitingBorrower->id,
            'g004_m008_activity_id' => $waitingBorrower->activity->id,
            'direction' => 'return',
            'initiated_by' => $manager->id,
            'manager_confirmed_by' => $manager->id,
            'manager_confirmed_at' => now(),
        ]);

        $queue = app(LoanQuickActionService::class)->forUser($sarpras)->keyBy('key');

        $this->assertSame(['confirmReturn'], $queue['vehicle:'.$waitingBorrower->id]['actions']);
        $this->assertSame(['requestReturn'], $queue['room:'.$ownCheckedOut->id]['actions']);
        $this->assertArrayNotHasKey('item:'.$ownSubmitted->id, $queue);
        $this->assertArrayNotHasKey('room:'.$otherCheckedOut->id, $queue);
    }

    public function test_widget_actions_validate_and_transition_reservations(): void
    {
        $this->seed(RoleSeeder::class);
        [$unit, $manager, $managed, $other] = $this->managerFixtures();
        $approvedThroughWidget = $this->itemReservation($unit, $managed, ReservationStatus::Submitted, 'Proyektor');
        $rejectedThroughWidget = $this->itemReservation($unit, $managed, ReservationStatus::Submitted, 'Speaker');
        $outsideManagement = $this->itemReservation($unit, $other, ReservationStatus::Submitted, 'Kamera');

        Livewire::actingAs($manager)
            ->test(QuickActionsWidget::class)
            ->assertSee('Tindakan Cepat')
            ->assertSee('Proyektor')
            ->assertDontSee('Kamera')
            ->callAction('approve', arguments: [
                'type' => 'item',
                'reservation_id' => $approvedThroughWidget->id,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(ReservationStatus::Approved->value, $approvedThroughWidget->fresh()->status);

        Livewire::actingAs($manager)
            ->test(QuickActionsWidget::class)
            ->callAction('reject', arguments: [
                'type' => 'item',
                'reservation_id' => $rejectedThroughWidget->id,
            ])
            ->assertHasActionErrors(['rejection_reason' => 'required'])
            ->callAction('reject', data: [
                'rejection_reason' => 'Aset sedang dalam pemeliharaan.',
            ], arguments: [
                'type' => 'item',
                'reservation_id' => $rejectedThroughWidget->id,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(ReservationStatus::Rejected->value, $rejectedThroughWidget->fresh()->status);
        $this->assertSame('Aset sedang dalam pemeliharaan.', $rejectedThroughWidget->fresh()->rejection_reason);

        Livewire::actingAs($manager)
            ->test(QuickActionsWidget::class)
            ->callAction('approve', arguments: [
                'type' => 'item',
                'reservation_id' => $outsideManagement->id,
            ]);

        $this->assertSame(ReservationStatus::Submitted->value, $outsideManagement->fresh()->status);
    }

    public function test_dashboard_renders_empty_queue_for_operational_role_regardless_of_filters(): void
    {
        $this->seed(RoleSeeder::class);
        $facility = User::factory()->create();
        $facility->assignRole(config('role.fasilitas'));

        $this->actingAs($facility)
            ->get('/admin?filters[start_date]=1990-01-01&filters[end_date]=1990-01-02&filters[status]=returned')
            ->assertOk()
            ->assertSee('Tindakan Cepat')
            ->assertSee('Tidak ada tindakan yang menunggu');
    }

    public function test_widget_completes_checkout_and_two_party_return_flow(): void
    {
        $this->seed(RoleSeeder::class);
        [$unit, $manager, $management] = $this->managerFixtures();
        $sarpras = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $sarpras->assignRole(config('role.sarpras'));
        $reservation = $this->roomReservation(
            $unit,
            $management,
            ReservationStatus::Approved,
            'Ruang Serbaguna',
            $sarpras,
        );
        $arguments = [
            'type' => 'room',
            'reservation_id' => $reservation->id,
        ];

        Livewire::actingAs($manager)
            ->test(QuickActionsWidget::class)
            ->callAction('checkout', arguments: $arguments)
            ->assertHasNoActionErrors();

        $this->assertSame(ReservationStatus::CheckedOut->value, $reservation->fresh()->status);

        Livewire::actingAs($sarpras)
            ->test(QuickActionsWidget::class)
            ->callAction('requestReturn', data: [
                'is_ok' => true,
                'notes' => 'Ruangan sudah dirapikan.',
            ], arguments: $arguments)
            ->assertHasNoActionErrors();

        $this->assertSame(ReservationStatus::ReturnRequested->value, $reservation->fresh()->status);
        $this->assertNotNull($reservation->fresh()->returnReceipt?->borrower_confirmed_at);
        $this->assertNull($reservation->fresh()->returnReceipt?->manager_confirmed_at);

        Livewire::actingAs($manager)
            ->test(QuickActionsWidget::class)
            ->callAction('confirmReturn', arguments: $arguments)
            ->assertHasNoActionErrors();

        $this->assertSame(ReservationStatus::Returned->value, $reservation->fresh()->status);
        $this->assertNotNull($reservation->fresh()->returnReceipt?->completed_at);
    }

    public function test_widget_prefills_item_instances_and_supports_manager_initiated_return(): void
    {
        $this->seed(RoleSeeder::class);
        [$unit, $manager, $management] = $this->managerFixtures();
        $sarpras = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $sarpras->assignRole(config('role.sarpras'));
        $reservation = $this->itemReservation(
            $unit,
            $management,
            ReservationStatus::CheckedOut,
            'Kamera',
            $sarpras,
        );
        $reservation->item->update(['available_quantity' => 0]);
        $instance = G002M015ItemInstance::query()->create([
            'g002_m007_item_id' => $reservation->g002_m007_item_id,
            'name' => 'Kamera 01',
            'code' => 'CAM-01',
            'status' => ReservationStatus::CheckedOut->value,
            'is_available' => false,
            'is_borrowable' => true,
        ]);
        G005M016ItemReservationDetail::query()->create([
            'g005_m009_item_reservation_id' => $reservation->id,
            'g002_m015_item_instance_id' => $instance->id,
        ]);
        $arguments = [
            'type' => 'item',
            'reservation_id' => $reservation->id,
        ];

        $component = Livewire::actingAs($manager)
            ->test(QuickActionsWidget::class)
            ->mountAction('recordReturn', $arguments);
        $mountedInstances = $component->get('mountedActionsData.0.instances');
        $instanceKey = array_key_first($mountedInstances);

        $this->assertSame($instance->id, $mountedInstances[$instanceKey]['item_instance_id']);
        $this->assertSame('CAM-01', $mountedInstances[$instanceKey]['instance_label']);
        $this->assertTrue($mountedInstances[$instanceKey]['is_ok']);

        $component
            ->setActionData([
                'instances' => [$instanceKey => [
                    'item_instance_id' => $instance->id,
                    'instance_label' => 'CAM-01',
                    'is_ok' => true,
                ]],
                'receipt_notes' => 'Diterima langsung oleh pengelola.',
            ])
            ->call('callMountedAction')
            ->assertHasNoActionErrors();

        $this->assertSame(ReservationStatus::ReturnRequested->value, $reservation->fresh()->status);
        $this->assertNotNull($reservation->fresh()->returnReceipt?->manager_confirmed_at);
        $this->assertNull($reservation->fresh()->returnReceipt?->borrower_confirmed_at);
        $this->assertDatabaseHas('loan_reservation_checklists', [
            'reservation_type' => 'item',
            'reservation_id' => $reservation->id,
            'g002_m015_item_instance_id' => $instance->id,
            'is_ok' => true,
        ]);

        Livewire::actingAs($sarpras)
            ->test(QuickActionsWidget::class)
            ->callAction('confirmReturn', arguments: $arguments)
            ->assertHasNoActionErrors();

        $this->assertSame(ReservationStatus::Returned->value, $reservation->fresh()->status);
        $this->assertTrue((bool) $instance->fresh()->is_available);
    }

    private function managerFixtures(): array
    {
        $unit = G001M001Unit::query()->create(['name' => 'Unit Pengujian']);
        $manager = User::factory()->create();
        $managed = G002M003ItemManagement::query()->create(['name' => 'Pengelolaan Fasilitas']);
        $other = G002M003ItemManagement::query()->create(['name' => 'Pengelolaan Lain']);
        $managed->users()->attach($manager);

        return [$unit, $manager, $managed, $other];
    }

    private function activity(G001M001Unit $unit, ReservationStatus $status, ?User $requester = null): G004M008Activity
    {
        $requester ??= User::factory()->create(['g001_m001_unit_id' => $unit->id]);

        return G004M008Activity::query()->create([
            'user_id' => $requester->id,
            'g001_m001_unit_id' => $unit->id,
            'name' => 'Kegiatan '.Str::random(6),
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
            'status' => $status->value,
        ]);
    }

    private function itemReservation(
        G001M001Unit $unit,
        G002M003ItemManagement $management,
        ReservationStatus $status,
        string $name,
        ?User $requester = null,
    ): G005M009ItemReservation {
        $activity = $this->activity($unit, $status, $requester);
        $item = G002M007Item::query()->create([
            'name' => $name,
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'quantity' => 1,
            'available_quantity' => 1,
            'is_borrowable' => true,
        ]);

        return G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g002_m007_item_id' => $item->id,
            'quantity' => 1,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
            'status' => $status->value,
        ]);
    }

    private function roomReservation(
        G001M001Unit $unit,
        G002M003ItemManagement $management,
        ReservationStatus $status,
        string $name,
        ?User $requester = null,
    ): G005M010RoomReservation {
        $activity = $this->activity($unit, $status, $requester);
        $room = G003M006Room::query()->create([
            'name' => $name,
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'is_borrowable' => true,
        ]);

        return G005M010RoomReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g003_m006_room_id' => $room->id,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
            'status' => $status->value,
        ]);
    }

    private function vehicleReservation(
        G001M001Unit $unit,
        G002M003ItemManagement $management,
        ReservationStatus $status,
        string $name,
        ?User $requester = null,
    ): G005M019VehicleReservation {
        $activity = $this->activity($unit, $status, $requester);
        $vehicle = G008M017Vehicle::query()->create([
            'name' => $name,
            'license_plate' => 'B 1234 QA',
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'is_borrowable' => true,
        ]);

        return G005M019VehicleReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g008_m017_vehicle_id' => $vehicle->id,
            'start_time' => $activity->start_time,
            'end_time' => $activity->end_time,
            'status' => $status->value,
        ]);
    }
}
