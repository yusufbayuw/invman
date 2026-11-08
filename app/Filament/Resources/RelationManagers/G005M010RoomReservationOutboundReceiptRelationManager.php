<?php

namespace App\Filament\Resources\RelationManagers;

class G005M010RoomReservationOutboundReceiptRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'outboundReceipt';

    protected static ?string $title = 'Bukti Keluar';
}
