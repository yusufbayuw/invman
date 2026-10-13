<?php

namespace Tests\Feature;

use App\Models\G001M001Unit;
use App\Models\G008M017Vehicle;
use App\Models\User;
use App\Services\TicketService;
use App\Models\TicketCategory;
use App\Services\ProductionSecurityAudit;
use App\Support\AccountPasswordPolicy;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditP1HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeder_is_read_only_in_production(): void
    {
        $unit = G001M001Unit::query()->create(['name' => 'Unit Existing']);
        $vehicle = G008M017Vehicle::query()->create([
            'name' => 'Unit mobil', 'license_plate' => 'D 1234 TEST',
            'g001_m001_unit_id' => $unit->id,
            'is_borrowable' => false, 'status' => 'perlu_perbaikan',
        ]);
        config()->set('app.env', 'production');
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('g008_m017_vehicles', 1);
        $this->assertDatabaseCount('g001_m001_units', 1);
        $this->assertSame('perlu_perbaikan', $vehicle->fresh()->status);
        $this->assertFalse((bool) $vehicle->fresh()->is_borrowable);
    }

    public function test_enabled_account_is_separate_from_chat_presence_and_revocation_kicks_out_session(): void
    {
        Role::query()->firstOrCreate(['name' => config('role.sarpras'), 'guard_name' => 'web']);
        $user = User::factory()->create(['active_status' => false]);
        $user->assignRole(config('role.sarpras'));
        $this->assertTrue($user->is_enabled);
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));

        $user->update(['is_enabled' => false]);
        $this->assertFalse($user->fresh()->canAccessPanel(Filament::getPanel('admin')));
        $this->actingAs($user)->get('/login')->assertForbidden();
        $this->assertGuest();
    }

    public function test_strong_password_policy_matches_provisioning_and_profile(): void
    {
        $rule = AccountPasswordPolicy::rule();
        $this->assertTrue(Validator::make(['password' => 'weak'], ['password' => [$rule]])->fails());
        $this->assertFalse(Validator::make(
            ['password' => 'Strong!2026AccountPass'],
            ['password' => [AccountPasswordPolicy::rule()]],
        )->fails());
    }

    public function test_new_ticket_reports_require_maintenance_category_but_explicit_legacy_remains_available(): void
    {
        Role::query()->firstOrCreate(['name' => config('role.sarpras'), 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole(config('role.sarpras'));
        $legacy = TicketCategory::query()->where('slug', 'damage_room')->firstOrFail();
        $data = [
            'ticket_category_id' => $legacy->id,
            'title' => 'Pengaduan lama',
            'description' => 'Ada kerusakan lama yang perlu ditangani petugas.',
        ];

        try {
            app(TicketService::class)->open($user, $data);
            $this->fail('Legacy category must be rejected for new reports.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('tickets', 0);
        }

        $oldTicket = app(TicketService::class)->openLegacy($user, $data);
        $this->assertFalse($oldTicket->maintenance()->exists());

        $newCategory = TicketCategory::query()->where('slug', 'maintenance_other')->firstOrFail();
        $newTicket = app(TicketService::class)->open($user, [
            ...$data,
            'ticket_category_id' => $newCategory->id,
            'location' => 'Gedung Administrasi',
        ]);
        $this->assertTrue($newTicket->maintenance()->exists());
    }
}
