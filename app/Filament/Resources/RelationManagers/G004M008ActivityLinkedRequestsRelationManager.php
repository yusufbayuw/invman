<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G004M008ActivityResource;

class G004M008ActivityLinkedRequestsRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'linkedRequests';

    protected static string $relatedResource = G004M008ActivityResource::class;

    protected static ?string $title = 'Pengajuan Terkait';

    protected static bool $allowMutations = false;
}
