<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G005M019VehicleReservationResource;

class G008M018DriverVehicleReservationRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'vehicle_reservation';

    protected static string $relatedResource = G005M019VehicleReservationResource::class;

    protected static ?string $title = 'Riwayat Peminjaman';

    protected static bool $allowMutations = false;
}
