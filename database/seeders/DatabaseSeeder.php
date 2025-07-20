<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            UnitSeeder::class,
            ItemTypeSeeder::class,
            ItemManagementSeeder::class,
            GedungSeeder::class,
            FloorSeeder::class,
            RoomSeeder::class,
            UserSeeder::class,
        ]);
    }
}
