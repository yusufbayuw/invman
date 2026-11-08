<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\G002M015ItemInstanceResource;

class G003M006RoomItemInstancesRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'item_instances';

    protected static string $relatedResource = G002M015ItemInstanceResource::class;

    protected static ?string $title = 'Barang Satuan di Ruangan';
}
