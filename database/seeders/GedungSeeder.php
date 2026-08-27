<?php

namespace Database\Seeders;

use App\Models\G003M004Building;
use Illuminate\Database\Seeder;

class GedungSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $buildings = [
            'Gedung 52' => 'Jl. L.L.R.E. Martadinata 52, Bandung',
            'Gedung 91' => 'Jl. L.L.R.E. Martadinata 91, Bandung',
            'Gedung 93' => 'Jl. L.L.R.E. Martadinata 93, Bandung',
            'Gedung Setiabudi' => 'Jl. Setiabudi 122A, Bandung',
            'Gedung PHH Mustofa' => 'Jl. P.H.H. Mustofa 55, Bandung',
            'Gedung AH Nasution' => 'Jl. Raya Ujung Berung 15e, Bandung',
            'Gedung Kompas' => 'Jl. L.L.R.E. Martadinata 46, Bandung',
        ];

        foreach ($buildings as $name => $location) {
            G003M004Building::query()->updateOrCreate(
                ['name' => $name],
                ['location' => $location],
            );
        }
    }
}
