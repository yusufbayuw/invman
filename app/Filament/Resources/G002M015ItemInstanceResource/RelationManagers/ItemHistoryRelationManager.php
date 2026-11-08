<?php

namespace App\Filament\Resources\G002M015ItemInstanceResource\RelationManagers;

use App\Filament\Resources\G007M013ItemHistoryResource;
use App\Filament\Resources\RelationManagers\ResourceRelationManager;

class ItemHistoryRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'item_history';
    protected static string $relatedResource = G007M013ItemHistoryResource::class;
    protected static ?string $title = 'Riwayat Barang';
}
