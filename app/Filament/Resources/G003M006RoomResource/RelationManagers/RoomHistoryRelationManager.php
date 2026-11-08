<?php

namespace App\Filament\Resources\G003M006RoomResource\RelationManagers;

use App\Filament\Resources\G007M014RoomHistoryResource;
use App\Filament\Resources\RelationManagers\ResourceRelationManager;

class RoomHistoryRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'room_history';
    protected static string $relatedResource = G007M014RoomHistoryResource::class;
    protected static ?string $title = 'Riwayat Ruangan';
}
