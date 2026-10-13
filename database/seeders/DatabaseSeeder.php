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
        // Production seeding is opt-in by individual class. The default seeder
        // must never reset live vehicle/room availability or reference data.
        if (app()->environment('production') || config('app.env') === 'production') {
            $this->command?->warn('Production: DatabaseSeeder dilewati. Jalankan seeder referensi yang diperlukan secara eksplisit.');

            return;
        }

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
