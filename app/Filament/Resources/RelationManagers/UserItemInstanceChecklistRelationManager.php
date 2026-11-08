<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G009M022ItemInstanceChecklistResource;

class UserItemInstanceChecklistRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'item_instance_checklist';

    protected static string $relatedResource = G009M022ItemInstanceChecklistResource::class;

    protected static ?string $title = 'Checklist Barang';
}
