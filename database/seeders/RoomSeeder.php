<?php

namespace Database\Seeders;

use App\Models\G001M001Unit;
use App\Models\G003M004Building;
use App\Models\G003M005Floor;
use App\Models\G003M006Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $building = G003M004Building::query()->where('name', 'Gedung 52')->firstOrFail();
        $floors = G003M005Floor::query()
            ->where('g003_m004_building_id', $building->id)
            ->whereIn('name', ['Lantai 1', 'Lantai 2'])
            ->get()
            ->keyBy('name');
        $unit = G001M001Unit::query()->where('name', 'ADM')->firstOrFail();

        $rooms = [
            ['floor' => 'Lantai 1', 'name' => 'Lorong Server', 'is_borrowable' => false, 'capacity' => 30],
            ['floor' => 'Lantai 1', 'name' => 'Ruang Server', 'is_borrowable' => false, 'capacity' => 30],
            ['floor' => 'Lantai 1', 'name' => 'Ruang CCTV', 'is_borrowable' => false, 'capacity' => 30],
            ['floor' => 'Lantai 1', 'name' => 'Aula Lantai 1', 'is_borrowable' => true, 'capacity' => 150],
            ['floor' => 'Lantai 2', 'name' => 'Aula Lantai 2', 'is_borrowable' => true, 'capacity' => 300],
        ];

        foreach ($rooms as $room) {
            G003M006Room::query()->updateOrCreate(
                [
                    'g003_m005_floor_id' => $floors->get($room['floor'])->id,
                    'name' => $room['name'],
                ],
                [
                    'g001_m001_unit_id' => $unit->id,
                    'is_borrowable' => $room['is_borrowable'],
                    'capacity' => $room['capacity'],
                    'status' => 'tersedia',
                ],
            );
        }
    }
}
