<?php

namespace App\Filament\Resources\RelationManagers;

class TicketWorkOrderRevisionsRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'workOrderRevisions';

    protected static ?string $title = 'Revisi Work Order';

    protected static bool $internal = true;
}
