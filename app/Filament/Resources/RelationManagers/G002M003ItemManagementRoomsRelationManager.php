<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G003M006RoomResource;

class G002M003ItemManagementRoomsRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'rooms';

    protected static string $relatedResource = G003M006RoomResource::class;

    protected static ?string $title = 'Ruangan';
}
