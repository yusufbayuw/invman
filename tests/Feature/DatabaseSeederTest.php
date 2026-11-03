<?php

namespace Tests\Feature;

use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use App\Models\G004M008Activity;
use App\Models\G008M017Vehicle;
use App\Models\User;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderJobTitle;
use App\Models\WorkOrderWorkflow;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_reference_data_and_is_idempotent(): void
    {
        config()->set('security.seed_demo_users', true);
        config()->set('security.seed_demo_password', 'DemoPass!2026#Secure');
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(7, G001M001Unit::query()->count());
        $this->assertSame(23, User::query()->count());
        $this->assertSame(11, WorkOrderJobTitle::query()->count());
        $this->assertSame(21, WorkOrderAssignment::query()->count());
        $this->assertDatabaseHas('work_order_workflows', ['version' => 1, 'status' => 'draft', 'g001_m001_unit_id' => null]);
        $this->assertSame(0, WorkOrderWorkflow::query()->where('status', 'published')->count());
        $this->assertDatabaseHas('ticket_team_members', ['team_name' => 'Tim Fasilitas Demo', 'position' => 'lead']);
        $this->assertTrue(Hash::check('DemoPass!2026#Secure', User::query()->where('username', 'admin')->firstOrFail()->password));
        $this->assertTrue(Hash::check('DemoPass!2026#Secure', User::query()->where('username', 'sarpras.sd')->firstOrFail()->password));
        $this->assertSame(0, G002M007Item::query()->count());
        $this->assertSame(0, G002M015ItemInstance::query()->count());
        $this->assertSame(7, G008M017Vehicle::query()->count());
        $this->assertSame([
            'D 1052 FTB' => 'INNOVA SILVER',
            'D 1152 FTB' => 'RUSH',
            'D 1505 ABD' => 'INNOVA PUTIH',
            'D 7052 FB' => 'HIACE',
            'D 7292 AS' => 'BUS 01',
            'D 7293 AS' => 'BUS 02',
            'D 7294 AS' => 'BUS 03',
        ], G008M017Vehicle::query()->orderBy('license_plate')->pluck('name', 'license_plate')->all());
        $this->assertSame(0, G004M008Activity::query()->count());
    }
}
