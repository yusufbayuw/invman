<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G007M021VehicleHistoryResource;

class UserVehicleHistoryRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'vehicle_history';

    protected static string $relatedResource = G007M021VehicleHistoryResource::class;

    protected static ?string $title = 'Riwayat Kendaraan';
}
