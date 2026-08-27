<?php

namespace Database\Seeders;

use App\Models\G001M001Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Daycare, KB & TK', 'SD', 'SMP', 'SMA', 'TBU', 'ADM', 'Organ Yayasan'] as $name) {
            G001M001Unit::query()->firstOrCreate(['name' => $name]);
        }
    }
}
