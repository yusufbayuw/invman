<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G008M017VehicleResource;

class G002M003ItemManagementVehiclesRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'vehicles';

    protected static string $relatedResource = G008M017VehicleResource::class;

    protected static ?string $title = 'Kendaraan';
}
