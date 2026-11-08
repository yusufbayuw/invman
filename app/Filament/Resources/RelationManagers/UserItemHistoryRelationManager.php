<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G007M013ItemHistoryResource;

class UserItemHistoryRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'item_history';

    protected static string $relatedResource = G007M013ItemHistoryResource::class;

    protected static ?string $title = 'Riwayat Barang';
}
