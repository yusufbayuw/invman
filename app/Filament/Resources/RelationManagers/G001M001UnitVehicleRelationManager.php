<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G008M017VehicleResource;

class G001M001UnitVehicleRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'vehicle';

    protected static string $relatedResource = G008M017VehicleResource::class;

    protected static ?string $title = 'Kendaraan';
}
