<?php

namespace Database\Seeders;

use App\Models\G003M004Building;
use App\Models\G003M005Floor;
use Illuminate\Database\Seeder;

class FloorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $floorsByBuilding = [
            'Gedung 52' => ['Lantai 1', 'Lantai 2', 'Lantai 3', 'Lantai 4', 'Lantai 5'],
            'Gedung 91' => ['Lantai 1', 'Lantai 2', 'Lantai 3'],
            'Gedung 93' => ['Lantai 1', 'Lantai 2', 'Lantai 3'],
            'Gedung Setiabudi' => ['Lantai Basement', 'Lantai 1', 'Lantai 2', 'Lantai 3', 'Lantai 4'],
            'Gedung AH Nasution' => ['Lantai 1'],
            'Gedung Kompas' => ['Lantai 2'],
        ];

        foreach ($floorsByBuilding as $buildingName => $floorNames) {
            $building = G003M004Building::query()->where('name', $buildingName)->firstOrFail();

            foreach ($floorNames as $floorName) {
                G003M005Floor::query()->updateOrCreate(
                    [
                        'g003_m004_building_id' => $building->id,
                        'name' => $floorName,
                    ],
                    ['map' => ''],
                );
            }
        }
    }
}
