<?php

namespace App\Filament\Resources\RelationManagers;

class TicketWorkOrderApprovalHistoryRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'workOrderApprovalHistory';

    protected static ?string $title = 'Riwayat Persetujuan Work Order';

    protected static bool $internal = true;
}
