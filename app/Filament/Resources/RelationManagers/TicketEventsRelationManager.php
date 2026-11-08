<?php

namespace App\Filament\Resources\RelationManagers;

class TicketEventsRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $title = 'Riwayat Tindakan';
}
