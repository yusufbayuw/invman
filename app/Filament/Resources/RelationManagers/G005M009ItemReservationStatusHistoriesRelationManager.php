<?php

namespace App\Filament\Resources\RelationManagers;

class G005M009ItemReservationStatusHistoriesRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'statusHistories';

    protected static ?string $title = 'Riwayat Status';
}
