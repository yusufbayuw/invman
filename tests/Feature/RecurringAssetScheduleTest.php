<?php

namespace Tests\Feature;

use App\Filament\Resources\RecurringAssetScheduleResource;
use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G003M006Room;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M019VehicleReservation;
use App\Models\G008M017Vehicle;
use App\Models\RecurringAssetSchedule;
use App\Models\User;
use App\Services\LoanAvailabilityService;
use App\Services\LoanRequestService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecurringAssetScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-01 07:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function assets(): array
    {
        return [G002M007Item::create(['name' => 'Barang', 'quantity' => 3, 'is_borrowable' => true]),
            G003M006Room::create(['name' => 'Ruangan', 'is_borrowable' => true]),
            G008M017Vehicle::create(['name' => 'Kendaraan', 'is_borrowable' => true])];
    }

    private function schedule($asset, array $data = []): RecurringAssetSchedule
    {
        return $asset->recurringSchedules()->create(array_replace([
            'name' => 'Rutin Senin', 'weekdays' => [1], 'start_time' => '09:00', 'end_time' => '11:00',
            'valid_from' => '2026-11-01', 'valid_until' => null, 'is_active' => true,
        ], $data));
    }

    public function test_all_asset_options_and_locked_availability_reject_routine_conflicts(): void
    {
        [$item, $room, $vehicle] = $assets = $this->assets();
        foreach ($assets as $asset) {
            $this->schedule($asset);
        }
        $service = app(LoanAvailabilityService::class);
        $start = Carbon::parse('2026-11-09 10:00');
        $end = Carbon::parse('2026-11-09 12:00');
        $this->assertSame(0, $service->availableItemQuantity($item->id, $start, $end, locking: true));
        $this->assertFalse($service->roomIsAvailable($room->id, $start, $end, locking: true));
        $this->assertFalse($service->vehicleIsAvailable($vehicle->id, $start, $end, locking: true));
        $this->assertArrayNotHasKey($item->id, $service->itemOptions($start, $end));
        $this->assertArrayNotHasKey($room->id, $service->roomOptions($start, $end));
        $this->assertArrayNotHasKey($vehicle->id, $service->vehicleOptions($start, $end));
        $this->assertSame(3, $service->availableItemQuantity($item->id, '2026-11-09 11:00', '2026-11-09 12:00'));
        $this->assertTrue($service->roomIsAvailable($room->id, Carbon::parse('2026-11-09 08:00'), Carbon::parse('2026-11-09 09:00')));
        $this->assertTrue($service->vehicleIsAvailable($vehicle->id, Carbon::parse('2026-11-10 10:00'), Carbon::parse('2026-11-10 11:00')));
    }

    public function test_recurrence_handles_overnight_dates_long_periods_and_deactivation(): void
    {
        $asset = $this->assets()[1];
        $schedule = $this->schedule($asset, ['start_time' => '23:00', 'end_time' => '02:00', 'valid_from' => '2026-11-09', 'valid_until' => '2026-11-09']);
        $overlaps = fn ($start, $end) => $schedule->overlaps(Carbon::parse($start), Carbon::parse($end));
        $this->assertTrue($overlaps('2026-11-10 01:00', '2026-11-10 02:00'));
        $this->assertFalse($overlaps('2026-11-10 02:00', '2026-11-10 03:00'));
        $this->assertFalse($overlaps('2026-11-02 23:00', '2026-11-03 01:00'));
        $this->assertFalse($overlaps('2026-11-16 23:00', '2026-11-17 01:00'));
        $this->assertTrue($overlaps('2026-01-01', '2035-01-01'));
        $schedule->update(['is_active' => false]);
        $this->assertFalse($overlaps('2026-11-09 23:00', '2026-11-10 01:00'));
    }

    public function test_draft_saved_before_schedule_cannot_be_submitted_after_schedule_added(): void
    {
        $this->seed(RoleSeeder::class);
        $unit = G001M001Unit::create(['name' => 'Unit']);
        $user = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $user->assignRole(config('role.sarpras'));
        $item = $this->assets()[0];
        $service = app(LoanRequestService::class);
        $draft = $service->saveDraft($user, ['name' => 'Draf', 'description' => 'Draf sebelum jadwal',
            'start_time' => '2026-11-09 10:00', 'end_time' => '2026-11-09 12:00',
            'needs' => [['type' => 'item', 'item_id' => $item->id, 'quantity' => 1]]]);
        $this->schedule($item);
        $this->expectException(ValidationException::class);
        $service->submitDraft($user, $draft);
    }

    public function test_new_schedule_cannot_override_an_existing_active_booking(): void
    {
        $this->seed(RoleSeeder::class);
        $unit = G001M001Unit::create(['name' => 'Unit']);
        $user = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $user->assignRole(config('role.sarpras'));
        $item = $this->assets()[0];
        app(LoanRequestService::class)->submit($user, ['name' => 'Booking aktif', 'description' => 'Booking sebelum jadwal',
            'start_time' => '2026-11-09 10:00', 'end_time' => '2026-11-09 12:00',
            'needs' => [['type' => 'item', 'item_id' => $item->id, 'quantity' => 1]]]);
        try {
            $this->schedule($item);
            $this->fail('Must reject overlap');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('weekdays', $exception->errors());
        }
        $this->assertDatabaseCount('recurring_asset_schedules', 0);
    }

    public function test_legacy_reservation_creates_cannot_bypass_routine_guard(): void
    {
        $assets = $this->assets();
        foreach ($assets as $asset) {
            $this->schedule($asset);
        }
        foreach ([
            [G005M009ItemReservation::class, 'g002_m007_item_id', $assets[0]->id],
            [G005M010RoomReservation::class, 'g003_m006_room_id', $assets[1]->id],
            [G005M019VehicleReservation::class, 'g008_m017_vehicle_id', $assets[2]->id],
        ] as [$model, $key, $id]) {
            try {
                $model::create([$key => $id, 'start_time' => '2026-11-09 10:00', 'end_time' => '2026-11-09 12:00', 'status' => 'submitted']);
                $this->fail();
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('start_time', $e->errors());
            }
            $this->assertSame(0, $model::count());
        }
    }

    public function test_public_room_schedule_shows_routine_blocks_without_exposing_internal_title(): void
    {
        $room = $this->assets()[1];
        $this->schedule($room, ['name' => 'Rapat rahasia']);
        Carbon::setTestNow('2026-11-09 10:00');
        $this->get($room->publicScheduleUrl())->assertOk()->assertSee('Terpakai untuk jadwal rutin')
            ->assertSee('Jadwal Rutin')->assertSee('Senin')->assertDontSee('Rapat rahasia');
    }

    public function test_schedule_manager_and_asset_relation_render_with_authorization(): void
    {
        $this->seed(RoleSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::factory()->create();
        $admin->assignRole(config('role.admin'));
        $room = $this->assets()[1];
        $this->actingAs($admin)->get(RecurringAssetScheduleResource::getUrl())->assertOk()->assertSee('Jadwal Rutin Aset');
        $this->get('/admin/room/'.$room->id.'/edit')->assertOk()->assertSee('Jadwal Rutin');
        $regular = User::factory()->create();
        $this->actingAs($regular)->get(RecurringAssetScheduleResource::getUrl())->assertForbidden();
    }
}
