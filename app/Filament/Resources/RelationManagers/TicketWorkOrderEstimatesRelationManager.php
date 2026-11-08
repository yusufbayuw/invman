<?php

namespace App\Filament\Resources\RelationManagers;

class TicketWorkOrderEstimatesRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'workOrderEstimates';

    protected static ?string $title = 'Estimasi Biaya';

    protected static bool $internal = true;
}
