<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $unit = [
            ['name' => 'Daycare, KB & TK', 'created_at' => now()],
            ['name' => 'SD', 'created_at' => now()],
            ['name' => 'SMP', 'created_at' => now()],
            ['name' => 'SMA', 'created_at' => now()],
            ['name' => 'TBU', 'created_at' => now()],
            ['name' => 'ADM', 'created_at' => now()],
        ];

        // Create units in bulk for better performance
        \App\Models\G001M001Unit::insert($unit);
    }
}
