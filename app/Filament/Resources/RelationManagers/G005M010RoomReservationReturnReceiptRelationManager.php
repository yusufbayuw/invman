<?php

namespace App\Filament\Resources\RelationManagers;

class G005M010RoomReservationReturnReceiptRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'returnReceipt';

    protected static ?string $title = 'Bukti Pengembalian';
}
