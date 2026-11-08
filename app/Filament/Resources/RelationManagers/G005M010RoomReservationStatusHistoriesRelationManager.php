<?php

namespace App\Filament\Resources\RelationManagers;

class G005M010RoomReservationStatusHistoriesRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'statusHistories';

    protected static ?string $title = 'Riwayat Status';
}
