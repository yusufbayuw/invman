<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\G005M019VehicleReservation;
use App\Models\G008M017Vehicle;
use App\Models\G008M018Driver;
use App\Models\User;
use App\Models\VehicleAssistant;
use App\Models\VehicleAssignmentHistory;
use App\Services\LoanRequestService;
use App\Services\VehicleAssignmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VehicleCrewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-09 07:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_bus_can_be_requested_without_staff_but_needs_driver_and_assistant_to_be_approved(): void
    {
        [$requester, $manager, $vehicle, $driver] = $this->fixtures(true);
        $reservation = $this->requestVehicle($requester, $vehicle);
        $this->assertNull($reservation->g008_m018_driver_id);
        $this->actingAs($manager);

        try {
            app(LoanRequestService::class)->processReservation('vehicle', $reservation->id, ReservationStatus::Approved);
            $this->fail('Bus should require an assistant.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assistant_id', $exception->errors());
        }

        $this->assertSame(ReservationStatus::Submitted->value, $reservation->fresh()->status);
        $assistant = VehicleAssistant::query()->create(['name' => 'Kenek Pagi', 'is_active' => true]);
        app(LoanRequestService::class)->processReservation('vehicle', $reservation->id, ReservationStatus::Approved, null, [
            'driver_id' => $driver->id,
            'assistant_id' => $assistant->id,
        ]);
        $this->assertSame(ReservationStatus::Approved->value, $reservation->fresh()->status);
        $this->assertEquals($driver->id, $reservation->fresh()->g008_m018_driver_id);
        $this->assertEquals($assistant->id, $reservation->fresh()->vehicle_assistant_id);

        app(LoanRequestService::class)->processReservation('vehicle', $reservation->id, ReservationStatus::CheckedOut);
        $this->assertSame(ReservationStatus::CheckedOut->value, $reservation->fresh()->status);
        $this->assertSame(1, $reservation->assignmentHistories()->count());
    }

    public function test_overlapping_vehicles_cannot_assign_same_driver(): void
    {
        [$requester, $manager, $first, $driver, $management, $unit] = $this->fixtures();
        $other = G008M017Vehicle::query()->create([
            'name' => 'Innova',
            'license_plate' => 'D 1235 TEST',
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'default_driver_id' => $driver->id,
            'is_borrowable' => true,
            'status' => 'tersedia',
        ]);

        $firstReservation = $this->requestVehicle($requester, $first);
        $otherReservation = $this->requestVehicle($requester, $other);
        $this->actingAs($manager);
        app(LoanRequestService::class)->processReservation('vehicle', $firstReservation->id, ReservationStatus::Approved);

        try {
            app(LoanRequestService::class)->processReservation('vehicle', $otherReservation->id, ReservationStatus::Approved);
            $this->fail('Driver collision should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('driver_id', $exception->errors());
        }

        $this->assertSame(ReservationStatus::Submitted->value, $otherReservation->fresh()->status);
        $replacement = $this->driver();
        app(LoanRequestService::class)->processReservation('vehicle', $otherReservation->id, ReservationStatus::Approved, null, [
            'driver_id' => $replacement->id,
        ]);
        $this->assertEquals($replacement->id, $otherReservation->fresh()->g008_m018_driver_id);
    }

    public function test_mid_trip_reassignment_keeps_history_and_master_default(): void
    {
        [$requester, $manager, $vehicle, $default] = $this->fixtures(true);
        $assistant = VehicleAssistant::query()->create(['name' => 'Kenek Satu']);
        $reservation = $this->requestVehicle($requester, $vehicle);
        $this->actingAs($manager);
        app(LoanRequestService::class)->processReservation('vehicle', $reservation->id, ReservationStatus::Approved, null, [
            'assistant_id' => $assistant->id,
        ]);
        app(LoanRequestService::class)->processReservation('vehicle', $reservation->id, ReservationStatus::CheckedOut);

        $replacementDriver = $this->driver();
        $replacementAssistant = VehicleAssistant::query()->create(['name' => 'Kenek Dua']);
        app(VehicleAssignmentService::class)->change($reservation->id, $manager, [
            'driver_id' => $replacementDriver->id,
            'assistant_id' => $replacementAssistant->id,
            'reason' => 'Pengemudi dan kenek awal berhalangan.',
        ]);

        $this->assertEquals($replacementDriver->id, $reservation->fresh()->g008_m018_driver_id);
        $this->assertEquals($replacementAssistant->id, $reservation->fresh()->vehicle_assistant_id);
        $this->assertEquals($default->id, $vehicle->fresh()->default_driver_id);
        $this->assertSame(ReservationStatus::CheckedOut->value, $reservation->fresh()->status);
        $this->assertSame(2, VehicleAssignmentHistory::query()->where('vehicle_reservation_id', $reservation->id)->count());
        $this->assertDatabaseHas('vehicle_assignment_histories', [
            'vehicle_reservation_id' => $reservation->id,
            'old_driver_id' => $default->id,
            'new_driver_id' => $replacementDriver->id,
            'reason' => 'Pengemudi dan kenek awal berhalangan.',
        ]);
    }

    public function test_draft_rechecks_vehicle_availability_before_creating_its_hold(): void
    {
        [$requester, , $vehicle] = $this->fixtures();
        $service = app(LoanRequestService::class);
        $data = $this->requestData($vehicle);
        $first = $service->saveDraft($requester, $data);
        $second = $service->saveDraft($requester, $data);

        $service->submitDraft($requester, $first);

        try {
            $service->submitDraft($requester, $second);
            $this->fail('Second overlapping hold should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(ReservationStatus::Submitted->value, $first->fresh()->status);
        $this->assertSame(ReservationStatus::Draft->value, $second->fresh()->status);
    }

    private function requestVehicle(User $user, G008M017Vehicle $vehicle): G005M019VehicleReservation
    {
        $this->actingAs($user);
        return app(LoanRequestService::class)->submit($user, $this->requestData($vehicle))
            ->vehicle_reservation()->firstOrFail();
    }

    private function requestData(G008M017Vehicle $vehicle): array
    {
        return [
            'name' => 'Kegiatan Transportasi',
            'description' => 'Penugasan kendaraan operasional.',
            'start_time' => '2026-10-09 10:00:00',
            'end_time' => '2026-10-09 12:00:00',
            'needs' => [['type' => 'vehicle', 'vehicle_id' => $vehicle->id]],
        ];
    }

    private function driver(): G008M018Driver
    {
        $user = User::factory()->create();
        return G008M018Driver::query()->create([
            'user_id' => $user->id,
            'sim_number' => 'SIM B',
            'sim_type' => 'B1',
        ]);
    }

    private function fixtures(bool $bus = false): array
    {
        $unit = G001M001Unit::query()->create(['name' => 'Unit Transport']);
        $requester = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        foreach ([config('role.sarpras'), config('role.fasilitas')] as $role) {
            Role::query()->firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $requester->assignRole(config('role.sarpras'));
        $manager = User::factory()->create();
        $manager->assignRole(config('role.fasilitas'));
        $management = G002M003ItemManagement::query()->create(['name' => 'Fasilitas Transportasi']);
        $management->users()->attach($manager);
        $driver = $this->driver();
        $vehicle = G008M017Vehicle::query()->create([
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'name' => $bus ? 'Bus Besar' : 'Mobil',
            'license_plate' => $bus ? 'D 7777 TEST' : 'D 8888 TEST',
            'default_driver_id' => $driver->id,
            'requires_assistant' => $bus,
            'is_borrowable' => true,
            'status' => 'tersedia',
        ]);

        return [$requester, $manager, $vehicle, $driver, $management, $unit];
    }
}
