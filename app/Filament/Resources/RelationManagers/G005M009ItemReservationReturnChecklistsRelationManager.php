<?php

namespace App\Filament\Resources\RelationManagers;

class G005M009ItemReservationReturnChecklistsRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'returnChecklists';

    protected static ?string $title = 'Checklist Pengembalian';
}
