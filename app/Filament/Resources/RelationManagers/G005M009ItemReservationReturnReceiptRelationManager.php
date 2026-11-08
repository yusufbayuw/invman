<?php

namespace App\Filament\Resources\RelationManagers;

class G005M009ItemReservationReturnReceiptRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'returnReceipt';

    protected static ?string $title = 'Bukti Pengembalian';
}
