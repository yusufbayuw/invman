<?php

namespace App\Filament\Resources\RelationManagers;

class G005M010RoomReservationOutboundChecklistsRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'outboundChecklists';

    protected static ?string $title = 'Checklist Keluar';
}
