<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M019VehicleReservation;
use App\Models\LoanRequestNeed;
use App\Models\LoanReservationStatusHistory;
use App\Models\User;
use App\Services\LoanRequestService;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MassAssignmentHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_persisted_application_model_has_an_explicit_attribute_allowlist(): void
    {
        $paths = glob(app_path('Models/*.php'));

        $this->assertNotEmpty($paths);

        foreach ($paths as $path) {
            $class = 'App\\Models\\'.basename($path, '.php');
            $instance = new $class;

            $this->assertFalse($instance->isFillable('id'), "{$class}: primary key must not be mass assignable");
            $this->assertFalse($instance->isFillable('created_at'), "{$class}: timestamps must not be mass assignable");
            $this->assertFalse($instance->isFillable('updated_at'), "{$class}: timestamps must not be mass assignable");
            $this->assertFalse($instance->isFillable('unexpected_admin_flag'), "{$class}: unknown flags must not be mass assignable");

            if (! $instance instanceof LoanRequestNeed) {
                $this->assertNotEmpty($instance->getFillable(), "{$class} must have an explicit fillable list");
                $this->assertNotContains('*', $instance->getFillable(), "{$class} cannot use '*' as fillable");
            }
        }

        $this->assertFalse(Model::isUnguarded());
    }

    public function test_untrusted_assignment_cannot_forge_reservation_approval_audit(): void
    {
        foreach ([G005M009ItemReservation::class, G005M010RoomReservation::class, G005M019VehicleReservation::class] as $model) {
            $instance = new $model;
            foreach (['decision_by', 'decision_at', 'status_changed_by', 'status_changed_at', 'overdue_notified_at'] as $column) {
                $this->assertFalse($instance->isFillable($column), "{$model}: {$column} must be service-only");
            }
            $this->assertTrue($instance->isFillable('status'), "{$model}: lifecycle uses a validated transition service/observer");
        }

        $this->expectException(MassAssignmentException::class);
        G005M009ItemReservation::query()->create(['decision_by' => 123]);
    }

    public function test_untrusted_payload_cannot_override_workflow_owner_unit_or_status(): void
    {
        $this->seed(RoleSeeder::class);

        $ownUnit = G001M001Unit::query()->create(['name' => 'Unit sendiri']);
        $otherUnit = G001M001Unit::query()->create(['name' => 'Unit asing']);
        $requester = User::factory()->create(['g001_m001_unit_id' => $ownUnit->id]);
        $requester->assignRole(config('role.sarpras'));
        $attacker = User::factory()->create(['g001_m001_unit_id' => $otherUnit->id]);
        $this->actingAs($requester);

        $draft = app(LoanRequestService::class)->saveDraft($requester, [
            'name' => 'Draf sah',
            'description' => 'Permintaan barang',
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHours(2),
            'needs' => [],
            // Forged fields must not pass through the service's explicit input mapping.
            'id' => Str::uuid()->toString(),
            'user_id' => $attacker->id,
            'g001_m001_unit_id' => $otherUnit->id,
            'status' => ReservationStatus::Approved->value,
            'hold_expires_at' => now()->addYear(),
            'decision_by' => $attacker->id,
            'status_changed_by' => $attacker->id,
        ]);

        $this->assertSame($requester->id, $draft->user_id);
        $this->assertSame($ownUnit->id, $draft->g001_m001_unit_id);
        $this->assertSame(ReservationStatus::Draft->value, $draft->status);
        $this->assertNull($draft->hold_expires_at);
        $this->assertNotSame($attacker->id, $draft->user_id);
    }

    public function test_audit_status_history_cannot_be_edited_or_deleted(): void
    {
        $history = LoanReservationStatusHistory::query()->create([
            'reservation_type' => 'item',
            'reservation_id' => Str::uuid()->toString(),
            'from_status' => ReservationStatus::Draft->value,
            'to_status' => ReservationStatus::Submitted->value,
            'notes' => 'Original evidence',
        ]);

        try {
            $history->update(['notes' => 'Forged evidence']);
            $this->fail('Audit evidence must never be editable.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('loan_reservation_status_histories', [
                'id' => $history->id, 'notes' => 'Original evidence',
            ]);
        }

        try {
            $history->delete();
            $this->fail('Audit evidence must never be deletable.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('loan_reservation_status_histories', ['id' => $history->id]);
        }
    }

    public function test_master_record_identifier_is_not_fillable(): void
    {
        $this->expectException(MassAssignmentException::class);
        G002M007Item::query()->create(['id' => 999999, 'name' => 'Injected item']);
    }
}
