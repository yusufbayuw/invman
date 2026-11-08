<?php

namespace App\Filament\Resources\G003M006RoomResource\RelationManagers;

use App\Filament\Resources\G005M010RoomReservationResource;
use App\Filament\Resources\RelationManagers\ResourceRelationManager;

class RoomReservationRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'room_reservation';
    protected static string $relatedResource = G005M010RoomReservationResource::class;
    protected static ?string $title = 'Peminjaman Ruangan';
    protected static bool $allowMutations = false;
}
