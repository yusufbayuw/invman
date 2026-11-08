<?php

namespace App\Filament\Resources\RelationManagers;

class G005M010RoomReservationCorrectionsRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'corrections';

    protected static ?string $title = 'Riwayat Koreksi';
}
