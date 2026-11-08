<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G009M024VehicleChecklistResource;

class G008M017VehicleVehicleChecklistsRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'vehicle_checklists';

    protected static string $relatedResource = G009M024VehicleChecklistResource::class;

    protected static ?string $title = 'Checklist Kendaraan';
}
