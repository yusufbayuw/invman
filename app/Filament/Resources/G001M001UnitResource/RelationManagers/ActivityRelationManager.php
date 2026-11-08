<?php

namespace App\Filament\Resources\G001M001UnitResource\RelationManagers;

use App\Filament\Resources\G004M008ActivityResource;
use App\Filament\Resources\RelationManagers\ResourceRelationManager;

class ActivityRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'activity';
    protected static string $relatedResource = G004M008ActivityResource::class;
    protected static ?string $title = 'Pengajuan';
    protected static bool $allowMutations = false;
}
