<?php

namespace App\Filament\Resources\RelationManagers;

class TicketMaintenanceRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'maintenance';

    protected static ?string $title = 'Dokumen Perawatan';

    protected static bool $internal = true;
}
