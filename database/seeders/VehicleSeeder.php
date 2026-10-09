<?php

namespace Database\Seeders;

use App\Models\G001M001Unit;
use App\Models\G002M003ItemManagement;
use App\Models\G008M017Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        $unit = G001M001Unit::query()->where('name', 'ADM')->firstOrFail();
        $management = G002M003ItemManagement::query()->where('name', 'Fasilitas')->firstOrFail();

        $this->vehicle('RUSH', 'D 1152 FTB', $unit, $management, 'D 5678 INV');
        $this->vehicle('INNOVA PUTIH', 'D 1505 ABD', $unit, $management);
        $this->vehicle('INNOVA SILVER', 'D 1052 FTB', $unit, $management);
        $this->vehicle('BUS 01', 'D 7292 AS', $unit, $management);
        $this->vehicle('BUS 02', 'D 7293 AS', $unit, $management);
        $this->vehicle('BUS 03', 'D 7294 AS', $unit, $management);
        $this->vehicle('HIACE', 'D 7052 FB', $unit, $management, 'D 1234 INV');
    }

    private function vehicle(
        string $name,
        string $licensePlate,
        G001M001Unit $unit,
        G002M003ItemManagement $management,
        ?string $legacyLicensePlate = null,
    ): void {
        $vehicle = G008M017Vehicle::query()
            ->where('license_plate', $licensePlate)
            ->first();

        if (! $vehicle && $legacyLicensePlate) {
            $vehicle = G008M017Vehicle::query()
                ->where('license_plate', $legacyLicensePlate)
                ->first();
        }

        $vehicle ??= new G008M017Vehicle;
        $vehicle->fill([
            'g001_m001_unit_id' => $unit->id,
            'g002_m003_item_management_id' => $management->id,
            'name' => $name,
            'license_plate' => $licensePlate,
            'stnk_date' => null,
            'kir_date' => null,
            'capacity' => null,
            'is_borrowable' => true,
            'requires_assistant' => in_array($name, ['BUS 01', 'BUS 02', 'BUS 03'], true),
            'status' => 'tersedia',
        ]);
        $vehicle->save();
    }
}
