<?php

namespace App\Filament\Resources\G001M001UnitResource\RelationManagers;

use App\Filament\Resources\G003M006RoomResource;
use App\Filament\Resources\RelationManagers\ResourceRelationManager;

class RoomRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'room';
    protected static string $relatedResource = G003M006RoomResource::class;
    protected static ?string $title = 'Ruangan';
}
