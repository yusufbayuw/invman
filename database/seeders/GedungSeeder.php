<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GedungSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $buildings = [
            ['name' => 'Gedung 52', 'location' => 'Jl. L.L.R.E. Martadinata 52, Bandung', 'created_at' => now()],
            ['name' => 'Gedung 91', 'location' => 'Jl. L.L.R.E. Martadinata 91, Bandung', 'created_at' => now()],
            ['name' => 'Gedung 93', 'location' => 'Jl. L.L.R.E. Martadinata 93, Bandung', 'created_at' => now()],
            ['name' => 'Gedung Setiabudi', 'location' => 'Jl. Setiabudi 122A, Bandung', 'created_at' => now()],
            ['name' => 'Gedung PHH Mustofa', 'location' => 'Jl. P.H.H. Mustofa 55, Bandung', 'created_at' => now()],
            ['name' => 'Gedung AH Nasution', 'location' => 'Jl. Raya Ujung Berung 15e, Bandung', 'created_at' => now()],
            ['name' => 'Gedung Kompas', 'location' => 'Jl. L.L.R.E. Martadinata 46, Bandung', 'created_at' => now()],
        ];

        \App\Models\G003M004Building::insert($buildings);
    }
}
