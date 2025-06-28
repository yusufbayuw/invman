<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $newUser = User::create([
            'name' => 'Admin',
            'email' => 'yusufbayu31@gmail.com',
            'password' => bcrypt('12345678'),
            'email_verified_at' => now(),
            'username' => 'admin',
        ]);

        $unit = [
            ['name' => 'Daycare, KB & TK'],
            ['name' => 'SD'],
            ['name' => 'SMP'],
            ['name' => 'SMA'],
            ['name' => 'TBU'],
            ['name' => 'ADM'],
        ];

        foreach ($unit as $u) {
            \App\Models\G001M001Unit::create($u);
        }

        // run 'php artisan shield:generate --all' inside code
        Artisan::call('shield:generate', [
            '--all' => true,
        ]);

        // Assign the 'super_admin' role to the new user
        $newUser->assignRole('super_admin');
        $newUser->g001_m001_unit_id = 6; // Assign to the first unit
        $newUser->save();

        $itemTypes = [
            ['name' => 'Barang', 'description' => 'Barang'],
            ['name' => 'Alat Tulis Kantor', 'description' => 'Alat Tulis Kantor'],
            ['name' => 'Peralatan Elektronik', 'description' => 'Peralatan Elektronik'],
            ['name' => 'Perabotan Kantor', 'description' => 'Perabotan Kantor'],
        ];

        foreach ($itemTypes as $itemType) {
            \App\Models\G002M002ItemType::create($itemType);
        }

        $itemManagements = [
            ['name' => 'Fasilitas', 'description' => 'Fasilitas Umum'],
            ['name' => 'IT', 'description' => 'Peralatan Teknologi Informasi'],
        ];

        foreach ($itemManagements as $itemManagement) {
            \App\Models\G002M003ItemManagement::create($itemManagement);
        }

        $buildings = [
            ['name' => 'Gedung 52', 'location' => 'Jl. L.L.R.E. Martadinata 52, Bandung'],
            ['name' => 'Gedung 93', 'location' => 'Jl. L.L.R.E. Martadinata 52, Bandung'],
        ];

        foreach ($buildings as $building) {
            \App\Models\G003M004Building::create($building);
        }

        $floors = [
            ['g003_m004_building_id' => 1, 'name' => 'Lantai 1', 'map' => ''],
            ['g003_m004_building_id' => 1, 'name' => 'Lantai 2', 'map' => ''],
            ['g003_m004_building_id' => 1, 'name' => 'Lantai 3', 'map' => ''],
            ['g003_m004_building_id' => 1, 'name' => 'Lantai 4', 'map' => ''],
            ['g003_m004_building_id' => 1, 'name' => 'Lantai 5', 'map' => ''],
            ['g003_m004_building_id' => 2, 'name' => 'Lantai 1', 'map' => ''],
            ['g003_m004_building_id' => 2, 'name' => 'Lantai 2', 'map' => ''],
            ['g003_m004_building_id' => 2, 'name' => 'Lantai 3', 'map' => ''],
        ];

        foreach ($floors as $floor) {
            \App\Models\G003M005Floor::create($floor);
        }

        $rooms = [
            ['g003_m005_floor_id' => 1, 'g001_m001_unit_id' => 1, 'name' => 'Ruang Kelas A', 'is_borrowable' => true, 'capacity' => 30, 'status' => 'Tersedia'],
            ['g003_m005_floor_id' => 1, 'g001_m001_unit_id' => 1, 'name' => 'Ruang Kelas B', 'is_borrowable' => true, 'capacity' => 30, 'status' => 'Tersedia'],
            ['g003_m005_floor_id' => 2, 'g001_m001_unit_id' => 2, 'name' => 'Ruang Kelas C', 'is_borrowable' => true, 'capacity' => 30, 'status' => 'Tersedia'],
            ['g003_m005_floor_id' => 3, 'g001_m001_unit_id' => 3, 'name' => 'Ruang Kelas D', 'is_borrowable' => true, 'capacity' => 30, 'status' => 'Tersedia'],
        ];
        foreach ($rooms as $room) {
            \App\Models\G003M006Room::create($room);
        }

        $items = [
            ['g001_m001_unit_id' => 1, 'g002_m003_item_management_id' => 1, 'g002_m002_item_type_id' => 1, 'g003_m006_room_id' => 1, 'name' => 'Meja Belajar', 'is_borrowable' => true, 'status' => 'Tersedia', 'quantity' => 10],
            ['g001_m001_unit_id' => 1, 'g002_m003_item_management_id' => 1, 'g002_m002_item_type_id' => 2, 'g003_m006_room_id' => 1, 'name' => 'Kursi Belajar', 'is_borrowable' => true, 'status' => 'Tersedia', 'quantity' => 10],
            ['g001_m001_unit_id' => 2, 'g002_m003_item_management_id' => 1, 'g002_m002_item_type_id' => 3, 'g003_m006_room_id' => 2, 'name' => 'Komputer', 'is_borrowable' => true, 'status' => 'Tersedia', 'quantity' => 5],
        ];
        foreach ($items as $item) {
            \App\Models\G002M007Item::create($item);
        }
    }
}
