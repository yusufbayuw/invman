<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rooms = [
            ['g003_m005_floor_id' => 1, 'g001_m001_unit_id' => 6, 'name' => 'Lorong Server', 'is_borrowable' => false, 'capacity' => 30, 'status' => 'tersedia', 'created_at' => now()],
            ['g003_m005_floor_id' => 1, 'g001_m001_unit_id' => 6, 'name' => 'Ruang Server', 'is_borrowable' => false, 'capacity' => 30, 'status' => 'tersedia', 'created_at' => now()],
            ['g003_m005_floor_id' => 1, 'g001_m001_unit_id' => 6, 'name' => 'Ruang CCTV', 'is_borrowable' => false, 'capacity' => 30, 'status' => 'tersedia', 'created_at' => now()],
            ['g003_m005_floor_id' => 1, 'g001_m001_unit_id' => 6, 'name' => 'Aula Lantai 1', 'is_borrowable' => true, 'capacity' => 150, 'status' => 'tersedia', 'created_at' => now()],
            ['g003_m005_floor_id' => 2, 'g001_m001_unit_id' => 6, 'name' => 'Aula Lantai 2', 'is_borrowable' => true, 'capacity' => 300, 'status' => 'tersedia', 'created_at' => now()],
        ];
        \App\Models\G003M006Room::insert($rooms);
    }
}
