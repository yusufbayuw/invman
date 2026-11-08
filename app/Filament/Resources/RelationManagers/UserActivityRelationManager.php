<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G004M008ActivityResource;

class UserActivityRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'activity';

    protected static string $relatedResource = G004M008ActivityResource::class;

    protected static ?string $title = 'Pengajuan';

    protected static bool $allowMutations = false;
}
