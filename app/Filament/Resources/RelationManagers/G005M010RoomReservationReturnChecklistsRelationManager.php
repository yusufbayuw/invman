<?php

namespace App\Filament\Resources\RelationManagers;

class G005M010RoomReservationReturnChecklistsRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'returnChecklists';

    protected static ?string $title = 'Checklist Pengembalian';
}
