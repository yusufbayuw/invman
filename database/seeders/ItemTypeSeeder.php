<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ItemTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $itemTypes = [
            ['name' => 'Server & Infrastruktur', 'description' => 'Server, infrastruktur jaringan, dan perangkat terkait.', 'created_at' => now()],
            ['name' => 'Komputer & Laptop', 'description' => 'Semua jenis komputer, laptop, dan perangkat terkait.', 'created_at' => now()],
            ['name' => 'Jaringan & Komunikasi', 'description' => 'Perangkat jaringan, komunikasi, dan konektivitas.', 'created_at' => now()],
            ['name' => 'Multimedia & Audio Visual', 'description' => 'Perangkat multimedia, audio, dan visual.', 'created_at' => now()],
            ['name' => 'Perangkat Keras Lainnya', 'description' => 'Kategori umum untuk perangkat keras IT lainnya.', 'created_at' => now()],
            ['name' => 'Aksesori & Periferal', 'description' => 'Aksesori komputer dan periferal lainnya.', 'created_at' => now()],
        ];
        
        \App\Models\G002M002ItemType::insert($itemTypes);
    }
}
