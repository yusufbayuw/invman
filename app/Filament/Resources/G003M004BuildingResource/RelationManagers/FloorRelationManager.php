<?php

namespace App\Filament\Resources\G003M004BuildingResource\RelationManagers;

use App\Filament\Resources\G003M005FloorResource;
use App\Filament\Resources\RelationManagers\ResourceRelationManager;

class FloorRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'floor';
    protected static string $relatedResource = G003M005FloorResource::class;
    protected static ?string $title = 'Lantai';
}
