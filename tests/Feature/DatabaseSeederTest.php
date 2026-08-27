<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\G001M001Unit;
use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use App\Models\G004M008Activity;
use App\Models\G008M017Vehicle;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_complete_demo_data_and_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(7, G001M001Unit::query()->count());
        $this->assertSame(8, User::query()->count());
        $this->assertTrue(Hash::check('password', User::query()->where('username', 'admin')->firstOrFail()->password));
        $this->assertTrue(Hash::check('password', User::query()->where('username', 'sarpras.sd')->firstOrFail()->password));
        $this->assertSame(4, G002M007Item::query()->count());
        $this->assertSame(15, G002M015ItemInstance::query()->count());
        $this->assertSame(15, G002M015ItemInstance::query()->where('is_borrowable', true)->count());
        $this->assertSame(2, G008M017Vehicle::query()->count());
        $this->assertSame(2, G004M008Activity::query()->count());

        $this->assertDatabaseHas('g004_m008_activities', [
            'name' => '[DEMO] Rapat Koordinasi SD',
            'status' => ReservationStatus::Approved->value,
        ]);
        $this->assertDatabaseHas('g004_m008_activities', [
            'name' => '[DEMO] Kunjungan Belajar SMP',
            'status' => ReservationStatus::Submitted->value,
        ]);
    }
}
