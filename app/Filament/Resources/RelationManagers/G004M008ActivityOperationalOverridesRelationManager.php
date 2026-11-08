<?php

namespace App\Filament\Resources\RelationManagers;

class G004M008ActivityOperationalOverridesRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'operationalOverrides';

    protected static ?string $title = 'Riwayat Tindakan Mewakili';

    protected static bool $internal = true;
}
