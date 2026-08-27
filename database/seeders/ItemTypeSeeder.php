<?php

namespace Database\Seeders;

use App\Models\G002M002ItemType;
use Illuminate\Database\Seeder;

class ItemTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $itemTypes = [
            'Server & Infrastruktur' => 'Server, infrastruktur jaringan, dan perangkat terkait.',
            'Komputer & Laptop' => 'Semua jenis komputer, laptop, dan perangkat terkait.',
            'Jaringan & Komunikasi' => 'Perangkat jaringan, komunikasi, dan konektivitas.',
            'Multimedia & Audio Visual' => 'Perangkat multimedia, audio, dan visual.',
            'Perangkat Keras Lainnya' => 'Kategori umum untuk perangkat keras IT lainnya.',
            'Aksesori & Periferal' => 'Aksesori komputer dan periferal lainnya.',
        ];

        foreach ($itemTypes as $name => $description) {
            G002M002ItemType::query()->updateOrCreate(
                ['name' => $name],
                ['description' => $description],
            );
        }
    }
}
