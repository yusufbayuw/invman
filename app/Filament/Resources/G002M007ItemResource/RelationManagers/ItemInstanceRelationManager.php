<?php

namespace App\Filament\Resources\G002M007ItemResource\RelationManagers;

use App\Filament\Resources\G002M015ItemInstanceResource;
use App\Filament\Resources\RelationManagers\ResourceRelationManager;

class ItemInstanceRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'item_instance';
    protected static string $relatedResource = G002M015ItemInstanceResource::class;
    protected static ?string $title = 'Barang Satuan';
}
