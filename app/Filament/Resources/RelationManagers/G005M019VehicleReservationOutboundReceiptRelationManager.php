<?php

namespace App\Filament\Resources\RelationManagers;

class G005M019VehicleReservationOutboundReceiptRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'outboundReceipt';

    protected static ?string $title = 'Bukti Keluar';
}
