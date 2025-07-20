<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ItemManagementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $itemManagements = [
            [
                'name' => 'Fasilitas',
                'description' => 'Dikelola oleh Bidang Fasilitas',
                'created_at' => now(),
            ],
            [
                'name' => 'IT',
                'description' => 'Dikelola oleh Bidang IT',
                'created_at' => now(),
            ],
            [
                'name' => 'Perpustakaan',
                'description' => 'Dikelola oleh Perpustakaan',
                'created_at' => now(),
            ],
            [
                'name' => 'Laboratorium',
                'description' => 'Dikelola oleh Laboratorium',
                'created_at' => now(),
            ],
        ];

        \App\Models\G002M003ItemManagement::insert($itemManagements);
    }
}
