<?php

namespace Database\Seeders;

use App\Models\WorkOrderJobTitle;
use Illuminate\Database\Seeder;

class WorkOrderJobTitleSeeder extends Seeder
{
    public function run(): void
    {
        // Reference names only: a title never grants permissions by itself.
        // Do not reset labels, active state or administrator changes.
        foreach ([
            'Atasan Unit',
            'Kepala Sarpras',
            'Staf Fasilitas',
            'Supervisor Fasilitas',
            'Manager Fasilitas',
            'Staf IT',
            'Supervisor IT',
            'Staf Perpustakaan',
            'Supervisor Perpustakaan',
            'Koordinator Teknisi',
            'Teknisi',
        ] as $name) {
            WorkOrderJobTitle::query()->firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
