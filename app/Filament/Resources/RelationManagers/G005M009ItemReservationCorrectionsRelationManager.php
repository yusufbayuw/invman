<?php

namespace App\Filament\Resources\RelationManagers;

class G005M009ItemReservationCorrectionsRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'corrections';

    protected static ?string $title = 'Riwayat Koreksi';
}
