<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G008M017VehicleResource;

class G008M018DriverDefaultVehiclesRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'defaultVehicles';

    protected static string $relatedResource = G008M017VehicleResource::class;

    protected static ?string $title = 'Kendaraan Default';
}
