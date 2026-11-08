<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\LoanEventResource;

class G001M001UnitLoanEventsRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'loanEvents';

    protected static string $relatedResource = LoanEventResource::class;

    protected static ?string $title = 'Kegiatan';

    protected static bool $allowMutations = false;
}
