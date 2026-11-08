<?php

namespace App\Filament\Resources\G001M001UnitResource\RelationManagers;

use App\Filament\Resources\G002M007ItemResource;
use App\Filament\Resources\RelationManagers\ResourceRelationManager;

class ItemRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'item';
    protected static string $relatedResource = G002M007ItemResource::class;
    protected static ?string $title = 'Barang';
}
