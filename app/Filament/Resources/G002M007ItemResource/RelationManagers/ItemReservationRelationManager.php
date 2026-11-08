<?php

namespace App\Filament\Resources\G002M007ItemResource\RelationManagers;

use App\Filament\Resources\G005M009ItemReservationResource;
use App\Filament\Resources\RelationManagers\ResourceRelationManager;

class ItemReservationRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'item_reservation';
    protected static string $relatedResource = G005M009ItemReservationResource::class;
    protected static ?string $title = 'Peminjaman Barang';
    protected static bool $allowMutations = false;
}
