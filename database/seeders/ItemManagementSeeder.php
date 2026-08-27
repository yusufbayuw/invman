<?php

namespace Database\Seeders;

use App\Models\G002M003ItemManagement;
use Illuminate\Database\Seeder;

class ItemManagementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $itemManagements = [
            'Fasilitas' => 'Dikelola oleh Bidang Fasilitas',
            'IT' => 'Dikelola oleh Bidang IT',
            'Perpustakaan' => 'Dikelola oleh Perpustakaan',
            'Laboratorium' => 'Dikelola oleh Laboratorium',
        ];

        foreach ($itemManagements as $name => $description) {
            G002M003ItemManagement::query()->updateOrCreate(
                ['name' => $name],
                ['description' => $description],
            );
        }
    }
}
