<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G005M019VehicleReservationResource;

class G008M017VehicleVehicleReservationRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'vehicle_reservation';

    protected static string $relatedResource = G005M019VehicleReservationResource::class;

    protected static ?string $title = 'Peminjaman Kendaraan';

    protected static bool $allowMutations = false;
}
