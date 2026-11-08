<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G004M008ActivityResource;

class LoanEventRequestsRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'requests';

    protected static string $relatedResource = G004M008ActivityResource::class;

    protected static ?string $title = 'Pengajuan Kegiatan';

    protected static bool $allowMutations = false;
}
