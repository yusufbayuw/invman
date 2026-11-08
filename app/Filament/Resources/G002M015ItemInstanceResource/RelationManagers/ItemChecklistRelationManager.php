<?php

namespace App\Filament\Resources\G002M015ItemInstanceResource\RelationManagers;

use App\Filament\Resources\G009M022ItemInstanceChecklistResource;
use App\Filament\Resources\RelationManagers\ResourceRelationManager;

class ItemChecklistRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'item_instance_checklist';
    protected static string $relatedResource = G009M022ItemInstanceChecklistResource::class;
    protected static ?string $title = 'Checklist Kondisi';
}
