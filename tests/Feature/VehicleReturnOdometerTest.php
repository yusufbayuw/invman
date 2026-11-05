<?php
namespace Tests\Feature;

use App\Models\{G001M001Unit, G002M003ItemManagement, G004M008Activity, G005M019VehicleReservation, G008M017Vehicle, LoanHandoverReceipt, User};
use App\Services\{LoanHandoverDocumentService, LoanRequestService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VehicleReturnOdometerTest extends TestCase
{
    use RefreshDatabase;

    private function context(?int $initial = 10000): array
    {
        Role::findOrCreate(config('role.sarpras'), 'web');
        $unit = G001M001Unit::create(['name' => 'Synthetic vehicle unit']);
        $borrower = User::factory()->create(['g001_m001_unit_id' => $unit->id]);
        $borrower->assignRole(config('role.sarpras'));
        $manager = User::factory()->create();
        $management = G002M003ItemManagement::create(['name' => 'Vehicle pool']);
        $management->users()->attach($manager);
        $activity = G004M008Activity::create(['name' => 'Vehicle journey', 'user_id' => $borrower->id,
            'g001_m001_unit_id' => $unit->id, 'status' => 'checked_out',
            'start_time' => now()->subHour(), 'end_time' => now()->addHour()]);
        $vehicle = G008M017Vehicle::create(['name' => 'School vehicle', 'license_plate' => 'QA-ODO',
            'g002_m003_item_management_id' => $management->id, 'is_borrowable' => true]);
        $reservation = G005M019VehicleReservation::create(['g004_m008_activity_id' => $activity->id,
            'g008_m017_vehicle_id' => $vehicle->id, 'status' => 'checked_out',
            'start_time' => $activity->start_time, 'end_time' => $activity->end_time]);
        LoanHandoverReceipt::create(['receipt_number' => 'QA-OUT', 'reservation_type' => 'vehicle',
            'reservation_id' => $reservation->id, 'g004_m008_activity_id' => $activity->id,
            'direction' => 'checkout', 'initiated_by' => $manager->id, 'checkout_odometer' => $initial]);
        return [$borrower, $manager, $reservation];
    }

    public function test_borrower_return_stores_final_reading_and_renders_it_after_two_party_confirmation(): void
    {
        [$borrower, $manager, $reservation] = $this->context();
        $this->actingAs($borrower);
        app(LoanRequestService::class)->requestReservationReturn('vehicle', $reservation->id, ['is_ok' => true, 'return_odometer' => 10125]);
        $this->assertSame(10125, $reservation->fresh()->returnReceipt->return_odometer);
        $this->actingAs($manager);
        app(LoanRequestService::class)->confirmReturn('vehicle', $reservation->id);
        $this->assertSame('returned', $reservation->fresh()->status);
        $data = app(LoanHandoverDocumentService::class)->document('vehicle', $reservation->fresh());
        $this->assertSame(10125, $data['returnEvidence']['returnOdometer']);
        $this->assertStringContainsString('10.125', view('pdf.loans.handover-evidence', $data)->render());
    }

    public function test_invalid_readings_roll_back_receipt_checklist_and_status_on_both_entry_paths(): void
    {
        [$borrower, $manager, $reservation] = $this->context();
        foreach (['borrower', 'manager'] as $path) {
            $this->actingAs($path === 'borrower' ? $borrower : $manager);
            foreach ([9999, -1, '10000.5', 'not-a-number', '99999999999999999999999'] as $value) {
                try {
                    $service = app(LoanRequestService::class);
                    $data = ['is_ok' => true, 'return_odometer' => $value];
                    $path === 'borrower' ? $service->requestReservationReturn('vehicle', $reservation->id, $data)
                        : $service->completeManagedReturn('vehicle', $reservation->id, $data);
                    $this->fail('Invalid odometer accepted');
                } catch (ValidationException) {
                    $this->assertSame('checked_out', $reservation->fresh()->status);
                    $this->assertNull($reservation->fresh()->returnReceipt);
                    $this->assertSame(0, $reservation->returnChecklists()->count());
                }
            }
        }
    }

    public function test_additive_migration_preserves_legacy_receipt_and_backfills_only_null(): void
    {
        [, , $reservation] = $this->context();
        $migration = require database_path('migrations/2026_11_05_000002_add_return_odometer_to_handover_receipts.php');
        // Execute DDL outside the RefreshDatabase wrapper on MySQL only in its
        // dedicated upgrade job; SQLite supports transactional DDL here.
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('MySQL upgrade is executed in the disposable CI upgrade database.');
        }
        $migration->down();
        $before = (array) \Illuminate\Support\Facades\DB::table('loan_handover_receipts')->where('id', $reservation->outboundReceipt->id)->first();
        $migration->up();
        $after = (array) \Illuminate\Support\Facades\DB::table('loan_handover_receipts')->where('id', $reservation->outboundReceipt->id)->first();
        $this->assertNull($after['return_odometer']);
        unset($after['return_odometer']);
        $this->assertSame($before, $after);
    }

    public function test_final_reading_is_optional_and_available_in_the_native_return_form(): void
    {
        [, $manager, $reservation] = $this->context();
        \Livewire\Livewire::withQueryParams(['type' => 'vehicle', 'reservation' => $reservation->id])
            ->actingAs($manager)->test(\App\Filament\Pages\PeminjamanSerahTerima::class)
            ->assertFormFieldExists('return_odometer')
            ->fillForm(['is_ok' => true, 'return_odometer' => null])
            ->call('submitReturn')->assertHasNoFormErrors();
        $this->assertNull($reservation->fresh()->returnReceipt->return_odometer);
        $this->assertSame('return_requested', $reservation->fresh()->status);
    }

    public function test_legacy_missing_initial_reading_can_record_zero(): void
    {
        [, $manager, $reservation] = $this->context(null);
        $this->actingAs($manager);
        app(LoanRequestService::class)->completeManagedReturn('vehicle', $reservation->id, ['is_ok' => true, 'return_odometer' => 0]);
        $this->assertSame(0, $reservation->fresh()->returnReceipt->return_odometer);
        $this->assertSame('return_requested', $reservation->fresh()->status);
    }
}
