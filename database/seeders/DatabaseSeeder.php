<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            RoleSeeder::class,
            UnitSeeder::class,
            ItemTypeSeeder::class,
            ItemManagementSeeder::class,
            GedungSeeder::class,
            FloorSeeder::class,
            RoomSeeder::class,
            UserSeeder::class,
            VehicleSeeder::class,
        ]);
    }
}
