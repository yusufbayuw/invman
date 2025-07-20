<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FloorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $floors = [
            ['g003_m004_building_id' => 1, 'name' => 'Lantai 1', 'map' => '', 'created_at' => now()],
            ['g003_m004_building_id' => 1, 'name' => 'Lantai 2', 'map' => '', 'created_at' => now()],
            ['g003_m004_building_id' => 1, 'name' => 'Lantai 3', 'map' => '', 'created_at' => now()],
            ['g003_m004_building_id' => 1, 'name' => 'Lantai 4', 'map' => '', 'created_at' => now()],
            ['g003_m004_building_id' => 1, 'name' => 'Lantai 5', 'map' => '', 'created_at' => now()],
            ['g003_m004_building_id' => 2, 'name' => 'Lantai 1', 'map' => '', 'created_at' => now()],
            ['g003_m004_building_id' => 2, 'name' => 'Lantai 2', 'map' => '', 'created_at' => now()],
            ['g003_m004_building_id' => 2, 'name' => 'Lantai 3', 'map' => '', 'created_at' => now()],
            ['g003_m004_building_id' => 3, 'name' => 'Lantai 1', 'map' => '', 'created_at' => now()],
            ['g003_m004_building_id' => 3, 'name' => 'Lantai 2', 'map' => '', 'created_at' => now()],
            ['g003_m004_building_id' => 3, 'name' => 'Lantai 3', 'map' => '', 'created_at' => now()],
            ['g003_m004_building_id' => 4, 'name' => 'Lantai Basement', 'map' => '', 'created_at' => now()],
            ['g003_m004_building_id' => 4, 'name' => 'Lantai 1', 'map' => '', 'created_at' => now()],
            ['g003_m004_building_id' => 4, 'name' => 'Lantai 2', 'map' => '', 'created_at' => now()],
            ['g003_m004_building_id' => 4, 'name' => 'Lantai 3', 'map' => '', 'created_at' => now()],
            ['g003_m004_building_id' => 4, 'name' => 'Lantai 4', 'map' => '', 'created_at' => now()],
            ['g003_m004_building_id' => 5, 'name' => 'Lantai 1', 'map' => '', 'created_at' => now()],
            ['g003_m004_building_id' => 5, 'name' => 'Lantai 2', 'map' => '', 'created_at' => now()],
            ['g003_m004_building_id' => 6, 'name' => 'Lantai 1', 'map' => '', 'created_at' => now()],
            ['g003_m004_building_id' => 7, 'name' => 'Lantai 2', 'map' => '', 'created_at' => now()],
        ];

        \App\Models\G003M005Floor::insert($floors);
    }
}
