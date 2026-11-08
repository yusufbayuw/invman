<?php

namespace App\Filament\Resources\RelationManagers;

class TicketOperationalOverridesRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'operationalOverrides';

    protected static ?string $title = 'Tindakan Mewakili';

    protected static bool $internal = true;
}
