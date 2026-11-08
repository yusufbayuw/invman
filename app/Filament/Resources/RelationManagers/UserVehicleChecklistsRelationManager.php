<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G009M024VehicleChecklistResource;

class UserVehicleChecklistsRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'vehicle_checklists';

    protected static string $relatedResource = G009M024VehicleChecklistResource::class;

    protected static ?string $title = 'Checklist Kendaraan';
}
