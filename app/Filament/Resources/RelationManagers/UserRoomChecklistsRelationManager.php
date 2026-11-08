<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G009M023RoomChecklistResource;

class UserRoomChecklistsRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'room_checklists';

    protected static string $relatedResource = G009M023RoomChecklistResource::class;

    protected static ?string $title = 'Checklist Ruangan';
}
