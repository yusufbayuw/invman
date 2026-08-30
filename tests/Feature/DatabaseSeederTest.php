<?php

namespace Tests\Feature;

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

    public function test_database_seeder_creates_reference_data_and_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(7, G001M001Unit::query()->count());
        $this->assertSame(8, User::query()->count());
        $this->assertTrue(Hash::check('password', User::query()->where('username', 'admin')->firstOrFail()->password));
        $this->assertTrue(Hash::check('password', User::query()->where('username', 'sarpras.sd')->firstOrFail()->password));
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
