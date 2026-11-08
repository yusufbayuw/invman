<?php

namespace App\Filament\Resources\G005M009ItemReservationResource\RelationManagers;

use App\Filament\Resources\G005M016ItemReservationDetailResource;
use App\Filament\Resources\RelationManagers\ResourceRelationManager;

class ItemReservationDetailRelationManager extends ResourceRelationManager
{
    protected static string $relationship = 'item_reservation_detail';
    protected static string $relatedResource = G005M016ItemReservationDetailResource::class;
    protected static ?string $title = 'Alokasi Barang Satuan';
    protected static bool $allowMutations = false;
}
