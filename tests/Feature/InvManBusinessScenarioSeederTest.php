<?php

namespace Tests\Feature;

use App\Models\G001M001Unit;
use App\Models\G003M006Room;
use App\Models\G004M008Activity;
use App\Models\G008M017Vehicle;
use App\Models\G008M018Driver;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\InvManBusinessScenarioSeeder;
use Database\Seeders\InvManRoleMatrixDemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InvManBusinessScenarioSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedDemo(): void
    {
        config()->set('security.seed_demo_users', true);
        $this->seed(RoleSeeder::class);
        $this->seed(InvManRoleMatrixDemoSeeder::class);
        $this->seed(InvManBusinessScenarioSeeder::class);
    }

    public function test_operational_scenarios_create_distinct_demo_assets_and_business_states(): void
    {
        $this->seedDemo();

        $driver = User::query()->where('username', 'driver')->firstOrFail();
        $this->assertSame(1, G008M018Driver::query()->where('user_id', $driver->id)->count());
        $this->assertSame(5, G008M017Vehicle::query()->where('name', 'like', '[DEMO-ORG]%')->count());
        $this->assertSame(10, G003M006Room::query()->where('name', 'like', '[DEMO-ORG]%')->count());
        $this->assertSame(5, G001M001Unit::query()->where('name', 'like', '[DEMO-ORG]%')->count());
        $this->assertSame(21, G004M008Activity::query()->where('name', 'like', '[DEMO-ORG]%')->count());

        $expected = [
            'SD Menunggu Persetujuan IT' => 'submitted',
            'SMP Laptop Disetujui' => 'approved',
            'SMA Buku Ditolak' => 'rejected',
            'Universitas Persetujuan Sebagian' => 'partially_approved',
            'TK Pengajuan Ruangan Dibatalkan' => 'cancelled',
            'SD Kendaraan Driver Ditugaskan' => 'approved',
        ];
        foreach ($expected as $name => $status) {
            $this->assertSame($status,
                G004M008Activity::query()->where('name', '[DEMO-ORG] '.$name)->firstOrFail()->status);
        }

        $vehicleBooking = G004M008Activity::query()
            ->where('name', '[DEMO-ORG] SD Kendaraan Driver Ditugaskan')->firstOrFail()
            ->vehicle_reservation()->firstOrFail();
        $this->assertSame(
            G008M018Driver::query()->where('user_id', $driver->id)->firstOrFail()->id,
            $vehicleBooking->g008_m018_driver_id,
        );

        $expectedTickets = [
            'tk-open' => 'open',
            'sd-triaged' => 'triaged',
            'smp-progress' => 'in_progress',
            'sma-parts' => 'waiting_parts',
            'univ-closed' => 'closed',
            'sd-cancelled' => 'cancelled',
            'sma-resolved' => 'resolved',
        ];
        foreach ($expectedTickets as $key => $status) {
            $this->assertSame($status, Ticket::query()
                ->where('source_key', 'invman:org-demo:ticket:'.$key)->firstOrFail()->status);
        }
    }

    public function test_scenarios_are_idempotent(): void
    {
        $this->seedDemo();
        $counts = [
            G008M017Vehicle::query()->count(),
            G008M018Driver::query()->count(),
            G003M006Room::query()->count(),
            G004M008Activity::query()->count(),
            Ticket::query()->count(),
        ];
        $this->seed(InvManBusinessScenarioSeeder::class);
        $this->assertSame($counts, [
            G008M017Vehicle::query()->count(),
            G008M018Driver::query()->count(),
            G003M006Room::query()->count(),
            G004M008Activity::query()->count(),
            Ticket::query()->count(),
        ]);
    }

    public function test_scenarios_reject_production(): void
    {
        config()->set('app.env', 'production');
        $this->expectException(RuntimeException::class);
        $this->seed(InvManBusinessScenarioSeeder::class);
    }
}
